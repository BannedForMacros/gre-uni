<?php

namespace Tests\Feature\Guia;

use App\Models\Parametro;
use App\Models\Serie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Los catalogos que la pantalla de guias pide a la ApiGRE: proveedores,
 * clientes, transportistas, ubigeos y el correlativo de la serie.
 *
 * Todos comparten la misma regla: la ApiGRE es un servicio ajeno que se cae,
 * que responde HTML cuando IIS esta apagado y que a veces devuelve un JSON
 * con otra forma. Nada de eso puede convertirse en un 500 del buscador,
 * porque el navegador lo unico que ve es "Error interno del servidor" y el
 * almacenero no puede seguir cargando la guia. La respuesta correcta es 200
 * con lista vacia (o procede:false), que el JS ya sabe pintar.
 *
 * SIN RED: cada fake termina con el comodin '*'. Sin el, lo que no encaja con
 * un stub sale a la red de verdad.
 */
class CatalogosTest extends TestCase
{
    use RefreshDatabase;

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

    /** La ApiGRE apagada: todo responde 500 sin cuerpo. */
    private function apiCaida(): void
    {
        Http::fake(['*' => Http::response('', 500)]);
    }

    /** IIS responde con una pagina de error en vez de JSON. */
    private function apiRespondeHtml(): void
    {
        Http::fake(['*' => Http::response('<html><body>Service Unavailable</body></html>', 503)]);
    }

    /** JSON valido pero sin la clave que el controlador espera. */
    private function apiRespondeOtroJson(): void
    {
        Http::fake(['*' => Http::response(['exito' => false, 'mensaje' => 'Sin permisos'])]);
    }

    public function rutasProveedores(): array
    {
        return ['ingreso' => ['guiaingreso.listarProveedores'], 'salida' => ['guiasalida.listarProveedores']];
    }

    public function rutasSerie(): array
    {
        return ['ingreso' => ['guiaingreso.getSerie'], 'salida' => ['guiasalida.getSerie']];
    }

    // -----------------------------------------------------------------
    // listarProveedores (ingreso y salida)
    // -----------------------------------------------------------------

