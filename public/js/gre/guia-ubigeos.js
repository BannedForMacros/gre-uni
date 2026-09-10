/**
 * Ubigeos y almacenes de Guía de Salida.
 *
 * Reemplaza a ubigeo.js y almacen.js, que sumaban 140 lineas y se comunicaban
 * por el DOM: el servidor devolvia <option> concatenados y el JavaScript los
 * inyectaba con .html(), leyendo el estado de atributos data-* de cada <select>
 * y de la <option> seleccionada.
 *
 * Aqui las tres listas de cada lado son arrays y los <select> se pintan desde
 * ellos. La cascada departamento -> provincia -> distrito se dispara sola.
 *
 * Se compone dentro de greGuiaSalida(); no crea un scope Alpine propio.
 */
window.greGuiaUbigeos = function (config) {
    config = config || {};

    /** Estado de un lado del traslado (partida o llegada). */
    function lado() {
        return {
            provincias: [],
            distritos: [],
            departamento: '',
            provincia: '',
            distrito: '',
            direccion: '',
            cargando: false
        };
    }

    return {

        ubigeos: {
            // Los departamentos son la MISMA lista para partida y llegada:
            // se piden una sola vez en vez de dos, como hacia el codigo viejo.
            departamentos: [],
            partida: lado(),
            llegada: lado()
        },

        rutasUbigeo: config.rutasUbigeo || {},

        // ---------------------------------------------------------------
        // Carga
        // ---------------------------------------------------------------

        /**
         * Trae un nivel de ubigeos.
         * tipoConsulta: 1 departamento · 2 provincia · 3 distrito
         */
        _pedirUbigeos: function (codigoPadre, tipoConsulta) {
            return window.Gre.request(this.rutasUbigeo.listarUbigeos, {
                codUbigeo: codigoPadre || '',
                tipo_busqueda: tipoConsulta
            }, { silencioso: true });
        },

        cargarDepartamentos: function () {
            var self = this;
            return this._pedirUbigeos('', 1).done(function (resp) {
                self.ubigeos.departamentos = (resp && resp.ubigeos) || [];
            });
        },

        /**
         * Al elegir departamento se recarga la provincia y se limpia el
         * distrito: dejar el distrito anterior colgando de otro departamento
         * era como se guardaban ubigeos incoherentes.
         */
        alCambiarDepartamento: function (nombreLado) {
            var self = this;
            var l = this.ubigeos[nombreLado];

            l.provincias = [];
            l.distritos = [];
            l.provincia = '';
            l.distrito = '';

            if (!l.departamento) { return; }

            l.cargando = true;
            return this._pedirUbigeos(l.departamento, 2)
                .done(function (resp) { l.provincias = (resp && resp.ubigeos) || []; })
                .always(function () { l.cargando = false; });
        },

        alCambiarProvincia: function (nombreLado) {
            var self = this;
            var l = this.ubigeos[nombreLado];

            l.distritos = [];
            l.distrito = '';

            if (!l.provincia) { return; }

            l.cargando = true;
            return this._pedirUbigeos(l.provincia, 3)
                .done(function (resp) { l.distritos = (resp && resp.ubigeos) || []; })
                .always(function () { l.cargando = false; });
        },

        // ---------------------------------------------------------------
        // Almacenes
        // ---------------------------------------------------------------

        /**
         * Al elegir un almacen se rellena el ubigeo y la direccion del lado que
         * corresponda: tipo 1 = partida, tipo 2 = llegada.
         *
         * El ubigeo y la direccion viajan en la <option> del almacen porque el
         * catalogo ya los trae; de ahi salen los tres niveles.
         */
        alCambiarAlmacen: function (idSelect) {
            var self = this;
            var sel = document.getElementById(idSelect);
            if (!sel) { return; }

            var opt = sel.selectedOptions ? sel.selectedOptions[0] : null;
            if (!opt) { return; }

            var tipo = parseInt(sel.getAttribute('data-almacen_tipo'), 10) || 1;

            return window.Gre.request(this.rutasUbigeo.getUbigeosPorAlmacen, {
                tipo: tipo,
                ubigeo: opt.getAttribute('data-ubigeo') || '',
                direccion: opt.getAttribute('data-direccion') || ''
            }, { silencioso: true }).done(function (resp) {
                if (!resp) { return; }
                var nombreLado = (resp.tipo === 2 || resp.tipo === '2') ? 'llegada' : 'partida';
                var l = self.ubigeos[nombreLado];

                self.ubigeos.departamentos = resp.departamentos || self.ubigeos.departamentos;
                l.provincias   = resp.provincias || [];
                l.distritos    = resp.distritos  || [];
                l.departamento = (resp.seleccion && resp.seleccion.departamento) || '';
                l.provincia    = (resp.seleccion && resp.seleccion.provincia) || '';
                l.distrito     = (resp.seleccion && resp.seleccion.distrito) || '';
                l.direccion    = resp.direccion || '';
            });
        },

        /**
         * Arranque de la parte de ubigeos.
         *
         * Se llama desde el init() del componente principal. Si la guia ya
         * traia ubigeos guardados (caso "continuar"), se reconstruye la cascada
         * para que los tres selects muestren lo que corresponde.
         */
        iniciarUbigeos: function (inicial) {
            var self = this;
            inicial = inicial || {};

            return this.cargarDepartamentos().done(function () {
                ['partida', 'llegada'].forEach(function (nombreLado) {
                    var guardado = inicial[nombreLado];
                    if (!guardado || !guardado.distrito) { return; }

                    var l = self.ubigeos[nombreLado];
                    l.distrito     = guardado.distrito;
                    l.provincia    = guardado.distrito.substring(0, 4);
                    l.departamento = guardado.distrito.substring(0, 2);
                    l.direccion    = guardado.direccion || '';

                    // Rehidratar provincia y distrito sin limpiar lo elegido.
                    self._pedirUbigeos(l.departamento, 2).done(function (r) {
                        l.provincias = (r && r.ubigeos) || [];
                    });
                    self._pedirUbigeos(l.provincia, 3).done(function (r) {
                        l.distritos = (r && r.ubigeos) || [];
                    });
                });
            });
        }
    };
};
