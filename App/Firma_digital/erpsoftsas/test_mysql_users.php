<?php
include_once '/var/www/html/erpsoftsas/business/class.conexionUsuarios.php';
$conSql = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
$stmt = $conSql->consultar("SELECT usu_Id, usu_Nombre, usu_Usuario FROM conf_usuario;");
while($row = $conSql->obnerFila($stmt)){
    print_r($row);
}
