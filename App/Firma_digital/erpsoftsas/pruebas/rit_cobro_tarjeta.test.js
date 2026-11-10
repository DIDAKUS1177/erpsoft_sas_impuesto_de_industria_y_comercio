/*
 * Pruebas de la tarjeta «Derechos de inscripción» de la pantalla del RIT
 * (core/icaWebRit.js, migración 055).
 *
 * Se ejecutan con:   node pruebas/rit_cobro_tarjeta.test.js
 * Sin dependencias: no hay framework ni navegador, solo Node.
 *
 * Qué comprueba, sin abrir el navegador:
 *   - La tarjeta de cada estado (pendiente que bloquea, pendiente que solo informa, pagado,
 *     exonerado) y que NADA de lo que viene del servidor o lo escribió una persona entra
 *     crudo al HTML (instrucciones, banco, recibo, motivo, nombres).
 *   - Sin cobro (Paipa, o a quien no le corresponde) la pantalla no se toca: la tarjeta se
 *     oculta y el botón de firmar queda como estaba, ni siquiera con un "disabled=false".
 *   - Los tres formularios (abrir el pago, registrar el pago, exonerar): qué se manda al
 *     servidor, qué se hace con cada respuesta y que ningún error queda mudo.
 *
 * Se extrae de core/icaWebRit.js el bloque de métodos del cobro y se ejecuta contra un
 * jQuery de mentira que anota lo que la página recibiría. Es el código REAL del archivo,
 * no una copia: si cambian los marcadores del bloque, la prueba avisa en vez de pasar en falso.
 */
const fs = require('fs');
const path = require('path');

// Dentro del repositorio (pruebas/) la raíz es la carpeta de arriba; con un argumento, la que se indique.
const raiz = process.argv[2] || path.join(__dirname, '..');
// El repositorio puede estar con saltos CRLF (autocrlf en Windows): los marcadores se buscan con LF.
const src = fs.readFileSync(path.join(raiz, 'core', 'icaWebRit.js'), 'utf8').replace(/\r\n/g, '\n');
const NumerosCOP = require(path.join(raiz, 'core', 'numeros.js'));

function tomar(inicio, fin) {
    const i = src.indexOf(inicio);
    const f = i < 0 ? -1 : src.indexOf(fin, i);
    if (i < 0 || f < 0) { throw new Error('No se halló el bloque que empieza en: ' + inicio.trim()); }
    return src.slice(i, f);
}
const escapar = tomar('    escaparHtml(valor) {', '\n\n\n    /**\n     * Puntos 14 y 15');
// Desde el primer método del cobro hasta el que sigue ("Actualizar"): pintarCobro, el formulario
// del pago, el registro del pago, el formulario de la exoneración y la exoneración.
const bloque = tomar('    _fechaCobro(texto) {', '    /** "Actualizar": desbloquea para registrar una novedad. */');

let pasadas = 0;
const fallidas = [];
function comprobar(descripcion, condicion, detalle) {
    if (condicion) { pasadas++; return; }
    fallidas.push('  ✗ ' + descripcion + (detalle !== undefined ? '\n      ' + String(detalle).slice(0, 400) : ''));
}

/** ¿La tarjeta dejó deshabilitado el botón de firmar? */
function firmaBloqueada(dom) {
    return !!(dom['#btnFirmarRIT'] && dom['#btnFirmarRIT'].props.disabled === true);
}

/** Un "ahora" fijo: el 9 de noviembre de 2026, 9 p. m. en el reloj del navegador. */
class FechaFija extends Date {
    constructor(...a) { super(...(a.length ? a : [2026, 10, 9, 21, 0, 0])); }
}

/**
 * jQuery de mentira. Cada selector tiene su estado (valor, texto, html, props, atributos,
 * opciones) y lo que se le hace queda en `reg`: ajax enviados, swal mostrados, modales
 * abiertos o cerrados y formularios reiniciados.
 */
