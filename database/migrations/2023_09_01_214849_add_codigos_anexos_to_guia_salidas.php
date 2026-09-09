<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCodigosAnexosToGuiaSalidas extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('guia_salidas', function (Blueprint $table) {
            $table->string('codigo_anexo_partida')->nullable();
            $table->string('codigo_anexo_llegada')->nullable();
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
            $table->dropColumn('codigo_anexo_partida');
            $table->dropColumn('codigo_anexo_llegada');
        });
    }
}
