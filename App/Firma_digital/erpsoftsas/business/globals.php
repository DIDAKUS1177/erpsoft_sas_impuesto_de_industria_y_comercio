<?php
   // Los avisos de PHP NUNCA deben imprimirse en la respuesta: casi todos los
   // controladores que incluyen este archivo contestan JSON, y un simple
   // "Warning: Undefined array key" impreso antes del json_encode deja la
   // respuesta invalida. jQuery (dataType:'json') no la puede parsear,
   // success() no se ejecuta y -si el .ajax() no trae error()- la pantalla se
   // queda sin hacer nada, sin ningun mensaje. Asi fue justamente como el
   // boton "Liquidar" parecia estar muerto en produccion (2026-08-11), donde
   // display_errors esta activo, mientras en local (con display_errors off)
   // funcionaba perfecto. Se siguen registrando en el log de PHP.
   @ini_set('display_errors', '0');
   @ini_set('log_errors', '1');

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