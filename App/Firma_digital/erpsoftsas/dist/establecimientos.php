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
					<h4 class="h4">Listado de Establecimientos del Contribuyente</h4>
					<div>
					<button type="button" class="btn btn-outline-info mr-1" onclick="establecimientos.verInformacionContribuyente()"><span class="ti-id-badge"></span> Información del Contribuyente</button>
					<button type="button" class="btn btn-outline-success" onclick="establecimientos.crearEstablecimientos()"><span class="ti-plus"></span> Crear Establecimientos </button>
					</div>
				</div>
				<div class="pb-20">
				<table id="establecimientosRegistrados" class="data-table table stripe hover nowrap">
						<thead>
							<tr>
                                <th>Establecimiento</th>
                                <th>Contribuyente</th>
                                <th># Documento</th>
								<th>Dirección</th>
								<!-- Punto 13: el estado tenia que poder distinguirse de un
								     vistazo. Antes solo se deducia del color del boton de
								     accion, que ademas confunde: verde significaba "esta
								     activo", pero al pulsarlo lo inactiva. -->
								<th>Estado</th>
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
						<h5 class="modal-title" id="exampleModalFormTitle">Establecimiento</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					




<!--
<div class="col-sm-12 col-md-2">
    <label>Cámara de Comercio</label><br>
    <input type="checkbox" id="con_CamaraComercio" name="con_CamaraComercio" data-toggle="switch">
</div>
-->

<!-- 
<div class="col-sm-12 col-md-2">
    <label>Activo</label><br>
    <input type="checkbox" id="con_Activo" name="con_Activo" checked data-toggle="switch">
</div>
-->

<!-- 
<div class="col-sm-12 col-md-3">
    <div class="form-group" style="width: 95%">
        <label>Código Catastral</label>
        <input type="text" class="form-control" id="con_CodigoCatastral" name="con_CodigoCatastral">
    </div>
</div>
 -->
    
 <!-- 
<div class="col-sm-12 col-md-4">
    <label>Fecha Cierre del Establecimiento</label>
    <input type="date" class="form-control" id="con_FechaCierre" name="con_FechaCierre">
</div>
-->

<!--
<div class="col-sm-12 col-md-8">
    <div class="form-group" style="width: 95%">
        <label>No. Resolución</label>
        <input type="text" class="form-control" id="con_NoResolucion" name="con_NoResolucion">
    </div>
</div>
-->
       

<form id="formCrearEstablecimientos" action="" enctype="multipart/form-data">
<!-- El id del establecimiento que se esta editando. No existia: el codigo
     lo pasaba de funcion en funcion como parametro, asi que los anexos no
     tenian forma de saber a que establecimiento colgarse. -->
<input type="hidden" id="est_Id" name="est_Id">

<div class="modal-body">

<!-- ===================== DATOS GENERALES ===================== -->
<div class="bloque-form">
<div class="titulo-bloque">Datos Generales</div>
<div class="row">

<div class="col-md-2">
<label>* Código</label>
<!-- est_Codigo es INT en la base: si aqui entra una letra, la consulta
     revienta y el endpoint devuelve un 500 vacio que la pantalla solo
     sabe traducir a "error de conexion". El servidor lo valida tambien
     (_validarCodigo), esto es para avisar antes de enviar. -->
<input type="text" class="form-control" id="est_Codigo" name="est_Codigo"
       inputmode="numeric" pattern="[0-9]*" maxlength="10"
       title="Solo dígitos" required>
</div>

<!--
     Punto 5 de la reunion 2026-08-18: un RIT tiene muchos establecimientos, y
     el establecimiento se crea SIEMPRE desde el RIT del contribuyente que ya
     esta en sesion. Preguntar de quien es no tiene sentido: ya se sabe.

     El select se cambia por un campo oculto. El servidor de todas formas ya
     ignora lo que llegue aqui y fija el contribuyente de la sesion para todo
     rol que no sea Alcaldia (_agregarEstablecimientos / _editarEstablecimientos
     en class.establecimientos.php), asi que esto es solo la parte visible.
-->
<input type="hidden" id="est_IdContribuyente" name="est_IdContribuyente">

<div class="col-md-4">
<label>Nombre</label>
<input type="text" class="form-control" id="est_Nombre" name="est_Nombre" required>
</div>

