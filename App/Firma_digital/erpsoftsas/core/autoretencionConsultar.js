/*
 * Pantalla "Consultar Declaraciones" de AUTORRETEICA.
 */
$(function () {
    Retenciones.consultar({
        // Viaja al servicio de firmas: identifica de qué formulario es
        // cada firma. Los tres módulos reparten números de series distintas,
        // así que el mismo número existe en varios a la vez.
        modulo: 'AUTORRETEICA',
        endpoint: '../business/controller/class.autorreteica.php',
        titulo: 'Autorretención de Industria y Comercio',
        periodoMax: 6,
        nombrePeriodo: 'bimestre',
        pantallaPresentar: 'autoretencionPresentar.php',
        pdf: '../extensiones/autorreteica.php'
    });
});
