/**
 * Formulario de guía — Ingreso y Salida.
 *
 * Reemplaza a articulo.js, storage.js y cargar_de_guias.js, que juntos sumaban
 * 559 lineas repartidas en tres archivos que se comunicaban por el DOM: el
 * buscador guardaba el articulo en once inputs ocultos y el "agregar" los leia
 * de vuelta. Aqui el estado es un objeto y el HTML se deriva de el.
 *
 * Extiende greDetalleGuia() con: busqueda de articulos, borrador automatico y
 * carga desde otra guia.
 *
 * Ingreso y Salida comparten todo esto. Lo unico que cambia es de donde sale
 * el precio de referencia (costo en compras, precio de venta en salidas) y si
 * hay que vigilar el stock, y eso ya viene resuelto desde el servidor. Se
 * parametriza con `tipo` para no tener dos copias del mismo archivo.
 */
window.greGuiaForm = function (config) {
    config = config || {};

    var base = window.greDetalleGuia(config);

    var extension = {

        // ---------------------------------------------------------------
        // Búsqueda de artículos
        // ---------------------------------------------------------------
        busqueda: {
            texto: '',
            tipo: 1,          // 1 = código de barras · 2 = nombre
            resultados: [],
            cargando: false,
            abierto: false,
            activo: -1,       // índice resaltado, para navegar con flechas
            mensaje: ''
        },

        _debounce: null,

        /** El almacén y la lista de precios salen de los selects de la cabecera. */
        contexto: function () {
            var almacen = document.getElementById('codalmacen');
            var lista   = document.getElementById('codlistaprecio');
            var opt     = almacen && almacen.selectedOptions ? almacen.selectedOptions[0] : null;
            return {
                codalmacen:     almacen ? almacen.value : 1,
                codlistaprecio: lista ? lista.value : 1,
                codestacion:    opt ? (opt.getAttribute('data-codestacion') || 1) : 1
            };
        },

        /**
         * Se dispara al escribir. Un lector de código de barras teclea muy
         * rápido y termina con Enter, así que el debounce es corto: si esperara
         * más, el Enter llegaría antes que la búsqueda.
         */
        alEscribir: function () {
            var self = this;
            clearTimeout(this._debounce);

            var texto = (this.busqueda.texto || '').trim();
            if (texto.length < (this.busqueda.tipo === 1 ? 3 : 3)) {
                this.busqueda.resultados = [];
                this.busqueda.abierto = false;
                this.busqueda.mensaje = '';
                return;
            }

            this._debounce = setTimeout(function () { self.buscar(); }, 220);
        },

        /** Un codigo de barras: solo digitos y suficientemente largo. */
        pareceCodigoBarras: function (texto) {
            return /^[0-9]{8,}$/.test(texto);
        },

        _consultar: function (texto, tipo) {
            var ctx = this.contexto();
            return window.Gre.get(this.rutas.listarArticulos, {
                term: texto,
                tipo_busqueda_articulo: tipo,
                codalmacen: ctx.codalmacen,
                codlistaprecio: ctx.codlistaprecio,
                codestacion: ctx.codestacion
            }, { silencioso: true });
        },

        buscar: function () {
            var self = this;
            var texto = (this.busqueda.texto || '').trim();
            if (!texto) { return window.jQuery.Deferred().resolve().promise(); }

            this.busqueda.cargando = true;
            this.busqueda.mensaje = '';

            var listo = window.jQuery.Deferred();

            function aplicar(items, tipoUsado) {
                self.busqueda.resultados = items || [];
                self.busqueda.abierto = true;
                self.busqueda.activo = self.busqueda.resultados.length ? 0 : -1;
                self.busqueda.mensaje = self.busqueda.resultados.length
                    ? ''
                    : 'Sin resultados para "' + texto + '".';
                // Si el codigo de barras aparecio estando en otro modo, dejar
                // el selector donde de verdad esta buscando.
                if (items && items.length && tipoUsado !== self.busqueda.tipo) {
                    self.busqueda.tipo = tipoUsado;
                }
                self.busqueda.cargando = false;
                listo.resolve();
            }

            this._consultar(texto, this.busqueda.tipo)
                .done(function (resp) {
                    var items = (resp && resp.items) || [];

                    // El escaner no sabe en que modo esta el selector. Si no
                    // hubo resultados y lo tecleado parece un codigo de barras,
                    // se reintenta como tal en vez de dejar al almacenero
                    // mirando una lista vacia.
                    if (!items.length && self.pareceCodigoBarras(texto) && self.busqueda.tipo !== 1) {
                        self._consultar(texto, 1)
                            .done(function (r2) { aplicar((r2 && r2.items) || [], 1); })
                            .fail(function () { aplicar([], self.busqueda.tipo); });
                        return;
                    }
                    aplicar(items, self.busqueda.tipo);
                })
                .fail(function (err) {
                    self.busqueda.resultados = [];
                    self.busqueda.abierto = true;
                    self.busqueda.mensaje = err.message;
                    self.busqueda.cargando = false;
                    listo.resolve();
                });

            return listo.promise();
        },

        /**
         * Enter en el buscador.
         *
         * Con lector de código de barras el resultado es uno solo y se agrega
         * sin que el usuario toque nada más: es el caso que se repite decenas
         * de veces por guía. Si hay varios, Enter toma el resaltado.
         */
        alPresionarEnter: function () {
            var self = this;
            clearTimeout(this._debounce);

            if (this.busqueda.resultados.length === 1) {
                return this.elegir(this.busqueda.resultados[0]);
            }
            if (this.busqueda.abierto && this.busqueda.activo >= 0) {
                return this.elegir(this.busqueda.resultados[this.busqueda.activo]);
            }
            // Todavía no hay resultados: buscar y, si es único, agregarlo.
            return this.buscar().done(function () {
                if (self.busqueda.resultados.length === 1) {
                    self.elegir(self.busqueda.resultados[0]);
                }
            });
        },

        mover: function (delta) {
            if (!this.busqueda.abierto || !this.busqueda.resultados.length) { return; }
            var n = this.busqueda.resultados.length;
            this.busqueda.activo = (this.busqueda.activo + delta + n) % n;
        },

        cerrarBusqueda: function () {
            this.busqueda.abierto = false;
            this.busqueda.activo = -1;
        },

        /** Agrega el artículo elegido al detalle. */
        elegir: function (item) {
            if (!item) { return; }
            var self = this;
            this.cerrarBusqueda();

            return this.agregar({
                id:                 item.id,
                descripcion:        item.descripcion,
                codigo_barra:       item.codigo_barra,
                cod_plu:            item.cod_plu || item.id,
                precio_publico:     item.precio_publico,
                precio_sin_igv:     item.precio_sin_igv,
                precio_visual:      item.costo_articulo || item.precio_sin_igv,
                costo_articulo:     item.costo_articulo,
                peso:               item.peso,
                cod_unidad:         item.cod_unidad,
                desc_unidad_medida: item.desc_unidad_medida,
                sigla_umfe:         item.sigla_umfe,
                tipo_igv:           item.tipo_igv,
                stock:              item.stock,
                afecto:             item.afecto
            }).done(function (resp) {
                self.busqueda.texto = '';
                self.busqueda.resultados = [];
                if (resp && typeof resp.validarStock === 'boolean') {
                    self.validarStock = resp.validarStock;
                }
            });
        },

        // ---------------------------------------------------------------
        // Borrador automático
        // ---------------------------------------------------------------
        borrador: { hay: false, fecha: null },

        tipo: config.tipo || 'ingreso',

        _claveBorrador: function () {
            return 'gre.borrador.' + this.tipo;
        },

        /**
         * Guarda en silencio.
         *
         * Antes esto abría un modal bloqueante en CADA carga de página
         * preguntando "¿Desea cargarlos?" con un botón que decía "seran
         * borrados". Ahora el borrador se guarda solo y, si existe, aparece un
         * aviso discreto que el usuario puede ignorar.
         */
        guardarBorrador: function () {
            try {
                if (!this.lineas.length) {
                    localStorage.removeItem(this._claveBorrador());
                    return;
                }
                localStorage.setItem(this._claveBorrador(), JSON.stringify({
                    fecha: new Date().toISOString(),
                    lineas: this.lineas,
                    baseCalculo: this.baseCalculo
                }));
            } catch (e) {
                // Sin localStorage (modo privado, cuota llena) la pantalla
                // sigue funcionando: el borrador es una comodidad, no un
                // requisito.
            }
        },

        detectarBorrador: function () {
            try {
                var crudo = localStorage.getItem(this._claveBorrador());
                if (!crudo) { return; }
                var d = JSON.parse(crudo);
                if (!d || !d.lineas || !d.lineas.length) { return; }
                if (this.lineas.length) { return; }   // la guía ya trae detalle
                this.borrador.hay = true;
                this.borrador.fecha = d.fecha;
            } catch (e) {
                this.borrador.hay = false;
            }
        },

        restaurarBorrador: function () {
            try {
                var d = JSON.parse(localStorage.getItem(this._claveBorrador()));
                this.lineas = d.lineas || [];
                if (typeof d.baseCalculo === 'number') { this.baseCalculo = d.baseCalculo; }
            } catch (e) { /* nada que restaurar */ }
            this.borrador.hay = false;
        },

        descartarBorrador: function () {
            try { localStorage.removeItem(this._claveBorrador()); } catch (e) {}
            this.borrador.hay = false;
        },

        borradorRelativo: function () {
            if (!this.borrador.fecha) { return ''; }
            var min = Math.round((Date.now() - new Date(this.borrador.fecha).getTime()) / 60000);
            if (min < 1)   { return 'hace unos segundos'; }
            if (min < 60)  { return 'hace ' + min + ' min'; }
            var h = Math.round(min / 60);
            if (h < 24)    { return 'hace ' + h + (h === 1 ? ' hora' : ' horas'); }
            return 'hace ' + Math.round(h / 24) + ' días';
        },

        // ---------------------------------------------------------------
        // Stock (solo salidas)
        // ---------------------------------------------------------------
        validarStock: !!config.validarStock,

        /**
         * ¿Esta línea pide más de lo que hay?
         *
         * Se avisa, no se bloquea: el almacenero suele saber de stock que el
         * DataMart todavía no refleja, y bloquear la carga por eso detiene el
         * despacho. La advertencia queda visible en la fila.
         */
        excedeStock: function (l) {
            if (!this.validarStock || this.tipo !== 'salida') { return false; }
            var stock = Number(l.stock);
            if (!isFinite(stock)) { return false; }
            return (Number(l.cantidad) || 0) > stock;
        },

        get lineasSinStock() {
            var self = this;
            return this.lineas.filter(function (l) { return self.excedeStock(l); }).length;
        },

        // ---------------------------------------------------------------
        // Arranque
        // ---------------------------------------------------------------
        init: function () {
            var self = this;
            window.greDetalle = this;   // puente para el JS que aún no se migró

            this.detectarBorrador();

            // Guardar el borrador cuando cambie el detalle, sin escribir en
            // cada tecla.
            this.$watch('lineas', function () {
                clearTimeout(self._guardado);
                self._guardado = setTimeout(function () { self.guardarBorrador(); }, 600);
            });

            this.$nextTick(function () { self.volverAlBuscador(); });
        }
    };

    // Object.assign EJECUTA los getters al copiarlos, y `get lineasSinStock`
    // hace this.lineas.filter(...) sobre un objeto que todavia no tiene
    // lineas: la expresion del x-data reventaba y Alpine dejaba el scope
    // vacio, sin decir nada. defineProperties copia los descriptores, asi que
    // los getters siguen siendo getters.
    Object.defineProperties(base, Object.getOwnPropertyDescriptors(extension));
    return base;
};

/** Alias por pantalla, para que la vista diga lo que es. */
window.greGuiaIngreso = function (config) {
    return window.greGuiaForm(Object.assign({ tipo: 'ingreso' }, config || {}));
};

window.greGuiaSalida = function (config) {
    config = config || {};
    var comp = window.greGuiaForm(Object.assign({ tipo: 'salida' }, config));

    // Ubigeos y almacenes solo existen en Salida. Se componen aqui para que
    // Ingreso no cargue con estado que no usa.
    if (window.greGuiaUbigeos) {
        var ubi = window.greGuiaUbigeos(config);

        // El init del formulario ya hace lo suyo; se encadena el de ubigeos en
        // vez de reemplazarlo.
        var initForm = comp.init;
        ubi.init = function () {
            initForm.call(this);
            this.iniciarUbigeos(config.ubigeoInicial);
        };

        // defineProperties y no Object.assign: assign ejecuta los getters al
        // copiarlos y eso ya nos dejo un scope vacio sin aviso una vez.
        Object.defineProperties(comp, Object.getOwnPropertyDescriptors(ubi));
    }

    return comp;
};
