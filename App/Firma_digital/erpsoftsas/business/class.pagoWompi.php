<?php

namespace erpsoftsas;

require_once __DIR__ . '/class.wompi.php';
require_once __DIR__ . '/class.pseModulo.php';
require_once __DIR__ . '/class.pasarela.php';
require_once __DIR__ . '/class.pagoDeclaracion.php';

/**
 * Los intentos de pago con Wompi (ind_pagos_en_linea, migración 042) y lo que
 * cada uno le hace a su declaración.
 *
 * POR QUÉ UN RENGLÓN POR INTENTO
 *
 * Wompi no deja reutilizar una referencia, así que cada vez que alguien pulsa
 * "Pagar en línea" nace un intento con la suya: "ICA2026000017-9f3a2c1b". El
 * módulo va adelante porque ICA, retención y autorretención numeran igual
 * (2026000001 existe en los tres). Un aviso de Wompi o un retorno se resuelve
 * por la referencia, sin adivinar a qué declaración va, y queda rastro de cada
 * intento: quién lo inició, cuánto se cobró, en qué ambiente y qué contestó Wompi.
 *
 * LO QUE SE PROTEGE (revisión del 2026-10-02, tres revisores)
 *
 *  - Solo se aplica una transacción que vino de una fuente confiable: un aviso
 *    firmado, la búsqueda por referencia de NUESTRO comercio (con la llave
 *    privada) o el id que ya guardó una de esas dos. El id que trae la URL del
 *    retorno no basta: podría ser de otro comercio con la misma referencia.
 *  - Todo se aplica dentro de una transacción con un candado por declaración:
 *    el aviso, el retorno y el cron llegan casi a la vez. El pago se registra
 *    ANTES de marcar el intento; si algo falla, no queda ninguno de los dos y
 *    el próximo intento (cron, reintento de Wompi) lo vuelve a hacer.
 *  - Valor y moneda tienen que ser los del intento (los cubre la firma de
 *    integridad): si no cuadran, el pago no se registra solo, queda REVISAR.
 *  - Un estado cerrado no vuelve a "en trámite" (un aviso viejo que llega tarde).
 *  - En el checkout se puede reintentar con la misma referencia: un rechazo de
 *    OTRA transacción no pisa un pago aprobado, y otra aprobada es un segundo
 *    pago que se anota para devolver. Un pago anulado después de registrado
 *    queda REVISAR (la declaración sigue pagada hasta que alguien lo mire).
 *  - El estado que ve la declaración lo cambia el intento más reciente o uno
 *    aprobado: un rechazo viejo no tapa un pago en trámite.
 *  - Si se aprueba un SEGUNDO pago de una declaración ya pagada (dos pestañas,
 *    el banco por recaudo), no se registra dos veces: queda DOBLE para devolverlo.
 *  - Un intento recién creado cuenta como "en trámite" mientras su enlace pueda
 *    pagarse: así no se crea otro y se paga dos veces sin querer.
 */
class PagoWompi
{
    const PREFIJOS = ['ica' => 'ICA', 'reteica' => 'RET', 'autorreteica' => 'AUT'];

    /**
     * Estados del intento que ya no cambian solos. Además de los finales de
     * Wompi: REVISAR (aprobado con otro valor, anulado después de registrado o
     * aprobado sin poder registrarse), DOBLE (aprobado sobre una declaración ya
     * pagada: hay que devolverlo) y SIN_PAGO (nunca se pagó).
     */
    const CERRADOS = ['APPROVED', 'DECLINED', 'VOIDED', 'ERROR', 'REVISAR', 'DOBLE', 'SIN_PAGO'];

    /** Horas que Wompi sigue reintentando un aviso (30 min, 3 h y 24 h) más un margen. */
    const HORAS_AVISOS = 26;

    /** Si Wompi no deja preguntar, un intento sin transacción se cierra a los 7 días. */
    const HORAS_MAXIMAS = 168;

    /** Minutos en que un intento recién creado puede estar pagándose: el enlace vale 30. */
    const MINUTOS_RECIENTE = 45;

    /** Intentos por declaración en una hora: más que eso es un error o un abuso. */
    const MAX_INTENTOS_HORA = 5;

