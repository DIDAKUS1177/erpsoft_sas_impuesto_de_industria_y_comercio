/* ============================================================================
   005 — Actividades economicas y codigos de RUT suben al contribuyente
   ----------------------------------------------------------------------------
   Puntos 6 y 11 de la reunion del 2026-08-18.

   El cliente pidio dos cosas que son el mismo movimiento: sacar del
   establecimiento lo que en realidad es de la PERSONA y dejarlo en el RIT.

     - Actividades economicas: hoy viven en ind_actividad_establecimiento,
       atadas a un local concreto. Pero la regla de negocio confirmada dice que
       la declaracion es UNA por contribuyente y agrega las actividades por
       codigo CIIU, asi que el dato ya se usaba a nivel de contribuyente
       -_consultarRIT() y el certificado las leen uniendo TODOS sus locales-.
       Que se guarden por local solo obliga a deducirlas cada vez, y permite
       que el mismo codigo aparezca repetido: en esta base, el contribuyente 30
       tiene el codigo 3 del año 2025 en sus dos establecimientos.

     - Codigos de RUT (est_Rut, est_Rut_segundo, est_Rut_tercero): son
       VARCHAR(6) con los codigos de actividad economica del RUT de la persona
       -valores reales: '1254', '2124', '4923'-. No son rutas de archivo pese
       al nombre. Estan copiados en cada local del mismo contribuyente: el 30
       tiene '1254' en sus dos establecimientos. Nada garantiza que las copias
       coincidan, que es el mismo problema que resolvio la migracion 003 con el
       representante legal y el contador.

   Criterio, igual que en 003 y 004: no se elimina NADA. Las columnas
   est_Rut* y la tabla ind_actividad_establecimiento quedan intactas, con sus
   datos, para poder revertir sin perdida. El codigo pasa a leer las nuevas.

   Re-ejecutable: guardas IF NOT EXISTS / IF COL_LENGTH en cada paso, y el
   relleno usa MERGE-por-NOT EXISTS, asi que correrla dos veces no duplica.
   ============================================================================ */

/* ---------------------------------------------------------------------------
   0. Candado
   Misma razon que en la 003: el archivo va separado por GO, asi que dos
   corridas simultaneas podrian entrelazar sus lotes. sp_getapplock con
   @LockOwner='Session' sobrevive a los GO; se libera al final.
   --------------------------------------------------------------------------- */
DECLARE @candado INT;
EXEC @candado = sp_getapplock
     @Resource   = 'migracion_005_actividades_y_rut',
     @LockMode   = 'Exclusive',
     @LockOwner  = 'Session',
     @LockTimeout = 120000;

IF @candado < 0
BEGIN
    RAISERROR('No se pudo tomar el candado de la migracion 005 (otra corrida en curso).', 16, 1);
    SET NOEXEC ON;
END
GO


/* ---------------------------------------------------------------------------
   1. Codigos de RUT en ind_contribuyentes
   Mismo tipo y tamaño que en el establecimiento, para que el traslado sea
   literal y no haya truncamiento.
   --------------------------------------------------------------------------- */
IF COL_LENGTH('dbo.ind_contribuyentes', 'ind_Rut') IS NULL
BEGIN
    ALTER TABLE dbo.ind_contribuyentes ADD ind_Rut VARCHAR(6) NULL;
    PRINT 'ind_Rut agregada.';
END
ELSE PRINT 'ind_Rut ya existia.';
GO

IF COL_LENGTH('dbo.ind_contribuyentes', 'ind_Rut_segundo') IS NULL
BEGIN
    ALTER TABLE dbo.ind_contribuyentes ADD ind_Rut_segundo VARCHAR(6) NULL;
    PRINT 'ind_Rut_segundo agregada.';
END
ELSE PRINT 'ind_Rut_segundo ya existia.';
GO

