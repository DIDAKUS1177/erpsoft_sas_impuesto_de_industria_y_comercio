<?php

// ========================================== 
// CONFIGURACIÓN DE MARCA BLANCA (MULTI-TENANT) 
// ========================================== 

// 1. Datos del Municipio 
define('MUNICIPIO_NOMBRE', 'Nombre del Municipio');
define('MUNICIPIO_LOGO', '/assets/img/logo_default.png');

// 2. Base de Datos (Producción) 
define('DB_PROD_SERVER', 'localhost');
define('DB_PROD_USER', 'usuario_prod');
define('DB_PROD_PASS', 'clave_prod');
define('DB_PROD_NAME', 'bd_prod');

// 3. Base de Datos (Desarrollo/Pruebas) 
define('DB_DEV_SERVER', '10.0.30.28');
define('DB_DEV_USER', 'usuario_dev');
define('DB_DEV_PASS', 'clave_dev');
define('DB_DEV_NAME', 'bd_dev');

?>