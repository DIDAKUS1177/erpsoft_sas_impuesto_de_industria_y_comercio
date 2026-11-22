<?php

/**
 * Punto de entrada del botón de pago en línea: crea el pago en la pasarela de
 * la entidad (042: PlacetoPay en Paipa, Wompi en Macanal) y redirige al
 * usuario a pagarlo.
 *
 * Sirve a los TRES módulos (ICA, Retención, Autorretención) según ?modulo=,
 * usando el descriptor de PseModulo para saber tabla y columnas. Sin modulo,
 * es ICA (compatibilidad).
 *
 * POST/GET: id (o dec_Id), modulo, acepto
 * POST: intereses (ICA vencida: intereses de mora escritos en el resumen)
 * POST/GET: pasarela = 101 -> el botón «PSE · Bancolombia» (1cero1pay,
 *     migración 060), la segunda pasarela; sin él, la de la entidad.
 */
include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.conexionSqlServer.php';
require_once SERVER . '/business/class.placetopay.php';
require_once SERVER . '/business/class.pseModulo.php';
require_once SERVER . '/business/class.pasarela.php';

$configPath = dirname(dirname(dirname(__DIR__))) . '/config.municipio.php';
if (!file_exists($configPath)) {
    $configPath = dirname(dirname(__DIR__)) . '/config.municipio.php';
}
if (file_exists($configPath)) {
    require_once $configPath;
}

$con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

// La SEGUNDA pasarela (060): «PSE · Bancolombia» por 1cero1pay, si se pidió.
$es101 = \erpsoftsas\Pasarela::pedida($_POST['pasarela'] ?? $_GET['pasarela'] ?? '') !== null;
if ($es101) {
    require_once SERVER . '/business/class.pagoUnoCeroUno.php';
}

// Sin convenio de recaudo configurado no hay nada que cobrar. Se comprueba lo
// PRIMERO, antes de tocar la base (ver la nota en pagar.php).
if ($es101 ? !\erpsoftsas\UnoCeroUno::activo() : !\erpsoftsas\Pasarela::configurado()) {
    http_response_code(503);
    die('El pago en línea no está disponible: la Alcaldía todavía no ha configurado '
      . 'el convenio de recaudo. Puede pagar en el banco con el código de barras '
      . 'impreso en su declaración.');
}

$modulo = $_POST['modulo'] ?? $_GET['modulo'] ?? 'ica';
$m      = \erpsoftsas\PseModulo::get($modulo);
$modulo = $m['clave'];
$id     = (int) ($_POST['id'] ?? $_GET['id'] ?? $_POST['dec_Id'] ?? $_GET['dec_Id'] ?? 0);

// El resumen (pagar.php) de la pasarela pedida: a donde se vuelve si no se puede seguir.
$resumen = 'pagar.php?modulo=' . urlencode($modulo) . '&id=' . $id
         . ($es101 ? '&pasarela=' . \erpsoftsas\Pasarela::SEGUNDA : '');

