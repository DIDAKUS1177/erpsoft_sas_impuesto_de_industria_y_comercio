<?php

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/DAO/DAO_FacturaDocumento.php';
include_once SERVER . '/business/DAO/DAO_FacturaDetalleDocumento.php';
include_once SERVER . '/business/DAO/DAO_Tesoreria.php';
include_once SERVER . '/business/class.sessions.php';

class imprimirFactura{

public $codigo;
public $prefijo;

public function traerImpresionFactura(){

// Id Documento Factura.
$idDocumento = $this->codigo;

$con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();

	$query = "SELECT coe.*, CSE.*, (select conmu.mun_Nombre from conf_municipios as conmu 
			WHERE conmu.mun_Id = CSE.seem_IdMunicipio) as mun_Nombre from fac_documento as fad 
			INNER JOIN conf_sedes_empresa_cajas as csec on fad.doc_IdSerieCaja = csec.seemca_Id 
			INNER JOIN conf_sedes_empresa AS CSE ON csec.seemca_IdSedeEmpresa = CSE.seem_Id 
			INNER JOIN conf_empresa as coe on CSE.seem_IdEmpresa = coe.emp_Id WHERE fad.doc_Id = $idDocumento";

	$data = $con->consultar($query);
	if($data != NULL && $data != ""){
		if( $con->getNumeroFilasConsultadas($data) >0 ){ 
			while($res = $con->obnerFila($data)){
				$rowEmpresa[] = $res;
			}
		}else{ $rowEmpresa = NULL;}
	}else{ $rowEmpresa = NULL;}

	$texto = $rowEmpresa[0]['emp_TextoFactura'];

//------------  Obtener Datos de la Factura y Cliente.
	$query = "SELECT fd.*, round(fd.doc_ValorNeto) as 'doc_ValorNeto', fd.doc_Prefijo as 'prefijo', fd.doc_Numero as 'numero', 
					fd.doc_Fecha as 'fecha', fcl.cli_RazonSocial as 'cliente',
					fcl.cli_Identificacion as 'idClinete', cusu.usu_Usuario as 'vendedor', 
					round(fd.doc_ValorImpuestos) as 'valorImpuestos', 
					round(fd.doc_ValorBruto) as 'doc_ValorBruto',
					cse.seemca_Nombre as 'caja'  FROM fac_documento_cotizaciones as fd 
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
	$query = "SELECT ipp.pro_Nombre as 'nombre', fdd.detDoc_Cantidad as 'cantidad', ium.uniM_Prefijo as 'unidad',
				round(fdd.detDoc_ValorTotal) AS 'total', round(fdd.detDoc_ValorUnitario) AS 'uni'
				FROM fac_detalle_documento_cotizaciones as fdd 
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

