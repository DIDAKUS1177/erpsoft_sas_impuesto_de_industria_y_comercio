/* ============================================================================
   009 — Parametros configurables, y el EAN de recaudo del ICA
   ----------------------------------------------------------------------------
   El 2026-08-20 Javier (Alcaldia de Paipa) entrego el EAN de recaudo del ICA:

       7709998161047

   Lo mando dentro de un formulario real de Paipa ya generado, con su codigo de
   barras impreso, y pidio expresamente:

     "creeria que se debe configurar en una tabla para poderlo cambiar en caso
      que sea necesario, cada entidad tiene su propio EAN"

   Tiene razon, y hasta ahora no era asi: el EAN vivia en la constante
   MUNICIPIO_EAN_RECAUDO de config.municipio.php, un archivo del servidor que
   la Alcaldia no puede tocar. Si el banco cambia el convenio, habria que pedir
   un despliegue para cambiar trece digitos.

   Se crea entonces conf_parametros: una tabla de clave/valor para los datos de
   operacion que el municipio debe poder cambiar sin tocar codigo. Nace con el
   EAN, y queda lista para lo que venga (credenciales de pasarela, plazos,
   etc.).

   POR QUE UNA TABLA GENERICA Y NO UNA COLUMNA

   Porque lo que viene detras es de la misma naturaleza -parametros de
   operacion, no entidades del negocio- y crear una tabla por cada uno
   terminaria en diez tablas de una fila. La clave es texto y unica, asi que
   leerlos es directo y no hay forma de duplicar uno por accidente.

   IMPORTANTE: la constante NO se elimina. Sigue sirviendo de respaldo si la
   tabla no tiene el parametro (ver class.codigoBarrasRecaudo.php), que es lo
   que mantiene funcionando cualquier instalacion que todavia no haya corrido
   esta migracion.

   Re-ejecutable: guardas IF NOT EXISTS en cada paso.
   ============================================================================ */

/* ---------------------------------------------------------------------------
   0. Candado
   --------------------------------------------------------------------------- */
DECLARE @candado INT;
EXEC @candado = sp_getapplock
     @Resource    = 'migracion_009_parametros',
     @LockMode    = 'Exclusive',
     @LockOwner   = 'Session',
     @LockTimeout = 120000;

IF @candado < 0
BEGIN
    RAISERROR('No se pudo tomar el candado de la migracion 009 (otra corrida en curso).', 16, 1);
    SET NOEXEC ON;
END
GO


/* ---------------------------------------------------------------------------
   1. Tabla de parametros
   --------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'conf_parametros')
BEGIN
    CREATE TABLE dbo.conf_parametros (
        par_Id          INT            IDENTITY(1,1) PRIMARY KEY,
        /* Clave tecnica con la que el codigo lo busca. No se traduce ni se
           cambia: si hiciera falta otro nombre, se crea otro parametro. */
        par_Clave       VARCHAR(60)    NOT NULL,
        par_Valor       NVARCHAR(500)  NULL,
        /* Como se ve en la pantalla de configuracion, y la ayuda que necesita
           el funcionario para saber que esta cambiando. */
        par_Nombre      NVARCHAR(200)  NOT NULL,
        par_Descripcion NVARCHAR(1000) NULL,
        /* Para validar antes de guardar sin que el patron viva en el codigo:
           el EAN son 13 digitos, otro parametro tendra otra regla. */
        par_Patron      VARCHAR(200)   NULL,
        par_Estado      INT            NOT NULL DEFAULT 1,
        par_FechaActualizacion DATETIME NULL
    );

    CREATE UNIQUE INDEX UX_conf_parametros_clave ON dbo.conf_parametros (par_Clave);

    PRINT 'conf_parametros creada.';
END
ELSE PRINT 'conf_parametros ya existia.';
GO


/* ---------------------------------------------------------------------------
   2. El EAN de recaudo del ICA

   Va en EXEC porque, si la tabla se acabara de crear en el lote anterior, un
   INSERT escrito en linea se resolveria al COMPILAR el lote y reventaria
   aunque la rama no se tomara.
   --------------------------------------------------------------------------- */
EXEC('
IF NOT EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = ''RECAUDO_EAN'')
    INSERT INTO dbo.conf_parametros (par_Clave, par_Valor, par_Nombre, par_Descripcion, par_Patron)
    VALUES (
        ''RECAUDO_EAN'',
        ''7709998161047'',
        ''EAN de recaudo (codigo de barras)'',
        ''Los 13 digitos que identifican el convenio de recaudo del municipio ante el banco. Van dentro del codigo de barras de la declaracion y son los que permiten que un cajero pueda cobrarla. Lo entrega el banco; cada entidad tiene el suyo. Si queda vacio, el codigo de barras se imprime con el numero de declaracion y NO se puede pagar en ventanilla.'',
        ''^[0-9]{13}$''
    );
');
PRINT 'Parametro RECAUDO_EAN listo.';
GO


/* ---------------------------------------------------------------------------
   2b. Vigencia del recibo (el segmento 96 del codigo de barras)

   El ejemplo que entrego Javier trae un cuarto segmento, (96)20260820, que es
   una fecha en formato AAAAMMDD. El 96 NO es un identificador estandar de
   GS1: es de uso interno del convenio, asi que su significado exacto -fecha
   de vencimiento del recibo, o simple fecha de generacion- lo tiene que
   confirmar el banco. En su ejemplo coincide con el dia en que se genero el
   PDF, que admite las dos lecturas.

   Mientras no se confirme, el parametro nace VACIO y el segmento se omite.
   Eso es deliberado: un recibo sin fecha se paga, pero un recibo con una
   fecha de vencimiento equivocada lo rechaza el cajero.

   Cuando el banco responda:
     - si es "vencimiento a N dias", se pone el numero de dias aqui;
     - si es la fecha de generacion, se pone 0.
   No hace falta tocar codigo ni desplegar.
   --------------------------------------------------------------------------- */
EXEC('
IF NOT EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = ''RECAUDO_DIAS_VIGENCIA'')
    INSERT INTO dbo.conf_parametros (par_Clave, par_Valor, par_Nombre, par_Descripcion, par_Patron)
    VALUES (
        ''RECAUDO_DIAS_VIGENCIA'',
        NULL,
        ''Dias de vigencia del recibo'',
        ''Cuantos dias vale el codigo de barras desde que se imprime. Se usa para el segmento (96) del codigo. Dejarlo VACIO omite ese segmento, que es lo recomendado mientras el banco no confirme si esa fecha es el vencimiento del recibo. Poner 0 imprime la fecha del dia en que se genera.'',
        ''^[0-9]{1,4}$''
    );
');
PRINT 'Parametro RECAUDO_DIAS_VIGENCIA listo.';
GO


/* ---------------------------------------------------------------------------
   3. Informe
   --------------------------------------------------------------------------- */
SELECT par_Clave, par_Valor, par_Nombre FROM dbo.conf_parametros ORDER BY par_Clave;
GO


/* ---------------------------------------------------------------------------
   4. Registro y liberacion del candado
   --------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '009_parametros_y_ean_de_recaudo')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('009_parametros_y_ean_de_recaudo',
            'Tabla conf_parametros (clave/valor) para lo que la Alcaldia debe poder cambiar sin tocar codigo, y el EAN de recaudo del ICA de Paipa (7709998161047) que entrego Javier el 2026-08-20. La constante MUNICIPIO_EAN_RECAUDO se conserva como respaldo.');
GO

SET NOEXEC OFF;
EXEC sp_releaseapplock @Resource = 'migracion_009_parametros', @LockOwner = 'Session';
GO
