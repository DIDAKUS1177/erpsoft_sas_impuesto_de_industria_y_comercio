<?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    require_once '../business/globals.php';
    include_once('../business/class.sessions.php');
?>
<!DOCTYPE html>
<html>
<head>
	<!-- Basic Page Info -->
	<meta charset="utf-8">
	<title>Presentar Declaración | ERPSOFTSAS</title>

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
	
			<!--
			  Antes esta pantalla abria con la tabla de ESTABLECIMIENTOS y una
			  barra que mostraba SOLO la ultima declaracion; el resto quedaba
			  escondido en un modal al que se llegaba desde un establecimiento.
			  Como la declaracion es del contribuyente y no del establecimiento,
			  ahora se lista directamente y desde aqui se puede crear una nueva
			  en cualquier momento, no solo cuando no existe ninguna.
			-->
			<div class="card-box mb-30" id="ltsRol">
				<div class="pd-20 d-flex justify-content-between align-items-center">
					<h4 class="h4 mb-0">Declaraciones del Contribuyente</h4>
					<button type="button" class="btn btn-primary" id="btnNuevaDeclaracion">
						<i class="fa fa-file-text-o"></i> Crear Declaración
					</button>
				</div>
				<div class="pb-20 px-3">



                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-sm" id="tablaDeclaraciones">
                                <thead style="background:#e9ecef; font-weight:600;">
                                    <tr>
                                        <th>Año</th>
                                        <th>Mes</th>
                                        <th>N° Declaración</th>
                                        <th>Estado</th>
                                        <th>Fecha Pago</th>
                                        <th>Banco</th>
                                        <th>Valor Pago</th>
                                        <th class="text-center" style="width:280px;">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyDeclaraciones">
                                    <!-- dinámico -->
                                </tbody>
                            </table>
                        </div>
				</div>
			</div>
		</div>
    

		<!--Modal dependencia-->
		<div class="modal fade" id="modal-Establecimientos" role="dialog" aria-labelledby="exampleModalFormTitle" aria-hidden="true">
			<div class="modal-dialog modal-xl" role="document">
				<div class="modal-content ">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalFormTitle">Establecimiento</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					
       

<form id="formCrearEstablecimientos" action="" enctype="multipart/form-data">

<div class="modal-body">

<!-- ===================== DATOS GENERALES ===================== -->
<div class="bloque-form">
<div class="titulo-bloque">Datos Generales</div>
<div class="row">

<div class="col-md-2">
<label>* Código</label>
<input type="text" class="form-control" id="est_Codigo" name="est_Codigo" required>
</div>

<div class="col-md-5">
<label>*Nombre Establecimiento</label>
<input type="text" class="form-control" id="est_Nombre" name="est_Nombre" required>
</div>

<div class="col-md-5">
<label>* Dirección</label>
<input type="text" class="form-control" id="est_Direccion" name="est_Direccion" required> 
</div>

<div class="col-md-3">
<label>* País</label>
<select class="form-control" id="est_Pais" name="est_Pais" required>
<option value="Colombia">Colombia</option>
</select>
</div>

<div class="col-md-3">
<label>* Departamento</label>
<select class="form-control" id="est_Departamento" name="est_Departamento" required>
<option value="">Seleccione departamento...</option>
</select>
</div>

<div class="col-md-3">
<label>* Ciudad</label>
<select class="form-control" id="est_Ciudad" name="est_Ciudad" required>
<option value="">Seleccione municipio...</option>
</select>
</div>

<div class="col-md-3">
<label>* Barrio</label>
<input type="text" class="form-control" id="est_Barrio" name="est_Barrio" required>
</div>

<div class="col-md-3">
<label>* Correo</label>
<input type="email" class="form-control" id="est_Correo" name="est_Correo" required>
</div>

<!-- lo esta trayendo de ind_contribuyentes, no se si es necesario
<div class="col-md-3">
<label>Teléfono</label>
<input type="text" class="form-control" id="est_Telefono" name="est_Telefono">
</div>
-->

<!--  se oculto por Juan gabriel
<div class="col-sm-12 col-md-3">
    <div class="form-group" style="width: 95%">
        <label>Activos</label>
        <input type="number" class="form-control" id="est_Activos" name="est_Activos" value="0">
    </div>
</div>

<div class="col-sm-12 col-md-3">
    <div class="form-group" style="width: 95%">
        <label>Área</label>
        <input type="text" class="form-control" id="est_Area" name="est_Area">
    </div>
