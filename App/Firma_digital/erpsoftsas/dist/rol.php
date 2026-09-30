<?php
    require_once '../business/globals.php';
    include_once('../business/class.sessions.php');
?>
<!DOCTYPE html>
<html>
<head>
	<!-- Basic Page Info -->
	<meta charset="utf-8">
	<title>Roles y permisos | ERPSOFTSAS</title>

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
	<link rel="stylesheet" type="text/css" href="../vendors/styles/style.css">
	<link rel="stylesheet" type="text/css" href="../src/plugins/sweetalert2/sweetalert2.css">
	<link rel="stylesheet" type="text/css" href="../src/styles/loading.css">

	<style>
	/* =====================================================================
	   ROLES Y PERMISOS (2026-09-29)
	   Un interruptor por acción, agrupados como el menú. Usa los tokens y
	   componentes de dist/menu.php (--erp-*, .chip-estado, .acc-card,
	   .estado-vacio), para que se vea como el resto del sistema.
	   ===================================================================== */
	.roles-intro { font-size: 13px; max-width: 640px; color: var(--erp-texto-tenue); }

	.tabla-roles td { vertical-align: middle; }
	.tabla-roles .rol-nombre { font-weight: 600; color: var(--erp-texto); }
	.tabla-roles .rol-desc { display: block; font-size: 12px; color: var(--erp-texto-tenue); white-space: normal; max-width: 360px; }
	.tabla-roles .num { text-align: center; font-variant-numeric: tabular-nums; }
	.marca-propio {
		display: inline-block; margin-left: 6px; padding: 1px 7px; border-radius: 999px;
		font-size: 11px; font-weight: 600; color: var(--erp-primario-hover);
		background: var(--erp-primario-suave); border: 1px solid var(--erp-primario-borde);
	}
	.sin-permisos { color: #B4341F; font-size: 12px; font-weight: 600; display: block; }

	/* Tipo de rol: el color acompaña al texto, nunca va solo. */
	.chip-tipo {
		display: inline-block; padding: 3px 10px; border-radius: 6px;
		font-size: 12px; font-weight: 600; white-space: nowrap; border: 1px solid;
	}
	.chip-tipo.tipo-ADMINISTRADOR { color: #fff; background: var(--erp-primario-hover); border-color: var(--erp-primario-hover); }
	.chip-tipo.tipo-ALCALDIA      { color: var(--erp-primario-hover); background: var(--erp-primario-suave); border-color: var(--erp-primario-borde); }
	.chip-tipo.tipo-CONTRIBUYENTE { color: #1864ab; background: #e7f1ff; border-color: #c9deff; }
	.chip-tipo.tipo-EXTERNO       { color: #4B5563; background: #F3F4F6; border-color: #D1D5DB; }
	.chip-estado.est-inactivo     { color: #6B7280; border-color: #D1D5DB; background: #F9FAFB; }

	/* ---------- vista de permisos ---------- */
	.volver-roles { font-size: 13px; font-weight: 600; color: var(--erp-primario-hover); }
	.volver-roles:hover { color: var(--erp-primario); text-decoration: none; }
	.perm-meta { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; font-size: 13px; color: var(--erp-texto-tenue); }
	.perm-acciones { display: flex; gap: 8px; flex-wrap: wrap; }

	.perm-grupos {
		display: grid; gap: 16px; margin-bottom: 96px;
		grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
		align-items: start;
	}
	@media (max-width: 420px) { .perm-grupos { grid-template-columns: 1fr; } }

	.perm-grupo { padding: 0; overflow: hidden; }
	.perm-grupo-cab {
		display: flex; justify-content: space-between; align-items: center; gap: 12px;
		padding: 14px 18px; border-bottom: 1px solid var(--erp-borde);
	}
	.perm-grupo-cab h5 { font-size: 15px; font-weight: 700; margin: 0; color: var(--erp-texto); }
	.perm-grupo-cab .perm-cuenta { font-size: 12px; color: var(--erp-texto-tenue); font-variant-numeric: tabular-nums; }
	.perm-grupo-nota {
		margin: 0; padding: 8px 18px; font-size: 12px; line-height: 1.45;
		color: #7a5b00; background: #fff8e1; border-bottom: 1px solid #f0d98c;
	}
	.perm-grupo-nota.es-info { color: var(--erp-primario-hover); background: var(--erp-primario-suave); border-bottom-color: var(--erp-primario-borde); }
	.perm-grupo.apagado .perm-lista { opacity: .55; }

	.perm-lista { list-style: none; margin: 0; padding: 6px 0; }
	.perm-lista li { padding: 8px 18px; }
	.perm-lista li + li { border-top: 1px solid #F3F4F6; }
	.perm-lista .custom-control-label { cursor: pointer; padding-left: 4px; }
	.perm-lista input:disabled ~ .custom-control-label { cursor: default; }
	.perm-nombre { display: block; font-size: 13.5px; font-weight: 600; color: var(--erp-texto); line-height: 1.35; }
	.perm-desc { display: block; font-size: 12px; color: var(--erp-texto-tenue); line-height: 1.4; margin-top: 1px; }
	.perm-requiere { display: block; font-size: 11.5px; color: var(--erp-texto-tenue); margin-top: 3px; }
	.perm-requiere i { margin-right: 3px; }

	/* El interruptor prendido lleva el color del municipio. */
	.custom-switch .custom-control-input:checked ~ .custom-control-label::before {
		background-color: var(--erp-primario); border-color: var(--erp-primario);
	}
	.custom-switch .custom-control-input:focus ~ .custom-control-label::before {
		box-shadow: 0 0 0 .2rem rgba(31,164,157,.25);
	}
	.custom-switch .custom-control-input:disabled:checked ~ .custom-control-label::before {
		background-color: var(--erp-primario-borde); border-color: var(--erp-primario-borde);
	}
	.interruptor-grupo .custom-control-label { font-size: 12px; font-weight: 600; color: var(--erp-texto-tenue); }

	/* Barra de guardar, fija abajo mientras se editan los permisos. */
	.perm-barra {
		position: fixed; left: 0; right: 0; bottom: 0; z-index: 1030;
		display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
		padding: 12px 24px; background: #fff; border-top: 1px solid var(--erp-borde);
		box-shadow: 0 -4px 14px rgba(0,0,0,.06); font-size: 13px;
	}
	/* En escritorio el menú lateral mide 280 px (dist/menu.php); oculto, nada. */
	@media (min-width: 1200px) {
		.perm-barra { left: 280px; transition: left .2s ease; }
		body.menu-oculto .perm-barra { left: 0; }
	}
	.perm-barra .perm-conteo { font-weight: 600; color: var(--erp-texto); font-variant-numeric: tabular-nums; }
	.perm-barra .perm-cambios { color: #a5680a; font-weight: 600; }
	.perm-barra .perm-eco { color: var(--erp-texto-tenue); flex: 1 1 260px; min-width: 0; }
	.perm-barra .perm-eco:empty { display: none; }
	@media (max-width: 600px) {
		.perm-barra { padding: 10px 16px; gap: 8px; }
		.perm-grupos { margin-bottom: 150px; }
	}
	.perm-barra .botones { margin-left: auto; display: flex; gap: 8px; }

	/* Tipo de rol en el formulario: tres opciones con su explicación. */
	.opciones-tipo { display: grid; gap: 8px; }
	.opcion-tipo {
		display: flex; gap: 10px; align-items: flex-start; margin: 0;
		padding: 10px 12px; border: 1px solid var(--erp-borde); border-radius: 8px; cursor: pointer;
	}
	.opcion-tipo input { margin-top: 3px; }
	.opcion-tipo b { display: block; font-size: 13.5px; color: var(--erp-texto); }
	.opcion-tipo small { display: block; font-size: 12px; color: var(--erp-texto-tenue); line-height: 1.4; }
	.opcion-tipo:has(input:checked) { border-color: var(--erp-primario); background: var(--erp-primario-suave); }
	.opcion-tipo:has(input:disabled) { cursor: not-allowed; opacity: .7; }
	</style>
</head>
<body>
	<div id="loading" class="loading" hidden></div>
	<div id="wrapper" class="wrapper">

		<?php include 'menu.php'; ?>
		<div class="mobile-menu-overlay"></div>

		<div class="main-container">

			<!-- =============== LISTA DE ROLES =============== -->
			<section id="vistaRoles" class="card-box mb-30" aria-labelledby="tituloRoles">
				<div class="pd-20 d-flex justify-content-between align-items-start flex-wrap" style="gap: 12px;">
					<div>
						<h4 class="h4 mb-1" id="tituloRoles">Roles y permisos</h4>
						<p class="roles-intro mb-0">
							Cada rol define lo que pueden hacer sus cuentas, con un interruptor por acción.
							El Administrador tiene todos los permisos, siempre.
						</p>
					</div>
					<button type="button" class="btn btn-outline-success" id="btnNuevoRol" data-permiso-crear="roles.editar">
						<span class="ti-plus"></span> Crear rol
					</button>
				</div>
				<div class="pb-20 px-3">
					<div class="table-responsive">
						<table class="table table-bordered table-striped table-sm tabla-roles mb-0">
							<thead style="background:#e9ecef;">
								<tr>
									<th>Rol</th>
									<th>Tipo</th>
									<th class="text-center">Cuentas</th>
									<th class="text-center">Permisos</th>
									<th>Estado</th>
									<th class="text-center">Acciones</th>
								</tr>
							</thead>
							<tbody id="tbodyRoles">
								<tr><td colspan="6" class="text-center text-muted py-4">Cargando roles…</td></tr>
							</tbody>
						</table>
					</div>
				</div>
			</section>

			<!-- =============== PERMISOS DE UN ROL =============== -->
			<section id="vistaPermisos" hidden aria-labelledby="permRolNombre">
				<div class="card-box mb-30">
					<div class="pd-20">
						<a href="#" id="btnVolverRoles" class="volver-roles"><i class="fa fa-arrow-left"></i> Roles</a>
						<div class="d-flex justify-content-between align-items-start flex-wrap mt-2" style="gap: 12px;">
							<div>
								<h4 class="h4 mb-2" id="permRolNombre"></h4>
								<div class="perm-meta" id="permRolMeta"></div>
							</div>
							<div class="perm-acciones" id="permAcciones">
								<button type="button" class="btn btn-outline-secondary btn-sm" id="btnPrenderTodo">Prender todo</button>
								<button type="button" class="btn btn-outline-secondary btn-sm" id="btnApagarTodo">Apagar todo</button>
							</div>
						</div>
						<div id="permAviso" class="alert mt-3 mb-0" role="status" hidden></div>
					</div>
				</div>

				<div class="perm-grupos" id="permGrupos"></div>

				<div class="perm-barra" id="permBarra">
					<span class="perm-conteo" id="permConteo"></span>
					<span class="perm-cambios" id="permCambios" hidden><i class="fa fa-circle" style="font-size:8px;"></i> Cambios sin guardar</span>
					<span class="perm-eco" id="permEco" aria-live="polite"></span>
					<div class="botones">
						<button type="button" class="btn btn-outline-secondary" id="btnDescartar" disabled>Descartar</button>
						<button type="button" class="btn btn-primary" id="btnGuardarPermisos" disabled>Guardar permisos</button>
					</div>
				</div>
			</section>
		</div>

		<!-- =============== CREAR / EDITAR ROL =============== -->
		<div class="modal fade" id="modalRol" tabindex="-1" role="dialog" aria-labelledby="tituloModalRol" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered" role="document">
				<div class="modal-content">
					<form id="formRol" novalidate>
						<div class="modal-header">
							<h4 class="modal-title" id="tituloModalRol">Crear rol</h4>
							<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">×</button>
						</div>
						<div class="modal-body">
							<input type="hidden" id="rolId">
							<div class="form-group">
								<label for="rolNombre">Nombre del rol</label>
								<input type="text" class="form-control" id="rolNombre" maxlength="100" required
								       placeholder="Ej.: Funcionario de rentas" autocomplete="off">
							</div>
							<div class="form-group">
								<label for="rolDescripcion">Descripción <span class="text-muted" style="font-weight:400;">(opcional)</span></label>
								<textarea class="form-control" id="rolDescripcion" rows="2" maxlength="500"
								          placeholder="Para qué es este rol" style="height: auto;"></textarea>
							</div>
							<fieldset class="form-group mb-0">
								<legend style="font-size: 14px; font-weight: 600;">Tipo de rol</legend>
								<div class="opciones-tipo">
									<label class="opcion-tipo">
										<input type="radio" name="rolTipo" value="ALCALDIA">
										<span><b>Alcaldía</b>
										<small>Funcionarios. Pueden tener las opciones de la Alcaldía (contribuyentes, recaudo, parámetros, usuarios) y trabajar sobre el contribuyente que gestionen.</small></span>
									</label>
									<label class="opcion-tipo">
										<input type="radio" name="rolTipo" value="CONTRIBUYENTE">
										<span><b>Contribuyente</b>
										<small>Cada cuenta trabaja solo sobre su propio contribuyente (el de su documento): su RIT, establecimientos y declaraciones.</small></span>
									</label>
									<label class="opcion-tipo">
										<input type="radio" name="rolTipo" value="EXTERNO">
										<span><b>Consulta externa</b>
										<small>Consultas puntuales, como el paz y salvo del predial. Tampoco ve lo de la Alcaldía.</small></span>
									</label>
								</div>
								<small id="rolTipoAviso" class="form-text" style="color:#a5680a;" hidden></small>
							</fieldset>
						</div>
						<div class="modal-footer">
							<button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
							<button type="submit" class="btn btn-primary" id="btnGuardarRol">Guardar</button>
						</div>
					</form>
				</div>
			</div>
		</div>

		<!-- js -->
		<script src="../vendors/scripts/core.js"></script>
		<script src="../vendors/scripts/script.min.js"></script>
		<script src="../vendors/scripts/process.js"></script>
		<script src="../vendors/scripts/layout-settings.js"></script>
		<script src="../src/plugins/sweetalert2/sweetalert2.all.js"></script>
		<script src="../core/rol.js?v=<?php echo time(); ?>"></script>
	</div>
</body>
</html>
