$(document).ready(function () {
  setTimeout(() => {

    var id_continua = $('#id_continua').val();
    setTimeout(() => {
      if (id_continua == '') {
        // callListarUbigeos('partida_departamento');
        // callListarUbigeos('llegada_departamento');
        var tipo_operacion_id = $('#tipo_operacion_id').val();
        // console.log({tipo_operacion_id});
        if (tipo_operacion_id == 12) {//transf
          callUbigeoAlmacen('cod_almacen_origen');
          callUbigeoAlmacen('cod_almacen_destino');

        }else{
          callUbigeoAlmacen('codalmacen');
          callListarUbigeos('llegada_departamento');

        }
      }
    }, 300);


  }, 300);
});

$(document).on('change', '.almacen_select', function(event) {
  event.preventDefault();
  /* Act on the event */
  var id_tag = $(this).prop('id');
  callUbigeoAlmacen(id_tag);
});


var callUbigeoAlmacen = (id_tag) => {
  var tipo = $(`#${id_tag}`).data('almacen_tipo');
  var ubigeo = $(`#${id_tag}`).find(':selected').data('ubigeo');
  var direccion = $(`#${id_tag}`).find(':selected').data('direccion');

  console.log({id_tag,tipo, ubigeo, direccion});
  
  var formData = new FormData();
  formData.append('_token', _token);
  formData.append('tipo', tipo);
  formData.append('ubigeo', ubigeo);
  formData.append('direccion', direccion);
  getUbigeosPorAlmacen(formData);
}


var getUbigeosPorAlmacen = function(formData){
  var options = {
    type: 'POST',
    url: route('guiasalida.getUbigeosPorAlmacen'),
    data:formData,
    processData: false,
    contentType: false,
    dataType: 'json',
    success: function(response){
      if (response.tipo == 1) {//origen
        $('#partida_departamento').html(response.optionsDepartamento);
        $('#partida_provincia').html(response.optionsProvincia);
        $('#partida_distrito').html(response.optionsDistrito);
        $('#direccion_partida').val(response.direccion);
        
      }
      
      if (response.tipo == 2) {//destino
        $('#llegada_departamento').html(response.optionsDepartamento);
        $('#llegada_provincia').html(response.optionsProvincia);
        $('#llegada_distrito').html(response.optionsDistrito);
        $('#direccion_llegada').val(response.direccion);
      }

      updateLocalStorage();
    }
  };
  $.ajax(options);
};
