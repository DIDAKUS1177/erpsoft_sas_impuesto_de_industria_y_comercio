<?php
   // Cargar configuracion del municipio ANTES que cualquier conexion a BD se
   // instancie: sin esto, class.conexionSqlServer.php nunca ve las constantes
   // DB_PROD_* y cae a las credenciales de produccion hardcodeadas, sin
   // importar que municipio/despliegue sea. Este archivo (globals.php) es el
   // unico punto que TODOS los controladores de business/controller/*.php
   // incluyen antes de conectarse a la BD.
   //
   // Ubicacion real (Plesk/produccion): un nivel arriba de /erpsoftsas, fuera
   // del codigo versionado (ver plan_despliegue_plesk_claude.md). Este archivo
   // vive en business/, dos niveles bajo /erpsoftsas, por eso son dos dirname().
   // Fallback dentro de /erpsoftsas: solo para Docker local, donde
   // docker-compose.yml monta unicamente esa carpeta y el nivel de arriba no
   // existe dentro del contenedor.
   $_configMunicipioPath = dirname(dirname(__DIR__)) . '/config.municipio.php';
   if (!file_exists($_configMunicipioPath)) {
       $_configMunicipioPath = dirname(__DIR__) . '/config.municipio.php';
   }
   if (file_exists($_configMunicipioPath)) {
       require_once $_configMunicipioPath;
   }

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