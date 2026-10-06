<?php

namespace erpsoftsas;

include_once __DIR__ . '/class.parametros.php';

/**
 * Wompi (Bancolombia): cobro en línea por Web Checkout.
 *
 * Documentación: docs.wompi.co/docs/colombia/ -> widget-checkout-web (pago),
 * eventos (avisos), seguimiento-de-transacciones (consulta), ambientes-y-llaves.
 *
 * CÓMO SE COBRA
 *
 * Aquí no se escribe nada del banco ni de la tarjeta: el contribuyente va a la
 * página de Wompi (checkout.wompi.co/p/) con la referencia del intento, el
 * valor en centavos y una FIRMA DE INTEGRIDAD:
 *
 *     SHA-256(referencia + centavos + moneda + vencimiento + secreto de integridad)
 *
 * Sin ella Wompi no cobra, y con ella nadie puede cambiar el valor ni la
 * referencia en el camino: el secreto nunca sale del servidor.
 *
 * CÓMO SE CONFIRMA (tres caminos, como con PlacetoPay)
 *
 *  - Wompi devuelve al contribuyente a retorno.php, agregando ?id=<transacción>.
 *  - Wompi avisa a wompi_eventos.php (evento transaction.updated), con un
 *    checksum = SHA-256(valores de signature.properties + timestamp + secreto
 *    de eventos).
 *  - El cron de respaldo pregunta por los intentos que siguen abiertos.
 *
 * En los tres, lo que se guarda sale de una CONSULTA propia a Wompi con la
 * llave privada (GET /transactions/{id}), nunca de la URL ni del aviso: un
 * aviso con firma válida solo dice "pregunte". Wompi ya no responde consultas
 * con la llave pública (da 404).
 *
 * Las llaves son de cada entidad y viven en conf_parametros (migración 042),
 * como el convenio de PlacetoPay: se cambian en Municipio y bancos sin desplegar.
 */
class Wompi
{
    const PARAM_PUBLICA    = 'WOMPI_LLAVE_PUBLICA';
    const PARAM_PRIVADA    = 'WOMPI_LLAVE_PRIVADA';
    const PARAM_INTEGRIDAD = 'WOMPI_SECRETO_INTEGRIDAD';
    const PARAM_EVENTOS    = 'WOMPI_SECRETO_EVENTOS';

    const URL_CHECKOUT   = 'https://checkout.wompi.co/p/';
    const API_PRUEBAS    = 'https://sandbox.wompi.co/v1';
    const API_PRODUCCION = 'https://production.wompi.co/v1';

    const MONEDA = 'COP';

    /** Minutos que vale el enlace de pago (expiration-time). */
    const MINUTOS_VIGENCIA = 30;

    /** Estados en que Wompi ya no cambia la transacción. */
    const FINALES = ['APPROVED', 'DECLINED', 'VOIDED', 'ERROR'];

    public static function llavePublica()
    {
        return Parametros::valorOConstante(self::PARAM_PUBLICA, self::PARAM_PUBLICA, '/^pub_(test|prod)_[A-Za-z0-9]{8,120}$/');
    }

    public static function llavePrivada()
    {
        return Parametros::valorOConstante(self::PARAM_PRIVADA, self::PARAM_PRIVADA, '/^prv_(test|prod)_[A-Za-z0-9]{8,120}$/');
    }

    public static function secretoIntegridad()
    {
        return Parametros::valorOConstante(self::PARAM_INTEGRIDAD, self::PARAM_INTEGRIDAD, '/^(test|prod)_integrity_[A-Za-z0-9]{8,120}$/');
    }

    public static function secretoEventos()
    {
        return Parametros::valorOConstante(self::PARAM_EVENTOS, self::PARAM_EVENTOS, '/^(test|prod)_events_[A-Za-z0-9]{8,120}$/');
    }

    /**
     * 'test' o 'prod'. Manda la llave PRIVADA, que es la que viaja al API; si
     * no está, la pública. Así, dejar en blanco la pública (la única que la
     * pantalla deja vaciar) no manda la privada de producción al sandbox.
     */
    public static function ambiente()
    {
        $privada = self::llavePrivada();
        if ($privada !== null) { return strpos($privada, 'prv_prod_') === 0 ? 'prod' : 'test'; }
        $publica = self::llavePublica();
        if ($publica !== null) { return strpos($publica, 'pub_prod_') === 0 ? 'prod' : 'test'; }
        return null;
    }