function entorno(puede, idContribuyente) {
    const dom = Object.create(null);
    const reg = { ajax: [], swal: [], modal: [], reset: [], consultas: 0 };
    const estado = (sel) => dom[sel] || (dom[sel] = { val: '', text: '', html: null, visible: null, props: {}, attrs: {}, opciones: [] });

    function envoltura(sel, e) {
        const o = {
            0: { reset() { reg.reset.push(sel); e.val = ''; } },
            hide() { e.visible = false; return o; },
            show() { e.visible = true; return o; },
            empty() { e.opciones = []; e.html = ''; return o; },
            html(h) { if (h === undefined) { return e.html; } e.html = h; return o; },
            text(t) { if (t === undefined) { return e.text; } e.text = t; return o; },
            val(v) { if (v === undefined) { return e.val; } e.val = v; return o; },
            append(x) { e.opciones.push(x.__estado); return o; },
            attr(k, v) { if (v === undefined) { return e.attrs[k]; } e.attrs[k] = v; return o; },
            removeAttr(k) { delete e.attrs[k]; return o; },
            prop(k, v) { if (v === undefined) { return e.props[k]; } e.props[k] = v; return o; },
            modal(accion) { reg.modal.push([sel, accion]); return o; },
        };
        o.__estado = e;
        return o;
    }
    const $ = function (sel) {
        if (sel === '<option>') { return envoltura('<option>', { val: '', text: '' }); }
        return envoltura(sel, estado(sel));
    };
    $.ajax = (opciones) => { reg.ajax.push(opciones); };
    const swal = (o) => { reg.swal.push(o); };
    const localStorage = { getItem: (k) => (k === 'id_Contribuyente' ? (idContribuyente === undefined ? '30' : idContribuyente) : null) };

    const Clase = new Function('NumerosCOP', '$', 'swal', 'localStorage', 'Date',
        'return class T {\n' + escapar + '\n' + bloque +
        '\n puede(c) { return ' + (puede ? 'true' : 'false') + '; }' +
        '\n consultarFirmaRIT() { this._consultas = (this._consultas || 0) + 1; }\n};')(NumerosCOP, $, swal, localStorage, FechaFija);
    const t = new Clase();
    return { t, reg, dom, estado };
}

/* =====================================================================
   La tarjeta
   ===================================================================== */

// --- sin cobro: Paipa no cambia
let { t, reg, dom } = entorno(true);
t.pintarCobro(null);
comprobar('sin cobro: la tarjeta se oculta', dom['#ritCobro'] && dom['#ritCobro'].visible === false);
comprobar('sin cobro: NO se toca el botón de firmar (ni disabled=false ni title)', !('#btnFirmarRIT' in dom), Object.keys(dom).join(','));
t.pintarCobro(undefined);
comprobar('sin cobro (undefined): lo mismo', !('#btnFirmarRIT' in dom));

// --- pendiente que bloquea, con todo lo malo posible en los textos
({ t, reg, dom } = entorno(true));
t.pintarCobro({
    estado: 'PENDIENTE', valor: 25000, bloquea: 1, puedeRegistrar: 1,
    instrucciones: 'Pague en <script>alert(1)</script> "caja" & \'tesoreria\'',
});
let html = dom['#ritCobro'].html;
comprobar('pendiente: se muestra', dom['#ritCobro'].visible === true);
comprobar('pendiente: valor en formato colombiano', html.indexOf('$25.000') !== -1, html);
comprobar('pendiente: sin <script> crudo, y el texto sale escapado', html.indexOf('<script') === -1 && html.indexOf('&lt;script&gt;') !== -1, html);
comprobar('pendiente: comillas y & escapados', html.indexOf('&quot;caja&quot;') !== -1 && html.indexOf('&amp; &#39;tesoreria&#39;') !== -1, html);
comprobar('pendiente que bloquea: dice que no podrá firmar', html.indexOf('No podrá firmar el RIT') !== -1);
comprobar('pendiente: con permiso salen los dos botones', html.indexOf('id="btnRitCobroPago"') !== -1 && html.indexOf('id="btnRitCobroExonerar"') !== -1);
comprobar('pendiente que bloquea: el botón de firmar se deshabilita y explica por qué',
    dom['#btnFirmarRIT'].props.disabled === true && /pago de los derechos/.test(dom['#btnFirmarRIT'].attrs.title || ''));

