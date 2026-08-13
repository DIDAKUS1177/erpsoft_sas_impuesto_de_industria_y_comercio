# Cambios solicitados por el cliente — Sistema ICA, Alcaldía de Paipa

**Fecha:** 12 de agosto de 2026 · **Versión:** 1.0 · **Estado:** alcance confirmado con el cliente

Resumen: 24 cambios · 2 bugs críticos · 5 fases · ~11 días · 0 tablas nuevas.

---

## 1. Hallazgos verificados

Dos de los problemas reportados no son fallas de visualización, sino **pérdidas de
datos que ya ocurrieron**. Se confirmaron consultando la base, no solo leyendo código.

### Al editar se borran las actividades económicas

El `DELETE` de actividades se ejecuta siempre, pero la reinserción solo ocurre si el
formulario envió la lista (`_editarEstablecimientos()` en
`business/controller/class.establecimientos.php`). Editar desde una pantalla que no las
manda las elimina de forma definitiva.

Esto explica dos quejas a la vez: que el RIT no carga las actividades, y que el
certificado imprime "No registra actividades". No es que no las lea — es que ya no están.

> **6 de 12** establecimientos quedaron sin ninguna actividad económica.

### Al editar se borran los archivos adjuntos

El guardado recorre todo `$_POST` y lo escribe sin filtrar. Un campo de archivo que no se
vuelve a seleccionar viaja vacío, y como una cadena vacía no es `null`, sobrescribe la
ruta guardada.

> **12 de 12** establecimientos con anexos vacíos · **6 de 12** sin RUT.

Ambos se corrigen sin tocar la base de datos y encabezan el plan.

---

## 2. Cambios solicitados

Marca `[BD]` = requiere modificar la base de datos.

### Estructura y navegación

| # | Cambio | BD |
|---|---|---|
| 1 | Sacar el RIT de "Industria y Comercio" y dejarlo como módulo propio, externo | — |
| 2 | Renombrarlo "Registro de Identificación Tributaria" | — |
| 3 | Ubicarlo de primero o de último en el menú | — |
| 4 | El RIT es **solo formulario**: al abrirlo no aparece la tabla, sino el formulario listo para actualizar | BD |
| 5 | Establecimientos queda como módulo aparte y **pierde el botón de descarga** | BD |

### Formulario del RIT

| # | Cambio | BD |
|---|---|---|
| 6 | Botones "Cancelar" y "Actualizar" en la parte superior | — |
| 7 | Cambiar el título "Establecimiento" por "RIT" | — |
| 8 | Matrícula de **persona natural o jurídica**, en campo propio y distinto al del establecimiento | BD |
| 9 | Que cargue las actividades económicas | BD |
| 10 | El RIT se crea automáticamente en el primer ingreso al sistema | BD |

### Módulo de establecimientos

| # | Cambio | BD |
|---|---|---|
| 11 | Formulario propio con datos generales y matrícula **de establecimiento** | BD |
| 12 | Botones "Crear", "Actualizar" y "Cerrar" | — |
| 13 | Distinguir visualmente los establecimientos activos de los cerrados | — |

### Cese de actividades

| # | Cambio | BD |
|---|---|---|
| 14 | Solo el administrador puede registrarlo | BD |
| 15 | El contribuyente lo ve en modo lectura | — |
| 16 | Aparece tanto en el RIT como en el establecimiento | BD |

### Archivos adjuntos

| # | Cambio | BD |
|---|---|---|
| 17 | Poder consultar los archivos ya subidos, no solamente cargarlos | — |
| 18 | **CRÍTICO** Que dejen de borrarse al editar | — |

### Certificado en PDF (se descarga desde el RIT)

| # | Cambio | BD |
|---|---|---|
| 19 | Incluir nombre, matrícula, dirección y fecha de inicio de los establecimientos | — |
| 20 | Que cargue toda la información que le corresponde | — |

### Declaraciones

| # | Cambio | BD |
|---|---|---|
| 21 | "Presentar": quitar los establecimientos y habilitar la creación de nuevas declaraciones | — |
| 22 | "Consultar": quitar los establecimientos y mostrar únicamente las presentadas | — |

### Errores confirmados

| # | Cambio | BD |
|---|---|---|
| 23 | **CRÍTICO** Se borran las actividades económicas al editar | — |
| 24 | **CRÍTICO** Se borra la información del formulario al editar | — |

---

## 3. Decisiones del cliente

Puntos que estaban abiertos y quedaron resueltos. Condicionan el diseño de la solución.

- **Alcance del RIT** — Es un módulo externo a Industria y Comercio. Contiene solo el
  formulario, donde se edita y actualiza. Los establecimientos viven en su propio módulo
  y allí ya no se descarga el certificado.
