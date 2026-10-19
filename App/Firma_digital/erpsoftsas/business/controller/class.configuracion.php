<?php

namespace erpsoftsas;

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.conexionSqlServer.php';
include_once SERVER . '/business/controller/class.cabecera.php';
include_once SERVER . '/business/class.permisosRol.php';

/**
 * Configuración del municipio: parámetros, cuentas de los bancos y cuentas
 * contables del pago manual (funciones 8 a 11, migración 051).
 *
 * POR QUÉ EXISTE
 *
 * El EAN de recaudo y los días de vigencia del código de barras viven en
 * conf_parametros desde la migración 009 —Javier lo pidió así, "se debe
 * configurar en una tabla para poderlo cambiar en caso que sea necesario,
 * cada entidad tiene su propio EAN"—, pero no había ninguna pantalla: la
 * única forma de cambiarlos era entrar a la base con SQL. Lo mismo con las
 * cuentas contable y recaudadora de los 25 bancos, que están las 25 vacías y
 * hacen falta para cuadrar el recaudo.
 *
 * Pedir un cambio de configuración por correo, para que alguien corra un
 * UPDATE a mano en producción, es exactamente el tipo de operación donde se
 * escribe en la base equivocada.
 *
 * QUIÉN PUEDE ENTRAR
 *
 * Quien tenga "Municipio y bancos" en el panel de Roles (solo cuenta en un rol
 * de la Alcaldía). Un contribuyente no tiene nada que hacer aquí: el EAN
 * gobierna el código de barras con el que el banco recauda, y cambiarlo rompe
 * el pago de todo el municipio.
 *
 * VALIDACIÓN
 *
 * conf_parametros trae una columna par_Patron con una expresión regular por
 * parámetro. Se respeta: el EAN son 13 dígitos y meter 12 produce un código de
 * barras que el escáner del banco rechaza en ventanilla, con el contribuyente
 * delante. Vale más rechazarlo aquí.
 */
class ControladorConfiguracion extends \erpsoftsas\Cabecera
{
    private $_funcion;
    private $_ok = 0;
    private $_mensaje = '';

    /*
     * CONTRASEÑA DE EDICIÓN (pedido del dueño, 2026-09-24: "para editar esto,
     * que es más denso, pon una contraseña").
     *
     * Ver no la pide; guardar un PARÁMETRO sí, y se exige aquí en run(), en el
     * servidor: un campo habilitado a mano en el navegador no sirve de nada. Al
     * acertarla, la edición queda abierta MINUTOS_DESBLOQUEO minutos para ESTA
     * sesión. Tras INTENTOS_MAXIMOS fallos seguidos hay que esperar
     * MINUTOS_ESPERA. Las cuentas de los bancos no la piden (cliente,
     * 2026-09-30): las cambia quien tenga "Municipio y bancos", y queda quién.
     * Las cuentas contables (051) tampoco, por lo mismo.
     *
     * Solo se guarda el hash; la contraseña la tiene el dueño y no se escribe en
     * el código ni en la documentación del repositorio. Para cambiarla, un
     * municipio define MUNICIPIO_CLAVE_PARAMETROS_HASH en su config.municipio.php
     * con el resultado de password_hash('la-nueva', PASSWORD_DEFAULT).
     */
    const CLAVE_EDICION_HASH = '$2y$10$rql2bsvA95XkgAD65GMaZedzfyKcmDLTSRskoZyRdYD08.MRHZo92';
    const MINUTOS_DESBLOQUEO = 15;
    const INTENTOS_MAXIMOS   = 5;
    const MINUTOS_ESPERA     = 5;

