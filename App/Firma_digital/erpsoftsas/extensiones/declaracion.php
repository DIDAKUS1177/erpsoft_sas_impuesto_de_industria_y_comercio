<?php
require_once('tcpdf/tcpdf.php');

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.conexionSqlServer.php';

class ICAPdf extends TCPDF {
    public function Header(){}
    public function Footer(){}
}

function dibujarTextoVertical($pdf, $texto, $y_inicio, $y_fin) {


    $pdf->StartTransform();
    $pdf->Rotate(90, $y_inicio, $y_fin);
    $pdf->SetXY($y_inicio, $y_fin);

    $pdf->SetFont('helvetica','B',5);
    $pdf->MultiCell(30, 4, $texto, 0, 'C');
    $pdf->StopTransform();
}


$pdf = new ICAPdf('P','mm',array(215.9, 330.2),true,'UTF-8',false);
$pdf->SetMargins(10,10,10);
// Sin esto, TCPDF inserta una segunda pagina en cuanto el contenido (p.ej.
// la fila de la firma) se acerca al margen inferior, dejando el bloque de
// codigo de barras / referencia de recaudo suelto en una pagina aparte y
// las etiquetas verticales de dibujarTextoVertical() (que asumen pagina 1)
// mal ubicadas.
$pdf->SetAutoPageBreak(false, 0);
$pdf->AddPage();
$pdf->SetFont('helvetica','',7);

/* ===========================
CONEXIÓN Y DATOS
=========================== */

$con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
$idDeclaracion = $_GET['dec_Id'] ?? 0;


/* ===========================
QUERY PRINCIPAL
=========================== */

$sql = "
SELECT 
    d.*,
    e.*,
    c.*,
    ciu.ciu_Nombre,
    ciu.ciu_Departamento
FROM ind_declaraciones_ica d
INNER JOIN ind_establecimientos e 
    ON e.est_Id = d.dec_IdEstablecimiento
INNER JOIN ind_contribuyentes c 
    ON c.ind_Id = d.dec_IdContribuyente
LEFT JOIN conf_ciudades ciu
    ON ciu.ciu_Id = c.ind_IdCiudad
WHERE d.dec_Id = ?
";

$stmt = $con->consultar($sql, [$idDeclaracion]);
$row = $con->obnerFila($stmt);

if (!$row) {
    http_response_code(404);
    die('Declaración no encontrada.');
}

/* ===========================
ACTIVIDADES
=========================== */

$sqlAct = "
SELECT 
    da.*,
    ca.acc_Codigo,
    ca.acc_Nombre
FROM ind_declaraciones_ica_actividades da
INNER JOIN ind_actividadescomercio ca 
    ON ca.acc_Id = da.dia_IdActividad
WHERE da.dia_IdDeclaracion = ?
";

$stmtAct = $con->consultar($sqlAct, [$idDeclaracion]);

$actividades = [];
while($a = $con->obnerFila($stmtAct)){
    $actividades[] = $a;
}

/* ===========================
FIRMA DIGITAL
=========================== */

$sqlFirma = "
SELECT fd_NombreUsuario, fd_EmailUsuario, fd_FechaHora, fu.fu_Base64
FROM firmas_declaraciones fd
LEFT JOIN firmas_usuario fu ON fu.fu_IdUsuario = fd.fd_IdUsuario
WHERE fd.fd_NumeroDeclaracion = ?
";
$stmtFirma = $con->consultar($sqlFirma, [$idDeclaracion]);
$firmaData = $con->obnerFila($stmtFirma);

/* ===========================
FORMATEOS
=========================== */

$nombreCompleto = trim(
    $row['ind_PrimerNombre'].' '.
    $row['ind_SegundoNombre'].' '.
    $row['ind_PrimerApellido'].' '.
    $row['ind_SegundoApellido']
);

$tipoDocumento = 'CC';
if($row['ind_IdTipoDocumento'] == 2) $tipoDocumento = 'NIT';
if($row['ind_IdTipoDocumento'] == 3) $tipoDocumento = 'CE';


$totalImpuestoActividades = 0;

foreach($actividades as $act){
    $totalImpuestoActividades += $act['dia_ValorImpuesto'];
}


/* ===========================
DATOS
=========================== */

