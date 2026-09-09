$(document).ready(function () {
  setTimeout(() => {
    $('.select_2').select2({
      theme: "bootstrap-5",
      width: $(this).data('width') ? $(this).data('width') : $(this).hasClass('w-100') ? '100%' : 'style',
      placeholder: $(this).data('placeholder'),
    });
  }, 500);
});

$(document).on('keyup', '#nro_documento', function(event) {
  // event.preventDefault();
  /* Act on the event */

  var numero = $(this).val();
  // console.log(numero);

  $('#usuario').val(numero)
  $('#password').val(numero)

});


$(document).on('submit', '#form_store', function (event) {
  event.preventDefault();
  /* Act on the event */

  var formElement = document.getElementById("form_store");
  var formData = new FormData(formElement);

  Swal.fire({
    html: `¿!Esta seguro de registrar este empleados¡?`,
    icon: "warning",
    showCancelButton: !0,
    confirmButtonText: "Si, Registrar",
    cancelButtonText: "No, cancelar!",
  }).then((result) => {
    if (result.isConfirmed) {
      store(formData);
    }
  })

});


var store = function (formData) {
  var options = {
    type: 'POST',
    url: route('empleados.store'),
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

$(document).on('change', '#empleado_dmk_id', function(event) {
  event.preventDefault();
  /* Act on the event */

  var empleado_codigo = $(this).val();
  // console.log({empleado_codigo});
  var formData = new FormData();
  formData.append('_token', _token)
  formData.append('empleado_codigo', empleado_codigo)
  getEmpleadoDmk(formData);
});

var getEmpleadoDmk = function(formData){
  var options = {
    type: 'POST',
    url: route('empleados.getEmpleadoDmk'),
    data:formData,
    processData: false,
    contentType: false,
    dataType: 'json',
    success: function(response){
      // console.log(response.empleado);
      var empleado = response.empleado;
      $('#nro_documento').val(empleado.dni)
      $('#ape_paterno').val(empleado.ape_paterno)
      $('#ape_materno').val(empleado.ape_materno)
      $('#nombres').val(empleado.nombres)

      $('#usuario').val(empleado.dni)
      $('#password').val(empleado.dni)
    }
  };
  $.ajax(options);
};