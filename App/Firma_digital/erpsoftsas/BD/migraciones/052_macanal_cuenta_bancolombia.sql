/* ============================================================================
   052 — Macanal: la cuenta de Bancolombia en el recibo de pago
   ----------------------------------------------------------------------------
   Certificación de Bancolombia (2026-10-19): el MUNICIPIO DE MACANAL (NIT
   891801129) tiene la CUENTA DE AHORROS - INDUSTRIA Y COMERCIO 492-735031-87,
   activa. Es la cuenta recaudadora de Bancolombia: sale en el recibo de pago
   ("Páguese en: BANCOS").

   Se pone aquí porque la pantalla (Municipio y bancos → Cuentas de los
   bancos) pide la sesión del administrador de Macanal. Desde allí se puede
   cambiar cuando se quiera.

   QUÉ HACE
   Solo en Macanal, y solo si Bancolombia (código 007) no tiene ya una cuenta
   recaudadora: le pone 492-735031-87. En las demás bases no hace nada.

   RIESGO: bajo. Una celda. Re-ejecutable.
   ============================================================================ */

SET NOCOUNT ON;
GO

IF DB_NAME() = 'erpsofts_ind_comercio_maca'
BEGIN
    -- ban_ActualizadoPor en NULL: el cambio no es de ningún usuario (si no,
    -- "Último cambio" mostraría a quien editó Bancolombia antes).
    UPDATE dbo.ind_bancos
       SET ban_CuentaRecaudadora = '492-735031-87', ban_FechaActualizacion = GETDATE(), ban_ActualizadoPor = NULL
     WHERE ban_Codigo = '007'
       AND NULLIF(LTRIM(RTRIM(ban_CuentaRecaudadora)), '') IS NULL;

    PRINT '  + Bancolombia de Macanal con la cuenta 492-735031-87 (' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' fila)';
END
GO

IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '052_macanal_cuenta_bancolombia')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('052_macanal_cuenta_bancolombia',
            N'Solo Macanal: cuenta recaudadora de Bancolombia 492-735031-87 (ahorros, Industria y Comercio, certificación del 2026-10-19).');
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRÁS
       Vaciar la cuenta en Municipio y bancos → Cuentas de los bancos, y
       DELETE FROM dbo.conf_migraciones WHERE mig_Nombre = '052_macanal_cuenta_bancolombia';
   ---------------------------------------------------------------------------- */
