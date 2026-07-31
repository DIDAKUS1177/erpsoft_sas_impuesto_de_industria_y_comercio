<?php
require_once '../business/globals.php';
include_once('../business/class.sessions.php');

// Cargar configuración del municipio
$configPath = dirname(dirname(__DIR__)) . '/config.municipio.php';
if (file_exists($configPath)) {
    require_once $configPath;
}
if (!defined('MUNICIPIO_NOMBRE')) define('MUNICIPIO_NOMBRE', 'Alcaldía de Paipa');
if (!defined('MUNICIPIO_LOGO')) define('MUNICIPIO_LOGO', '/erpsoftsas/vendors/images/escudo-paipa.png');
if (!defined('MUNICIPIO_COLOR')) define('MUNICIPIO_COLOR', '#1fa49d');
if (!defined('MUNICIPIO_COLOR_OSCURO')) define('MUNICIPIO_COLOR_OSCURO', '#17756f');
?>
<link rel="stylesheet" type="text/css" href="../src/plugins/sweetalert2/sweetalert2.css">

<style>
	:root {
		--erp-primario: <?php echo MUNICIPIO_COLOR; ?>;
		--erp-oscuro: <?php echo MUNICIPIO_COLOR_OSCURO; ?>;
	}
	
	/* Forzar los colores de la marca en el Header y Sidebar, sobreescribiendo el tema por defecto (rojo/azul) */
	.header {
		background: var(--erp-primario) !important;
		border-bottom: none !important;
	}
	.left-side-bar {
		background: var(--erp-primario) !important;
	}
	
	/* Ajustar los textos e iconos para que se vean bien sobre fondo Teal */
	.header-left .menu-icon, 
	.header-right .user-info-dropdown .user-name,
	.sidebar-menu .dropdown-toggle .mtext,
	.sidebar-menu .dropdown-toggle .micon {
		color: #ffffff !important;
	}
	
	/* Hover en el menú lateral */
	.sidebar-menu .show > .dropdown-toggle,
	.sidebar-menu .dropdown-toggle:hover {
		background: var(--erp-oscuro) !important;
	}
