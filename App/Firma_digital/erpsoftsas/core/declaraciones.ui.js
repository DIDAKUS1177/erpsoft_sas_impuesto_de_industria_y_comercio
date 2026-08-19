/**
 * Modulo compartido entre icaWebPresentar.js e icaWebConsultar.js.
 *
 * Antes cada pantalla tenia su propia copia (ligeramente distinta) de la
 * logica que decide que botones mostrar para una declaracion segun su
 * estado de firma. Eso fue lo que causo que "Presentar Declaracion"
 * mostrara botones muertos (href="#") mientras "Consultar Declaraciones"
 * si funcionaba: un cambio en un lado nunca se replicaba al otro.
 *
 * A partir de ahora ambas pantallas llaman a las mismas funciones de aqui,
 * asi que un fix futuro aplica a las dos automaticamente.
 */
var DeclaracionesUI = (function () {

    function nombreMes(mes) {
        var meses = [
            '', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
        ];
        return meses[mes] || mes;
    }

    /**
     * Botones de accion para UNA declaracion, segun su estado real.
     *
     * Antes solo se miraba is_signed, asi que una declaracion ya
     * presentada seguia ofreciendo el boton "Presentar" (y una pagada,
     * el de "Pagar"). Ahora cada estado ofrece unicamente lo que todavia
     * tiene sentido hacer:
     *
     *   sin declaracion -> los 3 botones de borrador, deshabilitados
     *   borrador        -> Editar borrador / Firmar / Ver borrador
     *   firmada         -> Descargar PDF / Presentar / Pagar
     *   presentada      -> Descargar PDF / Pagar
     *   pagada          -> Descargar PDF
     *
     * @param {object|null} d Fila devuelta por class.declaracionesIca.php (funcion 8).
     * @param {string} objJs  Nombre de la variable global (en la pantalla que
     *                        llama) que expone editarDeclaracion/abrirFirmaDigital/
     *                        presentarDeclaracion, p.ej. "establecimientos".
     */
    function htmlAcciones(d, objJs) {

        objJs = objJs || 'establecimientos';

        if (!d || !d.dec_Id) {
            var deshabilitado = function (clase, icono, titulo) {
                return '<a href="#" class="btn ' + clase + ' btn-sm mr-1" title="' + titulo + '" ' +
                       'aria-disabled="true" tabindex="-1" style="pointer-events:none;opacity:.4;">' +
                       '<i class="fa ' + icono + '"></i></a>';
            };
            return deshabilitado('btn-warning', 'fa-pencil', 'Editar Borrador') +
                   deshabilitado('btn-secondary', 'fa-certificate', 'Firmar') +
                   deshabilitado('btn-info', 'fa-eye', 'Ver Borrador');
        }

        var clave = estado(d).clave;

        // Descargar el formulario oficial esta disponible en todos los
        // estados: es el mismo documento, con o sin sello segun avance.
        var descargar = '<a href="../extensiones/declaracion.php?dec_Id=' + d.dec_Id + '" target="_blank" ' +
                            'class="btn btn-primary btn-sm mr-1" title="Descargar"><i class="fa fa-download"></i></a>';

        if (clave === 'borrador') {
            return '<a href="javascript:void(0);" onclick="' + objJs + '.editarDeclaracion(' + d.dec_Id + ')" ' +
                       'class="btn btn-warning btn-sm mr-1" title="Editar"><i class="fa fa-pencil"></i></a>' +
                   '<a href="javascript:void(0);" onclick="' + objJs + '.abrirFirmaDigital(' + d.dec_Id + ', ' + d.dec_IdEstablecimiento + ')" ' +
                       'class="btn btn-secondary btn-sm mr-1" title="Firmar"><i class="fa fa-pencil-square-o"></i></a>' +
                   descargar +
                   '<a href="javascript:void(0);" onclick="' + objJs + '.borrarDeclaracion(' + d.dec_Id + ')" ' +
                       'class="btn btn-danger btn-sm mr-1" title="Borrar borrador"><i class="fa fa-trash"></i></a>';
        }

        if (clave === 'pendienteCont' || clave === 'firmada') {
            // "Editar borrador" sobre una firmada BORRA las firmas y devuelve
            // la declaracion a borrador (regla del cliente): dejarian de
            // acreditar el contenido si este cambia.
            var acciones = '<a href="javascript:void(0);" onclick="' + objJs + '.editarFirmada(' + d.dec_Id + ')" ' +
                               'class="btn btn-warning btn-sm mr-1" title="Editar borrador (elimina las firmas)"><i class="fa fa-pencil"></i></a>' +
                           descargar;

            if (clave === 'pendienteCont') {
                // Falta la firma del contador/revisor, pero para quien
                // presenta esto ya NO es un paso aparte: el boton dice
                // "Presentar" igual que en el estado siguiente, y es
                // presentarDeclaracion() -no firmaContador()- quien decide
                // que hacer. Al intentar presentar sin esa firma, el
                // backend responde con datos.codigo="FALTA_CONTADOR" y el
                // frontend abre ahi mismo el OTP del contador; en cuanto
                // firma, reintenta presentar solo. Un unico click de
                // principio a fin, en vez de "manda el codigo" y luego
                // "ahora si presenta" como dos acciones separadas.
                if (Number(d.tiene_correo_contador) === 1) {
                    acciones += '<a href="javascript:void(0);" onclick="' + objJs + '.presentarDeclaracion(' + d.dec_Id + ', ' + d.dec_IdEstablecimiento + ')" ' +
                                    'class="btn btn-success btn-sm mr-1" title="Presentar">' +
                                    '<i class="fa fa-paper-plane"></i></a>';
                } else {
                    // Sin correo registrado no hay a donde mandar el codigo:
                    // se dice que falta el dato en vez de fallar al pulsar.
                    acciones += '<button type="button" class="btn btn-success btn-sm mr-1" disabled ' +
                                    'title="Registre el correo del contador o revisor fiscal en el RIT antes de presentar">' +
                                    '<i class="fa fa-paper-plane"></i></button>';
                }
                return acciones;
            }

            return acciones +
                   '<a href="javascript:void(0);" onclick="' + objJs + '.presentarDeclaracion(' + d.dec_Id + ', ' + d.dec_IdEstablecimiento + ')" ' +
                       'class="btn btn-success btn-sm mr-1" title="Presentar"><i class="fa fa-paper-plane"></i></a>';
        }

        // presentada / pagada
        var botones = descargar;

        /*
         * Correccion: genera una declaracion nueva enlazada a esta. Una vez
         * presentada no existe un "crear otra declaracion" -para la ley es
         * UNA declaracion original por contribuyente y periodo; presentar
         * una segunda "original" para el mismo periodo se ve, ante la
         * Alcaldia, como una doble declaracion, no como una correccion
         * legitima-. Este boton ES la forma correcta de "declarar de
         * nuevo": antes era solo un icono de lapiz sin mas contexto, y
         * quien no sabia que "Corregir" cumple ese papel sentia que la
         * pantalla no ofrecia ninguna salida despues de presentar.
         */
        botones += '<a href="javascript:void(0);" onclick="' + objJs + '.corregirDeclaracion(' + d.dec_Id + ')" ' +
                       'class="btn btn-warning btn-sm mr-1" ' +
                       'title="¿Necesitas declarar de nuevo este período? Esta es la forma correcta: genera una corrección enlazada a la presentada.">' +
                       '<i class="fa fa-pencil"></i> Corregir</a>';

        // Aqui vivia un boton de "Código de barras" que abria liquidacion.php.
        // Se quita: el codigo de barras NO es un documento aparte, va impreso
        // dentro de la propia declaracion que ya se descarga con el boton de
        // Descargar. Tener un boton propio para el sugeria que era otra cosa y
        // solo sumaba ruido a la fila.

        // Pago PSE (PlacetoPay/Avalpaycenter): crearSesion.php crea la sesion
        // y redirige directo al banco -por eso target="_blank", en vez de
        // onclick+JS-, igual que "Descargar" y el boton de arriba.
        // Ahora mismo contra el ambiente de PRUEBAS del banco (ver
        // config.municipio.php, PLACETOPAY_BASEURL); cambiar a producción
        // solo cuando el banco entregue las credenciales reales.
        // Solo en PRESENTADAS: pagar un borrador dejaba la declaracion
        // "pagada pero sin presentar", un estado que despues no se puede
        // corregir. crearSesion.php lo valida tambien del lado del servidor;
        // esto solo evita ofrecer un boton que va a rebotar.
        if (clave === 'presentada') {
            botones += '<a href="../extensiones/pse/crearSesion.php?dec_Id=' + d.dec_Id + '" target="_blank" ' +
                           'class="btn btn-danger btn-sm" title="Pagar por PSE">' +
                           '<i class="fa fa-money"></i></a>';
        }

        return botones;
    }

    /**
     * Estado real de una declaracion, derivado de los datos que ya trae
     * el listado. Antes el usuario tenia que deducirlo mirando que
     * botones le aparecian.
     *
     * Los 4 estados salen de columnas reales:
     *   borrador   -> no existe registro en firmas_declaraciones
     *   firmada    -> firmada, pero dec_Estado aun no es 2
     *   presentada -> dec_Estado = 2
     *   pagada     -> dec_Pagado = 1
     *
     * No se incluye "vencida" a proposito: haria falta la fecha maxima de
     * presentacion del calendario tributario y hoy el sistema no la guarda
     * en ninguna columna, asi que habria que inventarla.
     */
    var ESTADOS = {
        borrador:      { texto: 'Borrador',          clase: 'est-borrador',   paso: 2 },
        pendienteCont: { texto: 'Falta contador',    clase: 'est-firmada',    paso: 3 },
        firmada:       { texto: 'Firmada',           clase: 'est-firmada',    paso: 4 },
        presentada:    { texto: 'Presentada',        clase: 'est-presentada', paso: 5 },
        pagada:        { texto: 'Pagada',            clase: 'est-pagada',     paso: 6 }
    };

    /*
     * Presentar exige la firma del declarante y, cuando el contribuyente
     * tiene registrado un contador o revisor fiscal, tambien la de esa
     * persona (ver _requiereContador en class.declaracionesICA.php, que es
     * quien calcula d.requiere_contador -la regla vive una sola vez, en el
     * backend, para que este archivo no se desactualice si cambia).
     *
     * Cuando no aplica, "pendienteCont" se salta: firmar el declarante deja
     * la declaracion directo en "firmada".
     */
    function claveEstado(d) {
        if (!d) { return 'borrador'; }

        /*
         * "Pagada" exige ADEMAS estar presentada.
         *
         * dec_Pagado por si solo no alcanza: una declaracion marcada como
         * pagada pero sin presentar es un estado imposible, y pintarla
         * "Pagada" mentia al usuario -le mostraba un tramite cerrado que
         * despues no podia corregir, porque Corregir exige dec_Estado = 2-.
         * Ese es justo el caso que reporto el cliente con las declaraciones
         * 217 y 218.
         *
         * El origen se cerro en class.recaudo.php (esos pagos ya no se
         * aplican, se reportan), pero la pantalla no debe volver a mostrar
         * como pagado algo que el resto del sistema no trata como pagado.
         */
        if (Number(d.dec_Pagado) === 1 && Number(d.dec_Estado) === 2) { return 'pagada'; }
        if (Number(d.dec_Estado) === 2)  { return 'presentada'; }
        if (Number(d.is_signed) === 1) {
            var faltaContador = Number(d.requiere_contador) === 1 && Number(d.is_signed_contador) !== 1;
            return faltaContador ? 'pendienteCont' : 'firmada';
        }
        return 'borrador';
    }

    function estado(d) {
        var clave = claveEstado(d);
        var info  = ESTADOS[clave];
        return { clave: clave, texto: info.texto, clase: info.clase, paso: info.paso };
    }

    /** Distintivo de estado. El color nunca va solo: lleva punto y texto. */
    function chipEstado(d) {
        var e = estado(d);
        return '<span class="chip-estado ' + e.clase + '">' + e.texto + '</span>';
    }

    /**
     * Resumen "Declaración No. X — Año gravable YYYY" + chip de estado, para
     * la barra unica de icaWebPresentar (antes solo decia "Declaración de
     * este contribuyente" sin identificar de cual declaracion se trataba).
     * Hay declaraciones antiguas con dec_NumeroDeclaracion en NULL: se cae a
     * dec_Id, igual que ya hace icaWebConsultar.js.
     */
    function resumenDeclaracion(d) {
        var numero = d.dec_NumeroDeclaracion || d.dec_Id;
        return '<span>Declaración No. ' + numero + ' &mdash; Año gravable ' + d.dec_AnioDeclaracion + '</span> ' + chipEstado(d);
    }

    /**
     * Barra de progreso del tramite. Responde "que hice, en que voy y que
     * me falta" sin que la persona tenga que preguntar.
     */
    var PASOS = ['Crear', 'Liquidar', 'Firmar', 'Presentar', 'Pagar'];

    function stepperHtml(d) {
        var actual = estado(d).paso;
        var html = '<div class="stepper-tramite" role="list" aria-label="Progreso de la declaración">';
        for (var i = 0; i < PASOS.length; i++) {
            var n = i + 1;
            var clase = n < actual ? 'done' : (n === actual ? 'now' : 'todo');
            var sr = n < actual ? 'completado' : (n === actual ? 'paso actual' : 'pendiente');
            html += '<div class="paso ' + clase + '" role="listitem">' +
                        '<span class="paso-n">' + n + '</span>' +
                        '<span class="paso-t">' + PASOS[i] + '</span>' +
                        '<span class="sr-only"> (' + sr + ')</span>' +
                    '</div>';
        }
        return html + '</div>';
    }

    /**
     * Fecha de SQL Server -> texto dd/mm/aaaa para mostrar.
     *
     * El driver sqlsrv no devuelve las fechas como cadena sino como objeto
     * ({date, timezone_type, timezone}), asi que concatenarlas directamente
     * imprimia "[object Object]" -es lo que salia en la columna Fecha Pago de
     * "Consultar Declaraciones" en cuanto una declaracion estaba pagada-.
     *
     * 1900-01-01 es el centinela de "nunca se lleno" de esta base y se trata
     * como vacio, igual que en el resto del sistema.
     */
    function fechaTexto(valor, siVacio) {
        var vacio = (siVacio === undefined) ? 'No aplica' : siVacio;
        if (!valor) { return vacio; }

        var texto = (typeof valor === 'string') ? valor : (valor.date || '');
        if (!texto) { return vacio; }

        var soloFecha = texto.substring(0, 10);          // AAAA-MM-DD
        if (soloFecha === '1900-01-01') { return vacio; }

        var p = soloFecha.split('-');
        return (p.length === 3) ? (p[2] + '/' + p[1] + '/' + p[0]) : soloFecha;
    }

    return {
        nombreMes: nombreMes,
        htmlAcciones: htmlAcciones,
        estado: estado,
        chipEstado: chipEstado,
        resumenDeclaracion: resumenDeclaracion,
        stepperHtml: stepperHtml,
        fechaTexto: fechaTexto
    };

})();


