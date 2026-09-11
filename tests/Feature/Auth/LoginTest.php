<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Entrar al sistema con usuario y clave.
 *
 * Esto existe por un fallo que solo aparecio al instalar en un Windows limpio:
 * el formulario manda "username", pero Laravel validaba "email" y devolvia al
 * login sin explicar nada. En la maquina de desarrollo no se notaba porque el
 * archivo de laravel/ui estaba modificado a mano, y vendor/ no esta en el
 * repositorio: la aplicacion entregada no podia iniciar sesion.
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $clave = 'Clave-De-Prueba-1'): User
    {
        $u = new User();
        $u->name = 'Administrador';
        $u->username = 'admin';
        $u->email = null;
        $u->password = Hash::make($clave);
        $u->perfil_id = 1;
        $u->save();

        return $u;
    }

    public function test_el_campo_del_login_es_usuario_y_no_correo(): void
    {
        // Lo que fija el contrato: si vuelve a ser "email", nadie entra.
        $controlador = new \App\Http\Controllers\Auth\LoginController();

        $this->assertSame('username', $controlador->username());
    }

    public function test_se_entra_con_usuario_y_clave(): void
    {
        $this->usuario();

        $this->post('/login', ['username' => 'admin', 'password' => 'Clave-De-Prueba-1'])
             ->assertRedirect();

        $this->assertAuthenticated();
    }

    public function test_con_la_clave_equivocada_no_se_entra(): void
    {
        $this->usuario();

        $this->from('/login')
             ->post('/login', ['username' => 'admin', 'password' => 'otra'])
             ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_sin_usuario_avisa_del_campo_usuario(): void
    {
        // Antes el aviso hablaba de "email", un campo que la pantalla no tiene.
        $this->from('/login')
             ->post('/login', ['password' => 'x'])
             ->assertSessionHasErrors('username');
    }
}