$d = [
    // Encabezado entidad / periodo
    'entidad'     => 'ALCALDÍA DE PAIPA',
    'secretaria'  => 'SECRETARÍA DE HACIENDA',
    'nit_entidad' => '891855138-1',

    'dep'         => 'BOYACA',
    'mun'         => 'PAIPA',
    'anio'        => '2025',
    'fecha_max'   => '',
    'num_form' => $row['dec_NumeroDeclaracion'],

    // Datos contribuyente
    /*
    'razon'       => 'SISTEMAS ERPSOFT S.A.S',
    'tipo_documento' => 'NIT', // CC, NIT, CE
    'nit'         => '901632232',
    'dv'          => '2',
    'direccion'   => 'CALLE 6 NRO 8/23 BARRIO LOS ALCAZAREZ',
    'telefono'    => '3125042143',
    'correo'      => 'erpsoftsas@gmail.com',
    'dep_notif'   => 'BOYACA',
    'mun_notif'   => 'DUITAMA',
    'num_estab'   => '1',
    */
    'clasificacion' => 'SELECCIONE',

    'razon' => $row['ind_Persona'] == 1 ? $nombreCompleto : $row['est_Nombre'],
    'tipo_documento' => $tipoDocumento,
    'nit' => $row['ind_NumeroIdentificacion'],
    'dv' => $row['ind_DV'],
    'direccion' => $row['ind_Direccion'],
    'telefono' => $row['ind_Telefono'],
    'correo' => $row['ind_Email'],
    'dep_notif' => $row['ciu_Departamento'],
    'mun_notif' => $row['ciu_Nombre'],
    'num_estab' => '1',



    // Tipo de declaración
    'es_decl_inicial'   => true,
    'es_solo_pago'      => false,
    'es_correccion'     => false,
    'es_consorcio'      => false,
    'patrimonio_autonomo' => false,

    // Base gravable (renglones 8–16)
    /*
    'ing8'  => '$ 296.115.000',
    'ing9'  => '$ 278.465.000',
    'ing10' => '$ 17.650.000',
    'ing11' => '0',
    'ing12' => '0',
    'ing13' => '0',
    'ing14' => '0',
    'ing15' => '0',
    'ing16' => '$ 17.650.000',
    */
    'ing8'  => '$'.number_format($row['dec_TotalIngresos'] ?? 0,0,',','.'),
    'ing9'  => '$'.number_format($row['dec_IngresosFueraMunicipio'] ?? 0,0,',','.'),
    'ing10' => '$'.number_format(($row['dec_TotalIngresos'] ?? 0) - ($row['dec_IngresosFueraMunicipio'] ?? 0),0,',','.'),
    'ing11' => '$'.number_format($row['dec_IngresosDevoluciones'] ?? 0,0,',','.'),
    'ing12' => '$'.number_format($row['dec_IngresosExportaciones'] ?? 0,0,',','.'),
    'ing13' => '$'.number_format($row['dec_IngresosVentas'] ?? 0,0,',','.'),
    'ing14' => '$'.number_format($row['dec_IngresosActividades'] ?? 0,0,',','.'),
    'ing15' => '$'.number_format($row['dec_IngresosOtrasActividades'] ?? 0,0,',','.'),
    'ing16' => '$'.number_format($row['dec_BaseGravable'] ?? 0,0,',','.'),



    // Actividades (1–3) y total (17)
    /*
    'codigo1'                => '342',
    'ingresos1'              => '$ 17.650.000',
    'tarifa1'                => '9,4',
    'impuesto1'              => '$ 166.000',

        
    'actividad2_ingresos'    => '0',
    'actividad3_ingresos'    => '0',

    'total_ingresos_gravados'=> '$ 17.650.000',
    'total_impuesto_gravado' => '$ 166.000',

    */
    'codigo1' => $actividades[0]['acc_Codigo'] ?? '',
    'ingresos1' => '$'.number_format($actividades[0]['dia_BaseGravable'] ?? 0,0,',','.'),
    'tarifa1' => $actividades[0]['dia_Tarifa'] ?? '',
    'impuesto1' => number_format($actividades[0]['dia_ValorImpuesto'] ?? 0,0,',','.'),

    'codigo2' => $actividades[1]['acc_Codigo'] ?? '',
    'ingresos2' => '$'.number_format($actividades[1]['dia_BaseGravable'] ?? 0,0,',','.'),
    'tarifa2' => $actividades[1]['dia_Tarifa'] ?? '',
    'impuesto2' => number_format($actividades[1]['dia_ValorImpuesto'] ?? 0,0,',','.'),

    'codigo3' => $actividades[2]['acc_Codigo'] ?? '',
    'ingresos3' => '$'.number_format($actividades[2]['dia_BaseGravable'] ?? 0,0,',','.'),
    'tarifa3' => $actividades[2]['dia_Tarifa'] ?? '',
    'impuesto3' => number_format($actividades[2]['dia_ValorImpuesto'] ?? 0,0,',','.'),


    'total_ingresos_gravados' => '$'.number_format($row['dec_BaseGravable'] ?? 0,0,',','.'),
    //'total_impuesto_gravado' => '$'.number_format($row['dec_ValorImpuesto'] ?? 0,0,',','.'),
    'total_impuesto_gravado' => '$'.number_format($totalImpuestoActividades ?? 0,0,',','.'),



    // Otros renglones (18–25)
   
    
    'gen_energia_kw' => $row['dec_CapacidadInstalada'],
    'imp_ley56' => '$'.number_format($row['dec_ValorImpuesto'] ?? 0,0,',','.'),


/*
    'ica'              => '$ 166.000',
    'avisos'           => '$ 25.000',
    'bomberil'         => '$ 5.000',
    'sobretasa_seguridad' => '0',
    'total25'          => '$ 196.000',
    */
    'ica' => '$'.number_format($row['dec_ValorConcepto1'] ?? 0,0,',','.'),
    'avisos' => '$'.number_format($row['dec_ValorConcepto2'] ?? 0,0,',','.'),
    'bomberil' => '$'.number_format($row['dec_ValorConcepto3'] ?? 0,0,',','.'),
    'sobretasa_seguridad' => '$ 0',
    'total25' => '$'.number_format($row['dec_ValorConcepto4'] ?? 0,0,',','.'),


    // Ajustes, anticipos y saldos (26–33)
    /*
    'exencion'         => '0',
    'ret27'            => '$ 177.000',
    'autoretenciones'  => '0',
    'anticipo_anterior'=> '0',
    'anticipo_siguiente'=> '0',
    'sanciones'        => '0',
    'saldo_favor_ant'  => '0',
    'saldo33'          => '$ 19.000',
    */
    'exencion' => '$'.number_format($row['dec_ValorConcepto5'] ?? 0,0,',','.'),
    'ret27' => '$'.number_format($row['dec_ValorConcepto6'] ?? 0,0,',','.'),
    'autoretenciones' => '$'.number_format($row['dec_ValorConcepto7'] ?? 0,0,',','.'),
    'anticipo_anterior' => '$'.number_format($row['dec_ValorConcepto8'] ?? 0,0,',','.'),
    'anticipo_siguiente' => '$'.number_format($row['dec_ValorConcepto9'] ?? 0,0,',','.'),
    'sanciones' => '$'.number_format($row['dec_ValorConcepto10'] ?? 0,0,',','.'),
    'saldo_favor_ant' => '$'.number_format($row['dec_ValorConcepto11'] ?? 0,0,',','.'),
    'saldo33' => '$'.number_format($row['dec_ValorConcepto12'] ?? 0,0,',','.'),


    // Liquidación privada y pago (34–40)
    /*
    'total_saldo_favor'      => '0',
    'valor_pagar'            => '0',
    'descuento_pronto_pago'  => '0',
    'intereses_mora'         => '0',
    'total_a_pagar'          => '0',
    'pago_voluntario'        => '0',
    'total_con_pago_voluntario' => '0',
*/

    'total_saldo_favor' => '$'.number_format($row['dec_ValorConcepto13'] ?? 0,0,',','.'),
    'valor_pagar' => '$'.number_format($row['dec_ValorConcepto14'] ?? 0,0,',','.'),
    'descuento_pronto_pago' => '$'.number_format($row['dec_ValorConcepto15'] ?? 0,0,',','.'),
    'intereses_mora' => '$'.number_format($row['dec_ValorConcepto16'] ?? 0,0,',','.'),
    'total_a_pagar' => '$'.number_format($row['dec_ValorConcepto20'] ?? 0,0,',','.'),
    'pago_voluntario' => '$ 0',
    'total_con_pago_voluntario' => '$'.number_format($row['dec_ValorConcepto20'] ?? 0,0,',','.'),


    // Firmas


    'declarante_nombre'      => $nombreCompleto,
    'declarante_cc'          => $row['ind_NumeroIdentificacion'],
    'contador_nombre'        => '',
    'contador_doc'           => '',
    'contador_tp'            => '',
    'revisor_nombre'         => '',
    'revisor_doc'            => '',
    'revisor_tp'             => '',
];