    /** Crea un intento y devuelve su referencia. */
    public static function crearIntento($con, array $m, $idDeclaracion, $numero, $centavos, $intereses, $idUsuario)
    {
        $referencia = self::PREFIJOS[$m['clave']] . preg_replace('/\D/', '', (string) $numero)
                    . '-' . bin2hex(random_bytes(4));

        // Los centavos van como texto: pasan de 2^31 desde $21.474.837 y el
        // driver podría mandarlos como INT.
        $con->consultar(
            "INSERT INTO ind_pagos_en_linea
                    (pel_Pasarela, pel_Modulo, pel_IdDeclaracion, pel_Referencia, pel_Centavos, pel_Intereses,
                     pel_IdUsuario, pel_Ambiente)
             VALUES ('WOMPI', ?, ?, ?, CAST(? AS BIGINT), ?, ?, ?)",
            [$m['clave'], (int) $idDeclaracion, $referencia, (string) (int) $centavos, (float) $intereses,
             ((int) $idUsuario) > 0 ? (int) $idUsuario : null, Wompi::ambiente()]
        );
        return $referencia;
    }

    public static function intento($con, $referencia)
    {
        $f = $con->obnerFila($con->consultar(
            "SELECT * FROM ind_pagos_en_linea WHERE pel_Referencia = ? AND pel_Pasarela = 'WOMPI'",
            [(string) $referencia]
        ));
        return $f ?: null;
    }

    /** El intento PENDIENTE más reciente de la declaración: un pago en trámite. */
    public static function pendiente($con, $modulo, $idDeclaracion)
    {
        $f = $con->obnerFila($con->consultar(
            "SELECT TOP 1 * FROM ind_pagos_en_linea
              WHERE pel_Pasarela = 'WOMPI' AND pel_Modulo = ? AND pel_IdDeclaracion = ? AND pel_Estado = 'PENDING'
              ORDER BY pel_Id DESC",
            [(string) $modulo, (int) $idDeclaracion]
        ));
        return $f ?: null;
    }

    /**
     * ¿Hay un pago en trámite? Un intento PENDING, o uno creado hace menos de
     * MINUTOS_RECIENTE cuyo enlace todavía puede estar pagándose (el
     * contribuyente cerró la pestaña sin volver). A esos se les pregunta a
     * Wompi primero; si Wompi no contesta, por las dudas cuentan como en trámite.
     *
     * @return array|null el intento en trámite, con 'minutos' desde su creación
     */
    public static function enTramite($con, $modulo, $idDeclaracion)
    {
        $p = self::pendiente($con, $modulo, $idDeclaracion);
        if ($p) { return $p; }

        $st = $con->consultar(
            "SELECT *, DATEDIFF(MINUTE, pel_FechaCreacion, SYSDATETIME()) AS minutos
               FROM ind_pagos_en_linea
              WHERE pel_Pasarela = 'WOMPI' AND pel_Modulo = ? AND pel_IdDeclaracion = ? AND pel_Estado = 'CREADO'
                AND pel_FechaCreacion > DATEADD(MINUTE, -" . self::MINUTOS_RECIENTE . ", SYSDATETIME())
              ORDER BY pel_Id DESC",
            [(string) $modulo, (int) $idDeclaracion]
        );
        $recientes = [];
        while ($f = $con->obnerFila($st)) { $recientes[] = $f; }

        foreach ($recientes as $i) {
            try {
                $r = self::confirmar($con, $i);
            } catch (\Exception $e) {
                error_log('[wompi] ' . $i['pel_Referencia'] . ': no se pudo preguntar a Wompi: ' . $e->getMessage());
                return $i;
            }
            if ($r !== null && in_array($r['estado'], ['PENDING', 'APPROVED'], true)) {
                return self::intento($con, $i['pel_Referencia']) + ['minutos' => $i['minutos']];
            }
        }
        return null;
    }

