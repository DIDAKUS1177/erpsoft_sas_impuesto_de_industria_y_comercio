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
	
			<!--
			  Punto 4: el RIT es SOLO formulario. Antes esta pantalla abria con
			  la tabla de establecimientos y el RIT era un modal que habia que
			  ir a buscar. Los establecimientos ahora viven en su propio modulo
			  (punto 5); aqui queda el formulario del contribuyente, que es lo
			  que el RIT realmente es.
			-->
			<div class="card-box mb-30">

				<!-- Punto 6: los botones arriba, para no tener que recorrer
				     todo el formulario antes de poder guardar. -->
				<div class="pd-20 d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
					<div>
						<h4 class="h4 mb-0">Registro de Identificación Tributaria</h4>
						<small class="text-muted" id="ritEstadoCarga">Cargando…</small>
					</div>
					<!-- Reunion 2026-08-19. El RIT tiene dos estados y la barra cambia
					     con ellos:

					       FIRMADO  -> la pantalla queda BLOQUEADA. Solo "Actualizar"
					                   (que la desbloquea) y "Descargar".
					       EDITANDO -> "Guardar", "Firmar" y "Cancelar".

					     Es lo que pidio el cliente: "cuando ya lo firman, que quede
					     bloqueada esa pantalla, que ya no deje diligenciar los campos
					     (...) esa informacion ya queda guardada y ya no se puede editar
					     como tal, sino actualizar".

					     Encaja con como funciona la firma: cualquier cambio invalida el
					     hash, asi que "Actualizar" es, literalmente, empezar una novedad
					     que habra que volver a firmar. -->
					<div>
						<span id="ritEstadoFirma" class="mr-2"></span>

						<!-- Solo con el RIT firmado -->
						<button type="button" class="btn btn-primary" id="btnActualizarRIT" style="display:none;">
							<i class="fa fa-unlock"></i> Actualizar
						</button>

						<!-- Solo mientras se edita -->
						<button type="button" class="btn btn-outline-success" id="btnFirmarRIT">
							<i class="fa fa-pencil-square-o"></i> Firmar RIT
						</button>
						<button type="button" class="btn btn-outline-secondary" id="btnCancelarRIT">Cancelar</button>
						<button type="submit" class="btn btn-primary" form="formRIT" id="btnGuardarRIT">Guardar</button>

						<!-- Siempre -->
						<a class="btn btn-outline-info" id="btnDescargarRIT" href="#" target="_blank">
							<i class="fa fa-download"></i> Descargar RIT
						</a>
					</div>
				</div>

				<!-- Aviso de que actualizar el RIT es obligatorio. Sale solo mientras
				     NO haya firma vigente; en cuanto se firma, estorba. -->
				<div id="ritAvisoObligatorio" class="pd-20 pt-0" style="display:none;">
					<div class="alert alert-warning mb-0" style="border-left:4px solid #d39e00;">
						<b><i class="fa fa-exclamation-triangle"></i> Debe actualizar y firmar su RIT.</b><br>
						<span style="font-size:13px;">
							Diligencie la información, guárdela y fírmela. Mientras no esté firmado,
							el formulario que descargue saldrá marcado <b>SIN FIRMAR</b>.
							La inscripción o actualización del RIT está establecida en el estatuto
							tributario, así como las sanciones por no realizarla oportunamente.
						</span>
					</div>
				</div>

				<form id="formRIT" class="pd-20 pt-0" onsubmit="establecimientos.guardarRIT(); return false;">
					<input type="hidden" name="ind_Id" id="rit_ind_Id">

					<h5 class="mb-3" style="font-weight:600;">Identificación</h5>
					<div class="row">
						<!-- Documento, DV y tipo van solo de lectura: son la
						     identidad tributaria y una declaracion ya firmada
						     quedaria atada a un documento distinto del que la
						     firmo. Se cambian por el camino de administrador. -->
						<div class="col-md-3 form-group">
							<label>Tipo de documento</label>
							<input type="text" class="form-control" id="rit_TipoDocumento" readonly>
						</div>
						<div class="col-md-3 form-group">
							<label>Número de documento</label>
							<input type="text" class="form-control" id="rit_ind_NumeroIdentificacion" readonly>
						</div>
						<div class="col-md-1 form-group">
							<label>DV</label>
							<input type="text" class="form-control" id="rit_ind_DV" readonly>
						</div>
						<div class="col-md-5 form-group">
							<label>Tipo de persona</label>
							<select class="form-control" name="ind_Persona" id="rit_ind_Persona">
								<option value="1">Natural</option>
								<option value="2">Jurídica</option>
							</select>
						</div>
					</div>

					<div class="row">
						<div class="col-md-3 form-group">
							<label>Primer nombre</label>
							<input type="text" class="form-control" name="ind_PrimerNombre" id="rit_ind_PrimerNombre" maxlength="100">
						</div>
						<div class="col-md-3 form-group">
							<label>Segundo nombre</label>
							<input type="text" class="form-control" name="ind_SegundoNombre" id="rit_ind_SegundoNombre" maxlength="100">
						</div>
						<div class="col-md-3 form-group">
							<label>Primer apellido</label>
							<input type="text" class="form-control" name="ind_PrimerApellido" id="rit_ind_PrimerApellido" maxlength="100">
						</div>
						<div class="col-md-3 form-group">
							<label>Segundo apellido</label>
							<input type="text" class="form-control" name="ind_SegundoApellido" id="rit_ind_SegundoApellido" maxlength="100">
						</div>
					</div>

					<div class="row">
						<!-- Reunion 2026-08-19: el cliente pidio que en el formulario del RIT
						     esta casilla diga "Dirección de notificación", que es el nombre que
						     tiene en el formulario oficial en papel. Es la MISMA columna
						     (ind_Direccion): solo cambia el rotulo, no se agrega campo. Si mas
						     adelante piden separar la de residencia de la de notificacion, eso
						     si serian dos columnas distintas. -->
						<div class="col-md-4 form-group">
							<label>Dirección de notificación</label>
							<input type="text" class="form-control" name="ind_Direccion" id="rit_ind_Direccion" maxlength="200">
						</div>
						<!--
						     Punto 1 (reunion 2026-08-18): departamento y municipio en campos
						     separados. Antes era un unico select con los 1.120 municipios del
						     pais como "Municipio - Departamento": encontrar el propio obligaba a
						     recorrer la lista entera.
						
						     Lo que se guarda sigue siendo ind_IdCiudad. El departamento no es una
						     columna: sale de conf_ciudades.ciu_Departamento y solo acota la lista.
						-->
						<div class="col-md-4 form-group">
							<label>Departamento de residencia</label>
							<select class="form-control" id="rit_DepartamentoResidencia"></select>
						</div>
						<div class="col-md-4 form-group">
							<label>Municipio de residencia</label>
							<select class="form-control" name="ind_IdCiudad" id="rit_ind_IdCiudad"></select>
						</div>
						</div>

						<!--
						     Telefono y correo pasan a una fila propia, a mitad de ancho cada uno.
						     La fila anterior sumaba 13 columnas de una rejilla de 12 (3+3+3+2+2), asi
						     que Bootstrap bajaba la ultima y quedaba un hueco a la derecha. Ahora
						     cada fila cierra en 12 justos y los campos llegan hasta el borde.
						-->
						<div class="row">
						<div class="col-md-6 form-group">
							<label>Teléfono</label>
							<input type="text" class="form-control" name="ind_Telefono" id="rit_ind_Telefono">
						</div>
						<div class="col-md-6 form-group">
							<label>Correo electrónico de notificación</label>
							<input type="email" class="form-control" name="ind_Email" id="rit_ind_Email" maxlength="500">
						</div>
					</div>

					<hr>
					<h5 class="mb-3" style="font-weight:600;">Datos del registro</h5>
					<div class="row">
						<!-- Punto 8: esta matricula es la de la PERSONA natural
						     o juridica. La del establecimiento es otra y vive en
						     el modulo de establecimientos. -->
						<div class="col-md-3 form-group">
							<label>Matrícula mercantil <small class="text-muted">(de la persona natural o jurídica)</small></label>
							<input type="text" class="form-control" name="ind_Matricula" id="rit_ind_Matricula" maxlength="50">
						</div>
						<div class="col-md-3 form-group">
							<label>Fecha de matrícula</label>
							<input type="date" class="form-control" name="ind_Fecha_matricula" id="rit_ind_Fecha_matricula">
						</div>
						<!-- El municipio sale del config, no en duro: es la misma pantalla
						     para cualquier alcaldia que use el sistema. -->
						<div class="col-md-3 form-group">
							<label>Fecha de inicio de actividades en el Municipio de <?php echo htmlspecialchars(defined('MUNICIPIO_CIUDAD') ? MUNICIPIO_CIUDAD : ''); ?></label>
							<input type="date" class="form-control" name="ind_Fecha_inicio" id="rit_ind_Fecha_inicio">
						</div>
					</div>

					<!-- ===================== CESE DE ACTIVIDADES =====================
					     Reunion 2026-08-19: el cliente lo pidio aqui, "debajo de donde
					     dice fecha de inicio de actividades", y "sin el numero de
					     resolucion". Asi esta en el formulario oficial en papel.

					     El dato NO cambio de tabla: sigue en ind_establecimientos, porque
					     lo que cesa es un LOCAL y no la persona -se puede cerrar uno y
					     seguir operando los demas-. Por eso hay que decir de cual se
					     trata, y de ahi el selector.

					     Quien no es Alcaldia lo ve pero no lo edita: el cese lo registra
					     el municipio. El bloqueo de verdad esta en el servidor
					     (_filtrarCese en class.establecimientos.php); lo de aqui es solo
					     para no ofrecer algo que va a rebotar.
					-->
					<hr>
					<h5 class="mb-3" style="font-weight:600;">Cese de actividades</h5>

					<div id="ritAvisoCese" class="mb-3" style="display:none; font-size:13px; color:#6B7280;">
						<i class="fa fa-lock"></i> Solo la Alcaldía puede registrar el cese de actividades.
					</div>

					<div class="row">
						<div class="col-md-3 form-group">
							<label>Establecimiento que cesa</label>
							<select class="form-control" id="rit_cese_Establecimiento"></select>
						</div>
						<div class="col-md-3 form-group">
							<label>Fecha de cese</label>
							<input type="date" class="form-control cese-solo-admin" id="rit_est_Fecha_cierre">
						</div>
						<div class="col-md-3 form-group">
							<label>Causal</label>
							<select class="form-control cese-solo-admin" id="rit_est_Causal">
								<option value="">Sin cese</option>
								<option value="1">Fusión</option>
								<option value="2">Escisión</option>
								<option value="3">Liquidación</option>
								<option value="4">Otro</option>
							</select>
						</div>
						<div class="col-md-3 form-group">
							<label>Observación</label>
							<input type="text" class="form-control cese-solo-admin" maxlength="255"
							       id="rit_est_Observacion_cierre">
						</div>
					</div>

					<div class="row">
						<div class="col-md-12">
							<button type="button" class="btn btn-sm btn-primary cese-solo-admin"
							        id="btnGuardarCeseRIT">
								<i class="fa fa-save"></i> Guardar cese
							</button>
						</div>
					</div>

					<div class="row">
						<div class="col-md-3 form-group">
							<label>¿Registrado en cámara de comercio?</label>
							<select class="form-control" name="ind_Ind_camara_comercio" id="rit_ind_Ind_camara_comercio">
								<option value="">Sin especificar</option>
								<option value="1">Sí</option>
								<option value="0">No</option>
							</select>
						</div>
					</div>

					<hr>
					<h5 class="mb-3" style="font-weight:600;">Representante legal</h5>
					<div class="row">
						<div class="col-md-3 form-group">
							<label>Cédula</label>
							<input type="text" class="form-control" name="ind_Cedula_representante" id="rit_ind_Cedula_representante" maxlength="20">
						</div>
						<div class="col-md-5 form-group">
							<label>Nombre</label>
							<input type="text" class="form-control" name="ind_Nombre_representante" id="rit_ind_Nombre_representante" maxlength="100">
						</div>
						<div class="col-md-4 form-group">
							<label>Correo</label>
							<input type="email" class="form-control" name="ind_Email_representante" id="rit_ind_Email_representante" maxlength="150">
						</div>
					</div>

					<hr>
					<!-- Puntos 14 y 15: esto lo registra solo el administrador;
					     el contribuyente lo ve pero no lo edita. El bloqueo real
					     esta en el servidor (_camposSoloAdministrador); lo de
					     aqui es para que se entienda a la vista. -->
					<h5 class="mb-1" style="font-weight:600;">Contador y/o Revisor Fiscal</h5>
					<p class="text-muted" id="ritAvisoContador" style="display:none;">
						<i class="fa fa-lock"></i> Solo la Alcaldía puede modificar estos datos.
					</p>
					<div class="row">
						<div class="col-md-3 form-group">
							<label>Cédula del contador</label>
							<input type="text" class="form-control campo-solo-admin" name="ind_Cedula_contador" id="rit_ind_Cedula_contador" maxlength="20">
						</div>
						<div class="col-md-3 form-group">
							<label>Nombres y Apellidos del Contador</label>
							<input type="text" class="form-control campo-solo-admin" name="ind_Nombre_contador" id="rit_ind_Nombre_contador" maxlength="100">
						</div>
						<div class="col-md-3 form-group">
							<label>Tarjeta profesional</label>
							<input type="text" class="form-control campo-solo-admin" name="ind_Tarjeta_profesional" id="rit_ind_Tarjeta_profesional" maxlength="50">
						</div>
						<div class="col-md-3 form-group">
							<label>Correo del contador</label>
							<input type="email" class="form-control campo-solo-admin" name="ind_EmailContador" id="rit_ind_EmailContador" maxlength="150">
						</div>
					</div>
					<div class="row">
						<div class="col-md-3 form-group">
							<label>Cédula del revisor fiscal</label>
							<input type="text" class="form-control campo-solo-admin" name="ind_Cedula_revisor" id="rit_ind_Cedula_revisor" maxlength="20">
						</div>
						<div class="col-md-3 form-group">
							<label>Nombres y Apellidos del Revisor Fiscal</label>
							<input type="text" class="form-control campo-solo-admin" name="ind_Nombre_revisor" id="rit_ind_Nombre_revisor" maxlength="100">
						</div>
						<div class="col-md-3 form-group">
							<label>Tarjeta profesional</label>
							<input type="text" class="form-control campo-solo-admin" name="ind_Tarjeta_profesional_revisor" id="rit_ind_Tarjeta_profesional_revisor" maxlength="50">
						</div>
						<div class="col-md-3 form-group">
							<label>Correo del revisor fiscal</label>
							<input type="email" class="form-control campo-solo-admin" name="ind_EmailRevisor" id="rit_ind_EmailRevisor" maxlength="150">
						</div>
					</div>

					<hr>
					<!--
					     Puntos 6 y 11 (reunion 2026-08-18): los codigos del RUT son de la
					     PERSONA, no de cada local. La migracion 005 los subio a
					     ind_contribuyentes (ind_Rut, ind_Rut_segundo, ind_Rut_tercero);
					     antes estaban copiados en cada establecimiento sin nada que
					     garantizara que las copias coincidieran.
					-->
					<!-- Rotulo tal como lo pidio el cliente el 2026-08-19: quiere que se vea que
     es la informacion que trae el RUT, y ademas que son codigos CIIU. Antes
     decia solo "Informacion del RUT"; el 18 se renombro a "Codigos CIIU
     Actividades economicas" y ahora pidio las dos cosas juntas.

     Ojo, esto NO es la tabla de actividades que liquida el impuesto: esa va
     mas abajo y sale del catalogo del municipio. Estas tres casillas son
     texto libre con lo que dice el RUT de la DIAN. -->
					<h5 class="mb-3" style="font-weight:600;">Información del RUT - Códigos CIIU Actividades económicas</h5>
					<div class="row">
						<div class="col-md-3 form-group">
							<label>Código actividad principal <small class="text-muted">(RUT)</small></label>
							<input type="text" class="form-control" name="ind_Rut" id="rit_ind_Rut" maxlength="6">
						</div>
						<div class="col-md-3 form-group">
							<label>Código actividad secundaria</label>
							<input type="text" class="form-control" name="ind_Rut_segundo" id="rit_ind_Rut_segundo" maxlength="6">
						</div>
						<div class="col-md-3 form-group">
							<label>Otra actividad</label>
							<input type="text" class="form-control" name="ind_Rut_tercero" id="rit_ind_Rut_tercero" maxlength="6">
						</div>
					</div>

					<hr>
					<!--
					     Punto 11: las actividades economicas se registran AQUI, no en el
					     establecimiento. Desde la migracion 005 pertenecen al
					     contribuyente (ind_actividad_contribuyente), que es como el
					     negocio ya las usaba: la declaracion es una por contribuyente y
					     agrega por codigo CIIU.

					     Se guardan con su propio boton (funcion 8) y no con el resto del
					     formulario: son filas de otra tabla, no campos del contribuyente.
					-->
					<div class="d-flex justify-content-between align-items-center flex-wrap mb-3" style="gap:10px;">
						<h5 class="mb-0" style="font-weight:600;">Actividades económicas</h5>
						<!--
						     Sin selector de año (migracion 007): estas son las actividades
						     VIGENTES del contribuyente. El historico por periodo lo guarda
						     cada declaracion, que copia las suyas al liquidar.
						-->
						<div class="d-flex align-items-center" style="gap:8px;">
							<select class="form-control form-control-sm" id="ritCatalogoActividades" style="min-width:320px;">
								<option value="">Agregar actividad…</option>
							</select>
							<button type="button" class="btn btn-sm btn-outline-success" id="btnAgregarActividadRIT">
								<span class="ti-plus"></span> Agregar
							</button>
							<button type="button" class="btn btn-sm btn-primary" id="btnGuardarActividadesRIT">
								Guardar actividades
							</button>
						</div>
					</div>
					<div class="table-responsive">
						<table class="table table-bordered table-sm">
							<thead style="background:#e9ecef; font-weight:600;">
								<tr>
									<th style="width:110px;">Código</th>
									<th>Descripción</th>
									<th style="width:90px;">Tarifa</th>
									<th style="width:70px;"></th>
								</tr>
							</thead>
							<tbody id="tbodyActividadesRIT"></tbody>
						</table>
					</div>


					<hr>
					<!--
					     El cliente pidio que sin esta casilla NO se pueda actualizar el RIT.
					     Vivia en el formulario de cada establecimiento, donde se repetia por
					     local sin sentido: es una manifestacion de la PERSONA. La migracion
					     007 la subio al contribuyente (ind_Autorizacion).

					     El required de aqui es comodidad; quien de verdad lo exige es
					     _guardarRIT() en el servidor, porque un required se quita desde la
					     consola del navegador.
					-->
					<h5 class="mb-3" style="font-weight:600;">Autorización de notificación</h5>
					<div class="form-check mb-2">
						<input type="checkbox" class="form-check-input" value="1"
						       name="ind_Autorizacion" id="rit_ind_Autorizacion" required>
						<label class="form-check-label" for="rit_ind_Autorizacion" style="margin-left:4px;">
							Autorizo que la Secretaría de Hacienda del municipio de
							<?php echo defined('MUNICIPIO_NOMBRE') ? str_replace('Alcaldía de ', '', MUNICIPIO_NOMBRE) : 'Paipa'; ?>
							notifique los actos administrativos en materia de impuestos al correo
							electrónico registrado en esta plataforma.
						</label>
					</div>
					<small class="text-muted">Debe autorizarla para poder guardar los cambios.</small>

					<!-- Las firmas, al pie, como en el formulario impreso: el cliente
					     pidio que "en la parte de abajo se visualicen como las firmas".
					     Se llena desde consultarFirmaRIT(). -->
					<div id="ritBloqueFirmas" style="display:none;">
						<hr>
						<h5 class="mb-3" style="font-weight:600;">Firmas</h5>
						<div class="row">
							<div class="col-md-6">
								<div style="border:1px solid #dee2e6; border-radius:4px; padding:14px; min-height:110px;">
									<small class="text-muted d-block mb-2">30. Contribuyente o Representante Legal</small>
									<div id="ritFirmaContribuyente" class="text-center"></div>
								</div>
							</div>
							<div class="col-md-6">
								<div style="border:1px solid #dee2e6; border-radius:4px; padding:14px; min-height:110px;">
									<small class="text-muted d-block mb-2">31. Firma del Funcionario</small>
									<div class="text-center">
										<img src="../extensiones/tcpdf/pdf/img/firma_rit.png" style="height:46px;">
									</div>
								</div>
							</div>
						</div>
					</div>

					</form>
			</div>
		</div>
    

		<!--
		  Aqui vivian el modal de "Establecimiento/RIT", "Crear Declaración",
		  "Consultar Declaraciones", "Cese de Actividades" e "Información del
		  Contribuyente". Los disparaba la tabla de establecimientos que esta
		  pagina tenia antes de convertirse en el formulario del RIT (ver el
		  commit que hizo ese cambio); al quitar la tabla, sus botones
		  desaparecieron con ella y estos cinco modales quedaron sin nada que
		  los abriera -mas de 1200 lineas de marcado muerto-. Cada uno tiene su
		  version viva y alcanzable en otra pantalla (Establecimientos,
		  Presentar/Consultar declaraciones), asi que no se perdio ninguna
		  funcionalidad al quitarlos de aqui.
		-->
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
		<script src="../core/geografia.js?v=<?php echo time(); ?>"></script>
		<!-- El modal de firma (FirmaOTP) vive en declaraciones.ui.js. Se carga
		     aqui para que el RIT use EXACTAMENTE la misma ventana que las
		     declaraciones, como lo pidio el cliente. Va ANTES de icaWebRit.js,
		     que es quien lo llama. -->
		<script src="../core/declaraciones.ui.js?v=<?php echo time(); ?>"></script>
		<script src="../core/icaWebRit.js?v=<?php echo time(); ?>"></script>

		<!-- ===================== FIRMA DIGITAL =====================
		     La MISMA ventana que usan las declaraciones. El cliente pidio
		     expresamente que la firma del RIT no abriera una distinta.

		     El HTML esta copiado tal cual de icaWebConsultar/icaWebPresentar
		     -las tres pantallas comparten los mismos ids, que es lo que
		     FirmaOTP (core/declaraciones.ui.js) espera encontrar-.
		-->
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


<script>
$(document).ready(function () {
    swal({
        icon: 'info',
        title: 'Actualización requerida',
        text: 'Se requiere realizar la actualización de datos del establecimiento y la carga de los respectivos documentos para poder continuar con el proceso de declaraciones.',
        button: 'Entendido',
        closeOnClickOutside: false,
        closeOnEsc: false
    });
});
</script>



</body>
</html>