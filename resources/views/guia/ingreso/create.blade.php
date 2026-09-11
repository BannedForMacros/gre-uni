@extends('layouts.app')

{{--
  Registro de Guia de Ingreso.

  La pantalla va en pasos, en el orden en que se hace el trabajo: que documento
  es, de quien viene, como se mueve, y que lleva dentro. Antes eran ~20 campos
  en una rejilla plana, sin agrupar y sin orden: para encontrar uno habia que
  leerlos todos.

  Los name= e id= de cada campo NO se pueden cambiar: el envio se arma con un
  FormData que los lee por nombre (public/js/guias/ingreso/create.js) y el
  controlador los espera asi.
--}}

@php
  /*
   * Una guia retomada no es "nueva": el distintivo dice en que estado esta.
   *
   * Si la guia existe pero su estado no se puede resolver -la fila de
   * guia_estados no esta, cosa que pasa en instalaciones a medio migrar-, NO se
   * cae a "Nueva": eso seria decirle al usuario que esta empezando una guia
   * cuando esta editando una que ya existe. En ese caso no se muestra nada.
   */
  $esGuiaExistente = isset($guia) && $guia->exists;
  $estadoGuia = $esGuiaExistente
      ? optional(\App\Models\GuiaEstado::find($guia->guia_estado_id))->nombre
      : 'Nueva';

  // El proveedor que ya viene elegido, con la forma que devuelve el buscador,
  // para que una guia retomada y una recien buscada se pinten igual.
  $proveedorElegido = isset($listProveedores[0]) ? [
      'id'               => $listProveedores[0]->codProveedor,
      'text'             => '[' . $listProveedores[0]->ruc . '] ' . $listProveedores[0]->nombreproveedor,
      'proveedor_nombre' => $listProveedores[0]->nombreproveedor,
      'proveedor_ruc'    => $listProveedores[0]->ruc,
  ] : null;
@endphp

