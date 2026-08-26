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

## Dos cosas que NO arregla ninguna migración, y son decisión de la Alcaldía

### Las declaraciones ya presentadas que quedaron con ceros

Hasta la migración `010`, el recálculo ponía en cero los nueve renglones que
escribe el contribuyente (retenciones, anticipos, sanciones…). El arreglo
aplica de aquí en adelante, pero **las que ya se presentaron con ceros grabados
siguen así, y no se tocan**: una declaración presentada es un acto legal y no
se recalcula por detrás. La vía correcta es corregirla, una por una, y eso lo
decide la Alcaldía.

Para encontrarlas:

```sql
SELECT d.dec_Id, d.dec_NumeroDeclaracion, d.dec_AnioDeclaracion,
       c.ind_NumeroIdentificacion, d.dec_ValorConcepto1 AS impuesto
  FROM ind_declaraciones_ica d
  JOIN ind_contribuyentes c ON c.ind_Id = d.dec_IdContribuyente
 WHERE d.dec_Estado = 2                    -- presentada
   AND ISNULL(d.dec_ValorConcepto1, 0) > 0 -- con impuesto liquidado
   AND ISNULL(d.dec_ValorConcepto5,0)  = 0 AND ISNULL(d.dec_ValorConcepto6,0)  = 0
   AND ISNULL(d.dec_ValorConcepto7,0)  = 0 AND ISNULL(d.dec_ValorConcepto8,0)  = 0
   AND ISNULL(d.dec_ValorConcepto9,0)  = 0 AND ISNULL(d.dec_ValorConcepto10,0) = 0
   AND ISNULL(d.dec_ValorConcepto11,0) = 0 AND ISNULL(d.dec_ValorConcepto16,0) = 0
   AND ISNULL(d.dec_ValorConcepto17,0) = 0
 ORDER BY d.dec_Id;
```

En la copia local de trabajo son **2**. Que salgan en esta lista no prueba que
estén mal: un contribuyente puede no tener ninguna retención ni anticipo. Es
una lista de casos **a revisar**, no de errores confirmados.

### Los duplicados anteriores al corte

La migración `020` impide crear declaraciones repetidas del mismo contribuyente
y período **de aquí en adelante**, pero deja las anteriores como están. El corte
queda anotado en `ind_consecutivos` con `cse_Tipo = 'CORTE_DUPLICADOS'`.

Para verlas:

```sql
SELECT dec_IdContribuyente, dec_AnioDeclaracion, dec_MesDeclaracion, COUNT(*) AS cuantas
  FROM ind_declaraciones_ica
 WHERE dec_DeclaracionCorrige IS NULL
 GROUP BY dec_IdContribuyente, dec_AnioDeclaracion, dec_MesDeclaracion
HAVING COUNT(*) > 1
 ORDER BY cuantas DESC;
```

Ojo con el dato antes de decidir: en la copia local, 213 declaraciones colapsan
en **cinco** períodos distintos. Eso no es un conjunto sano con algunos
duplicados: es una base de pruebas llena de intentos repetidos. Conviene
comprobar cómo se ve esto en producción antes de planear una limpieza.

Cuando se limpien, el índice definitivo (sin el filtro por `dec_Id`) está
escrito al pie de la migración `020`.
