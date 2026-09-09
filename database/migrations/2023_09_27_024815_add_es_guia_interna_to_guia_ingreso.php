<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEsGuiaInternaToGuiaIngreso extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('guia_ingresos', function (Blueprint $table) {
            $table->tinyInteger('es_guia_interna')->default(0)->comment('indica si es guia interna para serie y numeracion');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('guia_ingresos', function (Blueprint $table) {
            $table->dropColumn('es_guia_interna');
        });
    }
}
