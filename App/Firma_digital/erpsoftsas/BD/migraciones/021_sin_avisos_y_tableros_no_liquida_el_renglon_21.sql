/* ============================================================================
   021 — Quien esta exento de avisos y tableros no lo liquida
   ----------------------------------------------------------------------------
   Pedido del cliente el 2026-08-26: "si en el RIT activo el valor de exento de
   avisos y tableros, aqui en el 21 va en 0".

   EL RENGLON 21 ES EL IMPUESTO DE AVISOS Y TABLEROS

   Se calcula solo, como el 15% del renglon 20:

       ROUND(dec_ValorConcepto1*0.15,-3)

   La casilla "Sin Avisos y Tableros" del RIT (columna ind_SinAvisosTableros,
   migracion 016) existia desde el 25 de agosto pero no cambiaba ni una cifra:
   se imprimia en el formulario y ahi se quedaba. Un contribuyente marcado como
   exento seguia liquidando el 15% igual que los demas.

   POR QUE LA FORMULA Y NO EL CODIGO

   Porque aqui es donde vive el calculo. sp_calculo_comercio no tiene las
   formulas escritas: las lee de esta columna y las inyecta como el SET de

       UPDATE ep SET ep.<campo> = (<formula>) FROM ind_declaraciones_ica ep

   Eso significa dos cosas. Una, que ep.dec_IdContribuyente esta a la vista
   dentro de la formula, asi que una subconsulta al contribuyente es legitima y
   no un truco. Y otra mas importante: si la exencion se aplicara en PHP o en
   el navegador, habria DOS liquidadores -uno para la pantalla y otro para el
   procedimiento- y el dia que se recalculara por cualquier otra via volveria a
   aparecer el 15%. La exencion tiene que vivir donde vive el calculo.

   EL COSTO

   La subconsulta corre una vez por declaracion liquidada, sobre la clave
   primaria de ind_contribuyentes. No hay tabla que recorrer.

   NO TOCA LO YA LIQUIDADO

   Cambia como se calcula de aqui en adelante, no lo calculado. Una declaracion
   presentada conserva su renglon 21 tal como se presento, que es lo correcto:
   es un acto ya firmado. Si alguna hay que corregir, se corrige por su via.

   RIESGO

   Bajo, y acotado a los contribuyentes con la casilla marcada. Para los demas
   -que hoy son todos menos los que la activen- el CASE cae al ELSE y la
   formula es literalmente la de antes.
   ============================================================================ */

SET NOCOUNT ON;
GO

/* ---------------------------------------------------------------------------
   Guarda: sin la columna de la migracion 016 esto no tiene sentido
   --------------------------------------------------------------------------- */
IF COL_LENGTH('dbo.ind_contribuyentes', 'ind_SinAvisosTableros') IS NULL
BEGIN
    RAISERROR('Falta ind_contribuyentes.ind_SinAvisosTableros: aplique antes la migracion 016.', 16, 1);
    SET NOEXEC ON;
END
GO


DECLARE @formula VARCHAR(500) =
    'CASE WHEN EXISTS (SELECT 1 FROM ind_contribuyentes c'
  + ' WHERE c.ind_Id = ep.dec_IdContribuyente'
  + ' AND ISNULL(c.ind_SinAvisosTableros, 0) = 1)'
  + ' THEN 0 ELSE ROUND(dec_ValorConcepto1*0.15,-3) END';

/* Se identifica por con_Codigo, que es lo que recorre el cursor del
   procedimiento, y no por con_Id. Y se cubren TODOS los años: la tabla tiene
   una fila de conceptos por año y el cursor filtra por con_Anio, asi que
   arreglar solo el año en curso dejaria el 15% vivo en los demas. */
UPDATE dbo.ind_Conceptos
   SET con_Observaciones      = @formula,
       con_FechaActualizacion = SYSDATETIME()
 WHERE con_Codigo = '2'
   AND con_Observaciones <> @formula;

PRINT '  + renglon 21 (avisos y tableros): ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' año(s) actualizado(s)';
GO


IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '021_sin_avisos_y_tableros_no_liquida_el_renglon_21')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('021_sin_avisos_y_tableros_no_liquida_el_renglon_21',
            'La casilla "Sin Avisos y Tableros" del RIT (ind_SinAvisosTableros, migracion 016) pasa a tener efecto sobre la liquidacion: el renglon 21 sale en 0 para el contribuyente que la tenga marcada. Se hace en la formula de ind_Conceptos y no en codigo, porque es ahi donde sp_calculo_comercio lee el calculo; hacerlo fuera dejaria dos liquidadores que se separarian. No altera declaraciones ya liquidadas.');
GO

SET NOEXEC OFF;
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRAS

       UPDATE dbo.ind_Conceptos
          SET con_Observaciones = 'ROUND(dec_ValorConcepto1*0.15,-3)'
        WHERE con_Codigo = '2';

       DELETE FROM dbo.conf_migraciones
        WHERE mig_Nombre = '021_sin_avisos_y_tableros_no_liquida_el_renglon_21';
   ---------------------------------------------------------------------------- */
