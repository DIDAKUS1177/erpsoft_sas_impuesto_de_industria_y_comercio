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

// item 4.3: si ya hay una sesion y el ultimo estado conocido es PENDIENTE, no
// se crea otra: se informa. "Verificar estado" fuerza una consulta (retorno.php).
if (!empty($row['pse_req']) && strtoupper((string) $row['pse_est']) === 'PENDING') {
    $retorno = 'retorno.php?modulo=' . urlencode($modulo) . '&id=' . $id;
    pantalla('Pago en proceso',
        '<div class="aviso pend">Su pago con referencia <span class="ref">' . htmlspecialchars($referencia)
      . '</span> por <b>' . $valorFmt . '</b> está en estado <b>PENDIENTE</b>: aún no recibimos '
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
<div class="fila"><span class="k">Total a pagar</span><span class="v total"><?= $valorFmt ?></span></div>

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
  <button type="submit" class="btn" id="btnPagar" disabled>Pagar con PSE</button>
  <a class="btn sec" href="javascript:window.close();">Cancelar</a>
</form>

<script>
  // item 4.1: el boton solo se habilita al aceptar, y se deshabilita al enviar
  // para que multiples clics no creen varias sesiones.
  var chk = document.getElementById('acepto');
  var btn = document.getElementById('btnPagar');
  var frm = document.getElementById('formPago');
  chk.addEventListener('change', function () { btn.disabled = !chk.checked; });
  frm.addEventListener('submit', function () {
    btn.disabled = true;
    btn.textContent = 'Redirigiendo al banco…';
  });
</script>
<?php
$html = ob_get_clean();
pantalla('Resumen de pago', $html, $color, $muni);
