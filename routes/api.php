<?php

use App\Http\Controllers\SimulacionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::controller(SimulacionController::class)->group(function(){
    Route::post('simulacion/ObtenerProveedores', 'ObtenerProveedores')->name('simulacion.ObtenerProveedores');
    Route::post('simulacion/ObtenerAlmacenes', 'ObtenerAlmacenes')->name('simulacion.ObtenerAlmacenes');
    Route::post('simulacion/ObtenerChoferes', 'ObtenerChoferes')->name('simulacion.ObtenerChoferes');
    Route::post('simulacion/ObtenerOperaciones', 'ObtenerOperaciones')->name('simulacion.ObtenerOperaciones');
    Route::post('simulacion/ObtenerListaPrecios', 'ObtenerListaPrecios')->name('simulacion.ObtenerListaPrecios');
    Route::post('simulacion/ObtenerFormasPago', 'ObtenerFormasPago')->name('simulacion.ObtenerFormasPago');
    Route::post('simulacion/ObtenerArticulos', 'ObtenerArticulos')->name('simulacion.ObtenerArticulos');
    Route::post('simulacion/ObtenerTrabajadores', 'ObtenerTrabajadores')->name('simulacion.ObtenerTrabajadores');
    Route::post('simulacion/ObtenerTransportistas', 'ObtenerTransportistas')->name('simulacion.ObtenerTransportistas');
    Route::post('simulacion/ObtenerVehiculos', 'ObtenerVehiculos')->name('simulacion.ObtenerVehiculos');
    Route::post('simulacion/ObtenerClientes', 'ObtenerClientes')->name('simulacion.ObtenerClientes');
    Route::post('simulacion/ObtenerTiposCambio', 'ObtenerTiposCambio')->name('simulacion.ObtenerTiposCambio');
    Route::post('simulacion/ObtenerPedidoClientes', 'ObtenerPedidoClientes')->name('simulacion.ObtenerPedidoClientes');
    Route::post('simulacion/ObtenerDetalleOrden', 'ObtenerDetalleOrden')->name('simulacion.ObtenerDetalleOrden');
    Route::post('simulacion/ObtenerSeries', 'ObtenerSeries')->name('simulacion.ObtenerSeries');

    Route::post('simulacion/generateData', 'generateData')->name('simulacion.generateData');
});