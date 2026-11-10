/* ============================================================================
   056 — Informe general (informes dinámicos): plantillas guardadas y permisos
   ----------------------------------------------------------------------------
   Pedido de la Alcaldía de Paipa (2026-11-09, definido con Diego): un informe
   como el "Informe general" de Gestión Predial, donde la persona elige qué
   filas trae (una por declaración, o solo contribuyentes), los filtros, las
   columnas, su orden y el título de cada una, y guarda sus plantillas.

   Lo que se elige se decide en el catálogo del servidor (business/
   class.informeCatalogo.php): el navegador solo manda claves y valores, nunca
   nombres de tabla ni de columna. Esta migración solo trae lo que SÍ es dato.

   QUÉ HACE
   1. ind_informe_plantillas: las plantillas que guardan las personas. Las del
      SISTEMA no están aquí: viven en el código (inmutables, y una mejora llega
      sin migración). Cada municipio tiene su tabla, así que "compartida" es
      compartida con quienes usan el Informe general en ese municipio.
        ipl_Visibilidad  PRIVADA (solo su dueño) | MUNICIPIO (la ven todos)
        ipl_Config       la configuración en JSON (versión en ipl_VersionConfig)
        ipl_Activo       borrar es lógico; el índice único solo cuenta las activas
      El JSON se valida en PHP y no con CHECK (ISJSON): ISJSON pide nivel de
      compatibilidad 130 y no se sabe el de cada servidor.
      El nombre es único entre las plantillas activas del municipio (UX_ipl_nombre).
   2. Tres permisos en el grupo "Alcaldía" del panel de Roles (necesita la 040,
      que es la que crea el catálogo; sin ella la migración avisa y no se registra):
        alcaldia.informes.personalizados  Usar el Informe general
        alcaldia.informes.plantillas      Compartir plantillas del Informe general
        alcaldia.informes.contacto        Incluir datos de contacto en el Informe general
      El Administrador los tiene siempre; los demás roles, si se prenden. No se
      reparten solos a ningún rol (como la 044 y la 045): son datos personales.

   El código funciona SIN esta migración: el Informe general se usa con las
   plantillas del sistema y se avisa que guardar plantillas aún no está
   disponible.

   RIESGO: bajo. Una tabla y tres permisos nuevos; no toca nada existente.
   Re-ejecutable.
   ============================================================================ */

SET NOCOUNT ON;
GO

DECLARE @candado INT;
EXEC @candado = sp_getapplock
     @Resource    = 'migracion_056_informes_plantillas',
     @LockMode    = 'Exclusive',
     @LockOwner   = 'Session',
     @LockTimeout = 120000;

IF @candado < 0
BEGIN
    RAISERROR('No se pudo tomar el candado de la migracion 056 (otra corrida en curso).', 16, 1);
    SET NOEXEC ON;
END
GO

-- Los índices filtrados exigen estas dos opciones al crearse (error 1934 si no).
SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
GO

