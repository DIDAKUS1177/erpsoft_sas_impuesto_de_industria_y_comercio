/* ============================================================================
   017 — Los anexos pueden colgar del contribuyente, no solo del establecimiento
   ----------------------------------------------------------------------------
   Pedido en la revision del 2026-08-25:

       "EN EL RIT FALTA LA OPCION PARA SUBIR LOS DOCUMENTOS:
        - RUT (obligatorio)
        - Certificado de existencia y representacion legal - Camara de comercio
          o Acta de constitucion (obligatorio)
        - Documento de identificacion Representante Legal o Propietario
          (obligatorio)
        - Uso de suelo (opcional)"

   POR QUE NO SE HACE UNA TABLA NUEVA

   Ya existe ind_establecimiento_anexos con todo el mecanismo alrededor:
   validacion de extension Y de tipo real del contenido, nombre de archivo
   generado por el servidor, carpeta blindada con .htaccess, borrado logico,
   tope de archivos y descarga con comprobacion de permiso. Duplicar eso para
   el contribuyente seria duplicar tambien la superficie donde equivocarse.

   Se le añade una segunda forma de dueño. La tabla conserva el nombre, que
   ahora queda corto; renombrarla obligaria a tocar los cinco archivos que la
   usan sin ganar nada.

   COMO QUEDA

       anx_IdEstablecimiento   el anexo es de un LOCAL   (cese, uso de suelo)
       anx_IdContribuyente     el anexo es de la PERSONA (RUT, camara, cedula)

   Exactamente una de las dos lleva valor. Las dos existentes se rellenan hacia
   arriba: cada anexo de un establecimiento anota tambien de quien es ese
   establecimiento, para que una consulta por contribuyente encuentre todo lo
   suyo sin importar por donde se subio.

   POR QUE ESTOS DOCUMENTOS SON DE LA PERSONA

   El RUT, el certificado de camara de comercio y la cedula del representante
   son del contribuyente: pedirlos en cada local los hacia repetir tantas veces
   como locales tuviera, y nada garantizaba que las copias coincidieran. El uso
   de suelo si es del local -lo expide la Alcaldia por direccion- pero el
   cliente lo pidio en el RIT, asi que ahi va.

   RIESGO

   Bajo. Una columna nueva que nace en nulo y un NOT NULL que se relaja. Nada
   de lo guardado cambia de significado y ningun archivo se mueve de sitio.
   ============================================================================ */

SET NOCOUNT ON;
GO

DECLARE @candado INT;
EXEC @candado = sp_getapplock
     @Resource    = 'migracion_017_anexos',
     @LockMode    = 'Exclusive',
     @LockOwner   = 'Session',
     @LockTimeout = 120000;

IF @candado < 0
BEGIN
    RAISERROR('No se pudo tomar el candado de la migracion 017 (otra corrida en curso).', 16, 1);
    SET NOEXEC ON;
END
GO


/* ---------------------------------------------------------------------------
   1. La columna del contribuyente
   --------------------------------------------------------------------------- */
IF COL_LENGTH('dbo.ind_establecimiento_anexos', 'anx_IdContribuyente') IS NULL
BEGIN
    ALTER TABLE dbo.ind_establecimiento_anexos ADD anx_IdContribuyente INT NULL;
    PRINT '  + ind_establecimiento_anexos.anx_IdContribuyente';
END
ELSE
    PRINT '  = anx_IdContribuyente ya existia';
GO


/* ---------------------------------------------------------------------------
   2. El establecimiento deja de ser obligatorio

   Un anexo del RIT no cuelga de ningun local. Es el mismo movimiento que hizo
   la migracion 013 con dec_IdEstablecimiento, y por el mismo motivo: dejar la
   columna en NOT NULL haria que el INSERT reventara con un 500 en blanco.
   --------------------------------------------------------------------------- */
IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = 'dbo'
              AND TABLE_NAME   = 'ind_establecimiento_anexos'
              AND COLUMN_NAME  = 'anx_IdEstablecimiento'
              AND IS_NULLABLE  = 'NO')
BEGIN
    ALTER TABLE dbo.ind_establecimiento_anexos
        ALTER COLUMN anx_IdEstablecimiento INT NULL;
    PRINT '  = anx_IdEstablecimiento pasa a admitir NULL';
END
ELSE
    PRINT '  = anx_IdEstablecimiento ya admitia NULL';
GO


/* ---------------------------------------------------------------------------
   3. Los anexos que ya existen anotan tambien su contribuyente
   --------------------------------------------------------------------------- */
UPDATE a
   SET a.anx_IdContribuyente = e.est_IdContribuyente
  FROM dbo.ind_establecimiento_anexos a
  JOIN dbo.ind_establecimientos e ON e.est_Id = a.anx_IdEstablecimiento
 WHERE a.anx_IdContribuyente IS NULL;
PRINT '  = anexos existentes enlazados a su contribuyente';
GO


/* ---------------------------------------------------------------------------
   4. Una fila tiene que tener dueño, y uno solo

   Sin esto, un fallo del codigo podria dejar filas huerfanas -sin
   establecimiento ni contribuyente- que no apareceran en ninguna consulta y
   cuyo archivo quedaria ocupando disco para siempre.

   Se admite el caso "los dos con valor" a proposito: es lo que queda tras el
   relleno del paso 3, donde un anexo de local anota ademas de quien es ese
   local. Lo que se prohibe es que no haya ninguno.
   --------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_anexo_tiene_dueno')
BEGIN
    ALTER TABLE dbo.ind_establecimiento_anexos WITH NOCHECK
        ADD CONSTRAINT CK_anexo_tiene_dueno
        CHECK (anx_IdEstablecimiento IS NOT NULL OR anx_IdContribuyente IS NOT NULL);
    PRINT '  + CK_anexo_tiene_dueno';
END
ELSE
    PRINT '  = CK_anexo_tiene_dueno ya existia';
GO


/* ---------------------------------------------------------------------------
   5. Registro y liberacion del candado
   --------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '017_anexos_del_contribuyente')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('017_anexos_del_contribuyente',
            'Los anexos pueden colgar del contribuyente ademas del establecimiento, para poder subir el RUT, la camara de comercio, la cedula del representante y el uso de suelo desde el RIT. Se reutiliza ind_establecimiento_anexos con todo su mecanismo de validacion en vez de crear una tabla paralela. anx_IdEstablecimiento pasa a admitir NULL y los anexos existentes anotan tambien su contribuyente.');
GO

SET NOEXEC OFF;
EXEC sp_releaseapplock @Resource = 'migracion_017_anexos', @LockOwner = 'Session';
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRAS (solo si ningun anexo cuelga ya de un contribuyente sin local)

       ALTER TABLE dbo.ind_establecimiento_anexos DROP CONSTRAINT CK_anexo_tiene_dueno;
       ALTER TABLE dbo.ind_establecimiento_anexos DROP COLUMN anx_IdContribuyente;
       ALTER TABLE dbo.ind_establecimiento_anexos
           ALTER COLUMN anx_IdEstablecimiento INT NOT NULL;
       DELETE FROM dbo.conf_migraciones WHERE mig_Nombre = '017_anexos_del_contribuyente';
   ---------------------------------------------------------------------------- */
