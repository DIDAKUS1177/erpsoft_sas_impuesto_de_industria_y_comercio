/*
 * Pruebas de la sección «Contacto del municipio» de Municipio y bancos
 * (core/configuracion.js), SIN navegador: se extrae la clase Configuracion y se
 * ejecuta contra un $ y un swal de mentira que anotan lo que la pantalla haría.
 *
 *   node pruebas/contacto_pantalla.test.js
 *
 * Lo que importa comprobar:
 *  - los tres datos se pintan en su sección y NO en la tabla de parámetros
 *    (esa sigue detrás de la contraseña);
 *  - si falta alguna de las tres filas (migración 054 sin aplicar) o la lista no
 *    cargó, el formulario queda apagado y lo dice, en vez de ofrecer un guardado
 *    que no puede funcionar;
 *  - lo que la persona está escribiendo no se pisa cuando la pantalla recarga;
 *  - Guardar manda la función 12 con los tres campos, marca los que el servidor
 *    rechaza y avisa SIEMPRE, también si la petición ni se completa;
 *  - un valor con HTML se pone como texto o como valor de un campo, nunca como HTML.
 */
const fs = require('fs');
const path = require('path');

const fuente = fs.readFileSync(path.join(__dirname, '..', 'core', 'configuracion.js'), 'utf8');
const ini = fuente.indexOf('class Configuracion {');
const fin = fuente.indexOf('const configuracion = new Configuracion();');
if (ini < 0 || fin < 0) { throw new Error('No se encontró la clase Configuracion en core/configuracion.js'); }
const claseCodigo = fuente.slice(ini, fin);

let pasadas = 0;
const fallidas = [];
function comprobar(descripcion, obtenido, esperado) {
    const a = JSON.stringify(obtenido), b = JSON.stringify(esperado);
    if (a === b) { pasadas++; } else { fallidas.push(`  ✗ ${descripcion}\n      esperado: ${b}\n      obtenido: ${a}`); }
}

/** Un $ de mentira: anota por selector lo que se le hace. */
function entorno() {
    const reg = { vals: {}, props: {}, textos: {}, htmls: {}, toggles: {}, clases: {}, ajax: [], swal: [] };
    const $ = function (sel) {
        const o = {
            val(v) { if (v === undefined) { return reg.vals[sel]; } reg.vals[sel] = v; return o; },
            prop(k, v) { reg.props[sel + '|' + k] = v; return o; },
            text(t) { reg.textos[sel] = t; return o; },
            html(h) { reg.htmls[sel] = h; return o; },
            toggleClass(c, f) { reg.toggles[sel + '|' + c] = f; return o; },
            addClass(c) { reg.clases[sel + '|+' + c] = true; return o; },
            removeClass(c) { reg.clases[sel + '|-' + c] = true; return o; },
            find() { return o; }, on() { return o; }, trigger() { return o; }
        };
        return o;
    };
    $.ajax = function (opciones) { reg.ajax.push(opciones); return {}; };
    $.post = function () { return {}; };
    const swal = function (o) { reg.swal.push(o); };
    const Clase = new Function('$', 'swal', 'console', claseCodigo + '\nreturn Configuracion;')($, swal, { log() {} });
    return { reg, c: new Clase() };
}

const FILAS = [
    { par_Clave: 'CONTACTO_CORREO', par_Valor: 'hacienda@guateque.gov.co', par_Contacto: 1, par_FechaActualizacion: '2026-11-09 10:00' },
    { par_Clave: 'CONTACTO_DEPENDENCIA', par_Valor: 'Tesorería Municipal', par_Contacto: 1, par_FechaActualizacion: '2026-11-10 08:30' },
    { par_Clave: 'CONTACTO_TELEFONO', par_Valor: '311 000 1111', par_Contacto: 1, par_FechaActualizacion: null }
];

