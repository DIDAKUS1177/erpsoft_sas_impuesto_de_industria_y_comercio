<?php
    require_once '../business/globals.php';
    include_once('../business/class.sessions.php');
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>Informes | ERPSOFTSAS</title>

	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta http-equiv="Expires" content="0">
	<meta http-equiv="Cache-Control" content="no-cache, mustrevalidate">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<link rel="icon" type="image/png" sizes="32x32" href="../vendors/images/favicon-32x32.png">
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
	<link rel="stylesheet" type="text/css" href="../vendors/styles/core.css">
	<link rel="stylesheet" type="text/css" href="../vendors/styles/icon-font.min.css">
	<link rel="stylesheet" type="text/css" href="../vendors/styles/style.css">
	<link rel="stylesheet" type="text/css" href="../src/plugins/sweetalert2/sweetalert2.css">
	<link rel="stylesheet" type="text/css" href="../src/styles/loading.css">
	<style>
		/* Informes (2026-10-14): los filtros con el mismo estilo de Consultar
		   (.filtros-declaraciones, dist/menu.php) y los dos botones de descarga. */
		.informe-filtros .campo select, .informe-filtros .campo input { min-width: 190px; }
		/* La casilla del NIT, igual que las listas de .filtros-declaraciones. */
		.informe-filtros .campo input {
			height: 34px; font-size: 13px; padding: 0 8px; border: 1px solid #D1D5DB;
			border-radius: 5px; background: #FFFFFF; color: var(--erp-texto);
		}
		.informe-filtros .campo input:focus { outline: 2px solid var(--erp-primario, #1fa49d); outline-offset: 1px; }
		.informe-acciones { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; }
		.informe-acciones .btn-informe {
			display: inline-flex; align-items: center; gap: 8px; height: 40px; padding: 0 18px;
			font-size: 14px; font-weight: 600; border-radius: 8px;
			border: 1px solid var(--erp-primario, #1fa49d); color: var(--erp-primario, #1fa49d); background: #fff;
			transition: background .15s, color .15s;
		}
		.informe-acciones .btn-informe:hover, .informe-acciones .btn-informe:focus-visible {
			background: var(--erp-primario, #1fa49d); color: #fff;
		}
		.informe-acciones .btn-informe[disabled] { opacity: .6; cursor: wait; }
		.informe-ayuda { font-size: 13px; color: #6B7280; margin: 14px 0 0; max-width: 70ch; }
	</style>
</head>
<body>
	<div id="loading" class="loading" hidden></div>
	<div id="wrapper" class="wrapper">

		<?php include 'menu.php'; ?>
		<div class="mobile-menu-overlay"></div>

		<div class="main-container">

			<div class="card-box mb-30">
				<div class="pd-20">
					<h4 class="h4 mb-1">Informes de declaraciones</h4>
					<small class="text-muted">
						Declaraciones presentadas, pagadas o las dos, de todos los contribuyentes o de uno, en PDF o Excel.
					</small>
				</div>

				<div class="pb-20 px-3">
					<div class="filtros-declaraciones informe-filtros">
						<div class="campo">
							<label for="infModulo">Impuesto</label>
							<select id="infModulo">
								<option value="ica">Industria y Comercio</option>
								<option value="reteica">Retención de ICA</option>
								<option value="autorreteica">Autorretención de ICA</option>
							</select>
						</div>
						<div class="campo">
							<label for="infEstado">Declaraciones</label>
							<select id="infEstado">
								<option value="pagadas">Pagadas</option>
								<option value="sinpagar">Presentadas sin pagar</option>
								<option value="todas">Todas las presentadas (pagadas o no)</option>
							</select>
						</div>
						<div class="campo">
							<label for="infAnio">Año</label>
							<select id="infAnio"></select>
						</div>
						<div class="campo" id="campoPeriodo" style="display:none;">
							<label for="infPeriodo">Período</label>
							<select id="infPeriodo"></select>
						</div>
						<!-- Rango de fechas (2026-10-20): de pago en las pagadas, de
						     presentación en las demás; el rótulo lo dice (informePagos.js). -->
						<div class="campo">
							<label for="infDesde" id="lblDesde">Pagadas desde</label>
							<input type="date" id="infDesde" min="2000-01-01" max="9999-12-31">
						</div>
						<div class="campo">
							<label for="infHasta" id="lblHasta">Pagadas hasta</label>
							<input type="date" id="infHasta" min="2000-01-01" max="9999-12-31">
						</div>
						<div class="campo">
							<label for="infDocumento">Contribuyente (NIT o cédula)</label>
							<input type="text" id="infDocumento" inputmode="numeric" maxlength="12" placeholder="Todos (sin DV)" autocomplete="off">
						</div>
					</div>

					<div class="informe-acciones">
						<button type="button" class="btn-informe" data-formato="pdf">
							<i class="fa fa-file-pdf-o"></i> Descargar PDF
						</button>
						<button type="button" class="btn-informe" data-formato="xlsx">
							<i class="fa fa-file-excel-o"></i> Descargar Excel
						</button>
					</div>
					<p class="informe-ayuda">
						El PDF se abre en otra pestaña; el Excel queda en Descargas. Sin NIT o cédula salen todos
						los contribuyentes; el NIT va sin dígito de verificación. Las fechas son un rango: las pagadas
						por fecha de pago, las demás por fecha de presentación (las dos casillas son opcionales).
						"Sin pagar" deja fuera las de $0 y las que ya tienen una corrección presentada.
					</p>
				</div>
			</div>

		</div>
	</div>

	<script src="../vendors/scripts/core.js"></script>
	<script src="../vendors/scripts/script.min.js"></script>
	<script src="../vendors/scripts/process.js"></script>
	<script src="../vendors/scripts/layout-settings.js"></script>
	<script src="../src/plugins/sweetalert2/sweetalert2.all.js"></script>
	<script src="../src/plugins/sweetalert2/sweet-alert.init.js"></script>
	<!-- menu.js ya lo carga menu.php. -->
	<script src="../core/informePagos.js?v=<?php echo time(); ?>"></script>
</body>
</html>
