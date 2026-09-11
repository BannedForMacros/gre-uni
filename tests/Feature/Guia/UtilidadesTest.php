<?php

namespace Tests\Feature\Guia;

use App\Models\FacturacionEnvio;
use App\Models\GuiaSalida;
use App\Models\Parametro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Las rutas pequenas de la pantalla de salida (y el modal de registro de
 * ingreso): decidir la modalidad de traslado, pintar el modal de progreso,
 * validar que el mes este abierto en el ERP y servir el PDF que devolvio el
 * facturador.
 *
 * Son rutas chicas, pero cada una se llama en un momento en que el usuario ya
 * tiene la guia armada: si una revienta, se pierde el trabajo. De ahi que se
 * insista en que nunca respondan 500, ni con la ApiGRE ni con el facturador
 * caidos.
 *
 * SIN RED: todo fake termina con el comodin '*'; ni SUNAT ni el facturador se
 * consultan de verdad.
 */
class UtilidadesTest extends TestCase
{
    use RefreshDatabase;

    private const RUC_EMPRESA = '20100030838';
    private const CREDENCIAL  = 'token-de-pruebas';
    private const CONSULTAS   = 'http://facturador.local/consultas';

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            1 => self::CREDENCIAL,
            2 => self::RUC_EMPRESA,
            6 => 'http://apigre.local/GREDMK',
            8 => self::CONSULTAS,
        ] as $id => $valor) {
            $p = new Parametro();
            $p->id = $id; $p->nombre = 'p' . $id; $p->valor = $valor; $p->activo = 1;
            $p->save();
        }
    }

    private function usuario(): User
    {
        return User::factory()->create();
    }

    // -----------------------------------------------------------------
    // getModalidadTraslado
    // -----------------------------------------------------------------

    public function test_si_la_empresa_se_transporta_a_si_misma_es_privado_y_se_piden_chofer_y_vehiculo(): void
    {
        // create.js pone modalidad_traslado en el form y con verChofer muestra
        // los bloques de chofer y vehiculo, que SUNAT exige solo en privado.
        Http::fake(['*' => Http::response('', 500)]);

        $this->actingAs($this->usuario())
             ->post(route('guiasalida.getModalidadTraslado'), ['transportista_ruc' => self::RUC_EMPRESA])
             ->assertOk()
             ->assertExactJson(['modalidad_traslado' => '02', 'verChofer' => true]);

        // Es una comparacion contra el parametro 2: no hay nada que consultar.
        Http::assertNothingSent();
    }

    public function test_con_un_transportista_ajeno_es_publico_y_no_se_piden(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $this->actingAs($this->usuario())
             ->post(route('guiasalida.getModalidadTraslado'), ['transportista_ruc' => '20601234567'])
             ->assertOk()
             ->assertExactJson(['modalidad_traslado' => '01', 'verChofer' => false]);

        Http::assertNothingSent();
    }

    // -----------------------------------------------------------------
    // modalStore (ingreso y salida)
    // -----------------------------------------------------------------

    public function test_el_modal_de_salida_anuncia_el_envio_a_sunat_solo_si_corresponde(): void
    {
        // create.js mete el HTML en #modales y muestra #modalStore; la lista
        // de pasos tiene que reflejar lo que de verdad va a pasar: un avance
        // no se envia a SUNAT, y una guia interna tampoco.
        Http::fake(['*' => Http::response('', 500)]);

        $this->actingAs($this->usuario())
             ->post(route('guiasalida.modalStore'), ['envio_sunat' => 1, 'guardar_avance' => 'false'])
             ->assertOk()
             ->assertSee('id="modalStore"', false)
             ->assertSee('Registrando en DataMart')
             ->assertSee('Enviando a SUNAT');

        $this->actingAs($this->usuario())
             ->post(route('guiasalida.modalStore'), ['envio_sunat' => 1, 'guardar_avance' => 'true'])
             ->assertOk()
             ->assertSee('id="modalStore"', false)
             ->assertDontSee('Enviando a SUNAT');

        $this->actingAs($this->usuario())
             ->post(route('guiasalida.modalStore'), ['envio_sunat' => 0, 'guardar_avance' => 'false'])
             ->assertOk()
             ->assertDontSee('Enviando a SUNAT');

        Http::assertNothingSent();
    }

    public function test_el_modal_de_ingreso_se_pinta_con_sus_dos_pasos(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $this->actingAs($this->usuario())
             ->post(route('guiaingreso.modalStore'))
             ->assertOk()
             ->assertSee('id="modalStore"', false)
             ->assertSee('Registrando guia')
             ->assertSee('Registrando en DataMart');

        Http::assertNothingSent();
    }

    // -----------------------------------------------------------------
    // validarMesAbierto
    // -----------------------------------------------------------------

    public function test_con_el_mes_abierto_procede_y_se_consulta_el_anio_y_mes_de_la_fecha(): void
    {
        // create.js muestra el boton Guardar solo si procede; con procede:false
        // lo esconde y avisa con msj.
        Http::fake([
            '*ValidaMesAbierto' => Http::response(['exito' => true]),
            '*'                 => Http::response('', 500),
        ]);

        $this->actingAs($this->usuario())
             ->post(route('guiasalida.validarMesAbierto'), ['fecha_emision' => '2026-09-11'])
             ->assertOk()
             ->assertJson(['procede' => true]);

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/ValidaMesAbierto')
                && (string) $request['anio'] === '2026'
                && (string) $request['mes'] === '09';
        });
    }

    public function test_con_el_mes_cerrado_no_procede_y_avisa(): void
    {
        Http::fake([
            '*ValidaMesAbierto' => Http::response(['exito' => false]),
            '*'                 => Http::response('', 500),
        ]);

        $this->actingAs($this->usuario())
             ->post(route('guiasalida.validarMesAbierto'), ['fecha_emision' => '2026-09-11'])
             ->assertOk()
             ->assertJson(['procede' => false, 'msj_tipo' => 'error'])
             ->assertJsonPath('msj', 'Mes no esta abierto para emision');
    }

    public function test_con_la_apigre_caida_o_rara_no_procede_en_vez_de_reventar(): void
    {
        // Sin respuesta no se puede afirmar que el mes este abierto: se
        // bloquea el guardado, pero con un aviso, no con un 500.
        $datos = ['fecha_emision' => '2026-09-11'];

        Http::fake(['*' => Http::response('', 500)]);
        $this->actingAs($this->usuario())->post(route('guiasalida.validarMesAbierto'), $datos)
             ->assertOk()->assertJson(['procede' => false]);

        Http::fake(['*' => Http::response('<html>Service Unavailable</html>', 503)]);
        $this->actingAs($this->usuario())->post(route('guiasalida.validarMesAbierto'), $datos)
             ->assertOk()->assertJson(['procede' => false]);

        Http::fake(['*' => Http::response(['mensaje' => 'sin permisos'])]);
        $this->actingAs($this->usuario())->post(route('guiasalida.validarMesAbierto'), $datos)
             ->assertOk()->assertJson(['procede' => false]);
    }

    // -----------------------------------------------------------------
    // pdfDecode
    // -----------------------------------------------------------------

    private function guiaEnviada(): GuiaSalida
    {
        $g = new GuiaSalida();
        $g->serie = '1'; $g->numero = 4020;
        $g->fecha_emision = '2026-03-04'; $g->hora_emision = '11:22:33';
        $g->guia_estado_id = 1; $g->activo = 1; $g->envio_sunat = 1;
        $g->total_venta = 45.49; $g->monto_igv = 6.94;
        $g->importe_sin_igv = 38.55; $g->monto_descuento = 0;
        $g->cliente_razon_social = 'DISTRIBUIDORA ANDINA SAC';
        $g->cliente_nro_documento = '20445566778';
        $g->indicar_proveedor = 0;
        $g->save();

        return $g;
    }

    private function envio(GuiaSalida $guia, ?string $pdfBase64): FacturacionEnvio
    {
        $e = new FacturacionEnvio();
        $e->tabla = 'guia_salidas'; $e->registro_id = $guia->id;
        $e->trama_json = '{}'; $e->exito = 1; $e->activo = 1;
        $e->pdf = $pdfBase64;
        $e->save();

        return $e;
    }

    public function test_con_el_pdf_guardado_lo_sirve_como_application_pdf_sin_consultar_a_nadie(): void
    {
        // El listado abre esta ruta en otra pestana para las guias enviadas a
        // SUNAT. El PDF se guardo en base64 al recibirlo del facturador; aqui
        // se decodifica y se sirve tal cual.
        Http::fake(['*' => Http::response('', 500)]);

        $contenido = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF";
        $guia = $this->guiaEnviada();
        $this->envio($guia, base64_encode($contenido));

        $r = $this->actingAs($this->usuario())
                  ->get(route('guiasalida.pdfDecode', ['guia' => $guia->id]))
                  ->assertOk()
                  ->assertHeader('Content-Type', 'application/pdf');

        $this->assertSame($contenido, $r->getContent());

        Http::assertNothingSent();
    }

    public function test_una_guia_sin_envio_avisa_en_vez_de_reventar(): void
    {
        // La condicion final estaba al reves (!= null): con envio ya se habia
        // respondido antes, y sin envio no devolvia nada. Ahora avisa.
        Http::fake(['*' => Http::response('', 500)]);

        $guia = $this->guiaEnviada();

        $this->actingAs($this->usuario())
             ->get(route('guiasalida.pdfDecode', ['guia' => $guia->id]))
             ->assertOk()
             ->assertSee('No existe envio de este comprobante');

        Http::assertNothingSent();
    }

    public function test_con_envio_sin_pdf_y_el_facturador_caido_avisa_en_vez_de_reventar(): void
    {
        // Es el unico camino que consulta al facturador. Su respuesta se leia
        // a ciegas (->success sobre null) fuera del try: facturador caido era
        // un 500 en la pestana del PDF.
        Http::fake(['*' => Http::response('', 500)]);

        $guia = $this->guiaEnviada();
        $this->envio($guia, null);

        $this->actingAs($this->usuario())
             ->get(route('guiasalida.pdfDecode', ['guia' => $guia->id]))
             ->assertOk()
             ->assertSee('No se pudo obtener el PDF');

        Http::assertSent(function ($request) {
            return $request->url() === self::CONSULTAS && $request['serie'] === 'T001-4020';
        });
        $this->assertNull(FacturacionEnvio::first()->pdf, 'Sin respuesta no hay nada que guardar');
    }
}
