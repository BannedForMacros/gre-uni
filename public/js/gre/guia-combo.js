/**
 * Buscador con resultados, reutilizable: select y busqueda a la vez.
 *
 * POR QUE EXISTE
 *
 * En la misma pantalla convivian dos buscadores con aspecto y comportamiento
 * distintos: el de articulos, escrito con Alpine, y el de proveedor, que era un
 * select2 de jQuery. Este componente cubre el caso general -escribir o abrir la
 * lista, elegir, dejarlo marcado-. El de articulos NO se reemplaza por este:
 * tiene reglas propias (reintento como codigo de barras, agregar al detalle en
 * vez de seleccionar, stock), pero se comporta igual al abrir y al escribir.
 *
 * SIN MINIMO DE LETRAS
 *
 * Antes habia que escribir dos o tres letras para ver algo: al entrar al campo
 * no pasaba nada y no se sabia si el buscador funcionaba. Ahora la lista se
 * abre sin texto con los primeros resultados y consulta desde la primera
 * letra. No satura: el servidor corta en 20 y avisa si hay mas
 * (App\Support\BusquedaCatalogo tiene las mediciones).
 *
 * Teclado, que es como se trabaja aqui:
 *   clic o flecha abajo -> abre la lista con los primeros resultados
 *   escribir            -> filtra solo, tras una pausa
 *   flechas             -> mueve por los resultados
 *   Enter               -> elige el resaltado
 *   Escape              -> cierra sin elegir
 *
 * Sin build: Alpine 3 y ES5, como el resto de public/js/gre/.
 */
window.greCombo = function (config) {
    'use strict';

    config = config || {};

    return {
        // --- Configuracion ---------------------------------------------
        ruta:        config.ruta || '',
        // 0 = consulta desde la primera letra, y sin texto al abrir la lista.
        minimo:      typeof config.minimo === 'number' ? config.minimo : 0,
        parametros:  config.parametros || {},
        campoId:     config.campoId || 'id',
        campoTexto:  config.campoTexto || 'text',
        // Hay catalogos que sin texto no listan nada (transportistas): en vez
        // de un "sin resultados" que parece un fallo, se dice que escribir.
        ayudaSinTexto: config.ayudaSinTexto || 'Escriba para buscar.',

        // --- Estado ----------------------------------------------------
        texto:       '',
        resultados:  [],
        activo:      0,
        abierto:     false,
        cargando:    false,
        mensaje:     '',
        hayMas:      false,
        elegido:     config.elegido || null,

        _timer: null,
        _secuencia: 0,
        _cache: {},

        // --- Busqueda --------------------------------------------------

        /** Clic en el campo o flecha abajo con la lista cerrada. */
        abrir: function () {
            if (this.abierto) { return; }
            this.buscar();
        },

        alEscribir: function () {
            var self = this;

            // Se espera una pausa antes de consultar: escribir "DISTRIBUIDORA"
            // no lanza catorce peticiones.
            window.clearTimeout(this._timer);

            if (this.texto.trim().length < this.minimo) {
                this.resultados = [];
                this.hayMas = false;
                this.abierto = false;
                return;
            }

            this._timer = window.setTimeout(function () { self.buscar(); }, 250);
        },

        /** Cambio de modo (razon social, RUC, codigo): repite si hay algo a la vista. */
        cambioDeModo: function () {
            if (this.abierto || this.texto.trim()) { this.buscar(); }
        },

        buscar: function () {
            var self = this;
            var termino = this.texto.trim();
            var hecho = window.jQuery.Deferred();

            if (!this.ruta || termino.length < this.minimo) { return hecho.resolve().promise(); }

            var datos = window.jQuery.extend({ term: termino }, this._parametrosResueltos());
            var clave = JSON.stringify(datos);
            this.abierto = true;

            // La primera pagina sin texto se pide una vez: abrir y cerrar la
            // lista no vuelve a consultar el DataMart.
            if (!termino && this._cache[clave]) {
                this._aplicar(this._cache[clave], termino);
                return hecho.resolve().promise();
            }

            var numero = ++this._secuencia;
            this.cargando = true;
            if (!this.resultados.length) { this.mensaje = 'Buscando...'; }

            window.Gre.get(this.ruta, datos, { silencioso: true })
                .done(function (resp) {
                    // Escribir rapido lanza varias consultas y pueden volver
                    // desordenadas: solo cuenta la ultima. Sin esto la lista
                    // mostraba los resultados de "DI" despues de "DISTRI".
                    if (numero !== self._secuencia) { return; }
                    if (!termino) { self._cache[clave] = resp; }
                    self._aplicar(resp, termino);
                })
                .fail(function (err) {
                    if (numero !== self._secuencia) { return; }
                    self.resultados = [];
                    self.hayMas = false;
                    self.mensaje = (err && err.message) || 'No se pudo consultar.';
                })
                .always(function () {
                    if (numero === self._secuencia) { self.cargando = false; }
                    hecho.resolve();
                });

            return hecho.promise();
        },

        _aplicar: function (resp, termino) {
            this.resultados = (resp && resp.items) || [];
            this.hayMas = !!(resp && resp.hayMas);
            this.activo = 0;
            this.mensaje = this.resultados.length
                ? ''
                : (termino ? 'Sin resultados para "' + termino + '".' : this.ayudaSinTexto);
        },

        /**
         * Los parametros pueden venir como funcion para leer otro campo de la
         * pantalla en el momento de buscar (el modo de busqueda, por ejemplo).
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
            // Flecha abajo con la lista cerrada la abre, como un select.
            if (!this.abierto) {
                if (paso > 0) { this.abrir(); }
                return;
            }
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
            this.hayMas = false;
            this.abierto = false;
            this.mensaje = '';

            if (typeof config.alElegir === 'function') { config.alElegir(item); }
        },

        limpiar: function () {
            this.elegido = null;
            this.texto = '';
            this.resultados = [];
            this.hayMas = false;
            this.abierto = false;

            if (typeof config.alElegir === 'function') { config.alElegir(null); }
        },

        cerrar: function () {
            this.abierto = false;
        },

        // --- Lectura para la vista -------------------------------------

        get pie() {
            return this.hayMas
                ? 'Se muestran los primeros ' + this.resultados.length + '. Escriba para afinar.'
                : '';
        },

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
