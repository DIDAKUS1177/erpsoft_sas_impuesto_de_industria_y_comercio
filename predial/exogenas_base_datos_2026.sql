-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 28-04-2026 a las 04:30:01
-- Versión del servidor: 10.4.27-MariaDB
-- Versión de PHP: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `exogenas`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `conf_modulo`
--

CREATE TABLE `conf_modulo` (
  `mod_Id` int(11) NOT NULL,
  `mod_Descripcion` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `mod_Nombre` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `mod_Icono` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `mod_Url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `mod_Estado` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `conf_modulo`
--

INSERT INTO `conf_modulo` (`mod_Id`, `mod_Descripcion`, `mod_Nombre`, `mod_Icono`, `mod_Url`, `mod_Estado`) VALUES
(1, 'ROLES', 'ROLES', 'mdi mdi-account-group', 'rol.php', 1),
(2, 'USUARIOS', 'USUARIOS', 'mdi mdi-account', 'usuario.php', 1),
(3, 'BODEGA', 'BODEGA', 'mdi mdi-account', 'bodega.php', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `conf_permisos`
--

CREATE TABLE `conf_permisos` (
  `per_Id` int(11) NOT NULL,
  `per_IdSubmodulo` int(11) NOT NULL,
  `per_IdRol` int(11) NOT NULL,
  `per_IdModulo` int(11) NOT NULL,
  `per_IdBoton` int(11) NOT NULL,
  `per_Estado` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `conf_permisos`
--

INSERT INTO `conf_permisos` (`per_Id`, `per_IdSubmodulo`, `per_IdRol`, `per_IdModulo`, `per_IdBoton`, `per_Estado`) VALUES
(51, 1, 2, 1, 11, 1),
(52, 6, 2, 2, 26, 1),
(53, 10, 2, 3, 310, 1),
(54, 11, 2, 3, 311, 1),
(55, 12, 2, 3, 312, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `conf_rol`
--

CREATE TABLE `conf_rol` (
  `rol_Id` int(11) NOT NULL,
  `rol_Nombre` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `rol_Estado` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `conf_rol`
--

INSERT INTO `conf_rol` (`rol_Id`, `rol_Nombre`, `rol_Estado`) VALUES
(1, 'ADMINISTRADOR', 1),
(2, 'GESTIÓN', 1),
(3, 'e', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `conf_submodulo`
--

CREATE TABLE `conf_submodulo` (
  `subMod_Id` int(11) NOT NULL,
  `subMod_Nombre` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `subMod_Descripcion` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `subMod_IdModulo` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `conf_submodulo`
--

INSERT INTO `conf_submodulo` (`subMod_Id`, `subMod_Nombre`, `subMod_Descripcion`, `subMod_IdModulo`) VALUES
(1, 'VER ROL', 'VER ROL', 1),
(2, 'REGISTRAR ROL', 'REGISTRAR ROL', 1),
(3, 'EDITAR ROL', 'EDITAR ROL', 1),
(4, 'ACTIVAR/INACTIVAR ROL', 'ACTIVAR/INACTIVAR ROL', 1),
(5, 'ASIGNAR/NEGAR PERMISOS AL ROL', 'ASIGNAR/NEGAR PERMISOS AL ROL', 1),
(6, 'VER USUARIO', 'VER USUARIO', 2),
(7, 'CREAR USUARIO', 'CREAR USUARIO', 2),
(8, 'EDITAR USUARIO', 'EDITAR USUARIO', 2),
(9, 'ACTIVAR/INACTIVAR USUARIO', 'ACTIVAR/INACTIVAR USUARIO', 2),
(10, 'VER BODEGA', 'VER BODEGA', 3),
(11, 'CREAR BODEGA', 'CREAR BODEGA', 3),
(12, 'EDITAR BODEGA', 'EDITAR BODEGA', 3),
(13, 'ACTIVAR/INACTIVAR BODEGA', 'ACTIVAR/INACTIVAR BODEGA', 3);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `conf_usuario`
--

CREATE TABLE `conf_usuario` (
  `usu_Id` int(11) NOT NULL,
  `usu_Nombre` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `usu_Usuario` varchar(100) NOT NULL,
  `usu_NumeroDocumento` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `usu_Correo` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `usu_Password` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `usu_Rol` int(11) NOT NULL,
  `usu_Estado` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `conf_usuario`
--

INSERT INTO `conf_usuario` (`usu_Id`, `usu_Nombre`, `usu_Usuario`, `usu_NumeroDocumento`, `usu_Correo`, `usu_Password`, `usu_Rol`, `usu_Estado`) VALUES
(1, 'digitsoft', 'digitsoft', '74381687', 'soporte@digitsoft.com.co', 'adbaa8a2b601d57e8e514479d07091d47c96dc75', 1, 1),
(2, 'administrador', 'administrador', '11111111', 'admin@gmail.co', 'db7db5897571e433fd1ebc420d06eb91142aaffb', 1, 1),
(3, 'Juan Gabriel Suarez Avendaño', 'juan', '123456789', 'juan@gmail.com', 'fb07285768de67c86c692a9e327e52e1997bf4e9', 1, 1),
(7, 'pruebas1', 'pruebas1', '1000000000', 'pruebas1@email.com', '160bdfd31600203d94d4367e48efbd16bce5eb94', 2, 1),
(8, 'pruebas2', 'pruebas2', '1000000001', 'pruebas2@email.com', 'a64c9dd9f8adbfbe25473dae4db9ad96949c9d6d', 2, 1),
(9, 'pruebas3', 'pruebas3', '1000000002', 'pruebas3@email.com', '40350ae49d80ac8230a9019d9a4e0daa047e07cf', 2, 1),
(10, 'Cristian', '1052400234', '1052400234', 'cristianmd9@gmail.com', '40bd001563085fc35165329ea1ff5c5ecbdbbeef', 2, 1),
(11, 'asdasd', 'asd', '123', 'paulaandreita2009@live.com', '40bd001563085fc35165329ea1ff5c5ecbdbbeef', 2, 1),
(12, 'Lizeth Lorena Gómez Herrera', 'qe', '132', 'brayan@distribucioneselmago.com', '40bd001563085fc35165329ea1ff5c5ecbdbbeef', 2, 1),
(13, 'asdsad', 'asdas', '2312', 'vendedor@gmail.com', '40bd001563085fc35165329ea1ff5c5ecbdbbeef', 2, 1),
(14, 'sdfsdf', 'ads', '213', 'paulaadasdndreita2009@live.com', 'f10e2821bbbea527ea02200352313bc059445190', 2, 1),
(15, 'Paula Sanchez Camargo', 'sds', '234', 'vendedoasdr@gmail.com', '40bd001563085fc35165329ea1ff5c5ecbdbbeef', 2, 1),
(16, 'DUITAMA', 'qwe', '2342423', 'paulaandreita2asdasd009@live.com', 'cb4e5208b4cd87268b208e49452ed6e89a68e0b8', 2, 1),
(17, 'DUITAMA', 'qweqweqw', '32423432', 'vendasdasasdasdedor@gmail.com', '0ec09ef9836da03f1add21e3ef607627e687e790', 2, 1),
(18, 'DUITAMA', 'qweqweqwq', '324234321', 'vendasdaasasdasdedor@gmail.com', '0ec09ef9836da03f1add21e3ef607627e687e790', 2, 1),
(19, 'Cristian Manrique', 'admin', '10524002344', 'cristianmd99@gmail.com', 'd033e22ae348aeb5660fc2140aec35850c4da997', 2, 1),
(20, 'aa', '123yy', '1111', 'cristianmd91@gmail.com', '40bd001563085fc35165329ea1ff5c5ecbdbbeef', 2, 1),
(21, 'qqqq', '111', '1111111', 'cristia1nmd9@gmail.com', '6216f8a75fd5bb3d5f22b6f9958cdede3fc086c2', 2, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documento_exogenas`
--

CREATE TABLE `documento_exogenas` (
  `doc_Id` int(11) NOT NULL,
  `doc_IdDocumento` int(11) NOT NULL,
  `doc_IdExogena` int(11) NOT NULL,
  `doc_ClienteAdquiriente` int(11) NOT NULL,
  `doc_DicClienteAdquiriente` int(11) NOT NULL,
  `doc_NombreClienteAdquiriente` int(11) NOT NULL,
  `doc_EmailClienteAdquiriente` int(11) NOT NULL,
  `doc_TelefonoClienteAdquiriente` int(11) NOT NULL,
  `doc_DireccionClienteAdquiriente` int(11) NOT NULL,
  `doc_IdCodigoMunicipioClienteAdquiriente` int(11) NOT NULL,
  `doc_ValorClienteAdquiriente` int(11) DEFAULT NULL,
  `doc_DevolucionesClienteAdquiriente` int(11) DEFAULT NULL,
  `doc_RetencionesClienteAdquiriente` int(11) DEFAULT NULL,
  `doc_AutoretencionClienteAdquiriente` int(11) DEFAULT NULL,
  `doc_IngresosClienteAdquiriente` int(11) DEFAULT NULL,
  `doc_DomicilioClienteAdquiriente` varchar(500) DEFAULT NULL,
  `doc_NumeroContratoClienteAdquiriente` int(11) DEFAULT NULL,
  `doc_FechaServicioClienteAdquiriente` date DEFAULT NULL,
  `doc_CapacidadKMClienteAdquiriente` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `exogenas`
--

CREATE TABLE `exogenas` (
  `exo_Id` int(11) NOT NULL,
  `exo_IdUsuario` int(11) NOT NULL,
  `exo_IdTipoDocumento` int(11) NOT NULL,
  `exo_Anio` int(11) NOT NULL,
  `exo_Observaciones` varchar(500) DEFAULT NULL,
  `exo_estado` int(11) NOT NULL,
  `exo_FechaCreacion` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `exogenas`
--

INSERT INTO `exogenas` (`exo_Id`, `exo_IdUsuario`, `exo_IdTipoDocumento`, `exo_Anio`, `exo_Observaciones`, `exo_estado`, `exo_FechaCreacion`) VALUES
(28, 3, 4, 2024, 'qqqq', 1, '2026-04-27 21:22:13'),
(29, 3, 4, 2025, 'asd', 1, '2026-04-27 21:28:05');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inv_bodega`
--

CREATE TABLE `inv_bodega` (
  `bod_Id` int(11) NOT NULL,
  `bod_IdTipo` int(11) NOT NULL COMMENT '1:Producto ; 2:Insumos',
  `bod_Nombre` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `bod_Estado` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `inv_bodega`
--

INSERT INTO `inv_bodega` (`bod_Id`, `bod_IdTipo`, `bod_Nombre`, `bod_Estado`) VALUES
(1, 1, 'PFE1 - INGRESOS ORDINARIOS POR VENTA DE BIENES Y SERVICIOS.', 1),
(2, 1, 'PFE2 - INGRESOS ORDINARIOS POR PRESTACION DE SERVICIOS (TELEVISIÓN,INTERNET, TELEFONIA FIJA, <br>TELEFONIA MOVIL, NAVEGACIÓN MOVIL Y/O SERVICIO DE DATOS', 2),
(3, 1, 'PFE3 - INGRESOS ORDINARIOS POR VENTA DE BIENES Y SERVICIOS <br>(COMERCIALIZACIÓN DEL SERVICIO DOMICILIARIO DE ENERGIA)', 3),
(4, 1, 'PFE4 - INGRESOS OBTENIDOS FUERA DE PAIPA', 4),
(5, 1, 'PFE5 - COMPRA DE BIENES Y SERVICIOS EN CONDICIÓN DE ADQUIRIENTE', 5);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `conf_modulo`
--
ALTER TABLE `conf_modulo`
  ADD PRIMARY KEY (`mod_Id`);

--
-- Indices de la tabla `conf_permisos`
--
ALTER TABLE `conf_permisos`
  ADD PRIMARY KEY (`per_Id`),
  ADD KEY `per_IdSubmodulo` (`per_IdSubmodulo`),
  ADD KEY `per_IdRol` (`per_IdRol`),
  ADD KEY `per_IdModulo` (`per_IdModulo`);

--
-- Indices de la tabla `conf_rol`
--
ALTER TABLE `conf_rol`
  ADD PRIMARY KEY (`rol_Id`);

--
-- Indices de la tabla `conf_submodulo`
--
ALTER TABLE `conf_submodulo`
  ADD PRIMARY KEY (`subMod_Id`),
  ADD KEY `subMod_Modulo` (`subMod_IdModulo`);

--
-- Indices de la tabla `conf_usuario`
--
ALTER TABLE `conf_usuario`
  ADD PRIMARY KEY (`usu_Id`),
  ADD KEY `usu_IdRol` (`usu_Rol`);

--
-- Indices de la tabla `documento_exogenas`
--
ALTER TABLE `documento_exogenas`
  ADD PRIMARY KEY (`doc_Id`);

--
-- Indices de la tabla `exogenas`
--
ALTER TABLE `exogenas`
  ADD PRIMARY KEY (`exo_Id`);

--
-- Indices de la tabla `inv_bodega`
--
ALTER TABLE `inv_bodega`
  ADD PRIMARY KEY (`bod_Id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `conf_modulo`
--
ALTER TABLE `conf_modulo`
  MODIFY `mod_Id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT de la tabla `conf_permisos`
--
ALTER TABLE `conf_permisos`
  MODIFY `per_Id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT de la tabla `conf_rol`
--
ALTER TABLE `conf_rol`
  MODIFY `rol_Id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `conf_submodulo`
--
ALTER TABLE `conf_submodulo`
  MODIFY `subMod_Id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=112;

--
-- AUTO_INCREMENT de la tabla `conf_usuario`
--
ALTER TABLE `conf_usuario`
  MODIFY `usu_Id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de la tabla `documento_exogenas`
--
ALTER TABLE `documento_exogenas`
  MODIFY `doc_Id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `exogenas`
--
ALTER TABLE `exogenas`
  MODIFY `exo_Id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT de la tabla `inv_bodega`
--
ALTER TABLE `inv_bodega`
  MODIFY `bod_Id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `conf_permisos`
--
ALTER TABLE `conf_permisos`
  ADD CONSTRAINT `conf_permisos_ibfk_1` FOREIGN KEY (`per_IdModulo`) REFERENCES `conf_modulo` (`mod_Id`),
  ADD CONSTRAINT `conf_permisos_ibfk_2` FOREIGN KEY (`per_IdSubmodulo`) REFERENCES `conf_submodulo` (`subMod_Id`),
  ADD CONSTRAINT `conf_permisos_ibfk_3` FOREIGN KEY (`per_IdRol`) REFERENCES `conf_rol` (`rol_Id`);

--
-- Filtros para la tabla `conf_submodulo`
--
ALTER TABLE `conf_submodulo`
  ADD CONSTRAINT `fk_submodulo_1` FOREIGN KEY (`subMod_IdModulo`) REFERENCES `conf_modulo` (`mod_Id`);

--
-- Filtros para la tabla `conf_usuario`
--
ALTER TABLE `conf_usuario`
  ADD CONSTRAINT `fk_rol_1` FOREIGN KEY (`usu_Rol`) REFERENCES `conf_rol` (`rol_Id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
