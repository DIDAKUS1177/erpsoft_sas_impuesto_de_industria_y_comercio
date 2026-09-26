/* ============================================================================
   035 — Novedades de establecimiento: cierres y reaperturas
   ----------------------------------------------------------------------------
   Revisión del cliente (2026-09-25): un establecimiento solo se cierra desde
   "Cierre de establecimiento", con soporte y fecha de cese, y lo hace solo la
   Alcaldía. Cerrado no se vuelve a activar; si fue un error, lo reabre solo el
   administrador (el director de impuestos) "dejando escrita la justificación".

   El cierre en sí queda en ind_establecimientos (est_Activo = 0, est_Opcion_uso
   = 3, est_Fecha_cierre). Esta tabla guarda la HISTORIA: quién cerró o reabrió,
   cuándo, con qué fecha de cese y por qué. Un establecimiento que se cierra, se
   reabre por error y se vuelve a cerrar deja tres filas; en las columnas del
   establecimiento solo quedaría la última.

   RIESGO: ninguno. Tabla nueva; no toca datos existentes. Re-ejecutable.
   ============================================================================ */

SET NOCOUNT ON;
GO

DECLARE @candado INT;
EXEC @candado = sp_getapplock
     @Resource    = 'migracion_035_novedades_establecimiento',
     @LockMode    = 'Exclusive',
     @LockOwner   = 'Session',
     @LockTimeout = 120000;

IF @candado < 0
BEGIN
    RAISERROR('No se pudo tomar el candado de la migracion 035 (otra corrida en curso).', 16, 1);
    SET NOEXEC ON;
END
GO


IF OBJECT_ID('dbo.ind_establecimiento_novedades', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.ind_establecimiento_novedades (
        nov_Id                INT            IDENTITY(1,1) PRIMARY KEY,
        nov_IdEstablecimiento INT            NOT NULL,
        /* 'CIERRE' o 'REAPERTURA'. */
        nov_Tipo              VARCHAR(20)    NOT NULL,
        /* La fecha de cese de actividades del cierre; en una reapertura, la del
           cierre que se deshace. */
        nov_FechaCese         DATE           NULL,
        /* Observación del cierre, o la justificación (obligatoria) de la reapertura. */
        nov_Observacion       NVARCHAR(1000) NULL,
        nov_IdUsuario         INT            NULL,
        nov_Fecha             DATETIME       NOT NULL CONSTRAINT DF_nov_Fecha DEFAULT GETDATE()
    );

    CREATE INDEX IX_nov_establecimiento
        ON dbo.ind_establecimiento_novedades (nov_IdEstablecimiento, nov_Fecha);

    PRINT '  + ind_establecimiento_novedades';
END
ELSE
    PRINT '  = ind_establecimiento_novedades ya existia';
GO


IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '035_novedades_de_establecimiento')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('035_novedades_de_establecimiento',
            N'Historia de cierres y reaperturas de establecimientos (quién, cuándo, fecha de cese y justificación). El cierre lo hace solo la Alcaldía con soporte y fecha; la reapertura, solo el administrador con justificación.');
GO

SET NOEXEC OFF;
EXEC sp_releaseapplock @Resource = 'migracion_035_novedades_establecimiento', @LockOwner = 'Session';
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRÁS

       DROP TABLE dbo.ind_establecimiento_novedades;
       DELETE FROM dbo.conf_migraciones WHERE mig_Nombre = '035_novedades_de_establecimiento';
   ---------------------------------------------------------------------------- */
