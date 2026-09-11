/**
 * Pruebas del buscador reutilizable (select y busqueda a la vez).
 *
 * Se corre con:  node tests/js/guia-combo.test.js
 */
global.window = global;
window.setTimeout = function (fn) { fn(); return 0; };   // sin esperas: la pausa se prueba aparte
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

// Gre.get simulado: cada peticion queda pendiente hasta que la prueba la resuelve.
var peticiones = [];
window.Gre = {
    get: function (url, datos) {
        var d = window.jQuery.Deferred();
        peticiones.push({ url: url, datos: datos, d: d });
        return d.promise();
    }
};

require('../../public/js/gre/guia-combo.js');

var total = 0, fallos = 0;
function assert(c, m) { total++; if (c) { console.log('  ok  ' + m); } else { fallos++; console.log('  FALLO  ' + m); } }
function eq(a, b, m) { assert(JSON.stringify(a) === JSON.stringify(b), m + ' (esperado ' + JSON.stringify(b) + ', vino ' + JSON.stringify(a) + ')'); }
function items(n, prefijo) { var r = []; for (var i = 0; i < n; i++) { r.push({ id: (prefijo || 'x') + i, text: (prefijo || 'x') + i }); } return r; }
function nuevo(cfg) { peticiones = []; return window.greCombo(Object.assign({ ruta: '/buscar' }, cfg || {})); }

console.log('\n--- abrir sin texto ---');
var c = nuevo();
c.abrir();
eq(peticiones.length, 1, 'abrir consulta aunque no haya texto');
eq(peticiones[0].datos.term, '', 'con el termino vacio');
assert(c.abierto, 'y la lista queda abierta');
eq(c.mensaje, 'Buscando...', 'mientras llega dice que esta buscando');
peticiones[0].d.resolve({ items: items(20), hayMas: true });
eq(c.resultados.length, 20, 'muestra la primera pagina');
assert(c.hayMas, 'sabe que hay mas');
eq(c.pie, 'Se muestran los primeros 20. Escriba para afinar.', 'y lo dice al pie');
assert(!c.cargando, 'deja de cargar');

console.log('\n--- la primera pagina no se vuelve a pedir ---');
c.cerrar();
c.abrir();
eq(peticiones.length, 1, 'cerrar y abrir no consulta otra vez');
eq(c.resultados.length, 20, 'y muestra lo mismo');
c.texto = 'ab'; c.alEscribir();
eq(peticiones.length, 2, 'con texto si consulta');

console.log('\n--- sin minimo de letras ---');
c = nuevo();
c.texto = 'a'; c.alEscribir();
eq(peticiones.length, 1, 'una sola letra ya consulta');
eq(peticiones[0].datos.term, 'a', 'con esa letra');

console.log('\n--- respuestas desordenadas ---');
c = nuevo();
c.texto = 'DI'; c.buscar();
c.texto = 'DISTRI'; c.buscar();
peticiones[1].d.resolve({ items: [{ id: 2, text: 'DISTRIBUIDORA' }] });
peticiones[0].d.resolve({ items: items(20, 'di'), hayMas: true });
eq(c.resultados.map(function (r) { return r.id; }), [2], 'la respuesta vieja no pisa a la nueva');
assert(!c.hayMas, 'ni su "hay mas"');

console.log('\n--- flecha abajo ---');
c = nuevo();
c.mover(1);
eq(peticiones.length, 1, 'con la lista cerrada la abre');
peticiones[0].d.resolve({ items: items(3) });
eq(c.activo, 0, 'resalta el primero');
c.mover(1);
eq(c.activo, 1, 'con la lista abierta mueve');
c.mover(-1); c.mover(-1);
eq(c.activo, 2, 'y da la vuelta hacia arriba');
c = nuevo(); c.mover(-1);
eq(peticiones.length, 0, 'flecha arriba con la lista cerrada no abre');

console.log('\n--- cambio de modo ---');
var modo = 3;
c = nuevo({ parametros: { tipo: function () { return modo; } } });
c.abrir(); peticiones[0].d.resolve({ items: items(2) });
modo = 2; c.cambioDeModo();
eq(peticiones.length, 2, 'con la lista abierta repite la busqueda');
eq(peticiones[1].datos.tipo, 2, 'con el modo nuevo');
c.cerrar(); c.texto = ''; c.cambioDeModo();
eq(peticiones.length, 2, 'cerrada y sin texto no consulta');

console.log('\n--- catalogos que no listan sin texto ---');
c = nuevo({ ayudaSinTexto: 'Escriba el nombre o RUC del transportista.' });
c.abrir(); peticiones[0].d.resolve({ items: [] });
eq(c.mensaje, 'Escriba el nombre o RUC del transportista.', 'sin texto dice que escribir');
c.texto = 'zzz'; c.buscar(); peticiones[1].d.resolve({ items: [] });
eq(c.mensaje, 'Sin resultados para "zzz".', 'con texto dice que no hubo resultados');

console.log('\n--- error ---');
c = nuevo();
c.abrir(); peticiones[0].d.reject(new Error('No se pudo consultar los proveedores.'));
eq(c.mensaje, 'No se pudo consultar los proveedores.', 'muestra el motivo');
assert(!c.cargando, 'y deja de cargar');

console.log('\n--- minimo configurado ---');
c = nuevo({ minimo: 3 });
c.texto = 'ab'; c.alEscribir();
eq(peticiones.length, 0, 'si una vista pide minimo, se respeta');

console.log('\n--- elegir ---');
var elegido = null;
c = nuevo({ alElegir: function (i) { elegido = i; } });
c.abrir(); peticiones[0].d.resolve({ items: items(20), hayMas: true });
c.mover(1); c.alPresionarEnter();
eq(elegido && elegido.id, 'x1', 'Enter elige el resaltado');
assert(!c.abierto && !c.hayMas && c.pie === '', 'y cierra la lista sin dejar el aviso');
eq(c.etiqueta(), 'x1', 'queda como elegido');

console.log('\n' + (fallos === 0 ? 'OK (' + total + ' pruebas)' : fallos + ' de ' + total + ' FALLARON'));
process.exit(fallos === 0 ? 0 : 1);
