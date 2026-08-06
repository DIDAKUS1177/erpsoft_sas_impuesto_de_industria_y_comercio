<?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
	require_once '../business/globals.php';
	include_once('../business/class.sessions.php');
	
	// Cargar configuración del municipio
	$configPath = dirname(__DIR__) . '/config.municipio.php';
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
	<meta name="viewport" content="width=device-width, initial-scale=1">

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

			<h4 class="h4 mb-30" style="color: var(--erp-texto);">Accesos rápidos</h4>

			<!--
			  Accesos Directos.

			  Antes eran 3 tarjetas escritas a mano que ademas apuntaban a
			  SUBmodulos de Industria y Comercio (RIT / Presentar / Consultar).
			  Ahora se generan solas a partir del menu lateral, que ya viene
			  filtrado por los permisos del rol: se listan los MODULOS
			  generales del sistema. Al agregar un modulo nuevo -o al montar
			  otro municipio- esta pantalla se adapta sin tocar codigo.
			-->
			<div class="row clearfix mb-30" id="accesosRapidosModulos"></div>

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

	<script>
	/*
	 * Accesos rapidos a los MODULOS generales.
	 *
	 * Se construyen leyendo el propio menu lateral (#accordion-menu) en vez
	 * de repetir aqui la lista de modulos. Ventajas:
	 *   - el sidebar ya viene filtrado por los permisos del rol, asi que las
	 *     tarjetas nunca ofrecen algo que la persona no puede abrir;
	 *   - se reutiliza el mismo icono, para que tarjeta y menu coincidan;
	 *   - agregar un modulo (o cambiar de municipio) no obliga a tocar esta
	 *     pantalla.
	 * "Inicio" se excluye: es esta misma pagina.
	 */
	(function () {
		'use strict';

		function construirAccesosRapidos() {
			var contenedor = document.getElementById('accesosRapidosModulos');
			if (!contenedor) { return; }

			var modulos = document.querySelectorAll('#accordion-menu > li.dropdown');

			modulos.forEach(function (li) {
				var etiqueta = li.querySelector('.mtext');
				var icono    = li.querySelector('.micon');
				if (!etiqueta) { return; }

				var nombre = etiqueta.textContent.trim();

				// "Inicio" es esta misma pantalla: no es un acceso rapido.
				if (nombre === '' || nombre.toLowerCase() === 'inicio') { return; }

				var enlaces = li.querySelectorAll('.submenu a');

				// Un modulo sin submodulos visibles no tiene a donde llevar.
				if (!enlaces.length) { return; }

				var subtitulo = enlaces.length === 1
					? '1 opción disponible'
					: enlaces.length + ' opciones disponibles';

				var col = document.createElement('div');
				col.className = 'col-xl-4 col-lg-4 col-md-6 mb-20';

				var tarjeta = document.createElement('div');
				tarjeta.className = 'card-box height-100-p widget-style1';
				tarjeta.style.cssText = 'padding:20px;transition:box-shadow .2s;';

				var cabecera = document.createElement('div');
				cabecera.className = 'd-flex flex-wrap align-items-center';
				cabecera.style.cssText = 'cursor:pointer;';
				cabecera.setAttribute('role', 'button');
				cabecera.setAttribute('tabindex', '0');
				cabecera.setAttribute('aria-expanded', 'false');
				cabecera.setAttribute('aria-label', 'Ver opciones de ' + nombre);

				cabecera.innerHTML =
						'<div class="progress-data" style="width:70px;">' +
							'<div style="background:var(--erp-primario-suave);width:60px;height:60px;' +
								'border-radius:50%;display:flex;align-items:center;justify-content:center;' +
								'color:var(--erp-primario);">' +
								'<span class="acceso-icono" style="display:flex;width:26px;height:26px;"></span>' +
							'</div>' +
						'</div>' +
						'<div class="widget-data" style="flex:1;">' +
							'<div class="h4 mb-0" style="font-weight:700;"></div>' +
							'<div class="weight-600 font-14 text-muted"></div>' +
						'</div>' +
						'<span class="acceso-flecha" style="transition:transform .15s;color:var(--erp-tenue,#9aa5a3);">' +
							'<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" ' +
								'stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
								'<polyline points="6 9 12 15 18 9"></polyline>' +
							'</svg>' +
						'</span>';

				// textContent (no innerHTML) para no inyectar markup desde el menu.
				cabecera.querySelector('.widget-data .h4').textContent = nombre;
				cabecera.querySelector('.widget-data .text-muted').textContent = subtitulo;

				// Se clona el SVG del menu para que el icono sea identico.
				var svg = icono ? icono.querySelector('svg') : null;
				if (svg) {
					var copia = svg.cloneNode(true);
					copia.setAttribute('width', '26');
					copia.setAttribute('height', '26');
					cabecera.querySelector('.acceso-icono').appendChild(copia);
				}

				/*
				 * La tarjeta despliega sus opciones AHI MISMO, en vez de
				 * abrir el submenu del sidebar. Antes se disparaba el mismo
				 * toggle del menu lateral y se hacia scrollIntoView() hasta
				 * el, pero el sidebar tiene su propio scroll interno
				 * (.menu-block.customscroll) y para los modulos mas abajo
				 * en la lista el submenu se abria fuera de la vista: el
				 * usuario pulsaba la tarjeta y, a simple vista, no pasaba
				 * nada. Desplegar en la propia tarjeta no depende de donde
				 * quedo el sidebar ni de su scroll.
				 */
				var panel = document.createElement('div');
				panel.className = 'acceso-panel';
				panel.style.cssText = 'display:none;margin-top:14px;padding-top:14px;border-top:1px solid var(--erp-borde,#E5E7EB);';

				enlaces.forEach(function (enlaceOriginal) {
					var texto = enlaceOriginal.textContent.replace(/\s+/g, ' ').trim();
					if (!texto) { return; }

					var item = document.createElement('a');
					item.href = 'javascript:void(0);';
					item.className = 'd-block';
					item.style.cssText = 'padding:8px 4px;font-size:13.5px;color:var(--erp-texto,#333);text-decoration:none;';
					item.textContent = texto;

					// Se reutiliza el <a> original del sidebar (con su
					// onclick="menu.validarIngreso(...)" ya cableado, que
					// valida permisos y navega): la tarjeta solo simula el
					// click sobre el, no reimplementa esa logica.
					item.addEventListener('click', function (e) {
						e.stopPropagation();
						enlaceOriginal.click();
					});
					item.addEventListener('mouseover', function () {
						item.style.color = 'var(--erp-primario)';
					});
					item.addEventListener('mouseout', function () {
						item.style.color = 'var(--erp-texto,#333)';
					});

					panel.appendChild(item);
				});

				function alternarPanel() {
					var abierto = panel.style.display !== 'none';
					panel.style.display = abierto ? 'none' : 'block';
					cabecera.setAttribute('aria-expanded', String(!abierto));
					cabecera.querySelector('.acceso-flecha').style.transform = abierto ? 'none' : 'rotate(180deg)';
				}

				cabecera.addEventListener('click', alternarPanel);
				cabecera.addEventListener('keydown', function (e) {
					if (e.key === 'Enter' || e.key === ' ') {
						e.preventDefault();
						alternarPanel();
					}
				});

				tarjeta.appendChild(cabecera);
				tarjeta.appendChild(panel);
				col.appendChild(tarjeta);
				contenedor.appendChild(col);
			});
		}

		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', construirAccesosRapidos);
		} else {
			construirAccesosRapidos();
		}
	})();
	</script>

</body>
</html>