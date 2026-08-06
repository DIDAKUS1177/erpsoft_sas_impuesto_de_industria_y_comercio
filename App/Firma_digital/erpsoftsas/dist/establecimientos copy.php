<?php
    require_once '../business/globals.php';
    include_once('../business/class.sessions.php');
?>
<!DOCTYPE html>
<html>
<head>
	<!-- Basic Page Info -->
	<meta charset="utf-8">
	<title>Establecimientos |ERPSOFTSAS </title>

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
					<h4 class="h4">Listado de Establecimientos</h4>
					<button type="button" class="btn btn-outline-success" onclick="establecimientos.crearEstablecimientos()"><span class="ti-plus"></span> Crear Establecimientos </button>
				</div>
				<div class="pb-20">
				<table id="establecimientosRegistrados" class="data-table table stripe hover nowrap">
						<thead>
							<tr>
                                <th>Establecimiento</th>
                                <th>Contribuyente</th>
                                <th># Documento</th>	
								<th>Dirección</th>								
								<th>Acciones</th>
							</tr>
						</thead>
						<tbody id="bodyEstablecimientosRegistrados">
						</tbody>
					</table>
				</div>
			</div>
		</div>
    

		<!--Modal dependencia-->
		<div class="modal fade" id="modal-Establecimientos" tabindex="-1" role="dialog" aria-labelledby="exampleModalFormTitle" aria-hidden="true">
			<div class="modal-dialog modal-xl" role="document">
				<div class="modal-content ">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalFormTitle">Establecimientos</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					
					<form id="formCrearEstablecimientos" action="">
                        <div class="modal-body">
                            <div class="row container">

                                <!-- Codigo -->
                                <div class="col-sm-12 col-md-2">
                                    <div class="form-group" style="width: 95%">
                                        <label>* Codigo</label>
                                        <input type="text" class="form-control" id="est_Codigo" name="est_Codigo" placeholder="Codigo" required>
                                    </div>
                                </div>

                                <!-- Nombre -->
                                <div class="col-sm-12 col-md-5">
                                    <div class="form-group" style="width: 95%">
                                        <label>Nombre</label>
                                        <input type="text" class="form-control" id="est_Nombre" name="est_Nombre" placeholder="Nombre">
                                    </div>
                                </div>

                                <!-- Dirección -->
                                <div class="col-sm-12 col-md-5">
                                    <div class="form-group" style="width: 95%">
                                        <label>Dirección</label>
                                        <input type="text" class="form-control" id="est_Direccion" name="est_Direccion" placeholder="Dirección">
                                    </div>
                                </div>

                                <!-- País -->
                                <div class="col-sm-12 col-md-3">
                                    <div class="form-group" style="width: 95%">
                                        <label>País</label>
                                        <select class="form-control" id="est_Pais" name="est_Pais">
                                            <option value="1">Colombia</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Departamento -->
                                <div class="col-sm-12 col-md-3">
                                    <div class="form-group" style="width: 95%">
                                        <label>Departamento</label>
                                        <select class="form-control" id="est_Departamento" name="est_Departamento">
                                            <option value="1">Boyaca</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Ciudad -->
                                <div class="col-sm-12 col-md-3">
                                    <div class="form-group" style="width: 95%">
                                        <label>Ciudad</label>
                                        <select class="form-control" id="est_Ciudad" name="est_Ciudad">
                                            <option value="1">Paipa</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Barrio -->
                                <div class="col-sm-12 col-md-3">
                                    <div class="form-group" style="width: 95%">
                                        <label>Barrio</label>
                                        <input type="text" class="form-control" id="est_Barrio" name="est_Barrio">
                                    </div>
                                </div>

                                <!-- Correo -->
                                <div class="col-sm-12 col-md-6">
                                    <div class="form-group" style="width: 95%">
                                        <label>Correo</label>
                                        <input type="email" class="form-control" id="est_Correo" name="est_Correo">
                                    </div>
                                </div>

                                <!-- Teléfono -->
                                <div class="col-sm-12 col-md-3">
                                    <div class="form-group" style="width: 95%">
                                        <label>Teléfono</label>
                                        <input type="text" class="form-control" id="est_Telefono" name="est_Telefono">
                                    </div>
                                </div>

                                <!-- Activos -->
                                <div class="col-sm-12 col-md-3">
                                    <div class="form-group" style="width: 95%">
                                        <label>Activos</label>
                                        <input type="number" class="form-control" id="est_Activos" name="est_Activos" value="0">
                                    </div>
                                </div>

                                <!-- Area -->
                                <div class="col-sm-12 col-md-3">
                                    <div class="form-group" style="width: 95%">
                                        <label>Área</label>
                                        <input type="text" class="form-control" id="est_Area" name="est_Area">
                                    </div>
                                </div>

                                <!-- Persona -->
                                <div class="col-sm-12 col-md-3">
                                    <div class="form-group" style="width: 95%">
                                        <label>Persona</label>
                                        <select class="form-control" id="est_Persona" name="est_Persona">
                                            <option value="1">Natural</option>
                                            <option value="2">Jurídica</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Opción de uso -->
                                <div class="col-sm-12 col-md-3">
                                    <div class="form-group" style="width: 95%">
                                        <label>Opción de uso</label>
                                        <select class="form-control" id="est_OpcionUso" name="est_OpcionUso">
                                            <option value="1">Inscripción</option>
                                            <option value="2">Actualización</option>
                                            <option value="3">Cese de Actividades</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Causal -->
                                <div class="col-sm-12 col-md-3">
                                    <div class="form-group" style="width: 95%">
                                        <label>Causal</label>
                                        <select class="form-control" id="est_Causal" name="est_Causal">
                                            <option value="1">Fusion</option>
                                            <option value="2">Escision</option>
                                            <option value="3">Liquidación</option>
                                            <option value="4">Otro</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Cedula Representante Legal -->
                                <div class="col-sm-12 col-md-4">
                                    <div class="form-group" style="width: 95%">
                                        <label>Cédula R. Legal</label>
                                        <input type="text" class="form-control" id="est_CedulaRLegal" name="est_CedulaRLegal">
                                    </div>
                                </div>

                                <!-- Nombre Representante Legal -->
                                <div class="col-sm-12 col-md-8">
                                    <div class="form-group" style="width: 95%">
                                        <label>Nombre R. Legal</label>
                                        <input type="text" class="form-control" id="est_NombreRLegal" name="est_NombreRLegal">
                                    </div>
                                </div>

                                <!-- Correo Representante Legal -->
                                <div class="col-sm-12 col-md-6">
                                    <div class="form-group" style="width: 95%">
                                        <label>Correo R. Legal</label>
                                        <input type="email" class="form-control" id="est_CorreoRLegal" name="est_CorreoRLegal">
                                    </div>
                                </div>

                                <!-- Estado del Registro -->
                                <div class="col-sm-12 col-md-3">
                                    <div class="form-group" style="width: 95%">
                                        <label>Estado del Registro</label>
                                        <select class="form-control" id="est_EstadoRegistro" name="est_EstadoRegistro">
                                            <option value="1">Matricula</option>
                                            <option value="2">Renovación</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Matrícula -->
                                <div class="col-sm-12 col-md-3">
                                    <div class="form-group" style="width: 95%">
                                        <label>Matrícula</label>
                                        <input type="text" class="form-control" id="est_Matricula" name="est_Matricula">
                                    </div>
                                </div>

                                <!-- Fechas -->
                                <div class="col-sm-12 col-md-4">
                                    <label>Fecha Matrícula</label>
                                    <input type="date" class="form-control" id="est_FechaMatricula" name="est_FechaMatricula">
                                </div>

                                <div class="col-sm-12 col-md-4">
                                    <label>Fecha Inscripción</label>
                                    <input type="date" class="form-control" id="est_FechaInscripcion" name="est_FechaInscripcion">
                                </div>

                                <div class="col-sm-12 col-md-4">
                                    <label>Fecha Inicio</label>
                                    <input type="date" class="form-control" id="est_FechaInicio" name="est_FechaInicio">
                                </div>

                                <!-- Toggles -->
                                <div class="col-sm-12 col-md-2">
                                    <label>Excluido</label><br>
                                    <input type="checkbox" id="est_Excluido" name="est_Excluido" data-toggle="switch">
                                </div>

                                <div class="col-sm-12 col-md-3">
                                    <label>Excento Avisos y Tableros</label><br>
                                    <input type="checkbox" id="est_ExcentoAvisos" name="est_ExcentoAvisos" data-toggle="switch">
                                </div>

                                <div class="col-sm-12 col-md-3">
                                    <label>Local en el municipio</label><br>
                                    <input type="checkbox" id="est_LocalMunicipio" name="est_LocalMunicipio" data-toggle="switch">
                                </div>

                                        <!-- Camara de Comercio -->
                                <div class="col-sm-12 col-md-2">
                                    <label>Cámara de Comercio</label><br>
                                    <input type="checkbox" id="est_CamaraComercio" name="est_CamaraComercio" data-toggle="switch">
                                </div>

                                <!-- Activo -->
                                <div class="col-sm-12 col-md-2">
                                    <label>Activo</label><br>
                                    <input type="checkbox" id="est_Activo" name="est_Activo" checked data-toggle="switch">
                                </div>

                                <!-- Codigo Catastral -->
                                <div class="col-sm-12 col-md-3">
                                    <div class="form-group" style="width: 95%">
                                        <label>Código Catastral</label>
                                        <input type="text" class="form-control" id="est_CodigoCatastral" name="est_CodigoCatastral">
                                    </div>
                                </div>



                                <!-- Observación -->
                                <div class="col-sm-12 col-md-12">
                                    <div class="form-group" style="width: 95%">
                                        <label>Observación</label>
                                        <input type="text" class="form-control" id="est_Observacion" name="est_Observacion">
                                    </div>
                                </div>

                                <!-- Fecha Cierre -->
                                <div class="col-sm-12 col-md-4">
                                    <label>Fecha Cierre</label>
                                    <input type="date" class="form-control" id="est_FechaCierre" name="est_FechaCierre">
                                </div>

                                <!-- No Resolución -->
                                <div class="col-sm-12 col-md-8">
                                    <div class="form-group" style="width: 95%">
                                        <label>No. Resolución</label>
                                        <input type="text" class="form-control" id="est_NoResolucion" name="est_NoResolucion">
                                    </div>
                                </div>

                                <!-- Principal Rut -->
                                <div class="col-sm-12 col-md-3">
                                    <div class="form-group" style="width: 95%">
                                        <label>Principal Rut</label>
                                        <input type="text" class="form-control" id="est_PrincipalRut" name="est_PrincipalRut">
                                    </div>
                                </div>

                                <!-- Actividades Rut -->
                                <div class="col-sm-12 col-md-3">
                                    <label>Actividad 2 Rut</label>
                                    <input type="text" class="form-control" id="est_Actividad2" name="est_Actividad2">
                                </div>

                                <div class="col-sm-12 col-md-3">
                                    <label>Actividad 3 Rut</label>
                                    <input type="text" class="form-control" id="est_Actividad3" name="est_Actividad3">
                                </div>

                                <div class="col-sm-12 col-md-3">
                                    <label>Fecha Actividad</label>
                                    <input type="date" class="form-control" id="est_FechaActividad" name="est_FechaActividad">
                                </div>



                                <!-- Contador -->
                                <div class="col-sm-12 col-md-4">
                                    <label>Cédula Contador</label>
                                    <input type="text" class="form-control" id="est_CedulaContador" name="est_CedulaContador">
                                </div>

                                <div class="col-sm-12 col-md-4">
                                    <label>Nombre Contador</label>
                                    <input type="text" class="form-control" id="est_NombreContador" name="est_NombreContador">
                                </div>

                                <div class="col-sm-12 col-md-4">
                                    <label>Tarjeta Profesional</label>
                                    <input type="text" class="form-control" id="est_TarjetaContador" name="est_TarjetaContador">
                                </div>

                                <!-- Revisor -->
                                <div class="col-sm-12 col-md-4">
                                    <label>Cédula Revisor</label>
                                    <input type="text" class="form-control" id="est_CedulaRevisor" name="est_CedulaRevisor">
                                </div>

                                <div class="col-sm-12 col-md-4">
                                    <label>Nombre Revisor</label>
                                    <input type="text" class="form-control" id="est_NombreRevisor" name="est_NombreRevisor">
                                </div>

                                <div class="col-sm-12 col-md-4">
                                    <label>Tarjeta Profesional</label>
                                    <input type="text" class="form-control" id="est_TarjetaRevisor" name="est_TarjetaRevisor">
                                </div>

                            </div>
                        </div>

                        <div class="modal-footer" id="modal_footer">
                            <button type="button" class="btn btn-danger btn-pill" data-dismiss="modal">
                                <span class="ti-close"></span> Cancelar
                            </button>
                            <button type="submit" class="btn btn-success btn-pill" id="btnCrearEstablecimiento">
                                Guardar
                            </button>
                        </div>
                    </form>

					
				</div>
			</div>
		</div>
		



        <!-- MODAL CREAR DECLARACIÓN -->
        <div class="modal fade" id="modal-CrearDeclaracion" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-xl" role="document">
                <div class="modal-content">

                    <div class="modal-header">
                        <h4 class="modal-title">Crear Declaración</h4>
                        
                        <div style="position:absolute; right:60px; top:15px; display:flex; gap:6px;">

                            <!-- CREAR -->
                            <button type="button"
                                    class="btn btn-sm btn-primary"
                                    id="btnCrearDeclaracion">
                                <i class="fa fa-plus"></i> Crear
                            </button>

                            <!-- IMPRIMIR 
                            <button type="button"
                                    class="btn btn-sm btn-success"
                                    id="btnDescargarPDF"
                                    disabled>
                                <i class="fa fa-file-pdf-o"></i> PDF BORRADOR
                            </button>
