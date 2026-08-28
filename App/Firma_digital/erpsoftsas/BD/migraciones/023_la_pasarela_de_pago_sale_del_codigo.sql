/* ============================================================================
   023 — La pasarela de pago se configura, no se despliega
   ----------------------------------------------------------------------------
   El cliente lo dijo el 2026-08-26 sobre la eleccion entre Banco de Bogota y
   BBVA: "dependiendo del contrato es diferente".

   Hoy el convenio de recaudo esta escrito en el codigo. Las credenciales y la
   direccion de la pasarela son constantes de config.municipio.php:

       PLACETOPAY_LOGIN / PLACETOPAY_SECRETKEY / PLACETOPAY_BASEURL

   Ese archivo NO se sube por git: se edita a mano en el servidor. O sea que
   cambiar de convenio, rotar un secreto filtrado o simplemente pasar de
   pruebas a produccion exige hoy que alguien con acceso a Plesk edite un
   archivo. La Alcaldia no puede.

   Es exactamente la situacion de la que se saco al EAN de recaudo en la
   migracion 009, y por el mismo motivo que dio Javier entonces: "se debe
   configurar en una tabla para poderlo cambiar en caso que sea necesario, cada
   entidad tiene su propio EAN". El convenio de pasarela es igual de propio.

   QUE HACE ESTA MIGRACION

   1. Una columna par_Sensible en conf_parametros.
   2. Tres parametros nuevos para la pasarela, con el secreto marcado sensible.

   POR QUE HACE FALTA par_Sensible

   Porque sin ella esto seria un retroceso, no una mejora. La pantalla de
   configuracion devuelve el valor de cada parametro al navegador y lo pinta en
   una casilla de texto; ademas, al guardar deja el valor nuevo escrito en el
   log del servidor. Meter ahi una clave secreta la pondria a la vista de
   cualquiera con rol 1 o 2 y la dejaria copiada en los logs — peor que el
   archivo de hoy, que al menos solo lo ve quien entra al servidor.

   Con par_Sensible = 1 el controlador nunca devuelve el valor: manda solo si
   esta puesto o no, y solo lo sobrescribe cuando escriben uno nuevo. El log
   anota que se cambio, no a que.

   LOS TRES NACEN VACIOS, A PROPOSITO

   Un parametro vacio hace que el codigo caiga a la constante de siempre, que
   es la que hoy funciona. Asi que aplicar esta migracion NO cambia el
   comportamiento de nada: solo habilita el camino nuevo. El dia que la
   Alcaldia escriba los valores en la pantalla, la tabla manda; mientras tanto,
   el archivo. Igual que el EAN.

   Sembrar aqui las credenciales de sandbox seria ademas meter un secreto en un
   archivo versionado, que es justo lo que se quiere dejar de hacer.

   LO QUE ESTA MIGRACION NO RESUELVE

   Cambiar de PROVEEDOR. Esto permite apuntar a otro convenio de la misma
   pasarela -otra entidad, otro ambiente, otro banco del grupo-, que es lo que
   cubre "depende del contrato" en la mayoria de los casos. Si el contrato de
   BBVA fuera con una pasarela de otro fabricante, hablamos de un protocolo
   distinto y eso es un adaptador, no un parametro. Queda anotado en el README.

   RIESGO

   Ninguno sobre el comportamiento actual: columna nueva con valor por defecto
   y tres filas vacias que nadie lee todavia.
   ============================================================================ */

SET NOCOUNT ON;
GO


/* ---------------------------------------------------------------------------
   Guarda: sin la tabla de la migracion 009 no hay nada que hacer
   --------------------------------------------------------------------------- */
IF OBJECT_ID('dbo.conf_parametros', 'U') IS NULL
BEGIN
    RAISERROR('Falta la tabla conf_parametros: aplique antes la migracion 009.', 16, 1);
    SET NOEXEC ON;
END
GO


/* ---------------------------------------------------------------------------
   1. La marca de "esto no se muestra"
   --------------------------------------------------------------------------- */
