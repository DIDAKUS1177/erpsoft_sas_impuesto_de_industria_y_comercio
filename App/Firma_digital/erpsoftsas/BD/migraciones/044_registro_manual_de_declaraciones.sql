/* ============================================================================
   044 — Declaraciones antiguas ya pagadas y pagos manuales con soporte
   ----------------------------------------------------------------------------
   Pedido del cliente (Juan, Paipa; definido con Diego el 2026-10-09):

     1. La Alcaldía registra una declaración que ya se presentó en papel y ya
        se pagó (transferencia, consignación...), en cualquiera de los tres
        módulos, llenando el MISMO formulario y subiendo dos PDF: la
        declaración original y el soporte de pago. Queda presentada y pagada.
        El número es uno NUEVO de la serie; el del papel queda de referencia.
     2. Sobre una declaración ya presentada en la plataforma y sin pagar, la
        Alcaldía registra un pago manual con su soporte.

   Nada de esto va en las tablas de las declaraciones: así una corrección no
   lo hereda (la del ICA copia todas sus columnas) y cada registro conserva
   quién lo hizo y cuándo.

   QUÉ HACE
   1. ind_declaracion_soportes: los PDF (declaración original y soporte de
      pago) de cualquier declaración de los tres módulos.
   2. ind_pagos_manuales: un pago manual por declaración, con su medio,
      banco, referencia, observación, soporte y quién lo registró.
   3. ind_declaraciones_historicas: el número en papel y la fecha de
      presentación original de las que se registran ya pagadas.
   4. ind_registros_manuales_anulados: quién deshizo un registro manual, por
      qué y qué había (la declaración vuelve a borrador o queda sin pagar).
   5. Tres permisos en el grupo "Alcaldía" del panel de Roles:
        alcaldia.declaraciones.historicas  Registrar declaraciones ya pagadas
        alcaldia.pagos.manual              Registrar pagos manuales
        alcaldia.registro.anular           Anular registros manuales
      El administrador los tiene siempre; los demás roles, si se prenden.

   El pago queda con *_RutaPago = 'MANUAL' (PagoDeclaracion::VIA_MANUAL).

   RIESGO: bajo. Tablas y permisos nuevos; no toca datos existentes.
   Re-ejecutable.
   ============================================================================ */

SET NOCOUNT ON;
GO

DECLARE @candado INT;
EXEC @candado = sp_getapplock
     @Resource    = 'migracion_044_registro_manual',
     @LockMode    = 'Exclusive',
     @LockOwner   = 'Session',
     @LockTimeout = 120000;

IF @candado < 0
BEGIN
    RAISERROR('No se pudo tomar el candado de la migracion 044 (otra corrida en curso).', 16, 1);
    SET NOEXEC ON;
END
GO

