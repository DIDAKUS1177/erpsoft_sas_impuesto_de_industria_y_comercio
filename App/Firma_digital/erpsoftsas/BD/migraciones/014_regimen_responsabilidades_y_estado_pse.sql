/* ============================================================================
   014 — Regimen tributario, responsabilidades y estado del pago PSE
   ----------------------------------------------------------------------------
   Pedido en la revision del 2026-08-25.

   TRES COSAS EN UNA SOLA MIGRACION

   Van juntas a proposito: son tres ALTER sobre dos tablas que ya existen, y
   partirlas en tres migraciones separadas sobre las mismas tablas es pedir
   problemas al desplegar.

   1. REGIMEN TRIBUTARIO Y RESPONSABILIDADES (ind_contribuyentes)

   El cliente pide dos casillas de seleccion MULTIPLE en el RIT:

       Regimen tributario   -> ordinario, simple, especial,
                               responsable de IVA, no responsable de IVA
       Responsabilidades    -> agente de retencion, autorretenedor,
                               informante de exogena

   Se guardan como lista de codigos separados por coma en una sola columna
   (p.ej. 'ORDINARIO,RESP_IVA'). No se hace una tabla aparte por cada una
   porque son catalogos cerrados de cinco y tres valores que el usuario no
   administra: una tabla de union añadiria dos tablas y cuatro consultas para
   guardar lo mismo.

   OJO con ind_IdRegimen, que YA existe: es un INT de un solo valor y no sirve
   para esto -el cliente pide varias a la vez-. Se deja como esta, porque hay
   codigo que lo escribe, pero la casilla nueva del RIT usa la columna nueva.

   2. ESTADO DEL PAGO POR PSE (ind_declaraciones_ica)

   Hoy solo se guarda el pago cuando el banco responde APPROVED. Si responde
   REJECTED, PENDING o FAILED no queda rastro en ningun lado, porque no hay
   donde anotarlo: la declaracion simplemente sigue sin pagar y nadie sabe si
   fue un rechazo o si esta en tramite.

   En PSE colombiano eso importa: un pago puede quedarse en PENDING durante
   horas. Comprobado el 2026-08-25 contra el entorno de pruebas del banco: la
   sesion se creo bien y quedo en PENDING.

   3. CONSECUTIVO DEL CODIGO DE ESTABLECIMIENTO

   Se siembra el contador en la tabla que ya creo la migracion 012. El codigo
   del establecimiento no se generaba solo y quedaba en NULL; los que hay
   estan puestos a mano y sin criterio (12, 121, 999, o el NIT).

   RIESGO

   Ninguno sobre los datos: son columnas nuevas que nacen en NULL y una fila
   de contador. No se toca ni se reinterpreta nada de lo que ya esta guardado.
   La vuelta atras queda al pie.
   ============================================================================ */

SET NOCOUNT ON;
GO

DECLARE @candado INT;
EXEC @candado = sp_getapplock
     @Resource    = 'migracion_014_regimen_pse',
     @LockMode    = 'Exclusive',
     @LockOwner   = 'Session',
     @LockTimeout = 120000;

IF @candado < 0
BEGIN
    RAISERROR('No se pudo tomar el candado de la migracion 014 (otra corrida en curso).', 16, 1);
    SET NOEXEC ON;
END
GO


/* ---------------------------------------------------------------------------
   1. Regimen tributario y responsabilidades en el contribuyente
   --------------------------------------------------------------------------- */
IF COL_LENGTH('dbo.ind_contribuyentes', 'ind_RegimenTributario') IS NULL
BEGIN
    ALTER TABLE dbo.ind_contribuyentes ADD ind_RegimenTributario VARCHAR(200) NULL;
    PRINT '  + ind_contribuyentes.ind_RegimenTributario';
END
ELSE
    PRINT '  = ind_RegimenTributario ya existia';
GO

IF COL_LENGTH('dbo.ind_contribuyentes', 'ind_Responsabilidades') IS NULL
BEGIN
    ALTER TABLE dbo.ind_contribuyentes ADD ind_Responsabilidades VARCHAR(200) NULL;
    PRINT '  + ind_contribuyentes.ind_Responsabilidades';
END
ELSE
    PRINT '  = ind_Responsabilidades ya existia';
GO


/* ---------------------------------------------------------------------------
   2. Estado del pago por PSE en la declaracion
   --------------------------------------------------------------------------- */