IF COL_LENGTH('dbo.conf_parametros', 'par_Sensible') IS NULL
BEGIN
    ALTER TABLE dbo.conf_parametros
        ADD par_Sensible BIT NOT NULL CONSTRAINT DF_conf_parametros_sensible DEFAULT 0;

    PRINT '  + conf_parametros.par_Sensible';
END
ELSE
    PRINT '  = conf_parametros.par_Sensible ya existia';
GO


/* ---------------------------------------------------------------------------
   2. Los tres parametros de la pasarela

   Los patrones se guardan CON anclas y SIN delimitadores, que es como los lee
   _guardarParametro (ver el comentario alli): el controlador solo les pone las
   barras.

   El de la clave secreta es deliberadamente ancho -cualquier cosa que no sean
   espacios, de 8 a 200- porque el formato lo decide el banco y no nos toca
   adivinarlo; lo unico que se impide es guardar basura obviamente corta.
   --------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = 'PASARELA_BASEURL')
    INSERT INTO dbo.conf_parametros (par_Clave, par_Valor, par_Nombre, par_Descripcion, par_Patron, par_Sensible)
    VALUES ('PASARELA_BASEURL', NULL,
            'Dirección de la pasarela de pago',
            'La dirección del servicio con el que se cobra por PSE, terminada en /api y sin barra final. La entrega el banco: una para pruebas y otra para producción. Vacío = se usa la del archivo de configuración del servidor.',
            '^https://[A-Za-z0-9.-]+(/[A-Za-z0-9._~/-]*)?$', 0);
GO

IF NOT EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = 'PASARELA_LOGIN')
    INSERT INTO dbo.conf_parametros (par_Clave, par_Valor, par_Nombre, par_Descripcion, par_Patron, par_Sensible)
    VALUES ('PASARELA_LOGIN', NULL,
            'Usuario del convenio de recaudo',
            'El identificador que entrega el banco junto con la clave secreta. Identifica el convenio de ESTA entidad. Vacío = se usa el del archivo de configuración del servidor.',
            '^[A-Za-z0-9._-]{8,100}$', 0);
GO

IF NOT EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = 'PASARELA_SECRETKEY')
    INSERT INTO dbo.conf_parametros (par_Clave, par_Valor, par_Nombre, par_Descripcion, par_Patron, par_Sensible)
    VALUES ('PASARELA_SECRETKEY', NULL,
            'Clave secreta del convenio de recaudo',
            'La clave que entrega el banco con el usuario. No se muestra nunca: solo se indica si está puesta. Para cambiarla, escriba la nueva; dejarla en blanco no la borra. Vacío = se usa la del archivo de configuración del servidor.',
            '^\S{8,200}$', 1);
GO

PRINT '  + PASARELA_BASEURL, PASARELA_LOGIN, PASARELA_SECRETKEY (vacios: manda el archivo hasta que se llenen)';
GO


IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '023_la_pasarela_de_pago_sale_del_codigo')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('023_la_pasarela_de_pago_sale_del_codigo',
            'Las credenciales y la direccion de la pasarela pasan a conf_parametros, para que el convenio de recaudo -que depende del contrato de cada entidad- se cambie sin desplegar. Mismo patron que el EAN de la 009: manda la tabla y la constante queda de respaldo. Anade par_Sensible, sin la cual la clave secreta quedaria a la vista en la pantalla y escrita en los logs. Los tres nacen vacios, asi que aplicarla no cambia nada.');
GO

SET NOEXEC OFF;
GO

/* ----------------------------------------------------------------------------
   COMO SE USA

   La Alcaldia entra a Configuración y escribe los tres valores que le entregue
   el banco. Desde ese momento manda la tabla. Para volver al archivo, se borran
   los valores.

   VUELTA ATRAS

       DELETE FROM dbo.conf_parametros
        WHERE par_Clave IN ('PASARELA_BASEURL','PASARELA_LOGIN','PASARELA_SECRETKEY');

       ALTER TABLE dbo.conf_parametros DROP CONSTRAINT DF_conf_parametros_sensible;
       ALTER TABLE dbo.conf_parametros DROP COLUMN par_Sensible;

       DELETE FROM dbo.conf_migraciones
        WHERE mig_Nombre = '023_la_pasarela_de_pago_sale_del_codigo';
   ---------------------------------------------------------------------------- */
