<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega estado_sunat_consultado_at a guia_salidas.
 *
 * PROBLEMA QUE RESUELVE
 *   El listado de salida consultaba el estado en el facturador UNA VEZ POR
 *   GUIA, en serie, dentro de listar(). Con 25 guias por pagina son 25 HTTP
 *   encadenados antes de pintar una sola fila (medido: 3.85 s con un servicio
 *   que responde en 150 ms).
 *
 *   La consulta se saca del render y pasa a una segunda peticion del navegador.
 *   Para que esa segunda peticion no repita las mismas consultas cada vez que
 *   el listado se recarga -y el listado se recarga despues de CADA accion:
 *   anular, reenviar a DataMart, reenviar al facturador- hace falta saber
 *   cuando se consulto por ultima vez cada guia. Sin esta columna la unica
 *   alternativa seria el cache de Laravel, que en las instalaciones on-premise
 *   es de archivos y se pierde con cualquier limpieza, mientras que el estado
 *   ya vive en esta misma tabla.
 *
 * NULL significa "nunca consultada": se consulta en el primer refresco.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('guia_salidas', 'estado_sunat_consultado_at')) {
            return;
        }
        Schema::table('guia_salidas', function (Blueprint $table) {
            $table->timestamp('estado_sunat_consultado_at')->nullable()
                  ->comment('Ultima consulta de estado al facturador. Evita repetir la consulta en cada recarga del listado.');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('guia_salidas', 'estado_sunat_consultado_at')) {
            return;
        }
        Schema::table('guia_salidas', function (Blueprint $table) {
            $table->dropColumn('estado_sunat_consultado_at');
        });
    }
};
