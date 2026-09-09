<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPesoToGuiaSalidaDetalles extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('guia_salida_detalles', function (Blueprint $table) {
            $table->decimal('peso_unitario', 8,4)->after('monto_descuento')->default(0);
            $table->decimal('peso_total', 8,4)->after('monto_descuento')->default(0);
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
            $table->dropColumn('peso_unitario');
            $table->dropColumn('peso_total');
        });
    }
}
