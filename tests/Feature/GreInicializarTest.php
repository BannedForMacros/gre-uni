<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * gre:inicializar es lo que el instalador corre en cada cliente, y lo vuelve a
 * correr en cada actualizacion. Lo que importa: que deje todo listo para
 * entrar, y que la segunda vez no duplique nada ni cambie la clave.
 */
class GreInicializarTest extends TestCase
{
    use RefreshDatabase;

    public function test_en_base_vacia_deja_catalogos_y_un_administrador(): void
    {
        $this->artisan('gre:inicializar', ['--clave' => 'Clave-Prueba-1'])
             ->expectsOutput('ADMIN_USUARIO=admin')
             ->assertExitCode(0);

        foreach (['perfiles', 'guia_estados', 'auditoria_acciones'] as $tabla) {
            $this->assertGreaterThan(0, DB::table($tabla)->count(), "{$tabla} quedo vacia");
        }
        $this->assertGreaterThanOrEqual(10, DB::table('parametros')->count());

        $admin = User::where('username', 'admin')->first();
        $this->assertNotNull($admin);
        $this->assertTrue(Hash::check('Clave-Prueba-1', $admin->password));
        $this->assertSame(1, (int) $admin->perfil_id);
    }

    public function test_correrlo_dos_veces_no_duplica_ni_cambia_la_clave(): void
    {
        $this->artisan('gre:inicializar', ['--clave' => 'Clave-Prueba-1'])->assertExitCode(0);
        $antes = [
            DB::table('perfiles')->count(), DB::table('guia_estados')->count(),
            DB::table('auditoria_acciones')->count(), DB::table('parametros')->count(),
        ];

        // La segunda vez es una actualizacion: revienta si algun seeder usa create().
        $this->artisan('gre:inicializar', ['--clave' => 'Otra-Clave-2'])->assertExitCode(0);

        $this->assertSame($antes, [
            DB::table('perfiles')->count(), DB::table('guia_estados')->count(),
            DB::table('auditoria_acciones')->count(), DB::table('parametros')->count(),
        ]);
        $this->assertSame(1, User::count());
        $this->assertTrue(Hash::check('Clave-Prueba-1', User::first()->password), 'La clave no puede cambiar al reinstalar');
    }

    public function test_el_administrador_creado_puede_entrar(): void
    {
        $this->artisan('gre:inicializar', ['--clave' => 'Clave-Prueba-1'])->assertExitCode(0);

        $this->post('/login', ['username' => 'admin', 'password' => 'Clave-Prueba-1'])->assertRedirect();

        $this->assertAuthenticated();
    }
}
