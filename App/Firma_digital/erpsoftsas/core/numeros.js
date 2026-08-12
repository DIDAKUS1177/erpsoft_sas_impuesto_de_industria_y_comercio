/*
 * NumerosCOP — manejo canónico de cifras en formato colombiano.
 * ============================================================
 *
 * Por qué existe este archivo
 * ---------------------------
 * Estas cuatro funciones estaban COPIADAS en icaWebRit.js,
 * icaWebConsultar.js e icaWebPresentar.js. Eso ya costó dos bugs reales:
 *
 *   - El botón "Liquidar": numero() no convertía la coma decimal antes de
 *     parseFloat, así que truncaba silenciosamente cualquier valor con
 *     decimales. Se corrigió en UNA copia y siguió roto en las otras dos.
 *
 *   - Los "00000" al corregir una declaración: SQL Server devuelve los
 *     decimales como texto con PUNTO ("2500000.00"), pero estos campos se
 *     leen como formato colombiano, donde el punto es separador de MILES.
 *     El valor quedaba multiplicado por 100 en cada pasada.
 *
 * Regla del formato es-CO: PUNTO = miles, COMA = decimal.
 *   "2.500.000,75"  ->  2500000.75
 *
 * OJO con el origen del dato:
 *   - Viene de un INPUT que ve el usuario  -> usar aCifra()
 *   - Viene crudo de la BASE DE DATOS      -> usar deBaseDeDatos()
 * Confundirlos es exactamente el bug de los "00000".
 *
 * Este archivo no depende de jQuery ni del DOM: se puede probar en Node
 * (ver BD/../pruebas/numeros.test.js) y se exporta por module.exports
 * cuando existe.
 */
var NumerosCOP = (function () {

    /**
     * Texto en formato colombiano -> número.
     * Para valores que el USUARIO escribió o que ya se pintaron formateados.
     *   "2.500.000"    -> 2500000
     *   "2.500.000,75" -> 2500000.75
     */
    function aCifra(valor) {
        if (valor === null || valor === undefined || valor === '') { return 0; }
        return parseFloat(
            valor.toString().replace(/\./g, '').replace(',', '.')
        ) || 0;
    }

    /**
     * Valor crudo de la BASE DE DATOS -> número.
     * SQL Server entrega los decimales con PUNTO decimal ("2500000.00"), que
     * es justo la notación de JavaScript, así que aquí NO se toca el punto.
     * Pasar esto por aCifra() lo multiplicaría por 100.
     */
    function deBaseDeDatos(valor) {
        if (valor === null || valor === undefined || valor === '') { return 0; }
        return parseFloat(valor) || 0;
    }

    /**
     * Número -> texto en formato colombiano, para mostrar.
     *   2500000 -> "2.500.000"
     */
    function formatear(numero) {
        var n = (typeof numero === 'number') ? numero : deBaseDeDatos(numero);
        return n.toLocaleString('es-CO');
    }

    /**
     * Valor de la BD -> texto listo para meter en un input de la pantalla.
     * Es el puente correcto BD -> formulario, y redondea a peso entero
     * porque los campos de la declaración se manejan sin centavos.
     */
    function deBaseDeDatosAInput(valor) {
        return formatear(Math.round(deBaseDeDatos(valor)));
    }

    /**
     * Texto en formato colombiano -> entero (trunca hacia abajo).
     * Equivale al viejo limpiarNumero().
     */
    function aEntero(valor) {
        return Math.floor(aCifra(valor));
    }

    var api = {
        aCifra: aCifra,
        deBaseDeDatos: deBaseDeDatos,
        deBaseDeDatosAInput: deBaseDeDatosAInput,
        formatear: formatear,
        aEntero: aEntero
    };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
    return api;
})();
