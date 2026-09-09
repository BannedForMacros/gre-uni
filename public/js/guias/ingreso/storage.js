$(document).ready(function () {
  setTimeout(() => {
    verificarStorage();
  }, 500);
});


var verificarStorage = () => {

  var storage = localStorage.getItem('storageGuiaIngreso');
  // console.log({storage});

  if (storage != null) {
    Swal.fire({
      html: `<b>Se detecto datos sin grabar ¿Desea cargarlos?</b> <br><code>Si seleccionma NO, seran borrados</code>`,
      icon: "warning",
      showCancelButton: !0,
      confirmButtonText: "Si, Cargar",
      cancelButtonText: "No, descartar!",
      allowOutsideClick: false
      // reverseButtons: !0
    }).then((result) => {
      console.log(result);
      if (result.isConfirmed) {
        // console.log(localStorage.getItem('storageGuiaIngreso'));
        // console.log('cargamos el storage en form');
        cargarStorage();
      }
      if (result.isDismissed) {
        $('#save_local_storage').val('true')
        updateLocalStorage();
      }
    })
  } else {
    $('#save_local_storage').val('true')
    updateLocalStorage();
  }

}

var updateLocalStorage = () => {

  var formElement = document.getElementById("form_store");
  var formData = new FormData(formElement);
  
  var vendedor_nombre = $('#vendedor_id').find(':selected').data('vendedor_nombre');
  console.log({vendedor_nombre});
  if (vendedor_nombre == undefined) {
    vendedor_nombre = '';
  }
  formData.append('vendedor_nombre', vendedor_nombre);

  var tipo_operacion_nombre = $('#tipo_operacion_id').find(':selected').data('nombre');
  formData.append('tipo_operacion_nombre', tipo_operacion_nombre);

  var data_proveedor = $('#proveedor_id').select2('data')[0];
  var data_proveedor_2 = $('#proveedor_id').data();

  console.log({data_proveedor, data_proveedor_2});
  if (data_proveedor != null) {
    
    var proveedor_nombre = data_proveedor.proveedor_nombre;
    formData.append('proveedor_nombre', $('#proveedor_nombre').val());
    var proveedor_ruc = data_proveedor.proveedor_ruc;
    formData.append('proveedor_ruc', $('#proveedor_ruc').val());
  }



  
  var base_calculo = $('#base_calculo').val();
  formData.append('base_calculo', base_calculo);

  var monto_descuento = $('#monto_descuento').val();
  var importe_sin_igv = $('#importe_sin_igv').val();
  var monto_igv = $('#monto_igv').val();
  var total_venta = $('#total_venta').val();

  formData.append('monto_descuento', monto_descuento);
  formData.append('importe_sin_igv', importe_sin_igv);
  formData.append('monto_igv', monto_igv);
  formData.append('total_venta', total_venta);

  // console.log({storageGuiaIngreso});
  var items = $('#tbody tr').map(function (i, row) {
    return {
      'codarticulo': $(this).data('producto_id'),
      // 'codigo_producto' : $(this).find('input[name=item]').val(),
      'precio': $(this).find('span[name=span_precio]').text(),
      'cantidad': $(this).find('input[name=cantidad]').val(),
      'importe': $(this).find('span[name=span_importe]').text(),
      'porcentaje_descuento': $(this).find('input[name=porcentaje_descuento]').val(),
      'monto_descuento': $(this).find('input[name=monto_descuento]').val(),
      'descripcion': $(this).data('descripcion'),
      'codigo': $(this).data('codigo'),
      'precio_publico': $(this).data('precio_publico'),
      'precio_sin_igv': $(this).data('precio_sin_igv'),
      'codigo_barra': $(this).data('codigo_barra'),
      'peso' : $(this).data('peso'),
      'costo_articulo' : $(this).data('costo_articulo'),
      'cod_unidad' : $(this).data('cod_unidad'),
      'desc_unidad_medida' : $(this).data('desc_unidad_medida'),
      'sigla_umfe' : $(this).data('sigla_umfe'),
    };
  }).get();

  formData.append('detalle', JSON.stringify(items));

  var comentario = $('#comentario').val();
  formData.append('comentario', comentario);

  if (formData.get('save_local_storage') == 'true') {
    var storageGuiaIngreso = {};
    formData.forEach((valor, clave) => {
      storageGuiaIngreso[clave] = valor.trim();
    });

    localStorage.setItem('storageGuiaIngreso', JSON.stringify(storageGuiaIngreso));

  }

}


