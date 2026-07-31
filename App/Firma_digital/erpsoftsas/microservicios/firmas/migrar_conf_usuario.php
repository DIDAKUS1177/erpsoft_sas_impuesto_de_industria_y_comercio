<?php
/**
 * Migración única: conf_usuario  MySQL (erpsoftsas) → SQL Server (erpsoftweb)
 *
 * Copia todos los usuarios de la BD MySQL central a la tabla conf_usuario
 * de SQL Server, preservando usu_Id. Es idempotente: si el usuario ya
 * existe en SQL Server lo actualiza, si no lo inserta (upsert).
 *
 * Prerequisito: ejecutar antes sql_migracion_conf_usuario.sql
 * (crea la tabla en erpsoftweb).
 *
 * Ejecución (una sola vez, luego eliminar este archivo del servidor):
 *   - CLI:      php migrar_conf_usuario.php
 *   - Browser:  https://<host>/erpsoftsas/microservicios/firmas/migrar_conf_usuario.php
 *
 * Este es el ÚNICO punto que sigue tocando MySQL después de la migración
 * de api.php; una vez ejecutado, el módulo de firmas queda 100% SQL Server.
 */

if (php_sapi_name() === 'cli') {
    // En CLI no existe DOCUMENT_ROOT (o es string vacio): ajustar a la raíz real del proyecto
    if (empty($_SERVER['DOCUMENT_ROOT'])) {
        $_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../..');
    }
}

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.conexionUsuarios.php';      // MySQL (solo para esta migración)
include_once SERVER . '/business/class.conexionSqlServer.php';     // SQL Server

header('Content-type: text/plain; charset=utf-8');

try {
    $conMysql = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
    $conSql = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

    // Verificar que la tabla destino exista
    $stmtTabla = $conSql->consultar(
        "SELECT 1 AS existe FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'conf_usuario'"
    );
    if (!$conSql->obnerFila($stmtTabla)) {
        exit("ERROR: la tabla conf_usuario no existe en SQL Server.\n" .
             "Ejecute primero sql_migracion_conf_usuario.sql en erpsoftweb.\n");
    }

    $stmt = $conMysql->consultar(
        "SELECT usu_Id, usu_Nombre, usu_Usuario, usu_NumeroDocumento,
                usu_Correo, usu_Password, usu_Rol, usu_Estado
         FROM conf_usuario"
    );

    $insertados = 0;
    $actualizados = 0;

    while ($u = $conMysql->obnerFila($stmt)) {
        $params = [
            $u['usu_Nombre'],
            $u['usu_Usuario'],
            $u['usu_NumeroDocumento'],
            $u['usu_Correo'],
            $u['usu_Password'],
            intval($u['usu_Rol']),
            intval($u['usu_Estado']),
            intval($u['usu_Id'])
        ];

        $stmtExiste = $conSql->consultar(
            "SELECT usu_Id FROM conf_usuario WHERE usu_Id = ?",
            [intval($u['usu_Id'])]
        );

        if ($conSql->obnerFila($stmtExiste)) {
            $conSql->consultar(
                "UPDATE conf_usuario
                 SET usu_Nombre = ?, usu_Usuario = ?, usu_NumeroDocumento = ?,
                     usu_Correo = ?, usu_Password = ?, usu_Rol = ?, usu_Estado = ?
                 WHERE usu_Id = ?",
                $params
            );
            $actualizados++;
        } else {
            $conSql->consultar(
                "INSERT INTO conf_usuario
                    (usu_Nombre, usu_Usuario, usu_NumeroDocumento, usu_Correo,
                     usu_Password, usu_Rol, usu_Estado, usu_Id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                $params
            );
            $insertados++;
        }
    }

    echo "Migración completada.\n";
    echo "Usuarios insertados:   $insertados\n";
    echo "Usuarios actualizados: $actualizados\n";
    echo "\nRecuerde ELIMINAR este archivo del servidor tras la migración.\n";
} catch (\Exception $e) {
    echo "ERROR en la migración: " . $e->getMessage() . "\n";
}
