<?php

namespace erpsoftsas;

require_once __DIR__ . '/class.unocerouno.php';
require_once __DIR__ . '/class.pseModulo.php';
require_once __DIR__ . '/class.pasarela.php';
require_once __DIR__ . '/class.pagoDeclaracion.php';

/**
 * Los intentos de pago por 1cero1pay («PSE · Bancolombia», migración 060) y lo
 * que cada uno le hace a su declaración. Mismas reglas que el pago en línea de
 * siempre (class.pagoDeclaracion.php) y la misma mecánica de Wompi como
 * pasarela con intentos (class.pagoWompi.php).
 *
 * UN RENGLÓN POR INTENTO (ind_pagos_en_linea, pel_Pasarela = '1CERO1PAY')
 *
 * Cada vez que alguien pulsa «PSE · Bancolombia» nace un intento con su
 * FACTURA, el identificador único del pago en 1cero1pay (máximo 18 cifras):
 *
 *     <número de la declaración><módulo: 1 ICA, 2 retención, 3 autorretención><consecutivo de 3 cifras>
 *     2026000017 + 1 + 001  ->  20260000171001
 *
 * El módulo va dentro porque ICA, retención y autorretención numeran igual, y
 * el consecutivo porque cada intento se consulta por SU factura: así la
 * respuesta de 1cero1pay es de ese intento y de ningún otro. La Referencia que
 * ve 1cero1pay es el número de la declaración. El intento guarda además el
 * IDImpuesto con que se creó (se consulta con el mismo aunque después se
 * cambie el parámetro), lo que se cobró en centavos y los intereses de mora.
 *
 * Estados del intento: CREADO (anotado, todavía sin dirección de pago),
 * COMENZADA y PENDIENTE (los de 1cero1pay: abiertos), APROBADA, RECHAZADA y
 * FALLIDA (finales de 1cero1pay), y los de aquí: ERROR (1cero1pay no creó la
 * transacción), REVISAR (aprobado que no se registró solo: lo mira una
 * persona), DOBLE (aprobado sobre una declaración ya pagada: hay que
 * devolverlo) y SIN_PAGO (nunca se pagó).
 *
 * LO QUE SE PROTEGE
 *
 *  - Nada se toma de la URL: lo que se guarda sale de una consulta propia a
 *    1cero1pay, autenticada con las credenciales de la entidad.
 *  - La transacción tiene que ser DE ESA FACTURA y por el VALOR EXACTO del
 *    intento. Aprobada con otro valor, sin valor o de otra factura: no se
 *    registra, queda REVISAR.
 *  - Todo se aplica dentro de una transacción con el candado de la declaración
 *    (el mismo de Wompi y del pago manual): el retorno y la tarea llegan casi a
 *    la vez. El pago se registra ANTES de marcar el intento; si algo falla no
 *    queda ninguno de los dos y la próxima consulta lo vuelve a hacer.
 *  - Aprobar dos veces el mismo intento no registra dos veces
 *    (PagoDeclaracion::registrar solo marca una declaración sin pagar).
 *  - Un segundo pago aprobado de una declaración ya pagada (por el banco, por
 *    la otra pasarela, por otro intento) queda DOBLE para devolverlo.
 *  - Un estado cerrado no vuelve a "en trámite".
 *  - Un estado que 1cero1pay manda y no está en su documento no cambia nada: el
 *    intento queda como estaba, con el texto guardado, y la tarea lo avisa.
 *  - Nada frena para siempre: un intento recién iniciado deja de frenar a los
 *    45 minutos; uno sin respuesta útil de 1cero1pay se cierra pasadas 26
 *    horas; y un PENDIENTE de verdad, que sí frena otro pago EN LÍNEA hasta
 *    que el banco conteste, deja de frenar el pago MANUAL de la Alcaldía
 *    pasadas HORAS_PENDIENTE_FRENA_MANUAL (la tarea lo sigue avisando).
 *  - UN SOLO PAGO EN CURSO POR DECLARACIÓN, también entre pasarelas: con un
 *    intento abierto aquí no se inicia otro ni aquí ni en la pasarela de la
 *    entidad, y al revés (crearIntento y siLibre, con el candado).
 *
 * Este pago NO escribe las columnas *_PSE_* de la declaración: son de la
 * sesión de PlacetoPay (su requestId, su estado), que puede convivir.
 */
class PagoUnoCeroUno
{
    const PASARELA = UnoCeroUno::PASARELA;

    /** Dígito del módulo dentro de la factura. */
    const DIGITO_MODULO = ['ica' => '1', 'reteica' => '2', 'autorreteica' => '3'];

    const ABIERTOS = ['CREADO', 'COMENZADA', 'PENDIENTE'];
    const CERRADOS = ['APROBADA', 'RECHAZADA', 'FALLIDA', 'ERROR', 'REVISAR', 'DOBLE', 'SIN_PAGO'];

    /**
     * Minutos en que un intento recién iniciado (CREADO o COMENZADA) puede
     * estar pagándose: mientras tanto no se inicia otro. 1Cero1 no dice cuánto
     * vale su dirección de pago; es el mismo margen de Wompi.
     */
    const MINUTOS_RECIENTE = 45;

    /** Intentos por declaración en una hora: más que eso es un error o un abuso. */
    const MAX_INTENTOS_HORA = 5;

    /** Horas tras las que un intento que 1cero1pay sigue viendo sin empezar se cierra. */
    const HORAS_SIN_TERMINAR = 26;

    /** Horas en que la tarea vuelve a mirar un intento rechazado, por si acaso. */
    const HORAS_RECHAZADOS = 26;

    /** Horas tras las que un PENDIENTE sale en los avisos de la tarea. */
    const HORAS_AVISO_PENDIENTE = 48;

    /**
     * Horas que un PENDIENTE (el banco no ha contestado) frena el PAGO MANUAL
     * de la Alcaldía. Un PSE pendiente se resuelve en minutos u horas; pasado
     * este tiempo la Alcaldía puede registrar a mano un pago con su soporte
     * sin esperar a 1cero1pay. Si el pendiente termina aprobándose después,
     * queda DOBLE (para devolver), nunca registrado dos veces.
     */
    const HORAS_PENDIENTE_FRENA_MANUAL = 48;

