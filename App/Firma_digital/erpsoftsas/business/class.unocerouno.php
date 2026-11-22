<?php

namespace erpsoftsas;

include_once __DIR__ . '/class.parametros.php';

/**
 * 1cero1pay (1Cero1 Software): el botón «PSE · Bancolombia».
 *
 * Es una SEGUNDA pasarela, simultánea con la de la entidad (migración 060):
 * Paipa cobra por AvalPay (PlacetoPay) y, además, por 1cero1pay. Esta clase
 * solo habla con el servicio y lee la configuración; los intentos de pago y lo
 * que cada uno le hace a su declaración viven en class.pagoUnoCeroUno.php.
 *
 * EL SERVICIO («Web Service Pasarela de Pagos – Trámites con Recaudo», 1Cero1,
 * septiembre de 2026; la misma integración del predial, psepaipa/src/UnoCeroUno.php)
 *
 *   POST Login/authenticate             {Username, Password} -> token (Bearer, 5 minutos)
 *   POST transaction/InsertTransaction  {Referencia, Factura, Total, CodigoEntidad,
 *                                        IDImpuesto, FuentePago, TipoImplementacion,
 *                                        Pagador: {TipoDocumento, Identificacion, Nombre,
 *                                        Email, Telefono}} -> Código, URL, Mensaje
 *   GET  transaction?CodigoEntidad=&Factura=&IDImpuesto=
 *                                       -> EstadoTransaccion (APROBADA, FALLIDA, RECHAZADA,
 *                                          COMENZADA, PENDIENTE), Total, CUS, Factura...
 *
 * No hay URL de notificación: un pago se confirma cuando el contribuyente
 * vuelve (retorno.php) y con la tarea programada (cron_verificar_pagos.php).
 *
 * LA CONFIGURACIÓN (conf_parametros, migración 060; se edita en Parámetros ICA >
 * Municipio y bancos, detrás de la contraseña de edición)
 *
 *   UNOCEROUNO_ACTIVO                 1 = se ofrece el botón
 *   UNOCEROUNO_USUARIO, _CLAVE        credenciales (sensibles: nunca se muestran)
 *   UNOCEROUNO_CODIGO_ENTIDAD         el código de la entidad en 1Cero1
 *   UNOCEROUNO_IDIMPUESTO_ICA         2   (Industria y Comercio y Avisos y Tableros)
 *   UNOCEROUNO_IDIMPUESTO_RETEICA     77  (Retención de Industria y Comercio)
 *   UNOCEROUNO_IDIMPUESTO_AUTORRETEICA 615 (Autorretención de ICA)
 *   UNOCEROUNO_FUENTE_PAGO            1 (Portal Web)
 *   UNOCEROUNO_TIPO_IMPLEMENTACION    7 (Sistema Tributario – Pasarela 1Cero1)
 *   UNOCEROUNO_CODIGO_SERVICIO        dato del convenio; la API no lo pide (se guarda por si acaso)
 *   UNOCEROUNO_DOCUMENTOS_PRUEBA      documentos de las cuentas que ven el botón; vacío = todos
 *   UNOCEROUNO_BANCO                  el banco (ind_bancos) con que se registra el pago
 *   UNOCEROUNO_SECRETO_ENLACE         secreto propio, aleatorio, para firmar el enlace de
 *                                     «Verificar estado del pago». No es una credencial de
 *                                     1cero1pay ni se ve en la pantalla (ver secretoEnlace)
 *
 * Usuario, clave y código de entidad pueden venir también de constantes del
 * mismo nombre en config.municipio.php (la tabla manda), como el convenio de
 * PlacetoPay. Sin los tres, sin UNOCEROUNO_ACTIVO = 1 o sin la migración 060 no
 * existe: ni botón, ni consultas, ni nada distinto para el municipio.
 *
 * La dirección del servicio NO es un parámetro de pantalla: quien pudiera
 * cambiarla podría apuntar las consultas a un servidor que "apruebe" pagos. Es
 * la de producción, salvo la constante UNOCEROUNO_API_BASE (solo en un config
 * o en las pruebas locales, con el simulador).
 */
class UnoCeroUno
{
    /** Nombre de la pasarela en ind_pagos_en_linea.pel_Pasarela. */
    const PASARELA = '1CERO1PAY';

