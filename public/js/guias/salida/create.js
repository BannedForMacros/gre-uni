$(document).ready(function () {
  setTimeout(() => {
    $('.select_2').select2({
      theme: "bootstrap-5",
      width: $(this).data('width') ? $(this).data('width') : $(this).hasClass('w-100') ? '100%' : 'style',
      placeholder: $(this).data('placeholder'),
    });
    // callListarArticulos();
    callFormBusquedaArticulo();
    callListarClientes();
    callListarTransportistas();
    callListarProveedores();
    callBrevete();
    callIndicarProveedor();
    callGetSerie();

    callSetMotivoTraslado();
    calcularTotales();
  }, 300);


});

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
    url: route('guiasalida.getSerie'),
    data:formData,
    processData: false,
    contentType: false,
    dataType: 'json',
    success: function(response){
      console.log({response});
      var serie = response.getSerie;
      $('#span_numero').val(serie.nuevo_numero);
      updateLocalStorage();
    }
  };
  $.ajax(options);
};

$(document).on('change', '#envio-sunat', function(event) {
  event.preventDefault();
  /* Act on the event */
  updateLocalStorage();
});

var callListarArticulos = () => {

  $(`#producto_select`).select2({
    theme: "bootstrap-5",
    containerCssClass: "select2--small",
    dropdownCssClass: "select2--small",
    ajax: {
      url: route('guiasalida.listarArticulos'),
      // type: 'POST',
      data: function (params) {
        var codalmacen = $('#codalmacen').val();
        var codlistaprecio = $('#codlistaprecio').val();
        var codestacion = $('#codlistaprecio').find(':selected').data('codestacion');
        var tipo = $('#tipo_busqueda_articulo').val();
        var indicar_proveedor = $('#indicar_proveedor').prop('checked');


        var query = {
          term: params.term,
          _token: _token,
          codalmacen: codalmacen,
          codlistaprecio: codlistaprecio,
          codestacion: codestacion,
          tipo: tipo,
          indicar_proveedor: indicar_proveedor,
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
      // Additional AJAX parameters go here; see the end of this chapter for the full code of this example
    }
  });

}

var callListarClientes = () => {


  $(`#cliente_id`).select2({
    theme: "bootstrap-5",
    containerCssClass: "select2--small",
    dropdownCssClass: "select2--small",
    ajax: {
      url: route('guiasalida.listarClientes'),
      // type: 'POST',
      data: function (params) {
        var tipo_busqueda_cliente = $('#tipo_busqueda_cliente').val();
        var query = {
          term: params.term,
          tipo_busqueda_cliente: tipo_busqueda_cliente,
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

var callListarTransportistas = () => {

  $(`#transportista_id`).select2({
    theme: "bootstrap-5",
    containerCssClass: "select2--small",
    dropdownCssClass: "select2--small",
    ajax: {
      url: route('guiasalida.listarTransportistas'),
      // type: 'POST',
      data: function (params) {

        var query = {
          term: params.term,
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

var callListarProveedores = () => {

  $(`#proveedor_id`).select2({
    theme: "bootstrap-5",
    containerCssClass: "select2--small",
    dropdownCssClass: "select2--small",
    ajax: {
      url: route('guiasalida.listarProveedores'),
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

$(document).on('change', '#cliente_id', function(event) {
  var data = $(this).select2('data')[0];

  var direccion = data.direccion;
  $('#direccion').val(direccion);
  // console.log({option});
  console.log({data});

  $('#cliente_razon_social').val(data.razon_social);
  $('#cliente_nro_documento').val(data.nro_documento);
  $('#cliente_documento_tipo_nombre').val(data.documento_tipo_nombre);
  $('#cliente_direccion').val(direccion);
});

$(document).on('change', '#transportista_id', function(event) {
  var data = $(this).select2('data')[0];

  var transportista_direccion = data.transportista_direccion;
  $('#transportista_direccion').val(transportista_direccion);
  $('#transportista_ruc').val(data.ruc);
  $('#transportista_nombre').val(data.nombre);
  // console.log({option});
  updateLocalStorage();
  callGetModalidadTraslado();
});

$(document).on('change', '#chofer_id', function(event) {
  event.preventDefault();
  /* Act on the event */
  callBrevete();
});

var callBrevete = () => {
  var brevete = $('#chofer_id').find(':selected').data('brevete_chofer');
  // console.log({brevete});
  $('#brevete').val(brevete)
}

$(document).on('click', '#btnAdd', function(event) {
  event.preventDefault();
  /* Act on the event */
  callAgregarItem();

});

$(document).on('click', '.delete_item', function(event) {
  event.preventDefault();
  /* Act on the event */

  $(this).parent().parent().remove();

  calcularTotales();

});




// --- FUNCIÓN CENTRAL DE CÁLCULO DE LA FILA ---
// ***** AGREGA ESTA NUEVA VERSIÓN DE LA FUNCIÓN *****

function recalcularFila(fila) {
    const cantidad = parseFloat(fila.find('.input_cantidad_tr').val()) || 0;
    const precioUnitario = parseFloat(fila.find('span[name="span_precio"]').text()) || 0;
    const valorDescuento = parseFloat(fila.find('.valor_descuento_tr').val()) || 0;

    // CAMBIO CLAVE: Lee el tipo de descuento desde el control maestro en la cabecera
    const tipoDescuento = $('input[name="master_discount_type"]:checked').val();

    let montoDescuentoCalculado = 0;
    let porcentajeDescuentoCalculado = 0;
    const subtotal = cantidad * precioUnitario;

    if (tipoDescuento === 'porcentaje') {
        porcentajeDescuentoCalculado = valorDescuento;
        montoDescuentoCalculado = subtotal * (valorDescuento / 100);
    } else { // tipoDescuento es 'monto'
        montoDescuentoCalculado = valorDescuento;
        if (subtotal > 0) {
            porcentajeDescuentoCalculado = (valorDescuento / subtotal) * 100;
        }
    }

    const importeFinal = subtotal - montoDescuentoCalculado;

    fila.find('span[name="span_importe"]').text(importeFinal.toFixed(2));
    fila.attr('data-monto-descuento', montoDescuentoCalculado.toFixed(4));
    fila.attr('data-porcentaje-descuento', porcentajeDescuentoCalculado.toFixed(4));

    calcularTotales();
}

// --- DISPARADORES DE EVENTOS ---
// Esto hace que la función se ejecute cuando cambies la cantidad, el valor del dcto o el tipo de dcto.
// ***** REEMPLAZA LOS EVENTOS ANTERIORES DE LA TABLA CON ESTE CÓDIGO *****

// Evento unificado para cuando se edita una fila (cantidad o valor del descuento)
$('#tbody').on('input', '.input_cantidad_tr, .valor_descuento_tr', function() {
    const filaActual = $(this).closest('tr');
    recalcularFila(filaActual);
});

// Evento para el control maestro en la cabecera
$(document).on('change', 'input[name="master_discount_type"]', function() {
    // Cuando el control maestro cambia, recalculamos TODAS las filas
    $('#tbody tr').each(function() {
        recalcularFila($(this));
    });
});

// Se asegura de que los cálculos se ejecuten al cargar la página
$(document).ready(function() {
    setTimeout(() => {
        $('#tbody tr').each(function() {
            recalcularFila($(this));
        });
    }, 600); // Un poco más de tiempo por si acaso
});


var calcularTotales = () => {
    // Se mantiene la recolección de datos que ya estaba correcta
    var items = $('#tbody tr').map(function(i, row) {
        return {
            'producto_id': $(this).data('producto_id'),
            'cantidad': $(this).find('input[name=cantidad]').val(),
            'importe': $(this).find('span[name=span_importe]').text(), // Este es el valor clave (ya tiene descuento)
            'monto_descuento': $(this).attr('data-monto-descuento') || 0,
            'peso': $(this).data('peso'),
            'afecto': $(this).data('afecto'),
        };
    }).get();

    var total_items = items.length;
    var peso_total = 0;
    var total_igv = 0;
    var total_sin_igv = 0; // Esto será nuestro "Valor Venta"
    var monto_descuento = 0;
    var total_cantidad = 0;

    // Primer bucle: Sumamos cantidades, pesos y descuentos totales.
    // Esto ya estaba casi bien, solo lo simplificamos.
    $.map(items, function(element) {
        if (element.cantidad && parseFloat(element.cantidad) > 0) {
            total_cantidad += parseFloat(element.cantidad);
            peso_total += (parseFloat(element.peso) * parseFloat(element.cantidad));
            monto_descuento += parseFloat(element.monto_descuento);
        }
    });

    // --- INICIO DE LA CORRECCIÓN PRINCIPAL ---
    // Segundo bucle: Calculamos la base imponible (valor venta) y el IGV
    // a partir del IMPORTE CON DESCUENTO.
    $.map(items, function(element) {
        if (element.cantidad && parseFloat(element.cantidad) > 0) {
            const importe_con_descuento = parseFloat(element.importe);

            // Si el producto está afecto a IGV, separamos la base del impuesto
            if (element.afecto == 1) {
                const valor_venta_item = importe_con_descuento / 1.18;
                const igv_item = importe_con_descuento - valor_venta_item;

                total_sin_igv += valor_venta_item;
                total_igv += igv_item;
            } else {
                // Si no está afecto, el importe completo es valor venta y no hay IGV.
                total_sin_igv += importe_con_descuento;
            }
        }
    });
    // --- FIN DE LA CORRECCIÓN PRINCIPAL ---

    // El total venta ahora se calcula correctamente
    var total_venta = total_sin_igv + total_igv;

    $('#total_items').val(total_items);
    $('#total_cantidad').val(total_cantidad.toFixed(2));
    $('#monto_descuento').val(monto_descuento.toFixed(2));
    $('#importe_sin_igv').val(total_sin_igv.toFixed(2)); // "Valor Venta"
    $('#monto_igv').val(total_igv.toFixed(2));
    $('#total_venta').val(total_venta.toFixed(2));
    $('#peso_bruto_total').val(peso_total.toFixed(2));

    updateLocalStorage();
};



$(document).on('submit', '#form_store', function(event) {
  event.preventDefault();
  /* Act on the event */

  callStore();

});

var callStore = (guardar_avance = false) => {

  var formElement = document.getElementById("form_store");
  var formData = new FormData(formElement);
  const esConsignadoMaster = $('#es_consignado_master').is(':checked') ? 1 : 0;

  var items = $('#tbody tr').map(function(i, row) {
    return {
      'codarticulo' : $(this).data('producto_id'),
      // 'codigo_producto' : $(this).find('input[name=item]').val(),
      'precio' : $(this).find('span[name=span_precio]').text(),
      'cantidad' : $(this).find('input[name=cantidad]').val(),
      'importe' : $(this).find('span[name=span_importe]').text(),
      'porcentaje_descuento' : $(this).attr('data-porcentaje-descuento') || 0,
      'monto_descuento' : $(this).attr('data-monto-descuento') || 0,
      'descripcion' : $(this).data('descripcion'),
      'codigo' : $(this).data('codigo'),
      'precio_publico' : $(this).data('precio_publico'),
      'precio_sin_igv' : $(this).data('precio_sin_igv'),
      'codigo_barra' : $(this).data('codigo_barra'),
      'peso' : $(this).data('peso'),
      'stock' : $(this).data('stock'),
      'cod_unidad' : $(this).data('cod_unidad'),
      'desc_unidad_medida' : $(this).data('desc_unidad_medida'),
      'sigla_umfe' : $(this).data('sigla_umfe'),
      'costo_articulo' : $(this).data('costo_articulo'),
       'es_consignado': esConsignadoMaster

    };
  }).get();
  
  formData.append('detalle', JSON.stringify(items));

  // console.log({items});
  var codestacion = $('#codlistaprecio').find(':selected').data('codestacion');
  formData.append('codestacion', codestacion)

  var monto_descuento = $('#monto_descuento').val();
  var importe_sin_igv = $('#importe_sin_igv').val();
  var monto_igv = $('#monto_igv').val();
  var total_venta = $('#total_venta').val();
  var comentario = $('#comentario').val();
  var data_proveedor = $('#proveedor_id').select2('data')[0];


  // if (data_proveedor != null) {

  //   var proveedor_nombre = data_proveedor.proveedor_nombre;
  //   formData.append('proveedor_nombre', proveedor_nombre);
  //   var proveedor_ruc = data_proveedor.proveedor_ruc;
  //   formData.append('proveedor_ruc', proveedor_ruc);
  // }

  var vendedor_nombre = $('#vendedor_id').find(':selected').data('vendedor_nombre');
  console.log({vendedor_nombre});
  if (vendedor_nombre == undefined) {
    vendedor_nombre = '';
  }
  formData.append('vendedor_nombre', vendedor_nombre);


  var data_cliente = $('#cliente_id').select2('data')[0];
  // if (data_cliente != null) {

  //   formData.append('cliente_razon_social', data_cliente.razon_social);
  //   formData.append('cliente_nro_documento', data_cliente.nro_documento);
  //   formData.append('cliente_documento_tipo_nombre', data_cliente.documento_tipo_nombre);
  //   formData.append('cliente_direccion', data_cliente.direccion);
  // }

  var divisa_nombre = $('#divisa_id').find(':selected').data('nombre');
  formData.append('divisa_nombre', divisa_nombre);

  var forma_pago_nombre = $('#forma_pago_id').find(':selected').data('nombre');
  formData.append('forma_pago_nombre', forma_pago_nombre);

  var tipo_operacion_nombre = $('#tipo_operacion_id').find(':selected').data('nombre');
  formData.append('tipo_operacion_nombre', tipo_operacion_nombre);

  var almacen_nombre = $('#codalmacen').find(':selected').data('nombre');
  formData.append('almacen_nombre', almacen_nombre);

  var data_transportista = $('#transportista_id').select2('data')[0];
  // if (data_transportista != null) {
  //   formData.append('transportista_ruc', data_transportista.ruc);
  //   formData.append('transportista_nombre', data_transportista.nombre);
  //   formData.append('transportista_direccion', data_transportista.transportista_direccion);

  // }

  var chofer_dni = $('#chofer_id').find(':selected').data('dni_chofer');
  formData.append('chofer_dni', chofer_dni);

  var chofer_brevete = $('#chofer_id').find(':selected').data('brevete_chofer');
  formData.append('chofer_brevete', chofer_brevete);

  var chofer_nombre = $('#chofer_id').find(':selected').data('nombre');
  formData.append('chofer_nombre', chofer_nombre);

  var vehiculo_placa = $('#vehiculo_id').find(':selected').data('placa');
  formData.append('vehiculo_placa', vehiculo_placa);

  var vehiculo_marca = $('#vehiculo_id').find(':selected').data('marca');
  formData.append('vehiculo_marca', vehiculo_marca);

  var base_calculo = $('#base_calculo').val();
  formData.append('base_calculo', base_calculo);

  formData.append('monto_descuento', monto_descuento);
  formData.append('importe_sin_igv', importe_sin_igv);
  formData.append('monto_igv', monto_igv);
  formData.append('total_venta', total_venta);
  formData.append('comentario', comentario);
  formData.append('guardar_avance', guardar_avance);


  formData.append('ubigeo_partida_departamento', $('#partida_departamento').val());
  formData.append('ubigeo_partida_provincia', $('#partida_provincia').val());
  formData.append('ubigeo_partida_distrito', $('#partida_distrito').val());


  formData.append('ubigeo_llegada_departamento', $('#llegada_departamento').val());
  formData.append('ubigeo_llegada_provincia', $('#llegada_provincia').val());
  formData.append('ubigeo_llegada_distrito', $('#llegada_distrito').val());

  var almacen_origen_nombre = $('#cod_almacen_origen').find(':selected').data('nombre');
  formData.append('almacen_origen_nombre', almacen_origen_nombre);

  var codigo_anexo_partida = $('#cod_almacen_origen').find(':selected').data('codigo_anexo');
  formData.append('codigo_anexo_partida', codigo_anexo_partida);

  var almacen_destino_nombre = $('#cod_almacen_destino').find(':selected').data('nombre');
  formData.append('almacen_destino_nombre', almacen_destino_nombre);

  var codigo_anexo_llegada = $('#cod_almacen_destino').find(':selected').data('codigo_anexo');
  formData.append('codigo_anexo_llegada', codigo_anexo_llegada);

  var peso_bruto_total = $('#peso_bruto_total').val();
  formData.append('peso_bruto_total', peso_bruto_total);

  var motivo_traslado_id = $('#motivo_traslado_id').val();
  var descripcion_motivo_traslado = $('#motivo_traslado_id').find(':selected').text();
  if (motivo_traslado_id == '-1') {
    var descripcion_motivo_traslado = '';
  }

  formData.append('descripcion_motivo_traslado', descripcion_motivo_traslado);
  new Response(formData).text().then(console.log)
  // store(formData);

  var indicar_proveedor = $('#indicar_proveedor').prop('checked');
  var procede_store = true;

  var msj_store = '';
  console.log(formData.get('proveedor_nombre'));

  if (formData.get('guardar_avance') == 'false') {

    if (formData.get('vendedor_nombre') == '') {
      procede_store = false;
      msj_store = `Debe indicar un vendedor`;
    }

    if (procede_store == true) {

      if (formData.get('tipo_operacion_id') == 12) {
        if (formData.get('cod_almacen_origen') == formData.get('cod_almacen_destino')) {
          procede_store = false;
          msj_store = 'Almacen origen y Destino no pueden ser iguales';
        }
      }
    }

    if (indicar_proveedor == true) {
      if (formData.get('proveedor_nombre') == '') {
        procede_store = false;
        msj_store = 'Debe indicar un proveedor';
      }

    }

    if (procede_store == true) {
      if (indicar_proveedor == false) {
        if (formData.get('tipo_operacion_id') != 12) {
          if (formData.get('cliente_razon_social') == '') {
            procede_store = false;
            msj_store = 'Debe indicar un cliente';
          }

        }

      }
    }

    if (procede_store == true) {
      if (formData.get('transportista_nombre') == '') {
        procede_store = false;
        msj_store = 'Debe indicar un transportista';
      }
    }

    if (procede_store == true) {
      if (items.length <= 0) {
        procede_store = false;
        msj_store = 'Debe indicar articulos en la guia';
      }
    }

    if (procede_store == true) {
      if (peso_bruto_total == '') {
        procede_store = false;
        msj_store = 'Debe indicar el Peso Total';
      }
    }

    if (procede_store == true) {
      if (peso_bruto_total <= 0) {
        procede_store = false;
        msj_store = 'El peso debe ser mayor a cero (0)';
      }
    }

    if (procede_store == true) {
      if (formData.get('direccion_llegada').trim() == formData.get('direccion_partida').trim()) {
        procede_store = false;
        msj_store = `<b>Las direcciones no pueden ser las mismas</b>`
      }

    }
    if (procede_store == true) {
      if (formData.get('direccion_llegada').trim() == '') {
        procede_store = false;
        msj_store = `<b>La direccion de llegada no pueden estan en blanco</b>`
      }

    }
    if (procede_store == true) {
      if (formData.get('direccion_partida').trim() == '') {
        procede_store = false;
        msj_store = `<b>La direccion de partida no pueden estan en blanco</b>`
      }

    }

    if (procede_store == true) {
      if (formData.get('modalidad_traslado').trim() == '') {
        procede_store = false;
        msj_store = `<b>No se cargo la modalidad de traslado</b>`;
      }
    }

    if (procede_store == true) {
      if (formData.get('ubigeo_llegada_distrito').trim() == '') {
        procede_store = false;
        msj_store = `<b>Codigo de Ubigeo no cargado, reintente con buscando de nuevo</b>`
      }
    }

    if (procede_store == true) {
      if (formData.get('ubigeo_partida_distrito').trim() == '') {
        procede_store = false;
        msj_store = `<b>Codigo de Ubigeo no cargado, reintente con buscando de nuevo</b>`
      }
    }

  }

  $.map(items, function (element, index) {
    if (procede_store == true) {
      if (parseFloat(element.cantidad) < 0) {
        procede_store = false;
        msj_store = `<b>El item [${element.codarticulo}] ${element.descripcion} <br>tiene un valor negativo o cero = ${element.cantidad}</b>`;
      }
      if (parseFloat(element.cantidad) == 0) {
        procede_store = false;
        msj_store = `<b>El item [${element.codarticulo}] ${element.descripcion} <br>tiene un cero = ${element.cantidad}</b>`;
      }
    }
    console.log(element.stock, element.cantidad);

    if (procede_store == true) {
      var validar_stock = ($('#validar_stock').val() == 'true') ? true : false

      if (validar_stock == true) {
        console.log('vlidacion de stock');

        if (element.stock < parseInt(element.cantidad)) {
          procede_store = false;
          msj_store = `<b>El item [${element.codarticulo}] ${element.descripcion} <br>tiene stock menor a ${element.cantidad}</b>`;
        }

      }
    }
  });

  if (procede_store == true) {

    var msj_guardado = `<b>¿Desea registrar esta Guia de Salida?</b>`;
    if (formData.get('envio_sunat') == 0) {
      msj_guardado = `${msj_guardado} <br><code>No se enviara a SUNAT</code>`;
    }
    if (formData.get('envio_sunat') == 1) {
      msj_guardado = `${msj_guardado} <br><code>Se enviara a SUNAT</code>`;
    }
    if (guardar_avance == true) {
      msj_guardado = `<b>¿Desea guardar el avance de esta Guia de Salida?</b>`;

    }

    Swal.fire({
      html: msj_guardado,
      icon: "warning",
      showCancelButton: !0,
      confirmButtonText: "Si, Registrar",
      cancelButtonText: "No, cancelar!",
      allowOutsideClick: false

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

$(document).on('change', '#proveedor_id', function(event) {
  event.preventDefault();
  /* Act on the event */
  var data_proveedor = $('#proveedor_id').select2('data')[0];
  console.log(data_proveedor);


  $('#proveedor_nombre').val(data_proveedor.proveedor_nombre);
  $('#proveedor_ruc').val(data_proveedor.proveedor_ruc);
  $('#proveedor_direccion').val(data_proveedor.proveedor_direccion);

  limpiarDetalle();

  setTimeout(() => {
    validarDireccionProveedor();
  }, 200);

});

var modalStore = function(formData){
  var options = {
    type: 'POST',
    url: route('guiasalida.modalStore'),
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
    url: route('guiasalida.store'),
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
        localStorage.removeItem('storageGuiaSalida')
      }

    }
  };
  $.ajax(options);
};

var storeDataMart = function(formData){
  var options = {
    type: 'POST',
    url: route('guiasalida.storeDataMart'),
    data:formData,
    processData: false,
    contentType: false,
    dataType: 'json',
    success: function(response){
      $('#li_store_datamart').html(response.msj);

      if (response.procede == true) {
        if (formData.get('guardar_avance') == 'false') {
          if (formData.get('envio_sunat') == 1) {
            if (formData.get('id') == '') {
              formData.append('id', response.id);
            }
            facturacionElectronica(formData);

          }

        }
      }

    }
  };
  $.ajax(options);
};

var facturacionElectronica = function(formData){
  var options = {
    type: 'POST',
    url: route('guiasalida.facturacionElectronica'),
    data:formData,
    processData: false,
    contentType: false,
    dataType: 'json',
    success: function(response){
      $('#li_facturacion').html(response.msj);
    }
  };
  $.ajax(options);
};

$(document).on('change', '#tipo_operacion_id', function(event) {
  event.preventDefault();
  /* Act on the event */

  callSetMotivoTraslado();

  limpiarDetalle();

  setTimeout(() => {
    validarDireccionProveedor();
  }, 200);
});

var callSetMotivoTraslado = () => {

  var data = $('#tipo_operacion_id').find(':selected').data();
  if (data.codigo_motivo_traslado == '') {
    $('#motivo_traslado_id').html(`<option value='-1' >Sin motivo</option>`);

  }else{
    $('#motivo_traslado_id').html(`<option value='${data.codigo_motivo_traslado}' >${data.nombre_motivo_traslado}</option>`);
  }

  var tipo_operacion_id = $('#tipo_operacion_id').val();
  console.log({tipo_operacion_id});
  if (tipo_operacion_id == 12) {
    $('#div_almacen_unico').hide();
    $('#div_almacene_transferencia').show();
    $('#div_cliente').hide();
    $('#div_proveedor').hide();
    $('#div_operaciones').removeClass("col-md-6").addClass("col-md-12");
    $('#indicar_proveedor').prop('checked', false);
    $('#indicar_proveedor').prop('disabled', true);


    console.log('mostramos origen y destino');
  } else {
    $('#div_operaciones').removeClass("col-md-12").addClass("col-md-6");
    $('#indicar_proveedor').prop('disabled', false);

    var indicar_proveedor = $('#indicar_proveedor').prop('checked');
    if (indicar_proveedor == true) {
      $('#div_cliente').hide();
      $('#div_proveedor').show();

    }else{
      $('#div_cliente').show();
      $('#div_proveedor').hide();

    }
    $('#div_almacen_unico').show();
    $('#div_almacene_transferencia').hide();
    console.log('mostramos solo un almacen');
  }

  updateLocalStorage();
}

$(document).on('change', '#base_calculo', function(event) {
  event.preventDefault();
  /* Act on the event */

  callBaseCalculo();


});

var callBaseCalculo = () => {
  console.log('generado base calculo...');

  var base_calculo = $('#base_calculo').val();

  console.log({base_calculo});

  $('#tbody tr').map(function(i, row) {
    // console.log($(this).data());
    var cantidad = $(this).find('input[name=cantidad]').val();
    var afecto = $(this).data('afecto');
    var precio_unitario_sin_igv = $(this).data('precio_sin_igv');

    var indicar_proveedor = $('#indicar_proveedor').prop('checked');
    console.log({indicar_proveedor});


    if (afecto == 1) {
      var precio_unitario = $(this).data('precio_unitario');
      if (base_calculo == 1) {
        var precio_unitario = $(this).data('precio_sin_igv');
      }

      $(this).find('span[name=span_precio]').html(precio_unitario);

      var importe = round((parseFloat(precio_unitario) * cantidad),2);

      $(this).find('span[name=span_importe]').html(importe);

    }
    var importe_sin_igv = round((parseFloat(precio_unitario_sin_igv) * cantidad),2);
    $(this).find('span[name=span_importe_sin_igv]').html(importe_sin_igv);

  })

  calcularTotales();


}

$(document).on('click', '#btnGuardarAvance', function(event) {
  event.preventDefault();
  /* Act on the event */
  callStore(true);
});

$(document).on('change', '#indicar_proveedor', function(event) {
  event.preventDefault();
  /* Act on the event */
  callIndicarProveedor();

  limpiarDetalle();

  setTimeout(() => {
    validarDireccionProveedor();
  }, 200);
});

var callIndicarProveedor = () => {

  var status = $('#indicar_proveedor').prop('checked')

  var tipo_operacion_id = $('#tipo_operacion_id').val();
  $('#th_tipo_precio').text('Precio')

  if (status == true) {
    $('#div_proveedor').show();
    $('#div_cliente').hide();
    $('#th_tipo_precio').text('Costo')
  } else {
    $('#div_proveedor').hide();
    $('#div_cliente').show();

  }

  updateLocalStorage();

}

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

var callGetModalidadTraslado = () => {
  var transportista_ruc = $('#transportista_ruc').val();

  var formData = new FormData();
  formData.append('_token', _token);
  formData.append('transportista_ruc', transportista_ruc);

  getModalidadTraslado(formData);
}

var getModalidadTraslado = function(formData){
  var options = {
    type: 'POST',
    url: route('guiasalida.getModalidadTraslado'),
    data:formData,
    processData: false,
    contentType: false,
    dataType: 'json',
    success: function(response){
      $('#modalidad_traslado').val(response.modalidad_traslado);
      if (response.verChofer == true) {
        $('#div_chofer').show();
        $('#div_vehiculo').show();
      }else{
        $('#div_chofer').hide();
        $('#div_vehiculo').hide();

      }

      updateLocalStorage();
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

$(document).on('click', '#btnReintentarFacturar', function(event) {
  event.preventDefault();
  /* Act on the event */
  var id = $(this).data('id');

  $('#li_facturacion').html(`<b>Enviando a SUNAT...</b>
  <span class=""><i class="fa-solid fa-spinner fa-spin fa-lg"></i></span>`);

  var formData = new FormData();
  formData.append('_token', _token);
  formData.append('id', id);

  facturacionElectronica(formData);
});

$(document).on('change', '#pedido_interno', function(event) {
  event.preventDefault();
  /* Act on the event */

  // var status = $(this).prop('checked');
  // console.log({status});
  updateLocalStorage();
});

$(document).on('keyup', '.input_pedido_interno', function(event) {
  event.preventDefault();
  /* Act on the event */
  updateLocalStorage();
});

$(document).on('keyup', '#comentario', function(event) {
  event.preventDefault();
  /* Act on the event */

  updateLocalStorage();

});

$(document).on('change', '#es_consignado_master', function(event) {
    event.preventDefault();
    /* Act on the event */
    updateLocalStorage();
});

var limpiarDetalle = () => {

  $('#tbody').html('');

  updateLocalStorage();

  calcularTotales();

}

var validarDireccionProveedor = () => {

  var indicar_proveedor = $('#indicar_proveedor').prop('checked');

  console.log({indicar_proveedor});
  var proveedor_id = $('#proveedor_id').val();

  if (indicar_proveedor == true) {
    if (proveedor_id != null) {
      // console.log(' se toma direccion ');
      var proveedor_direccion = $('#proveedor_direccion').val();
      $('#direccion_llegada').val(proveedor_direccion);
    }
  }

}

$(document).on('change', '#fecha_emision', function(event) {
  event.preventDefault();
  /* Act on the event */

  callValidarMesAbierto();

});


var callValidarMesAbierto = () => {

  var fecha_emision = $('#fecha_emision').val();

  var formData = new FormData();
  formData.append('_token', _token);
  formData.append('fecha_emision', fecha_emision);

  validarMesAbierto(formData);
}

var validarMesAbierto = function(formData){
  var options = {
    type: 'POST',
    url: route('guiasalida.validarMesAbierto'),
    data:formData,
    processData: false,
    contentType: false,
    dataType: 'json',
    success: function(response){
      if (response.procede == true) {
        $('#div_btn_guardar').show();
      }

      if (response.procede == false) {
        $('#div_btn_guardar').hide();

        Swal.fire({
          html: response.msj,
          icon: response.msj_tipo
        })

      }

    }
  };
  $.ajax(options);
};
