$(document).ready(function () {

  setTimeout(() => {
    $('.select_2').select2({
      theme: "bootstrap-5",
      width: $(this).data('width') ? $(this).data('width') : $(this).hasClass('w-100') ? '100%' : 'style',
      placeholder: $(this).data('placeholder'),
    });

    callListarProveedores();
    callListarArticulos();
    callFormBusquedaArticulo();

    calcularTotales();
    $('#base_calculo').trigger('change');

  }, 300);
});

$(document).on('change', '#es_guia_interna', function(event) {
  event.preventDefault();
  /* Act on the event */
  callEsGuiaInterna();
});

var callEsGuiaInterna = () => {

  var es_guia_interna = $('#es_guia_interna').val();
  // console.log({es_guia_interna});

  if (es_guia_interna == 0) {//no es guia interna
    
    $('#div_serie_interna').hide();
    $('#div_serie_externa').show();

  }

  if (es_guia_interna == 1) {// es guia interna

    $('#div_serie_interna').show();
    $('#div_serie_externa').hide();

    callGetSerie();

  }

  updateLocalStorage();
}

$(document).on('change', '#serie', function(event) {
  event.preventDefault();
  /* Act on the event */
  callGetSerie();
});

var callGetSerie = () => {

  var serie = $('#serie').val();

  var formData = new FormData();
  formData.append('_token', _token);
  formData.append('serie', serie);

  getSerie(formData);
}

var getSerie = function(formData){
  var options = {
    type: 'POST',
    url: route('guiaingreso.getSerie'),
    data:formData,
    processData: false,
    contentType: false,
    dataType: 'json',
    success: function(response){
      console.log({response});
      var serie = response.getSerie;
      $('#numero').val(serie.nuevo_numero);
      updateLocalStorage();
    }
  };
  $.ajax(options);
};

var callListarArticulos = () => {


  $(`#producto_select`).select2({
    theme: "bootstrap-5",
    containerCssClass: "select2--small",
    dropdownCssClass: "select2--small",
    ajax: {
      url: route('guiaingreso.listarArticulos'),
      // type: 'POST',
      data: function (params) {
        var codalmacen = $('#codalmacen').val();
        var codlistaprecio = $('#codlistaprecio').val();
        var codestacion = $('#codlistaprecio').find(':selected').data('codestacion');
        var tipo = $('#tipo_busqueda_articulo').val();

        var query = {
          term: params.term,
          _token: _token,
          codalmacen: codalmacen,
          codlistaprecio: codlistaprecio,
          codestacion: codestacion,
          tipo: tipo,
        }
        return query;
      },
      dataType: 'json',
      delay: 250,
      processResults: function (data) {
        return {
          results : data.items
          // results: $.map(data.items, function (obj) {
            
          //   return { id: obj.id, text: obj.name,  };
          // })
        };
      },
      
    }
  });

}
$(document).on('click', '.delete_item', function(event) {
  event.preventDefault();
  /* Act on the event */

  $(this).parent().parent().remove();

});

var callListarProveedores = () => {

  $(`#proveedor_id`).select2({
    theme: "bootstrap-5",
    containerCssClass: "select2--small",
    dropdownCssClass: "select2--small",
    ajax: {
      url: route('guiaingreso.listarProveedores'),
      // type: 'POST',
      data: function (params) {
        var tipo = $('#tipo_busqueda_proveedor').val();
        var query = {
          term: params.term,
          tipo: tipo,
          _token: _token,
        }
        return query;
      },
      dataType: 'json',
      delay: 250,
      processResults: function (data) {
        // console.log(data.items);
        return {
          results : data.items
          // results: $.map(data.items, function (obj) {
            
          //   return { id: obj.id, text: obj.name,  };
          // })
        };
      },
      // Additional AJAX parameters go here; see the end of this chapter for the full code of this example
    }
  });

}

$(document).on('change', '#proveedor_id', function(event) {
  event.preventDefault();
  /* Act on the event */
  var data = $('#proveedor_id').select2('data')[0];
  console.log({data});

  $('#proveedor_nombre').val(data.proveedor_nombre);
  $('#proveedor_ruc').val(data.proveedor_ruc);

  updateLocalStorage();

});