    /**
     * ¿Están las cuatro llaves, y todas del MISMO ambiente?
     *
     * Una llave de pruebas con un secreto de producción no da error en ninguna
     * parte: Wompi rechaza la firma, o los avisos no pasan la verificación y los
     * pagos solo se confirman con el cron. Mejor no ofrecer el botón.
     */
    public static function configurado()
    {
        $amb = self::ambiente();
        if ($amb === null) { return false; }

        $publica = self::llavePublica();
        $privada = self::llavePrivada();
        $integ   = self::secretoIntegridad();
        $eventos = self::secretoEventos();

        return $publica !== null && $privada !== null && $integ !== null && $eventos !== null
            && strpos($publica, 'pub_' . $amb . '_') === 0
            && strpos($privada, 'prv_' . $amb . '_') === 0
            && strpos($integ, $amb . '_integrity_') === 0
            && strpos($eventos, $amb . '_events_') === 0;
    }

    /**
     * Dirección del API según el ambiente de las llaves. La constante
     * WOMPI_API_BASE (solo en un config o en las pruebas locales) la reemplaza;
     * a propósito no es un parámetro de la pantalla: quien pudiera cambiarla
     * podría apuntar las consultas a un servidor que "apruebe" pagos.
     */
    public static function apiBase()
    {
        // Con llaves de producción la constante no cuenta: la llave privada
        // real solo viaja a production.wompi.co.
        if (self::ambiente() === 'prod') { return self::API_PRODUCCION; }
        if (defined('WOMPI_API_BASE')) { return rtrim((string) WOMPI_API_BASE, '/'); }
        return self::API_PRUEBAS;
    }

    /** Vía con que se registra un pago: la de pruebas nunca se confunde con uno real. */
    public static function via()
    {
        require_once __DIR__ . '/class.pagoDeclaracion.php';
        return self::ambiente() === 'prod' ? PagoDeclaracion::VIA_WOMPI : PagoDeclaracion::VIA_WOMPI_PRUEBA;
    }

    /** SHA-256(referencia + centavos + moneda [+ vencimiento] + secreto de integridad). */
    public static function firmaIntegridad($referencia, $centavos, $moneda, $vencimiento = null, $secreto = null)
    {
        $secreto = $secreto ?? self::secretoIntegridad();
        return hash('sha256', $referencia . (int) $centavos . $moneda . ($vencimiento ?? '') . $secreto);
    }

    /** Vencimiento del enlace, en el formato de Wompi (ISO 8601 en UTC). */
    public static function vencimiento($desde = null)
    {
        return gmdate('Y-m-d\TH:i:s.000\Z', ($desde ?? time()) + self::MINUTOS_VIGENCIA * 60);
    }

