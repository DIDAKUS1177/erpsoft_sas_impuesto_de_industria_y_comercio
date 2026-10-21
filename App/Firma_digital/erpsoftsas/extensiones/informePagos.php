<?php
/**
 * Informe de declaraciones presentadas, pagadas o las dos, en PDF o Excel
 * (pedido de la Alcaldía de Paipa, 2026-10-13 y 14). Lo pide el módulo
 * Informes del administrador (dist/informes.php, core/informePagos.js).
 *
 * GET
 *   modulo         ica | reteica | autorreteica
 *   formato        pdf | xlsx
 *   estado         pagadas | sinpagar | todas (presentadas)
 *   anio, periodo  filtros opcionales
 *   documento      NIT o cédula, solo dígitos (vacío = todos los contribuyentes)
 *   desde, hasta   rango de fechas AAAA-MM-DD, opcionales (2026-10-20): de pago
 *                  en las pagadas, de presentación en las demás
 *
 * Quién: sesión, alcaldia.informes.pagos (que solo cuenta en un rol de la
 * Alcaldía) y el "Ver" del módulo.
 */

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
require_once __DIR__ . '/pdfRetenciones.php';
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
// La pantalla manda por POST: el NIT o la cédula de una persona no queda en la
// dirección, ni en el historial del navegador ni en el registro del servidor.
$IN = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$clave   = strtolower(trim((string) ($IN['modulo'] ?? '')));
$formato = strtolower(trim((string) ($IN['formato'] ?? '')));
if (!PseModulo::existe($clave) || !in_array($formato, ['pdf', 'xlsx'], true)) {
    informe_fin(400, 'Informe no válido.');
}
$m = PseModulo::get($clave);

$anio    = preg_match('/^\d{4}$/', (string) ($IN['anio'] ?? '')) ? (int) $IN['anio'] : null;
$maxPer  = $clave === 'autorreteica' ? 6 : 12;
$periodo = preg_match('/^\d{1,2}$/', (string) ($IN['periodo'] ?? '')) ? (int) $IN['periodo'] : null;
if ($periodo !== null && ($periodo < 1 || $periodo > $maxPer)) { $periodo = null; }
if ($anio !== null && ($anio < 2000 || $anio > 2100)) { $anio = null; }
$estado    = in_array($IN['estado'] ?? '', InformePagos::ESTADOS, true) ? $IN['estado'] : 'pagadas';
// ind_NumeroIdentificacion es INT: un NIT con su dígito de verificación pegado
// (9001234567) no cabe y la consulta fallaba. Se pide sin DV, como se guarda.
$documento = trim((string) ($IN['documento'] ?? ''));
if ($documento !== '' && (!ctype_digit($documento) || strlen($documento) > 10
                          || (int) $documento <= 0 || (int) $documento > 2147483647)) {
    informe_fin(400, 'Escriba el NIT o la cédula solo con números y sin dígito de verificación.');
}
// El rango de fechas (Paipa, 2026-10-20), los dos extremos opcionales.
$fechaInforme = function ($t) {
    $t = trim((string) $t);
    if ($t === '') { return null; }
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $t, $x) || !checkdate((int) $x[2], (int) $x[3], (int) $x[1])) { return false; }
    return $t;
};
$desde = $fechaInforme($IN['desde'] ?? '');
$hasta = $fechaInforme($IN['hasta'] ?? '');
if ($desde === false || $hasta === false) {
    informe_fin(400, 'Escriba las fechas del rango como día/mes/año.');
}
if ($desde !== null && $hasta !== null && $desde > $hasta) {
    informe_fin(400, 'La fecha "desde" es posterior a la fecha "hasta".');
}

/* ---- Quién --------------------------------------------------------------------- */
foreach (['alcaldia.informes.pagos', $clave . '.ver'] as $permiso) {
    if (!PermisosRol::tiene($permiso)) { informe_fin(403, PermisosRol::mensaje($permiso)); }
}
$con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

// Ya se sabe quién es: soltar la sesión para que sus otras pestañas no esperen
// mientras se arma un informe grande, y darle tiempo y memoria (medido: 10.000
// filas en PDF, ~8 s y ~73 MB).
$idUsuarioSesion = (int) $_SESSION['id_usuario'];
session_write_close();
@set_time_limit(180);
if ((int) ini_get('memory_limit') > 0 && (int) ini_get('memory_limit') < 256) { @ini_set('memory_limit', '256M'); }