</div>
-->


<!--  Lo esta trayendo de ind_contribuyentes, no se si es necesario
<div class="col-md-3">
<label>Persona</label>
<select class="form-control" id="est_Persona" name="est_Persona">
<option value="1">Natural</option>
<option value="2">Jurídica</option>
</select>
</div>
-->

<div class="col-md-3">
<label>Estado del Registro</label>
<select class="form-control" id="est_OpcionUso" name="est_OpcionUso">
<option value="1">Inscripción</option>
<option value="2">Actualización</option>
</select>
</div>


<!-- 
<div class="col-sm-12 col-md-3">
    <div class="form-group" style="width: 95%">
        <label>Causal</label>
        <select class="form-control" id="con_Causal" name="con_Causal">
            <option value="1">Fusion</option>
            <option value="2">Escision</option>
            <option value="3">Liquidación</option>
            <option value="4">Otro</option>
        </select>
    </div>
</div>
-->

</div>
</div>

<!-- ===================== REPRESENTANTE LEGAL ===================== -->
<div class="bloque-form">
<div class="titulo-bloque">Representante Legal / Propietario</div>
<div class="row">

<div class="col-md-4">
<label>* Cédula Representante Legal / Propietario</label>
<input type="text" class="form-control" id="est_Cedula_representante" name="est_Cedula_representante" required>
</div>

<div class="col-md-8">
<label>* Nombre Representante Legal / Propietario</label>
<input type="text" class="form-control" id="est_Nombre_representante" name="est_Nombre_representante" required>
</div>

<div class="col-md-6">
<label>* Correo Representante Legal / Propietario</label>
<input type="email" class="form-control" id="est_Email_representante" name="est_Email_representante" required>
</div>



          
<!-- Estado del Registro 
<div class="col-sm-12 col-md-3">
    <div class="form-group" style="width: 95%">
        <label>Estado del Registro</label>
        <select class="form-control" id="con_EstadoRegistro" name="con_EstadoRegistro">
            <option value="1">Matricula</option>
            <option value="2">Renovación</option>
        </select>
    </div>
</div>
-->

<div class="col-md-3">
<label>Matrícula</label>
<input type="text" class="form-control" id="est_Matricula" name="est_Matricula">
</div>

<div class="col-md-4">
<label>Fecha Matrícula</label>
<input type="date" class="form-control" id="est_Fecha_matricula" name="est_Fecha_matricula">
</div>

<div class="col-md-4">
<label>Fecha Inscripción</label>
<input type="date" class="form-control" id="est_Fecha_inscripcion" name="est_Fecha_inscripcion" value="<?php echo date('Y-m-d'); ?>">
</div>

<div class="col-md-4">
<label>Fecha Inicio de Actividades en el Municipio</label>
<input type="date" class="form-control" id="est_Fecha_inicio" name="est_Fecha_inicio">
</div>

<!-- Las dos exenciones se fueron al RIT (migracion 016). Ser o no sujeto
     pasivo, y estar o no obligado a avisos y tableros, es una condicion de la
     PERSONA frente al municipio: un contribuyente con tres locales no puede
     estar exento en uno y no en otro. Aqui estaban repetidas por local y
     pudiendo contradecirse.

     Se llaman ahora "Realiza actividades no sujetas o no gravadas" y "Sin
     Avisos y Tableros", que es como las nombro el cliente el 2026-08-25.

     OJO si alguien piensa en devolverlas: el JS NO debe volver a enviar
     est_Exento ni est_Excento_avisos mientras el input no exista, porque
     $(...).is(":checked") sobre un elemento ausente devuelve false y cada
     guardado apagaria el dato en silencio. Ya paso una vez. -->

<!-- 
<div class="col-sm-12 col-md-3">
    <label>Local en el municipio</label><br>
    <input type="checkbox" id="con_LocalMunicipio" name="con_LocalMunicipio" data-toggle="switch">
</div>
-->


</div>
</div>




<!-- ===================== ACTIVIDADES ECONÓMICAS ===================== -->
<div class="card shadow-sm mt-3">

<div class="card-header bg-light">
<strong>Actividades Económicas</strong>
</div>

<div class="card-body">

<!-- FILTROS -->
<div class="row align-items-end mb-3">

