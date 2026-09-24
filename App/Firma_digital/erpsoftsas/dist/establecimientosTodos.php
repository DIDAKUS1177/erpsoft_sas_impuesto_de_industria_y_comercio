<?php
    require_once '../business/globals.php';
    include_once('../business/class.sessions.php');
?>
<!DOCTYPE html>
<html>
<head>
	<!-- Basic Page Info -->
	<meta charset="utf-8">
	<title>Establecimientos del municipio | ERPSOFTSAS</title>

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
	<link rel="stylesheet" type="text/css" href="../src/styles/loading.css">
</head>
<body>
	<div id="loading" class="loading" hidden></div>
	<div id="wrapper" class="wrapper">

		<?php include 'menu.php'; ?>
		<div class="mobile-menu-overlay"></div>

		<div class="main-container">

			<!-- Todos los establecimientos del municipio, para el administrador
			     (retro cliente 2026-09-24). Es un DIRECTORIO: se busca aquí y, para
			     editar uno, se gestiona a su contribuyente, que es donde viven las
			     herramientas del establecimiento. Ver core/establecimientosTodos.js. -->
			<div class="card-box mb-30">
				<div class="pd-20">
					<h4 class="h4 mb-1">Establecimientos del municipio</h4>
					<p class="text-muted mb-0" style="font-size: 13px;">
						Todos los establecimientos registrados. Para editar uno, pulsa «Gestionar»:
						entrarás como su contribuyente.
					</p>
				</div>
				<div class="pd-20 pt-0">
					<label for="buscarEstablecimiento" class="sr-only">Buscar establecimiento</label>
					<div class="input-group mb-0" style="max-width: 560px;">
						<div class="input-group-prepend">
							<span class="input-group-text"><i class="fa fa-search"></i></span>
						</div>
						<input type="search" id="buscarEstablecimiento" class="form-control"
						       placeholder="Nombre, dirección o documento del contribuyente" autocomplete="off" autofocus>
					</div>
					<small id="estadoBusqueda" class="form-text text-muted" aria-live="polite"></small>
				</div>
				<div class="pb-20">
					<table id="tablaEstablecimientos" class="data-table table stripe hover nowrap">
						<thead>
							<tr>
								<th>Establecimiento</th>
								<th>Dirección</th>
								<th>Contribuyente</th>
								<th>Estado</th>
								<th class="datatable-nosort">Acciones</th>
							</tr>
						</thead>
						<tbody id="bodyEstablecimientos"></tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- js -->
		<script src="../vendors/scripts/core.js"></script>
		<script src="../vendors/scripts/script.min.js"></script>
		<script src="../vendors/scripts/process.js"></script>
		<script src="../vendors/scripts/layout-settings.js"></script>
		<script src="../src/plugins/datatables/js/jquery.dataTables.min.js"></script>
		<script src="../src/plugins/datatables/js/dataTables.bootstrap4.min.js"></script>
		<script src="../src/plugins/datatables/js/dataTables.responsive.min.js"></script>
		<script src="../src/plugins/datatables/js/responsive.bootstrap4.min.js"></script>
		<script src="../src/plugins/sweetalert2/sweetalert2.all.js"></script>
		<script src="../core/establecimientosTodos.js?v=<?php echo time(); ?>"></script>
	</div>
</body>
</html>
