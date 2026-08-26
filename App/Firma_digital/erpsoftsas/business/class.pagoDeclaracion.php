<?php

namespace erpsoftsas;

/**
 * El ÚNICO sitio que marca una declaración como pagada.
 *
 * POR QUÉ EXISTE
 *
 * Hasta el 2026-08-25 el pago se escribía desde cuatro sitios distintos y cada
 * uno llenaba un juego de columnas diferente:
 *
 *     recaudo bancario  ->  dec_Pagado, dec_FechaPago, dec_ValorPago, dec_BancoPago
 *     PSE (tres vías)   ->  las cuatro anteriores + dec_FechaRealPago
 *
 * y dos columnas no las llenaba nadie: dec_AnioPago y dec_RutaPago. O sea que,
 * según por dónde entrara la plata, la misma declaración quedaba con datos
 * distintos, y cualquier informe que cruzara esos campos daba cifras que no
 * cuadran. Es lo que el cliente pidió unificar.
 *
 * QUÉ SIGNIFICA CADA COLUMNA
 *
 *     dec_Pagado          la declaración está pagada
 *     dec_FechaPago       cuándo pagó el contribuyente, según el banco o la
 *                         pasarela. NO es cuándo nos enteramos nosotros
 *     dec_FechaRealPago   cuándo lo registró el sistema. Con el archivo del
 *                         banco puede ser días después de la fecha de pago
 *     dec_ValorPago       lo que efectivamente entró
 *     dec_BancoPago       nombre del banco por el que entró
 *     dec_AnioPago        año del pago. Sirve para cuadrar el recaudo por
 *                         vigencia sin tener que extraerlo de una fecha
 *     dec_RutaPago        por dónde entró: PSE o recaudo bancario. Sin esto
 *                         no hay forma de saber el canal de un pago viejo
 *
 * La distinción entre las dos fechas no es un capricho: en el recaudo bancario
 * el archivo plano trae la fecha en que la gente pagó en ventanilla, y ese
 * archivo se carga después. Guardar solo una de las dos hace imposible
 * responder "¿pagó a tiempo?" y "¿cuándo lo supimos?" a la vez.
 *
 * UN PAGO SOLO EXISTE SOBRE UNA DECLARACIÓN PRESENTADA
 *
 * Esa regla se comprueba antes de llamar aquí, en cada vía: el recaudo manda
 * esos renglones al informe de excepciones y PSE rechaza pagar un borrador.
 * Ver la nota correspondiente en CLAUDE.md.
 */
class PagoDeclaracion
{
    /** Por dónde entró la plata. Va tal cual a dec_RutaPago. */
    const VIA_PSE     = 'PSE';
    const VIA_RECAUDO = 'RECAUDO_BANCARIO';

    /**
     * dec_BancoPago es VARCHAR(60) en la base.
     *
     * El código de PSE lo recortaba a 10 por un comentario que decía que era
     * VARCHAR(10) — comprobado el 2026-08-25 contra INFORMATION_SCHEMA: son
     * 60. Con el tope viejo, "Banco de Bogotá" se guardaba como "Banco de B".
     */
    const LARGO_BANCO = 60;

    /**
     * Marca la declaración como pagada, llenando SIEMPRE el mismo juego de
     * columnas venga por donde venga.
     *
     * @param  mixed  $con            conexión del proyecto
     * @param  int    $idDeclaracion
     * @param  array  $datos          valor, banco, via, y opcionalmente
     *                                fechaPago ('Y-m-d' o 'Y-m-d H:i:s')
     * @return bool   true si esta llamada fue la que la marcó
     */
    public static function registrar($con, $idDeclaracion, array $datos)
    {
        $idDeclaracion = (int) $idDeclaracion;
        if ($idDeclaracion <= 0) { return false; }

        $via = in_array($datos['via'] ?? '', [self::VIA_PSE, self::VIA_RECAUDO], true)
            ? $datos['via']
            : self::VIA_RECAUDO;

        $banco = substr(trim((string) ($datos['banco'] ?? '')), 0, self::LARGO_BANCO);

        /*
         * Si no viene fecha de pago se usa la de ahora. PSE es así: el pago
         * acaba de ocurrir. El recaudo bancario SÍ la trae, y es la de
         * ventanilla, que puede ser de días atrás.
         */
        $fechaPago = trim((string) ($datos['fechaPago'] ?? ''));
        if ($fechaPago === '') { $fechaPago = date('Y-m-d H:i:s'); }

        $anio = (int) date('Y', strtotime($fechaPago));

        /*
         * La guarda de dec_Pagado hace la operación idempotente: la
         * notificación del banco y el retorno del usuario llegan casi a la vez
         * y las dos intentan aplicar el mismo pago. Sin ella, la segunda
         * pisaría la fecha de la primera con una posterior.
         */
        $con->consultar(
            "UPDATE ind_declaraciones_ica
                SET dec_Pagado        = 1,
                    dec_FechaPago     = ?,
                    dec_FechaRealPago = GETDATE(),
                    dec_ValorPago     = ?,
                    dec_BancoPago     = ?,
                    dec_AnioPago      = ?,
                    dec_RutaPago      = ?
              WHERE dec_Id = ? AND ISNULL(dec_Pagado, 0) = 0",
            [$fechaPago, $datos['valor'] ?? 0, $banco, $anio, $via, $idDeclaracion]
        );

        $fila = $con->obnerFila($con->consultar(
            "SELECT dec_RutaPago FROM ind_declaraciones_ica WHERE dec_Id = ?",
            [$idDeclaracion]
        ));

        // Fue esta llamada la que la marcó si la vía guardada es la suya.
        return isset($fila['dec_RutaPago']) && $fila['dec_RutaPago'] === $via;
    }
}
