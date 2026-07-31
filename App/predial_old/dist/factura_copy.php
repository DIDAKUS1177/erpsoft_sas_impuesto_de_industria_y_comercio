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
	<title>Factura | predial</title>

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
	<link rel="stylesheet" type="text/css" href="../src/plugins/jquery-steps/jquery.steps.css">
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
			<div class="pd-20 card-box mb-30">
					<div class="clearfix">
						<h4 class="text-black h4">Nuevo Documento</h4>
						<!-- <p class="mb-30">jQuery Step wizard</p> -->
					</div>

<!--****************************************************** -->
		<div class="modal-dialog modal-lg" role="document">
				<div class="modal-content ">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalFormTitle">Crear nota</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<form id="formCrearNota">
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
											<th>Producto</th>
											<th>Cantidad</th>
											<th>Costo</th>
											<th>Bodega</th>
											<th>Acciones</th>
										</tr>
									</thead>
									<tbody>
										<tr>
											<td style="width: 35%">
												<select class="custom-select2 form-control" style="width: 100%;" tabindex="-1" aria-hidden="true" id="detkar_IdProducto" name="detkar_IdProducto">
												</select>
											</td>
											<td style="width: 10%">
												<input type="text" class="form-control" id="detkar_Cantidad" name="detkar_Cantidad" style="text-align: right;">
											</td>
											<td>
												<input type="text" class="form-control" id="detkar_Costo" name="detkar_Costo" style="text-align: right;">
											</td style="width: 20%">
											<td style="width: 30%">
												<select class="custom-select2 form-control" style="width: 100%;" tabindex="-1" aria-hidden="true" id="detkar_IdBodega" name="detkar_IdBodega" >
												</select>
											</td>
											<td align="center" style="width: 5%">
												<button type="button" class="btn btn-social-icon btn-success " data-toggle="tooltip" title="Agregar detalle"  onclick="nota.agregarDetalle()">
													<i class="dw dw-checked"></i>
												</button>
											</td>
										
									</tbody>
								</table>
							</div>

							<div class="col-sm-12">
								<!-- Simple Datatable start -->
								<table id="detalleNotas" class="data-table table stripe hover nowrap">
									<thead>
										<tr>
											<th>Producto</th>
											<th>Cantidad</th>
											<th>Costo</th>
											<th>Bodega</th>
											<th>Acciones</th>
										</tr>
									</thead>
									<tbody id="bodyDetallesNotas">
										
									</tbody>
								</table>
							</div>
							
						</div>
						<div class="modal-footer" id="modal_footer">
							

						</div>
					</form>
					
				</div>
			</div>

