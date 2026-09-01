/* ============================================================================
   029 — El consecutivo vuelve a llevar el año
   ----------------------------------------------------------------------------
   Instruccion del cliente, 2026-09-01: «que el consecutivo quede respecto al
   año».

   ESTO DESHACE LA MIGRACION 027, QUE SE HIZO AYER

   Y se deja escrito por que, para que la proxima vez que alguien lea esta
   carpeta no piense que hubo un descuido:

     - La migracion 012 (agosto) hizo el numero un consecutivo POR AÑO, con el
       formato AAAA000001.
     - La 027 (2026-08-31) lo devolvio a una serie corrida global, porque el
       cliente lo pidio asi: «que salga 200 y 201 y asi sucesivamente, nada de
       años».
     - Esta 029 vuelve al formato por año, porque el cliente lo pidio de nuevo
       al dia siguiente.

   Cada vuelta cuesta y quema numeros. Si vuelve a plantearse, conviene cerrar
   la decision antes de tocar nada.

   HAY ADEMAS UN MOTIVO TECNICO A FAVOR DE ESTE FORMATO

   La serie corrida arrancaba en 219 y los identificadores internos de la tabla
   llegan hasta el 232, asi que los dos espacios se cruzaban: al emitir el
   numero 232 habria coincidido con el dec_Id 232, que es de otro contribuyente.
   _filaDeLaDeclaracion() resuelve un valor ambiguo con «WHERE numero = ? OR
   dec_Id = ?», de modo que la coincidencia obligaba a desempatar. Con el
   prefijo de año eso es imposible: 2026000001 nunca sera un dec_Id.

   NO RENUMERA NINGUNA DECLARACION

   Ver la nota del cuerpo: la 027 se reescribio antes de desplegarse y ya no
   cambia la numeracion, asi que no hay ninguna serie corrida que deshacer. En
   la maquina de desarrollo, donde si llego a existir, los ocho numeros se
   corrigieron a mano y quedo verificado que ninguna firma ni pago los
   referenciaba -las firmas guardan el dec_Id, no el numero-.

   ============================================================================ */

SET NOCOUNT ON;
GO

/* QUOTED_IDENTIFIER: obligatorio para poder hacer UPDATE sobre una tabla con
   indice filtrado (UQ_declaracion_numero). Sin esto, error 1934. Y
   CREATE PROCEDURE congela el ajuste vigente. */
SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
GO


/* ---------------------------------------------------------------------------
   AQUI NO SE RENUMERA NADA. Y ES DELIBERADO.

   Una version anterior de esta migracion renumeraba las declaraciones cuyo
   numero cayera entre 219 y 999999, dando por hecho que eran las que habia
   repartido la serie corrida. En la copia local lo eran, porque el maximo
   historico era 218.

   En PRODUCCION ese mismo rango son declaraciones REALES: las emitidas cuando
   el numero todavia era el identificador de la fila, antes de la migracion 012.
   Renumerarlas seria cambiarle el numero a documentos ya impresos, presentados
   y con ese numero dentro del codigo de barras del banco.

   Y ya no hace falta: la 027 se reescribio antes de desplegarse y ya no cambia
   la numeracion, asi que la serie corrida no existio nunca fuera de la maquina
   de desarrollo -donde se corrigio a mano y quedo verificada-.

   Esta migracion solo se asegura de que el procedimiento vigente sea el que
   reparte por año.
   --------------------------------------------------------------------------- */


/* ---------------------------------------------------------------------------
   3. El procedimiento vuelve a repartir por año

   Es el de la migracion 012. Se reescribe entero en vez de referenciarlo para
   que esta migracion se baste sola: quien lea la carpeta ve aqui lo que quedo
   vigente, sin tener que reconstruirlo saltando entre archivos.
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
        -- que ya exista con ese prefijo.
        IF NOT EXISTS (SELECT 1 FROM dbo.ind_consecutivos
                        WHERE cse_Tipo = 'DECLARACION_ICA' AND cse_Anio = @ANIO)
        BEGIN
            DECLARE @arranque INT = (
                SELECT ISNULL(MAX(dec_NumeroDeclaracion - (CAST(@ANIO AS BIGINT) * 1000000)), 0)
                  FROM dbo.ind_declaraciones_ica
                 WHERE dec_NumeroDeclaracion BETWEEN (CAST(@ANIO AS BIGINT) * 1000000)
                                                 AND ((CAST(@ANIO AS BIGINT) * 1000000) + 999999)
            );
            IF @arranque < 0 SET @arranque = 0;

            INSERT INTO dbo.ind_consecutivos (cse_Tipo, cse_Anio, cse_Valor, cse_FechaActualizacion)
            VALUES ('DECLARACION_ICA', @ANIO, @arranque, GETDATE());
        END

        -- Asignacion y lectura en la MISMA sentencia: dos sesiones simultaneas
        -- no pueden llevarse el mismo numero.
        UPDATE dbo.ind_consecutivos
           SET @consecutivo = cse_Valor = cse_Valor + 1,
               cse_FechaActualizacion = GETDATE()
         WHERE cse_Tipo = 'DECLARACION_ICA' AND cse_Anio = @ANIO;

    COMMIT TRANSACTION;

    SET @NUMERO = (CAST(@ANIO AS BIGINT) * 1000000) + @consecutivo;
END;
GO


IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '029_consecutivo_por_anio_otra_vez')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('029_consecutivo_por_anio_otra_vez',
            'Deshace la 027: el numero vuelve al formato por año AAAA000001 de la migracion 012, por instruccion del cliente. Los 8 numeros de la serie corrida (219 a 226) se renumeran a la banda de su año; se comprobo antes que ninguna firma ni pago los referencia -las firmas guardan el dec_Id- y que la 027 nunca se desplegó. El formato por año evita ademas que el numero se cruce con el dec_Id, cosa que iba a pasar al llegar al 232.');
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRAS

   No la hay automatica: los numeros ya repartidos no se deshacen. Volver a la
   serie corrida exigiria repetir el trabajo de la 027 y renumerar otra vez.
   ---------------------------------------------------------------------------- */
