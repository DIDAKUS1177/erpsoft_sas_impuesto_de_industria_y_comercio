<?php
/**
 * Informe de declaraciones PAGADAS en PDF o Excel (pedido de la Alcaldía de
 * Paipa, 2026-10-13). Lo piden los botones de Consultar de los tres módulos
 * (core/informePagos.js).
 *
 * GET
 *   modulo         ica | reteica | autorreteica
 *   formato        pdf | xlsx
 *   anio, periodo  filtros opcionales (los de la pantalla)
 *   alcance        'todos' (todos los contribuyentes) o vacío
 *   contribuyente  el contribuyente de la pantalla (cuando no es 'todos')
 *
 * Quién: sesión y "Ver" del módulo. Todos los contribuyentes, solo con
 * alcaldia.informes.pagos (que solo cuenta en un rol de la Alcaldía); uno,
 * quien puede operar sobre él (la Alcaldía que gestiona, o el propio
 * contribuyente). Columnas: las que pidió el cliente (NIT, contribuyente, año,
 * período, número, fecha de pago, banco y valor), más el medio y lo pagado.
 */

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
require_once __DIR__ . '/pdfRetenciones.php';
include_once SERVER . '/business/controller/class.anexos.php';
include_once SERVER . '/business/class.permisosRol.php';
include_once SERVER . '/business/class.informePagos.php';
include_once SERVER . '/business/class.xlsxSimple.php';

use erpsoftsas\InformePagos;
use erpsoftsas\PermisosRol;
use erpsoftsas\PseModulo;

if (session_status() === PHP_SESSION_NONE) { @session_start(); }

function informe_fin($codigo, $texto)
{
    http_response_code($codigo);
    header('Content-Type: text/plain; charset=utf-8');
    exit($texto);
}

if (empty($_SESSION['id_usuario'])) {
    informe_fin(401, 'Debe iniciar sesión para descargar el informe.');
}

/* ---- Lo que se pide ---------------------------------------------------------- */
$clave   = strtolower(trim((string) ($_GET['modulo'] ?? '')));
$formato = strtolower(trim((string) ($_GET['formato'] ?? '')));
if (!PseModulo::existe($clave) || !in_array($formato, ['pdf', 'xlsx'], true)) {
    informe_fin(400, 'Informe no válido.');
}
$m = PseModulo::get($clave);

$anio    = preg_match('/^\d{4}$/', (string) ($_GET['anio'] ?? '')) ? (int) $_GET['anio'] : null;
$maxPer  = $clave === 'autorreteica' ? 6 : 12;
$periodo = preg_match('/^\d{1,2}$/', (string) ($_GET['periodo'] ?? '')) ? (int) $_GET['periodo'] : null;
if ($periodo !== null && ($periodo < 1 || $periodo > $maxPer)) { $periodo = null; }
if ($anio !== null && ($anio < 2000 || $anio > 2100)) { $anio = null; }
$todos = ($_GET['alcance'] ?? '') === 'todos';
$idContribuyente = (int) ($_GET['contribuyente'] ?? 0);

/* ---- Quién --------------------------------------------------------------------- */
if (!PermisosRol::tiene($clave . '.ver')) {
    informe_fin(403, PermisosRol::mensaje($clave . '.ver'));
}
$con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
if ($todos) {
    if (!PermisosRol::tiene('alcaldia.informes.pagos')) {
        informe_fin(403, PermisosRol::mensaje('alcaldia.informes.pagos'));
    }
    $idContribuyente = 0;
} elseif ($idContribuyente <= 0 || !\erpsoftsas\ControladorAnexos::puedeOperarSobreContribuyente($idContribuyente, $con)) {
    informe_fin(403, 'Solo puede descargar el informe del contribuyente que gestiona.');
}

// Ya se sabe quién es: soltar la sesión para que sus otras pestañas no esperen
// mientras se arma un informe grande, y darle tiempo y memoria (medido: 10.000
// filas en PDF, ~8 s y ~73 MB).
$idUsuarioSesion = (int) $_SESSION['id_usuario'];
session_write_close();
@set_time_limit(180);
if ((int) ini_get('memory_limit') > 0 && (int) ini_get('memory_limit') < 256) { @ini_set('memory_limit', '256M'); }

/* ---- Los datos ----------------------------------------------------------------- */
try {
    $filas = InformePagos::filas($con, $clave, ['anio' => $anio, 'periodo' => $periodo, 'contribuyente' => $idContribuyente ?: null]);
} catch (\Throwable $e) {
    error_log('[informe de pagos] ' . $e->getMessage());
    informe_fin(500, 'No se pudo generar el informe. Intente de nuevo.');
}

