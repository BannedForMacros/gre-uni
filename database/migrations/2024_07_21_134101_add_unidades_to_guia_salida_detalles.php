<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUnidadesToGuiaSalidaDetalles extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('guia_salida_detalles', function (Blueprint $table) {
            $table->integer('cod_unidad')->nullable()->after('peso_unitario');
            $table->string('desc_unidad_medida')->nullable()->after('peso_unitario');
            $table->string('sigla_umfe')->nullable()->after('peso_unitario');

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
            $table->dropColumn('cod_unidad');
            $table->dropColumn('desc_unidad_medida');
            $table->dropColumn('sigla_umfe');
        });
    }
}
