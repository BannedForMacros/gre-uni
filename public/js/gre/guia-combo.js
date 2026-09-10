/**
 * Buscador con resultados, reutilizable.
 *
 * POR QUE EXISTE
 *
 * En la misma pantalla convivian dos buscadores con aspecto y comportamiento
 * distintos: el de articulos, escrito con Alpine, y el de proveedor, que era un
 * select2 de jQuery. Distinto foco, distinta lista, distinto teclado. Para
 * alguien que carga guias todo el dia eso es tener que aprender dos veces la
 * misma tarea, y ademas select2 se traia su propio CSS que no coincide con el
 * resto de la pantalla.
 *
 * Este componente cubre el caso general -escribir, elegir de una lista, dejarlo
 * marcado-. El de articulos NO se reemplaza por este: tiene reglas propias que
 * aqui no pintan nada (reintento como codigo de barras, agregar al detalle en
 * vez de seleccionar, control de stock).
 *
 * Teclado, que es como se trabaja aqui:
 *   escribir  -> busca solo, tras una pausa
 *   flechas   -> mueve por los resultados
 *   Enter     -> elige el resaltado
 *   Escape    -> cierra sin elegir
 *
 * Sin build: Alpine 3 y ES5, como el resto de public/js/gre/.
 */
window.greCombo = function (config) {
    'use strict';

    config = config || {};

    return {
        // --- Configuracion ---------------------------------------------
        ruta:        config.ruta || '',
        minimo:      config.minimo || 3,
        parametros:  config.parametros || {},
        // Como se saca de cada resultado lo que se muestra y lo que se guarda.
        // Se pasan desde la vista para que este archivo no sepa de proveedores.
        campoId:     config.campoId || 'id',
        campoTexto:  config.campoTexto || 'text',

        // --- Estado ----------------------------------------------------
        texto:       '',
        resultados:  [],
        activo:      0,
        abierto:     false,
        cargando:    false,
        mensaje:     '',
        elegido:     config.elegido || null,

        _timer: null,

        // --- Busqueda --------------------------------------------------

        alEscribir: function () {
            var self = this;

            // Se espera una pausa antes de consultar. Sin esto, escribir
            // "DISTRIBUIDORA" lanza catorce peticiones y las respuestas
            // llegan desordenadas: la lista parpadea con resultados viejos.
            window.clearTimeout(this._timer);

            if (this.texto.trim().length < this.minimo) {
                this.resultados = [];
                this.abierto = false;
                return;
            }

            this._timer = window.setTimeout(function () { self.buscar(); }, 250);
        },

        buscar: function () {
            var self = this;
            var termino = this.texto.trim();

            if (!this.ruta || termino.length < this.minimo) { return; }

            this.cargando = true;
            this.abierto = true;
            this.mensaje = 'Buscando...';

            var datos = window.jQuery.extend({ term: termino }, this._parametrosResueltos());

            window.Gre.get(this.ruta, datos, { silencioso: true })
                .done(function (resp) {
                    self.resultados = (resp && resp.items) || [];
                    self.activo = 0;
                    self.mensaje = self.resultados.length
                        ? ''
                        : 'Sin resultados para "' + termino + '".';
                })
                .fail(function (err) {
                    self.resultados = [];
                    self.mensaje = err.message || 'No se pudo consultar.';
                })
                .always(function () {
                    self.cargando = false;
                });
        },

        /**
         * Los parametros pueden venir como funcion para poder leer otro campo
         * de la pantalla en el momento de buscar: el proveedor se busca por
         * razon social, RUC o codigo segun lo que el usuario haya elegido, y
         * ese valor cambia despues de montar el componente.
         */
        _parametrosResueltos: function () {
            var salida = {};

            for (var clave in this.parametros) {
                if (!Object.prototype.hasOwnProperty.call(this.parametros, clave)) { continue; }
                var valor = this.parametros[clave];
                salida[clave] = (typeof valor === 'function') ? valor() : valor;
            }

            return salida;
        },

        // --- Teclado ---------------------------------------------------

        mover: function (paso) {
            if (!this.resultados.length) { return; }

            var siguiente = this.activo + paso;
            if (siguiente < 0) { siguiente = this.resultados.length - 1; }
            if (siguiente >= this.resultados.length) { siguiente = 0; }

            this.activo = siguiente;
        },

        alPresionarEnter: function () {
            if (!this.abierto || !this.resultados.length) { return; }
            this.elegir(this.resultados[this.activo]);
        },

        // --- Seleccion -------------------------------------------------

        elegir: function (item) {
            this.elegido = item;
            this.texto = '';
            this.resultados = [];
            this.abierto = false;
            this.mensaje = '';

            if (typeof config.alElegir === 'function') { config.alElegir(item); }
        },

        limpiar: function () {
            this.elegido = null;
            this.texto = '';
            this.resultados = [];
            this.abierto = false;

            if (typeof config.alElegir === 'function') { config.alElegir(null); }
        },

        cerrar: function () {
            this.abierto = false;
        },

        // --- Lectura para la vista -------------------------------------

        get hayElegido() {
            return this.elegido !== null && this.elegido !== undefined;
        },

        valor: function () {
            return this.hayElegido ? this.elegido[this.campoId] : '';
        },

        etiqueta: function () {
            return this.hayElegido ? this.elegido[this.campoTexto] : '';
        },

        dato: function (campo) {
            return this.hayElegido ? (this.elegido[campo] || '') : '';
        }
    };
};
