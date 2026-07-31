	<?php


	include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
	include_once SERVER . '/business/DAO/DAO_FacturaDocumento.php';
	include_once SERVER . '/business/DAO/DAO_FacturaDetalleDocumento.php';
	include_once SERVER . '/business/DAO/DAO_Tesoreria.php';
	include_once SERVER . '/business/class.sessions.php';
	//include_once SERVER .'/business/controller/class.logs.php';


	class imprimirFactura{

	public $codigo;
	public $prefijo;

	public function traerImpresionFactura(){

	// Id Cierre de Factura
	$idDocumento = $this->codigo;

	$con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();

	//------------  Obtener Datos de la EMPRESA.
		$query = "SELECT * FROM fac_cierre_caja as fcc  
					INNER JOIN conf_sedes_empresa_cajas as csec on fcc.cica_IdCaja = csec.seemca_Id
					INNER JOIN conf_sedes_empresa as cse ON cse.seem_Id = csec.seemca_IdSedeEmpresa
					INNER JOIN conf_empresa as ce ON ce.emp_Id = cse.seem_IdEmpresa
					where fcc.cica_Id = $idDocumento ";

		$data = $con->consultar($query);
		if($data != NULL && $data != ""){
			if( $con->getNumeroFilasConsultadas($data) >0 ){ 
				while($res = $con->obnerFila($data)){
					$rowEmpresa[] = $res;
				}
			}else{ $rowEmpresa = NULL;}
		}else{ $rowEmpresa = NULL;}

	//------------  Obtener Datos de ultima y primera factura (PREFIJO Y NUMERO) - FACTURA POS.
	$query = "(SELECT facdo.* FROM fac_tesoreria as ft 
	INNER JOIN fac_documento as facdo ON facdo.doc_Id = ft.teso_IdDocumento
			where facdo.doc_IdTipoDocumento = 2 and
			ft.teso_IdCierre = $idDocumento ORDER by facdo.doc_Id ASC limit 1)
			UNION ALL
			(SELECT facdo.* FROM fac_tesoreria as ft 
	INNER JOIN fac_documento as facdo ON facdo.doc_Id = ft.teso_IdDocumento
			where facdo.doc_IdTipoDocumento = 2 and
			ft.teso_IdCierre = $idDocumento ORDER by facdo.doc_Id desc limit 1)";
		$data = $con->consultar($query);
		if($data != NULL && $data != ""){
			if( $con->getNumeroFilasConsultadas($data) >0 ){ 
				while($res = $con->obnerFila($data)){
					$rowPrefijosPOS[] = $res;
				}
			}else{ $rowPrefijosPOS = NULL;}
		}else{ $rowPrefijosPOS = NULL;}

// Validar si hay facturas POS y extraemos consecutivos

if(isset($rowPrefijosPOS[0])){
	$delpos= 'Del';
	$alpos= 'al';
	$prepos= $rowPrefijosPOS[0]['doc_Prefijo'];
	$numposuno= $rowPrefijosPOS[0]['doc_Numero'];
	$numposdos= $rowPrefijosPOS[1]['doc_Numero'];
}else{
	$delpos= '';
	$alpos= '';
	$prepos= '';
	$numposuno= '';
	$numposdos= '';
}

//------------  Obtener Datos de ultima y primera factura (PREFIJO Y NUMERO) - REMISIÓN.
		$queryy = "(SELECT facdo.* FROM fac_tesoreria as ft 
		INNER JOIN fac_documento as facdo ON facdo.doc_Id = ft.teso_IdDocumento
				where facdo.doc_IdTipoDocumento = 1 and
				ft.teso_IdCierre = $idDocumento ORDER by facdo.doc_Id ASC limit 1)
				UNION ALL
				(SELECT facdo.* FROM fac_tesoreria as ft 
		INNER JOIN fac_documento as facdo ON facdo.doc_Id = ft.teso_IdDocumento
				where facdo.doc_IdTipoDocumento = 1 and 
				ft.teso_IdCierre = $idDocumento ORDER by facdo.doc_Id desc limit 1)";
			$dataa = $con->consultar($queryy);
			if($dataa != NULL && $data != ""){
				if( $con->getNumeroFilasConsultadas($dataa) >0 ){ 
					while($res = $con->obnerFila($dataa)){
						$rowPrefijosREMI[] = $res;
					}
				}else{ $rowPrefijosREMI = NULL;}
			}else{ $rowPrefijosREMI = NULL;}

			
