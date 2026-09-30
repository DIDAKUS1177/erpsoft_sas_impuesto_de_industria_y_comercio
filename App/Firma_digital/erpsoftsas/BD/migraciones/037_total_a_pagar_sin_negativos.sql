/* ============================================================================
   037 — El total a pagar del ICA (renglón 38) nunca queda en negativo
   ----------------------------------------------------------------------------
   Revisión del 2026-09-28 (auditoría de lo pedido por el cliente).

   EL PROBLEMA

   Desde la 015 el renglón 38 (concepto 20) es:

       CASE WHEN dec_ValorConcepto13 > 0 THEN 0
            ELSE dec_ValorConcepto14 - dec_ValorConcepto15 + dec_ValorConcepto16 END

   El 15 (descuento por pronto pago) lo escribe el contribuyente (015). Si
   escribe un descuento mayor que el valor a pagar (14) más los intereses (16),
   el total a pagar salía NEGATIVO: el recibo, PSE y el código de barras
   recibían un valor imposible. Los renglones 33 y 35 (conceptos 12 y 14) ya
   tienen piso en cero desde la 015; al 38 le faltaba.

   EL CAMBIO

   Mismo cálculo, con piso en cero. Solo se cambia la fórmula que dejó la 015
   (si la Alcaldía la editó en Parámetros ICA > Conceptos, se respeta), en
   todos los años, y la anterior queda respaldada en
   ind_conceptos_formulas_previas como hizo la 015.

   RIESGO

   Ninguno sobre lo presentado: el procedimiento solo corre al liquidar o
   guardar un borrador. Para las declaraciones con total >= 0 el resultado es
   idéntico. Re-ejecutable.
   ============================================================================ */

SET NOCOUNT ON;
GO

DECLARE @candado INT;
EXEC @candado = sp_getapplock
     @Resource    = 'migracion_037_total_a_pagar_sin_negativos',
     @LockMode    = 'Exclusive',
     @LockOwner   = 'Session',
     @LockTimeout = 120000;

IF @candado < 0
BEGIN
    RAISERROR('No se pudo tomar el candado de la migracion 037 (otra corrida en curso).', 16, 1);
    SET NOEXEC ON;
END
GO

IF OBJECT_ID('dbo.ind_conceptos_formulas_previas', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.ind_conceptos_formulas_previas (
        con_Anio          INT,
        con_Codigo        VARCHAR(10),
        con_Nombre        VARCHAR(200),
        con_Observaciones VARCHAR(MAX),
        fec_Respaldo      DATETIME DEFAULT GETDATE()
    );
END
GO

INSERT INTO dbo.ind_conceptos_formulas_previas (con_Anio, con_Codigo, con_Nombre, con_Observaciones, fec_Respaldo)
SELECT c.con_Anio, c.con_Codigo, c.con_Nombre, c.con_Observaciones, GETDATE()
  FROM dbo.ind_conceptos c
 WHERE c.con_Codigo = '20'
   AND c.con_Observaciones = 'CASE WHEN dec_ValorConcepto13 > 0 THEN 0 ELSE dec_ValorConcepto14-dec_ValorConcepto15+dec_ValorConcepto16 END'
   AND NOT EXISTS (
        SELECT 1 FROM dbo.ind_conceptos_formulas_previas p
         WHERE p.con_Codigo = c.con_Codigo
           AND p.con_Anio   = c.con_Anio
           AND p.con_Observaciones = c.con_Observaciones);
PRINT '  = formula anterior del concepto 20 respaldada';
GO

UPDATE dbo.ind_conceptos
   SET con_Observaciones      = 'CASE WHEN dec_ValorConcepto13 > 0 THEN 0 WHEN dec_ValorConcepto14-dec_ValorConcepto15+dec_ValorConcepto16 < 0 THEN 0 ELSE dec_ValorConcepto14-dec_ValorConcepto15+dec_ValorConcepto16 END',
       con_FechaActualizacion = GETDATE()
 WHERE con_Codigo = '20'
   AND con_Observaciones = 'CASE WHEN dec_ValorConcepto13 > 0 THEN 0 ELSE dec_ValorConcepto14-dec_ValorConcepto15+dec_ValorConcepto16 END';
PRINT '  + concepto 20 (total a pagar) con piso en cero';
GO

-- Que se note si una fórmula no tenía la forma esperada: el aplicador no
-- muestra los PRINT, y "aplicada" con un concepto 20 sin piso engañaría. La
-- migración queda sin registrar hasta revisarla (Parámetros ICA > Conceptos).
IF EXISTS (SELECT 1 FROM dbo.ind_conceptos
            WHERE con_Codigo = '20'
              AND con_Observaciones NOT LIKE '%dec_ValorConcepto14-dec_ValorConcepto15+dec_ValorConcepto16 < 0 THEN 0%')
    RAISERROR('037: hay una formula del concepto 20 sin piso en cero (no tenia la forma esperada). Revisela en Parametros ICA > Conceptos.', 16, 1);
GO


/* ---------------------------------------------------------------------------
   Registro y liberación del candado
   --------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '037_total_a_pagar_sin_negativos')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('037_total_a_pagar_sin_negativos',
            N'Concepto 20 (renglón 38, total a pagar) con piso en cero: un descuento por pronto pago mayor que el valor a pagar lo dejaba negativo. Solo cambia la fórmula de la 015; la anterior queda en ind_conceptos_formulas_previas.');
GO

SET NOEXEC OFF;
EXEC sp_releaseapplock @Resource = 'migracion_037_total_a_pagar_sin_negativos', @LockOwner = 'Session';
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRÁS

       UPDATE dbo.ind_conceptos
          SET con_Observaciones = 'CASE WHEN dec_ValorConcepto13 > 0 THEN 0 ELSE dec_ValorConcepto14-dec_ValorConcepto15+dec_ValorConcepto16 END'
        WHERE con_Codigo = '20'
          AND con_Observaciones = 'CASE WHEN dec_ValorConcepto13 > 0 THEN 0 WHEN dec_ValorConcepto14-dec_ValorConcepto15+dec_ValorConcepto16 < 0 THEN 0 ELSE dec_ValorConcepto14-dec_ValorConcepto15+dec_ValorConcepto16 END';
   ---------------------------------------------------------------------------- */
