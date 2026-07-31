<?php
/**
 * Microservicio Firmas — helper de consulta a base de datos.
 *
 * Uso desde cualquier archivo PHP:
 *   require_once __DIR__ . '/../microservicios/firmas/getFirma.php';
 *   $base64 = firmas_getBase64($idEstablecimiento);
 */

if (!defined('SERVER')) {
    include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
}
include_once SERVER . '/business/class.conexionSqlServer.php';

/**
 * Retorna el base64 de la firma activa para un establecimiento, o null si no existe.
 */
function firmas_getBase64(int $idEstablecimiento): ?string {
    if ($idEstablecimiento <= 0) {
        return null;
    }
    try {
        $con  = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
        $stmt = $con->consultar(
            "SELECT firma_Base64 FROM firmas
             WHERE firma_IdEstablecimiento = $idEstablecimiento AND firma_Estado = 1"
        );
        $row = $con->obnerFila($stmt);
        return $row ? $row['firma_Base64'] : null;
    } catch (\Exception $e) {
        return null;
    }
}

/**
 * Retorna el base64 de la firma personal de un usuario, o null si no existe.
 */
function firmas_getBase64Usuario(int $idUsuario): ?string {
    if ($idUsuario <= 0) {
        return null;
    }
    try {
        $con  = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
        $stmt = $con->consultar(
            "SELECT fu_Base64 FROM firmas_usuario WHERE fu_IdUsuario = $idUsuario"
        );
        $row = $con->obnerFila($stmt);
        return $row ? $row['fu_Base64'] : null;
    } catch (\Exception $e) {
        return null;
    }
}
