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

## Correos repetidos (2026-08-26)

La migración `022` pone un índice único sobre `conf_usuarios.usu_Correo`. Antes
de aplicarla hay que dejar la tabla sin repeticiones, o la creación del índice
falla a propósito con un mensaje claro.

Para ver si una base las tiene:

```sql
-- cuentas que comparten correo (bloquea la migración 022)
SELECT LOWER(LTRIM(RTRIM(usu_Correo))) AS correo, COUNT(*) AS veces
  FROM conf_usuarios
 WHERE usu_Correo IS NOT NULL AND LTRIM(RTRIM(usu_Correo)) <> ''
 GROUP BY LOWER(LTRIM(RTRIM(usu_Correo)))
HAVING COUNT(*) > 1;

-- contribuyentes que comparten correo de notificación
SELECT LOWER(LTRIM(RTRIM(ind_Email))) AS correo, COUNT(*) AS veces
  FROM ind_contribuyentes
 WHERE ind_Email IS NOT NULL AND LTRIM(RTRIM(ind_Email)) <> ''
 GROUP BY LOWER(LTRIM(RTRIM(ind_Email)))
HAVING COUNT(*) > 1;

-- una misma dirección en las dos tablas, pero de documentos distintos
SELECT u.usu_Id, u.usu_Usuario, u.usu_Correo, c.ind_Id, c.ind_PrimerNombre
  FROM conf_usuarios u
  INNER JOIN ind_contribuyentes c
          ON LOWER(LTRIM(RTRIM(c.ind_Email))) = LOWER(LTRIM(RTRIM(u.usu_Correo)))
 WHERE LTRIM(RTRIM(c.ind_NumeroIdentificacion)) <> LTRIM(RTRIM(u.usu_NumeroDocumento));
```

**Al resolverlas, la unicidad se mide por DOCUMENTO, no por fila.** Dos registros
de contribuyente con el mismo `ind_NumeroIdentificacion` son la misma persona y
*deben* poder compartir correo — el cliente pidió justamente que el correo de la
cuenta y el del RIT sean el mismo. La tercera consulta ya excluye ese caso; las
dos primeras no, así que léelas junto al documento antes de tocar nada.

Ese es también el motivo de que **no** haya índice único sobre
`ind_contribuyentes.ind_Email`: la regla de esa columna es por contribuyente y
cruza las dos tablas, y un índice no sabe expresar ninguna de las dos cosas. La
hace `business/controller/class.contribuyentes.php`.

### Qué se limpió en la copia local

Tres identidades distintas se repartían dos direcciones:

| Identidad | Documento | Registros | Correo |
|---|---|---|---|
| Cristian Manrique (natural) | 1052400234 | `usu 12`, `ind 24` | `cristianmd99@gmail.com` |
| SISTEMAS ERPSOFT S.A.S | 901632232 | `usu 16`, `ind 26` | `cristianmd99+erpsoft@gmail.com` |
| Cuenta de pruebas | 1052400237 | `usu 17` | `cristianmd99+cris@gmail.com` |

Las direcciones nuevas usan el sufijo `+` de Gmail: llegan al mismo buzón de
siempre, así que no se pierde correo ni se inventa la dirección de nadie.

## Parámetros de configuración (`conf_parametros`)

La tabla existe desde la migración `009` y es donde vive todo lo que **cambia de
una entidad a otra**: el EAN de recaudo, y desde la `023` las credenciales de la
pasarela de pago. La Alcaldía las edita desde la pantalla de Configuración, sin
desplegar nada.

### Cómo se lee un parámetro desde el código

Siempre por `business/class.parametros.php`. **No volver a copiar el
mecanismo**: estuvo duplicado dentro de `class.codigoBarrasRecaudo.php` y el
segundo consumidor obligó a extraerlo.

```php
include_once SERVER . '/business/class.parametros.php';

// solo la tabla
$v = \erpsoftsas\Parametros::valor('RECAUDO_EAN');

// la tabla y, si no hay nada, la constante del config (el patrón habitual)
$v = \erpsoftsas\Parametros::valorOConstante(
        'PASARELA_BASEURL', 'PLACETOPAY_BASEURL', '#^https://\S+$#');
```

La tabla manda; la constante de `config.municipio.php` queda de respaldo para
instalaciones sin la migración correspondiente. Un parámetro **vacío es
"no configurado"**, no "configurado en blanco" — por eso `null` y no `''`.

### Al añadir un parámetro nuevo

- **El patrón (`par_Patron`) se guarda con anclas y SIN delimitadores.** El
  validador les pone `#` alrededor; hasta el 2026-08-26 les ponía `/`, y eso
  rompía cualquier patrón que llevara una barra — el de una URL, sin ir más
  lejos. Si el patrón lleva `#`, se escapa solo.
- **Si el valor es un secreto, `par_Sensible = 1`.** Con esa marca el
  controlador no lo devuelve nunca al navegador, no lo escribe en el log, y
  guardar en blanco no lo borra. Sin ella, cualquier usuario de la Alcaldía lo
  vería en pantalla y quedaría copiado en los logs del servidor.
- **Nace vacío**, para que el código siga cayendo a la constante y aplicar la
  migración no cambie ningún comportamiento.
- **No sembrar credenciales reales en el `.sql`**: es un archivo versionado.

### Un límite con el que ya se tropezó

`conf_migraciones.mig_Nota` es `varchar(500)`. Una nota más larga hace fallar la
migración entera con *"String or binary data would be truncated"*, y encima
después de haber aplicado los cambios de arriba. Contar los caracteres antes.

### Lo que la 023 NO resuelve

Cambiar de **proveedor** de pasarela. Los parámetros permiten apuntar a otro
convenio del mismo proveedor —otra entidad, otro ambiente, otro banco del
grupo—, que es lo que cubre «depende del contrato» en la mayoría de los casos.
Si una entidad firmara con una pasarela de otro fabricante, el protocolo es
distinto y eso es un adaptador (`business/class.placetopay.php` tendría que
pasar a ser una implementación de una interfaz), no un parámetro.
