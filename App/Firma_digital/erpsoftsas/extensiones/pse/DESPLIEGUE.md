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

## 7. Wompi (Bancolombia) en vez de PlacetoPay — Macanal (migración 042)

Cada entidad cobra con SU pasarela: el parámetro `PASARELA_PROVEEDOR`
(Municipio y bancos) dice cuál. La 042 lo deja en `WOMPI` solo en la base de
Macanal; en las demás queda `PLACETOPAY` y nada cambia.

1. **Cuenta del comercio**: la Alcaldía crea la suya en comercios.wompi.co, a
   su nombre y con la cuenta bancaria donde Wompi consigna. En el panel, en
   Desarrolladores, están las cuatro llaves de cada ambiente.
2. **Llaves** (Municipio y bancos, detrás de la contraseña de edición):
   `WOMPI_LLAVE_PUBLICA` (pub_test_…), `WOMPI_LLAVE_PRIVADA` (prv_test_…),
   `WOMPI_SECRETO_INTEGRIDAD` (test_integrity_…) y `WOMPI_SECRETO_EVENTOS`
   (test_events_…). Las tres últimas nunca se vuelven a mostrar. Si se mezclan
   llaves de pruebas y de producción, el botón no se ofrece.
3. **Modo prueba**: `PASARELA_USUARIOS_PRUEBA` con el id del usuario de prueba
   (mejor un contribuyente de prueba: solo puede pagar lo suyo). Con las llaves
   de pruebas y la lista vacía el botón no lo ve nadie. Lo que se pague en
   pruebas queda con vía `WOMPI_PRUEBA` y banco "Wompi PRUEBAS - …". Al pasar a
   producción, revisar esas declaraciones y dejarlas como estaban, y vaciar la
   lista.
4. **URL de eventos** (en el panel de Wompi; exige https, así que el dominio
   necesita su certificado SSL antes):

   ```
   https://industria-comercio-macanal.erpsoftsas.com/erpsoftsas/extensiones/pse/wompi_eventos.php
   ```

5. **Cron de respaldo**: la misma tarea del punto 4 (`cron_verificar_pagos.php`,
   tipo "Ejecutar un script PHP": desde la 042 solo corre por línea de comandos)
   revisa también los intentos de Wompi que sigan abiertos. Las líneas
   "ATENCIÓN" son pagos para revisar a mano (DOBLE: devolver; REVISAR: no
   cuadran o se anularon); conviene que la tarea notifique su salida.
6. **Producción**: cambiar las cuatro llaves por las `…_prod_…` y vaciar
   `PASARELA_USUARIOS_PRUEBA`. El API pasa solo a production.wompi.co (lo
   decide la llave pública).

Comprobado contra el sandbox con las llaves de prueba de Macanal (2026-10-05):
pago aprobado registrado, rechazado sin registrar, recarga del retorno sin
duplicar, enlace con firma alterada rechazado. La búsqueda por referencia
(`GET /v1/transactions?reference=`) SÍ responde con la llave privada. Falta en
el dominio real: el aviso firmado (necesita el https) y un pago abandonado.

Ojo: Wompi bloquea (403 de su CDN) un `redirect-url` con `localhost`; en local
el retorno se abre a mano. Con el dominio del municipio no pasa.

## 8. AvalPay en producción — Paipa

AvalPay entregó las credenciales productivas (2026-10-06). Son tres sitios:
**"ICA - WC" es el de este sistema** (ICA, retención y autorretención); "OTROS
INGRESOS" y "PREDIAL" son de otras aplicaciones. Las credenciales no van aquí
(repositorio público): las escribe quien administra, en la pantalla.

1. **Parámetros ICA → Municipio y bancos** (detrás de la contraseña de
   edición), los tres juntos:
   - `PASARELA_BASEURL`: `https://checkout.avalpaycenter.com/api`. El correo de
     AvalPay da la dirección sin `/api`; el sistema la necesita CON `/api`
     (llama a `<base>/session`).
   - `PASARELA_LOGIN` y `PASARELA_SECRETKEY`: los del sitio "ICA - WC".
   Mezclar dirección de producción con credenciales de pruebas (o al revés)
   hace fallar toda sesión.
2. **Primer pago real con valor mínimo**, como pide AvalPay, todavía con
   `PASARELA_USUARIOS_PRUEBA` = el usuario de prueba: solo él ve el botón. Lo
   hace una persona con su propia cuenta; se comprueba que la declaración quede
   pagada y, al día siguiente, que el dinero llegue a la cuenta de la Alcaldía
   (compensación).
3. **Abrirlo**: vaciar `PASARELA_USUARIOS_PRUEBA`. Desde ahí todos ven
   "Pagar con PSE".
4. **Aviso (webhook)**: confirmar con soporte de AvalPay que el sitio
   productivo tenga la URL de notificación del punto 5. Sin ella el pago igual
   se registra al volver el contribuyente o con el cron, pero más tarde.
5. **Cron**: el de siempre, una vez al día de madrugada. Desde el 2026-10-06
   solo consulta sesiones pendientes, como pide AvalPay.
6. **Panel de AvalPay** (`dash.avalpaycenter.com`): la tesorería acepta la
   invitación que llega por correo.

Pagos de la certificación que quedaron en la base de Paipa: todos del
contribuyente de prueba (NIT 900900900), en ICA y retención. No tocan a ningún
contribuyente real; si estorban en los informes, se devuelven a "sin pagar".

**La sesión nace PENDIENTE (2026-10-06).** Antes solo se guardaba el requestId
al crearla y el estado quedaba vacío hasta la primera consulta: nada frenaba
una segunda sesión (otra pestaña), que pisaba la primera, y un pago hecho en la
primera ya no lo encontraban ni el aviso, ni el retorno, ni el cron.
`PlacetoPay::anotarSesion` la deja PENDING; el resumen muestra "Pago iniciado"
con lo que falta para que venza (30 min) y "Verificar estado del pago".
Revisión del mismo día, antes de subirlo:
- **No se crea otra encima** de una PENDING ni de una APPROVED que no se
  alcanzó a registrar, ni sobre una declaración ya pagada.
- **Un error del servicio no es un resultado**: si PlacetoPay contesta sin la
  sesión (credenciales de otro ambiente, reloj corrido), se lanza y no se
  anota nada; antes quedaba "FAILED" y se podía pagar encima.
- **El aviso de una sesión ya reemplazada** no pisa el estado de la nueva.
- **Volver sin pagar** (sin elegir banco) no borra "Pago iniciado" ni su hora.
- **El cron** deja de consultar solo las finales (REJECTED, EXPIRED,
  PARTIAL_EXPIRED); lo demás se sigue mirando.
