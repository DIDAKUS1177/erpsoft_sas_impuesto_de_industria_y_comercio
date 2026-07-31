<?php
include_once '/var/www/html/erpsoftsas/business/class.conexionSqlServer.php';
$conSql = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
$stmt = $conSql->consultar("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_name='conf_usuarios';");
while($row = $conSql->obnerFila($stmt)){
    echo $row['COLUMN_NAME']."\n";
}
