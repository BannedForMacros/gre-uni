<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFacturacionEnviosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('facturacion_envios', function (Blueprint $table) {
            $table->id();
            $table->string('tabla');
            $table->integer('registro_id');
            $table->text('trama_json');
            $table->text('codigo_hash')->nullable();
            $table->text('codigo_qr')->nullable();
            $table->text('pdf417')->nullable();
            $table->tinyInteger('exito')->comment('1->true;0->false');
            $table->text('mensaje_error')->nullable();
            $table->text('pila')->nullable();
            $table->text('xml')->nullable();
            $table->text('pdf')->nullable();
            
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
        Schema::dropIfExists('facturacion_envios');
    }
}