// --- y cuando ya no bloquea (se registró el pago), el botón vuelve a quedar habilitado
t.pintarCobro({ estado: 'PAGADO', valor: 25000, bloquea: 0, pago: { fecha: '2026-11-09', banco: '', referencia: 'X', registradoPor: 'Ana', registradoEl: '2026-11-09 21:29' } });
comprobar('tras el pago el botón de firmar se habilita y pierde el aviso',
    dom['#btnFirmarRIT'].props.disabled === false && !('title' in dom['#btnFirmarRIT'].attrs));
// y si de repente no hay cobro (la Alcaldía lo dejó gratis), lo que ESTA tarjeta bloqueó se libera
({ t, reg, dom } = entorno(true));
t.pintarCobro({ estado: 'PENDIENTE', valor: 25000, bloquea: 1, puedeRegistrar: 0 });
t.pintarCobro(null);
comprobar('si el cobro desaparece, el botón que bloqueó la tarjeta se libera',
    dom['#btnFirmarRIT'].props.disabled === false && !('title' in dom['#btnFirmarRIT'].attrs) && dom['#ritCobro'].visible === false);

// --- pendiente que solo informa y sin permiso de registrar
({ t, reg, dom } = entorno(false));
t.pintarCobro({ estado: 'PENDIENTE', valor: 25000, bloquea: 0, puedeRegistrar: 1, instrucciones: '' });
html = dom['#ritCobro'].html;
comprobar('solo informa: no dice que no podrá firmar', html.indexOf('No podrá firmar') === -1 && html.indexOf('registrará su pago') !== -1);
comprobar('solo informa: el botón de firmar no se bloquea (y ni se toca)', !firmaBloqueada(dom) && !('#btnFirmarRIT' in dom));
comprobar('sin el permiso en pantalla no salen los botones (aunque el servidor diga puedeRegistrar)', html.indexOf('btnRitCobroPago') === -1);
comprobar('sin instrucciones no sale "Cómo pagar"', html.indexOf('Cómo pagar') === -1);

({ t, reg, dom } = entorno(true));
t.pintarCobro({ estado: 'PENDIENTE', valor: 25000, bloquea: 0, puedeRegistrar: 0, instrucciones: 'x' });
comprobar('puedeRegistrar=0 del servidor: sin botones aunque el rol tenga el permiso', dom['#ritCobro'].html.indexOf('btnRitCobroPago') === -1);

// --- pagado
({ t, reg, dom } = entorno(true));
t.pintarCobro({
    estado: 'PAGADO', valor: 25000, bloquea: 0,
    pago: { fecha: '2026-11-09', banco: 'BANCOLOMBIA S.A.', referencia: 'RC-<1>', registradoPor: 'Ana "la" <b>Tesorera</b>', registradoEl: '2026-11-09 21:29' },
});
html = dom['#ritCobro'].html;
comprobar('pagado: fecha dd/mm/aaaa y fecha-hora del registro', html.indexOf('09/11/2026') !== -1 && html.indexOf('09/11/2026 21:29') !== -1, html);
comprobar('pagado: banco, referencia y quién, escapados',
    html.indexOf('BANCOLOMBIA S.A.') !== -1 && html.indexOf('RC-&lt;1&gt;') !== -1 && html.indexOf('&lt;b&gt;Tesorera&lt;/b&gt;') !== -1 && html.indexOf('<b>Tesorera') === -1, html);
comprobar('pagado: chip "Pagado" y sin botones', html.indexOf('est-pagada') !== -1 && html.indexOf('btnRitCobro') === -1);
comprobar('pagado: el botón de firmar no se bloquea', !firmaBloqueada(dom));

