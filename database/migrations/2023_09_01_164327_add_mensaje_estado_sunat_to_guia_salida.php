<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMensajeEstadoSunatToGuiaSalida extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('guia_salidas', function (Blueprint $table) {
            $table->text('mensaje_estado_sunat')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('guia_salidas', function (Blueprint $table) {
            $table->dropColumn('mensaje_estado_sunat');
        });
    }
}
