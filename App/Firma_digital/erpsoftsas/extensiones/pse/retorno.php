<?php

/**
 * A donde la pasarela devuelve al usuario despues de pagar. Consulta el estado
 * real del pago (no confia en nada de la URL) y actualiza la declaracion antes
 * de mostrar el resultado.
 *
 * Muestra el detalle de la transaccion (Guia WC, item 12.4): referencia,
 * estado final, fecha, valor y moneda. Sirve a los tres modulos segun ?modulo=.
 *
 * GET PlacetoPay: id (o dec_Id) = la declaracion, modulo
 * GET Wompi (042): decl = la declaracion, modulo, ref = el intento, t = la firma
 *     del enlace (PagoWompi::urlRetorno), e id = la transaccion, que agrega
 *     Wompi (por eso la declaracion no va en "id").
 */
include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.conexionSqlServer.php';
require_once SERVER . '/business/class.placetopay.php';
require_once SERVER . '/business/class.pseModulo.php';
require_once SERVER . '/business/class.pagoDeclaracion.php';
require_once SERVER . '/business/class.pasarela.php';

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

$m       = \erpsoftsas\PseModulo::get($_GET['modulo'] ?? 'ica');
// La rama la decide la pasarela de la entidad, no la URL (revision 2026-10-02).
$esWompi = \erpsoftsas\Pasarela::esWompi();
$id      = $esWompi ? (int) ($_GET['decl'] ?? 0) : (int) ($_GET['id'] ?? $_GET['dec_Id'] ?? 0);

$col = [
    'numero' => $m['numero'], 'valor' => $m['valor'], 'pagado' => $m['pagado'],
    'req' => \erpsoftsas\PseModulo::colRequestId($m),
];
$row = $con->obnerFila($con->consultar(
    "SELECT {$col['numero']} AS numero, {$col['req']} AS pse_req, {$col['valor']} AS valor, {$col['pagado']} AS pagado,
            {$m['prefijo']}_ValorPago AS valor_pagado
     FROM {$m['tabla']} WHERE {$m['pk']} = ?",
    [$id]
));

$mensaje    = '';
$aprobado   = false;
$referencia = $row['numero'] ?? '';
$valor      = (float) ($row['valor'] ?? 0);
$estado     = '';
$fechaIso   = '';

