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

**El EAN ya llegó** (2026-08-20): `7709998161047`, entregado por Javier de la
Alcaldía dentro de un formulario real de Paipa con su código impreso. Se
comprobó que nuestra cadena sale **idéntica carácter por carácter** a la suya.

Ya NO vive en la constante sino en `conf_parametros` (clave `RECAUDO_EAN`,
migración 009), porque lo pidió así: *«se debe configurar en una tabla para
poderlo cambiar en caso que sea necesario, cada entidad tiene su propio EAN»*.
`MUNICIPIO_EAN_RECAUDO` se conserva como respaldo para instalaciones sin la
migración.

Pendiente antes de anunciarlo como funcional:
- **El AI 96 sigue sin confirmar.** El ejemplo del banco lo trae
  (`(96)20260820`), pero 96 no es un AI estándar de GS1 —es de uso interno— y
  en su muestra coincide con el día en que se generó el PDF, así que admite dos
  lecturas: fecha de vencimiento o fecha de generación. Se dejó gobernado por
  el parámetro `RECAUDO_DIAS_VIGENCIA`: vacío omite el segmento (así se
  entrega), `0` imprime la fecha de hoy, `N` imprime hoy + N días. Cuando el
  banco responda es cambiar una fila, sin desplegar.
- Desde 2026-09-24 la casilla «fecha máxima de presentación» del ICA sale llena
  con la fecha límite (`ICA_FECHA_LIMITE`, ver "Vencimiento del ICA"), y esa es
  la fecha del (96) en la DECLARACIÓN. `RECAUDO_DIAS_VIGENCIA` sigue mandando en
  el recibo de pago de una vencida y en los de retención y autorretención.
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
   Para que un PNG aplanado quede DEBAJO del texto sin alfa: dibujarlo con
   `$pdf->SetAlpha(x, 'Multiply')` y volver a `SetAlpha(1, 'Normal')`. El blanco
   no pinta y lo oscuro gana (así va el sello de fondo de `declaracion.php`).

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

### Despliegue del 2026-09-09: lo que se aprendió a golpes

Producción llevaba **desde el 11 de agosto** sin actualizar, así que el pull trajo
86 commits de golpe. Y su base de datos **nunca había pasado por el sistema de
migraciones**: 18 tablas, sin `conf_migraciones`. Quedó en 32 tablas con las 31
migraciones aplicadas y registradas.

**El servidor es WINDOWS con IIS**, no Linux. Se perdió un rato mandando
`/bin/sh` y rutas `/opt/...` a las tareas programadas, que no existen ahí y
fallan sin decir nada. El motor es `.\MSSQLSERVER2022` — **local a la máquina**,
no expuesto a Internet, así que no hay forma de conectarse con un cliente SQL
desde fuera sin RDP.

**Plesk NO tiene consola SQL para SQL Server.** El "Web Admin" solo aparece en las
bases MySQL; para MS SQL solo hay volcados. Por eso existe
`BD/aplicar_migraciones.php`: se corre desde *Tareas programadas → Ejecutar un
script PHP*, con `--aplicar` como argumento y **PHP 8.3**. Usar **"Ejecutar
ahora"**, no "Aceptar" — no se quiere una tarea que aplique migraciones a diario.

**El "Pull ahora" de Plesk se quedó clavado** en un commit y no avanzaba por más
que se pulsara, ni con "Desplegar ahora". La salida: *Administrador de archivos →
`+` → **Importar archivo mediante URL***, apuntando al raw de GitHub
(`https://raw.githubusercontent.com/<owner>/<repo>/main/<ruta>`). Sobrescribe el
archivo y es fiable. Queda pendiente averiguar por qué el pull no avanza.

**PHP no puede escribir archivos en la carpeta de la aplicación** — misma familia
que la trampa de TCPDF con los temporales. Un script que intente dejar un informe
en disco falla en silencio.

**Y la trampa de siempre**: `globals.php` fuerza `display_errors=0`, así que un
fatal en un script de línea de comandos sale **mudo** y Plesk solo dice "se
completó con errores". Todo script de mantenimiento debe encender
`display_errors` para sí mismo.

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

## Firma del RIT (2026-08-19)

El RIT se firma con OTP, igual que las declaraciones, y estampa la casilla 30
del formulario impreso ("Contribuyente o Representante Legal", que hasta ahora
salía en blanco mientras la 31 ya traía la firma del funcionario).

```
BD/migraciones/008_firma_del_rit.sql   tabla ind_rit_firmas + columna codigo_Rol
business/class.ritFirma.php            hash del contenido firmado y firma vigente
microservicios/firmas/api.php          funcion 9 firmar, funcion 10 consultar
extensiones/ritActualizado.php         casilla 30 + marca de agua SIN FIRMAR
```

