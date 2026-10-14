/* ============================================================================
   045 — El banco de cada pago (ind_bancos) e informe de pagos
   ----------------------------------------------------------------------------
   Pedido de la Alcaldía de Paipa (2026-10-13, definido con Diego):

     1. Cada pago guarda el ID del banco por el que entró (ind_bancos), en los
        tres módulos y venga por donde venga: recaudo por archivo (el banco
        del archivo), pago manual (se elige de la lista) y pago en línea (el
        banco al que la pasarela le consigna a la entidad: parámetro
        PASARELA_BANCO, que se elige en Municipio y bancos). *_BancoPago
        sigue guardando el texto de siempre ("Transferencia - BANCOLOMBIA",
        el banco que informó PSE...).
     2. La Alcaldía descarga en PDF o Excel el informe de las declaraciones
        pagadas de todos los contribuyentes (permiso nuevo); el
        contribuyente, solo las suyas.
     3. "Anular" se llama ahora "Eliminar pago" en pantalla (mismo permiso,
        mismo funcionamiento): se cambia el nombre en el panel de Roles.

   QUÉ HACE
   1. dec_IdBancoPago, ret_IdBancoPago y aut_IdBancoPago (INT NULL, FK a
      ind_bancos) y pma_IdBanco en ind_pagos_manuales.
   2. Llena el banco de los pagos que ya existen cuando el texto guardado es
      el nombre de un banco del catálogo, SOLO en el recaudo por archivo y en
      los pagos manuales. En un PSE el texto es el banco DEL QUE PAGÓ (o
      "PSE", que también está en el catálogo): no es por donde entró la plata.
      Los pagos en línea viejos se llenan cuando se elija PASARELA_BANCO
      (Municipio y bancos lo hace al guardar).
   3. Parámetro PASARELA_BANCO, vacío.
   4. Permiso alcaldia.informes.pagos ("Descargar el informe de pagos de
      todos los contribuyentes") y el nombre nuevo de alcaldia.registro.anular.

   RIESGO: bajo. Columnas nuevas que aceptan NULL, un parámetro vacío, un
   permiso. Solo escribe en columnas nuevas. Re-ejecutable.
   ============================================================================ */

SET NOCOUNT ON;
GO

DECLARE @candado INT;
EXEC @candado = sp_getapplock
     @Resource    = 'migracion_045_banco_del_pago',
     @LockMode    = 'Exclusive',
     @LockOwner   = 'Session',
     @LockTimeout = 120000;

IF @candado < 0
BEGIN
    RAISERROR('No se pudo tomar el candado de la migracion 045 (otra corrida en curso).', 16, 1);
    SET NOEXEC ON;
END
GO

/* 1. Las columnas ------------------------------------------------------------ */
IF COL_LENGTH('dbo.ind_declaraciones_ica', 'dec_IdBancoPago') IS NULL
BEGIN
    ALTER TABLE dbo.ind_declaraciones_ica ADD dec_IdBancoPago INT NULL
        CONSTRAINT FK_dec_IdBancoPago REFERENCES dbo.ind_bancos (ban_Id);
    PRINT '  + dec_IdBancoPago';
END
GO
IF OBJECT_ID('dbo.ind_reteica', 'U') IS NOT NULL AND COL_LENGTH('dbo.ind_reteica', 'ret_IdBancoPago') IS NULL
BEGIN
    ALTER TABLE dbo.ind_reteica ADD ret_IdBancoPago INT NULL
        CONSTRAINT FK_ret_IdBancoPago REFERENCES dbo.ind_bancos (ban_Id);
    PRINT '  + ret_IdBancoPago';
END
GO
IF OBJECT_ID('dbo.ind_autorreteica', 'U') IS NOT NULL AND COL_LENGTH('dbo.ind_autorreteica', 'aut_IdBancoPago') IS NULL
BEGIN
    ALTER TABLE dbo.ind_autorreteica ADD aut_IdBancoPago INT NULL
        CONSTRAINT FK_aut_IdBancoPago REFERENCES dbo.ind_bancos (ban_Id);
    PRINT '  + aut_IdBancoPago';
END
GO
IF OBJECT_ID('dbo.ind_pagos_manuales', 'U') IS NOT NULL AND COL_LENGTH('dbo.ind_pagos_manuales', 'pma_IdBanco') IS NULL
BEGIN
    ALTER TABLE dbo.ind_pagos_manuales ADD pma_IdBanco INT NULL
        CONSTRAINT FK_pma_IdBanco REFERENCES dbo.ind_bancos (ban_Id);
    PRINT '  + pma_IdBanco';
END
GO

