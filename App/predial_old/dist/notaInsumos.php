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
	<title>Notas Insumos | predial</title>

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
			<div class="card-box mb-30">
				<div class="pd-20 d-flex justify-content-between" style="margin-top: 1%">
					<h4 class="h4">Listado de notas - Insumos</h4>
					<button type="button" class="btn btn-outline-success" onclick="notaInsumo.crearNotaInsumo()"><span class="ti-plus"></span> Crear Nota Entrada</button>
					<br>
					<button type="button" class="btn btn-outline-success" onclick="notaInsumo.crearNotaInsumoS()"><span class="ti-plus"></span> Crear Nota Salida</button>
				</div>			
				
				<div class="pb-20">
				<table id="notasInsumoRegistradas" class="data-table table stripe hover nowrap">
						<thead>
							<tr>
								<th>Fecha</th>
								<th>Tipo</th>
								<th>Observaiones</th>
								<th>Acciones</th>
							</tr>
						</thead>
						<tbody id="bodyNotasInsumoRegistradas">
							
						</tbody>
					</table>
				</div>
			</div>
		</div>
	
		<?php #require_once 'footer.php'?>
    
		<!--Modal Nota Inusmo-->
		<div class="modal fade" id="modal-NotaInsumo"  role="dialog" aria-labelledby="exampleModalFormTitle" aria-hidden="true">
			<div class="modal-dialog modal-lg" role="document">
				<div class="modal-content ">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalFormTitle">Crear nota Insumo</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<form id="formCrearNotaInsumo">
						<div class="modal-body">
							<div class="col-sm-12">
								<div class="row">
									<div class="form-group" style="width: 100%">
										<label>Tipo <span class="require">*</span></label>
										<select class="form-control" style="width: 100%;"
										tabindex="-1" aria-hidden="true" id="kar_Tipo" name="kar_Tipo" required>
											<option value="">Seleccione una opción</option>
											<option value="1">Entrada</option>
											<option value="2">Salida</option>
										</select>
									</div>	
								</div>	
							</div>

							<div class="col-sm-12">
								<div class="row">
									<div class="form-group" style="width: 100%">
										<label>Observaciones</label>
										<input class="form-control" id="Kar_Observaciones" name="Kar_Observaciones" maxlength="500" 
											placeholder="Observaciones..." required/>
									</div>	
								</div>	
							</div>

							<div class="col-sm-12">
								<table class="table hover nowrap">
									<thead>
										<tr>
											<th>Insumo</th>
											<th>Cantidad</th>
											<th>Costo</th>
											<th>Bodega</th>
											<th>Acciones</th>
										</tr>
									</thead>
									<tbody>
										<tr>
											<td style="width: 35%">
												<select class="custom-select2 form-control" style="width: 100%;" tabindex="-1" aria-hidden="true" id="detkar_IdInsumo" name="detkar_IdInsumo">
												</select>
											</td>
											<td style="width: 10%">
												<input type="text" class="form-control" id="detkar_Cantidad" name="detkar_Cantidad"  style="text-align: right;"  required>
											</td>
											<td>
												<input type="text" class="form-control" id="detkar_Costo" name="detkar_Costo" style="text-align: right;">
											</td style="width: 20%">
											<td style="width: 30%">
												<select class="custom-select2 form-control" style="width: 100%;" tabindex="-1" aria-hidden="true" id="detkar_IdBodega" name="detkar_IdBodega" >
												</select>
											</td>
											<td align="center" style="width: 5%">
												<button type="button" class="btn btn-social-icon btn-success " data-toggle="tooltip" title="Agregar detalle"  onclick="notaInsumo.agregarDetalle()">
													<i class="dw dw-checked"></i>
												</button>
											</td>
										
									</tbody>
								</table>
							</div>

							<div class="col-sm-12">
								<!-- Simple Datatable start -->
								<table id="detalleNotasInsumo" class="data-table table stripe hover nowrap table-responsive">
									<thead>
										<tr>
											<th>Insumo</th>
											<th>Cantidad</th>
											<th>Costo</th>
											<th>Bodega</th>
											<th>Acciones</th>
										</tr>
									</thead>
									<tbody id="bodyDetallesNotasInsumo">
										
									</tbody>
								</table>
							</div>
							
						</div>
						<div class="modal-footer" id="modal_footer">
							

						</div>
					</form>
					
				</div>
			</div>
		</div>

		<!--Modal Detalles-->
		<div class="modal fade" id="modal-Detalles"  role="dialog" aria-labelledby="exampleModalFormTitle" aria-hidden="true">
			<div class="modal-dialog modal-lg" role="document">
				<div class="modal-content ">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalFormTitle">Detalles nota Insumo</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					
					<div class="modal-body">
						<div class="col-sm-12">
							<table id="ltsDetallesNotaInsumo" class="table table-responsive hover nowrap">
								<thead>
									<tr>
										<th>insumo</th>
										<th>Bodega</th>
										<th>Cant Entrada</th>
										<th>Valor Entrada</th>
										<th>Cant Salida</th>
										<th>Valor Salida</th>
										<th>Cant Saldo</th>
										<th>Valor Saldo</th>
									</tr>
								</thead>
								<tbody id="bodyDetallesNotaInsumo">
									
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

		<!-- jquery-number js -->
		<script src="../src/plugins/jquery-number/jquery.number.js"></script>
		
		<script src="../src/plugins/sweetalert2/sweetalert2.all.js"></script>
		<script src="../core/notaInsumo.js"></script>
		<!-- <script src="../core/Permisos.js"></script> -->
	</div>	
</body>
</html>