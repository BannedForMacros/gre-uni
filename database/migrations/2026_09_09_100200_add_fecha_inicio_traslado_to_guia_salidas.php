<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega fecha_inicio_traslado a guia_salidas.
 *
 * El codigo pendiente de la version base (GitLab) ya la usa:
 *     $datos['fecha_inicio_traslado'] = $request->post('fecha_inicio_traslado') ?: null;
 *     "FechaInicioTraslado" => $guia->fecha_inicio_traslado ?? $guia->fecha_emision,
 * mas el toggle en el blade y la validacion contra la fecha de emision.
 *
 * Pero la columna NO existe en ninguna base. Laravel la descarta en silencio,
 * asi que hoy toda esa funcionalidad no persiste nada.
 *
 * SUNAT la exige como fecha de inicio de traslado en la GRE 2.1.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('guia_salidas', 'fecha_inicio_traslado')) {
            return;
        }
        Schema::table('guia_salidas', function (Blueprint $table) {
            $table->date('fecha_inicio_traslado')->nullable()
                  ->comment('Fecha de inicio de traslado SUNAT. Si es null se usa fecha_emision.');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('guia_salidas', 'fecha_inicio_traslado')) {
            return;
        }
        Schema::table('guia_salidas', function (Blueprint $table) {
            $table->dropColumn('fecha_inicio_traslado');
        });
    }
};