/* 2. El banco de los pagos que ya existen -------------------------------------
   Solo cuando el texto ES el nombre de un banco del catálogo (sin distinguir
   mayúsculas ni tildes): el recaudo por archivo guarda ban_Nombre tal cual, y
   el pago manual guardó el banco escrito a mano en pma_Banco. Lo demás queda
   sin banco: adivinarlo sería peor que no tenerlo. */
IF OBJECT_ID('dbo.ind_pagos_manuales', 'U') IS NOT NULL
    UPDATE pm SET pm.pma_IdBanco = b.ban_Id
      FROM dbo.ind_pagos_manuales pm
      JOIN dbo.ind_bancos b
        ON LTRIM(RTRIM(b.ban_Nombre)) COLLATE Latin1_General_CI_AI = LTRIM(RTRIM(pm.pma_Banco)) COLLATE Latin1_General_CI_AI
     WHERE pm.pma_IdBanco IS NULL;
GO

-- Pagos manuales: el de su detalle.
IF OBJECT_ID('dbo.ind_pagos_manuales', 'U') IS NOT NULL
BEGIN
    UPDATE d SET d.dec_IdBancoPago = pm.pma_IdBanco
      FROM dbo.ind_declaraciones_ica d
      JOIN dbo.ind_pagos_manuales pm ON pm.pma_Modulo = 'ica' AND pm.pma_IdDeclaracion = d.dec_Id
     WHERE d.dec_IdBancoPago IS NULL AND d.dec_RutaPago = 'MANUAL' AND pm.pma_IdBanco IS NOT NULL;
    IF OBJECT_ID('dbo.ind_reteica', 'U') IS NOT NULL
        UPDATE d SET d.ret_IdBancoPago = pm.pma_IdBanco
          FROM dbo.ind_reteica d
          JOIN dbo.ind_pagos_manuales pm ON pm.pma_Modulo = 'reteica' AND pm.pma_IdDeclaracion = d.ret_Id
         WHERE d.ret_IdBancoPago IS NULL AND d.ret_RutaPago = 'MANUAL' AND pm.pma_IdBanco IS NOT NULL;
    IF OBJECT_ID('dbo.ind_autorreteica', 'U') IS NOT NULL
        UPDATE d SET d.aut_IdBancoPago = pm.pma_IdBanco
          FROM dbo.ind_autorreteica d
          JOIN dbo.ind_pagos_manuales pm ON pm.pma_Modulo = 'autorreteica' AND pm.pma_IdDeclaracion = d.aut_Id
         WHERE d.aut_IdBancoPago IS NULL AND d.aut_RutaPago = 'MANUAL' AND pm.pma_IdBanco IS NOT NULL;
END
GO

-- Recaudo por archivo: guarda ban_Nombre tal cual. Los de ruta vacía pueden ser
-- PSE anteriores a dec_RutaPago (su texto es el banco del que pagó): no se tocan.
UPDATE d SET d.dec_IdBancoPago = b.ban_Id
  FROM dbo.ind_declaraciones_ica d
  JOIN dbo.ind_bancos b
    ON LTRIM(RTRIM(b.ban_Nombre)) COLLATE Latin1_General_CI_AI = LTRIM(RTRIM(d.dec_BancoPago)) COLLATE Latin1_General_CI_AI
 WHERE d.dec_IdBancoPago IS NULL AND d.dec_Pagado = 1 AND d.dec_RutaPago = 'RECAUDO_BANCARIO';
GO
IF OBJECT_ID('dbo.ind_reteica', 'U') IS NOT NULL
    UPDATE d SET d.ret_IdBancoPago = b.ban_Id
      FROM dbo.ind_reteica d
      JOIN dbo.ind_bancos b
        ON LTRIM(RTRIM(b.ban_Nombre)) COLLATE Latin1_General_CI_AI = LTRIM(RTRIM(d.ret_BancoPago)) COLLATE Latin1_General_CI_AI
     WHERE d.ret_IdBancoPago IS NULL AND d.ret_Pagado = 1 AND d.ret_RutaPago = 'RECAUDO_BANCARIO';
GO
IF OBJECT_ID('dbo.ind_autorreteica', 'U') IS NOT NULL
    UPDATE d SET d.aut_IdBancoPago = b.ban_Id
      FROM dbo.ind_autorreteica d
      JOIN dbo.ind_bancos b
        ON LTRIM(RTRIM(b.ban_Nombre)) COLLATE Latin1_General_CI_AI = LTRIM(RTRIM(d.aut_BancoPago)) COLLATE Latin1_General_CI_AI
     WHERE d.aut_IdBancoPago IS NULL AND d.aut_Pagado = 1 AND d.aut_RutaPago = 'RECAUDO_BANCARIO';
