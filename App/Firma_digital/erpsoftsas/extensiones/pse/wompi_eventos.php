<?php

/**
 * URL de eventos que se registra en el panel de Wompi (migración 042):
 *
 *     https://<dominio>/erpsoftsas/extensiones/pse/wompi_eventos.php
 *
 * Wompi hace POST aquí cada vez que una transacción cambia de estado
 * (transaction.updated). Se comprueba la firma (checksum con el secreto de
 * eventos) y, aunque sea válida, lo que se guarda sale de una consulta propia
 * a Wompi con la llave privada, nunca del cuerpo del aviso. La lógica vive en
 * PagoWompi::procesarEvento, donde la prueban las suites.
 *
 * Tiene que responder 200 para que Wompi no reintente; si responde otra cosa,
 * Wompi lo vuelve a mandar a los 30 minutos, a las 3 horas y a las 24 horas.
 */
include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.conexionSqlServer.php';
require_once SERVER . '/business/class.pagoWompi.php';

$configPath = dirname(dirname(dirname(__DIR__))) . '/config.municipio.php';
if (!file_exists($configPath)) {
    $configPath = dirname(dirname(__DIR__)) . '/config.municipio.php';
}
if (file_exists($configPath)) {
    require_once $configPath;
}

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Solo POST']);
    exit;
}

$con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

list($codigo, $respuesta) = \erpsoftsas\PagoWompi::procesarEvento(
    $con,
    file_get_contents('php://input'),
    $_SERVER['HTTP_X_EVENT_CHECKSUM'] ?? null
);

http_response_code($codigo);
echo json_encode($respuesta);
