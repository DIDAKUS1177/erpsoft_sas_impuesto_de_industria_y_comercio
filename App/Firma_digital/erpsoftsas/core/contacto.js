/**
 * Los avisos de las pantallas que mandan al contribuyente a la Alcaldía.
 *
 * POR QUÉ EXISTE
 *
 * Seis avisos (cuatro de "Creación de establecimiento" y dos de "Presentación
 * y pago") traían ESCRITO "el municipio de Paipa" y impuestos@paipa-boyaca.gov.co,
 * así que en los demás municipios mandaban al contribuyente a Paipa. Ahora salen
 * de aquí, armados con el contacto del municipio que dist/menu.php deja en
 * window.ERP_CONTACTO (lo lee business/class.contacto.php de Municipio y bancos):
 *
 *     { dependencia, telefono, correo, ciudad }     cada uno texto o null
 *
 * Un solo sitio para los textos: que un cambio de redacción no se haga en una
 * pantalla y se olvide en las otras cinco.
 *
 * REGLAS
 *
 *  - Con el contacto de Paipa el texto queda EXACTAMENTE igual al de siempre.
 *    Incluida la errata "transferecia" (así estaba escrito y así se conserva;
 *    corregirla es decisión de Diego).
 *  - Sin correo configurado el aviso no menciona ningún correo, y sin ciudad no
 *    nombra ninguna: nunca se completa con datos de otro municipio.
 *  - Los avisos se muestran con swal({ text: ... }), que SweetAlert2 pone como
 *    texto y no como HTML. Si algún día se pasan a "html", hay que escapar.
 *
 * Se prueba con:   node pruebas/contacto.test.js
 */
var ErpContacto = (function () {
    'use strict';

    function limpio(valor) {
        return (valor === null || valor === undefined) ? '' : String(valor).trim();
    }

    /** El contacto: el que se pase (las pruebas) o el de la página (window.ERP_CONTACTO). */
    function datos(contacto) {
        var c = contacto || (typeof window !== 'undefined' ? window.ERP_CONTACTO : null) || {};
        return {
            dependencia: limpio(c.dependencia),
            telefono: limpio(c.telefono),
            correo: limpio(c.correo),
            ciudad: limpio(c.ciudad)
        };
    }

    /** "Creación de establecimiento": se hace en persona o por correo. */
    function textoNuevoEstablecimiento(contacto) {
        var c = datos(contacto);
        return 'Para la creación de un nuevo establecimiento diríjase de manera presencial a la secretaria de Hacienda ' +
            (c.ciudad ? 'del municipio de ' + c.ciudad : 'de su municipio') +
            (c.correo ? ' o realice el trámite a través del correo ' + c.correo : '') +
            '.';
    }

    /** "Presentación y pago": a las entidades financieras, y los soportes a la Alcaldía. */
    function textoPresentarYPagar(contacto) {
        var c = datos(contacto);
        return 'Para la PRESENTACIÓN y PAGO de la Declaración diríjase a las entidades financieras o realice transferecia bancaria y ' +
            (c.correo
                ? 'envíe los respectivos soportes al correo: ' + c.correo
                : 'entregue los respectivos soportes en la secretaria de Hacienda ' +
                  (c.ciudad ? 'del municipio de ' + c.ciudad : 'de su municipio')) +
            '.';
    }

    var api = {
        datos: datos,
        textoNuevoEstablecimiento: textoNuevoEstablecimiento,
        textoPresentarYPagar: textoPresentarYPagar
    };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
    return api;
})();
