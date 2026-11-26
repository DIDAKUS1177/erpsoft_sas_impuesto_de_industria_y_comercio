/* ============================================================================
   061 — Quién presentó la declaración
   ----------------------------------------------------------------------------
   Pedido de la Alcaldía de Paipa (reunión con Juan, 2026-11-25):

     "Una columna extra de quién subió: por ejemplo, fue Jennyfer la que la
      subió manual. Esa columna en informes."

   Hasta hoy solo quedaba anotado quién registró el PAGO manual o la «Ya pagada»
   (pma_IdUsuario, his_IdUsuario). Quién PRESENTÓ la declaración no se guardaba:
   cuando un funcionario gestiona a un contribuyente y presenta por él, no había
   forma de saberlo.

   QUÉ CAMBIA

     1. Una columna en cada tabla de declaraciones con la cuenta (conf_usuarios)
        que la presentó: dec_IdUsuarioPresenta, ret_IdUsuarioPresenta,
        aut_IdUsuarioPresenta. NULL = no se sabe (presentada antes de esta
        migración).
     2. Las «Ya pagada» ya registradas se llenan con quien las registró
        (his_IdUsuario): ahí presentar y registrar son el mismo acto.

   NO CAMBIA: nada de lo ya presentado por la plataforma se puede reconstruir;
   esas quedan en blanco. La columna se llena de aquí en adelante.

   RIESGO: bajo. Tres columnas que admiten NULL y un UPDATE sobre filas que no
   tenían el dato. Re-ejecutable. El código funciona sin esta migración
   (pregunta si existe la columna); aplicarla al desplegar.
   ============================================================================ */

SET NOCOUNT ON;
GO
SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
GO

/* 1. Las columnas ----------------------------------------------------------------- */
IF OBJECT_ID('dbo.ind_declaraciones_ica', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.ind_declaraciones_ica', 'dec_IdUsuarioPresenta') IS NULL
BEGIN
    ALTER TABLE dbo.ind_declaraciones_ica ADD dec_IdUsuarioPresenta INT NULL;
    PRINT '  + dec_IdUsuarioPresenta';
END
GO

IF OBJECT_ID('dbo.ind_reteica', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.ind_reteica', 'ret_IdUsuarioPresenta') IS NULL
BEGIN
    ALTER TABLE dbo.ind_reteica ADD ret_IdUsuarioPresenta INT NULL;
    PRINT '  + ret_IdUsuarioPresenta';
END
GO

IF OBJECT_ID('dbo.ind_autorreteica', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.ind_autorreteica', 'aut_IdUsuarioPresenta') IS NULL
BEGIN
    ALTER TABLE dbo.ind_autorreteica ADD aut_IdUsuarioPresenta INT NULL;
    PRINT '  + aut_IdUsuarioPresenta';
END
GO

/* 2. Las «Ya pagada» ya registradas ------------------------------------------------ */
IF OBJECT_ID('dbo.ind_declaraciones_historicas', 'U') IS NOT NULL
BEGIN
    IF COL_LENGTH('dbo.ind_declaraciones_ica', 'dec_IdUsuarioPresenta') IS NOT NULL
        EXEC sp_executesql N'
            UPDATE d SET d.dec_IdUsuarioPresenta = h.his_IdUsuario
              FROM dbo.ind_declaraciones_ica d
              INNER JOIN dbo.ind_declaraciones_historicas h
                      ON h.his_Modulo = ''ica'' AND h.his_IdDeclaracion = d.dec_Id
             WHERE d.dec_IdUsuarioPresenta IS NULL AND d.dec_Estado = 2;';

    IF COL_LENGTH('dbo.ind_reteica', 'ret_IdUsuarioPresenta') IS NOT NULL
        EXEC sp_executesql N'
            UPDATE d SET d.ret_IdUsuarioPresenta = h.his_IdUsuario
              FROM dbo.ind_reteica d
              INNER JOIN dbo.ind_declaraciones_historicas h
                      ON h.his_Modulo = ''reteica'' AND h.his_IdDeclaracion = d.ret_Id
             WHERE d.ret_IdUsuarioPresenta IS NULL AND d.ret_Estado = 2;';

    IF COL_LENGTH('dbo.ind_autorreteica', 'aut_IdUsuarioPresenta') IS NOT NULL
        EXEC sp_executesql N'
            UPDATE d SET d.aut_IdUsuarioPresenta = h.his_IdUsuario
              FROM dbo.ind_autorreteica d
              INNER JOIN dbo.ind_declaraciones_historicas h
                      ON h.his_Modulo = ''autorreteica'' AND h.his_IdDeclaracion = d.aut_Id
             WHERE d.aut_IdUsuarioPresenta IS NULL AND d.aut_Estado = 2;';

    PRINT '  = declaraciones «Ya pagada» con quien las registró';
END
GO

IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '061_quien_presenta_la_declaracion')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('061_quien_presenta_la_declaracion',
            N'Quien presento la declaracion: dec_IdUsuarioPresenta, ret_IdUsuarioPresenta y aut_IdUsuarioPresenta (cuenta de conf_usuarios; NULL en lo presentado antes). Las Ya pagada existentes se llenan con quien las registro. Sale en los informes.');
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRÁS

       ALTER TABLE dbo.ind_declaraciones_ica DROP COLUMN dec_IdUsuarioPresenta;
       ALTER TABLE dbo.ind_reteica          DROP COLUMN ret_IdUsuarioPresenta;
       ALTER TABLE dbo.ind_autorreteica     DROP COLUMN aut_IdUsuarioPresenta;
       DELETE FROM dbo.conf_migraciones WHERE mig_Nombre = '061_quien_presenta_la_declaracion';

   Sin las columnas el código deja de anotar y los informes no ofrecen la
   columna. Se pierde lo anotado desde que se aplicó.
   ---------------------------------------------------------------------------- */