    public static function run()
    {
        $_obj = new self();
        $_obj->_funcion = isset($_POST['funcion']) ? $_POST['funcion'] : null;

        // Punto único de control de acceso: son quince líneas de configuración
        // que gobiernan el recaudo de todo el municipio.
        if (!self::_esAlcaldia()) {
            header('Content-type: application/json');
            echo json_encode([
                'ok' => 0,
                'mensaje' => empty($_SESSION['id_usuario']) ? 'Debe iniciar sesión.'
                           : \erpsoftsas\PermisosRol::mensaje('parametros.municipio'),
                'datos' => [],
                // Si el rechazo es porque la sesion vencio, dist/menu.php lleva al
                // login con aviso (como en contribuyentes y establecimientos) en
                // vez de dejar la pantalla diciendo que "solo la Alcaldia puede".
                'sinSesion' => empty($_SESSION['id_usuario']) ? 1 : 0
            ]);
            return;
        }

        // Lo que guarda (2 y 4) solo se acepta desde la pantalla: jQuery manda
        // X-Requested-With y un formulario de otro sitio no puede ponerlo, así
        // que una página ajena no cambia una cuenta con la sesión abierta de un
        // funcionario. Para las cuentas eso lo cuidaba el candado.
        if (in_array((int) $_obj->_funcion, [2, 4, 9, 10, 11], true) && !self::_desdeLaPantalla()) {
            header('Content-type: application/json');
            echo json_encode([
                'ok' => 0,
                'mensaje' => 'No se pudo guardar: hágalo desde la pantalla de Municipio y bancos.',
                'datos' => []
            ]);
            return;
        }

        // Guardar un PARÁMETRO (función 2) exige además la edición desbloqueada
        // con la contraseña: el EAN y las claves de PSE gobiernan el recaudo.
        // Las cuentas de los bancos (función 4) no: son las del recibo de pago y
        // el cliente pidió que la Alcaldía las cambie sin pedírselas a nadie
        // (2026-09-30). Basta "Municipio y bancos", y queda quién (ban_ActualizadoPor).
        if ((int) $_obj->_funcion === 2 && !self::_edicionDesbloqueada()) {
            header('Content-type: application/json');
            echo json_encode([
                'ok' => 0,
                'mensaje' => 'Para guardar cambios, desbloquee la edición con la contraseña.',
                'datos' => ['requiereClave' => true]
            ]);
            return;
        }

        try {
            $respuesta = null;
            switch ($_obj->_funcion) {
                case 1:
                    $respuesta = $_obj->_consultarParametros();
                    break;
                case 2:
                    $respuesta = $_obj->_guardarParametro();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarBancos();
                    break;
                case 4:
                    $respuesta = $_obj->_guardarCuentasBanco();
                    break;
                case 5:
                    $respuesta = $_obj->_desbloquearEdicion();
                    break;
                case 6:
                    $_obj->_ok = 1;
                    $respuesta = self::_estadoEdicion();
                    break;
                case 7:
                    unset($_SESSION['config_desbloqueo_hasta']);
                    $_obj->_ok = 1;
                    $_obj->_mensaje = 'Edición bloqueada.';
                    $respuesta = self::_estadoEdicion();
                    break;
                // Cuentas contables (migración 051). Como las cuentas de los
                // bancos, sin la contraseña de edición; queda quién y cuándo.
                case 8:
                    $respuesta = $_obj->_consultarCuentasContables();
                    break;
                case 9:
                    $respuesta = $_obj->_agregarCuentaContable();
                    break;
                case 10:
                    $respuesta = $_obj->_guardarDescripcionCuenta();
                    break;
                case 11:
                    $respuesta = $_obj->_quitarCuentaContable();
                    break;
                default:
                    $_obj->_mensaje = 'Función no válida';
            }

            header('Content-type: application/json');
            echo json_encode([
                'ok' => $_obj->_ok,
                'mensaje' => $_obj->_mensaje,
                'datos' => $respuesta ?? []
            ]);

        } catch (\Exception $e) {
            // Sin este catch, cualquier fallo de SQL sale como un 500 con el
            // cuerpo vacío y la pantalla solo sabe decir "error de conexión".
            error_log('[configuracion] ' . $e->getMessage());
            header('Content-type: application/json');
            echo json_encode([
                'ok' => 0,
                'mensaje' => 'No se pudo completar la operación. Intente de nuevo.',
                'datos' => []
            ]);
        }
    }

    /**
     * Quien tiene "Municipio y bancos" (panel de Roles, 2026-09-29; antes los
     * roles 1 y 2 por numero). Guardar un parámetro pide además la contraseña
     * de edición; guardar las cuentas de los bancos, no.
     */
    private static function _esAlcaldia()
    {
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }

        if (empty($_SESSION['id_usuario'])) { return false; }