GO

/* 3. El banco de la pasarela -------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = 'PASARELA_BANCO')
    INSERT INTO dbo.conf_parametros (par_Clave, par_Valor, par_Nombre, par_Descripcion, par_Patron)
    VALUES ('PASARELA_BANCO', NULL,
            N'Banco del pago en línea',
            N'Banco al que la pasarela (PSE o Wompi) le consigna a la entidad. Cada pago en línea queda con este banco. Al elegirlo, los pagos en línea anteriores sin banco (o con el que estaba elegido antes) quedan con él.',
            '^[0-9]{1,9}$');
GO

/* 4. Los permisos (solo si existe el catálogo de la 040) ---------------------- */
IF COL_LENGTH('dbo.conf_submodulo', 'subMod_Clave') IS NOT NULL
   AND EXISTS (SELECT 1 FROM dbo.conf_modulo WHERE mod_Clave = 'alcaldia')
BEGIN
    INSERT INTO dbo.conf_submodulo (subMod_Nombre, subMod_Descripcion, subMod_IdModulo, subMod_Clave, subMod_Orden)
    SELECT N'Descargar el informe de pagos de todos',
           N'Descarga en PDF o Excel las declaraciones pagadas de todos los contribuyentes (NIT, nombre, período, número, fecha de pago, banco y valor). Sin este permiso cada quien descarga solo las del contribuyente que gestiona.',
           m.mod_Id, 'alcaldia.informes.pagos', 14
      FROM dbo.conf_modulo m
     WHERE m.mod_Clave = 'alcaldia'
       AND NOT EXISTS (SELECT 1 FROM dbo.conf_submodulo s WHERE s.subMod_Clave = 'alcaldia.informes.pagos');

    UPDATE dbo.conf_submodulo
       SET subMod_Nombre = N'Eliminar pagos manuales',
           subMod_Descripcion = N'Elimina un pago manual hecho por error: si la declaración se registró ya pagada vuelve a borrador; si fue un pago manual, queda presentada sin pagar. Pide un motivo y queda constancia de quién lo hizo.'
     WHERE subMod_Clave = 'alcaldia.registro.anular';

    PRINT '  + permiso alcaldia.informes.pagos; "Anular" pasa a "Eliminar pagos manuales"';
END
GO

/* 5. Registro y liberación del candado ---------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '045_banco_del_pago_e_informes')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('045_banco_del_pago_e_informes',
            N'Banco de cada pago (dec/ret/aut_IdBancoPago y pma_IdBanco, FK a ind_bancos), parámetro PASARELA_BANCO, permiso alcaldia.informes.pagos y "Eliminar pagos manuales" como nombre de alcaldia.registro.anular.');
GO

SET NOEXEC OFF;
EXEC sp_releaseapplock @Resource = 'migracion_045_banco_del_pago', @LockOwner = 'Session';
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRÁS

       ALTER TABLE dbo.ind_declaraciones_ica DROP CONSTRAINT FK_dec_IdBancoPago;
       ALTER TABLE dbo.ind_declaraciones_ica DROP COLUMN dec_IdBancoPago;
       ALTER TABLE dbo.ind_reteica DROP CONSTRAINT FK_ret_IdBancoPago;
       ALTER TABLE dbo.ind_reteica DROP COLUMN ret_IdBancoPago;
       ALTER TABLE dbo.ind_autorreteica DROP CONSTRAINT FK_aut_IdBancoPago;
       ALTER TABLE dbo.ind_autorreteica DROP COLUMN aut_IdBancoPago;
       ALTER TABLE dbo.ind_pagos_manuales DROP CONSTRAINT FK_pma_IdBanco;
       ALTER TABLE dbo.ind_pagos_manuales DROP COLUMN pma_IdBanco;
       DELETE FROM dbo.conf_parametros WHERE par_Clave = 'PASARELA_BANCO';
       DELETE p FROM dbo.conf_permisos p
         JOIN dbo.conf_submodulo s ON s.subMod_Id = p.per_IdSubmodulo
        WHERE s.subMod_Clave = 'alcaldia.informes.pagos';
       DELETE FROM dbo.conf_submodulo WHERE subMod_Clave = 'alcaldia.informes.pagos';
       DELETE FROM dbo.conf_migraciones WHERE mig_Nombre = '045_banco_del_pago_e_informes';

   Los nombres del panel de Roles para alcaldia.registro.anular vuelven con
   el UPDATE inverso (ver la 044). El código nuevo funciona sin las columnas:
   comprueba si existen antes de escribirlas.
   ---------------------------------------------------------------------------- */
