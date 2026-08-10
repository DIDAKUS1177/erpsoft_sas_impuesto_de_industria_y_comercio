-- Migración PSE ICA — correr UNA VEZ contra la base de datos de PRODUCCIÓN
-- (la misma que usa la app real en Plesk, no la copia local de Docker).
--
-- Es idempotente: si ya se corrió antes, no hace nada ni da error.
--
-- Cómo correrla: Plesk > paipa.erpsoftsas.com > Bases de datos > (la BD de
-- ICA) > phpMyAdmin/Query o el cliente SQL Server que uses, pegar y ejecutar
-- este script completo.

IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('ind_declaraciones_ica') AND name = 'dec_PSE_RequestId'
)
BEGIN
    ALTER TABLE ind_declaraciones_ica ADD dec_PSE_RequestId BIGINT NULL;
END
