<?php

namespace Tests\Feature\Guia;

use App\Models\GuiaIngreso;
use App\Models\Parametro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Guardar una guia de ingreso: lo que falta tiene que decirse, no reventar.
 *
 * Salieron de la prueba de punta a punta: sin serie, el guardado moria con
 * "Undefined array key" (500); y si fallaba la escritura en la base local, el
 * aviso decia "No se pudo registrar en Nube", marcado como exito.
 */
class GuardarIngresoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $p = new Parametro();
        $p->id = 6; $p->nombre = 'api_datos'; $p->valor = 'http://apigre.local/GREDMK'; $p->activo = 1;
        $p->save();

        Http::fake(['*' => Http::response('', 500)]);
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'fecha_emision' => '2026-09-11', 'guardar_avance' => 'false',
            'proveedor_id' => 312, 'proveedor_nombre' => 'PROVEEDOR SAC', 'proveedor_ruc' => '20100055237',
            'tipo_operacion_id' => 37, 'codalmacen' => '1', 'monto_descuento' => 0,
            'importe_sin_igv' => 10, 'monto_igv' => 1.8, 'total_venta' => 11.8, 'comentario' => 'prueba',
            'detalle' => json_encode([[
                'item' => 1, 'codarticulo' => '34297', 'codigo_barra' => '8436567350043', 'descripcion' => 'ARTICULO', 'cantidad' => 1,
                'precio' => 10, 'importe' => 10, 'porcentaje_descuento' => 0, 'monto_descuento' => 0,
                'cod_unidad' => 1, 'desc_unidad_medida' => 'UND', 'sigla_umfe' => 'NIU', 'costo_articulo' => 7,
                'precio_publico' => 11.8, 'precio_sin_igv' => 10, 'peso_unitario' => 0, 'tipo_igv' => 1,
                'bonificacion' => 0, 'es_consignado' => 0,
            ]]),
        ], $extra);
    }

    private function guardar(array $extra)
    {
        return $this->actingAs(User::factory()->create())->postJson(route('guiaingreso.store'), $this->payload($extra));
    }

    public function test_guia_externa_sin_serie_avisa_en_vez_de_un_500(): void
    {
        $this->guardar(['es_guia_interna' => 0, 'serie_externa' => ''])
             ->assertOk()
             ->assertJson(['procede' => false, 'msj' => 'Indique la serie de la guia externa.']);

        $this->assertSame(0, GuiaIngreso::count());
    }

    public function test_guia_externa_sin_el_campo_siquiera_tampoco_revienta(): void
    {
        $this->guardar(['es_guia_interna' => 0])->assertOk()->assertJson(['procede' => false]);
    }

    public function test_guia_interna_sin_serie_avisa_en_vez_de_un_500(): void
    {
        $this->guardar(['es_guia_interna' => 1])
             ->assertOk()
             ->assertJson(['procede' => false, 'msj' => 'Elija la serie de la guia.']);

        $this->assertSame(0, GuiaIngreso::count());
    }

    public function test_un_avance_puede_guardarse_sin_serie(): void
    {
        // El borrador no gasta correlativo: no se le exige serie.
        $this->guardar(['es_guia_interna' => 1, 'guardar_avance' => 'true'])
             ->assertOk()
             ->assertJson(['procede' => true]);
    }

    public function test_si_falla_la_base_local_el_aviso_lo_dice_y_como_error(): void
    {
        // vendedor_id no numerico: MySQL en modo estricto rechaza la fila. Es
        // lo que paso de verdad cuando llego 'v.codigo' desde una plantilla.
        $r = $this->guardar(['es_guia_interna' => 0, 'serie_externa' => '5', 'numero' => 10, 'vendedor_id' => 'v.codigo'])
                  ->assertOk()
                  ->assertJson(['procede' => false]);

        $this->assertStringContainsString('base local', $r->json('msj'));
        $this->assertStringNotContainsString('Nube', $r->json('msj'));
        $this->assertSame('error', $r->json('msj_tipo'));
        $this->assertSame(0, GuiaIngreso::count());
    }
}
