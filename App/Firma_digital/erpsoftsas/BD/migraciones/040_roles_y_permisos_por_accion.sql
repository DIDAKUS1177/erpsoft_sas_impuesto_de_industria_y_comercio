/* ============================================================================
   040 — Roles con tipo y permisos por acción
   ----------------------------------------------------------------------------
   Pedido del cliente (2026-09-29): el administrador arma los roles en el panel
   de Roles con interruptores, uno por acción (ver, crear y editar, firmar,
   presentar, corregir, pagar...), y lo que diga el interruptor es lo que el
   sistema deja hacer.

   ANTES

   - Los permisos eran 16 submódulos heredados ("DATOS BASICOS", "ICA WEB
     CONTRIBUYENTES"...): uno solo abría a la vez Contribuyentes, Recaudo y
     Parámetros, y el menú era lo único que los miraba.
   - El servidor no los leía: decidía por el número de rol (1 administrador,
     2 Alcaldía). Un rol nuevo no funcionaba aunque tuviera todo marcado.

   EL CAMBIO

   1. conf_rol.rol_Tipo: ADMINISTRADOR | ALCALDIA | CONTRIBUYENTE | EXTERNO.
      Dice para quién es el rol; el servidor usa el tipo, no el número.
   2. conf_modulo.mod_Clave / mod_Orden y conf_submodulo.subMod_Clave /
      subMod_Orden: el catálogo nuevo (9 grupos, 46 permisos) con una clave
      estable por permiso ("ica.firmar"), que es lo que lee el código
      (business/class.permisosRol.php). Los módulos y submódulos viejos se
      dejan donde están (sin clave): el código nuevo no los lee.
   3. Los permisos que cada rol ya tenía pasan a sus equivalentes nuevos:
      los contribuyentes (rol 4) y la consulta externa (rol 3) quedan con lo
      mismo que podían hacer. El administrador no necesita filas: tiene todo.
      Cada permiso trae lo que necesita (firmar -> ver, cerrar -> editar el
      establecimiento...), como los arma el panel.

   ROLES DISTINTOS DE 1-4 (revisión 2026-09-29)

   Su tipo sale de los botones que tenían: con alguno de la Alcaldía (1639,
   1645, 26, 11-15) son de la Alcaldía; si no, con los del contribuyente
   (1640, 1641, 1643, 1644) son de contribuyentes; con solo el de predial
   (1035), consulta externa; sin ninguno, de la Alcaldía (sin permisos). Antes
   todos quedaban "de la Alcaldía": un rol usado por contribuyentes perdía hasta
   lo propio y no se podía corregir sin mover sus cuentas.

   RIESGO

   Bajo: columnas nuevas que nacen en NULL, filas nuevas y permisos nuevos por
   cada permiso viejo (los viejos no se borran). Re-ejecutable.
   ============================================================================ */

SET NOCOUNT ON;
GO

DECLARE @candado INT;
EXEC @candado = sp_getapplock
     @Resource    = 'migracion_040_roles_y_permisos',
     @LockMode    = 'Exclusive',
     @LockOwner   = 'Session',
     @LockTimeout = 120000;

IF @candado < 0
BEGIN
    RAISERROR('No se pudo tomar el candado de la migracion 040 (otra corrida en curso).', 16, 1);
    SET NOEXEC ON;
END
GO

/* ---------------------------------------------------------------------------
   1. Columnas nuevas
   --------------------------------------------------------------------------- */
IF COL_LENGTH('dbo.conf_rol', 'rol_Tipo') IS NULL
    ALTER TABLE dbo.conf_rol ADD rol_Tipo VARCHAR(20) NULL;
GO
IF COL_LENGTH('dbo.conf_modulo', 'mod_Clave') IS NULL
    ALTER TABLE dbo.conf_modulo ADD mod_Clave VARCHAR(40) NULL;
GO
IF COL_LENGTH('dbo.conf_modulo', 'mod_Orden') IS NULL
    ALTER TABLE dbo.conf_modulo ADD mod_Orden INT NULL;
GO
IF COL_LENGTH('dbo.conf_submodulo', 'subMod_Clave') IS NULL
    ALTER TABLE dbo.conf_submodulo ADD subMod_Clave VARCHAR(60) NULL;
