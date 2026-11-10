/* Esquema base del sistema de ICA: ÍNDICES y LLAVES FORÁNEAS. Cada objeto se crea solo si no existe. */
SET NOCOUNT ON;
SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id=OBJECT_ID(N'dbo.codigos_verificacion') AND name=N'IX_codigos_verificacion_usuario')
    CREATE NONCLUSTERED INDEX [IX_codigos_verificacion_usuario] ON dbo.[codigos_verificacion] ([codigo_IdUsuario],[codigo_IdEstablecimiento],[codigo_Usado]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id=OBJECT_ID(N'dbo.conf_parametros') AND name=N'UX_conf_parametros_clave')
    CREATE UNIQUE NONCLUSTERED INDEX [UX_conf_parametros_clave] ON dbo.[conf_parametros] ([par_Clave]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id=OBJECT_ID(N'dbo.conf_submodulo') AND name=N'UX_conf_submodulo_clave')
    CREATE UNIQUE NONCLUSTERED INDEX [UX_conf_submodulo_clave] ON dbo.[conf_submodulo] ([subMod_Clave]) WHERE ([subMod_Clave] IS NOT NULL);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id=OBJECT_ID(N'dbo.conf_usuarios') AND name=N'UQ_usuario_correo')
    CREATE UNIQUE NONCLUSTERED INDEX [UQ_usuario_correo] ON dbo.[conf_usuarios] ([usu_Correo]) WHERE ([usu_Correo] IS NOT NULL AND [usu_Correo]<>'');
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id=OBJECT_ID(N'dbo.firmas') AND name=N'IX_firmas_establecimiento')
    CREATE NONCLUSTERED INDEX [IX_firmas_establecimiento] ON dbo.[firmas] ([firma_IdEstablecimiento],[firma_Estado]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id=OBJECT_ID(N'dbo.firmas_declaraciones') AND name=N'IX_firmas_declaraciones_numero')
    CREATE NONCLUSTERED INDEX [IX_firmas_declaraciones_numero] ON dbo.[firmas_declaraciones] ([fd_NumeroDeclaracion]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id=OBJECT_ID(N'dbo.ind_actividad_contribuyente') AND name=N'UX_actividad_contribuyente_sin_anio')
    CREATE UNIQUE NONCLUSTERED INDEX [UX_actividad_contribuyente_sin_anio] ON dbo.[ind_actividad_contribuyente] ([atc_IdContribuyente],[atc_IdCodigoActividad]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id=OBJECT_ID(N'dbo.ind_archivos_asobancaria') AND name=N'IX_archivos_asobancaria_hash')
    CREATE NONCLUSTERED INDEX [IX_archivos_asobancaria_hash] ON dbo.[ind_archivos_asobancaria] ([arc_Hash]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id=OBJECT_ID(N'dbo.ind_autorreteica') AND name=N'IX_autorreteica_contribuyente')
    CREATE NONCLUSTERED INDEX [IX_autorreteica_contribuyente] ON dbo.[ind_autorreteica] ([aut_IdContribuyente],[aut_Anio],[aut_Periodo]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id=OBJECT_ID(N'dbo.ind_autorreteica') AND name=N'UQ_autorreteica_numero')
    CREATE UNIQUE NONCLUSTERED INDEX [UQ_autorreteica_numero] ON dbo.[ind_autorreteica] ([aut_NumeroDeclaracion]) WHERE ([aut_NumeroDeclaracion] IS NOT NULL);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id=OBJECT_ID(N'dbo.ind_autorreteica_actividades') AND name=N'IX_aua_autorreteica')
    CREATE NONCLUSTERED INDEX [IX_aua_autorreteica] ON dbo.[ind_autorreteica_actividades] ([aua_IdAutorreteica]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id=OBJECT_ID(N'dbo.ind_bancos') AND name=N'UX_bancos_asobancaria')
    CREATE UNIQUE NONCLUSTERED INDEX [UX_bancos_asobancaria] ON dbo.[ind_bancos] ([ban_Asobancaria]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id=OBJECT_ID(N'dbo.ind_bancos') AND name=N'UX_bancos_codigo')
    CREATE UNIQUE NONCLUSTERED INDEX [UX_bancos_codigo] ON dbo.[ind_bancos] ([ban_Codigo]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id=OBJECT_ID(N'dbo.ind_conceptos') AND name=N'IX_ind_conceptos_anio_codigo')
    CREATE UNIQUE NONCLUSTERED INDEX [IX_ind_conceptos_anio_codigo] ON dbo.[ind_conceptos] ([con_Anio],[con_Codigo]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id=OBJECT_ID(N'dbo.ind_declaracion_soportes') AND name=N'IX_sop_declaracion')
    CREATE NONCLUSTERED INDEX [IX_sop_declaracion] ON dbo.[ind_declaracion_soportes] ([sop_Modulo],[sop_IdDeclaracion]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id=OBJECT_ID(N'dbo.ind_declaraciones_historicas') AND name=N'UX_his_declaracion')
    CREATE UNIQUE NONCLUSTERED INDEX [UX_his_declaracion] ON dbo.[ind_declaraciones_historicas] ([his_Modulo],[his_IdDeclaracion]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id=OBJECT_ID(N'dbo.ind_declaraciones_ica') AND name=N'IX_declaracion_contribuyente')
    CREATE NONCLUSTERED INDEX [IX_declaracion_contribuyente] ON dbo.[ind_declaraciones_ica] ([dec_IdContribuyente]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id=OBJECT_ID(N'dbo.ind_declaraciones_ica') AND name=N'IX_declaracion_establecimiento')
    CREATE NONCLUSTERED INDEX [IX_declaracion_establecimiento] ON dbo.[ind_declaraciones_ica] ([dec_IdEstablecimiento]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id=OBJECT_ID(N'dbo.ind_declaraciones_ica') AND name=N'IX_declaracion_periodo')
    CREATE NONCLUSTERED INDEX [IX_declaracion_periodo] ON dbo.[ind_declaraciones_ica] ([dec_AnioDeclaracion],[dec_MesDeclaracion]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id=OBJECT_ID(N'dbo.ind_declaraciones_ica') AND name=N'UQ_declaracion_numero')
    CREATE UNIQUE NONCLUSTERED INDEX [UQ_declaracion_numero] ON dbo.[ind_declaraciones_ica] ([dec_NumeroDeclaracion]) WHERE ([dec_NumeroDeclaracion] IS NOT NULL);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id=OBJECT_ID(N'dbo.ind_declaraciones_ica_actividades') AND name=N'UX_declaracion_actividad')
    CREATE UNIQUE NONCLUSTERED INDEX [UX_declaracion_actividad] ON dbo.[ind_declaraciones_ica_actividades] ([dia_IdDeclaracion],[dia_IdActividad]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id=OBJECT_ID(N'dbo.ind_establecimiento_anexos') AND name=N'IX_anexos_establecimiento')
    CREATE NONCLUSTERED INDEX [IX_anexos_establecimiento] ON dbo.[ind_establecimiento_anexos] ([anx_IdEstablecimiento],[anx_Activo]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id=OBJECT_ID(N'dbo.ind_establecimiento_novedades') AND name=N'IX_nov_establecimiento')
    CREATE NONCLUSTERED INDEX [IX_nov_establecimiento] ON dbo.[ind_establecimiento_novedades] ([nov_IdEstablecimiento],[nov_Fecha]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id=OBJECT_ID(N'dbo.ind_pagos_en_linea') AND name=N'IX_pel_Declaracion')
    CREATE NONCLUSTERED INDEX [IX_pel_Declaracion] ON dbo.[ind_pagos_en_linea] ([pel_Modulo],[pel_IdDeclaracion]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id=OBJECT_ID(N'dbo.ind_pagos_en_linea') AND name=N'UX_pel_Referencia')
    CREATE UNIQUE NONCLUSTERED INDEX [UX_pel_Referencia] ON dbo.[ind_pagos_en_linea] ([pel_Referencia]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id=OBJECT_ID(N'dbo.ind_pagos_manuales') AND name=N'UX_pma_declaracion')
    CREATE UNIQUE NONCLUSTERED INDEX [UX_pma_declaracion] ON dbo.[ind_pagos_manuales] ([pma_Modulo],[pma_IdDeclaracion]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id=OBJECT_ID(N'dbo.ind_recaudo_aplicaciones') AND name=N'IX_rap_pago')
    CREATE NONCLUSTERED INDEX [IX_rap_pago] ON dbo.[ind_recaudo_aplicaciones] ([rap_Referencia],[rap_FechaPago],[rap_Valor]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id=OBJECT_ID(N'dbo.ind_registros_manuales_anulados') AND name=N'IX_anu_declaracion')
    CREATE NONCLUSTERED INDEX [IX_anu_declaracion] ON dbo.[ind_registros_manuales_anulados] ([anu_Modulo],[anu_IdDeclaracion]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id=OBJECT_ID(N'dbo.ind_reteica') AND name=N'IX_reteica_contribuyente')
    CREATE NONCLUSTERED INDEX [IX_reteica_contribuyente] ON dbo.[ind_reteica] ([ret_IdContribuyente],[ret_Anio],[ret_Periodo]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id=OBJECT_ID(N'dbo.ind_reteica') AND name=N'UQ_reteica_numero')
    CREATE UNIQUE NONCLUSTERED INDEX [UQ_reteica_numero] ON dbo.[ind_reteica] ([ret_NumeroDeclaracion]) WHERE ([ret_NumeroDeclaracion] IS NOT NULL);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id=OBJECT_ID(N'dbo.ind_reteica_actividades') AND name=N'IX_rea_reteica')
    CREATE NONCLUSTERED INDEX [IX_rea_reteica] ON dbo.[ind_reteica_actividades] ([rea_IdReteica]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id=OBJECT_ID(N'dbo.ind_rit_firmas') AND name=N'IX_rit_firmas_contribuyente')
    CREATE NONCLUSTERED INDEX [IX_rit_firmas_contribuyente] ON dbo.[ind_rit_firmas] ([rif_IdContribuyente],[rif_FechaHora] DESC);
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name=N'FK__conf_perm__per_I__693CA210')
    ALTER TABLE dbo.[conf_permisos] ADD CONSTRAINT [FK__conf_perm__per_I__693CA210] FOREIGN KEY ([per_IdSubmodulo]) REFERENCES dbo.[conf_submodulo] ([subMod_Id]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name=N'FK__conf_perm__per_I__6A30C649')
    ALTER TABLE dbo.[conf_permisos] ADD CONSTRAINT [FK__conf_perm__per_I__6A30C649] FOREIGN KEY ([per_IdRol]) REFERENCES dbo.[conf_rol] ([rol_Id]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name=N'FK__conf_perm__per_I__6B24EA82')
    ALTER TABLE dbo.[conf_permisos] ADD CONSTRAINT [FK__conf_perm__per_I__6B24EA82] FOREIGN KEY ([per_IdModulo]) REFERENCES dbo.[conf_modulo] ([mod_Id]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name=N'FK__conf_subm__subMo__6C190EBB')
    ALTER TABLE dbo.[conf_submodulo] ADD CONSTRAINT [FK__conf_subm__subMo__6C190EBB] FOREIGN KEY ([subMod_IdModulo]) REFERENCES dbo.[conf_modulo] ([mod_Id]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name=N'FK_ActividadEstablecimiento_Actividad')
    ALTER TABLE dbo.[ind_actividad_establecimiento] ADD CONSTRAINT [FK_ActividadEstablecimiento_Actividad] FOREIGN KEY ([ace_IdCodigoActividad]) REFERENCES dbo.[ind_actividadescomercio] ([acc_Id]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name=N'FK_ActividadEstablecimiento_Establecimiento')
    ALTER TABLE dbo.[ind_actividad_establecimiento] ADD CONSTRAINT [FK_ActividadEstablecimiento_Establecimiento] FOREIGN KEY ([ace_IdEstablecimiento]) REFERENCES dbo.[ind_establecimientos] ([est_Id]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name=N'FK_aua_autorreteica')
    ALTER TABLE dbo.[ind_autorreteica_actividades] ADD CONSTRAINT [FK_aua_autorreteica] FOREIGN KEY ([aua_IdAutorreteica]) REFERENCES dbo.[ind_autorreteica] ([aut_Id]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name=N'FK_aut_IdBancoPago')
    ALTER TABLE dbo.[ind_autorreteica] ADD CONSTRAINT [FK_aut_IdBancoPago] FOREIGN KEY ([aut_IdBancoPago]) REFERENCES dbo.[ind_bancos] ([ban_Id]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name=N'FK_conf_usuario_conf_rol')
    ALTER TABLE dbo.[conf_usuarios] ADD CONSTRAINT [FK_conf_usuario_conf_rol] FOREIGN KEY ([usu_Rol]) REFERENCES dbo.[conf_rol] ([rol_Id]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name=N'FK_dec_contribuyente')
    ALTER TABLE dbo.[ind_declaraciones_ica] ADD CONSTRAINT [FK_dec_contribuyente] FOREIGN KEY ([dec_IdContribuyente]) REFERENCES dbo.[ind_contribuyentes] ([ind_Id]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name=N'FK_dec_establecimiento')
    ALTER TABLE dbo.[ind_declaraciones_ica] ADD CONSTRAINT [FK_dec_establecimiento] FOREIGN KEY ([dec_IdEstablecimiento]) REFERENCES dbo.[ind_establecimientos] ([est_Id]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name=N'FK_dec_IdBancoPago')
    ALTER TABLE dbo.[ind_declaraciones_ica] ADD CONSTRAINT [FK_dec_IdBancoPago] FOREIGN KEY ([dec_IdBancoPago]) REFERENCES dbo.[ind_bancos] ([ban_Id]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name=N'FK_dia_actividad')
    ALTER TABLE dbo.[ind_declaraciones_ica_actividades] ADD CONSTRAINT [FK_dia_actividad] FOREIGN KEY ([dia_IdActividad]) REFERENCES dbo.[ind_actividadescomercio] ([acc_Id]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name=N'FK_dia_declaracion')
    ALTER TABLE dbo.[ind_declaraciones_ica_actividades] ADD CONSTRAINT [FK_dia_declaracion] FOREIGN KEY ([dia_IdDeclaracion]) REFERENCES dbo.[ind_declaraciones_ica] ([dec_Id]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name=N'FK_ind_ban_cuenta_ind_bancos')
    ALTER TABLE dbo.[ind_bancos_cuenta] ADD CONSTRAINT [FK_ind_ban_cuenta_ind_bancos] FOREIGN KEY ([Id_banco]) REFERENCES dbo.[ind_bancos] ([ban_Id]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name=N'FK_pma_IdBanco')
    ALTER TABLE dbo.[ind_pagos_manuales] ADD CONSTRAINT [FK_pma_IdBanco] FOREIGN KEY ([pma_IdBanco]) REFERENCES dbo.[ind_bancos] ([ban_Id]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name=N'FK_rea_reteica')
    ALTER TABLE dbo.[ind_reteica_actividades] ADD CONSTRAINT [FK_rea_reteica] FOREIGN KEY ([rea_IdReteica]) REFERENCES dbo.[ind_reteica] ([ret_Id]);
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name=N'FK_ret_IdBancoPago')
    ALTER TABLE dbo.[ind_reteica] ADD CONSTRAINT [FK_ret_IdBancoPago] FOREIGN KEY ([ret_IdBancoPago]) REFERENCES dbo.[ind_bancos] ([ban_Id]);
GO
