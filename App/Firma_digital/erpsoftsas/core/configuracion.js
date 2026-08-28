/**
 * Configuración del municipio: parámetros y cuentas de los bancos.
 *
 * El permiso real vive en el servidor (class.configuracion.php, roles 1 y 2).
 * Lo de aquí es solo la pantalla: quien llegue por la URL sin ser Alcaldía
 * recibe un rechazo del servidor y las dos tablas se quedan vacías con su
 * mensaje, en vez de mostrar el EAN de recaudo a cualquiera.
 */
class Configuracion {

    constructor() {
        this._bancos = [];
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

                    const casilla = sensible
                        ? '<input type="password" class="form-control form-control-sm" ' +
                              'id="par_' + Number(p.par_Id) + '" value="" autocomplete="new-password" ' +
                              'placeholder="' + (puesto ? 'Escriba una nueva para cambiarla'
                                                        : 'Sin configurar') + '">' +
                          '<small class="' + (puesto ? 'text-success' : 'text-muted') + '">' +
                              (puesto ? '<i class="fa fa-check"></i> Configurada · dejarla en blanco no la borra'
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

        const tiene = (b) =>
            (b.ban_CuentaContable && String(b.ban_CuentaContable).trim() !== '') ||
            (b.ban_CuentaRecaudadora && String(b.ban_CuentaRecaudadora).trim() !== '');

        const lista = soloConCuenta ? self._bancos.filter(tiene) : self._bancos;

        let filas = '';
        lista.forEach(function (b) {
            filas +=
                '<tr>' +
                '<td>' + self.escapeHtml(b.ban_Codigo) + '</td>' +
                '<td>' + self.escapeHtml(b.ban_Nombre) + '</td>' +
                '<td>' + self.escapeHtml(b.ban_Asobancaria) + '</td>' +
                '<td><input type="text" class="form-control form-control-sm" ' +
                    'id="cta_' + Number(b.ban_Id) + '" maxlength="40" ' +
                    'value="' + self.escapeHtml(b.ban_CuentaContable) + '"></td>' +
                '<td><input type="text" class="form-control form-control-sm" ' +
                    'id="rec_' + Number(b.ban_Id) + '" maxlength="40" ' +
                    'value="' + self.escapeHtml(b.ban_CuentaRecaudadora) + '"></td>' +
                '<td><button type="button" class="btn btn-sm btn-primary" ' +
                    'onclick="configuracion.guardarCuentas(' + Number(b.ban_Id) + ')">Guardar</button></td>' +
                '</tr>';
        });

        $('#tbodyBancos').html(filas ||
            '<tr><td colspan="6" class="text-center text-muted py-3">' +
            (soloConCuenta ? 'Ningún banco tiene cuentas configuradas todavía.' : 'No hay bancos.') +
            '</td></tr>');
    }

    guardarCuentas(idBanco) {
        const self = this;

        $.ajax({
            url: '../business/controller/class.configuracion.php',
            type: 'POST',
            dataType: 'json',
            data: {
                funcion: 4,
                ban_Id: idBanco,
                ban_CuentaContable: $('#cta_' + idBanco).val(),
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
}

const configuracion = new Configuracion();

$(function () {
    configuracion.cargarParametros();
    configuracion.cargarBancos();
});
