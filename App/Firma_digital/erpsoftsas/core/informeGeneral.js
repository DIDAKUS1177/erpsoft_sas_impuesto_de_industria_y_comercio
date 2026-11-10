/**
 * Pestaña «Informe general» de dist/informes.php (2026-11-10, migración 056).
 *
 * La persona elige qué filas trae el informe (una por declaración, una por
 * contribuyente con sus declaraciones agrupadas, o solo contribuyentes), los
 * filtros, las columnas, su orden y el título de cada una, mira una vista previa
 * (paginada; busca y ordena el servidor sobre TODAS las filas) y descarga el Excel. Las plantillas del sistema
 * (en código) y las guardadas (privadas o del municipio) cargan los tres bloques.
 *
 * Todo lo que importa lo decide el SERVIDOR (business/class.informes.php): qué
 * fuentes y columnas ve este rol, que no se pidan datos de contacto sin permiso,
 * los topes del Excel. Aquí solo se arma la configuración (JSON v1, solo claves
 * del catálogo y valores) y se muestra lo que el servidor contesta. Por eso:
 *   - nunca sale a la pantalla algo sin pasar por .text() / .attr() (los títulos,
 *     nombres de plantilla y celdas pueden traer < o comillas);
 *   - ningún AJAX falla en silencio: cada llamada tiene su error() y muestra el
 *     mensaje del servidor tal cual.
 *
 * Este archivo tiene dos partes:
 *   1. InformeGeneralLogica: funciones PURAS (sin DOM ni jQuery), probadas con
 *      node en pruebas/informeGeneral.test.js.
 *   2. InformeGeneral: la pantalla.
 */

