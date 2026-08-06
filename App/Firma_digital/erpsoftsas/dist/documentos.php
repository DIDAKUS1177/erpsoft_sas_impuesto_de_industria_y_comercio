<?php
    require_once '../business/globals.php';
    include_once('../business/class.sessions.php');
?>
<!DOCTYPE html>
<html>
<head>
	<!-- Basic Page Info -->
	<meta charset="utf-8">
	<title>Radicación | Gestor Documental</title>

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
	<meta name="viewport" content="width=device-width, initial-scale=1">

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
	
	<!-- Analitica retirada: la etiqueta era UA- (Universal Analytics),
	     apagada por Google en 2023, por lo que no recogia ningun dato. -->
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
					<h4 class="h4">Modulo de Radicación</h4>
				</div>
				<div class="pb-20">
					<div>
						<h5><center>DATOS INICIALES</center></h5>
					</div>

					<form id="formCrearDocumentos" class="" action="">
						<div class="modal-body">
							<div class="row container">

								<div class="col-sm-12 col-md-3" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*Tipo de Petición</label>
											<select class="form-control" style="width: 100%;" 
												tabindex="-1" aria-hidden="true" id="pe_IdTipoPeticion" name="pe_IdTipoPeticion" required>
											</select>
										</div>	
									</div>	
								</div>


								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*Nombre Completo o Razon Social</label>
											<input type="text" class="form-control" id="pe_NombreCompleto" name="pe_NombreCompleto" 
											title='Ingrese Nombre Completo o Razon Social' placeholder="Nombre o Razon Social" required>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-3" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*Numero de Identificación</label>
											<input type="number" class="form-control" id="pe_NumeroIdentificacion" name="pe_NumeroIdentificacion"
											title='Ingrese Numero de Identificación' placeholder="Numero de Identificación" required>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-3" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*Dirección</label>
											<input type="text" class="form-control" id="pe_Direccion" name="pe_Direccion"
											title='Ingrese Dirección' placeholder="Dirección" required>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-3" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*Telefono</label>
											<input type="number" class="form-control" id="pe_Telefono" name="pe_Telefono" 
											title='Ingrese Telefono' placeholder="Telefono" required>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>Correo Electronico</label>
											<input type="email" class="form-control" id="pe_CorreoElectronico" name="pe_CorreoElectronico"
											title='Ingrese Correo Electronico' placeholder="Correo Electronico">
										</div>	
									</div>	
								</div>
								

								<div class="col-sm-12 col-md-4" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*Dependencia</label>
											<select class="form-control" style="width: 100%;"
												tabindex="-1" aria-hidden="true" id="pe_IdDependencia" name="pe_IdDependencia"  required>
											</select>
										</div>	
									</div>	
								</div>

								
								<div class="col-sm-12 col-md-4" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*Serie Documental</label>
											<select class="form-control" style="width: 100%;"
												tabindex="-1" aria-hidden="true" id="pe_IdCategoria" name="pe_IdCategoria"
												onclick = "documentos.getIdSubSeriesDoc()" required>
											</select>
										</div>	
									</div>	
								</div>

								
								<div class="col-sm-12 col-md-4" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>Sub Serie Documental</label>
											<select class="form-control" style="width: 100%;"
												tabindex="-1" aria-hidden="true" id="pe_IdSubCategoria" name="pe_IdSubCategoria">
											</select>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-6">
									<div class="row" >
										<div class="form-group" style="width: 95%">
											<label>* Descripción</label>
											<textarea class="form-control" id="pe_Descripcion" name="pe_Descripcion" 
												rows="4" cols="50" title='Ingrese Descripción' placeholder="Descripción" required></textarea>
										</div>	
									</div>	
								</div>


								<div class="col-sm-12 col-md-6">
									<div class="row" >
										<div class="form-group" style="width: 95%">
											<label>Observaciónes</label>
											<textarea class="form-control" id="pe_Observaciones" name="pe_Observaciones" rows="4" cols="50"
											title='Ingrese Observaciónes Adicionales' placeholder="Observaciónes Adicionales"></textarea>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>Prioridad</label>
											<select class="form-control" style="width: 100%;"
												tabindex="-1" aria-hidden="true" id="pe_Prioridad" name="pe_Prioridad" required>
												<option value="2">Normal</option>
												<option value="1">Urgente</option>
												<option value="3">Baja</option>
											</select>
										</div>	
									</div>	
								</div>

								
								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*Forma de Recepción</label>
											<select class="form-control" style="width: 100%;"
												tabindex="-1" aria-hidden="true" id="pe_FormaRecepcion" name="pe_FormaRecepcion" required>
												<option value="1">Presencial</option>
												<option value="2">Virtual</option>
												<option value="3">Correo Postal</option>
											</select>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-3" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*Numero de Folios</label>
											<input type="number" class="form-control" id="pe_NumeroFolios" name="pe_NumeroFolios" 
											title='Ingrese Numero de Folios' placeholder="Numero de Folios" required>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-3">
									<div class="row" >
										<div class="form-group" style="width: 99%">
											<label> Anexos </label>
											<input type="file" id="doc_Anexos" name="doc_Anexos[]" accept="application/pdf" multiple>
										</div>
									</div>
								</div>

