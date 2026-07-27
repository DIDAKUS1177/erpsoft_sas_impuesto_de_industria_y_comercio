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
<link rel="stylesheet" type="text/css" href="../src/plugins/sweetalert2/sweetalert2.css">

<!-- ========== PANTALLA DE CARGA ========== -->
<div class="pre-loader">
	<div class="pre-loader-box" style="text-align: center;">
		<div style="display: flex; justify-content: center; align-items: center; gap: 1.5rem; margin-bottom: 1.5rem;">
			<img src="<?php echo MUNICIPIO_LOGO; ?>" alt="Escudo Municipio" style="width: 56px; height: 56px; border-radius: 6px; object-fit: contain;">
			<img src="../vendors/images/deskapp-logo.svg" alt="ERPSoft" style="width: 56px; height: 56px; object-fit: contain;">
		</div>
		<div style="font-family: 'Inter', sans-serif; font-size: 16px; font-weight: 700; color: #1a56db; margin-bottom: 4px;">
			<?php echo MUNICIPIO_NOMBRE; ?>
		</div>
		<div style="font-family: 'Inter', sans-serif; font-size: 12px; color: #6B7280; margin-bottom: 1rem;">
			Powered by ERPSOFTSAS
		</div>
		<div class='loader-progress' id="progress_div">
			<div class='bar' id='bar1'></div>
		</div>
		<div class='percent' id='percent1'>0%</div>
		<div class="loading-text">
			Cargando...
		</div>
	</div>
</div>

<!-- ========== HEADER PRINCIPAL ========== -->
<div class="header" style="border-bottom: 3px solid #1a56db;">
	<div class="header-left">
		<div class="menu-icon dw dw-menu"></div>
		<div style="display: flex; align-items: center; gap: 0.75rem; margin-left: 1rem;">
			<img src="<?php echo MUNICIPIO_LOGO; ?>" alt="Escudo" style="width: 40px; height: 40px; border-radius: 4px; object-fit: contain;">
			<div>
				<div style="font-size: 14px; font-weight: 700; color: #1F2937; line-height: 1.2;"><?php echo MUNICIPIO_NOMBRE; ?></div>
				<div style="font-size: 11px; color: #6B7280;">Industria y Comercio</div>
			</div>
		</div>
	</div>
	<div class="header-right">
		
		<div class="user-info-dropdown">
			<div class="dropdown">
				<a class="dropdown-toggle" href="#" role="button" data-toggle="dropdown">
					<span>
						<img src="../src/images/user/svg/user.svg" alt="erpsoftsas user" width="40" height="40">
					</span>
                    <span class="user-name" id="NomUsu" style="font-size: 13px;"></span>
				</a>
				<div class="dropdown-menu dropdown-menu-right dropdown-menu-icon-list">
					<a class="dropdown-item" href="javascript:void(0)" id="btnCerrarSesion"><i class="dw dw-logout" ></i>Cerrar Sesión </a>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="right-sidebar">
	<div class="sidebar-title">
		<h3 class="weight-600 font-16 text-blue">
			Configuración Visual
			<span class="btn-block font-weight-400 font-12">Ajustes de interfaz</span>
		</h3>
		<div class="close-sidebar" data-toggle="right-sidebar-close">
			<i class="icon-copy ion-close-round"></i>
		</div>
	</div>
	<div class="right-sidebar-body customscroll">
		<div class="right-sidebar-body-content">
			<h4 class="weight-600 font-18 pb-10">Header Background</h4>
			<div class="sidebar-btn-group pb-30 mb-10">
				<a href="javascript:void(0);" class="btn btn-outline-primary header-white active">White</a>
				<a href="javascript:void(0);" class="btn btn-outline-primary header-dark">Dark</a>
			</div>

			<h4 class="weight-600 font-18 pb-10">Sidebar Background</h4>
			<div class="sidebar-btn-group pb-30 mb-10">
				<a href="javascript:void(0);" class="btn btn-outline-primary sidebar-light ">White</a>
				<a href="javascript:void(0);" class="btn btn-outline-primary sidebar-dark active">Dark</a>
			</div>

			<div class="reset-options pt-30 text-center">
				<button class="btn btn-danger" id="reset-settings">Reset Settings</button>
			</div>
		</div>
	</div>
</div>
 
