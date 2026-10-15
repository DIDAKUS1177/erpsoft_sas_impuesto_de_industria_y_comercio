/* ============================================================================
   048 — Qué línea del archivo del banco pagó qué declaración
   ----------------------------------------------------------------------------
   Desde el 2026-10-15 el recaudo reconoce la referencia que es el DOCUMENTO del
   contribuyente (cédula o NIT), no solo el número de la declaración. Para
   saber si un pago por documento "ya se aplicó" (reenvío del archivo) no
   basta con mirar las declaraciones del contribuyente pagadas ese día por ese
   valor: una pudo pagarse por su número, con otra línea, y el pago por cédula
   de OTRA declaración del mismo valor quedaba como "ya aplicado" y sin
   registrar (revisión del mismo día).

   QUÉ HACE
   ind_recaudo_aplicaciones: un renglón por cada pago aplicado desde un archivo
   (al aplicarlo o al asignarlo a mano): archivo, referencia tal como viene,
   valor, fecha de pago, módulo y declaración, y quién.

   RIESGO: bajo. Tabla nueva. Re-ejecutable. Sin ella el recaudo funciona como
   antes (cuenta por las declaraciones pagadas).
   ============================================================================ */

SET NOCOUNT ON;
GO

IF OBJECT_ID('dbo.ind_recaudo_aplicaciones', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.ind_recaudo_aplicaciones (
        rap_Id             INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_ind_recaudo_aplicaciones PRIMARY KEY,
        rap_IdArchivo      INT            NULL,
        rap_Referencia     VARCHAR(48)    NOT NULL,
        rap_Valor          DECIMAL(18,2)  NOT NULL,
        rap_FechaPago      DATE           NOT NULL,
        rap_Modulo         VARCHAR(15)    NOT NULL,
        rap_IdDeclaracion  INT            NOT NULL,
        rap_IdUsuario      INT            NULL,
        rap_Fecha          DATETIME2(0)   NOT NULL CONSTRAINT DF_rap_Fecha DEFAULT (GETDATE()),
        CONSTRAINT CK_rap_Modulo CHECK (rap_Modulo IN ('ica', 'reteica', 'autorreteica'))
    );
    CREATE INDEX IX_rap_pago ON dbo.ind_recaudo_aplicaciones (rap_Referencia, rap_FechaPago, rap_Valor);
    PRINT '  + ind_recaudo_aplicaciones';
END
GO

IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '048_recaudo_aplicaciones')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('048_recaudo_aplicaciones',
            N'ind_recaudo_aplicaciones: qué línea del archivo del banco (referencia, valor, fecha) pagó qué declaración; con ella se sabe si un pago por documento ya se aplicó.');
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRÁS
       DROP TABLE dbo.ind_recaudo_aplicaciones;
       DELETE FROM dbo.conf_migraciones WHERE mig_Nombre = '048_recaudo_aplicaciones';
   Los pagos ya quedaron en las declaraciones; solo se pierde la huella.
   ---------------------------------------------------------------------------- */