$(document).on('click', '.delete_item', function(event) {
  event.preventDefault();
  /* Act on the event */

  $(this).parent().parent().remove();

  calcularTotales();

});

$(document).on('keyup', '.input_cantidad_tr', function(event) {
  event.preventDefault();
  /* Act on the event */

  var cantidad = $(this).val();
  if (cantidad == '') {
    cantidad = 0;
  }
  var precio = $(this).parent().parent().find('span[name=span_precio]').text();
  // var importe = parseInt(cantidad) * parseFloat(precio);
  var importe = round((parseInt(cantidad) * parseFloat(precio)),2);
  $(this).parent().parent().find('span[name=span_importe]').html(importe)
  
  setTimeout(() => {
    calcularTotales();
    
  }, 300);
});

$(document).on('keyup', '.input_porcentaje_descuento_tr', function(event) {
  event.preventDefault();
  /* Act on the event */
  var porcentaje_descuento = parseFloat($(this).val()) / 100;
  
  if ($(this).val() == '') {
    porcentaje_descuento = 0;
  }

  
  var cantidad = $(this).parent().parent().find('input[name=cantidad]').val();
  var precio = $(this).parent().parent().find('span[name=span_precio]').text();
  var importe = parseInt(cantidad) * parseFloat(precio);
  
  var monto_descuento = parseFloat(importe) * porcentaje_descuento;
  $(this).parent().parent().find('input[name=monto_descuento]').val(monto_descuento);
  var nuevo_importe = importe - monto_descuento;
  $(this).parent().parent().find('span[name=span_importe]').html(round(nuevo_importe,2))
  // console.log({porcentaje_descuento, cantidad, precio, importe, monto_descuento});

  setTimeout(() => {
    calcularTotales();
  }, 300);
});

var calcularTotales = () => {

  var base_calculo = $('#base_calculo').val(); // 1 sin igv, 2 con igv (solo visual)

  var items = $('#tbody tr').map(function(i, row) {
    var bonificacion = $(this).find('input[name=bonificacion]').prop('checked');
    if (bonificacion == false) {
      return {
        producto_id: $(this).data('producto_id'),
        cantidad: parseFloat($(this).find('input[name=cantidad]').val() || 0),

        importe_visual: parseFloat($(this).find('span[name=span_importe]').text() || 0),

        porcentaje_descuento: $(this).find('input[name=porcentaje_descuento]').val(),
        monto_descuento: parseFloat($(this).find('input[name=monto_descuento]').val() || 0),

        peso: parseFloat($(this).data('peso') || 0),

        tipo_igv: parseInt($(this).data('tipo_igv') || 0),
        costo_sin_igv: parseFloat($(this).data('costo_sin_igv') || 0),
      };
    }
  }).get();

  var total_items = items.length;

  var total_cantidad = 0;
  var monto_descuento = 0;
  var peso_total = 0;

  // ✅ Importante: mantener acumuladores como NUMBER (no string)
  var valor_venta_num = 0;   // base SIN IGV (number)
  var base_afecta_num = 0;   // solo afectos tipo_igv==1 (number)

  items.forEach(function (el, idx) {

    total_cantidad += el.cantidad;
    peso_total += (el.peso * el.cantidad);

    // round() devuelve STRING, por eso lo convertimos a number
    var importe_sin_igv_str = round(el.costo_sin_igv * el.cantidad, 2);
    var importe_sin_igv_num = parseFloat(importe_sin_igv_str) || 0;

    if (el.porcentaje_descuento != '') {
      monto_descuento += el.monto_descuento;

      // ojo: descuento también puede producir string si lo pasas por round
      importe_sin_igv_num = (importe_sin_igv_num - (parseFloat(el.monto_descuento) || 0));
      // si quieres “cortar” a 2 decimales sin romper tipos:
      importe_sin_igv_num = parseFloat(round(importe_sin_igv_num, 2)) || 0;
    }

    // ✅ sumas reales (number + number)
    valor_venta_num += importe_sin_igv_num;

    if (el.tipo_igv === 1) {
      base_afecta_num += importe_sin_igv_num;
    }
  });

  // ✅ Si quieres seguir usando round() para mostrar, conviertes a number primero:
  valor_venta_num = parseFloat(round(valor_venta_num, 2)) || 0;
  monto_descuento = parseFloat(round(monto_descuento, 2)) || 0;

  var monto_igv_num = parseFloat(round(base_afecta_num * 0.18, 2)) || 0;
  var total_venta_num = parseFloat(round(valor_venta_num + monto_igv_num, 2)) || 0;

  // ✅ set inputs cabecera (si quieres strings con 2 decimales para mostrar)
  $('#total_items').val(total_items);
  $('#total_cantidad').val(total_cantidad);

  $('#importe_sin_igv').val(valor_venta_num.toFixed(2));
  $('#monto_igv').val(monto_igv_num.toFixed(2));
  $('#total_venta').val(total_venta_num.toFixed(2));

  $('#monto_descuento').val(monto_descuento.toFixed(2));
  $('#peso_bruto_total').val((parseFloat(round(peso_total, 2)) || 0).toFixed(2));

  updateLocalStorage();
}




