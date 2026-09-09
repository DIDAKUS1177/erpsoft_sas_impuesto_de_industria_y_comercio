/*
 * Pantalla "Presentar Declaración" de RETEICA (retencion mensual).
 *
 * Las actividades SI se eligen: son las de los terceros a quienes el agente
 * retenedor les practico retencion durante el mes, y eso el sistema no lo sabe.
 */
$(function () {
    Retenciones.presentar({
        // Viaja al servicio de firmas: identifica de qué formulario es
        // cada firma. Los tres módulos reparten números de series distintas,
        // así que el mismo número existe en varios a la vez.
        modulo: 'RETEICA',
        endpoint: '../business/controller/class.reteica.php',
        titulo: 'Retención de Industria y Comercio',
        periodoMax: 12,
        nombrePeriodo: 'mes',
        actividadesEditables: true,
        campoEnergia: false,
        textoSinActividades: 'Agregue las actividades sobre las que practicó retención en el mes.',
        pdf: '../extensiones/reteica.php'
    });
});
