/* Esquema base del sistema de ICA: TABLAS (con sus llaves primarias, únicas, checks y valores por defecto).
   Generado a partir de la base de Paipa al nivel de la migración 053. Solo estructura, sin datos.
   Cada tabla se crea solo si no existe, así que se puede volver a correr. */
SET NOCOUNT ON;
SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
GO
IF OBJECT_ID(N'dbo.codigos_verificacion', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[codigos_verificacion] (
    [codigo_Id] int IDENTITY(1,1) NOT NULL,
    [codigo_Valor] varchar(6) NOT NULL,
    [codigo_IdUsuario] int NOT NULL,
    [codigo_Email] nvarchar(500) NOT NULL,
    [codigo_IdEstablecimiento] int NOT NULL CONSTRAINT [DF__codigos_v__codig__1F63A897] DEFAULT ((0)),
    [codigo_FechaExpiracion] datetime NOT NULL,
    [codigo_Usado] int NOT NULL CONSTRAINT [DF__codigos_v__codig__2057CCD0] DEFAULT ((0)),
    [codigo_Rol] varchar(20) NULL,
    CONSTRAINT [PK__codigos___757510AC5D75B81A] PRIMARY KEY CLUSTERED ([codigo_Id])
    );
END
GO
IF OBJECT_ID(N'dbo.conf_ciudades', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[conf_ciudades] (
    [ciu_Id] int IDENTITY(1,1) NOT NULL,
    [ciu_CodigoDane] varchar(10) NOT NULL,
    [ciu_Nombre] varchar(150) NOT NULL,
    [ciu_Departamento] varchar(150) NOT NULL,
    [ciu_Estado] int NOT NULL CONSTRAINT [DF__conf_ciud__ciu_E__339FAB6E] DEFAULT ((1)),
    [ciu_FechaCreacion] datetime2(0) NOT NULL CONSTRAINT [DF__conf_ciud__ciu_F__3493CFA7] DEFAULT (getdate()),
    [ciu_FechaActualizacion] datetime2(0) NOT NULL CONSTRAINT [DF__conf_ciud__ciu_F__3587F3E0] DEFAULT (getdate()),
    CONSTRAINT [PK__conf_ciu__66B81ADB688E86DA] PRIMARY KEY CLUSTERED ([ciu_Id])
    );
END
GO
IF OBJECT_ID(N'dbo.conf_migraciones', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[conf_migraciones] (
    [mig_Id] int IDENTITY(1,1) NOT NULL,
    [mig_Nombre] varchar(150) NOT NULL,
    [mig_FechaAplicada] datetime NOT NULL CONSTRAINT [DF__conf_migr__mig_F__42ACE4D4] DEFAULT (getdate()),
    [mig_Nota] varchar(500) NULL,
    CONSTRAINT [PK__conf_mig__07DF5BAC4D432935] PRIMARY KEY CLUSTERED ([mig_Id]),
    CONSTRAINT [UQ__conf_mig__07D2E1171CD792C1] UNIQUE NONCLUSTERED ([mig_Nombre])
    );
END
GO
IF OBJECT_ID(N'dbo.conf_modulo', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[conf_modulo] (
    [mod_Id] int IDENTITY(1,1) NOT NULL,
    [mod_Descripcion] nvarchar(500) NOT NULL,
    [mod_Nombre] nvarchar(500) NOT NULL,
    [mod_Icono] nvarchar(500) NOT NULL,
    [mod_Url] nvarchar(500) NOT NULL,
    [mod_Estado] int NOT NULL,
    [mod_Clave] varchar(40) NULL,
    [mod_Orden] int NULL,
    CONSTRAINT [PK__conf_mod__656687D6A08BCB95] PRIMARY KEY CLUSTERED ([mod_Id])
    );
END
GO
IF OBJECT_ID(N'dbo.conf_parametros', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[conf_parametros] (
    [par_Id] int IDENTITY(1,1) NOT NULL,
    [par_Clave] varchar(60) NOT NULL,
    [par_Valor] nvarchar(500) NULL,
    [par_Nombre] nvarchar(200) NOT NULL,
    [par_Descripcion] nvarchar(1000) NULL,
    [par_Patron] varchar(200) NULL,
    [par_Estado] int NOT NULL CONSTRAINT [DF__conf_para__par_E__473C8FC7] DEFAULT ((1)),
    [par_FechaActualizacion] datetime NULL,
    [par_Sensible] bit NOT NULL CONSTRAINT [DF_conf_parametros_sensible] DEFAULT ((0)),
    CONSTRAINT [PK__conf_par__7113269D69C70CC0] PRIMARY KEY CLUSTERED ([par_Id])
    );
END
GO
IF OBJECT_ID(N'dbo.conf_permisos', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[conf_permisos] (
    [per_Id] int IDENTITY(1,1) NOT NULL,
    [per_IdSubmodulo] int NOT NULL,
    [per_IdRol] int NOT NULL,
    [per_IdModulo] int NOT NULL,
    [per_IdBoton] int NOT NULL,
    [per_Estado] int NOT NULL,
    CONSTRAINT [PK__conf_per__32A0223FF379914B] PRIMARY KEY CLUSTERED ([per_Id])
    );
END
GO
IF OBJECT_ID(N'dbo.conf_rol', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[conf_rol] (
    [rol_Id] int IDENTITY(1,1) NOT NULL,
    [rol_Nombre] nvarchar(200) NOT NULL,
    [rol_Descripcion] nvarchar(500) NULL,
    [rol_Estado] int NOT NULL CONSTRAINT [DF__conf_rol__rol_Es__6477ECF3] DEFAULT ((1)),
    [rol_Fecha_Creacion] datetime NOT NULL CONSTRAINT [DF_conf_rol_rol_Fecha_Creacion] DEFAULT (getdate()),
    [rol_Tipo] varchar(20) NULL,
    CONSTRAINT [PK__conf_rol__CF31E07BF4FE4291] PRIMARY KEY CLUSTERED ([rol_Id])
    );
END
GO
IF OBJECT_ID(N'dbo.conf_submodulo', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[conf_submodulo] (
    [subMod_Id] int IDENTITY(1,1) NOT NULL,
    [subMod_Nombre] nvarchar(500) NOT NULL,
    [subMod_Descripcion] nvarchar(500) NOT NULL,
    [subMod_IdModulo] int NOT NULL,
    [subMod_Clave] varchar(60) NULL,
    [subMod_Orden] int NULL,
    CONSTRAINT [PK__conf_sub__1FDE8EBFC53E2123] PRIMARY KEY CLUSTERED ([subMod_Id])
    );
END
GO
IF OBJECT_ID(N'dbo.conf_usuarios', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[conf_usuarios] (
    [usu_Id] int IDENTITY(1,1) NOT NULL,
    [usu_Nombres] nvarchar(500) NOT NULL,
    [usu_Apellidos] nvarchar(500) NULL,
    [usu_Usuario] nvarchar(100) NOT NULL,
    [usu_IdTipoDocumento] int NULL,
    [usu_NumeroDocumento] nvarchar(500) NULL,
    [usu_Correo] nvarchar(500) NULL,
    [usu_Telefono] nvarchar(50) NULL,
    [usu_Direccion] nvarchar(500) NULL,
    [usu_Password] nvarchar(500) NOT NULL,
    [usu_Rol] int NOT NULL,
    [usu_Estado] int NOT NULL CONSTRAINT [DF__conf_usua__usu_E__66603565] DEFAULT ((1)),
    [usu_FechaCreacion] datetime NOT NULL CONSTRAINT [DF__conf_usua__usu_F__6754599E] DEFAULT (getdate()),
    [usu_FechaActualizacion] datetime NOT NULL CONSTRAINT [DF__conf_usua__usu_F__68487DD7] DEFAULT (getdate()),
    CONSTRAINT [PK__conf_usu__43056304397B2B57] PRIMARY KEY CLUSTERED ([usu_Id])
    );
END
GO
IF OBJECT_ID(N'dbo.firmas', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[firmas] (
    [firma_Id] int IDENTITY(1,1) NOT NULL,
    [firma_IdEstablecimiento] int NOT NULL,
    [firma_IdUsuario] int NOT NULL,
    [firma_NombreUsuario] nvarchar(500) NULL,
    [firma_EmailUsuario] nvarchar(500) NULL,
    [firma_Base64] nvarchar(max) NOT NULL,
    [firma_Estado] int NOT NULL CONSTRAINT [DF__firmas__firma_Es__1B9317B3] DEFAULT ((1)),
    [firma_FechaHora] datetime NOT NULL CONSTRAINT [DF__firmas__firma_Fe__1C873BEC] DEFAULT (getdate()),
    CONSTRAINT [PK__firmas__5F83BC7541E874C6] PRIMARY KEY CLUSTERED ([firma_Id])
    );
END
GO
IF OBJECT_ID(N'dbo.firmas_declaraciones', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[firmas_declaraciones] (
    [fd_Id] int IDENTITY(1,1) NOT NULL,
    [fd_NumeroDeclaracion] varchar(50) NOT NULL,
    [fd_IdUsuario] int NOT NULL,
    [fd_NombreUsuario] nvarchar(500) NULL,
    [fd_EmailUsuario] nvarchar(500) NULL,
    [fd_FechaHora] datetime NULL CONSTRAINT [DF__firmas_de__fd_Fe__2704CA5F] DEFAULT (getdate()),
    [fd_Rol] varchar(20) NULL,
    [fd_Modulo] varchar(20) NOT NULL CONSTRAINT [DF_fd_Modulo] DEFAULT ('ICA'),
    CONSTRAINT [PK__firmas_d__7BBAA383AEA9853B] PRIMARY KEY CLUSTERED ([fd_Id])
    );
END
GO
IF OBJECT_ID(N'dbo.firmas_usuario', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[firmas_usuario] (
    [fu_Id] int IDENTITY(1,1) NOT NULL,
    [fu_IdUsuario] int NOT NULL,
    [fu_Base64] nvarchar(max) NOT NULL,
    [fu_FechaHora] datetime NULL CONSTRAINT [DF__firmas_us__fu_Fe__24285DB4] DEFAULT (getdate()),
    CONSTRAINT [PK__firmas_u__ACE8DA86E5F498EC] PRIMARY KEY CLUSTERED ([fu_Id]),
    CONSTRAINT [UQ__firmas_u__5BA7D6D52733F913] UNIQUE NONCLUSTERED ([fu_IdUsuario])
    );
END
GO
IF OBJECT_ID(N'dbo.ind_actividad_contribuyente', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[ind_actividad_contribuyente] (
    [atc_Id] int IDENTITY(1,1) NOT NULL,
    [atc_IdContribuyente] int NOT NULL,
    [atc_IdCodigoActividad] int NOT NULL,
    [atc_Anio] int NULL,
    [atc_FechaCreacion] datetime2(7) NOT NULL CONSTRAINT [DF_atc_FechaCreacion] DEFAULT (sysdatetime()),
    [atc_FechaActualizacion] datetime2(7) NULL,
    CONSTRAINT [PK_ind_actividad_contribuyente] PRIMARY KEY CLUSTERED ([atc_Id])
    );
END
GO
IF OBJECT_ID(N'dbo.ind_actividad_establecimiento', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[ind_actividad_establecimiento] (
    [ace_Id] int IDENTITY(1,1) NOT NULL,
    [ace_IdCodigoActividad] int NOT NULL,
    [ace_IdEstablecimiento] int NOT NULL,
    [ace_Anio] int NOT NULL,
    [ace_FechaCreacion] datetime2(0) NOT NULL CONSTRAINT [DF__ind_activ__ace_F__395884C4] DEFAULT (getdate()),
    [ace_FechaActualizacion] datetime2(0) NULL,
    CONSTRAINT [PK__ind_acti__097BBC6A71431826] PRIMARY KEY CLUSTERED ([ace_Id]),
    CONSTRAINT [UQ_Actividad_Establecimiento_Anio] UNIQUE NONCLUSTERED ([ace_IdCodigoActividad],[ace_IdEstablecimiento],[ace_Anio])
    );
END
GO
IF OBJECT_ID(N'dbo.ind_actividadescomercio', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[ind_actividadescomercio] (
    [acc_Id] int IDENTITY(1,1) NOT NULL,
    [acc_Anio] int NULL,
    [acc_Codigo] varchar(20) NOT NULL,
    [acc_Nombre] varchar(500) NOT NULL,
    [acc_Tarifa] decimal(4,3) NOT NULL,
    [acc_GrupoTarifa] int NOT NULL,
    [acc_Exento] bit NOT NULL CONSTRAINT [DF__ind_activ__acc_E__19DFD96B] DEFAULT ((0)),
    [acc_Estado] int NOT NULL CONSTRAINT [DF__ind_activ__acc_E__1AD3FDA4] DEFAULT ((1)),
    [acc_FechaCreacion] datetime2(0) NOT NULL CONSTRAINT [DF__ind_activ__acc_F__1BC821DD] DEFAULT (sysdatetime()),
    [acc_FechaActualizacion] datetime2(0) NOT NULL CONSTRAINT [DF__ind_activ__acc_F__1CBC4616] DEFAULT (sysdatetime()),
    CONSTRAINT [PK_ind_actividadescomercio] PRIMARY KEY CLUSTERED ([acc_Id]),
    CONSTRAINT [UQ_ind_actividadescomercio_Anio_Codigo] UNIQUE NONCLUSTERED ([acc_Anio],[acc_Codigo])
    );
END
GO
IF OBJECT_ID(N'dbo.ind_archivos_asobancaria', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[ind_archivos_asobancaria] (
    [arc_Id] int IDENTITY(1,1) NOT NULL,
    [arc_IdUsuario] int NOT NULL,
    [arc_Nombre] varchar(255) NOT NULL,
    [arc_Ruta] varchar(400) NULL,
    [arc_Tipo] varchar(100) NULL,
    [arc_Tamano] int NULL,
    [arc_Hash] varchar(64) NULL,
    [arc_IdBanco] int NULL,
    [arc_FechaPago] date NULL,
    [arc_TotalRegistros] int NOT NULL CONSTRAINT [DF_arc_TotalRegistros] DEFAULT ((0)),
    [arc_TotalAplicados] int NOT NULL CONSTRAINT [DF_arc_TotalAplicados] DEFAULT ((0)),
    [arc_TotalYaPagados] int NOT NULL CONSTRAINT [DF_arc_TotalYaPagados] DEFAULT ((0)),
    [arc_TotalFallidos] int NOT NULL CONSTRAINT [DF_arc_TotalFallidos] DEFAULT ((0)),
    [arc_ValorControl] decimal(18,2) NULL,
    [arc_ValorSumado] decimal(18,2) NULL,
    [arc_RegistrosControl] int NULL,
    [arc_Descripcion] nvarchar(max) NULL,
    [arc_FechaCarga] datetime2(7) NOT NULL CONSTRAINT [DF_arc_FechaCarga] DEFAULT (sysdatetime()),
    CONSTRAINT [PK_ind_archivos_asobancaria] PRIMARY KEY CLUSTERED ([arc_Id])
    );
END
GO
IF OBJECT_ID(N'dbo.ind_autorreteica', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[ind_autorreteica] (
    [aut_Id] int IDENTITY(1,1) NOT NULL,
    [aut_NumeroDeclaracion] bigint NULL,
    [aut_IdContribuyente] int NOT NULL,
    [aut_Anio] int NOT NULL,
    [aut_Periodo] tinyint NOT NULL,
    [aut_FechaDeclaracion] date NULL,
    [aut_HoraDeclaracion] time(0) NULL,
    [aut_Corrige] bigint NULL,
    [aut_Estado] int NULL,
    [aut_FechaPresentacion] datetime NULL,
    [aut_ValorConcepto9] decimal(18,2) NOT NULL CONSTRAINT [DF__ind_autor__aut_V__6F1576F7] DEFAULT ((0)),
    [aut_ValorConcepto10] decimal(18,2) NOT NULL CONSTRAINT [DF__ind_autor__aut_V__70099B30] DEFAULT ((0)),
    [aut_ValorConcepto11] decimal(18,2) NOT NULL CONSTRAINT [DF__ind_autor__aut_V__70FDBF69] DEFAULT ((0)),
    [aut_ValorConcepto12] decimal(18,2) NOT NULL CONSTRAINT [DF__ind_autor__aut_V__71F1E3A2] DEFAULT ((0)),
    [aut_ValorConcepto13] decimal(18,2) NOT NULL CONSTRAINT [DF__ind_autor__aut_V__72E607DB] DEFAULT ((0)),
    [aut_ImpuestoEnergia] decimal(18,2) NOT NULL CONSTRAINT [DF__ind_autor__aut_I__73DA2C14] DEFAULT ((0)),
    [aut_ValorConcepto15] decimal(18,2) NOT NULL CONSTRAINT [DF__ind_autor__aut_V__74CE504D] DEFAULT ((0)),
    [aut_ValorConcepto16] decimal(18,2) NOT NULL CONSTRAINT [DF__ind_autor__aut_V__75C27486] DEFAULT ((0)),
    [aut_ValorConcepto17] decimal(18,2) NOT NULL CONSTRAINT [DF__ind_autor__aut_V__76B698BF] DEFAULT ((0)),
    [aut_ValorConcepto18] decimal(18,2) NOT NULL CONSTRAINT [DF__ind_autor__aut_V__77AABCF8] DEFAULT ((0)),
    [aut_ValorConcepto19] decimal(18,2) NOT NULL CONSTRAINT [DF__ind_autor__aut_V__789EE131] DEFAULT ((0)),
    [aut_ValorConcepto20] decimal(18,2) NOT NULL CONSTRAINT [DF__ind_autor__aut_V__7993056A] DEFAULT ((0)),
    [aut_ValorConcepto21] decimal(18,2) NOT NULL CONSTRAINT [DF__ind_autor__aut_V__7A8729A3] DEFAULT ((0)),
    [aut_ValorConcepto22] decimal(18,2) NOT NULL CONSTRAINT [DF__ind_autor__aut_V__7B7B4DDC] DEFAULT ((0)),
    [aut_ValorConcepto23] decimal(18,2) NOT NULL CONSTRAINT [DF__ind_autor__aut_V__7C6F7215] DEFAULT ((0)),
    [aut_Pagado] bit NOT NULL CONSTRAINT [DF__ind_autor__aut_P__7D63964E] DEFAULT ((0)),
    [aut_FechaPago] date NULL,
    [aut_ValorPago] decimal(18,2) NULL,
    [aut_BancoPago] varchar(120) NULL,
    [aut_FechaCreador] datetime NULL,
    [aut_Creador] int NULL,
    [aut_PSE_RequestId] bigint NULL,
    [aut_PSE_Estado] varchar(20) NULL,
    [aut_PSE_FechaEstado] datetime2(7) NULL,
    [aut_PSE_Mensaje] varchar(300) NULL,
    [aut_FechaRealPago] datetime2(7) NULL,
    [aut_AnioPago] int NULL,
    [aut_RutaPago] varchar(200) NULL,
    [aut_IdBancoPago] int NULL,
    CONSTRAINT [PK_ind_autorreteica] PRIMARY KEY CLUSTERED ([aut_Id]),
    CONSTRAINT [CK_autorreteica_estado] CHECK ([aut_Estado] IS NULL OR ([aut_Estado]=(2) OR [aut_Estado]=(1) OR [aut_Estado]=(0))),
    CONSTRAINT [CK_autorreteica_periodo] CHECK ([aut_Periodo]>=(1) AND [aut_Periodo]<=(6))
    );
END
GO
IF OBJECT_ID(N'dbo.ind_autorreteica_actividades', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[ind_autorreteica_actividades] (
    [aua_Id] int IDENTITY(1,1) NOT NULL,
    [aua_IdAutorreteica] int NOT NULL,
    [aua_IdActividad] int NOT NULL,
    [aua_IngresosGravados] decimal(18,2) NOT NULL CONSTRAINT [DF__ind_autor__aua_I__02284B6B] DEFAULT ((0)),
    [aua_Tarifa] decimal(6,5) NOT NULL CONSTRAINT [DF__ind_autor__aua_T__031C6FA4] DEFAULT ((0)),
    [aua_ValorImpuesto] decimal(18,2) NOT NULL CONSTRAINT [DF__ind_autor__aua_V__041093DD] DEFAULT ((0)),
    [aua_Activo] bit NOT NULL CONSTRAINT [DF__ind_autor__aua_A__0504B816] DEFAULT ((1)),
    [aua_FechaCreador] datetime NULL,
    [aua_ValorManual] bit NOT NULL CONSTRAINT [DF_aua_ValorManual] DEFAULT ((0)),
    CONSTRAINT [PK_ind_autorreteica_actividades] PRIMARY KEY CLUSTERED ([aua_Id])
    );
END
GO
IF OBJECT_ID(N'dbo.ind_bancos', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[ind_bancos] (
    [ban_Id] int IDENTITY(1,1) NOT NULL,
    [ban_Codigo] varchar(6) NOT NULL,
    [ban_Nombre] varchar(128) NOT NULL,
    [ban_Asobancaria] varchar(6) NOT NULL,
    [ban_CuentaContable] varchar(30) NULL,
    [ban_CuentaRecaudadora] varchar(30) NULL,
    [ban_Activo] bit NOT NULL CONSTRAINT [DF_ban_Activo] DEFAULT ((1)),
    [ban_FechaCreacion] datetime2(7) NOT NULL CONSTRAINT [DF_ban_FechaCreacion] DEFAULT (sysdatetime()),
    [ban_FechaActualizacion] datetime2(7) NULL,
    [ban_ActualizadoPor] int NULL,
    CONSTRAINT [PK_ind_bancos] PRIMARY KEY CLUSTERED ([ban_Id])
    );
END
GO
IF OBJECT_ID(N'dbo.ind_bancos_cuenta', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[ind_bancos_cuenta] (
    [Id] int IDENTITY(1,1) NOT NULL,
    [Id_banco] int NOT NULL,
    [ano] int NOT NULL,
    [cuenta] varchar(70) NOT NULL,
    [creador] varchar(20) NOT NULL,
    [fecha_creador] datetime NULL,
    [descripcion] varchar(100) NULL,
    CONSTRAINT [PK_ind_bancos_cuenta] PRIMARY KEY CLUSTERED ([Id])
    );
END
GO
IF OBJECT_ID(N'dbo.ind_conceptos', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[ind_conceptos] (
    [con_Id] int IDENTITY(1,1) NOT NULL,
    [con_Anio] int NULL,
    [con_Codigo] varchar(50) NOT NULL,
    [con_Nombre] varchar(500) NOT NULL,
    [con_Observaciones] varchar(500) NOT NULL,
    [con_Estado] int NOT NULL,
    [con_FechaCreacion] datetime2(0) NOT NULL CONSTRAINT [DF__ind_conce__con_F__1F98B2C1] DEFAULT (sysdatetime()),
    [con_FechaActualizacion] datetime2(0) NOT NULL CONSTRAINT [DF__ind_conce__con_F__208CD6FA] DEFAULT (sysdatetime()),
    CONSTRAINT [PK_ind_conceptos] PRIMARY KEY CLUSTERED ([con_Id])
    );
END
GO
IF OBJECT_ID(N'dbo.ind_consecutivos', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[ind_consecutivos] (
    [cse_Tipo] varchar(40) NOT NULL,
    [cse_Anio] int NOT NULL,
    [cse_Valor] int NOT NULL CONSTRAINT [DF__ind_conse__cse_V__00750D23] DEFAULT ((0)),
    [cse_FechaActualizacion] datetime NULL,
    CONSTRAINT [PK_ind_consecutivos] PRIMARY KEY CLUSTERED ([cse_Tipo],[cse_Anio])
    );
END
GO
IF OBJECT_ID(N'dbo.ind_contribuyentes', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[ind_contribuyentes] (
    [ind_Id] int IDENTITY(1,1) NOT NULL,
    [ind_NumeroIdentificacion] int NOT NULL,
    [ind_DV] int NOT NULL,
    [ind_IdTipoDocumento] int NOT NULL,
    [ind_PrimerNombre] varchar(100) NOT NULL,
    [ind_SegundoNombre] varchar(100) NULL,
    [ind_PrimerApellido] varchar(100) NULL,
    [ind_SegundoApellido] varchar(100) NULL,
    [ind_Direccion] varchar(200) NOT NULL,
    [ind_IdCiudad] int NOT NULL,
    [ind_Persona] int NOT NULL,
    [ind_IdRegimen] int NULL,
    [ind_Telefono] bigint NULL,
    [ind_Email] varchar(500) NULL,
    [ind_Estado] int NOT NULL,
    [ind_FechaCreacion] datetime2(0) NOT NULL CONSTRAINT [DF__ind_contr__ind_F__160F4887] DEFAULT (sysdatetime()),
    [ind_FechaActualizacion] datetime2(0) NOT NULL CONSTRAINT [DF__ind_contr__ind_F__17036CC0] DEFAULT (sysdatetime()),
    [ind_EmailContador] nvarchar(150) NULL,
    [ind_EmailRevisor] nvarchar(150) NULL,
    [ind_Matricula] varchar(50) NULL,
    [ind_Fecha_matricula] datetime NULL,
    [ind_Fecha_inicio] datetime NULL,
    [ind_Ind_camara_comercio] int NULL,
    [ind_Cedula_representante] varchar(20) NULL,
    [ind_Nombre_representante] varchar(100) NULL,
    [ind_Email_representante] varchar(150) NULL,
    [ind_RIT_FechaCreacion] datetime NULL,
    [ind_CedulaContador] varchar(20) NULL,
    [ind_NombreContador] varchar(100) NULL,
    [ind_TarjetaProfContador] varchar(50) NULL,
    [ind_CedulaRevisor] varchar(20) NULL,
    [ind_NombreRevisor] varchar(100) NULL,
    [ind_TarjetaProfRevisor] varchar(50) NULL,
    [ind_Rut] varchar(6) NULL,
    [ind_Rut_segundo] varchar(6) NULL,
    [ind_Rut_tercero] varchar(6) NULL,
    [ind_Autorizacion] int NULL,
    [ind_RegimenTributario] varchar(200) NULL,
    [ind_Responsabilidades] varchar(200) NULL,
    [ind_NoSujetas] bit NOT NULL CONSTRAINT [DF__ind_contr__ind_N__119F9925] DEFAULT ((0)),
    [ind_SinAvisosTableros] bit NOT NULL CONSTRAINT [DF__ind_contr__ind_S__1293BD5E] DEFAULT ((0)),
    [ind_FechaCese] date NULL,
    [ind_CausalCese] varchar(1) NULL,
    [ind_ObservacionCese] varchar(255) NULL,
    [ind_Telefono_representante] varchar(30) NULL,
    [ind_EsConsorcio] bit NOT NULL CONSTRAINT [DF__ind_contr__ind_E__17236851] DEFAULT ((0)),
    [ind_PatrimonioAutonomo] bit NOT NULL CONSTRAINT [DF__ind_contr__ind_P__18178C8A] DEFAULT ((0)),
    CONSTRAINT [PK_ind_contribuyentes] PRIMARY KEY CLUSTERED ([ind_Id])
    );
END
GO
IF OBJECT_ID(N'dbo.ind_declaracion_soportes', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[ind_declaracion_soportes] (
    [sop_Id] int IDENTITY(1,1) NOT NULL,
    [sop_Modulo] varchar(15) NOT NULL,
    [sop_IdDeclaracion] int NOT NULL,
    [sop_Tipo] varchar(30) NOT NULL,
    [sop_NombreOriginal] nvarchar(255) NOT NULL,
    [sop_Ruta] nvarchar(400) NOT NULL,
    [sop_Tamano] int NOT NULL,
    [sop_IdUsuario] int NULL,
    [sop_FechaCarga] datetime2(0) NOT NULL CONSTRAINT [DF_sop_FechaCarga] DEFAULT (getdate()),
    [sop_Activo] bit NOT NULL CONSTRAINT [DF_sop_Activo] DEFAULT ((1)),
    CONSTRAINT [PK_ind_declaracion_soportes] PRIMARY KEY CLUSTERED ([sop_Id]),
    CONSTRAINT [CK_sop_Modulo] CHECK ([sop_Modulo]='autorreteica' OR [sop_Modulo]='reteica' OR [sop_Modulo]='ica'),
    CONSTRAINT [CK_sop_Tipo] CHECK ([sop_Tipo]='soporte_pago' OR [sop_Tipo]='declaracion_original')
    );
END
GO
IF OBJECT_ID(N'dbo.ind_declaraciones_historicas', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[ind_declaraciones_historicas] (
    [his_Id] int IDENTITY(1,1) NOT NULL,
    [his_Modulo] varchar(15) NOT NULL,
    [his_IdDeclaracion] int NOT NULL,
    [his_NumeroPapel] nvarchar(30) NOT NULL,
    [his_FechaPresentacion] date NOT NULL,
    [his_Observacion] nvarchar(500) NULL,
    [his_IdUsuario] int NOT NULL,
    [his_FechaRegistro] datetime2(0) NOT NULL CONSTRAINT [DF_his_FechaRegistro] DEFAULT (getdate()),
    CONSTRAINT [PK_ind_declaraciones_historicas] PRIMARY KEY CLUSTERED ([his_Id]),
    CONSTRAINT [CK_his_Modulo] CHECK ([his_Modulo]='autorreteica' OR [his_Modulo]='reteica' OR [his_Modulo]='ica')
    );
END
GO
IF OBJECT_ID(N'dbo.ind_declaraciones_ica', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[ind_declaraciones_ica] (
    [dec_Id] int IDENTITY(1,1) NOT NULL,
    [dec_AnioDeclaracion] int NOT NULL,
    [dec_MesDeclaracion] int NOT NULL,
    [dec_NumeroDeclaracion] bigint NULL,
    [dec_IdContribuyente] int NOT NULL,
    [dec_IdEstablecimiento] int NULL,
    [dec_FechaDeclaracion] datetime2(7) NULL,
    [dec_HoraDeclaracion] time(7) NULL,
    [dec_ValorPago] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_V__40058253] DEFAULT ((0)),
    [dec_FechaPago] datetime2(7) NULL,
    [dec_Pagado] bit NULL CONSTRAINT [DF__ind_decla__dec_P__40F9A68C] DEFAULT ((0)),
    [dec_BancoPago] varchar(60) NULL,
    [dec_ValorConcepto1] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_V__41EDCAC5] DEFAULT ((0)),
    [dec_ValorConcepto2] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_V__42E1EEFE] DEFAULT ((0)),
    [dec_ValorConcepto3] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_V__43D61337] DEFAULT ((0)),
    [dec_ValorConcepto4] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_V__44CA3770] DEFAULT ((0)),
    [dec_ValorConcepto5] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_V__45BE5BA9] DEFAULT ((0)),
    [dec_ValorConcepto6] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_V__46B27FE2] DEFAULT ((0)),
    [dec_ValorConcepto7] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_V__47A6A41B] DEFAULT ((0)),
    [dec_ValorConcepto8] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_V__489AC854] DEFAULT ((0)),
    [dec_ValorConcepto9] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_V__498EEC8D] DEFAULT ((0)),
    [dec_ValorConcepto10] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_V__4A8310C6] DEFAULT ((0)),
    [dec_ValorConcepto11] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_V__4B7734FF] DEFAULT ((0)),
    [dec_ValorConcepto12] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_V__4C6B5938] DEFAULT ((0)),
    [dec_ValorConcepto13] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_V__4D5F7D71] DEFAULT ((0)),
    [dec_ValorConcepto14] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_V__4E53A1AA] DEFAULT ((0)),
    [dec_ValorConcepto15] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_V__4F47C5E3] DEFAULT ((0)),
    [dec_ValorConcepto16] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_V__503BEA1C] DEFAULT ((0)),
    [dec_ValorConcepto17] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_V__51300E55] DEFAULT ((0)),
    [dec_ValorConcepto18] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_V__5224328E] DEFAULT ((0)),
    [dec_ValorConcepto19] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_V__531856C7] DEFAULT ((0)),
    [dec_ValorConcepto20] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_V__540C7B00] DEFAULT ((0)),
    [dec_ValorConcepto21] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_V__55009F39] DEFAULT ((0)),
    [dec_ValorConcepto22] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_V__55F4C372] DEFAULT ((0)),
    [dec_ValorConcepto23] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_V__56E8E7AB] DEFAULT ((0)),
    [dec_ValorConcepto24] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_V__57DD0BE4] DEFAULT ((0)),
    [dec_ValorConcepto25] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_V__58D1301D] DEFAULT ((0)),
    [dec_ValorConcepto26] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_V__59C55456] DEFAULT ((0)),
    [dec_ValorConcepto27] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_V__5AB9788F] DEFAULT ((0)),
    [dec_ValorConcepto28] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_V__5BAD9CC8] DEFAULT ((0)),
    [dec_ValorConcepto29] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_V__5CA1C101] DEFAULT ((0)),
    [dec_ValorConcepto30] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_V__5D95E53A] DEFAULT ((0)),
    [dec_TotalCalculo] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_T__5E8A0973] DEFAULT ((0)),
    [dec_Creador] varchar(50) NULL,
    [dec_FechaCreador] datetime2(7) NULL,
    [dec_Modificador] varchar(50) NULL,
    [dec_FechaModificador] datetime2(7) NULL,
    [dec_TotalIngresos] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_T__5F7E2DAC] DEFAULT ((0)),
    [dec_IngresosFueraMunicipio] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_I__607251E5] DEFAULT ((0)),
    [dec_IngresosDevoluciones] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_I__6166761E] DEFAULT ((0)),
    [dec_IngresosExportaciones] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_I__625A9A57] DEFAULT ((0)),
    [dec_IngresosVentas] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_I__634EBE90] DEFAULT ((0)),
    [dec_IngresosActividades] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_I__6442E2C9] DEFAULT ((0)),
    [dec_IngresosOtrasActividades] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_I__65370702] DEFAULT ((0)),
    [dec_DeclaracionCorrige] bigint NULL,
    [dec_VigenciaActual] int NULL,
    [dec_AnioPago] int NULL,
    [dec_FechaIntereses] datetime2(7) NULL,
    [dec_OpcionUso] varchar(1) NULL,
    [dec_BaseGravable] decimal(18,2) NULL CONSTRAINT [DF__ind_decla__dec_B__662B2B3B] DEFAULT ((0)),
    [dec_CuentaDebito] varchar(70) NULL,
    [dec_FechaRealPago] datetime2(7) NULL,
    [dec_RutaDeclaracion] varchar(200) NULL,
    [dec_RutaPago] varchar(200) NULL,
    [dec_CapacidadInstalada] int NULL CONSTRAINT [DF_ind_declaraciones_ica_dec_CapacidadInstalada] DEFAULT ((0)),
    [dec_ValorImpuesto] decimal(18,2) NULL CONSTRAINT [DF_ind_declaraciones_ica_dec_ValorImpuesto] DEFAULT ((0)),
    [dec_PSE_RequestId] bigint NULL,
    [dec_Estado] int NULL,
    [dec_FechaPresentacion] datetime NULL,
    [dec_PSE_Estado] varchar(20) NULL,
    [dec_PSE_FechaEstado] datetime2(7) NULL,
    [dec_PSE_Mensaje] varchar(300) NULL,
    [dec_TipoSancion] varchar(20) NULL,
    [dec_OtraSancion] nvarchar(100) NULL,
    [dec_IdBancoPago] int NULL,
    CONSTRAINT [PK__ind_decl__206B0E049E5DDFA2] PRIMARY KEY CLUSTERED ([dec_Id])
    );
