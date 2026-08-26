/* ============================================================================
   020 — Se impiden las declaraciones duplicadas NUEVAS, sin tocar las viejas
   ----------------------------------------------------------------------------
   El indice unico que impide dos declaraciones del mismo contribuyente para el
   mismo periodo lleva bloqueado desde el 2026-08-21: no se puede crear porque
   ya existen duplicados, y decidir que hacer con ellos es de la Alcaldia.

   Esa espera tiene un costo que no se estaba contando: mientras no exista el
   indice, se siguen creando duplicados nuevos. El problema no esta congelado,
   esta creciendo.

   LA SALIDA: UNA LINEA EN LA ARENA

   Un indice unico FILTRADO que solo mira las filas creadas de aqui en
   adelante. Las que ya existen quedan exactamente como estan -no se borra ni
   se fusiona nada, la decision sigue siendo de la Alcaldia y sigue abierta-,
   pero desde hoy no se puede crear una repetida.

   QUE HAY EN LA BASE HOY (medido el 2026-08-26)

   213 declaraciones que colapsan en solo CINCO combinaciones distintas de
   contribuyente + año + periodo. O sea que no son "94 duplicados" sobre un
   conjunto sano: es una base de PRUEBAS llena de intentos repetidos. Vale la
   pena que la Alcaldia lo sepa antes de decidir: seguramente lo que hay que
   limpiar no es un problema de produccion.

   POR QUE dec_Id Y NO UNA FECHA

   dec_Id es IDENTITY y solo crece, asi que "creada despues de" es exactamente
   "id mayor que". Una fecha admite filas con la fecha en nulo o mal puesta; el
   id no. El corte se escribe como constante a proposito: es una marca
   historica -"hasta aqui llego el desorden"- y tiene que seguir significando
   lo mismo dentro de un año.

   SI ALGUN DIA SE LIMPIAN LAS VIEJAS

   Se borra este indice y se crea el de verdad, sin filtro de id. Queda al pie.

   RIESGO

   Ninguno sobre los datos: un indice no cambia ni una fila. Lo unico que puede
   pasar es que un INSERT nuevo sea rechazado, que es justo lo que se busca.
   ============================================================================ */

SET NOCOUNT ON;
GO

DECLARE @candado INT;
EXEC @candado = sp_getapplock
     @Resource    = 'migracion_020_duplicados',
     @LockMode    = 'Exclusive',
     @LockOwner   = 'Session',
     @LockTimeout = 120000;

IF @candado < 0
BEGIN
    RAISERROR('No se pudo tomar el candado de la migracion 020 (otra corrida en curso).', 16, 1);
    SET NOEXEC ON;
END
GO


/* ---------------------------------------------------------------------------
   Se deja constancia de donde quedo la linea

   El corte se calcula UNA vez, al aplicar la migracion, y se guarda en
   ind_consecutivos para poder consultarlo despues sin releer el indice.
   --------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM dbo.ind_consecutivos WHERE cse_Tipo = 'CORTE_DUPLICADOS')
BEGIN
    DECLARE @corte INT = (SELECT ISNULL(MAX(dec_Id), 0) FROM dbo.ind_declaraciones_ica);

    INSERT INTO dbo.ind_consecutivos (cse_Tipo, cse_Anio, cse_Valor, cse_FechaActualizacion)
    VALUES ('CORTE_DUPLICADOS', 0, @corte, GETDATE());

    PRINT '  + corte anotado en dec_Id = ' + CAST(@corte AS VARCHAR(20));
END
ELSE
    PRINT '  = el corte ya estaba anotado';
GO


/* ---------------------------------------------------------------------------
   El indice

   El filtro NO puede usar una variable: SQL Server exige que el predicado de
   un indice filtrado sea determinista y constante, asi que la sentencia se
   arma como texto con el corte ya resuelto.
   --------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UQ_declaracion_periodo_nuevas')
BEGIN
    DECLARE @corte2 VARCHAR(20) = (
        SELECT CAST(cse_Valor AS VARCHAR(20)) FROM dbo.ind_consecutivos
         WHERE cse_Tipo = 'CORTE_DUPLICADOS');

    DECLARE @sql NVARCHAR(MAX) = N'
        CREATE UNIQUE INDEX UQ_declaracion_periodo_nuevas
            ON dbo.ind_declaraciones_ica
               (dec_IdContribuyente, dec_AnioDeclaracion, dec_MesDeclaracion)
         WHERE dec_DeclaracionCorrige IS NULL
           AND dec_Id > ' + @corte2 + ';';

    EXEC sp_executesql @sql;
    PRINT '  + UQ_declaracion_periodo_nuevas (solo sobre dec_Id > ' + @corte2 + ')';
END
ELSE
    PRINT '  = el indice ya existia';
GO


IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '020_no_mas_declaraciones_duplicadas')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('020_no_mas_declaraciones_duplicadas',
            'Indice unico FILTRADO que impide declaraciones repetidas del mismo contribuyente y periodo, aplicando solo a las filas creadas de aqui en adelante. Las existentes no se tocan y la decision sobre ellas sigue siendo de la Alcaldia; lo que se corta es que sigan apareciendo nuevas mientras se decide. Medido al aplicarlo: 213 declaraciones que colapsan en cinco periodos distintos, o sea intentos repetidos de una base de pruebas.');
GO

SET NOEXEC OFF;
EXEC sp_releaseapplock @Resource = 'migracion_020_duplicados', @LockOwner = 'Session';
GO

/* ----------------------------------------------------------------------------
   CUANDO SE LIMPIEN LAS VIEJAS, el indice definitivo es este:

       DROP INDEX UQ_declaracion_periodo_nuevas ON dbo.ind_declaraciones_ica;

       CREATE UNIQUE INDEX UQ_declaracion_contribuyente_periodo
           ON dbo.ind_declaraciones_ica
              (dec_IdContribuyente, dec_AnioDeclaracion, dec_MesDeclaracion)
        WHERE dec_DeclaracionCorrige IS NULL;

   VUELTA ATRAS

       DROP INDEX UQ_declaracion_periodo_nuevas ON dbo.ind_declaraciones_ica;
       DELETE FROM dbo.ind_consecutivos WHERE cse_Tipo = 'CORTE_DUPLICADOS';
       DELETE FROM dbo.conf_migraciones WHERE mig_Nombre = '020_no_mas_declaraciones_duplicadas';
   ---------------------------------------------------------------------------- */
