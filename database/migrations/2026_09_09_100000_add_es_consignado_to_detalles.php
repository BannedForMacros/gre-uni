<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formaliza la columna es_consignado de los DETALLES.
 *
 * Ya existe en produccion (se agrego con ALTER TABLE a mano en dic-2025), pero
 * nunca tuvo migracion. Por eso una instalacion limpia con `artisan migrate`
 * genera una base que no coincide con produccion.
 *
 * Es idempotente: en los clientes que ya la tienen no hace nada.
 */
return new class extends Migration
{
    private array $tablas = ['guia_ingreso_detalles', 'guia_salida_detalles'];

    public function up(): void
    {
        foreach ($this->tablas as $tabla) {
            if (Schema::hasColumn($tabla, 'es_consignado')) {
                continue;
            }
            Schema::table($tabla, function (Blueprint $table) {
                $table->smallInteger('es_consignado')->default(0)
                      ->comment('1 = articulo consignado. Solo se usa si el cliente tiene consignados habilitado.');
            });
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
