/* ============================================================================
   050 — En Paipa, el contribuyente ya no corrige declaraciones
   ----------------------------------------------------------------------------
   Pedido de la Alcaldía de Paipa (Juan, 2026-10-15): "el botón de corregir que
   quede inhabilitado para los contribuyentes". Es un interruptor del panel de
   Roles (rol "Externos - ICA": ica.corregir, reteica.corregir y
   autorreteica.corregir); Diego pidió apagarlo (2026-10-16) y se hace aquí
   porque el panel pide iniciar sesión con la cuenta del administrador.

   QUÉ HACE
   Solo en Paipa: quita esos tres permisos del rol "Externos - ICA", igual que
   el panel al apagarlos (borra la fila; ver class.rol.php). El botón
   "Corregir" desaparece para los contribuyentes y el servidor rechaza la
   acción. El Administrador conserva todo: si alguien necesita corregir, lo
   hace la Alcaldía. En las demás bases no hace nada.

   RIESGO: bajo. Tres permisos de un rol. Re-ejecutable. Se vuelven a prender
   desde el panel de Roles.
   ============================================================================ */

SET NOCOUNT ON;
GO

IF DB_NAME() = 'erpsofts_ind_comercio_paip'
   AND COL_LENGTH('dbo.conf_submodulo', 'subMod_Clave') IS NOT NULL
BEGIN
    DELETE p
      FROM dbo.conf_permisos p
      JOIN dbo.conf_submodulo s ON s.subMod_Id = p.per_IdSubmodulo
      JOIN dbo.conf_rol r       ON r.rol_Id = p.per_IdRol
     WHERE r.rol_Nombre = 'Externos - ICA'
       AND s.subMod_Clave IN ('ica.corregir', 'reteica.corregir', 'autorreteica.corregir');

    PRINT '  - Corregir apagado en el rol Externos - ICA (' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' permisos)';
END
GO

IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '050_paipa_contribuyentes_sin_corregir')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('050_paipa_contribuyentes_sin_corregir',
            N'Solo Paipa: el rol "Externos - ICA" sin ica.corregir, reteica.corregir ni autorreteica.corregir (el contribuyente ya no corrige; lo hace la Alcaldía).');
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRÁS
       Prender "Corregir" en Usuarios y roles → Roles → Externos - ICA, y
       DELETE FROM dbo.conf_migraciones WHERE mig_Nombre = '050_paipa_contribuyentes_sin_corregir';
   ---------------------------------------------------------------------------- */
