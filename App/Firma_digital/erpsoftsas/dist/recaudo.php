<?php
    require_once '../business/globals.php';
    include_once('../business/class.sessions.php');
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>Recaudo por código de barras | ERPSOFTSAS</title>

	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta http-equiv="Expires" content="0">
	<meta http-equiv="Cache-Control" content="no-cache, mustrevalidate">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<link rel="icon" type="image/png" sizes="32x32" href="../vendors/images/favicon-32x32.png">
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
	<link rel="stylesheet" type="text/css" href="../vendors/styles/core.css">
	<link rel="stylesheet" type="text/css" href="../vendors/styles/icon-font.min.css">
	<link rel="stylesheet" type="text/css" href="../src/plugins/datatables/css/dataTables.bootstrap4.min.css">
	<link rel="stylesheet" type="text/css" href="../vendors/styles/style.css">
	<link rel="stylesheet" type="text/css" href="../src/plugins/sweetalert2/sweetalert2.css">
	<link rel="stylesheet" type="text/css" href="../src/styles/loading.css">
</head>
<body>
	<div id="loading" class="loading" hidden></div>
	<div id="wrapper" class="wrapper">

		<?php include 'menu.php'; ?>
		<div class="mobile-menu-overlay"></div>

		<div class="main-container">

			<div class="card-box mb-30">
				<div class="pd-20">
					<h4 class="h4 mb-1">Recaudo por código de barras</h4>
					<small class="text-muted">
						Archivo de recaudo que entrega la entidad financiera con los pagos hechos en ventanilla.
					</small>
				</div>

				<div class="pd-20 pt-0">
					<div class="row align-items-end">
						<div class="col-md-6 form-group mb-2">
							<label>Archivo del banco</label>
							<input type="file" class="form-control-file" id="archivoRecaudo" accept=".txt,.asc,.rec,.dat">
							<small class="text-muted">Texto plano, máximo 20 MB.</small>
						</div>
						<div class="col-md-6 form-group mb-2">
							<button type="button" class="btn btn-outline-primary" id="btnPrevisualizar">
								<span class="ti-search"></span> Revisar archivo
							</button>
							<!--
							     Aplicar arranca deshabilitado a proposito: solo se suelta
							     despues de previsualizar. Marcar declaraciones como pagadas
							     es irreversible en la practica, asi que no se hace sin que
							     alguien haya visto antes que va a pasar.
							-->
							<button type="button" class="btn btn-success ml-2" id="btnAplicar" disabled>
								<span class="ti-check"></span> Aplicar pagos
							</button>
						</div>
					</div>
				</div>
			</div>

			<!-- Resumen de la previsualizacion -->
			<div class="card-box mb-30" id="cajaResumen" style="display:none;">
				<div class="pd-20">
					<h5 class="mb-3" style="font-weight:600;">Resumen del archivo</h5>
					<div id="resumenRecaudo"></div>
				</div>

				<div class="pd-20 pt-0">
					<ul class="nav nav-tabs" role="tablist">
						<li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tabAplicables">Se van a aplicar</a></li>
						<li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tabYaPagadas">Ya estaban pagadas</a></li>
						<li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tabSinDeclaracion">Sin declaración</a></li>
					</ul>
					<div class="tab-content pt-3">
						<div class="tab-pane fade show active" id="tabAplicables">
							<div class="table-responsive">
								<table class="table table-bordered table-sm">
									<thead style="background:#e9ecef; font-weight:600;">
										<tr><th style="width:180px;">N° Declaración</th><th>Valor pagado</th><th style="width:160px;">Estado</th></tr>
									</thead>
									<tbody id="tbodyAplicables"></tbody>
								</table>
							</div>
						</div>
						<div class="tab-pane fade" id="tabYaPagadas">
							<div class="table-responsive">
								<table class="table table-bordered table-sm">
									<thead style="background:#e9ecef; font-weight:600;">
										<tr><th style="width:180px;">N° Declaración</th><th>Valor en el archivo</th></tr>
									</thead>
									<tbody id="tbodyYaPagadas"></tbody>
								</table>
							</div>
						</div>
						<div class="tab-pane fade" id="tabSinDeclaracion">
							<div class="table-responsive">
								<table class="table table-bordered table-sm">
									<thead style="background:#e9ecef; font-weight:600;">
										<tr><th style="width:180px;">Referencia</th><th>Valor en el archivo</th></tr>
									</thead>
									<tbody id="tbodySinDeclaracion"></tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Historial -->
			<div class="card-box mb-30">
				<div class="pd-20 d-flex justify-content-between align-items-center">
					<h5 class="mb-0" style="font-weight:600;">Archivos cargados</h5>
					<button type="button" class="btn btn-sm btn-outline-secondary" id="btnRefrescarHistorial">Actualizar</button>
				</div>
				<div class="pb-20 pd-20 pt-0 table-responsive">
					<table class="table table-bordered table-sm">
						<thead style="background:#e9ecef; font-weight:600;">
							<tr>
								<th>Archivo</th><th style="width:150px;">Banco</th>
								<th style="width:110px;">Fecha pago</th><th style="width:90px;">Registros</th>
								<th style="width:90px;">Aplicados</th><th style="width:100px;">Ya pagadas</th>
								<th style="width:90px;">Fallidos</th><th style="width:140px;">Cargado</th>
							</tr>
						</thead>
						<tbody id="tbodyHistorialRecaudo"></tbody>
					</table>
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
	<script src="../core/menu.js"></script>
	<script src="../core/recaudo.js"></script>
</body>
</html>