// --- exonerado
({ t, reg, dom } = entorno(true));
t.pintarCobro({
    estado: 'EXONERADO', valor: 25000, bloquea: 0,
    exoneracion: { motivo: 'Junta de acción comunal <img src=x onerror=alert(1)>', registradoPor: 'Luis', registradoEl: '2026-11-09 08:05' },
});
html = dom['#ritCobro'].html;
comprobar('exonerado: motivo escapado (sin etiqueta img viva)', html.indexOf('<img') === -1 && html.indexOf('&lt;img src=x onerror=alert(1)&gt;') !== -1, html);
comprobar('exonerado: chip "Exonerado"', html.indexOf('Exonerado') !== -1);

// --- ayudantes de formato
({ t } = entorno(true));
comprobar('fecha vacía o rara no revienta', t._fechaCobro('') === '' && t._fechaCobro(null) === '' && t._fechaCobro('basura') === '');
comprobar('fecha con T también', t._fechaCobro('2026-11-09T08:05:00') === '09/11/2026 08:05');
comprobar('pesos: miles con punto y redondeo a entero', t._pesosCobro(1250000) === '$1.250.000' && t._pesosCobro('25000') === '$25.000' && t._pesosCobro(null) === '$0');

/* =====================================================================
   Abrir el formulario del pago
   ===================================================================== */

// --- sin tarjeta o sin pendiente: no se hace nada
({ t, reg, dom } = entorno(true));
t.abrirPagoCobro();
comprobar('abrir pago sin tarjeta: no envía nada', reg.ajax.length === 0);
t._cobro = { estado: 'PAGADO' };
t.abrirPagoCobro();
comprobar('abrir pago con el cobro ya pagado: no envía nada', reg.ajax.length === 0);

// --- pendiente: pide al servidor el cobro y los bancos
({ t, reg, dom } = entorno(true, '77'));
t._cobro = { estado: 'PENDIENTE', valor: 25000 };
t.abrirPagoCobro();
comprobar('abrir pago: una sola petición al controlador, función 1, con el contribuyente activo',
    reg.ajax.length === 1 && reg.ajax[0].url === '../business/controller/class.ritCobro.php' && reg.ajax[0].type === 'POST' &&
    reg.ajax[0].data.funcion === 1 && reg.ajax[0].data.id_contribuyente === '77', JSON.stringify(reg.ajax[0] && reg.ajax[0].data));
reg.ajax[0].success({
    ok: 1, mensaje: '',
    datos: { cobro: { estado: 'PENDIENTE', valor: 30000 }, bancos: [{ id: 5, nombre: 'BANCO <b>X</b>', cuenta: '123-4' }, { id: 6, nombre: 'BANCO Y', cuenta: '' }] },
});
comprobar('abrir pago: muestra el valor que dice el servidor (no el de la tarjeta vieja)', dom['#ritCobroPagoValor'].text === '$30.000', dom['#ritCobroPagoValor'].text);
const ops = dom['#ritCobroBanco'].opciones;
comprobar('abrir pago: "Sin banco" primero y luego los bancos', ops.length === 3 && ops[0].text === 'Sin banco' && ops[0].val === '' && ops[1].val === '5' && ops[2].val === '6', JSON.stringify(ops));
comprobar('abrir pago: el banco entra como TEXTO (nunca como HTML) y con su cuenta si la tiene',
    ops[1].text === 'BANCO <b>X</b> (cuenta 123-4)' && ops[2].text === 'BANCO Y', JSON.stringify(ops));
comprobar('abrir pago: la fecha por defecto es hoy y no deja elegir una futura',
    dom['#ritCobroFechaPago'].val === '2026-11-09' && dom['#ritCobroFechaPago'].attrs.max === '2026-11-09', JSON.stringify(dom['#ritCobroFechaPago']));
comprobar('abrir pago: reinicia el formulario y abre la ventana', reg.reset.indexOf('#formRitCobroPago') !== -1 && reg.modal.some((m) => m[0] === '#modal-RitCobroPago' && m[1] === 'show'));

// --- el servidor dice que ya no está pendiente
({ t, reg, dom } = entorno(true));
t._cobro = { estado: 'PENDIENTE', valor: 25000 };
t.abrirPagoCobro();
reg.ajax[0].success({ ok: 1, datos: { cobro: { estado: 'PAGADO', valor: 25000 }, bancos: [] } });
comprobar('abrir pago, ya no pendiente: avisa, no abre la ventana y vuelve a consultar la tarjeta',
    reg.swal.length === 1 && reg.swal[0].type === 'info' && !reg.modal.length && t._consultas === 1, JSON.stringify(reg.swal));
