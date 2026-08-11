<?php

// ==========================================
// CONFIGURACIÓN DE MARCA BLANCA (MULTI-TENANT)
// ==========================================
//
// Copie este archivo como `config.municipio.php` (en la raiz del proyecto) y
// ajuste los valores del municipio. `config.municipio.php` esta en .gitignore
// para que las credenciales reales no lleguen al repositorio.
//
// Este archivo lo incluyen varios puntos de entrada (index.php, dist/menu.php,
// dist/dashboard.php y business/class.conexionUsuarios.php). Todas las
// constantes se declaran con guarda `defined()` para que incluirlo mas de una
// vez no emita "Constant already defined" ni imprima warnings en el HTML.

// 1. Datos del Municipio
if (!defined('MUNICIPIO_NOMBRE')) define('MUNICIPIO_NOMBRE', 'Nombre del Municipio');
if (!defined('MUNICIPIO_CIUDAD')) define('MUNICIPIO_CIUDAD', 'Ciudad del Municipio');
// Departamento al que pertenece el municipio. Va en el encabezado de los dos
// formularios PDF ("DEPARTAMENTO"), que identifica DONDE se declara el
// impuesto -no donde vive el contribuyente, eso es la direccion de
// notificacion, que sale de conf_ciudades-.
if (!defined('MUNICIPIO_DEPARTAMENTO')) define('MUNICIPIO_DEPARTAMENTO', 'Departamento del Municipio');

// Rutas web ABSOLUTAS: la constante se usa desde /erpsoftsas/ y desde
// /erpsoftsas/dist/, por lo que una ruta relativa no puede servir a ambas.
// MUNICIPIO_LOGO      -> escudo cuadrado, para slots pequenos (32-56 px).
// MUNICIPIO_LOGO_FULL -> lockup horizontal (escudo + texto), para areas anchas.
if (!defined('MUNICIPIO_LOGO'))      define('MUNICIPIO_LOGO', '/erpsoftsas/vendors/images/escudo-municipio.png');
if (!defined('MUNICIPIO_LOGO_FULL')) define('MUNICIPIO_LOGO_FULL', '/erpsoftsas/vendors/images/logo-municipio-full.png');

// Color institucional. Igual que el nombre y los logos, cambia por
// municipio: de aqui salen el login, la cabecera y el menu lateral.
// MUNICIPIO_COLOR_OSCURO se usa en degradados y estados hover.
if (!defined('MUNICIPIO_COLOR'))        define('MUNICIPIO_COLOR', '#1fa49d');
if (!defined('MUNICIPIO_COLOR_OSCURO')) define('MUNICIPIO_COLOR_OSCURO', '#17756f');

// Fondo de Login y Sello de Firma Digital
if (!defined('MUNICIPIO_FONDO_LOGIN'))  define('MUNICIPIO_FONDO_LOGIN', 'vendors/images/fondo-municipio.jpg');
if (!defined('MUNICIPIO_SELLO_FIRMA'))  define('MUNICIPIO_SELLO_FIRMA', 'Sello_Firma.png');

// 2. Base de Datos (Producción)
if (!defined('DB_PROD_SERVER')) define('DB_PROD_SERVER', 'localhost');
if (!defined('DB_PROD_USER'))   define('DB_PROD_USER', 'usuario_prod');
if (!defined('DB_PROD_PASS'))   define('DB_PROD_PASS', 'clave_prod');
if (!defined('DB_PROD_NAME'))   define('DB_PROD_NAME', 'bd_prod');

// 3. Base de Datos (Desarrollo/Pruebas)
if (!defined('DB_DEV_SERVER')) define('DB_DEV_SERVER', 'db');
if (!defined('DB_DEV_USER'))   define('DB_DEV_USER', 'usuario_dev');
if (!defined('DB_DEV_PASS'))   define('DB_DEV_PASS', 'clave_dev');
if (!defined('DB_DEV_NAME'))   define('DB_DEV_NAME', 'bd_dev');

// 4. PlacetoPay (PSE ICA) — credenciales del convenio de recaudo con el
// banco. Usar el login/secretKey de PRUEBA mientras se certifica, y los de
// producción solo cuando el banco los entregue para el paso a producción.
if (!defined('PLACETOPAY_LOGIN'))      define('PLACETOPAY_LOGIN', 'login_placetopay');
if (!defined('PLACETOPAY_SECRETKEY'))  define('PLACETOPAY_SECRETKEY', 'secretkey_placetopay');
if (!defined('PLACETOPAY_BASEURL'))    define('PLACETOPAY_BASEURL', 'https://checkout.test.avalpaycenter.com/api');
