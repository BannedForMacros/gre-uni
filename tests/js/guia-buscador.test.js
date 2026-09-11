/**
 * Pruebas del buscador de articulos de la guia (guia-form.js).
 *
 * Se corre con:  node tests/js/guia-buscador.test.js
 */
global.window = global;
global.document = { getElementById: function () { return null; } };
window.setTimeout = function (fn) { fn(); return 0; };
window.clearTimeout = function () {};

window.jQuery = {
    extend: function () {
        var out = arguments[0] || {};
        for (var i = 1; i < arguments.length; i++) {
            var src = arguments[i] || {};
            for (var k in src) { if (Object.prototype.hasOwnProperty.call(src, k)) { out[k] = src[k]; } }
        }
        return out;
    },
    Deferred: function () {
        var cbDone = [], cbFail = [], cbAlways = [], estado = null, valor;
        var d = {
            resolve: function (v) { if (estado) { return d; } estado = 'ok'; valor = v; cbDone.forEach(function (f) { f(v); }); cbAlways.forEach(function (f) { f(v); }); return d; },
            reject:  function (v) { if (estado) { return d; } estado = 'no'; valor = v; cbFail.forEach(function (f) { f(v); }); cbAlways.forEach(function (f) { f(v); }); return d; },
            done: function (f) { if (estado === 'ok') { f(valor); } else if (!estado) { cbDone.push(f); } return d; },
            fail: function (f) { if (estado === 'no') { f(valor); } else if (!estado) { cbFail.push(f); } return d; },
            always: function (f) { if (estado) { f(valor); } else { cbAlways.push(f); } return d; },
            promise: function () { return d; }
        };
        return d;
    }
};

var peticiones = [];
window.Gre = {
    get: function (url, datos) {
        var d = window.jQuery.Deferred();
        peticiones.push({ url: url, datos: datos, d: d });
        return d.promise();
    },
    request: function () { return window.jQuery.Deferred().promise(); }
};

require('../../public/js/gre/guia-detalle.js');
require('../../public/js/gre/guia-form.js');

var total = 0, fallos = 0;
function assert(c, m) { total++; if (c) { console.log('  ok  ' + m); } else { fallos++; console.log('  FALLO  ' + m); } }
function eq(a, b, m) { assert(JSON.stringify(a) === JSON.stringify(b), m + ' (esperado ' + JSON.stringify(b) + ', vino ' + JSON.stringify(a) + ')'); }
function art(id) { return { id: id, descripcion: 'ART ' + id, codigo_barra: '84365673500' + id }; }
function nuevo() { peticiones = []; return window.greGuiaIngreso({ lineas: [], rutas: { listarArticulos: '/art', agregarItem: '/add' } }); }

console.log('\n--- abrir sin texto explora por descripcion ---');
var f = nuevo();
eq(f.busqueda.tipo, 1, 'arranca en codigo de barras');
f.abrirBusqueda();
eq(peticiones.length, 1, 'abrir consulta sin texto');
eq(peticiones[0].datos.tipo_busqueda_articulo, 4, 'por descripcion, que es lo que lista sin codigo');
eq(peticiones[0].datos.term, '', 'con el termino vacio');
peticiones[0].d.resolve({ items: [art(1), art(2)], hayMas: true });
eq(f.busqueda.tipo, 1, 'y el selector sigue en codigo de barras');
eq(f.busqueda.resultados.length, 2, 'muestra la primera pagina');
assert(f.busqueda.hayMas, 'sabe que hay mas');

console.log('\n--- sin minimo de letras ---');
f = nuevo();
f.busqueda.tipo = 4; f.busqueda.texto = 'e'; f.alEscribir();
eq(peticiones.length, 1, 'una sola letra ya consulta');
f = nuevo();
f.busqueda.texto = ''; f.alEscribir();
eq(peticiones.length, 0, 'borrar todo con la lista cerrada no consulta');

console.log('\n--- el escaner: respuestas desordenadas ---');
f = nuevo();
f.busqueda.texto = '84'; f.buscar();
f.busqueda.texto = '8436567350043'; f.buscar();
peticiones[1].d.resolve({ items: [art(43)] });
peticiones[0].d.resolve({ items: [art(1), art(2), art(3)], hayMas: true });
eq(f.busqueda.resultados.map(function (r) { return r.id; }), [43], 'la respuesta vieja no pisa al codigo escaneado');
assert(!f.busqueda.hayMas, 'ni deja su aviso');

console.log('\n--- reintento como codigo de barras ---');
f = nuevo();
f.busqueda.tipo = 4; f.busqueda.texto = '8436567350043'; f.buscar();
peticiones[0].d.resolve({ items: [] });
eq(peticiones.length, 2, 'sin resultados por descripcion reintenta');
eq(peticiones[1].datos.tipo_busqueda_articulo, 1, 'como codigo de barras');
peticiones[1].d.resolve({ items: [art(43)] });
eq(f.busqueda.tipo, 1, 'y deja el selector donde lo encontro');

console.log('\n--- teclado ---');
f = nuevo();
f.alPresionarEnter();
eq(peticiones.length, 0, 'Enter con el campo vacio y la lista cerrada no hace nada');
f.mover(-1);
eq(peticiones.length, 0, 'flecha arriba con la lista cerrada no abre');
f.mover(1);
eq(peticiones.length, 1, 'flecha abajo con la lista cerrada abre');
peticiones[0].d.resolve({ items: [art(1), art(2)] });
f.mover(1);
eq(f.busqueda.activo, 1, 'con la lista abierta mueve');

console.log('\n' + (fallos === 0 ? 'OK (' + total + ' pruebas)' : fallos + ' de ' + total + ' FALLARON'));
process.exit(fallos === 0 ? 0 : 1);