    /**
     * La página de pago de Wompi para un intento.
     *
     * @param array $datos referencia, centavos, redirect, vencimiento (opcional),
     *                     cliente (opcional: email, nombre, documento, tipoDocumento)
     */
    public static function urlCheckout(array $datos)
    {
        $centavos    = (int) $datos['centavos'];
        $vencimiento = $datos['vencimiento'] ?? null;

        $params = [
            'public-key'          => self::llavePublica(),
            'currency'            => self::MONEDA,
            'amount-in-cents'     => (string) $centavos,
            'reference'           => (string) $datos['referencia'],
            'signature:integrity' => self::firmaIntegridad((string) $datos['referencia'], $centavos, self::MONEDA, $vencimiento),
            'redirect-url'        => (string) $datos['redirect'],
        ];
        if ($vencimiento !== null) { $params['expiration-time'] = $vencimiento; }

        // El comprobante de Wompi le llega al correo REGISTRADO del contribuyente,
        // no al de quien opera (un funcionario que paga por él).
        $cliente = $datos['cliente'] ?? [];
        $mapa = ['email' => 'customer-data:email', 'nombre' => 'customer-data:full-name',
                 'documento' => 'customer-data:legal-id', 'tipoDocumento' => 'customer-data:legal-id-type'];
        foreach ($mapa as $k => $p) {
            if (isset($cliente[$k]) && trim((string) $cliente[$k]) !== '') { $params[$p] = trim((string) $cliente[$k]); }
        }

        return self::URL_CHECKOUT . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /** GET al API con la llave privada. null si Wompi dice que no existe (404). */
    private static function _get($ruta)
    {
        $privada = self::llavePrivada();
        if ($privada === null) { throw new \Exception('Wompi no está configurado (falta la llave privada).'); }

        $ch = curl_init(self::apiBase() . $ruta);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Accept: application/json', 'Authorization: Bearer ' . $privada],
            CURLOPT_TIMEOUT        => 20,
        ]);
        $cuerpo = curl_exec($ch);
        if ($cuerpo === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception('Error de conexión con Wompi: ' . $error);
        }
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http === 404) { return null; }
        $data = json_decode($cuerpo, true);
        // Ni la llave ni la respuesta van al mensaje: termina en el registro.
        if ($http < 200 || $http >= 300 || !is_array($data)) {
            throw new \Exception('Wompi respondió HTTP ' . $http . ' a la consulta.');
        }
        return $data;
    }

    /** La transacción tal como la tiene Wompi, o null si no existe. */
    public static function consultarTransaccion($idTransaccion)
    {
        $idTransaccion = trim((string) $idTransaccion);
        if (!preg_match('/^[A-Za-z0-9-]{1,60}$/', $idTransaccion)) { return null; }

        $r = self::_get('/transactions/' . rawurlencode($idTransaccion));
        return (is_array($r) && isset($r['data']['id'])) ? $r['data'] : null;
    }

    /**
     * Las transacciones de una referencia. Lo usa el cron cuando nunca se supo
     * el id: el contribuyente cerró la ventana sin volver y el aviso no llegó.
     */
    public static function transaccionesDeReferencia($referencia)
    {
        $referencia = (string) $referencia;
        $r = self::_get('/transactions?reference=' . rawurlencode($referencia));
        // Un 404 aquí NO es "no hay pagos": es que Wompi no reconoce la consulta
        // (llave privada equivocada, otro ambiente). Tratarlo como lista vacía
        // cerraba como SIN_PAGO intentos que sí se pagaron (revisión 2026-10-02).
        if ($r === null || !array_key_exists('data', $r)) {
            throw new \Exception('Wompi no respondió la búsqueda por referencia (¿llave privada correcta?).');
        }
        $d = $r['data'];
        $lista = isset($d['id']) ? [$d] : (is_array($d) ? array_values(array_filter($d, 'is_array')) : []);
        // Solo las de ESA referencia, por si el filtro de Wompi fuera más amplio.
        return array_values(array_filter($lista, function ($t) use ($referencia) {
            return (string) ($t['reference'] ?? '') === $referencia;
        }));
    }

    /**
     * ¿El aviso viene de Wompi? Recalcula el checksum con el secreto de eventos.
     *
     * Las propiedades firmadas las dice el propio aviso (signature.properties,
     * p. ej. "transaction.id"), y se leen dentro de "data". Si llegó también la
     * cabecera X-Event-Checksum, tiene que coincidir.
     */
    public static function validarEvento(array $evento, $cabecera = null, $secreto = null)
    {
        $secreto  = $secreto ?? self::secretoEventos();
        $props    = $evento['signature']['properties'] ?? null;
        $recibido = $evento['signature']['checksum'] ?? null;

        if ($secreto === null || !is_array($props) || !$props || !isset($evento['timestamp'])
            || !is_string($recibido) || $recibido === '') {
            return false;
        }

        $cadena = '';
        foreach ($props as $ruta) {
            $v = $evento['data'] ?? null;
            foreach (explode('.', (string) $ruta) as $parte) {
                if (!is_array($v) || !array_key_exists($parte, $v)) { return false; }
                $v = $v[$parte];
            }
            if (is_array($v)) { return false; }
            $cadena .= is_bool($v) ? ($v ? 'true' : 'false') : (string) $v;
        }
        $calculado = hash('sha256', $cadena . $evento['timestamp'] . $secreto);

        if (!hash_equals($calculado, strtolower(trim($recibido)))) { return false; }
        if ($cabecera !== null && trim((string) $cabecera) !== ''
            && !hash_equals($calculado, strtolower(trim((string) $cabecera)))) {
            return false;
        }
        return true;
    }

    /** Lo que importa de una transacción, con los nombres que usa el resto del flujo. */
    public static function interpretar(array $tx)
    {
        $estado   = strtoupper(trim((string) ($tx['status'] ?? 'PENDING')));
        $medio    = strtoupper(trim((string) ($tx['payment_method_type'] ?? '')));
        // El medio termina en pantallas (*_BancoPago): solo nombres como los de
        // Wompi (CARD, PSE, NEQUI, BANCOLOMBIA_TRANSFER...).
        if ($medio !== '' && !preg_match('/^[A-Z_]{1,30}$/', $medio)) { $medio = 'OTRO'; }
        $centavos = (isset($tx['amount_in_cents']) && is_numeric($tx['amount_in_cents'])) ? (int) $tx['amount_in_cents'] : null;

        return [
            'aprobado'   => $estado === 'APPROVED',
            'estado'     => $estado,
            'final'      => in_array($estado, self::FINALES, true),
            // Va a *_BancoPago: el medio dice más que "Wompi" a secas. Con
            // llaves de pruebas lo dice, para que no pase por un pago real.
            'banco'      => (self::ambiente() === 'prod' ? 'Wompi' : 'Wompi PRUEBAS') . ($medio !== '' ? ' - ' . $medio : ''),
            'medio'      => $medio,
            'id'         => (string) ($tx['id'] ?? ''),
            'referencia' => (string) ($tx['reference'] ?? ''),
            'moneda'     => strtoupper((string) ($tx['currency'] ?? '')),
            'centavos'   => $centavos,
            'valor'      => $centavos === null ? null : $centavos / 100,
            'fecha'      => (string) ($tx['finalized_at'] ?? $tx['created_at'] ?? ''),
            'mensaje'    => mb_substr((string) ($tx['status_message'] ?? ''), 0, 300),
        ];
    }
}
