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

/**
 * ¿El RIT de este contribuyente es de quien tiene la sesion abierta?
 *
 * Hace falta desde que el establecimiento dejo de ser obligatorio para
 * imprimir: sin el, no hay establecimiento cuyo dueño comprobar, y sin esta
 * comprobacion bastaria cambiar el numero de la direccion para descargar el
 * RIT de otro contribuyente -datos con reserva tributaria-.
 *
 * Mismo criterio que ControladorAnexos::puedeOperarSobreEstablecimiento: los
 * roles de Alcaldia (1 y 2) pueden ver cualquiera, y el resto solo el suyo,
 * cruzado por numero de documento porque no hay columna que ate el usuario al
 * contribuyente.
 */
function _ritEsDeLaSesion($idContribuyente, $con)
{
    if (session_status() === PHP_SESSION_NONE) { @session_start(); }

    if (empty($_SESSION['id_usuario'])) { return false; }

    $rol = isset($_SESSION['id_Rol']) ? (int) $_SESSION['id_Rol'] : 0;
    if (in_array($rol, [1, 2], true)) { return true; }

    $propio = $con->obnerFila($con->consultar(
        "SELECT c.ind_Id
           FROM ind_contribuyentes c
           INNER JOIN conf_usuarios u ON u.usu_NumeroDocumento = c.ind_NumeroIdentificacion
          WHERE u.usu_Id = ? AND c.ind_Id = ?",
        [(int) $_SESSION['id_usuario'], (int) $idContribuyente]
    ));

    return (bool) $propio;
}

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

    /*
     * Aqui se cortaba con "este contribuyente todavia no tiene
     * establecimientos registrados". Reportado el 2026-08-25: un contribuyente
     * recien inscrito no podia descargar su RIT.
     *
     * Era una herencia del diseño anterior, cuando el RIT colgaba del
     * establecimiento. Desde que el RIT es del CONTRIBUYENTE, el
     * establecimiento solo aporta datos de ubicacion del local, y no tenerlo
     * no puede impedir imprimir el registro de la persona: la inscripcion en
     * el RIT es justamente el primer tramite, antes de registrar local alguno.
     *
     * Ahora se sigue sin establecimiento y el formulario sale con esa parte
     * en blanco.
     */
}

if (!$idEstablecimiento && !$idContribuyente) {
    exit('No se indicó de qué registro generar el certificado.');
}

// Si se entro por ?codigo=, se resuelve de quien es ese establecimiento; hace
// falta mas abajo para consultar al contribuyente, que ahora es el ancla.
if ($idEstablecimiento && !$idContribuyente) {
    $fila = $con->obnerFila($con->consultar(
        "SELECT est_IdContribuyente FROM ind_establecimientos WHERE est_Id = ?",
        [$idEstablecimiento]
    ));
    $idContribuyente = $fila['est_IdContribuyente'] ?? null;

    if (!$idContribuyente) {
        exit('No existe información para el registro solicitado.');
    }
}

// El chequeo de pertenencia se hace sobre el establecimiento YA resuelto: si
// se entro por ?contribuyente=, ese establecimiento ya se filtro por
// est_IdContribuyente = ese contribuyente, asi que comprobar su dueño cubre
// los dos caminos de entrada con una sola llamada -y de paso resuelve el caso
// de parametros contradictorios (?codigo=1&contribuyente=999): si el
// establecimiento 1 no es de la sesion actual, se rechaza igual.
$permitido = $idEstablecimiento
    // Con establecimiento se comprueba su dueño, que cubre los dos caminos de
    // entrada de una vez: si se entro por ?contribuyente=, ese establecimiento
    // ya salio filtrado por el; y si los parametros se contradicen
    // (?codigo=1&contribuyente=999), el establecimiento 1 sigue sin ser de la
    // sesion y se rechaza igual.
    ? \erpsoftsas\ControladorAnexos::puedeOperarSobreEstablecimiento($idEstablecimiento, $con)
    // Sin establecimiento hay que comprobar el contribuyente directamente, o
    // cualquiera podria pedir el RIT de otro cambiando el numero en la
    // direccion. Se cruza por numero de documento, que es como este sistema
    // ata el usuario al contribuyente.
    : _ritEsDeLaSesion($idContribuyente, $con);

