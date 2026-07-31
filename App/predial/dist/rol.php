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
	<title>Roles | DS-POS</title>

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
					<h4 class="h4">Listado de Roles</h4>
					<button type="button" class="btn btn-outline-success" onclick="rol.crearRol()"><span class="ti-plus"></span> Crear Rol</button>
				</div>
				<div class="pb-20">
				<table id="tblRol" class="data-table table stripe hover nowrap">
						<thead>
							<tr>
								<th>Nombre</th>
								<th>Editar</th>
								<th>Activar/Inactivar</th>
								<th>Asiginar Permisos</th>
							</tr>
						</thead>
						<tbody id="tbodyRol">
						
						</tbody>
					</table>
				</div>
			</div>

			<div class="pd-ltr-20 xs-pd-20-10" id="ltsPermisos" hidden>
				<div class="row clearfix d-flex justify-content-between" >
					
					<div class="col-lg-4 col-md-4 col-sm-12 mb-30">
						<div class="card card-box">
							<div class="pd-20 card-header">
								<h4 class="h4">Listado de Módulos</h4>
							</div>
							<div class="card-body">
								<form >
									<div class="form-row" id="ltsModulos">
									</div>
								</form>
							</div>
						</div>
					</div>
					
					<!--<div style="float:right; width: 180px;">-->
					<div class="col-lg-6 col-md-8 col-sm-12 mb-30 position-fixed" style="right:0;">
						<div class="card card-box">
							<div class="pd-20 card-header">
								<h4 class="h4">Listado de submódulos</h4>
							</div>
							<div class="card-body">
								<form>
									<div class="form-row" id="ltsSubModulos">
									</div>
								</form>
							</div>
							<div class="card-footer">
								<button type="button" class="btn btn-secondary" onclick="rol.back()">Cancelar</button>
								<button type="button" id="btnAsignarPermisos" class="btn btn-success">Asignar Permisos</button>
							</div>
						</div>
					</div>
					<!--</div>-->

				</div>
			</div>
		</div>
	
		<?php #require_once 'footer.php'?>
    
		<div class="modal fade" id="modal-editRol" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title" id="tituloModalRol"></h4>
						<button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
					</div>
					<form id="form-editRol">
						<div class="modal-body">
							<div class="form-group">
								<label class="control-label">* Rol</label>
								<input type="text" maxlength="100" title='Máximo 100 Caracteres'class="form-control" placeholder="Nombre" id="txtRol" required>
							</div>
						</div>
						<div class="modal-footer">
							<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
							<button type="submit" class="btn btn-success" id="btnModalRol"></button>
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
		<script src="../core/rol.js"></script>
		<!-- <script src="../core/Permisos.js"></script> -->
	</div>	
</body>
</html>