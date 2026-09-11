$(document).ready(function () {

  setTimeout(() => {
    $('.select_2').select2({
      theme: "bootstrap-5",
      width: $(this).data('width') ? $(this).data('width') : $(this).hasClass('w-100') ? '100%' : 'style',
      placeholder: $(this).data('placeholder'),
    });

    callListarArticulos();

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
      var serie = response.getSerie;
      // El servidor ya no revienta con 500 cuando la ApiGRE esta caida o la
      // serie no existe: responde procede:false y getSerie null. Sin esta
      // guarda, leer .nuevo_numero de null rompia el script y el numero
      // quedaba en blanco sin ningun aviso.
      if (!serie) {
        $('#numero').val('');
        if (window.Gre && Gre.avisarError) { Gre.avisarError(response.msj || 'No se pudo obtener el numero de la serie.'); }
        return;
      }
      $('#numero').val(serie.nuevo_numero);
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

// El buscador de proveedor ya no es un select2.
//
// Era el unico select2 que quedaba en esta pantalla, junto a un buscador de
// articulos escrito con Alpine: dos buscadores con distinto aspecto, distinto
// foco y distinto teclado para la misma tarea. Ahora los dos son el mismo
// componente (public/js/gre/guia-combo.js) y comparten vocabulario.
//
// De aqui desaparecen la inicializacion y el handler de change: el componente
// publica proveedor_id, proveedor_nombre y proveedor_ruc como inputs del
// formulario, asi que entran solos en el FormData.


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

/**
 * Los totales los calcula el componente (public/js/gre/guia-detalle.js) y
 * Alpine los pinta solos. Esto queda solo para el localStorage, que todavia
 * no se migro.
 *
 * Antes eran 83 lineas que recorrian las filas del <tbody> leyendo atributos
 * data-*, con el 0.18 escrito a mano y aplicando el IGV sobre la suma de las
 * bases. Eso daba un centimo de diferencia contra el calculo de PHP.
 */
var calcularTotales = () => {
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

  var items = (window.greDetalle ? window.greDetalle.detalleParaEnviar() : []);



  formData.append('detalle', JSON.stringify(items));

  // console.log({items});



  var codestacion = $('#codalmacen').find(':selected').data('codestacion');
  formData.append('codestacion', codestacion)

  var monto_descuento = $('#monto_descuento').val();
  var importe_sin_igv = $('#importe_sin_igv').val();
  var monto_igv = $('#monto_igv').val();
  var total_venta = $('#total_venta').val();
  var comentario = $('#comentario').val();
  // proveedor_nombre y proveedor_ruc son inputs del formulario que publica el
  // buscador, igual que vendedor_nombre. Antes habia que pedirselos a select2.
  formData.append('proveedor_nombre', $('#proveedor_nombre').val() || '');
  formData.append('proveedor_ruc', $('#proveedor_ruc').val() || '');
  // new Response(formData).text().then(console.log)

  // vendedor_nombre ya viaja como input del formulario, lo publica el
  // componente del selector. Antes se leia del data-* de la <option>, que solo
  // existia porque el controller devolvia las opciones ya en HTML.

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

  // store(formData);

  var procede_store = true;
  var msj_store = '';
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

        // Una guia de ingreso es un documento interno: no se envia a SUNAT.
        // Aqui habia una llamada a facturacionElectronica(), que en esta
        // pantalla ni siquiera existe (ReferenceError si llegaba a entrar).
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
});

// El cambio de base de calculo (con/sin IGV) lo maneja el componente:
// baseCalculo es una propiedad y precioMostrado() deriva de ella.
// Antes eran 25 lineas recorriendo el <tbody> y reescribiendo <span> a mano.



$(document).on('click', '#btnGuardarAvance', function(event) {
  event.preventDefault();
  /* Act on the event */
  callStore(true);
});

// La busqueda de vendedor vive en public/js/gre/guia-vendedor.js: era un
// $.ajax que metia los <option> que armaba el controller con .html().

// Enter no envia la guia: el formulario tiene un solo boton de guardar y
// enviarla desde cualquier input era la forma facil de grabar a medias.
$('#form_store').on('keydown', function(e) {
  var keyCode = e.keyCode || e.which;
  if (keyCode === 13) {
    e.preventDefault();
    return false;
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


});

$(document).on('change', '#fecha_vencimiento', function(event) {
  event.preventDefault();
  /* Act on the event */


});

$(document).on('change', '#tipo_busqueda_proveedor', function(event) {
  event.preventDefault();
  /* Act on the event */

});

$(document).on('keyup', '#condiciones', function(event) {
  event.preventDefault();
  /* Act on the event */

});

$(document).on('keyup', '#pedido_numero', function(event) {
  event.preventDefault();
  /* Act on the event */

});

$(document).on('keyup', '#pedido_serie', function(event) {
  event.preventDefault();
  /* Act on the event */

});

$(document).on('keyup', '#comentario', function(event) {
  event.preventDefault();
  /* Act on the event */

});

$(document).on('click', '.radio_relacion_doc', function(event) {
  // event.preventDefault();
  /* Act on the event */
// IMPLEMTNACION DE MEJORAS PARA EL SWEET ALER
});