GO
IF COL_LENGTH('dbo.conf_submodulo', 'subMod_Orden') IS NULL
    ALTER TABLE dbo.conf_submodulo ADD subMod_Orden INT NULL;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_conf_submodulo_clave')
    CREATE UNIQUE INDEX UX_conf_submodulo_clave ON dbo.conf_submodulo (subMod_Clave) WHERE subMod_Clave IS NOT NULL;
GO

/* ---------------------------------------------------------------------------
   2. Tipo de los roles que ya existen
   --------------------------------------------------------------------------- */
UPDATE dbo.conf_rol SET rol_Tipo = 'ADMINISTRADOR' WHERE rol_Id = 1 AND rol_Tipo IS NULL;
UPDATE dbo.conf_rol SET rol_Tipo = 'ALCALDIA'      WHERE rol_Id = 2 AND rol_Tipo IS NULL;
UPDATE dbo.conf_rol SET rol_Tipo = 'EXTERNO'       WHERE rol_Id = 3 AND rol_Tipo IS NULL;
UPDATE dbo.conf_rol SET rol_Tipo = 'CONTRIBUYENTE' WHERE rol_Id = 4 AND rol_Tipo IS NULL;
-- Cualquier otro rol creado antes: por los botones que tenía (ver arriba).
UPDATE r
   SET rol_Tipo = CASE
        WHEN EXISTS (SELECT 1 FROM dbo.conf_permisos p
                      WHERE p.per_IdRol = r.rol_Id AND ISNULL(p.per_Estado, 1) = 1
                        AND p.per_IdBoton IN (1639, 1645, 26, 11, 12, 13, 14, 15)) THEN 'ALCALDIA'
        WHEN EXISTS (SELECT 1 FROM dbo.conf_permisos p
                      WHERE p.per_IdRol = r.rol_Id AND ISNULL(p.per_Estado, 1) = 1
                        AND p.per_IdBoton IN (1640, 1641, 1643, 1644)) THEN 'CONTRIBUYENTE'
        WHEN EXISTS (SELECT 1 FROM dbo.conf_permisos p
                      WHERE p.per_IdRol = r.rol_Id AND ISNULL(p.per_Estado, 1) = 1
                        AND p.per_IdBoton = 1035) THEN 'EXTERNO'
        ELSE 'ALCALDIA' END
  FROM dbo.conf_rol r
 WHERE r.rol_Tipo IS NULL;
PRINT '  + tipo de rol';
GO

/* ---------------------------------------------------------------------------
   3. Catálogo: grupos (módulos) y permisos (submódulos)
   --------------------------------------------------------------------------- */
DECLARE @grupos TABLE (clave VARCHAR(40), nombre NVARCHAR(200), orden INT);
INSERT INTO @grupos VALUES
    ('alcaldia',         N'Alcaldía', 1),
    ('parametros',       N'Parámetros ICA', 2),
    ('rit',              N'RIT', 3),
    ('establecimientos', N'Establecimientos', 4),
    ('ica',              N'Declaración de ICA', 5),
    ('reteica',          N'Retención de ICA', 6),
    ('autorreteica',     N'Autorretención de ICA', 7),
    ('configuracion',    N'Usuarios y roles', 8),
    ('predial',          N'Predial', 9);

INSERT INTO dbo.conf_modulo (mod_Descripcion, mod_Nombre, mod_Icono, mod_Url, mod_Estado, mod_Clave, mod_Orden)
SELECT g.nombre, g.nombre, '', '', 1, g.clave, g.orden
  FROM @grupos g
 WHERE NOT EXISTS (SELECT 1 FROM dbo.conf_modulo m WHERE m.mod_Clave = g.clave);

