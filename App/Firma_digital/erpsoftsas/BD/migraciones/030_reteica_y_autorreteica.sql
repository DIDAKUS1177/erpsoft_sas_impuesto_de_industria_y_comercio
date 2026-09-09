/* ============================================================================
   030 — Las tablas de RETEICA y AUTORRETEICA
   ----------------------------------------------------------------------------
   Dos modulos nuevos: la declaracion de RETENCION de ICA (mensual) y la de
   AUTORRETENCION (bimestral). Hasta hoy solo existian cuatro pantallas vacias
   y sus entradas de menu.

   QUE NO SE CREA, PORQUE YA EXISTE

     ind_actividadescomercio  El cliente entrego dos hojas de actividades para
                              estos modulos. Se compararon: 68 y 69 filas,
                              identicas entre si salvo el codigo 219 -que es un
                              duplicado del 218-, con las MISMAS tarifas, y son
                              las mismas que ya usa el ICA. No se carga ningun
                              catalogo nuevo.
     ind_contribuyentes       El encabezado de los dos formularios sale del RIT
                              y no se edita.
     ind_consecutivos         El mismo repartidor de numeros, con una fila por
                              modulo y año.
     firmas_declaraciones     Ya separa por rol; se le añade el tipo mas abajo.

   POR QUE TABLAS SEPARADAS Y NO UNA COLUMNA "TIPO" EN ind_declaraciones_ica

   Porque son tres formularios con renglones distintos: el ICA anual tiene 38
   casillas, RETEICA tiene cuatro de liquidacion y AUTORRETEICA nueve mas una
   seccion de ingresos. Meterlos en la misma tabla obligaria a revisar todas las
   consultas del ICA que hoy funcionan -y ya costo caro confundir cosas en esa
   tabla-. Ademas ind_Conceptos tiene indice unico por (año, codigo), asi que
   los renglones de un modulo chocarian con los del otro.

   LA DECISION QUE MAS ERRORES EVITA: LAS COLUMNAS SE LLAMAN COMO LA CASILLA

   En el ICA, el concepto 1 es la casilla 20 del formulario, el concepto 2 es la
   21, y asi. Esa traduccion silenciosa entre "numero de concepto" y "numero de
   casilla" esta detras de varios defectos, porque quien lee el formulario
   impreso y quien lee la base hablan de numeros distintos.

   Aqui no. La columna que guarda la casilla 17 se llama ValorConcepto17. El
   nombre de la columna, el numero impreso en el papel y el codigo del renglon
   en el catalogo son EL MISMO NUMERO. Cuesta unas columnas con hueco -no hay
   14 en autorretencion- y ahorra toda una clase de equivocaciones.

   LA PERIODICIDAD VA CON CHECK, NO POR CONVENIO

   RETEICA es mensual (1-12) y AUTORRETEICA bimestral (1-6). El ICA guarda
   dec_MesDeclaracion = 12 fijo por convenio, y un convenio no impide que
   alguien escriba un 13. Aqui lo impide la base.

   LO QUE ESTA MIGRACION NO TRAE

   Las FORMULAS de los renglones en disputa. Los tres documentos del cliente
   describen el mismo calculo de tres maneras distintas y una de ellas cobra
   casi el doble -la casilla 15 de autorretencion suma el impuesto de energia
   dos veces-. Las formulas acordadas van cargadas; las discutidas quedan en
   NULL y se completan cuando el cliente confirme. Una formula equivocada aqui
   no da error: da una liquidacion equivocada firmada por el contribuyente.

   RIESGO

   Bajo: son tablas nuevas y una columna nueva en firmas. Nada de lo que hoy
   funciona las lee.
   ============================================================================ */

SET NOCOUNT ON;
GO

SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
GO


/* ===========================================================================
   1. RETEICA — la declaracion de retencion (MENSUAL)
   =========================================================================== */