if ($esWompi) {
    /*
     * WOMPI (042, revision 2026-10-02). Sin el enlace FIRMADO (ref + t, que solo
     * arma crearSesion.php o el resumen) no se consulta nada ni se muestran
     * datos: nadie puede recorrer declaraciones ajenas ni gastar la llave
     * privada desde afuera.
     *
     * El "id" que agrega Wompi tampoco se aplica tal cual: podria ser de otra
     * cuenta de Wompi con la misma referencia. Se pregunta por la REFERENCIA del
     * intento con la llave privada (solo trae lo de este comercio), o por el id
     * que ya guardo un aviso firmado (PagoWompi::confirmar). Si no hay nada
     * todavia, el aviso de Wompi lo registra en un momento.
     *
     * Tambien con la declaracion ya pagada: asi un segundo pago se ve como tal.
     */
    require_once SERVER . '/business/class.pagoWompi.php';
    $refWompi = (string) ($_GET['ref'] ?? '');
    $intento  = null;
    if ($row && \erpsoftsas\PagoWompi::retornoValido($m['clave'], $id, $refWompi, $_GET['t'] ?? '')) {
        $intento = \erpsoftsas\PagoWompi::intento($con, $refWompi);
        if ($intento && ($intento['pel_Modulo'] !== $m['clave'] || (int) $intento['pel_IdDeclaracion'] !== $id)) {
            $intento = null;
        }
    }

    if (!$intento) {
        $referencia = '';
        $valor      = 0;
        $mensaje    = 'No se pudo verificar este enlace de pago. Consulte el estado del pago desde su declaración.';
    } else {
        try {
            $r = \erpsoftsas\PagoWompi::confirmar($con, $intento);
            if ($r === null) {
                $estado  = 'PENDING';
                $mensaje = trim((string) ($_GET['id'] ?? '')) !== ''
                    ? 'Recibimos su regreso de Wompi: el pago se está confirmando. Puede cerrar esta ventana; '
                      . 'se actualizará solo en unos minutos.'
                    : 'No se encontró un pago con este enlace. Si no lo terminó, puede iniciarlo de nuevo desde su declaración.';
            } else {
                $info     = $r['info'];
                $estado   = $r['estado'];
                $fechaIso = $info['fecha'];
                if ($info['valor'] !== null) { $valor = (float) $info['valor']; }

                if ($r['pagada']) {
                    $aprobado = true;
                    $mensaje  = 'Pago aprobado. Gracias.';
                } elseif ($r['doble']) {
                    $mensaje = 'Wompi aprobó este pago, pero la declaración ya estaba pagada. ' . $muni
                             . ' revisará la devolución; conserve su comprobante de Wompi.';
                } elseif ($info['aprobado']) {
                    $mensaje = 'Wompi aprobó el pago, pero no se pudo registrar automáticamente. '
                             . 'Comuníquese con ' . $muni . ' con su número de referencia.';
                } elseif ($estado === 'PENDING') {
                    $mensaje = 'El pago quedó en proceso. En cuanto se confirme, se actualizará automáticamente (puede tardar unos minutos).';
                } else {
                    $mensaje = 'El pago no fue aprobado. Puede intentarlo de nuevo desde su declaración.';
                    if ($info['mensaje'] !== '') { $mensaje .= ' (' . $info['mensaje'] . ')'; }
                }
            }
        } catch (Exception $e) {
            error_log('[wompi retorno] ' . $m['clave'] . ' ' . $id . ': ' . $e->getMessage());
            $mensaje = 'No se pudo confirmar el estado del pago en este momento. Si el pago sí se realizó, '
                     . 'quedará confirmado automáticamente en las próximas horas.';
        }
    }
} elseif (!$row || empty($row['pse_req'])) {
    $mensaje = 'No se encontró un pago PSE iniciado para esta declaración.';
} elseif ((int) $row['pagado'] === 1) {
    $aprobado = true;
    $estado   = 'APPROVED';
    $mensaje  = 'Esta declaración ya estaba registrada como pagada.';
    // Lo que entró, que en una ICA vencida incluye los intereses de mora.
    if ((float) ($row['valor_pagado'] ?? 0) > 0) { $valor = (float) $row['valor_pagado']; }
} else {
    try {
        $respuesta = PlacetoPay::consultarSesion($row['pse_req']);

        /*
         * La sesion tiene que ser DE ESTA declaracion (la referencia con que se
         * creo es su numero). Una correccion anterior al 2026-09-28 pudo heredar
         * la de la original: aplicada aqui, quedaba pagada con el pago ajeno.
         * Se le quita, para que el contribuyente pueda iniciar el suyo.
         */
        if (\erpsoftsas\PagoDeclaracion::sesionDeOtraDeclaracion($respuesta, $row['numero'])) {
            \erpsoftsas\PagoDeclaracion::olvidarSesionAjena($con, $id, $m);
            $mensaje = 'No se encontró un pago PSE iniciado para esta declaración. Puede iniciarlo desde su declaración.';
        } else {
            $info = PlacetoPay::interpretarRespuesta($respuesta);
            // Lo que cobró el banco: en una ICA vencida, el total más los intereses de mora.
            if (!empty($info['valor'])) { $valor = (float) $info['valor']; }

            // Se guarda el estado venga como venga; solo se marca pagada si el
            // banco la aprobo. El descriptor $m dice a que tabla/columnas escribir.
            PlacetoPay::aplicarADeclaracion($con, $id, $info, $valor, $m);

            $estado   = $info['estado'];
            $fechaIso = $info['fecha'] ?? '';

            // Aprobado por el banco NO es lo mismo que registrado: un borrador
            // no se marca pagado (PagoDeclaracion::registrar exige presentada).
            // Solo pasa con sesiones creadas antes de esa regla; decir "Pago
            // aprobado. Gracias." ahí dejaba el pago sin registrar y sin rastro.
            $registrada = true;
            if ($info['aprobado']) {
                $filaPago = $con->obnerFila($con->consultar(
                    "SELECT ISNULL({$m['pagado']}, 0) AS pagado FROM {$m['tabla']} WHERE {$m['pk']} = ?",
                    [$id]
                ));
                $registrada = ((int) ($filaPago['pagado'] ?? 0) === 1);
                if (!$registrada) {
                    error_log('[pse retorno] ' . $m['clave'] . ' ' . $id . ': el banco aprobó la sesión '
                            . $row['pse_req'] . ' pero la declaración no quedó pagada (no está presentada).');
                }
            }

            if ($info['aprobado'] && $registrada) {
                $aprobado = true;
                $mensaje = 'Pago aprobado. Gracias.';
            } elseif ($info['aprobado']) {
                $mensaje = 'El banco aprobó el pago, pero la declaración no está presentada y no se pudo '
                         . 'registrar. Comuníquese con ' . $muni . ' con el número de referencia.';
            } elseif (!empty($info['sinIntento'])) {
                // Abierta y sin ningun intento: no eligio banco o volvio atras.
                $mensaje = 'Todavía no se ha hecho el pago: la sesión que inició sigue abierta. Si no lo va a '
                         . 'terminar, podrá iniciar uno nuevo desde su declaración cuando esa sesión venza ('
                         . PlacetoPay::MINUTOS_SESION . ' minutos después de iniciada).';
            } elseif ($info['estado'] === 'PENDING') {
                $mensaje = 'El pago quedó en proceso. En cuanto el banco confirme, se actualizará automáticamente (puede tardar unos minutos).';
            } else {
                $mensaje = 'El pago no fue aprobado. Puede intentarlo de nuevo desde su declaración.';
                if (!empty($info['mensaje'])) {
                    $mensaje .= ' (' . $info['mensaje'] . ')';
                }
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
    // Wompi (042)
    'DECLINED' => 'Rechazado', 'VOIDED' => 'Anulado', 'ERROR' => 'Fallido',
    'REVISAR'  => 'En revisión', 'DOBLE' => 'Pago repetido',
];
$estadoTxt = $mapaEstado[$estado] ?? ($estado !== '' ? $estado : '—');
$valorFmt  = $valor > 0 ? '$ ' . number_format($valor, 0, ',', '.') . ' COP' : '—';
// En hora de Colombia, como el comprobante del banco (con el servidor en UTC salía 5 horas adelantada).
$zonaCo    = new DateTimeZone('America/Bogota');
try {
    $fechaTxt = ($fechaIso !== '' ? new DateTime($fechaIso) : new DateTime('now'))->setTimezone($zonaCo)->format('Y-m-d H:i');
} catch (Exception $e) {
    $fechaTxt = (new DateTime('now', $zonaCo))->format('Y-m-d H:i');
}
$claseEstado = $aprobado ? 'ok' : ($estado === 'PENDING' ? 'pendiente' : 'error');

/*
 * "Cerrar" no cerraba nada. Los navegadores solo dejan que window.close()
 * cierre una pestaña abierta por un script y sin historial, y esta llega
 * despues de pasar por el resumen, crearSesion.php y las paginas del banco:
 * el boton quedaba muerto y el contribuyente atrapado en el comprobante.
 * Ahora se intenta cerrar y, si el navegador no lo permite, se va al listado
 * de consulta del modulo, que es donde aparece la declaracion pagada.
 */
$volver = [
    'ica'          => '../../dist/icaWebConsultar.php',
    'reteica'      => '../../dist/reteicaConsultar.php',
    'autorreteica' => '../../dist/autoretencionConsultar.php',
][$m['clave']] ?? '../../dist/dashboard.php';

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
    <a class="btn" id="btnCerrar" href="<?= htmlspecialchars($volver) ?>">Cerrar</a>
  </div>
<script>
  // Primero se intenta cerrar la pestaña; si el navegador no lo permite, a los
  // 300 ms se sigue el enlace (el listado del módulo).
  document.getElementById('btnCerrar').addEventListener('click', function (e) {
    e.preventDefault();
    var destino = this.href;
    window.close();
    setTimeout(function () { location.href = destino; }, 300);
  });
</script>
</body>
</html>
