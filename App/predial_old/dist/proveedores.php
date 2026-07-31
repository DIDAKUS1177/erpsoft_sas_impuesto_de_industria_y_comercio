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
	<title>Proveedores | DS-POS</title>

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
					<h4 class="h4">Listado de Proveedores</h4>
					<button type="button" class="btn btn-outline-success" onclick="bod.crearProveedores()"><span class="ti-plus"></span> Crear Proveedores</button>
				</div>
				<div class="pb-20">
				<table id="tblProveedores" class="data-table table stripe hover nowrap">
						<thead>
							<tr>
								<th>Nombre</th>
								<th>NIT</th>
								<th>Telefono</th>
								<th>Acciones</th>
							</tr>
						</thead>
						<tbody id="tbodyProveedores">
						
						</tbody>
					</table>
				</div>
			</div>
		</div>
	
		<?php #require_once 'footer.php'?>
    
		<div class="modal fade" id="modal-Proveedores" tabindex="-1" role="dialog" aria-labelledby="exampleModalFormTitle" aria-hidden="true">
			<div class="modal-dialog  modal-lg" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalFormTitle">Proveedores</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					
					<form id="formProveedores" class="" action="">
						<div class="modal-body">
							<div class="row container">
								
								<div class="col-sm-12 col-md-6">
									<div class="row" >
										<div class="form-group" style="width: 95%">
											<label class="control-label">*Nombre</label>
											<input type="text" class="form-control" placeholder="" id="txtProveedores" required>
										</div>
									</div>
								</div>

								<div class="col-sm-12 col-md-6">
									<div class="row" >
										<div class="form-group" style="width: 95%">
											<label class="control-label">*Razon Social</label>
											<input type="text" class="form-control" placeholder="" id="txtRazonSocial"  name="txtRazonSocial" required>
										</div>
									</div>
								</div>

								<div class="col-sm-12 col-md-6">
									<div class="row" >
										<div class="form-group" style="width: 95%">
											<label class="control-label">*NIT</label>
											<input type="text" class="form-control" placeholder="" id="txtNit" required>
										</div>
									</div>
								</div>

								<div class="col-sm-12 col-md-6">
									<div class="row" >
										<div class="form-group" style="width: 95%">
											<label class="control-label">*Dirección</label>
											<input type="text" class="form-control" placeholder="" id="txtDireccion" required>
										</div>
									</div>
								</div>

								<div class="col-sm-12 col-md-6">
									<div class="row" >
										<div class="form-group" style="width: 95%">
											<label>*Departamento</label>
											<select class="form-control" id="selec_IdDepartamento" name="selec_IdDepartamento" onclick = "bod.getIdMunicipios()"  required>
											</select>
										</div>	
									</div>
								</div>

								<div class="col-sm-12 col-md-6">
									<div class="row" >
										<div class="form-group" style="width: 95%">
											<label>*Ciudad</label>
											<select class="form-control" id="selec_IdCiudad" placeholder="" name="selec_IdCiudad" required>
											</select>
										</div>	
									</div>
								</div>

								<div class="col-sm-12 col-md-6">
									<div class="row" >
										<div class="form-group" style="width: 95%">
											<label class="control-label">*Telefono</label>
											<input type="number" class="form-control" placeholder="" id="txtTelefono" required>
										</div>
									</div>
								</div>
								
								<div class="col-sm-12 col-md-6" >
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>* Correo</label>
											<input type="email" class="form-control" id="usu_Correo" name="usu_Correo"
												maxlength="250" placeholder="E-mail" title='Ingrese Correo Electrónico' required>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-6" >
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*Tipo de Persona</label>
											<select class="form-control" id="selec_IdTipoPersona" placeholder="" name="selec_IdTipoPersona" required>
											</select>
										</div>	
									</div>	
								</div>	

							</div>
						</div>

						<div class="modal-footer" id="modal_footerProveedores">
							<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal">
								Cancelar
							</button>
							<button type="submit" class="btn btn-success btn-pill" id="btnCrearProveedores">
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
		<script src="../core/proveedores.js"></script>
		<!-- <script src="../core/Permisos.js"></script> -->
	</div>	
</body>
</html>