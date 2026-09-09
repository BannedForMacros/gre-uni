$(document).ready(function () {
  setTimeout(() => {
    // updateLocalStorage();
    verificarStorage();
  }, 500);
});

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

  var motivo_traslado_id = $('#motivo_traslado_id').val();
  var descripcion_motivo_traslado = $('#motivo_traslado_id').find(':selected').text();
  if (motivo_traslado_id == '-1') {
    var descripcion_motivo_traslado = '';
  }
  var base_calculo = $('#base_calculo').val();
  formData.append('base_calculo', base_calculo);

  var peso_bruto_total = $('#peso_bruto_total').val();
  formData.append('peso_bruto_total', peso_bruto_total);
  // formData.append('descripcion_motivo_traslado', descripcion_motivo_traslado);

  var indicar_proveedor = $('#indicar_proveedor').prop('checked');
  formData.append('indicar_proveedor', indicar_proveedor);

  // new Response(formData).text().then(console.log)

  // console.log({storageGuiaSalida});
  var items = $('#tbody tr').map(function (i, row) {
    return {
      'codarticulo': $(this).data('producto_id'),
      // 'codigo_producto' : $(this).find('input[name=item]').val(),
      'precio': $(this).find('span[name=span_precio]').text(),
      'cantidad': $(this).find('input[name=cantidad]').val(),
      'importe': $(this).find('span[name=span_importe]').text(),
      'importe_sin_igv': $(this).find('span[name=span_importe_sin_igv]').text(),
      'porcentaje_descuento': $(this).find('input[name=porcentaje_descuento]').val(),
      'monto_descuento': $(this).find('input[name=monto_descuento]').val(),
      'descripcion': $(this).data('descripcion'),
      'codigo': $(this).data('codigo'),
      'precio_publico': $(this).data('precio_publico'),
      'precio_sin_igv': $(this).data('precio_sin_igv'),
      'codigo_barra': $(this).data('codigo_barra'),
      'peso' : $(this).data('peso'),
      'stock' : $(this).data('stock'),
      'costo_articulo' : $(this).data('costo_articulo'),
      'cod_unidad' : $(this).data('cod_unidad'),
      'desc_unidad_medida' : $(this).data('desc_unidad_medida'),
      'sigla_umfe' : $(this).data('sigla_umfe'),
      'afecto' : $(this).data('afecto'),
    };
  }).get();

  formData.append('detalle', JSON.stringify(items));

  var comentario = $('#comentario').val();
  formData.append('comentario', comentario);
  
  
  // var departamento_options = $('#partida_departamento').text();
  // console.log({departamento_options});
  
  var ubigeos = getUbigeosStorage();
  formData.append('ubigeos', JSON.stringify(ubigeos))
  // console.log({ubigeos});
  
  // console.log(partida_departamento);
  
  if (formData.get('save_local_storage') == 'true') {
    var storageGuiaSalida = {};
    formData.forEach((valor, clave) => {
      storageGuiaSalida[clave] = valor.trim();
    });

    localStorage.setItem('storageGuiaSalida', JSON.stringify(storageGuiaSalida));

  }

}

var getUbigeosStorage = () => {

  const partida_departamento = JSON.stringify($('#partida_departamento option').map(function() {
    var selected = '';
    if ($('#partida_departamento').val() == this.value) {
      var selected = 'selected';
      
    }
    // return { [this.value]: this.text };
    return {
      'id' : this.value,
      'name' : this.text,
      'selected' : selected
    }
  }).get());
  
  const partida_provincia = JSON.stringify($('#partida_provincia option').map(function() {
    var selected = '';
    if ($('#partida_provincia').val() == this.value) {
      var selected = 'selected';
      
    }
    // return { [this.value]: this.text };
    return {
      'id' : this.value,
      'name' : this.text,
      'selected' : selected
    }
  }).get());
  
  const partida_distrito = JSON.stringify($('#partida_distrito option').map(function() {
    var selected = '';
    if ($('#partida_distrito').val() == this.value) {
      var selected = 'selected';
      
    }
    // return { [this.value]: this.text };
    return {
      'id' : this.value,
      'name' : this.text,
      'selected' : selected
    }
  }).get());

  // destino

  const llegada_departamento = JSON.stringify($('#llegada_departamento option').map(function() {
    var selected = '';
    if ($('#llegada_departamento').val() == this.value) {
      var selected = 'selected';
      
    }
    // return { [this.value]: this.text };
    return {
      'id' : this.value,
      'name' : this.text,
      'selected' : selected
    }
  }).get());
  
  const llegada_provincia = JSON.stringify($('#llegada_provincia option').map(function() {
    var selected = '';
    if ($('#llegada_provincia').val() == this.value) {
      var selected = 'selected';
      
    }
    // return { [this.value]: this.text };
    return {
      'id' : this.value,
      'name' : this.text,
      'selected' : selected
    }
  }).get());
  
  const llegada_distrito = JSON.stringify($('#llegada_distrito option').map(function() {
    var selected = '';
    if ($('#llegada_distrito').val() == this.value) {
      var selected = 'selected';
      
    }
    // return { [this.value]: this.text };
    return {
      'id' : this.value,
      'name' : this.text,
      'selected' : selected
    }
  }).get());

  return {partida_departamento, partida_provincia, partida_distrito, llegada_departamento, llegada_provincia, llegada_distrito}
}

