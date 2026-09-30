/*
 * ============================================================================
 * PANTALLAS DE RETEICA Y AUTORRETEICA
 * ============================================================================
 *
 * Un solo motor para los cuatro archivos de pantalla (consultar y presentar,
 * por cada uno de los dos modulos). Cada pantalla solo entrega su CONFIG.
 *
 * Se hace asi por lo mismo que el backend comparte class.retenciones.php: en
 * este proyecto las funciones de cifras estuvieron copiadas en tres JS y eso
 * costo dos bugs de produccion -se arreglaba una copia y las otras seguian
 * rotas-. Cuatro copias de una pantalla habrian sido peor.
 *
 * DOS REGLAS DEL PROYECTO QUE AQUI NO SE NEGOCIAN
 *
 * 1. Las cifras SIEMPRE pasan por NumerosCOP. Lo que viene de la base usa
 *    deBaseDeDatos() y lo que viene de un input usa aCifra(). Confundirlas
 *    multiplica el valor por 100 en cada pasada: fue el bug de los "00000".
 *
 * 2. Todo .ajax lleva error(). Sin el, una peticion caida deja la pantalla
 *    MUDA -ni mensaje, ni spinner-. Asi parecio muerto el boton "Liquidar"
 *    del ICA durante meses.
 * ============================================================================
 */

