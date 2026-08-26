<?php
    require_once '../business/globals.php';
    include_once('../business/class.sessions.php');
?>
<!DOCTYPE html>
<html>
<head>
	<!-- Basic Page Info -->
	<meta charset="utf-8">
	<title>Configuración | ERPSOFTSAS</title>

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
</head>
<body>
	<div id="loading" class="loading" hidden></div>
	<div id="wrapper" class="wrapper">

		<?php include 'menu.php'; ?>
		<div class="mobile-menu-overlay"></div>

		<div class="main-container">

			<!-- ===================== PARÁMETROS ===================== -->
			<!-- Estos valores vivían solo en la base desde la migración 009 y no
			     había pantalla para cambiarlos: la única vía era entrar con SQL.
			     El EAN gobierna el código de barras con el que el banco recauda,
			     así que pedirlo por correo para que alguien corra un UPDATE a mano
			     en producción es justo la operación donde se escribe en la base
			     equivocada. -->
			<div class="card-box mb-30">
				<div class="pd-20">
					<h4 class="h4 mb-1">Parámetros del municipio</h4>
					<p class="text-muted mb-0" style="font-size:13px;">
						Cada entidad tiene su propio EAN de recaudo. Los cambios entran
						de inmediato en los códigos de barras que se generen a partir de
						ahora; los documentos ya impresos no cambian.
					</p>
				</div>
				<div class="pb-20 pl-20 pr-20">
					<table class="table table-hover">
						<thead>
							<tr>
								<th style="width:26%;">Parámetro</th>
								<th style="width:24%;">Valor</th>
								<th>Para qué sirve</th>
								<th style="width:14%;">Último cambio</th>
								<th style="width:10%;">Acciones</th>
							</tr>
						</thead>
						<tbody id="tbodyParametros">
							<tr><td colspan="5" class="text-center text-muted py-3">Cargando…</td></tr>
						</tbody>
					</table>
				</div>
			</div>

			<!-- ===================== CUENTAS DE LOS BANCOS ===================== -->
			<!-- Los 25 bancos están cargados desde la migración 006 pero los 25
			     tienen las dos cuentas vacías, y hacen falta para cuadrar el
			     recaudo. Solo se editan esas dos columnas: el código y el código
			     Asobancaria los fija el banco, no la Alcaldía, y dejarlos
			     editables invita a "corregir" un código que en realidad es el
			     correcto. -->
			<div class="card-box mb-30">
				<div class="pd-20 d-flex justify-content-between align-items-center">
					<div>
						<h4 class="h4 mb-1">Cuentas de los bancos</h4>
						<p class="text-muted mb-0" style="font-size:13px;">
							Cuenta contable y cuenta recaudadora de cada banco. El código y el
							código Asobancaria los fija el banco y no se editan aquí.
						</p>
					</div>
					<div class="custom-control custom-checkbox">
						<input type="checkbox" class="custom-control-input" id="soloConCuenta"
						       onchange="configuracion.pintarBancos()">
						<label class="custom-control-label" for="soloConCuenta">Ver sólo los que ya tienen cuenta</label>
					</div>
				</div>
				<div class="pb-20 pl-20 pr-20">
					<table class="table table-hover">
						<thead>
							<tr>
								<th style="width:8%;">Código</th>
								<th style="width:30%;">Banco</th>
								<th style="width:10%;">Asobancaria</th>
								<th style="width:22%;">Cuenta contable</th>
								<th style="width:22%;">Cuenta recaudadora</th>
								<th style="width:8%;">Acciones</th>
							</tr>
						</thead>
						<tbody id="tbodyBancos">
							<tr><td colspan="6" class="text-center text-muted py-3">Cargando…</td></tr>
						</tbody>
					</table>
				</div>
			</div>

		</div>


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
		<script src="../core/configuracion.js?v=<?php echo time(); ?>"></script>
		<!-- <script src="../core/Permisos.js"></script> -->
	</div>	
</body>
</html>