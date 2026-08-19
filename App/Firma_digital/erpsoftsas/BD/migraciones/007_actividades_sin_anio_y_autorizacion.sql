/* ============================================================================
   007 — Las actividades del RIT dejan de estar atadas a un año, y la
         autorizacion de notificaciones sube al contribuyente
   ----------------------------------------------------------------------------
   Reunion del 2026-08-19 con el cliente.

   1. ACTIVIDADES SIN AÑO

   La migracion 005 subio las actividades economicas del establecimiento al
   contribuyente, conservando el año con que venian. El cliente pidio quitarlo:
   "que no esten vinculadas por años, sino en general".

   Y tiene razon. El RIT es el registro VIGENTE de a que se dedica el
   contribuyente, no una bitacora año por año. El historico ya existe donde
   corresponde: cada declaracion guarda su propia copia de las actividades en
   ind_declaraciones_ica_actividades cuando se liquida, asi que preguntar "que
   actividades tenia en 2024" se responde mirando la declaracion de 2024.

   Se comprobo ademas que el año de esta tabla no lo usa nadie aguas abajo:
   solo servia para que la pantalla mostrara "las del año mas reciente".

   La columna atc_Anio NO se elimina -misma regla que en las migraciones
   anteriores: aqui no se borra nada-, solo pasa a admitir NULL y deja de
   escribirse. Los valores que ya tiene quedan como rastro de cuando se
   registro cada actividad.

   2. AUTORIZACION DE NOTIFICACIONES

   Vivia en el establecimiento (est_Autorizacion) y se pedia en CADA local, lo
   que no tiene sentido: es una manifestacion de la PERSONA, no del negocio.
   En la reunion del 18 se saco del formulario de establecimientos; esta
   migracion crea su lugar definitivo en el contribuyente, y el RIT pasa a
   exigirla para poder guardar.

   Re-ejecutable: guardas IF NOT EXISTS / IF COL_LENGTH en cada paso.
   ============================================================================ */

/* ---------------------------------------------------------------------------
   0. Candado
   --------------------------------------------------------------------------- */
DECLARE @candado INT;
EXEC @candado = sp_getapplock
     @Resource    = 'migracion_007_actividades_sin_anio',
     @LockMode    = 'Exclusive',
     @LockOwner   = 'Session',
     @LockTimeout = 120000;

IF @candado < 0
BEGIN
    RAISERROR('No se pudo tomar el candado de la migracion 007 (otra corrida en curso).', 16, 1);
    SET NOEXEC ON;
END
GO


/* ---------------------------------------------------------------------------
   1. Autorizacion de notificaciones en el contribuyente
   Mismo tipo que est_Autorizacion (INT) para que el traslado sea literal.
   --------------------------------------------------------------------------- */
IF COL_LENGTH('dbo.ind_contribuyentes', 'ind_Autorizacion') IS NULL
BEGIN
    ALTER TABLE dbo.ind_contribuyentes ADD ind_Autorizacion INT NULL;
    PRINT 'ind_Autorizacion agregada.';
END
ELSE PRINT 'ind_Autorizacion ya existia.';
GO

/* Relleno: si CUALQUIERA de sus establecimientos ya tenia la autorizacion
   marcada, se da por autorizado. Es una manifestacion de la persona, asi que
   basta que la haya dado una vez; obligarlo a repetirla seria pedirle dos
   veces lo mismo. */
UPDATE c
   SET c.ind_Autorizacion = 1
  FROM dbo.ind_contribuyentes c
 WHERE ISNULL(c.ind_Autorizacion, 0) = 0
   AND EXISTS (
        SELECT 1 FROM dbo.ind_establecimientos e
         WHERE e.est_IdContribuyente = c.ind_Id
           AND ISNULL(e.est_Autorizacion, 0) = 1
   );

PRINT 'Autorizacion trasladada desde los establecimientos.';
GO


/* ---------------------------------------------------------------------------
   2. Colapsar las actividades repetidas entre años
   Si un contribuyente tiene la misma actividad en 2024 y en 2026, pasa a tener
   una sola fila. Se conserva la del año MAS RECIENTE porque es la que refleja
   su situacion vigente, que es justo lo que el RIT debe mostrar.
   --------------------------------------------------------------------------- */
