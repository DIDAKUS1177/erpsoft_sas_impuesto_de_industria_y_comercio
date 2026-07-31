<?php

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/DAO/DAO_FacturaDocumentoOrdenes.php';
include_once SERVER . '/business/DAO/DAO_FacturaDetalleDocumentoOrdenes.php';
include_once SERVER . '/business/DAO/DAO_Tesoreria.php';
include_once SERVER . '/business/class.sessions.php';

class imprimirFactura{

public $codigo;
public $prefijo;

public function traerImpresionFactura(){

// Id Documento Factura.
$idDocumento = $this->codigo;

$con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();

//------------  Obtener Datos de la EMPRESA.
/*	$query = "SELECT *  FROM conf_empresa as ce INNER JOIN conf_sedes_empresa as cse 
	on ce.emp_Id = cse.seem_IdEmpresa 
	INNER JOIN conf_municipios AS comuni on cse.seem_IdMunicipio = comuni.mun_Id limit 1";  */

	$query = "SELECT * FROM conf_empresa as coe";

	$data = $con->consultar($query);
	if($data != NULL && $data != ""){
		if( $con->getNumeroFilasConsultadas($data) >0 ){ 
			while($res = $con->obnerFila($data)){
				$rowEmpresa[] = $res;
			}
		}else{ $rowEmpresa = NULL;}
	}else{ $rowEmpresa = NULL;}

//------------  Obtener Datos de la ORDEN
	$query = "SELECT fd.*, round(fd.doc_ValorNeto) as 'doc_ValorNeto', fd.doc_Numero as 'numero', 
					fd.doc_Fecha as 'fecha', cusu.usu_Nombre as 'vendedor', 
					cse.seemma_Nombre as 'mesa' FROM fac_documento_ordenes as fd
				INNER JOIN conf_sedes_empresa_mesas as cse ON fd.doc_IdMesa = cse.seemma_Id
				INNER join conf_usuario as cusu ON fd.doc_IdVendedor = cusu.usu_Id
				where fd.doc_Id= $idDocumento";
	$data = $con->consultar($query);
	if($data != NULL && $data != ""){
		if( $con->getNumeroFilasConsultadas($data) >0 ){ 
			while($res = $con->obnerFila($data)){
				$rowDocuClien[] = $res;
			}
		}else{ $rowDocuClien = NULL;}
	}else{ $rowDocuClien = NULL;}
		

//-------------  Obtener Prodcutos de la ORDEN.
	$con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
	$query = "SELECT ipp.pro_Nombre as 'nombre', fdd.detDoc_Cantidad as 'cantidad', 
				ium.uniM_Prefijo as 'unidad', round(fdd.detDoc_ValorTotal) AS 'total'
				FROM fac_detalle_documento_ordenes as fdd 
			INNER JOIN inv_producto as ipp ON fdd.detDoc_IdProducto= ipp.pro_Id 
			INNER JOIN inv_unidad_medida as ium ON ipp.pro_UnidadMed= ium.uniM_Id
				where fdd.detDoc_IdDocumento = $idDocumento";

	$data = $con->consultar($query);
	if($data != NULL && $data != ""){
		if( $con->getNumeroFilasConsultadas($data) >0 ){
			while($res = $con->obnerFila($data)){
				$productos[] = $res;
			}
		}else{ $productos = NULL;}
	}else{ $productos = NULL;}

	$factu_remi= "Orden de Mesa";

if($rowDocuClien[0]['doc_Estado'] == 1){
	$anulada= "";
}else{
	$anulada= "Cerrada";
}

if($rowDocuClien[0]['doc_Observaciones'] != null){
	$observacionesOrden = $rowDocuClien[0]['doc_Observaciones'];
}else{
	$observacionesOrden= "";
}

if($rowDocuClien[0]['doc_NombreDomi'] != null){
	$nombreDomi = $rowDocuClien[0]['doc_NombreDomi'];
	$telefonoDomi = $rowDocuClien[0]['doc_TelefonoDomi'];
	$direccionDomi = $rowDocuClien[0]['doc_DireccionDomi'];
}else{
	$nombreDomi= "";
	$telefonoDomi= "";
	$direccionDomi= "";
}

$subtotal= number_format( $rowDocuClien[0]['doc_ValorNeto'], 0,',','.');


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

<table style="font-size:15px; text-align:center">
	<tr>
		<td style="width:170px; font-size:9px">
				$item[emp_NombreComercial] <br>
			<em style="width:130px;font-size:7px"align="center">
				NIT: $item[emp_Nit]
			</em>
		</td>
	</tr>
</table>

EOF;

$pdf->writeHTML($bloque1, false, false, false, false, '');
}

//----------------------------------------------------------
// -------------- Información de factutación ---------------
foreach ($rowDocuClien as $key => $item) {
$bloque2 = <<<EOF

<table style="font-size:8px; text-align:center">
	<tr>
		<td style="width:160px;">		
			<b> $factu_remi N°: </b> $item[numero]
		</td>
	</tr>

	<tr>
		<td style="width:160px;">		
			<b> $anulada </b>
		</td>
		<td style="width:160px;">
		</td>
	</tr>
</table>

EOF;

$pdf->writeHTML($bloque2, false, false, false, false, '');
}



//---------------------------------------------------------
// -------------- Información del Cliente -----------------
foreach ($rowDocuClien as $key => $item) {
$bloque3 = <<<EOF

<table border="0.1" style="font-size:6px; text-align:center">
	
	<tr>
		<td style="width:34px;">
			Fecha: 
		</td>
		<td style="width:126px;">
			$item[fecha]
		</td>
	</tr>

	<tr>
		<td style="width:34px;">
			Vendedor: 
		</td>
		<td style="width:46px;">
			$item[vendedor]
		</td>
		<td style="width:38px;">
			Mesa:
		</td>
		<td style="width:42px;">
			$item[mesa]
		</td>
	</tr>
</table>

<table style="font-size:8px; text-align:center">

	<tr>
		<td style="width:160px;">
		</td>
	</tr>

</table>

EOF;

$pdf->writeHTML($bloque3, false, false, false, false, '');
}


//---------------------------------------------------------
// -------------- Información de Productos ---------------
$bloque4 = <<<EOF

<table border="0.1" style="font-size:6px; text-align:center">
	<tr>
		<td style="width:80px;margin:auto;text-align:left">
			Descripción
		</td>
		<td style="width:30px;margin:auto;text-align:left">
			Cantidad
		</td>
		<td style="width:50px;margin:auto;text-align:left">
			Valor		
		</td>
	</tr>

</table>

EOF;

$pdf->writeHTML($bloque4, false, false, false, false, '');



//---------------------------------------------------------
// -------------- Listado de Productos --------------------
foreach ($productos as $key => $item) {
	
$format_numm = number_format( $item['total'], 0,',','.');

$bloque5 = <<<EOF

<table border="0.1" style="font-size:6px; text-align:center">
	<tr>
		<td style="width:80px; text-align:left">
			$item[nombre] 
		</td>
		<td style="width:30px; text-align:left">
			$item[cantidad] $item[unidad]
		</td>
		<td style="width:50px; text-align:left">
			$format_numm
		</td>
	</tr>
</table>

EOF;

$pdf->writeHTML($bloque5, false, false, false, false, '');

}


//---------------------------------------------------------
// -------------- Total de la Factura --------------
$bloque6 = <<<EOF

<table border="0.1" style="font-size:6px; text-align:center">
	<tr>
		<td style="width:110px; text-align:right" >
			 SubTotal:
		</td>

