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
	<title>Informes Eventos | predial</title>

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
				<div class="modal-header">
					<h5 class="modal-title" id="exampleModalFormTitle">INFORME UTILIDAD EVENTOS</h5>
				</div>

				<form id="formGenerarInforme" method="post" action="../business/controller/class.informesEventos.php">
					<div class="modal-body" style="text-align: center">
						<div class="col-sm-12">
							<div class="row">
								<div class="form-group; col-sm-4" style="width: 100%">
									<label>MODULO <span class="require">*</span></label>
									<select class="form-control" style="width: 100%;"
									tabindex="-1" aria-hidden="true" id="mod_IdModulo" name="mod_IdModulo" required></select>
								</div>	
								<br> 
								<div class="form-group; col-sm-3" style="width: 100%">
									<label>FECHA INICIAL<span class="require"></span></label>
									<input type="date" class="form-control" placeholder="Ingrese Fecha" id="txtFechaInicio"  name="txtFechaInicio" required>
									<!--<input type="text" class="form-control date-picker" id="mod_FechaInicial" name="mod_FechaInicial" placeholder="Selecciona Fecha Inicial"  required>-->
									<input type="hidden" id="funcion" name="funcion" value="1">
								</div>

								<div class="form-group; col-sm-3" style="width: 100%">
									<label>FECHA FINAL<span class="require"></span></label>
									<input type="date" class="form-control" placeholder="Ingrese Fecha" id="txtFechaFinal"  name="txtFechaFinal" required>

								</div>
							</div>	
						</div>
					</div>

					<div class="modal-footer" style="text-align: center" id="modal_footer">
						<button type="submit" class="btn btn-success btn-pill" id="btnGenerarInforme">
						EXPORTAR EXCEL
						</button>
					</div>
				</form>
			</div>
		
	

			<!-- Simple Datatable start -->
			<div class="card-box mb-30">
				<div class="modal-header">
					<h5 class="modal-title" id="exampleModalFormTitle">INFORME EVENTO / ACTIVIDADES</h5>
				</div>

				<form id="formGenerarInforme" method="post" action="../business/controller/class.informesEventos.php">
					<div class="modal-body" style="text-align: center">
						<div class="col-sm-12">
							<div class="row">
								<div class="form-group; col-sm-4" style="width: 100%">
									<label>MODULO <span class="require">*</span></label>
									<select class="form-control" style="width: 100%;"
									tabindex="-1" aria-hidden="true" id="mod_IdModuloFinanciero" name="mod_IdModuloFinanciero" required></select>
								</div>	
								<br> 
								<div  id="fechaInicial" class="form-group; col-sm-3" style="width: 100%">
									<label> EVENTOS <span class="require">*</span></label>									
									<select id="id_Eventos" name="id_Eventos" class="form-control" style="width: 100%;"
									tabindex="-1" aria-hidden="true" required>
									</select>
									<input type="hidden" id="funcion" name="funcion" value="1">
								</div>									
							</div>	
						</div>
					</div>

					<div class="modal-footer" style="text-align: center" id="modal_footer">
						<button type="submit" class="btn btn-success btn-pill" id="btnGenerarInforme">
						EXPORTAR EXCEL
						</button>
					</div>
				</form>
			</div>

				<!-- Simple Datatable start -->

				<div class="card-box mb-30">
				<div class="modal-header">
					<h5 class="modal-title" id="exampleModalFormTitle">INFORME POR ACTIVIDAD</h5>
				</div>

				<form id="formGenerarInforme" method="post" action="../business/controller/class.informesEventos.php">
					<div class="modal-body" style="text-align: center">
						<div class="col-sm-12">
							<div class="row">
								<div class="form-group; col-sm-4" style="width: 100%">
									<label>MODULO <span class="require">*</span></label>
									<select class="form-control" style="width: 100%;"
									tabindex="-1" aria-hidden="true" id="mod_IdActividad" name="mod_IdActividad" required></select>
								</div>	
								<br> 

								<div class="form-group; col-sm-3" style="width: 100%">
									<label> EVENTOS <span class="require">*</span></label>									
									<select id="id_EventosAct" name="id_EventosAct" class="form-control" style="width: 100%;"
									tabindex="-1" aria-hidden="true"  onchange="nota.actividadesEventos()" required>
									</select>
									<input type="hidden" id="funcion" name="funcion" value="1">
								</div>

								<div class="form-group; col-sm-3" style="width: 100%">
									<label> ACTIVIDADES <span class="require">*</span></label>									
									<select id="id_ActEvento" name="id_ActEvento" class="form-control" style="width: 100%;"
									tabindex="-1" aria-hidden="true" required>
									</select>
									<input type="hidden" id="funcion" name="funcion" value="1">
								</div>

							</div>	
						</div>
					</div>

					<div class="modal-footer" style="text-align: center" id="modal_footer">
						<button type="submit" class="btn btn-success btn-pill" id="btnGenerarInforme">
						EXPORTAR EXCEL
						</button>
					</div>
				</form>
			</div>

			<!-- INFOREM P Y G  -->
			<div class="card-box mb-30">
				<div class="modal-header">
					<h5 class="modal-title" id="exampleModalFormTitle">INFORME POR EVENTO - P Y G</h5>
				</div>

				<form id="formGenerarInforme" method="post" action="../business/controller/class.informesEventos.php">
					<div class="modal-body" style="text-align: center">
						<div class="col-sm-12">
							<div class="row">
								<div class="form-group; col-sm-4" style="width: 100%">
									<label>MODULO <span class="require">*</span></label>
									<select class="form-control" style="width: 100%;"
									tabindex="-1" aria-hidden="true" id="mod_IdModuloEventoPyG" name="mod_IdModuloEventoPyG" required></select>
								</div>	
								<br> 
								<div class="form-group; col-sm-3" style="width: 100%">
									<label> EVENTOS <span class="require">*</span></label>									
									<select id="id_EventosPyg" name="id_EventosPyg" class="form-control" style="width: 100%;"
									tabindex="-1" aria-hidden="true" required>
									</select>
									<input type="hidden" id="funcion" name="funcion" value="1">
								</div>									
							</div>	
						</div>
					</div>

					<div class="modal-footer" style="text-align: center" id="modal_footer">
						<button type="submit" class="btn btn-success btn-pill" id="btnGenerarInforme">
						EXPORTAR EXCEL
						</button>
					</div>
				</form>
			</div>

		


			<div class="card-box mb-30">
				<div class="modal-header">
					<h5 class="modal-title" id="exampleModalFormTitle"></h5>
				</div>

				
			</div>
		</div>
	
		<?php #require_once 'footer.php'?>
 
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
		<script src="../core/informesEventos.js"></script>
		<script src="../core/xlsx.mini.js"></script>
	  
	  </script>

		<!-- <script src="../core/Permisos.js"></script> -->
	</div>	
</body>
</html>