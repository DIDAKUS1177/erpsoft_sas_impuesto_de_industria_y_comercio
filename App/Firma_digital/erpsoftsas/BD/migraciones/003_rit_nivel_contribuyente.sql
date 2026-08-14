/* ============================================================================
   003 — El RIT pasa a vivir en el contribuyente, no en el establecimiento
   ----------------------------------------------------------------------------
   Contexto (cambios pedidos por el cliente el 2026-08-12, puntos 4, 8, 9, 10,
   11, 14, 15 y 16).

   Hasta ahora los datos que en realidad son DEL CONTRIBUYENTE -su matricula
   mercantil, su representante legal, su contador y su revisor fiscal- estaban
   guardados en ind_establecimientos, es decir, repetidos en cada local. Un
   contribuyente con tres establecimientos tenia tres copias de su contador, y
   nada garantizaba que coincidieran.

   Esta migracion los sube al nivel que les corresponde. Los de
   ind_establecimientos NO se borran: siguen ahi, intactos, porque el codigo
   viejo todavia los lee y porque son el respaldo si algo del relleno
   automatico sale mal. Limpiarlos es una decision posterior y aparte.

   La matricula queda DOBLE a proposito, no por descuido: ind_Matricula es la
   de la persona natural o juridica y est_Matricula es la del establecimiento.
   El cliente confirmo que son dos matriculas distintas (punto 8).

   Re-ejecutable: cada columna se crea solo si falta, y el relleno solo toca
   filas que esten en NULL. Correrla dos veces no cambia nada la segunda vez.

   IMPORTANTE: hacer respaldo de la base antes de correrla en produccion.
   ============================================================================ */


/* ---------------------------------------------------------------------------
   1. Matricula mercantil de la PERSONA (punto 8)
   --------------------------------------------------------------------------- */
IF COL_LENGTH('dbo.ind_contribuyentes', 'ind_Matricula') IS NULL
BEGIN
    ALTER TABLE dbo.ind_contribuyentes ADD ind_Matricula VARCHAR(50) NULL;
    PRINT 'ind_Matricula agregada.';
END
ELSE PRINT 'ind_Matricula ya existia.';
GO

IF COL_LENGTH('dbo.ind_contribuyentes', 'ind_Fecha_matricula') IS NULL
BEGIN
    ALTER TABLE dbo.ind_contribuyentes ADD ind_Fecha_matricula DATETIME NULL;
    PRINT 'ind_Fecha_matricula agregada.';
END
ELSE PRINT 'ind_Fecha_matricula ya existia.';
GO


/* ---------------------------------------------------------------------------
   2. Datos generales del RIT (punto 4)
   --------------------------------------------------------------------------- */
IF COL_LENGTH('dbo.ind_contribuyentes', 'ind_Fecha_inicio') IS NULL
BEGIN
    ALTER TABLE dbo.ind_contribuyentes ADD ind_Fecha_inicio DATETIME NULL;
    PRINT 'ind_Fecha_inicio agregada.';
END
ELSE PRINT 'ind_Fecha_inicio ya existia.';
GO

IF COL_LENGTH('dbo.ind_contribuyentes', 'ind_Ind_camara_comercio') IS NULL
BEGIN
    ALTER TABLE dbo.ind_contribuyentes ADD ind_Ind_camara_comercio INT NULL;
    PRINT 'ind_Ind_camara_comercio agregada.';
END
ELSE PRINT 'ind_Ind_camara_comercio ya existia.';
GO


/* ---------------------------------------------------------------------------
   3. Representante legal (punto 4)
   El cliente confirmo que es UNO solo por contribuyente.
   --------------------------------------------------------------------------- */
IF COL_LENGTH('dbo.ind_contribuyentes', 'ind_Cedula_representante') IS NULL
BEGIN
    ALTER TABLE dbo.ind_contribuyentes ADD ind_Cedula_representante VARCHAR(20) NULL;
    PRINT 'ind_Cedula_representante agregada.';
