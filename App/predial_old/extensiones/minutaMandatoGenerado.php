<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_FacturaDocumento.php';
include_once SERVER . '/business/DAO/DAO_FacturaDetalleDocumento.php';
include_once SERVER . '/business/DAO/DAO_Tesoreria.php';
include_once SERVER . '/business/class.sessions.php';

require_once "vendor/autoload.php";
use PhpOffice\PhpWord\Style\Language;
use Luecano\NumeroALetras\NumeroALetras;

class imprimirFactura{

public $codigo;
// public $fecha;
// public $fechaFinal;

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
 
 // $fechaInicial = $this->fecha;
 // $fechaFinall = $this->fechaFinal;

// LOCAL
$serverName = "DESARROLLO\SQLEXPRESS2019";
$connectionInfo = array( "Database"=>"erpsofts_paipa_test", "UID"=>"sa", "PWD"=>"Server2019");

// PRODUCCIÓN  
//$serverName = "167.114.216.134\\MSSQLSERVER2019";
//$connectionInfo = array( "Database"=>"erpsofts_paipa_test", "UID"=>"erpsofts_pse", "PWD"=>"predial123.");
 
 $conn = sqlsrv_connect( $serverName, $connectionInfo);

 /* ------ Consultar ID predio ----------------------------------------
$queryy = "SELECT p.id FROM predios as p where codigo_predio = '$idPredioFull'";
$stmtt = sqlsrv_query( $conn, $queryy );

if( $stmtt === false) { $roww = NULL;
}else{
    while( $res = sqlsrv_fetch_array( $stmtt, SQLSRV_FETCH_ASSOC) ) {
            $roww[] = $res;
     }
}

$idPredio = $roww[0]['id'];

*/

$con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
$queryq = "SELECT *, (SELECT su.usu_Nombre FROM conf_usuario as su 
                    where su.usu_Id = pre.pre_IdUsuario) as usuario,
                    (SELECT con.con_NombreDirector FROM pre_configuracion as con 
            where con.con_Id = pre.pre_IdDirector and con.con_Estado = 1) as director,
            (SELECT con.con_Resolucion FROM pre_configuracion as con 
            where con.con_Id = pre.pre_IdDirector and con.con_Estado = 1) as resolucion
            FROM pre_prediosgenerados pre WHERE pre.pre_CodigoPredio = '$idPredioFull' 
                ORDER BY pre.pre_Id DESC LIMIT 1";
$dataq = $con->consultar($queryq);

if( $con->getNumeroFilasConsultadas($dataq) >0 ){ 
    while($ress = $con->obnerFila($dataq)){
        $roww[] = $ress;
    }
}else{ $roww=[]; }

$fechaInicial = $roww[0]['pre_Fecha'];
$fechaFinall = $roww[0]['pre_FechaFinal'];
$NomUsu = $roww[0]['usuario'];
$idResolucion = $roww[0]['pre_Id'];
$NomDirector = $roww[0]['director'];
$NomResolucion = $roww[0]['resolucion'];
$diaCreacion = $roww[0]['pre_DiaCreacion'];
$mesCreacion = $roww[0]['pre_MesCreacion'];
$anioCreacion = $roww[0]['pre_AnioCreacion'];

// ----- Calculo Predio -------------------------------------------------
//$queryye = "EXEC [dbo].[SP_CALCULO_PREDIAL] @ANO = 2023, @PREDIO_ID = $idPredio";
//$stmt = sqlsrv_query( $conn, $queryye);

 $query = "SELECT p.id, p.direccion, (SELECT TOP(1)pr.nombre FROM predios_propietarios as pp inner join propietarios as pr on pp.id_propietario=pr.id
            where pp.id_predio = p.id) as nombre,(SELECT TOP(1)pr.identificacion FROM predios_propietarios as pp inner join propietarios as pr on pp.id_propietario=pr.id
            where pp.id_predio = p.id) as identificacion, (SELECT SUM(prep.total_calculo) from predios_pagos as prep where prep.id_predio = p.id and prep.pagado = 0 and (prep.ultimo_anio > $fechaInicial and prep.ultimo_anio <= $fechaFinall)) as valorTotal, (SELECT STRING_AGG(prep.ultimo_anio, ',') from predios_pagos as prep where prep.id_predio = p.id and prep.pagado = 0 and (prep.ultimo_anio > $fechaInicial and prep.ultimo_anio <= $fechaFinall)) as anios,
            (SELECT dato.matricula_inmobiliaria FROM predios_datos as dato where dato.id_predio = p.id) as matricula
                FROM predios as p
                where codigo_predio = '$idPredioFull'";

