/* ============================================================================
   000 — Tabla de registro de migraciones
   ----------------------------------------------------------------------------
   Es la primera que se aplica en cualquier base. Sirve para saber, mirando la
   propia base, qué migraciones tiene y cuáles le faltan.

   Antes esto no existía: los cambios de esquema se hacían a mano y no quedaba
   rastro, así que la copia local y producción se desincronizaron sin que
   nadie se diera cuenta (fd_Rol, dec_Estado, ind_EmailContador...).

   Re-ejecutable: si la tabla ya existe, no hace nada.
   ============================================================================ */

IF OBJECT_ID('dbo.conf_migraciones') IS NULL
BEGIN
    CREATE TABLE dbo.conf_migraciones (
        mig_Id            INT IDENTITY(1,1) PRIMARY KEY,
        mig_Nombre        VARCHAR(150) NOT NULL UNIQUE,
        mig_FechaAplicada DATETIME     NOT NULL DEFAULT GETDATE(),
        mig_Nota          VARCHAR(500) NULL
    );
    PRINT 'conf_migraciones creada.';
END
ELSE
    PRINT 'conf_migraciones ya existía, no se hizo nada.';
GO

IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '000_registro_de_migraciones')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('000_registro_de_migraciones', 'Tabla de control de migraciones.');
GO

/* Verificación */
SELECT mig_Nombre, mig_FechaAplicada FROM dbo.conf_migraciones ORDER BY mig_Nombre;