$(document).on('submit', '#form_store', function(event) {
  event.preventDefault();
  /* Act on the event */

  callStore();

});

var callStore = (guardar_avance = false) => {

  var formElement = document.getElementById("form_store");
  var formData = new FormData(formElement);

  // 1. CAPTURAR EL CHECKBOX (NUEVO)
  const esConsignadoMaster = $('#es_consignado_master').is(':checked') ? 1 : 0; // <--- NUEVO
  formData.append('es_consignado', esConsignadoMaster); // <--- NUEVO: Para asegurar que vaya en la cabecera también

  var items = $('#tbody tr').map(function(i, row) {
      var cantidad = parseFloat($(this).find('input[name=cantidad]').val() || 0);
      var costo_sin_igv = parseFloat($(this).data('costo_sin_igv') || 0);
      var tipo_igv = parseInt($(this).data('tipo_igv') || 0);

      // ✅ precio/importe que se GUARDAN SIEMPRE SIN IGV
      var precio_guardar = round(costo_sin_igv, 2);
      var importe_guardar = round(costo_sin_igv * cantidad, 2);
      return {
        codarticulo : $(this).data('producto_id'),
        precio : precio_guardar,
        cantidad : cantidad,
        importe : importe_guardar,

        porcentaje_descuento : $(this).find('input[name=porcentaje_descuento]').val(),
        monto_descuento : parseFloat($(this).find('input[name=monto_descuento]').val() || 0),

        descripcion : $(this).data('descripcion'),
        codigo : $(this).data('codigo'),
        precio_publico : $(this).data('precio_publico'),
        precio_sin_igv : $(this).data('precio_sin_igv'),
        codigo_barra : $(this).data('codigo_barra'),
        cod_unidad : $(this).data('cod_unidad'),
        desc_unidad_medida : $(this).data('desc_unidad_medida'),
        sigla_umfe : $(this).data('sigla_umfe'),

        costo_articulo : $(this).data('costo_articulo'),

        tipo_igv: tipo_igv, // ✅ opcional, por si luego lo guardas
        es_consignado: esConsignadoMaster
      };
    }).get();



  formData.append('detalle', JSON.stringify(items));

  // console.log({items});



  var codestacion = $('#codalmacen').find(':selected').data('codestacion');
  formData.append('codestacion', codestacion)

  var monto_descuento = $('#monto_descuento').val();
  var importe_sin_igv = $('#importe_sin_igv').val();
  var monto_igv = $('#monto_igv').val();
  var total_venta = $('#total_venta').val();
  var comentario = $('#comentario').val();
  var data_proveedor = $('#proveedor_id').select2('data')[0];
  var data_proveedor_2 = $('#proveedor_id').data();

  console.log({data_proveedor, data_proveedor_2});
  if (data_proveedor != null) {
    
    var proveedor_nombre = data_proveedor.proveedor_nombre;
    formData.append('proveedor_nombre', $('#proveedor_nombre').val());
    var proveedor_ruc = data_proveedor.proveedor_ruc;
    formData.append('proveedor_ruc', $('#proveedor_ruc').val());
  }
  // new Response(formData).text().then(console.log)

  var vendedor_nombre = $('#vendedor_id').find(':selected').data('vendedor_nombre');
  console.log({vendedor_nombre});
  if (vendedor_nombre == undefined) {
    vendedor_nombre = '';
  }
  formData.append('vendedor_nombre', vendedor_nombre);

  var divisa_nombre = $('#divisa_id').find(':selected').data('nombre');
  formData.append('divisa_nombre', divisa_nombre);

  var forma_pago_nombre = $('#forma_pago_id').find(':selected').data('nombre');
  formData.append('forma_pago_nombre', forma_pago_nombre);

  var tipo_operacion_nombre = $('#tipo_operacion_id').find(':selected').data('nombre');
  formData.append('tipo_operacion_nombre', tipo_operacion_nombre);

  var almacen_nombre = $('#codalmacen').find(':selected').data('nombre');
  formData.append('almacen_nombre', almacen_nombre);
  
  var base_calculo = $('#base_calculo').val();
  formData.append('base_calculo', base_calculo);
  
  formData.append('monto_descuento', monto_descuento);
  formData.append('importe_sin_igv', importe_sin_igv);
  formData.append('monto_igv', monto_igv);
  formData.append('total_venta', total_venta);
  formData.append('comentario', comentario);
  formData.append('guardar_avance', guardar_avance);

  new Response(formData).text().then(console.log)
  // store(formData);

  var procede_store = true;
  var msj_store = '';
  new Response(formData).text().then(console.log)
  console.log(formData.get('proveedor_nombre'));



  if (formData.get('guardar_avance') == 'false') {
    
    if (formData.get('vendedor_nombre') == '') {
      procede_store = false;
      msj_store = `Debe indicar un vendedor`;
    }

    if (procede_store == true) {
      
      if (formData.get('proveedor_nombre') == null) {
        procede_store = false;
        msj_store = 'Debe indicar un proveedor';
      }
    }
  
    if (procede_store == true) {
      if (items.length <= 0) {
        procede_store = false;
        msj_store = 'Debe indicar articulos en la guia';
      }
    }

    if (procede_store == true) {
      if (formData.get('es_guia_interna') == 0) {
        if (formData.get('serie_externa').length <= 0) {
          procede_store = false;
          msj_store = `<b>Debe ingresar una serie para la guia</b>`
        }
      }
    }
    if (procede_store == true) {
      if (formData.get('es_guia_interna') == 0) {
        if (formData.get('numero').length <= 0) {
          procede_store = false;
          msj_store = `<b>Debe ingresar un numero para la guia</b>`
        }
      }
    }

  }

  // validar negativos
  // console.log({items});

  $.map(items, function (element, index) {
    if (procede_store == true) {
      if (parseFloat(element.cantidad) < 0) {
        procede_store = false;
        msj_store = `<b>El item [${element.codarticulo}] ${element.descripcion} <br>tiene un valor negativo = ${element.cantidad}</b>`;
      }

      if (parseFloat(element.cantidad) == 0) {
        procede_store = false;
        msj_store = `<b>El item [${element.codarticulo}] ${element.descripcion} <br>tiene un cero = ${element.cantidad}</b>`;
      }
    }
  });

  // procede_store = false;



  if (procede_store == true) {
    var msj_guardado = `<b>¿Desea registrar esta Guia de Ingreso?</b>`;
    if (guardar_avance == true) {
      msj_guardado = `<b>¿Desea guardar el avance de esta Guia de Ingreso?</b>`;
      
    }
    Swal.fire({
      html: msj_guardado,
      icon: "warning",
      showCancelButton: !0,
      confirmButtonText: "Si, Registrar",
      cancelButtonText: "No, cancelar!",
  
      // reverseButtons: !0
    }).then((result) => {
      if (result.isConfirmed) {
        // store(formData);
        modalStore(formData);
      }
    })
    
  } else {
    Swal.fire({
      html: msj_store,
      icon: 'error'
    })
  }
}

