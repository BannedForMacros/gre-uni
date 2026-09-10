@extends('layouts.app')


@section('content')
<div class="gre">
  <div class="container-fluid">
    <div class="row justify-content-center">
      <div class="col-md-12">
        <h5><i class="fa fa-ticket"></i> Guia de Ingreso</h5>
        <form name="form_store" id="form_store" onkeydown="return event.key != 'Enter';">
          <input type="hidden" name="save_local_storage" id="save_local_storage" value="false">
          <input type="hidden" name="id_continuar" value="{{ $guia->id ?? '' }}" >
          @csrf
          <div class="row">
            <div class="col-md-2">
              <label class="form-label">Guia Interna</label>
              <select class="form-select" name="es_guia_interna" id="es_guia_interna">
                <option value="0">No</option>
                <option value="1">Si</option>
              </select>
            </div>
            <div class="col-md-2 mb-2" id="div_serie_interna" style="display:none ">
              <label class="form-label">Serie</label>
              <select class="form-select" name="serie" id="serie">
                @foreach ($listSeries ?? [] as $item)
                  <option value="{{ $item->numserie }}" {{ $item->selected ?? '' }}>
                    {{ $item->numserie }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="col-md-3 mb-2" id="div_serie_externa">
              <label class="form-label">Serie</label>
              <input type="number" class="form-control" id="serie_externa" name="serie_externa">
            </div>
            <div class="col-md-3 mb-2">
              <label class="form-label">Numero</label>
              <input type="text" class="form-control" id="numero" name="numero" >
            </div>

          </div>
          <div class="row mt-2">

            
            <div class="col-md-6">
              <div class="row">

                <div class="col-md-3 mb-2">
                  <label class="form-label">Fecha Emision</label>
                  <input type="date" class="form-control" value="{{ date('Y-m-d') }}" id="fecha_emision" name="fecha_emision">
                </div>
                <div class="col-md-3 col-sm-4 mb-2">
                  <label class="form-label">Fecha Vencimiento</label>
                  <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" class="form-control">
                </div>
              </div>
              <div class="row mt-2">
                <div class="col-md-12">

                  <div x-data="greVendedor({
                          ruta: '{{ route('guiaingreso.getVendedor') }}',
                          codigo: '{{ $guia->vendedor_id ?? '' }}',
                          seleccionado: '{{ $guia->vendedor_id ?? '' }}',
                          vendedores: {{ Js::from(\App\Support\VendedorVista::lista($listVendedores)) }}
                       })">

                    <div class="row">
                      <div class="col-md-2">
                        <label class="form-label mt-2">Codigo</label>
                      </div>
                      <div class="col-md-6">
                        <div class="input-group">
                          <input type="text" class="form-control" id="vendedor_codigo" placeholder="Ingresar codigo"
                                 x-model="codigo" @keydown.enter.prevent="buscar()">
                          <button class="btn btn-primary" type="button" id="btnBuscarVendedor"
                                  @click="buscar()" :disabled="buscando">
                            <i class="fa" :class="buscando ? 'fa-circle-notch fa-spin' : 'fa-search'"></i>
                          </button>
                        </div>
                      </div>
                    </div>

                    <div class="row">
                      <div class="col-md-12">
                        <label class="form-label">Contacto</label>
                        <select class="form-select " name="vendedor_id" id="vendedor_id" style="width: 100%"
                                @change="seleccionado = $event.target.value">
                          <template x-for="v in vendedores" :key="v.codigo">
                            <option :value="v.codigo" :selected="String(v.codigo) === seleccionado"
                                    x-text="v.etiqueta"></option>
                          </template>
                        </select>

                        {{-- El nombre lo pide el store. Como input del form entra
                             solo en el FormData; antes el JS lo sacaba del
                             data-* de la <option> elegida. --}}
                        <input type="hidden" name="vendedor_nombre" :value="nombreVendedor()">

                        <div class="form-text text-danger" x-show="mensaje" x-cloak x-text="mensaje"></div>
                      </div>

                    </div>
                  </div>
                </div>
                  <div class="col-md-3 mb-2">
                    <label class="form-label">Estado</label>
                    <input type="text" class="form-control" readonly value="GENERADA">
                  </div>

                  @if(\App\Support\ConfiguracionEmpresa::usaConsignados())
                  <div class="col-md-6 mb-2">
                    <label class="form-label text-primary" style="font-weight: bold; font-size: 0.85rem;">¿TIENE PRODUCTOS CONSIGNADOS?</label>

                    <div class="form-check mt-1">
                      <input type="hidden" name="es_consignado" value="0">
                      <input class="form-check-input" id="es_consignado_master" name="es_consignado"
                            type="checkbox" value="1" {{ ($guia->es_consignado ?? 0) == 1 ? 'checked' : '' }} />
                      <label class="form-check-label" for="es_consignado_master">Productos Consignados</label>
                    </div>
                  </div>
                  @endif
              </div>

              <div class="row">
                <div class="col-md-7 mb-2">
                  <label class="form-label">Relacionar Documento</label>
                  <div class="row">
                    <div class="col-md-4">
                      <div class="form-check">
                        <input class="form-check-input radio_relacion_doc" type="radio" name="relacion_pedido" id="pedido"
                          value="1" {{ (($guia->relacion_pedido ?? '') == 1) ? 'checked' : '' ; }}>
                        <label class="form-check-label" for="pedido">
                          Pedido
                        </label>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input radio_relacion_doc" type="radio" name="relacion_pedido" id="recepcion"
                          value="2" {{ (($guia->relacion_pedido ?? 2) == 2) ? 'checked' : '' ; }}>
                        <label class="form-check-label" for="recepcion" >
                          Recepcion
                        </label>
                      </div>
                    </div>
                    <div class="col-md-8">
						{{-- <label class="form-label">Serie-Nro</label> --}}
						<div class="row">
							<div class="col-md-6">
								<input 
									type="text" 
									class="form-control" 
									name="pedido_serie" 
									id="pedido_serie" 
									placeholder="Serie" 
									value="{{ $guia->pedido_serie ?? '' }}"
									/* CAMBIO 2: Este código impide escribir letras, solo deja números 0-9 */
									oninput="this.value = this.value.replace(/[^0-9]/g, '')"
									maxlength="4"
								>
							</div>
							
							<div class="col-md-6">
								<input 
									type="text" 
									class="form-control" 
									name="pedido_numero" 
									id="pedido_numero" 
									placeholder="Numero" 
									value="{{ $guia->pedido_numero ?? '' }}"
									/* También protegemos el número por si acaso */
									oninput="this.value = this.value.replace(/[^0-9]/g, '')"
								>
							</div>
						</div>
					</div>

                  </div>
                </div>
              </div>


            </div>
            <div class="col-md-6">
              <div class="row">
                <div class="col-md-12">
                  <label class="form-label">Proveedor</label>
                  <div class="row g-2">
                    <div class="col-md-3">
                      <select id="tipo_busqueda_proveedor" name="tipo_busqueda_proveedor" class="form-select" style="width: 100%">
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
                            <option value="{{ $item->codProveedor }}" data-proveedor_nombre="{{ $item->nombreproveedor }}"
                              data-proveedor_ruc="{{ $item->ruc }}" selected="selected">
                              {{ "[$item->ruc] $item->nombreproveedor" }}
                            </option>
                          @endforeach
                            
                        @endif
                      </select>
                      <input type="hidden" id="proveedor_nombre" value="{{ $listProveedores[0]->nombreproveedor ?? '' }}">
                      <input type="hidden" id="proveedor_ruc" value="{{ $listProveedores[0]->ruc ?? ''}}">
                    </div>
                  </div>
                </div>
              </div>

              <div class="row">
                <div class="col-md-3 mb-2">
                  <label class="form-label">Divisa</label>
                  <select class="form-select" name="divisa_id" id="divisa_id">
                    <option value="1">Soles</option>
                    <option value="2">Dolares</option>
                  </select>
                </div>
                <div class="col-md-6 mb-2">
                  <label class="form-label">Condiciones</label>
                  <input type="text" class="form-control" name="condiciones" placeholder="Condiciones">
                </div>
              </div>

              <div class="row mb-2">
                <div class="col-md-3">
                  <label class="form-label">F. Pago</label>
                  <select class="form-select" name="forma_pago_id" id="forma_pago_id">
                    @foreach ($listFormasPago as $item)
                      <option value="{{ $item->codFormaPago }}" data-nombre="{{ $item->descripcion }}">
                        {{ $item->descripcion }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="col-md-5">
                  <label class="form-label">Tipo Operacion</label>
                  <select class="form-select" name="tipo_operacion_id" id="tipo_operacion_id">
                    @foreach ($listTipoOperacion as $item)
                      @if ($item->ingresoSalida == 'Ingreso')
                        <option value="{{ $item->tipoOperacion }}" data-nombre="{{ $item->descripcion }}" {{ $item->selected ?? '' }}>
                          {{ $item->descripcion }}</option>
                      @endif
                    @endforeach
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Almacen</label>
                  <select class="form-select" name="codalmacen" id="codalmacen">
                    @foreach ($listAlmacenes as $item)
                      <option value="{{ $item->codAlmacen }}" data-nombre="{{ $item->descripcion }}"
                        data-codestacion="{{ $item->codEstacion }}" {{ $item->selected ?? '' }}>{{ $item->descripcion }}</option>
                    @endforeach
                  </select>
                </div>
              </div>

            </div>

          </div>

        </form>

        {{-- ------------------------------------------------------------------
             Scope de Alpine para el detalle de la guia.
             El estado (lineas + tasa de IGV) vive aqui, no en atributos data-*
             de cada <tr> como antes. Los totales se derivan solos.
             ------------------------------------------------------------------ --}}
        <div x-data="greGuiaIngreso({
                lineas: {{ Js::from($lineasDetalle ?? []) }},
                tasaIgv: {{ config('gre.igv.tasa', 0.18) }},
                rutas: {
                    agregarItem:    '{{ route('guiaingreso.agregarItem') }}',
                    cargarOtraGuia:  '{{ route('guiaingreso.cargarOtraGuia') }}',
                    listarArticulos: '{{ route('guiaingreso.listarArticulos') }}'
                }
             })"
             x-cloak>

        <div class="row mt-4">
          <div class="row">
            <div class="col-md-2">
              <h5><i class="fa fa-list"></i> Detalle</h5>
            </div>
            <div class="col-md-6">
              <button class="btn btn-sm btn-primary" id="btn_cargar_otras_guias"><i class="fa fa-download"></i> Cargar de Otras Guias</button>
            </div>

          </div>
          <div class="col-md-12">
            {{-- Aviso de borrador: reemplaza al modal bloqueante que saltaba en
                 cada carga de pagina preguntando si cargar los datos sin grabar. --}}
            <div class="gre-borrador" x-show="borrador.hay" x-cloak>
              <i class="fa fa-clock-rotate-left"></i>
              <span>
                Hay un detalle sin guardar de <strong x-text="borradorRelativo()"></strong>.
              </span>
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
                          {{-- Cambiar de modo vuelve a buscar lo ya escrito, no lo borra.
                               Borrarlo rompia el reintento automatico como codigo de
                               barras: el fallback cambia el modo, y eso disparaba este
                               change, que limpiaba justo lo que acababa de encontrar. --}}
                          x-model.number="busqueda.tipo" @change="busqueda.texto ? buscar() : volverAlBuscador()">
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
                          <span class="gre-num" x-text="money(r.costo_articulo || r.precio_sin_igv)"></span>
                        </span>
                      </button>
                    </template>
                  </div>
                </div>
              </div>
            </div>{{-- /.gre-buscador --}}

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
              <input type="hidden" id="producto_costo_articulo" name="producto_costo_articulo">
              <input type="hidden" id="producto_tipo_igv" name="producto_tipo_igv">



            </div>

            <div class="row mt-2">
              <div class="col-md-12">
                <div class="gre-scroll">
                <table class="table table-hover table-sm table-bordered gre-detalle">
                  <thead>
                    <th class="text-center">Cod. Barras</th>
                    <th class="text-center">Codigo</th>
                    <th class="text-center">Cod. Int</th>
                    <th class="text-center">Descripcion</th>
                    <th class="text-center">Precio</th>
                    <th class="text-center" style="width: 7rem">Cantidad</th>
                    <th class="text-center">Uni</th>
                    <th class="text-center">Importe</th>
                    <th class="text-center" style="width: 4rem">Descto</th>
                    <th class="text-center">Bonificacion</th>
                    <th class="text-center">Accion</th>
                  </thead>
                  <tbody id="tbody">
                    {{-- El estado vive en el componente, no en atributos data-* del DOM. --}}
                    <template x-for="(l, i) in lineas" :key="l.codArticulo">
                      <tr :class="{ 'gre-bonificada': l.bonificacion }">
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
                        <td class="align-middle gre-num" x-text="money(importeMostrado(l))"></td>
                        <td class="align-middle">
                          <input type="number" min="0" max="100" step="any"
                                 class="form-control form-control-sm"
                                 x-model.number="l.porcentajeDescuento">
                        </td>
                        <td class="align-middle text-center">
                          <input class="form-check-input" type="checkbox" x-model="l.bonificacion">
                        </td>
                        <td class="align-middle text-center">
                          <button type="button" class="btn btn-danger btn-sm" @click="quitar(i)">
                            <i class="fa fa-times-circle"></i>
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
              </div>
            </div>
          </div>
        </div>

        <div class="row mt-2">
          <div class="col-md-12">
            <div class="row">
              <div class="col-md-8 mb-2">
                <div class="row">
                  <div class="col-md-10">
                    <label class="form-label">Comentario</label>
                    <textarea class="form-control" name="comentario" id="comentario" rows="2">{{ $guia->comentario ?? '' }}</textarea>

                  </div>
                </div>
              </div>
              <div class="col-md-4">
                {{-- Los totales quedan a la vista mientras se cargan articulos.
                     Antes habia que bajar hasta el final para saber en cuanto iba. --}}
                <div class="gre-totales">
                  <div class="row g-2">
                    <div class="col-6 col-xxl-3">
                      <label class="form-label" for="monto_descuento">Descuento</label>
                      <input class="form-control form-control-sm" type="text" id="monto_descuento" readonly :value="money(montoDescuento)">
                    </div>
                    <div class="col-6 col-xxl-3">
                      <label class="form-label" for="importe_sin_igv">Valor</label>
                      <input class="form-control form-control-sm" type="text" id="importe_sin_igv" readonly :value="money(valorVenta)">
                    </div>
                    <div class="col-6 col-xxl-3">
                      <label class="form-label" for="monto_igv">
                        IGV <span x-text="'(' + (tasaIgv * 100).toFixed(0) + '%)'"></span>
                      </label>
                      <input class="form-control form-control-sm" type="text" id="monto_igv" readonly :value="money(montoIgv)">
                    </div>
                    <div class="col-6 col-xxl-3 gre-total-final">
                      <label class="form-label" for="total_venta">Total</label>
                      <input class="form-control form-control-sm" type="text" id="total_venta" readonly :value="money(totalVenta)">
                    </div>
                  </div>
                  <div class="mt-2">
                    <span class="gre-contador">
                      <i class="fa fa-list-ul"></i>
                      <span x-text="totalItems"></span>
                      <span x-text="totalItems === 1 ? 'artículo' : 'artículos'"></span>
                      <span x-show="hayLineas">·</span>
                      <span x-show="hayLineas" x-text="totalCantidad + ' und.'"></span>
                    </span>
                  </div>
                </div>
              </div>
            </div>
            <div class="row">
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
                  {{-- <div class="col-md-3">
                    <label class="form-label">Flete</label>
                    <input class="form-control" type="text" readonly>
                  </div> --}}
                  <div class="col-md-3">
                    <label class="form-label">Base Calculo</label>
                    <select class="form-select" name="base_calculo" id="base_calculo" x-model.number="baseCalculo">
                      <option value="2" {{ (($guia->base_calculo ?? '') == 2) ? 'selected' : '' ; }}>Con IGV</option>
                      <option value="1" {{ (($guia->base_calculo ?? '') == 1) ? 'selected' : '' ; }}>Sin IGV</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>


        <div class="row">
          <div class="col-md-12">
            <br>
            <a type="button" href="{{ route('guiaingreso.index') }}" class="btn btn-danger float-start"><i
                class="fa fa-arrow-left" aria-hidden="true"></i>
              Cancelar</a>
            <button type="submit" form="form_store" class="btn btn-primary float-end" ><i class="fa fa-save" aria-hidden="true"></i>
              Guardar</button>

            <!-- Example split danger button -->
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

    </div>{{-- /x-data greDetalleGuia --}}
    <div id="modales"></div>
  </div>

  @push('js-scripts')
    {{-- Alpine 3: 15 KB, sin build. defer es obligatorio. --}}
    <script defer src="{{ asset('js/vendor/alpine.min.js') }}"></script>
    <script src="{{ asset('js/gre/http.js?v=') }}{{ rand() }}"></script>
    <script src="{{ asset('js/gre/guia-detalle.js?v=') }}{{ rand() }}"></script>
    <script src="{{ asset('js/gre/guia-form.js?v=') }}{{ rand() }}"></script>
    <script src="{{ asset('js/gre/guia-vendedor.js?v=') }}{{ rand() }}"></script>
    <script src="{{ asset('js/guias/ingreso/create.js?v=') }}{{ rand() }}"></script>

  @endpush
</div>{{-- /.gre --}}
@endsection
