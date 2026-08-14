<?php
require_once '../business/globals.php';
include_once('../business/class.sessions.php');

// Cargar configuración del municipio. Ubicación real (Plesk/producción): un
// nivel arriba de /erpsoftsas; fallback dentro de /erpsoftsas solo para
// Docker local (ver business/globals.php, que ya se incluyó arriba).
$configPath = dirname(dirname(__DIR__)) . '/config.municipio.php';
if (!file_exists($configPath)) {
    $configPath = dirname(__DIR__) . '/config.municipio.php';
}
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
					<a class="dropdown-item" href="javascript:void(0)" id="btnCambiarClave"><i class="dw dw-lock"></i>Cambiar Contraseña</a>
					<a class="dropdown-item" href="javascript:void(0)" id="btnCerrarSesion"><i class="dw dw-logout" ></i>Cerrar Sesión </a>
				</div>
			</div>
		</div>

		<!-- Modal Cambiar Contraseña. Antes NINGUNA pantalla de cara al rol
		     contribuyente permitia esto: la unica via era el reseteo por
		     correo (clave temporal generada por el sistema), sin forma de
		     volver a asignar una propia despues. -->
		<div class="modal fade" id="modal-CambiarClave" tabindex="-1" role="dialog" aria-hidden="true">
			<div class="modal-dialog" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title">Cambiar Contraseña</h5>
						<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
					</div>
					<form id="formCambiarClave" onsubmit="MenuUsuario.postCambiarClave(); return false;">
						<div class="modal-body">
							<div class="form-group">
								<label>* Contraseña Actual</label>
								<input type="password" class="form-control" id="cc_ClaveActual" required>
							</div>
							<div class="form-group">
								<label>* Nueva Contraseña</label>
								<input type="password" class="form-control" id="cc_ClaveNueva" required>
							</div>
							<div class="form-group">
								<label>* Confirmar Nueva Contraseña</label>
								<input type="password" class="form-control" id="cc_ClaveNuevaConfirmar" required>
							</div>
							<div style="font-size: 13px;">
								<div id="cc_req-length" class="text-danger">• Mínimo 8 caracteres</div>
								<div id="cc_req-upper" class="text-danger">• Al menos una mayúscula</div>
								<div id="cc_req-lower" class="text-danger">• Al menos una minúscula</div>
								<div id="cc_req-number" class="text-danger">• Al menos un número</div>
							</div>
						</div>
						<div class="modal-footer">
							<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
							<button type="submit" class="btn btn-success" id="btnGuardarCambiarClave">Guardar</button>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- El panel lateral "Configuracion Visual" (Header/Sidebar White-Dark y
     "Reset Settings") venia con la plantilla comprada. En un portal
     tributario municipal no aporta nada -y deja cambiar los colores
     institucionales-, asi que se retira. layout-settings.js sigue
     cargando sin problema: sus selectores simplemente no encuentran
     nada y no hacen nada. -->