if (!$permitido) {
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
FROM ind_contribuyentes c
LEFT JOIN ind_establecimientos e
    ON e.est_Id = ?
LEFT JOIN conf_ciudades ciu
    ON ciu.ciu_Id = c.ind_IdCiudad
WHERE c.ind_Id = ?
";

// El ancla es el CONTRIBUYENTE y el establecimiento entra por LEFT JOIN. Antes
// era al reves y por eso un contribuyente sin locales no tenia RIT que
// imprimir. Sin establecimiento, las columnas e.* llegan en nulo y esa parte
// del formulario sale en blanco, que es lo correcto.
$row = $con->obnerFila($con->consultar($sql, [$idEstablecimiento, $idContribuyente]));

// Ver la nota de "opcion de uso" mas abajo. Se resuelven aqui, sobre el
// contribuyente, porque puede no haber establecimiento del que leerlas.
$firmaPrevia = $con->obnerFila($con->consultar(
    "SELECT TOP 1 rif_Id FROM ind_rit_firmas WHERE rif_IdContribuyente = ?",
    [$idContribuyente]
));
$ritYaFormalizado = (bool) $firmaPrevia;

$filaCese = $con->obnerFila($con->consultar(
    "SELECT TOP 1 est_Id FROM ind_establecimientos
      WHERE est_IdContribuyente = ? AND est_Fecha_cierre IS NOT NULL",
    [$idContribuyente]
));
$hayCese = (bool) $filaCese;

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
/*
 * Las actividades salen de ind_actividad_contribuyente, NO de la vieja
 * ind_actividad_establecimiento.
 *
 * Las migraciones 005 y 007 subieron las actividades del establecimiento al
 * contribuyente y les quitaron el año: el RIT es el registro VIGENTE de a que
 * se dedica la persona, y el historico por año ya vive donde corresponde, en
 * las actividades que cada declaracion congela al liquidarse.
 *
 * Este PDF se quedo leyendo la tabla vieja. Hoy las dos coinciden porque la
 * migracion copio el contenido y nadie ha editado desde entonces, pero la
 * pantalla del RIT ya solo escribe en la nueva: la primera edicion de
 * actividades habria hecho que el formulario impreso mostrara una lista
 * distinta a la que ve el contribuyente en pantalla. La tabla vieja quedo sin
 * nadie que le escriba; no se borra -misma regla que las migraciones-, pero
 * deja de leerse desde aqui.
 */
$actividades = [];
$stmtAct = $con->consultar(
    "SELECT ca.acc_Codigo, ca.acc_Nombre
       FROM ind_actividad_contribuyente a
       LEFT JOIN ind_actividadescomercio ca ON ca.acc_Id = a.atc_IdCodigoActividad
      WHERE a.atc_IdContribuyente = ?
      ORDER BY ca.acc_Codigo",
    [$row['est_IdContribuyente']]
);
while ($a = $con->obnerFila($stmtAct)) {
    if (!empty($a['acc_Codigo'])) {
        $actividades[] = ['codigo' => $a['acc_Codigo'], 'nombre' => $a['acc_Nombre']];
    }
}

/*
 * FIRMA DEL RIT (casilla 30)
 *
 * Pedido del cliente el 2026-08-19: la casilla 30 -"Contribuyente o
 * Representante Legal"- salia siempre en blanco, mientras la 31 ya traia la
 * firma del funcionario. Se estampa igual que en las declaraciones: sello,
 * nombre de quien firmo y fecha.
 *
 * firmaVigente() solo la da por buena si el hash guardado coincide con el
 * contenido de HOY. Si el contribuyente cambio algo del RIT despues de
 * firmarlo, aqui llega 'firmado' en false y el formulario vuelve a imprimirse
 * sin firma -que es lo correcto: la firma amparaba otro contenido-.
 */
// Misma guarda que declaracion.php: si el config del municipio no la trae,
// se cae al sello por defecto en vez de reventar con constante indefinida
// (en PHP 8 eso es error fatal, no aviso).
if (!defined('MUNICIPIO_SELLO_FIRMA')) define('MUNICIPIO_SELLO_FIRMA', 'Sello_Firma.png');
include_once dirname(__DIR__) . '/business/class.ritFirma.php';
$estadoFirmaRit = \erpsoftsas\RitFirma::firmaVigente($con, (int) $row['est_IdContribuyente']);
$firmaRit       = $estadoFirmaRit['firmado'] ? $estadoFirmaRit['firma'] : null;

$fechaFirmaRit = '';
if ($firmaRit) {
    $f = $firmaRit['rif_FechaHora'];
    $fechaFirmaRit = ($f instanceof \DateTime) ? $f->format('d/m/Y h:i a') : (string) $f;
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
// Encabezado pedido por el cliente el 2026-08-19, tomado del formulario
// oficial en papel. Va por constante y no en duro para que otro municipio
// pueda poner el nombre de SU dependencia sin tocar este archivo.
'dependencia' => $esc(defined('MUNICIPIO_DEPENDENCIA_TRIBUTARIA')
        ? MUNICIPIO_DEPENDENCIA_TRIBUTARIA
        : 'DIRECCIÓN DE IMPUESTOS, RENTAS Y JURISDICCIÓN COACTIVA'),
'secretaria'  => $esc(defined('MUNICIPIO_SECRETARIA')
        ? MUNICIPIO_SECRETARIA
        : 'SECRETARIA DE HACIENDA'),
'nit_entidad' => $esc(MUNICIPIO_NIT),
'direccion_entidad' => $esc(MUNICIPIO_DIRECCION),
'ciudad_entidad' => $esc(mb_strtoupper(MUNICIPIO_CIUDAD, 'UTF-8') . ' - ' . mb_strtoupper(MUNICIPIO_DEPARTAMENTO, 'UTF-8')),

// OPCIÓN USO
/*
 * Opcion de uso. Pedido el 2026-08-25: "si estan en la base de datos, solo
 * salga ACTUALIZACION; si no estan, salga INSCRIPCION".
 *
 * Antes salia de est_Opcion_uso, una casilla del ESTABLECIMIENTO que el
 * usuario elegia a mano. Tenia dos problemas: nada impedia marcar
 * "Inscripcion" en un RIT que llevaba años registrado, y desde que el
 * establecimiento es opcional (arreglo del 2026-08-25) podia no haber ninguno
 * de donde leerla.
 *
 * La regla se resuelve ahora sobre el CONTRIBUYENTE, y "estar en la base" se
 * interpreta como "este RIT ya se formalizo alguna vez", que es lo que
 * distingue una inscripcion de una novedad: la primera vez es inscripcion, de
 * ahi en adelante es actualizacion. Un registro creado pero nunca firmado
 * sigue siendo una inscripcion en tramite.
 *
 * El cese manda sobre las dos: si hay fecha de cese, el formulario es de cese
 * de actividades aunque el RIT ya estuviera formalizado.
 */
'opcion_inscripcion'   => !$ritYaFormalizado && !$hayCese,
'opcion_actualizacion' =>  $ritYaFormalizado && !$hayCese,
'opcion_cese'          =>  $hayCese,

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

'resolucion_cese' => $esc($row['est_Resolucion_cierre']),

// Punto 12: la observacion del cese se captura desde hace dias pero no se
// imprimia en ninguna parte.
'observacion_cese' => $esc((string) ($row['est_Observacion_cierre'] ?? '')),

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
    <!-- El escudo es 200x285: MUY alto en proporcion (1.43 de alto por cada
         ancho). A width=80 mide unos 40mm y se derramaba sobre las barras
         negras de "A. OPCION DE USO" y "B. DATOS DEL CONTRIBUYENTE" -TCPDF no
         reajusta la fila, la imagen simplemente se pinta encima-.

         Se salio de sitio al acortar el encabezado: antes eran 9 lineas de
         texto y ahora son 6, asi que la fila bajo ~9mm y el logo, que es de
         alto fijo, dejo de caber. Se le da ALTO explicito en vez de solo
         ancho, que es lo que de verdad hay que controlar aqui. -->
    <img src="tcpdf/pdf/img/logopazysalvo.png" width="49" height="70">
</td>

<td width="80%" class="header">

'.$d['entidad'].'<br>
'.$d['dependencia'].'<br>
'.$d['secretaria'].'<br><br>

<b>REGISTRO DE INFORMACIÓN TRIBUTARIA R.I.T</b><br>
FORMATO DE INSCRIPCION Y/O NOVEDADES DE CONTRIBUYENTES

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

<!-- Punto 6 de la revision del 2026-08-21: se quita "Nombre Comercial" porque
     no existe en el formulario oficial del RIT. La razon social pasa a ocupar el
     ancho que quedaba. El dato en la base NO se toca; solo deja de imprimirse. -->
<tr>
<td width="25%"><b>6. Apellidos y Nombres ó Razón Social</b></td>
<td width="75%">'.$d['razon'].'</td>
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
<!-- Punto 7: el cliente pidio dejarlo en "Numero de matricula mercantil". -->
<td width="40%"><b>15. Número de matrícula mercantil:</b></td>
<td width="10%">'.$d['matricula'].'</td>
<td width="39%"><b>16. Fecha de la Matricula mercantil:</b></td>
<td width="11%">'.$d['fecha_matricula'].'</td>

</tr>

<tr>
<td class="titulo" width="100%">18. Actividades Economicas del Contribuyente</td>
</tr>


'.$actividadesHtml.'

<!-- Punto 13 de la revision del 2026-08-21: "Quitar establecimientos del
     contribuyente".

     OJO, esto REVIERTE el punto 19 de la lista escrita anterior, que pedia
     justamente incluir nombre, matricula, direccion y fecha de inicio de cada
     establecimiento en el certificado. Por eso el 2026-08-20 se devolvieron al
     formulario de establecimientos las casillas de matricula y fecha de inicio.

     Esas dos casillas se DEJAN donde estan: siguen siendo datos utiles del
     local aunque ya no se impriman aqui. Si el cliente confirma que tampoco las
     quiere capturar, se retiran en otro paso.

     La consulta que arma $establecimientosHtml se conserva mas arriba; solo
     deja de pintarse. -->

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

<!-- Punto 12 de la revision del 2026-08-21: "Solamente las casillas Fecha cese
     de actividades, causal y observacion".

     Se retira la casilla 29 ("Numero de Establecimiento que clausura"): el cese
     que se declara aqui es el del CONTRIBUYENTE, y cerrar un local concreto pasa
     a manejarse desde el estado del registro del establecimiento.

     Se agrega Observacion, que estaba en la pantalla pero no se imprimia -o sea
     que el funcionario escribia algo que nadie volvia a ver-. -->
<tr>
<td width="15%"><b>27. Fecha de cese actividades:</b></td>
<td width="12%">'.$d['fecha_cese'].'</td>
<td width="10%"><b>28. Causal:</b></td>
<td width="7%">Fusión</td>
<td width="3%">'.($d['causal'] === '1' ? 'X' : '').'</td>
<td width="8%">Escision</td>
<td width="3%">'.($d['causal'] === '2' ? 'X' : '').'</td>
<td width="10%">Liquidación</td>
<td width="3%">'.($d['causal'] === '3' ? 'X' : '').'</td>
<td width="5%">Otro</td>
<td width="3%">'.($d['causal'] === '4' ? 'X' : '').'</td>
<td width="17%"></td>
</tr>

<tr>
<td width="15%"><b>29. Observación:</b></td>
<td width="85%">'.$d['observacion_cese'].'</td>
</tr>

</table>

<br>

<table>

<tr class="section">
<td width="100%">E. FIRMAS</td>
</tr>

<!-- Rotulo y firma van en la MISMA celda, no en dos filas.

     Con el rotulo en una fila y la firma en la siguiente, TCPDF dibujaba un
     trozo de borde suelto a la derecha de "31. Firma del Funcionario": esta
     tabla mezcla filas de 2 columnas con filas de 7, y en esos cortes el
     motor de tablas pinta segmentos de linea que no corresponden a ningun
     recuadro. Juntandolo en una celda desaparece el corte.

     El rotulo se alinea a la izquierda y la firma al centro con <div align>,
     que es lo que TCPDF entiende dentro de una celda.
-->
<!-- Fila de las dos firmas.

     Ojo con dos trampas de TCPDF que ya costaron una vuelta aqui:

     1. height="40" en un <td> lo IGNORA el parser HTML: la fila mide lo que
        mida su contenido. Para reservar alto hay que usar <br> (~3.1mm cada
        uno). Antes esta fila confiaba en ese height y la firma del funcionario
        -que a height=40 mide ~14mm- se salia por arriba, cruzando el borde de
        la fila del rotulo. Y como la imagen esta aplanada contra blanco (no
        puede llevar alfa, revienta en Plesk), ese blanco TAPABA la linea del
        recuadro en vez de dejarla ver: de ahi que se viera rota.

     2. Las dos celdas tienen que ocupar alto parecido o la fila queda
        descuadrada. Por eso el sello del contribuyente y la firma del
        funcionario van a una altura comparable (~10mm) y las dos celdas
        cierran con el mismo <br>.
-->
<tr>
<td width="50%">
<div align="left"><b>30. Contribuyente, Representante Legal o propietario</b></div>
<div align="center">'.
($firmaRit
    /* Mismo sello que usan las declaraciones. Sin canal alfa por lo dicho
       arriba. A 28 unidades son ~10mm, igual que la firma de al lado. */
    ? '<img src="'.MUNICIPIO_SELLO_FIRMA.'" width="28" height="28"><br>'.
      '<span style="font-size:6px;">'.$esc($firmaRit['rif_NombreUsuario']).' &nbsp;·&nbsp; '.$esc($fechaFirmaRit).'</span>'
    /* Sin firma digital queda el espacio para firmar a mano, como toda la vida. */
    : '<br><br><br>').
'</div>
</td>
<td width="50%">
<div align="left"><b>31. Firma del Funcionario</b></div>
<div align="center"><img src="tcpdf/pdf/img/firma_rit.png" width="84" height="28"><br>
<span style="font-size:6px;">&nbsp;</span></div>
</td>
</tr>

<!-- Punto 8: "Representante Legal o propietario". Una persona natural no es
     representante de si misma, es propietaria. -->
<tr>
<td width="50%"><b>NOMBRE (Representante Legal o propietario) </b> '.$d['representante'].'</td>
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

/*
 * Marca de agua "SIN FIRMAR".
 *
 * El PDF se sigue generando siempre -la Alcaldia necesita poder imprimir el
 * formulario en blanco para ventanilla-, pero si no hay firma vigente el papel
 * lo dice, en vez de parecer un RIT en regla. Mismo criterio que el
 * BORRADOR/PRESENTADA/PAGADA de la declaracion.
 *
 * Va al FINAL, justo antes de Output(), y no despues de AddPage(): llamar
 * Rotate() antes de escribir el contenido cuelga el worker de PHP-FPM al 99%
 * de CPU (documentado en CLAUDE.md, costo encontrarlo).
 */
if (!$firmaRit) {
    $familia = $pdf->getFontFamily();
    $estilo  = $pdf->getFontStyle();
    $tamano  = $pdf->getFontSizePt();

    $pdf->StartTransform();
    $pdf->SetAlpha(0.10);
    $pdf->SetTextColor(200, 0, 0);
    $pdf->SetFont('helvetica', 'B', 60);

    $cx = $pdf->getPageWidth()  / 2;
    $cy = $pdf->getPageHeight() / 2;
    $pdf->Rotate(45, $cx, $cy);

    // Dentro de un Rotate(), Cell()/SetXY() interpretan las coordenadas en el
    // espacio YA rotado; Text() con un unico punto de anclaje es lo que centra
    // de verdad (ver CLAUDE.md).
    $texto = 'SIN FIRMAR';
    $pdf->Text($cx - $pdf->GetStringWidth($texto) / 2, $cy, $texto);

    $pdf->StopTransform();
    $pdf->SetAlpha(1);
    $pdf->SetTextColor(0, 0, 0);
    // StartTransform/StopTransform NO restauran la fuente: si no se repone, la
    // de 60pt se queda pegada al resto del documento.
    $pdf->SetFont($familia, $estilo, $tamano);
}

$pdf->Output('RIT_PAIPA.pdf','I');