/**
 * Comprueba el filtro de "solo presentadas" de la pantalla Consultar
 * (punto 22 de los cambios pedidos por el cliente).
 *
 * El predicado real vive en icaWebConsultar.js y se apoya en
 * DeclaracionesUI.estado(d).paso. Aqui se prueba contra el modulo de verdad,
 * no contra una copia, para que la prueba se entere si cambian los estados.
 *
 * Se corre con:  node pruebas/declaracionesFiltro.test.js
 */

const fs = require('fs');
const path = require('path');
const vm = require('vm');

// declaraciones.ui.js espera un navegador: se le dan los minimos que toca al
// cargarse. Nada de esto se usa en las funciones que se prueban aqui.
const jqueryFalso = () => ({ on: () => {}, ajaxError: () => {}, hide: () => {}, removeClass: () => {} });
jqueryFalso.ajaxSetup = () => {};

const sandbox = { window: {}, document: { addEventListener: () => {} }, $: jqueryFalso, jQuery: jqueryFalso, console };
sandbox.window.console = console;

const ruta = path.join(__dirname, '..', 'core', 'declaraciones.ui.js');
vm.createContext(sandbox);
vm.runInContext(fs.readFileSync(ruta, 'utf8'), sandbox, { filename: ruta });

const DeclaracionesUI = sandbox.DeclaracionesUI;
if (!DeclaracionesUI) { throw new Error('No se pudo cargar DeclaracionesUI'); }

// Mismo predicado que aplica pintarDeclaracionesFiltradas en Consultar.
const yaPresentada = (d) => DeclaracionesUI.estado(d).paso >= 5;

const casos = [
    { nombre: 'borrador sin firmar',      d: {},                                                        esperado: false },
    { nombre: 'firmada por el declarante', d: { is_signed: 1 },                                          esperado: false },
    { nombre: 'firmada pero falta contador', d: { is_signed: 1, requiere_contador: 1 },                  esperado: false },
    { nombre: 'presentada',               d: { dec_Estado: 2 },                                          esperado: true  },
    { nombre: 'presentada y firmada',     d: { dec_Estado: 2, is_signed: 1 },                            esperado: true  },
    { nombre: 'pagada',                   d: { dec_Pagado: 1 },                                          esperado: true  },
    // SQL Server devuelve estos campos como texto en varios endpoints.
    { nombre: 'presentada con dec_Estado como texto', d: { dec_Estado: '2' },                            esperado: true  },
    { nombre: 'pagada con dec_Pagado como texto',     d: { dec_Pagado: '1' },                            esperado: true  },
];

let fallos = 0;
for (const c of casos) {
    const obtenido = yaPresentada(c.d);
    const ok = obtenido === c.esperado;
    if (!ok) { fallos++; }
    console.log(`  ${ok ? 'OK  ' : 'FALLA'}  ${c.nombre}: se muestra=${obtenido}, esperado=${c.esperado}`);
}

// El conteo tambien se calcula sobre las presentadas, no sobre el total: si se
// comparara contra el total, la pantalla abriria diciendo "Mostrando 3 de 8"
// sin que nadie haya puesto un filtro.
const listado = casos.map(c => c.d);
const presentadas = listado.filter(yaPresentada).length;
const okConteo = presentadas === casos.filter(c => c.esperado).length;
if (!okConteo) { fallos++; }
console.log(`  ${okConteo ? 'OK  ' : 'FALLA'}  conteo de presentadas: ${presentadas}`);

console.log(fallos === 0
    ? `\n${casos.length + 1}/${casos.length + 1} pruebas OK`
    : `\n${fallos} prueba(s) FALLARON`);
process.exit(fallos === 0 ? 0 : 1);
