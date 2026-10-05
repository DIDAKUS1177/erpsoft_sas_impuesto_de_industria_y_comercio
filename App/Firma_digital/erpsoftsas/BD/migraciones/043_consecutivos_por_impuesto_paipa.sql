/* ============================================================================
   043 — En Paipa, cada impuesto con su serie: ICA 20261xxxxx, retención
         20262xxxxx, autorretención 20263xxxxx
   ----------------------------------------------------------------------------
   Pedido de la Alcaldía de Paipa (Juan, 2026-10-05): el consecutivo va en
       Industria y Comercio     2026100000
       Retención de ICA         2026200000
       Autorretención           2026300000

   El número sigue siendo AAAA + seis cifras (migraciones 029 y 030); ahora la
   primera de las seis dice el impuesto. Cabe en el formato de siempre, en la
   columna y en el código de barras (8020, 24 cifras). De paso los tres módulos
   dejan de repartir los mismos números: 2026000001 existía en los tres y el
   recaudo por archivo tenía que mandar esos pagos a "Revisar a mano".

   SOLO PAIPA. La base de cada serie vive en ind_consecutivos (cse_Anio = 0, como
   ESTABLECIMIENTO y CORTE_DUPLICADOS) y solo se siembra en
   erpsofts_ind_comercio_paip. En las demás bases los procedimientos encuentran
   base 0 y numeran exactamente como antes.

   QUÉ HACE
   1. Las tres bases, solo en Paipa: BASE_DECLARACION_ICA 100000,
      BASE_RETEICA 200000 y BASE_AUTORRETEICA 300000.
   2. Sube a su base los contadores que van por debajo: la próxima ICA de 2026
      sale 2026100001, la próxima retención 2026200001 y la próxima
      autorretención 2026300001.
   3. Los dos procedimientos que reparten números abren la serie de un año
      nuevo en la base (la primera ICA de 2027 será 2027100001) y no dejan pasar
      de base + 99.999, porque ahí empieza la serie del impuesto siguiente.
      Además toman la fila del año con bloqueo: dos declaraciones creadas al
      mismo tiempo en un año nuevo chocaban al crear esa fila.

   NO RENUMERA NADA. Las declaraciones ya emitidas (2026000001...) conservan su
   número, que ya va impreso y dentro de códigos de barras.

   RIESGO: bajo. Tres filas nuevas y tres contadores subidos, solo en Paipa, y
   dos procedimientos que con base 0 hacen lo mismo que antes. Re-ejecutable.
   ============================================================================ */

SET NOCOUNT ON;
GO

/* QUOTED_IDENTIFIER: CREATE PROCEDURE congela el ajuste vigente, y la tabla de
   declaraciones tiene un índice filtrado (ver 029). */
SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
GO

/* 1 y 2. Las bases y los contadores, solo en Paipa ----------------------------- */
IF DB_NAME() = 'erpsofts_ind_comercio_paip'
BEGIN
    IF NOT EXISTS (SELECT 1 FROM dbo.ind_consecutivos WHERE cse_Tipo = 'BASE_DECLARACION_ICA' AND cse_Anio = 0)
        INSERT INTO dbo.ind_consecutivos (cse_Tipo, cse_Anio, cse_Valor, cse_FechaActualizacion)
        VALUES ('BASE_DECLARACION_ICA', 0, 100000, GETDATE());

    IF NOT EXISTS (SELECT 1 FROM dbo.ind_consecutivos WHERE cse_Tipo = 'BASE_RETEICA' AND cse_Anio = 0)
        INSERT INTO dbo.ind_consecutivos (cse_Tipo, cse_Anio, cse_Valor, cse_FechaActualizacion)
        VALUES ('BASE_RETEICA', 0, 200000, GETDATE());

    IF NOT EXISTS (SELECT 1 FROM dbo.ind_consecutivos WHERE cse_Tipo = 'BASE_AUTORRETEICA' AND cse_Anio = 0)
        INSERT INTO dbo.ind_consecutivos (cse_Tipo, cse_Anio, cse_Valor, cse_FechaActualizacion)
        VALUES ('BASE_AUTORRETEICA', 0, 300000, GETDATE());
END
GO

/* Donde haya base (Paipa), los contadores de cada año que vayan por debajo
   suben a ella. Sin base, este UPDATE no encuentra nada. */
UPDATE c
   SET cse_Valor = b.cse_Valor, cse_FechaActualizacion = GETDATE()
  FROM dbo.ind_consecutivos c
  JOIN dbo.ind_consecutivos b ON b.cse_Anio = 0 AND b.cse_Tipo = 'BASE_' + c.cse_Tipo
 WHERE c.cse_Tipo IN ('DECLARACION_ICA', 'RETEICA', 'AUTORRETEICA')
   AND c.cse_Anio > 0
   AND c.cse_Valor < b.cse_Valor;
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

IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '043_consecutivos_por_impuesto_paipa')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('043_consecutivos_por_impuesto_paipa',
            N'En Paipa cada impuesto tiene su serie: ICA 20261xxxxx, retención 20262xxxxx y autorretención 20263xxxxx (bases en ind_consecutivos, cse_Anio = 0, solo en erpsofts_ind_comercio_paip). Los repartidores abren cada año en la base, no pasan de base + 99.999 y toman la fila del año con bloqueo. No renumera nada.');
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRÁS (solo Paipa)

       DELETE FROM dbo.ind_consecutivos WHERE cse_Tipo LIKE 'BASE[_]%' AND cse_Anio = 0;
       DELETE FROM dbo.conf_migraciones WHERE mig_Nombre = '043_consecutivos_por_impuesto_paipa';

   Los contadores NO se bajan: los números de la serie nueva ya emitidos
   chocarían. Sin las bases, los procedimientos siguen subiendo desde donde van.
   ---------------------------------------------------------------------------- */