// Solo quien ve el botón, y sobre sus propias declaraciones (la Alcaldía, sobre
// cualquiera): ver PseModulo::motivoParaNoPagar.
$motivo = \erpsoftsas\PseModulo::motivoParaNoPagar($con, $m, $id, $es101 ? \erpsoftsas\Pasarela::SEGUNDA : null);
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
// Solo por POST (el formulario del resumen): con GET, un enlace o una imagen en
// otra pagina creaba pagos con la sesion abierta del contribuyente (revision
// 2026-10-02). Con GET se va al resumen, como sin aceptar.
$acepto = (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['acepto'] ?? '') === '1');
if (!$acepto) {
    header('Location: ' . $resumen);
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
$esWompi = \erpsoftsas\Pasarela::esWompi();
if ($esWompi) {
    require_once SERVER . '/business/class.pagoWompi.php';
}
// Con Wompi, en tramite tambien es un intento recien creado cuyo enlace todavia
// puede estar pagandose (PagoWompi::enTramite): asi no se paga dos veces.
// Con PlacetoPay, en tramite es PENDING o APPROVED sin registrar (PlacetoPay::enTramite).
$enTramite = $esWompi
    ? \erpsoftsas\PagoWompi::enTramite($con, $modulo, $id) !== null
    : (!empty($row['pse_req']) && PlacetoPay::enTramite($row['pse_est']));
if ($enTramite) {
    header('Location: ' . $resumen);
    exit;
}
/*
 * 1CERO1PAY (060): un solo pago en curso por declaración, también ENTRE
 * pasarelas. Con un pago por «PSE · Bancolombia» en trámite (o aprobado y en
 * revisión) no se inicia otro, ni por ahí ni por la pasarela de la entidad; se
 * manda al resumen, que lo explica. Solo corre donde hay credenciales de
 * 1cero1pay.
 */
$hay101 = \erpsoftsas\Pasarela::hayUnoCeroUno();
if ($hay101) {
    require_once SERVER . '/business/class.pagoUnoCeroUno.php';
    // Si esta comprobación falla (la base, no 1cero1pay: eso ya cuenta como "en
    // trámite"), no se sigue a ciegas ni se rompe la página: al resumen, con el
    // aviso de intentar de nuevo. Vale también para el botón de siempre.
    try {
        $frena101 = \erpsoftsas\PagoUnoCeroUno::enTramite($con, $modulo, $id) !== null
                 || \erpsoftsas\PagoUnoCeroUno::enRevision($con, $m, $id) !== null;
        // Preguntarle a 1cero1pay pudo dejarla pagada en este mismo momento.
        $ahora = $con->obnerFila($con->consultar(
            "SELECT ISNULL({$m['pagado']}, 0) AS pagado FROM {$m['tabla']} WHERE {$m['pk']} = ?", [$id]
        ));
    } catch (\Throwable $e) {
        error_log('[1cero1pay crearSesion] ' . $modulo . ' ' . $id . ': no se pudo comprobar el pago en curso: ' . $e->getMessage());
        header('Location: ' . $resumen . '&aviso=reintente');
        exit;
    }
    if ($frena101) {
        header('Location: ' . $resumen);
        exit;
    }
    if ((int) ($ahora['pagado'] ?? 0) === 1) {
        die('Esta declaración ya está pagada.');
    }
}
if (!$es101 && $esWompi && \erpsoftsas\PagoWompi::intentosUltimaHora($con, $modulo, $id) >= \erpsoftsas\PagoWompi::MAX_INTENTOS_HORA) {
    http_response_code(429);
    die('Ya se iniciaron varios pagos de esta declaración en la última hora. Si ya pagó, espere la confirmación; '
      . 'si no, intente de nuevo más tarde.');
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
    // Obligatorios solo para el contribuyente, como en el recibo de pago: la
    // Alcaldía (roles 1 y 2) puede cobrar sin intereses (ver pagar.php).
    include_once SERVER . '/business/class.permisosRol.php';
    $esAlcaldia = \erpsoftsas\PermisosRol::tiene('alcaldia.recibo.intereses');
    $exige      = $vencidaIca && !$esAlcaldia
                  && \erpsoftsas\VencimientoICA::exigeIntereses($anioIca, $ica['dec_ValorConcepto16'] ?? 0);
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
    header('Location: ' . $resumen);
    exit;
}
$valor += $intereses;

// URL de retorno: esquema+host actuales para que funcione igual en local,
// pruebas y produccion. Lleva el modulo para volver a la tabla correcta.
$esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

/*
 * WOMPI (042). Cada clic es un intento con su referencia (Wompi no deja
 * reutilizarla), firmada junto con el valor: nadie puede cambiar lo que se
 * cobra en el camino. Wompi devuelve al contribuyente a retorno.php agregando
 * ?id=<transaccion>; por eso la declaracion va aqui como "decl" y no como "id",
 * con la referencia y una firma del enlace (PagoWompi::urlRetorno). Los
 * intereses de mora van dentro del valor, igual que con PlacetoPay.
 */
/*
 * 1CERO1PAY (060), «PSE · Bancolombia». Cada clic es un intento con su FACTURA
 * (el identificador único del pago en 1cero1pay: número de la declaración +
 * módulo + consecutivo, ver class.pagoUnoCeroUno.php); la Referencia es el
 * número de la declaración y el IDImpuesto, el del módulo. Los intereses de
 * mora van dentro del valor, igual que con las otras pasarelas. El intento se
 * anota ANTES de hablar con 1cero1pay, con el candado de la declaración: ahí se
 * vuelve a comprobar que siga presentada, sin pagar y sin otro pago en curso
 * por ninguna pasarela. No hay URL de notificación: el pago se confirma al
 * volver (retorno.php) y con la tarea programada.
 */
if ($es101) {
    $centavos = (int) round($valor * 100);
    $usuario  = (int) ($_SESSION['id_usuario'] ?? 0);
    try {
        $r101 = \erpsoftsas\PagoUnoCeroUno::crearIntento($con, $m, $id, $centavos, $intereses, $usuario);
    } catch (Exception $e) {
        error_log('[1cero1pay crearSesion] ' . $modulo . ' ' . $id . ': ' . $e->getMessage());
        http_response_code(502);
        die('No se pudo iniciar el pago en línea. Intente de nuevo en unos minutos.');
    }
    if (!$r101['ok']) {
        if ($r101['motivo'] === 'limite') {
            http_response_code(429);
            die('Ya se iniciaron varios pagos de esta declaración en la última hora. Si ya pagó, espere la confirmación; '
              . 'si no, intente de nuevo más tarde.');
        }
        if ($r101['motivo'] === 'numero') {
            die('Esta declaración no se puede pagar por este medio. Puede pagar en el banco con el recibo de pago.');
        }
        // Ya pagada, sin presentar o con otro pago en curso: el resumen lo explica.
        header('Location: ' . $resumen);
        exit;
    }

    $factura = (string) $r101['intento']['pel_Referencia'];
    try {
        $tx101 = \erpsoftsas\UnoCeroUno::crearTransaccion([
            'factura'    => $factura,
            'referencia' => $referencia,
            'centavos'   => $centavos,
            'modulo'     => $modulo,
            'idImpuesto' => (int) $r101['intento']['pel_IdImpuesto'],
            'pagador'    => \erpsoftsas\PagoUnoCeroUno::pagador($con, $m, $id),
        ]);
    } catch (Exception $e) {
        // Sin dirección de pago no hay cómo pagarlo: el intento se cierra y se
        // puede reintentar. El detalle va al registro, no a la pantalla.
        error_log('[1cero1pay crearSesion] ' . $factura . ': ' . $e->getMessage());
        try { \erpsoftsas\PagoUnoCeroUno::marcarError($con, $factura, $e->getMessage()); } catch (Exception $e2) { /* ya quedó en el registro */ }
        http_response_code(502);
        die('No se pudo iniciar el pago en línea. Intente de nuevo en unos minutos.');
    }
    \erpsoftsas\PagoUnoCeroUno::marcarIniciado($con, $factura, $tx101['id']);

    // Para el regreso sin enlace firmado (1cero1pay no recibe una URL de
    // retorno por intento): la sesión que inició el pago puede verificarlo.
    $_SESSION['pago101'] = ['modulo' => $modulo, 'id' => $id, 'ref' => $factura];

    header('Location: ' . $tx101['url']);
    exit;
}

if ($esWompi) {
    $centavos = (int) round($valor * 100);
    $usuario  = (int) ($_SESSION['id_usuario'] ?? 0);
    try {
        // Con 1cero1pay configurado, el intento se anota con el candado y solo
        // si 1cero1pay no tiene un pago en curso (dos pestañas, una por pasarela).
        $crearWompi = function () use ($con, $m, $id, $referencia, $centavos, $intereses, $usuario) {
            return \erpsoftsas\PagoWompi::crearIntento($con, $m, $id, $referencia, $centavos, $intereses, $usuario);
        };
        $refWompi = $hay101 ? \erpsoftsas\PagoUnoCeroUno::siLibre($con, $m, $id, $crearWompi) : $crearWompi();
        if ($refWompi === null) {
            header('Location: ' . $resumen);
            exit;
        }
        $url = \erpsoftsas\Wompi::urlCheckout([
            'referencia'  => $refWompi,
            'centavos'    => $centavos,
            'vencimiento' => \erpsoftsas\Wompi::vencimiento(),
            'redirect'    => \erpsoftsas\PagoWompi::urlRetorno(
                                 $esquema . '://' . $_SERVER['HTTP_HOST'] . '/erpsoftsas/extensiones/pse/', $modulo, $id, $refWompi),
            'cliente'     => \erpsoftsas\PagoWompi::cliente($con, $m, $id),
        ]);
    } catch (Exception $e) {
        error_log('[wompi crearSesion] ' . $modulo . ' ' . $id . ': ' . $e->getMessage());
        http_response_code(502);
        die('No se pudo iniciar el pago en línea. Intente de nuevo en unos minutos.');
    }
    header('Location: ' . $url);
    exit;
}
$returnUrl = $esquema . '://' . $_SERVER['HTTP_HOST']
           . '/erpsoftsas/extensiones/pse/retorno.php?modulo=' . urlencode($modulo) . '&id=' . $id;

// Extradata (item 5): sale en el comprobante del banco. Periodo = prefijo AAAA
// del numero de declaracion (migraciones 012/029/030). En la ICA, el año de la
// declaracion: desde el 2026-10-05 una de 2023 lleva numero de la serie de hoy
// (2026...), y el comprobante diria "Periodo 2026".
$anio = (strlen($referencia) >= 4 && ctype_digit(substr($referencia, 0, 4)))
      ? substr($referencia, 0, 4) : date('Y');
if ($modulo === 'ica' && !empty($anioIca)) {
    $anio = (string) $anioIca;
}
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

// La sesion queda anotada ya PENDIENTE (PlacetoPay::anotarSesion): otra no se
// crea hasta que esta se resuelva o venza. Si en el intervalo otra pestaña dejo
// la suya, esta no se ofrece: al resumen, que muestra el pago en curso.
// Con 1cero1pay configurado (060), se anota con el candado de la declaración y
// solo si 1cero1pay no tiene un pago en curso (dos pestañas, una por pasarela).
$anotar = function () use ($con, $m, $id, $sesion) {
    return PlacetoPay::anotarSesion($con, $m, $id, $sesion['requestId']);
};
// Si con 1cero1pay configurado no se puede comprobar (el candado tarda, la base
// falla), el botón de siempre no termina en un error 500: al resumen, con el
// aviso de intentar de nuevo (PagoUnoCeroUno::intentarSiLibre). La sesión que ya
// se creó en el banco no se ofrece y vence sola, como en el caso de abajo.
if ($hay101) {
    $libre = \erpsoftsas\PagoUnoCeroUno::intentarSiLibre($con, $m, $id, $anotar);
    if (!$libre['ok']) {
        header('Location: ' . $resumen . '&aviso=reintente');
        exit;
    }
    $anotada = $libre['valor'];
} else {
    $anotada = $anotar();
}
if (!$anotada) {
    header('Location: ' . $resumen);
    exit;
}

header('Location: ' . $sesion['processUrl']);
exit;
