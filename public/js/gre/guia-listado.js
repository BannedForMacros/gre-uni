/**
 * Listado de guías — Ingreso y Salida.
 *
 * Reemplaza a public/js/guias/{ingreso,salida}/index.js, que juntos sumaban 389
 * lineas casi identicas: mismo filtro de fechas, mismo listar, mismo reenvio a
 * DataMart, copiados dos veces con pequenas diferencias que se fueron
 * desincronizando.
 *
 * El listar() del controller devolvia HTML (una vista parcial) que el JS metia
 * con $('#resultados').html(). Eso ataba el diseno de la tabla al backend y
 * obligaba a re-inicializar DataTables a mano en cada busqueda. Ahora el
 * controller devuelve datos y la tabla se pinta desde el estado.
 *
 * Orden, filtro rapido y paginacion se resuelven aqui: eran lo unico que
 * aportaba DataTables, y mantenerlo obligaba a seguir generando HTML en PHP.
 */
window.greGuiaListado = function (config) {
    config = config || {};

    return {

        tipo: config.tipo || 'ingreso',
        rutas: config.rutas || {},

        filtros: {
            fechaInicio: config.fechaInicio || '',
            fechaFin: config.fechaFin || '',
            serie: '',
            numero: ''
        },

        guias: [],
        cargando: false,
        error: '',

        /** Solo Salida: el estado en SUNAT se pide despues de pintar la tabla. */
        refrescandoEstados: false,

        /** Filtro sobre lo ya traido, sin volver al servidor. */
        filtroRapido: '',

        orden: { campo: 'numero', dir: 'desc' },

        pagina: 1,
        porPagina: 25,

        // ---------------------------------------------------------------
        // Carga
        // ---------------------------------------------------------------

        cargar: function () {
            var self = this;
            this.cargando = true;
            this.error = '';

            return window.Gre.request(this.rutas.listar, {
                fecha_inicio: this.filtros.fechaInicio,
                fecha_fin: this.filtros.fechaFin,
                serie: this.filtros.serie,
                numero: this.filtros.numero
            }, { silencioso: true })
            .done(function (resp) {
                self.guias = (resp && resp.guias) || [];
                self.pagina = 1;
                self.refrescarEstados();
            })
            .fail(function (err) {
                self.guias = [];
                self.error = err.message;
            })
            .always(function () {
                self.cargando = false;
            });
        },

        /**
         * Pide el estado en SUNAT de las guias que siguen sin respuesta.
         *
         * Antes esto lo hacia el propio listar(): una llamada al facturador por
         * guia, en serie, antes de devolver una sola fila. La tabla quedaba en
         * blanco varios segundos por un dato que no es el que el usuario esta
         * mirando. Ahora la tabla se pinta ya y el estado se corrige solo
         * cuando el facturador contesta; si no contesta, las filas siguen ahi
         * con el ultimo estado conocido.
         *
         * Silencioso a proposito: que la consulta de estado falle no es motivo
         * para taparle el listado al usuario con un modal de error.
         */
        refrescarEstados: function () {
            var self = this;

            if (!this.rutas.estadosSunat) { return; }

            var ids = this.guias.filter(function (g) {
                return g.estadoPendiente;
            }).map(function (g) {
                return g.id;
            });

            if (!ids.length) { return; }

            this.refrescandoEstados = true;

            return window.Gre.request(this.rutas.estadosSunat, { ids: ids }, { silencioso: true })
                .done(function (resp) {
                    var cambiadas = (resp && resp.guias) || [];
                    if (!cambiadas.length) { return; }

                    var porId = {};
                    cambiadas.forEach(function (g) { porId[g.id] = g; });

                    // Reasignar el array entero: Alpine repinta la fila y con
                    // ella los botones, que dependen del estado.
                    self.guias = self.guias.map(function (g) {
                        return porId[g.id] || g;
                    });
                })
                .always(function () {
                    self.refrescandoEstados = false;
                });
        },

        /**
         * Mantiene coherente el rango de fechas.
         *
         * Antes esto vivia en un handler sobre .fecha que leia data-tipo del
         * DOM y escribia con $('#fecha_fin').val(), asi que el valor del input
         * y el que se enviaba podian discrepar.
         */
        alCambiarFecha: function (cual) {
            if (!this.filtros.fechaInicio || !this.filtros.fechaFin) { return; }
            if (this.filtros.fechaInicio <= this.filtros.fechaFin) { return; }

            if (cual === 'inicio') {
                this.filtros.fechaFin = this.filtros.fechaInicio;
            } else {
                this.filtros.fechaInicio = this.filtros.fechaFin;
            }
        },

        // ---------------------------------------------------------------
        // Derivados
        // ---------------------------------------------------------------

        get guiasFiltradas() {
            var texto = (this.filtroRapido || '').trim().toLowerCase();
            var lista = this.guias;

            if (texto) {
                lista = lista.filter(function (g) {
                    return (
                        (g.documento || '') + ' ' +
                        (g.razonSocial || '') + ' ' +
                        (g.estadoNombre || '')
                    ).toLowerCase().indexOf(texto) !== -1;
                });
            }

            var campo = this.orden.campo;
            var signo = this.orden.dir === 'asc' ? 1 : -1;

            // slice(): sort muta el array, y ordenar el original haria que
            // Alpine repintara la tabla entera en cada comparacion.
            return lista.slice().sort(function (a, b) {
                var x = a[campo];
                var y = b[campo];
                if (typeof x === 'number' && typeof y === 'number') {
                    return (x - y) * signo;
                }
                x = (x === null || x === undefined) ? '' : String(x);
                y = (y === null || y === undefined) ? '' : String(y);
                return x.localeCompare(y, 'es', { numeric: true }) * signo;
            });
        },

        get totalPaginas() {
            return Math.max(1, Math.ceil(this.guiasFiltradas.length / this.porPagina));
        },

        get guiasPagina() {
            var desde = (this.pagina - 1) * this.porPagina;
            return this.guiasFiltradas.slice(desde, desde + this.porPagina);
        },

        get hayGuias() {
            return this.guiasFiltradas.length > 0;
        },

        get totalImporte() {
            var t = 0;
            this.guiasFiltradas.forEach(function (g) {
                t += Number(g.totalVenta) || 0;
            });
            return Math.round(t * 100) / 100;
        },

        ordenarPor: function (campo) {
            if (this.orden.campo === campo) {
                this.orden.dir = this.orden.dir === 'asc' ? 'desc' : 'asc';
            } else {
                this.orden.campo = campo;
                this.orden.dir = 'asc';
            }
            this.pagina = 1;
        },

        flecha: function (campo) {
            if (this.orden.campo !== campo) { return ''; }
            return this.orden.dir === 'asc' ? '▲' : '▼';
        },

        irA: function (n) {
            if (n < 1 || n > this.totalPaginas) { return; }
            this.pagina = n;
        },

        // ---------------------------------------------------------------
        // Acciones sobre una guía
        // ---------------------------------------------------------------

        /** Confirmacion. Devuelve una promesa que resuelve solo si acepta. */
        confirmar: function (html, textoBoton) {
            var dfd = window.jQuery.Deferred();

            if (window.Swal) {
                window.Swal.fire({
                    html: html,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: textoBoton,
                    cancelButtonText: 'No, cancelar'
                }).then(function (r) {
                    if (r.isConfirmed) { dfd.resolve(); } else { dfd.reject(); }
                });
            } else if (window.confirm(html.replace(/<[^>]+>/g, ''))) {
                dfd.resolve();
            } else {
                dfd.reject();
            }

            return dfd.promise();
        },

        /**
         * Ejecuta una accion y recarga.
         *
         * Antes cada accion repetia el mismo bloque: FormData, $.ajax, Swal con
         * la respuesta y recarga si procede. Eran cuatro copias por pantalla.
         */
        _accion: function (url, guia, extra, opciones) {
            var self = this;
            if (!url) { return; }

            opciones = opciones || {};
            var datos = window.jQuery.extend({ id: guia.id }, extra || {});

            this.cargando = true;
            return window.Gre.request(url, datos, { silencioso: true })
                .done(function (resp) {
                    window.Gre.avisarOk(self._texto(resp && resp.msj) || 'Listo', opciones);
                    self.cargar();
                })
                .fail(function (err) {
                    // Tambien se recarga al fallar: el envio pudo dejar la guia
                    // en otro estado y la fila tiene que decir la verdad.
                    window.Gre.avisarError(err.message);
                    self.cargar();
                });
        },

        /** El backend devuelve mensajes con etiquetas HTML; aqui van como texto. */
        _texto: function (html) {
            if (!html) { return ''; }
            return String(html).replace(/<[^>]+>/g, '').trim();
        },

        eliminar: function (guia) {
            var self = this;
            this.confirmar(
                '¿Seguro de <b>eliminar</b> la guía ' + guia.documento + '?',
                'Sí, eliminar'
            ).done(function () {
                self._accion(self.rutas.eliminar, guia);
            });
        },

        anular: function (guia) {
            var self = this;
            this.confirmar(
                '¿Seguro de <b>anular</b> la guía ' + guia.documento + '?',
                'Sí, anular'
            ).done(function () {
                self._accion(self.rutas.anular, guia);
            });
        },

        reenviarDataMart: function (guia) {
            var self = this;
            this.confirmar(
                '¿Reenviar la guía ' + guia.documento + ' al DataMart?',
                'Sí, reenviar'
            ).done(function () {
                self._accion(self.rutas.storeDataMart, guia, { panel_origen: 'index' }, { persistente: true });
            });
        },

        reenviarFacturador: function (guia) {
            var self = this;
            this.confirmar(
                '¿Reenviar la guía ' + guia.documento + ' al facturador?',
                'Sí, reenviar'
            ).done(function () {
                self._accion(self.rutas.facturacionElectronica, guia, { panel_origen: 'index' }, { persistente: true });
            });
        },

        // ---------------------------------------------------------------
        // Formato
        // ---------------------------------------------------------------

        money: function (n) {
            return (Math.round((Number(n) || 0) * 100) / 100).toFixed(2);
        },

        /**
         * Fecha para mostrar, sin pasar por Date.
         *
         * new Date('2026-09-10') se interpreta como UTC y en Peru (UTC-5)
         * retrocede un dia al formatearla. Con fechas sin hora, partir el texto
         * es correcto y ademas mas barato.
         */
        fecha: function (iso) {
            if (!iso) { return ''; }
            var p = String(iso).substring(0, 10).split('-');
            if (p.length !== 3) { return String(iso); }
            return p[2] + '/' + p[1] + '/' + p[0];
        },

        // ---------------------------------------------------------------
        // Arranque
        // ---------------------------------------------------------------

        init: function () {
            this.cargar();
        }
    };
};

/** Alias por pantalla, para que la vista diga lo que es. */
window.greListadoIngreso = function (config) {
    return window.greGuiaListado(window.jQuery.extend({ tipo: 'ingreso' }, config || {}));
};

window.greListadoSalida = function (config) {
    return window.greGuiaListado(window.jQuery.extend({ tipo: 'salida' }, config || {}));
};
