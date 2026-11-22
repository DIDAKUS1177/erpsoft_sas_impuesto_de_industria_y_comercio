<?php
/**
 * Un 1cero1pay DE MENTIRA para pruebas/probar_unocerouno.php.
 *
 * Lo arranca la propia prueba dentro del contenedor web, con el servidor
 * embebido de PHP:
 *
 *     php -S 127.0.0.1:9101 pruebas/stub_unocerouno.php
 *
 * Imita el «Web Service Pasarela de Pagos – Trámites con Recaudo» de 1Cero1:
 *
 *     POST /api/Login/authenticate             {Username, Password} -> {"Token": "..."}
 *     POST /api/transaction/InsertTransaction  -> {"Código": n, "URL": "...", "Mensaje": "..."}
 *     GET  /api/transaction?CodigoEntidad=&Factura=&IDImpuesto=  -> la transacción
 *
 * Su estado vive en un archivo JSON (variable de entorno STUB_101_ESTADO) que
 * la prueba lee y escribe para decidir qué contesta: el estado de cada
 * transacción, respuestas con otro valor u otra factura, errores 500, tokens
 * vencidos... y para ver qué le pidieron. Exige el token Bearer que entregó,
 * como el servicio real.
 *
 * SOLO funciona con el servidor embebido (cli-server): abierto por la web, o
 * por línea de comandos, no hace nada.
 */
if (PHP_SAPI !== 'cli-server') {
    if (PHP_SAPI !== 'cli') { http_response_code(404); }
    exit;
}

$archivo = getenv('STUB_101_ESTADO') ?: (sys_get_temp_dir() . '/stub_unocerouno.json');

