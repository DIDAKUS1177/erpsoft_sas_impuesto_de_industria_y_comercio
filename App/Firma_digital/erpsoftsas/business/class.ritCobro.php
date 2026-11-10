<?php

namespace erpsoftsas;

require_once __DIR__ . '/class.parametros.php';

/**
 * Cobro de la inscripción del RIT, por municipio (migración 055).
 *
 * Pedido de Diego (2026-11-09): Macanal y Guateque cobran la inscripción en el
 * RIT; Paipa no. El valor lo escribe cada Alcaldía en "Municipio y bancos"
 * (RIT_VALOR_INSCRIPCION); vacío o 0 = gratis, y en ese caso NADA de esta clase
 * toca la base ni cambia una respuesta: Paipa queda idéntico.
 *
 * QUÉ ES (Y QUÉ NO) ESTE COBRO
 *
 * El RIT no tiene un estado "inscrito" guardado: la inscripción se deduce
 * (RitFirma::opcionDeUso y las firmas de ind_rit_firmas). Por eso el
 * "pendiente" tampoco se guarda: se deduce igual. Se cobra a quien
 *   - nunca ha firmado su RIT (ninguna fila en ind_rit_firmas),
 *   - no está en cese ni ha presentado declaraciones (opcionDeUso = 'Inscripción').
 * Quien firmó el RIT o declaró antes de que se activara el cobro NO paga, y el
 * cese nunca se cobra. Lo único que se guarda (ind_rit_cobros) es la decisión
 * de la Alcaldía: PAGADO (registró el pago) o EXONERADO (con su motivo).
 *
 * UNA CONSECUENCIA QUE HAY QUE SABER. Como el pendiente se deduce y no se guarda,
 * con RIT_PAGO_PREVIO_A_FIRMAR = 0 ("solo informa", como nace) un contribuyente
 * que firma su RIT antes de que la Alcaldía registre su pago deja de figurar con
 * cobro, y ya no se puede registrar ni exonerar. Para que el sistema conserve
 * quién pagó, el municipio que cobra debe poner el pago previo en 1: así nadie
 * firma sin que el pago o la exoneración estén registrados.
 *
 * El pago no pasa por PSE, Wompi, el recaudo por archivo ni el recibo con
 * código de barras: todo eso está atado a una declaración PRESENTADA y mover el
 * RIT ahí tocaría lo que cobra en Paipa. El contribuyente paga fuera del
 * sistema (ventanilla, consignación...) y un funcionario con el permiso
 * alcaldia.rit.cobro lo registra. Por eso tampoco se usa
 * PagoDeclaracion::registrar: la fila nace ya pagada, en un solo INSERT, con la
 * hora de Colombia (registrar() escribe GETDATE(), que en el servidor es UTC).
 *
 * LA HUELLA DEL RIT NO SE TOCA. El comprobante del pago NO se sube como anexo
 * del RIT: los anexos entran en la huella (RitFirma::documentosImpresos) y
 * agregar uno cambia el hash y deja "Firma desactualizada". Este cobro no entra
 * en RitFirma ni en el PDF.
 *
 * EL VALOR COBRADO QUEDA COMO FOTO. Al registrar, el valor del parámetro de ese
 * momento se guarda en la fila; cambiarlo después no modifica lo ya registrado.
 *
 * TODO TOLERA LA AUSENCIA: sin la migración 055 (tabla o parámetros) el código
 * responde "no hay cobro", porque el despliegue del código y el de la migración
 * no son simultáneos.
 */
class RitCobro
{
    const CONCEPTO  = 'INSCRIPCION';
    const PENDIENTE = 'PENDIENTE';
    const PAGADO    = 'PAGADO';
    const EXONERADO = 'EXONERADO';

    /** Por dónde entró el pago: lo registró un funcionario (igual que PagoDeclaracion::VIA_MANUAL). */
    const VIA_MANUAL = 'MANUAL';

    const MOTIVO_MIN = 10;
    const MOTIVO_MAX = 500;

    /* =====================================================================
     * HORA DE COLOMBIA
     *
     * El servidor SQL está en UTC: desde las 7 p. m. su "hoy" ya es mañana.
     * ===================================================================== */

    /** Hoy en Colombia (AAAA-MM-DD). */
    public static function hoy()
    {
        return (new \DateTime('now', new \DateTimeZone('America/Bogota')))->format('Y-m-d');
    }