var InformeGeneralLogica = (function () {

    var Num = (typeof NumerosCOP !== 'undefined') ? NumerosCOP
            : (typeof require === 'function' ? require('./numeros.js') : null);

    /* ---------- Utilidades ---------- */

    function tieneOp(def, op) { return !!def && (def.ops || []).indexOf(op) !== -1; }

    /* Solo dígitos de lo que la persona escribió ("1.500.000" -> 1500000); vacío -> null. */
    function enteroDeTexto(texto) {
        var t = String(texto === null || texto === undefined ? '' : texto).replace(/[\s$]/g, '');
        // Los centavos no cuentan: "1.500,50" y "1500.50" son 1500 (no 150050).
        if (t.indexOf(',') !== -1) { t = t.slice(0, t.indexOf(',')); }
        else if (/\.\d{1,2}$/.test(t)) { t = t.replace(/\.\d{1,2}$/, ''); }
        var s = t.replace(/[^0-9]/g, '');
        if (s === '' || s.length > 15) { return s === '' ? null : NaN; }
        return parseInt(s, 10);
    }

    /* NIT o cédula: se corta en el guion (el dígito de verificación no va) y quedan solo dígitos. */
    function documentoDeTexto(texto) {
        var s = String(texto === null || texto === undefined ? '' : texto).trim();
        if (s === '') { return null; }
        s = s.split('-')[0].replace(/[\s.]/g, '');
        if (!/^\d{1,15}$/.test(s)) { return NaN; }
        return parseInt(s, 10);
    }

    function enteroPlano(texto) {
        var s = String(texto === null || texto === undefined ? '' : texto).trim();
        if (s === '') { return null; }
        if (!/^-?\d+$/.test(s)) { return NaN; }
        return parseInt(s, 10);
    }

    function fechaValida(s) { return /^\d{4}-\d{2}-\d{2}$/.test(String(s || '')); }

    function aDdMmAaaa(iso) {
        var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(iso || ''));
        return m ? (m[3] + '/' + m[2] + '/' + m[1]) : String(iso || '');
    }

    /* ---------- Filtros: lo que hay en pantalla <-> lo que va en la configuración ---------- */

    /**
     * Un filtro de la pantalla -> {filtro:{c,op,v}} | {error} | null (sin usar).
     * raw: entero/dinero/fecha {d,h}; lista [..]; texto "..."; booleano ''|'true'|'false'.
     */
    function filtroDesdePantalla(def, raw) {
        var c = def.c, r;
        switch (def.tipo) {
            case 'entero':
            case 'dinero':
            case 'fecha': {
                var d = raw && raw.d !== undefined ? raw.d : '';
                var h = raw && raw.h !== undefined ? raw.h : '';
                var a, b;
                if (def.tipo === 'entero' && !tieneOp(def, 'between')) {
                    // Valor exacto (NIT o cédula): una sola casilla, nunca un rango.
                    a = documentoDeTexto(d);
                    if (a === null) { a = documentoDeTexto(h); }
                    if (a === null) { return null; }
                    if (isNaN(a)) { return { error: 'El filtro «' + def.etiqueta + '»: escriba solo el número, sin puntos ni letras.' }; }
                    return { filtro: { c: c, op: 'in', v: [a] } };
                }
                if (def.tipo === 'entero') { a = enteroPlano(d); b = enteroPlano(h); }
                else if (def.tipo === 'dinero') { a = enteroDeTexto(d); b = enteroDeTexto(h); }
                else {
                    a = String(d || '').trim() === '' ? null : String(d).trim();
                    b = String(h || '').trim() === '' ? null : String(h).trim();
                    if ((a !== null && !fechaValida(a)) || (b !== null && !fechaValida(b))) {
                        return { error: 'El filtro «' + def.etiqueta + '»: escriba las fechas completas (día/mes/año) o déjelas vacías.' };
                    }
                }
                if ((typeof a === 'number' && isNaN(a)) || (typeof b === 'number' && isNaN(b))) {
                    return { error: 'El filtro «' + def.etiqueta + '»: escriba solo números.' };
                }
                if (a === null && b === null) { return null; }
                if (a !== null && b !== null && a > b) {
                    return { error: 'El filtro «' + def.etiqueta + '»: el valor inicial es mayor que el final.' };
                }
                if (def.tipo === 'entero') {
                    var lo = (def.min !== null && def.min !== undefined) ? def.min : null;
                    var hi = (def.max !== null && def.max !== undefined) ? def.max : null;
                    if (a !== null && b !== null) {
                        return { filtro: (a === b && tieneOp(def, 'in')) ? { c: c, op: 'in', v: [a] } : { c: c, op: 'between', v: [a, b] } };
                    }
                    if (a !== null) {
                        if (hi !== null && tieneOp(def, 'between')) { return { filtro: { c: c, op: 'between', v: [a, Math.max(a, hi)] } }; }
                        return { filtro: { c: c, op: 'in', v: [a] } };
                    }
                    if (lo !== null && tieneOp(def, 'between')) { return { filtro: { c: c, op: 'between', v: [Math.min(b, lo), b] } }; }
                    return { filtro: { c: c, op: 'in', v: [b] } };
                }
                // dinero y fecha: dos puntas = entre; una sola = mayor o menor igual.
                if (a !== null && b !== null) { return { filtro: { c: c, op: 'between', v: [a, b] } }; }
                return { filtro: a !== null ? { c: c, op: '>=', v: a } : { c: c, op: '<=', v: b } };
            }
            case 'lista': {
                if (!raw || !raw.length) { return null; }
                var numerico = raw.every(function (x) { return /^\d+$/.test(String(x)); });
                // Los bancos viajan como ids enteros; los estados como códigos de texto.
                return { filtro: { c: c, op: 'in', v: raw.map(function (x) { return numerico ? parseInt(x, 10) : String(x); }) } };
            }
            case 'texto': {
                r = String(raw || '').trim();
                if (r === '') { return null; }
                var op = tieneOp(def, 'igual') ? 'igual' : 'contiene';
                if (op === 'igual') {
                    r = r.replace(/[^0-9]/g, '');
                    if (r === '') { return { error: 'El filtro «' + def.etiqueta + '»: escriba solo dígitos.' }; }
                }
                return { filtro: { c: c, op: op, v: r } };
            }
            case 'booleano':
                if (raw === 'true' || raw === 'false') { return { filtro: { c: c, op: '=', v: raw === 'true' } }; }
                return null;
        }
        return null;
    }

    /* Un filtro de la configuración -> lo que muestra la pantalla (lo contrario de arriba). */
    function pantallaDesdeFiltro(def, f) {
        var v = f.v;
        switch (def.tipo) {
            case 'entero':
                if (f.op === 'in' && Array.isArray(v) && v.length) {
                    var nums = v.map(Number).sort(function (x, y) { return x - y; });
                    return { d: String(nums[0]), h: String(nums[nums.length - 1]) };
                }
                if (f.op === 'between') { return { d: String(v[0]), h: String(v[1]) }; }
                return { d: '', h: '' };
            case 'dinero':
                if (f.op === 'between') { return { d: Num.formatear(Number(v[0])), h: Num.formatear(Number(v[1])) }; }
                if (f.op === '>=') { return { d: Num.formatear(Number(v)), h: '' }; }
                if (f.op === '<=') { return { d: '', h: Num.formatear(Number(v)) }; }
                return { d: '', h: '' };
            case 'fecha':
                if (f.op === 'between') { return { d: String(v[0]), h: String(v[1]) }; }
                if (f.op === '>=') { return { d: String(v), h: '' }; }
                if (f.op === '<=') { return { d: '', h: String(v) }; }
                return { d: '', h: '' };
            case 'lista':
                return (Array.isArray(v) ? v : [v]).map(String);
            case 'texto':
                return String(v === null || v === undefined ? '' : v);
            case 'booleano':
                return v === true || v === 'true' || v === 1 ? 'true' : (v === false || v === 'false' || v === 0 ? 'false' : '');
        }
        return null;
    }

    /* ---------- Configuración ---------- */

    /**
     * Estado de la pantalla -> configuración v1 para el servidor.
     * estado: {modo, impuesto, filtros:{clave:raw}, columnas:[{c,t}], orden:[{c,d}]}
     * fuente: el descriptor del catálogo (columnas y filtros) del estado.
     * Devuelve {config, errores:[]}.
     */
    function construirConfig(estado, fuente) {
        var errores = [];
        var defsF = {}, defsC = {};
        (fuente.filtros || []).forEach(function (d) { defsF[d.c] = d; });
        (fuente.columnas || []).forEach(function (d) { defsC[d.c] = d; });

        var filtros = [];
        (fuente.filtros || []).forEach(function (def) {
            if (!Object.prototype.hasOwnProperty.call(estado.filtros || {}, def.c)) { return; }
            var r = filtroDesdePantalla(def, estado.filtros[def.c]);
            if (r && r.error) { errores.push(r.error); }
            else if (r && r.filtro) { filtros.push(r.filtro); }
        });

        var columnas = [];
        (estado.columnas || []).forEach(function (col) {
            var def = defsC[col.c];
            if (!def) { return; }
            var t = String(col.t || '').trim();
            if (/[<>\u0000-\u001f]/.test(t)) {
                errores.push('El título de la columna «' + def.etiqueta + '» no puede llevar los signos < ni >.');
            } else if (t.length > 60) {
                errores.push('El título de la columna «' + def.etiqueta + '» admite hasta 60 caracteres.');
            }
            var item = { c: col.c };
            if (t !== '' && t !== def.etiqueta) { item.t = t; }
            columnas.push(item);
        });
        if (!columnas.length) { errores.push('Elija al menos una columna para el informe.'); }

        var config = { v: 1, modo: estado.modo };
        // Una fila por declaración y una fila por contribuyente agrupado son de UN impuesto.
        if (estado.modo !== 'contribuyente') { config.impuesto = estado.impuesto; }
        config.filtros = filtros;
        config.columnas = columnas;
        config.orden = (estado.orden || []).filter(function (o) { return defsC[o.c]; })
                                          .map(function (o) { return { c: o.c, d: o.d === 'desc' ? 'desc' : 'asc' }; });
        return { config: config, errores: errores };
    }

    /**
     * Quita del estado lo que la fuente nueva no tiene (al cambiar de impuesto o de modo).
     * Devuelve cuántas columnas, filtros y criterios de orden se quitaron.
     */
    function adaptarAFuente(estado, fuente) {
        var cols = {}, fils = {};
        (fuente.columnas || []).forEach(function (d) { cols[d.c] = d; });
        (fuente.filtros || []).forEach(function (d) { fils[d.c] = d; });
        var quitadas = 0, quitados = 0, quitadosOrden = 0;
        var nuevas = [];
        estado.columnas.forEach(function (c) { if (cols[c.c]) { nuevas.push(c); } else { quitadas++; } });
        estado.columnas = nuevas;
        Object.keys(estado.filtros).forEach(function (k) {
            if (!fils[k]) { delete estado.filtros[k]; quitados++; }
        });
        var ord = [];
        (estado.orden || []).forEach(function (o) { if (cols[o.c] && cols[o.c].ordenable) { ord.push(o); } else { quitadosOrden++; } });
        estado.orden = ord;
        // Un filtro de lista que sigue existiendo puede tener menos opciones en la fuente nueva (el estado
        // "Corregida" no existe entre las declaraciones vigentes): las opciones que sobran se quitan, y si
        // no queda ninguna, el filtro entero. Los bancos y otras listas sin opciones fijas no se tocan.
        Object.keys(estado.filtros).forEach(function (k) {
            var def = fils[k], raw = estado.filtros[k];
            if (!def || def.tipo !== 'lista' || !Array.isArray(raw) || !def.opciones) { return; }
            var validas = def.opciones.map(function (o) { return String(o.v); });
            var quedan = raw.filter(function (v) { return validas.indexOf(String(v)) !== -1; });
            if (quedan.length === raw.length) { return; }
            if (quedan.length) { estado.filtros[k] = quedan; } else { delete estado.filtros[k]; quitados++; }
        });
        return { columnas: quitadas, filtros: quitados, orden: quitadosOrden };
    }

    /**
     * El orden de la vista previa al pulsar un encabezado: sin orden -> ascendente -> descendente ->
     * sin orden (vuelve al de la configuración). actual: {c, d} | null.
     */
    function siguienteOrden(actual, c) {
        if (!actual || actual.c !== c) { return { c: c, d: 'asc' }; }
        return actual.d === 'asc' ? { c: c, d: 'desc' } : null;
    }

    /* Si la columna del orden de la vista ya no está en el informe, el orden se descarta. */
    function ordenVigente(orden, columnas) {
        if (!orden) { return null; }
        return indiceColumna(columnas || [], orden.c) === -1 ? null : orden;
    }

    /**
     * La fila de totales de la vista previa: un texto por columna. El rótulo ("Total de las N filas") va
     * en la primera columna que NO suma, para que no tape la suma de una primera columna de dinero. Si
     * todas suman, el rótulo va aparte (rotuloAparte). null si ninguna columna tiene total.
     */
    function filaTotales(cols, tot, total, unidad) {
        tot = tot || {};
        var suma = function (c) { return !!c.total && tot[c.c] !== undefined && tot[c.c] !== null; };
        if (!cols.some(suma)) { return null; }
        var u = unidad || { singular: 'fila', plural: 'filas' };
        var rotulo = 'Total de ' + (total === 1 ? 'la ' : 'las ') + Num.formatear(total) + ' ' + (total === 1 ? u.singular : u.plural);
        var donde = -1;
        cols.forEach(function (c, i) { if (donde === -1 && !suma(c)) { donde = i; } });
        var celdas = cols.map(function (c, i) {
            if (suma(c)) { return formatearCelda(tot[c.c], c.tipo); }
            return i === donde ? rotulo : '';
        });
        return { celdas: celdas, rotuloAparte: donde === -1 ? rotulo : null };
    }

    /* ---------- Lista ordenada de columnas del panel ---------- */

    function indiceColumna(cols, c) {
        for (var i = 0; i < cols.length; i++) { if (cols[i].c === c) { return i; } }
        return -1;
    }

    /* Marcar una casilla agrega al final; desmarcar quita. Devuelve false si ya estaba / no cabe. */
    function agregarColumna(cols, c, max) {
        if (indiceColumna(cols, c) !== -1) { return false; }
        if (max && cols.length >= max) { return false; }
        cols.push({ c: c, t: '' });
        return true;
    }

    function quitarColumna(cols, c) {
        var i = indiceColumna(cols, c);
        if (i === -1) { return false; }
        cols.splice(i, 1);
        return true;
    }

    /* Sube (delta -1) o baja (delta 1) la columna de la posición i. Devuelve la nueva posición o -1. */
    function moverColumna(cols, i, delta) {
        var j = i + delta;
        if (i < 0 || i >= cols.length || j < 0 || j >= cols.length) { return -1; }
        var x = cols[i]; cols[i] = cols[j]; cols[j] = x;
        return j;
    }

    /* ---------- Celdas de la vista previa ---------- */

    function formatearCelda(valor, tipo) {
        if (valor === null || valor === undefined || valor === '') { return ''; }
        switch (tipo) {
            case 'dinero': return '$ ' + Num.formatear(Number(valor));
            case 'entero': return Num.formatear(Number(valor));
            case 'fecha': return aDdMmAaaa(valor);
            default: return String(valor);
        }
    }

    function esNumerica(tipo) { return tipo === 'dinero' || tipo === 'entero'; }

    function textoConteo(total, unidad) {
        var u = unidad || { singular: 'fila', plural: 'filas' };
        if (total === 0) { return 'No hay ' + u.plural + ' con estos filtros.'; }
        return Num.formatear(total) + ' ' + (total === 1 ? u.singular : u.plural);
    }

    /* ---------- Excel: qué decirle a la persona cuando el servidor no devolvió un libro ---------- */

    function mensajeErrorExcel(status, tipoContenido, texto) {
        var t = /text\/plain/.test(tipoContenido || '') ? String(texto || '') : '';
        // IIS puede cambiar el cuerpo de un error por su propia página HTML.
        if (!t || /</.test(t)) {
            t = status === 401 ? 'Su sesión terminó. Inicie sesión de nuevo.'
              : status === 403 ? 'Su rol no tiene permiso para descargar este informe.'
              : 'No se pudo generar el informe. Intente de nuevo.';
        }
        return t.slice(0, 400);
    }

    function nombreSeguro(texto, max) {
        return String(texto || '').replace(/[<>\u0000-\u001f]/g, '').trim().slice(0, max || 100);
    }

    return {
        filtroDesdePantalla: filtroDesdePantalla,
        pantallaDesdeFiltro: pantallaDesdeFiltro,
        construirConfig: construirConfig,
        adaptarAFuente: adaptarAFuente,
        agregarColumna: agregarColumna,
        quitarColumna: quitarColumna,
        moverColumna: moverColumna,
        indiceColumna: indiceColumna,
        formatearCelda: formatearCelda,
        esNumerica: esNumerica,
        textoConteo: textoConteo,
        mensajeErrorExcel: mensajeErrorExcel,
        siguienteOrden: siguienteOrden,
        ordenVigente: ordenVigente,
        filaTotales: filaTotales,
        nombreSeguro: nombreSeguro,
        aDdMmAaaa: aDdMmAaaa
    };
})();

