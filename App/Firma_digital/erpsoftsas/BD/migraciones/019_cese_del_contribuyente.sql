/* ============================================================================
   019 — El cese de actividades sube al contribuyente
   ----------------------------------------------------------------------------
   Pedido en la revision del 2026-08-25: "solamente las casillas Fecha cese de
   actividades, causal y observacion". Quitar el selector de establecimiento
   obliga a decidir de QUIEN es el cese, y esa decision estaba frenada porque
   contradecia lo acordado el 2026-08-19, cuando se dejo escrito que "el cese
   es de un LOCAL, no de la persona: un contribuyente puede cerrar un
   establecimiento y seguir operando los otros".

   POR QUE YA NO SE CONTRADICEN

   Esa objecion era correcta cuando se escribio: si el cese subia a la persona,
   se perdia la forma de cerrar un local suelto. Ya no, porque el 2026-08-25 el
   estado del registro del establecimiento gano la opcion "Cierre de
   establecimiento", con su constancia adjunta.

   Asi que hay dos hechos distintos y cada uno queda en su sitio:

       la PERSONA cesa actividades en el municipio   -> ind_contribuyentes
                                                        (es lo que se firma y
                                                         se imprime en el RIT)

       un LOCAL cierra y los demas siguen abiertos   -> ind_establecimientos,
                                                        estado del registro

   No se pierde nada y el RIT deja de pedir un dato que su propio formulario ya
   no imprime: la casilla 29, "Numero de Establecimiento que clausura", se
   retiro a peticion del cliente en la revision anterior. Pedir en pantalla
   algo que el papel no recoge era justamente la incoherencia.

   ES LA MISMA DIRECCION QUE TODO LO DEMAS

   Actividades (005), autorizacion de notificacion (007), matricula,
   representante, contador y revisor (003), exenciones (016), regimen y
   responsabilidades (014): todo lo que describe a la PERSONA ha ido subiendo
   del establecimiento al contribuyente. El cese es de esa misma familia.

   LAS COLUMNAS VIEJAS NO SE BORRAN

   Misma regla que el resto. est_Fecha_cierre, est_Causal y
   est_Observacion_cierre siguen ahi y siguen sirviendo al cierre de UN local.

   RIESGO

   Bajo. Tres columnas nuevas que nacen en nulo, y una copia hacia arriba de lo
   que hubiera. En esta base ningun establecimiento tiene cese real, asi que no
   hay nada que trasladar; en produccion la copia se encarga.
   ============================================================================ */

SET NOCOUNT ON;
GO

DECLARE @candado INT;
EXEC @candado = sp_getapplock
     @Resource    = 'migracion_019_cese',
     @LockMode    = 'Exclusive',
     @LockOwner   = 'Session',
     @LockTimeout = 120000;

IF @candado < 0
BEGIN
    RAISERROR('No se pudo tomar el candado de la migracion 019 (otra corrida en curso).', 16, 1);
    SET NOEXEC ON;
END
GO


/* ---------------------------------------------------------------------------
   1. El cese en el contribuyente
   --------------------------------------------------------------------------- */
IF COL_LENGTH('dbo.ind_contribuyentes', 'ind_FechaCese') IS NULL
BEGIN
    ALTER TABLE dbo.ind_contribuyentes ADD ind_FechaCese DATE NULL;
    PRINT '  + ind_contribuyentes.ind_FechaCese';
END
ELSE
    PRINT '  = ind_FechaCese ya existia';
GO

IF COL_LENGTH('dbo.ind_contribuyentes', 'ind_CausalCese') IS NULL
BEGIN
    -- 1 Fusion, 2 Escision, 3 Liquidacion, 4 Otro. Mismo catalogo que el
    -- formulario impreso; se guarda el codigo, no el texto.
    ALTER TABLE dbo.ind_contribuyentes ADD ind_CausalCese VARCHAR(1) NULL;
    PRINT '  + ind_contribuyentes.ind_CausalCese';
END
ELSE
    PRINT '  = ind_CausalCese ya existia';
GO

IF COL_LENGTH('dbo.ind_contribuyentes', 'ind_ObservacionCese') IS NULL
BEGIN
    ALTER TABLE dbo.ind_contribuyentes ADD ind_ObservacionCese VARCHAR(255) NULL;
    PRINT '  + ind_contribuyentes.ind_ObservacionCese';
END
ELSE
    PRINT '  = ind_ObservacionCese ya existia';
GO


/* ---------------------------------------------------------------------------
   2. Se sube el cese que hubiera declarado

   Se toma el MAS RECIENTE de sus locales: si la persona ceso, esa es la fecha
   en que dejo de operar. Se descarta 1900-01-01, que es en lo que SQL Server
   convierte una cadena vacia al guardarla en una columna de fecha y que ya
   ensucio otra comprobacion antes (ver la nota del PDF del RIT).
   --------------------------------------------------------------------------- */
UPDATE c
   SET c.ind_FechaCese       = x.fecha,
       c.ind_CausalCese      = x.causal,
       c.ind_ObservacionCese = x.obs,
       c.ind_FechaActualizacion = GETDATE()
  FROM dbo.ind_contribuyentes c
  JOIN (
        SELECT e.est_IdContribuyente,
               MAX(CONVERT(DATE, e.est_Fecha_cierre)) AS fecha,
               MAX(e.est_Causal)                      AS causal,
               MAX(e.est_Observacion_cierre)          AS obs
          FROM dbo.ind_establecimientos e
         WHERE e.est_Fecha_cierre IS NOT NULL
           AND CONVERT(DATE, e.est_Fecha_cierre) <> '1900-01-01'
         GROUP BY e.est_IdContribuyente
       ) x ON x.est_IdContribuyente = c.ind_Id
 WHERE c.ind_FechaCese IS NULL;
PRINT '  = cese trasladado desde los establecimientos';
GO


/* ---------------------------------------------------------------------------
   3. Registro y liberacion del candado
   --------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '019_cese_del_contribuyente')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('019_cese_del_contribuyente',
            'El cese de actividades que se firma e imprime en el RIT pasa a ser del contribuyente. No contradice lo acordado el 19 de agosto: el cierre de UN local sigue existiendo, ahora en el estado del registro del establecimiento con su constancia adjunta. Son dos hechos distintos y cada uno queda en su sitio. Las columnas del establecimiento no se borran.');
GO

SET NOEXEC OFF;
EXEC sp_releaseapplock @Resource = 'migracion_019_cese', @LockOwner = 'Session';
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRAS

       ALTER TABLE dbo.ind_contribuyentes DROP COLUMN ind_FechaCese;
       ALTER TABLE dbo.ind_contribuyentes DROP COLUMN ind_CausalCese;
       ALTER TABLE dbo.ind_contribuyentes DROP COLUMN ind_ObservacionCese;
       DELETE FROM dbo.conf_migraciones WHERE mig_Nombre = '019_cese_del_contribuyente';

   Los datos de origen siguen intactos en ind_establecimientos.
   ---------------------------------------------------------------------------- */
