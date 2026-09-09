<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGuiaIngresosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('guia_ingresos', function (Blueprint $table) {
            $table->id();
            $table->string('serie')->nullable();
            $table->integer('numero')->nullable();
            $table->date('fecha_emision')->nullable();
            $table->string('hora_emision')->nullable();
            $table->integer('relacion_pedido')->nullable()->comment('1->pedido;2->recepcion');
            $table->string('pedido_serie')->nullable();
            $table->string('pedido_numero')->nullable();
            $table->integer('vendedor_id')->nullable();
            $table->string('vendedor_nombre')->nullable();
            $table->integer('proveedor_id')->nullable();
            $table->string('proveedor_nombre')->nullable();
            $table->string('proveedor_ruc')->nullable();
            $table->integer('divisa_id')->nullable();
            $table->string('divisa_nombre')->nullable();
            $table->integer('forma_pago_id')->nullable();
            $table->string('forma_pago_nombre')->nullable();
            $table->integer('tipo_operacion_id')->nullable();
            $table->string('tipo_operacion_nombre')->nullable();
            $table->string('codalmacen')->nullable();
            $table->string('almacen_nombre')->nullable();
            $table->text('condiciones')->nullable();
            $table->integer('base_calculo')->nullable();

            $table->decimal('monto_descuento')->nullable();
            $table->decimal('importe_sin_igv')->nullable();
            $table->decimal('monto_igv')->nullable();
            $table->decimal('total_venta')->nullable();
            $table->string('comentario')->nullable();
            $table->integer('guia_estado_id')->nullable();

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
        Schema::dropIfExists('guia_ingresos');
    }
}
