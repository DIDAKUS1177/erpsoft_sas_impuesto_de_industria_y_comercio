'use strict';

/**
 * Microservicio Firmas — Firma de Declaraciones
 *
 * Flujo: OTP paso 1 → OTP paso 2 → verifica OTP + marca declaración como firmada
 */
class FirmaDeclaracion {

    constructor() {
        this._numeroDeclaracion = null;
        this._onFirmadoCb = null;   // callback que llama el módulo que abre el modal
        this._timerInterval = null;
    }

    /**
     * Abre el modal para firmar una declaración específica.
     * @param {string}   numero    número de declaración
     * @param {Function} onFirmado callback(numero) ejecutado cuando la firma es exitosa
     */
    abrirModal(numero, onFirmado = null) {
        this._numeroDeclaracion = numero;
        this._onFirmadoCb = onFirmado;

        // Reset UI
        this._irPaso(1);
        $('#decl-codigo-input').val('');
        $('#decl-numero-display').text(numero);
        $('#decl-ok-msg').text('');

        $('#modal-FirmaDeclaracion').modal({ backdrop: 'static', keyboard: false });
        $('#modal-FirmaDeclaracion').modal('show');
    }

    // ── PASO 1: Enviar OTP ───────────────────────────────────────────────────

    async enviarCodigo() {
        const idUsuario = localStorage.getItem('id_Usuario');
        if (!idUsuario) {
            swal({ type: 'error', title: 'Error', text: 'Sesión no encontrada. Inicia sesión nuevamente.' });
            return;
        }

        $('#decl-btn-enviar').prop('disabled', true)
            .html('<i class="fa fa-spinner fa-spin"></i> Enviando...');

        // id_establecimiento = 0 → identifica que el OTP es para declaraciones
        const resp = await this._post({
            funcion: 1,
            id_usuario: idUsuario,
            id_establecimiento: 0
        });

        $('#decl-btn-enviar').prop('disabled', false)
            .html('<i class="fa fa-envelope"></i> Enviar código al correo');

        if (resp.ok === 1) {
            swal({ type: 'success', title: 'Código enviado', text: resp.mensaje });
            this._irPaso(2);
            this._iniciarTimer();
        } else {
            swal({ type: 'error', title: 'Error', text: resp.mensaje });
        }
    }

    // ── PASO 2: Verificar OTP y firmar declaración ───────────────────────────

    async verificarYFirmar() {
        const codigo = $('#decl-codigo-input').val().trim();
        const idUsuario = localStorage.getItem('id_Usuario');

        if (codigo.length !== 6) {
            swal({ type: 'warning', title: 'Atención', text: 'Ingresa el código de 6 dígitos.' });
            return;
        }

        $('#decl-btn-verificar').prop('disabled', true)
            .html('<i class="fa fa-spinner fa-spin"></i> Verificando...');

        // Un solo paso: el codigo viaja CON la firma.
        //
        // Antes esto eran dos llamadas -la funcion 2 verificaba el codigo y la
        // 7 registraba la firma- y la 7 no volvia a mirarlo. Cualquiera que
        // llamara la 7 directamente registraba una firma sin haber recibido
        // ningun correo. Ahora la 7 valida y consume el codigo ella misma, asi
        // que no queda ningun hueco entre comprobar y firmar.
        const respFirma = await this._post({
            funcion: 7,
            codigo: codigo,
            numero_declaracion: this._numeroDeclaracion
        });

        $('#decl-btn-verificar').prop('disabled', false)
            .html('<i class="fa fa-check"></i> Verificar y Firmar');

        if (respFirma.ok === 1) {
            $('#decl-ok-msg').text(
                `Firmada por ${localStorage.getItem('nombre_Usuario') || 'el funcionario'} el ${new Date().toLocaleString('es-CO')}`
            );
            this._irPaso('ok');

            // Notificar al módulo que lo invocó
            if (typeof this._onFirmadoCb === 'function') {
                this._onFirmadoCb(this._numeroDeclaracion);
            }
        } else {
            swal({ type: 'error', title: 'Error', text: respFirma.mensaje });
        }
    }