/**
 * Flujo de FIRMA DIGITAL (OTP por correo), compartido por
 * icaWebPresentar.js e icaWebConsultar.js.
 *
 * Antes cada pantalla tenia su propia copia del flujo y la experiencia
 * tenia varios huecos: no se decia a que correo llegaba el codigo, no
 * habia forma de reenviarlo, no se veia cuanto faltaba para que
 * venciera, el campo aceptaba letras, y si el codigo se validaba pero
 * la firma fallaba el OTP ya quedaba consumido sin explicarselo al
 * usuario.
 */
var FirmaOTP = (function () {

    var API = '../microservicios/firmas/api.php';
    var VIGENCIA_SEG = 600;   // el backend expira el codigo a los 10 min
    var REENVIO_SEG  = 60;    // espera minima antes de permitir reenviar

    var _timerVigencia = null;
    var _timerReenvio  = null;
    var _onFirmado     = null;
    var _idEstablecimiento = null;
    // Rol de quien esta firmando en este momento: 'declarante' o 'contador'.
    // Decide a que correo viaja el codigo y con que rol queda la firma.
    var _rol           = 'declarante';
    var _modo = 'declaracion';   // 'declaracion' | 'rit'

    function _mostrarError(msg) {
        $('#otpError').text(msg).show();
    }

    function _limpiarError() {
        $('#otpError').hide().text('');
    }

    function _mmss(seg) {
        var m = Math.floor(seg / 60);
        var s = seg % 60;
        return m + ':' + (s < 10 ? '0' + s : s);
    }

    function _pararTimers() {
        if (_timerVigencia) { clearInterval(_timerVigencia); _timerVigencia = null; }
        if (_timerReenvio)  { clearInterval(_timerReenvio);  _timerReenvio  = null; }
    }

    function _arrancarVigencia() {
        var restante = VIGENCIA_SEG;
        $('#otpVigencia').css('color', '#6B7280').text('El código vence en ' + _mmss(restante));

        if (_timerVigencia) { clearInterval(_timerVigencia); }
        _timerVigencia = setInterval(function () {
            restante--;
            if (restante <= 0) {
                clearInterval(_timerVigencia);
                _timerVigencia = null;
                $('#otpVigencia').css('color', '#DC2626').text('El código venció. Solicita uno nuevo.');
                $('#btnValidarOTP').prop('disabled', true);
                return;
            }
            $('#otpVigencia')
                .css('color', restante <= 60 ? '#DC2626' : '#6B7280')
                .text('El código vence en ' + _mmss(restante));
        }, 1000);
    }

    function _arrancarCooldownReenvio() {
        var restante = REENVIO_SEG;
        var $btn = $('#btnReenviarOTP');
        $btn.prop('disabled', true).html('<i class="fa fa-refresh"></i> Reenviar código (' + restante + 's)');

        if (_timerReenvio) { clearInterval(_timerReenvio); }
        _timerReenvio = setInterval(function () {
            restante--;
            if (restante <= 0) {
                clearInterval(_timerReenvio);
                _timerReenvio = null;
                $btn.prop('disabled', false).html('<i class="fa fa-refresh"></i> Reenviar código');
                return;
            }
            $btn.html('<i class="fa fa-refresh"></i> Reenviar código (' + restante + 's)');
        }, 1000);
    }

    /** Pide un codigo nuevo al backend. */
    function _solicitarCodigo(esReenvio) {

        if (!esReenvio) {
            swal({
                title: 'Generando código',
                text: 'Por favor espere...',
                allowOutsideClick: false,
                onOpen: function () { swal.showLoading(); }
            });
        } else {
            $('#btnReenviarOTP').prop('disabled', true);
        }

        $.ajax({
            url: API,
            type: 'POST',
            dataType: 'json',
            data: {
                funcion: 1,
                id_usuario: ID_USUARIO,
                id_establecimiento: 0,
                // El rol decide a que correo viaja el codigo: al del usuario
                // (declarante) o al del contador/revisor del contribuyente.
                rol: _rol,
                numero_declaracion: $('#otpIdDeclaracion').val()
            },
            success: function (resp) {

                if (resp.ok != 1) {
                    if (!esReenvio) { swal('Error', resp.mensaje, 'error'); }
                    else { _mostrarError(resp.mensaje || 'No se pudo reenviar el código.'); }
                    $('#btnReenviarOTP').prop('disabled', false);
                    return;
                }

                // El backend responde "Código enviado a <correo>": se extrae
                // el correo para que el usuario sepa donde buscarlo.
                var correo = (resp.mensaje || '').replace(/^.*?enviado a\s*/i, '').trim();
                if (correo) { $('#otpDestino').text(correo); }

                $('#otpCodigo').val('');
                _limpiarError();
                $('#btnValidarOTP').prop('disabled', false);
                _arrancarVigencia();
                _arrancarCooldownReenvio();

                if (!esReenvio) {
                    swal.close();
                    $('#modal-FirmaDigital').modal('show');
                    setTimeout(function () { $('#otpCodigo').focus(); }, 400);
                }
            }
        });
    }

    /**
     * Abre el modal de firma para una declaracion.
     * @param {number}   decId      Declaracion a firmar.
     * @param {number}   idEst      Establecimiento (para refrescar al terminar).
     * @param {function} onFirmado  Callback tras firmar con exito.
     * @param {string}   [rol]      'declarante' (por defecto) o 'contador'.
     */
    function abrir(decId, idEst, onFirmado, rol) {
        _onFirmado = onFirmado || null;
        _idEstablecimiento = idEst || null;
        _modo = 'declaracion';
        _rol = rol === 'contador' ? 'contador' : 'declarante';

        $('#otpIdDeclaracion').val(decId);
        $('#otpDestino').text(_rol === 'contador'
            ? 'el correo del contador / revisor fiscal'
            : 'su correo electrónico');
        _limpiarError();
        _solicitarCodigo(false);
    }

    /**
     * Abre el MISMO modal para firmar el RIT.
     *
     * El cliente pidio expresamente que la firma del RIT se vea igual que la
     * de las declaraciones, no en una ventana distinta. Cambia poco: el
     * codigo se pide con rol 'rit' -que tiene su propio cajon en
     * codigos_verificacion, para que un codigo de declaracion no sirva para
     * firmar el RIT- y se registra con la funcion 9 en vez de la 7.
     *
     * @param {function} onFirmado  Callback tras firmar con exito.
     */
    function abrirRit(onFirmado) {
        _onFirmado = onFirmado || null;
        _idEstablecimiento = null;
        _modo = 'rit';
        _rol = 'rit';

        $('#otpIdDeclaracion').val('');
        $('#otpDestino').text('su correo electrónico');
        _limpiarError();
        _solicitarCodigo(false);
    }

    function _validarYFirmar() {

        var codigo = ($('#otpCodigo').val() || '').trim();
        var decId  = $('#otpIdDeclaracion').val();

        if (!/^\d{6}$/.test(codigo)) {
            _mostrarError('Ingresa los 6 dígitos del código.');
            $('#otpCodigo').focus();
            return;
        }

        _limpiarError();
        $('#btnValidarOTP').prop('disabled', true);

        swal({
            title: 'Validando firma',
            text: 'Por favor espere...',
            allowOutsideClick: false,
            onOpen: function () { swal.showLoading(); }
        });

        /*
         * UNA sola llamada.
         *
         * Antes esto eran dos: la funcion 2 verificaba el codigo y la 7
         * registraba la firma. La 7 no volvia a mirar el codigo, asi que
         * llamarla directamente dejaba una firma registrada sin haber
         * recibido ningun correo. Desde el 2026-08-19 la 7 valida y consume
         * el codigo ella misma, de modo que aqui solo hay que mandarselo.
         *
         * Ya no se manda id_usuario: el firmante lo toma el servidor de la
         * sesion, porque un id en el POST no prueba quien es quien.
         */
        $.ajax({
            url: API,
            type: 'POST',
            dataType: 'json',
            // funcion 9 = firmar el RIT, funcion 7 = firmar una declaracion.
            data: (_modo === 'rit')
                ? { funcion: 9, codigo: codigo }
                : { funcion: 7, codigo: codigo, id_declaracion: decId, rol: _rol },
            success: function (respFirma) {

                swal.close();

                if (respFirma.ok != 1) {
                    $('#btnValidarOTP').prop('disabled', false);
                    _mostrarError(respFirma.mensaje || 'Código inválido o expirado.');
                    $('#otpCodigo').val('').focus();
                    return;
                }

                _pararTimers();
                $('#modal-FirmaDigital').modal('hide');

                var titulo = _modo === 'rit' ? 'RIT firmado' : 'Firmada';
                var texto  = _modo === 'rit'
                    ? 'El RIT ha sido firmado digitalmente.'
                    : 'La declaración ha sido firmada digitalmente.';

                swal(titulo, texto, 'success').then(function () {
                    if (typeof _onFirmado === 'function') {
                        _onFirmado(_idEstablecimiento);
                    }
                });
            },
            error: function () {
                swal.close();
                $('#btnValidarOTP').prop('disabled', false);
                _mostrarError('No se pudo conectar para firmar. Intenta de nuevo.');
            }
        });
    }

    if (typeof $ !== 'undefined') {
        $(function () {
            // Solo digitos en el campo del codigo.
            $(document).on('input', '#otpCodigo', function () {
                var limpio = this.value.replace(/\D/g, '').slice(0, 6);
                if (this.value !== limpio) { this.value = limpio; }
                if (limpio.length === 6) { _limpiarError(); }
            });

            // Enter dentro del campo = validar.
            $(document).on('keypress', '#otpCodigo', function (e) {
                if (e.which === 13) { e.preventDefault(); _validarYFirmar(); }
            });

            $(document).on('click', '#btnValidarOTP', _validarYFirmar);

            $(document).on('click', '#btnReenviarOTP', function () {
                _solicitarCodigo(true);
            });

            // Al cerrar el modal se detienen los contadores.
            $(document).on('hidden.bs.modal', '#modal-FirmaDigital', function () {
                _pararTimers();
            });
        });
    }

    return { abrir: abrir, abrirRit: abrirRit };

})();

