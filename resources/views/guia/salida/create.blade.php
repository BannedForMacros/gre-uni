@extends('layouts.app')

<style>
    body {
        background-color: #f5f7fa !important;
        padding-right: 0 !important
    }
</style>

@section('content')
{{-- El scope va en el contenedor exterior a proposito: puesto en un div
     interno, el navegador re-anidaba el marcado y los totales quedaban fuera
     del alcance de Alpine, mostrandose vacios. --}}
<div class="gre"
     x-data="greGuiaSalida({
                lineas: {{ Js::from($lineasDetalle ?? []) }},
                tasaIgv: {{ config('gre.igv.tasa', 0.18) }},
                validarStock: {{ (($validar_stock ?? 'false') === 'true') ? 'true' : 'false' }},
                rutas: {
                    agregarItem:     '{{ route('guiasalida.agregarItem') }}',
                    cargarOtraGuia:  '{{ route('guiasalida.cargarOtraGuia') }}',
                    listarArticulos: '{{ route('guiasalida.listarArticulos') }}'
                }
             })"
     x-cloak>
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-md-12">
                <h5><i class="fa fa-ticket"></i> Guia de Salida</h5>
                <form name="form_store" id="form_store" onkeydown="return event.key != 'Enter';">
                    <input type="hidden" name="save_local_storage" id="save_local_storage" value="false">
                    <input type="hidden" name="id_continua" id="id_continua" value="{{ $guia->id ?? '' }}">

                    <input type="hidden" name="cliente_transf_id" value="{{ $clienteTransferencia->codCliente ?? ''}}">
                    <input type="hidden" name="cliente_transf_razon_social" value="{{ $clienteTransferencia->razonSocial ?? ''}}">

                    <input type="hidden" name="cliente_transf_nro_documento" value="{{ $clienteTransferencia->rucCliente ?? ''}}">
                    <input type="hidden" name="cliente_transf_documento_tipo_nombre" value="RUC">
                    <input type="hidden" name="cliente_transf_direccion" value="{{ $clienteTransferencia->direccion ?? '' }}">


                    @csrf
                    <div class="row">
                        <div class="col-md-12">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="row">
                                                <h5>Guia</h5>
                                                <div class="col-md-6 mb-2">
                                                    <label class="form-label">Serie</label>
                                                    <select class="form-select" name="serie" id="serie">
                                                        @foreach ($listSeries as $item)
                                                            <option value="{{ $item->numserie }}" {{ $item->selected ?? '' }}>
                                                                {{ $item->numserie }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-6 mb-2">
                                                    <label class="form-label">Numero</label>
                                                    <input type="text" class="form-control" id="span_numero" readonly>
                                                </div>
                                                <div class="col-md-6 mb-2">
                                                    <label class="form-label">Fecha Emision</label>
                                                    <input type="date" class="form-control" value="{{ date('Y-m-d') }}" name="fecha_emision" id="fecha_emision">
                                                </div>
                                                <div class="col-md-6 mb-2">

                                                    <label class="form-label">Enviar a sunat</label>
                                                    <select class="form-select" name="envio_sunat" id="envio-sunat">
                                                        <option value="0" {{ ($guia->envio_sunat ?? '') == 0 ? 'selected' : '' }}>No</option>
                                                        <option value="1" {{ ($guia->envio_sunat ?? '') == 1 ? 'selected' : '' }}>Si</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-9 mb-3">
                                                    <label class="form-label">Fecha de Inicio de Traslado (Opcional)</label>
                                                    <div class="d-flex align-items-center">
                                                        <div class="form-check form-switch me-3">
                                                            <input class="form-check-input" type="checkbox" role="switch" id="toggle-fecha-traslado">
                                                            <label class="form-check-label" for="toggle-fecha-traslado">Activar</label>
                                                        </div>

                                                        <div id="contenedor-fecha-traslado" class="flex-grow-1" style="display: none;">
                                                            <input type="date" class="form-control"
                                                                   id="fecha_inicio_traslado"
                                                                   name="fecha_inicio_traslado">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6" hidden>
                                                    <label class="form-label">Comprobante Pago</label>
                                                    <input type="text" class="form-control" name="comprobante_pago">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <label class="form-label">Pedido</label>
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" value="1" id="pedido_interno"
                                                               {{ ($guia->pedido_interno ?? '') == 1 ? 'checked' : '' }} name="pedido_interno">
                                                        <label class="form-check-label" for="pedido_interno">
                                                            Interno
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-md-9">
                                                    <div class="row no-gutters">
                                                        <div class="col-md-5">
                                                            <input type="text" class="form-control input_pedido_interno" placeholder="Serie" name="pedido_serie" id="pedido_serie"
                                                                   value="{{ $guia->pedido_serie ?? '' }}">
                                                        </div>
                                                        <div class="col-md-7">
                                                            <input type="text" class="form-control input_pedido_interno" placeholder="Numero" name="pedido_numero" id="pedido_numero"
                                                                   value="{{ $guia->pedido_numero ?? '' }}">
                                                        </div>

                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="row">
                                        <div class="col-md-2">
                                            <label class="form-label mt-2">Codigo</label>
                                        </div>
                                        <div class="col-md-6">
                                            {{-- <input class="form-control" type="text" name="b_codigo_empleado" id="b_codigo_empleado" placeholder="Codigo Empleado"> --}}
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="vendedor_codigo" placeholder="Ingresar codigo" aria-describedby="button-addon2" value="{{ $guia->vendedor_id ?? '' }}">
                                                <button class="btn btn-primary" type="button" id="btnBuscarVendedor"><i class="fa fa-search"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-10 mb-2">
                                            <label class="form-label">Vendedor</label>

                                            <select class="form-select" name="vendedor_id" id="vendedor_id" style="width: 100%">
                                                @foreach ($listVendedores as $item)
                                                    <option value="{{ $item->codTrabajador }}"
                                                            data-vendedor_nombre="{{ "{$item->apellidos} {$item->nombres}" }}"
                                                        {{ ($item->selected ?? '') == 'selected' ? 'selected' : '' }}>
                                                        {{ "[{$item->codTrabajador}] {$item->apellidos} {$item->nombres}" }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-10 mb-2">
                                            <div class="row">
                                                <div class="col-md-12">
                                                    <h5>Otros Datos</h5>
                                                    <div class="form-check">
                                                        <input class="form-check-input" id="indicar_proveedor" name="indicar_proveedor"
                                                               type="checkbox" {{ ($guia->indicar_proveedor ?? 0) == 1 ? 'checked' : '' }} />
                                                        <label class="form-check-label" for="indicar_proveedor">Proveedor</label>
                                                    </div>
                                                    @if(\App\Support\ConfiguracionEmpresa::usaConsignados())
                                                    <div class="form-check">
                                                        <input type="hidden"  value="0">
                                                        <input class="form-check-input" id="es_consignado_master"
                                                               type="checkbox" value="1" {{ ($guia->es_consignado ?? 0) == 1 ? 'checked' : '' }} />
                                                        <label class="form-check-label" for="es_consignado_master">Productos Consignados</label>
                                                    </div>
                                                    @endif

                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                    <div class="row mt-2">
                        <div class="col-md-6">
                            <div class="row" id="div_cliente"
                                 style="display: {{ ($guia->indicar_proveedor ?? 0) == 0 ? '' : 'none' }}">
                                <h5>Datos el Cliente</h5>
                                <div class="col-md-12">
                                    <label class="form-label">Cliente</label>
                                    <div class="row g-2">
                                        <div class="col-md-3">
                                            <select id="tipo_busqueda_cliente" class="form-select">
                                                <option value="4">Nombre</option>
                                                <option value="2">RUC</option>
                                                <option value="3">DNI</option>
                                            </select>
                                        </div>
                                        <div class="col-md-9">
                                            <select class="form-select" name="cliente_id" id="cliente_id" style="width: 100%"
                                                    data-placeholder="Buscar Cliente">
                                                @if (count($listClientes) > 0)
                                                    @foreach ($listClientes as $item)
                                                        <option value="{{ $item->codCliente }}">{{ $item->texto_cliente }}</option>
                                                    @endforeach
                                                @endif
                                            </select>
                                        </div>
                                        <input type="hidden" name="cliente_razon_social" id="cliente_razon_social"
                                               value="{{ $guia->cliente_razon_social ?? '' }}">
                                        <input type="hidden" name="cliente_nro_documento" id="cliente_nro_documento"
                                               value="{{ $guia->cliente_nro_documento ?? '' }}">
                                        <input type="hidden" name="cliente_documento_tipo_nombre" id="cliente_documento_tipo_nombre"
                                               value="{{ $guia->cliente_documento_tipo_nombre ?? '' }}">

                                        <input type="hidden" name="cliente_direccion" id="cliente_direccion"
                                               value="{{ $guia->cliente_direccion ?? '' }}">
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label mt-1">Direccion</label>
                                    <input type="text" class="form-control" name="direccion" id="direccion"
                                           placeholder="Direccion del cliente" value="{{ $guia->cliente_direccion ?? '' }}">
                                </div>
                            </div>
                            <div class="row" id="div_proveedor"
                                 style="display: {{ ($guia->indicar_proveedor ?? 0) == 1 ? 'block' : 'none' }};">
                                <h5>Proveedor</h5>
                                <div class="col-md-12">
                                    <div class="row g-2">
                                        <div class="col-md-3">
                                            <select id="tipo_busqueda_proveedor" class="form-select" style="width: 100%">
                                                <option value="3">Razon Social</option>
                                                <option value="2">RUC</option>
                                                <option value="1">Codigo</option>
                                            </select>
                                        </div>
                                        <div class="col-md-9">
                                            <select class="form-select" id="proveedor_id" name="proveedor_id"
                                                    data-placeholder="Buscar un proveedor" style="width: 100%">
                                                @if (count($listProveedores) > 0)
                                                    @foreach ($listProveedores as $item)
                                                        <option value="{{ $item->codProveedor }}">
                                                            {{ "[{$item->ruc}] {$item->nombreproveedor}" }}</option>
                                                    @endforeach
                                                @endif
                                            </select>
                                        </div>
                                        <input type="hidden" name="proveedor_nombre" id="proveedor_nombre"
                                               value="{{ $guia->proveedor_nombre ?? '' }}">
                                        <input type="hidden" name="proveedor_ruc" id="proveedor_ruc"
                                               value="{{ $guia->proveedor_ruc ?? '' }}">
                                        <input class="form-control" type="text" name="proveedor_direccion" id="proveedor_direccion">
                                    </div>
                                </div>
                                <div class="col-md-12" hidden>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" value="guia_valodada" id="guia_valorada">
                                        <label class="form-check-label" for="guia_valorada">
                                            Guia Valorada
                                        </label>
                                    </div>
                                </div>
                            </div>


                        </div>
                        <div class="col-md-6" id="div_operaciones">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="row mt-4">
                                        <div class="col-md-6">
                                            <label class="form-label">Divisa</label>
                                            <select class="form-select" name="divisa_id" id="divisa_id">
                                                <option value="1" data-nombre="Soles">Soles</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">F Pago</label>
                                            <select class="form-select" name="forma_pago_id" id="forma_pago_id">
                                                @foreach ($listFormasPago as $item)
                                                    <option value="{{ $item->codFormaPago }}" data-nombre="{{ $item->descripcion }}">
                                                        {{ $item->descripcion }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-12 mt-2">
                                            <label class="form-label">Lista Precio</label>
                                            <select class="form-select" name="codlistaprecio" id="codlistaprecio">
                                                @foreach ($listPrecios as $item)
                                                    <option value="{{ $item->codListaPrecio }}" data-codestacion="{{ $item->codEstacion }}">
                                                        {{ $item->precio }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="row mt-4">
                                        <div class="col-md-12">
                                            <label class="form-label">Tipo Operacion</label>
                                            <select class="form-select" name="tipo_operacion_id" id="tipo_operacion_id">
                                                @foreach ($listTipoOperacion as $item)
                                                    @if ($item->ingresoSalida == 'Salida')
                                                        <option value="{{ $item->tipoOperacion }}"
                                                                data-codigo_motivo_traslado="{{ $item->motivotraslado }}"
                                                                data-nombre_motivo_traslado="{{ $item->descriMotivotraslado }}"
                                                                data-nombre="{{ $item->descripcion }}" {{ $item->selected ?? '' }}>
                                                            {{ $item->descripcion }}
                                                        </option>
                                                    @endif
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-12 mt-2" id="div_almacen_unico">
                                            <label class="form-label">Almacen</label>
                                            <select class="form-select almacen_select" data-almacen_tipo='1' name="codalmacen" id="codalmacen">
                                                @foreach ($listAlmacenes as $item)
                                                    <option value="{{ $item->codAlmacen }}" data-nombre="{{ $item->descripcion }}"
                                                            data-ubigeo="{{ $item->ubigeo ?? '' }}"
                                                            data-direccion="{{ $item->direccion ?? '' }}"
                                                            data-codigo_anexo = "{{ $item->codInterno }}"
                                                        {{ $item->selected ?? '' }}>
                                                        {{ $item->descripcion }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="mt-2" id="div_almacene_transferencia" style="display: none">
                                            <div class="col-md-12">
                                                <label class="form-label">Almacen Origen</label>
                                                <select class="form-select almacen_select" data-almacen_tipo="1" name="cod_almacen_origen" id="cod_almacen_origen">
                                                    @foreach ($listAlmacenOrigen as $item)
                                                        <option value="{{ $item->codAlmacen }}" data-nombre="{{ $item->descripcion }}"
                                                                data-ubigeo="{{ $item->ubigeo ?? '' }}"
                                                                data-direccion="{{ $item->direccion ?? '' }}"
                                                                data-codigo_anexo = "{{ $item->codInterno }}"
                                                            {{ $item->selected ?? '' }}>
                                                            {{ $item->descripcion }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-12">
                                                <label class="form-label">Almacen Destino</label>
                                                <select class="form-select almacen_select" data-almacen_tipo="2" name="cod_almacen_destino" id="cod_almacen_destino">
                                                    @foreach ($listAlmacenDestino as $item)
                                                        <option value="{{ $item->codAlmacen }}" data-nombre="{{ $item->descripcion }}"
                                                                data-ubigeo="{{ $item->ubigeo ?? '' }}"
                                                                data-direccion="{{ $item->direccion ?? '' }}"
                                                                data-codigo_anexo = "{{ $item->codInterno }}"
                                                            {{ $item->selected ?? '' }}>
                                                            {{ $item->descripcion }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <h5>Datos transporte</h5>
                        <div class="col-md-6">
                            <div class="row">
                                <div class="col-md-12">
                                    <input type="hidden" name="modalidad_traslado" id="modalidad_traslado" value="{{ $guia->modalidad_traslado ?? '' }}">
                                    <label class="form-label">Transportista</label>
                                    <select class="form-select" name="transportista_id" id="transportista_id" style="width: 100%"
                                            data-placeholder="Seleccionar un Transportista">
                                        @if (count($listTransportistas ?? []) > 0)
                                            @foreach ($listTransportistas as $item)
                                                <option value="{{ $item->codTransportista }}">{{ $item->texto_transportista }}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                    <input type="hidden" name="transportista_ruc" id="transportista_ruc"
                                           value="{{ $guia->transportista_ruc ?? '' }}">
                                    <input type="hidden" name="transportista_nombre" id="transportista_nombre"
                                           value="{{ $guia->transportista_nombre ?? '' }}">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Direccion</label>
                                    <input type="text" class="form-control" id="transportista_direccion"
                                           name="transportista_direccion" placeholder="Direccion del Transportista"
                                           value="{{ $guia->transportista_direccion ?? '' }}">
                                </div>
                            </div>

                        </div>
                        <div class="col-md-6" >
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label">Motivo Traslado</label>
                                    <select class="form-select" name="motivo_traslado_id" id="motivo_traslado_id">
                                        <option value="1">Envio Equipaje</option>
                                    </select>
                                </div>

                                <div class="col-md-6" id="div_vehiculo" style="{{ $verVehiculo }}">
                                    <label class="form-label">Vehiculo</label>
                                    <select name="vehiculo_id" id="vehiculo_id" class="form-select">
                                        @foreach ($listVehiculos as $item)
                                            @if ($item->estado == 1)
                                                <option data-placa="{{ $item->placaVehiculo }}" data-marca="{{ $item->marcaVehiculo }}">Placa:
                                                    @endif
                                                    {{ $item->placaVehiculo }} - Marca: {{ $item->marcaVehiculo }}</option>
                                                @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="row mt-2" id="div_chofer" style="{{ $verChofer }}">
                                <div class="col-md-6">
                                    <label class="form-label">Chofer</label>
                                    <select name="chofer_id" id="chofer_id" class="form-select">
                                        @foreach ($listChoferes as $item)
                                            @if ($item->estado == 1)
                                                <option data-dni_chofer="{{ $item->dniChofer }}"
                                                        data-brevete_chofer="{{ $item->breveteChofer }}" data-nombre="{{ $item->nombreChofer }}">
                                                    {{ $item->nombreChofer }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Brevete</label>
                                    <input type="text" class="form-control" name="brevete" id="brevete">
                                </div>
                            </div>

                        </div>
                    </div>


                    <div class="row mt-4">
                        <div class="col-md-6">
                            <h5>Datos Partida</h5>
                            <div class="row mt-3 mb-2">
                                <div class="col-md-6">
                                    <label class="form-label">Departamento</label>
                                    <select class="form-select ubigeo mt-1" data-tipo_busqueda="2" data-tipo_ubigeo="partida"
                                            name="partida_departamento" id="partida_departamento">
                                        @foreach ($listUbigeosDepartamentoPartida as $item)
                                            <option value="{{ $item->codUbigeo }}" {{ $item->selected ?? '' }}>{{ $item->descripcion }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Provincia</label>
                                    <select class="form-select mt-1 ubigeo" data-tipo_busqueda="3" data-tipo_ubigeo="partida"
                                            name="partida_provincia" id="partida_provincia">
                                        @if (count($listUbigeosProvinciaPartida) > 0)
                                            @foreach ($listUbigeosProvinciaPartida as $item)
                                                <option value="{{ $item->codUbigeo }}" {{ $item->selected }}>
                                                    {{ $item->descripcion }}
                                                </option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Distrito</label>
                                    <select class="form-select mt-1 ubigeo" name="ubigeo_partida" data-tipo_ubigeo="partida"
                                            id="partida_distrito">
                                        @if (count($listUbigeosDistritoPartida) > 0)
                                            @foreach ($listUbigeosDistritoPartida as $item)
                                                <option value="{{ $item->codUbigeo }}" {{ $item->selected }}>
                                                    {{ $item->descripcion }}
                                                </option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <label class="form-label">Direccion Partida</label>
                                    <input type="text" class="form-control" name="direccion_partida" id="direccion_partida"
                                           placeholder="Direccion de Partida" value="{{ $guia->direccion_partida ?? '' }}">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">

                            <h5>Datos Llegada</h5>
                            <div class="row mt-3 mb-2">
                                <div class="col-md-6">
                                    <label class="form-label">Departamento</label>
                                    <select class="form-select ubigeo mt-1" data-tipo_busqueda="2" data-tipo_ubigeo="llegada"
                                            name="llegada_departamento" id="llegada_departamento">
                                        @foreach ($listUbigeosDepartamentoLlegada as $item)
                                            <option value="{{ $item->codUbigeo }}" {{ $item->selected ?? '' }}>{{ $item->descripcion }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Provincia</label>
                                    <select class="form-select mt-1 ubigeo" data-tipo_busqueda="3" data-tipo_ubigeo="llegada"
                                            name="llegada_provincia" id="llegada_provincia">
                                        @if (count($listUbigeosProvinciaLlegada) > 0)
                                            @foreach ($listUbigeosProvinciaLlegada as $item)
                                                <option value="{{ $item->codUbigeo }}" {{ $item->selected }}>
                                                    {{ $item->descripcion }}
                                                </option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Distrito</label>
                                    <select class="form-select mt-1 ubigeo" name="ubigeo_llegada" data-tipo_ubigeo="llegada"
                                            id="llegada_distrito">
                                        @if (count($listUbigeosDistritoLlegada) > 0)
                                            @foreach ($listUbigeosDistritoLlegada as $item)
                                                <option value="{{ $item->codUbigeo }}" {{ $item->selected }}>
                                                    {{ $item->descripcion }}
                                                </option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <label class="form-label">Llegada</label>
                                    <input type="text" class="form-control" name="direccion_llegada" id="direccion_llegada"
                                           placeholder="Direccion de llegada" value="{{ $guia->direccion_llegada ?? '' }}">
                                </div>
                            </div>
                        </div>
                    </div>

                </form>

                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="row">
                            <div class="col-md-2">
                                <h5><i class="fa fa-list"></i> Detalle</h5>
                            </div>
                            <div class="col-md-6">
                                <button class="btn btn-sm btn-primary" id="btn_cargar_otras_guias"><i class="fa fa-download"></i> Cargar de Otras Guias</button>
                            </div>

                        </div>

                    </div>
                    <div class="col-md-12">
                        <div class="gre-borrador" x-show="borrador.hay" x-cloak>
                          <i class="fa fa-clock-rotate-left"></i>
                          <span>Hay un detalle sin guardar de <strong x-text="borradorRelativo()"></strong>.</span>
                          <button type="button" class="btn btn-sm btn-primary" @click="restaurarBorrador()">Restaurar</button>
                          <button type="button" class="btn btn-sm btn-link" @click="descartarBorrador()">Descartar</button>
                        </div>

                        <div class="gre-buscador mb-3">
                          <label class="form-label" for="producto_valor">
                            Buscar artículo
                            <span class="gre-atajo">
                              escanee el código de barras o escriba el nombre ·
                              <kbd>↑</kbd><kbd>↓</kbd> para elegir · <kbd>Enter</kbd> para agregar
                            </span>
                          </label>

                          <div class="row g-2">
                            <div class="col-md-3 col-lg-2">
                              <select class="form-select" id="tipo_busqueda_articulo"
                                      x-model.number="busqueda.tipo"
                                      @change="busqueda.texto ? buscar() : volverAlBuscador()">
                                <option value="1">Código de barras</option>
                                <option value="4">Descripción</option>
                                <option value="2">Código artículo</option>
                                <option value="3">Código interno</option>
                              </select>
                            </div>

                            <div class="col-md-9 col-lg-10 gre-combo" @click.outside="cerrarBusqueda()">
                              <input type="text" class="form-control" id="producto_valor" autocomplete="off"
                                     :placeholder="busqueda.tipo == 1 ? 'Escanee o escriba el código de barras' : 'Escriba parte del nombre del artículo'"
                                     x-model="busqueda.texto"
                                     @input="alEscribir()"
                                     @keydown.enter.prevent="alPresionarEnter()"
                                     @keydown.arrow-down.prevent="mover(1)"
                                     @keydown.arrow-up.prevent="mover(-1)"
                                     @keydown.escape="cerrarBusqueda()">

                              <span class="gre-combo-estado" x-show="busqueda.cargando" x-cloak>
                                <i class="fa fa-circle-notch fa-spin"></i>
                              </span>

                              <div class="gre-combo-lista" x-show="busqueda.abierto" x-cloak>
                                <div class="gre-combo-vacio" x-show="!busqueda.resultados.length" x-text="busqueda.mensaje"></div>
                                <template x-for="(r, i) in busqueda.resultados" :key="r.id">
                                  <button type="button" class="gre-combo-item"
                                          :class="{ 'activo': i === busqueda.activo }"
                                          @click="elegir(r)" @mouseenter="busqueda.activo = i">
                                    <span class="gre-combo-desc" x-text="r.descripcion"></span>
                                    <span class="gre-combo-meta">
                                      <span x-text="r.codigo_barra"></span>
                                      <span>stock <span x-text="r.stock ?? 0"></span></span>
                                      <span class="gre-num" x-text="money(r.precio_sin_igv || r.precio_publico)"></span>
                                    </span>
                                  </button>
                                </template>
                              </div>
                            </div>
                          </div>

                          <div class="gre-aviso-stock" x-show="validarStock && lineasSinStock > 0" x-cloak>
                            <i class="fa fa-triangle-exclamation"></i>
                            <span>
                              <strong x-text="lineasSinStock"></strong>
                              <span x-text="lineasSinStock === 1 ? 'artículo supera' : 'artículos superan'"></span>
                              el stock disponible. Puede continuar, pero revíselo antes de despachar.
                            </span>
                          </div>
                        </div>


                        <div>
                            <input type="hidden" id="producto_id" name="producto_id">
                            <input type="hidden" id="producto_codigo_barra" name="producto_codigo_barra">
                            <input type="hidden" id="producto_descripcion" name="producto_descripcion">
                            <input type="hidden" id="producto_precio_publico" name="producto_precio_publico">
                            <input type="hidden" id="producto_precio_sin_igv" name="producto_precio_sin_igv">
                            <input type="hidden" id="producto_peso" name="producto_peso">
                            <input type="hidden" id="producto_cod_unidad" name="producto_cod_unidad">
                            <input type="hidden" id="producto_desc_unidad_medida" name="producto_desc_unidad_medida">
                            <input type="hidden" id="producto_sigla_umfe" name="producto_sigla_umfe">
                            <input type="hidden" id="producto_stock" name="producto_stock">
                            <input type="hidden" id="producto_costo_articulo" name="producto_costo_articulo">
                            <input type="hidden" id="producto_afecto" name="producto_afecto">

                        </div>

                        <div class="row mt-2">
                            <div class="col-md-12 table-responsive">
                                <table class="table table-hover table-striped table-sm table-bordered">
                                    <thead>
                                    <th class="text-center">Cod. Barras</th>
                                    <th class="text-center">Codigo</th>
                                    <th class="text-center">Cod. Int</th>
                                    <th class="text-center">Descripcion</th>
                                    <th class="text-center" ><span id="th_tipo_precio">Precio</span></th>
                                    <th class="text-center" style="width: 7rem">Cantidad</th>
                                    <th class="text-center">Uni</th>
                                    <th class="text-center">stock</th>
                                    <th class="text-center">Importe</th>
                                    <th class="text-center" style="width: 4rem">
                                        Descuento
                                        <div class="btn-group btn-group-sm d-flex mt-1" role="group">
                                            <input type="radio" class="btn-check" name="master_discount_type" id="master_discount_pct" value="porcentaje" autocomplete="off" checked>
                                            <label class="btn btn-outline-primary" for="master_discount_pct">%</label>

                                            <input type="radio" class="btn-check" name="master_discount_type" id="master_discount_monto" value="monto" autocomplete="off">
                                            <label class="btn btn-outline-primary" for="master_discount_monto">S/</label>
                                        </div>
                                    </th>
                                    <th class="text-center" hidden>Coso Art.</th>
                                    <th class="text-center">Accion</th>
                                    </thead>
                                    <tbody id="tbody">
                                    <template x-for="(l, i) in lineas" :key="l.codArticulo">
                                      <tr :class="{ 'gre-bonificada': l.bonificacion, 'gre-sin-stock': excedeStock(l) }">
                                        <td class="align-middle" x-text="l.codigoBarra"></td>
                                        <td class="align-middle" x-text="l.codArticulo"></td>
                                        <td class="align-middle" x-text="l.codPlu"></td>
                                        <td class="align-middle" x-text="l.descripcion"></td>
                                        <td class="align-middle gre-num" x-text="money(precioMostrado(l))"></td>
                                        <td class="align-middle">
                                          <input type="number" min="0.01" step="any"
                                                 class="form-control form-control-sm"
                                                 x-model.number="l.cantidad">
                                        </td>
                                        <td class="align-middle" x-text="l.descUnidadMedida || 'UNI'"></td>
                                        <td class="align-middle gre-num">
                                          <span x-text="l.stock"></span>
                                          <i class="fa fa-triangle-exclamation gre-alerta-stock"
                                             x-show="excedeStock(l)" x-cloak
                                             title="La cantidad supera el stock disponible"></i>
                                        </td>
                                        <td class="align-middle gre-num" x-text="money(importeMostrado(l))"></td>
                                        <td class="align-middle">
                                          <input type="number" min="0" step="any"
                                                 class="form-control form-control-sm"
                                                 x-model.number="l.porcentajeDescuento">
                                        </td>
                                        <td class="align-middle" hidden x-text="l.costoArticulo"></td>
                                        <td class="align-middle text-center">
                                          <button type="button" class="btn btn-danger btn-sm" @click="quitar(i)">
                                            <i class="fa fa-times-circle"></i>
                                          </button>
                                        </td>
                                      </tr>
                                    </template>

                                    <tr x-show="!hayLineas">
                                      <td colspan="12" class="gre-vacio">
                                        <i class="fa fa-barcode"></i>
                                        <strong>Aún no hay artículos en esta guía</strong>
                                        <span>Escanee un código de barras o busque por nombre en el campo de arriba.</span>
                                      </td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-2">
                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-md-3">
                                <label class="form-label">Item(s)</label>
                                <input class="form-control" type="text" id="total_items" readonly :value="totalItems">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Cantidad</label>
                                <input class="form-control" type="text" id="total_cantidad" readonly :value="totalCantidad">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Base Calculo</label>
                                <select class="form-select" name="base_calculo" id="base_calculo">
                                    <option value="1" {{ ($guia->base_calculo ?? '') == 1 ? 'selected' : '' }}>Sin IGV</option>
                                    <option value="2" {{ ($guia->base_calculo ?? '') == 2 ? 'selected' : '' }}>Con IGV</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-8">
                                <label class="form-label">Comentario</label>
                                <textarea class="form-control" name="comentario" id="comentario" rows="2">{{ $guia->comentario ?? '' }}</textarea>
                            </div>
                            <div class="col-md-4 ">
                                <label class="form-label">Peso total (Kg)</label>
                                <input type="number" class="form-control" name="peso_bruto_total" id="peso_bruto_total"
                                       value="{{ $guia->peso_bruto_total ?? '' }}">
                            </div>
                        </div>

                    </div>
                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-md-3">
                                <label class="form-label">Descuento</label>
                                <input class="form-control" type="text" id="monto_descuento" readonly :value="money(montoDescuento)">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Valor Venta</label>
                                <input class="form-control" type="text" id="importe_sin_igv" readonly :value="money(valorVenta)">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">IGV</label>
                                <input class="form-control" type="text" id="monto_igv" readonly :value="money(montoIgv)">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Total Venta</label>
                                <input class="form-control" type="text" id="total_venta" readonly :value="money(totalVenta)">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="row mt-4" hidden>
                                    <div class="col-md-5">
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" value="" id="descuento_porcentual">
                                            <label class="form-check-label" for="descuento_porcentual">
                                                Aplicar Descto Porcentual
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <input class="form-control" type="text" placeholder="porcentaje descuento">
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>


                </div>

                <div class="row">
                    <div class="col-md-12">
                        <br>
                        <a type="button" href="{{ route('guiasalida.index') }}" class="btn btn-danger float-start"><i
                                class="fa fa-arrow-left" aria-hidden="true"></i>
                            Cancelar</a>
                        <div id="div_btn_guardar">
                            <button class="btn btn-primary float-end" form="form_store"><i class="fa fa-save" aria-hidden="true"></i> Guardar</button>

                        </div>
                        {{-- <div class="btn-group float-end">
                          <button type="submit" form="form_store" class="btn btn-primary"><i class="fa fa-save"
                              aria-hidden="true"></i> Guardar</button>
                          <button type="button" class="btn btn-dark dropdown-toggle dropdown-toggle-split"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="visually-hidden">Toggle Dropdown</span>
                          </button>
                          <ul class="dropdown-menu">
                            <li><a class="dropdown-item" style="cursor: pointer" id="btnGuardarAvance"><i
                                  class="fa fa-download"></i> <b>Guardar Avance</b></a></li>
                          </ul>
                        </div> --}}
                    </div>
                </div>


            </div>

        </div>
        <div id="modales"></div>
        <input type="hidden" id="validar_stock" value="{{ $validar_stock }}">
    </div>
    @push('js-scripts')
        <script defer src="{{ asset('js/vendor/alpine.min.js') }}"></script>
        <script src="{{ asset('js/gre/http.js?v=') }}{{ rand() }}"></script>
        <script src="{{ asset('js/gre/guia-detalle.js?v=') }}{{ rand() }}"></script>
        <script src="{{ asset('js/gre/guia-form.js?v=') }}{{ rand() }}"></script>

        <script src="{{ asset('js/guias/salida/create.js?v=') }}{{ rand() }}"></script>
        <script src="{{ asset('js/guias/salida/ubigeo.js?v=') }}{{ rand() }}"></script>
        <script src="{{ asset('js/guias/salida/almacen.js?v=') }}{{ rand() }}"></script>
        <script>
            $(document).ready(function() {
                // Seleccionamos TODOS los elementos que vamos a necesitar
                const envioSunatSelect = $('#envio-sunat');
                const toggleFecha = $('#toggle-fecha-traslado');
                const contenedorFecha = $('#contenedor-fecha-traslado');
                const inputFechaTraslado = $('#fecha_inicio_traslado');
                const inputFechaEmision = $('#fecha_emision'); // <-- Nuevo selector

                // --- FUNCIÓN PARA VALIDAR LAS FECHAS ---
                function validarFechaDeTraslado() {
                    const fechaEmision = inputFechaEmision.val();
                    const fechaTraslado = inputFechaTraslado.val();

                    // 1. Establecemos la fecha mínima permitida para el traslado
                    inputFechaTraslado.attr('min', fechaEmision);

                    // 2. Si la fecha de traslado actual es inválida (anterior a la de emisión),
                    //    la corregimos automáticamente.
                    if (fechaTraslado && fechaTraslado < fechaEmision) {
                        inputFechaTraslado.val(fechaEmision);
                    }
                }

                // --- FUNCIÓN PRINCIPAL QUE CONTROLA LA LÓGICA DEL SWITCH ---
                function actualizarEstadoFechaTraslado() {
                    const fechaActual = new Date().toISOString().split('T')[0];
                    const enviarASunat = envioSunatSelect.val() === '1';

                    if (enviarASunat) {
                        // ... (lógica anterior sin cambios)
                        toggleFecha.prop('checked', true);
                        toggleFecha.prop('disabled', true);
                        inputFechaTraslado.prop('required', true);

                        // Si el input está vacío o es inválido, le ponemos la fecha de emisión
                        if (!inputFechaTraslado.val() || inputFechaTraslado.val() < inputFechaEmision.val()) {
                            inputFechaTraslado.val(inputFechaEmision.val());
                        }

                        contenedorFecha.slideDown();
                    } else {
                        // ... (lógica anterior sin cambios)
                        toggleFecha.prop('disabled', false);
                        inputFechaTraslado.prop('required', false);

                        if (toggleFecha.is(':checked')) {
                            contenedorFecha.slideDown();
                            if (!inputFechaTraslado.val()) {
                                inputFechaTraslado.val(inputFechaEmision.val());
                            }
                        } else {
                            contenedorFecha.slideUp();
                            inputFechaTraslado.val('');
                        }
                    }
                    // Nos aseguramos de validar las fechas después de cualquier cambio
                    validarFechaDeTraslado();
                }

                // --- EVENT LISTENERS (ESCUCHADORES DE EVENTOS) ---

                // 1. Cuando cambia la "Fecha de Emisión"
                inputFechaEmision.on('change', function() {
                    // Validamos inmediatamente para actualizar la fecha mínima de traslado
                    validarFechaDeTraslado();
                });

                // 2. Cuando cambia "Enviar a SUNAT"
                envioSunatSelect.on('change', function() {
                    if ($(this).val() === '0') {
                        toggleFecha.prop('checked', false);
                    }
                    actualizarEstadoFechaTraslado();
                });

                // 3. Cuando el usuario usa el switch
                toggleFecha.on('change', function() {
                    actualizarEstadoFechaTraslado();
                });

                // --- INICIALIZACIÓN ---
                // Ejecutamos ambas funciones al cargar la página para el estado inicial
                validarFechaDeTraslado();
                actualizarEstadoFechaTraslado();
            });
        </script>
    @endpush
</div>{{-- /.gre --}}
@endsection