DECLARE @permisos TABLE (grupo VARCHAR(40), clave VARCHAR(60), nombre NVARCHAR(200), descripcion NVARCHAR(500), orden INT);
INSERT INTO @permisos VALUES
    ('alcaldia', 'alcaldia.contribuyentes.ver',       N'Buscar y ver contribuyentes',        N'Entra a Contribuyentes y consulta la ficha de cualquiera.', 1),
    ('alcaldia', 'alcaldia.contribuyentes.editar',    N'Crear y editar contribuyentes',      N'Crea contribuyentes y corrige sus datos básicos.', 2),
    ('alcaldia', 'alcaldia.contribuyentes.gestionar', N'Gestionar a un contribuyente',       N'Trabaja como el contribuyente: su RIT, establecimientos y declaraciones, según los permisos de esas secciones.', 3),
    ('alcaldia', 'alcaldia.establecimientos.ver',     N'Ver establecimientos del municipio', N'Directorio de todos los establecimientos.', 4),
    ('alcaldia', 'alcaldia.establecimientos.cerrar',  N'Cerrar establecimientos',            N'Cierra un establecimiento con su soporte y fecha de cese.', 5),
    ('alcaldia', 'alcaldia.establecimientos.reabrir', N'Reabrir establecimientos cerrados',  N'Deshace un cierre hecho por error, con justificación.', 6),
    ('alcaldia', 'alcaldia.cese',                     N'Registrar el cese de actividades',   N'Cese del contribuyente en el RIT, con su constancia de cierre.', 7),
    ('alcaldia', 'alcaldia.recaudo.cargar',           N'Cargar archivos de recaudo',         N'Carga y aplica los archivos del banco.', 8),
    ('alcaldia', 'alcaldia.recaudo.asignar',          N'Asignar pagos a mano',               N'Asigna los pagos que el recaudo deja para revisar.', 9),
    ('alcaldia', 'alcaldia.recibo.intereses',         N'Recibos con intereses de mora',      N'Liquida los intereses de mora al generar recibos de pago.', 10),
    ('parametros', 'parametros.ver',                  N'Ver parámetros',                     N'Consulta actividades, conceptos y grupos tarifarios.', 1),
    ('parametros', 'parametros.actividades',          N'Editar actividades',                 N'Crea, edita e inactiva actividades económicas.', 2),
    ('parametros', 'parametros.conceptos',            N'Editar conceptos',                   N'Crea, edita e inactiva conceptos y fórmulas.', 3),
    ('parametros', 'parametros.grupos',               N'Editar grupos tarifarios',           N'Crea, edita e inactiva grupos tarifarios.', 4),
    ('parametros', 'parametros.municipio',            N'Municipio y bancos',                 N'Parámetros del municipio y cuentas de los bancos (además pide su contraseña).', 5),
    ('rit', 'rit.ver',                                N'Ver el RIT',                         N'Consulta el RIT y descarga el formulario.', 1),
    ('rit', 'rit.editar',                             N'Editar el RIT',                      N'Actualiza y guarda el RIT.', 2),
    ('rit', 'rit.firmar',                             N'Firmar el RIT',                      N'Pide el código de firma (llega al correo del representante legal) y firma.', 3),
    ('rit', 'rit.documentos',                         N'Subir y quitar documentos',          N'RUT, cámara de comercio y documento del representante.', 4),
    ('establecimientos', 'establecimientos.ver',      N'Ver establecimientos',               N'Consulta los establecimientos del contribuyente.', 1),
    ('establecimientos', 'establecimientos.editar',   N'Crear y editar establecimientos',    N'Registra establecimientos y actualiza sus datos.', 2),
    ('ica', 'ica.ver',                                N'Ver y descargar declaraciones',      N'Consulta las declaraciones de ICA y descarga el PDF.', 1),
    ('ica', 'ica.editar',                             N'Crear y editar borradores',          N'Crea, liquida, guarda y borra borradores.', 2),
    ('ica', 'ica.firmar',                             N'Firmar declaraciones',               N'Pide los códigos de firma del declarante y del contador y firma.', 3),
    ('ica', 'ica.presentar',                          N'Presentar declaraciones',            N'Presenta la declaración firmada.', 4),
    ('ica', 'ica.corregir',                           N'Corregir declaraciones',             N'Crea la corrección de una declaración presentada.', 5),
    ('ica', 'ica.pagar',                              N'Recibo de pago y PSE',               N'Genera el recibo de pago y paga en línea.', 6),
    ('reteica', 'reteica.ver',                        N'Ver y descargar declaraciones',      N'Consulta las declaraciones de retención y descarga el PDF.', 1),
    ('reteica', 'reteica.editar',                     N'Crear y editar borradores',          N'Crea, liquida, guarda y borra borradores.', 2),
    ('reteica', 'reteica.firmar',                     N'Firmar declaraciones',               N'Pide los códigos de firma del declarante y del contador y firma.', 3),
    ('reteica', 'reteica.presentar',                  N'Presentar declaraciones',            N'Presenta la declaración firmada.', 4),
    ('reteica', 'reteica.corregir',                   N'Corregir declaraciones',             N'Crea la corrección de una declaración presentada.', 5),
    ('reteica', 'reteica.pagar',                      N'Recibo de pago y PSE',               N'Genera el recibo de pago y paga en línea.', 6),
    ('autorreteica', 'autorreteica.ver',              N'Ver y descargar declaraciones',      N'Consulta las declaraciones de autorretención y descarga el PDF.', 1),
    ('autorreteica', 'autorreteica.editar',           N'Crear y editar borradores',          N'Crea, liquida, guarda y borra borradores.', 2),
    ('autorreteica', 'autorreteica.firmar',           N'Firmar declaraciones',               N'Pide los códigos de firma del declarante y del contador y firma.', 3),
    ('autorreteica', 'autorreteica.presentar',        N'Presentar declaraciones',            N'Presenta la declaración firmada.', 4),
    ('autorreteica', 'autorreteica.corregir',         N'Corregir declaraciones',             N'Crea la corrección de una declaración presentada.', 5),
    ('autorreteica', 'autorreteica.pagar',            N'Recibo de pago y PSE',               N'Genera el recibo de pago y paga en línea.', 6),
    ('configuracion', 'usuarios.ver',                 N'Ver usuarios',                       N'Consulta las cuentas de acceso.', 1),
    ('configuracion', 'usuarios.editar',              N'Crear y editar usuarios',            N'Crea cuentas y cambia sus datos y su rol.', 2),
    ('configuracion', 'usuarios.estado',              N'Activar e inactivar usuarios',       N'Bloquea o desbloquea cuentas.', 3),
    ('configuracion', 'roles.ver',                    N'Ver roles',                          N'Consulta los roles y sus permisos.', 4),
    ('configuracion', 'roles.editar',                 N'Crear y editar roles',               N'Crea roles, les cambia el nombre y el tipo, y los activa o inactiva.', 5),
    ('configuracion', 'roles.permisos',               N'Asignar permisos a los roles',       N'Prende y apaga los interruptores de este panel.', 6),
    ('predial', 'predial.consultar',                  N'Consultar paz y salvo',              N'Consulta de paz y salvo del impuesto predial.', 1);

