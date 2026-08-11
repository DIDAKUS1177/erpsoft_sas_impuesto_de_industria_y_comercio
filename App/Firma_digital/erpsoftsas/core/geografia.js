/**
 * Geografia: departamento/ciudad en cascada para los selects de direccion de
 * establecimiento (est_Departamento / est_Ciudad).
 *
 * Antes esos dos <select> venian con UN SOLO <option> fijo en el HTML
 * ("Boyaca" / "Paipa") en las 4 pantallas que crean/editan establecimientos
 * (establecimientos.php, icaWebRit.php, icaWebConsultar.php,
 * icaWebPresentar.php) -asi que cualquier contribuyente de otro departamento
 * quedaba forzado a guardar una direccion que no era la suya. Esta ausencia
 * de catalogo era justo el punto 4 que reporto el cliente ("solo me carga
 * Colombia y solo un departamento y solo Paipa").
 *
 * est_Pais/est_Departamento/est_Ciudad son columnas VARCHAR de texto libre
 * (ver business/DAO/DAO_Establecimientos.php), no llaves foraneas -por eso
 * aqui se guarda el NOMBRE tal cual, no un id-. La fuente de los nombres es
 * el mismo catalogo conf_ciudades que ya usa "Informacion del Contribuyente"
 * (business/controller/class.ciudades.php), asi que hay un solo lugar donde
 * mantener la lista de municipios de Colombia.
 */
var Geografia = (function () {

    var _cache = null; // arr.datos de class.ciudades.php, cacheado una vez por carga de pagina.

    function cargarCatalogo(callback) {
        if (_cache) { callback(_cache); return; }

        $.ajax({
            url: '../business/controller/class.ciudades.php',
            type: 'POST',
            dataType: 'json',
            data: { funcion: 1 },
            success: function (arr) {
                _cache = (arr.ok == 1 && Array.isArray(arr.datos)) ? arr.datos : [];
                callback(_cache);
            },
            error: function () {
                callback([]);
            }
        });
    }

    /**
     * poblar: prepara los selects #idDepartamento / #idCiudad de una pantalla.
     * - Llena el de departamento con los nombres unicos del catalogo.
     * - Al cambiar de departamento, repuebla el de ciudad con los municipios
     *   de ese departamento.
     * - Si se pasan deptoActual/ciudadActual (modo edicion), preselecciona.
     */
    function poblar(idDepartamento, idCiudad, deptoActual, ciudadActual) {

        var $depto = $('#' + idDepartamento);
        var $ciudad = $('#' + idCiudad);

        cargarCatalogo(function (datos) {

            var deptos = [];
            var vistos = {};
            datos.forEach(function (c) {
                if (!vistos[c.ciu_Departamento]) {
                    vistos[c.ciu_Departamento] = true;
                    deptos.push(c.ciu_Departamento);
                }
            });
            deptos.sort();

            $depto.empty().append('<option value="">Seleccione departamento...</option>');
            deptos.forEach(function (d) {
                $depto.append('<option value="' + d + '">' + d + '</option>');
            });

            function repoblarCiudad(depto, ciudadPreseleccionada) {
                $ciudad.empty().append('<option value="">Seleccione municipio...</option>');
                datos
                    .filter(function (c) { return c.ciu_Departamento === depto; })
                    .forEach(function (c) {
                        $ciudad.append('<option value="' + c.ciu_Nombre + '">' + c.ciu_Nombre + '</option>');
                    });
                if (ciudadPreseleccionada) {
                    $ciudad.val(ciudadPreseleccionada.trim());
                }
            }

            $depto.off('change.geografia').on('change.geografia', function () {
                repoblarCiudad($(this).val(), null);
            });

            if (deptoActual) {
                $depto.val(deptoActual.trim());
                repoblarCiudad(deptoActual.trim(), ciudadActual);
            } else {
                $ciudad.empty().append('<option value="">Seleccione municipio...</option>');
            }
        });
    }

    return { poblar: poblar };

})();