/* ===========================
HEADER
=========================== */

$html='

<style>
table { width:100%; border-collapse: collapse; }
td { vertical-align: top; font-size:7px; }

.tituloPrincipal { font-size:11px; font-weight:bold; }
.titulo { font-size:12px; font-weight:bold; }
.subtitulo { font-size:5px; }
.pequeno { font-size:7.5px; }

</style>

<table border="0" cellpadding="2" width="100%">

<tr>

<td width="10%" rowspan="10" align="center" >
    <img src="tcpdf/pdf/img/logopazysalvo.png" width="85"> 
</td>

<td class="tituloPrincipal" width="90%" align="center">
<b>'.$d['entidad'].'</b><br>
'.$d['secretaria'].'<br>
FORMULARIO ÚNICO NACIONAL DE DECLARACIÓN Y PAGO DEL<br>
IMPUESTO DE INDUSTRIA Y COMERCIO
</td>

</tr>

<tr><td height="9"></td></tr>
<tr><td height="9"></td></tr>
<tr><td height="9"></td></tr>

</table>

<br>

<table border="1" cellpadding="3" width="100%">

<tr bgcolor="#e1dada">

<td width="12%"><b>DEPARTAMENTO</b></td>
<td width="10%">'.$d['dep'].'</td>

<td width="8%"><b>MUNICIPIO</b></td>
<td width="10%">'.$d['mun'].'</td>