INSERT INTO dbo.conf_submodulo (subMod_Nombre, subMod_Descripcion, subMod_IdModulo, subMod_Clave, subMod_Orden)
SELECT p.nombre, p.descripcion, m.mod_Id, p.clave, p.orden
  FROM @permisos p
  JOIN dbo.conf_modulo m ON m.mod_Clave = p.grupo
 WHERE NOT EXISTS (SELECT 1 FROM dbo.conf_submodulo s WHERE s.subMod_Clave = p.clave);

PRINT '  + catálogo de permisos por acción';
GO

/* ---------------------------------------------------------------------------
   4. Lo que cada rol ya tenía pasa a sus equivalentes nuevos
   (per_IdBoton de los permisos viejos -> claves nuevas)
   --------------------------------------------------------------------------- */
DECLARE @mapa TABLE (boton INT, clave VARCHAR(60));
INSERT INTO @mapa VALUES
    (1641, 'rit.ver'), (1641, 'rit.editar'), (1641, 'rit.firmar'), (1641, 'rit.documentos'),
    (1641, 'ica.ver'), (1641, 'ica.editar'), (1641, 'ica.firmar'), (1641, 'ica.presentar'), (1641, 'ica.corregir'), (1641, 'ica.pagar'),
    (1640, 'establecimientos.ver'), (1640, 'establecimientos.editar'),
    (1643, 'reteica.ver'), (1643, 'reteica.editar'), (1643, 'reteica.firmar'), (1643, 'reteica.presentar'), (1643, 'reteica.corregir'), (1643, 'reteica.pagar'),
    (1644, 'autorreteica.ver'), (1644, 'autorreteica.editar'), (1644, 'autorreteica.firmar'), (1644, 'autorreteica.presentar'), (1644, 'autorreteica.corregir'), (1644, 'autorreteica.pagar'),
    (1035, 'predial.consultar'),
    (1639, 'alcaldia.contribuyentes.ver'), (1639, 'alcaldia.contribuyentes.editar'), (1639, 'alcaldia.contribuyentes.gestionar'),
    (1639, 'alcaldia.establecimientos.ver'), (1639, 'alcaldia.establecimientos.cerrar'), (1639, 'alcaldia.recaudo.cargar'),
    (1639, 'alcaldia.recaudo.asignar'), (1639, 'alcaldia.recibo.intereses'),
    (1639, 'parametros.ver'), (1639, 'parametros.actividades'), (1639, 'parametros.conceptos'), (1639, 'parametros.grupos'),
    (1645, 'parametros.municipio'),
    (26, 'usuarios.ver'), (26, 'usuarios.editar'), (26, 'usuarios.estado'),
    (11, 'roles.ver'), (12, 'roles.editar'), (13, 'roles.editar'), (14, 'roles.editar'), (15, 'roles.permisos');