    /**
     * Refirma una declaración utilizando la firma guardada del usuario.
     *
     * OJO: antes esto se saltaba el OTP a proposito ("si ya tiene firma
     * guardada, no hace falta el codigo"). Eso convertia la refirma en una
     * puerta de atras: bastaba llamarla para estampar una firma sin ningun
     * correo de por medio. Desde el 2026-08-19 pide codigo como cualquier
     * otra firma.
     */
    async refirmar(numero, onFirmado = null) {
        const idUsuario = localStorage.getItem('id_Usuario');
        if (!idUsuario) {
            swal({ type: 'error', title: 'Error', text: 'Sesión no encontrada.' });
            return;
        }

        // 1. Verificar si tiene firma guardada
        const respFirmaUsr = await this._post({ funcion: 6, id_usuario: idUsuario });
        if (respFirmaUsr.ok !== 1) {
            swal({
                type: 'warning',
                title: 'No tienes firma guardada',
                text: 'Para refirmar automáticamente, primero debes configurar tu firma en tu perfil de usuario.'
            });
            return;
        }

        // 2. Confirmación
        const confirm = await swal({
            title: '¿Refirmar declaración?',
            text: `Se actualizará la firma de la declaración N° ${numero} usando tu firma guardada.`,
            type: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, refirmar',
            cancelButtonText: 'Cancelar'
        });

        if (!confirm.value) return;

        // 3. Codigo al correo, tambien para refirmar.
        //
        // Refirmar es volver a firmar: si la firma original exige el codigo,
        // esta no puede saltarselo -seria la puerta de atras que acabamos de
        // cerrar-. El servidor lo exige de todas formas (funcion 7), esto solo
        // evita mandar una peticion que va a rebotar.
        swal({ title: 'Enviando código…', allowOutsideClick: false, onOpen: () => { swal.showLoading(); } });

        const respEnvio = await this._post({
            funcion: 1,
            id_usuario: idUsuario,
            id_establecimiento: 0
        });

        swal.close();

        if (respEnvio.ok !== 1) {
            swal({ type: 'error', title: 'No se pudo enviar el código', text: respEnvio.mensaje || '' });
            return;
        }

        const pedido = await swal({
            title: 'Código de verificación',
            text: respEnvio.mensaje || 'Escriba el código de 6 dígitos que le llegó al correo.',
            input: 'text',
            inputAttributes: { maxlength: 6, autocapitalize: 'off', autocorrect: 'off' },
            showCancelButton: true,
            confirmButtonText: 'Refirmar',
            cancelButtonText: 'Cancelar',
            inputValidator: (v) => (!v || !/^[0-9]{6}$/.test(v.trim())) ? 'El código son 6 dígitos' : null
        });

        if (!pedido.value) return;

        // 4. Ejecutar refirma en el backend
        swal({ title: 'Refirmando...', allowOutsideClick: false, onOpen: () => { swal.showLoading(); } });

        const resp = await this._post({
            funcion: 7,
            codigo: pedido.value.trim(),
            numero_declaracion: numero,
            refirma: 1  // 👈 Flag para permitir update
        });

        swal.close();

        if (resp.ok === 1) {
            swal({ type: 'success', title: '¡Refirmada!', text: resp.mensaje });
            if (typeof onFirmado === 'function') {
                onFirmado(numero);
            }
        } else {
            swal({ type: 'error', title: 'Error', text: resp.mensaje });
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    _irPaso(id) {
        if (id !== 2) this._detenerTimer();
        $('.decl-paso').hide();
        $(`#decl-paso-${id}`).show();
    }

    _iniciarTimer() {
        this._detenerTimer();
        let segundos = 600;
        const $wrap = $('#decl-timer-wrap');
        const $display = $('#decl-timer-display');
        const $badge = $('#decl-timer-badge');

        $badge.removeClass('badge-danger').addClass('badge-warning');
        $wrap.show();

        const tick = () => {
            const m = String(Math.floor(segundos / 60)).padStart(2, '0');
            const s = String(segundos % 60).padStart(2, '0');
            $display.text(`${m}:${s}`);
            if (segundos <= 120) {
                $badge.removeClass('badge-warning').addClass('badge-danger');
            }
        };

        tick();
        this._timerInterval = setInterval(() => {
            segundos--;
            tick();
            if (segundos <= 0) {
                this._detenerTimer();
                swal({ type: 'warning', title: 'Código expirado', text: 'El código expiró. Solicita uno nuevo.' });
                this._irPaso(1);
            }
        }, 1000);
    }

    _detenerTimer() {
        if (this._timerInterval) {
            clearInterval(this._timerInterval);
            this._timerInterval = null;
        }
        $('#decl-timer-wrap').hide();
        $('#decl-timer-display').text('10:00');
        $('#decl-timer-badge').removeClass('badge-danger').addClass('badge-warning');
    }

    _post(data) {
        return $.ajax({
            url: '../microservicios/firmas/api.php',
            type: 'POST',
            dataType: 'json',
            data: data
        }).then(r => r).catch(() => ({ ok: 0, mensaje: 'Error de conexión con el servidor.' }));
    }
}

const firmaDeclaracion = new FirmaDeclaracion();
