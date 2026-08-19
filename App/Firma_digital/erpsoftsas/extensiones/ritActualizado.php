<?php
require_once('tcpdf/tcpdf.php');

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.conexionSqlServer.php';
// Trae ControladorAnexos::puedeOperarSobreEstablecimiento(), ya blindado
// contra auto-ejecutarse al incluirse desde otro archivo (mismo patron que
// usa extensiones/anexo.php).
include_once SERVER . '/business/controller/class.anexos.php';

use ConexionMysqlUsuariosSqlServer\ConexionSQLServer;

class ICAPdf extends TCPDF {
    public function Header(){}
    public function Footer(){}
}

$con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

/* ===========================
   Sesion y permiso
   ----------------------------------------------------------------------
   Este archivo no comprobaba NADA: ni sesion, ni de quien era el
   establecimiento/contribuyente pedido. Confirmado en vivo: un curl sin
   ninguna cookie descargaba el certificado completo (NIT, direccion,
   telefono, representante legal, contador, revisor con sus cedulas) de
   CUALQUIER contribuyente, solo cambiando un entero secuencial en la URL.
   Mismo chequeo que ya usa extensiones/anexo.php para los anexos.
   =========================== */
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (empty($_SESSION['id_usuario'])) {
    http_response_code(401);
    exit('Debe iniciar sesión para descargar este certificado.');
}

// El certificado se puede pedir de dos maneras:
//   ?codigo=<est_Id>          -> como siempre, desde un establecimiento
//   ?contribuyente=<ind_Id>   -> desde el RIT, que ahora es del contribuyente
// En el segundo caso se toma su establecimiento mas antiguo como base para
// los datos de ubicacion; los datos de la PERSONA (matricula, representante,
// contador, revisor) ya no salen del establecimiento sino de
// ind_contribuyentes, que es donde los dejo la migracion 003.
//
// 'codigo' se valida como numerico ANTES de castear: pasarlo tal cual a un
// parametro ligado contra una columna int (est_Id) hacia que un valor no
// numerico reventara con una excepcion sin capturar (500 en blanco) en vez
// del mensaje claro que ya se usa para los demas casos de esta seccion.
$idEstablecimientoCrudo = $_GET['codigo'] ?? null;
if ($idEstablecimientoCrudo !== null && !ctype_digit((string) $idEstablecimientoCrudo)) {
    exit('El identificador del registro no es válido.');
}
$idEstablecimiento = $idEstablecimientoCrudo !== null ? (int) $idEstablecimientoCrudo : null;
$idContribuyente    = isset($_GET['contribuyente']) ? (int) $_GET['contribuyente'] : null;

if (!$idEstablecimiento && $idContribuyente) {
    $fila = $con->obnerFila($con->consultar(
        "SELECT TOP 1 est_Id FROM ind_establecimientos
          WHERE est_IdContribuyente = ? ORDER BY est_Id",
        [$idContribuyente]
    ));
    $idEstablecimiento = $fila['est_Id'] ?? null;

    if (!$idEstablecimiento) {
        exit('Este contribuyente todavía no tiene establecimientos registrados, '
           . 'así que no se puede generar el certificado.');
    }
}

if (!$idEstablecimiento) {
    exit('No se indicó de qué registro generar el certificado.');
}

// El chequeo de pertenencia se hace sobre el establecimiento YA resuelto: si
// se entro por ?contribuyente=, ese establecimiento ya se filtro por
// est_IdContribuyente = ese contribuyente, asi que comprobar su dueño cubre
// los dos caminos de entrada con una sola llamada -y de paso resuelve el caso
// de parametros contradictorios (?codigo=1&contribuyente=999): si el
// establecimiento 1 no es de la sesion actual, se rechaza igual.
if (!\erpsoftsas\ControladorAnexos::puedeOperarSobreEstablecimiento($idEstablecimiento, $con)) {
    http_response_code(403);
    exit('No tiene permiso para ver este registro.');
}

// Oficio 8.5 x 13 pulgadas = 215.9 x 330.2 mm. Es el mismo tamaño que ya usan
// declaracion.php y liquidacion.php; el certificado era el unico que seguia en
// carta, y por eso se veia mas apretado que los otros dos documentos.
$pdf = new ICAPdf('P','mm',array(215.9, 330.2),true,'UTF-8',false);
$pdf->SetMargins(8,8,8);
$pdf->AddPage();

$pdf->SetFont('helvetica','',8);

