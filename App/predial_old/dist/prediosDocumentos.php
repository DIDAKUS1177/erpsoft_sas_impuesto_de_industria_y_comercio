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
	<meta charset="utf-8">
	<title>ERPSOFTSAS</title>

	<meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="Expires" content="0">
    <meta http-equiv="Last-Modified" content="0">
    <meta http-equiv="Cache-Control" content="no-cache, mustrevalidate">
    <meta http-equiv="Pragma" content="no-cache">
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
	<meta http-equiv="Content-Type" content="Mime-Type; charset=UTF-8"/>

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
					
				</div>


				<form id="formCrearEventos" class="" action="" method='post'>
					<div class="modal-body">
						<div class="row container">
						<div class="col-sm-12 col-md-6">
						<h4 class="h3">Listado de Predios</h3>
							<br>
						<p>Seleccionar fecha de Generación de Documentos de Predios.</p>
						</div>


							<div class="col-sm-12 col-md-2">
								<div class="row" >
									<div class="form-group" style="width: 95%">
										<label>*Año</label>
											<select class="form-control" id="fechaAnio" name="fechaAnio" required>
											<?php
											if(isset($_POST["fechaAnio"])){
												$year = date('Y');
												for ($i = 2023; $i < $year+1; $i++) {
													if($_POST["fechaAnio"] == $i){
														echo'<option value="'.$i.'"selected>'.$i.'</option>';
													}else{
														echo'<option value="'.$i.'">'.$i.'</option>';
													}
												  }
											}else{
												$year = date('Y');
												for ($i = 2023; $i < $year+1; $i++) {
													echo'<option value="'.$i.'">'.$i.'</option>';
												  }
											}

											?> </select>
									</div>	
								</div>	
							</div>

							<div class="col-sm-12 col-md-2">
								<div class="row" >
									<div class="form-group" style="width: 95%">
										<label>*Mes</label>
											<select class="form-control" id="fechaMes" name="fechaMes" required>
											<?php
											if(isset($_POST["fechaMes"])){										
												for ($i = 1; $i < 13; $i++) {
													if($_POST["fechaMes"] == $i){
														echo'<option value="'.$i.'"selected>'.$i.'</option>';
													}else{
														echo'<option value="'.$i.'">'.$i.'</option>';
													}
												  }
											}else{
												for ($i = 1; $i < 13; $i++) {
													echo'<option value="'.$i.'">'.$i.'</option>';
												  }
											}
											?> </select>
									</div>	
								</div>	
							</div>
							
														<div class="col-sm-12 col-md-2">
								<div class="row" >
									<div class="form-group" style="width: 95%">
										<label>*Dia</label>
											<select class="form-control" id="fechaDia" name="fechaDia" required>
											<?php
											if(isset($_POST["fechaDia"])){										
												for ($i = 1; $i < 32; $i++) {
													if($_POST["fechaDia"] == $i){
														echo'<option value="'.$i.'"selected>'.$i.'</option>';
													}else{
														echo'<option value="'.$i.'">'.$i.'</option>';
													}
												  }
											}else{
												for ($i = 1; $i < 32; $i++) {
													echo'<option value="'.$i.'">'.$i.'</option>';
												  }
											}
											?> </select>
									</div>	
								</div>	
							</div>
							

						</div>
					</div>

					<div class="modal-footer" id="modal_footer">
						<button type="submit" class="btn btn-success btn-pill" id="btnCrearEventos">
							Actualizar
						</button>
					</div>
				</form>



				<div class="pb-20">
					<table id="notasRegistradas" class="data-table table stripe hover nowrap">
						<thead>
							<tr>
								<th>Codigo Predio</th>
								<th>Nombre Propietario</th>
								<th>Dirección</th>
								<th>Profesional</th>
								<th>CITACION A NOTIFICACION </br> PERSONAL COBRO COACTIVO</th>
								<th>MINUTA MANDAMIENTO </br> DE PAGO PREDIAL</th>
								
							</tr>
						</thead>
						<tbody id="bodyNotasRegistradas">
							
						</tbody>
					</table>
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
		<script src="../core/prediosDocumentos.js"></script>
		
	</div>	
</body>
</html>