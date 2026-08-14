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

Login de prueba: usuario `administrador`, clave `AdminPruebaLocal2026` (reseteada
2026-08-14 en esta copia local; el hash guardado no coincidía con
`administrador2025`, que puede seguir siendo la real de producción — no se
comprobó ahí. El reseteo se hizo por el camino legítimo de la app,
`DAO_Usuario->guardar()` con tipodato `'clave'`, igual que usa
`_cambiarClave()`).

Usuario de prueba con rol "Externos - ICA": `pruebaica`, clave
`PruebaICA2026` (creado 2026-08-14, atado al contribuyente 30 — PRUEBA
Manrique Duran, doc 1052400237 — que tiene el establecimiento 43/DigitSoft y
ya trae datos de RIT).

## Reglas de negocio confirmadas por el cliente (no inventar, no asumir)

- La declaración de ICA es **una por contribuyente y año**, no por establecimiento —
  un contribuyente con varios locales declara una sola vez, agregando actividades por
  código CIIU.
- Contador/revisor fiscal firman con OTP a su correo (`ind_EmailContador` /
  `ind_EmailRevisor` en `ind_contribuyentes`), comparten una sola casilla en el
  formulario (se usa el del contador; el del revisor solo si el del contador está
  vacío).
- Es obligatorio firmar como contador/revisor **cuando el contribuyente tiene uno
  registrado**: si `ind_EmailContador` o `ind_EmailRevisor` tienen valor, esa firma se
  exige para presentar, sin importar tipo de persona ni ingresos (regla nueva desde
  2026-08-11, por instrucción explícita del cliente). **Reemplazó** a la regla anterior
  (jurídica siempre / natural sobre 3.500 UVT), que quedó derogada — si alguien la
  vuelve a mencionar, está desactualizado. `UVT_VALOR`/`UVT_ANIO` siguen definidos en
  `business/config.tributario.php` pero **hoy no los lee nadie**.
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

### Inyección SQL: el DAO concatena, NO parametriza (importante)

`business/DAO/class.DAO.php` arma todas sus consultas concatenando strings.
En `guardar()` la clave primaria va al `WHERE` **sin comillas siquiera**
(`" WHERE usu_Id = " . $valor`), y los valores de texto van dentro de un
literal `'...'` sin escapar. Consecuencias al escribir cualquier controlador
nuevo contra este DAO:

- **Todo id que venga del cliente hay que castearlo** (`(int) $_POST[...]`)
  antes de pasarlo al DAO. Sin eso hay inyección directa.
- **Todo texto libre del usuario hay que escaparlo** duplicando la comilla
  simple (`str_replace("'", "''", $v)`), que es como SQL Server escapa dentro
  de un literal. Para el campo de contraseña esto **no** altera el hash
  guardado: SQL Server parsea `''` como una sola comilla, así que
  `HASHBYTES` recibe el texto original y el login (que hace `sha1()` en PHP
  sobre el texto crudo) sigue coincidiendo — verificado con una contraseña
  que contiene comilla, cambiándola y volviendo a entrar.

Ambas cosas están aplicadas en `_cambiarClave()`. El resto de controladores
viejos **no** las aplica; es deuda conocida, no asumir que un endpoint
existente ya está protegido.

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

## Cifras en formato colombiano: usar SIEMPRE core/numeros.js

`core/numeros.js` (`NumerosCOP`) es la **única** definición real del manejo de
cifras. Antes estas funciones estaban copiadas en `icaWebRit.js`,
`icaWebConsultar.js` e `icaWebPresentar.js`, y eso costó dos bugs de
producción: se arreglaba una copia y las otras seguían rotas. Las copias
siguen existiendo como métodos (para no tocar ~200 llamadas) pero **delegan**;
no volver a poner lógica en ellas.

La distinción crítica es de dónde viene el dato:

| Origen | Función | Por qué |
|---|---|---|
| Input que ve el usuario | `NumerosCOP.aCifra()` | Formato es-CO: punto = miles, coma = decimal |
| Crudo de la base de datos | `NumerosCOP.deBaseDeDatos()` | SQL Server usa PUNTO decimal, igual que JS |
| BD → input de pantalla | `NumerosCOP.deBaseDeDatosAInput()` | Hace el puente correcto |

Confundir las dos primeras **multiplica el valor por 100** en cada pasada: fue
exactamente el bug de los "00000" al corregir una declaración.

Hay pruebas en `pruebas/numeros.test.js` (29 casos, incluidas regresiones de
los dos bugs). Se corren con `node pruebas/numeros.test.js`, sin dependencias.
**Correrlas antes de tocar cualquier cálculo.**

## Ningún AJAX puede fallar en silencio

`dist/menu.php` instala un `ajaxError` global para las **22 pantallas** que lo
incluyen. Antes solo existía en `declaraciones.ui.js`, que apenas cargan dos
pantallas: en el resto, una petición caída (500, timeout, JSON inválido)
dejaba la pantalla muda. Así fue como el botón "Liquidar" pareció muerto
durante meses.

La bandera `window.__erpRedAjax` evita el doble registro. Esto **no** reemplaza
el manejo propio de cada pantalla (los `success` que revisan `resp.ok`), solo
cubre que la petición ni siquiera se haya completado.

## Migraciones de base de datos: BD/migraciones/