<div class="col-md-2">
<label class="font-weight-bold">Año</label>
<select class="form-control" id="ace_Anio">
<option value="">Seleccione</option>
</select>
</div>

<div class="col-md-7">
<label class="font-weight-bold">Actividad Económica</label>
<select class="form-control" id="ace_IdCodigoActividad"></select>
</div>

<div class="col-md-3">
<button type="button"
class="btn btn-success btn-block"
onclick="establecimientos.agregarActividad()">
<i class="fa fa-plus"></i> Agregar Actividad
</button>
</div>

</div>


<!-- TABLA -->
<div class="table-responsive">

<table class="table table-striped table-hover table-sm align-middle"
id="tablaActividadesEstablecimiento">

<thead class="thead-light">

<tr>

<th style="width:10%" class="text-center">
Código
</th>

<th>
Actividad Económica
</th>

<th style="width:12%" class="text-center">
Año
</th>

<th style="width:10%" class="text-center">
Acción
</th>

</tr>

</thead>

<tbody id="tbodyActividadesEstablecimiento"></tbody>

</table>

</div>

</div>

</div>


<!-- ===================== INFORMACIÓN TRIBUTARIA ===================== -->
<div class="bloque-form">
<div class="titulo-bloque">Información RUT</div>
<div class="row">

<div class="col-md-3">
<label>* Actividad Principal</label>
<input type="text" class="form-control" id="est_Rut" name="est_Rut" required>
</div>

<div class="col-md-3">
<label>Actividad 2 Rut</label>
<input type="text" class="form-control" id="est_Rut_segundo" name="est_Rut_segundo">
</div>

<div class="col-md-3">
<label>Actividad 3 Rut</label>
<input type="text" class="form-control" id="est_Rut_tercero" name="est_Rut_tercero">
</div>

<div class="col-md-3">
<label>Fecha Actividad</label>
<input type="date" class="form-control" id="est_Fecha_actividad" name="est_Fecha_actividad">
</div>

</div>
</div>

<!-- ===================== CONTADOR Y REVISOR ===================== -->
<div class="bloque-form">
<div class="titulo-bloque">Contador y Revisor Fiscal</div>
<div class="row">

<div class="col-md-4">
<label>Cédula Contador</label>
<input type="text" class="form-control" id="est_Cedula_contador" name="est_Cedula_contador">
</div>

<div class="col-md-4">
<label>Nombre Contador</label>
<input type="text" class="form-control" id="est_Nombre_contador" name="est_Nombre_contador">
</div>

<div class="col-md-4">
<label>Tarjeta Profesional</label>
<input type="text" class="form-control" id="est_Tarjeta_profesional" name="est_Tarjeta_profesional">
</div>

<div class="col-md-4">
<label>Cédula Revisor</label>
<input type="text" class="form-control" id="est_Cedula_revisor" name="est_Cedula_revisor">
</div>

<div class="col-md-4">
<label>Nombre Revisor</label>
<input type="text" class="form-control" id="est_Nombre_revisor" name="est_Nombre_revisor">
</div>

<div class="col-md-4">
<label>Tarjeta Profesional</label>
<input type="text" class="form-control" id="est_Tarjeta_profesional_revisor" name="est_Tarjeta_profesional_revisor">
</div>

</div>
</div>





<!-- Los documentos adjuntos salieron de la inscripcion y la actualizacion del
     establecimiento. Pedido el 2026-08-21 (punto 14) y repetido el 2026-08-25:
     "Sin documentos adjuntos en la inscripcion o actualizacion del
     establecimiento".

     El RUT, la camara de comercio y la cedula son del CONTRIBUYENTE, no de
     cada local: pedirlos aqui los hacia repetir tantas veces como locales
     tuviera. Suben al RIT.

     Ya se habian quitado de dist/establecimientos.php; estas dos pantallas se
     quedaron con la copia y por eso el cliente los seguia viendo.

     NO se toca class.anexos.php ni anexos_establecimientos/: los archivos ya
     subidos deben poder consultarse, y la subida por establecimiento seguira
     haciendo falta para la constancia de cierre. -->

<!-- ===================== OBSERVACIÓN Y AUTORIZACIÓN ===================== -->
<div class="bloque-form">
<div class="titulo-bloque">Observaciones</div>

<input type="text" class="form-control mb-3" id="est_Observacion_cierre" name="est_Observacion_cierre">

