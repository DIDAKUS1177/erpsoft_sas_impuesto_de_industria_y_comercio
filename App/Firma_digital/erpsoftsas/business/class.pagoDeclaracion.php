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
    /** Wompi (Bancolombia), migración 042: PSE, tarjeta, Nequi... el medio va en *_BancoPago. */
    const VIA_WOMPI   = 'WOMPI';
    /**
     * Wompi con llaves de PRUEBAS: el sandbox "aprueba" con plata de mentira.
     * Queda con su propia vía para que nunca se confunda con un pago real.
     */
    const VIA_WOMPI_PRUEBA = 'WOMPI_PRUEBA';
    /**
     * Registrado a mano por la Alcaldía con su soporte (migración 044):
     * transferencia, consignación... o una declaración antigua ya pagada. El
     * detalle (medio, referencia, quién) va en ind_pagos_manuales.
     */
    const VIA_MANUAL = 'MANUAL';

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
     * @param  array|null $m          descriptor del modulo (PseModulo::get);
     *                                si es null, ICA -asi el recaudo bancario y
     *                                cualquier llamada vieja siguen igual-. Los
     *                                tres modulos tienen el MISMO juego de
     *                                columnas de pago (migraciones 014 y 032).
     * @return bool   true si esta llamada fue la que la marcó
     */
    public static function registrar($con, $idDeclaracion, array $datos, $m = null)
    {
        $idDeclaracion = (int) $idDeclaracion;
        if ($idDeclaracion <= 0) { return false; }

        require_once __DIR__ . '/class.pseModulo.php';
        if ($m === null) { $m = \erpsoftsas\PseModulo::get('ica'); }
        $t  = $m['tabla'];    // nombre de tabla y prefijo salen del mapa fijo
        $p  = $m['prefijo'];  // de PseModulo, nunca del usuario: seguro interpolarlos
        $pk = $m['pk'];

        $via = in_array($datos['via'] ?? '', [self::VIA_PSE, self::VIA_RECAUDO, self::VIA_WOMPI, self::VIA_WOMPI_PRUEBA, self::VIA_MANUAL], true)
            ? $datos['via']
            : self::VIA_RECAUDO;

        // mb_substr: con substr, cortar en medio de una "á" dejaba UTF-8
        // inválido y sqlsrv rechazaba el parámetro (revisión 2026-10-09).
        $banco = mb_substr(trim((string) ($datos['banco'] ?? '')), 0, self::LARGO_BANCO);

        /*
         * Si no viene fecha de pago se usa la de ahora. PSE es así: el pago
         * acaba de ocurrir. El recaudo bancario SÍ la trae, y es la de
         * ventanilla, que puede ser de días atrás.
         */
        $fechaPago = trim((string) ($datos['fechaPago'] ?? ''));
        if ($fechaPago === '') {
            // La hora de Colombia: con el servidor en UTC, un PSE pagado el día
            // límite después de las 7 p. m. quedaba del día siguiente (y el 31/12,
            // del año siguiente).
            $fechaPago = (new \DateTime('now', new \DateTimeZone('America/Bogota')))->format('Y-m-d H:i:s');
        }

        $anio = (int) date('Y', strtotime($fechaPago));

        /*
         * La guarda de _Pagado hace la operación idempotente: la notificación
         * del banco y el retorno del usuario llegan casi a la vez y las dos
         * intentan aplicar el mismo pago. Sin ella, la segunda pisaría la fecha
         * de la primera con una posterior.
         *
         * Y la de _Estado = 2 cumple AQUÍ la regla de la cabecera ("un pago solo
         * existe sobre una declaración presentada"), que hasta el 2026-09-28
         * dependía de que cada vía la revisara antes de llamar. PSE no lo hacía:
         * una corrección que heredaba la sesión de pago de la original quedaba
         * pagada en borrador por el cron. Un borrador no se marca, venga de
         * donde venga el pago; la llamada devuelve false y el pago queda para
         * quien lo concilie.
         */
        $con->consultar(
            "UPDATE $t
                SET {$p}_Pagado        = 1,
                    {$p}_FechaPago     = ?,
                    {$p}_FechaRealPago = GETDATE(),
                    {$p}_ValorPago     = ?,
                    {$p}_BancoPago     = ?,
                    {$p}_AnioPago      = ?,
                    {$p}_RutaPago      = ?
              WHERE $pk = ? AND ISNULL({$p}_Pagado, 0) = 0 AND {$p}_Estado = 2",
            [$fechaPago, $datos['valor'] ?? 0, $banco, $anio, $via, $idDeclaracion]
        );

        $fila = $con->obnerFila($con->consultar(
            "SELECT {$p}_RutaPago AS ruta FROM $t WHERE $pk = ?",
            [$idDeclaracion]
        ));

        // Fue esta llamada la que la marcó si la vía guardada es la suya.
        return isset($fila['ruta']) && $fila['ruta'] === $via;
    }

    /**
     * ¿La sesión de pago en línea que contestó el banco es de OTRA declaración?
     *
     * Cada sesión de PlacetoPay se crea con el número de la declaración como
     * referencia (extensiones/pse/crearSesion.php), y el banco la devuelve al
     * consultarla. Hasta el 2026-09-28 una corrección heredaba el requestId de
     * la original: el retorno, el webhook y el cron consultaban esa sesión
     * ajena y, si estaba aprobada, marcaban pagada la corrección con el pago de
     * la original. Ya no se copia, pero las correcciones creadas antes pueden
     * tenerlo; con esta comprobación una sesión solo se aplica a la
     * declaración cuyo número lleva.
     *
     * Si la respuesta no trae la referencia no hay con qué comparar y se
     * responde false: se aplica como siempre, en vez de dejar sin registrar un
     * pago bueno.
     */
    public static function sesionDeOtraDeclaracion(array $respuesta, $numero)
    {
        $referencia = $respuesta['request']['payment']['reference']
                   ?? $respuesta['payment'][0]['reference']
                   ?? null;

        if ($referencia === null || trim((string) $referencia) === '') { return false; }

        return trim((string) $referencia) !== trim((string) $numero);
    }

    /**
     * Le quita a una declaración SIN PAGAR la sesión de pago en línea que no es
     * suya (ver sesionDeOtraDeclaracion): el requestId y lo que se anotó de él.
     *
     * Con esa sesión encima, el resumen de pago la mostraba "en proceso" si la
     * ajena estaba pendiente, y el cron la volvía a consultar cada hora. Sin
     * ella, el contribuyente puede iniciar su propio pago. Una pagada no se
     * toca: su pago ya quedó registrado.
     */
    public static function olvidarSesionAjena($con, $idDeclaracion, $m = null)
    {
        require_once __DIR__ . '/class.pseModulo.php';
        if ($m === null) { $m = \erpsoftsas\PseModulo::get('ica'); }

        // Tabla y columnas salen del mapa fijo de PseModulo, nunca del usuario.
        $con->consultar(
            "UPDATE {$m['tabla']}
                SET " . \erpsoftsas\PseModulo::colRequestId($m)   . " = NULL,
                    " . \erpsoftsas\PseModulo::colEstado($m)      . " = NULL,
                    " . \erpsoftsas\PseModulo::colFechaEstado($m) . " = NULL,
                    " . \erpsoftsas\PseModulo::colMensaje($m)     . " = NULL
              WHERE {$m['pk']} = ? AND ISNULL({$m['pagado']}, 0) = 0",
            [(int) $idDeclaracion]
        );
    }
}