var verificarStorage = () => {

  var storage = localStorage.getItem('storageGuiaSalida');
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
        // console.log(localStorage.getItem('storageGuiaSalida'));
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

var cargarStorage = () => {

  var storage = JSON.parse(localStorage.getItem('storageGuiaSalida'));
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
  if (storage.vendedor_id != '') {
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

  callGetModalidadTraslado();

  // tipo operacion
  $('#tipo_operacion_id').val(storage.tipo_operacion_id);
  // motivo traslado
  // $('#motivo_traslado_id').val(storage.motivo_traslado_id)
  $('#motivo_traslado_id').html(`<option value="${storage.motivo_traslado_id}">${storage.descripcion_motivo_traslado}</option>`)
  $('#modalidad_traslado').val(storage.modalidad_traslado);
  callSetMotivoTraslado();

  // almacenes
  $('#codalmacen').val(storage.codalmacen)
  $('#cod_almacen_origen').val(storage.cod_almacen_origen)
  $('#cod_almacen_destino').val(storage.cod_almacen_destino)

  // ubigeos

  if (storage.indicar_proveedor == 'true') {
    // console.log('indicamos proveedor');
  $('#indicar_proveedor').prop('checked', true)

    callIndicarProveedor();
  }


  asignarUbigeosStorage(storage.ubigeos);


  setTimeout(() => {
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
          data-stock = "${element.stock}"
          data-afecto = "${ (element.afecto == 0) ? 0 : 1 }"
          >
          <td class="align-middle">${element.codigo_barra}</td>
          <td class="align-middle">${element.codigo}</td>
          <td class="align-middle">${element.codarticulo}</td>
          <td class="align-middle">${element.descripcion}</td>
          <td class="align-middle">
            <span name="span_precio">${element.precio_publico}</span>
            <span name='span_precio_sin_igv' hidden>${element.precio_sin_igv}</span>
          </td>
          <td class="align-middle"><input type="number" class="form-control form-control-sm input_cantidad_tr" name="cantidad" value="${element.cantidad}"></td>
          <td class="align-middle">${element.desc_unidad_medida}</td>
          <td class="align-middle">${element.stock}</td>
          <td class="align-middle">
            <span name="span_importe">${importe}</span>
            <span name='span_importe_sin_igv' hidden>${element.importe_sin_igv}</span>
          </td>
          <td class="align-middle">
            <input class="form-control form-control-sm input_porcentaje_descuento_tr" name="porcentaje_descuento" value="${element.porcentaje_descuento}"> <input type="hidden" name="monto_descuento" value="${element.monto_descuento}">
          </td>
          <td hidden>${element.costo_articulo}</td>
          <td class="align-middle text-center">
              <button class="btn btn-danger btn-sm delete_item"><i class="fa fa-times-circle"></i></button>
          </td>
        </tr>
      `);
    });
    
  }, 300);



  $('#comentario').val(storage.comentario);
  $('#base_calculo').val(storage.base_calculo);
  $('#peso_bruto_total').val(storage.peso_bruto_total);
  

  $('#save_local_storage').val('true');

  setTimeout(() => {
    // calcularTotales();
    callBaseCalculo();
  }, 300);

}

var asignarUbigeosStorage = (ubigeos) => {
  var ubigeos_storage = JSON.parse(ubigeos);
  
  var llegada_departamento = JSON.parse(ubigeos_storage.llegada_departamento);
  
  var llegada_departamento_options = ``;
  $.map(llegada_departamento, function (element, index) {
    llegada_departamento_options += `<option value='${element.id}' ${element.selected} >${element.name}</option>`
  });

  $('#llegada_departamento').html(llegada_departamento_options);
  
  var llegada_provincia = JSON.parse(ubigeos_storage.llegada_provincia);
  console.log({llegada_provincia});
  
  var llegada_provincia_options = ``;
  $.map(llegada_provincia, function (element, index) {
    llegada_provincia_options += `<option value='${element.id}' ${element.selected} >${element.name}</option>`
  });

  $('#llegada_provincia').html(llegada_provincia_options);
  
  var llegada_distrito = JSON.parse(ubigeos_storage.llegada_distrito);
  
  var llegada_distrito_options = ``;
  $.map(llegada_distrito, function (element, index) {
    llegada_distrito_options += `<option value='${element.id}' ${element.selected} >${element.name}</option>`
  });

  $('#llegada_distrito').html(llegada_distrito_options);
  
  var partida_departamento = JSON.parse(ubigeos_storage.partida_departamento);
  
  var partida_departamento_options = ``;
  $.map(partida_departamento, function (element, index) {
    partida_departamento_options += `<option value='${element.id}' ${element.selected} >${element.name}</option>`
  });

  $('#partida_departamento').html(partida_departamento_options);
  
  var partida_provincia = JSON.parse(ubigeos_storage.partida_provincia);
  console.log({partida_provincia});

  var partida_provincia_options = ``;
  $.map(partida_provincia, function (element, index) {
    partida_provincia_options += `<option value='${element.id}' ${element.selected} >${element.name}</option>`
  });

  $('#partida_provincia').html(partida_provincia_options);
  
  var partida_distrito = JSON.parse(ubigeos_storage.partida_distrito);
  
  var partida_distrito_options = ``;
  $.map(partida_distrito, function (element, index) {
    partida_distrito_options += `<option value='${element.id}' ${element.selected} >${element.name}</option>`
  });

  $('#partida_distrito').html(partida_distrito_options);


}