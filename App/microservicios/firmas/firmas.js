'use strict';

class Firmas {

    constructor() {
        this._idEstablecimiento = null;
        this._codigoVerificado  = false;
        this._sigPad            = null;   // instancia de SignaturePad
        this._timerInterval     = null;
    }

    /**
     * Abre el modal de firma para un establecimiento dado.
     */
    async abrirModal(idEstablecimiento) {
        this._idEstablecimiento = idEstablecimiento;
        this._codigoVerificado  = false;

        this._irPaso(1);
        $('#firma-codigo-input').val('');
        $('#firma-info-existente').hide();
        $('#firma-btn-guardar').prop('disabled', true);
        this._destruirCanvas();

        const resp = await this._post({ funcion: 4, id_establecimiento: idEstablecimiento });
        if (resp.ok === 1) {
            const d = resp.datos;
            $('#firma-info-existente')
                .html(`<i class="fa fa-check-circle text-success"></i>
                       Ya existe una firma de <strong>${d.firma_NombreUsuario}</strong>
                       registrada el ${d.firma_FechaHora}.
                       Puedes reemplazarla siguiendo los pasos.`)
                .show();
        }

        $('#modal-Firma').modal({ backdrop: 'static', keyboard: false });
        $('#modal-Firma').modal('show');
    }

    // ── PASO 1: Enviar código ────────────────────────────────────────────────

    async enviarCodigo() {
        const idUsuario = localStorage.getItem('id_Usuario');
        if (!idUsuario) {
            swal({ type: 'error', title: 'Error', text: 'Sesión no encontrada. Inicia sesión nuevamente.' });
            return;
        }

        $('#firma-btn-enviar').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Enviando...');

        const resp = await this._post({
            funcion:            1,
            id_usuario:         idUsuario,
            id_establecimiento: this._idEstablecimiento
        });

        $('#firma-btn-enviar').prop('disabled', false).html('<i class="fa fa-envelope"></i> Enviar código');

        if (resp.ok === 1) {
            swal({ type: 'success', title: 'Código enviado', text: resp.mensaje });
            this._irPaso(2);
            this._iniciarTimer();
        } else {
            swal({ type: 'error', title: 'Error', text: resp.mensaje });
        }
    }

    // ── PASO 2: Verificar código ─────────────────────────────────────────────

    async verificarCodigo() {
        const codigo    = $('#firma-codigo-input').val().trim();
        const idUsuario = localStorage.getItem('id_Usuario');

        if (codigo.length !== 6) {
            swal({ type: 'warning', title: 'Atención', text: 'Ingresa el código de 6 dígitos.' });
            return;
        }

        $('#firma-btn-verificar').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Verificando...');

        const resp = await this._post({
            funcion:            2,
            codigo:             codigo,
            id_usuario:         idUsuario,
            id_establecimiento: this._idEstablecimiento
        });

        $('#firma-btn-verificar').prop('disabled', false).html('<i class="fa fa-check"></i> Verificar código');

        if (resp.ok === 1) {
            this._codigoVerificado = true;
            swal({ type: 'success', title: 'Verificado', text: resp.mensaje });
            this._irPaso(3);
            this._inicializarCanvas();
        } else {
            swal({ type: 'error', title: 'Código incorrecto', text: resp.mensaje });
        }
    }

    // ── PASO 3: Canvas de firma ──────────────────────────────────────────────

    _inicializarCanvas() {
        const canvas = document.getElementById('firma-canvas');
        if (!canvas) return;

        // Ajustar resolución real del canvas al tamaño CSS
        const rect = canvas.getBoundingClientRect();
        canvas.width  = rect.width  * (window.devicePixelRatio || 1);
        canvas.height = rect.height * (window.devicePixelRatio || 1);

        this._sigPad = new SignaturePad(canvas, {
            backgroundColor: 'rgba(255,255,255,0)',
            penColor:        'rgb(0,0,0)',
            minWidth:        1,
            maxWidth:        3
        });

        this._sigPad.addEventListener('endStroke', () => {
            $('#firma-btn-guardar').prop('disabled', this._sigPad.isEmpty());
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
            $('#firma-btn-guardar').prop('disabled', true);
        }
    }

    // ── Guardar firma ────────────────────────────────────────────────────────

    async guardarFirma() {
        if (!this._codigoVerificado) {
            swal({ type: 'warning', title: 'Atención', text: 'Debes verificar el código primero.' });
            return;
        }
        if (!this._sigPad || this._sigPad.isEmpty()) {
            swal({ type: 'warning', title: 'Atención', text: 'Dibuja tu firma antes de guardar.' });
            return;
        }

        const base64    = this._sigPad.toDataURL('image/png');
        const idUsuario = localStorage.getItem('id_Usuario');

        $('#firma-btn-guardar').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Guardando...');

        const resp = await this._post({
            funcion:            3,
            id_usuario:         idUsuario,
            id_establecimiento: this._idEstablecimiento,
            firma_base64:       base64
        });

        $('#firma-btn-guardar').prop('disabled', false).html('<i class="fa fa-save"></i> Guardar firma');

        if (resp.ok === 1) {
            this._destruirCanvas();
            $('#modal-Firma').modal('hide');
            swal({ type: 'success', title: '¡Firma guardada!', text: 'La firma quedó registrada en el sistema.' });
        } else {
            swal({ type: 'error', title: 'Error', text: resp.mensaje });
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    _irPaso(numero) {
        if (numero !== 2) this._detenerTimer();
        $('.firma-paso').hide();
        $(`#firma-paso-${numero}`).show();
    }

    _iniciarTimer() {
        this._detenerTimer();
        let segundos = 600;
        const $wrap    = $('#firma-timer-wrap');
        const $display = $('#firma-timer-display');
        const $badge   = $('#firma-timer-badge');

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
        $('#firma-timer-wrap').hide();
        $('#firma-timer-display').text('10:00');
        $('#firma-timer-badge').removeClass('badge-danger').addClass('badge-warning');
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

const firmas = new Firmas();
