/**
 * Pruebas del componente de listado de guias.
 *
 * Se corre con:  node tests/js/guia-listado.test.js
 * Sin dependencias: no hay npm en el servidor del cliente.
 */
global.window = global;

// jQuery minimo: el componente solo usa extend y Deferred.
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
        var cbDone = [], cbFail = [], estado = null, valor;
        var d = {
            resolve: function (v) { estado = 'ok'; valor = v; cbDone.forEach(function (f) { f(v); }); return d; },
            reject:  function (v) { estado = 'no'; valor = v; cbFail.forEach(function (f) { f(v); }); return d; },
            done: function (f) { if (estado === 'ok') { f(valor); } else if (!estado) { cbDone.push(f); } return d; },
            fail: function (f) { if (estado === 'no') { f(valor); } else if (!estado) { cbFail.push(f); } return d; },
            always: function (f) { if (estado) { f(valor); } else { cbDone.push(f); cbFail.push(f); } return d; },
            promise: function () { return d; }
        };
        return d;
    }
};

// Gre simulado: cada prueba decide que responde.
var ultimaPeticion = null;
var peticiones = [];
var respuestaSimulada = { guias: [] };
var fallaSimulada = null;
var avisos = [];

window.Gre = {
    request: function (url, datos) {
        ultimaPeticion = { url: url, datos: datos };
        peticiones.push(ultimaPeticion);
        var d = window.jQuery.Deferred();
        if (fallaSimulada) { d.reject(new Error(fallaSimulada)); } else { d.resolve(respuestaSimulada); }
        return d.promise();
    },
    get: function (url, datos) { return window.Gre.request(url, datos); },
    avisarOk:    function (m) { avisos.push('ok:' + m); },
    avisarError: function (m) { avisos.push('error:' + m); }
};

require('../../public/js/gre/guia-listado.js');

var fallos = 0, total = 0;

function assert(cond, nombre, extra) {
    total++;
    if (cond) { console.log('  OK    ' + nombre); }
    else { fallos++; console.log('  FALLA ' + nombre + (extra ? '  -> ' + extra : '')); }
}
function eq(a, b, nombre) { assert(a === b, nombre, 'esperado ' + b + ', obtenido ' + a); }

function guia(over) {
    return Object.assign({
        id: 1, documento: 'T001-1', serie: 'T001', numero: 1,
        razonSocial: 'PROVEEDOR UNO', fechaEmision: '2026-09-10',
        totalVenta: 100, guiaEstadoId: 1, estadoNombre: 'GENERADA',
        mostrarEliminar: true, mostrarGuardarDatamarket: true, mostrarContinuar: false
    }, over || {});
}

function nuevo(over) {
    return window.greListadoIngreso(Object.assign({
        fechaInicio: '2026-09-01', fechaFin: '2026-09-10',
        rutas: { listar: '/listar', eliminar: '/eliminar', storeDataMart: '/dmk' }
    }, over || {}));
}

// ---------------------------------------------------------------------------

console.log('\n--- rango de fechas coherente ---');
var c = nuevo();
c.filtros.fechaInicio = '2026-09-20';
c.filtros.fechaFin = '2026-09-10';
c.alCambiarFecha('inicio');
eq(c.filtros.fechaFin, '2026-09-20', 'mover el inicio arrastra el fin');

c = nuevo();
c.filtros.fechaInicio = '2026-09-20';
c.filtros.fechaFin = '2026-09-10';
c.alCambiarFecha('fin');
eq(c.filtros.fechaInicio, '2026-09-10', 'mover el fin arrastra el inicio');

c = nuevo();
c.filtros.fechaInicio = '2026-09-01';
c.filtros.fechaFin = '2026-09-10';
c.alCambiarFecha('inicio');
eq(c.filtros.fechaFin, '2026-09-10', 'un rango valido no se toca');

console.log('\n--- carga ---');
respuestaSimulada = { guias: [guia({ id: 1 }), guia({ id: 2 })] };
c = nuevo();
c.cargar();
eq(c.guias.length, 2, 'guarda las guias que llegan');
eq(ultimaPeticion.url, '/listar', 'llama a la ruta de listar');
eq(ultimaPeticion.datos.fecha_inicio, '2026-09-01', 'envia el rango de fechas');
eq(c.cargando, false, 'apaga el indicador al terminar');

console.log('\n--- un fallo del servidor no deja la tabla a medias ---');
fallaSimulada = 'No hay conexion con el servidor.';
c = nuevo();
c.guias = [guia()];
c.cargar();
eq(c.guias.length, 0, 'vacia el listado');
eq(c.error, 'No hay conexion con el servidor.', 'muestra el motivo');
eq(c.cargando, false, 'no queda cargando para siempre');
fallaSimulada = null;

