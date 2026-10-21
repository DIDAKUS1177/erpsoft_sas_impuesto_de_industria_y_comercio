/**
 * Pantalla "Informes" del administrador (dist/informes.php, 2026-10-14):
 * declaraciones presentadas, pagadas o las dos, en PDF o Excel
 * (extensiones/informePagos.php). Reemplaza los botones que hubo un día en
 * Consultar: el cliente quiso los informes en un módulo propio.
 *
 * El servidor vuelve a comprobar todo (sesión, alcaldia.informes.pagos y el
 * "Ver" del módulo); aquí solo se arman los filtros y la descarga.
 */
var InformePagos = (function () {

    var URL = '../extensiones/informePagos.php';
    var URL_ = window.URL || window.webkitURL;
    var MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto',
                 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    var BIMESTRES = ['Enero - Febrero', 'Marzo - Abril', 'Mayo - Junio', 'Julio - Agosto',
                     'Septiembre - Octubre', 'Noviembre - Diciembre'];

    function aviso(texto) {
        if (typeof swal === 'function') { swal({ type: 'error', title: 'No se pudo descargar', text: texto }); }
        else { alert(texto); }
    }

    function pintarAnios() {
        var hoy = new Date().getFullYear();
        var html = '<option value="">Todos los años</option>';
        for (var a = hoy; a >= hoy - 10; a--) { html += '<option value="' + a + '">' + a + '</option>'; }
        $('#infAnio').html(html).val('');
    }

    // El ICA es anual: sin período. Retención por mes, autorretención por bimestre.
    function pintarPeriodos() {
        var modulo = $('#infModulo').val();
        var lista = modulo === 'reteica' ? MESES : (modulo === 'autorreteica' ? BIMESTRES : []);
        var html = '<option value="">Todos los períodos</option>';
        lista.forEach(function (n, i) { html += '<option value="' + (i + 1) + '">' + n + '</option>'; });
        $('#infPeriodo').html(html).val('');
        $('#campoPeriodo').toggle(lista.length > 0);
    }

    /* El NIT sin el dígito de verificación: lo que va después del guion se
       descarta (así se guarda el documento, y con el DV pegado no cabe). */
    function documento() {
        return String($('#infDocumento').val() || '').split('-')[0].replace(/[^0-9]/g, '');
    }

    /* Los filtros. Viajan por POST: el NIT o la cédula de una persona no queda
       en la dirección ni en el historial. */
    function parametros(formato) {
        var p = { modulo: $('#infModulo').val(), formato: formato, estado: $('#infEstado').val() };
        var anio = $('#infAnio').val(), periodo = $('#infPeriodo').val(), doc = documento();
        var desde = $('#infDesde').val(), hasta = $('#infHasta').val();
        if (anio) { p.anio = anio; }
        if (periodo && $('#campoPeriodo').is(':visible')) { p.periodo = periodo; }
        if (doc) { p.documento = doc; }
        if (desde) { p.desde = desde; }
        if (hasta) { p.hasta = hasta; }
        return p;
    }

    /* El rango de fechas (2026-10-20): de pago en las pagadas, de presentación
       en las demás. El rótulo lo dice. */
    function rotularRango() {
        var que = $('#infEstado').val() === 'pagadas' ? 'Pagadas' : 'Presentadas';
        $('#lblDesde').text(que + ' desde');
        $('#lblHasta').text(que + ' hasta');
    }

    /* "Desde" después de "hasta" se avisa aquí: el PDF se abre en otra pestaña
       y ahí solo se vería el texto del error. */
    function rangoValido() {
        // Una fecha a medio escribir (o con un año de más de 4 cifras) se lee
        // vacía y el rango se ignoraría sin aviso.
        var mala = $('#infDesde, #infHasta').filter(function () {
            return (this.validity && this.validity.badInput) || (this.value && !/^\d{4}-\d{2}-\d{2}$/.test(this.value));
        });
        if (mala.length) {
            aviso('Revise las fechas del rango: escríbalas completas (día/mes/año) o déjelas vacías.');
            return false;
        }
        var desde = $('#infDesde').val(), hasta = $('#infHasta').val();
        if (desde && hasta && desde > hasta) {
            aviso('La fecha "desde" es posterior a la fecha "hasta". Corríjalas y vuelva a descargar.');
            return false;
        }
        return true;
    }

    /* El PDF en otra pestaña, con un formulario POST (window.open solo hace GET). */
    function pdf() {
        var $f = $('<form method="post" target="_blank" style="display:none"></form>').attr('action', URL);
        var p = parametros('pdf');
        Object.keys(p).forEach(function (k) { $('<input type="hidden">').attr('name', k).val(p[k]).appendTo($f); });
        $f.appendTo('body');
        $f[0].submit();
        $f.remove();
    }

    /* El Excel se pide por XHR para poder mostrar el motivo si el servidor lo
       niega; el PDF se abre en otra pestaña (abrirla después de una espera la
       bloquearía el navegador). */
    function excel($btn) {
        var html = $btn.html();
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Generando…');
        var xhr = new XMLHttpRequest();
        xhr.open('POST', URL);
        xhr.responseType = 'blob';
        xhr.onload = function () {
            $btn.prop('disabled', false).html(html);
            // Un aviso de PHP o una página de error llegan con 200 y otro tipo:
            // descargarlos dejaría un .xlsx dañado sin decir nada.
            var tipo = xhr.getResponseHeader('Content-Type') || '';
            if (xhr.status !== 200 || tipo.indexOf('spreadsheetml') === -1) {
                var lector = new FileReader();
                lector.onload = function () {
                    // IIS puede cambiar el cuerpo de un error por su propia página HTML.
                    var texto = /text\/plain/.test(tipo) ? String(lector.result || '') : '';
                    if (!texto || /</.test(texto)) {
                        texto = xhr.status === 401 ? 'Su sesión terminó. Inicie sesión de nuevo.'
                              : xhr.status === 403 ? 'Su rol no tiene permiso para descargar este informe.'
                              : 'No se pudo generar el informe. Intente de nuevo.';
                    }
                    aviso(texto.slice(0, 300));
                    if (xhr.status === 401) { setTimeout(function () { window.location = '../index.php'; }, 2500); }
                };
                lector.readAsText(xhr.response);
                return;
            }
            var nombre = 'Informe_de_declaraciones.xlsx';
            var m = /filename="([^"]+)"/.exec(xhr.getResponseHeader('Content-Disposition') || '');
            if (m) { nombre = m[1]; }
            var enlace = document.createElement('a');
            enlace.href = URL_.createObjectURL(xhr.response);
            enlace.download = nombre;
            document.body.appendChild(enlace);
            enlace.click();
            setTimeout(function () { URL_.revokeObjectURL(enlace.href); enlace.remove(); }, 1000);
        };
        xhr.onerror = function () {
            $btn.prop('disabled', false).html(html);
            aviso('Sin respuesta del servidor. Revise su conexión e intente de nuevo.');
        };
        var fd = new FormData();
        var p = parametros('xlsx');
        Object.keys(p).forEach(function (k) { fd.append(k, p[k]); });
        xhr.send(fd);
    }

    function iniciar() {
        $('#headerPageTitle').text('Informes');

        // Solo los impuestos que el rol puede ver (el servidor exige su "Ver").
        $('#infModulo option').each(function () {
            if (typeof erpPuede === 'function' && !erpPuede(this.value + '.ver')) { $(this).remove(); }
        });
        if (!$('#infModulo option').length) {
            $('.informe-filtros, .informe-acciones').hide();
            $('.informe-ayuda').text('Su rol no puede ver las declaraciones de ningún impuesto. Pídale el permiso al administrador.');
            return;
        }

        pintarAnios();
        pintarPeriodos();
        rotularRango();
        $('#infModulo').on('change', pintarPeriodos);
        $('#infEstado').on('change', rotularRango);
        // Dígitos y, a lo sumo, un guion: lo que va después (el DV) no se usa.
        $('#infDocumento').on('input', function () {
            var limpio = String($(this).val()).replace(/[^0-9-]/g, '');
            if (limpio !== $(this).val()) { $(this).val(limpio); }
        });
        $('.informe-acciones').on('click', '.btn-informe', function () {
            if (!rangoValido()) { return; }
            if ($(this).data('formato') === 'pdf') { pdf(); }
            else { excel($(this)); }
        });
    }

    $(iniciar);
    return { parametros: parametros };
})();
