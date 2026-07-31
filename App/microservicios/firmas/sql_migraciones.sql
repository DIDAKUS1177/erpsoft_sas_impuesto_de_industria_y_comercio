-- =============================================================
-- Microservicio Firmas Digitales — Migraciones SQL Server
-- Base de datos: erpsoftweb
-- Ejecutar completo antes de usar el módulo.
-- =============================================================

-- 1. Firmas por establecimiento (RIT)
--    Un registro activo por establecimiento (firma_Estado = 1).
--    Al guardar una nueva firma, se desactivan las anteriores.
IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_NAME = 'firmas'
)
BEGIN
    CREATE TABLE firmas (
        firma_Id               INT           IDENTITY(1,1) PRIMARY KEY,
        firma_IdEstablecimiento INT          NOT NULL,
        firma_IdUsuario        INT           NOT NULL,
        firma_NombreUsuario    NVARCHAR(500) NULL,
        firma_EmailUsuario     NVARCHAR(500) NULL,
        firma_Base64           NVARCHAR(MAX) NOT NULL,
        firma_Estado           INT           NOT NULL DEFAULT 1,
        firma_FechaHora        DATETIME      NOT NULL DEFAULT GETDATE()
    );

    CREATE INDEX IX_firmas_establecimiento
        ON firmas(firma_IdEstablecimiento, firma_Estado);
END
GO

-- 2. Códigos OTP de verificación
--    Se genera uno por solicitud. Se marca como usado tras verificar o al generar uno nuevo.
--    codigo_IdEstablecimiento:
--      > 0  → firma de establecimiento (RIT)
--      = 0  → firma de declaración
--      = -1 → firma personal del usuario (perfil)
IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_NAME = 'codigos_verificacion'
)
BEGIN
    CREATE TABLE codigos_verificacion (
        codigo_Id                  INT           IDENTITY(1,1) PRIMARY KEY,
        codigo_Valor               VARCHAR(6)    NOT NULL,
        codigo_IdUsuario           INT           NOT NULL,
        codigo_Email               NVARCHAR(500) NOT NULL,
        codigo_IdEstablecimiento   INT           NOT NULL DEFAULT 0,
        codigo_FechaExpiracion     DATETIME      NOT NULL,
        codigo_Usado               INT           NOT NULL DEFAULT 0
    );

    CREATE INDEX IX_codigos_verificacion_usuario
        ON codigos_verificacion(codigo_IdUsuario, codigo_IdEstablecimiento, codigo_Usado);
END
GO

-- 3. Firma personal del usuario (dibujada con signature pad)
--    UN registro por usuario. Se actualiza cuando el usuario redibuja.
IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_NAME = 'firmas_usuario'
)
BEGIN
    CREATE TABLE firmas_usuario (
        fu_Id        INT           IDENTITY(1,1) PRIMARY KEY,
        fu_IdUsuario INT           NOT NULL UNIQUE,
        fu_Base64    NVARCHAR(MAX) NOT NULL,
        fu_FechaHora DATETIME      DEFAULT GETDATE()
    );
END
GO

-- 4. Registro de declaraciones firmadas
--    Queda un registro cada vez que se firma una declaración con OTP.
IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_NAME = 'firmas_declaraciones'
)
BEGIN
    CREATE TABLE firmas_declaraciones (
        fd_Id                  INT           IDENTITY(1,1) PRIMARY KEY,
        fd_NumeroDeclaracion   VARCHAR(50)   NOT NULL,
        fd_IdUsuario           INT           NOT NULL,
        fd_NombreUsuario       NVARCHAR(500) NULL,
        fd_EmailUsuario        NVARCHAR(500) NULL,
        fd_FechaHora           DATETIME      DEFAULT GETDATE()
    );

    CREATE INDEX IX_firmas_declaraciones_numero
        ON firmas_declaraciones(fd_NumeroDeclaracion);
END
GO
