# Crear la base de un municipio nuevo (Plesk)

El esquema completo y los catalogos viven en `BD/esquema/` (estructura y catalogos de Paipa al nivel de la
migracion 053, sin datos de nadie). `BD/provisionar_municipio.php` los carga en una base vacia. Las migraciones
de `BD/migraciones/` NO sirven para crear una base desde cero (la 001 ya supone que existen las tablas).

## Paso a paso

1. **Dominio.** Crear el dominio en la suscripcion (convencion `industria-comercio-<municipio>.erpsoftsas.com`;
   hay DNS comodin). PHP 8.3 con sqlsrv, pdo_sqlsrv, gd y mbstring. Sin redireccion a https hasta tener certificado.
2. **Git.** Dominio > Git: repositorio publico del proyecto, rama `main`, ruta de despliegue = raiz del dominio
   (no `App/Firma_digital`), carpeta Git con nombre unico (`ica_<municipio>.git`). "Pull ahora".
3. **Base de datos.** Bases de datos > Agregar base de datos (MSSQL). Nombre `erpsofts_ind_comercio_<4 letras>`,
   **intercalacion `Modern_Spanish_CI_AS`** (la de Paipa) y un usuario propio cuyo **esquema por defecto sea `dbo`**
   (si no lo es, las tablas de firma y las migraciones 001/008/030 fallan; el script lo detecta y se detiene).
   La clave la define quien instala; no pasa por el chat ni por el repositorio.
4. **Config.** Crear `config.municipio.php` una carpeta ARRIBA de `erpsoftsas/` (`App/Firma_digital/`), a partir
   de `config.municipio.example.php`: identidad del municipio y las cuatro constantes
   `DB_PROD_SERVER`, `DB_PROD_USER`, `DB_PROD_PASS`, `DB_PROD_NAME`. No copiar el bloque `PLACETOPAY_*` del ejemplo.
   Sin las cuatro constantes la aplicacion usa unas credenciales de produccion escritas en el codigo, y el
   script de aprovisionamiento se niega a correr.
5. **Cargar el esquema.** Herramientas de desarrollo > Tareas programadas > Agregar tarea > "Ejecutar un script PHP":
   ruta `.../App/Firma_digital/erpsoftsas/BD/provisionar_municipio.php`, PHP 8.3.
   - Primero SIN argumentos y **"Ejecutar ahora"** (nunca "Aceptar": guarda la tarea). Confirmar que
     "Base de datos" es la del municipio. No escribe nada.
   - Despues con el argumento `--aplicar` y "Ejecutar ahora". Debe terminar con "Terminado sin errores" y
     resumir ~39 tablas, 1120 ciudades, 69 actividades, 18 conceptos y 0 usuarios.
   - Es repetible: si se corta, se corrige y se vuelve a correr. Si la base ya esta aprovisionada, no hace nada.
6. **Migraciones.** Misma forma con `BD/aplicar_migraciones.php`: sin argumentos primero. Deben salir pendientes la
   `042` (Wompi, solo Macanal) y la `043` (consecutivos de Paipa), que **no se aplican**, y las posteriores a la 053.
   Aplicar cada nueva POR NUMERO (`--aplicar 054`, etc.), nunca `--aplicar` a secas. PHP 8.3, "Ejecutar ahora".
7. **Primer administrador.** `BD/crear_administrador.php` (ver su encabezado; recomendado `--sin-clave`).
8. **SSL.** Let's Encrypt del dominio; despues se puede activar la redireccion a https.
9. **Municipio y bancos** (en la aplicacion): EAN de recaudo (queda **vacio**: sin el, el codigo de barras no es
   pagable), cuentas de los bancos, `ICA_FECHA_LIMITE`, funcionario del RIT. Pasarela solo cuando haya convenio.
10. **Verificar.** Entrar por el navegador, revisar que el login muestre el nombre y el escudo del municipio, y
    que ningun PDF diga "Paipa". Si existe `BD/verificar_municipio.php`, correrlo igual (solo lectura).

Despues de la prueba, salir de la tarea con "Cancelar" para no dejarla guardada.

## Que trae y que no trae el esquema

- Trae: tablas, indices, llaves, procedimientos (`sp_calculo_comercio`, numeracion de declaraciones y retenciones,
  `sp_buscar_predios`) y los catalogos: ciudades (DIVIPOLA), modulos y permisos, los 4 roles con sus permisos base,
  actividades, grupos tarifarios, conceptos y renglones de retencion, bancos (sin cuentas) y parametros.
- **Ojo:** las tarifas de actividades y las formulas de conceptos son las de Paipa. Cada Alcaldia debe validarlas
  con su Estatuto de Rentas antes de liquidar.
- No trae: contribuyentes, usuarios, clave alguna, anexos, firmas, pagos ni datos de Paipa. `RECAUDO_EAN` y
  `RECIBO_BANCOS` quedan vacios.
- Deja registradas en `conf_migraciones` las migraciones 000 a 053 (menos 042 y 043); lo que el esquema incluye de
  esas dos (columnas, parametros y procedimientos de la 043 con base 0) ya esta, y son inofensivas si algun dia se aplican.
- No se incluyen las tablas de respaldo de Paipa (`*_bak_20260821`, `ind_conceptos_formulas_previas`) ni `sysdiagrams`.

## Mantener el esquema al dia

El esquema esta al nivel de la 053. Las migraciones nuevas se aplican encima con `aplicar_migraciones.php`. Si algun
dia se regenera `BD/esquema/` desde una base mas nueva, hay que subir `COBERTURA_ESQUEMA` en
`provisionar_municipio.php` al mismo numero; si no, quedarian como pendientes migraciones que el esquema ya incluye (son re-ejecutables, pero se repetirian).