<td width="10%"><b>AÑO GRAVABLE</b></td>
<td width="5%">'.$d['anio'].'</td>

<td width="15%"><b>FECHA MÁXIMA PRESENT.</b></td>
<td width="10%">'.$d['fecha_max'].'</td>

<td width="10%"> <b>NÚMERO DE FORMULARIO</b></td>
<td width="10%">'.$d['num_form'].'</td>

</tr>

</table>

<br>

<table border="1" cellpadding="2" width="100%">

<tr>
<td width="10%">DECLARACIÓN INICIAL</td>
<td width="3%" align="center">'.($d['es_decl_inicial'] ? 'X' : '').'</td>
<td width="10%">SOLO PAGO</td>
<td width="3%" align="center">'.($d['es_solo_pago'] ? 'X' : '').'</td>
<td width="15%">DECLARACIÓN DE CORRECCIÓN</td>
<td width="3%" align="center">'.($d['es_correccion'] ? 'X' : '').'</td>
<td width="20%">No. DE DECLARACIÓN A CORREGIR</td>
<td width="10%"></td>
<td width="16%">FECHA DE PRESENTACIÓN DECLARACIÓN A CORREGIR</td>
<td width="10%"></td>
</tr>

</table>

<br>

';
$pdf->writeHTML($html, true, false, true, false, '');
$ySecA_inicio = $pdf->GetY();

$html = '
<table border="1" cellpadding="2" width="100%">

<tr>
<!-- COLUMNA IZQUIERDA (VACÍA PARA ROTACIÓN REAL) -->
<td width="5%" rowspan="5" bgcolor="#e1dada"></td>
<!-- FILA 1 -->
<td width="3%">1</td>
<td width="35%"><b>APELLIDOS Y NOMBRES DEL PROPIETARIO O RAZÓN SOCIAL</b></td>
<td width="57%">'.$d['razon'].'</td>
</tr>

<tr class="subtitulo">
<td width="3%">2</td>
<td width="5%">C.C.</td>
<td width="3%" align="center">'.($d['tipo_documento'] === 'CC' ? 'X' : '').'</td>
<td width="5%">NIT.</td>
<td width="3%" align="center">'.($d['tipo_documento'] === 'NIT' ? 'X' : '').'</td>
<td width="5%">C.E.</td>
<td width="3%" align="center">'.($d['tipo_documento'] === 'CE' ? 'X' : '').'</td>
<td width="14%">N° '.$d['nit'].' - '.$d['dv'].'</td>
<td width="20%">¿ES CONSORCIO O UNIÓN TEMPORAL?</td>
<td width="3%" align="center">'.($d['es_consorcio'] ? 'X' : '').'</td>
<td width="23%">¿REALIZA ACTIVIDADES A TRAVÉS DE PATRIMONIO AUTÓNOMO?</td>
<td width="3%" align="center">'.($d['patrimonio_autonomo'] ? 'X' : '').'</td>
</tr>

