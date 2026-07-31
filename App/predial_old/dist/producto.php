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
	<title>Productos | DS-POS</title>

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
					<h4 class="h4">Listado de Productos</h4>
					<button type="button" class="btn btn-outline-success" onclick="prod.crearProducto()"><span class="ti-plus"></span> Crear Producto</button>
				</div>
				<div class="pb-20">
					<table id="productosRegistrados" class="data-table table stripe hover nowrap">
						<thead>
							<tr>
								<th>Código</th>
								<th>Nombre</th>
								<th>Código Barras</th>
								<th>Precio Venta</th>
								<th>Precio Costo</th>
								<th>Stock Total</th>
								<th>Categoria</th>
								<th>Acciones</th>
							</tr>
						</thead>
						<tbody id="bodyProductosRegistrados">
						
						</tbody>
					</table>
				</div>
			</div>
		</div>
	
		<?php #require_once 'footer.php'?>
    
		<!--Modal Producto-->
		<div class="modal fade" id="modal-Producto" tabindex="-1" role="dialog" aria-labelledby="exampleModalFormTitle" aria-hidden="true">
			<div class="modal-dialog modal-lg" role="document">
				<div class="modal-content ">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalFormTitle">Producto</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					
					<form id="formCrearProducto" class="" action="">
						<div class="modal-body">
							<div class="row container">
								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>Código</label>
											<input type="text" class="form-control" id="pro_Codigo" name="pro_Codigo" readonly>
										</div>	
									</div>	
								</div>
								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>Nombre</label>
											<input type="text" class="form-control" id="pro_Nombre" name="pro_Nombre" placeholder="Nombre..." required>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>Código de Barras</label>
											<input type="text" class="form-control" id="pro_CodBarras" name="pro_CodBarras" placeholder="Código de barras..." >
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>Tipo</label>
											<select class="form-control" style="width: 100%;"
												tabindex="-1" aria-hidden="true" id="pro_Tipo" name="pro_Tipo" required>
													<option value="">Seleccione una opción</option>
													<option value="1">Producto</option>
													<option value="2">Servicio</option>
											</select>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>Unidad de medida</label>
											<select class="form-control" style="width: 100%;"
												tabindex="-1" aria-hidden="true" id="pro_UnidadMed" name="pro_UnidadMed" required></select>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>Cantidad de Medida</label>
											<input type="text" class="form-control" id="pro_CantidadMed" name="pro_CantidadMed" value="1" placeholder="Cantidad de Medida" readonly>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>Usa Stock</label>
											<select class="form-control" style="width: 100%;"
												tabindex="-1" aria-hidden="true" id="pro_UsaStoks" name="pro_UsaStoks"  onChange="prod.getstock(this.value)" required>
												<option value="1">Si</option> 
												<option value="0">No</option> 
											</select>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>Impuesto</label>
											<select class="form-control" style="width: 100%;"
												tabindex="-1" aria-hidden="true" id="pro_IdImpuesto" name="pro_IdImpuesto" required></select>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>Categoria</label>
											<select class="form-control" style="width: 100%;"
												tabindex="-1" aria-hidden="true" id="pro_Categoria" name="pro_Categoria" onclick = "prod.getSubCategorias()" required></select>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>Subcategoria</label>
											<select class="form-control" style="width: 100%;"
												tabindex="-1" aria-hidden="true" id="pro_SubCategoria" name="pro_SubCategoria" required>
												</select>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>Marca</label>
											<select class="form-control" style="width: 100%;"
												tabindex="-1" aria-hidden="true" id="pro_IdMarca" name="pro_IdMarca" required></select>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>Proveedor</label>
											<select class="form-control" style="width: 100%;"
												tabindex="-1" aria-hidden="true" id="pro_IdProveedor" name="pro_IdProveedor" required>
											</select>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-12">
									<div class="row" >	
										<div class="form-group" style="width: 100%">
											<h5 class="modal-title text-center">COSTO - PRECIO</h5>
										</div>
									</div>
								</div>

								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>Costo Unitario (IVA Incluido)</label>
											<input type="text" class="form-control" id="pro_costo" name="pro_costo" value="0" placeholder="Costo Unitario" required>
											<input type="hidden" class="form-control" id="pro_Costo_Id" name="pro_Costo_Id">
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>Precio Venta</label>
											<input type="text" class="form-control" id="pro_PrecioVenta" name="pro_PrecioVenta" value="1" placeholder="Precio Venta" required>
											<input type="hidden" class="form-control" id="pro_PrecioVenta_Id" name="pro_PrecioVenta_Id">
										</div>	
									</div>	
								</div>

								<div id="div_stock" name="div_stock"  class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label id="pro_labelStock">Stock Inicial</label>
											<input type="text" class="form-control" id="pro_StockInicial" name="pro_StockInicial" value="1" placeholder="Stock Inicial" required>
											<input type="hidden" class="form-control" id="pro_StockInicial_Id" name="pro_StockInicial_Id">
										</div>	
									</div>	
								</div>

								
								<div id="div_Bodega" name="div_Bodega"  class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label id="pro_LabelIdBodega">Bodega Destino</label>
											<select class="form-control" style="width: 100%;" tabindex="-1" aria-hidden="true" id="pro_IdBodega" name="pro_IdBodega" required></select>
										</div>	
									</div>	
								</div>

								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<!--<label>Imagen</label>-->
											<input type="hidden" class="form-control" id="imagen" name="imagen" placeholder="Código de barras" >
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
			
		
		<!--Modal Precios Venta-->
		<div class="modal fade" id="modal-Precios" tabindex="-1" role="dialog" aria-labelledby="exampleModalFormTitle" aria-hidden="true">
			<div class="modal-dialog modal-lg" role="document">
				<div class="modal-content ">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalFormTitle">Precios de Venta</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					
					<form id="formCrearPrecios" class="" action="">
						<div class="modal-body">
							<div class="row container">
								<div class="col-sm-12 col-md-6" style="margin-top: 1%">
									<div class="row">
										<div class="form-group" style="width: 95%">
											<label>Tarifa #1</label>
											<input type="text" class="form-control" id="pre_Preciouno" name="pre_Preciouno" placeholder="Precio #1" required>
											<input type="hidden" class="form-control" id="pre_Preciouno_Id" name="pre_Preciouno_Id" placeholder="Precio #1" required>
										</div>	
									</div>	
								</div>

							</div>
						</div>
						<div class="modal-footer" id="modal_footer_1">
						</div>
					</form>
					
				</div>
			</div>
		</div>
			


		<!--Modal Stock-->
		<div class="modal fade" id="modal-Stock" tabindex="-1" role="dialog" aria-labelledby="exampleModalFormTitle" aria-hidden="true">
		<div class="modal-dialog modal-lg" role="document">
			<div class="modal-content ">
				<div class="modal-header">
					<h5 class="modal-title" id="exampleModalFormTitle">Stock en Bodegas</h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
				
				<form id="formCrearStock" class="" action="">
					<div class="modal-body" id="modal_body_1">
					</div>
				</form>
				
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
		<script src="../core/producto.js"></script>
		<!-- <script src="../core/Permisos.js"></script> -->
	</div>	
</body>
</html>