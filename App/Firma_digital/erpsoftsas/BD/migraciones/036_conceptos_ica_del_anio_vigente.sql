/* ============================================================================
   036 — La liquidación del ICA toma las fórmulas del año vigente
   ----------------------------------------------------------------------------
   Revisión del 2026-09-28.

   EL PROBLEMA

   sp_calculo_comercio liquida los renglones 21 a 38 con las fórmulas de
   ind_Conceptos, y las pedía del año EXACTO de la declaración:

       WHERE con_Anio = @ANO_DECLARACION

   La tabla solo tiene fórmulas de 2026 (18 filas), y el año de la declaración
   es el año en que se crea (_agregarDeclaracion, date('Y'); el cliente quitó
   el selector de año). Desde el 1 de enero de 2027 -plena temporada del ICA-
   toda declaración nueva habría salido con el renglón 20 (se calcula aparte,
   antes del cursor) y todo lo demás en cero: sin avisos y tableros, sin
   bomberil, total a pagar $0, sin recibo de pago y sin PSE. Sin ningún error:
   el cursor simplemente no encontraba fórmulas.

   EL CAMBIO

   Para cada renglón rige la fórmula del año más reciente que no pase del
   declarado (el acuerdo vigente mientras no se expida el nuevo); si no hay
   ninguna anterior -una declaración de antes del primer año cargado-, la más
   antigua que haya. Es la misma regla de business/class.catalogoAnio.php para
   retención y autorretención, aplicada RENGLÓN POR RENGLÓN y no por año
   completo: Parámetros ICA > Conceptos crea las fórmulas de a una, así que si
   la Alcaldía carga para 2027 solo la del renglón que cambia (por ejemplo, la
   tarifa bomberil), los demás siguen con la de 2026 en vez de quedar en cero.
   Para quitar un renglón en un año nuevo se le carga una fórmula que dé 0.

   Para las declaraciones de 2026 el resultado es idéntico: todos los renglones
   tienen fórmula de 2026. Se conserva TODO lo demás del procedimiento tal cual
   (renglón 20, cursor, SQL dinámico, parámetros y orden); solo cambia de qué
   año se leen las fórmulas.

   RIESGO

   Ninguno sobre lo presentado: el procedimiento solo corre al liquidar o
   guardar un borrador. Re-ejecutable (CREATE OR ALTER); la vuelta atrás es
   volver a correr la 011.
   ============================================================================ */

SET NOCOUNT ON;
GO

DECLARE @candado INT;
EXEC @candado = sp_getapplock
     @Resource    = 'migracion_036_conceptos_ica_del_anio_vigente',
     @LockMode    = 'Exclusive',
     @LockOwner   = 'Session',
     @LockTimeout = 120000;

IF @candado < 0
BEGIN
    RAISERROR('No se pudo tomar el candado de la migracion 036 (otra corrida en curso).', 16, 1);
    SET NOEXEC ON;
END
GO


/* ---------------------------------------------------------------------------
   sp_calculo_comercio (migración 036, 2026-09-28): las fórmulas de cada
   renglón salen del año más reciente que no pase del declarado, o del más
   antiguo si no hay ninguno anterior. Antes: solo del año exacto, y desde el
   1 de enero de 2027 una declaración nueva no habría encontrado ninguna.
   --------------------------------------------------------------------------- */
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

   -- Cada renglón con la fórmula del año más reciente que no pase del
   -- declarado; si no hay ninguna anterior, la más antigua (migración 036).
   -- La fila de la declaración se sigue buscando con el año DECLARADO
   -- (@ANO en el SQL dinámico): lo que cambia es solo de dónde sale la fórmula.
   DECLARE CURSOR_CONCEPTOS CURSOR FOR
        SELECT c.con_Codigo, c.con_Observaciones
        FROM ind_Conceptos c
        WHERE c.con_Codigo > @POSICION_CONCEPTO
          AND c.con_Anio = COALESCE(
                (SELECT MAX(v.con_Anio) FROM ind_Conceptos v
                  WHERE v.con_Codigo = c.con_Codigo AND v.con_Anio <= @ANO_DECLARACION),
                (SELECT MIN(v.con_Anio) FROM ind_Conceptos v
                  WHERE v.con_Codigo = c.con_Codigo))
        ORDER BY CAST(c.con_Codigo AS INT);

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

PRINT '  + sp_calculo_comercio: fórmulas del año vigente por renglón';
GO


/* ---------------------------------------------------------------------------
   Registro y liberación del candado
   --------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '036_conceptos_ica_del_anio_vigente')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('036_conceptos_ica_del_anio_vigente',
            N'sp_calculo_comercio: cada renglón se liquida con la fórmula de ind_Conceptos del año más reciente que no pase del declarado (o la más antigua si no hay anterior). Antes pedía el año exacto y solo hay fórmulas de 2026: desde el 1 de enero de 2027 las declaraciones nuevas habrían salido con los renglones 21 a 38 en cero. Para 2026 el resultado no cambia.');
GO

SET NOEXEC OFF;
EXEC sp_releaseapplock @Resource = 'migracion_036_conceptos_ica_del_anio_vigente', @LockOwner = 'Session';
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRÁS

       Volver a correr BD/migraciones/011_liquidacion_sin_actividades.sql
       (CREATE OR ALTER, deja el procedimiento como estaba) y:

       DELETE FROM dbo.conf_migraciones WHERE mig_Nombre = '036_conceptos_ica_del_anio_vigente';
   ---------------------------------------------------------------------------- */