/* ---- Los datos ----------------------------------------------------------------- */
try {
    $filas = InformePagos::filas($con, $clave, ['estado' => $estado, 'anio' => $anio, 'periodo' => $periodo,
                                                'documento' => $documento !== '' ? (int) $documento : null,
                                                'desde' => $desde, 'hasta' => $hasta]);
} catch (\Throwable $e) {
    error_log('[informe de pagos] ' . $e->getMessage());
    informe_fin(500, 'No se pudo generar el informe. Intente de nuevo.');
}

$tz    = new \DateTimeZone('America/Bogota');
$ahora = new \DateTime('now', $tz);
$u = $con->obnerFila($con->consultar("SELECT usu_Nombres, usu_Apellidos FROM conf_usuarios WHERE usu_Id = ?", [$idUsuarioSesion]));
$quien = trim(preg_replace('/\s+/', ' ', ($u['usu_Nombres'] ?? '') . ' ' . ($u['usu_Apellidos'] ?? '')));

$modulos   = ['ica' => 'Industria y Comercio', 'reteica' => 'Retención de Industria y Comercio', 'autorreteica' => 'Autorretención de Industria y Comercio'];
$titulos   = ['pagadas' => 'Declaraciones pagadas', 'sinpagar' => 'Declaraciones presentadas sin pagar',
              'todas' => 'Todas las declaraciones presentadas'];
$titulo    = $titulos[$estado];
$subtitulo = $modulos[$clave] . ' · Municipio de ' . MUNICIPIO_CIUDAD;
$dmy = function ($f) { return substr($f, 8, 2) . '/' . substr($f, 5, 2) . '/' . substr($f, 0, 4); };
$rango = '';
if ($desde !== null || $hasta !== null) {
    $rango = ($estado === 'pagadas' ? 'Pagadas' : 'Presentadas')
           . ($desde !== null && $hasta !== null ? ' del ' . $dmy($desde) . ' al ' . $dmy($hasta)
              : ($desde !== null ? ' desde el ' . $dmy($desde) : ' hasta el ' . $dmy($hasta))) . ' · ';
}
$filtros   = $rango . 'Año: ' . ($anio ?: 'todos')
           . ($clave === 'ica' ? '' : ' · Período: ' . ($periodo ? InformePagos::nombrePeriodo($clave, $periodo) : 'todos'))
           . ' · Contribuyente: ' . ($documento === '' ? 'todos' : InformePagos::nombrePorDocumento($con, $documento));
$generado  = 'Generado el ' . $ahora->format('d/m/Y \a \l\a\s h:i a') . ($quien !== '' ? ' por ' . $quien : '');

$totalDeclarado = array_sum(array_column($filas, 'valorDeclarado'));
$totalPagado    = array_sum(array_column($filas, 'valorPagado'));
$totalIntereses = array_sum(array_map('floatval', array_filter(array_column($filas, 'intereses'), 'is_numeric')));
$TOTALES = ['valorDeclarado' => $totalDeclarado, 'valorPagado' => $totalPagado, 'intereses' => $totalIntereses];

