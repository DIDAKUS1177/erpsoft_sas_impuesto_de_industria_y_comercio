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

			<!-- ===================== CANDADO DE EDICIÓN ===================== -->
			<!-- Ver no pide nada; guardar un PARÁMETRO exige la contraseña de
			     edición, y quien la exige es el servidor (class.configuracion.php).
			     Las cuentas de los bancos no la piden (cliente, 2026-09-30). Esto
			     solo la pide y muestra si la edición está abierta. -->
			<div class="card-box mb-30" id="cajaCandado">
				<div class="pd-20 d-flex flex-wrap align-items-center justify-content-between" style="gap: 12px;">
					<div>
						<div class="h5 mb-1" id="candadoTitulo"><i class="fa fa-lock"></i> Parámetros protegidos</div>
						<div class="text-muted" id="candadoTexto" style="font-size: 13px;">
							Para cambiar los parámetros del municipio se pide la contraseña de edición. Las cuentas de los bancos se cambian sin ella.
						</div>
					</div>
					<button type="button" class="btn btn-primary" id="btnCandado">Desbloquear edición</button>
				</div>
			</div>

			<div class="modal fade" id="modal-Clave" tabindex="-1" role="dialog" aria-labelledby="tituloClave" aria-hidden="true">
				<div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 420px;">
					<form class="modal-content" id="formClave" autocomplete="off">
						<div class="modal-header">
							<h5 class="modal-title" id="tituloClave">Desbloquear edición</h5>
							<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
						</div>
						<div class="modal-body">
							<label for="claveEdicion">Contraseña de edición</label>
							<input type="password" class="form-control" id="claveEdicion" autocomplete="off" required>
							<small class="form-text text-danger" id="claveError" aria-live="polite"></small>
						</div>
						<div class="modal-footer">
							<button type="button" class="btn btn-link" data-dismiss="modal">Cancelar</button>
							<button type="submit" class="btn btn-primary" id="btnDesbloquear">Desbloquear</button>
						</div>
					</form>
				</div>
			</div>

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
			<!-- Los bancos están cargados desde la migración 006 (Confiar, desde la
			     041). Solo se editan las dos cuentas: el código y el código
			     Asobancaria los fija el banco, no la Alcaldía, y dejarlos
			     editables invita a "corregir" un código que en realidad es el
			     correcto. La cuenta recaudadora es la que sale en el recibo de
			     pago (class.bancosRecibo.php) y se guarda sin la contraseña de
			     edición: el cliente pidió manejarla sin pedírsela a nadie. -->
			<div class="card-box mb-30">
				<div class="pd-20 d-flex flex-wrap justify-content-between align-items-center" style="gap: 12px;">
					<div>
						<h4 class="h4 mb-1">Cuentas de los bancos</h4>
						<p class="text-muted mb-0" style="font-size:13px;">
							Los bancos con <b>cuenta recaudadora</b> salen en el recibo de pago
							("Páguese en: BANCOS"), en orden alfabético. Para quitar un banco del
							recibo, deje su cuenta recaudadora vacía y guarde. El código y el
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
								<th style="width:7%;">Código</th>
								<th style="width:24%;">Banco</th>
								<th style="width:9%;">Asobancaria</th>
								<th style="width:19%;">Cuenta contable</th>
								<th style="width:20%;">Cuenta recaudadora <small class="text-muted" style="font-weight:400;">(sale en el recibo)</small></th>
								<th style="width:13%;">Último cambio</th>
								<th style="width:8%;">Acciones</th>
							</tr>
						</thead>
						<tbody id="tbodyBancos">
							<tr><td colspan="7" class="text-center text-muted py-3">Cargando…</td></tr>
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