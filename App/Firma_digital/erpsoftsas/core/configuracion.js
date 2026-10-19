/**
 * Configuración del municipio: parámetros y cuentas de los bancos.
 *
 * El permiso real vive en el servidor (class.configuracion.php, permiso
 * "Municipio y bancos"). Lo de aquí es solo la pantalla: quien llegue por la
 * URL sin ese permiso recibe un rechazo del servidor y las dos tablas se
 * quedan vacías con su mensaje, en vez de mostrar el EAN de recaudo a
 * cualquiera.
 *
 * La contraseña de edición protege solo los PARÁMETROS. Las cuentas de los
 * bancos (las del recibo de pago) se cambian sin ella: el cliente pidió que la
 * Alcaldía las maneje sin pedírselas a nadie (2026-09-30). Las cuentas
 * contables del pago manual (migración 051, 2026-10-19), tampoco.
 */
class Configuracion {

    constructor() {
        this._bancos = [];
        this._desbloqueada = false;   // lo decide el servidor (función 6)
        this._relojCandado = null;
    }

    /* ==================== CANDADO DE EDICIÓN ==================== */

    /** Pregunta al servidor si la edición está abierta para esta sesión. */
    consultarCandado() {
        const self = this;
        $.post('../business/controller/class.configuracion.php', { funcion: 6 }, function (resp) {
            if (resp && resp.ok == 1) { self.aplicarCandado(resp.datos); }
        }, 'json');
    }

    /** Pinta el candado y habilita o no las tablas según lo que dijo el servidor. */
    aplicarCandado(estado) {
        const self = this;
        const abierta = !!(estado && estado.desbloqueada);
        this._desbloqueada = abierta;
        clearTimeout(this._relojCandado);

        if (abierta) {
            const hora = new Date(Date.now() + estado.segundos * 1000)
                .toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' });
            $('#candadoTitulo').html('<i class="fa fa-unlock"></i> Edición de parámetros desbloqueada');
            // "12:56 p. m." ya trae su punto: no se le agrega otro.
            $('#candadoTexto').text('Puede guardar los parámetros. Se vuelve a bloquear sola a las '
                + hora + (hora.slice(-1) === '.' ? '' : '.'));
            $('#btnCandado').text('Bloquear ahora').removeClass('btn-primary').addClass('btn-outline-secondary');
            // Al vencer, la pantalla se bloquea a la par con el servidor.
            this._relojCandado = setTimeout(function () {
                self.aplicarCandado({ desbloqueada: false });
            }, estado.segundos * 1000);
        } else {
            $('#candadoTitulo').html('<i class="fa fa-lock"></i> Parámetros protegidos');
            $('#candadoTexto').text('Para cambiar los parámetros del municipio se pide la contraseña de edición. '
                + 'Las cuentas de los bancos y las contables se cambian sin ella.');
            $('#btnCandado').text('Desbloquear edición').removeClass('btn-outline-secondary').addClass('btn-primary');
        }
        this.bloquearTablas();
    }

    /** Campos y botones Guardar de los parámetros, según el candado (los bancos no lo llevan). */
    bloquearTablas() {
        $('#tbodyParametros').find('input, select, button').prop('disabled', !this._desbloqueada);
    }

    abrirClave(mensaje) {
        $('#claveEdicion').val('');
        $('#claveError').text(mensaje || '');
        $('#modal-Clave').modal('show');
    }

    desbloquear() {
        const self = this;
        $('#btnDesbloquear').prop('disabled', true);
        $.ajax({
            url: '../business/controller/class.configuracion.php',
            type: 'POST',
            dataType: 'json',
            data: { funcion: 5, clave: $('#claveEdicion').val() },
            success: function (resp) {
                $('#btnDesbloquear').prop('disabled', false);
                if (resp.ok == 1) {
                    $('#modal-Clave').modal('hide');
                    self.aplicarCandado(resp.datos);
                    return;
                }
                $('#claveError').text(resp.mensaje || 'No se pudo desbloquear.');
                $('#claveEdicion').val('').trigger('focus');
            },
            error: function () {
                $('#btnDesbloquear').prop('disabled', false);
                $('#claveError').text('No se pudo verificar. Revise la conexión e intente de nuevo.');
            }
        });
    }

