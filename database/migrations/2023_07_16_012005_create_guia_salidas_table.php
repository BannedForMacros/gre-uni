<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGuiaSalidasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('guia_salidas', function (Blueprint $table) {
            $table->id();
            $table->string('serie')->nullable();
            $table->integer('numero')->nullable();
            $table->date('fecha_emision')->nullable();
            $table->string('hora_emision')->nullable();
            $table->tinyInteger('pedido_interno')->default(0)->nullable();
            $table->string('pedido_serie')->nullable();
            $table->string('pedido_numero')->nullable();
            $table->integer('vendedor_id')->nullable();
            $table->string('vendedor_nombre')->nullable();

            $table->tinyInteger('indicar_proveedor')->default(0);
            $table->integer('proveedor_id')->nullable();
            $table->string('proveedor_nombre')->nullable();
            $table->string('proveedor_ruc')->nullable();

            $table->integer('cliente_id')->nullable();
            $table->string('cliente_razon_social')->nullable();
            $table->string('cliente_nro_documento')->nullable();
            $table->string('cliente_documento_tipo_nombre')->nullable();
            $table->string('cliente_direccion')->nullable();

            $table->integer('divisa_id')->nullable();
            $table->string('divisa_nombre')->nullable();
            $table->integer('forma_pago_id')->nullable();
            $table->string('forma_pago_nombre')->nullable();
            $table->integer('codlistaprecio')->nullable();
            $table->integer('tipo_operacion_id')->nullable();
            $table->string('tipo_operacion_nombre')->nullable();
            $table->string('codalmacen')->nullable();
            $table->string('almacen_nombre')->nullable();
            $table->string('codestacion')->nullable();
            $table->string('cod_almacen_origen')->nullable();
            $table->string('almacen_origen_nombre')->nullable();
            $table->string('cod_almacen_destino')->nullable();
            $table->string('almacen_destino_nombre')->nullable();

            // datos transporte
            $table->integer('transportista_id')->nullable();
            $table->string('transportista_ruc')->nullable();
            $table->string('transportista_nombre')->nullable();
            $table->string('transportista_direccion')->nullable();

            $table->string('chofer_dni')->nullable();
            $table->string('chofer_brevete')->nullable();
            $table->string('chofer_nombre')->nullable();

            $table->string('vehiculo_placa')->nullable();
            $table->string('vehiculo_marca')->nullable();

            $table->string('motivo_traslado_id')->nullable();
            $table->string('descripcion_motivo_traslado')->nullable();
            $table->string('modalidad_traslado')->nullable();

            // ubigeos

            $table->string('ubigeo_partida_departamento')->nullable();
            $table->string('ubigeo_partida_provincia')->nullable();
            $table->string('ubigeo_partida_distrito')->nullable();
            $table->string('ubigeo_partida')->nullable();
            $table->text('direccion_partida')->nullable();


            $table->string('ubigeo_llegada')->nullable();
            $table->string('ubigeo_llegada_departamento')->nullable();
            $table->string('ubigeo_llegada_provincia')->nullable();
            $table->string('ubigeo_llegada_distrito')->nullable();
            $table->text('direccion_llegada')->nullable();

            $table->decimal('monto_descuento')->nullable();
            $table->decimal('importe_sin_igv')->nullable();
            $table->decimal('monto_igv')->nullable();
            $table->decimal('total_venta')->nullable();
            $table->decimal('peso_bruto_total')->nullable();
            $table->integer('base_calculo')->nullable();
            
            $table->integer('guia_estado_id')->nullable();

            $table->string('comentario')->nullable();
            
            $table->tinyInteger('envio_sunat')->nullable();
            $table->integer('envio_id')->nullable();
            $table->integer('facturacion_estado')->nullable();
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
        Schema::dropIfExists('guia_salidas');
    }
}