({ t, reg, dom } = entorno(true));
t._cobro = { estado: 'PENDIENTE', valor: 25000 };
t.abrirPagoCobro();
reg.ajax[0].success({ ok: 0, mensaje: 'Su rol no tiene permiso para esta acción.' });
comprobar('abrir pago, rechazo del servidor: muestra SU mensaje', reg.swal.length === 1 && reg.swal[0].text === 'Su rol no tiene permiso para esta acción.' && !reg.modal.length, JSON.stringify(reg.swal));
({ t, reg, dom } = entorno(true));
t._cobro = { estado: 'PENDIENTE', valor: 25000 };
t.abrirPagoCobro();
reg.ajax[0].error();
comprobar('abrir pago, falla de conexión: no queda mudo', reg.swal.length === 1 && reg.swal[0].type === 'error' && /conexión/i.test(reg.swal[0].title), JSON.stringify(reg.swal));

/* =====================================================================
   Registrar el pago
   ===================================================================== */
({ t, reg, dom, estado } = entorno(true, '77'));
estado('#ritCobroFechaPago').val = '2026-11-08';
estado('#ritCobroReferencia').val = 'RC-2026-001';
estado('#ritCobroBanco').val = '5';
t.registrarPagoCobro();
comprobar('registrar: manda función 2 con contribuyente, fecha, referencia y banco',
    reg.ajax.length === 1 && reg.ajax[0].url === '../business/controller/class.ritCobro.php' && reg.ajax[0].data.funcion === 2 &&
    reg.ajax[0].data.id_contribuyente === '77' && reg.ajax[0].data.fechaPago === '2026-11-08' &&
    reg.ajax[0].data.referencia === 'RC-2026-001' && reg.ajax[0].data.idBanco === '5', JSON.stringify(reg.ajax[0] && reg.ajax[0].data));
comprobar('registrar: nunca manda el valor (lo pone el servidor)', !('valor' in reg.ajax[0].data) && !('valorPago' in reg.ajax[0].data));
comprobar('registrar: el botón queda deshabilitado mientras envía (sin doble clic)', dom['#btnRitCobroGuardarPago'].props.disabled === true);
reg.ajax[0].success({ ok: 1, mensaje: 'Pago de la inscripción registrado.' });
reg.ajax[0].complete();
comprobar('registrar, ok: cierra la ventana, avisa y vuelve a pintar la tarjeta',
    reg.modal.some((m) => m[0] === '#modal-RitCobroPago' && m[1] === 'hide') && reg.swal.length === 1 && reg.swal[0].type === 'success' && t._consultas === 1, JSON.stringify(reg.swal));
comprobar('registrar: al terminar el botón se habilita otra vez', dom['#btnRitCobroGuardarPago'].props.disabled === false);

({ t, reg, dom } = entorno(true));
t.registrarPagoCobro();
reg.ajax[0].success({ ok: 0, mensaje: 'La fecha de pago no puede ser futura ni anterior al año 2000.' });
reg.ajax[0].complete();
comprobar('registrar, rechazo: muestra el motivo y deja abierta la ventana (para corregir)',
    reg.swal.length === 1 && reg.swal[0].type === 'error' && /futura/.test(reg.swal[0].text) && !reg.modal.some((m) => m[1] === 'hide') && !t._consultas, JSON.stringify(reg.swal));

({ t, reg, dom } = entorno(true));
t.registrarPagoCobro();
reg.ajax[0].success({ ok: 0, mensaje: 'La inscripción de este contribuyente ya figura como pagada.', codigo: 'YA_REGISTRADO' });
reg.ajax[0].complete();
comprobar('registrar, "ya registrado" (otro funcionario ganó): cierra y muestra lo que quedó',
    reg.swal.length === 1 && reg.modal.some((m) => m[1] === 'hide') && t._consultas === 1, JSON.stringify(reg.swal));

