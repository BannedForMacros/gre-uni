<?php

namespace Tests\Feature\Guia;

use App\Models\Parametro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Agregar un articulo al detalle (ingreso y salida).
 *
 * Es la llamada que mas veces hace el almacenero: una por cada articulo que
 * escanea. Antes devolvia HTML concatenado con comillas simples, y una
 * descripcion con apostrofe ("L'ORANGE") truncaba la fila sin aviso. Ahora
 * devuelve datos y el HTML lo arma public/js/gre/guia-detalle.js, que hace
 * lineas.push(resp.linea): las claves de esa linea son un contrato con el JS.
 */
class AgregarItemTest extends TestCase
{
    use RefreshDatabase;

    /** Claves que guia-detalle.js lee de cada linea. Si falta una, la tabla
     *  pinta "undefined" o calcula con NaN, sin ningun error en el servidor. */
    private const CLAVES_LINEA = [
        'codArticulo', 'codigoBarra', 'codPlu', 'descripcion', 'cantidad',
        'precioSinIgv', 'precioPublico', 'costoArticulo', 'peso', 'codUnidad',
        'descUnidadMedida', 'siglaUmfe', 'tipoIgv', 'afectoIgv',
        'porcentajeDescuento', 'bonificacion', 'esConsignado',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([6 => 'http://apigre.local/GREDMK', 10 => 'true'] as $id => $valor) {
            $p = new Parametro();
            $p->id = $id; $p->nombre = 'p' . $id; $p->valor = $valor; $p->activo = 1;
            $p->save();
        }

        // Nada de esto debe tocar la red: agregar una linea es puro calculo.
        Http::fake(['*' => Http::response('', 500)]);
    }

    /** Lo que manda guia-detalle.js tal cual sale del buscador. */
    private function articulo(array $extra = []): array
    {
        return array_merge([
            'producto_id'        => '34297',
            'descripcion'        => "EP 226ERS L'ORANGE",
            'codigo_barra'       => '843656735001',
            'cod_plu'            => '34297',
            'precio_publico'     => '9.10',
            'precio_sin_igv'     => '7.71',
            'precio_visual'      => '7.71',
            'costo_articulo'     => '5.25',
            'peso'               => '0.4',
            'cod_unidad'         => '9',
            'desc_unidad_medida' => 'UNIDAD',
            'sigla_umfe'         => 'NIU',
            'tipo_igv'           => '1',
            'items'              => '[]',
        ], $extra);
    }

    private function agregar(string $ruta, array $datos)
    {
        return $this->actingAs(User::factory()->create())->postJson(route($ruta), $datos);
    }

    public function rutas(): array
    {
        return ['ingreso' => ['guiaingreso.agregarItem'], 'salida' => ['guiasalida.agregarItem']];
    }

    // -----------------------------------------------------------------
    // Contrato con el JS
    // -----------------------------------------------------------------

    /** @dataProvider rutas */
    public function test_devuelve_la_linea_con_todo_lo_que_la_tabla_necesita(string $ruta): void
    {
        $r = $this->agregar($ruta, $this->articulo())->assertOk()->assertJson(['procede' => true]);

        $linea = $r->json('linea');
        foreach (self::CLAVES_LINEA as $clave) {
            $this->assertArrayHasKey($clave, $linea, "Falta {$clave} en la linea que devuelve {$ruta}");
        }

        $this->assertSame('34297', $linea['codArticulo']);
        $this->assertSame(1, $linea['cantidad']);
        $this->assertSame(7.71, $linea['precioSinIgv']);
        $this->assertSame(9.1, $linea['precioPublico']);
        $this->assertSame(5.25, $linea['costoArticulo']);
        $this->assertSame(9, $linea['codUnidad']);
        $this->assertTrue($linea['afectoIgv']);
        $this->assertSame(0.18, $r->json('igv.tasa'));
    }

    /** @dataProvider rutas */
    public function test_el_apostrofe_de_la_descripcion_llega_entero(string $ruta): void
    {
        // El fallo original: "L'ORANGE" se cortaba en la comilla.
        $this->agregar($ruta, $this->articulo())
             ->assertOk()
             ->assertJsonPath('linea.descripcion', "EP 226ERS L'ORANGE");
    }

    /** @dataProvider rutas */
    public function test_un_articulo_inafecto_sale_sin_igv(string $ruta): void
    {
        $this->agregar($ruta, $this->articulo(['tipo_igv' => '2']))
             ->assertOk()
             ->assertJsonPath('linea.tipoIgv', 2)
             ->assertJsonPath('linea.afectoIgv', false);
    }

