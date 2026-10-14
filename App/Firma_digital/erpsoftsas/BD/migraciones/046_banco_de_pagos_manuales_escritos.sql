/* ============================================================================
   046 — El banco de los pagos manuales registrados antes de la 045
   ----------------------------------------------------------------------------
   Hasta la 045 el banco del pago manual se escribía a mano ("confiar",
   "BANCO BOGOTA"...), y la 045 solo reconoció los que eran IGUALES al nombre
   del catálogo. En Paipa quedaron tres sin banco (Diego, 2026-10-14):
   salían en el informe con el texto escrito y no con el banco.

   QUÉ HACE
   Compara el texto escrito con el nombre de cada banco del catálogo, sin
   mayúsculas ni tildes, sin puntos y sin las palabras "DE" y "SA": el banco
   corresponde si su nombre EMPIEZA por lo escrito ("CONFIAR" ->
   "CONFIAR COOPERATIVA FINANCIERA", "BANCO BOGOTA" -> "BANCO DE BOGOTÁ").
   Solo se asigna si corresponde a UN banco y lo escrito tiene al menos 4
   letras ("BANCO" a secas corresponde a muchos y no se toca). CAJA (00) y
   PSE (999) no son bancos. Después la declaración toma el banco de su pago
   manual.

   RIESGO: bajo. Solo llena columnas de la 045 que están vacías. Re-ejecutable.
   Requiere la 045.
   ============================================================================ */

SET NOCOUNT ON;
GO

IF COL_LENGTH('dbo.ind_pagos_manuales', 'pma_IdBanco') IS NULL
BEGIN
    RAISERROR('La 046 necesita la 045 (falta ind_pagos_manuales.pma_IdBanco). Aplique primero la 045.', 16, 1);
    SET NOEXEC ON;
END
GO

/* 1. El pago manual ------------------------------------------------------------ */
;WITH p AS (
    SELECT pma_Id,
           LTRIM(RTRIM(REPLACE(REPLACE(REPLACE(' ' + UPPER(LTRIM(RTRIM(pma_Banco))) + ' ', '.', ''), ' DE ', ' '), ' SA ', ' '))) AS n
      FROM dbo.ind_pagos_manuales
     WHERE pma_IdBanco IS NULL AND LEN(LTRIM(RTRIM(ISNULL(pma_Banco, '')))) >= 4
), b AS (
    SELECT ban_Id,
           LTRIM(RTRIM(REPLACE(REPLACE(REPLACE(' ' + UPPER(LTRIM(RTRIM(ban_Nombre))) + ' ', '.', ''), ' DE ', ' '), ' SA ', ' '))) AS n
      FROM dbo.ind_bancos
     WHERE ISNULL(ban_Activo, 1) = 1 AND ban_Codigo NOT IN ('00', '999')
), m AS (
    SELECT p.pma_Id, MIN(b.ban_Id) AS ban_Id, COUNT(*) AS c
      FROM p
      JOIN b ON LEFT(b.n, LEN(p.n)) COLLATE Latin1_General_CI_AI = p.n COLLATE Latin1_General_CI_AI
     GROUP BY p.pma_Id
)
UPDATE pm SET pm.pma_IdBanco = m.ban_Id
  FROM dbo.ind_pagos_manuales pm
  JOIN m ON m.pma_Id = pm.pma_Id
 WHERE m.c = 1;
PRINT '  pagos manuales con banco reconocido: ' + CAST(@@ROWCOUNT AS VARCHAR(10));
GO

/* 2. La declaración toma el banco de su pago manual ----------------------------- */
UPDATE d SET d.dec_IdBancoPago = pm.pma_IdBanco
  FROM dbo.ind_declaraciones_ica d
  JOIN dbo.ind_pagos_manuales pm ON pm.pma_Modulo = 'ica' AND pm.pma_IdDeclaracion = d.dec_Id
 WHERE d.dec_IdBancoPago IS NULL AND d.dec_RutaPago = 'MANUAL' AND pm.pma_IdBanco IS NOT NULL;
GO
IF COL_LENGTH('dbo.ind_reteica', 'ret_IdBancoPago') IS NOT NULL
    UPDATE d SET d.ret_IdBancoPago = pm.pma_IdBanco
      FROM dbo.ind_reteica d
      JOIN dbo.ind_pagos_manuales pm ON pm.pma_Modulo = 'reteica' AND pm.pma_IdDeclaracion = d.ret_Id
     WHERE d.ret_IdBancoPago IS NULL AND d.ret_RutaPago = 'MANUAL' AND pm.pma_IdBanco IS NOT NULL;
GO
IF COL_LENGTH('dbo.ind_autorreteica', 'aut_IdBancoPago') IS NOT NULL
    UPDATE d SET d.aut_IdBancoPago = pm.pma_IdBanco
      FROM dbo.ind_autorreteica d
      JOIN dbo.ind_pagos_manuales pm ON pm.pma_Modulo = 'autorreteica' AND pm.pma_IdDeclaracion = d.aut_Id
     WHERE d.aut_IdBancoPago IS NULL AND d.aut_RutaPago = 'MANUAL' AND pm.pma_IdBanco IS NOT NULL;
GO

/* 3. Registro -------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '046_banco_de_pagos_manuales_escritos')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('046_banco_de_pagos_manuales_escritos',
            N'Banco (pma_IdBanco y *_IdBancoPago) de los pagos manuales anteriores a la 045 cuyo banco escrito a mano corresponde a un único banco del catálogo.');
GO

SET NOEXEC OFF;
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRÁS: no hace falta; solo llenó columnas vacías. Para deshacerlo en
   una declaración, poner en NULL su *_IdBancoPago y el pma_IdBanco de su pago.
   ---------------------------------------------------------------------------- */
