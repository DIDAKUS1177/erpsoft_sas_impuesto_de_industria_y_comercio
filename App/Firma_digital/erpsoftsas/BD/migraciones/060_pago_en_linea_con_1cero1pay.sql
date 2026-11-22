/* ============================================================================
   060 — Pago en línea con 1cero1pay («PSE · Bancolombia»), segunda pasarela
   ----------------------------------------------------------------------------
   Pedido (2026-11-21): que Paipa pueda cobrar en línea con DOS botones a la
   vez: el de su pasarela de siempre (AvalPay/PlacetoPay, «Pagar PSE») y uno
   nuevo, «PSE · Bancolombia», por 1cero1pay (1Cero1 Software), en ICA,
   retención y autorretención. Es la misma pasarela del segundo botón del
   predial.

   QUÉ HACE

   1. Los parámetros de 1cero1pay, que se editan en Parámetros ICA > Municipio
      y bancos (detrás de la contraseña de edición):

        UNOCEROUNO_ACTIVO                   0 (apagado)
        UNOCEROUNO_USUARIO                  vacío   (sensible: nunca se muestra)
        UNOCEROUNO_CLAVE                    vacío   (sensible: nunca se muestra)
        UNOCEROUNO_CODIGO_ENTIDAD           vacío
        UNOCEROUNO_IDIMPUESTO_ICA           2
        UNOCEROUNO_IDIMPUESTO_RETEICA       77
        UNOCEROUNO_IDIMPUESTO_AUTORRETEICA  615
        UNOCEROUNO_FUENTE_PAGO              1 (Portal Web)
        UNOCEROUNO_TIPO_IMPLEMENTACION      7 (Sistema Tributario – Pasarela 1Cero1)
        UNOCEROUNO_CODIGO_SERVICIO          2016006 solo en la base de Paipa; vacío en las demás
        UNOCEROUNO_DOCUMENTOS_PRUEBA        vacío (con documentos, solo esas cuentas ven el botón)
        UNOCEROUNO_BANCO                    vacío (el banco con que se registra el pago)
        UNOCEROUNO_SECRETO_ENLACE           aleatorio, distinto en cada base (sensible e interno:
                                            firma los enlaces de «Verificar estado del pago»; no
                                            es una credencial, no sale en la pantalla ni se edita)

      Las credenciales NO van aquí (el repositorio es público): las escribe
      quien administra, en la pantalla. Los IDImpuesto por defecto son los
      «ID Trámite» que 1Cero1 entregó a Paipa; el anexo de su documento dice
      otra cosa (2 = Industria y Comercio, 3 = ReteICA), así que hay que
      confirmarlos con 1Cero1 antes de activar. El «Código Servicio» no aparece
      en su API: se guarda por si hace falta.

   2. ind_pagos_en_linea (la tabla de intentos de la 042) donde no exista —en
      Paipa la 042 está pendiente a propósito—, con la misma definición, y la
      columna nueva pel_IdImpuesto: el IDImpuesto con que se creó cada intento
      de 1cero1pay, para consultarlo con el mismo aunque después se cambie el
      parámetro.

   NO CAMBIA NADA POR SÍ SOLA

   Nace apagada y sin credenciales en todas las bases: ningún botón nuevo,
   ninguna consulta nueva, la pasarela de cada entidad sigue igual. No crea
   PASARELA_PROVEEDOR ni las llaves de Wompi (eso es la 042) ni permisos: el
   botón nuevo usa los permisos «Recibo de pago y PSE» de siempre (*.pagar).

   RIESGO: bajo. Trece parámetros nuevos, una tabla nueva donde faltaba y una
   columna que acepta NULL. No toca datos existentes. Re-ejecutable.
   Requiere la 023 (par_Sensible).
   ============================================================================ */

SET NOCOUNT ON;
GO

IF COL_LENGTH('dbo.conf_parametros', 'par_Sensible') IS NULL
BEGIN
    RAISERROR('Falta conf_parametros.par_Sensible: aplique antes la migracion 023.', 16, 1);
    SET NOEXEC ON;
END
GO