    bloquear() {
        const self = this;
        $.post('../business/controller/class.configuracion.php', { funcion: 7 }, function (resp) {
            self.aplicarCandado(resp && resp.datos ? resp.datos : { desbloqueada: false });
        }, 'json');
    }

    /** Si al guardar el servidor dice que la edición ya se cerró, se pide la clave. */
    respuestaPideClave(resp) {
        if (resp && resp.datos && resp.datos.requiereClave) {
            this.aplicarCandado({ desbloqueada: false });
            this.abrirClave('La edición se volvió a bloquear. Escriba la contraseña para guardar.');
            return true;
        }
        return false;
    }

    /** Escapa lo que venga de la base antes de meterlo al HTML. */
    escapeHtml(texto) {
        return String(texto == null ? '' : texto)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    /* ==================== PARÁMETROS ==================== */

    cargarParametros() {
        const self = this;
        $.ajax({
            url: '../business/controller/class.configuracion.php',
            type: 'POST',
            dataType: 'json',
            data: { funcion: 1 },
            success: function (resp) {
                if (resp.ok != 1) {
                    $('#tbodyParametros').html(
                        '<tr><td colspan="5" class="text-center text-muted py-3">' +
                        self.escapeHtml(resp.mensaje || 'No se pudo cargar.') + '</td></tr>');
                    return;
                }

                let filas = '';
                (resp.datos || []).forEach(function (p) {
                    /*
                     * Un parametro sensible -hoy, la clave del convenio de
                     * recaudo- llega SIN valor: el servidor no lo manda nunca,
                     * solo dice si esta puesto. Asi que la casilla nace vacia y
                     * lo unico que se muestra es su estado.
                     *
                     * El tipo password no es por la ocultacion visual, que aqui
                     * sobra porque no hay nada escrito: es para que el gestor de
                     * contraseñas del navegador no la guarde ni la autocomplete
                     * como si fuera un campo cualquiera.
                     *
                     * Dejarla en blanco no borra la clave; el servidor lo trata
                     * como "no la estoy cambiando". El texto de ayuda lo dice,
                     * porque un campo vacio junto a un boton Guardar invita a
                     * pensar lo contrario.
                     */
                    const sensible = Number(p.par_Sensible) === 1;
                    const puesto   = Number(p.par_Puesto) === 1;

                    // Un parámetro con lista (el banco de la pasarela, 045) es un desplegable.
                    const opciones = Array.isArray(p.par_Opciones) ? p.par_Opciones : null;
                    const casilla = opciones
                        ? '<select class="form-control form-control-sm" id="par_' + Number(p.par_Id) + '">' +
                              '<option value="">Sin elegir</option>' +
                              opciones.map(function (o) {
                                  return '<option value="' + self.escapeHtml(o.valor) + '"' +
                                         (String(o.valor) === String(p.par_Valor || '') ? ' selected' : '') + '>' +
                                         self.escapeHtml(o.texto) + '</option>';
                              }).join('') +
                          '</select>'
                        : sensible
                        ? '<input type="password" class="form-control form-control-sm" ' +
                              'id="par_' + Number(p.par_Id) + '" value="" autocomplete="new-password" ' +
                              'placeholder="' + (puesto ? 'Escriba una nueva para cambiarla'
                                                        : 'Sin configurar') + '">' +
                          '<small class="' + (puesto ? 'text-success' : 'text-muted') + '">' +
                              (puesto ? '<i class="fa fa-check"></i> Configurada' +
                                        // Llaves de Wompi: de pruebas o de producción (no deben mezclarse).
                                        (p.par_Ambiente ? ' (' + self.escapeHtml(p.par_Ambiente) + ')' : '') +
                                        ' · dejarla en blanco no la borra'
                                      : 'Sin configurar') + '</small>'
                        : '<input type="text" class="form-control form-control-sm" ' +
                              'id="par_' + Number(p.par_Id) + '" ' +
                              'value="' + self.escapeHtml(p.par_Valor) + '">';

                    filas +=
                        '<tr>' +
                        '<td><b>' + self.escapeHtml(p.par_Nombre || p.par_Clave) + '</b><br>' +
                            '<small class="text-muted">' + self.escapeHtml(p.par_Clave) + '</small></td>' +
                        '<td>' + casilla + '</td>' +
                        '<td><small>' + self.escapeHtml(p.par_Descripcion) + '</small></td>' +
                        '<td><small>' + self.escapeHtml(p.par_FechaActualizacion) + '</small></td>' +
                        '<td><button type="button" class="btn btn-sm btn-primary" ' +
                            'onclick="configuracion.guardarParametro(' + Number(p.par_Id) + ')">Guardar</button></td>' +
                        '</tr>';
                });

                $('#tbodyParametros').html(filas ||
                    '<tr><td colspan="5" class="text-center text-muted py-3">No hay parámetros.</td></tr>');
                self.bloquearTablas();
            },
            error: function (xhr) {
                console.log('Error al cargar parámetros:', xhr.responseText);
                $('#tbodyParametros').html(
                    '<tr><td colspan="5" class="text-center text-muted py-3">Error de conexión.</td></tr>');
            }
        });
    }

    guardarParametro(idParametro) {
        const self = this;
        const valor = $('#par_' + idParametro).val();

        $.ajax({
            url: '../business/controller/class.configuracion.php',
            type: 'POST',
            dataType: 'json',
            data: { funcion: 2, par_Id: idParametro, par_Valor: valor },
            success: function (resp) {
                if (self.respuestaPideClave(resp)) { return; }
                swal({
                    type: resp.ok == 1 ? 'success' : 'error',
                    title: resp.ok == 1 ? 'Guardado' : 'No se pudo guardar',
                    text: resp.mensaje || ''
                });
                // Se recarga siempre: si el servidor rechazó el valor, el campo
                // debe volver a mostrar el que sigue vigente y no el rechazado.
                self.cargarParametros();
            },
            error: function (xhr) {
                console.log('Error al guardar el parámetro:', xhr.responseText);
                swal({ type: 'error', title: 'Error en el servidor',
                       text: 'No se recibió respuesta válida.' });
            }
        });
    }

    /* ==================== BANCOS ==================== */

    cargarBancos() {
        const self = this;
        $.ajax({
            url: '../business/controller/class.configuracion.php',
            type: 'POST',
            dataType: 'json',
            data: { funcion: 3 },
            success: function (resp) {
                if (resp.ok != 1) {
                    $('#tbodyBancos').html(
                        '<tr><td colspan="6" class="text-center text-muted py-3">' +
                        self.escapeHtml(resp.mensaje || 'No se pudo cargar.') + '</td></tr>');
                    return;
                }
                self._bancos = resp.datos || [];
                self.pintarBancos();
            },
            error: function (xhr) {
                console.log('Error al cargar bancos:', xhr.responseText);
                $('#tbodyBancos').html(
                    '<tr><td colspan="6" class="text-center text-muted py-3">Error de conexión.</td></tr>');
            }
        });
    }

    /**
     * Pinta la tabla, opcionalmente solo los que ya tienen alguna cuenta.
     *
     * El filtro existe porque son 25 bancos y la Alcaldía normalmente recauda
     * por dos o tres: sin él, los configurados se pierden entre los otros
     * veintitantos vacíos.
     */
    pintarBancos() {
        const self = this;
        const soloConCuenta = $('#soloConCuenta').is(':checked');

        // La cuenta contable ya no va aquí (son varias por banco y año: ver
        // "Cuentas contables"); "con cuenta" es con cuenta recaudadora.
        const tiene = (b) => b.ban_CuentaRecaudadora && String(b.ban_CuentaRecaudadora).trim() !== '';

        const lista = soloConCuenta ? self._bancos.filter(tiene) : self._bancos;

        let filas = '';
        lista.forEach(function (b) {
            filas +=
                '<tr>' +
                '<td>' + self.escapeHtml(b.ban_Codigo) + '</td>' +
                '<td>' + self.escapeHtml(b.ban_Nombre) + '</td>' +
                '<td>' + self.escapeHtml(b.ban_Asobancaria) + '</td>' +
                '<td><input type="text" class="form-control form-control-sm" ' +
                    'id="rec_' + Number(b.ban_Id) + '" maxlength="30" ' +
                    'aria-label="Cuenta recaudadora de ' + self.escapeHtml(b.ban_Nombre) + '" ' +
                    'value="' + self.escapeHtml(b.ban_CuentaRecaudadora) + '"></td>' +
                '<td><small>' + self.escapeHtml(b.ban_FechaActualizacion) +
                    (b.ban_ActualizadoPorNombre
                        ? '<br><span class="text-muted">' + self.escapeHtml(b.ban_ActualizadoPorNombre) + '</span>'
                        : '') + '</small></td>' +
                '<td><button type="button" class="btn btn-sm btn-primary" ' +
                    'onclick="configuracion.guardarCuentas(' + Number(b.ban_Id) + ')">Guardar</button></td>' +
                '</tr>';
        });

        $('#tbodyBancos').html(filas ||
            '<tr><td colspan="6" class="text-center text-muted py-3">' +
            (soloConCuenta ? 'Ningún banco tiene cuentas configuradas todavía.' : 'No hay bancos.') +
            '</td></tr>');
        self.bloquearTablas();
    }

    /**
     * Cambiar la cuenta recaudadora cambia lo que imprime el recibo de pago de
     * todo el municipio, y ya no lo frena la contraseña: se confirma con el
     * antes y el después a la vista.
     */
    guardarCuentas(idBanco) {
        const self = this;
        const banco = self._bancos.find((b) => Number(b.ban_Id) === Number(idBanco)) || {};
        const antes = String(banco.ban_CuentaRecaudadora || '').trim();
        const despues = String($('#rec_' + idBanco).val() || '').trim();

        if (antes === despues) { self.enviarCuentas(idBanco); return; }

        const fila = (rotulo, valor) =>
            '<tr><td style="text-align:left;padding:4px 10px;">' + rotulo + '</td>' +
            '<td style="text-align:left;padding:4px 10px;"><b>' +
            (valor ? self.escapeHtml(valor) : '<span style="color:#888;">(vacía: no sale en el recibo)</span>') +
            '</b></td></tr>';

        swal({
            title: '¿Cambiar la cuenta del recibo?',
            html: '<p style="margin-bottom:8px;">' + self.escapeHtml(banco.ban_Nombre || 'Banco') + '</p>' +
                  '<table style="margin:0 auto;">' + fila('Antes', antes) + fila('Después', despues) + '</table>' +
                  '<p style="margin-top:12px;font-size:14px;">Los recibos de pago que se generen desde ahora ' +
                  'salen con este cambio.</p>',
            type: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, guardar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.value) { self.enviarCuentas(idBanco); }
        });
    }

