<?php

/**
 * A donde PlacetoPay redirige al usuario cuando da "volver al comercio".
 * Consulta el estado real de la sesion (no confia en nada de la URL) y
 * actualiza la declaracion antes de mostrar el resultado.
 *
 * GET dec_Id
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

$con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
$idDeclaracion = $_GET['dec_Id'] ?? 0;

$row = $con->obnerFila($con->consultar(
    "SELECT dec_PSE_RequestId, dec_ValorConcepto20, dec_Pagado FROM ind_declaraciones_ica WHERE dec_Id = ?",
    [$idDeclaracion]
));

$mensaje = '';
$aprobado = false;

if (!$row || empty($row['dec_PSE_RequestId'])) {
    $mensaje = 'No se encontró un pago PSE iniciado para esta declaración.';
} elseif ((int) $row['dec_Pagado'] === 1) {
    $aprobado = true;
    $mensaje = 'Esta declaración ya estaba registrada como pagada.';
} else {
    try {
        $respuesta = PlacetoPay::consultarSesion($row['dec_PSE_RequestId']);
        $info = PlacetoPay::interpretarRespuesta($respuesta);

        // Se guarda el estado venga como venga; solo se marca pagada si el
        // banco la aprobo.
        PlacetoPay::aplicarADeclaracion($con, $idDeclaracion, $info, $row['dec_ValorConcepto20']);

        if ($info['aprobado']) {
            $aprobado = true;
            $mensaje = 'Pago aprobado. Gracias.';
        } elseif ($info['estado'] === 'PENDING') {
            $mensaje = 'El pago quedó en proceso. En cuanto el banco confirme, se actualizará automáticamente (puede tardar unos minutos).';
        } else {
            $mensaje = 'El pago no fue aprobado (estado: ' . htmlspecialchars($info['estado']) . '). Puede intentarlo de nuevo.';
        }
    } catch (Exception $e) {
        $mensaje = 'No se pudo confirmar el estado del pago en este momento. Si el pago sí se realizó, quedará confirmado automáticamente en las próximas horas.';
    }
}

?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Resultado del pago - ICA</title>
<style>
body { font-family: Arial, sans-serif; max-width: 480px; margin: 60px auto; text-align: center; color: #333; }
.icono { font-size: 48px; }
.ok { color: #1fa49d; }
.pendiente { color: #d9a441; }
.error { color: #c0392b; }
a.btn { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #1fa49d; color: #fff; text-decoration: none; border-radius: 4px; }
</style>
</head>
<body>
<div class="icono <?= $aprobado ? 'ok' : 'pendiente' ?>"><?= $aprobado ? '✔' : '⏳' ?></div>
<h2><?= $aprobado ? 'Pago procesado' : 'Estado del pago' ?></h2>
<p><?= htmlspecialchars($mensaje) ?></p>
<a class="btn" href="javascript:window.close();">Cerrar</a>
</body>
</html>
