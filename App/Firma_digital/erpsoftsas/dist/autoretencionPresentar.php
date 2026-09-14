<?php
    require_once '../business/globals.php';
    include_once('../business/class.sessions.php');
?>
<!DOCTYPE html>
<html>
<head>
	<!-- Basic Page Info -->
	<meta charset="utf-8">
	<title>AUTORETENCION WEB PRESENTAR |ERPSOFTSAS </title>

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
    
	<link rel="stylesheet" type="text/css" href="../src/plugins/sweetalert2/sweetalert2.css">
	
	<!-- switchery css -->
	<link rel="stylesheet" type="text/css" href="../src/plugins/switchery/switchery.min.css">

	<!-- loading css -->
	<link rel="stylesheet" type="text/css" href="../src/styles/loading.css">
	
	<!-- Analitica retirada: la etiqueta era UA- (Universal Analytics),
	     apagada por Google en 2023, por lo que no recogia ningun dato. -->

	
    <style>
        /*
         * El stub de esta pantalla centraba el contenido con
         * "body { display:flex; align-items:center; height:100vh }" para el
         * cartel de "en construccion". Con el menu lateral y una tabla real eso
         * rompe el layout entero, asi que se retira y se usa el mismo esqueleto
         * que el resto de pantallas internas.
         */
        .renglon-codigo { width: 60px; }
        #tablaRenglones td, #tablaActividades td { vertical-align: middle; }
    </style>
