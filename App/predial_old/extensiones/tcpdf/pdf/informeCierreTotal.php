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
		$query = "SELECT *, IFNULL(csec.seemca_Serial, 'No Asignado') as 'serial' FROM fac_cierre_caja as fcc  
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


	$pdf->SetMargins(12, 2, 0);
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
				<b> Cierre de Caja: </b>$item[seemca_Nombre]
			</td>
		</tr>
		<tr>
			<td style="width:160px; font-size:6px">		
				<b> Serial Equipo: </b>$item[serial] 
			</td>
		</tr>
		<tr>
			<td style="width:160px; font-size:6px">		
				<b> Fecha: </b>$hoy 
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
	$bloque4 = <<<EOF

	<table border="0.1" style="font-size:6px; text-align:center">
		<tr>
			<td style="width:160px;margin:auto;text-align:Center">
				<strong>CUADRE DE CAJA</strong>
			</td>
		</tr>
		<tr>
			<td style="width:80px;margin:auto;text-align:left">
				Efectivo
			</td>
			<td style="width:80px;margin:auto;text-align:left">
				$ $valorEfectivo_format
			</td>
		</tr>
		<tr>
			<td style="width:80px;margin:auto;text-align:left">
				Base
			</td>
			<td style="width:80px;margin:auto;text-align:left">
				$ $valorBase_format
			</td>
		</tr>
		<tr>
			<td style="width:80px;margin:auto;text-align:left">
				Pagos a Caja
			</td>
			<td style="width:80px;margin:auto;text-align:left">
				- $ $valorPagos_format
			</td>
		</tr>
		<tr>
			<td style="width:80px;margin:auto;text-align:left">
				TOTAL
			</td>
			<td style="width:80px;margin:auto;text-align:left">
				$ $valorTotal_format
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



	//---------------------------------------------------------
	// -------------- Información de Productos ---------------
	
	foreach ($rowDocuClien as $key => $item) {
	
	$format_cant=0;
	$format_cant= number_format( $item[cantidad], 0,',','.');

	$bloque4 = <<<EOF

	<table border="0.1" style="font-size:6px; text-align:center">
		<tr>
			<td style="width:160px;margin:auto;text-align:center">
				<strong>Forma de Pago $item[nomForma] </strong>
			</td>

		</tr>
		<tr>
			<td style="width:80px;margin:auto;text-align:left">
				Ventas
			</td>
			<td style="width:80px;margin:auto;text-align:left">
				$ $format_cant
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

	//---------------------------------------------------------
	// -------------- Información de Productos ----------------
	$bloque5 = <<<EOF
	<table style="font-size:8px; text-align:center">
		<tr>
			<td style="width:160px;">
			</td>
		</tr>	
		<tr>
			<td style="width:160px;">		
				<b> Resumen de Caja</b><br>
				Ventas por Forma de Pago
			</td>
		</tr>
		<tr>
			<td style="width:160px;">
			</td>
		</tr>
	</table>

	<table border="0.1" style="font-size:6px; text-align:center">
		<tr>
			<td style="width:40px;margin:auto;text-align:center">
				<b> FORMA DE PAGO </b>
			</td>
			<td style="width:40px;margin:auto;text-align:center">
				<b> BRUTO </b>
			</td>
			<td style="width:35px;margin:auto;text-align:center">
				<b> IVA </b>
			</td>
			<td style="width:45px;margin:auto;text-align:center">
				<b> SUBTOTAL </b>
			</td>
		</tr>
	</table>

	EOF;

	$pdf->writeHTML($bloque5, false, false, false, false, '');






	//---------------------------------------------------------
	// -------------- Información de Productos ---------------

	foreach ($rowDocuClien as $key => $item) {

	$format_bruto=0;
	$format_impuestos=0;
	$format_subtotal=0;
	
	$format_bruto= number_format( $item[bruto], 0,',','.');
	$format_impuestos= number_format( $item[impuestos], 0,',','.');
	$format_subtotal= number_format( $item[subtotal], 0,',','.');

	$bloque7 = <<<EOF

	<table border="0.1" style="font-size:5.5px; text-align:center">
		<tr>
			<td style="width:40;margin:auto;text-align:center">
				$item[nomForma]
			</td>
			<td style="width:40px;margin:auto;text-align:center">
				$ $format_bruto
			</td>
			<td style="width:35px;margin:auto;text-align:center">
				$ $format_impuestos
			</td>
			<td style="width:45px;margin:auto;text-align:center">
				$ $format_subtotal
			</td>
		</tr>
	</table>

	EOF;


	$pdf->writeHTML($bloque7, false, false, false, false, '');
	}


	//---------------------------------------------------------
	// -------------- Información de Productos ---------------
	$total=0;
	$redon=0;
	$descu=0;

	foreach ($rowDocuClien as $key => $item) {	
	$total = $total + $item[cantidad];
	$redondeoTotal = $redon + $item[redondeo];
	$descuentoTotal = $descu + $item[descuento];
	}
	$total_format= number_format( $total, 0,',','.');
	$redondeo_format= number_format( $redondeoTotal, 0,',','.');
	$descuento_format= number_format( $descuentoTotal, 0,',','.');

	//$total = ($total + $valorBase) - $valorPagos;


	$bloque8 = <<<EOF

	<table border="0.1" style="font-size:6px; text-align:center">
		<tr>
			<td style="width:115px;margin:auto;text-align:right">
				<b>DESCUENTOS</b>
			</td>
			<td style="width:45px;margin:auto;text-align:center">
				$ $descuento_format
			</td>
		</tr>
		<tr>
			<td style="width:115px;margin:auto;text-align:right">
				<b>REDONDEO</b>
			</td>
			<td style="width:45px;margin:auto;text-align:center">
				$ $redondeo_format
			</td>
		</tr>
		<tr>
			<td style="width:115px;margin:auto;text-align:right">
				<b>TOTAL</b>
			</td>
			<td style="width:45px;margin:auto;text-align:center">
				$ $total_format
			</td>
		</tr>
	</table>

	EOF;

	$pdf->writeHTML($bloque8, false, false, false, false, '');


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