<?php

namespace Tests\Feature\Guia;

use App\Models\GuiaIngreso;
use App\Models\GuiaSalida;
use App\Models\Parametro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Eliminar una guia de ingreso y anular una de salida.
 *
 * Son las dos acciones que DESTRUYEN, y las dos tocan dos sitios: la base local
 * y el DataMart del ERP. Lo que hay que garantizar no es que funcionen cuando
 * todo va bien, sino que no dejen los dos lados contandose historias distintas
 * cuando algo falla.
 *
 * El fallo que ya ocurrio: al eliminar se ponia activo = 0 y, si el DataMart
 * rechazaba la baja, se restauraba el estado pero NO activo. La guia quedaba
 * invisible en el listado y viva en el DataMart: desaparecia de la vista sin
 * haberse borrado de ningun sitio.
 */
class EliminarYAnularTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([2 => 'ruc_entiedad', 3 => 'razon_social_entidad', 6 => 'api_datos', 10 => 'validar_stock'] as $id => $nombre) {
            $p = new Parametro();
            $p->id = $id; $p->nombre = $nombre; $p->activo = 1;
            $p->valor = $id === 6 ? 'http://apigre.local/GREDMK' : 'x';
            $p->save();
        }
    }

    private function usuario(): User
    {
        return User::factory()->create();
    }

    private function guiaIngreso(): GuiaIngreso
    {
        $g = new GuiaIngreso();
        $g->serie = '1'; $g->numero = 7001;
        $g->fecha_emision = '2026-03-04'; $g->hora_emision = '10:00:00';
        $g->guia_estado_id = 1; $g->activo = 1;
        $g->total_venta = 45.49; $g->monto_igv = 6.94;
        $g->importe_sin_igv = 38.55; $g->monto_descuento = 0;
        $g->save();

        return $g;
    }

    private function guiaSalida(): GuiaSalida
    {
        $g = new GuiaSalida();
        $g->serie = '1'; $g->numero = 8001;
        $g->fecha_emision = '2026-03-04'; $g->hora_emision = '10:00:00';
        $g->guia_estado_id = 1; $g->activo = 1;
        $g->total_venta = 45.49; $g->monto_igv = 6.94;
        $g->importe_sin_igv = 38.55; $g->monto_descuento = 0;
        $g->save();

        return $g;
    }

    // =================================================================
    // Eliminar (guia de ingreso)
    // =================================================================

    public function test_si_el_datamart_acepta_la_baja_la_guia_desaparece_del_listado(): void
    {
        Http::fake(['*EliminaGuiaDMK*' => Http::response(['exito' => true]), '*' => Http::response([])]);

        $guia = $this->guiaIngreso();

        $this->actingAs($this->usuario())
             ->post(route('guiaingreso.eliminar'), ['id' => $guia->id])
             ->assertOk()
             ->assertJson(['procede' => true]);

        $this->assertSame(0, (int) $guia->fresh()->activo);
    }

    public function test_si_el_datamart_rechaza_la_baja_la_guia_sigue_visible(): void
    {
        // Este es el fallo que ya ocurrio: se restauraba el estado pero no
        // activo, y la guia desaparecia del listado sin haberse borrado.
        Http::fake([
            '*EliminaGuiaDMK*' => Http::response(['exito' => false, 'msgerror' => 'No se pudo']),
            '*' => Http::response([]),
        ]);

        $guia = $this->guiaIngreso();

        $this->actingAs($this->usuario())
             ->post(route('guiaingreso.eliminar'), ['id' => $guia->id])
             ->assertOk()
             ->assertJson(['procede' => false]);

        $fresca = $guia->fresh();
        $this->assertSame(1, (int) $fresca->activo, 'La guia tiene que seguir visible si el DataMart la rechazo');
        $this->assertSame(1, (int) $fresca->guia_estado_id, 'Y con su estado original');
    }

    public function test_si_la_apigre_esta_caida_la_guia_no_se_da_por_eliminada(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $guia = $this->guiaIngreso();

        $respuesta = $this->actingAs($this->usuario())
                          ->post(route('guiaingreso.eliminar'), ['id' => $guia->id])
                          ->assertOk();

        $this->assertFalse($respuesta->json('procede'));
        $this->assertSame(1, (int) $guia->fresh()->activo);
    }

    public function test_eliminar_una_guia_que_no_existe_avisa_en_vez_de_reventar(): void
    {
        Http::fake(['*' => Http::response([])]);

        $this->actingAs($this->usuario())
             ->post(route('guiaingreso.eliminar'), ['id' => 999999])
             ->assertOk()
             ->assertJson(['procede' => false]);
    }

    // =================================================================
    // Anular (guia de salida)
    // =================================================================

    public function test_anular_deja_la_guia_marcada_como_anulada(): void
    {
        Http::fake(['*AnulaGuiaDMK*' => Http::response(['exito' => true]), '*' => Http::response([])]);

        $guia = $this->guiaSalida();

        $this->actingAs($this->usuario())
             ->post(route('guiasalida.anular'), ['id' => $guia->id])
             ->assertOk()
             ->assertJson(['procede' => true]);

        $this->assertSame(0, (int) $guia->fresh()->guia_estado_id);
    }

    public function test_si_el_datamart_rechaza_la_anulacion_la_guia_conserva_su_estado(): void
    {
        Http::fake([
            '*AnulaGuiaDMK*' => Http::response(['exito' => false, 'msgerror' => 'No se pudo']),
            '*' => Http::response([]),
        ]);

        $guia = $this->guiaSalida();

        $this->actingAs($this->usuario())
             ->post(route('guiasalida.anular'), ['id' => $guia->id])
             ->assertOk()
             ->assertJson(['procede' => false]);

        // Anulada en un sitio y viva en el otro es el peor de los dos mundos.
        $this->assertSame(1, (int) $guia->fresh()->guia_estado_id);
    }

    public function test_si_la_apigre_esta_caida_la_guia_no_se_da_por_anulada(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $guia = $this->guiaSalida();

        $respuesta = $this->actingAs($this->usuario())
                          ->post(route('guiasalida.anular'), ['id' => $guia->id])
                          ->assertOk();

        $this->assertFalse($respuesta->json('procede'));
        $this->assertSame(1, (int) $guia->fresh()->guia_estado_id);
    }
}
