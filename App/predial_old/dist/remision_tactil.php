<?php
    require_once '../business/globals.php';
    include_once('../business/class.sessions.php');
    try {
        \predial\SesionUsuario::verificarSesion();
    } catch (\predial\sesionException $e) {
        echo $e->getMessage();
    }
?>
<!DOCTYPE html>
<html>
<head>
	<!-- Basic Page Info -->
	<meta charset="utf-8">
	<title>Remisión | predial</title>

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
			<div class="card-box mb-30">
				

				<div class="pb-20">
			
					<form id="formCrearNota">
							<div class="modal-body">
								<div class="col-sm-12">
									<div class="row">

										<div class="form-group; col-sm-2" style="width: 100%">
											<label><strong>FECHA</strong></label>
											<input type="text" id="fecha_dia" size="5" style="text-align:center; border: 0" name="fecha_dia"  readonly>
											<!-- <input type="text" class="form-control" id="fecha_dia" name="fecha_dia" style="text-align: center;" readonly>  -->
										</div>

										<div class="form-group; col-sm-3" style="width: 100%">
											<label><strong>SEDE / CAJA</strong><span class="require"></span></label>
											<input type="text" id="sede" size="10" name="sede" style="text-align:center; border: 0"  readonly>
										</div>

										<div class="form-group; col-sm-2" style="width: 100%">
												<label><strong>PREFIJO</strong><span class="require"></span></label>
												<input type="text" id="prefijo" size="3" name="prefijo" style="text-align:center; border: 0"  readonly>																					
										</div>
										
										<div class="form-group; col-sm-2" style="width: 100%">
												<label><strong>N°</strong><span class="require"></span></label>
												<input type="text" size="3" id="numero" name="numero" style="text-align:center; border: 0" readonly>										
												<!-- <input type="hidden" class="form-control" id="prefijo" name="prefijo" style="text-align: center;" readonly> -->
												<input type="hidden" class="form-control" id="id_caja" name="id_caja" style="text-align: center;" readonly>										
												<input type="hidden" class="form-control" id="id_vendedor" name="id_vendedor" style="text-align: center;" readonly>										
												<input type="hidden" class="form-control" id="id_tipoDocumento" name="id_tipoDocumento" style="text-align: center;" readonly>										
										</div>
									
										<div class="form-group; col-sm-3" style="width: 100%">
												<label><strong>CLIENTE</strong><span class="require">*</span></label>
													<select class="custom-select2 form-control" style="width: 50%;"
													tabindex="-1" aria-hidden="true" id="doc_IdCliente" name="doc_IdCliente" required></select>

													<!-- Ingresar Nombre CAMPO PERSONALIZADO 
													<label><strong>ASESOR</strong><span class="require">*</span></label> -->
													
													<!-- Tipo de Campo Personalizado  
														- INPUT --> 
													<!-- <input type="text" class="form-control" id="campo_personalizado" name="campo_personalizado" style="text-align: center;">  -->
													
													<!--  LISTADO 
													<select class="custom-select2 form-control" style="width: 50%;"
													tabindex="-1" aria-hidden="true" id="campo_personalizado" name="campo_personalizado">
													<option value="andres">Andres</option>
													<option value="andrea">Andrea</option>
													</select>
													--> 
													<!--<input type="hidden" id="campo_personalizado" name="campo_personalizado" style="text-align:center">-->
										</div>
									</div>	
								</div>

<!--  SECCION DE SELECCIÓN PE PRODUCTOS Y LISTADO DE PRODUCTOS FACTURADOS     -->
								<div class="col-sm-12">
									<div class="row">
										<div role="documentt" class="col-sm-4">
											<table name="tablaDatos" id="tablaDatos" class="data-table table hover nowrap table-responsive">
												<thead>
													<tr>
														<th>Cantidad</th>
														<!--<th>Descuento</th>-->
														<th>Producto</th>
														<!--<th>Precio</th>-->
														<!--<th>Costo</th>-->
														<!--<th>Bodega</th>-->
														<!--<th>Acciones</th>-->
													</tr>
												</thead>
												<tbody id="bodyProductosTactil">
													<tr>
														<td style="width: 40%">
															<input type="text" class="form-control" id="detkar_Cantidad" name="detkar_Cantidad" value= "1" style="text-align: right;" onkeydown="nota.noPuntoComa( event )">
															<input type="hidden" class="form-control" id="detkar_Descuento" name="detkar_Descuento" value= "0" style="text-align: right;" readonly>
														</td>
														<td style="width: 45%">
															<!--<select class="custom-select2 form-control" style="width: 100%;" tabindex="-1" aria-hidden="true" id="detkar_IdProducto" name="detkar_IdProducto" onchange="nota.cargarPrecios()">
															</select>-->
															<input type="hidden" class="form-control" id="detkar_Stock" name="detkar_Stock" style="text-align: center;" value = "0">
															<input type="hidden" class="form-control" id="impuesto" name="impuesto" style="text-align: center;" readonly>										
															<input type="hidden" class="form-control" id="detkar_Costo" name="detkar_Costo" style="text-align: center;" readonly>
															<select style="width: 100%; visibility: hidden" tabindex="-1" aria-hidden="true" id="detkar_IdBodega" name="detkar_IdBodega" readonly>
															</select>
														</td>
													</tr>
												</tbody>
											</table>
										</div>

										<div role="documentt" class="col-sm-8">
											<!-- Simple Datatable start -->
											<table  name="detalleNotas" id="detalleNotas" class="data-table table table-sm hover stripe nowrap">
												<thead style="text-align:justify">
													<tr>
														<th>Producto</th>
														<th>Precio Uni</th>
														<th>Precio Total</th>
														<th>Cantidad</th>
														<!-- <th>Bodega</th>-->
														<th>Acciones</th>
													</tr>
												</thead>
												<tbody id="bodyDetallesNotas" class="col-sm-12">
													
												</tbody>
											</table>
										</div>

									</div>
								</div>
								
							</div>

							<div class="modal-pagos" id="modal_pagos">
								<div class="modal-body">
									<div class="col-sm-12">
										<div class="row">
											<div class="form-group; col-sm-3" style="width: 100%">
												<label>FORMA DE PAGO <span class="require">*</span></label>
												<select class="form-control" style="width: 100%;"
												tabindex="-1" aria-hidden="true" id="doc_IdFormaPago" name="doc_IdFormaPago" required>
												</select>
											</div>
