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
	<title>Resoluciones | DS-POS</title>

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
					<h4 class="h4">Listado de Resoluciones</h4>
					<button type="button" class="btn btn-outline-success" onclick="resolucion.crearResolucion()"><span class="ti-plus"></span> Crear Resolucion</button>
				</div>
				<div class="pb-20">
				<table id="resolucionesRegistrados" class="data-table table stripe hover nowrap">
						<thead>
							<tr>
								<th>Tipo</th>							
								<th>Prefijo/Numeración</th>
								<th>Fecha Autorización</th>
								<th>Fecha Vencimiento</th>
								<th>Estado</th>
								<th>Acciones</th>
							</tr>
						</thead>
						<tbody id="bodyResolucionesRegistrados">
						
						</tbody>
					</table>
				</div>
			</div>
		</div>
	
		<?php #require_once 'footer.php'?>
    
		<!--Modal Rol-->
		<div class="modal fade" id="modal-Resolucion" tabindex="-1" role="dialog" aria-labelledby="exampleModalFormTitle" aria-hidden="true">
					<div class="modal-dialog modal-lg" role="document">
						<div class="modal-content ">
							<div class="modal-header">
								<h5 class="modal-title" id="exampleModalFormTitle">Resolucion</h5>
								<button type="button" class="close" data-dismiss="modal" aria-label="Close">
									<span aria-hidden="true">&times;</span>
								</button>
							</div>
							
							<form id="formCrearResolucion" class="" action="">
								<div class="modal-body">
									<div class="row container">

										<div class="col-sm-12 col-md-6">
											<div class="row">
												<div class="form-group" style="width: 95%">
													<label>*Tipo de Documento</label>
													<select class="form-control" style="width: 100%;"
														id="reso_IdTipoDocumento" name="reso_IdTipoDocumento" onChange="resolucion.activarCampos(this.value)" required></select>
												</div>	
											</div>	
										</div>

										<div class="col-sm-12 col-md-6">
											<div class="row" >
												<div class="form-group" style="width: 95%">
													<label>*Prefijo</label>
													<input type="text" class="form-control" id="reso_Prefijo" name="reso_Prefijo"
													 	maxlength="5" placeholder="Prefijo" required>
												</div>	
											</div>	
										</div>

										<div class="col-sm-12 col-md-6">
											<div class="row" >
												<div class="form-group" style="width: 95%">
													<label id="desde"  style="display: none">*Desde</label>
													<input type="number" style="display: none" class="form-control" id="reso_NumeroInicial" name="reso_NumeroInicial"
													 	maxlength="10" placeholder="Numero Inicial" required>
												</div>	
											</div>	
										</div>

										<div class="col-sm-12 col-md-6">
											<div class="row" >
												<div class="form-group" style="width: 95%">
													<label id="hasta"  style="display: none">*Hasta</label>
													<input type="number" style="display: none" class="form-control" id="reso_NumeroFinal" name="reso_NumeroFinal"
													 	maxlength="10" placeholder="Numero Final" required>
												</div>	
											</div>	
										</div>

										<div class="col-sm-12 col-md-6">
											<div class="row" >
												<div class="form-group" style="width: 95%">
													<label id="numAur"  style="display: none">*Numero de Autorización</label>
													<input type="number" style="display: none" class="form-control" id="reso_Numero" name="reso_Numero"
													 	maxlength="10" placeholder="Numero de Autorización" required>
												</div>	
											</div>	
										</div>
										
										<div class="col-sm-12 col-md-6">
											<div class="row" >
												<div class="form-group" style="width: 95%">
													<label id="fechAut"  style="display: none">*Fecha de Autorización</label>
													<input type="date" style="display: none" class="form-control" placeholder="Fecha de Autorización" 
														id="reso_FechaAutorizacion" name="reso_FechaAutorizacion" required >
												</div>	
											</div>	
										</div>

										<div class="col-sm-12 col-md-6">
											<div class="row" >
												<div class="form-group" style="width: 95%">
													<label id="fechVenci" style="display: none">*Fecha de Vencimiento</label>
													<input type="date" style="display: none" class="form-control" placeholder="Fecha de Vencimiento" 
														id="reso_FechaVencimiento" name="reso_FechaVencimiento" required >
												</div>	
											</div>	
										</div>

									</div>
								</div>

								<div class="modal-footer" id="modal_footer">
									<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal"><span class="ti-close"></span>
										Cancelar
									</button>
									<button type="submit" class="btn btn-success btn-pill" id="btnCrearResolucion">
										Actualizar
									</button>
								</div>

							</form>
							
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
		<script src="../core/resolucion.js"></script>
		<!-- <script src="../core/Permisos.js"></script> -->
	</div>	
</body>
</html>