<tr>
<td width="3%">3</td>
<td width="20%"><b>DIRECCIÓN DE NOTIFICACIÓN</b></td>
<td width="72%">'.$d['direccion'].'</td>
</tr>

<tr>
<td colspan="2" width="40%">
<b>DEPARTAMENTO DE DIRECCIÓN DE NOTIFICACIÓN</b>
</td>
<td width="10%">'.$d['dep_notif'].'</td>
<td colspan="2" width="35%">
<b>MUNICIPIO O DISTRITO DE DIRECCIÓN DE NOTIFICACIÓN</b>
</td>
<td width="10%">'.$d['mun_notif'].'</td>
</tr>

<tr>
<td width="3%">4</td>
<td width="10%"><b>TELÉFONO</b></td>
<td width="10%">'.$d['telefono'].'</td>
<td width="3%">5</td>
<td width="15%"><b>CORREO ELECTRÓNICO</b></td>
<td width="15%">'.$d['correo'].'</td>
<td width="3%">6</td>
<td width="10%"><b>No. ESTABLECIMIENTOS</b></td>
<td width="2%">'.$d['num_estab'].'</td>
<td width="3%">7</td>
<td width="10%"><b>CLASIFICACIÓN</b></td>
<td width="11%">'.$d['clasificacion'].'</td>
</tr>

</table>
';
$pdf->writeHTML($html, true, false, true, false, '');
$ySecA_fin = $pdf->GetY();
$ySecB_inicio = $ySecA_fin;

$html = '
<br>

<table border="1" cellpadding="2" width="100%">

<tr bgcolor="#cae6e7">
<td width="5%" rowspan="9" bgcolor="#e1dada"></td>
<td width="3%">8</td>
<td width="72%"><b>TOTAL INGRESOS ORDINARIOS Y EXTRAORDINARIOS DEL PERIODO EN TODO EL PAÍS</b></td>
<td width="20%" align="right">'.$d['ing8'].'</td>
</tr>

<tr>
<td>9</td>
<td><b>MENOS INGRESOS FUERA DE ESTE MUNICIPIO</b></td>
<td align="right">'.$d['ing9'].'</td>
</tr>

<tr bgcolor="#cae6e7">
<td>10</td>
<td><b>TOTAL INGRESOS EN ESTE MUNICIPIO</b></td>
<td align="right">'.$d['ing10'].'</td>
</tr>

<tr>
<td>11</td>
<td><b>MENOS DEVOLUCIONES, REBAJAS, DESCUENTOS</b></td>
<td align="right">'.$d['ing11'].'</td>
</tr>

<tr>
<td>12</td>
<td><b>MENOS EXPORTACIONES</b></td>
<td align="right">'.$d['ing12'].'</td>
</tr>

<tr>
<td>13</td>
<td><b>MENOS VENTA ACTIVOS FIJOS</b></td>
<td align="right">'.$d['ing13'].'</td>
</tr>

<tr>
<td>14</td>
<td><b>MENOS ACTIVIDADES EXCLUIDAS</b></td>
<td align="right">'.$d['ing14'].'</td>
</tr>

<tr>
<td>15</td>
<td><b>MENOS ACTIVIDADES EXENTAS</b></td>
<td align="right">'.$d['ing15'].'</td>
</tr>

<tr bgcolor="#cae6e7">
<td>16</td>
<td><b>TOTAL INGRESOS GRAVABLES</b></td>
<td align="right">'.$d['ing16'].'</td>
</tr>

</table>
';
$pdf->writeHTML($html, true, false, true, false, '');
$ySecB_fin = $pdf->GetY();
$ySecC_inicio = $ySecB_fin;

$html = '
<br>

<table border="1" cellpadding="2" width="100%">

<tr bgcolor="#cae6e7">
<td width="5%" rowspan="6" bgcolor="#e1dada"></td>
<td width="35%"><b>ACTIVIDADES GRAVADAS</b></td>
<td width="10%"><b>CÓDIGO</b></td>
<td width="15%"><b>INGRESOS GRAVADOS</b></td>
<td width="15%"><b>TARIFA (por mil)</b></td>
<td width="20%"><b>VALOR IMPUESTO</b></td>
</tr>