$stmt = sqlsrv_query( $conn, $query );

    if( $stmt === false) { $row = NULL;
    }else{
        while( $res = sqlsrv_fetch_array( $stmt, SQLSRV_FETCH_ASSOC) ) {
                $row[] = $res;
         }
    }

	$nombre = $row[0]['nombre'];
    $direccion = $row[0]['direccion'];
    $matricula = $row[0]['matricula'];

    $identificacion = $row[0]['identificacion'];
    $valorTotal = $row[0]['valorTotal'];
    $anios = $row[0]['anios'];
    $idPredio = $row[0]['id'];


    $formatter = new NumeroALetras();
    $valorLetras = $formatter->toWords($valorTotal, 0);
    //$valorLetras = $formatter->toWords(120300, 0).' PESOS';

    $queryAni = "SELECT prep.ultimo_anio
        from predios_pagos as prep where prep.id_predio = $idPredio and prep.pagado = 0 
            and (prep.ultimo_anio > $fechaInicial and prep.ultimo_anio <= $fechaFinall) order by prep.ultimo_anio";

    $stmt = sqlsrv_query( $conn, $queryAni );

    if( $stmt === false) { $rowani = NULL;
    }else{
    while( $res = sqlsrv_fetch_array( $stmt, SQLSRV_FETCH_ASSOC) ) {
            $rowani[] = $res;
    }
    }
    $consolAnios='';
    foreach ($rowani as $key => $item) {
        
        $consolAnios = $consolAnios.', '.$item['ultimo_anio'];
    }

$documento = new \PhpOffice\PhpWord\PhpWord();
$propiedades = $documento->getDocInfo();
$propiedades->setCreator("ERPSOFTSAS");
$propiedades->setTitle("Texto");

$seccion = $documento->addSection();

$subsequent = $seccion->addHeader();
$subsequent->addImage('images/header.png', array('align'=>'center', 'width' => 400, 'height' => 50));

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

//$seccion = $documento->addSection();
# Simple texto
# $seccion->addText("CITACIÓN A NOTIFICACIÓN PERSONAL");

# Otra tabla
$estiloTabla = [
    "borderColor" => "000000",
    "borderSize" => 6,
    'spaceAfter'=> 0 ,
    'align' => 'right',
];

// $styleTable = array('borderSize' => 6, 'borderColor' => '999999');

// Guardarlo para usarlo más tarde
$documento->addTableStyle("estilo2", $estiloTabla);
$tabla = $seccion->addTable("estilo2");

$fuente = [
    "name" => "Century Gothic",
    "size" => 9,
];

// PRIMERA TABLA ---------------------------
$tabla->addRow();