END
ELSE PRINT 'ind_Cedula_representante ya existia.';
GO

IF COL_LENGTH('dbo.ind_contribuyentes', 'ind_Nombre_representante') IS NULL
BEGIN
    ALTER TABLE dbo.ind_contribuyentes ADD ind_Nombre_representante VARCHAR(100) NULL;
    PRINT 'ind_Nombre_representante agregada.';
END
ELSE PRINT 'ind_Nombre_representante ya existia.';
GO

IF COL_LENGTH('dbo.ind_contribuyentes', 'ind_Email_representante') IS NULL
BEGIN
    ALTER TABLE dbo.ind_contribuyentes ADD ind_Email_representante VARCHAR(150) NULL;
    PRINT 'ind_Email_representante agregada.';
END
ELSE PRINT 'ind_Email_representante ya existia.';
GO


/* ---------------------------------------------------------------------------
   4. Contador y revisor fiscal (puntos 14, 15, 16)
   ----------------------------------------------------------------------------
   CORREGIDO 2026-08-14 (encontrado en el chequeo exhaustivo de esta sesion):
   la primera version de esta seccion creaba ind_Cedula_contador,
   ind_Nombre_contador, ind_Tarjeta_profesional, ind_Cedula_revisor,
   ind_Nombre_revisor, ind_Tarjeta_profesional_revisor -un juego de columnas
   PARALELO, con otro nombre, a uno que ya existe en produccion desde
   migracion_2026-08_contribuyente.sql BLOQUE 4 (aplicada en Paipa el
   2026-08-04): ind_NombreContador, ind_CedulaContador,
   ind_TarjetaProfContador, ind_NombreRevisor, ind_CedulaRevisor,
   ind_TarjetaProfRevisor. declaracion.php y microservicios/firmas/api.php
   leen EXCLUSIVAMENTE ese juego viejo. Si esta migracion hubiera creado el
   juego nuevo en produccion, cualquier edicion de contador/revisor hecha
   desde el RIT se habria guardado en columnas que el PDF y el microservicio
   de firma nunca leen -una divergencia silenciosa entre lo que el
   contribuyente ve y lo que de verdad aplica para su firma tributaria-.
   Esta migracion NUNCA se desplego a ningun lado (solo corrio en la copia
   local de pruebas), asi que se corrige aqui mismo en vez de arrastrar el
   error con una migracion aparte.

   Los correos (ind_EmailContador / ind_EmailRevisor) no cambian: esos ya
   estaban bien -mismo nombre en las dos migraciones- desde el principio.
   --------------------------------------------------------------------------- */
IF COL_LENGTH('dbo.ind_contribuyentes', 'ind_CedulaContador') IS NULL
BEGIN
    ALTER TABLE dbo.ind_contribuyentes ADD ind_CedulaContador VARCHAR(20) NULL;
    PRINT 'ind_CedulaContador agregada.';
END
ELSE PRINT 'ind_CedulaContador ya existia.';
GO

IF COL_LENGTH('dbo.ind_contribuyentes', 'ind_NombreContador') IS NULL
BEGIN
    ALTER TABLE dbo.ind_contribuyentes ADD ind_NombreContador VARCHAR(100) NULL;
    PRINT 'ind_NombreContador agregada.';
END
ELSE PRINT 'ind_NombreContador ya existia.';
GO

IF COL_LENGTH('dbo.ind_contribuyentes', 'ind_TarjetaProfContador') IS NULL
BEGIN
    ALTER TABLE dbo.ind_contribuyentes ADD ind_TarjetaProfContador VARCHAR(50) NULL;
    PRINT 'ind_TarjetaProfContador agregada.';
END
ELSE PRINT 'ind_TarjetaProfContador ya existia.';
GO