END
GO
IF OBJECT_ID(N'dbo.ind_declaraciones_ica_actividades', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[ind_declaraciones_ica_actividades] (
    [dia_Id] int IDENTITY(1,1) NOT NULL,
    [dia_IdDeclaracion] int NOT NULL,
    [dia_IdActividad] int NOT NULL,
    [dia_BaseGravable] decimal(18,2) NOT NULL CONSTRAINT [DF__ind_decla__dia_B__756D6ECB] DEFAULT ((0)),
    [dia_Tarifa] decimal(4,3) NOT NULL CONSTRAINT [DF__ind_decla__dia_T__76619304] DEFAULT ((0)),
    [dia_ValorImpuesto] decimal(18,2) NOT NULL CONSTRAINT [DF__ind_decla__dia_V__7755B73D] DEFAULT ((0)),
    [dia_Activo] bit NOT NULL CONSTRAINT [DF__ind_decla__dia_A__7849DB76] DEFAULT ((1)),
    [dia_FechaCreador] datetime2(7) NOT NULL CONSTRAINT [DF__ind_decla__dia_F__793DFFAF] DEFAULT (getdate()),
    [dia_FechaModificador] datetime2(7) NULL,
    CONSTRAINT [PK_ind_declaraciones_ica_actividades] PRIMARY KEY CLUSTERED ([dia_Id])
    );
END
GO
IF OBJECT_ID(N'dbo.ind_establecimiento_anexos', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[ind_establecimiento_anexos] (
    [anx_Id] int IDENTITY(1,1) NOT NULL,
    [anx_IdEstablecimiento] int NULL,
    [anx_Tipo] varchar(40) NULL,
    [anx_NombreOriginal] varchar(255) NOT NULL,
    [anx_Ruta] varchar(400) NOT NULL,
    [anx_Extension] varchar(10) NULL,
    [anx_Tamano] int NULL,
    [anx_FechaCarga] datetime NOT NULL CONSTRAINT [DF__ind_estab__anx_F__54CB950F] DEFAULT (getdate()),
    [anx_IdUsuario] int NULL,
    [anx_Activo] int NOT NULL CONSTRAINT [DF__ind_estab__anx_A__55BFB948] DEFAULT ((1)),
    [anx_IdContribuyente] int NULL,
    CONSTRAINT [PK__ind_esta__6A8216B5465AE028] PRIMARY KEY CLUSTERED ([anx_Id]),
    CONSTRAINT [CK_anexo_tiene_dueno] CHECK ([anx_IdEstablecimiento] IS NOT NULL OR [anx_IdContribuyente] IS NOT NULL)
    );
END
GO
IF OBJECT_ID(N'dbo.ind_establecimiento_novedades', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[ind_establecimiento_novedades] (
    [nov_Id] int IDENTITY(1,1) NOT NULL,
    [nov_IdEstablecimiento] int NOT NULL,
    [nov_Tipo] varchar(20) NOT NULL,
    [nov_FechaCese] date NULL,
    [nov_Observacion] nvarchar(1000) NULL,
    [nov_IdUsuario] int NULL,
    [nov_Fecha] datetime NOT NULL CONSTRAINT [DF_nov_Fecha] DEFAULT (getdate()),
    CONSTRAINT [PK__ind_esta__1A59CDCE7CE34052] PRIMARY KEY CLUSTERED ([nov_Id])
    );
END
GO
IF OBJECT_ID(N'dbo.ind_establecimientos', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[ind_establecimientos] (
    [est_Id] int IDENTITY(1,1) NOT NULL,
    [est_Codigo] int NULL,
    [est_IdContribuyente] int NOT NULL,
    [est_Nombre] varchar(255) NOT NULL,
    [est_Direccion] varchar(150) NULL,
    [est_Pais] varchar(5) NULL,
    [est_Departamento] varchar(5) NULL,
    [est_Ciudad] varchar(5) NULL,
    [est_Barrio] varchar(150) NULL,
    [est_Correo] varchar(150) NULL,
    [est_Activos] float NULL CONSTRAINT [DF__ind_estab__est_A__6FE99F9F] DEFAULT ((0)),
    [est_Local] int NULL,
    [est_Matricula] varchar(50) NULL,
    [est_Fecha_matricula] datetime NULL,
    [est_Fecha_inscripcion] datetime NULL,
    [est_Fecha_inicio] datetime NULL,
    [est_Activo] int NOT NULL CONSTRAINT [DF__ind_estab__est_A__70DDC3D8] DEFAULT ((-1)),
    [est_Observacion_cierre] varchar(255) NULL,
    [est_Fecha_cierre] datetime NULL,
    [est_Resolucion_cierre] varchar(50) NULL,
    [est_Area] varchar(20) NULL,
    [est_Creador] varchar(30) NULL,
    [est_Fecha_creador] datetime NULL,
    [est_Excento_avisos] int NOT NULL CONSTRAINT [DF__ind_estab__est_E__71D1E811] DEFAULT ((0)),
    [est_Ultimo_ano_pago] int NULL,
    [est_Estado_registro] varchar(1) NULL,
    [est_Local_municipio] int NOT NULL CONSTRAINT [DF__ind_estab__est_L__72C60C4A] DEFAULT ((0)),
    [est_Codigo_catastral] varchar(30) NULL,
    [est_Opcion_uso] varchar(1) NULL,
    [est_Causal] varchar(1) NULL,
    [est_Cedula_representante] varchar(20) NULL,
    [est_Nombre_representante] varchar(100) NULL,
    [est_Email_representante] varchar(150) NULL,
    [est_Cedula_contador] varchar(20) NULL,
    [est_Nombre_contador] varchar(100) NULL,
    [est_Tarjeta_profesional] varchar(50) NULL,
    [est_Cedula_revisor] varchar(20) NULL,
    [est_Nombre_revisor] varchar(100) NULL,
    [est_Ruta_anexos] varchar(150) NULL,
    [est_Tarjeta_profesional_revisor] varchar(50) NULL,
    [est_Exento] int NOT NULL CONSTRAINT [DF__ind_estab__est_E__73BA3083] DEFAULT ((0)),
    [est_Rut] varchar(6) NULL,
    [est_Fecha_actividad] datetime NULL,
    [est_Rut_segundo] varchar(6) NULL,
    [est_Rut_tercero] varchar(6) NULL,
    [est_Ind_camara_comercio] int NOT NULL CONSTRAINT [DF__ind_estab__est_I__74AE54BC] DEFAULT ((0)),
    [est_Autorizacion] int NOT NULL CONSTRAINT [DF__ind_estab__est_A__367C1819] DEFAULT ((0)),
    [est_Observacion] varchar(255) NULL,
    [est_Telefono] varchar(30) NULL,
    CONSTRAINT [PK_ind_establecimientos] PRIMARY KEY CLUSTERED ([est_Id])
    );
END
GO
IF OBJECT_ID(N'dbo.ind_grupotarifa', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[ind_grupotarifa] (
    [gru_Id] int IDENTITY(1,1) NOT NULL,
    [gru_Nombre] varchar(500) NOT NULL,
    [gru_Codigo] varchar(500) NOT NULL,
    [gru_Estado] int NOT NULL,
    [gru_FechaCreacion] datetime2(0) NOT NULL CONSTRAINT [DF__ind_grupo__gru_F__236943A5] DEFAULT (sysdatetime()),
    [gru_FechaActualizacion] datetime2(0) NOT NULL CONSTRAINT [DF__ind_grupo__gru_F__245D67DE] DEFAULT (sysdatetime()),
    CONSTRAINT [PK_ind_grupotarifa] PRIMARY KEY CLUSTERED ([gru_Id])
    );
END
GO
IF OBJECT_ID(N'dbo.ind_pagos_en_linea', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[ind_pagos_en_linea] (
    [pel_Id] int IDENTITY(1,1) NOT NULL,
    [pel_Pasarela] varchar(15) NOT NULL,
    [pel_Modulo] varchar(15) NOT NULL,
    [pel_IdDeclaracion] int NOT NULL,
    [pel_Referencia] varchar(60) NOT NULL,
    [pel_Centavos] bigint NOT NULL,
    [pel_Intereses] decimal(18,2) NOT NULL CONSTRAINT [DF_pel_Intereses] DEFAULT ((0)),
    [pel_Transaccion] varchar(60) NULL,
    [pel_Estado] varchar(20) NOT NULL CONSTRAINT [DF_pel_Estado] DEFAULT ('CREADO'),
    [pel_Medio] varchar(40) NULL,
    [pel_Mensaje] nvarchar(300) NULL,
    [pel_IdUsuario] int NULL,
    [pel_FechaCreacion] datetime2(0) NOT NULL CONSTRAINT [DF_pel_FechaCreacion] DEFAULT (sysdatetime()),
    [pel_FechaEstado] datetime2(0) NULL,
    [pel_Ambiente] varchar(4) NULL,
    CONSTRAINT [PK_ind_pagos_en_linea] PRIMARY KEY CLUSTERED ([pel_Id])
    );
END
GO
IF OBJECT_ID(N'dbo.ind_pagos_manuales', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[ind_pagos_manuales] (
    [pma_Id] int IDENTITY(1,1) NOT NULL,
    [pma_Modulo] varchar(15) NOT NULL,
    [pma_IdDeclaracion] int NOT NULL,
    [pma_FechaPago] date NOT NULL,
    [pma_Valor] decimal(18,2) NOT NULL,
    [pma_Medio] varchar(20) NOT NULL,
    [pma_Banco] nvarchar(60) NULL,
    [pma_Referencia] nvarchar(60) NULL,
    [pma_Observacion] nvarchar(500) NULL,
    [pma_IdSoporte] int NULL,
    [pma_IdUsuario] int NOT NULL,
    [pma_FechaRegistro] datetime2(0) NOT NULL CONSTRAINT [DF_pma_FechaRegistro] DEFAULT (getdate()),
    [pma_IdBanco] int NULL,
    [pma_FechaPresentacion] date NULL,
    [pma_IdCuentaContable] int NULL,
    [pma_CuentaContable] varchar(70) NULL,
    CONSTRAINT [PK_ind_pagos_manuales] PRIMARY KEY CLUSTERED ([pma_Id]),
    CONSTRAINT [CK_pma_Medio] CHECK ([pma_Medio]='NO_INDICADO' OR [pma_Medio]='OTRO' OR [pma_Medio]='VENTANILLA' OR [pma_Medio]='CONSIGNACION' OR [pma_Medio]='TRANSFERENCIA'),
    CONSTRAINT [CK_pma_Modulo] CHECK ([pma_Modulo]='autorreteica' OR [pma_Modulo]='reteica' OR [pma_Modulo]='ica'),
    CONSTRAINT [CK_pma_Valor] CHECK ([pma_Valor]>(0))
    );
END
GO
IF OBJECT_ID(N'dbo.ind_recaudo_aplicaciones', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[ind_recaudo_aplicaciones] (
    [rap_Id] int IDENTITY(1,1) NOT NULL,
    [rap_IdArchivo] int NULL,
    [rap_Referencia] varchar(48) NOT NULL,
    [rap_Valor] decimal(18,2) NOT NULL,
    [rap_FechaPago] date NOT NULL,
    [rap_Modulo] varchar(15) NOT NULL,
    [rap_IdDeclaracion] int NOT NULL,
    [rap_IdUsuario] int NULL,
    [rap_Fecha] datetime2(0) NOT NULL CONSTRAINT [DF_rap_Fecha] DEFAULT (getdate()),
    CONSTRAINT [PK_ind_recaudo_aplicaciones] PRIMARY KEY CLUSTERED ([rap_Id]),
    CONSTRAINT [CK_rap_Modulo] CHECK ([rap_Modulo]='autorreteica' OR [rap_Modulo]='reteica' OR [rap_Modulo]='ica')
    );
END
GO
IF OBJECT_ID(N'dbo.ind_registros_manuales_anulados', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[ind_registros_manuales_anulados] (
    [anu_Id] int IDENTITY(1,1) NOT NULL,
    [anu_Modulo] varchar(15) NOT NULL,
    [anu_IdDeclaracion] int NOT NULL,
    [anu_Tipo] varchar(15) NOT NULL,
    [anu_Motivo] nvarchar(500) NOT NULL,
    [anu_Datos] nvarchar(max) NULL,
    [anu_IdUsuario] int NOT NULL,
    [anu_Fecha] datetime2(0) NOT NULL CONSTRAINT [DF_anu_Fecha] DEFAULT (getdate()),
    CONSTRAINT [PK_ind_registros_manuales_anulados] PRIMARY KEY CLUSTERED ([anu_Id]),
    CONSTRAINT [CK_anu_Modulo] CHECK ([anu_Modulo]='autorreteica' OR [anu_Modulo]='reteica' OR [anu_Modulo]='ica'),
    CONSTRAINT [CK_anu_Tipo] CHECK ([anu_Tipo]='PAGO' OR [anu_Tipo]='HISTORICA')
    );
END
GO
IF OBJECT_ID(N'dbo.ind_renglones_retencion', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[ind_renglones_retencion] (
    [ren_Id] int IDENTITY(1,1) NOT NULL,
    [ren_Modulo] varchar(20) NOT NULL,
    [ren_Anio] int NOT NULL,
    [ren_Codigo] int NOT NULL,
    [ren_Nombre] nvarchar(300) NOT NULL,
    [ren_Formula] nvarchar(1000) NULL,
    [ren_Manual] bit NOT NULL CONSTRAINT [DF__ind_rengl__ren_M__09C96D33] DEFAULT ((0)),
    [ren_Orden] int NOT NULL CONSTRAINT [DF__ind_rengl__ren_O__0ABD916C] DEFAULT ((0)),
    [ren_Estado] bit NOT NULL CONSTRAINT [DF__ind_rengl__ren_E__0BB1B5A5] DEFAULT ((1)),
    CONSTRAINT [PK_ind_renglones_retencion] PRIMARY KEY CLUSTERED ([ren_Id]),
    CONSTRAINT [UQ_renglon_modulo_anio_codigo] UNIQUE NONCLUSTERED ([ren_Modulo],[ren_Anio],[ren_Codigo]),
    CONSTRAINT [CK_renglon_modulo] CHECK ([ren_Modulo]='AUTORRETEICA' OR [ren_Modulo]='RETEICA')
    );
END
GO
IF OBJECT_ID(N'dbo.ind_reteica', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[ind_reteica] (
    [ret_Id] int IDENTITY(1,1) NOT NULL,
    [ret_NumeroDeclaracion] bigint NULL,
    [ret_IdContribuyente] int NOT NULL,
    [ret_Anio] int NOT NULL,
    [ret_Periodo] tinyint NOT NULL,
    [ret_FechaDeclaracion] date NULL,
    [ret_HoraDeclaracion] time(0) NULL,
    [ret_Corrige] bigint NULL,
    [ret_Estado] int NULL,
    [ret_FechaPresentacion] datetime NULL,
    [ret_ValorConcepto14] decimal(18,2) NOT NULL CONSTRAINT [DF__ind_retei__ret_V__5FD33367] DEFAULT ((0)),
    [ret_ValorConcepto15] decimal(18,2) NOT NULL CONSTRAINT [DF__ind_retei__ret_V__60C757A0] DEFAULT ((0)),
    [ret_ValorConcepto16] decimal(18,2) NOT NULL CONSTRAINT [DF__ind_retei__ret_V__61BB7BD9] DEFAULT ((0)),
    [ret_ValorConcepto17] decimal(18,2) NOT NULL CONSTRAINT [DF__ind_retei__ret_V__62AFA012] DEFAULT ((0)),
    [ret_Pagado] bit NOT NULL CONSTRAINT [DF__ind_retei__ret_P__63A3C44B] DEFAULT ((0)),
    [ret_FechaPago] date NULL,
    [ret_ValorPago] decimal(18,2) NULL,
    [ret_BancoPago] varchar(120) NULL,
    [ret_FechaCreador] datetime NULL,
    [ret_Creador] int NULL,
    [ret_PSE_RequestId] bigint NULL,
    [ret_PSE_Estado] varchar(20) NULL,
    [ret_PSE_FechaEstado] datetime2(7) NULL,
    [ret_PSE_Mensaje] varchar(300) NULL,
    [ret_FechaRealPago] datetime2(7) NULL,
    [ret_AnioPago] int NULL,
    [ret_RutaPago] varchar(200) NULL,
    [ret_IdBancoPago] int NULL,
    CONSTRAINT [PK_ind_reteica] PRIMARY KEY CLUSTERED ([ret_Id]),
    CONSTRAINT [CK_reteica_estado] CHECK ([ret_Estado] IS NULL OR ([ret_Estado]=(2) OR [ret_Estado]=(1) OR [ret_Estado]=(0))),
    CONSTRAINT [CK_reteica_periodo] CHECK ([ret_Periodo]>=(1) AND [ret_Periodo]<=(12))
    );
END
GO
IF OBJECT_ID(N'dbo.ind_reteica_actividades', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[ind_reteica_actividades] (
    [rea_Id] int IDENTITY(1,1) NOT NULL,
    [rea_IdReteica] int NOT NULL,
    [rea_IdActividad] int NOT NULL,
    [rea_BaseGravable] decimal(18,2) NOT NULL CONSTRAINT [DF__ind_retei__rea_B__68687968] DEFAULT ((0)),
    [rea_Tarifa] decimal(6,5) NOT NULL CONSTRAINT [DF__ind_retei__rea_T__695C9DA1] DEFAULT ((0)),
    [rea_ValorRetencion] decimal(18,2) NOT NULL CONSTRAINT [DF__ind_retei__rea_V__6A50C1DA] DEFAULT ((0)),
    [rea_Activo] bit NOT NULL CONSTRAINT [DF__ind_retei__rea_A__6B44E613] DEFAULT ((1)),
    [rea_FechaCreador] datetime NULL,
    [rea_ValorManual] bit NOT NULL CONSTRAINT [DF_rea_ValorManual] DEFAULT ((0)),
    CONSTRAINT [PK_ind_reteica_actividades] PRIMARY KEY CLUSTERED ([rea_Id])
    );
END
GO
IF OBJECT_ID(N'dbo.ind_rit_firmas', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.[ind_rit_firmas] (
    [rif_Id] int IDENTITY(1,1) NOT NULL,
    [rif_IdContribuyente] int NOT NULL,
    [rif_IdUsuario] int NOT NULL,
    [rif_NombreUsuario] nvarchar(500) NULL,
    [rif_EmailUsuario] nvarchar(500) NULL,
    [rif_Hash] varchar(64) NOT NULL,
    [rif_Opcion] int NULL,
    [rif_FechaHora] datetime NOT NULL CONSTRAINT [DF__ind_rit_f__rif_F__3429BB53] DEFAULT (getdate()),
    CONSTRAINT [PK__ind_rit___9ABFE725D7F4AB6B] PRIMARY KEY CLUSTERED ([rif_Id])
    );
END
GO
