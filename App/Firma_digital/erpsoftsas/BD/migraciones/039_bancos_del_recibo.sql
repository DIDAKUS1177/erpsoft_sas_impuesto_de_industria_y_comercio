/* ============================================================================
   039 — Bancos autorizados en el recibo de pago
   ----------------------------------------------------------------------------
   Pedido del cliente (2026-09-29, Jennifer): el recibo de pago lleva al pie
   "Páguese en: BANCOS: ..." con los bancos autorizados para el pago de
   Industria y Comercio, "solamente banco y en seguida número de cuenta; tipo de
   cuenta no", como en las demás facturas del municipio.

   EL CAMBIO

   Un parámetro nuevo, RECIBO_BANCOS, que se edita en Parámetros ICA > Municipio
   y bancos (texto libre: BANCO (cuenta), BANCO (cuenta), ...). Vacío = el
   recibo no imprime la línea. extensiones/reciboPago.php lo lee.

   Nace con la lista de PAIPA solo en la base de Paipa, que se reconoce por su
   NOMBRE (erpsofts_ind_comercio_paip) y por su EAN de recaudo (7709998161047).
   Por el EAN solo no alcanza: la 009 siembra ese mismo EAN en toda base nueva,
   y Guateque o un municipio recién cargado habría quedado con las cuentas de
   Paipa en su recibo (revisión 2026-09-29). En los demás municipios nace vacío
   y cada Alcaldía escribe la suya. aplicar_migraciones.php imprime el nombre de
   la base al empezar.

   RIESGO

   Ninguno: una fila nueva en conf_parametros. Re-ejecutable.
   ============================================================================ */

SET NOCOUNT ON;
GO

IF NOT EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = 'RECIBO_BANCOS')
BEGIN
    DECLARE @valor NVARCHAR(500) = NULL;
    IF DB_NAME() = 'erpsofts_ind_comercio_paip'
       AND EXISTS (SELECT 1 FROM dbo.conf_parametros
                    WHERE par_Clave = 'RECAUDO_EAN' AND par_Valor = '7709998161047')
        SET @valor = N'BANCO AGRARIO (1511-3-00905-0), BANCO POPULAR (261-11436-7), BANCO DE BOGOTÁ (676026834), DAVIVIENDA (41600003686-1), BANCOLOMBIA (038-85109275), BBVA (001380660100000035), CONFIAR (450008701)';

    INSERT INTO dbo.conf_parametros (par_Clave, par_Valor, par_Nombre, par_Descripcion, par_Patron)
    VALUES ('RECIBO_BANCOS', @valor,
            N'Bancos autorizados (recibo de pago)',
            N'Se imprimen al pie del recibo de pago como "Páguese en: BANCOS: ...". Escríbalos como BANCO (número de cuenta), separados por comas. Vacío = el recibo no los imprime.',
            '^[^<>]{0,500}$');

    PRINT '  + RECIBO_BANCOS' + CASE WHEN @valor IS NULL THEN ' (vacío)' ELSE ' (bancos de Paipa)' END;
END
ELSE
    PRINT '  = RECIBO_BANCOS ya existia';
GO

IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '039_bancos_del_recibo')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('039_bancos_del_recibo',
            N'Parámetro RECIBO_BANCOS: bancos autorizados al pie del recibo de pago ("Páguese en: BANCOS: ..."). Nace con los de Paipa solo en la base de Paipa (erpsofts_ind_comercio_paip, EAN 7709998161047).');
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRÁS

       DELETE FROM dbo.conf_parametros WHERE par_Clave = 'RECIBO_BANCOS';
       DELETE FROM dbo.conf_migraciones WHERE mig_Nombre = '039_bancos_del_recibo';
   ---------------------------------------------------------------------------- */