/**
 * Abre el formulario de liquidacion (el mismo modal #modal-CrearDeclaracion
 * que usa "Crear Declaración") pero PRE-CARGADO con una declaracion ya
 * existente, para editarla.
 *
 * Antes "Editar" (el lapiz sobre una declaracion en borrador) era un stub
 * que solo mostraba "disponible próximamente" -en Presentar Declaración Y
 * en Consultar Declaraciones, las dos pantallas tenian exactamente el
 * mismo aviso-. No existia ninguna forma de modificar una declaracion ya
 * creada. Vive en este modulo compartido (no en cada pantalla por
 * separado) porque ambas paginas tienen el mismo modal y los mismos ids
 * de campo.
 */
var EditarDeclaracion = (function () {

    function abrir(decId) {

        $.ajax({
            url: '../business/controller/class.declaracionesICA.php',
            type: 'POST',
            dataType: 'json',
            data: { funcion: 13, dec_Id: decId },
            success: function (resp) {

                if (resp.ok != 1) {
                    swal({
                        type: 'error',
                        title: 'No se pudo abrir para editar',
                        text: resp.mensaje || 'Ocurrió un error al cargar la declaración.'
                    });
                    return;
                }

                var d = resp.datos.declaracion;
                var actividades = resp.datos.actividades;

                $('#numDeclaracion').val(d.dec_Id);
                $('#anioDeclaracion').val(d.dec_AnioDeclaracion);
                $('#periodoDeclaracion').val(d.dec_MesDeclaracion);
                $('#opcionUso').val(d.dec_OpcionUso);

                // Los totales se repueblan desde las columnas dec_* (mismo
                // mapeo, a la inversa, que usa el guardado en
                // "Finalizar Declaración"); ingresos_municipio e
                // ingresos_gravables son de solo lectura y se recalculan.
                //
                // OJO: hay que formatearlos a formato colombiano ANTES de
                // meterlos al input. SQL Server devuelve los decimales como
                // texto con PUNTO decimal ("2500000.00"), pero estos campos se
                // leen despues con numero()/limpiarNumero(), que tratan el
                // punto como separador de MILES y por lo tanto lo eliminan:
                // "2500000.00" -> 250000000. El valor quedaba multiplicado por
                // 100 en cada pasada, y como la correccion copia y reabre la
                // declaracion, los ceros se iban acumulando (el bug de los
                // "00000" que reporto el cliente). Las actividades, mas abajo,
                // siempre hicieron bien este parseFloat + formatearCOP.
                // BD -> input: la conversion canonica vive en core/numeros.js.
                var aCOP = function (v) { return NumerosCOP.deBaseDeDatosAInput(v); };

                $('[data-campo="ingresos_total_pais"]').val(aCOP(d.dec_TotalIngresos));
                $('[data-campo="menos_fuera_municipio"]').val(aCOP(d.dec_IngresosFueraMunicipio));
                $('[data-campo="devoluciones"]').val(aCOP(d.dec_IngresosDevoluciones));
                $('[data-campo="exportaciones"]').val(aCOP(d.dec_IngresosExportaciones));
                $('[data-campo="venta_activos"]').val(aCOP(d.dec_IngresosVentas));
                $('[data-campo="actividades_excluidas"]').val(aCOP(d.dec_IngresosActividades));
                $('[data-campo="otras_exentas"]').val(aCOP(d.dec_IngresosOtrasActividades));

                if (typeof establecimientos !== 'undefined' && establecimientos.calcularIngresos) {
                    establecimientos.calcularIngresos();
                }

                // Actividades: se muestran con la base/tarifa/impuesto TAL
                // COMO quedaron guardadas la ultima vez, no la lista
                // agregada desde cero que usa "Crear Declaración".
                var $tbody = $('#tbodyActividades').empty();

                actividades.forEach(function (a) {
                    var base = parseFloat(a.dia_BaseGravable) || 0;
                    var impuesto = parseFloat(a.dia_ValorImpuesto) || 0;
                    var fmt = (typeof establecimientos !== 'undefined' && establecimientos.formatearCOP)
                        ? establecimientos.formatearCOP
                        : function (n) { return n; };

                    $tbody.append(
                        '<tr>' +
                            '<td>' + a.acc_Codigo + ' - ' + a.acc_Nombre +
                                '<input type="hidden" class="actividad-id" value="' + a.dia_IdActividad + '"></td>' +
                            '<td><input type="text" class="form-control base-gravable" value="' + fmt(base) + '"></td>' +
                            '<td><input type="text" class="form-control tarifa" value="' + a.dia_Tarifa + '" readonly></td>' +
                            '<td><input type="text" class="form-control impuesto" readonly value="' + fmt(impuesto) + '"></td>' +
                        '</tr>'
                    );
                });

                if (typeof establecimientos !== 'undefined' && establecimientos.calcularTotalesActividades) {
                    establecimientos.calcularTotalesActividades();
                }

                $('#btnGenerarOficial').prop('disabled', false);
                $('#btnDescargarPDF')
                    .prop('disabled', false)
                    .attr('onclick', "window.open('../extensiones/declaracion.php?dec_Id=" + d.dec_Id + "', '_blank')");

                $('#stepperDeclaracion').html(DeclaracionesUI.stepperHtml(d));

                $('#modal-CrearDeclaracion').modal({ backdrop: 'static', keyboard: false });
            }
        });
    }

    return { abrir: abrir };

})();

