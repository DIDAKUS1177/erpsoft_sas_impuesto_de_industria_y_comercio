/**
 * Registro manual de la Alcaldía sobre una declaración (migración 044), en las
 * pantallas de ICA, retención y autorretención:
 *
 *   RegistroManual.historica(modulo, id, numero)
 *       Un borrador ya llenado queda PRESENTADO y PAGADO: declaración que se
 *       presentó en papel y se pagó fuera de la plataforma. Pide el número y
 *       la fecha del papel, el pago y dos PDF (original y soporte).
 *   RegistroManual.pago(modulo, id, numero, valor)
 *       Una declaración presentada queda pagada con un pago manual: solo las
 *       fechas de presentación y de pago, el banco y el soporte en PDF (Paipa,
 *       2026-10-14 y 15); el valor es el total de la declaración.
 *   RegistroManual.anular(modulo, id, numero)
 *       "Eliminar pago" (se llamó "Anular" hasta el 2026-10-13): deshace un
 *       registro manual hecho por error, con motivo.
 *
 * El banco se elige de la lista de ind_bancos (migración 045): primero los que
 * tienen cuenta recaudadora del municipio. Si el banco tiene cuentas contables
 * este año (ind_bancos_cuenta, migración 051), se elige además una.
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

    /* El pago manual: fechas de presentación y de pago, banco y soporte. El valor
       se muestra, no se escribe. La fecha de presentación (Juan, 2026-10-15)
       llega llena con la de la plataforma y se cambia si se presentó en otra
       fecha. En una ICA vencida en la fecha de pago aparece además "Intereses de
       mora" (Diego, 2026-10-15), que se suman al total. Ver fechas() abajo. */
    function seccionPagoSimple(valor) {
        return '<h6 style="margin:6px 0 10px;font-weight:700;">El pago</h6>'
             + '<div class="form-row">'
             + campo('Fecha de presentación', '<input type="date" name="fechaPresentacion" class="form-control" required max="' + hoy() + '">',
                     '<span class="js-presentacion-ayuda">Cámbiela si se presentó en otra fecha.</span>', 6)
             + campo('Fecha de pago', '<input type="date" name="fechaPago" class="form-control" required max="' + hoy() + '">', '', 6)
             + '</div>'
             + '<div class="form-row">'
             + campo('Banco', '<select name="idBanco" class="form-control" required><option value="">Cargando bancos…</option></select>',
                     'Por el que entró la plata.', 6)
             + campoCuenta()
             + '</div>'
             + '<div class="form-row js-intereses" style="display:none;">'
             + campo('Intereses de mora pagados (pesos)', '<input type="text" name="intereses" class="form-control" inputmode="numeric" '
                   + 'maxlength="13" placeholder="0" autocomplete="off">', '<span class="js-intereses-ayuda"></span>', 6)
             + '</div>'
             + '<p style="font-size:13px;color:#4B5563;margin:0 0 8px;">Valor que se registra: <b class="js-valor-registra">$ '
             + esc(pesos(valor) || '0') + '</b> <span class="js-valor-detalle">(el total a pagar de la declaración)</span>.</p>';
    }

    function fechaTexto(f) {
        var p = String(f).split('-');
        return p[2] + '/' + p[1] + '/' + p[0];
    }

    /* Llena la fecha de presentación con la de la plataforma (si nadie escribió
       otra) y, en la ICA, muestra la casilla de intereses si la fecha de pago es
       posterior a la fecha límite, manteniendo al día el valor que se registra. */
    function fechas(o, $m) {
        if (o.historica) { return; }
        var ica = o.modulo === 'ica';
        var limite = null;
        var $fecha = $m.find('[name=fechaPago]'), $int = $m.find('[name=intereses]'), $fila = $m.find('.js-intereses');
        var actualizar = function () {
            var vencida = !!limite && !!$fecha.val() && $fecha.val() > limite;
            $fila.toggle(vencida);
            if (!vencida) { $int.val(''); }
            var extra = vencida ? (parseInt(String($int.val()).replace(/[^0-9]/g, ''), 10) || 0) : 0;
            $m.find('.js-valor-registra').text('$ ' + (pesos((Number(o.valor) || 0) + extra) || '0'));
            $m.find('.js-valor-detalle').text(extra > 0 ? '(el total de la declaración más los intereses)' : '(el total a pagar de la declaración)');
        };
        if (ica) {
            $fecha.on('change input', actualizar);
            $int.on('input', function () {
                var limpio = String($(this).val()).replace(/[^0-9.]/g, '');
                if (limpio !== $(this).val()) { $(this).val(limpio); }
                actualizar();
            });
        }
        // Lo que alguien empezó a escribir no se pisa con la respuesta (una fecha
        // a medio escribir se lee vacía).
        var $pre = $m.find('[name=fechaPresentacion]'), tocada = false;
        $pre.one('input change', function () { tocada = true; });
        var sinFecha = function () {
            $m.find('.js-presentacion-ayuda').text('No se pudo traer la fecha de la plataforma: escríbala.');
        };
        $.ajax({ url: URL, type: 'POST', dataType: 'json', global: false, data: { funcion: 5, modulo: o.modulo, id: o.id } })
            .fail(sinFecha)
            .done(function (r) {
                if (!r || Number(r.ok) !== 1) { sinFecha(); return; }
                var d = r.datos || {};
                if (d.presentada) {
                    if (!tocada && !$pre.val()) { $pre.val(d.presentada); }
                    $m.find('.js-presentacion-ayuda').text('En la plataforma: ' + fechaTexto(d.presentada) + '. Cámbiela si se presentó en otra fecha.');
                }
                if (!ica) { return; }
                limite = d.fechaLimite || null;
                if (limite) {
                    $m.find('.js-intereses-ayuda').text('Venció el ' + fechaTexto(limite) + '. Escriba 0 si no se cobraron.');
                }
                actualizar();
            });
    }

    function seccionPago(valor) {
        var opciones = MEDIOS.map(function (m) { return '<option value="' + m[0] + '">' + m[1] + '</option>'; }).join('');
        return '<h6 style="margin:6px 0 10px;font-weight:700;">El pago</h6>'
             + '<div class="form-row">'
             + campo('Fecha de pago', '<input type="date" name="fechaPago" class="form-control" required max="' + hoy() + '">', '', 4)
             + campo('Valor pagado (pesos)', '<input type="text" name="valor" class="form-control" required inputmode="numeric" '
                   + 'placeholder="Ej. 1.250.000" value="' + esc(pesos(valor)) + '">', 'Lo que entró a la cuenta, con intereses si los hubo.', 4)
             + campo('Medio', '<select name="medio" class="form-control" required>' + opciones + '</select>', '', 4)
             + campo('Banco', '<select name="idBanco" class="form-control" required><option value="">Cargando bancos…</option></select>',
                     'Por el que entró la plata.', 6)
             + campoCuenta()
             + campo('Referencia o comprobante', '<input type="text" name="referencia" class="form-control" maxlength="60">', '', 6)
             // Alto fijo: la hoja de retención le da a los textarea un alto mínimo enorme.
             + campo('Observación', '<textarea name="observacion" class="form-control" rows="2" maxlength="500" '
                   + 'style="min-height:0;height:64px;"></textarea>', '', 12)
             + '</div>';
    }

    /* La cuenta contable (migración 051): aparece al elegir un banco que tiene
       cuentas este año, y entonces es obligatoria. Ver cuentas() abajo. */
    function campoCuenta() {
        return '<div class="form-group col-md-6 js-cuenta-contable" style="display:none;">'
             + '<label style="font-weight:600;">Cuenta contable</label>'
             + '<select name="idCuentaContable" class="form-control"></select>'
             + '<small class="form-text text-muted js-cuenta-ayuda"></small></div>';
    }

    /* Llena la cuenta contable con las del banco elegido (o la esconde si no tiene). */
    function cuentas($m) {
        var id = Number($m.find('select[name=idBanco]').val()) || 0;
        var banco = (bancos || []).filter(function (b) { return Number(b.id) === id; })[0];
        var lista = (banco && banco.cuentas) || [];
        var $grupo = $m.find('.js-cuenta-contable'), $sel = $grupo.find('select');
        if (!lista.length) {
            $grupo.hide();
            $sel.prop('required', false).html('');
            return;
        }
        $sel.html('<option value="">Elija la cuenta</option>' + lista.map(function (c) {
            return '<option value="' + Number(c.id) + '">' + esc(c.cuenta) + (c.descripcion ? ' · ' + esc(c.descripcion) : '') + '</option>';
        }).join('')).prop('required', true);
        if (lista.length === 1) { $sel.val(String(Number(lista[0].id))); }
        $grupo.find('.js-cuenta-ayuda').text('Con la que se hace el ingreso (cuentas de ' + Number(lista[0].anio) + ').');
        $grupo.show();
    }

    /*
     * Los bancos, una vez por página. Los que tienen cuenta recaudadora (donde
     * la entidad recibe) van primero y con su cuenta; los demás debajo.
     */
    var bancos = null;
    function cargarBancos($m) {
        var $sel = $m.find('select[name=idBanco]');
        $sel.off('change.cuenta').on('change.cuenta', function () { cuentas($m); });
        var pintar = function (lista) {
            var elegido = $sel.val();
            var propias = lista.filter(function (b) { return b.cuenta; });
            var otras   = lista.filter(function (b) { return !b.cuenta; });
            var op = function (b) {
                return '<option value="' + Number(b.id) + '">' + esc(b.nombre) + (b.cuenta ? ' (cuenta ' + esc(b.cuenta) + ')' : '') + '</option>';
            };
            var html = '<option value="">Elija el banco</option>';
            if (propias.length && otras.length) {
                html += '<optgroup label="Cuentas del municipio">' + propias.map(op).join('') + '</optgroup>'
                      + '<optgroup label="Otros bancos">' + otras.map(op).join('') + '</optgroup>';
            } else {
                html += lista.map(op).join('');
            }
            $sel.html(html);
            // Al recargar la lista (ver enviar()) se conserva el banco elegido.
            if (elegido) { $sel.val(elegido); }
            cuentas($m);
        };
        if (bancos) { pintar(bancos); return; }
        $.ajax({ url: URL, type: 'POST', dataType: 'json', global: false, data: { funcion: 4 } })
            .done(function (r) {
                if (r && Number(r.ok) === 1 && r.datos && r.datos.length) { bancos = r.datos; pintar(bancos); return; }
                $sel.html('<option value="">' + esc((r && r.mensaje) || 'No hay bancos activos. Revise Municipio y bancos.') + '</option>');
            })
            .fail(function () {
                $sel.html('<option value="">No se pudieron cargar los bancos. Cierre y vuelva a abrir.</option>');
            });
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
              + 'con este pago hecho fuera de la plataforma.';

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
          + papel + (o.historica ? seccionPago(o.valor) : seccionPagoSimple(o.valor)) + archivos
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
        // Si corrige una fecha, la confirmación de "pago anterior a la
        // presentación" ya no aplica: se quita y se vuelve a revisar al enviar.
        $m.on('change input', '[name=fechaPago], [name=fechaPresentacion]', function () {
            $m.find('#rmConfirmaFechas').closest('.alert').remove();
        });
        cargarBancos($m);
        fechas(o, $m);
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
                // Las cuentas contables cambiaron con la página abierta: se vuelven
                // a pedir para que la casilla aparezca (o se actualice).
                if (r && /cuenta contable/i.test(r.mensaje || '')) { bancos = null; cargarBancos($m); }
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
     * "Eliminar pago": deshace un registro manual hecho por error (permiso
     * alcaldia.registro.anular). El servidor decide qué se deshace: la ya
     * pagada vuelve a borrador; el pago manual se quita.
     */
    function anular(modulo, id, numero, historica) {
        swal({
            type: 'warning',
            title: historica ? 'Eliminar el registro ya pagada' : 'Eliminar el pago manual',
            html: 'Declaración N° <b>' + esc(numero) + '</b>.<br>'
                + (historica
                    ? 'Se registró <b>ya pagada</b>: al eliminar el pago <b>vuelve a borrador</b> (pierde también la presentación). '
                    : 'Queda <b>presentada sin pagar</b>. ')
                + 'Los PDF dejan de mostrarse y queda constancia.',
            input: 'textarea',
            inputPlaceholder: '¿Por qué se elimina? (obligatorio)',
            showCancelButton: true,
            confirmButtonText: 'Eliminar pago',
            confirmButtonColor: '#DC2626',
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
                        swal({ type: 'success', title: 'Pago eliminado', text: x.mensaje }).then(function () { location.reload(); });
                        return;
                    }
                    if (x && Number(x.sinSesion) === 1) { location.reload(); return; }
                    swal({ type: 'error', title: 'No se eliminó', text: (x && x.mensaje) || 'No se pudo eliminar el pago. Intente de nuevo.' });
                })
                .fail(function () {
                    swal({ type: 'error', title: 'Sin respuesta del servidor',
                           text: 'Recargue la lista antes de intentarlo de nuevo: el pago pudo haberse eliminado.' });
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
