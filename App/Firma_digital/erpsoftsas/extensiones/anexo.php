<?php
/**
 * Entrega un anexo de establecimiento (punto 17).
 *
 * Los archivos viven FUERA de la raiz web, asi que esta es la unica via para
 * llegar a ellos. Por eso aqui es donde se comprueba quien pide que:
 *
 *   - hay que haber iniciado sesion;
 *   - un contribuyente solo puede bajar anexos de SUS establecimientos.
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
    "SELECT a.anx_NombreOriginal, a.anx_Ruta, a.anx_Extension, a.anx_Activo,
            e.est_IdContribuyente
       FROM ind_establecimiento_anexos a
       INNER JOIN ind_establecimientos e ON e.est_Id = a.anx_IdEstablecimiento
      WHERE a.anx_Id = ?",
    [$idAnexo]
));

if (!$anexo || (int) $anexo['anx_Activo'] !== 1) {
    http_response_code(404);
    exit('El archivo no existe.');
}

/* ---- Quien puede verlo -------------------------------------------------
   Rol 1 (Administrador) y 2 (Internos Alcaldia) ven cualquiera, que es su
   trabajo. Los demas, solo lo suyo: se compara el contribuyente del anexo
   contra el del usuario que tiene la sesion abierta. */
$rol = isset($_SESSION['id_Rol']) ? (int) $_SESSION['id_Rol'] : 0;

if (!in_array($rol, [1, 2], true)) {

    // No hay columna que ate el usuario al contribuyente: el sistema los cruza
    // por numero de documento (ver el 'sql' de usu_idContibuyente en
    // DAO_Usuario.php, que hace exactamente esta subconsulta).
    $propio = $con->obnerFila($con->consultar(
        "SELECT c.ind_Id
           FROM ind_contribuyentes c
           INNER JOIN conf_usuarios u
                   ON u.usu_NumeroDocumento = c.ind_NumeroIdentificacion
          WHERE u.usu_Id = ?",
        [(int) $_SESSION['id_usuario']]
    ));

    $idContribuyenteSesion = $propio['ind_Id'] ?? null;

    if (!$idContribuyenteSesion
        || (int) $idContribuyenteSesion !== (int) $anexo['est_IdContribuyente']) {
        http_response_code(403);
        exit('Este archivo pertenece a otro contribuyente.');
    }
}

/* ---- Entrega ---------------------------------------------------------- */
$ruta = \erpsoftsas\ControladorAnexos::carpetaBase() . '/' . $anexo['anx_Ruta'];

// realpath resuelve cualquier ".." que se hubiera colado en la ruta guardada;
// si el resultado no cae dentro de la carpeta de anexos, no se entrega nada.
$rutaReal = realpath($ruta);
$baseReal = realpath(\erpsoftsas\ControladorAnexos::carpetaBase());

if (!$rutaReal || !$baseReal || strpos($rutaReal, $baseReal) !== 0 || !is_file($rutaReal)) {
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
