<?php
   define ('SERVER', $_SERVER['DOCUMENT_ROOT']."/erpsoftsas");
   header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
	header("Expires: Sat, 1 Jul 2000 05:00:00 GMT"); // Fecha en el pasado
/*
 * MODULOS
 */
define('DASHBOARD_ADMIN', 1);
define('PROYECTOS', 2);
define('CLIENTES', 3);
define('USUARIOS', 4);
define('ROL', 5);
define('AMBITOS', 6);
define('COMBO', 7);
define('EVENTO', 8);
define('MENSAJES', 9);
define('CONFIGMENSAJE', 10);
define('SLA', 11);
define('LINEABASE', 12);