-->
                         

                        </div>


                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">

                        <form id="formDeclaracion">

                            <div class="row">

                                <!-- NUMERO DECLARACIÓN -->
                                <div class="col-sm-3">
                                    <label>Numero Declaración</label>
                                    <input type="text" id="numDeclaracion" class="form-control input-sm" readonly>
                                </div>

                                <!-- AÑO -->
                                <div class="col-sm-3">
                                    <label>Año Declaración</label>
                                    <input type="number" id="anioDeclaracion" class="form-control input-sm" >
                                </div>

                                <!-- PERIODO -->
                                <div class="col-sm-3">
                                    <label>Periodo Declaración</label>
                                    <input type="number" id="periodoDeclaracion" class="form-control input-sm" >
                                </div>

                                <!-- FECHA DECLARACIÓN -->
                                <div class="col-sm-3">
                                    <label>Fecha Declaración</label>
                                    <input type="date" id="fechaDeclaracion" class="form-control input-sm">
                                </div>

                                <!-- HORA -->
                                <div class="col-sm-3">
                                    <label>Hora</label>
                                    <input type="time" id="horaDeclaracion" class="form-control input-sm">
                                </div>

                                <!-- OPCIÓN DE USO -->
                                <div class="col-sm-3">
                                    <label>Opción de uso</label>
                                    <select id="opcionUso" class="form-control input-sm">
                                        <option value="">Seleccione…</option>
                                        <option value="1">Declaración Inicial</option>
                                        <!-- <option value="2">Solo Pago</option> -->
                                        <option value="3">Corrección</option>
                                    </select>
                                </div>

                                <!-- FECHA LÍMITE -->
                                <div class="col-sm-3">
                                    <label>Fecha Límite Para Cálculo de Intereses</label>
                                    <input type="date" id="fechaLimiteInteres" class="form-control input-sm">
                                </div>

                                <!-- DECLARACIÓN QUE CORRIGE -->
                                <div class="col-sm-3" id="grupoDeclaracionCorrige">
                                    <label>Declaración que corrige</label>
                                    <select id="declaracionCorrige" class="form-control input-sm">
                                        <option value="">Seleccione…</option>
                                    </select>
                                </div>

                                <!-- PAGA / SIN PAGO -->
                                 <!--
                                    <div class="col-sm-6">
                                        <label>Pagada</label><br>
                                        <input type="checkbox" id="switchPagada" data-toggle="toggle" data-onstyle="success" data-offstyle="secondary">
                                        <label style="margin-left:20px;">Sin pago</label>
                                        <input type="checkbox" id="switchSinPago" data-toggle="toggle" data-onstyle="success" data-offstyle="secondary" checked>
                                    </div>
                                -->

                                </div>


                            <!-- TABLA TOTALES -->
                            <h4 class="titulo-seccion">Totales</h4>
                                <div class="table-responsive bloque-separado">
                                    <table id="tablaTotales" class="table table-bordered">
                                        <tbody>

                                            <!-- CADA FILA TIENE 3 COLUMNAS -->
                                            <tr>
                                                <td style="width: 30%;">TOTAL INGRESOS ORDINARIOS Y EXTRAORDINARIOS DEL PERIODO EN TODO EL PAIS</td>
                                                <td style="width: 20%;"><input type="text" class="form-control campo-total" data-campo="ingresos_total_pais" value="0"></td>
                                                <td style="width: 5px; background:#fff;"></td>
                                                <td style="width: 30%;">MENOS INGRESOS FUERA DE ESTE MUNICIPIO O DISTRITO</td>
                                                <td style="width: 20%;"><input type="text" class="form-control campo-total" data-campo="1zmenos_fuera_municipio" value="0"></td>
                                            </tr>
                                            <tr>
                                                <td style="width: 30%;">TOTAL INGRESOS ORDINARIOS Y EXTRAORDINARIOS EN ESTE MUNICIPIO</td>
                                                <td style="width: 20%;"><input type="text" class="form-control campo-total" data-campo="ingresos_municipio" value="0" readonly></td>
                                                <td style="width: 5px; background:#fff;"></td>
                                                <td style="width: 30%;">MENOS INGRESOS POR DEVOLUCIONES, REBAJAS Y DESCUENTOS</td>
                                                <td style="width: 20%;"><input type="text" class="form-control campo-total" data-campo="devoluciones" value="0"></td>
                                            </tr>
                                            <tr>
                                                <td style="width: 30%;">MENOS INGRESOS POR EXPORTACIONES</td>
                                                <td style="width: 20%;"><input type="text" class="form-control campo-total" data-campo="exportaciones" value="0"></td>
                                                <td style="width: 5px; background:#fff;"></td>
                                                <td style="width: 30%;">MENOS INGRESOS POR VENTA DE ACTIVOS FIJOS</td>
                                                <td style="width: 20%;"><input type="text" class="form-control campo-total" data-campo="venta_activos" value="0"></td>
                                            </tr>
                                            <tr>
                                                <td style="width: 30%;">MENOS INGRESOS POR ACTIVIDADES EXCLUIDAS O NO SUJETAS</td>
                                                <td style="width: 20%;"><input type="text" class="form-control campo-total" data-campo="actividades_excluidas" value="0"></td>
                                                <td style="width: 5px; background:#fff;"></td>
                                                <td style="width: 30%;">MENOS INGRESOS POR OTRAS ACTIVIDADES EXENTAS</td>
                                                <td style="width: 20%;"><input type="text" class="form-control campo-total" data-campo="otras_exentas" value="0"></td>
                                            </tr>
                                            <tr>
                                                <td style="width: 30%;"></td>
                                                <td style="width: 20%;"></td>
                                                <td style="width: 5px; background:#fff;"></td>
                                                <td style="width: 30%;">TOTAL INGRESOS GRAVABLES</td>
                                                <td style="width: 20%;"><input type="text" class="form-control campo-total" data-campo="ingresos_gravables" value="0" readonly></td>
                                            </tr>

                                        </tbody>
                                    </table>
                                </div>


                                                                
                                <h4 class="titulo-seccion">Actividades</h4>

                                <div class="table-responsive bloque-separado">
                                    <table id="tablaActividades" class="table table-bordered table-striped">
                                        <thead style="background:#ececec; font-weight:bold;">
                                            <tr>
                                                <th>Actividad</th>
                                                <th>Base Gravable</th>
                                                <th>Tarifa</th>
                                                <th>Impuesto</th>
                                                <th>Seleccionado</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbodyActividades"></tbody>
                                    </table>
                                </div>





