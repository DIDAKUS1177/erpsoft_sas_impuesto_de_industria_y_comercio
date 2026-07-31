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
	<title>Cuentas | DS-POS</title>

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
					<h4 class="h4">Listado de Cuentas $</h4>
					<button type="button" class="btn btn-outline-success" onclick="cuentasContables.crearMovimientoContable()"><span class="ti-plus"></span> Crear Movimiento de Cuenta</button>
				</div>
				<div class="pb-20">
				<table id="cuentasContablesRegistrados" class="data-table table stripe hover nowrap">
						<thead>
							<tr>
								<th>Nombre Cuenta</th>
								<!-- <th>Tipo Movimiento</th> -->
								<th>Saldo en Cuenta</th>
								<th>Acciones</th>
							</tr>
						</thead>
						<tbody id="bodyCuentasContablesRegistrados">
						
						</tbody>
					</table>
					<input class="form-control" id="valor_Total" name="valor_Total" style="text-align:center;" readonly/>
				</div>
			</div>
		</div>
	
		<?php #require_once 'footer.php'?>
    
		<!--Modal Rol-->
		<div class="modal fade" id="modal-CuentasContables" tabindex="-1" role="dialog" aria-labelledby="exampleModalFormTitle" aria-hidden="true">
					<div class="modal-dialog modal-lg" role="document">
						<div class="modal-content ">
							<div class="modal-header">
								<h5 class="modal-title" id="exampleModalFormTitle">Movimiento Cuentas</h5>
								<button type="button" class="close" data-dismiss="modal" aria-label="Close">
									<span aria-hidden="true">&times;</span>
								</button>
							</div>
							
							<form id="formCrearCuentasContables" class="" action="">
								<div class="modal-body">
									<div class="row container">

										<div class="col-sm-12 col-md-6">
											<div class="row">
												<div class="form-group" style="width: 95%">
													<label>*Tipo de Movimiento</label>
													<select class="form-control" style="width: 100%;"
														id="cuco_TipoMovimiento" name="cuco_TipoMovimiento" onChange="cuentasContables.activarCampos(this.value)" required>
														<option value="">Seleccion una opción</option>
														<option value="1">Entrada</option>
														<option value="2">Salida</option></select>
												</div>	
											</div>	
										</div>

										<div class="col-sm-12 col-md-6">
											<div class="row">
												<div class="form-group" style="width: 95%">
													<label>*Numero de Cuenta</label>
													<select class="form-control" id="cuco_IdNumeroCuenta" name="cuco_IdNumeroCuenta" required>
													</select>
												</div>	
											</div>	
										</div>

										<div class="col-sm-12 col-md-6">
											<div class="row">
												<div class="form-group" style="width: 95%">
													<label id="nomTipo" style="display: none">*Tipo</label>
													<select class="form-control" style="width: 100%; display: none"
														id="cuco_TipoSalida" name="cuco_TipoSalida" required></select>
												</div>	
											</div>	
										</div>

										<div class="col-sm-12 col-md-6">
											<div class="row">
												<div class="form-group" style="width: 95%">
													<label id="nomSubTipo" style="display: none">*Sub Tipo</label>
													<select class="form-control" style="width: 100%; display: none"
														id="cuco_SubTipoSalida" name="cuco_SubTipoSalida" required></select>
												</div>	
											</div>	
										</div>

										<div class="col-sm-12 col-md-6">
											<div class="row">
												<div class="form-group" style="width: 95%">
													<label>*Valor</label>
													<input type="number" class="form-control" id="cuco_Valor" name="cuco_Valor"
													 	maxlength="200" placeholder="Valor del Movimiento" required>
												</div>	
											</div>	
										</div>

										<div class="col-sm-12 col-md-6">
											<div class="row">
												<div class="form-group" style="width: 95%">
													<label>*Observaciónes</label>
													<input type="text" class="form-control" id="cuco_Observaciones" name="cuco_Observaciones"
													 	maxlength="200" placeholder="Observaciónes" required>
												</div>	
											</div>	
										</div>
										
										
									</div>

								</div>
								<div class="modal-footer" id="modal_footer">
									<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal"><span class="ti-close"></span>
										Cancelar
									</button>
									<button type="submit" class="btn btn-success btn-pill" id="btnCrearCuentasContables">
										Actualizar
									</button>

								</div>
							</form>
							
						</div>
					</div>
				</div>
                <!-- /.modal-dialog -->



		<!--Modal Detalles-->
		<div class="modal fade" id="modal-Detalles"  role="dialog" aria-labelledby="exampleModalFormTitle" aria-hidden="true">
			<div class="modal-dialog modal-lg" role="document">
				<div class="modal-content ">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalFormTitle">Detalles de los Movimientos de Cuentas</h5>
						<input type="text" class="form-control" id="nombreCuenta" name="nombreCuenta" readonly>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					
					<div class="modal-body">
						<div class="col-sm-12">
							<table id="ltsDetallesNota" class="table table-responsive hover nowrap">
								<thead>
									<tr>
										<th>Tipo Movimiento</th>	
										<th>Descripción Movimiento de Cuenta</th>
										<th>Valor $ </th>
										<th>Fecha Creación </th>
									</tr>
								</thead>
								<tbody id="bodyDetallesNota">
									
								</tbody>
							</table>
						</div>

					</div>
					<div class="modal-footer">
						<button class="btn btn-primary" data-dismiss="modal"><span class="icon-copy ti-close"></span> Cerrar</button>
					</div>
					
					
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
		<script src="../core/cuentasContables.js"></script>
		<!-- <script src="../core/Permisos.js"></script> -->
	</div>	
</body>
</html>