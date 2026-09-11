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

        $('#loading').show();

        $.ajax({
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
     * ya incluyen, y sus cuatro variantes coinciden una a una con los estados
     * que devuelve el backend. Usar el mismo componente no es cosmética: el
     * cliente compara las pantallas entre sí, y dos formas distintas de pintar
     * "Presentada" en el mismo sistema se leen como descuido.
     */
    function insignia(fila) {
        var textos = {
            borrador:   'Borrador',
            firmada:    'Firmada',
            presentada: 'Presentada',
            pagada:     'Pagada'
        };
        var clave = textos[fila.estadoClave] ? fila.estadoClave : 'borrador';
        return '<span class="chip-estado est-' + clave + '">' + textos[clave] + '</span>';
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

            if (!esReenvio) {
                Swal.fire({
                    title: 'Generando código',
                    text: 'Por favor espere…',
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
            _cfg      = cfg;
            _numero   = numero;
            _rol      = (rol === 'contador') ? 'contador' : 'declarante';
            _alFirmar = alFirmar || null;

            _asegurarModal();
            _pararTimers();
            _limpiarError();
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

        function pintar(filas) {

            var $cuerpo = $('#tablaDeclaraciones tbody').empty();

            $('#conteoDeclaraciones').text(
                filas.length + (filas.length === 1 ? ' declaración' : ' declaraciones')
            );

            if (!filas.length) {
                $cuerpo.append(vacio(7,
                    'Ninguna declaración coincide con el filtro',
                    'Pruebe con otro año o quite el filtro de período.',
                    'fa-filter'));
                return;
            }

            filas.forEach(function (f) {

                /* Botones con icono y title, como en icaWebConsultar: la
                   columna de acciones del ICA no lleva texto. */
                var acciones =
                    '<button class="btn btn-info btn-sm mr-1 js-ver" data-id="' + f.id + '" '
                  + 'title="Ver declaración"><i class="fa fa-eye"></i></button>';

                // Corregir solo sobre una presentada; es la unica via legal de
                // cambiar algo ya declarado.
                if (f.estado === 2) {
                    acciones += '<button class="btn btn-warning btn-sm mr-1 js-corregir" data-id="'
                              + f.id + '" title="Corregir"><i class="fa fa-pencil"></i></button>';
                }

                /*
                 * El boton de PDF solo aparece si el generador existe. Al
                 * 2026-09-08 todavia no se ha escrito el de estos dos modulos,
                 * y un boton que lleva a un 404 es peor que no tenerlo: el
                 * usuario cree que el sistema fallo.
                 */
                if (cfg.pdf) {
                    acciones += '<a class="btn btn-primary btn-sm" target="_blank" title="Descargar PDF" href="'
                              + cfg.pdf + '?id=' + f.id + '"><i class="fa fa-download"></i></a>';
                }

                $cuerpo.append(
                    '<tr>'
                  + '<td>' + f.anio + '</td>'
                  + '<td>' + nombrePeriodo(cfg, f.periodo) + '</td>'
                  + '<td>' + escapar(f.numero) + '</td>'
                  + '<td>' + insignia(f) + '</td>'
                  + '<td>' + (f.corrige ? ('Corrige la ' + escapar(f.corrige)) : '—') + '</td>'
                  + '<td style="text-align:right;">' + pesos(f.total) + '</td>'
                  + '<td class="text-center" style="white-space:nowrap;">' + acciones + '</td>'
                  + '</tr>'
                );
            });
        }

        $('#tablaDeclaraciones').on('click', '.js-ver', function () {
            window.location = cfg.pantallaPresentar + '?id=' + $(this).data('id');
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

        var catalogo = [];   // actividades disponibles para el desplegable
        var abierta  = null; // la declaracion que se esta editando

        $('#tituloModulo').text(cfg.titulo);
        $('#etiquetaPeriodo').text(cfg.nombrePeriodo);

        llenarSelectAnios($('#nuevoAnio'));
        llenarSelectPeriodos($('#nuevoPeriodo'), cfg);

        // Si la pantalla de consulta mando un id, se abre directamente.
        var idUrl = new URLSearchParams(window.location.search).get('id');

        cargarCatalogo(function () {
            if (idUrl) { abrir(idUrl); } else { listarBorradores(); }
        });

        /* ---------------- catalogo de actividades ---------------- */

        function cargarCatalogo(luego) {
            pedir(cfg, 8, { anio: $('#nuevoAnio').val() }, function (r) {
                catalogo = r.datos;
                luego();
            });
        }

        function opcionesActividad(seleccionada) {
            var html = '<option value="">Seleccione la actividad…</option>';
            catalogo.forEach(function (a) {
                html += '<option value="' + a.id + '"' + (a.id == seleccionada ? ' selected' : '') + '>'
                      + escapar(a.codigo + ' — ' + a.descripcion) + '</option>';
            });
            return html;
        }

        /* ---------------- listado corto de lo pendiente ---------------- */

        function listarBorradores() {
            pedir(cfg, 2, {}, function (r) {
                var $c = $('#tablaMias tbody').empty();
                if (!r.datos.length) {
                    $c.append(vacio(6,
                        'Todavía no ha creado ninguna declaración',
                        'Elija el año y el período arriba y pulse "Crear declaración".',
                        'fa-file-o'));
                    return;
                }
                r.datos.forEach(function (f) {
                    $c.append(
                        '<tr>'
                      + '<td>' + f.anio + '</td>'
                      + '<td>' + nombrePeriodo(cfg, f.periodo) + '</td>'
                      + '<td>' + escapar(f.numero) + '</td>'
                      + '<td>' + insignia(f) + '</td>'
                      + '<td style="text-align:right;">' + pesos(f.total) + '</td>'
                      + '<td class="text-center"><button class="btn btn-info btn-sm js-abrir" data-id="'
                      + f.id + '" title="Abrir"><i class="fa fa-folder-open"></i></button></td>'
                      + '</tr>'
                    );
                });
            });
        }

        $('#tablaMias').on('click', '.js-abrir', function () { abrir($(this).data('id')); });

        /* ---------------- crear ---------------- */

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

        function abrir(id) {
            pedir(cfg, 3, { id: id }, function (r) {
                abierta = r.datos;
                pintarFormulario();
                $('#panelCrear').hide();
                $('#panelFormulario').show();
                $('html, body').animate({ scrollTop: 0 }, 200);
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
            $('#btnGuardar, #btnLiquidar, #btnPresentar').toggle(editable);
            $('#avisoCerrada').toggle(!editable);
        }

        /* ---------------- actividades ---------------- */

        function pintarActividades(editable) {

            var $c = $('#tablaActividades tbody').empty();

            if (!abierta.actividades.length) {
                $c.append('<tr class="js-vacia">'
                        + vacio(5, 'Sin actividades', cfg.textoSinActividades, 'fa-list').replace(/^<tr><td colspan="5">/, '<td colspan="5">').replace(/<\/td><\/tr>$/, '</td>')
                        + '</tr>');
                return;
            }

            abierta.actividades.forEach(function (a) { filaActividad(a, editable); });
        }

        function filaActividad(a, editable) {

            $('#tablaActividades tbody .js-vacia').remove();

            var celdaActividad = editable && cfg.actividadesEditables
                ? '<select class="form-control form-control-sm js-actividad">'
                  + opcionesActividad(a.idActividad) + '</select>'
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
        });

        $('#tablaActividades').on('click', '.js-quitar', function () {
            $(this).closest('tr').remove();
            if (!$('#tablaActividades tbody tr').length) { pintarActividades(true); }
        });

        // Al cambiar la actividad se refresca la tarifa mostrada, para que el
        // usuario vea con que se le va a liquidar antes de pulsar nada.
        $('#tablaActividades').on('change', '.js-actividad', function () {
            var id = $(this).val(), $fila = $(this).closest('tr');
            var act = catalogo.filter(function (a) { return a.id == id; })[0];
            $fila.find('.js-tarifa').text(act ? ((act.tarifa * 1000).toFixed(1)) : '0.0');
        });

        /* ---------------- renglones ---------------- */

        function pintarRenglones(editable) {

            var $c = $('#tablaRenglones tbody').empty();

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

                $c.append(
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
            pedir(cfg, 4, recoger(), function (r) {
                abierta = r.datos;
                pintarFormulario();
                if (alTerminar) { alTerminar(); }
            });
        }

        // "Liquidar" y "Guardar" son el mismo camino: solo corre sobre
        // borradores, asi que recalcular guardando no pisa nada cerrado. Tener
        // dos caminos distintos ya produjo, en el ICA, dos cifras discrepando
        // en la misma pantalla.
        $('#btnLiquidar').on('click', function () {
            guardar(function () { Swal.fire('Liquidada', 'Se recalcularon los valores.', 'success'); });
        });

        $('#btnGuardar').on('click', function () {
            guardar(function () { Swal.fire('Guardada', 'Los datos quedaron guardados.', 'success'); });
        });

        /**
         * Intenta presentar. Si el backend contesta que falta una firma, abre
         * el modal para esa firma y vuelve a intentarlo al terminar.
         *
         * ES UN SOLO BOTÓN de principio a fin: la persona pulsa "Presentar" y
         * el sistema le va pidiendo lo que haga falta -su firma y, si el
         * contribuyente tiene contador registrado, la de él-. Es el mismo
         * comportamiento que el cliente pidió para el ICA; obligar a buscar un
         * botón "Firmar" aparte es donde la gente se queda atascada.
         *
         * El reintento NO es un bucle infinito: cada vuelta ocurre solo después
         * de una firma registrada de verdad, y solo hay dos firmas posibles.
         */
        function intentarPresentar() {

            pedir(cfg, 6, { id: abierta.declaracion.id },

                function () {
                    Swal.fire('Presentada', 'La declaración quedó presentada.', 'success');
                    abrir(abierta.declaracion.id);
                },

                function (r) {
                    var falta = r.datos && r.datos.falta;

                    if (falta !== 'declarante' && falta !== 'contador') {
                        // Cualquier otro rechazo se muestra tal cual.
                        Swal.fire('Atención', r.mensaje || 'No se pudo presentar.', 'warning');
                        return;
                    }

                    FirmaRetencion.abrir(
                        cfg,
                        abierta.declaracion.numero,
                        falta,
                        intentarPresentar
                    );
                }
            );
        }

        $('#btnPresentar').on('click', function () {
            Swal.fire({
                title: '¿Presentar la declaración?',
                text: 'Se le pedirá firmar con un código que enviaremos a su correo. '
                    + 'Una vez presentada no podrá editarla; solo corregirla.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, presentar',
                cancelButtonText: 'Cancelar'
            }).then(function (res) {
                if (!res.isConfirmed) { return; }
                // Se guarda primero: lo que se presenta tiene que ser lo que
                // el usuario tiene en pantalla, no lo que se guardo hace rato.
                guardar(intentarPresentar);
            });
        });

        $('#btnDescartar').on('click', function () {
            Swal.fire({
                title: '¿Eliminar este borrador?',
                text: 'Se perderá lo que haya diligenciado.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then(function (res) {
                if (!res.isConfirmed) { return; }
                pedir(cfg, 5, { id: abierta.declaracion.id }, function () {
                    $('#panelFormulario').hide();
                    $('#panelCrear').show();
                    listarBorradores();
                });
            });
        });

        $('#btnVolver').on('click', function () {
            $('#panelFormulario').hide();
            $('#panelCrear').show();
            listarBorradores();
        });

        // Cambiar el año recarga el catalogo: las tarifas son por año.
        $('#nuevoAnio').on('change', function () { cargarCatalogo(function () {}); });
    }


    return {
        consultar: consultar,
        presentar: presentar
    };

})();
