/* ============================================================================
   031 — Las formulas de autorretencion que el cliente confirmo
   ----------------------------------------------------------------------------
   La migracion 030 dejo cinco casillas de AUTORRETEICA sin formula, en NULL,
   porque los tres documentos del cliente describian el mismo calculo de tres
   maneras y una de ellas cobraba casi el doble. El 2026-09-09 el cliente lo
   resolvio por escrito. Esta migracion las llena.

   QUE DIJO EL CLIENTE

     «En el total de energia se sumo por error con el valor superior; en
      realidad, ese rubro debe ser unicamente de 5 millones. En la casilla 15 si
      se deben sumar los 490 mas los 5 millones correspondientes a energia. Es
      la sumatoria de esos totales.»

   O sea: casilla 15 = impuesto de las actividades + impuesto de energia, UNA
   sola vez. Sobre su propio ejemplo, 490.000 + 5.000.000 = 5.490.000.

   QUE TENIA EL EXCEL, Y POR QUE ESTABA MAL

   Leido del archivo (FORMULARIO AUTORRETEICA (3).xlsx, Hoja1):

       P23 = P19+P20+P21+P22+I23    -> 490.000 + 5.000.000 = 5.490.000
                                       (el TOTAL ya incluye la energia)
       M24 = P23 + I23              -> 5.490.000 + 5.000.000 = 10.490.000
                                       (la energia entra por segunda vez)

   El arrastre llegaba hasta el final: el total a pagar salia 12.550.000 cuando
   son 6.800.000. Casi el doble, sobre un documento que firma el contribuyente.

   COMPROBACION CON EL PROPIO EJEMPLO DEL CLIENTE

     actividades  490.000        (70.000.000 al 7 x mil)
     energia    5.000.000
     15 = 490.000 + 5.000.000                    = 5.490.000
     16 = redondeo(5.490.000 x 15%, miles)       =   824.000
     17 = 5.490.000 + 824.000                    = 6.314.000
     18 = 64.000 (lo escribe el contribuyente)
     19 = 6.314.000 - 64.000                     = 6.250.000
     21 = 50.000   22 = 500.000
     23 = 6.250.000 - 0 + 50.000 + 500.000       = 6.800.000

   EL REDONDEO TAMBIEN QUEDA RESUELTO, Y NO POR ESTA CONVERSACION

   Estaba anotado como decision pendiente. No hacia falta: los DOS formularios
   nuevos lo dicen en sus propias formulas.

       RETEICA        O16 = MROUND((I16*K16/1000),1000)   <- fila por fila
       AUTORRETEICA   P19 = MROUND((M19*J19/1000),1000)   <- fila por fila
       AUTORRETEICA   M25 = MROUND(M24*15%,1000)          <- y la casilla 16

   Se redondea a MILES, FILA POR FILA. El ICA no se toca: redondea una sola vez
   sobre el total y es otro formulario. ROUND() de SQL Server redondea la mitad
   alejandose del cero, igual que MROUND de Excel, asi que 823.500 da 824.000 en
   los dos.

   LO QUE SIGUE SIN RESOLVER, Y NO SE INVENTA AQUI

   1. La casilla 20 (SALDO A FAVOR) queda MANUAL, como en el Excel del cliente
      -donde es una celda escrita a mano, sin formula-. Su etiqueta dice
      «Renglon 17-18 menor que 0», lo que sugiere que 19 y 20 son las dos ramas
      de la misma resta, pero el Excel no lo implementa asi: calcula 19 = 17-18
      siempre. Se deja como esta el archivo. Si el cliente confirma las ramas,
      son dos filas de esta tabla.

   2. La exencion de avisos y tableros. El formulario cobra el 15% a todos.
      ind_contribuyentes.ind_SinAvisosTableros existe y en el ICA SI exime
      (migracion 021). Aqui se deja como dice el formulario. Es una pregunta
      abierta al cliente, no un olvido: eximir a quien no corresponde y cobrar a
      quien esta exento son los dos errores, y ninguno lo puede decidir el
      programador.

   RIESGO

   Bajo en lo tecnico -son cuatro UPDATE sobre un catalogo-, pero cambia
   CIFRAS: toda declaracion de autorretencion que se liquide despues de esto da
   un resultado distinto al de antes. Ninguna esta presentada en produccion,
   porque el modulo no se ha desplegado.
   ============================================================================ */

SET NOCOUNT ON;
GO
SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
GO


/* ---------------------------------------------------------------------------
   Casilla 15 — AUTORRETENCIONES INDUSTRIA Y COMERCIO

   La suma del impuesto de las actividades mas el impuesto de energia. UNA vez.
   --------------------------------------------------------------------------- */