    const API_PRODUCCION = 'https://www.1cero1pay.com/ApiPayment/api';

    /** IDImpuesto por defecto de cada módulo (los «ID Trámite» que entregó 1Cero1 a Paipa). */
    const IDIMPUESTO_POR_DEFECTO = ['ica' => 2, 'reteica' => 77, 'autorreteica' => 615];

    const FUENTE_PAGO_POR_DEFECTO         = 1;   // Portal Web
    const TIPO_IMPLEMENTACION_POR_DEFECTO = 7;   // Sistema Tributario – Pasarela 1Cero1

    /** El token dura 5 minutos; se renueva antes. */
    const SEGUNDOS_TOKEN = 240;

    /**
     * ind_IdTipoDocumento del sistema (1 C.C., 3 C.E., 4 pasaporte, 5 NIT) ->
     * TipoDocumento de 1cero1pay (1 cédula, 2 NIT, 3 cédula de extranjería).
     * El pasaporte no tiene equivalente en su documento: va como cédula.
     */
    const TIPOS_DOCUMENTO = [1 => 1, 3 => 3, 4 => 1, 5 => 2];

    /** Parámetro (sensible, oculto en la pantalla) con el secreto de los enlaces de retorno. */
    const PARAM_SECRETO_ENLACE = 'UNOCEROUNO_SECRETO_ENLACE';

    /** @var string|null el secreto de los enlaces, leído una vez por petición */
    private static $secretoEnlace = null;

    /** @var string|null token de la sesión con 1cero1pay */
    private static $token = null;
    /** @var int cuándo se pidió */
    private static $tokenHora = 0;

    /* ------------------------------------------------------------------ */
    /* Configuración                                                       */
    /* ------------------------------------------------------------------ */

    public static function usuario()
    {
        return Parametros::valorOConstante('UNOCEROUNO_USUARIO', 'UNOCEROUNO_USUARIO');
    }

    public static function clave()
    {
        return Parametros::valorOConstante('UNOCEROUNO_CLAVE', 'UNOCEROUNO_CLAVE');
    }

    public static function codigoEntidad()
    {
        return Parametros::valorOConstante('UNOCEROUNO_CODIGO_ENTIDAD', 'UNOCEROUNO_CODIGO_ENTIDAD', '/^[A-Za-z0-9_-]{1,10}$/');
    }

    /** El IDImpuesto del módulo (ica | reteica | autorreteica). */
    public static function idImpuesto($modulo)
    {
        $modulo = strtolower((string) $modulo);
        if (!isset(self::IDIMPUESTO_POR_DEFECTO[$modulo])) { return null; }
        $v = Parametros::valorOConstante('UNOCEROUNO_IDIMPUESTO_' . strtoupper($modulo), null, '/^[0-9]{1,9}$/');
        return $v === null ? self::IDIMPUESTO_POR_DEFECTO[$modulo] : (int) $v;
    }

    public static function fuentePago()
    {
        $v = Parametros::valorOConstante('UNOCEROUNO_FUENTE_PAGO', null, '/^[0-9]{1,4}$/');
        return $v === null ? self::FUENTE_PAGO_POR_DEFECTO : (int) $v;
    }

    public static function tipoImplementacion()
    {
        $v = Parametros::valorOConstante('UNOCEROUNO_TIPO_IMPLEMENTACION', null, '/^[0-9]{1,4}$/');
        return $v === null ? self::TIPO_IMPLEMENTACION_POR_DEFECTO : (int) $v;
    }

    /** El «Código Servicio» del convenio. La API no lo pide: se guarda por si hace falta. */
    public static function codigoServicio()
    {
        return Parametros::valorOConstante('UNOCEROUNO_CODIGO_SERVICIO', null, '/^[A-Za-z0-9_-]{1,20}$/');
    }

    /** Documentos de las cuentas de prueba; vacío = el botón lo ven todos. */
    public static function documentosPrueba()
    {
        $lista = Parametros::valor('UNOCEROUNO_DOCUMENTOS_PRUEBA');
        if ($lista === null) { return []; }
        $docs = [];
        foreach (explode(',', $lista) as $d) {
            $d = preg_replace('/\D/', '', $d);
            if ($d !== '') { $docs[] = ltrim($d, '0'); }
        }
        return array_values(array_unique($docs));
    }

