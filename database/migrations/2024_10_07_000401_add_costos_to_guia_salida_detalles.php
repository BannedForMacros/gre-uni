<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCostosToGuiaSalidaDetalles extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('guia_salida_detalles', function (Blueprint $table) {
            $table->decimal('costo_articulo')->nullable()->default(0)->after('cod_unidad');
            $table->decimal('costo_total')->nullable()->default(0)->after('costo_articulo');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('guia_salida_detalles', function (Blueprint $table) {
            $table->dropColumn('costo_articulo');
            $table->dropColumn('costo_total');
        });
    }
}
