<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     * El formulario de esta aplicacion pide USUARIO, no correo.
     *
     * Sin esto Laravel valida el campo "email", que el formulario nunca manda:
     * la validacion falla y devuelve al login sin decir por que. En la maquina
     * de desarrollo funcionaba porque el archivo de laravel/ui estaba modificado
     * a mano, y vendor/ no esta en el repositorio ni viaja en el paquete. Asi
     * que en una instalacion nueva nadie podia entrar.
     *
     * Se descubrio instalando en un Windows limpio. Declararlo aqui hace que la
     * aplicacion no dependa de ningun parche en vendor.
     */
    public function username()
    {
        return 'username';
    }
}
