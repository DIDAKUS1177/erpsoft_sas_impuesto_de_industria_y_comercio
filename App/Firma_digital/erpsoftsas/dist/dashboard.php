<?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
	require_once '../business/globals.php';
	include_once('../business/class.sessions.php');
	
	// Cargar configuración del municipio
	$configPath = dirname(dirname(__DIR__)) . '/config.municipio.php';
	if (file_exists($configPath)) {
		require_once $configPath;
	}
	if (!defined('MUNICIPIO_NOMBRE')) define('MUNICIPIO_NOMBRE', 'Alcaldía de Paipa');
	if (!defined('MUNICIPIO_LOGO')) define('MUNICIPIO_LOGO', '/erpsoftsas/vendors/images/escudo-paipa.png');
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
		/* Misma tipografia que el login para dar continuidad visual. */
		body, .dashboard-footer {
			font-family: 'Inter', -apple-system, "Segoe UI", sans-serif;
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
		/* Altura fija y ancho automatico: el escudo es cuadrado y el logo de
		   ERPSoft es horizontal, fijar ambos ejes deformaria el segundo. */
		.dashboard-footer .footer-logos img {
			height: 26px;
			width: auto;
			max-width: 130px;
			object-fit: contain;
		}

		@media (max-width: 768px) {
			.dashboard-footer { flex-direction: column; gap: 0.75rem; text-align: center; }
			.dashboard-footer .footer-logos { flex-wrap: wrap; justify-content: center; }
		}
	</style>
</head>
<body>
	
	<?php include_once 'menu.php' ?>
	<div class="mobile-menu-overlay"></div>

	<div class="main-container">
		<div class="pd-ltr-20">

			<h4 class="h4 mb-30" style="color: var(--erp-texto);">Módulo de Industria y Comercio</h4>
			
			<!-- Accesos Directos -->
			<div class="row clearfix mb-30">
				<div class="col-xl-4 col-lg-4 col-md-6 mb-20">
					<div class="card-box height-100-p widget-style1" style="cursor: pointer; padding: 20px; transition: transform 0.2s;" onclick="menu.validarIngreso(1641,101)" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='none'">
						<div class="d-flex flex-wrap align-items-center">
							<div class="progress-data" style="width: 70px;">
								<div style="background: var(--erp-primario-suave); width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
									<i class="fa fa-id-card" style="font-size: 26px; color: var(--erp-primario);"></i>
								</div>
							</div>
							<div class="widget-data">
								<div class="h4 mb-0" style="font-weight: 700;">RIT</div>
								<div class="weight-600 font-14 text-muted">Registro de Información Tributaria</div>
							</div>
						</div>
					</div>
				</div>

				<div class="col-xl-4 col-lg-4 col-md-6 mb-20">
					<div class="card-box height-100-p widget-style1" style="cursor: pointer; padding: 20px; transition: transform 0.2s;" onclick="menu.validarIngreso(1641,103)" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='none'">
						<div class="d-flex flex-wrap align-items-center">
							<div class="progress-data" style="width: 70px;">
								<div style="background: var(--erp-primario-suave); width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
									<i class="fa fa-file-text" style="font-size: 26px; color: var(--erp-primario);"></i>
								</div>
							</div>
							<div class="widget-data">
								<div class="h4 mb-0" style="font-weight: 700;">Presentar</div>
								<div class="weight-600 font-14 text-muted">Nueva Declaración de ICA</div>
							</div>
						</div>
					</div>
				</div>

				<div class="col-xl-4 col-lg-4 col-md-6 mb-20">
					<div class="card-box height-100-p widget-style1" style="cursor: pointer; padding: 20px; transition: transform 0.2s;" onclick="menu.validarIngreso(1641,102)" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='none'">
						<div class="d-flex flex-wrap align-items-center">
							<div class="progress-data" style="width: 70px;">
								<div style="background: var(--erp-primario-suave); width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
									<i class="fa fa-search" style="font-size: 26px; color: var(--erp-primario);"></i>
								</div>
							</div>
							<div class="widget-data">
								<div class="h4 mb-0" style="font-weight: 700;">Consultar</div>
								<div class="weight-600 font-14 text-muted">Consultar e Imprimir Declaraciones</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Footer -->
			<div class="dashboard-footer" style="flex-direction: column; gap: 8px;">
				<div style="display: flex; justify-content: space-between; width: 100%; align-items: center;">
					<div class="footer-logos">
						<img src="<?php echo MUNICIPIO_LOGO; ?>" alt="Escudo">
						<span><?php echo MUNICIPIO_NOMBRE; ?></span>
						<span style="color: #D1D5DB;">|</span>
						<img src="../vendors/images/deskapp-logo.svg" alt="ERPSoft">
						<span>ERPSOFTSAS V.1</span>
					</div>
					<span>© 2026</span>
				</div>
				<div style="font-size: 13px; color: #6B7280; font-weight: 600; text-align: left; width: 100%;">
					Secretaría de Hacienda - Dirección de Impuestos, Rentas y Jurisdicción Coactiva
				</div>
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

</body>
</html>