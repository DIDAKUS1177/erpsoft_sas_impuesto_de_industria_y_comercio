<?php
/**
 * Entrega un anexo de establecimiento (punto 17).
 *
 * Los archivos viven FUERA de la raiz web (con .htaccess de respaldo, ver
 * class.anexos.php::blindarCarpeta), asi que este es el unico camino para
 * DESCARGARLOS. Se comprueba quien pide que via
 * ControladorAnexos::puedeOperarSobreEstablecimiento(), la misma regla que
 * usan subir/listar/eliminar en class.anexos.php:
 *
 *   - hay que haber iniciado sesion;
 *   - un contribuyente solo opera sobre SUS establecimientos.
 *
 * Sin la segunda comprobacion bastaria con ir cambiando el numero en la URL
 * para bajarse el RUT y la camara de comercio de todos los contribuyentes del
 * municipio. La primera sola no alcanza: cualquier usuario registrado sigue
 * siendo un tercero frente a los papeles de otro.
 */

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/controller/class.anexos.php';

if (session_status() === PHP_SESSION_NONE) { @session_start(); }

if (empty($_SESSION['id_usuario'])) {
    http_response_code(401);
    exit('Debe iniciar sesión para ver este archivo.');
}

$idAnexo = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($idAnexo <= 0) {
    http_response_code(400);
    exit('Anexo no indicado.');
}

$con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

$anexo = $con->obnerFila($con->consultar(
    "SELECT a.anx_IdEstablecimiento, a.anx_NombreOriginal, a.anx_Ruta,
            a.anx_Extension, a.anx_Activo
       FROM ind_establecimiento_anexos a
      WHERE a.anx_Id = ?",
    [$idAnexo]
));

if (!$anexo || (int) $anexo['anx_Activo'] !== 1) {
    http_response_code(404);
    exit('El archivo no existe.');
}

/* ---- Quien puede verlo --------------------------------------------------
   Misma regla que subir/listar/eliminar (class.anexos.php), centralizada ahi
   para que las cuatro vias no se desalineen con el tiempo. */
if (!\erpsoftsas\ControladorAnexos::puedeOperarSobreEstablecimiento($anexo['anx_IdEstablecimiento'], $con)) {
    http_response_code(403);
    exit('Este archivo pertenece a otro contribuyente.');
}

/* ---- Entrega ---------------------------------------------------------- */
$ruta = \erpsoftsas\ControladorAnexos::carpetaBase() . '/' . $anexo['anx_Ruta'];

// realpath resuelve cualquier ".." que se hubiera colado en la ruta guardada;
// si el resultado no cae dentro de la carpeta de anexos, no se entrega nada.
$rutaReal = realpath($ruta);
$baseReal = realpath(\erpsoftsas\ControladorAnexos::carpetaBase());

// strpos($rutaReal, $baseReal) !== 0 sin el separador final es el bypass
// clasico de prefijo: "/var/.../anexos_establecimientos_EVIL/x" tambien
// empieza por "/var/.../anexos_establecimientos", pero es una carpeta
// HERMANA, no un hijo. Hoy anx_Ruta siempre la genera el servidor (nunca
// trae "..", no hay forma normal de inyectarlo), asi que no es explotable
// por la via de subida actual -pero el chequeo en si estaba mal, y
// reproducido a mano (una fila con anx_Ruta manipulada) si se colaba.
if (!$rutaReal || !$baseReal
    || ($rutaReal !== $baseReal && strpos($rutaReal, $baseReal . DIRECTORY_SEPARATOR) !== 0)
    || !is_file($rutaReal)) {
    http_response_code(404);
    exit('El archivo no está disponible.');
}

$tiposMime = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
];
$mime = $tiposMime[strtolower((string) $anexo['anx_Extension'])] ?? 'application/octet-stream';

// inline para que el PDF se abra en el navegador; el nombre que ve la persona
// es el original, aunque en disco se llame de otra forma.
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . str_replace('"', '', $anexo['anx_NombreOriginal']) . '"');
header('Content-Length: ' . filesize($rutaReal));
header('X-Content-Type-Options: nosniff');

readfile($rutaReal);