        return \erpsoftsas\PermisosRol::tiene('parametros.municipio');
    }

    /** La petición viene de la pantalla (jQuery), no de un formulario de otro sitio. */
    private static function _desdeLaPantalla()
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /** ind_bancos.ban_ActualizadoPor existe desde la migración 041. */
    private static function _hayAutorBancos($con)
    {
        $f = $con->obnerFila($con->consultar(
            "SELECT COL_LENGTH('dbo.ind_bancos', 'ban_ActualizadoPor') AS l", []
        ));
        return !empty($f['l']);
    }

    /* ==================== CONTRASEÑA DE EDICIÓN ==================== */

    private static function _hashClave()
    {
        return defined('MUNICIPIO_CLAVE_PARAMETROS_HASH')
            ? MUNICIPIO_CLAVE_PARAMETROS_HASH
            : self::CLAVE_EDICION_HASH;
    }

    private static function _edicionDesbloqueada()
    {
        return !empty($_SESSION['config_desbloqueo_hasta'])
            && (int) $_SESSION['config_desbloqueo_hasta'] > time();
    }

    /** Lo que la pantalla necesita para pintar el candado. */
    private static function _estadoEdicion()
    {
        $abierta = self::_edicionDesbloqueada();
        return [
            'desbloqueada' => $abierta,
            'segundos'     => $abierta ? (int) $_SESSION['config_desbloqueo_hasta'] - time() : 0,
        ];
    }

    private function _desbloquearEdicion()
    {
        $ahora = time();

        if (!empty($_SESSION['config_espera_hasta']) && (int) $_SESSION['config_espera_hasta'] > $ahora) {
            $minutos = (int) ceil(((int) $_SESSION['config_espera_hasta'] - $ahora) / 60);
            $this->_mensaje = 'Demasiados intentos fallidos. Intente de nuevo en '
                            . $minutos . ($minutos === 1 ? ' minuto.' : ' minutos.');
            return self::_estadoEdicion();
        }

        $clave = (string) ($_POST['clave'] ?? '');
        if ($clave !== '' && password_verify($clave, self::_hashClave())) {
            unset($_SESSION['config_intentos'], $_SESSION['config_espera_hasta']);
            $_SESSION['config_desbloqueo_hasta'] = $ahora + self::MINUTOS_DESBLOQUEO * 60;
            $this->_ok = 1;
            $this->_mensaje = 'Edición desbloqueada por ' . self::MINUTOS_DESBLOQUEO . ' minutos.';
            return self::_estadoEdicion();
        }

        $intentos = (int) ($_SESSION['config_intentos'] ?? 0) + 1;
        if ($intentos >= self::INTENTOS_MAXIMOS) {
            $_SESSION['config_intentos'] = 0;
            $_SESSION['config_espera_hasta'] = $ahora + self::MINUTOS_ESPERA * 60;
            $this->_mensaje = 'Contraseña incorrecta. Por seguridad, espere '
                            . self::MINUTOS_ESPERA . ' minutos para volver a intentarlo.';
        } else {
            $_SESSION['config_intentos'] = $intentos;
            $restan = self::INTENTOS_MAXIMOS - $intentos;
            $this->_mensaje = 'Contraseña incorrecta. '
                            . ($restan === 1 ? 'Queda 1 intento.' : 'Quedan ' . $restan . ' intentos.');
        }
        error_log('[configuracion] contraseña de edición incorrecta; usuario ' . (int) ($_SESSION['id_usuario'] ?? 0));

        return self::_estadoEdicion();
    }

    private function _consultarParametros()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        /*
         * UN PARAMETRO SENSIBLE NO SALE DE AQUI.
         *
         * par_Sensible (migracion 023) marca los valores que no se pueden
         * mostrar: hoy, la clave secreta del convenio de recaudo. De esos se
         * manda si estan puestos o no, nunca el valor.
         *
         * Sin esto, mover un secreto del archivo del servidor a la base seria
         * un retroceso y no una mejora: quedaria a la vista de cualquier
         * usuario con rol 1 o 2, viajando en un JSON y pintado en una casilla
         * de texto del navegador. El archivo de hoy, al menos, solo lo ve
         * quien entra al servidor.
         *
         * COALESCE porque una instalacion sin la migracion 023 no tiene la
         * columna... salvo que entonces la consulta ni compila. Se comprueba
         * antes si existe, y si no, se tratan todos como no sensibles: es el
         * comportamiento que habia antes de la migracion.
         */
        $haySensible = (bool) $con->obnerFila($con->consultar(
            "SELECT 1 AS x FROM sys.columns
              WHERE object_id = OBJECT_ID('dbo.conf_parametros')
                AND name = 'par_Sensible'", []
        ));

        $columnaSensible = $haySensible ? 'par_Sensible' : 'CAST(0 AS BIT) AS par_Sensible';

        $stmt = $con->consultar(
            "SELECT par_Id, par_Clave, par_Valor, par_Nombre, par_Descripcion,
                    par_Patron, par_FechaActualizacion, $columnaSensible
               FROM conf_parametros
              WHERE ISNULL(par_Estado, 1) = 1
              ORDER BY par_Clave",
            []
        );

        $lista = [];
        while ($f = $con->obnerFila($stmt)) {
            if ($f['par_FechaActualizacion'] instanceof \DateTime) {
                $f['par_FechaActualizacion'] = $f['par_FechaActualizacion']->format('Y-m-d H:i');
            }

            $f['par_Sensible'] = (int) !empty($f['par_Sensible']);

            if ($f['par_Sensible']) {
                // Se sustituye por la unica informacion que la pantalla
                // necesita: si hay algo guardado.
                $f['par_Puesto'] = (int) (trim((string) $f['par_Valor']) !== '');
                // De las llaves de Wompi (042) se dice ademas si son de pruebas o
                // de produccion: lo dice su prefijo, que no es secreto, y sin
                // eso una mezcla apaga el boton sin que se sepa por que.
                if (strpos((string) $f['par_Clave'], 'WOMPI_') === 0 && $f['par_Puesto']) {
                    $v = trim((string) $f['par_Valor']);
                    $f['par_Ambiente'] = preg_match('/^(prv_)?prod_/', $v) ? 'producción'
                                       : (preg_match('/^(prv_)?test_/', $v) ? 'pruebas' : null);
                }
                $f['par_Valor']  = '';
            } else {
                $f['par_Puesto'] = (int) (trim((string) $f['par_Valor']) !== '');
            }

            // El banco de la pasarela se elige de la lista de bancos (045).
            if ($f['par_Clave'] === 'PASARELA_BANCO') {
                require_once SERVER . '/business/class.bancosPago.php';
                $f['par_Opciones'] = array_map(function ($b) {
                    return ['valor' => (string) $b['id'], 'texto' => $b['nombre'] . ($b['cuenta'] !== '' ? ' (cuenta ' . $b['cuenta'] . ')' : '')];
                }, \erpsoftsas\BancosPago::lista($con));
            }

            $lista[] = $f;
        }

        $this->_ok = 1;
        $this->_mensaje = count($lista) . ' parámetro(s)';
        return $lista;
    }

    private function _guardarParametro()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $id    = (int) ($_POST['par_Id'] ?? 0);
        $valor = trim((string) ($_POST['par_Valor'] ?? ''));

        if ($id <= 0) {
            $this->_mensaje = 'No se indicó el parámetro';
            return [];
        }

        $haySensible = (bool) $con->obnerFila($con->consultar(
            "SELECT 1 AS x FROM sys.columns
              WHERE object_id = OBJECT_ID('dbo.conf_parametros')
                AND name = 'par_Sensible'", []
        ));
        $columnaSensible = $haySensible ? 'par_Sensible' : 'CAST(0 AS BIT) AS par_Sensible';

        $par = $con->obnerFila($con->consultar(
            "SELECT par_Clave, par_Nombre, par_Patron, $columnaSensible
               FROM conf_parametros WHERE par_Id = ?",
            [$id]
        ));
        if (!$par) {
            $this->_mensaje = 'El parámetro no existe';
            return [];
        }

        $sensible = !empty($par['par_Sensible']);

        /*
         * DEJAR UN SECRETO EN BLANCO NO LO BORRA.
         *
         * La pantalla nunca muestra el valor de un parametro sensible, asi que
         * la casilla llega siempre vacia. Si eso se guardara tal cual, abrir
         * Configuracion y pulsar Guardar en cualquier otra fila borraria la
         * clave del convenio sin que nadie lo pretendiera, y los pagos
         * dejarian de funcionar sin mas explicacion.
         *
         * Vacio significa "no lo estoy cambiando". Para borrarlo de verdad se
         * hace desde la base, que es una operacion deliberada y rara.
         *
         * En los parametros normales el vacio SI se guarda: es como se apaga
         * un parametro opcional, y asi funcionaba antes.
         */
        if ($sensible && $valor === '') {
            $this->_ok = 1;
            $this->_mensaje = 'La clave no se modificó (dejarla en blanco no la borra).';
            return ['par_Id' => $id];
        }

        /*
         * El patrón vive en la base, uno por parámetro. Un valor vacío se
         * admite siempre: es como se apaga un parámetro opcional
         * -RECAUDO_DIAS_VIGENCIA vacío significa "no imprimir la fecha de
         * vencimiento en el código de barras"-.
         */
        $patron = trim((string) ($par['par_Patron'] ?? ''));
        if ($valor !== '' && $patron !== '') {
            /*
             * El patrón se guarda CON sus anclas (^...$) y sin delimitadores:
             * comprobado en la base, RECAUDO_EAN trae '^[0-9]{13}$'. Aquí solo
             * se le ponen. Añadirle otras anclas produciría '^^...$$', que hoy
             * funciona por casualidad pero se rompe en cuanto un patrón use
             * alternancia.
             *
             * EL DELIMITADOR NO PUEDE SER LA BARRA.
             *
             * Era '/' . $patron . '/', y eso rompe cualquier patrón que
             * contenga una barra — que es justo lo que necesita un patrón de
             * URL. Con '^https://...' PHP cierra la expresión en la primera
             * barra interna y lo que sigue lo lee como modificadores: la
             * expresión queda inválida, preg_match devuelve false y el valor
             * BUENO se rechaza con "no tiene el formato esperado".
             *
             * Encontrado el 2026-08-26 al añadir PASARELA_BASEURL: nadie
             * habría podido guardar la dirección de la pasarela. No se vio
             * antes porque el único patrón que existía, el del EAN, son trece
             * dígitos y no lleva barras.
             *
             * Se usa '#', y se escapa por si algún patrón futuro lo trae. En
             * PCRE '\#' es simplemente '#', así que escaparlo nunca cambia lo
             * que la expresión significa.
             *
             * Si aun así el patrón estuviera mal escrito, preg_match devuelve
             * false y el valor se rechaza: ante una regla que no se entiende,
             * no dejar pasar es lo seguro.
             */
            $expresion = '#' . str_replace('#', '\#', $patron) . '#';

            if (!@preg_match($expresion, $valor)) {
                $this->_mensaje = 'El valor de "' . $par['par_Nombre'] . '" no tiene el formato esperado.';
                return [];
            }
        }

        /*
         * FECHA LIMITE DEL ICA: EL PATRON NO BASTA (revision 2026-09-28).
         *
         * El patron DD/MM deja pasar "31/02" o "30/02", y aqui se contestaba
         * "Parametro actualizado"; pero VencimientoICA::fechaLimite las descarta
         * con checkdate y usa el 30/04 SIN AVISAR, asi que la Alcaldia creia haber
         * movido la fecha y el sistema seguia con otra. Se valida contra un año NO
         * bisiesto: el 29/02 existiria solo uno de cada cuatro años, y los otros
         * tres el sistema volveria en silencio al 30/04. Se guarda como DD/MM.
         */
        if ($par['par_Clave'] === 'ICA_FECHA_LIMITE' && $valor !== '') {
            if (!preg_match('#^(\d{1,2})/(\d{1,2})$#', $valor, $m) || !checkdate((int) $m[2], (int) $m[1], 2025)) {
                $this->_mensaje = 'La fecha "' . $valor . '" no existe en todos los años. Escríbala como '
                                . 'día/mes, por ejemplo 30/04 (el 29/02 no sirve: no existe en tres de cada cuatro años).';
                return [];
            }
            $valor = sprintf('%02d/%02d', $m[1], $m[2]);
        }

        // El banco de la pasarela tiene que ser un banco activo del catálogo.
        $bancoPasarela = null;
        $bancoAnterior = null;
        if ($par['par_Clave'] === 'PASARELA_BANCO') {
            $antes = $con->obnerFila($con->consultar("SELECT par_Valor FROM conf_parametros WHERE par_Id = ?", [$id]));
            $bancoAnterior = (int) ($antes['par_Valor'] ?? 0);
        }
        if ($par['par_Clave'] === 'PASARELA_BANCO' && $valor !== '') {
            require_once SERVER . '/business/class.bancosPago.php';
            $bancoPasarela = \erpsoftsas\BancosPago::activo($con, (int) $valor);
            if (!$bancoPasarela) {
                $this->_mensaje = 'Elija un banco activo de la lista.';
                return [];
            }
        }

        $con->consultar(
            "UPDATE conf_parametros
                SET par_Valor = ?, par_FechaActualizacion = GETDATE()
              WHERE par_Id = ?",
            [$valor, $id]
        );

        // Los pagos en línea anteriores sin banco, o con el que estaba elegido,
        // quedan con este (la entidad tiene una sola pasarela).
        $completados = $bancoPasarela
            ? \erpsoftsas\BancosPago::completarPagosEnLinea($con, $bancoPasarela['id'], $bancoAnterior)
            : 0;

        /*
         * Queda constancia de quien cambio que: el EAN gobierna el recaudo de
         * todo el municipio y un cambio silencioso ahi es dificil de rastrear.
         *
         * De un parametro sensible se anota QUE se cambio, no A QUE: escribir
         * la clave del convenio en el log del servidor la dejaria en un archivo
         * de texto que se rota, se copia y se manda a soporte.
         */
        error_log(sprintf('[configuracion] usuario %s cambio %s a %s',
            $_SESSION['id_usuario'] ?? '?',
            $par['par_Clave'],
            $sensible ? '(valor no registrado por ser sensible)' : '"' . $valor . '"'));

        $this->_ok = 1;
        $this->_mensaje = $sensible ? 'Clave actualizada' : 'Parámetro actualizado';
        if ($completados > 0) {
            $this->_mensaje .= '. ' . $completados . ($completados === 1 ? ' pago en línea anterior quedó' : ' pagos en línea anteriores quedaron')
                             . ' con ' . $bancoPasarela['nombre'] . '.';
        }

        // Un valor sensible tampoco vuelve en la respuesta.
        return $sensible ? ['par_Id' => $id] : ['par_Id' => $id, 'par_Valor' => $valor];
    }

    private function _consultarBancos()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        // Último cambio de las cuentas: fecha y, con la 041, quién. Sin la
        // contraseña, es lo que deja ver un cambio que nadie esperaba.
        $hayAutor = self::_hayAutorBancos($con);
        $autor = $hayAutor
            ? "LTRIM(RTRIM(ISNULL(u.usu_Nombres, '') + ' ' + ISNULL(u.usu_Apellidos, '')))"
            : "CAST(NULL AS VARCHAR(1))";
        $union = $hayAutor ? "LEFT JOIN conf_usuarios u ON u.usu_Id = b.ban_ActualizadoPor" : "";

        $stmt = $con->consultar(
            "SELECT b.ban_Id, b.ban_Codigo, b.ban_Nombre, b.ban_Asobancaria,
                    b.ban_CuentaContable, b.ban_CuentaRecaudadora, b.ban_Activo,
                    CONVERT(VARCHAR(16), b.ban_FechaActualizacion, 120) AS ban_FechaActualizacion,
                    $autor AS ban_ActualizadoPorNombre
               FROM ind_bancos b
               $union
              ORDER BY b.ban_Codigo",
            []
        );

        $lista = [];
        while ($f = $con->obnerFila($stmt)) { $lista[] = $f; }

        $this->_ok = 1;
        $this->_mensaje = count($lista) . ' banco(s)';
        return $lista;
    }

    private function _guardarCuentasBanco()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $id = (int) ($_POST['ban_Id'] ?? 0);
        if ($id <= 0) {
            $this->_mensaje = 'No se indicó el banco';
            return [];
        }

        $banco = $con->obnerFila($con->consultar(
            "SELECT ban_Id, ban_Nombre, ban_CuentaRecaudadora, ban_Activo FROM ind_bancos WHERE ban_Id = ?", [$id]
        ));
        if (!$banco) {
            $this->_mensaje = 'El banco no existe';
            return [];
        }

        $contable    = trim((string) ($_POST['ban_CuentaContable'] ?? ''));
        $recaudadora = trim((string) ($_POST['ban_CuentaRecaudadora'] ?? ''));

        /*
         * Una cuenta bancaria o contable son dígitos, y a veces guiones o
         * puntos como separadores. Se rechaza cualquier otra cosa: estos
         * números terminan en un archivo que va al banco y en el recibo de
         * pago, y una letra ahí hace rebotar el archivo entero. Con al menos un
         * dígito ("-" o "." solos saldrían en el recibo como si fueran una
         * cuenta). Hasta 30, que es lo que cabe en la columna (con 31 la base
         * cortaba el guardado con un error sin explicación).
         */
        foreach ([['contable', $contable], ['recaudadora', $recaudadora]] as $par) {
            if ($par[1] === '') { continue; }
            if (!preg_match('/^[0-9.\- ]+$/', $par[1])) {
                $this->_mensaje = 'La cuenta ' . $par[0] . ' solo admite números, guiones y puntos.';
                return [];
            }
            if (!preg_match('/[0-9]/', $par[1])) {
                $this->_mensaje = 'La cuenta ' . $par[0] . ' debe tener al menos un número.';
                return [];
            }
            if (strlen($par[1]) > 30) {
                $this->_mensaje = 'La cuenta ' . $par[0] . ' admite hasta 30 caracteres.';
                return [];
            }
        }

        // La cuenta contable de una sola por banco ya no está en la pantalla:
        // ahora son varias por banco y año (Cuentas contables, 051). Si no
        // llega, se deja como está.
        $conContable = array_key_exists('ban_CuentaContable', $_POST);
        $valores = $conContable ? [$contable !== '' ? $contable : null] : [];
        $valores[] = $recaudadora !== '' ? $recaudadora : null;
        $autor = '';
        if (self::_hayAutorBancos($con)) {
            $autor = ', ban_ActualizadoPor = ?';
            $valores[] = (int) ($_SESSION['id_usuario'] ?? 0) ?: null;
        }
        $valores[] = $id;

        $con->consultar(
            "UPDATE ind_bancos
                SET " . ($conContable ? 'ban_CuentaContable = ?, ' : '') . "ban_CuentaRecaudadora = ?,
                    ban_FechaActualizacion = GETDATE()$autor
              WHERE ban_Id = ?",
            $valores
        );

        $antes = trim((string) ($banco['ban_CuentaRecaudadora'] ?? ''));
        error_log(sprintf('[configuracion] usuario %s cambio las cuentas de %s (recaudadora: "%s" -> "%s")',
            $_SESSION['id_usuario'] ?? '?', $banco['ban_Nombre'], $antes, $recaudadora));

        $this->_ok = 1;
        $this->_mensaje = 'Cuentas actualizadas.';
        if ($recaudadora !== $antes) {
            // Un banco inactivo no sale en el recibo aunque tenga cuenta.
            if (empty($banco['ban_Activo'])) {
                $this->_mensaje .= ' Este banco está inactivo: no sale en el recibo de pago.';
            } else {
                $this->_mensaje .= $recaudadora !== ''
                    ? ' El recibo de pago ya sale con esta cuenta.'
                    : ' Este banco ya no sale en el recibo de pago.';
            }
        }
        return ['ban_Id' => $id];
    }

    /* =====================================================================
     * CUENTAS CONTABLES (migración 051)
     *
     * Las cuentas con las que se hace el ingreso de cada pago manual: varias
     * por banco y por año, en ind_bancos_cuenta (la tabla que creó Javier en
     * Paipa). Pedido de Paipa (2026-10-19): con auditoría de qué administrador
     * la creó y cuándo (creador, fecha_creador). Como las cuentas de los
     * bancos, no piden la contraseña de edición.
     * ===================================================================== */

    private function _hayCuentasContables($con)
    {
        require_once SERVER . '/business/class.bancosPago.php';
        if (\erpsoftsas\BancosPago::hayCuentasContables($con)) { return true; }
        $this->_mensaje = 'Las cuentas contables aún no están instaladas en este municipio (falta la migración 051).';
        return false;
    }

    /** La cuenta como se escribe: dígitos y, de separadores, guiones, puntos o espacios. */
    private static function _leerCuenta($texto)
    {
        $cuenta = trim((string) $texto);
        if ($cuenta === '') { return 'Escriba el número de la cuenta contable.'; }
        if (!preg_match('/^[0-9.\- ]+$/', $cuenta) || !preg_match('/[0-9]/', $cuenta)) {
            return 'La cuenta contable solo admite números, guiones y puntos.';
        }
        if (strlen($cuenta) > 70) { return 'La cuenta contable admite hasta 70 caracteres.'; }
        return ['cuenta' => $cuenta];
    }

    /** Descripción opcional, sin caracteres de control; hasta 100. */
    private static function _leerDescripcion($texto)
    {
        $d = trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string) $texto) ?? '');
        if (mb_strlen($d) > 100) { return 'La descripción admite hasta 100 caracteres.'; }
        return ['descripcion' => $d];
    }

    /** Función 8: las cuentas de un año (el actual si no se pide otro), los años y los bancos. */
    private function _consultarCuentasContables()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
        if (!$this->_hayCuentasContables($con)) { return []; }

        $hoy  = \erpsoftsas\BancosPago::anioCuentas();
        $anio = (int) ($_POST['anio'] ?? 0);
        if ($anio < 2000 || $anio > $hoy + 1) { $anio = $hoy; }

        // Los años con cuentas, el actual y el siguiente (para cargarlas antes).
        $anios = [$hoy, $hoy + 1];
        $st = $con->consultar("SELECT DISTINCT ano FROM ind_bancos_cuenta", []);
        while ($f = $con->obnerFila($st)) { $anios[] = (int) $f['ano']; }
        $anios = array_values(array_unique($anios));
        rsort($anios);

        // "usuario N" (un usuario de más de 20 caracteres) se muestra con su nombre.
        $st = $con->consultar(
            "SELECT c.Id AS id, c.Id_banco AS idBanco, b.ban_Nombre AS banco, LTRIM(RTRIM(c.cuenta)) AS cuenta,
                    LTRIM(RTRIM(ISNULL(c.descripcion, ''))) AS descripcion,
                    COALESCE(NULLIF(LTRIM(RTRIM(ISNULL(u.usu_Nombres, '') + ' ' + ISNULL(u.usu_Apellidos, ''))), ''), c.creador) AS creador,
                    CONVERT(VARCHAR(16), c.fecha_creador, 120) AS fecha,
                    (SELECT COUNT(*) FROM ind_pagos_manuales p WHERE p.pma_IdCuentaContable = c.Id) AS usos
               FROM ind_bancos_cuenta c
               LEFT JOIN ind_bancos b ON b.ban_Id = c.Id_banco
               LEFT JOIN conf_usuarios u ON c.creador = 'usuario ' + CAST(u.usu_Id AS VARCHAR(12))
              WHERE c.ano = ?
              ORDER BY b.ban_Nombre, c.cuenta",
            [$anio]
        );
        $cuentas = [];
        while ($f = $con->obnerFila($st)) {
            $f['id'] = (int) $f['id']; $f['idBanco'] = (int) $f['idBanco']; $f['usos'] = (int) $f['usos'];
            $cuentas[] = $f;
        }

        // En diciembre, aviso si el año siguiente todavía no tiene cuentas: el
        // 1 de enero el pago manual dejaría de pedirlas.
        $siguiente = $con->obnerFila($con->consultar("SELECT COUNT(*) AS n FROM ind_bancos_cuenta WHERE ano = ?", [$hoy + 1]));
        $mes = (int) (new \DateTime('now', new \DateTimeZone('America/Bogota')))->format('n');

        $this->_ok = 1;
        $this->_mensaje = count($cuentas) . ' cuenta(s)';
        return [
            'anio' => $anio, 'anioActual' => $hoy, 'anios' => $anios, 'cuentas' => $cuentas,
            'bancos' => \erpsoftsas\BancosPago::lista($con),
            'faltaSiguiente' => $mes === 12 && (int) ($siguiente['n'] ?? 0) === 0,
        ];
    }

    /** Función 9: agrega una cuenta (banco activo, año, número y descripción). */
    private function _agregarCuentaContable()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
        if (!$this->_hayCuentasContables($con)) { return []; }

        $banco = \erpsoftsas\BancosPago::activo($con, (int) ($_POST['idBanco'] ?? 0));
        if (!$banco) { $this->_mensaje = 'Elija el banco de la cuenta.'; return []; }

        $hoy  = \erpsoftsas\BancosPago::anioCuentas();
        $anio = (int) ($_POST['anio'] ?? 0);
        if ($anio < 2000 || $anio > $hoy + 1) {
            $this->_mensaje = 'El año de la cuenta debe estar entre 2000 y ' . ($hoy + 1) . '.';
            return [];
        }

        $c = self::_leerCuenta($_POST['cuenta'] ?? '');
        if (is_string($c)) { $this->_mensaje = $c; return []; }
        $d = self::_leerDescripcion($_POST['descripcion'] ?? '');
        if (is_string($d)) { $this->_mensaje = $d; return []; }

        $repetida = $con->obnerFila($con->consultar(
            "SELECT COUNT(*) AS n FROM ind_bancos_cuenta WHERE Id_banco = ? AND ano = ? AND LTRIM(RTRIM(cuenta)) = ?",
            [$banco['id'], $anio, $c['cuenta']]
        ));
        if ((int) ($repetida['n'] ?? 0) > 0) {
            $this->_mensaje = 'La cuenta ' . $c['cuenta'] . ' ya está en ' . $banco['nombre'] . ' para ' . $anio . '.';
            return [];
        }

        // creador: el usuario con el que entró (VARCHAR(20), como lo dejó Javier).
        // Uno más largo (un correo) no se recorta, que dejaría la auditoría
        // ambigua: va "usuario N" y la pantalla muestra su nombre.
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        $u = $con->obnerFila($con->consultar("SELECT usu_Usuario FROM conf_usuarios WHERE usu_Id = ?", [$idUsuario]));
        $login = trim((string) ($u['usu_Usuario'] ?? ''));
        $creador = ($login !== '' && strlen($login) <= 20) ? $login : 'usuario ' . $idUsuario;

        $con->consultar(
            "INSERT INTO ind_bancos_cuenta (Id_banco, ano, cuenta, creador, fecha_creador, descripcion)
             VALUES (?, ?, ?, ?, GETDATE(), ?)",
            [$banco['id'], $anio, $c['cuenta'], $creador, $d['descripcion'] !== '' ? $d['descripcion'] : null]
        );
        error_log(sprintf('[configuracion] usuario %d agrego la cuenta contable %s de %s (%d)',
            $idUsuario, $c['cuenta'], $banco['nombre'], $anio));

        $this->_ok = 1;
        $this->_mensaje = 'Cuenta contable agregada: ' . $banco['nombre'] . ' · ' . $c['cuenta'] . ' (' . $anio . ').';
        return ['anio' => $anio];
    }

    /** Función 10: cambia la descripción de una cuenta (el número no: se quita y se agrega). */
    private function _guardarDescripcionCuenta()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
        if (!$this->_hayCuentasContables($con)) { return []; }

        $id = (int) ($_POST['id'] ?? 0);
        $d  = self::_leerDescripcion($_POST['descripcion'] ?? '');
        if (is_string($d)) { $this->_mensaje = $d; return []; }

        $f = $con->obnerFila($con->consultar(
            "SET NOCOUNT ON; UPDATE ind_bancos_cuenta SET descripcion = ? WHERE Id = ?; SELECT @@ROWCOUNT AS n;",
            [$d['descripcion'] !== '' ? $d['descripcion'] : null, $id]
        ));
        if ((int) ($f['n'] ?? 0) !== 1) { $this->_mensaje = 'La cuenta contable no existe.'; return []; }

        $this->_ok = 1;
        $this->_mensaje = 'Descripción guardada.';
        return ['id' => $id];
    }

    /** Función 11: quita una cuenta que ningún pago usó; la usada se queda (los pagos la nombran). */
    private function _quitarCuentaContable()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
        if (!$this->_hayCuentasContables($con)) { return []; }

        $id = (int) ($_POST['id'] ?? 0);
        $c = $con->obnerFila($con->consultar(
            "SELECT c.cuenta, c.ano, b.ban_Nombre AS banco,
                    (SELECT COUNT(*) FROM ind_pagos_manuales p WHERE p.pma_IdCuentaContable = c.Id) AS usos
               FROM ind_bancos_cuenta c LEFT JOIN ind_bancos b ON b.ban_Id = c.Id_banco WHERE c.Id = ?", [$id]
        ));
        if (!$c) { $this->_mensaje = 'La cuenta contable no existe.'; return []; }
        if ((int) $c['usos'] > 0) {
            $this->_mensaje = 'La cuenta ' . trim((string) $c['cuenta']) . ' ya se usó en ' . (int) $c['usos']
                            . ((int) $c['usos'] === 1 ? ' pago manual' : ' pagos manuales') . ': no se puede quitar.';
            return [];
        }

        // En el mismo DELETE, otra vez "sin usar": un pago pudo guardarse con
        // ella entre la consulta de arriba y aquí.
        $borrada = $con->obnerFila($con->consultar(
            "SET NOCOUNT ON;
             DELETE FROM ind_bancos_cuenta
              WHERE Id = ? AND NOT EXISTS (SELECT 1 FROM ind_pagos_manuales p WHERE p.pma_IdCuentaContable = ?);
             SELECT @@ROWCOUNT AS n;", [$id, $id]
        ));
        if ((int) ($borrada['n'] ?? 0) !== 1) {
            $this->_mensaje = 'La cuenta acaba de usarse en un pago manual: no se quitó.';
            return [];
        }
        error_log(sprintf('[configuracion] usuario %s quito la cuenta contable %s de %s (%d)',
            $_SESSION['id_usuario'] ?? '?', trim((string) $c['cuenta']), $c['banco'], (int) $c['ano']));

        $this->_ok = 1;
        $this->_mensaje = 'Cuenta contable quitada.';
        return ['id' => $id];
    }
}

\erpsoftsas\ControladorConfiguracion::run();
