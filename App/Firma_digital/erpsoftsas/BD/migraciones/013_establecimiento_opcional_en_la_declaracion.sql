/* ============================================================================
   013 — El establecimiento deja de ser obligatorio en la declaracion
   ----------------------------------------------------------------------------
   Encontrado el 2026-08-21 probando la creacion de declaraciones.

   LO QUE PASA

   Desde el cambio de "la declaracion es del contribuyente" (agosto 2026), el
   controlador solo manda dec_IdEstablecimiento si viene en la peticion:

       if (!empty($_POST['dec_IdEstablecimiento'])) {
           $_obj->set_dec_IdEstablecimiento($_POST['dec_IdEstablecimiento']);
       }

   porque el establecimiento quedo como simple referencia de auditoria: sirve
   para saber desde que local se pulso el boton, no para decidir de quien es
   la declaracion. Pero en la base la columna sigue siendo NOT NULL, asi que
   si no viene el INSERT revienta con:

       Cannot insert the value NULL into column 'dec_IdEstablecimiento'

   y como esa excepcion no la atrapaba nadie, el usuario recibia una respuesta
   vacia (500) sin ningun mensaje. Es el mismo sintoma que el cliente ya
   reporto en otros botones: "aprieto y no pasa nada".

   Hoy no se dispara desde la pantalla de Presentar, porque el boton "Crear
   declaracion" sale en la fila de un establecimiento y siempre lo manda. Pero
   basta un contribuyente sin locales registrados, o una pantalla nueva que
   cree la declaracion desde el contribuyente, para caer en ello.

   POR QUE NO ESTABA APLICADO

   El ALTER existe en BD/migracion_2026-08_contribuyente.sql (bloque 3), pero
   ese archivo se escribio para ejecutarse sobre la tabla VACIA y no se aplico
   en las bases que ya tenian declaraciones. Se rescata aqui, solo el ALTER,
   de forma idempotente y sin suponer nada sobre el contenido de la tabla.

   El indice unico UQ_declaracion_contribuyente_periodo del mismo bloque NO se
   incluye: sigue bloqueado por los duplicados historicos, y crearlo es una
   decision aparte que exige antes limpiarlos.

   RIESGO

   Ninguno sobre los datos: relajar NOT NULL no toca ni una fila y no cambia
   lo que ya esta guardado (ahora mismo no hay ninguna fila con la columna en
   nulo). Es reversible mientras no se inserten nulos; la vuelta atras queda
   al pie.
   ============================================================================ */

SET NOCOUNT ON;
GO

/* --- Comprobaciones previas: el ALTER falla si la columna esta amarrada a un
       indice o a una clave foranea, asi que se avisa antes de intentarlo. --- */
DECLARE @atada INT = 0;

SELECT @atada = COUNT(*)
  FROM sys.index_columns ic
  JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
 WHERE ic.object_id = OBJECT_ID('dbo.ind_declaraciones_ica')
   AND c.name = 'dec_IdEstablecimiento';

IF @atada > 0
    PRINT '  AVISO: dec_IdEstablecimiento participa en algun indice; revisar antes de continuar.';
ELSE
    PRINT '  dec_IdEstablecimiento no participa en indices.';
GO

IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = 'dbo'
              AND TABLE_NAME   = 'ind_declaraciones_ica'
              AND COLUMN_NAME  = 'dec_IdEstablecimiento'
              AND IS_NULLABLE  = 'NO')
BEGIN
    ALTER TABLE dbo.ind_declaraciones_ica
        ALTER COLUMN dec_IdEstablecimiento INT NULL;

    PRINT '  dec_IdEstablecimiento pasa a admitir NULL.';
END
ELSE
    PRINT '  dec_IdEstablecimiento ya admitia NULL; no se hace nada.';
GO

/* --- Registro de la migracion --- */
IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '013_establecimiento_opcional_en_la_declaracion')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('013_establecimiento_opcional_en_la_declaracion',
            'dec_IdEstablecimiento pasa a admitir NULL. El codigo ya lo trataba como opcional -referencia de auditoria del local desde donde se pulso el boton- pero la columna seguia siendo NOT NULL y el INSERT reventaba con un 500 en blanco. Se rescata el ALTER del bloque 3 de migracion_2026-08_contribuyente.sql, que no se aplico en bases con declaraciones. El indice unico de ese mismo bloque queda fuera: sigue bloqueado por los duplicados historicos.');
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRAS (solo si ninguna fila quedo con la columna en nulo)

       ALTER TABLE dbo.ind_declaraciones_ica
           ALTER COLUMN dec_IdEstablecimiento INT NOT NULL;

   Si ya hay nulos, primero hay que decidir con que llenarlos.
   ---------------------------------------------------------------------------- */
