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
	<title>Insumos | predial</title>

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
					<h4 class="h4">Listado de Insumos</h4>
					<button type="button" class="btn btn-outline-success" onclick="prod.crearInsumo()"><span class="ti-plus"></span> Crear Insumo</button>
				</div>
				<div class="pb-20">
					<table id="insumosRegistrados" class="data-table table stripe hover nowrap">
						<thead>
							<tr>
								<th>Código</th>
								<th>Nombre</th>
								<th>Categoria</th>
								<th>Subcategoria</th>
								<th>Stock</th>
								<th>Acciones</th>
							</tr>
						</thead>
						<tbody id="bodyInsumosRegistrados">
						
						</tbody>
					</table>
				</div>
			</div>
		</div>
	
		<?php #require_once 'footer.php'?>
    
		<!--Modal Insumo-->
		<div class="modal fade" id="modal-Insumo" tabindex="-1" role="dialog" aria-labelledby="exampleModalFormTitle" aria-hidden="true">
			<div class="modal-dialog modal-lg" role="document">
				<div class="modal-content ">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalFormTitle">Insumo</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					
					<form id="formCrearInsumo" class="" action="">
						<div class="modal-body">
							<div class="row container">
								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*Código</label>
											<input type="text" class="form-control" id="ins_Codigo" name="ins_Codigo" readonly>
										</div>	
									</div>	
								</div>
								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*Nombre</label>
											<input type="text" class="form-control" id="ins_Nombre" name="ins_Nombre" placeholder="Nombre..." required>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>Código de Barras</label>
											<input type="text" class="form-control" id="ins_CodBarras" name="ins_CodBarras" placeholder="Código de barras..." >
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*Proveedor</label>
											<select class="form-control" style="width: 100%;"
												tabindex="-1" aria-hidden="true" id="ins_IdProveedor" name="ins_IdProveedor" required></select>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*Categoria</label>
											<select class="form-control" style="width: 100%;"
												tabindex="-1" aria-hidden="true" id="ins_IdCategoria" name="ins_IdCategoria" required></select>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*Subcategoria</label>
											<select class="form-control" style="width: 100%;"
												tabindex="-1" aria-hidden="true" id="ins_IdSubCategoria" name="ins_IdSubCategoria" required></select>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*Tipo Cantidad</label>
											<select class="form-control" style="width: 100%;"
												tabindex="-1" aria-hidden="true" id="ins_IdTipoCantidad" name="ins_IdTipoCantidad" required></select>
										</div>	
									</div>	
								</div>							

								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*Tipo Unidad</label>
											<select class="form-control" style="width: 100%;"
												tabindex="-1" aria-hidden="true" id="ins_IdTipoUnidad" name="ins_IdTipoUnidad" required></select>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>Referencia #1 (Nombre)</label>
											<input type="text" class="form-control" id="ins_ReferenciaNombre1" name="ins_ReferenciaNombre1" placeholder="Referencia #1" >
										</div>	
									</div>	
								</div>
							
								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>Referencia #1 (Valor)</label>
											<input type="text" class="form-control" id="ins_ReferenciaValor1" name="ins_ReferenciaValor1" placeholder="Referencia #1" >
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>Referencia #2 (Nombre)</label>
											<input type="text" class="form-control" id="ins_ReferenciaNombre2" name="ins_ReferenciaNombre2" placeholder="Referencia #2" >
										</div>	
									</div>	
								</div>		

								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>Referencia #2 (Valor)</label>
											<input type="text" class="form-control" id="ins_ReferenciaValor2" name="ins_ReferenciaValor2" placeholder="Referencia #2" >
										</div>	
									</div>	
								</div>	

								<div class="col-sm-12 col-md-12" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<!--<label>Imagen</label>-->
											<input type="hidden" class="form-control" id="imagen" name="imagen" placeholder="Imagen" >
										</div>	
									</div>	
								</div>
								
							</div>

						</div>
						<div class="modal-footer" id="modal_footer">
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

		<!-- jquery-number js -->
		<script src="../src/plugins/jquery-number/jquery.number.js"></script>
		
		<script src="../src/plugins/sweetalert2/sweetalert2.all.js"></script>
		<script src="../core/insumo.js"></script>
		<!-- <script src="../core/Permisos.js"></script> -->
	</div>	
</body>
</html>