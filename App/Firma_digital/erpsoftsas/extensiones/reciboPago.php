<?php
/*
 * ============================================================================
 * RECIBO DE PAGO — ICA, RETENCIÓN Y AUTORRETENCIÓN
 * ============================================================================
 *
 * El recibo que el contribuyente lleva al banco (pedido del cliente 2026-09-24,
 * con su formato de referencia "Factura de Pago ICA"). Una hoja carta: arriba
 * la copia del CONTRIBUYENTE con los conceptos y el total en letras; abajo, tras
 * la línea de corte, la copia del BANCO con el sello y el código de barras.
 *
 * Es UNO para los tres impuestos: lo único que cambia son los conceptos, y
 * salen de lo que la declaración ya tiene guardado -los mismos valores que
 * imprime su formulario-, sin recalcular nada.
 *
 *   ?modulo=ICA|RETEICA|AUTORRETEICA&id=<id de la declaración>
 *
 * Solo para declaraciones PRESENTADAS, SIN PAGAR y con valor a pagar: sobre un
 * borrador el valor todavía puede cambiar, y una pagada ya no necesita recibo.
 * El acceso es el mismo de los PDF de la declaración (pdfret_filaAutorizada):
 * la Alcaldía, cualquiera; el contribuyente, solo las suyas.
 *
 * Lo que decidió el cliente (2026-09-24):
 *   - Se llama "Recibo de pago". "Papelería municipal", que trae el formato de
 *     referencia, NO va. El establecimiento tampoco; el NIT sí.
 *   - LIQUIDADOR: si lo imprime un funcionario de la Alcaldía (roles 1 y 2) va
 *     su nombre; si lo genera el propio contribuyente, no va ninguno.
 *   - INTERESES DE MORA: línea aparte bajo el SUBTOTAL, sumada al total y al
 *     código de barras. A MANO por ahora (Javier, 2026-09-25): los escribe quien
 *     genera el recibo (?intereses=; sin él, se le pide el valor). Una ICA
 *     vencida sale CON intereses también si la saca el contribuyente (cliente,
 *     2026-09-25). Ver el bloque de los intereses.
 *   - ICA: hasta la fecha límite (class.vencimientoICA.php) el recibo vale
 *     hasta ese día y no lleva intereses; vencida, vale lo que diga "Días de
 *     vigencia del recibo".
 * ============================================================================
 */

require_once __DIR__ . '/pdfRetenciones.php';
include_once SERVER . '/business/class.vencimientoICA.php';

// "Hoy" es el de Colombia (emisión y "pague antes de"): con el servidor en UTC,
// desde las 7 p. m. el recibo salía con la fecha de mañana.
date_default_timezone_set('America/Bogota');

/** Mensaje en texto y fin: el enlace abre en otra pestaña. */
function recibo_salir($mensaje, $codigo = 200)
{
    http_response_code($codigo);
    header('Content-Type: text/plain; charset=utf-8');
    exit($mensaje);
}

