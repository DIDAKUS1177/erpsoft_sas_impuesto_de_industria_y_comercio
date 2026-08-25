/* ============================================================================
   016 — Las dos exenciones suben del establecimiento al contribuyente
   ----------------------------------------------------------------------------
   Pedido en la revision del 2026-08-25, sobre las dos casillas del formulario
   de establecimiento:

       "Quitar estas opciones, solo van en el RIT, con estos nombres:
        - Realiza actividades no sujetas o no gravadas
        - Sin Avisos y Tableros"

   POR QUE SUBEN

   Ser o no sujeto pasivo del impuesto, y estar o no obligado a avisos y
   tableros, es una condicion de la PERSONA frente al municipio, no de cada
   local: un contribuyente con tres locales no puede estar exento en uno y no
   en otro. Estaban en ind_establecimientos, o sea repetidas por local y
   pudiendo contradecirse entre si.

   Es el mismo movimiento que ya hicieron las actividades (migracion 005) y la
   autorizacion de notificacion (007).

   LOS NOMBRES CAMBIAN PORQUE LOS ANTERIORES ENGAÑABAN

   "Excluido" no decia de que, y "Exento Avisos y Tableros" mezclaba dos
   conceptos tributarios distintos -exento y no sujeto- que tienen efectos
   legales diferentes. Los nombres nuevos los dicto el cliente.

   LAS COLUMNAS VIEJAS NO SE BORRAN

   Misma regla que el resto de migraciones. Quedan sin nadie que les escriba;
   el formulario de establecimiento deja de mostrarlas y el codigo deja de
   enviarlas. Si algun dia hay que recuperarlas, siguen ahi.

   OJO al quitar las casillas del formulario: dos de las cuatro pantallas
   enviaban su valor con

       est_Exento: $("#est_Exento").is(":checked") ? 1 : 0

   y `.is(":checked")` sobre un elemento que ya no existe devuelve FALSE, no
   undefined. O sea que quitar solo la casilla del HTML habria escrito un 0
   solido en cada guardado, apagando el dato en silencio. Ya paso una vez con
   estas mismas dos columnas (ver CLAUDE.md). Por eso el envio se quita
   tambien, no solo la casilla.

   RIESGO

   Ninguno: son dos columnas nuevas que nacen en 0 y una copia de lo que ya
   hubiera marcado. En esta base los doce establecimientos las tienen en 0, asi
   que no hay nada que trasladar; en produccion la copia se encarga.
   ============================================================================ */

SET NOCOUNT ON;
GO

DECLARE @candado INT;
EXEC @candado = sp_getapplock
     @Resource    = 'migracion_016_exenciones',
     @LockMode    = 'Exclusive',
     @LockOwner   = 'Session',
     @LockTimeout = 120000;

IF @candado < 0
BEGIN
    RAISERROR('No se pudo tomar el candado de la migracion 016 (otra corrida en curso).', 16, 1);
    SET NOEXEC ON;
END
GO


/* ---------------------------------------------------------------------------
   1. Las columnas nuevas en el contribuyente
   --------------------------------------------------------------------------- */
IF COL_LENGTH('dbo.ind_contribuyentes', 'ind_NoSujetas') IS NULL
BEGIN
    ALTER TABLE dbo.ind_contribuyentes ADD ind_NoSujetas BIT NOT NULL DEFAULT 0;
    PRINT '  + ind_contribuyentes.ind_NoSujetas';
END
ELSE
    PRINT '  = ind_NoSujetas ya existia';
GO

IF COL_LENGTH('dbo.ind_contribuyentes', 'ind_SinAvisosTableros') IS NULL
BEGIN
    ALTER TABLE dbo.ind_contribuyentes ADD ind_SinAvisosTableros BIT NOT NULL DEFAULT 0;
    PRINT '  + ind_contribuyentes.ind_SinAvisosTableros';
END
ELSE
    PRINT '  = ind_SinAvisosTableros ya existia';
GO


/* ---------------------------------------------------------------------------
   2. Se sube lo que hubiera marcado en cualquiera de sus locales

   Basta con que UNO lo tenga marcado: la condicion es de la persona, y si un
   local decia que si, esa es la manifestacion que hizo. Ante locales que se
   contradicen se conserva el "si", que es el dato que alguien escribio a
   proposito -el 0 es el valor por defecto de la columna-.
   --------------------------------------------------------------------------- */
UPDATE c
   SET c.ind_NoSujetas         = 1,
       c.ind_FechaActualizacion = GETDATE()
  FROM dbo.ind_contribuyentes c
 WHERE c.ind_NoSujetas = 0
   AND EXISTS (SELECT 1 FROM dbo.ind_establecimientos e
                WHERE e.est_IdContribuyente = c.ind_Id AND e.est_Exento = 1);
PRINT '  = ind_NoSujetas trasladado desde los establecimientos';
GO

UPDATE c
   SET c.ind_SinAvisosTableros  = 1,
       c.ind_FechaActualizacion = GETDATE()
  FROM dbo.ind_contribuyentes c
 WHERE c.ind_SinAvisosTableros = 0
   AND EXISTS (SELECT 1 FROM dbo.ind_establecimientos e
                WHERE e.est_IdContribuyente = c.ind_Id AND e.est_Excento_avisos = 1);
PRINT '  = ind_SinAvisosTableros trasladado desde los establecimientos';
GO


/* ---------------------------------------------------------------------------
   3. Registro y liberacion del candado
   --------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '016_exenciones_al_contribuyente')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('016_exenciones_al_contribuyente',
            'Las casillas de exencion suben del establecimiento al contribuyente y cambian de nombre: est_Exento pasa a ind_NoSujetas -Realiza actividades no sujetas o no gravadas- y est_Excento_avisos a ind_SinAvisosTableros -Sin Avisos y Tableros-. Son condiciones de la persona frente al municipio, no de cada local. Las columnas viejas quedan sin uso pero no se borran.');
GO

SET NOEXEC OFF;
EXEC sp_releaseapplock @Resource = 'migracion_016_exenciones', @LockOwner = 'Session';
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRAS

       ALTER TABLE dbo.ind_contribuyentes DROP CONSTRAINT <nombre del default>;
       ALTER TABLE dbo.ind_contribuyentes DROP COLUMN ind_NoSujetas;
       ALTER TABLE dbo.ind_contribuyentes DROP COLUMN ind_SinAvisosTableros;
       DELETE FROM dbo.conf_migraciones WHERE mig_Nombre = '016_exenciones_al_contribuyente';

   Los datos de origen siguen intactos en ind_establecimientos, asi que no se
   pierde nada al volver atras.
   ---------------------------------------------------------------------------- */