/**
 * Red de seguridad global para peticiones AJAX que fallan (500, timeout,
 * red caida, etc.). Antes ningun $.ajax() de estas pantallas definia
 * `error:`, asi que una peticion fallida dejaba el spinner de carga
 * girando para siempre y el usuario sin ningun mensaje. Esto no
 * reemplaza el manejo de errores propio de cada pantalla (los `success`
 * que revisan resp.ok siguen igual); solo cubre el caso de que la
 * peticion ni siquiera haya podido completarse.
 */
// La bandera window.__erpRedAjax la comparte dist/menu.php, que instala esta
// misma red en TODAS las pantallas (este archivo solo lo cargan dos). Quien
// registre primero gana; el otro no duplica el aviso.
if (typeof $ !== 'undefined' && !window.__erpRedAjax) {
    window.__erpRedAjax = true;
    $(document).ajaxError(function (event, jqxhr, settings) {
        $('#loading').hide();
        $('#wrapper').removeClass('body-load');

        if (typeof swal === 'function') {
            swal({
                type: 'error',
                title: 'Error de conexión',
                text: 'No se pudo completar la solicitud. Intenta de nuevo; si persiste, avisa a soporte.'
            });
        }
        if (window.console && console.error) {
            console.error('AJAX fallido:', settings && settings.url, jqxhr && jqxhr.status, jqxhr && jqxhr.responseText);
        }
    });
}
