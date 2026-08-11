<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.conexionSqlServer.php';
require_once('./tcpdf/tcpdf.php');

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


class LiquidacionICAComercioPdf extends TCPDF {
    public function Header() {}
    public function Footer() {}
}

/**
 * Marca de agua diagonal ("BORRADOR" / "PRESENTADA"), misma logica que
 * extensiones/declaracion.php.
 */
function dibujarMarcaDeAgua($pdf, $texto, $anchoPagina, $altoPagina) {
    // Ver nota en declaracion.php: StartTransform()/StopTransform() no
    // restauran la fuente, hay que guardarla y ponerla de vuelta a mano.
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

/* ============================================================
   CONEXION Y DATOS REALES (mismo patron que declaracion.php)
   ============================================================ */
$con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
$idDeclaracion = $_GET['dec_Id'] ?? 0;

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
while ($a = $con->obnerFila($stmtAct)) {
    $actividades[] = $a;
}

$sqlFirma = "
SELECT fd_NombreUsuario, fd_EmailUsuario, fd_FechaHora, fu.fu_Base64
FROM firmas_declaraciones fd
LEFT JOIN firmas_usuario fu ON fu.fu_IdUsuario = fd.fd_IdUsuario
WHERE fd.fd_NumeroDeclaracion = ?
";
$stmtFirma = $con->consultar($sqlFirma, [$idDeclaracion]);
$firmaData = $con->obnerFila($stmtFirma);

/*
 * Fecha impresa dentro del sello: acredita la PRESENTACION ante el
 * municipio, no el momento de la firma. Si aun no se ha presentado se
 * cae a la fecha de firma para no dejar el sello sin fecha.
 * (Mismo criterio que extensiones/declaracion.php.)
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

if ($fechaSello === '' && $firmaData) {
    $fechaSello = erp_formatearFechaSello($firmaData['fd_FechaHora'] ?? null);
}

$nombreCompleto = trim(
    $row['ind_PrimerNombre'].' '.
    $row['ind_SegundoNombre'].' '.
    $row['ind_PrimerApellido'].' '.
    $row['ind_SegundoApellido']
);

$tipoDocumento = 'CC';
if ($row['ind_IdTipoDocumento'] == 2) $tipoDocumento = 'NIT';
if ($row['ind_IdTipoDocumento'] == 3) $tipoDocumento = 'CE';

$totalImpuestoActividades = 0;
foreach ($actividades as $act) {
    $totalImpuestoActividades += $act['dia_ValorImpuesto'];
}

/* ============================================================
   VARIABLES – ENCABEZADO / GENERALES
   ============================================================ */
// mb_strtoupper y NO strtoupper: strtoupper() trabaja byte a byte y en UTF-8
// deja las vocales acentuadas intactas -"Boyacá" salia "BOYACá"-. Con el
// despliegue multi-municipio importa aun mas: Bogotá, Nariño, Chocó, Córdoba,
// Atlántico, Bolívar, Caquetá, Quindío... casi todos llevan tilde.
$municipio          = mb_strtoupper(MUNICIPIO_CIUDAD, 'UTF-8');
$departamento       = mb_strtoupper(MUNICIPIO_DEPARTAMENTO, 'UTF-8');
$nit_municipio      = "891801240";
$direccion_mpio     = "Carrera 22 No 25-14";
$ciudad_mpio        = mb_strtoupper(MUNICIPIO_CIUDAD, 'UTF-8') . " - " . mb_strtoupper(MUNICIPIO_DEPARTAMENTO, 'UTF-8');

$fecha_max_presentacion = "";
$anio_gravable      = $row['dec_AnioDeclaracion'] ?? date('Y');
$solo_bogota        = "SI"; // solo para texto, no checkbox real

$periodo_texto      = "SAMANENTE PARA BOGOTA, marque el bimestre o periodo anual";

// checkboxes meses/bimestres (true/false). Todo el sistema solo maneja
// declaraciones anuales (ver dec_MesDeclaracion fijo en 12 al crear la
// declaracion), asi que "anual" siempre es la opcion real.
$chk_ene_feb  = false;
$chk_mar_abr  = false;
$chk_may_jun  = false;
$chk_jul_ago  = false;
$chk_sep_oct  = false;
$chk_nov_dic  = false;
$chk_anual    = true;

// Opción de uso (igual que declaracion.php: el sistema no maneja
// correcciones todavia, siempre es declaracion inicial)
$chk_declaracion_inicial = true;
$chk_solo_pago           = false;
$chk_correccion          = false;
$no_declaracion_corrige  = "";
$fecha_declaracion       = $row['dec_FechaDeclaracion'] instanceof DateTime
    ? $row['dec_FechaDeclaracion']->format('d/m/Y')
    : (string)($row['dec_FechaDeclaracion'] ?? '');

/* ============================================================
   VARIABLES – DATOS CONTRIBUYENTE (BLOQUE 1–6)
   ============================================================ */
$nombre_razon_social = $row['ind_Persona'] == 1 ? $nombreCompleto : $row['est_Nombre'];

$tipo_doc_CC  = $tipoDocumento === 'CC';
$tipo_doc_NIT = $tipoDocumento === 'NIT';
$tipo_doc_XT  = false;
$tipo_doc_TI  = false;
$tipo_doc_CE  = $tipoDocumento === 'CE';
$tipo_doc_No  = false;

$numero_documento = $row['ind_NumeroIdentificacion'] ?? '';
$digito_verif     = $row['ind_DV'] ?? '';

$es_consorcio_un_tv  = false;
$realiza_act_traves_patrimonio = false;

$direccion_notificacion = $row['ind_Direccion'] ?? '';
$municipio_contrib      = $row['ciu_Nombre'] ?? '';
$departamento_contrib   = $row['ciu_Departamento'] ?? '';

$telefono_contrib   = $row['ind_Telefono'] ?? '';
$correo_contrib     = $row['ind_Email'] ?? '';

// Mismo criterio que extensiones/declaracion.php: cuenta todos los
// establecimientos activos del contribuyente (aun no se captura cual esta
// en Paipa, ver la nota alla).
$no_establecimientos = (string) ($con->obnerFila($con->consultar(
    "SELECT COUNT(*) AS n FROM ind_establecimientos
     WHERE est_IdContribuyente = ? AND est_Activo = 1",
    [$row['dec_IdContribuyente']]
))['n'] ?? 1);

$clasificacion       = "";

/* ============================================================
   VARIABLES – BASE GRAVABLE / LIQ. IMPUESTO
   Mapeadas 1:1 a las mismas columnas que usa declaracion.php, para
   que ambos documentos de una misma declaracion siempre coincidan.
   ============================================================ */
// Base gravable – ingresos (renglones 8–16)
$vlr_8_total_ingresos_pais         = (float)($row['dec_TotalIngresos'] ?? 0);
$vlr_9_menos_fuera_municipio       = (float)($row['dec_IngresosFueraMunicipio'] ?? 0);
$vlr_10_total_ingresos_municipio   = $vlr_8_total_ingresos_pais - $vlr_9_menos_fuera_municipio;
$vlr_11_menos_devoluciones         = (float)($row['dec_IngresosDevoluciones'] ?? 0);
$vlr_12_menos_exportaciones        = (float)($row['dec_IngresosExportaciones'] ?? 0);
$vlr_13_menos_venta_activos        = (float)($row['dec_IngresosVentas'] ?? 0);
$vlr_14_menos_excluidos_no_grav    = (float)($row['dec_IngresosActividades'] ?? 0);
$vlr_15_menos_otras_actividades    = (float)($row['dec_IngresosOtrasActividades'] ?? 0);
$vlr_16_total_ingresos_gravables   = (float)($row['dec_BaseGravable'] ?? 0);

// Actividad gravada (principal)
$actividad_codigo       = $actividades[0]['acc_Codigo'] ?? '';
$actividad_descripcion  = $actividades[0]['acc_Nombre'] ?? 'ACTIVIDADES GRAVADAS';
$actividad_ingresos     = (float)($actividades[0]['dia_BaseGravable'] ?? 0);
$actividad_tarifa_mil   = (float)($actividades[0]['dia_Tarifa'] ?? 0);
$actividad_impuesto     = (float)($actividades[0]['dia_ValorImpuesto'] ?? 0);
$total_impuesto_renglon = (float)$totalImpuestoActividades;

// Otros campos liquidación (renglon 20 en adelante)
$vlr_20_total_impto_ic       = (float)($row['dec_ValorConcepto1'] ?? 0);
$vlr_21_impto_avisos_tableros= (float)($row['dec_ValorConcepto2'] ?? 0);
$vlr_22_pago_unidades_adic   = 0;
$vlr_23_sobretasa_bomberos   = (float)($row['dec_ValorConcepto3'] ?? 0);
$vlr_24_sobretasa_seguridad  = 0;
$vlr_25_total_impto_cargo    = (float)($row['dec_ValorConcepto4'] ?? 0);
$vlr_26_menos_valores_exencion = (float)($row['dec_ValorConcepto5'] ?? 0);
$vlr_27_menos_retenciones     = (float)($row['dec_ValorConcepto6'] ?? 0);
// Nota: esta plantilla no tiene renglon propio para "autorretenciones"
// (dec_ValorConcepto7), a diferencia de declaracion.php que si lo
// muestra en su renglon 28. Los TOTALES (33, 38, etc.) igual son
// correctos porque vienen de su propia columna precalculada, no de la
// suma de los renglones mostrados aqui.
$vlr_28_menos_anticipo_anterior= (float)($row['dec_ValorConcepto8'] ?? 0);
$vlr_29_anticipo_anio_sgte    = (float)($row['dec_ValorConcepto9'] ?? 0);
$vlr_31_sanciones             = (float)($row['dec_ValorConcepto10'] ?? 0);
$vlr_32_menos_saldo_favor_ant = (float)($row['dec_ValorConcepto11'] ?? 0);
$vlr_33_total_saldo_cargo     = (float)($row['dec_ValorConcepto12'] ?? 0);
$vlr_35_valor_a_pagar         = (float)($row['dec_ValorConcepto14'] ?? 0);
$vlr_37_intereses_mora        = (float)($row['dec_ValorConcepto16'] ?? 0);
$vlr_38_total_a_pagar         = (float)($row['dec_ValorConcepto20'] ?? 0);

// Pago voluntario (el sistema no maneja aporte voluntario todavia)
$vlr_39_aporte_voluntario = 0;
$vlr_40_total_con_aporte  = (float)($row['dec_ValorConcepto20'] ?? 0);

/* ============================================================
   VARIABLES – FIRMAS
   ============================================================ */
$firmante_nombre      = $nombreCompleto;
$firmante_tipo_doc_CC = $tipoDocumento === 'CC';
$firmante_tipo_doc_CE = $tipoDocumento === 'CE';
$firmante_tipo_doc_TI = false;
$firmante_tipo_doc_NIT= $tipoDocumento === 'NIT';
$firmante_num_doc     = $row['ind_NumeroIdentificacion'] ?? '';

$contador_nombre      = "";
$contador_tipo_doc_CC = false;
$contador_tipo_doc_CE = false;
$contador_tipo_doc_TI = false;
$contador_num_doc     = "";
$contador_tp          = "";

$revisor_nombre      = "";
$revisor_tipo_doc_CC = false;
$revisor_tipo_doc_CE = false;
$revisor_tipo_doc_TI = false;
$revisor_num_doc     = "";
$revisor_tp          = "";

$codigo_barras       = "";
$referencia_recaudo  = $row['dec_NumeroDeclaracion'] ?? '';

/* ============================================================
   FUNCIONES AUXILIARES
   ============================================================ */
function moneyCol($v) {
    return '$' . number_format((float)$v, 2, ',', '.');
}

/* ============================================================
   CODIGO DE BARRAS / REFERENCIA DE RECAUDO
   Referencia = numero de declaracion. Formato provisional (Code 128): en
   cuanto quede definido el convenio de recaudo con el banco, ajustar aqui
   el armado de la referencia (y el tipo de codigo, si exigen otro).

   Se dibuja con write1DBarcode() despues del writeHTML (ver mas abajo), no
   como imagen embebida: el vectorial no necesita escribir en disco.
   ============================================================ */

/* ============================================================
   CREACIÓN PDF
   Reescrito con tablas HTML (writeHTML), igual que declaracion.php, en vez
   de Cell()/Rect() posicionados a mano: mas facil de mantener y evita que
   una fila se salga de la pagina sin darse cuenta (con SetAutoPageBreak en
   false, TCPDF no avisa si el contenido se pasa del borde inferior).
   ============================================================ */
$pdf = new LiquidacionICAComercioPdf('P', 'mm', array(215.9, 330.2), true, 'UTF-8', false);
$pdf->SetMargins(10,10,10);
$pdf->SetAutoPageBreak(false, 0);
$pdf->AddPage();
$pdf->SetFont('helvetica','',7);

// Se dibuja al final del archivo, justo antes de Output: ver nota larga
// en declaracion.php -Rotate()/StartTransform() tan temprano en el
// documento colgaba el resto del render en pruebas-.
$textoMarcaAgua = ((int)($row['dec_Estado'] ?? 0) === 2) ? 'PRESENTADA' : 'BORRADOR';

$nombreFirmanteContadorRevisor = $contador_nombre !== '' ? $contador_nombre : $revisor_nombre;
$docFirmanteContadorRevisor    = $contador_num_doc !== '' ? $contador_num_doc : $revisor_num_doc;

$html = '

<style>
table { width:100%; border-collapse: collapse; }
td { vertical-align: top; font-size:6px; }
.tituloPrincipal { font-size:11px; font-weight:bold; }
</style>

<table border="0" cellpadding="2" width="100%">

<tr>

<td width="10%" rowspan="10" align="center">
    <img src="' . dirname(dirname(__DIR__)) . MUNICIPIO_LOGO . '" width="85">
    <div style="font-size:5px; text-align:center;">NIT ' . $nit_municipio . '</div>
</td>

<td class="tituloPrincipal" width="90%" align="center">
<b>MUNICIPIO DE ' . htmlspecialchars($municipio) . '</b><br>
' . $direccion_mpio . ' - ' . $ciudad_mpio . '<br>
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

<!-- Este encabezado identifica DONDE se declara el impuesto (el municipio que
     recauda), no donde vive el contribuyente. Antes usaba las variables del
     contribuyente, asi que una declaracion de alguien residente en Tunja salia
     diciendo "MUNICIPIO O DISTRITO: Tunja" siendo una declaracion ante Paipa.
     La direccion del contribuyente tiene su propia fila mas abajo. -->
<tr bgcolor="#e1dada">
<td width="14%"><b>MUNICIPIO O DISTRITO</b></td>
<td width="12%">' . htmlspecialchars($municipio) . '</td>
<td width="14%"><b>DEPARTAMENTO</b></td>
<td width="12%">' . htmlspecialchars($departamento) . '</td>
<td width="14%"><b>AÑO GRAVABLE</b></td>
<td width="8%">' . $anio_gravable . '</td>
<td width="16%"><b>FECHA MÁXIMA PRESENT.</b></td>
<td width="10%">' . $fecha_max_presentacion . '</td>
</tr>

</table>

<br>

<table border="1" cellpadding="2" width="100%">

<tr>
<td width="44%">SOLAMENTE PARA BOGOTÁ, marque el bimestre o periodo anual</td>
<td width="8%" align="center">ene-feb<br>' . ($chk_ene_feb ? 'X' : '') . '</td>
<td width="8%" align="center">mar-abr<br>' . ($chk_mar_abr ? 'X' : '') . '</td>
<td width="8%" align="center">may-jun<br>' . ($chk_may_jun ? 'X' : '') . '</td>
<td width="8%" align="center">jul-ago<br>' . ($chk_jul_ago ? 'X' : '') . '</td>
<td width="8%" align="center">sep-oct<br>' . ($chk_sep_oct ? 'X' : '') . '</td>
<td width="8%" align="center">nov-dic<br>' . ($chk_nov_dic ? 'X' : '') . '</td>
<td width="8%" align="center">anual<br>' . ($chk_anual ? 'X' : '') . '</td>
</tr>

</table>

<br>

<table border="1" cellpadding="2" width="100%">

<tr>
<td width="14%">DECLARACIÓN INICIAL</td>
<td width="3%" align="center">' . ($chk_declaracion_inicial ? 'X' : '') . '</td>
<td width="10%">SOLO PAGO</td>
<td width="3%" align="center">' . ($chk_solo_pago ? 'X' : '') . '</td>
<td width="10%">CORRECCIÓN</td>
<td width="3%" align="center">' . ($chk_correccion ? 'X' : '') . '</td>
<td width="18%">Declaración que corrige No.</td>
<td width="10%">' . $no_declaracion_corrige . '</td>
<td width="15%">Fecha</td>
<td width="14%">' . $fecha_declaracion . '</td>
</tr>

</table>

<br>

<table border="1" cellpadding="2" width="100%">

<tr>
<td width="3%">1</td>
<td width="30%"><b>NOMBRES Y APELLIDOS O RAZÓN SOCIAL</b></td>
<td width="67%">' . htmlspecialchars($nombre_razon_social) . '</td>
</tr>

<tr>
<td width="3%">2</td>
<td width="4%">CC</td><td width="3%" align="center">' . ($tipo_doc_CC ? 'X' : '') . '</td>
<td width="5%">NIT</td><td width="3%" align="center">' . ($tipo_doc_NIT ? 'X' : '') . '</td>
<td width="4%">XT</td><td width="3%" align="center">' . ($tipo_doc_XT ? 'X' : '') . '</td>
<td width="4%">TI</td><td width="3%" align="center">' . ($tipo_doc_TI ? 'X' : '') . '</td>
<td width="4%">CE</td><td width="3%" align="center">' . ($tipo_doc_CE ? 'X' : '') . '</td>
<td width="16%">No. ' . htmlspecialchars($numero_documento) . '  DV ' . htmlspecialchars($digito_verif) . '</td>
<td width="17%">¿Consorcio o unión temporal?</td><td width="3%" align="center">' . ($es_consorcio_un_tv ? 'X' : '') . '</td>
<td width="18%">¿Patrimonio autónomo?</td><td width="3%" align="center">' . ($realiza_act_traves_patrimonio ? 'X' : '') . '</td>
</tr>

<tr>
<td width="3%">3</td>
<td width="20%"><b>DIRECCIÓN DE NOTIFICACIÓN</b></td>
<td width="77%">' . htmlspecialchars($direccion_notificacion) . '</td>
</tr>

<tr>
<td width="3%">4</td>
<td width="27%"><b>MUNICIPIO O DISTRITO DE LA DIRECCIÓN</b></td>
<td width="15%">' . htmlspecialchars($municipio_contrib) . '</td>
<td width="14%"><b>DEPARTAMENTO</b></td>
<td width="10%">' . htmlspecialchars($departamento_contrib) . '</td>
<td width="16%"><b>No. ESTABLECIMIENTOS</b></td>
<td width="5%">' . $no_establecimientos . '</td>
<td width="5%"><b>CLASIF.</b></td>
<td width="5%">' . $clasificacion . '</td>
</tr>

<tr>
<td width="3%">5</td>
<td width="15%"><b>TELÉFONO</b></td>
<td width="15%">' . htmlspecialchars($telefono_contrib) . '</td>
<td width="17%"><b>CORREO ELECTRÓNICO</b></td>
<td width="50%">' . htmlspecialchars($correo_contrib) . '</td>
</tr>

</table>

<br>

<table border="1" cellpadding="2" width="100%">

<tr bgcolor="#e1dada">
<td width="5%"></td>
<td width="75%"><b>BASE GRAVABLE</b></td>
<td width="20%" align="center"><b>VALOR</b></td>
</tr>

<tr><td>8</td><td>TOTAL INGRESOS ORDINARIOS Y EXTRAORDINARIOS DEL PERÍODO EN TODO EL PAÍS</td><td align="right">' . moneyCol($vlr_8_total_ingresos_pais) . '</td></tr>
<tr><td>9</td><td>MENOS INGRESOS FUERA DE ESTE MUNICIPIO O DISTRITO</td><td align="right">' . moneyCol($vlr_9_menos_fuera_municipio) . '</td></tr>
<tr><td>10</td><td>TOTAL INGRESOS EN ESTE MUNICIPIO (renglón 8 menos 9)</td><td align="right">' . moneyCol($vlr_10_total_ingresos_municipio) . '</td></tr>
<tr><td>11</td><td>MENOS DEVOLUCIONES, REBAJAS, DESCUENTOS</td><td align="right">' . moneyCol($vlr_11_menos_devoluciones) . '</td></tr>
<tr><td>12</td><td>MENOS EXPORTACIONES</td><td align="right">' . moneyCol($vlr_12_menos_exportaciones) . '</td></tr>
<tr><td>13</td><td>MENOS VENTA DE ACTIVOS FIJOS</td><td align="right">' . moneyCol($vlr_13_menos_venta_activos) . '</td></tr>
<tr><td>14</td><td>MENOS ACTIVIDADES EXCLUIDAS O NO SUJETAS Y OTROS INGRESOS NO GRAVADOS</td><td align="right">' . moneyCol($vlr_14_menos_excluidos_no_grav) . '</td></tr>
<tr><td>15</td><td>MENOS OTRAS ACTIVIDADES EXENTAS EN ESTE MUNICIPIO O DISTRITO (POR ACUERDO)</td><td align="right">' . moneyCol($vlr_15_menos_otras_actividades) . '</td></tr>
<tr bgcolor="#cae6e7"><td>16</td><td><b>TOTAL INGRESOS GRAVABLES (renglón 10 menos 11,12,13,14,15)</b></td><td align="right"><b>' . moneyCol($vlr_16_total_ingresos_gravables) . '</b></td></tr>

</table>

<br>

<table border="1" cellpadding="2" width="100%">

<tr bgcolor="#e1dada">
<td width="30%" align="center"><b>ACTIVIDADES GRAVADAS</b></td>
<td width="12%" align="center"><b>CÓDIGO</b></td>
<td width="25%" align="center"><b>INGRESOS GRAVADOS</b></td>
<td width="15%" align="center"><b>TARIFA (por mil)</b></td>
<td width="18%" align="center"><b>IMPUESTO</b></td>
</tr>

<tr>
<td>' . htmlspecialchars($actividad_descripcion) . '</td>
<td align="center">' . htmlspecialchars($actividad_codigo) . '</td>
<td align="right">' . moneyCol($actividad_ingresos) . '</td>
<td align="center">' . number_format($actividad_tarifa_mil,3,',','.') . ' ‰</td>
<td align="right">' . moneyCol($actividad_impuesto) . '</td>
</tr>

<tr bgcolor="#cae6e7">
<td colspan="2"><b>17. TOTAL INGRESOS GRAVADOS</b></td>
<td align="right"><b>' . moneyCol($actividad_ingresos) . '</b></td>
<td><b>17. TOTAL IMPUESTO</b></td>
<td align="right"><b>' . moneyCol($total_impuesto_renglon) . '</b></td>
</tr>

</table>

<br>

<table border="1" cellpadding="2" width="100%">

<tr><td width="5%"></td><td width="75%">20 TOTAL IMPUESTO DE INDUSTRIA Y COMERCIO (renglón 17+19)</td><td width="20%" align="right">' . moneyCol($vlr_20_total_impto_ic) . '</td></tr>
<tr><td></td><td>21 IMPUESTO DE AVISOS Y TABLEROS (15% de renglón 20)</td><td align="right">' . moneyCol($vlr_21_impto_avisos_tableros) . '</td></tr>
<tr><td></td><td>22 PAGO POR UNIDADES COMERCIALES ADICIONALES DEL SECTOR FINANCIERO</td><td align="right">' . moneyCol($vlr_22_pago_unidades_adic) . '</td></tr>
<tr><td></td><td>23 SOBRETASA BOMBERIL (Ley 1575 de 2012), según el acuerdo municipal o distrital</td><td align="right">' . moneyCol($vlr_23_sobretasa_bomberos) . '</td></tr>
<tr><td></td><td>24 SOBRETASA DE SEGURIDAD (Ley 1421 de 2010), según el acuerdo municipal o distrital</td><td align="right">' . moneyCol($vlr_24_sobretasa_seguridad) . '</td></tr>
<tr bgcolor="#cae6e7"><td></td><td><b>25 TOTAL IMPUESTO A CARGO (Renglón 20+21+22+23+24)</b></td><td align="right"><b>' . moneyCol($vlr_25_total_impto_cargo) . '</b></td></tr>
<tr><td></td><td>26 MENOS VALOR DE EXENCIÓN O EXONERACIÓN SOBRE EL IMPUESTO Y NO SOBRE LOS INGRESOS</td><td align="right">' . moneyCol($vlr_26_menos_valores_exencion) . '</td></tr>
<tr><td></td><td>27 MENOS RETENCIONES QUE LE PRACTICARON A FAVOR DE ESTE MUNICIPIO O DISTRITO EN ESTE PERÍODO</td><td align="right">' . moneyCol($vlr_27_menos_retenciones) . '</td></tr>
<tr><td></td><td>28 MENOS ANTICIPO LIQUIDADO EN EL AÑO ANTERIOR</td><td align="right">' . moneyCol($vlr_28_menos_anticipo_anterior) . '</td></tr>
<tr><td></td><td>29 ANTICIPO DEL AÑO SIGUIENTE, según el acuerdo municipal o distrital</td><td align="right">' . moneyCol($vlr_29_anticipo_anio_sgte) . '</td></tr>
<tr><td>31</td><td>SANCIONES: Extemporaneidad&nbsp;&nbsp;&nbsp;Corrección&nbsp;&nbsp;&nbsp;Inexactitud&nbsp;&nbsp;&nbsp;Otra ¿Cuál?</td><td align="right">' . moneyCol($vlr_31_sanciones) . '</td></tr>
<tr><td>32</td><td>MENOS SALDO A FAVOR DEL PERÍODO ANTERIOR SIN SOLICITUD DE DEVOLUCIÓN O COMPENSACIÓN</td><td align="right">' . moneyCol($vlr_32_menos_saldo_favor_ant) . '</td></tr>
<tr bgcolor="#cae6e7"><td>33</td><td><b>TOTAL SALDO A CARGO (Renglón 25-26-27-28-29+30+31-32)</b></td><td align="right"><b>' . moneyCol($vlr_33_total_saldo_cargo) . '</b></td></tr>
<tr><td>35</td><td><b>VALOR A PAGAR</b></td><td align="right"><b>' . moneyCol($vlr_35_valor_a_pagar) . '</b></td></tr>
<tr><td>37</td><td>INTERESES DE MORA</td><td align="right">' . moneyCol($vlr_37_intereses_mora) . '</td></tr>
<tr bgcolor="#cae6e7"><td>38</td><td><b>TOTAL A PAGAR (renglón 35+36+37)</b></td><td align="right"><b>' . moneyCol($vlr_38_total_a_pagar) . '</b></td></tr>

</table>

<br>

<table border="1" cellpadding="2" width="100%">

<tr>
<td width="45%" rowspan="2" bgcolor="#e1dada">SECCIÓN PAGO VOLUNTARIO (solamente donde exista esta opción)</td>
<td width="40%">39 LIQUIDE EL VALOR DEL PAGO VOLUNTARIO</td>
<td width="15%" align="right">' . moneyCol($vlr_39_aporte_voluntario) . '</td>
</tr>

<tr>
<td width="40%">40 TOTAL A PAGAR CON PAGO VOLUNTARIO (renglón 38+39)</td>
<td width="15%" align="right">' . moneyCol($vlr_40_total_con_aporte) . '</td>
</tr>

</table>

<br>

<table border="1" cellpadding="2" width="100%">

<tr>
<td width="35%" align="center"><b>FIRMA DEL DECLARANTE</b><br>';

if ($firmaData) {
    $html .= '<div align="center"><img src="' . MUNICIPIO_SELLO_FIRMA . '" width="16" height="16"><br>'
           . '<span style="font-size:6px;">' . htmlspecialchars($firmaData['fd_NombreUsuario']) . '<br>' . $fechaSello . '</span></div>';
} else {
    $html .= '<br><br>';
}

$html .= '
</td>

<td width="65%" align="center"><b>FIRMA DEL CONTADOR O REVISOR FISCAL</b><br><br><br>
</td>

</tr>

<tr>

<td width="35%">
<b>NOMBRE:</b> ' . htmlspecialchars($firmante_nombre) . '<br>
C.C./NIT No. ' . htmlspecialchars($firmante_num_doc) . '
</td>

<td width="65%">
<b>NOMBRE:</b> ' . htmlspecialchars($nombreFirmanteContadorRevisor) . '<br>
C.C./T.P. No. ' . htmlspecialchars($docFirmanteContadorRevisor) . '
</td>

</tr>

</table>

<br>


';

$pdf->writeHTML($html, true, false, true, false, '');

/* ============================================================
   CODIGO DE BARRAS / REFERENCIA DE RECAUDO

   Bloque dibujado COMPLETO a mano (recuadro + rotulos + codigo), igual que en
   declaracion.php y por las mismas dos razones:

   1. Como <img src="@base64"> dentro del writeHTML, TCPDF vuelca la imagen a
      un archivo temporal en sys_get_temp_dir(), donde el PHP-FPM de Plesk no
      puede escribir ("TCPDF ERROR: Unable to write file"). write1DBarcode()
      es vectorial y no toca disco.
   2. Anclarlo con GetY() tras el writeHTML tampoco sirve: GetY() NO devuelve
      el borde inferior de la tabla -TCPDF deja el cursor mas abajo, pasado el
      salto de bloque-, asi que el codigo terminaba dibujado FUERA del
      recuadro, colgando debajo de la tabla.

   Dibujando la caja aqui se controla cada coordenada: el codigo queda
   centrado horizontal y verticalmente dentro de su celda por construccion.
   ============================================================ */
$margenes  = $pdf->getMargins();
$anchoUtil = $pdf->getPageWidth() - $margenes['left'] - $margenes['right'];
$mitad     = $anchoUtil / 2;
$xBloque   = $margenes['left'];
// -3mm compensa el salto de bloque que TCPDF suma al cerrar la tabla, para
// que el recuadro quede pegado al de arriba.
$yBloque   = $pdf->GetY() - 3;

$altoRotulo = 4.5;
$altoCodigo = 10.5;

$pdf->SetXY($xBloque, $yBloque);
$pdf->SetFont('helvetica', 'B', 7);
$pdf->Cell($mitad, $altoRotulo, 'CÓDIGO DE BARRAS', 1, 0, 'L');
$pdf->Cell($mitad, $altoRotulo, 'REFERENCIA DE RECAUDO FORMULARIO No.', 1, 1, 'L');

$pdf->SetXY($xBloque, $yBloque + $altoRotulo);
$pdf->Cell($mitad, $altoCodigo, '', 1, 0, 'C');
$pdf->SetFont('helvetica', '', 7);
$pdf->Cell($mitad, $altoCodigo, $referencia_recaudo, 1, 1, 'L');

$anchoBarcode = 55;
$altoBarcode  = 8;

$pdf->write1DBarcode(
    $referencia_recaudo, 'C128',
    $xBloque + ($mitad - $anchoBarcode) / 2,
    $yBloque + $altoRotulo + ($altoCodigo - $altoBarcode) / 2,
    $anchoBarcode, $altoBarcode,
    '',
    array('position' => '', 'border' => false, 'padding' => 0,
          'fgcolor' => array(0,0,0), 'bgcolor' => false,
          'text' => false, 'stretch' => true),
    'N'
);
/* ============================================================
   SALIDA PDF
   ============================================================ */
dibujarMarcaDeAgua($pdf, $textoMarcaAgua, 215.9, 330.2);

$pdf->Output('Liquidacion_ICA_Comercio.pdf','I');
