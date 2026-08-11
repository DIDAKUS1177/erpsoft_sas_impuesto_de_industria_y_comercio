<?php
require_once('tcpdf/tcpdf.php');
require_once('tcpdf/tcpdf_barcodes_1d.php');

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.conexionSqlServer.php';

// Cargar configuración del municipio. Ubicación real (Plesk/producción): un
// nivel arriba de /erpsoftsas; fallback dentro de /erpsoftsas solo para
// Docker local (ver business/globals.php, que ya se incluyó arriba).
$configPath = dirname(dirname(__DIR__)) . '/config.municipio.php';
if (!file_exists($configPath)) {
    $configPath = dirname(__DIR__) . '/config.municipio.php';
}
if (file_exists($configPath)) {
    require_once $configPath;
}
if (!defined('MUNICIPIO_SELLO_FIRMA')) define('MUNICIPIO_SELLO_FIRMA', 'Sello_Firma.png');
// Departamento donde se declara el impuesto (encabezado del formulario).
// Fallback a Boyaca por ser el despliegue original (Paipa); cada municipio
// lo define en su config.municipio.php.
if (!defined('MUNICIPIO_DEPARTAMENTO')) define('MUNICIPIO_DEPARTAMENTO', 'Boyacá');


class ICAPdf extends TCPDF {
    public function Header(){}
    public function Footer(){}
}

/**
 * Dibuja el rotulo lateral rotado 90 grados, CENTRADO dentro de la banda
 * [$y_top, $y_bottom] de su seccion.
 *
 * MultiCell($anchoCaja, 4, $texto, 0, 'C') centra el texto DENTRO de una
 * caja de ancho fijo $anchoCaja que arranca en el pivote y crece hacia
 * $y_top -- el centro visual del texto queda entonces en
 * (pivote - $anchoCaja/2), no en el pivote mismo. Por eso el pivote debe
 * ser el centro de la banda MAS la mitad de esa caja (no la mitad del
 * texto real, que es lo que se probo primero y quedaba descuadrado).
 * $anchoCaja=40 porque es mayor que la etiqueta mas larga ("A. INFORMACIÓN
 * DEL CONTRIBUYENTE" ~34.6mm a 5pt), asi ninguna hace wrap a 2 lineas.
 */
function dibujarTextoVertical($pdf, $texto, $x, $y_top, $y_bottom) {

    $anchoCaja = 40;
    $pivote = ($y_top + $y_bottom) / 2 + $anchoCaja / 2;

    $pdf->StartTransform();
    $pdf->Rotate(90, $x, $pivote);
    $pdf->SetXY($x, $pivote);
    $pdf->SetFont('helvetica','B',5);
    $pdf->MultiCell($anchoCaja, 4, $texto, 0, 'C');
    $pdf->StopTransform();
}

/**
 * Marca de agua diagonal ("BORRADOR" / "PRESENTADA") centrada en la
 * pagina, en gris claro semitransparente. Se dibuja ANTES del contenido
 * (justo despues de AddPage) para que quede detras del texto -en TCPDF
 * el orden de dibujo es el orden de las llamadas, no hay z-index-.
 */
function dibujarMarcaDeAgua($pdf, $texto, $anchoPagina, $altoPagina) {
    // OJO: StartTransform()/StopTransform() solo restauran la matriz de
    // transformacion (rotacion) y el alpha -NO la fuente-. Sin guardar y
    // restaurar la fuente a mano, el SetFont() de aqui se queda pegado en
    // TODO el resto del documento (paso una vez: rompio el layout completo,
    // titulos y campos salieron gigantes en 70pt).
    $familiaOriginal = $pdf->getFontFamily();
    $estiloOriginal  = $pdf->getFontStyle();
    $tamanoOriginal  = $pdf->getFontSizePt();

    $cx = $anchoPagina / 2;
    $cy = $altoPagina / 2;

    $pdf->SetFont('helvetica', 'B', 70);
    $anchoTexto = $pdf->GetStringWidth($texto);

    $pdf->StartTransform();
    $pdf->SetAlpha(0.15);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->Rotate(45, $cx, $cy);
    // Text() con un solo punto de anclaje: mas simple y confiable que
    // Cell() con ancho grande centrado -con Cell() el rectangulo quedaba
    // mal ubicado tras la rotacion (probado, solo se veia una esquina).
    $pdf->Text($cx - ($anchoTexto / 2), $cy, $texto);
    $pdf->StopTransform();
    $pdf->SetAlpha(1);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont($familiaOriginal, $estiloOriginal, $tamanoOriginal);
}


