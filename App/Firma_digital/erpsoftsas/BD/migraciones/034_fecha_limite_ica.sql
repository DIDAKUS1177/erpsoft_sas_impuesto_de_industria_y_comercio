/* ============================================================================
   034 — Fecha límite de pago del ICA
   ----------------------------------------------------------------------------
   Pedido por el cliente (2026-09-24): «El 30 de abril es la fecha límite, pero
   debería poderse modificar», en la pantalla que pide contraseña, «default 30
   de abril».

   Hasta esa fecha el ICA se paga normal: por PSE o con la declaración y su
   código de barras. Desde el día siguiente la declaración está VENCIDA: sale
   sin código de barras y se paga con el recibo de pago, que es donde van los
   intereses de mora. La regla vive en business/class.vencimientoICA.php.

   Va en conf_parametros, así que aparece sola en Parámetros ICA > Municipio y
   bancos, que ya pide la contraseña para guardar.

   RIESGO

   En la base, ninguno: una fila nueva. El efecto está en el código: las
   declaraciones sin pagar cuyo año ya pasó la fecha límite salen sin código de
   barras. Re-ejecutable; la vuelta atrás queda al pie.
   ============================================================================ */

SET NOCOUNT ON;
GO

DECLARE @candado INT;
EXEC @candado = sp_getapplock
     @Resource    = 'migracion_034_fecha_limite_ica',
     @LockMode    = 'Exclusive',
     @LockOwner   = 'Session',
     @LockTimeout = 120000;

IF @candado < 0
BEGIN
    RAISERROR('No se pudo tomar el candado de la migracion 034 (otra corrida en curso).', 16, 1);
    SET NOEXEC ON;
END
GO


/* ---------------------------------------------------------------------------
   1. El parámetro. DD/MM y no una fecha completa: el vencimiento se repite
   cada año, y una fecha completa que nadie actualice dejaría vencidas todas
   las declaraciones del año siguiente desde el primer día.
   --------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = 'ICA_FECHA_LIMITE')
    INSERT INTO dbo.conf_parametros (par_Clave, par_Valor, par_Nombre, par_Descripcion, par_Patron)
    VALUES ('ICA_FECHA_LIMITE', '30/04',
            N'Fecha límite de pago del ICA (día/mes)',
            N'Día y mes (DD/MM) hasta el que se presenta y paga el ICA sin intereses. Al día siguiente la declaración queda vencida: ya no se imprime con código de barras y se paga con el recibo de pago. Vacío = 30/04.',
            '^(0?[1-9]|[12][0-9]|3[01])/(0?[1-9]|1[0-2])$');
GO

PRINT '  + ICA_FECHA_LIMITE = 30/04';
GO


/* ---------------------------------------------------------------------------
   2. Registro y liberación del candado
   --------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '034_fecha_limite_ica')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('034_fecha_limite_ica',
            N'Parámetro ICA_FECHA_LIMITE (DD/MM, nace en 30/04): hasta ese día del año de la declaración el ICA se paga con la declaración y su código de barras o por PSE; desde el siguiente está vencida y se paga con el recibo de pago. Editable en Municipio y bancos, detrás de su contraseña.');
GO

SET NOEXEC OFF;
EXEC sp_releaseapplock @Resource = 'migracion_034_fecha_limite_ica', @LockOwner = 'Session';
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRÁS (el código vuelve solo al 30/04 si el parámetro no existe)

       DELETE FROM dbo.conf_parametros WHERE par_Clave = 'ICA_FECHA_LIMITE';
       DELETE FROM dbo.conf_migraciones WHERE mig_Nombre = '034_fecha_limite_ica';
   ---------------------------------------------------------------------------- */
