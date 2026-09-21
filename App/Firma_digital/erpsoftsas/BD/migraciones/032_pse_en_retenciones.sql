/* ============================================================================
   032 — Pago PSE en RETEICA y AUTORRETEICA
   ----------------------------------------------------------------------------
   El pago en linea (PlacetoPay/AvalPay) ya existia solo para el ICA
   (ind_declaraciones_ica, columnas dec_PSE_* + dec_FechaRealPago/AnioPago/
   RutaPago, migracion 014). El cliente pidio (2026-09-17) habilitarlo tambien
   en los dos modulos de retencion.

   Esta migracion agrega a ind_reteica e ind_autorreteica EXACTAMENTE las mismas
   columnas de pago/PSE que ya tiene el ICA, con los mismos tipos (comprobados
   contra INFORMATION_SCHEMA el 2026-09-17), para que el flujo de pago sea uno
   solo, parametrizado por modulo (ver business/class.pseModulo.php).

   Las tablas ya traian de la migracion 030 las columnas base de pago
   (*_Pagado, *_FechaPago, *_ValorPago, *_BancoPago). Aqui se suman las que
   faltaban: las cuatro de PSE y las tres de auditoria del pago.

   RIESGO: bajo. Solo agrega columnas NULL a dos tablas; nada que hoy funcione
   las lee todavia. Re-ejecutable (guardas IF COL_LENGTH).
   ============================================================================ */

SET NOCOUNT ON;
GO
SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
GO

/* ---- RETEICA (ind_reteica, prefijo ret_) ---- */
IF OBJECT_ID('dbo.ind_reteica','U') IS NOT NULL
BEGIN
    IF COL_LENGTH('dbo.ind_reteica','ret_PSE_RequestId')   IS NULL ALTER TABLE dbo.ind_reteica ADD ret_PSE_RequestId   BIGINT       NULL;
    IF COL_LENGTH('dbo.ind_reteica','ret_PSE_Estado')      IS NULL ALTER TABLE dbo.ind_reteica ADD ret_PSE_Estado      VARCHAR(20)  NULL;
    IF COL_LENGTH('dbo.ind_reteica','ret_PSE_FechaEstado') IS NULL ALTER TABLE dbo.ind_reteica ADD ret_PSE_FechaEstado DATETIME2    NULL;
    IF COL_LENGTH('dbo.ind_reteica','ret_PSE_Mensaje')     IS NULL ALTER TABLE dbo.ind_reteica ADD ret_PSE_Mensaje     VARCHAR(300) NULL;
    IF COL_LENGTH('dbo.ind_reteica','ret_FechaRealPago')   IS NULL ALTER TABLE dbo.ind_reteica ADD ret_FechaRealPago   DATETIME2    NULL;
    IF COL_LENGTH('dbo.ind_reteica','ret_AnioPago')        IS NULL ALTER TABLE dbo.ind_reteica ADD ret_AnioPago        INT          NULL;
    IF COL_LENGTH('dbo.ind_reteica','ret_RutaPago')        IS NULL ALTER TABLE dbo.ind_reteica ADD ret_RutaPago        VARCHAR(200) NULL;
    PRINT '  = PSE en ind_reteica listo';
END
GO

/* ---- AUTORRETEICA (ind_autorreteica, prefijo aut_) ---- */
IF OBJECT_ID('dbo.ind_autorreteica','U') IS NOT NULL
BEGIN
    IF COL_LENGTH('dbo.ind_autorreteica','aut_PSE_RequestId')   IS NULL ALTER TABLE dbo.ind_autorreteica ADD aut_PSE_RequestId   BIGINT       NULL;
    IF COL_LENGTH('dbo.ind_autorreteica','aut_PSE_Estado')      IS NULL ALTER TABLE dbo.ind_autorreteica ADD aut_PSE_Estado      VARCHAR(20)  NULL;
    IF COL_LENGTH('dbo.ind_autorreteica','aut_PSE_FechaEstado') IS NULL ALTER TABLE dbo.ind_autorreteica ADD aut_PSE_FechaEstado DATETIME2    NULL;
    IF COL_LENGTH('dbo.ind_autorreteica','aut_PSE_Mensaje')     IS NULL ALTER TABLE dbo.ind_autorreteica ADD aut_PSE_Mensaje     VARCHAR(300) NULL;
    IF COL_LENGTH('dbo.ind_autorreteica','aut_FechaRealPago')   IS NULL ALTER TABLE dbo.ind_autorreteica ADD aut_FechaRealPago   DATETIME2    NULL;
    IF COL_LENGTH('dbo.ind_autorreteica','aut_AnioPago')        IS NULL ALTER TABLE dbo.ind_autorreteica ADD aut_AnioPago        INT          NULL;
    IF COL_LENGTH('dbo.ind_autorreteica','aut_RutaPago')        IS NULL ALTER TABLE dbo.ind_autorreteica ADD aut_RutaPago        VARCHAR(200) NULL;
    PRINT '  = PSE en ind_autorreteica listo';
END
GO

IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '032_pse_en_retenciones')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('032_pse_en_retenciones',
            'Columnas de pago PSE (RequestId/Estado/FechaEstado/Mensaje) + auditoria de pago (FechaRealPago/AnioPago/RutaPago) en ind_reteica e ind_autorreteica, iguales a las del ICA, para habilitar el pago en linea en los tres modulos con un flujo unico por modulo.');
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRAS
     ALTER TABLE dbo.ind_reteica       DROP COLUMN ret_PSE_RequestId, ret_PSE_Estado, ret_PSE_FechaEstado, ret_PSE_Mensaje, ret_FechaRealPago, ret_AnioPago, ret_RutaPago;
     ALTER TABLE dbo.ind_autorreteica  DROP COLUMN aut_PSE_RequestId, aut_PSE_Estado, aut_PSE_FechaEstado, aut_PSE_Mensaje, aut_FechaRealPago, aut_AnioPago, aut_RutaPago;
     DELETE FROM dbo.conf_migraciones WHERE mig_Nombre = '032_pse_en_retenciones';
   ---------------------------------------------------------------------------- */