</style>
<!-- ========== PANTALLA DE CARGA ========== -->
<div class="pre-loader">
	<div class="pre-loader-box" style="text-align: center;">
		<div style="display: flex; justify-content: center; align-items: center; gap: 3rem; margin-bottom: 2rem; flex-wrap: wrap;">
			<img src="<?php echo MUNICIPIO_LOGO; ?>" alt="Escudo Municipio" style="height: 250px; width: auto; max-width: 350px; object-fit: contain;">
			<img src="../vendors/images/deskapp-logo.svg" alt="ERPSoft" style="height: 180px; width: auto; max-width: 300px; object-fit: contain;">
		</div>
		<div style="font-family: 'Inter', sans-serif; font-size: 26px; font-weight: 700; color: var(--erp-primario); margin-bottom: 6px; letter-spacing: -0.01em;">
			<?php echo MUNICIPIO_NOMBRE; ?>
		</div>
		<div style="font-family: 'Inter', sans-serif; font-size: 14px; color: #6B7280; margin-bottom: 1.5rem;">
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
<div class="header">
	<div class="header-left" style="display: flex; align-items: center;">
		<!-- Botón para ocultar/mostrar menú ahora es el escudo y texto -->
		<div id="btnMenu" style="display: flex; align-items: center; gap: 0.75rem; margin-left: 1rem; cursor: pointer; padding: 5px; border-radius: 6px; transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'" title="Mostrar u ocultar el menú lateral">
			<img src="<?php echo MUNICIPIO_LOGO; ?>" alt="Escudo" style="width: 55px; height: 55px; border-radius: 4px; object-fit: contain;">
			<div>
				<div style="font-size: 14px; font-weight: 700; color: #FFFFFF; line-height: 1.2;"><?php echo MUNICIPIO_NOMBRE; ?></div>
				<div style="font-size: 11px; color: rgba(255,255,255,.85);">Industria y Comercio</div>
			</div>
		</div>
		
		<!-- Título dinámico de la página actual -->
		<div style="width: 1px; height: 25px; background: rgba(255,255,255,0.3); margin: 0 20px;"></div>
		<div id="headerPageTitle" style="color: #FFFFFF; font-size: 15px; font-weight: 600; letter-spacing: 0.5px;"></div>
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
					<div style="padding: 10px 18px 8px; border-bottom: 1px solid #E5E7EB; line-height: 1.35;">
						<div id="ddNomUsu" style="font-size: 13px; font-weight: 700; color: #1F2937;"></div>
						<div id="mailUsu" style="font-size: 11px; color: #6B7280; word-break: break-all;"></div>
					</div>
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
	<div class="menu-block customscroll" style="padding-bottom: 150px;">
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

	<!-- FOOTER DEL SIDEBAR (LOGO ERP) -->
	<div class="sidebar-footer" style="position: absolute; bottom: 0; left: 0; width: 100%; padding: 15px; text-align: center; border-top: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.15);">
		<div style="background: rgba(255,255,255,0.08); padding: 5px; border-radius: 4px; display: inline-block; margin-bottom: 8px; border: 1px solid rgba(255,255,255,0.08);">
			<img src="../vendors/images/deskapp-logo.svg" alt="ERPSoft" style="height: 30px; width: auto; object-fit: contain;">
		</div>
		<div class="sidebar-version" style="font-size: 11px; font-weight: 600; color: rgba(255, 255, 255, 0.5);">
			v2 &copy; <?php echo date('Y'); ?>
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
    
    // Se usa .text() para no inyectar HTML con datos provenientes del usuario.
    $("#NomUsu").text(NomUsu || '');
    $("#ddNomUsu").text(NomUsu || '');
    $("#mailUsu").text(mailUsu || '');

    $("#btnCerrarSesion").click(function(){
		localStorage.clear();
		window.location = '../index.php';
    });

    // Configurar Título del Header Dinámicamente leyendo la opción activa del menú
    $(document).ready(function() {
        var $activeSub = $('.sidebar-menu .submenu a.active');
        var $activeMenu = $('.sidebar-menu li.active > a.dropdown-toggle');
        
        var title = '';
        var iconHtml = '';

        if ($activeSub.length > 0) {
            title = $activeSub.text().trim();
            iconHtml = '<i class="fa fa-angle-right" style="margin-right: 8px; font-size: 16px; opacity: 0.7;"></i>';
        } else if ($activeMenu.length > 0) {
            title = $activeMenu.find('.mtext').text().trim();
            // Buscar el icono del menú activo si existe
            var $micon = $activeMenu.find('.micon').clone();
            if($micon.length > 0) {
                $micon.css({'margin-right': '8px', 'font-size': '18px'});
                iconHtml = $micon.prop('outerHTML');
            }
        }
        
        if (title) {
            $('#headerPageTitle').html(iconHtml + title);
        }
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

    /* ===== Mostrar / ocultar el menú lateral en escritorio =====
       El menú arranca desplegado; si el usuario lo oculta se recuerda.
       En móvil no se toca: ahí sigue mandando la clase .open del tema. */
    (function () {

        var ESCRITORIO = 1200;
        var $cuerpo = $('body');

        function esEscritorio() {
            return $(window).width() >= ESCRITORIO;
        }

        // Restaurar preferencia. Por defecto: oculto si prefieren auto-hide, pero
        // usaremos el localStorage para recordar si lo abrieron.
        if (localStorage.getItem('menuOculto') === '1') {
            $cuerpo.addClass('menu-oculto');
        }

        $('#btnMenu').on('click', function (e) {
            if (!esEscritorio()) { return; }

            // El tema base tiene su propio handler sobre .menu-icon que activa
            // .open y el velo .mobile-menu-overlay, pensados para móvil. En
            // escritorio eso solo oscurece la pantalla, asi que se corta aqui.
            // Este handler se registra antes que el del tema, por lo que
            // stopImmediatePropagation() evita que aquel llegue a ejecutarse.
            e.stopImmediatePropagation();
            $('.left-side-bar').removeClass('open');
            $('.mobile-menu-overlay').removeClass('show');

            var oculto = !$cuerpo.hasClass('menu-oculto');
            $cuerpo.toggleClass('menu-oculto', oculto);
            localStorage.setItem('menuOculto', oculto ? '1' : '0');
        });

        // Ocultar menú automáticamente al hacer clic fuera (en el main-container)
        $(document).on('click', function(e) {
            if (esEscritorio() && !$cuerpo.hasClass('menu-oculto')) {
                // Si el clic no fue dentro del menú ni en el botón superior
                if (!$(e.target).closest('.left-side-bar, #btnMenu').length) {
                    $cuerpo.addClass('menu-oculto');
                    localStorage.setItem('menuOculto', '1');
                }
            }
        });

        $(window).on('resize', function() {});
    })();

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

/* =========================================================
   PALETA INSTITUCIONAL
   El primario sale de config.municipio.php (MUNICIPIO_COLOR), igual
   que el nombre y los logos: cambiando ese archivo se remarca toda la
   aplicacion para otro municipio, sin tocar una linea de codigo.
   ========================================================= */
:root {
	--erp-primario:       <?php echo MUNICIPIO_COLOR; ?>;
	--erp-primario-hover: <?php echo MUNICIPIO_COLOR_OSCURO; ?>;
	--erp-primario-suave: #E6F6F5;
	--erp-primario-borde: #B9E5E2;
	--erp-texto:          #1F2937;
	--erp-texto-tenue:    #6B7280;
	--erp-borde:          #E5E7EB;
	--erp-fondo:          #F3F4F6;
}

/* ---------- Pantalla de carga ---------- */
.pre-loader .bar {
	background: var(--erp-primario) !important;
}

/* ---------- Header ---------- */
.header {
	background: var(--erp-primario) !important;
	border-bottom: none !important;
	box-shadow: none !important;
}

/* Sobre fondo de color, los textos del header van en blanco. */
.header .user-name,
.header .dropdown-toggle,
.header a:not(.dropdown-item) {
	color: #FFFFFF !important;
}

/* ---------- Sidebar ----------
   Mismo color que la cabecera para que se lean como una sola pieza.
   Antes el menú se veía plano: todos los niveles con el mismo peso,
   sin separación entre bloques y sin un estado activo claro. */

.left-side-bar {
	background: var(--erp-primario) !important;
	border-right: none;
	top: 70px !important;
	height: calc(100vh - 70px) !important;
}

/* Brand logo sidebar removed */

/* IMPORTANTE: .micon va con position:absolute; left:10px; width:42px.
   El padding-left de 67px es el hueco reservado para ese icono; si se
   reduce, el icono se monta encima del texto. */
.sidebar-menu .dropdown-toggle {
	color: #FFFFFF !important;
	font-size: 13.5px !important;
	font-weight: 500 !important;
	border-radius: 8px;
	margin: 2px 10px;
	padding: 12px 15px 12px 67px !important;
	transition: background .15s ease, color .15s ease;
}

.sidebar-menu .dropdown-toggle .micon {
	color: rgba(255, 255, 255, .85) !important;
	font-size: 19px !important;
	transition: color .15s ease;
}

.sidebar-menu .dropdown-toggle:hover {
	background: rgba(255, 255, 255, .14) !important;
	color: #FFFFFF !important;
}

.sidebar-menu .dropdown-toggle:hover .micon,
.sidebar-menu .show > .dropdown-toggle .micon,
.sidebar-menu li.active > .dropdown-toggle .micon,
.sidebar-light .sidebar-menu > ul > li > .dropdown-toggle.active .micon {
	color: #FFFFFF !important;
}

/* Estado activo: fondo blanco translucido y barra solida a la izquierda,
   que sobre un sidebar de color se lee mejor que un simple cambio de tono. */
.sidebar-menu li.active > .dropdown-toggle,
.sidebar-menu .show > .dropdown-toggle {
	background: rgba(255, 255, 255, .22) !important;
	color: #FFFFFF !important;
	font-weight: 700 !important;
	box-shadow: inset 3px 0 0 #FFFFFF;
}

/* Submenús: un punto de jerarquía por debajo, sin gritar */
.sidebar-menu .submenu a {
	font-size: 13px;
	font-weight: 400;
	color: rgba(255, 255, 255, .82) !important;
}

.sidebar-menu .submenu a:hover {
	color: #FFFFFF !important;
	background: rgba(255, 255, 255, .10) !important;
	border-radius: 6px;
}

.sidebar-menu .submenu .submenu-icon {
	width: 16px;
	text-align: center;
	margin-right: 6px;
	opacity: .75;
}

/* Separación entre bloques de primer nivel */
.sidebar-menu > ul > li {
	margin-bottom: 2px;
}

/* La flecha del desplegable estaba muy marcada */
.sidebar-menu ul li.dropdown > a:after {
	opacity: .45;
}

/* ================== ESTADO COLLAPSED (SIDEBAR-SHRINK) ================== */
body.sidebar-shrink .left-side-bar .brand-logo img {
	width: 45px !important;
	height: 45px !important;
	padding: 2px !important;
}

body.sidebar-shrink .left-side-bar .brand-logo span {
	display: none !important;
}

body.sidebar-shrink .sidebar-footer .hide-on-shrink {
	display: none !important;
}

body.sidebar-shrink .sidebar-footer {
	padding: 15px 0 !important;
}

body.sidebar-shrink #btnMenuSidebar {
	margin-bottom: 0 !important;
}

/* ---------- Contenido ---------- */
.main-container {
	background: var(--erp-fondo);
}

/* ---------- Boton de menu ---------- */
.menu-icon {
	cursor: pointer;
	color: rgba(255, 255, 255, .9) !important;
	font-size: 20px !important;
	transition: opacity .15s ease;
	display: inline-block !important;
}
.menu-icon:hover {
	opacity: .75;
}

/* ---------- Ocultar / mostrar el menu en escritorio ----------
   Por defecto el menu queda desplegado. El tema base solo sabe abrirlo
   y cerrarlo en movil (clase .open); en escritorio el boton no hacia
   nada. Estas reglas anaden el colapso real, recordado en localStorage. */
@media (min-width: 1200px) {

	/* style.css esconde el sidebar (left:-281px) dentro de un
	   @media (max-width:5000px), que en la practica aplica SIEMPRE. Por eso
	   el menu nunca aparecia desplegado y el boton lo abria como overlay
	   con velo oscuro. Aqui se devuelve a su sitio en escritorio. */
	.left-side-bar {
		left: 0 !important;
		transition: transform .2s ease;
	}

	/* La X de cerrar es para el overlay movil; en escritorio sobra,
	   el boton del header ya hace esa funcion. */
	.left-side-bar .close-sidebar {
		display: none !important;
	}

	.main-container {
		padding-left: 300px;
		transition: padding-left .2s ease;
	}

	/* El velo solo tiene sentido cuando el menu flota sobre el contenido. */
	.mobile-menu-overlay {
		display: none !important;
	}

	body.menu-oculto .left-side-bar {
		transform: translateX(-100%);
	}

	body.menu-oculto .main-container {
		padding-left: 20px !important;
	}
}

/* ---------- Mejoras visuales en Tablas (Botones más grandes y fondo) ---------- */
.card-box {
	background-color: #ffffff;
	box-shadow: 0 4px 15px rgba(0,0,0,0.03) !important;
	border-radius: 8px;
	border: 1px solid rgba(0,0,0,0.05);
}

.data-table .btn-sm {
	padding: 8px 12px !important;
	font-size: 14px !important;
	border-radius: 6px !important;
	margin: 2px !important;
	box-shadow: 0 2px 4px rgba(0,0,0,0.08);
}

.data-table .btn-sm i {
	font-size: 16px !important;
}

table.dataTable.stripe tbody tr.odd {
	background-color: #fdfdfd !important;
}
table.dataTable tbody tr:hover {
	background-color: #f4f8f8 !important;
}

</style>