    /** Intentos de la declaración en la última hora. */
    public static function intentosUltimaHora($con, $modulo, $idDeclaracion)
    {
        $f = $con->obnerFila($con->consultar(
            "SELECT COUNT(*) AS n FROM ind_pagos_en_linea
              WHERE pel_Pasarela = 'WOMPI' AND pel_Modulo = ? AND pel_IdDeclaracion = ?
                AND pel_FechaCreacion > DATEADD(HOUR, -1, SYSDATETIME())",
            [(string) $modulo, (int) $idDeclaracion]
        ));
        return (int) ($f['n'] ?? 0);
    }

    /**
     * Firma del enlace de retorno: HMAC de módulo, declaración y referencia con
     * el secreto de integridad (que nunca sale del servidor). Sin ella, el
     * retorno no consulta nada ni muestra datos: nadie puede recorrer
     * declaraciones ajenas ni gastar la llave privada desde afuera.
     */
    public static function firmaRetorno($modulo, $idDeclaracion, $referencia)
    {
        $secreto = Wompi::secretoIntegridad();
        if ($secreto === null) { return ''; }
        return hash_hmac('sha256', $modulo . '|' . (int) $idDeclaracion . '|' . $referencia, $secreto);
    }

    public static function retornoValido($modulo, $idDeclaracion, $referencia, $firma)
    {
        $esperada = self::firmaRetorno($modulo, $idDeclaracion, $referencia);
        return $esperada !== '' && is_string($firma) && hash_equals($esperada, $firma);
    }

    /** La dirección de retorno (sin esquema ni dominio si $base es ''). */
    public static function urlRetorno($base, $modulo, $idDeclaracion, $referencia)
    {
        return $base . 'retorno.php?pasarela=wompi&modulo=' . rawurlencode($modulo) . '&decl=' . (int) $idDeclaracion
             . '&ref=' . rawurlencode($referencia) . '&t=' . self::firmaRetorno($modulo, $idDeclaracion, $referencia);
    }

