/* ============================================================================
   055 — Cobro de la inscripción del RIT, por municipio
   ----------------------------------------------------------------------------
   Pedido de Diego (2026-11-09): Macanal y Guateque cobran la inscripción en el
   RIT; Paipa no (es gratis). El valor es totalmente personalizable y se escribe
   en la configuración del municipio ("Municipio y bancos"), no en el código.

   Hasta hoy el sistema no cobraba nada por el RIT: ni parámetro, ni tabla, ni
   texto. Todo lo que cobra (recibo con código de barras, PSE, Wompi, recaudo
   por archivo, pago manual) está atado a una DECLARACIÓN presentada; meter el
   RIT ahí habría tocado piezas que cobran en producción en Paipa. Por eso el
   cobro del RIT es un registro aparte y simple: la Alcaldía informa el valor,
   el contribuyente paga fuera del sistema (ventanilla, consignación...) y un
   funcionario con permiso registra el pago, o exonera con un motivo.

   QUÉ HACE
   1. Tres parámetros en conf_parametros (Municipio y bancos los muestra solos):
        RIT_VALOR_INSCRIPCION     pesos enteros. Vacío o 0 = gratis (nace vacío:
                                  con él así no cambia nada en ningún municipio).
        RIT_PAGO_INSTRUCCIONES    texto libre: dónde y cómo pagar.
        RIT_PAGO_PREVIO_A_FIRMAR  1 = el servidor no deja firmar el RIT hasta que
                                  se registre el pago o la exoneración. Nace en
                                  0 = solo informa el cobro.
      No se siembra ningún valor, cuenta ni teléfono: el repositorio es público
      y cada Alcaldía escribe lo suyo en pantalla.
   2. ind_rit_cobros: una fila por contribuyente cuando la Alcaldía REGISTRA EL
      PAGO o EXONERA. El "pendiente" no se guarda, se deduce (ver
      business/class.ritCobro.php). El valor cobrado queda como FOTO: cambiar
      después el parámetro no modifica lo ya registrado. Lleva la misma familia
      de columnas de pago que las declaraciones (_Pagado, _FechaPago,
      _FechaRealPago, _ValorPago, _BancoPago, _IdBancoPago, _AnioPago,
      _RutaPago). Un índice único filtrado impide dos registros vigentes del
      mismo contribuyente y concepto aunque dos funcionarios lo intenten a la vez.
   3. Permiso alcaldia.rit.cobro ("Registrar el pago de la inscripción del RIT")
      en el grupo Alcaldía del panel de Roles. El administrador lo tiene siempre;
      los demás roles, si se prende. El contribuyente ve el cobro con rit.ver.

   NO TOCA la huella del RIT (RitFirma): el comprobante del pago NO se sube como
   anexo del RIT, porque los anexos entran en la huella y cambiarla tumba la firma.

   RIESGO: bajo. Una tabla vacía, tres parámetros (dos vacíos) y un permiso;
   no cambia ningún dato existente. Re-ejecutable. Aplicarla SIEMPRE por número
   (php BD/aplicar_migraciones.php --aplicar 055): la 042 sigue pendiente a
   propósito en Paipa y un "--aplicar" a secas la correría.

   Al escribir en ind_rit_cobros desde fuera de la aplicación con sqlcmd, usar
   -I (QUOTED_IDENTIFIER ON): la tabla tiene un índice filtrado.
   ============================================================================ */

SET NOCOUNT ON;
SET QUOTED_IDENTIFIER ON;
GO

/* 1. Los parámetros ------------------------------------------------------------- */
SET QUOTED_IDENTIFIER ON;
IF NOT EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = 'RIT_VALOR_INSCRIPCION')
    INSERT INTO dbo.conf_parametros (par_Clave, par_Valor, par_Nombre, par_Descripcion, par_Patron)
    VALUES ('RIT_VALOR_INSCRIPCION', NULL,
            N'Inscripción del RIT: valor a cobrar (pesos)',
            N'Lo que se cobra por inscribirse en el RIT, en pesos enteros y sin puntos, comas ni signo $ (por ejemplo 25000). Vacío o 0 = la inscripción es gratis y no se muestra ningún cobro. Solo se cobra a quien nunca ha firmado su RIT, no ha presentado declaraciones y no está en cese.',
            '^[0-9]{1,9}$');
GO