    /* ------------------------------------------------------------------ */
    /* Lectura                                                             */
    /* ------------------------------------------------------------------ */

    /** El intento por su factura. */
    public static function intento($con, $factura)
    {
        $f = $con->obnerFila($con->consultar(
            "SELECT *, DATEDIFF(MINUTE, pel_FechaCreacion, SYSDATETIME()) AS minutos
               FROM ind_pagos_en_linea WHERE pel_Referencia = ? AND pel_Pasarela = '" . self::PASARELA . "'",
            [(string) $factura]
        ));
        return $f ?: null;
    }

    /**
     * Los intentos que todavía pueden terminar en pago, SIN preguntar a
     * 1cero1pay: los PENDIENTE (el banco no ha contestado) y los CREADO o
     * COMENZADA de menos de MINUTOS_RECIENTE. Más recientes primero.
     */
    public static function abiertosLocal($con, $modulo, $idDeclaracion)
    {
        $st = $con->consultar(
            "SELECT *, DATEDIFF(MINUTE, pel_FechaCreacion, SYSDATETIME()) AS minutos
               FROM ind_pagos_en_linea
              WHERE pel_Pasarela = '" . self::PASARELA . "' AND pel_Modulo = ? AND pel_IdDeclaracion = ?
                AND (pel_Estado = 'PENDIENTE'
                     OR (pel_Estado IN ('CREADO', 'COMENZADA')
                         AND pel_FechaCreacion > DATEADD(MINUTE, -" . self::MINUTOS_RECIENTE . ", SYSDATETIME())))
              ORDER BY pel_Id DESC",
            [(string) $modulo, (int) $idDeclaracion]
        );
        $r = [];
        while ($f = $con->obnerFila($st)) { $r[] = $f; }
        return $r;
    }

    /**
     * ¿Hay un pago en trámite por 1cero1pay? A los recién iniciados se les
     * pregunta primero a 1cero1pay (el contribuyente pudo pagar en otra pestaña
     * sin volver, o haberlo dejado); si no contesta, por las dudas cuentan como
     * en trámite. Un PENDIENTE lo es hasta que 1cero1pay diga otra cosa (lo
     * mira «Verificar estado del pago» y la tarea).
     *
     * @return array|null el intento en trámite, con 'minutos' desde su creación
     */
    public static function enTramite($con, $modulo, $idDeclaracion)
    {
        foreach (self::abiertosLocal($con, $modulo, $idDeclaracion) as $i) {
            if ($i['pel_Estado'] === 'PENDIENTE') { return $i; }
            try {
                self::confirmar($con, $i);
            } catch (\Exception $e) {
                error_log('[1cero1pay] ' . $i['pel_Referencia'] . ': no se pudo preguntar: ' . $e->getMessage());
                return $i;
            }
            $ahora = self::intento($con, $i['pel_Referencia']);
            if ($ahora && in_array($ahora['pel_Estado'], self::ABIERTOS, true)) { return $ahora; }
        }
        return null;
    }

    /**
     * ¿Hay un pago APROBADO por 1cero1pay que no se registró solo (REVISAR) y
     * la declaración sigue sin pagar? Mientras alguien lo mira no se ofrece
     * otro pago en línea: el contribuyente ya pagó una vez. El pago manual de
     * la Alcaldía no se frena: es justamente como se resuelve.
     *
     * @return array|null el intento en revisión
     */
    public static function enRevision($con, array $m, $idDeclaracion)
    {
        // Tabla y columnas salen del mapa fijo de PseModulo, nunca del usuario.
        $f = $con->obnerFila($con->consultar(
            "SELECT TOP 1 p.* FROM ind_pagos_en_linea p
               JOIN {$m['tabla']} d ON d.{$m['pk']} = p.pel_IdDeclaracion
              WHERE p.pel_Pasarela = '" . self::PASARELA . "' AND p.pel_Modulo = ? AND p.pel_IdDeclaracion = ?
                AND p.pel_Estado = 'REVISAR' AND ISNULL(d.{$m['pagado']}, 0) = 0
              ORDER BY p.pel_Id DESC",
            [$m['clave'], (int) $idDeclaracion]
        ));
        return $f ?: null;
    }

    /**
     * ¿Frena 1cero1pay el PAGO MANUAL de la Alcaldía sobre esta declaración?
     *
     * Sí mientras haya un intento que todavía puede terminar en pago: uno
     * recién iniciado (menos de MINUTOS_RECIENTE) o un PENDIENTE de menos de
     * HORAS_PENDIENTE_FRENA_MANUAL. Un PENDIENTE más viejo ya no frena (ver la
     * constante), y uno en REVISAR tampoco: el pago manual es como se resuelve.
     *
     * @param bool $preguntar false con el candado de la declaración ya tomado:
     *                        ahí no se habla con 1cero1pay (aplicar su respuesta
     *                        abriría otra transacción) y basta lo que hay en la base
     */
    public static function frenaPagoManual($con, $modulo, $idDeclaracion, $preguntar = true)
    {
        foreach (self::abiertosLocal($con, $modulo, $idDeclaracion) as $i) {
            if ($preguntar) {
                // Lo último que diga 1cero1pay: pudo aprobarse, rechazarse o seguir igual.
                try {
                    self::confirmar($con, $i);
                } catch (\Exception $e) {
                    error_log('[1cero1pay] ' . $i['pel_Referencia'] . ': no se pudo preguntar: ' . $e->getMessage());
                }
                $i = self::intento($con, $i['pel_Referencia']) ?: $i;
                if (!in_array($i['pel_Estado'], self::ABIERTOS, true)) { continue; }
            }
            if ($i['pel_Estado'] === 'PENDIENTE'
                && (int) ($i['minutos'] ?? 0) >= self::HORAS_PENDIENTE_FRENA_MANUAL * 60) {
                continue;
            }
            return true;
        }
        return false;
    }

