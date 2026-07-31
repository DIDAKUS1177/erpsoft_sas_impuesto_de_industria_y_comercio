<?php
include_once '/var/www/html/erpsoftsas/business/class.conexionSqlServer.php';
$conSql = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
$stmt = $conSql->consultar("SELECT usu_Id, usu_Nombres, usu_Usuario, usu_Estado FROM conf_usuarios;");
while($row = $conSql->obnerFila($stmt)){
    echo $row['usu_Id'] . " | " . $row['usu_Nombres'] . " | " . $row['usu_Usuario'] . " | " . $row['usu_Estado'] . "\n";
}
