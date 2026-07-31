<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_FacturaDocumento.php';
include_once SERVER . '/business/DAO/DAO_FacturaDetalleDocumento.php';
include_once SERVER . '/business/DAO/DAO_Tesoreria.php';
include_once SERVER . '/business/class.sessions.php';

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
 
// LOCAL
$serverName = "DESARROLLO\SQLEXPRESS2019";
$connectionInfo = array( "Database"=>"erpsofts_paipa_test", "UID"=>"sa", "PWD"=>"Server2019");

// PRODUCCIÓN  
//$serverName = "167.114.216.134\\MSSQLSERVER2019";
//$connectionInfo = array( "Database"=>"erpsofts_paipa_test", "UID"=>"erpsofts_pse", "PWD"=>"predial123.");

$conn = sqlsrv_connect( $serverName, $connectionInfo);

 $query = "SELECT pp.factura_pago as factura, 
            (SELECT TOP(1)pr.nombre FROM predios_propietarios as ppp inner join 
                propietarios as pr on ppp.id_propietario=pr.id
                where ppp.id_predio = pp.id_predio) as nombre ,
			(SELECT TOP(1)pr.identificacion FROM predios_propietarios as ppp inner join 
                propietarios as pr on ppp.id_propietario=pr.id
                where ppp.id_predio = pp.id_predio) as identificacion,
                CAST(pp.fecha_asignacion_publicacion_moroso AS DATE) AS fechaPublicacion
			from predios_pagos as pp 
                where pp.id_predio =  $idPredio and pp.ultimo_anio = $anio";

$stmt = sqlsrv_query( $conn, $query );

    if( $stmt === false) { $row = NULL;
    }else{
        while( $res = sqlsrv_fetch_array( $stmt, SQLSRV_FETCH_ASSOC) ) {
                $row[] = $res;
         }
    }

	$nombrePredio = $row[0]['nombre'];
    $identificacionPredio = $row[0]['identificacion'];
    $facturaPredio = $row[0]['factura'];

    $fechaPublicacion = $row[0]['fechaPublicacion'];
    $fechaPublicacion = $fechaPublicacion->format('Y-m-d'); // 
    

      /******************************************************* */
    // Director y Resolucion Obtener

    $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
    $query = "SELECT * from pre_configuracion con where con_Estado = 1 ORDER BY con_Id DESC limit 1";

	$data = $con->consultar($query);
	if($data != NULL && $data != ""){
		if( $con->getNumeroFilasConsultadas($data) >0 ){
			while($res = $con->obnerFila($data)){
				$resoim[] = $res;
			}
		}else{ $resoim = NULL;}
	}else{ $resoim = NULL;}

    $nombreDirector = $resoim[0]['con_NombreDirector'];
    $resolucionDirector = $resoim[0]['con_Resolucion'];


$diaDoc = date("d");
$mesDoc = date("m");
$anioDoc = date("Y");

$documento = new \PhpOffice\PhpWord\PhpWord();
$propiedades = $documento->getDocInfo();
$propiedades->setCreator("ERPSOFTSAS");
$propiedades->setTitle("Texto");

$seccion = $documento->addSection();

$subsequent = $seccion->addHeader();
$subsequent->addImage('images/header_publicacion.png', array('align'=>'center', 'width' => 400, 'height' => 100));

//$footer = $seccion->addFooter();
//$footer->addImage('images/footer.png', array('align'=>'center', 'width' => 400, 'height' => 50));

/*
$paper = new Paper();
$paper->setSize('Legal');
*/
$seccion->getStyle()
    ->setPaperSize('Legal')
;

# Agregar texto...
/*
Todos los textos deben estar dentro de una sección
 */

# Simple texto
# $seccion->addText("CITACIÓN A NOTIFICACIÓN PERSONAL");

# Otra tabla
$estiloTabla = [
    "borderColor" => "000000",
    "borderSize" => 10,
    "cellMargin" => 10,
    'align' => 'center',
    'spaceAfter' => 0,
];

// Guardarlo para usarlo más tarde
$documento->addTableStyle("estilo2", $estiloTabla);
$tabla = $seccion->addTable("estilo2");

$fuente = [
    "name" => "Century Gothic",
    "size" => 8,
    "bold" => true,
];

// TABLA DE FECHA ---------------------

# Otra tabla
$estiloTablaDos = [
    "borderColor" => "000000",
    "borderSize" => 10,
    "cellMargin" => 10,
];


// ---------- TABLA DE DATOS PREDIO ---------------------
$fuenteDosTituloTres = [
    "name" => "Century Gothic",
    "size" => 8,
];

