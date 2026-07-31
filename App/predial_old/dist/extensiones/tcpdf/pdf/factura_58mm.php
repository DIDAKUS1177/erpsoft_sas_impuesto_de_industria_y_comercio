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
	$query = "SELECT *  FROM conf_empresa as ce INNER JOIN conf_sedes_empresa as cse 
	on ce.emp_Id = cse.seem_IdEmpresa 
	INNER JOIN conf_municipios AS comuni on cse.seem_IdMunicipio = comuni.mun_Id limit 1";
	$data = $con->consultar($query);
	if($data != NULL && $data != ""){
		if( $con->getNumeroFilasConsultadas($data) >0 ){ 
			while($res = $con->obnerFila($data)){
				$rowEmpresa[] = $res;
			}
		}else{ $rowEmpresa = NULL;}
	}else{ $rowEmpresa = NULL;}


//------------  Obtener Datos de la Factura y Cliente.
	$query = "SELECT fd.*, round(fd.doc_ValorNeto) as 'doc_ValorNeto', fd.doc_Prefijo as 'prefijo', fd.doc_Numero as 'numero', 
					fd.doc_Fecha as 'fecha', fcl.cli_RazonSocial as 'cliente',
					fcl.cli_Identificacion as 'idClinete', cusu.usu_Nombre as 'vendedor', 
					round(fd.doc_ValorImpuestos) as 'valorImpuestos', 
					round(fd.doc_ValorBruto) as 'doc_ValorBruto',
					cse.seemca_Nombre as 'caja'  FROM fac_documento as fd 
			INNER JOIN fac_cliente as fcl ON fd.doc_IdCliente = fcl.cli_Id
			INNER JOIN conf_sedes_empresa_cajas as cse ON fd.doc_IdSerieCaja=cse.seemca_Id
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
		

//-------------  Obtener Prodcutos de la Facturación.
	$con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
	$query = "SELECT ipp.pro_Nombre as 'nombre', fdd.detDoc_Cantidad as 'cantidad', 
				round(fdd.detDoc_ValorTotal) AS 'total', fpg.forpa_Descripcion as 'formaPago'
				FROM fac_detalle_documento as fdd 
			INNER JOIN inv_producto as ipp ON fdd.detDoc_IdProducto= ipp.pro_Id 
			INNER JOIN fac_tesoreria as ft ON fdd.detDoc_IdDocumento = ft.teso_IdDocumento
			INNER JOIN fac_formas_pago as fpg ON ft.teso_IdFormaPago = fpg.forpa_Id
				where fdd.detDoc_IdDocumento = $idDocumento";

	$data = $con->consultar($query);
	if($data != NULL && $data != ""){
		if( $con->getNumeroFilasConsultadas($data) >0 ){
			while($res = $con->obnerFila($data)){
				$productos[] = $res;
			}
		}else{ $productos = NULL;}
	}else{ $productos = NULL;}


//-------------  Obtener Impuestos de la Facturación.
	$con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
	$query = "SELECT ipp.pro_Nombre as 'nombre', fdd.detDoc_Cantidad as 'cantidad', 
			round(SUM(fdd.detDoc_ValorTotal)) AS 'total', fpg.forpa_Descripcion as 'formaPago',
			round(SUM(fdd.detDoc_ValorImpuesto)) as 'valorImpuesto',
			round(SUM(fdd.detDoc_ValorTotal)) as 'valorTotal',
			fii.imp_Descripcion as 'nomImpuesto'
				FROM fac_detalle_documento as fdd
			INNER JOIN inv_producto as ipp ON fdd.detDoc_IdProducto= ipp.pro_Id 
			INNER JOIN fac_tesoreria as ft ON fdd.detDoc_IdDocumento = ft.teso_IdDocumento
			INNER JOIN fac_formas_pago as fpg ON ft.teso_IdFormaPago = fpg.forpa_Id
			INNER JOIN fac_impuestos as fii ON ipp.pro_IdImpuesto = fii.imp_Id
				where fdd.detDoc_IdDocumento =$idDocumento GROUP by fii.imp_Id";

	$data = $con->consultar($query);
	if($data != NULL && $data != ""){
		if( $con->getNumeroFilasConsultadas($data) >0 ){
			while($res = $con->obnerFila($data)){
				$impuetos[] = $res;
			}
		}else{ $impuetos = NULL;}
	}else{ $impuetos = NULL;}