IF COL_LENGTH('dbo.ind_contribuyentes', 'ind_Rut_tercero') IS NULL
BEGIN
    ALTER TABLE dbo.ind_contribuyentes ADD ind_Rut_tercero VARCHAR(6) NULL;
    PRINT 'ind_Rut_tercero agregada.';
END
ELSE PRINT 'ind_Rut_tercero ya existia.';
GO


/* ---------------------------------------------------------------------------
   2. Relleno de los codigos de RUT
   Se toma, por cada contribuyente, el primer valor NO VACIO entre sus
   establecimientos, prefiriendo el local mas reciente (est_Id mayor): si dos
   copias divergieron, la ultima capturada es la que el usuario vio de ultimas.
   Solo se rellena lo que este vacio, para no pisar un dato ya corregido a mano
   si la migracion se vuelve a correr.
   --------------------------------------------------------------------------- */
;WITH rut_por_contribuyente AS (
    SELECT
        e.est_IdContribuyente,
        rut = (
            SELECT TOP 1 e2.est_Rut FROM dbo.ind_establecimientos e2
             WHERE e2.est_IdContribuyente = e.est_IdContribuyente
               AND LTRIM(RTRIM(ISNULL(e2.est_Rut, ''))) <> ''
             ORDER BY e2.est_Id DESC
        ),
        seg = (
            SELECT TOP 1 e2.est_Rut_segundo FROM dbo.ind_establecimientos e2
             WHERE e2.est_IdContribuyente = e.est_IdContribuyente
               AND LTRIM(RTRIM(ISNULL(e2.est_Rut_segundo, ''))) <> ''
             ORDER BY e2.est_Id DESC
        ),
        ter = (
            SELECT TOP 1 e2.est_Rut_tercero FROM dbo.ind_establecimientos e2
             WHERE e2.est_IdContribuyente = e.est_IdContribuyente
               AND LTRIM(RTRIM(ISNULL(e2.est_Rut_tercero, ''))) <> ''
             ORDER BY e2.est_Id DESC
        )
    FROM dbo.ind_establecimientos e
    GROUP BY e.est_IdContribuyente
)
UPDATE c
   SET c.ind_Rut         = CASE WHEN LTRIM(RTRIM(ISNULL(c.ind_Rut, '')))         = '' THEN r.rut ELSE c.ind_Rut END,
       c.ind_Rut_segundo = CASE WHEN LTRIM(RTRIM(ISNULL(c.ind_Rut_segundo, ''))) = '' THEN r.seg ELSE c.ind_Rut_segundo END,
       c.ind_Rut_tercero = CASE WHEN LTRIM(RTRIM(ISNULL(c.ind_Rut_tercero, ''))) = '' THEN r.ter ELSE c.ind_Rut_tercero END
  FROM dbo.ind_contribuyentes c
 INNER JOIN rut_por_contribuyente r ON r.est_IdContribuyente = c.ind_Id;

PRINT 'Codigos de RUT trasladados al contribuyente.';
GO


/* ---------------------------------------------------------------------------
   3. Tabla de actividades economicas del contribuyente
   Se crea tabla y no columnas por la misma razon que en la 004: el numero de
   actividades no esta fijado de antemano, y con columnas habria que inventar
   un tope y ampliarlo cada vez.
   --------------------------------------------------------------------------- */
