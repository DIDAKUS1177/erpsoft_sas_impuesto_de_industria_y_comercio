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

  <!-- Override primary-blue with #40ACC3 -->
  <style>
    /* btn-primary */
    .btn-primary {
      background-color: #40ACC3 !important;
      border-color:     #40ACC3 !important;
    }
    .btn-primary:hover,
    .btn-primary:focus {
      background-color: #359aa5 !important;
      border-color:     #359aa5 !important;
    }

    /* text-primary */
    .text-primary {
      color: #40ACC3 !important;
    }

    /* Modal: btn-success and btn-danger */
    .modal .btn-success {
      background-color: #40ACC3 !important;
      border-color:     #40ACC3 !important;
      color:            #fff    !important;
    }
    .modal .btn-success:hover,
    .modal .btn-success:focus {
      background-color: #359aa5 !important;
      border-color:     #359aa5 !important;
    }
    .modal .btn-danger {
      background-color: #40ACC3 !important;
      border-color:     #40ACC3 !important;
      color:            #fff    !important;
    }
    .modal .btn-danger:hover,
    .modal .btn-danger:focus {
      background-color: #359aa5 !important;
      border-color:     #359aa5 !important;
    }
  </style>

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
          <img src="vendors/images/deskapp-logo.svg" alt="">
        </a>
      </div>
      <div class="login-menu">
      </div>
    </div>
  </div>

  <div class="login-wrap d-flex align-items-center flex-wrap justify-content-center">
    <div class="container">
      <div class="row align-items-center">

        <div class="col-md-6 col-lg-12">
          <div class="login-box bg-white box-shadow border-radius-10">
            <div class="login-title">
              <h2 class="text-center text-primary">Iniciar sesión<br></h2>
            </div>
            <form action="javascript:login.init();">
              <div class="select-role"></div>
              <div class="input-group custom">
                <input type="text" class="form-control form-control-lg" id="email" placeholder="Username" required>
                <div class="input-group-append custom">
                  <span class="input-group-text"><i class="icon-copy dw dw-user1"></i></span>
                </div>
              </div>
              <div class="input-group custom mt-3">
                <input type="password" class="form-control form-control-lg" id="password" placeholder="**********" required>
                <div class="input-group-append custom">
                  <span class="input-group-text"><i class="dw dw-padlock1"></i></span>
                </div>
              </div>
              <div class="row mt-4">
                <div class="col-sm-12">
                  <button type="submit" class="btn btn-primary btn-lg btn-block">Iniciar sesión</button>
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
          <h5 class="modal-title">Crear Usuario</h5>
          <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <form id="formCrearUsuario" onsubmit="login.postUsuario(); return false;">
          <div class="modal-body">
            <div class="form-row">
              <div class="form-group col-md-6">
                <label>* Nombre</label>
                <input type="text" class="form-control" id="usu_Nombre" required>
              </div>
              <div class="form-group col-md-6">
                <label>* Documento</label>
                <input type="number" class="form-control" id="usu_Documento" required>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group col-md-6">
                <label>* Correo</label>
                <input type="email" class="form-control" id="usu_Correo" required>
              </div>
              <div class="form-group col-md-6">
                <label>* Clave</label>
                <input type="password" class="form-control" id="usu_Clave" required>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group col-md-6">
                <label>* Usuario</label>
                <input type="text" class="form-control" id="usu_Usuario" required>
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
            <div class="form-row">
              <div class="form-group col-md-6">
                <label>* Correo</label>
                <input type="email" class="form-control" id="usu_CorreoRecuperar" required>
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

  <!-- ============================
       Scripts: ¡El orden importa!
  ============================ -->
  <!-- 1) jQuery -->
  <script src="src/scripts/jquery.min.js"></script>
  <!-- 2) Bootstrap Bundle (Popper + JS del modal) -->
  <script
    src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2LcLhKZW1LYatGEz8Y1USpaS7RSvoRxT2MZw1T"
    crossorigin="anonymous"
  ></script>
  <!-- 3) DataTables -->
  <script src="src/plugins/datatables/js/jquery.dataTables.min.js"></script>
  <script src="src/plugins/datatables/js/dataTables.bootstrap4.min.js"></script>
  <script src="src/plugins/datatables/js/dataTables.responsive.min.js"></script>
  <script src="src/plugins/datatables/js/responsive.bootstrap4.min.js"></script>
  <!-- 4) Switchery & SweetAlert2 -->
  <script src="src/plugins/switchery/switchery.min.js"></script>
  <script src="src/plugins/sweetalert2/sweetalert2.all.js"></script>
  <!-- 5) Vendor Scripts -->
  <script src="vendors/scripts/core.js"></script>
  <script src="vendors/scripts/script.min.js"></script>
  <script src="vendors/scripts/process.js"></script>
  <script src="vendors/scripts/layout-settings.js"></script>
  <!-- 6) Tu lógica de login -->
  <script src="login.js"></script>
</body>
</html>
