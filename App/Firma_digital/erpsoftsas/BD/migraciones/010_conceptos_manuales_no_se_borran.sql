/* ============================================================================
   010 — Los renglones que escribe el contribuyente dejan de borrarse solos
   ----------------------------------------------------------------------------
   Reportado por el cliente el 2026-08-21:

     "PENDIENTE REVISAR LIQUIDACION SALDO A FAVOR ESTA MAL Y ANTICIPOS BORRAN
      OTROS DATOS"
     "al darle guardar no esta guardando los valores de la liquidacion"

   EL MECANISMO

   La liquidacion la hace sp_calculo_comercio. Ese procedimiento recorre
   ind_Conceptos y, por cada renglon, arma y ejecuta:

       UPDATE ep SET ep.[dec_ValorConceptoN] = ( <con_Observaciones> )
       FROM ind_declaraciones_ica ep WHERE ...

   O sea que con_Observaciones NO es un comentario: es la formula que se
   ejecuta. Y NUEVE renglones que el contribuyente diligencia A MANO tienen
   como formula el literal '0':

       5  VALOR DE EXENCION O EXONERACION
       6  (-) MENOS RETENCIONES
       7  MENOS AUTORETENCIONES
       8  (-) ANTICIPO AÑO ANTERIOR
       9  (+) ANTICIPO AÑO SIGUIENTE
      10  SANCIONES
      11  SALDO A FAVOR VIGENCIAS ANTERIORES
      16  INTERES DE MORA
      17  PAPELERIA MUNICIPAL

   El procedimiento no distingue "campo calculado" de "campo escrito": los
   recorre todos. Asi que cada recalculo escribia CERO justo encima de lo que
   la persona acababa de digitar. El parametro @POSICION_CONCEPTO solo protege
   los renglones ANTERIORES al que se edito, y el guardado general pasa 0, de
   modo que los nueve se borran de una.

   Ademas arrastraba a los totales: los renglones 12, 13, 14 y 20 restan esos
   valores, y al leerlos en cero calculaban de mas. De ahi que "el saldo a
   favor este mal".

   EL ARREGLO

   Cambiar la formula de esos nueve por una referencia a si mismos:

       dec_ValorConcepto8  ->  'ep.dec_ValorConcepto8'

   El UPDATE se vuelve idempotente -escribe el valor que ya tenia- y deja de
   borrar. Comprobado en tempdb: 'SET ep.[x] = (ep.x)' conserva 500000.

   POR QUE ASI Y NO TOCANDO EL PROCEDIMIENTO

   La otra via seria agregar una columna con_EsManual y filtrar el cursor. Es
   mas explicita, pero obliga a modificar sp_calculo_comercio, que es la pieza
   mas delicada del sistema y la que calcula el impuesto de verdad. Cambiar
   nueve filas de datos es reversible con un UPDATE; cambiar el procedimiento
   no lo es de la misma manera. Si mas adelante se agregan conceptos manuales
   nuevos, la regla queda escrita aqui y en CLAUDE.md.

   LO QUE ESTA MIGRACION NO HACE

   NO corrige las declaraciones que ya quedaron con ceros grabados. Solo
   cambia el calculo de aqui en adelante. Una declaracion PRESENTADA es un
   acto legal y no se puede recalcular por detras: la via es "Corregir". En la
   base local hay 2 presentadas; en produccion hay que contarlas antes.

   Re-ejecutable: solo toca las filas cuya formula sea exactamente '0'.
   ============================================================================ */

/* ---------------------------------------------------------------------------
   0. Candado
   --------------------------------------------------------------------------- */
DECLARE @candado INT;
EXEC @candado = sp_getapplock
     @Resource    = 'migracion_010_conceptos_manuales',
     @LockMode    = 'Exclusive',
     @LockOwner   = 'Session',
     @LockTimeout = 120000;

IF @candado < 0
BEGIN
    RAISERROR('No se pudo tomar el candado de la migracion 010 (otra corrida en curso).', 16, 1);
    SET NOEXEC ON;
END
GO


/* ---------------------------------------------------------------------------
   1. Respaldo de las formulas, dentro de la propia base

   Antes de tocar nada. Si hubiera que devolverlo:
       UPDATE c SET c.con_Observaciones = b.con_Observaciones
         FROM ind_conceptos c
         JOIN ind_conceptos_formulas_previas b
           ON b.con_Anio = c.con_Anio AND b.con_Codigo = c.con_Codigo;
   --------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_NAME = 'ind_conceptos_formulas_previas')
BEGIN
    SELECT con_Anio, con_Codigo, con_Nombre, con_Observaciones,
           fec_Respaldo = GETDATE()
      INTO dbo.ind_conceptos_formulas_previas
      FROM dbo.ind_conceptos;

    PRINT 'Formulas respaldadas en ind_conceptos_formulas_previas.';
END
ELSE PRINT 'El respaldo de formulas ya existia; no se sobreescribe.';
GO


/* ---------------------------------------------------------------------------
   2. Los nueve renglones manuales pasan a conservarse

   Se filtra por con_Observaciones = '0' y por la lista explicita de codigos:
   las dos condiciones a la vez. Si algun dia un concepto CALCULADO tuviera
   legitimamente la formula '0', no se toca por no estar en la lista.
   --------------------------------------------------------------------------- */
UPDATE dbo.ind_conceptos
   SET con_Observaciones = 'ep.dec_ValorConcepto' + CAST(con_Codigo AS VARCHAR(10)),
       con_FechaActualizacion = GETDATE()
 WHERE LTRIM(RTRIM(con_Observaciones)) = '0'
   AND con_Codigo IN (5, 6, 7, 8, 9, 10, 11, 16, 17);

PRINT CAST(@@ROWCOUNT AS VARCHAR(10)) + ' conceptos manuales dejan de borrarse.';
GO


/* ---------------------------------------------------------------------------
   3. Informe: como quedaron los 18 conceptos
   --------------------------------------------------------------------------- */
SELECT con_Anio,
       con_Codigo,
       con_Nombre,
       con_Observaciones,
       tipo = CASE
                WHEN con_Observaciones LIKE 'ep.dec_ValorConcepto%'
                     AND con_Codigo IN (5,6,7,8,9,10,11,16,17) THEN 'MANUAL (se conserva)'
                WHEN LTRIM(RTRIM(con_Observaciones)) = '0'      THEN '*** SIGUE EN CERO ***'
                ELSE 'calculado'
              END
  FROM dbo.ind_conceptos
 ORDER BY con_Anio, CAST(con_Codigo AS INT);
GO

/* Ninguna fila deberia decir "SIGUE EN CERO". */
GO


/* ---------------------------------------------------------------------------
   4. Registro y liberacion del candado
   --------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '010_conceptos_manuales_no_se_borran')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('010_conceptos_manuales_no_se_borran',
            'Los nueve renglones de captura manual (exencion, retenciones, autorretenciones, anticipos, sanciones, saldo a favor anterior, interes de mora, papeleria) tenian como formula el literal 0, y sp_calculo_comercio los ponia en cero en cada recalculo, borrando lo que el contribuyente escribia y descuadrando los totales. Pasan a referirse a si mismos. No corrige las declaraciones ya grabadas con ceros. Respaldo en ind_conceptos_formulas_previas.');
GO

SET NOEXEC OFF;
EXEC sp_releaseapplock @Resource = 'migracion_010_conceptos_manuales', @LockOwner = 'Session';
GO
