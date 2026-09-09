$(document).ready(function () {
  var id_continua = $('#id_continua').val();
  setTimeout(() => {
    if (id_continua == '') {
      // callListarUbigeos('partida_departamento');
      // callListarUbigeos('llegada_departamento');
      
    }
  }, 300);
});

$(document).on('change', '.ubigeo', function(event) {
  event.preventDefault();
  /* Act on the event */

  var tag_id = $(this).attr('id');
  // console.log({tag_id});
  callListarUbigeos(tag_id);
  updateLocalStorage();
});

var callListarUbigeos = (tag_id) => {
  var tipo_busqueda = $(`#${tag_id}`).data('tipo_busqueda');
  var tipo_ubigeo = $(`#${tag_id}`).data('tipo_ubigeo');
  var codUbigeo = $(`#${tag_id}`).val();
  var formData = new FormData();
  formData.append('_token', _token);
  formData.append('tipo_ubigeo', tipo_ubigeo);
  formData.append('tipo_busqueda', tipo_busqueda);
  formData.append('codUbigeo', codUbigeo);

  if (tipo_busqueda != null) {
    listarUbigeos(formData);
  }

}

var listarUbigeos = function(formData){
  var options = {
    type: 'POST',
    url: route('guiasalida.listarUbigeos'),
    data:formData,
    processData: false,
    contentType: false,
    dataType: 'json',
    success: function(response){
      $(`#${response.tag_id}`).html(response.options);
      if (response.procede == true) {
        if (response.next == true) {
          callListarUbigeos(response.tag_id);
        }
      }

      // setTimeout(() => {
      //   validarDireccionProveedor();
      // }, 200);

      updateLocalStorage();
    }
  };
  $.ajax(options);
};