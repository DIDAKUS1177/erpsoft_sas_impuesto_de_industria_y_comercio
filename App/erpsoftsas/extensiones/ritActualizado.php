<?php
require_once('tcpdf/tcpdf.php');

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.conexionSqlServer.php';

use ConexionMysqlUsuariosSqlServer\ConexionSQLServer;

class ICAPdf extends TCPDF {
    public function Header(){}
    public function Footer(){}
}

$pdf = new ICAPdf('P','mm','LETTER',true,'UTF-8',false);
$pdf->SetMargins(8,8,8);
$pdf->AddPage();

$pdf->SetFont('helvetica','',8);


/* ===========================
DATOS DE PRUEBA
=========================== */

$con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

$idEstablecimiento = $_GET['codigo']; // o como lo recibas

//$sql = "SELECT * FROM ind_establecimientos WHERE est_Id = ?";
$anioActual = date('Y');

$sql = "
SELECT 
    e.*,
    c.*,
    a.ace_Anio,
    ca.acc_Codigo,
    ca.acc_Nombre,
    a.ace_IdCodigoActividad,
    ciu.ciu_Nombre,
    ciu.ciu_Departamento
FROM ind_establecimientos e
INNER JOIN ind_contribuyentes c 
    ON c.ind_Id = e.est_IdContribuyente
LEFT JOIN conf_ciudades ciu
    ON ciu.ciu_Id = c.ind_IdCiudad
LEFT JOIN ind_actividad_establecimiento a 
    ON a.ace_IdEstablecimiento = e.est_Id
    AND a.ace_Anio = 2026
LEFT JOIN ind_actividadescomercio ca
    ON ca.acc_Id = a.ace_IdCodigoActividad
WHERE e.est_Id = ?
";

$stmt = $con->consultar($sql, [$idEstablecimiento]);

$row = null;
$actividades = [];

while($r = $con->obnerFila($stmt)){
    
    if (!$row) {
        $row = $r; // datos principales (solo una vez)
    }

    if (!empty($r['ace_IdCodigoActividad'])) {
        $actividades[] = [
                'codigo' => $r['acc_Codigo'],
                'nombre' => $r['acc_Nombre']
            ];
    }
}

$nombreCompleto = trim(
    $row['ind_PrimerNombre'].' '.
    $row['ind_SegundoNombre'].' '.
    $row['ind_PrimerApellido'].' '.
    $row['ind_SegundoApellido']
);


$d = [

'entidad' => 'MUNICIPIO DE PAIPA',
'nit_entidad' => '891801240',
'direccion_entidad' => 'Carrera 22 No 25-14',
'ciudad_entidad' => 'PAIPA - BOYACÁ',

// OPCIÓN USO
'opcion_inscripcion' => $row['est_Opcion_uso'] == 1,
'opcion_actualizacion' => $row['est_Opcion_uso'] == 2,
'opcion_cese' => $row['est_Opcion_uso'] == 3,

// IDENTIFICACIÓN
'nit' => $row['ind_NumeroIdentificacion'],
'dv' => $row['ind_DV'],

// PERSONA
'razon' => $row['ind_Persona'] == 1 
    ? $nombreCompleto 
    : $row['est_Nombre'],

'direccion' => $row['ind_Direccion'],
'municipio' => $row['ciu_Nombre'],
'departamento' => $row['ciu_Departamento'],

'telefono' => $row['ind_Telefono'],
'correo' => $row['ind_Email'],

// MATRÍCULA
'matricula' => $row['est_Matricula'],
'fecha_matricula' => !empty($row['est_Fecha_matricula']) 
    ? $row['est_Fecha_matricula']->format('d-m-Y') 
    : '',

// ACTIVIDAD
'fecha_inicio' => !empty($row['est_Fecha_inicio']) 
    ? $row['est_Fecha_inicio']->format('d-m-Y') 
    : '',

'actividades' => $actividades,

'nombre_comercial' => $row['est_Nombre'],
'direccion_actividad' => $row['est_Direccion'],

// REPRESENTANTE
'representante' => $row['est_Nombre_representante'],
'cc_representante' => $row['est_Cedula_representante'],
'email_representante' => $row['est_Email_representante'],

// REPRESENTANTE
'nombre_funcionario' => 'JUAN GABRIEL SUAREZ AVENDAÑO',
'cc_funcionario' => '10101010',


// CONTADOR
'contador_nombre' => $row['est_Nombre_contador'],
'contador_cc' => $row['est_Cedula_contador'],
'contador_tp' => $row['est_Tarjeta_profesional'],

// REVISOR
'revisor_nombre' => $row['est_Nombre_revisor'],
'revisor_cc' => $row['est_Cedula_revisor'],
'revisor_tp' => $row['est_Tarjeta_profesional_revisor'],

// CATASTRAL
'codigo_catastral' => $row['est_Codigo_catastral'],

// CESE
'fecha_cese' => !empty($row['est_Fecha_cierre']) 
    ? $row['est_Fecha_cierre']->format('d-m-Y') 
    : '',

'causal' => $row['est_Causal']

];