//-------------  Obtener Resolucion de la DIAN
	$con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
	$query = "SELECT crr.*  FROM fac_documento as fdd
		INNER JOIN conf_sedes_empresa_cajas as cse ON fdd.doc_IdSerieCaja = cse.seemca_Id
		INNER JOIN conf_resoluciones as crr ON cse.seemca_IdResolucion = crr.reso_Id
			where fdd.doc_Id = $idDocumento";

	$data = $con->consultar($query);
	if($data != NULL && $data != ""){
		if( $con->getNumeroFilasConsultadas($data) >0 ){
			while($res = $con->obnerFila($data)){
				$resoim[] = $res;
			}
		}else{ $resoim = NULL;}
	}else{ $resoim = NULL;}



if($rowDocuClien[0]['doc_IdTipoDocumento'] == 1){
	$factu_remi= "Remisión";
}else{
	$factu_remi= "Factura";
}


$total= $rowDocuClien[0]['doc_ValorNeto'];
$formaPago= $productos[0]['formaPago'];
$tipoDoc= $rowDocuClien[0]['doc_IdTipoDocumento'];


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
		<td style="width:70px;">
			<img src="images/logo.jpeg">
		</td>
		<td style="width:80px; font-size:10px">
				$item[emp_NombreComercial] <br>
			<!--<em style="width:100px;font-size:7px" align="center">
				$item[emp_Nombre] <br>
			</em>-->
			<em style="width:100px;font-size:7px"align="center">
				NIT: $item[emp_Nit]
			</em><br>
			<em style="width:100px;font-size:6px">
				$item[seem_Direccion]
			</em>
			<em style="width:100px;font-size:6px">
				$item[mun_Nombre]
			</em>
			<em style="width:100px;font-size:7px">
				$item[seem_Telefono]
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
			<b> $factu_remi N°: </b>$item[prefijo] - $item[numero]
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

<table border="0.1" style="font-size:7px; text-align:center">
	
	<tr>
		<td style="width:40px;">
			Fecha: 
		</td>
		<td style="width:100px;">
			$item[fecha]
		</td>
	</tr>

	<tr>
		<td style="width:40px;">
			Cliente: 
		</td>
		<td style="width:40px">
			$item[cliente]
		</td>
		<td style="width:30px;">
			Cedula: 
		</td>
		<td style="width:30px;">
			$item[idClinete]
		</td>
	</tr>

	<tr>
		<td style="width:40px;">
			Vendedor: 
		</td>
		<td style="width:40px;">
			$item[vendedor]
		</td>
		<td style="width:30px;">
			Caja:
		</td>
		<td style="width:30px;">
			$item[caja]
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

<table border="0.1" style="font-size:7px; text-align:center">
	<tr>
		<td style="width:75px;margin:auto;text-align:left">
			Descripción
		</td>
		<td style="width:25px;margin:auto;text-align:left">
			Cant
		</td>
		<td style="width:40px;margin:auto;text-align:left">
			Total		
		</td>
	</tr>

</table>

EOF;

$pdf->writeHTML($bloque4, false, false, false, false, '');



//---------------------------------------------------------
// -------------- Listado de Productos --------------------
foreach ($productos as $key => $item) {

$bloque5 = <<<EOF

<table border="0.1" style="font-size:9px; text-align:center">
	<tr>
		<td style="width:75px; text-align:left">
			$item[nombre] 
		</td>
		<td style="width:25px; text-align:left">
			$item[cantidad]
		</td>
		<td style="width:40px; text-align:left">
			$item[total]
		</td>
	</tr>
</table>

EOF;

$pdf->writeHTML($bloque5, false, false, false, false, '');

}


//---------------------------------------------------------
// -------------- Total de la Factura --------------
$bloque6 = <<<EOF

<table border="0.1" style="font-size:9px; text-align:center">
	<tr>
		<td style="width:75px; text-align:right" >
			 Total:
		</td>

		<td style="width:65px;text-align:left">
			$ $total
		</td>
	</tr>
	<tr>
		<td style="width:75px; text-align:right" >
			Medio de Pago: 
		</td>

		<td style="width:65px;text-align:left">
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

$pdf->writeHTML($bloque6, false, false, false, false, '');


//---------------------------------------------------------
// -------------- Información de Impuestos --------------
$bloque7 = <<<EOF

<table WIDTH="140" style="font-size:6px; text-align:right"  border="0.1">
	<tr>
		<td style="margin:auto;font-size:8px;text-align:center">
			Discriminación de Impuestos	
		</td>
	</tr>
	<tr>
		<td WIDTH="60" style="text-align:left">
			Concepto
		</td>
		<td WIDTH="40" style="margin:auto;text-align:left">
			Base Imp
		</td>
		<td WIDTH="40" style="margin:auto;text-align:left">
			Total Imp
		</td>
	</tr>