INSERT INTO dbo.conf_permisos (per_IdSubmodulo, per_IdRol, per_IdModulo, per_IdBoton, per_Estado)
SELECT DISTINCT s.subMod_Id, p.per_IdRol, s.subMod_IdModulo,
       CAST(CAST(s.subMod_IdModulo AS VARCHAR(10)) + CAST(s.subMod_Id AS VARCHAR(10)) AS INT), 1
  FROM dbo.conf_permisos p
  JOIN @mapa mp ON mp.boton = p.per_IdBoton
  JOIN dbo.conf_submodulo s ON s.subMod_Clave = mp.clave
  JOIN dbo.conf_rol rol ON rol.rol_Id = p.per_IdRol
 WHERE p.per_IdRol <> 1
   AND ISNULL(p.per_Estado, 1) = 1
   -- Las secciones de la Alcaldía, solo en roles de la Alcaldía.
   AND (rol.rol_Tipo = 'ALCALDIA'
        OR NOT (s.subMod_Clave LIKE 'alcaldia.%' OR s.subMod_Clave LIKE 'parametros.%'
                OR s.subMod_Clave LIKE 'usuarios.%' OR s.subMod_Clave LIKE 'roles.%'))
   AND NOT EXISTS (SELECT 1 FROM dbo.conf_permisos x
                    WHERE x.per_IdRol = p.per_IdRol AND x.per_IdSubmodulo = s.subMod_Id);

PRINT '  + permisos existentes pasados al catálogo nuevo';
GO

/* ---------------------------------------------------------------------------
   4b. Cada permiso con lo que necesita para servir (el mismo mapa que
   PermisosRol::requisitos): un rol con el botón 1639 y sin el 1640 quedaba con
   "Cerrar establecimientos" sin poder abrir el formulario del establecimiento.
   Se repite hasta que no falte nada (hay requisitos de requisitos).
   --------------------------------------------------------------------------- */
DECLARE @req TABLE (clave VARCHAR(60), requisito VARCHAR(60));
INSERT INTO @req VALUES
    ('alcaldia.contribuyentes.editar', 'alcaldia.contribuyentes.ver'),
    ('alcaldia.contribuyentes.gestionar', 'alcaldia.contribuyentes.ver'),
    ('alcaldia.establecimientos.cerrar', 'alcaldia.contribuyentes.gestionar'),
    ('alcaldia.establecimientos.cerrar', 'establecimientos.editar'),
    ('alcaldia.establecimientos.reabrir', 'alcaldia.contribuyentes.gestionar'),
    ('alcaldia.establecimientos.reabrir', 'establecimientos.ver'),
    ('alcaldia.cese', 'alcaldia.contribuyentes.gestionar'),
    ('alcaldia.cese', 'rit.ver'),
    ('alcaldia.recaudo.asignar', 'alcaldia.recaudo.cargar'),
    ('alcaldia.recibo.intereses', 'alcaldia.contribuyentes.gestionar'),
    ('parametros.actividades', 'parametros.ver'),
    ('parametros.conceptos', 'parametros.ver'),
    ('parametros.grupos', 'parametros.ver'),
    ('establecimientos.editar', 'establecimientos.ver'),
    ('usuarios.editar', 'usuarios.ver'),
    ('usuarios.estado', 'usuarios.ver'),
    ('roles.editar', 'roles.ver'),
    ('roles.permisos', 'roles.ver'),
    ('rit.editar', 'rit.ver'),
    ('rit.firmar', 'rit.ver'),
    ('rit.documentos', 'rit.ver'),
    ('ica.editar', 'ica.ver'),
    ('ica.firmar', 'ica.ver'),
    ('ica.presentar', 'ica.ver'),
    ('ica.corregir', 'ica.ver'),
    ('ica.pagar', 'ica.ver'),
    ('ica.corregir', 'ica.editar'),
    ('reteica.editar', 'reteica.ver'),
    ('reteica.firmar', 'reteica.ver'),
    ('reteica.presentar', 'reteica.ver'),
    ('reteica.corregir', 'reteica.ver'),
    ('reteica.pagar', 'reteica.ver'),
    ('reteica.corregir', 'reteica.editar'),
    ('autorreteica.editar', 'autorreteica.ver'),
    ('autorreteica.firmar', 'autorreteica.ver'),
    ('autorreteica.presentar', 'autorreteica.ver'),
    ('autorreteica.corregir', 'autorreteica.ver'),
    ('autorreteica.pagar', 'autorreteica.ver'),
    ('autorreteica.corregir', 'autorreteica.editar');