<!-- ========== SIDEBAR / MENÚ LATERAL ========== -->
<div class="left-side-bar">
	<div class="brand-logo" style="padding: 12px 15px;">
		<a href="dashboard.php" style="display: flex; align-items: center; gap: 0.5rem; text-decoration: none;">
			<img src="<?php echo MUNICIPIO_LOGO; ?>" alt="Escudo" style="width: 32px; height: 32px; border-radius: 4px; object-fit: contain;">
			<span style="font-size: 13px; font-weight: 700; color: #FFFFFF; line-height: 1.2;">Módulo ICA</span>
		</a>
		<div class="close-sidebar" data-toggle="left-sidebar-close">
			<i class="ion-close-round"></i>
		</div>
	</div>
	<div class="menu-block customscroll">
		<div class="sidebar-menu">
		
			<ul id="accordion-menu">

				<!-- INICIO -->
				<li class="dropdown" id="MInicio">
					<a href="dashboard.php" class="dropdown-toggle no-arrow">
						<span class="micon dw dw-house-1"></span>
						<span class="mtext">Inicio</span>
					</a>
				</li>

				<!-- CONSULTAS EXTERNAS -->
				<li class="dropdown" id="MConsultasExternas">
					<a href="javascript:;" class="dropdown-toggle">
						<span class="micon fa fa-search"></span>
						<span class="mtext">Impuesto Predial</span>
					</a>

					<ul class="submenu" id="SubConsultasExternas">
						<li class="menu_1035">
							<a  id="ConsultasPazYSalvo" onclick="menu.validarIngreso(1035,100)">
								<i class="fa fa-check-circle submenu-icon"></i> Consultas Paz y Salvo
							</a>
						</li>
					</ul>
				</li>

				<!-- ICA ALCALDÍA → ADMINISTRACIÓN ICA -->
				<li class="dropdown" id="MICAAlcaldia">
					<a href="javascript:;" class="dropdown-toggle">
						<span class="micon fa fa-institution"></span>
						<span class="mtext">Administración ICA</span>
					</a>

					<ul class="submenu" id="SubICAAlcaldia">
						<!-- DATOS BASICOS -->
						<li class="dropdown" id="MICA_DatosBasicos">
							<a href="javascript:;" class="dropdown-toggle">
								<i class="fa fa-folder-open submenu-icon"></i> Datos Básicos
							</a>

							<ul class="submenu" id="SubICA_DatosBasicos">
								<li class="menu_1639">
									<a id="ICA_Contribuyentes" onclick="menu.validarIngreso(1639,4)">
										<i class="fa fa-users submenu-icon"></i> Contribuyentes
									</a>
								</li>

								<li class="menu_1639">
									<a id="ICA_Actividades" onclick="menu.validarIngreso(1639,3)">
										<i class="fa fa-briefcase submenu-icon"></i> Actividades Comercio
									</a>
								</li>

								<li class="menu_1639">
									<a id="ICA_Conceptos" onclick="menu.validarIngreso(1639,6)">
										<i class="fa fa-tags submenu-icon"></i> Conceptos
									</a>
								</li>

								<li class="menu_1639">
									<a id="ICA_GrupoTarifario" onclick="menu.validarIngreso(1639,5)">
										<i class="fa fa-list-alt submenu-icon"></i> Grupo Tarifario
									</a>
								</li>

							</ul>
						</li>

						<!-- PROCESOS -->
						<li class="dropdown" id="MICA_Procesos">
							<a href="javascript:;" class="dropdown-toggle">
								<i class="fa fa-industry submenu-icon"></i> Procesos
							</a>

							<ul class="submenu" id="SubICA_Procesos">
								<li class="menu_1640">
									<a id="ICA_Establecimientos" onclick="menu.validarIngreso(1640,7)">
										<i class="fa fa-building submenu-icon"></i> Establecimientos
									</a>
								</li>
							</ul>
						</li>
					</ul>
				</li>

				<!-- ICA WEB → INDUSTRIA Y COMERCIO -->
				<li class="dropdown" id="MICAWeb">
					<a href="javascript:;" class="dropdown-toggle">
						<span class="micon fa fa-building"></span>
						<span class="mtext">Industria y Comercio</span>
					</a>

					<ul class="submenu" id="SubICAWeb">

						<li class="menu_1641">
							<a id="ICAWeb_RIT" onclick="menu.validarIngreso(1641,101)">
								<i class="fa fa-id-card submenu-icon"></i> RIT
							</a>
						</li>

						<li class="menu_1641">
							<a id="ICAWeb_Presentar" onclick="menu.validarIngreso(1641,103)">
								<i class="fa fa-file-text submenu-icon"></i> Presentar Declaración
							</a>
						</li>

						<li class="menu_1641">
							<a id="ICAWeb_Declaraciones" onclick="menu.validarIngreso(1641,102)">
								<i class="fa fa-search submenu-icon"></i>
								Consultar Declaraciones
							</a>
						</li>

					</ul>
				</li>

				<!-- RETE ICA → RETENCIÓN ICA -->
				<li class="dropdown" id="MReteICA">
					<a href="javascript:;" class="dropdown-toggle">
						<span class="micon fa fa-balance-scale"></span>
						<span class="mtext">Retención ICA</span>
					</a>

					<ul class="submenu" id="SubReteICA">

						<li class="menu_1643">
							<a id="ReteICA_Declaraciones" onclick="menu.validarIngreso(1643,104)">
								<i class="fa fa-search submenu-icon"></i>
								Consultar Declaraciones
							</a>
						</li>

						<li class="menu_1643">
							<a id="ReteICA_Presentar" onclick="menu.validarIngreso(1643,105)">
								<i class="fa fa-file-text submenu-icon"></i> Presentar Declaración
							</a>
						</li>

					</ul>
				</li>

				<!-- AUTO RETENCION → AUTO RETENCIÓN ICA -->
				<li class="dropdown" id="MAutoretencion">
					<a href="javascript:;" class="dropdown-toggle">
						<span class="micon fa fa-percent"></span>
						<span class="mtext">Auto Retención ICA</span>
					</a>

					<ul class="submenu" id="SubAutoretencion">

						<li class="menu_1644">
							<a id="AutoRet_Declaraciones" onclick="menu.validarIngreso(1644,106)">
								<i class="fa fa-search submenu-icon"></i> Consultar Declaraciones
							</a>
						</li>

						<li class="menu_1644">
							<a id="AutoRet_Presentar" onclick="menu.validarIngreso(1644,107)">
								<i class="fa fa-file-text submenu-icon"></i> Presentar Declaración
							</a>
						</li>

					</ul>
				</li>

				<!-- CONFIGURACION -->
				<li class="dropdown" id="MConfig">
					<a href="javascript:;" class="dropdown-toggle">
						<span class="micon dw dw-settings"></span>
						<span class="mtext">Configuración</span>
					</a>

					<ul class="submenu" id="SubConfig">
						<li class="menu_26">
							<a id="Config_Usuarios" onclick="menu.validarIngreso(26,1)">
								<i class="fa fa-user-circle submenu-icon"></i> Usuarios
							</a>
						</li>

						<li class="menu_11">
							<a id="Config_Roles" onclick="menu.validarIngreso(11,2)">
								<i class="fa fa-key submenu-icon"></i> Roles
							</a>
						</li>
					</ul>
				</li>

			</ul>


		</div>
	</div>
