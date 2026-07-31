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
     * Botones de accion para UNA declaracion.
     *
     * d = null            -> 3 botones deshabilitados (todavia no hay declaracion).
     * d.is_signed == 0     -> Editar Borrador / Firmar / Ver Borrador.
     * d.is_signed == 1     -> Descargar PDF Oficial / Presentar / Pagar.
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

        if (d.is_signed == 0) {
            return '<a href="javascript:void(0);" onclick="' + objJs + '.editarDeclaracion(' + d.dec_Id + ')" ' +
                       'class="btn btn-warning btn-sm mr-1" title="Editar Borrador"><i class="fa fa-pencil"></i></a>' +
                   '<a href="javascript:void(0);" onclick="' + objJs + '.abrirFirmaDigital(' + d.dec_Id + ', ' + d.dec_IdEstablecimiento + ')" ' +
                       'class="btn btn-secondary btn-sm mr-1" title="Firmar"><i class="fa fa-certificate"></i></a>' +
                   '<a href="../extensiones/declaracion.php?dec_Id=' + d.dec_Id + '" target="_blank" ' +
                       'class="btn btn-info btn-sm mr-1" title="Ver Borrador"><i class="fa fa-eye"></i></a>';
        }

        return '<a href="../extensiones/declaracion.php?dec_Id=' + d.dec_Id + '" target="_blank" ' +
                   'class="btn btn-primary btn-sm mr-1" title="Descargar PDF Oficial"><i class="fa fa-download"></i></a>' +
               '<a href="javascript:void(0);" onclick="' + objJs + '.presentarDeclaracion(' + d.dec_Id + ')" ' +
                   'class="btn btn-success btn-sm mr-1" title="Presentar"><i class="fa fa-paper-plane"></i></a>' +
               '<a href="../extensiones/liquidacion.php?dec_Id=' + d.dec_Id + '" target="_blank" ' +
                   'class="btn btn-danger btn-sm" title="Pagar"><i class="fa fa-money"></i></a>';
    }

    return {
        nombreMes: nombreMes,
        htmlAcciones: htmlAcciones
    };

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
if (typeof $ !== 'undefined') {
    $(document).ajaxError(function (event, jqxhr, settings) {
        $('#loading').hide();
        $('#wrapper').removeClass('body-load');

        if (typeof swal === 'function') {
            swal({
                type: 'error',
                title: 'Error de conexión',
                text: 'No se pudo completar la solicitud (' + settings.url + '). Intenta de nuevo.'
            });
        }
    });
}
