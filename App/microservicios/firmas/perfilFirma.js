'use strict';

/**
 * Microservicio Firmas — Firma personal del usuario (perfil)
 *
 * Flujo:
 *   Paso 1 — Verificación OTP (id_establecimiento = -1 para distinguir
 *             de firma de establecimiento > 0 y de declaraciones = 0)
 *   Paso 2 — Dibujar y guardar la firma en canvas
 */
class PerfilFirma {

    constructor() {
        this._sigPad         = null;
        this._idUsuario      = null;
        this._firmaExistente = null;
    }

    /**
     * Abre el modal de firma personal.
     * @param {number|string} idUsuario
     */
    async abrirModal(idUsuario) {
        this._idUsuario      = idUsuario || localStorage.getItem('id_Usuario');
        this._firmaExistente = null;

        // Reset UI — volver a paso 1
        this._destruirCanvas();
        $('#fu-paso-1').show();
        $('#fu-paso-2').hide();
        $('#fu-otp-enviar').show();
        $('#fu-otp-verificar').hide();
        $('#fu-otp-input').val('');
        $('#fu-btn-guardar').hide().prop('disabled', true);
        $('#fu-info-existente').hide();
        $('#fu-preview-wrap').hide();

        $('#modal-FirmaUsuario').modal({ backdrop: 'static', keyboard: false });
        $('#modal-FirmaUsuario').modal('show');

        // Consultar si ya tiene firma guardada (para mostrarla como referencia en paso 2)
        const resp = await this._post({ funcion: 6, id_usuario: this._idUsuario });
        if (resp.ok === 1) {
            this._firmaExistente = resp.datos;
        }
    }

    // ── Paso 1: Enviar OTP ────────────────────────────────────────────────

    async enviarCodigo() {
        $('#fu-btn-enviar').prop('disabled', true)
            .html('<i class="fa fa-spinner fa-spin"></i> Enviando...');

        const resp = await this._post({
            funcion:            1,
            id_usuario:         this._idUsuario,
            id_establecimiento: -1
        });

        $('#fu-btn-enviar').prop('disabled', false)
            .html('<i class="fa fa-paper-plane-o"></i> Enviar código');

        if (resp.ok === 1) {
            $('#fu-otp-enviar').hide();
            $('#fu-otp-verificar').show();
            $('#fu-otp-input').focus();
        } else {
            swal({ type: 'error', title: 'Error', text: resp.mensaje });
        }
    }

    // ── Paso 1: Verificar OTP → pasar a paso 2 ───────────────────────────

    async verificarCodigo() {
        const codigo = $('#fu-otp-input').val().trim();
        if (codigo.length !== 6) {
            swal({ type: 'warning', title: 'Atención', text: 'Ingresa el código de 6 dígitos.' });
            return;
        }

        $('#fu-btn-verificar').prop('disabled', true)
            .html('<i class="fa fa-spinner fa-spin"></i> Verificando...');

        const resp = await this._post({
            funcion:            2,
            codigo:             codigo,
            id_usuario:         this._idUsuario,
            id_establecimiento: -1
        });

        $('#fu-btn-verificar').prop('disabled', false)
            .html('<i class="fa fa-check"></i> Verificar');

        if (resp.ok === 1) {
            // Transición al paso 2
            $('#fu-paso-1').hide();
            $('#fu-paso-2').show();
            $('#fu-btn-guardar').show();

            if (this._firmaExistente) {
                $('#fu-info-existente')
                    .html(`<i class="fa fa-check-circle text-success"></i>
                           Ya tienes una firma registrada (${this._firmaExistente.fu_FechaHora}).
                           Dibuja una nueva para reemplazarla.`)
                    .show();
                $('#fu-preview-img').attr('src', this._firmaExistente.fu_Base64);
                $('#fu-preview-fecha').text('Guardada el ' + this._firmaExistente.fu_FechaHora);
                $('#fu-preview-wrap').show();
            }

            // Delay para que el DOM esté visible antes de medir el canvas
            setTimeout(() => this._inicializarCanvas(), 300);
        } else {
            swal({ type: 'error', title: 'Código incorrecto', text: resp.mensaje });
        }
    }

    // ── Paso 2: Canvas ────────────────────────────────────────────────────

    _inicializarCanvas() {
        const canvas = document.getElementById('fu-canvas');
        if (!canvas) return;

        const rect = canvas.getBoundingClientRect();
        canvas.width  = rect.width  * (window.devicePixelRatio || 1);
        canvas.height = rect.height * (window.devicePixelRatio || 1);

        this._sigPad = new SignaturePad(canvas, {
            backgroundColor: 'rgba(255,255,255,0)',
            penColor:        'rgb(0,0,128)',   // tinta azul oscuro
            minWidth:        1,
            maxWidth:        3
        });

        this._sigPad.addEventListener('endStroke', () => {
            $('#fu-btn-guardar').prop('disabled', this._sigPad.isEmpty());
        });
    }

    _destruirCanvas() {
        if (this._sigPad) {
            this._sigPad.off();
            this._sigPad = null;
        }
    }

    limpiarCanvas() {
        if (this._sigPad) {
            this._sigPad.clear();
            $('#fu-btn-guardar').prop('disabled', true);
        }
    }

    // ── Guardar firma ─────────────────────────────────────────────────────

    async guardar() {
        if (!this._sigPad || this._sigPad.isEmpty()) {
            swal({ type: 'warning', title: 'Atención', text: 'Dibuja tu firma antes de guardar.' });
            return;
        }

        const base64 = this._sigPad.toDataURL('image/png');

        $('#fu-btn-guardar').prop('disabled', true)
            .html('<i class="fa fa-spinner fa-spin"></i> Guardando...');

        const resp = await this._post({
            funcion:      5,
            id_usuario:   this._idUsuario,
            firma_base64: base64
        });

        $('#fu-btn-guardar').prop('disabled', false)
            .html('<i class="fa fa-save"></i> Guardar firma');

        if (resp.ok === 1) {
            this._destruirCanvas();
            $('#modal-FirmaUsuario').modal('hide');
            swal({ type: 'success', title: '¡Firma guardada!', text: resp.mensaje });
        } else {
            swal({ type: 'error', title: 'Error', text: resp.mensaje });
        }
    }

    _post(data) {
        return $.ajax({
            url:      '../microservicios/firmas/api.php',
            type:     'POST',
            dataType: 'json',
            data:     data
        }).then(r => r).catch(() => ({ ok: 0, mensaje: 'Error de conexión con el servidor.' }));
    }
}

const perfilFirma = new PerfilFirma();
