<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Funcionalidad de Consignados
    |--------------------------------------------------------------------------
    |
    | Activa la marca de artículos consignados en SQL Server
    | (MaestroArticulo.consignacion y DetalleGuiaRemision.esconsignado).
    |
    | Esas columnas SOLO existen en clientes que usan consignados, por eso
    | esta funcionalidad está APAGADA por defecto. Para activarla en el
    | cliente que la necesita, agrega en su .env:
    |
    |     CONSIGNADOS_ENABLED=true
    |
    | Los clientes que no la usan no requieren ningún cambio en su SQL Server.
    |
    */

    'enabled' => env('CONSIGNADOS_ENABLED', false),

];
