/* ============================================================================
   049 — Fecha de presentación en el pago manual y el funcionario del RIT
   ----------------------------------------------------------------------------
   Pedido de la Alcaldía de Paipa (Juan, 2026-10-15):
     1. En "Registrar pago manual" falta la fecha de presentación. Viene llena
        con la de la plataforma y se cambia si la declaración se presentó en
        otra fecha (el sello del banco, la ventanilla). Se guarda con el pago
        manual y es la que sale en Informes; la de la declaración no se toca.
     2. En el RIT, la casilla 31 ("Firma del Funcionario") traía la imagen de
        una firma y, debajo, el nombre de quien firmó el RIT en la plataforma:
        casi siempre el propio contribuyente. Ahora dice el nombre y el cargo
        del funcionario, que cada municipio pone en Municipio y bancos.

   QUÉ HACE
   1. pma_FechaPresentacion (DATE NULL) en ind_pagos_manuales. Los pagos
      manuales que ya existen quedan en NULL: Informes usa entonces la fecha de
      la declaración, como hasta hoy.
   2. Parámetros RIT_FUNCIONARIO_NOMBRE y RIT_FUNCIONARIO_CARGO, vacíos. Vacíos,
      la casilla 31 queda en blanco para firmar a mano. El patrón lleva
      (*UTF8) para que los 80 sean letras y no bytes (una tilde son dos).
   3. Solo en Paipa: "Juan Gabriel Suárez A" y "Director de impuestos", si
      siguen vacíos.

   RIESGO: bajo. Una columna nueva que acepta NULL y dos parámetros. No
   cambia ningún dato existente. Re-ejecutable.
   ============================================================================ */

SET NOCOUNT ON;
GO

/* 1. La fecha de presentación del pago manual ---------------------------------- */
IF OBJECT_ID('dbo.ind_pagos_manuales', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.ind_pagos_manuales', 'pma_FechaPresentacion') IS NULL
BEGIN
    ALTER TABLE dbo.ind_pagos_manuales ADD pma_FechaPresentacion DATE NULL;
    PRINT '  + ind_pagos_manuales.pma_FechaPresentacion';
END
GO

/* 2. El funcionario del RIT ---------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = 'RIT_FUNCIONARIO_NOMBRE')
    INSERT INTO dbo.conf_parametros (par_Clave, par_Valor, par_Nombre, par_Descripcion, par_Patron)
    VALUES ('RIT_FUNCIONARIO_NOMBRE', NULL,
            N'Funcionario del RIT: nombre',
            N'Nombre que sale en la casilla 31 del RIT ("Firma del Funcionario"). Vacío, la casilla queda en blanco para firmar a mano.',
            '(*UTF8)^.{1,80}$');
GO

IF NOT EXISTS (SELECT 1 FROM dbo.conf_parametros WHERE par_Clave = 'RIT_FUNCIONARIO_CARGO')
    INSERT INTO dbo.conf_parametros (par_Clave, par_Valor, par_Nombre, par_Descripcion, par_Patron)
    VALUES ('RIT_FUNCIONARIO_CARGO', NULL,
            N'Funcionario del RIT: cargo',
            N'Cargo que sale debajo del nombre en la casilla 31 del RIT, por ejemplo "Director de impuestos".',
            '(*UTF8)^.{1,80}$');
GO

/* 3. Paipa, con los datos que dio Juan ----------------------------------------- */
IF DB_NAME() = 'erpsofts_ind_comercio_paip'
BEGIN
    UPDATE dbo.conf_parametros
       SET par_Valor = N'Juan Gabriel Suárez A', par_FechaActualizacion = GETDATE()
     WHERE par_Clave = 'RIT_FUNCIONARIO_NOMBRE' AND NULLIF(LTRIM(RTRIM(par_Valor)), '') IS NULL;

    UPDATE dbo.conf_parametros
       SET par_Valor = N'Director de impuestos', par_FechaActualizacion = GETDATE()
     WHERE par_Clave = 'RIT_FUNCIONARIO_CARGO' AND NULLIF(LTRIM(RTRIM(par_Valor)), '') IS NULL;

    PRINT '  + funcionario del RIT de Paipa';
END
GO

/* Se registra solo si la columna quedó: sin la 044 (ind_pagos_manuales) no hay
   dónde ponerla, y registrada la 049 ya no volvería a correr. */
IF COL_LENGTH('dbo.ind_pagos_manuales', 'pma_FechaPresentacion') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '049_fecha_presentacion_y_funcionario_rit')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('049_fecha_presentacion_y_funcionario_rit',
            N'pma_FechaPresentacion en ind_pagos_manuales (la fecha de presentación del pago manual) y los parámetros RIT_FUNCIONARIO_NOMBRE / RIT_FUNCIONARIO_CARGO de la casilla 31 del RIT (Paipa: Juan Gabriel Suárez A, Director de impuestos).');
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRÁS
       ALTER TABLE dbo.ind_pagos_manuales DROP COLUMN pma_FechaPresentacion;
       DELETE FROM dbo.conf_parametros WHERE par_Clave IN ('RIT_FUNCIONARIO_NOMBRE', 'RIT_FUNCIONARIO_CARGO');
       DELETE FROM dbo.conf_migraciones WHERE mig_Nombre = '049_fecha_presentacion_y_funcionario_rit';
   Se pierden las fechas de presentación escritas en los pagos manuales.
   ---------------------------------------------------------------------------- */