/* ---------- pintarContacto ---------- */
{
    const { reg, c } = entorno();
    c.pintarContacto(FILAS);
    comprobar('con las 3 filas: dependencia en su casilla', reg.vals['#ctoDependencia'], 'Tesorería Municipal');
    comprobar('con las 3 filas: teléfono en su casilla', reg.vals['#ctoTelefono'], '311 000 1111');
    comprobar('con las 3 filas: correo en su casilla', reg.vals['#ctoCorreo'], 'hacienda@guateque.gov.co');
    comprobar('con las 3 filas: el formulario queda habilitado', reg.props['#formContacto :input|disabled'], false);
    comprobar('con las 3 filas: sin aviso', reg.toggles['#avisoContacto|d-none'], true);
    comprobar('"Último cambio" es el más reciente', reg.textos['#ctoUltimoCambio'], 'Último cambio: 2026-11-10 08:30');
}
{
    const { reg, c } = entorno();
    c.pintarContacto([FILAS[0], FILAS[1]]);
    comprobar('faltan filas (054 sin aplicar): formulario apagado', reg.props['#formContacto :input|disabled'], true);
    comprobar('faltan filas: el aviso se muestra', reg.toggles['#avisoContacto|d-none'], false);
    comprobar('faltan filas: dice que falta la migración 054', /migraci[oó]n 054/.test(reg.textos['#avisoContacto']), true);
    comprobar('faltan filas: no pinta nada en las casillas', reg.vals['#ctoDependencia'], undefined);
}
{
    const { reg, c } = entorno();
    c.pintarContacto([], 'Error de conexión.');
    comprobar('la lista no cargó: formulario apagado', reg.props['#formContacto :input|disabled'], true);
    comprobar('la lista no cargó: el aviso es el del servidor', reg.textos['#avisoContacto'], 'Error de conexión.');
}
{
    // Lo que se está escribiendo no se pisa al recargar la lista.
    const { reg, c } = entorno();
    c._contactoSucio = true;
    c.pintarContacto(FILAS);
    comprobar('con cambios sin guardar no se pisan las casillas', reg.vals['#ctoDependencia'], undefined);
    comprobar('con cambios sin guardar el formulario sigue habilitado', reg.props['#formContacto :input|disabled'], false);
}
{
    // Un valor con HTML llega como valor de un campo (val), nunca como html().
    const { reg, c } = entorno();
    const raro = FILAS.map(f => Object.assign({}, f));
    raro[1].par_Valor = '<img src=x onerror=alert(1)>';
    c.pintarContacto(raro);
    comprobar('un valor con HTML se pone con val(), no con html()', reg.vals['#ctoDependencia'], '<img src=x onerror=alert(1)>');
    comprobar('pintarContacto no usa html() en ningún lado', Object.keys(reg.htmls).length, 0);
}

/* ---------- cargarParametros: las del contacto no van en la tabla ---------- */
{
    const { reg, c } = entorno();
    c.cargarParametros();
    const peticion = reg.ajax[0];
    comprobar('cargarParametros pide la función 1', peticion.data.funcion, 1);
    peticion.success({ ok: 1, datos: FILAS.concat([
        { par_Id: 7, par_Clave: 'RECAUDO_EAN', par_Nombre: 'EAN de recaudo', par_Valor: '7700000000000', par_Contacto: 0, par_Sensible: 0, par_Descripcion: 'd', par_FechaActualizacion: null }
    ]) });
    const tabla = reg.htmls['#tbodyParametros'] || '';
    comprobar('la tabla de parámetros trae RECAUDO_EAN', tabla.indexOf('RECAUDO_EAN') >= 0, true);
    comprobar('la tabla de parámetros NO trae ningún CONTACTO_', tabla.indexOf('CONTACTO_') >= 0, false);
    comprobar('y las casillas de contacto sí se llenaron', reg.vals['#ctoTelefono'], '311 000 1111');
}
{
    const { reg, c } = entorno();
    c.cargarParametros();
    reg.ajax[0].success({ ok: 0, mensaje: 'Su rol no tiene permiso.' });
    comprobar('si la lista falla, el contacto lo dice', reg.textos['#avisoContacto'], 'Su rol no tiene permiso.');
    const e2 = entorno();
    e2.c.cargarParametros();
    e2.reg.ajax[0].error({ responseText: '' });
    comprobar('si la petición ni se completa, el contacto lo dice', e2.reg.textos['#avisoContacto'], 'Error de conexión.');
}

