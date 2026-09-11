<?php

namespace Tests\Feature\Guia;

use App\Models\GuiaSalida;
use App\Models\GuiaSalidaDetalle;
use App\Models\Parametro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Retomar una guia de salida guardada como avance.
 *
 * Esta pantalla estuvo ROTA desde que continuar() se separo de create(): le
 * faltaban cinco variables que la vista necesita -detalle, validar_stock,
 * clienteTransferencia, verChofer y verVehiculo- y moria con "Undefined
 * variable". Nadie podia retomar un avance, y no habia una sola prueba que lo
 * dijera.
 *
 * Todo va con Http::fake: la pantalla tiene que abrir aunque la ApiGRE este
 * apagada, porque lo que se esta recuperando ya esta guardado en la base.
 */
class GuiaSalidaContinuarTest extends TestCase
{
    use RefreshDatabase;

    /** RUC de la empresa. Se compara con el del transportista para saber si el
     *  traslado es privado, que es lo que decide si se piden chofer y vehiculo. */
    private const RUC_EMPRESA = '20100030838';

    protected function setUp(): void
    {
        parent::setUp();

        $this->parametro(2, 'ruc_entiedad', self::RUC_EMPRESA);
        $this->parametro(3, 'razon_social_entidad', 'Franco Supermercado E.I.R.L.');
        $this->parametro(6, 'api_datos', 'http://apigre.local/GREDMK');
        $this->parametro(10, 'validar_stock', 'false');

        // Los estados viven en una tabla; sin esta fila el distintivo no puede
        // decir "Avance", que es justo lo que comprueba una de las pruebas.
        $estado = new \App\Models\GuiaEstado();
        $estado->id = 4; $estado->nombre = 'Avance';
        $estado->save();

        $this->apiRespondiendo();
    }

    private function parametro(int $id, string $nombre, string $valor): void
    {
        $p = new Parametro();
        $p->id = $id; $p->nombre = $nombre; $p->valor = $valor; $p->activo = 1;
        $p->save();
    }

    /** Los catalogos que continuar() pide al abrirse. */
    private function apiRespondiendo(array $extra = []): void
    {
        Http::fake(array_merge([
            '*ObtenerProveedores*'        => Http::response(['proveedores' => []]),
            '*ObtenerFormasPago*'         => Http::response(['formasdePago' => []]),
            '*ObtenerOperacion*'          => Http::response(['operaciones' => []]),
            '*ObtenerAlmacenes*'          => Http::response(['almacenes' => []]),
            '*ObtenerSucursalPrecio*'     => Http::response(['listasPrecio' => []]),
            '*ObtenerTrabajador*'         => Http::response(['trabajador' => []]),
            '*obtenerCliente*'            => Http::response(['cliente' => []]),
            '*ObtenerVehiculo*'           => Http::response(['vehiculos' => []]),
            '*ObtenerChoferes*'           => Http::response(['choferes' => []]),
            '*obtenerSeriesNumerosGuia*'  => Http::response(['serienumeros' => []]),
            '*ObtenerTransportista*'      => Http::response(['transportistas' => []]),
            '*'                           => Http::response([]),
        ], $extra));
    }

    private function avance(array $campos = []): GuiaSalida
    {
        $guia = new GuiaSalida();
        $guia->serie = '1';
        $guia->numero = 4010;
        $guia->fecha_emision = '2026-03-04';
        $guia->hora_emision = '11:22:33';
        $guia->guia_estado_id = 4;            // Avance
        $guia->activo = 1;
        $guia->total_venta = 45.49;
        $guia->monto_igv = 6.94;
        $guia->importe_sin_igv = 38.55;
        $guia->monto_descuento = 0;
        $guia->cliente_razon_social = 'DISTRIBUIDORA ANDINA SAC';
        $guia->cliente_nro_documento = '20445566778';
        $guia->cliente_documento_tipo_nombre = 'RUC';
        $guia->cliente_direccion = 'AV. LA MARINA 2355';
        $guia->transportista_nombre = 'TRANSPORTES RAPIDOS SAC';
        $guia->transportista_ruc = '20601234567';
        $guia->transportista_direccion = 'AV. NICOLAS AYLLON 4500';
        $guia->ubigeo_partida = '150101';
        $guia->direccion_partida = 'Almacen central';
        $guia->ubigeo_llegada = '040101';
        $guia->direccion_llegada = 'Tienda Arequipa';

        foreach ($campos as $k => $v) { $guia->{$k} = $v; }
        $guia->save();

        $linea = new GuiaSalidaDetalle();
        $linea->guia_salida_id = $guia->id;
        $linea->codarticulo = 34297;
        $linea->descripcion = "EP 226ERS L'ORANGE";   // con apostrofe, a proposito
        $linea->cantidad = 3;
        $linea->precio = 7.71;
        $linea->importe = 23.13;
        $linea->porcentaje_descuento = 0;
        $linea->monto_descuento = 0;
        $linea->precio_publico = 9.10;
        $linea->precio_sin_igv = 7.71;
        $linea->costo_articulo = 0;
        $linea->costo_total = 0;
        $linea->desc_unidad_medida = 'UND';
        $linea->save();

        return $guia;
    }

    private function abrir(GuiaSalida $guia)
    {
        return $this->actingAs(User::factory()->create())
                    ->get(route('guiasalida.continuar', ['guia' => $guia->id]));
    }

