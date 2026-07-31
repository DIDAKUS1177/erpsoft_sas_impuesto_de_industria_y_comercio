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

// Id Documento Factura.
$idDocumento = $this->codigo;

$con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();

//------------  Obtener Datos de la EMPRESA.
	$query = "SELECT * FROM conf_empresa as ce 
				INNER JOIN conf_sedes_empresa as cse on ce.emp_Id = cse.seem_IdEmpresa
				INNER JOIN conf_sedes_empresa_cajas as csec on cse.seem_Id = csec.seemca_IdSedeEmpresa
					where csec.seemca_Id =$idDocumento ";
	$data = $con->consultar($query);
	if($data != NULL && $data != ""){
		if( $con->getNumeroFilasConsultadas($data) >0 ){ 
			while($res = $con->obnerFila($data)){
				$rowEmpresa[] = $res;
			}
		}else{ $rowEmpresa = NULL;}
	}else{ $rowEmpresa = NULL;}


//------------  Obtener Datos de la Facturas sin cierre.
	$query = "SELECT ffp.forpa_Descripcion as 'nomForma', csec.seemca_Nombre as 'nomCaja', 
					 SUM(round(ft.teso_Importe)) as 'cantidad'  FROM fac_tesoreria as ft 
			INNER JOIN conf_sedes_empresa_cajas as csec on ft.teso_IdCaja = csec.seemca_Id
			INNER JOIN fac_formas_pago as ffp on ft.teso_IdFormaPago = ffp.forpa_Id
					where ft.teso_Cierre = 0 and ft.teso_IdCaja = $idDocumento 
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
if($rowDocuClien[0]['cantidad']){
	$valorEfectivo= $rowDocuClien[0]['cantidad'];
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
		WHERE fpc.paca_Cierre = 0 and fpc.paca_IdCaja = $idDocumento";

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
				WHERE fbc.bace_Cierre = 0 and fbc.bace_IdCaja = $idDocumento";

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

$valorTotal= ($valorEfectivo + $valorBase) - $valorPagos;

$valor= $valorCredito + $valorTotal;

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
			<!--<img src="images/logo.jpeg">-->
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
		<strong>Forma de Pago EFECTIVO</strong>
		</td>
	</tr>
	<tr>
		<td style="width:80px;margin:auto;text-align:left">
			Ventas
		</td>
		<td style="width:80px;margin:auto;text-align:left">
			$valorEfectivo
		</td>
	</tr>
	<tr>
		<td style="width:80px;margin:auto;text-align:left">
			Base
		</td>
		<td style="width:80px;margin:auto;text-align:left">
			$valorBase
		</td>
	</tr>
	<tr>
		<td style="width:80px;margin:auto;text-align:left">
			Pagos a Caja
		</td>
		<td style="width:80px;margin:auto;text-align:left">
			- $valorPagos
		</td>
	</tr>
	<tr>
		<td style="width:80px;margin:auto;text-align:left">
			TOTAL
		</td>
		<td style="width:80px;margin:auto;text-align:left">
			 $valorTotal
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
$bloque4 = <<<EOF

<table border="0.1" style="font-size:6px; text-align:center">
	<tr>
		<td style="width:160px;margin:auto;text-align:center">
			<strong>Forma de Pago CREDITO</strong>
		</td>

	</tr>
	<tr>
		<td style="width:80px;margin:auto;text-align:left">
			Ventas
		</td>
		<td style="width:80px;margin:auto;text-align:left">
			$valorCredito
		</td>
	</tr>	
	<tr>
		<td style="width:80px;margin:auto;text-align:left">
			TOTAL
		</td>
		<td style="width:80px;margin:auto;text-align:left">
			$valorCredito
		</td>
	</tr>
</table>

EOF;

$pdf->writeHTML($bloque4, false, false, false, false, '');
	



//---------------------------------------------------------
// -------------- Información de Productos ---------------
$bloque5 = <<<EOF
<table style="font-size:8px; text-align:center">
	<tr>
		<td style="width:160px;">
		</td>
	</tr>	
	<tr>
		<td style="width:160px;">		
			<b> Resumen de Cuadre Caja</b>
		</td>
	</tr>
	<tr>
		<td style="width:160px;">
		</td>
	</tr>
</table>

<table border="0.1" style="font-size:6px; text-align:center">
	<tr>
		<td style="width:80px;margin:auto;text-align:left">
			EFECTIVO
		</td>
		<td style="width:80px;margin:auto;text-align:left">
			$valorTotal
		</td>
	</tr>
	<tr>
		<td style="width:80px;margin:auto;text-align:left">
			CREDITO
		</td>
		<td style="width:80px;margin:auto;text-align:left">
			$valorCredito
		</td>
	</tr>	
	<tr>
		<td style="width:80px;margin:auto;text-align:left">
			TOTAL
		</td>
		<td style="width:80px;margin:auto;text-align:left">
			$valor
		</td>
	</tr>
</table>

EOF;

$pdf->writeHTML($bloque5, false, false, false, false, '');
	

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