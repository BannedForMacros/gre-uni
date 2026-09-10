$(document).on('change', '#tipo_busqueda_articulo', function (event) {
  event.preventDefault();
  callFormBusquedaArticulo();
});


var callFormBusquedaArticulo = () => {
  var tipo_busqueda_articulo = $('#tipo_busqueda_articulo').val();

  var formData = new FormData();
  formData.append('_token', _token);
  formData.append('tipo_busqueda_articulo', tipo_busqueda_articulo);

  formBusquedaArticulo(formData);
}


var formBusquedaArticulo = function (formData) {
  var options = {
    type: 'POST',
    url: route('guiasalida.formBusquedaArticulo'),
    data: formData,
    processData: false,
    contentType: false,
    dataType: 'json',
    success: function (response) {
      $('#div_form_buscar_articulo').html(response.form);
      if (response.callSelect == true) {
        callListarArticulos();
        setTimeout(() => {
          $('#producto_id').focus();
        }, 200);
      } else {
        setTimeout(() => {
          $('#producto_valor').focus();
        }, 200);
      }
    }
  };
  $.ajax(options);
};

$(document).on('submit', '#form_buscar_articulo', function (event) {
  event.preventDefault();
  var formElement = document.getElementById("form_buscar_articulo");
  var formData = new FormData(formElement);
  var codalmacen = $('#codalmacen').val();
  var codlistaprecio = 1;
  var codestacion = 1;
  var tipo = $('#tipo_busqueda_articulo').val();
  formData.append('codalmacen', codalmacen);
  formData.append('codlistaprecio', codlistaprecio);
  formData.append('codestacion', codestacion);
  formData.append('tipo', tipo);
  buscarArticuloBarra(formData);
});


var buscarArticuloBarra = function (formData) {
  var options = {
    type: 'POST',
    url: route('guiaingreso.buscarArticuloBarra'),
    data: formData,
    processData: false,
    contentType: false,
    dataType: 'json',
    success: function (response) {

      if (response.procede == true) {
        var data = response.getArticulo
        
        $('#producto_id').val(data.codArticulo);
        $('#producto_codigo_barra').val(data.codBarra);
        $('#producto_descripcion').val(data.nombreArticulo);
        $('#producto_precio_publico').val(data.precioPublico);
        $('#producto_precio_sin_igv').val(data.precioSinIGV);
        $('#producto_cod_unidad').val(data.codUnidad);
        $('#producto_desc_unidad_medida').val(data.descUnidadMedida);
        $('#producto_sigla_umfe').val(data.siglaUMFE);
        $('#producto_peso').val(data.peso);
        $('#producto_costo_articulo').val(data.costo_articulo);
        $('#producto_afecto').val(data.afecto);
        $('#producto_tipo_igv').val(data.tipo_igv);


        callAgregarItem();
  
        setTimeout(() => {
          $('#producto_valor').val('')
        }, 100);
        
      }

      if (response.procede == false) {
        Swal.fire({
          title: '',
          html: response.msj,
          icon: response.msj_tipo,
          allowOutsideClick : false
        })
      }

    }
  };
  $.ajax(options);
};


$(document).on('change', '#producto_select', function(event) {
  event.preventDefault();
  var data = $("#producto_select").select2('data')[0];
  

  $('#producto_id').val(data.id);
  $('#producto_codigo_barra').val(data.codigo_barra);
  $('#producto_descripcion').val(data.descripcion);
  $('#producto_precio_publico').val(data.precio_publico);
  $('#producto_precio_sin_igv').val(data.precio_sin_igv);
  $('#producto_peso').val(data.peso);
  $('#producto_cod_unidad').val(data.cod_unidad);
  $('#producto_desc_unidad_medida').val(data.desc_unidad_medida);
  $('#producto_sigla_umfe').val(data.sigla_umfe);
  $('#producto_costo_articulo').val(data.costo_articulo);
  $('#producto_tipo_igv').val(data.tipo_igv);


  callAgregarItem();
});