/* 1. Las plantillas guardadas -------------------------------------------------- */
IF OBJECT_ID('dbo.ind_informe_plantillas', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.ind_informe_plantillas (
        ipl_Id                 INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_ind_informe_plantillas PRIMARY KEY,
        ipl_Nombre             NVARCHAR(100)  NOT NULL,
        ipl_Descripcion        NVARCHAR(300)  NULL,
        ipl_Fuente             VARCHAR(20)    NOT NULL,   -- ica | reteica | autorreteica | contribuyentes
        ipl_Config             NVARCHAR(MAX)  NOT NULL,   -- JSON: filas, filtros, columnas con su título y orden
        ipl_VersionConfig      SMALLINT       NOT NULL CONSTRAINT DF_ipl_VersionConfig DEFAULT (1),
        ipl_Visibilidad        VARCHAR(10)    NOT NULL CONSTRAINT DF_ipl_Visibilidad DEFAULT ('PRIVADA'),
        ipl_Activo             BIT            NOT NULL CONSTRAINT DF_ipl_Activo DEFAULT (1),
        ipl_IdUsuario          INT            NOT NULL,   -- el dueño (conf_usuarios, sin FK: como el resto del repo)
        ipl_FechaCreacion      DATETIME2(0)   NOT NULL CONSTRAINT DF_ipl_FechaCreacion DEFAULT (GETDATE()),
        ipl_FechaActualizacion DATETIME2(0)   NULL,
        ipl_IdUsuarioActualizo INT            NULL,
        CONSTRAINT CK_ipl_Visibilidad CHECK (ipl_Visibilidad IN ('PRIVADA', 'MUNICIPIO'))
    );
    PRINT '  + ind_informe_plantillas';
END
GO

-- El nombre es ÚNICO en el municipio (esta base es de uno solo): entre las activas, privadas y compartidas
-- de cualquier persona. Así dos «Cartera» nunca se confunden, y una plantilla compartida no choca con una
-- privada de otro (todas se nombran igual para todos). La comparación usa la intercalación de la base
-- (sin distinguir mayúsculas), la misma con la que el código revisa el nombre antes de guardar.
-- Los dos índices que traía la primera versión de esta migración (por dueño y entre compartidas) quedan
-- incluidos en este y se quitan si existen.
IF OBJECT_ID('dbo.ind_informe_plantillas', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_ipl_nombre' AND object_id = OBJECT_ID('dbo.ind_informe_plantillas'))
BEGIN
    IF EXISTS (SELECT 1 FROM dbo.ind_informe_plantillas WHERE ipl_Activo = 1 GROUP BY ipl_Nombre HAVING COUNT(*) > 1)
    BEGIN
        -- No se renombra nada solo: la persona decide cuál conserva su nombre.
        RAISERROR('056: hay plantillas activas con el mismo nombre en ind_informe_plantillas. Renombre o borre las repetidas y vuelva a aplicar la 056.', 16, 1);
        SET NOEXEC ON;
    END
END
GO

IF OBJECT_ID('dbo.ind_informe_plantillas', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_ipl_nombre' AND object_id = OBJECT_ID('dbo.ind_informe_plantillas'))
BEGIN
    CREATE UNIQUE INDEX UX_ipl_nombre
        ON dbo.ind_informe_plantillas (ipl_Nombre)
     WHERE ipl_Activo = 1;
    PRINT '  + UX_ipl_nombre';
END
GO

IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_ipl_nombre_dueno' AND object_id = OBJECT_ID('dbo.ind_informe_plantillas'))
    DROP INDEX UX_ipl_nombre_dueno ON dbo.ind_informe_plantillas;
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_ipl_nombre_compartida' AND object_id = OBJECT_ID('dbo.ind_informe_plantillas'))
    DROP INDEX UX_ipl_nombre_compartida ON dbo.ind_informe_plantillas;
GO

/* 2. Los permisos (solo si existe el catálogo de la 040) ----------------------- */
IF COL_LENGTH('dbo.conf_submodulo', 'subMod_Clave') IS NOT NULL
   AND EXISTS (SELECT 1 FROM dbo.conf_modulo WHERE mod_Clave = 'alcaldia')
BEGIN
    DECLARE @modulo INT, @orden INT;
    SELECT @modulo = mod_Id FROM dbo.conf_modulo WHERE mod_Clave = 'alcaldia';
    -- El siguiente número libre del grupo: otras migraciones también agregan permisos a "Alcaldía".
    SELECT @orden = ISNULL(MAX(subMod_Orden), 0) FROM dbo.conf_submodulo WHERE subMod_IdModulo = @modulo;

    IF NOT EXISTS (SELECT 1 FROM dbo.conf_submodulo WHERE subMod_Clave = 'alcaldia.informes.personalizados')
    BEGIN
        SET @orden = @orden + 1;
        INSERT INTO dbo.conf_submodulo (subMod_Nombre, subMod_Descripcion, subMod_IdModulo, subMod_Clave, subMod_Orden)
        VALUES (N'Usar el Informe general',
                N'Arma informes con las columnas, los filtros y el orden que elija sobre las declaraciones de ICA, retención y autorretención o sobre los contribuyentes; los ve en pantalla y los descarga en Excel. Cada fuente pide además el permiso de ver ese impuesto (o de ver contribuyentes). Se usa junto al Informe rápido.',
                @modulo, 'alcaldia.informes.personalizados', @orden);
    END

    IF NOT EXISTS (SELECT 1 FROM dbo.conf_submodulo WHERE subMod_Clave = 'alcaldia.informes.plantillas')
    BEGIN
        SET @orden = @orden + 1;
        INSERT INTO dbo.conf_submodulo (subMod_Nombre, subMod_Descripcion, subMod_IdModulo, subMod_Clave, subMod_Orden)
        VALUES (N'Compartir plantillas del Informe general',
                N'Guarda plantillas que ven todos los que usan el Informe general en el municipio, y modifica o borra las compartidas de otras personas. Sin este permiso cada quien guarda solo plantillas propias y privadas.',
                @modulo, 'alcaldia.informes.plantillas', @orden);
    END

    IF NOT EXISTS (SELECT 1 FROM dbo.conf_submodulo WHERE subMod_Clave = 'alcaldia.informes.contacto')
    BEGIN
        SET @orden = @orden + 1;
        INSERT INTO dbo.conf_submodulo (subMod_Nombre, subMod_Descripcion, subMod_IdModulo, subMod_Clave, subMod_Orden)
        VALUES (N'Incluir datos de contacto en el Informe general',
                N'Permite incluir en los informes el correo, el teléfono y la dirección del contribuyente y los datos del representante legal, el contador y el revisor fiscal. Son datos personales (Ley 1581 de 2012): conviene darlo solo a quien los necesita.',
                @modulo, 'alcaldia.informes.contacto', @orden);
    END
    PRINT '  + permisos alcaldia.informes.personalizados / .plantillas / .contacto';
END
ELSE
BEGIN
    -- Sin el catálogo de la 040 no hay dónde crear los permisos. Registrarse así dejaría la 056 "aplicada"
    -- sin ellos y el aplicador no volvería a correrla: mejor un error visible y sin registro; la tabla de
    -- arriba ya quedó creada y, al aplicar la 040, esta se vuelve a aplicar sin riesgo (es re-ejecutable).
    RAISERROR('056: falta la migracion 040 (catalogo de permisos de los roles). Aplique la 040 y despues vuelva a aplicar la 056.', 16, 1);
    SET NOEXEC ON;
END
GO

/* 3. Registro y liberación del candado ------------------------------------------ */
IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '056_informes_plantillas')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('056_informes_plantillas',
            N'Informe general: tabla ind_informe_plantillas (nombre unico por municipio; privadas o del municipio) y permisos alcaldia.informes.personalizados, .plantillas y .contacto. Las plantillas del sistema viven en el codigo.');
GO

SET NOEXEC OFF;
EXEC sp_releaseapplock @Resource = 'migracion_056_informes_plantillas', @LockOwner = 'Session';
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRÁS

       DELETE p FROM dbo.conf_permisos p
         JOIN dbo.conf_submodulo s ON s.subMod_Id = p.per_IdSubmodulo
        WHERE s.subMod_Clave IN ('alcaldia.informes.personalizados', 'alcaldia.informes.plantillas', 'alcaldia.informes.contacto');
       DELETE FROM dbo.conf_submodulo
        WHERE subMod_Clave IN ('alcaldia.informes.personalizados', 'alcaldia.informes.plantillas', 'alcaldia.informes.contacto');
       DROP TABLE dbo.ind_informe_plantillas;
       DELETE FROM dbo.conf_migraciones WHERE mig_Nombre = '056_informes_plantillas';

   Pierde las plantillas guardadas. El código sigue funcionando: sin la tabla
   solo ofrece las plantillas del sistema.
   ---------------------------------------------------------------------------- */