$pdf = new ICAPdf('P','mm',array(215.9, 330.2),true,'UTF-8',false);
// Margen superior 5mm (no 10): el formulario completo medido terminaba en
// 331.2mm sobre una pagina de 330.2mm -es decir, 1mm FUERA del papel-, y el
// bloque del codigo de barras quedaba cortado contra el borde inferior.
// Subiendo el arranque 5mm el documento cierra en ~326mm y queda margen real
// abajo. OJO: las bandas verticales de "E. PAGO" y "F. FIRMAS" usan
// coordenadas Y fijas (ver mas abajo) y por eso se corrigieron en los mismos
// 5mm; si se vuelve a tocar este margen, hay que moverlas otra vez.
$pdf->SetMargins(10,5,10);
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

// Marca de agua: PRESENTADA si ya tiene fecha de presentacion (dec_Estado
// = 2), BORRADOR en cualquier otro estado anterior (borrador, firmada,
// pendiente de firma del contador) -tal como lo pidio el cliente, sin
// distinguir esos estados intermedios en la marca de agua-. Se dibuja
// hasta el final del archivo (ver mas abajo, justo antes de Output): se
// probo dibujarla aqui, temprano, pero el Rotate()/StartTransform() tan
// pronto en el documento colgaba el resto del render (writeHTML nunca
// terminaba, worker de PHP al 99% CPU indefinidamente). Al 0.15 de alpha
// se ve igual de bien encima del contenido que detras.
$textoMarcaAgua = ((int)($row['dec_Estado'] ?? 0) === 2) ? 'PRESENTADA' : 'BORRADOR';

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
SELECT fd_NombreUsuario, fd_EmailUsuario, fd_FechaHora
FROM firmas_declaraciones
WHERE fd_NumeroDeclaracion = ? AND fd_Rol = ?
";
$firmaData         = $con->obnerFila($con->consultar($sqlFirma, [$idDeclaracion, 'declarante']));
$firmaContadorData = $con->obnerFila($con->consultar($sqlFirma, [$idDeclaracion, 'contador']));

/*
 * Fecha que va impresa dentro del sello.
 *
 * El sello acredita la PRESENTACION de la declaracion ante el municipio,
 * no el momento en que se firmo. Por eso se imprime dec_FechaPresentacion.
 * Mientras la declaracion este firmada pero aun sin presentar todavia no
 * existe esa fecha, y se muestra la de la firma para no dejar el sello mudo.
 */
if (!function_exists('erp_formatearFechaSello')) {
function erp_formatearFechaSello($valor) {
    if ($valor instanceof DateTime) {
        return $valor->format('d/m/Y H:i:s');
    }
    if (is_string($valor) && trim($valor) !== '') {
        $ts = strtotime($valor);
        return $ts ? date('d/m/Y H:i:s', $ts) : trim($valor);
    }
    return '';
}
}

$fechaSello = erp_formatearFechaSello($row['dec_FechaPresentacion'] ?? null);

/*
 * "No. ESTABLECIMIENTOS" del formulario: el cliente confirmo que cuenta
 * solo los de Paipa. Hoy eso NO se puede filtrar -ind_establecimientos
 * tiene la columna est_Local_municipio para eso, pero la pantalla del RIT
 * nunca la captura (el campo esta comentado en core/icaWebRit.js)-. Hasta
 * que se capture ese dato se cuentan TODOS los establecimientos activos
 * del contribuyente, que es un numero seguro (nunca sub-cuenta) aunque
 * pueda quedar por encima del que exige el formulario.
 */
