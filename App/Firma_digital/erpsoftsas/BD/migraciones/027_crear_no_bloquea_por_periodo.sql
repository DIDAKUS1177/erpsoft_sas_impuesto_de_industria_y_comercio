/* ============================================================================
   027 — Crear una declaración deja de bloquearse por período
   ----------------------------------------------------------------------------
   Instruccion del cliente, 2026-08-31: «cuando cree una nueva salga 200 y 201 y
   asi sucesivamente, nada mas; nada de años y presentadas, nada de eso».

   De ese pedido, lo que sobrevive es ESTO: que pulsar "Crear Declaracion" cree
   siempre una declaracion, sin que el sistema se niegue porque ya hay una del
   mismo periodo.

   POR QUE ESTA MIGRACION SE REESCRIBIO ANTES DE DESPLEGARSE

   La version original hacia dos cosas: quitar este bloqueo Y cambiar el numero
   de declaracion del formato por año (2026000001, migracion 012) a una serie
   corrida global. Al dia siguiente el cliente pidio el formato por año otra
   vez, asi que la segunda mitad quedaba deshecha por la 029.

   Dejarlas las dos habria hecho que produccion renumerara sus declaraciones
   REALES dos veces -a serie corrida y de vuelta a año- para acabar donde
   estaba. El numero va impreso en el formulario y dentro del codigo de barras
   con que el banco recauda, asi que eso no es un rodeo inofensivo: es tocar
   documentos ya emitidos sin ninguna razon.

   Como ninguna de las dos llego a desplegarse, se corrige el historial en vez
   de arrastrarlo: esta migracion se queda con lo que sigue vigente, y la 029
   se ocupa del procedimiento de numeracion.

   QUE SIGNIFICA QUITAR EL INDICE

   UQ_declaracion_periodo_nuevas (migracion 020) impedia dos declaraciones del
   mismo contribuyente para el mismo periodo.

   SE ADVIRTIO Y SE DECIDIO: se le expuso al cliente que sin el pueden convivir
   dos declaraciones ORIGINALES del mismo periodo -y que la regla de «una por
   contribuyente y año, y lo presentado solo se corrige» la habia confirmado el
   propio cliente antes como requisito legal-. Lo confirmo igualmente. Queda
   como decision suya, no como olvido nuestro.

   LO QUE NO SE TOCA

   UQ_declaracion_numero (migracion 024) se queda. Un numero identifica el
   documento ante el contribuyente y ante el banco, asi que dos declaraciones
   con el mismo numero serian dos recibos indistinguibles. Eso no es politica
   discutible, es condicion para cobrar.
   ============================================================================ */

SET NOCOUNT ON;
GO

SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
GO


IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UQ_declaracion_periodo_nuevas')
BEGIN
    DROP INDEX UQ_declaracion_periodo_nuevas ON dbo.ind_declaraciones_ica;
    PRINT '  - UQ_declaracion_periodo_nuevas (crear ya no bloquea por periodo)';
END
ELSE
    PRINT '  = UQ_declaracion_periodo_nuevas no existia';
GO


IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '027_numeracion_corrida')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('027_numeracion_corrida',
            'Retira UQ_declaracion_periodo_nuevas para que Crear Declaracion cree siempre, por instruccion del cliente; se le advirtio que permite dos originales del mismo periodo y lo confirmo. La version original cambiaba ademas el numero a serie corrida; se retiro antes de desplegarse porque el cliente pidio volver al formato por año y produccion habria renumerado declaraciones reales dos veces para nada.');
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRAS

       Recrear el indice de la migracion 020. Fallara si para entonces ya
       existen dos declaraciones del mismo periodo, que es justo lo que este
       cambio permite; habria que resolver esos duplicados primero.
   ---------------------------------------------------------------------------- */
