$(document).ready(function () {
  callListarGuias();
});

$(document).on('submit', '#form_busqueda', function (event) {
  event.preventDefault();
  /* Act on the event */
  callListarGuias();
});

var callListarGuias = () => {

  var formElement = document.getElementById("form_busqueda");
  var formData = new FormData(formElement);
  listar(formData);
}

var listar = function (formData) {
  var options = {
    type: 'POST',
    url: route('guiaingreso.listar'),
    data: formData,
    processData: false,
    contentType: false,
    dataType: 'html',
    success: function (response) {
      $('#resultados').html(response);

      $('#tabla_guias').DataTable({
        responsive: true,
        language: DataTable_Spanish
      });
    }
  };
  $.ajax(options);
};

$(document).on('change', '.fecha', function (event) {
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

$(document).on('click', '.eliminar_guia', function (event) {
  event.preventDefault();
  /* Act on the event */

  var id = $(this).data('id');

  var formData = new FormData();
  formData.append('_token', _token);
  formData.append('id', id);

  Swal.fire({
    html: `¿!Esta seguro de <b>Eliminar</b> esta Guia¡?`,
    icon: "warning",
    showCancelButton: !0,
    confirmButtonText: "Si, Eliminar",
    cancelButtonText: "No, cancelar!",

    // reverseButtons: !0
  }).then((result) => {
    if (result.isConfirmed) {
      eliminarGuia(formData);
    }
  })

});

var eliminarGuia = function (formData) {
  var options = {
    type: 'POST',
    url: route('guiaingreso.eliminar'),
    data: formData,
    processData: false,
    contentType: false,
    dataType: 'json',
    success: function (response) {
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
    confirmButtonText: "Si, Registrar",
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
    url: route('guiaingreso.storeDataMart'),
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