IF COL_LENGTH('dbo.ind_declaraciones_ica', 'dec_PSE_Estado') IS NULL
BEGIN
    -- APPROVED / REJECTED / PENDING / FAILED, tal como los devuelve el banco
    ALTER TABLE dbo.ind_declaraciones_ica ADD dec_PSE_Estado VARCHAR(20) NULL;
    PRINT '  + ind_declaraciones_ica.dec_PSE_Estado';
END
ELSE
    PRINT '  = dec_PSE_Estado ya existia';
GO

IF COL_LENGTH('dbo.ind_declaraciones_ica', 'dec_PSE_FechaEstado') IS NULL
BEGIN
    ALTER TABLE dbo.ind_declaraciones_ica ADD dec_PSE_FechaEstado DATETIME2 NULL;
    PRINT '  + ind_declaraciones_ica.dec_PSE_FechaEstado';
END
ELSE
    PRINT '  = dec_PSE_FechaEstado ya existia';
GO

IF COL_LENGTH('dbo.ind_declaraciones_ica', 'dec_PSE_Mensaje') IS NULL
BEGIN
    -- El texto que da el banco cuando rechaza. Sin esto, un rechazo solo se
    -- puede explicar entrando al panel de PlacetoPay.
    ALTER TABLE dbo.ind_declaraciones_ica ADD dec_PSE_Mensaje VARCHAR(300) NULL;
    PRINT '  + ind_declaraciones_ica.dec_PSE_Mensaje';
END
ELSE
    PRINT '  = dec_PSE_Mensaje ya existia';
GO

/* Las que ya estan pagadas se marcan como aprobadas: es lo que fueron, y asi
   la columna nace coherente en vez de con un hueco para las historicas. */
UPDATE dbo.ind_declaraciones_ica
   SET dec_PSE_Estado      = 'APPROVED',
       dec_PSE_FechaEstado = ISNULL(dec_FechaPago, dec_FechaRealPago)
 WHERE ISNULL(dec_Pagado, 0) = 1
   AND dec_PSE_RequestId IS NOT NULL
   AND dec_PSE_Estado IS NULL;
PRINT '  = pagos PSE anteriores marcados como APPROVED';
GO


/* ---------------------------------------------------------------------------
   3. Contador para el codigo de establecimiento
   --------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM dbo.ind_consecutivos WHERE cse_Tipo = 'ESTABLECIMIENTO' AND cse_Anio = 0)
BEGIN
    /* Arranca en 1000 y NO por encima del maximo existente: hay codigos
       puestos a mano que son el NIT del contribuyente (1192762963), y arrancar
       por encima de eso daria codigos de diez digitos para siempre. Quien
       reparte el codigo salta los que ya esten ocupados, que son doce.
       cse_Anio = 0 porque este consecutivo NO se reinicia cada año: el codigo
       identifica al local para siempre. */
    INSERT INTO dbo.ind_consecutivos (cse_Tipo, cse_Anio, cse_Valor, cse_FechaActualizacion)
    VALUES ('ESTABLECIMIENTO', 0, 1000, GETDATE());

    PRINT '  + contador ESTABLECIMIENTO sembrado';
END
ELSE
    PRINT '  = el contador ESTABLECIMIENTO ya existia';
GO


/* ---------------------------------------------------------------------------
   4. Registro y liberacion del candado
   --------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '014_regimen_responsabilidades_y_estado_pse')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('014_regimen_responsabilidades_y_estado_pse',
            'Regimen tributario y responsabilidades del contribuyente como listas de seleccion multiple; estado, fecha y mensaje del pago por PSE en la declaracion -antes solo se guardaba el pago aprobado y un PENDING o un rechazo no dejaban rastro-; y contador para generar el codigo de establecimiento, que no se generaba solo.');
GO

SET NOEXEC OFF;
EXEC sp_releaseapplock @Resource = 'migracion_014_regimen_pse', @LockOwner = 'Session';
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRAS

       ALTER TABLE dbo.ind_contribuyentes    DROP COLUMN ind_RegimenTributario;
       ALTER TABLE dbo.ind_contribuyentes    DROP COLUMN ind_Responsabilidades;
       ALTER TABLE dbo.ind_declaraciones_ica DROP COLUMN dec_PSE_Estado;
       ALTER TABLE dbo.ind_declaraciones_ica DROP COLUMN dec_PSE_FechaEstado;
       ALTER TABLE dbo.ind_declaraciones_ica DROP COLUMN dec_PSE_Mensaje;
       DELETE FROM dbo.ind_consecutivos WHERE cse_Tipo = 'ESTABLECIMIENTO';
       DELETE FROM dbo.conf_migraciones  WHERE mig_Nombre = '014_regimen_responsabilidades_y_estado_pse';
   ---------------------------------------------------------------------------- */