    enviarCuentas(idBanco) {
        const self = this;

        $.ajax({
            url: '../business/controller/class.configuracion.php',
            type: 'POST',
            dataType: 'json',
            data: {
                funcion: 4,
                ban_Id: idBanco,
                ban_CuentaRecaudadora: $('#rec_' + idBanco).val()
            },
            success: function (resp) {
                swal({
                    type: resp.ok == 1 ? 'success' : 'error',
                    title: resp.ok == 1 ? 'Guardado' : 'No se pudo guardar',
                    text: resp.mensaje || ''
                });
                self.cargarBancos();
            },
            error: function (xhr) {
                console.log('Error al guardar las cuentas:', xhr.responseText);
                swal({ type: 'error', title: 'Error en el servidor',
                       text: 'No se recibió respuesta válida.' });
            }
        });
    }

    /* ==================== CUENTAS CONTABLES (051) ==================== */

    /** Las del año elegido (la primera vez, el actual), los años y los bancos. */
    cargarCuentasContables() {
        const self = this;
        const anio = $('#anioCuentas').val() || '';
        $.ajax({
            url: '../business/controller/class.configuracion.php',
            type: 'POST',
            dataType: 'json',
            data: { funcion: 8, anio: anio },
            success: function (resp) {
                if (resp.ok != 1) {
                    $('#tbodyCuentasContables').html(
                        '<tr><td colspan="5" class="text-center text-muted py-3">' +
                        self.escapeHtml(resp.mensaje || 'No se pudo cargar.') + '</td></tr>');
                    $('#formCuentaContable :input').prop('disabled', true);
                    return;
                }
                self.pintarCuentasContables(resp.datos || {});
            },
            error: function (xhr) {
                console.log('Error al cargar las cuentas contables:', xhr.responseText);
                $('#formCuentaContable :input').prop('disabled', true);
                $('#tbodyCuentasContables').html(
                    '<tr><td colspan="5" class="text-center text-muted py-3">Error de conexión.</td></tr>');
            }
        });
    }

