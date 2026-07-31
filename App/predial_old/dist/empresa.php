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
	<title>Empresa | DS-POS</title>

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
					<h4 class="h4">Configuración Empresa</h4>
					<!--<button type="button" class="btn btn-outline-success" onclick="prod.crearEmpresa()"><span class="ti-plus"></span> Crear Empresa</button>-->
				</div>
				<div class="pb-20">
					<div>
						<h5><center>DATOS INICIALES EMPRESA</center></h5>
					</div>

					<form id="formCrearEmpresa" class="" action="">
						<div class="modal-body">
							<div class="row container">
								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*Nombre</label>
											<input type="text" class="form-control" id="emp_Nombre" name="emp_Nombre" required>
										</div>	
									</div>	
								</div>
								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*Nombre Comercial</label>
											<input type="text" class="form-control" id="emp_NombreComercial" name="emp_NombreComercial" required>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-3" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*NIT</label>
											<input type="text" class="form-control" id="emp_Nit" name="emp_Nit" required>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-3" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*Departamento</label>
											<select class="form-control" style="width: 100%;"
												tabindex="-1" aria-hidden="true" id="emp_IdDepartamento" name="emp_IdDepartamento"  
												onclick = "prod.getIdMunicipios()"  required>
											</select>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-3" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*Ciudad</label>
											<select class="form-control" style="width: 100%;"
												tabindex="-1" aria-hidden="true" id="emp_IdMunicipio" name="emp_IdMunicipio" required>
											</select>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-3" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*Email</label>
											<input type="email" class="form-control" id="emp_Email" name="emp_Email" required>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-4" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*Sitio Web</label>
											<input type="text" class="form-control" id="emp_SitioWeb" name="emp_SitioWeb" required>
										</div>	
									</div>	
								</div>
								
								<div class="col-sm-12 col-md-4" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*Tipo Impresora</label>
											<select class="form-control" style="width: 100%;" id="emp_TipoImpresora" name="emp_TipoImpresora" required>
												<option value="1">80 mm</option>
												<option value="2">58 mm</option>
											</select>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-4" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*Tipo pantalla para Facturación</label>
											<select class="form-control" style="width: 100%;" id="emp_TipoPantalla" name="emp_TipoPantalla" required>
												<option value="1">Tipo Negocio</option>	
												<option value="2">Tipo Celular</option>
												<!-- <option value="3">Tipo Tactil</option> -->
											</select>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-4" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*Texto a Mostrar en Factura</label>
											<input type="text" class="form-control" id="emp_TextoFactura" name="emp_TextoFactura" required>
										</div>	
									</div>	
								</div>


								<div class="col-sm-12 col-md-4">
									<div class="row" >
										<div class="form-group" style="width: 99%">
											<label class="control-label" id="cargarSoporte">Cargar Logo Factura (848px x 714 px)</label>
												<input class="form-control" type="file" id="imageSoporte" name="imageSoporte"/>
										</div>
									</div>
								</div>

								<div class="col-sm-12 col-md-4">
									<div class="row" >
										<div class="form-group" style="width: 99%">
											<p><center><img name="imagenCargar" id="imagenCargar" width="50%" height="50%"></p></center>
										</div>
									</div>
								</div>

							</div>

						</div>
						<div class="modal-footer" id="modal_footer_Empresa">
						</div>
					</form>

					<div><center>
						<h5>SEDE EMPRESA</h5>
					</center></div>

<!--     EN DESARROLLO BOTONES DE CREACIÓN DE SEDES Y CAJAS
					<div class="pd-20 d-flex justify-content-between">
						<h4 class="h4">Listado de Sedes</h4>
						<button type="button" class="btn btn-outline-success" onclick="cierreCaja.crearCierreCaja()"><span class="ti-plus"></span> Crear Sedes</button>
						<button type="button" class="btn btn-outline-success" onclick="cierreCaja.crearCierreCaja()"><span class="ti-plus"></span> Crear Cajas</button>
					</div>