// [rótulo, ancho PDF (mm), ancho Excel, alineación, campo]. Suman 259.4 mm (carta
// apaisada menos márgenes). El ICA es anual (el mes es siempre diciembre): sin
// esa columna, Contribuyente, Banco y Medio caben completos. La cuenta contable
// (2026-10-19) es la del pago manual; los demás pagos no la llevan. Intereses
// (2026-10-20): lo pagado menos lo que había que pagar, nunca negativo.
if ($clave === 'ica') {
    $columnas = [
        ['NIT / Documento',  21, 16, 'L', 'documento'],
        ['Contribuyente',    34, 40, 'L', 'contribuyente'],
        ['Año',              10,  7, 'C', 'anio'],
        ['N° declaración',   20, 15, 'C', 'numero'],
        ['Presentada',       17, 13, 'C', 'fechaPresentacion'],
        ['Estado',           15, 11, 'C', 'estado'],
        ['Fecha de pago',    17, 13, 'C', 'fechaPago'],
        ['Banco',            28, 32, 'L', 'banco'],
        ['Cuenta contable',  16, 15, 'C', 'cuentaContable'],
        ['Medio',            18, 14, 'L', 'canal'],
        ['Valor a pagar',    21.7, 16, 'R', 'valorDeclarado'],
        ['Valor pagado',     21.7, 16, 'R', 'valorPagado'],
        ['Intereses',        20, 14, 'R', 'intereses'],
    ];
} else {
    $columnas = [
        ['NIT / Documento',  20, 16, 'L', 'documento'],
        ['Contribuyente',    25, 36, 'L', 'contribuyente'],
        ['Año',              10,  7, 'C', 'anio'],
        [$clave === 'autorreteica' ? 'Bimestre' : 'Mes', 16, 18, 'L', 'periodo'],
        ['N° declaración',   18, 15, 'C', 'numero'],
        ['Presentada',       17, 13, 'C', 'fechaPresentacion'],
        ['Estado',           15, 11, 'C', 'estado'],
        ['Fecha de pago',    18, 13, 'C', 'fechaPago'],
        ['Banco',            27, 32, 'L', 'banco'],
        ['Cuenta contable',  16, 15, 'C', 'cuentaContable'],
        ['Medio',            18, 14, 'L', 'canal'],
        ['Valor a pagar',    20.2, 16, 'R', 'valorDeclarado'],
        ['Valor pagado',     20.2, 16, 'R', 'valorPagado'],
        ['Intereses',        19, 14, 'R', 'intereses'],
    ];
}
$FECHAS = ['fechaPago', 'fechaPresentacion'];
$PESOS  = ['valorDeclarado', 'valorPagado', 'intereses'];
// Las columnas de valor van al final; el total va debajo de cada una.
$primeraValor = array_search('valorDeclarado', array_column($columnas, 4), true);

$color = ltrim(defined('MUNICIPIO_COLOR') ? (string) MUNICIPIO_COLOR : '#1fa49d', '#');
if (!preg_match('/^[0-9a-fA-F]{6}$/', $color)) { $color = '1fa49d'; }

$nombres = ['pagadas' => 'Pagadas', 'sinpagar' => 'Sin_pagar', 'todas' => 'Presentadas'];
$base = 'Declaraciones_' . $nombres[$estado] . '_' . preg_replace('/[^A-Za-z]/', '', ucfirst($clave)) . '_'
      . ($anio ?: 'todos-los-anios') . ($desde !== null ? '_desde-' . $desde : '') . ($hasta !== null ? '_hasta-' . $hasta : '')
      . ($documento !== '' ? '_' . $documento : '') . '_' . $ahora->format('Ymd');

