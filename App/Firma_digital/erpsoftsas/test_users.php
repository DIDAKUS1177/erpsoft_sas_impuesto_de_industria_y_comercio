<?php
include_once '/var/www/html/erpsoftsas/business/class.conexionSqlServer.php';
$conSql = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
$stmt = $conSql->consultar("SELECT usu_Id, usu_Nombres, usu_Usuario, usu_Estado FROM conf_usuarios;");
while($row = $conSql->obnerFila($stmt)){
    print_r($row);
}