function recibo_h($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function recibo_pesos($v)
{
    return '$' . number_format((float) $v, 0, ',', '.');
}

/**
 * Paso previo cuando el recibo lleva intereses de mora: una casilla para
 * escribirlos a mano (la Alcaldía, o el contribuyente con una ICA vencida; ver
 * el bloque de los intereses). "Generar recibo" vuelve a este mismo archivo con
 * ?intereses= y ahí sale el PDF. Termina la petición.
 */
function recibo_pedirIntereses(array $d)
{
    $color = defined('MUNICIPIO_COLOR') ? MUNICIPIO_COLOR : '#1fa49d';

    $filas = [
        ['Impuesto', $d['impuesto']],
        ['N° declaración', $d['numero']],
        ['Período', $d['periodo']],
        ['Contribuyente', $d['nombre'] . ($d['doc'] !== '' ? ' · ' . $d['doc'] : '')],
    ];
    if ($d['limite'] !== '') {
        $filas[] = ['Fecha límite', $d['limite'] . ' (vencida)'];
    }
    $filas[] = ['Valor de la declaración', recibo_pesos($d['subtotal'])];
    $filas[] = ['Pague antes de', $d['vence']];

    $htmlFilas = '';
    foreach ($filas as [$k, $v]) {
        $htmlFilas .= '<div class="fila"><span class="k">' . recibo_h($k) . '</span><span class="v">'
                    . recibo_h($v) . '</span></div>';
    }

    header('Content-Type: text/html; charset=utf-8');
    ?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Recibo de pago <?= recibo_h($d['numero']) ?></title>
<style>
  :root { --c: <?= recibo_h($color) ?>; }
  * { box-sizing: border-box; }
  body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f6f8; color: #333;
         margin: 0; padding: 24px 16px; display: flex; justify-content: center; }
  .tarjeta { background: #fff; max-width: 520px; width: 100%; border-radius: 10px;
             box-shadow: 0 4px 20px rgba(0,0,0,.08); overflow: hidden; }
  .cab { background: var(--c); color: #fff; padding: 16px 24px; }
  .cab b { display: block; font-size: 18px; }
  .cab span { font-size: 13px; opacity: .9; }
  .cuerpo { padding: 20px 24px 24px; }
  .fila { display: flex; justify-content: space-between; gap: 16px; padding: 8px 0;
          border-bottom: 1px solid #eee; font-size: 14px; }
  .fila .k { color: #666; }
  .fila .v { font-weight: 600; text-align: right; }
  .nota { font-size: 13px; background: #eef4f5; border: 1px solid #cfe0e2; color: #33555a;
          border-radius: 6px; padding: 10px 12px; margin-top: 14px; }
  label { display: block; font-weight: 600; margin: 18px 0 6px; }
  input { width: 100%; font-size: 20px; padding: 10px 12px; border: 1px solid #c8d0d6;
          border-radius: 6px; text-align: right; font-variant-numeric: tabular-nums; }
  input:focus { outline: 2px solid var(--c); outline-offset: 1px; border-color: var(--c); }
  .nota.error { background: #fdecec; border-color: #f3c2c2; color: #8a1f1f; }
  .ayuda { font-size: 12px; color: #777; margin-top: 6px; }
  .total { display: flex; justify-content: space-between; font-size: 17px; font-weight: 700;
           margin: 16px 0; font-variant-numeric: tabular-nums; }
  .btn { display: block; width: 100%; border: 0; padding: 13px; font-size: 16px; font-weight: 600;
         color: #fff; background: var(--c); border-radius: 6px; cursor: pointer; }
  .btn.sec { background: #6c757d; margin-top: 10px; text-align: center; text-decoration: none; }
</style>
</head>
<body>
  <div class="tarjeta">
    <div class="cab"><b>Recibo de pago</b><span><?= recibo_h($d['municipio']) ?></span></div>
    <div class="cuerpo">
      <?= $htmlFilas ?>
      <?php if ($d['declarados'] > 0): ?>
      <div class="nota">La declaración ya trae <?= recibo_h(recibo_pesos($d['declarados'])) ?> de
        intereses de mora en su formulario, incluidos en el valor de arriba. Escriba solo lo que falte.</div>
      <?php endif; ?>
      <form method="get" action="reciboPago.php" id="formIntereses">
        <input type="hidden" name="modulo" value="<?= recibo_h($d['modulo']) ?>">
        <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
        <?php if (!empty($d['error'])): ?>
        <div class="nota error" role="alert"><?= recibo_h($d['error']) ?></div>
        <?php endif; ?>
        <label for="intereses">Intereses de mora al <?= recibo_h($d['vence']) ?></label>
        <?php if (!empty($d['exige'])): ?>
        <input id="intereses" name="intereses" value="" placeholder="Escriba el valor" inputmode="numeric" autocomplete="off" autofocus required>
        <div class="ayuda">La declaración venció el <?= recibo_h($d['limite']) ?>: el recibo lleva los
          intereses de mora de <?= (int) $d['dias'] ?> día<?= (int) $d['dias'] === 1 ? '' : 's' ?>, del día siguiente a la
          fecha límite al <?= recibo_h($d['vence']) ?>. En pesos, sin decimales. Si no sabe cuánto son,
          comuníquese con la Alcaldía.</div>
        <?php else: ?>
        <input id="intereses" name="intereses" value="0" inputmode="numeric" autocomplete="off" autofocus required>
        <div class="ayuda">En pesos, sin decimales. Déjelo en 0 si no aplica.<?php if ((int) $d['dias'] > 0): ?>
          Días de mora al <?= recibo_h($d['vence']) ?>: <?= (int) $d['dias'] ?>.<?php endif; ?></div>
        <?php endif; ?>
        <div class="total"><span>Total del recibo</span><span id="total"><?= recibo_h(recibo_pesos($d['subtotal'])) ?></span></div>
        <button type="submit" class="btn">Generar recibo</button>
        <a class="btn sec" href="#" onclick="window.close(); setTimeout(function () { history.back(); }, 200); return false;">Cancelar</a>
      </form>
    </div>
  </div>
<script>
  // Pesos enteros con puntos de miles mientras se escribe; el servidor quita los
  // puntos. Lo que vaya después de la coma (centavos) se ve pero no se cobra: el
  // servidor lo descarta (VencimientoICA::leerIntereses).
  var subtotal = <?= (int) round($d['subtotal']) ?>;
  var campo = document.getElementById('intereses');
  var total = document.getElementById('total');
  function pesos(n) { return '$' + String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }
  campo.addEventListener('input', function () {
    var partes = campo.value.split(',');
    var digitos = partes[0].replace(/\D/g, '').replace(/^0+(?=\d)/, '').slice(0, 10);
    var centavos = partes.length > 1 ? ',' + partes.slice(1).join('').replace(/\D/g, '').slice(0, 2) : '';
    campo.value = (digitos === '' ? '' : pesos(digitos).slice(1)) + centavos;
    total.textContent = pesos(subtotal + Number(digitos || 0));
  });
  // Lo pegado con punto decimal ("1234.56") pasa a coma, que es como se descarta.
  campo.addEventListener('paste', function (e) {
    var t = String((e.clipboardData || window.clipboardData).getData('text') || '').trim();
    e.preventDefault();
    if (t.indexOf(',') === -1 && (t.match(/\./g) || []).length === 1 && /\.\d{1,2}$/.test(t)) { t = t.replace('.', ','); }
    campo.value = t;
    campo.dispatchEvent(new Event('input'));
  });
  campo.addEventListener('focus', function () { campo.select(); });
</script>
</body>
</html><?php
    exit;
}

$MODULOS = [
    'ICA'          => ['tabla' => 'ind_declaraciones_ica', 'prefijo' => 'dec_',
                       'impuesto' => 'IMPUESTO DE INDUSTRIA Y COMERCIO'],
    'RETEICA'      => ['tabla' => 'ind_reteica', 'prefijo' => 'ret_',
                       'impuesto' => 'RETENCIÓN DE INDUSTRIA Y COMERCIO'],
    'AUTORRETEICA' => ['tabla' => 'ind_autorreteica', 'prefijo' => 'aut_',
                       'impuesto' => 'AUTORRETENCIÓN DE INDUSTRIA Y COMERCIO'],
];

$modulo = strtoupper(trim((string) ($_GET['modulo'] ?? '')));
if (!isset($MODULOS[$modulo])) {
    recibo_salir('Módulo no válido.', 400);
}
$m = $MODULOS[$modulo];
$p = $m['prefijo'];

$con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
$row = pdfret_filaAutorizada($con, $m['tabla'], $p, $_GET['id'] ?? 0);

if ((int) ($row[$p . 'Estado'] ?? 0) !== 2) {
    recibo_salir('El recibo de pago se genera cuando la declaración ya está presentada.');
}
if (!empty($row[$p . 'Pagado'])) {
    recibo_salir('Esta declaración ya está pagada: no necesita recibo de pago.');
}

$contribuyente = $con->obnerFila($con->consultar(
    "SELECT * FROM ind_contribuyentes WHERE ind_Id = ?",
    [(int) $row[$p . 'IdContribuyente']]
)) ?: [];

/* ===========================================================================
   LO QUE CAMBIA POR MÓDULO: número, período, conceptos y total declarado
   =========================================================================== */

$MESES = [1 => 'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO',
          'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];
// Los mismos rótulos que el formulario de autorretención.
$BIMESTRES = [1 => 'ENERO - FEBRERO', 'MARZO - ABRIL', 'MAYO - JUNIO',
              'JULIO - AGOSTO', 'SEPTIEMBRE - OCTUBRE', 'NOVIEMBRE - DICIEMBRE'];

// Los renglones de la declaración MENOS su total: ese pasa a ser el SUBTOTAL,
// al que se suman los intereses de mora.
$conceptos = [];

if ($modulo === 'ICA') {
    $numero  = (string) ($row['dec_NumeroDeclaracion'] ?: $row['dec_Id']);
    $anio    = (int) $row['dec_AnioDeclaracion'];
    $periodo = (string) $anio;

    /* Mismo mapeo casilla → columna que extensiones/declaracion.php. OJO: el
       TOTAL A PAGAR (renglón 38) vive en dec_ValorConcepto20, no en la 17; es
       también el valor que lleva el código de barras de la declaración. */
    $mapa = [
        'INDUSTRIA Y COMERCIO'               => 1,
        'AVISOS Y TABLEROS'                  => 2,
        'SOBRETASA BOMBERIL'                 => 3,
        'TOTAL IMPUESTO A CARGO'             => 4,
        'VALOR DE EXENCIÓN O EXONERACIÓN'    => 5,
        '(-) MENOS RETENCIONES'              => 6,
        '(-) MENOS AUTORRETENCIONES'         => 7,
        '(-) ANTICIPO AÑO ANTERIOR'          => 8,
        '(+) ANTICIPO AÑO SIGUIENTE'         => 9,
        'SANCIONES'                          => 10,
        'SALDO A FAVOR VIGENCIAS ANTERIORES' => 11,
        'TOTAL SALDO A CARGO'                => 12,
        'TOTAL SALDO A FAVOR'                => 13,
        'VALOR A PAGAR'                      => 14,
        'DESCUENTO POR PRONTO PAGO'          => 15,
        'INTERESES DE MORA'                  => 16,
    ];
    foreach ($mapa as $nombre => $columna) {
        $conceptos[] = [$nombre, (float) ($row['dec_ValorConcepto' . $columna] ?? 0)];
    }
    $subtotal = (float) ($row['dec_ValorConcepto20'] ?? 0);
} else {
    $numero  = (string) ($row[$p . 'NumeroDeclaracion'] ?: $row[$p . 'Id']);
    $anio    = (int) $row[$p . 'Anio'];
    $per     = (int) $row[$p . 'Periodo'];
    $periodo = ($modulo === 'RETEICA' ? ($MESES[$per] ?? $per) : ($BIMESTRES[$per] ?? $per)) . ' ' . $anio;

    /* Los renglones salen del catálogo, igual que en el formulario: si mañana se
       renombra uno, el recibo lo refleja solo. Solo la liquidación: en
       autorretención los renglones 9 a 13 son ingresos, no conceptos a pagar. */
    $desde        = ($modulo === 'RETEICA') ? 14 : 15;
    $renglonTotal = ($modulo === 'RETEICA') ? 17 : 23;

    $stmt = $con->consultar(
        "SELECT ren_Codigo, ren_Nombre
           FROM ind_renglones_retencion
          WHERE ren_Modulo = ? AND ren_Anio = ? AND ren_Estado = 1
            AND ren_Codigo >= ? AND ren_Codigo <> ?
          ORDER BY ren_Orden",
        [$modulo, $anio, $desde, $renglonTotal]
    );
    while ($r = $con->obnerFila($stmt)) {
        $conceptos[] = [
            mb_strtoupper((string) $r['ren_Nombre'], 'UTF-8'),
            (float) ($row[$p . 'ValorConcepto' . (int) $r['ren_Codigo']] ?? 0),
        ];
    }
    $subtotal = (float) ($row[$p . 'ValorConcepto' . $renglonTotal] ?? 0);
}

if ($subtotal <= 0) {
    recibo_salir('Esta declaración no tiene valor a pagar: no necesita recibo de pago.');
}

/* ===========================================================================
   LO QUE ES IGUAL EN LOS TRES
   =========================================================================== */

/* "Pague antes de" es la MISMA fecha que va dentro del código de barras
   (segmento 96), para que el banco no rechace el pago por una fecha distinta a
   la impresa. Un ICA que todavía no vence vale hasta su fecha límite: antes de
   ese día no corren intereses. Vencido -y en retención y autorretención, que
   aún no tienen fecha límite-, sale de "Días de vigencia del recibo"
   (Municipio y bancos); sin configurar, vale el mismo día, como el formato de
   referencia. */
$icaAlDia = ($modulo === 'ICA' && !\erpsoftsas\VencimientoICA::vencida($anio));
if ($icaAlDia) {
    $venceIso = \erpsoftsas\VencimientoICA::fechaLimite($anio);
} else {
    $venceIso = \erpsoftsas\CodigoBarrasRecaudo::fechaVigencia() ?: date('Y-m-d');
}
$vence   = date('d/m/Y', strtotime($venceIso));
$emision = date('d/m/Y');

$esAlcaldia = in_array((int) ($_SESSION['id_Rol'] ?? 0), [1, 2], true);

$municipio  = 'MUNICIPIO DE ' . mb_strtoupper(MUNICIPIO_CIUDAD, 'UTF-8');
$ubicacion  = mb_strtoupper(MUNICIPIO_CIUDAD, 'UTF-8') . ' - ' . mb_strtoupper(MUNICIPIO_DEPARTAMENTO, 'UTF-8');
// La dirección de la Alcaldía sale del config del municipio (MUNICIPIO_DIRECCION);
// si no la trae, se omite la línea en vez de dejar un renglón en blanco.
$dirAlcaldia = defined('MUNICIPIO_DIRECCION') ? trim((string) MUNICIPIO_DIRECCION) : '';

$doc       = (string) ($contribuyente['ind_NumeroIdentificacion'] ?? '');
$nombre    = pdfret_nombreContribuyente($contribuyente);
$direccion = (string) ($contribuyente['ind_Direccion'] ?? '');

/* Intereses de mora hasta el "pague antes de": A MANO por ahora (Javier,
   2026-09-25: "que se deje de manera manual de momento"; no hay tabla de tasas
   ni fórmula). Sin ?intereses=, este mismo archivo pide el valor antes de
   armar el PDF (recibo_pedirIntereses):
     - ICA VENCIDA: a todos. El contribuyente también saca el recibo, pero le
       sale CON intereses (cliente, 2026-09-25): tiene que escribirlos, salvo
       que la declaración ya traiga los suyos (renglón 37). La Alcaldía los
       escribe si aplican (puede dejarlos en 0).
     - Retención y autorretención, sin fecha límite todavía: solo la Alcaldía,
       si aplican. El contribuyente saca su recibo directo.
     - ICA que todavía no vence: nadie; no lleva intereses.
   La regla es la misma de PSE (extensiones/pse/pagar.php y crearSesion.php). */
$intereses = 0.0;
$textoIntereses = trim((string) ($_GET['intereses'] ?? ''));

$icaVencida = ($modulo === 'ICA' && !$icaAlDia);
$declarados = (float) ($row[$p . 'ValorConcepto' . ['ICA' => 16, 'RETEICA' => 16, 'AUTORRETEICA' => 21][$modulo]] ?? 0);
$exige      = !$esAlcaldia && $icaVencida && \erpsoftsas\VencimientoICA::exigeIntereses($anio, $declarados);

$formulario = null;   // la casilla de los intereses, cuando la hay
if ($icaVencida || ($esAlcaldia && !$icaAlDia)) {
    $formulario = [
        'impuesto'   => $m['impuesto'],
        'numero'     => $numero,
        'periodo'    => $periodo,
        'nombre'     => $nombre,
        'doc'        => $doc,
        'subtotal'   => $subtotal,
        'declarados' => $declarados,
        'limite'     => $modulo === 'ICA'
            ? date('d/m/Y', strtotime(\erpsoftsas\VencimientoICA::fechaLimite($anio))) : '',
        'dias'       => $icaVencida ? \erpsoftsas\VencimientoICA::diasDeMora($anio, $venceIso) : 0,
        'exige'      => $exige,
        'vence'      => $vence,
        'modulo'     => $modulo,
        'id'         => (int) $row[$p . 'Id'],
        'municipio'  => $municipio,
    ];
}

if ($textoIntereses !== '') {
    $leidos = \erpsoftsas\VencimientoICA::leerIntereses($textoIntereses);
    if ($leidos === null) {
        $error = 'Intereses de mora no válidos: escriba el valor en pesos, sin decimales.';
        if ($formulario !== null) {
            recibo_pedirIntereses($formulario + ['error' => $error]);
        }
        recibo_salir($error, 400);
    }
    $intereses = (float) $leidos;
    if ($intereses > 0 && $icaAlDia) {
        recibo_salir('Esta declaración vence el ' . $vence . ': hasta ese día no lleva intereses de mora.', 400);
    }
    if ($intereses > 0 && $formulario === null) {
        recibo_salir('Los intereses de mora de este recibo los liquida la Alcaldía.', 403);
    }
    if ($intereses <= 0 && $exige) {
        recibo_pedirIntereses($formulario + ['error' => 'La declaración está vencida: escriba los intereses de mora.']);
    }
} elseif ($formulario !== null) {
    recibo_pedirIntereses($formulario);
}
$total = $subtotal + $intereses;

$contenidoBarras = \erpsoftsas\CodigoBarrasRecaudo::construir($numero, $total, $venceIso);

// El funcionario que imprime el recibo; vacío si lo genera el contribuyente.
$liquidador = '';
if ($esAlcaldia) {
    $u = $con->obnerFila($con->consultar(
        "SELECT usu_Nombres, usu_Apellidos FROM conf_usuarios WHERE usu_Id = ?",
        [(int) ($_SESSION['id_usuario'] ?? 0)]
    ));
    $liquidador = mb_strtoupper(trim(preg_replace('/\s+/', ' ',
        ($u['usu_Nombres'] ?? '') . ' ' . ($u['usu_Apellidos'] ?? ''))), 'UTF-8');
}
$lineaLegible    = $contenidoBarras !== null
    ? \erpsoftsas\CodigoBarrasRecaudo::textoLegible($numero, $total, $venceIso)
    : '';

$GRIS  = '#dfe3ea';   // cabeceras, como el formato de referencia
$SUAVE = '#f3f5f8';   // columna de valores

/**
 * Los renglones de datos que llevan ambas copias: cada uno es una cabecera gris
 * y sus valores, con sus propios anchos (el primero ya no lleva establecimiento
 * y tiene una columna menos que el segundo).
 */
function recibo_tablaDatos(array $renglones, $gris)
{
    $html = '<table border="1" cellpadding="2.5" width="100%">';
    foreach ($renglones as [$cabeceras, $valores, $anchos]) {
        foreach ([[$cabeceras, true], [$valores, false]] as [$celdas, $esCabecera]) {
            $html .= '<tr' . ($esCabecera ? ' bgcolor="' . $gris . '"' : '') . '>';
            foreach ($celdas as $i => $c) {
                $html .= '<td width="' . $anchos[$i] . '" align="center">'
                       . ($esCabecera ? '<b>' . recibo_h($c) . '</b>' : $c) . '</td>';
            }
            $html .= '</tr>';
        }
    }
    return $html . '</table>';
}

// El renglón de la declaración, igual en las dos copias (en la del banco, en negrita).
$ANCHOS_DECLARACION = ['17%', '33%', '25%', '25%'];
$cabDeclaracion     = ['N° Declaración', 'Período de pago', 'Pague antes de', 'Valor a pagar'];

$pdf = pdfret_nuevoPdf();
$pdf->SetTitle('Recibo de pago ' . $numero);

/* ---------- COPIA DEL CONTRIBUYENTE ---------- */

$html = '
<style> td { font-size: 7.5px; } </style>
<table cellpadding="3" width="100%">
<tr>
    <td width="12%" align="center"><img src="' . pdfret_rutaEscudo() . '" width="46"></td>
    <td width="33%" align="center"><b>' . recibo_h($municipio) . '</b><br>NIT ' . recibo_h(MUNICIPIO_NIT)
        . ($dirAlcaldia !== '' ? '<br>' . recibo_h($dirAlcaldia) : '') . '<br>' . recibo_h($ubicacion) . '</td>
    <td width="55%" align="right">
        <span style="font-size:11px;"><b>RECIBO DE PAGO</b></span><br>
        <span style="font-size:9px;"><b>' . recibo_h($m['impuesto']) . '</b></span><br>
        <span style="font-size:9px;">N° Factura &nbsp;<b style="font-size:11px;">' . recibo_h($numero) . '</b></span><br>
        <span style="font-size:8px;">Fecha de emisión &nbsp;' . recibo_h($emision) . '</span>
    </td>
</tr>
</table>'
. recibo_tablaDatos([
    [['Nit./C.C.', 'Contribuyente', 'Dirección'],
     [recibo_h($doc), recibo_h($nombre), recibo_h($direccion)],
     ['17%', '43%', '40%']],
    [$cabDeclaracion,
     [recibo_h($numero), recibo_h($periodo), recibo_h($vence), '<b>' . recibo_pesos($total) . '</b>'],
     $ANCHOS_DECLARACION],
], $GRIS);
$pdf->writeHTML($html, true, false, true, false, '');

// Conceptos con la cuadrícula gris clara del formato de referencia. Con
// border="1" TCPDF la dibuja negra sin importar SetDrawColor: el color va en el
// estilo de cada celda.
$LINEA = 'border: 0.3px solid #c3c8d0;';
$fila = function ($concepto, $valor, $estilo = '') use ($LINEA, $SUAVE) {
    return '<tr><td width="72%" align="right" style="' . $LINEA . $estilo . '">' . $concepto . '</td>'
         . '<td width="28%" align="right" bgcolor="' . $SUAVE . '" style="' . $LINEA . $estilo . '">'
         . recibo_pesos($valor) . '</td></tr>';
};
$filas = '';
foreach ($conceptos as $c) {
    $filas .= $fila(recibo_h($c[0]), $c[1]);
}
$filas .= $fila('<b>SUBTOTAL</b>', $subtotal, 'font-weight:bold;')
        . $fila('INTERESES DE MORA AL ' . recibo_h($vence), $intereses);

$pdf->writeHTML(
    '<style> td { font-size: 7.5px; } </style>
    <table border="0" cellpadding="1.8" width="100%">' . $filas . '
    <tr bgcolor="' . $GRIS . '">
        <td align="right" style="font-size:10px; ' . $LINEA . '"><b>TOTAL A PAGAR</b></td>
        <td align="right" style="font-size:10px; ' . $LINEA . '"><b>' . recibo_pesos($total) . '</b></td>
    </tr>
    <tr><td colspan="2" align="center" style="' . $LINEA . '"><b>SON: ' . recibo_h(pdfret_numeroALetras($total)) . '</b></td></tr>
    </table>',
    true, false, true, false, ''
);

if ($liquidador !== '') {
    $pdf->SetFont('helvetica', '', 7.5);
    $pdf->SetXY($pdf->getMargins()['left'] + 2, $pdf->GetY() - DESFASE_GETY_RET + 1.5);
    $pdf->Cell(0, 4, 'LIQUIDADOR  ' . $liquidador, 0, 1, 'L');
}

/* ---------- LÍNEA DE CORTE ---------- */

$margenes = $pdf->getMargins();
$xIzq     = $margenes['left'];
$ancho    = $pdf->getPageWidth() - $margenes['left'] - $margenes['right'];

$yCorte = $pdf->GetY() + 2;
$pdf->SetLineStyle(['width' => 0.3, 'dash' => '3,2', 'color' => [110, 110, 110]]);
$pdf->Line($xIzq, $yCorte, $xIzq + $ancho, $yCorte);
$pdf->SetLineStyle(['width' => 0.2, 'dash' => 0, 'color' => [0, 0, 0]]);
$pdf->SetFont('helvetica', '', 6);
$pdf->SetTextColor(110, 110, 110);
$pdf->SetXY($xIzq, $yCorte + 0.6);
$pdf->Cell($ancho, 3, 'Desprenda por esta línea: la parte de abajo queda en el banco', 0, 1, 'C');
$pdf->SetTextColor(0, 0, 0);

/* ---------- COPIA DEL BANCO ---------- */

$pdf->SetY($yCorte + 6);
$pdf->writeHTML(
    '<style> td { font-size: 7.5px; } </style>
    <table cellpadding="1" width="100%"><tr><td align="center" style="font-size:9px;">
        <b>BANCO</b><br><b>' . recibo_h($municipio) . '</b><br><b>' . recibo_h($m['impuesto']) . '</b>
    </td></tr></table>'
    . recibo_tablaDatos([
        [['Nit./C.C.', 'Contribuyente', 'N° Factura'],
         [recibo_h($doc), recibo_h($nombre), '<b>' . recibo_h($numero) . '</b>'],
         ['17%', '58%', '25%']],
        [$cabDeclaracion,
         ['<b>' . recibo_h($numero) . '</b>', '<b>' . recibo_h($periodo) . '</b>',
          '<b>' . recibo_h($vence) . '</b>', '<b>' . recibo_pesos($total) . '</b>'],
         $ANCHOS_DECLARACION],
    ], $GRIS),
    true, false, true, false, ''
);

$yBanco      = $pdf->GetY() - DESFASE_GETY_RET + 2;
$anchoSello  = 60;
$anchoBarras = $ancho - $anchoSello - 8;

$pdf->SetFont('helvetica', 'B', 7.5);
$pdf->SetXY($xIzq, $yBanco);
$pdf->Cell($anchoBarras, 4, $municipio . ' NIT ' . MUNICIPIO_NIT, 0, 0, 'L');

// Recuadro para el sello del banco.
$pdf->Rect($xIzq + $ancho - $anchoSello, $yBanco, $anchoSello, 30);
$pdf->SetXY($xIzq + $ancho - $anchoSello, $yBanco + 1);
$pdf->Cell($anchoSello, 4, 'SELLO BANCO', 0, 0, 'C');

if ($contenidoBarras !== null) {
    // write1DBarcode (vectorial), nunca como imagen: ver la trampa 5 de
    // pdfRetenciones.php. La línea legible es la que el cajero digita si el
    // escáner falla; en GS1-128 los FNC1 no se imprimen, por eso va aparte.
    $pdf->write1DBarcode(
        $contenidoBarras, 'C128',
        $xIzq, $yBanco + 6, $anchoBarras, 18, '',
        ['position' => '', 'border' => false, 'padding' => 0,
         'fgcolor' => [0, 0, 0], 'bgcolor' => false, 'text' => false, 'stretch' => true],
        'N'
    );
    $pdf->SetFont('helvetica', '', 6.5);
    $pdf->SetXY($xIzq, $yBanco + 24.5);
    $pdf->Cell($anchoBarras, 3, $lineaLegible, 0, 0, 'C');
} else {
    $pdf->SetFont('helvetica', 'I', 7);
    $pdf->SetXY($xIzq, $yBanco + 11);
    $pdf->MultiCell($anchoBarras, 4,
        "Código de barras no disponible: falta el EAN de recaudo del municipio\n"
        . "(Parámetros ICA > Municipio y bancos).", 0, 'C');
}

if ($liquidador !== '') {
    $pdf->SetFont('helvetica', '', 7.5);
    $pdf->SetXY($xIzq + 2, $yBanco + 33);
    $pdf->Cell($ancho, 4, 'LIQUIDADOR  ' . $liquidador, 0, 1, 'L');
}

$pdf->Output('Recibo_de_pago_' . $modulo . '_' . $numero . '.pdf', 'I');
