<?php
/**
 * Entrega un PDF de una declaración registrada a mano (migración 044): la
 * declaración original en papel o el soporte de pago.
 *
 * Viven bajo la carpeta de los anexos, cerrada al acceso directo (ver
 * business/class.registroManual.php), así que este es el único camino. La
 * regla es la de extensiones/anexo.php: sesión, el dueño de la declaración (un
 * contribuyente solo ve los de sus declaraciones; la Alcaldía, los de quien
 * gestiona) y el interruptor de "ver" del módulo.
 *
 * GET: id (sop_Id)
 */

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/controller/class.anexos.php';
include_once SERVER . '/business/class.permisosRol.php';
include_once SERVER . '/business/class.registroManual.php';

if (session_status() === PHP_SESSION_NONE) { @session_start(); }

if (empty($_SESSION['id_usuario'])) {
    http_response_code(401);
    exit('Debe iniciar sesión para ver este archivo.');
}

$idSoporte = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($idSoporte <= 0) {
    http_response_code(400);
    exit('Archivo no indicado.');
}

$con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
if (!\erpsoftsas\RegistroManual::hayTablas($con)) {
    http_response_code(404);
    exit('El archivo no existe.');
}

$sop = $con->obnerFila($con->consultar(
    "SELECT sop_Modulo, sop_IdDeclaracion, sop_NombreOriginal, sop_Ruta, sop_Activo
       FROM ind_declaracion_soportes WHERE sop_Id = ?",
    [$idSoporte]
));
if (!$sop || (int) $sop['sop_Activo'] !== 1 || !\erpsoftsas\PseModulo::existe($sop['sop_Modulo'])) {
    http_response_code(404);
    exit('El archivo no existe.');
}

/* ---- De quién es: el contribuyente de la declaración ----------------------- */
$m = \erpsoftsas\PseModulo::get($sop['sop_Modulo']);
$decl = $con->obnerFila($con->consultar(
    "SELECT {$m['prefijo']}_IdContribuyente AS c FROM {$m['tabla']} WHERE {$m['pk']} = ?",
    [(int) $sop['sop_IdDeclaracion']]
));
if (!$decl || !\erpsoftsas\ControladorAnexos::puedeOperarSobreContribuyente($decl['c'], $con)) {
    http_response_code(403);
    exit('Este archivo pertenece a otro contribuyente.');
}

// "Ver y descargar declaraciones" del módulo, o los permisos de quien lo registró.
$claves = [$m['clave'] . '.ver', 'alcaldia.declaraciones.historicas', 'alcaldia.pagos.manual'];
if (!\erpsoftsas\PermisosRol::tieneAlguno($claves)) {
    http_response_code(403);
    exit(\erpsoftsas\PermisosRol::mensaje($claves[0]));
}

/* ---- Entrega (misma comprobación de ruta que anexo.php) -------------------- */
$base     = \erpsoftsas\RegistroManual::carpetaBase();
$rutaReal = realpath(\erpsoftsas\ControladorAnexos::carpetaBase() . '/' . $sop['sop_Ruta']);
$baseReal = realpath($base);
if (!$rutaReal || !$baseReal
    || strpos($rutaReal, $baseReal . DIRECTORY_SEPARATOR) !== 0
    || !is_file($rutaReal)) {
    http_response_code(404);
    exit('El archivo no está disponible.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . str_replace(['"', "\r", "\n"], '', $sop['sop_NombreOriginal']) . '"');
header('Content-Length: ' . filesize($rutaReal));
header('X-Content-Type-Options: nosniff');

readfile($rutaReal);