<!--
                                <h4 class="titulo-seccion">Actividades</h4>

                                <div class="table-responsive bloque-separado">
                                    <table id="tablaActividades" class="table table-bordered table-striped">
                                        <thead style="background:#ececec; font-weight:bold;">
                                            <tr>
                                                <th>GENERACIÓN DE ENERGIA CAPACIDAD INSTALADA  KW</th>                                            
                                                <th>IMPUESTO LEY 56 DE 1981 </th>
                                                <th>Seleccionado</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbodyActividades"></tbody>
                                    </table>
                                </div>
-->

                            <!-- LIQUIDAR -->
                            <button type="button"
                                    class="btn btn-sm"
                                    style="background-color:#0b3d91; color:white;"
                                    id="btnValidarDeclaracion"
                                    disabled>
                                <i class="fa fa-check"></i> Liquidar
                            </button>

                                <h4 class="titulo-seccion">Totales</h4>
                                <div class="table-responsive bloque-separado">
                                    <table id="tablaTotalesSegundos" class="table table-bordered">
                                        <tbody>
                                            
                                            <tr>
                                                <td style="width: 30%;">INDUSTRIA Y COMERCIO</td>
                                                <td style="width: 20%;"><input type="text" class="form-control campo-total" data-campo="industria_comercio" value="0" readonly></td>
                                                <td style="width: 5px; background:#fff;"></td>
                                                <td style="width: 30%;">AVISOS Y TABLEROS</td>
                                                <td style="width: 20%;"><input type="text" class="form-control campo-total" data-campo="avisos_tableros" value="0"></td>                                                    
                                            </tr>
                                            <tr>
                                                <td style="width: 30%;">SOBRETASA BOMBERIL</td>
                                                <td style="width: 20%;"><input type="text" class="form-control campo-total" data-campo="sobretasa_bomberil" value="0"></td>                                                    
                                                <td style="width: 5px; background:#fff;"></td>

                                                <td style="width: 30%;">SOBRETASA DE SEGURIDAD</td>
                                                <td style="width: 20%;"><input type="text" class="form-control campo-total" data-campo="sobretasa_seguridad" value="0" readonly></td>                                                    
                                            </tr>



                                            <tr>
                                                <td style="width: 30%;"></td>
                                                <td style="width: 20%;"></td>                 
                                                <td style="width: 5px; background:#fff;"></td>
                                                <td style="width: 30%;">TOTAL IMPUESTO A CARGO</td>
                                                <td style="width: 20%;"><input type="text" class="form-control campo-total" data-campo="total_impuesto_cargo" value="0" readonly></td>                                                    
                                            </tr>


                                                


                                                
                                            <tr>
                                                <td style="width: 30%;">VALOR DE EXENCION O EXONERACION</td>
                                                <td style="width: 20%;"><input type="text" class="form-control campo-total" data-campo="valor_exencion_exoneracion" value="0"></td> 
                                                <td style="width: 5px; background:#fff;"></td>
                                                <td style="width: 30%;">(-) MENOS RETENCIONES</td>
                                                <td style="width: 20%;"><input type="text" class="form-control campo-total" data-campo="menos_retenciones" value="0"></td>                                                    
                                            </tr>
                                            <tr>
                                                <td style="width: 30%;">MENOS AUTORETENCIONES</td>
                                                <td style="width: 20%;"><input type="text" class="form-control campo-total" data-campo="menos_autoretenciones" value="0"></td>                                                    
                                                <td style="width: 5px; background:#fff;"></td>
                                                <td style="width: 30%;">(-) ANTICIPO AÑO ANTERIOR</td>
                                                <td style="width: 20%;"><input type="text" class="form-control campo-total" data-campo="anticipo_anterior" value="0"></td>                                                    
                                            </tr>
                                            <tr>
                                                <td style="width: 30%;">(+) ANTICIPO AÑO SIGUIENTE</td>
                                                <td style="width: 20%;"><input type="text" class="form-control campo-total" data-campo="anticipo_siguiente" value="0"></td>                                                    
                                                
                                                <td style="width: 5px; background:#fff;"></td>
                                                <td style="width: 30%;">SANCIONES</td>
                                                <td style="width: 20%;"><input type="text" class="form-control campo-total" data-campo="sanciones" value="0"></td>                                                    
                                            </tr>
                                            <tr>
                                                <td style="width: 30%;">SALDO A FAVOR VIGENCIAS ANTERIORES</td>
                                                <td style="width: 20%;"><input type="text" class="form-control campo-total" data-campo="saldo_favor_vigencias_anteriores" value="0"></td>                                                    
                                                <td style="width: 5px; background:#fff;"></td>
                                                <td style="width: 30%;">TOTAL SALDO A CARGO</td>
                                                <td style="width: 20%;"><input type="text" class="form-control campo-total" data-campo="total_saldo_a_cargo" value="0" readonly></td>                                                    
                                            </tr>
                                            <tr>
                                                <td style="width: 30%;">TOTAL SALDO A FAVOR</td>
                                                <td style="width: 20%;"><input type="text" class="form-control campo-total" data-campo="total_saldo_a_favor" value="0" readonly></td>                                                    
                                                <td style="width: 5px; background:#fff;"></td>
                                                <td style="width: 30%;">VALOR A PAGAR</td>
                                                <td style="width: 20%;"><input type="text" class="form-control campo-total" data-campo="valor_a_pagar" value="0" readonly></td>                                                    
                                            </tr>
                                            <tr>
                                                <td style="width: 30%;">DESCUENTO POR PRONTO PAGO</td>
                                                <td style="width: 20%;"><input type="text" class="form-control campo-total" data-campo="descuento_pronto_pago" value="0"></td>                                                    
                                                <td style="width: 5px; background:#fff;"></td>
                                                <td style="width: 30%;">INTERES DE MORA</td>
                                                <td style="width: 20%;"><input type="text" class="form-control campo-total" data-campo="interes_mora" value="0"></td>                                                    
                                            </tr>
                                            <tr>
                                                <td style="width: 30%;"></td>
                                                <td style="width: 20%;"></td>                                                
                                                <td style="width: 5px; background:#fff;"></td>
                                                <td style="width: 30%;">TOTAL A PAGAR</td>
                                                <td style="width: 20%;"><input type="text" class="form-control campo-total" data-campo="total_a_pagar" value="0" readonly></td>                                                        
                                                
                                            </tr>


                                        </tbody>
                                    </table>
                                </div>

                            <button type="button"
                                class="btn btn-sm btn-success"
                                id="btnDescargarPDF"
                                disabled
                                onclick="window.open('../extensiones/declaracion.php','_blank')">
                                <i class="fa fa-file-pdf-o"></i> PDF BORRADOR
                            </button>


                            <!-- GENERAR DECLARACIÓN OFICIAL -->
                            <button type="button"
                                    class="btn btn-sm btn-warning"
                                    id="btnGenerarOficial"
                                    disabled>
                                <i class="fa fa-check-circle"></i> Declaración Oficial
                            </button>


                        </form>

                    </div>