    pintarCuentasContables(d) {
        const self = this;
        const anio = Number(d.anio);
        $('#anioCuentas').html((d.anios || []).map(function (a) {
            return '<option value="' + Number(a) + '"' + (Number(a) === anio ? ' selected' : '') + '>' + Number(a) + '</option>';
        }).join(''));

        // Los bancos del formulario: primero los que tienen cuenta recaudadora.
        const $banco = $('#ccBanco');
        const elegido = $banco.val();
        $banco.html('<option value="">Elija el banco</option>' + (d.bancos || []).map(function (b) {
            return '<option value="' + Number(b.id) + '">' + self.escapeHtml(b.nombre) + '</option>';
        }).join(''));
        if (elegido) { $banco.val(elegido); }
        $('#formCuentaContable :input').prop('disabled', false);
        $('#btnAgregarCuenta').text('Agregar a ' + anio);

        const cuentas = d.cuentas || [];
        let filas = '';
        cuentas.forEach(function (c) {
            const usos = Number(c.usos) || 0;
            filas +=
                '<tr>' +
                '<td>' + self.escapeHtml(c.banco) + '</td>' +
                '<td><b>' + self.escapeHtml(c.cuenta) + '</b></td>' +
                '<td><div class="d-flex" style="gap:6px;">' +
                    '<input type="text" class="form-control form-control-sm" id="ccDesc_' + Number(c.id) + '" maxlength="100" ' +
                    'aria-label="Descripción de la cuenta ' + self.escapeHtml(c.cuenta) + '" ' +
                    'value="' + self.escapeHtml(c.descripcion) + '">' +
                    '<button type="button" class="btn btn-sm btn-outline-primary" ' +
                    'aria-label="Guardar la descripción de la cuenta ' + self.escapeHtml(c.cuenta) + '" ' +
                    'onclick="configuracion.guardarDescripcion(' + Number(c.id) + ')">Guardar</button></div></td>' +
                '<td><small>' + self.escapeHtml(c.fecha || '') +
                    (c.creador ? '<br><span class="text-muted">' + self.escapeHtml(c.creador) + '</span>' : '') + '</small></td>' +
                '<td>' + (usos > 0
                    // La usada no se quita: los pagos manuales la nombran.
                    ? '<small class="text-muted">Usada en ' + usos + (usos === 1 ? ' pago' : ' pagos') + '</small>'
                    : '<button type="button" class="btn btn-sm btn-outline-danger" ' +
                      'aria-label="Quitar la cuenta ' + self.escapeHtml(c.cuenta) + '" ' +
                      'onclick="configuracion.quitarCuentaContable(' + Number(c.id) + ')">Quitar</button>') + '</td>' +
                '</tr>';
        });
        $('#tbodyCuentasContables').html(filas ||
            '<tr><td colspan="5" class="text-center text-muted py-3">No hay cuentas contables para ' + anio + '.' +
            (anio === Number(d.anioActual) ? ' Mientras un banco no tenga, el pago manual no la pide.' : '') + '</td></tr>');
        // En diciembre, si el año siguiente no tiene cuentas: el 1 de enero el
        // pago manual dejaría de pedirlas.
        $('#avisoCuentasSiguiente').remove();
        if (d.faltaSiguiente) {
            $('#formCuentaContable').before('<div class="alert alert-warning py-2" id="avisoCuentasSiguiente" style="font-size:13px;">' +
                'Todavía no hay cuentas contables para ' + (Number(d.anioActual) + 1) + '. Cárguelas antes del 1 de enero ' +
                '(elija ' + (Number(d.anioActual) + 1) + ' arriba): desde ese día el pago manual usa las del año nuevo.</div>');
        }
    }