$numEstablecimientosContribuyente = $con->obnerFila($con->consultar(
    "SELECT COUNT(*) AS n FROM ind_establecimientos
     WHERE est_IdContribuyente = ? AND est_Activo = 1",
    [$row['dec_IdContribuyente']]
))['n'] ?? 1;

if ($fechaSello === '' && $firmaData) {
    $fechaSello = erp_formatearFechaSello($firmaData['fd_FechaHora'] ?? null);
}

/*
 * Firma del contador / revisor fiscal: es otra persona, que recibe su
 * propio codigo OTP en su correo y queda con fd_Rol = 'contador'
 * (ver microservicios/firmas/api.php). Se consulta arriba junto con la del
 * declarante; si aun no ha firmado, la casilla sale vacia.
 */

/*
 * Datos de la casilla unica de contador/revisor. Se prefiere el contador;
 * si no esta diligenciado, se usa el revisor fiscal. Cada campo cae a su
 * equivalente en ind_establecimientos para registros anteriores a la
 * migracion 2026-08, cuando estos datos vivian en el establecimiento.
 */
$valorContador = function ($claveContribuyente, $claveEstablecimiento) use ($row) {
    $v = trim((string)($row[$claveContribuyente] ?? ''));
    return $v !== '' ? $v : trim((string)($row[$claveEstablecimiento] ?? ''));
};

$nombreContador = $valorContador('ind_NombreContador', 'est_Nombre_contador');

if ($nombreContador !== '') {
    $datosContador = [
        'nombre' => $nombreContador,
        'doc'    => $valorContador('ind_CedulaContador', 'est_Cedula_contador'),
        'tp'     => $valorContador('ind_TarjetaProfContador', 'est_Tarjeta_profesional'),
    ];
} else {
    $datosContador = [
        'nombre' => $valorContador('ind_NombreRevisor', 'est_Nombre_revisor'),
        'doc'    => $valorContador('ind_CedulaRevisor', 'est_Cedula_revisor'),
        'tp'     => $valorContador('ind_TarjetaProfRevisor', 'est_Tarjeta_profesional_revisor'),
    ];
}

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
    'entidad'     => mb_strtoupper(MUNICIPIO_NOMBRE, 'UTF-8'),
    'secretaria'  => 'SECRETARÍA DE HACIENDA',
    'dir'         => ' ',
    'tel'         => ' ',
    'web'         => ' ',
    'email'       => ' ',
    // 'dep' faltaba por completo: el encabezado imprimia $d['dep'] pero
    // nadie lo definia, asi que la casilla DEPARTAMENTO salia SIEMPRE vacia
    // (y lanzaba un "Undefined array key" en cada PDF). Es el departamento
    // donde se declara -Paipa/Boyaca-, no el del contribuyente: ese va mas
    // abajo en 'dep_notif'.
    'dep'         => mb_strtoupper(MUNICIPIO_DEPARTAMENTO, 'UTF-8'),
    'mun'         => mb_strtoupper(MUNICIPIO_CIUDAD, 'UTF-8'),
    // Antes esto era el literal '2025'. El año gravable tiene que salir de
    // la declaracion, no de una constante: con el hardcode, una declaracion
    // de 2026 se imprimia como 2025 en ESTE formulario mientras que
    // liquidacion.php -que si lee dec_AnioDeclaracion- mostraba 2026. Dos
    // documentos de la misma declaracion con años distintos.
    'anio'        => (string) ($row['dec_AnioDeclaracion'] ?? date('Y')),
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
    'num_estab' => (string) $numEstablecimientosContribuyente,



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
    // Contador y revisor fiscal comparten una sola casilla en el formulario
    // (ver bloque "F. FIRMAS"): un contribuyente tiene contador O revisor,
    // no ambos firmando, asi que se toma el que este diligenciado.
    //
    // La fuente es el CONTRIBUYENTE (ind_*), no el establecimiento: la
    // declaracion es una sola por contribuyente aunque tenga varios
    // establecimientos. Se cae a los campos est_* solo por compatibilidad
    // con registros anteriores a la migracion 2026-08.
    'contador_nombre'        => $datosContador['nombre'],
    'contador_doc'           => $datosContador['doc'],
    'contador_tp'            => $datosContador['tp'],
    'revisor_nombre'         => $row['ind_NombreRevisor'] ?? ($row['est_Nombre_revisor'] ?? ''),
    'revisor_doc'            => $row['ind_CedulaRevisor'] ?? ($row['est_Cedula_revisor'] ?? ''),
    'revisor_tp'             => $row['ind_TarjetaProfRevisor'] ?? ($row['est_Tarjeta_profesional_revisor'] ?? ''),
];

