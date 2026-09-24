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

	/*
	 * El icono del titulo de la cabecera se CLONA del menu lateral (ver el
	 * bloque que llena #headerPageTitle mas abajo), pero fuera de .sidebar-menu
	 * la regla que dimensiona el SVG no aplica y el icono salia a ~168px: era el
	 * "globo" gigante que aparecia sobre Establecimientos y las demas pantallas
	 * de PRIMER NIVEL -las de submenu usan una flecha, no un SVG, por eso no lo
	 * mostraban-. Se acota el tamaño aqui.
	 */
	#headerPageTitle .micon { display: inline-flex; align-items: center; }
	#headerPageTitle .micon svg { width: 18px; height: 18px; }

	/*
	 * BOTONES DE ACCION EN TARJETA (icono + texto).
	 * Pedido del cliente 2026-09-14: los botones de las filas dejan de ser
	 * iconos sueltos y pasan a tarjetas con icono arriba y su nombre debajo,
	 * A COLOR cuando la accion esta disponible y en GRIS (.acc-off) cuando no.
	 * Solo cambia la forma: mismos botones, mismas funciones. Vive aqui porque
	 * lo comparten los TRES modulos -ICA (core/declaraciones.ui.js) y
	 * Retencion/Autorretencion (core/retenciones.js)-, que ya incluyen menu.php.
	 */
	.acc-cards {
		/* nowrap: el cliente pidió que quepan TODOS en una fila. La columna de
		   acciones se ensancha lo necesario; en pantallas angostas la tabla ya
		   tiene scroll horizontal (.table-responsive). */
		display: inline-flex; flex-wrap: nowrap; gap: 4px; justify-content: center;
		vertical-align: middle;
	}
	.acc-card {
		display: inline-flex; flex-direction: column; align-items: center; justify-content: center;
		width: 50px; min-height: 44px; padding: 4px 3px; gap: 2px;
		border: 1px solid transparent; border-radius: 7px;
		font-size: 9px; font-weight: 600; line-height: 1.1; text-align: center;
		cursor: pointer; text-decoration: none; background: none;
		transition: transform .08s ease, box-shadow .12s ease, filter .12s ease;
	}
	.acc-card i { font-size: 14px; line-height: 1; }
	.acc-card .acc-lbl { display: block; white-space: normal; }
	.acc-card:hover { transform: translateY(-1px); box-shadow: 0 2px 7px rgba(0,0,0,.14); text-decoration: none; filter: brightness(1.03); }
	.acc-card:focus-visible { outline: 2px solid rgba(0,0,0,.28); outline-offset: 1px; }

	/* Activo = a color (relleno suave + icono/texto y borde del color de la accion). */
	.acc-card.acc-info      { color:#0b7285; background:#e3fafc; border-color:#c5f0f5; }
	.acc-card.acc-warning   { color:#a5680a; background:#fff6e6; border-color:#ffe3b3; }
	.acc-card.acc-primary   { color:#1864ab; background:#e7f1ff; border-color:#c9deff; }
	.acc-card.acc-success   { color:#2b8a3e; background:#e9f8ee; border-color:#c3eccf; }
	.acc-card.acc-danger    { color:#c92a2a; background:#ffecec; border-color:#ffd0d0; }
	.acc-card.acc-secondary { color:#4c4fbf; background:#eeefff; border-color:#d7d9ff; }

	/* Inhabilitado = gris, sin click. */
	.acc-card.acc-off,
	.acc-card[disabled],
	.acc-card[aria-disabled="true"] {
		color:#adb5bd !important; background:#f4f5f6 !important; border-color:#e6e8ea !important;
		cursor: not-allowed; pointer-events: none; box-shadow: none; filter: none; transform: none;
	}

	/* Hover a color pleno para el que va a pulsar (el activo, no el gris). */
	.acc-card.acc-info:hover      { background:#0b7285; color:#fff; border-color:#0b7285; }
	.acc-card.acc-warning:hover   { background:#a5680a; color:#fff; border-color:#a5680a; }
	.acc-card.acc-primary:hover   { background:#1864ab; color:#fff; border-color:#1864ab; }
	.acc-card.acc-success:hover   { background:#2b8a3e; color:#fff; border-color:#2b8a3e; }
	.acc-card.acc-danger:hover    { background:#c92a2a; color:#fff; border-color:#c92a2a; }
	.acc-card.acc-secondary:hover { background:#4c4fbf; color:#fff; border-color:#4c4fbf; }

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
		<!-- El escudo es el botón del menú en TODOS los anchos: en escritorio lo
		     oculta/muestra y en ventanas angostas (< 1200 px) lo abre flotando. Hubo
		     un ☰ aparte para las angostas y el cliente pidió quitarlo (2026-09-24:
		     "se ve tan amateur"). -->
		<div id="btnMenu" role="button" tabindex="0" aria-label="Mostrar u ocultar el menú" style="display: flex; align-items: center; gap: 0.75rem; margin-left: 1rem; cursor: pointer; padding: 5px; border-radius: 6px; transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'" title="Mostrar u ocultar el menú lateral">
			<img src="<?php echo MUNICIPIO_LOGO; ?>" alt="Escudo" style="width: 55px; height: 55px; border-radius: 4px; object-fit: contain;">
			<div class="marca-texto">
				<div class="marca-nombre" style="font-size: 14px; font-weight: 700; color: #FFFFFF; line-height: 1.2;"><?php echo MUNICIPIO_NOMBRE; ?></div>
				<div style="font-size: 11px; color: rgba(255,255,255,.85);">Industria y Comercio</div>
			</div>
		</div>

		<!-- Título dinámico de la página actual -->
		<div class="header-separador" style="width: 1px; height: 25px; background: rgba(255,255,255,0.3); margin: 0 20px;"></div>
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
		
			<ul id="accordion-menu" class="menu-cargando">

				<!-- INICIO -->
				<li class="dropdown" id="MInicio">
					<a href="dashboard.php" class="dropdown-toggle no-arrow">
						<span class="micon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
						<span class="mtext">Inicio</span>
					</a>
				</li>

				<!-- MENÚ DEL ADMINISTRADOR (retro cliente 2026-09-24: "tiene muchas cosas
				     repetidas"). Arriba la puerta de entrada de la Alcaldía -Contribuyentes-;
				     debajo, el bloque del contribuyente que se gestiona (RIT … Autorretención),
				     que ContribActivo le muestra al rol 1 solo mientras gestiona a alguien; al
				     final, lo que no depende de un contribuyente. Para el contribuyente (rol 4)
				     el orden visible NO cambia: sus cinco módulos siguen en el mismo orden y lo
				     demás no lo ve (no tiene esos permisos). -->

				<!-- CONTRIBUYENTES: antes en Administración ICA > Datos Básicos > Contribuyentes. -->
				<li class="dropdown menu_1639" id="MContribuyentes">
					<a id="ICA_Contribuyentes" onclick="menu.validarIngreso(1639,4)" class="dropdown-toggle no-arrow" style="cursor:pointer;" title="Buscar un contribuyente y gestionarlo">
						<span class="micon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
						<span class="mtext">Contribuyentes</span>
					</a>
				</li>

				<!-- ESTABLECIMIENTOS (todos los del municipio): directorio de la Alcaldía
				     (retro cliente 2026-09-24). No reemplaza al "Establecimientos" del
				     bloque del contribuyente gestionado, que es donde se editan. -->
				<li class="dropdown menu_1639" id="MEstablecimientosTodos">
					<a id="ICA_EstablecimientosTodos" onclick="menu.validarIngreso(1639,13)" class="dropdown-toggle no-arrow" style="cursor:pointer;" title="Todos los establecimientos del municipio">
						<span class="micon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-4 7 4v14"/><line x1="9" y1="9" x2="9" y2="9.01"/><line x1="9" y1="13" x2="9" y2="13.01"/><line x1="9" y1="17" x2="9" y2="17.01"/><line x1="15" y1="9" x2="15" y2="9.01"/><line x1="15" y1="13" x2="15" y2="13.01"/><line x1="15" y1="17" x2="15" y2="17.01"/></svg></span>
						<span class="mtext">Establecimientos</span>
					</a>
				</li>

				<!-- Rótulo "Gestionando a X" del bloque del contribuyente. Sin clase menu_:
				     solo lo muestra ContribActivo (rol 1, con alguien elegido). -->
				<li class="menu-gestionando" id="MGestionando" style="display: none;">
					<span class="menu-gestionando-titulo">Gestionando a</span>
					<span class="menu-gestionando-nombre"></span>
				</li>

				<!-- RIT: el cliente pidio sacarlo de "Industria y Comercio" porque el
				     Registro de Informacion Tributaria aplica a TODOS los modulos, no
				     solo a ICA. Va de primero, justo despues de Inicio. -->
				<!-- La clase menu_XXXX NO es decorativa: menu.js
				     (mostrarMenuPorPermisos) arranca ocultando TODO el menu y
				     solo vuelve a mostrar los <li> que tengan la clase
				     menu_<idBoton> de un permiso activo del rol. Un item sin
				     esa clase queda invisible para cualquier rol que no sea el
				     administrador (rol 1), que se muestra entero por atajo.
				     Por eso RIT y Establecimientos, que se agregaron como items
				     de primer nivel en la Fase 4, no aparecian para el usuario
				     externo aunque su rol SI tuviera los permisos 1641 y 1640. -->
				<li class="dropdown menu_1641" id="MRIT">
					<a id="ICAWeb_RIT" onclick="menu.validarIngreso(1641,101)" class="dropdown-toggle no-arrow" style="cursor:pointer;" title="Registro de Información Tributaria">
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

				<!-- ESTABLECIMIENTOS -->
				<!--
				  Historia, porque esto ya se movio antes y conviene no repetir la
				  discusion a ciegas:

				    2026-08 (punto 5)  se saco a primer nivel
				    2026-08-18         el cliente pidio devolverlo dentro de
				                       Industria y Comercio
				    2026-09-09         vuelve a primer nivel

				  El argumento de ahora NO es el de agosto. En agosto el unico
				  modulo era el ICA, asi que colgar Establecimientos de el era
				  razonable. Hoy los MISMOS establecimientos los usan tres modulos
				  -ICA, Retencion y Autorretencion-, y tenerlos dentro de uno de
				  los tres sugiere que pertenecen solo a ese. Van arriba, junto al
				  RIT, que es el otro dato transversal del contribuyente.

				  Sigue con el permiso 1640: mover el elemento en el menu no cambia
				  quien puede entrar. menu.js muestra `.menu_<boton>` y su
				  `li.dropdown` contenedor, asi que como elemento de primer nivel
				  la clase va en el propio <li>, igual que en el RIT.
				-->
				<li class="dropdown menu_1640" id="MEstablecimientos">
					<a id="ICAWeb_Establecimientos" onclick="menu.validarIngreso(1640,7)" class="dropdown-toggle no-arrow" style="cursor:pointer;" title="Establecimientos del contribuyente">
						<span class="micon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-4 7 4v14"/><line x1="9" y1="9" x2="9" y2="9.01"/><line x1="9" y1="13" x2="9" y2="13.01"/><line x1="9" y1="17" x2="9" y2="17.01"/><line x1="15" y1="9" x2="15" y2="9.01"/><line x1="15" y1="13" x2="15" y2="13.01"/><line x1="15" y1="17" x2="15" y2="17.01"/></svg></span>
						<span class="mtext">Establecimientos</span>
					</a>
				</li>

				<!-- ICA WEB → INDUSTRIA Y COMERCIO -->
				<li class="dropdown" id="MICAWeb">
					<a href="javascript:;" class="dropdown-toggle">
						<span class="micon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg></span>
						<span class="mtext">Industria y Comercio</span>
					</a>

					<ul class="submenu" id="SubICAWeb">

						<!-- Establecimientos ya NO esta aqui: subio a primer nivel, junto
						     al RIT. Ver la nota en ese bloque. -->

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
							<a id="ReteICA_Presentar" onclick="menu.validarIngreso(1643,105)">
								<i class="submenu-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg></i> Presentar Declaración
							</a>
						</li>

						<li class="menu_1643">
							<a id="ReteICA_Declaraciones" onclick="menu.validarIngreso(1643,104)">
								<i class="submenu-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></i>
								Consultar Declaraciones
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
							<a id="AutoRet_Presentar" onclick="menu.validarIngreso(1644,107)">
								<i class="submenu-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg></i> Presentar Declaración
							</a>
						</li>

						<li class="menu_1644">
							<a id="AutoRet_Declaraciones" onclick="menu.validarIngreso(1644,106)">
								<i class="submenu-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></i> Consultar Declaraciones
							</a>
						</li>

					</ul>
				</li>

				<!-- RECAUDO ICA: potestad exclusiva de la Alcaldía (marca declaraciones como
				     pagadas; el controlador exige rol 1 o 2). Antes dentro de Administración ICA. -->
				<li class="dropdown menu_1639" id="MRecaudo">
					<a id="ICA_Recaudo" onclick="menu.validarIngreso(1639,11)" class="dropdown-toggle no-arrow" style="cursor:pointer;" title="Recaudo por código de barras">
						<span class="micon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><line x1="6" y1="8" x2="6" y2="16"/><line x1="9" y1="8" x2="9" y2="16"/><line x1="13" y1="8" x2="13" y2="16"/><line x1="18" y1="8" x2="18" y2="16"/></svg></span>
						<span class="mtext">Recaudo ICA</span>
					</a>
				</li>

				<!-- PARÁMETROS ICA: antes "Administración ICA", con un tercer nivel "Datos
				     Básicos". Se aplana y se renombra para no tener dos "Configuración".
				     "Municipio y bancos" es configuracion.php: EAN de recaudo y cuentas de los
				     bancos (gobierna el código de barras de todo el municipio; el controlador
				     exige rol 1 o 2, no se confía en que el ítem no se vea). -->
				<li class="dropdown" id="MICAAlcaldia">
					<a href="javascript:;" class="dropdown-toggle">
						<span class="micon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/></svg></span>
						<span class="mtext">Parámetros ICA</span>
					</a>

					<ul class="submenu" id="SubICAAlcaldia">

						<li class="menu_1645">
							<a id="ICA_Configuracion" onclick="menu.validarIngreso(1645,12)" style="cursor:pointer;">
								<i class="submenu-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></i> Municipio y bancos
							</a>
						</li>

						<li class="menu_1639">
							<a id="ICA_Actividades" onclick="menu.validarIngreso(1639,3)">
								<i class="submenu-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg></i> Actividades
							</a>
						</li>

						<li class="menu_1639">
							<a id="ICA_Conceptos" onclick="menu.validarIngreso(1639,6)">
								<i class="submenu-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41L13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg></i> Conceptos
							</a>
						</li>

						<li class="menu_1639">
							<a id="ICA_GrupoTarifario" onclick="menu.validarIngreso(1639,5)">
								<i class="submenu-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg></i> Grupos tarifarios
							</a>
						</li>

					</ul>
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

				<!-- USUARIOS Y ROLES (antes "Configuración", que chocaba con la de ICA) -->
				<li class="dropdown" id="MConfig">
					<a href="javascript:;" class="dropdown-toggle">
						<span class="micon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/></svg></span>
						<span class="mtext">Usuarios y roles</span>
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
<style>
/* ------------------------------------------------------------------
   El menu arranca OCULTO.

   Antes se pintaba completo y core/menu.js lo escondia despues, ya con
   los permisos en la mano. En ese hueco -entre que el navegador pinta y
   el JS corre- el usuario alcanzaba a ver los modulos que no le tocan:
   Administracion ICA, Configuracion, etc. Se notaba sobre todo al
   cambiar de pantalla, que es cuando el menu se vuelve a pintar.

   Invirtiendo el orden no hay ningun instante en que se vea de mas:
   nace oculto y menu.js solo revela lo permitido (con display en linea,
   que pesa mas que esta regla).

   El contenedor lleva .menu-cargando y el JS se la quita al terminar;
   asi, si algun dia el JS falla, queda un menu vacio -un fallo visible-
   en vez de uno que enseña de mas sin que nadie se entere.
   ------------------------------------------------------------------ */
#accordion-menu.menu-cargando > li,
#accordion-menu.menu-cargando .submenu li { display: none !important; }
#accordion-menu > li { display: none; }
</style>

	<div class="sidebar-footer" style="position: absolute; bottom: 0; left: 0; width: 100%; padding: 15px; text-align: center; border-top: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.15);">
		<!-- El logo es azul oscuro casi negro y estaba sobre una caja
		     translucida (blanco al 8%) encima del teal del menu: no se leia.
		     El archivo "deskapp-logo-white.svg" no sirve, tiene los mismos
		     rellenos oscuros pese al nombre. Se pasa a blanco con un filtro
		     -brightness(0) lo vuelve negro solido, invert(1) lo pasa a
		     blanco-, que funciona con cualquier logo que pongan despues, y
		     se le quita la caja: sobre el teal el blanco solo se ve mejor. -->
		<div style="margin-bottom: 8px;">
			<img src="../vendors/images/deskapp-logo.svg" alt="ERPSoft S.A.S"
			     style="height: 38px; width: auto; object-fit: contain;
			            filter: brightness(0) invert(1); opacity: 0.92;">
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

    /* ============================================================
       CONTRIBUYENTE ACTIVO Y PESTAÑAS
       El administrador no tiene contribuyente propio: elige uno en la pantalla
       de Contribuyentes ("Gestionar") y a partir de ahí opera como ese
       contribuyente. La elección vive en localStorage.id_Contribuyente, de donde
       ya leen el RIT, establecimientos y las 3 declaraciones. Aquí se pinta la
       barra "Gestionando a X", el bloque de ese contribuyente en el menú lateral
       (sus módulos solo aparecen mientras se gestiona a alguien) y se evita
       entrar a un módulo sin haber elegido a nadie. Se inyecta con .text()/text
       nodes, nunca como HTML.

       localStorage es UNO para todas las pestañas del navegador (la sesión PHP
       también: es la misma cookie, así que guardarlo allá no cambiaría nada).
       Si en otra pestaña se elige otro contribuyente -o se entra con otra
       cuenta, que le pasa igual a un contador con varios clientes-, esta
       seguiría mostrando los datos del anterior pero guardando sobre el nuevo.
       Por eso cada pestaña recuerda con quién abrió y, si eso cambia por
       fuera, se detiene y pregunta con cuál seguir. Aplica a todos los roles.
       ============================================================ */
    var ContribActivo = (function () {

        var MODULOS = ['icawebrit.php', 'establecimientos.php', 'icawebpresentar.php',
                       'icawebconsultar.php', 'reteicapresentar.php', 'reteicaconsultar.php',
                       'autoretencionpresentar.php', 'autoretencionconsultar.php'];
        var pagina   = (location.pathname.split('/').pop() || '').toLowerCase();
        var enModulo = MODULOS.indexOf(pagina) !== -1;
        var esAdmin  = localStorage.getItem('id_Rol') == 1;   // solo el administrador gestiona a otros

        // localStorage guarda texto: null, 'null', 'undefined' y '' son "ninguno".
        function leer(llave) {
            var v = localStorage.getItem(llave);
            v = (v == null) ? '' : ('' + v).trim();
            return (v === 'null' || v === 'undefined') ? '' : v;
        }

        function actual() {
            return {
                id: leer('id_Contribuyente'), usuario: leer('id_Usuario'),
                nombre: leer('contribActivoNombre'), doc: leer('contribActivoDoc')
            };
        }

        var deEstaPestana = actual();   // con quién abrió ESTA pestaña
        var detenida = false;

        // Los módulos del contribuyente en el menú lateral. Para el administrador
        // no aparecen sin nadie elegido -no hay sobre quién trabajar- y con
        // alguien van un paso adentro, bajo su nombre (#MGestionando).
        var MENU_DEL_CONTRIBUYENTE = '#MRIT, #MEstablecimientos, #MICAWeb, #MReteICA, #MAutoretencion';

        function pintarMenu() {
            if (!esAdmin) { return; }
            var c = actual();
            $('#MGestionando .menu-gestionando-nombre').text(c.nombre || 'Contribuyente');
            $('#MGestionando').attr('title', c.nombre || '').toggle(!!c.id);
            $(MENU_DEL_CONTRIBUYENTE).toggle(!!c.id).toggleClass('en-gestion', !!c.id);
        }

        function pintarBarra() {
            pintarMenu();
            $('#barraContribActivo').remove();

            var c = actual();
            if (!esAdmin || !c.id) { return; }

            var $bar = $('<div id="barraContribActivo"></div>').css({
                background: '#fff8e1', 'border-bottom': '1px solid #f0d98c',
                padding: '8px 16px', display: 'flex', 'align-items': 'center',
                'justify-content': 'space-between', 'flex-wrap': 'wrap', gap: '8px',
                'font-size': '13px'
            });

            var $izq = $('<span style="color:#7a5b00;"></span>')
                .append('<i class="fa fa-user-circle-o" style="margin-right:6px;"></i>')
                .append(document.createTextNode('Gestionando a: '))
                .append($('<b></b>').text(c.nombre || 'Contribuyente'));
            if (c.doc) { $izq.append(document.createTextNode('  ·  ' + c.doc)); }

            var $der = $('<span></span>');
            $('<a href="contribuyentes.php">Cambiar</a>')
                .css({ color: '#1fa49d', 'font-weight': 600, 'margin-right': '16px' }).appendTo($der);
            $('<a href="#">Salir</a>')
                .css({ color: '#b03535', 'font-weight': 600 }).appendTo($der)
                .on('click', function (e) { e.preventDefault(); salir(); });

            $bar.append($izq).append($der);
            $('.main-container').prepend($bar);
        }

        /** Deja a c = {id, doc, nombre} como contribuyente activo de esta pestaña. */
        function fijar(c) {
            // El id va de ÚLTIMO: las otras pestañas reaccionan a él, y para
            // entonces el nombre y el documento ya son los del nuevo.
            localStorage.setItem('contribActivoNombre', c.nombre || '');
            localStorage.setItem('contribActivoDoc', c.doc || '');
            localStorage.setItem('id_Contribuyente', c.id);
            deEstaPestana = actual();
            pintarBarra();
        }

        function salir() {
            localStorage.removeItem('contribActivoNombre');
            localStorage.removeItem('contribActivoDoc');
            localStorage.setItem('id_Contribuyente', '');
            window.location = 'contribuyentes.php';
        }

        /* ---------- guardia entre pestañas ---------- */

        function revisar() {
            if (detenida) { return; }

            var ahora = actual();
            if (ahora.id === deEstaPestana.id && ahora.usuario === deEstaPestana.usuario) { return; }

            var otraCuenta = ahora.usuario !== deEstaPestana.usuario;

            // Fuera de los módulos (o si la pestaña no tenía a nadie) no hay
            // datos de un contribuyente en pantalla: si solo cambió el
            // contribuyente, basta con actualizar la barra.
            if (!otraCuenta && (!enModulo || !deEstaPestana.id)) {
                deEstaPestana = ahora;
                pintarBarra();
                return;
            }

            detener(ahora, otraCuenta);
        }

        function detener(ahora, otraCuenta) {
            detenida = true;

            var antes = deEstaPestana;
            var suNombre = antes.nombre || 'el contribuyente de esta pestaña';

            // Se recarga SIN los parámetros de la dirección: un ?id= de una
            // declaración del anterior no debe volver a abrirse bajo el nuevo.
            function recargarLimpia() { location.replace(location.pathname); }

            if (otraCuenta) {
                mostrarAviso('La sesión cambió en otra pestaña',
                    ['En otra pestaña se cerró la sesión o se entró con otra cuenta. ',
                     'Esta pestaña se detuvo para no guardar información a nombre de otra persona.'],
                    [{ texto: 'Recargar esta pestaña', accion: recargarLimpia }]);
                return;
            }

            var segunda = ahora.id
                ? { texto: 'Cambiar a ' + (ahora.nombre || 'el otro contribuyente'),
                    accion: recargarLimpia }
                : { texto: 'Ir a Contribuyentes',
                    accion: function () { location.href = 'contribuyentes.php'; } };

            mostrarAviso(ahora.id ? 'Cambiaste de contribuyente en otra pestaña'
                                  : 'Saliste de este contribuyente en otra pestaña',
                ahora.id
                    ? ['Esta pestaña tiene datos de ', { b: suNombre },
                       ', pero en otra pasaste a gestionar a ', { b: ahora.nombre || 'otro contribuyente' },
                       '. Elige con cuál seguir para no guardar nada en el equivocado.']
                    : ['Esta pestaña tiene datos de ', { b: suNombre },
                       ', pero en otra ya no lo estás gestionando. Elige cómo seguir.'],
                [{ texto: 'Seguir con ' + suNombre,
                   // Lo vuelve a dejar activo: ahora es la OTRA pestaña la que queda detenida.
                   accion: function () { fijar(antes); reanudar(); } },
                 segunda]);
        }

        var aviso = null, apagados = [];

        /** Aviso que tapa y bloquea la página (inert) hasta que se elija qué hacer. */
        function mostrarAviso(titulo, partes, botones) {
            aviso = document.createElement('div');
            aviso.setAttribute('role', 'alertdialog');
            aviso.setAttribute('aria-modal', 'true');
            aviso.setAttribute('aria-labelledby', 'avisoPestanaTitulo');
            aviso.style.cssText = 'position:fixed;top:0;right:0;bottom:0;left:0;z-index:2147483000;'
                + 'background:rgba(15,23,42,.55);display:flex;align-items:center;justify-content:center;padding:16px;';

            var caja = document.createElement('div');
            caja.style.cssText = 'background:#fff;color:#1f2937;border-radius:10px;max-width:440px;width:100%;'
                + 'padding:24px;box-shadow:0 12px 32px rgba(0,0,0,.25);font-size:14px;line-height:1.5;';

            var h = document.createElement('h5');
            h.id = 'avisoPestanaTitulo';
            h.textContent = titulo;
            h.style.cssText = 'margin:0 0 10px;font-size:17px;font-weight:600;color:#1f2937;';

            var p = document.createElement('p');
            p.style.cssText = 'margin:0 0 18px;';
            partes.forEach(function (x) {
                if (typeof x === 'string') { p.appendChild(document.createTextNode(x)); return; }
                var b = document.createElement('b');
                b.textContent = x.b;
                p.appendChild(b);
            });

            var fila = document.createElement('div');
            fila.style.cssText = 'display:flex;flex-direction:column;gap:8px;';
            botones.forEach(function (d, i) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = d.texto;
                btn.className = i === 0 ? 'btn' : 'btn btn-outline-secondary';
                btn.style.cssText = 'white-space:normal;text-align:center;'
                    + (i === 0 ? 'background:var(--erp-primario);border-color:var(--erp-primario);color:#fff;' : '');
                btn.addEventListener('click', d.accion);
                fila.appendChild(btn);
            });

            caja.appendChild(h);
            caja.appendChild(p);
            caja.appendChild(fila);
            aviso.appendChild(caja);

            // Todo lo demás queda inerte: ni clic ni teclado (Tab) llegan a un
            // botón "Guardar" de atrás, aunque haya un modal de la página abierto.
            apagados = [];
            Array.prototype.forEach.call(document.body.children, function (el) {
                if (!el.inert) { el.inert = true; apagados.push(el); }
            });
            document.body.appendChild(aviso);
            fila.firstChild.focus();
        }

        function reanudar() {
            apagados.forEach(function (el) { el.inert = false; });
            apagados = [];
            if (aviso) { aviso.remove(); aviso = null; }
            detenida = false;
        }

        // 'storage' llega cuando OTRA pestaña escribe (key null = localStorage.clear(),
        // que es el cierre de sesión). Al volver a la pestaña se revisa otra vez
        // por si se perdió el evento (p. ej. página restaurada con "Atrás").
        window.addEventListener('storage', function (e) {
            if (e.key === null || e.key === 'id_Contribuyente' || e.key === 'id_Usuario') { revisar(); }
        });
        window.addEventListener('focus', revisar);
        window.addEventListener('pageshow', revisar);
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') { revisar(); }
        });

        /* ---------- al cargar ---------- */

        // El administrador sin contribuyente elegido no entra a un módulo: se
        // redirige de UNA, antes de que carguen los scripts de la pantalla, para
        // no encimar el aviso con los popups propios del módulo. El mensaje lo
        // muestra Contribuyentes al llegar (bandera en sessionStorage).
        if (esAdmin && enModulo && !deEstaPestana.id) {
            try { sessionStorage.setItem('avisoElegirContrib', '1'); } catch (e) {}
            window.location.replace('contribuyentes.php');
        } else {
            $(document).ready(pintarBarra);
        }

        return { fijar: fijar, salir: salir };
    })();

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
            // Ventana angosta: el menú flota sobre el contenido con el velo de
            // la plantilla. Se corta la propagación porque la plantilla cierra
            // el menú con cualquier clic fuera de él o de un .menu-icon, y el
            // escudo no es ninguno de los dos: lo cerraría en el mismo clic.
            if (!esEscritorio()) {
                e.stopPropagation();
                var abrir = !$('.left-side-bar').hasClass('open');
                $('.left-side-bar').toggleClass('open', abrir);
                $('.mobile-menu-overlay').toggleClass('show', abrir);
                return;
            }

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

        // En pantalla táctil la plantilla también cierra con touchstart, que
        // llega antes del clic: con el menú abierto, tocar el escudo lo cerraba
        // y el clic lo volvía a abrir.
        $('#btnMenu').on('touchstart', function (e) {
            e.stopPropagation();
        });

        // El escudo es un <div>: Enter o espacio lo activan como a un botón.
        $('#btnMenu').on('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                $(this).trigger('click');
            }
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
		padding-left: 22px !important;
		/* Los rótulos del submenú nunca se parten en dos líneas: al abrir el
		   bloque (li.dropdown.show) la caja se angosta un poco y "Presentar
		   Declaración"/"Consultar Declaraciones" se veían en dos filas. Con
		   nowrap quedan siempre en una sola línea (retro cliente 2026-09-17). */
		white-space: nowrap !important;
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
/* Arranca oculta a proposito. vendors/scripts/process.js la muestra solo si
   la pagina sigue cargando pasado su umbral (~350ms). Antes salia en CADA
   navegacion -y aqui toda navegacion es una carga de pagina completa-, con
   una espera minima obligatoria que hacia sentir el sistema mas lento de lo
   que es. Si se quita esta regla, vuelve a salir siempre. */
.pre-loader {
	display: none;
}

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
	/* Antes 2px 10px: el margen lateral de 10px angostaba la caja 20px y hacía
	   que "Presentar Declaración"/"Consultar Declaraciones" se partieran en dos
	   líneas al abrir el bloque (retro cliente 2026-09-17). 5px deja la tarjeta
	   flotante pero con ancho suficiente para una sola línea. */
	margin: 2px 5px;
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

/* Bloque "Gestionando a" del administrador (lo pinta ContribActivo, al pie de
   este archivo): el rótulo con el nombre y, un paso adentro, los módulos de ESE
   contribuyente. Sin nadie elegido no aparece nada de esto. */
#accordion-menu > li.menu-gestionando {
	margin: 12px 10px 4px;
	padding: 8px 14px;
	border-radius: 8px;
	background: rgba(0, 0, 0, .16);
	color: #FFFFFF;
	line-height: 1.3;
}

.menu-gestionando-titulo {
	display: block;
	font-size: 10.5px;
	font-weight: 600;
	letter-spacing: .08em;
	text-transform: uppercase;
	color: rgba(255, 255, 255, .75);
}

.menu-gestionando-nombre {
	display: block;
	font-size: 14px;
	font-weight: 700;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

#accordion-menu > li.en-gestion > .dropdown-toggle {
	margin-left: 22px;
}

#accordion-menu > li.en-gestion.dropdown.show {
	margin-left: 17px;
}

#accordion-menu > li.en-gestion.dropdown.show > .dropdown-toggle {
	margin-left: 0;
}

/* Aire al cerrar el bloque, antes de lo que es de la Alcaldía. */
#accordion-menu > li.en-gestion + li:not(.en-gestion) {
	margin-top: 12px;
}

/* Con el menú encogido solo quedan íconos: sin rótulo ni sangría. */
body.sidebar-shrink #accordion-menu > li.menu-gestionando {
	display: none !important;
}

body.sidebar-shrink #accordion-menu > li.en-gestion > .dropdown-toggle {
	margin-left: 10px;
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

/* ---------- Ventanas angostas: encabezado compacto ----------
   Por debajo de 1200 px el menú flota (plantilla: .left-side-bar.open) y lo
   abre el mismo escudo (#btnMenu) que en escritorio lo oculta y lo muestra. */
#btnMenu:focus-visible {
	outline: 2px solid rgba(255, 255, 255, .75);
	outline-offset: 2px;
}

@media (max-width: 1199.98px) {
	/* "Alcaldía de Paipa" se partía en tres renglones. */
	#btnMenu .marca-nombre {
		white-space: nowrap;
	}
}

@media (max-width: 1024.98px) {
	/* La plantilla deja al bloque izquierdo solo el 25 % del ancho por debajo
	   de 1025 px (el resto era para su buscador, que aquí no existe): escudo,
	   nombre y título de la pantalla no cabían y se montaban unos sobre otros.
	   El izquierdo toma lo que sobra; el derecho, solo lo que ocupa el usuario. */
	.header-left {
		width: auto;
		flex: 1 1 auto;
		min-width: 0;
	}

	.header-right {
		width: auto;
		flex: 0 0 auto;
	}

	#btnMenu,
	.header-left .header-separador {
		flex: none;
	}

	#headerPageTitle {
		min-width: 0;
		overflow: hidden;
		white-space: nowrap;
		text-overflow: ellipsis;
	}
}

@media (max-width: 767.98px) {
	/* En un teléfono no cabe todo: quedan el escudo y el título de la pantalla. */
	#btnMenu .marca-texto,
	.header-left .header-separador {
		display: none !important;
	}

	#btnMenu img {
		width: 40px !important;
		height: 40px !important;
	}

	#headerPageTitle {
		margin-left: 8px;
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

/* Estado de un establecimiento (dist/establecimientosTodos.php). */
.chip-estado.est-activo     { color: #1B6E45; border-color: #1B6E45; background: #ECFDF3; }
.chip-estado.est-cerrado    { color: #6B7280; border-color: #D1D5DB; background: #F9FAFB; }

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

	/*
	 * Red de seguridad de la capa de carga.
	 *
	 * #loading se abre con $('#loading').show() en decenas de sitios y se
	 * cierra a mano en cada success/error. Basta que un camino se salga antes
	 * -un return temprano, una validacion que corta, una excepcion- para que
	 * la capa quede abierta: cubre la pantalla entera con z-index 9999999 y
	 * deja un recuadro encima del contenido que no se va con nada.
	 *
	 * ajaxStop dispara cuando NO queda ninguna peticion en curso, asi que
	 * cerrar aqui no interrumpe nada que siga trabajando.
	 */
	jQuery(document).ajaxStop(function () {
		jQuery('#loading').hide();
		jQuery('#wrapper').removeClass('body-load');
	});
});
</script>

<style>

</style>