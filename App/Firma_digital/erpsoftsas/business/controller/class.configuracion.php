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

        $stmt = $con->consultar(
            "SELECT par_Id, par_Clave, par_Valor, par_Nombre, par_Descripcion,
                    par_Patron, par_FechaActualizacion
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

        $par = $con->obnerFila($con->consultar(
            "SELECT par_Clave, par_Nombre, par_Patron FROM conf_parametros WHERE par_Id = ?",
            [$id]
        ));
        if (!$par) {
            $this->_mensaje = 'El parámetro no existe';
            return [];
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
             * comprobado en la base, RECAUDO_EAN trae '^[0-9]{13}$'. Solo se
             * le ponen las barras. Añadirle otras anclas produciría '^^...$$',
             * que hoy funciona por casualidad pero se rompe en cuanto un
             * patrón use alternancia.
             *
             * Si el patrón estuviera mal escrito, preg_match devuelve false y
             * el valor se rechaza: ante una regla que no se entiende, no
             * dejar pasar es lo seguro.
             */
            if (!@preg_match('/' . $patron . '/', $valor)) {
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

        // Queda constancia de quién cambió qué: el EAN gobierna el recaudo de
        // todo el municipio y un cambio silencioso ahí es difícil de rastrear.
        error_log(sprintf('[configuracion] usuario %s cambio %s a "%s"',
            $_SESSION['id_usuario'] ?? '?', $par['par_Clave'], $valor));

        $this->_ok = 1;
        $this->_mensaje = 'Parámetro actualizado';
        return ['par_Id' => $id, 'par_Valor' => $valor];
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