<div class="col-md-4">
<label>Dirección</label>
<input type="text" class="form-control" id="est_Direccion" name="est_Direccion" required> 
</div>

<!-- OJO: estos tres campos van SIN atributo name a proposito. Las columnas
     est_Pais/est_Departamento/est_Ciudad son VARCHAR(5) y no admiten un
     nombre ("Colombia" son 8 caracteres): mandarlas tumbaba el guardado con
     un 500 vacio. El servidor las descarta igual (_descartarUbicacion), esto
     evita el viaje. Ver la nota larga en class.establecimientos.php.

     Reunion 2026-08-19: la ubicacion queda FIJA en el municipio de la
     instalacion. El cliente lo pidio explicito ("dejar bloqueado Paipa -
     Boyaca: porque los establecimientos que deben registrar son solo los de
     Paipa"), y es correcto: este sistema liquida el ICA de UN municipio, asi
     que un establecimiento de otro lado no tendria como declarar aqui.

     Se dejan como campos de solo lectura -no como <select> deshabilitado-
     porque el JS arma el payload con .val() y un select deshabilitado se ve
     como un desplegable que no responde, que confunde mas de lo que informa.
     Los ids se conservan: core/establecimientos.js los sigue leyendo igual.

     Van del config del municipio, no en duro, para que la marca blanca siga
     funcionando: otro municipio cambia config.municipio.php y ya. -->
<div class="col-md-3">
<label>País</label>
<input type="text" class="form-control" id="est_Pais"
       value="Colombia" readonly>
</div>

<div class="col-md-3">
<label>Departamento</label>
<input type="text" class="form-control" id="est_Departamento"
       value="<?php echo htmlspecialchars(defined('MUNICIPIO_DEPARTAMENTO') ? MUNICIPIO_DEPARTAMENTO : ''); ?>" readonly>
</div>

<div class="col-md-3">
<label>Ciudad</label>
<input type="text" class="form-control" id="est_Ciudad"
       value="<?php echo htmlspecialchars(defined('MUNICIPIO_CIUDAD') ? MUNICIPIO_CIUDAD : ''); ?>" readonly>
</div>

<div class="col-md-3">
<label>Barrio</label>
<input type="text" class="form-control" id="est_Barrio" name="est_Barrio">
</div>

<!-- Matricula y fecha de inicio DEL ESTABLECIMIENTO.
     Se habian ido del formulario en la limpieza del 2026-08-18, pero el
     certificado del RIT las imprime: es el punto 19 de la lista del cliente
     ("incluir nombre, matricula, direccion y fecha de inicio de los
     establecimientos"). Sin estos dos campos esa tabla del PDF no se puede
     llenar nunca -hoy 8 de 12 establecimientos tienen la fecha centinela
     1900-01-01, que es como decir vacia-.

     Ojo: NO confundir con las del contribuyente. La matricula de la persona
     natural o juridica y su fecha de inicio de actividades viven en el RIT
     (punto 8); estas son las del LOCAL. -->
<div class="col-md-3">
<label>Matrícula mercantil <small class="text-muted">(del establecimiento)</small></label>
<input type="text" class="form-control" id="est_Matricula" name="est_Matricula" maxlength="50">
</div>

<div class="col-md-3">
<label>Fecha de inicio de actividades <small class="text-muted">(del establecimiento)</small></label>
<input type="date" class="form-control" id="est_Fecha_inicio" name="est_Fecha_inicio">
</div>

<div class="col-md-3">
<label>Correo</label>
<input type="email" class="form-control" id="est_Correo" name="est_Correo">
</div>

<!-- lo esta trayendo de ind_contribuyentes, no se si es necesario
<div class="col-md-3">
<label>Teléfono</label>
<input type="text" class="form-control" id="est_Telefono" name="est_Telefono">
</div>
-->

<!-- Punto 6 (reunion 2026-08-18): "Activos" y "Área" se quitan del
     formulario. Ojo, la columna est_Activos NO se borra de la base: es un
     float con el monto de activos y hay filas con dato. Solo deja de pedirse.
     No confundirla con est_Activo (int), que es el indicador activo/inactivo. -->

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

<!-- Reunion 2026-08-19: el cliente pidio que el flag de "exento de avisos y
     tableros" viva aqui, en el establecimiento. Y aqui es donde debe estar:
     la exencion de avisos y tableros aplica al LOCAL (a su publicidad
     visible), no a la persona -un contribuyente puede tener un local exento
     y otro no.

     Ojo, esto ademas repara un defecto propio: estas dos casillas SI existian
     en este formulario y se borraron por error en el commit feba615 (reunion
     del 18) sin quitar el envio correspondiente en core/establecimientos.js.
     Como el elemento ya no existia, $(...).is(":checked") devolvia false y
     CADA guardado apagaba en silencio las dos exenciones. Mientras el input
     no exista, el JS no debe mandar el campo (ver guardas en el JS). -->
<div class="col-md-2">
<label>Excluido</label><br>
<input type="checkbox" id="est_Exento" name="est_Exento" data-toggle="switch">
</div>

<div class="col-md-3">
<label>Exento Avisos y Tableros</label><br>
<input type="checkbox" id="est_Excento_avisos" name="est_Excento_avisos" data-toggle="switch">
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
<!-- ===================== INFORMACIÓN TRIBUTARIA ===================== -->
<!-- ===================== CONTADOR Y REVISOR ===================== -->
<!-- ===================== DOCUMENTOS ===================== -->
<div class="bloque-form">
<div class="titulo-bloque">Documentos Adjuntos</div>
<div class="row">

<!-- Antes habia cuatro <input type="file"> sueltos (est_Pdf1..4) que no
     subian nada: el JS que los leia estaba dentro de un bloque comentado y el
     controlador no tenia $_FILES. Ahora se sube de verdad, contra
     class.anexos.php, y sobre todo se puede VER lo ya cargado, que es lo que
     pidio el cliente (punto 17). La carga es aparte del guardado del
     formulario: el establecimiento tiene que existir para colgarle archivos. -->

<div class="col-md-4">
<label>Tipo de documento</label>
<select class="form-control" id="anexoTipo">
    <option value="rut">RUT</option>
    <option value="camara">Cámara de Comercio o Acta de Constitución</option>
    <option value="cedula">Cédula</option>
    <option value="usosuelo">Uso de Suelos</option>
    <option value="cese">Soporte de cese</option>
    <option value="otro">Otro</option>
</select>
</div>

<div class="col-md-5">
<label>Archivo <small class="text-muted">(PDF, JPG o PNG, hasta 10 MB)</small></label>
<input type="file" class="form-control" id="anexoArchivo" accept=".pdf,.jpg,.jpeg,.png" multiple>
</div>

<div class="col-md-3">
<label>&nbsp;</label>
<button type="button" class="btn btn-primary btn-block" id="btnSubirAnexo">
    <i class="fa fa-upload"></i> Cargar
</button>
</div>

<div class="col-md-12 mt-3">
    <label>Archivos cargados</label>
    <div class="table-responsive">
        <table class="table table-bordered table-sm">
            <thead style="background:#e9ecef; font-weight:600;">
                <tr>
                    <th style="width:130px;">Tipo</th>
                    <th>Archivo</th>
                    <th style="width:110px;">Tamaño</th>
                    <th style="width:140px;">Cargado</th>
                    <th style="width:120px;" class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody id="tbodyAnexos"></tbody>
        </table>
    </div>
</div>

</div>
</div>

<!-- El cese de actividades se fue al RIT (reunion 2026-08-19): el cliente lo
     quiere justo debajo de "fecha de inicio de actividades", que es como esta
     en el formulario oficial en papel, y sin el numero de resolucion -esa
     resolucion la expide la Alcaldia despues, el contribuyente no la tiene
     cuando declara el cese-.

     Las columnas NO se mueven de sitio: siguen en ind_establecimientos
     (est_Fecha_cierre, est_Causal, est_Observacion_cierre) porque el cese es
     de un LOCAL, no de la persona -un contribuyente puede cerrar un
     establecimiento y seguir operando los otros-. Lo que cambia es donde se
     captura: la pantalla del RIT pregunta de cual establecimiento se trata.

     El control de permiso sigue en el servidor (_filtrarCese en
     class.establecimientos.php): solo la Alcaldia registra un cese. -->

<!-- ===================== OBSERVACIÓN Y AUTORIZACIÓN ===================== -->
<div class="bloque-form">
<div class="titulo-bloque">Observación y Autorización</div>

<!-- La observacion del cese se fue al RIT junto con el resto del bloque. -->

<!-- Punto 8: la autorizacion de notificacion electronica es del
     CONTRIBUYENTE, no de cada local, asi que vive unicamente en el RIT.
     Pedirla en cada establecimiento la repetia sin sentido. -->

</div>






<!-- Puntos 8 y 11 (reunion 2026-08-18): las actividades economicas salen del
     establecimiento y pasan al RIT, donde el contribuyente las registra. Aqui
     estaba la tabla con su buscador y su boton de agregar. -->
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

                            <!-- CREAR -->
                            <button type="button"
                                    class="btn btn-sm btn-primary"
                                    id="btnCrearDeclaracion">
                                <i class="fa fa-plus"></i> Crear
                            </button>

                            <!-- LIQUIDAR -->
                            <button type="button"
                                    class="btn btn-sm"
                                    style="background-color:#0b3d91; color:white;"
                                    id="btnValidarDeclaracion"
                                    disabled>
                                <i class="fa fa-check"></i> Liquidar
                            </button>

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

		<!--Modal Información del Contribuyente-->
		<div class="modal fade" id="modal-InfoContribuyente" tabindex="-1" role="dialog" aria-labelledby="tituloInfoContribuyente" aria-hidden="true">
			<div class="modal-dialog modal-lg" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="tituloInfoContribuyente">Información del Contribuyente</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<form id="formInfoContribuyente" onsubmit="establecimientos.guardarInformacionContribuyente(); return false;">
					<div class="modal-body">
						<input type="hidden" id="infoContrib_ind_Id" name="ind_Id">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label>Tipo de Persona</label>
									<select class="form-control" id="infoContrib_ind_Persona" name="ind_Persona">
										<option value="1">Natural</option>
										<option value="2">Jurídica</option>
									</select>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label>Tipo de Documento</label>
									<select class="form-control" id="infoContrib_ind_IdTipoDocumento" name="ind_IdTipoDocumento">
										<option value="1">C.C.</option>
										<option value="2">NIT</option>
										<option value="3">C.E.</option>
									</select>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label>Número de Documento</label>
									<input type="text" class="form-control" id="infoContrib_ind_NumeroIdentificacion" name="ind_NumeroIdentificacion">
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label>DV</label>
									<input type="text" class="form-control" id="infoContrib_ind_DV" name="ind_DV">
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label>Primer Nombre</label>
									<input type="text" class="form-control" id="infoContrib_ind_PrimerNombre" name="ind_PrimerNombre">
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label>Segundo Nombre</label>
									<input type="text" class="form-control" id="infoContrib_ind_SegundoNombre" name="ind_SegundoNombre">
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label>Primer Apellido</label>
									<input type="text" class="form-control" id="infoContrib_ind_PrimerApellido" name="ind_PrimerApellido">
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label>Segundo Apellido</label>
									<input type="text" class="form-control" id="infoContrib_ind_SegundoApellido" name="ind_SegundoApellido">
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label>Teléfono</label>
									<input type="text" class="form-control" id="infoContrib_ind_Telefono" name="ind_Telefono">
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label>Correo Electrónico</label>
									<input type="email" class="form-control" id="infoContrib_ind_Email" name="ind_Email">
								</div>
							</div>
							<div class="col-md-12">
								<div class="form-group">
									<label>Dirección</label>
									<input type="text" class="form-control" id="infoContrib_ind_Direccion" name="ind_Direccion">
								</div>
							</div>
								<!-- ind_IdCiudad: ver nota en icaWebRit.php. -->
								<div class="col-md-12">
									<div class="form-group">
										<label>Municipio de Registro</label>
										<select class="form-control select2" id="infoContrib_ind_IdCiudad" name="ind_IdCiudad" style="width:100%">
											<option value="">Seleccione ciudad...</option>
										</select>
									</div>
								</div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
						<button type="submit" class="btn btn-success"><span class="ti-save"></span> Guardar</button>
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
		<script src="../core/geografia.js?v=<?php echo time(); ?>"></script>
		<script src="../core/establecimientos.js?v=<?php echo time(); ?>"></script>
		<!-- <script src="../core/Permisos.js"></script> -->
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/css/select2.min.css" rel="stylesheet" />
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/js/select2.min.js"></script>
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