<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeCantidadToGuiaIngresoDetalles extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('guia_ingreso_detalles', function (Blueprint $table) {
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
        Schema::table('guia_ingreso_detalles', function (Blueprint $table) {
            $table->integer('cantidad')->change();
        });
    }
}