/* ==== EXCEL ======================================================================== */
if ($formato === 'xlsx') {
    $x = new \erpsoftsas\XlsxSimple('Declaraciones', $color);
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
            if (in_array($campo, $FECHAS, true)) {
                $x->fecha($n, $i + 1, $f[$campo]);
            } elseif (($campo === 'valorPagado' && $f['estado'] !== 'Pagada') || ($campo === 'intereses' && $f['intereses'] === null)) {
                $x->texto($n, $i + 1, '', \erpsoftsas\XlsxSimple::PESOS);   // sin pagar o sin intereses: en blanco
            } elseif (in_array($campo, $PESOS, true)) {
                $x->numero($n, $i + 1, $f[$campo], \erpsoftsas\XlsxSimple::PESOS);
            } elseif ($campo === 'anio') {
                $x->numero($n, $i + 1, $f[$campo], \erpsoftsas\XlsxSimple::CENTRADO);
            } else {
                $x->texto($n, $i + 1, $f[$campo], in_array($campo, ['numero', 'estado', 'cuentaContable'], true) ? \erpsoftsas\XlsxSimple::CENTRADO : \erpsoftsas\XlsxSimple::TEXTO);
            }
        }
    }
    $x->congelarDebajoDe($cab);
    if ($filas) {
        $x->autofiltro('A' . $cab . ':' . $ultimaCol . $n);
        // SUBTOTAL(109, …): el total sigue a los filtros que se apliquen en Excel.
        $t = $n + 1;
        $x->texto($t, 1, 'Total', \erpsoftsas\XlsxSimple::TOTAL);
        for ($i = 2; $i <= $primeraValor; $i++) { $x->texto($t, $i, '', \erpsoftsas\XlsxSimple::TOTAL); }
        // La columna del número (cuenta las filas visibles) depende del módulo.
        $colNum = \erpsoftsas\XlsxSimple::columna(array_search('numero', array_column($columnas, 4), true) + 1);
        $x->formula($t, 2, 'SUBTOTAL(103,' . $colNum . ($cab + 1) . ':' . $colNum . $n . ')&" declaraciones"', \erpsoftsas\XlsxSimple::TOTAL,
                    count($filas) . ' declaraciones');
        foreach ($columnas as $i => $c) {
            if (!isset($TOTALES[$c[4]])) { continue; }
            $col = \erpsoftsas\XlsxSimple::columna($i + 1);
            $x->formula($t, $i + 1, 'SUBTOTAL(109,' . $col . ($cab + 1) . ':' . $col . $n . ')', \erpsoftsas\XlsxSimple::TOTAL_PESOS, $TOTALES[$c[4]]);
        }
    } else {
        $x->texto($cab + 1, 1, 'No hay declaraciones con estos filtros.', \erpsoftsas\XlsxSimple::SUBTITULO);
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
        $this->Cell(70, 4, 'A PAGAR $ ' . number_format($e['totalDeclarado'], 0, ',', '.')
                         . '  ·  PAGADO $ ' . number_format($e['totalPagado'], 0, ',', '.'), 0, 0, 'R');

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
        $this->SetFont('helvetica', 'B', 7);
        // Un rótulo que no cabe se comprime en vez de salirse de su celda.
        foreach ($this->columnas as $c) {
            $this->Cell($c[1], 7, $c[0], 'LR', 0, $c[3], true, '', 1);
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
    'cantidad' => count($filas), 'totalPagado' => $totalPagado, 'totalDeclarado' => $totalDeclarado,
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
    $pdf->Cell(259.4, 14, 'No hay declaraciones con estos filtros.', 'B', 1, 'C');
}

foreach ($filas as $i => $f) {
    if ($pdf->GetY() + $alto > $limite) { $pdf->AddPage(); $pdf->SetDrawColor(229, 231, 235); }
    $pdf->SetFont('helvetica', '', 7);
    $pdf->SetTextColor(31, 41, 55);
    $pdf->SetFillColor(244, 247, 249);
    $zebra = $i % 2 === 1;
    foreach ($columnas as $c) {
        $v = $f[$c[4]];
        if (($c[4] === 'valorPagado' && $f['estado'] !== 'Pagada') || ($c[4] === 'intereses' && $v === null)) {
            $pdf->Cell($c[1], $alto, '', 'B', 0, $c[3], $zebra, '', 0);   // sin pagar o sin intereses: en blanco
            continue;
        }
        if (in_array($c[4], $PESOS, true)) {
            // Una cifra nunca se recorta: si no cabe, se comprime.
            $pdf->Cell($c[1], $alto, '$ ' . number_format($v, 0, ',', '.'), 'B', 0, $c[3], $zebra, '', 1);
            continue;
        }
        // El número de cuenta, el banco, el período, el estado y el medio no se
        // recortan: si no caben, se comprimen (como las cifras).
        if (in_array($c[4], ['cuentaContable', 'banco', 'estado', 'canal'], true)
            || ($c[4] === 'periodo' && $clave !== 'autorreteica')) {
            $pdf->Cell($c[1], $alto, (string) $v, 'B', 0, $c[3], $zebra, '', 1);
            continue;
        }
        if (in_array($c[4], $FECHAS, true)) {
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
    $antes = array_sum(array_column(array_slice($columnas, 0, $primeraValor), 1));
    $n = count($filas);
    $pdf->Cell($antes, 8, 'Total: ' . $n . ($n === 1 ? ' declaración' : ' declaraciones'), 'T', 0, 'L', true);
    $valores = array_slice($columnas, $primeraValor);
    foreach ($valores as $k => $c) {
        $pdf->Cell($c[1], 8, '$ ' . number_format($TOTALES[$c[4]], 0, ',', '.'), 'T', $k === count($valores) - 1 ? 1 : 0, 'R', true, '', 1);
    }
}

$pdf->Output($base . '.pdf', 'I');
