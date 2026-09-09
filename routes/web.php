<?php

use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\Guia\GuiaIngresoController;
use App\Http\Controllers\Guia\GuiaSalidaController;
use App\Http\Controllers\GuiaRemisionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    // return view('welcome');
    if (Auth::check()) {
        // dd('hola');
        return view('home');
        // return route('home');
    }
    return view('auth.login');
});

// actualizar vendor\laravel\ui\auth-backend\AuthenticatesUsers.php
// public function username()
// {
//     // return 'email';
//     return 'username';
// }



Auth::routes();

// Configuración de Empresa (logo + datos)
Route::controller(ConfiguracionController::class)->middleware('auth')->group(function () {
    Route::get('configuracion/empresa', 'empresa')->name('configuracion.empresa');
    Route::post('configuracion/empresa', 'empresaUpdate')->name('configuracion.empresa.update');
    Route::post('configuracion/empresa/logo/eliminar', 'empresaLogoEliminar')->name('configuracion.empresa.logo.eliminar');
});

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::controller(GuiaRemisionController::class)->group(function(){

    Route::resource('guias', GuiaRemisionController::class)->except('update');
});

Route::controller(GuiaSalidaController::class)->group(function (){

    Route::post('guiasalida/getModalidadTraslado', 'getModalidadTraslado')->name('guiasalida.getModalidadTraslado');
    Route::post('guiasalida/agregarItem', 'agregarItem')->name('guiasalida.agregarItem');
    Route::post('guiasalida/formBusquedaArticulo', 'formBusquedaArticulo')->name('guiasalida.formBusquedaArticulo');
    Route::post('guiasalida/getVendedor', 'getVendedor')->name('guiasalida.getVendedor');
    Route::post('guiasalida/buscarArticuloBarra', 'buscarArticuloBarra')->name('guiasalida.buscarArticuloBarra');
    Route::get('guiasalida/listarArticulos', 'listarArticulos')->name('guiasalida.listarArticulos');
    Route::get('guiasalida/listarClientes', 'listarClientes')->name('guiasalida.listarClientes');
    Route::get('guiasalida/listarTransportistas', 'listarTransportistas')->name('guiasalida.listarTransportistas');
    Route::get('guiasalida/listarProveedores', 'listarProveedores')->name('guiasalida.listarProveedores');
    Route::post('guiasalida/getSerie', 'getSerie')->name('guiasalida.getSerie');
    Route::post('guiasalida/listar', 'listar')->name('guiasalida.listar');
    Route::post('guiasalida/listarUbigeos', 'listarUbigeos')->name('guiasalida.listarUbigeos');
    Route::post('guiasalida/getUbigeosPorAlmacen', 'getUbigeosPorAlmacen')->name('guiasalida.getUbigeosPorAlmacen');
    Route::get('guiasalida/pdf/{guia}/{valorada}', 'pdf')->name('guiasalida.pdf');
    Route::get('guiasalida/pdfDecode/{guia}', 'pdfDecode')->name('guiasalida.pdfDecode');
    Route::get('guiasalida/continuar/{guia}', 'continuar')->name('guiasalida.continuar');
    Route::post('guiasalida/modalStore', 'modalStore')->name('guiasalida.modalStore');
    Route::post('guiasalida/store', 'store')->name('guiasalida.store');
    Route::post('guiasalida/storeDataMart', 'storeDataMart')->name('guiasalida.storeDataMart');
    Route::post('guiasalida/facturacionElectronica', 'facturacionElectronica')->name('guiasalida.facturacionElectronica');
    Route::post('guiasalida/anular', 'anular')->name('guiasalida.anular');
    Route::post('guiasalida/modalOtrasGuias', 'modalOtrasGuias')->name('guiasalida.modalOtrasGuias');
    Route::post('guiasalida/buscarOtrasGuias', 'buscarOtrasGuias')->name('guiasalida.buscarOtrasGuias');
    Route::post('guiasalida/cargarOtraGuia', 'cargarOtraGuia')->name('guiasalida.cargarOtraGuia');
    Route::post('guiasalida/validarMesAbierto', 'validarMesAbierto')->name('guiasalida.validarMesAbierto');
    
    Route::resource('guiasalida', GuiaSalidaController::class)->parameter('guiasalida', 'guia')->except('update');
});

Route::controller(GuiaIngresoController::class)->group(function (){
    
    Route::post('guiaingreso/agregarItem', 'agregarItem')->name('guiaingreso.agregarItem');
    Route::post('guiaingreso/formBusquedaArticulo', 'formBusquedaArticulo')->name('guiaingreso.formBusquedaArticulo');
    Route::post('guiaingreso/getVendedor', 'getVendedor')->name('guiaingreso.getVendedor');
    Route::post('guiaingreso/buscarArticuloBarra', 'buscarArticuloBarra')->name('guiaingreso.buscarArticuloBarra');
    Route::get('guiaingreso/listarProveedores', 'listarProveedores')->name('guiaingreso.listarProveedores');
    Route::get('guiaingreso/listarArticulos', 'listarArticulos')->name('guiaingreso.listarArticulos');
    Route::post('guiaingreso/listar', 'listar')->name('guiaingreso.listar');
    Route::post('guiaingreso/modalStore', 'modalStore')->name('guiaingreso.modalStore');
    Route::post('guiaingreso/storeDataMart', 'storeDataMart')->name('guiaingreso.storeDataMart');
    // Route::get('guiaingreso/pdf/{guia}', 'pdf')->name('guiaingreso.pdf');
    Route::get('guiaingreso/pdf/{guia}/{valorada}', 'pdf')->name('guiaingreso.pdf');
    Route::get('guiaingreso/continuar/{guia}', 'continuar')->name('guiaingreso.continuar');
    Route::post('guiaingreso/eliminar', 'eliminar')->name('guiaingreso.eliminar');
    Route::post('guiaingreso/getSerie', 'getSerie')->name('guiaingreso.getSerie');

    Route::post('guiaingreso/modalOtrasGuias', 'modalOtrasGuias')->name('guiaingreso.modalOtrasGuias');
    Route::post('guiaingreso/buscarOtrasGuias', 'buscarOtrasGuias')->name('guiaingreso.buscarOtrasGuias');
    Route::post('guiaingreso/cargarOtraGuia', 'cargarOtraGuia')->name('guiaingreso.cargarOtraGuia');

    Route::resource('guiaingreso', GuiaIngresoController::class)->parameter('guiaingreso', 'guia')->except('update');
});

Route::controller(EmpleadoController::class)->group(function () {
    
    Route::post('empleados/getEmpleadoDmk', 'getEmpleadoDmk')->name('empleados.getEmpleadoDmk');
    Route::post('empleados/update', 'update')->name('empleados.update');

    Route::resource('empleados', EmpleadoController::class)->parameter('empleados', 'empleado')->except('update');
});