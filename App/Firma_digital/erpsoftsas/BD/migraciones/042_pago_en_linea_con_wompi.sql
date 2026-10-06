/* ============================================================================
   042 — Pago en línea con Wompi (Bancolombia), por municipio
   ----------------------------------------------------------------------------
   Pedido (2026-10-02): Macanal cobra en línea con Wompi, la pasarela de
   Bancolombia; Paipa sigue con AvalPay (PlacetoPay), que ya está certificada.
   La pasarela es de cada entidad, igual que su convenio de recaudo.

   1. PASARELA_PROVEEDOR: PLACETOPAY o WOMPI. Nace WOMPI solo en la base de
      Macanal; en las demás, PLACETOPAY, que es lo que ya hacían. Sin las
      llaves, el botón de pago no se ofrece en ninguna.

   2. Las cuatro llaves de Wompi, vacías. Las entrega Wompi en el panel del
      comercio: unas de pruebas (pub_test_..., prv_test_..., test_integrity_...,
      test_events_...) y otras de producción (..._prod_...). La privada y los
      dos secretos son sensibles: la pantalla nunca los muestra ni los escribe
      en el registro. El código se niega a mezclar llaves de pruebas con llaves
      de producción.

   3. ind_pagos_en_linea: un renglón por intento de pago. Wompi exige una
      referencia distinta en cada intento (no deja reutilizarla), y la columna
      *_PSE_RequestId de la declaración es BIGINT y guarda una sola sesión: no
      sirve. El intento guarda su referencia, la transacción de Wompi, lo que se
      cobró (con los intereses de mora, si los hubo), el medio (PSE, tarjeta,
      Nequi...), el estado, el ambiente (pruebas o producción) y quién lo inició.

   2b. PASARELA_USUARIOS_PRUEBA donde no existía (solo Paipa la tenía, creada a
      mano): sin la fila, la pantalla no tenía cómo limitar el botón a los
      usuarios de prueba.

   RIESGO

   Bajo: cinco parámetros nuevos (vacíos, o con la pasarela de siempre) y una
   tabla nueva. No toca datos existentes. Re-ejecutable.
   ============================================================================ */

SET NOCOUNT ON;
GO

/* 1. Qué pasarela usa esta entidad ------------------------------------------ */
IF NOT EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = 'PASARELA_PROVEEDOR')
    INSERT INTO dbo.conf_parametros (par_Clave, par_Valor, par_Nombre, par_Descripcion, par_Patron, par_Sensible)
    VALUES ('PASARELA_PROVEEDOR',
            CASE WHEN DB_NAME() = 'erpsofts_ind_comercio_maca' THEN 'WOMPI' ELSE 'PLACETOPAY' END,
            'Pasarela de pago en línea',
            'Con qué empresa se cobra en línea: PLACETOPAY (AvalPay, PSE) o WOMPI (Bancolombia: PSE, tarjetas, Nequi y los demás medios que el comercio habilite en Wompi). Cada una usa sus propias credenciales.',
            '^(PLACETOPAY|WOMPI)$', 0);
GO

/* 2. Las llaves de Wompi ---------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = 'WOMPI_LLAVE_PUBLICA')
    INSERT INTO dbo.conf_parametros (par_Clave, par_Valor, par_Nombre, par_Descripcion, par_Patron, par_Sensible)
    VALUES ('WOMPI_LLAVE_PUBLICA', NULL,
            'Wompi: llave pública',
            'Empieza por pub_test_ (pruebas) o pub_prod_ (producción). Está en el panel de Wompi del comercio, en Desarrolladores.',
            '^pub_(test|prod)_[A-Za-z0-9]{8,120}$', 0);
GO

IF NOT EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = 'WOMPI_LLAVE_PRIVADA')
    INSERT INTO dbo.conf_parametros (par_Clave, par_Valor, par_Nombre, par_Descripcion, par_Patron, par_Sensible)
    VALUES ('WOMPI_LLAVE_PRIVADA', NULL,
            'Wompi: llave privada',
            'Empieza por prv_test_ o prv_prod_. Con ella el sistema confirma cada pago con Wompi. No se muestra nunca; para cambiarla, escriba la nueva.',
            '^prv_(test|prod)_[A-Za-z0-9]{8,120}$', 1);
GO

IF NOT EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = 'WOMPI_SECRETO_INTEGRIDAD')
    INSERT INTO dbo.conf_parametros (par_Clave, par_Valor, par_Nombre, par_Descripcion, par_Patron, par_Sensible)
    VALUES ('WOMPI_SECRETO_INTEGRIDAD', NULL,
            'Wompi: secreto de integridad',
            'Empieza por test_integrity_ o prod_integrity_. Firma el valor y la referencia de cada pago para que nadie los cambie en el camino. No se muestra nunca.',
            '^(test|prod)_integrity_[A-Za-z0-9]{8,120}$', 1);
GO

IF NOT EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = 'WOMPI_SECRETO_EVENTOS')
    INSERT INTO dbo.conf_parametros (par_Clave, par_Valor, par_Nombre, par_Descripcion, par_Patron, par_Sensible)
    VALUES ('WOMPI_SECRETO_EVENTOS', NULL,
            'Wompi: secreto de eventos',
            'Empieza por test_events_ o prod_events_. Con él se comprueba que los avisos de pago vienen de Wompi. No se muestra nunca.',
            '^(test|prod)_events_[A-Za-z0-9]{8,120}$', 1);
GO

/* 2b. Usuarios de prueba del pago en línea --------------------------------- */
/* Paipa la tiene desde su certificación (se creó a mano); en las demás bases no
   existía, y la pantalla solo modifica filas que ya están: sin ella no había
   cómo limitar el botón a los usuarios de prueba. Con las llaves de PRUEBAS de
   Wompi y la lista vacía el botón no lo ve nadie (Pasarela::botonVisible). */