    /** Ahora en Colombia, ISO con T para SQL Server. */
    public static function ahora()
    {
        return (new \DateTime('now', new \DateTimeZone('America/Bogota')))->format('Y-m-d\TH:i:s');
    }

    /** Pesos para un mensaje: "$25.000". */
    public static function pesos($valor)
    {
        return '$' . number_format((float) $valor, 0, ',', '.');
    }

    /* =====================================================================
     * LOS PARÁMETROS (Municipio y bancos)
     * ===================================================================== */

    /**
     * Pesos que se cobran, o 0 si la inscripción es gratis.
     *
     * Solo dígitos, hasta nueve: lo mismo que acepta la pantalla (par_Patron).
     * Cualquier otra cosa -vacío, "0", "50.000", "abc"- vale 0: ante un valor que
     * no se entiende se falla hacia gratis, nunca hacia un cobro mal leído
     * ("50.000" leído como 50 o como 50000 sería cobrar de más o de menos).
     */
    public static function valor()
    {
        $v = Parametros::valor('RIT_VALOR_INSCRIPCION');
        if ($v === null || !preg_match('/^[0-9]{1,9}$/', $v)) { return 0; }
        return (int) $v;
    }

    /**
     * ¿El servidor impide firmar el RIT mientras el cobro esté pendiente?
     * Solo con '1' explícito: vacío, '0' o ausente solo informa (así nace).
     */
    public static function previoAFirmar()
    {
        return Parametros::valor('RIT_PAGO_PREVIO_A_FIRMAR') === '1';
    }