IF COL_LENGTH('dbo.ind_contribuyentes', 'ind_CedulaRevisor') IS NULL
BEGIN
    ALTER TABLE dbo.ind_contribuyentes ADD ind_CedulaRevisor VARCHAR(20) NULL;
    PRINT 'ind_CedulaRevisor agregada.';
END
ELSE PRINT 'ind_CedulaRevisor ya existia.';
GO

IF COL_LENGTH('dbo.ind_contribuyentes', 'ind_NombreRevisor') IS NULL
BEGIN
    ALTER TABLE dbo.ind_contribuyentes ADD ind_NombreRevisor VARCHAR(100) NULL;
    PRINT 'ind_NombreRevisor agregada.';
END
ELSE PRINT 'ind_NombreRevisor ya existia.';
GO

IF COL_LENGTH('dbo.ind_contribuyentes', 'ind_TarjetaProfRevisor') IS NULL
BEGIN
    ALTER TABLE dbo.ind_contribuyentes ADD ind_TarjetaProfRevisor VARCHAR(50) NULL;
    PRINT 'ind_TarjetaProfRevisor agregada.';
END
ELSE PRINT 'ind_TarjetaProfRevisor ya existia.';
GO

/* Correccion de bases que ya corrieron la version anterior (con el nombre
   equivocado): se copia el dato a la columna correcta -solo si esta vacia,
   para no pisar nada que ya se haya corregido a mano- y se retiran las
   columnas equivocadas. Si esas columnas nunca existieron aqui (caso normal
   en produccion, que nunca corrio la version con el error), este bloque no
   hace nada. */
IF COL_LENGTH('dbo.ind_contribuyentes', 'ind_Nombre_contador') IS NOT NULL
BEGIN
    UPDATE dbo.ind_contribuyentes SET
        ind_CedulaContador      = COALESCE(ind_CedulaContador,      ind_Cedula_contador),
        ind_NombreContador      = COALESCE(ind_NombreContador,      ind_Nombre_contador),
        ind_TarjetaProfContador = COALESCE(ind_TarjetaProfContador, ind_Tarjeta_profesional),
        ind_CedulaRevisor       = COALESCE(ind_CedulaRevisor,       ind_Cedula_revisor),
        ind_NombreRevisor       = COALESCE(ind_NombreRevisor,       ind_Nombre_revisor),
        ind_TarjetaProfRevisor  = COALESCE(ind_TarjetaProfRevisor,  ind_Tarjeta_profesional_revisor);

    ALTER TABLE dbo.ind_contribuyentes
        DROP COLUMN ind_Cedula_contador, ind_Nombre_contador, ind_Tarjeta_profesional,
                    ind_Cedula_revisor, ind_Nombre_revisor, ind_Tarjeta_profesional_revisor;

    PRINT 'Columnas equivocadas de una corrida anterior: datos trasladados y columnas retiradas.';
END
ELSE PRINT 'No habia columnas equivocadas que corregir.';
GO


/* ---------------------------------------------------------------------------
   5. Marca de cuando se inicializo el RIT (punto 10)
   El RIT no es un registro nuevo: es el propio contribuyente, que ya existe
   desde la inscripcion. Lo unico que hacia falta era dejar constancia de
   cuando el sistema lo dio por inicializado, para no volver a hacerlo y para
   poder auditarlo despues.
   --------------------------------------------------------------------------- */
IF COL_LENGTH('dbo.ind_contribuyentes', 'ind_RIT_FechaCreacion') IS NULL
BEGIN
    ALTER TABLE dbo.ind_contribuyentes ADD ind_RIT_FechaCreacion DATETIME NULL;
    PRINT 'ind_RIT_FechaCreacion agregada.';
END
ELSE PRINT 'ind_RIT_FechaCreacion ya existia.';
GO