var Retenciones = (function () {

    'use strict';

    /* --------------------------------------------------------------------
     * Puente de SweetAlert2 (v7 -> API v8+)
     * --------------------------------------------------------------------
     * Este proyecto trae SweetAlert2 v7.16.0, cuyo global es `swal` en
     * minuscula: no tiene `.fire`, usa `type`/`onOpen` (no `icon`/`didOpen`) y
     * su promesa resuelve en `.value` (no `.isConfirmed`). Este archivo se
     * escribio contra la API v8+ (`Swal.fire`, `icon`, `didOpen`,
     * `.isConfirmed`), asi que sin este puente TODA llamada a Swal lanzaba
     * "Swal is not defined" y el flujo moria antes de abrir la declaracion:
     * por eso "Crear declaracion" no hacia nada.
     *
     * Se traduce aqui, en un solo sitio, en vez de reescribir las ~15
     * llamadas o cambiar la version -que romperia las demas pantallas del
     * sistema, que si usan swal(...) v7-. Si algun dia se sube a la v11, este
     * puente cede el paso solo (window.Swal ya existiria).
     *
     * sweetalert2 se carga antes que este archivo (ver el orden de <script> en
     * las cuatro vistas), asi que window.swal ya existe cuando esto corre.
     */
    var Swal = window.Swal || (function (base) {
        if (typeof base !== 'function') {
            // Sin libreria de alertas: no romper el flujo por un aviso.
            var noop = function () { return { then: function (f) { try { f({ isConfirmed: true, value: true }); } catch (e) {} return { catch: function () {} }; } }; };
            return { fire: noop, showLoading: function () {}, close: function () {} };
        }
        function fire(a, b, c) {
            var o;
            if (a && typeof a === 'object') {
                o = {};
                for (var k in a) { if (a.hasOwnProperty(k)) { o[k] = a[k]; } }
                if (o.icon && !o.type)      { o.type   = o.icon;    delete o.icon; }
                if (o.didOpen && !o.onOpen) { o.onOpen = o.didOpen;  delete o.didOpen; }
            } else {
                o = { title: a, text: b, type: c };
            }
            function norm(res) {
                var ok = !!(res && res.value);
                return { isConfirmed: ok, isDismissed: !ok, value: res && res.value };
            }
            return base(o).then(norm, function () { return { isConfirmed: false, isDismissed: true }; });
        }
        return {
            fire: fire,
            showLoading: function () { return base.showLoading(); },
            close: function () { return base.close(); }
        };
    })(window.swal);

    /* --------------------------------------------------------------------
     * Conversacion con el backend
     * ------------------------------------------------------------------ */

    /**
     * @param {function} [alFallo] Si se pasa, recibe la respuesta con ok=0 y
     *        ESTE se encarga del mensaje. Sin el, se muestra el aviso genérico.
     *        Hace falta para "Presentar": cuando el backend responde que falta
     *        una firma no queremos un aviso de error, queremos abrir el modal
     *        de firma. Mostrar las dos cosas confundiría más que ayudar.
     */
    function pedir(cfg, funcion, datos, alOk, alFallo) {

        var envio = $.extend({ funcion: funcion }, datos || {});

        // Contribuyente activo (administrador gestionando a otro). Lo lee el
        // servidor solo para roles de Alcaldía; al contribuyente normal se lo
        // ignora y usa el suyo, así que enviarlo siempre es seguro. El resto de
        // pantallas (RIT, ICA, establecimientos) ya trabajan con esta misma
        // llave de localStorage; retención/autorretención no la mandaban.
        var _idc = localStorage.getItem('id_Contribuyente');
        if (_idc && _idc !== 'null' && ('' + _idc).trim() !== '') {
            envio.idContribuyente = _idc;
        }

        $('#loading').show();

        return $.ajax({
            url: cfg.endpoint,
            type: 'POST',
            dataType: 'json',
            data: envio,
            success: function (r) {
                $('#loading').hide();
                if (!r || r.ok != 1) {
                    if (alFallo) { alFallo(r || {}); return; }
                    Swal.fire('Atención', (r && r.mensaje) || 'No se pudo completar la operación.', 'warning');
                    return;
                }
                alOk(r);
            },
            error: function (xhr) {
                // Sin esto la pantalla se queda muda. Ver la cabecera.
                $('#loading').hide();
                Swal.fire('Error de conexión',
                          'No se pudo contactar el servidor (' + xhr.status + '). Intente de nuevo.',
                          'error');
            }
        });
    }

    /* --------------------------------------------------------------------
     * Utilidades de presentacion
     * ------------------------------------------------------------------ */

    var MESES = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    var BIMESTRES = ['', 'Enero - Febrero', 'Marzo - Abril', 'Mayo - Junio',
                     'Julio - Agosto', 'Septiembre - Octubre', 'Noviembre - Diciembre'];

    function nombrePeriodo(cfg, n) {
        var tabla = (cfg.periodoMax === 6) ? BIMESTRES : MESES;
        return tabla[n] || ('Período ' + n);
    }

    /**
     * El chip de estado del ICA, no un badge de Bootstrap.
     *
     * `.chip-estado.est-*` está definido en dist/menu.php, que estas pantallas
     * ya incluyen, y sus variantes coinciden con los estados que devuelve el
     * backend ("Falta contador" usa la de firmada, como el ICA). Usar el mismo
     * componente no es cosmética: el
     * cliente compara las pantallas entre sí, y dos formas distintas de pintar
     * "Presentada" en el mismo sistema se leen como descuido.
     */
    function insignia(fila) {
        var estados = {
            borrador:      { texto: 'Borrador',       clase: 'borrador' },
            pendienteCont: { texto: 'Falta contador', clase: 'firmada' },
            firmada:       { texto: 'Firmada',        clase: 'firmada' },
            presentada:    { texto: 'Presentada',     clase: 'presentada' },
            pagada:        { texto: 'Pagada',         clase: 'pagada' }
        };
        var e = estados[fila.estadoClave] || estados.borrador;
        return '<span class="chip-estado est-' + e.clase + '">' + e.texto + '</span>';
    }

    /** El bloque de "no hay nada", con el mismo formato que el ICA. */
    function vacio(columnas, titulo, texto, icono) {
        return '<tr><td colspan="' + columnas + '">'
             + '<div class="estado-vacio">'
             + '<div class="ev-icono"><i class="fa ' + (icono || 'fa-inbox') + '"></i></div>'
             + '<div class="ev-titulo">' + titulo + '</div>'
             + '<div class="ev-texto">' + texto + '</div>'
             + '</div></td></tr>';
    }

    function pesos(valor) {
        return '$ ' + NumerosCOP.formatear(NumerosCOP.deBaseDeDatos(valor));
    }

    function escapar(t) {
        return $('<div>').text(t === null || t === undefined ? '' : t).html();
    }

    /*
     * Boton de accion en TARJETA (icono + texto), IGUAL que el ICA.
     * Pedido del cliente 2026-09-14: los mismos botones y funciones de siempre,
     * solo cambia la forma -icono arriba, su nombre debajo, a color cuando la
     * accion esta disponible-. La CSS (.acc-cards/.acc-card) vive en
     * dist/menu.php, compartida con el ICA. Estas pantallas usan delegacion de
     * eventos (clase js-* + data-id), asi que el boton acepta clase e id;
     * cuando es un enlace directo (PDF) usa href.
     *
     *   o.tipo    info|warning|primary|success|danger|secondary
     *   o.icono   clase de Font Awesome
     *   o.texto   etiqueta visible
     *   o.title   tooltip
     *   o.clase   clase js-* para la delegacion   (con o.id)
     *   o.id      data-id de la fila
     *   o.numero  data-numero: el N° de la declaracion (con él se firma)
     *   o.href    enlace directo   + o.target opcional (por defecto _blank)
     */
    function accBtn(o) {
        // Lo que el rol no puede hacer no se ofrece (interruptores del panel de
        // Roles; el servidor igual lo rebotaria). Arreglo = todas las claves.
        if (o.permiso && typeof erpPuede === 'function'
            && !(Array.isArray(o.permiso) ? o.permiso : [o.permiso]).every(function (x) { return erpPuede(x); })) {
            return '';
        }
        var cls = 'acc-card acc-' + o.tipo + (o.clase ? ' ' + o.clase : '');
        var cuerpo = '<i class="fa ' + o.icono + '"></i>'
                   + '<span class="acc-lbl">' + o.texto + '</span>';
        if (o.href) {
            return '<a class="' + cls + '" target="' + (o.target || '_blank') + '" '
                 + 'title="' + o.title + '" href="' + o.href + '">' + cuerpo + '</a>';
        }
        return '<button class="' + cls + '" data-id="' + o.id + '" '
             + (o.numero ? 'data-numero="' + escapar(o.numero) + '" ' : '')
             + 'title="' + o.title + '">' + cuerpo + '</button>';
    }

    /** Envuelve las tarjetas de acciones de una fila. */
    function accCards(html) {
        return '<div class="acc-cards">' + html + '</div>';
    }

    /**
     * Botones de acción de UNA fila, HOMOGÉNEOS con el ICA. El cliente pidió
     * (2026-09-14) que los tres módulos se vean y se manejen igual: antes el
     * listado de retención solo traía Ver/Descargar y "faltaba Firmar". Ahora,
     * según el estado, ofrece lo mismo que icaWebConsultar/Presentar:
     *
     *   borrador       -> Editar / Firmar / Descargar / Borrar
     *   falta contador -> Editar / Descargar / Firmar contador / Presentar
     *   firmada        -> Editar / Descargar / Presentar
     *   presentada     -> Descargar / Pagar PSE / Recibo de pago / Corregir
     *   pagada         -> Descargar
     *
     * El estado sale de las firmas (class.retenciones.php, _filaParaPantalla).
     *
     * Los botones con acción llevan clase js-* y cada pantalla pone el manejador:
     * en "Presentar", Editar abre el formulario y Firmar, Firmar contador y
     * Presentar se hacen desde el listado; en "Consultar" (solo presentadas)
     * queda Corregir. Descargar, Pagar PSE y Recibo son enlaces directos.
     */
    function accionesRetencion(f, cfg) {
        var estado = f.estadoClave || 'borrador';
        // Prefijo de los interruptores del panel de Roles: reteica / autorreteica.
        var mod = String(cfg.modulo || '').toLowerCase();
        var pdf = cfg.pdf
            ? accBtn({ permiso: mod + '.ver', tipo: 'primary', icono: 'fa-download', texto: 'Descargar', title: 'Descargar', href: cfg.pdf + '?id=' + f.id })
            : '';
        var b;

        if (estado === 'presentada' || estado === 'pagada') {
            b = pdf;
            if (estado === 'presentada') {
                // Pagar PSE: solo en presentadas, si la entidad tiene convenio
                // (pago_en_linea, igual que en el ICA) y si hay valor a pagar
                // (en $0 pagar.php solo puede decir "no aplica"). Va al RESUMEN
                // (pagar.php): monto + logo AvalPay + politica antes de redirigir.
                if (Number(f.pago_en_linea) === 1 && Number(f.total) > 0) {
                    b += accBtn({ permiso: mod + '.pagar', tipo: 'danger', icono: 'fa-money', texto: 'Pagar PSE', title: 'Pagar por PSE',
                                  href: '../extensiones/pse/pagar.php?modulo=' + encodeURIComponent(cfg.modulo || '') + '&id=' + f.id,
                                  target: '_blank' });
                }
                // Recibo de pago para el banco: el mismo en los tres módulos
                // (extensiones/reciboPago.php), solo si hay valor.
                if (Number(f.total) > 0) {
                    b += accBtn({ permiso: mod + '.pagar', tipo: 'info', icono: 'fa-barcode', texto: 'Recibo de pago',
                                  title: 'Descargar el recibo de pago para el banco',
                                  href: '../extensiones/reciboPago.php?modulo=' + encodeURIComponent(cfg.modulo || '') + '&id=' + f.id,
                                  target: '_blank' });
                }
                b += accBtn({ permiso: mod + '.corregir', tipo: 'warning', icono: 'fa-pencil', texto: 'Corregir',
                              title: 'Corregir', clase: 'js-corregir', id: f.id });
            }
        } else if (estado === 'pendienteCont' || estado === 'firmada') {
            b = accBtn({ permiso: mod + '.editar', tipo: 'warning', icono: 'fa-pencil', texto: 'Editar',
                         title: 'Editar (si cambia algo, al guardar se quitan las firmas)', clase: 'js-editar', id: f.id })
              + pdf;
            if (estado === 'pendienteCont') {
                // Firmar como contador es un acto propio, con su botón, como en
                // el ICA (cliente, 2026-09-01): el contador firma y se va.
                // "Presentar" pide la firma que falte y presenta de una vez.
                b += accBtn({ permiso: mod + '.firmar', tipo: 'info', icono: 'fa-pencil-square-o', texto: 'Firmar contador',
                              title: 'Firmar como contador o revisor fiscal (solo firma, no presenta)',
                              clase: 'js-firmar-contador', id: f.id, numero: f.numero });
            }
            b += accBtn({ permiso: estado === 'pendienteCont' ? [mod + '.presentar', mod + '.firmar'] : mod + '.presentar',
                      tipo: 'success', icono: 'fa-paper-plane', texto: 'Presentar',
                          title: 'Presentar', clase: 'js-presentar', id: f.id, numero: f.numero });
        } else { // borrador
            b = accBtn({ permiso: mod + '.editar', tipo: 'warning',   icono: 'fa-pencil',          texto: 'Editar', title: 'Editar',          clase: 'js-editar', id: f.id })
              + accBtn({ permiso: mod + '.firmar', tipo: 'secondary', icono: 'fa-pencil-square-o', texto: 'Firmar', title: 'Firmar',          clase: 'js-firmar', id: f.id, numero: f.numero })
              + pdf
              + accBtn({ permiso: mod + '.editar', tipo: 'danger',    icono: 'fa-trash',           texto: 'Borrar', title: 'Borrar borrador', clase: 'js-borrar', id: f.id });
        }
        return accCards(b);
    }

    /** Los años que se ofrecen: el actual y los dos anteriores.
     *  El anterior hace falta de verdad: en enero se declara diciembre. */
    function aniosOfrecidos() {
        var hoy = new Date().getFullYear(), lista = [];
        for (var a = hoy; a >= hoy - 2; a--) { lista.push(a); }
        return lista;
    }

    function llenarSelectPeriodos($sel, cfg) {
        $sel.empty().append('<option value="">Seleccione…</option>');
        for (var i = 1; i <= cfg.periodoMax; i++) {
            $sel.append('<option value="' + i + '">' + nombrePeriodo(cfg, i) + '</option>');
        }
    }

    function llenarSelectAnios($sel) {
        $sel.empty();
        aniosOfrecidos().forEach(function (a) {
            $sel.append('<option value="' + a + '">' + a + '</option>');
        });
    }


    /* ====================================================================
     * FIRMA CON CÓDIGO AL CORREO (OTP)
     *
     * El mismo flujo que el ICA: se pide un código de 6 dígitos al correo del
     * firmante y se registra la firma con ese código en UNA sola llamada -la
     * función 7 lo valida y lo consume ella misma-. Llamar a firmar sin código
     * fue durante meses la forma de registrar una firma sin haber recibido
     * ningún correo; eso se cerró en agosto y aquí no se reabre.
     *
     * EL HTML DEL MODAL SE GENERA AQUÍ, no se copia en las vistas.
     *
     * En el ICA ese HTML está repetido en tres pantallas y CLAUDE.md advierte
     * que un cambio hay que replicarlo en las tres. Con los dos módulos nuevos
     * serían cinco copias. Se crea una sola vez desde JavaScript y se cuelga
     * del body: misma apariencia, un solo sitio donde mantenerlo.
     * ================================================================== */

    var FirmaRetencion = (function () {

        var API          = '../microservicios/firmas/api.php';
        var VIGENCIA_SEG = 600;   // el backend expira el código a los 10 min
        var REENVIO_SEG  = 60;    // espera mínima antes de dejar reenviar

        var _timerVigencia = null;
        var _timerReenvio  = null;
        var _cfg = null, _numero = null, _rol = 'declarante', _alFirmar = null;

        // Un pedido de código a la vez: con doble clic salían dos correos y el
        // primer código quedaba inválido (la función 1 anula los anteriores).
        var _pidiendo = false;

        function _mmss(seg) {
            var m = Math.floor(seg / 60), s = seg % 60;
            return m + ':' + (s < 10 ? '0' + s : s);
        }

        function _pararTimers() {
            if (_timerVigencia) { clearInterval(_timerVigencia); _timerVigencia = null; }
            if (_timerReenvio)  { clearInterval(_timerReenvio);  _timerReenvio  = null; }
        }

        function _error(msg) { $('#retOtpError').text(msg).show(); }
        function _limpiarError() { $('#retOtpError').hide().text(''); }

        function _asegurarModal() {

            if ($('#retModalFirma').length) { return; }

            $('body').append(
              '<div class="modal fade" id="retModalFirma" role="dialog" aria-hidden="true" data-backdrop="static">'
            + '  <div class="modal-dialog modal-sm modal-dialog-centered" role="document">'
            + '    <div class="modal-content">'
            + '      <div class="modal-header" style="background:var(--erp-primario); color:#fff;">'
            + '        <h5 class="modal-title text-white">Firma digital</h5>'
            + '        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>'
            + '      </div>'
            + '      <div class="modal-body text-center">'
            + '        <p style="font-size:14px; margin-bottom:6px;">Enviamos un código de 6 dígitos a:</p>'
            + '        <p id="retOtpDestino" style="font-size:13px; font-weight:700; color:var(--erp-primario); margin-bottom:4px; word-break:break-all;"></p>'
            + '        <p id="retOtpVigencia" style="font-size:12px; color:#6B7280; margin-bottom:15px;"></p>'
            + '        <div class="form-group">'
            + '          <input type="text" id="retOtpCodigo" class="form-control form-control-lg text-center"'
            + '                 placeholder="000000" maxlength="6" inputmode="numeric" autocomplete="one-time-code"'
            + '                 style="font-size:24px; letter-spacing:5px; font-weight:bold; width:80%; margin:0 auto;">'
            + '        </div>'
            + '        <div id="retOtpError" style="display:none; font-size:12.5px; color:#DC2626; margin-top:8px;"></div>'
            + '        <button type="button" id="retBtnReenviar" class="btn btn-link btn-sm" style="font-size:12.5px; margin-top:6px;">Reenviar código</button>'
            + '      </div>'
            + '      <div class="modal-footer justify-content-center">'
            + '        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>'
            + '        <button type="button" class="btn btn-success btn-sm" id="retBtnValidar">Validar y firmar</button>'
            + '      </div>'
            + '    </div>'
            + '  </div>'
            + '</div>'
            );

            $('#retBtnValidar').on('click', _validarYFirmar);
            $('#retBtnReenviar').on('click', function () { _solicitarCodigo(true); });

            // Enter dentro del campo firma, que es lo que espera cualquiera.
            $('#retOtpCodigo').on('keypress', function (e) {
                if (e.which === 13) { _validarYFirmar(); }
            });

            // Al cerrar se paran los contadores: si no, siguen corriendo en
            // segundo plano y escriben sobre un modal que ya no se ve.
            $('#retModalFirma').on('hidden.bs.modal', _pararTimers);
        }

        function _arrancarVigencia() {
            var restante = VIGENCIA_SEG;
            $('#retOtpVigencia').css('color', '#6B7280').text('El código vence en ' + _mmss(restante));

            if (_timerVigencia) { clearInterval(_timerVigencia); }
            _timerVigencia = setInterval(function () {
                restante--;
                if (restante <= 0) {
                    clearInterval(_timerVigencia);
                    _timerVigencia = null;
                    $('#retOtpVigencia').css('color', '#DC2626').text('El código venció. Solicite uno nuevo.');
                    $('#retBtnValidar').prop('disabled', true);
                    return;
                }
                $('#retOtpVigencia')
                    .css('color', restante <= 60 ? '#DC2626' : '#6B7280')
                    .text('El código vence en ' + _mmss(restante));
            }, 1000);
        }

        function _arrancarCooldown() {
            var restante = REENVIO_SEG;
            var $btn = $('#retBtnReenviar');
            $btn.prop('disabled', true).text('Reenviar código (' + restante + 's)');

            if (_timerReenvio) { clearInterval(_timerReenvio); }
            _timerReenvio = setInterval(function () {
                restante--;
                if (restante <= 0) {
                    clearInterval(_timerReenvio);
                    _timerReenvio = null;
                    $btn.prop('disabled', false).text('Reenviar código');
                    return;
                }
                $btn.text('Reenviar código (' + restante + 's)');
            }, 1000);
        }

        function _solicitarCodigo(esReenvio) {

            if (_pidiendo) { return; }
            _pidiendo = true;

            if (!esReenvio) {
                Swal.fire({
                    title: 'Generando código',
                    text: _rol === 'contador'
                        ? 'Falta la firma del contador o revisor fiscal: el código va a su correo. Por favor espere…'
                        : 'Por favor espere…',
                    allowOutsideClick: false,
                    didOpen: function () { Swal.showLoading(); }
                });
            } else {
                $('#retBtnReenviar').prop('disabled', true);
            }

            $.ajax({
                url: API,
                type: 'POST',
                dataType: 'json',
                data: {
                    funcion: 1,
                    // El servidor toma el firmante de la SESIÓN. Un id en el
                    // POST no prueba quién es quién.
                    id_establecimiento: 0,
                    rol: _rol,
                    modulo: _cfg.modulo,
                    numero_declaracion: _numero
                },
                complete: function () { _pidiendo = false; },
                success: function (r) {

                    if (!r || r.ok != 1) {
                        var msg = (r && r.mensaje) || 'No se pudo generar el código.';
                        if (esReenvio) { _error(msg); $('#retBtnReenviar').prop('disabled', false); }
                        else { Swal.fire('No se pudo firmar', msg, 'warning'); }
                        return;
                    }

                    // El backend responde "Código enviado a <correo>"; se
                    // extrae para que la persona sepa dónde buscarlo.
                    var correo = (r.mensaje || '').replace(/^.*?enviado a\s*/i, '').trim();
                    $('#retOtpDestino').text(correo || (_rol === 'contador'
                        ? 'el correo del contador o revisor fiscal'
                        : 'su correo electrónico'));

                    $('#retOtpCodigo').val('');
                    _limpiarError();
                    $('#retBtnValidar').prop('disabled', false);
                    _arrancarVigencia();
                    _arrancarCooldown();

                    if (!esReenvio) {
                        Swal.close();
                        $('#retModalFirma').modal('show');
                        setTimeout(function () { $('#retOtpCodigo').focus(); }, 400);
                    }
                },
                error: function (xhr) {
                    Swal.fire('Error de conexión',
                              'No se pudo contactar el servidor (' + xhr.status + ').', 'error');
                    $('#retBtnReenviar').prop('disabled', false);
                }
            });
        }

        function _validarYFirmar() {

            var codigo = ($('#retOtpCodigo').val() || '').trim();

            if (!/^\d{6}$/.test(codigo)) {
                _error('Escriba los 6 dígitos del código.');
                $('#retOtpCodigo').focus();
                return;
            }

            _limpiarError();
            $('#retBtnValidar').prop('disabled', true);

            $.ajax({
                url: API,
                type: 'POST',
                dataType: 'json',
                /* UNA sola llamada: la función 7 valida el código, lo consume y
                   registra la firma. Separar validar de firmar fue justo el
                   agujero que permitía firmar sin haber recibido correo. */
                data: {
                    funcion: 7,
                    codigo: codigo,
                    rol: _rol,
                    modulo: _cfg.modulo,
                    numero_declaracion: _numero
                },
                success: function (r) {

                    if (!r || r.ok != 1) {
                        $('#retBtnValidar').prop('disabled', false);
                        _error((r && r.mensaje) || 'Código inválido o expirado.');
                        $('#retOtpCodigo').val('').focus();
                        return;
                    }

                    _pararTimers();
                    $('#retModalFirma').modal('hide');
                    if (_alFirmar) { _alFirmar(); }
                },
                error: function (xhr) {
                    $('#retBtnValidar').prop('disabled', false);
                    _error('No se pudo contactar el servidor (' + xhr.status + ').');
                }
            });
        }

        /**
         * @param {object}   cfg      El CONFIG de la pantalla (aporta el módulo).
         * @param {string}   numero   Número de la declaración que se firma.
         * @param {string}   rol      'declarante' o 'contador'.
         * @param {function} alFirmar Qué hacer cuando la firma quedó registrada.
         */
        function abrir(cfg, numero, rol, alFirmar) {
            if (_pidiendo) { return; }   // doble clic: ya se está pidiendo uno

            _cfg      = cfg;
            _numero   = numero;
            _rol      = (rol === 'contador') ? 'contador' : 'declarante';
            _alFirmar = alFirmar || null;

            _asegurarModal();
            _pararTimers();
            _limpiarError();
            $('#retModalFirma .modal-title').text(_rol === 'contador'
                ? 'Firma del contador o revisor fiscal'
                : 'Firma del declarante');
            $('#retOtpDestino').text(_rol === 'contador'
                ? 'el correo del contador o revisor fiscal'
                : 'su correo electrónico');

            _solicitarCodigo(false);
        }

        return { abrir: abrir };

    })();


    /* ====================================================================
     * PANTALLA: CONSULTAR DECLARACIONES
     * ================================================================== */

    function consultar(cfg) {

        llenarSelectAnios($('#filtroAnio'));
        $('#filtroAnio').prepend('<option value="">Todos los años</option>').val('');
        llenarSelectPeriodos($('#filtroPeriodo'), cfg);
        $('#filtroPeriodo option:first').text('Todos los períodos');

        $('#tituloModulo').text(cfg.titulo);

        function refrescar() {
            pedir(cfg, 2, {
                anio: $('#filtroAnio').val() || '',
                periodo: $('#filtroPeriodo').val() || ''
            }, function (r) { pintar(r.datos); });
        }

        /*
         * "Tipo de declaración" (cliente, 2026-09-29), como en el ICA: Inicial o
         * Corrección de cuál, y en la ya corregida, por cuál.
         */
        function tipoDeclaracion(f, todas) {
            var html = f.corrige
                ? '<span class="chip-estado est-firmada">Corrección</span>'
                    + '<div style="font-size:11px;color:#6B7280;">de la N° ' + escapar(f.corrige) + '</div>'
                : '<span class="chip-estado est-borrador">Inicial</span>';
            var por = (todas || []).filter(function (x) {
                return x.corrige && String(x.corrige) === String(f.numero);
            });
            if (por.length) {
                var ultima = por[por.length - 1];
                var yaPresentada = ultima.estadoClave === 'presentada' || ultima.estadoClave === 'pagada';
                html += '<div style="font-size:11px;color:#B45309;">'
                      + (yaPresentada ? 'Corregida por la N° ' : 'En corrección: N° ')
                      + escapar(ultima.numero) + '</div>';
            }
            return html;
        }

        function pintar(filas) {

            // Toda la lista, para saber cuál corrige a cuál ("Tipo de declaración").
            var todas = filas || [];

            // Esta pantalla es de CONSULTA: igual que el Consultar del ICA, solo
            // se listan las declaraciones ya PRESENTADAS (o pagadas). Los
            // borradores y las firmadas se trabajan en "Presentar Declaración".
            filas = (filas || []).filter(function (f) {
                return f.estadoClave === 'presentada' || f.estadoClave === 'pagada';
            });

            var $cuerpo = $('#tablaDeclaraciones tbody').empty();

            $('#conteoDeclaraciones').text(
                filas.length + (filas.length === 1 ? ' declaración' : ' declaraciones')
            );

            if (!filas.length) {
                $cuerpo.append(vacio(7,
                    'Aún no hay declaraciones presentadas',
                    'Cuando presente una declaración aparecerá aquí. Los borradores están en "Presentar Declaración".',
                    'fa-file-o'));
                return;
            }

            filas.forEach(function (f) {

                $cuerpo.append(
                    '<tr>'
                  + '<td>' + f.anio + '</td>'
                  + '<td>' + nombrePeriodo(cfg, f.periodo) + '</td>'
                  + '<td>' + escapar(f.numero) + '</td>'
                  + '<td>' + insignia(f) + '</td>'
                  + '<td>' + tipoDeclaracion(f, todas) + '</td>'
                  + '<td style="text-align:right;">' + pesos(f.total) + '</td>'
                  + '<td class="text-center">' + accionesRetencion(f, cfg) + '</td>'
                  + '</tr>'
                );
            });
        }

        // Consultar solo lista presentadas, así que hoy solo se usa Corregir.
        // Estos quedan por si vuelve a listar borradores: abren "Presentar
        // Declaración" con la acción en la URL (ver presentar(), que la usa una
        // sola vez y pide confirmación para presentar).
        $('#tablaDeclaraciones').on('click', '.js-editar', function () {
            window.location = cfg.pantallaPresentar + '?id=' + $(this).data('id');
        });
        $('#tablaDeclaraciones').on('click', '.js-firmar', function () {
            window.location = cfg.pantallaPresentar + '?id=' + $(this).data('id') + '&accion=firmar';
        });
        $('#tablaDeclaraciones').on('click', '.js-presentar', function () {
            window.location = cfg.pantallaPresentar + '?id=' + $(this).data('id') + '&accion=presentar';
        });
        $('#tablaDeclaraciones').on('click', '.js-borrar', function () {
            var id = $(this).data('id');
            Swal.fire({
                title: '¿Eliminar este borrador?',
                text: 'Se perderá lo que haya diligenciado.',
                icon: 'warning', showCancelButton: true,
                confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar'
            }).then(function (res) {
                if (!res.isConfirmed) { return; }
                pedir(cfg, 5, { id: id }, function () { refrescar(); });
            });
        });

        $('#tablaDeclaraciones').on('click', '.js-corregir', function () {
            var id = $(this).data('id');
            Swal.fire({
                title: '¿Corregir esta declaración?',
                text: 'Se creará una nueva declaración que corrige a la presentada. '
                    + 'La original no se modifica.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, corregir',
                cancelButtonText: 'Cancelar'
            }).then(function (res) {
                if (!res.isConfirmed) { return; }
                pedir(cfg, 7, { id: id }, function (r) {
                    window.location = cfg.pantallaPresentar + '?id=' + r.datos.id;
                });
            });
        });

        // Sin botón "Filtrar": en el ICA los filtros se aplican al cambiar, y
        // tener que pulsar un botón en una pantalla y no en la otra es
        // exactamente la clase de diferencia que el cliente nota.
        $('#filtroAnio, #filtroPeriodo').on('change', refrescar);
        refrescar();
    }


    /* ====================================================================
     * PANTALLA: PRESENTAR DECLARACION
     * ================================================================== */

    function presentar(cfg) {

        var catalogo     = [];   // actividades disponibles para el desplegable
        var anioCatalogo = null; // de que año es ese catalogo
        var abierta      = null; // la declaracion que se esta editando
        var sucio        = false; // hay cambios en pantalla sin guardar
        var guardando    = false; // candados contra el doble clic: la capa
        var presentando  = false; // #loading no se ve (loading.css la oculta)

        $('#tituloModulo').text(cfg.titulo);
        $('#etiquetaPeriodo').text(cfg.nombrePeriodo);

        llenarSelectAnios($('#nuevoAnio'));
        llenarSelectPeriodos($('#nuevoPeriodo'), cfg);

        // Si la pantalla de consulta mando un id -y, opcionalmente, una acción a
        // encadenar (firmar/presentar)-, se abre directamente. UNA sola vez: la
        // URL se limpia, para que recargar (F5) no vuelva a abrir el formulario
        // ni a pedir un código o presentar.
        var params    = new URLSearchParams(window.location.search);
        var idUrl     = params.get('id');
        var accionUrl = params.get('accion');
        if (idUrl && window.history && history.replaceState) {
            history.replaceState(null, '', window.location.pathname);
        }

        if (idUrl) { abrir(idUrl, accionUrl); } else { listarBorradores(); }

        /* ---------------- catalogo de actividades ---------------- */

        /*
         * El catalogo es el del AÑO DE LA DECLARACION que se abre, no el del
         * selector de "Crear": con el selector en otro año, las actividades
         * guardadas no aparecian en el desplegable, quedaban en blanco y
         * Guardar las borraba.
         */
        function cargarCatalogo(anio, luego) {
            anio = String(anio);
            if (anio === anioCatalogo) { luego(); return; }
            pedir(cfg, 8, { anio: anio }, function (r) {
                catalogo = r.datos || [];
                anioCatalogo = anio;
                luego();
            });
        }

        function opcionesActividad(a) {
            var sel = a.idActividad, esta = false;
            var html = '<option value="">Seleccione la actividad…</option>';
            catalogo.forEach(function (c) {
                if (c.id == sel) { esta = true; }
                html += '<option value="' + c.id + '"' + (c.id == sel ? ' selected' : '') + '>'
                      + escapar(c.codigo + ' — ' + c.descripcion) + '</option>';
            });
            // Guardada con una actividad que no está en el catálogo de su año
            // (la Alcaldía la retiró): se conserva, para que Guardar no la
            // borre sin que nadie lo note.
            if (sel && !esta) {
                html += '<option value="' + sel + '" data-tarifa="' + (Number(a.tarifa) || 0) + '" selected>'
                      + escapar((a.codigo || '') + ' — ' + (a.descripcion || '')) + '</option>';
            }
            return html;
        }

        /* ---------------- listado corto de lo pendiente ---------------- */

        function listarBorradores(luego) {
            pedir(cfg, 2, {}, function (r) {
                var $c = $('#tablaMias tbody').empty();
                // Esta pantalla es "Presentar Declaración": solo se trabajan los
                // borradores y las firmadas. Las presentadas/pagadas se ven en
                // "Consultar Declaraciones" (mismo split que el ICA). Sin este
                // filtro la misma declaracion presentada salia en las dos
                // pantallas a la vez (pedido cliente 2026-09-23).
                var filas = (r.datos || []).filter(function (f) {
                    return f.estadoClave !== 'presentada' && f.estadoClave !== 'pagada';
                });
                if (!filas.length) {
                    $c.append(vacio(6,
                        'No tiene declaraciones en edición',
                        'Elija el año y el período arriba y pulse "Crear declaración".',
                        'fa-file-o'));
                } else {
                    filas.forEach(function (f) {
                        $c.append(
                            '<tr>'
                          + '<td>' + f.anio + '</td>'
                          + '<td>' + nombrePeriodo(cfg, f.periodo) + '</td>'
                          + '<td>' + escapar(f.numero) + '</td>'
                          + '<td>' + insignia(f) + '</td>'
                          + '<td style="text-align:right;">' + pesos(f.total) + '</td>'
                          + '<td class="text-center">' + accionesRetencion(f, cfg) + '</td>'
                          + '</tr>'
                        );
                    });
                }
                if (luego) { luego(filas); }
            });
        }

        /* Después de firmar desde el listado: se refresca y se dice qué sigue.
           Antes el modal se cerraba y solo cambiaba la etiqueta de la fila. */
        function trasFirmar(id, rol) {
            listarBorradores(function (filas) {
                var f = filas.filter(function (x) { return String(x.id) === String(id); })[0];
                var texto = !f ? 'La firma quedó registrada.'
                    : f.estadoClave === 'pendienteCont'
                        ? 'Falta la firma del contador o revisor fiscal: use "Firmar contador", o "Presentar", que la pide.'
                        : 'Ya puede presentarla con "Presentar".';
                Swal.fire(rol === 'contador' ? 'Firmada por el contador' : 'Firmada', texto, 'success');
            });
        }

        // Mismos botones que el ICA. Editar abre el formulario; Firmar, Firmar
        // contador y Presentar se hacen AQUÍ, en el listado, que es donde el
        // cliente espera firmar (2026-09-28) y donde se ve el paso siguiente.
        $('#tablaMias').on('click', '.js-editar', function () { abrir($(this).data('id')); });
        $('#tablaMias').on('click', '.js-firmar', function () {
            var id = $(this).data('id');
            FirmaRetencion.abrir(cfg, String($(this).data('numero')), 'declarante',
                function () { trasFirmar(id, 'declarante'); });
        });
        $('#tablaMias').on('click', '.js-firmar-contador', function () {
            var id = $(this).data('id');
            FirmaRetencion.abrir(cfg, String($(this).data('numero')), 'contador',
                function () { trasFirmar(id, 'contador'); });
        });
        $('#tablaMias').on('click', '.js-presentar', function () {
            presentarDeclaracion($(this).data('id'), String($(this).data('numero')));
        });
        $('#tablaMias').on('click', '.js-corregir', function () {
            var id = $(this).data('id');
            Swal.fire({
                title: '¿Corregir esta declaración?',
                text: 'Se creará una nueva declaración que corrige a la presentada. La original no se modifica.',
                icon: 'question', showCancelButton: true,
                confirmButtonText: 'Sí, corregir', cancelButtonText: 'Cancelar'
            }).then(function (res) {
                if (!res.isConfirmed) { return; }
                pedir(cfg, 7, { id: id }, function (r) { abrir(r.datos.id); });
            });
        });
        $('#tablaMias').on('click', '.js-borrar', function () {
            var id = $(this).data('id');
            Swal.fire({
                title: '¿Eliminar este borrador?',
                text: 'Se perderá lo que haya diligenciado.',
                icon: 'warning', showCancelButton: true,
                confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar'
            }).then(function (res) {
                if (!res.isConfirmed) { return; }
                pedir(cfg, 5, { id: id }, function () { listarBorradores(); });
            });
        });

        /* ---------------- crear ---------------- */

        // Crear es "Crear y editar borradores" del panel de Roles: quien solo
        // firma o presenta no ve el boton (el servidor igual lo rebotaria).
        if (typeof erpPuede === 'function' && !erpPuede(String(cfg.modulo || '').toLowerCase() + '.editar')) {
            $('#btnCrear').prop('disabled', true).hide();
        }

        $('#btnCrear').on('click', function () {

            var periodo = $('#nuevoPeriodo').val();
            if (!periodo) {
                Swal.fire('Falta un dato', 'Seleccione el ' + cfg.nombrePeriodo + ' que va a declarar.', 'warning');
                return;
            }

            pedir(cfg, 1, { anio: $('#nuevoAnio').val(), periodo: periodo }, function (r) {
                // El backend avisa si reabrio un borrador en vez de crear uno.
                Swal.fire('Listo', r.mensaje, 'success');
                abrir(r.datos.id);
            });
        });

        /* ---------------- abrir y pintar ---------------- */

        function abrir(id, accion) {
            pedir(cfg, 3, { id: id }, function (r) {
                cargarCatalogo(r.datos.declaracion.anio, function () {
                    abierta = r.datos;
                    pintarFormulario();
                    $('#panelCrear').hide();
                    $('#panelFormulario').show();
                    $('html, body').animate({ scrollTop: 0 }, 200);

                    // Acción que llegó por la URL (hoy nadie la manda: Consultar
                    // solo lista presentadas). Presentar pide confirmación.
                    if (accion === 'firmar') {
                        FirmaRetencion.abrir(cfg, abierta.declaracion.numero, 'declarante', volverAlListado);
                    } else if (accion === 'presentar') {
                        presentarDeclaracion(abierta.declaracion.id, abierta.declaracion.numero);
                    }
                });
            });
        }

        function pintarFormulario() {

            var d = abierta.declaracion;
            var editable = abierta.editable == 1;

            $('#encNumero').text(d.numero);
            $('#encAnio').text(d.anio);
            $('#encPeriodo').text(nombrePeriodo(cfg, d.periodo));
            $('#encEstado').html(insignia(d));
            $('#encContribuyente').text(d.razon + '  (' + d.documento + ')');
            $('#encCorrige').html(d.corrige ? ('Corrige la declaración N° ' + escapar(d.corrige)) : '');

            pintarActividades(editable);
            pintarRenglones(editable);

            if (cfg.campoEnergia) {
                $('#bloqueEnergia').show();
                $('#impuestoEnergia')
                    .val(NumerosCOP.deBaseDeDatosAInput(abierta.extra.impuestoEnergia))
                    .prop('disabled', !editable);
            }

            /*
             * El PDF se puede ver en cualquier momento, tambien en borrador:
             * ahi sale con marca de agua BORRADOR y sin codigo escaneable, que
             * es justo para lo que sirve -revisar antes de presentar-.
             */
            if (cfg.pdf) {
                $('#btnPdf').attr('href', cfg.pdf + '?id=' + d.id).show();
            } else {
                $('#btnPdf').hide();
            }

            $('#btnAgregarActividad').toggle(editable && cfg.actividadesEditables);
            $('#btnGuardar, #btnLiquidar').toggle(editable);
            // Eliminar solo lo que no se ha presentado: en una presentada el
            // servidor lo rechaza y el aviso remitía a un "Corregir" que aquí
            // no existe.
            $('#btnDescartar').toggle(editable);
            $('#avisoCerrada').toggle(!editable);

            sucio = false;   // lo que acaba de pintarse es lo guardado
        }

        /* ---------------- actividades ---------------- */

        function pintarActividades(editable) {

            $('#tablaActividades tbody').empty();

            if (!abierta.actividades.length) { pintarFilaVacia(); return; }

            abierta.actividades.forEach(function (a) { filaActividad(a, editable); });
        }

        function pintarFilaVacia() {
            $('#tablaActividades tbody').empty().append('<tr class="js-vacia">'
                + vacio(5, 'Sin actividades', cfg.textoSinActividades, 'fa-list').replace(/^<tr><td colspan="5">/, '<td colspan="5">').replace(/<\/td><\/tr>$/, '</td>')
                + '</tr>');
        }

        function filaActividad(a, editable) {

            $('#tablaActividades tbody .js-vacia').remove();

            var celdaActividad = editable && cfg.actividadesEditables
                ? '<select class="form-control form-control-sm js-actividad">'
                  + opcionesActividad(a) + '</select>'
                : '<input type="hidden" class="js-actividad" value="' + (a.idActividad || '') + '">'
                  + escapar((a.codigo || '') + ' — ' + (a.descripcion || ''));

            // La tarifa se MUESTRA por mil, que es como la imprime el
            // formulario, pero viaja y se guarda en fraccion. La conversion
            // ocurre aqui y en ningun otro sitio.
            var porMil = (a.tarifa || 0) * 1000;

            $('#tablaActividades tbody').append(
                '<tr>'
              + '<td>' + celdaActividad + '</td>'
              + '<td class="text-right js-tarifa">' + porMil.toFixed(1) + '</td>'
              + '<td><input type="text" class="form-control form-control-sm text-right js-base" '
              +      'inputmode="numeric" value="' + NumerosCOP.deBaseDeDatosAInput(a.base) + '"'
              +      (editable ? '' : ' disabled') + '></td>'
              + '<td class="text-right js-valor">' + pesos(a.valor) + '</td>'
              + '<td class="text-center">' + (editable && cfg.actividadesEditables
                    ? '<button class="btn btn-danger btn-sm js-quitar" title="Quitar"><i class="fa fa-trash"></i></button>' : '')
              + '</td>'
              + '</tr>'
            );
        }

        $('#btnAgregarActividad').on('click', function () {
            filaActividad({ idActividad: '', tarifa: 0, base: 0, valor: 0 }, true);
            sucio = true;
            recalcularEnVivo();
        });

        // Quitar la última deja la tabla vacía. Antes se repintaban las
        // actividades GUARDADAS: reaparecían todas y no se podía dejar un mes
        // sin retenciones ni cambiar la única actividad.
        $('#tablaActividades').on('click', '.js-quitar', function () {
            $(this).closest('tr').remove();
            if (!$('#tablaActividades tbody tr').length) { pintarFilaVacia(); }
            sucio = true;
            recalcularEnVivo();
        });

        // Al cambiar la actividad se refresca la tarifa mostrada, para que el
        // usuario vea con que se le va a liquidar antes de pulsar nada.
        $('#tablaActividades').on('change', '.js-actividad', function () {
            var id = $(this).val(), $fila = $(this).closest('tr');
            var act = catalogo.filter(function (a) { return a.id == id; })[0];
            // La actividad guardada que ya no esta en el catalogo lleva su
            // tarifa en la opcion: sin esto, volver a elegirla la mostraba en 0.
            var tarifa = act ? act.tarifa : (parseFloat($(this).find('option:selected').data('tarifa')) || 0);
            $fila.find('.js-tarifa').text((tarifa * 1000).toFixed(1));
            recalcularEnVivo();
        });

        /* -----------------------------------------------------------------
         * CALCULO EN VIVO (vista previa; el servidor manda al guardar)
         * -----------------------------------------------------------------
         * El cliente pidio (2026-09-14) que los totales se vean sumar a
         * medida que se escribe, no solo al pulsar "Liquidar". Se replica
         * EXACTAMENTE la aritmetica del servidor -las mismas sumas/restas y
         * el mismo redondeo a miles fila por fila- para que la vista previa
         * coincida con lo que guarda el backend.
         *
         * La fuente de verdad SIGUE siendo el servidor: Guardar, Liquidar y
         * Presentar repintan con la respuesta del backend; esto es solo lo
         * que se ve mientras se teclea. Cada modulo declara su formula en
         * cfg.calcular (junto a donde se define la pantalla), para no meter
         * reglas de impuesto en el motor compartido.
         *
         * Referencias que deben coincidir:
         *   valor de actividad  business/class.retenciones.php  ROUND(base*tarifa/1000,0)*1000
         *   casillas derivadas  BD/migraciones/030 y 031 (columna ren_Formula)
         */
        function valorActividadFila($f) {
            var base   = NumerosCOP.aEntero($f.find('.js-base').val());
            var porMil = parseFloat($f.find('.js-tarifa').text()) || 0;  // tarifa "por mil"
            var tarifa = porMil / 1000;                                  // -> fraccion, como acc_Tarifa
            // Identico a class.retenciones.php: ROUND(base*tarifa/1000,0)*1000.
            return Math.round(base * tarifa / 1000) * 1000;
        }

        function recalcularEnVivo() {
            if (typeof cfg.calcular !== 'function') { return; }

            // Lo que escribe el contribuyente (en cualquiera de las dos tablas).
            var v = {};
            $('.js-renglon').each(function () {
                v[$(this).data('codigo')] = NumerosCOP.aEntero($(this).val());
            });

            // Valor de cada actividad, redondeado como el servidor, y su suma.
            var sumaActividades = 0;
            $('#tablaActividades tbody tr').each(function () {
                var $f = $(this);
                if (!$f.find('.js-base').length) { return; }   // fila "sin actividades"
                var valor = valorActividadFila($f);
                $f.find('.js-valor').text(pesos(valor));
                sumaActividades += valor;
            });

            var energia = cfg.campoEnergia ? NumerosCOP.aEntero($('#impuestoEnergia').val()) : 0;

            var derivadas = cfg.calcular(v, sumaActividades, energia) || {};
            Object.keys(derivadas).forEach(function (cod) {
                $('.js-calculado[data-codigo="' + cod + '"]').text(pesos(derivadas[cod]));
            });
        }

        // Recalcular mientras se teclea, sin perder el foco: solo cambian los
        // textos calculados (.js-valor / .js-calculado), nunca los campos que se
        // estan editando.
        $('#panelFormulario')
            .on('input', '.js-renglon, .js-base, #impuestoEnergia', recalcularEnVivo);

        // Cualquier cosa que el usuario escriba o elija es un cambio sin guardar.
        $('#panelFormulario').on('input change', 'input, select', function () { sucio = true; });

        /* ---------------- renglones ---------------- */

        function pintarRenglones(editable) {

            var $liq = $('#tablaRenglones tbody').empty();

            // Cuando la pantalla separa los ingresos (autorretencion: ingresos ->
            // actividades -> liquidacion, igual que el ICA), las casillas hasta
            // cfg.ingresosHasta se pintan en su propia tabla, ANTES de las
            // actividades. Si la pantalla no tiene #tablaIngresos (retencion),
            // todo va a la tabla de liquidacion, como siempre.
            var $ing = cfg.ingresosHasta ? $('#tablaIngresos tbody').empty() : $();
            var separaIngresos = $ing.length > 0;

            abierta.renglones.forEach(function (r) {

                var celda;

                if (r.manual) {
                    celda = '<input type="text" class="form-control form-control-sm text-right js-renglon" '
                          + 'data-codigo="' + r.codigo + '" inputmode="numeric" value="'
                          + NumerosCOP.deBaseDeDatosAInput(r.valor) + '"'
                          + (editable ? '' : ' disabled') + '>';
                } else {
                    celda = '<span class="js-calculado" data-codigo="' + r.codigo + '">'
                          + pesos(r.valor) + '</span>';
                }

                /*
                 * Un renglon calculado sin formula todavia no se puede liquidar.
                 * Se dice en pantalla en vez de mostrar un cero limpio: un cero
                 * sin explicacion se lee como "no debe nada", que es justo la
                 * conclusion equivocada.
                 */
                var nota = r.pendiente
                    ? ' <span class="chip-estado est-borrador" title="El cálculo de esta casilla está '
                    + 'pendiente de confirmación por la Alcaldía.">pendiente</span>'
                    : '';

                var $destino = (separaIngresos && Number(r.codigo) <= cfg.ingresosHasta)
                    ? $ing : $liq;

                $destino.append(
                    '<tr' + (r.pendiente ? ' class="table-warning"' : '') + '>'
                  + '<td class="text-muted">' + r.codigo + '</td>'
                  + '<td>' + escapar(r.nombre) + nota + '</td>'
                  + '<td class="text-right" style="width:200px">' + celda + '</td>'
                  + '</tr>'
                );
            });

            $('#avisoPendientes').toggle(
                abierta.renglones.some(function (r) { return r.pendiente == 1; })
            );
        }

        /* ---------------- guardar / liquidar / presentar ---------------- */

        function recoger() {

            var datos = { id: abierta.declaracion.id, renglones: {}, actividades: [] };

            $('.js-renglon').each(function () {
                datos.renglones[$(this).data('codigo')] = NumerosCOP.aEntero($(this).val());
            });

            $('#tablaActividades tbody tr').each(function () {
                var idAct = $(this).find('.js-actividad').val();
                if (!idAct) { return; }
                datos.actividades.push({
                    idActividad: idAct,
                    base: NumerosCOP.aEntero($(this).find('.js-base').val())
                });
            });

            if (cfg.campoEnergia) {
                datos.impuestoEnergia = NumerosCOP.aEntero($('#impuestoEnergia').val());
            }

            return datos;
        }

        function guardar(alTerminar) {
            if (guardando) { return; }
            guardando = true;

            // Mientras viaja, el formulario no se edita: lo que se escribiera
            // en ese rato no iba en el envio y se perdia al repintar con la
            // respuesta, sin aviso. Al volver, pintarFormulario rehace los
            // campos; si falla, se sueltan los mismos que se trabaron aqui.
            var $trabados = $('#panelFormulario').find('input, select')
                .filter(':not([disabled]):not([readonly])');
            $trabados.filter('input').prop('readonly', true);
            $trabados.filter('select').prop('disabled', true);

            pedir(cfg, 4, recoger(), function (r) {
                abierta = r.datos;
                pintarFormulario();
                if (alTerminar) { alTerminar(r); }
            }).always(function () {
                guardando = false;
                $trabados.filter('input').prop('readonly', false);
                $trabados.filter('select').prop('disabled', false);
            });
        }

        /* Una fila con base pero sin actividad elegida se descartaba en
           silencio al guardar (recoger() la salta): se avisa antes. */
        function filasSinActividad() {
            var n = 0;
            $('#tablaActividades tbody tr').each(function () {
                var $f = $(this);
                if (!$f.find('.js-base').length) { return; }   // fila "sin actividades"
                if (!$f.find('.js-actividad').val() && NumerosCOP.aEntero($f.find('.js-base').val()) > 0) { n++; }
            });
            return n;
        }

        /*
         * Guardar (o liquidar, que guarda) una declaración firmada le quita las
         * firmas en el servidor SI CAMBIÓ algo: lo firmado dejaría de ser lo
         * guardado. Se avisa antes, y solo si hay cambios en pantalla.
         */
        function confirmarSiFirmada(textoBoton, seguir) {
            if (filasSinActividad()) {
                Swal.fire('Falta la actividad', 'Hay una fila con valor pero sin actividad elegida. '
                        + 'Elija la actividad o quite la fila.', 'warning');
                return;
            }
            var e = abierta && abierta.declaracion && abierta.declaracion.estadoClave;
            if ((e !== 'firmada' && e !== 'pendienteCont') || !sucio) { seguir(); return; }
            Swal.fire({
                title: 'La declaración ya está firmada',
                text: 'Si cambió algún dato, al guardar se quitan las firmas y habrá que firmarla de nuevo. ¿Continuar?',
                icon: 'warning', showCancelButton: true,
                confirmButtonText: textoBoton, cancelButtonText: 'Cancelar'
            }).then(function (res) {
                if (res.isConfirmed) { seguir(); }
            });
        }

        function volverAlListado() {
            $('#panelFormulario').hide();
            $('#panelCrear').show();
            listarBorradores();
        }

        // "Liquidar" y "Guardar" son el mismo camino: solo corre sobre
        // borradores, asi que recalcular guardando no pisa nada presentado.
        // Tener dos caminos distintos ya produjo, en el ICA, dos cifras
        // discrepando en la misma pantalla.
        $('#btnLiquidar').on('click', function () {
            confirmarSiFirmada('Sí, liquidar', function () {
                guardar(function (r) {
                    Swal.fire('Liquidada', r.datos && r.datos.firmasQuitadas == 1
                        ? 'Se recalcularon los valores. Como cambió, se quitaron las firmas.'
                        : 'Se recalcularon los valores.', 'success');
                });
            });
        });

        // Guardar vuelve al listado, que es donde se firma y se presenta (como
        // el ICA desde el 2026-09-25; el cliente lo pidió aquí el 2026-09-28).
        $('#btnGuardar').on('click', function () {
            confirmarSiFirmada('Sí, guardar', function () {
                guardar(function (r) {
                    var e = r.datos && r.datos.declaracion && r.datos.declaracion.estadoClave;
                    volverAlListado();
                    Swal.fire('Declaración guardada',
                        r.datos && r.datos.firmasQuitadas == 1
                            ? 'Como cambió, se quitaron las firmas: fírmela de nuevo desde el listado.'
                            : (e === 'firmada' || e === 'pendienteCont')
                                ? 'No cambió nada de lo firmado: las firmas siguen. Desde el listado puede presentarla.'
                                : 'Desde el listado puede firmarla y presentarla.', 'success');
                });
            });
        });

        /**
         * Presenta. Si el backend contesta que falta una firma, abre el modal
         * para esa firma y vuelve a intentarlo al terminar. Al final, al listado.
         *
         * ES UN SOLO BOTÓN de principio a fin: la persona pulsa "Presentar" en
         * el listado y el sistema le va pidiendo lo que haga falta -su firma y,
         * si el contribuyente tiene contador registrado, la de él-. Es el mismo
         * comportamiento que el cliente pidió para el ICA; obligar a buscar un
         * botón "Firmar" aparte es donde la gente se queda atascada. El
         * formulario solo guarda: por pedido del cliente ya no tiene botón
         * "Presentar" propio.
         *
         * El reintento NO es un bucle infinito: cada vuelta ocurre solo después
         * de una firma registrada de verdad, y solo hay dos firmas posibles.
         */
        function presentarDeclaracion(id, numero, confirmada) {

            if (presentando) { return; }

            // Se pregunta antes, como en el ICA: presentar no tiene vuelta
            // atrás. Los reintentos de la cadena de firmas ya van confirmados.
            if (!confirmada) {
                Swal.fire({
                    title: '¿Presentar la declaración?',
                    text: 'Al presentarla ya no podrá editarla; si hay que cambiar algo después, se hace con "Corregir".',
                    icon: 'warning', showCancelButton: true,
                    confirmButtonText: 'Sí, presentar', cancelButtonText: 'Cancelar'
                }).then(function (res) {
                    if (res.isConfirmed) { presentarDeclaracion(id, numero, true); }
                });
                return;
            }

            presentando = true;
            pedir(cfg, 6, { id: id },

                function () {
                    volverAlListado();
                    Swal.fire('Presentada', 'La declaración quedó presentada. Está en "Consultar Declaraciones".', 'success');
                },

                function (r) {
                    var falta = r.datos && r.datos.falta;

                    if (falta !== 'declarante' && falta !== 'contador') {
                        // Cualquier otro rechazo se muestra tal cual, y el
                        // listado se refresca: si al recalcular cambio la
                        // liquidacion, el servidor quito las firmas.
                        Swal.fire('Atención', r.mensaje || 'No se pudo presentar.', 'warning');
                        listarBorradores();
                        return;
                    }

                    FirmaRetencion.abrir(cfg, numero, falta,
                        function () { presentarDeclaracion(id, numero, true); });
                }
            ).always(function () { presentando = false; });
        }

        $('#btnDescartar').on('click', function () {
            var e = abierta.declaracion.estadoClave;
            var firmada = (e === 'firmada' || e === 'pendienteCont');
            Swal.fire({
                title: firmada ? '¿Eliminar esta declaración firmada?' : '¿Eliminar este borrador?',
                text: firmada
                    ? 'Ya tiene firmas: se pierden lo diligenciado y las firmas.'
                    : 'Se perderá lo que haya diligenciado.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then(function (res) {
                if (!res.isConfirmed) { return; }
                pedir(cfg, 5, { id: abierta.declaracion.id }, volverAlListado);
            });
        });

        // Volver sin guardar pregunta si hay cambios: ahora que Firmar y
        // Presentar están en el listado, salir sin guardar llevaba a firmar lo
        // último guardado, que podía ser un formulario en cero.
        $('#btnVolver').on('click', function () {
            if (!sucio || !(abierta && abierta.editable == 1)) { volverAlListado(); return; }
            Swal.fire({
                title: 'Hay cambios sin guardar',
                text: 'Si vuelve al listado, se pierden. ¿Salir sin guardar?',
                icon: 'warning', showCancelButton: true,
                confirmButtonText: 'Salir sin guardar', cancelButtonText: 'Seguir editando'
            }).then(function (res) {
                if (res.isConfirmed) { volverAlListado(); }
            });
        });
    }


    return {
        consultar: consultar,
        presentar: presentar
    };

})();