$tz    = new \DateTimeZone('America/Bogota');
$ahora = new \DateTime('now', $tz);
$u = $con->obnerFila($con->consultar("SELECT usu_Nombres, usu_Apellidos FROM conf_usuarios WHERE usu_Id = ?", [$idUsuarioSesion]));
$quien = trim(preg_replace('/\s+/', ' ', ($u['usu_Nombres'] ?? '') . ' ' . ($u['usu_Apellidos'] ?? '')));

$modulos   = ['ica' => 'Industria y Comercio', 'reteica' => 'Retención de Industria y Comercio', 'autorreteica' => 'Autorretención de Industria y Comercio'];
$titulo    = 'Informe de declaraciones pagadas';
$subtitulo = $modulos[$clave] . ' · Municipio de ' . MUNICIPIO_CIUDAD;
$filtros   = 'Año: ' . ($anio ?: 'todos')
           . ($clave === 'ica' ? '' : ' · Período: ' . ($periodo ? InformePagos::nombrePeriodo($clave, $periodo) : 'todos'))
           . ' · Contribuyente: ' . ($todos ? 'todos' : InformePagos::nombreContribuyente($con, $idContribuyente));
$generado  = 'Generado el ' . $ahora->format('d/m/Y \a \l\a\s h:i a') . ($quien !== '' ? ' por ' . $quien : '');

$totalDeclarado = array_sum(array_column($filas, 'valorDeclarado'));
$totalPagado    = array_sum(array_column($filas, 'valorPagado'));

$columnas = [
    // [rótulo, ancho PDF (mm), ancho Excel, alineación, campo]
    ['NIT / Documento',  26, 16, 'L', 'documento'],
    ['Contribuyente',    46, 40, 'L', 'contribuyente'],
    ['Año',              11,  7, 'C', 'anio'],
    [$clave === 'autorreteica' ? 'Bimestre' : 'Mes', 24, 20, 'L', 'periodo'],
    ['N° declaración',   22, 15, 'C', 'numero'],
    ['Fecha de pago',    21, 13, 'C', 'fechaPago'],
    ['Banco',            36, 32, 'L', 'banco'],
    ['Medio',            24, 18, 'L', 'canal'],
    ['Valor a pagar',    24.7, 16, 'R', 'valorDeclarado'],
    ['Valor pagado',     24.7, 16, 'R', 'valorPagado'],
];

$color = ltrim(defined('MUNICIPIO_COLOR') ? (string) MUNICIPIO_COLOR : '#1fa49d', '#');
if (!preg_match('/^[0-9a-fA-F]{6}$/', $color)) { $color = '1fa49d'; }

$base = 'Pagos_' . preg_replace('/[^A-Za-z]/', '', ucfirst($clave)) . '_' . ($anio ?: 'todos-los-anios')
      . ($todos ? '_todos' : '') . '_' . $ahora->format('Ymd');

/* ==== EXCEL ======================================================================== */
if ($formato === 'xlsx') {
    $x = new \erpsoftsas\XlsxSimple('Pagadas', $color);
    $x->anchos(array_column($columnas, 2));
    $ultimaCol = \erpsoftsas\XlsxSimple::columna(count($columnas));

    $x->texto(1, 1, $titulo, \erpsoftsas\XlsxSimple::TITULO);            $x->unir('A1', $ultimaCol . '1');
    $x->texto(2, 1, $subtitulo, \erpsoftsas\XlsxSimple::SUBTITULO);      $x->unir('A2', $ultimaCol . '2');
    $x->texto(3, 1, $filtros, \erpsoftsas\XlsxSimple::SUBTITULO);        $x->unir('A3', $ultimaCol . '3');
    $x->texto(4, 1, $generado, \erpsoftsas\XlsxSimple::SUBTITULO);       $x->unir('A4', $ultimaCol . '4');

    $cab = 6;
    foreach ($columnas as $i => $c) { $x->texto($cab, $i + 1, $c[0], \erpsoftsas\XlsxSimple::ENCABEZADO); }
    $n = $cab;
    foreach ($filas as $f) {
        $n++;
        foreach ($columnas as $i => $c) {
            $campo = $c[4];
            if ($campo === 'fechaPago') {
                $x->fecha($n, $i + 1, $f[$campo]);
            } elseif ($campo === 'valorDeclarado' || $campo === 'valorPagado') {
                $x->numero($n, $i + 1, $f[$campo], \erpsoftsas\XlsxSimple::PESOS);
            } elseif ($campo === 'anio') {
                $x->numero($n, $i + 1, $f[$campo], \erpsoftsas\XlsxSimple::CENTRADO);
            } else {
                $x->texto($n, $i + 1, $f[$campo], $campo === 'numero' ? \erpsoftsas\XlsxSimple::CENTRADO : \erpsoftsas\XlsxSimple::TEXTO);
            }
        }
    }
    $x->congelarDebajoDe($cab);
    if ($filas) {
        $x->autofiltro('A' . $cab . ':' . $ultimaCol . $n);
        // SUBTOTAL(109, …): el total sigue a los filtros que se apliquen en Excel.
        $t = $n + 1;
        $x->texto($t, 1, 'Total', \erpsoftsas\XlsxSimple::TOTAL);
        for ($i = 2; $i <= 8; $i++) { $x->texto($t, $i, '', \erpsoftsas\XlsxSimple::TOTAL); }
        $x->formula($t, 2, 'SUBTOTAL(103,E' . ($cab + 1) . ':E' . $n . ')&" declaraciones"', \erpsoftsas\XlsxSimple::TOTAL,
                    count($filas) . ' declaraciones');
        $x->formula($t, 9,  'SUBTOTAL(109,I' . ($cab + 1) . ':I' . $n . ')', \erpsoftsas\XlsxSimple::TOTAL_PESOS, $totalDeclarado);
        $x->formula($t, 10, 'SUBTOTAL(109,J' . ($cab + 1) . ':J' . $n . ')', \erpsoftsas\XlsxSimple::TOTAL_PESOS, $totalPagado);
    } else {
        $x->texto($cab + 1, 1, 'No hay declaraciones pagadas con estos filtros.', \erpsoftsas\XlsxSimple::SUBTITULO);
        $x->unir('A' . ($cab + 1), $ultimaCol . ($cab + 1));
    }

    $bytes = $x->contenido();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $base . '.xlsx"');
    header('Content-Length: ' . strlen($bytes));
    header('Cache-Control: private, no-store');
    echo $bytes;
    exit;
}

