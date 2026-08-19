/* ============================================================================
   006 — Catalogo de bancos y auditoria del recaudo por archivo Asobancaria
   ----------------------------------------------------------------------------
   Prepara el terreno para conciliar los pagos hechos en ventanilla con codigo
   de barras, igual que ya lo hace el sistema de PREDIAL de la misma empresa
   (Laravel, otro stack). Aqui solo van las TABLAS; el modulo de carga viene
   despues.

   Por que hace falta una tabla de bancos y no basta dec_BancoPago:

     El archivo que entrega el banco identifica la entidad con TRES DIGITOS,
     no con su nombre: en la posicion 20 del registro de encabezado y en la 80
     de cada registro de detalle. Sin una tabla que traduzca "001" a "BANCO DE
     BOGOTÁ", el archivo es ilegible.

     Y el codigo interno NO siempre coincide con el de Asobancaria: Itau es
     003 internamente pero 006 en Asobancaria, Davivienda 012 y 051. Por eso
     son dos columnas y no una.

   dec_BancoPago (VARCHAR 60) NO se toca ni se reemplaza por una clave foranea:
   hoy guarda 'Banco de Bogotá' y 'Placetopay', el PSE ya escribe ahi, y
   cambiarlo obligaria a migrar ese flujo sin necesidad. El modulo de recaudo
   escribira el nombre del banco en esa misma columna; ind_bancos es para
   traducir el codigo del archivo y para la contabilidad.

   Formato del archivo, confirmado decodificando 79 archivos reales de recaudo
   de predial (uno de Banco de Bogota con 1.654 registros cuadro al centavo
   contra su propio registro de control):

     01  encabezado archivo : nit(10) fecha(8) banco(3) cuenta(17) ...
     05  encabezado lote    : codigo del servicio EAN-13(13) lote(4)
     06  detalle            : referencia(48) valor(12+2) ... banco debitado(3)
     08  control de lote    : registros(9) valor(16+2) lote(4)
     09  control de archivo : registros(9) valor(16+2)

   Re-ejecutable: guardas IF NOT EXISTS en cada paso y los bancos se siembran
   con NOT EXISTS por codigo de Asobancaria.
   ============================================================================ */

/* ---------------------------------------------------------------------------
   0. Candado, misma razon que en la 003 y la 005: el archivo va separado por
   GO y dos corridas simultaneas podrian entrelazar sus lotes.
   --------------------------------------------------------------------------- */
DECLARE @candado INT;
EXEC @candado = sp_getapplock
     @Resource    = 'migracion_006_bancos_recaudo',
     @LockMode    = 'Exclusive',
     @LockOwner   = 'Session',
     @LockTimeout = 120000;

IF @candado < 0
BEGIN
    RAISERROR('No se pudo tomar el candado de la migracion 006 (otra corrida en curso).', 16, 1);
    SET NOEXEC ON;
END
GO


/* ---------------------------------------------------------------------------
   1. Catalogo de bancos
   --------------------------------------------------------------------------- */
