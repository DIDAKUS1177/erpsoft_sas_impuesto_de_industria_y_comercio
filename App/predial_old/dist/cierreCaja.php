<?php
    require_once '../business/globals.php';
    include_once('../business/class.sessions.php');
    try {
        \predial\SesionUsuario::verificarSesion();
    } catch (\predial\sesionException $e) {
        echo $e->getMessage();
    }
?>
<!DOCTYPE html>
<html>
<head>
	<!-- Basic Page Info -->
	<meta charset="utf-8">
	<title>Cierres de  Cajas | DS-POS</title>

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
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">

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
	
	<!-- Global site tag (gtag.js) - Google Analytics -->
	<script async src="https://www.googletagmanager.com/gtag/js?id=UA-119386393-1"></script>

	<script>
		window.dataLayer = window.dataLayer || [];
		function gtag(){dataLayer.push(arguments);}
		gtag('js', new Date());

		gtag('config', 'UA-119386393-1');
	</script>
</head>
<body>
	<div id="loading" class="loading" hidden></div>
	<div id="wrapper" class="wrapper">

		<?php include 'menu.php'; ?>
		<div class="mobile-menu-overlay"></div>

		<div class="main-container">
			

			<!-- Simple Datatable start -->
			<div class="card-box mb-30" id="ltsRol">
				<div class="pd-20 d-flex justify-content-between">
					<h4 class="h4">Listado de Cierres de Cajas</h4>
					<button type="button" class="btn btn-outline-success" onclick="cierreCaja.crearCierreCaja()"><span class="ti-plus"></span> Crear Cierre a Caja</button>
				</div>
				<div class="pb-20">
				<table id="tblCierreCaja" class="data-table table stripe hover nowrap">
						<thead>
							<tr>
								<th>Caja</th>
								<th>Vendedor</th>
								<th>Fecha</th>
								<th>Total Ventas</th>
								<th>Informe</th>
							</tr>
						</thead>
						<tbody id="tbodyCierreCaja">

						</tbody>
					</table>
				</div>
			</div>
		</div>
	
		<?php #require_once 'footer.php'?>
    
		<div class="modal fade" id="modal-CierreCaja" tabindex="-1" role="dialog" aria-labelledby="exampleModalFormTitle" aria-hidden="true">
			<div class="modal-dialog" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalFormTitle">Cierre a Caja</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					
					<form id="formCierreCaja" class="" action="">
						<div class="modal-body">

							<div class="form-group" style="width: 100%">
								<label>*Caja</label>
								<select class="form-control" id="paca_IdCaja" placeholder="" name="paca_IdCaja" onchange="cierreCaja.cargarDoc();" required>
										<!--<option value="">Seleccione una opción</option>
										<option value="1">Caja 1</option>
										<option value="2">Caja 2</option>-->
								</select>
							</div>	

							<div class="form-group" style="width: 100%">
								<label>Vendedor</label>
								<input type="text" class="form-control" placeholder="" id="paca_Vendedor" name="paca_Vendedor"required readonly>
								<input type="hidden" class="form-control" placeholder="" id="paca_IdVendedor" name="paca_IdVendedor"required readonly>
								<input type="hidden" class="form-control" placeholder="" id="paca_Total" name="paca_Total"required readonly>
								<input type="hidden" class="form-control" placeholder="" id="paca_TotalEfectivo" name="paca_TotalEfectivo"required readonly>
								<input type="hidden" class="form-control" placeholder="" id="paca_Base" name="paca_Base"required readonly>
								<input type="hidden" class="form-control" placeholder="" id="paca_Pagos" name="paca_Pagos"required readonly>
								
							</div>

							<div class="form-group">
								<label class="control-label">Descuadre</label>
								<input type="text" class="form-control" placeholder="" id="paca_Descuadre" name="paca_Descuadre" required>
							</div>

							<div class="form-group">
								<label class="control-label">Observaciónes</label>
								<textarea type="text" class="form-control" placeholder="Observaciónes" id="paca_ObservacionesCierre"  name="paca_ObservacionesCierre">  </textarea>
							</div>

							<div class="form-group">
								<label class="form-check-label">Base para el Dia Siguiente </label>
								<input class="form-control" type="text" id="baseVisual"  name="baseVisual"  readonly>
								<input class="form-control form-check-input" type="checkbox"  id="crearBase" name="crearBase" disabled="disabled"  checked>
								<label class="form-check-label">Crear Base Automaticamente</label>
								
								
							</div>
						
						</div>

						<div class="modal-footer" id="modal_footerCierreCaja">
							<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal">
								Cancelar
							</button>
							<button type="submit" class="btn btn-success btn-pill" id="btnCrearCierreCaja">
								Actualizar
							</button>

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
		<script src="../core/cierreCaja.js"></script>
		<!-- <script src="../core/Permisos.js"></script> -->
	</div>	
</body>
</html>