$celda = $tabla->addCell(3500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Código Dependencia que Genera',$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$celda = $tabla->addCell(500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('122',$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
/*
$celda = $tabla->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Código Serie',$fuente);
$celda = $tabla->addCell(500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('-');
$celda = $tabla->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Código Subserie',$fuente);

$row = $tabla->addRow();
$celda = $tabla->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('122');
$celda = $tabla->addCell(500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('-');
$celda = $tabla->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('');
$celda = $tabla->addCell(500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('-');
$celda = $tabla->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('');

$row = $tabla->addRow();
$celda = $tabla->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('');
$celda = $tabla->addCell(500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('');
$celda = $tabla->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('');
$celda = $tabla->addCell(500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('');
$celda = $tabla->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('');
*/

$seccion->addText('',$fuente,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

// TABLA DE resolucion ---------------------

$estiloTablaDos = [
    "borderColor" => "000000",
    "borderSize" => 6,
    "cellMargin" => 10,
    'align' => 'center',
];

$fuenteDos = [
    "name" => "Century Gothic",
    "size" => 9,
    "bold" => true,
    'align' => 'center'
];

$fuenteTituloDos = [
    "name" => "Arial",
    "size" => 9,
];

$documento->addTableStyle("estilo3", $estiloTablaDos);
$tabla = $seccion->addTable("estilo3");

$row = $tabla->addRow();
$row->addCell(5000, array('gridSpan' => 2, 'vMerge' => 'restart'))->addText('Marque con X',$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Número',$fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row = $tabla->addRow();
$row->addCell(3000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('RESOLUCIÓN',$fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('X',$fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('122-', $fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row = $tabla->addRow();
$row->addCell(3000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('AUTO',$fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('',$fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2000, array('vMerge' => 'continue'));

$seccion->addText('',$fuenteDos,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

// TABLA DE FECHA ---------------------
$documento->addTableStyle("estilo3", $estiloTablaDos);
$tabla = $seccion->addTable("estilo3");
//$tabla = $seccion->addTable(array('align' => 'center'),"estilo2");

$row = $tabla->addRow();
$row->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Fecha',$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Día',$fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Mes',$fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Año',$fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

$row = $tabla->addRow();
$row->addCell(1000, array('vMerge' => 'continue'));
$row->addCell(1000)->addText($diaCreacion, $fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(1000)->addText($mesCreacion, $fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(1000)->addText($anioCreacion, $fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

$seccion->addText('',$fuenteDos,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));


// TABLA DE DATOS PREDIO ---------------------
$documento->addTableStyle("estilo4", $estiloTablaDos);
$tabla = $seccion->addTable("estilo4");

$row = $tabla->addRow();
$row->addCell(2500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Por medio del (la) cual',$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(7500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('SE LIBRA MANDAMIENTO DE PAGO POR CONCEPTO DE IMPUESTO PREDIAL', $fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

$seccion->addText('',$fuenteDos,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

// TABLA DE DATOS PREDIO ---------------------
$documento->addTableStyle("estilo5", $estiloTablaDos);
$tabla = $seccion->addTable("estilo5");

$row = $tabla->addRow();
$row->addCell(10000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('LA DIRECCIÓN DE IMPUESTOS RENTAS Y JURISDICCIÓN COACTIVA',$fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row = $tabla->addRow();
$row->addCell(10000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('En uso de sus atribuciones conferidas por el Acuerdo N° 019 de fecha 22 de Diciembre 2022 - Estatuto de Rentas y Tributario del Municipio de Paipa y el Estatuto Tributario Nacional, Resolución Nº 044 de 2017, Resolución Nº 090 de 2017,  '.$NomResolucion.' y demás normas concordantes', $fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

$seccion->addText('',$fuenteDos,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

// -------  TABLA DE DATOS PREDIO ---------------------

$estiloTablaTres = [
    "borderColor" => "000000",
    'align' => 'center'
];


$documento->addTableStyle("estilo6", $estiloTablaTres);
$tabla = $seccion->addTable("estilo6");

$row = $tabla->addRow();
$row->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('CONSIDERANDO',$fuenteDos);


$seccion->addText("Que el contribuyente ".$nombre." identificado con Cédula de ciudadanía No. ".$identificacion." en calidad de sujeto pasivo del impuesto predial y complementarios del predio identificado con ficha catastral ".$idPredioFull." ubicado en ".$direccion." debe al Municipio de Paipa  - Boyacá, la suma de $ ".$valorTotal." (".$valorLetras.") por concepto de impuesto predial adeudado de las vigencias ".$consolAnios.", más la actualización que se generen hasta su pago.",$fuente, array('align'=>'both'));
$seccion->addText("Que obra al Despacho para su cobro por Jurisdicción Coactiva la Liquidación Oficial mediante  estado de cuenta  de fecha ".$diaCreacion."/".$mesCreacion."/".$anioCreacion.", de la que se evidencia una obligación clara, expresa y actualmente exigible a favor del Municipio de Paipa - Boyacá, y en contra de ".$nombre." identificado con Cédula de ciudadanía No. ".$identificacion.", por concepto de Impuesto Predial Unificado en cuantía de $".$valorTotal." (".$valorLetras.").",$fuente, array('align'=>'both'));
$seccion->addText("Así mismo, una vez verificado el Sistema (SISTEMAS ERPSOFT S.A.S.), se encuentra que el valor por las vigencias de ".$consolAnios.", por valor de $ ".$valorTotal." (".$valorLetras.") no ha sido cancelado por el deudor, motivo por el cual es procedente dar inicio al procedimiento de cobro administrativo coactivo contenido en los Artículos 823 y siguientes del Estatuto Tributario, para obtener su pago.",$fuente, array('align'=>'both'));
$seccion->addText("Que la Dirección de Impuestos Rentas y Jurisdicción Coactiva del Municipio de Paipa es competente para el cobro según lo dispuesto en el Resolución Nº 090 de 2017 “POR MEDIO DEL CUAL SE HACE UNA DELEGACION A LA DIRECCION DE IMPUESTOS, RENTAS Y JURISDICCION COACTIVA DE LA SECRETARIA DE HACIENDA, PARA EJERCER LA FISCALIZACION, Y DETERMINACION DE LOS IMPUESTOS, TASAS Y CONTRIBUCIONES MUNICIPALES Y LA JURISDICCION COACTIVA.”,  y los Artículos 823 y siguientes del Estatuto Tributario Nacional.",$fuente, array('align'=>'both'));
$seccion->addText("Que el suscrito funcionario es competente para conocer del citado procedimiento, según lo dispuesto en el ".$NomResolucion." por medio de la que  “SE EFECTÚA UN NOMBRAMIENTO ORDINARIO” como Director Técnico Dirección de Impuestos, Rentas y Jurisdicción Coactiva adscrita a la Secretaria de Hacienda del Municipio de Paipa.",$fuente, array('align'=>'both'));
$seccion->addText("Por lo anteriormente expuesto, la Dirección de Impuestos Rentas y Jurisdicción Coactiva del Municipio de Paipa,",$fuente, array('align'=>'both'));

$documento->addTableStyle("estilo6", $estiloTablaTres);
$tabla = $seccion->addTable("estilo6");

$row = $tabla->addRow();
$row->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('RESUELVE:',$fuenteDos);

$primero = $seccion->addTextRun('estilo2');
$primero->addText("PRIMERO.- AVOCAR CONOCIMIENTO", $fuenteDos, array('align'=>'both'));
$primero->addText(" respecto del procedimiento Administrativo de Cobro de Coactivo No ___________ del año 2023, en contra de ".$nombre." identificado con Cédula de ciudadanía No. ".$identificacion." por concepto de impuesto predial adeudado de las vigencias ".$consolAnios." junto con los intereses liquidados a la emisión del presente mandamiento de pago.",$fuente, array('align'=>'both'));

$segundo = $seccion->addTextRun('estilo2');
$segundo->addText("SEGUNDO.- LIBRAR ORDEN DE PAGO ",$fuenteDos , array('align'=>'both'));
$segundo->addText(" por la vía administrativa coactiva a favor del Municipio de Paipa - Boyacá y a cargo de ".$nombre." identificado con Cédula de ciudadanía No. ".$identificacion." por las siguientes sumas:",$fuente, array('align'=>'both'));

$seccion->addText("a) Por la suma de $ ".$valorTotal." (".$valorLetras.") por concepto Impuesto Predial Unificado de las vigencias ".$consolAnios." junto con los intereses generados a la fecha según estado de cuenta liquidada a la emisión de este mandamiento, más los intereses que se causen hasta el cumplimiento total de la obligación.",$fuente, array('align'=>'both'));
$seccion->addText("b) Por la suma de SETENTA Y SIETE MIL TRESCIENTOS TREINTA Y CUATRO PESOS  M/CTE ($77.334) equivalente a 2 S.M.D.L.V y correspondiente al valor de las costas del presente proceso, de acuerdo con la tasación efectuada mediante Resolución N° 122-0020 de fecha 20 enero 2023, emitida por la Dirección de Impuestos, Rentas y Jurisdicción Coactiva para todos los procesos administrativos de cobro.",$fuente, array('align'=>'both'));

$tercero = $seccion->addTextRun('estilo2');
$tercero->addText("TERCERO.- NOTIFICAR",$fuenteDos, array('align'=>'both'));
$tercero->addText(" este mandamiento de pago personalmente al ejecutado (a), su apoderado o representante legal, previa citación enviada por correo certificado, dirigida a la dirección del mismo para que comparezca dentro de los diez (10) días siguientes a la misma. De no comparecer en el término fijado, se notificará por AVISO conforme a lo dispuesto en el Artículo 69 del Código de Procedimiento Administrativo y de lo Contencioso Administrativo.",$fuente, array('align'=>'both'));
$cuarto = $seccion->addTextRun('estilo2');
$cuarto->addText("CUARTO.- ADVERTIR", $fuenteDos, array('align'=>'both'));
$cuarto->addText(" al deudor que dispone de quince (15) días a partir de su notificación, para cancelar la deuda o proponer excepciones legales que estime pertinentes como dispone el Artículo 831 del Estatuto Tributario.",$fuente, array('align'=>'both'));
$quinto = $seccion->addTextRun('estilo2');
$quinto->addText("QUINTO.- ORDENAR ESTUDIO DE BIENES", $fuenteDos, array('align'=>'both'));
$quinto->addText(" para realizar la investigación pertinente a fin de identificar los bienes del deudor, con el fin de realizar el embargo, secuestro de estos,  Líbrese los oficios correspondientes.",$fuente, array('align'=>'both'));
$sexto = $seccion->addTextRun('estilo2');
$sexto->addText("SEXTO.- ORDENAR EL EMBARGO", $fuenteDos, array('align'=>'both'));
$sexto->addText(" del bien inmueble ubicado en la Jurisdicción del Municipio de Paipa – Boyacá -, de propiedad del contribuyente ".$nombre." identificado con Cédula de ciudadanía No. ".$identificacion." registrado en el Folio de Matricula Inmobiliaria No ".$matricula." de la Oficina de Registro de Instrumentos Públicos  y código catastral ".$idPredioFull.".",$fuente, array('align'=>'both'));
$septimo = $seccion->addTextRun('estilo2');
$septimo->addText("SEPTIMO.- LIBRAR", $fuenteDos, array('align'=>'both'));
$septimo->addText(" los oficios correspondientes  para el cumplimiento del artículo anterior y comuníquese a la oficina encargada para su registro.",$fuente, array('align'=>'both'));

$documento->addTableStyle("estilo6", $estiloTablaTres);
$tabla = $seccion->addTable("estilo6");

$row = $tabla->addRow();
$row->addCell(10000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('NOTIFIQUESE Y CUMPLASE',$fuenteDos, array('align'=>'center'));

$seccion->addText('',$fuenteDos,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$seccion->addText('',$fuenteDos,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$seccion->addText('',$fuenteDos,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

// TABLA DE EMITE EMPLEO ---------------------
$documento->addTableStyle("estilo7", $estiloTablaDos);
$tabla = $seccion->addTable("estilo7");

$row = $tabla->addRow();
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Emite:',$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(6000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($NomDirector, $fuenteDos, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

$row = $tabla->addRow();
$row->addCell(2000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Empleo',$fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(6000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Director de Impuestos, Rentas y Jurisdicción Coactiva.', $fuente, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

$seccion->addText(' ');


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
$row->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Validación:',$fuenteDosTituloCinco, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(3000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Nombre Completo', $fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Vo.Bo.', $fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('', $fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('No', $fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(800, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Tipo', $fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2700, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Gestión Documental (¿A Quién? - Empleo)', $fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

$row = $tabla->addRow();
$row->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Proyecto:', $fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(3000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText($NomUsu, $fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('', $fuenteTituloCinco, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('', $fuenteTituloCinco, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('1', $fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(800, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Original',$fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2700, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Actos Administrativos',$fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

$row = $tabla->addRow();
$row->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Revisó:', $fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(3000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Manuel Sánchez/Director Impuestos y Rentas.', $fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('', $fuenteTituloCinco, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('', $fuenteTituloCinco, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('2',$fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(800, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Copia 1',$fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2700, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Fiscalización y determinación del impuesto, tasas y contribuciones.',$fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
/*
$row = $tabla->addRow();
$row->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Aprobó:', $fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(3000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Alexander  Galán Pérez/Secretario de Hacienda de Paipa', $fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('', $fuenteTituloCinco, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('', $fuenteTituloCinco, array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('3',$fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(800, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('Copia 2',$fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(2700, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('', $fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
*/
$seccion->addText(' ');


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
$row->addCell(2500, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('', $fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));
$row->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('', $fuenteTituloCinco,  array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));

$seccion->addText('',$fuenteTituloCinco,array('align'=>'center','spaceBefore' => 0, 'spaceAfter' => 0));


/*
//____________________________________________________________________

$styleTable = array('borderSize' => 6, 'borderColor' => '999999');
$documento->addTableStyle('Colspan Rowspan', $styleTable);
$table = $seccion->addTable('Colspan Rowspan');

$row = $table->addRow();
$row->addCell(1000, array('vMerge' => 'restart'))->addText('A');
$row->addCell(1000, array('gridSpan' => 3, 'vMerge' => 'restart'))->addText('B');
$row->addCell(1000, array('gridSpan' => 1, 'vMerge' => 'restart'))->addText('C');
$row->addCell(1000, array('gridSpan' => 2, 'vMerge' => 'restart'))->addText('D');
$row->addCell(1000, array('gridSpan' => 2, 'vMerge' => 'restart'))->addText('E');

$row = $table->addRow();
$row->addCell(1000, array('vMerge' => 'continue'));
$row->addCell(1000, array('vMerge' => 'continue', 'gridSpan' => 3));
$row->addCell(1000, array('vMerge' => 'continue', 'gridSpan' => 1));
$row->addCell(1000)->addText('D1');
$row->addCell(1000)->addText('D2');
$row->addCell(1000)->addText('E1');
$row->addCell(1000)->addText('E2');

$row = $table->addRow();
$row->addCell(1000, array('vMerge' => 'continue'));
$row->addCell(1000)->addText('B1');
$row->addCell(1000)->addText('B2');
$row->addCell(1000)->addText('B3');
$row->addCell(1000)->addText('C1');
$row->addCell(1000)->addText('Da');
$row->addCell(1000)->addText('Db');
$row->addCell(1000)->addText('Ea');
$row->addCell(1000)->addText('Eb');
*/

# Para que no diga que se abre en modo de compatibilidad
$documento->getCompatibility()->setOoxmlVersion(15);
# Idioma español de México
$documento->getSettings()->setThemeFontLang(new Language("ES-MX"));


$nombre = "minuta_".$idPredioFull.".docx";
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
$objWriter->save("../documentosPredial/".$idPredioFull."/minuta_".$idPredioFull.".docx");
echo "<script languaje='javascript' type='text/javascript'>window.close();</script>";
*/

}
}
$factura = new imprimirFactura();
$factura -> codigo = $_GET["codigo"];
// $factura -> fecha = $_GET["fecha"];
// $factura -> fechaFinal = $_GET["fechaFinal"];
$factura -> traerImpresionFactura();

?>