/**
 * El segundo botón de pago en línea del listado del ICA: «PSE · Bancolombia»
 * por 1cero1pay (migración 060), simultáneo con el de la pasarela de siempre.
 *
 * Se prueba contra el módulo de verdad (core/declaraciones.ui.js), no contra
 * una copia: el servidor manda pago_en_linea (el de siempre) y pago_en_linea_2
 * (el nuevo) en cada fila, y la pantalla pinta uno, otro, los dos o ninguno.
 *
 * Se corre con:  node pruebas/pago_segundo_boton.test.js
 */

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const jqueryFalso = () => ({ on: () => {}, ajaxError: () => {}, hide: () => {}, removeClass: () => {} });
jqueryFalso.ajaxSetup = () => {};

function cargar(permisos) {
    const sandbox = { window: {}, document: { addEventListener: () => {} }, $: jqueryFalso, jQuery: jqueryFalso, console };
    sandbox.window.console = console;
    // erpPuede: lo que el rol puede hacer (dist/menu.php). Sin él, todo se ofrece.
    if (permisos) { sandbox.erpPuede = (p) => permisos.indexOf(p) !== -1; }
    const ruta = path.join(__dirname, '..', 'core', 'declaraciones.ui.js');
    vm.createContext(sandbox);
    vm.runInContext(fs.readFileSync(ruta, 'utf8'), sandbox, { filename: ruta });
    if (!sandbox.DeclaracionesUI) { throw new Error('No se pudo cargar DeclaracionesUI'); }
    return sandbox.DeclaracionesUI;
}

const UI = cargar(null);
const presentada = { dec_Id: 360, dec_Estado: 2, dec_Pagado: 0, dec_ValorConcepto20: 1261000, is_signed: 1 };
const enlace1 = 'pagar.php?modulo=ica&id=360"';
const enlace2 = 'pagar.php?modulo=ica&id=360&pasarela=101"';

let ok = 0;
let fallos = 0;
function prueba(nombre, condicion) {
    if (condicion) { ok++; } else { fallos++; }
    console.log(`  ${condicion ? 'OK   ' : 'FALLA'} ${nombre}`);
}
const html = (d, ui) => (ui || UI).htmlAcciones(d, 'icaWebConsultar');

let h = html(Object.assign({}, presentada, { pago_en_linea: 1, pago_en_linea_texto: 'Pagar PSE' }));
prueba('sin pago_en_linea_2 (municipio sin 1cero1pay): solo el botón de siempre', h.indexOf(enlace1) !== -1 && h.indexOf('pasarela=101') === -1 && h.indexOf('Bancolombia') === -1);

h = html(Object.assign({}, presentada, { pago_en_linea: 1, pago_en_linea_texto: 'Pagar PSE', pago_en_linea_2: 0, pago_en_linea_2_texto: '' }));
prueba('pago_en_linea_2 = 0: solo el botón de siempre', h.indexOf(enlace1) !== -1 && h.indexOf('pasarela=101') === -1);

h = html(Object.assign({}, presentada, { pago_en_linea: 1, pago_en_linea_texto: 'Pagar PSE', pago_en_linea_2: 1, pago_en_linea_2_texto: 'PSE · Bancolombia' }));
prueba('los dos a la vez: «Pagar PSE» y «PSE · Bancolombia», cada uno con su enlace',
    h.indexOf(enlace1) !== -1 && h.indexOf(enlace2) !== -1 && h.indexOf('>Pagar PSE<') !== -1 && h.indexOf('>PSE · Bancolombia<') !== -1);
prueba('  el de siempre va primero', h.indexOf(enlace1) < h.indexOf(enlace2));
prueba('  el recibo de pago sigue estando', h.indexOf('reciboPago.php?modulo=ICA&id=360') !== -1);

h = html(Object.assign({}, presentada, { pago_en_linea: 0, pago_en_linea_2: '1', pago_en_linea_2_texto: 'PSE · Bancolombia' }));
prueba('solo 1cero1pay (entidad sin otra pasarela; el dato llega como texto): solo «PSE · Bancolombia»',
    h.indexOf(enlace2) !== -1 && h.indexOf(enlace1) === -1);

h = html(Object.assign({}, presentada, { dec_ValorConcepto20: 0, pago_en_linea: 1, pago_en_linea_2: 1 }));
prueba('con total a pagar en $0: ninguno de los dos', h.indexOf('pagar.php') === -1);

h = html(Object.assign({}, presentada, { dec_Pagado: 1, pago_en_linea: 1, pago_en_linea_2: 1 }));
prueba('ya pagada: ninguno de los dos', h.indexOf('pagar.php') === -1);

h = html({ dec_Id: 360, dec_Estado: 1, dec_ValorConcepto20: 1261000, is_signed: 1, pago_en_linea: 1, pago_en_linea_2: 1 });
prueba('sin presentar (firmada): ninguno de los dos', h.indexOf('pagar.php') === -1);

