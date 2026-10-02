/* ============================================================================
   041 — Las cuentas del recibo se manejan en "Cuentas de los bancos"
   ----------------------------------------------------------------------------
   Pedido del cliente (2026-09-30): las cuentas deben ser dinámicas; si un día
   cambia una, la Alcaldía la cambia ella misma desde Municipio y bancos, sin
   pedírsela a nadie.

   ANTES

   La 039 dejó los bancos del recibo ("Páguese en: BANCOS: ...") en un texto
   libre, el parámetro RECIBO_BANCOS, y abajo, en la misma pantalla, estaba la
   tabla "Cuentas de los bancos" (ind_bancos, cuenta recaudadora por banco),
   que no hacía nada en el recibo: dos lugares para lo mismo, y el que la
   Alcaldía esperaba usar era el que no servía.

   EL CAMBIO

   El recibo toma los bancos de ind_bancos (class.bancosRecibo.php): cada banco
   activo con cuenta recaudadora, en orden alfabético. Guardar una cuenta ya no
   pide la contraseña de edición (sí el permiso "Municipio y bancos"). Esta
   migración:

   1. Agrega ind_bancos.ban_ActualizadoPor: quién cambió las cuentas por última
      vez, que la pantalla muestra junto a la fecha. Sin la contraseña, es lo
      que deja ver un cambio que nadie esperaba.
   2. Corrige la descripción de "Municipio y bancos" en el panel de Roles (decía
      que las cuentas piden la contraseña).
   3. Si RECIBO_BANCOS es todavía la lista que dejó la 039 (la de Jennifer, solo
      en la base de Paipa), pasa esas siete cuentas a la cuenta recaudadora de
      cada banco, agrega CONFIAR COOPERATIVA FINANCIERA (no estaba en el
      catálogo) y apaga el parámetro. Todo o nada, en una transacción: si algún
      banco no está, está inactivo o ya tiene OTRA cuenta, no toca nada y la
      migración falla con un error visible y queda sin registrar (el recibo
      sigue con el parámetro).
      CONFIAR va con código y código Asobancaria "-": no se conocen, y el de
      Asobancaria es con el que Recaudo reconoce el archivo de cada banco, así
      que uno inventado podría atribuirle a Confiar el archivo de otro. Los dos
      son únicos en la tabla (UX_bancos_codigo, UX_bancos_asobancaria).
   4. Donde RECIBO_BANCOS está vacío (Guateque y los demás), lo apaga. Si tiene
      otra lista que no es la de la 039, lo deja: el recibo lo sigue usando
      mientras ningún banco tenga cuenta en la tabla.

   El parámetro se APAGA (par_Estado = 0), no se borra: deja de verse en la
   pantalla y de leerse, y su texto queda por si hay que volver atrás.

   ORDEN AL DESPLEGAR: primero el código (Pull) y después esta migración. Con el
   código anterior, un recibo generado después de la 041 saldría sin bancos.

   RIESGO

   Bajo: una columna nueva, una descripción, una fila en ind_bancos, siete
   UPDATE en una transacción y un parámetro apagado. Re-ejecutable.
   ============================================================================ */

SET NOCOUNT ON;
GO

/* 1. Quién cambió las cuentas por última vez ------------------------------- */
IF COL_LENGTH('dbo.ind_bancos', 'ban_ActualizadoPor') IS NULL
    ALTER TABLE dbo.ind_bancos ADD ban_ActualizadoPor INT NULL;
GO

/* 2. Descripción del permiso en el panel de Roles -------------------------- */
IF COL_LENGTH('dbo.conf_submodulo', 'subMod_Clave') IS NOT NULL
    UPDATE dbo.conf_submodulo
       SET subMod_Descripcion = N'Parámetros del municipio (piden la contraseña de edición) y cuentas de los bancos que salen en el recibo de pago.'
     WHERE subMod_Clave = 'parametros.municipio';
GO

/* 3 y 4. Las cuentas de RECIBO_BANCOS pasan a la tabla --------------------- */
SET XACT_ABORT ON;

DECLARE @lista039 NVARCHAR(500) = N'BANCO AGRARIO (1511-3-00905-0), BANCO POPULAR (261-11436-7), BANCO DE BOGOTÁ (676026834), DAVIVIENDA (41600003686-1), BANCOLOMBIA (038-85109275), BBVA (001380660100000035), CONFIAR (450008701)';

IF EXISTS (SELECT 1 FROM dbo.conf_parametros
            WHERE par_Clave = 'RECIBO_BANCOS' AND ISNULL(par_Estado, 1) = 1 AND par_Valor = @lista039)
