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
        pdf: '../extensiones/reteica.php',

        // Retencion no tiene bloque de ingresos: se declara sobre las
        // actividades de los terceros, asi que la tabla de actividades ya va
        // primero y no hay nada que reordenar (por eso no lleva ingresosHasta).

        /*
         * Suma en vivo, identica al servidor (BD/migraciones/030):
         *   14 = suma de las retenciones de las actividades
         *   17 = 14 + 15 (sanciones) + 16 (intereses)
         * Solo vista previa; el backend manda al Guardar/Liquidar/Presentar.
         */
        calcular: function (v, actividades /*, energia */) {
            var c14 = actividades;
            var c17 = c14 + (v[15] || 0) + (v[16] || 0);
            return { 14: c14, 17: c17 };
        }
    });
});