var modalStore = function(formData){
  var options = {
    type: 'POST',
    url: route('guiaingreso.modalStore'),
    data:formData,
    processData: false,
    contentType: false,
    dataType: 'html',
    success: function(response){
      $('#modales').html(response);
      $('#modalStore').modal('show');
      store(formData);
    }
  };
  $.ajax(options);
};

var store = function(formData){
  var options = {
    type: 'POST',
    url: route('guiaingreso.store'),
    data:formData,
    processData: false,
    contentType: false,
    dataType: 'json',
    success: function(response){
      // Swal.fire({
      //   html: response.msj,
      //   icon: response.msj_tipo,
      // }).then((result) => {
      //   if (result) {
      //     if (response.procede == true) {
      //       window.location.href = response.url_redirect;
      //     }
      //   }
      // })

      $('#li_store').html(response.msj);

      if (response.procede == true) {
        formData.append('id', response.id);
        storeDataMart(formData);
        localStorage.removeItem('storageGuiaIngreso')
      }

    }
  };
  $.ajax(options);
};

var storeDataMart = function(formData){
  var options = {
    type: 'POST',
    url: route('guiaingreso.storeDataMart'),
    data:formData,
    processData: false,
    contentType: false,
    dataType: 'json',
    beforeSend: function(){
      // Pantalla de carga: avisa que se están colocando/actualizando los maestros de artículos
      Swal.fire({
        title: 'Registrando guía...',
        html: 'Registrando la guía en SQL Server.<br>Por favor espere.',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: function(){ Swal.showLoading(); }
      });
    },
    success: function(response){
      Swal.close();
      $('#li_store_datamart').html(response.msj);

      if (response.procede == true) {

        // Confirmación visible de cuántos maestros de artículos se actualizaron
        var consignados  = response.consignados_enviados || 0;
        var actualizados = (response.maestros_actualizados != null) ? response.maestros_actualizados : 0;
        if (consignados > 0) {
          Swal.fire({
            icon:  (actualizados > 0 ? 'success' : 'warning'),
            title: (actualizados > 0 ? 'Maestro de artículos actualizado' : 'Maestro NO actualizado'),
            html:  'Consignados enviados: <b>' + consignados + '</b><br>'
                 + 'Maestros actualizados: <b>' + actualizados + '</b>',
            timer: 3000,
            timerProgressBar: true,
            showConfirmButton: false
          });
        } else {
          Swal.fire({ icon: 'success', title: 'Guía registrada en DataMart', timer: 2000, showConfirmButton: false });
        }

        if (formData.get('guardar_avance') == 'false') {
          if (formData.get('envio_sunat') == 1) {
            if (formData.get('id') == '') {
              formData.append('id', response.id);
            }
            facturacionElectronica(formData);
          }
        }
      } else {
        Swal.fire({ icon: 'error', title: 'No se completó el registro', html: response.msj || 'Ocurrió un error.' });
      }

    },
    error: function(){
      Swal.close();
      Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo registrar en DataMart.' });
    }
  };
  $.ajax(options);
};

