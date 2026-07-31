USE [erpsoftweb]
GO
/****** Object:  Table [dbo].[conf_ciudades]    Script Date: 23/03/2026 5:46:51 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[conf_ciudades](
	[ciu_Id] [int] IDENTITY(1,1) NOT NULL,
	[ciu_CodigoDane] [varchar](10) NOT NULL,
	[ciu_Nombre] [varchar](150) NOT NULL,
	[ciu_Departamento] [varchar](150) NOT NULL,
	[ciu_Estado] [int] NOT NULL,
	[ciu_FechaCreacion] [datetime2](0) NOT NULL,
	[ciu_FechaActualizacion] [datetime2](0) NOT NULL,
PRIMARY KEY CLUSTERED 
(
	[ciu_Id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[conf_modulo]    Script Date: 23/03/2026 5:46:52 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[conf_modulo](
	[mod_Id] [int] IDENTITY(1,1) NOT NULL,
	[mod_Descripcion] [nvarchar](500) NOT NULL,
	[mod_Nombre] [nvarchar](500) NOT NULL,
	[mod_Icono] [nvarchar](500) NOT NULL,
	[mod_Url] [nvarchar](500) NOT NULL,
	[mod_Estado] [int] NOT NULL,
PRIMARY KEY CLUSTERED 
(
	[mod_Id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[conf_permisos]    Script Date: 23/03/2026 5:46:52 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[conf_permisos](
	[per_Id] [int] IDENTITY(1,1) NOT NULL,
	[per_IdSubmodulo] [int] NOT NULL,
	[per_IdRol] [int] NOT NULL,
	[per_IdModulo] [int] NOT NULL,
	[per_IdBoton] [int] NOT NULL,
	[per_Estado] [int] NOT NULL,
PRIMARY KEY CLUSTERED 
(
	[per_Id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[conf_rol]    Script Date: 23/03/2026 5:46:52 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[conf_rol](
	[rol_Id] [int] IDENTITY(1,1) NOT NULL,
	[rol_Nombre] [nvarchar](200) NOT NULL,
	[rol_Descripcion] [nvarchar](500) NULL,
	[rol_Estado] [int] NOT NULL,
	[rol_Fecha_Creacion] [datetime] NOT NULL,
PRIMARY KEY CLUSTERED 
(
	[rol_Id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[conf_submodulo]    Script Date: 23/03/2026 5:46:52 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[conf_submodulo](
	[subMod_Id] [int] IDENTITY(1,1) NOT NULL,
	[subMod_Nombre] [nvarchar](500) NOT NULL,
	[subMod_Descripcion] [nvarchar](500) NOT NULL,
	[subMod_IdModulo] [int] NOT NULL,
PRIMARY KEY CLUSTERED 
(
	[subMod_Id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[conf_usuarios]    Script Date: 23/03/2026 5:46:52 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[conf_usuarios](
	[usu_Id] [int] IDENTITY(1,1) NOT NULL,
	[usu_Nombres] [nvarchar](500) NOT NULL,
	[usu_Apellidos] [nvarchar](500) NULL,
	[usu_Usuario] [nvarchar](100) NOT NULL,
	[usu_IdTipoDocumento] [int] NULL,
	[usu_NumeroDocumento] [nvarchar](500) NULL,
	[usu_Correo] [nvarchar](500) NULL,
	[usu_Telefono] [nvarchar](50) NULL,
	[usu_Direccion] [nvarchar](500) NULL,
	[usu_Password] [nvarchar](500) NOT NULL,
	[usu_Rol] [int] NOT NULL,
	[usu_Estado] [int] NOT NULL,
	[usu_FechaCreacion] [datetime] NOT NULL,
	[usu_FechaActualizacion] [datetime] NOT NULL,
PRIMARY KEY CLUSTERED 
(
	[usu_Id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[ind_actividad_establecimiento]    Script Date: 23/03/2026 5:46:52 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[ind_actividad_establecimiento](
	[ace_Id] [int] IDENTITY(1,1) NOT NULL,
	[ace_IdCodigoActividad] [int] NOT NULL,
	[ace_IdEstablecimiento] [int] NOT NULL,
	[ace_Anio] [int] NOT NULL,
	[ace_FechaCreacion] [datetime2](0) NOT NULL,
	[ace_FechaActualizacion] [datetime2](0) NULL,
PRIMARY KEY CLUSTERED 
(
	[ace_Id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY],
 CONSTRAINT [UQ_Actividad_Establecimiento_Anio] UNIQUE NONCLUSTERED 
(
	[ace_IdCodigoActividad] ASC,
	[ace_IdEstablecimiento] ASC,
	[ace_Anio] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[ind_actividadescomercio]    Script Date: 23/03/2026 5:46:52 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[ind_actividadescomercio](
	[acc_Id] [int] IDENTITY(1,1) NOT NULL,
	[acc_Anio] [int] NULL,
	[acc_Codigo] [varchar](20) NOT NULL,
	[acc_Nombre] [varchar](500) NOT NULL,
	[acc_Tarifa] [decimal](4, 3) NOT NULL,
	[acc_GrupoTarifa] [int] NOT NULL,
	[acc_Exento] [bit] NOT NULL,
	[acc_Estado] [int] NOT NULL,
	[acc_FechaCreacion] [datetime2](0) NOT NULL,
	[acc_FechaActualizacion] [datetime2](0) NOT NULL,
 CONSTRAINT [PK_ind_actividadescomercio] PRIMARY KEY CLUSTERED 
(
	[acc_Id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY],
 CONSTRAINT [UQ_ind_actividadescomercio_Anio_Codigo] UNIQUE NONCLUSTERED 
(
	[acc_Anio] ASC,
	[acc_Codigo] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[ind_conceptos]    Script Date: 23/03/2026 5:46:52 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[ind_conceptos](
	[con_Id] [int] IDENTITY(1,1) NOT NULL,
	[con_Anio] [int] NULL,
	[con_Codigo] [varchar](50) NOT NULL,
	[con_Nombre] [varchar](500) NOT NULL,
	[con_Observaciones] [varchar](500) NOT NULL,
	[con_Estado] [int] NOT NULL,
	[con_FechaCreacion] [datetime2](0) NOT NULL,
	[con_FechaActualizacion] [datetime2](0) NOT NULL,
 CONSTRAINT [PK_ind_conceptos] PRIMARY KEY CLUSTERED 
(
	[con_Id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[ind_contribuyentes]    Script Date: 23/03/2026 5:46:52 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[ind_contribuyentes](
	[ind_Id] [int] IDENTITY(1,1) NOT NULL,
	[ind_NumeroIdentificacion] [int] NOT NULL,
	[ind_DV] [int] NOT NULL,
	[ind_IdTipoDocumento] [int] NOT NULL,
	[ind_PrimerNombre] [varchar](100) NOT NULL,
	[ind_SegundoNombre] [varchar](100) NULL,
	[ind_PrimerApellido] [varchar](100) NULL,
	[ind_SegundoApellido] [varchar](100) NULL,
	[ind_Direccion] [varchar](200) NOT NULL,
	[ind_IdCiudad] [int] NOT NULL,
	[ind_Persona] [int] NOT NULL,
	[ind_IdRegimen] [int] NULL,
	[ind_Telefono] [bigint] NULL,
	[ind_Email] [varchar](500) NULL,
	[ind_Estado] [int] NOT NULL,
	[ind_FechaCreacion] [datetime2](0) NOT NULL,
	[ind_FechaActualizacion] [datetime2](0) NOT NULL,
 CONSTRAINT [PK_ind_contribuyentes] PRIMARY KEY CLUSTERED 
(
	[ind_Id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[ind_declaraciones_ica]    Script Date: 23/03/2026 5:46:52 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[ind_declaraciones_ica](
	[dec_Id] [int] IDENTITY(1,1) NOT NULL,
	[dec_AnioDeclaracion] [int] NOT NULL,
	[dec_MesDeclaracion] [int] NOT NULL,
	[dec_NumeroDeclaracion] [bigint] NULL,
	[dec_IdContribuyente] [int] NOT NULL,
	[dec_IdEstablecimiento] [int] NOT NULL,
	[dec_FechaDeclaracion] [datetime2](7) NULL,
	[dec_HoraDeclaracion] [time](7) NULL,
	[dec_ValorPago] [decimal](18, 2) NULL,
	[dec_FechaPago] [datetime2](7) NULL,
	[dec_Pagado] [bit] NULL,
	[dec_BancoPago] [varchar](10) NULL,
	[dec_ValorConcepto1] [decimal](18, 2) NULL,
	[dec_ValorConcepto2] [decimal](18, 2) NULL,
	[dec_ValorConcepto3] [decimal](18, 2) NULL,
	[dec_ValorConcepto4] [decimal](18, 2) NULL,
	[dec_ValorConcepto5] [decimal](18, 2) NULL,
	[dec_ValorConcepto6] [decimal](18, 2) NULL,
	[dec_ValorConcepto7] [decimal](18, 2) NULL,
	[dec_ValorConcepto8] [decimal](18, 2) NULL,
	[dec_ValorConcepto9] [decimal](18, 2) NULL,
	[dec_ValorConcepto10] [decimal](18, 2) NULL,
	[dec_ValorConcepto11] [decimal](18, 2) NULL,
	[dec_ValorConcepto12] [decimal](18, 2) NULL,
	[dec_ValorConcepto13] [decimal](18, 2) NULL,
	[dec_ValorConcepto14] [decimal](18, 2) NULL,
	[dec_ValorConcepto15] [decimal](18, 2) NULL,
	[dec_ValorConcepto16] [decimal](18, 2) NULL,
	[dec_ValorConcepto17] [decimal](18, 2) NULL,
	[dec_ValorConcepto18] [decimal](18, 2) NULL,
	[dec_ValorConcepto19] [decimal](18, 2) NULL,
	[dec_ValorConcepto20] [decimal](18, 2) NULL,
	[dec_ValorConcepto21] [decimal](18, 2) NULL,
	[dec_ValorConcepto22] [decimal](18, 2) NULL,
	[dec_ValorConcepto23] [decimal](18, 2) NULL,
	[dec_ValorConcepto24] [decimal](18, 2) NULL,
	[dec_ValorConcepto25] [decimal](18, 2) NULL,
	[dec_ValorConcepto26] [decimal](18, 2) NULL,
	[dec_ValorConcepto27] [decimal](18, 2) NULL,
	[dec_ValorConcepto28] [decimal](18, 2) NULL,
	[dec_ValorConcepto29] [decimal](18, 2) NULL,
	[dec_ValorConcepto30] [decimal](18, 2) NULL,
	[dec_TotalCalculo] [decimal](18, 2) NULL,
	[dec_Creador] [varchar](50) NULL,
	[dec_FechaCreador] [datetime2](7) NULL,
	[dec_Modificador] [varchar](50) NULL,
	[dec_FechaModificador] [datetime2](7) NULL,
	[dec_TotalIngresos] [decimal](18, 2) NULL,
	[dec_IngresosFueraMunicipio] [decimal](18, 2) NULL,
	[dec_IngresosDevoluciones] [decimal](18, 2) NULL,
	[dec_IngresosExportaciones] [decimal](18, 2) NULL,
	[dec_IngresosVentas] [decimal](18, 2) NULL,
	[dec_IngresosActividades] [decimal](18, 2) NULL,
	[dec_IngresosOtrasActividades] [decimal](18, 2) NULL,
	[dec_DeclaracionCorrige] [bigint] NULL,
	[dec_VigenciaActual] [int] NULL,
	[dec_AnioPago] [int] NULL,
	[dec_FechaIntereses] [datetime2](7) NULL,
	[dec_OpcionUso] [varchar](1) NULL,
	[dec_BaseGravable] [decimal](18, 2) NULL,
	[dec_CuentaDebito] [varchar](70) NULL,
	[dec_FechaRealPago] [datetime2](7) NULL,
	[dec_RutaDeclaracion] [varchar](200) NULL,
	[dec_RutaPago] [varchar](200) NULL,
PRIMARY KEY CLUSTERED 
(
	[dec_Id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[ind_declaraciones_ica_actividades]    Script Date: 23/03/2026 5:46:52 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[ind_declaraciones_ica_actividades](
	[dia_Id] [int] IDENTITY(1,1) NOT NULL,
	[dia_IdDeclaracion] [int] NOT NULL,
	[dia_IdActividad] [int] NOT NULL,
	[dia_BaseGravable] [decimal](18, 2) NOT NULL,
	[dia_Tarifa] [decimal](4, 3) NOT NULL,
	[dia_ValorImpuesto] [decimal](18, 2) NOT NULL,
	[dia_Activo] [bit] NOT NULL,
	[dia_FechaCreador] [datetime2](7) NOT NULL,
	[dia_FechaModificador] [datetime2](7) NULL,
 CONSTRAINT [PK_ind_declaraciones_ica_actividades] PRIMARY KEY CLUSTERED 
(
	[dia_Id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[ind_establecimientos]    Script Date: 23/03/2026 5:46:52 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[ind_establecimientos](
	[est_Id] [int] IDENTITY(1,1) NOT NULL,
	[est_Codigo] [int] NULL,
	[est_IdContribuyente] [int] NOT NULL,
	[est_Nombre] [varchar](255) NOT NULL,
	[est_Direccion] [varchar](150) NULL,
	[est_Pais] [varchar](5) NULL,
	[est_Departamento] [varchar](5) NULL,
	[est_Ciudad] [varchar](5) NULL,
	[est_Barrio] [varchar](150) NULL,
	[est_Correo] [varchar](150) NULL,
	[est_Activos] [float] NULL,
	[est_Local] [int] NULL,
	[est_Matricula] [varchar](50) NULL,
	[est_Fecha_matricula] [datetime] NULL,
	[est_Fecha_inscripcion] [datetime] NULL,
	[est_Fecha_inicio] [datetime] NULL,
	[est_Activo] [int] NOT NULL,
	[est_Observacion_cierre] [varchar](255) NULL,
	[est_Fecha_cierre] [datetime] NULL,
	[est_Resolucion_cierre] [varchar](50) NULL,
	[est_Area] [varchar](20) NULL,
	[est_Creador] [varchar](30) NULL,
	[est_Fecha_creador] [datetime] NULL,
	[est_Excento_avisos] [int] NOT NULL,
	[est_Ultimo_ano_pago] [int] NULL,
	[est_Estado_registro] [varchar](1) NULL,
	[est_Local_municipio] [int] NOT NULL,
	[est_Codigo_catastral] [varchar](30) NULL,
	[est_Opcion_uso] [varchar](1) NULL,
	[est_Causal] [varchar](1) NULL,
	[est_Cedula_representante] [varchar](20) NULL,
	[est_Nombre_representante] [varchar](100) NULL,
	[est_Email_representante] [varchar](150) NULL,
	[est_Cedula_contador] [varchar](20) NULL,
	[est_Nombre_contador] [varchar](100) NULL,
	[est_Tarjeta_profesional] [varchar](50) NULL,
	[est_Cedula_revisor] [varchar](20) NULL,
	[est_Nombre_revisor] [varchar](100) NULL,
	[est_Ruta_anexos] [varchar](150) NULL,
	[est_Tarjeta_profesional_revisor] [varchar](50) NULL,
	[est_Exento] [int] NOT NULL,
	[est_Rut] [varchar](6) NULL,
	[est_Fecha_actividad] [datetime] NULL,
	[est_Rut_segundo] [varchar](6) NULL,
	[est_Rut_tercero] [varchar](6) NULL,
	[est_Ind_camara_comercio] [int] NOT NULL,
	[est_Autorizacion] [int] NOT NULL,
 CONSTRAINT [PK_ind_establecimientos] PRIMARY KEY CLUSTERED 
(
	[est_Id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[ind_grupotarifa]    Script Date: 23/03/2026 5:46:52 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[ind_grupotarifa](
	[gru_Id] [int] IDENTITY(1,1) NOT NULL,
	[gru_Nombre] [varchar](500) NOT NULL,
	[gru_Codigo] [varchar](500) NOT NULL,
	[gru_Estado] [int] NOT NULL,
	[gru_FechaCreacion] [datetime2](0) NOT NULL,
	[gru_FechaActualizacion] [datetime2](0) NOT NULL,
 CONSTRAINT [PK_ind_grupotarifa] PRIMARY KEY CLUSTERED 
(
	[gru_Id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
ALTER TABLE [dbo].[conf_ciudades] ADD  DEFAULT ((1)) FOR [ciu_Estado]
GO
ALTER TABLE [dbo].[conf_ciudades] ADD  DEFAULT (getdate()) FOR [ciu_FechaCreacion]
GO
ALTER TABLE [dbo].[conf_ciudades] ADD  DEFAULT (getdate()) FOR [ciu_FechaActualizacion]
GO
ALTER TABLE [dbo].[conf_rol] ADD  DEFAULT ((1)) FOR [rol_Estado]
GO
ALTER TABLE [dbo].[conf_rol] ADD  CONSTRAINT [DF_conf_rol_rol_Fecha_Creacion]  DEFAULT (getdate()) FOR [rol_Fecha_Creacion]
GO
ALTER TABLE [dbo].[conf_usuarios] ADD  DEFAULT ((1)) FOR [usu_Estado]
GO
ALTER TABLE [dbo].[conf_usuarios] ADD  DEFAULT (getdate()) FOR [usu_FechaCreacion]
GO
ALTER TABLE [dbo].[conf_usuarios] ADD  DEFAULT (getdate()) FOR [usu_FechaActualizacion]
GO
ALTER TABLE [dbo].[ind_actividad_establecimiento] ADD  DEFAULT (getdate()) FOR [ace_FechaCreacion]
GO
ALTER TABLE [dbo].[ind_actividadescomercio] ADD  DEFAULT ((0)) FOR [acc_Exento]
GO
ALTER TABLE [dbo].[ind_actividadescomercio] ADD  DEFAULT ((1)) FOR [acc_Estado]
GO
ALTER TABLE [dbo].[ind_actividadescomercio] ADD  DEFAULT (sysdatetime()) FOR [acc_FechaCreacion]
GO
ALTER TABLE [dbo].[ind_actividadescomercio] ADD  DEFAULT (sysdatetime()) FOR [acc_FechaActualizacion]
GO
ALTER TABLE [dbo].[ind_conceptos] ADD  DEFAULT (sysdatetime()) FOR [con_FechaCreacion]
GO
ALTER TABLE [dbo].[ind_conceptos] ADD  DEFAULT (sysdatetime()) FOR [con_FechaActualizacion]
GO
ALTER TABLE [dbo].[ind_contribuyentes] ADD  DEFAULT (sysdatetime()) FOR [ind_FechaCreacion]
GO
ALTER TABLE [dbo].[ind_contribuyentes] ADD  DEFAULT (sysdatetime()) FOR [ind_FechaActualizacion]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_ValorPago]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_Pagado]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_ValorConcepto1]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_ValorConcepto2]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_ValorConcepto3]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_ValorConcepto4]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_ValorConcepto5]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_ValorConcepto6]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_ValorConcepto7]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_ValorConcepto8]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_ValorConcepto9]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_ValorConcepto10]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_ValorConcepto11]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_ValorConcepto12]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_ValorConcepto13]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_ValorConcepto14]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_ValorConcepto15]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_ValorConcepto16]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_ValorConcepto17]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_ValorConcepto18]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_ValorConcepto19]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_ValorConcepto20]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_ValorConcepto21]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_ValorConcepto22]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_ValorConcepto23]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_ValorConcepto24]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_ValorConcepto25]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_ValorConcepto26]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_ValorConcepto27]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_ValorConcepto28]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_ValorConcepto29]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_ValorConcepto30]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_TotalCalculo]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_TotalIngresos]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_IngresosFueraMunicipio]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_IngresosDevoluciones]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_IngresosExportaciones]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_IngresosVentas]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_IngresosActividades]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_IngresosOtrasActividades]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] ADD  DEFAULT ((0)) FOR [dec_BaseGravable]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica_actividades] ADD  DEFAULT ((0)) FOR [dia_BaseGravable]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica_actividades] ADD  DEFAULT ((0)) FOR [dia_Tarifa]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica_actividades] ADD  DEFAULT ((0)) FOR [dia_ValorImpuesto]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica_actividades] ADD  DEFAULT ((1)) FOR [dia_Activo]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica_actividades] ADD  DEFAULT (getdate()) FOR [dia_FechaCreador]
GO
ALTER TABLE [dbo].[ind_establecimientos] ADD  DEFAULT ((0)) FOR [est_Activos]
GO
ALTER TABLE [dbo].[ind_establecimientos] ADD  DEFAULT ((-1)) FOR [est_Activo]
GO
ALTER TABLE [dbo].[ind_establecimientos] ADD  DEFAULT ((0)) FOR [est_Excento_avisos]
GO
ALTER TABLE [dbo].[ind_establecimientos] ADD  DEFAULT ((0)) FOR [est_Local_municipio]
GO
ALTER TABLE [dbo].[ind_establecimientos] ADD  DEFAULT ((0)) FOR [est_Exento]
GO
ALTER TABLE [dbo].[ind_establecimientos] ADD  DEFAULT ((0)) FOR [est_Ind_camara_comercio]
GO
ALTER TABLE [dbo].[ind_establecimientos] ADD  DEFAULT ((0)) FOR [est_Autorizacion]
GO
ALTER TABLE [dbo].[ind_grupotarifa] ADD  DEFAULT (sysdatetime()) FOR [gru_FechaCreacion]
GO
ALTER TABLE [dbo].[ind_grupotarifa] ADD  DEFAULT (sysdatetime()) FOR [gru_FechaActualizacion]
GO
ALTER TABLE [dbo].[conf_permisos]  WITH CHECK ADD FOREIGN KEY([per_IdSubmodulo])
REFERENCES [dbo].[conf_submodulo] ([subMod_Id])
GO
ALTER TABLE [dbo].[conf_permisos]  WITH CHECK ADD FOREIGN KEY([per_IdRol])
REFERENCES [dbo].[conf_rol] ([rol_Id])
GO
ALTER TABLE [dbo].[conf_permisos]  WITH CHECK ADD FOREIGN KEY([per_IdModulo])
REFERENCES [dbo].[conf_modulo] ([mod_Id])
GO
ALTER TABLE [dbo].[conf_submodulo]  WITH CHECK ADD FOREIGN KEY([subMod_IdModulo])
REFERENCES [dbo].[conf_modulo] ([mod_Id])
GO
ALTER TABLE [dbo].[conf_usuarios]  WITH CHECK ADD  CONSTRAINT [FK_conf_usuario_conf_rol] FOREIGN KEY([usu_Rol])
REFERENCES [dbo].[conf_rol] ([rol_Id])
GO
ALTER TABLE [dbo].[conf_usuarios] CHECK CONSTRAINT [FK_conf_usuario_conf_rol]
GO
ALTER TABLE [dbo].[ind_actividad_establecimiento]  WITH CHECK ADD  CONSTRAINT [FK_ActividadEstablecimiento_Actividad] FOREIGN KEY([ace_IdCodigoActividad])
REFERENCES [dbo].[ind_actividadescomercio] ([acc_Id])
GO
ALTER TABLE [dbo].[ind_actividad_establecimiento] CHECK CONSTRAINT [FK_ActividadEstablecimiento_Actividad]
GO
ALTER TABLE [dbo].[ind_actividad_establecimiento]  WITH CHECK ADD  CONSTRAINT [FK_ActividadEstablecimiento_Establecimiento] FOREIGN KEY([ace_IdEstablecimiento])
REFERENCES [dbo].[ind_establecimientos] ([est_Id])
GO
ALTER TABLE [dbo].[ind_actividad_establecimiento] CHECK CONSTRAINT [FK_ActividadEstablecimiento_Establecimiento]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica]  WITH CHECK ADD  CONSTRAINT [FK_dec_contribuyente] FOREIGN KEY([dec_IdContribuyente])
REFERENCES [dbo].[ind_contribuyentes] ([ind_Id])
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] CHECK CONSTRAINT [FK_dec_contribuyente]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica]  WITH CHECK ADD  CONSTRAINT [FK_dec_establecimiento] FOREIGN KEY([dec_IdEstablecimiento])
REFERENCES [dbo].[ind_establecimientos] ([est_Id])
GO
ALTER TABLE [dbo].[ind_declaraciones_ica] CHECK CONSTRAINT [FK_dec_establecimiento]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica_actividades]  WITH CHECK ADD  CONSTRAINT [FK_dia_actividad] FOREIGN KEY([dia_IdActividad])
REFERENCES [dbo].[ind_actividadescomercio] ([acc_Id])
GO
ALTER TABLE [dbo].[ind_declaraciones_ica_actividades] CHECK CONSTRAINT [FK_dia_actividad]
GO
ALTER TABLE [dbo].[ind_declaraciones_ica_actividades]  WITH CHECK ADD  CONSTRAINT [FK_dia_declaracion] FOREIGN KEY([dia_IdDeclaracion])
REFERENCES [dbo].[ind_declaraciones_ica] ([dec_Id])
GO
ALTER TABLE [dbo].[ind_declaraciones_ica_actividades] CHECK CONSTRAINT [FK_dia_declaracion]
GO
/****** Object:  StoredProcedure [dbo].[sp_buscar_predios]    Script Date: 23/03/2026 5:46:52 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE PROCEDURE [dbo].[sp_buscar_predios]
    @dato NVARCHAR(100) = NULL
AS
BEGIN
    SET NOCOUNT ON;

    -- 🔹 Tabla temporal solo si hay filtro
    DECLARE @pat NVARCHAR(120) = NULL;
    IF (ISNULL(@dato, '') <> '') SET @pat = '%' + @dato + '%';

    ---------------------------------------------------
    -- 1️⃣ Trae propietarios con CROSS APPLY (evita subconsultas repetitivas)
    ---------------------------------------------------
    SELECT TOP (5)
        p.codigo_predio,
        UPPER(LTRIM(RTRIM(ISNULL(pr.nombre, '')))) AS nombre,
        LTRIM(RTRIM(p.direccion)) AS direccion,
        CASE 
            WHEN EXISTS (
                SELECT 1 
                FROM [erpsofts_paipa_test].dbo.predios_pagos AS pg
                WHERE pg.id_predio = p.id
                  AND ISNULL(pg.pagado, 0) <> -1
            ) THEN 1 ELSE 0
        END AS tiene_deuda
    FROM [erpsofts_paipa_test].dbo.predios AS p
    CROSS APPLY (
        SELECT TOP 1 pr.nombre
        FROM [erpsofts_paipa_test].dbo.predios_propietarios AS rel
        INNER JOIN [erpsofts_paipa_test].dbo.propietarios AS pr
            ON pr.id = rel.id_propietario
        WHERE rel.id_predio = p.id
        ORDER BY pr.id ASC
    ) AS pr
    WHERE
        @pat IS NULL
        OR p.codigo_predio LIKE @pat
        OR pr.nombre LIKE @pat
        OR p.direccion LIKE @pat
    ORDER BY p.codigo_predio;
END



/*
ALTER PROCEDURE [dbo].[sp_buscar_predios] --'0000000000040342000000000'
    @dato NVARCHAR(100) = NULL
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @t TABLE (
        codigo_predio NVARCHAR(50),
        nombre NVARCHAR(200),
        direccion NVARCHAR(300),
        tiene_deuda BIT
    );

    ---------------------------------------------------
    -- 🔹 CONSULTA REAL A LA BASE DE DATOS ERPSOFTS_PAIPA
    ---------------------------------------------------
    INSERT INTO @t (codigo_predio, nombre, direccion, tiene_deuda)
    SELECT 
        p.codigo_predio,
		UPPER(LTRIM(RTRIM(
            ISNULL((
                SELECT TOP 1 pr.nombre
                FROM [erpsofts_paipa_test].dbo.predios_propietarios AS rel2
                INNER JOIN [erpsofts_paipa_test].dbo.propietarios AS pr
                    ON pr.id = rel2.id_propietario
                WHERE rel2.id_predio = p.id
                ORDER BY pr.id ASC
            ), '')
        ))) AS nombre,

        LTRIM(RTRIM(p.direccion)) AS direccion,
        CASE 
            WHEN EXISTS (
                SELECT 1 
                FROM [erpsofts_paipa_test].dbo.predios_pagos AS pg
                WHERE pg.id_predio = p.id
                  AND ISNULL(pg.pagado, 0) <> -1
            ) THEN 1   -- Tiene deuda
            ELSE 0     -- Está al día
        END AS tiene_deuda
    FROM [erpsofts_paipa_test].dbo.predios AS p
    

    ---------------------------------------------------
    -- 🔹 FILTRO DE BÚSQUEDA
    ---------------------------------------------------
    IF (@dato IS NULL OR LTRIM(RTRIM(@dato)) = '')
    BEGIN
        SELECT codigo_predio, nombre, direccion, tiene_deuda
        FROM @t;
        RETURN;
    END

    DECLARE @pat NVARCHAR(120) = '%' + @dato + '%';

    SELECT codigo_predio, nombre, direccion, tiene_deuda
    FROM @t
    WHERE codigo_predio LIKE @pat
       OR nombre LIKE @pat
       OR direccion LIKE @pat;
END

*/
GO
/****** Object:  StoredProcedure [dbo].[sp_calculo_comercio]    Script Date: 23/03/2026 5:46:52 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO


CREATE PROCEDURE [dbo].[sp_calculo_comercio] 
	@ANO_DECLARACION INT,	
    @MES_DECLARACION INT,
    @NUMERO_DECLARACION BIGINT,
    @FECHA_LIMITE DATETIME = null
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE 
        @CODIGO INT,
        @FORMULA NVARCHAR(MAX),
        @SQL NVARCHAR(MAX),
        @CAMPO_DESTINO NVARCHAR(50);

    IF @FECHA_LIMITE IS NULL
       SET @FECHA_LIMITE = CONVERT(DATETIME, '2025-11-11', 120);
        
--calcula el valor del impuesto
UPDATE di
SET di.dec_valorconcepto1 = ROUND(t.VIMPUESTO, -3)
FROM ind_declaraciones_ica di
CROSS APPLY (
    SELECT SUM(da.dia_ValorImpuesto) AS VIMPUESTO
    FROM ind_declaraciones_ica_actividades da
    WHERE da.dia_iddeclaracion = di.dec_id
    AND da.dia_Activo NOT IN (0)
) t
WHERE di.dec_AnioDeclaracion = @ANO_DECLARACION
  AND di.dec_MesDeclaracion = @MES_DECLARACION
  AND di.dec_NumeroDeclaracion = @NUMERO_DECLARACION;
  
   DECLARE CURSOR_CONCEPTOS CURSOR FOR
        SELECT con_Codigo, con_Observaciones
        FROM ind_Conceptos
        WHERE con_Anio = @ANO_DECLARACION
        ORDER BY con_Codigo;

    OPEN CURSOR_CONCEPTOS;
    FETCH NEXT FROM CURSOR_CONCEPTOS INTO @CODIGO, @FORMULA;

    WHILE @@FETCH_STATUS = 0
    BEGIN
        -- Construimos el nombre del campo destino (VALOR_CONCEPTO#)
        SET @CAMPO_DESTINO = 'dec_ValorConcepto' + CAST(@CODIGO AS VARCHAR(10));

        -- Armamos el SQL dinámico que actualiza ese campo
        SET @SQL = N'
        UPDATE ep
        SET ep.' + QUOTENAME(@CAMPO_DESTINO) + N' = (' + @FORMULA + N')
        FROM ind_declaraciones_ica ep
        WHERE ep.dec_AnioDeclaracion= @ANO
          AND ep.dec_MesDeclaracion= @MES
          AND ep.dec_NumeroDeclaracion= @NUMERO;';

        -- Ejecutamos el SQL dinámico con parámetros
        EXEC sp_executesql 
            @SQL,
            N'@ANO INT, @MES INT, @NUMERO FLOAT, @FECHA_LIMITE DATETIME',
            @ANO = @ANO_DECLARACION,
            @MES = @MES_DECLARACION,
            @NUMERO = @NUMERO_DECLARACION,
            @FECHA_LIMITE = @FECHA_LIMITE;

 --       PRINT 'Actualizado: ' + @CAMPO_DESTINO + ' usando fórmula: ' + @FORMULA;

        FETCH NEXT FROM CURSOR_CONCEPTOS INTO @CODIGO, @FORMULA;
    END;

    CLOSE CURSOR_CONCEPTOS;
    DEALLOCATE CURSOR_CONCEPTOS;
END;
GO
