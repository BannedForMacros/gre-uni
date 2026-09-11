<?php

namespace Tests\Feature\Guia;

use App\Models\FacturacionEnvio;
use App\Models\GuiaSalida;
use App\Models\GuiaSalidaDetalle;
use App\Models\Parametro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Envio de la guia de salida al facturador (y de ahi a SUNAT).
 *
 * Es la unica accion del sistema con efecto FUERA de la empresa: una guia
 * emitida ante SUNAT no se puede deshacer, solo anular con otro tramite. Por
 * eso aqui no se comprueba solo que el envio funcione, sino sobre todo cuando
 * NO debe salir: una guia que ya se envio, una anulada, una sin lineas.
 *
 * Todo el trafico va contra Http::fake con un comodin '*': estas pruebas no
 * pueden hablar con ningun servidor real, ni por accidente.
 */
class FacturacionElectronicaTest extends TestCase
{
    use RefreshDatabase;

    private const FACTURADOR = 'http://facturador.local/api/GuiaRemision';
    private const CONSULTAS  = 'http://facturador.local/api/Consulta';
    private const CREDENCIAL = 'credencial-de-prueba';

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            1  => self::CREDENCIAL,
            2  => '20100030838',
            3  => 'Franco Supermercado E.I.R.L.',
            6  => 'http://apigre.local/GREDMK',
            7  => self::FACTURADOR,
            8  => self::CONSULTAS,
            10 => 'false',
        ] as $id => $valor) {
            $p = new Parametro();
            $p->id = $id; $p->nombre = 'p' . $id; $p->valor = $valor; $p->activo = 1;
            $p->save();
        }
    }

    /** El facturador acepta la guia. La consulta dice "no existe" hasta que
     *  se emite, y despues devuelve el PDF: como el facturador real. */
    private function facturadorAcepta(): void
    {
        Http::fake([
            self::FACTURADOR => Http::response([
                'Exito' => true, 'MensajeError' => '', 'CodigoHash' => 'HASH123',
                'CodigoQr' => 'QR123', 'pdf417' => '', 'Pila' => '',
            ]),
            self::CONSULTAS => Http::sequence()
                // Respuesta REAL del facturador (e-dbfact:9191, 11 sep 2026) para un
                // documento que no existe. Ojo: data trae texto, no null. Por
                // eso "existe" exige success, y no basta con que haya data.
                ->push(['success' => false, 'data' => 'El documento no existe'])
                ->push(['success' => true, 'data' => 'JVBERi0=']),
            '*'            => Http::response('', 500),
        ]);
    }

    private function guia(array $campos = [], int $lineas = 1): GuiaSalida
    {
        $g = new GuiaSalida();
        $g->serie = '1'; $g->numero = 4020;
        $g->fecha_emision = '2026-03-04'; $g->hora_emision = '11:22:33';
        $g->fecha_inicio_traslado = '2026-03-05';
        $g->guia_estado_id = 1; $g->activo = 1;
        $g->total_venta = 45.49; $g->monto_igv = 6.94;
        $g->importe_sin_igv = 38.55; $g->monto_descuento = 0;
        $g->cliente_razon_social = 'DISTRIBUIDORA ANDINA S.A.C.';
        $g->cliente_nro_documento = '20445566778';
        $g->cliente_documento_tipo_nombre = 'RUC';
        $g->indicar_proveedor = 0;
        $g->motivo_traslado_id = '01';
        $g->descripcion_motivo_traslado = 'VENTA';
        $g->modalidad_traslado = '02';
        $g->peso_bruto_total = 12.96;
        $g->transportista_ruc = '20601234567';
        $g->transportista_nombre = 'TRANSPORTES RAPIDOS SAC';
        $g->ubigeo_partida = '150101'; $g->direccion_partida = 'Almacen central';
        $g->ubigeo_llegada = '040101'; $g->direccion_llegada = 'Tienda Arequipa';
        foreach ($campos as $k => $v) { $g->{$k} = $v; }
        $g->save();

        for ($i = 0; $i < $lineas; $i++) {
            $d = new GuiaSalidaDetalle();
            $d->guia_salida_id = $g->id;
            $d->codarticulo = 34297 + $i; $d->descripcion = "EP 226ERS L'ORANGE Ñ";
            $d->cantidad = 3; $d->precio = 7.71; $d->importe = 23.13;
            $d->porcentaje_descuento = 0; $d->monto_descuento = 0;
            $d->precio_publico = 9.10; $d->precio_sin_igv = 7.71;
            $d->costo_articulo = 0; $d->costo_total = 0;
            $d->desc_unidad_medida = 'UND'; $d->sigla_umfe = 'NIU';
            $d->codigo_barra = '843656735001';
            $d->save();
        }

        return $g;
    }

    private function enviar(int $id, string $origen = 'create')
    {
        return $this->actingAs(User::factory()->create())
                    ->post(route('guiasalida.facturacionElectronica'), ['id' => $id, 'panel_origen' => $origen]);
    }

    // -----------------------------------------------------------------
    // El camino bueno
    // -----------------------------------------------------------------

    public function test_una_guia_valida_se_envia_una_sola_vez_y_queda_registrada(): void
    {
        $this->facturadorAcepta();
        $guia = $this->guia();

        $this->enviar($guia->id)->assertOk()->assertJson(['procede' => true]);

        // Exactamente UNA emision, al facturador configurado, con la credencial.
        // consulta previa (no existe) + emision + consulta del PDF ya emitido
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $r) => $r->url() === self::CONSULTAS && $r['serie'] === 'T001-4020');
        Http::assertSent(function (Request $r) {
            return $r->url() === self::FACTURADOR
                && $r->method() === 'PUT'
                && $r->hasHeader('Credencial', self::CREDENCIAL)
                && $r['IdDocumento'] === 'T001-4020'
                && $r['TipoDocumento'] === '09'
                && $r['Remitente']['NroDocumento'] === '20100030838'
                && $r['Destinatario']['NroDocumento'] === '20445566778'
                && $r['Destinatario']['TipoDocumento'] === '6'
                && count($r['BienesATransportar']) === 1
                && $r['BienesATransportar'][0]['Cantidad'] == 3;
        });

        $envio = FacturacionEnvio::first();
        $this->assertNotNull($envio, 'Tiene que quedar rastro del envio');
        $this->assertSame('HASH123', $envio->codigo_hash);
        $this->assertSame('JVBERi0=', $envio->pdf, 'El PDF que devuelve el facturador se guarda');

        $fresca = $guia->fresh();
        $this->assertSame(1, (int) $fresca->enviado_facturador);
        $this->assertSame($envio->id, (int) $fresca->envio_id);
    }

    public function test_la_trama_no_lleva_tildes_ni_simbolos_que_el_facturador_rechaza(): void
    {
        $this->facturadorAcepta();
        $guia = $this->guia();

        $this->enviar($guia->id)->assertOk();

        Http::assertSent(function (Request $r) {
            return $r->url() === self::FACTURADOR
                && $r['Destinatario']['NombreRazonSocial'] === 'DISTRIBUIDORA ANDINA SAC'
                && strpos($r['BienesATransportar'][0]['Descripcion'], "L'") === false
                && strpos($r['BienesATransportar'][0]['Descripcion'], 'Ñ') === false;
        });
    }

    public function test_con_dni_el_tipo_de_documento_del_destinatario_es_1(): void
    {
        $this->facturadorAcepta();
        $guia = $this->guia(['cliente_documento_tipo_nombre' => 'DNI', 'cliente_nro_documento' => '45678912']);

        $this->enviar($guia->id)->assertOk();

        Http::assertSent(function (Request $r) {
            return $r->url() === self::FACTURADOR && $r['Destinatario']['TipoDocumento'] === '1';
        });
    }

    // -----------------------------------------------------------------
    // Cuando el facturador dice que no
    // -----------------------------------------------------------------

    public function test_si_el_facturador_rechaza_la_guia_no_queda_como_enviada(): void
    {
        Http::fake([
            self::FACTURADOR => Http::response(['Exito' => false, 'MensajeError' => 'RUC del destinatario no valido']),
            '*'              => Http::response('', 500),
        ]);
        $guia = $this->guia();

        $r = $this->enviar($guia->id)->assertOk()->assertJson(['procede' => false]);

        $this->assertStringContainsString('RUC del destinatario no valido', $r->json('msj'));
        $this->assertSame(0, (int) $guia->fresh()->enviado_facturador);
        $this->assertSame(0, FacturacionEnvio::count());
    }

    public function test_si_el_facturador_esta_caido_la_guia_no_queda_como_enviada(): void
    {
        Http::fake(['*' => Http::response('', 500)]);
        $guia = $this->guia();

        $this->enviar($guia->id)->assertOk()->assertJson(['procede' => false]);

        $this->assertSame(0, (int) $guia->fresh()->enviado_facturador);
    }

    public function test_desde_la_pantalla_de_registro_ofrece_reintentar_y_desde_el_listado_no(): void
    {
        Http::fake(['*' => Http::response('', 500)]);
        $guia = $this->guia();

        $this->assertStringContainsString('btnReintentarFacturar', $this->enviar($guia->id, 'create')->json('msj'));
        $this->assertStringNotContainsString('btnReintentarFacturar', $this->enviar($guia->id, 'index')->json('msj'));
    }

    // -----------------------------------------------------------------
    // Lo que NUNCA debe salir hacia SUNAT
    // -----------------------------------------------------------------

    public function test_una_guia_ya_enviada_no_se_vuelve_a_enviar(): void
    {
        // Emitir dos veces el mismo documento es el peor fallo posible de esta
        // pantalla: el segundo envio no se puede deshacer.
        $this->facturadorAcepta();
        $guia = $this->guia(['enviado_facturador' => 1, 'envio_id' => 77]);

        $this->enviar($guia->id)->assertOk()->assertJson(['procede' => false]);

        Http::assertNothingSent();
    }

    public function test_una_guia_anulada_no_se_envia(): void
    {
        $this->facturadorAcepta();
        $guia = $this->guia(['guia_estado_id' => 0]);

        $this->enviar($guia->id)->assertOk()->assertJson(['procede' => false]);

        Http::assertNothingSent();
    }

    public function test_un_avance_no_se_envia(): void
    {
        $this->facturadorAcepta();
        $guia = $this->guia(['guia_estado_id' => 4]);

        $this->enviar($guia->id)->assertOk()->assertJson(['procede' => false]);

        Http::assertNothingSent();
    }

    public function test_una_guia_sin_lineas_no_se_envia(): void
    {
        // Antes moria con "Undefined variable $body_detalle"; y si no hubiera
        // muerto, habria mandado una guia vacia a SUNAT.
        $this->facturadorAcepta();
        $guia = $this->guia([], 0);

        $this->enviar($guia->id)->assertOk()->assertJson(['procede' => false]);

        Http::assertNothingSent();
    }

    public function test_una_guia_que_no_existe_avisa_en_vez_de_reventar(): void
    {
        $this->facturadorAcepta();

        $this->enviar(999999)->assertOk()->assertJson(['procede' => false]);

        Http::assertNothingSent();
    }

    public function test_sin_facturador_configurado_avisa_en_vez_de_reventar(): void
    {
        Parametro::where('id', 7)->delete();
        $this->facturadorAcepta();
        $guia = $this->guia();

        $this->enviar($guia->id)->assertOk()->assertJson(['procede' => false]);

        Http::assertNothingSent();
    }

    // -----------------------------------------------------------------
    // Reintentar sin emitir dos veces
    // -----------------------------------------------------------------

    public function test_si_falla_el_registro_local_no_queda_ni_envio_ni_guia_marcada_a_medias(): void
    {
        // El facturador acepta, pero algo revienta despues de guardar el
        // envio. Antes quedaba el envio registrado y la guia sin marcar. Con
        // la transaccion no queda ninguno de los dos, y el reintento pasa por
        // la consulta y recupera sin volver a emitir.
        $this->facturadorAcepta();
        $guia = $this->guia();

        FacturacionEnvio::saved(function () {
            throw new \RuntimeException('se cayo la base a mitad');
        });

        $r = $this->enviar($guia->id)->assertOk()->assertJson(['procede' => false]);

        $this->assertStringContainsString('no se pudo registrar localmente', $r->json('msj'));
        $this->assertSame(0, FacturacionEnvio::count(), 'La transaccion tiene que deshacer el envio');
        $this->assertSame(0, (int) $guia->fresh()->enviado_facturador);
    }

    public function test_reintentar_una_guia_que_sunat_ya_tiene_la_recupera_sin_volver_a_emitir(): void
    {
        // Lo que pasa tras el caso anterior: la guia esta emitida en SUNAT y
        // sin registro local. Reintentar NO puede hacer otro PUT.
        Http::fake([
            self::CONSULTAS  => Http::response(['success' => true, 'data' => 'JVBERi0=']),
            self::FACTURADOR => Http::response(['Exito' => true, 'CodigoHash' => 'NO-DEBERIA-LLEGAR']),
            '*'              => Http::response('', 500),
        ]);
        $guia = $this->guia();

        $r = $this->enviar($guia->id)->assertOk()->assertJson(['procede' => true]);

        Http::assertNotSent(fn (Request $req) => $req->url() === self::FACTURADOR);
        $this->assertStringContainsString('ya estaba emitida', $r->json('msj'));

        $envio = FacturacionEnvio::first();
        $this->assertNotNull($envio);
        $this->assertSame('JVBERi0=', $envio->pdf, 'El PDF recuperado queda guardado para verlo');
        $this->assertSame(1, (int) $envio->exito);
        $this->assertSame(1, (int) $guia->fresh()->enviado_facturador);
        $this->assertSame($envio->id, (int) $guia->fresh()->envio_id);
    }

    public function test_si_la_consulta_dice_que_no_existe_entonces_si_se_emite(): void
    {
        $this->facturadorAcepta();
        $guia = $this->guia();

        $this->enviar($guia->id)->assertOk()->assertJson(['procede' => true]);

        Http::assertSent(fn (Request $r) => $r->url() === self::FACTURADOR);
    }

    public function test_si_la_consulta_no_responde_se_emite_igual_como_antes(): void
    {
        // Decision consciente: la consulta caida no puede bloquear la primera
        // emision de cada guia. La ventana de doble emision queda reducida a
        // "fallo el registro local Y la consulta esta caida al reintentar".
        Http::fake([
            self::CONSULTAS  => Http::response('<html>Gateway Timeout</html>', 504),
            self::FACTURADOR => Http::response(['Exito' => true, 'MensajeError' => '', 'CodigoHash' => 'H', 'CodigoQr' => '', 'pdf417' => '', 'Pila' => '']),
            '*'              => Http::response('', 500),
        ]);
        $guia = $this->guia();

        $this->enviar($guia->id)->assertOk()->assertJson(['procede' => true]);

        Http::assertSent(fn (Request $r) => $r->url() === self::FACTURADOR);
        $this->assertNull(FacturacionEnvio::first()->pdf, 'Sin PDF, pero emitida y registrada');
    }
}