</head>
<body>
	<div id="loading" class="loading" hidden></div>
	<div id="wrapper" class="wrapper">

		<?php include 'menu.php'; ?>
		<div class="mobile-menu-overlay"></div>

		<div class="main-container">

			<!-- ============ CREAR / ESCOGER ============ -->
			<div id="panelCrear">

				<div class="card-box mb-30">
					<div class="pd-20">
						<h4 class="h4" id="tituloModulo">Presentar Declaración</h4>
					</div>
					<div class="pb-20 px-3">
						<div class="filtros-declaraciones">
							<div class="campo">
								<label for="nuevoAnio">Año gravable</label>
								<select id="nuevoAnio"></select>
							</div>
							<div class="campo">
								<label for="nuevoPeriodo"><span id="etiquetaPeriodo">Período</span> a declarar</label>
								<select id="nuevoPeriodo"></select>
							</div>
							<div class="campo">
								<label>&nbsp;</label>
								<button type="button" id="btnCrear" class="btn btn-primary btn-sm">
									<i class="fa fa-plus"></i> Crear declaración
								</button>
							</div>
						</div>
					</div>
				</div>

				<div class="card-box mb-30">
					<div class="pd-20"><h4 class="h4">Mis declaraciones</h4></div>
					<div class="pb-20 px-3">
						<div class="table-responsive">
							<table class="table table-bordered table-striped table-sm" id="tablaMias">
								<thead style="background:#e9ecef; font-weight:600;">
									<tr>
										<th>Año</th>
										<th>Período</th>
										<th>N° Declaración</th>
										<th>Estado</th>
										<th style="text-align:right;">Total a pagar</th>
										<th class="text-center" style="width:110px;">Acciones</th>
									</tr>
								</thead>
								<tbody></tbody>
							</table>
						</div>
					</div>
				</div>

			</div>

			<!-- ============ FORMULARIO ============ -->
			<div id="panelFormulario" style="display:none">

				<div class="card-box mb-30">
					<div class="pd-20">
						<div class="row align-items-center">
							<div class="col-md-8">
								<h4 class="h4 mb-10">Declaración No. <span id="encNumero"></span> <span id="encEstado"></span></h4>
								<p class="mb-5"><strong id="encContribuyente"></strong></p>
								<p class="text-muted mb-0">Año <span id="encAnio"></span> &mdash; Período <span id="encPeriodo"></span></p>
								<p class="text-warning mb-0" id="encCorrige"></p>
							</div>
							<div class="col-md-4 text-right">
								<a id="btnPdf" class="btn btn-primary btn-sm mr-1" target="_blank" href="#" title="Ver PDF"><i class="fa fa-download"></i> PDF</a>
								<button type="button" id="btnVolver" class="btn btn-info btn-sm mr-1" title="Volver al listado"><i class="fa fa-arrow-left"></i></button>
								<button type="button" id="btnDescartar" class="btn btn-danger btn-sm" title="Eliminar borrador"><i class="fa fa-trash"></i></button>
							</div>
						</div>
					</div>
				</div>

				<div class="alert alert-secondary" id="avisoCerrada" style="display:none">
					Esta declaración ya fue presentada, por lo que no se puede editar.
					Para modificarla use la opción <strong>Corregir</strong>.
				</div>

				<!--
					INGRESOS DEL BIMESTRE (casillas 9-13). Van ANTES de las
					actividades, en el mismo orden que el ICA: primero los
					ingresos, luego las actividades, luego la liquidación. Antes
					todas las casillas (9-23) salían juntas DESPUÉS de las
					actividades; el cliente pidió (2026-09-14) que la
					autorretención se leyera como el ICA. La casilla 13 (ingresos
					netos gravados) es una sumatoria y se recalcula en vivo.
					El motor (core/retenciones.js) reparte cada renglón a esta
					tabla o a la de liquidación según cfg.ingresosHasta.
				-->
				<div class="card-box mb-30">
					<div class="pd-20"><h4 class="h4 mb-0">Ingresos del bimestre</h4></div>
					<div class="pb-20 px-3">
						<div class="table-responsive">
							<table class="table table-bordered table-striped table-sm" id="tablaIngresos">
								<thead style="background:#e9ecef; font-weight:600;">
									<tr>
										<th class="text-center" style="width:55px;">N°</th>
										<th>Concepto</th>
										<th style="text-align:right; width:200px;">Valor</th>
									</tr>
								</thead>
								<tbody></tbody>
							</table>
						</div>
					</div>
				</div>

				<div class="card-box mb-30">
					<div class="pd-20 d-flex justify-content-between align-items-center">
						<h4 class="h4 mb-0">Actividades</h4>
						<button type="button" id="btnAgregarActividad" class="btn btn-success btn-sm">
							<i class="fa fa-plus"></i> Agregar actividad
						</button>
					</div>
					<div class="pb-20 px-3">
						<div class="table-responsive">
							<table class="table table-bordered table-striped table-sm" id="tablaActividades">
								<thead style="background:#e9ecef; font-weight:600;">
									<tr>
										<th>Actividad</th>
										<th class="text-center" style="width:110px;">Tarifa (x mil)</th>
										<th style="text-align:right; width:170px;">Base</th>
										<th style="text-align:right; width:150px;">Valor</th>
										<th style="width:50px;"></th>
									</tr>
								</thead>
								<tbody></tbody>
							</table>
						</div>

						<!-- Solo autorretención: impuesto de generación de energía
						     (Ley 56 de 1981). No tiene número de casilla en el formulario. -->
						<div id="bloqueEnergia" style="display:none">
							<div class="row">
								<div class="col-md-6">
									<label style="font-weight:600; font-size:13px;">Impuesto por generación de energía (Ley 56 de 1981)</label>
									<input type="text" id="impuestoEnergia" class="form-control text-right" inputmode="numeric" value="0">
								</div>
							</div>
						</div>
					</div>
				</div>

				<div class="card-box mb-30">
					<div class="pd-20"><h4 class="h4 mb-0">Liquidación privada</h4></div>
					<div class="pb-20 px-3">

						<div id="avisoPendientes" style="display:none">
							<div class="alert alert-warning">
								Las casillas marcadas como <strong>pendiente</strong> no se liquidan todavía:
								su fórmula está en revisión por la Alcaldía. Aparecen en cero.
							</div>
						</div>

						<div class="table-responsive">
							<table class="table table-bordered table-striped table-sm" id="tablaRenglones">
								<thead style="background:#e9ecef; font-weight:600;">
									<tr>
										<th class="text-center" style="width:55px;">N°</th>
										<th>Concepto</th>
										<th style="text-align:right; width:200px;">Valor</th>
									</tr>
								</thead>
								<tbody></tbody>
							</table>
						</div>
					</div>
				</div>

				<div class="card-box mb-30">
					<div class="pd-20 text-right">
						<button type="button" id="btnLiquidar"  class="btn btn-info">Liquidar</button>
						<button type="button" id="btnGuardar"   class="btn btn-secondary">Guardar</button>
						<button type="button" id="btnPresentar" class="btn btn-success">Presentar</button>
					</div>
				</div>

			</div>

		</div>

		<!-- /.modal-dialog -->
		<!-- js -->
		<script src="../vendors/scripts/core.js"></script>
		<script src="../vendors/scripts/script.min.js"></script>
		<script src="../vendors/scripts/process.js"></script>
		<script src="../vendors/scripts/layout-settings.js"></script>
		<script src="../src/plugins/datatables/js/jquery.dataTables.min.js"></script>
		<script src="../src/plugins/datatables/js/dataTables.bootstrap4.min.js"></script>
		<script src="../src/plugins/datatables/js/dataTables.responsive.min.js"></script>
		<script src="../src/plugins/datatables/js/responsive.bootstrap4.min.js"></script>
		<!-- buttons for Export datatable -->
		<script src="../src/plugins/datatables/js/dataTables.buttons.min.js"></script>
		<script src="../src/plugins/datatables/js/buttons.bootstrap4.min.js"></script>
		<script src="../src/plugins/datatables/js/buttons.print.min.js"></script>
		<script src="../src/plugins/datatables/js/buttons.html5.min.js"></script>
		<script src="../src/plugins/datatables/js/buttons.flash.min.js"></script>
		<script src="../src/plugins/datatables/js/pdfmake.min.js"></script>
		<script src="../src/plugins/datatables/js/vfs_fonts.js"></script>
		<!-- switchery js -->
		<script src="../src/plugins/switchery/switchery.min.js"></script>
		<script src="../src/plugins/sweetalert2/sweetalert2.all.js"></script>
		<script src="../core/numeros.js?v=<?php echo time(); ?>"></script>
		<script src="../core/retenciones.js?v=<?php echo time(); ?>"></script>
		<script src="../core/autoretencionPresentar.js?v=<?php echo time(); ?>"></script>
		<!-- <script src="../core/Permisos.js"></script> -->
	</div>	
</body>
</html>