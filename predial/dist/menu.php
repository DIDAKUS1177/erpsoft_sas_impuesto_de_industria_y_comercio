<?php
    require_once '../business/globals.php';
    include_once('../business/class.sessions.php');
    try {
        \predial\SesionUsuario::verificarSesion();
    } catch (\predial\sesionException $e) {
        echo $e->getMessage();
    }
?>
<link rel="stylesheet" type="text/css" href="../src/plugins/sweetalert2/sweetalert2.css">
	<div class="pre-loader">
		<div class="pre-loader-box">
			<div class="loader-logo"><img src="../vendors/images/deskapp-logo.svg" alt=""></div>
			<div class='loader-progress' id="progress_div">
				<div class='bar' id='bar1'></div>
			</div>
			<div class='percent' id='percent1'>0%</div>
			<div class="loading-text">
				Cargando...
			</div>
		</div>
	</div>

	<div class="header">
		<div class="header-left">
			<div class="menu-icon dw dw-menu"></div>
			<div class="search-toggle-icon dw dw-search2" data-toggle="header_search"></div>
			
			<!-- <img src="../extensiones/images/hearexo.png" width="250" height="50"> -->
		
		</div>
		<div class="header-right">
			<div class="user-info-dropdown">
				<div class="dropdown">
					<a class="dropdown-toggle" href="#" role="button" data-toggle="dropdown">
						<span>
							<img src="../src/images/user/svg/user.svg" alt="predial user" width="40" height="40">
						</span>
                        <span class="user-name" id="NomUsu" style="font-size: 13px;"></span>
					</a>
					<div class="dropdown-menu dropdown-menu-right dropdown-menu-icon-list">
						<a class="dropdown-item" href="javascript:void(0)" id="btnCerrarSesion"><i class="dw dw-logout" ></i>Cerrar Sesión </a>
					</div>
				</div>
			</div>
		</div>
	</div>

	
 
	<div class="left-side-bar">
		<div class="brand-logo">
			<a href="dashboard.php">
				<img src="../vendors/images/deskapp-logo.svg" alt="" class="dark-logo">
				<img src="../vendors/images/deskapp-logo-white.svg" alt="" class="light-logo">
			</a>
			<div class="close-sidebar" data-toggle="left-sidebar-close">
				<i class="ion-close-round"></i>
			</div>
		</div>
		<div class="menu-block customscroll">
			<div class="sidebar-menu">
				<ul id="accordion-menu">
<!--  PROCESO MANDAMIENTOS DE PAGO - DICIMEBRE 2023

					<li class="dropdown" id="DPredial">
						<a href="javascript:;" class="dropdown-toggle">
							<span class="micon dw dw-invoice-1"></span><span class="mtext">PREDIAL</span>
                        </a>
                        <ul class="submenu" id="SPredial">
							<li><a id="SubMenuLisPredial" href="javascript:void(0)" onclick="menu.validarIngreso(622,25)">Listado Predios</a></li>	
							<li><a id="SubMenuPreDoc" href="javascript:void(0)" onclick="menu.validarIngreso(622,26)">Predios con Documento</a></li>	
						</ul>
					</li>
-->

<!--  MODULO DE GESTION DE COBRO  -  DICIMEBRE 2024 
					<li class="dropdown" id="DMorosos">
						<a href="javascript:;" class="dropdown-toggle">
							<span class="micon dw dw-invoice-1"></span><span class="mtext">GESTIÓN DE COBRO</span>
                        </a>
                        <ul class="submenu" id="SMorosos">
							<li><a id="SubMenuLiquidacionListado" href="javascript:void(0)" onclick="menu.validarIngreso(622,52)">Listado de Morosos</a></li>	
							<li><a id="SubMenuLiquidacionPro" href="javascript:void(0)" onclick="menu.validarIngreso(622,51)">Proceso de Fiscalización</a></li>	
							<li><a id="SubMenuHojadeVida" href="javascript:void(0)" onclick="menu.validarIngreso(622,54)">Hoja de Vida</a></li>
						</ul>
					</li>
-->

<!--   CONFIGURACIÓNES PARA EL MODULO DE GESION DE COBOR Y PREDIAL 

					<li class="dropdown" id="DConfiguracion">
						<a href="javascript:;" class="dropdown-toggle">
							<span class="micon dw dw-invoice-1"></span><span class="mtext">CONFIGURACION</span>
                        </a>
                        <ul class="submenu" id="SubConfiguracion">
							<li><a id="SubMenuDirector" href="javascript:void(0)" onclick="menu.validarIngreso(622,29)">Director</a></li>	
							<li><a id="SubMenuUsuario" href="javascript:void(0)" onclick="menu.validarIngreso(26,1)">Usuarios</a></li>
						</ul>				
					</li>
					
					<li class="dropdown" id="DInformes">
						<a href="javascript:;" class="dropdown-toggle">
							<span class="micon dw dw-apartment"></span><span class="mtext">INFORMES</span>
						</a>
						<ul class="submenu" id="SInformes">
                            <li><a id="SubMenuInfoFac" href="javascript:void(0)" onclick="menu.validarIngreso(622,22)">Predios</a></li>
                            <li><a id="SubMenuInfoFac" href="javascript:void(0)" onclick="menu.validarIngreso(622,49)">Morosos</a></li>
						</ul>
					</li>

-->