<!--****************************************************** -->


					<div class="wizard-content">
						<form class="tab-wizard wizard-circle wizard">
							<h5>Datos generales</h5>
							<section>
								<div class="row">
									<div class="col-sm-12 col-md-6">
										<div class="form-group">
											<label>Tipo Documento</label>
											<select class="custom-select form-control" id="sltTipoDocCli" name="sltTipoDocCli"> 
												
											</select>
										</div>
									</div>

									<div class="col-sm-12 col-md-6">
										<div class="form-group">
											<label >Identificación</label>
											<input type="text" class="form-control" id="txtDocCliente" name="txtDocCliente" required>
										</div>
									</div>
									<div class="col-sm-12 col-md-6">
										<div class="form-group">
											<label >Nombres</label>
											<input type="text" class="form-control" id="txtNombres" name="txtNombres" required>
										</div>
									</div>
								
									<div class="col-sm-12 col-md-6">
										<div class="form-group">
											<label>Correo</label>
											<input type="email" class="form-control" id="txtCorreo" name="txtCorreo" required>
										</div>
									</div>
									<div class="col-sm-12 col-md-6">
										<div class="form-group">
											<label>Correo copia</label>
											<input type="text" class="form-control" id="txtCorreoCopia" name="txtCorreoCopia">
										</div>
									</div>
								
									<div class="col-sm-12 col-md-6">
										<div class="form-group">
											<label>Teléfono</label>
											<input type="text" class="form-control" id="txtTelefono" name="txtTelefono">
										</div>
									</div>

									<div class="col-sm-12 col-md-6">
										<div class="form-group">
											<label >Fecha</label>
											<input type="text" class="form-control date-picker" id="txtFecha" name="txtFecha">
										</div>
									</div>

									<div class="col-sm-12 col-md-6">
										<div class="form-group">
											<label>Observaciones</label>
											<textarea class="form-control" id="txtObservaciones" name="txtObservaciones"></textarea>
										</div>
									</div>
								</div>
							</section>
							<!-- Step 2 -->
							<h5>Detalle documento</h5>
							<section>
								<div class="row">
									<div class="col-md-6">
										<div class="form-group">
											<label>Job Title :</label>
											<input type="text" class="form-control">
										</div>
									</div>
									<div class="col-md-6">
										<div class="form-group">
											<label>Company Name :</label>
											<input type="text" class="form-control">
										</div>
									</div>
									<div class="col-md-12">
										<div class="form-group">
											<label>Job Description :</label>
											<textarea class="form-control"></textarea>
										</div>
									</div>
								</div>
							</section>
							<!-- Step 3 -->
							<h5>Interview</h5>
							<section>
								<div class="row">
									<div class="col-md-6">
										<div class="form-group">
											<label>Interview For :</label>
											<input type="text" class="form-control">
										</div>
										<div class="form-group">
											<label>Interview Type :</label>
											<select class="form-control">
												<option>Normal</option>
												<option>Difficult</option>
												<option>Hard</option>
											</select>
										</div>
									</div>
									<div class="col-md-6">
										<div class="form-group">
											<label>Interview Date :</label>
											<input type="text" class="form-control date-picker" placeholder="Select Date">
										</div>
										<div class="form-group">
											<label>Interview Time :</label>
											<input class="form-control time-picker" placeholder="Select time" type="text">
										</div>
									</div>
								</div>
							</section>
							<!-- Step 4 -->
							<h5>Remark</h5>
							<section>
								<div class="row">
									<div class="col-md-6">
										<div class="form-group">
											<label>Behaviour :</label>
											<input type="text" class="form-control">
										</div>
										<div class="form-group">
											<label>Confidance</label>
											<input type="text" class="form-control">
										</div>
										<div class="form-group">
											<label>Result</label>
											<select class="form-control">
												<option>Select Result</option>
												<option>Selected</option>
												<option>Rejected</option>
											</select>
										</div>
									</div>
									<div class="col-md-6">
										<div class="form-group">
											<label>Comments</label>
											<textarea class="form-control"></textarea>
										</div>
									</div>
								</div>
							</section>
						</form>
					</div>
				</div>
		</div>
	
		<?php #require_once 'footer.php'?>
    
		<div class="modal fade" id="modal-Bodega" tabindex="-1" role="dialog" aria-labelledby="exampleModalFormTitle" aria-hidden="true">
			<div class="modal-dialog" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalFormTitle">Bodega</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					
					<form id="formBodega" class="" action="">
						<div class="modal-body">
							<div class="form-group">
								<label class="control-label">*Nombre</label>
								<input type="text" class="form-control" placeholder="" id="txtBodega" required>
							</div>

						</div>
						<div class="modal-footer" id="modal_footerBodega">
							<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal">
								Cancelar
							</button>
							<button type="submit" class="btn btn-success btn-pill" id="btnCrearBodega">
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
		<script src="../src/plugins/jquery-steps/jquery.steps.js"></script>
		<script src="../vendors/scripts/steps-setting.js"></script>
		<script src="../src/plugins/sweetalert2/sweetalert2.all.js"></script>

		<script src="../core/factura.js"></script>
		<!-- <script src="../core/Permisos.js"></script> -->
	</div>	
</body>
</html>