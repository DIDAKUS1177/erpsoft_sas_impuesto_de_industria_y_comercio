<?php

/**
 * Integración PSE ICA con PlacetoPay Checkout (Avalpaycenter / Banco de
 * Bogotá). Referencia: https://docs.placetopay.dev/checkout
 *
 * Autenticación: cada request lleva un objeto "auth" con login/tranKey/
 * nonce/seed. tranKey = Base64(SHA256(nonce_crudo + seed + secretKey)),
 * y nonce = Base64(nonce_crudo) (dos codificaciones distintas del mismo
 * valor aleatorio: una cruda dentro del hash, otra en base64 en el JSON).
 *
 * El webhook (webhook.php) valida la firma que manda PlacetoPay en la
 * notificacion (ver validarFirmaWebhook) COMO PRIMER FILTRO, pero de
 * todas formas nunca actualiza la declaracion con datos tomados
 * directamente del POST: siempre vuelve a consultar el estado real de la
 * sesion con consultarSesion() (autenticado con nuestro propio
 * secretKey) antes de guardar nada. Asi hay dos capas: firma invalida se
 * rechaza de una, y aunque la firma sea valida, el estado que se guarda
 * siempre sale de una consulta autenticada nuestra, no del payload.
 */
class PlacetoPay {

    /*
     * EL CONVENIO DE RECAUDO SE CONFIGURA, NO SE DESPLIEGA
     *
     * Estos tres datos -direccion, usuario y clave secreta- los entrega el
     * banco y son propios del convenio de CADA entidad. El cliente lo dijo el
     * 2026-08-26 hablando de Banco de Bogota y BBVA: "dependiendo del contrato
     * es diferente".
     *
     * Hasta entonces eran constantes de config.municipio.php, un archivo que
     * no se sube por git y que solo se edita entrando al servidor. Cambiar de
     * convenio, rotar un secreto o pasar de pruebas a produccion era, por eso,
     * un despliegue.
     *
     * Ahora salen de conf_parametros (migracion 023) y la constante queda de
     * respaldo, exactamente como se hizo con el EAN de recaudo. La tabla manda;
     * si esta vacia, el archivo; si tampoco, null y no se cobra.
     */
    const PARAM_BASEURL   = 'PASARELA_BASEURL';
    const PARAM_LOGIN     = 'PASARELA_LOGIN';
    const PARAM_SECRETKEY = 'PASARELA_SECRETKEY';

    /** Vigencia de una sesion (la guia WC exige entre 10 y 30 minutos). */
    const MINUTOS_SESION = 30;

    /** *_PSE_Mensaje de una sesion recien creada, sin respuesta del banco aun. */
    const MENSAJE_SESION_CREADA = 'Sesión creada: esperando el pago';

    /*
     * Estados de una sesion, para el candado y el cron (revision 2026-10-06).
     *
     * EN TRAMITE: no se crea otra encima. PENDING es la que puede estar
     * pagandose; APPROVED sin pagar es un pago aprobado que no se alcanzo a
     * registrar (registrar fallo): pisarla perderia el requestId con que el
     * retorno o el cron lo registran, y el contribuyente pagaria dos veces.
     *
     * FINALES SIN PAGO: ya no cambian; el cron no las vuelve a consultar (lo
     * pide AvalPay). Todo lo demas -pendiente, aprobada sin registrar, sin
     * estado (de antes de anotarSesion) u otro- se sigue consultando.
     */
    const ESTADOS_EN_TRAMITE = ['PENDING', 'APPROVED'];
    const ESTADOS_FINALES    = ['REJECTED', 'EXPIRED', 'PARTIAL_EXPIRED'];

    /** ¿Hay una sesion en tramite con ese ultimo estado conocido? */
    public static function enTramite($estado)
    {
        return in_array(strtoupper((string) $estado), self::ESTADOS_EN_TRAMITE, true);
    }

    /** Lista SQL de estados ('A', 'B'); solo de las constantes de arriba. */
    public static function listaSql(array $estados)
    {
        return "'" . implode("', '", $estados) . "'";
    }

    private static function parametro($clave, $constante, $patron = null)
    {
        include_once __DIR__ . '/class.parametros.php';

        return \erpsoftsas\Parametros::valorOConstante($clave, $constante, $patron);
    }

    /** Direccion del servicio, sin barra final. */
    public static function baseUrl()
    {
        $v = self::parametro(self::PARAM_BASEURL, 'PLACETOPAY_BASEURL',
                             '#^https://[A-Za-z0-9.-]+(/[A-Za-z0-9._~/-]*)?$#');

        return $v === null ? null : rtrim($v, '/');
    }

    public static function login()
    {
        return self::parametro(self::PARAM_LOGIN, 'PLACETOPAY_LOGIN');
    }