/* ==== PDF ========================================================================== */
class InformePagosPdf extends TCPDF
{
    public $encabezado = [];
    public $columnas = [];
    public $rgb = [31, 164, 157];

    public function Header()
    {
        $e = $this->encabezado;
        $escudo = pdfret_rutaEscudo();
        // $resize = false: redimensionar pasa por GD y un archivo temporal, y el
        // PHP de Plesk no puede escribirlo (la trampa de TCPDF de CLAUDE.md).
        if (is_file($escudo)) { $this->Image($escudo, 10, 8, 0, 15, '', '', '', false, 300); }

        $this->SetXY(28, 8.5);
        $this->SetFont('helvetica', 'B', 13);
        $this->SetTextColor($this->rgb[0], $this->rgb[1], $this->rgb[2]);
        $this->Cell(170, 6, $e['titulo'], 0, 2, 'L');
        $this->SetFont('helvetica', '', 8.5);
        $this->SetTextColor(55, 65, 81);
        $this->Cell(170, 4.5, $e['subtitulo'], 0, 2, 'L');
        $this->SetTextColor(107, 114, 128);
        $this->Cell(170, 4.5, informe_cabe($this, $e['filtros'], 166), 0, 0, 'L');

        // Resumen a la derecha.
        $this->SetXY(199.4, 8.5);
        $this->SetFont('helvetica', '', 7.5);
        $this->Cell(70, 4, 'DECLARACIONES', 0, 2, 'R');
        $this->SetFont('helvetica', 'B', 12);
        $this->SetTextColor(17, 24, 39);
        $this->Cell(70, 5.5, number_format($e['cantidad'], 0, ',', '.'), 0, 2, 'R');
        $this->SetFont('helvetica', '', 7.5);
        $this->SetTextColor(107, 114, 128);
        $this->Cell(70, 4, 'TOTAL PAGADO  $ ' . number_format($e['totalPagado'], 0, ',', '.'), 0, 0, 'R');

        $this->SetDrawColor($this->rgb[0], $this->rgb[1], $this->rgb[2]);
        $this->SetLineWidth(0.5);
        $this->Line(10, 26, 269.4, 26);
        $this->SetLineWidth(0.2);

        // Encabezado de la tabla, en cada hoja.
        $this->SetXY(10, 29);
        $this->filaEncabezado();
    }

    public function filaEncabezado()
    {
        // TCPDF dibuja el encabezado de página sin el margen interno de las celdas.
        $this->setCellPaddings(1.6, 0, 1.6, 0);
        $this->SetFillColor($this->rgb[0], $this->rgb[1], $this->rgb[2]);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(255, 255, 255);
        $this->SetFont('helvetica', 'B', 7.5);
        foreach ($this->columnas as $c) {
            $this->Cell($c[1], 7, $c[0], 'LR', 0, $c[3], true, '', 0);
        }
        $this->Ln();
    }

