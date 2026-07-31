<?php

require_once "../../../controladores/ventas.controlador.php";
require_once "../../../modelos/ventas.modelo.php";

require_once "../../../controladores/clientes.controlador.php";
require_once "../../../modelos/clientes.modelo.php";

require_once "../../../controladores/usuarios.controlador.php";
require_once "../../../modelos/usuarios.modelo.php";

require_once "../../../controladores/productos.controlador.php";
require_once "../../../modelos/productos.modelo.php";

class imprimirFactura{

public $codigo;
public $prefijo;

public function traerImpresionFactura(){
	
//TRAEMOS LA INFORMACIÓN DE LA VENTA

$itemVenta1 = "id";
//$itemVenta2= "numero_codigo";
$valorVenta2 = $this->codigo;
//$valorVenta1 = $_GET["prefijo"];

/* Validamos si es factura DIAN o Remisión


if($valorVenta1 != 0){
	$factu_remi= "Factura";
}else{
	$factu_remi= "Remisión";
}

*/
//$valorVenta = 'M'.$valorVenta1.'-'.$valorVenta2;
$valorVenta = $valorVenta2;

$respuestaVenta = ControladorVentas::ctrMostrarCargueVehiculo($itemVenta1, $valorVenta2);

$fecha = substr($respuestaVenta["fecha_creacion"],0,-8);
$productos = json_decode($respuestaVenta["productos"], true);
$vehiculo = $respuestaVenta["vehiculo"];
$nombre_usuario = $respuestaVenta["usuario"];
$nombre_conductor = $respuestaVenta["conductor"];
$nombre_ruta = $respuestaVenta["ruta"];


//REQUERIMOS LA CLASE TCPDF
require_once('tcpdf_include.php');

$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);


$pdf->SetMargins(12, 2, 0);

$pdf->setPrintHeader(false);
$pdf->SetHeaderMargin(0);
$pdf->setPrintFooter(false);
$pdf->SetFooterMargin(0);

$pdf->SetAutoPageBreak(TRUE, 0);

$pdf->AddPage('P', 'Letter');

//---------------------------------------------------------

$bloque1 = <<<EOF

<table style="font-size:10px; text-align:center">
	<tr>
		<td style="width:50px;">
			<img src="images/logo.png">
		</td>
		<td style="width:500px; font-size:10px">
			Distribuidora El Mago <br>
			<em style="width:130px;font-size:8px">
				Jairo Alberto González Guevara
			</em>
			<em style="width:130px;font-size:8px">
				Telefono: 320 8994061
			</em> <br><br>
			<em style="width:130px;font-size:10px">
				PLANILLA CARGUE N° $valorVenta2
			</em>
		</td>
	</tr>
</table>

<table style="font-size:8px; text-align:center">

	<tr>
		<td style="width:160px;">
		</td>
	</tr>

</table>


<table border="0.1" style="font-size:10px; text-align:center">
	<tr>
		<td style="width:125px;">
			RUTA: 
		</td>

		<td style="width:125px;">
			$nombre_ruta
		</td>

		<td style="width:125px;">
			CONDUCTOR:
		</td>

		<td style="width:125px;">
			$nombre_conductor
		</td>

	</tr>

	<tr>
		<td style="width:125px;">
			FECHA:
		</td>

		<td style="width:125px;">
			$fecha
		</td>
		<td style="width:125px;">
			VEHICULO: 
		</td>

		<td style="width:125px;">
			$vehiculo
		</td>
	</tr>
	
</table>

<table style="font-size:8px; text-align:center">

	<tr>
		<td style="width:160px;">
		</td>
	</tr>

</table>

<table border="0.1" style="font-size:6px; text-align:center">
	<tr>
		<td style="width:70px;margin:auto;text-align:left">
			Descripción
		</td>
		<td style="width:50px;margin:auto;text-align:left">
			Cargue
		</td>
		<td style="width:50px;margin:auto;text-align:left">
			Dev		
		</td>
		<td style="width:70px;margin:auto;text-align:left">
			Verificación
		</td>

		<td style="width:20px;margin:auto;text-align:left">
			
		</td>

		<td style="width:70px;margin:auto;text-align:left">
			Descripción
		</td>
		<td style="width:50px;margin:auto;text-align:left">
			Cargue
		</td>
		<td style="width:50px;margin:auto;text-align:left">
			Dev		
		</td>
		<td style="width:70px;margin:auto;text-align:left">
			Verificación
		</td>

	</tr>

</table>

EOF;

$pdf->writeHTML($bloque1, false, false, false, false, '');

// ---------------------------------------------------------

// Impuestos agrupar
$totalproduc1=0;
$totalproduc2=0;
$totalproduc3=0;

foreach ($productos as $key => $item) {

$bloque2 = <<<EOF

<table border="0.1" style="font-size:4px; text-align:center">
	<tr>
		<td style="width:70px; text-align:left">
			$item[descripcion]
		</td>
		<td style="width:50px; text-align:left">
			$item[cantidad]
		</td>
		<td style="width:50px; text-align:left">
			
		</td>
		<td style="width:70px; text-align:left">
			
		</td>

		<td style="width:20px;margin:auto;text-align:left">
			
		</td>
		
		<td style="width:70px; text-align:left">
			$item[descripcion]
		</td>
		<td style="width:50px; text-align:left">
			$item[cantidad]
		</td>
		<td style="width:50px; text-align:left">
			
		</td>
		<td style="width:70px; text-align:left">
			
		</td>
	</tr>
</table>

EOF;

$pdf->writeHTML($bloque2, false, false, false, false, '');

}



$bloque6 = <<<EOF
	<table style="font-size:6px; text-align:center" >
		<tr>
			<td></td>
		</tr>
		<tr>
			<td></td>
		</tr>
		<tr>
			<td style="width:160px;">
				______________________________________
			</td>
		</tr>
		<tr>
			<td style="width:160px;">
				Firma
			</td>
		</tr>
	</table>

EOF;


	$pdf->writeHTML($bloque6, false, false, false, false, '');



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