<tr>
<td><b>ACTIVIDAD 1 (PRINCIPAL)</b></td>
<td>'.$d['codigo1'].'</td>
<td align="right">'.$d['ingresos1'].'</td>
<td>'.$d['tarifa1'].'</td>
<td align="right">'.$d['impuesto1'].'</td>
</tr>

<tr>
<td><b>ACTIVIDAD 2</b></td>
<td>'.$d['codigo2'].'</td>
<td align="right">'.$d['ingresos2'].'</td>
<td>'.$d['tarifa2'].'</td>
<td align="right">'.$d['impuesto2'].'</td>
</tr>

<tr>
<td><b>ACTIVIDAD 3</b></td>
<td>'.$d['codigo3'].'</td>
<td align="right">'.$d['ingresos3'].'</td>
<td>'.$d['tarifa3'].'</td>
<td align="right">'.$d['impuesto3'].'</td>
</tr>

<tr>
<td colspan="2" align="right"><b>TOTAL INGRESOS GRAVADOS</b></td>
<td align="right">'.$d['total_ingresos_gravados'].'</td>
<td align="right"><b>17 TOTAL IMPUESTO</b></td>
<td align="right">'.$d['total_impuesto_gravado'].'</td>
</tr>

<tr>
<td width="3%">18</td>
<td width="25%"><b>GENERACIÓN DE ENERGÍA</b></td>
<td width="22%"><b>CAPACIDAD INSTALADA '.$d['gen_energia_kw'].' KW</b></td>
<td width="25%"><b>19 IMPUESTO LEY 56 DE 1981</b></td>
<td align="right" width="20%">'.$d['imp_ley56'].'</td>

</tr>

</table>
';
$pdf->writeHTML($html, true, false, true, false, '');
$ySecC_fin = $pdf->GetY();
$ySecD_inicio = $ySecC_fin;

$html = '
<br>

<table border="1" cellpadding="2" width="100%">


<tr>
<td width="5%" rowspan="15" bgcolor="#e1dada"></td>
<td width="3%">20</td>
<td width="72%"><b>IMPUESTO DE INDUSTRIA Y COMERCIO (Renglón 17+19)</b></td>
<td width="20%" align="right">'.$d['ica'].'</td>
</tr>

<tr>
<td width="3%">21</td>
<td width="72%"><b>IMPUESTO DE AVISOS Y TABLEROS (15% del renglón 20)</b></td>
<td width="20%" align="right">'.$d['avisos'].'</td>
</tr>

<tr>
<td width="3%">22</td>
<td width="72%"><b>PAGO POR UNIDADES COMERCIALES ADICIONALES DEL SECTOR FINANCIERO</b></td>
<td width="20%" align="right">0</td>
</tr>

<tr>
<td width="3%">23</td>
<td width="72%"><b>SOBRETASA BOMBERIL (Ley 1575 de 2012) (Si la hay, liquídela según el acuerdo municipal o distrital)</b></td>
<td width="20%" align="right">'.$d['bomberil'].'</td>
</tr>

<tr>
<td width="3%">24</td>
<td width="72%"><b>SOBRETASA DE SEGURIDAD (Ley 1421 de 2011) (Si la hay, liquídela según el acuerdo municipal o distrital)</b></td>
<td width="20%" align="right">'.$d['sobretasa_seguridad'].'</td>
</tr>

<tr bgcolor="#cae6e7">
<td width="3%">25</td>
<td width="72%"><b>TOTAL IMPUESTO A CARGO (Renglón 20+21+22+23+24)</b></td>
<td width="20%" align="right">'.$d['total25'].'</td>
</tr>

<tr>
<td width="3%">26</td>
<td width="72%"><b>MENOS VALOR DE EXENCIÓN O EXONERACIÓN SOBRE EL IMPUESTO Y NO SOBRE LOS INGRESOS</b></td>
<td width="20%" align="right">'.$d['exencion'].'</td>
</tr>

<tr>
<td width="3%">27</td>
<td width="72%"><b>MENOS RETENCIONES QUE LE PRACTICARON A FAVOR DE ESTE MUNICIPIO O DISTRITO EN ESTE PERIODO</b></td>
<td width="20%" align="right">'.$d['ret27'].'</td>
</tr>

