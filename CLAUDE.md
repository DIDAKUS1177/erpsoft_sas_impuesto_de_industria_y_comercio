# ERPSOFTSAS — Industria y Comercio, Alcaldía de Paipa

Sistema de declaración de ICA (Industria y Comercio) de la Alcaldía de Paipa (Boyacá,
Colombia). Backend PHP 8.1 + SQL Server, frontend jQuery/Bootstrap 4, PDFs con TCPDF.
Diseñado para ser multi-tenant (marca blanca): cambiar de municipio no debería exigir
tocar lógica, solo `config.municipio.php`.

> `contextualiza_a_claude.md` (en esta misma raíz) es un documento **anterior y
> desactualizado** — describe una paleta azul que ya no existe (el municipio real usa
> teal `#1fa49d`) y una función de "lupa" que fue reemplazada por completo. No confiar
> en él; este archivo es la fuente vigente.

## Dónde está el código real (importante — hay carpetas trampa)

```
App/Firma_digital/
├── config.municipio.php       ← config activa (nombre, logo, color, credenciales BD)
└── erpsoftsas/                  ← TODA la aplicación real. Esta carpeta es la raíz
    │                              web servida por el contenedor Docker del 8081.
    ├── business/                 controladores, DAO, conexión a BD
    ├── core/                     JS de cada pantalla
    ├── dist/                     vistas PHP (una por pantalla)
    ├── extensiones/               generación de PDFs (TCPDF)
    ├── microservicios/firmas/     API de firma digital + OTP por correo
    └── index.php                  login

predial/dist/dashboard.php     ← portal público (landing page sin login), MÓDULO
                                   APARTE, solo servido por el contenedor del 8080
                                   (que monta todo el repo). No confundir con el
                                   dashboard interno (App/Firma_digital/erpsoftsas/dist/dashboard.php).

_archivo_obsoleto_YYYY-MM-DD/  ← carpetas de limpieza: duplicados/backups viejos
                                   movidos aquí (nunca borrados) durante las sesiones
                                   de orden del repo. Seguro ignorarlas.
```

**Gotcha crítico y recurrente**: varios archivos (`index.php`, `dist/menu.php`,
`dist/dashboard.php`, `business/globals.php`, `extensiones/declaracion.php`,
`extensiones/liquidacion.php`) buscan `config.municipio.php` **uno o dos niveles
arriba** de donde están (`dirname(__DIR__)` / `dirname(dirname(__DIR__))`), apuntando
siempre primero a `App/Firma_digital/config.municipio.php` (la ubicación real en
Plesk/producción, fuera del código versionado). Si ese archivo no existe ahí — como
pasa en el contenedor Docker local `erpsoftsas_web_completo`, que monta *solo*
`erpsoftsas/` y por lo tanto no puede ver un nivel arriba — cada uno de esos archivos
cae a un segundo intento dentro de `erpsoftsas/config.municipio.php` (gitignored,
solo para desarrollo local; **nunca** debe copiarse a un despliegue real). Ambos
niveles usan el guard `defined()`, así que el primero que se resuelva gana.

2026-08-10: se corrigió un bug real en este mecanismo — `index.php`, `dist/menu.php`,
`dist/dashboard.php`, `business/globals.php` y los dos generadores de PDF en
`extensiones/` tenían el cálculo de `dirname()` un nivel corto (aterrizaban todos en
`erpsoftsas/`, nunca en `App/Firma_digital/`), así que en la práctica el archivo local
de Docker siempre ganaba y el real de un nivel arriba nunca se leía. Se agregó el
fallback explícito de dos niveles descrito arriba. Antes de cualquier despliegue
nuevo, seguir confirmando que `config.municipio.php` es alcanzable desde la ruta un
nivel arriba de `erpsoftsas/`.

## Docker (entorno local)

| Contenedor | Puerto | Monta | Uso |
|---|---|---|---|
| `erpsoftsas_web_completo` | 8081 | solo `App/Firma_digital/erpsoftsas/` | el que se usa para probar — siempre correcto |
| `erpsoftsascomalcaldiadepaipa-web-1` | 8080 | todo el repo | sirve tambien `predial/` |
| `erpsoftsascomalcaldiadepaipa-db-1` | 1433 | — | SQL Server local (normalmente NO es el que usan los scripts de prueba: ver nota abajo) |
| `dreamy_hopper` | — | — | phpMyAdmin |

