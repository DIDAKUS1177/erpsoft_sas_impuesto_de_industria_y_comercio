/*
 * Recaudo por codigo de barras (archivo Asobancaria).
 *
 * El flujo es de DOS pasos a proposito: primero se revisa el archivo y se
 * muestra que va a pasar, y solo entonces se habilita "Aplicar". Marcar
 * declaraciones como pagadas es irreversible en la practica.
 */

var RUTA_RECAUDO = '../business/controller/class.recaudo.php';

/** El nombre en disco que devolvio la previsualizacion, para poder aplicarlo. */
var archivoRevisado = null;
var nombreOriginal  = null;

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
    (lista || []).forEach(function (x) {
        filas += '<tr>' + columnas.map(function (c) { return '<td>' + c(x) + '</td>'; }).join('') + '</tr>';
    });
    if (!filas) {
        filas = '<tr><td colspan="' + columnas.length + '" class="text-center text-muted py-3">' + vacio + '</td></tr>';
    }
    $('#' + idCuerpo).html(filas);
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
        avisos += '<div class="alert alert-danger py-2 mb-2">' +
                  'Este archivo <b>ya se cargó</b> el ' + escapar(d.yaSubido.fecha) +
                  ' como «' + escapar(d.yaSubido.nombre) + '». Aplicarlo otra vez no hará nada.</div>';
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
            '<div class="col-md-3"><small class="text-muted d-block">Se van a aplicar</small><b class="text-success">' + (d.aplicables || []).length + '</b></div>' +
            '<div class="col-md-3"><small class="text-muted d-block">Ya estaban pagadas</small><b>' + (d.yaPagadas || []).length + '</b></div>' +
            '<div class="col-md-3"><small class="text-muted d-block">Sin declaración</small><b class="text-danger">' + (d.sinDeclaracion || []).length + '</b></div>' +
        '</div>' +
        '<div class="row mt-3">' +
            '<div class="col-md-3"><small class="text-muted d-block">Sin presentar (no se aplican)</small><b class="text-danger">' + (d.sinPresentar || []).length + '</b></div>' +
        '</div>'
    );

    // Aqui ya solo pueden entrar declaraciones presentadas: el servidor manda
    // las demas a sinPresentar y no las aplica. La columna se conserva porque
    // el usuario pidio ver el estado, pero deja de ser una advertencia.
    pintarFilas('tbodyAplicables', d.aplicables, [
        function (x) { return escapar(x.referencia); },
        function (x) { return pesos(x.valor); },
        function ()  { return '<span class="text-success">Presentada</span>'; }
    ], 'Ninguna declaración quedará marcada como pagada con este archivo.');

    pintarFilas('tbodyYaPagadas', d.yaPagadas, [
        function (x) { return escapar(x.referencia); },
        function (x) { return pesos(x.valor); }
    ], 'Ninguna.');

    pintarFilas('tbodySinDeclaracion', d.sinDeclaracion, [
        function (x) { return escapar(x.referencia); },
        function (x) { return pesos(x.valor); }
    ], 'Ninguna: todas las referencias del archivo existen en el sistema.');

    // Pagos que el banco reporta contra una declaracion que existe pero NO
    // esta presentada. No se aplican -ver la nota larga en class.recaudo.php-
    // y quedan listados para conciliacion manual.
    pintarFilas('tbodySinPresentar', d.sinPresentar, [
        function (x) { return escapar(x.referencia); },
        function (x) { return pesos(x.valor); }
    ], 'Ninguna: todos los pagos del archivo corresponden a declaraciones presentadas.');

    $('#cajaResumen').show();
}

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
            pintarResumen(r.datos);
            // Sin nada que aplicar, el boton sigue bloqueado.
            $('#btnAplicar').prop('disabled', (r.datos.aplicables || []).length === 0);
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
        text: 'Las declaraciones del listado quedarán marcadas como pagadas.',
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
                swal({
                    type: (r.ok == 1) ? 'success' : 'warning',
                    title: (r.ok == 1) ? 'Listo' : 'No se aplicó',
                    text: r.mensaje || ''
                });
                if (r.ok == 1) {
                    $('#btnAplicar').prop('disabled', true);
                    archivoRevisado = null;
                    cargarHistorial();
                }
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
    $('#headerPageTitle').text('Recaudo ICA');
    cargarHistorial();
});