<tr>
<td width="3%">28</td>
<td width="72%"><b>MENOS AUTORRETENCIONES PRACTICADAS A FAVOR DE ESTE MUNICIPIO O DISTRITO EN ESTE PERIODO</b></td>
<td width="20%" align="right">'.$d['autoretenciones'].'</td>
</tr>

<tr>
<td width="3%">29</td>
<td width="72%"><b>MENOS ANTICIPO LIQUIDADO EN EL AÑO ANTERIOR</b></td>
<td width="20%" align="right">'.$d['anticipo_anterior'].'</td>
</tr>

<tr>
<td width="3%">30</td>
<td  width="72%"><b>ANTICIPO DEL AÑO SIGUIENTE (Si existe, liquide porcentaje según acuerdo municipal o distrital)</b></td>
<td width="20%" align="right">'.$d['anticipo_siguiente'].'</td>
</tr>

<tr>
<td width="3%">31</td>
<td width="72%"><b>SANCIONES: Extemporaneidad Corrección Inexactitud Otra ¿Cuál?</b></td>
<td width="20%" align="right">'.$d['sanciones'].'</td>
</tr>

<tr>
<td width="3%">32</td>
<td width="72%"><b>MENOS SALDO A FAVOR DEL PERIODO ANTERIOR SIN SOLICITUD DE DEVOLUCIÓN O COMPENSACIÓN</b></td>
<td width="20%" align="right">'.$d['saldo_favor_ant'].'</td>
</tr>

<tr bgcolor="#cae6e7">
<td width="3%">33</td>
<td width="72%"><b>TOTAL SALDO A CARGO (Renglón 25-26-27-28-29+30+31-32)</b></td>
<td width="20%" align="right">'.$d['saldo33'].'</td>
</tr>

<tr bgcolor="#cae6e7">
<td width="3%">34</td>
<td width="72%"><b>TOTAL SALDO A FAVOR (Renglón 25-26-27-28-29+30+31-32) (Si el resultado es menor a cero)</b></td>
<td width="20%" align="right">'.$d['total_saldo_favor'].'</td>
</tr>

</table>
';
$pdf->writeHTML($html, true, false, true, false, '');
$ySecD_fin = $pdf->GetY();
$ySecE_inicio = $ySecD_fin;

$html = '
<br>

<table border="1" cellpadding="2" width="100%">

<tr>
<td width="5%" rowspan="4" bgcolor="#e1dada"></td>
<td width="3%">35</td>
<td width="52%"><b>VALOR A PAGAR Sin Pago</b></td>
<td width="20%"><b>Sin Pago</b></td>
<td width="20%" align="right">'.$d['valor_pagar'].'</td>
</tr>

<tr>
<td width="3%">36</td>
<td width="72%"><b>DESCUENTO POR PRONTO PAGO (Si existe, liquídelo según el acuerdo municipal o distrital)</b></td>
<td width="20%" align="right">'.$d['descuento_pronto_pago'].'</td>
</tr>

<tr>
<td width="3%">37</td>
<td width="72%"><b>INTERESES DE MORA</b></td>
<td width="20%" align="right">'.$d['intereses_mora'].'</td>
</tr>

<tr bgcolor="#cae6e7">
<td width="3%">38</td>
<td width="72%"><b>TOTAL A PAGAR (Renglón 35-36+37)</b></td>
<td width="20%" align="right">'.$d['total_a_pagar'].'</td>
</tr>

</table>
';
$pdf->writeHTML($html, true, false, true, false, '');
// Aqui termina la banda "E. PAGO": la tabla de "Seccion pago voluntario"
// que sigue ya trae su propio rotulo escrito en la celda (rowspan), asi
// que la etiqueta E no debe extenderse sobre ella o se superponen.
$ySecE_fin = $pdf->GetY();

$html = '
<br>

<table border="1" cellpadding="2" width="100%">



<tr>
<td width="20%" rowspan="4" bgcolor="#e1dada"> SECCIÓN PAGO VOLUNTARIO (solamente donde exista esta opción) </td>
<td width="3%">39</td>
<td width="57%"><b>LIQUIDE EL VALOR DEL PAGO VOLUNTARIO (Según instrucciones del Municipio/Distrito)</b></td>
<td width="20%" align="right">'.$d['pago_voluntario'].'</td>
</tr>

