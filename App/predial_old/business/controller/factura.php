<?php
namespace predial;

use Exception;

include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_FacturaDocumento.php';
include_once SERVER . '/business/DAO/DAO_FacturaDetalleDocumento.php';
include_once SERVER . '/business/DAO/DAO_Tesoreria.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER .'/business/controller/class.logs.php';


class imprimirFactura{

public $codigo;
public $prefijo;

public function traerImpresionFactura(){
	
//TRAEMOS LA INFORMACIÓN DE LA VENTA

//$itemVenta1 = "codigo";
//$itemVenta2= "numero_codigo";
$idDocumento = $this->codigo;
//$valorVenta1 = $_GET["prefijo"];

// Validamos si es factura DIAN o Remisión


		$con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT * FROM fac_documento WHERE doc_Id= $idDocumento";
        $data = $con->consultar($query);
        if($data != NULL && $data != ""){
            if( $con->getNumeroFilasConsultadas($data) >0 ){ 
                while($res = $con->obnerFila($data)){
                    $row[] = $res;
                }
         
            }else{
    
                $row = NULL;
            }
        }else{
            $row = NULL;
		}
		
		

if($row[0]['doc_IdTipoDocumento'] == 1){
	$factu_remi= "Factura";
}else{
	$factu_remi= "Remisión";
}


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

$bloque1 = <<<EOF

<table style="font-size:10px; text-align:center">
	<tr>
		<td style="width:40px;">
			<img src="images/logo.png">
		</td>
		<td style="width:120px; font-size:10px">
			Distribuidora El Mago <br>
			<em style="width:130px;font-size:5px" align="center">
				Jairo Alberto González Guevara <br>
			</em>
			<em style="width:130px;font-size:5px"align="center">
				NIT: 7.224 574-3
			</em><br>
			<em style="width:130px;font-size:5px">
				Cel: 320 8994061 -  Dirección: Kra 11 # 18 - 57
			</em>
		</td>
	</tr>
</table>

<table style="font-size:8px; text-align:center">
	<tr>
		<td style="width:160px;">		
			<b>$factu_remi N°: </b>
		</td>
	</tr>

	<tr>
		<td style="width:160px;">
		</td>
	</tr>

</table>

<!--
<table border="0.1" style="font-size:6px; text-align:center">
	
	<tr>
		<td style="width:160px;">
			NIT: 7.224 574-3
		</td>
	</tr>

	<tr>
		<td style="width:80px;">
			Teléfono: 320 899 4061
		</td>
		<td style="width:80px;">
			Dirección: Kra 11 # 18 - 57
		</td>
	</tr>
	
</table>

<table style="font-size:8px; text-align:center">

	<tr>
		<td style="width:160px;">
		</td>
	</tr>

</table>
-->

<table border="0.1" style="font-size:6px; text-align:center">

	<tr>
		<td style="width:27px;">
			Cliente: 
		</td>
		<td style="width:53px;font-size:7px">
			
		</td>
		<td style="width:38px;">
		NIT/Cedula: 
		</td>
		<td style="width:42px;">
			
		</td>
	</tr>

	<tr>
		<td style="width:27px;">
			Fecha:
		</td>
		<td style="width:53px;">
			
		</td>
		<td style="width:38px;">
			Ruta:
		</td>
		<td style="width:42px;">
			
		</td>
	</tr>

<!--
	<tr>
		<td style="width:40px;">
			Vendedor: 
		</td>
		<td style="width:120px;">
			
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

$pdf->writeHTML($bloque1, false, false, false, false, '');


// ---------------------------------------------------------
//SALIDA DEL ARCHIVO 

//$pdf->Output('factura.pdf', 'D');
$pdf->Output('factura.pdf');

}

}

$factura = new imprimirFactura();
$factura -> codigo = $_GET["codigo"];
$factura -> traerImpresionFactura();

?>