@section('content')
<div class="gre">
  <div class="container-fluid">

    <div class="gre-titulo">
      <h1>Guía de Ingreso</h1>
      @if($estadoGuia)<span class="gre-etiqueta">{{ $estadoGuia }}</span>@endif
    </div>

    <form name="form_store" id="form_store" onkeydown="return event.key != 'Enter';">
      <input type="hidden" name="save_local_storage" id="save_local_storage" value="false">
      <input type="hidden" name="id_continuar" value="{{ $guia->id ?? '' }}">
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
              <label class="form-label" for="es_guia_interna">Guía interna</label>
              <select class="form-select" name="es_guia_interna" id="es_guia_interna">
                <option value="0">No</option>
                <option value="1">Sí</option>
              </select>
            </div>

            <div class="col-6 col-md-3 col-lg-2 gre-campo" id="div_serie_interna" style="display:none">
              <label class="form-label" for="serie">Serie</label>
              <select class="form-select" name="serie" id="serie">
                @foreach ($listSeries ?? [] as $item)
                  <option value="{{ $item->numserie }}" {{ $item->selected ?? '' }}>{{ $item->numserie }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-6 col-md-3 col-lg-2 gre-campo" id="div_serie_externa">
              <label class="form-label" for="serie_externa">Serie</label>
              <input type="number" class="form-control gre-num" id="serie_externa" name="serie_externa">
            </div>

            <div class="col-6 col-md-3 col-lg-2 gre-campo">
              <label class="form-label" for="numero">Número</label>
              <input type="text" class="form-control gre-num" id="numero" name="numero">
            </div>

            <div class="col-6 col-md-3 col-lg-2 gre-campo">
              <label class="form-label" for="fecha_emision">Fecha emisión</label>
              <input type="date" class="form-control" value="{{ date('Y-m-d') }}" id="fecha_emision" name="fecha_emision">
            </div>

            <div class="col-6 col-md-3 col-lg-2 gre-campo">
              <label class="form-label" for="fecha_vencimiento">Fecha vencimiento</label>
              <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" class="form-control">
            </div>
          </div>

          <div class="row">
            <div class="col-md-5 col-lg-4 gre-campo">
              <label class="form-label">Relacionar documento</label>
              <div class="gre-radios">
                <div class="form-check">
                  <input class="form-check-input radio_relacion_doc" type="radio" name="relacion_pedido" id="pedido"
                         value="1" {{ (($guia->relacion_pedido ?? '') == 1) ? 'checked' : '' }}>
                  <label class="form-check-label" for="pedido">Pedido</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input radio_relacion_doc" type="radio" name="relacion_pedido" id="recepcion"
                         value="2" {{ (($guia->relacion_pedido ?? 2) == 2) ? 'checked' : '' }}>
                  <label class="form-check-label" for="recepcion">Recepción</label>
                </div>
              </div>
            </div>

            <div class="col-6 col-md-3 col-lg-2 gre-campo">
              <label class="form-label" for="pedido_serie">Serie del documento</label>
              {{-- Solo digitos: el campo del ERP no admite letras, y antes eso se
                   descubria al fallar el registro, no al escribir. --}}
              <input type="text" class="form-control gre-num" name="pedido_serie" id="pedido_serie"
                     placeholder="0000" maxlength="4" value="{{ $guia->pedido_serie ?? '' }}"
                     oninput="this.value = this.value.replace(/[^0-9]/g, '')">
            </div>

            <div class="col-6 col-md-3 col-lg-2 gre-campo">
              <label class="form-label" for="pedido_numero">Número del documento</label>
              <input type="text" class="form-control gre-num" name="pedido_numero" id="pedido_numero"
                     placeholder="0" value="{{ $guia->pedido_numero ?? '' }}"
                     oninput="this.value = this.value.replace(/[^0-9]/g, '')">
            </div>
          </div>
        </div>
      </section>

      {{-- ================================================================
           2. De quien viene
           ================================================================ --}}
      <section class="gre-seccion"
               x-data="greCombo({
                   ruta: '{{ route('guiaingreso.listarProveedores') }}',
                   parametros: {
                       tipo: function () {
                           var s = document.getElementById('tipo_busqueda_proveedor');
                           return s ? s.value : 3;
                       }
                   },
                   elegido: {{ Js::from($proveedorElegido) }}
               })">
        <div class="gre-seccion-cab">
          <span class="gre-paso">2</span>
          <h2>Proveedor</h2>
          <span class="gre-seccion-nota" x-show="hayElegido" x-cloak x-text="etiqueta()"></span>
        </div>

        <div class="gre-seccion-cuerpo">
          <div class="row">
            <div class="col-md-7 col-lg-6 gre-campo">
              <label class="form-label" for="proveedor_busqueda">Buscar proveedor</label>

              {{-- Ya elegido: se muestra como dato confirmado y no como caja de
                   texto, para que no quede duda de si se selecciono de la lista
                   o solo se escribio encima. --}}
              <div class="gre-elegido" x-show="hayElegido" x-cloak>
                <span class="gre-elegido-texto" x-text="etiqueta()"></span>
                <button type="button" title="Quitar proveedor" @click="limpiar()">
                  <i class="fa fa-times"></i>
                </button>
              </div>

              <div class="row g-2" x-show="!hayElegido">
                <div class="col-4">
                  <select id="tipo_busqueda_proveedor" name="tipo_busqueda_proveedor" class="form-select"
                          @change="cambioDeModo()">
                    <option value="3">Razón social</option>
                    <option value="2">RUC</option>
                    <option value="1">Código</option>
                  </select>
                </div>

                <div class="col-8 gre-combo gre-combo-campo" @click.outside="cerrar()">
                  <input type="text" class="form-control" id="proveedor_busqueda" autocomplete="off"
                         placeholder="Haga clic para ver la lista o escriba para buscar"
                         x-model="texto"
                         @input="alEscribir()"
                         @click="abrir()"
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
                        <span class="gre-combo-meta">
                          <span class="gre-num" x-text="r.proveedor_ruc"></span>
                        </span>
                      </button>
                    </template>
                    <div class="gre-combo-pie" x-show="hayMas" x-text="pie"></div>
                  </div>
                </div>
              </div>

              {{-- Lo que viaja en el FormData. Antes el JS lo sacaba del data-*
                   de la <option> que select2 dejaba en el DOM. --}}
              <input type="hidden" id="proveedor_id" name="proveedor_id" :value="valor()">
              <input type="hidden" id="proveedor_nombre" :value="dato('proveedor_nombre')">
              <input type="hidden" id="proveedor_ruc" :value="dato('proveedor_ruc')">
            </div>

            <div class="col-md-5 col-lg-4 gre-campo"
                 x-data="greVendedor({
                     ruta: '{{ route('guiaingreso.getVendedor') }}',
                     codigo: '{{ $guia->vendedor_id ?? '' }}',
                     seleccionado: '{{ $guia->vendedor_id ?? '' }}',
                     vendedores: {{ Js::from(\App\Support\VendedorVista::lista($listVendedores)) }}
                 })">
              <label class="form-label" for="vendedor_codigo">Contacto</label>

              <div class="input-group">
                <input type="text" class="form-control gre-num" id="vendedor_codigo" placeholder="Código"
                       style="max-width: 6.5rem"
                       x-model="codigo" @keydown.enter.prevent="buscar()">
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

              {{-- El nombre lo pide el store. Como input del form entra solo en
                   el FormData. --}}
              <input type="hidden" name="vendedor_nombre" :value="nombreVendedor()">
              <div class="gre-ayuda gre-ayuda-error" x-show="mensaje" x-cloak x-text="mensaje"></div>
            </div>
          </div>
        </div>
      </section>

      {{-- ================================================================
           3. Como se mueve y como se paga
           ================================================================ --}}
      <section class="gre-seccion">
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
                  @if ($item->ingresoSalida == 'Ingreso')
                    <option value="{{ $item->tipoOperacion }}" data-nombre="{{ $item->descripcion }}" {{ $item->selected ?? '' }}>
                      {{ $item->descripcion }}</option>
                  @endif
                @endforeach
              </select>
            </div>

            <div class="col-6 col-md-4 col-lg-3 gre-campo">
              <label class="form-label" for="codalmacen">Almacén</label>
              <select class="form-select" name="codalmacen" id="codalmacen">
                @foreach ($listAlmacenes as $item)
                  <option value="{{ $item->codAlmacen }}" data-nombre="{{ $item->descripcion }}"
                          data-codestacion="{{ $item->codEstacion }}" {{ $item->selected ?? '' }}>{{ $item->descripcion }}</option>
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
                <option value="1">Soles</option>
                <option value="2">Dólares</option>
              </select>
            </div>

            <div class="col-md-8 col-lg-2 gre-campo">
              <label class="form-label" for="condiciones">Condiciones</label>
              <input type="text" class="form-control" name="condiciones" id="condiciones" placeholder="Opcional">
            </div>
          </div>

          @if(\App\Support\ConfiguracionEmpresa::usaConsignados())
            <div class="row">
              <div class="col-md-7 col-lg-5">
                <div class="gre-opcion">
                  <input type="hidden" name="es_consignado" value="0">
                  <input class="form-check-input" id="es_consignado_master" name="es_consignado"
                         type="checkbox" value="1" {{ ($guia->es_consignado ?? 0) == 1 ? 'checked' : '' }}>
                  <label for="es_consignado_master">
                    Esta guía lleva productos consignados
                    <small>Marca la guía completa. Cada artículo se puede ajustar en el detalle.</small>
                  </label>
                </div>
              </div>
            </div>
          @endif
        </div>
      </section>
    </form>

    {{-- ==================================================================
         4. Que lleva dentro
         El estado (lineas + tasa de IGV) vive en el componente, no en
         atributos data-* de cada <tr> como antes. Los totales se derivan.
         ================================================================== --}}
    <div x-data="greGuiaIngreso({
            lineas: {{ Js::from($lineasDetalle ?? []) }},
            tasaIgv: {{ config('gre.igv.tasa', 0.18) }},
            rutas: {
                agregarItem:     '{{ route('guiaingreso.agregarItem') }}',
                cargarOtraGuia:  '{{ route('guiaingreso.cargarOtraGuia') }}',
                listarArticulos: '{{ route('guiaingreso.listarArticulos') }}'
            }
         })"
         x-cloak>

      <section class="gre-seccion">
        <div class="gre-seccion-cab">
          <span class="gre-paso">4</span>
          <h2>Artículos</h2>
          <span class="gre-seccion-nota">
            <span x-text="totalItems"></span><span x-text="totalItems === 1 ? ' artículo' : ' artículos'"></span><span
              x-show="hayLineas"> · <span x-text="totalCantidad"></span> und.</span>
          </span>
        </div>

        <div class="gre-seccion-cuerpo">

          {{-- Aviso de borrador: reemplaza al modal bloqueante que saltaba en
               cada carga de pagina preguntando si cargar los datos sin grabar. --}}
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
                        {{-- Cambiar de modo vuelve a buscar lo ya escrito, no lo borra.
                             Borrarlo rompia el reintento automatico como codigo de
                             barras: el fallback cambia el modo, y eso disparaba este
                             change, que limpiaba justo lo que acababa de encontrar. --}}
                        x-model.number="busqueda.tipo" @change="(busqueda.texto || busqueda.abierto) ? buscar() : volverAlBuscador()">
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
                       @click="abrirBusqueda()"
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
                        <span class="gre-num" x-text="money(r.costo_articulo || r.precio_sin_igv)"></span>
                      </span>
                    </button>
                  </template>
                  <div class="gre-combo-pie" x-show="busqueda.hayMas" x-text="'Se muestran los primeros ' + busqueda.resultados.length + '. Escriba para afinar.'"></div>
                </div>
              </div>
            </div>
          </div>{{-- /.gre-buscador --}}

          {{-- Los lee create.js para armar la linea antes de mandarla. --}}
          <input type="hidden" id="producto_id" name="producto_id">
          <input type="hidden" id="producto_codigo_barra" name="producto_codigo_barra">
          <input type="hidden" id="producto_descripcion" name="producto_descripcion">
          <input type="hidden" id="producto_precio_publico" name="producto_precio_publico">
          <input type="hidden" id="producto_precio_sin_igv" name="producto_precio_sin_igv">
          <input type="hidden" id="producto_peso" name="producto_peso">
          <input type="hidden" id="producto_cod_unidad" name="producto_cod_unidad">
          <input type="hidden" id="producto_desc_unidad_medida" name="producto_desc_unidad_medida">
          <input type="hidden" id="producto_sigla_umfe" name="producto_sigla_umfe">
          <input type="hidden" id="producto_costo_articulo" name="producto_costo_articulo">
          <input type="hidden" id="producto_tipo_igv" name="producto_tipo_igv">

          <div class="gre-scroll">
            <table class="table table-hover table-sm gre-detalle">
              <thead>
                <tr>
                  <th>Cód. barras</th>
                  <th>Código</th>
                  <th>Cód. int</th>
                  <th>Descripción</th>
                  <th class="text-end">Precio</th>
                  <th class="text-center" style="width: 7rem">Cantidad</th>
                  <th>Uni</th>
                  <th class="text-end">Importe</th>
                  <th class="text-center" style="width: 5.5rem">Descto</th>
                  <th class="text-center">Bonif.</th>
                  <th class="text-center">Acción</th>
                </tr>
              </thead>
              <tbody id="tbody">
                {{-- El estado vive en el componente, no en atributos data-* del DOM. --}}
                <template x-for="(l, i) in lineas" :key="l.codArticulo">
                  <tr :class="{ 'gre-bonificada': l.bonificacion }">
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
                    <td class="align-middle gre-num text-end" x-text="money(importeMostrado(l))"></td>
                    <td class="align-middle">
                      <input type="number" min="0" max="100" step="any" class="form-control form-control-sm text-end"
                             x-model.number="l.porcentajeDescuento">
                    </td>
                    <td class="align-middle text-center">
                      <input class="form-check-input" type="checkbox" x-model="l.bonificacion">
                    </td>
                    <td class="align-middle text-center">
                      <button type="button" class="btn btn-outline-danger btn-sm" title="Quitar artículo" @click="quitar(i)">
                        <i class="fa fa-times"></i>
                      </button>
                    </td>
                  </tr>
                </template>

                <tr x-show="!hayLineas">
                  <td colspan="11" class="gre-vacio">
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
              <div class="gre-campo">
                <label class="form-label" for="comentario">Comentario</label>
                <textarea class="form-control" name="comentario" id="comentario" form="form_store"
                          rows="2" placeholder="Opcional">{{ $guia->comentario ?? '' }}</textarea>
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
                  <select class="form-select" name="base_calculo" id="base_calculo" form="form_store"
                          x-model.number="baseCalculo">
                    <option value="2" {{ (($guia->base_calculo ?? '') == 2) ? 'selected' : '' }}>Con IGV</option>
                    <option value="1" {{ (($guia->base_calculo ?? '') == 1) ? 'selected' : '' }}>Sin IGV</option>
                  </select>
                </div>
              </div>
            </div>

            <div class="col-md-5 col-lg-4">
              {{-- Los totales quedan a la vista mientras se cargan articulos.
                   Antes habia que bajar hasta el final para saber en cuanto iba. --}}
              <div class="gre-totales">
                <div class="row g-2">
                  <div class="col-6">
                    <label class="form-label" for="monto_descuento">Descuento</label>
                    <input class="form-control form-control-sm text-end" type="text" id="monto_descuento" readonly :value="money(montoDescuento)">
                  </div>
                  <div class="col-6">
                    <label class="form-label" for="importe_sin_igv">Valor</label>
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

        </div>{{-- /.gre-seccion-cuerpo --}}
      </section>

      {{-- El total viaja aqui abajo, al lado del boton que lo confirma: antes
           Guardar estaba al final de la pagina y el total quedaba fuera de
           vista justo en el momento de decidir. --}}
      <div class="gre-barra">
        <a href="{{ route('guiaingreso.index') }}" class="btn btn-outline-secondary">
          <i class="fa fa-arrow-left"></i> Cancelar
        </a>

        <button type="button" class="btn btn-outline-primary" id="btn_cargar_otras_guias">
          <i class="fa fa-download"></i> Cargar de otras guías
        </button>

        <div class="gre-barra-total">
          <span>Total</span>
          <strong x-text="money(totalVenta)"></strong>
        </div>

        <button type="submit" form="form_store" class="btn btn-primary">
          <i class="fa fa-save"></i> Guardar guía
        </button>
      </div>

    </div>{{-- /x-data greGuiaIngreso --}}

    <div id="modales"></div>
  </div>

  @push('js-scripts')
    {{-- Alpine 3: 15 KB, sin build. defer es obligatorio. --}}
    <script defer src="{{ asset('js/vendor/alpine.min.js') }}"></script>
    <script src="{{ asset('js/gre/http.js?v=') }}{{ rand() }}"></script>
    <script src="{{ asset('js/gre/guia-combo.js?v=') }}{{ rand() }}"></script>
    <script src="{{ asset('js/gre/guia-detalle.js?v=') }}{{ rand() }}"></script>
    <script src="{{ asset('js/gre/guia-form.js?v=') }}{{ rand() }}"></script>
    <script src="{{ asset('js/gre/guia-vendedor.js?v=') }}{{ rand() }}"></script>
    <script src="{{ asset('js/guias/ingreso/create.js?v=') }}{{ rand() }}"></script>
  @endpush
</div>{{-- /.gre --}}
@endsection
