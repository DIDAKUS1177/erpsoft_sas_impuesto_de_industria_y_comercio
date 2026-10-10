/**
 * Registro manual de la Alcaldía sobre una declaración (migración 044), en las
 * pantallas de ICA, retención y autorretención:
 *
 *   RegistroManual.historica(modulo, id, numero)
 *       Un borrador ya llenado queda PRESENTADO y PAGADO: declaración que se
 *       presentó en papel y se pagó fuera de la plataforma. Pide el número y
 *       la fecha del papel, el pago y dos PDF (original y soporte).
 *   RegistroManual.pago(modulo, id, numero, valor)
 *       Una declaración presentada queda pagada con un pago manual
 *       (transferencia, consignación...) y su soporte en PDF.
 *
 * El servidor (business/controller/class.registroManual.php) valida todo de
 * nuevo y exige los permisos alcaldia.declaraciones.historicas y
 * alcaldia.pagos.manual; aquí solo se arma el formulario.
 *
 * El modal se crea en el <body>: dentro del encabezado o de una tarjeta quedaba
 * debajo del fondo oscuro de Bootstrap (lección del modal de la contraseña,
 * dist/menu.php, 2026-10-05).
 */
var RegistroManual = (function () {

    var URL = '../business/controller/class.registroManual.php';
    var MEDIOS = [
        ['TRANSFERENCIA', 'Transferencia'],
        ['CONSIGNACION', 'Consignación'],
        ['VENTANILLA', 'Pago en ventanilla'],
        ['OTRO', 'Otro medio']
    ];
    var NOMBRE = { ica: 'Industria y Comercio', reteica: 'Retención de ICA', autorreteica: 'Autorretención de ICA' };

    function esc(t) {
        return String(t === null || t === undefined ? '' : t)
            .replace(/&/g, '&amp;').replace(/"/g, '&quot;')
            .replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function hoy() {
        var d = new Date();
        var dos = function (n) { return (n < 10 ? '0' : '') + n; };
        return d.getFullYear() + '-' + dos(d.getMonth() + 1) + '-' + dos(d.getDate());
    }

    function pesos(v) {
        var n = Math.round(Number(v) || 0);
        return n > 0 ? n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.') : '';
    }

    function campo(etiqueta, control, ayuda, ancho) {
        return '<div class="form-group col-md-' + (ancho || 6) + '">'
             + '<label style="font-weight:600;">' + etiqueta + '</label>' + control
             + (ayuda ? '<small class="form-text text-muted">' + ayuda + '</small>' : '')
             + '</div>';
    }

    function seccionPago(valor) {
        var opciones = MEDIOS.map(function (m) { return '<option value="' + m[0] + '">' + m[1] + '</option>'; }).join('');
        return '<h6 style="margin:6px 0 10px;font-weight:700;">El pago</h6>'
             + '<div class="form-row">'
             + campo('Fecha de pago', '<input type="date" name="fechaPago" class="form-control" required max="' + hoy() + '">', '', 4)
             + campo('Valor pagado (pesos)', '<input type="text" name="valor" class="form-control" required inputmode="numeric" '
                   + 'placeholder="Ej. 1.250.000" value="' + esc(pesos(valor)) + '">', 'Lo que entró a la cuenta, con intereses si los hubo.', 4)
             + campo('Medio', '<select name="medio" class="form-control" required>' + opciones + '</select>', '', 4)
             + campo('Banco', '<input type="text" name="banco" class="form-control" maxlength="60" placeholder="Ej. Bancolombia">', '', 6)
             + campo('Referencia o comprobante', '<input type="text" name="referencia" class="form-control" maxlength="60">', '', 6)
             // Alto fijo: la hoja de retención le da a los textarea un alto mínimo enorme.
             + campo('Observación', '<textarea name="observacion" class="form-control" rows="2" maxlength="500" '
                   + 'style="min-height:0;height:64px;"></textarea>', '', 12)
             + '</div>';
    }

    function pdf(nombre, etiqueta, ayuda) {
        return campo(etiqueta, '<input type="file" name="' + nombre + '" class="form-control-file" required accept="application/pdf,.pdf">',
                     ayuda || 'Solo PDF, hasta 10 MB.', 6);
    }

    function abrir(o) {
        $('#modal-RegistroManual').modal('hide').remove();

        var titulo = o.historica ? 'Registrar declaración ya pagada' : 'Registrar pago manual';
        var intro = o.historica
            ? 'La declaración N° <b>' + esc(o.numero) + '</b> (' + esc(NOMBRE[o.modulo] || '') + ') quedará <b>presentada y pagada</b> '
              + 'con los datos que se llenaron en el formulario, sin firma por código: el respaldo son los PDF. '
              + 'Revise antes que el formulario tenga los mismos valores del papel.'
            : 'La declaración N° <b>' + esc(o.numero) + '</b> (' + esc(NOMBRE[o.modulo] || '') + ') quedará <b>pagada</b> '
              + 'con este pago. Úselo para pagos hechos fuera de la plataforma: transferencia, consignación, ventanilla.';

        var papel = !o.historica ? '' :
              '<h6 style="margin:6px 0 10px;font-weight:700;">La declaración en papel</h6>'
            + '<div class="form-row">'
            + campo('Número en el papel', '<input type="text" name="numeroPapel" class="form-control" required maxlength="30">',
                    'El sistema le da un número nuevo de su serie; este queda de referencia.', 6)
            + campo('Fecha de presentación', '<input type="date" name="fechaPresentacion" class="form-control" required max="' + hoy() + '">', '', 6)
            + '</div>';

        var archivos = '<h6 style="margin:6px 0 10px;font-weight:700;">Soportes</h6><div class="form-row">'
            + (o.historica ? pdf('declaracion', 'Declaración original (PDF)') : '')
            + pdf('soporte', 'Soporte de pago (PDF)')
            + '</div>';

        var html =
            '<div class="modal fade" id="modal-RegistroManual" tabindex="-1" role="dialog" aria-labelledby="tituloRegistroManual">'
          + '<div class="modal-dialog modal-lg" role="document"><div class="modal-content">'
          + '<form novalidate>'
          + '<div class="modal-header"><h5 class="modal-title" id="tituloRegistroManual">' + titulo + '</h5>'
          + '<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button></div>'
          + '<div class="modal-body">'
          + '<p style="font-size:13px;color:#4B5563;">' + intro + '</p>'
          + papel + seccionPago(o.valor) + archivos
          + '</div>'
          + '<div class="modal-footer">'
          + '<button type="button" class="btn btn-link" data-dismiss="modal">Cancelar</button>'
          + '<button type="submit" class="btn btn-success">' + titulo + '</button>'
          + '</div></form></div></div></div>';

        $('body').append(html);
        var $m = $('#modal-RegistroManual');
        $m.find('form').on('submit', function (e) {
            e.preventDefault();
            // Lo que falta, con el mensaje del navegador, antes de subir PDF.
            if (this.checkValidity && !this.checkValidity()) { this.reportValidity && this.reportValidity(); return; }
            enviar(o, $m);
        });
        // Mientras se envía no se cierra: la petición seguiría y registraría algo
        // que la persona creyó cancelado.
        $m.on('hide.bs.modal', function (e) { if ($m.data('enviando')) { e.preventDefault(); } });
        $m.on('hidden.bs.modal', function () { $m.remove(); });
        $m.modal('show');
    }

    /*
     * La confirmación de "pago anterior a la presentación" va DENTRO del modal,
     * con una casilla, y no en un swal encima: Bootstrap le quita el foco al
     * swal (Enter no confirmaba) y su Esc cerraba también el modal, con lo
     * llenado y los PDF.
     */
    function pedirConfirmacionFechas($m, mensaje) {
        if ($m.find('#rmConfirmaFechas').length) { return; }
        $m.find('.modal-body').append(
            '<div class="alert alert-warning" style="font-size:13px;">' + esc(mensaje)
          + '<label style="display:flex;gap:8px;align-items:center;margin:8px 0 0;font-weight:600;">'
          + '<input type="checkbox" name="confirmaFechas" value="1" id="rmConfirmaFechas" required> '
          + 'Confirmo que el pago fue anterior a la presentación</label></div>');
        var el = $m.find('#rmConfirmaFechas')[0];
        if (el && el.scrollIntoView) { el.scrollIntoView({ block: 'center' }); }
    }

    function enviar(o, $m) {
        var fd = new FormData($m.find('form')[0]);
        fd.append('funcion', o.historica ? 1 : 2);
        fd.append('modulo', o.modulo);
        fd.append('id', o.id);

        var $btn = $m.find('[type=submit]');
        var texto = $btn.text();
        $btn.prop('disabled', true).text('Registrando…');
        $m.data('enviando', true).find('[data-dismiss=modal]').prop('disabled', true);
        var listo = function () {
            $m.data('enviando', false).find('[data-dismiss=modal]').prop('disabled', false);
            $btn.prop('disabled', false).text(texto);
        };

        // global: false -> el aviso genérico de "error de conexión" de
        // dist/menu.php no tapa el de aquí.
        $.ajax({ url: URL, type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json', global: false })
            .done(function (r) {
                listo();
                if (r && Number(r.ok) === 1) {
                    $m.modal('hide');
                    swal({ type: 'success', title: 'Registrado', text: r.mensaje }).then(function () { location.reload(); });
                    return;
                }
                if (r && r.codigo === 'FECHAS') { pedirConfirmacionFechas($m, r.mensaje); return; }
                if (r && Number(r.sinSesion) === 1) { location.reload(); return; }
                swal({ type: 'error', title: 'No se registró', text: (r && r.mensaje) || 'No se pudo registrar. Intente de nuevo.' });
            })
            .fail(function () {
                listo();
                // Si la conexión se cortó después de guardar, el cambio sí quedó:
                // no se afirma lo contrario.
                swal({ type: 'error', title: 'Sin respuesta del servidor',
                       text: 'Revise su conexión y recargue la lista antes de intentarlo de nuevo: '
                           + 'si el registro alcanzó a guardarse, la declaración ya aparecerá pagada.' });
            });
    }

    /**
     * Deshace un registro manual hecho por error (permiso
     * alcaldia.registro.anular). El servidor decide qué se deshace: la ya
     * pagada vuelve a borrador; el pago manual se quita.
     */
    function anular(modulo, id, numero) {
        swal({
            type: 'warning',
            title: 'Anular el registro manual',
            html: 'Declaración N° <b>' + esc(numero) + '</b>.<br>Si se registró <b>ya pagada</b>, vuelve a borrador; '
                + 'si fue un <b>pago manual</b>, queda presentada sin pagar. Los PDF dejan de mostrarse y queda constancia.',
            input: 'textarea',
            inputPlaceholder: 'Motivo de la anulación (obligatorio)',
            showCancelButton: true,
            confirmButtonText: 'Anular',
            cancelButtonText: 'Cancelar',
            inputValidator: function (v) {
                return new Promise(function (resolve) {
                    resolve(String(v || '').trim().length >= 10 ? undefined : 'Escriba el motivo (al menos 10 caracteres).');
                });
            }
        }).then(function (r) {
            if (!r || !r.value) { return; }
            $.ajax({ url: URL, type: 'POST', dataType: 'json', global: false,
                     data: { funcion: 3, modulo: modulo, id: id, motivo: String(r.value).trim() } })
                .done(function (x) {
                    if (x && Number(x.ok) === 1) {
                        swal({ type: 'success', title: 'Anulado', text: x.mensaje }).then(function () { location.reload(); });
                        return;
                    }
                    if (x && Number(x.sinSesion) === 1) { location.reload(); return; }
                    swal({ type: 'error', title: 'No se anuló', text: (x && x.mensaje) || 'No se pudo anular. Intente de nuevo.' });
                })
                .fail(function () {
                    swal({ type: 'error', title: 'Sin respuesta del servidor',
                           text: 'Recargue la lista antes de intentarlo de nuevo: la anulación pudo haberse guardado.' });
                });
        });
    }

    /** "Presentada en papel N° X" / "Pago manual: medio", para la fila. */
    function nota(rm) {
        if (!rm) { return ''; }
        var html = '';
        if (rm.historica) {
            html += '<div style="font-size:11px;color:#0F766E;">Presentada en papel N° ' + esc(rm.historica.numeroPapel)
                  + ' (' + esc(rm.historica.fechaPresentacion) + ')</div>';
        }
        if (rm.pagoManual) {
            html += '<div style="font-size:11px;color:#0F766E;">Pago manual: ' + esc(rm.pagoManual.medio)
                  + (rm.pagoManual.referencia ? ' · ref. ' + esc(rm.pagoManual.referencia) : '') + '</div>';
        }
        return html;
    }

    /** Enlaces a los PDF (soporte.php comprueba sesión, dueño y permiso). */
    function soportes(rm) {
        if (!rm || !rm.soportes || !rm.soportes.length) { return []; }
        return rm.soportes.map(function (s) {
            var original = s.tipo === 'declaracion_original';
            return { id: Number(s.id), original: original, texto: original ? 'Original' : 'Soporte',
                     titulo: (original ? 'Declaración original: ' : 'Soporte de pago: ') + s.nombre,
                     url: '../extensiones/soporte.php?id=' + Number(s.id) };
        });
    }

    return {
        historica: function (modulo, id, numero) { abrir({ historica: true, modulo: modulo, id: id, numero: numero }); },
        pago: function (modulo, id, numero, valor) { abrir({ historica: false, modulo: modulo, id: id, numero: numero, valor: valor }); },
        anular: anular,
        nota: nota,
        soportes: soportes
    };
})();
