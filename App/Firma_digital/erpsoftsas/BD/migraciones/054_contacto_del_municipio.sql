/* ============================================================================
   054 — Contacto del municipio (dependencia, teléfono y correo)
   ----------------------------------------------------------------------------
   Pedido de Diego (2026-11-09): poder cambiar los números de contacto desde la
   configuración del municipio, porque en los demás municipios salían los de
   Paipa. Salían escritos en el código: el pie de la pantalla de ingreso decía
   "Secretaría de Hacienda - Dirección de Impuestos, Rentas y Jurisdicción
   Coactiva" y "Soporte: 318 530 9285" en TODOS los municipios, y seis avisos
   de las pantallas mandaban al correo de Paipa.

   QUÉ HACE
   1. Tres parámetros nuevos en conf_parametros, vacíos, que cada Alcaldía
      escribe en Municipio y bancos > Contacto del municipio:
        CONTACTO_DEPENDENCIA  la dependencia que atiende (hasta 150 caracteres)
        CONTACTO_TELEFONO     uno o varios números (hasta 80)
        CONTACTO_CORREO       el correo de la dependencia (hasta 120)
      Vacíos no se muestra nada: ningún municipio hereda datos de otro.
      Se editan SIN la contraseña de edición (basta el permiso "Municipio y
      bancos"), a diferencia de los demás parámetros.
   2. Solo en Paipa (por el nombre de su base): los mismos datos PÚBLICOS que
      hoy salen en su pantalla, para que no cambie nada de lo que ve. Solo si
      siguen vacíos: lo que la Alcaldía cambie después no se pisa.

   LOS PATRONES (par_Patron) se guardan con anclas y SIN delimitadores, como el
   resto. (*UTF8) hace que los 150 sean letras y no bytes (una tilde son dos).
   La dependencia no admite < ni > ni caracteres de control; el teléfono solo
   dígitos, espacios y + ( ) . , / - con al menos un dígito; el correo, una
   dirección con arroba y dominio. Son los mismos de business/class.contacto.php,
   que además los vuelve a comprobar al leer.

   RIESGO: bajo. Tres filas nuevas y, en Paipa, tres valores. No cambia ningún
   dato existente. Re-ejecutable. Aplicar por número (--aplicar 054): la 042
   está pendiente a propósito en Paipa.
   ============================================================================ */

SET NOCOUNT ON;
SET QUOTED_IDENTIFIER ON;
GO

/* 1. Los tres parámetros, vacíos ----------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = 'CONTACTO_DEPENDENCIA')
    INSERT INTO dbo.conf_parametros (par_Clave, par_Valor, par_Nombre, par_Descripcion, par_Patron)
    VALUES ('CONTACTO_DEPENDENCIA', NULL,
            N'Contacto: dependencia que atiende',
            N'Nombre de la dependencia que atiende a los contribuyentes, por ejemplo Secretaría de Hacienda - Dirección de Impuestos. Sale al pie de la pantalla de ingreso. Vacío, esa línea no se muestra.',
            '(*UTF8)^[^<>\x00-\x1F\x7F]{1,150}$');
GO

IF NOT EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = 'CONTACTO_TELEFONO')
    INSERT INTO dbo.conf_parametros (par_Clave, par_Valor, par_Nombre, par_Descripcion, par_Patron)
    VALUES ('CONTACTO_TELEFONO', NULL,
            N'Contacto: teléfono',
            N'Uno o varios números de teléfono de contacto, por ejemplo 310 123 4567 o 8 785 0000, 310 123 4567. Solo números, espacios y + ( ) . , / -. Sale al pie de la pantalla de ingreso como Soporte. Vacío, no se muestra.',
            '^(?=.*[0-9])[0-9 +().,/-]{1,80}$');
GO

IF NOT EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = 'CONTACTO_CORREO')
    INSERT INTO dbo.conf_parametros (par_Clave, par_Valor, par_Nombre, par_Descripcion, par_Patron)
    VALUES ('CONTACTO_CORREO', NULL,
            N'Contacto: correo',
            N'Correo de la dependencia que atiende, por ejemplo impuestos@municipio.gov.co. Aparece en los avisos de las pantallas que mandan al contribuyente a la Alcaldía (crear un establecimiento, enviar los soportes de un pago). Vacío, esos avisos no mencionan ningún correo.',
            '^(?=.{1,120}$)[A-Za-z0-9_%+-]+(\.[A-Za-z0-9_%+-]+)*@[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,}$');
GO

/* 2. Paipa, con lo que su pantalla ya mostraba (datos públicos) ------------------ */
IF DB_NAME() = 'erpsofts_ind_comercio_paip'
BEGIN
    UPDATE dbo.conf_parametros
       SET par_Valor = N'Secretaría de Hacienda - Dirección de Impuestos, Rentas y Jurisdicción Coactiva',
           par_FechaActualizacion = GETDATE()
     WHERE par_Clave = 'CONTACTO_DEPENDENCIA' AND NULLIF(LTRIM(RTRIM(par_Valor)), '') IS NULL;

    UPDATE dbo.conf_parametros
       SET par_Valor = N'318 530 9285', par_FechaActualizacion = GETDATE()
     WHERE par_Clave = 'CONTACTO_TELEFONO' AND NULLIF(LTRIM(RTRIM(par_Valor)), '') IS NULL;

    UPDATE dbo.conf_parametros
       SET par_Valor = N'impuestos@paipa-boyaca.gov.co', par_FechaActualizacion = GETDATE()
     WHERE par_Clave = 'CONTACTO_CORREO' AND NULLIF(LTRIM(RTRIM(par_Valor)), '') IS NULL;

    PRINT '  + contacto de Paipa';
END
GO

/* Se registra solo si las tres filas quedaron: registrada la 054 ya no volvería
   a correr. */
IF (SELECT COUNT(*) FROM dbo.conf_parametros
     WHERE par_Clave IN ('CONTACTO_DEPENDENCIA', 'CONTACTO_TELEFONO', 'CONTACTO_CORREO')) = 3
   AND NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '054_contacto_del_municipio')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('054_contacto_del_municipio',
            N'Parámetros CONTACTO_DEPENDENCIA, CONTACTO_TELEFONO y CONTACTO_CORREO (vacíos; se editan en Municipio y bancos sin la contraseña de edición) para el pie del ingreso y los avisos de las pantallas. Paipa: sus datos públicos de siempre.');
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRÁS
       DELETE FROM dbo.conf_parametros
        WHERE par_Clave IN ('CONTACTO_DEPENDENCIA', 'CONTACTO_TELEFONO', 'CONTACTO_CORREO');
       DELETE FROM dbo.conf_migraciones WHERE mig_Nombre = '054_contacto_del_municipio';
   Se pierden los tres datos que la Alcaldía haya escrito. El código tolera su
   ausencia: el pie del ingreso y los avisos salen sin esas líneas.
   ---------------------------------------------------------------------------- */
