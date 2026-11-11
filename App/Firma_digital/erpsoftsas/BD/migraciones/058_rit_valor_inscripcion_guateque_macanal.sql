/* ============================================================================
   058 — Valor de la inscripción del RIT: $10.000 en Guateque y Macanal
   ----------------------------------------------------------------------------
   Pedido de Diego (2026-11-11): que Guateque y Macanal cobren la inscripción
   del RIT y que el valor por defecto sea de 10.000 pesos. Paipa queda gratis
   (RIT_VALOR_INSCRIPCION vacío), así que esta migración NO toca otras bases.

   QUÉ HACE
     Escribe RIT_VALOR_INSCRIPCION = 10000 SOLO en erpsofts_ind_comercio_guat y
     erpsofts_ind_comercio_maca, y SOLO si el parámetro está vacío o en 0: si la
     Alcaldía ya escribió su valor, se respeta.

   NO CAMBIA
     RIT_PAGO_PREVIO_A_FIRMAR sigue en 0 (solo informa el cobro, no bloquea la
     firma) y RIT_PAGO_INSTRUCCIONES sigue como esté: cada Alcaldía escribe en
     «Municipio y bancos» dónde y cómo se paga. Quien ya firmó su RIT, ya
     presentó declaraciones o está en cese no se cobra (regla de la 055).

   RIESGO: bajo. Un valor en un parámetro; re-ejecutable. Requiere la 055.
   ============================================================================ */

SET NOCOUNT ON;
GO

IF NOT EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = 'RIT_VALOR_INSCRIPCION')
BEGIN
    RAISERROR('Falta RIT_VALOR_INSCRIPCION: aplique antes la migracion 055.', 16, 1);
    SET NOEXEC ON;
END
GO

IF DB_NAME() IN ('erpsofts_ind_comercio_guat', 'erpsofts_ind_comercio_maca')
BEGIN
    UPDATE dbo.conf_parametros
       SET par_Valor = '10000'
     WHERE par_Clave = 'RIT_VALOR_INSCRIPCION'
       AND (par_Valor IS NULL OR LTRIM(RTRIM(par_Valor)) IN ('', '0'));
    IF @@ROWCOUNT > 0 PRINT '  RIT_VALOR_INSCRIPCION = 10000';
    ELSE PRINT '  (RIT_VALOR_INSCRIPCION ya tenia valor: se respeta)';
END
ELSE
    PRINT '  (esta base no es de Guateque ni de Macanal: no se toca)';
GO

IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '058_rit_valor_inscripcion_guateque_macanal')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('058_rit_valor_inscripcion_guateque_macanal',
            N'Valor de la inscripcion del RIT en 10.000 pesos solo en Guateque y Macanal (si estaba vacio). Paipa queda gratis.');
GO

SET NOEXEC OFF;
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRÁS
       UPDATE dbo.conf_parametros SET par_Valor = NULL WHERE par_Clave = 'RIT_VALOR_INSCRIPCION';
       DELETE FROM dbo.conf_migraciones WHERE mig_Nombre = '058_rit_valor_inscripcion_guateque_macanal';
   ---------------------------------------------------------------------------- */