/* ============================================================================
   6. RELLENO desde los establecimientos
   ----------------------------------------------------------------------------
   Se sube el dato que ya existe, pero SOLO cuando no hay ambiguedad: si los
   establecimientos de un mismo contribuyente traen valores distintos para el
   mismo campo, no hay forma de saber cual es el bueno, asi que la columna
   queda en NULL y el caso sale listado en el informe del final para que
   alguien lo resuelva a mano.

   Tambien se ignoran las cadenas vacias y las de solo espacios: en esta base
   hay de las dos, y tomarlas como valor bueno taparia el dato real de otro
   establecimiento del mismo contribuyente.
   ============================================================================ */

/* Un solo paso por campo. NULLIF(LTRIM(RTRIM(x)),'') convierte '' y '   ' en
   NULL para que MIN() y COUNT(DISTINCT) los ignoren. */
;WITH valores AS (
    SELECT
        est_IdContribuyente AS ind_Id,
        MIN(NULLIF(LTRIM(RTRIM(est_Matricula)), ''))              AS matricula,
        COUNT(DISTINCT NULLIF(LTRIM(RTRIM(est_Matricula)), ''))   AS n_matricula,
        MIN(est_Ind_camara_comercio)                              AS camara,
        COUNT(DISTINCT est_Ind_camara_comercio)                   AS n_camara,
        MIN(NULLIF(LTRIM(RTRIM(est_Cedula_representante)), ''))            AS ced_rep,
        COUNT(DISTINCT NULLIF(LTRIM(RTRIM(est_Cedula_representante)), '')) AS n_ced_rep,
        MIN(NULLIF(LTRIM(RTRIM(est_Nombre_representante)), ''))            AS nom_rep,
        COUNT(DISTINCT NULLIF(LTRIM(RTRIM(est_Nombre_representante)), '')) AS n_nom_rep,
        MIN(NULLIF(LTRIM(RTRIM(est_Email_representante)), ''))             AS mail_rep,
        COUNT(DISTINCT NULLIF(LTRIM(RTRIM(est_Email_representante)), ''))  AS n_mail_rep,
        MIN(NULLIF(LTRIM(RTRIM(est_Cedula_contador)), ''))            AS ced_cont,
        COUNT(DISTINCT NULLIF(LTRIM(RTRIM(est_Cedula_contador)), '')) AS n_ced_cont,
        MIN(NULLIF(LTRIM(RTRIM(est_Nombre_contador)), ''))            AS nom_cont,
        COUNT(DISTINCT NULLIF(LTRIM(RTRIM(est_Nombre_contador)), '')) AS n_nom_cont,
        MIN(NULLIF(LTRIM(RTRIM(est_Tarjeta_profesional)), ''))            AS tp_cont,
        COUNT(DISTINCT NULLIF(LTRIM(RTRIM(est_Tarjeta_profesional)), '')) AS n_tp_cont,
        MIN(NULLIF(LTRIM(RTRIM(est_Cedula_revisor)), ''))            AS ced_rev,
        COUNT(DISTINCT NULLIF(LTRIM(RTRIM(est_Cedula_revisor)), '')) AS n_ced_rev,
        MIN(NULLIF(LTRIM(RTRIM(est_Nombre_revisor)), ''))            AS nom_rev,
        COUNT(DISTINCT NULLIF(LTRIM(RTRIM(est_Nombre_revisor)), '')) AS n_nom_rev,
        MIN(NULLIF(LTRIM(RTRIM(est_Tarjeta_profesional_revisor)), ''))            AS tp_rev,
        COUNT(DISTINCT NULLIF(LTRIM(RTRIM(est_Tarjeta_profesional_revisor)), '')) AS n_tp_rev
    FROM dbo.ind_establecimientos
    GROUP BY est_IdContribuyente
)
UPDATE c SET
    /* Solo se rellena si la columna esta vacia Y el valor de origen es unico. */
    c.ind_Matricula                    = CASE WHEN v.n_matricula  = 1 THEN COALESCE(c.ind_Matricula,  v.matricula) ELSE c.ind_Matricula  END,
    c.ind_Ind_camara_comercio          = CASE WHEN v.n_camara     = 1 THEN COALESCE(c.ind_Ind_camara_comercio, v.camara) ELSE c.ind_Ind_camara_comercio END,
    c.ind_Cedula_representante         = CASE WHEN v.n_ced_rep    = 1 THEN COALESCE(c.ind_Cedula_representante, v.ced_rep)  ELSE c.ind_Cedula_representante END,
    c.ind_Nombre_representante         = CASE WHEN v.n_nom_rep    = 1 THEN COALESCE(c.ind_Nombre_representante, v.nom_rep)  ELSE c.ind_Nombre_representante END,
    c.ind_Email_representante          = CASE WHEN v.n_mail_rep   = 1 THEN COALESCE(c.ind_Email_representante,  v.mail_rep) ELSE c.ind_Email_representante  END,
    c.ind_CedulaContador               = CASE WHEN v.n_ced_cont   = 1 THEN COALESCE(c.ind_CedulaContador, v.ced_cont) ELSE c.ind_CedulaContador END,
    c.ind_NombreContador               = CASE WHEN v.n_nom_cont   = 1 THEN COALESCE(c.ind_NombreContador, v.nom_cont) ELSE c.ind_NombreContador END,
    c.ind_TarjetaProfContador          = CASE WHEN v.n_tp_cont    = 1 THEN COALESCE(c.ind_TarjetaProfContador, v.tp_cont) ELSE c.ind_TarjetaProfContador END,
    c.ind_CedulaRevisor                = CASE WHEN v.n_ced_rev    = 1 THEN COALESCE(c.ind_CedulaRevisor, v.ced_rev) ELSE c.ind_CedulaRevisor END,
    c.ind_NombreRevisor                = CASE WHEN v.n_nom_rev    = 1 THEN COALESCE(c.ind_NombreRevisor, v.nom_rev) ELSE c.ind_NombreRevisor END,
    c.ind_TarjetaProfRevisor           = CASE WHEN v.n_tp_rev     = 1 THEN COALESCE(c.ind_TarjetaProfRevisor, v.tp_rev) ELSE c.ind_TarjetaProfRevisor END