// Validar si hay facturas REMISIÓN y extraemos consecutivos
if(isset($rowPrefijosREMI[0])){
	$delremi= 'Del';
	$alremi= 'al';
	$preremi= $rowPrefijosREMI[0]['doc_Prefijo'];
	$numremiuno= $rowPrefijosREMI[0]['doc_Numero'];
	$numremidos= $rowPrefijosREMI[1]['doc_Numero'];
}else{
	$delremi= '';
	$alremi= '';
	$preremi= '';
	$numremiuno= '';
	$numremidos= '';
}


//------------  Obtener desglose de productos por facturas del cierre (FACTURAS ACTIVAS).  

		$query = "SELECT  concat_ws('_', fd.doc_Prefijo, fd.doc_Numero) as numero, ipp.pro_Nombre as nombre, 
				fpa.forpa_Descripcion as medioPago,
				TRUNCATE(fdd.detDoc_Cantidad,0) as cantidad, TRUNCATE(fdd.detDoc_ValorTotal,0) as precio 
                FROM fac_tesoreria as ft 
				INNER JOIN fac_formas_pago as fpa on fpa.forpa_Id=ft.teso_IdFormaPago
                INNER JOIN fac_documento as fd on ft.teso_IdDocumento=fd.doc_Id
                INNER JOIN fac_detalle_documento as fdd on ft.teso_IdDocumento=fdd.detDoc_IdDocumento
                INNER JOIN inv_producto as ipp on fdd.detDoc_IdProducto=ipp.pro_Id 
                	where fd.doc_Estado = 1 and ft.teso_IdCierre = $idDocumento;";
		$data = $con->consultar($query);
		if($data != NULL && $data != ""){
			if( $con->getNumeroFilasConsultadas($data) >0 ){ 
				while($res = $con->obnerFila($data)){
					$productosFactura[] = $res;
				}
			}else{ $productosFactura = NULL;}
		}else{ $productosFactura = NULL;}


//------------  Obtener Redondeos, descuentos y valor total de las facturas del cierre (FACTURAS ACTIVAS).  

		$query = "SELECT SUM(TRUNCATE(fd.doc_Descuento,0)) as descuento, 
					SUM(TRUNCATE(fd.doc_Redondeo,0)) as redondeo ,
					SUM(TRUNCATE(fd.doc_ValorNeto,0)) as totales 
				FROM fac_tesoreria as ft 
					INNER JOIN fac_documento as fd on ft.teso_IdDocumento=fd.doc_Id
				where fd.doc_Estado = 1 and ft.teso_IdCierre = $idDocumento;";

		$data = $con->consultar($query);
		if($data != NULL && $data != ""){
		if( $con->getNumeroFilasConsultadas($data) >0 ){ 
		while($res = $con->obnerFila($data)){
			$totalesFacturas[] = $res;
		}
		}else{ $totalesFacturas = NULL;}
		}else{ $totalesFacturas = NULL;}

//------------  Obtener desglose de productos por facturas del cierre (FACTURAS ANULADAS).  

$query = "SELECT  concat_ws('_', fd.doc_Prefijo, fd.doc_Numero) as numero, ipp.pro_Nombre as nombre, 
				TRUNCATE(fdd.detDoc_Cantidad,0) as cantidad, TRUNCATE(fdd.detDoc_ValorTotal,0) as precio 
                FROM fac_tesoreria as ft 
                INNER JOIN fac_documento as fd on ft.teso_IdDocumento=fd.doc_Id
                INNER JOIN fac_detalle_documento as fdd on ft.teso_IdDocumento=fdd.detDoc_IdDocumento
                INNER JOIN inv_producto as ipp on fdd.detDoc_IdProducto=ipp.pro_Id 
                	where fd.doc_Estado = 0 and ft.teso_IdCierre = $idDocumento;";
		$data = $con->consultar($query);
		if($data != NULL && $data != ""){
			if( $con->getNumeroFilasConsultadas($data) >0 ){ 
				while($res = $con->obnerFila($data)){
					$productosFacturaAnuladas[] = $res;
				}
			}else{ $productosFacturaAnuladas = NULL;}
		}else{ $productosFacturaAnuladas = NULL;}		