SET QUOTED_IDENTIFIER ON;
IF NOT EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = 'RIT_PAGO_INSTRUCCIONES')
    INSERT INTO dbo.conf_parametros (par_Clave, par_Valor, par_Nombre, par_Descripcion, par_Patron)
    VALUES ('RIT_PAGO_INSTRUCCIONES', NULL,
            N'Inscripción del RIT: dónde y cómo pagar',
            N'Texto que ve el contribuyente junto al valor: banco y número de cuenta, oficina o ventanilla, referencia que debe usar y a quién avisar. Hasta 500 caracteres, sin los signos < ni >.',
            '(*UTF8)^[^<>]{1,500}$');
GO

SET QUOTED_IDENTIFIER ON;
IF NOT EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = 'RIT_PAGO_PREVIO_A_FIRMAR')
    INSERT INTO dbo.conf_parametros (par_Clave, par_Valor, par_Nombre, par_Descripcion, par_Patron)
    VALUES ('RIT_PAGO_PREVIO_A_FIRMAR', '0',
            N'Inscripción del RIT: exigir el pago antes de firmar',
            N'1 = el sistema no deja firmar el RIT mientras la Alcaldía no registre el pago de la inscripción (o la exoneración). 0 o vacío = solo se informa el cobro y el contribuyente puede firmar.',
            '^[01]$');
GO