    /**
     * Lo que responde el servidor: aviso y, si se pide, la lista recargada. Al
     * guardar una descripción no se recarga: se perderían las que se están
     * escribiendo en otras filas.
     */
    resultadoCuenta(resp, recargar) {
        swal({
            type: resp && resp.ok == 1 ? 'success' : 'error',
            title: resp && resp.ok == 1 ? 'Listo' : 'No se pudo',
            text: (resp && resp.mensaje) || ''
        });
        if (resp && resp.ok == 1 && recargar !== false) { this.cargarCuentasContables(); }
    }

    agregarCuentaContable() {
        const self = this;
        const form = document.getElementById('formCuentaContable');
        if (form.checkValidity && !form.checkValidity()) { if (form.reportValidity) { form.reportValidity(); } return; }
        $('#btnAgregarCuenta').prop('disabled', true);
        $.ajax({
            url: '../business/controller/class.configuracion.php',
            type: 'POST',
            dataType: 'json',
            data: {
                funcion: 9, anio: $('#anioCuentas').val(), idBanco: $('#ccBanco').val(),
                cuenta: $('#ccCuenta').val(), descripcion: $('#ccDescripcion').val()
            },
            success: function (resp) {
                $('#btnAgregarCuenta').prop('disabled', false);
                if (resp && resp.ok == 1) { $('#ccCuenta').val(''); $('#ccDescripcion').val(''); }
                self.resultadoCuenta(resp);
            },
            error: function () {
                $('#btnAgregarCuenta').prop('disabled', false);
                swal({ type: 'error', title: 'Error en el servidor', text: 'No se recibió respuesta válida.' });
            }
        });
    }