    /** Intentos de la declaración en la última hora. */
    public static function intentosUltimaHora($con, $modulo, $idDeclaracion)
    {
        $f = $con->obnerFila($con->consultar(
            "SELECT COUNT(*) AS n FROM ind_pagos_en_linea
              WHERE pel_Pasarela = '" . self::PASARELA . "' AND pel_Modulo = ? AND pel_IdDeclaracion = ?
                AND pel_FechaCreacion > DATEADD(HOUR, -1, SYSDATETIME())",
            [(string) $modulo, (int) $idDeclaracion]
        ));
        return (int) ($f['n'] ?? 0);
    }

    /* ------------------------------------------------------------------ */
    /* Un solo pago en curso por declaración, entre pasarelas              */
    /* ------------------------------------------------------------------ */

    /** Un pago a la vez por declaración (se suelta con el commit o el rollback). */
    private static function _candado($con, $modulo, $id)
    {
        // El MISMO recurso que PagoWompi y RegistroManual. Módulo del mapa fijo
        // y id entero: seguro dentro de la cadena.
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

    /**
     * ¿Tiene la pasarela de la ENTIDAD (PlacetoPay o Wompi) un pago en curso de
     * esta declaración? Solo con lo que hay en la base (se llama con el candado
     * tomado: ahí no se habla con nadie).
     */
    public static function principalEnTramiteLocal($con, array $m, $idDeclaracion)
    {
        // PlacetoPay: sesión PENDING, o APPROVED sin registrar (PlacetoPay::enTramite).
        $d = $con->obnerFila($con->consultar(
            "SELECT " . PseModulo::colRequestId($m) . " AS req, " . PseModulo::colEstado($m) . " AS est
               FROM {$m['tabla']} WHERE {$m['pk']} = ?", [(int) $idDeclaracion]
        ));
        if ($d && !empty($d['req']) && in_array(strtoupper((string) $d['est']), ['PENDING', 'APPROVED'], true)) {
            return true;
        }
        // Wompi: un intento PENDING, o uno CREADO cuyo enlace todavía vale.
        $w = $con->obnerFila($con->consultar(
            "SELECT COUNT(*) AS n FROM ind_pagos_en_linea
              WHERE pel_Pasarela = 'WOMPI' AND pel_Modulo = ? AND pel_IdDeclaracion = ?
                AND (pel_Estado = 'PENDING'
                     OR (pel_Estado = 'CREADO' AND pel_FechaCreacion > DATEADD(MINUTE, -45, SYSDATETIME())))",
            [$m['clave'], (int) $idDeclaracion]
        ));
        return (int) ($w['n'] ?? 0) > 0;
    }

    /**
     * ¿Frena 1cero1pay un pago nuevo de esta declaración por OTRA vía en línea?
     * Con un intento abierto o uno aprobado en revisión, sí. Sin preguntar a
     * 1cero1pay.
     */
    public static function frenaOtroPago($con, array $m, $idDeclaracion)
    {
        return (bool) self::abiertosLocal($con, $m['clave'], $idDeclaracion)
            || self::enRevision($con, $m, $idDeclaracion) !== null;
    }

    /**
     * siLibre sin lanzar: lo usa el botón de la pasarela de SIEMPRE, que no
     * puede terminar en un error 500 porque 1cero1pay esté configurado y el
     * candado tarde o la base falle (revisión 2026-11-22). El detalle va al
     * registro.
     *
     * @return array ['ok' => true, 'valor' => lo de siLibre (null = 1cero1pay tiene un pago en curso)]
     *               o ['ok' => false, 'valor' => null] si no se pudo comprobar
     */
    public static function intentarSiLibre($con, array $m, $idDeclaracion, callable $accion)
    {
        try {
            return ['ok' => true, 'valor' => self::siLibre($con, $m, $idDeclaracion, $accion)];
        } catch (\Throwable $e) {
            error_log('[1cero1pay] ' . $m['clave'] . ' ' . (int) $idDeclaracion . ': no se pudo comprobar si hay un pago en curso: ' . $e->getMessage());
            return ['ok' => false, 'valor' => null];
        }
    }

    /**
     * Para la pasarela de la entidad: hace $accion (anotar su sesión, crear su
     * intento) solo si 1cero1pay no tiene un pago en curso de la declaración, y
     * con el candado tomado, para que dos pestañas -una por cada pasarela- no
     * pasen a la vez.
     *
     * @return mixed lo que devuelva $accion, o null si 1cero1pay tiene un pago en curso
     */
    public static function siLibre($con, array $m, $idDeclaracion, callable $accion)
    {
        $con->begin();
        try {
            if (!self::_candado($con, $m['clave'], $idDeclaracion)) {
                throw new \Exception('No se obtuvo el candado de la declaración ' . $m['clave'] . ' ' . (int) $idDeclaracion . '.');
            }
            if (self::frenaOtroPago($con, $m, $idDeclaracion)) {
                $con->rollback();
                return null;
            }
            $r = $accion();
            $con->commit();
            return $r;
        } catch (\Throwable $e) {
            try { $con->rollback(); } catch (\Throwable $e2) { /* ya se perdió la conexión */ }
            throw ($e instanceof \Exception) ? $e : new \Exception($e->getMessage());
        }
    }

    /* ------------------------------------------------------------------ */
    /* Crear un intento                                                    */
    /* ------------------------------------------------------------------ */

    /**
     * Anota un intento (CREADO) si la declaración puede pagarse y no tiene otro
     * pago en curso por ninguna pasarela. Todo con el candado tomado.
     *
     * @return array ['ok' => true, 'intento' => fila]  o
     *               ['ok' => false, 'motivo' => 'tramite' | 'limite' | 'estado' | 'numero']
     */
    public static function crearIntento($con, array $m, $idDeclaracion, $centavos, $intereses, $idUsuario)
    {
        $idDeclaracion = (int) $idDeclaracion;
        $idImpuesto    = UnoCeroUno::idImpuesto($m['clave']);

        $con->begin();
        try {
            if (!self::_candado($con, $m['clave'], $idDeclaracion)) {
                throw new \Exception('No se obtuvo el candado de la declaración ' . $m['clave'] . ' ' . $idDeclaracion . '.');
            }
            $no = function ($motivo) use ($con) {
                $con->rollback();
                return ['ok' => false, 'motivo' => $motivo];
            };

            // Se relee DENTRO del candado: presentada, sin pagar y con número.
            $d = $con->obnerFila($con->consultar(
                "SELECT {$m['numero']} AS numero, ISNULL({$m['pagado']}, 0) AS pagado, ISNULL({$m['estado']}, 0) AS estado
                   FROM {$m['tabla']} WHERE {$m['pk']} = ?", [$idDeclaracion]
            ));
            if (!$d || (int) $d['pagado'] === 1 || (int) $d['estado'] !== 2) { return $no('estado'); }

            if (self::principalEnTramiteLocal($con, $m, $idDeclaracion) || self::frenaOtroPago($con, $m, $idDeclaracion)) {
                return $no('tramite');
            }
            if (self::intentosUltimaHora($con, $m['clave'], $idDeclaracion) >= self::MAX_INTENTOS_HORA) {
                return $no('limite');
            }

            $n = $con->obnerFila($con->consultar(
                "SELECT COUNT(*) AS n FROM ind_pagos_en_linea
                  WHERE pel_Pasarela = '" . self::PASARELA . "' AND pel_Modulo = ? AND pel_IdDeclaracion = ?",
                [$m['clave'], $idDeclaracion]
            ));
            $factura = self::factura($m['clave'], $d['numero'], (int) ($n['n'] ?? 0) + 1);
            if ($factura === null || $idImpuesto === null) { return $no('numero'); }

            // Los centavos van como texto: pasan de 2^31 desde $21.474.837 y el
            // driver podría mandarlos como INT.
            $con->consultar(
                "INSERT INTO ind_pagos_en_linea
                        (pel_Pasarela, pel_Modulo, pel_IdDeclaracion, pel_Referencia, pel_Centavos, pel_Intereses,
                         pel_IdUsuario, pel_Ambiente, pel_IdImpuesto)
                 VALUES ('" . self::PASARELA . "', ?, ?, ?, CAST(? AS BIGINT), ?, ?, 'prod', ?)",
                [$m['clave'], $idDeclaracion, $factura, (string) (int) $centavos, (float) $intereses,
                 ((int) $idUsuario) > 0 ? (int) $idUsuario : null, (int) $idImpuesto]
            );
            $intento = self::intento($con, $factura);
            $con->commit();
            return ['ok' => true, 'intento' => $intento + ['numero' => (string) $d['numero']]];
        } catch (\Throwable $e) {
            try { $con->rollback(); } catch (\Throwable $e2) { /* ya se perdió la conexión */ }
            throw ($e instanceof \Exception) ? $e : new \Exception($e->getMessage());
        }
    }

    /**
     * La factura de un intento (ver la cabecera), o null si no cabe en las 18
     * cifras de 1cero1pay o el número de la declaración no es un número.
     */
    public static function factura($modulo, $numero, $consecutivo)
    {
        $numero = trim((string) $numero);
        $digito = self::DIGITO_MODULO[$modulo] ?? null;
        if ($digito === null || !ctype_digit($numero) || (int) $consecutivo < 1 || (int) $consecutivo > 999) { return null; }
        $f = $numero . $digito . sprintf('%03d', (int) $consecutivo);
        return strlen($f) <= 18 ? $f : null;
    }

    /** 1cero1pay creó la transacción: el intento pasa a COMENZADA con su id. */
    public static function marcarIniciado($con, $factura, $idTransaccion)
    {
        $con->consultar(
            "UPDATE ind_pagos_en_linea
                SET pel_Estado = 'COMENZADA', pel_Transaccion = ?, pel_FechaEstado = SYSDATETIME(),
                    pel_Mensaje = 'Transacción creada: esperando el pago'
              WHERE pel_Referencia = ? AND pel_Pasarela = '" . self::PASARELA . "' AND pel_Estado = 'CREADO'",
            [trim((string) $idTransaccion) !== '' ? mb_substr(trim((string) $idTransaccion), 0, 60) : null, (string) $factura]
        );
    }

    /** 1cero1pay no creó la transacción: el intento se cierra para poder reintentar. */
    public static function marcarError($con, $factura, $mensaje)
    {
        $con->consultar(
            "UPDATE ind_pagos_en_linea
                SET pel_Estado = 'ERROR', pel_FechaEstado = SYSDATETIME(), pel_Mensaje = ?
              WHERE pel_Referencia = ? AND pel_Pasarela = '" . self::PASARELA . "' AND pel_Estado = 'CREADO'",
            [mb_substr((string) $mensaje, 0, 300), (string) $factura]
        );
    }

    /**
     * El pagador que se le informa a 1cero1pay: el CONTRIBUYENTE de la
     * declaración (documento, nombre, correo y teléfono registrados), no quien
     * opera. El correo, el de notificación o el del representante.
     */
    public static function pagador($con, array $m, $idDeclaracion)
    {
        // Tabla y columnas salen del mapa fijo de PseModulo, nunca del usuario.
        $c = $con->obnerFila($con->consultar(
            "SELECT c.ind_NumeroIdentificacion, c.ind_IdTipoDocumento, c.ind_PrimerNombre, c.ind_SegundoNombre,
                    c.ind_PrimerApellido, c.ind_SegundoApellido, c.ind_Telefono, c.ind_Email, c.ind_Email_representante
               FROM {$m['tabla']} d
               JOIN ind_contribuyentes c ON c.ind_Id = d.{$m['prefijo']}_IdContribuyente
              WHERE d.{$m['pk']} = ?",
            [(int) $idDeclaracion]
        ));
        if (!$c) { return []; }

        $email = '';
        foreach (['ind_Email', 'ind_Email_representante'] as $col) {
            $e = trim((string) ($c[$col] ?? ''));
            if (filter_var($e, FILTER_VALIDATE_EMAIL)) { $email = $e; break; }
        }
        $nombre = trim(preg_replace('/\s+/', ' ', implode(' ', [
            (string) ($c['ind_PrimerNombre'] ?? ''), (string) ($c['ind_SegundoNombre'] ?? ''),
            (string) ($c['ind_PrimerApellido'] ?? ''), (string) ($c['ind_SegundoApellido'] ?? ''),
        ])));

        return [
            'tipoDocumento'  => UnoCeroUno::TIPOS_DOCUMENTO[(int) ($c['ind_IdTipoDocumento'] ?? 0)] ?? 1,
            'identificacion' => preg_replace('/\D/', '', (string) ($c['ind_NumeroIdentificacion'] ?? '')),
            'nombre'         => mb_substr($nombre, 0, 120),
            'email'          => $email,
            'telefono'       => mb_substr(preg_replace('/\D/', '', (string) ($c['ind_Telefono'] ?? '')), 0, 15),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* El enlace de «Verificar estado del pago»                            */
    /* ------------------------------------------------------------------ */

    /**
     * Firma del enlace: HMAC de módulo, declaración y factura con un secreto
     * aleatorio PROPIO de la base (UnoCeroUno::secretoEnlace), nunca con la
     * clave de 1cero1pay: la firma viaja al navegador. Sin ella el retorno no
     * consulta nada ni muestra datos, salvo a la sesión que inició ese pago.
     */
    public static function firmaRetorno($modulo, $idDeclaracion, $factura)
    {
        $secreto = UnoCeroUno::secretoEnlace();
        if ($secreto === null) { return ''; }
        return hash_hmac('sha256', '1cero1pay|' . $modulo . '|' . (int) $idDeclaracion . '|' . $factura, $secreto);
    }

    public static function retornoValido($modulo, $idDeclaracion, $factura, $firma)
    {
        $esperada = self::firmaRetorno($modulo, $idDeclaracion, $factura);
        return $esperada !== '' && is_string($firma) && hash_equals($esperada, $firma);
    }

    /** La dirección de retorno (sin esquema ni dominio si $base es ''). */
    public static function urlRetorno($base, $modulo, $idDeclaracion, $factura)
    {
        return $base . 'retorno.php?pasarela=101&modulo=' . rawurlencode($modulo) . '&decl=' . (int) $idDeclaracion
             . '&ref=' . rawurlencode($factura) . '&t=' . self::firmaRetorno($modulo, $idDeclaracion, $factura);
    }

    /* ------------------------------------------------------------------ */
    /* Confirmar con 1cero1pay y aplicar                                   */
    /* ------------------------------------------------------------------ */

    /**
     * Pregunta a 1cero1pay por la factura de un intento y aplica lo que
     * encuentre. Si hay varias transacciones, manda la aprobada; si no, la
     * última. Las que no son de esa factura no cuentan.
     *
     * @return array|null el resultado de aplicar(), o null si 1cero1pay no tiene
     *                    ninguna transacción de esa factura
     * @throws \Exception si no se pudo preguntar
     */
    public static function confirmar($con, array $intento)
    {
        $factura    = (string) $intento['pel_Referencia'];
        $idImpuesto = (int) ($intento['pel_IdImpuesto'] ?? 0);
        if ($idImpuesto <= 0) { $idImpuesto = (int) UnoCeroUno::idImpuesto($intento['pel_Modulo']); }

        $lista = UnoCeroUno::consultar($factura, $idImpuesto);
        if (!$lista) { return null; }

        $propias = [];
        $ajenas  = [];
        foreach ($lista as $tx) {
            $info = UnoCeroUno::interpretar($tx);
            if ($info['factura'] !== '' && $info['factura'] === $factura) { $propias[] = $info; } else { $ajenas[] = $info; }
        }

        if (!$propias) {
            // 1cero1pay contestó con transacciones que no dicen ser de esta
            // factura (otra, o sin el campo): no se aplican. Si alguna está
            // aprobada, lo mira una persona.
            $aprobada = null;
            foreach ($ajenas as $a) { if ($a['aprobado']) { $aprobada = $a; break; } }
            return self::_ajena($con, $intento, $aprobada ?? $ajenas[0], $aprobada !== null);
        }

        $elegida = end($propias);
        foreach ($propias as $p) { if ($p['aprobado']) { $elegida = $p; break; } }
        return self::aplicar($con, $intento, $elegida);
    }

    /** Respuesta de otra factura: se anota; con una aprobada, el intento queda REVISAR. */
    private static function _ajena($con, array $intento, array $info, $aprobada)
    {
        $res = ['intento' => $intento, 'info' => $info, 'estado' => (string) $intento['pel_Estado'],
                'pagada' => false, 'doble' => false, 'ajena' => true];
        $nota = '1cero1pay respondió con una transacción '
              . ($info['factura'] !== '' ? 'de otra factura (' . $info['factura'] . ')' : 'sin número de factura')
              . ($aprobada ? ', aprobada. No se registró el pago: revisar a mano.' : '. No se aplicó.');
        error_log('[1cero1pay] ' . $intento['pel_Referencia'] . ': ' . $nota);

        if ($aprobada && in_array((string) $intento['pel_Estado'], self::ABIERTOS, true)) {
            $con->consultar(
                "UPDATE ind_pagos_en_linea SET pel_Estado = 'REVISAR', pel_Mensaje = ?, pel_FechaEstado = SYSDATETIME()
                  WHERE pel_Id = ? AND pel_Estado IN ('CREADO', 'COMENZADA', 'PENDIENTE')",
                [mb_substr($nota, 0, 300), (int) $intento['pel_Id']]
            );
            $res['estado'] = 'REVISAR';
        } else {
            $con->consultar("UPDATE ind_pagos_en_linea SET pel_Mensaje = ? WHERE pel_Id = ?",
                            [mb_substr($nota, 0, 300), (int) $intento['pel_Id']]);
        }
        return $res;
    }

    /**
     * Aplica a un intento y a su declaración lo que 1cero1pay dice de SU
     * transacción ($info = UnoCeroUno::interpretar, ya comprobado que es de la
     * factura del intento).
     *
     * @return array intento (fila), info, estado (el que quedó en el intento),
     *               pagada (si la declaración quedó pagada por este pago) y
     *               doble (un segundo pago que hay que devolver)
     */
    public static function aplicar($con, array $intento, array $info)
    {
        $m  = PseModulo::get($intento['pel_Modulo']);
        $id = (int) $intento['pel_IdDeclaracion'];

        // Defensa en profundidad: nunca la transacción de otra factura.
        if ($info['factura'] !== (string) $intento['pel_Referencia']) {
            return self::_ajena($con, $intento, $info, !empty($info['aprobado']));
        }

        $con->begin();
        try {
            if (!self::_candado($con, $m['clave'], $id)) {
                throw new \Exception('No se obtuvo el candado de la declaración ' . $m['clave'] . ' ' . $id . '.');
            }
            // Se relee DENTRO del candado: otro proceso pudo cambiarlo.
            $intento = self::intento($con, $intento['pel_Referencia']);
            $res = self::_aplicar($con, $intento, $info, $m, $id);
            $con->commit();
        } catch (\Throwable $e) {
            try { $con->rollback(); } catch (\Throwable $e2) { /* ya se perdió la conexión */ }
            throw ($e instanceof \Exception) ? $e : new \Exception($e->getMessage());
        }
        return $res;
    }

    private static function _aplicar($con, array $intento, array $info, array $m, $id)
    {
        $res = ['intento' => $intento, 'info' => $info, 'estado' => $info['estado'], 'pagada' => false, 'doble' => false];
        $ref      = $intento['pel_Referencia'];
        $anterior = (string) $intento['pel_Estado'];

        // Un estado que no está en el documento de 1cero1pay: no se adivina. El
        // intento queda como estaba y se guarda el texto para que alguien lo
        // vea (y para que la tarea lo avise). No pasa a PENDIENTE.
        if ($info['estado'] === 'DESCONOCIDO') {
            $nota = 'Estado no reconocido de 1cero1pay: «' . $info['estadoOriginal'] . '». No se aplicó; el intento sigue ' . $anterior . '.';
            error_log('[1cero1pay] ' . $ref . ': ' . $nota);
            if (in_array($anterior, self::ABIERTOS, true)) {
                $con->consultar("UPDATE ind_pagos_en_linea SET pel_Mensaje = ? WHERE pel_Id = ?",
                                [mb_substr($nota, 0, 300), (int) $intento['pel_Id']]);
            }
            $res['estado']      = $anterior;
            $res['desconocido'] = true;
            return $res;
        }

        // Un estado cerrado no vuelve a "en trámite".
        if (in_array($anterior, self::CERRADOS, true) && !$info['final']) {
            $res['estado'] = $anterior;
            return $res;
        }
        // Un segundo pago ya anotado para devolver sigue siéndolo.
        if ($anterior === 'DOBLE') {
            $res['estado'] = 'DOBLE';
            $res['doble']  = true;
            return $res;
        }
        // En revisión y 1cero1pay ya no lo da por aprobado: sigue en revisión.
        if ($anterior === 'REVISAR' && !$info['aprobado']) {
            $res['estado'] = 'REVISAR';
            return $res;
        }

        $estado  = $info['estado'];
        $mensaje = $info['mensaje'] !== '' ? $info['mensaje'] : ('1cero1pay: ' . ($info['estadoOriginal'] !== '' ? $info['estadoOriginal'] : $estado));

        // El mismo pago, ya registrado, dejó de estar aprobado.
        if ($anterior === 'APROBADA' && !$info['aprobado']) {
            $mensaje = '1cero1pay pasó a ' . $info['estado'] . ' un pago ya registrado; la declaración sigue pagada: revisar.';
            error_log('[1cero1pay] ' . $ref . ': ' . $mensaje);
            self::_guardar($con, $intento, 'REVISAR', $info, $mensaje);
            $res['estado'] = 'REVISAR';
            return $res;
        }

        // El valor exacto del intento (con los intereses de mora, si los hubo).
        $cuadra   = $info['centavos'] !== null && $info['centavos'] === (int) $intento['pel_Centavos'];
        $aprobado = $info['aprobado'] && $cuadra;
        if ($info['aprobado'] && !$cuadra) {
            $estado  = 'REVISAR';
            $mensaje = '1cero1pay aprobó '
                     . ($info['centavos'] === null ? 'un pago sin informar el valor' : '$' . number_format($info['centavos'] / 100, 2, ',', '.'))
                     . ' y el intento era por $' . number_format(((int) $intento['pel_Centavos']) / 100, 2, ',', '.')
                     . '. No se registró el pago: revisar a mano.';
            error_log('[1cero1pay] ' . $ref . ': ' . $mensaje);
        }

        $pagada = false;
        if ($aprobado) {
            // ¿Ya estaba pagada por OTRO medio (el banco, la otra pasarela, a
            // mano) u OTRO intento? Es un segundo pago: no se registra otra vez,
            // se anota para devolverlo. Si la pagó este mismo intento (se vuelve
            // a procesar), no es doble.
            $decl = $con->obnerFila($con->consultar(
                "SELECT ISNULL({$m['pagado']}, 0) AS pagado, {$m['prefijo']}_RutaPago AS ruta
                   FROM {$m['tabla']} WHERE {$m['pk']} = ?", [$id]
            ));
            if ((int) ($decl['pagado'] ?? 0) === 1) {
                $otro = $con->obnerFila($con->consultar(
                    "SELECT COUNT(*) AS n FROM ind_pagos_en_linea
                      WHERE pel_Modulo = ? AND pel_IdDeclaracion = ? AND pel_Id <> ? AND pel_Estado IN ('APROBADA', 'APPROVED')",
                    [$m['clave'], $id, (int) $intento['pel_Id']]
                ));
                $ruta    = trim((string) ($decl['ruta'] ?? ''));
                $porAqui = $ruta === PagoDeclaracion::VIA_UNOCEROUNO;
                $hayOtro = (int) ($otro['n'] ?? 0) > 0;

                // Estaba EN REVISIÓN (aprobado sin registrar) y la declaración
                // aparece pagada por otra vía, sin ningún otro intento aprobado:
                // lo normal es que la Alcaldía haya resuelto ESTA revisión con un
                // pago manual. Eso no es un segundo pago ni hay nada que devolver;
                // decir DOBLE era una falsa alarma (revisión 2026-11-22). Sigue en
                // REVISAR, con la nota, para que quien lo mire lo confirme.
                if ($anterior === 'REVISAR' && !$porAqui && !$hayOtro) {
                    $mensaje = 'Aprobado en 1cero1pay y la declaración ya figura pagada por otra vía (' . ($ruta !== '' ? $ruta : 'sin dato')
                             . '). Si ese registro es el que resolvió esta revisión, no hay nada que devolver; si es OTRO pago, revisar la devolución.'
                             . ($info['cus'] !== '' ? ' CUS ' . $info['cus'] . '.' : '');
                    self::_guardar($con, $intento, 'REVISAR', $info, $mensaje);
                    $res['estado']   = 'REVISAR';
                    $res['yaPagada'] = true;
                    return $res;
                }

                if (!$porAqui || $hayOtro || !in_array($anterior, ['APROBADA', 'REVISAR'], true)) {
                    $mensaje = 'Pago aprobado en 1cero1pay sobre una declaración que ya estaba pagada: revisar la devolución.'
                             . ($info['cus'] !== '' ? ' CUS ' . $info['cus'] . '.' : '');
                    error_log('[1cero1pay] ' . $ref . ': ' . $mensaje);
                    self::_guardar($con, $intento, 'DOBLE', $info, $mensaje);
                    $res['estado'] = 'DOBLE';
                    $res['doble']  = true;
                    return $res;
                }
                // La pagó este mismo intento: nada que hacer.
                $pagada = true;
            } else {
                // Primero el pago y después el intento: si algo falla, el
                // rollback deshace los dos y la próxima consulta lo repite.
                $pagada = PagoDeclaracion::registrar($con, $id, [
                    'valor'     => $info['valor'],
                    'banco'     => $info['banco'],
                    'via'       => PagoDeclaracion::VIA_UNOCEROUNO,
                    'fechaPago' => self::_fechaPago($info['fecha'], $intento),
                    'idBanco'   => UnoCeroUno::idBanco($con),
                ], $m);
            }

            if ($pagada) {
                $mensaje = 'Aprobada.' . ($info['cus'] !== '' ? ' CUS ' . $info['cus'] . '.' : '');
            } else {
                $estado  = 'REVISAR';
                $mensaje = 'Aprobado en 1cero1pay, pero la declaración no quedó registrada como pagada por este pago '
                         . '(no está presentada): revisar.' . ($info['cus'] !== '' ? ' CUS ' . $info['cus'] . '.' : '');
                error_log('[1cero1pay] ' . $ref . ': ' . $mensaje);
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
            [$estado, $info['id'] !== '' ? $info['id'] : null, $info['medio'] !== '' ? mb_substr($info['medio'], 0, 40) : null,
             mb_substr((string) $mensaje, 0, 300), (int) $intento['pel_Id']]
        );
    }

    /**
     * La fecha del pago según 1cero1pay, en hora de Colombia, o null (se usa la
     * de ahora). El documento no dice el formato: se aceptan AAAA-MM-DD[ T]HH:MM[:SS]
     * (con zona, se convierte; sin zona, es hora de Colombia) y DD/MM/AAAA HH:MM[:SS].
     * Una fecha anterior al intento o futura no se cree.
     */
    private static function _fechaPago($texto, array $intento)
    {
        $texto = trim((string) $texto);
        if ($texto === '') { return null; }
        $bogota = new \DateTimeZone('America/Bogota');
        try {
            if (preg_match('#^(\d{2})/(\d{2})/(\d{4})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?#', $texto, $x)) {
                if (!checkdate((int) $x[2], (int) $x[1], (int) $x[3])) { return null; }
                $f = new \DateTime(sprintf('%04d-%02d-%02d %02d:%02d:%02d', $x[3], $x[2], $x[1],
                                           $x[4] ?? 0, $x[5] ?? 0, $x[6] ?? 0), $bogota);
            } elseif (preg_match('#^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2}(\.\d+)?)?)?(Z|[+-]\d{2}:?\d{2})?$#', $texto)) {
                $f = (new \DateTime($texto, $bogota))->setTimezone($bogota);
            } else {
                return null;
            }
        } catch (\Exception $e) {
            return null;
        }

        $ahora = new \DateTime('now', $bogota);
        if ($f > (clone $ahora)->modify('+10 minutes')) { return null; }
        $creado = $intento['pel_FechaCreacion'] ?? null;
        if ($creado instanceof \DateTimeInterface) {
            // pel_FechaCreacion es la hora del servidor SQL; un día de margen por la zona.
            $minima = (new \DateTime($creado->format('Y-m-d H:i:s'), $bogota))->modify('-1 day');
            if ($f < $minima) { return null; }
        }
        return $f->format('Y-m-d H:i:s');
    }

    /* ------------------------------------------------------------------ */
    /* La tarea programada                                                 */
    /* ------------------------------------------------------------------ */

    /**
     * Revisa los intentos que siguen abiertos (CREADO, COMENZADA, PENDIENTE) y
     * los rechazados de las últimas HORAS_RECHAZADOS (por si 1cero1pay dejó
     * reintentar con la misma factura).
     *
     * Un intento sin empezar (CREADO o COMENZADA) se cierra como SIN_PAGO cuando,
     * pasadas HORAS_SIN_TERMINAR, 1cero1pay CONTESTA y su respuesta no trae un
     * pago: sigue sin empezar, no tiene la transacción, contesta algo sin la
     * factura (p. ej. {"Mensaje": "No existe"}) o un estado que no se reconoce.
     * Así ninguno queda abierto para siempre, costando una consulta por corrida
     * (revisión 2026-11-22). «Verificar estado del pago» lo sigue consultando, y
     * una APROBADA lo registra aunque esté cerrado. Si 1cero1pay NO deja
     * preguntar (sin conexión, 4xx, 5xx) no se cierra nada: aquí no hay avisos
     * que lo registren después. Un PENDIENTE no se cierra solo: se avisa.
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
              WHERE pel_Pasarela = '" . self::PASARELA . "'
                AND (pel_Estado IN ('CREADO', 'COMENZADA', 'PENDIENTE')
                     OR (pel_Estado IN ('RECHAZADA', 'FALLIDA')
                         AND pel_FechaCreacion > DATEADD(HOUR, -" . self::HORAS_RECHAZADOS . ", SYSDATETIME())))
              ORDER BY pel_Id",
            []
        );
        while ($f = $con->obnerFila($st)) { $abiertos[] = $f; }

        $revisados = 0;
        $pagados   = 0;
        foreach ($abiertos as $f) {
            $revisados++;
            $ref     = $f['pel_Referencia'];
            $cerrado = in_array($f['pel_Estado'], ['RECHAZADA', 'FALLIDA'], true);
            $viejo   = (int) $f['horas'] >= self::HORAS_SIN_TERMINAR;
            try {
                $r = self::confirmar($con, $f);
                $sinEmpezar = in_array($f['pel_Estado'], ['CREADO', 'COMENZADA'], true);
                if ($r === null) {
                    if ($sinEmpezar && $viejo) {
                        self::_cerrarSinPago($con, $f, '1cero1pay no tiene ninguna transacción de esta factura pasadas '
                                                     . self::HORAS_SIN_TERMINAR . ' horas.');
                        $eco("$ref: sin transacción en 1cero1pay; se cerró.");
                    } elseif (!$cerrado) {
                        $eco("$ref: 1cero1pay no devuelve ninguna transacción de esta factura.");
                    }
                    continue;
                }
                if ($r['pagada']) { $pagados++; }
                if (!empty($r['ajena'])) {
                    // Respondió, pero no con la transacción de esta factura. Si
                    // no había ninguna aprobada (eso queda REVISAR) y el intento
                    // nunca empezó, pasado el plazo se cierra.
                    if (in_array($r['estado'], ['CREADO', 'COMENZADA'], true) && $viejo) {
                        self::_cerrarSinPago($con, $f, '1cero1pay no devuelve la transacción de esta factura pasadas '
                                                     . self::HORAS_SIN_TERMINAR . ' horas (su respuesta no la trae).');
                        $eco("$ref: la respuesta de 1cero1pay no trae esta factura; se cerró.");
                    } else {
                        $eco("$ref: la respuesta no es de esta factura; no se aplicó.");
                    }
                } elseif (!empty($r['desconocido'])) {
                    $original = (string) ($r['info']['estadoOriginal'] ?? '');
                    if (in_array($r['estado'], ['CREADO', 'COMENZADA'], true) && $viejo) {
                        self::_cerrarSinPago($con, $f, 'Estado no reconocido de 1cero1pay («' . $original . '») pasadas '
                                                     . self::HORAS_SIN_TERMINAR . ' horas: se cerró sin pago. Si era un pago, verifíquelo con 1Cero1.');
                        $eco("ATENCIÓN $ref: estado no reconocido «" . $original . "»; se cerró sin pago. Si era un pago, verifíquelo con 1Cero1.");
                    } else {
                        $eco("ATENCIÓN $ref: estado no reconocido «" . $original . "»; no se aplicó (el intento sigue " . $r['estado'] . ').');
                    }
                } elseif (in_array($r['estado'], ['CREADO', 'COMENZADA'], true) && $viejo) {
                    self::_cerrarSinPago($con, $f, 'El pago se inició y no se terminó.');
                    $eco("$ref: sin terminar en 1cero1pay; se cerró.");
                } elseif (!$cerrado || $r['estado'] !== $f['pel_Estado']) {
                    $eco("$ref: " . $r['estado'] . ($r['pagada'] ? ', pago registrado.' : '.'));
                }
            } catch (\Exception $e) {
                $eco("$ref: error al consultar - " . $e->getMessage());
            }
        }

        // Lo que necesita a una persona: devoluciones, pagos que no cuadran y
        // pendientes que no se resuelven.
        $st = $con->consultar(
            "SELECT pel_Referencia, pel_Modulo, pel_IdDeclaracion, pel_Estado, pel_Mensaje FROM ind_pagos_en_linea
              WHERE pel_Pasarela = '" . self::PASARELA . "'
                AND ((pel_Estado IN ('DOBLE', 'REVISAR') AND pel_FechaEstado > DATEADD(DAY, -30, SYSDATETIME()))
                     OR (pel_Estado = 'PENDIENTE' AND pel_FechaCreacion < DATEADD(HOUR, -" . self::HORAS_AVISO_PENDIENTE . ", SYSDATETIME())))
              ORDER BY pel_Id",
            []
        );
        while ($f = $con->obnerFila($st)) {
            $eco('ATENCIÓN ' . $f['pel_Referencia'] . ' (' . $f['pel_Modulo'] . ' ' . $f['pel_IdDeclaracion'] . ', '
               . $f['pel_Estado'] . '): ' . $f['pel_Mensaje']);
        }

        return ['revisados' => $revisados, 'pagados' => $pagados];
    }

    private static function _cerrarSinPago($con, array $f, $mensaje)
    {
        // «Verificar estado del pago» igual lo consulta: SIN_PAGO es cerrado,
        // pero una APROBADA (final) sí lo cambia.
        $con->consultar(
            "UPDATE ind_pagos_en_linea SET pel_Estado = 'SIN_PAGO', pel_FechaEstado = SYSDATETIME(), pel_Mensaje = ?
              WHERE pel_Id = ? AND pel_Estado IN ('CREADO', 'COMENZADA')",
            [$mensaje, (int) $f['pel_Id']]
        );
    }
}
