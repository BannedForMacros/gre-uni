<?php

return [

    /*
    |--------------------------------------------------------------------------
    | IGV
    |--------------------------------------------------------------------------
    | Unica fuente de verdad de la tasa. Antes estaba cableada en 14 lugares
    | entre PHP, JavaScript, Blade y stored procedures.
    */
    'igv' => [
        'tasa' => (float) env('GRE_IGV_TASA', 0.18),
    ],

    /*
    |--------------------------------------------------------------------------
    | ApiGRE
    |--------------------------------------------------------------------------
    | Unico canal hacia SQL Server. La aplicacion NO abre conexiones al
    | DataMart del cliente: no hay conexion 'sqlsrv' en config/database.php.
    */
    'api' => [
        'url'      => env('GRE_API_URL', 'http://localhost:8181/api/v1'),
        'key'      => env('GRE_API_KEY'),
        'timeout'  => (int) env('GRE_API_TIMEOUT', 30),
        'reintentos' => (int) env('GRE_API_REINTENTOS', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Consignados
    |--------------------------------------------------------------------------
    | No se configura a mano: la ApiGRE inspecciona el DataMart del cliente y
    | reporta si la funcionalidad esta disponible. Esto solo permite apagarla
    | aunque el DataMart la soporte.
    */
    'consignados' => [
        'permitido' => (bool) env('GRE_CONSIGNADOS', true),
    ],

];