-->
					<table id="empresasRegistrados" class="data-table table stripe hover nowrap">
						<thead>
							<tr>
								<th>Nombre</th>
								<th>Dirección</th>
								<th>Ciudad</th>
								<th>Puntos de Venta (Cajas)</th>
							</tr>
						</thead>
						<tbody id="bodyEmpresasRegistrados">
						
						</tbody>
					</table>

				</div>
			</div>
		</div>
	
		<?php #require_once 'footer.php'?>
    
		<!--Modal Empresa-->
		<div class="modal fade" id="modal-SedeEmpresa" tabindex="-1" role="dialog" aria-labelledby="exampleModalFormTitle" aria-hidden="true">
			<div class="modal-dialog modal-lg" role="document">
				<div class="modal-content ">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalFormTitle">Empresa</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					
					<form id="formCrearSedeEmpresa" class="" action="">
						<div class="modal-body">
							<div class="row container">
							<div class="col-sm-12 col-md-4" style="margin-top: 1%">
									<div class="row" style="display: none">
										<div class="form-group" style="width: 95%">
											<label>*EMPRESA</label>
											<select class="form-control" style="width: 100%;"
												tabindex="-1" aria-hidden="true" id="seem_IdEmpresa" name="seem_IdEmpresa" required>
											</select>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-4" style="margin-top: 1%">
									<div class="row" style="display: none">
										<div class="form-group" style="width: 95%">
											<label>*BODEGA</label>
											<select class="form-control" style="width: 100%;"
												tabindex="-1" aria-hidden="true" id="seem_IdBodega" name="seem_IdBodega" required>
											</select>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*Nombre</label>
											<input type="text" class="form-control" id="seem_Nombre" name="seem_Nombre" required>
										</div>	
									</div>	
								</div>
								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*Telefono</label>
											<input type="text" class="form-control" id="seem_Telefono" name="seem_Telefono" required>
										</div>	
									</div>	
								</div>


								<div class="col-sm-12 col-md-4" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*Dirección</label>
											<input type="text" class="form-control" id="seem_Direccion" name="seem_Direccion" required>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-4" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*Departamento</label>
											<select class="form-control" style="width: 100%;"
												tabindex="-1" aria-hidden="true" id="seem_IdDepartamento" name="seem_IdDepartamento"
												onclick = "prod.getIdMunicipiosSedes()" required>
											</select>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-4" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*Ciudad</label>
											<select class="form-control" style="width: 100%;"
												tabindex="-1" aria-hidden="true" id="seem_IdMunicipio" name="seem_IdMunicipio" required>
											</select>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*Email</label>
											<input type="text" class="form-control" id="seem_Email" name="seem_Email" required>
										</div>	
									</div>	
								</div>
								
							</div>

						</div>
						<div class="modal-footer" id="modal_footer_SedeEmpresa">
						</div>
					</form>
					
				</div>
			</div>
		</div>
			
		
		<!--Modal Crear/Actualizar Cajas-->
		<div class="modal fade" id="modal-CrearCajas" tabindex="-1" role="dialog" aria-labelledby="exampleModalFormTitle" aria-hidden="true">
			<div class="modal-dialog modal-sm" role="document">
				<div class="modal-content ">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalFormTitle">Ver Puntos de Venta (Cajas)</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					
					<form id="formCrearCrearCajas" class="" action="">
						<div class="modal-body">
							<div class="row container">

								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>Nombre Caja</label>
											<input type="text" class="form-control" id="seemca_NombreCrear" name="seemca_NombreCrear" required>
											
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-4" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*Resolucion POS</label>
											<select class="form-control" style="width: 100%;"
												tabindex="-1" aria-hidden="true" id="seemca_IdResolucionCrear" name="seemca_IdResolucionCrear"
												onclick = "prod.getIdResolucion()" required>
											</select>
										</div>	
									</div>	
								</div>

								
								<div class="col-sm-12 col-md-4" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*Resolucion Remisión</label>
											<select class="form-control" style="width: 100%;"
												tabindex="-1" aria-hidden="true" id="seemca_IdResolucionRemiCrear" name="seemca_IdResolucionRemiCrear"
												onclick = "prod.getIdResolucionRemi()" required>
											</select>
										</div>	
									</div>	
								</div>

							</div>
						</div>
						<div class="modal-footer" id="modal_footer_CrearCajas">
						</div>
					</form>
					
				</div>
			</div>
		</div>			



		<!--Modal Precios Venta-->
		<div class="modal fade" id="modal-VerCajas" tabindex="-1" role="dialog" aria-labelledby="exampleModalFormTitle" aria-hidden="true">
			<div class="modal-dialog modal-lg" role="document">
				<div class="modal-content ">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalFormTitle">Ver Puntos de Venta (Cajas)</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					
					<form id="formCrearVerCajas" class="" action="">
						<div class="modal-body">
							<div class="row container">

								<table id="VerCajasRegistrados" class="data-table-VerCajas table stripe hover nowrap table-responsive">
									<thead>
										<tr>
											<th>Nombre</th>
											<th>N° Resoluciones </th>
											<!--<th>N° Resolucion REMISIÓN </th>
											<th>Fecha de Creación</th>-->
											<th>Editar Caja</th>
										</tr>
									</thead>
									<tbody id="bodyVerCajasRegistrados">
									
									</tbody>
								</table>

							</div>

							
						</div>
						<div class="modal-footer" id="modal_footer_VerCajas">
						</div>
					</form>

					<div>
						<h5><center>Editar PUNTO DE VENTA (CAJA)</center></h5>
					</div>

					<form id="formCrearEditarCajas" class="" action="">
						<div class="modal-body">
							<div class="row container">

								<div class="col-sm-12 col-md-4" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>Nombre Caja</label>
											<input type="text" class="form-control" id="seemca_Nombre" name="seemca_Nombre" required>
											<input type="hidden" class="form-control" id="seemca_IdSedeEmpresa" name="seemca_IdSedeEmpresa">
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-4" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*SERIAL EQUIPO</label>
											<input type="text" class="form-control" id="seemca_Serial" name="seemca_Serial" required>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-4" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*CLAVE ELIMINAR PRODUCTOS</label>
											<input type="text" class="form-control" id="seemca_CodigoCaja" name="seemca_CodigoCaja" required>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*Resolucion POS</label>
											<select class="form-control" style="width: 100%;"
												tabindex="-1" aria-hidden="true" id="seemca_IdResolucion" name="seemca_IdResolucion" required>
											</select>
										</div>	
									</div>	
								</div>

								
								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*Resolucion Remisión</label>
											<select class="form-control" style="width: 100%;"
												tabindex="-1" aria-hidden="true" id="seemca_IdResolucionRemi" name="seemca_IdResolucionRemi" required>
											</select>
										</div>	
									</div>	
								</div>

							</div>
						</div>
						<div class="modal-footer" id="modal_footer_EditarCajas">
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
		<script src="../core/empresa.js"></script>
		<!-- <script src="../core/Permisos.js"></script> -->
	</div>	
</body>
</html>