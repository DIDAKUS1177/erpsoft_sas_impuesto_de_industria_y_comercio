/* ============================================================================
   008 — El RIT se firma
   ----------------------------------------------------------------------------
   Reunion del 2026-08-19 con el cliente:

     "el RIT tambien debe ser firmado al momento de la actualizacion o la
      inscripcion (...) se guarde, se firme, y ya cuando quede firmado pues
      genere el PDF (...) en la ultima parte tu ves que dice 'firmas': esta la
      firma de Juan, pero tambien nos hace falta que se genere la firma de la
      persona, asi tal cual como se genera la firma de las declaraciones."

   El formulario impreso ya tiene la seccion "E. FIRMAS" con dos casillas: la
   30 del contribuyente (hoy vacia) y la 31 del funcionario (que ya trae una
   imagen fija). Esta migracion crea el soporte de la casilla 30.

   POR QUE UNA TABLA NUEVA Y NO REUSAR firmas_declaraciones

   Esa tabla se identifica por fd_NumeroDeclaracion y da por sentado que lo
   firmado es INMUTABLE: una declaracion presentada ya no cambia. El RIT es lo
   contrario, esta hecho para actualizarse (el propio formulario se llama "de
   inscripcion Y/O NOVEDADES"). Guardar solo "fulano firmo" no alcanza: si
   despues cambia la direccion, esa firma quedaria amparando un contenido que
   ya no existe, que es exactamente lo que una firma no debe hacer.

   Por eso cada firma guarda el HASH del contenido que se firmo. El PDF vuelve
   a calcular el hash de lo que va a imprimir y solo estampa la firma si
   coincide. Si el contribuyente modifica cualquier dato del RIT, el hash deja
   de coincidir y el registro vuelve a quedar SIN FIRMAR hasta que lo firme de
   nuevo. No hay que acordarse de invalidar nada a mano: se cae solo.

   La tabla es un historico, no un estado: cada firma deja su fila. Sirve de
   bitacora de quien declaro que y cuando, que es justo lo que se le pide a un
   registro tributario.

   Re-ejecutable: guardas IF NOT EXISTS / IF COL_LENGTH en cada paso.
   ============================================================================ */

/* ---------------------------------------------------------------------------
   0. Candado
   --------------------------------------------------------------------------- */
DECLARE @candado INT;
EXEC @candado = sp_getapplock
     @Resource    = 'migracion_008_firma_del_rit',
     @LockMode    = 'Exclusive',
     @LockOwner   = 'Session',
     @LockTimeout = 120000;

IF @candado < 0
BEGIN
    RAISERROR('No se pudo tomar el candado de la migracion 008 (otra corrida en curso).', 16, 1);
    SET NOEXEC ON;
END
GO


/* ---------------------------------------------------------------------------
   1. Tabla de firmas del RIT
   --------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'ind_rit_firmas')
BEGIN
    CREATE TABLE dbo.ind_rit_firmas (
        rif_Id              INT           IDENTITY(1,1) PRIMARY KEY,
        rif_IdContribuyente INT           NOT NULL,
        rif_IdUsuario       INT           NOT NULL,
        rif_NombreUsuario   NVARCHAR(500) NULL,
        rif_EmailUsuario    NVARCHAR(500) NULL,
        /* SHA-256 en hexadecimal del contenido firmado: 64 caracteres. */
        rif_Hash            VARCHAR(64)   NOT NULL,
        /* 1 inscripcion, 2 actualizacion. Es el "Estado del Registro" que ya
           maneja la pantalla; queda aqui para saber que fue cada firma. */
        rif_Opcion          INT           NULL,
        rif_FechaHora       DATETIME      NOT NULL DEFAULT GETDATE()
    );

    PRINT 'ind_rit_firmas creada.';
END
ELSE PRINT 'ind_rit_firmas ya existia.';
GO