<div class="form-check">
<input type="checkbox" id="est_Autorizacion" name="est_Autorizacion" data-toggle="switch"> 
<label style="margin-left:8px;">
Autorizo que la Administración Tributaria me notifique los actos administrativos en materia de impuestos al correo electrónico registrado en esta plataforma, y acepto que dichas notificaciones se entenderán válidamente surtidas conforme al artículo 565 y siguientes del Estatuto Tributario y las normas que los modifiquen, adicionen o sustituyan
</label>
</div>

</div>




</div>

<div class="modal-footer">
<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal">
<span class="ti-close"></span> Cancelar
</button>

<button type="submit" class="btn btn-success btn-pill" id="btnCrearEstablecimientos">
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

                            <!-- CREAR 
                            <button type="button"
                                    class="btn btn-sm btn-primary"
                                    id="btnCrearDeclaracion">
                                <i class="fa fa-plus"></i> Crear
                            </button>
-->
                            <!-- IMPRIMIR 
                            <button type="button"
                                    class="btn btn-sm btn-success"
                                    id="btnDescargarPDF"
                                    disabled>
                                <i class="fa fa-file-pdf-o"></i> PDF BORRADOR
                            </button>
-->
                            <button type="button"
                                class="btn btn-sm btn-success"
                                id="btnDescargarPDF"
                                disabled>
                                <i class="fa fa-file-pdf-o"></i> PDF BORRADOR
                            </button>

                            <!-- LIQUIDAR: calcula y NO guarda.
                                 El cliente lo pidio de vuelta el 2026-08-26.
                                 Va ANTES de "Guardar y liquidar" porque ese es
                                 el orden en que se usan: primero se mira como
                                 queda, despues se conserva. El manejador esta
                                 en core/declaraciones.ui.js, uno solo para las
                                 dos pantallas que comparten este modal. -->
                            <button type="button"
                                    class="btn btn-sm btn-info"
                                    id="btnLiquidar"
                                    disabled>
                                <i class="fa fa-calculator"></i> Liquidar
                            </button>

                            <!-- GENERAR DECLARACIÓN OFICIAL -->
                            <button type="button"
                                    class="btn btn-sm btn-warning"
                                    id="btnGenerarOficial"
                                    disabled>
                                <i class="fa fa-check-circle"></i> Guardar y liquidar
                            </button>

                        </div>


                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">

                        <!-- Progreso del tramite: responde "que hice, en que voy
                             y que me falta" sin que la persona tenga que
                             deducirlo de los botones disponibles. -->
                        <div id="stepperDeclaracion"></div>

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

                                <style>
/* Ver la nota en icaWebRit.php: un readonly de Bootstrap se ve igual que un
   campo editable, asi que hay que marcarlo para que se note. */
