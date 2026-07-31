<?php

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
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
					where csec.seemca_Id =1";
	$data = $con->consultar($query);
	if($data != NULL && $data != ""){
		if( $con->getNumeroFilasConsultadas($data) >0 ){ 
			while($res = $con->obnerFila($data)){
				$rowEmpresa[] = $res;
			}
		}else{ $rowEmpresa = NULL;}
	}else{ $rowEmpresa = NULL;}


//------------  Obtener desglose de pagos a caja. -------------------- 
$query = "SELECT TRUNCATE(paca_Valor,0) as valor, paca_Observaciones as descripcion, paca_FechaCreacion as fecha,
(SELECT tipo.tipa_Nombre from fac_tipos_pagos as tipo WHERE tipo.tipa_Id=pc.paca_IdTipoPago) as tipoPago,
(SELECT subtipo.subtipa_Nombre from fac_sub_tipos_pagos as subtipo WHERE subtipo.subtipa_Id=pc.paca_IdSubTipoPago) as subTipoPago
		FROM fac_pagos_caja as pc where pc.paca_Id  = $idDocumento;";
$data = $con->consultar($query);
if($data != NULL && $data != ""){
if( $con->getNumeroFilasConsultadas($data) >0 ){ 
while($res = $con->obnerFila($data)){
	$pagosCaja[] = $res;
}
}else{ $pagosCaja = NULL;}
}else{ $pagosCaja = NULL;}

$hoy= $pagosCaja[0]['fecha'];

//REQUERIMOS LA CLASE TCPDF
require_once('tcpdf_include.php');

$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);


$pdf->SetMargins(9, 2, 0);
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
			<b> Egreso de Caja: </b>$item[seemca_Nombre]
		</td>
	</tr>
	<tr>
		<td style="width:160px; font-size:6px">		
			<b> Fecha: </b>$hoy 
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
	// -------------- Pagos a Caja ---------------
	$bloque6 = <<<EOF
	<table border="0.1" style="font-size:6px; text-align:center">
		<tr>
			<td style="width:70px;margin:auto;text-align:left">
				<strong>Observaciónes</strong>
			</td>
			<td style="width:50px;margin:auto;text-align:left">
				<strong>Tipo/Sub Tipo</strong>
			</td>
			<td style="width:40px;margin:auto;text-align:left">
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
	$format_cant= number_format( $item['valor'], 0,',','.');

	$bloque7 = <<<EOF

	<table border="0.1" style="font-size:6px; text-align:center">
		<tr>
			<td style="width:70px;margin:auto;text-align:left">
				$item[descripcion]
			</td>
			<td style="width:50px;margin:auto;text-align:left">
				$item[subTipoPago] <br> $item[tipoPago] 
			</td>
			<td style="width:40px;margin:auto;text-align:left">
				$ $format_cant
			</td>
		</tr>	
	</table>

	EOF;

	$pdf->writeHTML($bloque7, false, false, false, false, '');
		
	}
	
	



//---------------------------------------------------------
// -------------- Información DIGITSOFT ---------
$bloque9 = <<<EOF
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

$pdf->writeHTML($bloque9, false, false, false, false, '');

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