/* ===========================
CODIGO DE BARRAS / REFERENCIA DE RECAUDO
Referencia = numero de declaracion. Formato provisional (Code 128): en
cuanto quede definido el convenio de recaudo con el banco, ajustar aqui
el armado de la referencia (y el tipo de codigo, si exigen otro).
=========================== */
// Se dibuja con write1DBarcode() (vectorial) DESPUES del writeHTML, no como
// <img src="@base64"> dentro del HTML: TCPDF, para una imagen embebida en
// base64, la vuelca primero a un archivo temporal en sys_get_temp_dir(), y el
// PHP-FPM de Plesk no tiene permiso de escritura ahi -> "TCPDF ERROR: Unable
// to write file". El barcode vectorial no toca el disco.
$referenciaRecaudo = (string)$d['num_form'];

/* ===========================
HEADER
=========================== */

$html='

<style>
table { width:100%; border-collapse: collapse; }
td { vertical-align: top; font-size:6px; }

.tituloPrincipal { font-size:11px; font-weight:bold; }
.titulo { font-size:12px; font-weight:bold; }
.subtitulo { font-size:5px; }
.pequeno { font-size:7.5px; }

</style>

<table border="0" cellpadding="2" width="100%">

<tr>

<td width="10%" rowspan="10" align="center" >
    <img src="' . dirname(dirname(__DIR__)) . MUNICIPIO_LOGO . '" width="85"> 
    <div style="font-size:5px; text-align:center;">NIT 891.801.240-1</div>
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
// A partir de aqui (renglones 35 en adelante: pago, seccion pago
// voluntario, firmas y codigo de barras) TODO se escribe con un UNICO
// writeHTML(), sin volver a cortar en varias llamadas.
//
// Se intento dividir tambien este tramo en llamadas separadas (para
// capturar la posicion real de "E. PAGO" y "F. FIRMAS" con GetY(), igual
// que se hizo con A-D mas arriba), pero se encontro un comportamiento de
// TCPDF por el cual, cerca del margen inferior de una pagina con
// SetAutoPageBreak(false), llamar a writeHTML() varias veces seguidas
// hace que el contenido de la ULTIMA tabla (codigo de barras) se pierda
// silenciosamente -sin ningun error ni advertencia-, sin importar cuanto
// se reduzca su contenido (se probo quitando la imagen del sello por
// completo y el problema persistia). Con una sola llamada para todo el
// tramo el contenido siempre sale completo, asi que las etiquetas E y F
// se ubican con coordenadas fijas, medidas directamente sobre el PDF ya
// renderizado (con un dec_Id real) en vez de con GetY().
// Medido sobre un render real (dec_Id=94, fuente 6px). OJO: la banda de
// "E. PAGO" es SOLO el recuadro de los renglones 35-38 (fila 35 empieza
// ~251.5mm, fila 38 termina ~268.4mm) -- la tabla de "Seccion pago
// voluntario" (39/40) que sigue ya trae su propio rotulo en la celda y
// NO debe incluirse en esta banda, o el texto rotado le pasa por encima.
// "F. FIRMAS" va desde donde empieza "FIRMA DEL DECLARANTE" (~283mm)
// hasta donde termina "CODIGO DE BARRAS" (~320.3mm, dejando ~10mm de
// margen inferior dentro de los 330.2mm de la pagina).
// Valores originales (con margen superior 10mm): 250 / 268 / 282 / 320.
// Al bajar el margen superior a 5mm todo el contenido sube 5mm, asi que
// estas bandas -que NO se calculan con GetY()- se corrieron lo mismo.
$ySecE_inicio = 245;
$ySecE_fin = 263;
$ySecF_inicio = 277;
$ySecF_fin = 327;

