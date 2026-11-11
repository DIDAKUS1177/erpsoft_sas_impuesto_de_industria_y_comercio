/* ============================================================================
   057 — Autorretención: la casilla 16 (avisos y tableros) respeta la exención
         del RIT y se puede escribir a mano
   ----------------------------------------------------------------------------
   Pedido del cliente (consulta de Juan, respondida por Jennifer el 2026-11-10):

     "El contribuyente marca sin avisos y tableros y la autorretención igual
      los calcula." Y pedía casillas editables.

   Decisión (Diego y Jennifer): solo la casilla 16 queda editable, no todas.
   Dejar todas editables permite presentar cifras que no cuadran entre sí en un
   documento tributario.

   QUÉ CAMBIA

     1. aut_AvisosManual marca que el contribuyente escribió la casilla 16.
        Mientras vale 0 la casilla se calcula; en 1 la liquidación NO la pisa
        (misma idea que aua_ValorManual de la 047 para el valor de actividad).
     2. La fórmula de la casilla 16 queda así:
          - escrita a mano  -> se deja el valor guardado
          - contribuyente con ind_SinAvisosTableros = 1 en el RIT -> 0
          - en otro caso    -> 15% de la casilla 15, redondeado a miles
        (la regla del 15% es la de la 031, sin tocar).

   NO CAMBIA: la retención (RETEICA) no tiene casilla de avisos y tableros.
   Las declaraciones ya presentadas no se recalculan: la fórmula solo corre al
   guardar o presentar un borrador o una corrección.

   RIESGO: bajo. Una columna con valor por defecto y una fórmula del catálogo.
   Re-ejecutable. Requiere la 030 y la 031. El código funciona sin esta
   migración (pregunta si existe la columna); aplicarla al desplegar.
   ============================================================================ */

SET NOCOUNT ON;
GO
SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
GO

IF OBJECT_ID('dbo.ind_autorreteica', 'U') IS NULL
BEGIN
    RAISERROR('Falta ind_autorreteica: aplique antes la migracion 030.', 16, 1);
    SET NOEXEC ON;
END
GO

/* 1. Casilla 16 escrita a mano ---------------------------------------------------- */
IF COL_LENGTH('dbo.ind_autorreteica', 'aut_AvisosManual') IS NULL
BEGIN
    ALTER TABLE dbo.ind_autorreteica ADD aut_AvisosManual BIT NOT NULL
        CONSTRAINT DF_aut_AvisosManual DEFAULT (0);
    PRINT '  + aut_AvisosManual';
END
GO

/* 2. La fórmula de la casilla 16 -------------------------------------------------- */
IF COL_LENGTH('dbo.ind_contribuyentes', 'ind_SinAvisosTableros') IS NULL
BEGIN
    RAISERROR('Falta ind_contribuyentes.ind_SinAvisosTableros: aplique antes la migracion 016.', 16, 1);
    SET NOEXEC ON;
END
GO

UPDATE dbo.ind_renglones_retencion
   SET ren_Formula = N'CASE
        WHEN ISNULL(ep.aut_AvisosManual, 0) = 1 THEN ISNULL(ep.aut_ValorConcepto16, 0)
        WHEN EXISTS (SELECT 1 FROM dbo.ind_contribuyentes c
                      WHERE c.ind_Id = ep.aut_IdContribuyente
                        AND ISNULL(c.ind_SinAvisosTableros, 0) = 1) THEN 0
        ELSE ROUND(ISNULL(ep.aut_ValorConcepto15, 0) * 0.15 / 1000.0, 0) * 1000
     END',
       ren_Nombre  = N'AUTORRETENCIONES AVISOS Y TABLEROS (15%)'
 WHERE ren_Modulo = 'AUTORRETEICA' AND ren_Codigo = 16 AND ren_Formula IS NOT NULL;
PRINT '  16 AVISOS Y TABLEROS = a mano, o 0 si el RIT esta exento, o 15% de la 15';
GO

/* 3. Registro --------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '057_autorretencion_avisos_exento_y_editable')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('057_autorretencion_avisos_exento_y_editable',
            N'Autorretencion: la casilla 16 (avisos y tableros) sale en 0 si el RIT tiene ind_SinAvisosTableros, y el contribuyente puede escribirla a mano (aut_AvisosManual). Retencion no cambia.');
GO

SET NOEXEC OFF;
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRÁS
       -- 1. Fórmula de la 031:
       UPDATE dbo.ind_renglones_retencion
          SET ren_Formula = N'ROUND(ISNULL(ep.aut_ValorConcepto15,0) * 0.15 / 1000.0, 0) * 1000'
        WHERE ren_Modulo = 'AUTORRETEICA' AND ren_Codigo = 16;
       -- 2. La columna (después de la fórmula):
       ALTER TABLE dbo.ind_autorreteica DROP CONSTRAINT DF_aut_AvisosManual;
       ALTER TABLE dbo.ind_autorreteica DROP COLUMN aut_AvisosManual;
       DELETE FROM dbo.conf_migraciones WHERE mig_Nombre = '057_autorretencion_avisos_exento_y_editable';
   ---------------------------------------------------------------------------- */
