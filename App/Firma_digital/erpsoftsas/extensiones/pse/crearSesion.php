<?php

/**
 * Punto de entrada del botón "Pagar PSE": crea la sesión en PlacetoPay
 * para una declaración y redirige al usuario a pagarla.
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
    "SELECT dec_NumeroDeclaracion, dec_ValorConcepto20, dec_Pagado, dec_PSE_RequestId
     FROM ind_declaraciones_ica WHERE dec_Id = ?",
    [$idDeclaracion]
));

if (!$row) {
    http_response_code(404);
    die('Declaración no encontrada.');
}

if ((int) $row['dec_Pagado'] === 1) {
    die('Esta declaración ya está pagada.');
}

$referencia = (string) $row['dec_NumeroDeclaracion'];
$valor = (float) $row['dec_ValorConcepto20'];

if ($valor <= 0) {
    die('El valor a pagar de esta declaración es $0, no aplica pago PSE.');
}

// URL a la que PlacetoPay redirige al usuario al terminar (o cancelar) el
// pago. Se arma dinamicamente (esquema+host actuales) para que funcione
// igual en Docker local, pruebas y produccion sin tocar codigo.
$esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$returnUrl = $esquema . '://' . $_SERVER['HTTP_HOST'] . '/erpsoftsas/extensiones/pse/retorno.php?dec_Id=' . urlencode($idDeclaracion);

try {
    $sesion = PlacetoPay::crearSesion(
        $referencia,
        $valor,
        'Pago Impuesto de Industria y Comercio - Formulario No. ' . $referencia,
        $returnUrl
    );
} catch (Exception $e) {
    http_response_code(502);
    die('No se pudo iniciar el pago con PlacetoPay: ' . htmlspecialchars($e->getMessage()));
}

$con->consultar(
    "UPDATE ind_declaraciones_ica SET dec_PSE_RequestId = ? WHERE dec_Id = ?",
    [$sesion['requestId'], $idDeclaracion]
);

header('Location: ' . $sesion['processUrl']);
exit;