.campo-bloqueado { background-color:#eef1f4 !important; cursor:not-allowed; color:#55606b; }
</style>
<!-- FECHA Y HORA DE DECLARACIÓN
                                     Pedido el 2026-08-25: "que tome la fecha de creación de
                                     la declaración y no deje modificar". Son la constancia de
                                     cuándo se diligenció; dejarlas editables permitía
                                     antedatar una declaración, que sobre un documento
                                     tributario no es un detalle. El servidor ya las graba al
                                     crear (date('Y-m-d') / date('H:i:s') en
                                     _agregarDeclaracion), asi que aqui solo se muestran. -->
                                <div class="col-sm-3">
                                    <label>Fecha Declaración</label>
                                    <input type="date" id="fechaDeclaracion" class="form-control input-sm campo-bloqueado"
                                           readonly title="Es la fecha en que se creó la declaración">
                                </div>

                                <!-- HORA -->
                                <div class="col-sm-3">
                                    <label>Hora</label>
                                    <input type="time" id="horaDeclaracion" class="form-control input-sm campo-bloqueado"
                                           readonly title="Es la hora en que se creó la declaración">
                                </div>

                                <!-- OPCIÓN DE USO -->
                                <div class="col-sm-3">
                                    <label>Opción de uso</label>
                                    <select id="opcionUso" class="form-control input-sm">
                                        <option value="">Seleccione…</option>
                                        <option value="1">Declaración Inicial</option>
                                        <option value="3">Corrección</option>
                                    </select>
                                </div>

                                <!-- Punto 10 de la revision del 2026-08-21: se quita "Fecha Limite
                                     Para Calculo de Intereses". El cliente no la usa; el interes de
                                     mora se liquida en su propio renglon del formulario.
                                     La columna en base NO se toca, solo deja de pedirse. -->
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
                                                <td style="width: 20%;"><input type="text" class="form-control campo-total" data-campo="menos_fuera_municipio" value="0"></td>
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
                                            </tr>
                                        </thead>
                                        <tbody id="tbodyActividades"></tbody>

                                        <tfoot>
                                            <tr style="background:#f7f7f7;font-weight:bold;">
                                            <td class="text-right">TOTAL</td>

                                            <td>
                                            <input type="text" id="totalBaseGravable"
                                            class="form-control" readonly>
                                            </td>

                                            <td></td>

                                            <td>
                                            <input type="text" id="totalImpuesto"
                                            class="form-control" readonly>
                                            </td>

                                            <td></td>

                                            </tr>
                                            </tfoot>
                                    </table>
                                </div>

                                <h4 class="titulo-seccion">Actividades</h4>

                                <div class="table-responsive bloque-separado">

                                <table id="tablaEnergia" class="table table-bordered table-striped">
                                    <thead style="background:#ececec;font-weight:bold;">
                                        <tr>
                                            <th>GENERACIÓN DE ENERGÍA CAPACIDAD INSTALADA (KW)</th>
                                            <th>IMPUESTO LEY 56 DE 1981</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>
                                                <input type="text"
                                                class="form-control capacidad-kw"
												data-campo="capacidad_instalada"
                                                value="0">
                                            </td>
                                            <td>
                                                <input type="text"
                                                class="form-control impuesto-energia"
												data-campo="valor_impuesto"
                                                value="0">
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                                </div>

                                                                <!-- LIQUIDAR -->
                                <!-- El boton "Liquidar" se retira. Instruccion del cliente:
                                     "liquidar solo al guardar".

                                     En este sistema liquidar y guardar SON la misma operacion:
                                     el calculo lo hace sp_calculo_comercio del lado del
                                     servidor, sobre la fila ya grabada, asi que un "liquidar
                                     sin guardar" no existe. Tener dos botones para una sola
                                     operacion fue justo lo que produjo los reportes de "no
                                     liquida" y "no guarda": cada pantalla tenia el manejador
                                     real colgado de uno distinto y el otro mentia.

                                     Queda un unico boton, y dice lo que hace. -->

                            <h4 class="titulo-seccion">Liquidación Privada</h4>

                            <div class="table-responsive bloque-separado">

                            <table id="tablaTotalesSegundos" class="table table-bordered">

                            <thead style="background:#ececec;font-weight:bold;">
                            <tr>
                            <th style="width:8%">No</th>
                            <th>Concepto</th>
                            <th style="width:220px">Valor</th>
                            </tr>
                            </thead>

                            <tbody>

                            <tr>
                            <td>20</td>
                            <td>TOTAL IMPUESTO INDUSTRIA Y COMERCIO (reglón 17 + 19)</td>
                            <td>
                            <input type="text" class="form-control campo-total"
                            data-campo="industria_comercio"  value="0" readonly>
                            </td>
                            </tr>

                            <tr>
                            <td>21</td>
                            <td>IMPUESTO DE AVISOS Y TABLEROS (15% de renglón 20)</td>
                            <td>
                            <input type="text" class="form-control campo-total"
                            data-campo="avisos_tableros" numeroCampo="2" value="0">
                            </td>
                            </tr>
<!---
                            <tr>
                            <td>22</td>
                            <td>PAGO POR UNIDADES COMERCIALES ADICIONALES DEL SECTOR FINANCIERO</td>
                            <td>
                            <input type="text" class="form-control campo-total"
                            data-campo="avisos_tableros" value="0">
                            </td>
                            </tr>
-->
                            <tr>
                            <td>23</td>
                            <td>SOBRETASA BOMBERIL (Ley 1575 de 2012) si la hay, liquidela segun el acuerdo municipal o distrital</td>
                            <td>
                            <input type="text" class="form-control campo-total"
                            data-campo="sobretasa_bomberil" numeroCampo="3" value="0">
                            </td>
                            </tr>

                            <tr>
                            <td>24</td>
                            <td>SOBRETASA DE SEGURIDAD (Ley 1421 de 2011) si la hay, liquidela segun el acuerdo municipal o distrital</td></td>
                            <td>
                            <input type="text" class="form-control campo-total"
                            data-campo="sobretasa_seguridad"  value="0" readonly>
                            </td>
                            </tr>

                            <tr>
                            <td>25</td>
                            <td>TOTAL IMPUESTO A CARGO (Renglon 20+21+22+23+24)</td>
                            <td>
                            <input type="text" class="form-control campo-total"
                            data-campo="total_impuesto_cargo" readonly>
                            </td>
                            </tr>

                            <tr>
                            <td>26</td>
                            <td>MENOS VALOR DE EXENCIÓN O EXONERACIÓN SOBRE EL IMPUESTO Y NO SOBRE LOS INGRESOS</td>
                            <td>
                            <input type="text" class="form-control campo-total"
                            data-campo="valor_exencion_exoneracion" numeroCampo="5" value="0">
                            </td>
                            </tr>

                            <tr>
                            <td>27</td>
                            <td>MENOS RETENCIONES que le practicaron a favor de este municipio o distrito en este periodo</td>
                            <td>
                            <input type="text" class="form-control campo-total"
                            data-campo="menos_retenciones" numeroCampo="6" value="0">
                            </td>
                            </tr>

                            <tr>
                            <td>28</td>
                            <td>MENOS AUTORETENCIONES practicadas a favor de este municipio o distrito en este periodo</td>
                            <td>
                            <input type="text" class="form-control campo-total"
                            data-campo="menos_autoretenciones" numeroCampo="7" value="0">
                            </td>
                            </tr>

                            <tr>
                            <td>29</td>
                            <td>MENOS ANTICIPO LIQUIDADO EN EL AÑO ANTERIOR </td>
                            <td>
                            <input type="text" class="form-control campo-total"
                            data-campo="anticipo_anterior" numeroCampo="8" value="0">
                            </td>
                            </tr>

                            <tr>
                            <td>30</td>
                            <td>ANTICIPO DEL AÑO SIGUIENTE (si existe , liquide porcentaje segun el acuerdo municipal o distrital)</td>
                            <td>
                            <input type="text" class="form-control campo-total"
                            data-campo="anticipo_siguiente" numeroCampo="9" value="0">
                            </td>
                            </tr>
<!--
<tr>
<td>31</td>
<td>SANCIONES</td>
<td>
<input type="text" class="form-control campo-total"
data-campo="sanciones" value="0">
</td>
</tr>
-->

                            <tr>
                            <td>31</td>
                            <td>
                                <b>SANCIONES</b><br>

                                <!-- Punto 15 de la revision del 2026-08-21: "Quitar activar
                                     sanciones y colocar directamente las opciones de la sancion".

                                     La casilla "Activar sanciones" era un paso de mas: para marcar
                                     una sancion habia que marcar primero una casilla y despues el
                                     tipo. Ahora las opciones estan a la vista y basta elegir una.

                                     Se agrega "Ninguna" marcada por defecto, porque sin ella no
                                     habria forma de DESmarcar una sancion elegida por error: los
                                     radios no se pueden desmarcar entre si. -->
                                <div id="boxSanciones" style="margin-top:8px;">

                                    <div>
                                        <input type="radio" name="tipoSancion" value="" id="chkSinSancion" checked> Ninguna
                                    </div>

                                    <div>
                                        <input type="radio" name="tipoSancion" value="extemporaneidad"> Extemporaneidad
                                    </div>

                                    <div>
                                        <input type="radio" name="tipoSancion" value="correccion"> Corrección
                                    </div>

                                    <div>
                                        <input type="radio" name="tipoSancion" value="inexactitud"> Inexactitud
                                    </div>

                                    <div>
                                        <input type="radio" name="tipoSancion" value="otra" id="chkOtraSancion"> Otra
                                    </div>

                                    <div id="inputOtraSancion" style="display:none; margin-top:5px;">
                                        <input type="text" class="form-control" id="txtOtraSancion" placeholder="Detalle de la sanción">
                                    </div>

                                </div>

                            </td>

                            <td>
                            <input type="text" class="form-control campo-total"
                            data-campo="sanciones"  numeroCampo="10" value="0">
                            </td>
                            </tr>


                            <tr>
                            <td>32</td>
                            <td>MENOS SALDO A FAVOR DEL PERIODO ANTERIOR SIN SOLICITUD DE DEVOLUCION O COMPENSACION</td>
                            <td>
                            <input type="text" class="form-control campo-total"
                            data-campo="saldo_favor_vigencias_anteriores"  numeroCampo="11" value="0">
                            </td>
                            </tr>

                            <tr>
                            <td>33</td>
                            <td>TOTAL SALDO A CARGO (Renglon 25-26-27-28-29+30+31-32)</td>
                            <td>
                            <input type="text" class="form-control campo-total"
                            data-campo="total_saldo_a_cargo"  value="0" readonly>
                            </td>
                            </tr>

                            <tr>
                            <td>34</td>
                            <td>TOTAL SALDO A FAVOR (Renglon 25-26-27-28-29+30+31-32) si el resultado es menor a cero</td>
                            <td>
                            <input type="text" class="form-control campo-total"
                            data-campo="total_saldo_a_favor"  value="0" readonly>
                            </td>
                            </tr>

                            <tr>
                            <td>35</td>
                            <td>VALOR A PAGAR</td>
                            <td>
                            <input type="text" class="form-control campo-total"
                            data-campo="valor_a_pagar"  value="0" readonly>
                            </td>
                            </tr>

                            <tr>
                            <td>36</td>
                            <td>DESCUENTO POR PRONTO PAGO (si existe, liquidelo segun el acuerdo municipal o distrital)</td>
                            <td>
                            <input type="text" class="form-control campo-total"
                            data-campo="descuento_pronto_pago"  numeroCampo="15" value="0">
                            </td>
                            </tr>

                            <tr>
                            <td>37</td>
                            <td>INTERÉS DE MORA</td>
                            <td>
                            <input type="text" class="form-control campo-total"
                            data-campo="interes_mora"  numeroCampo="16" value="0">
                            </td>
                            </tr>

                            <tr style="background:#f5f5f5;font-weight:bold;">
                            <td>38</td>
                            <td>TOTAL A PAGAR (Renglon 35-36+37)</td>
                            <td>
                            <input type="text" class="form-control campo-total"
                            data-campo="total_a_pagar"  value="0" readonly>
                            </td>
                            </tr>

                            </tbody>

                            </table>
                            </div>
                              

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




        <!-- MODAL FIRMA DIGITAL -->
        <div class="modal fade" id="modal-FirmaDigital" role="dialog" aria-hidden="true" data-backdrop="static">
            <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header" style="background: var(--erp-primario); color: white;">
                        <h5 class="modal-title text-white"><i class="fa fa-certificate"></i> Firma Digital</h5>
                        <button type="button" class="close text-white" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body text-center">
                        <p style="font-size: 14px; margin-bottom: 6px;">Enviamos un código de 6 dígitos a:</p>
                        <p id="otpDestino" style="font-size: 13px; font-weight: 700; color: var(--erp-primario); margin-bottom: 4px; word-break: break-all;">su correo electrónico</p>
                        <p id="otpVigencia" style="font-size: 12px; color: #6B7280; margin-bottom: 15px;">El código vence en 10:00</p>
                        <div class="form-group">
                            <input type="text" id="otpCodigo" class="form-control form-control-lg text-center"
                                   placeholder="000000" maxlength="6" inputmode="numeric" autocomplete="one-time-code"
                                   style="font-size: 24px; letter-spacing: 5px; font-weight: bold; width: 80%; margin: 0 auto;">
                        </div>
                        <div id="otpError" style="display:none; font-size:12.5px; color:#DC2626; margin-top:8px;"></div>
                        <button type="button" id="btnReenviarOTP" class="btn btn-link btn-sm" style="font-size:12.5px; margin-top:6px;">
                            <i class="fa fa-refresh"></i> Reenviar código
                        </button>
                        <input type="hidden" id="otpIdDeclaracion">
                    </div>
                    <div class="modal-footer justify-content-center">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-success btn-sm" id="btnValidarOTP">Validar y Firmar</button>
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
		<script src="../core/numeros.js?v=<?php echo time(); ?>"></script>
		<script src="../core/declaraciones.ui.js?v=<?php echo time(); ?>"></script>
		<script src="../core/geografia.js?v=<?php echo time(); ?>"></script>
		<script src="../core/icaWebPresentar.js?v=<?php echo time(); ?>"></script>
		<!-- <script src="../core/Permisos.js"></script> -->
        <script>
            const ID_USUARIO = <?php echo isset($_SESSION['IdUsuario']) ? $_SESSION['IdUsuario'] : (isset($_SESSION['usu_Id']) ? $_SESSION['usu_Id'] : 0); ?>;
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