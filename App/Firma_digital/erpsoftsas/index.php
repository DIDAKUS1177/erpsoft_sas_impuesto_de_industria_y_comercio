<?php
// Incluir configuración del municipio.
// Ubicación real (Plesk/producción): un nivel arriba de /erpsoftsas (ver
// plan_despliegue_plesk_claude.md). Fallback dentro de /erpsoftsas: solo para
// Docker local, donde docker-compose.yml monta únicamente esta carpeta.
$configPath = dirname(__DIR__) . '/config.municipio.php';
if (!file_exists($configPath)) {
    $configPath = __DIR__ . '/config.municipio.php';
}
if (file_exists($configPath)) {
    require_once $configPath;
}
// Valores por defecto si no hay config
if (!defined('MUNICIPIO_NOMBRE')) define('MUNICIPIO_NOMBRE', 'Alcaldía de Paipa');
if (!defined('MUNICIPIO_LOGO')) define('MUNICIPIO_LOGO', '/erpsoftsas/vendors/images/escudo-paipa.png');
if (!defined('MUNICIPIO_COLOR')) define('MUNICIPIO_COLOR', '#1fa49d');
if (!defined('MUNICIPIO_COLOR_OSCURO')) define('MUNICIPIO_COLOR_OSCURO', '#17756f');
if (!defined('MUNICIPIO_FONDO_LOGIN')) define('MUNICIPIO_FONDO_LOGIN', 'vendors/images/fondo-paipa.jpg');
?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<title>ERPSOFTSAS - <?php echo MUNICIPIO_NOMBRE; ?></title>

	<!-- Site favicon -->
	<link rel="apple-touch-icon" sizes="180x180" href="vendors/images/apple-touch-icon.png">
	<link rel="icon" type="image/png" sizes="32x32" href="vendors/images/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="vendors/images/favicon-16x16.png">

	<!-- Mobile Specific Metas -->
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">

	<!-- Google Font -->
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
	
	<!-- CSS del framework base (necesario para modales Bootstrap) -->
	<link rel="stylesheet" type="text/css" href="vendors/styles/core.css">
	<link rel="stylesheet" type="text/css" href="vendors/styles/icon-font.min.css">
	<link rel="stylesheet" type="text/css" href="src/plugins/sweetalert2/sweetalert2.css">

	<style>
		/* ==========================================================================
		   VARIABLES DE DISEÑO - PALETA INSTITUCIONAL
		   El primario sale de config.municipio.php (MUNICIPIO_COLOR), igual que
		   el nombre y los logos: cambiando ese archivo se remarca la aplicacion
		   para otro municipio sin tocar codigo. Secundario blanco y grises.
		   ========================================================================== */
		:root {
			--brand-primary: <?php echo MUNICIPIO_COLOR; ?>;
			--brand-primary-hover: <?php echo MUNICIPIO_COLOR_OSCURO; ?>;
			--brand-primary-light: #E6F6F5;
			--brand-accent: <?php echo MUNICIPIO_COLOR_OSCURO; ?>;
			--text-main: #1F2937;
			--text-muted: #6B7280;
			--bg-body: #F3F4F6;
			--bg-card: #FFFFFF;
			--border-color: #E5E7EB;
			--shadow-card: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
			--shadow-hover: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
			--transition-smooth: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
		}

		/* ===== ESTILOS DEL LOGIN ===== */
		body.login-page {
			font-family: 'Inter', sans-serif !important;
			background-color: var(--bg-body) !important;
			margin: 0;
			padding: 0;
			min-height: 100vh;
			display: flex;
			flex-direction: column;
		}

		/* Header del login */
		.login-main-header {
			background-color: var(--bg-card);
			padding: 0.75rem 2rem;
			display: flex;
			justify-content: space-between;
			align-items: center;
			border-bottom: 3px solid var(--brand-primary);
			box-shadow: 0 2px 4px rgba(0,0,0,0.04);
		}
		.login-logo-container {
			display: flex;
			align-items: center;
			gap: 1rem;
		}
		.login-logo-shield {
			width: 48px;
			height: 48px;
			object-fit: contain;
			border-radius: 4px;
		}
		.login-logo-text h1 {
			font-size: 17px;
			font-weight: 700;
			color: var(--text-main);
			line-height: 1.2;
			margin: 0;
		}
		.login-logo-text p {
			font-size: 12px;
			color: var(--text-muted);
			font-weight: 500;
			margin: 0;
		}

		/* Contenedor principal del login */
		.login-container-custom {
			flex-grow: 1;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 1.4rem 1.5rem;
			/* Panoramica del municipio (fondo-paipa.jpg: el lago Sochagota) con
			   un velo institucional. Antes el velo llegaba a 78% de opacidad,
			   suficiente para tapar casi toda la foto -por eso se veia como un
			   degradado solido sin ninguna imagen debajo-. Bajado para que el
			   lago se note, conservando contraste para el card blanco. */
			background:
				linear-gradient(rgba(15, 60, 57, 0.38), rgba(15, 60, 57, 0.52)),
				url('<?php echo MUNICIPIO_FONDO_LOGIN; ?>') center/cover no-repeat;
		}

		/* Card de login */
		.login-card-custom {
			background-color: var(--bg-card);
			border-radius: 16px;
			border: 1px solid var(--border-color);
			box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
			width: 100%;
			max-width: 440px;
			overflow: hidden;
			transition: var(--transition-smooth);
		}
		.login-card-custom:hover {
			box-shadow: var(--shadow-hover);
		}

		/* Banda superior del card, en el color primario */
		.login-header-band {
			background: linear-gradient(135deg, var(--brand-primary), var(--brand-accent));
			color: #FFFFFF;
			padding: 0.85rem 2rem;
			text-align: center;
		}
		.login-header-band h2 {
			font-size: 18px;
			font-weight: 700;
			margin: 0;
		}
		.login-header-band p {
			font-size: 13px;
			opacity: 0.9;
			margin-top: 0.25rem;
		}

		/* Cuerpo del formulario */
		.login-body-custom {
			padding: 1.4rem 2rem;
		}

		.form-group-custom {
			margin-bottom: 1.15rem;
			display: flex;
			flex-direction: column;
			gap: 0.4rem;
		}
		.form-group-custom label {
			font-size: 13px;
			font-weight: 700;
			color: var(--brand-primary);
			text-transform: uppercase;
			letter-spacing: 0.5px;
		}
		.input-wrapper-custom {
			position: relative;
			display: flex;
			align-items: center;
		}
		.input-icon-custom {
			position: absolute;
			left: 1rem;
			color: var(--text-muted);
			pointer-events: none;
			width: 18px;
			height: 18px;
		}
		.form-control-custom {
			width: 100%;
			padding: 0.85rem 1rem 0.85rem 2.75rem;
			border: 1px solid var(--border-color);
			border-radius: 8px;
			font-size: 14px;
			outline: none;
			transition: var(--transition-smooth);
			font-family: inherit;
		}
		.form-control-custom:focus {
			border-color: var(--brand-primary);
			box-shadow: 0 0 0 3px rgba(31, 164, 157, 0.12);
		}

		/* Botón principal */
		.btn-submit-custom {
			background: linear-gradient(135deg, var(--brand-primary), var(--brand-accent));
			color: #FFFFFF;
			width: 100%;
			padding: 0.85rem;
			border-radius: 8px;
			font-weight: 700;
			font-size: 15px;
			text-transform: uppercase;
			letter-spacing: 0.5px;
			box-shadow: 0 4px 10px rgba(31, 164, 157, 0.25);
			transition: var(--transition-smooth);
			margin-bottom: 1.1rem;
			border: none;
			cursor: pointer;
		}
		.btn-submit-custom:hover {
			background: linear-gradient(135deg, var(--brand-primary-hover), var(--brand-accent));
			transform: translateY(-1px);
			box-shadow: 0 6px 15px rgba(31, 164, 157, 0.35);
		}

		/* Links del login */
		.login-links-custom {
			text-align: center;
			font-size: 13.5px;
			color: var(--text-muted);
			border-top: 1px solid var(--border-color);
			padding-top: 1.1rem;
		}
		.login-links-custom a,
		.login-links-custom button {
			color: var(--brand-primary);
			font-weight: 600;
			background: none;
			border: none;
			cursor: pointer;
			font-size: 13.5px;
			padding: 0;
		}
		.login-links-custom a:hover,
		.login-links-custom button:hover {
			text-decoration: underline;
		}

		/* Footer del login */
		.login-footer-banner {
			background: linear-gradient(135deg, var(--brand-primary), var(--brand-accent));
			color: #FFFFFF;
			padding: 1.1rem 2rem;
			text-align: center;
			font-size: 13px;
		}
		.login-footer-banner .footer-logos {
			display: flex;
			justify-content: center;
			align-items: center;
			gap: 2rem;
			margin-bottom: 0.6rem;
		}
		.login-footer-banner .footer-logo-item {
			display: flex;
			align-items: center;
			gap: 0.5rem;
			font-weight: 600;
		}
		/* El escudo es cuadrado; el logo de ERPSoft es un lockup horizontal.
		   Se fija solo la altura para no deformar ninguno de los dos. */
		.login-footer-banner .footer-logo-item img {
			height: 40px;
			width: auto;
			max-width: 250px;
			object-fit: contain;
		}
		.login-footer-banner .footer-logo-item img.logo-vendor {
			background: #FFFFFF;
			border-radius: 4px;
			padding: 4px 6px;
		}
		.login-footer-banner .footer-separator {
			width: 1px;
			height: 24px;
			background: rgba(255,255,255,0.3);
		}
		.login-footer-banner a {
			color: rgba(255,255,255,0.9);
			text-decoration: none;
		}
		.login-footer-banner a:hover {
			color: #FFFFFF;
			text-decoration: underline;
		}

		/* Ocultar el header y login originales del framework */
		.login-header, .login-wrap { display: none !important; }

		@media (max-width: 768px) {
			.login-main-header { padding: 0.75rem 1rem; }
			.login-container-custom { padding: 1.5rem; }
			.login-body-custom { padding: 1.5rem; }
			.login-footer-banner .footer-logos { flex-direction: column; gap: 1rem; }
			.login-footer-banner .footer-separator { display: none; }
		}
	</style>
	<!-- select2: usado por el campo "Municipio de Residencia" del registro
	     publico -mismo CDN que ya usa dist/contribuyentes.php-. -->
	<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