    /** Dónde y cómo pagar, tal como lo escribió la Alcaldía ('' si no escribió nada). */
    public static function instrucciones()
    {
        $t = Parametros::valor('RIT_PAGO_INSTRUCCIONES');
        if ($t === null) { return ''; }
        // La pantalla ya rechaza < y >; aquí se vuelve a quitar por si el texto
        // llegó a la tabla por otro camino. Y sin saltos ni caracteres de control.
        $t = preg_replace('/[<>\x00-\x1F\x7F]+/u', ' ', $t);
        return mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $t)), 0, 500);
    }

    /* =====================================================================
     * LA TABLA
     * ===================================================================== */

    /** ¿Ya corrió la 055 (existe ind_rit_cobros)? Se pregunta una vez por petición. */
    public static function hayTabla($con)
    {
        static $hay = null;
        if ($hay === null) {
            $f = $con->obnerFila($con->consultar("SELECT OBJECT_ID('dbo.ind_rit_cobros', 'U') AS o", []));
            $hay = !empty($f['o']);
        }
        return $hay;
    }

    /* =====================================================================
     * EL ESTADO
     * ===================================================================== */

    /**
     * El estado del cobro de este contribuyente, o null si no hay cobro.
     *
     *   null       gratis, o no le corresponde (ya firmó, ya declaró, cese)
     *   PENDIENTE  le corresponde pagar y la Alcaldía aún no lo registra
     *   PAGADO     la Alcaldía registró su pago
     *   EXONERADO  la Alcaldía lo exoneró, con su motivo
     *
     * @return array|null ['estado' => ..., 'valor' => int, y 'pago' o 'exoneracion']
     */
    public static function estado($con, $idContribuyente)
    {
        $id = (int) $idContribuyente;
        $valor = self::valor();

        // Gratis: ni se mira la base. Es lo que deja a Paipa idéntico, y lo que
        // permite subir el código antes de aplicar la migración.
        if ($id <= 0 || $valor <= 0 || !self::hayTabla($con)) { return null; }

        // Lo ya registrado manda, con el valor de ESE día (la foto), no el de hoy.
        $f = $con->obnerFila($con->consultar(
            "SELECT TOP 1 c.rit_Valor, c.rit_Pagado, c.rit_Exonerado, c.rit_ValorPago,
                    CONVERT(VARCHAR(10), c.rit_FechaPago, 120)     AS fechaPago,
                    CONVERT(VARCHAR(16), c.rit_FechaCreacion, 120) AS registradoEl,
                    c.rit_BancoPago, c.rit_Referencia, c.rit_MotivoExoneracion,
                    LTRIM(RTRIM(ISNULL(u.usu_Nombres, '') + ' ' + ISNULL(u.usu_Apellidos, ''))) AS registradoPor
               FROM dbo.ind_rit_cobros c
               LEFT JOIN dbo.conf_usuarios u ON u.usu_Id = c.rit_IdUsuario
              WHERE c.rit_IdContribuyente = ? AND c.rit_Concepto = ? AND c.rit_Estado = 2
              ORDER BY c.rit_Id DESC",
            [$id, self::CONCEPTO]
        ));
        if ($f) {
            if (!empty($f['rit_Exonerado'])) {
                return [
                    'estado' => self::EXONERADO,
                    'valor'  => (int) round((float) $f['rit_Valor']),
                    'exoneracion' => [
                        'motivo'       => (string) $f['rit_MotivoExoneracion'],
                        'registradoPor' => (string) $f['registradoPor'],
                        'registradoEl' => (string) $f['registradoEl'],
                    ],
                ];
            }
            return [
                'estado' => self::PAGADO,
                'valor'  => (int) round((float) $f['rit_ValorPago']),
                'pago'   => [
                    'fecha'         => (string) $f['fechaPago'],
                    'banco'         => (string) $f['rit_BancoPago'],
                    'referencia'    => (string) $f['rit_Referencia'],
                    'registradoPor' => (string) $f['registradoPor'],
                    'registradoEl'  => (string) $f['registradoEl'],
                ],
            ];
        }

        return self::_leCorresponde($con, $id) ? ['estado' => self::PENDIENTE, 'valor' => $valor] : null;
    }

    /**
     * ¿Le corresponde pagar la inscripción? Solo a quien nunca firmó su RIT y
     * está en 'Inscripción' (sin cese ni declaraciones presentadas): quien ya
     * se formalizó antes del cobro no paga retroactivamente.
     */
    private static function _leCorresponde($con, $id)
    {
        // Un id que no existe no debe verse como "inscripción pendiente".
        $existe = $con->obnerFila($con->consultar(
            "SELECT TOP 1 1 AS x FROM ind_contribuyentes WHERE ind_Id = ?", [$id]
        ));
        if (!$existe) { return false; }

        $firmo = $con->obnerFila($con->consultar(
            "SELECT TOP 1 1 AS x FROM ind_rit_firmas WHERE rif_IdContribuyente = ?", [$id]
        ));
        if ($firmo) { return false; }

        require_once __DIR__ . '/class.ritFirma.php';
        return RitFirma::opcionDeUso($con, $id) === 'Inscripción';
    }

    /**
     * Por qué el RIT no se puede firmar todavía, o null si se puede.
     *
     * Solo bloquea con RIT_PAGO_PREVIO_A_FIRMAR = 1 y el cobro PENDIENTE. Lo
     * llaman las dos puertas de la firma del servidor (pedir el código y
     * firmar): la pantalla solo es cortesía y se salta desde la consola.
     * Sin cobro (Paipa) responde null sin tocar la base.
     */
    public static function motivoDeBloqueo($con, $idContribuyente)
    {
        if (self::valor() <= 0 || !self::previoAFirmar()) { return null; }

        $e = self::estado($con, $idContribuyente);
        if ($e === null || $e['estado'] !== self::PENDIENTE) { return null; }

        $msg = 'Antes de firmar el RIT debe pagar los derechos de inscripción (' . self::pesos($e['valor'])
             . '). Cuando la Alcaldía registre su pago podrá firmar.';
        $como = self::instrucciones();
        if ($como !== '') {
            $msg .= ' Cómo pagar: ' . $como . (preg_match('/[.!?]$/u', $como) ? '' : '.');
        }
        return $msg;
    }

    /** ¿El servidor impide firmar ahora mismo? (ver motivoDeBloqueo, que dice por qué). */
    public static function bloqueaFirma($con, $idContribuyente)
    {
        return self::motivoDeBloqueo($con, $idContribuyente) !== null;
    }

    /**
     * Lo que la pantalla del RIT muestra en la tarjeta «Derechos de inscripción»,
     * o null si no hay nada que mostrar (gratis o no corresponde): en ese caso
     * la respuesta de la función 10 no lleva la clave y queda idéntica a la de antes.
     *
     * @param bool $puedeRegistrar quien pregunta puede registrar el pago o exonerar
     *                             (lo decide quien llama, con PermisosRol)
     * @return array|null
     */
    public static function resumen($con, $idContribuyente, $puedeRegistrar = false)
    {
        $e = self::estado($con, $idContribuyente);
        if ($e === null) { return null; }

        $pendiente = $e['estado'] === self::PENDIENTE;
        $r = [
            'estado'  => $e['estado'],
            'valor'   => (int) $e['valor'],
            'bloquea' => ($pendiente && self::previoAFirmar()) ? 1 : 0,
        ];
        if ($pendiente) {
            $r['instrucciones']  = self::instrucciones();
            $r['puedeRegistrar'] = $puedeRegistrar ? 1 : 0;
        } elseif ($e['estado'] === self::PAGADO) {
            $r['pago'] = $e['pago'];
        } else {
            $r['exoneracion'] = $e['exoneracion'];
        }
        return $r;
    }

    /**
     * ¿La sesión puede registrar el pago o exonerar? El permiso del panel de
     * Roles y, como todo lo que se hace sobre otro contribuyente, "Gestionar a
     * un contribuyente": el contribuyente nunca registra su propio pago.
     */
    public static function puedeRegistrar()
    {
        require_once __DIR__ . '/class.permisosRol.php';
        return PermisosRol::tiene('alcaldia.rit.cobro') && PermisosRol::gestionaOtros();
    }

    /* =====================================================================
     * REGISTRAR
     * ===================================================================== */

    /**
     * La Alcaldía registra que el contribuyente pagó la inscripción.
     *
     * Pide el día del pago, el N° del recibo o de la consignación y, si lo hubo,
     * el banco. El valor NO se escribe: es el del parámetro, leído bajo el
     * candado, y queda como foto. Registrar dos veces, o dos funcionarios a la
     * vez, deja un solo registro: el candado serializa y, bajo él, solo se
     * registra si sigue PENDIENTE (el índice único es la última red).
     *
     * @param array $datos fechaPago (AAAA-MM-DD), referencia, idBanco (opcional)
     * @return array ['ok' => bool, 'mensaje' => string, 'codigo' => ?string]
     */
    public static function registrarPago($con, $idContribuyente, array $datos, $idUsuario)
    {
        $id      = (int) $idContribuyente;
        $usuario = (int) $idUsuario;

        $previo = self::_antesDeEscribir($con, $id, $usuario);
        if ($previo !== null) { return $previo; }

        $fecha = self::_fecha(self::_cadena($datos['fechaPago'] ?? ''));
        if (is_array($fecha)) { return $fecha; }

        // El N° del recibo o de la consignación lo escribe una persona: letras (con tildes, que
        // "Consignación 889" es lo normal), números y los signos de un número de recibo. Empieza
        // por letra o número (así un "=..." o "+..." no llega a una hoja de cálculo) y no admite
        // < > ' " ; ni saltos. Solo letras latinas y dígitos 0-9: cada carácter cuenta una vez
        // en la columna de 60, sin sorpresas con símbolos de otros alfabetos.
        $referencia = self::_cadena($datos['referencia'] ?? '');
        if (!preg_match('/^[\p{Latin}0-9][\p{Latin}0-9 .,:#°\/_()-]{0,59}$/uD', $referencia)) {
            return self::_no('Escriba el número del recibo o de la consignación (letras, números, espacios y los signos . , : # ° / _ - ( ); hasta 60 caracteres).');
        }

        // El banco es opcional (un pago en ventanilla no pasa por uno), pero si
        // llega tiene que ser uno activo del catálogo.
        $banco = null;
        // Un dato mal formado (un arreglo) no se ignora como si no se hubiera elegido banco:
        // se rechaza, que lo que se registra es lo que se quiso registrar.
        if (!is_scalar($datos['idBanco'] ?? '')) {
            return self::_no('Elija un banco activo de la lista, o déjelo sin elegir.');
        }
        $idBanco = self::_cadena($datos['idBanco'] ?? '');
        if ($idBanco !== '') {
            require_once __DIR__ . '/class.bancosPago.php';
            $banco = preg_match('/^[0-9]{1,9}$/', $idBanco) ? BancosPago::activo($con, (int) $idBanco) : null;
            if (!$banco) { return self::_no('Elija un banco activo de la lista, o déjelo sin elegir.'); }
        }

        try {
            $con->begin();
            if (!self::_candado($con, $id)) {
                $con->rollback();
                return self::_no('Otra persona está registrando el cobro de este contribuyente. Intente de nuevo en un minuto.');
            }

            // Bajo el candado, otra vez: otro funcionario pudo registrarlo mientras tanto.
            // Los parámetros se vuelven a leer aquí (la caché es de antes de esperar
            // el candado): el valor que queda en la fila es el vigente al registrar.
            Parametros::olvidar();
            $e = self::estado($con, $id);
            $rechazo = self::_rechazoPorEstado($e);
            if ($rechazo !== null) { $con->rollback(); return $rechazo; }

            $valor = (int) $e['valor'];   // el del parámetro, leído bajo el candado: la foto
            $ahora = self::ahora();
            $con->consultar(
                "SET NOCOUNT ON;
                 INSERT INTO dbo.ind_rit_cobros
                    (rit_IdContribuyente, rit_Concepto, rit_Estado, rit_Valor, rit_Pagado, rit_FechaPago,
                     rit_FechaRealPago, rit_ValorPago, rit_BancoPago, rit_IdBancoPago, rit_AnioPago,
                     rit_RutaPago, rit_Referencia, rit_Exonerado, rit_MotivoExoneracion, rit_IdUsuario, rit_FechaCreacion)
                 VALUES (?, ?, 2, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, 0, NULL, ?, ?)",
                [
                    $id, self::CONCEPTO, $valor, $fecha,
                    $ahora, $valor,
                    $banco ? mb_substr($banco['nombre'], 0, 60) : null,
                    $banco ? (int) $banco['id'] : null,
                    (int) substr($fecha, 0, 4),
                    self::VIA_MANUAL, $referencia,
                    $usuario, $ahora,
                ]
            );
            $con->commit();
        } catch (\Throwable $t) {
            try { $con->rollback(); } catch (\Throwable $t2) { /* ya cerrada */ }
            return self::_fallo('registrar el pago', $id, $t);
        }

        error_log(sprintf('[rit cobro] usuario %d registró el pago de la inscripción del contribuyente %d: %s, día %s, ref. %s',
            $usuario, $id, self::pesos($valor), $fecha, $referencia));

        return ['ok' => true, 'mensaje' => 'Pago de la inscripción registrado.', 'codigo' => null];
    }

    /**
     * La Alcaldía exonera de la inscripción, con un motivo que queda escrito.
     * Mismas garantías que registrarPago (candado, un solo registro vigente).
     */
    public static function exonerar($con, $idContribuyente, $motivo, $idUsuario)
    {
        $id      = (int) $idContribuyente;
        $usuario = (int) $idUsuario;

        $previo = self::_antesDeEscribir($con, $id, $usuario);
        if ($previo !== null) { return $previo; }

        // Sin saltos de línea ni caracteres de control: el motivo se muestra en la tarjeta.
        $motivo = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', self::_cadena($motivo)));
        // La columna NVARCHAR cuenta unidades UTF-16 (un emoji ocupa 2), no caracteres.
        $unidades = (int) (strlen((string) mb_convert_encoding($motivo, 'UTF-16LE', 'UTF-8')) / 2);
        if (mb_strlen($motivo) < self::MOTIVO_MIN || $unidades > self::MOTIVO_MAX) {
            return self::_no('Escriba por qué se exonera la inscripción (entre ' . self::MOTIVO_MIN . ' y ' . self::MOTIVO_MAX . ' caracteres).');
        }

        try {
            $con->begin();
            if (!self::_candado($con, $id)) {
                $con->rollback();
                return self::_no('Otra persona está registrando el cobro de este contribuyente. Intente de nuevo en un minuto.');
            }

            Parametros::olvidar();   // el valor vigente al exonerar, no el de antes del candado
            $e = self::estado($con, $id);
            $rechazo = self::_rechazoPorEstado($e);
            if ($rechazo !== null) { $con->rollback(); return $rechazo; }

            $valor = (int) $e['valor'];   // lo que se exonera, como foto
            $con->consultar(
                "SET NOCOUNT ON;
                 INSERT INTO dbo.ind_rit_cobros
                    (rit_IdContribuyente, rit_Concepto, rit_Estado, rit_Valor, rit_Pagado, rit_Exonerado,
                     rit_MotivoExoneracion, rit_IdUsuario, rit_FechaCreacion)
                 VALUES (?, ?, 2, ?, 0, 1, ?, ?, ?)",
                [$id, self::CONCEPTO, $valor, $motivo, $usuario, self::ahora()]
            );
            $con->commit();
        } catch (\Throwable $t) {
            try { $con->rollback(); } catch (\Throwable $t2) { /* ya cerrada */ }
            return self::_fallo('exonerar', $id, $t);
        }

        error_log(sprintf('[rit cobro] usuario %d exoneró de la inscripción (%s) al contribuyente %d: %s',
            $usuario, self::pesos($valor), $id, $motivo));

        return ['ok' => true, 'mensaje' => 'Inscripción exonerada.', 'codigo' => null];
    }

    /* =====================================================================
     * PIEZAS
     * ===================================================================== */

    private static function _no($mensaje, $codigo = null)
    {
        return ['ok' => false, 'mensaje' => $mensaje, 'codigo' => $codigo];
    }

    /**
     * Un dato del formulario como texto recortado. Lo que no es un valor simple (un
     * arreglo mandado a propósito: "campo[]=x") es vacío: de otro modo PHP lo vuelve la
     * palabra "Array" y pasaría por un número de recibo.
     */
    private static function _cadena($valor)
    {
        return is_scalar($valor) ? trim((string) $valor) : '';
    }

    /** Lo que se comprueba antes de abrir la transacción, común a registrar y exonerar. */
    private static function _antesDeEscribir($con, $id, $usuario)
    {
        if ($id <= 0) { return self::_no('No se indicó el contribuyente.'); }
        if ($usuario <= 0) { return self::_no('Su sesión terminó. Inicie sesión de nuevo.'); }
        if (self::valor() <= 0) { return self::_no('Este municipio no cobra la inscripción del RIT.'); }
        if (!self::hayTabla($con)) {
            return self::_no('Esta función aún no está instalada en este municipio (falta la migración 055).');
        }
        return null;
    }

    /** Si el estado no permite registrar, el mensaje de por qué; null si está PENDIENTE. */
    private static function _rechazoPorEstado($e)
    {
        if ($e === null) {
            return self::_no('Este contribuyente no tiene una inscripción pendiente de pago '
                           . '(ya firmó su RIT, ya presentó declaraciones, está en cese, o no existe).', 'NO_PENDIENTE');
        }
        if ($e['estado'] === self::PAGADO)    { return self::_no('La inscripción de este contribuyente ya figura como pagada.', 'YA_REGISTRADO'); }
        if ($e['estado'] === self::EXONERADO) { return self::_no('La inscripción de este contribuyente ya fue exonerada.', 'YA_REGISTRADO'); }
        return null;
    }

    /** Fecha AAAA-MM-DD válida, desde el 2000 y no futura (hora de Colombia). Devuelve la fecha o el error. */
    private static function _fecha($texto)
    {
        $t = trim((string) $texto);
        if (!preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $t, $x) || !checkdate((int) $x[2], (int) $x[3], (int) $x[1])) {
            return self::_no('Escriba la fecha de pago.');
        }
        if ($t < '2000-01-01' || $t > self::hoy()) {
            return self::_no('La fecha de pago no puede ser futura ni anterior al año 2000.');
        }
        return $t;
    }

    /**
     * Un registro a la vez por contribuyente: el mismo mecanismo de los pagos
     * (sp_getapplock) dentro de la transacción, que lo suelta al terminar.
     */
    private static function _candado($con, $id)
    {
        $f = $con->obnerFila($con->consultar(
            "SET NOCOUNT ON;
             DECLARE @r INT;
             EXEC @r = sp_getapplock @Resource = ?, @LockMode = 'Exclusive',
                                     @LockOwner = 'Transaction', @LockTimeout = 20000;
             SELECT @r AS r;",
            ['erp_ritcobro_' . (int) $id]
        ));
        return isset($f['r']) && (int) $f['r'] >= 0;
    }

    /** Un error inesperado: al registro va el detalle, a la pantalla un aviso claro. */
    private static function _fallo($que, $id, \Throwable $t)
    {
        error_log('[rit cobro] no se pudo ' . $que . ' (contribuyente ' . $id . '): ' . $t->getMessage());
        // Clave duplicada (2601/2627) en el índice único del cobro vigente: otro registro
        // ganó la carrera. Se reconoce por el código que imprime el driver o por el nombre del
        // índice, no por un "2601" suelto (podría salir en cualquier otro texto del mensaje).
        if (preg_match('/\[code\]\s*=>\s*(2601|2627)\b/i', $t->getMessage())
            || stripos($t->getMessage(), 'UX_rit_cobros_vigente') !== false) {
            return self::_no('La inscripción de este contribuyente ya fue registrada. Recargue la pantalla.', 'YA_REGISTRADO');
        }
        return self::_no('No se pudo ' . $que . ': no quedó ningún cambio. Intente de nuevo.');
    }
}