Los cuatro a veces no arrancan solos tras reiniciar Windows/Docker Desktop — verificar
con `docker ps` y `docker start <nombre>` si falta alguno.

**Docker Desktop en esta máquina se ha corrompido más de una vez** (sockets AF_UNIX
huérfanos en `AppData\Local\Docker\run\` o `AppData\Local\docker-secrets-engine\` que
ni admin ni reinicio de Windows logran borrar por las vías normales). Solución que
funcionó: `Rename-Item` de la carpeta contenedora completa (no del archivo individual)
para sacarla del paso, dejar que Docker Desktop recree una limpia.

Login de prueba: usuario `administrador`, clave `administrador2025`.

## Reglas de negocio confirmadas por el cliente (no inventar, no asumir)

- La declaración de ICA es **una por contribuyente y año**, no por establecimiento —
  un contribuyente con varios locales declara una sola vez, agregando actividades por
  código CIIU.
- Contador/revisor fiscal firman con OTP a su correo (`ind_EmailContador` /
  `ind_EmailRevisor` en `ind_contribuyentes`), comparten una sola casilla en el
  formulario (se usa el del contador; el del revisor solo si el del contador está
  vacío).
- Es obligatorio firmar como contador/revisor **solo** cuando la ley lo exige: persona
  jurídica siempre; persona natural solo si sus ingresos superan 3.500 UVT (constante
  en `business/config.tributario.php`, **hay que actualizar el valor de la UVT cada
  enero** — la de 2026 es $52.374, Resolución DIAN 000238/2025).
- Una declaración ya **presentada** no se edita ni se vuelve a crear una "original"
  para el mismo período — la única vía correcta es "Corregir" (crea una nueva ligada
  por `dec_DeclaracionCorrige`). Esto es un requisito legal, no una limitación técnica.
- PSE / código de barras: **implementado y desplegado en producción** (2026-08-10, ver
  sección "PSE PlacetoPay" abajo). Los botones ya están habilitados.

## Estado del sistema (última sesión de trabajo activa)

Completado: recuperación de clave por NIT/cédula, accesos rápidos por módulo,
declaración a nivel contribuyente con agregación de actividades, edición de
declaraciones en borrador, estados (borrador → firmada → falta-contador → presentada
→ corrección) con un solo botón "Presentar" que encadena el OTP del contador
automáticamente si falta, sello con fecha de presentación en el PDF, RIT reorganizado
(contador y revisor fiscal en tarjetas separadas con su correo), código de barras +
integración de pago PSE (PlacetoPay).

Pendiente / conocido: el conteo de "No. establecimientos" del formulario debería
filtrar solo los de Paipa (`est_Local_municipio`), pero ese campo nunca se captura en
el RIT (está comentado en el JS) — hasta que se capture, cuenta todos los
establecimientos del contribuyente. El sistema de roles/permisos (`conf_rol`,
`conf_permisos`, pantalla `dist/rol.php`) ya existe en el código pero no está
configurado a fondo para este cliente.

## Código de barras (declaracion.php / liquidacion.php)

Referencia = `dec_NumeroDeclaracion`, dibujado con la librería TCPDF ya vendorizada
(`extensiones/tcpdf/tcpdf_barcodes_1d.php`, clase `TCPDFBarcode`, tipo `C128`) —
**formato provisional**, pendiente de que el banco confirme si exige otro estándar
para el código de barras/referencia de recaudo.

`liquidacion.php` fue reescrito completo de Cell()/Rect() posicionados a mano a tablas
HTML vía `writeHTML()` (mismo patrón que `declaracion.php`), porque el layout manual
original no dejaba margen para el código de barras sin cortarse en el borde inferior
de la página (`SetAutoPageBreak` está en `false` en ambos archivos — TCPDF no avisa si
el contenido se pasa del borde).

## Cambios solicitados por el cliente, lote 2026-08-11 (puntos 1, 4, 5, 11, 13)

De la lista de 13 sugerencias (`sugerencias ica diego.pdf`), quedan estos por
documentar (los del lote anterior ya estaban en este archivo):

- **Punto 1 (cambio de contraseña propio)**: antes la única vía era el
  reseteo por correo (clave temporal generada por el sistema), sin forma de
  volver a asignar una propia. Se agregó "Cambiar Contraseña" al dropdown de
  usuario en `dist/menu.php` (compartido por todas las pantallas internas),
  con backend en `class.usuarios.php` `funcion=6` (`_cambiarClave`). Exige la
  contraseña actual para autorizar el cambio. **Detalle no obvio**: las
  claves se guardan con `HASHBYTES('SHA1', texto)` vía `DAO->guardar()`
  (tipodato `'clave'`), pero `DAO->consultar()` NO aplica ese hash del lado
  del `WHERE` — hace comparación literal. Por eso, para validar la clave
  actual, hay que replicar lo que hace el login real
  (`business/controller/class.login.php`): comparar contra `sha1()` calculado
  en PHP, no contra el texto plano. Funciona porque SQL Server compara
  mayúsculas/minúsculas indistintamente por la collation por defecto.

- **Puntos 4 y 5 (catálogos geográficos + municipio de registro incorrecto)**:
  se resolvieron juntos porque comparten la misma causa. `conf_ciudades` solo
  tenía 240 municipios (Bogotá/Boyacá/Cundinamarca); se completó con el
  catálogo DIVIPOLA completo (1.120 municipios, 33 departamentos), tomado de
  `datos.gov.co`/DANE vía `panchicore/dane_colombia` en GitHub (nombres con
  tilde cruzados contra un segundo dataset independiente; ~15 casos sin match
  se corrigieron a mano — ver `CORRECCIONES` en el script de migración, ya
  no versionado). La causa real del "municipio de registro carga mal": en
  `business/controller/class.usuarios.php`, la inscripción pública
  (`Inscribirse`) grababa `ind_IdCiudad = 1` (Tunja) **fijo, a ciegas, para
  cualquier contribuyente** — el formulario nunca pedía esa ciudad. Se
  agregó el campo "Municipio de Residencia" (select2) al formulario de
  inscripción (`index.php`) y al modal "Información del Contribuyente"
  (para que los ya registrados se corrijan ellos mismos), ambos alimentados
  por `class.ciudades.php`. Además, los selects de país/departamento/ciudad
  de la DIRECCIÓN del establecimiento (`est_Pais/est_Departamento/est_Ciudad`
  — columnas VARCHAR de texto libre, no FK) estaban fijos en un único
  `<option>` en 4 pantallas (`establecimientos.php`, `icaWebRit.php`,
  `icaWebConsultar.php`, `icaWebPresentar.php`); se creó `core/geografia.js`
  (departamento→ciudad en cascada, cacheado, reutilizado en las 4) para
  reemplazarlos.

- **Punto 13 (la corrección añadía "00000")**: el bug real estaba en
  `EditarDeclaracion.abrir()` (`core/declaraciones.ui.js`). SQL Server
  devuelve los totales como texto con PUNTO decimal (`"2500000.00"`), pero
  esos campos se leen después con `numero()`/`limpiarNumero()`, que tratan el
  punto como separador de MILES colombiano y lo eliminan:
  `"2500000.00"` → `250000000`. Cada vez que se abría/corregía una
  declaración el valor quedaba multiplicado por 100. Se arregló formateando
  esos valores a formato colombiano (`Math.round` + `toLocaleString('es-CO')`)
  antes de meterlos al input, igual que ya hacían las actividades gravadas
  (que nunca tuvieron el bug).

- **Punto 11 (firma de contador obligatoria)**: reemplazó por completo la
  regla anterior (persona jurídica siempre, persona natural solo sobre 3.500
  UVT — instrucción explícita del cliente, "la pasada muere"). Regla nueva en
  `_requiereContador()` (`class.declaracionesICA.php`): si el contribuyente
  tiene registrado un correo de contador **o** de revisor
  (`ind_EmailContador`/`ind_EmailRevisor`), la firma de esa persona es
  obligatoria para presentar — sin importar tipo de persona ni ingresos. Se
  quitaron `UMBRAL_INGRESOS_CONTADOR_NATURAL_UVT` y
  `UMBRAL_INGRESOS_REVISOR_FISCAL` de `business/config.tributario.php` por
  quedar sin uso; `UVT_VALOR`/`UVT_ANIO` se mantienen (uso general, no
  específico a esta regla).

- **Tipografía del menú lateral**: `Inter` ya se cargaba vía Google Fonts en
  todas las pantallas pero nunca se aplicaba de verdad al sidebar (caía al
  stack por defecto de Bootstrap). Se aplicó explícitamente en
  `dist/menu.php` junto con un leve ajuste de tamaño/letter-spacing.

### Bug de producción encontrado y corregido en el camino (2026-08-11)

El código de barras nativo de TCPDF (`<img src="@base64">`) rompía la
descarga de PDF en producción con `TCPDF ERROR: Unable to write file`: para
ese tipo de imagen TCPDF necesita escribir un archivo temporal en
`sys_get_temp_dir()`, y el PHP-FPM de Plesk no tiene permiso de escritura
ahí. Localmente no se detectaba porque el contenedor Docker sí permite
escribir en `/tmp`. Se reemplazó por `write1DBarcode()` (vectorial, no toca
disco) en `declaracion.php` y `liquidacion.php`.

De paso se encontró y corrigió la causa REAL de que el botón "Liquidar"
pareciera no hacer nada en producción (el fix anterior sobre `numero()` y la
coma decimal era un bug real pero no era este): `_insertarActividadesDeclaracionIca()`
leía `$totales['dec_CapacidadInstalada']`/`dec_ValorImpuesto`, dos claves que
el JS nunca envía. En PHP 8 eso emite un `Warning`, y en producción
(`display_errors` activo) el warning se imprime ANTES del `json_encode` —
la respuesta deja de ser JSON válido, `success()` no corre, y como el
`.ajax()` no tenía `error()`, la pantalla quedaba muda. `business/globals.php`
ahora fuerza `display_errors=0` (con `log_errors=1`) para que ningún aviso
de PHP vuelva a corromper una respuesta JSON en NINGÚN endpoint del sistema.

## Trampa: warnings de PHP que rompen respuestas JSON (2026-08-11)

`business/globals.php` fuerza `display_errors=0` a proposito. Casi todos los
controladores contestan JSON, y basta un `Warning: Undefined array key` impreso
antes del `json_encode` para que la respuesta deje de ser JSON valido; jQuery
(`dataType:'json'`) no la parsea, `success()` no corre y, si el `.ajax()` no
trae `error()`, la pantalla se queda **muda**: ni mensaje, ni spinner, nada.

Asi se comporto el boton "Liquidar" en produccion: parecia muerto. En local no
se reproducia porque ahi `display_errors` ya venia apagado — la diferencia de
configuracion entre local y produccion era justo lo que ocultaba el bug. El
disparador concreto: `_insertarActividadesDeclaracionIca()` leia
`$totales['dec_CapacidadInstalada']` y `$totales['dec_ValorImpuesto']`, dos
claves que el JS nunca envia (ademas de grabar NULL encima del valor guardado;
ahora se conserva con `COALESCE`).

Al escribir/tocar un `.ajax()` en este proyecto, **siempre** ponerle `error()`.
Y ojo con los comentarios SQL `--` dentro de cadenas PHP: si el comentario
lleva comillas dobles y la cadena tambien, se rompe el parseo.

## Marca de agua BORRADOR / PRESENTADA (declaracion.php / liquidacion.php)

Ambos PDFs dibujan un texto diagonal semitransparente ("BORRADOR" o "PRESENTADA",
según `dec_Estado`) sobre todo el contenido, vía una función `dibujarMarcaDeAgua()`
duplicada en cada archivo (mismo patrón que el resto de duplicación entre estos dos
generadores). Tres bugs de TCPDF encontrados y corregidos al implementarla
(2026-08-10), por si se reutiliza el patrón en otro PDF:

1. **Fuga de fuente**: `StartTransform()/StopTransform()` **no** restaura el estado de
   la fuente. Hay que guardar `getFontFamily()/getFontStyle()/getFontSizePt()` antes
   de `SetFont()` dentro del bloque y restaurarlos explícitamente después, o la fuente
   grande de la marca de agua se queda pegada al resto del documento.
2. **Posicionamiento**: dentro de un bloque `Rotate()`, `Cell()`/`SetXY()` interpretan
   las coordenadas en el espacio YA rotado, no en el espacio original de la página —
   intentar centrar con `Cell()` + ancho grande solo pinta un fragmento diminuto. Hay
   que usar `Text($cx - GetStringWidth($texto)/2, $cy, $texto)` (un solo punto de
   anclaje).
3. **Cuelgue infinito (el más grave)**: llamar la función justo después de
   `AddPage()` (antes del resto del contenido) cuelga el worker de PHP-FPM
   indefinidamente al ~99% CPU — probablemente interacción entre `Rotate()` temprano y
   el manejo interno de salto de página/HTML de TCPDF más adelante. Se resuelve
   llamando la función al final del archivo, justo antes de `Output()` (la marca queda
   dibujada encima del contenido en vez de debajo, visualmente sigue bien).

## PSE PlacetoPay (pago del impuesto)

Integración construida desde cero contra la documentación pública de PlacetoPay
(`docs.placetopay.dev/checkout`), usando credenciales de **prueba** (sandbox
Avalpaycenter/Banco de Bogotá) — ver `PLACETOPAY_*` en `config.municipio.php`.
Probada de extremo a extremo contra ese sandbox real antes de desplegar.

```
business/class.placetopay.php        auth (tranKey SHA256+Base64), crearSesion(),
                                      consultarSesion(), validarFirmaWebhook()
extensiones/pse/crearSesion.php      dispara el botón "Pagar PSE" -> crea sesión,
                                      guarda dec_PSE_RequestId, redirige al banco
extensiones/pse/retorno.php          a donde vuelve el usuario tras pagar
extensiones/pse/webhook.php          notificación automática de PlacetoPay
extensiones/pse/cron_verificar_pagos.php   respaldo, exigido por el banco
extensiones/pse/DESPLIEGUE.md        guía de despliegue paso a paso
extensiones/pse/migracion_produccion.sql   migración de BD (ya corrida en prod)
```

**Diseño de seguridad del webhook**: nunca se actualiza la declaración con datos
tomados directamente del POST de PlacetoPay. Primero se valida la firma que traen
(`hash(requestId + status.status + status.date + secretKey)`, SHA-1 por defecto o
SHA-256 con prefijo `sha256:` — fórmula **no documentada públicamente**, confirmada
leyendo un webhook de PlacetoPay ya usado en otro proyecto del equipo para predial,
ver `pruebas.erpsoftsas.com/respuestaplacepay.php` en Plesk). Aunque la firma sea
válida, el estado que se guarda siempre sale de una consulta autenticada aparte
(`consultarSesion`), nunca del payload — así una firma robada no basta para forjar un
pago.

**Nota importante sobre "código replicable" de PSE**: existe infraestructura PSE ya
desplegada en el mismo servidor para **PREDIAL** (dominios `pse{municipio}.erpsoftsas.com`
+ `serviciospse{municipio}.erpsoftsas.com` por cada municipio), pero es de otro stack
(Angular + .NET/C#, con su propia tabla de facturas y SP `SP_UPDATE_FACTURAS_GENERADAS`)
y **no es reutilizable** para esta app PHP de ICA — se investigó a fondo (2026-08-10)
antes de decidir construir desde cero. Lo único rescatado de ahí fue la fórmula de
firma del webhook, ya incorporada arriba.

### Despliegue (Plesk)

- Subscripción: `industria-comercio-paipa.erpsoftsas.com` — **cuidado**, existen
  varios dominios parecidos que NO son este proyecto: `paipa.erpsoftsas.com` es
  predial, `gestorpaipa.erpsoftsas.com` es otra cosa.
- Base de datos real: `erpsofts_ind_comercio_paip`.
- Plesk tiene Git conectado a este mismo repo (`erpsoft_sas_impuesto_de_industria_y_comercio`,
  rama `main`) con la app publicada en `App/Firma_digital/erpsoftsas/` dentro del
  checkout. Desplegar = push a `main` + botón "Pull ahora" en Plesk > el dominio >
  Git (no es 100% automático pese al texto "se despliega automáticamente"; hay que
  darle clic).
- Tarea programada `Verificar_pagos_PSE_ICA_Paipa` (Plesk > Tareas programadas) corre
  `cron_verificar_pagos.php` cada hora, PHP 8.1. Creada 2026-08-10. Cambiar a
  diariamente (madrugada) cuando pase a producción real con el banco.
- Pendiente (requiere al cliente/banco, no ejecutable por Claude): credenciales de
  producción de PlacetoPay, certificación/homologación con el banco, registrar la URL
  del webhook ante PlacetoPay.
