<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeCantidadToGuiaSalidaDetalles extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('guia_salida_detalles', function (Blueprint $table) {
            $table->decimal('cantidad')->change();
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
            $table->integer('cantidad')->change();
        });
    }
}