/* Busqueda tipica: la ULTIMA firma de un contribuyente. */
IF NOT EXISTS (SELECT 1 FROM sys.indexes
                WHERE name = 'IX_rit_firmas_contribuyente'
                  AND object_id = OBJECT_ID('dbo.ind_rit_firmas'))
BEGIN
    CREATE INDEX IX_rit_firmas_contribuyente
        ON dbo.ind_rit_firmas (rif_IdContribuyente, rif_FechaHora DESC);
    PRINT 'IX_rit_firmas_contribuyente creado.';
END
ELSE PRINT 'IX_rit_firmas_contribuyente ya existia.';
GO


/* ---------------------------------------------------------------------------
   2. codigos_verificacion.codigo_Rol

   OJO: esto NO es solo para el RIT. Al implementar la firma se descubrio que
   la columna codigo_Rol NO EXISTE en la base, y sin embargo
   microservicios/firmas/api.php la usa en TODOS sus INSERT y SELECT de
   codigos. Es decir que cada intento de generar un OTP fallaba con
   "Invalid column name 'codigo_Rol'" y ningun codigo llegaba a guardarse:
   codigos_verificacion tenia 0 filas.

   Y aun asi firmas_declaraciones tenia 5 firmas registradas. Se explica
   porque la funcion 7 (registrar firma) NO vuelve a validar el codigo -da por
   hecho que la funcion 2 lo verifico antes-, de modo que las firmas se
   grababan sin que el OTP hubiera funcionado nunca. Ese segundo problema no
   se arregla aqui, es de codigo y esta anotado aparte.

   La columna se crea con el mismo criterio que fd_Rol en la migracion 001:
   VARCHAR(20) NULL. Los codigos que ya existan -si los hay- se marcan como
   'declarante', que era el unico rol posible antes.

   Valores validos: 'declarante', 'contador', 'rit'. El RIT tiene rol propio a
   proposito: los codigos se identifican por (usuario, establecimiento, rol) y
   si el RIT reusara 'declarante' con establecimiento 0 -que es lo que usan las
   declaraciones-, un codigo pedido para firmar una declaracion serviria para
   firmar el RIT y al reves. Son dos autorizaciones distintas.
   --------------------------------------------------------------------------- */
IF COL_LENGTH('dbo.codigos_verificacion', 'codigo_Rol') IS NULL
BEGIN
    ALTER TABLE dbo.codigos_verificacion ADD codigo_Rol VARCHAR(20) NULL;
    PRINT 'codigo_Rol agregada (el OTP no podia funcionar sin ella).';
END
ELSE PRINT 'codigo_Rol ya existia.';
GO

/* Va en EXEC porque los nombres de columna de un literal SQL se resuelven al
   COMPILAR el lote, no al ejecutarlo: si la columna acabara de crearse arriba,
   un UPDATE escrito en linea reventaria igual aunque la rama no se tomara. */
EXEC('UPDATE dbo.codigos_verificacion SET codigo_Rol = ''declarante'' WHERE codigo_Rol IS NULL');
PRINT 'Codigos existentes marcados como declarante.';
GO


/* ---------------------------------------------------------------------------
   3. Informe
   --------------------------------------------------------------------------- */
SELECT
    firmas_rit     = (SELECT COUNT(*) FROM dbo.ind_rit_firmas),
    contribuyentes = (SELECT COUNT(*) FROM dbo.ind_contribuyentes),
    codigo_Rol     = COL_LENGTH('dbo.codigos_verificacion', 'codigo_Rol');
GO


/* ---------------------------------------------------------------------------
   4. Registro y liberacion del candado
   --------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '008_firma_del_rit')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('008_firma_del_rit',
            'El RIT pasa a firmarse con OTP, igual que las declaraciones. Cada firma guarda el hash del contenido firmado, de modo que cualquier novedad posterior invalida la firma sola y obliga a firmar de nuevo. Alimenta la casilla 30 del formulario impreso.');
GO

SET NOEXEC OFF;
EXEC sp_releaseapplock @Resource = 'migracion_008_firma_del_rit', @LockOwner = 'Session';
GO
