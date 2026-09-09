<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEnviadoFacturadorToGuiaSalidas extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('guia_salidas', function (Blueprint $table) {
            $table->tinyInteger('enviado_facturador')->default(0);
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
            $table->dropColumn('enviado_facturador');
        });
    }
}