IF OBJECT_ID('dbo.ind_bancos', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.ind_bancos (
        ban_Id                 INT IDENTITY(1,1) NOT NULL,
        -- Codigo interno de la entidad. Se conserva el de predial para que las
        -- dos aplicaciones hablen el mismo idioma si algun dia se cruzan datos.
        ban_Codigo             VARCHAR(6)    NOT NULL,
        ban_Nombre             VARCHAR(128)  NOT NULL,
        -- El de TRES DIGITOS que viaja dentro del archivo de recaudo. Es la
        -- unica llave util para leerlo, y por eso va UNICA.
        ban_Asobancaria        VARCHAR(6)    NOT NULL,
        -- Cuenta del PUC municipal. Se deja NULA a proposito: ver seccion 3.
        ban_CuentaContable     VARCHAR(30)   NULL,
        -- Cuenta en la que el banco consigna el recaudo. Sirve para comprobar
        -- que el archivo corresponde al convenio del municipio (el encabezado
        -- trae ese numero en la posicion 23, con 17 digitos).
        ban_CuentaRecaudadora  VARCHAR(30)   NULL,
        ban_Activo             BIT           NOT NULL CONSTRAINT DF_ban_Activo DEFAULT (1),
        ban_FechaCreacion      DATETIME2     NOT NULL CONSTRAINT DF_ban_FechaCreacion DEFAULT (SYSDATETIME()),
        ban_FechaActualizacion DATETIME2     NULL,
        CONSTRAINT PK_ind_bancos PRIMARY KEY (ban_Id)
    );
    PRINT 'ind_bancos creada.';
END
ELSE PRINT 'ind_bancos ya existia.';
GO

/* Indices unicos con guarda propia: si el CREATE TABLE quedo a medias en una
   corrida anterior, el indice no debe quedar huerfano (lo mismo que se
   corrigio en la 004). */
IF OBJECT_ID('dbo.ind_bancos', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes
                    WHERE name = 'UX_bancos_asobancaria' AND object_id = OBJECT_ID('dbo.ind_bancos'))
BEGIN
    CREATE UNIQUE INDEX UX_bancos_asobancaria ON dbo.ind_bancos (ban_Asobancaria);
    PRINT 'UX_bancos_asobancaria creado.';
END
ELSE PRINT 'UX_bancos_asobancaria ya existia o la tabla no esta.';
GO

IF OBJECT_ID('dbo.ind_bancos', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes
                    WHERE name = 'UX_bancos_codigo' AND object_id = OBJECT_ID('dbo.ind_bancos'))
BEGIN
    CREATE UNIQUE INDEX UX_bancos_codigo ON dbo.ind_bancos (ban_Codigo);
    PRINT 'UX_bancos_codigo creado.';
END
ELSE PRINT 'UX_bancos_codigo ya existia o la tabla no esta.';
GO


/* ---------------------------------------------------------------------------
   2. Auditoria de los archivos cargados
   Responde "por que esta declaracion figura pagada" seis meses despues.
   --------------------------------------------------------------------------- */
IF OBJECT_ID('dbo.ind_archivos_asobancaria', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.ind_archivos_asobancaria (
        arc_Id                INT IDENTITY(1,1) NOT NULL,
        arc_IdUsuario         INT           NOT NULL,
        arc_Nombre            VARCHAR(255)  NOT NULL,
        arc_Ruta              VARCHAR(400)  NULL,
        arc_Tipo              VARCHAR(100)  NULL,
        arc_Tamano            INT           NULL,
        -- Huella del contenido. Predial no la tenia: alli subir dos veces el
        -- mismo archivo se atajaba pago por pago, no de entrada. Con el hash
        -- el segundo intento se rechaza antes de tocar una sola declaracion.
        arc_Hash              VARCHAR(64)   NULL,
        arc_IdBanco           INT           NULL,
        arc_FechaPago         DATE          NULL,
        arc_TotalRegistros    INT           NOT NULL CONSTRAINT DF_arc_TotalRegistros DEFAULT (0),
        arc_TotalAplicados    INT           NOT NULL CONSTRAINT DF_arc_TotalAplicados DEFAULT (0),
        arc_TotalYaPagados    INT           NOT NULL CONSTRAINT DF_arc_TotalYaPagados DEFAULT (0),
        arc_TotalFallidos     INT           NOT NULL CONSTRAINT DF_arc_TotalFallidos  DEFAULT (0),
        -- Las dos cifras del registro 09 frente a lo que sumamos nosotros.
        -- Predial lee ese registro pero nunca lo compara, asi que un archivo
        -- truncado entraba sin protestar. Guardar las dos permite probar que
        -- el archivo llego completo.
        arc_ValorControl      DECIMAL(18,2) NULL,
        arc_ValorSumado       DECIMAL(18,2) NULL,
        arc_RegistrosControl  INT           NULL,
        arc_Descripcion       NVARCHAR(MAX) NULL,
        arc_FechaCarga        DATETIME2     NOT NULL CONSTRAINT DF_arc_FechaCarga DEFAULT (SYSDATETIME()),
        CONSTRAINT PK_ind_archivos_asobancaria PRIMARY KEY (arc_Id)
    );
    PRINT 'ind_archivos_asobancaria creada.';
END
ELSE PRINT 'ind_archivos_asobancaria ya existia.';
GO

IF OBJECT_ID('dbo.ind_archivos_asobancaria', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes
                    WHERE name = 'IX_archivos_asobancaria_hash'
                      AND object_id = OBJECT_ID('dbo.ind_archivos_asobancaria'))
BEGIN
    CREATE INDEX IX_archivos_asobancaria_hash ON dbo.ind_archivos_asobancaria (arc_Hash);
    PRINT 'IX_archivos_asobancaria_hash creado.';
END
ELSE PRINT 'IX_archivos_asobancaria_hash ya existia o la tabla no esta.';
GO


/* ---------------------------------------------------------------------------
   3. Siembra del catalogo de bancos
   Codigos y nombres tomados de la tabla real de predial (base erpsofts_predial
   del respaldo de Guateque), que es la que el banco ya viene usando.

   ban_CuentaContable va NULA a proposito. En predial esas cuentas estan
   llenas -11050101, 1110050101...- pero son las del PUC de GUATEQUE. Copiarlas
   a Paipa seria sembrar contabilidad ajena. Las llena el area financiera del
   municipio.
   --------------------------------------------------------------------------- */
;WITH catalogo(codigo, asobancaria, nombre) AS (
    SELECT * FROM (VALUES
        ('00',  '00',  'CAJA'),
        ('000', '000', 'BANCO DE LA REPÚBLICA'),
        ('001', '001', 'BANCO DE BOGOTÁ'),
        ('002', '002', 'BANCO POPULAR'),
        ('003', '006', 'ITAÚ CORPBANCA COLOMBIA S.A.'),
        ('005', '009', 'CITIBANK COLOMBIA'),
        ('006', '012', 'GNB SUDAMERIS S.A.'),
        ('007', '007', 'BANCOLOMBIA S.A.'),
        ('008', '019', 'COLPATRIA'),
        ('009', '023', 'BANCO DE OCCIDENTE'),
        ('010', '032', 'BANCO CAJA SOCIAL - BCSC S.A.'),
        ('012', '051', 'BANCO DAVIVIENDA S.A.'),
        ('013', '013', 'BBVA COLOMBIA'),
        ('014', '053', 'BANCO W S.A.'),
        ('015', '058', 'BANCO CREDIFINANCIERA S.A.C.F'),
        ('016', '059', 'BANCAMIA'),
        ('017', '060', 'BANCO PICHINCHA S.A.'),
        ('018', '061', 'BANCOOMEVA'),
        ('019', '062', 'CMR FALABELLA S.A.'),
        ('020', '063', 'BANCO FINANDINA S.A.'),
        ('021', '065', 'BANCO SANTANDER DE NEGOCIOS COLOMBIA S.A.'),
        ('022', '066', 'BANCO COOPERATIVO COOPCENTRAL'),
        ('024', '069', 'BANCO SERFINANZA S.A'),
        ('040', '040', 'BANCO AGRARIO DE COLOMBIA S.A.'),
        -- 999 no es un banco: es como el archivo marca "pago por PSE" y como
        -- el registro de detalle dice "usa el banco del encabezado". Se siembra
        -- porque el parser se va a topar con el.
        ('999', '999', 'PSE')
    ) AS x(codigo, asobancaria, nombre)
)
INSERT INTO dbo.ind_bancos (ban_Codigo, ban_Nombre, ban_Asobancaria)
SELECT c.codigo, c.nombre, c.asobancaria
  FROM catalogo c
 WHERE NOT EXISTS (SELECT 1 FROM dbo.ind_bancos b WHERE b.ban_Asobancaria = c.asobancaria);

PRINT 'Catalogo de bancos sembrado.';
GO


/* ---------------------------------------------------------------------------
   4. Informe
   --------------------------------------------------------------------------- */
SELECT
    bancos              = (SELECT COUNT(*) FROM dbo.ind_bancos),
    sin_cuenta_contable = (SELECT COUNT(*) FROM dbo.ind_bancos WHERE ban_CuentaContable IS NULL),
    archivos_cargados   = (SELECT COUNT(*) FROM dbo.ind_archivos_asobancaria);
GO

/* sin_cuenta_contable = total de bancos es lo ESPERADO recien corrida: esas
   cuentas las llena el area financiera del municipio. */
GO


/* ---------------------------------------------------------------------------
   5. Registro y liberacion del candado
   --------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '006_bancos_y_recaudo_asobancaria')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('006_bancos_y_recaudo_asobancaria',
            'Catalogo de bancos con codigo Asobancaria y auditoria de archivos de recaudo. Las cuentas contables quedan nulas: las llena el area financiera. No toca dec_BancoPago.');
GO

SET NOEXEC OFF;
EXEC sp_releaseapplock @Resource = 'migracion_006_bancos_recaudo', @LockOwner = 'Session';
GO
