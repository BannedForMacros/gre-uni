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
 * Alpine 3 · sin build · 15 KB · compatible con Chrome 49+ (techo de Windows 7).
 */
window.greDetalleGuia = function (config) {
    config = config || {};

    return {
        lineas: config.lineas || [],
        tasaIgv: typeof config.tasaIgv === 'number' ? config.tasaIgv : 0.18,
        rutas: config.rutas || {},
        guardando: false,

        // ---------- derivados: se recalculan solos ----------

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
            });
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
                    monto_descuento: self.redondear(
                        (Number(l.cantidad) || 0) * (Number(l.precioSinIgv) || 0) *
                        ((Number(l.porcentajeDescuento) || 0) / 100)
                    ),
                    cod_unidad: l.codUnidad,
                    desc_unidad_medida: l.descUnidadMedida,
                    sigla_umfe: l.siglaUmfe,
                    costo_articulo: l.costoArticulo,
                    peso_unitario: l.peso,
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
