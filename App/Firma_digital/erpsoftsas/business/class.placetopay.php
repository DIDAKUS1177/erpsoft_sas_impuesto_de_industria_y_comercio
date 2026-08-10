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

    private static function auth() {
        $seed = date('c');
        $nonceCrudo = random_bytes(16);
        $tranKey = base64_encode(hash('sha256', $nonceCrudo . $seed . PLACETOPAY_SECRETKEY, true));

        return [
            'login'   => PLACETOPAY_LOGIN,
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
    public static function crearSesion($referencia, $valor, $descripcion, $returnUrl) {
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
            'expiration'   => date('c', strtotime('+2 hours')),
            'returnUrl'    => $returnUrl,
            'ipAddress'    => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'userAgent'    => $_SERVER['HTTP_USER_AGENT'] ?? 'ERPSoftSAS-ICA-Paipa',
            'paymentMethod' => 'pse',
            'locale'       => 'es_CO',
        ];

        $data = self::post(PLACETOPAY_BASEURL . '/session', $payload);

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

        $firmaLocal = hash($algoritmo, $requestId . $estado . $fecha . PLACETOPAY_SECRETKEY);

        return hash_equals($firmaLocal, $firmaRecibida);
    }

    /**
     * Consulta el estado real de una sesión ya creada.
     * @return array Respuesta completa de PlacetoPay (status.status = APPROVED|PENDING|REJECTED|EXPIRED).
     */
    public static function consultarSesion($requestId) {
        $payload = ['auth' => self::auth()];
        return self::post(PLACETOPAY_BASEURL . '/session/' . (int) $requestId, $payload);
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

        return [
            'aprobado'      => $estado === 'APPROVED',
            'estado'        => $estado,
            'banco'         => $transaccion['issuerName'] ?? $transaccion['franchise'] ?? 'PSE',
            'autorizacion'  => $transaccion['authorization'] ?? '',
            'fecha'         => $respuesta['status']['date'] ?? date('c'),
        ];
    }
}
