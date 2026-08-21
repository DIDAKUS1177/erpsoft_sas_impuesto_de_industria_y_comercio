/* ============================================================================
   012 — El numero de declaracion deja de ser el id interno de la fila
   ----------------------------------------------------------------------------
   Reportado por el cliente el 2026-08-21: "Revisar el consecutivo de la
   declaracion".

   LO QUE PASABA

   No habia generador. Justo despues de insertar el borrador, el codigo hacia:

       UPDATE ind_declaraciones_ica SET dec_NumeroDeclaracion = ? WHERE dec_Id = ?
       -- con el MISMO id en los dos parametros

   O sea que el "numero de formulario" era literalmente el IDENTITY de la
   tabla. Consecuencias: no lleva el año delante, nunca se reinicia, y va por
   218 cuando el formulario oficial de la Alcaldia usa numeros como
   2025001197.

   EL FORMATO NUEVO

       AAAA + consecutivo de 6 digitos    ->   2026000001, 2026000002, ...

   compuesto como (anio * 1000000) + consecutivo. La columna ya es BIGINT, asi
   que cabe de sobra y no hay que migrar el tipo.

   LA GENERACION ES ATOMICA

   Una tabla de control con un UPDATE que incrementa y devuelve el valor en la
   misma sentencia (cláusula OUTPUT). Dos usuarios declarando a la vez no
   pueden llevarse el mismo numero, que es lo que si podia pasar con un
   SELECT MAX + 1.

   POR QUE SE SIGUE ASIGNANDO AL CREAR EL BORRADOR

   Lo natural seria asignarlo al PRESENTAR, para no gastar numeros en
   borradores abandonados. No se hace, y es deliberado: hay CUATRO consultas
   del flujo de liquidacion que localizan la fila por
   'WHERE dec_NumeroDeclaracion = ?' -incluido el UPDATE de totales y las dos
   llamadas al procedimiento de calculo-. Con el numero en NULL durante el
   borrador, esas consultas afectarian cero filas y el boton Liquidar dejaria
   de funcionar, justo lo que se acaba de arreglar.

   Mover la asignacion al momento de presentar exige antes cambiar esas cuatro
   consultas para que busquen por dec_Id. Es un cambio aparte, con su propia
   verificacion. Un consecutivo con huecos no es un defecto: en numeracion
   tributaria es normal.

   LAS DECLARACIONES EXISTENTES NO SE RENUMERAN

   Sus numeros ya estan referenciados en las liquidaciones y en los codigos de
   barras impresos. El contador arranca por encima del maximo actual, asi que
   no puede haber colision.

   Re-ejecutable: guardas IF NOT EXISTS en cada paso.
   ============================================================================ */

DECLARE @candado INT;
EXEC @candado = sp_getapplock
     @Resource    = 'migracion_012_consecutivo',
     @LockMode    = 'Exclusive',
     @LockOwner   = 'Session',
     @LockTimeout = 120000;

IF @candado < 0
BEGIN
    RAISERROR('No se pudo tomar el candado de la migracion 012 (otra corrida en curso).', 16, 1);
    SET NOEXEC ON;
END
GO


/* ---------------------------------------------------------------------------
   1. Tabla de control
   --------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'ind_consecutivos')
BEGIN
    CREATE TABLE dbo.ind_consecutivos (
        cse_Tipo   VARCHAR(40) NOT NULL,   -- 'DECLARACION_ICA', y lo que venga
        cse_Anio   INT         NOT NULL,
        cse_Valor  INT         NOT NULL DEFAULT 0,
        cse_FechaActualizacion DATETIME NULL,
        CONSTRAINT PK_ind_consecutivos PRIMARY KEY (cse_Tipo, cse_Anio)
    );
    PRINT 'ind_consecutivos creada.';
END
ELSE PRINT 'ind_consecutivos ya existia.';
GO


/* ---------------------------------------------------------------------------
   2. Procedimiento que entrega el siguiente numero

   El UPDATE ... SET @x = cse_Valor = cse_Valor + 1 incrementa y captura en una
   sola operacion, bajo el bloqueo de la fila. Es la forma correcta en SQL
   Server de repartir consecutivos sin carreras.
   --------------------------------------------------------------------------- */
GO
CREATE OR ALTER PROCEDURE dbo.sp_siguiente_numero_declaracion
    @ANIO   INT,
    @NUMERO BIGINT OUTPUT
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @consecutivo INT;

    BEGIN TRANSACTION;

        -- La fila del año se crea la primera vez, arrancando por encima de lo
        -- que ya exista con ese prefijo (para bases que ya traen numeros).
        IF NOT EXISTS (SELECT 1 FROM dbo.ind_consecutivos
                        WHERE cse_Tipo = 'DECLARACION_ICA' AND cse_Anio = @ANIO)
        BEGIN
            DECLARE @arranque INT = (
                SELECT ISNULL(MAX(dec_NumeroDeclaracion - (@ANIO * 1000000)), 0)
                  FROM dbo.ind_declaraciones_ica
                 WHERE dec_NumeroDeclaracion BETWEEN (@ANIO * 1000000) AND ((@ANIO * 1000000) + 999999)
            );
            IF @arranque < 0 SET @arranque = 0;

            INSERT INTO dbo.ind_consecutivos (cse_Tipo, cse_Anio, cse_Valor, cse_FechaActualizacion)
            VALUES ('DECLARACION_ICA', @ANIO, @arranque, GETDATE());
        END

        UPDATE dbo.ind_consecutivos
           SET @consecutivo = cse_Valor = cse_Valor + 1,
               cse_FechaActualizacion = GETDATE()
         WHERE cse_Tipo = 'DECLARACION_ICA' AND cse_Anio = @ANIO;

    COMMIT TRANSACTION;

    SET @NUMERO = (CAST(@ANIO AS BIGINT) * 1000000) + @consecutivo;
END;
GO


/* ---------------------------------------------------------------------------
   3. Informe
   --------------------------------------------------------------------------- */
SELECT
    declaraciones     = (SELECT COUNT(*) FROM dbo.ind_declaraciones_ica),
    numero_maximo     = (SELECT MAX(dec_NumeroDeclaracion) FROM dbo.ind_declaraciones_ica),
    con_formato_nuevo = (SELECT COUNT(*) FROM dbo.ind_declaraciones_ica
                          WHERE dec_NumeroDeclaracion > 2000000000);
GO


/* ---------------------------------------------------------------------------
   4. Registro y liberacion del candado
   --------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '012_consecutivo_de_declaracion')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('012_consecutivo_de_declaracion',
            'El numero de declaracion dejaba de ser el IDENTITY de la fila y pasa a un consecutivo por año con formato AAAA + 6 digitos, repartido de forma atomica por sp_siguiente_numero_declaracion. Las declaraciones existentes NO se renumeran. Se sigue asignando al crear el borrador, porque cuatro consultas del flujo de liquidacion localizan la fila por ese numero.');
GO

SET NOEXEC OFF;
EXEC sp_releaseapplock @Resource = 'migracion_012_consecutivo', @LockOwner = 'Session';
GO
