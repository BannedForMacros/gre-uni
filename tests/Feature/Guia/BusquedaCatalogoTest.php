<?php

namespace Tests\Feature\Guia;

use App\Models\Parametro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Buscadores de catalogo sin minimo de letras y con corte en el servidor.
 *
 * Antes habia que escribir dos o tres letras para ver algo. Ahora se consulta
 * desde la primera letra, o sin texto al abrir la lista, y se corta en 20:
 * a la ApiGRE se le piden 21 para saber si hay mas. Medido en el DataMart de
 * pruebas: sin texto el procedimiento devuelve 6.681 articulos o 12.199
 * clientes, y eso no puede viajar entero hasta el navegador.
 *
 * Todo con Http::fake y comodin: ninguna peticion sale a la red.
 */
class BusquedaCatalogoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $p = new Parametro();
        $p->id = 6; $p->nombre = 'api_datos'; $p->valor = 'http://apigre.local/GREDMK'; $p->activo = 1;
        $p->save();
    }

    /** [ruta, endpoint de la ApiGRE, clave de la respuesta, fabrica de una fila, parametros de la pantalla] */
    public function buscadores(): array
    {
        $articulo = function (int $i) {
            return [
                'codArticulo' => 1000 + $i, 'codBarra' => '77500000' . $i, 'nombreArticulo' => "ARTICULO {$i}",
                'precioPublico' => 11.8, 'precioSinIGV' => 10, 'costoArticulo' => 7, 'stock' => 5, 'peso' => 0.5,
                'codUnidad' => 1, 'descUnidadMedida' => 'UND', 'siglaUMFE' => 'NIU', 'tipoIgv' => 1,
            ];
        };

        return [
            'articulos de ingreso' => ['guiaingreso.listarArticulos', 'ObtenerArticulo', 'articulos', $articulo, ['tipo_busqueda_articulo' => 4]],
            'articulos de salida'  => ['guiasalida.listarArticulos', 'ObtenerArticulo', 'articulos', $articulo, ['tipo_busqueda_articulo' => 4]],
            'proveedores de ingreso' => ['guiaingreso.listarProveedores', 'ObtenerProveedores', 'proveedores',
                function (int $i) { return ['codProveedor' => $i, 'ruc' => '2010000000' . $i, 'nombreproveedor' => "PROVEEDOR {$i}"]; }, ['tipo' => 3]],
            'proveedores de salida' => ['guiasalida.listarProveedores', 'ObtenerProveedores', 'proveedores',
                function (int $i) { return ['codProveedor' => $i, 'ruc' => '2010000000' . $i, 'nombreproveedor' => "PROVEEDOR {$i}"]; }, ['tipo' => 3]],
            'clientes' => ['guiasalida.listarClientes', 'obtenerCliente', 'cliente',
                function (int $i) { return ['codCliente' => $i, 'razonSocial' => "CLIENTE {$i} SAC", 'direccion' => 'AV. X', 'tipoDocumentoIdentidad' => '', 'dni' => '', 'rucCliente' => '2044556677' . $i]; }, ['tipo_busqueda_cliente' => 4]],
            'transportistas' => ['guiasalida.listarTransportistas', 'ObtenerTransportista', 'transportistas',
                function (int $i) { return ['codTransportista' => $i, 'rucTransportista' => '2060123456' . $i, 'nombreTransportista' => "TRANSPORTES {$i}", 'direccionTransportista' => 'AV. Y']; }, []],
        ];
    }

    private function respuesta(string $clave, callable $fila, int $n): array
    {
        $filas = [];
        for ($i = 1; $i <= $n; $i++) { $filas[] = $fila($i); }

        return ['exito' => true, $clave => $filas];
    }

    private function buscar(string $ruta, array $parametros)
    {
        return $this->actingAs(User::factory()->create())->getJson(route($ruta, $parametros));
    }

    /** @dataProvider buscadores */
    public function test_sin_texto_ya_consulta_y_pide_uno_de_mas(string $ruta, string $endpoint, string $clave, callable $fila, array $params): void
    {
        Http::fake(["*{$endpoint}*" => Http::response($this->respuesta($clave, $fila, 3)), '*' => Http::response('', 500)]);

        $this->buscar($ruta, $params + ['term' => ''])->assertOk()->assertJsonCount(3, 'items')->assertJsonPath('hayMas', false);

        Http::assertSent(function (Request $r) use ($endpoint) {
            return strpos($r->url(), $endpoint) !== false && $r['valor'] === '' && $r['limite'] === 21;
        });
    }

    /** @dataProvider buscadores */
    public function test_con_una_sola_letra_tambien_consulta(string $ruta, string $endpoint, string $clave, callable $fila, array $params): void
    {
        Http::fake(["*{$endpoint}*" => Http::response($this->respuesta($clave, $fila, 2)), '*' => Http::response('', 500)]);

        $this->buscar($ruta, $params + ['term' => 'a'])->assertOk()->assertJsonCount(2, 'items');

        Http::assertSentCount(1);
    }

    /** @dataProvider buscadores */
    public function test_corta_en_20_y_avisa_que_hay_mas(string $ruta, string $endpoint, string $clave, callable $fila, array $params): void
    {
        Http::fake(["*{$endpoint}*" => Http::response($this->respuesta($clave, $fila, 21)), '*' => Http::response('', 500)]);

        $this->buscar($ruta, $params + ['term' => ''])->assertOk()->assertJsonCount(20, 'items')->assertJsonPath('hayMas', true);
    }

    /** @dataProvider buscadores */
    public function test_una_apigre_antigua_que_ignora_el_limite_igual_se_corta(string $ruta, string $endpoint, string $clave, callable $fila, array $params): void
    {
        // Un cliente puede actualizar Laravel y quedarse con la ApiGRE de antes,
        // que devuelve TODO: el corte no puede depender de ella.
        Http::fake(["*{$endpoint}*" => Http::response($this->respuesta($clave, $fila, 300)), '*' => Http::response('', 500)]);

        $this->buscar($ruta, $params + ['term' => ''])->assertOk()->assertJsonCount(20, 'items')->assertJsonPath('hayMas', true);
    }

    public function test_el_limite_que_pide_la_pantalla_tiene_tope(): void
    {
        [$ruta, $endpoint, $clave, $fila, $params] = $this->buscadores()['clientes'];
        Http::fake(["*{$endpoint}*" => Http::response($this->respuesta($clave, $fila, 80)), '*' => Http::response('', 500)]);

        $this->buscar($ruta, $params + ['term' => 'SAC', 'limite' => 500])->assertOk()->assertJsonCount(50, 'items')->assertJsonPath('hayMas', true);

        Http::assertSent(function (Request $r) { return $r['limite'] === 51; });
    }

    public function test_articulos_de_salida_con_la_apigre_apagada_avisa_en_vez_de_un_500(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        });

        $this->buscar('guiasalida.listarArticulos', ['term' => 'agua', 'tipo_busqueda_articulo' => 4])
             ->assertStatus(502)
             ->assertJsonPath('items', []);
    }
}
