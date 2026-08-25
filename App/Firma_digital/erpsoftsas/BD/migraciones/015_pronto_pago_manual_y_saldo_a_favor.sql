/* ============================================================================
   015 — El pronto pago deja de liquidarse solo, y el saldo a favor pone en
         cero las casillas que ya no aplican
   ----------------------------------------------------------------------------
   Pedido en la revision del 2026-08-25:

       "Que no liquide automaticamente el descuento por pronto pago"
       "Cuando el resultado de la operacion da saldo a favor (casilla 34)
        que coloque 0 en la casilla 33-35 y en la 38"

   Las casillas del formulario impreso y los conceptos de la base no llevan la
   misma numeracion. La correspondencia de las que se tocan aqui:

       casilla 33  ->  concepto 12   TOTAL SALDO A CARGO
       casilla 34  ->  concepto 13   TOTAL SALDO A FAVOR
       casilla 35  ->  concepto 14   VALOR A PAGAR
       casilla 36  ->  concepto 15   DESCUENTO POR PRONTO PAGO
       casilla 38  ->  concepto 20   TOTAL A PAGAR

   1. EL PRONTO PAGO PASA A SER MANUAL

   Hoy el concepto 15 se calcula solo:

       CASE WHEN ep.dec_FechaDeclaracion <= @FECHA_LIMITE
            THEN ROUND(dec_ValorConcepto1*0.1,-3) ELSE 0 END

   o sea, un 10% fijo si la declaracion entra antes de la fecha limite. Ese 10%
   no sale de ningun lado: el propio rotulo del formulario dice "si existe,
   liquidelo segun el acuerdo municipal o distrital", que es precisamente
   admitir que el porcentaje lo fija cada acuerdo y cambia cada año. Ademas
   depende de @FECHA_LIMITE, que viene de una casilla que el cliente ya pidio
   retirar en la revision anterior.

   Pasa a comportarse como los otros nueve renglones manuales (migracion 010):
   la formula se apunta a si misma para que el recalculo no la pise.

   2. EL SALDO A FAVOR APAGA LAS CASILLAS QUE NO APLICAN

   Cuando la liquidacion da negativo, hoy queda a la vez un "saldo a favor" en
   la 34 y un "valor a pagar" NEGATIVO en la 33, la 35 y la 38. Un valor a
   pagar en negativo no significa nada en el formulario: o se debe, o esta a
   favor. El cliente pide que en ese caso las tres queden en cero, que es como
   se diligencia el formulario en papel.

   No se toca el concepto 13: ya calculaba bien el saldo a favor.

   RIESGO

   Solo cambia como se RECALCULA de aqui en adelante. Los valores ya grabados
   no se tocan, y una declaracion presentada no se recalcula nunca. Las
   formulas anteriores quedan respaldadas en ind_conceptos_formulas_previas,
   asi que la vuelta atras es copiarlas de vuelta.
   ============================================================================ */

SET NOCOUNT ON;
GO

DECLARE @candado INT;
EXEC @candado = sp_getapplock
     @Resource    = 'migracion_015_pronto_pago',
     @LockMode    = 'Exclusive',
     @LockOwner   = 'Session',
     @LockTimeout = 120000;

IF @candado < 0
BEGIN
    RAISERROR('No se pudo tomar el candado de la migracion 015 (otra corrida en curso).', 16, 1);
    SET NOEXEC ON;
END
GO


/* ---------------------------------------------------------------------------
   0. Respaldo de las formulas que se van a cambiar
   --------------------------------------------------------------------------- */
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
 WHERE c.con_Codigo IN ('12', '14', '15', '20')
   AND NOT EXISTS (
        SELECT 1 FROM dbo.ind_conceptos_formulas_previas p
         WHERE p.con_Codigo = c.con_Codigo
           AND p.con_Anio   = c.con_Anio
           AND p.con_Observaciones = c.con_Observaciones);
PRINT '  = formulas anteriores respaldadas';
GO


/* ---------------------------------------------------------------------------
   1. Concepto 15 — el pronto pago lo escribe el usuario
   --------------------------------------------------------------------------- */
UPDATE dbo.ind_conceptos
   SET con_Observaciones    = 'ep.dec_ValorConcepto15',
       con_FechaActualizacion = GETDATE()
 WHERE con_Codigo = '15'
   AND con_Observaciones <> 'ep.dec_ValorConcepto15';
PRINT '  + concepto 15 (pronto pago) pasa a manual';
GO


/* ---------------------------------------------------------------------------
   2. Conceptos 12 y 14 — nunca en negativo
   --------------------------------------------------------------------------- */
UPDATE dbo.ind_conceptos
   SET con_Observaciones = 'CASE WHEN dec_ValorConcepto4-dec_ValorConcepto5-dec_ValorConcepto6-dec_ValorConcepto7-dec_ValorConcepto8+dec_ValorConcepto9+dec_ValorConcepto10-dec_ValorConcepto11 < 0 THEN 0 ELSE dec_ValorConcepto4-dec_ValorConcepto5-dec_ValorConcepto6-dec_ValorConcepto7-dec_ValorConcepto8+dec_ValorConcepto9+dec_ValorConcepto10-dec_ValorConcepto11 END',
       con_FechaActualizacion = GETDATE()
 WHERE con_Codigo IN ('12', '14');
PRINT '  + conceptos 12 y 14 quedan en cero cuando hay saldo a favor';
GO


/* ---------------------------------------------------------------------------
   3. Concepto 20 — total a pagar en cero si hay saldo a favor

   Se apoya en el concepto 13, que el procedimiento ya calculo antes de llegar
   aqui porque los recorre en orden de codigo.
   --------------------------------------------------------------------------- */
UPDATE dbo.ind_conceptos
   SET con_Observaciones = 'CASE WHEN dec_ValorConcepto13 > 0 THEN 0 ELSE dec_ValorConcepto14-dec_ValorConcepto15+dec_ValorConcepto16 END',
       con_FechaActualizacion = GETDATE()
 WHERE con_Codigo = '20';
PRINT '  + concepto 20 queda en cero cuando hay saldo a favor';
GO


/* ---------------------------------------------------------------------------
   4. Registro y liberacion del candado
   --------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '015_pronto_pago_manual_y_saldo_a_favor')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('015_pronto_pago_manual_y_saldo_a_favor',
            'El descuento por pronto pago (concepto 15) deja de calcularse solo con un 10% fijo atado a @FECHA_LIMITE y pasa a escribirlo el usuario, como los otros nueve renglones manuales. Y cuando la liquidacion da saldo a favor, los conceptos 12, 14 y 20 -casillas 33, 35 y 38- quedan en cero en vez de mostrar un valor a pagar negativo.');
GO

SET NOEXEC OFF;
EXEC sp_releaseapplock @Resource = 'migracion_015_pronto_pago', @LockOwner = 'Session';
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRAS

   Copiar de vuelta desde el respaldo, tomando la fila mas antigua de cada
   concepto (la de antes de esta migracion):

       UPDATE c
          SET c.con_Observaciones = p.con_Observaciones
         FROM dbo.ind_conceptos c
         JOIN (SELECT con_Codigo, con_Anio, con_Observaciones,
                      ROW_NUMBER() OVER (PARTITION BY con_Codigo, con_Anio ORDER BY fec_Respaldo) rn
                 FROM dbo.ind_conceptos_formulas_previas) p
           ON p.con_Codigo = c.con_Codigo AND p.con_Anio = c.con_Anio AND p.rn = 1
        WHERE c.con_Codigo IN ('12','14','15','20');
   ---------------------------------------------------------------------------- */