</head>
<body class="login-page">

	<!-- ========== HEADER INSTITUCIONAL ========== -->
	<header class="login-main-header">
		<div class="login-logo-container">
			<img src="<?php echo MUNICIPIO_LOGO; ?>" alt="Escudo" class="login-logo-shield">
			<div class="login-logo-text">
				<h1><?php echo MUNICIPIO_NOMBRE; ?></h1>
				<p>Secretaría de Hacienda — Portal Tributario Virtual</p>
			</div>
		</div>
	</header>

	<!-- ========== FORMULARIO DE LOGIN ========== -->
	<main class="login-container-custom">
		<div class="login-card-custom">
			<div class="login-header-band">
				<h2>Iniciar Sesión</h2>
				<p>Módulo de Industria y Comercio</p>
			</div>
			<div class="login-body-custom">
				<form action="javascript:login.init();">
					<div class="form-group-custom">
						<label for="email">Usuario / NIT</label>
						<div class="input-wrapper-custom">
							<svg class="input-icon-custom" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
								<circle cx="12" cy="7" r="4"/>
							</svg>
							<input type="text" class="form-control-custom" id="email" placeholder="Ingrese su usuario o NIT" required>
						</div>
					</div>
					
					<div class="form-group-custom">
						<label for="password">Contraseña</label>
						<div class="input-wrapper-custom">
							<svg class="input-icon-custom" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
								<path d="M7 11V7a5 5 0 0 1 10 0v4"/>
							</svg>
							<input type="password" class="form-control-custom" id="password" placeholder="••••••••••••" required>
						</div>
					</div>

					<button type="submit" class="btn-submit-custom">Ingresar al Sistema</button>
				</form>

				<div class="login-links-custom">
					<button type="button" onclick="login.crearUsuario()">
						¿Soy un nuev@ usuario? Inscribirse
					</button><br>
					<button type="button" onclick="login.RecuperarUsuario()">
						¿Has olvidado tu Contraseña?
					</button>
				</div>
			</div>
		</div>
	</main>

	<!-- ========== FOOTER CON AMBOS LOGOS ========== -->
	<footer class="login-footer-banner">
		<div class="footer-logos">
			<div class="footer-logo-item">
				<img src="<?php echo MUNICIPIO_LOGO; ?>" alt="Escudo Municipio">
				<span><?php echo MUNICIPIO_NOMBRE; ?></span>
			</div>
			<div class="footer-separator"></div>
			<div class="footer-logo-item">
				<img src="vendors/images/deskapp-logo.svg" alt="ERPSoft" class="logo-vendor">
			</div>
		</div>
		<div style="margin: 4px 0; font-size: 14px; font-weight: 600; opacity: 0.95;">
			Secretaría de Hacienda - Dirección de Impuestos, Rentas y Jurisdicción Coactiva
		</div>
		<span style="opacity: 0.75; font-size: 12px;">ERPSOFTSAS © 2026 — <a href="https://erpsoftsas.com" target="_blank" style="color: #FFFFFF; text-decoration: underline;">erpsoftsas.com</a> | Soporte: 318 530 9285</span>
	</footer>

	<!-- ================ MODALES (CONSERVADOS) ================ -->

	<!-- Modal Crear Usuario -->
	<div class="modal fade" id="modal-Usuario" tabindex="-1" role="dialog" aria-labelledby="exampleModalFormTitle" aria-hidden="true">
		<div class="modal-dialog modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Información del Contribuyente</h5>
					<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
				</div>
				<form id="formCrearUsuario" onsubmit="login.postUsuario(); return false;">
					<div class="modal-body">
						<div class="form-row">

						<div class="form-group col-md-6">
							<label>* Tipo Persona</label>
							<select class="form-control" style="width: 100%;"
								id="usu_IdTipoPersona" name="usu_IdTipoPersona" required>
								<option value="">Seleccione Tipo Persona</option>
								<option value="1">Natural</option>
								<option value="2">Jurídica</option>
							</select>
						</div>

						<div class="form-group col-md-6">
							<label>* Tipo Documento</label>
							<select class="form-control" style="width: 100%;"
								id="usu_IdTipoDocumento" name="usu_IdTipoDocumento" required>
								<option value="">Seleccione Tipo Documento</option>
								<option value="1">Cédula de Ciudadanía</option>
								<option value="5">NIT</option>
								<option value="3">Cédula de Extranjería</option>
								<option value="4">Pasaporte</option>
							</select>
						</div>

						<div class="form-group col-md-4">
							<label id="labelDocumento">* Documento</label>
							<input type="number" class="form-control" id="usu_Documento" required>
						</div>

						<div class="form-group col-md-2">
							<label>* DV</label>
							<input type="text" class="form-control" id="usu_DV" disabled>
						</div>
							
						<div class="form-group col-md-8" id="grupoNombres">
							<label id="labelNombres">* Nombres</label>
							<input type="text" class="form-control" id="usu_Nombres" required>
						</div>
						<div class="form-group col-md-6"  id="grupoApellidos">
							<label>* Apellidos</label>
							<input type="text" class="form-control" id="usu_Apellidos" required>
						</div>

						<div class="form-group col-md-6">
							<label>* Correo</label>
							<input type="email" class="form-control" id="usu_Correo" required>
						</div>
						<div class="form-group col-md-6">
							<label>* Telefono</label>
							<input type="text" class="form-control" id="usu_Telefono" required>
						</div>

						<div class="form-group col-md-8">
							<label>* Dirección</label>
							<input type="text" class="form-control" id="usu_Direccion" required>
						</div>

						<!-- usu_IdCiudad: municipio de RESIDENCIA del contribuyente. Antes este
						formulario no pedia esta ciudad y class.usuarios.php grababa
						ind_IdCiudad=1 (Tunja) a ciegas para todo el mundo -por eso el PDF de
						cualquier contribuyente mostraba "Tunja / Boyacá" como municipio de
						notificacion sin importar donde estuviera realmente-. -->
						<div class="form-group col-md-4">
							<label>* Municipio de Residencia</label>
							<select class="form-control select2" id="usu_IdCiudad" name="usu_IdCiudad"
								style="width: 100%;" required>
								<option value="">Seleccione ciudad...</option>
							</select>
						</div>

						<div class="form-group col-md-4">
							<label>* Usuario</label>
							<input type="text" class="form-control" id="usu_Usuario" required>
						</div>	
						<div class="form-group col-md-4">
							<label>* Clave</label>
							<input type="password" class="form-control" id="usu_Clave" required>
						</div>	
						<div class="form-group col-md-4" id="passwordHelp" class="mt-2" style="font-size: 13px;">
							<div id="req-length" class="text-danger">• Mínimo 8 caracteres</div>
							<div id="req-upper" class="text-danger">• Al menos una mayúscula</div>
							<div id="req-lower" class="text-danger">• Al menos una minúscula</div>
							<div id="req-number" class="text-danger">• Al menos un número</div>
						</div>
					
						<div class="form-group col-md-6">
							<input type="checkbox" id="con_Activo" name="con_Activo" data-toggle="switch" required>
							<label>Acepto tratamiento de datos personales</label><br>
						</div>

					</div>
					
					
					
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal">Cancelar</button>
						<button type="submit" class="btn btn-success btn-pill" id="btnCrearUsuario">Inscribirse</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<!-- Modal Recuperar Usuario -->
	<div class="modal fade" id="modal-RecuperarUsuario" tabindex="-1" role="dialog" aria-labelledby="exampleModalFormTitle" aria-hidden="true">
		<div class="modal-dialog modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Recuperar Usuario</h5>
					<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
				</div>
				<form id="formRecuperarUsuario" onsubmit="login.postRecuperarUsuario(); return false;">
					
				<div class="modal-body">
					<div class="form-row justify-content-center">
						<div class="form-group col-md-8 col-lg-6 text-center">

							<label class="font-weight-bold">* NIT o Cédula</label>

							<input type="text"
								class="form-control text-center"
								id="usu_DocumentoRecuperar"
								required
								inputmode="numeric"
								placeholder="Ingrese su NIT o número de cédula">

							<small class="form-text text-muted mt-2">
								Enviaremos las instrucciones al correo electrónico
								registrado para ese documento.
							</small>

						</div>
					</div>
				</div>

					<div class="modal-footer">
						<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal">Cancelar</button>
						<button type="submit" class="btn btn-success btn-pill" id="btnRecuperarUsuario">Enviar</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<!-- js -->
	<script src="vendors/scripts/core.js"></script>
	<script src="vendors/scripts/script.min.js"></script>
	<script src="vendors/scripts/process.js"></script>
	<script src="vendors/scripts/layout-settings.js"></script>
	<script src="src/scripts/jquery.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
	<script src="src/plugins/sweetalert2/sweetalert2.all.js"></script>
	<script src="login.js?v=<?php echo time(); ?>"></script>
	
	<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

</body>
</html>