DECLARE @agregadas INT = 1;
WHILE @agregadas > 0
BEGIN
    INSERT INTO dbo.conf_permisos (per_IdSubmodulo, per_IdRol, per_IdModulo, per_IdBoton, per_Estado)
    SELECT DISTINCT sr.subMod_Id, p.per_IdRol, sr.subMod_IdModulo,
           CAST(CAST(sr.subMod_IdModulo AS VARCHAR(10)) + CAST(sr.subMod_Id AS VARCHAR(10)) AS INT), 1
      FROM dbo.conf_permisos p
      JOIN dbo.conf_submodulo s  ON s.subMod_Id = p.per_IdSubmodulo
      JOIN @req r                ON r.clave = s.subMod_Clave
      JOIN dbo.conf_submodulo sr ON sr.subMod_Clave = r.requisito
      JOIN dbo.conf_rol rol      ON rol.rol_Id = p.per_IdRol
     WHERE p.per_IdRol <> 1
       AND ISNULL(p.per_Estado, 1) = 1
       AND (rol.rol_Tipo = 'ALCALDIA' OR NOT (sr.subMod_Clave LIKE 'alcaldia.%' OR sr.subMod_Clave LIKE 'parametros.%'
            OR sr.subMod_Clave LIKE 'usuarios.%' OR sr.subMod_Clave LIKE 'roles.%'))
       AND NOT EXISTS (SELECT 1 FROM dbo.conf_permisos x
                        WHERE x.per_IdRol = p.per_IdRol AND x.per_IdSubmodulo = sr.subMod_Id);
    SET @agregadas = @@ROWCOUNT;
END
PRINT '  + requisitos de cada permiso';
GO

/* ---------------------------------------------------------------------------
   5. Registro y liberación del candado
   --------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '040_roles_y_permisos_por_accion')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('040_roles_y_permisos_por_accion',
            N'Roles con tipo (ADMINISTRADOR, ALCALDIA, CONTRIBUYENTE, EXTERNO) y catálogo de 46 permisos por acción con clave estable (conf_submodulo.subMod_Clave), que lee business/class.permisosRol.php. Los permisos que cada rol tenía pasan a sus equivalentes; los viejos no se borran.');
GO

SET NOEXEC OFF;
EXEC sp_releaseapplock @Resource = 'migracion_040_roles_y_permisos', @LockOwner = 'Session';
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRÁS

       DELETE p FROM dbo.conf_permisos p JOIN dbo.conf_submodulo s ON s.subMod_Id = p.per_IdSubmodulo WHERE s.subMod_Clave IS NOT NULL;
       DELETE FROM dbo.conf_submodulo WHERE subMod_Clave IS NOT NULL;
       DELETE FROM dbo.conf_modulo WHERE mod_Clave IS NOT NULL;
       DROP INDEX UX_conf_submodulo_clave ON dbo.conf_submodulo;
       ALTER TABLE dbo.conf_submodulo DROP COLUMN subMod_Clave, subMod_Orden;
       ALTER TABLE dbo.conf_modulo DROP COLUMN mod_Clave, mod_Orden;
       ALTER TABLE dbo.conf_rol DROP COLUMN rol_Tipo;
       DELETE FROM dbo.conf_migraciones WHERE mig_Nombre = '040_roles_y_permisos_por_accion';
   ---------------------------------------------------------------------------- */