FROM dbo.ind_contribuyentes c
INNER JOIN valores v ON v.ind_Id = c.ind_Id;
GO

/* La fecha de matricula y la de inicio se suben aparte: son DATETIME y en esta
   base hay fechas centinela 1900-01-01 que no significan "matriculado en 1900"
   sino "nunca se lleno". Subirlas tal cual ensuciaria el RIT. */
;WITH fechas AS (
    SELECT
        est_IdContribuyente AS ind_Id,
        MIN(NULLIF(est_Fecha_matricula, '1900-01-01'))            AS f_matricula,
        COUNT(DISTINCT NULLIF(est_Fecha_matricula, '1900-01-01')) AS n_matricula,
        MIN(NULLIF(est_Fecha_inicio, '1900-01-01'))               AS f_inicio,
        COUNT(DISTINCT NULLIF(est_Fecha_inicio, '1900-01-01'))    AS n_inicio
    FROM dbo.ind_establecimientos
    GROUP BY est_IdContribuyente
)
UPDATE c SET
    c.ind_Fecha_matricula = CASE WHEN f.n_matricula = 1 THEN COALESCE(c.ind_Fecha_matricula, f.f_matricula) ELSE c.ind_Fecha_matricula END,
    c.ind_Fecha_inicio    = CASE WHEN f.n_inicio    = 1 THEN COALESCE(c.ind_Fecha_inicio,    f.f_inicio)    ELSE c.ind_Fecha_inicio    END
FROM dbo.ind_contribuyentes c
INNER JOIN fechas f ON f.ind_Id = c.ind_Id;
GO


