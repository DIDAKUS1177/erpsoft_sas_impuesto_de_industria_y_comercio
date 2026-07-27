<?php
    require_once '../business/globals.php';
    include_once('../business/class.sessions.php');
?>
<!DOCTYPE html>
<html>
<head>
	<!-- Basic Page Info -->
	<meta charset="utf-8">
	<title>Actividades Comercio |ERPSOFTSAS </title>

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
					<h4 class="h4">Listado de Actividades Comercio</h4>
					<button type="button" class="btn btn-outline-success" onclick="actividadesComercio.crearActividadesComercio()"><span class="ti-plus"></span> Crear Acttividad </button>
				</div>
				<div class="pb-20">
				<table id="actividadesComercioRegistrados" class="data-table table stripe hover nowrap">
						<thead>
							<tr>
                                <th>Codigo</th>	
                                <th>Nombre Actividad</th>
								<th>Tarifa</th>
								<th>Grupo Tarifa</th>
								<th>Acciones</th>
							</tr>
						</thead>
						<tbody id="bodyActividadesComercioRegistrados">
						
						</tbody>
					</table>
				</div>
			</div>
		</div>
    

		<!--Modal dependencia-->
		<div class="modal fade" id="modal-ActividadesComercio" tabindex="-1" role="dialog" aria-labelledby="exampleModalFormTitle" aria-hidden="true">
			<div class="modal-dialog modal-lg" role="document">
				<div class="modal-content ">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalFormTitle">Actividades Comercio</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					
					<form id="formCrearActividadesComercio" class="" action="">
						<div class="modal-body">
							<div class="row container">
                                <!-- ind_PrimerNombre (varchar) -->
								<div class="col-sm-12 col-md-3">
                                    <div class="row">
                                        <div class="form-group" style="width: 95%">
                                            <label>* Año</label>
                                            <input type="number" class="form-control" 
                                                id="acc_Anio" name="acc_Anio" 
                                                placeholder="Año" 
                                                title="Ingrese Año"
												 min="1900" 
												max="9999"
                                                required>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-sm-12 col-md-3">
                                    <div class="row">
                                        <div class="form-group" style="width: 95%">
                                            <label>* Codigo</label>
                                            <input type="text" class="form-control" 
                                                id="acc_Codigo" name="acc_Codigo" 
                                                placeholder="Codigo" 
                                                title="Ingrese Codigo"
                                                required>
                                        </div>
                                    </div>
                                </div>

                                <!-- ind_SegundoNombre (varchar) -->
                                <div class="col-sm-12 col-md-6">
                                    <div class="row">
                                        <div class="form-group" style="width: 95%">
                                            <label>Nombre Actividad</label>
                                            <input type="text" class="form-control" 
                                                id="acc_Nombre" name="acc_Nombre" 
                                                placeholder="Nombre Actividad" 
                                                title="Ingrese Nombre Actividad">
                                        </div>
                                    </div>
                                </div>

                                <!-- ind_PrimerApellido (varchar) -->
                             <div class="col-sm-12 col-md-4">
								<div class="row">
									<div class="form-group" style="width: 95%">
										<label>* Tarifa</label>
										<input type="text"
											class="form-control"
											id="acc_Tarifa"
											name="acc_Tarifa"
											placeholder="0.000"
											title="Formato válido: 0.000"
											inputmode="decimal"
											pattern="^\d\.\d{3}$"
											required>
									</div>
								</div>
							</div>

                            
                                <!-- ind_IdTipoDocumento (integer) -->
                                <div class="col-sm-12 col-md-4">
                                    <div class="row">
                                        <div class="form-group" style="width: 95%">
                                            <label>* Grupo Tarifa</label>
                                            <select class="form-control" id="acc_GrupoTarifa" name="acc_GrupoTarifa" required>
                                                <option value="1">Comercial</option>
                                                <option value="2">Servicio Financiero</option>
                                                <option value="3">Industrial</option>
                                                <option value="4">Otros</option>
												<option value="5">Servicios</option>
                                                <!-- etc. -->
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- ind_NumeroIdentificacion (integer) -->
                                <div class="col-sm-12 col-md-4">
                                    <div class="row">
                                        <div class="form-group" style="width: 95%">
                                            <label>* Exento</label>
                                                <select class="form-control" id="acc_Exento" name="acc_Exento" required>
                                                    <option value="0">NO</option>    
                                                    <option value="1">SI</option>
                                                </select>
                                        </div>
                                    </div>
                                </div>

							</div>
						</div>

						<div class="modal-footer" id="modal_footer">
							<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal"><span class="ti-close"></span>
								Cancelar
							</button>
							<button type="submit" class="btn btn-success btn-pill" id="btnCrearActividadesComercio">
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
		<script src="../core/actividadesComercio.js?v=<?php echo time(); ?>"></script>
		<!-- <script src="../core/Permisos.js"></script> -->
	</div>	
</body>
</html>