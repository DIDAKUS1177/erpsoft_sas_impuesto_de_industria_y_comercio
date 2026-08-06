/* ============================================================================
   MIGRACIÓN 2026-08 — Declaración por CONTRIBUYENTE + firma de contador
   ----------------------------------------------------------------------------
   >>> YA APLICADA en el servidor el 2026-08-04. Se conserva como registro. <<<

   Resultado verificado:
     BLOQUE 1 respaldo    -> 256 / 246 / 8 filas copiadas (conteos coincidieron)
     BLOQUE 2 limpieza    -> las 3 tablas quedaron en 0
     BLOQUE 3 eje         -> dec_IdEstablecimiento NULLABLE + índice único creado
     BLOQUE 4 contador    -> 8/8 campos nuevos + fd_Rol + índice de rol
     BLOQUE 5 traslado    -> 3 contribuyentes recibieron datos de contador/revisor

   NOTA sobre el RESEED de identidad: los DBCC CHECKIDENT del BLOQUE 2 NO se
   ejecutaron. La clase de conexión trata los mensajes informativos de DBCC
   (SQLSTATE 01000) como error y aborta. Se dejaron fuera a propósito: que los
   IDs sigan desde 262 en vez de reiniciar en 1 evita que un dec_Id nuevo
   coincida con uno del respaldo, lo cual sería confuso al comparar.

   Ejecutar en orden, un bloque a la vez, verificando entre uno y otro.

   Contexto de las reglas de negocio (confirmadas con la Secretaría de Hacienda):
     - La declaración es UNA por contribuyente y período, no por establecimiento.
     - Las bases gravables se agrupan por ACTIVIDAD económica (CIIU), sumando
       todos los establecimientos: 3 restaurantes con el mismo CIIU = 1 base.
     - Los ingresos de fuera del municipio los reporta el contribuyente
       (total nacional − fuera de Paipa = base a declarar en Paipa).
     - "No. establecimientos" del formulario cuenta SOLO los de Paipa.
     - Contador y revisor fiscal comparten una única casilla y un único sello.
   ============================================================================ */


/* ============================================================================
   BLOQUE 1 — RESPALDO (no destructivo, ejecutar primero)
   ----------------------------------------------------------------------------
   Copia las 3 tablas afectadas a tablas _bkp_ con fecha. Son copias dentro de
   la misma base: rápidas de hacer y de restaurar, pero NO reemplazan un
   BACKUP DATABASE completo. Si se puede sacar el .bak del servidor, mejor.
   ============================================================================ */

IF OBJECT_ID('dbo.bkp_20260804_declaraciones_ica') IS NOT NULL
    DROP TABLE dbo.bkp_20260804_declaraciones_ica;
SELECT * INTO dbo.bkp_20260804_declaraciones_ica
FROM dbo.ind_declaraciones_ica;
GO

IF OBJECT_ID('dbo.bkp_20260804_declaraciones_ica_actividades') IS NOT NULL
    DROP TABLE dbo.bkp_20260804_declaraciones_ica_actividades;
SELECT * INTO dbo.bkp_20260804_declaraciones_ica_actividades
FROM dbo.ind_declaraciones_ica_actividades;
GO

IF OBJECT_ID('dbo.bkp_20260804_firmas_declaraciones') IS NOT NULL
    DROP TABLE dbo.bkp_20260804_firmas_declaraciones;
SELECT * INTO dbo.bkp_20260804_firmas_declaraciones
FROM dbo.firmas_declaraciones;
GO

-- VERIFICACIÓN: los conteos de origen y respaldo deben coincidir.
-- Al momento de escribir esto: 256 declaraciones, 8 firmas.
SELECT 'declaraciones'  AS tabla,
       (SELECT COUNT(*) FROM dbo.ind_declaraciones_ica)               AS origen,
       (SELECT COUNT(*) FROM dbo.bkp_20260804_declaraciones_ica)      AS respaldo
UNION ALL
SELECT 'actividades',
       (SELECT COUNT(*) FROM dbo.ind_declaraciones_ica_actividades),
       (SELECT COUNT(*) FROM dbo.bkp_20260804_declaraciones_ica_actividades)