;WITH ordenadas AS (
    SELECT atc_Id,
           ROW_NUMBER() OVER (
               PARTITION BY atc_IdContribuyente, atc_IdCodigoActividad
               ORDER BY atc_Anio DESC, atc_Id DESC
           ) AS puesto
      FROM dbo.ind_actividad_contribuyente
)
DELETE FROM dbo.ind_actividad_contribuyente
 WHERE atc_Id IN (SELECT atc_Id FROM ordenadas WHERE puesto > 1);

PRINT 'Actividades repetidas entre años colapsadas.';
GO


/* ---------------------------------------------------------------------------
   3. El indice unico deja de incluir el año
   Hay que soltarlo ANTES de crear el nuevo: el viejo permite la misma
   actividad en años distintos, que es justo lo que ya no debe pasar.
   --------------------------------------------------------------------------- */
IF EXISTS (SELECT 1 FROM sys.indexes
            WHERE name = 'UX_actividad_contribuyente'
              AND object_id = OBJECT_ID('dbo.ind_actividad_contribuyente'))
BEGIN
    DROP INDEX UX_actividad_contribuyente ON dbo.ind_actividad_contribuyente;
    PRINT 'UX_actividad_contribuyente (con año) retirado.';
END
ELSE PRINT 'UX_actividad_contribuyente ya no estaba.';
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
                WHERE name = 'UX_actividad_contribuyente_sin_anio'
                  AND object_id = OBJECT_ID('dbo.ind_actividad_contribuyente'))
BEGIN
    CREATE UNIQUE INDEX UX_actividad_contribuyente_sin_anio
        ON dbo.ind_actividad_contribuyente (atc_IdContribuyente, atc_IdCodigoActividad);
    PRINT 'UX_actividad_contribuyente_sin_anio creado.';
END
ELSE PRINT 'UX_actividad_contribuyente_sin_anio ya existia.';
GO


/* ---------------------------------------------------------------------------
   4. atc_Anio pasa a admitir NULL
   No se elimina: los valores que ya tiene quedan como rastro de cuando se
   registro cada actividad. Simplemente deja de exigirse al insertar.
   --------------------------------------------------------------------------- */
IF EXISTS (SELECT 1 FROM sys.columns
            WHERE object_id = OBJECT_ID('dbo.ind_actividad_contribuyente')
              AND name = 'atc_Anio' AND is_nullable = 0)
BEGIN
    ALTER TABLE dbo.ind_actividad_contribuyente ALTER COLUMN atc_Anio INT NULL;
    PRINT 'atc_Anio pasa a admitir NULL.';
END
ELSE PRINT 'atc_Anio ya admitia NULL.';
GO


/* ---------------------------------------------------------------------------
   5. Informe
   --------------------------------------------------------------------------- */
SELECT
    actividades          = (SELECT COUNT(*) FROM dbo.ind_actividad_contribuyente),
    contribuyentes       = (SELECT COUNT(DISTINCT atc_IdContribuyente) FROM dbo.ind_actividad_contribuyente),
    repetidas            = (SELECT COUNT(*) FROM (
                                SELECT atc_IdContribuyente, atc_IdCodigoActividad
                                  FROM dbo.ind_actividad_contribuyente
                              GROUP BY atc_IdContribuyente, atc_IdCodigoActividad
                                HAVING COUNT(*) > 1) x),
    con_autorizacion     = (SELECT COUNT(*) FROM dbo.ind_contribuyentes WHERE ISNULL(ind_Autorizacion,0) = 1);
GO

/* "repetidas" tiene que ser 0: es lo que garantiza el indice nuevo. */
GO


/* ---------------------------------------------------------------------------
   6. Registro y liberacion del candado
   --------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '007_actividades_sin_anio_y_autorizacion')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('007_actividades_sin_anio_y_autorizacion',
            'Las actividades del RIT dejan de estar atadas a un año (el historico vive en cada declaracion) y la autorizacion de notificaciones sube del establecimiento al contribuyente. No se elimina ninguna columna: atc_Anio solo pasa a admitir NULL.');
GO

SET NOEXEC OFF;
EXEC sp_releaseapplock @Resource = 'migracion_007_actividades_sin_anio', @LockOwner = 'Session';
GO