/* 2. Los cobros registrados ------------------------------------------------------ */
SET QUOTED_IDENTIFIER ON;
IF OBJECT_ID('dbo.ind_rit_cobros', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.ind_rit_cobros (
        rit_Id                INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_ind_rit_cobros PRIMARY KEY,
        rit_IdContribuyente   INT           NOT NULL
                              CONSTRAINT FK_rit_cobros_contribuyente REFERENCES dbo.ind_contribuyentes (ind_Id),
        /* Hoy solo INSCRIPCION. La columna deja sitio a otro concepto sin rehacer la tabla. */
        rit_Concepto          VARCHAR(20)   NOT NULL CONSTRAINT DF_rit_cobros_concepto DEFAULT ('INSCRIPCION'),
        /* 2 = vigente, 9 = anulado (igual que dec_Estado = 2 es "vigente"). */
        rit_Estado            INT           NOT NULL CONSTRAINT DF_rit_cobros_estado DEFAULT (2),
        /* FOTO del valor que se cobraba el día del registro: nunca se recalcula con el parámetro de hoy. */
        rit_Valor             DECIMAL(18,2) NOT NULL,
        /* La familia de columnas de pago de las declaraciones. */
        rit_Pagado            BIT           NOT NULL CONSTRAINT DF_rit_cobros_pagado DEFAULT (0),
        rit_FechaPago         DATE          NULL,          -- el día que pagó el contribuyente (consignación, recibo)
        rit_FechaRealPago     DATETIME2(0)  NULL,          -- cuándo lo registró el sistema (hora de Colombia)
        rit_ValorPago         DECIMAL(18,2) NULL,
        rit_BancoPago         VARCHAR(60)   NULL,
        rit_IdBancoPago       INT           NULL
                              CONSTRAINT FK_rit_cobros_banco REFERENCES dbo.ind_bancos (ban_Id),
        rit_AnioPago          INT           NULL,
        rit_RutaPago          VARCHAR(20)   NULL,          -- MANUAL: lo registró un funcionario
        rit_Referencia        NVARCHAR(60)  NULL,          -- N° del recibo o de la consignación
        /* Exonerado: no paga, y queda por qué. */
        rit_Exonerado         BIT           NOT NULL CONSTRAINT DF_rit_cobros_exonerado DEFAULT (0),
        rit_MotivoExoneracion NVARCHAR(500) NULL,
        rit_IdUsuario         INT           NOT NULL,      -- quién lo registró
        rit_FechaCreacion     DATETIME2(0)  NOT NULL,      -- cuándo (hora de Colombia, la pone la aplicación)
        CONSTRAINT CK_rit_cobros_estado   CHECK (rit_Estado IN (2, 9)),
        CONSTRAINT CK_rit_cobros_valor    CHECK (rit_Valor > 0),
        /* Una fila es siempre una decisión: pagado o exonerado, nunca las dos ni ninguna. */
        CONSTRAINT CK_rit_cobros_decision CHECK ((rit_Pagado = 1 AND rit_Exonerado = 0)
                                              OR (rit_Pagado = 0 AND rit_Exonerado = 1)),
        CONSTRAINT CK_rit_cobros_pago     CHECK (rit_Pagado = 0
                                              OR (rit_FechaPago IS NOT NULL AND rit_ValorPago IS NOT NULL)),
        CONSTRAINT CK_rit_cobros_motivo   CHECK (rit_Exonerado = 0
                                              OR LEN(LTRIM(RTRIM(ISNULL(rit_MotivoExoneracion, '')))) >= 10)
    );
    PRINT '  + ind_rit_cobros';
END
GO

/* Un solo cobro vigente por contribuyente y concepto: el candado de la aplicación
   evita el choque, y este índice lo garantiza aunque algo lo salte. */
SET QUOTED_IDENTIFIER ON;
IF OBJECT_ID('dbo.ind_rit_cobros', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes
                    WHERE name = 'UX_rit_cobros_vigente' AND object_id = OBJECT_ID('dbo.ind_rit_cobros'))
BEGIN
    CREATE UNIQUE INDEX UX_rit_cobros_vigente
        ON dbo.ind_rit_cobros (rit_IdContribuyente, rit_Concepto)
        WHERE rit_Estado = 2;
    PRINT '  + UX_rit_cobros_vigente';
END
GO

/* 3. El permiso (solo si existe el catálogo de la 040) ---------------------------- */
SET QUOTED_IDENTIFIER ON;
IF COL_LENGTH('dbo.conf_submodulo', 'subMod_Clave') IS NOT NULL
   AND EXISTS (SELECT 1 FROM dbo.conf_modulo WHERE mod_Clave = 'alcaldia')
BEGIN
    DECLARE @permisos TABLE (clave VARCHAR(60), nombre NVARCHAR(200), descripcion NVARCHAR(500), orden INT);
    INSERT INTO @permisos VALUES
        ('alcaldia.rit.cobro', N'Registrar el pago de la inscripción del RIT',
         N'Registra que el contribuyente pagó los derechos de inscripción del RIT, o lo exonera con un motivo. Queda constancia de quién y cuándo. Solo sirve en los municipios que cobran la inscripción.', 20);

    INSERT INTO dbo.conf_submodulo (subMod_Nombre, subMod_Descripcion, subMod_IdModulo, subMod_Clave, subMod_Orden)
    SELECT p.nombre, p.descripcion, m.mod_Id, p.clave, p.orden
      FROM @permisos p
      JOIN dbo.conf_modulo m ON m.mod_Clave = 'alcaldia'
     WHERE NOT EXISTS (SELECT 1 FROM dbo.conf_submodulo s WHERE s.subMod_Clave = p.clave);

    PRINT '  + permiso alcaldia.rit.cobro';
END
GO

/* Se registra solo si quedó la tabla y los parámetros: registrada la 055 ya no
   volvería a correr. */
SET QUOTED_IDENTIFIER ON;
IF OBJECT_ID('dbo.ind_rit_cobros', 'U') IS NOT NULL
   AND EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = 'RIT_VALOR_INSCRIPCION')
   AND EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = 'RIT_PAGO_INSTRUCCIONES')
   AND EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = 'RIT_PAGO_PREVIO_A_FIRMAR')
   AND NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '055_cobro_inscripcion_rit')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('055_cobro_inscripcion_rit',
            N'Cobro de la inscripción del RIT por municipio: parámetros RIT_VALOR_INSCRIPCION (vacío = gratis), RIT_PAGO_INSTRUCCIONES y RIT_PAGO_PREVIO_A_FIRMAR (nace en 0 = solo informa), tabla ind_rit_cobros (pago registrado a mano o exoneración, con el valor cobrado como foto) y permiso alcaldia.rit.cobro.');
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRÁS (antes de que se registre algún cobro)

       DELETE FROM dbo.conf_submodulo WHERE subMod_Clave = 'alcaldia.rit.cobro';
       DROP TABLE dbo.ind_rit_cobros;
       DELETE FROM dbo.conf_parametros WHERE par_Clave IN
           ('RIT_VALOR_INSCRIPCION', 'RIT_PAGO_INSTRUCCIONES', 'RIT_PAGO_PREVIO_A_FIRMAR');
       DELETE FROM dbo.conf_migraciones WHERE mig_Nombre = '055_cobro_inscripcion_rit';

   Con cobros registrados, borrar la tabla pierde quién pagó, cuándo y con qué
   referencia. Si algún cobro se registró por error, no se borra: se pone
   rit_Estado = 9 (anulado) y queda libre el registro vigente.
   ---------------------------------------------------------------------------- */
