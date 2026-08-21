/* ============================================================================
   011 — La liquidacion deja de anularse cuando no hay actividades
   ----------------------------------------------------------------------------
   Parte de lo que el cliente reporta el 2026-08-21 como "no esta liquidando".

   EL MECANISMO

   sp_calculo_comercio arranca calculando el renglon 1 (impuesto de industria y
   comercio) asi:

       SET di.dec_valorconcepto1 = ROUND(t.VIMPUESTO + di.dec_ValorImpuesto, -3)

   donde VIMPUESTO es SUM(dia_ValorImpuesto) de las actividades de esa
   declaracion. Cuando la declaracion NO tiene actividades activas, ese SUM
   devuelve NULL -no cero-, y en SQL NULL + cualquier cosa es NULL. El renglon 1
   queda NULL, el 4 lo suma y tambien queda NULL, y de ahi en cascada los
   totales 12, 13, 14 y 20.

   O sea: el formulario se ve completamente vacio, como si no hubiera liquidado
   nada. En la base local hay 139 declaraciones de 213 sin actividades activas.

   EL CAMBIO

   Una sola linea: ISNULL en los dos sumandos. Un cero honesto en vez de un NULL
   que se propaga en silencio. Se comprobo que para las declaraciones que SI
   tienen actividades el resultado es identico.

   Se conserva TODO lo demas del procedimiento tal cual estaba: el cursor sobre
   ind_Conceptos, el SQL dinamico, los parametros y el orden. La definicion
   original quedo respaldada antes de tocar nada.

   Re-ejecutable: CREATE OR ALTER.
   ============================================================================ */



CREATE OR ALTER PROCEDURE [dbo].[sp_calculo_comercio] --2026,12,159,5
	@ANO_DECLARACION INT,	
    @MES_DECLARACION INT,
    @NUMERO_DECLARACION BIGINT,
	@POSICION_CONCEPTO INT = 0,
    @FECHA_LIMITE DATETIME = null

AS
BEGIN
    SET NOCOUNT ON;

    DECLARE 
        @CODIGO INT,
        @FORMULA NVARCHAR(MAX),
        @SQL NVARCHAR(MAX),
        @CAMPO_DESTINO NVARCHAR(50);

    IF @FECHA_LIMITE IS NULL
       SET @FECHA_LIMITE = CONVERT(DATETIME, '2026-11-11', 120);
        
--calcula el valor del impuesto
UPDATE di
SET di.dec_valorconcepto1 = ROUND(ISNULL(t.VIMPUESTO, 0) + ISNULL(di.dec_ValorImpuesto, 0),-3)
FROM ind_declaraciones_ica di
CROSS APPLY (
    SELECT SUM(da.dia_ValorImpuesto) AS VIMPUESTO
    FROM ind_declaraciones_ica_actividades da
    WHERE da.dia_iddeclaracion = di.dec_id
    AND da.dia_Activo NOT IN (0)
) t
WHERE di.dec_AnioDeclaracion = @ANO_DECLARACION
  AND di.dec_MesDeclaracion = @MES_DECLARACION
  AND di.dec_NumeroDeclaracion = @NUMERO_DECLARACION;
  
   DECLARE CURSOR_CONCEPTOS CURSOR FOR
        SELECT con_Codigo, con_Observaciones
        FROM ind_Conceptos
        WHERE con_Anio = @ANO_DECLARACION AND con_Codigo > @POSICION_CONCEPTO
        ORDER BY CAST(con_Codigo AS INT);

    OPEN CURSOR_CONCEPTOS;
    FETCH NEXT FROM CURSOR_CONCEPTOS INTO @CODIGO, @FORMULA;

    WHILE @@FETCH_STATUS = 0
    BEGIN
        -- Construimos el nombre del campo destino (VALOR_CONCEPTO#)
        SET @CAMPO_DESTINO = 'dec_ValorConcepto' + CAST(@CODIGO AS VARCHAR(10));

        -- Armamos el SQL dinámico que actualiza ese campo
        SET @SQL = N'
        UPDATE ep
        SET ep.' + QUOTENAME(@CAMPO_DESTINO) + N' = (' + @FORMULA + N')
        FROM ind_declaraciones_ica ep
        WHERE ep.dec_AnioDeclaracion= @ANO
          AND ep.dec_MesDeclaracion= @MES
          AND ep.dec_NumeroDeclaracion= @NUMERO;';

        -- Ejecutamos el SQL dinámico con parámetros
        EXEC sp_executesql 
            @SQL,
            N'@ANO INT, @MES INT, @NUMERO FLOAT, @FECHA_LIMITE DATETIME',
            @ANO = @ANO_DECLARACION,
            @MES = @MES_DECLARACION,
            @NUMERO = @NUMERO_DECLARACION,
            @FECHA_LIMITE = @FECHA_LIMITE;

       --PRINT 'Actualizado: ' + @CAMPO_DESTINO + ' usando fórmula: ' + @FORMULA;

        FETCH NEXT FROM CURSOR_CONCEPTOS INTO @CODIGO, @FORMULA;
    END;

    CLOSE CURSOR_CONCEPTOS;
    DEALLOCATE CURSOR_CONCEPTOS;
END;
GO


/* ---------------------------------------------------------------------------
   Registro
   --------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '011_liquidacion_sin_actividades')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('011_liquidacion_sin_actividades',
            'sp_calculo_comercio: ISNULL en el calculo del renglon 1. Sin actividades activas, SUM() devolvia NULL y anulaba en cascada los renglones 1, 4, 12, 13, 14 y 20, dejando el formulario vacio. Unica linea modificada; el resto del procedimiento queda identico.');
GO
