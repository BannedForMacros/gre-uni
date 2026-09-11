<?php

namespace Tests\Feature\Guia;

use App\Models\GuiaEstado;
use App\Models\GuiaIngreso;
use App\Models\GuiaIngresoDetalle;
use App\Models\GuiaSalida;
use App\Models\GuiaSalidaDetalle;
use App\Models\Parametro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * El buscador de articulos y la carga de lineas desde otra guia.
 *
 * Son las dos maneras de llenar el detalle: buscar articulo por articulo en la
 * ApiGRE, o traerse entero el detalle de una guia anulada/eliminada para no
 * volver a escanearlo. En ambos casos el resultado va a guia-detalle.js, que
 * arma la tabla con lo que recibe: las claves de cada linea son un contrato.
 *
 * Y la regla de siempre: la ApiGRE caida o respondiendo cualquier cosa no
 * puede ser un 500. El buscador se pinta vacio y el usuario reintenta.
 */
class ArticulosYOtrasGuiasTest extends TestCase
{
    use RefreshDatabase;

    /** Un articulo tal como lo devuelve ObtenerArticulo en el DataMart. */
    private const ARTICULO = [
        'codArticulo' => 34297, 'codBarra' => '843656735001', 'nombreArticulo' => "EP 226ERS L'ORANGE",
        'precioPublico' => 9.10, 'precioSinIGV' => 7.71, 'costoArticulo' => 5.25, 'peso' => 0.4,
        'codUnidad' => 9, 'descUnidadMedida' => 'UNIDAD', 'siglaUMFE' => 'NIU', 'stock' => 12.5, 'tipoIgv' => 1,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $p = new Parametro();
        $p->id = 6; $p->nombre = 'api_datos'; $p->valor = 'http://apigre.local/GREDMK'; $p->activo = 1;
        $p->save();
    }

    private function usuario(): User
    {
        return User::factory()->create();
    }

    private function apiConArticulos(array $articulos): void
    {
        Http::fake([
            '*ObtenerArticulo' => Http::response(['articulos' => $articulos]),
            '*'                => Http::response('', 500),
        ]);
    }

    private function apiCaida(): void
    {
        Http::fake(['*' => Http::response('', 500)]);
    }

    private function apiRespondeHtml(): void
    {
        Http::fake(['*' => Http::response('<html><body>Service Unavailable</body></html>', 503)]);
    }

    private function apiRespondeOtroJson(): void
    {
        Http::fake(['*' => Http::response(['exito' => false, 'mensaje' => 'Sin permisos'])]);
    }

    public function rutasListar(): array
    {
        return ['ingreso' => ['guiaingreso.listarArticulos'], 'salida' => ['guiasalida.listarArticulos']];
    }

    public function rutasBarra(): array
    {
        return ['ingreso' => ['guiaingreso.buscarArticuloBarra'], 'salida' => ['guiasalida.buscarArticuloBarra']];
    }

    public function rutasModal(): array
    {
        return ['ingreso' => ['guiaingreso.modalOtrasGuias'], 'salida' => ['guiasalida.modalOtrasGuias']];
    }

    public function rutasCargar(): array
    {
        return ['ingreso' => ['guiaingreso.cargarOtraGuia'], 'salida' => ['guiasalida.cargarOtraGuia']];
    }

    // -----------------------------------------------------------------
    // listarArticulos (ingreso y salida)
    // -----------------------------------------------------------------

    /** Lo que guia-form.js manda al buscar por nombre. */
    private function consultaArticulos(array $extra = []): array
    {
        return array_merge([
            'term' => 'orange', 'tipo_busqueda_articulo' => 4,
            'codalmacen' => 1, 'codlistaprecio' => 2, 'codestacion' => 3,
        ], $extra);
    }

