<!DOCTYPE html>
<html>
<head>
	<!-- Basic Page Info -->
	<meta charset="utf-8">
	<title>ERPSOFTSAS</title>

	<!-- Site favicon -->
	<link rel="apple-touch-icon" sizes="180x180" href="vendors/images/apple-touch-icon.png">
	<link rel="icon" type="image/png" sizes="32x32" href="vendors/images/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="vendors/images/favicon-16x16.png">

	<!-- Mobile Specific Metas -->
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">

	<!-- Google Font -->
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
	<!-- CSS -->
	<link rel="stylesheet" type="text/css" href="vendors/styles/core.css">
	<link rel="stylesheet" type="text/css" href="vendors/styles/icon-font.min.css">
	<link rel="stylesheet" type="text/css" href="vendors/styles/style.css">

	<link rel="stylesheet" type="text/css" href="src/plugins/sweetalert2/sweetalert2.css">

	<!-- Global site tag (gtag.js) - Google Analytics -->
	<script async src="https://www.googletagmanager.com/gtag/js?id=UA-119386393-1"></script>
	<script>
		window.dataLayer = window.dataLayer || [];
		function gtag(){dataLayer.push(arguments);}
		gtag('js', new Date());

		gtag('config', 'UA-119386393-1');
	</script>
</head>
<body class="login-page">
	<div class="login-header box-shadow">
		<div class="container-fluid d-flex justify-content-between align-items-center">
			<div class="brand-logo">
				<a href="index.php">
					<img src="vendors/images/deskapp-logo-white.png" alt="">
				</a>
			</div>
			<div class="login-menu">
				<!-- <ul>
					<li><a href="register.html">Register</a></li>
				</ul> -->
			</div>
		</div>
	</div>
	<div class="login-wrap d-flex align-items-center flex-wrap justify-content-center">
		<div class="container">
			<div class="row align-items-center">
				<div class="col-md-6 col-lg-7">
					<img src="vendors/images/login-page-img.png" alt="">
				</div>
				<div class="col-md-6 col-lg-5">
					<div class="login-box bg-white box-shadow border-radius-10">
						<div class="login-title">
							<h2 class="text-center text-primary">Iniciar de Sesión <br>Modulo Industria y Comercio</h2>
						</div>
						<form action="javascript:login.init();">
							<div class="select-role">
							</div>
							<div class="input-group custom">
								<input type="text" class="form-control form-control-lg" id="email" placeholder="Username" required>
								<div class="input-group-append custom">
									<span class="input-group-text"><i class="icon-copy dw dw-user1"></i></span>
								</div>
							</div>
							<div class="input-group custom">
								<input type="password" class="form-control form-control-lg" id="password" placeholder="**********" required>
								<div class="input-group-append custom">
									<span class="input-group-text"><i class="dw dw-padlock1"></i></span>
								</div>
							</div>
							<div class="row pb-30">
								<!-- <div class="col-6">
									<div class="custom-control custom-checkbox">
										<input type="checkbox" class="custom-control-input" id="customCheck1">
										<label class="custom-control-label" for="customCheck1">Remember</label>
									</div>
								</div> -->
								<!--
								<div class="col-12">
									<div class="forgot-password"><a href="forgot-password.html">¿Olvidó su contraseña?</a></div>
								</div>-->
							</div>
							<div class="row">
								<div class="col-sm-12">
									<div class="input-group mb-0">
										<!--
											use code for form submit
											<input class="btn btn-primary btn-lg btn-block" type="submit" value="Sign In">
										-->
										<button type="submit" class="btn btn-primary btn-lg btn-block" href="index.html">Iniciar sesión</button>
									</div>
										</br>
									<div class="text-center"> 
										<a href="https://erpsoftsas.com" target="_blank">ERPSOFT S.A.S</a></br>
									</div>
									<!-- <div class="font-16 weight-600 pt-10 pb-10 text-center" data-color="#707373">OR</div>
									<div class="input-group mb-0">
										<a class="btn btn-outline-primary btn-lg btn-block" href="register.html">Register To Create Account</a>
									</div> -->
								</div>
							</div>
						</form>

						<div class="text-center mt-3">
							<button type="button" class="btn btn-link text-primary p-0" onclick="login.crearUsuario()">
								¿Soy un nuev@ usuario Inscribirse?
							</button><br>
							<button type="button" class="btn btn-link text-primary p-0" onclick="login.RecuperarUsuario()">
								¿Has olvidado tu Contraseña?
							</button><br>
							<a href="https://erpsoftsas.com" target="_blank">ERPSOFTSAS</a> - V.1
						</div>

					</div>
				</div>
			</div>
		</div>
	</div>

	
  <!-- Modal Crear Usuario -->
  <div class="modal fade" id="modal-Usuario" tabindex="-1" role="dialog" aria-labelledby="exampleModalFormTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Creación de Usuario</h5>
          <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <form id="formCrearUsuario" onsubmit="login.postUsuario(); return false;">
          <div class="modal-body">
            <div class="form-row">

				<div class="form-group col-md-6">
					<label>* Tipo Persona</label>
					<select class="form-control" style="width: 100%;"
						id="usu_IdTipoPersona" name="usu_IdTipoPersona" required>
						<option value="">Seleccione Tipo Persona</option>
						<option value="1">Natural</option>
						<option value="2">Jurídica</option>
					</select>
				</div>

				<div class="form-group col-md-6">
					<label>* Tipo Documento</label>
					<select class="form-control" style="width: 100%;"
						id="usu_IdTipoDocumento" name="usu_IdTipoDocumento" required>
						<option value="">Seleccione Tipo Documento</option>
						<option value="1">Cédula de Ciudadanía</option>
						<option value="5">NIT</option>
						<option value="3">Cédula de Extranjería</option>
						<option value="4">Pasaporte</option>
					</select>
				</div>

				<div class="form-group col-md-4">
					<label id="labelDocumento">* Documento</label>
					<input type="number" class="form-control" id="usu_Documento" required>
				</div>

				<div class="form-group col-md-2">
					<label>* DV</label>
					<input type="text" class="form-control" id="usu_DV" disabled>
				</div>
					
				<div class="form-group col-md-8" id="grupoNombres">
					<label id="labelNombres">* Nombres</label>
					<input type="text" class="form-control" id="usu_Nombres" required>
				</div>
				<div class="form-group col-md-6"  id="grupoApellidos">
					<label>* Apellidos</label>
					<input type="text" class="form-control" id="usu_Apellidos" required>
				</div>

				<div class="form-group col-md-6">
					<label>* Correo</label>
					<input type="email" class="form-control" id="usu_Correo" required>
				</div>
				<div class="form-group col-md-6">
					<label>* Telefono</label>
					<input type="text" class="form-control" id="usu_Telefono" required>
				</div>

				<div class="form-group col-md-12">
					<label>* Dirección</label>
					<input type="text" class="form-control" id="usu_Direccion" required>
				</div>

				<div class="form-group col-md-4">
					<label>* Usuario</label>
					<input type="text" class="form-control" id="usu_Usuario" required>
				</div>	
				<div class="form-group col-md-4">
					<label>* Clave</label>
					<input type="password" class="form-control" id="usu_Clave" required>
				</div>	
				<div class="form-group col-md-4" id="passwordHelp" class="mt-2" style="font-size: 13px;">
					<div id="req-length" class="text-danger">• Mínimo 8 caracteres</div>
					<div id="req-upper" class="text-danger">• Al menos una mayúscula</div>
					<div id="req-lower" class="text-danger">• Al menos una minúscula</div>
					<div id="req-number" class="text-danger">• Al menos un número</div>
				</div>
			  
				<div class="form-group col-md-6">
            	    <input type="checkbox" id="con_Activo" name="con_Activo" data-toggle="switch" required>
        	        <label>Acepto tratamiento de datos personales</label><br>
				</div>

            </div>
           
           
           
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-danger btn-pill" data-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-success btn-pill" id="btnCrearUsuario">Inscribirse</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal Crear Usuario -->
  <div class="modal fade" id="modal-RecuperarUsuario" tabindex="-1" role="dialog" aria-labelledby="exampleModalFormTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Recuperar Usuario</h5>
          <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <form id="formRecuperarUsuario" onsubmit="login.postRecuperarUsuario(); return false;">
          
			<div class="modal-body">
				<div class="form-row justify-content-center">
					<div class="form-group col-md-8 col-lg-6 text-center">

						<label class="font-weight-bold">* Correo</label>

						<input type="email"
							class="form-control text-center"
							id="usu_CorreoRecuperar"
							required
							placeholder="Ingrese su correo electrónico">

						<small class="form-text text-muted mt-2">
							Te enviaremos un correo con las instrucciones de recuperación.
						</small>

					</div>
				</div>
			</div>

          <div class="modal-footer">
            <button type="button" class="btn btn-danger btn-pill" data-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-success btn-pill" id="btnRecuperarUsuario">Enviar</button>
          </div>
        </form>
      </div>
    </div>
  </div>

	<!-- js -->
	<script src="vendors/scripts/core.js"></script>
	<script src="vendors/scripts/script.min.js"></script>
	<script src="vendors/scripts/process.js"></script>
	<script src="vendors/scripts/layout-settings.js"></script>
	<script src="src/scripts/jquery.min.js"></script>
	<script src="src/plugins/sweetalert2/sweetalert2.all.js"></script>
	<script src="login.js?v=<?php echo time(); ?>"></script>
	
	<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>


</body>
</html>