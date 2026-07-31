<?php
include_once '/var/www/html/erpsoftsas/business/class.conexionSqlServer.php';
$conSql = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
$stmt = $conSql->consultar("UPDATE conf_usuarios SET usu_Correo = 'diealeherbla.dh@gmail.com' WHERE usu_Usuario = 'administrador'");
echo "Email actualizado";
