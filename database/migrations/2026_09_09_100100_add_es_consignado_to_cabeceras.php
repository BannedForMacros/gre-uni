<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega es_consignado a las CABECERAS.
 *
 * BUG QUE CORRIGE: "a veces no se pintan los consignados".
 *
 * El JS manda es_consignado para la cabecera:
 *     formData.append('es_consignado', esConsignadoMaster);   // create.js
 * pero guia_ingresos / guia_salidas NO tienen esa columna. Laravel 8 la
 * descarta en silencio (isGuardableColumn consulta el esquema), asi que:
 *
 *     resources/views/guia/ingreso/create.blade.php
 *     {{ ($guia->es_consignado ?? 0) == 1 ? 'checked' : '' }}   <-- SIEMPRE 0
 *
 * Resultado: al editar una guia, el check "Productos Consignados" nunca
 * vuelve marcado, aunque sus lineas si esten guardadas como consignadas.
 *
 * Verificado en el MySQL de produccion y en los dumps de jun-2026 y jul-2026:
 * la columna no existe en ninguno.
 */
return new class extends Migration
{
    private array $tablas = ['guia_ingresos', 'guia_salidas'];

    public function up(): void
    {
        foreach ($this->tablas as $tabla) {
            if (Schema::hasColumn($tabla, 'es_consignado')) {
                continue;
            }
            Schema::table($tabla, function (Blueprint $table) {
                $table->smallInteger('es_consignado')->default(0)
                      ->comment('Flag maestro del formulario. Permite repintar el check al editar.');
            });
        }

        // Backfill: si alguna linea de la guia es consignada, la cabecera tambien.
        // Recupera el estado correcto de las guias historicas.
        //
        // OJO CON LA FORMA DE ESTA CONSULTA. La version anterior usaba
        // WHERE EXISTS (SELECT 1 FROM detalles WHERE fk = c.id AND ...).
        // Eso obliga a recorrer los detalles UNA VEZ POR CABECERA, y las
        // tablas de detalle de los clientes no tienen indice sobre la clave
        // foranea: solo la primaria. Medido en el respaldo real de un cliente
        // (18.272 cabeceras y 301.424 detalles): 27 minutos y 40 segundos,
        // para acabar actualizando cero filas.
        //
        // Asi se recorren los detalles una sola vez y se actualizan solo las
        // cabeceras que aparecen en esa lista.
        foreach ([['guia_ingresos', 'guia_ingreso_detalles', 'guia_ingreso_id'],
                  ['guia_salidas',  'guia_salida_detalles',  'guia_salida_id']] as [$cab, $det, $fk]) {
            \DB::statement("
                UPDATE {$cab} c
                  JOIN (SELECT DISTINCT {$fk} AS cab_id
                          FROM {$det}
                         WHERE es_consignado = 1) d ON d.cab_id = c.id
                   SET c.es_consignado = 1
            ");
        }
    }

    public function down(): void
    {
        foreach ($this->tablas as $tabla) {
            if (! Schema::hasColumn($tabla, 'es_consignado')) {
                continue;
            }
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropColumn('es_consignado');
            });
        }
    }
};
