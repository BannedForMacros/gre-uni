$(document).on('change', '#tipo_busqueda_articulo', function (event) {
  event.preventDefault();
  /* Act on the event */
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
  /* Act on the event */
  // console.log('hola');
  var formElement = document.getElementById("form_buscar_articulo");
  var formData = new FormData(formElement);
  var codalmacen = $('#codalmacen').val();
  var codlistaprecio = $('#codlistaprecio').val();
  var codestacion = $('#codlistaprecio').find(':selected').data('codestacion');
  var tipo = $('#tipo_busqueda_articulo').val();
  var indicar_proveedor = $('#indicar_proveedor').prop('checked');
  

  formData.append('codalmacen', codalmacen);
  formData.append('codlistaprecio', codlistaprecio);
  formData.append('codestacion', codestacion);
  formData.append('tipo', tipo);
  formData.append('indicar_proveedor', indicar_proveedor);


  buscarArticuloBarra(formData);
});

var buscarArticuloBarra = function (formData) {
  var options = {
    type: 'POST',
    url: route('guiasalida.buscarArticuloBarra'),
    data: formData,
    processData: false,
    contentType: false,
    dataType: 'json',
    success: function (response) {
      if (response.procede == true) {
        var data = response.getArticulo
        console.log({data});
        $('#producto_id').val(data.codArticulo);
        $('#producto_codigo_barra').val(data.codBarra);
        $('#producto_descripcion').val(data.nombreArticulo);
        $('#producto_precio_publico').val(data.precioPublico);
        $('#producto_precio_sin_igv').val(data.precioSinIGV);
        $('#producto_cod_unidad').val(data.codUnidad);
        $('#producto_desc_unidad_medida').val(data.descUnidadMedida);
        $('#producto_sigla_umfe').val(data.siglaUMFE);
        $('#producto_peso').val(data.peso);
        $('#producto_stock').val(data.stock);
        $('#producto_costo_articulo').val(data.costo_articulo);
        $('#producto_afecto').val(data.afecto);
        

  
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
  /* Act on the event */
  console.log('seleccion de producto');
  var data = $("#producto_select").select2('data')[0];
  console.log({data});
  $('#producto_id').val(data.id);
  $('#producto_codigo_barra').val(data.codigo_barra);
  $('#producto_descripcion').val(data.descripcion);
  $('#producto_precio_publico').val(data.precio_publico);
  $('#producto_precio_sin_igv').val(data.precio_sin_igv);
  $('#producto_peso').val(data.peso);
  $('#producto_cod_unidad').val(data.cod_unidad);
  $('#producto_desc_unidad_medida').val(data.desc_unidad_medida);
  $('#producto_sigla_umfe').val(data.sigla_umfe);
  $('#producto_stock').val(data.stock);
  $('#producto_costo_articulo').val(data.costo_articulo);
  $('#producto_afecto').val(data.afecto);


  callAgregarItem();
});

var callAgregarItem = () => {
  // var data = $("#producto_select").select2('data')[0];
  // console.log(data);//displays hello world
  var cantidad = $('#cantidad').val();
  var producto_id = $('#producto_id').val();
  // var codigo_barra = $('#producto_id').find(':selected').data('codigo_barra');
  // var codigo_barra = data.codigo_barra;
  var codigo_barra = $('#producto_codigo_barra').val();
  // console.log({codigo_barra});
  // var cod_plu = producto_id;
  var cod_plu = $('#producto_id').val();
  // var descripcion = data.descripcion;
  var descripcion = $('#producto_descripcion').val();
  var cod_unidad = $('#producto_cod_unidad').val();
  var desc_unidad_medida = $('#producto_desc_unidad_medida').val();
  var sigla_umfe = $('#producto_sigla_umfe').val();
  var stock = $('#producto_stock').val();
  var costo_articulo = $('#producto_costo_articulo').val();
  // var precio_publico = data.precio_publico;
  var precio_publico = $('#producto_precio_publico').val();
  // var precio_sin_igv = data.precio_sin_igv;
  var precio_sin_igv = $('#producto_precio_sin_igv').val();
  var peso = $('#producto_peso').val();
  var afecto = $('#producto_afecto').val();

  var base_calculo = $('#base_calculo').val();
  var tipo_busqueda_articulo = $('#tipo_busqueda_articulo').val();


  // console.log({producto_id});
  var items = $('#tbody tr').map(function (i, row) {
    return {
      'producto_id': $(this).data('producto_id'),
      // 'codigo_producto' : $(this).find('input[name=item]').val(),
      // 'item_seleccionado' : $(this).find('input[name=item]').prop('checked'),
      // 'acceso' : $(this).find('input[type=radio]:checked').val(),
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
  formData.append('stock', stock);
  formData.append('costo_articulo', costo_articulo);
  formData.append('cantidad', cantidad);
  formData.append('base_calculo', base_calculo);
  formData.append('tipo_busqueda_articulo', tipo_busqueda_articulo);
  formData.append('afecto', afecto);

  formData.append('items', JSON.stringify(items));
  if (formData.get('producto_id') != '') {
    agregarItem(formData);
  
    limpiarFormArticulo();
    
  }
}

var agregarItem = function(formData){
  var options = {
    type: 'POST',
    url: route('guiasalida.agregarItem'),
    data:formData,
    processData: false,
    contentType: false,
    dataType: 'json',
    success: function(response){

      if (response.procede == true) {
        $('#tbody').append(response.tr);
      }

      if (response.procede == false) {
        Swal.fire({
          title: '',
          html: response.msj,
          icon: response.msj_tipo,
          allowOutsideClick : false
        })
      }
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
}

var asignarFocusCantidad = (codigo_producto) => {
  console.log({codigo_producto});
  $('#tbody tr').map(function(i, row) {

    var id = $(this).data('producto_id');
    console.log({codigo_producto, id});

    if (id == codigo_producto) {
      console.log('iguales 🤗');
      $(this).find('input[name=cantidad]').focus();
    }

    // return {
    //   'codarticulo' : $(this).data('producto_id'),
    // };
  })

  // console.log({items});

}