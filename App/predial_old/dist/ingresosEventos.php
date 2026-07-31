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
	<title>Pagos/Abonos | predial</title>

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
					<h4 class="h4">Ingresos</h4>
					<button type="button" class="btn btn-outline-success" onclick="bod.crearPagosAbonos()"><span class="ti-plus"></span> Crear Ingreso</button>
				</div>
				<div class="pb-20">
				<table id="tblPagosAbonos" class="data-table table stripe hover nowrap">
						<thead>
							<tr>
								<th>Fecha</th>	
								<th>Proyecto</th>	
								<th>Descripción</th>	
								<th>Valor</th>
								<th>Acciones</th>
							</tr>
						</thead>
						<tbody id="tbodyPagosAbonos">
						
						</tbody>
					</table>
				</div>
			</div>
		</div>
	
		<?php #require_once 'footer.php'?>

		<!--          
			MODAL DE CREACIÓN DE PROYECTOS
		-->
    
		<div class="modal fade" id="modal-PagosAbonos" tabindex="-1" role="dialog" aria-labelledby="exampleModalFormTitle" aria-hidden="true">
			<div class="modal-dialog  modal-lg" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalFormTitle">Crear Ingreso</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					
					<form id="formPagosAbonos" class="" action="">
						<div class="modal-body">
							<div class="row container">

								<div class="col-sm-12 col-md-12">
									<div class="row" >
										<div class="form-group" style="width: 100%">
											<h5 class="modal-title text-center">DATOS INICIALES</h5>
										</div>
									</div>
								</div>

								<div class="col-sm-12 col-md-6" >
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*Proyecto</label>
											<select class="form-control" id="pago_IdProyecto" placeholder="" name="pago_IdProyecto" required>
											</select>
										</div>	
									</div>	
								</div>	

								<div class="col-sm-12 col-md-6">
									<div class="row" >
										<div class="form-group" style="width: 99%">
											<label class="control-label">*Fecha</label>
											<input type="date" class="form-control" placeholder="Ingrese Fecha" id="txtFecha"  name="txtFecha" required>
										</div>
									</div>
								</div>
								
								<div class="col-sm-12 col-md-6">
									<div class="row" >
										<div class="form-group" style="width: 99%">
											<label class="control-label">*Descripción</label>
											<input type="text" class="form-control" placeholder="Ingrese Descripción del Pago/Abono" name="pago_Descripcion" id="pago_Descripcion" required>
										</div>
									</div>
								</div>

								<div class="col-sm-12 col-md-6">
									<div class="row" >
										<div class="form-group" style="width: 99%">
											<label id="cuValor" class="control-label">*Valor</label>
											<input type="text" data-type="currency" class="form-control" placeholder="Ingrese Valor" id="pago_Valor"  name="pago_Valor">
										</div>
									</div>
								</div>

								<div class="col-sm-12 col-md-6">
									<div class="row" >
										<div class="form-group" style="width: 99%">
											<input name="chec" type="checkbox" id="chec" onChange="bod.activarSupervisor(this)"> <label id="cuentasTeso" class="control-label">Ingreso a Cuentas de Tesoreria</label>
											<select class="form-control" id="select_Cuentas" name="select_Cuentas" style="display:none">
											</select>
										</div>
									</div>
								</div>

								<div class="col-sm-12 col-md-12">
									<div class="row" >
										<div class="form-group" style="width: 99%">
										<input type="text" class="form-control text-center" id="cuentaContable"  name="cuentaContable" readonly>
										</div>
									</div>
								</div>

								

							</div>
						</div>

						<div class="modal-footer" id="modal_footerPagosAbonos">
							<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal">
								Cancelar
							</button>
							<button type="submit" class="btn btn-success btn-pill" id="btnCrearClientes">
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
		<script src="../core/pagosAbonos.js"></script>
		<!-- <script src="../core/Permisos.js"></script> -->
	</div>	
</body>
</html>