- **Datos divergentes del representante legal** — Se conserva el del establecimiento más
  reciente.
- **Matrículas** — Son dos campos distintos: la del RIT corresponde a la persona natural
  o jurídica; la del establecimiento es independiente.
- **Cese de actividades** — Lo registra únicamente el administrador. El contribuyente lo
  consulta en modo lectura y queda desmarcado, sin perder el acceso al sistema.
- **Creación del RIT** — Se genera automáticamente en el primer ingreso. Para los
  contribuyentes ya existentes se creará durante la migración, tomando los datos del
  establecimiento más reciente.
- **Respaldo previo** — Se hace copia completa de la base de producción antes de ejecutar
  la migración.

---

## 4. Plan de trabajo

Ordenado por dependencia y riesgo: primero lo que detiene la pérdida de información,
de último lo que altera la estructura de datos.

| Fase | Contenido | Duración | BD | Cambios |
|---|---|---|---|---|
| **0** | **Detener la pérdida de datos.** Corregir el borrado de actividades y de adjuntos. Cada edición que se haga hoy sigue destruyendo información real. | 1 día | No | 18, 23, 24 |
| **1** | **Navegación y presentación.** Reubicación y renombrado del RIT, botones, distinción activo/cerrado. | 1 día | No | 1, 2, 3, 6, 7, 12, 13 |
| **2** | **Archivos y certificado.** Consulta de archivos cargados y certificado completo. Los campos ya existen. | 2 días | No | 17, 19, 20 |
| **3** | **Flujo de declaraciones.** Quitar establecimientos, habilitar creación, filtrar solo presentadas. | 2 días | No | 21, 22 |
| **4** | **Separación RIT / establecimiento.** El cambio estructural. | 4-5 días | Sí | 4, 5, 8, 9, 10, 11, 14, 15, 16 |

---

## 5. Impacto en la base de datos

Solo la Fase 4 modifica la base. Criterio: tocar lo mínimo posible. **No se crean tablas
nuevas, no se elimina ninguna columna existente y las relaciones se mantienen intactas.**

### Columnas que se agregan a `ind_contribuyentes`

| Grupo | Columnas |
|---|---|
| Matrícula del contribuyente | `ind_Matricula`, `ind_FechaMatricula` |
| Representante legal | `ind_CedulaRepresentante`, `ind_NombreRepresentante`, `ind_EmailRepresentante`, `ind_TelefonoRepresentante`, `ind_DireccionRepresentante` |
| Contador | `ind_CedulaContador`, `ind_NombreContador`, `ind_TarjetaProfesional` |
| Revisor fiscal | `ind_CedulaRevisor`, `ind_NombreRevisor`, `ind_TarjetaProfesionalRevisor` |
| Actividad | `ind_FechaInicioActividades`, `ind_DireccionActividad` |
| Cese de actividades | `ind_FechaCese`, `ind_CausalCese`, `ind_ResolucionCese` |
| Anexos | `ind_Rut`, `ind_RutSegundo`, `ind_RutTercero`, `ind_RutaAnexos` |

Ya existen y se conservan: `ind_EmailContador`, `ind_EmailRevisor`.

### Relaciones entre tablas

No cambian. El dato que estaba repetido simplemente sube de nivel.

```
ind_contribuyentes   (1) ──< ind_establecimientos          (N)
ind_establecimientos (1) ──< ind_actividad_establecimiento (N)
```

Por qué el cambio: hoy el representante legal, la matrícula y el contador están guardados
**una vez por cada establecimiento**. Nada mantiene esas copias sincronizadas y ya
divergieron — el contribuyente 26 tiene 2 cédulas y 3 nombres distintos entre sus 6
establecimientos; el contribuyente 30 tiene 2 nombres distintos entre 2. Al subir el dato
a nivel de contribuyente queda una sola versión.

### Resguardos

| Medida | Motivo |
|---|---|
| Ninguna columna de `ind_establecimientos` se elimina | Permite revertir sin pérdida |
| Migración con guardas `IF NOT EXISTS` | Re-ejecutable sin efectos secundarios |
| Respaldo completo de producción antes de aplicar | Se manipula información real de contribuyentes |
| Informe de conflictos previo | Contribuyentes 26 y 30 se revisan antes |

La migración va en `BD/migraciones/`, siguiendo el formato ya establecido.

---

## 6. Fuera de este alcance

El **código de barras de recaudo bancario** se aborda una vez terminadas las cinco fases.
La estructura técnica ya está resuelta y documentada (ver
`erpsoftsas-ica-paipa-proyecto` en la memoria del proyecto); queda pendiente únicamente
que el banco entregue el número de convenio y la especificación de la referencia. Se
estima un día de trabajo a partir de ese momento.