Numeradas, re-ejecutables (con guardas `IF NOT EXISTS` / `IF COL_LENGTH`) y
auto-registradas en la tabla `conf_migraciones`. Para saber qué le falta a una
base:

```sql
SELECT mig_Nombre, mig_FechaAplicada FROM conf_migraciones ORDER BY mig_Nombre;
```

Existen porque el esquema local y el de producción se desincronizaron sin que
nadie lo notara (`fd_Rol`, `dec_Estado`, `ind_EmailContador`...), y con varios
municipios eso pasa de molestia a riesgo. Ver `BD/migraciones/README.md` para
el orden completo al crear una base nueva.

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

## Código de barras de recaudo bancario (GS1-128)

`business/class.codigoBarrasRecaudo.php` construye la referencia de recaudo
que va en el código de barras de los dos PDF. **Replica la estructura que ya
usa el sistema de PREDIAL de la misma alcaldía** (Laravel, en
`paipa.erpsoftsas.com` — otro stack, no este repo), leída de
`PrediosController.php` y de `facturaPDF_pai.blade.php`. Es el formato que el
banco ya acepta en ventanilla para el recibo de predial:

```
FNC1 + "415"  + EAN(13)
     + "8020" + numeroFactura(24, ceros a la izquierda)
FNC1 + "3900" + valor(14, ceros a la izquierda, sin decimales)
FNC1 + "96"   + fechaVencimiento(AAAAMMDD)   [opcional]
```

FNC1 se escribe como `chr(241)`: así lo entiende el Code128 de TCPDF (ver
`$fnc_a`/`$fnc_b` en `extensiones/tcpdf/tcpdf_barcodes_1d.php`, donde 241 se
mapea al carácter 102 = FNC1) y también la librería de predial. El primer
FNC1 marca el código como GS1-128; los demás cierran los AI de longitud
variable (8020 y 3900). El 415 no lleva separador porque es de longitud fija.

**Se activa solo con `MUNICIPIO_EAN_RECAUDO` definido** en
`config.municipio.php`. Sin esa constante el código sigue imprimiendo el
número de declaración pelado — que se ve bien pero **no es pagable en
banco**. Ese es el estado por defecto a propósito: no dar por funcional un
recaudo que el banco no ha certificado.

Pendiente antes de anunciarlo como funcional:
- El EAN de ICA es **distinto** al de predial (confirmado con el cliente el
  2026-08-12). Hay que pedírselo al banco.
- El formato de la fecha del AI 96 no está confirmado (96 no es un AI
  estándar de GS1, es de uso interno); se asume AAAAMMDD. Hoy el segmento se
  omite porque ICA todavía no captura la fecha límite.
- Falta la certificación del banco: imprimir un PDF de prueba y confirmar
  que el escáner de ventanilla lo lee.

Pruebas: `pruebas/codigoBarrasRecaudo.test.php` (15 casos) —
`php pruebas/codigoBarrasRecaudo.test.php`.

## Trampas de layout en los PDF (aprendidas a golpes, 2026-08-11)

Los dos generadores usan `SetAutoPageBreak(false)` y el formulario ocupa casi
toda la hoja (oficio, 215.9 × 330.2 mm), así que **TCPDF no avisa cuando algo
se sale**: simplemente se dibuja fuera del papel y no se ve. Cuatro cosas que
costaron encontrar:

1. **`GetY()` tras `writeHTML()` NO es el borde inferior de la tabla.** TCPDF
   deja el cursor más abajo, pasado el salto de bloque (medido: ~3mm). Anclar
   algo a `GetY()` creyendo que es el fin de la tabla lo deja flotando fuera
   del recuadro. Por eso el bloque de código de barras se dibuja ahora
   **completo a mano** (marco + rótulos + código con `Cell()`), controlando
   cada coordenada, en vez de superponerse a una celda HTML.
2. **`height="8"` en un `<td>` no reserva alto**: el parser HTML de TCPDF lo
   ignora y la fila termina midiendo lo que mida la celda más alta. Para
   reservar espacio hay que usar `<br>` (medido: cada uno aporta 3.1mm).
3. **`strtoupper()` rompe las tildes en UTF-8** ("Boyacá" → "BOYACá",
   "Alcaldía" → "ALCALDíA"). Usar siempre `mb_strtoupper($x, 'UTF-8')`.
   Crítico para multi-municipio: Bogotá, Nariño, Chocó, Córdoba, Atlántico,
   Bolívar, Caquetá, Quindío… casi todos los departamentos llevan tilde.
4. **Los PNG con canal alfa hacen que TCPDF escriba archivos temporales**
   (`ImagePngAlpha()` → dos ficheros en `K_PATH_CACHE`), y el PHP-FPM de
   Plesk no puede escribir ahí → `TCPDF ERROR: Unable to write file`. El
   sello `Sello_Firma.png` tenía alfa, así que **cualquier declaración
   firmada** habría fallado en producción. Se aplanó contra blanco (se
   imprime sobre celda blanca, se ve idéntico). **Los sellos de los demás
   municipios tienen que venir SIN canal alfa**; comprobar con el byte 25 del
   PNG (color type: 2 = RGB sin alfa, 6 = RGB+alfa).

Para verificar cambios de layout, no basta con mirar el PDF: conviene
imprimir las coordenadas (`$pdf->GetY()`, alto de página) y comprobar que el
contenido cierra por debajo de 330.2mm.

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