var cargarStorage = () => {

  var storage = JSON.parse(localStorage.getItem('storageGuiaIngreso'));
  console.log({storage});
  var detalle = JSON.parse(storage.detalle);
  console.log({ detalle });

  // serie
  $('#serie').val(storage.serie);
  callGetSerie();

  // pedido_serie; pedido_numero
  $('#pedido_serie').val(storage.pedido_serie)
  $('#pedido_numero').val(storage.pedido_numero)

  // vendedor
  if (storage.vendedor_nombre != '') {
    $('#vendedor_codigo').val(storage.vendedor_id);
    $('#vendedor_id').html(`<option value="${storage.vendedor_id}" data-vendedor_nombre="${storage.vendedor_nombre}">${storage.vendedor_nombre}</option>`);
    
  }

  // enviar sunat
  $('#envio-sunat').val(storage.envio_sunat);

  // pedido interno
  if (storage.pedido_interno =! null) {
    if (storage.pedido_interno == 1) {
      $('#pedido_interno').prop('checked', true)
    }
  }


  // transportista
  $('#transportista_id').html(`<option value='${storage.transportista_id}' >${storage.transportista_nombre}</option>`);
  $('#transportista_ruc').val(storage.transportista_ruc);
  $('#transportista_nombre').val(storage.transportista_nombre);
  $('#transportista_direccion').val(storage.transportista_direccion);

  // tipo operacion
  $('#tipo_operacion_id').val(storage.tipo_operacion_id);
  // motivo traslado
  // $('#motivo_traslado_id').val(storage.motivo_traslado_id)
  $('#motivo_traslado_id').html(`<option value="${storage.motivo_traslado_id}">${storage.descripcion_motivo_traslado}</option>`)
  // callSetMotivoTraslado();

  // almacenes
  $('#codalmacen').val(storage.codalmacen)
  $('#cod_almacen_origen').val(storage.cod_almacen_origen)
  $('#cod_almacen_destino').val(storage.cod_almacen_destino)

  $.map(detalle, function (element, index) {
    console.log(element);
    var importe = round((element.cantidad * element.precio_publico),2);
    $('#tbody').prepend(`
      <tr 
        data-producto_id="${element.codigo}" 
        data-precio_unitario="${element.precio_publico}" 
        data-precio_publico="${element.precio_publico}" 
        data-precio_sin_igv="${element.precio_sin_igv}" 
        data-descripcion="${element.descripcion}" 
        data-codigo="${element.codarticulo}" 
        data-codigo_barra="${element.codigo_barra}" 
        data-peso="${element.peso}"
        data-costo_articulo="${element.costo_articulo}"
        data-cod_unidad="${element.cod_unidad}"
        data-desc_unidad_medida="${element.desc_unidad_medida}"
        data-sigla_umfe="${element.sigla_umfe}"
      >
        <td class="align-middle">${element.codigo_barra}</td>
        <td class="align-middle">${element.codigo}</td>
        <td class="align-middle">${element.codarticulo}</td>
        <td class="align-middle">${element.descripcion}</td>
        <td class="align-middle"><span name="span_precio">${element.precio_publico}</span></td>
        <td class="align-middle"><input type="number" class="form-control form-control-sm input_cantidad_tr" name="cantidad" value="${element.cantidad}"></td>
        <td class="align-middle">${element.desc_unidad_medida}</td>
        <td class="align-middle"><span name="span_importe">${importe}</span></td>
        <td class="align-middle">
          <input class="form-control form-control-sm input_porcentaje_descuento_tr" name="porcentaje_descuento" value="${element.porcentaje_descuento}"> <input type="hidden" name="monto_descuento" value="${element.monto_descuento}">
        </td>
        <td class="align-middle" style="text-align:center">
            <input class="bonificacion" type="checkbox" name="bonificacion">
        </td>
        <td class="align-middle text-center">
            <button class="btn btn-danger btn-sm delete_item"><i class="fa fa-times-circle"></i></button>
        </td>
      </tr>
    `);
  });


  $('#comentario').val(storage.comentario);
  $('#base_calculo').val(storage.base_calculo);
  $('#peso_bruto_total').val(storage.peso_bruto_total);
  

  $('#save_local_storage').val('true');

  setTimeout(() => {
    calcularTotales();
  }, 300);

}