    /** El banco (ind_bancos.ban_Id, activo) con que se registran estos pagos, o null. */
    public static function idBanco($con)
    {
        $v = Parametros::valor('UNOCEROUNO_BANCO');
        if ($v === null || !preg_match('/^[0-9]{1,9}$/', $v)) { return null; }
        require_once __DIR__ . '/class.bancosPago.php';
        $b = BancosPago::activo($con, (int) $v);
        return $b ? $b['id'] : null;
    }

    /**
     * El secreto con que se firma el enlace de «Verificar estado del pago».
     *
     * Es un valor aleatorio PROPIO de cada base (64 cifras hexadecimales), no
     * la clave de 1cero1pay: la firma viaja al navegador dentro del enlace, y
     * una credencial no se usa como llave de nada que salga del servidor
     * (revisión 2026-11-22). Lo crea la migración 060; si falta (una base
     * donde la 060 se aplicó antes de tenerlo, o alguien lo vació) se genera
     * aquí la primera vez. La escritura es condicional (solo si sigue vacío) y
     * después se RELEE: si dos peticiones lo generan a la vez, las dos terminan
     * con el mismo, el que quedó en la base.
     *
     * Se lee directo de la tabla, sin pasar por Parametros ni por constantes:
     * no depende de par_Estado ni se puede fijar desde un archivo.
     *
     * @return string|null null si no se pudo leer ni crear (entonces ningún enlace vale)
     */
    public static function secretoEnlace()
    {
        if (self::$secretoEnlace !== null) { return self::$secretoEnlace; }
        try {
            $con  = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
            $leer = function () use ($con) {
                $f = $con->obnerFila($con->consultar(
                    "SELECT par_Valor AS v FROM conf_parametros WHERE par_Clave = ?", [self::PARAM_SECRETO_ENLACE]
                ));
                $v = trim((string) ($f['v'] ?? ''));
                return preg_match('/^[0-9A-Fa-f]{32,128}$/', $v) ? $v : null;
            };
            $v = $leer();
            if ($v === null) {
                $nuevo = bin2hex(random_bytes(32));
                $con->consultar(
                    "UPDATE conf_parametros SET par_Valor = ?, par_FechaActualizacion = GETDATE()
                      WHERE par_Clave = ? AND (par_Valor IS NULL OR LEN(LTRIM(RTRIM(par_Valor))) < 32)",
                    [$nuevo, self::PARAM_SECRETO_ENLACE]
                );
                $con->consultar(
                    "INSERT INTO conf_parametros (par_Clave, par_Valor, par_Nombre, par_Descripcion, par_Patron, par_Sensible)
                     SELECT ?, ?, ?, ?, ?, 1
                      WHERE NOT EXISTS (SELECT 1 FROM conf_parametros WITH (UPDLOCK, HOLDLOCK) WHERE par_Clave = ?)",
                    [self::PARAM_SECRETO_ENLACE, $nuevo, 'PSE · Bancolombia (1cero1pay): secreto de los enlaces',
                     'Valor aleatorio interno con que se firman los enlaces de «Verificar estado del pago». No es una credencial de 1cero1pay y no se edita.',
                     '^[0-9A-Fa-f]{32,128}$', self::PARAM_SECRETO_ENLACE]
                );
                $v = $leer();   // el que quedó en la base, lo haya escrito esta petición u otra
            }
            self::$secretoEnlace = $v;
            return $v;
        } catch (\Throwable $e) {
            error_log('[1cero1pay] no se pudo leer ni crear el secreto de los enlaces: ' . $e->getMessage());
            return null;
        }
    }

    /** Olvida el secreto leído (solo hace falta si cambia dentro de la misma petición: las pruebas). */
    public static function olvidarSecretoEnlace()
    {
        self::$secretoEnlace = null;
    }

