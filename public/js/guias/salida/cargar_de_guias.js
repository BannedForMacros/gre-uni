$(document).on('click', '#btn_cargar_otras_guias', function(event) {
  event.preventDefault();
  /* Act on the event */

  var formData = new FormData();
  formData.append('_token', _token);

  new Response(formData).text().then(console.log)

  modalOtrasGuias(formData);
});


var modalOtrasGuias = function(formData){
  var options = {
    type: 'POST',
    url: route('guiasalida.modalOtrasGuias'),
    data:formData,
    processData: false,
    contentType: false,
    dataType: 'html',
    success: function(response){
      $('#modales').html(response);
      $('#modalOtrasGuias').modal('show')
    }
  };
  $.ajax(options);
};


$(document).on('submit', '#form_buscar_otras_guias', function(event) {
  event.preventDefault();
  /* Act on the event */

  var formElement = document.getElementById("form_buscar_otras_guias");
  var formData = new FormData(formElement);

  // new Response(formData).text().then(console.log)
  buscarOtrasGuias(formData);
});


var buscarOtrasGuias = function(formData){
  var options = {
    type: 'POST',
    url: route('guiasalida.buscarOtrasGuias'),
    data:formData,
    processData: false,
    contentType: false,
    dataType: 'html',
    success: function(response){
      $('#resultados_otras_guias').html(response);
    }
  };
  $.ajax(options);
};

$(document).on('click', '.select_otra_guia', function(event) {
  event.preventDefault();
  /* Act on the event */

  var id = $(this).data('id');

  var formData = new FormData();
  formData.append('_token', _token);
  formData.append('id', id);

  var indicar_proveedor = $('#indicar_proveedor').prop('checked');
  formData.append('indicar_proveedor', indicar_proveedor);
  var base_calculo = $('#base_calculo').val();
  formData.append('base_calculo', base_calculo);

  Swal.fire({
    title: 'Cargando guia anulada...',
    text: 'Por favor espera un momento.',
    allowOutsideClick: false,
    didOpen: () => {
      Swal.showLoading();
    }
  });

  cargarOtraGuia(formData);
  
});

var cargarOtraGuia = function(formData){
  var options = {
    type: 'POST',
    url: route('guiasalida.cargarOtraGuia'),
    data:formData,
    processData: false,
    contentType: false,
    dataType: 'json',
    success: function(response){
      
      Swal.close();

      $('#tbody').html('');
      $('#modalOtrasGuias').modal('hide');

      $('#tbody').html(response.tabla);
      setTimeout(() => {
        // calcularTotales();
        callBaseCalculo();
      }, 300);
    }
  };
  $.ajax(options);
};