**Por qué el hash y no un simple "fulano firmó"**: una declaración presentada ya
no cambia, pero el RIT está hecho para actualizarse (el formulario se llama "de
inscripción **Y/O NOVEDADES**"). Cada firma guarda el SHA-256 del contenido
firmado; al imprimir se recalcula y solo se estampa si coincide. Cualquier
novedad invalida la firma **sola**, sin que nadie tenga que acordarse de
invalidarla. El hash cubre exactamente lo que el formulario imprime
(contribuyente + actividades + establecimientos, incluido el cese): de más
invalidaría por cambios que el papel no muestra, de menos dejaría pasar cambios
visibles sin volver a firmar. `RitFirma::VERSION` permite invalidar a propósito
todas las firmas viejas si algún día cambia *qué* se firma.

El OTP del RIT usa `codigo_Rol = 'rit'`, distinto de `'declarante'`. Sin eso, un
código pedido para firmar una declaración serviría para firmar el RIT y al
revés: ambos usan `id_establecimiento = 0`.

### Pendiente conocido: la firma de declaración es falsificable

`_firmarDeclaracion()` (función 7 de `microservicios/firmas/api.php`) **no
vuelve a validar el código OTP**: da por hecho que el navegador llamó antes a la
función 2. Quien haga un POST directo a la función 7 registra una firma sin
haber recibido ningún correo. Y no es teórico: se encontró
`codigos_verificacion` con **0 filas** y `firmas_declaraciones` con **5 firmas**
— es decir que se firmó sin que el OTP hubiera funcionado nunca.

La firma del RIT (función 9) **no repite ese diseño**: valida y consume el
código dentro de la misma llamada que registra la firma. Arreglar la función 7
igual exige decidir qué pasa con "refirmar", que hoy entra por otra puerta y sin
código; por eso quedó anotado y no cambiado.

## Trampas encontradas el 2026-08-19 (todas costaron un rato)

**1. `is(":checked")` sobre un elemento que no existe devuelve `false`, no
`undefined`.** Escrito como `campo: $("#x").is(":checked") ? 1 : 0`, eso manda
un `0` sólido y **apaga la columna en silencio en cada guardado**. Pasó con
`est_Exento` / `est_Excento_avisos`: se quitaron las casillas del formulario y
se olvidó quitar el envío. Los campos de texto **no** tienen el problema:
`.val()` da `undefined`, jQuery lo omite del POST y el controlador —que recorre
`$_POST` con `foreach`— ni lo toca. Usar `flagCasilla()` de
`core/establecimientos.js`.

**2. `est_Pais` / `est_Departamento` / `est_Ciudad` son `VARCHAR(5)`.** No caben
"Colombia" (8) ni "Boyacá" (6). Mandar el nombre produce *"String or binary data
would be truncated"*, la excepción no se captura y el endpoint contesta **500
con el cuerpo vacío** → la pantalla solo dice "error de conexión". Por eso las
12 filas tienen `'1'`: la ubicación del establecimiento **nunca se ha guardado**.
Nadie las lee (solo el DAO las declara), así que
`_descartarUbicacion()` las descarta y el formulario las muestra fijas desde el
config. Si algún día hay que almacenarlas de verdad, **ensanchar las tres
columnas en una migración antes** de volver a enviarlas.

**3. `est_Codigo` es `INT` y el input era texto libre.** Una letra bastaba para
el mismo 500 vacío. Ahora `_validarCodigo()` lo rechaza con mensaje y el input
lleva `inputmode="numeric"`.

**4. `codigos_verificacion.codigo_Rol` no existía.** `api.php` la usa en todos
sus INSERT y SELECT, así que **ningún OTP llegó nunca a guardarse**. La crea la
migración 008. Al desplegar, verificar que producción también la tenga.

**5. Más PNG con canal alfa en los PDF.** Se había aplanado `Sello_Firma.png`
pero quedaban `firma_rit.png` y `logopazysalvo.png`, los dos impresos en el RIT:
en Plesk habrían reventado con *"Unable to write file"*. Ya están aplanados
(los originales quedaron como `*_ORIGINAL_con_alfa.png`). `escudo-paipa.png`
**no** se aplanó porque la web lo usa sobre fondos de color: se generó
`escudo-paipa-pdf.png` y los PDF la toman por `MUNICIPIO_LOGO_PDF`.
**Antes de dar por bueno un PDF nuevo, comprobar el byte 25 de cada PNG que
imprima** (2 = RGB sin alfa, 6 = RGB+alfa).

## Un pago solo existe sobre una declaración PRESENTADA

Ni el recaudo bancario ni PSE pueden marcar como pagada una declaración que no
esté en `dec_Estado = 2`. Dejarlo pasar producía un estado imposible —pagada
pero sin presentar— que rompe "Corregir": la pantalla la pintaba "Pagada"
porque miraba `dec_Pagado`, pero corregir exige `dec_Estado = 2` y la rechazaba,
así que el contribuyente quedaba con una declaración cerrada que no podía tocar.

- **Recaudo** (`class.recaudo.php`): esos renglones van al informe de
  excepciones ("Sin presentar") y **no se aplican**. El código de barras de
  recaudo solo se imprime en declaraciones presentadas, así que una referencia
  contra una que no lo está no pudo salir de un recibo nuestro.
- **PSE** (`extensiones/pse/crearSesion.php`): rechaza el pago de un borrador.
  Además el monto de un borrador todavía puede cambiar.
- **Pantalla** (`claveEstado()` en `core/declaraciones.ui.js`): "Pagada" exige
  las dos condiciones, no solo `dec_Pagado`.

## Trampa: hay TRES config.municipio.php, y el que manda no está en el host

Al arreglar el campo "Departamento" del formulario de establecimientos se
descubrió que editarlo en el host no cambiaba nada en Docker. La razón:

| Archivo | Dónde vive | ¿Manda? |
|---|---|---|
| `App/Firma_digital/config.municipio.php` | host (el "real" de producción) | no, en Docker |
| `App/Firma_digital/erpsoftsas/config.municipio.php` | host, gitignored | no |
| `/var/www/html/config.municipio.php` | **dentro del contenedor** | **sí** |

El contenedor monta **solo** `.../erpsoftsas -> /var/www/html/erpsoftsas`. El
`config.municipio.php` que queda un nivel arriba es una copia horneada en la
imagen, invisible desde el host — y como los buscadores de config resuelven
primero el nivel de arriba (ver la sección del "gotcha crítico"), es esa copia
la que gana.

Consecuencia práctica: **una constante nueva hay que agregarla en los tres
sitios**, o al menos en el del contenedor si se quiere ver el efecto en local.
Así se perdió un rato con `MUNICIPIO_DEPARTAMENTO`: estaba en los dos archivos
del host y el campo seguía saliendo vacío.

Para saber cuál se cargó:

```php
foreach (get_included_files() as $f)
    if (strpos($f, 'config.municipio') !== false) echo $f;
```

## El menú ya no destella (2026-08-19)

`core/menu.js` pintaba el menú completo y lo recortaba **300 ms después**
(`setTimeout`), así que en cada cambio de pantalla el usuario alcanzaba a ver
los módulos que no le tocan —Administración ICA, Configuración—. Lo reportó el
cliente.

Ahora el menú **nace oculto** por CSS (`.menu-cargando`) y `menu.js` solo
revela lo permitido. La espera de 300 ms se quitó: los permisos los guarda
`login.js` en `localStorage` al iniciar sesión, así que ya están cuando carga
cualquier pantalla interna.

Dos guardas, porque un menú oculto que nunca se destapa sería peor que el
destello: `revelarMenu()` va en un `finally`, y si no hay permisos en
`localStorage` se muestra solo "Inicio" en vez de reventar.

## La firma es UNA sola ventana (2026-08-19)

El modal de firma vive en `core/declaraciones.ui.js` (módulo `FirmaOTP`) y el
HTML con sus ids (`#modal-FirmaDigital`, `#otpCodigo`, `#btnValidarOTP`…) está
repetido en `icaWebConsultar.php`, `icaWebPresentar.php` y `icaWebRit.php`.

`FirmaOTP.abrir(...)` firma declaraciones y `FirmaOTP.abrirRit(...)` firma el
RIT: mismo modal, distinto `_modo`. Cambian dos cosas nada más — el rol con
que se pide el código (`'rit'` tiene su propio cajón en `codigos_verificacion`,
para que un código de declaración no sirva para firmar el RIT) y la función que
registra la firma (9 en vez de 7).

Al tocar ese flujo hay que acordarse de las **tres** pantallas: comparten ids,
así que un cambio en el HTML del modal debe replicarse en las tres.

## Módulos RETEICA y AUTORRETEICA (2026-09-08)

Retención de ICA (**mensual**, períodos 1–12) y autorretención (**bimestral**,
1–6). Hasta esta fecha existían solo cuatro pantallas con el cartel de "página
en construcción" y sus entradas de menú.

```
BD/migraciones/030_reteica_y_autorreteica.sql   4 tablas + catálogo de renglones
business/class.retenciones.php                  el motor: TODO el ciclo de vida
business/controller/class.reteica.php           qué distingue a retención
business/controller/class.autorreteica.php      qué distingue a autorretención
core/retenciones.js                             el motor de las 4 pantallas
core/{reteica,autoretencion}{Presentar,Consultar}.js   solo el CONFIG de cada una
dist/  las mismas cuatro, ya con formulario real
```

**Un motor y dos configuraciones, no dos copias.** Backend y frontend comparten
el ciclo completo (crear, capturar, liquidar, presentar, corregir) y cada módulo
solo declara sus tablas, su prefijo de columnas y su periodicidad. Se hizo así
por lo que ya costó en este repo la duplicación de `declaracion.php` /
`liquidacion.php` y de las funciones de cifras en tres JS.

**No usan el DAO.** Todo va por `consultar()` con parámetros. El DAO concatena
strings y mete la PK en el `WHERE` sin comillas; estos módulos no heredan eso.

**Las fórmulas viven en la base**, en `ind_renglones_retencion`, no en PHP. El
motor aplica solo los renglones que tienen fórmula; los que están en `NULL`
salen en cero y la pantalla los marca *pendiente*. Eso permitió entregar los
módulos con cinco casillas de autorretención sin resolver y **cerrarlas después
sin tocar una línea de PHP**: la migración 031 son cinco `UPDATE`.

**El cálculo quedó confirmado el 2026-09-09** (migración 031). La casilla 15 es
`actividades + energía`, sumada UNA vez; el Excel la sumaba dos y el total a
pagar salía 12.550.000 en vez de 6.800.000. El sistema reproduce hoy el ejemplo
del cliente **cifra por cifra** (15 → 5.490.000, 16 → 824.000, 17 → 6.314.000,
19 → 6.250.000, 23 → 6.800.000).

**El redondeo es a MILES y FILA POR FILA**, en los dos módulos. No hizo falta
preguntarlo: lo dicen las propias fórmulas de los formularios del cliente
(`MROUND((I16*K16/1000),1000)` en RETEICA, `MROUND((M19*J19/1000),1000)` y
`MROUND(M24*15%,1000)` en AUTORRETEICA). El ICA **no se toca**: redondea una sola
vez sobre el total y es otro formulario.

**El catálogo de actividades está por año y hoy solo tiene 2025**
(`acc_Anio`, 69 filas). Pedir literalmente el año de la declaración devolvería
un desplegable vacío, así que se toma el año vigente más reciente que no pase
del declarado. Ojo también con los nombres: son `acc_*` (`acc_Id`, `acc_Codigo`,
`acc_Nombre`, `acc_Tarifa`), no `act_*`.

**`ind_contribuyentes` no tiene columna de razón social.** El nombre sale de los
cuatro campos de persona natural, y en una persona jurídica la razón social vive
en `ind_PrimerNombre`. Es la misma trampa que dejaba el nombre en blanco en el
RIT de las jurídicas.

### Firma y PDF (2026-09-08, mismo día)

```
extensiones/pdfRetenciones.php   utilidades comunes de los dos formularios
extensiones/reteica.php          el formulario mensual
extensiones/autorreteica.php     el formulario bimestral
```

**Sin firma no se presenta**, igual que en el ICA: la del declarante siempre, y
la del contador o revisor cuando el contribuyente tiene uno registrado. El
motivo del rechazo vuelve en `datos.falta` (`'declarante'` / `'contador'`) para
que la pantalla pueda encadenar el OTP en vez de mostrar un error suelto.

**`firmas_declaraciones` ahora distingue el módulo**, y no es cosmético: los
tres formularios reparten números de series distintas, así que la retención
2026000001 y la declaración de ICA 2026000001 existen a la vez. Comprobado en
la prueba local: los dos módulos emitieron su propio 2026000001 el mismo día.

En `microservicios/firmas/api.php`, `fd_Rol` sigue siendo el rol (`declarante` /
`contador`) y el módulo va en `fd_Modulo`. **El rol del código OTP sí lleva el
módulo dentro** (`ret:declarante`, `aut:contador`, ver `_rolCodigo()`), por lo
mismo que el RIT tiene el suyo desde agosto: un código pedido para firmar el ICA
no debe servir para firmar una retención. El prefijo va abreviado porque
`codigo_Rol` es `varchar(20)`.

**Los PDF son carta (215.9 × 279.4), no oficio.** El de ICA usa oficio porque
tiene 38 casillas; estos cierran en 176mm y 240mm, así que oficio desperdiciaría
media hoja. Los dos archivos aceptan `?medir=1`, que en vez de generar el PDF
imprime dónde cerró el formulario — `SetAutoPageBreak` está en `false` y TCPDF
no avisa si algo se sale del papel.

Dos detalles del layout que costaron una vuelta y ya están resueltos en
`pdfret_textoVertical()`: los renglones del rótulo rotado se apilan **hacia la
derecha**, así que uno que se parta en tres invade la primera casilla de la
tabla; y contar renglones con `GetStringWidth` no basta, porque `MultiCell`
parte por palabras — hay que usar `getNumLines()` **y** exigir que la palabra
más larga quepa entera, o sale "A. CONTRIBUYEN / TE".

**El código de barras escaneable exige dos condiciones**: que esté presentada y
que haya algo que pagar. Un GS1-128 por $0 no lo puede cobrar el cajero, y en
autorretención es hoy el caso normal porque la casilla 23 sigue sin fórmula.

### El modal de firma se genera desde JavaScript

En el ICA el HTML del modal de OTP está **copiado en tres pantallas**, y este
mismo archivo advierte que un cambio hay que replicarlo en las tres. Con los dos
módulos nuevos serían cinco copias, así que `FirmaRetencion` (dentro de
`core/retenciones.js`) lo construye una sola vez y lo cuelga del `body`. Misma
apariencia, un solo sitio donde mantenerlo. Sus ids llevan prefijo `ret` para no
chocar si algún día conviven con los del ICA en una pantalla.

"Presentar" es **un solo botón de principio a fin**: guarda, intenta presentar y,
si el backend responde `datos.falta`, abre el modal para esa firma y reintenta al
terminar. No es un bucle infinito — cada vuelta ocurre solo tras una firma
registrada, y solo hay dos firmas posibles.

**Probado en vivo el 2026-09-09**, mandando los mismos parámetros que manda la
pantalla, en los dos módulos: pedir código → se guarda con `codigo_Rol` =
`ret:declarante` / `aut:contador` → firmar → presentar. Y la prueba que importa:
un código pedido para el **ICA** (`codigo_Rol = 'declarante'`) se **rechaza** al
intentar firmar con él una retención, y la declaración sigue sin poder
presentarse. Esa separación es la razón de ser del prefijo.

**Ojo con los correos al probar**: pedir el código dispara un envío SMTP real, y
el contribuyente de prueba 30 tiene registrados los correos del representante y
del contador de personas reales. Para la prueba se apuntaron temporalmente a
`@pruebalocal.invalid` (dominio reservado por RFC 2606, no entrega a nadie) y se
restauraron después, verificando que quedaran idénticos.

### Las pantallas usan el diseño del ICA, no uno propio (2026-09-09)

Nacieron con markup genérico de Bootstrap y se alinearon con `icaWebConsultar`,
porque el cliente compara las pantallas entre sí y dos lenguajes visuales en el
mismo sistema se leen como descuido. Lo que se adoptó:

| | Se usa |
|---|---|
| Estructura | `main-container` > `card-box mb-30` > `pd-20` (con `h4`) + `pb-20 px-3` |
| Filtros | `.filtros-declaraciones` / `.campo`, con `.conteo` a la derecha, **sin botón** (se aplican al cambiar, como el ICA) |
| Tablas | `table-bordered table-striped table-sm`, cabecera `#e9ecef` |
| Estados | `.chip-estado.est-borrador\|firmada\|presentada\|pagada` |
| Vacíos | `.estado-vacio` con icono, título y texto |
| Acciones | botones `btn-sm` **solo icono** con `title`, mismos colores que el ICA |

Todas esas clases están definidas en `dist/menu.php`, que estas pantallas ya
incluyen: no hay CSS nuevo que mantener. El modal de firma toma
`var(--erp-primario)` en vez de un teal escrito a mano, para que siga al
municipio.

### Establecimientos vive en el primer nivel del menú — y ya se movió antes

**Ojo antes de volver a tocarlo**, porque esto tiene historia:

- **2026-08** (punto 5 de la lista): se sacó a primer nivel.
- **2026-08-18**: el cliente pidió devolverlo dentro de Industria y Comercio.
- **2026-09-09**: vuelve a primer nivel.

El argumento de ahora **no es el de agosto**. En agosto el único módulo era el
ICA, así que colgar Establecimientos de él era razonable. Hoy los mismos
establecimientos los usan **tres** módulos —ICA, Retención y Autorretención— y
tenerlos dentro de uno sugiere que pertenecen solo a ese. Van junto al RIT, que
es el otro dato transversal del contribuyente.

Sigue con el permiso **1640**: mover el elemento no cambia quién entra. Como
elemento de primer nivel, la clase `menu_1640` va en el propio `<li>`, igual que
`menu_1641` en el RIT — `menu.js` muestra `.menu_<boton>` y su `li.dropdown`
contenedor.

### Lo que falta

Una pregunta al cliente:

- **La exención de avisos y tableros.** El formulario cobra el 15% a todos, y así
  quedó. Pero `ind_SinAvisosTableros` existe y en el ICA **sí** exime (migración
  021). Cobrarle a un exento y eximir a quien no lo es son los dos errores.

**La casilla 20 quedó resuelta** (2026-09-09): el cliente confirmó que la escribe
el contribuyente. Ya estaba así —manual, como en su Excel—, así que no hubo que
cambiar nada. Se anota para que nadie lo vuelva a abrir.

Y lo técnico:

- Probar el flujo desde el NAVEGADOR. El backend está verificado de punta a
  punta, pero el modal en pantalla no se ha visto funcionando. (Al 2026-09-23 el
  navegador interno SÍ llega a `localhost:8081` con la entrada `erpsoftsas-local`
  de `.claude/launch.json`; lo que falta es una sesión, que no se inicia
  escribiendo contraseñas en el navegador.)
- Cargar el catálogo de actividades de 2026 (hoy solo hay 2025).

## El administrador gestiona contribuyentes: "contribuyente activo" (2026-09-23)

El rol 1 **no es un contribuyente más**: no tiene RIT propio. Su entrada es
`dist/contribuyentes.php` → botón **Gestionar** → abre directo el RIT del elegido
(el lanzador con los 5 módulos se retiró el 2026-09-24: repetía el menú). El
elegido queda en `localStorage.id_Contribuyente` (+ `contribActivoNombre` /
`contribActivoDoc`), que es la misma llave de donde ya leían todas las pantallas
del contribuyente: **no se duplicó ninguna pantalla**.

- `dist/menu.php`: barra "Gestionando a: X · Cambiar · Salir" (solo rol 1) y
  guardia: sin contribuyente activo, las 8 pantallas de módulo redirigen a
  Contribuyentes **antes** de cargar sus scripts (con swal se encimaba con los
  popups propios del RIT). El aviso lo muestra Contribuyentes vía sessionStorage.
- **Menú del administrador (2026-09-24, aprobado por Diego):** Inicio ·
  **Contribuyentes** · [bloque "Gestionando a X": RIT · Establecimientos · Industria
  y Comercio · Retención · Autorretención] · Recaudo ICA · **Parámetros ICA**
  (Municipio y bancos, Actividades, Conceptos, Grupos tarifarios) · Impuesto Predial
  · **Usuarios y roles**. Es el orden ESTÁTICO del HTML; `ContribActivo.pintarMenu()`
  muestra el bloque (`#MGestionando` + `.en-gestion`) solo con alguien elegido, y
  `menu.js` ya no oculta `#MRIT`. Para el rol 4 el orden visible no cambió (solo
  tiene 1640/1641/1643/1644); rol 3 solo 1035; rol 2 no tiene permisos. Los ids de
  siempre se conservan (`#MICAAlcaldia` es Parámetros ICA, `#MConfig` es Usuarios y
  roles); desapareció el nivel "Datos Básicos".
- `dist/dashboard.php` (Accesos rápidos) lee el menú YA filtrado: corre en `$(…)`
  (jQuery 3: después de los ready de menu.js/ContribActivo), descarta lo oculto y
  arma tarjeta directa para los ítems sin submenú (Contribuyentes, RIT, Recaudo…).
  Antes tomaba todos los `<li>` y al rol 4 le ofrecía tarjetas de la Alcaldía.
- `core/retenciones.js` (`pedir`) y `core/establecimientos.js`
  (`getEstablecimientos`) mandan el contribuyente activo. **Ojo**: el servidor
  deja a los roles 1/2 filtrar por el id que llegue y SIN id les devuelve TODO;
  cualquier listado nuevo del contribuyente debe mandar el activo o el admin verá
  el padrón entero bajo la barra de un solo contribuyente (bug real encontrado en
  establecimientos en la revisión).

### Firma cuando la Alcaldía presenta por otro

El OTP del **declarante** ya no sale del usuario de la sesión sino del **dueño de
la declaración** (`_datosDeclarante`, mismo criterio por módulo que
`_destinatarioContador`), y va al `ind_Email_representante` de ese contribuyente
(`_correoRepresentante`). Nunca al correo del funcionario. El respaldo "correo de
la cuenta" solo aplica si quien firma ES el propio contribuyente.

`_puedeFirmar` exige que la declaración sea del contribuyente de la sesión salvo
roles 1/2, en funciones 1 y 7 y para **declarante y contador** (antes el contador
no tenía chequeo: cualquiera podía hacer llegar códigos al contador de otro).
`fd_NombreUsuario` del declarante guarda al representante; `fd_IdUsuario` sigue
siendo el usuario real (traza de quién operó). RIT: `_ritPermitido` ya resolvía
roles 1/2; fuera de ellos ahora usa SIEMPRE el propio (antes rechazaba si el id
del navegador no coincidía, y con documentos repetidos bloqueaba al dueño).

Probado sin enviar correos con reflexión sobre los helpers privados (api.php solo
corre en POST, se puede incluir desde CLI).

### Pestañas: cada una recuerda con quién abrió (`ContribActivo`, menu.php)

localStorage es uno para todas las pestañas del navegador. Si en otra pestaña se
elige otro contribuyente, la vieja seguía mostrando datos del anterior y guardando
sobre el nuevo (varias pantallas leen el id en cada petición). **No se movió a
`$_SESSION`**, como se había anotado: la sesión PHP es la misma cookie para todas
las pestañas, así que tenía el mismo problema; y la mayoría de las pantallas de
`dist/` ni siquiera abren sesión antes de pintar (menu.php va dentro del body y
PHP ≥ 7.2 no deja arrancarla con la salida ya enviada).

Lo que se hizo: `ContribActivo` guarda al cargar con qué contribuyente **y usuario**
abrió la pestaña y revisa en `storage`, `focus`, `pageshow` y `visibilitychange`:

- Cambió el contribuyente (mismo usuario) en una pantalla de módulo → aviso que
  bloquea la página (`inert` a todo lo demás): **Seguir con X** (lo reactiva y la
  que queda detenida es la otra pestaña) o **Cambiar a Y** / **Ir a Contribuyentes**.
  Fuera de los módulos solo se repinta la barra.
- Cambió el usuario (otra cuenta o `localStorage.clear()` del cierre de sesión) →
  solo **Recargar**. Aplica a **todos los roles**: también protege al contador que
  abre dos clientes en pestañas del mismo navegador.
- Recargar/cambiar usa `location.replace(location.pathname)`: un `?id=` de una
  declaración del anterior no se reabre bajo el nuevo.
- `contribuyentes.js` ya no escribe localStorage directo: usa
  `ContribActivo.fijar({id, doc, nombre})`, que escribe el id de ÚLTIMO (las otras
  pestañas reaccionan a él y para entonces nombre/doc ya son los nuevos).

### Contribuyentes busca en el servidor (función 5)

La pantalla ya no descarga el padrón (función 3): buscador propio → función 5,
máximo 20 filas (`TOP 21` para saber si hay más → `{filas, hayMas}`; cero
resultados es `ok = 1`). Sin texto trae los 20 más recientes. Cada palabra debe
aparecer en el documento o en algún nombre; `1.052.400.237` y `NIT-DV` se buscan
sin puntos ni DV; `%`, `_` y `[` son literales. Compara con
`COLLATE Latin1_General_CI_AI`: `Modern_Spanish` (la de la base) distingue tildes
y hasta en AI trata la ñ como otra letra ("avendano" no hallaba "Avendaño").
DataTables quedó sin buscador/paginación propios. Probado contra la BD local.
Las acciones de cada fila son `.acc-card` (Gestionar / Editar / Activar-Inactivar,
con el nombre de la ACCIÓN), las mismas tarjetas de ICA y retención. No usar
`btn-sm` con texto en una tabla: `dist/menu.php` vuelve todo `.data-table .btn-sm`
un cuadrado de 32px y el texto sale recortado (así se veía "Gestionar").

### Retro 2026-09-24 (pruebas del dueño en local)

- **El admin entra sin contribuyente activo.** `login.js` guardaba
  `usu_idContibuyente` también para el rol 1, y el documento del usuario
  `administrador` (21321) coincide con el contribuyente 2: quedaba "Gestionando a:
  Contribuyente" sin haber elegido a nadie. Rol 1 → `''`, y el login borra el
  nombre/doc de una gestión anterior.
- **Usuarios daba 500** (y el usuario con documento repetido no podía iniciar
  sesión): la subconsulta `usu_idContibuyente` de `DAO_Usuario` devolvía 2 filas
  con el documento 1052400234 duplicado en el padrón (error 512). Va con `TOP 1 …
  ORDER BY ind_Id`, mismo criterio que `_contribuyenteDeLaSesion`. Roles no fallaba.
- **PDF de retención/autorretención:** la sección A es UNA tabla con la banda en
  una celda `rowspan` (patrón de `declaracion.php`); con una tabla por fila las
  líneas cruzaban el rótulo. El `rowspan` de la tabla de actividades cuenta la fila
  "sin actividades" (`max(1, count)`): sin eso el TOTAL se corría y el rótulo se
  salía. Encabezado: todas las líneas a 11 como el ICA (antes 11/8).

### Segunda ronda 2026-09-24

- **Establecimientos (todos)** para la Alcaldía, bajo Contribuyentes
  (`dist/establecimientosTodos.php` + `core/establecimientosTodos.js`, permiso 1639,
  `menu.validarIngreso(1639,13)`). Es un DIRECTORIO: busca en el servidor
  (`class.establecimientos.php` función 22, TOP 20, mismo criterio que la búsqueda
  de contribuyentes) y "Gestionar" deja activo al dueño y abre sus establecimientos.
  No se edita ahí a propósito: el formulario toma el dueño del contribuyente activo.
  No reemplaza al "Establecimientos" del bloque del contribuyente gestionado.
- **Contraseña de edición de "Municipio y bancos"** (la definió el dueño; no se
  escribe en el repositorio). La exige `class.configuracion.php::run()` para las funciones que guardan
  (2 y 4); 5 desbloquea (15 min en la sesión), 6 consulta, 7 bloquea; 5 intentos
  fallidos → 5 min de espera. Solo el hash en `CLAVE_EDICION_HASH`; un municipio lo
  cambia con `MUNICIPIO_CLAVE_PARAMETROS_HASH` en su config.
- **Recibo de pago** (`extensiones/reciboPago.php?modulo=ICA|RETEICA|
  AUTORRETEICA&id=`; nació como "desprendible", el cliente lo nombró así): uno para
  los tres; conceptos de lo guardado (ICA: mismo mapeo que `declaracion.php`, total
  en `dec_ValorConcepto20`; retención 14–17 y autorretención 15–23 del catálogo).
  Solo presentada, sin pagar y con valor. El total de la declaración sale como
  SUBTOTAL, debajo "INTERESES DE MORA AL <pague antes de>" y TOTAL A PAGAR (lo que
  lleva el código de barras). Los intereses van en $0: el cálculo lo dejó
  PENDIENTE el cliente. Sin establecimiento (pedido del cliente); NIT sí.
  Papelería municipal NO va. LIQUIDADOR: si lo imprime un funcionario (rol 1/2) va
  su nombre (`conf_usuarios`); si lo genera el contribuyente, ninguno. Tarjeta
  "Recibo de pago" (50 px, parte en dos renglones como las demás).
- **Vencimiento del ICA** (audio + respuestas del cliente, 2026-09-24): hasta la
  fecha límite se paga con la PROPIA declaración (su código de barras) o por PSE;
  vencida, la declaración sale SIN código de barras (leyenda "DECLARACIÓN VENCIDA EL
  … Para pagarla, genere el recibo de pago") y se paga con el recibo. La fecha es
  `ICA_FECHA_LIMITE` (DD/MM, migración **034**, 30/04), editable en Municipio y
  bancos detrás de su contraseña; la regla vive en `business/class.vencimientoICA.php`.
  **Cae en el MISMO año de `dec_AnioDeclaracion`**, no en el siguiente: ese año lo
  pone `_agregarDeclaracion` con `date('Y')` (el cliente quitó el selector de año
  gravable). La casilla FECHA MÁXIMA PRESENT. ya no sale en blanco y el (96) de la
  declaración es esa fecha. Recibo del ICA: sin vencer, vale hasta la fecha
  límite; vencido (y retención/autorretención, sin fecha aún), "Días de vigencia
  del recibo" (vacío = el mismo día). Una pagada conserva su código (comprobante).
  `liquidacion.php` (sin enlace en la interfaz, pero abre por URL) sigue la misma
  regla, para que no quede una puerta a un código pagable de una vencida.
- **Sello de la declaración ICA DE FONDO** ("se ve horrible", cliente): el sello
  grande (17 mm, dibujado encima de la fila de firmas) tapaba con su fondo blanco
  el título de la casilla, el nombre y la fecha. Ahora va con `Multiply` al 60 %,
  1,5 mm más abajo para no pisar el título, y el sello chico de la casilla se
  cambió por `Sello_Firma_espacio.png` (8×8 blanco, sin alfa), que conserva el alto
  de la fila sin que aparezca un segundo sello en miniatura dentro del grande.
- **Menú en ventanas < 1200 px**: lo abre el ESCUDO (`#btnMenu`), el mismo botón
  que en escritorio lo oculta. El ☰ que hubo se quitó ("se ve tan amateur", cliente
  2026-09-24). El handler corta la propagación del clic y del `touchstart`: la
  plantilla cierra el menú con cualquier toque fuera de él o de un `.menu-icon`, y
  el escudo no es ninguno. Bajo 1025 px la plantilla dejaba el bloque izquierdo del
  encabezado en 25 % y el título se montaba sobre el nombre: ahora es flexible.
- `_contribuyenteDeLaSesion` de retenciones, declaraciones ICA y establecimientos
  también con `TOP 1 … ORDER BY c.ind_Id` (el mismo contribuyente que el login).
- Inicio y Recaudo ya no cargan dos veces `menu.js`/`Permisos.js`; el título de la
  pestaña del RIT decía "Establecimientos".

### Revisión del cliente 2026-09-25 ("REVISIÓN ICA WEB"), primera entrega

- **"guardo, cierro, vuelvo a editar y me sale error"**: `EditarDeclaracion.abrir`
  (declaraciones.ui.js) llenaba número, año y período y DESPUÉS llamaba a
  `limpiarFormularioDeclaracion()`, que borra justo esos tres (regresión de
  f8ce25c, 2026-08-29). La declaración quedaba abierta sin id: "Guardar"
  (función 6) y el recálculo de renglones (función 7) respondían "Id de
  declaración requerido", y la pantalla lo mostraba como "No se pudieron guardar
  las actividades". Pasaba también con las correcciones (Corregir abre por ahí).
  Ahora se limpia primero y la casilla lleva el NÚMERO (como al crear); el
  servidor resuelve número o id. Los dos avisos muestran `arr.mensaje`.
- "Guardar" cierra la ventana y vuelve al listado (se refresca en
  `hidden.bs.modal`); en Consultar, donde solo se editan correcciones, el aviso
  dice que quedó en Presentar.
- Columna "Valor a pagar" (antes "Valor Pago", que era lo pagado): casilla 38,
  `dec_ValorConcepto20`, en Presentar y Consultar.
- Textos del cliente: "Liquidación calculada" → "Estas son las cifras calculadas.
  Para conservarlas, pulse "Guardar"."; y "Notificación electrónica" en el RIT
  (arts. 566 y 566-1 ET, art. 7 Decreto 009 de 2017), también en las dos copias
  del formulario de establecimiento que traen Presentar y Consultar.
- `establecimientos.js` e `icaWebRit.js` conservan copias viejas del flujo de
  declaración (datos simulados, avisos falsos) que ya no se alcanzan: no se tocaron.

### Revisión 2026-09-25, segunda entrega (con las respuestas del cliente)

- **Cierre de establecimientos** (funciones 23 y 24 de `class.establecimientos.php`):
  cierra SOLO la Alcaldía (roles 1 y 2), desde "Estado del registro: Cierre de
  establecimiento", con fecha de cese (hoy o anterior) y al menos un soporte tipo
  `cese` (cámara de comercio o acta de liquidación; "con uno basta"). Queda
  `est_Activo = 0`, `est_Opcion_uso = 3`, `est_Fecha_cierre`. Reabre SOLO el
  administrador (rol 1, "el director de impuestos") con justificación ≥ 10
  caracteres. La historia va en `ind_establecimiento_novedades` (migración **035**).
  Se quitaron "Retirar" y "Reactivar"; la función 4 contesta con el camino nuevo;
  la función 2 no edita un cerrado, no acepta `est_Activo` ni la opción 3; la 1 no
  crea cerrados; `_filtrarCese` descarta los datos de cierre para todos; anexos no
  deja subir ni quitar archivos de un cerrado (el soporte es la prueba). En
  pantalla: el cerrado se abre solo para consulta ("Ver"), "Reabrir" solo rol 1,
  la opción 3 no se ofrece al contribuyente ni al crear.
- **Cerrar un local no toca la declaración**: las actividades son del contribuyente
  (migraciones 005/007), así que el año del cierre se declara igual (respuesta 6).
- **RIT obligatorio** (`_faltantesRIT` en `class.contribuyentes.php`, y
  `validarObligatoriosRIT` en `icaWebRit.js`): no se guarda sin tipo de persona,
  primer nombre o razón social, primer apellido (solo persona natural), dirección,
  departamento y municipio, teléfono, correo de notificación, fecha de inicio de
  actividades, cédula/nombre/correo/celular del representante, un régimen
  (ordinario/simple/especial) y responsable o no de IVA, más los tres documentos
  de `RitFirma::DOCUMENTOS_OBLIGATORIOS` (antes solo se exigían al firmar). Para
  todos los roles y para inscripción y actualización. Contador y revisor quedan
  opcionales. Identidad (tipo y número) no se edita en el RIT: si falta, lo
  corrige la Alcaldía en Contribuyentes.
- **DV**: el cliente lo pidió obligatorio, pero en el RIT es de solo lectura y
  la columna no admite NULL (los que nunca lo tuvieron guardan 0, que también es
  un DV válido). `_completarDV` lo recalcula desde el NIT (tipo 5, algoritmo de
  la DIAN) al guardar el RIT y lo corrige si no coincide.
- **Correo de la firma**: sin cambios (sigue al representante legal o
  propietario, opción b del cliente); el RIT ahora lo dice debajo de esos campos.
- Pruebas: `probar_segunda.php` (22 casos) en el arnés. `caso.php` pone ahora
  `SCRIPT_FILENAME` como Apache; sin eso `class.anexos.php` no se ejecuta.

### Intereses de mora A MANO (Javier y el cliente, 2026-09-25)

- "Que se deje de manera manual de momento": no hay tabla de tasas ni fórmula.
  Los escribe a mano quien liquida, en tres sitios:
  - El renglón de intereses de la DECLARACIÓN (ICA casilla 37 =
    `dec_ValorConcepto16`; retención 16; autorretención 21), que ya era de
    captura manual (migración 010): lo liquida el contribuyente al presentar.
  - El RECIBO DE PAGO, línea "INTERESES DE MORA AL <pague antes de>".
    `reciboPago.php` sin `?intereses=` muestra primero una casilla (valor de la
    declaración, fecha límite, días de mora, intereses que ya trae el formulario
    y total en vivo); "Generar recibo" vuelve con `?intereses=` y el valor se
    suma al total, al "SON:" y al código de barras.
  - PSE: el resumen (`extensiones/pse/pagar.php`) trae la misma casilla y
    `crearSesion.php` cobra el total MÁS los intereses (con un campo "Intereses
    de mora" en el comprobante del banco).
- Quién y cuándo (la regla vive en `business/class.vencimientoICA.php`:
  `leerIntereses`, `exigeIntereses`, `diasDeMora`, `hoy`):
  - **ICA vencida**: casilla para TODOS. El contribuyente también saca el recibo
    y paga por PSE, pero CON intereses (cliente, 2026-09-25: "que le salga CON
    intereses"): son obligatorios, salvo que la declaración ya traiga los suyos
    en el renglón 37. La Alcaldía los escribe y puede dejarlos en 0.
  - Retención y autorretención (sin fecha límite todavía): casilla solo para la
    Alcaldía en el recibo; el contribuyente lo saca directo y no puede poner
    intereses (403); PSE no los lleva.
  - ICA que todavía no vence: sin casilla; intereses > 0 se rechazan.
  - Pesos enteros (acepta puntos de miles; lo pegado pierde los centavos),
    máximo 10 cifras. Un valor no válido vuelve a la casilla con el error.
- Lo pagado: el recibo lo registra el recaudo con lo que entró (`dec_ValorPago`);
  PSE registra lo que confirma el banco (`PlacetoPay::interpretarRespuesta`,
  campo `valor`), no el total de la declaración. No hay tabla de intereses: el
  PDF del recibo lleva el LIQUIDADOR cuando lo genera la Alcaldía.
- Pruebas: `probar_intereses.php` (19 casos, uno mueve `ICA_FECHA_LIMITE` y otro
  el renglón 37, y los devuelven) y `probar_pse_intereses.php` (11; NO crea
  sesiones en el banco: con `sinBanco` en `caso.php` la pasarela apunta a
  `https://sin-banco.invalid`). En `probar_nuevo.php` los recibos pasan intereses.

### Revisión previa al despliegue (2026-09-25)

Tres revisiones independientes del diff (cierre de establecimientos, RIT,
declaraciones y recibo). Lo que se encontró y quedó arreglado:

- **Establecimiento nuevo nacía "Cerrado"**: `_agregar` quitaba `est_Activo` del
  POST, el DAO omite lo nulo y la columna trae `DEFAULT -1`, que TODO el sistema
  lee como no activo (declaración, liquidación y retenciones filtran
  `est_Activo = 1`). Ahora el servidor pone 1.
- **Crear no edita**: con `est_Id` en el POST la función 1 hacía UPDATE de ese
  local (lo reabría y le cambiaba el dueño); ahora se descarta. La 2 convierte
  `est_Id` a entero antes de todo (el DAO lo pegaba tal cual en el WHERE).
- **Cerrado es `est_Activo <> 1`** también en el servidor (editar, cerrar,
  reabrir, anexos), como ya lo leían la pantalla y los PDF.
- Opción de uso recortada y solo 1/2 por crear/editar ("3 " pasaba y SQL Server
  lo guarda como 3); el rechazo de la 3 va antes de repartir código.
- **Cierre**: fecha de cese entre el inicio de actividades del local (o 1900) y
  hoy de Colombia (un año de dos cifras llegaba como 0025 y daba 500);
  `SET NOCOUNT ON; UPDATE … WHERE est_Activo = 1; SELECT @@ROWCOUNT` para que un
  doble envío no deje dos CIERRE; observación cortada a 255; botón deshabilitado
  mientras envía. **Reapertura** en transacción (o se reabre y se anota, o nada),
  con el mismo `@@ROWCOUNT` y justificación ≤ 1.000 caracteres (NVARCHAR de la 035).
- **PDF del RIT**: "Cese de actividades" sale solo por el cese del CONTRIBUYENTE
  (`ind_FechaCese`). Caía también al `est_Fecha_cierre` de cualquier local, y
  desde el cierre nuevo eso marcaba cese total a quien cerraba UNO de sus locales.
- **No. de establecimientos** en declaración, liquidación y retenciones: activos
  más los cerrados durante el año declarado o después (funcionaron ese año);
  reimprimir una declaración presentada ya no cambia la cifra al cerrar un local.
- Lista de establecimientos con el texto escapado; "Quitar" un anexo muestra el
  rechazo del servidor.
- **RIT: una sola regla para guardar y firmar**, `RitFirma::faltantes()` (campos
  y documentos). La firma la aplica sobre lo GUARDADO, antes de mandar el código
  y antes de firmar (`microservicios/firmas/api.php`): un RIT guardado a medias
  antes de la regla ya no se firma incompleto. Teléfono y celular cuentan solo
  con dígitos ("N/A" no pasa); régimen e IVA exigen UNA opción por grupo, y en
  pantalla las casillas del grupo se excluyen. El departamento ya no se exige
  aparte (no viaja; si el catálogo no carga quedaba trabado). Las marcas rojas se
  limpian al recargar y al pasar a persona jurídica.
- **NIT en el PDF de la declaración ICA y en la liquidación**: marcaban NIT con
  el tipo 2; el NIT es el 5 (el 2 no existe), así que toda empresa salía con la
  X en C.C. (`ritActualizado.php` ya se había corregido por lo mismo).
- **Editar una declaración borraba el impuesto de la Ley 56**: la edición no
  pintaba capacidad instalada ni `dec_ValorImpuesto`, limpiar los dejaba en 0 y
  Guardar los grababa en 0; `sp_calculo_comercio` suma ese valor al renglón 20
  (Termopaipa). Quedó oculto mientras Guardar fallaba en edición (f8ce25c).
  También se pintan la fecha y la hora.
- Errores de la base al guardar o recalcular: el detalle va al log y la pantalla
  recibe un aviso claro, como texto (en SweetAlert2 7 el 2.º argumento es HTML).
  El aviso "Declaración guardada" sale cuando la ventana ya cerró.
- Consultar: en una pagada, "Pagado $X" debajo del valor si lo que entró no es
  la casilla 38 (los intereses a mano del recibo).
- **Recibo**: al pegar "1.234,00" quedaba 123.400 (se quitaban la coma y los
  centavos como dígitos); ahora lo pegado pierde los centavos, la coma no se
  escribe, el tope es de 10 cifras y un valor no válido vuelve a la casilla con
  el error. "Hoy" es el de Colombia en el recibo, en `VencimientoICA::vencida` y
  en el cierre: con el servidor en UTC, desde las 7 p. m. ya era "mañana".
- **Recaudo por archivo**: retención y autorretención numeran igual que el ICA
  (2026000001 existe en los tres) y sus recibos llevan el mismo EAN, así que un
  pago de retención se aplicaba a la ICA ajena con ese número. Un número que
  también es de una retención o autorretención PRESENTADA Y SIN PAGAR ya no se
  aplica: va a la pestaña "Revisar a mano".
- Segunda pasada (dos revisiones más, sobre las correcciones y el pago):
  - **PSE sin dueño**: `pagar.php` y `crearSesion.php` se abrían con la URL, sin
    sesión: en certificación cualquiera podía crear una sesión del ambiente de
    PRUEBAS sobre una declaración real, y el banco de pruebas la dejaba "pagada"
    sin plata. Ahora `PseModulo::motivoParaNoPagar`: sesión, `botonVisible` y
    dueño (la Alcaldía, cualquiera), como los PDF. `retorno.php` y el webhook
    siguen abiertos (los usa el banco).
  - Un error de `sp_calculo_comercio` se tragaba y Guardar, el recálculo y
    Liquidar decían que todo salió bien: `_ejecutarSpLiquidacion` ya no atrapa;
    las funciones 6, 7 y 14 registran y avisan. La 14 distingue lo que es del
    usuario (`DeclaracionesICAException`, "No se encontró la declaración").
  - PSE: la fecha de pago que se registra y la que muestra el retorno, en hora de
    Colombia; el aviso de pago pendiente no afirma un monto sin intereses; junto
    al botón se explica que faltan los intereses; "Atrás" desde el banco deja el
    botón como debe. La coma de centavos se puede escribir en las dos casillas y
    se descarta (`leerIntereses`).
  - "Declaración guardada" y "Establecimiento cerrado": el aviso sale al cerrar
    la ventana, o directo si ya estaba cerrada (no queda uno colgado); Guardar se
    deshabilita mientras envía. El cierre ya no da 500 si falla la base.
  - PDF ICA y liquidación: el pasaporte ya no sale como C.C. y el DV se imprime
    solo con NIT. El RIT exige al firmar lo mismo que al guardar también con los
    enteros de la base (municipio 0, tipo de persona que no sea 1 o 2).
  - El conteo de establecimientos del PDF corta en el 1 de enero de
    `dec_AnioDeclaracion`, el año que el formulario imprime como "AÑO GRAVABLE".
    Si el cliente decide que ese año es el de presentación (ver la decisión de
    enero), el corte pasa al año anterior.
- Pruebas: `probar_revision.php` (32 casos). `probar_segunda.php` y
  `probar_intereses.php` fijan `America/Bogota` como el servidor.

### Pendientes

- Migración **035** (novedades de establecimiento): sin ella se cierra igual
  (queda en el log), pero no se puede reabrir, porque la justificación tiene que
  quedar escrita.
- **Antes de abrir PSE a todos** (además de las credenciales de producción y de
  registrar el webhook): `crearSesion.php` pisa `*_PSE_RequestId` sin consultar
  la sesión anterior. Si alguien paga, el banco aprueba, cierra la pestaña sin
  volver y abre otra sesión antes de que llegue el webhook o pase el cron, la
  primera queda huérfana (el webhook busca por requestId) y puede pagar dos
  veces. Con el webhook registrado el riesgo es mínimo; lo de fondo es consultar
  la sesión anterior antes de crear otra, y probarlo contra el banco. Al
  certificador hay que avisarle que el ICA 2026 ya venció y pide intereses de
  mora (cualquier valor), o que pruebe con una retención.
- **Referencias de recaudo por módulo**: mientras ICA, retención y autorretención
  compartan números, los choques quedan en "Revisar a mano". Lo de fondo es que
  la referencia distinga el módulo (decisión con el cliente y el banco, porque
  cambia el código de barras).
- Guardar el RIT depende de las migraciones 004 y 017 (anexos del contribuyente):
  Paipa y Guateque las tienen; confirmarlo en Macanal cuando tenga la clave.
- El DAO genérico (`class.DAO.php`) arma el SQL pegando los valores entre
  comillas; los controladores que pasan por él dependen de eso.
- Revisión 2026-09-25, lo que falta: la parametrización de la corrección (el
  cliente quiere una reunión para lo pagado, la diferencia, los intereses y un
  ejemplo); y el calendario tributario de retención y autorretención (por último
  dígito del NIT; el adjunto no ha llegado). Los intereses quedaron a mano (ver
  su sección); si algún día se automatizan, el cálculo del cliente era: tabla de
  tasas mensuales como la de predial, base = total sin sanciones, desde el día
  siguiente a la fecha límite hasta el pago, redondeo al mil; faltaba saber si
  los meses cuentan 30 días.
- Migración **033** (consorcio/patrimonio): `_guardarRIT` omite esas dos columnas
  si no existen, para que desplegar sin correrla no tumbe el guardado del RIT;
  igual hay que aplicarla para que se graben.
- Migración **034** (fecha límite del ICA): sin ella el código usa el 30/04, pero
  el parámetro no aparece en Municipio y bancos.
- Intereses: las dos decisiones que quedaban se cerraron el 2026-09-25 (el
  contribuyente saca el recibo de una vencida CON intereses; PSE cobra igual que
  el recibo). Si se automatiza el cálculo, lo que dijo el cliente está en
  "Revisión 2026-09-25, lo que falta". Faltan las fechas de vencimiento de
  retención y autorretención, y pasar el recibo por el escáner del banco antes de
  anunciarlo (misma certificación pendiente del GS1-128).
