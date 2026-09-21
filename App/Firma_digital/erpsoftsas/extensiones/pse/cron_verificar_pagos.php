<?php

/**
 * Tarea programada de respaldo (exigida por PlacetoPay/el banco): revisa toda
 * declaracion con una sesion PSE creada que aun no aparezca pagada, en los TRES
 * modulos (ICA, RETEICA, AUTORRETEICA), y confirma directamente contra
 * PlacetoPay. Es el tercer y ultimo mecanismo (junto con retorno.php y
 * webhook.php) para que ningun pago se quede sin reflejar.
 *
 * Producción: 1 vez cada 24h, en horario de bajo trafico. Certificacion: cada
 * 15 minutos (Guia WC, item 4.6).
 *
 * Se ejecuta por CLI (php cron_verificar_pagos.php): $_SERVER['DOCUMENT_ROOT']
 * no existe en ese contexto, asi que las rutas van con __DIR__.
 */
require_once __DIR__ . '/../../business/class.conexionSqlServer.php';
require_once __DIR__ . '/../../business/class.placetopay.php';
require_once __DIR__ . '/../../business/class.pseModulo.php';

$configPath = dirname(__DIR__, 3) . '/config.municipio.php';
if (!file_exists($configPath)) {
    $configPath = dirname(__DIR__, 2) . '/config.municipio.php';
}
if (file_exists($configPath)) {
    require_once $configPath;
}

$con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

$revisadas = 0;
$actualizadas = 0;

foreach (\erpsoftsas\PseModulo::claves() as $clave) {
    $m   = \erpsoftsas\PseModulo::get($clave);
    $req = \erpsoftsas\PseModulo::colRequestId($m);

    $stmt = $con->consultar(
        "SELECT {$m['pk']} AS id, {$req} AS pse_req, {$m['valor']} AS valor
         FROM {$m['tabla']}
         WHERE {$req} IS NOT NULL AND ISNULL({$m['pagado']}, 0) = 0",
        []
    );

    while ($row = $con->obnerFila($stmt)) {
        $revisadas++;
        try {
            $respuesta = PlacetoPay::consultarSesion($row['pse_req']);
            $info = PlacetoPay::interpretarRespuesta($respuesta);

            $pagada = PlacetoPay::aplicarADeclaracion($con, $row['id'], $info, $row['valor'], $m);

            if ($pagada) {
                $actualizadas++;
                echo "[{$clave}] id {$row['id']}: APROBADO, actualizada.\n";
            } else {
                echo "[{$clave}] id {$row['id']}: sigue en estado {$info['estado']}, anotado.\n";
            }
        } catch (Exception $e) {
            echo "[{$clave}] id {$row['id']}: error al consultar - {$e->getMessage()}\n";
        }
    }
}

echo "Revisadas: $revisadas, actualizadas a pagada: $actualizadas.\n";