$(document).on('click', '#btnReintentarDataMart', function(event) {
  event.preventDefault();
  /* Act on the event */
  // var callGuardarAvance = $(this).data('guardar_avance');

  // $('#modalStore').modal('hide');

  // console.log({callGuardarAvance});
  // callStore();

  var formData = new FormData();
  var id = $(this).data('id');

  formData.append('_token', _token);
  formData.append('id', id);

  $('#li_store_datamart').html(`<b>Registrando en DataMart...</b>
  <span class=""><i class="fa-solid fa-spinner fa-spin fa-lg"></i></span>`);
  storeDataMart(formData);


});

$(document).on('change', '.bonificacion', function(event) {
  event.preventDefault();
  /* Act on the event */

  calcularTotales();

});

// Evento para actualizar el Local Storage cuando cambie el checkbox
$(document).on('change', '#es_consignado_master', function(event) {
    // No es necesario preventDefault en un checkbox cambio, pero si lo deseas mantener:
    // event.preventDefault(); 
    
    /* Act on the event */
    updateLocalStorage();
});

$(document).on('change', '#base_calculo', function (event) {
  event.preventDefault();

  var base_calculo = $(this).val(); // 2 con IGV, 1 sin IGV

  $('#tbody tr').each(function () {

    var cantidad = parseFloat($(this).find('input[name=cantidad]').val() || 0);

    var tipo_igv = parseInt($(this).data('tipo_igv') || 0);

    var costo_con_igv = parseFloat($(this).data('costo_con_igv') || 0);
    var costo_sin_igv = parseFloat($(this).data('costo_sin_igv') || 0);

    if (tipo_igv !== 1) {
      costo_con_igv = costo_sin_igv;
    }

    var precio_unitario = (base_calculo == 1) ? costo_sin_igv : costo_con_igv;

    $(this).find('span[name=span_precio]').html(precio_unitario);

    var importe = round((precio_unitario * cantidad), 2);
    $(this).find('span[name=span_importe]').html(importe);
  });

  calcularTotales();
});