IF NOT EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = 'PASARELA_USUARIOS_PRUEBA')
    INSERT INTO dbo.conf_parametros (par_Clave, par_Valor, par_Nombre, par_Descripcion, par_Patron, par_Sensible)
    VALUES ('PASARELA_USUARIOS_PRUEBA', NULL,
            'Usuarios de prueba del pago en línea',
            'Mientras se prueba la pasarela con credenciales de PRUEBAS, solo estos usuarios (números de usuario separados por coma) ven el botón de pago. Vacío = lo ven todos, lo que se quiere en producción. Con las llaves de pruebas de Wompi y este campo vacío no lo ve nadie.',
            '^[0-9]{1,9}( *, *[0-9]{1,9})*$', 0);
GO

/* 3. Los intentos de pago --------------------------------------------------- */
IF OBJECT_ID('dbo.ind_pagos_en_linea', 'U') IS NULL
    CREATE TABLE dbo.ind_pagos_en_linea (
        pel_Id            INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_ind_pagos_en_linea PRIMARY KEY,
        pel_Pasarela      VARCHAR(15)   NOT NULL,
        pel_Modulo        VARCHAR(15)   NOT NULL,   -- ica | reteica | autorreteica
        pel_IdDeclaracion INT           NOT NULL,
        pel_Referencia    VARCHAR(60)   NOT NULL,   -- la que se manda a la pasarela; única
        pel_Centavos      BIGINT        NOT NULL,   -- lo que se cobra, en centavos
        pel_Intereses     DECIMAL(18,2) NOT NULL CONSTRAINT DF_pel_Intereses DEFAULT 0,
        pel_Transaccion   VARCHAR(60)   NULL,       -- id de la transacción en la pasarela
        pel_Estado        VARCHAR(20)   NOT NULL CONSTRAINT DF_pel_Estado DEFAULT 'CREADO',
        pel_Medio         VARCHAR(40)   NULL,       -- PSE, CARD, NEQUI, BANCOLOMBIA_TRANSFER...
        pel_Mensaje       NVARCHAR(300) NULL,
        pel_IdUsuario     INT           NULL,
        pel_Ambiente      VARCHAR(4)    NULL,       -- test | prod: un pago del sandbox nunca pasa por real
        pel_FechaCreacion DATETIME2(0)  NOT NULL CONSTRAINT DF_pel_FechaCreacion DEFAULT SYSDATETIME(),
        pel_FechaEstado   DATETIME2(0)  NULL
    );
GO

-- Una base donde la tabla ya se creó sin la columna (la local de pruebas).
IF COL_LENGTH('dbo.ind_pagos_en_linea', 'pel_Ambiente') IS NULL
    ALTER TABLE dbo.ind_pagos_en_linea ADD pel_Ambiente VARCHAR(4) NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_pel_Referencia' AND object_id = OBJECT_ID('dbo.ind_pagos_en_linea'))
    CREATE UNIQUE INDEX UX_pel_Referencia ON dbo.ind_pagos_en_linea (pel_Referencia);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_pel_Declaracion' AND object_id = OBJECT_ID('dbo.ind_pagos_en_linea'))
    CREATE INDEX IX_pel_Declaracion ON dbo.ind_pagos_en_linea (pel_Modulo, pel_IdDeclaracion);
GO

IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '042_pago_en_linea_con_wompi')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('042_pago_en_linea_con_wompi',
            N'Pasarela de pago por entidad (PASARELA_PROVEEDOR: PLACETOPAY o WOMPI; WOMPI solo en Macanal), las cuatro llaves de Wompi (vacías; privada y secretos sensibles), PASARELA_USUARIOS_PRUEBA donde no existía e ind_pagos_en_linea, un renglón por intento de pago con su referencia única.');
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRÁS

       DROP TABLE dbo.ind_pagos_en_linea;
       DELETE FROM dbo.conf_parametros
        WHERE par_Clave IN ('PASARELA_PROVEEDOR', 'WOMPI_LLAVE_PUBLICA', 'WOMPI_LLAVE_PRIVADA',
                            'WOMPI_SECRETO_INTEGRIDAD', 'WOMPI_SECRETO_EVENTOS');
       DELETE FROM dbo.conf_migraciones WHERE mig_Nombre = '042_pago_en_linea_con_wompi';

   Sin PASARELA_PROVEEDOR el código usa PlacetoPay, como antes. Borrar la tabla
   borra el rastro de los intentos: exportarla antes si ya hubo pagos.
   ---------------------------------------------------------------------------- */