<!--
								<div class="col-sm-12 col-md-3">
									<div class="row" >
										<div class="form-group" style="width: 99%">
											<label class="control-label" id="cargarSoporte">Anexo #1</label>
												<input class="form-control" type="file" id="doc_Anexo1" name="doc_Anexo1"/>
										</div>
									</div>
								</div>

									
								<div class="col-sm-12 col-md-3">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>N° de Folios #1</label>
											<input type="number" class="form-control" id="pe_NumeroFolios" name="pe_NumeroFolios" 
											title='Ingrese Numero de Folios' placeholder="Numero de Folios" required>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-3">
									<div class="row" >
										<div class="form-group" style="width: 99%">
											<label class="control-label" id="cargarSoporte">Anexo #2</label>
												<input class="form-control" type="file" id="doc_Anexo2" name="doc_Anexo2"/>
										</div>
									</div>
								</div>

								<div class="col-sm-12 col-md-3">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>N° de Folios #2</label>
											<input type="number" class="form-control" id="pe_NumeroFolios" name="pe_NumeroFolios" 
											title='Ingrese Numero de Folios' placeholder="Numero de Folios" required>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-3">
									<div class="row" >
										<div class="form-group" style="width: 99%">
											<label class="control-label" id="cargarSoporte">Anexo #3</label>
												<input class="form-control" type="file" id="doc_Anexo3" name="doc_Anexo3"/>
										</div>
									</div>
								</div>

								<div class="col-sm-12 col-md-3">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>N° de Folios #3</label>
											<input type="number" class="form-control" id="pe_NumeroFolios" name="pe_NumeroFolios" 
											title='Ingrese Numero de Folios' placeholder="Numero de Folios" required>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-3">
									<div class="row" >
										<div class="form-group" style="width: 99%">
											<label class="control-label" id="cargarSoporte">Anexo #4</label>
												<input class="form-control" type="file" id="doc_Anexo4" name="doc_Anexo4"/>
										</div>
									</div>
								</div>

								<div class="col-sm-12 col-md-3">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>N° de Folios #4</label>
											<input type="number" class="form-control" id="pe_NumeroFolios" name="pe_NumeroFolios" 
											title='Ingrese Numero de Folios' placeholder="Numero de Folios" required>
										</div>	
									</div>	
								</div>
-->
							</div>

						</div>
						<div class="modal-footer" id="modal_footer_Documentos">
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
		<script src="../core/documentos.js?v=<?php echo time(); ?>"></script>
		<!-- <script src="../core/Permisos.js"></script> -->
	</div>	
</body>
</html>