/* 1. Los parámetros de 1cero1pay -------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = 'UNOCEROUNO_ACTIVO')
    INSERT INTO dbo.conf_parametros (par_Clave, par_Valor, par_Nombre, par_Descripcion, par_Patron, par_Sensible)
    VALUES ('UNOCEROUNO_ACTIVO', '0',
            N'PSE · Bancolombia (1cero1pay): activo',
            N'1 = se ofrece el botón «PSE · Bancolombia» junto al de la pasarela de siempre; 0 = apagado. Solo funciona con el usuario, la clave y el código de entidad de 1cero1pay puestos. Apagarlo no abandona los pagos ya iniciados: se siguen confirmando.',
            '^[01]$', 0);
GO

IF NOT EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = 'UNOCEROUNO_USUARIO')
    INSERT INTO dbo.conf_parametros (par_Clave, par_Valor, par_Nombre, par_Descripcion, par_Patron, par_Sensible)
    VALUES ('UNOCEROUNO_USUARIO', NULL,
            N'PSE · Bancolombia (1cero1pay): usuario',
            N'El usuario (Username) que entregó 1Cero1 para el servicio de pagos. No se muestra nunca: solo se indica si está puesto. Para cambiarlo, escriba el nuevo; dejarlo en blanco no lo borra.',
            '^\S{3,200}$', 1);
GO

IF NOT EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = 'UNOCEROUNO_CLAVE')
    INSERT INTO dbo.conf_parametros (par_Clave, par_Valor, par_Nombre, par_Descripcion, par_Patron, par_Sensible)
    VALUES ('UNOCEROUNO_CLAVE', NULL,
            N'PSE · Bancolombia (1cero1pay): clave',
            N'La clave (Password) que entregó 1Cero1 con el usuario. No se muestra nunca: solo se indica si está puesta. Para cambiarla, escriba la nueva; dejarla en blanco no la borra.',
            '^\S{3,200}$', 1);
GO

IF NOT EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = 'UNOCEROUNO_CODIGO_ENTIDAD')
    INSERT INTO dbo.conf_parametros (par_Clave, par_Valor, par_Nombre, par_Descripcion, par_Patron, par_Sensible)
    VALUES ('UNOCEROUNO_CODIGO_ENTIDAD', NULL,
            N'PSE · Bancolombia (1cero1pay): código de entidad',
            N'El código de la entidad (CodigoEntidad) que entregó 1Cero1, de hasta 10 caracteres.',
            '^[A-Za-z0-9_-]{1,10}$', 0);
GO

IF NOT EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = 'UNOCEROUNO_IDIMPUESTO_ICA')
    INSERT INTO dbo.conf_parametros (par_Clave, par_Valor, par_Nombre, par_Descripcion, par_Patron, par_Sensible)
    VALUES ('UNOCEROUNO_IDIMPUESTO_ICA', '2',
            N'PSE · Bancolombia (1cero1pay): IDImpuesto del ICA',
            N'El «ID Trámite» de 1Cero1 para Industria y Comercio y Avisos y Tableros. Por defecto 2. Confírmelo con 1Cero1: un número equivocado manda el pago a otro trámite.',
            '^[0-9]{1,9}$', 0);
GO

IF NOT EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = 'UNOCEROUNO_IDIMPUESTO_RETEICA')
    INSERT INTO dbo.conf_parametros (par_Clave, par_Valor, par_Nombre, par_Descripcion, par_Patron, par_Sensible)
    VALUES ('UNOCEROUNO_IDIMPUESTO_RETEICA', '77',
            N'PSE · Bancolombia (1cero1pay): IDImpuesto de la retención',
            N'El «ID Trámite» de 1Cero1 para Retención de Industria y Comercio. Por defecto 77. Confírmelo con 1Cero1: un número equivocado manda el pago a otro trámite.',
            '^[0-9]{1,9}$', 0);
GO

IF NOT EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = 'UNOCEROUNO_IDIMPUESTO_AUTORRETEICA')
    INSERT INTO dbo.conf_parametros (par_Clave, par_Valor, par_Nombre, par_Descripcion, par_Patron, par_Sensible)
    VALUES ('UNOCEROUNO_IDIMPUESTO_AUTORRETEICA', '615',
            N'PSE · Bancolombia (1cero1pay): IDImpuesto de la autorretención',
            N'El «ID Trámite» de 1Cero1 para Autorretención de ICA. Por defecto 615. Confírmelo con 1Cero1: un número equivocado manda el pago a otro trámite.',
            '^[0-9]{1,9}$', 0);
GO

IF NOT EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = 'UNOCEROUNO_FUENTE_PAGO')
    INSERT INTO dbo.conf_parametros (par_Clave, par_Valor, par_Nombre, par_Descripcion, par_Patron, par_Sensible)
    VALUES ('UNOCEROUNO_FUENTE_PAGO', '1',
            N'PSE · Bancolombia (1cero1pay): fuente de pago',
            N'FuentePago del servicio de 1Cero1. 1 = Portal Web. No lo cambie sin que 1Cero1 lo indique.',
            '^[0-9]{1,4}$', 0);
GO

IF NOT EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = 'UNOCEROUNO_TIPO_IMPLEMENTACION')
    INSERT INTO dbo.conf_parametros (par_Clave, par_Valor, par_Nombre, par_Descripcion, par_Patron, par_Sensible)
    VALUES ('UNOCEROUNO_TIPO_IMPLEMENTACION', '7',
            N'PSE · Bancolombia (1cero1pay): tipo de implementación',
            N'TipoImplementacion del servicio de 1Cero1: 1 Web Services, 2 Archivo plano, 3 Pago sin validación, 7 Sistema Tributario – Pasarela 1Cero1 (el del predial). No lo cambie sin que 1Cero1 lo indique.',
            '^[0-9]{1,4}$', 0);
GO

IF NOT EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = 'UNOCEROUNO_CODIGO_SERVICIO')
    INSERT INTO dbo.conf_parametros (par_Clave, par_Valor, par_Nombre, par_Descripcion, par_Patron, par_Sensible)
    VALUES ('UNOCEROUNO_CODIGO_SERVICIO',
            CASE WHEN DB_NAME() = 'erpsofts_ind_comercio_paip' THEN '2016006' ELSE NULL END,
            N'PSE · Bancolombia (1cero1pay): código de servicio',
            N'El «Código Servicio» que 1Cero1 entregó con los trámites. Su servicio de pagos no lo pide: se guarda aquí como dato del convenio, por si hace falta.',
            '^[A-Za-z0-9_-]{1,20}$', 0);
GO

IF NOT EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = 'UNOCEROUNO_DOCUMENTOS_PRUEBA')
    INSERT INTO dbo.conf_parametros (par_Clave, par_Valor, par_Nombre, par_Descripcion, par_Patron, par_Sensible)
    VALUES ('UNOCEROUNO_DOCUMENTOS_PRUEBA', NULL,
            N'PSE · Bancolombia (1cero1pay): documentos de prueba',
            N'Mientras se prueba, solo las cuentas de usuario con estos números de documento (separados por coma) ven el botón «PSE · Bancolombia». Vacío = lo ven todos, lo que se quiere al abrirlo. Ojo: un pago hecho en la prueba es un pago real.',
            '^[0-9]{3,15}( *, *[0-9]{3,15})*$', 0);
GO

IF NOT EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = 'UNOCEROUNO_BANCO')
    INSERT INTO dbo.conf_parametros (par_Clave, par_Valor, par_Nombre, par_Descripcion, par_Patron, par_Sensible)
    VALUES ('UNOCEROUNO_BANCO', NULL,
            N'PSE · Bancolombia (1cero1pay): banco del pago',
            N'El banco al que 1cero1pay le consigna a la entidad. Con él quedan registrados los pagos que entren por «PSE · Bancolombia» (informes de pagos). Vacío = el pago queda sin banco.',
            '^[0-9]{1,9}$', 0);
GO

/* El secreto de los enlaces de retorno: 64 cifras hexadecimales al azar, propias
   de esta base. La firma del enlace viaja al navegador, así que no se usa la
   clave de 1cero1pay. Si ya existe con valor, no se toca (los enlaces abiertos
   siguen valiendo); si falta o está vacío, se genera. El código también lo crea
   si no lo encuentra (UnoCeroUno::secretoEnlace). */