/* ---------- guardarContacto ---------- */
{
    const { reg, c } = entorno();
    reg.vals['#ctoDependencia'] = 'Tesorería'; reg.vals['#ctoTelefono'] = '311 000 1111'; reg.vals['#ctoCorreo'] = 'a@b.co';
    c._contactoSucio = true;
    c.guardarContacto();
    const p = reg.ajax[0];
    comprobar('Guardar usa POST a class.configuracion.php', [p.type, p.url], ['POST', '../business/controller/class.configuracion.php']);
    comprobar('Guardar manda la función 12 con los tres campos', p.data,
        { funcion: 12, dependencia: 'Tesorería', telefono: '311 000 1111', correo: 'a@b.co' });
    comprobar('mientras guarda, el botón queda apagado', reg.props['#btnGuardarContacto|disabled'], true);

    p.success({ ok: 1, mensaje: 'Contacto del municipio guardado.', datos: { cambiados: ['CONTACTO_CORREO'] } });
    comprobar('al guardar, el botón vuelve a encenderse', reg.props['#btnGuardarContacto|disabled'], false);
    comprobar('al guardar, se avisa con éxito', reg.swal.map(s => s.type), ['success']);
    comprobar('al guardar, ya no hay cambios pendientes', c._contactoSucio, false);
    comprobar('al guardar, se recarga la lista (la función 1)', reg.ajax.length === 2 && reg.ajax[1].data.funcion === 1, true);
}
{
    const { reg, c } = entorno();
    c.guardarContacto();
    reg.ajax[0].success({ ok: 1, mensaje: 'No hay cambios que guardar.', datos: [] });
    comprobar('sin cambios: el aviso es informativo', reg.swal.map(s => s.type), ['info']);
}
{
    const { reg, c } = entorno();
    c._contactoSucio = true;
    c.guardarContacto();
    reg.ajax[0].success({ ok: 0, mensaje: 'El correo no es válido.', datos: { campos: ['correo', 'telefono'] } });
    comprobar('rechazo: marca los campos que el servidor dijo', [reg.clases['#ctoCorreo|+is-invalid'], reg.clases['#ctoTelefono|+is-invalid']], [true, true]);
    comprobar('rechazo: NO marca el que no dijo', reg.clases['#ctoDependencia|+is-invalid'], undefined);
    comprobar('rechazo: avisa con el mensaje del servidor', [reg.swal[0].type, reg.swal[0].text], ['error', 'El correo no es válido.']);
    comprobar('rechazo: lo escrito se conserva (sigue sucio)', c._contactoSucio, true);
    comprobar('rechazo: el botón vuelve a encenderse', reg.props['#btnGuardarContacto|disabled'], false);
}
{
    const { reg, c } = entorno();
    c.guardarContacto();
    reg.ajax[0].error({ responseText: '' });
    comprobar('si la petición ni se completa: avisa (nunca en silencio)', reg.swal.map(s => s.type), ['error']);
    comprobar('si la petición ni se completa: el botón vuelve a encenderse', reg.props['#btnGuardarContacto|disabled'], false);
}
{
    // Un rechazo sin datos (p. ej. sin permiso o sin sesión) no revienta.
    const { reg, c } = entorno();
    c.guardarContacto();
    reg.ajax[0].success({ ok: 0, mensaje: 'Debe iniciar sesión.' });
    comprobar('rechazo sin datos: avisa y no revienta', reg.swal.map(s => s.type), ['error']);
}

/* ---------- Resultado ---------- */
const total = pasadas + fallidas.length;
if (fallidas.length) {
    console.log(`FALLARON ${fallidas.length} de ${total}:\n` + fallidas.join('\n'));
    process.exit(1);
}
console.log(`contacto_pantalla.test.js: ${pasadas} de ${total} casos pasan.`);
