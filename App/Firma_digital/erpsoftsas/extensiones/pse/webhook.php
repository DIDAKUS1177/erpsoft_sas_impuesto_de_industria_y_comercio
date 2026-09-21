<?php

/**
 * URL de notificacion que se registra ante PlacetoPay. Cuando el estado de una
 * sesion cambia, PlacetoPay hace POST aqui con requestId, status y signature.
 * Se valida la firma primero (validarFirmaWebhook) y, aunque sea valida, el
 * estado que se guarda SIEMPRE sale de una consulta autenticada aparte
 * (consultarSesion), nunca del contenido del POST.
 *
 * El requestId es unico por sesion, pero puede pertenecer a cualquiera de los
 * tres modulos (ICA, RETEICA, AUTORRETEICA). Como PlacetoPay no dice de cual,
 * se busca en las tres tablas.
 */
include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.conexionSqlServer.php';
require_once SERVER . '/business/class.placetopay.php';
require_once SERVER . '/business/class.pseModulo.php';

$configPath = dirname(dirname(dirname(__DIR__))) . '/config.municipio.php';
if (!file_exists($configPath)) {
    $configPath = dirname(dirname(__DIR__)) . '/config.municipio.php';
}
if (file_exists($configPath)) {
    require_once $configPath;
}

$body = json_decode(file_get_contents('php://input'), true);
$requestId = $body['requestId'] ?? null;

header('Content-Type: application/json');

if (!$requestId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Falta requestId']);
    exit;
}

if (!PlacetoPay::validarFirmaWebhook($body)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'mensaje' => 'Firma inválida']);
    exit;
}

$con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

// Buscar el requestId en los tres modulos.
$m = null; $row = null;
foreach (\erpsoftsas\PseModulo::claves() as $clave) {
    $cand = \erpsoftsas\PseModulo::get($clave);
    $req = \erpsoftsas\PseModulo::colRequestId($cand);
    $r = $con->obnerFila($con->consultar(
        "SELECT {$cand['pk']} AS id, {$cand['valor']} AS valor, {$cand['pagado']} AS pagado
         FROM {$cand['tabla']} WHERE {$req} = ?",
        [$requestId]
    ));
    if ($r) { $m = $cand; $row = $r; break; }
}

if (!$row) {
    // Puede ser una notificacion de una sesion que no es de esta integracion.
    // Se responde 200 para que PlacetoPay no reintente indefinidamente.
    http_response_code(200);
    echo json_encode(['ok' => true, 'mensaje' => 'requestId no asociado a ninguna declaración']);
    exit;
}

if ((int) $row['pagado'] === 1) {
    echo json_encode(['ok' => true, 'mensaje' => 'Ya estaba pagada']);
    exit;
}

try {
    $respuesta = PlacetoPay::consultarSesion($requestId);
    $info = PlacetoPay::interpretarRespuesta($respuesta);

    // Se guarda SIEMPRE el estado, apruebe o no.
    PlacetoPay::aplicarADeclaracion($con, $row['id'], $info, $row['valor'], $m);

    echo json_encode(['ok' => true, 'modulo' => $m['clave'], 'estado' => $info['estado']]);
} catch (Exception $e) {
    // 500: que PlacetoPay reintente el webhook mas tarde. Si aun asi nunca se
    // confirma, el cron de respaldo la recoge en su siguiente corrida.
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
}
