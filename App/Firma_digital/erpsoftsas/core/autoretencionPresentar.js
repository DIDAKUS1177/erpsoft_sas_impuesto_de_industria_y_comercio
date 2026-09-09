/*
 * Pantalla "Presentar Declaración" de AUTORRETEICA (autorretencion bimestral).
 *
 * Las actividades NO se eligen: se declara sobre las propias, que ya estan en
 * el RIT, y por eso llegan precargadas y solo se les escriben los ingresos.
 */
$(function () {
    Retenciones.presentar({
        // Viaja al servicio de firmas: identifica de qué formulario es
        // cada firma. Los tres módulos reparten números de series distintas,
        // así que el mismo número existe en varios a la vez.
        modulo: 'AUTORRETEICA',
        endpoint: '../business/controller/class.autorreteica.php',
        titulo: 'Autorretención de Industria y Comercio',
        periodoMax: 6,
        nombrePeriodo: 'bimestre',
        actividadesEditables: false,
        campoEnergia: true,
        textoSinActividades: 'No tiene actividades registradas en el RIT. '
                           + 'Actualice su RIT antes de declarar.',
        pdf: '../extensiones/autorreteica.php'
    });
});