IF NOT EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = 'UNOCEROUNO_SECRETO_ENLACE')
    INSERT INTO dbo.conf_parametros (par_Clave, par_Valor, par_Nombre, par_Descripcion, par_Patron, par_Sensible)
    VALUES ('UNOCEROUNO_SECRETO_ENLACE', CONVERT(VARCHAR(64), CRYPT_GEN_RANDOM(32), 2),
            N'PSE · Bancolombia (1cero1pay): secreto de los enlaces',
            N'Valor aleatorio interno con que se firman los enlaces de «Verificar estado del pago». No es una credencial de 1cero1pay y no se edita.',
            '^[0-9A-Fa-f]{32,128}$', 1);
GO

UPDATE dbo.conf_parametros
   SET par_Valor = CONVERT(VARCHAR(64), CRYPT_GEN_RANDOM(32), 2)
 WHERE par_Clave = 'UNOCEROUNO_SECRETO_ENLACE'
   AND (par_Valor IS NULL OR LEN(LTRIM(RTRIM(par_Valor))) < 32);
GO

PRINT '  + UNOCEROUNO_* (apagado y sin credenciales: no cambia nada hasta configurarlo)';
GO

/* 2. Los intentos de pago ---------------------------------------------------- */
/* La misma tabla de la 042, donde no exista (Paipa). */
IF OBJECT_ID('dbo.ind_pagos_en_linea', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.ind_pagos_en_linea (
        pel_Id            INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_ind_pagos_en_linea PRIMARY KEY,
        pel_Pasarela      VARCHAR(15)   NOT NULL,
        pel_Modulo        VARCHAR(15)   NOT NULL,   -- ica | reteica | autorreteica
        pel_IdDeclaracion INT           NOT NULL,
        pel_Referencia    VARCHAR(60)   NOT NULL,   -- la que se manda a la pasarela; única (en 1cero1pay, la Factura)
        pel_Centavos      BIGINT        NOT NULL,   -- lo que se cobra, en centavos
        pel_Intereses     DECIMAL(18,2) NOT NULL CONSTRAINT DF_pel_Intereses DEFAULT 0,
        pel_Transaccion   VARCHAR(60)   NULL,       -- id de la transacción en la pasarela
        pel_Estado        VARCHAR(20)   NOT NULL CONSTRAINT DF_pel_Estado DEFAULT 'CREADO',
        pel_Medio         VARCHAR(40)   NULL,
        pel_Mensaje       NVARCHAR(300) NULL,
        pel_IdUsuario     INT           NULL,
        pel_Ambiente      VARCHAR(4)    NULL,       -- test | prod
        pel_FechaCreacion DATETIME2(0)  NOT NULL CONSTRAINT DF_pel_FechaCreacion DEFAULT SYSDATETIME(),
        pel_FechaEstado   DATETIME2(0)  NULL
    );
    PRINT '  + ind_pagos_en_linea (no existia)';
END
GO

IF COL_LENGTH('dbo.ind_pagos_en_linea', 'pel_Ambiente') IS NULL
    ALTER TABLE dbo.ind_pagos_en_linea ADD pel_Ambiente VARCHAR(4) NULL;
GO

/* El IDImpuesto con que se creó el intento en 1cero1pay. */
IF COL_LENGTH('dbo.ind_pagos_en_linea', 'pel_IdImpuesto') IS NULL
BEGIN
    ALTER TABLE dbo.ind_pagos_en_linea ADD pel_IdImpuesto INT NULL;
    PRINT '  + ind_pagos_en_linea.pel_IdImpuesto';
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_pel_Referencia' AND object_id = OBJECT_ID('dbo.ind_pagos_en_linea'))
    CREATE UNIQUE INDEX UX_pel_Referencia ON dbo.ind_pagos_en_linea (pel_Referencia);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_pel_Declaracion' AND object_id = OBJECT_ID('dbo.ind_pagos_en_linea'))
    CREATE INDEX IX_pel_Declaracion ON dbo.ind_pagos_en_linea (pel_Modulo, pel_IdDeclaracion);
GO

IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '060_pago_en_linea_con_1cero1pay')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('060_pago_en_linea_con_1cero1pay',
            N'Segunda pasarela de pago en linea, simultanea con la de la entidad: 1cero1pay (boton PSE - Bancolombia). Trece parametros UNOCEROUNO_* (apagado y sin credenciales; uno es el secreto aleatorio de los enlaces), ind_pagos_en_linea donde no existia y la columna pel_IdImpuesto. No cambia nada hasta configurarlo en Municipio y bancos.');
GO

SET NOEXEC OFF;
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRÁS

       DELETE FROM dbo.conf_parametros WHERE par_Clave LIKE 'UNOCEROUNO[_]%';
       ALTER TABLE dbo.ind_pagos_en_linea DROP COLUMN pel_IdImpuesto;
       DELETE FROM dbo.conf_migraciones WHERE mig_Nombre = '060_pago_en_linea_con_1cero1pay';

   Sin los parámetros el código no ofrece el botón ni consulta nada. La tabla
   ind_pagos_en_linea solo se borra si la creó esta migración (donde no se
   aplicó la 042) y exportándola antes si ya hubo pagos: es el rastro de cada
   intento. Antes de quitar la columna o los parámetros, que no queden intentos
   de 1cero1pay abiertos (pel_Pasarela = '1CERO1PAY' en CREADO, COMENZADA o
   PENDIENTE): ya no se podrían confirmar.
   ---------------------------------------------------------------------------- */