$tipoDoc = $row['ind_IdTipoDocumento'];
$d['doc_cc']  = ($tipoDoc == 1); 
$d['doc_nit'] = ($tipoDoc == 2);
$d['doc_ti']  = ($tipoDoc == 3);

$tipoPersona = $row['ind_Persona'];
$d['persona_natural']   = ($tipoPersona == 1);
$d['persona_juridica']  = ($tipoPersona == 2);
$d['persona_hecho']     = ($tipoPersona == 3);

$regimen = $row['ind_IdRegimen'];
$d['regimen_comun'] = ($regimen == 1);
$d['regimen_simplificado']        = ($regimen == 2);
$d['regimen_simple']     = ($regimen == 3);
$d['regimen_especial']     = ($regimen == 4);
$d['regimen_otro']     = ($regimen == 5);

$regimen = $row['ind_IdRegimen'];

if($row['ind_IdRegimen']== 1) $regimenNombre = 'Responsable de IVA';
if($row['ind_IdRegimen']== 2) $regimenNombre = 'No Responsable de IVA';
if($row['ind_IdRegimen']== 3) $regimenNombre = 'Autoretenedor';
if($row['ind_IdRegimen']== 4) $regimenNombre = 'Régimen Simple de Tributación (RST)';
if($row['ind_IdRegimen']== 5) $regimenNombre = 'Régimen Tributario Especial (RTE)';
if($row['ind_IdRegimen']== 6) $regimenNombre = 'Otro';

$actividadesHtml = '';

if (!empty($d['actividades'])) {

    foreach ($d['actividades'] as $index => $act) {

        $tipo = ($index == 0) 
            ? 'Código Actividad Principal' 
            : 'Código Actividad Secundaria';

        $actividadesHtml .= '
        <tr>
            <td width="15%">'.$tipo.'</td>
            <td width="15%">'.$act['codigo'].'</td>
            <td width="20%">Descripción</td>
            <td width="50%">'.$act['nombre'].'</td>
        </tr>';
    }

} else {

    $actividadesHtml = '
    <tr>
        <td width="100%">No registra actividades</td>
    </tr>';
}

/* ===========================
HTML
=========================== */

$html = '

<style>

table{
border-collapse: collapse;
}

td{
border:0.1px solid #000;
font-size:10px;
padding:3px;
}

.header{
border:0.1px solid #000;
font-size:10px;
font-weight:bold;
text-align:center;
}

.section{
background-color:#000;
color:#fff;
font-weight:bold;
font-size:8px;
}

.celda{
border:1px solid #000;
}

.center{
text-align:center;
}

.right{
text-align:right;
}

.titulo{
font-weight:bold;
}

</style>


<table cellpadding="4">

<tr>

<td width="20%" rowspan="4" align="center">
    <img src="tcpdf/pdf/img/logopazysalvo.png" width="80">
</td>

<td width="80%" class="header">

'.$d['entidad'].'<br>
'.$d['nit_entidad'].'<br>
'.$d['direccion_entidad'].'<br>
'.$d['ciudad_entidad'].'<br><br>