IF OBJECT_ID('dbo.ind_actividad_contribuyente', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.ind_actividad_contribuyente (
        atc_Id                  INT IDENTITY(1,1) NOT NULL,
        atc_IdContribuyente     INT           NOT NULL,
        atc_IdCodigoActividad   INT           NOT NULL,
        atc_Anio                INT           NOT NULL,
        atc_FechaCreacion       DATETIME2     NOT NULL CONSTRAINT DF_atc_FechaCreacion DEFAULT (SYSDATETIME()),
        atc_FechaActualizacion  DATETIME2     NULL,
        CONSTRAINT PK_ind_actividad_contribuyente PRIMARY KEY (atc_Id)
    );
    PRINT 'ind_actividad_contribuyente creada.';
END
ELSE PRINT 'ind_actividad_contribuyente ya existia.';
GO

/* Indice UNICO: es lo que impide que vuelva a aparecer el mismo codigo dos
   veces para el mismo contribuyente y año, que es justo lo que pasaba al
   guardarlas por local. Guarda propia, no compartida con la del CREATE TABLE:
   si el CREATE fallo a medias, el indice no queda huerfano (lo mismo que se
   corrigio en la 004). */
IF OBJECT_ID('dbo.ind_actividad_contribuyente', 'U') IS NOT NULL
   AND NOT EXISTS (
        SELECT 1 FROM sys.indexes
         WHERE name = 'UX_actividad_contribuyente'
           AND object_id = OBJECT_ID('dbo.ind_actividad_contribuyente')
   )
BEGIN
    CREATE UNIQUE INDEX UX_actividad_contribuyente
        ON dbo.ind_actividad_contribuyente (atc_IdContribuyente, atc_IdCodigoActividad, atc_Anio);
    PRINT 'UX_actividad_contribuyente creado.';
END
ELSE PRINT 'UX_actividad_contribuyente ya existia o la tabla no esta.';
GO


/* ---------------------------------------------------------------------------
   4. Relleno de las actividades
   DISTINCT porque el mismo codigo puede venir repetido desde varios locales
   del mismo contribuyente -en esta base, el contribuyente 30 con el codigo 3
   del 2025-. El NOT EXISTS hace la insercion re-ejecutable.
   --------------------------------------------------------------------------- */
INSERT INTO dbo.ind_actividad_contribuyente
        (atc_IdContribuyente, atc_IdCodigoActividad, atc_Anio)
SELECT DISTINCT
        e.est_IdContribuyente,
        a.ace_IdCodigoActividad,
        a.ace_Anio
  FROM dbo.ind_actividad_establecimiento a
 INNER JOIN dbo.ind_establecimientos e ON e.est_Id = a.ace_IdEstablecimiento
 WHERE NOT EXISTS (
        SELECT 1 FROM dbo.ind_actividad_contribuyente x
         WHERE x.atc_IdContribuyente   = e.est_IdContribuyente
           AND x.atc_IdCodigoActividad = a.ace_IdCodigoActividad
           AND x.atc_Anio              = a.ace_Anio
 );

PRINT 'Actividades trasladadas al contribuyente.';
GO


/* ---------------------------------------------------------------------------
   5. Informe
   --------------------------------------------------------------------------- */
SELECT
    filas_origen       = (SELECT COUNT(*) FROM dbo.ind_actividad_establecimiento),
    filas_destino      = (SELECT COUNT(*) FROM dbo.ind_actividad_contribuyente),
    contribuyentes     = (SELECT COUNT(DISTINCT atc_IdContribuyente) FROM dbo.ind_actividad_contribuyente),
    con_rut_en_persona = (SELECT COUNT(*) FROM dbo.ind_contribuyentes
                           WHERE LTRIM(RTRIM(ISNULL(ind_Rut, ''))) <> '');
GO

/* filas_destino menor que filas_origen es lo ESPERADO cuando un contribuyente
   repetia el mismo codigo en varios locales: ahi esta el sentido del cambio. */
GO


/* ---------------------------------------------------------------------------
   6. Registro y liberacion del candado
   --------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '005_actividades_y_rut_a_contribuyente')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('005_actividades_y_rut_a_contribuyente',
            'Actividades economicas y codigos de RUT suben de establecimiento a contribuyente (puntos 6 y 11 de la reunion 2026-08-18). No se elimina nada: ind_actividad_establecimiento y est_Rut* quedan intactas.');
GO

SET NOEXEC OFF;
EXEC sp_releaseapplock @Resource = 'migracion_005_actividades_y_rut', @LockOwner = 'Session';
GO