({ t, reg, dom } = entorno(true));
t.registrarPagoCobro();
reg.ajax[0].error();
reg.ajax[0].complete();
comprobar('registrar, falla de conexión: avisa (no queda mudo) y habilita el botón',
    reg.swal.length === 1 && reg.swal[0].type === 'error' && /conexión/i.test(reg.swal[0].title) && dom['#btnRitCobroGuardarPago'].props.disabled === false, JSON.stringify(reg.swal));

/* =====================================================================
   Exonerar
   ===================================================================== */
({ t, reg, dom } = entorno(true));
t.abrirExonerarCobro();
comprobar('abrir exoneración sin tarjeta: no hace nada', !reg.modal.length);
t._cobro = { estado: 'PENDIENTE', valor: 25000 };
t.abrirExonerarCobro();
comprobar('abrir exoneración: muestra el valor y abre la ventana',
    dom['#ritCobroExonerarValor'].text === '$25.000' && reg.modal.some((m) => m[0] === '#modal-RitCobroExonerar' && m[1] === 'show') && reg.reset.indexOf('#formRitCobroExonerar') !== -1);

({ t, reg, dom, estado } = entorno(true));
estado('#ritCobroMotivo').val = '  corto  ';
t.exonerarCobro();
comprobar('exonerar con motivo de menos de 10 caracteres: avisa y NO envía', reg.ajax.length === 0 && reg.swal.length === 1 && reg.swal[0].type === 'warning', JSON.stringify(reg.swal));

({ t, reg, dom, estado } = entorno(true, '77'));
estado('#ritCobroMotivo').val = '   Junta de acción comunal, acuerdo 012   ';
t.exonerarCobro();
comprobar('exonerar: manda función 3 con el motivo recortado',
    reg.ajax.length === 1 && reg.ajax[0].data.funcion === 3 && reg.ajax[0].data.id_contribuyente === '77' && reg.ajax[0].data.motivo === 'Junta de acción comunal, acuerdo 012', JSON.stringify(reg.ajax[0] && reg.ajax[0].data));
comprobar('exonerar: el botón queda deshabilitado mientras envía', dom['#btnRitCobroGuardarExoneracion'].props.disabled === true);
reg.ajax[0].success({ ok: 1, mensaje: 'Inscripción exonerada.' });
reg.ajax[0].complete();
comprobar('exonerar, ok: cierra, avisa y vuelve a pintar la tarjeta',
    reg.modal.some((m) => m[0] === '#modal-RitCobroExonerar' && m[1] === 'hide') && reg.swal[0].type === 'success' && t._consultas === 1);
comprobar('exonerar: al terminar el botón se habilita otra vez', dom['#btnRitCobroGuardarExoneracion'].props.disabled === false);

({ t, reg, dom, estado } = entorno(true));
estado('#ritCobroMotivo').val = 'un motivo suficientemente largo';
t.exonerarCobro();
reg.ajax[0].success({ ok: 0, mensaje: 'Este contribuyente no tiene una inscripción pendiente de pago.' });
reg.ajax[0].complete();
comprobar('exonerar, rechazo: muestra el motivo y deja la ventana abierta', reg.swal.length === 1 && reg.swal[0].type === 'error' && !reg.modal.some((m) => m[1] === 'hide'));

({ t, reg, dom, estado } = entorno(true));
estado('#ritCobroMotivo').val = 'un motivo suficientemente largo';
t.exonerarCobro();
reg.ajax[0].error();
reg.ajax[0].complete();
comprobar('exonerar, falla de conexión: no queda mudo', reg.swal.length === 1 && reg.swal[0].type === 'error' && /conexión/i.test(reg.swal[0].title));

/* ---------- resultado ---------- */
console.log('\n  ' + pasadas + ' pruebas pasaron');
if (fallidas.length) {
    console.log('  ' + fallidas.length + ' FALLARON:\n');
    console.log(fallidas.join('\n'));
    process.exit(1);
}
console.log('  Todo correcto.\n');
