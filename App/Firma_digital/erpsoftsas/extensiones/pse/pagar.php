<?php

/**
 * Resumen de pago ANTES de redirigir al banco (exigido por la certificacion
 * WC de AvalPay):
 *   - item 4    : mostrar el monto total a pagar antes de la redireccion.
 *   - item 12.1 : mostrar el logo de AvalPay y que el usuario acepte la
 *                 politica de tratamiento de datos antes de pagar.
 *   - item 4.1  : un solo envio aunque se haga clic varias veces.
 *   - item 4.3  : si ya hay un pago PENDIENTE, avisarlo con su referencia y
 *                 contacto, en vez de crear otra sesion.
 *
 * Sirve a los TRES módulos (ICA, Retención, Autorretención) según ?modulo=.
 * El pago en si lo crea crearSesion.php, al que este resumen envia por POST.
 *
 * GET: id (o dec_Id), modulo
 */
include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.conexionSqlServer.php';
require_once SERVER . '/business/class.placetopay.php';
require_once SERVER . '/business/class.pseModulo.php';

$configPath = dirname(dirname(dirname(__DIR__))) . '/config.municipio.php';
if (!file_exists($configPath)) {
    $configPath = dirname(dirname(__DIR__)) . '/config.municipio.php';
}
if (file_exists($configPath)) {
    require_once $configPath;
}

$con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

$color   = defined('MUNICIPIO_COLOR') ? MUNICIPIO_COLOR : '#1fa49d';
$muni    = defined('MUNICIPIO_NOMBRE') ? MUNICIPIO_NOMBRE : 'Alcaldía';
// Logo de AvalPay para el resumen (Guia WC, item 12.1). En produccion el banco
// suele entregar la URL del logo productivo; si cambia, se ajusta aqui.
$logoAvalPay = 'https://placetopay-static-test-bucket.s3.us-east-2.amazonaws.com/avalpaycenter-com/logos/Logo%20Avalpay.svg';

$modulo = $_GET['modulo'] ?? 'ica';
$m      = \erpsoftsas\PseModulo::get($modulo);
$modulo = $m['clave'];
$id     = (int) ($_GET['id'] ?? $_GET['dec_Id'] ?? 0);