<!--
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary" id="btnGuardarDeclaracion">Guardar</button>
                    </div>
-->
                </div>
            </div>
        </div>




        <!-- MODAL CONSULTAR DECLARACIONES -->
        <div class="modal fade" id="modal-ConsultarDeclaraciones" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-xl" role="document">
                <div class="modal-content">

                    <div class="modal-header">
                        <h4 class="modal-title">Declaraciones del Establecimiento</h4>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">

                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-sm" id="tablaDeclaraciones">
                                <thead style="background:#e9ecef; font-weight:600;">
                                    <tr>
                                        <th>Año</th>
                                        <th>Mes</th>
                                        <th>N° Declaración</th>
                                        <th>Fecha Pago</th>
                                        <th>Banco</th>
                                        <th>Valor Pago</th>
                                        <th style="width:80px;">Acción</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyDeclaraciones">
                                    <!-- dinámico -->
                                </tbody>
                            </table>
                        </div>

                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                            Cerrar
                        </button>
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
		<script src="../core/establecimientos.js?v=<?php echo time(); ?>"></script>
		<!-- <script src="../core/Permisos.js"></script> -->

        <script>
            $(document).on("keypress", ".campo-total", function (e) {
            if (!/[0-9.]/.test(e.key)) e.preventDefault();
        });

        

        </script>

        <style>

            /* Inputs compactos */
