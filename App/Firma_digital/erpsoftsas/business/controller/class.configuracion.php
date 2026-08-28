<?php

namespace erpsoftsas;

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.conexionSqlServer.php';
include_once SERVER . '/business/controller/class.cabecera.php';

/**
 * Configuración del municipio: parámetros y cuentas de los bancos.
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
 * Solo los roles de Alcaldía (1 y 2). Un contribuyente no tiene nada que
 * hacer aquí: el EAN gobierna el código de barras con el que el banco recauda,
 * y cambiarlo rompe el pago de todo el municipio.
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
                'mensaje' => 'Solo la Alcaldía puede ver o cambiar la configuración.',
                'datos' => []
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

    /** Roles 1 (Administrador) y 2 (Internos Alcaldía), igual que el resto del sistema. */
    private static function _esAlcaldia()
    {
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }

        if (empty($_SESSION['id_usuario'])) { return false; }

        $rol = isset($_SESSION['id_Rol']) ? (int) $_SESSION['id_Rol'] : 0;
        return in_array($rol, [1, 2], true);
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
                $f['par_Valor']  = '';
            } else {
                $f['par_Puesto'] = (int) (trim((string) $f['par_Valor']) !== '');
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

        $con->consultar(
            "UPDATE conf_parametros
                SET par_Valor = ?, par_FechaActualizacion = GETDATE()
              WHERE par_Id = ?",
            [$valor, $id]
        );

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

        // Un valor sensible tampoco vuelve en la respuesta.
        return $sensible ? ['par_Id' => $id] : ['par_Id' => $id, 'par_Valor' => $valor];
    }

    private function _consultarBancos()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $stmt = $con->consultar(
            "SELECT ban_Id, ban_Codigo, ban_Nombre, ban_Asobancaria,
                    ban_CuentaContable, ban_CuentaRecaudadora, ban_Activo
               FROM ind_bancos
              ORDER BY ban_Codigo",
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
            "SELECT ban_Id, ban_Nombre FROM ind_bancos WHERE ban_Id = ?", [$id]
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
         * números terminan en un archivo que va al banco, y una letra ahí
         * hace rebotar el archivo entero.
         */
        foreach ([['contable', $contable], ['recaudadora', $recaudadora]] as $par) {
            if ($par[1] !== '' && !preg_match('/^[0-9.\- ]{1,40}$/', $par[1])) {
                $this->_mensaje = 'La cuenta ' . $par[0] . ' solo admite números, guiones y puntos.';
                return [];
            }
        }

        $con->consultar(
            "UPDATE ind_bancos
                SET ban_CuentaContable = ?, ban_CuentaRecaudadora = ?,
                    ban_FechaActualizacion = GETDATE()
              WHERE ban_Id = ?",
            [$contable !== '' ? $contable : null,
             $recaudadora !== '' ? $recaudadora : null,
             $id]
        );

        error_log(sprintf('[configuracion] usuario %s cambio las cuentas de %s',
            $_SESSION['id_usuario'] ?? '?', $banco['ban_Nombre']));

        $this->_ok = 1;
        $this->_mensaje = 'Cuentas actualizadas';
        return ['ban_Id' => $id];
    }
}

\erpsoftsas\ControladorConfiguracion::run();
