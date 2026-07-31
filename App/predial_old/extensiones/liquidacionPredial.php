<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_FacturaDocumento.php';
include_once SERVER . '/business/DAO/DAO_FacturaDetalleDocumento.php';
include_once SERVER . '/business/DAO/DAO_Tesoreria.php';
include_once SERVER . '/business/class.sessions.php';

require_once "barcode/barcode.php";
require_once "vendor/autoload.php";
use PhpOffice\PhpWord\Style\Language;
use PhpOffice\PhpWord\Style\Paper;
use PhpOffice\PhpWord\SimpleType\Jc;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;

class imprimirFactura{

public $codigo;
public $nombre;
public $idPredio;
public $anio;

public function traerImpresionFactura(){
    
/**
 * Trabajar con documentos de Word y PHP usando PHPOffice
 *
 * Más tutoriales en: parzibyte.me/blog
 *
 * Ejemplo 2:
 * Agregar enlaces y texto con distintas fuentes y colores
 */

 $idPredioFull = $this->codigo;
 $nombre = $this->nombre;
 $idPredio = $this->idPredio;
 $anio = $this->anio;
 
	
// ------------ Conexión a BD -------------------------------------------------

// LOCAL
$serverName = "DESARROLLO\SQLEXPRESS2019";
$connectionInfo = array( "Database"=>"erpsofts_paipa_test", "UID"=>"sa", "PWD"=>"Server2019");

// PRODUCCIÓN  
//$serverName = "167.114.216.134\\MSSQLSERVER2019";
//$connectionInfo = array( "Database"=>"erpsofts_paipa_test", "UID"=>"erpsofts_pse", "PWD"=>"predial123.");

$conn = sqlsrv_connect( $serverName, $connectionInfo);

// ----------- Calculo de Intereses del Predio ---------------------------------
date_default_timezone_set('America/Bogota');
$datefinal = date("Y-m-d H:i:s");

	// Configurar el tiempo de espera de la conexión
//sqlsrv_query($conexion, "SET LOCK_TIMEOUT 60000"); // Tiempo en milisegundos (60 segundos)

// Aumentar el tiempo máximo de ejecución del script PHP
set_time_limit(120); // Tiempo en segundos (2 minutos)

$querye = "EXEC [dbo].[SP_CALCULO_PREDIAL] @USUARIO_ID = 1, @ANO = $anio, @ANO_FIN = $anio, @PREDIO_ID = $idPredio, @FECHA_PAGO ='$datefinal'";
$stmt = sqlsrv_query( $conn, $querye);
	
/*
if( $stmt === false) {
	echo 'entro error procedimiento';
    }else{
    echo 'entro ok  procedimiento';
}
*/

// ----------- Valida NUM FACTURA y la crea ---------------------------------
$queryye = "EXEC [API].[SP_UPDATE_NUM_FACTURA_PREDIOSPAGOS_MOROSOS] @Annio = $anio, @IdPredio = $idPredio";
$stmt = sqlsrv_query( $conn, $queryye);

// ----------- Obtenemos los Datos de la Factura ---------------------------------
$query = "SELECT DISTINCT TOP(1) 
		 CONVERT(varchar(128),CONVERT(varchar(20),PredioPago.fecha_emision,103)+' '+ RIGHT( CONVERT(DATETIME, PredioPago.fecha_emision , 108),8)) AS FechaEmision
		,PredioPago.factura_pago		AS NoFactura		
		,predios.codigo_predio			AS CodigoCatastral
		,predios.codigo_predio_anterior			AS CodigoCatastrakAnt
		,[propietarios].identificacion	AS NitCC
		,(SELECT factura_pago FROM predios_pagos WHERE predios_pagos.id_predio = $idPredio AND ULTIMO_ANIO = predios.ultimo_anio_pago)		AS NoReciboAnt
		,CONVERT(varchar(128),CAST(predios.Area_hectareas AS MONEY), 1)		AS AreaHa
		,CONVERT(varchar(128),CAST(predios.area_metros AS MONEY), 1)		AS AreaM2
		,CONVERT(varchar(128),CAST(predios.area_construida AS MONEY), 1)	AS AreaCont
		,LTRIM(RTRIM([propietarios].nombre))			AS Propietario
		,LTRIM(RTRIM([predios].direccion))			AS Direccion
		,PredioPago.ultimo_anio			AS AnnioPagar
		,CONVERT(varchar(128),CAST(predios.avaluo	AS MONEY), 1) AS Avaluo
		,predios.ultimo_anio_pago		AS UltimoAnnioPago
		,CONVERT(varchar,(SELECT fecha_pago FROM predios_pagos WHERE predios_pagos.id_predio = $idPredio AND ULTIMO_ANIO = predios.ultimo_anio_pago),103)	AS FechaPago
		,CONVERT(varchar(128),CAST((SELECT valor_pago FROM predios_pagos WHERE predios_pagos.id_predio = $idPredio AND ULTIMO_ANIO = predios.ultimo_anio_pago)	AS MONEY), 1)	AS ValorPagado
		,PredioPago.ultimo_anio	AS Annio
		,CONVERT(varchar(128),Predios.tarifa_actual ,1 ) AS PorcMTAR
		,CONVERT(varchar(128),CAST(PredioPago.avaluo AS MONEY), 1)	AS AvaluoPago
		,CONVERT(varchar(128),(CAST(PredioPago.valor_concepto1 AS MONEY) +  CAST(PredioPago.valor_concepto3 AS MONEY)) , 1) AS Impuesto
		,CONVERT(varchar(128),(CAST(PredioPago.valor_concepto2 AS MONEY)+ CAST(PredioPago.valor_concepto4 AS MONEY)) , 1) AS Intereses
		,CONVERT(varchar(128),CAST(PredioPago.valor_concepto13 AS MONEY), 1)	AS Dscto
		,CONVERT(varchar(128),CAST(PredioPago.valor_concepto14 AS MONEY), 1)	AS Catorce
		,CONVERT(varchar(128),CAST(PredioPago.valor_concepto15 AS MONEY), 1)	AS Dscto1
		,''	AS Blan
		,''	AS Otros 
		,CONVERT(varchar(128),CAST(PredioPago.total_calculo	AS MONEY), 1)		AS Total
		,CONVERT(varchar(20),PredioPago.primer_fecha,23) AS PrimerFecha
		,CONVERT(varchar(20),PredioPago.primer_fecha,112) AS PrimerFechaCodigo
		,PredioPago.total_calculo AS TotalEspecial
		,PredioPago.porcentaje_uno AS PorcentajeUno
		,[predios_propietarios].jerarquia 
	FROM predios_pagos PredioPago
	INNER JOIN [dbo].[predios] on predios.id = PredioPago.id_predio
	INNER JOIN [dbo].[predios_propietarios] ON [predios_propietarios].id_predio = predios.id
	INNER JOIN [dbo].[propietarios] ON [propietarios].id =[predios_propietarios].id_propietario 
	WHERE PredioPago.id_predio = $idPredio AND ULTIMO_ANIO = $anio
	ORDER BY [predios_propietarios].jerarquia ASC";
	
$stmt = sqlsrv_query( $conn, $query );

if( $stmt === false) {
	die( print_r(sqlsrv_errors(), true));
		$row = NULL;
    }else{
    while( $res = sqlsrv_fetch_array( $stmt, SQLSRV_FETCH_ASSOC) ) {
        $row[] = $res;
    }
	//echo 'entro okss'.$row[0]['FechaEmision'];
}

$FechaEmision = $row[0]['FechaEmision'];
$NoFactura = $row[0]['NoFactura'];
$CodigoCatastral = $row[0]['CodigoCatastral'];
$CodigoCatastrakAnt = $row[0]['CodigoCatastrakAnt'];
$NitCC = $row[0]['NitCC'];
$NoReciboAnt = $row[0]['NoReciboAnt'];
$AreaHa = $row[0]['AreaHa'];
$AreaM2 = $row[0]['AreaM2'];
$AreaCont = $row[0]['AreaCont'];
$Propietario = $row[0]['Propietario'];
$Direccion = $row[0]['Direccion'];
$AnnioPagar = $row[0]['AnnioPagar'];
$Avaluo = $row[0]['Avaluo'];
$UltimoAnnioPago = $row[0]['UltimoAnnioPago'];
$FechaPago = $row[0]['FechaPago'];
$ValorPagado = $row[0]['ValorPagado'];
$Annio = $row[0]['Annio'];
$PorcMTAR = $row[0]['PorcMTAR'];
$AvaluoPago = $row[0]['AvaluoPago'];
$Impuesto = $row[0]['Impuesto'];
$Intereses = $row[0]['Intereses'];
$Dscto = $row[0]['Dscto'];
$Catorce = $row[0]['Catorce'];
$Dscto1 = $row[0]['Dscto1'];
$Blan = $row[0]['Blan'];
$Otros = $row[0]['Otros'];
$Total = $row[0]['Total'];
$PrimerFecha = $row[0]['PrimerFecha'];
$PorcentajeUno = $row[0]['PorcentajeUno'];
	
$PrimerFechaCodigo =  $row[0]['PrimerFechaCodigo'];
$TotalEspecial =  $row[0]['TotalEspecial'];
	
// Proceso para armar el codigo de barras 
$cantiValor = strlen($TotalEspecial);
$cerosNew = 14 - $cantiValor;
$numReal='';

for ($i = 1; $i <= $cerosNew; $i++) {
    $numReal = $numReal.'0';
}

$numReal = $numReal.$TotalEspecial;
$codigoFull = '(415)7709998776913(8020)000000000000000'.$NoFactura.'(3900)'.$numReal.'(96)'.$PrimerFechaCodigo;

barcode( 'imaBarras/'.$NoFactura.'.png', $codigoFull ,'100','horizontal','code128',true,1);

/*	
$SegundaFecha = $row[0]['SegundaFecha'];
$PorcentajeDos = $row[0]['PorcentajeDos'];
$TotalDos = $row[0]['TotalDos'];
$TerceraFecha = $row[0]['TerceraFecha'];
$PorcentajeTres = $row[0]['PorcentajeTres'];
$TotalTres = $row[0]['TotalTres'];


$FechaEmision = '20/03/2024   3:42PM';
$NoFactura = '202410286';
$CodigoCatastral = '202410286';
$CodigoCatastrakAnt = '0003000000080434000000000';
$NitCC = '23854899';
$NoReciboAnt = '202410286';
$AreaHa = '0.00';
$AreaM2 = '1,040.00';
$AreaCont = '0.00';
$Propietario = 'MARIA GLORIA RUIZ ALFONSO';
$Direccion = 'SAN CAYETANO VDA ROMITA';
$AnnioPagar = '2020';
$Avaluo = '15,451,000.00';
$UltimoAnnioPago = '2024';
$FechaPago = '21/03/2024';
$ValorPagado = '39,400.00';
$Annio = '2020';
$PorcMTAR = '3.00';
$AvaluoPago = '15,451,000.00';
$Impuesto = '46,400.00';
$Intereses = '0.00';
$Dscto = '-5,900.00';
$Catorce = '0.00';
$Dscto1 = '-1,100.00';
$Blan = '';
$Otros = '';
$Total = '39,400.00';
$PrimerFecha = '2024-04-30';
$PorcentajeUno = '15';
$SegundaFecha = '2024-05-31';
$PorcentajeDos = '10';
$TotalDos = '41,800.00';
$TerceraFecha = '2024-06-30';
$PorcentajeTres = '5';
$TotalTres = '44,000.00';
*/
	
$diaDoc = date("d");
$mesDoc = date("m");
$anioDoc = date("Y");

$documento = new \PhpOffice\PhpWord\PhpWord();
$propiedades = $documento->getDocInfo();
$propiedades->setCreator("ERPSOFTSAS");
$propiedades->setTitle("Texto");

$seccion = $documento->addSection();

/*
$paper = new Paper();
$paper->setSize('Legal');
*/
$seccion->getStyle()
    ->setPaperSize('Letter')
;

# Agregar texto...
/*
Todos los textos deben estar dentro de una sección
 */

# Simple texto
# $seccion->addText("CITACIÓN A NOTIFICACIÓN PERSONAL");


$estiloTablaDos = [
    "borderColor" => "000000",
    "borderSize" => 6,
    "cellMargin" => 10,
    'align' => 'center',
];

$estiloTablaDosEspecial = [
    "borderColor" => "FFFFFF",
    "borderSize" => 6,
    "cellMargin" => 10,
    'align' => 'center',
];


$fuente = [
    "name" => "Century Gothic",
    "size" => 8,
];

$fuenteDos = [
    "name" => "Century Gothic",
    "size" => 8,
    "bold" => true,
    'align' => 'center'
];

$fuenteDosp = [
    "name" => "Century Gothic",
    "size" => 7,
    "bold" => true,
    'align' => 'center'
];

$estiloTablaTres = [
    "borderColor" => "000000",
    'align' => 'center'
];


// TABLA DE DATOS PREDIO ---------------------
$documento->addTableStyle("estilo3", $estiloTablaDosEspecial);
$tabla = $seccion->addTable("estilo3");

$row = $tabla->addRow();
$row->addCell(4000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('', array('align'=>'center','width' => 1500, 'height' => 50,'spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(6000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('IMPUESTO PREDIAL UNIFICADO Y COMPLEMENTARIOS  LIQUIDACIÓN OFICIAL No.'.$NoFactura,$fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row = $tabla->addRow();
$row->addCell(4000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addImage('images/logo.png', array('align'=>'center','width' => 200, 'height' => 50,'spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(6000)->addText('La Dirección de Impuestos, Rentas y Jurisdicción Coactivo del Municipio de Paipa según el Acuerdo 019 de 2022, Decreto 009 de 2017, Decreto 011 de 2017, Resolución 090 de 2017 y Resolución 004 de 2024, determina oficialmente los siguientes periodos gravables del Impuesto Predial Unificado.', $fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));


$seccion->addText('',$fuenteDos,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

$documento->addTableStyle("estilo4", $estiloTablaDos);
$tabla = $seccion->addTable("estilo4");

$row = $tabla->addRow();
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('CÓDIGO CATASTRAL', $fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(3000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($CodigoCatastral,$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('CÓDIGO ANTERIOR:', $fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(3000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($CodigoCatastrakAnt,$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row = $tabla->addRow();
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('PROPIETARIO:', $fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(3000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($Propietario,$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('C.C. O NIT:', $fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(3000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($NitCC,$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row = $tabla->addRow();
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('DIRECCIÓN DE COBRO:', $fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(3000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($Direccion,$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('DIRECCIÓN:', $fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(3000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($Direccion,$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

$seccion->addText('',$fuenteDos,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));


$documento->addTableStyle("estilo5", $estiloTablaDos);
$tabla = $seccion->addTable("estilo5");

$row = $tabla->addRow();
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('No RECIBO ANT:', $fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('AÑOS A PAGAR',$fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('PAGUE ANTES DE', $fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('ÁREA HA:',$fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($AreaHa,$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('ÁREA M2:',$fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($AreaM2,$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('CONSTRUIDA',$fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($AreaCont,$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row = $tabla->addRow();
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($NoReciboAnt, $fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($AnnioPagar,$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($PrimerFecha, $fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('ÚLT AÑO PAG: ',$fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($UltimoAnnioPago,$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('FECHA PAG:',$fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($FechaPago,$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('VALOR PAGADO:',$fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($ValorPagado,$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

$seccion->addText('',$fuenteDos,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

// TABLA DE DATOS PREDIO ---------------------
$documento->addTableStyle("estilo7", $estiloTablaDos);
$tabla = $seccion->addTable("estilo7");

$row = $tabla->addRow();
$row->addCell(10000, array('gridSpan' => 9, 'vMerge' => 'restart'))->addText('DETALLE DE PAGO',$fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row = $tabla->addRow();
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('AÑO', $fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('%Mil TAR',$fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('AVALÚO', $fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('IMPUESTO',$fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('INTERÉS',$fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('DESCUENTO IMP',$fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('ALUMBRADO',$fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('OTROS',$fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('TOTAL',$fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

$row = $tabla->addRow();
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($Annio, $fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($PorcMTAR,$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($AvaluoPago,$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($Impuesto,$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($Intereses,$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($Dscto,$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($Catorce,$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($Otros,$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($Total,$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

$row = $tabla->addRow();
$row->addCell(2000, array('gridSpan' => 3, 'vMerge' => 'restart'))->addText('TOTALES', $fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($Impuesto,$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($Intereses,$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($Dscto,$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($Catorce,$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($Otros,$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($Total,$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));


$fuenteTituloCinco = [
    "name" => "Century Gothic",
    "size" => 6,
];

$seccion->addText("Los intereses deberán ser cancelados de acuerdo al valor causado por cada día calendario de retardo hasta la fecha efectiva de pago.", $fuenteTituloCinco,  array('align'=>'left','spaceBefore' => 0, 'spaceAfter' => 0));
$seccion->addText('',$fuenteDos,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

// ------------- CODIGO DE BARRAS  ------------------------
$seccion->addText('________________________________________________________________________________________________________',$fuenteDos,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
//$seccion->addText('',$fuenteDos,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

$documento->addTableStyle("estilo10", $estiloTablaDosEspecial);
$tabla = $seccion->addTable("estilo10");

$row = $tabla->addRow();
$row->addCell(2500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('', array('align'=>'center','width' => 1500, 'height' => 50,'spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('', array('align'=>'center','width' => 1500, 'height' => 50,'spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Formulario No '.$NoFactura,$fuenteDosp, array('align'=>'left','width' => 1500, 'height' => 50,'spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Referencia No '.$NoFactura,$fuenteDosp, array('align'=>'left','width' => 1500, 'height' => 50,'spaceBefore' => 0, 'spaceAfter' => 0));
$row = $tabla->addRow();
$row->addCell(2500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('', array('align'=>'left','width' => 200, 'height' => 50,'spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('-USUARIO-',$fuenteDosp, array('align'=>'left','width' => 200, 'height' => 50,'spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('', array('align'=>'center','width' => 1500, 'height' => 50,'spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($FechaEmision,$fuenteDosp, array('align'=>'right','width' => 200, 'height' => 50,'spaceBefore' => 0, 'spaceAfter' => 0));
$row = $tabla->addRow();
$row->addCell(2500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('', array('align'=>'center','width' => 1500, 'height' => 50,'spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(5000, array('gridSpan' => 2, 'vMerge' => 'restart'))->addText('Pague hasta '.$PrimerFecha,$fuenteDosp, array('align'=>'right','width' => 1500, 'height' => 50,'spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($Total ,$fuenteDosp, array('align'=>'right','width' => 1500, 'height' => 50,'spaceBefore' => 0, 'spaceAfter' => 0));

//$seccion->addText('',$fuenteDos,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

$documento->addTableStyle("estilo3", $estiloTablaDosEspecial);
$tabla = $seccion->addTable("estilo3");

$row = $tabla->addRow();
$row->addCell(5000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addImage('images/logo.png', array('align'=>'center','width' => 200, 'height' => 50,'spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(5000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addImage('imaBarras/'.$NoFactura.'.png', array('align'=>'right','width' => 250, 'height' => 50,'spaceBefore' => 0, 'spaceAfter' => 0));


// ------------- CODIGO DE BARRAS  ------------------------
$seccion->addText('________________________________________________________________________________________________________',$fuenteDos,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
//$seccion->addText('',$fuenteDos,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

$documento->addTableStyle("estilo10", $estiloTablaDosEspecial);
$tabla = $seccion->addTable("estilo10");

$row = $tabla->addRow();
$row->addCell(2500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('', array('align'=>'center','width' => 1500, 'height' => 50,'spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('', array('align'=>'center','width' => 1500, 'height' => 50,'spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Formulario No '.$NoFactura,$fuenteDosp, array('align'=>'left','width' => 1500, 'height' => 50,'spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Referencia No '.$NoFactura,$fuenteDosp, array('align'=>'left','width' => 1500, 'height' => 50,'spaceBefore' => 0, 'spaceAfter' => 0));
$row = $tabla->addRow();
$row->addCell(2500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('', array('align'=>'left','width' => 200, 'height' => 50,'spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('-BANCO-',$fuenteDosp, array('align'=>'left','width' => 200, 'height' => 50,'spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('', array('align'=>'center','width' => 1500, 'height' => 50,'spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($FechaEmision,$fuenteDosp, array('align'=>'right','width' => 200, 'height' => 50,'spaceBefore' => 0, 'spaceAfter' => 0));
$row = $tabla->addRow();
$row->addCell(2500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('', array('align'=>'center','width' => 1500, 'height' => 50,'spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(5000, array('gridSpan' => 2, 'vMerge' => 'restart'))->addText('Pague hasta '.$PrimerFecha,$fuenteDosp, array('align'=>'right','width' => 1500, 'height' => 50,'spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($Total ,$fuenteDosp, array('align'=>'right','width' => 1500, 'height' => 50,'spaceBefore' => 0, 'spaceAfter' => 0));

//$seccion->addText('',$fuenteDos,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

$documento->addTableStyle("estilo3", $estiloTablaDosEspecial);
$tabla = $seccion->addTable("estilo3");

$row = $tabla->addRow();
$row->addCell(5000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addImage('images/logo.png', array('align'=>'center','width' => 200, 'height' => 50,'spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(5000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addImage('imaBarras/'.$NoFactura.'.png', array('align'=>'right','width' => 250, 'height' => 50,'spaceBefore' => 0, 'spaceAfter' => 0));

$seccion->addText('',$fuenteDos,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$seccion->addText('',$fuenteDos,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$seccion->addText('',$fuenteDos,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$seccion->addText('',$fuenteDos,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$seccion->addText('',$fuenteDos,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$seccion->addText('',$fuenteDos,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$seccion->addText('',$fuenteDos,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$seccion->addText('',$fuenteDos,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$seccion->addText('',$fuenteDos,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$seccion->addText('',$fuenteDos,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
//$seccion->addText('',$fuenteDos,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$seccion->addText('',$fuenteDos,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

$documento->addTableStyle("estilo6", $estiloTablaTres);
$tabla = $seccion->addTable("estilo6");

$row = $tabla->addRow();
$row->addCell(10000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('LIQUIDACION-FACTURA',$fuenteDos,array('align'=>'center'));
$row = $tabla->addRow();
$row->addCell(10000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('IMPUESTO PREDIAL UNIFICADO',$fuenteDos,array('align'=>'center'));

$primero = $seccion->addTextRun([
    "alignment" => Jc::BOTH,
    "lineHeight" => 1, # Quedará muy pegado
]);
$primero->addText("1. COMPETENCIA:", $fuenteDos, array('align'=>'both'));
$seccion->addText("La Dirección de impuestos, Rentas y Jurisdicción Coactiva de la Secretaria de Hacienda del Municipio de Paipa, en uso de sus atribuciones conferidas por las Resoluciones 045 y 090 de 2017, Decreto 009 de 2017 y  la Resolución 004 de 2024, es competente para realizar la liquidación factura del impuesto predial unificado.",$fuente, array('align'=>'both'));

$segundo = $seccion->addTextRun([
    "alignment" => Jc::BOTH,
    "lineHeight" => 1, # Quedará muy pegado
]);
$segundo->addText("2. FUNDAMENTO DE LA LIQUIDIACIÓN:",$fuenteDos , array('align'=>'both'));

$segundoo = $seccion->addTextRun([
    "alignment" => Jc::BOTH,
    "lineHeight" => 1, # Quedará muy pegado
]);
$segundoo->addText("A. Consultada la base de datos de contribuyentes del impuesto predial unificado, se encuentra que el predio identificado con código catastral No. ",$fuente, array('align'=>'both'));
$segundoo->addText($idPredioFull, $fuenteDos, array('align'=>'both'));
$segundoo->addText(" cuya propiedad figura a nombre de ",$fuente, array('align'=>'both'));
$segundoo->addText($nombre, $fuenteDos, array('align'=>'both'));
$segundoo->addText(", a la fecha presenta obligaciones pendientes de pago por dicho concepto correspondiente a la vigencia ",$fuente, array('align'=>'both'));
$segundoo->addText($anio,$fuenteDos, array('align'=>'both'));
$segundoo->addText(".",$fuente, array('align'=>'both'));

$segundooi = $seccion->addTextRun([
    "alignment" => Jc::BOTH,
    "lineHeight" => 1, # Quedará muy pegado
]);
$segundooi->addText("B. Que el artículo 11 del Decreto 009 del 13 de enero de 2017 concordante con el artículo 354 de la Ley 1819 de 2016, establece que para efectos de la facturación del Impuesto Predial Unificado, así como para la notificación de los actos devueltos por correo por causal diferente a dirección errada, la notificación se realizará mediante publicación mediante inserción en la página Web de la Alcaldía Municipal de Paipa, de tal suerte que el envió que del acto se haga a la dirección del predio surte efecto de divulgación adicional sin que la omisión de esta formalidad invalide la notificación efectuada.",$fuente, array('align'=>'both'));

$tercero = $seccion->addTextRun([
    "alignment" => Jc::BOTH,
    "lineHeight" => 1, # Quedará muy pegado
]);
$tercero->addText("3. ASPECTOS LEGALES Y PROCEDIMENTALES:",$fuenteDos, array('align'=>'both'));

$terceroo = $seccion->addTextRun([
    "alignment" => Jc::BOTH,
    "lineHeight" => 1, # Quedará muy pegado
]);
$terceroo->addText("3.1. SUJETO PASIVOS DEL IMPUESTO PREDIAL UNIFICADO: ",$fuenteDos, array('align'=>'both'));
$terceroo->addText("Los sujetos pasivos del impuesto predial unificado se encuentran establecidos en el artículo 24 del Acuerdo Municipal N° 019 de 2022.",$fuente, array('align'=>'both'));
$cuarto = $seccion->addTextRun([
    "alignment" => Jc::BOTH,
    "lineHeight" => 1, # Quedará muy pegado
]);
$cuarto->addText("3.2. INTERESES MORATORIOS: ", $fuenteDos, array('align'=>'both'));
$cuarto->addText("Artículos 72 y 79 del Decreto Municipal N° 009 de 2017, establecen los intereses moratorios y la determinación de la tasa de interés moratorio de conformidad con los artículos 634 y 635 del Estatuto Tributario Nacional, los cuales se causan a partir del vencimiento del plazo establecido para realizar su pago. Los intereses moratorios aquí determinados se actualizarán hasta el momento del pago efectivo.",$fuente, array('align'=>'both'));
$quinto = $seccion->addTextRun([
    "alignment" => Jc::BOTH,
    "lineHeight" => 1, # Quedará muy pegado
]);
$quinto->addText("3.3. PROCEDENCIA DE LA NOTIFICACIÓN A TRAVÉS DE LA INSERCIÓN EN PAGINA WEB: ", $fuenteDos, array('align'=>'both'));
$quinto->addText("La notificación de la liquidación factura a través de la inserción en página web es procedente de conformidad con los artículos 11 del Decreto 009 del 13 de enero  de 2017 y 354 de la Ley 1819 de 2016.",$fuente, array('align'=>'both'));
$sexto = $seccion->addTextRun([
    "alignment" => Jc::BOTH,
    "lineHeight" => 1, # Quedará muy pegado
]);
$sexto->addText("3.4. RECURSOS QUE PROCEDEN: ", $fuenteDos, array('align'=>'both'));
$sexto->addText("Contra la presente liquidación factura procede el recurso de reconsideración, el cual podrá interponerse dentro de los dos (2) meses siguientes a su publicación, de acuerdo a lo establecido por los artículos 720 y 722 del Estatuto Tributario Nacional.",$fuente, array('align'=>'both'));
$septimo = $seccion->addTextRun([
    "alignment" => Jc::BOTH,
    "lineHeight" => 1, # Quedará muy pegado
]);
$septimo->addText("3.5. MERITO EJECUTIVO: ", $fuenteDos, array('align'=>'both'));
$septimo->addText("El no pago oportuno de las obligaciones aquí establecidas o el no ejercicio de las acciones otorgadas en la ley, conlleva a la firmeza de la liquidación factura, con lo cual puede ser cobrada por vía coactiva.",$fuente, array('align'=>'both'));
$octavo = $seccion->addTextRun([
    "alignment" => Jc::BOTH,
    "lineHeight" => 1, # Quedará muy pegado
]);
$octavo->addText("3.6 ANEXOS: ", $fuenteDos, array('align'=>'both'));
$octavo->addText("Los anexos hacen parte integral del acto administrativo.",$fuente, array('align'=>'both'));

// TABLA DE EMITE EMPLEO ---------------------

$documento->addTableStyle("estilo8", $estiloTablaDosEspecial);
$tabla = $seccion->addTable("estilo8");

$imgd = $seccion->addTextRun('estilo20');
$imgd->addImage('images/firma.png', array('align'=>'center','width' => 200, 'height' => 50,'spaceBefore' => 0, 'spaceAfter' => 0));

/*
$documento->addTableStyle("estilo7", $estiloTablaDos);
$tabla = $seccion->addTable("estilo7");

$row = $tabla->addRow();
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Emite:',$fuente, array('align'=>'right','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(6000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('JUAN GABRIEL SUÁREZ AVENDAÑO.', $fuenteDos, array('align'=>'left','spaceBefore' => 0, 'spaceAfter' => 0));

$row = $tabla->addRow();
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Empleo',$fuente, array('align'=>'right','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(6000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Director de Impuestos, Rentas y Jurisdicción Coactiva.', $fuente, array('align'=>'left','spaceBefore' => 0, 'spaceAfter' => 0));
*/


# Para que no diga que se abre en modo de compatibilidad
$documento->getCompatibility()->setOoxmlVersion(15);
# Idioma español de México
$documento->getSettings()->setThemeFontLang(new Language("ES-MX"));

# Guardarlo
$objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($documento, "Word2007");

$micarpeta = '../PROCESO_FISCALIZACION/'.$idPredioFull.'/FACTURA';
if (!file_exists($micarpeta)) {
mkdir("../PROCESO_FISCALIZACION/".$idPredioFull, 0700);
mkdir("../PROCESO_FISCALIZACION/".$idPredioFull."/FACTURA", 0700);
}
$objWriter->save("../PROCESO_FISCALIZACION/".$idPredioFull."/FACTURA"."/".$NitCC."_".$NoFactura.".docx");

// Convierte a PDF
Settings::setPdfRendererName(Settings::PDF_RENDERER_TCPDF);
Settings::setPdfRendererPath('./tcpdf');

$phpWord = IOFactory::load("../PROCESO_FISCALIZACION/".$idPredioFull."/FACTURA"."/".$NitCC."_".$NoFactura.".docx", 'Word2007');
$phpWord->save("../PROCESO_FISCALIZACION/".$idPredioFull."/FACTURA"."/".$NitCC."_".$NoFactura.".pdf", 'PDF');

// Descarga del PDF en el quipo local.
// $archivofull = ''.$NitCC.'_'.$NoFactura.'.pdf';
// $file = "../PROCESO_FISCALIZACION/".$idPredioFull."/".$NitCC."_".$NoFactura.".pdf";
//header ("Content-Disposition: attachment; filename=".$archivofull.";" );
//header ("Content-Type: application/force-download");
//readfile($file);
	
//echo "<script languaje='javascript' type='text/javascript'>window.close();</script>";
}
}
$factura = new imprimirFactura();
$factura -> codigo = $_GET["codigo"];
$factura -> nombre = $_GET["nombre"];
$factura -> idPredio = $_GET["idPredio"];
$factura -> anio = $_GET["anio"];
$factura -> traerImpresionFactura();

?>