$(document).on('click', '#btnGuardarAvance', function(event) {
  event.preventDefault();
  /* Act on the event */
  callStore(true);
});

$(document).on('keypress', '#vendedor_codigo', function(event) {
  // event.preventDefault();
  console.log('enter');
  /* Act on the event */
  console.log(event.keyCode);

});

$(document).on('click', '#btnBuscarVendedor', function(event) {
  event.preventDefault();
  /* Act on the event */
  callGetVendedor();

});

var callGetVendedor = () => {

  var vendedor_codigo = $('#vendedor_codigo').val();
  // console.log({vendedor_codigo});
  var formData = new FormData();
  formData.append('_token', _token);
  formData.append('vendedor_codigo', vendedor_codigo);

  getVendedor(formData);
}

var getVendedor = function(formData){
  var options = {
    type: 'POST',
    url: route('guiaingreso.getVendedor'),
    data:formData,
    processData: false,
    contentType: false,
    dataType: 'json',
    success: function(response){
      $('#vendedor_id').html(response.options);
      updateLocalStorage();
    }
  };
  $.ajax(options);
};

$('#form_store').on('keydown', function(e) {
  var keyCode = e.keyCode || e.which;
  var tag = e.target.tagName
  var tag_id = e.target.id;

  console.log({keyCode, tag});
  console.log(e.target.id);
  if (keyCode === 13 && tag_id !=="vendedor_codigo") {
    console.log("Enter prevented")
    e.preventDefault();
    return false;
  }else{
    // console.log("Enter is ok...")
    if (keyCode == 13) {
      callGetVendedor();
      
    }
  }
});

$(document).on('keypress', '.input_cantidad_tr', function(event) {
  // event.preventDefault();
  var keyCode = event.keyCode || event.which;
  var tipo_busqueda_articulo = $('#tipo_busqueda_articulo').val();
  if (keyCode == 13) {//enter
    if (tipo_busqueda_articulo == 1) {
      // console.log('cambiar foco');
      $('#producto_valor').focus();
      
    }
  }
});

$(document).on('keyup', '#numero', function(event) {
  event.preventDefault();
  /* Act on the event */

  updateLocalStorage();

});

$(document).on('change', '#fecha_vencimiento', function(event) {
  event.preventDefault();
  /* Act on the event */

  updateLocalStorage();

});

$(document).on('change', '#tipo_busqueda_proveedor', function(event) {
  event.preventDefault();
  /* Act on the event */

  updateLocalStorage();
});

$(document).on('keyup', '#condiciones', function(event) {
  event.preventDefault();
  /* Act on the event */

  updateLocalStorage();
});

$(document).on('keyup', '#pedido_numero', function(event) {
  event.preventDefault();
  /* Act on the event */

  updateLocalStorage();
});

$(document).on('keyup', '#pedido_serie', function(event) {
  event.preventDefault();
  /* Act on the event */

  updateLocalStorage();
});

$(document).on('keyup', '#comentario', function(event) {
  event.preventDefault();
  /* Act on the event */

  updateLocalStorage();
});

$(document).on('click', '.radio_relacion_doc', function(event) {
  // event.preventDefault();
  /* Act on the event */
  updateLocalStorage();
// IMPLEMTNACION DE MEJORAS PARA EL SWEET ALER
});