$html .= '
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

<br>

<table border="1" cellpadding="2" width="100%">

<tr>
<td width="5%" rowspan="4" bgcolor="#e1dada"></td>
<td width="35%">
<b>FIRMA DEL DECLARANTE</b><br>
';

if ($firmaData) {
    // Sello a 18x18mm: a 65x65 (y despues a 30x30) la fila de firmas
    // crecia tanto que empujaba el bloque de codigo de barras fuera de
    // la pagina (ver nota de SetAutoPageBreak mas arriba). Medido con
    // GetY(): a 30mm el contenido quedaba 4.5mm mas alto que la pagina.
    $html .= '<div align="center"><img src="' . MUNICIPIO_SELLO_FIRMA . '" width="16" height="16"><br>';

    // Nombre de quien firmo + fecha/hora de presentacion (ver $fechaSello).
    $html .= '<span style="font-size: 8px;">' . htmlspecialchars($firmaData['fd_NombreUsuario']) . '<br>' . $fechaSello . '</span></div>';
} else {
    // Only put enough space for a physical signature without breaking the page layout.
    // Recortado de 3 a 2 <br>: con el bloque de codigo de barras nuevo, cada mm
    // libre al fondo de la pagina cuenta (SetAutoPageBreak esta en false).
    $html .= '<br><br>';
}

$html .= '
</td>

<td width="60%">
<b>FIRMA DEL CONTADOR O REVISOR FISCAL</b><br>
';

/*
 * Antes eran dos recuadros separados ("FIRMA DEL CONTADOR" y "FIRMA DEL
 * REVISOR FISCAL"). Se unifican en uno solo porque el contribuyente tiene
 * contador O revisor fiscal -no ambos firmando a la vez- y el formulario
 * ya traia una unica fila de identidad (NOMBRE / C.C. / T.P.) compartida
 * entre las dos casillas.
 *
 * Lo que se estampa aqui es el MISMO sello del declarante (no una firma
 * manuscrita), con el nombre de quien firmo y la fecha/hora de
 * presentacion. $firmaContadorData lo llenara el flujo de OTP dirigido al
 * correo del contador/revisor.
 */
if (!empty($firmaContadorData)) {
    $html .= '<div align="center"><img src="' . MUNICIPIO_SELLO_FIRMA . '" width="16" height="16"><br>';
    $html .= '<span style="font-size: 8px;">'
           . htmlspecialchars($firmaContadorData['fd_NombreUsuario'])
           . '<br>' . $fechaSello . '</span></div>';
} else {
    $html .= '<br><br><br>';
}

$html .= '
</td>
</tr>


<tr>

<td width="35%">
<b>NOMBRE:</b> '.$d['declarante_nombre'].'<br>
</td>

<td width="60%">
<b>NOMBRE:</b> '.htmlspecialchars($d['contador_nombre']).'<br>
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
<td width="14%">'.htmlspecialchars($d['contador_doc']).'</td>
<td width="5%">T.P.</td>
<td width="15%">'.htmlspecialchars($d['contador_tp']).'</td>


</tr>


</table>
';

$pdf->writeHTML($html,true,false,true,false,'');

