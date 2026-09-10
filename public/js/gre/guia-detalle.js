/**
 * Detalle de la guia: el estado vive AQUI, no en el DOM.
 *
 * Antes cada fila guardaba sus datos en 15 atributos data-* de un <tr>, y para
 * calcular un total el JavaScript recorria las filas del HTML leyendo esos
 * atributos. Eso ataba los calculos al orden de las columnas y hacia que una
 * descripcion con apostrofe corrompiera la fila entera.
 *
 * Ahora `lineas` es un array de objetos y el HTML se pinta desde el. Los
 * totales se recalculan solos: no hay que acordarse de llamar a nada.
 *
 * Alpine 3 · sin build · 15 KB. Chrome 109 es el ultimo que soporta Windows 7,
 * y es de 2023: cubre de sobra lo que Alpine necesita.
 */
window.greDetalleGuia = function (config) {
    config = config || {};

    return {
        lineas: config.lineas || [],

        /**
         * Puente hacia el JS que todavia no se migro (busqueda de articulos,
         * series, proveedor). Alpine no expone el componente por si solo, asi
         * que se registra aqui. Cuando esos archivos se migren, este init y la
         * referencia global desaparecen.
         */
        init: function () {
            window.greDetalle = this;
        },

        tasaIgv: typeof config.tasaIgv === 'number' ? config.tasaIgv : 0.18,

        /** 1 = mostrar precios sin IGV · 2 = mostrarlos con IGV. Solo afecta lo
         *  que se ve: los importes que se guardan van SIEMPRE sin IGV. */
        baseCalculo: config.baseCalculo || 1,
        rutas: config.rutas || {},
        guardando: false,

        // ---------- derivados: se recalculan solos ----------

        /**
         * Precio a MOSTRAR segun la base de calculo elegida.
         * Un articulo inafecto se muestra igual en ambas bases.
         */
        precioMostrado: function (l) {
            if (this.baseCalculo === 2 && l.afectoIgv) {
                return this.redondear((Number(l.precioSinIgv) || 0) * (1 + this.tasaIgv));
            }
            return this.redondear(l.precioSinIgv);
        },

        /**
         * Importe de la linea tal como se muestra.
         *
         * DERIVA de importeDeLinea() en vez de recalcularse desde el precio
         * mostrado. Recalcular introducia un centimo de diferencia, porque
         * precioMostrado() redondea a 2 decimales antes de multiplicar:
         *
         *     3 x 14.407         = 43.221  -10%  -> 38.90   (correcto)
         *     3 x redondear(14.407) = 43.23 -10% -> 38.91   (mal)
         *
         * El importe de la fila SIEMPRE tiene que ser lo que esa fila aporta
         * al total, o el usuario suma las filas y no le cuadra.
         */
        importeMostrado: function (l) {
            var base = this.importeDeLinea(l);
            if (this.baseCalculo === 2 && l.afectoIgv) {
                return this.redondear(base * (1 + this.tasaIgv));
            }
            return base;
        },

        importeDeLinea: function (l) {
            var bruto = (Number(l.cantidad) || 0) * (Number(l.precioSinIgv) || 0);
            var desc = bruto * ((Number(l.porcentajeDescuento) || 0) / 100);
            return this.redondear(bruto - desc);
        },

        get valorVenta() {
            var t = 0;
            for (var i = 0; i < this.lineas.length; i++) {
                if (!this.lineas[i].bonificacion) {
                    t += this.importeDeLinea(this.lineas[i]);
                }
            }
            return this.redondear(t);
        },

        get baseAfecta() {
            var t = 0;
            for (var i = 0; i < this.lineas.length; i++) {
                var l = this.lineas[i];
                if (l.afectoIgv && !l.bonificacion) {
                    t += this.importeDeLinea(l);
                }
            }
            return this.redondear(t);
        },

        /** IGV de una linea, redondeado a 2 decimales. */
        igvDeLinea: function (l) {
            if (!l.afectoIgv || l.bonificacion) { return 0; }
            return this.redondear(this.importeDeLinea(l) * this.tasaIgv);
        },

        /**
         * IGV total = SUMA de los IGV de cada linea, no el IGV de la suma.
         *
         * El orden del redondeo importa: sumar las bases y aplicar el IGV una
         * sola vez daba un centimo de diferencia contra el calculo de PHP, y el
         * total en pantalla no cuadraba con el guardado. El XML de SUNAT lleva
         * el IGV por linea y el total de cabecera debe ser exactamente su suma,
         * asi que esta es la regla correcta.
         */
        get montoIgv() {
            var t = 0;
            for (var i = 0; i < this.lineas.length; i++) {
                t += this.igvDeLinea(this.lineas[i]);
            }
            return this.redondear(t);
        },

        get totalVenta() {
            return this.redondear(this.valorVenta + this.montoIgv);
        },

        get totalItems() {
            return this.lineas.length;
        },

        get totalCantidad() {
            var t = 0;
            for (var i = 0; i < this.lineas.length; i++) {
                t += Number(this.lineas[i].cantidad) || 0;
            }
            return this.redondear(t);
        },

        /** Monto de descuento acumulado, para la cabecera. */
        get montoDescuento() {
            var t = 0;
            for (var i = 0; i < this.lineas.length; i++) {
                t += this.descuentoDeLinea(this.lineas[i]);
            }
            return this.redondear(t);
        },

        descuentoDeLinea: function (l) {
            var bruto = (Number(l.cantidad) || 0) * (Number(l.precioSinIgv) || 0);
            return this.redondear(bruto * ((Number(l.porcentajeDescuento) || 0) / 100));
        },

        get pesoTotal() {
            var t = 0;
            for (var i = 0; i < this.lineas.length; i++) {
                t += (Number(this.lineas[i].peso) || 0) * (Number(this.lineas[i].cantidad) || 0);
            }
            return this.redondear(t);
        },

        get hayLineas() {
            return this.lineas.length > 0;
        },

        // ---------- acciones ----------

        agregar: function (articulo) {
            var self = this;
            return window.Gre.request(this.rutas.agregarItem, {
                producto_id: articulo.id,
                descripcion: articulo.descripcion,
                codigo_barra: articulo.codigo_barra,
                cod_plu: articulo.cod_plu,
                precio_publico: articulo.precio_publico,
                precio_sin_igv: articulo.precio_sin_igv,
                precio_visual: articulo.precio_visual,
                costo_articulo: articulo.costo_articulo,
                peso: articulo.peso,
                cod_unidad: articulo.cod_unidad,
                desc_unidad_medida: articulo.desc_unidad_medida,
                sigla_umfe: articulo.sigla_umfe,
                tipo_igv: articulo.tipo_igv,
                items: JSON.stringify(self.lineas)
            }).done(function (resp) {
                self.lineas.push(resp.linea);
                if (resp.igv && typeof resp.igv.tasa === 'number') {
                    self.tasaIgv = resp.igv.tasa;
                }
                self.volverAlBuscador();
            });
        },

        /**
         * Devuelve el foco al buscador y lo deja limpio.
         *
         * El almacenero carga entre 20 y 80 articulos por guia, casi siempre
         * escaneando. Sin esto tiene que tomar el mouse despues de cada uno,
         * que es el gesto que mas veces se repite en la pantalla.
         */
        volverAlBuscador: function () {
            var input = document.getElementById('producto_valor');
            if (!input) { return; }
            input.value = '';
            input.focus();
            input.select();
        },

        quitar: function (indice) {
            this.lineas.splice(indice, 1);
        },

        limpiar: function () {
            this.lineas = [];
        },

        cargarDesdeGuia: function (idGuia) {
            var self = this;
            return window.Gre.request(this.rutas.cargarOtraGuia, { id: idGuia })
                .done(function (resp) {
                    self.lineas = resp.lineas || [];
                    if (resp.igv && typeof resp.igv.tasa === 'number') {
                        self.tasaIgv = resp.igv.tasa;
                    }
                });
        },

        /** Marca o desmarca consignado en todas las lineas a la vez. */
        marcarConsignados: function (valor) {
            for (var i = 0; i < this.lineas.length; i++) {
                this.lineas[i].esConsignado = !!valor;
            }
        },

        /** Lo que se envia al backend al guardar. */
        detalleParaEnviar: function () {
            var self = this;
            return this.lineas.map(function (l, i) {
                return {
                    item: i + 1,
                    codarticulo: l.codArticulo,
                    codigo_barra: l.codigoBarra,
                    descripcion: l.descripcion,
                    cantidad: Number(l.cantidad) || 0,
                    precio: Number(l.precioSinIgv) || 0,
                    importe: self.importeDeLinea(l),
                    porcentaje_descuento: Number(l.porcentajeDescuento) || 0,
                    monto_descuento: self.descuentoDeLinea(l),
                    cod_unidad: l.codUnidad,
                    desc_unidad_medida: l.descUnidadMedida,
                    sigla_umfe: l.siglaUmfe,
                    costo_articulo: l.costoArticulo,

                    // El controller lee estos tres. Faltaban desde que este
                    // metodo reemplazo al armado por DOM, y store() moria con
                    // "Undefined property: stdClass::$precio_publico" antes de
                    // guardar una sola linea del detalle.
                    precio_publico: Number(l.precioPublico) || 0,
                    precio_sin_igv: Number(l.precioSinIgv) || 0,

                    // Ingreso lo lee como peso_unitario y Salida como peso.
                    peso_unitario: Number(l.peso) || 0,
                    peso: Number(l.peso) || 0,
                    tipo_igv: l.tipoIgv,
                    bonificacion: l.bonificacion ? 1 : 0,
                    es_consignado: l.esConsignado ? 1 : 0
                };
            });
        },

        // ---------- utilidades ----------

        redondear: function (n) {
            return Math.round((Number(n) || 0) * 100) / 100;
        },

        money: function (n) {
            return this.redondear(n).toFixed(2);
        }
    };
};
