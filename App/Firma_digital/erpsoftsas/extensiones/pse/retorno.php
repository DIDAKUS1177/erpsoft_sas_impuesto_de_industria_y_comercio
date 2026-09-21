<?php

/**
 * A donde PlacetoPay redirige al usuario cuando da "volver al comercio".
 * Consulta el estado real de la sesion (no confia en nada de la URL) y
 * actualiza la declaracion antes de mostrar el resultado.
 *
 * Muestra el detalle de la transaccion (Guia WC, item 12.4): referencia,
 * estado final, fecha, valor y moneda. Sirve a los tres modulos segun ?modulo=.
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

$color = defined('MUNICIPIO_COLOR') ? MUNICIPIO_COLOR : '#1fa49d';
$muni  = defined('MUNICIPIO_NOMBRE') ? MUNICIPIO_NOMBRE : 'Alcaldía';

$m   = \erpsoftsas\PseModulo::get($_GET['modulo'] ?? 'ica');
$id  = (int) ($_GET['id'] ?? $_GET['dec_Id'] ?? 0);

$col = [
    'numero' => $m['numero'], 'valor' => $m['valor'], 'pagado' => $m['pagado'],
    'req' => \erpsoftsas\PseModulo::colRequestId($m),
];
$row = $con->obnerFila($con->consultar(
    "SELECT {$col['numero']} AS numero, {$col['req']} AS pse_req, {$col['valor']} AS valor, {$col['pagado']} AS pagado
     FROM {$m['tabla']} WHERE {$m['pk']} = ?",
    [$id]
));

$mensaje    = '';
$aprobado   = false;
$referencia = $row['numero'] ?? '';
$valor      = (float) ($row['valor'] ?? 0);
$estado     = '';
$fechaIso   = '';

if (!$row || empty($row['pse_req'])) {
    $mensaje = 'No se encontró un pago PSE iniciado para esta declaración.';
} elseif ((int) $row['pagado'] === 1) {
    $aprobado = true;
    $estado   = 'APPROVED';
    $mensaje  = 'Esta declaración ya estaba registrada como pagada.';
} else {
    try {
        $respuesta = PlacetoPay::consultarSesion($row['pse_req']);
        $info = PlacetoPay::interpretarRespuesta($respuesta);

        // Se guarda el estado venga como venga; solo se marca pagada si el
        // banco la aprobo. El descriptor $m dice a que tabla/columnas escribir.
        PlacetoPay::aplicarADeclaracion($con, $id, $info, $valor, $m);

        $estado   = $info['estado'];
        $fechaIso = $info['fecha'] ?? '';

        if ($info['aprobado']) {
            $aprobado = true;
            $mensaje = 'Pago aprobado. Gracias.';
        } elseif ($info['estado'] === 'PENDING') {
            $mensaje = 'El pago quedó en proceso. En cuanto el banco confirme, se actualizará automáticamente (puede tardar unos minutos).';
        } else {
            $mensaje = 'El pago no fue aprobado. Puede intentarlo de nuevo desde su declaración.';
            if (!empty($info['mensaje'])) {
                $mensaje .= ' (' . $info['mensaje'] . ')';
            }
        }
    } catch (Exception $e) {
        $mensaje = 'No se pudo confirmar el estado del pago en este momento. Si el pago sí se realizó, quedará confirmado automáticamente en las próximas horas.';
    }
}

// Etiqueta legible del estado.
$mapaEstado = [
    'APPROVED' => 'Aprobado', 'PENDING' => 'Pendiente', 'REJECTED' => 'Rechazado',
    'EXPIRED'  => 'Expirado', 'FAILED' => 'Fallido',
];
$estadoTxt = $mapaEstado[$estado] ?? ($estado !== '' ? $estado : '—');
$valorFmt  = $valor > 0 ? '$ ' . number_format($valor, 0, ',', '.') . ' COP' : '—';
$fechaTxt  = $fechaIso !== '' ? date('Y-m-d H:i', strtotime($fechaIso)) : date('Y-m-d H:i');
$claseEstado = $aprobado ? 'ok' : ($estado === 'PENDING' ? 'pendiente' : 'error');

?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Resultado del pago - <?= htmlspecialchars($muni) ?></title>
<style>
  :root { --c: <?= htmlspecialchars($color) ?>; }
  body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f6f8; color: #333;
         margin: 0; padding: 40px 16px; }
  .tarjeta { background: #fff; max-width: 480px; margin: 0 auto; border-radius: 10px;
             box-shadow: 0 4px 20px rgba(0,0,0,.08); overflow: hidden; text-align: center; }
  .cab { padding: 28px 24px 8px; }
  .icono { font-size: 52px; line-height: 1; }
  .ok { color: var(--c); } .pendiente { color: #d9a441; } .error { color: #c0392b; }
  h2 { margin: 10px 0 4px; }
  .msg { padding: 0 24px; color: #555; font-size: 15px; }
  .detalle { text-align: left; margin: 20px 24px; border: 1px solid #eee; border-radius: 8px; }
  .detalle .fila { display: flex; justify-content: space-between; padding: 10px 14px; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
  .detalle .fila:last-child { border-bottom: 0; }
  .detalle .k { color: #777; } .detalle .v { font-weight: 600; text-align: right; }
  .ref { font-family: monospace; }
  .chip { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 13px; font-weight: 700; }
  .chip.ok { background: #e5f5f3; color: var(--c); }
  .chip.pendiente { background: #fdf3dd; color: #a5791f; }
  .chip.error { background: #fbe7e4; color: #c0392b; }
  a.btn { display: inline-block; margin: 8px 0 26px; padding: 11px 24px; background: var(--c);
          color: #fff; text-decoration: none; border-radius: 6px; }
</style>
</head>
<body>
  <div class="tarjeta">
    <div class="cab">
      <div class="icono <?= $claseEstado ?>"><?= $aprobado ? '✔' : ($estado === 'PENDING' ? '⏳' : '✕') ?></div>
      <h2><?= $aprobado ? 'Pago procesado' : 'Estado del pago' ?></h2>
    </div>
    <p class="msg"><?= htmlspecialchars($mensaje) ?></p>
    <div class="detalle">
      <div class="fila"><span class="k">Concepto</span><span class="v"><?= htmlspecialchars($m['etiqueta']) ?></span></div>
      <div class="fila"><span class="k">Referencia</span><span class="v ref"><?= htmlspecialchars($referencia) ?></span></div>
      <div class="fila"><span class="k">Estado</span><span class="v"><span class="chip <?= $claseEstado ?>"><?= htmlspecialchars($estadoTxt) ?></span></span></div>
      <div class="fila"><span class="k">Fecha y hora</span><span class="v"><?= htmlspecialchars($fechaTxt) ?></span></div>
      <div class="fila"><span class="k">Valor</span><span class="v"><?= htmlspecialchars($valorFmt) ?></span></div>
      <div class="fila"><span class="k">Entidad</span><span class="v"><?= htmlspecialchars($muni) ?></span></div>
    </div>
    <a class="btn" href="javascript:window.close();">Cerrar</a>
  </div>
</body>
</html>