    public static function secretKey()
    {
        return self::parametro(self::PARAM_SECRETKEY, 'PLACETOPAY_SECRETKEY');
    }

    /**
     * ¿Esta el convenio completo?
     *
     * Hace falta porque hasta ahora nadie lo preguntaba: la pantalla pintaba
     * el boton "Pagar PSE" siempre, y si faltaba cualquiera de las tres
     * constantes PHP lanzaba un error fatal al leerla -pantalla en blanco, sin
     * mensaje-. Un municipio recien instalado, sin convenio todavia, veia un
     * boton que solo podia romperse.
     *
     * Los tres tienen que estar: con dos de tres no se puede cobrar nada.
     */
    public static function configurado()
    {
        return self::baseUrl() !== null
            && self::login() !== null
            && self::secretKey() !== null;
    }

    // El modo certificacion (PASARELA_USUARIOS_PRUEBA: el boton solo para los
    // usuarios de prueba) vive desde la 042 en \erpsoftsas\Pasarela::botonVisible,
    // que sirve a las dos pasarelas.

    private static function auth() {
        $seed = date('c');
        $nonceCrudo = random_bytes(16);
        $tranKey = base64_encode(hash('sha256', $nonceCrudo . $seed . self::secretKey(), true));

        return [
            'login'   => self::login(),
            'tranKey' => $tranKey,
            'nonce'   => base64_encode($nonceCrudo),
            'seed'    => $seed,
        ];
    }