const sinPagar = cargar(['ica.ver', 'ica.corregir']);
h = html(Object.assign({}, presentada, { pago_en_linea: 1, pago_en_linea_2: 1 }), sinPagar);
prueba('un rol sin el permiso ica.pagar: ninguno de los dos', h.indexOf('pagar.php') === -1);

const conPagar = cargar(['ica.ver', 'ica.pagar']);
h = html(Object.assign({}, presentada, { pago_en_linea: 1, pago_en_linea_2: 1 }), conPagar);
prueba('con ica.pagar: los dos', h.indexOf(enlace1) !== -1 && h.indexOf(enlace2) !== -1);

/* ----------------------------------------------------------------------
 * Retención y autorretención (core/retenciones.js). La función que arma las
 * acciones de una fila es privada del módulo: se carga el archivo de verdad y
 * solo se le agrega una salida más para poder llamarla desde aquí.
 * ---------------------------------------------------------------------- */
function cargarRetenciones(permisos) {
    // retenciones.js escapa textos con $('<div>').text(t).html().
    const jq = () => {
        let texto = '';
        const o = { on: () => o, ajaxError: () => o, hide: () => o, removeClass: () => o,
                    text: (t) => { texto = String(t); return o; },
                    html: () => texto.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') };
        return o;
    };
    jq.ajaxSetup = () => {};
    const sandbox = { window: {}, document: { addEventListener: () => {} }, $: jq, jQuery: jq, console };
    if (permisos) { sandbox.erpPuede = (p) => permisos.indexOf(p) !== -1; }
    const ruta = path.join(__dirname, '..', 'core', 'retenciones.js');
    const fuente = fs.readFileSync(ruta, 'utf8');
    const conSalida = fuente.replace(/return \{\s*consultar: consultar,/, 'return { _acciones: accionesRetencion, consultar: consultar,');
    if (conSalida === fuente) { throw new Error('No se encontró la salida de Retenciones para agregarle la función de acciones'); }
    vm.createContext(sandbox);
    vm.runInContext(conSalida + '\n;this.Retenciones = Retenciones;', sandbox, { filename: ruta });
    return sandbox.Retenciones._acciones;
}

const acciones = cargarRetenciones(null);
['RETEICA', 'AUTORRETEICA'].forEach(function (modulo) {
    const cfg = { modulo: modulo, pdf: '../extensiones/x.php' };
    const fila = { id: 7, numero: '2026000002', estadoClave: 'presentada', total: 90000 };
    const e1 = 'pagar.php?modulo=' + modulo + '&id=7"';
    const e2 = 'pagar.php?modulo=' + modulo + '&id=7&pasarela=101"';

    let r = acciones(Object.assign({}, fila, { pago_en_linea: 1, pago_en_linea_texto: 'Pagar PSE' }), cfg);
    prueba(modulo + ': sin pago_en_linea_2, solo el botón de siempre', r.indexOf(e1) !== -1 && r.indexOf('pasarela=101') === -1);

    r = acciones(Object.assign({}, fila, { pago_en_linea: 1, pago_en_linea_texto: 'Pagar PSE', pago_en_linea_2: 1, pago_en_linea_2_texto: 'PSE · Bancolombia' }), cfg);
    prueba(modulo + ': los dos a la vez, el de siempre primero', r.indexOf(e1) !== -1 && r.indexOf(e2) !== -1 && r.indexOf(e1) < r.indexOf(e2)
        && r.indexOf('>PSE · Bancolombia<') !== -1 && r.indexOf('reciboPago.php') !== -1);

    r = acciones(Object.assign({}, fila, { pago_en_linea: 0, pago_en_linea_2: 1 }), cfg);
    prueba(modulo + ': solo 1cero1pay', r.indexOf(e2) !== -1 && r.indexOf(e1) === -1);

    r = acciones(Object.assign({}, fila, { total: 0, pago_en_linea: 1, pago_en_linea_2: 1 }), cfg);
    prueba(modulo + ': con total en $0, ninguno', r.indexOf('pagar.php') === -1);

    r = acciones(Object.assign({}, fila, { estadoClave: 'pagada', pago_en_linea: 1, pago_en_linea_2: 1 }), cfg);
    prueba(modulo + ': pagada, ninguno', r.indexOf('pagar.php') === -1);

    r = acciones(Object.assign({}, fila, { estadoClave: 'firmada', pago_en_linea: 1, pago_en_linea_2: 1 }), cfg);
    prueba(modulo + ': sin presentar, ninguno', r.indexOf('pagar.php') === -1);

    const sinPermiso = cargarRetenciones([modulo.toLowerCase() + '.ver']);
    r = sinPermiso(Object.assign({}, fila, { pago_en_linea: 1, pago_en_linea_2: 1 }), cfg);
    prueba(modulo + ': un rol sin el permiso de pagar, ninguno', r.indexOf('pagar.php') === -1);
});

console.log(fallos === 0 ? `\n${ok}/${ok} pruebas OK` : `\n${fallos} prueba(s) FALLARON`);
process.exit(fallos === 0 ? 0 : 1);