    /**
     * ¿Está la migración 060 (ind_pagos_en_linea con pel_IdImpuesto)? Se
     * pregunta una vez por petición, y solo cuando ya hay credenciales: un
     * municipio sin 1cero1pay no hace ni esta consulta.
     */
    public static function hayTabla()
    {
        static $hay = null;
        if ($hay === null) {
            $hay = false;
            try {
                if (class_exists('\\ConexionMysqlUsuariosSqlServer\\ConexionSQLServer')) {
                    $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
                    $f = $con->obnerFila($con->consultar(
                        "SELECT COL_LENGTH('dbo.ind_pagos_en_linea', 'pel_IdImpuesto') AS n", []
                    ));
                    $hay = !empty($f['n']);
                }
            } catch (\Throwable $e) {
                error_log('[1cero1pay] no se pudo comprobar la migración 060: ' . $e->getMessage());
            }
        }
        return $hay;
    }

    /** ¿Están las credenciales y la tabla? (con eso basta para CONSULTAR intentos ya creados). */
    public static function configurado()
    {
        return self::usuario() !== null && self::clave() !== null && self::codigoEntidad() !== null
            && self::hayTabla();
    }

    /** ¿Se ofrece? Configurado y con UNOCEROUNO_ACTIVO = 1. */
    public static function activo()
    {
        return Parametros::valor('UNOCEROUNO_ACTIVO') === '1' && self::configurado();
    }

    /**
     * ¿Ve este usuario el botón «PSE · Bancolombia»?
     *
     * Con documentos de prueba, solo las cuentas con uno de esos documentos
     * (conf_usuarios.usu_NumeroDocumento); sin ellos, todos. Con $idUsuario
     * null responde "¿lo ven todos?" (lo usa la herramienta de verificación).
     */
    public static function botonVisible($idUsuario = null)
    {
        if (!self::activo()) { return false; }

        $docs = self::documentosPrueba();
        if (!$docs) { return true; }

        $idUsuario = (int) $idUsuario;
        if ($idUsuario <= 0) { return false; }

        static $docDe = [];
        if (!array_key_exists($idUsuario, $docDe)) {
            $docDe[$idUsuario] = '';
            try {
                $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
                $f = $con->obnerFila($con->consultar(
                    "SELECT usu_NumeroDocumento AS d FROM conf_usuarios WHERE usu_Id = ?", [$idUsuario]
                ));
                $docDe[$idUsuario] = ltrim(preg_replace('/\D/', '', (string) ($f['d'] ?? '')), '0');
            } catch (\Throwable $e) {
                error_log('[1cero1pay] no se pudo leer el documento del usuario ' . $idUsuario . ': ' . $e->getMessage());
            }
        }
        return $docDe[$idUsuario] !== '' && in_array($docDe[$idUsuario], $docs, true);
    }

    /** Texto del botón de los listados. */
    public static function textoBoton()
    {
        return 'PSE · Bancolombia';
    }

    /** Nombre para el contribuyente ("Pago seguro procesado por ..."). */
    public static function nombre()
    {
        return '1cero1pay (PSE · Bancolombia)';
    }

    /** Dirección del servicio, sin barra final (ver la cabecera). */
    public static function apiBase()
    {
        if (defined('UNOCEROUNO_API_BASE') && trim((string) UNOCEROUNO_API_BASE) !== '') {
            return rtrim(trim((string) UNOCEROUNO_API_BASE), '/');
        }
        return self::API_PRODUCCION;
    }

    /**
     * ¿La dirección a la que se manda al contribuyente es de 1cero1pay? Por
     * https y en su dominio; o en el mismo servidor del servicio cuando se usa
     * UNOCEROUNO_API_BASE (el simulador de las pruebas).
     */
    public static function urlDePagoValida($url)
    {
        $url   = trim((string) $url);
        $host  = strtolower((string) parse_url($url, PHP_URL_HOST));
        $https = strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
        if ($host === '' || !preg_match('#^https?://#i', $url)) { return false; }

        if ($https && ($host === '1cero1pay.com' || substr($host, -strlen('.1cero1pay.com')) === '.1cero1pay.com')) {
            return true;
        }
        if (defined('UNOCEROUNO_API_BASE')) {
            return $host === strtolower((string) parse_url(self::apiBase(), PHP_URL_HOST));
        }
        return false;
    }

    /* ------------------------------------------------------------------ */
    /* El servicio                                                         */
    /* ------------------------------------------------------------------ */