//$sql = "SELECT * FROM ind_establecimientos WHERE est_Id = ?";
// Mismo patron que declaracion.php: si el config del municipio no trae estos
// datos, el certificado sale con el campo vacio en vez de reventar. El NIT NO
// hereda el de Paipa a proposito: un NIT ajeno en un certificado tributario es
// peor que uno en blanco.
if (!defined('MUNICIPIO_DEPARTAMENTO')) define('MUNICIPIO_DEPARTAMENTO', '');
if (!defined('MUNICIPIO_NIT'))          define('MUNICIPIO_NIT', '');
if (!defined('MUNICIPIO_DIRECCION'))    define('MUNICIPIO_DIRECCION', '');


$sql = "
SELECT
    e.*,
    c.*,
    ciu.ciu_Nombre,
    ciu.ciu_Departamento
FROM ind_establecimientos e
INNER JOIN ind_contribuyentes c
    ON c.ind_Id = e.est_IdContribuyente
LEFT JOIN conf_ciudades ciu
    ON ciu.ciu_Id = c.ind_IdCiudad
WHERE e.est_Id = ?
";

$row = $con->obnerFila($con->consultar($sql, [$idEstablecimiento]));

if (!$row) {
    exit('No existe información para el registro solicitado.');
}

// Punto 18 dice "Actividades Economicas DEL CONTRIBUYENTE" -en plural, de
// todos sus establecimientos-, pero esta consulta estaba anclada a
// a.ace_IdEstablecimiento = e.est_Id: un contribuyente con actividades
// repartidas en mas de un establecimiento perdia en silencio las de
// cualquiera que no fuera el "ancla" resuelta arriba. Se agrega por
// est_IdContribuyente, mismo patron que ya usa _consultarRIT() en
// class.contribuyentes.php y el bloque de "Establecimientos del
// Contribuyente" mas abajo en este mismo archivo.
$actividades = [];
$stmtAct = $con->consultar(
    "SELECT ca.acc_Codigo, ca.acc_Nombre
       FROM ind_actividad_establecimiento a
       INNER JOIN ind_establecimientos e2 ON e2.est_Id = a.ace_IdEstablecimiento
       LEFT JOIN ind_actividadescomercio ca ON ca.acc_Id = a.ace_IdCodigoActividad
      WHERE e2.est_IdContribuyente = ?
        AND a.ace_Anio = (
                SELECT MAX(a2.ace_Anio)
                  FROM ind_actividad_establecimiento a2
                  INNER JOIN ind_establecimientos e3 ON e3.est_Id = a2.ace_IdEstablecimiento
                 WHERE e3.est_IdContribuyente = ?
            )
      ORDER BY ca.acc_Codigo",
    [$row['est_IdContribuyente'], $row['est_IdContribuyente']]
);
while ($a = $con->obnerFila($stmtAct)) {
    if (!empty($a['acc_Codigo'])) {
        $actividades[] = ['codigo' => $a['acc_Codigo'], 'nombre' => $a['acc_Nombre']];
    }
}

$nombreCompleto = trim(
    $row['ind_PrimerNombre'].' '.
    $row['ind_SegundoNombre'].' '.
    $row['ind_PrimerApellido'].' '.
    $row['ind_SegundoApellido']
);


// Todo campo de texto libre que pueda venir de datos guardados por un
// contribuyente (varios de estos los escribe directamente el formulario del
// RIT, sin ser administrador) se escapa aqui, UNA sola vez, antes de entrar a
// $d[]. Antes se interpolaban crudos dentro de writeHTML(): un
// ind_Nombre_representante como '</td></tr><tr><td colspan=99>HACKED'
// rompia literalmente la tabla del certificado oficial -reproducido y
// confirmado, no es un riesgo teorico-.
$esc = function ($v) {
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
};

