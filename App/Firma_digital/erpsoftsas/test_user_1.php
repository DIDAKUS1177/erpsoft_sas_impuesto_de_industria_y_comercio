<?php
include_once '/var/www/html/erpsoftsas/business/class.conexionSqlServer.php';
$conSql = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
$stmt = $conSql->consultar("SELECT usu_Id, usu_Nombres, usu_Correo, usu_Estado FROM conf_usuarios WHERE usu_Id = 1;");
while($row = $conSql->obnerFila($stmt)){
    print_r($row);
}
