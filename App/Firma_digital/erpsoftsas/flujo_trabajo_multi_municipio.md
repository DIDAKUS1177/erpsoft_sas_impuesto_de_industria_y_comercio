# Flujo de Trabajo — Despliegue a Nuevos Municipios

Este documento complementa a `plan_despliegue_plesk_claude.md` (el "qué" técnico de
Plesk) con el "cómo" del día a día: cómo seguir desarrollando/probando en local sin
romper Paipa (que ya está en producción) y qué revisar antes de subir un municipio
nuevo.

## 1. Principio guía

**Paipa es producción viva. Todo cambio de código pasa primero por local (Docker) antes
de tocar Plesk.** El código de la aplicación (`business/`, `core/`, `dist/`,
`microservicios/`) es **compartido entre todos los municipios** — un bug o cambio de
comportamiento introducido para Guateque se siente también en Paipa en el próximo pull.
Lo único que NO se comparte es `config.municipio.php` (fuera del repo, un nivel arriba
de cada instalación) y los datos de cada base de datos.

Regla práctica: si el cambio es **de negocio o de esquema de BD**, hay que preguntarse
primero si aplica a todos los municipios o es específico de Paipa. Si es específico de
Paipa, no debería ir en código compartido — debería resolverse por configuración o
quedar documentado como excepción.

## 2. Ciclo de desarrollo local (antes de tocar nada en Plesk)

1. Levantar el entorno Docker local (`docker-compose.yml`, contenedor
   `erpsoftsas_web_completo` en el puerto 8081 — es el que monta solo
   `App/Firma_digital/erpsoftsas/`, el mismo árbol que se despliega).
2. Hacer el cambio y probarlo contra la BD de desarrollo local (`DB_DEV_*` en
   `config.municipio.php`, contenedor `db` / SQL Server local).
3. Si el cambio toca esquema de BD, escribir el script de migración en
   `BD/` siguiendo el formato ya usado en `migracion_2026-08_contribuyente.sql`:
   bloque de respaldo no destructivo primero, bloques de cambio con guardas
   (`IF COL_LENGTH(...) IS NULL`, `IF NOT EXISTS (...)`) para que sea re-ejecutable sin
   error, y una nota de verificación al final.
4. Confirmar que nada de lo nuevo depende de datos que solo existen en Paipa
   (contribuyentes, establecimientos, CIIU específicos, etc.).
5. Commit y push a `main` en GitHub — recordar que esto es lo que **todos** los
   municipios recibirán en su próximo pull (ver sección 4 del plan de Plesk).

## 3. Estado real de la base de datos (verificado 2026-08-06)

- El esquema de producción de Paipa **ya no coincide** con el script base
  `bd-industria_comercio.sql` (fechado 23-mar, previo a la migración de agosto). Antes
  de crear la BD de un municipio nuevo hay que regenerar este script — lo más seguro es
  hacer un `mssql-scripter` / export del esquema actual de Paipa (sin datos) en vez de
  seguir arrastrando el `.sql` viejo.
- Migraciones ya aplicadas sobre el esquema base que un municipio nuevo también
  necesita: `migracion_2026-08_contribuyente.sql` (declaración por contribuyente,
  campos de contador/revisor, `fd_Rol`) y `microservicios/firmas/sql_migraciones.sql` /
  `sql_migracion_conf_usuario.sql` (tabla `conf_usuario` en SQL Server, reemplazo de la
  consulta a MySQL).
- Pendiente de decidir: si `bd-industria_comercio.sql` se actualiza como "esquema base
  congelado" (foto del esquema actual, sin las migraciones sueltas) o si se deja como
  base histórica y las migraciones se aplican en orden sobre cualquier BD nueva. Lo
  primero es más simple para desplegar rápido un municipio nuevo; lo segundo mantiene
  trazabilidad de cómo se llegó al esquema actual.

## 4. Checklist para desplegar un municipio nuevo

1. **Código**: crear el dominio/subdominio en Plesk, conectar la extensión Git al
   mismo repo (`DIDAKUS1177/erpsoft_sas_impuesto_de_industria_y_comercio`), pull a
   `/erpsoftsas` (ver `plan_despliegue_plesk_claude.md` sección 2.1).
2. **Base de datos**: crear la BD nueva en Plesk/SQL Server y aplicar el esquema
   **actualizado** (ver punto 3 — no usar `bd-industria_comercio.sql` tal cual hasta
   regenerarlo).
3. **`config.municipio.php`**: copiar `config.municipio.example.php` (raíz del repo,
   plantilla ya sin credenciales) a `config.municipio.php` un nivel arriba de
   `/erpsoftsas`, y completar nombre, logos, colores y credenciales `DB_PROD_*` del
   municipio nuevo.
4. **Datos semilla**: si el municipio arranca sin usuarios, insertar al menos un
   usuario administrador en `conf_usuario` (ver el seed de ejemplo en
   `microservicios/firmas/sql_migracion_conf_usuario.sql`).
5. **Prueba de humo**: login, dashboard, crear una declaración de prueba y firmarla
   (OTP), descargar el PDF — confirma que `config.municipio.php` es alcanzable desde la
   ruta relativa que usan `index.php` / `dist/menu.php` / `dist/dashboard.php`
   (`dirname(__DIR__)` o `dirname(dirname(__DIR__))` según el archivo — ver el gotcha
   en `CLAUDE.md`).
6. **UVT del año**: confirmar que `business/config.tributario.php` tiene el valor
   vigente (se actualiza cada enero).

## 5. Qué es específico de Paipa y NO debería copiarse tal cual

- Datos reales: contribuyentes, establecimientos, declaraciones, credenciales de BD de
  producción (`erpsofts_gestor_macanal`).
- Branding: escudo, logo, colores (`#1fa49d` / `#17756f`), fondo de login —
  todo esto vive en `config.municipio.php` y en `vendors/images/`, se reemplaza por
  municipio.
- PSE / código de barras: está deshabilitado a propósito en Paipa por un trámite
  pendiente con Banco de Bogotá — no asumir que otro municipio tiene el mismo estado a
  menos que se confirme.
