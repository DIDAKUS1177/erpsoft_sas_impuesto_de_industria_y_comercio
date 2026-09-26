<?php

/**
 * Punto de entrada del botón "Pagar PSE": crea la sesión en PlacetoPay
 * para una declaración y redirige al usuario a pagarla.
 *
 * Sirve a los TRES módulos (ICA, Retención, Autorretención) según ?modulo=,
 * usando el descriptor de PseModulo para saber tabla y columnas. Sin modulo,
 * es ICA (compatibilidad).
 *
 * POST/GET: id (o dec_Id), modulo, acepto
 * POST: intereses (ICA vencida: intereses de mora escritos en el resumen)
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

// Sin convenio de recaudo configurado no hay nada que cobrar. Se comprueba lo
// PRIMERO, antes de tocar la base (ver la nota en pagar.php).
if (!PlacetoPay::configurado()) {
    http_response_code(503);
    die('El pago en línea no está disponible: la Alcaldía todavía no ha configurado '
      . 'el convenio de recaudo. Puede pagar en el banco con el código de barras '
      . 'impreso en su declaración.');
}

$modulo = $_POST['modulo'] ?? $_GET['modulo'] ?? 'ica';
$m      = \erpsoftsas\PseModulo::get($modulo);
$modulo = $m['clave'];
$id     = (int) ($_POST['id'] ?? $_GET['id'] ?? $_POST['dec_Id'] ?? $_GET['dec_Id'] ?? 0);

// Solo quien ve el botón, y sobre sus propias declaraciones (la Alcaldía, sobre
// cualquiera): ver PseModulo::motivoParaNoPagar.
$motivo = \erpsoftsas\PseModulo::motivoParaNoPagar($con, $m, $id);
if ($motivo !== null) {
    http_response_code(403);
    die(htmlspecialchars($motivo));
}

/*
 * La certificacion WC exige un resumen con aceptacion de la politica de datos
 * ANTES de pagar (items 4 y 12.1). Ese resumen es pagar.php, que envia aqui por
 * POST con acepto=1. Si se llega sin aceptar -por ejemplo abriendo esta URL a
 * mano-, se redirige al resumen en vez de crear la sesion directamente.
 */
$acepto = (($_POST['acepto'] ?? $_GET['acepto'] ?? '') === '1');
if (!$acepto) {
    header('Location: pagar.php?modulo=' . urlencode($modulo) . '&id=' . $id);
    exit;
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
    die('Declaración no encontrada.');
}

if ((int) $row['pagado'] === 1) {
    die('Esta declaración ya está pagada.');
}

/*
 * Solo se paga lo que ya esta PRESENTADO (estado = 2). Sin esta guarda se podia
 * pagar un BORRADOR y dejar la declaracion "pagada pero sin presentar", un
 * estado imposible que rompe "Corregir". La misma regla aplica al recaudo
 * bancario. No se confia en que el boton este oculto: esta URL se llama a mano.
 */
if ((int) ($row['estado'] ?? 0) !== 2) {
    die('Esta declaración todavía no está presentada. Debe presentarla antes de pagar.');
}

/*
 * item 4.3: si ya hay una sesion cuyo ultimo estado conocido es PENDING, no se
 * crea otra; se manda al resumen (pagar.php), que muestra el aviso de "pago en
 * proceso". Evita dobles pagos sobre una operacion en tramite.
 */
if (!empty($row['pse_req']) && strtoupper((string) $row['pse_est']) === 'PENDING') {
    header('Location: pagar.php?modulo=' . urlencode($modulo) . '&id=' . $id);
    exit;
}

$referencia = (string) $row['numero'];
$valor = (float) $row['valor'];

if ($valor <= 0) {
    die('El valor a pagar de esta declaración es $0, no aplica pago PSE.');
}

/*
 * Intereses de mora: una ICA VENCIDA se paga con ellos, escritos a mano en el
 * resumen (pagar.php), con la regla del recibo de pago (Javier y el cliente,
 * 2026-09-25; business/class.vencimientoICA.php). Se cobra el total MAS los
 * intereses, y lo que se registra como pagado es lo que confirme el banco
 * (PlacetoPay::interpretarRespuesta). Lo que no es una ICA vencida no los lleva.
 */
include_once SERVER . '/business/class.vencimientoICA.php';
$intereses  = 0;
$vencidaIca = false;
$exige      = false;
if ($modulo === 'ica') {
    $ica = $con->obnerFila($con->consultar(
        "SELECT dec_AnioDeclaracion, dec_ValorConcepto16 FROM ind_declaraciones_ica WHERE dec_Id = ?", [$id]
    ));
    $anioIca    = (int) ($ica['dec_AnioDeclaracion'] ?? 0);
    $vencidaIca = $ica && \erpsoftsas\VencimientoICA::vencida($anioIca);
    $exige      = $vencidaIca && \erpsoftsas\VencimientoICA::exigeIntereses($anioIca, $ica['dec_ValorConcepto16'] ?? 0);
}
$textoIntereses = trim((string) ($_POST['intereses'] ?? ''));
if ($textoIntereses !== '') {
    $leidos = \erpsoftsas\VencimientoICA::leerIntereses($textoIntereses);
    if ($leidos === null) {
        die('Intereses de mora no válidos: escriba el valor en pesos, sin decimales.');
    }
    $intereses = $leidos;
}
if ($intereses > 0 && !$vencidaIca) {
    die('Esta declaración no lleva intereses de mora en el pago en línea.');
}
if ($exige && $intereses <= 0) {
    header('Location: pagar.php?modulo=' . urlencode($modulo) . '&id=' . $id);
    exit;
}
$valor += $intereses;

// URL de retorno: esquema+host actuales para que funcione igual en local,
// pruebas y produccion. Lleva el modulo para volver a la tabla correcta.
$esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$returnUrl = $esquema . '://' . $_SERVER['HTTP_HOST']
           . '/erpsoftsas/extensiones/pse/retorno.php?modulo=' . urlencode($modulo) . '&id=' . $id;

// Extradata (item 5): sale en el comprobante del banco. Periodo = prefijo AAAA
// del numero de declaracion (migraciones 012/029/030).
$anio = (strlen($referencia) >= 4 && ctype_digit(substr($referencia, 0, 4)))
      ? substr($referencia, 0, 4) : date('Y');
$fields = [
    ['keyword' => 'Concepto', 'value' => $m['etiqueta'], 'displayOn' => 'both'],
    ['keyword' => 'Periodo',  'value' => $anio, 'displayOn' => 'both'],
];
if ($intereses > 0) {
    $fields[] = ['keyword' => 'Intereses de mora',
                 'value' => '$ ' . number_format($intereses, 0, ',', '.'), 'displayOn' => 'both'];
}

try {
    $sesion = PlacetoPay::crearSesion(
        $referencia,
        $valor,
        'Pago ' . $m['etiqueta'] . ' - Formulario No. ' . $referencia
            . ($intereses > 0 ? ' (incluye intereses de mora)' : ''),
        $returnUrl,
        null,       // buyer: opcional; AvalPay pide los datos del titular en su pantalla
        $fields
    );
} catch (Exception $e) {
    http_response_code(502);
    die('No se pudo iniciar el pago con PlacetoPay: ' . htmlspecialchars($e->getMessage()));
}

$con->consultar(
    "UPDATE {$m['tabla']} SET {$col['req']} = ? WHERE {$m['pk']} = ?",
    [$sesion['requestId'], $id]
);

header('Location: ' . $sesion['processUrl']);
exit;