//------------  Obtener desglose de pagos a caja. -------------------- 

		$query = "SELECT TRUNCATE(paca_Valor,0) as valor, paca_Observaciones as descripcion,
		(SELECT tipo.tipa_Nombre from fac_tipos_pagos as tipo WHERE tipo.tipa_Id=pc.paca_IdTipoPago) as tipoPago,
		(SELECT subtipo.subtipa_Nombre from fac_sub_tipos_pagos as subtipo WHERE subtipo.subtipa_Id=pc.paca_IdSubTipoPago) as subTipoPago
				FROM fac_pagos_caja as pc where pc.paca_IdCierre=$idDocumento;";
		$data = $con->consultar($query);
		if($data != NULL && $data != ""){
		if( $con->getNumeroFilasConsultadas($data) >0 ){ 
		while($res = $con->obnerFila($data)){
			$pagosCaja[] = $res;
		}
		}else{ $pagosCaja = NULL;}
		}else{ $pagosCaja = NULL;}

//-------------  Obtener Datos del Cierre.
	$con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
	$query = "SELECT * FROM fac_cierre_caja as cie 
				WHERE cie.cica_Id = $idDocumento";

	$data = $con->consultar($query);
	if($data != NULL && $data != ""){
		if( $con->getNumeroFilasConsultadas($data) >0 ){
			while($res = $con->obnerFila($data)){
				$datosCierre[] = $res;
			}
		}else{ $datosCierre = NULL;}
	}else{ $datosCierre = NULL;}


	//------------  Obtener Datos de la Facturas sin cierre.
		$query = "SELECT ffp.forpa_Descripcion as 'nomForma', csec.seemca_Nombre as 'nomCaja', 
						SUM(round(ft.teso_Importe)) as 'cantidad' , SUM(round(facdo.doc_ValorImpuestos)) as 'impuestos',
						SUM(round(facdo.doc_ValorBruto)) as 'bruto',  SUM(round(facdo.doc_ValorNeto)) as 'neto',
						SUM(round(facdo.doc_Redondeo)) as 'redondeo', SUM(round(facdo.doc_Descuento)) as 'descuento', 
						SUM(round(facdo.doc_Subtotal)) as 'subtotal'
				FROM fac_tesoreria as ft 
				INNER JOIN conf_sedes_empresa_cajas as csec on ft.teso_IdCaja = csec.seemca_Id
				INNER JOIN fac_formas_pago as ffp on ft.teso_IdFormaPago = ffp.forpa_Id
				INNER JOIN fac_documento as facdo ON facdo.doc_Id = ft.teso_IdDocumento
						where ft.teso_IdCierre = $idDocumento 
						GROUP by ft.teso_IdFormaPago";
		$data = $con->consultar($query);
		if($data != NULL && $data != ""){
			if( $con->getNumeroFilasConsultadas($data) >0 ){ 
				while($res = $con->obnerFila($data)){
					$rowDocuClien[] = $res;
				}
			}else{ $rowDocuClien = NULL;}
		}else{ $rowDocuClien = NULL;}

	// Datos
	if($rowDocuClien[0]['cantidad'] and ($rowDocuClien[0]['nomForma'] == 'EFECTIVO')){
		$valorEfectivo= $rowDocuClien[0]['cantidad'];
		$valorEfectivo_format= number_format( $valorEfectivo, 0,',','.');
	}else{
		$valorEfectivo=0;
	}

	if( isset($rowDocuClien[1])){
		$valorCredito= $rowDocuClien[1]['cantidad'];
	}else{
		$valorCredito=0;
	}

	//-------------  Obtener Pagos a caja sin cierre.
		$con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
			$query = "SELECT round(SUM(fpc.paca_Valor)) as 'valorPagos' FROM fac_pagos_caja as fpc 
			WHERE fpc.paca_IdCierre= $idDocumento";

		$data = $con->consultar($query);
		if($data != NULL && $data != ""){
			if( $con->getNumeroFilasConsultadas($data) >0 ){
				while($res = $con->obnerFila($data)){
					$productos[] = $res;
				}
			}else{ $productos = NULL;}
		}else{ $productos = NULL;}

	// Datos
	$valorPagos= $productos[0]['valorPagos'];
	

	//-------------  Obtener Base de Caja sin Cerrar.
		$con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
		$query = "SELECT round(fbc.bace_Base) as 'bace_Base' FROM fac_base_caja as fbc 
					WHERE fbc.bace_IdCierre= $idDocumento";

		$data = $con->consultar($query);
		if($data != NULL && $data != ""){
			if( $con->getNumeroFilasConsultadas($data) >0 ){
				while($res = $con->obnerFila($data)){
					$impuetos[] = $res;
				}
			}else{ $impuetos = NULL;}
		}else{ $impuetos = NULL;}

	// Datos
	$valorBase= $impuetos[0]['bace_Base'];
	$valorBase_format= number_format( $valorBase, 0,',','.');

	$valorTotal= ($valorEfectivo + $valorBase) - $valorPagos;
	$valorTotal_format= number_format( $valorTotal, 0,',','.');

	$valorPagos_format= number_format( $valorPagos, 0,',','.');

	$valor= $valorCredito + $valorTotal;

	$hoy = $rowEmpresa[0]['cica_Fecha'];
	
	//REQUERIMOS LA CLASE TCPDF
	require_once('tcpdf_include.php');

	$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);


	$pdf->SetMargins(0, 2, 0);
	$pdf->setPrintHeader(false);
	$pdf->SetHeaderMargin(0);
	$pdf->setPrintFooter(false);
	$pdf->SetFooterMargin(0);

	$pdf->SetAutoPageBreak(TRUE, 0);

	$pdf->AddPage('P', 'A7');

	//---------------------------------------------------------
	// -------------- Información de la EMPRESA---------------
	foreach ($rowEmpresa as $key => $item) {
	$bloque1 = <<<EOF

	<table style="font-size:10px; text-align:center">
		<tr>
			<td style="width:40px;">
				<img src="images/logo.jpeg">
			</td>
			<td style="width:120px; font-size:10px">
					$item[emp_NombreComercial] <br>
				<em style="width:130px;font-size:5px" align="center">
					$item[emp_Nombre] <br>
				</em>
				<em style="width:130px;font-size:5px"align="center">
					NIT: $item[emp_Nit]
				</em><br>
				<em style="width:130px;font-size:5px">
					Dirección: $item[seem_Direccion]
				</em>
			</td>
		</tr>
	</table>


	<table style="font-size:8px; text-align:center">
		<tr>
			<td style="width:160px;">		
				<b> Informe General</b>
			</td>
		</tr>
		<tr>
			<td style="width:160px; font-size:5px">		
				Cierre de Caja # $item[seemca_Nombre]<br>
				<b> $hoy </b>
			</td>
		</tr>
		<tr>	
			<td style="width:160px; font-size:6px">		
			$delpos <b> $prepos </b> $numposuno $alpos <b> $prepos </b> $numposdos  
			</td>
		</tr>	
		<tr>	
			<td style="width:160px; font-size:6px">		
			$delremi <b> $preremi </b> $numremiuno $alremi <b> $preremi </b> $numremidos  
			</td>
		</tr>	
		<tr>
			<td style="width:160px;">
			</td>
		</tr>
	</table>

	EOF;

	$pdf->writeHTML($bloque1, false, false, false, false, '');
	}

	//---------------------------------------------------------
	// -------------- Información de Productos ---------------
	$bloque2 = <<<EOF

	<table border="0.1" style="font-size:6px; text-align:center">
		<tr>
			<td style="width:160px;margin:auto;text-align:Center">
			<strong>Facturas de Ventas Generadas</strong>
			</td>
		</tr>
		<tr>
			<td style="width:30px;margin:auto;text-align:left">
				<strong>#</strong>
			</td>
			<td style="width:65px;margin:auto;text-align:left">
				<strong>Producto</strong>
			</td>
			<td style="width:35px;margin:auto;text-align:left">
				<strong>Cantidad</strong>
			</td>
			<td style="width:30px;margin:auto;text-align:left">
				<strong>Precio</strong>
			</td>
		</tr>
		
	</table>

	EOF;

	$pdf->writeHTML($bloque2, false, false, false, false, '');


	//---------------------------------------------------------
	// -------------- Información de Productos ---------------
	
	foreach ($productosFactura as $key => $item) {
		
	$format_numm = number_format( $item['precio'], 0,',','.');

	$bloque3 = <<<EOF

	<table border="0.1" style="font-size:6px; text-align:center">
		<tr>
			<td style="width:30px;margin:auto;text-align:left">
				$item[numero] 
			</td>
			<td style="width:85px;margin:auto;text-align:center">
				$item[nombre]<br>
				Medio Pago: <strong>$item[medioPago]</strong>
			</td>
			<td style="width:15px;margin:auto;text-align:left">
				$item[cantidad]
			</td>
			<td style="width:30px;margin:auto;text-align:left">
				$format_numm
			</td>
		</tr>	
	</table>

	EOF;

	$pdf->writeHTML($bloque3, false, false, false, false, '');
		
	}

	$totalProductos=0;	

	foreach ($productosFactura as $key => $item) {	
	$totalProductos = $totalProductos + $item[precio];
	}
	$total_format_Pro= number_format( $totalProductos, 0,',','.');
	
	$bloque4 = <<<EOF
	<table border="0.1" style="font-size:6px; text-align:center">
		<tr>
			<td style="width:130px;margin:auto;text-align:rigth">
				<strong>SUBTOTAL:</strong>
			</td>
			<td style="width:30px;margin:auto;text-align:left">
				$total_format_Pro
			</td>
		</tr>	
	</table>

	EOF;

	$pdf->writeHTML($bloque4, false, false, false, false, '');

	

	$totalesRedondeo=0;	
	$totalesDescuentos=0;	
	$totalesTotal=0;	

	foreach ($totalesFacturas as $key => $item) {	
	$totalesRedondeo = $totalesRedondeo + $item[redondeo];
	$totalesDescuentos = $totalesDescuentos + $item[descuento];
	$totalesTotal = $totalesTotal + $item[totales];
	}
	$total_format_redondeo= number_format( $totalesRedondeo, 0,',','.');
	$total_format_descuentos= number_format( $totalesDescuentos, 0,',','.');
	$total_format_totales= number_format( $totalesTotal, 0,',','.');
	
	$bloque4 = <<<EOF
	<table border="0.1" style="font-size:6px; text-align:center">
		<tr>
			<td style="width:130px;margin:auto;text-align:rigth">
				<strong>Redondeo:</strong>
			</td>
			<td style="width:30px;margin:auto;text-align:left">
				$total_format_redondeo
			</td>
		</tr>	
		<tr>
			<td style="width:130px;margin:auto;text-align:rigth">
				<strong>Descuento:</strong>
			</td>
			<td style="width:30px;margin:auto;text-align:left">
				$total_format_descuentos
			</td>
		</tr>	
		<tr>
			<td style="width:130px;margin:auto;text-align:rigth">
				<strong>TOTAL:</strong>
			</td>
			<td style="width:30px;margin:auto;text-align:left">
				$total_format_totales
			</td>
		</tr>	
	</table>

	EOF;

	$pdf->writeHTML($bloque4, false, false, false, false, '');

	
	



	if(is_array($pagosCaja)){
	//---------------------------------------------------------
	// -------------- Pagos a Caja ---------------
	$bloque6 = <<<EOF
	<table style="font-size:8px; text-align:center">
		<tr>
			<td style="width:160px;">
			</td>
		</tr>
	</table>

	<table border="0.1" style="font-size:6px; text-align:center">
		<tr>
			<td style="width:160px;margin:auto;text-align:Center">
			<strong>Pagos por Caja</strong>
			</td>
		</tr>
		<tr>
			<td style="width:50px;margin:auto;text-align:left">
				<strong>Observaciónes</strong>
			</td>	
			<td style="width:50px;margin:auto;text-align:left">
				<strong>Tipo de Pago</strong>
			</td>	
			<td style="width:30px;margin:auto;text-align:left">
				<strong>Sub Tipo de Pago</strong>
			</td>	
			<td style="width:30px;margin:auto;text-align:left">
				<strong>Valor</strong>
			</td>
		</tr>
		
	</table>

	EOF;

	$pdf->writeHTML($bloque6, false, false, false, false, '');


	//---------------------------------------------------------
	// -------------- Información de pagos ---------------
	
	foreach ($pagosCaja as $key => $item) {

	$format_cant=0;
	$format_cant= number_format( $item[valor], 0,',','.');

	$bloque7 = <<<EOF

	<table border="0.1" style="font-size:6px; text-align:center">
		<tr>
			<td style="width:50px;margin:auto;text-align:left">
				$item[descripcion]
			</td>
			<td style="width:50px;margin:auto;text-align:left">
				$item[tipoPago]
			</td>
			<td style="width:30px;margin:auto;text-align:left">
				$item[subTipoPago]
			</td>
			<td style="width:30px;margin:auto;text-align:left">
				$format_cant
			</td>
		</tr>	
	</table>

	EOF;

	$pdf->writeHTML($bloque7, false, false, false, false, '');
		
	}
	
	$total=0;	

	foreach ($pagosCaja as $key => $item) {	
	$total = $total + $item[valor];
	}
	$total_format= number_format( $total, 0,',','.');
	
	$bloque8 = <<<EOF
	<table border="0.1" style="font-size:6px; text-align:center">
		<tr>
			<td style="width:120px;margin:auto;text-align:rigth">
				<strong>TOTAL:</strong>
			</td>
			<td style="width:40px;margin:auto;text-align:left">
				$total_format
			</td>
		</tr>	
	</table>

	EOF;

	$pdf->writeHTML($bloque8, false, false, false, false, '');
}

