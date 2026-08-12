/* ============================================================================
   001 — Columnas que el código usa y pueden faltar en bases desactualizadas
   ----------------------------------------------------------------------------
   Estas tres columnas EXISTEN en producción (Paipa) pero faltaban en la copia
   local restaurada del respaldo del 26-jul-2026. Cada una hizo caer una
   pantalla distinta hasta que se agregaron a mano:

     dec_Estado          -> declaracion.php reventaba al calcular la marca de
                            agua BORRADOR/PRESENTADA.
     ind_EmailContador   -> _requiereContador() no podía consultar si el
     ind_EmailRevisor       contribuyente tiene contador registrado (la regla
                            de firma obligatoria del cliente).
     fd_Rol              -> _presentarDeclaracion() no podía distinguir la
                            firma del declarante de la del contador.

   Esta migración las crea SOLO si faltan, así que es segura de correr en
   cualquier base, incluida producción (ahí no hará nada).
   ============================================================================ */

/* ---- dec_Estado: 0 borrador, 1 firmada, 2 presentada ---- */
IF COL_LENGTH('dbo.ind_declaraciones_ica', 'dec_Estado') IS NULL
BEGIN
    ALTER TABLE dbo.ind_declaraciones_ica ADD dec_Estado INT NULL;
    PRINT 'dec_Estado agregada.';
END
ELSE PRINT 'dec_Estado ya existía.';
GO

/* ---- Correos de contador y revisor fiscal ---- */
IF COL_LENGTH('dbo.ind_contribuyentes', 'ind_EmailContador') IS NULL
BEGIN
    ALTER TABLE dbo.ind_contribuyentes ADD ind_EmailContador NVARCHAR(150) NULL;
    PRINT 'ind_EmailContador agregada.';
END
ELSE PRINT 'ind_EmailContador ya existía.';
GO

IF COL_LENGTH('dbo.ind_contribuyentes', 'ind_EmailRevisor') IS NULL
BEGIN
    ALTER TABLE dbo.ind_contribuyentes ADD ind_EmailRevisor NVARCHAR(150) NULL;
    PRINT 'ind_EmailRevisor agregada.';
END
ELSE PRINT 'ind_EmailRevisor ya existía.';
GO

/* ---- fd_Rol: 'declarante' | 'contador' ---- */
IF COL_LENGTH('dbo.firmas_declaraciones', 'fd_Rol') IS NULL
BEGIN
    ALTER TABLE dbo.firmas_declaraciones ADD fd_Rol VARCHAR(20) NULL;
    PRINT 'fd_Rol agregada.';
END
ELSE PRINT 'fd_Rol ya existía.';
GO

/* ---- dec_PSE_RequestId: id de sesión de PlacetoPay ---- */
IF COL_LENGTH('dbo.ind_declaraciones_ica', 'dec_PSE_RequestId') IS NULL
BEGIN
    ALTER TABLE dbo.ind_declaraciones_ica ADD dec_PSE_RequestId BIGINT NULL;
    PRINT 'dec_PSE_RequestId agregada.';
END
ELSE PRINT 'dec_PSE_RequestId ya existía.';
GO

/* ---- dec_BancoPago: OJO, VARCHAR(10) se queda corto ----
   PlacetoPay devuelve nombres como "Placetopay Bank" (16 caracteres). En
   SQL Server, pasarse del ancho aborta el UPDATE COMPLETO (error 2628), no
   trunca en silencio: la declaración se quedaba sin marcar como pagada.
   Se amplía a 60. El código además recorta por seguridad. */
IF COL_LENGTH('dbo.ind_declaraciones_ica', 'dec_BancoPago') IS NOT NULL
   AND COL_LENGTH('dbo.ind_declaraciones_ica', 'dec_BancoPago') < 60
BEGIN
    ALTER TABLE dbo.ind_declaraciones_ica ALTER COLUMN dec_BancoPago VARCHAR(60) NULL;
    PRINT 'dec_BancoPago ampliada a 60.';
END
ELSE PRINT 'dec_BancoPago ya tenía ancho suficiente (o no existe).';
GO

IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '001_columnas_faltantes')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('001_columnas_faltantes', 'dec_Estado, ind_EmailContador/Revisor, fd_Rol, dec_PSE_RequestId, ancho de dec_BancoPago.');
GO

/* Verificación */
SELECT
    COL_LENGTH('dbo.ind_declaraciones_ica','dec_Estado')        AS dec_Estado,
    COL_LENGTH('dbo.ind_contribuyentes','ind_EmailContador')    AS ind_EmailContador,
    COL_LENGTH('dbo.ind_contribuyentes','ind_EmailRevisor')     AS ind_EmailRevisor,
    COL_LENGTH('dbo.firmas_declaraciones','fd_Rol')             AS fd_Rol,
    COL_LENGTH('dbo.ind_declaraciones_ica','dec_PSE_RequestId') AS dec_PSE_RequestId,
    COL_LENGTH('dbo.ind_declaraciones_ica','dec_BancoPago')     AS dec_BancoPago;
