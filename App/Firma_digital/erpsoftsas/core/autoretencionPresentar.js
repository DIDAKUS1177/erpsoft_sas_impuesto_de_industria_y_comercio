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
        pdf: '../extensiones/autorreteica.php',

        // Las casillas 9-13 (ingresos) se pintan ANTES de las actividades, como
        // en el ICA. De la 15 en adelante (liquidacion) van despues. No hay
        // casilla 14 en autorretencion; el corte natural es en la 13.
        ingresosHasta: 13,

        /*
         * Suma en vivo de las casillas calculadas, IDENTICA a lo que hace el
         * servidor al liquidar. Es solo la vista previa mientras se teclea; el
         * backend vuelve a calcular y manda al Guardar/Liquidar/Presentar. Las
         * formulas son las de BD/migraciones/030 y 031 (deben coincidir):
         *
         *   13 = 9 - 10 - 11 - 12                 (ingresos netos gravados)
         *   15 = actividades + energia            (una sola vez)
         *   16 = 15 * 15%, redondeado a miles
         *   17 = 15 + 16
         *   19 = 17 - 18
         *   20 = manual (saldo a favor que escribe el contribuyente)
         *   23 = 19 - 20 + 21 + 22
         */
        calcular: function (v, actividades, energia) {
            var alMil = function (x) { return Math.round(x / 1000) * 1000; };
            var c13 = (v[9] || 0) - (v[10] || 0) - (v[11] || 0) - (v[12] || 0);
            var c15 = actividades + energia;
            var c16 = alMil(c15 * 0.15);
            var c17 = c15 + c16;
            var c19 = c17 - (v[18] || 0);
            var c23 = c19 - (v[20] || 0) + (v[21] || 0) + (v[22] || 0);
            return { 13: c13, 15: c15, 16: c16, 17: c17, 19: c19, 23: c23 };
        }
    });
});