/* ===========================
CODIGO DE BARRAS / REFERENCIA DE RECAUDO

Este bloque se dibuja COMPLETO a mano (recuadro + rotulos + codigo), no como
tabla HTML. Dos razones:

1. El codigo no puede ir como <img src="@base64"> dentro del writeHTML: para
   una imagen embebida TCPDF la vuelca antes a un archivo temporal en
   sys_get_temp_dir(), y el PHP-FPM de Plesk no puede escribir ahi
   ("TCPDF ERROR: Unable to write file"). write1DBarcode() es vectorial y no
   toca el disco.
2. Dibujarlo despues del writeHTML tomando GetY() como referencia tampoco
   sirve: GetY() NO devuelve el borde inferior de la ultima tabla -TCPDF deja
   el cursor mas abajo, pasado el salto de bloque-, asi que el codigo
   terminaba colgando FUERA del recuadro, debajo de la tabla.

Dibujando la caja aqui se controla cada coordenada: el codigo queda centrado
horizontal y verticalmente dentro de su celda por construccion, sin depender
de cuanto mida una fila HTML ni de donde quedo el cursor.
=========================== */
$margenes  = $pdf->getMargins();
$anchoUtil = $pdf->getPageWidth() - $margenes['left'] - $margenes['right'];
$mitad     = $anchoUtil / 2;
$xBloque   = $margenes['left'];
// GetY() queda ~3mm por debajo del borde real de la tabla de firmas (TCPDF
// suma el salto de bloque al cerrar la tabla). Se compensa para que el
// recuadro quede pegado a la tabla de arriba, como en el formulario oficial,
// y para que el bloque completo cierre dentro de la pagina: sin esto medimos
// que terminaba en 332.1mm sobre un papel de 330.2mm -es decir, cortado-.
$yBloque   = $pdf->GetY() - 3;

$altoRotulo  = 4.5;   // fila de titulos
$altoCodigo  = 10.5;  // fila que contiene el codigo

// Fila de rotulos
$pdf->SetXY($xBloque, $yBloque);
$pdf->SetFont('helvetica', 'B', 7);
$pdf->Cell($mitad, $altoRotulo, 'CODIGO DE BARRAS', 1, 0, 'L');
$pdf->Cell($mitad, $altoRotulo, 'REFERENCIA DE RECAUDO FORMULARIO No.', 1, 1, 'L');

// Fila del codigo: celda izquierda vacia (la llena el barcode) y celda
// derecha con el numero de referencia.
$pdf->SetXY($xBloque, $yBloque + $altoRotulo);
$pdf->Cell($mitad, $altoCodigo, '', 1, 0, 'C');
$pdf->SetFont('helvetica', '', 7);
$pdf->Cell($mitad, $altoCodigo, $referenciaRecaudo, 1, 1, 'L');

// Codigo centrado dentro de la celda izquierda. stretch=true hace que ocupe
// exactamente el ancho pedido, asi el centrado es exacto y no aproximado.
$anchoBarcode = 50;
$altoBarcode  = 8;

$pdf->write1DBarcode(
    $referenciaRecaudo, 'C128',
    $xBloque + ($mitad - $anchoBarcode) / 2,
    $yBloque + $altoRotulo + ($altoCodigo - $altoBarcode) / 2,
    $anchoBarcode, $altoBarcode,
    '',
    array('position' => '', 'border' => false, 'padding' => 0,
          'fgcolor' => array(0,0,0), 'bgcolor' => false,
          'text' => false, 'stretch' => true),
    'N'
);

/* ===========================
ETIQUETAS VERTICALES (A-F)
Se dibujan al final, usando el Y real (capturado con GetY() justo
antes/despues de escribir cada tabla) en vez de coordenadas fijas
adivinadas a mano. Antes las etiquetas quedaban desalineadas del
contenido real en cuanto el texto de una fila cambiaba de tamaño
(p.ej. "F. FIRMAS" aparecia por encima del bloque de firmas real).
=========================== */

$x = 13;
dibujarTextoVertical($pdf, 'A. INFORMACIÓN DEL CONTRIBUYENTE', $x, $ySecA_inicio, $ySecA_fin);
dibujarTextoVertical($pdf, 'B. BASE GRAVABLE', $x, $ySecB_inicio, $ySecB_fin);
dibujarTextoVertical($pdf, "C. DISCR.\nACTIVIDADES GRAVADAS", $x - 2, $ySecC_inicio, $ySecC_fin);
dibujarTextoVertical($pdf, 'D. LIQUIDACIÓN PRIVADA', $x, $ySecD_inicio, $ySecD_fin);
dibujarTextoVertical($pdf, 'E. PAGO', $x, $ySecE_inicio, $ySecE_fin);
dibujarTextoVertical($pdf, 'F. FIRMAS', $x, $ySecF_inicio, $ySecF_fin);

dibujarMarcaDeAgua($pdf, $textoMarcaAgua, 215.9, 330.2);

$pdf->Output('ICA_DECLARACION.pdf','I');