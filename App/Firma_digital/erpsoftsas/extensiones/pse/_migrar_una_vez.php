<?php

/**
 * Script de un solo uso para aplicar migracion_produccion.sql contra la
 * base de datos real, usando la misma conexion que ya tiene configurada
 * la app en este servidor (sin manejar credenciales aparte). Protegido
 * con un token de un solo uso para que nadie mas lo dispare mientras
 * esta desplegado. BORRAR este archivo (commit + pull en Plesk) apenas
 * se confirme que la columna quedo creada.
 */
include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.conexionSqlServer.php';

$configPath = dirname(dirname(dirname(__DIR__))) . '/config.municipio.php';
if (!file_exists($configPath)) {
    $configPath = dirname(dirname(__DIR__)) . '/config.municipio.php';
}
if (file_exists($configPath)) {
    require_once $configPath;
}

header('Content-Type: text/plain; charset=utf-8');

if (($_GET['token'] ?? '') !== 'mig_pse_ica_2026_08_10_x7f2') {
    http_response_code(403);
    echo "Token invalido.\n";
    exit;
}

$con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

$row = $con->obnerFila($con->consultar(
    "SELECT COUNT(*) n FROM sys.columns WHERE object_id = OBJECT_ID('ind_declaraciones_ica') AND name = 'dec_PSE_RequestId'"
));

if ((int) $row['n'] > 0) {
    echo "dec_PSE_RequestId ya existe. No se hizo nada.\n";
    exit;
}

$con->consultar("ALTER TABLE ind_declaraciones_ica ADD dec_PSE_RequestId BIGINT NULL");
echo "Columna dec_PSE_RequestId agregada correctamente.\n";