    /**
     * Una petición. Devuelve [código HTTP, cuerpo decodificado (o el texto)].
     * Ni las credenciales ni la respuesta van a los mensajes de error: terminan
     * en el registro del servidor.
     */
    private static function _http($metodo, $ruta, $cuerpo = null, $token = null)
    {
        $ch = curl_init(self::apiBase() . $ruta);
        $cabeceras = ['Accept: application/json'];
        if ($cuerpo !== null) { $cabeceras[] = 'Content-Type: application/json'; }
        if ($token !== null)  { $cabeceras[] = 'Authorization: Bearer ' . $token; }
        $op = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $cabeceras,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_CUSTOMREQUEST  => $metodo,
        ];
        if ($cuerpo !== null) { $op[CURLOPT_POSTFIELDS] = json_encode($cuerpo, JSON_UNESCAPED_UNICODE); }
        curl_setopt_array($ch, $op);
        $r = curl_exec($ch);
        if ($r === false) {
            $e = curl_error($ch);
            curl_close($ch);
            throw new \Exception('Sin conexión con 1cero1pay: ' . $e);
        }
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $d = json_decode((string) $r, true);
        return [$http, $d === null ? trim((string) $r) : $d];
    }

    /** Olvida el token (para pedir otro). */
    public static function olvidarToken()
    {
        self::$token = null;
        self::$tokenHora = 0;
    }

    /**
     * El token de la sesión con 1cero1pay (dura 5 minutos: se reutiliza menos
     * que eso dentro del mismo proceso).
     *
     * @throws \Exception si no lo entrega
     */
    public static function token()
    {
        if (self::$token !== null && (time() - self::$tokenHora) < self::SEGUNDOS_TOKEN) {
            return self::$token;
        }
        $usuario = self::usuario();
        $clave   = self::clave();
        if ($usuario === null || $clave === null) {
            throw new \Exception('1cero1pay no está configurado (faltan las credenciales).');
        }

        [$http, $r] = self::_http('POST', '/Login/authenticate', ['Username' => $usuario, 'Password' => $clave]);
        if ($http < 200 || $http >= 300) {
            throw new \Exception('1cero1pay no aceptó las credenciales (HTTP ' . $http . ').');
        }
        // El documento dice "Token"; el sistema anterior usaba el cuerpo entero.
        if (is_array($r)) {
            $r = self::campo($r, ['Token', 'access_token', 'accessToken', 'jwt'])
              ?? self::campo(self::_contenido($r), ['Token', 'access_token', 'accessToken', 'jwt']) ?? '';
        }
        $t = is_string($r) ? trim($r, " \t\n\r\"") : '';
        if ($t === '' || preg_match('/\s/', $t)) {
            throw new \Exception('1cero1pay no entregó el token.');
        }
        self::$token     = $t;
        self::$tokenHora = time();
        return $t;
    }

    /**
     * Una petición autenticada. Si el servicio contesta 401 (token vencido), se
     * pide otro token y se repite UNA vez.
     *
     * @return mixed el cuerpo decodificado
     * @throws \Exception con cualquier respuesta que no sea 2xx
     */
    private static function _pedir($metodo, $ruta, $cuerpo = null)
    {
        [$http, $r] = self::_http($metodo, $ruta, $cuerpo, self::token());
        if ($http === 401) {
            self::olvidarToken();
            [$http, $r] = self::_http($metodo, $ruta, $cuerpo, self::token());
        }
        if ($http < 200 || $http >= 300) {
            throw new \Exception('1cero1pay respondió HTTP ' . $http . '.');
        }
        return $r;
    }

    /** Quita el sobre de la respuesta: {data:{value}}, {data}, {value}. */
    private static function _contenido($d)
    {
        if (!is_array($d)) { return []; }
        $data = self::campo($d, ['data']);
        if (is_array($data)) {
            $valor = self::campo($data, ['value']);
            return is_array($valor) ? $valor : $data;
        }
        $valor = self::campo($d, ['value']);
        return is_array($valor) ? $valor : $d;
    }

    /**
     * Un campo de la respuesta sin importar mayúsculas ni tildes ("Código",
     * "codigo", "URL", "Url"): el documento y el servicio no siempre coinciden.
     */
    public static function campo($a, array $nombres)
    {
        if (!is_array($a)) { return null; }
        $norm = function ($k) {
            return strtolower(strtr((string) $k, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
                                                  'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u']));
        };
        $mapa = [];
        foreach ($a as $k => $v) { $mapa[$norm($k)] = $v; }
        foreach ($nombres as $n) {
            $n = $norm($n);
            if (array_key_exists($n, $mapa) && $mapa[$n] !== null) { return $mapa[$n]; }
        }
        return null;
    }

    /**
     * Crea la transacción y devuelve a dónde mandar al contribuyente.
     *
     * @param array $d factura (≤18), referencia (≤30), centavos, modulo, pagador
     *                 (tipoDocumento, identificacion, nombre, email, telefono)
     * @return array ['url' => string, 'id' => string]
     * @throws \Exception si no la crea o la dirección no es de 1cero1pay
     */
    public static function crearTransaccion(array $d)
    {
        $idImpuesto = isset($d['idImpuesto']) ? (int) $d['idImpuesto'] : self::idImpuesto($d['modulo'] ?? '');
        $factura    = (string) ($d['factura'] ?? '');
        $referencia = (string) ($d['referencia'] ?? '');
        $centavos   = (int) ($d['centavos'] ?? 0);
        if ($idImpuesto === null || $idImpuesto <= 0 || $factura === '' || strlen($factura) > 18
            || $referencia === '' || strlen($referencia) > 30 || $centavos <= 0) {
            throw new \Exception('Datos incompletos para crear la transacción en 1cero1pay.');
        }

        $p = $d['pagador'] ?? [];
        $r = self::_contenido(self::_pedir('POST', '/transaction/InsertTransaction', [
            'Referencia'         => $referencia,
            'Factura'            => $factura,
            // En pesos: entero si no hay centavos (los totales de las declaraciones lo son).
            'Total'              => $centavos % 100 === 0 ? intdiv($centavos, 100) : round($centavos / 100, 2),
            'CodigoEntidad'      => (string) self::codigoEntidad(),
            'IDImpuesto'         => $idImpuesto,
            'FuentePago'         => self::fuentePago(),
            'TipoImplementacion' => self::tipoImplementacion(),
            'Pagador'            => [
                'TipoDocumento'  => (int) ($p['tipoDocumento'] ?? 1),
                'Identificacion' => (string) ($p['identificacion'] ?? ''),
                'Nombre'         => (string) ($p['nombre'] ?? ''),
                'Email'          => (string) ($p['email'] ?? ''),
                'Telefono'       => (string) ($p['telefono'] ?? ''),
            ],
        ]));

        $url = trim((string) (self::campo($r, ['URL']) ?? ''));
        $id  = self::campo($r, ['IDTransaccion', 'idTransaccion', 'TransactionID', 'Codigo', 'Code']);
        $id  = is_scalar($id) ? trim((string) $id) : '';
        if ($url === '') {
            // El mensaje de 1cero1pay va al registro, no a la pantalla.
            $msj = self::campo($r, ['Mensaje', 'Message']);
            throw new \Exception('1cero1pay no creó la transacción: ' . mb_substr(is_scalar($msj) ? (string) $msj : 'sin detalle', 0, 150));
        }
        if (!self::urlDePagoValida($url)) {
            throw new \Exception('1cero1pay devolvió una dirección de pago que no es de su dominio.');
        }
        return ['url' => $url, 'id' => mb_substr($id, 0, 60)];
    }

    /**
     * Las transacciones que 1cero1pay tiene de una factura (un arreglo, aunque
     * el servicio conteste una sola). Vacío si no tiene ninguna.
     *
     * @throws \Exception si no se pudo preguntar (sin conexión, 4xx, 5xx): eso
     *                    NO es "no hay pago"
     */
    public static function consultar($factura, $idImpuesto)
    {
        $q = http_build_query([
            'CodigoEntidad' => (string) self::codigoEntidad(),
            'Factura'       => (string) $factura,
            'IDImpuesto'    => (int) $idImpuesto,
        ]);
        $d = self::_contenido(self::_pedir('GET', '/transaction?' . $q));
        if (!$d) { return []; }
        return array_key_exists(0, $d) ? array_values(array_filter($d, 'is_array')) : [$d];
    }

    /** Estados de 1cero1pay en que la transacción ya no cambia. */
    const FINALES = ['APROBADA', 'RECHAZADA', 'FALLIDA'];

    /**
     * El estado, con los cinco nombres del documento. Solo "aprobada" con todas
     * sus letras es un pago.
     *
     * Lo que NO se reconoce («NO AUTORIZADA», «ABANDONADA», vacío...) es
     * DESCONOCIDO, no PENDIENTE: no se registra nada y el intento queda como
     * estaba, con el texto original guardado para verlo. Tratarlo como
     * PENDIENTE lo dejaba frenando la declaración para siempre -a 1cero1pay, a
     * la otra pasarela y al pago manual-, porque un PENDIENTE no caduca
     * (revisión 2026-11-22).
     */
    public static function estado($texto)
    {
        $t = strtoupper(trim(strtr((string) $texto, ['á' => 'A', 'é' => 'E', 'í' => 'I', 'ó' => 'O', 'ú' => 'U'])));
        if (in_array($t, ['APROBADA', 'APROBADO', 'APPROVED'], true)) { return 'APROBADA'; }
        if (in_array($t, ['RECHAZADA', 'RECHAZADO', 'REJECTED', 'DECLINADA', 'DECLINED'], true)) { return 'RECHAZADA'; }
        if (in_array($t, ['FALLIDA', 'FALLIDO', 'FAILED', 'EXPIRADA', 'EXPIRED', 'VENCIDA', 'ANULADA', 'CANCELADA'], true)) { return 'FALLIDA'; }
        if (in_array($t, ['COMENZADA', 'COMENZADO', 'INICIADA', 'CREADA'], true)) { return 'COMENZADA'; }
        if (in_array($t, ['PENDIENTE', 'PENDING'], true)) { return 'PENDIENTE'; }
        return 'DESCONOCIDO';
    }

    /** Lo que importa de una transacción, con los nombres que usa el resto del flujo. */
    public static function interpretar(array $tx)
    {
        $original = self::campo($tx, ['EstadoTransaccion', 'Estado']);
        $original = is_scalar($original) ? trim((string) $original) : '';
        $estado   = self::estado($original);

        $total    = self::campo($tx, ['Total']);
        $centavos = (is_int($total) || is_float($total) || (is_string($total) && is_numeric(trim($total))))
                  ? (int) round(((float) $total) * 100) : null;

        $texto = function (array $nombres, $largo) use ($tx) {
            $v = self::campo($tx, $nombres);
            return is_scalar($v) ? mb_substr(trim((string) $v), 0, $largo) : '';
        };
        // El medio termina en pantallas (*_BancoPago): solo letras, cifras y espacios.
        $medio = trim(preg_replace('/[^\p{L}\p{N} ._-]/u', '', $texto(['MedioPago'], 30)));

        return [
            'aprobado'       => $estado === 'APROBADA',
            'estado'         => $estado,
            'estadoOriginal' => mb_substr($original, 0, 40),
            'final'          => in_array($estado, self::FINALES, true),
            'factura'        => $texto(['Factura'], 40),
            'referencia'     => $texto(['Referencia'], 60),
            'centavos'       => $centavos,
            'valor'          => $centavos === null ? null : $centavos / 100,
            'cus'            => $texto(['CUS'], 40),
            'medio'          => $medio,
            'banco'          => '1cero1pay' . ($medio !== '' ? ' - ' . $medio : ''),
            'id'             => $texto(['IDTransaccion', 'idTransaccion', 'TransactionID'], 60),
            'fecha'          => $texto(['FechaRespuestaBanco'], 40) ?: $texto(['FechaTransaccion'], 40),
            'identificacion' => $texto(['Identificacion'], 30),
            'mensaje'        => $texto(['Mensaje'], 200),
        ];
    }

    /**
     * Para la herramienta de verificación: ¿acepta 1cero1pay las credenciales?
     * Solo pide un token; no crea ni consulta ninguna transacción.
     *
     * @return array ['ok' => bool, 'detalle' => string] (sin credenciales ni token)
     */
    public static function probarCredenciales()
    {
        try {
            self::olvidarToken();
            self::token();
            return ['ok' => true, 'detalle' => 'el servicio entrego el token'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'detalle' => $e->getMessage()];
        }
    }
}
