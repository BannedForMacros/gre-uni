/**
 * Pruebas de la cascada de ubigeos.
 * Se corre con:  node tests/js/guia-ubigeos.test.js
 */
global.window = global;

// jQuery Deferred minimo: el componente lo usa a traves de window.Gre.
var respuestas = {};
global.jQuery = {
    Deferred: function () {
        var cbs = [], cbsAlways = [], resuelto = false, valor;
        var d = {
            resolve: function (v) {
                resuelto = true; valor = v;
                cbs.forEach(function (f) { f(v); });
                cbsAlways.forEach(function (f) { f(); });
                return d;
            },
            done: function (f) { if (resuelto) { f(valor); } else { cbs.push(f); } return d; },
            always: function (f) { if (resuelto) { f(); } else { cbsAlways.push(f); } return d; },
            fail: function () { return d; },
            promise: function () { return d; }
        };
        return d;
    }
};

global.document = { getElementById: function () { return null; } };

window.Gre = {
    request: function (url, datos) {
        var d = global.jQuery.Deferred();
        var clave = url + '|' + datos.tipo_busqueda;
        d.resolve(respuestas[clave] || { ubigeos: [] });
        return d;
    }
};

require('../../public/js/gre/guia-ubigeos.js');

var fallos = 0, total = 0;
function assert(c, n, extra) {
    total++;
    if (c) { console.log('  OK    ' + n); }
    else { fallos++; console.log('  FALLA ' + n + (extra ? '  -> ' + extra : '')); }
}
function eq(a, b, n) { assert(a === b, n, 'esperado ' + b + ', obtenido ' + a); }

var RUTA = '/listarUbigeos';
function comp() {
    return window.greGuiaUbigeos({ rutasUbigeo: { listarUbigeos: RUTA, getUbigeosPorAlmacen: '/almacen' } });
}

console.log('\n--- carga de departamentos ---');
respuestas[RUTA + '|1'] = { ubigeos: [{ codUbigeo: '01', descripcion: 'AMAZONAS' }, { codUbigeo: '15', descripcion: 'LIMA' }] };
var c = comp();
c.cargarDepartamentos();
eq(c.ubigeos.departamentos.length, 2, 'trae los departamentos');
eq(c.ubigeos.departamentos[1].descripcion, 'LIMA', 'con su descripcion');

console.log('\n--- la lista de departamentos es UNA sola para ambos lados ---');
assert(c.ubigeos.partida.provincias.length === 0, 'partida arranca sin provincias');
assert(c.ubigeos.llegada.provincias.length === 0, 'llegada arranca sin provincias');
assert(!('departamentos' in c.ubigeos.partida), 'no se duplica la lista por lado');

console.log('\n--- cascada departamento -> provincia ---');
respuestas[RUTA + '|2'] = { ubigeos: [{ codUbigeo: '1501', descripcion: 'LIMA' }] };
c.ubigeos.partida.departamento = '15';
c.alCambiarDepartamento('partida');
eq(c.ubigeos.partida.provincias.length, 1, 'carga las provincias del departamento');

console.log('\n--- cascada provincia -> distrito ---');
respuestas[RUTA + '|3'] = { ubigeos: [{ codUbigeo: '150101', descripcion: 'LIMA' }, { codUbigeo: '150102', descripcion: 'ANCON' }] };
c.ubigeos.partida.provincia = '1501';
c.alCambiarProvincia('partida');
eq(c.ubigeos.partida.distritos.length, 2, 'carga los distritos de la provincia');

console.log('\n--- cambiar de departamento limpia lo de abajo ---');
// Dejar un distrito colgando de otro departamento era como se guardaban
// ubigeos incoherentes.
c.ubigeos.partida.distrito = '150102';
respuestas[RUTA + '|2'] = { ubigeos: [] };
c.ubigeos.partida.departamento = '01';
c.alCambiarDepartamento('partida');
eq(c.ubigeos.partida.provincia, '', 'limpia la provincia');
eq(c.ubigeos.partida.distrito, '', 'limpia el distrito');
eq(c.ubigeos.partida.distritos.length, 0, 'y vacia la lista de distritos');

console.log('\n--- cambiar de provincia limpia solo el distrito ---');
c.ubigeos.llegada.departamento = '15';
c.ubigeos.llegada.provincia = '1501';
c.ubigeos.llegada.distrito = '150101';
respuestas[RUTA + '|3'] = { ubigeos: [] };
c.ubigeos.llegada.provincia = '1502';
c.alCambiarProvincia('llegada');
eq(c.ubigeos.llegada.distrito, '', 'limpia el distrito');
eq(c.ubigeos.llegada.departamento, '15', 'pero conserva el departamento');

console.log('\n--- los dos lados son independientes ---');
c = comp();
respuestas[RUTA + '|2'] = { ubigeos: [{ codUbigeo: '1501', descripcion: 'LIMA' }] };
c.ubigeos.partida.departamento = '15';
c.alCambiarDepartamento('partida');
eq(c.ubigeos.partida.provincias.length, 1, 'partida cargo sus provincias');
eq(c.ubigeos.llegada.provincias.length, 0, 'llegada no se toco');

console.log('\n' + (fallos === 0 ? 'OK (' + total + ' pruebas)' : fallos + ' de ' + total + ' FALLARON'));
process.exit(fallos === 0 ? 0 : 1);
