<?php
    require_once '../business/globals.php';
    include_once('../business/class.sessions.php');
  
?>
<!DOCTYPE html>
<html>
<head>
    <!-- Basic Page Info -->
    <meta charset="utf-8">
    <title>Inicio | DS-POS</title>

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

    <!-- Global site tag (gtag.js) - Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=UA-119386393-1"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'UA-119386393-1');
    </script>

    <style>
        /* ===========================
           ESTILOS PARA ACCESO ALCALDÍA
           =========================== */
        /* color base y borde */
        #btnAccesoAlcaldia.btn-outline-primary {
            color: #40ACC3 !important;
            border-color: #40ACC3 !important;
        }
        /* estado hover/focus/active */
        #btnAccesoAlcaldia.btn-outline-primary:hover,
        #btnAccesoAlcaldia.btn-outline-primary:focus,
        #btnAccesoAlcaldia.btn-outline-primary:active {
            background-color: #40ACC3 !important;
            border-color:     #40ACC3 !important;
            color:            #fff    !important;
        }

        /* ===========================
           ESTILOS PARA BOTONES DE OPCIONES (exogena, predial, etc)
           =========================== */
    

        /* 1) Haz que el contenedor interno ocupe todo el ancho de la columna */
        .card-box.widget-style1 .pd-20.d-flex.justify-content-between {
            width: 100% !important;
        }

        /* 2) Forza a que el botón llene ese contenedor por completo */
        .card-box.widget-style1 .btn {
            width: 100% !important;
            height: 100% !important;
            display: flex !important;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        /* ===========================
           NUEVO: Fondo con foto + degradado en el ENCABEZADO
           =========================== */
        .header-con-imagen {
            /* 1) El degradado semitransparente evita que la foto interfiera con el texto y el logo */
            background-image:
                linear-gradient(
                    to bottom,
                    rgba(255,255,255,0.5),
                    rgba(255,255,255,0.5)
                ),
                url('../vendors/images/paipa.jpg'); /* <-- reemplaza esta ruta */
            background-size: cover;       /* cubre todo el contenedor */
            background-position: center;  /* centra la imagen */
            position: relative;           /* permite ubicar elementos absolutamente sobre este fondo */
        }
        /* Es opcional: si quieres un borde redondeado o sombra similar a .card-box original */
        .header-con-imagen {
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .card-box.widget-style1 .btn-outline-success {
            border: none !important;
            background: transparent !important;
            color: inherit !important;
        }
        /* ————————————————————————
        2) Pintar el fondo de la tarjeta de verde suave
        ———————————————————————— */
        .card-box.widget-style1 {
            border: none !important;
            /* background-color: #40ACC3 !important;  usa el mismo verde */
            background-color: #D0EEF5 !important;

        }


        .footer-seccion {
            padding: 1rem;
            border: 1px solid #ccc;
            border-radius: 4px;
            background-color: #fff;
            margin-bottom: 1rem;
        }
        .footer-seccion h5 {
            margin-bottom: .75rem;
            font-weight: 600;
            font-size: 1rem;
        }
        .footer-seccion p,
        .footer-seccion ul {
            margin: 0;
            padding: 0;
            list-style: none;
            font-size: .9rem;
        }
        .footer-seccion ul li {
            margin-bottom: .5rem;
        }
        .footer-seccion ul li a {
            color: #007bff;
            text-decoration: none;
        }
        .footer-seccion ul li a:hover {
            text-decoration: underline;
        }
    </style>
    
</head>
<body>
    <div>
        <div class="pd-ltr-20">
            <!--
              Hemos agregado la clase "header-con-imagen" en lugar de "card-box pd-20 height-100-p mb-30 position-relative".
              De este modo, el fondo (foto + degradado) queda en esa sección.
            -->
            <div class="card-box pd-20 height-100-p mb-30 position-relative header-con-imagen">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <!-- Logo de la Alcaldía permanece tal cual -->
                        <img src="../vendors/images/banner-img.png" alt="">
                    </div>
                    <div class="col-md-8">
                        <h4 class="font-20 weight-500 mb-30">
                            Secretaria de Hacienda
                            <div class="weight-600 font-10 text-blue">
                                <!-- Texto de bienvenida -->
                                Dirección de Impuestos, Rentas y Juridicción Coactiva
                            </div>
                        </h4>
                        <p class="font-18 max-width-600"></p>
                    </div>
                </div>

                <!-- Botón "Acceso Alcaldía" sobre la foto y degradado -->
                <a
                    id="btnAccesoAlcaldia"
                    href="https://sistema.erpsoftsas.com/predial"
                    target="_blank"
                    class="btn btn-outline-primary position-absolute"
                    style="top: 1rem; right: 1rem;"
                >
                    Acceso Alcaldía
                </a>
            </div>

            <div class="row">
                <div class="col-xl-3 mb-30">
                    <div class="card-box height-100-p widget-style1">
                        <div class="d-flex flex-wrap align-items-center">
                            <div class="progress-data">
                                <div id="chart"></div>
                            </div>
                            <div class="pd-20 d-flex justify-content-between">
                                <button
                                    type="button"
                                    class="btn btn-outline-success d-flex flex-column align-items-center w-100 py-3"
                                    onclick="window.open('https://psepaipa.erpsoftsas.com/', '_blank')"
                                >
                                    <img
                                        src="../vendors/images/industriaComercio.jpg"
                                        alt="Impuesto Predial"
                                        style="width:50px; height:50px;"
                                        class="mb-2"
                                    >
                                    <span>IMPUESTO PREDIAL</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 mb-30">
                    <div class="card-box height-100-p widget-style1">
                        <div class="d-flex flex-wrap align-items-center">
                            <div class="pd-20 d-flex justify-content-between">
                                <button
                                    type="button"
                                    class="btn btn-outline-success d-flex flex-column align-items-center w-100 py-3"
                                    onclick="window.open('https://sistema.erpsoftsas.com/predial', '_blank')"
                                >
                                    <img
                                        src="../vendors/images/informacionExogena.jpg"
                                        alt="INFORMACIÓN EXOGENA"
                                        style="width:50px; height:50px;"
                                        class="mb-2"
                                    >
                                    <span>INFORMACIÓN EXÓGENA</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 mb-30">
                    <div class="card-box height-100-p widget-style1">
                        <div class="d-flex flex-wrap align-items-center">
                            <div class="progress-data">
                                <div id="chart2"></div>
                            </div>
                            <div class="pd-20 d-flex justify-content-between">
                                <button
                                    type="button"
                                    class="btn btn-outline-success d-flex flex-column align-items-center w-100 py-3"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalEnDesarrollo"
                                >
                                    <img
                                        src="../vendors/images/impuestoPredial.jpg"
                                        alt="IMPUESTO DE INDUSTRIA Y COMERCIO"
                                        style="width:50px; height:50px;"
                                        class="mb-2"
                                    >
                                    <span>IMPUESTO DE INDUSTRIA Y COMERCIO</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 mb-30">
                    <div class="card-box height-100-p widget-style1">
                        <div class="d-flex flex-wrap align-items-center">
                            <div class="progress-data">
                                <div id="chart4"></div>
                            </div>
                            <div class="pd-20 d-flex justify-content-between">
                                <button
                                    type="button"
                                    class="btn btn-outline-success d-flex flex-column align-items-center w-100 py-3"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalEnDesarrollo"
                                >
                                    <img
                                        src="../vendors/images/estampillas.jpg"
                                        alt="ESTAMPILLAS"
                                        style="width:50px; height:50px;"
                                        class="mb-2"
                                    >
                                    <span>ESTAMPILLAS</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
  <!-- PIE: Secciones Contacto y Normatividad -->
            <div class="row">
                <!-- Contacto -->
                <div class="col-md-6">
                    <div class="footer-seccion">
                        <h5>CONTACTO</h5>
                        <p>
                            Secretaría de Hacienda – Dirección de Impuestos, Rentas y Jurisdicción Coactiva<br>
                            Teléfono: <a href="tel:3185309285">318 530 9285</a><br>
                            Correo: <a href="mailto:impuestos@paipa-boyaca.gov.co">impuestos@paipa-boyaca.gov.co</a><br>
                            Dirección: Carrera 22 # 25-14, primer piso
                        </p>
                    </div>
                </div>
                <!-- Normatividad -->
                <div class="col-md-6">
                    <div class="footer-seccion">
                        <h5>NORMATIVIDAD</h5>
                        <ul>
                            <li><a href="https://www.paipa-boyaca.gov.co/Transparencia/Normatividad/Acuerdo%20019%20de%202022.pdf" target="_blank">Estatuto de Rentas Municipal</a></li>
                            <li><a href="https://www.paipa-boyaca.gov.co/Transparencia/Normatividad/Acuerdo%20018%20de%202024.pdf" target="_blank">Régimen Sancionatorio</a></li>
                            <li><a href="https://www.paipa-boyaca.gov.co/Transparencia/Normatividad/Resolucion%20122-2143%20de%202024.pdf" target="_blank">Calendario Tributario 2025</a></li>
                            <li><a href="https://www.paipa-boyaca.gov.co/Transparencia/Normatividad/Resolucion%20122-155%20de%202024.pdf" target="_blank">Información Exógena Resolución 122-0155</a></li>
                            <li><a href="https://www.paipa-boyaca.gov.co/Transparencia/Normatividad/RESOLUCI%C3%93N%20122-0090%20DE%202025.pdf" target="_blank">Información Exógena Resolución 122-0090</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- FIN PIE -->

            <!-- Enlace ERPSOFTSAS -->

                <div class="col-12 text-center mt-3 mb-4">
                    <a href="https://ERPSOFTSAS.com" target="_blank">ERPSOFTSAS</a> - 2025
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalEnDesarrollo" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalLabel">Módulo en desarrollo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    Este módulo aún se encuentra en desarrollo. Próximamente estará disponible.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- js -->
    <script src="../vendors/scripts/core.js"></script>
    <script src="../vendors/scripts/script.min.js"></script>
    <script src="../vendors/scripts/process.js"></script>
    <script src="../vendors/scripts/layout-settings.js"></script>
    <script src="../src/plugins/apexcharts/apexcharts.min.js"></script>
    <script src="../src/plugins/datatables/js/jquery.dataTables.min.js"></script>
    <script src="../src/plugins/datatables/js/dataTables.bootstrap4.min.js"></script>
    <script src="../src/plugins/datatables/js/dataTables.responsive.min.js"></script>
    <script src="../src/plugins/datatables/js/responsive.bootstrap4.min.js"></script>
    <script src="../vendors/scripts/dashboard.js"></script>
    <script src="../core/menu.js"></script>
    <script src="../core/Permisos.js"></script>
    <script src="../core/datosVisuales.js"></script>

    <!-- CSS de Bootstrap (opcional, si no está ya cargado) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- JS de Bootstrap (opcional, si no está ya cargado) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
