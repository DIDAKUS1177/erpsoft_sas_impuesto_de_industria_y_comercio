/* ============================================================================
   038 — El tipo de sanción de la declaración ICA se guarda
   ----------------------------------------------------------------------------
   Revisión del 2026-09-28 (auditoría de lo pedido por el cliente).

   EL PROBLEMA

   El cliente pidió quitar "Activar sanciones" y dejar a la vista las opciones
   (Extemporaneidad, Corrección, Inexactitud, Otra ¿cuál?). Se hizo en pantalla,
   pero la opción elegida no se guardaba en ninguna parte: al reabrir la
   declaración volvía a "Ninguna" con el importe puesto, y el formulario impreso
   (renglón 31) mostraba las cuatro opciones sin marcar ninguna.

   EL CAMBIO

   Dos columnas en ind_declaraciones_ica:
       dec_TipoSancion  VARCHAR(20)   NULL  -- extemporaneidad | correccion |
                                             -- inexactitud | otra (NULL = ninguna)
       dec_OtraSancion  NVARCHAR(100) NULL  -- el "¿Cuál?" de "Otra"
   Las escribe "Guardar" (_guardarActividadesYTotales) y las leen la pantalla y
   extensiones/declaracion.php. Sin esta migración el código funciona igual que
   antes (no guarda el tipo).

   RIESGO

   Ninguno: dos columnas nuevas que nacen en NULL. Re-ejecutable.
   ============================================================================ */

SET NOCOUNT ON;
GO

IF COL_LENGTH('dbo.ind_declaraciones_ica', 'dec_TipoSancion') IS NULL
BEGIN
    ALTER TABLE dbo.ind_declaraciones_ica ADD dec_TipoSancion VARCHAR(20) NULL;
    PRINT '  + ind_declaraciones_ica.dec_TipoSancion';
END
ELSE
    PRINT '  = dec_TipoSancion ya existia';
GO

IF COL_LENGTH('dbo.ind_declaraciones_ica', 'dec_OtraSancion') IS NULL
BEGIN
    ALTER TABLE dbo.ind_declaraciones_ica ADD dec_OtraSancion NVARCHAR(100) NULL;
    PRINT '  + ind_declaraciones_ica.dec_OtraSancion';
END
ELSE
    PRINT '  = dec_OtraSancion ya existia';
GO

IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '038_tipo_de_sancion')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('038_tipo_de_sancion',
            N'dec_TipoSancion y dec_OtraSancion en ind_declaraciones_ica: el tipo de sanción elegido (renglón 31) se guarda, se vuelve a ver al reabrir y sale marcado en el formulario impreso.');
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRÁS

       ALTER TABLE dbo.ind_declaraciones_ica DROP COLUMN dec_TipoSancion, dec_OtraSancion;
       DELETE FROM dbo.conf_migraciones WHERE mig_Nombre = '038_tipo_de_sancion';
   ---------------------------------------------------------------------------- */
