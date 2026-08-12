/*
 * Pruebas del núcleo numérico (core/numeros.js).
 *
 * Se ejecutan con:   node pruebas/numeros.test.js
 * Sin dependencias: no hay framework, solo Node.
 *
 * Cada caso de "regresión" documenta un bug que YA ocurrió en producción.
 * Si alguno vuelve a fallar, es que el bug volvió.
 */
const N = require('../core/numeros.js');

let pasadas = 0;
let fallidas = [];

function comprobar(descripcion, obtenido, esperado) {
    if (Object.is(obtenido, esperado)) {
        pasadas++;
    } else {
        fallidas.push(`  ✗ ${descripcion}\n      esperado: ${esperado}\n      obtenido: ${obtenido}`);
    }
}

/* ---------- aCifra: texto colombiano -> número ---------- */
comprobar('entero con separador de miles', N.aCifra('2.500.000'), 2500000);
comprobar('con decimales por coma',        N.aCifra('2.500.000,75'), 2500000.75);
comprobar('sin separadores',               N.aCifra('1500'), 1500);
comprobar('solo decimales',                N.aCifra('0,5'), 0.5);
comprobar('vacio es cero',                 N.aCifra(''), 0);
comprobar('null es cero',                  N.aCifra(null), 0);
comprobar('undefined es cero',             N.aCifra(undefined), 0);
comprobar('texto basura es cero',          N.aCifra('abc'), 0);
comprobar('numero nativo pasa igual',      N.aCifra(1500), 1500);

/* ---------- deBaseDeDatos: crudo de SQL Server -> número ---------- */
comprobar('decimal de SQL Server',         N.deBaseDeDatos('2500000.00'), 2500000);
comprobar('decimal con centavos',          N.deBaseDeDatos('1500.50'), 1500.5);
comprobar('cero de SQL Server',            N.deBaseDeDatos('.00'), 0);
comprobar('null de SQL Server',            N.deBaseDeDatos(null), 0);

/* ---------- REGRESIÓN: el bug de los "00000" ----------
 * Al corregir una declaración se reabrían los totales tomados de la BD.
 * Si el valor crudo "2500000.00" se parseaba como formato colombiano, el
 * punto se eliminaba y quedaba 250000000: x100 en CADA apertura.
 */
comprobar('BD->input no multiplica por 100',
    N.aCifra(N.deBaseDeDatosAInput('2500000.00')), 2500000);
comprobar('BD->input aguanta reaperturas seguidas',
    N.aCifra(N.deBaseDeDatosAInput(N.aCifra(N.deBaseDeDatosAInput('2500000.00')))), 2500000);
comprobar('BD->input con centavos redondea a peso',
    N.aCifra(N.deBaseDeDatosAInput('1500.50')), 1501);
comprobar('el punto de la BD NO es separador de miles',
    N.deBaseDeDatos('2500000.00') === N.aCifra('2.500.000'), true);

/* ---------- REGRESIÓN: el botón "Liquidar" ----------
 * numero() no convertía la coma decimal antes de parseFloat, así que
 * truncaba y la comparación estricta contra el renglón 16 fallaba siempre.
 */
comprobar('la coma decimal no se pierde', N.aCifra('2.350.000,5'), 2350000.5);
comprobar('suma de bases coincide con el total',
    N.aCifra('2.000.000') + N.aCifra('1.000.000'), N.aCifra('3.000.000'));

/* ---------- formatear: número -> texto ---------- */
comprobar('formatea con puntos de miles', N.formatear(2500000), '2.500.000');
comprobar('formatea cero',                N.formatear(0), '0');

/* ---------- ida y vuelta ---------- */
[0, 1, 999, 1000, 2500000, 987654321].forEach(function (v) {
    comprobar('ida y vuelta ' + v, N.aCifra(N.formatear(v)), v);
});

/* ---------- aEntero ---------- */
comprobar('trunca decimales', N.aEntero('1.500,90'), 1500);
comprobar('entero se queda igual', N.aEntero('1.500'), 1500);

/* ---------- resultado ---------- */
console.log(`\n  ${pasadas} pruebas pasaron`);
if (fallidas.length) {
    console.log(`  ${fallidas.length} FALLARON:\n`);
    console.log(fallidas.join('\n'));
    process.exit(1);
}
console.log('  Todo correcto.\n');