    guardarDescripcion(id) {
        const self = this;
        $.ajax({
            url: '../business/controller/class.configuracion.php',
            type: 'POST',
            dataType: 'json',
            data: { funcion: 10, id: id, descripcion: $('#ccDesc_' + Number(id)).val() },
            success: function (resp) {
                if (resp && resp.ok == 1) { $('#ccDesc_' + Number(id)).val(String($('#ccDesc_' + Number(id)).val()).trim()); }
                self.resultadoCuenta(resp, false);
            },
            error: function () { swal({ type: 'error', title: 'Error en el servidor', text: 'No se recibió respuesta válida.' }); }
        });
    }

    quitarCuentaContable(id) {
        const self = this;
        swal({
            title: '¿Quitar la cuenta contable?',
            text: 'Deja de salir en el pago manual. Ningún pago la ha usado todavía.',
            type: 'question',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Sí, quitarla',
            cancelButtonText: 'Cancelar'
        }).then(function (r) {
            if (!r || !r.value) { return; }
            $.ajax({
                url: '../business/controller/class.configuracion.php',
                type: 'POST',
                dataType: 'json',
                data: { funcion: 11, id: id },
                success: function (resp) { self.resultadoCuenta(resp); },
                error: function () { swal({ type: 'error', title: 'Error en el servidor', text: 'No se recibió respuesta válida.' }); }
            });
        });
    }
}

const configuracion = new Configuracion();

$(function () {
    configuracion.consultarCandado();
    configuracion.cargarParametros();
    configuracion.cargarBancos();
    configuracion.cargarCuentasContables();
    $('#formCuentaContable').on('submit', function (e) {
        e.preventDefault();
        configuracion.agregarCuentaContable();
    });

    $('#btnCandado').on('click', function () {
        if (configuracion._desbloqueada) { configuracion.bloquear(); } else { configuracion.abrirClave(); }
    });
    $('#formClave').on('submit', function (e) {
        e.preventDefault();
        configuracion.desbloquear();
    });
    $('#modal-Clave').on('shown.bs.modal', function () { $('#claveEdicion').trigger('focus'); });
});
