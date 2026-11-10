/* Esquema base del sistema de ICA: PROCEDIMIENTOS, FUNCIONES, VISTAS Y DISPARADORES (CREATE OR ALTER: se pueden repetir). */
SET NOCOUNT ON;
SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
GO
SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
GO
CREATE OR ALTER PROCEDURE [dbo].[sp_buscar_predios]
    @dato NVARCHAR(100) = NULL
AS
BEGIN
    SET NOCOUNT ON;

    -- 🔹 Tabla temporal solo si hay filtro
    DECLARE @pat NVARCHAR(120) = NULL;
    IF (ISNULL(@dato, '') <> '') SET @pat = '%' + @dato + '%';

    ---------------------------------------------------
    -- 1️⃣ Trae propietarios con CROSS APPLY (evita subconsultas repetitivas)
    ---------------------------------------------------
    SELECT TOP (5)
        p.codigo_predio,
        UPPER(LTRIM(RTRIM(ISNULL(pr.nombre, '')))) AS nombre,
        LTRIM(RTRIM(p.direccion)) AS direccion,
        CASE 
            WHEN EXISTS (
                SELECT 1 
                FROM [erpsofts_paipa_test].dbo.predios_pagos AS pg
                WHERE pg.id_predio = p.id
                  AND ISNULL(pg.pagado, 0) <> -1
            ) THEN 1 ELSE 0
        END AS tiene_deuda
    FROM [erpsofts_paipa_test].dbo.predios AS p
    CROSS APPLY (
        SELECT TOP 1 pr.nombre
        FROM [erpsofts_paipa_test].dbo.predios_propietarios AS rel
        INNER JOIN [erpsofts_paipa_test].dbo.propietarios AS pr
            ON pr.id = rel.id_propietario
        WHERE rel.id_predio = p.id
        ORDER BY pr.id ASC
    ) AS pr
    WHERE
        @pat IS NULL
        OR p.codigo_predio LIKE @pat
        OR pr.nombre LIKE @pat
        OR p.direccion LIKE @pat
    ORDER BY p.codigo_predio;
END



/*
ALTER PROCEDURE [dbo].[sp_buscar_predios] --'0000000000040342000000000'
    @dato NVARCHAR(100) = NULL
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @t TABLE (
        codigo_predio NVARCHAR(50),
        nombre NVARCHAR(200),
        direccion NVARCHAR(300),
        tiene_deuda BIT
    );

    ---------------------------------------------------
    -- 🔹 CONSULTA REAL A LA BASE DE DATOS ERPSOFTS_PAIPA
    ---------------------------------------------------
    INSERT INTO @t (codigo_predio, nombre, direccion, tiene_deuda)
    SELECT 
        p.codigo_predio,
		UPPER(LTRIM(RTRIM(
            ISNULL((
                SELECT TOP 1 pr.nombre
                FROM [erpsofts_paipa_test].dbo.predios_propietarios AS rel2
                INNER JOIN [erpsofts_paipa_test].dbo.propietarios AS pr
                    ON pr.id = rel2.id_propietario
                WHERE rel2.id_predio = p.id
                ORDER BY pr.id ASC
            ), '')
        ))) AS nombre,

        LTRIM(RTRIM(p.direccion)) AS direccion,
        CASE 
            WHEN EXISTS (
                SELECT 1 
                FROM [erpsofts_paipa_test].dbo.predios_pagos AS pg
                WHERE pg.id_predio = p.id
                  AND ISNULL(pg.pagado, 0) <> -1
            ) THEN 1   -- Tiene deuda
            ELSE 0     -- Está al día
        END AS tiene_deuda
    FROM [erpsofts_paipa_test].dbo.predios AS p
    

    ---------------------------------------------------
    -- 🔹 FILTRO DE BÚSQUEDA
    ---------------------------------------------------
    IF (@dato IS NULL OR LTRIM(RTRIM(@dato)) = '')
    BEGIN
        SELECT codigo_predio, nombre, direccion, tiene_deuda
        FROM @t;
        RETURN;
    END

    DECLARE @pat NVARCHAR(120) = '%' + @dato + '%';

    SELECT codigo_predio, nombre, direccion, tiene_deuda
    FROM @t
    WHERE codigo_predio LIKE @pat
       OR nombre LIKE @pat
       OR direccion LIKE @pat;
END

*/
GO
SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
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
SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
GO

