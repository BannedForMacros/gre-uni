$(document).ready(function () {
  callListarGuias();
});

$(document).on('submit', '#form_busqueda', function(event) {
  event.preventDefault();
  /* Act on the event */
  callListarGuias();
});

var callListarGuias = () => {

  var formElement = document.getElementById("form_busqueda");
  var formData = new FormData(formElement);

  $('#div_loading').show();
  $('#resultados').html('');
  listar(formData);
}

var listar = function(formData){
  var options = {
    type: 'POST',
    url: route('guiasalida.listar'),
    data:formData,
    processData: false,
    contentType: false,
    dataType: 'html',
    success: function(response){
      $('#resultados').html(response);
      $('#div_loading').hide();
      
      $('#tabla_guias').DataTable({
        responsive: true,
        language: DataTable_Spanish
      });
    }
  };
  $.ajax(options);
};

$(document).on('change', '.fecha', function(event) {
  var tipo = $(this).data('tipo');

  var fecha_inicio = $('#fecha_inicio').val();
  
  var fecha_fin = $('#fecha_fin').val();

  if (tipo == 'inicio') {
    
    if (fecha_inicio > fecha_fin) {
      // igualar = true;
      $('#fecha_fin').val(fecha_inicio)
    }
  }
  if (tipo == 'fin') {
    
    if (fecha_inicio > fecha_fin) {
      // igualar = true;
      $('#fecha_inicio').val(fecha_fin)
    }
  }

  // console.log({tipo, fecha_inicio, fecha_fin});

});

$(document).on('click', '.anular_guia', function(event) {
  event.preventDefault();
  /* Act on the event */

  var id = $(this).data('id');

  var formData = new FormData();
  formData.append('_token', _token);
  formData.append('id', id);

  Swal.fire({
    html: `¿!Esta seguro de <b>Anular</b> esta Guia¡?`,
    icon: "warning",
    showCancelButton: !0,
    confirmButtonText: "Si, Anular",
    cancelButtonText: "No, cancelar!",

    // reverseButtons: !0
  }).then((result) => {
    if (result.isConfirmed) {
      anularGuia(formData);
    }
  })

});

var anularGuia = function(formData){
  var options = {
    type: 'POST',
    url: route('guiasalida.anular'),
    data:formData,
    processData: false,
    contentType: false,
    dataType: 'json',
    success: function(response){
      Swal.fire({
        html: response.msj,
        icon: response.msj_tipo,
      }).then((result) => {
        if (result) {
          if (response.procede == true) {
            callListarGuias();
          }
        }
      })

    }
  };
  $.ajax(options);
};



$(document).on('click', '.reenviar_datamarket', function(event) {
  event.preventDefault();
  /* Act on the event */

  var id = $(this).data('id');

  var formData = new FormData();
  formData.append('_token',_token);
  formData.append('panel_origen','index');
  formData.append('id',id);

  Swal.fire({
    html: `¿<b>Deseas reenviar esta guia a DataMarket</b>?!`,
    icon: "warning",
    showCancelButton: !0,
    confirmButtonText: "Si, Reenviar",
    cancelButtonText: "No, cancelar!",
  }).then((result) => {
    if (result.isConfirmed) {
      
      storeDataMart(formData);
      
    }
  })

});


var storeDataMart = function (formData) {
  var options = {
    type: 'POST',
    url: route('guiasalida.storeDataMart'),
    data: formData,
    processData: false,
    contentType: false,
    dataType: 'json',
    success: function (response) {
      Swal.fire({
        title: '',
        html: response.msj,
        icon: response.msj_tipo,
        allowOutsideClick : false
      })

      if (response.procede == true) {
        callListarGuias();
      }
    }
  };
  $.ajax(options);
};


$(document).on('click', '.reenviar_facturador', function(event) {
  event.preventDefault();
  /* Act on the event */

  var id = $(this).data('id');

  var formData = new FormData();
  formData.append('_token',_token);
  formData.append('panel_origen','index');
  formData.append('id',id);

  Swal.fire({
    html: `¿<b>Deseas reenviar esta guia al Facturador</b>?!`,
    icon: "warning",
    showCancelButton: !0,
    confirmButtonText: "Si, reenviar",
    cancelButtonText: "No, cancelar!",
  }).then((result) => {
    if (result.isConfirmed) {
      
      facturacionElectronica(formData);
      
    }
  })

});

var facturacionElectronica = function(formData){
  var options = {
    type: 'POST',
    url: route('guiasalida.facturacionElectronica'),
    data:formData,
    processData: false,
    contentType: false,
    dataType: 'json',
    success: function(response){

      Swal.fire({
        title: '',
        html: response.msj,
        icon: response.msj_tipo,
        allowOutsideClick : false
      })

      if (response.procede == true) {
        callListarGuias();
      }

    }
  };
  $.ajax(options);
};