/** Lee, deja modificar y guarda el estado, con candado (el retorno y la tarea piden a la vez). */
function con_estado($archivo, callable $f)
{
    $h = fopen($archivo, 'c+');
    flock($h, LOCK_EX);
    $txt = stream_get_contents($h);
    $e = json_decode($txt === '' ? '{}' : $txt, true) ?: [];
    $e += ['usuario' => '', 'clave' => '', 'modo' => [], 'tokens' => [], 'tx' => [], 'pedidos' => [], 'n' => 0, 'logins' => 0];
    $r = $f($e);
    ftruncate($h, 0);
    rewind($h);
    fwrite($h, json_encode($e, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fflush($h);
    flock($h, LOCK_UN);
    fclose($h);
    return $r;
}

function responder($codigo, $cuerpo)
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo is_string($cuerpo) ? $cuerpo : json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    exit;
}

$ruta   = rtrim((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$metodo = $_SERVER['REQUEST_METHOD'];
$crudo  = file_get_contents('php://input');
$cuerpo = json_decode($crudo, true);
$auth   = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token  = stripos($auth, 'Bearer ') === 0 ? trim(substr($auth, 7)) : '';

// La página a la que "se manda al contribuyente": no hace nada.
if ($ruta === '/pagar') {
    header('Content-Type: text/html; charset=utf-8');
    echo 'Simulador de 1cero1pay: pagina de pago de la transaccion ' . htmlspecialchars((string) ($_GET['tx'] ?? ''));
    exit;
}

[$codigo, $respuesta] = con_estado($archivo, function (&$e) use ($ruta, $metodo, $cuerpo, $token) {
    $e['pedidos'][] = ['metodo' => $metodo, 'ruta' => $ruta, 'query' => $_GET, 'cuerpo' => $cuerpo, 'token' => $token];
    $modo = $e['modo'];

    if ($ruta === '/api/Login/authenticate' && $metodo === 'POST') {
        if (!empty($modo['login500'])) { return [500, ['Mensaje' => 'Error interno']]; }
        if (!is_array($cuerpo) || ($cuerpo['Username'] ?? null) !== $e['usuario'] || ($cuerpo['Password'] ?? null) !== $e['clave']
            || $e['usuario'] === '') {
            return [401, ['Mensaje' => 'Usuario o clave incorrectos']];
        }
        $e['logins']++;
        $t = 'tok' . $e['logins'] . bin2hex(random_bytes(6));
        $e['tokens'][$t] = time() + 300;      // 5 minutos, como el real
        // 'tokenCrudo': el cuerpo entero es el token (así contestaba el sistema anterior).
        return [200, !empty($modo['tokenCrudo']) ? json_encode($t) : ['Token' => $t]];
    }

    // Todo lo demás exige el token vigente.
    if ($token === '' || !isset($e['tokens'][$token]) || $e['tokens'][$token] < time()) {
        return [401, ['Mensaje' => 'Token no valido o vencido']];
    }

    if ($ruta === '/api/transaction/InsertTransaction' && $metodo === 'POST') {
        if (!empty($modo['insert500'])) { return [500, ['Mensaje' => 'Error interno']]; }
        if (!empty($modo['insertSinUrl'])) { return [200, ['Código' => 0, 'URL' => null, 'Mensaje' => 'La entidad no tiene habilitado el tramite']]; }
        if (!is_array($cuerpo)) { return [400, ['Mensaje' => 'Cuerpo no valido']]; }
        $clave = ($cuerpo['CodigoEntidad'] ?? '') . '|' . ($cuerpo['IDImpuesto'] ?? '') . '|' . ($cuerpo['Factura'] ?? '');
        if (isset($e['tx'][$clave])) { return [200, ['Código' => 0, 'URL' => null, 'Mensaje' => 'La factura ya tiene una transaccion']]; }
        $e['n']++;
        $id = 500000 + $e['n'];
        $e['tx'][$clave] = [
            'IDTransaccion'       => $id,
            'EstadoTransaccion'   => 'COMENZADA',
            'Total'               => $cuerpo['Total'] ?? null,
            'CUS'                 => null,
            'FechaTransaccion'    => date('Y-m-d\TH:i:s', time() - 5 * 3600),
            'FechaRespuestaBanco' => null,
            'MedioPago'           => 'PSE',
            'CodigoBanco'         => '1007',
            'Identificacion'      => $cuerpo['Pagador']['Identificacion'] ?? null,
            'Factura'             => (string) ($cuerpo['Factura'] ?? ''),
            'Referencia'          => (string) ($cuerpo['Referencia'] ?? ''),
            'CodigoEntidad'       => (string) ($cuerpo['CodigoEntidad'] ?? ''),
            'IDImpuesto'          => $cuerpo['IDImpuesto'] ?? null,
        ];
        $url = !empty($modo['urlAjena']) ? 'https://pagos.ejemplo-ajeno.com/pagar?tx=' . $id
                                         : 'http://' . $_SERVER['HTTP_HOST'] . '/pagar?tx=' . $id;
        return [200, ['Código' => $id, 'URL' => $url, 'Mensaje' => 'Transaccion creada']];
    }

    if ($ruta === '/api/transaction' && $metodo === 'GET') {
        if (!empty($modo['consulta500'])) { return [500, ['Mensaje' => 'Error interno']]; }
        // 'noExiste': contesta 200 con un objeto que no es la transacción (sin Factura).
        if (!empty($modo['noExiste'])) { return [200, ['Mensaje' => 'No existe']]; }
        $clave = ($_GET['CodigoEntidad'] ?? '') . '|' . ($_GET['IDImpuesto'] ?? '') . '|' . ($_GET['Factura'] ?? '');
        if (!isset($e['tx'][$clave])) { return [200, 'null']; }
        $tx = $e['tx'][$clave];
        unset($tx['CodigoEntidad'], $tx['IDImpuesto']);
        // 'sobre': la respuesta viene envuelta ({data: {value: ...}}), como algunas del servicio real.
        return [200, !empty($modo['sobre']) ? ['data' => ['value' => $tx]] : $tx];
    }

    return [404, ['Mensaje' => 'Ruta no encontrada']];
});

responder($codigo, $respuesta);
