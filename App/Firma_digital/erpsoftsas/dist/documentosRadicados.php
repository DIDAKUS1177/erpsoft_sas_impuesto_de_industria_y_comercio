<?php
    require_once '../business/globals.php';
    include_once('../business/class.sessions.php');
?>
<!DOCTYPE html>
<html>
<head>
	<!-- Basic Page Info -->
	<meta charset="utf-8">
	<title>Documentos Radicados |Gestor Documental </title>

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
					<h4 class="h4">Listado de Radicados</h4>
					<input  type="text" style="width: 250px; text-align: center" name="daterange" id="daterange" />
					<!--<button type="button" class="btn btn-outline-success" onclick="documentosRadicados.crearDocumentosRadicados()"><span class="ti-plus"></span> Crear Serie Documental</button>-->
				</div>
				<div class="pb-20">
				<table id="documentosRadicadosRegistrados" class="data-table table stripe hover nowrap">
						<thead>
							<tr>
								<th>Fecha Creación</th>
								<th>N° Radicado</th>
								<th>Tipo Documento</th>
								<th>Dependencia</th>
								<th>Prioridad</th>
								<th>Estado</th>
								<th>Acciones</th>
							</tr>
						</thead>
						<tbody id="bodyDocumentosRadicadosRegistrados">
						
						</tbody>
					</table>
				</div>
			</div>
		</div>
    

		<!--Modal documentosRadicados-->
		<div class="modal fade" id="modal-RadicadoActualizar" tabindex="-1" role="dialog" aria-labelledby="exampleModalFormTitle" aria-hidden="true">
			<div class="modal-dialog modal-xl" role="document">
				<div class="modal-content ">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalFormTitle" name="exampleModalFormTitle" ></h5>
						
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					
					<form id="formCrearRadicadoActualizar" class="" action="">
						<div class="modal-body">
							<div class="row container">

							<div class="col-sm-12 col-md-3" style="margin-top: 1%">
								<div class="row">
									<div class="form-group" style="width: 95%">
										<label>*Tipo de Petición</label>
										<select class="form-control" style="width: 100%;" 
											tabindex="-1" aria-hidden="true" id="pe_IdTipoPeticion" name="pe_IdTipoPeticion" required>
										</select>
										<!-- Campo duplicado oculto -->
										<select class="form-control" style="display:none;" id="pe_IdTipoPeticionOriginal" name="pe_IdTipoPeticionOriginal"></select>
									</div>    
								</div>    
							</div>

							<div class="col-sm-12 col-md-6" style="margin-top: 1%">
								<div class="row">
									<div class="form-group" style="width: 95%">
										<label>*Nombre Completo o Razon Social</label>
										<input type="text" class="form-control" id="pe_NombreCompleto" name="pe_NombreCompleto" 
										title='Ingrese Nombre Completo o Razon Social' placeholder="Nombre o Razon Social" required>
										<!-- Campo duplicado oculto -->
										<input type="text" class="form-control" style="display:none;" id="pe_NombreCompletoOriginal" name="pe_NombreCompletoOriginal">
									</div>    
								</div>    
							</div>

							<div class="col-sm-12 col-md-3" style="margin-top: 1%">
								<div class="row">
									<div class="form-group" style="width: 95%">
										<label>*Numero de Identificación</label>
										<input type="number" class="form-control" id="pe_NumeroIdentificacion" name="pe_NumeroIdentificacion"
										title='Ingrese Numero de Identificación' placeholder="Numero de Identificación" required>
										<!-- Campo duplicado oculto -->
										<input type="number" class="form-control" style="display:none;" id="pe_NumeroIdentificacionOriginal" name="pe_NumeroIdentificacionOriginal">
									</div>    
								</div>    
							</div>

							<div class="col-sm-12 col-md-3" style="margin-top: 1%">
								<div class="row">
									<div class="form-group" style="width: 95%">
										<label>*Dirección</label>
										<input type="text" class="form-control" id="pe_Direccion" name="pe_Direccion"
										title='Ingrese Dirección' placeholder="Dirección" required>
										<!-- Campo duplicado oculto -->
										<input type="text" class="form-control" style="display:none;" id="pe_DireccionOriginal" name="pe_DireccionOriginal">
									</div>    
								</div>    
							</div>

							<div class="col-sm-12 col-md-3" style="margin-top: 1%">
								<div class="row">
									<div class="form-group" style="width: 95%">
										<label>*Telefono</label>
										<input type="number" class="form-control" id="pe_Telefono" name="pe_Telefono" 
										title='Ingrese Telefono' placeholder="Telefono" required>
										<!-- Campo duplicado oculto -->
										<input type="number" class="form-control" style="display:none;" id="pe_TelefonoOriginal" name="pe_TelefonoOriginal">
									</div>    
								</div>    
							</div>

							<div class="col-sm-12 col-md-6" style="margin-top: 1%">
								<div class="row">
									<div class="form-group" style="width: 95%">
										<label>Correo Electronico</label>
										<input type="email" class="form-control" id="pe_CorreoElectronico" name="pe_CorreoElectronico"
										title='Ingrese Correo Electronico' placeholder="Correo Electronico">
										<!-- Campo duplicado oculto -->
										<input type="email" class="form-control" style="display:none;" id="pe_CorreoElectronicoOriginal" name="pe_CorreoElectronicoOriginal">
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
										<!-- Campo duplicado oculto -->
										<select class="form-control" style="display:none;" id="pe_IdDependenciaOriginal" name="pe_IdDependenciaOriginal"></select>
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
										<!-- Campo duplicado oculto -->
										<select class="form-control" style="display:none;" id="pe_IdCategoriaOriginal" name="pe_IdCategoriaOriginal"></select>
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
										<!-- Campo duplicado oculto -->
										<select class="form-control" style="display:none;" id="pe_IdSubCategoriaOriginal" name="pe_IdSubCategoriaOriginal"></select>
									</div>    
								</div>    
							</div>

							<div class="col-sm-12 col-md-6">
								<div class="row" >
									<div class="form-group" style="width: 95%">
										<label>* Descripción</label>
										<textarea class="form-control" id="pe_Descripcion" name="pe_Descripcion" 
											rows="4" cols="50" title='Ingrese Descripción' placeholder="Descripción" required></textarea>
										<!-- Campo duplicado oculto -->
										<textarea class="form-control" style="display:none;" id="pe_DescripcionOriginal" name="pe_DescripcionOriginal"></textarea>
									</div>    
								</div>    
							</div>

							<div class="col-sm-12 col-md-6">
								<div class="row" >
									<div class="form-group" style="width: 95%">
										<label>Observaciónes</label>
										<textarea class="form-control" id="pe_Observaciones" name="pe_Observaciones" rows="4" cols="50"
										title='Ingrese Observaciónes Adicionales' placeholder="Observaciónes Adicionales"></textarea>
										<!-- Campo duplicado oculto -->
										<textarea class="form-control" style="display:none;" id="pe_ObservacionesOriginal" name="pe_ObservacionesOriginal"></textarea>
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
										<!-- Campo duplicado oculto -->
										<select class="form-control" style="display:none;" id="pe_PrioridadOriginal" name="pe_PrioridadOriginal">
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
										<!-- Campo duplicado oculto -->
										<select class="form-control" style="display:none;" id="pe_FormaRecepcionOriginal" name="pe_FormaRecepcionOriginal">
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
										<!-- Campo duplicado oculto -->
										<input type="number" class="form-control" style="display:none;" id="pe_NumeroFoliosOriginal" name="pe_NumeroFoliosOriginal">
									</div>    
								</div>    
							</div>

							<div class="col-sm-12 col-md-3">
									<div class="row" >
										<div class="form-group" style="width: 99%">
											<label> Anexos </label>
											<input type="file" id="doc_AnexosNew" name="doc_AnexosNew[]" accept="application/pdf" multiple>
										</div>
									</div>
								</div>

							<div class="col-sm-12 col-md-3" style="margin-top: 1%">
								<div class="row">
								<label>*Anexos</label>		
								<div class="form-group" id="pdfLinks" id="pdfLinks" style="width: 95%">
										
										
									</div>    
								</div>    
							</div>



							</div>
						</div>

						<div class="modal-footer" id="modal_footer">
							<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal"><span class="ti-close"></span>
								Cancelar
							</button>
							<button type="submit" class="btn btn-success btn-pill" id="btnCrearRadicadoActualizar">
								Actualizar
							</button>

						</div>
					</form>
					
				</div>
			</div>
		</div>


		<!--Modal GestionRadicados-->
		<div class="modal fade" id="modal-GestionRadicados" tabindex="-1" role="dialog" aria-labelledby="exampleModalFormTitle" aria-hidden="true">
			<div class="modal-dialog modal-lg" role="document">
				<div class="modal-content ">
					<div class="modal-header">
						<h5 class="modal-title" id="gestionRadicadoNombre" name="gestionRadicadoNombre" ></h5>
						
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					
					<form id="formCrearGestionRadicados" class="" action="">
						<div class="modal-body">
							<div class="row container">

								<div class="col-sm-12 col-md-4">
									<div class="row" >
										<div class="form-group" style="width: 95%">
											<label>Estado Actual</label>
											<h5 class="modal-title" id="tra_IdEstadoTipoPeticion" name="tra_IdEstadoTipoPeticion" ></h5>
											<!--<input type="text" class="form-control" id="tra_IdEstadoTipoPeticion" name="tra_IdEstadoTipoPeticion">-->
										</div>	
									</div>	
								</div>

								
								<div class="col-sm-12 col-md-8">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>*Estado a Actualizar</label>
											<select class="form-control" style="width: 100%;" 
												tabindex="-1" aria-hidden="true" id="tra_IdEstadoTipoPeticionNew" name="tra_IdEstadoTipoPeticionNew" required>
											</select>
										</div>    
									</div>    
								</div>

								<div class="col-sm-12 col-md-12">
									<div class="row" >
										<div class="form-group" style="width: 95%">
											<label>* Observaciónes</label>
											<textarea class="form-control" id="tra_Observaciones" name="tra_Observaciones" rows="4" cols="50" title='Ingrese Descripción' placeholder="Descripción" required></textarea>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-3">
									<div class="row" >
										<div class="form-group" style="width: 99%">
											<label>Anexos</label>
											<input type="file" id="doc_Anexos" name="doc_Anexos[]" accept="application/pdf" multiple>

											<!-- Campo duplicado oculto -->
											<select class="form-control" style="display:none;" id="pe_IdTipoPeticionOriginalCam" name="pe_IdTipoPeticionOriginalCam"></select>
											<select class="form-control" style="display:none;" id="pe_IdDependenciaOriginalCam" name="pe_IdDependenciaOriginalCam"></select>
											<select class="form-control" style="display:none;" id="pe_IdCategoriaOriginalCam" name="pe_IdCategoriaOriginalCam"></select>
											<select class="form-control" style="display:none;" id="pe_IdSubCategoriaOriginalCam" name="pe_IdSubCategoriaOriginalCam"></select>
										
										</div>
									</div>
								</div>

							</div>
						</div>

						<div class="modal-footer" id="modal_footer">
							<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal"><span class="ti-close"></span>
								Cancelar
							</button>
							<button type="submit" class="btn btn-success btn-pill" id="btnCrearGestionRadicados">
								Actualizar
							</button>

						</div>
					</form>
					
				</div>
			</div>
		</div>


		
		<!--Modal Trazabilidad de Radicados-->
		<div class="modal fade" id="modal-TrazabilidadRadicados"  role="dialog" aria-labelledby="exampleModalFormTitle" aria-hidden="true">
			<div class="modal-dialog modal-xl" role="document">
				<div class="modal-content ">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalFormTitle">Trazabilidad del Radicado</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					
					<div class="modal-body">
						<div class="modal-body">
						<!-- Encabezado de las pestañas -->
						<ul class="nav nav-tabs" id="myTab" role="tablist">
						<li class="nav-item" role="presentation">
							<a 
							class="nav-link active" 
							id="pestania1-tab" 
							data-toggle="tab" 
							href="#pestania1" 
							role="tab" 
							aria-controls="pestania1" 
							aria-selected="true">
							Trazabilidad
							</a>
						</li>
						<li class="nav-item" role="presentation">
							<a 
							class="nav-link" 
							id="pestania2-tab" 
							data-toggle="tab" 
							href="#pestania2" 
							role="tab" 
							aria-controls="pestania2" 
							aria-selected="false">
							Carpetas
							</a>
						</li>
						</ul>

						<!-- Contenido de cada pestaña -->
						<div class="tab-content" id="myTabContent">
						<!-- Primera pestaña -->
						<div 
							class="tab-pane fade show active" 
							id="pestania1" 
							role="tabpanel" 
							aria-labelledby="pestania1-tab">
							<div class="col-sm-12 mt-3">
							<table id="ltsTrazabilidadRadicados" class="table table-responsive hover nowrap">
								<thead>
								<tr>
									<th>Fecha</th>
									<th>Responsable</th>
									<th>Cambios</th>
									<th>Observaciones</th>
									<th>Usuario</th>
								</tr>
								</thead>
								<tbody id="bodyTrazabilidadRadicados">
								<!-- Aquí van tus filas -->
								</tbody>
							</table>
							</div>
						</div>

						<!-- Segunda pestaña -->
						<div 
							class="tab-pane fade" 
							id="pestania2" 
							role="tabpanel" 
							aria-labelledby="pestania2-tab">
							<div class="col-sm-12 mt-3" id="pdfTotales">
							<h5>Contenido de la segunda pestaña</h5>
							<!-- Aquí pones lo que quieras mostrar en la segunda pestaña -->
							</div>
						</div>
					</div>
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

		<!-- Enlaces para cargar Filtro de Fechas -->
		<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
		<script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
		<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
		<style>
        .text-wrap {
            white-space: normal;
            word-wrap: break-word; /* o word-break: break-word; */
        }
    </style>
		<script src="../core/documentosRadicados.js?v=<?php echo time(); ?>"></script>
	</div>	
</body>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const pdfDiv = document.getElementById("pdfTotales");
    pdfDiv.innerHTML = "Contenido cargado";
  });
</script>
</html>