    /** @dataProvider rutasProveedores */
    public function test_proveedores_arma_cada_opcion_con_lo_que_lee_el_combo(string $ruta): void
    {
        // guia-combo.js lee resp.items y de cada uno 'id' y 'text'; la vista
        // ademas guarda proveedor_nombre y proveedor_ruc en el formulario.
        Http::fake([
            '*ObtenerProveedores' => Http::response([
                'proveedores' => [
                    ['codProveedor' => 312, 'ruc' => '20100055237', 'nombreproveedor' => "L'OREAL PERU S.A."],
                ],
            ]),
            '*' => Http::response('', 500),
        ]);

        $this->actingAs($this->usuario())
             ->get(route($ruta, ['term' => 'oreal', 'tipo' => 3]))
             ->assertOk()
             ->assertJson(['items' => [[
                 'id'               => 312,
                 'text'             => "[20100055237] L'OREAL PERU S.A.",
                 'proveedor_nombre' => "L'OREAL PERU S.A.",
                 'proveedor_ruc'    => '20100055237',
             ]]]);

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/ObtenerProveedores')
                && $request['valor'] === 'oreal'
                && (string) $request['tipo'] === '3';
        });
    }

    /** @dataProvider rutasProveedores */
    public function test_proveedores_con_la_apigre_caida_devuelve_lista_vacia(string $ruta): void
    {
        $this->apiCaida();

        $this->actingAs($this->usuario())
             ->get(route($ruta, ['term' => 'distribuidora', 'tipo' => 3]))
             ->assertOk()
             ->assertJson(['items' => []]);
    }

    /** @dataProvider rutasProveedores */
    public function test_proveedores_con_html_o_json_inesperado_no_revienta(string $ruta): void
    {
        $this->apiRespondeHtml();
        $this->actingAs($this->usuario())
             ->get(route($ruta, ['term' => 'distribuidora', 'tipo' => 3]))
             ->assertOk()->assertJson(['items' => []]);

        $this->apiRespondeOtroJson();
        $this->actingAs($this->usuario())
             ->get(route($ruta, ['term' => 'distribuidora', 'tipo' => 3]))
             ->assertOk()->assertJson(['items' => []]);
    }

    /** @dataProvider rutasProveedores */
    public function test_proveedores_por_razon_social_con_dos_letras_ya_consulta(string $ruta): void
    {
        // Antes hacian falta 3 letras y al abrir el buscador no pasaba nada.
        // Ahora consulta desde la primera; el servidor corta en 20 (ver
        // BusquedaCatalogoTest). Con la ApiGRE caida sigue sin dar 500.
        $this->apiCaida();

        $this->actingAs($this->usuario())
             ->get(route($ruta, ['term' => 'di', 'tipo' => 3]))
             ->assertOk()->assertJson(['items' => []]);

        Http::assertSentCount(1);
    }

    // -----------------------------------------------------------------
    // listarClientes (solo salida)
    // -----------------------------------------------------------------

    public function test_clientes_distingue_dni_de_ruc_por_el_tipo_de_documento(): void
    {
        // El DataMart manda tipoDocumentoIdentidad vacio cuando es una empresa;
        // en ese caso el documento es el RUC (que viene con relleno de espacios).
        Http::fake([
            '*obtenerCliente' => Http::response([
                'cliente' => [
                    ['codCliente' => 10, 'tipoDocumentoIdentidad' => '1', 'dni' => '45678912', 'rucCliente' => '', 'razonSocial' => 'PEREZ JUAN', 'direccion' => 'AV. LIMA 123'],
                    ['codCliente' => 11, 'tipoDocumentoIdentidad' => '', 'dni' => '', 'rucCliente' => '20445566778   ', 'razonSocial' => 'ANDINA SAC', 'direccion' => 'JR. UNION 45'],
                ],
            ]),
            '*' => Http::response('', 500),
        ]);

        $r = $this->actingAs($this->usuario())
                  ->get(route('guiasalida.listarClientes', ['term' => 'and', 'tipo_busqueda_cliente' => 1]))
                  ->assertOk();

        $r->assertJson(['items' => [
            ['id' => 10, 'text' => '[45678912] PEREZ JUAN', 'nro_documento' => '45678912', 'documento_tipo_nombre' => 'DNI', 'razon_social' => 'PEREZ JUAN', 'direccion' => 'AV. LIMA 123'],
            ['id' => 11, 'text' => '[20445566778] ANDINA SAC', 'nro_documento' => '20445566778', 'documento_tipo_nombre' => 'RUC', 'razon_social' => 'ANDINA SAC', 'direccion' => 'JR. UNION 45'],
        ]]);

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/obtenerCliente') && $request['valor'] === 'and';
        });
    }

    public function test_clientes_con_la_apigre_caida_o_rara_devuelve_lista_vacia(): void
    {
        $url = route('guiasalida.listarClientes', ['term' => 'andina', 'tipo_busqueda_cliente' => 1]);

        $this->apiCaida();
        $this->actingAs($this->usuario())->get($url)->assertOk()->assertJson(['items' => []]);

        $this->apiRespondeHtml();
        $this->actingAs($this->usuario())->get($url)->assertOk()->assertJson(['items' => []]);

        $this->apiRespondeOtroJson();
        $this->actingAs($this->usuario())->get($url)->assertOk()->assertJson(['items' => []]);
    }

    public function test_clientes_con_dos_letras_ya_consulta(): void
    {
        // Sin minimo de letras: consulta, y con la ApiGRE caida no da 500.
        $this->apiCaida();

        $this->actingAs($this->usuario())
             ->get(route('guiasalida.listarClientes', ['term' => 'an', 'tipo_busqueda_cliente' => 1]))
             ->assertOk()->assertJson(['items' => []]);

        Http::assertSentCount(1);
    }

    // -----------------------------------------------------------------
    // listarTransportistas (solo salida)
    // -----------------------------------------------------------------

    public function test_transportistas_arma_cada_opcion_con_ruc_nombre_y_direccion(): void
    {
        Http::fake([
            '*ObtenerTransportista' => Http::response([
                'transportistas' => [
                    ['codTransportista' => 7, 'rucTransportista' => '20601234567', 'nombreTransportista' => 'TRANSPORTES RAPIDOS SAC', 'direccionTransportista' => 'AV. NICOLAS AYLLON 4500'],
                ],
            ]),
            '*' => Http::response('', 500),
        ]);

        $this->actingAs($this->usuario())
             ->get(route('guiasalida.listarTransportistas', ['term' => 'rapidos']))
             ->assertOk()
             ->assertJson(['items' => [[
                 'id'                      => 7,
                 'text'                    => '[20601234567] TRANSPORTES RAPIDOS SAC',
                 'ruc'                     => '20601234567',
                 'nombre'                  => 'TRANSPORTES RAPIDOS SAC',
                 'transportista_direccion' => 'AV. NICOLAS AYLLON 4500',
             ]]]);
    }

    public function test_transportistas_con_la_apigre_caida_o_rara_devuelve_lista_vacia(): void
    {
        $url = route('guiasalida.listarTransportistas', ['term' => 'rapidos']);

        $this->apiCaida();
        $this->actingAs($this->usuario())->get($url)->assertOk()->assertJson(['items' => []]);

        $this->apiRespondeHtml();
        $this->actingAs($this->usuario())->get($url)->assertOk()->assertJson(['items' => []]);

        $this->apiRespondeOtroJson();
        $this->actingAs($this->usuario())->get($url)->assertOk()->assertJson(['items' => []]);
    }

    // -----------------------------------------------------------------
    // listarUbigeos y getUbigeosPorAlmacen (solo salida)
    // -----------------------------------------------------------------

    public function test_ubigeos_llegan_limpios_de_los_espacios_del_datamart(): void
    {
        // En el DataMart el ubigeo es char(n): viene "15     ". guia-ubigeos.js
        // compara ese codigo con el guardado en la guia, y con los espacios no
        // coincidiria nunca.
        Http::fake([
            '*ObtieneUbigeos' => Http::response([
                'ubigeos' => [
                    ['codUbigeo' => '15     ', 'descripcion' => 'LIMA   '],
                    ['codUbigeo' => '04     ', 'descripcion' => 'AREQUIPA'],
                ],
            ]),
            '*' => Http::response('', 500),
        ]);

        $this->actingAs($this->usuario())
             ->post(route('guiasalida.listarUbigeos'), ['codUbigeo' => '', 'tipo_busqueda' => 1])
             ->assertOk()
             ->assertExactJson([
                 'procede'      => true,
                 'tipoConsulta' => 1,
                 'ubigeos'      => [
                     ['codUbigeo' => '15', 'descripcion' => 'LIMA'],
                     ['codUbigeo' => '04', 'descripcion' => 'AREQUIPA'],
                 ],
             ]);

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/ObtieneUbigeos')
                && (int) $request['tipoConsulta'] === 1;
        });
    }

    public function test_ubigeos_con_la_apigre_caida_o_rara_devuelve_lista_vacia(): void
    {
        // Un ubigeo ausente no puede impedir registrar la guia: la lista sale
        // vacia y procede sigue en true.
        $datos = ['codUbigeo' => '15', 'tipo_busqueda' => 2];

        $this->apiCaida();
        $this->actingAs($this->usuario())->post(route('guiasalida.listarUbigeos'), $datos)
             ->assertOk()->assertJson(['procede' => true, 'ubigeos' => []]);

        $this->apiRespondeHtml();
        $this->actingAs($this->usuario())->post(route('guiasalida.listarUbigeos'), $datos)
             ->assertOk()->assertJson(['procede' => true, 'ubigeos' => []]);

        $this->apiRespondeOtroJson();
        $this->actingAs($this->usuario())->post(route('guiasalida.listarUbigeos'), $datos)
             ->assertOk()->assertJson(['procede' => true, 'ubigeos' => []]);
    }

    public function test_el_almacen_reconstruye_la_cascada_a_partir_de_su_distrito(): void
    {
        // El ubigeo es jerarquico (DDPPDD): de '150101' salen el departamento
        // '15' y la provincia '1501'. El JS espera las tres listas y la
        // seleccion de cada nivel para pintar los tres selects de golpe.
        Http::fake([
            '*ObtieneUbigeos' => Http::response(['ubigeos' => [['codUbigeo' => 'X', 'descripcion' => 'Y']]]),
            '*'               => Http::response('', 500),
        ]);

        $this->actingAs($this->usuario())
             ->post(route('guiasalida.getUbigeosPorAlmacen'), ['tipo' => 2, 'ubigeo' => '150101', 'direccion' => 'Av. Lima 123'])
             ->assertOk()
             ->assertJson([
                 'tipo'          => 2,
                 'direccion'     => 'Av. Lima 123',
                 'departamentos' => [['codUbigeo' => 'X', 'descripcion' => 'Y']],
                 'provincias'    => [['codUbigeo' => 'X', 'descripcion' => 'Y']],
                 'distritos'     => [['codUbigeo' => 'X', 'descripcion' => 'Y']],
                 'seleccion'     => ['departamento' => '15', 'provincia' => '1501', 'distrito' => '150101'],
             ]);

        // Tres consultas, una por nivel, cada una con el padre que le toca.
        foreach ([[1, ''], [2, '15'], [3, '1501']] as [$tipo, $padre]) {
            Http::assertSent(function ($request) use ($tipo, $padre) {
                return str_ends_with($request->url(), '/ObtieneUbigeos')
                    && (int) $request['tipoConsulta'] === $tipo
                    && (string) $request['codigoUbigeo'] === $padre;
            });
        }
        Http::assertSentCount(3);
    }

    public function test_un_almacen_sin_ubigeo_solo_trae_departamentos(): void
    {
        Http::fake([
            '*ObtieneUbigeos' => Http::response(['ubigeos' => [['codUbigeo' => '15', 'descripcion' => 'LIMA']]]),
            '*'               => Http::response('', 500),
        ]);

        $this->actingAs($this->usuario())
             ->post(route('guiasalida.getUbigeosPorAlmacen'), ['tipo' => 1, 'ubigeo' => '', 'direccion' => ''])
             ->assertOk()
             ->assertJson([
                 'departamentos' => [['codUbigeo' => '15', 'descripcion' => 'LIMA']],
                 'provincias'    => [],
                 'distritos'     => [],
                 'seleccion'     => ['departamento' => '', 'provincia' => '', 'distrito' => ''],
             ]);

        Http::assertSentCount(1);
    }

    public function test_el_almacen_con_la_apigre_caida_devuelve_las_tres_listas_vacias(): void
    {
        $datos = ['tipo' => 1, 'ubigeo' => '150101', 'direccion' => 'Av. Lima 123'];
        $vacio = ['departamentos' => [], 'provincias' => [], 'distritos' => [],
                  'seleccion' => ['departamento' => '15', 'provincia' => '1501', 'distrito' => '150101']];

        $this->apiCaida();
        $this->actingAs($this->usuario())->post(route('guiasalida.getUbigeosPorAlmacen'), $datos)
             ->assertOk()->assertJson($vacio);

        $this->apiRespondeHtml();
        $this->actingAs($this->usuario())->post(route('guiasalida.getUbigeosPorAlmacen'), $datos)
             ->assertOk()->assertJson($vacio);
    }

    // -----------------------------------------------------------------
    // getSerie (ingreso y salida)
    // -----------------------------------------------------------------

    /** Lo que responde el DataMart al pedir las series de guia. */
    private function apiConSeries(): void
    {
        Http::fake([
            '*obtenerSeriesNumerosGuia' => Http::response([
                'serienumeros' => [
                    ['numserie' => '001', 'ultimoValormarket' => 1910, 'tipodocumento' => 9],
                    ['numserie' => '002', 'ultimoValormarket' => 35, 'tipodocumento' => 9],
                ],
            ]),
            '*' => Http::response('', 500),
        ]);
    }

    /** @dataProvider rutasSerie */
    public function test_sin_serie_local_el_correlativo_sigue_al_ultimo_del_datamart(string $ruta): void
    {
        // create.js lee response.getSerie.nuevo_numero y lo pone en el
        // campo numero, siempre con 4 digitos.
        $this->apiConSeries();

        $this->actingAs($this->usuario())
             ->post(route($ruta), ['serie' => '002'])
             ->assertOk()
             ->assertJsonPath('getSerie.numserie', '002')
             ->assertJsonPath('getSerie.nuevo_numero', '0036');
    }

    /** @dataProvider rutasSerie */
    public function test_con_serie_local_manda_el_correlativo_de_la_base_propia(string $ruta): void
    {
        // Cuando la serie ya se numera aqui, el ultimo del DataMart se ignora:
        // seguirlo duplicaria numeros.
        $this->apiConSeries();

        $s = new Serie();
        $s->id = 1; $s->documento_tipo_id = 9; $s->serie = '001'; $s->numero = 25; $s->activo = 1;
        $s->save();

        $this->actingAs($this->usuario())
             ->post(route($ruta), ['serie' => '001'])
             ->assertOk()
             ->assertJsonPath('getSerie.nuevo_numero', '0026');
    }

    /** @dataProvider rutasSerie */
    public function test_getserie_con_la_apigre_caida_avisa_en_vez_de_reventar(string $ruta): void
    {
        // Antes ->object()->serienumeros sobre una respuesta vacia terminaba en
        // "Attempt to read property on null" y la pantalla no cargaba el numero
        // sin ningun aviso.
        $this->apiCaida();

        $this->actingAs($this->usuario())
             ->post(route($ruta), ['serie' => '001'])
             ->assertOk()
             ->assertJson(['procede' => false])
             ->assertJsonPath('getSerie', null);
    }

    /** @dataProvider rutasSerie */
    public function test_getserie_con_html_o_json_inesperado_tampoco_revienta(string $ruta): void
    {
        $this->apiRespondeHtml();
        $this->actingAs($this->usuario())->post(route($ruta), ['serie' => '001'])
             ->assertOk()->assertJson(['procede' => false]);

        $this->apiRespondeOtroJson();
        $this->actingAs($this->usuario())->post(route($ruta), ['serie' => '001'])
             ->assertOk()->assertJson(['procede' => false]);
    }

    /** @dataProvider rutasSerie */
    public function test_una_serie_que_el_datamart_no_conoce_avisa_en_vez_de_reventar(string $ruta): void
    {
        // Si la serie del formulario no esta en la lista, $getSerie quedaba sin
        // definir y el metodo moria al leer ->numserie.
        $this->apiConSeries();

        $this->actingAs($this->usuario())
             ->post(route($ruta), ['serie' => '999'])
             ->assertOk()
             ->assertJson(['procede' => false]);
    }
}
