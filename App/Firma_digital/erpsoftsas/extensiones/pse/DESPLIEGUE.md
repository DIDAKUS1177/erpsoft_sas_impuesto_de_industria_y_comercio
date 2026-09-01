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

## 3. Credenciales del banco — desde la PANTALLA, no desde el archivo

**Desde la migración `023` esto ya no se edita en el servidor.** Las tres cosas
que entrega el banco se escriben en *Configuración*, dentro del propio sistema:

| Parámetro | Qué es |
|---|---|
| `PASARELA_BASEURL` | La dirección del servicio. Cambia entre pruebas y producción (normalmente basta quitar el `test.` del dominio). |
| `PASARELA_LOGIN` | El usuario del convenio. |
| `PASARELA_SECRETKEY` | La clave. **No se muestra nunca**: la pantalla sólo dice si está puesta. Para cambiarla se escribe la nueva; dejarla en blanco no la borra. |

Se hizo así porque el banco **depende del contrato de cada entidad** (instrucción
del cliente, 2026-08-26), y editar un archivo del servidor por cada cambio de
convenio, rotación de clave o paso a producción no es sostenible con varios
municipios. Es el mismo camino que ya recorrió el EAN de recaudo.

Mientras los tres estén vacíos manda el archivo de siempre, así que **una
instalación existente sigue funcionando igual sin tocar nada**.

### El archivo, sólo como respaldo

Las constantes siguen funcionando y sirven para una instalación que aún no tenga
la migración `023`. Si se usan, van en `config.municipio.php` (un nivel arriba de
`/erpsoftsas`, NO se sube por git, se edita a mano en Plesk):

```php
if (!defined('PLACETOPAY_LOGIN'))      define('PLACETOPAY_LOGIN', 'TU_LOGIN_AQUI');
if (!defined('PLACETOPAY_SECRETKEY'))  define('PLACETOPAY_SECRETKEY', 'TU_SECRETKEY_AQUI');
if (!defined('PLACETOPAY_BASEURL'))    define('PLACETOPAY_BASEURL', 'https://checkout.test.avalpaycenter.com/api');
```

**Lo que se escriba en la pantalla gana sobre el archivo.** Si algo no toma
efecto, mirar primero si el parámetro tiene valor en Configuración.

### Sin convenio no se ofrece el pago

Si los tres faltan —tabla y archivo—, el botón "Pagar PSE" no se pinta y la URL
del pago contesta un mensaje explicando que se puede pagar en el banco con el
código de barras. Antes, faltando una constante, salía una página en blanco.

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
https://industria-comercio-paipa.erpsoftsas.com/erpsoftsas/extensiones/pse/webhook.php
```

> **Ojo con el dominio.** Aquí decía `paipa.erpsoftsas.com`, que es el de
> **predial** — otra aplicación, otro stack. Esa dirección devuelve 404
> (comprobado el 2026-08-27), así que si se le hubiera dado al banco, las
> notificaciones de pago no habrían llegado a ninguna parte, y el fallo sería
> difícil de diagnosticar porque todo lo demás funcionaría. La correcta es la de
> arriba, donde el archivo sí existe.

Esto normalmente lo pide el mismo banco/PlacetoPay como parte del proceso de
homologación — coordinarlo directamente con su contacto de soporte técnico.

## 6. Cuándo habilitar el botón "Pagar PSE" en producción real

El botón ya está habilitado en el código (apunta a `crearSesion.php`), pero
mientras la dirección de la pasarela siga en modo prueba, cualquier clic ahí crea
sesiones de PRUEBA, no reales. No hace falta "activar" nada aparte: el
comportamiento cambia solo con las credenciales/URL de producción del punto 3.
