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
require_once SERVER . '/business/class.pagoDeclaracion.php';

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

/*
 * Buscar el requestId en los tres modulos, y TODAS las filas que lo tengan.
 *
 * Se tomaba la primera que apareciera. Hasta el 2026-09-28 una correccion
 * heredaba el requestId de la original, asi que el mismo requestId podia estar
 * en dos filas y el pago caia en cualquiera de ellas. Ahora se revisan todas y
 * la sesion se aplica solo a la declaracion cuyo numero lleva (la referencia
 * con que se creo); a las demas se les quita, porque no es suya.
 */
$filas = [];
foreach (\erpsoftsas\PseModulo::claves() as $clave) {
    $cand = \erpsoftsas\PseModulo::get($clave);
    $req = \erpsoftsas\PseModulo::colRequestId($cand);
    $st = $con->consultar(
        "SELECT {$cand['pk']} AS id, {$cand['valor']} AS valor, {$cand['pagado']} AS pagado,
                {$cand['numero']} AS numero, {$cand['estado']} AS estado
         FROM {$cand['tabla']} WHERE {$req} = ?",
        [$requestId]
    );
    while ($r = $con->obnerFila($st)) { $filas[] = ['m' => $cand, 'row' => $r]; }
}

if (!$filas) {
    // Puede ser una notificacion de una sesion que no es de esta integracion.
    // Se responde 200 para que PlacetoPay no reintente indefinidamente.
    http_response_code(200);
    echo json_encode(['ok' => true, 'mensaje' => 'requestId no asociado a ninguna declaración']);
    exit;
}

// Las ya pagadas no se tocan; si no queda ninguna, no hay nada que consultar.
$pendientes = array_values(array_filter($filas, function ($f) {
    return (int) $f['row']['pagado'] !== 1;
}));

if (!$pendientes) {
    echo json_encode(['ok' => true, 'mensaje' => 'Ya estaba pagada']);
    exit;
}

try {
    $respuesta = PlacetoPay::consultarSesion($requestId);
    $info = PlacetoPay::interpretarRespuesta($respuesta);

    $aplicadas = [];
    foreach ($pendientes as $f) {
        if (\erpsoftsas\PagoDeclaracion::sesionDeOtraDeclaracion($respuesta, $f['row']['numero'])) {
            \erpsoftsas\PagoDeclaracion::olvidarSesionAjena($con, $f['row']['id'], $f['m']);
            continue;
        }
        // Un borrador no se paga (PagoDeclaracion::registrar ya no lo marca).
        // Si la sesion SI es suya -solo pudo nacer antes de que crearSesion.php
        // exigiera la presentacion- se deja tal cual, para conciliarla a mano.
        if ((int) ($f['row']['estado'] ?? 0) !== 2) {
            // Que quede rastro: si el banco la aprobó, hay plata sin registrar.
            error_log('[pse webhook] requestId ' . $requestId . ': ' . $f['m']['clave'] . ' ' . $f['row']['id']
                    . ' no está presentada; no se registra el pago (estado del banco: ' . $info['estado'] . ').');
            continue;
        }

        // Se guarda SIEMPRE el estado, apruebe o no.
        PlacetoPay::aplicarADeclaracion($con, $f['row']['id'], $info, $f['row']['valor'], $f['m']);
        $aplicadas[] = $f['m']['clave'] . ':' . $f['row']['id'];
    }

    echo json_encode(['ok' => true, 'estado' => $info['estado'], 'aplicadas' => $aplicadas]);
} catch (Exception $e) {
    // 500: que PlacetoPay reintente el webhook mas tarde. Si aun asi nunca se
    // confirma, el cron de respaldo la recoge en su siguiente corrida.
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
}
