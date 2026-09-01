/* ============================================================================
   025 — El teléfono del representante legal
   ----------------------------------------------------------------------------
   Reportado por el cliente el 2026-08-31: «Número celular representante legal
   no aparece, y en el PDF coge el del principal, mas no está el del
   representante legal».

   Tenía razón, y las dos mitades del reporte son ciertas:

   1. El campo NO EXISTE. De ind_contribuyentes cuelgan ind_Cedula_representante,
      ind_Nombre_representante e ind_Email_representante, pero no hay teléfono.
      La pantalla del RIT tampoco lo pide.

   2. El formulario impreso IMPRIME OTRO. La casilla 26, dentro del bloque
      "C. REPRESENTACIÓN LEGAL", saca $d['telefono'], que es ind_Telefono — el
      del contribuyente. Así que el papel presenta el teléfono de la empresa
      como si fuera el del representante.

   Lo segundo es lo que importa: no es un campo que falte, es un dato que sale
   MAL. En una persona jurídica el contribuyente y su representante son dos
   personas distintas y sus teléfonos no tienen por qué coincidir.

   POR QUÉ UNA COLUMNA NUEVA Y NO REUSAR

   Porque son dos datos con dueños distintos. Reusar ind_Telefono sería repetir
   exactamente el error que se está corrigiendo. Es además el mismo criterio con
   que se separaron el correo del contribuyente y el del representante.

   VARCHAR(30), no numérico: en Colombia se escriben con indicativo, espacios y
   guiones («+57 312 566 6656»), y guardarlo como número los destroza — ya pasó
   con ind_Telefono, que es bigint y truncaba en el primer carácter no numérico.

   QUÉ PASA CON LAS FILAS QUE YA EXISTEN

   Quedan en NULL, y el PDF cae al teléfono del contribuyente cuando está vacío
   — que es lo que hace hoy, así que nada empeora. En cuanto alguien escriba el
   del representante, el papel dirá el correcto.

   RIESGO

   Ninguno: columna nueva que admite nulo. Nada la lee todavía hasta que se
   despliegue el código que la usa.
   ============================================================================ */

SET NOCOUNT ON;
GO


IF COL_LENGTH('dbo.ind_contribuyentes', 'ind_Telefono_representante') IS NULL
BEGIN
    ALTER TABLE dbo.ind_contribuyentes
        ADD ind_Telefono_representante VARCHAR(30) NULL;

    PRINT '  + ind_contribuyentes.ind_Telefono_representante';
END
ELSE
    PRINT '  = ind_Telefono_representante ya existia';
GO


IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '025_telefono_del_representante_legal')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('025_telefono_del_representante_legal',
            'Telefono del representante legal, que no existia. La casilla 26 del formulario impreso -dentro del bloque de Representacion Legal- venia sacando ind_Telefono, el del contribuyente, asi que el papel presentaba el telefono de la empresa como si fuera el del representante. VARCHAR(30) porque en Colombia se escribe con indicativo y espacios. Las filas existentes quedan en NULL y el PDF cae al telefono del contribuyente, que es lo que ya hacia.');
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRAS

       ALTER TABLE dbo.ind_contribuyentes DROP COLUMN ind_Telefono_representante;
       DELETE FROM dbo.conf_migraciones
        WHERE mig_Nombre = '025_telefono_del_representante_legal';
   ---------------------------------------------------------------------------- */
