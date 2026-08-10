<?php

/**
 * URL de notificacion que se registra ante PlacetoPay. Cuando el estado de
 * una sesion cambia, PlacetoPay hace POST aqui con un JSON que incluye
 * "requestId". No se confia en el contenido del POST (ver nota en
 * class.placetopay.php sobre por que no se valida firma): se usa
 * unicamente como aviso de "revisa esta sesion", y el estado real se
 * obtiene con una consulta autenticada aparte.
 */
include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.conexionSqlServer.php';
require_once SERVER . '/business/class.placetopay.php';

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

$con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

$row = $con->obnerFila($con->consultar(
    "SELECT dec_Id, dec_ValorConcepto20, dec_Pagado FROM ind_declaraciones_ica WHERE dec_PSE_RequestId = ?",
    [$requestId]
));

if (!$row) {
    // No es un error nuestro: puede ser una notificacion de una sesion que
    // no corresponde a esta integracion. Se responde 200 igual, porque
    // PlacetoPay reintenta si no recibe 200.
    http_response_code(200);
    echo json_encode(['ok' => true, 'mensaje' => 'requestId no asociado a ninguna declaración']);
    exit;
}

if ((int) $row['dec_Pagado'] === 1) {
    echo json_encode(['ok' => true, 'mensaje' => 'Ya estaba pagada']);
    exit;
}

try {
    $respuesta = PlacetoPay::consultarSesion($requestId);
    $info = PlacetoPay::interpretarRespuesta($respuesta);

    if ($info['aprobado']) {
        $con->consultar(
            "UPDATE ind_declaraciones_ica
             SET dec_Pagado = 1, dec_FechaPago = GETDATE(), dec_FechaRealPago = GETDATE(),
                 dec_ValorPago = ?, dec_BancoPago = ?
             WHERE dec_Id = ?",
            [$row['dec_ValorConcepto20'], $info['banco'], $row['dec_Id']]
        );
    }

    echo json_encode(['ok' => true, 'estado' => $info['estado']]);
} catch (Exception $e) {
    // 500: que PlacetoPay reintente el webhook mas tarde (fallo nuestro al
    // consultar, no un rechazo del pago). Si aun asi nunca se confirma, el
    // cron de respaldo la recoge en su siguiente corrida.
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
}