UPDATE dbo.ind_renglones_retencion
   SET ren_Formula = N'(SELECT ISNULL(SUM(a.aua_ValorImpuesto),0)
                          FROM dbo.ind_autorreteica_actividades a
                         WHERE a.aua_IdAutorreteica = ep.aut_Id AND a.aua_Activo = 1)
                     + ISNULL(ep.aut_ImpuestoEnergia,0)',
       ren_Nombre  = N'AUTORRETENCIONES INDUSTRIA Y COMERCIO'
 WHERE ren_Modulo = 'AUTORRETEICA' AND ren_Codigo = 15;
PRINT '  15 AUTORRETENCIONES ICA = actividades + energia (una sola vez)';
GO


/* ---------------------------------------------------------------------------
   Casilla 16 — AVISOS Y TABLEROS

   El 15% de la 15, redondeado a miles: MROUND(M24*15%,1000) del Excel.

   NO aplica la exencion de ind_SinAvisosTableros. Ver la nota de la cabecera.
   --------------------------------------------------------------------------- */
UPDATE dbo.ind_renglones_retencion
   SET ren_Formula = N'ROUND(ISNULL(ep.aut_ValorConcepto15,0) * 0.15 / 1000.0, 0) * 1000'
 WHERE ren_Modulo = 'AUTORRETEICA' AND ren_Codigo = 16;
PRINT '  16 AVISOS Y TABLEROS = 15% de la 15, redondeado a miles';
GO


/* ---------------------------------------------------------------------------
   Casilla 19 — TOTAL AUTORRETENCION LIQUIDADO

   17 menos 18, tal cual el Excel (M28 = M26-M27) y tal cual dice su etiqueta.
   --------------------------------------------------------------------------- */
UPDATE dbo.ind_renglones_retencion
   SET ren_Formula = N'ISNULL(ep.aut_ValorConcepto17,0) - ISNULL(ep.aut_ValorConcepto18,0)'
 WHERE ren_Modulo = 'AUTORRETEICA' AND ren_Codigo = 19;
PRINT '  19 TOTAL LIQUIDADO = 17 - 18';
GO


/* ---------------------------------------------------------------------------
   Casilla 20 — TOTAL SALDO A FAVOR

   MANUAL, como en el Excel. La formula se referencia A SI MISMA, que es la
   unica forma de que un renglon que llena el contribuyente sobreviva a los
   recalculos: con la formula en '0' -que es lo que traen las hojas del
   cliente-, cada liquidacion le escribiria cero encima de lo que la persona
   acaba de escribir. Ese fue el defecto que reporto en agosto y que arreglo la
   migracion 010 en el ICA.
   --------------------------------------------------------------------------- */
UPDATE dbo.ind_renglones_retencion
   SET ren_Formula = N'ep.aut_ValorConcepto20',
       ren_Manual  = 1
 WHERE ren_Modulo = 'AUTORRETEICA' AND ren_Codigo = 20;
PRINT '  20 SALDO A FAVOR = manual (pendiente de confirmar si debe calcularse)';
GO


/* ---------------------------------------------------------------------------
   Casilla 23 — TOTAL A PAGAR EN EL BIMESTRE

   19 - 20 + 21 + 22, que es M32 = M28-M29+M30+M31 del Excel.
   --------------------------------------------------------------------------- */
UPDATE dbo.ind_renglones_retencion
   SET ren_Formula = N'ISNULL(ep.aut_ValorConcepto19,0) - ISNULL(ep.aut_ValorConcepto20,0)
                     + ISNULL(ep.aut_ValorConcepto21,0) + ISNULL(ep.aut_ValorConcepto22,0)'
 WHERE ren_Modulo = 'AUTORRETEICA' AND ren_Codigo = 23;
PRINT '  23 TOTAL A PAGAR = 19 - 20 + 21 + 22';
GO


IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '031_formulas_autorretencion_confirmadas')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('031_formulas_autorretencion_confirmadas',
            'Llena las formulas que la 030 dejo en NULL, tras la confirmacion del cliente del 2026-09-09: la casilla 15 suma el impuesto de las actividades mas el de energia UNA sola vez. El Excel la sumaba dos veces (M24 = P23 + I23) y el total a pagar salia 12.550.000 en vez de 6.800.000. Quedan calculadas 15, 16, 19 y 23; la 20 manual, como en el Excel. El redondeo lo resuelven las propias formulas de los dos formularios: a miles y fila por fila. Sigue abierta la exencion de avisos.');
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRAS

       UPDATE dbo.ind_renglones_retencion SET ren_Formula = NULL
        WHERE ren_Modulo = 'AUTORRETEICA' AND ren_Codigo IN (15,16,19,23);
       UPDATE dbo.ind_renglones_retencion SET ren_Formula = NULL, ren_Manual = 0
        WHERE ren_Modulo = 'AUTORRETEICA' AND ren_Codigo = 20;
       DELETE FROM dbo.conf_migraciones
        WHERE mig_Nombre = '031_formulas_autorretencion_confirmadas';

   Las declaraciones ya liquidadas conservan las cifras hasta que se vuelvan a
   liquidar; una presentada no se recalcula nunca.
   ---------------------------------------------------------------------------- */