$d = [

// Estos cuatro iban fijos en Paipa. Con varios municipios sobre el mismo
// codigo, el certificado de Guateque salia diciendo "MUNICIPIO DE PAIPA".
'entidad' => $esc('MUNICIPIO DE ' . mb_strtoupper(MUNICIPIO_CIUDAD, 'UTF-8')),
'nit_entidad' => $esc(MUNICIPIO_NIT),
'direccion_entidad' => $esc(MUNICIPIO_DIRECCION),
'ciudad_entidad' => $esc(mb_strtoupper(MUNICIPIO_CIUDAD, 'UTF-8') . ' - ' . mb_strtoupper(MUNICIPIO_DEPARTAMENTO, 'UTF-8')),

// OPCIÓN USO
'opcion_inscripcion' => $row['est_Opcion_uso'] == 1,
'opcion_actualizacion' => $row['est_Opcion_uso'] == 2,
'opcion_cese' => $row['est_Opcion_uso'] == 3,

// IDENTIFICACIÓN
'nit' => $esc($row['ind_NumeroIdentificacion']),
'dv' => $esc($row['ind_DV']),

// PERSONA
'razon' => $esc($row['ind_Persona'] == 1 ? $nombreCompleto : $row['est_Nombre']),

'direccion' => $esc($row['ind_Direccion']),
'municipio' => $esc($row['ciu_Nombre']),
'departamento' => $esc($row['ciu_Departamento']),

'telefono' => $esc($row['ind_Telefono']),
'correo' => $esc($row['ind_Email']),

// MATRÍCULA
// Punto 8: la matricula de la PERSONA vive ahora en el
// contribuyente. Se cae a la del establecimiento solo para las
// bases donde la migracion 003 todavia no dejo el dato.
'matricula' => $esc($row['ind_Matricula'] ?: $row['est_Matricula']),
'fecha_matricula' => !empty($row['est_Fecha_matricula'])
    ? $row['est_Fecha_matricula']->format('d-m-Y')
    : '',

// ACTIVIDAD
'fecha_inicio' => !empty($row['est_Fecha_inicio'])
    ? $row['est_Fecha_inicio']->format('d-m-Y')
    : '',

'actividades' => $actividades,

'nombre_comercial' => $esc($row['est_Nombre']),
'direccion_actividad' => $esc($row['est_Direccion']),

// REPRESENTANTE
'representante' => $esc($row['ind_Nombre_representante'] ?: $row['est_Nombre_representante']),
// Los tres campos de abajo solo leian la columna legacy est_*, nunca su
// equivalente ind_* -que es el que _guardarRIT() (class.contribuyentes.php)
// SI actualiza-. Cualquier correccion de cedula/correo del representante
// hecha desde el RIT nunca se veia reflejada en el certificado. Mismo
// patron ind_X ?: est_X que ya se aplicaba en 'representante'.
'cc_representante' => $esc($row['ind_Cedula_representante'] ?: $row['est_Cedula_representante']),
'email_representante' => $esc($row['ind_Email_representante'] ?: $row['est_Email_representante']),

// REPRESENTANTE
'nombre_funcionario' => $esc('JUAN GABRIEL SUAREZ AVENDAÑO'),
'cc_funcionario' => $esc('10101010'),


// CONTADOR
// Los nombres de columna son ind_NombreContador (sin guion bajo entre
// palabras), no ind_Nombre_contador: son los que ya trae produccion desde
// migracion_2026-08_contribuyente.sql BLOQUE 4 (2026-08-04). La migracion
// 003 habia creado un juego paralelo con otro nombre; se corrigio para usar
// estos (ver el propio archivo de esa migracion).
'contador_nombre' => $esc($row['ind_NombreContador'] ?: $row['est_Nombre_contador']),
'contador_cc' => $esc($row['ind_CedulaContador'] ?: $row['est_Cedula_contador']),
'contador_tp' => $esc($row['ind_TarjetaProfContador'] ?: $row['est_Tarjeta_profesional']),

// REVISOR
'revisor_nombre' => $esc($row['ind_NombreRevisor'] ?: $row['est_Nombre_revisor']),
'revisor_cc' => $esc($row['ind_CedulaRevisor'] ?: $row['est_Cedula_revisor']),
'revisor_tp' => $esc($row['ind_TarjetaProfRevisor'] ?: $row['est_Tarjeta_profesional_revisor']),

// CATASTRAL
'codigo_catastral' => $esc($row['est_Codigo_catastral']),

// CESE
// 1900-01-01 es el centinela de "nunca se lleno" de esta base: se imprime
// vacio, igual que ya se hace con las fechas de los establecimientos. Sin
// esto el certificado afirmaba que el negocio ceso actividades en 1900.
'fecha_cese' => (!empty($row['est_Fecha_cierre'])
                 && $row['est_Fecha_cierre'] instanceof \DateTime
                 && $row['est_Fecha_cierre']->format('Y-m-d') !== '1900-01-01')
    ? $row['est_Fecha_cierre']->format('d-m-Y')
    : '',

// est_Causal guarda el codigo (1 Fusion, 2 Escision, 3 Liquidacion, 4 Otro).
// Las cuatro casillas del formulario se imprimian SIEMPRE vacias: el valor se
// leia pero no se usaba para marcar ninguna.
'causal' => trim((string) $row['est_Causal']),

'resolucion_cese' => $esc($row['est_Resolucion_cierre'])

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

// Dos columnas y un encabezado, no cuatro celdas por fila.
//
// Antes cada actividad ocupaba cuatro celdas -"Código Actividad
// Principal/Secundaria" | codigo | la palabra "Descripción" | nombre-, de modo
// que los rotulos se repetian en TODAS las filas y la descripcion quedaba
// aplastada en la mitad del ancho. El cliente pidio lo evidente: una cabecera
// con "Código de Actividad" y "Descripción", y debajo las actividades.
if (!empty($d['actividades'])) {

    $actividadesHtml = '
    <tr>
        <td width="22%" align="center"><b>Código de Actividad</b></td>
        <td width="78%"><b>Descripción</b></td>
    </tr>';

    foreach ($d['actividades'] as $act) {
        $actividadesHtml .= '
        <tr>
            <td width="22%" align="center">'.$act['codigo'].'</td>
            <td width="78%">'.$act['nombre'].'</td>
        </tr>';
    }

} else {

    $actividadesHtml = '
    <tr>
        <td width="100%">No registra actividades</td>
    </tr>';
}

/* ===========================
   Punto 19: el certificado debe incluir nombre, matricula, direccion y fecha
   de inicio de LOS ESTABLECIMIENTOS -en plural-. Antes solo salia el
   establecimiento por el que se habia entrado, asi que un contribuyente con
   varios locales no tenia forma de verlos todos en un mismo documento.
   =========================== */

$establecimientosHtml = '';
$stmtEst = $con->consultar(
    "SELECT est_Nombre, est_Matricula, est_Direccion, est_Fecha_inicio, est_Activo
       FROM ind_establecimientos
      WHERE est_IdContribuyente = ?
      ORDER BY est_Id",
    [$row['est_IdContribuyente']]
);

while ($e = $con->obnerFila($stmtEst)) {

    // 1900-01-01 es el centinela de "nunca se lleno", no una fecha real.
    $fechaIni = '';
    if (!empty($e['est_Fecha_inicio']) && $e['est_Fecha_inicio'] instanceof \DateTime
        && $e['est_Fecha_inicio']->format('Y') > 1900) {
        $fechaIni = $e['est_Fecha_inicio']->format('d-m-Y');
    }

    $establecimientosHtml .= '
    <tr>
        <td width="34%">'.htmlspecialchars((string) $e['est_Nombre'], ENT_QUOTES, 'UTF-8').'</td>
        <td width="16%">'.htmlspecialchars((string) $e['est_Matricula'], ENT_QUOTES, 'UTF-8').'</td>
        <td width="34%">'.htmlspecialchars((string) $e['est_Direccion'], ENT_QUOTES, 'UTF-8').'</td>
        <td width="16%">'.$fechaIni.'</td>
    </tr>';
}

if ($establecimientosHtml === '') {
    $establecimientosHtml = '<tr><td width="100%">No registra establecimientos</td></tr>';
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
<td width="10%">'.$d['matricula'].'</td>
<td width="39%"><b>16. Fecha de la Matricula mercantil:</b></td>
<td width="11%">'.$d['fecha_matricula'].'</td>

</tr>

<tr>
<td class="titulo" width="100%">18. Actividades Economicas del Contribuyente</td>
</tr>


'.$actividadesHtml.'

<tr>
<td class="titulo" width="100%">Establecimientos del Contribuyente</td>
</tr>

<tr>
<td width="34%"><b>Nombre</b></td>
<td width="16%"><b>Matrícula</b></td>
<td width="34%"><b>Dirección</b></td>
<td width="16%"><b>Fecha inicio</b></td>
</tr>

'.$establecimientosHtml.'

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
<td width="3%">'.($d['causal'] === '1' ? 'X' : '').'</td>
<td width="8%">Escision</td>
<td width="3%">'.($d['causal'] === '2' ? 'X' : '').'</td>
<td width="10%">Liquidación</td>
<td width="3%">'.($d['causal'] === '3' ? 'X' : '').'</td>
<td width="5%">Otro</td>
<td width="3%">'.($d['causal'] === '4' ? 'X' : '').'</td>
<td width="19%"><b>29. Número de Establecimiento que clausura</b></td>
<td width="5%">'.($d['fecha_cese'] !== '' ? $esc($idEstablecimiento) : '').'</td>
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