</table>


EOF;

if($tipoDoc == 2){
$pdf->writeHTML($bloque7, false, false, false, false, '');
}



//---------------------------------------------------------------
// -------------- Discriminación de Impuestos --------------------
foreach ($impuetos as $key => $item) {

$bruto = $item['valorTotal'] -  $item['valorImpuesto'];

$bloque8 = <<<EOF

<table WIDTH="140" style="font-size:6px; text-align:right"  border="0.1">
	<tr>
		<td WIDTH="60" style="text-align:left">
			$item[nomImpuesto]
		</td>	
		<td WIDTH="40" style="margin:auto;text-align:left">
			$item[valorImpuesto]
		</td>	
		<td WIDTH="40" style="margin:auto;text-align:left">
			$bruto
		</td>	
	</tr>
</table>

EOF;

if($tipoDoc == 2){
$pdf->writeHTML($bloque8, false, false, false, false, '');
}
}



//---------------------------------------------------------
// -------------- Discriminación de Impuestos Totales -----

foreach ($rowDocuClien as $key => $item) {
$bloque9 = <<<EOF

<table WIDTH="140" style="font-size:6px; text-align:right"  border="0.1">
	<tr>
		<td WIDTH="60" style="font-size:7px;text-align:left">
			Total Impuestos:
		</td>	
		<td WIDTH="40" style="margin:auto;font-size:7px;text-align:left">
			$item[valorImpuestos]
		</td>	
		<td WIDTH="40" style="margin:auto;font-size:7px;text-align:left">
			$item[doc_ValorBruto]
		</td>
	</tr>
	<tr>
		<td style="width:60px;font-size:7px;">
			 Total: 
		</td>
		<td style="width:80px;font-size:7px;">
			$ $total
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

if($tipoDoc == 2){
$pdf->writeHTML($bloque9, false, false, false, false, '');
}
}


//---------------------------------------------------------
// -------------- Información de Autorizacion DIAN ---------
foreach ($resoim as $key => $item) {

$bloque10 = <<<EOF
<table WIDTH="140" style="font-size:7px; text-align:right" border="0.1">
	<tr>
		<td style="width:75px; text-align:rigth" >
			Autorización DIAN: 
		</td>
		<td style="width:65px;text-align:left">
			$item[reso_Numero]
		</td>
	</tr>
	<tr>
		<td style="width:140px; text-align:center" >
			De $item[reso_Prefijo] $item[reso_NumeroInicial] a $item[reso_Prefijo] $item[reso_NumeroFinal]
		</td>
	</tr>
	<tr>
		<td style="width:140px; text-align:center" >
			Fecha de Autorización: $item[reso_FechaAutorizacion]
		</td>
	</tr>
	<tr>
		<td style="width:140px; text-align:center" >
			Responsable de IVA - No retenemos IVA
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

if($tipoDoc == 2){
$pdf->writeHTML($bloque10, false, false, false, false, '');
}
}

//---------------------------------------------------------
// -------------- Información Gracias Por la COMPRA ---------
$bloque11 = <<<EOF
<table style="font-size:9px; text-align:center">
	<tr>
		<td style="width:140px;font-size:7px">		
			Gracias por su Compra		
		</td>
	</tr>
	<tr>
		<td style="width:140px;">		
			Vuelva Pronto
		</td>    		
	</tr>    	
</table>
<table style="font-size:8px; text-align:center">
	<tr>
		<td style="width:140px;">
		</td>
	</tr>
</table>

EOF;

$pdf->writeHTML($bloque11, false, false, false, false, '');



//---------------------------------------------------------
// -------------- Información DIGITSOFT ---------
$bloque12 = <<<EOF
<table style="font-size:9px; text-align:center">
	<tr>
		<td style="width:140px;font-size:6px">		
			Desarrollado por DigitSoft - Software Ventas e Inventarios
		</td>
	</tr>
	<tr>
		<td style="width:140px;">		
			www.digitsoft.com.co 3215434380
		</td>    		
	</tr>    	
</table>

EOF;

//if($valorVenta1 != 100 and $valorVenta1 != 200 and $valorVenta1 != 300 and $valorVenta1 != 400 ){
$pdf->writeHTML($bloque12, false, false, false, false, '');
//}

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