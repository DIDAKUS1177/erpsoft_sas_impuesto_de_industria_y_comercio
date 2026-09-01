/* ============================================================================
   028 — El teléfono del lugar donde se ejerce la actividad
   ----------------------------------------------------------------------------
   Reportado por el cliente el 2026-09-01: «20 y 11, teléfono salen repetido.
   Mira si en el formulario sale la división de estos dos números de celular».

   SI, EL FORMULARIO LOS SEPARA, Y A PROPOSITO

   El formulario impreso tiene dos casillas de telefono en dos bloques
   distintos:

       casilla 11 — bloque "B. DATOS DEL CONTRIBUYENTE"
                    (va con Ciudad, Departamento y Regimen Tributario)
       casilla 20 — bloque del LUGAR DONDE SE EJERCE LA ACTIVIDAD
                    (va con Fecha de inicio, Direccion y Correo)

   Son datos distintos: el de la persona y el del local. Una empresa puede
   atender en un telefono y tener el negocio en otro.

   POR QUE SALIA REPETIDO

   Porque ind_establecimientos NO TIENE columna de telefono. Comprobado el
   2026-09-01: tiene est_Direccion y est_Correo, pero ningun telefono. Asi que
   el generador del PDF imprimia ind_Telefono en las dos casillas, no por
   descuido sino porque no habia otra cosa que imprimir.

   El arreglo de verdad es que exista el dato. Esta migracion crea la columna;
   el formulario impreso la usa en cuanto tiene valor y, mientras este vacia,
   cae al telefono del contribuyente -que es lo que hace hoy, asi que nada
   empeora-.

   VARCHAR(30), NO NUMERICO

   Mismo motivo que la migracion 025 con el telefono del representante: en
   Colombia se escriben con indicativo, espacios y guiones («+57 8 785 0000»),
   y guardarlo como numero los destroza. ind_Telefono es bigint y ya trunca en
   el primer caracter no numerico; no se repite el error.

   DONDE SE CAPTURA

   En el modulo de Establecimientos, que es donde se editan los demas datos del
   local. La pantalla del RIT no gestiona establecimientos -comprobado: no
   captura ningun campo est_ salvo los del cese-.

   RIESGO

   Ninguno: columna nueva que admite nulo, y nadie la lee mas que el formulario
   impreso, con respaldo.
   ============================================================================ */

SET NOCOUNT ON;
GO

SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
GO


IF COL_LENGTH('dbo.ind_establecimientos', 'est_Telefono') IS NULL
BEGIN
    ALTER TABLE dbo.ind_establecimientos
        ADD est_Telefono VARCHAR(30) NULL;

    PRINT '  + ind_establecimientos.est_Telefono';
END
ELSE
    PRINT '  = est_Telefono ya existia';
GO


IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '028_telefono_del_establecimiento')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('028_telefono_del_establecimiento',
            'Telefono del establecimiento. El formulario impreso tiene dos casillas de telefono en bloques distintos -la 11 es del contribuyente y la 20 del lugar donde se ejerce la actividad- pero ind_establecimientos no tenia columna de telefono, asi que las dos imprimian ind_Telefono y salian repetidas. VARCHAR(30) porque en Colombia se escribe con indicativo y espacios. Mientras este vacia, la casilla 20 cae al telefono del contribuyente.');
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRAS

       ALTER TABLE dbo.ind_establecimientos DROP COLUMN est_Telefono;
       DELETE FROM dbo.conf_migraciones
        WHERE mig_Nombre = '028_telefono_del_establecimiento';
   ---------------------------------------------------------------------------- */
