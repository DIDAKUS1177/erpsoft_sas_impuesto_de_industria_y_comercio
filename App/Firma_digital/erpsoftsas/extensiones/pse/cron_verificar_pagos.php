<?php

/**
 * Tarea programada de respaldo (exigida por PlacetoPay/el banco): revisa
 * toda declaracion con una sesion PSE creada que aun no aparezca pagada,
 * y confirma directamente contra PlacetoPay. Es el tercer y ultimo
 * mecanismo (junto con retorno.php y webhook.php) para garantizar que
 * ningun pago se quede sin reflejar.
 *
 * Producción: 1 vez cada 24h, en horario de bajo trafico (ver Plesk >
 * Tareas programadas). Pruebas: cada 5 minutos mientras se certifica.
 *
 * Se ejecuta por CLI (php cron_verificar_pagos.php), no por navegador -
 * $_SERVER['DOCUMENT_ROOT'] no existe en ese contexto, así que las rutas
 * se arman con __DIR__ en vez del patrón de los demás archivos de
 * extensiones/.
 */
require_once __DIR__ . '/../../business/class.conexionSqlServer.php';
require_once __DIR__ . '/../../business/class.placetopay.php';

$configPath = dirname(__DIR__, 3) . '/config.municipio.php';
if (!file_exists($configPath)) {
    $configPath = dirname(__DIR__, 2) . '/config.municipio.php';
}
if (file_exists($configPath)) {
    require_once $configPath;
}

$con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

$stmt = $con->consultar(
    "SELECT dec_Id, dec_PSE_RequestId, dec_ValorConcepto20
     FROM ind_declaraciones_ica
     WHERE dec_PSE_RequestId IS NOT NULL AND dec_Pagado = 0"
);

$revisadas = 0;
$actualizadas = 0;

while ($row = $con->obnerFila($stmt)) {
    $revisadas++;
    try {
        $respuesta = PlacetoPay::consultarSesion($row['dec_PSE_RequestId']);
        $info = PlacetoPay::interpretarRespuesta($respuesta);

        if ($info['aprobado']) {
            $con->consultar(
                "UPDATE ind_declaraciones_ica
                 SET dec_Pagado = 1, dec_FechaPago = GETDATE(), dec_FechaRealPago = GETDATE(),
                     dec_ValorPago = ?, dec_BancoPago = ?
                 WHERE dec_Id = ?",
                [$row['dec_ValorConcepto20'], $info['banco'], $row['dec_Id']]
            );
            $actualizadas++;
            echo "dec_Id {$row['dec_Id']}: APROBADO, actualizada.\n";
        } else {
            echo "dec_Id {$row['dec_Id']}: sigue en estado {$info['estado']}.\n";
        }
    } catch (Exception $e) {
        echo "dec_Id {$row['dec_Id']}: error al consultar - {$e->getMessage()}\n";
    }
}

echo "Revisadas: $revisadas, actualizadas a pagada: $actualizadas.\n";