    // -----------------------------------------------------------------
    // Que abra, que es lo que no hacia
    // -----------------------------------------------------------------

    public function test_retomar_un_avance_abre_la_pantalla(): void
    {
        $this->abrir($this->avance())->assertOk();
    }

    public function test_la_vista_recibe_todo_lo_que_necesita(): void
    {
        $this->abrir($this->avance())
             ->assertOk()
             ->assertViewHasAll([
                 'guia', 'lineasDetalle', 'validar_stock', 'clienteTransferencia',
                 'verChofer', 'verVehiculo', 'listSeries', 'listFormasPago',
                 'listTipoOperacion', 'listPrecios', 'listAlmacenes', 'listClientes',
                 'listVendedores', 'listVehiculos', 'listChoferes',
                 'listAlmacenOrigen', 'listAlmacenDestino',
             ]);
    }

    // -----------------------------------------------------------------
    // Que recupere lo guardado
    // -----------------------------------------------------------------

    public function test_recupera_las_lineas_con_la_forma_que_espera_la_pantalla(): void
    {
        $respuesta = $this->abrir($this->avance())->assertOk();

        $lineas = $respuesta->viewData('lineasDetalle');
        $this->assertCount(1, $lineas);

        // camelCase porque las consume public/js/gre/guia-detalle.js, no un Blade.
        $this->assertSame(34297, (int) $lineas[0]['codArticulo']);
        $this->assertSame(3.0, $lineas[0]['cantidad']);
        $this->assertSame(7.71, $lineas[0]['precioSinIgv']);
        $this->assertStringContainsString("L'ORANGE", $lineas[0]['descripcion']);
    }

    public function test_el_cliente_y_el_transportista_guardados_salen_ya_elegidos(): void
    {
        $html = $this->abrir($this->avance())->assertOk()->getContent();

        // Antes salian en blanco: el buscador se reescribio y hubo que
        // reconstruir lo ya elegido, que select2 daba hecho.
        $this->assertStringContainsString('DISTRIBUIDORA ANDINA SAC', $html);
        $this->assertStringContainsString('20445566778', $html);
        $this->assertStringContainsString('TRANSPORTES RAPIDOS SAC', $html);
        $this->assertStringContainsString('AV. LA MARINA 2355', $html);
    }

    public function test_el_distintivo_dice_el_estado_y_no_nueva(): void
    {
        $html = $this->abrir($this->avance())->assertOk()->getContent();

        $this->assertStringContainsString('gre-etiqueta">Avance', $html);
        $this->assertStringNotContainsString('gre-etiqueta">Nueva', $html);
    }

    public function test_si_el_estado_no_se_puede_resolver_no_se_dice_que_es_nueva(): void
    {
        // Pasa en instalaciones a medio migrar: la guia existe pero su fila de
        // guia_estados no. Decir "Nueva" seria mentirle al usuario, que esta
        // editando una guia que ya existe.
        \App\Models\GuiaEstado::query()->delete();

        $html = $this->abrir($this->avance())->assertOk()->getContent();

        $this->assertStringNotContainsString('gre-etiqueta">Nueva', $html);
    }

    public function test_los_ubigeos_guardados_viajan_al_componente(): void
    {
        $html = $this->abrir($this->avance())->assertOk()->getContent();

        // El componente rehidrata la cascada con estos dos; sin ellos la guia
        // se retoma sin origen ni destino.
        $this->assertStringContainsString('150101', $html);
        $this->assertStringContainsString('040101', $html);
        $this->assertStringContainsString('Almacen central', $html);
    }

    // -----------------------------------------------------------------
    // Chofer y vehiculo: solo en traslado privado
    // -----------------------------------------------------------------

    public function test_con_transportista_ajeno_no_se_piden_chofer_ni_vehiculo(): void
    {
        $respuesta = $this->abrir($this->avance())->assertOk();

        $this->assertSame('display: none', $respuesta->viewData('verChofer'));
        $this->assertSame('display: none', $respuesta->viewData('verVehiculo'));
    }

    public function test_si_la_empresa_se_transporta_a_si_misma_si_se_piden(): void
    {
        // Mismo RUC que la empresa: el traslado es privado.
        $guia = $this->avance(['transportista_ruc' => self::RUC_EMPRESA]);

        $respuesta = $this->abrir($guia)->assertOk();

        $this->assertSame('', $respuesta->viewData('verChofer'));
        $this->assertSame('', $respuesta->viewData('verVehiculo'));
    }

    // -----------------------------------------------------------------
    // Que no dependa de que la ApiGRE este viva
    // -----------------------------------------------------------------

    public function test_abre_aunque_la_apigre_este_caida(): void
    {
        // Lo que se esta recuperando ya esta en la base: que el catalogo no
        // responda no puede impedir retomar el trabajo de ayer.
        Http::fake(['*' => Http::response('', 500)]);

        $this->abrir($this->avance())->assertOk();
    }

    public function test_abre_aunque_la_apigre_responda_algo_inesperado(): void
    {
        // Un HTML de error, o un JSON sin los campos esperados.
        Http::fake(['*' => Http::response('<html>Gateway Timeout</html>', 200)]);

        $this->abrir($this->avance())->assertOk();
    }
}