var callAgregarItem = () => {
  var cantidad = $('#cantidad').val();
  var producto_id = $('#producto_id').val();
  var codigo_barra = $('#producto_codigo_barra').val();
  var cod_plu = $('#producto_id').val();
  var descripcion = $('#producto_descripcion').val();
  var cod_unidad = $('#producto_cod_unidad').val();
  var desc_unidad_medida = $('#producto_desc_unidad_medida').val();
  var sigla_umfe = $('#producto_sigla_umfe').val();
  
  // PRECIOS
  var precio_publico = $('#producto_precio_publico').val();
  var precio_sin_igv = $('#producto_precio_sin_igv').val();
  var costo_articulo = $('#producto_costo_articulo').val();
  var peso = $('#producto_peso').val();

  var base_calculo = $('#base_calculo').val();
  var tipo_igv = $('#producto_tipo_igv').val() || 0;
  var tipo_busqueda_articulo = $('#tipo_busqueda_articulo').val();

  var items = $('#tbody tr').map(function (i, row) {
    return {
      'producto_id': $(this).data('producto_id'),
    };
  }).get();

  var formData = new FormData();
  formData.append('_token', _token);
  formData.append('producto_id', producto_id);
  formData.append('codigo_barra', codigo_barra);
  formData.append('cod_plu', cod_plu);
  formData.append('descripcion', descripcion);
  formData.append('cod_unidad', cod_unidad);
  formData.append('desc_unidad_medida', desc_unidad_medida);
  formData.append('sigla_umfe', sigla_umfe);
  formData.append('precio_publico', precio_publico);
  formData.append('precio_sin_igv', precio_sin_igv);
  formData.append('peso', peso);
  formData.append('tipo_igv', tipo_igv);

  
  // --- INICIO DEBUG LOGICA DE PRECIOS ---
  
  // LÓGICA: Si hay costo mayor a 0, usar costo. Si no, usar precio público.
  var precio_visual = (costo_articulo && parseFloat(costo_articulo) > 0) ? costo_articulo : precio_publico;
  

  formData.append('costo_articulo', costo_articulo);
  formData.append('precio_visual', precio_visual); // <--- ESTO ES LO QUE DEBE RECIBIR PHP
  // --- FIN DEBUG ---

  formData.append('cantidad', cantidad);
  formData.append('base_calculo', base_calculo);
  formData.append('tipo_busqueda_articulo', tipo_busqueda_articulo);

  formData.append('items', JSON.stringify(items));
  
  if (formData.get('producto_id') != '') {
    agregarItem(formData);
    limpiarFormArticulo();
  }
}

var agregarItem = function(formData){
  var options = {
    type: 'POST',
    url: route('guiaingreso.agregarItem'),
    data:formData,
    processData: false,
    contentType: false,
    dataType: 'json',
    success: function(response){
      

      if (response.procede == true) {
        $('#tbody').append(response.tr);
        $('#base_calculo').trigger('change');

      }

      if (response.procede == false) {
        Swal.fire({
          title: '',
          html: response.msj,
          icon: response.msj_tipo,
          allowOutsideClick : false
        })
      }
      updateLocalStorage();
      
      calcularTotales();

      asignarFocusCantidad(formData.get('producto_id'));
      if (formData.get('tipo_busqueda_articulo') != 1) {
        $('#producto_select').html('');
      }
    }
  };
  $.ajax(options);
};


var limpiarFormArticulo = () => {
  $('#producto_id').val('');
  $('#producto_codigo_barra').val('');
  $('#producto_descripcion').val('');
  $('#producto_precio_publico').val('');
  $('#producto_precio_sin_igv').val('');
  // Limpiamos también el costo para evitar residuos
  $('#producto_costo_articulo').val('');
}

var asignarFocusCantidad = (codigo_producto) => {
  $('#tbody tr').map(function(i, row) {
    var id = $(this).data('producto_id');
    if (id == codigo_producto) {
      $(this).find('input[name=cantidad]').focus();
    }
  })
}