


<?php $__env->startSection('content'); ?>
<div class="gre">
  <div class="container-fluid">
    <div class="row justify-content-center">
      <div class="col-md-12">
        <h5><i class="fa fa-ticket"></i> Guia de Ingreso</h5>
        <form name="form_store" id="form_store" onkeydown="return event.key != 'Enter';">
          <input type="hidden" name="save_local_storage" id="save_local_storage" value="false">
          <input type="hidden" name="id_continuar" value="<?php echo e($guia->id ?? ''); ?>" >
          <?php echo csrf_field(); ?>
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
                <?php $__currentLoopData = $listSeries ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <option value="<?php echo e($item->numserie); ?>" <?php echo e($item->selected ?? ''); ?>>
                    <?php echo e($item->numserie); ?>

                  </option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
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
                  <input type="date" class="form-control" value="<?php echo e(date('Y-m-d')); ?>" id="fecha_emision" name="fecha_emision">
                </div>
                <div class="col-md-3 col-sm-4 mb-2">
                  <label class="form-label">Fecha Vencimiento</label>
                  <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" class="form-control">
                </div>
              </div>
              <div class="row mt-2">
                <div class="col-md-12">

                  <div class="row">
                    <div class="col-md-2">
                      <label class="form-label mt-2">Codigo</label>
                    </div>
                    <div class="col-md-6">
                      
                      <div class="input-group">
                        <input type="text" class="form-control" id="vendedor_codigo" placeholder="Ingresar codigo" aria-describedby="button-addon2" value="<?php echo e($guia->vendedor_id ?? ''); ?>">
                        <button class="btn btn-primary" type="button" id="btnBuscarVendedor"><i class="fa fa-search"></i></button>
                      </div>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col-md-12">
                      <label class="form-label">Contacto</label>
                      <select class="form-select " name="vendedor_id" id="vendedor_id" style="width: 100%">
                        <?php $__currentLoopData = $listVendedores; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                          <option value="<?php echo e($item->codTrabajador); ?>"
                            data-vendedor_nombre="<?php echo e("{$item->apellidos} {$item->nombres}"); ?>"
                            <?php echo e(($item->selected ?? '') == 'selected' ? 'selected' : ''); ?>>
                            <?php echo e("[{$item->codTrabajador}] {$item->apellidos} {$item->nombres}"); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                      </select>
                    </div>

                  </div>
                </div>
                  <div class="col-md-3 mb-2">
                    <label class="form-label">Estado</label>
                    <input type="text" class="form-control" readonly value="GENERADA">
                  </div>

                  <?php if(\App\Support\ConfiguracionEmpresa::usaConsignados()): ?>
                  <div class="col-md-6 mb-2">
                    <label class="form-label text-primary" style="font-weight: bold; font-size: 0.85rem;">¿TIENE PRODUCTOS CONSIGNADOS?</label>

                    <div class="form-check mt-1">
                      <input type="hidden" name="es_consignado" value="0">
                      <input class="form-check-input" id="es_consignado_master" name="es_consignado"
                            type="checkbox" value="1" <?php echo e(($guia->es_consignado ?? 0) == 1 ? 'checked' : ''); ?> />
                      <label class="form-check-label" for="es_consignado_master">Productos Consignados</label>
                    </div>
                  </div>
                  <?php endif; ?>
              </div>

              <div class="row">
                <div class="col-md-7 mb-2">
                  <label class="form-label">Relacionar Documento</label>
                  <div class="row">
                    <div class="col-md-4">
                      <div class="form-check">
                        <input class="form-check-input radio_relacion_doc" type="radio" name="relacion_pedido" id="pedido"
                          value="1" <?php echo e((($guia->relacion_pedido ?? '') == 1) ? 'checked' : '' ); ?>>
                        <label class="form-check-label" for="pedido">
                          Pedido
                        </label>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input radio_relacion_doc" type="radio" name="relacion_pedido" id="recepcion"
                          value="2" <?php echo e((($guia->relacion_pedido ?? 2) == 2) ? 'checked' : '' ); ?>>
                        <label class="form-check-label" for="recepcion" >
                          Recepcion
                        </label>
                      </div>
                    </div>
                    <div class="col-md-8">
						
						<div class="row">
							<div class="col-md-6">
								<input 
									type="text" 
									class="form-control" 
									name="pedido_serie" 
									id="pedido_serie" 
									placeholder="Serie" 
									value="<?php echo e($guia->pedido_serie ?? ''); ?>"
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
									value="<?php echo e($guia->pedido_numero ?? ''); ?>"
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
                        <?php if(count($listProveedores) > 0): ?>
                          <?php $__currentLoopData = $listProveedores; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($item->codProveedor); ?>" data-proveedor_nombre="<?php echo e($item->nombreproveedor); ?>"
                              data-proveedor_ruc="<?php echo e($item->ruc); ?>" selected="selected">
                              <?php echo e("[$item->ruc] $item->nombreproveedor"); ?>

                            </option>
                          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            
                        <?php endif; ?>
                      </select>
                      <input type="hidden" id="proveedor_nombre" value="<?php echo e($listProveedores[0]->nombreproveedor ?? ''); ?>">
                      <input type="hidden" id="proveedor_ruc" value="<?php echo e($listProveedores[0]->ruc ?? ''); ?>">
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
                    <?php $__currentLoopData = $listFormasPago; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                      <option value="<?php echo e($item->codFormaPago); ?>" data-nombre="<?php echo e($item->descripcion); ?>">
                        <?php echo e($item->descripcion); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                  </select>
                </div>
                <div class="col-md-5">
                  <label class="form-label">Tipo Operacion</label>
                  <select class="form-select" name="tipo_operacion_id" id="tipo_operacion_id">
                    <?php $__currentLoopData = $listTipoOperacion; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                      <?php if($item->ingresoSalida == 'Ingreso'): ?>
                        <option value="<?php echo e($item->tipoOperacion); ?>" data-nombre="<?php echo e($item->descripcion); ?>" <?php echo e($item->selected ?? ''); ?>>
                          <?php echo e($item->descripcion); ?></option>
                      <?php endif; ?>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Almacen</label>
                  <select class="form-select" name="codalmacen" id="codalmacen">
                    <?php $__currentLoopData = $listAlmacenes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                      <option value="<?php echo e($item->codAlmacen); ?>" data-nombre="<?php echo e($item->descripcion); ?>"
                        data-codestacion="<?php echo e($item->codEstacion); ?>" <?php echo e($item->selected ?? ''); ?>><?php echo e($item->descripcion); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                  </select>
                </div>
              </div>

            </div>

          </div>

        </form>

        
        <div x-data="greDetalleGuia({
                lineas: <?php echo e(Js::from($lineasDetalle ?? [])); ?>,
                tasaIgv: <?php echo e(config('gre.igv.tasa', 0.18)); ?>,
                rutas: {
                    agregarItem:    '<?php echo e(route('guiaingreso.agregarItem')); ?>',
                    cargarOtraGuia: '<?php echo e(route('guiaingreso.cargarOtraGuia')); ?>'
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
            <div class="gre-buscador mb-3">
            <label class="form-label" for="producto_valor">
              Buscar artículo
              <span class="gre-atajo">escanee el código de barras o escriba el nombre · <kbd>Enter</kbd> para agregar</span>
            </label>

            <form name="form_buscar_articulo" id="form_buscar_articulo">
              <?php echo csrf_field(); ?>
              <div class="row">
                <div class="col-md-2">
                  <select class="form-select" id="tipo_busqueda_articulo">
                    <option value="1">Codigo Barras</option>
                    <option value="2">Codigo Articulo</option>
                    <option value="3">Codigo Interno</option>
                    <option value="4">Descripcion</option>
                  </select>
                </div>
                <div class="col-md-8 mb-2" id="div_form_buscar_articulo">
                  
                </div>
                <div class="col-md-2">
                  
                </div>
              </div>

            </form>
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
              <input type="hidden" id="producto_costo_articulo" name="producto_costo_articulo">
              <input type="hidden" id="producto_tipo_igv" name="producto_tipo_igv">



            </div>

            <div class="row mt-2">
              <div class="col-md-12 table-responsive">
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
                    <textarea class="form-control" name="comentario" id="comentario" rows="2"><?php echo e($guia->comentario ?? ''); ?></textarea>

                  </div>
                </div>
              </div>
              <div class="col-md-4">
                
                <div class="gre-totales">
                  <div class="row g-2">
                    <div class="col-3">
                      <label class="form-label" for="monto_descuento">Descuento</label>
                      <input class="form-control form-control-sm" type="text" id="monto_descuento" readonly :value="money(montoDescuento)">
                    </div>
                    <div class="col-3">
                      <label class="form-label" for="importe_sin_igv">Valor venta</label>
                      <input class="form-control form-control-sm" type="text" id="importe_sin_igv" readonly :value="money(valorVenta)">
                    </div>
                    <div class="col-3">
                      <label class="form-label" for="monto_igv">
                        IGV <span x-text="'(' + (tasaIgv * 100).toFixed(0) + '%)'"></span>
                      </label>
                      <input class="form-control form-control-sm" type="text" id="monto_igv" readonly :value="money(montoIgv)">
                    </div>
                    <div class="col-3 gre-total-final">
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
                  
                  <div class="col-md-3">
                    <label class="form-label">Base Calculo</label>
                    <select class="form-select" name="base_calculo" id="base_calculo" x-model.number="baseCalculo">
                      <option value="2" <?php echo e((($guia->base_calculo ?? '') == 2) ? 'selected' : '' ); ?>>Con IGV</option>
                      <option value="1" <?php echo e((($guia->base_calculo ?? '') == 1) ? 'selected' : '' ); ?>>Sin IGV</option>
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
            <a type="button" href="<?php echo e(route('guiaingreso.index')); ?>" class="btn btn-danger float-start"><i
                class="fa fa-arrow-left" aria-hidden="true"></i>
              Cancelar</a>
            <button type="submit" form="form_store" class="btn btn-primary float-end" ><i class="fa fa-save" aria-hidden="true"></i>
              Guardar</button>

            <!-- Example split danger button -->
            

          </div>
        </div>
      </div>
    </div>

    </div>
    <div id="modales"></div>
  </div>

  <?php $__env->startPush('js-scripts'); ?>
    
    <script defer src="<?php echo e(asset('js/vendor/alpine.min.js')); ?>"></script>
    <script src="<?php echo e(asset('js/gre/http.js?v=')); ?><?php echo e(rand()); ?>"></script>
    <script src="<?php echo e(asset('js/gre/guia-detalle.js?v=')); ?><?php echo e(rand()); ?>"></script>
    <script src="<?php echo e(asset('js/guias/ingreso/create.js?v=')); ?><?php echo e(rand()); ?>"></script>
    <script src="<?php echo e(asset('js/guias/ingreso/articulo.js?v=')); ?><?php echo e(rand()); ?>"></script>
    <script src="<?php echo e(asset('js/guias/ingreso/storage.js?v=')); ?><?php echo e(rand()); ?>"></script>
    <script src="<?php echo e(asset('js/guias/ingreso/cargar_de_guias.js?v=')); ?><?php echo e(rand()); ?>"></script>

  <?php $__env->stopPush(); ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/jesus/DataBussines/guias-electronicas-unificado/resources/views/guia/ingreso/create.blade.php ENDPATH**/ ?>