//---------------------------------------------------------
// -------------- Información de OBSERVACIÓNES ---------------

	
foreach ($datosCierre as $key => $item) {
	if($item[cica_Observaciones] != null){

	$bloque4 = <<<EOF

	<table border="0" style="font-size:6px; text-align:center">
		<tr>
			<td style="width:160px;">
			</td>
		</tr>
	</table>

	<table border="0.1" style="font-size:6px; text-align:center">
		<tr>
			<td style="width:160px;margin:auto;text-align:center">
				$item[cica_Observaciones]
			</td>

		</tr>
	</table>

	<table border="0" style="font-size:6px; text-align:center">
		<tr>
			<td style="width:160px;">
			</td>
		</tr>
	</table>
	
EOF;

$pdf->writeHTML($bloque4, false, false, false, false, '');
}
}

	
	if(is_array($productosFacturaAnuladas)){
	//---------------------------------------------------------
	// -------------- FACTURAS ANULADAS ---------------
	$bloque9 = <<<EOF
	<table style="font-size:8px; text-align:center">
		<tr>
			<td style="width:160px;">
			</td>
		</tr>
	</table>
	<table border="0.1" style="font-size:6px; text-align:center">
		<tr>
			<td style="width:160px;margin:auto;text-align:Center">
			<strong>Facturas Anuladas</strong>
			</td>
		</tr>
		<tr>
			<td style="width:30px;margin:auto;text-align:left">
				<strong>#</strong>
			</td>
			<td style="width:65px;margin:auto;text-align:left">
				<strong>Producto</strong>
			</td>
			<td style="width:35px;margin:auto;text-align:left">
				<strong>Cantidad</strong>
			</td>
			<td style="width:30px;margin:auto;text-align:left">
				<strong>Precio</strong>
			</td>
		</tr>
		
	</table>

	EOF;

	$pdf->writeHTML($bloque9, false, false, false, false, '');


	//---------------------------------------------------------
	// -------------- Información de Productos ---------------
	

	foreach ($productosFacturaAnuladas as $key => $item) {

	$bloque10 = <<<EOF

	<table border="0.1" style="font-size:6px; text-align:center">
		<tr>
			<td style="width:30px;margin:auto;text-align:left">
				$item[numero]
			</td>
			<td style="width:85px;margin:auto;text-align:left">
				$item[nombre]
			</td>
			<td style="width:15px;margin:auto;text-align:left">
				$item[cantidad]
			</td>
			<td style="width:30px;margin:auto;text-align:left">
				$item[precio]
			</td>
		</tr>	
	</table>

	EOF;

	$pdf->writeHTML($bloque10, false, false, false, false, '');
		
	}
	}






//---------------------------------------------------------
// -------------- Información DIGITSOFT ---------
$bloque11 = <<<EOF
<table style="font-size:8px; text-align:center">
	<tr>
		<td style="width:160px;">
		</td>
	</tr>
</table>
<table style="font-size:6px; text-align:center">
	<tr>
		<td style="width:160px;font-size:6px">		
			Desarrollado por: <br> <b>DigitSoft _ </b>DS-POS
		</td>
	</tr>
	<tr>
		<td style="width:160px;">		
			www.digitsoft.com.co - 3215434380
		</td>    		
	</tr>    	
</table>

EOF;

$pdf->writeHTML($bloque11, false, false, false, false, '');

	// ---------------------------------------------------------
	//SALIDA DEL ARCHIVO 

	//$pdf->Output('factura.pdf', 'D');
	ob_end_clean();
	$pdf->Output('factura.pdf');

	}

	}

	$factura = new imprimirFactura();
	$factura -> codigo = $_GET["codigo"];
	$factura -> traerImpresionFactura();

	?>