UNION ALL
SELECT 'firmas',
       (SELECT COUNT(*) FROM dbo.firmas_declaraciones),
       (SELECT COUNT(*) FROM dbo.bkp_20260804_firmas_declaraciones);
GO


/* ============================================================================
   BLOQUE 2 — LIMPIEZA DE DATOS DE PRUEBA  ***DESTRUCTIVO — IRREVERSIBLE***
   ----------------------------------------------------------------------------
   Ejecutar SOLO después de confirmar que el BLOQUE 1 dejó los conteos iguales.

   Evidencia de que son datos de prueba (medido el 2026-08-03):
     - 256 declaraciones, TODAS del año 2026
     - solo 5 contribuyentes y 6 establecimientos distintos
     - 3 presentadas, 0 pagadas
   No hay declaraciones de vigencias anteriores ni pagos reales.

   NO borra contribuyentes ni establecimientos: esos se conservan.

   >>> DESCOMENTAR PARA EJECUTAR <<<
   ============================================================================ */

DELETE FROM dbo.ind_declaraciones_ica_actividades;
DELETE FROM dbo.firmas_declaraciones;
DELETE FROM dbo.ind_declaraciones_ica;
GO

-- Los DBCC CHECKIDENT (RESEED) se omitieron a propósito: ver la nota del
-- encabezado. Los IDs continúan desde 262.


/* ============================================================================
   BLOQUE 3 — EJE CONTRIBUYENTE (Fase 1)
   ----------------------------------------------------------------------------
   dec_IdEstablecimiento pasa a NULLABLE: la declaración cuelga del
   contribuyente (dec_IdContribuyente, que YA existe). La columna se conserva
   por compatibilidad con las pantallas actuales mientras se migran, pero deja
   de ser obligatoria.

   Ejecutar DESPUÉS del bloque 2 (con la tabla vacía el ALTER es instantáneo
   y no puede fallar por filas existentes).
   ============================================================================ */

ALTER TABLE dbo.ind_declaraciones_ica
    ALTER COLUMN dec_IdEstablecimiento INT NULL;
GO

-- Una declaración por contribuyente + año + período. Evita el duplicado que
-- hoy es posible (y que es justamente lo que el cliente quiere eliminar).
-- OJO: si más adelante se permiten correcciones como filas nuevas, este
-- índice debe excluirlas (las correcciones tienen dec_DeclaracionCorrige).
IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'UQ_declaracion_contribuyente_periodo')
BEGIN
    CREATE UNIQUE INDEX UQ_declaracion_contribuyente_periodo
        ON dbo.ind_declaraciones_ica
           (dec_IdContribuyente, dec_AnioDeclaracion, dec_MesDeclaracion)
        WHERE dec_DeclaracionCorrige IS NULL;
END
GO


/* ============================================================================
   BLOQUE 4 — CONTADOR / REVISOR FISCAL (Fase 3)
   ----------------------------------------------------------------------------
   Hoy los datos del contador y del revisor viven en ind_establecimientos
   (est_Cedula_contador, est_Nombre_contador, est_Tarjeta_profesional,
   est_Cedula_revisor, est_Nombre_revisor, est_Tarjeta_profesional_revisor)
   y NO existe ningún campo de correo — por eso hoy no hay a dónde enviarle
   el código OTP.

   Como la declaración pasa a ser del contribuyente, estos datos suben al
   contribuyente. Se agregan aquí; el traslado de los datos existentes va en
   el BLOQUE 5.
   ============================================================================ */

IF COL_LENGTH('dbo.ind_contribuyentes', 'ind_NombreContador') IS NULL
BEGIN
    ALTER TABLE dbo.ind_contribuyentes ADD
        ind_NombreContador       VARCHAR(100) NULL,
        ind_CedulaContador       VARCHAR(20)  NULL,
        ind_TarjetaProfContador  VARCHAR(50)  NULL,
        ind_EmailContador        VARCHAR(150) NULL,   -- destino del OTP
        ind_NombreRevisor        VARCHAR(100) NULL,
        ind_CedulaRevisor        VARCHAR(20)  NULL,
        ind_TarjetaProfRevisor   VARCHAR(50)  NULL,
        ind_EmailRevisor         VARCHAR(150) NULL;   -- destino del OTP
