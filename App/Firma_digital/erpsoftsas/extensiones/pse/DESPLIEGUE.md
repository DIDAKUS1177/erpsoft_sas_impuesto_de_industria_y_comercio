# Despliegue a Plesk — Código de barras + PSE PlacetoPay

## 1. Archivos a subir (todo lo demás del `git status` es de otra sesión de
   pruebas con el municipio "Guateque" — NO subir eso, es de otro trabajo)

Nuevos:
- `business/class.placetopay.php`
- `extensiones/pse/` (carpeta completa: crearSesion.php, retorno.php, webhook.php, cron_verificar_pagos.php)

Modificados:
- `business/globals.php`
- `dist/dashboard.php`
- `dist/menu.php`
- `index.php`
- `core/declaraciones.ui.js`
- `extensiones/declaracion.php`
- `extensiones/liquidacion.php`
- `config.municipio.example.php` (plantilla, no lleva secretos reales)

## 2. Base de datos de producción

Correr `extensiones/pse/migracion_produccion.sql` UNA VEZ contra la base de
datos real (no la de Docker). Es seguro volver a correrlo por accidente, no
duplica nada.

## 3. Config real del servidor (`config.municipio.php`, un nivel arriba de
   `/erpsoftsas` — NO se sube por git, hay que editarlo a mano en Plesk)

Agregar, cuando el banco entregue las credenciales de PRODUCCIÓN (mientras
tanto usar las de prueba para seguir probando ya en el servidor real):

```php
if (!defined('PLACETOPAY_LOGIN'))      define('PLACETOPAY_LOGIN', 'TU_LOGIN_AQUI');
if (!defined('PLACETOPAY_SECRETKEY'))  define('PLACETOPAY_SECRETKEY', 'TU_SECRETKEY_AQUI');
if (!defined('PLACETOPAY_BASEURL'))    define('PLACETOPAY_BASEURL', 'https://checkout.test.avalpaycenter.com/api');
```

Cuando pasen a producción real, `PLACETOPAY_BASEURL` cambia a la URL de
producción que entregue el banco (normalmente sin "test." en el dominio).

## 4. Tarea programada en Plesk (para el cron de respaldo)

Plesk > paipa.erpsoftsas.com > Herramientas de desarrollo > Tareas programadas > Añadir tarea:
- Tipo: "Ejecutar un script PHP"
- Ruta del script: `httpdocs/erpsoftsas/extensiones/pse/cron_verificar_pagos.php`
  (ajustar la ruta exacta según donde quede publicado `erpsoftsas/` en ese hosting)
- Frecuencia mientras se prueba: cada 5 minutos
- Frecuencia en producción: 1 vez al día, en horario de bajo tráfico (madrugada)

## 5. Panel de PlacetoPay (durante la certificación con el banco)

Registrar ante PlacetoPay la URL pública del webhook para que la apunten en
su sistema:

```
https://paipa.erpsoftsas.com/erpsoftsas/extensiones/pse/webhook.php
```

Esto normalmente lo pide el mismo banco/PlacetoPay como parte del proceso de
homologación — coordinarlo directamente con su contacto de soporte técnico.

## 6. Cuándo habilitar el botón "Pagar PSE" en producción real

El botón ya está habilitado en el código (apunta a `crearSesion.php`), pero
mientras `PLACETOPAY_BASEURL` siga en modo prueba, cualquier clic ahí crea
sesiones de PRUEBA, no reales. No hace falta "activar" nada aparte: el
comportamiento cambia solo con las credenciales/URL de producción del punto 3.
