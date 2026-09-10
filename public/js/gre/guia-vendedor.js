/**
 * Selector de vendedor de la cabecera — Ingreso y Salida.
 *
 * getVendedor() devolvia los <option> ya armados en PHP y esto era un
 * $('#vendedor_id').html(response.options). Dos consecuencias reales: un
 * apellido con apostrofe rompia el atributo -iba entre comillas simples- y la
 * opcion quedaba sin nombre, y el nombre del vendedor solo existia en un
 * data-* del DOM, asi que al guardar habia que volver a rascarlo de la
 * <option> seleccionada.
 *
 * Aqui la lista es estado y el <select> se deriva de el con x-for.
 */
window.greVendedor = function (config) {
    config = config || {};

    return {

        ruta: config.ruta || '',

        codigo: config.codigo != null ? String(config.codigo) : '',
        vendedores: config.vendedores || [],
        seleccionado: config.seleccionado != null ? String(config.seleccionado) : '',

        buscando: false,
        mensaje: '',

        buscar: function () {
            var self = this;
            var codigo = (this.codigo || '').trim();

            if (!codigo) {
                this.mensaje = 'Indique un codigo de vendedor.';
                return;
            }

            this.buscando = true;
            this.mensaje = '';

            window.Gre.request(this.ruta, { vendedor_codigo: codigo }, { silencioso: true })
                .done(function (resp) {
                    var lista = (resp && resp.vendedores) || [];

                    // Un codigo mal tecleado dejaba el <select> vacio y se
                    // perdia el vendedor que ya estaba elegido. Si la busqueda
                    // no trae nada se avisa y se deja la lista como estaba.
                    if (!lista.length) {
                        self.mensaje = 'Ningun vendedor con el codigo ' + codigo + '.';
                        return;
                    }

                    self.vendedores = lista;
                    self.seleccionado = String(lista[0].codigo);
                })
                .fail(function (err) {
                    self.mensaje = err.message;
                })
                .always(function () {
                    self.buscando = false;
                });
        },

        /**
         * El store guarda el nombre, no solo el codigo. Se publica en un input
         * oculto para que entre solo en el FormData del formulario, en vez de
         * que cada pantalla lo lea del DOM por su cuenta.
         */
        nombreVendedor: function () {
            var seleccionado = this.seleccionado;

            var elegido = this.vendedores.filter(function (v) {
                return String(v.codigo) === String(seleccionado);
            })[0];

            return elegido ? elegido.nombre : '';
        }
    };
};