<tr>
<td width="3%">40</td>
<td width="57%"><b>TOTAL A PAGAR CON PAGO VOLUNTARIO (Renglón 38+39)</b></td>
<td width="20%" align="right">'.$d['total_con_pago_voluntario'].'</td>
</tr>

<tr>
<td width="60%"><b>DESTINO DE MI APORTE VOLUNTARIO</b></td>
<td width="20%" align="right"></td>
</tr>

</table>
';
$pdf->writeHTML($html, true, false, true, false, '');

$html = '
<br><br>

<table border="1" cellpadding="2" width="100%">

<tr>
<td width="5%" rowspan="4" bgcolor="#e1dada"></td>
<td width="35%">
<b>FIRMA DEL DECLARANTE</b><br>
';

if ($firmaData) {
    // Sello a 30x30mm: a 65x65 la fila de firmas crecia tanto que empujaba
    // el bloque de codigo de barras fuera de la pagina (ver nota de
    // SetAutoPageBreak mas arriba).
    $html .= '<div align="center"><img src="Sello_Firma.png" width="30" height="30"><br>';
    
    // Add the name and date centered below the seal, using a smaller font
    $fechaHoraFirma = $firmaData['fd_FechaHora'] instanceof DateTime ? $firmaData['fd_FechaHora']->format('d/m/Y H:i:s') : (is_string($firmaData['fd_FechaHora']) ? $firmaData['fd_FechaHora'] : '');
    $html .= '<span style="font-size: 8px;">' . htmlspecialchars($firmaData['fd_NombreUsuario']) . '<br>' . $fechaHoraFirma . '</span></div>';
} else {
    // Only put enough space for a physical signature without breaking the page layout
    $html .= '<br><br><br>';
}

$html .= '
</td>

<td width="30%">
<b>FIRMA DEL CONTADOR</b>
</td>

<td width="30%">
<b>FIRMA DEL REVISOR FISCAL</b>
</td>
</tr>


<tr>

<td width="35%">
<b>NOMBRE:</b> '.$d['declarante_nombre'].'<br>
</td>

<td width="60%">
<b>NOMBRE:</b><br>
</td>

</tr>



<tr>

<td width="5%">C.C.</td>
<td width="3%"></td>
<td width="5%">C.E.</td>
<td width="3%"></td>
<td width="5%">No.</td>
<td width="14%">'.$d['declarante_cc'].'</td>


<td width="5%">C.C.</td>
<td width="3%"></td>
<td width="5%">C.E.</td>
<td width="3%"></td>
<td width="5%">No.</td>
<td width="14%">'.$d['contador_doc'].''.$d['contador_tp'].'</td>
<td width="5%">T.P.</td>
<td width="15%"></td>


</tr>


</table>

<br><br>

<table border="1" cellpadding="2" width="100%">

<tr>

<td width="50%">
<b>CODIGO DE BARRAS</b>
</td>

<td width="50%">
<b>REFERENCIA DE RECAUDO FORMULARIO No.</b>
</td>

</tr>


<tr>

<td width="50%">
<br><br><br><br>
</td>

<td width="50%">
<br><br><br>
</td>

</tr>


</table>


';



$pdf->writeHTML($html,true,false,true,false,'');
$ySecF_fin = $pdf->GetY();

/* ===========================
ETIQUETAS VERTICALES (A-F)
Se dibujan al final, usando el Y real (capturado con GetY() justo
antes/despues de escribir cada tabla) en vez de coordenadas fijas
adivinadas a mano. Antes las etiquetas quedaban desalineadas del
contenido real en cuanto el texto de una fila cambiaba de tamaño
(p.ej. "F. FIRMAS" aparecia por encima del bloque de firmas real).
=========================== */

$x = 13;
dibujarTextoVertical($pdf, 'A. INFORMACIÓN DEL CONTRIBUYENTE', $x, $ySecA_fin);
dibujarTextoVertical($pdf, 'B. BASE GRAVABLE', $x, $ySecB_fin);
dibujarTextoVertical($pdf, 'C. DISCR. ACTIVIDADES GRAVADAS', $x, $ySecC_fin);
dibujarTextoVertical($pdf, 'D. LIQUIDACIÓN PRIVADA', $x, $ySecD_fin);
dibujarTextoVertical($pdf, 'E. PAGO', $x, $ySecE_fin);
dibujarTextoVertical($pdf, 'F. FIRMAS', $x, $ySecF_fin);

$pdf->Output('ICA_DECLARACION.pdf','I');