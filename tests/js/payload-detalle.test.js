/**
 * El payload del detalle debe traer TODO lo que los controllers leen.
 *
 * Existe porque detalleParaEnviar() dejo de enviar precio_publico y
 * precio_sin_igv cuando reemplazo al armado por DOM: store() moria con
 * "Undefined property: stdClass::$precio_publico" y no guardaba una sola
 * linea del detalle. La respuesta HTTP era 500 y el usuario perdia la carga.
 *
 * Lee los controllers de verdad, asi que si alguien agrega un $item->campo
 * nuevo, esta prueba falla hasta que el componente lo envie.
 *
 * Uso:  node tests/js/payload-detalle.test.js
 */
global.window = global;
require('../../public/js/gre/guia-detalle.js');

var fs = require('fs');
var path = require('path');
var fallos = 0;

function camposQueLeeElController(archivo, desde, hasta) {
    var txt = fs.readFileSync(path.join(__dirname, '../../app/Http/Controllers/Guia/' + archivo), 'utf8');
    var ini = txt.indexOf(desde);
    var fin = txt.indexOf(hasta, ini);
    var trozo = txt.slice(ini, fin > ini ? fin : ini + 4000);
    var m = trozo.match(/\$item->[a-z_]+/g) || [];
    return Array.from(new Set(m.map(function (x) { return x.replace('$item->', ''); })));
}

var linea = {
    codArticulo: '40978', codigoBarra: '750', codPlu: 'A1', descripcion: 'ART',
    cantidad: 2, precioSinIgv: 10, precioPublico: 12, costoArticulo: 8, peso: 0.5,
    codUnidad: 9, descUnidadMedida: 'UND', siglaUmfe: 'NIU', tipoIgv: 1,
    afectoIgv: true, porcentajeDescuento: 0, bonificacion: false, esConsignado: false
};

var enviado = window.greDetalleGuia({ lineas: [linea] }).detalleParaEnviar()[0];

[['GuiaIngresoController.php', '//registrar detalle', 'actualizar serie'],
 ['GuiaSalidaController.php',  'registrar detalle',   'public function']].forEach(function (par) {
    var lee = camposQueLeeElController(par[0], par[1], par[2]);
    console.log('\n--- ' + par[0] + ' lee ' + lee.length + ' campos ---');
    lee.forEach(function (campo) {
        var ok = Object.prototype.hasOwnProperty.call(enviado, campo);
        if (!ok) { fallos++; console.log('  FALTA  ' + campo); }
        else { console.log('  OK     ' + campo); }
    });
});

console.log('\n' + (fallos === 0
    ? 'OK · el payload cubre todos los campos que leen los controllers'
    : fallos + ' CAMPOS FALTAN en detalleParaEnviar()'));
process.exit(fallos === 0 ? 0 : 1);