<!--
					<li class="dropdown" id="DRIT">
						<a href="javascript:;" class="dropdown-toggle">
							<span class="micon dw dw-invoice-1"></span><span class="mtext">RIT</span>
                        </a>
                        <ul class="submenu" id="SubConfiguracion">
							<li><a id="SubMenuDirector" href="javascript:void(0)" onclick="menu.validarIngreso(622,100)">ACTUALIZACIÓN RIT</a></li>	
						</ul>				
					</li>

					
					<li class="dropdown" id="DIndustria">
						<a href="javascript:;" class="dropdown-toggle">
							<span class="micon dw dw-invoice-1"></span><span class="mtext">Industria y Comercio</span>
                        </a>
                        <ul class="submenu" id="SubConfiguracion">
							<li><a id="SubMenuDirector" href="javascript:void(0)" onclick="menu.validarIngreso(622,100)">Actualización Datos</a></li>	
							<li><a id="SubMenuDirector" href="javascript:void(0)" onclick="menu.validarIngreso(622,100)">Registrar Declaraciónes</a></li>	
							<li><a id="SubMenuDirector" href="javascript:void(0)" onclick="menu.validarIngreso(622,100)">Consulta por Vigencias</a></li>	
						</ul>				
					</li>
-->
					<li class="dropdown" id="DExogenas">
						<a href="javascript:;" class="dropdown-toggle">
							<span class="micon dw dw-invoice-1"></span><span class="mtext">Exogenas</span>
                        </a>
                        <ul class="submenu" id="SubExogenas">
							<li><a id="SubMenuFormatos" href="javascript:void(0)" onclick="menu.validarIngreso(622,55)">Subir Formatos</a></li>		
							<li><a id="SubVerFormatos" href="javascript:void(0)" onclick="menu.validarIngreso(622,57)">Consulta por Vigencias</a></li>	
						</ul>				
					</li>
<!--
					<li class="dropdown" id="DInformes">
						<a href="javascript:;" class="dropdown-toggle">
							<span class="micon dw dw-invoice-1"></span><span class="mtext">Informes</span>
                        </a>
                        <ul class="submenu" id="SubInformes">
							<li><a id="SubMenuDirector" href="javascript:void(0)" onclick="menu.validarIngreso(622,100)">Consulta por Vigencias</a></li>	
						</ul>				
					</li>

-->


<!--
					<li class="dropdown" id="DReteica">
						<a href="javascript:;" class="dropdown-toggle">
							<span class="micon dw dw-invoice-1"></span><span class="mtext">Reteica</span>
                        </a>
                        <ul class="submenu" id="SubConfiguracion">
							<li><a id="SubMenuDirector" href="javascript:void(0)" onclick="menu.validarIngreso(622,100)">Descargar Formatos</a></li>	
							<li><a id="SubMenuDirector" href="javascript:void(0)" onclick="menu.validarIngreso(622,100)">Subir Exogenas</a></li>	
							<li><a id="SubMenuDirector" href="javascript:void(0)" onclick="menu.validarIngreso(622,100)">Consulta por Vigencias</a></li>	
						</ul>				
					</li>

					<li class="dropdown" id="DAuto">
						<a href="javascript:;" class="dropdown-toggle">
							<span class="micon dw dw-invoice-1"></span><span class="mtext">AutoRetención</span>
                        </a>
                        <ul class="submenu" id="SubConfiguracion">
							<li><a id="SubMenuDirector" href="javascript:void(0)" onclick="menu.validarIngreso(622,100)">Descargar Formatos</a></li>	
							<li><a id="SubMenuDirector" href="javascript:void(0)" onclick="menu.validarIngreso(622,100)">Subir Exogenas</a></li>	
							<li><a id="SubMenuDirector" href="javascript:void(0)" onclick="menu.validarIngreso(622,100)">Consulta por Vigencias</a></li>	
						</ul>				
					</li>

					<li class="dropdown" id="DEstampillas">
						<a href="javascript:;" class="dropdown-toggle">
							<span class="micon dw dw-invoice-1"></span><span class="mtext">Estampillas</span>
                        </a>
                        <ul class="submenu" id="SubConfiguracion">
							<li><a id="SubMenuDirector" href="javascript:void(0)" onclick="menu.validarIngreso(622,100)">Descargar Formatos</a></li>	
							<li><a id="SubMenuDirector" href="javascript:void(0)" onclick="menu.validarIngreso(622,100)">Subir Exogenas</a></li>	
							<li><a id="SubMenuDirector" href="javascript:void(0)" onclick="menu.validarIngreso(622,100)">Consulta por Vigencias</a></li>	
						</ul>				
					</li>
--->

				</ul>
			</div>
		</div>
	</div>

<script src="../src/scripts/jquery.min.js"></script>
<script src="../core/menu.js"></script>
<script src="../core/Permisos.js"></script>
<script src="../src/plugins/sweetalert2/sweetalert2.all.js"></script>

<script>
    var NomUsu = sessionStorage.getItem('NomUsu');
    var mailUsu = sessionStorage.getItem('mailUsu');
    
    console.log('NomUsu ',NomUsu)
    $("#NomUsu").empty();
    $("#NomUsu").append(NomUsu);

    $("#mailUsu").empty();
    $("#mailUsu").append('<strong>'+ mailUsu + '</strong>');

    $("#btnCerrarSesion").click(function(){
        $.ajax({
            url:'../business/class.sessions.php',
            data:{ kill : 1},
            type: 'GET',
            success: function(json){
                window.location = '../index.php';
            }
        });
    });

</script>