if (typeof module !== 'undefined' && module.exports) {
    module.exports = InformeGeneralLogica;
}

/* =====================================================================
 * La pantalla
 * ===================================================================== */
var InformeGeneral = (typeof window === 'undefined' || typeof jQuery === 'undefined') ? null : (function ($) {

    var L = InformeGeneralLogica;
    var URL_JSON = '../business/controller/class.informes.php';
    var URL_EXCEL = '../extensiones/informeGeneral.php';
    var URL_ = window.URL || window.webkitURL;

    var S = {
        cargado: false,
        cat: null,            // catálogo del servidor (lo que este rol puede)
        puede: {},
        limites: {},
        plantillas: [],
        guardarDisponible: true,
        plantilla: null,      // meta de la plantilla cargada (o null)
        modo: 'declaracion',
        impuesto: 'ica',
        filtros: {},          // clave -> valor tal como lo muestra la pantalla
        columnas: [],         // [{c, t}] en el orden del informe
        orden: [],
        sucio: false,
        pagina: 1,
        porPagina: 10,
        buscar: '',           // texto del buscador de la vista previa (lo aplica el servidor)
        ordenVista: null,     // {c, d}: el orden elegido en los encabezados de la vista previa
        excelNo: false,
        ocupado: false,
        pedido: 0
    };

    /* ---------- Servidor ---------- */

    function irAlLogin() { setTimeout(function () { window.location = '../index.php'; }, 2500); }

    /* POST a class.informes.php. Resuelve con r.datos; rechaza con {mensaje, sinSesion, sinPermiso}. */
    function llamar(funcion, datos) {
        var d = $.Deferred();
        $.ajax({
            url: URL_JSON, type: 'POST', dataType: 'json', global: false,
            data: $.extend({ funcion: funcion }, datos || {})
        }).done(function (r) {
            if (r && Number(r.ok) === 1) { d.resolve(r.datos); return; }
            var e = { mensaje: (r && r.mensaje) || 'No se pudo completar la operación. Intente de nuevo.',
                      sinSesion: !!(r && Number(r.sinSesion) === 1), sinPermiso: !!(r && Number(r.sinPermiso) === 1) };
            if (e.sinSesion) { irAlLogin(); }
            d.reject(e);
        }).fail(function (xhr) {
            var m = xhr && xhr.status === 405 ? 'Solicitud no válida.'
                  : (xhr && xhr.status === 0) ? 'Sin respuesta del servidor. Revise su conexión e intente de nuevo.'
                  : 'No se pudo procesar la solicitud. Intente de nuevo.';
            d.reject({ mensaje: m });
        });
        return d.promise();
    }

    function aviso(titulo, texto, tipo) {
        if (typeof swal === 'function') { swal({ type: tipo || 'error', title: titulo, text: texto }); }
        else { alert(titulo + '\n' + texto); }
    }

    function exito(titulo, texto) {
        if (typeof swal === 'function') { swal({ type: 'success', title: titulo, text: texto || '', timer: 1800, showConfirmButton: false }); }
    }

    function mensaje(texto, tipo) {
        var $m = $('#igMensaje');
        if (!texto) { $m.attr('hidden', true).empty(); return; }
        $m.attr('class', tipo === 'aviso' ? 'ig-aviso' : 'ig-error').text(texto).removeAttr('hidden');
    }

    /* ---------- Atajos del catálogo ---------- */

    function claveFuente() {
        return S.modo === 'contribuyente' ? 'contribuyentes' : (S.modo === 'agrupado' ? S.impuesto + ':agrup' : S.impuesto);
    }
    function fuente() { return (S.cat && S.cat.fuentes && S.cat.fuentes[claveFuente()]) || null; }
    function impuestosDisponibles() {
        return (S.cat.impuestos || []).filter(function (i) { return !!S.cat.fuentes[i.clave]; });
    }
    function modosDisponibles() {
        var hayDecl = impuestosDisponibles().length > 0;
        return (S.cat.modos || []).filter(function (m) {
            return m.clave === 'contribuyente' ? !!S.cat.fuentes.contribuyentes : hayDecl;
        });
    }
    function defColumna(c) {
        var f = fuente(); if (!f) { return null; }
        for (var i = 0; i < f.columnas.length; i++) { if (f.columnas[i].c === c) { return f.columnas[i]; } }
        return null;
    }

    /* ---------- Cambios ---------- */

    function huboCambio() {
        S.sucio = true;
        S.excelNo = false;
        $('#igExcel').prop('disabled', false);
        // La vista anterior ya no corresponde a lo que hay en pantalla.
        S.ordenVista = L.ordenVigente(S.ordenVista, S.columnas);
        $('#igResultado').empty();
        pintarBarra();
        mensaje('');
        pintarAyudaPlantilla();
    }

    /* ---------- Plantillas ---------- */

    function pintarPlantillas() {
        var $s = $('#igPlantilla').empty();
        var grupos = [
            { t: 'Del sistema', f: function (p) { return p.sistema; } },
            { t: 'Compartidas con el municipio', f: function (p) { return !p.sistema && !p.propia; } },
            { t: 'Mis plantillas', f: function (p) { return !p.sistema && p.propia; } }
        ];
        var actual = S.plantilla ? String(S.plantilla.id) : '';
        if (!actual) { $('<option>').val('').text('Informe a la medida (sin plantilla)').appendTo($s); }
        grupos.forEach(function (g) {
            var lista = S.plantillas.filter(g.f);
            if (!lista.length) { return; }
            var $g = $('<optgroup>').attr('label', g.t).appendTo($s);
            lista.forEach(function (p) {
                var nombre = p.nombre + (p.propia && p.visibilidad === 'MUNICIPIO' ? ' (compartida)' : '');
                $('<option>').val(String(p.id)).text(nombre).appendTo($g);
            });
        });
        $s.val(actual);
        if ($s.val() === null) { $s.val(''); }
        pintarAyudaPlantilla();
    }

    function pintarAyudaPlantilla() {
        var p = S.plantilla, t;
        if (!p) { t = 'Informe armado a mano. Para volver a usarlo, guárdelo como plantilla.'; }
        else if (p.sistema) { t = 'Plantilla del sistema: puede ajustarla y guardarla con otro nombre.'; }
        else if (p.propia) {
            t = p.visibilidad === 'MUNICIPIO' ? 'Su plantilla, compartida con todo el municipio.' : 'Su plantilla, solo usted la ve.';
        } else {
            t = 'Plantilla compartida' + (p.dueno ? ' por ' + p.dueno : '') + '.'
              + (p.puedeEditar ? '' : ' Para cambiarla, guárdela con otro nombre.');
        }
        if (p && p.descripcion) { t += ' ' + p.descripcion; }
        if (S.sucio) { t += ' Hay cambios sin guardar.'; }
        $('#igPlantillaAyuda').text(t);

        var puedeGuardar = S.guardarDisponible;
        $('#igGuardar, #igGuardarComo').toggle(puedeGuardar);
        $('#igBorrar').toggle(puedeGuardar);
        $('#igBorrar').prop('disabled', !(p && p.puedeBorrar));
        if (!puedeGuardar) {
            $('#igPlantillaAyuda').text(t + ' Guardar plantillas aún no está disponible en este municipio.');
        }
    }

    function recargarLista(idSeleccion) {
        return llamar('plantillas').then(function (d) {
            S.plantillas = d.plantillas || [];
            S.guardarDisponible = d.guardar !== false;
            if (idSeleccion) {
                var enc = S.plantillas.filter(function (p) { return String(p.id) === String(idSeleccion); })[0];
                if (enc) { S.plantilla = enc; }
            }
            pintarPlantillas();
        });
    }

    /* La configuración de una plantilla -> estado de la pantalla. */
    function aplicarConfig(cfg, meta, avisos) {
        S.modo = cfg.modo === 'contribuyente' ? 'contribuyente' : (cfg.modo === 'agrupado' ? 'agrupado' : 'declaracion');
        if (S.modo !== 'contribuyente' && cfg.impuesto && S.cat.fuentes[cfg.impuesto]) { S.impuesto = cfg.impuesto; }
        var f = fuente();
        S.filtros = {};
        var avisosLocales = (avisos || []).slice();
        (cfg.filtros || []).forEach(function (fl) {
            var def = (f.filtros || []).filter(function (d) { return d.c === fl.c; })[0];
            if (!def) { return; }
            var raw = L.pantallaDesdeFiltro(def, fl);
            if (def.tipo === 'entero' && fl.op === 'in' && Array.isArray(fl.v) && fl.v.length > 1) {
                var n = fl.v.map(Number).sort(function (a, b) { return a - b; });
                if (n[n.length - 1] - n[0] + 1 !== n.length) {
                    avisosLocales.push('El filtro «' + def.etiqueta + '» tenía años salteados: se dejó el rango de ' + n[0] + ' a ' + n[n.length - 1] + '.');
                }
            }
            S.filtros[fl.c] = raw;
        });
        S.columnas = (cfg.columnas || []).map(function (c) {
            var def = defColumna(c.c);
            var t = c.t || '';
            return { c: c.c, t: def && t === def.etiqueta ? '' : t };
        });
        S.orden = (cfg.orden || []).slice();
        // Otra plantilla es otro informe: la búsqueda y el orden de la vista anterior no le corresponden.
        S.ordenVista = null;
        S.buscar = '';
        $('#igBuscar').val('');
        $('#igBarra').attr('hidden', true);
        S.plantilla = meta || null;
        S.sucio = false;
        S.pagina = 1;
        S.excelNo = false;
        mostrarAvisos(avisosLocales);
        mensaje('');
        $('#igResultado').empty();
        renderTodo();
    }

    function mostrarAvisos(lista) {
        var $a = $('#igAvisos');
        if (!lista || !lista.length) { $a.attr('hidden', true).empty(); return; }
        $a.empty().attr('class', 'ig-aviso').removeAttr('hidden');
        lista.forEach(function (t) { $('<div>').text(t).appendTo($a); });
    }

    /* Abre una plantilla (del sistema o guardada). */
    function cargarPlantilla(id, claveFuenteSistema) {
        var datos = { id: id };
        var meta = S.plantillas.filter(function (p) { return String(p.id) === String(id); })[0];
        if (meta && meta.sistema) {
            var fs = (meta.fuentes || []).filter(function (k) { return !!S.cat.fuentes[k]; });
            var elegida = claveFuenteSistema && fs.indexOf(claveFuenteSistema) !== -1 ? claveFuenteSistema
                        : (fs.indexOf(S.impuesto) !== -1 && S.modo !== 'contribuyente' ? S.impuesto : fs[0]);
            if (!elegida) {
                aviso('No se pudo abrir', 'Su rol no puede ver los datos de esta plantilla.');
                return $.Deferred().reject().promise();
            }
            datos.fuente = elegida;
        }
        cargando(true);
        return llamar('plantilla', datos).then(function (d) {
            cargando(false);
            aplicarConfig(d.config, d.plantilla || meta, d.avisos);
        }, function (e) {
            cargando(false);
            aviso('No se pudo abrir la plantilla', e.mensaje);
            pintarPlantillas();
            return $.Deferred().reject(e).promise();
        });
    }

    function cargando(si) {
        $('#igContenido').css('opacity', si ? 0.6 : 1).attr('aria-busy', si ? 'true' : 'false');
    }

    function alElegirPlantilla() {
        var id = $('#igPlantilla').val();
        var anterior = S.plantilla ? String(S.plantilla.id) : '';
        if (id === '' || id === anterior && !S.sucio) { return; }
        var ir = function () { cargarPlantilla(id); };
        if (S.sucio && typeof swal === 'function') {
            swal({
                type: 'warning', title: 'Hay cambios sin guardar',
                text: 'Si abre otra plantilla se pierden los cambios del informe actual.',
                showCancelButton: true, confirmButtonText: 'Abrir de todos modos', cancelButtonText: 'Quedarme aquí'
            }).then(function (r) {
                if (r && r.value) { ir(); } else { $('#igPlantilla').val(anterior); }
            });
        } else { ir(); }
    }

    /* ---------- Bloque 1: impuesto y qué filas ---------- */

    function renderBloque1() {
        var modos = modosDisponibles();
        var $m = $('#igModos').empty();
        modos.forEach(function (m) {
            var $l = $('<label class="ig-tarjeta">');
            $('<input type="radio" name="igModo">').val(m.clave).prop('checked', m.clave === S.modo).appendTo($l);
            var $c = $('<span class="ig-tj">').appendTo($l);
            $('<b>').text(m.etiqueta).appendTo($c);
            $('<span>').text(m.descripcion || '').appendTo($c);
            $l.appendTo($m);
        });
        var imp = impuestosDisponibles();
        var $i = $('#igImpuesto').empty();
        imp.forEach(function (i) { $('<option>').val(i.clave).text(i.etiqueta).appendTo($i); });
        $i.val(S.impuesto);
        $('#igCampoImpuesto').toggle(S.modo !== 'contribuyente' && imp.length > 0);
    }

    /* Cambia el modo o el impuesto conservando lo que siga existiendo. */
    function cambiarFuente(modo, impuesto) {
        S.modo = modo;
        if (impuesto) { S.impuesto = impuesto; }
        if (modo !== 'contribuyente' && !S.cat.fuentes[S.impuesto]) { S.impuesto = impuestosDisponibles()[0].clave; }
        var q = L.adaptarAFuente(S, fuente());
        var partes = [];
        if (q.columnas) { partes.push(q.columnas + (q.columnas === 1 ? ' columna' : ' columnas')); }
        if (q.filtros) { partes.push(q.filtros + (q.filtros === 1 ? ' filtro' : ' filtros')); }
        mostrarAvisos(partes.length ? ['Se quitaron ' + partes.join(' y ') + ' que no existen en este informe.'] : []);
        S.pagina = 1;
        // Una plantilla de otro tipo de informe (por ejemplo el padrón) ya no describe lo que hay en pantalla.
        if (S.plantilla && S.plantilla.modo && S.plantilla.modo !== S.modo) { S.plantilla = null; }
        huboCambio();
        $('#igResultado').empty();
        if (!S.columnas.length && modo === 'agrupado') {
            // Sin plantilla del sistema para este modo: se parte de las columnas que lo definen.
            var def = ['documento', 'contribuyente', 'nDeclaraciones', 'aniosPresentados', 'ultimoAnioPagado', 'valorDeclarado', 'valorPagado'];
            def.forEach(function (c) { if (defColumna(c)) { S.columnas.push({ c: c, t: '' }); } });
            mostrarAvisos(['Se eligieron las columnas básicas de este tipo de informe porque no quedaba ninguna.']);
        }
        if (!S.columnas.length) {
            // Cambiar de modo puede dejar el informe sin columnas: se parte de la plantilla básica del modo.
            var slug = modo === 'contribuyente' ? 'sistema:padron' : 'sistema:todas';
            if (S.plantillas.some(function (p) { return p.id === slug; })) {
                cargarPlantilla(slug, claveFuente()).done(function () {
                    mostrarAvisos(['Se abrió la plantilla básica de este tipo de informe porque no quedaba ninguna columna.']);
                }).fail(renderTodo);
                return;
            }
        }
        renderTodo();
    }

    /* ---------- Bloque 2: filtros ---------- */

    function crearCampo(def) {
        var raw = S.filtros[def.c];
        var $campo = $('<div class="campo">');
        var idBase = 'igF_' + def.c;
        var lim = S.limites || {};
        var etiquetaId = idBase + '_et';

        function etiqueta(paraId) {
            return $(paraId ? '<label>' : '<span class="ig-etiqueta">').attr(paraId ? { 'for': paraId } : { id: etiquetaId }).text(def.etiqueta);
        }
        function ayuda() {
            if (def.ayuda) { $('<small class="ig-ayuda">').text(def.ayuda).appendTo($campo); }
        }

        if (def.tipo === 'entero' || def.tipo === 'dinero' || def.tipo === 'fecha') {
            etiqueta().appendTo($campo);
            var $par = $('<div class="ig-par" role="group">').attr('aria-labelledby', etiquetaId).appendTo($campo);
            var unico = def.tipo === 'entero' && (def.ops || []).indexOf('between') === -1;
            if (unico) {
                $('<input type="text">').attr({ id: idBase + '_d', 'data-c': def.c, 'data-k': 'd', autocomplete: 'off', inputmode: 'numeric',
                    maxlength: 20, placeholder: 'Número (sin dígito de verificación)', 'aria-label': def.etiqueta }).val(raw && raw.d ? raw.d : '').appendTo($par);
            }
            (unico ? [] : ['d', 'h']).forEach(function (k, i) {
                var $in = $('<input>').attr({ id: idBase + '_' + k, 'data-c': def.c, 'data-k': k, autocomplete: 'off',
                    'aria-label': def.etiqueta + (i === 0 ? ' desde' : ' hasta') });
                if (def.tipo === 'entero') {
                    $in.attr({ type: 'number', step: 1, placeholder: i === 0 ? 'Desde' : 'Hasta' });
                    if (def.min !== null && def.min !== undefined) { $in.attr('min', def.min); }
                    if (def.max !== null && def.max !== undefined) { $in.attr('max', def.max); }
                } else if (def.tipo === 'fecha') {
                    $in.attr({ type: 'date', min: '2000-01-01', max: '9999-12-31' });
                } else {
                    $in.attr({ type: 'text', inputmode: 'numeric', placeholder: i === 0 ? 'Desde $' : 'Hasta $', maxlength: 18 });
                }
                $in.val(raw && raw[k] ? raw[k] : '');
                $in.appendTo($par);
                if (i === 0) { $('<span aria-hidden="true">a</span>').appendTo($par); }
            });
        } else if (def.tipo === 'lista') {
            $campo.addClass('ig-ancho');
            etiqueta().appendTo($campo);
            var opciones = def.opciones || [];
            var $ch = $('<div class="ig-chips" role="group">').attr('aria-labelledby', etiquetaId).appendTo($campo);
            if (opciones.length > 14) { $ch.addClass('ig-largo'); }
            var marcados = Array.isArray(raw) ? raw.map(String) : [];
            opciones.forEach(function (o) {
                var on = marcados.indexOf(String(o.v)) !== -1;
                $('<button type="button" class="ig-chip">').attr({ 'aria-pressed': on ? 'true' : 'false', 'data-c': def.c, 'data-v': String(o.v) })
                    .text(o.t).appendTo($ch);
            });
        } else if (def.tipo === 'texto') {
            etiqueta(idBase).appendTo($campo);
            var soloDigitos = (def.ops || []).indexOf('igual') !== -1;
            $('<input type="text">').attr({ id: idBase, 'data-c': def.c, autocomplete: 'off', maxlength: lim.texto || 100,
                inputmode: soloDigitos ? 'numeric' : 'text', placeholder: soloDigitos ? 'Solo dígitos' : 'Contiene…' })
                .val(typeof raw === 'string' ? raw : '').appendTo($campo);
        } else if (def.tipo === 'booleano') {
            etiqueta(idBase).appendTo($campo);
            var $s = $('<select>').attr({ id: idBase, 'data-c': def.c }).appendTo($campo);
            [['', 'Todos'], ['true', 'Sí'], ['false', 'No']].forEach(function (o) { $('<option>').val(o[0]).text(o[1]).appendTo($s); });
            $s.val(typeof raw === 'string' ? raw : '');
        } else {
            return null;
        }
        ayuda();
        return $campo;
    }

    function renderFiltros() {
        var f = fuente(), $c = $('#igFiltros').empty();
        if (!f) { return; }
        (f.gruposFiltros || []).forEach(function (g) {
            var lista = (f.filtros || []).filter(function (d) { return d.grupo === g.clave; });
            if (!lista.length) { return; }
            var $sg = $('<div class="ig-subgrupo">').appendTo($c);
            $('<p class="ig-rotulo">').text(g.etiqueta).appendTo($sg);
            var $r = $('<div class="ig-rejilla">').appendTo($sg);
            lista.forEach(function (def) { var $x = crearCampo(def); if ($x) { $x.appendTo($r); } });
        });
    }

    function defFiltro(c) {
        var f = fuente(); if (!f) { return null; }
        return (f.filtros || []).filter(function (d) { return d.c === c; })[0] || null;
    }

    /* Lee de la pantalla el filtro `c` y lo deja en el estado (o lo quita si quedó vacío). */
    function leerFiltro(c) {
        var def = defFiltro(c); if (!def) { return; }
        var id = '#igF_' + c;
        var raw;
        if (def.tipo === 'entero' || def.tipo === 'dinero' || def.tipo === 'fecha') {
            raw = { d: $(id + '_d').val(), h: $(id + '_h').val() };
            if (!raw.d && !raw.h) { raw = null; }
        } else if (def.tipo === 'lista') {
            raw = $('#igFiltros .ig-chip[data-c="' + c + '"][aria-pressed="true"]').map(function () { return $(this).attr('data-v'); }).get();
            if (!raw.length) { raw = null; }
        } else {
            raw = $(id).val();
            if (raw === '' || raw === null) { raw = null; }
        }
        if (raw === null) { delete S.filtros[c]; } else { S.filtros[c] = raw; }
    }

    /* ---------- Bloque 3: columnas ---------- */

    function renderGrupos() {
        var f = fuente(), $g = $('#igGrupos').empty();
        if (!f) { return; }
        (f.grupos || []).forEach(function (g) {
            var cols = (f.columnas || []).filter(function (c) { return c.grupo === g.clave; });
            if (!cols.length) { return; }
            var $fs = $('<fieldset class="ig-grupo">').appendTo($g);
            $('<legend>').text(g.etiqueta).appendTo($fs);
            cols.forEach(function (c) {
                var $l = $('<label>').appendTo($fs);
                if (c.ayuda) { $l.attr('title', c.ayuda); }
                $('<input type="checkbox">').attr('data-c', c.c).prop('checked', L.indiceColumna(S.columnas, c.c) !== -1).appendTo($l);
                $('<span>').text(c.etiqueta).appendTo($l);
            });
        });
    }

    function sincronizarCasillas() {
        $('#igGrupos input[type=checkbox]').each(function () {
            $(this).prop('checked', L.indiceColumna(S.columnas, $(this).attr('data-c')) !== -1);
        });
    }

    function renderPanel(enfocar) {
        var $ul = $('#igLista').empty();
        S.columnas.forEach(function (col, i) {
            var def = defColumna(col.c);
            if (!def) { return; }
            var $li = $('<li>').attr('data-i', i).appendTo($ul);
            $('<span class="ig-n" aria-hidden="true">').text(i + 1).appendTo($li);
            $('<input type="text">').attr({ 'aria-label': 'Título de la columna ' + (i + 1) + ', ' + def.etiqueta, placeholder: def.etiqueta,
                maxlength: (S.limites && S.limites.titulo) || 60, autocomplete: 'off' }).val(col.t || '').appendTo($li);
            $('<button type="button" class="ig-icono ig-sube">').attr({ 'aria-label': 'Subir ' + def.etiqueta, title: 'Subir' })
                .prop('disabled', i === 0).html('<i class="fa fa-arrow-up" aria-hidden="true"></i>').appendTo($li);
            $('<button type="button" class="ig-icono ig-baja">').attr({ 'aria-label': 'Bajar ' + def.etiqueta, title: 'Bajar' })
                .prop('disabled', i === S.columnas.length - 1).html('<i class="fa fa-arrow-down" aria-hidden="true"></i>').appendTo($li);
            $('<button type="button" class="ig-icono ig-quita">').attr({ 'aria-label': 'Quitar ' + def.etiqueta, title: 'Quitar' })
                .html('<i class="fa fa-times" aria-hidden="true"></i>').appendTo($li);
        });
        $('#igCuenta').text(S.columnas.length);
        $('#igQuitarTodas').prop('disabled', !S.columnas.length);
        if (!S.columnas.length) {
            $('<li class="ig-sub" style="display:block">').text('Marque las columnas que quiere en el informe.').appendTo($ul);
        }
        if (enfocar) {
            var $li2 = $ul.children('li').eq(enfocar.i);
            var $b = $li2.find(enfocar.cual === 'baja' ? '.ig-baja' : '.ig-sube');
            if ($b.prop('disabled')) { $b = $li2.find(enfocar.cual === 'baja' ? '.ig-sube' : '.ig-baja'); }
            $b.trigger('focus');
        }
    }

    function renderOrden() {
        var f = fuente(), $s = $('#igOrdenCol').empty();
        if (!f) { return; }
        $('<option>').val('').text('Orden predeterminado').appendTo($s);
        f.columnas.filter(function (c) { return c.ordenable; }).forEach(function (c) {
            $('<option>').val(c.c).text(c.etiqueta).appendTo($s);
        });
        var o = S.orden[0];
        $s.val(o ? o.c : '');
        if ($s.val() === null) { $s.val(''); }
        $('#igOrdenDir').val(o && o.d === 'desc' ? 'desc' : 'asc').prop('disabled', !o);
    }

    function renderTodo() {
        if (!S.cat) { return; }
        renderBloque1();
        renderFiltros();
        renderGrupos();
        renderPanel();
        renderOrden();
        pintarPlantillas();
    }

    /* ---------- Configuración -> servidor ---------- */

    function configLista() {
        var r = L.construirConfig(S, fuente());
        if (r.errores.length) { mensaje(r.errores[0]); return null; }
        return r.config;
    }

    /* ---------- Vista previa ---------- */

    function vista(pagina) {
        var cfg = configLista();
        if (!cfg) { return; }
        mensaje('');
        S.pagina = pagina || 1;
        var mio = ++S.pedido;
        var $b = $('#igVista');
        var html = $b.html();
        $b.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Consultando…');
        var datos = { config: JSON.stringify(cfg), pagina: S.pagina, porPagina: S.porPagina };
        // El buscador y el orden de los encabezados los aplica el servidor (no solo a la página que se ve).
        if (S.buscar) { datos.buscar = S.buscar; }
        if (S.ordenVista) { datos.ordenC = S.ordenVista.c; datos.ordenD = S.ordenVista.d; }
        llamar('vista', datos).done(function (d) {
            if (mio !== S.pedido) { return; }
            S.pagina = d.pagina || 1;
            S.excelNo = !!(d.excel && d.excel.permitido === false);
            pintarVista(d);
            pintarBarra(true);
        }).fail(function (e) {
            if (mio !== S.pedido) { return; }
            $('#igResultado').empty();
            mensaje(e.mensaje);
        }).always(function () {
            if (mio === S.pedido) { $b.prop('disabled', false).html(html); }
        });
    }

    function pintarVista(d) {
        var $r = $('#igResultado').empty();
        if (d.filtros) { $('<p class="ig-filtros-txt">').text('Filtros: ' + d.filtros).appendTo($r); }
        (d.avisos || []).forEach(function (t) { $('<div class="ig-aviso">').text(t).appendTo($r); });
        $('<p class="ig-conteo" aria-live="polite">').append($('<b>').text(L.textoConteo(d.total, d.unidad)))
            .append(d.total > 0 ? document.createTextNode(' · página ' + d.pagina + ' de ' + d.paginas) : '').appendTo($r);

        if (S.excelNo) {
            var l = S.limites || {};
            mensaje('Este informe es demasiado grande para el Excel (hasta ' + NumerosCOP.formatear(l.filasExcel || 50000) + ' filas y '
                + NumerosCOP.formatear(l.celdasExcel || 200000) + ' celdas). Agregue filtros, por ejemplo un año o un rango de fechas, o quite columnas.', 'aviso');
        }
        $('#igExcel').prop('disabled', S.excelNo || d.total === 0);

        if (d.total === 0) { return; }
        var cols = d.columnas || [];
        var $caja = $('<div class="ig-tabla-caja">').appendTo($r);
        var $t = $('<table class="ig-tabla">').appendTo($caja);
        var $tr = $('<tr>').appendTo($('<thead>').appendTo($t));
        cols.forEach(function (c) {
            var $th = $('<th scope="col">').toggleClass('ig-der', c.alinea === 'R').appendTo($tr);
            var o = S.ordenVista && S.ordenVista.c === c.c ? S.ordenVista : null;
            if (c.ordenable) {
                // Un botón dentro del encabezado: se opera con teclado y el lector de pantalla dice el sentido.
                $th.attr('aria-sort', o ? (o.d === 'asc' ? 'ascending' : 'descending') : 'none');
                $('<button type="button" class="ig-ordena">').attr({ 'data-c': c.c, title: 'Ordenar por esta columna' })
                    .text(c.t + (o ? (o.d === 'asc' ? ' ▲' : ' ▼') : '')).appendTo($th);
            } else {
                $th.text(c.t);
            }
        });
        var $tb = $('<tbody>').appendTo($t);
        (d.filas || []).forEach(function (fila) {
            var $f = $('<tr>').appendTo($tb);
            cols.forEach(function (c, i) {
                var txt = L.formatearCelda(fila[i], c.tipo);
                $('<td>').toggleClass('ig-der', c.alinea === 'R').text(txt).attr('title', txt.length > 40 ? txt : null).appendTo($f);
            });
        });
        // Las sumas son de TODAS las filas que cumplen filtros y búsqueda, no solo de esta página.
        var ft = L.filaTotales(cols, d.totales, d.total, d.unidad);
        if (ft) {
            var $tf = $('<tr>').appendTo($('<tfoot>').appendTo($t));
            cols.forEach(function (c, i) {
                $('<td>').toggleClass('ig-der', c.alinea === 'R').text(ft.celdas[i]).appendTo($tf);
            });
            if (ft.rotuloAparte) {
                $('<tr>').appendTo($t.children('tfoot')).append($('<td>').attr('colspan', cols.length).text(ft.rotuloAparte));
            }
        }

        var $pie = $('<div class="ig-pie">').appendTo($r);
        $('<button type="button" class="ig-btn" id="igAnterior">').html('<i class="fa fa-chevron-left" aria-hidden="true"></i> Anterior')
            .prop('disabled', d.pagina <= 1).appendTo($pie);
        $('<button type="button" class="ig-btn" id="igSiguiente">').html('Siguiente <i class="fa fa-chevron-right" aria-hidden="true"></i>')
            .prop('disabled', d.pagina >= d.paginas).appendTo($pie);
        $('<label for="igPorPagina" style="margin:0 0 0 8px">Filas por página</label>').appendTo($pie);
        var $pp = $('<select id="igPorPagina">').appendTo($pie);
        ((S.limites && S.limites.filasPorPagina) || [5, 10, 20, 50, 100]).forEach(function (n) {
            $('<option>').val(n).text(n).appendTo($pp);
        });
        $pp.val(S.porPagina);
        $('<span>').text('Es una vista previa: el Excel trae todas las filas'
            + (S.buscar || S.ordenVista ? ' (o las de la búsqueda y el orden, si deja marcada la casilla de arriba).' : '.')).appendTo($pie);
    }

    /* La barra de la vista previa (buscador y casilla del Excel): fuera de #igResultado para que el campo no pierda el foco al repintar. */
    function pintarBarra(mostrar) {
        var $b = $('#igBarra');
        if (mostrar) { $b.removeAttr('hidden'); }
        // En el padrón y en el modo agrupado no hay un número de declaración que buscar.
        $('#igBuscar').attr('placeholder', S.modo === 'declaracion' ? 'Documento, nombre o número de declaración' : 'Documento o nombre');
        $('#igBuscarLimpiar').toggle(!!S.buscar);
        var hay = !!(S.buscar || S.ordenVista);
        $('#igExcelVistaCaja').toggle(hay);
        var t = '';
        if (S.ordenVista) {
            var def = defColumna(S.ordenVista.c);
            t = 'Orden de la vista previa: ' + (def ? def.etiqueta : '') + (S.ordenVista.d === 'asc' ? ' (ascendente)' : ' (descendente)') + '. ';
        }
        $('#igOrdenVistaTxt').text(t);
        $('#igOrdenVistaQuitar').toggle(!!S.ordenVista);
    }

    var temporizadorBusqueda = null;

    /* ---------- Excel ---------- */

    function excel() {
        var cfg = configLista();
        if (!cfg) { return; }
        mensaje('');
        var $b = $('#igExcel'), html = $b.html();
        $b.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Generando…');
        var libre = function () { $b.prop('disabled', S.excelNo).html(html); };
        var xhr = new XMLHttpRequest();
        xhr.open('POST', URL_EXCEL);
        xhr.responseType = 'blob';
        xhr.onload = function () {
            libre();
            var tipo = xhr.getResponseHeader('Content-Type') || '';
            if (xhr.status !== 200 || tipo.indexOf('spreadsheetml') === -1) {
                var lector = new FileReader();
                lector.onload = function () {
                    mensaje(L.mensajeErrorExcel(xhr.status, tipo, String(lector.result || '')));
                    if (xhr.status === 401) { irAlLogin(); }
                };
                lector.onerror = function () { mensaje(L.mensajeErrorExcel(xhr.status, '', '')); };
                lector.readAsText(xhr.response);
                return;
            }
            var nombre = 'Informe_general.xlsx';
            var m = /filename="([^"]+)"/.exec(xhr.getResponseHeader('Content-Disposition') || '');
            if (m) { nombre = m[1]; }
            var a = document.createElement('a');
            a.href = URL_.createObjectURL(xhr.response);
            a.download = nombre;
            document.body.appendChild(a);
            a.click();
            setTimeout(function () { URL_.revokeObjectURL(a.href); a.remove(); }, 1000);
        };
        xhr.onerror = function () {
            libre();
            mensaje('Sin respuesta del servidor. Revise su conexión e intente de nuevo.');
        };
        var fd = new FormData();
        fd.append('config', JSON.stringify(cfg));
        if ($('#igExcelVista').prop('checked')) {
            if (S.buscar) { fd.append('buscar', S.buscar); }
            if (S.ordenVista) { fd.append('ordenC', S.ordenVista.c); fd.append('ordenD', S.ordenVista.d); }
        }
        var titulo = S.plantilla ? L.nombreSeguro(S.plantilla.nombre, 100) : '';
        fd.append('titulo', titulo || 'Informe general');
        xhr.send(fd);
    }

    /* ---------- Guardar y borrar ---------- */

    function abrirModalGuardar() {
        if (!configLista()) { return; }
        var p = S.plantilla;
        $('#igNombre').val(p && !p.sistema ? p.nombre + ' (copia)' : '').attr('maxlength', (S.limites && S.limites.nombrePlantilla) || 100);
        $('#igDescripcion').val(p && !p.sistema ? (p.descripcion || '') : '').attr('maxlength', (S.limites && S.limites.descripcionPlantilla) || 300);
        $('input[name=igVis][value=PRIVADA]').prop('checked', true);
        $('#igVisMunicipio').toggle(!!S.puede.plantillas);
        $('#igModalError').attr('hidden', true).empty();
        $('#igModalGuardar').modal('show');
    }

    function guardarNueva(ev) {
        ev.preventDefault();
        var nombre = String($('#igNombre').val() || '').trim();
        var err = function (t) { $('#igModalError').text(t).removeAttr('hidden'); $('#igNombre').trigger('focus'); };
        if (nombre === '') { err('Escriba un nombre para la plantilla.'); return; }
        if (/[<>]/.test(nombre)) { err('El nombre no puede llevar los signos < ni >.'); return; }
        var cfg = configLista();
        if (!cfg) { $('#igModalGuardar').modal('hide'); return; }
        var $ok = $('#igModalOk').prop('disabled', true);
        llamar('guardar', {
            nombre: nombre, descripcion: String($('#igDescripcion').val() || '').trim(),
            visibilidad: S.puede.plantillas ? $('input[name=igVis]:checked').val() : 'PRIVADA',
            config: JSON.stringify(cfg)
        }).done(function (meta) {
            $('#igModalGuardar').modal('hide');
            S.plantilla = meta; S.sucio = false;
            recargarLista(meta.id).always(function () { exito('Plantilla guardada', nombre); });
        }).fail(function (e) { err(e.mensaje); })
          .always(function () { $ok.prop('disabled', false); });
    }

    function guardar() {
        var p = S.plantilla;
        if (!(p && !p.sistema && p.puedeEditar)) { abrirModalGuardar(); return; }
        var cfg = configLista();
        if (!cfg) { return; }
        var $b = $('#igGuardar').prop('disabled', true);
        llamar('guardar', { id: p.id, nombre: p.nombre, descripcion: p.descripcion || '', visibilidad: p.visibilidad, config: JSON.stringify(cfg) })
            .done(function (meta) {
                S.plantilla = meta; S.sucio = false;
                recargarLista(meta.id).always(function () { exito('Plantilla actualizada', meta.nombre); });
            })
            .fail(function (e) { aviso('No se guardó', e.mensaje); })
            .always(function () { $b.prop('disabled', false); });
    }

    function borrar() {
        var p = S.plantilla;
        if (!(p && p.puedeBorrar)) { return; }
        swal({
            type: 'warning', title: '¿Borrar la plantilla?', text: p.nombre
                + (p.visibilidad === 'MUNICIPIO' ? ' (compartida: dejará de verla todo el municipio)' : ''),
            showCancelButton: true, confirmButtonText: 'Sí, borrar', confirmButtonColor: '#DC2626', cancelButtonText: 'Cancelar'
        }).then(function (r) {
            if (!r || !r.value) { return; }
            llamar('borrar', { id: p.id }).done(function () {
                S.plantilla = null;
                recargarLista().always(function () { exito('Plantilla borrada', p.nombre); });
                pintarPlantillas();
            }).fail(function (e) { aviso('No se borró', e.mensaje); });
        });
    }

    /* ---------- Carga inicial ---------- */

    function mostrarError(texto) {
        $('#igCarga').attr('hidden', true);
        $('#igContenido').attr('hidden', true);
        $('#igError').removeAttr('hidden').empty().append(
            $('<div class="estado-vacio es-error">').append(
                $('<div class="ev-icono"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i></div>'),
                $('<div class="ev-titulo">').text('No se pudo abrir el Informe general'),
                $('<div class="ev-texto">').text(texto),
                $('<button type="button" class="ig-btn" id="igReintentar" style="margin-top:12px">Intentar de nuevo</button>')));
    }

    function iniciarGeneral() {
        if (S.cargado || S.ocupado) { return; }
        S.ocupado = true;
        $('#igError').attr('hidden', true);
        $('#igCarga').removeAttr('hidden');
        $.when(llamar('catalogo'), llamar('plantillas')).done(function (cat, pl) {
            S.cat = cat;
            S.puede = cat.puede || {};
            S.limites = cat.limites || {};
            S.porPagina = 10;
            S.plantillas = pl.plantillas || [];
            S.guardarDisponible = pl.guardar !== false && S.puede.guardar !== false;
            if (!modosDisponibles().length) {
                S.ocupado = false;
                mostrarError('Su rol no puede ver los datos de ningún impuesto en el Informe general. Pídale el permiso al administrador.');
                return;
            }
            var imp = impuestosDisponibles();
            S.impuesto = imp.length ? imp[0].clave : 'ica';
            S.modo = imp.length ? 'declaracion' : 'contribuyente';
            S.cargado = true;
            $('#igCarga').attr('hidden', true);
            $('#igContenido').removeAttr('hidden');
            // Se abre con la plantilla «Todas las declaraciones presentadas» (o la primera que sirva).
            var inicial = S.plantillas.filter(function (p) { return p.id === (imp.length ? 'sistema:todas' : 'sistema:padron'); })[0]
                       || S.plantillas[0];
            if (inicial) {
                cargarPlantilla(inicial.id).done(function () { vista(1); })
                    .fail(function () { renderTodo(); });
            } else { renderTodo(); }
            S.ocupado = false;
        }).fail(function (e) {
            S.ocupado = false;
            mostrarError((e && e.mensaje) || 'Intente de nuevo.');
        });
    }

    /* ---------- Eventos ---------- */

    function enlazar() {
        $('#igPlantilla').on('change', alElegirPlantilla);
        $('#igGuardar').on('click', guardar);
        $('#igGuardarComo').on('click', abrirModalGuardar);
        $('#igBorrar').on('click', borrar);
        $('#igFormGuardar').on('submit', guardarNueva);
        $('#igModalGuardar').on('shown.bs.modal', function () { $('#igNombre').trigger('focus'); });
        $('#igError').on('click', '#igReintentar', iniciarGeneral);

        $('#igImpuesto').on('change', function () { cambiarFuente('declaracion', $(this).val()); });
        $('#igModos').on('change', 'input[name=igModo]', function () { cambiarFuente($(this).val()); });

        // Filtros: un manejador por tipo de control, todos delegados.
        $('#igFiltros').on('input change', 'input, select', function (ev) {
            var c = $(this).attr('data-c'); if (!c) { return; }
            var def = defFiltro(c);
            if (def && $(this).attr('type') === 'text' && $(this).attr('inputmode') === 'numeric') {
                // Solo dígitos; las cifras en pesos se muestran con sus puntos de miles al salir de la casilla.
                var n = String($(this).val()).replace(/[^0-9]/g, '');
                if (def.tipo === 'dinero' && ev.type === 'change') {
                    n = n === '' ? '' : NumerosCOP.formatear(parseInt(n, 10));
                }
                if (n !== $(this).val() && !(def.tipo === 'dinero' && ev.type === 'input')) { $(this).val(n); }
            }
            leerFiltro(c);
            huboCambio();
        });
        $('#igFiltros').on('click', '.ig-chip', function () {
            var on = $(this).attr('aria-pressed') === 'true';
            $(this).attr('aria-pressed', on ? 'false' : 'true');
            leerFiltro($(this).attr('data-c'));
            huboCambio();
        });

        // Columnas
        $('#igGrupos').on('change', 'input[type=checkbox]', function () {
            var c = $(this).attr('data-c');
            if ($(this).prop('checked')) {
                if (!L.agregarColumna(S.columnas, c, S.limites.columnas || 60)) {
                    $(this).prop('checked', false);
                    mensaje('Un informe admite hasta ' + (S.limites.columnas || 60) + ' columnas.');
                    return;
                }
            } else { L.quitarColumna(S.columnas, c); }
            huboCambio();
            renderPanel();
            renderOrden();
        });
        $('#igLista').on('input', 'input[type=text]', function () {
            var i = Number($(this).closest('li').attr('data-i'));
            if (S.columnas[i]) { S.columnas[i].t = $(this).val(); huboCambio(); }
        });
        $('#igLista').on('click', '.ig-sube, .ig-baja', function () {
            var i = Number($(this).closest('li').attr('data-i'));
            var sube = $(this).hasClass('ig-sube');
            var j = L.moverColumna(S.columnas, i, sube ? -1 : 1);
            if (j === -1) { return; }
            huboCambio();
            renderPanel({ i: j, cual: sube ? 'sube' : 'baja' });
        });
        $('#igLista').on('click', '.ig-quita', function () {
            var i = Number($(this).closest('li').attr('data-i'));
            S.columnas.splice(i, 1);
            huboCambio();
            sincronizarCasillas();
            renderPanel();
            renderOrden();
            $('#igQuitarTodas').trigger('focus');
        });
        $('#igQuitarTodas').on('click', function () {
            S.columnas = [];
            huboCambio();
            sincronizarCasillas();
            renderPanel();
        });
        $('#igOrdenCol').on('change', function () {
            var c = $(this).val();
            S.orden = c ? [{ c: c, d: $('#igOrdenDir').val() }] : [];
            $('#igOrdenDir').prop('disabled', !c);
            huboCambio();
        });
        $('#igOrdenDir').on('change', function () {
            if (S.orden[0]) { S.orden[0].d = $(this).val(); huboCambio(); }
        });

        // Buscador y orden de la vista previa
        $('#igBuscar').on('input', function () {
            var t = String($(this).val() || '');
            clearTimeout(temporizadorBusqueda);
            temporizadorBusqueda = setTimeout(function () {
                if (t.trim() === S.buscar) { return; }
                S.buscar = t.trim();
                pintarBarra();
                vista(1);
            }, 450);
        });
        $('#igBuscarLimpiar').on('click', function () {
            clearTimeout(temporizadorBusqueda);
            S.buscar = '';
            $('#igBuscar').val('').trigger('focus');
            pintarBarra();
            vista(1);
        });
        $('#igOrdenVistaQuitar').on('click', function () { S.ordenVista = null; pintarBarra(); vista(1); });
        $('#igResultado').on('click', '.ig-ordena', function () {
            S.ordenVista = L.siguienteOrden(S.ordenVista, $(this).attr('data-c'));
            pintarBarra();
            vista(1);
        });

        // Resultado
        $('#igVista').on('click', function () { vista(1); });
        $('#igExcel').on('click', excel);
        $('#igResultado').on('click', '#igAnterior', function () { vista(S.pagina - 1); });
        $('#igResultado').on('click', '#igSiguiente', function () { vista(S.pagina + 1); });
        $('#igResultado').on('change', '#igPorPagina', function () { S.porPagina = Number($(this).val()) || 10; vista(1); });
    }

    /* ---------- Pestañas ---------- */

    function seleccionar(cual) {
        var general = cual === 'general';
        $('#tabRapido').attr({ 'aria-selected': general ? 'false' : 'true', tabindex: general ? -1 : 0 });
        $('#tabGeneral').attr({ 'aria-selected': general ? 'true' : 'false', tabindex: general ? 0 : -1 });
        $('#panelRapido').prop('hidden', general);
        $('#panelGeneral').prop('hidden', !general);
        if (general) {
            $('#headerPageTitle').text('Informe general');
            iniciarGeneral();
        } else {
            $('#headerPageTitle').text('Informes');
        }
    }

    function iniciarPestanas() {
        var puede = typeof erpPuede === 'function' && erpPuede('alcaldia.informes.personalizados');
        if (!puede) { $('#igPestanas').hide(); return; }   // la pantalla queda como siempre
        $('#tabGeneral').removeAttr('hidden');
        $('#igPestanas').on('click', '[role=tab]', function () { seleccionar(this.id === 'tabGeneral' ? 'general' : 'rapido'); });
        $('#igPestanas').on('keydown', '[role=tab]', function (e) {
            var k = e.key;
            if (['ArrowLeft', 'ArrowRight', 'Home', 'End'].indexOf(k) === -1) { return; }
            e.preventDefault();
            var haciaGeneral = k === 'ArrowRight' || k === 'End';
            seleccionar(haciaGeneral ? 'general' : 'rapido');
            $(haciaGeneral ? '#tabGeneral' : '#tabRapido').trigger('focus');
        });
    }

    $(function () {
        if (!$('#panelGeneral').length) { return; }
        enlazar();
        iniciarPestanas();
    });

    return { estado: S };
})(window.jQuery);