</div>

<script src="../src/scripts/jquery.min.js"></script>
<script src="../core/Permisos.js?v=<?php echo time(); ?>"></script>
<script src="../core/menu.js?v=<?php echo time(); ?>"></script>
<script src="../src/plugins/sweetalert2/sweetalert2.all.js"></script>

<script>
    var NomUsu = localStorage.getItem('NomUsu');
    var mailUsu = localStorage.getItem('mailUsu');
    
    console.log('NomUsu ',NomUsu)
    $("#NomUsu").empty();
    $("#NomUsu").append(NomUsu);

    $("#mailUsu").empty();
    $("#mailUsu").append('<strong>'+ mailUsu + '</strong>');

    $("#btnCerrarSesion").click(function(){
		localStorage.clear();
		window.location = '../index.php';
    });

	function validarSesion() {
		const fechaGuardada = localStorage.getItem('fechaSesion');
      
      // Si no existe fechaSesion, redirige de inmediato (no hay sesión)
		if (!fechaGuardada) {
			localStorage.clear();
			window.location = '../index.php';
			return;
		}

		// Fecha actual en YYYY-MM-DD
		const fechaHoy = new Date().toISOString().slice(0, 10);
		console.log('Fecha Hoy '+fechaHoy);
		console.log('Fecha Guardada '+fechaGuardada);


		if (fechaGuardada != fechaHoy) {    
			localStorage.clear();
			window.location = '../index.php';
		}
    }

    // Llamamos a la validación de la sesión al cargar la página
    window.addEventListener('load', validarSesion);

</script>

	
<style>
/* Estilos para resaltar el menú activo */
	/* Nivel 2 (Datos Básicos, Procesos) */
	.sidebar-menu .submenu {
		padding-left: 18px !important;
	}

	/* Nivel 3 (Contribuyentes, Actividades, etc) */
	.sidebar-menu .submenu .submenu {
		padding-left: 24px !important;
	}

	/* Alinear íconos más a la derecha para escalera visual */
	.sidebar-menu .submenu a {
		padding-left: 12px !important;
	}

	.sidebar-menu .submenu .submenu a {
		padding-left: 20px !important;
	}

	/* Quitar guiones del nivel 3 */
	.sidebar-menu ul.submenu li a:before {
		display: none !important;
	}


	/* Nivel 2 */
.sidebar-menu > ul > li > ul.submenu {
    padding-left: 18px !important;
}

/* Barra de progreso azul */
.pre-loader .bar {
	background: #1a56db !important;
}

/* Header border azul */
.header {
	border-bottom: 3px solid #1a56db !important;
}

</style>