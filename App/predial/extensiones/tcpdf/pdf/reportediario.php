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

if(isset($_GET["fechaInicial"])){

    $fechaInicial = $_GET["fechaInicial"];
    $fechaFinal = $_GET["fechaFinal"];
    $ruta = $_GET["ruta"];

}else{

$fechaInicial = null;
$fechaFinal = null;

}
$respuesta = ControladorVentas::ctrRangoFechasVentasDiario($fechaInicial, $fechaFinal);

/*
$fecha = substr($respuestaVenta["fecha"],0,-8);
$productos = json_decode($respuestaVenta["productos"], true);
$neto = number_format($respuestaVenta["neto"],2);
$impuesto = number_format($respuestaVenta["impuesto"],2);
$total = number_format($respuestaVenta["total"],2);
$medio_pago = $respuestaVenta["metodo_pago"];
*/

//Obtenemos datos de Resolucion DIAN
//session_start();

if ($_SESSION["id"] == 67){
	$usuario_usu= $ruta;
}else{
	$usuario_usu= $_SESSION["id_ruta"];
}


//$usuario_usu= $_SESSION["id_ruta"];

if( $usuario_usu == 1 ){
	$respuestaVenta["ruta_nombre"]= 'Villanueva';
}else if( $usuario_usu == 2 ){
    $respuestaVenta["ruta_nombre"]= 'Santander';
}else if( $usuario_usu == 3 ){
    $respuestaVenta["ruta_nombre"]= 'Yopal';
}else if( $usuario_usu == 4 ){
    $respuestaVenta["ruta_nombre"]= 'Paz de Ariporo';
}else if( $usuario_usu == 5 ){
	$respuestaVenta["ruta_nombre"]= 'Norte';
}else if( $usuario_usu == 6 ){
    $respuestaVenta["ruta_nombre"]= 'Toca';
}else if( $usuario_usu == 7 ){
    $respuestaVenta["ruta_nombre"]= 'Duitama';
}else if( $usuario_usu == 8 ){
    $respuestaVenta["ruta_nombre"]= 'Paipa';
}else if( $usuario_usu == 9 ){
	$respuestaVenta["ruta_nombre"]= 'Sogamoso';
}else if( $usuario_usu == 10 ){
    $respuestaVenta["ruta_nombre"]= 'Tunja';
}else if( $usuario_usu == 11 ){
    $respuestaVenta["ruta_nombre"]= 'Chinquinquira';
}

/*
if( $valorVenta1 == 100 and $respuestaVenta["id_ruta"] == 0 ){
	$respuestaVenta["ruta_nombre"]= 'Boyacá';
}else if( $valorVenta1 == 200){
    $respuestaVenta["ruta_nombre"]= 'Yopal';
}else if( $valorVenta1 == 300){
    $respuestaVenta["ruta_nombre"]= 'Santander';
}else if( $valorVenta1 == 400){
    $respuestaVenta["ruta_nombre"]= 'Villanueva';
}
*/
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

<table border="0.1" style="font-size:5px; text-align:left">

	<tr>
		<td style="width:27px;">
			Fecha:  
		</td>
		<td style="width:33px;">
			$fechaInicial
		</td>
		<td style="width:10px;">
			al  
		</td>
		<td style="width:90px;">
			$fechaFinal 
		</td>
	</tr>
	<tr>
		<td style="width:27px;">
			Ruta: 
		</td>
		<td style="width:133;">
			$respuestaVenta[ruta_nombre]
		</td>
	</tr>

</table>

	
<table style="font-size:6px; text-align:center">

	<tr>
		<td style="width:160px;">
		</td>
	</tr>

</table>


<table style="font-size:6px; text-align:center">

	<tr>
		<td style="width:160px;">
			FACTURAS A EFECTIVO
		</td>
	</tr>

</table>

<table border="0.1" style="font-size:5px; text-align:center">
	<tr>
		<td style="width:40px;margin:auto;text-align:left">
			N° Factura
		</td>
		<td style="width:60px;margin:auto;text-align:left">
			Cliente
		</td>
		<td style="width:30px;margin:auto;text-align:left">
			Pago		
		</td>
		<td style="width:30px;margin:auto;text-align:left">
			Total		
		</td>
		
	</tr>

</table>


EOF;

$pdf->writeHTML($bloque1, false, false, false, false, '');

// ---------------------------------------------------------

$totalefectivo=0;
foreach ($respuesta as $key => $item) {


	if($item['metodo_pago'] == 'Efectivo'and $usuario_usu == $item['id_ruta'] ) {

		$totalefectivo= $totalefectivo+$item['neto'];
$bloque2 = <<<EOF

<table border="0.1" style="font-size:5px; text-align:center">
	<tr>
		<td style="width:40px; text-align:left">
			$item[numero_codigo]
		</td>
		<td style="width:60px; text-align:left">
			$item[nombre_cliente]
		</td>
		<td style="width:30px; text-align:left">
			Efectivo
		</td>
		<td style="width:30px; text-align:left">
			$item[neto]
		</td>
	</tr>
	
</table>

EOF;

$pdf->writeHTML($bloque2, false, false, false, false, '');
	}
}

$bloque3 = <<<EOF

<table border="0.1" style="font-size:6px; text-align:center">
	
	<tr>
		<td style="width:130px; text-align:right" >
			 TOTAL: 
		</td>

		<td style="width:30px;text-align:left">
		$totalefectivo
		</td>
	</tr>

</table>

<table style="font-size:8px; text-align:center">

	<tr>
		<td style="width:160px;">
		</td>
	</tr>

</table>

<table style="font-size:6px; text-align:center">

	<tr>
		<td style="width:160px;">
			FACTURAS EN CREDITO
		</td>
	</tr>

</table>

EOF;

	$pdf->writeHTML($bloque3, false, false, false, false, '');

// ---------------------------------------------------------

$totalefectivoo=0;
foreach ($respuesta as $key => $item) {


	if($item['metodo_pago'] != 'Efectivo' and $usuario_usu == $item['id_ruta']	) {

		$totalefectivoo= $totalefectivoo+$item['neto'];
$bloque4 = <<<EOF

<table border="0.1" style="font-size:6px; text-align:center">
	<tr>
		<td style="width:40px; text-align:left">
			$item[numero_codigo]
		</td>
		<td style="width:60px; text-align:left">
			$item[nombre_cliente]
		</td>
		<td style="width:30px; text-align:left">
			Credito
		</td>
		<td style="width:30px; text-align:left">
			$item[neto]
		</td>
	</tr>
	
</table>

EOF;

$pdf->writeHTML($bloque4, false, false, false, false, '');
	}
}

$total_full = $totalefectivoo + $totalefectivo;

$bloque5 = <<<EOF

<table border="0.1" style="font-size:6px; text-align:center">
	
	<tr>
		<td style="width:130px; text-align:right" >
			 TOTAL: 
		</td>

		<td style="width:30px;text-align:left">
		$totalefectivoo
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
		<td style="width:160px; text-align:center" >
			 TOTAL: $total_full
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
$factura -> codigo = $_GET["fechaInicial"];
$factura -> traerImpresionFactura();

?>