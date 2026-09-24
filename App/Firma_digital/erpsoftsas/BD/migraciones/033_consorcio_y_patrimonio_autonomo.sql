/* ============================================================================
   033 — Consorcio/union temporal y patrimonio autonomo en el contribuyente
   ----------------------------------------------------------------------------
   Pedido por el cliente (2026-09-22).

   El formulario impreso de la declaracion ya trae dos casillas:

       ¿ES CONSORCIO O UNION TEMPORAL?
       ¿REALIZA ACTIVIDADES A TRAVES DE PATRIMONIO AUTONOMO?

   ...pero NO habia donde capturarlas: en declaracion.php y liquidacion.php
   salian siempre en blanco (es_consorcio / patrimonio_autonomo fijos en false).
   El cliente pide poder marcarlas/desmarcarlas en el RIT, junto al regimen
   tributario, y que se impriman en la declaracion.

   POR QUE COLUMNA PROPIA Y NO DENTRO DE ind_Responsabilidades

   Son banderas de si/no de la PERSONA, exactamente como ind_NoSujetas y
   ind_SinAvisosTableros (migracion 016), no una lista de seleccion multiple.
   Meterlas en la cadena de responsabilidades obligaria a migrar lo ya guardado
   y a parsear codigos para leer un simple booleano. Se sigue el patron de la
   016: BIT NOT NULL DEFAULT 0.

   RIESGO

   Ninguno: dos columnas nuevas que nacen en 0. No se toca ni se reinterpreta
   nada de lo ya guardado. La vuelta atras queda al pie.
   ============================================================================ */

SET NOCOUNT ON;
GO

DECLARE @candado INT;
EXEC @candado = sp_getapplock
     @Resource    = 'migracion_033_consorcio_patrimonio',
     @LockMode    = 'Exclusive',
     @LockOwner   = 'Session',
     @LockTimeout = 120000;

IF @candado < 0
BEGIN
    RAISERROR('No se pudo tomar el candado de la migracion 033 (otra corrida en curso).', 16, 1);
    SET NOEXEC ON;
END
GO


/* ---------------------------------------------------------------------------
   1. Las dos columnas nuevas en el contribuyente
   --------------------------------------------------------------------------- */
IF COL_LENGTH('dbo.ind_contribuyentes', 'ind_EsConsorcio') IS NULL
BEGIN
    ALTER TABLE dbo.ind_contribuyentes ADD ind_EsConsorcio BIT NOT NULL DEFAULT 0;
    PRINT '  + ind_contribuyentes.ind_EsConsorcio';
END
ELSE
    PRINT '  = ind_EsConsorcio ya existia';
GO

IF COL_LENGTH('dbo.ind_contribuyentes', 'ind_PatrimonioAutonomo') IS NULL
BEGIN
    ALTER TABLE dbo.ind_contribuyentes ADD ind_PatrimonioAutonomo BIT NOT NULL DEFAULT 0;
    PRINT '  + ind_contribuyentes.ind_PatrimonioAutonomo';
END
ELSE
    PRINT '  = ind_PatrimonioAutonomo ya existia';
GO


/* ---------------------------------------------------------------------------
   2. Registro y liberacion del candado
   --------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '033_consorcio_y_patrimonio_autonomo')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('033_consorcio_y_patrimonio_autonomo',
            'Dos banderas del contribuyente que ya pedia el formulario impreso pero no se capturaban: ind_EsConsorcio (¿es consorcio o union temporal?) e ind_PatrimonioAutonomo (¿realiza actividades a traves de patrimonio autonomo?). Se marcan en el RIT y se imprimen en la declaracion y la liquidacion. BIT NOT NULL DEFAULT 0, mismo patron que las exenciones de la 016.');
GO

SET NOEXEC OFF;
EXEC sp_releaseapplock @Resource = 'migracion_033_consorcio_patrimonio', @LockOwner = 'Session';
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRAS

       ALTER TABLE dbo.ind_contribuyentes DROP CONSTRAINT <nombre del default de ind_EsConsorcio>;
       ALTER TABLE dbo.ind_contribuyentes DROP CONSTRAINT <nombre del default de ind_PatrimonioAutonomo>;
       ALTER TABLE dbo.ind_contribuyentes DROP COLUMN ind_EsConsorcio;
       ALTER TABLE dbo.ind_contribuyentes DROP COLUMN ind_PatrimonioAutonomo;
       DELETE FROM dbo.conf_migraciones WHERE mig_Nombre = '033_consorcio_y_patrimonio_autonomo';
   ---------------------------------------------------------------------------- */
