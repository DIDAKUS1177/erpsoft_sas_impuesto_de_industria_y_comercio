/* ============================================================================
   004 — Anexos de los establecimientos
   ----------------------------------------------------------------------------
   Puntos 17 y 18 de los cambios pedidos por el cliente.

   El cliente pidio "poder consultar los archivos ya subidos, no solamente
   cargarlos", y reporto que "los archivos cargados tambien los borra a la hora
   de editar". Al revisarlo resulto que la carga de archivos NUNCA estuvo
   implementada:

     - el manejo de est_Pdf1..est_Pdf4 en core/icaWebRit.js esta dentro de un
       bloque comentado;
     - el PDF de cese solo se validaba, nunca se subia;
     - no habia FormData en el JS ni $_FILES en el controlador, asi que los
       archivos no tenian por donde viajar;
     - est_Ruta_anexos esta en NULL en los 12 establecimientos, y est_Rut,
       est_Rut_segundo y est_Rut_tercero son columnas de 6 caracteres con
       valores como '1254' o 'wer': son codigos cortos, no rutas.

   O sea que los archivos no se borraban al editar: nunca llegaron a guardarse.

   Por que una tabla y no mas columnas: un establecimiento puede tener varios
   anexos y el numero no esta fijado de antemano. Con columnas habria que
   inventar un tope (est_Pdf1..N) y ampliarlo cada vez que el cliente pida uno
   mas. est_Ruta_anexos, que es lo que habria que reutilizar, es VARCHAR(150):
   no alcanza ni para dos rutas.

   Las columnas viejas (est_Pdf*, est_Rut*, est_Ruta_anexos) NO se tocan.

   Re-ejecutable: si la tabla ya existe, no hace nada.
   ============================================================================ */

IF OBJECT_ID('dbo.ind_establecimiento_anexos') IS NULL
BEGIN
    CREATE TABLE dbo.ind_establecimiento_anexos (
        anx_Id                INT IDENTITY(1,1) PRIMARY KEY,
        anx_IdEstablecimiento INT           NOT NULL,
        /* rut | camara | cedula | cese | otro */
        anx_Tipo              VARCHAR(40)   NULL,
        /* El nombre que traia el archivo, solo para mostrarlo. NO se usa para
           construir la ruta en disco. */
        anx_NombreOriginal    VARCHAR(255)  NOT NULL,
        /* Ruta relativa a la carpeta de anexos. El nombre en disco es
           generado, nunca el del usuario. */
        anx_Ruta              VARCHAR(400)  NOT NULL,
        anx_Extension         VARCHAR(10)   NULL,
        anx_Tamano            INT           NULL,
        anx_FechaCarga        DATETIME      NOT NULL DEFAULT GETDATE(),
        anx_IdUsuario         INT           NULL,
        /* Borrado logico: si alguien quita un anexo por error, el archivo
           sigue en disco y la fila se puede recuperar. */
        anx_Activo            INT           NOT NULL DEFAULT 1
    );

    CREATE INDEX IX_anexos_establecimiento
        ON dbo.ind_establecimiento_anexos (anx_IdEstablecimiento, anx_Activo);

    PRINT 'ind_establecimiento_anexos creada.';
END
ELSE
    PRINT 'ind_establecimiento_anexos ya existia.';
GO

IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '004_anexos_establecimiento')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('004_anexos_establecimiento',
            'Tabla de anexos por establecimiento. La carga de archivos no existia; las columnas est_Pdf*/est_Rut*/est_Ruta_anexos quedan intactas.');
GO

/* Verificación */
SELECT COUNT(*) AS anexos_registrados FROM dbo.ind_establecimiento_anexos;
GO
