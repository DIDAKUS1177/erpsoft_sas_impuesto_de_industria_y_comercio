-- =============================================================
-- Migración: tabla conf_usuario  (MySQL → SQL Server)
-- Base de datos: erpsoftweb
--
-- Contexto: api.php (microservicio firmas) dejó de consultar
-- conf_usuario en MySQL y ahora la lee desde SQL Server.
-- Este script crea la tabla con el mismo esquema que tenía en
-- MySQL (BD erpsoftsas). NO confundir con la tabla existente
-- [conf_usuarios] (plural), que tiene otro esquema
-- (usu_Nombres/usu_Apellidos) y pertenece a otro módulo.
--
-- Los datos reales se importan con migrar_conf_usuario.php
-- (lee MySQL e inserta aquí). Los 2 INSERT de abajo son el
-- seed mínimo del dump original por si MySQL no está accesible.
--
-- Ejecutar completo antes de usar el módulo de firmas migrado.
-- =============================================================
USE [erpsoftweb]
GO

IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_NAME = 'conf_usuario'
)
BEGIN
    CREATE TABLE conf_usuario (
        -- Sin IDENTITY a propósito: el usu_Id se preserva tal cual
        -- viene de MySQL para que los id_usuario que envía el
        -- frontend sigan apuntando al mismo usuario.
        usu_Id              INT           NOT NULL PRIMARY KEY,
        usu_Nombre          NVARCHAR(500) NOT NULL,
        usu_Usuario         NVARCHAR(100) NOT NULL,
        usu_NumeroDocumento NVARCHAR(500) NOT NULL,
        usu_Correo          NVARCHAR(500) NOT NULL,
        usu_Password        NVARCHAR(500) NOT NULL,
        usu_Rol             INT           NOT NULL,
        usu_Estado          INT           NOT NULL DEFAULT 1
    );

    CREATE INDEX IX_conf_usuario_estado
        ON conf_usuario(usu_Estado);
END
GO

-- Seed mínimo (dump MySQL Base Datos_Limpia_22_05_2023).
-- migrar_conf_usuario.php sobreescribe/completa con los datos reales.
IF NOT EXISTS (SELECT 1 FROM conf_usuario WHERE usu_Id = 1)
    INSERT INTO conf_usuario (usu_Id, usu_Nombre, usu_Usuario, usu_NumeroDocumento, usu_Correo, usu_Password, usu_Rol, usu_Estado)
    VALUES (1, 'digitsoft', 'digitsoft', '74381687', 'soprote@digitsoft.com.co', 'adbaa8a2b601d57e8e514479d07091d47c96dc75', 1, 1);
GO

IF NOT EXISTS (SELECT 1 FROM conf_usuario WHERE usu_Id = 2)
    INSERT INTO conf_usuario (usu_Id, usu_Nombre, usu_Usuario, usu_NumeroDocumento, usu_Correo, usu_Password, usu_Rol, usu_Estado)
    VALUES (2, 'administrador', 'administrador', '11111111', 'admin@gmail.com', 'f865b53623b121fd34ee5426c792e5c33af8c227', 1, 1);
GO