<!--
											<div class="form-group; col-sm-3" style="width: 100%">
												<label>VALOR<span class="require"></span></label>
											    <input type="text" class="form-control" id="valor_dado" name="valor_dado" style="text-align: center;" onfocus="nota.cargarCalculadora(2,0);" required>
												<input type="text" class="form-control" id="valor_dado" name="valor_dado" value="0"  style="text-align: center;"required> 
											</div>
-->
											<div class="form-group; col-sm-2" style="width: 100%">
												<label style="text-align:center;">Descuento<span class="require"></span></label>
												<select id="descuentoPor_Dine" name="descuentoPor_Dine" onChange="nota.aplicarDescuentoTotal()"><option value="1">%</option> <option value="2">$</option></select>
												<input type="text" class="form-control" id="descuento" name="descuento" value="0" style="text-align: center;" onChange="nota.aplicarDescuentoTotal()">										
												<!-- <input type="hidden" class="form-control" id="valor_dado" name="valor_dado" value="0"  style="text-align: center;"required>  -->
											</div>
											<div class="form-group; col-sm-3" style="width: 100%">
												<label>VALOR<span class="require"></span></label>
												<input type="text" class="form-control" id="valor_dado" name="valor_dado" style="text-align: center;"required> 
											</div>
											<div class="form-group; col-sm-4" style="width: 100%">
												<div class="form-group; col-sm-12" style="width: 100%">
													<label>SUBTOTAL:  $<span class="require"></span></label>
													<input type="text" size="8"  id="subtotalocul" name="subtotalocul" style="text-align: left; border: 0" readonly>
												</div>
												<div class="form-group; col-sm-12" style="width: 100%">
													<label>BRUTO:  $<span class="require"></span></label>
													<input type="text" size="8" id="totalBrutoVisual" name="totalBrutoVisual" style="text-align: left; border: 0" readonly>
												</div>
												<div class="form-group; col-sm-12" style="width: 100%">
													<label>IMPUESTOS:  $<span class="require"></span></label>							
													<input type="text" size="8" id="totalImpuestosVisual" name="totalImpuestosVisual" style="text-align: left; border: 0" readonly>
												</div>
												<div class="form-group; col-sm-12" style="width: 100%">
													<label>DESCUENTO:  $<span class="require"></span></label>
													<input type="text" size="8"  id="descuentofacturavisual" name="descuentofacturavisual" value="0" style="text-align: left; border: 0" readonly>
												</div>
												<div class="form-group; col-sm-12" style="width: 100%">
													<label>REDONDEO:  $<span class="require"></span></label>
													<input type="text" size="8"  id="redondeo" name="redondeo" value="0" style="text-align: left; border: 0" readonly>
												</div>
											</div>
										</div>
									</div>
								</div>
								<div class="modal-body">
									<div class="col-sm-12">
										<div class="row">
											<div class="form-group; col-sm-8" style="width: 100%;text-align: center">
												<label><strong>REMISIÓN: FACTURACIÓN ELECTRONICA</strong><span class="require"></span>
											</div>

											<div class="form-group; col-sm-4" style="width: 100%">
												<label><strong>TOTAL: $</strong><span class="require"></span></label>
												<!--<input type="hidden" class="form-control" id="totalBruto" name="totalBruto" style="text-align: center;" readonly>										
												<input type="hidden" class="form-control" id="totalImpuestos" name="totalImpuestos" style="text-align: center;" readonly> -->
												<input type="hidden" id="total" name="total" style="text-align: left; border: 0" readonly>										
												<input type="hidden" id="totalImpuestos" name="totalImpuestos" style="text-align: left; border: 0" readonly>
												<input type="hidden" id="totalBruto" name="totalBruto" style="text-align: left; border: 0" readonly>										
												<input type="hidden" id="subtotal" name="subtotal" style="text-align: left; border: 0" readonly>	
												<input type="hidden" id="descuentofactura" name="descuentofactura"  style="text-align: left; border: 0" readonly>		
												<input type="text" id="totalVisual" name="total" style="text-align: left; border: 0" readonly>										
											</div>
										</div>
									</div>
									
								</div>
							</div>

							<div class="modal-footer" id="modal_footer">
								

							</div>
						</form>

				</div>
			</div>
		</div>
	
		<?php #require_once 'footer.php'?>
    
		<!--Modal Detalles-->
		<div class="modal fade" id="modal-Detalles"  role="dialog" aria-labelledby="exampleModalFormTitle" aria-hidden="true">
			<div class="modal-dialog modal-lg" role="document">
				<div class="modal-content ">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalFormTitle">Detalles nota</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					
					<div class="modal-body">
						<div class="col-sm-12">
							<table id="ltsDetallesNota" class="table table-responsive hover nowrap">
								<thead>
									<tr>
										<th>Producto</th>
										<th>Bodega</th>
										<th>Cant Entrada</th>
										<th>Valor Entrada</th>
										<th>Cant Salida</th>
										<th>Valor Salida</th>
										<th>Cant Saldo</th>
										<th>Valor Saldo</th>
									</tr>
								</thead>
								<tbody id="bodyDetallesNota">
									
								</tbody>
							</table>
						</div>

					</div>
					<div class="modal-footer">
						<button class="btn btn-primary" data-dismiss="modal"><span class="icon-copy ti-close"></span> Cerrar</button>
					</div>
					
					
				</div>
			</div>
		</div>

		<!--Modal Calculadora-->
		<div class="modal fade" id="modal-Calc" role="dialog" aria-labelledby="exampleModalFormTitle" aria-hidden="true">
			<div class="modal-dialog" role="document">
				<div class="modal-content">
					<form name="calculator" style="text-align: center;">
						<br>
						<input type="hidden" id="activador" name="activador" value="0"></input>
						<input type="hidden" id="activadorProducto" name="activadorProducto" value="0"></input>
						<input type="button" class="btn btn-success btn-pill" value="1" onClick="nota.cargarValuePrecio(1);">
						<input type="button" class="btn btn-success btn-pill" value="2" onClick="nota.cargarValuePrecio(2);">
						<input type="button" class="btn btn-success btn-pill" value="3" onClick="nota.cargarValuePrecio(3);">
						<br>
						<input type="button" class="btn btn-success btn-pill" value="4" onClick="nota.cargarValuePrecio(4);">
						<input type="button" class="btn btn-success btn-pill" value="5" onClick="nota.cargarValuePrecio(5);">
						<input type="button" class="btn btn-success btn-pill" value="6" onClick="nota.cargarValuePrecio(6);">
						<br>
						<input type="button" class="btn btn-success btn-pill" value="7" onClick="nota.cargarValuePrecio(7);">
						<input type="button" class="btn btn-success btn-pill" value="8" onClick="nota.cargarValuePrecio(8);">
						<input type="button" class="btn btn-success btn-pill" value="9" onClick="nota.cargarValuePrecio(9);">
						<br>
						<input type="button" class="btn btn-success btn-pill" value="0" onClick="nota.cargarValuePrecio(0);">
						<input type="reset" class="btn btn-success btn-pill" value="c" onClick="nota.cargarValuePrecio('c');">
					</form>
				</div>
			</div>
		</div>

		<!--Modal Calculadora-->
		<div class="modal fade" id="printSection" role="dialog" aria-labelledby="exampleModalFormTitle" aria-hidden="true">
			<div class="modal-dialog" role="document">
				<div class="modal-content">

					<div class="modal-headerr text-center">

					</div>
					<div class="modal-bodyy justify-content-center">
						
					</div>
					<!-- <div class="modal-footer justify-content-center">
						<a type="button" id='descargar' onclick="nota.descargarFac(1)" class="btn btn-info">Descargar <i class="far fa-file-pdf ml-1 text-white"></i></a>
						<a type="button" id='nodescargar' onclick="nota.descargarFac(2)" class="btn btn-outline-info waves-effect" data-dismiss="modal">Cerrar<i class="mi mi-ButtonX"></i></a>
					</div> -->
				</div>
			</div>
		</div>

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

		<!-- jquery-number js -->
		<script src="../src/plugins/jquery-number/jquery.number.js"></script>
		
		<script src="../src/plugins/sweetalert2/sweetalert2.all.js"></script>
		<script src="../core/remision_tactil.js"></script>

		<!-- <script src="../core/Permisos.js"></script> -->
	</div>	
</body>
</html>