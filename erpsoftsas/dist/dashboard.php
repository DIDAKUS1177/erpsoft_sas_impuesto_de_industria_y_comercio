<?php
	require_once '../business/globals.php';
	include_once('../business/class.sessions.php');
	
	// Cargar configuración del municipio
	$configPath = dirname(dirname(__DIR__)) . '/config.municipio.php';
	if (file_exists($configPath)) {
		require_once $configPath;
	}
	if (!defined('MUNICIPIO_NOMBRE')) define('MUNICIPIO_NOMBRE', 'Alcaldía de Paipa');
	if (!defined('MUNICIPIO_LOGO')) define('MUNICIPIO_LOGO', '../extensiones/tcpdf/pdf/images/logo.jpeg');
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>Inicio | ERPSOFTSAS - <?php echo MUNICIPIO_NOMBRE; ?></title>

	<meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="Expires" content="0">
    <meta http-equiv="Last-Modified" content="0">
    <meta http-equiv="Cache-Control" content="no-cache, mustrevalidate">
    <meta http-equiv="Pragma" content="no-cache">

	<!-- Site favicon -->
	<link rel="apple-touch-icon" sizes="180x180" href="../vendors/images/apple-touch-icon.png">
	<link rel="icon" type="image/png" sizes="32x32" href="../vendors/images/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="../vendors/images/favicon-16x16.png">

	<!-- Mobile Specific Metas -->
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">

	<!-- Google Font -->
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
	<!-- CSS -->
	<link rel="stylesheet" type="text/css" href="../vendors/styles/core.css">
	<link rel="stylesheet" type="text/css" href="../vendors/styles/icon-font.min.css">
	<link rel="stylesheet" type="text/css" href="../src/plugins/datatables/css/dataTables.bootstrap4.min.css">
	<link rel="stylesheet" type="text/css" href="../src/plugins/datatables/css/responsive.bootstrap4.min.css">
	<link rel="stylesheet" type="text/css" href="../vendors/styles/style.css">

	<style>
		.dashboard-welcome {
			background: linear-gradient(135deg, #1a56db 0%, #0e4baa 100%);
			border-radius: 12px;
			padding: 2rem;
			color: #FFFFFF;
			display: flex;
			align-items: center;
			gap: 2rem;
			margin-bottom: 1.5rem;
		}
		.dashboard-welcome img {
			width: 80px;
			height: 80px;
			border-radius: 10px;
			object-fit: contain;
			background: rgba(255,255,255,0.15);
			padding: 8px;
		}
		.dashboard-welcome h2 {
			font-size: 22px;
			font-weight: 700;
			margin-bottom: 4px;
		}
		.dashboard-welcome p {
			font-size: 14px;
			opacity: 0.9;
			margin: 0;
		}

		.quick-access-grid {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
			gap: 1rem;
			margin-bottom: 1.5rem;
		}
		.quick-card {
			background: #FFFFFF;
			border-radius: 10px;
			padding: 1.5rem;
			border: 1px solid #E5E7EB;
			box-shadow: 0 2px 8px rgba(0,0,0,0.04);
			transition: all 0.3s ease;
			cursor: pointer;
			text-align: center;
		}
		.quick-card:hover {
			transform: translateY(-3px);
			box-shadow: 0 8px 20px rgba(0,0,0,0.08);
			border-color: #1a56db;
		}
		.quick-card .icon-circle {
			width: 50px;
			height: 50px;
			border-radius: 12px;
			display: flex;
			align-items: center;
			justify-content: center;
			margin: 0 auto 0.75rem;
			font-size: 22px;
			color: #FFFFFF;
		}
		.quick-card h4 {
			font-size: 14px;
			font-weight: 700;
			color: #1F2937;
			margin-bottom: 4px;
		}
		.quick-card p {
			font-size: 12px;
			color: #6B7280;
			margin: 0;
		}

		.dashboard-footer {
			background: #FFFFFF;
			border-radius: 10px;
			padding: 1rem 1.5rem;
			border: 1px solid #E5E7EB;
			display: flex;
			justify-content: space-between;
			align-items: center;
			font-size: 13px;
			color: #6B7280;
		}
		.dashboard-footer .footer-logos {
			display: flex;
			align-items: center;
			gap: 1rem;
		}
		.dashboard-footer .footer-logos img {
			width: 24px;
			height: 24px;
			border-radius: 3px;
			object-fit: contain;
		}

		@media (max-width: 768px) {
			.dashboard-welcome { flex-direction: column; text-align: center; }
			.quick-access-grid { grid-template-columns: 1fr 1fr; }
		}
	</style>
</head>
<body>
	
	<?php include_once 'menu.php' ?>
	<div class="mobile-menu-overlay"></div>

	<div class="main-container">
		<div class="pd-ltr-20">

			<!-- Bienvenida -->
			<div class="dashboard-welcome">
				<img src="<?php echo MUNICIPIO_LOGO; ?>" alt="Escudo">
				<div>
					<h2>Bienvenido al Módulo de Industria y Comercio</h2>
					<p><?php echo MUNICIPIO_NOMBRE; ?> — Portal Tributario Virtual</p>
					<p style="margin-top: 6px; font-size: 12px; opacity: 0.8;">
						<i class="fa fa-user"></i> <span id="dash_NomUsu"></span> · <span id="dash_date"></span>
					</p>
				</div>
			</div>

			<!-- Accesos rápidos -->
			<div class="quick-access-grid">
				<div class="quick-card" onclick="window.location='icaWebRit.php'">
					<div class="icon-circle" style="background: linear-gradient(135deg, #1a56db, #3b82f6);">
						<i class="fa fa-id-card"></i>
					</div>
					<h4>RIT</h4>
					<p>Registro de Información Tributaria</p>
				</div>

				<div class="quick-card" onclick="window.location='icaWebPresentar.php'">
					<div class="icon-circle" style="background: linear-gradient(135deg, #059669, #10b981);">
						<i class="fa fa-file-text"></i>
					</div>
					<h4>Presentar Declaración</h4>
					<p>Crear y presentar su declaración ICA</p>
				</div>

				<div class="quick-card" onclick="window.location='icaWebConsultar.php'">
					<div class="icon-circle" style="background: linear-gradient(135deg, #d97706, #f59e0b);">
						<i class="fa fa-search"></i>
					</div>
					<h4>Consultar Declaraciones</h4>
					<p>Historial y descarga de declaraciones</p>
				</div>

				<div class="quick-card" onclick="window.location='establecimientos.php'">
					<div class="icon-circle" style="background: linear-gradient(135deg, #7c3aed, #a78bfa);">
						<i class="fa fa-building"></i>
					</div>
					<h4>Establecimientos</h4>
					<p>Gestión de establecimientos comerciales</p>
				</div>
			</div>

			<!-- Footer -->
			<div class="dashboard-footer">
				<div class="footer-logos">
					<img src="<?php echo MUNICIPIO_LOGO; ?>" alt="Escudo">
					<span><?php echo MUNICIPIO_NOMBRE; ?></span>
					<span style="color: #D1D5DB;">|</span>
					<img src="../vendors/images/deskapp-logo.svg" alt="ERPSoft">
					<span>ERPSOFTSAS V.1</span>
				</div>
				<span>© 2026</span>
			</div>

		</div>
	</div>

	
	<!-- js -->
	<script src="../vendors/scripts/core.js"></script>
	<script src="../vendors/scripts/script.min.js"></script>
	<script src="../vendors/scripts/process.js"></script>
	<script src="../vendors/scripts/layout-settings.js"></script>
	<script src="../src/plugins/apexcharts/apexcharts.min.js"></script>
	<script src="../src/plugins/datatables/js/jquery.dataTables.min.js"></script>
	<script src="../src/plugins/datatables/js/dataTables.bootstrap4.min.js"></script>
	<script src="../src/plugins/datatables/js/dataTables.responsive.min.js"></script>
	<script src="../src/plugins/datatables/js/responsive.bootstrap4.min.js"></script>
	<script src="../vendors/scripts/dashboard.js"></script>
	<script src="../core/Permisos.js?v=<?php echo time(); ?>"></script>
	<script src="../core/menu.js?v=<?php echo time(); ?>"></script>
	
	<script src="../core/datosVisuales.js?v=<?php echo time(); ?>"></script>

	<script>
		// Mostrar nombre del usuario en el dashboard
		var dashNom = localStorage.getItem('NomUsu');
		if (dashNom) document.getElementById('dash_NomUsu').textContent = dashNom;
		
		// Mostrar fecha actual
		var today = new Date();
		var options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
		document.getElementById('dash_date').textContent = today.toLocaleDateString('es-CO', options);
	</script>
</body>
</html>