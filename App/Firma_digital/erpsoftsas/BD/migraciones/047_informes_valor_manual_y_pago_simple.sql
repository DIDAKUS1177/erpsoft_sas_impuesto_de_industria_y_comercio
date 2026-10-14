/* ============================================================================
   047 — Valor de actividades a mano, pago manual simple y módulo de Informes
   ----------------------------------------------------------------------------
   Pedido de la Alcaldía de Paipa (2026-10-14, definido con Diego):

     1. En retención y autorretención el valor de cada actividad se calcula
        (base x tarifa, redondeado a miles) pero también se puede escribir a
        mano. rea_ValorManual / aua_ValorManual marcan la fila escrita a mano:
        al liquidar no se recalcula.
     2. El pago manual pide solo fecha de pago, banco y soporte: el medio deja
        de pedirse y se guarda 'NO_INDICADO'.
     3. El informe pasa a un módulo "Informes" del administrador (presentadas,
        pagadas o las dos): se le cambia el nombre al permiso de la 045.

   RIESGO: bajo. Dos columnas con valor por defecto, una restricción ampliada,
   un nombre de permiso. Re-ejecutable. Requiere la 044 y la 045.
   ============================================================================ */

SET NOCOUNT ON;
GO

/* 1. Valor de actividad escrito a mano ------------------------------------------ */
IF OBJECT_ID('dbo.ind_reteica_actividades', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.ind_reteica_actividades', 'rea_ValorManual') IS NULL
BEGIN
    ALTER TABLE dbo.ind_reteica_actividades ADD rea_ValorManual BIT NOT NULL
        CONSTRAINT DF_rea_ValorManual DEFAULT (0);
    PRINT '  + rea_ValorManual';
END
GO
IF OBJECT_ID('dbo.ind_autorreteica_actividades', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.ind_autorreteica_actividades', 'aua_ValorManual') IS NULL
BEGIN
    ALTER TABLE dbo.ind_autorreteica_actividades ADD aua_ValorManual BIT NOT NULL
        CONSTRAINT DF_aua_ValorManual DEFAULT (0);
    PRINT '  + aua_ValorManual';
END
GO

/* 2. Pago manual sin medio -------------------------------------------------------- */
IF OBJECT_ID('dbo.ind_pagos_manuales', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.check_constraints
                    WHERE name = 'CK_pma_Medio' AND definition LIKE '%NO_INDICADO%')
BEGIN
    IF OBJECT_ID('dbo.CK_pma_Medio', 'C') IS NOT NULL
        ALTER TABLE dbo.ind_pagos_manuales DROP CONSTRAINT CK_pma_Medio;
    ALTER TABLE dbo.ind_pagos_manuales ADD CONSTRAINT CK_pma_Medio
        CHECK (pma_Medio IN ('TRANSFERENCIA', 'CONSIGNACION', 'VENTANILLA', 'OTRO', 'NO_INDICADO'));
    PRINT '  ~ CK_pma_Medio admite NO_INDICADO';
END
GO

/* 3. El permiso del informe ------------------------------------------------------- */
IF COL_LENGTH('dbo.conf_submodulo', 'subMod_Clave') IS NOT NULL
    UPDATE dbo.conf_submodulo
       SET subMod_Nombre = N'Informes de declaraciones',
           subMod_Descripcion = N'Entra al módulo Informes y descarga en PDF o Excel las declaraciones presentadas, pagadas o las dos, de todos los contribuyentes o de uno.'
     WHERE subMod_Clave = 'alcaldia.informes.pagos';
GO

/* 4. Registro --------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '047_informes_valor_manual_y_pago_simple')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('047_informes_valor_manual_y_pago_simple',
            N'rea_ValorManual y aua_ValorManual (valor de actividad escrito a mano), pma_Medio NO_INDICADO (pago manual con fecha, banco y soporte) y "Informes de declaraciones" como nombre de alcaldia.informes.pagos.');
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRÁS
       ALTER TABLE dbo.ind_reteica_actividades DROP CONSTRAINT DF_rea_ValorManual;
       ALTER TABLE dbo.ind_reteica_actividades DROP COLUMN rea_ValorManual;
       ALTER TABLE dbo.ind_autorreteica_actividades DROP CONSTRAINT DF_aua_ValorManual;
       ALTER TABLE dbo.ind_autorreteica_actividades DROP COLUMN aua_ValorManual;
       -- CK_pma_Medio: solo si no hay filas NO_INDICADO; recrearla sin ese valor.
       DELETE FROM dbo.conf_migraciones WHERE mig_Nombre = '047_informes_valor_manual_y_pago_simple';
   El código funciona sin la 047: pregunta si existen las columnas y, sin
   ella, guarda el pago manual con medio OTRO. Aplicarla al desplegar.
   ---------------------------------------------------------------------------- */