	$factu_remi= "Cotización";
	$tipoRegimen= "";


if($rowDocuClien[0]['doc_Estado'] == 1){
	$anulada= "";
}else{
	$anulada= "Anulada";
}


$total= number_format( $rowDocuClien[0]['doc_ValorNeto'], 0,',','.');
$subtotal= number_format( $rowDocuClien[0]['doc_Subtotal'], 0,',','.');
$redondeo= number_format( $rowDocuClien[0]['doc_Redondeo'], 0,',','.');
$descuento= number_format( $rowDocuClien[0]['doc_Descuento'], 0,',','.');
$tipoDoc= $rowDocuClien[0]['doc_IdTipoDocumento'];


//REQUERIMOS LA CLASE TCPDF
require_once('tcpdf_include.php');

$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

$pdf->SetMargins(10, 5, 10);
$pdf->setPrintHeader(false);
$pdf->SetHeaderMargin(0);
$pdf->setPrintFooter(false);
$pdf->SetFooterMargin(0);

$pdf->SetAutoPageBreak(TRUE, 0);

$pdf->AddPage('P', 'A6');

// -------------- Información de la EMPRESA---------------
// -------------- Configuración por Defecto - LOGO AL LADO DERECHO ---------------

foreach ($rowEmpresa as $key => $item) {
$bloque1 = <<<EOF

<table style="font-size:15px; text-align:center">
	<tr>
		<td style="width:50px;">
			<img src="$item[emp_UrlSoporteLogo]">
		</td>
		<td style="width:120px; font-size:10px">
				$item[emp_NombreComercial] <br>
			<em style="width:130px;font-size:8px"align="center">
				NIT: $item[emp_Nit]
			</em><br>
			<em style="width:100px;font-size:6px" align="center">
				$tipoRegimen <br>
			</em>
		</td>
	</tr>
</table>

EOF;

//$pdf->writeHTML($bloque1, false, false, false, false, '');
}


// -------------- Información de la EMPRESA---------------
// -------------- Configuración LOGO CENTRAL ---------------

foreach ($rowEmpresa as $key => $item) {
$bloque1 = <<<EOF

<table style="text-align:center; width:180px">
	<tr>
		<td>
			<img src="$item[emp_UrlSoporteLogo]" style="width:80px">
		</td>
	</tr>
</table>
<table style="font-size:15px; text-align:center">
	<tr>
		<td style="width:170px; font-size:10px">
				$item[emp_NombreComercial] <br>
			<em style="width:130px;font-size:8px"align="center">
				NIT: $item[emp_Nit]
			</em><br>
			<em style="width:100px;font-size:6px" align="center">
				$tipoRegimen <br>
			</em>
		</td>
	</tr>
</table>

EOF;

$pdf->writeHTML($bloque1, false, false, false, false, '');
}	


foreach ($rowEmpresa as $key => $item) {
	$bloque1 = <<<EOF
	
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
			<b> $factu_remi N°: </b>$item[prefijo] - $item[numero]
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
			Cliente: 
		</td>
		<td style="width:46px">
			$item[cliente]
			<!-- $item[cliente] - $item[doc_campoPersonalizado] -->
		</td>
		<td style="width:38px;">
			NIT/Cedula: 
		</td>
		<td style="width:42px;">
			$item[idClinete]
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
			Caja:
		</td>
		<td style="width:42px;">
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

<table border="0.1" style="font-size:6px; text-align:center">
	<tr>
		<td style="width:60px;margin:auto;text-align:left">
			Descripción
		</td>
		<td style="width:25px;margin:auto;text-align:left">
			Cant.
		</td>
		<td style="width:37px;margin:auto;text-align:left">
			Vr. Unitario		
		</td>
		<td style="width:38px;margin:auto;text-align:left">
			Total		
		</td>
	</tr>

</table>

EOF;

$pdf->writeHTML($bloque4, false, false, false, false, '');



//---------------------------------------------------------
// -------------- Listado de Productos --------------------
foreach ($productos as $key => $item) {
	
$format_numm = number_format( $item['total'], 0,',','.');
$format_numm_uni = number_format( $item['uni'], 0,',','.');

$bloque5 = <<<EOF

<table border="0.1" style="font-size:6px; text-align:center">
	<tr>
		<td style="width:60px; text-align:left">
			$item[nombre] 
		</td>
		<td style="width:25px; text-align:left">
			$item[cantidad] $item[unidad]
		</td>
		<td style="width:37px;margin:auto;text-align:left">
			$ $format_numm_uni
		</td>
		<td style="width:38px; text-align:left">
			$ $format_numm
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
		<td style="width:122px; text-align:right" >
			 SubTotal:
		</td>

		<td style="width:38px;text-align:left">
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
// -------------- Discriminación de Impuestos Totales -----
$bloque7 = <<<EOF

<table WIDTH="160" style="font-size:6px; text-align:right"  border="0.1">
	<tr>
		<td style="width:110px;">
			Descuento: 
		</td>
		<td style="width:50px;font-size:7px;text-align:left">
			$ $descuento
		</td>
	</tr>
	<tr>
		<td style="width:110px;">
			Redondeo: 
		</td>
		<td style="width:50px;font-size:7px;text-align:left">
			$ $redondeo
		</td>
	</tr>
	<tr>
		<td style="width:110px;">
			Total: 
		</td>
		<td style="width:50px;font-size:7px;text-align:left">
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
	

$pdf->writeHTML($bloque7, false, false, false, false, '');



//---------------------------------------------------------
// -------------- Información del campo Personalizado -----------------
foreach ($rowDocuClien as $key => $item) {
	$bloque3 = <<<EOF
	
	<table border="0.1" style="font-size:6px; text-align:center">		
		<tr>
			<td style="width:34px;">
				Notas: 
			</td>
			<td style="width:126px">
				$item[doc_campoPersonalizado]
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

if($rowDocuClien[0]['doc_campoPersonalizado'] != NULL){
	$pdf->writeHTML($bloque3, false, false, false, false, '');
}
}


//---------------------------------------------------------
// -------------- Información DIGITSOFT ---------
$bloque13 = <<<EOF
<table style="font-size:6px; text-align:center">
	<tr>
		<td style="width:160px;font-size:5px">		
			Desarrollado por: <br> <b>DigitSoft _ </b>DS-POS
		</td>
	</tr>
	<tr>
		<td style="width:160px;font-size:5px">
			www.digitsoft.com.co - 3215434380
		</td>    		
	</tr>    	
</table>

EOF;

//if($valorVenta1 != 100 and $valorVenta1 != 200 and $valorVenta1 != 300 and $valorVenta1 != 400 ){
$pdf->writeHTML($bloque13, false, false, false, false, '');
//}



//---------------------------------------------------------
ob_end_clean();
$pdf->Output('cotizacion.pdf');
}

}
$factura = new imprimirFactura();
$factura -> codigo = $_GET["codigo"];
$factura -> traerImpresionFactura();

?>