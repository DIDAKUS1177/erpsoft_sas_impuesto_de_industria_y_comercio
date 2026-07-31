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
	<title>Eventos | predial</title>

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
					<h4 class="h4">Listado de Eventos</h4>
					<button type="button" class="btn btn-outline-success" onclick="eventos.getEventos(1)">Eventos Activos</button>
					<button type="button" class="btn btn-outline-success" onclick="eventos.getEventos(0)">Eventos Inactivos</button>
					<button type="button" class="btn btn-outline-success" onclick="eventos.crearEventos()"><span class="ti-plus"></span> Crear Eventos</button>
				</div>
				<div class="pb-20">
				<table id="eventosRegistrados" class="data-table table stripe hover nowrap">
						<thead>
							<tr>
								<th>Nombre</th>							
								<th>Acciones</th>
							</tr>
						</thead>
						<tbody id="bodyEventosRegistrados">
						
						</tbody>
					</table>
				</div>
			</div>
		</div>
	
		<?php #require_once 'footer.php'?>
    
		<!--Modal Rol-->
		<div class="modal fade" id="modal-Eventos" tabindex="-1" role="dialog" aria-labelledby="exampleModalFormTitle" aria-hidden="true">
					<div class="modal-dialog modal-lg" role="document">
						<div class="modal-content ">
							<div class="modal-header">
								<h5 class="modal-title" id="exampleModalFormTitle">Eventos</h5>
								<button type="button" class="close" data-dismiss="modal" aria-label="Close">
									<span aria-hidden="true">&times;</span>
								</button>
							</div>
							
							<form id="formCrearEventos" class="" action="">
								<div class="modal-body">
									<div class="row container">
										<div class="col-sm-12 col-md-6">
											<div class="row" >
												<div class="form-group" style="width: 95%">
													<label>*Nombre</label>
													<input type="text" class="form-control" id="eve_Nombre" name="eve_Nombre"
													 	maxlength="200" placeholder="Nombre" required>
												</div>	
											</div>	
										</div>

										<div class="col-sm-12 col-md-6">
											<div class="row" >
												<div class="form-group" style="width: 95%">
													<label>Descripción</label>
													<input type="text" class="form-control" id="eve_Descripcion" name="eve_Descripcion"
													 	maxlength="200" placeholder="Nombre">
												</div>	
											</div>	
										</div>

										<div class="col-sm-12 col-md-6">
											<div class="row" >
												<div class="form-group" style="width: 95%">
													<label>*Nombre Cliente</label>
													<input type="text" class="form-control" id="eve_NombreCliente" name="eve_NombreCliente"
													 	maxlength="200" placeholder="Nombre" required>
												</div>	
											</div>	
										</div>

										<div class="col-sm-12 col-md-6">
											<div class="row" >
												<div class="form-group" style="width: 95%">
													<label>*Telefono Cliente</label>
													<input type="number" class="form-control" id="eve_TelefonoCliente" name="eve_TelefonoCliente"
													 	maxlength="200" placeholder="Nombre" required>
												</div>	
											</div>	
										</div>
										
										<div class="col-sm-12 col-md-12">
											<div class="row" >
												<div class="form-group" style="width: 95%">
													<label>Email</label>
													<input type="email" class="form-control" id="eve_Email" name="eve_Email"
													 	maxlength="200" placeholder="Nombre">
												</div>	
											</div>	
										</div>

										<div class="col-sm-12 col-md-4">
											<div class="row" >
												<div class="form-group" style="width: 95%">
													<label>*Fecha Evento</label>
													<input type="date" class="form-control" id="eve_FechaEvento" name="eve_FechaEvento"
													 	maxlength="200" placeholder="Nombre" required>
												</div>	
											</div>	
										</div>

										<div class="col-sm-12 col-md-4">
											<div class="row" >
												<div class="form-group" style="width: 95%">
													<label>*Lugar Evento</label>
													<input type="text" class="form-control" id="eve_LugarEvento" name="eve_LugarEvento"
													 	maxlength="200" placeholder="Nombre" required>
												</div>	
											</div>	
										</div>

										<div class="col-sm-12 col-md-4">
											<div class="row" >
												<div class="form-group" style="width: 95%">
													<label>*Valor Evento</label>
													<input type="number" class="form-control" id="eve_ValorEvento" name="eve_ValorEvento"
													 	maxlength="200" placeholder="Nombre" required>
												</div>	
											</div>	
										</div>

										<div class="col-sm-12 col-md-12">
											<div class="row" >
												<div class="form-group" style="width: 95%">
													<label>Notas</label>
													<input type="text" class="form-control" id="eve_Notas" name="eve_Notas"
													 	maxlength="200" placeholder="Nombre">
												</div>	
											</div>	
										</div>
									</div>
								</div>

								<div class="modal-footer" id="modal_footer">
									<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal"><span class="ti-close"></span>
										Cancelar
									</button>
									<button type="submit" class="btn btn-success btn-pill" id="btnCrearEventos">
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
		<script src="../core/eventos.js"></script>
		<!-- <script src="../core/Permisos.js"></script> -->
	</div>	
</body>
</html>