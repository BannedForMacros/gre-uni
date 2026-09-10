@extends('layouts.app')

{{--
  Registro de Guia de Salida.

  Misma estructura que la de ingreso, en pasos y en el orden en que se hace el
  trabajo. Aqui hay un paso mas -el traslado- porque una salida ademas se
  mueve: de donde sale, a donde llega y quien la lleva.

  Los name= e id= de cada campo NO se pueden cambiar: el envio se arma con un
  FormData que los lee por nombre (public/js/guias/salida/create.js) y el
  controlador los espera asi. Los id de los contenedores que el JS muestra y
  oculta segun el tipo de operacion tampoco: div_cliente, div_proveedor,
  div_operaciones, div_almacen_unico, div_almacene_transferencia, div_chofer,
  div_vehiculo y div_btn_guardar.
--}}

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
                },
                rutasUbigeo: {
                    listarUbigeos:        '{{ route('guiasalida.listarUbigeos') }}',
                    getUbigeosPorAlmacen: '{{ route('guiasalida.getUbigeosPorAlmacen') }}'
                },
                ubigeoInicial: {
                    partida: { distrito: '{{ $guia->ubigeo_partida ?? '' }}', direccion: '{{ $guia->direccion_partida ?? '' }}' },
                    llegada: { distrito: '{{ $guia->ubigeo_llegada ?? '' }}', direccion: '{{ $guia->direccion_llegada ?? '' }}' }
                }
             })"
     x-cloak>
  <div class="container-fluid">

    <div class="gre-titulo">
      <h1>Guía de Salida</h1>
      <span class="gre-etiqueta">Nueva</span>
    </div>

    <form name="form_store" id="form_store" onkeydown="return event.key != 'Enter';">
      <input type="hidden" name="save_local_storage" id="save_local_storage" value="false">
      <input type="hidden" name="id_continua" id="id_continua" value="{{ $guia->id ?? '' }}">

      {{-- Cliente de las transferencias entre almacenes: la empresa se compra
           a si misma, asi que estos datos son fijos y no los elige nadie. --}}
      <input type="hidden" name="cliente_transf_id" value="{{ $clienteTransferencia->codCliente ?? '' }}">
      <input type="hidden" name="cliente_transf_razon_social" value="{{ $clienteTransferencia->razonSocial ?? '' }}">
      <input type="hidden" name="cliente_transf_nro_documento" value="{{ $clienteTransferencia->rucCliente ?? '' }}">
      <input type="hidden" name="cliente_transf_documento_tipo_nombre" value="RUC">
      <input type="hidden" name="cliente_transf_direccion" value="{{ $clienteTransferencia->direccion ?? '' }}">
      @csrf

      {{-- ================================================================
           1. El documento
           ================================================================ --}}
      <section class="gre-seccion">
        <div class="gre-seccion-cab">
          <span class="gre-paso">1</span>
          <h2>Documento</h2>
        </div>

        <div class="gre-seccion-cuerpo">
          <div class="row">
            <div class="col-6 col-md-3 col-lg-2 gre-campo">
              <label class="form-label" for="serie">Serie</label>
              <select class="form-select" name="serie" id="serie">
                @foreach ($listSeries as $item)
                  <option value="{{ $item->numserie }}" {{ $item->selected ?? '' }}>{{ $item->numserie }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-6 col-md-3 col-lg-2 gre-campo">
              <label class="form-label" for="span_numero">Número</label>
              <input type="text" class="form-control gre-num" id="span_numero" readonly>
            </div>

            <div class="col-6 col-md-3 col-lg-2 gre-campo">
              <label class="form-label" for="fecha_emision">Fecha emisión</label>
              <input type="date" class="form-control" value="{{ date('Y-m-d') }}" name="fecha_emision" id="fecha_emision">
            </div>

            <div class="col-6 col-md-3 col-lg-2 gre-campo">
              <label class="form-label" for="envio-sunat">Enviar a SUNAT</label>
              <select class="form-select" name="envio_sunat" id="envio-sunat">
                <option value="0" {{ ($guia->envio_sunat ?? '') == 0 ? 'selected' : '' }}>No</option>
                <option value="1" {{ ($guia->envio_sunat ?? '') == 1 ? 'selected' : '' }}>Sí</option>
              </select>
            </div>

            <div class="col-md-6 col-lg-4 gre-campo">
              <label class="form-label" for="fecha_inicio_traslado">Inicio de traslado</label>
              <div class="d-flex align-items-center gap-2">
                <div class="form-check form-switch mb-0">
                  <input class="form-check-input" type="checkbox" role="switch" id="toggle-fecha-traslado">
                  <label class="form-check-label" for="toggle-fecha-traslado">Indicar</label>
                </div>
                <div id="contenedor-fecha-traslado" class="flex-grow-1" style="display: none;">
                  <input type="date" class="form-control" id="fecha_inicio_traslado" name="fecha_inicio_traslado">
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 col-lg-5 gre-campo">
              <label class="form-label">Pedido</label>
              <div class="row g-2 align-items-center">
                <div class="col-4">
                  <div class="form-check gre-radios mb-0">
                    <input class="form-check-input" type="checkbox" value="1" id="pedido_interno"
                           {{ ($guia->pedido_interno ?? '') == 1 ? 'checked' : '' }} name="pedido_interno">
                    <label class="form-check-label" for="pedido_interno">Interno</label>
                  </div>
                </div>
                <div class="col-4">
                  <input type="text" class="form-control gre-num input_pedido_interno" placeholder="Serie"
                         name="pedido_serie" id="pedido_serie" value="{{ $guia->pedido_serie ?? '' }}">
                </div>
                <div class="col-4">
                  <input type="text" class="form-control gre-num input_pedido_interno" placeholder="Número"
                         name="pedido_numero" id="pedido_numero" value="{{ $guia->pedido_numero ?? '' }}">
                </div>
              </div>
            </div>

            <div class="col-md-6 col-lg-4 gre-campo"
                 x-data="greVendedor({
                     ruta: '{{ route('guiasalida.getVendedor') }}',
                     codigo: '{{ $guia->vendedor_id ?? '' }}',
                     seleccionado: '{{ $guia->vendedor_id ?? '' }}',
                     vendedores: {{ Js::from(\App\Support\VendedorVista::lista($listVendedores)) }}
                 })">
              <label class="form-label" for="vendedor_codigo">Vendedor</label>
              <div class="input-group">
                <input type="text" class="form-control gre-num" id="vendedor_codigo" placeholder="Código"
                       style="max-width: 6.5rem" x-model="codigo" @keydown.enter.prevent="buscar()">
                <button class="btn btn-outline-secondary" type="button" id="btnBuscarVendedor"
                        title="Buscar por código" @click="buscar()" :disabled="buscando">
                  <i class="fa" :class="buscando ? 'fa-circle-notch fa-spin' : 'fa-search'"></i>
                </button>
                <select class="form-select" name="vendedor_id" id="vendedor_id"
                        @change="seleccionado = $event.target.value">
                  <template x-for="v in vendedores" :key="v.codigo">
                    <option :value="v.codigo" :selected="String(v.codigo) === seleccionado" x-text="v.etiqueta"></option>
                  </template>
                </select>
              </div>
              <input type="hidden" name="vendedor_nombre" :value="nombreVendedor()">
              <div class="gre-ayuda gre-ayuda-error" x-show="mensaje" x-cloak x-text="mensaje"></div>
            </div>

            <div class="col-md-12 col-lg-3">
              <div class="gre-opcion">
                <input class="form-check-input" id="indicar_proveedor" name="indicar_proveedor"
                       type="checkbox" {{ ($guia->indicar_proveedor ?? 0) == 1 ? 'checked' : '' }}>
                <label for="indicar_proveedor">
                  La guía va a un proveedor
                  <small>En vez de a un cliente. Cambia a quién se le pide el destino.</small>
                </label>
              </div>

              @if(\App\Support\ConfiguracionEmpresa::usaConsignados())
                <div class="gre-opcion mt-2">
                  <input class="form-check-input" id="es_consignado_master" type="checkbox" value="1"
                         {{ ($guia->es_consignado ?? 0) == 1 ? 'checked' : '' }}>
                  <label for="es_consignado_master">
                    Lleva productos consignados
                    <small>Marca la guía completa.</small>
                  </label>
                </div>
              @endif
            </div>
          </div>

          <div hidden>
            <label class="form-label">Comprobante de pago</label>
            <input type="text" class="form-control" name="comprobante_pago">
          </div>
        </div>
      </section>

      {{-- ================================================================
           2. A quien va
           ================================================================ --}}
      <section class="gre-seccion">
        <div class="gre-seccion-cab">
          <span class="gre-paso">2</span>
          <h2>Destinatario</h2>
        </div>

        <div class="gre-seccion-cuerpo">

          {{-- CLIENTE. El id del contenedor lo usa el JS para alternar entre
               cliente y proveedor segun la casilla de arriba. --}}
          <div id="div_cliente" style="display: {{ ($guia->indicar_proveedor ?? 0) == 0 ? '' : 'none' }}"
               x-data="greCombo({
                   ruta: '{{ route('guiasalida.listarClientes') }}',
                   minimo: 3,
                   parametros: {
                       tipo_busqueda_cliente: function () {
                           var s = document.getElementById('tipo_busqueda_cliente');
                           return s ? s.value : 4;
                       }
                   },
                   alElegir: function (c) {
                       document.getElementById('direccion').value                     = c ? (c.direccion || '') : '';
                       document.getElementById('cliente_direccion').value             = c ? (c.direccion || '') : '';
                       document.getElementById('cliente_razon_social').value          = c ? (c.razon_social || '') : '';
                       document.getElementById('cliente_nro_documento').value         = c ? (c.nro_documento || '') : '';
                       document.getElementById('cliente_documento_tipo_nombre').value = c ? (c.documento_tipo_nombre || '') : '';
                   }
               })">
            <div class="row">
              <div class="col-md-7 col-lg-6 gre-campo">
                <label class="form-label" for="cliente_busqueda">Cliente</label>

                <div class="gre-elegido" x-show="hayElegido" x-cloak>
                  <span class="gre-elegido-texto" x-text="etiqueta()"></span>
                  <button type="button" title="Quitar cliente" @click="limpiar()"><i class="fa fa-times"></i></button>
                </div>

                <div class="row g-2" x-show="!hayElegido">
                  <div class="col-4">
                    <select id="tipo_busqueda_cliente" class="form-select"
                            @change="texto.length >= minimo ? buscar() : null">
                      <option value="4">Nombre</option>
                      <option value="2">RUC</option>
                      <option value="3">DNI</option>
                    </select>
                  </div>
                  <div class="col-8 gre-combo gre-combo-campo" @click.outside="cerrar()">
                    <input type="text" class="form-control" id="cliente_busqueda" autocomplete="off"
                           placeholder="Escriba y elija de la lista"
                           x-model="texto"
                           @input="alEscribir()"
                           @keydown.enter.prevent="alPresionarEnter()"
                           @keydown.arrow-down.prevent="mover(1)"
                           @keydown.arrow-up.prevent="mover(-1)"
                           @keydown.escape="cerrar()">

                    <span class="gre-combo-estado" x-show="cargando" x-cloak>
                      <i class="fa fa-circle-notch fa-spin"></i>
                    </span>

                    <div class="gre-combo-lista" x-show="abierto" x-cloak>
                      <div class="gre-combo-vacio" x-show="!resultados.length" x-text="mensaje"></div>
                      <template x-for="(r, i) in resultados" :key="r.id">
                        <button type="button" class="gre-combo-item" :class="{ 'activo': i === activo }"
                                @click="elegir(r)" @mouseenter="activo = i">
                          <span class="gre-combo-desc" x-text="r.razon_social || r.text"></span>
                          <span class="gre-combo-meta">
                            <span x-text="r.documento_tipo_nombre"></span>
                            <span class="gre-num" x-text="r.nro_documento"></span>
                          </span>
                        </button>
                      </template>
                    </div>
                  </div>
                </div>

                <input type="hidden" name="cliente_id" id="cliente_id" :value="valor()">
                <input type="hidden" name="cliente_razon_social" id="cliente_razon_social" value="{{ $guia->cliente_razon_social ?? '' }}">
                <input type="hidden" name="cliente_nro_documento" id="cliente_nro_documento" value="{{ $guia->cliente_nro_documento ?? '' }}">
                <input type="hidden" name="cliente_documento_tipo_nombre" id="cliente_documento_tipo_nombre" value="{{ $guia->cliente_documento_tipo_nombre ?? '' }}">
                <input type="hidden" name="cliente_direccion" id="cliente_direccion" value="{{ $guia->cliente_direccion ?? '' }}">
              </div>

              <div class="col-md-5 col-lg-6 gre-campo">
                <label class="form-label" for="direccion">Dirección del cliente</label>
                <input type="text" class="form-control" name="direccion" id="direccion"
                       placeholder="Se llena al elegir el cliente" value="{{ $guia->cliente_direccion ?? '' }}">
              </div>
            </div>
          </div>

          {{-- TRANSFERENCIA ENTRE ALMACENES. Aqui no hay a quien elegir: la
               empresa se envia a si misma, y esos datos ya viajan en los campos
               cliente_transf_*. Sin este bloque la seccion quedaba COMPLETAMENTE
               en blanco al elegir una transferencia, y parecia que la pantalla
               se habia roto. --}}
          <div id="div_destinatario_transferencia" style="display: none">
            <div class="row">
              <div class="col-md-7 col-lg-6 gre-campo">
                <label class="form-label">Destinatario</label>
                <div class="gre-elegido">
                  <span class="gre-elegido-texto">
                    {{ $clienteTransferencia->razonSocial ?? 'La propia empresa' }}
                  </span>
                  <span class="gre-elegido-ruc">{{ $clienteTransferencia->rucCliente ?? '' }}</span>
                </div>
                <div class="gre-ayuda">
                  En una transferencia la mercaderia no cambia de dueno: va de un almacen a otro.
                </div>
              </div>
            </div>
          </div>

          {{-- PROVEEDOR. Alternativa al cliente; el JS alterna los dos bloques. --}}
          <div id="div_proveedor" style="display: {{ ($guia->indicar_proveedor ?? 0) == 1 ? 'block' : 'none' }}"
               x-data="greCombo({
                   ruta: '{{ route('guiasalida.listarProveedores') }}',
                   minimo: 1,
                   parametros: {
                       tipo: function () {
                           var s = document.getElementById('tipo_busqueda_proveedor');
                           return s ? s.value : 3;
                       }
                   },
                   alElegir: function (p) {
                       document.getElementById('proveedor_nombre').value    = p ? (p.proveedor_nombre || '') : '';
                       document.getElementById('proveedor_ruc').value       = p ? (p.proveedor_ruc || '') : '';
                       document.getElementById('proveedor_direccion').value = p ? (p.proveedor_direccion || '') : '';

                       // Cambiar de proveedor invalida lo ya cargado: los
                       // precios y el stock son suyos.
                       if (typeof window.limpiarDetalle === 'function') { window.limpiarDetalle(); }
                       if (typeof window.validarDireccionProveedor === 'function') {
                           window.setTimeout(window.validarDireccionProveedor, 200);
                       }
                   }
               })">
            <div class="row">
              <div class="col-md-7 col-lg-6 gre-campo">
                <label class="form-label" for="proveedor_busqueda">Proveedor</label>

                <div class="gre-elegido" x-show="hayElegido" x-cloak>
                  <span class="gre-elegido-texto" x-text="etiqueta()"></span>
                  <button type="button" title="Quitar proveedor" @click="limpiar()"><i class="fa fa-times"></i></button>
                </div>

                <div class="row g-2" x-show="!hayElegido">
                  <div class="col-4">
                    <select id="tipo_busqueda_proveedor" class="form-select"
                            @change="texto.length >= minimo ? buscar() : null">
                      <option value="3">Razón social</option>
                      <option value="2">RUC</option>
                      <option value="1">Código</option>
                    </select>
                  </div>
                  <div class="col-8 gre-combo gre-combo-campo" @click.outside="cerrar()">
                    <input type="text" class="form-control" id="proveedor_busqueda" autocomplete="off"
                           placeholder="Escriba y elija de la lista"
                           x-model="texto"
                           @input="alEscribir()"
                           @keydown.enter.prevent="alPresionarEnter()"
                           @keydown.arrow-down.prevent="mover(1)"
                           @keydown.arrow-up.prevent="mover(-1)"
                           @keydown.escape="cerrar()">

                    <span class="gre-combo-estado" x-show="cargando" x-cloak>
                      <i class="fa fa-circle-notch fa-spin"></i>
                    </span>

                    <div class="gre-combo-lista" x-show="abierto" x-cloak>
                      <div class="gre-combo-vacio" x-show="!resultados.length" x-text="mensaje"></div>
                      <template x-for="(r, i) in resultados" :key="r.id">
                        <button type="button" class="gre-combo-item" :class="{ 'activo': i === activo }"
                                @click="elegir(r)" @mouseenter="activo = i">
                          <span class="gre-combo-desc" x-text="r.proveedor_nombre || r.text"></span>
                          <span class="gre-combo-meta"><span class="gre-num" x-text="r.proveedor_ruc"></span></span>
                        </button>
                      </template>
                    </div>
                  </div>
                </div>

                <input type="hidden" id="proveedor_id" name="proveedor_id" :value="valor()">
                <input type="hidden" name="proveedor_nombre" id="proveedor_nombre" value="{{ $guia->proveedor_nombre ?? '' }}">
                <input type="hidden" name="proveedor_ruc" id="proveedor_ruc" value="{{ $guia->proveedor_ruc ?? '' }}">
              </div>

              <div class="col-md-5 col-lg-6 gre-campo">
                <label class="form-label" for="proveedor_direccion">Dirección del proveedor</label>
                <input class="form-control" type="text" name="proveedor_direccion" id="proveedor_direccion">
              </div>
            </div>

            <div hidden>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" value="guia_valodada" id="guia_valorada">
                <label class="form-check-label" for="guia_valorada">Guía valorada</label>
              </div>
            </div>
          </div>
        </div>
      </section>

      {{-- ================================================================
           3. Como se mueve y como se paga
           ================================================================ --}}
      <section class="gre-seccion" id="div_operaciones">
        <div class="gre-seccion-cab">
          <span class="gre-paso">3</span>
          <h2>Operación</h2>
        </div>

        <div class="gre-seccion-cuerpo">
          <div class="row">
            <div class="col-6 col-md-4 col-lg-3 gre-campo">
              <label class="form-label" for="tipo_operacion_id">Tipo de operación</label>
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

            {{-- Almacen unico frente a origen+destino: el JS alterna estos dos
                 bloques segun si la operacion es una transferencia. --}}
            <div class="col-6 col-md-4 col-lg-3 gre-campo" id="div_almacen_unico">
              <label class="form-label" for="codalmacen">Almacén</label>
              <select class="form-select almacen_select" data-almacen_tipo="1" name="codalmacen" id="codalmacen"
                      @change="alCambiarAlmacen('codalmacen')">
                @foreach ($listAlmacenes as $item)
                  <option value="{{ $item->codAlmacen }}" data-nombre="{{ $item->descripcion }}"
                          data-ubigeo="{{ $item->ubigeo ?? '' }}" data-direccion="{{ $item->direccion ?? '' }}"
                          data-codigo_anexo="{{ $item->codInterno }}" {{ $item->selected ?? '' }}>
                    {{ $item->descripcion }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-md-8 col-lg-6" id="div_almacene_transferencia" style="display: none">
              <div class="row">
                <div class="col-6 gre-campo">
                  <label class="form-label" for="cod_almacen_origen">Almacén origen</label>
                  <select class="form-select almacen_select" data-almacen_tipo="1" name="cod_almacen_origen" id="cod_almacen_origen"
                          @change="alCambiarAlmacen('cod_almacen_origen')">
                    @foreach ($listAlmacenOrigen as $item)
                      <option value="{{ $item->codAlmacen }}" data-nombre="{{ $item->descripcion }}"
                              data-ubigeo="{{ $item->ubigeo ?? '' }}" data-direccion="{{ $item->direccion ?? '' }}"
                              data-codigo_anexo="{{ $item->codInterno }}" {{ $item->selected ?? '' }}>
                        {{ $item->descripcion }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="col-6 gre-campo">
                  <label class="form-label" for="cod_almacen_destino">Almacén destino</label>
                  <select class="form-select almacen_select" data-almacen_tipo="2" name="cod_almacen_destino" id="cod_almacen_destino"
                          @change="alCambiarAlmacen('cod_almacen_destino')">
                    @foreach ($listAlmacenDestino as $item)
                      <option value="{{ $item->codAlmacen }}" data-nombre="{{ $item->descripcion }}"
                              data-ubigeo="{{ $item->ubigeo ?? '' }}" data-direccion="{{ $item->direccion ?? '' }}"
                              data-codigo_anexo="{{ $item->codInterno }}" {{ $item->selected ?? '' }}>
                        {{ $item->descripcion }}</option>
                    @endforeach
                  </select>
                </div>
              </div>
            </div>

            <div class="col-6 col-md-4 col-lg-2 gre-campo">
              <label class="form-label" for="codlistaprecio">Lista de precio</label>
              <select class="form-select" name="codlistaprecio" id="codlistaprecio">
                @foreach ($listPrecios as $item)
                  <option value="{{ $item->codListaPrecio }}" data-codestacion="{{ $item->codEstacion }}">{{ $item->precio }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-6 col-md-4 col-lg-2 gre-campo">
              <label class="form-label" for="forma_pago_id">Forma de pago</label>
              <select class="form-select" name="forma_pago_id" id="forma_pago_id">
                @foreach ($listFormasPago as $item)
                  <option value="{{ $item->codFormaPago }}" data-nombre="{{ $item->descripcion }}">{{ $item->descripcion }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-6 col-md-4 col-lg-2 gre-campo">
              <label class="form-label" for="divisa_id">Divisa</label>
              <select class="form-select" name="divisa_id" id="divisa_id">
                <option value="1" data-nombre="Soles">Soles</option>
              </select>
            </div>
          </div>
        </div>
      </section>

      {{-- ================================================================
           4. El traslado
           ================================================================ --}}
      <section class="gre-seccion">
        <div class="gre-seccion-cab">
          <span class="gre-paso">4</span>
          <h2>Traslado</h2>
        </div>

        <div class="gre-seccion-cuerpo">
          <div class="row"
               x-data="greCombo({
                   ruta: '{{ route('guiasalida.listarTransportistas') }}',
                   minimo: 1,
                   alElegir: function (t) {
                       document.getElementById('transportista_direccion').value = t ? (t.transportista_direccion || '') : '';
                       document.getElementById('transportista_ruc').value       = t ? (t.ruc || '') : '';
                       document.getElementById('transportista_nombre').value    = t ? (t.nombre || '') : '';
                       if (t && typeof window.callGetModalidadTraslado === 'function') { window.callGetModalidadTraslado(); }
                   }
               })">
            <input type="hidden" name="modalidad_traslado" id="modalidad_traslado" value="{{ $guia->modalidad_traslado ?? '' }}">

            <div class="col-md-6 col-lg-4 gre-campo">
              <label class="form-label" for="transportista_busqueda">Transportista</label>

              <div class="gre-elegido" x-show="hayElegido" x-cloak>
                <span class="gre-elegido-texto" x-text="etiqueta()"></span>
                <button type="button" title="Quitar transportista" @click="limpiar()"><i class="fa fa-times"></i></button>
              </div>

              <div class="gre-combo gre-combo-campo" x-show="!hayElegido" @click.outside="cerrar()">
                <input type="text" class="form-control" id="transportista_busqueda" autocomplete="off"
                       placeholder="Escriba y elija de la lista"
                       x-model="texto"
                       @input="alEscribir()"
                       @keydown.enter.prevent="alPresionarEnter()"
                       @keydown.arrow-down.prevent="mover(1)"
                       @keydown.arrow-up.prevent="mover(-1)"
                       @keydown.escape="cerrar()">

                <span class="gre-combo-estado" x-show="cargando" x-cloak>
                  <i class="fa fa-circle-notch fa-spin"></i>
                </span>

                <div class="gre-combo-lista" x-show="abierto" x-cloak>
                  <div class="gre-combo-vacio" x-show="!resultados.length" x-text="mensaje"></div>
                  <template x-for="(r, i) in resultados" :key="r.id">
                    <button type="button" class="gre-combo-item" :class="{ 'activo': i === activo }"
                            @click="elegir(r)" @mouseenter="activo = i">
                      <span class="gre-combo-desc" x-text="r.nombre || r.text"></span>
                      <span class="gre-combo-meta"><span class="gre-num" x-text="r.ruc"></span></span>
                    </button>
                  </template>
                </div>
              </div>

              <input type="hidden" name="transportista_id" id="transportista_id" :value="valor()">
              <input type="hidden" name="transportista_ruc" id="transportista_ruc" value="{{ $guia->transportista_ruc ?? '' }}">
              <input type="hidden" name="transportista_nombre" id="transportista_nombre" value="{{ $guia->transportista_nombre ?? '' }}">
            </div>

            <div class="col-md-6 col-lg-4 gre-campo">
              <label class="form-label" for="transportista_direccion">Dirección del transportista</label>
              <input type="text" class="form-control" id="transportista_direccion" name="transportista_direccion"
                     placeholder="Se llena al elegir el transportista" value="{{ $guia->transportista_direccion ?? '' }}">
            </div>

            <div class="col-md-6 col-lg-2 gre-campo">
              <label class="form-label" for="motivo_traslado_id">Motivo del traslado</label>
              <select class="form-select" name="motivo_traslado_id" id="motivo_traslado_id">
                <option value="1">Envío equipaje</option>
              </select>
            </div>

            <div class="col-md-6 col-lg-2 gre-campo" id="div_vehiculo" style="{{ $verVehiculo }}">
              <label class="form-label" for="vehiculo_id">Vehículo</label>
              <select name="vehiculo_id" id="vehiculo_id" class="form-select">
                @foreach ($listVehiculos as $item)
                  @if ($item->estado == 1)
                    <option data-placa="{{ $item->placaVehiculo }}" data-marca="{{ $item->marcaVehiculo }}">
                      {{ $item->placaVehiculo }} — {{ $item->marcaVehiculo }}</option>
                  @endif
                @endforeach
              </select>
            </div>
          </div>

          <div class="row" id="div_chofer" style="{{ $verChofer }}">
            <div class="col-md-6 col-lg-4 gre-campo">
              <label class="form-label" for="chofer_id">Chofer</label>
              <select name="chofer_id" id="chofer_id" class="form-select">
                @foreach ($listChoferes as $item)
                  @if ($item->estado == 1)
                    <option data-dni_chofer="{{ $item->dniChofer }}" data-brevete_chofer="{{ $item->breveteChofer }}"
                            data-nombre="{{ $item->nombreChofer }}">{{ $item->nombreChofer }}</option>
                  @endif
                @endforeach
              </select>
            </div>
            <div class="col-md-6 col-lg-2 gre-campo">
              <label class="form-label" for="brevete">Brevete</label>
              <input type="text" class="form-control gre-num" name="brevete" id="brevete">
            </div>
          </div>

          {{-- Partida y llegada, uno al lado del otro: se comparan de un vistazo
               y se nota enseguida si el traslado no sale de donde deberia. --}}
          <div class="row">
            <div class="col-lg-6">
              <div class="gre-subtitulo">Punto de partida</div>
              <div class="row">
                <div class="col-6 col-xl-4 gre-campo">
                  <label class="form-label" for="partida_departamento">Departamento</label>
                  <select class="form-select" id="partida_departamento" name="partida_departamento"
                          x-model="ubigeos.partida.departamento" @change="alCambiarDepartamento('partida')">
                    <option value="">Seleccione</option>
                    <template x-for="u in ubigeos.departamentos" :key="u.codUbigeo">
                      <option :value="u.codUbigeo" x-text="u.descripcion"></option>
                    </template>
                  </select>
                </div>
                <div class="col-6 col-xl-4 gre-campo">
                  <label class="form-label" for="partida_provincia">Provincia</label>
                  <select class="form-select" id="partida_provincia" name="partida_provincia"
                          x-model="ubigeos.partida.provincia" @change="alCambiarProvincia('partida')"
                          :disabled="!ubigeos.partida.departamento">
                    <option value="">Seleccione</option>
                    <template x-for="u in ubigeos.partida.provincias" :key="u.codUbigeo">
                      <option :value="u.codUbigeo" x-text="u.descripcion"></option>
                    </template>
                  </select>
                </div>
                <div class="col-12 col-xl-4 gre-campo">
                  <label class="form-label" for="partida_distrito">Distrito</label>
                  <select class="form-select" id="partida_distrito" name="ubigeo_partida"
                          x-model="ubigeos.partida.distrito" :disabled="!ubigeos.partida.provincia">
                    <option value="">Seleccione</option>
                    <template x-for="u in ubigeos.partida.distritos" :key="u.codUbigeo">
                      <option :value="u.codUbigeo" x-text="u.descripcion"></option>
                    </template>
                  </select>
                  <span class="gre-ubigeo-cargando" x-show="ubigeos.partida.cargando" x-cloak>
                    <i class="fa fa-circle-notch fa-spin"></i> cargando…
                  </span>
                </div>
                <div class="col-12 gre-campo">
                  <label class="form-label" for="direccion_partida">Dirección de partida</label>
                  <input type="text" class="form-control" id="direccion_partida" name="direccion_partida"
                         x-model="ubigeos.partida.direccion" placeholder="Calle, número, referencia">
                </div>
              </div>
            </div>

            <div class="col-lg-6">
              <div class="gre-subtitulo">Punto de llegada</div>
              <div class="row">
                <div class="col-6 col-xl-4 gre-campo">
                  <label class="form-label" for="llegada_departamento">Departamento</label>
                  <select class="form-select" id="llegada_departamento" name="llegada_departamento"
                          x-model="ubigeos.llegada.departamento" @change="alCambiarDepartamento('llegada')">
                    <option value="">Seleccione</option>
                    <template x-for="u in ubigeos.departamentos" :key="u.codUbigeo">
                      <option :value="u.codUbigeo" x-text="u.descripcion"></option>
                    </template>
                  </select>
                </div>
                <div class="col-6 col-xl-4 gre-campo">
                  <label class="form-label" for="llegada_provincia">Provincia</label>
                  <select class="form-select" id="llegada_provincia" name="llegada_provincia"
                          x-model="ubigeos.llegada.provincia" @change="alCambiarProvincia('llegada')"
                          :disabled="!ubigeos.llegada.departamento">
                    <option value="">Seleccione</option>
                    <template x-for="u in ubigeos.llegada.provincias" :key="u.codUbigeo">
                      <option :value="u.codUbigeo" x-text="u.descripcion"></option>
                    </template>
                  </select>
                </div>
                <div class="col-12 col-xl-4 gre-campo">
                  <label class="form-label" for="llegada_distrito">Distrito</label>
                  <select class="form-select" id="llegada_distrito" name="ubigeo_llegada"
                          x-model="ubigeos.llegada.distrito" :disabled="!ubigeos.llegada.provincia">
                    <option value="">Seleccione</option>
                    <template x-for="u in ubigeos.llegada.distritos" :key="u.codUbigeo">
                      <option :value="u.codUbigeo" x-text="u.descripcion"></option>
                    </template>
                  </select>
                  <span class="gre-ubigeo-cargando" x-show="ubigeos.llegada.cargando" x-cloak>
                    <i class="fa fa-circle-notch fa-spin"></i> cargando…
                  </span>
                </div>
                <div class="col-12 gre-campo">
                  <label class="form-label" for="direccion_llegada">Dirección de llegada</label>
                  <input type="text" class="form-control" id="direccion_llegada" name="direccion_llegada"
                         x-model="ubigeos.llegada.direccion" placeholder="Calle, número, referencia">
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </form>

    {{-- ================================================================
         5. Que lleva dentro
         ================================================================ --}}
    <section class="gre-seccion">
      <div class="gre-seccion-cab">
        <span class="gre-paso">5</span>
        <h2>Artículos</h2>
        <span class="gre-seccion-nota">
          <span x-text="totalItems"></span><span x-text="totalItems === 1 ? ' artículo' : ' artículos'"></span><span
            x-show="hayLineas"> · <span x-text="totalCantidad"></span> und.</span>
        </span>
      </div>

      <div class="gre-seccion-cuerpo">

        <div class="gre-borrador" x-show="borrador.hay" x-cloak>
          <i class="fa fa-clock-rotate-left"></i>
          <span>Hay un detalle sin guardar de <strong x-text="borradorRelativo()"></strong>.</span>
          <button type="button" class="btn btn-sm btn-primary" @click="restaurarBorrador()">Restaurar</button>
          <button type="button" class="btn btn-sm btn-link" @click="descartarBorrador()">Descartar</button>
        </div>

        <div class="gre-buscador">
          <label class="form-label" for="producto_valor">
            Buscar artículo
            <span class="gre-atajo">
              escanee el código de barras o escriba el nombre ·
              <kbd>↑</kbd><kbd>↓</kbd> para elegir · <kbd>Enter</kbd> para agregar
            </span>
          </label>

          <div class="row g-2">
            <div class="col-4 col-md-3 col-lg-2">
              <select class="form-select" id="tipo_busqueda_articulo"
                      x-model.number="busqueda.tipo"
                      @change="busqueda.texto ? buscar() : volverAlBuscador()">
                <option value="1">Código de barras</option>
                <option value="4">Descripción</option>
                <option value="2">Código artículo</option>
                <option value="3">Código interno</option>
              </select>
            </div>

            <div class="col-8 col-md-9 col-lg-10 gre-combo" @click.outside="cerrarBusqueda()">
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
                  <button type="button" class="gre-combo-item" :class="{ 'activo': i === busqueda.activo }"
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
        </div>{{-- /.gre-buscador --}}

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

        <div class="gre-scroll">
          <table class="table table-hover table-sm gre-detalle">
            <thead>
              <tr>
                <th>Cód. barras</th>
                <th>Código</th>
                <th>Cód. int</th>
                <th>Descripción</th>
                <th class="text-end"><span id="th_tipo_precio">Precio</span></th>
                <th class="text-center" style="width: 7rem">Cantidad</th>
                <th>Uni</th>
                <th class="text-end">Stock</th>
                <th class="text-end">Importe</th>
                <th class="text-center" style="width: 6.5rem">
                  Descuento
                  <div class="btn-group btn-group-sm d-flex mt-1" role="group">
                    <input type="radio" class="btn-check" name="master_discount_type" id="master_discount_pct" value="porcentaje" autocomplete="off" checked>
                    <label class="btn btn-outline-primary" for="master_discount_pct">%</label>
                    <input type="radio" class="btn-check" name="master_discount_type" id="master_discount_monto" value="monto" autocomplete="off">
                    <label class="btn btn-outline-primary" for="master_discount_monto">S/</label>
                  </div>
                </th>
                <th hidden>Costo art.</th>
                <th class="text-center">Acción</th>
              </tr>
            </thead>
            <tbody id="tbody">
              <template x-for="(l, i) in lineas" :key="l.codArticulo">
                <tr :class="{ 'gre-bonificada': l.bonificacion, 'gre-sin-stock': excedeStock(l) }">
                  <td class="align-middle gre-num" x-text="l.codigoBarra"></td>
                  <td class="align-middle gre-num" x-text="l.codArticulo"></td>
                  <td class="align-middle gre-num" x-text="l.codPlu"></td>
                  <td class="align-middle" x-text="l.descripcion"></td>
                  <td class="align-middle gre-num text-end" x-text="money(precioMostrado(l))"></td>
                  <td class="align-middle">
                    <input type="number" min="0.01" step="any" class="form-control form-control-sm text-end"
                           x-model.number="l.cantidad">
                  </td>
                  <td class="align-middle" x-text="l.descUnidadMedida || 'UNI'"></td>
                  <td class="align-middle gre-num text-end">
                    <span x-text="l.stock"></span>
                    <i class="fa fa-triangle-exclamation gre-alerta-stock"
                       x-show="excedeStock(l)" x-cloak
                       title="La cantidad supera el stock disponible"></i>
                  </td>
                  <td class="align-middle gre-num text-end" x-text="money(importeMostrado(l))"></td>
                  <td class="align-middle">
                    <input type="number" min="0" step="any" class="form-control form-control-sm text-end"
                           x-model.number="l.porcentajeDescuento">
                  </td>
                  <td class="align-middle" hidden x-text="l.costoArticulo"></td>
                  <td class="align-middle text-center">
                    <button type="button" class="btn btn-outline-danger btn-sm" title="Quitar artículo" @click="quitar(i)">
                      <i class="fa fa-times"></i>
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
        </div>{{-- /.gre-scroll --}}

        <div class="row mt-3">
          <div class="col-md-7 col-lg-8">
            <div class="row">
              <div class="col-md-8 gre-campo">
                <label class="form-label" for="comentario">Comentario</label>
                <textarea class="form-control" name="comentario" id="comentario" form="form_store"
                          rows="2" placeholder="Opcional">{{ $guia->comentario ?? '' }}</textarea>
              </div>
              <div class="col-md-4 gre-campo">
                <label class="form-label" for="peso_bruto_total">Peso total (kg)</label>
                <input type="number" class="form-control gre-num text-end" name="peso_bruto_total" id="peso_bruto_total"
                       form="form_store" value="{{ $guia->peso_bruto_total ?? '' }}">
              </div>
            </div>

            <div class="row">
              <div class="col-4 col-md-3 gre-campo">
                <label class="form-label" for="total_items">Ítems</label>
                <input class="form-control gre-num text-end" type="text" id="total_items" readonly :value="totalItems">
              </div>
              <div class="col-4 col-md-3 gre-campo">
                <label class="form-label" for="total_cantidad">Cantidad</label>
                <input class="form-control gre-num text-end" type="text" id="total_cantidad" readonly :value="totalCantidad">
              </div>
              <div class="col-4 col-md-4 gre-campo">
                <label class="form-label" for="base_calculo">Base de cálculo</label>
                <select class="form-select" name="base_calculo" id="base_calculo" form="form_store">
                  <option value="1" {{ ($guia->base_calculo ?? '') == 1 ? 'selected' : '' }}>Sin IGV</option>
                  <option value="2" {{ ($guia->base_calculo ?? '') == 2 ? 'selected' : '' }}>Con IGV</option>
                </select>
              </div>
            </div>
          </div>

          <div class="col-md-5 col-lg-4">
            <div class="gre-totales">
              <div class="row g-2">
                <div class="col-6">
                  <label class="form-label" for="monto_descuento">Descuento</label>
                  <input class="form-control form-control-sm text-end" type="text" id="monto_descuento" readonly :value="money(montoDescuento)">
                </div>
                <div class="col-6">
                  <label class="form-label" for="importe_sin_igv">Valor venta</label>
                  <input class="form-control form-control-sm text-end" type="text" id="importe_sin_igv" readonly :value="money(valorVenta)">
                </div>
                <div class="col-6">
                  <label class="form-label" for="monto_igv">
                    IGV <span x-text="'(' + (tasaIgv * 100).toFixed(0) + '%)'"></span>
                  </label>
                  <input class="form-control form-control-sm text-end" type="text" id="monto_igv" readonly :value="money(montoIgv)">
                </div>
                <div class="col-6 gre-total-final">
                  <label class="form-label" for="total_venta">Total</label>
                  <input class="form-control form-control-sm text-end" type="text" id="total_venta" readonly :value="money(totalVenta)">
                </div>
              </div>
            </div>
          </div>
        </div>

        <div hidden>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" value="" id="descuento_porcentual">
            <label class="form-check-label" for="descuento_porcentual">Aplicar descuento porcentual</label>
          </div>
        </div>

      </div>{{-- /.gre-seccion-cuerpo --}}
    </section>

    {{-- El total viaja al lado del boton que lo confirma. --}}
    <div class="gre-barra">
      <a href="{{ route('guiasalida.index') }}" class="btn btn-outline-secondary">
        <i class="fa fa-arrow-left"></i> Cancelar
      </a>

      <button type="button" class="btn btn-outline-primary" id="btn_cargar_otras_guias">
        <i class="fa fa-download"></i> Cargar de otras guías
      </button>

      <div class="gre-barra-total">
        <span>Total</span>
        <strong x-text="money(totalVenta)"></strong>
      </div>

      {{-- El id lo usa el JS para ocultar Guardar en algunos tipos de operacion. --}}
      <div id="div_btn_guardar">
        <button class="btn btn-primary" form="form_store"><i class="fa fa-save"></i> Guardar guía</button>
      </div>
    </div>

    <div id="modales"></div>
    <input type="hidden" id="validar_stock" value="{{ $validar_stock }}">
  </div>

  @push('js-scripts')
    {{-- Alpine 3: 15 KB, sin build. defer es obligatorio. --}}
    <script defer src="{{ asset('js/vendor/alpine.min.js') }}"></script>
    <script src="{{ asset('js/gre/http.js?v=') }}{{ rand() }}"></script>
    <script src="{{ asset('js/gre/guia-combo.js?v=') }}{{ rand() }}"></script>
    <script src="{{ asset('js/gre/guia-detalle.js?v=') }}{{ rand() }}"></script>
    <script src="{{ asset('js/gre/guia-ubigeos.js?v=') }}{{ rand() }}"></script>
    <script src="{{ asset('js/gre/guia-form.js?v=') }}{{ rand() }}"></script>
    <script src="{{ asset('js/gre/guia-vendedor.js?v=') }}{{ rand() }}"></script>
    <script src="{{ asset('js/guias/salida/create.js?v=') }}{{ rand() }}"></script>

    <script>
      /**
       * Fecha de inicio de traslado.
       *
       * Enviando a SUNAT la fecha es obligatoria y no puede ser anterior a la
       * de emision, asi que el interruptor queda forzado y bloqueado. Sin
       * envio a SUNAT es opcional y el usuario decide.
       */
      $(document).ready(function () {
        var envioSunat        = $('#envio-sunat');
        var toggleFecha       = $('#toggle-fecha-traslado');
        var contenedorFecha   = $('#contenedor-fecha-traslado');
        var fechaTraslado     = $('#fecha_inicio_traslado');
        var fechaEmision      = $('#fecha_emision');

        function limitarPorFechaDeEmision() {
          fechaTraslado.attr('min', fechaEmision.val());

          // Si al mover la emision el traslado quedo antes, se corrige solo en
          // vez de dejar al usuario con una fecha que el servidor rechazara.
          if (fechaTraslado.val() && fechaTraslado.val() < fechaEmision.val()) {
            fechaTraslado.val(fechaEmision.val());
          }
        }

        function actualizarEstado() {
          if (envioSunat.val() === '1') {
            toggleFecha.prop('checked', true).prop('disabled', true);
            fechaTraslado.prop('required', true);

            if (!fechaTraslado.val() || fechaTraslado.val() < fechaEmision.val()) {
              fechaTraslado.val(fechaEmision.val());
            }
            contenedorFecha.slideDown();
          } else {
            toggleFecha.prop('disabled', false);
            fechaTraslado.prop('required', false);

            if (toggleFecha.is(':checked')) {
              contenedorFecha.slideDown();
              if (!fechaTraslado.val()) { fechaTraslado.val(fechaEmision.val()); }
            } else {
              contenedorFecha.slideUp();
              fechaTraslado.val('');
            }
          }

          limitarPorFechaDeEmision();
        }

        fechaEmision.on('change', limitarPorFechaDeEmision);

        envioSunat.on('change', function () {
          if ($(this).val() === '0') { toggleFecha.prop('checked', false); }
          actualizarEstado();
        });

        toggleFecha.on('change', actualizarEstado);

        limitarPorFechaDeEmision();
        actualizarEstado();
      });
    </script>
  @endpush
</div>{{-- /.gre --}}
@endsection