<!-- ========== SIDEBAR / MENÚ LATERAL ========== -->
<div class="left-side-bar">
	<div class="menu-block customscroll" style="padding-bottom: 150px;">
		<div class="sidebar-menu">
		
			<ul id="accordion-menu">

				<!-- INICIO -->
				<li class="dropdown" id="MInicio">
					<a href="dashboard.php" class="dropdown-toggle no-arrow">
						<span class="micon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
						<span class="mtext">Inicio</span>
					</a>
				</li>

				<!-- RIT: el cliente pidio sacarlo de "Industria y Comercio" porque el
				     Registro de Informacion Tributaria aplica a TODOS los modulos, no
				     solo a ICA. Va de primero, justo despues de Inicio. -->
				<li class="dropdown" id="MRIT">
					<a id="ICAWeb_RIT" onclick="menu.validarIngreso(1641,101)" class="dropdown-toggle no-arrow" style="cursor:pointer;" title="Registro de Identificación Tributaria">
						<span class="micon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><line x1="7" y1="8" x2="17" y2="8"/><line x1="7" y1="12" x2="17" y2="12"/><line x1="7" y1="16" x2="13" y2="16"/></svg></span>
						<!-- El nombre completo no cabe en el ancho del menu lateral y se
						     veia cortado a la mitad ("Registro de Identificación Tri...").
						     Se deja "RIT" aqui (con el nombre completo como title, y ya
						     escrito completo como titulo de la propia pagina) y se
						     agrega un item nuevo de Establecimientos justo debajo, para
						     que se encuentre sin tener que buscarlo dentro de
						     Administración ICA > Procesos. -->
						<span class="mtext">RIT</span>
					</a>
				</li>

				<li class="dropdown" id="MEstablecimientos">
					<a id="ICAWeb_Establecimientos" onclick="menu.validarIngreso(1640,7)" class="dropdown-toggle no-arrow" style="cursor:pointer;">
						<span class="micon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-4 7 4v14"/><line x1="9" y1="9" x2="9" y2="9.01"/><line x1="9" y1="13" x2="9" y2="13.01"/><line x1="9" y1="17" x2="9" y2="17.01"/><line x1="15" y1="9" x2="15" y2="9.01"/><line x1="15" y1="13" x2="15" y2="13.01"/><line x1="15" y1="17" x2="15" y2="17.01"/></svg></span>
						<span class="mtext">Establecimientos</span>
					</a>
				</li>

				<!-- CONSULTAS EXTERNAS -->
				<li class="dropdown" id="MConsultasExternas">
					<a href="javascript:;" class="dropdown-toggle">
						<span class="micon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>
						<span class="mtext">Impuesto Predial</span>
					</a>

					<ul class="submenu" id="SubConsultasExternas">
						<li class="menu_1035">
							<a  id="ConsultasPazYSalvo" onclick="menu.validarIngreso(1035,100)">
								<i class="submenu-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></i> Consultas Paz y Salvo
							</a>
						</li>
					</ul>
				</li>

				<!-- ICA ALCALDÍA → ADMINISTRACIÓN ICA -->
				<li class="dropdown" id="MICAAlcaldia">
					<a href="javascript:;" class="dropdown-toggle">
						<span class="micon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg></span>
						<span class="mtext">Administración ICA</span>
					</a>

					<ul class="submenu" id="SubICAAlcaldia">
						<!-- DATOS BASICOS -->
						<li class="dropdown" id="MICA_DatosBasicos">
							<a href="javascript:;" class="dropdown-toggle">
								<i class="submenu-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></i> Datos Básicos
							</a>

							<ul class="submenu" id="SubICA_DatosBasicos">
								<li class="menu_1639">
									<a id="ICA_Contribuyentes" onclick="menu.validarIngreso(1639,4)">
										<i class="submenu-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></i> Contribuyentes
									</a>
								</li>

								<li class="menu_1639">
									<a id="ICA_Actividades" onclick="menu.validarIngreso(1639,3)">
										<i class="submenu-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg></i> Actividades Comercio
									</a>
								</li>

								<li class="menu_1639">
									<a id="ICA_Conceptos" onclick="menu.validarIngreso(1639,6)">
										<i class="submenu-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41L13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg></i> Conceptos
									</a>
								</li>

								<li class="menu_1639">
									<a id="ICA_GrupoTarifario" onclick="menu.validarIngreso(1639,5)">
										<i class="submenu-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg></i> Grupo Tarifario
									</a>
								</li>

							</ul>
						</li>

						<!-- PROCESOS -->
						<li class="dropdown" id="MICA_Procesos">
							<a href="javascript:;" class="dropdown-toggle">
								<i class="submenu-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg></i> Procesos
							</a>

							<ul class="submenu" id="SubICA_Procesos">
								<li class="menu_1640">
									<a id="ICA_Establecimientos" onclick="menu.validarIngreso(1640,7)">
										<i class="submenu-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-4 7 4v14"/><line x1="9" y1="9" x2="9" y2="9.01"/><line x1="9" y1="13" x2="9" y2="13.01"/><line x1="9" y1="17" x2="9" y2="17.01"/><line x1="15" y1="9" x2="15" y2="9.01"/><line x1="15" y1="13" x2="15" y2="13.01"/><line x1="15" y1="17" x2="15" y2="17.01"/></svg></i> Establecimientos
									</a>
								</li>
							</ul>
						</li>
					</ul>
				</li>

				<!-- ICA WEB → INDUSTRIA Y COMERCIO -->
				<li class="dropdown" id="MICAWeb">
					<a href="javascript:;" class="dropdown-toggle">
						<span class="micon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg></span>
						<span class="mtext">Industria y Comercio</span>
					</a>

					<ul class="submenu" id="SubICAWeb">

						<li class="menu_1641">
							<a id="ICAWeb_Presentar" onclick="menu.validarIngreso(1641,103)">
								<i class="submenu-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg></i> Presentar Declaración
							</a>
						</li>

						<li class="menu_1641">
							<a id="ICAWeb_Declaraciones" onclick="menu.validarIngreso(1641,102)">
								<i class="submenu-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></i>
								Consultar Declaraciones
							</a>
						</li>

					</ul>
				</li>

				<!-- RETE ICA → RETENCIÓN ICA -->
				<li class="dropdown" id="MReteICA">
					<a href="javascript:;" class="dropdown-toggle">
						<span class="micon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18M8 3h8M5 8l-3 6a4 4 0 0 0 8 0z"/><path d="M19 8l-3 6a4 4 0 0 0 8 0z"/><path d="M3 8h5M16 8h5"/></svg></span>
						<span class="mtext">Retención ICA</span>
					</a>

					<ul class="submenu" id="SubReteICA">

						<li class="menu_1643">
							<a id="ReteICA_Declaraciones" onclick="menu.validarIngreso(1643,104)">
								<i class="submenu-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></i>
								Consultar Declaraciones
							</a>
						</li>

						<li class="menu_1643">
							<a id="ReteICA_Presentar" onclick="menu.validarIngreso(1643,105)">
								<i class="submenu-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg></i> Presentar Declaración
							</a>
						</li>

					</ul>
				</li>

				<!-- AUTO RETENCION → AUTO RETENCIÓN ICA -->
				<li class="dropdown" id="MAutoretencion">
					<a href="javascript:;" class="dropdown-toggle">
						<span class="micon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="5" x2="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg></span>
						<span class="mtext">Auto Retención ICA</span>
					</a>

					<ul class="submenu" id="SubAutoretencion">

						<li class="menu_1644">
							<a id="AutoRet_Declaraciones" onclick="menu.validarIngreso(1644,106)">
								<i class="submenu-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></i> Consultar Declaraciones
							</a>
						</li>

						<li class="menu_1644">
							<a id="AutoRet_Presentar" onclick="menu.validarIngreso(1644,107)">
								<i class="submenu-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg></i> Presentar Declaración
							</a>
						</li>

					</ul>
				</li>

				<!-- CONFIGURACION -->
				<li class="dropdown" id="MConfig">
					<a href="javascript:;" class="dropdown-toggle">
						<span class="micon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span>
						<span class="mtext">Configuración</span>
					</a>

					<ul class="submenu" id="SubConfig">
						<li class="menu_26">
							<a id="Config_Usuarios" onclick="menu.validarIngreso(26,1)">
								<i class="submenu-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="10" r="3"/><path d="M7 20.5a5 5 0 0 1 10 0"/></svg></i> Usuarios
							</a>
						</li>

						<li class="menu_11">
							<a id="Config_Roles" onclick="menu.validarIngreso(11,2)">
								<i class="submenu-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="7" cy="15" r="4"/><path d="M10.5 11.5L21 1"/><path d="M16 6l3 3"/><path d="M19 3l3 3"/></svg></i> Roles
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

    /**
     * MenuUsuario: cambio de contraseña propio (punto 1 solicitado por el
     * cliente). Antes solo existia el reseteo por correo con clave temporal
     * generada por el sistema, sin forma de asignar una propia despues.
     * Vive aca (inline en menu.php) porque este dropdown de usuario -y el
     * modal que lo acompaña- se incluye igual en TODAS las pantallas
     * internas, y ya es el patron que sigue este mismo archivo para
     * "Cerrar Sesión".
     */
    var MenuUsuario = (function () {

        function validarPassword(clave) {
            var okLength = clave.length >= 8;
            var okUpper = /[A-Z]/.test(clave);
            var okLower = /[a-z]/.test(clave);
            var okNumber = /[0-9]/.test(clave);

            $("#cc_req-length").toggleClass('text-success', okLength).toggleClass('text-danger', !okLength);
            $("#cc_req-upper").toggleClass('text-success', okUpper).toggleClass('text-danger', !okUpper);
            $("#cc_req-lower").toggleClass('text-success', okLower).toggleClass('text-danger', !okLower);
            $("#cc_req-number").toggleClass('text-success', okNumber).toggleClass('text-danger', !okNumber);

            return okLength && okUpper && okLower && okNumber;
        }

        function abrir() {
            $("#formCambiarClave").trigger("reset");
            $("#cc_req-length, #cc_req-upper, #cc_req-lower, #cc_req-number")
                .removeClass("text-success").addClass("text-danger");
            $('#modal-CambiarClave').modal({ backdrop: 'static', keyboard: false });
            $('#modal-CambiarClave').modal('show');
        }

        function postCambiarClave() {
            var claveActual = $("#cc_ClaveActual").val();
            var claveNueva = $("#cc_ClaveNueva").val();
            var claveConfirmar = $("#cc_ClaveNuevaConfirmar").val();
            var idUsuario = localStorage.getItem('id_Usuario');

            if (!validarPassword(claveNueva)) {
                swal({
                    type: 'warning',
                    title: 'Contraseña inválida',
                    text: 'La nueva contraseña debe tener mínimo 8 caracteres, incluir mayúscula, minúscula y número.'
                });
                return;
            }

            if (claveNueva !== claveConfirmar) {
                swal({
                    type: 'warning',
                    title: 'No coinciden',
                    text: 'La nueva contraseña y su confirmación no son iguales.'
                });
                return;
            }

            $("#btnGuardarCambiarClave").prop("disabled", true).text("Guardando...");

            $.ajax({
                url: '../business/controller/class.usuarios.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    funcion: 6,
                    usu_Id: idUsuario,
                    claveActual: claveActual,
                    claveNueva: claveNueva
                },
                success: function (arr) {
                    $("#btnGuardarCambiarClave").prop("disabled", false).text("Guardar");

                    if (arr.ok == 1) {
                        $("#modal-CambiarClave").modal('hide');
                        swal({ type: 'success', title: 'Listo', text: 'Su contraseña se actualizó correctamente.' });
                    } else {
                        swal({ type: 'error', title: 'No se pudo cambiar', text: arr.mensaje || 'Intente nuevamente.' });
                    }
                },
                error: function () {
                    $("#btnGuardarCambiarClave").prop("disabled", false).text("Guardar");
                    swal({ type: 'error', title: 'Error de conexión', text: 'No se pudo cambiar la contraseña.' });
                }
            });
        }

        $("#cc_ClaveNueva").on('input', function () { validarPassword($(this).val()); });
        $("#btnCambiarClave").click(abrir);

        return { postCambiarClave: postCambiarClave };
    })();

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
/* Estilos para resaltar el menú activo
   Antes el sangrado de cada nivel se hacia con padding-left en el <ul>
   del submenu (18px, +24px mas en el nivel 3), lo que ENCOGE la caja
   del <a> solo por la izquierda: su fondo de hover/activo terminaba
   pegado al borde derecho pero con un hueco a la izquierda, y por eso
   el resaltado de "Presentar Declaración" se veia como una pildora
   descuadrada en vez de una barra completa. Ahora el <ul> solo aporta
   un respiro simetrico (mismo padding a ambos lados) y TODO el sangrado
   jerarquico vive en el padding-left del propio <a>, que no encoge su
   caja: el fondo de hover/activo siempre llena el ancho completo. */
	.sidebar-menu .submenu {
		padding: 4px 6px !important;
	}

	.sidebar-menu .submenu a {
		padding-left: 26px !important;
	}

	.sidebar-menu .submenu .submenu a {
		padding-left: 40px !important;
	}

	/* Quitar guiones del nivel 3 */
	.sidebar-menu ul.submenu li a:before {
		display: none !important;
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
/* La pagina ya carga Google Fonts "Inter" (ver <link> en el <head> de cada
   pantalla), pero nunca se aplicaba de verdad al menu: sin un font-family
   explicito aqui, el sidebar caia al stack por defecto de Bootstrap
   (-apple-system, Segoe UI...) en vez de usar la fuente que se penso para
   el resto de la marca. De paso se sube un poco el tamano (13.5px -> 14px)
   y el letter-spacing para que se lea mejor sobre el fondo teal. */
.sidebar-menu .dropdown-toggle {
	color: #FFFFFF !important;
	font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
	font-size: 14px !important;
	font-weight: 500 !important;
	letter-spacing: 0.1px;
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

/* Iconos SVG (estilo trazo, como el mockup aprobado) en vez de
   Font Awesome: .micon/.submenu-icon ya estaban posicionados para
   iconos de fuente, asi que el SVG toma su tamano explicitamente. */
.sidebar-menu .micon svg {
	width: 19px;
	height: 19px;
	stroke: currentColor;
	display: block;
}

.sidebar-menu .submenu-icon svg {
	width: 15px;
	height: 15px;
	stroke: currentColor;
	display: inline-block;
	vertical-align: -3px;
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

/* Submenu ABIERTO: antes solo el link del padre cambiaba un poco de
   tono (rgba blanco 22%), facil de pasar por alto sobre un sidebar que
   ya es todo teal. Ahora todo el bloque (padre + hijos) pasa a un teal
   solido mas oscuro, para que se note de inmediato en cual seccion
   estas sin tener que leer el texto. */
.sidebar-menu li.dropdown.show {
	background: var(--erp-oscuro);
	border-radius: 8px;
	margin: 2px 10px;
}

.sidebar-menu li.dropdown.show > .dropdown-toggle {
	margin: 0;
	background: transparent !important;
}

.sidebar-menu li.dropdown.show > .submenu {
	background: rgba(0, 0, 0, .14);
	border-radius: 0 0 8px 8px;
	padding-top: 4px;
	padding-bottom: 4px;
}

/* Submenús: un punto de jerarquía por debajo, sin gritar */
.sidebar-menu .submenu a {
	font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
	font-size: 13.5px;
	font-weight: 400;
	color: rgba(255, 255, 255, .82) !important;
}

.sidebar-menu .submenu a:hover {
	color: #FFFFFF !important;
	background: rgba(255, 255, 255, .10) !important;
	border-radius: 6px;
}

.sidebar-menu .submenu a.active {
	color: #FFFFFF !important;
	background: rgba(255, 255, 255, .18) !important;
	border-radius: 6px;
	font-weight: 600;
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

/* La flecha del desplegable indica si un item se puede abrir y si ya
   esta abierto (chevron abajo/arriba). Antes quedaba casi invisible al
   45% de opacidad, que era precisamente lo que hacia dificil notar que
   el menu se podia desplegar. */
.sidebar-menu ul li.dropdown > a:after {
	opacity: .85;
	font-size: 13px;
	transition: opacity .15s ease;
}

.sidebar-menu ul li.dropdown > a:hover:after,
.sidebar-menu ul li.dropdown.show > a:after {
	opacity: 1;
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
	width: 32px !important;
	height: 32px !important;
	padding: 0 !important;
	display: inline-flex !important;
	align-items: center;
	justify-content: center;
	font-size: 14px !important;
	border-radius: 8px !important;
	margin: 2px !important;
	box-shadow: 0 2px 4px rgba(0,0,0,0.08);
	transition: transform .12s ease, box-shadow .12s ease;
}

.data-table .btn-sm:hover:not(:disabled) {
	transform: scale(1.08);
	box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.data-table .btn-sm i {
	font-size: 15px !important;
}

table.dataTable.stripe tbody tr.odd {
	background-color: #fdfdfd !important;
}
table.dataTable tbody tr:hover {
	background-color: #f4f8f8 !important;
}

/* =========================================================
   ESTADO DE LA DECLARACION
   El color nunca viaja solo: cada estado lleva punto + texto,
   para que se siga entendiendo impreso en blanco y negro o
   por alguien con daltonismo.
   ========================================================= */
.chip-estado {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	font-size: 12px;
	font-weight: 600;
	line-height: 1;
	padding: 5px 11px 5px 9px;
	border-radius: 999px;
	border: 1px solid;
	white-space: nowrap;
}

.chip-estado::before {
	content: "";
	width: 7px;
	height: 7px;
	border-radius: 50%;
	background: currentColor;
	flex: none;
}

.chip-estado.est-borrador   { color: #6B7280; border-color: #D1D5DB; background: #F9FAFB; }
.chip-estado.est-firmada    { color: var(--erp-primario-hover); border-color: var(--erp-primario); background: var(--erp-primario-suave); }
.chip-estado.est-presentada { color: #1B6E45; border-color: #1B6E45; background: #ECFDF3; }
.chip-estado.est-pagada     { color: #14532D; border-color: #14532D; background: #DCFCE7; }

/* =========================================================
   BARRA DE PROGRESO DEL TRAMITE
   ========================================================= */
.stepper-tramite {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
	margin: 0 0 14px;
}

.stepper-tramite .paso {
	flex: 1 1 110px;
	min-width: 100px;
	display: flex;
	align-items: center;
	gap: 7px;
	padding: 7px 10px;
	border: 1px solid #E5E7EB;
	border-radius: 6px;
	background: #FFFFFF;
	font-size: 12.5px;
}

.stepper-tramite .paso-n {
	width: 19px;
	height: 19px;
	flex: none;
	border-radius: 50%;
	background: #E5E7EB;
	color: #6B7280;
	font-size: 11px;
	font-weight: 700;
	display: inline-flex;
	align-items: center;
	justify-content: center;
}

.stepper-tramite .paso-t { font-weight: 500; color: #6B7280; }

.stepper-tramite .paso.done {
	border-color: var(--erp-primario-borde);
	background: var(--erp-primario-suave);
}
.stepper-tramite .paso.done .paso-n {
	background: var(--erp-primario);
	color: #FFFFFF;
}
.stepper-tramite .paso.done .paso-t { color: var(--erp-primario-hover); font-weight: 600; }

.stepper-tramite .paso.now {
	border-color: var(--erp-primario);
	border-width: 2px;
	padding: 6px 9px;
	box-shadow: 0 0 0 3px rgba(31, 164, 157, .13);
}
.stepper-tramite .paso.now .paso-n {
	background: var(--erp-primario);
	color: #FFFFFF;
}
.stepper-tramite .paso.now .paso-t { color: var(--erp-texto); font-weight: 700; }

.stepper-tramite .paso.todo { opacity: .55; }

/* =========================================================
   FILTROS DEL LISTADO DE DECLARACIONES
   ========================================================= */
.filtros-declaraciones {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-end;
	gap: 12px;
	padding: 12px 14px;
	margin-bottom: 14px;
	background: #F9FAFB;
	border: 1px solid #E5E7EB;
	border-radius: 6px;
}

.filtros-declaraciones .campo { display: flex; flex-direction: column; gap: 4px; }

.filtros-declaraciones label {
	font-size: 11px;
	font-weight: 600;
	letter-spacing: .04em;
	text-transform: uppercase;
	color: var(--erp-texto-tenue);
	margin: 0;
}

.filtros-declaraciones select {
	min-width: 140px;
	height: 34px;
	font-size: 13px;
	padding: 0 8px;
	border: 1px solid #D1D5DB;
	border-radius: 5px;
	background: #FFFFFF;
	color: var(--erp-texto);
}

.filtros-declaraciones .conteo {
	margin-left: auto;
	font-size: 12.5px;
	color: var(--erp-texto-tenue);
}

/* =========================================================
   ESTADOS VACIOS / DE ERROR
   ========================================================= */
.estado-vacio {
	padding: 34px 20px;
	text-align: center;
	color: var(--erp-texto-tenue);
}
.estado-vacio .ev-icono { font-size: 30px; opacity: .35; margin-bottom: 10px; }
.estado-vacio .ev-titulo {
	font-size: 14.5px;
	font-weight: 600;
	color: var(--erp-texto);
	margin-bottom: 4px;
}
.estado-vacio .ev-texto { font-size: 13px; max-width: 380px; margin: 0 auto; }
.estado-vacio.es-error .ev-icono,
.estado-vacio.es-error .ev-titulo { color: #B4341F; opacity: 1; }

/* Texto solo para lectores de pantalla */
.sr-only {
	position: absolute;
	width: 1px; height: 1px;
	padding: 0; margin: -1px;
	overflow: hidden;
	clip: rect(0,0,0,0);
	white-space: nowrap;
	border: 0;
}
</style>

<script>
/*
 * Red de seguridad global para peticiones AJAX fallidas, para TODAS las
 * pantallas (menu.php lo incluyen las 22). Antes solo existia en
 * declaraciones.ui.js, que apenas cargan Consultar/Presentar: en el resto,
 * una peticion caida (500, timeout, JSON invalido) dejaba la pantalla muda
 * -asi fue como el boton "Liquidar" parecio muerto durante meses-.
 *
 * jQuery se carga al FINAL de cada pagina (despues de este include), por eso
 * el registro se difiere a window.load. La bandera window.__erpRedAjax evita
 * doble registro donde declaraciones.ui.js ya la instala.
 */
window.addEventListener('load', function () {
	if (window.__erpRedAjax || typeof jQuery === 'undefined') { return; }
	window.__erpRedAjax = true;
	jQuery(document).ajaxError(function (event, jqxhr, settings) {
		jQuery('#loading').hide();
		jQuery('#wrapper').removeClass('body-load');
		if (typeof swal === 'function') {
			swal({
				type: 'error',
				title: 'Error de conexión',
				text: 'No se pudo completar la solicitud. Intenta de nuevo; si persiste, avisa a soporte.'
			});
		}
		if (window.console && console.error) {
			console.error('AJAX fallido:', settings && settings.url, jqxhr && jqxhr.status, jqxhr && jqxhr.responseText);
		}
	});
});
</script>

<style>

</style>