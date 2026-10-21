/* ============================================================================
   053 — Paipa: quitar la información de prueba
   ----------------------------------------------------------------------------
   Reunión con la Alcaldía de Paipa (2026-10-20): "borrar toda la información
   (pagos, declaraciones y demás) que se subió solo como prueba. Usuario de
   pruebas firmas e información subida en el usuario de ERP". Diego: "de
   momento solo la información" (los usuarios y los contribuyentes se quedan) y
   "ERPSOFT todas menos la de 1000 pesos".

   QUÉ SE QUITA (solo en Paipa)
   Las declaraciones de ICA, retención y autorretención de los contribuyentes
   PRUEBA FIRMAS (NIT 900900900) y SISTEMA ERPSOFT S.A.S. (NIT 901632232, que
   está dos veces en el padrón), con todo lo que cuelga de ellas: actividades,
   firmas, soportes, pagos manuales, registros de "ya pagada", eliminaciones de
   pagos y aplicaciones del recaudo. Sus pagos de PRUEBA FIRMAS fueron todos de
   la certificación de AvalPay (ambiente de pruebas, no entró plata).

   QUÉ SE QUEDA
   - La retención 2026200003 de ERPSOFT ($1.000 por PSE el 2026-10-13): esa plata
     sí entró al Banco de Bogotá.
   - Los usuarios, los contribuyentes, su RIT, establecimientos y anexos.
   - Los consecutivos: los números de las que se quitan no se vuelven a usar.

   REVERSIBLE: antes de borrar, todo se copia a tablas bkp053_* en la misma
   base (ver VUELTA ATRÁS). Todo va en una transacción: si algo falla, no se
   quita nada. Si las tablas bkp053_ ya existen, ya se corrió y no hace nada.
   ============================================================================ */

SET NOCOUNT ON;
GO

/* Como en la 029 y la 043: los DELETE tocan tablas con índices filtrados. */
SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
GO

IF DB_NAME() = 'erpsofts_ind_comercio_paip'
   AND OBJECT_ID('dbo.bkp053_ind_declaraciones_ica', 'U') IS NULL