BEGIN
    /* Código del banco en ind_bancos + su nombre, para no poner una cuenta en
       el banco equivocado si algún día una base trae otros códigos. */
    DECLARE @cuentas TABLE (codigo VARCHAR(6), patron VARCHAR(40), cuenta VARCHAR(30));
    INSERT INTO @cuentas VALUES
        ('040', 'BANCO AGRARIO%',   '1511-3-00905-0'),
        ('002', 'BANCO POPULAR%',   '261-11436-7'),
        ('001', 'BANCO DE BOGOT%',  '676026834'),
        ('012', '%DAVIVIENDA%',     '41600003686-1'),
        ('007', 'BANCOLOMBIA%',     '038-85109275'),
        ('013', 'BBVA%',            '001380660100000035');

    /* Bancos que se pueden llenar: existen, activos, y sin cuenta o con esa misma. */
    DECLARE @listos INT = (
        SELECT COUNT(*)
          FROM @cuentas c
          JOIN dbo.ind_bancos b ON b.ban_Codigo = c.codigo AND b.ban_Nombre LIKE c.patron
         WHERE b.ban_Activo = 1
           AND (NULLIF(LTRIM(RTRIM(b.ban_CuentaRecaudadora)), '') IS NULL
                OR LTRIM(RTRIM(b.ban_CuentaRecaudadora)) = c.cuenta));

    /* CONFIAR: o no existe y el código "-" está libre, o ya existe, activo,
       sin cuenta o con esa misma (una corrida anterior). */
    DECLARE @hayConfiar INT = (SELECT COUNT(*) FROM dbo.ind_bancos WHERE ban_Nombre LIKE '%CONFIAR%');
    DECLARE @confiarListo BIT = CASE
        WHEN @hayConfiar = 0
             AND NOT EXISTS (SELECT 1 FROM dbo.ind_bancos WHERE ban_Codigo = '-' OR ban_Asobancaria = '-') THEN 1
        WHEN @hayConfiar = 1
             AND EXISTS (SELECT 1 FROM dbo.ind_bancos
                          WHERE ban_Nombre LIKE '%CONFIAR%' AND ban_Activo = 1
                            AND (NULLIF(LTRIM(RTRIM(ban_CuentaRecaudadora)), '') IS NULL
                                 OR LTRIM(RTRIM(ban_CuentaRecaudadora)) = '450008701')) THEN 1
        ELSE 0 END;

    IF @listos = (SELECT COUNT(*) FROM @cuentas) AND @confiarListo = 1
    BEGIN
        BEGIN TRANSACTION;

        IF @hayConfiar = 0
            INSERT INTO dbo.ind_bancos (ban_Codigo, ban_Nombre, ban_Asobancaria)
            VALUES ('-', 'CONFIAR COOPERATIVA FINANCIERA', '-');

        UPDATE b
           SET ban_CuentaRecaudadora = c.cuenta,
               ban_FechaActualizacion = SYSDATETIME()
          FROM dbo.ind_bancos b
          JOIN @cuentas c ON b.ban_Codigo = c.codigo AND b.ban_Nombre LIKE c.patron
         WHERE NULLIF(LTRIM(RTRIM(b.ban_CuentaRecaudadora)), '') IS NULL;

        UPDATE dbo.ind_bancos
           SET ban_CuentaRecaudadora = '450008701', ban_FechaActualizacion = SYSDATETIME()
         WHERE ban_Nombre LIKE '%CONFIAR%'
           AND NULLIF(LTRIM(RTRIM(ban_CuentaRecaudadora)), '') IS NULL;

        UPDATE dbo.conf_parametros
           SET par_Estado = 0, par_FechaActualizacion = SYSDATETIME()
         WHERE par_Clave = 'RECIBO_BANCOS';

        COMMIT TRANSACTION;
        PRINT '  = las 7 cuentas de RECIBO_BANCOS pasaron a Cuentas de los bancos; el parámetro quedó apagado';
    END
    ELSE
        PRINT '  ! no se pudieron pasar las cuentas: se revisa abajo';
END
ELSE
BEGIN
    UPDATE dbo.conf_parametros
       SET par_Estado = 0, par_FechaActualizacion = SYSDATETIME()
     WHERE par_Clave = 'RECIBO_BANCOS'
       AND ISNULL(par_Estado, 1) = 1
       AND NULLIF(LTRIM(RTRIM(par_Valor)), '') IS NULL;
END

SET XACT_ABORT OFF;
GO

/* Registro: solo si no quedó pendiente la lista de la 039 ------------------- */
IF EXISTS (SELECT 1 FROM dbo.conf_parametros
            WHERE par_Clave = 'RECIBO_BANCOS' AND ISNULL(par_Estado, 1) = 1
              AND par_Valor = N'BANCO AGRARIO (1511-3-00905-0), BANCO POPULAR (261-11436-7), BANCO DE BOGOTÁ (676026834), DAVIVIENDA (41600003686-1), BANCOLOMBIA (038-85109275), BBVA (001380660100000035), CONFIAR (450008701)')
    RAISERROR('041: no se pasaron las cuentas de RECIBO_BANCOS a Cuentas de los bancos: algún banco no está, está inactivo o ya tiene otra cuenta recaudadora (o el código "-" lo usa otro banco). No se cambió ninguna cuenta; revise Municipio y bancos y vuelva a correrla.', 16, 1);
ELSE IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '041_cuentas_de_bancos_en_el_recibo')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('041_cuentas_de_bancos_en_el_recibo',
            N'El recibo de pago toma los bancos de "Cuentas de los bancos" (ind_bancos, cuenta recaudadora); guardarlas ya no pide la contraseña. Columna ban_ActualizadoPor. Descripción de "Municipio y bancos" en Roles. En Paipa: CONFIAR agregado (códigos "-") y las 7 cuentas de RECIBO_BANCOS (039) pasadas a la tabla; el parámetro quedó apagado (par_Estado = 0).');
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRÁS (el recibo vuelve a leer el texto de la 039)

       UPDATE dbo.conf_parametros SET par_Estado = 1 WHERE par_Clave = 'RECIBO_BANCOS';
       UPDATE dbo.ind_bancos SET ban_CuentaRecaudadora = NULL
        WHERE ban_Codigo IN ('040','002','001','012','007','013','-');
       DELETE FROM dbo.ind_bancos WHERE ban_Codigo = '-' AND ban_Nombre LIKE '%CONFIAR%';
       DELETE FROM dbo.conf_migraciones WHERE mig_Nombre = '041_cuentas_de_bancos_en_el_recibo';
       -- (la columna ban_ActualizadoPor puede quedarse; el código anterior no la lee)

   (y el código anterior de reciboPago.php, que leía RECIBO_BANCOS). Ojo: el
   UPDATE de las cuentas borra también las que la Alcaldía haya cambiado.
   ---------------------------------------------------------------------------- */
