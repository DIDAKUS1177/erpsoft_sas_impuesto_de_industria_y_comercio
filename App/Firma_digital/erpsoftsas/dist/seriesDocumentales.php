<?php
    require_once '../business/globals.php';
    include_once('../business/class.sessions.php');
?>
<!DOCTYPE html>
<html>
<head>
	<!-- Basic Page Info -->
	<meta charset="utf-8">
	<title>Series Documentales |Gestor Documental </title>

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
					<h4 class="h4">Listado de Series Documentales</h4>
					<button type="button" class="btn btn-outline-success" onclick="seriesDocumentales.crearSeriesDocumentales()"><span class="ti-plus"></span> Crear Serie Documental</button>
				</div>
				<div class="pb-20">
				<table id="seriesDocumentalesRegistrados" class="data-table table stripe hover nowrap">
						<thead>
							<tr>
								<th>N°</th>
								<th>Nombre</th>
								<th>Descripción</th>
								<th>Acciones</th>
								<th>Sub-Series</th>
							</tr>
						</thead>
						<tbody id="bodySeriesDocumentalesRegistrados">
						
						</tbody>
					</table>
				</div>
			</div>
		</div>
    

		<!--Modal seriesDocumentales-->
		<div class="modal fade" id="modal-SeriesDocumentales" tabindex="-1" role="dialog" aria-labelledby="exampleModalFormTitle" aria-hidden="true">
			<div class="modal-dialog modal-lg" role="document">
				<div class="modal-content ">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalFormTitle">Series Documentales</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					
					<form id="formCrearSeriesDocumentales" class="" action="">
						<div class="modal-body">
							<div class="row container">

								<div class="col-sm-12 col-md-6">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>* Dependencia</label>
											<select class="form-control" style="width: 100%;"
												tabindex="-1" aria-hidden="true" id="cat_IdDependencia" name="cat_IdDependencia" required></select>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-3">
									<div class="row" >
										<div class="form-group" style="width: 95%">
											<label>* Codigo</label>
											<input type="text" class="form-control" id="cat_Codigo" name="cat_Codigo" 
												maxlength="5" placeholder="Codigo" title='Ingrese Codigo' required>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-3">
									<div class="row" >
										<div class="form-group" style="width: 95%">
											<label>* Sigla</label>
											<input type="text" class="form-control" id="cat_Sigla" name="cat_Sigla"
													maxlength="5" placeholder="Sigla" title='Ingrese Sigla' required>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-12">
									<div class="row" >
										<div class="form-group" style="width: 95%">
											<label>* Nombre</label>
											<input type="text" class="form-control" id="cat_Nombre" name="cat_Nombre"
												maxlength="100" placeholder="Nombre" title='Ingrese Nombre' required>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-12">
									<div class="row" >
										<div class="form-group" style="width: 95%">
											<label>* Descripción</label>
											<textarea class="form-control" id="cat_Descripcion" name="cat_Descripcion" rows="4" cols="50" title='Ingrese Descripción' placeholder="Descripción" required></textarea>
										</div>	
									</div>	
								</div>

							</div>
						</div>

						<div class="modal-footer" id="modal_footer">
							<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal"><span class="ti-close"></span>
								Cancelar
							</button>
							<button type="submit" class="btn btn-success btn-pill" id="btnCrearSeriesDocumentales">
								Actualizar
							</button>

						</div>
					</form>
					
				</div>
			</div>
		</div>


			<!--Modal SubseriesDocumentales-->
		<div class="modal fade" id="modal-SubSeriesDocumentales" tabindex="-1" role="dialog" aria-labelledby="exampleModalFormTitle" aria-hidden="true">
			<div class="modal-dialog modal-lg" role="document">
				<div class="modal-content ">
					<div class="modal-header">
						<h5  style="width: 90%" class="modal-title" id="exampleModalFormTitle">Crear Sub Serie Documental para:</h5>
						<input type="text" class="form-control" style ="background-color: white; border: none; box-shadow: none; color: inherit;" id="nombreSerie" name="nombreSerie">
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					
					<form id="formCrearSubSeriesDocumentales" class="" action="">
						<div class="modal-body">
							<div class="row container">

								<div class="col-sm-12 col-md-6">
									<div class="row" >
										<div class="form-group" style="width: 95%">
											<label>* Nombre</label>
											<input type="text" class="form-control" id="subc_Nombre" name="subc_Nombre"
												maxlength="100" placeholder="Nombre" title='Ingrese Nombre' required>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-3">
									<div class="row" >
										<div class="form-group" style="width: 95%">
											<label>* Codigo</label>
											<input type="text" class="form-control" id="subc_Codigo" name="subc_Codigo" 
												maxlength="5" placeholder="Codigo" title='Ingrese Codigo' required>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-3">
									<div class="row" >
										<div class="form-group" style="width: 95%">
											<label>* Sigla</label>
											<input type="text" class="form-control" id="subc_Sigla" name="subc_Sigla"
													maxlength="5" placeholder="Sigla" title='Ingrese Sigla' required>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-12">
									<div class="row" >
										<div class="form-group" style="width: 95%">
											<label>* Descripción</label>
											<textarea class="form-control" id="subc_Descripcion" name="subc_Descripcion" rows="4" cols="50" title='Ingrese Descripción' placeholder="Descripción" required></textarea>
										</div>	
									</div>	
								</div>

							</div>
						</div>

						<div class="modal-footer" id="modal_footer">
							<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal"><span class="ti-close"></span>
								Cancelar
							</button>
							<button type="submit" class="btn btn-success btn-pill" id="btnCrearSubSeriesDocumentales">
								Actualizar
							</button>

						</div>
					</form>
					
				</div>
			</div>
		</div>
		

		<!--Modal Listado de Sub Series Documentales-->
		<div class="modal fade" id="modal-LisSubSeries"  role="dialog" aria-labelledby="exampleModalFormTitle" aria-hidden="true">
			<div class="modal-dialog modal-lg" role="document">
				<div class="modal-content ">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalFormTitle">Listado Sub Series de: </h5>
						<input type="text" class="form-control" id="nombreSeriesDocumentales" name="nombreSeriesDocumentales" readonly>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					
					<div class="modal-body">
						<div class="col-sm-12">
							<table id="ltsLisSubSeries" class="table table-responsive hover nowrap">
								<thead>
									<tr>
										<th>Codigo</th>
										<th>Nombre </th>
										<th>Sigla</th>
										<th>Acciones</th>
									</tr>
								</thead>
								<tbody id="bodyLisSubSeries">
									
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
		<script src="../core/seriesDocumentales.js?v=<?php echo time(); ?>"></script>
		<!-- <script src="../core/Permisos.js"></script> -->
	</div>	
</body>
</html>