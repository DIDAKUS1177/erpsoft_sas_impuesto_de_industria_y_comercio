<?php
namespace erpsoftsas;

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.permisosRol.php';
include_once SERVER . '/business/class.ritCobro.php';
include_once SERVER . '/business/class.bancosPago.php';

/**
 * Cobro de la inscripción del RIT (migración 055; la lógica está en
 * business/class.ritCobro.php).
 *
 *   funcion 1  consultar: el cobro del contribuyente y, a quien puede
 *              registrarlo, la lista de bancos para el formulario del pago
 *              ("rit.ver"; el contribuyente solo ve el suyo).
 *   funcion 2  registrar el pago (alcaldia.rit.cobro): día, N° de recibo o
 *              consignación y, si lo hubo, el banco.
 *   funcion 3  exonerar (alcaldia.rit.cobro): con un motivo.
 *
 * POST: funcion, id_contribuyente (el que gestiona la Alcaldía) y los datos.
 *
 * Quién puede qué lo decide el SERVIDOR: el permiso del panel de Roles y
 * "Gestionar a un contribuyente". Un contribuyente no registra su propio pago
 * ni el de otro, aunque mande el id por la consola. Escribir exige además
 * X-Requested-With (jQuery lo manda; un formulario de otro sitio no puede).
 *
 * Este archivo NO incluye class.contribuyentes.php ni class.configuracion.php:
 * ambos ejecutan su run() al ser incluidos. El contribuyente de la sesión se
 * resuelve aquí con su propio SQL, igual que microservicios/firmas/api.php.
 */
class ControladorRitCobro
{
    private static function _responder($ok, $mensaje, array $datos = [], array $extra = [])
    {
        echo json_encode(array_merge(
            ['ok' => $ok ? 1 : 0, 'mensaje' => $mensaje, 'datos' => $datos],
            $extra
        ));
    }

    public static function run()
    {
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }
        header('Content-type: application/json');

        $funcion = is_scalar($_POST['funcion'] ?? null) ? (int) $_POST['funcion'] : 0;
        if (!in_array($funcion, [1, 2, 3], true)) {
            self::_responder(false, 'Función no válida.');
            return;
        }

        // Una cuenta inactivada o sin sesión no hace nada (idUsuario() lo comprueba).
        if (PermisosRol::idUsuario() <= 0) {
            self::_responder(false, 'Su sesión terminó. Inicie sesión de nuevo.', [], ['sinSesion' => 1]);
            return;
        }

        if ($funcion === 1) {
            if (!PermisosRol::tiene('rit.ver')) {
                PermisosRol::negar(PermisosRol::mensaje('rit.ver'));
                return;
            }
        } else {
            // Registrar o exonerar es trabajar sobre OTRO contribuyente: el
            // permiso del cobro y "Gestionar". El panel pide lo segundo como
            // requisito; aquí no se da por hecho.
            $tiene = PermisosRol::tiene('alcaldia.rit.cobro');
            if (!$tiene || !PermisosRol::gestionaOtros()) {
                PermisosRol::negar(PermisosRol::mensaje($tiene ? 'alcaldia.contribuyentes.gestionar' : 'alcaldia.rit.cobro'));
                return;
            }
            // Solo desde la pantalla (jQuery manda esta cabecera): un formulario
            // de otro sitio no puede usar la sesión abierta de un funcionario.
            if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'xmlhttprequest') {
                self::_responder(false, 'Solicitud no válida.');
                return;
            }
        }

        try {
            $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

            // Registrar o exonerar nombra SIEMPRE al contribuyente: sin id no se cae en "el
            // propio" de quien opera (un funcionario cuyo documento coincidiera con el de un
            // contribuyente habría registrado el pago de otro por un descuido).
            $id = self::_contribuyente($con, $funcion !== 1);
            if ($id === null) {
                self::_responder(false, PermisosRol::gestionaOtros()
                    ? 'No se indicó el contribuyente.'
                    : 'Su usuario no está asociado a un contribuyente.');
                return;
            }

            if ($funcion === 1) {
                $cobro  = RitCobro::resumen($con, $id, RitCobro::puedeRegistrar());
                // Los bancos solo los necesita quien va a registrar un pago.
                $bancos = ($cobro && !empty($cobro['puedeRegistrar'])) ? BancosPago::lista($con) : [];
                self::_responder(true, '', ['cobro' => $cobro, 'bancos' => $bancos]);
                return;
            }

            $usuario = (int) $_SESSION['id_usuario'];
            $r = ($funcion === 2)
                ? RitCobro::registrarPago($con, $id, $_POST, $usuario)
                : RitCobro::exonerar($con, $id, $_POST['motivo'] ?? '', $usuario);

            self::_responder($r['ok'], $r['mensaje'], [], ['codigo' => $r['codigo'] ?? null]);
        } catch (\Throwable $e) {
            error_log('[rit cobro] ' . $e->getMessage());
            self::_responder(false, 'No se pudo procesar la solicitud. Intente de nuevo.');
        }
    }

    /**
     * Sobre qué contribuyente se trabaja. Quien gestiona contribuyentes (la
     * Alcaldía) indica el id; el resto solo ve el de su propia cuenta, sin
     * importar el id que mande el navegador (misma regla que la función 10 de
     * la API de firmas).
     *
     * @param bool $escribe registrar o exonerar: la Alcaldía tiene que indicar el
     *                      contribuyente, no hay "el propio" de respaldo
     */
    private static function _contribuyente($con, $escribe = false)
    {
        $propio = self::_contribuyenteDeLaSesion($con);
        if (PermisosRol::gestionaOtros()) {
            $pedido = is_scalar($_POST['id_contribuyente'] ?? null) ? (int) $_POST['id_contribuyente'] : 0;
            if ($pedido > 0) { return $pedido; }
            return $escribe ? null : $propio;
        }
        return $propio;
    }

    /** El contribuyente de la cuenta de la sesión (cruce por documento). */
    private static function _contribuyenteDeLaSesion($con)
    {
        $fila = $con->obnerFila($con->consultar(
            // TOP 1 + ORDER BY: con documentos repetidos en el padrón el
            // resultado debe ser siempre el mismo (el que usa el login).
            "SELECT TOP 1 c.ind_Id
               FROM ind_contribuyentes c
               INNER JOIN conf_usuarios u ON u.usu_NumeroDocumento = c.ind_NumeroIdentificacion
              WHERE u.usu_Id = ?
              ORDER BY c.ind_Id",
            [(int) $_SESSION['id_usuario']]
        ));
        return isset($fila['ind_Id']) ? (int) $fila['ind_Id'] : null;
    }
}

// Al ser incluido (pruebas) no se ejecuta solo: únicamente cuando es el script pedido.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    \erpsoftsas\ControladorRitCobro::run();
}