/** Cierra la pagina con un mensaje simple centrado. */
function pantalla($titulo, $html, $color, $muni) {
    ?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($titulo) ?> - <?= htmlspecialchars($muni) ?></title>
<style>
  :root { --c: <?= htmlspecialchars($color) ?>; }
  * { box-sizing: border-box; }
  body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f6f8; color: #333;
         margin: 0; padding: 24px 16px; display: flex; justify-content: center; }
  .tarjeta { background: #fff; max-width: 480px; width: 100%; border-radius: 10px;
             box-shadow: 0 4px 20px rgba(0,0,0,.08); overflow: hidden; }
  .cab { background: var(--c); color: #fff; padding: 18px 24px; font-size: 18px; font-weight: 600; }
  .cuerpo { padding: 24px; }
  .fila { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; font-size: 15px; }
  .fila .k { color: #666; }
  .fila .v { font-weight: 600; text-align: right; }
  .total { font-size: 22px; color: var(--c); }
  .aval { text-align: center; margin: 18px 0 6px; }
  .aval img { height: 34px; }
  .aval small { display: block; color: #888; margin-top: 4px; font-size: 11px; }
  .politica { font-size: 13px; color: #555; background: #f7f9fa; border: 1px solid #e6eaec;
              border-radius: 6px; padding: 12px; margin: 16px 0; }
  .politica label { display: flex; gap: 8px; align-items: flex-start; cursor: pointer; }
  .politica details { margin-top: 8px; }
  .politica summary { cursor: pointer; color: var(--c); }
  .btn { display: block; width: 100%; border: 0; padding: 14px; font-size: 16px; font-weight: 600;
         color: #fff; background: var(--c); border-radius: 6px; cursor: pointer; }
  .btn:disabled { opacity: .55; cursor: not-allowed; }
  .btn.sec { background: #6c757d; margin-top: 10px; text-align: center; text-decoration: none; }
  .aviso { padding: 14px; border-radius: 6px; font-size: 14px; line-height: 1.5; }
  .aviso.pend { background: #fff6e5; border: 1px solid #f0d38a; color: #7a5b00; }
  .aviso.info { background: #eef4f5; border: 1px solid #cfe0e2; color: #33555a; }
  .ref { font-family: monospace; font-weight: 700; }
  .mora { padding: 10px 0; border-bottom: 1px solid #eee; }
  .mora label { display: block; font-weight: 600; margin-bottom: 6px; }
  .mora input { width: 100%; font-size: 18px; padding: 10px 12px; border: 1px solid #c8d0d6;
                border-radius: 6px; text-align: right; box-sizing: border-box; }
  .mora small { display: block; color: #666; margin-top: 6px; font-size: 12px; line-height: 1.4; }
  .aviso-mora { font-size: 13px; color: #7a5b00; background: #fff6e5; border: 1px solid #f0d38a;
                border-radius: 6px; padding: 8px 10px; margin-bottom: 10px; }
</style>
</head>
<body>
  <div class="tarjeta">
    <div class="cab"><?= htmlspecialchars($muni) ?> · Pago en línea</div>
    <div class="cuerpo"><?= $html ?></div>
  </div>
</body>
</html><?php
    exit;
}

// Sin convenio configurado no hay pago que ofrecer.
if (!PlacetoPay::configurado()) {
    http_response_code(503);
    pantalla('Pago no disponible',
        '<div class="aviso info">El pago en línea todavía no está disponible. '
      . 'Puede pagar en el banco con el código de barras impreso en su declaración.</div>',
        $color, $muni);
}

// Solo quien ve el botón, y sobre sus propias declaraciones (la Alcaldía, sobre
// cualquiera): ver PseModulo::motivoParaNoPagar.
$motivo = \erpsoftsas\PseModulo::motivoParaNoPagar($con, $m, $id);
if ($motivo !== null) {
    http_response_code(403);
    pantalla('Pago no disponible', '<div class="aviso info">' . htmlspecialchars($motivo) . '</div>', $color, $muni);
}

$col = [
    'numero' => $m['numero'], 'valor' => $m['valor'], 'pagado' => $m['pagado'],
    'estado' => $m['estado'], 'req' => \erpsoftsas\PseModulo::colRequestId($m),
    'est' => \erpsoftsas\PseModulo::colEstado($m),
];
$row = $con->obnerFila($con->consultar(
    "SELECT {$col['numero']} AS numero, {$col['valor']} AS valor, {$col['pagado']} AS pagado,
            {$col['estado']} AS estado, {$col['req']} AS pse_req, {$col['est']} AS pse_est
     FROM {$m['tabla']} WHERE {$m['pk']} = ?",
    [$id]
));

if (!$row) {
    http_response_code(404);
    pantalla('No encontrada', '<div class="aviso info">Declaración no encontrada.</div>', $color, $muni);
}

$referencia = (string) $row['numero'];
$valor      = (float) $row['valor'];
$valorFmt   = '$ ' . number_format($valor, 0, ',', '.');

if ((int) $row['pagado'] === 1) {
    pantalla('Ya pagada',
        '<div class="aviso info">Esta declaración (referencia <span class="ref">'
      . htmlspecialchars($referencia) . '</span>) ya está registrada como pagada.</div>',
        $color, $muni);
}

if ((int) ($row['estado'] ?? 0) !== 2) {
    pantalla('Aún no presentada',
        '<div class="aviso info">Esta declaración todavía no está presentada. '
      . 'Debe presentarla antes de pagar.</div>', $color, $muni);
}

if ($valor <= 0) {
    pantalla('Sin valor a pagar',
        '<div class="aviso info">El valor a pagar de esta declaración es $0, no aplica pago en línea.</div>',
        $color, $muni);
}

/*
 * ICA VENCIDA: se paga CON intereses de mora, escritos a mano (Javier y el
 * cliente, 2026-09-25), con la misma regla del recibo de pago
 * (business/class.vencimientoICA.php): obligatorios, salvo que la declaracion
 * ya traiga los suyos en el renglon 37. crearSesion.php los vuelve a validar y
 * cobra el total mas los intereses. Retencion y autorretencion aun no tienen
 * fecha limite: no llevan la casilla.
 */
$mora = null;
if ($modulo === 'ica') {
    include_once SERVER . '/business/class.vencimientoICA.php';
    $ica = $con->obnerFila($con->consultar(
        "SELECT dec_AnioDeclaracion, dec_ValorConcepto16 FROM ind_declaraciones_ica WHERE dec_Id = ?", [$id]
    ));
    $anioIca = (int) ($ica['dec_AnioDeclaracion'] ?? 0);
    if ($ica && \erpsoftsas\VencimientoICA::vencida($anioIca)) {
        $mora = [
            'limite' => date('d/m/Y', strtotime(\erpsoftsas\VencimientoICA::fechaLimite($anioIca))),
            'dias'   => \erpsoftsas\VencimientoICA::diasDeMora($anioIca, \erpsoftsas\VencimientoICA::hoy()),
            'exige'  => \erpsoftsas\VencimientoICA::exigeIntereses($anioIca, $ica['dec_ValorConcepto16'] ?? 0),
        ];
    }
}

// item 4.3: si ya hay una sesion y el ultimo estado conocido es PENDIENTE, no
// se crea otra: se informa. "Verificar estado" fuerza una consulta (retorno.php).
if (!empty($row['pse_req']) && strtoupper((string) $row['pse_est']) === 'PENDING') {
    $retorno = 'retorno.php?modulo=' . urlencode($modulo) . '&id=' . $id;
    pantalla('Pago en proceso',
        '<div class="aviso pend">Su pago con referencia <span class="ref">' . htmlspecialchars($referencia)
      . '</span>' . ($mora === null ? ' por <b>' . $valorFmt . '</b>' : '')
      . ' está en estado <b>PENDIENTE</b>: aún no recibimos '
      . 'la confirmación de su entidad financiera. Espere unos minutos y vuelva a consultar; '
      . 'si el dinero fue debitado, el pago se confirmará automáticamente. Si tiene dudas, '
      . 'comuníquese con ' . htmlspecialchars($muni) . ' indicando su referencia.</div>'
      . '<a class="btn" href="' . htmlspecialchars($retorno) . '">Verificar estado del pago</a>',
        $color, $muni);
}

// Resumen normal + confirmacion.
ob_start();
?>
<div class="fila"><span class="k">Concepto</span><span class="v"><?= htmlspecialchars($m['etiqueta']) ?></span></div>
<div class="fila"><span class="k">Referencia</span><span class="v ref"><?= htmlspecialchars($referencia) ?></span></div>
<div class="fila"><span class="k">Entidad</span><span class="v"><?= htmlspecialchars($muni) ?></span></div>
<?php if ($mora): ?>
<div class="fila"><span class="k">Valor de la declaración</span><span class="v"><?= $valorFmt ?></span></div>
<?php endif; ?>
<?php if ($mora): ?>
  <div class="mora">
    <label for="intereses">Intereses de mora a hoy</label>
    <input id="intereses" name="intereses" form="formPago" inputmode="numeric" autocomplete="off"
           value="<?= $mora['exige'] ? '' : '0' ?>" placeholder="Escriba el valor"<?= $mora['exige'] ? ' required' : '' ?>>
    <small>La declaración venció el <?= htmlspecialchars($mora['limite']) ?>: se paga con los intereses de mora
      de <?= (int) $mora['dias'] ?> día<?= (int) $mora['dias'] === 1 ? '' : 's' ?>. En pesos, sin decimales.<?php if (!$mora['exige']): ?>
      La declaración ya trae intereses; escriba solo lo que falte (o 0).<?php else: ?> Si no sabe cuánto son,
      comuníquese con <?= htmlspecialchars($muni) ?>.<?php endif; ?></small>
  </div>
<?php endif; ?>
<div class="fila"><span class="k">Total a pagar</span><span class="v total" id="totalPse"><?= $valorFmt ?></span></div>

<div class="aval">
  <img src="<?= htmlspecialchars($logoAvalPay) ?>" alt="AvalPay" onerror="this.style.display='none'">
  <small>Pago seguro procesado por AvalPay (PSE)</small>
</div>

<form method="post" action="crearSesion.php" id="formPago">
  <input type="hidden" name="id" value="<?= $id ?>">
  <input type="hidden" name="modulo" value="<?= htmlspecialchars($modulo) ?>">
  <div class="politica">
    <label>
      <input type="checkbox" name="acepto" value="1" id="acepto" required>
      <span>He leído y acepto la
      <a href="faq.php" target="_blank" style="color:var(--c);font-weight:600;">política de tratamiento de datos y los términos y condiciones</a>
      de <?= htmlspecialchars($muni) ?> y autorizo el procesamiento del pago a través de AvalPay (PSE).</span>
    </label>
  </div>
  <div class="aviso-mora" id="avisoMora" hidden>Escriba los intereses de mora para continuar.</div>
  <button type="submit" class="btn" id="btnPagar" disabled>Pagar con PSE</button>
  <a class="btn sec" href="#" onclick="window.close(); setTimeout(function () { history.back(); }, 200); return false;">Cancelar</a>
</form>

<script>
  // item 4.1: el boton solo se habilita al aceptar, y se deshabilita al enviar
  // para que multiples clics no creen varias sesiones.
  var chk = document.getElementById('acepto');
  var btn = document.getElementById('btnPagar');
  var frm = document.getElementById('formPago');
  // Intereses de mora (ICA vencida): mismos pesos enteros que el recibo de pago.
  var mora = document.getElementById('intereses');
  var aviso = document.getElementById('avisoMora');
  var exige = <?= ($mora && $mora['exige']) ? 'true' : 'false' ?>;
  var valorDeclaracion = <?= (int) round($valor) ?>;
  function pesos(n) { return '$ ' + String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }
  // Pesos enteros: lo que vaya después de la coma (centavos) se ve pero no se cobra.
  function digitosMora() { return mora ? mora.value.split(',')[0].replace(/\D/g, '').replace(/^0+(?=\d)/, '').slice(0, 10) : ''; }
  function faltaMora() { return exige && !(Number(digitosMora() || 0) > 0); }
  function listo() { return chk.checked && !faltaMora(); }
  function refrescar() {
    btn.disabled = !listo();
    aviso.hidden = !(chk.checked && faltaMora());
  }
  if (mora) {
    mora.addEventListener('input', function () {
      var partes = mora.value.split(',');
      var d = digitosMora();
      var centavos = partes.length > 1 ? ',' + partes.slice(1).join('').replace(/\D/g, '').slice(0, 2) : '';
      mora.value = (d === '' ? '' : pesos(d).slice(2)) + centavos;
      document.getElementById('totalPse').textContent = pesos(valorDeclaracion + Number(d || 0));
      refrescar();
    });
    // Lo pegado con punto decimal ("1234.56") pasa a coma, que es como se descarta.
    mora.addEventListener('paste', function (e) {
      var t = String((e.clipboardData || window.clipboardData).getData('text') || '').trim();
      e.preventDefault();
      if (t.indexOf(',') === -1 && (t.match(/\./g) || []).length === 1 && /\.\d{1,2}$/.test(t)) { t = t.replace('.', ','); }
      mora.value = t;
      mora.dispatchEvent(new Event('input'));
    });
  }
  chk.addEventListener('change', function () {
    refrescar();
    if (chk.checked && faltaMora()) { mora.focus(); }
  });
  // Al volver con "Atrás" desde el banco, el navegador restaura la página con el
  // botón en "Redirigiendo…": se deja como corresponde.
  window.addEventListener('pageshow', function (e) {
    if (e.persisted) { btn.textContent = 'Pagar con PSE'; refrescar(); }
  });
  frm.addEventListener('submit', function () {
    btn.disabled = true;
    btn.textContent = 'Redirigiendo al banco…';
  });
</script>
<?php
$html = ob_get_clean();
pantalla('Resumen de pago', $html, $color, $muni);
