/**
 * Botones "Descargar PDF" y "Descargar Excel" del informe de declaraciones
 * pagadas (extensiones/informePagos.php), en el encabezado de Consultar de los
 * tres módulos (pedido de la Alcaldía de Paipa, 2026-10-13).
 *
 *   InformePagos.montar({ modulo, cabecera, anio, periodo })
 *       modulo    ica | reteica | autorreteica
 *       cabecera  selector del bloque del título de la tarjeta
 *       anio      selector del filtro de año (opcional)
 *       periodo   selector del filtro de período (opcional)
 *
 * El informe sale con los filtros que tenga la pantalla. Quien tiene
 * alcaldia.informes.pagos elige además "Solo este contribuyente" o "Todos los
 * contribuyentes"; los demás, solo el contribuyente de la pantalla. El
 * servidor vuelve a comprobarlo todo.
 */
var InformePagos = (function () {

    var URL = '../extensiones/informePagos.php';

    function puede(clave) {
        return typeof erpPuede !== 'function' || erpPuede(clave);
    }

    function estilos() {
        if (document.getElementById('estilosInformePagos')) { return; }
        var css =
            '.cabecera-informe{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px 16px;}'
          + '.cabecera-informe h4{margin:0;}'
          + '.informe-pagos{display:flex;align-items:center;flex-wrap:wrap;gap:8px;}'
          + '.informe-pagos .rotulo{font-size:12px;font-weight:600;color:#6B7280;text-transform:uppercase;letter-spacing:.04em;}'
          + '.informe-pagos select{width:auto;min-width:190px;height:34px;font-size:13px;}'
          + '.informe-pagos .btn-informe{display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 14px;font-size:13px;font-weight:600;'
          +   'border-radius:8px;border:1px solid var(--erp-primario,#1fa49d);color:var(--erp-primario,#1fa49d);background:#fff;transition:background .15s,color .15s;}'
          + '.informe-pagos .btn-informe:hover,.informe-pagos .btn-informe:focus-visible{background:var(--erp-primario,#1fa49d);color:#fff;}'
          + '.informe-pagos .btn-informe[disabled]{opacity:.6;cursor:wait;}'
          + '.informe-pagos .btn-informe i{font-size:14px;}';
        var s = document.createElement('style');
        s.id = 'estilosInformePagos';
        s.appendChild(document.createTextNode(css));
        document.head.appendChild(s);
    }

    function url(cfg, formato) {
        var q = ['modulo=' + encodeURIComponent(cfg.modulo), 'formato=' + formato];
        var anio = cfg.anio ? $(cfg.anio).val() : '';
        var periodo = cfg.periodo ? $(cfg.periodo).val() : '';
        if (anio) { q.push('anio=' + encodeURIComponent(anio)); }
        if (periodo) { q.push('periodo=' + encodeURIComponent(periodo)); }
        if ($('#informeAlcance').val() === 'todos') {
            q.push('alcance=todos');
        } else {
            var id = '';
            try { id = localStorage.getItem('id_Contribuyente') || ''; } catch (e) { /* sin almacenamiento */ }
            q.push('contribuyente=' + encodeURIComponent(id));
        }
        return URL + '?' + q.join('&');
    }

    function aviso(texto) {
        if (typeof swal === 'function') { swal({ type: 'error', title: 'No se pudo descargar', text: texto }); }
        else { alert(texto); }
    }

    /* El Excel se pide por XHR para poder mostrar el motivo si el servidor lo
       niega; el PDF se abre en otra pestaña (abrirla después de una espera la
       bloquearía el navegador). */
    function excel(cfg, $btn) {
        var html = $btn.html();
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Generando…');
        var xhr = new XMLHttpRequest();
        xhr.open('GET', url(cfg, 'xlsx'));
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
            var nombre = 'Informe_de_pagos.xlsx';
            var disp = xhr.getResponseHeader('Content-Disposition') || '';
            var m = /filename="([^"]+)"/.exec(disp);
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
        xhr.send();
    }
    var URL_ = window.URL || window.webkitURL;

    function montar(cfg) {
        var $cab = $(cfg.cabecera);
        if (!$cab.length || !puede(cfg.modulo + '.ver')) { return; }
        estilos();

        var alcance = puede('alcaldia.informes.pagos')
            ? '<select id="informeAlcance" class="form-control form-control-sm" aria-label="De quién es el informe">'
              + '<option value="">Solo este contribuyente</option>'
              + '<option value="todos">Todos los contribuyentes</option></select>'
            : '';

        $cab.addClass('cabecera-informe').append(
            '<div class="informe-pagos">'
          + '<span class="rotulo">Informe de pagadas</span>' + alcance
          + '<button type="button" class="btn-informe" data-formato="pdf" title="Las declaraciones pagadas, con los filtros de esta pantalla, en PDF">'
          + '<i class="fa fa-file-pdf-o"></i> Descargar PDF</button>'
          + '<button type="button" class="btn-informe" data-formato="xlsx" title="Las declaraciones pagadas, con los filtros de esta pantalla, en Excel">'
          + '<i class="fa fa-file-excel-o"></i> Descargar Excel</button>'
          + '</div>');

        // Sin contribuyente activo, "Solo este contribuyente" no tiene de quién.
        var activo = '';
        try { activo = localStorage.getItem('id_Contribuyente') || ''; } catch (e) { /* sin almacenamiento */ }
        if (!activo && $('#informeAlcance').length) { $('#informeAlcance').val('todos'); }

        $cab.on('click', '.btn-informe', function () {
            if ($(this).data('formato') === 'pdf') { window.open(url(cfg, 'pdf'), '_blank'); }
            else { excel(cfg, $(this)); }
        });
    }

    return { montar: montar };
})();