END
GO

/* ----------------------------------------------------------------------------
   firmas_declaraciones hoy admite UNA sola firma por declaración: el
   microservicio hace upsert por fd_NumeroDeclaracion. Para que el contador o
   revisor firme aparte del declarante se agrega el rol del firmante.
   ---------------------------------------------------------------------------- */

IF COL_LENGTH('dbo.firmas_declaraciones', 'fd_Rol') IS NULL
BEGIN
    ALTER TABLE dbo.firmas_declaraciones
        ADD fd_Rol VARCHAR(20) NOT NULL
            CONSTRAINT DF_firmas_declaraciones_rol DEFAULT 'declarante';
END
GO

-- Una firma por declaración y por rol (antes: una por declaración).
IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'UQ_firma_declaracion_rol')
BEGIN
    CREATE UNIQUE INDEX UQ_firma_declaracion_rol
        ON dbo.firmas_declaraciones (fd_NumeroDeclaracion, fd_Rol);
END
GO


/* ============================================================================
   BLOQUE 5 — TRASLADO DE DATOS DE CONTADOR/REVISOR (establecimiento → contribuyente)
   ----------------------------------------------------------------------------
   Si un contribuyente tiene varios establecimientos con contadores distintos,
   se toma el del establecimiento más antiguo (est_Id menor). Ejecutar y luego
   revisar con la consulta de verificación de abajo.
   ============================================================================ */

;WITH origen AS (
    SELECT
        e.est_IdContribuyente,
        e.est_Nombre_contador,
        e.est_Cedula_contador,
        e.est_Tarjeta_profesional,
        e.est_Nombre_revisor,
        e.est_Cedula_revisor,
        e.est_Tarjeta_profesional_revisor,
        ROW_NUMBER() OVER (PARTITION BY e.est_IdContribuyente
                           ORDER BY e.est_Id) AS rn
    FROM dbo.ind_establecimientos e
    WHERE LTRIM(RTRIM(ISNULL(e.est_Nombre_contador,''))) <> ''
       OR LTRIM(RTRIM(ISNULL(e.est_Nombre_revisor,'')))  <> ''
)
UPDATE c
   SET c.ind_NombreContador      = o.est_Nombre_contador,
       c.ind_CedulaContador      = o.est_Cedula_contador,
       c.ind_TarjetaProfContador = o.est_Tarjeta_profesional,
       c.ind_NombreRevisor       = o.est_Nombre_revisor,
       c.ind_CedulaRevisor       = o.est_Cedula_revisor,
       c.ind_TarjetaProfRevisor  = o.est_Tarjeta_profesional_revisor
  FROM dbo.ind_contribuyentes c
  JOIN origen o ON o.est_IdContribuyente = c.ind_Id
 WHERE o.rn = 1;
GO

-- Contribuyentes con contador/revisor pero SIN correo: a estos no se les
-- puede enviar el OTP hasta que alguien capture el correo en el RIT.
SELECT ind_Id, ind_NumeroIdentificacion,
       ind_NombreContador, ind_EmailContador,
       ind_NombreRevisor,  ind_EmailRevisor
  FROM dbo.ind_contribuyentes
 WHERE (LTRIM(RTRIM(ISNULL(ind_NombreContador,''))) <> ''
        AND LTRIM(RTRIM(ISNULL(ind_EmailContador,''))) = '')
    OR (LTRIM(RTRIM(ISNULL(ind_NombreRevisor,'')))  <> ''
        AND LTRIM(RTRIM(ISNULL(ind_EmailRevisor,'')))  = '');
GO


/* ============================================================================
   ROLLBACK del bloque 2 (si algo salió mal y hay que devolver los datos)
   ----------------------------------------------------------------------------
   SET IDENTITY_INSERT dbo.ind_declaraciones_ica ON;
   INSERT INTO dbo.ind_declaraciones_ica (<lista explícita de columnas>)
   SELECT <misma lista> FROM dbo.bkp_20260804_declaraciones_ica;
   SET IDENTITY_INSERT dbo.ind_declaraciones_ica OFF;
   -- (ídem para actividades y firmas)
   ============================================================================ */
