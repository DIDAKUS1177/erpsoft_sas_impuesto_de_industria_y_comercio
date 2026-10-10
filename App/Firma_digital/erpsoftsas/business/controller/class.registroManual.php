<?php
namespace erpsoftsas;

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER . '/business/class.permisosRol.php';
include_once SERVER . '/business/class.registroManual.php';

/**
 * Registro manual de la Alcaldía sobre las declaraciones de los tres módulos
 * (migración 044; la lógica está en business/class.registroManual.php).
 *
 *   funcion 1  declaración ya presentada y pagada fuera de la plataforma
 *              (alcaldia.declaraciones.historicas): un borrador ya llenado +
 *              número y fecha del papel + pago + dos PDF.
 *   funcion 2  pago manual de una declaración presentada
 *              (alcaldia.pagos.manual): pago + PDF del soporte.
 *   funcion 3  anular un registro manual hecho por error
 *              (alcaldia.registro.anular): motivo obligatorio.
 *
 * POST multipart: modulo (ica | reteica | autorreteica), id (el id interno de
 * la declaración), los datos del pago y los archivos "declaracion" y
 * "soporte".
 */
class ControladorRegistroManual extends \erpsoftsas\Cabecera
{
    public static function run()
    {
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }
        header('Content-type: application/json');

        // Archivos más grandes que post_max_size: PHP descarta TODO el cuerpo y
        // llegaría un "Función no válida" que no explica nada.
        if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            echo json_encode(['ok' => 0, 'mensaje' => 'Los archivos superan el tamaño permitido. Cada PDF puede pesar hasta 10 MB.', 'datos' => []]);
            return;
        }

        $funcion = (int) ($_POST['funcion'] ?? 0);
        $claves  = [1 => 'alcaldia.declaraciones.historicas', 2 => 'alcaldia.pagos.manual', 3 => 'alcaldia.registro.anular'];
        if (!isset($claves[$funcion])) {
            echo json_encode(['ok' => 0, 'mensaje' => 'Función no válida.', 'datos' => []]);
            return;
        }

        if (empty($_SESSION['id_usuario'])) {
            echo json_encode(['ok' => 0, 'mensaje' => 'Su sesión terminó. Inicie sesión de nuevo.', 'datos' => [], 'sinSesion' => 1]);
            return;
        }
        // Y gestionar contribuyentes: estas acciones son sobre declaraciones
        // ajenas. El panel lo exige como requisito; aquí no se da por hecho.
        if (!PermisosRol::tiene($claves[$funcion]) || !PermisosRol::gestionaOtros()) {
            $falta = PermisosRol::tiene($claves[$funcion]) ? 'alcaldia.contribuyentes.gestionar' : $claves[$funcion];
            echo json_encode(['ok' => 0, 'mensaje' => PermisosRol::mensaje($falta), 'datos' => []]);
            return;
        }
        // Solo desde la pantalla (jQuery manda esta cabecera): un formulario de
        // otro sitio no puede usar la sesión abierta para marcar pagos.
        if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'xmlhttprequest') {
            echo json_encode(['ok' => 0, 'mensaje' => 'Solicitud no válida.', 'datos' => []]);
            return;
        }

        try {
            $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
            if (!RegistroManual::hayTablas($con)) {
                echo json_encode(['ok' => 0, 'mensaje' => 'Esta función aún no está instalada en este municipio (falta la migración 044).', 'datos' => []]);
                return;
            }

            $modulo  = (string) ($_POST['modulo'] ?? '');
            $id      = (int) ($_POST['id'] ?? 0);
            $usuario = (int) $_SESSION['id_usuario'];

            if ($funcion === 1) {
                $r = RegistroManual::registrarHistorica($con, $modulo, $id, $_POST, $_FILES, $usuario);
            } elseif ($funcion === 2) {
                $r = RegistroManual::registrarPago($con, $modulo, $id, $_POST, $_FILES, $usuario);
            } else {
                $r = RegistroManual::anular($con, $modulo, $id, $_POST['motivo'] ?? '', $usuario);
            }

            echo json_encode([
                'ok' => $r['ok'] ? 1 : 0, 'mensaje' => $r['mensaje'],
                'codigo' => $r['codigo'] ?? null, 'datos' => [],
            ]);
        } catch (\Throwable $e) {
            error_log('[registro manual] ' . $e->getMessage());
            echo json_encode(['ok' => 0, 'mensaje' => 'No se pudo procesar la solicitud. Intente de nuevo.', 'datos' => []]);
        }
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    \erpsoftsas\ControladorRegistroManual::run();
}