		<td style="width:50px;text-align:left">
			$ $subtotal
		</td>
	</tr>
</table>

<table style="font-size:8px; text-align:center">
	<tr>
		<td style="width:160px;">
		</td>
	</tr>
</table>

EOF;

$pdf->writeHTML($bloque6, false, false, false, false, '');


//---------------------------------------------------------
// -------------- Listado de Observaciones --------------------

if($observacionesOrden != ''){

$bloque5 = <<<EOF

<table border="0.1" style="font-size:6px; text-align:center">
	<tr>
		<td style="width:160px; text-align:center">
			<b> OBSERVACIONES DE LA ORDEN </b>
		</td>
	</tr>
	<tr>
		<td style="width:160px; text-align:left">
			$observacionesOrden
		</td>
	</tr>
</table>

<table style="font-size:8px; text-align:center">
	<tr>
		<td style="width:160px;">
		</td>
	</tr>
</table>


EOF;

$pdf->writeHTML($bloque5, false, false, false, false, '');
}


//---------------------------------------------------------
// -------------- Listado de Observaciones --------------------

if($nombreDomi != ''){

$bloque5 = <<<EOF

<table border="0.1" style="font-size:6px; text-align:center">
	<tr>
		<td style="width:160px; text-align:center">
			<b> PARA DOMICILIO </b>
		</td>
	</tr>
<!--
	<tr>
		<td style="width:100px; text-align:center">
			$nombreDomi
		</td>
		<td style="width:60px; text-align:center">
			$telefonoDomi
		</td>
	</tr>
	<tr>
		<td style="width:160px; text-align:center">
			$direccionDomi
		</td>
	</tr>
-->
</table>

<table style="font-size:8px; text-align:center">
	<tr>
		<td style="width:160px;">
		</td>
	</tr>
</table>


EOF;

$pdf->writeHTML($bloque5, false, false, false, false, '');
}


	


//---------------------------------------------------------
// -------------- Información DIGITSOFT ---------
$bloque13 = <<<EOF
<table style="font-size:6px; text-align:center">
	<tr>
		<td style="width:160px;font-size:5px">		
			<b>DigitSoft _ </b>DS-POS
		</td>
	</tr>
	<tr>
		<td style="width:160px;font-size:5px">
			3215434380
		</td>    		
	</tr>    	
</table>

EOF;

$pdf->writeHTML($bloque13, false, false, false, false, '');

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