    public function Footer()
    {
        $this->SetY(-11);
        $this->SetDrawColor(209, 213, 219);
        $this->Line(10, $this->GetY(), 269.4, $this->GetY());
        $this->SetFont('helvetica', '', 7);
        $this->SetTextColor(107, 114, 128);
        $this->Cell(180, 6, $this->encabezado['generado'], 0, 0, 'L');
        // getAliasRightShift: el alias del total de páginas ocupa más que el
        // número que lo reemplaza, y sin esto el texto queda corrido a la izquierda.
        $this->Cell(79.4, 6, $this->getAliasRightShift() . 'Página ' . $this->getAliasNumPage() . ' de ' . $this->getAliasNbPages(), 0, 0, 'R');
    }
}

/** Recorta un texto para que quepa en el ancho, con "..." al final. */
function informe_cabe($pdf, $texto, $ancho)
{
    $texto = (string) $texto;
    if ($pdf->GetStringWidth($texto) <= $ancho) { return $texto; }
    while ($texto !== '' && $pdf->GetStringWidth($texto . '...') > $ancho) {
        $texto = mb_substr($texto, 0, -1, 'UTF-8');
    }
    return rtrim($texto) . '...';
}

$pdf = new InformePagosPdf('L', 'mm', 'LETTER', true, 'UTF-8', false);
$pdf->rgb = [hexdec(substr($color, 0, 2)), hexdec(substr($color, 2, 2)), hexdec(substr($color, 4, 2))];
$pdf->columnas = $columnas;
$pdf->encabezado = [
    'titulo' => $titulo, 'subtitulo' => $subtitulo, 'filtros' => $filtros, 'generado' => $generado,
    'cantidad' => count($filas), 'totalPagado' => $totalPagado,
];
$pdf->setCellPaddings(1.6, 0, 1.6, 0);
$pdf->SetCreator('ERPSoft');
$pdf->SetTitle($titulo . ' - ' . $modulos[$clave]);
$pdf->SetMargins(10, 36, 10);
$pdf->SetHeaderMargin(0);
$pdf->SetFooterMargin(12);
$pdf->SetAutoPageBreak(true, 14);
$pdf->AddPage();

$alto  = 6;
$limite = $pdf->getPageHeight() - 14;
$pdf->SetDrawColor(229, 231, 235);

if (!$filas) {
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(107, 114, 128);
    $pdf->Cell(259.4, 14, 'No hay declaraciones pagadas con estos filtros.', 'B', 1, 'C');
}

foreach ($filas as $i => $f) {
    if ($pdf->GetY() + $alto > $limite) { $pdf->AddPage(); $pdf->SetDrawColor(229, 231, 235); }
    $pdf->SetFont('helvetica', '', 7.5);
    $pdf->SetTextColor(31, 41, 55);
    $pdf->SetFillColor(244, 247, 249);
    $zebra = $i % 2 === 1;
    foreach ($columnas as $c) {
        $v = $f[$c[4]];
        if ($c[4] === 'valorDeclarado' || $c[4] === 'valorPagado') {
            // Una cifra nunca se recorta: si no cabe, se comprime.
            $pdf->Cell($c[1], $alto, '$ ' . number_format($v, 0, ',', '.'), 'B', 0, $c[3], $zebra, '', 1);
            continue;
        }
        if ($c[4] === 'fechaPago') {
            $v = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $d) ? $d[3] . '/' . $d[2] . '/' . $d[1] : '';
        } elseif ($c[4] === 'periodo' && $clave === 'autorreteica') {
            $v = preg_replace_callback('/[A-Za-zÁÉÍÓÚáéíóú]+/u', function ($p) { return mb_substr($p[0], 0, 3, 'UTF-8'); }, $v);
        }
        $pdf->Cell($c[1], $alto, informe_cabe($pdf, $v, $c[1] - 3.2), 'B', 0, $c[3], $zebra, '', 0);
    }
    $pdf->Ln();
}

if ($filas) {
    if ($pdf->GetY() + 8 > $limite) { $pdf->AddPage(); }
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetTextColor(17, 24, 39);
    $pdf->SetFillColor(238, 242, 246);
    $pdf->SetDrawColor($pdf->rgb[0], $pdf->rgb[1], $pdf->rgb[2]);
    $antes = array_sum(array_column(array_slice($columnas, 0, 8), 1));
    $n = count($filas);
    $pdf->Cell($antes, 8, 'Total: ' . $n . ($n === 1 ? ' declaración' : ' declaraciones'), 'T', 0, 'L', true);
    $pdf->Cell($columnas[8][1], 8, '$ ' . number_format($totalDeclarado, 0, ',', '.'), 'T', 0, 'R', true, '', 1);
    $pdf->Cell($columnas[9][1], 8, '$ ' . number_format($totalPagado, 0, ',', '.'), 'T', 1, 'R', true, '', 1);
}

$pdf->Output($base . '.pdf', 'I');
