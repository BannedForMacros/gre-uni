<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGuiaSalidaDetallesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('guia_salida_detalles', function (Blueprint $table) {
            $table->id();
            $table->integer('guia_salida_id');
            $table->string('codarticulo');
            $table->string('codigo_barra')->nullable();
            $table->string('descripcion');
            $table->decimal('precio')->comment('precio para mostrar en factura');
            $table->decimal('precio_publico')->nullable();
            $table->decimal('precio_sin_igv')->nullable();
            $table->integer('cantidad');
            $table->decimal('importe');
            $table->decimal('porcentaje_descuento');
            $table->decimal('monto_descuento');
            $table->tinyInteger('activo')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('guia_salida_detalles');
    }
}