    /** @dataProvider rutasListar */
    public function test_el_buscador_devuelve_cada_articulo_con_lo_que_la_pantalla_necesita(string $ruta): void
    {
        $this->apiConArticulos([self::ARTICULO]);

        $r = $this->actingAs($this->usuario())
                  ->get(route($ruta, $this->consultaArticulos()))
                  ->assertOk();

        $items = $r->json('items');
        $this->assertCount(1, $items);

        // Claves que guia-form.js pasa a agregarItem tal cual.
        foreach (['id', 'text', 'codigo_barra', 'descripcion', 'precio_publico', 'precio_sin_igv',
                  'peso', 'cod_unidad', 'desc_unidad_medida', 'sigla_umfe', 'costo_articulo'] as $clave) {
            $this->assertArrayHasKey($clave, $items[0], "Falta {$clave} en {$ruta}");
        }
        $this->assertSame(34297, $items[0]['id']);
        $this->assertSame("EP 226ERS L'ORANGE", $items[0]['descripcion']);
        $this->assertSame('843656735001', $items[0]['codigo_barra']);
        $this->assertSame('NIU', $items[0]['sigla_umfe']);

        // El tipo de busqueda y el contexto de almacen tienen que llegar a la
        // API: la ruta es GET y antes se leia con post(), que siempre era null.
        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/ObtenerArticulo')
                && $request['valor'] === 'orange'
                && (int) $request['tipoconsulta'] === 4
                && (int) $request['codalmacen'] === 1
                && (int) $request['codlistaprecio'] === 2
                && (int) $request['codestacion'] === 3;
        });
    }

    /** @dataProvider rutasListar */
    public function test_el_buscador_con_la_apigre_caida_devuelve_lista_vacia(string $ruta): void
    {
        $this->apiCaida();

        $this->actingAs($this->usuario())
             ->get(route($ruta, $this->consultaArticulos()))
             ->assertOk()
             ->assertJson(['items' => []]);
    }

    /** @dataProvider rutasListar */
    public function test_el_buscador_con_html_o_json_inesperado_no_revienta(string $ruta): void
    {
        $this->apiRespondeHtml();
        $this->actingAs($this->usuario())->get(route($ruta, $this->consultaArticulos()))
             ->assertOk()->assertJson(['items' => []]);

        $this->apiRespondeOtroJson();
        $this->actingAs($this->usuario())->get(route($ruta, $this->consultaArticulos()))
             ->assertOk()->assertJson(['items' => []]);
    }

    /** @dataProvider rutasListar */
    public function test_por_nombre_con_dos_letras_ya_consulta(string $ruta): void
    {
        // Antes hacian falta 3 letras por nombre. Ahora consulta desde la
        // primera y el servidor corta en 20. Con la ApiGRE caida, sin 500.
        $this->apiCaida();

        $this->actingAs($this->usuario())
             ->get(route($ruta, $this->consultaArticulos(['term' => 'or'])))
             ->assertOk()->assertJson(['items' => []]);

        Http::assertSentCount(1);
    }

    public function test_en_salida_viajan_el_stock_y_si_es_afecto(): void
    {
        // Salida ademas manda stock (para validarlo al agregar) y afecto, y
        // el texto del combo muestra la unidad y el stock.
        $this->apiConArticulos([self::ARTICULO]);

        $r = $this->actingAs($this->usuario())
                  ->get(route('guiasalida.listarArticulos', $this->consultaArticulos()))
                  ->assertOk();

        $this->assertSame(12.5, $r->json('items.0.stock'));
        $this->assertSame(1, $r->json('items.0.afecto'));
        $this->assertStringContainsString('stock 12.5', $r->json('items.0.text'));
    }

    public function test_en_salida_un_articulo_inafecto_sale_sin_igv_y_con_el_mismo_precio_en_ambos(): void
    {
        $this->apiConArticulos([array_merge(self::ARTICULO, ['tipoIgv' => 2])]);

        $r = $this->actingAs($this->usuario())
                  ->get(route('guiasalida.listarArticulos', $this->consultaArticulos()))
                  ->assertOk();

        $this->assertSame(0, $r->json('items.0.afecto'));
        $this->assertSame('7.71', $r->json('items.0.precio_sin_igv'));
        $this->assertSame('7.71', $r->json('items.0.precio_publico'));
    }

    public function test_en_salida_con_proveedor_los_precios_salen_del_costo(): void
    {
        // Una salida a proveedor (devolucion) se valora al costo, no al precio
        // de venta: sin IGV es el costo y el publico es el costo mas IGV.
        $this->apiConArticulos([self::ARTICULO]);

        $r = $this->actingAs($this->usuario())
                  ->get(route('guiasalida.listarArticulos', $this->consultaArticulos(['indicar_proveedor' => 'true'])))
                  ->assertOk();

        $this->assertSame('5.25', $r->json('items.0.precio_sin_igv'));
        $this->assertSame('6.20', $r->json('items.0.precio_publico'));
    }

    // -----------------------------------------------------------------
    // buscarArticuloBarra (ingreso y salida)
    // -----------------------------------------------------------------

    private function consultaBarra(): array
    {
        return ['producto_valor' => '843656735001', 'codestacion' => 3, 'codalmacen' => 1, 'codlistaprecio' => 2];
    }

    /** @dataProvider rutasBarra */
    public function test_por_codigo_de_barras_devuelve_el_articulo_y_procede(string $ruta): void
    {
        $this->apiConArticulos([self::ARTICULO]);

        $r = $this->actingAs($this->usuario())
                  ->post(route($ruta), $this->consultaBarra())
                  ->assertOk()
                  ->assertJson(['procede' => true, 'msj_tipo' => 'success']);

        $this->assertSame(34297, $r->json('getArticulo.codArticulo'));
        $this->assertSame("EP 226ERS L'ORANGE", $r->json('getArticulo.nombreArticulo'));
        $this->assertSame('843656735001', $r->json('getArticulo.codBarra'));
        // Ambos controladores exponen el costo con el nombre que usa el form.
        $this->assertSame(5.25, (float) $r->json('getArticulo.costo_articulo'));

        // Por barra la consulta es siempre tipo 1, escriba lo que escriba el usuario.
        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/ObtenerArticulo')
                && $request['valor'] === '843656735001'
                && (int) $request['tipoconsulta'] === 1;
        });
    }

    /** @dataProvider rutasBarra */
    public function test_un_codigo_que_no_existe_avisa_sin_reventar(string $ruta): void
    {
        $this->apiConArticulos([]);

        $this->actingAs($this->usuario())
             ->post(route($ruta), $this->consultaBarra())
             ->assertOk()
             ->assertJson(['procede' => false, 'msj_tipo' => 'error']);
    }

    /** @dataProvider rutasBarra */
    public function test_por_barra_con_la_apigre_caida_responde_procede_false(string $ruta): void
    {
        // En salida $getArticulo no se inicializaba: cuando el try fallaba, el
        // json de respuesta lo leia sin definir y la ruta moria con 500 en vez
        // de avisar "ocurrio un problema al buscar".
        $this->apiCaida();

        $this->actingAs($this->usuario())
             ->post(route($ruta), $this->consultaBarra())
             ->assertOk()
             ->assertJson(['procede' => false, 'msj_tipo' => 'error'])
             ->assertJsonPath('getArticulo', null);
    }

    /** @dataProvider rutasBarra */
    public function test_por_barra_con_html_o_json_inesperado_tampoco_revienta(string $ruta): void
    {
        $this->apiRespondeHtml();
        $this->actingAs($this->usuario())->post(route($ruta), $this->consultaBarra())
             ->assertOk()->assertJson(['procede' => false]);

        $this->apiRespondeOtroJson();
        $this->actingAs($this->usuario())->post(route($ruta), $this->consultaBarra())
             ->assertOk()->assertJson(['procede' => false]);
    }

    public function test_en_salida_un_inafecto_por_barra_sale_con_afecto_cero(): void
    {
        $this->apiConArticulos([array_merge(self::ARTICULO, ['tipoIgv' => 2])]);

        $r = $this->actingAs($this->usuario())
                  ->post(route('guiasalida.buscarArticuloBarra'), $this->consultaBarra())
                  ->assertOk()->assertJson(['procede' => true]);

        $this->assertSame(0, $r->json('getArticulo.afecto'));
        $this->assertSame('7.71', $r->json('getArticulo.precioPublico'));
    }

    // -----------------------------------------------------------------
    // modalOtrasGuias, buscarOtrasGuias y cargarOtraGuia
    // -----------------------------------------------------------------

    private function estados(): void
    {
        foreach ([1 => 'Generada', 2 => 'Aceptada', 4 => 'Avance'] as $id => $nombre) {
            $e = new GuiaEstado();
            $e->id = $id; $e->nombre = $nombre;
            $e->save();
        }
    }

    private function salida(array $campos = [], int $lineas = 0): GuiaSalida
    {
        $g = new GuiaSalida();
        $g->serie = '1'; $g->numero = 6002;
        $g->fecha_emision = '2026-03-04'; $g->hora_emision = '11:22:33';
        $g->guia_estado_id = 1; $g->activo = 1;
        $g->total_venta = 45.49; $g->monto_igv = 6.94;
        $g->importe_sin_igv = 38.55; $g->monto_descuento = 0;
        $g->cliente_razon_social = 'DISTRIBUIDORA ANDINA SAC';
        $g->cliente_nro_documento = '20445566778';
        $g->indicar_proveedor = 0;
        foreach ($campos as $k => $v) { $g->{$k} = $v; }
        $g->save();

        for ($i = 0; $i < $lineas; $i++) {
            $d = new GuiaSalidaDetalle();
            $d->guia_salida_id = $g->id;
            $d->codarticulo = 34297 + $i; $d->descripcion = "EP 226ERS L'ORANGE";
            $d->cantidad = 3; $d->precio = 7.71; $d->importe = 23.13;
            $d->porcentaje_descuento = 0; $d->monto_descuento = 0;
            $d->precio_publico = 9.10; $d->precio_sin_igv = 7.71;
            $d->costo_articulo = 5.25; $d->costo_total = 15.75;
            $d->desc_unidad_medida = 'UND'; $d->codigo_barra = '843656735001';
            $d->save();
        }

        return $g;
    }

    private function ingreso(array $campos = [], int $lineas = 0): GuiaIngreso
    {
        $g = new GuiaIngreso();
        $g->serie = '1'; $g->numero = 6001;
        $g->fecha_emision = '2026-03-04'; $g->hora_emision = '11:22:33';
        $g->guia_estado_id = 1; $g->activo = 1;
        $g->total_venta = 45.49; $g->monto_igv = 6.94;
        $g->importe_sin_igv = 38.55; $g->monto_descuento = 0;
        $g->proveedor_nombre = 'DISTRIBUIDORA ANDINA SAC'; $g->proveedor_ruc = '20445566778';
        foreach ($campos as $k => $v) { $g->{$k} = $v; }
        $g->save();

        for ($i = 0; $i < $lineas; $i++) {
            $d = new GuiaIngresoDetalle();
            $d->guia_ingreso_id = $g->id;
            $d->codarticulo = 34297 + $i; $d->descripcion = "EP 226ERS L'ORANGE";
            $d->cantidad = 3; $d->precio = 7.71; $d->importe = 23.13;
            $d->porcentaje_descuento = 0; $d->monto_descuento = 0;
            $d->precio_publico = 9.10; $d->precio_sin_igv = 7.71;
            $d->costo_articulo = 5.25; $d->costo_total = 15.75;
            $d->bonificacion = $i; // la segunda linea va bonificada
            $d->desc_unidad_medida = 'UND'; $d->codigo_barra = '843656735001';
            $d->save();
        }

        return $g;
    }

    /** @dataProvider rutasModal */
    public function test_el_modal_de_otras_guias_se_pinta(string $ruta): void
    {
        // En salida la vista se pedia como 'guia\salida\modal_otras_guias',
        // con barras invertidas: en Windows resolvia, en cualquier otro
        // servidor era "View not found" y el boton "Cargar de otra guia" moria
        // con 500. La notacion con puntos vale en todos.
        Http::fake(['*' => Http::response('', 500)]);

        $this->actingAs($this->usuario())
             ->post(route($ruta))
             ->assertOk()
             ->assertSee('id="modalOtrasGuias"', false)
             ->assertSee('id="form_buscar_otras_guias"', false)
             ->assertSee('id="resultados_otras_guias"', false);

        Http::assertNothingSent();
    }

    public function test_en_salida_lista_las_anuladas_aunque_ese_estado_no_este_en_la_tabla(): void
    {
        // El modal solo ofrece "Anuladas" (estado 0), y el estado 0 no existe
        // en guia_estados (anular pone el 0 a mano). GuiaEstado::find(0)
        // devolvia null y ->nombre reventaba: la unica opcion que ofrecia el
        // modal era justo la que no funcionaba.
        Http::fake(['*' => Http::response('', 500)]);
        $this->estados();

        $anulada = $this->salida(['numero' => 7001, 'guia_estado_id' => 0]);
        $viva    = $this->salida(['numero' => 7002, 'guia_estado_id' => 1]);

        $this->actingAs($this->usuario())
             ->post(route('guiasalida.buscarOtrasGuias'), ['estado_id' => 0, 'serie' => '', 'numero' => ''])
             ->assertOk()
             ->assertSee('1-7001')
             ->assertDontSee('1-7002')
             ->assertSee('data-id="' . $anulada->id . '"', false)
             ->assertSee('[20445566778] DISTRIBUIDORA ANDINA SAC');

        Http::assertNothingSent();
    }

    public function test_en_salida_filtra_por_serie_y_numero_y_muestra_el_nombre_del_estado(): void
    {
        Http::fake(['*' => Http::response('', 500)]);
        $this->estados();

        $this->salida(['serie' => '1', 'numero' => 7001, 'guia_estado_id' => 1]);
        $this->salida(['serie' => '1', 'numero' => 7002, 'guia_estado_id' => 1]);
        $this->salida(['serie' => '2', 'numero' => 7001, 'guia_estado_id' => 1]);

        $this->actingAs($this->usuario())
             ->post(route('guiasalida.buscarOtrasGuias'), ['estado_id' => 1, 'serie' => '2', 'numero' => '7001'])
             ->assertOk()
             ->assertSee('2-7001')
             ->assertDontSee('1-7001')
             ->assertDontSee('1-7002')
             ->assertSee('Generada');
    }

    public function test_en_ingreso_lista_las_eliminadas_y_filtra_por_numero(): void
    {
        // Ingreso no anula: elimina (activo = 0). El modal ofrece "Eliminadas".
        Http::fake(['*' => Http::response('', 500)]);
        $this->estados();

        $this->ingreso(['numero' => 8001, 'activo' => 0]);
        $this->ingreso(['numero' => 8002, 'activo' => 0]);
        $this->ingreso(['numero' => 8003, 'activo' => 1]);

        $this->actingAs($this->usuario())
             ->post(route('guiaingreso.buscarOtrasGuias'), ['activo' => 0, 'serie' => '', 'numero' => ''])
             ->assertOk()
             ->assertSee('1-8001')->assertSee('1-8002')->assertDontSee('1-8003');

        $this->actingAs($this->usuario())
             ->post(route('guiaingreso.buscarOtrasGuias'), ['activo' => 0, 'serie' => '', 'numero' => '8002'])
             ->assertOk()
             ->assertSee('1-8002')->assertDontSee('1-8001');

        Http::assertNothingSent();
    }

    public function test_en_ingreso_una_guia_con_estado_desconocido_tampoco_revienta(): void
    {
        Http::fake(['*' => Http::response('', 500)]);
        // Sin sembrar estados: el nombre no se puede resolver.

        $this->ingreso(['numero' => 8001, 'activo' => 0, 'guia_estado_id' => 99]);

        $this->actingAs($this->usuario())
             ->post(route('guiaingreso.buscarOtrasGuias'), ['activo' => 0, 'serie' => '', 'numero' => ''])
             ->assertOk()
             ->assertSee('1-8001');
    }

    /** @dataProvider rutasCargar */
    public function test_cargar_otra_guia_devuelve_las_lineas_con_la_forma_de_la_tabla(string $ruta): void
    {
        // Las claves son las mismas que devuelve agregarItem: una linea
        // recuperada y una recien buscada tienen que ser indistinguibles para
        // guia-detalle.js, que hace lineas = resp.lineas.
        Http::fake(['*' => Http::response('', 500)]);

        $guia = ($ruta === 'guiaingreso.cargarOtraGuia') ? $this->ingreso([], 2) : $this->salida([], 2);

        $r = $this->actingAs($this->usuario())
                  ->post(route($ruta), ['id' => $guia->id])
                  ->assertOk()
                  ->assertJson(['procede' => true])
                  ->assertJsonPath('igv.tasa', 0.18);

        $lineas = $r->json('lineas');
        $this->assertCount(2, $lineas);

        foreach (['codArticulo', 'codigoBarra', 'codPlu', 'descripcion', 'cantidad', 'precioSinIgv',
                  'precioPublico', 'costoArticulo', 'peso', 'codUnidad', 'descUnidadMedida', 'siglaUmfe',
                  'tipoIgv', 'afectoIgv', 'porcentajeDescuento', 'bonificacion', 'esConsignado'] as $clave) {
            $this->assertArrayHasKey($clave, $lineas[0], "Falta {$clave} en {$ruta}");
        }

        $this->assertSame(34297, (int) $lineas[0]['codArticulo']);
        $this->assertSame("EP 226ERS L'ORANGE", $lineas[0]['descripcion']);
        // 3.0 viaja como 3 en JSON; lo que importa es el valor, no el tipo.
        $this->assertEquals(3, $lineas[0]['cantidad']);
        $this->assertSame(7.71, $lineas[0]['precioSinIgv']);
        $this->assertSame(9.1, $lineas[0]['precioPublico']);
        $this->assertSame(5.25, $lineas[0]['costoArticulo']);
        $this->assertSame('843656735001', $lineas[0]['codigoBarra']);

        Http::assertNothingSent();
    }

    public function test_en_ingreso_se_respeta_la_bonificacion_de_cada_linea(): void
    {
        // Una linea bonificada no suma al total; si al recuperarla se perdiera
        // la marca, la guia nueva cobraria lo que la original regalaba.
        Http::fake(['*' => Http::response('', 500)]);

        $guia = $this->ingreso([], 2);

        $r = $this->actingAs($this->usuario())
                  ->post(route('guiaingreso.cargarOtraGuia'), ['id' => $guia->id])
                  ->assertOk();

        $this->assertFalse($r->json('lineas.0.bonificacion'));
        $this->assertTrue($r->json('lineas.1.bonificacion'));
    }

    /** @dataProvider rutasCargar */
    public function test_cargar_una_guia_que_no_existe_devuelve_cero_lineas_sin_reventar(string $ruta): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $this->actingAs($this->usuario())
             ->post(route($ruta), ['id' => 999999])
             ->assertOk()
             ->assertJson(['procede' => true, 'lineas' => []]);
    }
}
