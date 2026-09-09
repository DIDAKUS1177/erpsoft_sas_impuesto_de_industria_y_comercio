/*
 * Pantalla "Consultar Declaraciones" de RETEICA.
 * La logica vive en core/retenciones.js; aqui solo va lo que distingue al modulo.
 */
$(function () {
    Retenciones.consultar({
        // Viaja al servicio de firmas: identifica de qué formulario es
        // cada firma. Los tres módulos reparten números de series distintas,
        // así que el mismo número existe en varios a la vez.
        modulo: 'RETEICA',
        endpoint: '../business/controller/class.reteica.php',
        titulo: 'Retención de Industria y Comercio',
        periodoMax: 12,
        nombrePeriodo: 'mes',
        pantallaPresentar: 'reteicaPresentar.php',
        pdf: '../extensiones/reteica.php'
    });
});
