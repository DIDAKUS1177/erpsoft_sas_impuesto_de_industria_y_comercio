/* ============================================================================
   051 — La cuenta contable del pago manual
   ----------------------------------------------------------------------------
   Pedido de la Alcaldía de Paipa (2026-10-19): al registrar un pago manual,
   después del banco se elige la CUENTA CONTABLE con la que se hace el ingreso,
   como en el sistema anterior ("Registrar Pago": Banco → Cuenta contable). Un
   banco puede tener varias (Banco de Bogotá tiene cuatro en Paipa) y cambian
   por año.

   El catálogo es ind_bancos_cuenta, la tabla que creó Javier en la base de
   Paipa el 2026-10-19 (Id, Id_banco, ano, cuenta, creador, fecha_creador) con
   las cuentas de 2026. Aquí se crea IGUAL donde no exista, para que el código
   sea el mismo en los cuatro municipios; la de Paipa no se toca salvo por la
   columna nueva de abajo.

   QUÉ HACE
   1. ind_bancos_cuenta, si no existe (mismas columnas, llave y FK que la de
      Paipa).
   2. descripcion (VARCHAR(100) NULL) en ind_bancos_cuenta: para distinguir
      las cuentas de un mismo banco en la lista ("Industria y comercio",
      "Predial"...), como la columna NOMBRE del sistema anterior.
   3. pma_IdCuentaContable (INT NULL) y pma_CuentaContable (VARCHAR(70) NULL)
      en ind_pagos_manuales: la cuenta elegida y su número tal como estaba ese
      día (si después se quita del catálogo, el pago la conserva).

   RIESGO: bajo. Una tabla nueva donde no existe y tres columnas que aceptan
   NULL. No cambia datos. Re-ejecutable.
   ============================================================================ */

SET NOCOUNT ON;
GO

/* 1. El catálogo --------------------------------------------------------------- */
IF OBJECT_ID('dbo.ind_bancos_cuenta', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.ind_bancos_cuenta (
        Id             INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_ind_bancos_cuenta PRIMARY KEY,
        Id_banco       INT          NOT NULL
                       CONSTRAINT FK_ind_ban_cuenta_ind_bancos REFERENCES dbo.ind_bancos (ban_Id),
        ano            INT          NOT NULL,
        cuenta         VARCHAR(70)  NOT NULL,
        creador        VARCHAR(20)  NOT NULL,
        fecha_creador  DATETIME     NULL
    );
    PRINT '  + ind_bancos_cuenta';
END
GO

/* 2. La descripción ------------------------------------------------------------ */
IF COL_LENGTH('dbo.ind_bancos_cuenta', 'descripcion') IS NULL
BEGIN
    ALTER TABLE dbo.ind_bancos_cuenta ADD descripcion VARCHAR(100) NULL;
    PRINT '  + ind_bancos_cuenta.descripcion';
END
GO

/* 3. La cuenta del pago manual ------------------------------------------------- */
IF OBJECT_ID('dbo.ind_pagos_manuales', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.ind_pagos_manuales', 'pma_IdCuentaContable') IS NULL
BEGIN
    ALTER TABLE dbo.ind_pagos_manuales ADD pma_IdCuentaContable INT NULL, pma_CuentaContable VARCHAR(70) NULL;
    PRINT '  + ind_pagos_manuales.pma_IdCuentaContable, pma_CuentaContable';
END
GO

/* Se registra solo si quedó todo: sin la 044 no hay ind_pagos_manuales. */
IF COL_LENGTH('dbo.ind_pagos_manuales', 'pma_IdCuentaContable') IS NOT NULL
   AND COL_LENGTH('dbo.ind_bancos_cuenta', 'descripcion') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '051_cuentas_contables_del_pago')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('051_cuentas_contables_del_pago',
            N'Cuenta contable del pago manual: catálogo ind_bancos_cuenta (por banco y año, con descripción; el de Paipa lo creó Javier) y pma_IdCuentaContable / pma_CuentaContable en ind_pagos_manuales.');
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRÁS
       ALTER TABLE dbo.ind_pagos_manuales DROP COLUMN pma_IdCuentaContable, pma_CuentaContable;
       ALTER TABLE dbo.ind_bancos_cuenta DROP COLUMN descripcion;
       -- Solo fuera de Paipa (allá la tabla es de Javier):
       -- DROP TABLE dbo.ind_bancos_cuenta;
       DELETE FROM dbo.conf_migraciones WHERE mig_Nombre = '051_cuentas_contables_del_pago';
   ---------------------------------------------------------------------------- */
