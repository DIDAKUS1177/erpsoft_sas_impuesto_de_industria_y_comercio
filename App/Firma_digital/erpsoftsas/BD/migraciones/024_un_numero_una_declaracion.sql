/* ============================================================================
   024 — Un número, una declaración
   ----------------------------------------------------------------------------
   El número de declaración es lo que identifica el documento ante todo el
   mundo: es lo que ve el contribuyente, lo que va impreso en el formulario y lo
   que viaja dentro del código de barras con que el banco recauda. Dos
   declaraciones con el mismo número serían dos recibos que el banco no puede
   distinguir.

   Hasta hoy nada lo impedía. El número lo reparte un procedimiento que entrega
   consecutivos (migración 012), pero la columna no tenía índice único, así que
   cualquier camino que se saltara ese procedimiento —o cualquier fallo suyo—
   metía el duplicado en silencio.

   POR QUÉ AHORA

   Al revisar la numeración el 2026-08-29 aparecieron dos maneras de llegar a un
   número repetido: la siembra del contador cuando empieza un año nuevo, y la
   devolución del número al descartar un borrador. Las dos se corrigen en el
   código, pero un índice es lo único que lo hace IMPOSIBLE en vez de
   improbable. Es el mismo razonamiento de la migración 020 con las
   declaraciones duplicadas y el de la 022 con los correos.

   POR QUÉ FILTRADO

   Hay 99 declaraciones sin número —anteriores al consecutivo— y no se les
   inventa uno: renumerar documentos existentes es peor que dejarlos como están.
   El índice ignora los nulos, así que conviven sin problema.

   Medido antes de crearlo: cero números repetidos, así que entra limpio.

   RIESGO

   Bajo. Si alguna vez un INSERT intentara repetir un número, sería rechazado
   —que es exactamente lo que se busca—. Los caminos vivos ya piden el número al
   procedimiento, así que en condiciones normales no se nota.
   ============================================================================ */

SET NOCOUNT ON;
GO


/* ---------------------------------------------------------------------------
   Guarda: si ya hay repetidos, no se crea nada y se avisa
   --------------------------------------------------------------------------- */
IF EXISTS (
    SELECT 1 FROM dbo.ind_declaraciones_ica
     WHERE dec_NumeroDeclaracion IS NOT NULL
     GROUP BY dec_NumeroDeclaracion
    HAVING COUNT(*) > 1)
BEGIN
    RAISERROR('Hay números de declaración repetidos: resuélvalos antes de aplicar la migración 024.', 16, 1);
    SET NOEXEC ON;
END
GO


IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UQ_declaracion_numero')
BEGIN
    CREATE UNIQUE INDEX UQ_declaracion_numero
        ON dbo.ind_declaraciones_ica (dec_NumeroDeclaracion)
     WHERE dec_NumeroDeclaracion IS NOT NULL;

    PRINT '  + UQ_declaracion_numero';
END
ELSE
    PRINT '  = UQ_declaracion_numero ya existia';
GO


IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '024_un_numero_una_declaracion')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('024_un_numero_una_declaracion',
            'Indice unico filtrado sobre dec_NumeroDeclaracion. El numero identifica el documento ante el contribuyente y ante el banco -va dentro del codigo de barras de recaudo-, asi que dos declaraciones con el mismo numero serian dos recibos indistinguibles. Ignora los nulos: las 99 declaraciones anteriores al consecutivo de la migracion 012 no tienen numero y no se les inventa uno.');
GO

SET NOEXEC OFF;
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRAS

       DROP INDEX UQ_declaracion_numero ON dbo.ind_declaraciones_ica;
       DELETE FROM dbo.conf_migraciones WHERE mig_Nombre = '024_un_numero_una_declaracion';
   ---------------------------------------------------------------------------- */