/* ---------------------------------------------------------------------------
   7. Registro de la migracion
   --------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '003_rit_nivel_contribuyente')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('003_rit_nivel_contribuyente',
            'Matricula, representante legal, contador y revisor suben de ind_establecimientos a ind_contribuyentes. Las columnas del establecimiento NO se borran.');
GO


/* ============================================================================
   8. INFORME — casos que quedaron sin resolver
   ----------------------------------------------------------------------------
   Estos contribuyentes tienen establecimientos que NO coinciden entre si en
   algun campo, asi que el relleno automatico los dejo en blanco a proposito.
   Hay que decidir a mano cual es el dato bueno y cargarlo en el RIT.
   ============================================================================ */
SELECT
    e.est_IdContribuyente                                                        AS contribuyente,
    COUNT(*)                                                                     AS establecimientos,
    COUNT(DISTINCT NULLIF(LTRIM(RTRIM(e.est_Matricula)), ''))                    AS matriculas_distintas,
    COUNT(DISTINCT e.est_Ind_camara_comercio)                                    AS camara_comercio_distintos,
    COUNT(DISTINCT NULLIF(LTRIM(RTRIM(e.est_Cedula_representante)), ''))         AS cedulas_representante_distintas,
    COUNT(DISTINCT NULLIF(LTRIM(RTRIM(e.est_Nombre_representante)), ''))         AS representantes_distintos,
    COUNT(DISTINCT NULLIF(LTRIM(RTRIM(e.est_Email_representante)), ''))          AS correos_representante_distintos,
    COUNT(DISTINCT NULLIF(LTRIM(RTRIM(e.est_Cedula_contador)), ''))              AS cedulas_contador_distintas,
    COUNT(DISTINCT NULLIF(LTRIM(RTRIM(e.est_Nombre_contador)), ''))              AS contadores_distintos,
    COUNT(DISTINCT NULLIF(LTRIM(RTRIM(e.est_Tarjeta_profesional)), ''))          AS tarjetas_contador_distintas,
    COUNT(DISTINCT NULLIF(LTRIM(RTRIM(e.est_Cedula_revisor)), ''))               AS cedulas_revisor_distintas,
    COUNT(DISTINCT NULLIF(LTRIM(RTRIM(e.est_Nombre_revisor)), ''))               AS revisores_distintos,
    COUNT(DISTINCT NULLIF(LTRIM(RTRIM(e.est_Tarjeta_profesional_revisor)), ''))  AS tarjetas_revisor_distintas
FROM dbo.ind_establecimientos e
GROUP BY e.est_IdContribuyente
HAVING COUNT(DISTINCT NULLIF(LTRIM(RTRIM(e.est_Matricula)), ''))                    > 1
    OR COUNT(DISTINCT e.est_Ind_camara_comercio)                                    > 1
    OR COUNT(DISTINCT NULLIF(LTRIM(RTRIM(e.est_Cedula_representante)), ''))         > 1
    OR COUNT(DISTINCT NULLIF(LTRIM(RTRIM(e.est_Nombre_representante)), ''))         > 1
    OR COUNT(DISTINCT NULLIF(LTRIM(RTRIM(e.est_Email_representante)), ''))          > 1
    OR COUNT(DISTINCT NULLIF(LTRIM(RTRIM(e.est_Cedula_contador)), ''))              > 1
    OR COUNT(DISTINCT NULLIF(LTRIM(RTRIM(e.est_Nombre_contador)), ''))              > 1
    OR COUNT(DISTINCT NULLIF(LTRIM(RTRIM(e.est_Tarjeta_profesional)), ''))          > 1
    OR COUNT(DISTINCT NULLIF(LTRIM(RTRIM(e.est_Cedula_revisor)), ''))               > 1
    OR COUNT(DISTINCT NULLIF(LTRIM(RTRIM(e.est_Nombre_revisor)), ''))               > 1
    OR COUNT(DISTINCT NULLIF(LTRIM(RTRIM(e.est_Tarjeta_profesional_revisor)), ''))  > 1
ORDER BY e.est_IdContribuyente;
GO