/* 1. Los PDF -------------------------------------------------------------- */
IF OBJECT_ID('dbo.ind_declaracion_soportes', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.ind_declaracion_soportes (
        sop_Id             INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_ind_declaracion_soportes PRIMARY KEY,
        sop_Modulo         VARCHAR(15)    NOT NULL,
        sop_IdDeclaracion  INT            NOT NULL,
        sop_Tipo           VARCHAR(30)    NOT NULL,
        sop_NombreOriginal NVARCHAR(255)  NOT NULL,
        sop_Ruta           NVARCHAR(400)  NOT NULL,
        sop_Tamano         INT            NOT NULL,
        sop_IdUsuario      INT            NULL,
        sop_FechaCarga     DATETIME2(0)   NOT NULL CONSTRAINT DF_sop_FechaCarga DEFAULT (GETDATE()),
        sop_Activo         BIT            NOT NULL CONSTRAINT DF_sop_Activo DEFAULT (1),
        CONSTRAINT CK_sop_Modulo CHECK (sop_Modulo IN ('ica', 'reteica', 'autorreteica')),
        CONSTRAINT CK_sop_Tipo   CHECK (sop_Tipo IN ('declaracion_original', 'soporte_pago'))
    );
    CREATE INDEX IX_sop_declaracion ON dbo.ind_declaracion_soportes (sop_Modulo, sop_IdDeclaracion);
    PRINT '  + ind_declaracion_soportes';
END
GO

/* 2. Los pagos manuales ----------------------------------------------------- */
IF OBJECT_ID('dbo.ind_pagos_manuales', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.ind_pagos_manuales (
        pma_Id             INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_ind_pagos_manuales PRIMARY KEY,
        pma_Modulo         VARCHAR(15)    NOT NULL,
        pma_IdDeclaracion  INT            NOT NULL,
        pma_FechaPago      DATE           NOT NULL,
        pma_Valor          DECIMAL(18,2)  NOT NULL,
        pma_Medio          VARCHAR(20)    NOT NULL,
        pma_Banco          NVARCHAR(60)   NULL,
        pma_Referencia     NVARCHAR(60)   NULL,
        pma_Observacion    NVARCHAR(500)  NULL,
        pma_IdSoporte      INT            NULL,
        pma_IdUsuario      INT            NOT NULL,
        pma_FechaRegistro  DATETIME2(0)   NOT NULL CONSTRAINT DF_pma_FechaRegistro DEFAULT (GETDATE()),
        CONSTRAINT CK_pma_Modulo CHECK (pma_Modulo IN ('ica', 'reteica', 'autorreteica')),
        CONSTRAINT CK_pma_Medio  CHECK (pma_Medio IN ('TRANSFERENCIA', 'CONSIGNACION', 'VENTANILLA', 'OTRO')),
        CONSTRAINT CK_pma_Valor  CHECK (pma_Valor > 0)
    );
    -- Una declaración se paga una vez.
    CREATE UNIQUE INDEX UX_pma_declaracion ON dbo.ind_pagos_manuales (pma_Modulo, pma_IdDeclaracion);
    PRINT '  + ind_pagos_manuales';
END
GO

/* 3. Las declaraciones registradas ya pagadas ------------------------------- */
IF OBJECT_ID('dbo.ind_declaraciones_historicas', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.ind_declaraciones_historicas (
        his_Id                INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_ind_declaraciones_historicas PRIMARY KEY,
        his_Modulo            VARCHAR(15)    NOT NULL,
        his_IdDeclaracion     INT            NOT NULL,
        his_NumeroPapel       NVARCHAR(30)   NOT NULL,
        his_FechaPresentacion DATE           NOT NULL,
        his_Observacion       NVARCHAR(500)  NULL,
        his_IdUsuario         INT            NOT NULL,
        his_FechaRegistro     DATETIME2(0)   NOT NULL CONSTRAINT DF_his_FechaRegistro DEFAULT (GETDATE()),
        CONSTRAINT CK_his_Modulo CHECK (his_Modulo IN ('ica', 'reteica', 'autorreteica'))
    );
    CREATE UNIQUE INDEX UX_his_declaracion ON dbo.ind_declaraciones_historicas (his_Modulo, his_IdDeclaracion);
    PRINT '  + ind_declaraciones_historicas';
END
GO

/* 3b. Las anulaciones: quién deshizo un registro manual, por qué y qué había --- */
IF OBJECT_ID('dbo.ind_registros_manuales_anulados', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.ind_registros_manuales_anulados (
        anu_Id             INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_ind_registros_manuales_anulados PRIMARY KEY,
        anu_Modulo         VARCHAR(15)    NOT NULL,
        anu_IdDeclaracion  INT            NOT NULL,
        anu_Tipo           VARCHAR(15)    NOT NULL,   -- HISTORICA (volvió a borrador) o PAGO (quedó sin pagar)
        anu_Motivo         NVARCHAR(500)  NOT NULL,
        anu_Datos          NVARCHAR(MAX)  NULL,       -- lo que se deshizo, en JSON
        anu_IdUsuario      INT            NOT NULL,
        anu_Fecha          DATETIME2(0)   NOT NULL CONSTRAINT DF_anu_Fecha DEFAULT (GETDATE()),
        CONSTRAINT CK_anu_Modulo CHECK (anu_Modulo IN ('ica', 'reteica', 'autorreteica')),
        CONSTRAINT CK_anu_Tipo   CHECK (anu_Tipo IN ('HISTORICA', 'PAGO'))
    );
    CREATE INDEX IX_anu_declaracion ON dbo.ind_registros_manuales_anulados (anu_Modulo, anu_IdDeclaracion);
    PRINT '  + ind_registros_manuales_anulados';
END
GO

/* 4. Los permisos (solo si existe el catálogo de la 040) -------------------- */
IF COL_LENGTH('dbo.conf_submodulo', 'subMod_Clave') IS NOT NULL
   AND EXISTS (SELECT 1 FROM dbo.conf_modulo WHERE mod_Clave = 'alcaldia')
BEGIN
    DECLARE @permisos TABLE (clave VARCHAR(60), nombre NVARCHAR(200), descripcion NVARCHAR(500), orden INT);
    INSERT INTO @permisos VALUES
        ('alcaldia.declaraciones.historicas', N'Registrar declaraciones ya pagadas',
         N'Registra una declaración presentada y pagada fuera de la plataforma (en papel, por transferencia...), con el PDF original y el soporte de pago.', 11),
        ('alcaldia.pagos.manual', N'Registrar pagos manuales',
         N'Marca como pagada una declaración presentada, con la fecha, el valor, el medio y el soporte del pago.', 12),
        ('alcaldia.registro.anular', N'Anular registros manuales',
         N'Deshace un registro manual hecho por error: la declaración ya pagada vuelve a borrador, o el pago manual se quita. Pide un motivo y queda constancia.', 13);

    INSERT INTO dbo.conf_submodulo (subMod_Nombre, subMod_Descripcion, subMod_IdModulo, subMod_Clave, subMod_Orden)
    SELECT p.nombre, p.descripcion, m.mod_Id, p.clave, p.orden
      FROM @permisos p
      JOIN dbo.conf_modulo m ON m.mod_Clave = 'alcaldia'
     WHERE NOT EXISTS (SELECT 1 FROM dbo.conf_submodulo s WHERE s.subMod_Clave = p.clave);

    PRINT '  + permisos del registro manual (registrar ya pagadas, pagos manuales, anular)';
END
GO

/* 5. Registro y liberación del candado -------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '044_registro_manual_de_declaraciones')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('044_registro_manual_de_declaraciones',
            N'Declaraciones registradas ya pagadas (ind_declaraciones_historicas) y pagos manuales (ind_pagos_manuales, *_RutaPago = MANUAL), con sus PDF en ind_declaracion_soportes y sus anulaciones en ind_registros_manuales_anulados; permisos alcaldia.declaraciones.historicas, alcaldia.pagos.manual y alcaldia.registro.anular.');
GO

SET NOEXEC OFF;
EXEC sp_releaseapplock @Resource = 'migracion_044_registro_manual', @LockOwner = 'Session';
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRÁS (antes de que se registre algo con esto)

       DELETE FROM dbo.conf_submodulo WHERE subMod_Clave IN
           ('alcaldia.declaraciones.historicas', 'alcaldia.pagos.manual', 'alcaldia.registro.anular');
       DROP TABLE dbo.ind_registros_manuales_anulados;
       DROP TABLE dbo.ind_declaraciones_historicas;
       DROP TABLE dbo.ind_pagos_manuales;
       DROP TABLE dbo.ind_declaracion_soportes;
       DELETE FROM dbo.conf_migraciones WHERE mig_Nombre = '044_registro_manual_de_declaraciones';

   Con registros hechos, los pagos ya quedaron en las declaraciones
   (*_RutaPago = 'MANUAL'): borrar estas tablas pierde sus soportes.
   ---------------------------------------------------------------------------- */
