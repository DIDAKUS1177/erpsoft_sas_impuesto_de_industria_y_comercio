/*
 * Recaudo por codigo de barras (archivo Asobancaria).
 *
 * El flujo es de DOS pasos a proposito: primero se revisa el archivo y se
 * muestra que va a pasar, y solo entonces se habilita "Aplicar". Marcar
 * declaraciones como pagadas es irreversible en la practica.
 *
 * Despues de aplicar, lo que no se pudo aplicar solo (un numero que es de dos
 * declaraciones pendientes, o de una ya pagada) se asigna a mano, fila por
 * fila, con el comprobante del banco (funcion 4). Para volver otro dia sobre lo
 * pendiente se carga de nuevo el mismo archivo.
 */

var RUTA_RECAUDO = '../business/controller/class.recaudo.php';

/** El nombre en disco que devolvio la previsualizacion, para poder aplicarlo. */
var archivoRevisado = null;
var nombreOriginal  = null;

/** El archivo en disco sobre el que se asignan a mano los pagos pendientes, y
 *  si ya se aplico (solo entonces se puede: ver class.recaudo.php, funcion 4). */
var archivoEnDisco  = null;
var archivoAplicado = false;

/** Las dos tablas que pueden llevar boton de asignar. Cada fila se marca por
 *  su posicion: dos lineas con la misma referencia y el mismo valor son dos
 *  pagos, y asignar una no debe dar por asignada la otra. */
var listas = { aplicables: [], revisar: [] };

