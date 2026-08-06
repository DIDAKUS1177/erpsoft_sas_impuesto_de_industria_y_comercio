# Plan de Despliegue en Plesk (Arquitectura Multi-Tenant) para Claude

Este documento detalla la estrategia de DevOps diseñada para desplegar el sistema de Industria y Comercio en múltiples municipios (Paipa, Guateque, etc.) utilizando **Plesk**, **Git** y una **Arquitectura Marca Blanca (Multi-Tenant)**.

## Objetivo
Lograr que el mismo código fuente base alojado en GitHub sea consumido por múltiples instancias (dominios/subdominios) en Plesk. Cada instancia tendrá su propia base de datos y su propia identidad visual (escudos, colores, firma digital), controladas enteramente por un archivo de configuración local.

---

## 1. Modificación Crítica Realizada (Contexto para Claude)

Se modificó el archivo `business/class.conexionUsuarios.php` del núcleo del sistema. 
Anteriormente, este archivo tenía credenciales de base de datos "hardcoded" en un array. **Ahora, el método `_getConexion()` intercepta las constantes definidas en `config.municipio.php` (si existen) y sobrescribe las credenciales.**

Esto permite que:
1. El archivo `class.conexionUsuarios.php` pueda ser versionado y distribuido a todos los municipios de forma segura.
2. Cada municipio se conecte a su propia base de datos simplemente leyendo las variables de entorno inyectadas desde el archivo de configuración.

---

## 2. Paso a Paso para Desplegar el Primer Municipio (Ej. Paipa)

Claude, debes guiar al usuario o ejecutar estos pasos en el panel de control Plesk:

### 2.1. Conectar Plesk con GitHub
1. En Plesk, ingresar al dominio destinado para Paipa (ej. `sistema.erpsoftsas.com`).
2. Abrir la extensión de **Git**.
3. Añadir el repositorio remoto: `https://github.com/DIDAKUS1177/erpsoft_sas_impuesto_de_industria_y_comercio.git`.
4. Configurar el despliegue (Deployment) para que extraiga el código en el directorio raíz de la aplicación web, típicamente la carpeta **`/erpsoftsas`** (dependiendo de la estructura DocumentRoot definida en Plesk).
5. Realizar el despliegue (Pull) inicial.

### 2.2. Creación del Archivo de Configuración Maestro
1. Abrir el **Administrador de Archivos** de Plesk en ese dominio.
2. Navegar a la carpeta **padre** o directorio seguro que esté un nivel por encima o en la raíz pública dependiendo de la configuración de seguridad. (Por regla general, el código PHP de `index.php` busca el archivo `config.municipio.php` usando `dirname(__DIR__)`).
3. Crear el archivo **`config.municipio.php`** (Este archivo está intencionalmente excluido en el `.gitignore` para no sobreescribirse entre municipios).
4. Inyectar la configuración específica de Paipa y sus credenciales de Base de Datos:

```php
<?php
// ==========================================
// CONFIGURACIÓN: PAIPA
// ==========================================
// 1. Branding y Textos
if (!defined('MUNICIPIO_NOMBRE')) define('MUNICIPIO_NOMBRE', 'Alcaldía de Paipa');
if (!defined('MUNICIPIO_CIUDAD')) define('MUNICIPIO_CIUDAD', 'Paipa');

if (!defined('MUNICIPIO_LOGO'))      define('MUNICIPIO_LOGO', '/erpsoftsas/vendors/images/escudo-paipa.png');
if (!defined('MUNICIPIO_LOGO_FULL')) define('MUNICIPIO_LOGO_FULL', '/erpsoftsas/vendors/images/logo-municipio-full.png');
if (!defined('MUNICIPIO_SELLO_FIRMA'))  define('MUNICIPIO_SELLO_FIRMA', 'Sello_Firma.png');

if (!defined('MUNICIPIO_COLOR'))        define('MUNICIPIO_COLOR', '#1fa49d');
if (!defined('MUNICIPIO_COLOR_OSCURO')) define('MUNICIPIO_COLOR_OSCURO', '#17756f');

// 2. Base de Datos (Producción)
if (!defined('DB_PROD_SERVER')) define('DB_PROD_SERVER', 'localhost');
if (!defined('DB_PROD_USER'))   define('DB_PROD_USER', 'usuario_db_paipa'); // Cambiar por usuario real en Plesk
if (!defined('DB_PROD_PASS'))   define('DB_PROD_PASS', 'clave_super_segura'); // Cambiar por clave real
if (!defined('DB_PROD_NAME'))   define('DB_PROD_NAME', 'nombre_bd_paipa'); // Cambiar por DB real
```

---

## 3. Despliegue de Municipios Adicionales (Ej. Guateque)

El proceso para escalar el sistema a un segundo municipio es idéntico, demostrando la eficacia de la marca blanca:

1. Crear el nuevo dominio/subdominio en Plesk (ej. `guateque.erpsoftsas.com`).
2. Usar la extensión Git para hacer pull del **mismo repositorio exacto** hacia la carpeta `/erpsoftsas` del nuevo dominio.
3. Crear una nueva Base de Datos en Plesk (ej. `db_guateque`) y poblarla con el esquema SQL del sistema.
4. Crear el archivo `config.municipio.php` en el nuevo dominio, esta vez definiendo colores verdes, el nombre "Alcaldía de Guateque", el logo correspondiente y las credenciales de `db_guateque`.

## 4. Mantenimiento y Actualizaciones (CI/CD Básico)

* Cada vez que se desarrolle una nueva funcionalidad (ej. un nuevo reporte PDF), se hace Push a la rama `main` en GitHub.
* Mediante Webhooks (o pulls manuales en Plesk), **todos** los municipios recibirán la actualización del código instantáneamente.
* Ningún municipio perderá su identidad visual ni sus conexiones a base de datos, ya que el archivo `config.municipio.php` está protegido y es local para cada entorno en Plesk.
