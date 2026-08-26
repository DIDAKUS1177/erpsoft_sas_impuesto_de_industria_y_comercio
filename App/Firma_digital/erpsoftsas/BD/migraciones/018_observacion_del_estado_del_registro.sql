/* ============================================================================
   018 — Observacion del estado del registro del establecimiento
   ----------------------------------------------------------------------------
   Pedido en la revision del 2026-08-25, sobre el formulario de establecimiento:

       "Y dejar solo observacion con su casilla para escribir en el estado"

   QUE PASABA

   El bloque "Observacion y Autorizacion" habia quedado VACIO: un titulo sin
   nada debajo. Sus dos contenidos se habian ido a otro sitio -la observacion
   del cese al RIT, y la autorizacion de notificacion al contribuyente, porque
   es una manifestacion de la persona y pedirla en cada local la repetia-. Nadie
   quito el titulo, asi que el cliente veia un encabezado que no llevaba a nada.

   POR QUE UNA COLUMNA NUEVA Y NO REUSAR est_Observacion_cierre

   Es la tentacion obvia: ya existe, es varchar(255) y esta libre en esta
   pantalla. Pero el RIT la imprime como la observacion del CESE. Si el
   formulario de establecimiento escribiera ahi una nota de "Inscripcion", esa
   nota saldria en el formulario impreso como si el contribuyente hubiera
   declarado un cese. Un dato correcto en el sitio equivocado es peor que un
   dato ausente, y mas en un documento tributario.

   Son dos cosas distintas y quedan en dos columnas distintas:

       est_Observacion_cierre   por que ceso            -> se imprime en el RIT
       est_Observacion          nota del estado actual  -> uso interno

   RIESGO

   Ninguno: columna nueva que nace en nulo. No se toca nada de lo guardado.
   ============================================================================ */

SET NOCOUNT ON;
GO

DECLARE @candado INT;
EXEC @candado = sp_getapplock
     @Resource    = 'migracion_018_observacion',
     @LockMode    = 'Exclusive',
     @LockOwner   = 'Session',
     @LockTimeout = 120000;

IF @candado < 0
BEGIN
    RAISERROR('No se pudo tomar el candado de la migracion 018 (otra corrida en curso).', 16, 1);
    SET NOEXEC ON;
END
GO


IF COL_LENGTH('dbo.ind_establecimientos', 'est_Observacion') IS NULL
BEGIN
    -- Mismo largo que est_Observacion_cierre, por coherencia: son notas del
    -- mismo tamaño escritas por la misma gente.
    ALTER TABLE dbo.ind_establecimientos ADD est_Observacion VARCHAR(255) NULL;
    PRINT '  + ind_establecimientos.est_Observacion';
END
ELSE
    PRINT '  = est_Observacion ya existia';
GO


IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '018_observacion_del_estado_del_registro')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('018_observacion_del_estado_del_registro',
            'Observacion del estado del registro del establecimiento. Columna propia y NO reuso de est_Observacion_cierre, que el RIT imprime como la observacion del cese: una nota de inscripcion escrita ahi saldria en el formulario como si el contribuyente hubiera declarado un cese.');
GO

SET NOEXEC OFF;
EXEC sp_releaseapplock @Resource = 'migracion_018_observacion', @LockOwner = 'Session';
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRAS

       ALTER TABLE dbo.ind_establecimientos DROP COLUMN est_Observacion;
       DELETE FROM dbo.conf_migraciones WHERE mig_Nombre = '018_observacion_del_estado_del_registro';
   ---------------------------------------------------------------------------- */