function escapar(v) {
    if (v === null || v === undefined) { return ''; }
    return String(v)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function pesos(v) {
    var n = Number(v) || 0;
    return '$' + n.toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function pintarFilas(idCuerpo, lista, columnas, vacio) {
    var filas = '';
    (lista || []).forEach(function (x, i) {
        filas += '<tr>' + columnas.map(function (c) { return '<td>' + c(x, i) + '</td>'; }).join('') + '</tr>';
    });
    if (!filas) {
        filas = '<tr><td colspan="' + columnas.length + '" class="text-center text-muted py-3">' + vacio + '</td></tr>';
    }
    $('#' + idCuerpo).html(filas);
}

/** Boton para asignar a mano la fila i de una lista a la candidata c. */
function botonAsignar(lista, i, c, cand) {
    return '<button type="button" class="btn btn-sm btn-outline-primary mb-1 js-asignar"'
         + ' data-lista="' + lista + '" data-i="' + i + '" data-c="' + c + '">'
         + 'Aplicar a ' + escapar(cand.etiqueta) + ': ' + escapar(cand.contribuyente || cand.documento || '')
         + ' (total ' + pesos(cand.total) + ')</button>';
}

function pintarResumen(d) {

    var avisos = '';

    if (d.banco && d.banco.desconocido) {
        avisos += '<div class="alert alert-warning py-2 mb-2">' +
                  'El archivo viene del banco con código <b>' + escapar(d.banco.codigo) + '</b>, que no está ' +
                  'en el catálogo. Los pagos se pueden aplicar igual, pero quedará sin nombre de banco: ' +
                  'agréguelo al catálogo para que quede completo.</div>';
    }

    if (d.yaSubido) {
        avisos += '<div class="alert alert-info py-2 mb-2">' +
                  'Este archivo <b>ya se aplicó</b> el ' + escapar(d.yaSubido.fecha) +
                  ' como «' + escapar(d.yaSubido.nombre) + '». No se vuelve a aplicar: lo que quedó ' +
                  'pendiente se asigna fila por fila en las tablas de abajo.</div>';
    }

    var banco = (d.banco && d.banco.nombre) ? d.banco.nombre : ('código ' + escapar(d.banco ? d.banco.codigo : '?'));

    $('#resumenRecaudo').html(
        avisos +
        '<div class="row">' +
            '<div class="col-md-3"><small class="text-muted d-block">Banco</small><b>' + escapar(banco) + '</b></div>' +
            '<div class="col-md-3"><small class="text-muted d-block">Fecha de pago</small><b>' + escapar(d.encabezado.fecha) + '</b></div>' +
            '<div class="col-md-3"><small class="text-muted d-block">Convenio (EAN)</small><b>' + escapar(d.ean) + '</b></div>' +
            '<div class="col-md-3"><small class="text-muted d-block">Total del archivo</small><b>' + pesos(d.sumas.valor) + '</b></div>' +
        '</div>' +
        '<div class="row mt-3">' +
            '<div class="col-md-3"><small class="text-muted d-block">Registros</small><b>' + d.sumas.registros + '</b></div>' +
            '<div class="col-md-3"><small class="text-muted d-block">' + (d.yaSubido ? 'Por asignar a mano' : 'Se van a aplicar') +
                '</small><b class="text-success">' + (d.aplicables || []).length + '</b></div>' +
            '<div class="col-md-3"><small class="text-muted d-block">Ya estaban pagadas</small><b>' + (d.yaPagadas || []).length + '</b></div>' +
            '<div class="col-md-3"><small class="text-muted d-block">Sin declaración</small><b class="text-danger">' + (d.sinDeclaracion || []).length + '</b></div>' +
        '</div>' +
        '<div class="row mt-3">' +
            '<div class="col-md-3"><small class="text-muted d-block">Sin presentar (no se aplican)</small><b class="text-danger">' + (d.sinPresentar || []).length + '</b></div>' +
            '<div class="col-md-3"><small class="text-muted d-block">Revisar a mano (no se aplican solos)</small><b class="text-danger">' + (d.revisar || []).length + '</b></div>' +
        '</div>'
    );

    listas.aplicables = d.aplicables || [];
    listas.revisar    = d.revisar || [];
    pintarAplicables();
    pintarRevisar();

    pintarFilas('tbodyYaPagadas', d.yaPagadas, [
        function (x) { return escapar(x.referencia) + (x.etiqueta ? ' <small class="text-muted">(' + escapar(x.etiqueta) + ')</small>' : ''); },
        function (x) { return pesos(x.valor); }
    ], 'Ninguna.');

    pintarFilas('tbodySinDeclaracion', d.sinDeclaracion, [
        function (x) { return escapar(x.referencia); },
        function (x) { return pesos(x.valor); }
    ], 'Ninguna: todas las referencias del archivo existen en el sistema.');

    // Pagos que el banco reporta contra una declaracion que existe pero NO
    // esta presentada. No se aplican -ver la nota larga en class.recaudo.php-
    // y quedan listados para conciliacion manual. Se dice de que modulo son:
    // los tres numeran igual.
    pintarFilas('tbodySinPresentar', d.sinPresentar, [
        function (x) { return escapar(x.referencia) + (x.etiqueta ? ' <small class="text-muted">(' + escapar(x.etiqueta) + ')</small>' : ''); },
        function (x) { return pesos(x.valor); }
    ], 'Ninguna: todos los pagos del archivo corresponden a declaraciones presentadas.');

    $('#cajaResumen').show();
}

/*
 * Los que se aplican solos. Solo tienen boton cuando el archivo YA se aplico y
 * se volvio a cargar: un pago que ahora es inequivoco (la declaracion se
 * presento despues, o el archivo se aplico con la version que solo miraba el
 * ICA) ya no puede pasar por "Aplicar", que no repite un archivo; se asigna
 * aqui. El servidor impide aplicarlo dos veces.
 */
function pintarAplicables() {
    pintarFilas('tbodyAplicables', listas.aplicables, [
        function (x) { return escapar(x.referencia); },
        function (x) { return pesos(x.valor); },
        function (x, i) {
            if (x.asignada) {
                return '<span class="text-success">Aplicado a la ' + escapar(x.asignada) + '</span>';
            }
            var texto = escapar(x.etiqueta || 'ICA') + (x.contribuyente ? ': ' + escapar(x.contribuyente) : '');
            if (archivoAplicado) {
                return botonAsignar('aplicables', i, 0, x);
            }
            return '<span class="text-success">' + texto + '</span>';
        }
    ], 'Ninguna declaración quedará marcada como pagada con este archivo.');
}

// Los que no se aplican solos: se asignan a mano, con las candidatas a la vista.
function pintarRevisar() {
    pintarFilas('tbodyRevisar', listas.revisar, [
        function (x) { return escapar(x.referencia); },
        function (x) { return pesos(x.valor); },
        function (x) { return escapar(x.motivo); },
        function (x, i) {
            if (x.asignada) {
                return '<span class="text-success">Aplicado a la ' + escapar(x.asignada) + '</span>';
            }
            if (!archivoAplicado) {
                return '<span class="text-muted">Aplique primero el archivo; después podrá asignarlo aquí.</span>';
            }
            return (x.candidatas || []).map(function (c, ci) {
                return botonAsignar('revisar', i, ci, c);
            }).join('<br>');
        }
    ], 'Ninguna.');
}

// Asignar a mano un pago pendiente a la declaración que se elija.
$('#tbodyRevisar, #tbodyAplicables').on('click', '.js-asignar', function () {
    var $b    = $(this);
    var lista = String($b.data('lista'));
    var fila  = (listas[lista] || [])[Number($b.data('i'))];
    if (!fila) { return; }
    var cand  = (lista === 'aplicables') ? fila : (fila.candidatas || [])[Number($b.data('c'))];
    if (!cand) { return; }

    swal({
        title: '¿Aplicar este pago?',
        text: 'La referencia ' + fila.referencia + ' por ' + pesos(fila.valor) + ' quedará como pago de la '
            + cand.etiqueta + ' de ' + (cand.contribuyente || cand.documento || 'este contribuyente')
            + '. Hágalo solo si el comprobante del banco lo confirma.',
        type: 'warning', showCancelButton: true,
        confirmButtonText: 'Sí, aplicar', cancelButtonText: 'Cancelar'
    }).then(function (res) {
        if (!res.value) { return; }
        $('.js-asignar').prop('disabled', true);
        $.ajax({
            url: RUTA_RECAUDO, type: 'POST', dataType: 'json',
            data: { funcion: 4, archivo: archivoEnDisco, referencia: fila.referencia, valor: fila.valor,
                    modulo: cand.modulo, id: cand.id },
            success: function (r) {
                if (r.ok == 1) {
                    fila.asignada = cand.etiqueta;
                    cargarHistorial();
                }
                pintarAplicables();
                pintarRevisar();
                swal({ type: r.ok == 1 ? 'success' : 'warning', title: r.ok == 1 ? 'Listo' : 'No se aplicó', text: r.mensaje || '' });
            },
            error: function () {
                pintarAplicables();
                pintarRevisar();
                swal({ type: 'error', title: 'Error', text: 'No se pudo aplicar el pago. Intente de nuevo.' });
            }
        });
    });
});

function cargarHistorial() {
    $.ajax({
        url: RUTA_RECAUDO, type: 'POST', dataType: 'json', data: { funcion: 3 },
        success: function (r) {
            var filas = '';
            (r.datos || []).forEach(function (x) {
                filas += '<tr>' +
                    '<td>' + escapar(x.arc_Nombre) + '</td>' +
                    '<td>' + escapar(x.ban_Nombre || '—') + '</td>' +
                    '<td>' + escapar(x.arc_FechaPago || '—') + '</td>' +
                    '<td>' + escapar(x.arc_TotalRegistros) + '</td>' +
                    '<td class="text-success">' + escapar(x.arc_TotalAplicados) + '</td>' +
                    '<td>' + escapar(x.arc_TotalYaPagados) + '</td>' +
                    '<td>' + escapar(x.arc_TotalFallidos) + '</td>' +
                    '<td>' + escapar(x.arc_FechaCarga) + '</td>' +
                '</tr>';
            });
            $('#tbodyHistorialRecaudo').html(filas ||
                '<tr><td colspan="8" class="text-center text-muted py-3">Todavía no se ha cargado ningún archivo.</td></tr>');
        },
        error: function () {
            $('#tbodyHistorialRecaudo').html(
                '<tr><td colspan="8" class="text-center text-danger py-3">No se pudo cargar el historial.</td></tr>');
        }
    });
}

$('#btnPrevisualizar').on('click', function () {

    var input = document.getElementById('archivoRecaudo');
    if (!input.files.length) {
        swal({ type: 'warning', title: 'Falta el archivo', text: 'Seleccione el archivo que entregó el banco.' });
        return;
    }

    var datos = new FormData();
    datos.append('funcion', 1);
    datos.append('archivo', input.files[0]);

    $('#loading').show();
    $.ajax({
        url: RUTA_RECAUDO, type: 'POST', dataType: 'json',
        data: datos, processData: false, contentType: false,
        success: function (r) {
            $('#loading').hide();
            if (r.ok != 1) {
                $('#cajaResumen').hide();
                $('#btnAplicar').prop('disabled', true);
                swal({ type: 'error', title: 'No se pudo leer el archivo', text: r.mensaje || '' });
                return;
            }
            archivoRevisado = r.datos.archivo.ruta;
            nombreOriginal  = r.datos.archivo.nombre;
            archivoEnDisco  = r.datos.archivo.ruta;
            // Un archivo ya aplicado (se vuelve a cargar para asignar lo que
            // quedó pendiente) permite asignar a mano de una vez.
            archivoAplicado = !!r.datos.yaSubido;
            pintarResumen(r.datos);
            // "Aplicar" también registra un archivo cuyos pagos son todos para
            // revisar: sin eso, esos pagos no se podían asignar nunca.
            var pendientes = (r.datos.aplicables || []).length + (r.datos.revisar || []).length;
            $('#btnAplicar').prop('disabled', !!r.datos.yaSubido || pendientes === 0);
        },
        error: function () {
            $('#loading').hide();
            swal({ type: 'error', title: 'Error', text: 'No se pudo enviar el archivo. Intente de nuevo.' });
        }
    });
});

$('#btnAplicar').on('click', function () {

    if (!archivoRevisado) { return; }

    swal({
        title: '¿Aplicar los pagos?',
        text: listas.aplicables.length
            ? 'Las declaraciones de "Se van a aplicar" quedarán marcadas como pagadas. Los de "Revisar a mano" se asignan después, uno por uno.'
            : 'Ningún pago se aplica solo: el archivo queda registrado y los de "Revisar a mano" se asignan después, uno por uno.',
        type: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, aplicar',
        cancelButtonText: 'Cancelar'
    }).then(function (res) {

        if (!res.value) { return; }

        $('#loading').show();
        $.ajax({
            url: RUTA_RECAUDO, type: 'POST', dataType: 'json',
            data: { funcion: 2, archivo: archivoRevisado, nombre: nombreOriginal },
            success: function (r) {
                $('#loading').hide();
                if (r.ok == 1) {
                    $('#btnAplicar').prop('disabled', true);
                    archivoRevisado = null;
                    archivoAplicado = true;
                    // Lo que devolvio el servidor, analizado dentro del candado:
                    // los que quedaron aplicados se marcan; si alguno no se pudo
                    // (lo pago otra via en ese instante), queda con su boton.
                    listas.aplicables = (r.datos.aplicables || []).map(function (x) {
                        if (x.aplicado) { x.asignada = x.etiqueta; }
                        return x;
                    });
                    listas.revisar = r.datos.revisar || [];
                    pintarAplicables();
                    pintarRevisar();
                    cargarHistorial();
                } else if (r.datos && r.datos.yaAplicado) {
                    // Ya estaba aplicado: lo pendiente se asigna desde las tablas.
                    $('#btnAplicar').prop('disabled', true);
                    archivoAplicado = true;
                    pintarAplicables();
                    pintarRevisar();
                }
                swal({
                    type: (r.ok == 1) ? 'success' : 'warning',
                    title: (r.ok == 1) ? 'Listo' : 'No se aplicó',
                    text: r.mensaje || ''
                });
            },
            error: function () {
                $('#loading').hide();
                swal({ type: 'error', title: 'Error', text: 'No se pudieron aplicar los pagos.' });
            }
        });
    });
});

$('#btnRefrescarHistorial').on('click', cargarHistorial);

$(document).ready(function () {
    // Recaudo de los tres módulos (ICA, retención y autorretención), no solo ICA.
    $('#headerPageTitle').text('Recaudo');
    cargarHistorial();
});
