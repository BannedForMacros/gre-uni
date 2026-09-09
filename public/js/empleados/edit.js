$(document).on('keyup', '#nro_documento', function(event) {
  // event.preventDefault();
  /* Act on the event */

  var numero = $(this).val();
  // console.log(numero);

  $('#usuario').val(numero)
  $('#password').val(numero)

});


$(document).on('submit', '#form_update', function (event) {
  event.preventDefault();
  /* Act on the event */

  var formElement = document.getElementById("form_update");
  var formData = new FormData(formElement);

  Swal.fire({
    html: `¿!Esta seguro de actualizar este empleados¡?`,
    icon: "warning",
    showCancelButton: !0,
    confirmButtonText: "Si, Actualizar",
    cancelButtonText: "No, cancelar!",
  }).then((result) => {
    if (result.isConfirmed) {
      update(formData);
    }
  })

});


var update = function (formData) {
  var options = {
    type: 'POST',
    url: route('empleados.update'),
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
            window.location.href = response.url_redirect;
          }
        }
      })
    }
  };
  $.ajax(options);
};