/**
 * Pruebas del componente de detalle de guia.
 *
 * Se corre con:  node tests/js/guia-detalle.test.js
 * Sin dependencias: no hay npm en el servidor del cliente.
 */
global.window = global;
require('../../public/js/gre/guia-detalle.js');

var fallos = 0, total = 0;

function assert(cond, nombre, extra) {
    total++;
    if (cond) { console.log('  OK    ' + nombre); }
    else { fallos++; console.log('  FALLA ' + nombre + (extra ? '  -> ' + extra : '')); }
}
function eq(a, b, nombre) { assert(a === b, nombre, 'esperado ' + b + ', obtenido ' + a); }

function linea(over) {
    return Object.assign({
        codArticulo: '40978', descripcion: 'ART', cantidad: 1, precioSinIgv: 100,
        porcentajeDescuento: 0, afectoIgv: true, bonificacion: false, esConsignado: false,
        codUnidad: 9, tipoIgv: 1
    }, over || {});
}

console.log('\n--- calculos ---');
var c = window.greDetalleGuia({ lineas: [linea({ cantidad: 2, precioSinIgv: 50 }), linea({ precioSinIgv: 10 })] });
eq(c.valorVenta, 110, 'valorVenta suma las lineas');
eq(c.montoIgv, 19.8, 'IGV al 18%');
eq(c.totalVenta, 129.8, 'total = valor + IGV');

console.log('\n--- inafectos ---');
c = window.greDetalleGuia({ lineas: [linea(), linea({ afectoIgv: false })] });
eq(c.valorVenta, 200, 'el inafecto suma al valor venta');
eq(c.montoIgv, 18, 'pero no paga IGV');

console.log('\n--- descuento por linea ---');
c = window.greDetalleGuia({ lineas: [linea({ cantidad: 2, precioSinIgv: 50, porcentajeDescuento: 10 })] });
eq(c.valorVenta, 90, 'descuento del 10% sobre 100');
eq(c.montoIgv, 16.2, 'el IGV va sobre el importe ya descontado');

console.log('\n--- bonificacion ---');
c = window.greDetalleGuia({ lineas: [linea(), linea({ bonificacion: true })] });
eq(c.valorVenta, 100, 'la linea bonificada no suma');
eq(c.montoIgv, 18, 'ni paga IGV');

console.log('\n--- la tasa es configurable, no esta cableada ---');
eq(window.greDetalleGuia({ lineas: [linea()], tasaIgv: 0.10 }).montoIgv, 10, 'tasa 10%');
eq(window.greDetalleGuia({ lineas: [linea()], tasaIgv: 0 }).montoIgv, 0, 'tasa 0%');

console.log('\n--- consignados en bloque ---');
c = window.greDetalleGuia({ lineas: [linea(), linea()] });
c.marcarConsignados(true);
assert(c.lineas.every(function (l) { return l.esConsignado === true; }), 'marca todas');
c.marcarConsignados(false);
assert(c.lineas.every(function (l) { return l.esConsignado === false; }), 'desmarca todas');

console.log('\n--- el item se genera correlativo, no 1 en todas ---');
c = window.greDetalleGuia({ lineas: [linea(), linea(), linea()] });
var d = c.detalleParaEnviar();
eq(d.map(function (x) { return x.item; }).join(','), '1,2,3', 'items 1,2,3');

console.log('\n--- quitar y limpiar ---');
c = window.greDetalleGuia({ lineas: [linea({ codArticulo: 'A' }), linea({ codArticulo: 'B' })] });
c.quitar(0);
eq(c.lineas.length, 1, 'quitar deja una');
eq(c.lineas[0].codArticulo, 'B', 'quita la correcta');
c.limpiar();
eq(c.lineas.length, 0, 'limpiar vacia');

console.log('\n--- el apostrofe ya no rompe nada ---');
c = window.greDetalleGuia({ lineas: [linea({ descripcion: "L'OREAL SHAMPOO 1/2 LT" })] });
eq(c.detalleParaEnviar()[0].descripcion, "L'OREAL SHAMPOO 1/2 LT", 'descripcion intacta');

console.log('\n' + (fallos === 0 ? 'OK (' + total + ' pruebas)' : fallos + ' de ' + total + ' FALLARON'));
process.exit(fallos === 0 ? 0 : 1);
