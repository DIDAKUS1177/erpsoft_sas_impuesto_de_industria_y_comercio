<?php
include_once '/var/www/html/erpsoftsas/business/class.conexionSqlServer.php';
$conSql = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
$stmt = $conSql->consultar("SELECT table_name FROM information_schema.tables;");
while($row = $conSql->obnerFila($stmt)){
    echo $row['table_name']."\n";
}
