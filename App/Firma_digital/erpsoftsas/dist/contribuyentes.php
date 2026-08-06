<?php
    require_once '../business/globals.php';
    include_once('../business/class.sessions.php');
?>
<!DOCTYPE html>
<html>
<head>
	<!-- Basic Page Info -->
	<meta charset="utf-8">
	<title>Contribuyentes | ERPSOFTSAS </title>

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
					<h4 class="h4">Listado de Contribuyentes</h4>
					<button type="button" class="btn btn-outline-success" onclick="contribuyentes.crearContribuyentes()"><span class="ti-plus"></span> Crear Contribuyentes</button>
				</div>
				<div class="pb-20">
				<table id="contribuyentesRegistrados" class="data-table table stripe hover nowrap">
						<thead>
							<tr>
							  <th># Identificación</th>	
                <th>Nombre</th>
								<th>Apellido</th>
								<th>Dirección</th>
								<th>Acciones</th>
							</tr>
						</thead>
						<tbody id="bodyContribuyentesRegistrados">
						
						</tbody>
					</table>
				</div>
			</div>
		</div>
    

		<!--Modal dependencia-->
		<div class="modal fade" id="modal-Contribuyentes" tabindex="-1" role="dialog" aria-labelledby="exampleModalFormTitle" aria-hidden="true">
			<div class="modal-dialog modal-lg" role="document">
				<div class="modal-content ">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalFormTitle">Contribuyentes</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					
					<form id="formCrearContribuyentes" class="" action="">
						<div class="modal-body">
							<div class="row container">


                             <!-- ind_IdTipoDocumento (integer) -->
                <div class="col-sm-12 col-md-4" id="grupoTipoDocumento">
                    <div class="row">
                        <div class="form-group" style="width: 95%">
                            <label>* Tipo de Documento</label>
                            <select class="form-control" id="ind_IdTipoDocumento" name="ind_IdTipoDocumento" required>
                                <option value="">Seleccione...</option>
                                <!-- Aquí debes colocar las opciones válidas para tu sistema -->
                                <option value="1">Cédula de Ciudadanía</option>
                                <option value="5">NIT</option>
                                <!--<option value="2">Tarjeta de Identidad</option>-->
                                <option value="3">Cédula de Extranjería</option>
                                <option value="4">Pasaporte</option>
                                <!-- etc. -->
                            </select>
                        </div>
                    </div>
                </div>

                <!-- ind_NumeroIdentificacion (integer) -->
                  <div class="col-sm-12 col-md-6">
                    <div class="row">
                        <div class="form-group" style="width: 95%">
                            <label>* Numero  Identificacion</label>
                            <input type="number" class="form-control" 
                                  id="ind_NumeroIdentificacion" name="ind_NumeroIdentificacion" 
                                  placeholder="Numero de Identificacion" 
                                  title="Ingrese Numero de Identificacion"
                                  required>
                        </div>
                    </div>
                </div>

                <!-- ind_DV (integer) -->
                <div class="col-sm-12 col-md-2" id="grupoDV">
                    <div class="row">
                        <div class="form-group" style="width: 95%">
                            <label>* DV</label>
                            <input type="number" class="form-control" 
                                  id="ind_DV" name="ind_DV" 
                                  placeholder="DV" 
                                  title="DV"
                                  required>
                        </div>
                    </div>
                </div>

                <!-- ind_PrimerNombre (varchar) -->
                <div class="col-sm-12 col-md-6" id="grupoPrimerNombre">
                    <div class="row">
                        <div class="form-group" style="width: 95%">
                            <label>* Primer Nombre</label>
                            <input type="text" class="form-control" 
                                  id="ind_PrimerNombre" name="ind_PrimerNombre" 
                                  placeholder="Primer Nombre" 
                                  title="Ingrese Primer Nombre"
                                  required>
                        </div>
                    </div>
                </div>

                <!-- ind_SegundoNombre (varchar) -->
                <div class="col-sm-12 col-md-6">
                    <div class="row">
                        <div class="form-group" style="width: 95%">
                            <label>Segundo Nombre</label>
                            <input type="text" class="form-control" 
                                  id="ind_SegundoNombre" name="ind_SegundoNombre" 
                                  placeholder="Segundo Nombre" 
                                  title="Ingrese Segundo Nombre">
                        </div>
                    </div>
                </div>

                <!-- ind_PrimerApellido (varchar) -->
                <div class="col-sm-12 col-md-6" id="grupoPrimerApellido">
                    <div class="row">
                        <div class="form-group" style="width: 95%">
                            <label>* Primer Apellido</label>
                            <input type="text" class="form-control" 
                                  id="ind_PrimerApellido" name="ind_PrimerApellido" 
                                  placeholder="Primer Apellido" 
                                  title="Ingrese Primer Apellido"
                                  required>
                        </div>
                    </div>
                </div>

                <!-- ind_SegundoApellido (varchar) -->
                <div class="col-sm-12 col-md-6">
                    <div class="row">
                        <div class="form-group" style="width: 95%">
                            <label>Segundo Apellido</label>
                            <input type="text" class="form-control" 
                                  id="ind_SegundoApellido" name="ind_SegundoApellido" 
                                  placeholder="Segundo Apellido" 
                                  title="Ingrese SegundoApellido">
                        </div>
                    </div>
                </div>

               




                <!-- ind_Direccion (varchar) -->
                <div class="col-sm-12 col-md-6">
                    <div class="row">
                        <div class="form-group" style="width: 95%">
                            <label>* Direccion de Residencia</label>
                            <input type="text" class="form-control" 
                                  id="ind_Direccion" name="ind_Direccion" 
                                  placeholder="Direccion de Residencia" 
                                  title="Ingrese Direccion de Residencia"
                                  required>
                        </div>
                    </div>
                </div>

                <!-- ind_IdCiudad (integer) -->
                <div class="col-sm-12 col-md-3">
                    <div class="row">
                        <div class="form-group" style="width: 95%">
                            <label>* Ciudad Residencia</label>
                             <select class="form-control select2"
                                    id="ind_IdCiudad"
                                    name="ind_IdCiudad"
                                    style="width:100%"
                                    required>
                                <option value="">Seleccione ciudad...</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- ind_Persona (integer) -->
                <div class="col-sm-12 col-md-3">
                    <div class="row">
                        <div class="form-group" style="width: 95%">
                            <label>* Persona</label>
                            <select class="form-control" id="ind_Persona" name="ind_Persona" required>
                              <option value="">Seleccione...</option>
                              <option value="1">Natural</option>
                              <option value="2">Jurídica</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- ind_IdRegimen (integer) -->
                <div class="col-sm-12 col-md-3">
                    <div class="row">
                        <div class="form-group" style="width: 95%">
                            <label>Regimen</label>
                            <select class="form-control" id="ind_IdRegimen" name="ind_IdRegimen" required>
                                <option value="1">Responsable de IVA</option>
                                <option value="2">No Responsable de IVA</option>
                                <option value="3">Autoretenedor</option>
                                <option value="4">Régimen Simple de Tributación (RST)</option>
                                <option value="5">Régimen Tributario Especial (RTE)</option>
                                <option value="6">Otro</option>
                            </select>

                        </div>
                    </div>
                </div>

                <!-- ind_Telefono (integer) -->
                <div class="col-sm-12 col-md-3">
                    <div class="row">
                        <div class="form-group" style="width: 95%">
                            <label>Telefono</label>
                            <input type="number" class="form-control" 
                                  id="ind_Telefono" name="ind_Telefono" 
                                  placeholder="Telefono" 
                                  title="Ingrese Telefono">
                        </div>
                    </div>
                </div>

                <!-- ind_Email (varchar) -->
                <div class="col-sm-12 col-md-6">
                    <div class="row">
                        <div class="form-group" style="width: 95%">
                            <label>Correo Electronico</label>
                            <input type="email" class="form-control" 
                                  id="ind_Email" name="ind_Email" 
                                  placeholder="Email" 
                                  title="Ingrese Email">
                        </div>
                    </div>
                </div>

							</div>
						</div>

						<div class="modal-footer" id="modal_footer">
							<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal"><span class="ti-close"></span>
								Cancelar
							</button>
							<button type="submit" class="btn btn-success btn-pill" id="btnCrearContribuyentes">
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
		<script src="../core/contribuyentes.js?v=<?php echo time(); ?>"></script>

        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
		<!-- <script src="../core/Permisos.js"></script> -->
	</div>	
</body>
</html>