.modal .form-control {
    height: 32px;
    padding: 4px 8px;
    font-size: 13px;
}

/* Labels más compactos */
.modal label {
    margin-bottom: 2px;
    font-size: 13px;
    font-weight: 600;
}

/* Filas más juntas */
.modal .row > div {
    margin-bottom: 6px;
}

/* Títulos de sección */
.titulo-seccion {
    margin-top: 15px;
    margin-bottom: 6px;
    font-size: 15px;
    font-weight: 700;
}

/* Tablas compactas */
.modal table.table td,
.modal table.table th {
    padding: 6px 8px;
    font-size: 13px;
}

/* Inputs dentro de tablas */
.modal table .form-control {
    height: 30px;
    padding: 3px 6px;
    font-size: 13px;
}

/* Quitar aire extra */
.bloque-separado {
    margin-bottom: 10px;
}



    /* Espacio entre cada fila del modal */
    #modal-CrearDeclaracion .form-group,
    #modal-CrearDeclaracion .col-sm-6 {
        margin-bottom: 12px;
    }

    /* Espacio entre bloques */
    .bloque-separado {
        margin-top: 18px;
        margin-bottom: 12px;
    }

    /* Títulos de sección */
    .titulo-seccion {
        margin-top: 25px;
        margin-bottom: 8px;
        font-weight: bold;
        font-size: 15px;
    }

    /* Espacio dentro del modal */
    #modal-CrearDeclaracion .modal-body {
        padding-top: 20px !important;
    }


    /* ============================
   TABLA TOTALES COMPACTA
============================ */

#tablaTotales {
    font-size: 12.5px;
}

#tablaTotales td {
    padding: 4px 6px;
    vertical-align: middle;
}

#tablaTotales input.form-control {
    height: 28px;
    padding: 3px 6px;
    font-size: 12.5px;
    text-align: right;
}

/* Reduce separación entre bloques */
#tablaTotales tr {
    height: 30px;
}

/* Texto descriptivo más compacto */
#tablaTotales td:first-child,
#tablaTotales td:nth-child(4) {
    line-height: 1.2;
}

/* Quita bordes visuales innecesarios */
#tablaTotales td:nth-child(3) {
    padding: 0;
}

#tablaTotales td {
    white-space: normal;
}

</style>

	</div>	
</body>
</html>