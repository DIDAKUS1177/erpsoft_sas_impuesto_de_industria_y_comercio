<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_FacturaDocumento.php';
include_once SERVER . '/business/DAO/DAO_FacturaDetalleDocumento.php';
include_once SERVER . '/business/DAO/DAO_Tesoreria.php';
include_once SERVER . '/business/class.sessions.php';

require_once "vendor/autoload.php";
use PhpOffice\PhpWord\Style\Language;
use PhpOffice\PhpWord\Style\Paper;

class imprimirFactura{

public $codigo;

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

// LOCAL
$serverName = "DESARROLLO\SQLEXPRESS2019";
$connectionInfo = array( "Database"=>"erpsofts_paipa_test", "UID"=>"sa", "PWD"=>"Server2019");

// PRODUCCIÓN  
//$serverName = "167.114.216.134\\MSSQLSERVER2019";
//$connectionInfo = array( "Database"=>"erpsofts_paipa_test", "UID"=>"erpsofts_pse", "PWD"=>"predial123.");

$conn = sqlsrv_connect( $serverName, $connectionInfo);

 $query = "SELECT p.direccion, (SELECT TOP(1)pr.nombre FROM predios_propietarios as pp inner join propietarios as pr on pp.id_propietario=pr.id
            where pp.id_predio = p.id) as nombre
                FROM predios as p
                where codigo_predio = '$idPredioFull'";

$stmt = sqlsrv_query( $conn, $query );

    if( $stmt === false) { $row = NULL;
    }else{
        while( $res = sqlsrv_fetch_array( $stmt, SQLSRV_FETCH_ASSOC) ) {
                $row[] = $res;
         }
    }

$nombrePredio = $row[0]['nombre'];
$direccionPredio = $row[0]['direccion'];

$con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
$query = "SELECT *, (SELECT su.usu_Nombre FROM conf_usuario as su 
            where su.usu_Id = pre.pre_IdUsuario) as usuario,
            (SELECT con.con_NombreDirector FROM pre_configuracion as con 
            where con.con_Id = pre.pre_IdDirector and con.con_Estado = 1) as director
        FROM pre_prediosgenerados pre WHERE pre.pre_CodigoPredio = '$idPredioFull' 
                ORDER BY pre.pre_Id DESC LIMIT 1";
$data = $con->consultar($query);

if( $con->getNumeroFilasConsultadas($data) >0 ){ 
    while($res = $con->obnerFila($data)){
        $roww[] = $res;
    }
}else{ $roww=[]; }

$idResolucion = $roww[0]['pre_Id'];
$NomUsu = $roww[0]['usuario'];
$NomDirector = $roww[0]['director'];
$diaCreacion = $roww[0]['pre_DiaCreacion'];
$mesCreacion = $roww[0]['pre_MesCreacion'];
$anioCreacion = $roww[0]['pre_AnioCreacion'];

$documento = new \PhpOffice\PhpWord\PhpWord();
$propiedades = $documento->getDocInfo();
$propiedades->setCreator("ERPSOFTSAS");
$propiedades->setTitle("Texto");

$seccion = $documento->addSection();

$subsequent = $seccion->addHeader();
$subsequent->addImage('images/header_noti.png', array('align'=>'center', 'width' => 400, 'height' => 50));

$footer = $seccion->addFooter();
$footer->addImage('images/footer.png', array('align'=>'center', 'width' => 400, 'height' => 50));

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

$fuenteSin = [
    "name" => "Century Gothic",
    "size" => 8,
];

// PRIMERA TABLA ---------------------------
$tabla->addRow();
$celda = $tabla->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Código Dependencia',$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$celda = $tabla->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('-',$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0) );
$celda = $tabla->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Código Serie',$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$celda = $tabla->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('-',$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$celda = $tabla->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Código Subserie',$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$celda = $tabla->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('-',$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$celda = $tabla->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Radicado',$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$celda = $tabla->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('-',$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$celda = $tabla->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Número',$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$celda = $tabla->addCell(1000, array('gridSpan' => 2, 'vMerge' => 'restart'))->addText('Marque con X',$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));