IF OBJECT_ID('dbo.ind_reteica', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.ind_reteica (
        ret_Id                  INT IDENTITY(1,1) NOT NULL,

        /* El numero del formulario. Lo reparte sp_siguiente_numero_retencion,
           igual que en el ICA, y es lo que viaja en el codigo de barras del
           banco. Nace NULL solo el instante entre el INSERT y su asignacion. */
        ret_NumeroDeclaracion   BIGINT NULL,

        ret_IdContribuyente     INT NOT NULL,

        /* Casilla 8: vigencia fiscal. */
        ret_Anio                INT NOT NULL,

        /* El mes declarado. El CHECK es la unica forma de que no entre un 13. */
        ret_Periodo             TINYINT NOT NULL,

        ret_FechaDeclaracion    DATE NULL,
        ret_HoraDeclaracion     TIME(0) NULL,

        /* Casilla 10: numero de la declaracion que esta corrigiendo. Se guarda
           el NUMERO y no el id, porque es lo que se imprime en el papel. */
        ret_Corrige             BIGINT NULL,

        /* Mismo convenio que el ICA para no tener dos vocabularios de estado:
           NULL = borrador, 2 = presentada. */
        ret_Estado              INT NULL,
        ret_FechaPresentacion   DATETIME NULL,

        /* Los renglones. El numero de la columna ES el numero de la casilla. */
        ret_ValorConcepto14     DECIMAL(18,2) NOT NULL DEFAULT 0,  -- total retenciones
        ret_ValorConcepto15     DECIMAL(18,2) NOT NULL DEFAULT 0,  -- sanciones (manual)
        ret_ValorConcepto16     DECIMAL(18,2) NOT NULL DEFAULT 0,  -- intereses de mora (manual)
        ret_ValorConcepto17     DECIMAL(18,2) NOT NULL DEFAULT 0,  -- total a pagar

        /* Pago. Mismas columnas y mismo significado que en el ICA. */
        ret_Pagado              BIT NOT NULL DEFAULT 0,
        ret_FechaPago           DATE NULL,
        ret_ValorPago           DECIMAL(18,2) NULL,
        ret_BancoPago           VARCHAR(120) NULL,

        ret_FechaCreador        DATETIME NULL,
        ret_Creador             INT NULL,

        CONSTRAINT PK_ind_reteica PRIMARY KEY (ret_Id),
        CONSTRAINT CK_reteica_periodo CHECK (ret_Periodo BETWEEN 1 AND 12),
        CONSTRAINT CK_reteica_estado  CHECK (ret_Estado IS NULL OR ret_Estado IN (0,1,2))
    );

    PRINT '  + ind_reteica';
END
ELSE
    PRINT '  = ind_reteica ya existia';
GO

/* Un numero, una declaracion. Filtrado porque el numero es NULL el instante
   que va entre el INSERT y la asignacion del consecutivo. */
IF OBJECT_ID('dbo.ind_reteica','U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UQ_reteica_numero')
BEGIN
    CREATE UNIQUE INDEX UQ_reteica_numero
        ON dbo.ind_reteica (ret_NumeroDeclaracion)
     WHERE ret_NumeroDeclaracion IS NOT NULL;
    PRINT '  + UQ_reteica_numero';
END
GO

IF OBJECT_ID('dbo.ind_reteica','U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_reteica_contribuyente')
BEGIN
    CREATE INDEX IX_reteica_contribuyente
        ON dbo.ind_reteica (ret_IdContribuyente, ret_Anio, ret_Periodo);
    PRINT '  + IX_reteica_contribuyente';
END
GO


/* ===========================================================================
   2. Las filas de actividad de RETEICA

   El contribuyente elige la actividad de un desplegable -solo comerciales y de
   servicios, segun el documento del cliente- y escribe la base gravable; el
   sistema calcula la retencion.

   LA TARIFA SE COPIA, NO SE REFERENCIA. Igual que en el ICA: una declaracion
   presentada tiene que seguir diciendo lo mismo dentro de cinco años, aunque el
   acuerdo municipal cambie la tarifa. Por eso se guarda el valor con que se
   liquido y no solo el id de la actividad.
   =========================================================================== */
IF OBJECT_ID('dbo.ind_reteica_actividades', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.ind_reteica_actividades (
        rea_Id              INT IDENTITY(1,1) NOT NULL,
        rea_IdReteica       INT NOT NULL,
        rea_IdActividad     INT NOT NULL,

        rea_BaseGravable    DECIMAL(18,2) NOT NULL DEFAULT 0,   -- casilla 12

        /* En FRACCION (0.004), como el catalogo y como dia_Tarifa del ICA.
           El formulario la IMPRIME por mil (4). La conversion se hace al
           imprimir y en ningun otro sitio: confundir las dos unidades no da
           error, da una liquidacion mil veces mayor o menor. */
        rea_Tarifa          DECIMAL(6,5) NOT NULL DEFAULT 0,     -- casilla 11

        rea_ValorRetencion  DECIMAL(18,2) NOT NULL DEFAULT 0,    -- casilla 13

        rea_Activo          BIT NOT NULL DEFAULT 1,
        rea_FechaCreador    DATETIME NULL,

        CONSTRAINT PK_ind_reteica_actividades PRIMARY KEY (rea_Id),
        CONSTRAINT FK_rea_reteica FOREIGN KEY (rea_IdReteica)
            REFERENCES dbo.ind_reteica (ret_Id)
    );

    PRINT '  + ind_reteica_actividades';
END
ELSE
    PRINT '  = ind_reteica_actividades ya existia';
GO

IF OBJECT_ID('dbo.ind_reteica_actividades','U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_rea_reteica')
BEGIN
    CREATE INDEX IX_rea_reteica ON dbo.ind_reteica_actividades (rea_IdReteica);
    PRINT '  + IX_rea_reteica';
END
GO


/* ===========================================================================
   3. AUTORRETEICA — la declaracion de autorretencion (BIMESTRAL)
   =========================================================================== */
IF OBJECT_ID('dbo.ind_autorreteica', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.ind_autorreteica (
        aut_Id                  INT IDENTITY(1,1) NOT NULL,
        aut_NumeroDeclaracion   BIGINT NULL,
        aut_IdContribuyente     INT NOT NULL,

        aut_Anio                INT NOT NULL,

        /* Seis bimestres: 1 = Enero-Febrero ... 6 = Noviembre-Diciembre. */
        aut_Periodo             TINYINT NOT NULL,

        aut_FechaDeclaracion    DATE NULL,
        aut_HoraDeclaracion     TIME(0) NULL,
        aut_Corrige             BIGINT NULL,
        aut_Estado              INT NULL,
        aut_FechaPresentacion   DATETIME NULL,

        /* Seccion de ingresos, casillas 9 a 13. */
        aut_ValorConcepto9      DECIMAL(18,2) NOT NULL DEFAULT 0,  -- ingresos brutos
        aut_ValorConcepto10     DECIMAL(18,2) NOT NULL DEFAULT 0,  -- menos devoluciones
        aut_ValorConcepto11     DECIMAL(18,2) NOT NULL DEFAULT 0,  -- menos deducciones
        aut_ValorConcepto12     DECIMAL(18,2) NOT NULL DEFAULT 0,  -- menos ingresos fuera
        aut_ValorConcepto13     DECIMAL(18,2) NOT NULL DEFAULT 0,  -- ingresos netos gravados

        /* El impuesto por generacion de energia (Ley 56 de 1981) NO tiene numero
           de casilla en el formulario: esta en la fila del TOTAL, sin rotulo
           numerado. Por eso lleva nombre propio y no ValorConceptoN.

           Es justo el dato de la discrepancia mas grave del proyecto: el Excel
           lo suma dentro del TOTAL y otra vez en la casilla 15. */
        aut_ImpuestoEnergia     DECIMAL(18,2) NOT NULL DEFAULT 0,

        /* Liquidacion privada, casillas 15 a 23. No hay 14: el formulario salta
           de la seccion de actividades a la 15. El hueco se deja a proposito
           para que el numero de columna siga siendo el de la casilla. */
        aut_ValorConcepto15     DECIMAL(18,2) NOT NULL DEFAULT 0,  -- autorretenciones ICA
        aut_ValorConcepto16     DECIMAL(18,2) NOT NULL DEFAULT 0,  -- avisos y tableros (15%)
        aut_ValorConcepto17     DECIMAL(18,2) NOT NULL DEFAULT 0,  -- total a cargo
        aut_ValorConcepto18     DECIMAL(18,2) NOT NULL DEFAULT 0,  -- menos anticipos (manual)
        aut_ValorConcepto19     DECIMAL(18,2) NOT NULL DEFAULT 0,  -- total liquidado
        aut_ValorConcepto20     DECIMAL(18,2) NOT NULL DEFAULT 0,  -- total saldo a favor
        aut_ValorConcepto21     DECIMAL(18,2) NOT NULL DEFAULT 0,  -- intereses (manual)
        aut_ValorConcepto22     DECIMAL(18,2) NOT NULL DEFAULT 0,  -- sanciones (manual)
        aut_ValorConcepto23     DECIMAL(18,2) NOT NULL DEFAULT 0,  -- total a pagar

        aut_Pagado              BIT NOT NULL DEFAULT 0,
        aut_FechaPago           DATE NULL,
        aut_ValorPago           DECIMAL(18,2) NULL,
        aut_BancoPago           VARCHAR(120) NULL,

        aut_FechaCreador        DATETIME NULL,
        aut_Creador             INT NULL,

        CONSTRAINT PK_ind_autorreteica PRIMARY KEY (aut_Id),
        CONSTRAINT CK_autorreteica_periodo CHECK (aut_Periodo BETWEEN 1 AND 6),
        CONSTRAINT CK_autorreteica_estado  CHECK (aut_Estado IS NULL OR aut_Estado IN (0,1,2))
    );

    PRINT '  + ind_autorreteica';
END
ELSE
    PRINT '  = ind_autorreteica ya existia';
GO

IF OBJECT_ID('dbo.ind_autorreteica','U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UQ_autorreteica_numero')
BEGIN
    CREATE UNIQUE INDEX UQ_autorreteica_numero
        ON dbo.ind_autorreteica (aut_NumeroDeclaracion)
     WHERE aut_NumeroDeclaracion IS NOT NULL;
    PRINT '  + UQ_autorreteica_numero';
END
GO

IF OBJECT_ID('dbo.ind_autorreteica','U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_autorreteica_contribuyente')
BEGIN
    CREATE INDEX IX_autorreteica_contribuyente
        ON dbo.ind_autorreteica (aut_IdContribuyente, aut_Anio, aut_Periodo);
    PRINT '  + IX_autorreteica_contribuyente';
END
GO


/* ===========================================================================
   4. Las actividades de AUTORRETEICA

   Aqui NO las elige el contribuyente: se precargan las que tiene registradas en
   el RIT, y el solo escribe los ingresos gravados de cada una. Esa es la
   diferencia con RETEICA y por eso son dos tablas y no una compartida.
   =========================================================================== */
IF OBJECT_ID('dbo.ind_autorreteica_actividades', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.ind_autorreteica_actividades (
        aua_Id                INT IDENTITY(1,1) NOT NULL,
        aua_IdAutorreteica    INT NOT NULL,
        aua_IdActividad       INT NOT NULL,

        aua_IngresosGravados  DECIMAL(18,2) NOT NULL DEFAULT 0,
        aua_Tarifa            DECIMAL(6,5) NOT NULL DEFAULT 0,   -- en fraccion, ver ind_reteica_actividades
        aua_ValorImpuesto     DECIMAL(18,2) NOT NULL DEFAULT 0,

        aua_Activo            BIT NOT NULL DEFAULT 1,
        aua_FechaCreador      DATETIME NULL,

        CONSTRAINT PK_ind_autorreteica_actividades PRIMARY KEY (aua_Id),
        CONSTRAINT FK_aua_autorreteica FOREIGN KEY (aua_IdAutorreteica)
            REFERENCES dbo.ind_autorreteica (aut_Id)
    );

    PRINT '  + ind_autorreteica_actividades';
END
ELSE
    PRINT '  = ind_autorreteica_actividades ya existia';
GO

IF OBJECT_ID('dbo.ind_autorreteica_actividades','U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_aua_autorreteica')
BEGIN
    CREATE INDEX IX_aua_autorreteica ON dbo.ind_autorreteica_actividades (aua_IdAutorreteica);
    PRINT '  + IX_aua_autorreteica';
END
GO


/* ===========================================================================
   5. El catalogo de renglones de los dos modulos

   Una sola tabla para los dos, distinguidos por ren_Modulo. No se reusa
   ind_Conceptos porque tiene indice unico por (año, codigo) y los renglones de
   estos modulos chocarian con los del ICA.

   ren_Formula guarda la expresion SQL que se inyecta al liquidar, igual que
   con_Observaciones en el ICA (ver migracion 010, que explica por que un
   comentario resulto ser una formula).

   REGLA QUE NO SE PUEDE OLVIDAR: un renglon que llena el contribuyente NO
   puede tener formula '0'. Tiene que referenciarse a si mismo, o cada recalculo
   le escribe cero encima de lo que la persona acaba de escribir. Ese fue el
   defecto que el cliente reporto en agosto y que arreglo la migracion 010. Las
   hojas de conceptos que entrego traen justamente '0' en los renglones
   manuales; no se cargan tal cual.
   =========================================================================== */
IF OBJECT_ID('dbo.ind_renglones_retencion', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.ind_renglones_retencion (
        ren_Id        INT IDENTITY(1,1) NOT NULL,

        /* 'RETEICA' o 'AUTORRETEICA'. */
        ren_Modulo    VARCHAR(20) NOT NULL,
        ren_Anio      INT NOT NULL,

        /* El numero de la CASILLA del formulario impreso. Mismo numero que el
           sufijo de la columna ValorConceptoN. */
        ren_Codigo    INT NOT NULL,

        ren_Nombre    NVARCHAR(300) NOT NULL,

        /* La expresion SQL. NULL mientras la formula este por confirmar. */
        ren_Formula   NVARCHAR(1000) NULL,

        /* 1 = lo escribe el contribuyente. Un renglon manual nunca se recalcula
           a partir de otros. */
        ren_Manual    BIT NOT NULL DEFAULT 0,

        ren_Orden     INT NOT NULL DEFAULT 0,
        ren_Estado    BIT NOT NULL DEFAULT 1,

        CONSTRAINT PK_ind_renglones_retencion PRIMARY KEY (ren_Id),
        CONSTRAINT UQ_renglon_modulo_anio_codigo UNIQUE (ren_Modulo, ren_Anio, ren_Codigo),
        CONSTRAINT CK_renglon_modulo CHECK (ren_Modulo IN ('RETEICA','AUTORRETEICA'))
    );

    PRINT '  + ind_renglones_retencion';
END
ELSE
    PRINT '  = ind_renglones_retencion ya existia';
GO


/* ---------------------------------------------------------------------------
   Los renglones de RETEICA para 2026.

   Las formulas de este modulo no estan en disputa salvo por el redondeo (ver la
   nota del final), asi que van cargadas.
   --------------------------------------------------------------------------- */
IF OBJECT_ID('dbo.ind_renglones_retencion','U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM dbo.ind_renglones_retencion WHERE ren_Modulo = 'RETEICA' AND ren_Anio = 2026)
BEGIN
    INSERT INTO dbo.ind_renglones_retencion (ren_Modulo, ren_Anio, ren_Codigo, ren_Nombre, ren_Formula, ren_Manual, ren_Orden)
    VALUES
      /* La suma de las retenciones de las filas de actividad. Es una subconsulta
         y no una columna, porque el dato vive en la otra tabla; el motor la
         inyecta igual que cualquier otra formula. */
      ('RETEICA', 2026, 14, N'TOTAL VALOR RETENCIONES',
            N'(SELECT ISNULL(SUM(a.rea_ValorRetencion),0) FROM dbo.ind_reteica_actividades a WHERE a.rea_IdReteica = ep.ret_Id AND a.rea_Activo = 1)', 0, 1),
      ('RETEICA', 2026, 15, N'SANCIONES',               N'ep.ret_ValorConcepto15', 1, 2),
      ('RETEICA', 2026, 16, N'INTERESES MORATORIOS',    N'ep.ret_ValorConcepto16', 1, 3),
      ('RETEICA', 2026, 17, N'TOTAL A PAGAR',
            N'ISNULL(ep.ret_ValorConcepto14,0) + ISNULL(ep.ret_ValorConcepto15,0) + ISNULL(ep.ret_ValorConcepto16,0)', 0, 4);

    PRINT '  + renglones de RETEICA 2026';
END
GO


/* ---------------------------------------------------------------------------
   Los renglones de AUTORRETEICA para 2026.

   Las formulas de las casillas 15, 19, 20 y 23 quedan en NULL a proposito:
   el Excel, el documento en Word y la hoja de conceptos se contradicen, y la de
   la 15 cobra casi el doble. Se completan cuando el cliente confirme.
   --------------------------------------------------------------------------- */
IF OBJECT_ID('dbo.ind_renglones_retencion','U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM dbo.ind_renglones_retencion WHERE ren_Modulo = 'AUTORRETEICA' AND ren_Anio = 2026)
BEGIN
    INSERT INTO dbo.ind_renglones_retencion (ren_Modulo, ren_Anio, ren_Codigo, ren_Nombre, ren_Formula, ren_Manual, ren_Orden)
    VALUES
      ('AUTORRETEICA', 2026,  9, N'INGRESOS BRUTOS RECIBIDOS EN EL BIMESTRE',      N'ep.aut_ValorConcepto9',  1, 1),
      ('AUTORRETEICA', 2026, 10, N'Menos DEVOLUCIONES, REBAJAS Y DESCUENTOS',      N'ep.aut_ValorConcepto10', 1, 2),
      ('AUTORRETEICA', 2026, 11, N'Menos DEDUCCIONES, EXENCIONES Y NO SUJECIONES', N'ep.aut_ValorConcepto11', 1, 3),
      ('AUTORRETEICA', 2026, 12, N'Menos INGRESOS FUERA DEL MUNICIPIO',            N'ep.aut_ValorConcepto12', 1, 4),

      /* La unica de la seccion de ingresos que calcula el sistema. La etiqueta
         del formulario dice "Renglones 10-11-12-13", que esta corrida un numero
         -la 13 es ella misma-. El Word y la formula del Excel coinciden en lo
         correcto: 9 menos 10, 11 y 12. */
      ('AUTORRETEICA', 2026, 13, N'INGRESOS NETOS GRAVADOS (Renglones 9-10-11-12)',
            N'ISNULL(ep.aut_ValorConcepto9,0) - ISNULL(ep.aut_ValorConcepto10,0) - ISNULL(ep.aut_ValorConcepto11,0) - ISNULL(ep.aut_ValorConcepto12,0)', 0, 5),

      /* EN DISPUTA: el Excel suma el impuesto de energia dos veces. */
      ('AUTORRETEICA', 2026, 15, N'AUTORRETENCIONES INDUSTRIA Y COMERCIO', NULL, 0, 6),

      /* El 15% de la 15. La formula queda pendiente de la 15 y ademas de si la
         exencion de avisos y tableros del RIT aplica aqui, como si aplica en el
         ICA desde la migracion 021. */
      ('AUTORRETEICA', 2026, 16, N'AUTORRETENCIONES AVISOS Y TABLEROS (15%)', NULL, 0, 7),

      ('AUTORRETEICA', 2026, 17, N'TOTAL AUTORRETENCIONES A CARGO',
            N'ISNULL(ep.aut_ValorConcepto15,0) + ISNULL(ep.aut_ValorConcepto16,0)', 0, 8),

      ('AUTORRETEICA', 2026, 18, N'Menos ANTICIPOS U OTROS', N'ep.aut_ValorConcepto18', 1, 9),

      /* EN DISPUTA: las etiquetas de la 19 y la 20 describen dos ramas
         excluyentes que ninguna formula del cliente implementa. */
      ('AUTORRETEICA', 2026, 19, N'TOTAL AUTORRETENCION LIQUIDADO', NULL, 0, 10),
      ('AUTORRETEICA', 2026, 20, N'TOTAL SALDO A FAVOR',            NULL, 0, 11),

      ('AUTORRETEICA', 2026, 21, N'INTERESES',      N'ep.aut_ValorConcepto21', 1, 12),
      ('AUTORRETEICA', 2026, 22, N'Mas SANCIONES',  N'ep.aut_ValorConcepto22', 1, 13),

      /* EN DISPUTA: tres fuentes, tres formulas. */
      ('AUTORRETEICA', 2026, 23, N'TOTAL A PAGAR EN EL BIMESTRE', NULL, 0, 14);

    PRINT '  + renglones de AUTORRETEICA 2026 (cuatro formulas pendientes de confirmar)';
END
GO


/* ===========================================================================
   6. El tipo de declaracion en las firmas

   firmas_declaraciones ya distingue el ROL de quien firma (declarante,
   contador) pero no de que declaracion se trata: guarda un identificador suelto
   en fd_NumeroDeclaracion. Con tres modulos, el id 358 de una retencion y el
   358 de un ICA serian indistinguibles.

   Se añade el tipo con DEFAULT 'ICA' para que todas las firmas que ya existen
   queden correctamente clasificadas sin tocarlas.
   =========================================================================== */
IF COL_LENGTH('dbo.firmas_declaraciones', 'fd_Modulo') IS NULL
BEGIN
    ALTER TABLE dbo.firmas_declaraciones
        ADD fd_Modulo VARCHAR(20) NOT NULL CONSTRAINT DF_fd_Modulo DEFAULT 'ICA';

    PRINT '  + firmas_declaraciones.fd_Modulo (las existentes quedan como ICA)';
END
ELSE
    PRINT '  = fd_Modulo ya existia';
GO


/* ===========================================================================
   7. Los consecutivos de los dos modulos

   Se reusa ind_consecutivos y su mecanismo: la asignacion y la lectura ocurren
   en la MISMA sentencia, que es lo que impide que dos sesiones simultaneas se
   lleven el mismo numero.

   Formato AAAA000001, el mismo del ICA (migracion 029). El "20270000001" que
   aparece en el formulario del cliente tiene once digitos, pero es un numero
   dibujado a mano en el ejemplo, no un formato acordado: inventar un tercero
   solo añade un caso mas que mantener. Si la Alcaldia lo exige, se cambia aqui.

   Series SEPARADAS por modulo: la retencion numero 1 y la autorretencion numero
   1 son documentos distintos y cada serie tiene que poder auditarse sola.
   =========================================================================== */
GO
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

    BEGIN TRANSACTION;

        IF NOT EXISTS (SELECT 1 FROM dbo.ind_consecutivos
                        WHERE cse_Tipo = @TIPO AND cse_Anio = @ANIO)
        BEGIN
            /* Arranca por encima de lo que ya exista con ese prefijo, para que
               reaplicar la migracion sobre una base con datos no repita. */
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

            IF @arranque < 0 SET @arranque = 0;

            INSERT INTO dbo.ind_consecutivos (cse_Tipo, cse_Anio, cse_Valor, cse_FechaActualizacion)
            VALUES (@TIPO, @ANIO, @arranque, GETDATE());
        END

        UPDATE dbo.ind_consecutivos
           SET @consecutivo = cse_Valor = cse_Valor + 1,
               cse_FechaActualizacion = GETDATE()
         WHERE cse_Tipo = @TIPO AND cse_Anio = @ANIO;

    COMMIT TRANSACTION;

    SET @NUMERO = (CAST(@ANIO AS BIGINT) * 1000000) + @consecutivo;
END;
GO


IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '030_reteica_y_autorreteica')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('030_reteica_y_autorreteica',
            'Tablas de los modulos de retencion (mensual, periodo 1-12) y autorretencion (bimestral, 1-6) de ICA, su catalogo de renglones, el tipo de modulo en las firmas y el repartidor de consecutivos. No crea catalogo de actividades: las hojas del cliente son las que ya usa el ICA. Las columnas se llaman como la CASILLA impresa, no como un numero de concepto aparte. Cuatro formulas de autorretencion quedan en NULL: los documentos del cliente se contradicen y una cobra casi el doble.');
GO

/* ----------------------------------------------------------------------------
   PENDIENTE ANTES DE PODER LIQUIDAR

   1. Las cuatro formulas en NULL (casillas 15, 16, 19, 20 y 23 de
      autorretencion), que dependen de decisiones del cliente.
   2. El REDONDEO. Los dos formularios nuevos redondean a miles FILA POR FILA
      (MROUND(...,1000)); el ICA redondea una sola vez sobre el total. Son
      resultados distintos sobre los mismos datos y hay que decidir cual manda.
   3. El procedimiento de liquidacion propiamente dicho, que se escribira cuando
      las formulas esten cerradas.

   VUELTA ATRAS

       DROP PROCEDURE dbo.sp_siguiente_numero_retencion;
       DROP TABLE dbo.ind_autorreteica_actividades;
       DROP TABLE dbo.ind_reteica_actividades;
       DROP TABLE dbo.ind_autorreteica;
       DROP TABLE dbo.ind_reteica;
       DROP TABLE dbo.ind_renglones_retencion;
       ALTER TABLE dbo.firmas_declaraciones DROP CONSTRAINT DF_fd_Modulo;
       ALTER TABLE dbo.firmas_declaraciones DROP COLUMN fd_Modulo;
       DELETE FROM dbo.ind_consecutivos WHERE cse_Tipo IN ('RETEICA','AUTORRETEICA');
       DELETE FROM dbo.conf_migraciones WHERE mig_Nombre = '030_reteica_y_autorreteica';
   ---------------------------------------------------------------------------- */
