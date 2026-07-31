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

$query = "SELECT coe.*, CSE.*, (select conmu.mun_Nombre from conf_municipios as conmu 
WHERE conmu.mun_Id = CSE.seem_IdMunicipio) as mun_Nombre from conf_empresa as coe 
INNER JOIN conf_sedes_empresa AS CSE ON coe.emp_Id  = CSE.seem_IdEmpresa";

	$data = $con->consultar($query);
	if($data != NULL && $data != ""){
		if( $con->getNumeroFilasConsultadas($data) >0 ){ 
			while($res = $con->obnerFila($data)){
				$rowEmpresa[] = $res;
			}
		}else{ $rowEmpresa = NULL;}
	}else{ $rowEmpresa = NULL;}


//------------  Obtener Datos de la Factura y Cliente.

$query = "SELECT kar.*, (select pro.prov_Nombre from inv_proveedores as pro 
						WHERE pro.prov_Id= kar.kar_IdProveedor) as nombreProveedor, 
				(select pro.prov_Nit from inv_proveedores as pro 
				WHERE pro.prov_Id= kar.kar_IdProveedor) as nitProveedor 
		FROM inv_kardex as kar WHERE kar.kar_id = $idDocumento";

	$data = $con->consultar($query);
	if($data != NULL && $data != ""){
		if( $con->getNumeroFilasConsultadas($data) >0 ){ 
			while($res = $con->obnerFila($data)){
				$rowDocuClien[] = $res;
			}
		}else{ $rowDocuClien = NULL;}
	}else{ $rowDocuClien = NULL;}
		

//-------------  Obtener Prodcutos de la Facturación.
	$con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();

	$query= "SELECT dekar.detkar_NombreFacturar as nombre, 
				round(dekar.detkar_CantidadEntrada) as cantidad,
				round(detkar_ValorEntrada) as total FROM inv_detalle_kardex as dekar
			WHERE dekar.detkar_IdKardex = $idDocumento";

	$data = $con->consultar($query);
	if($data != NULL && $data != ""){
		if( $con->getNumeroFilasConsultadas($data) >0 ){
			while($res = $con->obnerFila($data)){
				$productos[] = $res;
			}
		}else{ $productos = NULL;}
	}else{ $productos = NULL;}



$factu_remi= "Recibo de Compra";

$total= $rowDocuClien[0]['doc_ValorNeto'];
$subtotal= $rowDocuClien[0]['doc_Subtotal'];

if($rowDocuClien[0]['kar_TipoPago'] == 1){
	$formaPago='CONTADO';
}else {
	$formaPago='CREDITO';
}

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
		<td style="width:50px;">
			<img src="images/logo.jpeg">
		</td>
		<td style="width:120px; font-size:10px">
				$item[emp_NombreComercial] <br>
			<em style="width:130px;font-size:8px"align="center">
				NIT: $item[emp_Nit]
			</em><br>
		</td>
	</tr>
</table>


<table border="0.1" style="font-size:6px; ">
	<tr>
		<td style="width:33px;text-align:right">
			Dirección: 
		</td>
		<td style="width:127px;text-align:center">
			$item[seem_Direccion]
		</td>
	</tr>

	<tr>
		<td style="width:33px;text-align:right">
			Ciudad: 
		</td>
		<td style="width:51px;text-align:center">
			$item[mun_Nombre]
		</td>
		<td style="width:32px;text-align:center">
			Telefono: 
		</td>
		<td style="width:44px;text-align:center">
			$item[seem_Telefono]
		</td>
	</tr>

	<tr>
		<td style="width:33px;text-align:right">
			Email:
		</td>
		<td style="width:127'px;text-align:center">
			$item[seem_Email]
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

$pdf->writeHTML($bloque1, false, false, false, false, '');
}

//----------------------------------------------------------
// -------------- Información de factutación ---------------
foreach ($rowDocuClien as $key => $item) {
$bloque2 = <<<EOF

<table style="font-size:8px; text-align:center">
	<tr>
		<td style="width:160px;">		
			<b> $factu_remi N°: </b>$item[kar_Id]
		</td>
	</tr>

	<tr>
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
		<td style="width:40px;">
			Fecha: 
		</td>
		<td style="width:120px;">
			$item[kar_Fecha]
		</td>
	</tr>

	<tr>
		<td style="width:40px;">
			Proveedor: 
		</td>
		<td style="width:40px">
			$item[nombreProveedor]
		</td>
		<td style="width:38px;">
			NIT/Cedula: 
		</td>
		<td style="width:42px;">
			$item[nitProveedor]
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
			Total		
		</td>
	</tr>

</table>

EOF;

$pdf->writeHTML($bloque4, false, false, false, false, '');



//---------------------------------------------------------
// -------------- Listado de Productos --------------------
$totalProductos=0;
foreach ($productos as $key => $item) {

$bloque5 = <<<EOF

<table border="0.1" style="font-size:6px; text-align:center">
	<tr>
		<td style="width:80px; text-align:left">
			$item[nombre] 
		</td>
		<td style="width:30px; text-align:left">
			$item[cantidad]
		</td>
		<td style="width:50px; text-align:left">
			$item[total]
		</td>
	</tr>
</table>

EOF;
$totalProductos= $totalProductos+ $item[total] ; 

$pdf->writeHTML($bloque5, false, false, false, false, '');

}

//---------------------------------------------------------
// -------------- Discriminación de Impuestos Totales -----
$bloque7 = <<<EOF

<table WIDTH="160" style="font-size:6px; text-align:right"  border="0.1">
	<tr>
		<td style="width:110px;">
			Total: 
		</td>
		<td style="width:50px;font-size:7px;text-align:left">
			$ $totalProductos
		</td>
	</tr>
	<tr>
		<td style="width:110px; text-align:right" >
			Tipo de Pago: 
		</td>

		<td style="width:50px;text-align:left">
			$formaPago
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

$pdf->writeHTML($bloque7, false, false, false, false, '');


//---------------------------------------------------------
// -------------- Información DIGITSOFT ---------
$bloque13 = <<<EOF
<table style="font-size:6px; text-align:center">
	<tr>
		<td style="width:160px;font-size:5px">		
			Desarrollado por DigitSoft - Software Ventas e Inventarios
		</td>
	</tr>
	<tr>
		<td style="width:160px;font-size:5px">
			www.digitsoft.com.co - 3215434380
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