<b>REGISTRO DE INFORMACIÓN TRIBUTARIA R.I.T</b><br>
FORMATO DE INSCRIPCION O NOVEDADES DEL ESTABLECIMIENTO<br>
IMPUESTO DE INDUSTRIA, COMERCIO, AVISOS Y TABLEROS<br>
SECRETARIA DE HACIENDA

</td>

</tr>

</table>

<br>


<table>

<tr class="section">
<td colspan="6">A. OPCION DE USO (Sólo puede marcar una casilla por formulario)</td>
</tr>

<tr>
<td width="30%">1. Inscripción</td>
<td width="5%">'.($d['opcion_inscripcion']?'X':'').'</td>
<td width="25%">2. Actualización</td>
<td width="5%">'.($d['opcion_actualizacion']?'X':'').'</td>
<td width="30%">3. Cese de Actividades</td>
<td width="5%">'.($d['opcion_cese']?'X':'').'</td>
</tr>

</table>

<br>

<table>

<tr class="section">
<td colspan="6">B. DATOS DEL CONTRIBUYENTE</td>
</tr>

<tr>
<td  class="center" width="50%"><b>4. Tipo de Documento</b></td>
<td  class="center" width="50%"><b>5. Naturaleza Jurídica</b></td>
</tr>

<tr>
<td width="5%">C.C.</td>
<td width="3%">'.($d['doc_cc'] ? 'X' : '').'</td>
<td width="5%">NIT</td>
<td width="3%">'.($d['doc_nit'] ? 'X' : '').'</td>
<td width="5%">T.I</td>
<td width="3%">'.($d['doc_ti'] ? 'X' : '').'</td>
<td width="5%">No.</td>
<td width="11%">'.$d['nit'].'</td>
<td width="5%">DV</td>
<td width="5%">'.$d['dv'].'</td>

<td width="13%">Persona Natural</td>
<td width="3%">'.($d['persona_natural'] ? 'X' : '').'</td>
<td width="14%">Persona Juridica</td>
<td width="3%">'.($d['persona_juridica'] ? 'X' : '').'</td>
<td width="14%">Sociedad de Hecho</td>
<td width="3%">'.($d['persona_hecho'] ? 'X' : '').'</td>
</tr>

<tr>
<td width="25%"><b>6. Apellidos y Nombres ó Razón Social</b></td>
<td width="25%">'.$d['razon'].'</td>
<td width="20%"><b>7. Nombre Comercial</b></td>
<td width="30%">'.$d['nombre_comercial'].'</td>
</tr>

<tr>
<td width="25%"><b>8. Dirección de Notificación</b></td>
<td width="25%">'.$d['direccion'].'</td>
<td width="13%"><b>9. Municipio</b></td>
<td width="11%">'.$d['municipio'].'</td>
<td width="16%"><b>10. Departamento</b></td>
<td width="10%">'.$d['departamento'].'</td>
</tr>


<tr>
<td width="25%"><b>11. Teléfono</b></td>
<td width="25%">'.$d['telefono'].'</td>

<td width="20%"><b>12. Régimen Tributario:</b></td>
<td width="30%">

'.$regimenNombre .'

</td>

</tr>

<tr>
<td width="15%"><b>13. Contador</b></td>
<td width="10%"><b>Nombre:</b></td>
<td width="15%">'.$d['contador_nombre'].'</td>
<td width="10%"><b>Cedula:</b></td>
<td width="15%">'.$d['contador_cc'].'</td>
<td width="22%"><b>Tarjeta Profesional No:</b></td>
<td width="13%">'.$d['contador_tp'].'</td>
</tr>

<tr>
<td width="15%"><b>14. Revisor Fiscal</b></td>
<td width="10%"><b>Nombre:</b></td>
<td width="15%">'.$d['revisor_nombre'].'</td>
<td width="10%"><b>Cedula:</b></td>
<td width="15%">'.$d['revisor_cc'].'</td>
<td width="20%"><b>Tarjeta Profesional No:</b></td>
<td width="15%">'.$d['revisor_tp'].'</td>
</tr>