    private static function post($url, $payload) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 20,
        ]);
        $respuesta = curl_exec($ch);
        if ($respuesta === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('Error de conexión con PlacetoPay: ' . $error);
        }
        $codigoHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($respuesta, true);
        if ($data === null) {
            throw new Exception('Respuesta no válida de PlacetoPay (HTTP ' . $codigoHttp . '): ' . $respuesta);
        }
        return $data;
    }

    /**
     * Crea una sesión de pago simple (sin desglose de impuestos ni pago
     * mixto, tal como confirmó el banco para este trámite).
     *
     * @param string $referencia  Numero de declaracion (misma referencia del codigo de barras).
     * @param float  $valor       Total a pagar.
     * @param string $descripcion Texto visible al contribuyente en PlacetoPay.
     * @param string $returnUrl   A donde redirige PlacetoPay cuando el usuario da "volver al comercio".
     * @return array ['requestId' => int, 'processUrl' => string]
     */
    public static function crearSesion($referencia, $valor, $descripcion, $returnUrl, $buyer = null, $fields = null) {
        $payload = [
            'auth' => self::auth(),
            'payment' => [
                'reference'   => (string) $referencia,
                'description' => $descripcion,
                'amount' => [
                    'currency' => 'COP',
                    'total'    => round((float) $valor, 2),
                ],
            ],
            // La certificacion WC de AvalPay exige que la expiracion este entre
            // 10 y 30 minutos (Guia de certificacion WC, item 2). Antes eran 2h,
            // fuera de rango: la sesion habria sido rechazada en la homologacion.
            'expiration'   => date('c', strtotime('+' . self::MINUTOS_SESION . ' minutes')),
            'returnUrl'    => $returnUrl,
            'ipAddress'    => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'userAgent'    => $_SERVER['HTTP_USER_AGENT'] ?? 'ERPSoftSAS-ICA-Paipa',
            'paymentMethod' => 'pse',
            'locale'       => 'es_CO',
        ];

        // buyer (opcional): datos del contribuyente. Si se envia, AvalPay los
        // usa; si no, los pide en su pantalla. fields: extradata (p. ej. el
        // periodo), que el banco muestra en el comprobante.
        if (is_array($buyer) && $buyer) {
            $payload['buyer'] = $buyer;
        }
        if (is_array($fields) && $fields) {
            $payload['payment']['fields'] = $fields;
        }

        $data = self::post(self::baseUrl() . '/session', $payload);

        if (empty($data['status']) || $data['status']['status'] !== 'OK') {
            $mensaje = $data['status']['message'] ?? 'Respuesta desconocida';
            throw new Exception('PlacetoPay rechazó la creación de la sesión: ' . $mensaje);
        }

        return [
            'requestId'  => $data['requestId'],
            'processUrl' => $data['processUrl'],
        ];
    }

    /**
     * Anota en la declaracion la sesion recien creada, ya PENDIENTE.
     *
     * Antes solo se guardaba el requestId; el estado quedaba vacio -o con el de
     * la sesion anterior, p. ej. REJECTED- hasta la primera consulta. Mientras
     * tanto nada frenaba otra sesion (el freno del item 4.3 mira el PENDING), y
     * la nueva PISABA el requestId de la primera: si el contribuyente pagaba en
     * la pestaña de la primera, el banco cobraba y ni el aviso (webhook.php), ni
     * el retorno, ni el cron encontraban ya la declaracion (revision 2026-10-06,
     * antes de pasar Paipa a produccion). Naciendo PENDING, la siguiente espera
     * a que esta se resuelva o venza.
     *
     * Solo se anota si en el intervalo nadie dejo otra sesion en tramite (dos
     * pestañas a la vez) y la declaracion sigue presentada y sin pagar (el
     * recaudo pudo marcarla mientras se hablaba con PlacetoPay): si no, devuelve
     * false y la nueva no se ofrece; vence sola sin que nadie la pague.
     *
     * @return bool true si la sesion quedo anotada en la declaracion
     */
    public static function anotarSesion($con, array $m, $idDeclaracion, $requestId)
    {
        require_once __DIR__ . '/class.pseModulo.php';

        // Tabla y columnas salen del mapa fijo de PseModulo, nunca del usuario.
        $req = \erpsoftsas\PseModulo::colRequestId($m);
        $est = \erpsoftsas\PseModulo::colEstado($m);

        $con->consultar(
            "UPDATE {$m['tabla']}
                SET {$req} = ?, {$est} = 'PENDING',
                    " . \erpsoftsas\PseModulo::colFechaEstado($m) . " = GETDATE(),
                    " . \erpsoftsas\PseModulo::colMensaje($m) . " = ?
              WHERE {$m['pk']} = ?
                AND ISNULL({$m['pagado']}, 0) = 0 AND {$m['estado']} = 2
                AND ({$req} IS NULL OR ISNULL({$est}, '') NOT IN (" . self::listaSql(self::ESTADOS_EN_TRAMITE) . "))",
            [$requestId, self::MENSAJE_SESION_CREADA, (int) $idDeclaracion]
        );

        $fila = $con->obnerFila($con->consultar(
            "SELECT {$req} AS req FROM {$m['tabla']} WHERE {$m['pk']} = ?", [(int) $idDeclaracion]
        ));

        return isset($fila['req']) && (string) $fila['req'] === (string) $requestId;
    }

    /**
     * Valida la firma que PlacetoPay incluye en el POST del webhook.
     * Formula (no documentada en docs.placetopay.dev, confirmada por un
     * webhook de PlacetoPay ya usado en otro proyecto del equipo para
     * predial): hash(requestId + status.status + status.date + secretKey).
     * SHA-1 por defecto; si la firma trae el prefijo "sha256:", se usa
     * SHA-256 y se compara sin ese prefijo.
     */
    public static function validarFirmaWebhook(array $body) {
        $firmaRecibida = $body['signature'] ?? null;
        if (!$firmaRecibida) {
            return false;
        }

        $algoritmo = 'sha1';
        if (strpos($firmaRecibida, 'sha256:') === 0) {
            $algoritmo = 'sha256';
            $firmaRecibida = substr($firmaRecibida, 7);
        }

        $requestId = $body['requestId'] ?? '';
        $estado = $body['status']['status'] ?? '';
        $fecha = $body['status']['date'] ?? '';

        $firmaLocal = hash($algoritmo, $requestId . $estado . $fecha . self::secretKey());

        return hash_equals($firmaLocal, $firmaRecibida);
    }

    /**
     * Consulta el estado real de una sesión ya creada.
     * @return array Respuesta completa de PlacetoPay (status.status = APPROVED|PENDING|REJECTED|EXPIRED).
     */
    public static function consultarSesion($requestId) {
        $payload = ['auth' => self::auth()];
        $data = self::post(self::baseUrl() . '/session/' . (int) $requestId, $payload);

        return self::exigirSesion($data, $requestId);
    }

    /**
     * La respuesta tiene que traer la sesion pedida; si no, se lanza.
     *
     * Una respuesta SIN la sesion es un error del servicio, no el estado del
     * pago: credenciales que no son del ambiente (p. ej. a mitad del cambio a
     * produccion), el reloj del servidor corrido (la semilla es la hora), una
     * sesion que no existe alli... PlacetoPay contesta entonces
     * {"status":{"status":"FAILED",...}} sin requestId, y eso se anotaba en la
     * declaracion como si fuera el resultado: un pago que seguia pendiente en
     * el banco quedaba "FAILED", el contribuyente podia pagar otra vez y nadie
     * volvia a mirarlo. Se lanza, como un error de conexion: no se anota nada,
     * el aviso contesta 500 (PlacetoPay lo reintenta) y el cron lo vuelve a
     * consultar (revision 2026-10-06).
     */
    public static function exigirSesion($data, $requestId)
    {
        if (!is_array($data) || !isset($data['requestId'])
            || (string) $data['requestId'] !== (string) (int) $requestId) {
            $motivo = $data['status']['message'] ?? 'respuesta sin la sesión';
            throw new Exception('PlacetoPay no devolvió la sesión ' . (int) $requestId . ': ' . $motivo);
        }

        return $data;
    }

    /**
     * Interpreta la respuesta de consultarSesion() para actualizar la
     * declaracion. Centralizado aqui porque retorno.php, webhook.php y el
     * cron necesitan exactamente la misma lectura del resultado.
     *
     * Los nombres de campo dentro de "payment[0]" (franchise, issuerName,
     * authorization) son los que documenta PlacetoPay para el objeto
     * Transaction; se leen con fallback vacio por si PSE no trae alguno.
     */
    public static function interpretarRespuesta(array $respuesta) {
        $estado = $respuesta['status']['status'] ?? 'PENDING';
        $transaccion = $respuesta['payment'][0] ?? [];
        $banco = $transaccion['issuerName'] ?? $transaccion['franchise'] ?? 'PSE';

        return [
            'aprobado'      => $estado === 'APPROVED',
            'estado'        => $estado,

            // La sesion consultada: el estado solo se anota en la declaracion
            // que sigue teniendo ESTA sesion (Pasarela::aplicarADeclaracion). Un
            // aviso tardio de una sesion ya reemplazada no pisa a la nueva.
            'requestId'     => $respuesta['requestId'] ?? null,

            // Abierta y sin ningun intento de pago (el contribuyente no eligio
            // banco, o volvio atras): no hay nada nuevo que anotar. Asi se
            // conserva "Pago iniciado" con su hora, y el resumen sigue diciendo
            // cuanto falta para que venza (revision 2026-10-06).
            'sinIntento'    => $estado === 'PENDING' && empty($respuesta['payment']),
            // El recorte lo hace PagoDeclaracion, con el largo REAL de la
            // columna. Aqui se recortaba a 10 por un comentario que decia que
            // dec_BancoPago era VARCHAR(10); son 60, comprobado contra
            // INFORMATION_SCHEMA el 2026-08-25. Con el tope viejo, "Banco de
            // Bogota" se guardaba como "Banco de B".
            'banco'         => $banco,
            'autorizacion'  => $transaccion['authorization'] ?? '',
            'fecha'         => $respuesta['status']['date'] ?? date('c'),

            // Lo que cobro el banco. Desde el 2026-09-25 no siempre es el total
            // de la declaracion: una ICA vencida se paga con los intereses de
            // mora escritos en el resumen (crearSesion.php). null si no viene.
            'valor'         => self::_valorCobrado($respuesta, $transaccion),

            // El texto con que el banco explica un rechazo. Sin esto, un pago
            // rechazado solo se puede explicar entrando al panel de PlacetoPay.
            'mensaje'       => substr((string) ($transaccion['status']['message']
                                             ?? $respuesta['status']['message'] ?? ''), 0, 300),
        ];
    }

    /** El valor que cobro el banco: el de la transaccion, o el de la solicitud. */
    private static function _valorCobrado(array $respuesta, array $transaccion)
    {
        $v = $transaccion['amount']['to']['total']
          ?? $transaccion['amount']['from']['total']
          ?? $respuesta['request']['payment']['amount']['total']
          ?? null;
        return is_numeric($v) ? (float) $v : null;
    }


    /**
     * Deja en la declaracion el resultado que dio el banco.
     *
     * Hasta el 2026-08-25 los tres caminos que reciben un resultado -el
     * retorno del usuario, la notificacion del banco y el proceso de
     * respaldo- solo hacian algo si el estado era APPROVED. Un REJECTED, un
     * PENDING o un FAILED no dejaban rastro en ninguna parte, porque no habia
     * donde anotarlo: la declaracion seguia sin pagar y nadie podia saber si
     * fue un rechazo o si el pago estaba en tramite.
     *
     * En PSE colombiano eso importa: un pago puede quedarse PENDING durante
     * horas. El contribuyente cree que pago, la Alcaldia no ve nada.
     *
     * Ahora SIEMPRE se guarda el estado, y el pago se marca solo si el banco
     * lo aprobo. Las columnas las crea la migracion 014.
     *
     * Devuelve true si la declaracion quedo marcada como pagada.
     *
     * Desde la 042 la logica es la misma para las dos pasarelas y vive en
     * \erpsoftsas\Pasarela::aplicarADeclaracion; aqui solo se fija la via (PSE).
     * $m es el descriptor del modulo; si no viene, es ICA.
     */
    public static function aplicarADeclaracion($con, $idDeclaracion, array $info, $valor, $m = null)
    {
        require_once __DIR__ . '/class.pasarela.php';
        require_once __DIR__ . '/class.pagoDeclaracion.php';

        return \erpsoftsas\Pasarela::aplicarADeclaracion(
            $con, $idDeclaracion, $info, $valor, $m, \erpsoftsas\PagoDeclaracion::VIA_PSE
        );
    }
}