    /**
     * El correo para el comprobante de Wompi: el REGISTRADO del contribuyente
     * (el de notificación, o el del representante), nunca el de quien opera. Solo
     * el correo: el enlace de pago queda en el historial del navegador, y el
     * documento o el nombre pueden ser de otra persona (el contador que paga).
     */
    public static function cliente($con, array $m, $idDeclaracion)
    {
        // Tabla y columnas salen del mapa fijo de PseModulo, nunca del usuario.
        $c = $con->obnerFila($con->consultar(
            "SELECT c.ind_Email, c.ind_Email_representante
               FROM {$m['tabla']} d
               JOIN ind_contribuyentes c ON c.ind_Id = d.{$m['prefijo']}_IdContribuyente
              WHERE d.{$m['pk']} = ?",
            [(int) $idDeclaracion]
        ));
        if (!$c) { return []; }

        foreach (['ind_Email', 'ind_Email_representante'] as $col) {
            $email = trim((string) ($c[$col] ?? ''));
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) { return ['email' => $email]; }
        }
        return [];
    }

    /**
     * Pregunta a Wompi por un intento y aplica lo que encuentre.
     *
     * Primero busca por la referencia (con la llave privada: solo trae
     * transacciones de NUESTRO comercio); si Wompi no deja buscar, consulta el
     * id que ya se había guardado de una fuente confiable. Con varias
     * transacciones (reintentos en el checkout), manda la aprobada; si no hay,
     * la más reciente.
     *
     * @return array|null el resultado de aplicar(), o null si Wompi no tiene ninguna
     * @throws \Exception si no se pudo preguntar (ni por referencia ni por id)
     */
    public static function confirmar($con, array $intento)
    {
        $txs = [];
        try {
            $txs = Wompi::transaccionesDeReferencia($intento['pel_Referencia']);
        } catch (\Exception $e) {
            if (empty($intento['pel_Transaccion'])) { throw $e; }
            $tx = Wompi::consultarTransaccion($intento['pel_Transaccion']);
            if (!$tx) {
                throw new \Exception('Wompi no encuentra la transacción ' . $intento['pel_Transaccion']
                                   . ' (¿llave privada correcta?).');
            }
            $txs = [$tx];
        }
        if (!$txs) { return null; }

        usort($txs, function ($a, $b) {
            $pa = (($a['status'] ?? '') === 'APPROVED') ? 1 : 0;
            $pb = (($b['status'] ?? '') === 'APPROVED') ? 1 : 0;
            return $pb <=> $pa ?: strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        });
        return self::aplicar($con, $txs[0]);
    }

    /**
     * Aplica a su intento y a su declaración lo que Wompi dice de una
     * transacción. $tx tiene que salir de una fuente confiable (ver la cabecera).
     *
     * @return array intento (fila, o null si la referencia no es de este
     *               sistema), info (Wompi::interpretar), estado (el que quedó en
     *               el intento), pagada (si la declaración quedó pagada por este
     *               pago) y doble (un segundo pago que hay que devolver)
     */
    public static function aplicar($con, array $tx)
    {
        $info    = Wompi::interpretar($tx);
        $intento = $info['referencia'] !== '' ? self::intento($con, $info['referencia']) : null;
        $res     = ['intento' => $intento, 'info' => $info, 'estado' => $info['estado'], 'pagada' => false, 'doble' => false];

        // Otra transacción del mismo comercio (p. ej. otro sistema de la Alcaldía
        // que use la misma cuenta de Wompi): no es de aquí.
        if (!$intento) { return $res; }

        $m  = PseModulo::get($intento['pel_Modulo']);
        $id = (int) $intento['pel_IdDeclaracion'];

        $con->begin();
        try {
            if (!self::_candado($con, $m['clave'], $id)) {
                throw new \Exception('No se obtuvo el candado de la declaración ' . $m['clave'] . ' ' . $id . '.');
            }
            // Se relee DENTRO del candado: otro proceso pudo cambiarlo.
            $intento = self::intento($con, $info['referencia']);
            $res = self::_aplicar($con, $intento, $info, $m, $id);
            $con->commit();
        } catch (\Throwable $e) {
            try { $con->rollback(); } catch (\Throwable $e2) { /* ya se perdió la conexión */ }
            throw ($e instanceof \Exception) ? $e : new \Exception($e->getMessage());
        }
        return $res;
    }

    /** Un pago a la vez por declaración (se suelta con el commit o el rollback). */
    private static function _candado($con, $modulo, $id)
    {
        // Módulo del mapa fijo y id entero: seguro dentro de la cadena.
        $recurso = 'erp_pago_' . preg_replace('/[^a-z]/', '', $modulo) . '_' . (int) $id;
        $f = $con->obnerFila($con->consultar(
            "SET NOCOUNT ON;
             DECLARE @r INT;
             EXEC @r = sp_getapplock @Resource = '$recurso', @LockMode = 'Exclusive',
                                     @LockOwner = 'Transaction', @LockTimeout = 20000;
             SELECT @r AS r;"
        ));
        return isset($f['r']) && (int) $f['r'] >= 0;
    }

    private static function _aplicar($con, array $intento, array $info, array $m, $id)
    {
        $res = ['intento' => $intento, 'info' => $info, 'estado' => $info['estado'], 'pagada' => false, 'doble' => false];
        $ref      = $intento['pel_Referencia'];
        $anterior = (string) $intento['pel_Estado'];
        $txActual = trim((string) ($intento['pel_Transaccion'] ?? ''));
        $mismaTx  = $txActual === '' || $txActual === $info['id'];
        $cuadra   = $info['moneda'] === Wompi::MONEDA && $info['centavos'] === (int) $intento['pel_Centavos'];

        // Un estado cerrado no vuelve a "en trámite".
        if (in_array($anterior, self::CERRADOS, true) && !$info['final']) {
            $res['estado'] = $anterior;
            return $res;
        }

        // Pagado (o en revisión) con OTRA transacción de la misma referencia: un
        // rechazo de otra no lo pisa; otra aprobada es un segundo pago.
        if (in_array($anterior, ['APPROVED', 'DOBLE', 'REVISAR'], true) && !$mismaTx) {
            $res['estado'] = $anterior;
            if ($info['aprobado']) {
                $res['doble'] = true;
                self::_anotar($con, $intento, 'Otra transacción aprobada (' . $info['id'] . ') con la misma '
                                            . 'referencia: revisar la devolución.');
            }
            return $res;
        }

        $estado  = $info['estado'];
        $mensaje = $info['mensaje'];

        // La misma transacción, ya registrada, cambió (p. ej. la anularon).
        if ($anterior === 'APPROVED' && $estado !== 'APPROVED') {
            $estado  = 'REVISAR';
            $mensaje = 'Wompi pasó a ' . $info['estado'] . ' un pago ya registrado; la declaración sigue pagada: revisar.';
            error_log('[wompi] ' . $ref . ': ' . $mensaje);
            self::_guardar($con, $intento, $estado, $info, $mensaje);
            $res['estado'] = $estado;
            return $res;
        }

        $aprobado = $info['aprobado'] && $cuadra;
        if ($info['aprobado'] && !$cuadra) {
            $estado  = 'REVISAR';
            $mensaje = 'Wompi aprobó ' . $info['centavos'] . ' centavos ' . $info['moneda'] . ' y el intento era por '
                     . $intento['pel_Centavos'] . ' centavos COP. No se registró el pago: revisar a mano.';
            error_log('[wompi] ' . $ref . ': ' . $mensaje);
        }

        // ¿Ya estaba pagada por OTRO medio (el banco, PSE) u OTRO intento? Es un
        // segundo pago: no se registra otra vez, se anota para devolverlo. Si la
        // pagó este mismo intento (se vuelve a procesar), no es doble.
        if ($aprobado) {
            $decl = $con->obnerFila($con->consultar(
                "SELECT ISNULL({$m['pagado']}, 0) AS pagado, {$m['prefijo']}_RutaPago AS ruta
                   FROM {$m['tabla']} WHERE {$m['pk']} = ?", [$id]
            ));
            if ((int) ($decl['pagado'] ?? 0) === 1) {
                $otro = $con->obnerFila($con->consultar(
                    "SELECT COUNT(*) AS n FROM ind_pagos_en_linea
                      WHERE pel_Modulo = ? AND pel_IdDeclaracion = ? AND pel_Id <> ? AND pel_Estado = 'APPROVED'",
                    [$m['clave'], $id, (int) $intento['pel_Id']]
                ));
                $porWompi = in_array(trim((string) ($decl['ruta'] ?? '')),
                                     [PagoDeclaracion::VIA_WOMPI, PagoDeclaracion::VIA_WOMPI_PRUEBA], true);
                if (!$porWompi || (int) ($otro['n'] ?? 0) > 0) {
                    // DOBLE y no APPROVED: así el intento que sí pagó no se ve a
                    // sí mismo como "otro aprobado" si se vuelve a procesar.
                    $mensaje = 'Pago aprobado en Wompi sobre una declaración que ya estaba pagada: revisar la devolución.';
                    error_log('[wompi] ' . $ref . ': ' . $mensaje);
                    self::_guardar($con, $intento, 'DOBLE', $info, $mensaje);
                    $res['estado'] = 'DOBLE';
                    $res['doble']  = true;
                    return $res;
                }
            }
        }

        // El estado de la declaración lo cambia el intento más reciente, o uno aprobado.
        $ultimo = $con->obnerFila($con->consultar(
            "SELECT MAX(pel_Id) AS u FROM ind_pagos_en_linea WHERE pel_Modulo = ? AND pel_IdDeclaracion = ?",
            [$m['clave'], $id]
        ));
        $pagada = false;
        if ($aprobado || (int) ($ultimo['u'] ?? 0) === (int) $intento['pel_Id']) {
            $infoDecl = ['estado' => $estado, 'mensaje' => $mensaje, 'aprobado' => $aprobado,
                         'valor' => $info['valor'], 'banco' => $info['banco'], 'fechaPago' => self::_fechaColombia($info['fecha'])];
            // Primero el pago y después el intento: si algo falla, el rollback
            // deshace los dos y el próximo intento lo vuelve a hacer.
            $pagada = Pasarela::aplicarADeclaracion($con, $id, $infoDecl, $info['valor'], $m, Wompi::via());

            if ($aprobado && !$pagada) {
                $estado  = 'REVISAR';
                $mensaje = 'Aprobado en Wompi, pero la declaración no quedó registrada como pagada por este pago '
                         . '(no está presentada o ya estaba pagada): revisar.';
                error_log('[wompi] ' . $ref . ': ' . $mensaje);
            }
        }

        self::_guardar($con, $intento, $estado, $info, $mensaje);
        $res['estado'] = $estado;
        $res['pagada'] = $pagada && $aprobado;
        return $res;
    }

    private static function _guardar($con, array $intento, $estado, array $info, $mensaje)
    {
        $con->consultar(
            "UPDATE ind_pagos_en_linea
                SET pel_Estado = ?, pel_Transaccion = COALESCE(?, pel_Transaccion), pel_Medio = COALESCE(?, pel_Medio),
                    pel_Mensaje = ?, pel_FechaEstado = SYSDATETIME()
              WHERE pel_Id = ?",
            [$estado, $info['id'] !== '' ? $info['id'] : null, $info['medio'] !== '' ? $info['medio'] : null,
             mb_substr((string) $mensaje, 0, 300), (int) $intento['pel_Id']]
        );
    }

    private static function _anotar($con, array $intento, $nota)
    {
        error_log('[wompi] ' . $intento['pel_Referencia'] . ': ' . $nota);
        $con->consultar("UPDATE ind_pagos_en_linea SET pel_Mensaje = ?, pel_FechaEstado = SYSDATETIME() WHERE pel_Id = ?",
                        [mb_substr($nota, 0, 300), (int) $intento['pel_Id']]);
    }

    /** La fecha en que Wompi cerró la transacción, en hora de Colombia; null si no vino. */
    private static function _fechaColombia($iso)
    {
        if (trim((string) $iso) === '') { return null; }
        try {
            return (new \DateTime((string) $iso))->setTimezone(new \DateTimeZone('America/Bogota'))->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Un aviso de Wompi (POST a extensiones/pse/wompi_eventos.php).
     *
     * 401 si la firma no es de Wompi; 200 para todo lo que no hay que repetir
     * (aplicado, de otra referencia, otro tipo de evento); 500 si no se pudo
     * confirmar con Wompi, para que lo reintente (a los 30 min, 3 h y 24 h).
     *
     * @return array [código HTTP, cuerpo de la respuesta]
     */
    public static function procesarEvento($con, $cuerpo, $cabecera = null)
    {
        $evento = json_decode((string) $cuerpo, true);
        if (!is_array($evento)) {
            return [400, ['ok' => false, 'mensaje' => 'Aviso no válido']];
        }
        if (!Wompi::validarEvento($evento, $cabecera)) {
            return [401, ['ok' => false, 'mensaje' => 'Firma inválida']];
        }
        if (($evento['event'] ?? '') !== 'transaction.updated') {
            return [200, ['ok' => true, 'mensaje' => 'Evento sin efecto aquí']];
        }

        $txAviso = $evento['data']['transaction'] ?? [];
        $ref     = (string) ($txAviso['reference'] ?? '');
        if ($ref === '' || !self::intento($con, $ref)) {
            return [200, ['ok' => true, 'mensaje' => 'Referencia no asociada a ninguna declaración']];
        }

        try {
            // El aviso viene firmado por Wompi (es de nuestro comercio), pero lo
            // que se guarda sale de la consulta, no de su cuerpo.
            $tx = Wompi::consultarTransaccion($txAviso['id'] ?? '');
            if (!$tx || (string) ($tx['reference'] ?? '') !== $ref) {
                return [500, ['ok' => false, 'mensaje' => 'Wompi todavía no confirma la transacción']];
            }
            $r = self::aplicar($con, $tx);
            return [200, ['ok' => true, 'estado' => $r['estado'], 'pagada' => $r['pagada']]];
        } catch (\Exception $e) {
            error_log('[wompi eventos] ' . $ref . ': ' . $e->getMessage());
            return [500, ['ok' => false, 'mensaje' => 'No se pudo confirmar con Wompi']];
        }
    }

    /**
     * El cron de respaldo: revisa los intentos que siguen abiertos (CREADO o
     * PENDING) y los rechazados de las últimas 26 horas (en el checkout se
     * puede reintentar con la misma referencia, y el aviso del aprobado pudo
     * perderse).
     *
     * Un intento SIN transacción se cierra como SIN_PAGO solo si Wompi contestó
     * la búsqueda sin nada pasadas HORAS_AVISOS; si Wompi no deja preguntar,
     * pasadas HORAS_MAXIMAS. Uno CON transacción nunca se cierra por no
     * encontrarla: eso es una llave equivocada, no un pago que no existió.
     *
     * @param callable $eco recibe una línea de texto por intento (para el cron)
     * @return array ['revisados' => n, 'pagados' => n]
     */
    public static function revisarAbiertos($con, callable $eco)
    {
        $abiertos = [];
        $st = $con->consultar(
            "SELECT *, DATEDIFF(HOUR, pel_FechaCreacion, SYSDATETIME()) AS horas
               FROM ind_pagos_en_linea
              WHERE pel_Pasarela = 'WOMPI'
                AND (pel_Estado IN ('CREADO', 'PENDING')
                     OR (pel_Estado IN ('DECLINED', 'ERROR')
                         AND pel_FechaCreacion > DATEADD(HOUR, -" . self::HORAS_AVISOS . ", SYSDATETIME())))
              ORDER BY pel_Id",
            []
        );
        while ($f = $con->obnerFila($st)) { $abiertos[] = $f; }

        $revisados = 0;
        $pagados   = 0;
        foreach ($abiertos as $f) {
            $revisados++;
            $ref     = $f['pel_Referencia'];
            $cerrado = in_array($f['pel_Estado'], ['DECLINED', 'ERROR'], true);
            try {
                $r = self::confirmar($con, $f);
                if ($r === null) {
                    if (!$cerrado && empty($f['pel_Transaccion']) && (int) $f['horas'] >= self::HORAS_AVISOS) {
                        self::_cerrarSinPago($con, $f, 'Wompi no tiene ningún pago con esta referencia.');
                        $eco("$ref: sin pago en Wompi; se cerró.");
                    } elseif (!$cerrado) {
                        $eco("$ref: todavía sin transacción.");
                    }
                    continue;
                }
                if ($r['pagada']) { $pagados++; }
                $eco("$ref: " . $r['estado'] . ($r['pagada'] ? ', pago registrado.' : '.'));
            } catch (\Exception $e) {
                if (!$cerrado && empty($f['pel_Transaccion']) && (int) $f['horas'] >= self::HORAS_MAXIMAS) {
                    self::_cerrarSinPago($con, $f, 'No se pudo confirmar con Wompi en 7 días; si llega un aviso de pago, se registra igual.');
                    $eco("$ref: sin respuesta de Wompi en 7 días; se cerró.");
                } else {
                    $eco("$ref: error al consultar - " . $e->getMessage());
                }
            }
        }

        // Lo que necesita a una persona: devoluciones y pagos que no cuadran.
        $st = $con->consultar(
            "SELECT pel_Referencia, pel_Estado, pel_Mensaje FROM ind_pagos_en_linea
              WHERE pel_Pasarela = 'WOMPI' AND pel_Estado IN ('DOBLE', 'REVISAR')
                AND pel_FechaEstado > DATEADD(DAY, -30, SYSDATETIME())
              ORDER BY pel_Id",
            []
        );
        while ($f = $con->obnerFila($st)) {
            $eco('ATENCIÓN ' . $f['pel_Referencia'] . ' (' . $f['pel_Estado'] . '): ' . $f['pel_Mensaje']);
        }

        return ['revisados' => $revisados, 'pagados' => $pagados];
    }

    private static function _cerrarSinPago($con, array $f, $mensaje)
    {
        // El aviso de un pago que llegue después igual se aplica: SIN_PAGO es
        // cerrado, pero un APPROVED (final) sí lo cambia.
        $con->consultar(
            "UPDATE ind_pagos_en_linea SET pel_Estado = 'SIN_PAGO', pel_FechaEstado = SYSDATETIME(), pel_Mensaje = ?
              WHERE pel_Id = ? AND pel_Estado IN ('CREADO', 'PENDING')",
            [$mensaje, (int) $f['pel_Id']]
        );
    }
}