BEGIN
    SET XACT_ABORT ON;
    BEGIN TRY
        BEGIN TRAN;

        /* Qué: los contribuyentes de prueba y sus declaraciones. */
        DECLARE @contrib TABLE (id INT PRIMARY KEY);
        INSERT @contrib (id)
        SELECT ind_Id FROM dbo.ind_contribuyentes WHERE ind_NumeroIdentificacion IN (900900900, 901632232);

        DECLARE @ica TABLE (id INT PRIMARY KEY, numero VARCHAR(30) NULL);
        INSERT @ica (id, numero)
        SELECT dec_Id, CAST(dec_NumeroDeclaracion AS VARCHAR(30))
          FROM dbo.ind_declaraciones_ica WHERE dec_IdContribuyente IN (SELECT id FROM @contrib);

        DECLARE @ret TABLE (id INT PRIMARY KEY, numero VARCHAR(30) NULL);
        INSERT @ret (id, numero)
        SELECT ret_Id, CAST(ret_NumeroDeclaracion AS VARCHAR(30))
          FROM dbo.ind_reteica
         WHERE ret_IdContribuyente IN (SELECT id FROM @contrib)
           AND ISNULL(ret_NumeroDeclaracion, 0) <> 2026200003;       -- la de $1.000 se queda

        DECLARE @aut TABLE (id INT PRIMARY KEY, numero VARCHAR(30) NULL);
        INSERT @aut (id, numero)
        SELECT aut_Id, CAST(aut_NumeroDeclaracion AS VARCHAR(30))
          FROM dbo.ind_autorreteica WHERE aut_IdContribuyente IN (SELECT id FROM @contrib);

        /* Antes de tocar nada: si los datos no son los esperados (tres registros
           de esos dos NIT, y la retención de $1.000 suya, presentada y pagada),
           no se borra nada ni se registra la migración. */
        IF (SELECT COUNT(*) FROM @contrib) <> 3
           OR NOT EXISTS (SELECT 1 FROM dbo.ind_reteica
                           WHERE ret_NumeroDeclaracion = 2026200003 AND ret_IdContribuyente IN (SELECT id FROM @contrib)
                             AND ISNULL(ret_Pagado, 0) = 1 AND ret_Estado = 2)
            THROW 50053, N'053: los datos de prueba no son los esperados (contribuyentes o la retención 2026200003). No se quitó nada.', 1;

        DECLARE @todas TABLE (modulo VARCHAR(15), id INT, PRIMARY KEY (modulo, id));
        INSERT @todas SELECT 'ica', id FROM @ica UNION ALL SELECT 'reteica', id FROM @ret UNION ALL SELECT 'autorreteica', id FROM @aut;

        /* La firma del ICA se encuentra por número o por id (como en class.declaracionesICA.php). */
        DECLARE @firmas TABLE (modulo VARCHAR(20), numero VARCHAR(30), PRIMARY KEY (modulo, numero));
        INSERT @firmas
        SELECT DISTINCT 'ICA', n FROM (SELECT numero AS n FROM @ica WHERE numero IS NOT NULL
                                       UNION SELECT CAST(id AS VARCHAR(30)) FROM @ica) x
        UNION ALL SELECT DISTINCT 'RETEICA', numero FROM @ret WHERE numero IS NOT NULL
        UNION ALL SELECT DISTINCT 'AUTORRETEICA', numero FROM @aut WHERE numero IS NOT NULL;
        -- Nunca la firma de una ICA que se queda (si su número o su id coincidiera).
        DELETE f FROM @firmas f
         WHERE f.modulo = 'ICA'
           AND f.numero IN (SELECT CAST(dec_NumeroDeclaracion AS VARCHAR(30)) FROM dbo.ind_declaraciones_ica
                             WHERE dec_NumeroDeclaracion IS NOT NULL AND dec_Id NOT IN (SELECT id FROM @ica)
                            UNION
                            SELECT CAST(dec_Id AS VARCHAR(30)) FROM dbo.ind_declaraciones_ica WHERE dec_Id NOT IN (SELECT id FROM @ica));

        /* 1. Respaldo. */
        SELECT d.* INTO dbo.bkp053_ind_declaraciones_ica FROM dbo.ind_declaraciones_ica d JOIN @ica t ON t.id = d.dec_Id;
        SELECT a.* INTO dbo.bkp053_ind_declaraciones_ica_actividades FROM dbo.ind_declaraciones_ica_actividades a JOIN @ica t ON t.id = a.dia_IdDeclaracion;
        SELECT d.* INTO dbo.bkp053_ind_reteica FROM dbo.ind_reteica d JOIN @ret t ON t.id = d.ret_Id;
        SELECT a.* INTO dbo.bkp053_ind_reteica_actividades FROM dbo.ind_reteica_actividades a JOIN @ret t ON t.id = a.rea_IdReteica;
        SELECT d.* INTO dbo.bkp053_ind_autorreteica FROM dbo.ind_autorreteica d JOIN @aut t ON t.id = d.aut_Id;
        SELECT a.* INTO dbo.bkp053_ind_autorreteica_actividades FROM dbo.ind_autorreteica_actividades a JOIN @aut t ON t.id = a.aua_IdAutorreteica;
        SELECT f.* INTO dbo.bkp053_firmas_declaraciones FROM dbo.firmas_declaraciones f
          JOIN @firmas t ON t.modulo = f.fd_Modulo AND t.numero = f.fd_NumeroDeclaracion;
        SELECT s.* INTO dbo.bkp053_ind_declaracion_soportes FROM dbo.ind_declaracion_soportes s
          JOIN @todas t ON t.modulo = s.sop_Modulo AND t.id = s.sop_IdDeclaracion;
        SELECT p.* INTO dbo.bkp053_ind_pagos_manuales FROM dbo.ind_pagos_manuales p
          JOIN @todas t ON t.modulo = p.pma_Modulo AND t.id = p.pma_IdDeclaracion;
        SELECT h.* INTO dbo.bkp053_ind_declaraciones_historicas FROM dbo.ind_declaraciones_historicas h
          JOIN @todas t ON t.modulo = h.his_Modulo AND t.id = h.his_IdDeclaracion;
        SELECT a.* INTO dbo.bkp053_ind_registros_manuales_anulados FROM dbo.ind_registros_manuales_anulados a
          JOIN @todas t ON t.modulo = a.anu_Modulo AND t.id = a.anu_IdDeclaracion;
        SELECT r.* INTO dbo.bkp053_ind_recaudo_aplicaciones FROM dbo.ind_recaudo_aplicaciones r
          JOIN @todas t ON t.modulo = r.rap_Modulo AND t.id = r.rap_IdDeclaracion;

        /* 2. Borrado: primero lo que cuelga, al final las declaraciones. */
        DELETE a FROM dbo.ind_declaraciones_ica_actividades a JOIN @ica t ON t.id = a.dia_IdDeclaracion;
        DELETE a FROM dbo.ind_reteica_actividades a JOIN @ret t ON t.id = a.rea_IdReteica;
        DELETE a FROM dbo.ind_autorreteica_actividades a JOIN @aut t ON t.id = a.aua_IdAutorreteica;
        DELETE f FROM dbo.firmas_declaraciones f JOIN @firmas t ON t.modulo = f.fd_Modulo AND t.numero = f.fd_NumeroDeclaracion;
        DELETE s FROM dbo.ind_declaracion_soportes s JOIN @todas t ON t.modulo = s.sop_Modulo AND t.id = s.sop_IdDeclaracion;
        DELETE p FROM dbo.ind_pagos_manuales p JOIN @todas t ON t.modulo = p.pma_Modulo AND t.id = p.pma_IdDeclaracion;
        DELETE h FROM dbo.ind_declaraciones_historicas h JOIN @todas t ON t.modulo = h.his_Modulo AND t.id = h.his_IdDeclaracion;
        DELETE a FROM dbo.ind_registros_manuales_anulados a JOIN @todas t ON t.modulo = a.anu_Modulo AND t.id = a.anu_IdDeclaracion;
        DELETE r FROM dbo.ind_recaudo_aplicaciones r JOIN @todas t ON t.modulo = r.rap_Modulo AND t.id = r.rap_IdDeclaracion;
        DELETE d FROM dbo.ind_declaraciones_ica d JOIN @ica t ON t.id = d.dec_Id;
        DELETE d FROM dbo.ind_reteica d JOIN @ret t ON t.id = d.ret_Id;
        DELETE d FROM dbo.ind_autorreteica d JOIN @aut t ON t.id = d.aut_Id;

        COMMIT;
        PRINT '  - información de prueba quitada (respaldo en las tablas bkp053_*)';
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK;
        THROW;
    END CATCH
    SET XACT_ABORT OFF;
