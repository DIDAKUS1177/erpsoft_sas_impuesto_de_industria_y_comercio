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

$con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();

//------------  Obtener Datos de la Factura y Cliente.
	
	$query = "SELECT ip.pro_Nombre, ip.pro_CodBarras, round(fpv.preVen_PrecioNeto)   as precio
				FROM inv_producto as ip
				INNER JOIN fac_precios_venta as fpv 
					on ip.pro_Id = fpv.preVen_IdProducto";
	$data = $con->consultar($query);
	if($data != NULL && $data != ""){
		if( $con->getNumeroFilasConsultadas($data) >0 ){ 
			while($res = $con->obnerFila($data)){
				$rowDocuClien[] = $res;
			}
		}else{ $rowDocuClien = NULL;}
	}else{ $rowDocuClien = NULL;}
		


//REQUERIMOS LA CLASE TCPDF
require_once('tcpdf_include.php');

$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

$pdf->SetMargins(0, 2, 0);
$pdf->setPrintHeader(false);
$pdf->SetHeaderMargin(0);
$pdf->setPrintFooter(false);
$pdf->SetFooterMargin(0);

$pdf->SetAutoPageBreak(TRUE,0);

//$pdf->AddPage('P', 'A7');
$pdf->AddPage('P',[60,10000]);

//---------------------------------------------------------
// -------------- Información del Cliente -----------------
foreach ($rowDocuClien as $key => $item) {
$bloque3 = <<<EOF

<table border="0.1" style="font-size:1px; text-align:center">
	
	<tr>
		<td style="width:160px;font-size:15px">
			$item[pro_Nombre]
		</td>
	</tr>

	<tr>
		<td style="width:160px;font-size:20px;">
			 $ $item[precio]
		</td>
	</tr>
	<tr>
		<td style="width:160px;;font-size:10px">
			$item[pro_CodBarras]
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



// ---------------------------------------------------------
//SALIDA DEL ARCHIVO 

//$pdf->Output('factura.pdf', 'D');
$pdf->Output('factura.pdf');

}

}

$factura = new imprimirFactura();
$factura -> traerImpresionFactura();

?>