<?php

namespace Tests\Feature;

use App\Models\Parametro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CabeceraTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_cabecera_dice_el_producto_y_la_empresa_no_el_framework(): void
    {
        $p = new Parametro();
        $p->id = 3; $p->nombre = 'razon_social_entidad';
        $p->valor = 'Franco Supermercado E.I.R.L.'; $p->activo = 1;
        $p->save();

        $usuario = User::factory()->create(['name' => 'Rosa Quispe']);

        $html = $this->actingAs($usuario)->get('/guiaingreso')->assertOk()->getContent();

        $this->assertStringContainsString('Guías Electrónicas', $html);
        $this->assertStringContainsString('Franco Supermercado E.I.R.L.', $html);
        $this->assertStringNotContainsString('>Laravel<', $html);

        // La inicial del usuario y la de la empresa, que son las dos marcas
        // que identifican de un vistazo con que cuenta y en que instalacion
        // se esta trabajando.
        $this->assertStringContainsString('gre-avatar', $html);
        $this->assertStringContainsString('gre-marca-inicial', $html);
    }

    public function test_el_menu_marca_la_pantalla_en_la_que_esta_el_usuario(): void
    {
        $usuario = User::factory()->create();

        $ingreso = $this->actingAs($usuario)->get('/guiaingreso')->getContent();
        $salida  = $this->actingAs($usuario)->get('/guiasalida')->getContent();

        // El enlace de la pantalla en la que se esta va marcado, y el otro no.
        // Antes las dos entradas se veian igual estuvieras donde estuvieras.
        $this->assertTrue($this->enlaceMarcado($ingreso, 'guiaingreso'), 'Ingreso deberia estar marcado en su pantalla');
        $this->assertFalse($this->enlaceMarcado($ingreso, 'guiasalida'), 'Salida NO deberia estar marcado en la pantalla de ingreso');

        $this->assertTrue($this->enlaceMarcado($salida, 'guiasalida'), 'Salida deberia estar marcado en su pantalla');
        $this->assertFalse($this->enlaceMarcado($salida, 'guiaingreso'), 'Ingreso NO deberia estar marcado en la pantalla de salida');
    }

    /** ¿El enlace del menu que apunta a esa ruta lleva la marca de activo? */
    private function enlaceMarcado(string $html, string $ruta): bool
    {
        preg_match_all('/<a class="dropdown-item([^"]*)"\s+href="[^"]*\/' . $ruta . '"/', $html, $coincidencias);

        foreach ($coincidencias[1] as $clases) {
            if (strpos($clases, 'activo') !== false) { return true; }
        }

        return false;
    }
}