console.log('\n--- filtro rapido, sin volver al servidor ---');
respuestaSimulada = { guias: [
    guia({ id: 1, razonSocial: 'DISTRIBUIDORA NORTE', documento: 'T001-1' }),
    guia({ id: 2, razonSocial: 'COMERCIAL SUR',       documento: 'T001-2' }),
    guia({ id: 3, razonSocial: 'DISTRIBUIDORA ESTE',  documento: 'T002-9' })
]};
c = nuevo();
c.cargar();
c.filtroRapido = 'distribuidora';
eq(c.guiasFiltradas.length, 2, 'filtra por razon social');
c.filtroRapido = 't002';
eq(c.guiasFiltradas.length, 1, 'filtra por documento, sin importar mayusculas');
c.filtroRapido = '';
eq(c.guiasFiltradas.length, 3, 'sin filtro devuelve todo');

console.log('\n--- orden ---');
respuestaSimulada = { guias: [
    guia({ id: 1, numero: 10, totalVenta: 50 }),
    guia({ id: 2, numero: 2,  totalVenta: 300 }),
    guia({ id: 3, numero: 7,  totalVenta: 100 })
]};
c = nuevo();
c.cargar();
c.orden = { campo: 'numero', dir: 'asc' };
eq(c.guiasFiltradas.map(function (g) { return g.numero; }).join(','), '2,7,10', 'numerico ascendente');
c.ordenarPor('numero');
eq(c.orden.dir, 'desc', 'volver a la misma columna invierte');
eq(c.guiasFiltradas.map(function (g) { return g.numero; }).join(','), '10,7,2', 'numerico descendente');
c.ordenarPor('totalVenta');
eq(c.orden.dir, 'asc', 'cambiar de columna arranca ascendente');

console.log('\n--- ordenar no muta el original ---');
c = nuevo();
c.cargar();
var antes = c.guias.map(function (g) { return g.id; }).join(',');
c.ordenarPor('totalVenta');
void c.guiasFiltradas;
eq(c.guias.map(function (g) { return g.id; }).join(','), antes, 'el array de origen queda intacto');

console.log('\n--- paginacion ---');
var muchas = [];
for (var i = 1; i <= 60; i++) { muchas.push(guia({ id: i, numero: i })); }
respuestaSimulada = { guias: muchas };
c = nuevo();
c.cargar();
eq(c.totalPaginas, 3, '60 guias en paginas de 25');
eq(c.guiasPagina.length, 25, 'la primera pagina trae 25');
c.irA(3);
eq(c.guiasPagina.length, 10, 'la ultima trae el resto');
c.irA(99);
eq(c.pagina, 3, 'no pasa de la ultima pagina');
c.irA(0);
eq(c.pagina, 3, 'ni antes de la primera');

console.log('\n--- buscar de nuevo vuelve a la primera pagina ---');
c.irA(3);
c.cargar();
eq(c.pagina, 1, 'no deja al usuario en una pagina que ya no existe');

console.log('\n--- total del listado ---');
respuestaSimulada = { guias: [guia({ id: 1, totalVenta: 10.5 }), guia({ id: 2, totalVenta: 20.25 })] };
c = nuevo();
c.cargar();
eq(c.totalImporte, 30.75, 'suma los importes visibles');
c.filtroRapido = 'no-existe';
eq(c.totalImporte, 0, 'el total respeta el filtro');

console.log('\n--- fecha sin desfase de zona horaria ---');
c = nuevo();
eq(c.fecha('2026-09-10'), '10/09/2026', 'no retrocede un dia');
eq(c.fecha('2026-01-01'), '01/01/2026', 'ni en cambio de anio');
eq(c.fecha(''), '', 'tolera vacio');

console.log('\n--- importes ---');
eq(c.money(1234.5), '1234.50', 'dos decimales');
eq(c.money(null), '0.00', 'null cuenta como cero');

console.log('\n--- los mensajes del backend llegan con etiquetas HTML ---');
eq(c._texto('<b>Guia T001-1</b> eliminada'), 'Guia T001-1 eliminada', 'se muestran como texto');

console.log('\n--- acciones ---');
respuestaSimulada = { guias: [guia()] };
avisos = [];
peticiones = [];
c = nuevo();
c._accion('/dmk', guia({ id: 7 }), { panel_origen: 'index' });
// _accion recarga al terminar, asi que la ultima peticion es la del listado.
var envio = peticiones.filter(function (p) { return p.url === '/dmk'; })[0];
eq(envio.datos.id, 7, 'envia el id de la guia');
eq(envio.datos.panel_origen, 'index', 'y los datos extra');
assert(peticiones.some(function (p) { return p.url === '/listar'; }), 'y recarga el listado despues');
assert(avisos.length > 0 && avisos[0].indexOf('ok:') === 0, 'avisa que salio bien');

console.log('\n--- alias por pantalla ---');
eq(window.greListadoIngreso({ rutas: {} }).tipo, 'ingreso', 'listado de ingreso');
eq(window.greListadoSalida({ rutas: {} }).tipo, 'salida', 'listado de salida');

console.log('\n' + (fallos === 0 ? 'OK (' + total + ' pruebas)' : fallos + ' de ' + total + ' FALLARON'));
process.exit(fallos === 0 ? 0 : 1);