/* 3. El repartidor del ICA ---------------------------------------------------- */
CREATE OR ALTER PROCEDURE dbo.sp_siguiente_numero_declaracion
    @ANIO   INT,
    @NUMERO BIGINT OUTPUT
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @consecutivo INT;

    -- Base de la serie (043): 0 donde no se sembró, que es numerar como antes.
    DECLARE @base INT = ISNULL((SELECT cse_Valor FROM dbo.ind_consecutivos
                                 WHERE cse_Tipo = 'BASE_DECLARACION_ICA' AND cse_Anio = 0), 0);

    BEGIN TRANSACTION;

        -- La fila del año se crea la primera vez, arrancando por encima de lo
        -- que ya exista con ese prefijo y nunca por debajo de la base. Con
        -- bloqueo: dos sesiones en el primer número del año chocaban aquí.
        IF NOT EXISTS (SELECT 1 FROM dbo.ind_consecutivos WITH (UPDLOCK, HOLDLOCK)
                        WHERE cse_Tipo = 'DECLARACION_ICA' AND cse_Anio = @ANIO)
        BEGIN
            DECLARE @arranque INT = (
                SELECT ISNULL(MAX(dec_NumeroDeclaracion - (CAST(@ANIO AS BIGINT) * 1000000)), 0)
                  FROM dbo.ind_declaraciones_ica
                 WHERE dec_NumeroDeclaracion BETWEEN (CAST(@ANIO AS BIGINT) * 1000000)
                                                 AND ((CAST(@ANIO AS BIGINT) * 1000000) + 999999)
            );
            IF @arranque < @base SET @arranque = @base;
            IF @arranque < 0 SET @arranque = 0;

            INSERT INTO dbo.ind_consecutivos (cse_Tipo, cse_Anio, cse_Valor, cse_FechaActualizacion)
            VALUES ('DECLARACION_ICA', @ANIO, @arranque, GETDATE());
        END

        -- Asignación y lectura en la MISMA sentencia: dos sesiones simultáneas
        -- no pueden llevarse el mismo número.
        UPDATE dbo.ind_consecutivos
           SET @consecutivo = cse_Valor = cse_Valor + 1,
               cse_FechaActualizacion = GETDATE()
         WHERE cse_Tipo = 'DECLARACION_ICA' AND cse_Anio = @ANIO;

        -- Con base, la serie tiene 99.999 números: el siguiente sería ya de
        -- otro impuesto.
        IF @base > 0 AND @consecutivo > @base + 99999
        BEGIN
            ROLLBACK TRANSACTION;
            RAISERROR('Se agotó la serie de números de Industria y Comercio del año %d.', 16, 1, @ANIO);
            RETURN;
        END

    COMMIT TRANSACTION;

    SET @NUMERO = (CAST(@ANIO AS BIGINT) * 1000000) + @consecutivo;
END;
GO
SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
GO

/* 3. El repartidor de retención y autorretención ------------------------------ */
CREATE OR ALTER PROCEDURE dbo.sp_siguiente_numero_retencion
    @TIPO   VARCHAR(20),      -- 'RETEICA' o 'AUTORRETEICA'
    @ANIO   INT,
    @NUMERO BIGINT OUTPUT
AS
BEGIN
    SET NOCOUNT ON;

    IF @TIPO NOT IN ('RETEICA','AUTORRETEICA')
    BEGIN
        RAISERROR('Tipo de retencion no valido: %s', 16, 1, @TIPO);
        RETURN;
    END

    DECLARE @consecutivo INT;
    DECLARE @base INT = ISNULL((SELECT cse_Valor FROM dbo.ind_consecutivos
                                 WHERE cse_Tipo = 'BASE_' + @TIPO AND cse_Anio = 0), 0);

    BEGIN TRANSACTION;

        IF NOT EXISTS (SELECT 1 FROM dbo.ind_consecutivos WITH (UPDLOCK, HOLDLOCK)
                        WHERE cse_Tipo = @TIPO AND cse_Anio = @ANIO)
        BEGIN
            /* Arranca por encima de lo que ya exista con ese prefijo, y nunca
               por debajo de la base. */
            DECLARE @arranque INT = 0;

            IF @TIPO = 'RETEICA'
                SELECT @arranque = ISNULL(MAX(ret_NumeroDeclaracion - (CAST(@ANIO AS BIGINT) * 1000000)), 0)
                  FROM dbo.ind_reteica
                 WHERE ret_NumeroDeclaracion BETWEEN (CAST(@ANIO AS BIGINT) * 1000000)
                                                 AND ((CAST(@ANIO AS BIGINT) * 1000000) + 999999);
            ELSE
                SELECT @arranque = ISNULL(MAX(aut_NumeroDeclaracion - (CAST(@ANIO AS BIGINT) * 1000000)), 0)
                  FROM dbo.ind_autorreteica
                 WHERE aut_NumeroDeclaracion BETWEEN (CAST(@ANIO AS BIGINT) * 1000000)
                                                 AND ((CAST(@ANIO AS BIGINT) * 1000000) + 999999);

            IF @arranque < @base SET @arranque = @base;
            IF @arranque < 0 SET @arranque = 0;

            INSERT INTO dbo.ind_consecutivos (cse_Tipo, cse_Anio, cse_Valor, cse_FechaActualizacion)
            VALUES (@TIPO, @ANIO, @arranque, GETDATE());
        END

        UPDATE dbo.ind_consecutivos
           SET @consecutivo = cse_Valor = cse_Valor + 1,
               cse_FechaActualizacion = GETDATE()
         WHERE cse_Tipo = @TIPO AND cse_Anio = @ANIO;

        IF @base > 0 AND @consecutivo > @base + 99999
        BEGIN
            ROLLBACK TRANSACTION;
            RAISERROR('Se agotó la serie de números de %s del año %d.', 16, 1, @TIPO, @ANIO);
            RETURN;
        END

    COMMIT TRANSACTION;

    SET @NUMERO = (CAST(@ANIO AS BIGINT) * 1000000) + @consecutivo;
END;
GO
SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
GO