END
GO

/* Se registra fuera de Paipa (no hace nada) o en Paipa solo si quedó el respaldo. */
IF (DB_NAME() <> 'erpsofts_ind_comercio_paip' OR OBJECT_ID('dbo.bkp053_ind_declaraciones_ica', 'U') IS NOT NULL)
   AND NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '053_paipa_quitar_datos_de_prueba')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('053_paipa_quitar_datos_de_prueba',
            N'Solo Paipa: quita las declaraciones de PRUEBA FIRMAS (900900900) y SISTEMA ERPSOFT (901632232) con lo que cuelga de ellas, menos la retención 2026200003 ($1.000 real). Respaldo en bkp053_*.');
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRÁS (solo Paipa), tabla por tabla, declaraciones primero y luego
   actividades, firmas, soportes, pagos manuales, históricas, anulados y
   aplicaciones:
       SET IDENTITY_INSERT dbo.<tabla> ON;
       INSERT INTO dbo.<tabla> (<todas sus columnas, con nombre>)
       SELECT <las mismas> FROM dbo.bkp053_<tabla>;
       SET IDENTITY_INSERT dbo.<tabla> OFF;
   (IDENTITY_INSERT exige la lista de columnas y admite una tabla a la vez.)
   Después: DROP TABLE de las bkp053_* y, al final,
       DELETE FROM dbo.conf_migraciones WHERE mig_Nombre = '053_paipa_quitar_datos_de_prueba';
   Cuando ya no hagan falta, solo el DROP TABLE de las bkp053_*.
   ---------------------------------------------------------------------------- */
