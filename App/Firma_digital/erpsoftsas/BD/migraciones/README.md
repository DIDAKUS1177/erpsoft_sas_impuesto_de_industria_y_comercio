# Migraciones de base de datos

## Por qué existe esta carpeta

Durante el trabajo de agosto 2026 se chocó **tres veces** con columnas que
existían en producción pero no en la copia local (`fd_Rol`, `dec_Estado`,
`ind_EmailContador` / `ind_EmailRevisor`). Cada vez costó un rato entender
que el código estaba bien y lo que fallaba era el esquema.

El problema de fondo: no había forma de saber **qué se aplicó dónde**. El
esquema base (`BD/Base Datos_Limpia_22_05_2023.sql`) es de mayo de 2023 y
desde entonces los cambios se fueron haciendo a mano, sin registro.

Con el despliegue a varios municipios eso deja de ser una molestia y pasa a
ser un riesgo real: cada base nueva puede quedar distinta de las demás.

## Reglas

1. **Toda migración es re-ejecutable.** Siempre con guardas
   (`IF COL_LENGTH(...) IS NULL`, `IF NOT EXISTS (...)`). Correrla dos veces
   no debe fallar ni duplicar nada.
2. **Numeradas y en orden**: `001_...`, `002_...`. Se aplican de menor a mayor.
3. **Cada una se registra sola** en la tabla `conf_migraciones` (la crea la
   `000`). Así se puede consultar qué tiene cada base.
4. **No se editan las ya aplicadas.** Si algo salió mal, se corrige con una
   migración nueva.

## Cómo saber qué le falta a una base

```sql
SELECT mig_Nombre, mig_FechaAplicada FROM conf_migraciones ORDER BY mig_Nombre;
```

Lo que no aparezca ahí, falta.

## Orden para una base NUEVA (municipio nuevo)

1. `BD/Base Datos_Limpia_22_05_2023.sql` — esquema base
2. `BD/migracion_2026-08_contribuyente.sql` — declaración por contribuyente
3. `microservicios/firmas/sql_migracion_conf_usuario.sql` — tabla de usuarios
4. Todo lo de esta carpeta, en orden numérico
5. Datos semilla: al menos un usuario administrador

> **Pendiente:** lo ideal sería reemplazar los pasos 1–3 por un volcado del
> esquema actual de Paipa (solo estructura, sin datos), que ya los tiene
> todos aplicados. Mientras eso no exista, hay que seguir la secuencia.

## Sobre `conf_ciudades`

El catálogo completo (1.120 municipios DIVIPOLA) lo carga la migración `002`.
Sin ella, los desplegables de departamento y municipio salen casi vacíos.
