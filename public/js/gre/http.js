/**
 * Cliente HTTP unico de la aplicacion.
 *
 * Antes habia 34 llamadas $.ajax sueltas, cada una repitiendo a mano la URL, el
 * token CSRF, el dataType y su propio manejo de error. No existia un solo lugar
 * donde decidir que hacer cuando la ApiGRE no responde, asi que en la practica
 * varios errores se ignoraban en silencio.
 *
 * Sin async/await ni fetch: jQuery Deferred, que ya esta cargado en la app.
 */
window.Gre = window.Gre || {};

(function (Gre, $) {
    'use strict';

    var TIMEOUT_MS = 30000;

    function token() {
        return $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val() || '';
    }

    /** Extrae un mensaje legible de cualquier forma de error del backend. */
    function mensajeDeError(xhr, textStatus) {
        if (textStatus === 'timeout') {
            return 'El servidor tardo demasiado en responder.';
        }
        if (xhr.status === 0) {
            return 'No hay conexion con el servidor.';
        }
        var r = xhr.responseJSON;
        if (r) {
            if (r.msj) { return r.msj; }
            if (r.message) { return r.message; }
            if (r.errors) {
                return Object.keys(r.errors).map(function (k) { return r.errors[k][0]; }).join('\n');
            }
        }
        if (xhr.status === 419) { return 'La sesion expiro. Recargue la pagina.'; }
        if (xhr.status >= 500)  { return 'Error interno del servidor.'; }
        return 'Ocurrio un error inesperado (' + xhr.status + ').';
    }

    /**
     * @param {string} url
     * @param {object} datos
     * @param {object} [opciones] { metodo, silencioso }
     * @returns {jQuery.Deferred} resuelve con la respuesta, rechaza con Error
     */
    Gre.request = function (url, datos, opciones) {
        opciones = opciones || {};
        var dfd = $.Deferred();

        $.ajax({
            url: url,
            type: opciones.metodo || 'POST',
            data: $.extend({ _token: token() }, datos || {}),
            dataType: 'json',
            timeout: opciones.timeout || TIMEOUT_MS
        })
        .done(function (resp) {
            // El backend usa 'procede' para errores de negocio con HTTP 200.
            if (resp && resp.procede === false) {
                var err = new Error(resp.msj || 'La operacion no se pudo completar.');
                err.respuesta = resp;
                if (!opciones.silencioso) { Gre.avisarError(err.message); }
                dfd.reject(err);
                return;
            }
            dfd.resolve(resp);
        })
        .fail(function (xhr, textStatus) {
            var err = new Error(mensajeDeError(xhr, textStatus));
            err.status = xhr.status;
            err.xhr = xhr;
            if (!opciones.silencioso) { Gre.avisarError(err.message); }
            dfd.reject(err);
        });

        return dfd.promise();
    };

    Gre.get = function (url, datos, opciones) {
        return Gre.request(url, datos, $.extend({ metodo: 'GET' }, opciones || {}));
    };

    /** Un solo lugar donde se decide como se le avisa al usuario. */
    Gre.avisarError = function (mensaje) {
        if (window.Swal) {
            window.Swal.fire({ icon: 'error', title: 'Error', text: mensaje });
        } else {
            window.alert(mensaje);
        }
    };

    Gre.avisarOk = function (mensaje) {
        if (window.Swal) {
            window.Swal.fire({ icon: 'success', title: mensaje, timer: 1600, showConfirmButton: false });
        }
    };

}(window.Gre, window.jQuery));