    // -----------------------------------------------------------------
    // Lo que no se puede agregar
    // -----------------------------------------------------------------

    /** @dataProvider rutas */
    public function test_no_deja_agregar_dos_veces_el_mismo_articulo(string $ruta): void
    {
        $yaCargado = json_encode([['codArticulo' => '34297', 'descripcion' => 'x', 'cantidad' => 2]]);

        $this->agregar($ruta, $this->articulo(['items' => $yaCargado]))
             ->assertStatus(422)
             ->assertJson(['procede' => false]);
    }

    /** @dataProvider rutas */
    public function test_el_mismo_codigo_con_otro_tipo_tambien_cuenta_como_repetido(string $ruta): void
    {
        // El JS manda el codigo como numero cuando viene de un avance y como
        // texto cuando viene del buscador. Son el mismo articulo.
        $yaCargado = json_encode([['codArticulo' => 34297]]);

        $this->agregar($ruta, $this->articulo(['items' => $yaCargado]))->assertStatus(422);
    }

    /** @dataProvider rutas */
    public function test_sin_codigo_o_sin_descripcion_avisa_en_vez_de_agregar_basura(string $ruta): void
    {
        $this->agregar($ruta, $this->articulo(['producto_id' => '']))
             ->assertStatus(422)
             ->assertJsonValidationErrors('producto_id');

        $this->agregar($ruta, $this->articulo(['descripcion' => '']))
             ->assertStatus(422)
             ->assertJsonValidationErrors('descripcion');
    }

    /** @dataProvider rutas */
    public function test_un_precio_que_no_es_numero_se_rechaza(string $ruta): void
    {
        $this->agregar($ruta, $this->articulo(['precio_publico' => 'abc']))
             ->assertStatus(422)
             ->assertJsonValidationErrors('precio_publico');
    }

    // -----------------------------------------------------------------
    // Que precio se toma cuando falta el principal
    // -----------------------------------------------------------------

    public function test_en_ingreso_el_precio_es_el_visual_y_si_falta_el_costo_y_luego_el_publico(): void
    {
        $ruta = 'guiaingreso.agregarItem';

        $this->agregar($ruta, $this->articulo())->assertJsonPath('linea.precioSinIgv', 7.71);

        // Vacio, como llega del formulario cuando el ERP no lo manda.
        $this->agregar($ruta, $this->articulo(['precio_visual' => '']))
             ->assertJsonPath('linea.precioSinIgv', 5.25);

        $this->agregar($ruta, $this->articulo(['precio_visual' => '', 'costo_articulo' => '']))
             ->assertJsonPath('linea.precioSinIgv', 9.1);

        $this->agregar($ruta, $this->articulo(['precio_visual' => '', 'costo_articulo' => '', 'precio_publico' => '']))
             ->assertJsonPath('linea.precioSinIgv', 0);
    }

    public function test_en_salida_el_precio_es_el_de_venta_y_si_falta_el_publico_y_luego_el_costo(): void
    {
        $ruta = 'guiasalida.agregarItem';

        $this->agregar($ruta, $this->articulo())->assertJsonPath('linea.precioSinIgv', 7.71);

        $this->agregar($ruta, $this->articulo(['precio_sin_igv' => '']))
             ->assertJsonPath('linea.precioSinIgv', 9.1);

        $this->agregar($ruta, $this->articulo(['precio_sin_igv' => '', 'precio_publico' => '']))
             ->assertJsonPath('linea.precioSinIgv', 5.25);
    }

    // -----------------------------------------------------------------
    // Solo salida
    // -----------------------------------------------------------------

    public function test_en_salida_viaja_el_stock_y_si_hay_que_validarlo(): void
    {
        $r = $this->agregar('guiasalida.agregarItem', $this->articulo(['stock' => '12.5']))->assertOk();

        $this->assertSame(12.5, $r->json('linea.stock'));
        $this->assertTrue($r->json('validarStock'), 'El parametro 10 esta en true');
    }

    public function test_en_salida_el_buscador_puede_decir_expresamente_si_es_afecto(): void
    {
        // El buscador de salida ya sabe si el articulo es afecto y lo manda;
        // eso manda sobre el tipo_igv.
        $this->agregar('guiasalida.agregarItem', $this->articulo(['tipo_igv' => '1', 'afecto' => '0']))
             ->assertJsonPath('linea.afectoIgv', false);
    }

    public function test_en_salida_sin_parametro_de_stock_no_revienta(): void
    {
        Parametro::where('id', 10)->delete();

        $this->agregar('guiasalida.agregarItem', $this->articulo())
             ->assertOk()
             ->assertJsonPath('validarStock', false);
    }
}
