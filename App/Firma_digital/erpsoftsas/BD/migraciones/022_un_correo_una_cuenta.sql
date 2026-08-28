/* ============================================================================
   022 — Un correo, una cuenta
   ----------------------------------------------------------------------------
   El cliente lo pidio el 2026-08-26 con estas palabras: "no permitir repeticion
   de correos electronicos de base de datos". Lo de "de base de datos" se toma
   literal: la comprobacion en PHP ya existe, pero una comprobacion en PHP no es
   una garantia — solo cubre los caminos que se acordaron de llamarla.

   Y no es hipotetico. El 2026-08-26 habia DOS cuentas compartiendo
   cristianmd99@gmail.com (usu 12 'cristian' y usu 16 'erp', dos personas
   distintas: una natural y una juridica), pese a que el codigo de alta ya
   comprobaba el correo desde hace tiempo. Entraron igual.

   POR QUE IMPORTA EN ESTA TABLA Y NO EN OTRA

   Porque usu_Correo es una LLAVE DE ENTRADA. business/controller/class.login.php
   acepta indistintamente el usuario o el correo:

       if (filter_var($this->_usuario, FILTER_VALIDATE_EMAIL)) {
           $this->_objUsuario->set_usu_Correo($this->_usuario);
       } else {
           $this->_objUsuario->set_usu_Usuario($this->_usuario);
       }

   Con el correo repetido, "entrar con el correo" busca por correo Y clave: hoy
   resuelve bien porque las dos cuentas tienen claves distintas. El dia que dos
   cuentas repetidas compartan tambien la clave -normal entre cuentas de prueba
   de una misma persona-, en cual de las dos entra deja de estar definido: es la
   que devuelva primero el motor. Nadie deberia poder acabar dentro de la cuenta
   equivocada por un empate.

   POR QUE FILTRADO

   El indice ignora los correos vacios o nulos. Una cuenta sin correo no esta
   repetida con otra cuenta sin correo, y sin el filtro la segunda no se podria
   crear. Hoy no hay ninguna asi, pero el esquema permite el nulo y el indice no
   debe decidir eso por su cuenta.

   NO SE PONE EL MISMO INDICE EN ind_contribuyentes

   A proposito. ind_Email es el correo de NOTIFICACION del RIT, no una llave de
   entrada, y ahi la unicidad no es por fila sino por CONTRIBUYENTE: en esta base
   hay un documento con dos registros de contribuyente -la misma persona inscrita
   dos veces-, y esa persona debe poder tener el mismo correo en los dos. Un
   indice unico no sabe distinguir eso; la comprobacion de
   class.contribuyentes.php si, porque compara por documento. Esa regla ademas
   cruza las DOS tablas, que es algo que un indice no puede hacer.

   ANTES DE APLICARLO

   Si quedan duplicados, la creacion del indice falla con un mensaje claro y no
   se aplica nada. Para verlos:

       SELECT LOWER(LTRIM(RTRIM(usu_Correo))) correo, COUNT(*) veces
         FROM conf_usuarios
        WHERE usu_Correo IS NOT NULL AND LTRIM(RTRIM(usu_Correo)) <> ''
        GROUP BY LOWER(LTRIM(RTRIM(usu_Correo)))
       HAVING COUNT(*) > 1;

   RIESGO

   Un INSERT o UPDATE que intente repetir un correo sera rechazado por el motor.
   Los tres caminos vivos que escriben usu_Correo ya avisan antes con un mensaje
   propio (_agregarUsuario y _editarUsuario en class.usuarios.php, y la
   sincronizacion de class.contribuyentes.php), asi que el indice es la red de
   abajo, no la puerta. Si algun dia salta, es que aparecio un cuarto camino sin
   comprobar — que es justo lo que se quiere descubrir.
   ============================================================================ */

SET NOCOUNT ON;
GO


/* ---------------------------------------------------------------------------
   Guarda: no se crea el indice sobre datos que aun lo violan
   --------------------------------------------------------------------------- */
IF EXISTS (
    SELECT 1 FROM dbo.conf_usuarios
     WHERE usu_Correo IS NOT NULL AND LTRIM(RTRIM(usu_Correo)) <> ''
     GROUP BY LOWER(LTRIM(RTRIM(usu_Correo)))
    HAVING COUNT(*) > 1)
BEGIN
    RAISERROR('Hay correos repetidos en conf_usuarios: resuelvalos antes de aplicar la migracion 022.', 16, 1);
    SET NOEXEC ON;
END
GO


IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UQ_usuario_correo')
BEGIN
    CREATE UNIQUE INDEX UQ_usuario_correo
        ON dbo.conf_usuarios (usu_Correo)
     WHERE usu_Correo IS NOT NULL AND usu_Correo <> '';

    PRINT '  + UQ_usuario_correo';
END
ELSE
    PRINT '  = UQ_usuario_correo ya existia';
GO


IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '022_un_correo_una_cuenta')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('022_un_correo_una_cuenta',
            'Indice unico filtrado sobre conf_usuarios.usu_Correo. El correo es una llave de entrada -se puede iniciar sesion con el en vez del usuario-, asi que dos cuentas con el mismo correo hacen indeterminado a cual se entra si ademas comparten clave. Se aplica solo a correos no vacios. No se pone el equivalente en ind_contribuyentes: alli la unicidad es por documento y cruza dos tablas, algo que un indice no puede expresar; esa la hace class.contribuyentes.php.');
GO

SET NOEXEC OFF;
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRAS

       DROP INDEX UQ_usuario_correo ON dbo.conf_usuarios;
       DELETE FROM dbo.conf_migraciones WHERE mig_Nombre = '022_un_correo_una_cuenta';
   ---------------------------------------------------------------------------- */