$row = $tabla->addRow();

$celda = $tabla->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('122',$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$celda = $tabla->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('-',$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$celda = $tabla->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('07',$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$celda = $tabla->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('-',$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$celda = $tabla->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('01',$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$celda = $tabla->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('-',$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$celda = $tabla->addCell(1000, array('vMerge' => 'continue'));
$celda = $tabla->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('-',$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$celda = $tabla->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('',$fuenteSin, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$celda = $tabla->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Interna',$fuenteSin, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$celda = $tabla->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('  ',$fuenteSin, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

$row = $tabla->addRow();

$celda = $tabla->addCell(1000, array('vMerge' => 'continue'));
$celda = $tabla->addCell(1000, array('vMerge' => 'continue'));
$celda = $tabla->addCell(1000, array('vMerge' => 'continue'));
$celda = $tabla->addCell(1000, array('vMerge' => 'continue'));
$celda = $tabla->addCell(1000, array('vMerge' => 'continue'));
$celda = $tabla->addCell(1000, array('vMerge' => 'continue'));
$celda = $tabla->addCell(1000, array('vMerge' => 'continue'));
$celda = $tabla->addCell(1000, array('vMerge' => 'continue'));
$celda = $tabla->addCell(1000, array('vMerge' => 'continue'));
$celda = $tabla->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Externa',$fuenteSin, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$celda = $tabla->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText(' X ',$fuenteSin, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

$fuenteHipervinculo = [
    "name" => "Century Gothic",
    "size" => 8,
    "italic" => true,
];

$seccion->addText('Información para el Destinatario: Al contestar favor referenciar esta codificación, así:122 – Radicado _____', $fuenteHipervinculo);

// TABLA DE FECHA ---------------------

# Otra tabla
$estiloTablaDos = [
    "borderColor" => "000000",
    "borderSize" => 10,
    "cellMargin" => 10,
];

$fuenteTituloDos = [
    "name" => "Century Gothic",
    "size" => 8,
];

$fuenteDosTituloDos = [
    "name" => "Century Gothic",
    "size" => 8,
    "bold" => true,
];

$documento->addTableStyle("estilo3", $estiloTablaDos);
$tablaDos = $seccion->addTable("estilo3");

$row = $tablaDos->addRow();
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Fecha',$fuenteDosTituloDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Día',$fuenteDosTituloDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Mes',$fuenteDosTituloDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Año',$fuenteDosTituloDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

$row = $tablaDos->addRow();
$row->addCell(1000, array('vMerge' => 'continue'));
$row->addCell(1000)->addText($diaCreacion,$fuenteDosTituloDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(1000)->addText($mesCreacion,$fuenteDosTituloDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(1000)->addText($anioCreacion,$fuenteDosTituloDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

$seccion->addText('',$fuenteDosTituloDos,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

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

$documento->addTableStyle("estilo4", $estiloTablaDos);
$tabla = $seccion->addTable("estilo4");

$row = $tabla->addRow();
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Dirigida a:',$fuenteTituloTres, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(8000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($nombrePredio, $fuenteDosTituloTres, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

$row = $tabla->addRow();
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Cargo / Empleo',$fuenteTituloTres, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(8000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText(' N/A', $fuenteTituloTres, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

$row = $tabla->addRow();
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Entidad',$fuenteTituloTres, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(8000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText(' Particular', $fuenteTituloTres, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

$row = $tabla->addRow();
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Dirección',$fuenteTituloTres, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(8000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($direccionPredio, $fuenteDosTituloTres, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

$seccion->addText('',$fuenteDosTituloTres,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

// TABLA DE ASUNTO ---------------------
$styleTable = array('borderSize' => 6, 'borderColor' => '999999');
$documento->addParagraphStyle('estilo4', array('align'=>'center', 'spaceAfter'=>100));
$documento->addTableStyle("estilo4", $styleTable);
$tabla = $seccion->addTable("estilo4");

$row = $tabla->addRow();
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Asunto',$fuenteTituloTres, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(8000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('CITACIÓN A NOTIFICACIÓN PERSONAL.', $fuenteTituloTres, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
 
$seccion->addText('',$fuenteDosTituloTres,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

# Con fuentes personalizadas
$fuenteTex = [
    "name" => "Century Gothic",
    "size" => 8,
];
$seccion->addText("De manera atenta, y en virtud de lo preceptuado por el artículo 68  del Código de Procedimiento Administrativo y de lo Contencioso Administrativo (Ley 1437 de 2011), le solicitamos comparecer ante este Despacho localizado en la Carrera 22 Nº 25-14 del Municipio de Paipa-Primer Piso, en el horario de 08:00 a.m. a 12:00 p.m. o de 02:00 p.m. a 06:00 p.m., de lunes a viernes, dentro de los cinco (5) días hábiles siguientes al recibo de esta comunicación, con el fin de notificarle personalmente el contenido del Acto Administrativo N° 122 - ______ de fecha ".$diaCreacion."/".$mesCreacion."/".$anioCreacion.", proferido por la Dirección de Impuestos, Rentas y Jurisdicción Coactiva de la Secretaria de Hacienda del Municipio de Paipa.",$fuenteTex,  array('align'=>'both'));

$seccion->addText("De no presentarse dentro del término mencionado, la notificación se surtirá por Aviso, tal y como lo dispone el inciso 2 del artículo 69  de la Ley 1437 de 2011.",$fuenteTex, array('align'=>'both'));

$seccion->addText("NOTA:",$fuenteTituloTres);
$seccion->addText("Si el predio objeto de esta notificación se encuentra en trámite de sucesión, se requiere que se informe a este Despacho para proceder a realizar la respectiva notificación a los herederos del deudor y/o deudores solidarios.",$fuenteTex, array('align'=>'both'));

$seccion->addText("Cordialmente.",$fuenteTex);
$seccion->addText(' ');

// TABLA DE EMITE EMPLEO ---------------------

$estiloTablaDos = [
    "borderColor" => "000000",
    "borderSize" => 6,
    "cellMargin" => 10,
    'align' => 'center',
];
$documento->addTableStyle("estilo7", $estiloTablaDos);
$tabla = $seccion->addTable("estilo7");

$row = $tabla->addRow();
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Emite:',$fuenteTex, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(6000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($NomDirector, $fuenteTituloTres, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

$row = $tabla->addRow();
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Empleo',$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(6000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Director de Impuestos, Rentas y Jurisdicción Coactiva.', $fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

$seccion->addText('',$fuenteTex,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));


// TABLA DE VALIDACIÓN ---------------------

$fuenteTituloCinco = [
    "name" => "Century Gothic",
    "size" => 6,
];

$fuenteDosTituloCinco = [
    "name" => "Century Gothic",
    "size" => 6,
    "bold" => true,
];

$documento->addTableStyle("estilo8", $estiloTablaDos);
$tabla = $seccion->addTable("estilo8");

$row = $tabla->addRow();
$row->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Validación:',$fuenteDosTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(3500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Nombre Completo', $fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Vo.Bo.', $fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('', $fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('No', $fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(800, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Tipo', $fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(3200, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Gestión Documental (¿A Quién? - Empleo)', $fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

$row = $tabla->addRow();
$row->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Proyecto:', $fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(3500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($NomUsu, $fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('',$fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('',$fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('1', $fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(800, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Original',$fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(3200, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Actos Administrativos',$fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

$row = $tabla->addRow();
$row->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Revisó:', $fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(3500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Manuel Sánchez/Director Impuestos y Rentas.', $fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('',$fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('',$fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('2',$fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(800, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Copia 1',$fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(3200, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Fiscalización y determinación del impuesto, tasas y contribuciones.',$fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
/*
$row = $tabla->addRow();
$row->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Aprobó:', $fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(3500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Alexander  Galán Pérez/Secretario de Hacienda de Paipa', $fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('',$fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('',$fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('3',$fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(800, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Copia 2',$fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(3200, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('',$fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
*/
$seccion->addText('',$fuenteTituloCinco,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));


// TABLA DE ANEXOS ---------------------

$documento->addTableStyle("estilo9", $estiloTablaDos);
$tabla = $seccion->addTable("estilo9");

$row = $tabla->addRow();
$row->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('No:', $fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(4000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Archivado en', $fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('',$fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('No:', $fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Anexos', $fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Folios', $fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

$row = $tabla->addRow();
$row->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('1', $fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(4000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Actos Administrativos',$fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('',$fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('1', $fuenteTituloCinco, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('', $fuenteTituloCinco, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('', $fuenteTituloCinco, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

$seccion->addText('',$fuenteDosTituloDos,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

# Con fuentes personalizadas
$fuenteTexx = [
    "name" => "Century Gothic",
    "size" => 5,
];

$fuenteDosTituloSeis = [
    "name" => "Century Gothic",
    "size" => 6,
    "bold" => true,
];
$seccion->addText("Artículo 68.", $fuenteDosTituloSeis, array('align'=>'both'));
$seccion->addText("Citaciones para notificación personal. Si no hay otro medio más eficaz de informar al interesado, se le enviará una citación a fa dirección, al número de fax o al correo electrónico que figuren en el expediente o puedan obtenerse del registro mercantil, para que comparezca a la diligencia de notificación personal. El envío de la citación se hará dentro de los cinco (5) días siguientes a la expedición del acto, y de dicha diligencia se dejará constancia en el expediente. Cuando se desconozca la información sobre el destinatario señalada en el inciso anterior, la citación se publicará en la página electrónica o en un lugar de acceso al público de la respectiva entidad por el término de cinco (5) días.",$fuenteTexx, array('align'=>'both'));
$seccion->addText("Artículo 69.",  $fuenteDosTituloSeis, array('align'=>'both'));
$seccion->addText("Notificación por aviso. Si no pudiere hacerse la notificación personal al cabo de los cinco (5) días del envío de la citación, esta se hará por medio de aviso que se remitirá a la dirección, al número de fax o al correo electrónico que figuren en el expediente o puedan obtenerse del registro mercantil, acompañado de copia íntegra del acto administrativo. El aviso deberá indicar la fecha y la del acto que se notifica, la autoridad que lo expidió, los recursos que legalmente proceden, las autoridades ante quienes deben interponerse, los plazos respectivos y la advertencia de que la notificación se considerará surtida al finalizar el día siguiente al de la entrega del aviso en el lugar de destino.",$fuenteTexx, array('align'=>'both'));
$seccion->addText("Cuando se desconozca la información sobre el destinatario, el aviso, con copia íntegra del acto administrativo, se publicará en la página electrónica y en todo caso en un lugar de acceso al público de la respectiva entidad por el término de cinco (5) días, con la advertencia de que la notificación se considerará surtida al finalizar el día siguiente al retiro del aviso. En el expediente se dejará constancia de la remisión o publicación del aviso y de la fecha en que por este medio quedará surtida la notificación personal",$fuenteTexx, array('align'=>'both'));

# Para que no diga que se abre en modo de compatibilidad
$documento->getCompatibility()->setOoxmlVersion(15);
# Idioma español de México
$documento->getSettings()->setThemeFontLang(new Language("ES-MX"));

$nombre = "notificacion_".$idPredioFull.".docx";
header("Content-Description: File Transfer");
header('Content-Disposition: attachment; filename="' . $nombre . '"');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Transfer-Encoding: binary');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Expires: 0');

# Guardarlo
$objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($documento, "Word2007");

$objWriter->save("php://output");

/*
$micarpeta = '../documentosPredial/'.$idPredioFull;
if (!file_exists($micarpeta)) {
mkdir("../documentosPredial/".$idPredioFull, 0700);
}
$objWriter->save("../documentosPredial/".$idPredioFull."/notificacion_".$idPredioFull.".docx");
echo "<script languaje='javascript' type='text/javascript'>window.close();</script>";
*/
}
}
$factura = new imprimirFactura();
$factura -> codigo = $_GET["codigo"];
$factura -> traerImpresionFactura();

?>