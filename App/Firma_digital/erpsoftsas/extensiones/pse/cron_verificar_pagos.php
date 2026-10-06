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
 *
 * SOLO por CLI (la tarea de Plesk "Ejecutar un script PHP" lo corre asi): por
 * la web cualquiera lo podia llamar en bucle, gastando la llave privada de la
 * pasarela y leyendo las referencias abiertas (revision 2026-10-02).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../../business/class.conexionSqlServer.php';
require_once __DIR__ . '/../../business/class.placetopay.php';
require_once __DIR__ . '/../../business/class.pseModulo.php';
require_once __DIR__ . '/../../business/class.pagoDeclaracion.php';

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

    /*
     * Solo PRESENTADAS (Estado = 2). Un borrador no se paga por PSE
     * (crearSesion.php lo rechaza), asi que un borrador con requestId es una
     * sesion que no es suya: la que una correccion heredaba de la original
     * hasta el 2026-09-28. Este cron la consultaba y, aprobada, marcaba la
     * correccion pagada en borrador.
     */
    $stmt = $con->consultar(
        "SELECT {$m['pk']} AS id, {$req} AS pse_req, {$m['valor']} AS valor,
                {$m['numero']} AS numero
         FROM {$m['tabla']}
         WHERE {$req} IS NOT NULL AND ISNULL({$m['pagado']}, 0) = 0
           AND {$m['estado']} = 2",
        []
    );

    while ($row = $con->obnerFila($stmt)) {
        $revisadas++;
        try {
            $respuesta = PlacetoPay::consultarSesion($row['pse_req']);

            // Una sesion creada para OTRA declaracion (la heredada por una
            // correccion de antes del arreglo) no se aplica: se le quita.
            if (\erpsoftsas\PagoDeclaracion::sesionDeOtraDeclaracion($respuesta, $row['numero'])) {
                \erpsoftsas\PagoDeclaracion::olvidarSesionAjena($con, $row['id'], $m);
                echo "[{$clave}] id {$row['id']}: la sesión {$row['pse_req']} es de otra declaración; se le quitó.\n";
                continue;
            }

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

/*
 * WOMPI (042): en la entidad que cobra con Wompi, los intentos que siguen
 * abiertos. Con PlacetoPay el bucle de arriba hace este trabajo con el
 * requestId de cada declaracion; Wompi no lo usa (ver class.pagoWompi.php).
 */
require_once __DIR__ . '/../../business/class.pasarela.php';
if (\erpsoftsas\Pasarela::esWompi()) {
    require_once __DIR__ . '/../../business/class.pagoWompi.php';
    if (\erpsoftsas\Wompi::configurado()) {
        $r = \erpsoftsas\PagoWompi::revisarAbiertos($con, function ($linea) { echo "[wompi] $linea\n"; });
        $revisadas    += $r['revisados'];
        $actualizadas += $r['pagados'];
    } else {
        echo "[wompi] las llaves de Wompi no están completas (o mezclan pruebas y producción): no se revisó nada.\n";
    }
}

echo "Revisadas: $revisadas, actualizadas a pagada: $actualizadas.\n";