<tr>
<td width="40%"><b>15. Número de Matricula Mercantil del Contribuyente:</b></td>
<td width="10%"></td>
<td width="39%"><b>16. Fecha de la Matricula mercantil:</b></td>
<td width="11%"></td>

</tr>

<tr>
<td class="titulo" width="100%">18. Actividades Economicas del Contribuyente</td>
</tr>


'.$actividadesHtml.'

<tr>
<td width="15%"><b>19. Fecha inicio actividades</b></td>
<td width="35%">'.$d['fecha_inicio'].'</td>
<td width="15%"><b>20. Teléfono</b></td>
<td width="35%">'.$d['telefono'].'</td>
</tr>

<tr>
<td width="25%"><b>21. Dirección del lugar en donde se ejerce la actividad</b></td>
<td width="25%">'.$d['direccion_actividad'].'</td>
<td width="25%"><b>22. Correo Electronico</b></td>
<td width="25%">'.$d['correo'].'</td>
</tr>

</table>

<br>

<table>

<tr class="section">
<td width="100%">C. REPRESENTACIÓN LEGAL</td>
</tr>

<tr>
<td width="25%"><b>23. Apellidos y Nombres</b></td>
<td width="25%">'.$d['representante'].'</td>
<td width="25%"><b>24. Identificación</b></td>
<td width="25%">'.$d['cc_representante'].'</td>
</tr>

<tr>
<td width="25%"><b>25. Correo Electronico</b></td>
<td width="25%">'.$d['email_representante'].'</td>
<td width="25%"><b>26. Telefono</b></td>
<td width="25%">'.$d['telefono'].'</td>
</tr>


</table>

<br>

<table>

<tr class="section">
<td width="100%">D. CESE DE ACTIVIDADES</td>
</tr>

<tr>
<td width="15%"><b>27. Fecha de cese actividades:</b></td>
<td width="9%">'.$d['fecha_cese'].'</td>
<td width="10%"><b>28. Causal:</b></td>
<td width="7%">Fusión</td>
<td width="3%"></td>
<td width="8%">Escision</td>
<td width="3%"></td>
<td width="10%">Liquidación</td>
<td width="3%"></td>
<td width="5%">Otro</td>
<td width="3%"></td>
<td width="19%"><b>29. Número de Establecimiento que clausura</b></td>
<td width="5%"></td>
</tr>

</table>

<br>

<table>

<tr class="section">
<td width="100%">E. FIRMAS</td>
</tr>

<tr>
<td width="50%"><b>30. Contribuyente o Representante Legal</b></td>
<td width="50%"><b>31. Firma del Funcionario</b></td>
</tr>

<tr>
<td height="40" width="50%"></td>
<td width="50%" style="text-align:center; vertical-align:middle;">
<img src="tcpdf/pdf/img/firma_rit.png" height="40">
</td>
</tr>

<tr>
<td width="50%"><b>NOMBRE </b> '.$d['representante'].'</td>
<td width="50%"><b>NOMBRE </b> '.$d['nombre_funcionario'].' </td>
</tr>

<tr>
<td width="7%">CC</td>
<td width="3%">X</td>
<td width="7%">CE</td>
<td width="3%"></td>
<td width="7%">OTRO</td>
<td width="3%"></td>
<td width="20%">No. '.$d['cc_representante'].'</td>

<td width="7%">CC</td>
<td width="3%">X</td>
<td width="7%">CE</td>
<td width="3%"></td>
<td width="7%">OTRO</td>
<td width="3%"></td>
<td width="20%">No. '.$d['cc_funcionario'].'</td>
</tr>

</table>

<br>

<table>

<tr>
<td class="center">
<b>PRESENTE ESTE FORMULARIO EN ORIGINAL Y COPIA CON CEDULA O NIT RESPECTIVO</b>
</td>
</tr>

<tr>
<td class="center">

La inscripción o cierre al RIT está establecida en el estatuto tributario así como las sanciones por no realizarlo oportunamente conforme las normas vigentes.

</td>
</tr>

</table>

';

$pdf->writeHTML($html,true,false,true,false,'');

$pdf->Output('RIT_PAIPA.pdf','I');