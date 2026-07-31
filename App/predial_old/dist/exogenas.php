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
	<title>Exogenas | ERPSOFTSAS</title>

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

		<style>

			/* 1) Convertimos .header-right en contenedor posicionado */
			.header-right {
			position: relative;
			}

			/* 2) Insertamos el banner justo debajo del avatar/nombre, sin tocar el flujo */
			.header-right::after {
			content: '';
			position: absolute;
			top: 100%;        /* lo coloca inmediatamente debajo de .header-right */
			left: 0;
			right: 0;
			height: 80px;     /* ajusta esta altura a la de tu banner real */
			background: url('../extensiones/images/banner.png') no-repeat center center;
			background-size: cover;
			pointer-events: none;  /* que no interfiera con clics */
			z-index: 1;
			}

		</style>
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
					<h4 class="h4">Listado de Documentos de Exogenas</h4>					
						<!---<button type="button" class="btn btn-outline-success" onclick="bod.xlsrearProducto()">Subir Exogenas </button>-->
				</div>
				<div class="pb-20">
				<table id="tblBodega" class="data-table table stripe hover nowrap">
						<thead>
							<tr>
								<th>Formato</th>	
								<th>Nombre</th>
								<th>Docuemnto</th>
							</tr>
						</thead>
						<tbody id="tbodyBodega">
	
						</tbody>
					</table>
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

							<div class="form-group" style="width: 100%">
								<label>*Tipo</label>
								<select class="form-control" id="pro_IdTipo" placeholder="" name="pro_IdTipo" required>
										<option value="">Seleccione una opción</option>
										<option value="1">Productos</option>
								</select>
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


			<!--Modal Producto-->
			<div class="modal fade" id="modal-ProductoXml" tabindex="-1" role="dialog" aria-labelledby="exampleModalFormTitle" aria-hidden="true">
			<div class="modal-dialog modal-lg" role="document">
				<div class="modal-content ">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalFormTitle">Importar Exogenas</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					
					<form id="formCrearProductoXml" class="" action="">
						<div class="modal-body">
							<div class="row container">

								<div class="col-sm-12 col-md-8">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label id="kar_NumOrdenLabel">Formato a Cargar: <span class="require">*</span></label>
											<select class="form-control" style="width: 100%;" tabindex="-1" aria-hidden="true" 
												id="detkar_TipoDocumento" name="detkar_TipoDocumento" required>
											</select>
										</div>	
									</div>	
								</div>
								
								<div class="col-sm-12 col-md-3">
									<div class="row">
										<div class="form-group" style="width: 100%">
											<label id="kar_TipoPagoLabell">Año <span class="require">*</span></label>
											<select class="form-control" style="width: 95%;"
											tabindex="-1" aria-hidden="true" id="kar_AnioXml" name="kar_AnioXml" required>
												<option value="">Seleccione una opción</option>
												<option value="2024">2024</option>
										
											</select>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-12">
									<div class="row">
										<div class="form-group" style="width: 100%">
											<label>Observaciones</label>
											<input class="form-control" id="Kar_Observaciones" name="Kar_Observaciones" maxlength="500" 
												placeholder="Observaciones..."/>
										</div>	
									</div>	
								</div>

<!--
								<div class="col-sm-12 col-md-12" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
										<label>Puedes cargar hasta 500 productos por archivo:</label>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-12" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<a href="plantillas/plantilla_cargue_productos.xlsx" download="plantilla_cargue_productos.xlsx" style="margin-top: 10px; display: inline-block;">
                            				Descargar plantilla</a>
										</div>	
									</div>	
								</div>
-->

								<div class="col-sm-12 col-md-12" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>Cargar Archivo:</label>
											<input type="file" name="archivo_excel" id="archivo_excel"  accept=".xlsx, .xls, .csv" required>
										</div>	
									</div>	
								</div>
					
							</div>
						</div>
						<div class="modal-footer" id="modal_footer-xml">
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
		<script src="../core/exogenas.js?v=<?php echo time(); ?>"></script>
	

	</div>	
</body>
</html>