$fuenteTituloTres = [
    "name" => "Century Gothic",
    "size" => 8,
    "bold" => true,
];

// TABLA DE ASUNTO ---------------------
$styleTable = array('borderSize' => 6, 'borderColor' => '999999');
$documento->addParagraphStyle('estilo4', array('align'=>'center', 'spaceAfter'=>100));
$documento->addTableStyle("estilo4", $styleTable);
$tabla = $seccion->addTable("estilo4");

$row = $tabla->addRow();
$row->addCell(10000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('CONSTANCIA DE EJECUTORIA', $fuenteTituloTres, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
 
$seccion->addText('',$fuenteDosTituloTres,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

# Con fuentes personalizadas
$fuenteTex = [
    "name" => "Century Gothic",
    "size" => 8,
];
$seccion->addText("La Dirección de Impuestos, Rentas y Jurisdicción Coactiva de la Secretaria de Hacienda del Municipio de Paipa, hace constar, que la factura de liquidación ".$facturaPredio." por medio de la cual se expidió Liquidación Oficial de Impuesto Predial Unificado, fue notificada mediante publicación el día ".$fechaPublicacion.", a través de la página web del municipio de Paipa, al contribuyente ".$nombrePredio." identificado con ".$identificacionPredio." y contra la cual no se interpuso ningún recurso, quedando debidamente ejecutoriada el día ".$diaDoc."/".$mesDoc."/".$anioDoc." de conformidad a lo establecido con el Estatuto de Rentas Municipal.",$fuenteTex,  array('align'=>'both'));

$seccion->addText("La presente se firma en la ciudad de Paipa.",$fuenteTex, array('align'=>'both'));

$seccion->addText('',$fuenteTex,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

// TABLA DE EMITE EMPLEO ---------------------

$estiloTablaDos = [
    "borderColor" => "000000",
    //"borderSize" => 6,
    "cellMargin" => 10,
    'align' => 'center',
];
$documento->addTableStyle("estilo7", $estiloTablaDos);
$tabla = $seccion->addTable("estilo7");

$row = $tabla->addRow();
$row->addCell(8000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($nombreDirector, $fuenteTituloTres, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

$row = $tabla->addRow();
$row->addCell(8000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Director de Impuestos, Rentas y Jurisdicción Coactiva.', $fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

$seccion->addText('',$fuenteTex,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));


# Para que no diga que se abre en modo de compatibilidad
$documento->getCompatibility()->setOoxmlVersion(15);
# Idioma español de México
$documento->getSettings()->setThemeFontLang(new Language("ES-MX"));

# Guardarlo
$objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($documento, "Word2007");

$micarpeta = '../PROCESO_FISCALIZACION/'.$idPredioFull.'/CONSTANCIA';
if (!file_exists($micarpeta)) {
    //mkdir("../PROCESO_FISCALIZACION/".$idPredioFull, 0700);
    mkdir("../PROCESO_FISCALIZACION/".$idPredioFull."/CONSTANCIA", 0700);
}
$objWriter->save("../PROCESO_FISCALIZACION/".$idPredioFull."/CONSTANCIA"."/".$identificacionPredio."_".$facturaPredio.".docx");

// Convierte a PDF
Settings::setPdfRendererName(Settings::PDF_RENDERER_TCPDF);
Settings::setPdfRendererPath('./tcpdf');

$phpWord = IOFactory::load("../PROCESO_FISCALIZACION/".$idPredioFull."/CONSTANCIA"."/".$identificacionPredio."_".$facturaPredio.".docx", 'Word2007');
$phpWord->save("../PROCESO_FISCALIZACION/".$idPredioFull."/CONSTANCIA"."/".$identificacionPredio."_".$facturaPredio.".pdf", 'PDF');

// Descarga del PDF en el quipo local.
//$archivofull = ''.$NitCC.'_'.$NoFactura.'.pdf';
//$file = "../PROCESO_FISCALIZACION/".$idPredioFull."/".$NitCC."_".$NoFactura.".pdf";
//header ("Content-Disposition: attachment; filename=".$archivofull.";" );
//header ("Content-Type: application/force-download");
//readfile($file);



echo "<script languaje='javascript' type='text/javascript'>window.close();</script>";
}
}
$factura = new imprimirFactura();

$factura -> codigo = $_GET["codigo"];
$factura -> nombre = $_GET["nombre"];
$factura -> idPredio = $_GET["idPredio"];
$factura -> anio = $_GET["anio"];

$factura -> traerImpresionFactura();

?>