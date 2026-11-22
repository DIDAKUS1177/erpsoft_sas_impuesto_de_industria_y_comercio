<?php
/**
 * Prueba del pago en línea con 1cero1pay («PSE · Bancolombia»), la segunda
 * pasarela, simultánea con la de la entidad (migración 060).
 *
 * Uso, DENTRO del contenedor web (necesita el driver de SQL Server y el Apache
 * del contenedor):
 *
 *     docker exec -w /var/www/html/erpsoftsas erpsoftsas_web_completo php pruebas/probar_unocerouno.php
 *
 * CÓMO FUNCIONA
 *
 *  - Arranca un 1cero1pay DE MENTIRA (pruebas/stub_unocerouno.php) en
 *    127.0.0.1:9101, dentro del contenedor. La prueba decide qué contesta
 *    (aprobada, rechazada, pendiente, otro valor, otra factura, error 500,
 *    token vencido) y mira qué le pidieron. Nada sale a Internet: a 1cero1pay
 *    de verdad no se le manda nada, y a la pasarela de la entidad (AvalPay)
 *    tampoco (su sesión se anota y se aplica con las mismas funciones que usan
 *    crearSesion.php y retorno.php, sin hablar con el banco).
 *  - Las pantallas (pagar.php, crearSesion.php, retorno.php y los listados) se
 *    piden por HTTP al Apache del contenedor con una sesión de verdad, como en
 *    el navegador. Para que ese Apache hable con el simulador, mientras corre
 *    la prueba se agrega la constante UNOCEROUNO_API_BASE al config.municipio.php
 *    DEL CONTENEDOR (/var/www/html, no es el del repositorio) y al final se
 *    deja como estaba.
 *  - Usa declaraciones presentadas y sin pagar del contribuyente de pruebaica
 *    (ICA, retención y autorretención). Al terminar las deja EXACTAMENTE como
 *    estaban, borra los intentos que creó y devuelve los parámetros UNOCEROUNO_*
 *    a su valor. Las credenciales que escribe son inventadas (las del simulador).
 *  - Se NIEGA a correr si la base se llama como las de los municipios
 *    (erpsofts_ind_comercio_*).
 */

if (PHP_SAPI !== 'cli') { exit('Solo por línea de comandos.'); }

$raiz = dirname(__DIR__);                          // .../erpsoftsas
$_SERVER['DOCUMENT_ROOT'] = dirname($raiz);        // SERVER = DOCUMENT_ROOT/erpsoftsas

/* Un proceso aparte que TOMA el candado de pago de una declaración y lo retiene
   unos segundos (otra conexión a la base): para probar qué hace la pasarela de
   siempre cuando no puede comprobar si 1cero1pay tiene un pago en curso. */
if (($argv[1] ?? '') === '--candado') {
    include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
    include_once SERVER . '/business/class.conexionSqlServer.php';
    $c = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
    $recurso = 'erp_pago_' . preg_replace('/[^a-z]/', '', (string) $argv[2]) . '_' . (int) $argv[3];
    $c->begin();
    $c->obnerFila($c->consultar("SET NOCOUNT ON; DECLARE @r INT; EXEC @r = sp_getapplock @Resource = '$recurso',
        @LockMode = 'Exclusive', @LockOwner = 'Transaction', @LockTimeout = 5000; SELECT @r AS r;"));
    echo "listo\n";
    flush();
    sleep((int) $argv[4]);
    $c->rollback();
    exit(0);
}

const PUERTO_STUB   = 9101;
const API_STUB      = 'http://127.0.0.1:9101/api';
const WEB           = 'http://localhost/erpsoftsas/';
const MARCA_CONFIG  = '// PRUEBA probar_unocerouno (se quita sola)';
const SIM_USUARIO   = 'usuario_del_simulador';
const SIM_CLAVE     = 'clave_del_simulador_101';
const SIM_ENTIDAD   = 'SIM0001';

// El proceso de la prueba también habla con el simulador.
define('UNOCEROUNO_API_BASE', API_STUB);

error_reporting(E_ALL);
include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.conexionSqlServer.php';
include_once SERVER . '/business/class.permisosRol.php';
require_once SERVER . '/business/class.placetopay.php';
require_once SERVER . '/business/class.pagoUnoCeroUno.php';
require_once SERVER . '/business/class.vencimientoICA.php';
ini_set('display_errors', '1');                    // globals.php lo apaga; en una prueba se quiere ver

use erpsoftsas\Parametros;
use erpsoftsas\Pasarela;
use erpsoftsas\PseModulo;
use erpsoftsas\UnoCeroUno;
use erpsoftsas\PagoUnoCeroUno;
use erpsoftsas\PagoDeclaracion;
use erpsoftsas\VencimientoICA;

/* ======================================================================
   Utilidades
   ====================================================================== */
$ok = 0;
$fallas = [];
function prueba($nombre, $condicion, $detalle = '')
{
    global $ok, $fallas;
    if ($condicion) { $ok++; echo "  ok   $nombre\n"; return true; }
    $fallas[] = $nombre;
    $d = is_string($detalle) ? $detalle : json_encode($detalle, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    echo "  FALLA $nombre" . ($d !== '' ? '  -> ' . mb_substr($d, 0, 500) : '') . "\n";
    return false;
}
function seccion($t) { echo "\n== $t\n"; }

$con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
if (function_exists('sqlsrv_configure')) { sqlsrv_configure('WarningsReturnAsErrors', 0); }
function uno($sql, array $p = []) { global $con; return $con->obnerFila($con->consultar($sql, $p)); }
function todas($sql, array $p = [])
{
    global $con;
    $st = $con->consultar($sql, $p);
    $r = [];
    while ($f = $con->obnerFila($st)) { $r[] = $f; }
    return $r;
}
function ejec($sql, array $p = []) { global $con; $con->consultar($sql, $p); }
function valor($sql, array $p = []) { $f = uno($sql, $p); return $f ? reset($f) : null; }

$base = valor('SELECT DB_NAME()');
if (preg_match('/^erpsofts_ind_comercio_/i', (string) $base)) {
    exit("Se niega a correr: la base '$base' se llama como las de los municipios. Use una copia local.\n");
}
echo "Base: $base\n";

if (!valor("SELECT COL_LENGTH('dbo.ind_pagos_en_linea', 'pel_IdImpuesto')")
    || (int) valor("SELECT COUNT(*) FROM conf_parametros WHERE par_Clave LIKE 'UNOCEROUNO[_]%'") < 12) {
    exit("Falta la migración 060: aplíquela (php BD/aplicar_migraciones.php --aplicar 060).\n");
}

/* ---------- el config del contenedor (para el Apache) ---------- */
$CONFIG   = dirname($raiz) . '/config.municipio.php';
$RESPALDO = sys_get_temp_dir() . '/config.municipio.php.respaldo_prueba_101';
if (!is_file($CONFIG) || !is_writable($CONFIG)) {
    exit("No se puede escribir $CONFIG: la prueba corre dentro del contenedor web, como root.\n");
}
// Una corrida anterior que se cortó: primero se deja el config como estaba.
if (is_file($RESPALDO) && strpos((string) file_get_contents($CONFIG), MARCA_CONFIG) !== false) {
    file_put_contents($CONFIG, file_get_contents($RESPALDO));
}
$configOriginal = (string) file_get_contents($CONFIG);
if (strpos($configOriginal, 'UNOCEROUNO_API_BASE') !== false) {
    exit("El config del contenedor ya define UNOCEROUNO_API_BASE: no se toca. Quítelo para correr la prueba.\n");
}
if (preg_match('/\?>\s*$/', $configOriginal)) {
    exit("El config del contenedor termina en '?>': la prueba no sabe agregarle la constante.\n");
}

/* ---------- el simulador ---------- */
$ESTADO_STUB = sys_get_temp_dir() . '/stub_unocerouno_' . getmypid() . '.json';
function stub(callable $f)
{
    global $ESTADO_STUB;
    $h = fopen($ESTADO_STUB, 'c+');
    flock($h, LOCK_EX);
    $txt = stream_get_contents($h);
    $e = json_decode($txt === '' ? '{}' : $txt, true) ?: [];
    $e += ['usuario' => '', 'clave' => '', 'modo' => [], 'tokens' => [], 'tx' => [], 'pedidos' => [], 'n' => 0, 'logins' => 0];
    $r = $f($e);
    ftruncate($h, 0);
    rewind($h);
    fwrite($h, json_encode($e, JSON_UNESCAPED_UNICODE));
    fflush($h);
    flock($h, LOCK_UN);
    fclose($h);
    return $r;
}
function stub_leer() { return stub(function (&$e) { return $e; }); }
function stub_reiniciar()
{
    stub(function (&$e) {
        $e = ['usuario' => SIM_USUARIO, 'clave' => SIM_CLAVE, 'modo' => [], 'tokens' => [], 'tx' => [], 'pedidos' => [], 'n' => 0, 'logins' => 0];
    });
    UnoCeroUno::olvidarToken();
}
function stub_modo(array $modo) { stub(function (&$e) use ($modo) { $e['modo'] = $modo; }); }
/** Cambia campos de la transacción de una factura en el simulador. */
function stub_tx($factura, array $cambios, $idImpuesto)
{
    return stub(function (&$e) use ($factura, $cambios, $idImpuesto) {
        $k = SIM_ENTIDAD . '|' . $idImpuesto . '|' . $factura;
        if (!isset($e['tx'][$k])) { return false; }
        $e['tx'][$k] = array_merge($e['tx'][$k], $cambios);
        return true;
    });
}
/** Aprobada por el banco, con su CUS y su fecha (hora de Colombia). */
function stub_aprobar($factura, $idImpuesto, array $mas = [])
{
    return stub_tx($factura, $mas + ['EstadoTransaccion' => 'APROBADA', 'CUS' => 'CUS' . substr($factura, -6),
                                     'FechaRespuestaBanco' => gmdate('Y-m-d\TH:i:s', time() - 5 * 3600)], $idImpuesto);
}
function stub_pedidos($ruta = null)
{
    $p = stub_leer()['pedidos'];
    if ($ruta === null) { return $p; }
    return array_values(array_filter($p, function ($x) use ($ruta) { return $x['ruta'] === $ruta; }));
}
function stub_olvidar_pedidos() { stub(function (&$e) { $e['pedidos'] = []; }); }
/** Quita del simulador las transacciones de una declaración (sus facturas empiezan por su número + el dígito del módulo). */
function stub_quitar($prefijo)
{
    stub(function (&$e) use ($prefijo) {
        foreach (array_keys($e['tx']) as $k) {
            $partes = explode('|', $k);
            if (strpos((string) end($partes), $prefijo) === 0) { unset($e['tx'][$k]); }
        }
    });
}

$procStub = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . PUERTO_STUB, $raiz . '/pruebas/stub_unocerouno.php'],
                      [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
                      $pipesStub, $raiz, ['STUB_101_ESTADO' => $ESTADO_STUB] + getenv());
if (!is_resource($procStub)) { exit("No se pudo arrancar el simulador de 1cero1pay.\n"); }

/* ---------- peticiones al Apache del contenedor ---------- */
$dirSesiones = session_save_path() ?: sys_get_temp_dir();
$sesionesCreadas = [];
/** Una sesión de verdad para el Apache: devuelve su id (la cookie). */
function sesion($idUsuario)
{
    global $dirSesiones, $sesionesCreadas;
    $sid = 'prueba101' . bin2hex(random_bytes(8));
    $f = $dirSesiones . '/sess_' . $sid;
    file_put_contents($f, 'id_usuario|i:' . (int) $idUsuario . ';');
    chmod($f, 0666);
    $sesionesCreadas[] = $f;
    return $sid;
}
function _curl($metodo, $url, $post, $sid, $ajax = false)
{
    $ch = curl_init($url);
    $cab = [];
    if ($sid !== null) { $cab[] = 'Cookie: PHPSESSID=' . $sid; }
    if ($ajax) { $cab[] = 'X-Requested-With: XMLHttpRequest'; }
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
                            CURLOPT_HTTPHEADER => $cab, CURLOPT_TIMEOUT => 60]);
    if ($metodo === 'POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    return $ch;
}
function _respuesta($ch, $crudo)
{
    $n = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $cab = substr((string) $crudo, 0, $n);
    $loc = preg_match('/^Location:\s*(.+)$/mi', $cab, $m) ? trim($m[1]) : '';
    return ['code' => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE), 'location' => $loc, 'body' => substr((string) $crudo, $n)];
}
function pedir($metodo, $ruta, array $datos, $sid, $ajax = false)
{
    $url = WEB . $ruta . ($metodo === 'GET' && $datos ? (strpos($ruta, '?') === false ? '?' : '&') . http_build_query($datos) : '');
    $ch = _curl($metodo, $url, $datos, $sid, $ajax);
    $r = _respuesta($ch, curl_exec($ch));
    curl_close($ch);
    return $r;
}
/** Varias peticiones A LA VEZ. */
function juntas(array $pedidos)
{
    $mh = curl_multi_init();
    $chs = [];
    foreach ($pedidos as $p) {
        [$metodo, $ruta, $datos, $sid] = $p;
        $url = WEB . $ruta . ($metodo === 'GET' && $datos ? (strpos($ruta, '?') === false ? '?' : '&') . http_build_query($datos) : '');
        $ch = _curl($metodo, $url, $datos, $sid);
        curl_multi_add_handle($mh, $ch);
        $chs[] = $ch;
    }
    do { curl_multi_exec($mh, $activos); curl_multi_select($mh, 0.2); } while ($activos > 0);
    $r = [];
    foreach ($chs as $ch) { $r[] = _respuesta($ch, curl_multi_getcontent($ch)); curl_multi_remove_handle($mh, $ch); curl_close($ch); }
    curl_multi_close($mh);
    return $r;
}
function pagar_php($sid, $modulo, $id, $pasarela = null)
{
    return pedir('GET', 'extensiones/pse/pagar.php', ['modulo' => $modulo, 'id' => $id] + ($pasarela ? ['pasarela' => $pasarela] : []), $sid);
}
function crear($sid, $modulo, $id, array $mas = [], $pasarela = '101')
{
    return pedir('POST', 'extensiones/pse/crearSesion.php',
                 ['modulo' => $modulo, 'id' => $id, 'acepto' => '1'] + ($pasarela ? ['pasarela' => $pasarela] : []) + $mas, $sid);
}
/** El retorno con el enlace firmado del intento (el de «Verificar estado del pago»). */
function retorno($modulo, $id, $factura, $sid = null)
{
    return pedir('GET', 'extensiones/pse/' . PagoUnoCeroUno::urlRetorno('', $modulo, $id, $factura), [], $sid);
}
function texto($html) { return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $html), ENT_QUOTES, 'UTF-8'))); }

/* ---------- parámetros ---------- */
// El secreto de los enlaces existe desde la 060; en una base donde la 060 se aplicó antes de
// tenerlo, el propio código lo crea la primera vez. Se asegura ANTES de la foto de los parámetros.
UnoCeroUno::secretoEnlace();
$paramsOriginales = todas("SELECT par_Clave, par_Valor, par_Estado FROM conf_parametros WHERE par_Clave LIKE 'UNOCEROUNO[_]%'");
function param($clave, $valor)
{
    ejec('UPDATE conf_parametros SET par_Valor = ?, par_Estado = 1 WHERE par_Clave = ?', [$valor, $clave]);
    Parametros::olvidar();
}
/** Configuración completa y activa, con los valores por defecto de la migración. */
function configurar($activo = '1')
{
    foreach (['UNOCEROUNO_ACTIVO' => $activo, 'UNOCEROUNO_USUARIO' => SIM_USUARIO, 'UNOCEROUNO_CLAVE' => SIM_CLAVE,
              'UNOCEROUNO_CODIGO_ENTIDAD' => SIM_ENTIDAD, 'UNOCEROUNO_IDIMPUESTO_ICA' => '2',
              'UNOCEROUNO_IDIMPUESTO_RETEICA' => '77', 'UNOCEROUNO_IDIMPUESTO_AUTORRETEICA' => '615',
              'UNOCEROUNO_FUENTE_PAGO' => '1', 'UNOCEROUNO_TIPO_IMPLEMENTACION' => '7', 'UNOCEROUNO_CODIGO_SERVICIO' => '2016006',
              'UNOCEROUNO_DOCUMENTOS_PRUEBA' => null, 'UNOCEROUNO_BANCO' => null] as $k => $v) {
        ejec('UPDATE conf_parametros SET par_Valor = ?, par_Estado = 1 WHERE par_Clave = ?', [$v, $k]);
    }
    Parametros::olvidar();
}
/** Como recién aplicada la migración: apagado y sin credenciales. */
function desconfigurar()
{
    foreach (['UNOCEROUNO_ACTIVO' => '0', 'UNOCEROUNO_USUARIO' => null, 'UNOCEROUNO_CLAVE' => null,
              'UNOCEROUNO_CODIGO_ENTIDAD' => null, 'UNOCEROUNO_DOCUMENTOS_PRUEBA' => null, 'UNOCEROUNO_BANCO' => null] as $k => $v) {
        ejec('UPDATE conf_parametros SET par_Valor = ?, par_Estado = 1 WHERE par_Clave = ?', [$v, $k]);
    }
    Parametros::olvidar();
}

/* ---------- las declaraciones de la prueba ---------- */
$u = uno("SELECT usu_Id, usu_NumeroDocumento FROM conf_usuarios WHERE usu_Usuario = 'pruebaica'");
if (!$u) { exit("No existe el usuario de prueba pruebaica.\n"); }
$admin = uno("SELECT usu_Id, usu_NumeroDocumento FROM conf_usuarios WHERE usu_Usuario = 'administrador'");
if (!$admin) { exit("No existe el usuario administrador.\n"); }
$c = uno('SELECT TOP 1 ind_Id, ind_NumeroIdentificacion, ind_IdTipoDocumento, ind_Email, ind_Telefono
            FROM ind_contribuyentes WHERE ind_NumeroIdentificacion = ? ORDER BY ind_Id', [$u['usu_NumeroDocumento']]);
if (!$c) { exit("pruebaica no tiene contribuyente.\n"); }

/** Columnas del pago y de la sesión PSE de un módulo: lo que se guarda y se devuelve. */
function columnas(array $m)
{
    $p = $m['prefijo'];
    return ["{$p}_Estado", "{$p}_Pagado", "{$p}_FechaPago", "{$p}_FechaRealPago", "{$p}_ValorPago", "{$p}_BancoPago",
            "{$p}_AnioPago", "{$p}_RutaPago", "{$p}_IdBancoPago", "{$p}_PSE_RequestId", "{$p}_PSE_Estado",
            "{$p}_PSE_FechaEstado", "{$p}_PSE_Mensaje"];
}
$D = [];   // clave => ['m', 'id', 'numero', 'valor', 'foto']
foreach (PseModulo::claves() as $clave) {
    $m = PseModulo::get($clave);
    $f = uno("SELECT TOP 1 {$m['pk']} AS id, {$m['numero']} AS numero, {$m['valor']} AS valor
                FROM {$m['tabla']}
               WHERE {$m['prefijo']}_IdContribuyente = ? AND {$m['estado']} = 2 AND ISNULL({$m['pagado']}, 0) = 0
                 AND {$m['valor']} > 0 AND " . PseModulo::colRequestId($m) . " IS NULL AND LEN({$m['numero']}) >= 8
               ORDER BY {$m['pk']} DESC", [(int) $c['ind_Id']]);
    if (!$f) { exit("pruebaica no tiene una declaración de $clave presentada, sin pagar, con valor y sin sesión de PSE.\n"); }
    $foto = uno('SELECT ' . implode(', ', columnas($m)) . " FROM {$m['tabla']} WHERE {$m['pk']} = ?", [(int) $f['id']]);
    $D[$clave] = ['m' => $m, 'id' => (int) $f['id'], 'numero' => (string) $f['numero'], 'valor' => (float) $f['valor'], 'foto' => $foto];
}
$pelMax = (int) valor('SELECT ISNULL(MAX(pel_Id), 0) FROM ind_pagos_en_linea');
$pelAntes = (int) valor('SELECT COUNT(*) FROM ind_pagos_en_linea');

function sql_valor($v) { return $v instanceof \DateTimeInterface ? $v->format('Y-m-d\TH:i:s.v') : $v; }
/** Deja la declaración como estaba y borra los intentos creados por la prueba. */
function restaurar($clave)
{
    global $D, $pelMax;
    $d = $D[$clave];
    $sets = [];
    $vals = [];
    foreach ($d['foto'] as $col => $v) { $sets[] = "$col = ?"; $vals[] = sql_valor($v); }
    $vals[] = $d['id'];
    ejec("UPDATE {$d['m']['tabla']} SET " . implode(', ', $sets) . " WHERE {$d['m']['pk']} = ?", $vals);
    ejec('DELETE FROM ind_pagos_en_linea WHERE pel_Id > ? AND pel_Modulo = ? AND pel_IdDeclaracion = ?', [$pelMax, $clave, $d['id']]);
}
function decl($clave)
{
    global $D;
    $m = $D[$clave]['m'];
    $p = $m['prefijo'];
    return uno("SELECT ISNULL({$p}_Pagado, 0) AS pagado, {$p}_ValorPago AS valor, {$p}_RutaPago AS ruta, {$p}_BancoPago AS banco,
                       {$p}_IdBancoPago AS idBanco, {$p}_AnioPago AS anio, CONVERT(VARCHAR(23), {$p}_FechaRealPago, 121) AS registrado,
                       CONVERT(VARCHAR(19), {$p}_FechaPago, 120) AS fechaPago,
                       {$p}_PSE_RequestId AS req, {$p}_PSE_Estado AS est
                  FROM {$m['tabla']} WHERE {$m['pk']} = ?", [$D[$clave]['id']]);
}
function intentos($clave)
{
    global $D, $pelMax;
    return todas("SELECT * FROM ind_pagos_en_linea WHERE pel_Id > ? AND pel_Modulo = ? AND pel_IdDeclaracion = ? ORDER BY pel_Id",
                 [$pelMax, $clave, $D[$clave]['id']]);
}
function ultimo($clave) { $i = intentos($clave); return $i ? end($i) : null; }
function envejecer($factura, $minutos)
{
    ejec("UPDATE ind_pagos_en_linea SET pel_FechaCreacion = DATEADD(MINUTE, ?, SYSDATETIME()) WHERE pel_Referencia = ?", [-(int) $minutos, $factura]);
}
function igual_dinero($a, $b) { return abs((float) $a - (float) $b) < 0.005; }

/* Las sesiones de PlacetoPay que estén abiertas en la base local se aparcan
   mientras corre la tarea programada: si no, la tarea las consultaría contra el
   ambiente de pruebas de AvalPay, por Internet. */
$aparcadas = [];
foreach (PseModulo::claves() as $clave) {
    $m = PseModulo::get($clave);
    $req = PseModulo::colRequestId($m);
    $est = PseModulo::colEstado($m);
    foreach (todas("SELECT {$m['pk']} AS id, $est AS est FROM {$m['tabla']}
                     WHERE $req IS NOT NULL AND ISNULL({$m['pagado']}, 0) = 0 AND {$m['estado']} = 2
                       AND ISNULL($est, '') NOT IN ('REJECTED', 'EXPIRED', 'PARTIAL_EXPIRED')") as $f) {
        $aparcadas[] = [$m['tabla'], $m['pk'], $est, (int) $f['id'], $f['est']];
    }
}
function aparcar($si)
{
    global $aparcadas;
    foreach ($aparcadas as [$tabla, $pk, $est, $id, $original]) {
        ejec("UPDATE $tabla SET $est = ? WHERE $pk = ?", [$si ? 'EXPIRED' : $original, $id]);
    }
}
function tarea()
{
    global $raiz;
    aparcar(true);
    $salida = [];
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($raiz . '/extensiones/pse/cron_verificar_pagos.php') . ' 2>&1', $salida);
    aparcar(false);
    return implode("\n", $salida);
}

/* ---------- dejar todo como estaba, pase lo que pase ---------- */
$limpio = false;
function limpiar_todo()
{
    global $limpio, $D, $paramsOriginales, $CONFIG, $RESPALDO, $configOriginal, $procStub, $ESTADO_STUB, $sesionesCreadas;
    if ($limpio) { return; }
    $limpio = true;
    try {
        foreach (array_keys($D) as $clave) { restaurar($clave); }
        aparcar(false);
        foreach ($paramsOriginales as $p) {
            ejec('UPDATE conf_parametros SET par_Valor = ?, par_Estado = ? WHERE par_Clave = ?', [$p['par_Valor'], $p['par_Estado'], $p['par_Clave']]);
        }
    } catch (\Throwable $e) {
        echo "ATENCIÓN: no se pudo dejar la base como estaba: " . $e->getMessage() . "\n";
    }
    if (strpos((string) file_get_contents($CONFIG), MARCA_CONFIG) !== false) { file_put_contents($CONFIG, $configOriginal); }
    @unlink($RESPALDO);
    if (is_resource($procStub)) { proc_terminate($procStub); proc_close($procStub); }
    @unlink($ESTADO_STUB);
    foreach ($sesionesCreadas as $f) { @unlink($f); }
}
register_shutdown_function('limpiar_todo');

// El simulador responde.
stub_reiniciar();
$arriba = false;
for ($i = 0; $i < 50 && !$arriba; $i++) {
    usleep(100000);
    $h = @fsockopen('127.0.0.1', PUERTO_STUB, $en, $es, 0.3);
    if ($h) { fclose($h); $arriba = true; }
}
if (!$arriba) { exit("El simulador de 1cero1pay no arrancó en el puerto " . PUERTO_STUB . ".\n"); }

$sesU = sesion($u['usu_Id']);          // el contribuyente
$sesIca = sesion($u['usu_Id']);        // otra sesión suya, solo para el pago de la ICA (el regreso sin enlace)
$sesA = sesion($admin['usu_Id']);      // la Alcaldía (administrador)
$ICA = $D['ica']; $RET = $D['reteica']; $AUT = $D['autorreteica'];
$vencida = VencimientoICA::vencida((int) valor('SELECT dec_AnioDeclaracion FROM ind_declaraciones_ica WHERE dec_Id = ?', [$ICA['id']]));
$INTERESES = $vencida ? 15000 : 0;
echo "ICA {$ICA['numero']} (" . ($vencida ? 'vencida: lleva intereses' : 'sin vencer') . "), retención {$RET['numero']}, autorretención {$AUT['numero']}\n";

/** Listado del ICA y de retención tal como los recibe la pantalla. */
function listado_ica($sid)
{
    $r = pedir('POST', 'business/controller/class.declaracionesICA.php', ['funcion' => 8], $sid, true);
    $j = json_decode($r['body'], true);
    return is_array($j) ? ($j['datos'] ?? []) : [];
}
function listado_ret($sid)
{
    $r = pedir('POST', 'business/controller/class.reteica.php', ['funcion' => 2], $sid, true);
    $j = json_decode($r['body'], true);
    return is_array($j) ? ($j['datos'] ?? []) : [];
}
function fila_de(array $filas, $campoId, $id)
{
    foreach ($filas as $f) { if (is_array($f) && (int) ($f[$campoId] ?? 0) === (int) $id) { return $f; } }
    return null;
}

/* ======================================================================
   0. Piezas sueltas (sin red)
   ====================================================================== */
seccion('0. Piezas sueltas');
prueba('estado: los cinco del documento', UnoCeroUno::estado('APROBADA') === 'APROBADA' && UnoCeroUno::estado('fallida') === 'FALLIDA'
    && UnoCeroUno::estado(' Rechazada ') === 'RECHAZADA' && UnoCeroUno::estado('COMENZADA') === 'COMENZADA' && UnoCeroUno::estado('PENDIENTE') === 'PENDIENTE');
prueba('estado: lo que no se reconoce (o vacío) no es un pago NI un pendiente: DESCONOCIDO',
    UnoCeroUno::estado('') === 'DESCONOCIDO' && UnoCeroUno::estado('OK') === 'DESCONOCIDO' && UnoCeroUno::estado('APROBADA PARCIAL') === 'DESCONOCIDO'
    && UnoCeroUno::estado('NO APROBADA') === 'DESCONOCIDO' && UnoCeroUno::estado('NO AUTORIZADA') === 'DESCONOCIDO'
    && UnoCeroUno::estado('ABANDONADA') === 'DESCONOCIDO' && UnoCeroUno::estado('pendiente') === 'PENDIENTE');
$i = UnoCeroUno::interpretar(['EstadoTransaccion' => 'No Autorizada', 'Factura' => '1', 'Total' => 5]);
prueba('interpretar: un estado desconocido no es aprobado ni final, y conserva el texto original',
    !$i['aprobado'] && !$i['final'] && $i['estado'] === 'DESCONOCIDO' && $i['estadoOriginal'] === 'No Autorizada');
prueba('factura: número + módulo + consecutivo, solo cifras, hasta 18',
    PagoUnoCeroUno::factura('ica', '2026000017', 1) === '20260000171001'
    && PagoUnoCeroUno::factura('reteica', '2026000017', 12) === '20260000172012'
    && PagoUnoCeroUno::factura('autorreteica', '2026000017', 999) === '20260000173999'
    && PagoUnoCeroUno::factura('ica', '2026000017', 1000) === null
    && PagoUnoCeroUno::factura('ica', '-360', 1) === null
    && PagoUnoCeroUno::factura('ica', '123456789012345', 1) === null
    && PagoUnoCeroUno::factura('otro', '2026000017', 1) === null);
prueba('dirección de pago: solo https de 1cero1pay.com (o el simulador de la prueba)',
    UnoCeroUno::urlDePagoValida('https://www.1cero1pay.com/Pago/123') && UnoCeroUno::urlDePagoValida('https://1cero1pay.com/x')
    && !UnoCeroUno::urlDePagoValida('http://www.1cero1pay.com/Pago/123') && !UnoCeroUno::urlDePagoValida('https://1cero1pay.com.evil.co/x')
    && !UnoCeroUno::urlDePagoValida('https://evil1cero1pay.com/x') && !UnoCeroUno::urlDePagoValida('javascript:alert(1)')
    && !UnoCeroUno::urlDePagoValida('') && UnoCeroUno::urlDePagoValida('http://127.0.0.1:9101/pagar?tx=1'));
$i = UnoCeroUno::interpretar(['estadoTransaccion' => 'Aprobada', 'TOTAL' => '1276000.00', 'cus' => 123456, 'factura' => 20260000071001,
                              'MedioPago' => 'PSE <b>', 'FechaTransaccion' => '2026-11-21T10:00:00']);
prueba('interpretar: nombres de campo sin importar mayúsculas, valor en centavos, medio sin HTML',
    $i['aprobado'] && $i['centavos'] === 127600000 && $i['cus'] === '123456' && $i['factura'] === '20260000071001'
    && $i['banco'] === '1cero1pay - PSE b' && $i['fecha'] === '2026-11-21T10:00:00', $i);
$i = UnoCeroUno::interpretar(['EstadoTransaccion' => 'APROBADA', 'Factura' => 'X']);
prueba('interpretar: sin Total no hay valor (no se inventa)', $i['centavos'] === null && $i['valor'] === null);
$rf = new ReflectionMethod(PagoUnoCeroUno::class, '_fechaPago');
$rf->setAccessible(true);
$hoyCo = gmdate('Y-m-d', time() - 5 * 3600);
$horaCo = gmdate('H:i:s', time() - 5 * 3600 - 120);
$falso = ['pel_FechaCreacion' => new DateTime('-10 minutes')];
prueba('fecha del pago: ISO sin zona es hora de Colombia; con zona se convierte; DD/MM/AAAA; lo raro o futuro, no',
    $rf->invoke(null, $hoyCo . 'T' . $horaCo, $falso) === $hoyCo . ' ' . $horaCo
    && $rf->invoke(null, gmdate('Y-m-d\TH:i:s\Z', time() - 120), $falso) === gmdate('Y-m-d H:i:s', time() - 5 * 3600 - 120)
    && $rf->invoke(null, gmdate('d/m/Y H:i:s', time() - 5 * 3600 - 120), $falso) === $hoyCo . ' ' . $horaCo
    && $rf->invoke(null, 'ayer por la tarde', $falso) === null && $rf->invoke(null, '', $falso) === null
    && $rf->invoke(null, '2099-01-01T00:00:00', $falso) === null && $rf->invoke(null, '2020-01-01T00:00:00', $falso) === null
    && $rf->invoke(null, '31/02/2026 10:00:00', $falso) === null);

/* ======================================================================
   1. Sin configuración no hay botón ni cambia nada
   ====================================================================== */
seccion('1. Sin configuración (como recién aplicada la migración)');
desconfigurar();
stub_reiniciar();
$antesIca = fila_de(listado_ica($sesU), 'dec_Id', $ICA['id']);
$antesRet = fila_de(listado_ret($sesU), 'id', $RET['id']);
prueba('el listado del ICA trae la declaración de la prueba', $antesIca !== null);
prueba('el listado de retención trae la declaración de la prueba', $antesRet !== null);
prueba('ICA: sin segundo botón (pago_en_linea_2 = 0)', $antesIca && (int) ($antesIca['pago_en_linea_2'] ?? -1) === 0, $antesIca['pago_en_linea_2'] ?? 'no viene');
prueba('retención: sin segundo botón', $antesRet && (int) ($antesRet['pago_en_linea_2'] ?? -1) === 0);
prueba('Pasarela::segundoBoton apagado y hayUnoCeroUno falso', Pasarela::segundoBoton($u['usu_Id'])['visible'] === 0 && !Pasarela::hayUnoCeroUno());
$r = pagar_php($sesU, 'ica', $ICA['id'], '101');
prueba('pagar.php?pasarela=101 -> 503, no disponible', $r['code'] === 503 && strpos($r['body'], 'no está disponible') !== false, $r['code']);
$r = crear($sesU, 'ica', $ICA['id'], ['intereses' => $INTERESES]);
prueba('crearSesion con pasarela=101 -> 503 y no crea nada', $r['code'] === 503 && $r['location'] === '' && !intentos('ica'), $r);
$resumenAntes = pagar_php($sesU, 'reteica', $RET['id']);
prueba('pagar.php de la pasarela de siempre: el resumen normal (200, «Pagar con PSE», sin campo pasarela)',
    $resumenAntes['code'] === 200 && strpos($resumenAntes['body'], 'Pagar con PSE') !== false
    && strpos($resumenAntes['body'], 'name="pasarela"') === false && strpos($resumenAntes['body'], 'Bancolombia') === false);
$t = tarea();
prueba('la tarea programada no menciona 1cero1pay', strpos($t, '1cero1pay') === false && strpos($t, 'Revisadas:') !== false, $t);
prueba('el simulador no recibió ninguna petición', count(stub_pedidos()) === 0, count(stub_pedidos()));

// Con credenciales pero apagado: tampoco.
configurar('0');
$f = fila_de(listado_ica($sesU), 'dec_Id', $ICA['id']);
prueba('con credenciales pero UNOCEROUNO_ACTIVO = 0: sin botón', $f && (int) $f['pago_en_linea_2'] === 0 && !UnoCeroUno::botonVisible($u['usu_Id']));
$r = pagar_php($sesU, 'ica', $ICA['id'], '101');
prueba('  y pagar.php?pasarela=101 sigue en 503', $r['code'] === 503);
// Activo pero sin la clave: tampoco.
configurar('1');
param('UNOCEROUNO_CLAVE', null);
prueba('activo pero sin la clave: sin botón', !UnoCeroUno::botonVisible($u['usu_Id']) && crear($sesU, 'ica', $ICA['id'], ['intereses' => $INTERESES])['code'] === 503);
// Los parámetros desactivados (como una base sin la migración): nada.
ejec("UPDATE conf_parametros SET par_Estado = 0 WHERE par_Clave LIKE 'UNOCEROUNO[_]%'");
Parametros::olvidar();
$f = fila_de(listado_ica($sesU), 'dec_Id', $ICA['id']);
prueba('sin los parámetros (base sin la 060): sin botón y el primero igual',
    $f && (int) $f['pago_en_linea_2'] === 0 && (int) $f['pago_en_linea'] === (int) $antesIca['pago_en_linea']
    && $f['pago_en_linea_texto'] === $antesIca['pago_en_linea_texto'] && !Pasarela::hayUnoCeroUno());
ejec("UPDATE conf_parametros SET par_Estado = 1 WHERE par_Clave LIKE 'UNOCEROUNO[_]%'");
prueba('nada de lo anterior llegó al simulador ni creó intentos', count(stub_pedidos()) === 0 && !intentos('ica'));

/* ======================================================================
   2. Quién ve el botón
   ====================================================================== */
seccion('2. Configurado: quién ve el botón «PSE · Bancolombia»');
configurar('1');

// Para que el Apache del contenedor hable con el simulador.
file_put_contents($RESPALDO, $configOriginal);
file_put_contents($CONFIG, rtrim($configOriginal) . "\nif (!defined('UNOCEROUNO_API_BASE')) define('UNOCEROUNO_API_BASE', '" . API_STUB . "'); " . MARCA_CONFIG . "\n");
sleep(3);   // opcache del Apache revisa el archivo cada 2 segundos

$fi = fila_de(listado_ica($sesU), 'dec_Id', $ICA['id']);
$fr = fila_de(listado_ret($sesU), 'id', $RET['id']);
prueba('ICA: pago_en_linea_2 = 1 con su texto', $fi && (int) $fi['pago_en_linea_2'] === 1 && $fi['pago_en_linea_2_texto'] === 'PSE · Bancolombia', $fi['pago_en_linea_2'] ?? null);
prueba('retención: pago_en_linea_2 = 1', $fr && (int) $fr['pago_en_linea_2'] === 1 && $fr['pago_en_linea_2_texto'] === 'PSE · Bancolombia');
prueba('el botón de la pasarela de siempre no cambió (ni si se ve ni su texto)',
    $fi && (int) $fi['pago_en_linea'] === (int) $antesIca['pago_en_linea'] && $fi['pago_en_linea_texto'] === $antesIca['pago_en_linea_texto']
    && $fr && (int) $fr['pago_en_linea'] === (int) $antesRet['pago_en_linea'] && $fr['pago_en_linea_texto'] === $antesRet['pago_en_linea_texto']);
$r = pagar_php($sesU, 'reteica', $RET['id'], '101');
prueba('pagar.php?pasarela=101: resumen con «Pagar con PSE · Bancolombia», el campo pasarela y sin logo de AvalPay',
    $r['code'] === 200 && strpos($r['body'], 'Pagar con PSE · Bancolombia') !== false
    && strpos($r['body'], 'name="pasarela" value="101"') !== false && strpos($r['body'], 'Logo%20Avalpay') === false
    && strpos($r['body'], '1cero1pay') !== false, $r['code']);
$r = pagar_php($sesU, 'reteica', $RET['id']);
prueba('pagar.php sin pasarela: idéntico al de antes de configurar', $r['code'] === 200 && $r['body'] === $resumenAntes['body']);
prueba('sin sesión: 403 en pagar.php y en crearSesion', pagar_php(null, 'reteica', $RET['id'], '101')['code'] === 403
    && crear(null, 'reteica', $RET['id'])['code'] === 403);

// Documentos de prueba.
param('UNOCEROUNO_DOCUMENTOS_PRUEBA', '99999999, 88888888');
$fi = fila_de(listado_ica($sesU), 'dec_Id', $ICA['id']);
prueba('documentos de prueba ajenos: pruebaica NO ve el botón', $fi && (int) $fi['pago_en_linea_2'] === 0);
prueba('  y el de la pasarela de siempre sigue igual', $fi && (int) $fi['pago_en_linea'] === (int) $antesIca['pago_en_linea']);
$r = pagar_php($sesU, 'reteica', $RET['id'], '101');
prueba('  pagar.php?pasarela=101 -> 403', $r['code'] === 403, $r['code']);
$r = crear($sesU, 'reteica', $RET['id']);
prueba('  crearSesion -> 403 y no crea nada', $r['code'] === 403 && !intentos('reteica') && count(stub_pedidos()) === 0);
param('UNOCEROUNO_DOCUMENTOS_PRUEBA', '99999999, ' . $u['usu_NumeroDocumento']);
$fi = fila_de(listado_ica($sesU), 'dec_Id', $ICA['id']);
prueba('con su documento en la lista: pruebaica SÍ lo ve', $fi && (int) $fi['pago_en_linea_2'] === 1 && pagar_php($sesU, 'reteica', $RET['id'], '101')['code'] === 200);
prueba('  y el administrador (otro documento) no', Pasarela::segundoBoton($admin['usu_Id'])['visible'] === 0
    && pagar_php($sesA, 'reteica', $RET['id'], '101')['code'] === 403 && crear($sesA, 'reteica', $RET['id'])['code'] === 403);
param('UNOCEROUNO_DOCUMENTOS_PRUEBA', null);
prueba('lista vacía: lo ven todos (también el administrador)', Pasarela::segundoBoton($admin['usu_Id'])['visible'] === 1
    && pagar_php($sesA, 'reteica', $RET['id'], '101')['code'] === 200);

/* ======================================================================
   3. Token
   ====================================================================== */
seccion('3. Token');
stub_reiniciar();
$tk = UnoCeroUno::token();
$p = stub_pedidos('/api/Login/authenticate');
prueba('Login/authenticate con Username y Password', count($p) === 1 && $p[0]['metodo'] === 'POST'
    && $p[0]['cuerpo'] === ['Username' => SIM_USUARIO, 'Password' => SIM_CLAVE] && strlen($tk) > 5);
prueba('el token se reutiliza dentro del mismo proceso', UnoCeroUno::token() === $tk && count(stub_pedidos('/api/Login/authenticate')) === 1);
// Token vencido: el simulador los invalida; la consulta contesta 401 y se pide otro.
stub(function (&$e) { foreach ($e['tokens'] as $k => $v) { $e['tokens'][$k] = time() - 1; } });
$lista = null;
try { $lista = UnoCeroUno::consultar('1', 2); } catch (Exception $e) { $lista = $e->getMessage(); }
$l = stub_leer();
prueba('token vencido (401): se autentica otra vez y repite la consulta', $lista === [] && $l['logins'] === 2
    && count(stub_pedidos('/api/transaction')) === 2 && UnoCeroUno::token() !== $tk, $lista);
stub_modo(['tokenCrudo' => true]);
UnoCeroUno::olvidarToken();
$tk2 = UnoCeroUno::token();
prueba('acepta el token como cuerpo entero (así contestaba el sistema anterior)', preg_match('/^tok\d+[0-9a-f]+$/', $tk2) === 1, $tk2);
stub_modo([]);
param('UNOCEROUNO_CLAVE', 'otra_clave_que_no_es');
UnoCeroUno::olvidarToken();
$msj = '';
try { UnoCeroUno::token(); } catch (Exception $e) { $msj = $e->getMessage(); }
prueba('credenciales equivocadas: error, sin la clave en el mensaje', $msj !== '' && strpos($msj, 'otra_clave') === false && strpos($msj, SIM_USUARIO) === false, $msj);
configurar('1');
UnoCeroUno::olvidarToken();

/* ======================================================================
   4. Crear la transacción, por módulo
   ====================================================================== */
seccion('4. Crear la transacción: datos correctos por módulo');
stub_reiniciar();
$totalIca = $ICA['valor'] + $INTERESES;
if ($vencida) {
    $r = crear($sesU, 'ica', $ICA['id']);
    prueba('ICA vencida sin intereses (contribuyente): vuelve al resumen y no crea nada',
        $r['code'] === 302 && strpos($r['location'], 'pagar.php') === 0 && strpos($r['location'], 'pasarela=101') !== false && !intentos('ica'), $r);
    $r = pagar_php($sesU, 'ica', $ICA['id'], '101');
    prueba('  el resumen de «PSE · Bancolombia» pide los intereses de mora, como el de siempre',
        $r['code'] === 200 && strpos($r['body'], 'Intereses de mora a hoy') !== false && strpos($r['body'], 'required') !== false);
}
$r = pedir('GET', 'extensiones/pse/crearSesion.php', ['modulo' => 'ica', 'id' => $ICA['id'], 'acepto' => 1, 'pasarela' => '101'], $sesU);
prueba('crearSesion por GET no crea nada: al resumen', $r['code'] === 302 && strpos($r['location'], 'pagar.php') === 0 && !intentos('ica'));
$r = pedir('POST', 'extensiones/pse/crearSesion.php', ['modulo' => 'ica', 'id' => $ICA['id'], 'pasarela' => '101'], $sesU);
prueba('sin aceptar la política: al resumen', $r['code'] === 302 && strpos($r['location'], 'pagar.php') === 0 && !intentos('ica'));
prueba('  nada de eso llegó al simulador', count(stub_pedidos()) === 0);

$r = crear($sesIca, 'ica', $ICA['id'], $vencida ? ['intereses' => number_format($INTERESES, 0, ',', '.')] : []);
$facIca = $ICA['numero'] . '1001';
$ins = stub_pedidos('/api/transaction/InsertTransaction');
$b = $ins[0]['cuerpo'] ?? [];
prueba('ICA: redirige a la dirección de pago que dio 1cero1pay', $r['code'] === 302 && strpos($r['location'], 'http://127.0.0.1:9101/pagar?tx=') === 0, $r);
prueba('ICA: Factura = número + 1 + 001 y Referencia = número de la declaración',
    ($b['Factura'] ?? null) === $facIca && ($b['Referencia'] ?? null) === $ICA['numero'], $b);
prueba('ICA: IDImpuesto 2, FuentePago 1, TipoImplementacion 7 y el código de entidad',
    ($b['IDImpuesto'] ?? null) === 2 && ($b['FuentePago'] ?? null) === 1 && ($b['TipoImplementacion'] ?? null) === 7 && ($b['CodigoEntidad'] ?? null) === SIM_ENTIDAD, $b);
prueba('ICA: Total = valor de la declaración' . ($vencida ? ' + intereses de mora' : ''), igual_dinero($b['Total'] ?? -1, $totalIca), $b['Total'] ?? null);
$tipoEsperado = [1 => 1, 3 => 3, 4 => 1, 5 => 2][(int) $c['ind_IdTipoDocumento']] ?? 1;
prueba('ICA: Pagador = el contribuyente (tipo, documento, nombre, correo, teléfono)',
    ($b['Pagador']['TipoDocumento'] ?? null) === $tipoEsperado && ($b['Pagador']['Identificacion'] ?? null) === (string) $c['ind_NumeroIdentificacion']
    && trim((string) ($b['Pagador']['Nombre'] ?? '')) !== '' && ($b['Pagador']['Email'] ?? null) === trim((string) $c['ind_Email'])
    && ($b['Pagador']['Telefono'] ?? null) === preg_replace('/\D/', '', (string) $c['ind_Telefono']), $b['Pagador'] ?? null);
prueba('ICA: la petición lleva el token Bearer que entregó el login', ($ins[0]['token'] ?? '') !== '' && isset(stub_leer()['tokens'][$ins[0]['token']]));
prueba('ICA: no se manda el código de servicio (la API no lo pide)', !array_key_exists('CodigoServicio', $b) && strpos(json_encode($b), '2016006') === false);
$i = ultimo('ica');
prueba('ICA: el intento queda COMENZADA con sus centavos, intereses, IDImpuesto y quién lo inició',
    $i && $i['pel_Pasarela'] === '1CERO1PAY' && $i['pel_Estado'] === 'COMENZADA' && $i['pel_Referencia'] === $facIca
    && (int) $i['pel_Centavos'] === (int) round($totalIca * 100) && igual_dinero($i['pel_Intereses'], $INTERESES)
    && (int) $i['pel_IdImpuesto'] === 2 && (int) $i['pel_IdUsuario'] === (int) $u['usu_Id'] && trim((string) $i['pel_Transaccion']) !== '', $i);
prueba('ICA: la declaración no cambió (ni pagada ni columnas de PSE)', (int) decl('ica')['pagado'] === 0 && decl('ica')['req'] === null && decl('ica')['est'] === null);

$r = crear($sesU, 'reteica', $RET['id'], ['intereses' => '5000']);
prueba('retención con intereses escritos: rechazado (no lleva), sin intento', strpos($r['body'], 'no lleva intereses') !== false && !intentos('reteica'), $r['body']);
$r = crear($sesU, 'reteica', $RET['id']);
$facRet = $RET['numero'] . '2001';
$b = stub_pedidos('/api/transaction/InsertTransaction')[1]['cuerpo'] ?? [];
prueba('retención: IDImpuesto 77, Factura número + 2 + 001, Total = valor de la declaración',
    $r['code'] === 302 && ($b['IDImpuesto'] ?? null) === 77 && ($b['Factura'] ?? null) === $facRet && ($b['Referencia'] ?? null) === $RET['numero']
    && igual_dinero($b['Total'] ?? -1, $RET['valor']), $b);
$r = crear($sesU, 'autorreteica', $AUT['id']);
$facAut = $AUT['numero'] . '3001';
$b = stub_pedidos('/api/transaction/InsertTransaction')[2]['cuerpo'] ?? [];
prueba('autorretención: IDImpuesto 615, Factura número + 3 + 001, Total = valor de la declaración',
    $r['code'] === 302 && ($b['IDImpuesto'] ?? null) === 615 && ($b['Factura'] ?? null) === $facAut && igual_dinero($b['Total'] ?? -1, $AUT['valor']), $b);
prueba('los totales viajan como número (no como texto)', is_int($b['Total'] ?? null) || is_float($b['Total'] ?? null));

/* ======================================================================
   5. Pago en trámite: no se inicia otro, por ninguna pasarela
   ====================================================================== */
seccion('5. Recién iniciado: un solo pago en curso, también entre pasarelas');
$n = count(stub_pedidos('/api/transaction/InsertTransaction'));
$r = crear($sesU, 'reteica', $RET['id']);
prueba('otro clic en «PSE · Bancolombia»: al resumen, sin crear otro intento ni otra transacción',
    $r['code'] === 302 && strpos($r['location'], 'pagar.php') === 0 && count(intentos('reteica')) === 1
    && count(stub_pedidos('/api/transaction/InsertTransaction')) === $n, $r);
$r = pagar_php($sesU, 'reteica', $RET['id'], '101');
prueba('el resumen dice «Pago iniciado» y ofrece verificar', $r['code'] === 200 && strpos($r['body'], 'se inició un pago') !== false
    && strpos($r['body'], 'retorno.php?pasarela=101') !== false && strpos($r['body'], 'id="formPago"') === false, texto($r['body']));
$r = pagar_php($sesU, 'reteica', $RET['id']);
prueba('el resumen de la OTRA pasarela también: no ofrece pagar', $r['code'] === 200 && strpos($r['body'], 'se inició un pago') !== false
    && strpos($r['body'], 'id="formPago"') === false);
$r = crear($sesU, 'reteica', $RET['id'], [], null);
prueba('crearSesion de la pasarela de siempre: al resumen, sin crear su sesión',
    $r['code'] === 302 && strpos($r['location'], 'pagar.php') === 0 && decl('reteica')['req'] === null, $r);
$anotada = PagoUnoCeroUno::siLibre($con, $RET['m'], $RET['id'], function () use ($con, $RET) {
    return PlacetoPay::anotarSesion($con, $RET['m'], $RET['id'], 990001);
});
prueba('con el candado: la sesión de PlacetoPay no se anota mientras 1cero1pay tenga el pago en curso', $anotada === null && decl('reteica')['req'] === null);
include_once SERVER . '/business/class.registroManual.php';
$rm = new ReflectionMethod(\erpsoftsas\RegistroManual::class, '_pagoEnLinea');
$rm->setAccessible(true);
$motivo = $rm->invoke(null, $con, $RET['m'], $RET['id'], ['pse_req' => null, 'pse_est' => null]);
prueba('el pago manual de la Alcaldía también espera', is_string($motivo) && strpos($motivo, 'PSE · Bancolombia') !== false, $motivo);
$motivo = $rm->invoke(null, $con, $RET['m'], $RET['id'], ['pse_req' => null, 'pse_est' => null], true);
prueba('  también con su candado tomado (sin preguntar a 1cero1pay)', is_string($motivo));

// 45 minutos después, sin haber pagado: se puede iniciar otro.
envejecer($facRet, 50);
$r = pagar_php($sesU, 'reteica', $RET['id'], '101');
prueba('pasados 45 minutos sin pagar: el resumen vuelve a ofrecer el pago', $r['code'] === 200 && strpos($r['body'], 'id="formPago"') !== false);
$r = crear($sesU, 'reteica', $RET['id']);
$facRet2 = $RET['numero'] . '2002';
$b = stub_pedidos('/api/transaction/InsertTransaction');
$b = end($b)['cuerpo'] ?? [];
prueba('  y el intento nuevo lleva otra factura (…002)', $r['code'] === 302 && strpos($r['location'], 'http://127.0.0.1:9101/') === 0
    && ($b['Factura'] ?? null) === $facRet2 && count(intentos('reteica')) === 2, $b);

/* ======================================================================
   6. Aprobada: el pago se registra UNA vez
   ====================================================================== */
seccion('6. Aprobada: el pago se registra una sola vez');
// Regreso sin enlace firmado y sin sesión: nada.
stub_olvidar_pedidos();
$r = pedir('GET', 'extensiones/pse/retorno.php', ['pasarela' => '101', 'modulo' => 'ica', 'decl' => $ICA['id'], 'ref' => $facIca, 't' => str_repeat('a', 64)], null);
prueba('retorno con firma inventada: no verifica nada ni pregunta a 1cero1pay',
    $r['code'] === 200 && strpos($r['body'], 'No se pudo verificar este enlace') !== false && count(stub_pedidos()) === 0);
$r = pedir('GET', 'extensiones/pse/retorno.php', ['pasarela' => '101', 'modulo' => 'ica', 'decl' => $ICA['id'], 'ref' => $facIca], $sesA);
prueba('retorno sin firma desde OTRA sesión: tampoco', strpos($r['body'], 'No se pudo verificar este enlace') !== false && count(stub_pedidos()) === 0);

// Todavía COMENZADA.
$r = retorno('ica', $ICA['id'], $facIca);
prueba('retorno antes de pagar: «todavía no se ha hecho el pago», nada registrado',
    strpos($r['body'], 'Todavía no se ha hecho el pago') !== false && (int) decl('ica')['pagado'] === 0 && ultimo('ica')['pel_Estado'] === 'COMENZADA', texto($r['body']));
$q = stub_pedidos('/api/transaction');
prueba('la consulta va por CodigoEntidad, Factura e IDImpuesto del intento',
    count($q) === 1 && ($q[0]['query'] ?? []) == ['CodigoEntidad' => SIM_ENTIDAD, 'Factura' => $facIca, 'IDImpuesto' => '2'], $q[0]['query'] ?? null);

stub_aprobar($facIca, 2);
// El regreso SIN enlace firmado: lo verifica la sesión que inició el pago.
$r = pedir('GET', 'extensiones/pse/retorno.php', ['pasarela' => '101'], $sesIca);
$d = decl('ica');
prueba('retorno sin enlace, con la sesión que inició el pago: «Pago aprobado»', strpos($r['body'], 'Pago aprobado. Gracias.') !== false
    && strpos($r['body'], 'Pago procesado') !== false, texto($r['body']));
prueba('la declaración queda pagada por el valor exacto' . ($vencida ? ' (con los intereses)' : ''),
    (int) $d['pagado'] === 1 && igual_dinero($d['valor'], $totalIca), $d);
prueba('  vía 1CERO1PAY, banco «1cero1pay - PSE», año y fecha de pago de hoy (Colombia)',
    $d['ruta'] === '1CERO1PAY' && $d['banco'] === '1cero1pay - PSE' && (int) $d['anio'] === (int) substr($hoyCo, 0, 4)
    && substr((string) $d['fechaPago'], 0, 10) === $hoyCo, $d);
prueba('  sin banco elegido (UNOCEROUNO_BANCO vacío) no se le pone el de la otra pasarela', $d['idBanco'] === null, $d['idBanco']);
prueba('  y las columnas de la sesión de PlacetoPay no se tocaron', $d['req'] === null && $d['est'] === null);
$i = ultimo('ica');
prueba('el intento queda APROBADA con el CUS', $i['pel_Estado'] === 'APROBADA' && strpos((string) $i['pel_Mensaje'], 'CUS') !== false && $i['pel_Medio'] === 'PSE', $i);
prueba('el valor mostrado es el cobrado', strpos($r['body'], '$ ' . number_format($totalIca, 0, ',', '.') . ' COP') !== false);

// Repetir no duplica.
$registrado = $d['registrado'];
usleep(300000);
$r = retorno('ica', $ICA['id'], $facIca);
$d2 = decl('ica');
prueba('repetir el retorno: sigue «Pago aprobado» y NO se registra otra vez (misma fecha de registro, mismo valor)',
    strpos($r['body'], 'Pago aprobado. Gracias.') !== false && $d2['registrado'] === $registrado && igual_dinero($d2['valor'], $totalIca)
    && ultimo('ica')['pel_Estado'] === 'APROBADA', $d2);
$t = tarea();
$d3 = decl('ica');
prueba('la tarea después: nada que registrar para esa factura', $d3['registrado'] === $registrado && strpos($t, $facIca) === false, $t);
$rs = juntas([['GET', 'extensiones/pse/' . PagoUnoCeroUno::urlRetorno('', 'ica', $ICA['id'], $facIca), [], null],
              ['GET', 'extensiones/pse/' . PagoUnoCeroUno::urlRetorno('', 'ica', $ICA['id'], $facIca), [], null],
              ['GET', 'extensiones/pse/' . PagoUnoCeroUno::urlRetorno('', 'ica', $ICA['id'], $facIca), [], null]]);
prueba('tres retornos a la vez: los tres «aprobado», un solo pago', count(array_filter($rs, function ($x) { return strpos($x['body'], 'Pago aprobado. Gracias.') !== false; })) === 3
    && decl('ica')['registrado'] === $registrado && count(intentos('ica')) === 1);
$r = pagar_php($sesU, 'ica', $ICA['id'], '101');
prueba('el resumen de una pagada: «ya está registrada como pagada»', strpos($r['body'], 'ya está registrada como pagada') !== false);
$r = crear($sesU, 'ica', $ICA['id'], ['intereses' => $INTERESES]);
prueba('crearSesion de una pagada: «ya está pagada», sin intento nuevo', strpos($r['body'], 'ya está pagada') !== false && count(intentos('ica')) === 1);
$fi = fila_de(listado_ica($sesU), 'dec_Id', $ICA['id']);
prueba('el listado la muestra pagada', $fi && (int) ($fi['dec_Pagado'] ?? 0) === 1, $fi['dec_Pagado'] ?? null);

// Dos aprobaciones a la vez desde cero (retorno y tarea llegan juntos).
restaurar('ica');
stub_quitar($ICA['numero'] . '1');
crear($sesA, 'ica', $ICA['id'], ['intereses' => '0']);          // la Alcaldía puede cobrar sin intereses
$i = ultimo('ica');
prueba('la Alcaldía puede iniciar el pago de la ICA con intereses en 0 (como en el recibo)',
    $i && $i['pel_Estado'] === 'COMENZADA' && (int) $i['pel_Centavos'] === (int) round($ICA['valor'] * 100) && (int) $i['pel_IdUsuario'] === (int) $admin['usu_Id'], $i);
stub_aprobar($facIca, 2);
$u1 = 'extensiones/pse/' . PagoUnoCeroUno::urlRetorno('', 'ica', $ICA['id'], $facIca);
$rs = juntas([['GET', $u1, [], null], ['GET', $u1, [], null], ['GET', $u1, [], null], ['GET', $u1, [], null]]);
$t = tarea();
$d = decl('ica');
prueba('cuatro retornos simultáneos sobre un intento sin aplicar + la tarea: un solo pago, intento APROBADA (no DOBLE)',
    (int) $d['pagado'] === 1 && igual_dinero($d['valor'], $ICA['valor']) && ultimo('ica')['pel_Estado'] === 'APROBADA'
    && count(array_filter($rs, function ($x) { return strpos($x['body'], 'Pago aprobado. Gracias.') !== false; })) === 4, [$d, array_column($rs, 'code')]);

/* ======================================================================
   7. Rechazada y fallida
   ====================================================================== */
seccion('7. Rechazada y fallida');
stub_tx($facAut, ['EstadoTransaccion' => 'RECHAZADA'], 615);
$r = retorno('autorreteica', $AUT['id'], $facAut);
prueba('rechazada: «El pago no fue aprobado», la declaración sigue sin pagar', strpos($r['body'], 'El pago no fue aprobado') !== false
    && strpos($r['body'], 'Rechazado') !== false && (int) decl('autorreteica')['pagado'] === 0 && ultimo('autorreteica')['pel_Estado'] === 'RECHAZADA', texto($r['body']));
$r = pagar_php($sesU, 'autorreteica', $AUT['id'], '101');
prueba('  el resumen vuelve a ofrecer el pago (por las dos pasarelas)', strpos($r['body'], 'id="formPago"') !== false
    && strpos(pagar_php($sesU, 'autorreteica', $AUT['id'])['body'], 'id="formPago"') !== false);
$r = crear($sesU, 'autorreteica', $AUT['id']);
$facAut2 = $AUT['numero'] . '3002';
prueba('  y se puede iniciar otro, con factura nueva (…002)', $r['code'] === 302 && ultimo('autorreteica')['pel_Referencia'] === $facAut2);
stub_tx($facAut2, ['EstadoTransaccion' => 'FALLIDA'], 615);
$r = retorno('autorreteica', $AUT['id'], $facAut2);
prueba('fallida: no aprobado, sin pagar, intento FALLIDA', strpos($r['body'], 'El pago no fue aprobado') !== false && strpos($r['body'], 'Fallido') !== false
    && (int) decl('autorreteica')['pagado'] === 0 && ultimo('autorreteica')['pel_Estado'] === 'FALLIDA');
// Un rechazo que después 1cero1pay da por aprobado (la tarea vuelve a mirar los rechazados recientes).
stub_aprobar($facAut2, 615);
$t = tarea();
prueba('un rechazado que 1cero1pay aprueba después: la tarea lo registra', (int) decl('autorreteica')['pagado'] === 1
    && ultimo('autorreteica')['pel_Estado'] === 'APROBADA' && strpos($t, "[1cero1pay] $facAut2: APROBADA, pago registrado.") !== false, $t);
restaurar('autorreteica');

/* ======================================================================
   8. Pendiente que luego aprueba la tarea
   ====================================================================== */
seccion('8. Pendiente que después aprueba la tarea programada');
param('UNOCEROUNO_BANCO', (string) valor("SELECT TOP 1 ban_Id FROM ind_bancos WHERE ban_Codigo = '007' AND ISNULL(ban_Activo, 1) = 1"));
$idBancolombia = (int) Parametros::valor('UNOCEROUNO_BANCO');
stub_tx($facRet2, ['EstadoTransaccion' => 'PENDIENTE'], 77);
$r = retorno('reteica', $RET['id'], $facRet2);
prueba('pendiente: «El pago quedó en proceso», sin pagar', strpos($r['body'], 'El pago quedó en proceso') !== false && strpos($r['body'], 'Pendiente') !== false
    && (int) decl('reteica')['pagado'] === 0 && ultimo('reteica')['pel_Estado'] === 'PENDIENTE', texto($r['body']));
envejecer($facRet2, 600);   // diez horas después sigue frenando: un PENDIENTE no caduca solo
$r = pagar_php($sesU, 'reteica', $RET['id'], '101');
prueba('el resumen dice «PENDIENTE» y no ofrece pagar (las dos pasarelas)', strpos($r['body'], 'PENDIENTE') !== false && strpos($r['body'], 'id="formPago"') === false
    && strpos(pagar_php($sesU, 'reteica', $RET['id'])['body'], 'id="formPago"') === false);
$n = count(intentos('reteica'));
prueba('no se inicia otro pago por ninguna de las dos', crear($sesU, 'reteica', $RET['id'])['code'] === 302 && crear($sesU, 'reteica', $RET['id'], [], null)['code'] === 302
    && count(intentos('reteica')) === $n && decl('reteica')['req'] === null);
$t = tarea();
prueba('la tarea, mientras siga pendiente: lo anota y no registra nada', strpos($t, "[1cero1pay] $facRet2: PENDIENTE.") !== false && (int) decl('reteica')['pagado'] === 0, $t);
stub_aprobar($facRet2, 77, ['MedioPago' => 'PSE']);
$t = tarea();
$d = decl('reteica');
prueba('la tarea, ya aprobada: «pago registrado» y la declaración queda pagada por el valor exacto',
    strpos($t, "[1cero1pay] $facRet2: APROBADA, pago registrado.") !== false && (int) $d['pagado'] === 1 && igual_dinero($d['valor'], $RET['valor']) && $d['ruta'] === '1CERO1PAY', $t);
prueba('  con el banco de UNOCEROUNO_BANCO', (int) $d['idBanco'] === $idBancolombia && $idBancolombia > 0, $d['idBanco']);
prueba('  y la cuenta de la tarea lo dice («actualizadas a pagada: 1»)', preg_match('/actualizadas a pagada: 1\./', $t) === 1, $t);
$registrado = $d['registrado'];
usleep(300000);
$t = tarea();
prueba('correr la tarea otra vez no duplica', decl('reteica')['registrado'] === $registrado && preg_match('/actualizadas a pagada: 0\./', $t) === 1
    && strpos($t, $facRet2) === false, $t);
param('UNOCEROUNO_BANCO', null);
restaurar('reteica');

/* ======================================================================
   9. Valor distinto, sin valor, otra factura: no se registra, queda REVISAR
   ====================================================================== */
seccion('9. Lo dudoso no se registra: queda para revisión');
stub_reiniciar();
crear($sesU, 'reteica', $RET['id']);
$fac = ultimo('reteica')['pel_Referencia'];
stub_aprobar($fac, 77, ['Total' => $RET['valor'] - 1000]);
$r = retorno('reteica', $RET['id'], $fac);
$i = ultimo('reteica');
prueba('aprobada por OTRO valor: no se registra el pago', (int) decl('reteica')['pagado'] === 0 && $i['pel_Estado'] === 'REVISAR'
    && strpos((string) $i['pel_Mensaje'], 'No se registró el pago') !== false, $i);
prueba('  el contribuyente ve que debe comunicarse (no «aprobado»)', strpos($r['body'], 'no se pudo registrar automáticamente') !== false
    && strpos($r['body'], 'Pago aprobado. Gracias.') === false && strpos($r['body'], 'En revisión') !== false, texto($r['body']));
$r = pagar_php($sesU, 'reteica', $RET['id'], '101');
prueba('  el resumen dice «Pago en revisión» y no deja pagar otra vez (ninguna pasarela)', strpos($r['body'], 'está revisando') !== false
    && strpos($r['body'], 'id="formPago"') === false && strpos(pagar_php($sesU, 'reteica', $RET['id'])['body'], 'id="formPago"') === false);
prueba('  crearSesion de las dos: al resumen, sin nada nuevo', crear($sesU, 'reteica', $RET['id'])['code'] === 302
    && crear($sesU, 'reteica', $RET['id'], [], null)['code'] === 302 && count(intentos('reteica')) === 1 && decl('reteica')['req'] === null);
$motivo = $rm->invoke(null, $con, $RET['m'], $RET['id'], ['pse_req' => null, 'pse_est' => null]);
prueba('  el pago manual de la Alcaldía NO se frena (es como se resuelve)', $motivo === null, $motivo);
$t = tarea();
prueba('  la tarea lo avisa con ATENCIÓN', strpos($t, "[1cero1pay] ATENCIÓN $fac (reteica {$RET['id']}, REVISAR)") !== false && (int) decl('reteica')['pagado'] === 0, $t);
stub_aprobar($fac, 77, ['Total' => $RET['valor']]);
$r = retorno('reteica', $RET['id'], $fac);
prueba('  si 1cero1pay corrige el valor y se vuelve a verificar, entonces sí se registra',
    (int) decl('reteica')['pagado'] === 1 && ultimo('reteica')['pel_Estado'] === 'APROBADA' && strpos($r['body'], 'Pago aprobado. Gracias.') !== false);
restaurar('reteica'); stub_quitar($RET['numero'] . '2');

crear($sesU, 'reteica', $RET['id']);
$fac = ultimo('reteica')['pel_Referencia'];
stub_aprobar($fac, 77, ['Total' => $RET['valor'] + 0.5]);
retorno('reteica', $RET['id'], $fac);
prueba('aprobada por 50 centavos más: tampoco', (int) decl('reteica')['pagado'] === 0 && ultimo('reteica')['pel_Estado'] === 'REVISAR');
restaurar('reteica'); stub_quitar($RET['numero'] . '2');

crear($sesU, 'reteica', $RET['id']);
$fac = ultimo('reteica')['pel_Referencia'];
stub_aprobar($fac, 77, ['Total' => null]);
retorno('reteica', $RET['id'], $fac);
$i = ultimo('reteica');
prueba('aprobada SIN valor: no se registra, REVISAR', (int) decl('reteica')['pagado'] === 0 && $i['pel_Estado'] === 'REVISAR'
    && strpos((string) $i['pel_Mensaje'], 'sin informar el valor') !== false, $i);
restaurar('reteica'); stub_quitar($RET['numero'] . '2');

crear($sesU, 'reteica', $RET['id']);
$fac = ultimo('reteica')['pel_Referencia'];
stub_tx($fac, ['EstadoTransaccion' => 'PENDIENTE', 'Factura' => '99999999999'], 77);
$r = retorno('reteica', $RET['id'], $fac);
prueba('respuesta de OTRA factura, sin aprobar: no se aplica; el intento sigue abierto',
    (int) decl('reteica')['pagado'] === 0 && ultimo('reteica')['pel_Estado'] === 'COMENZADA'
    && strpos((string) ultimo('reteica')['pel_Mensaje'], 'otra factura (99999999999)') !== false, ultimo('reteica'));
stub_aprobar($fac, 77, ['Factura' => '99999999999']);
$r = retorno('reteica', $RET['id'], $fac);
$i = ultimo('reteica');
prueba('respuesta de OTRA factura, aprobada y por el mismo valor: NO se registra, REVISAR',
    (int) decl('reteica')['pagado'] === 0 && $i['pel_Estado'] === 'REVISAR' && strpos((string) $i['pel_Mensaje'], 'otra factura') !== false
    && strpos($r['body'], 'Pago aprobado. Gracias.') === false, $i);
restaurar('reteica'); stub_quitar($RET['numero'] . '2');

crear($sesU, 'reteica', $RET['id']);
$fac = ultimo('reteica')['pel_Referencia'];
stub_aprobar($fac, 77, ['Factura' => null]);
retorno('reteica', $RET['id'], $fac);
prueba('aprobada pero la respuesta no trae la factura: no se registra, REVISAR', (int) decl('reteica')['pagado'] === 0 && ultimo('reteica')['pel_Estado'] === 'REVISAR');
restaurar('reteica'); stub_quitar($RET['numero'] . '2');

crear($sesU, 'reteica', $RET['id']);
$fac = ultimo('reteica')['pel_Referencia'];
stub_tx($fac, ['EstadoTransaccion' => 'PAGO EXITOSO PARCIAL'], 77);
$r = retorno('reteica', $RET['id'], $fac);
prueba('un estado que no está en el documento: no es un pago (el detalle, en la sección 15)',
    (int) decl('reteica')['pagado'] === 0 && ultimo('reteica')['pel_Estado'] === 'COMENZADA' && strpos($r['body'], 'Pago aprobado') === false);
restaurar('reteica'); stub_quitar($RET['numero'] . '2');

// Aprobada, pero la declaración ya no está presentada.
crear($sesU, 'reteica', $RET['id']);
$fac = ultimo('reteica')['pel_Referencia'];
ejec("UPDATE ind_reteica SET ret_Estado = 1 WHERE ret_Id = ?", [$RET['id']]);
stub_aprobar($fac, 77);
$r = retorno('reteica', $RET['id'], $fac);
prueba('aprobada sobre una declaración que ya no está presentada: no se marca pagada, REVISAR',
    (int) decl('reteica')['pagado'] === 0 && ultimo('reteica')['pel_Estado'] === 'REVISAR' && strpos($r['body'], 'no se pudo registrar automáticamente') !== false);
restaurar('reteica'); stub_quitar($RET['numero'] . '2');

// Con la respuesta envuelta en {data: {value}}.
stub_modo(['sobre' => true]);
crear($sesU, 'reteica', $RET['id']);
$fac = ultimo('reteica')['pel_Referencia'];
stub_aprobar($fac, 77);
retorno('reteica', $RET['id'], $fac);
prueba('la respuesta envuelta en {data:{value}} se entiende igual: pago registrado', (int) decl('reteica')['pagado'] === 1 && ultimo('reteica')['pel_Estado'] === 'APROBADA');
stub_modo([]);
restaurar('reteica'); stub_quitar($RET['numero'] . '2');

/* ======================================================================
   10. Errores del servicio
   ====================================================================== */
seccion('10. Errores de 1cero1pay');
stub_reiniciar();
stub_modo(['insert500' => true]);
$r = crear($sesU, 'reteica', $RET['id']);
$i = ultimo('reteica');
prueba('InsertTransaction con error 500: 502 sin redirección, intento ERROR, declaración igual',
    $r['code'] === 502 && $r['location'] === '' && $i && $i['pel_Estado'] === 'ERROR' && (int) decl('reteica')['pagado'] === 0, $r);
prueba('  el contribuyente no ve detalles técnicos', strpos($r['body'], 'No se pudo iniciar el pago en línea') !== false && strpos($r['body'], '500') === false
    && strpos($r['body'], SIM_USUARIO) === false);
stub_modo([]);
$r = crear($sesU, 'reteica', $RET['id']);
prueba('  se puede reintentar enseguida (el intento con error no frena), con la factura siguiente',
    $r['code'] === 302 && strpos($r['location'], 'http://127.0.0.1:9101/') === 0 && ultimo('reteica')['pel_Referencia'] === $RET['numero'] . '2002');
restaurar('reteica'); stub_quitar($RET['numero'] . '2');

stub_modo(['insertSinUrl' => true]);
$r = crear($sesU, 'reteica', $RET['id']);
prueba('1cero1pay contesta sin dirección de pago: 502, intento ERROR con su mensaje (en la base, no en pantalla)',
    $r['code'] === 502 && ultimo('reteica')['pel_Estado'] === 'ERROR' && strpos((string) ultimo('reteica')['pel_Mensaje'], 'no tiene habilitado') !== false
    && strpos($r['body'], 'no tiene habilitado') === false, ultimo('reteica'));
restaurar('reteica'); stub_quitar($RET['numero'] . '2');

stub_modo(['urlAjena' => true]);
$r = crear($sesU, 'reteica', $RET['id']);
prueba('dirección de pago de otro dominio: no se manda al contribuyente allí (502, ERROR)',
    $r['code'] === 502 && $r['location'] === '' && ultimo('reteica')['pel_Estado'] === 'ERROR');
restaurar('reteica'); stub_quitar($RET['numero'] . '2');

stub_modo(['login500' => true]);
$r = crear($sesU, 'reteica', $RET['id']);
prueba('el login de 1cero1pay falla: 502, intento ERROR', $r['code'] === 502 && ultimo('reteica')['pel_Estado'] === 'ERROR');
restaurar('reteica'); stub_quitar($RET['numero'] . '2');
stub_modo([]);

param('UNOCEROUNO_CLAVE', 'clave_equivocada');
$r = crear($sesU, 'reteica', $RET['id']);
prueba('credenciales equivocadas: 502, intento ERROR, sin la clave en ninguna parte',
    $r['code'] === 502 && ultimo('reteica')['pel_Estado'] === 'ERROR' && strpos((string) ultimo('reteica')['pel_Mensaje'], 'clave_equivocada') === false
    && strpos($r['body'], 'clave_equivocada') === false);
configurar('1');
restaurar('reteica'); stub_quitar($RET['numero'] . '2');

// La consulta falla: no cambia nada y cuenta como en trámite.
crear($sesU, 'reteica', $RET['id']);
$fac = ultimo('reteica')['pel_Referencia'];
stub_aprobar($fac, 77);
stub_modo(['consulta500' => true]);
$r = retorno('reteica', $RET['id'], $fac);
prueba('la consulta contesta 500: «no se pudo confirmar», nada registrado, el intento sigue como estaba',
    strpos($r['body'], 'No se pudo confirmar el estado del pago') !== false && (int) decl('reteica')['pagado'] === 0 && ultimo('reteica')['pel_Estado'] === 'COMENZADA');
$r = pagar_php($sesU, 'reteica', $RET['id'], '101');
prueba('  mientras no se pueda preguntar, cuenta como pago en trámite', strpos($r['body'], 'id="formPago"') === false && strpos($r['body'], 'se inició un pago') !== false);
$t = tarea();
prueba('  la tarea lo reporta como error y no cierra ni registra nada', strpos($t, "[1cero1pay] $fac: error al consultar") !== false
    && ultimo('reteica')['pel_Estado'] === 'COMENZADA' && (int) decl('reteica')['pagado'] === 0, $t);
envejecer($fac, 60 * 24 * 30);
$t = tarea();
prueba('  ni siquiera un mes después: sin respuesta de 1cero1pay no se cierra', ultimo('reteica')['pel_Estado'] === 'COMENZADA', $t);
stub_modo([]);
$t = tarea();
prueba('  cuando 1cero1pay vuelve a contestar, la tarea registra el pago', (int) decl('reteica')['pagado'] === 1 && ultimo('reteica')['pel_Estado'] === 'APROBADA', $t);
restaurar('reteica'); stub_quitar($RET['numero'] . '2');

// Token vencido entre la creación y la consulta, dentro del mismo proceso.
stub_reiniciar();
UnoCeroUno::token();
$r101 = PagoUnoCeroUno::crearIntento($con, $RET['m'], $RET['id'], (int) round($RET['valor'] * 100), 0, (int) $u['usu_Id']);
$fac = $r101['intento']['pel_Referencia'];
stub(function (&$e) { foreach ($e['tokens'] as $k => $v) { $e['tokens'][$k] = time() - 1; } });
$tx = UnoCeroUno::crearTransaccion(['factura' => $fac, 'referencia' => $RET['numero'], 'centavos' => (int) round($RET['valor'] * 100),
                                    'modulo' => 'reteica', 'pagador' => PagoUnoCeroUno::pagador($con, $RET['m'], $RET['id'])]);
PagoUnoCeroUno::marcarIniciado($con, $fac, $tx['id']);
prueba('token vencido al crear la transacción: se autentica de nuevo y la crea', stub_leer()['logins'] === 2 && strpos($tx['url'], '/pagar?tx=') !== false
    && ultimo('reteica')['pel_Estado'] === 'COMENZADA');
restaurar('reteica'); stub_quitar($RET['numero'] . '2');

/* ======================================================================
   11. La tarea cierra lo que nunca se pagó
   ====================================================================== */
seccion('11. Intentos que nunca se pagaron');
stub_reiniciar();
crear($sesU, 'reteica', $RET['id']);
$fac = ultimo('reteica')['pel_Referencia'];
envejecer($fac, 60 * 20);
$t = tarea();
prueba('COMENZADA de 20 horas: la tarea la deja abierta', ultimo('reteica')['pel_Estado'] === 'COMENZADA', $t);
envejecer($fac, 60 * 27);
$t = tarea();
prueba('COMENZADA de más de 26 horas (1cero1pay confirma que sigue sin empezar): se cierra SIN_PAGO',
    ultimo('reteica')['pel_Estado'] === 'SIN_PAGO' && strpos($t, "[1cero1pay] $fac: sin terminar en 1cero1pay; se cerró.") !== false, $t);
$t = tarea();
prueba('  y la tarea ya no vuelve a preguntar por ella', strpos($t, $fac) === false);
stub_aprobar($fac, 77);
$r = retorno('reteica', $RET['id'], $fac);
prueba('  si aun así se pagó y alguien verifica el enlace, el pago se registra', (int) decl('reteica')['pagado'] === 1 && ultimo('reteica')['pel_Estado'] === 'APROBADA');
restaurar('reteica');

// Límite de intentos por hora.
stub_reiniciar();
$codigos = [];
for ($k = 0; $k < 6; $k++) {
    $r = crear($sesU, 'reteica', $RET['id']);
    $codigos[] = $r['code'];
    $ult = ultimo('reteica');
    if ($ult) { stub_tx($ult['pel_Referencia'], ['EstadoTransaccion' => 'RECHAZADA'], 77); retorno('reteica', $RET['id'], $ult['pel_Referencia']); }
}
prueba('más de 5 intentos de una declaración en una hora: el sexto se rechaza (429)', $codigos === [302, 302, 302, 302, 302, 429] && count(intentos('reteica')) === 5, $codigos);
restaurar('reteica');

// Dos clics a la vez.
stub_reiniciar();
$rs = juntas([['POST', 'extensiones/pse/crearSesion.php', ['modulo' => 'reteica', 'id' => $RET['id'], 'acepto' => '1', 'pasarela' => '101'], $sesU],
              ['POST', 'extensiones/pse/crearSesion.php', ['modulo' => 'reteica', 'id' => $RET['id'], 'acepto' => '1', 'pasarela' => '101'], $sesA],
              ['POST', 'extensiones/pse/crearSesion.php', ['modulo' => 'reteica', 'id' => $RET['id'], 'acepto' => '1', 'pasarela' => '101'], $sesU]]);
$alBanco = count(array_filter($rs, function ($x) { return strpos($x['location'], 'http://127.0.0.1:9101/') === 0; }));
prueba('tres «pagar» a la vez (dos sesiones): un solo intento y una sola transacción en 1cero1pay',
    $alBanco === 1 && count(intentos('reteica')) === 1 && count(stub_pedidos('/api/transaction/InsertTransaction')) === 1,
    [array_column($rs, 'code'), array_column($rs, 'location')]);
restaurar('reteica');

/* ======================================================================
   12. Las dos pasarelas sobre la misma declaración
   ====================================================================== */
seccion('12. Convivencia con la pasarela de siempre (PlacetoPay) en la misma declaración');
stub_reiniciar();
$m = $AUT['m'];
// a) La de siempre tiene una sesión en curso: 1cero1pay no inicia otro pago.
prueba('PlacetoPay anota su sesión como siempre (PENDING)', PlacetoPay::anotarSesion($con, $m, $AUT['id'], 990101) === true
    && (string) decl('autorreteica')['req'] === '990101' && decl('autorreteica')['est'] === 'PENDING');
$r = crear($sesU, 'autorreteica', $AUT['id']);
prueba('con esa sesión en curso, «PSE · Bancolombia» no crea intento ni transacción: al resumen',
    $r['code'] === 302 && strpos($r['location'], 'pagar.php') === 0 && !intentos('autorreteica') && count(stub_pedidos()) === 0, $r);
$r = pagar_php($sesU, 'autorreteica', $AUT['id'], '101');
prueba('  y su resumen muestra el pago iniciado de la otra pasarela', strpos($r['body'], 'Pago iniciado') !== false && strpos($r['body'], 'id="formPago"') === false);
$res = PagoUnoCeroUno::crearIntento($con, $m, $AUT['id'], (int) round($AUT['valor'] * 100), 0, (int) $u['usu_Id']);
prueba('  tampoco directamente con el candado (crearIntento dice «tramite»)', $res['ok'] === false && $res['motivo'] === 'tramite');
// b) El banco la rechaza: se anota como siempre, y entonces sí se puede por 1cero1pay.
PlacetoPay::aplicarADeclaracion($con, $AUT['id'], ['aprobado' => false, 'estado' => 'REJECTED', 'requestId' => 990101, 'mensaje' => 'Rechazada por el banco'], $AUT['valor'], $m);
prueba('PlacetoPay anota el rechazo como siempre', decl('autorreteica')['est'] === 'REJECTED' && (int) decl('autorreteica')['pagado'] === 0);
$r = crear($sesU, 'autorreteica', $AUT['id']);
$fac = ultimo('autorreteica')['pel_Referencia'] ?? '';
prueba('rechazada allá, se puede pagar por «PSE · Bancolombia»', $r['code'] === 302 && strpos($r['location'], 'http://127.0.0.1:9101/') === 0 && $fac !== '');
// c) Con el intento de 1cero1pay en curso, PlacetoPay no anota otra sesión.
$anotada = PagoUnoCeroUno::siLibre($con, $m, $AUT['id'], function () use ($con, $m, $AUT) { return PlacetoPay::anotarSesion($con, $m, $AUT['id'], 990102); });
prueba('con el pago de 1cero1pay en curso, PlacetoPay no anota otra sesión (sigue la 990101 rechazada)',
    $anotada === null && (string) decl('autorreteica')['req'] === '990101' && decl('autorreteica')['est'] === 'REJECTED');
// d) 1cero1pay aprueba: pagada por 1cero1pay; lo de PlacetoPay queda como estaba.
stub_aprobar($fac, 615);
retorno('autorreteica', $AUT['id'], $fac);
$d = decl('autorreteica');
prueba('aprobada por 1cero1pay: pagada con vía 1CERO1PAY; el rastro de PlacetoPay intacto',
    (int) $d['pagado'] === 1 && $d['ruta'] === '1CERO1PAY' && (string) $d['req'] === '990101' && $d['est'] === 'REJECTED' && igual_dinero($d['valor'], $AUT['valor']), $d);
restaurar('autorreteica');

// e) La de siempre registra su pago exactamente igual con 1cero1pay activo.
stub_reiniciar();
$anotada = PagoUnoCeroUno::siLibre($con, $m, $AUT['id'], function () use ($con, $m, $AUT) { return PlacetoPay::anotarSesion($con, $m, $AUT['id'], 990103); });
prueba('sin nada en curso en 1cero1pay, PlacetoPay anota su sesión (por el camino con candado de crearSesion.php)',
    $anotada === true && (string) decl('autorreteica')['req'] === '990103' && decl('autorreteica')['est'] === 'PENDING');
$info = PlacetoPay::interpretarRespuesta(['requestId' => 990103, 'status' => ['status' => 'APPROVED', 'date' => date('c'), 'message' => 'Aprobada'],
            'request' => ['payment' => ['reference' => $AUT['numero'], 'amount' => ['total' => $AUT['valor']]]],
            'payment' => [['issuerName' => 'BANCO DE BOGOTA', 'amount' => ['to' => ['total' => $AUT['valor']]], 'status' => ['message' => 'Aprobada']]]]);
$pagada = PlacetoPay::aplicarADeclaracion($con, $AUT['id'], $info, $AUT['valor'], $m);
$d = decl('autorreteica');
prueba('PlacetoPay aprueba: pagada con vía PSE, su banco y su valor, como siempre',
    $pagada === true && (int) $d['pagado'] === 1 && $d['ruta'] === 'PSE' && $d['banco'] === 'BANCO DE BOGOTA' && igual_dinero($d['valor'], $AUT['valor']) && $d['est'] === 'APPROVED', $d);
prueba('  y ningún intento de 1cero1pay apareció', !intentos('autorreteica') && count(stub_pedidos()) === 0);
restaurar('autorreteica');

// f) Segundo pago: pagada por PlacetoPay y un intento VIEJO de 1cero1pay se aprueba después -> DOBLE.
stub_reiniciar();
crear($sesU, 'autorreteica', $AUT['id']);
$fac = ultimo('autorreteica')['pel_Referencia'];
envejecer($fac, 120);        // dos horas: ya no frena, pero su enlace pudo pagarse
PlacetoPay::anotarSesion($con, $m, $AUT['id'], 990104);
$info['requestId'] = 990104;
PlacetoPay::aplicarADeclaracion($con, $AUT['id'], $info, $AUT['valor'], $m);
$antes = decl('autorreteica');
stub_aprobar($fac, 615);
$r = retorno('autorreteica', $AUT['id'], $fac);
$d = decl('autorreteica');
$i = ultimo('autorreteica');
prueba('un pago de 1cero1pay aprobado sobre una declaración ya pagada por PSE: queda DOBLE, no se registra otra vez',
    $i['pel_Estado'] === 'DOBLE' && $d['ruta'] === 'PSE' && $d['registrado'] === $antes['registrado'] && igual_dinero($d['valor'], $antes['valor'])
    && strpos($r['body'], 'ya estaba pagada') !== false && strpos($r['body'], 'Pago repetido') !== false, [$i['pel_Estado'], $d]);
$r = retorno('autorreteica', $AUT['id'], $fac);
prueba('  repetirlo lo deja DOBLE', ultimo('autorreteica')['pel_Estado'] === 'DOBLE' && decl('autorreteica')['ruta'] === 'PSE');
$t = tarea();
prueba('  y la tarea avisa la devolución con ATENCIÓN', strpos($t, "[1cero1pay] ATENCIÓN $fac (autorreteica {$AUT['id']}, DOBLE)") !== false, $t);
restaurar('autorreteica');

// g) Dos intentos de 1cero1pay aprobados (el viejo y el nuevo): uno paga, el otro DOBLE.
stub_reiniciar();
crear($sesU, 'autorreteica', $AUT['id']);
$facA = ultimo('autorreteica')['pel_Referencia'];
envejecer($facA, 120);
crear($sesU, 'autorreteica', $AUT['id']);
$facB = ultimo('autorreteica')['pel_Referencia'];
stub_aprobar($facB, 615);
retorno('autorreteica', $AUT['id'], $facB);
$antes = decl('autorreteica');
stub_aprobar($facA, 615);
$t = tarea();
$todos = intentos('autorreteica');
prueba('dos intentos de 1cero1pay aprobados: el primero paga y el otro queda DOBLE (una sola vez pagada)',
    $facA !== $facB && $todos[0]['pel_Estado'] === 'DOBLE' && $todos[1]['pel_Estado'] === 'APROBADA'
    && decl('autorreteica')['registrado'] === $antes['registrado'] && (int) decl('autorreteica')['pagado'] === 1, array_column($todos, 'pel_Estado'));
retorno('autorreteica', $AUT['id'], $facB);
prueba('  y el que sí pagó no se ve a sí mismo como doble al reprocesarlo', intentos('autorreteica')[1]['pel_Estado'] === 'APROBADA');
restaurar('autorreteica');

// h) Apagar el botón no abandona un pago ya iniciado.
stub_reiniciar();
crear($sesU, 'autorreteica', $AUT['id']);
$fac = ultimo('autorreteica')['pel_Referencia'];
param('UNOCEROUNO_ACTIVO', '0');
$fi = fila_de(listado_ica($sesU), 'dec_Id', $ICA['id']);
prueba('apagado (UNOCEROUNO_ACTIVO = 0): el botón desaparece', $fi && (int) $fi['pago_en_linea_2'] === 0 && pagar_php($sesU, 'autorreteica', $AUT['id'], '101')['code'] === 503);
prueba('  pero el pago en curso sigue frenando a la otra pasarela', strpos(pagar_php($sesU, 'autorreteica', $AUT['id'])['body'], 'id="formPago"') === false);
stub_aprobar($fac, 615);
$t = tarea();
prueba('  y la tarea igual lo confirma y lo registra', (int) decl('autorreteica')['pagado'] === 1 && ultimo('autorreteica')['pel_Estado'] === 'APROBADA', $t);
configurar('1');
restaurar('autorreteica');

/* ======================================================================
   13. IDImpuesto configurable
   ====================================================================== */
seccion('13. IDImpuesto, fuente y tipo de implementación configurables');
stub_reiniciar();
param('UNOCEROUNO_IDIMPUESTO_RETEICA', '3');
param('UNOCEROUNO_FUENTE_PAGO', '4');
param('UNOCEROUNO_TIPO_IMPLEMENTACION', '1');
crear($sesU, 'reteica', $RET['id']);
$fac = ultimo('reteica')['pel_Referencia'];
$b = stub_pedidos('/api/transaction/InsertTransaction')[0]['cuerpo'] ?? [];
prueba('con otros valores en los parámetros: IDImpuesto 3, FuentePago 4, TipoImplementacion 1',
    ($b['IDImpuesto'] ?? null) === 3 && ($b['FuentePago'] ?? null) === 4 && ($b['TipoImplementacion'] ?? null) === 1 && (int) ultimo('reteica')['pel_IdImpuesto'] === 3, $b);
// Se cambia el parámetro con el intento abierto: se consulta con el IDImpuesto con que se creó.
configurar('1');
stub_aprobar($fac, 3);
stub_olvidar_pedidos();
retorno('reteica', $RET['id'], $fac);
$q = stub_pedidos('/api/transaction');
prueba('si después se cambia el parámetro, el intento se consulta con el IDImpuesto con que se creó (3) y se registra',
    ($q[0]['query']['IDImpuesto'] ?? null) === '3' && (int) decl('reteica')['pagado'] === 1, $q[0]['query'] ?? null);
restaurar('reteica');
param('UNOCEROUNO_IDIMPUESTO_ICA', 'dos');
prueba('un IDImpuesto que no es un número no se usa: vale el de por defecto (2)', UnoCeroUno::idImpuesto('ica') === 2);
configurar('1');

/* ======================================================================
   14. La migración es re-ejecutable y la pantalla no muestra credenciales
   ====================================================================== */
seccion('14. Migración 060 re-ejecutable; credenciales que no se muestran');
param('UNOCEROUNO_IDIMPUESTO_ICA', '9');
$antes = todas("SELECT par_Clave, par_Valor, par_Estado FROM conf_parametros WHERE par_Clave LIKE 'UNOCEROUNO[_]%' ORDER BY par_Clave");
$nMig = (int) valor("SELECT COUNT(*) FROM conf_migraciones WHERE mig_Nombre = '060_pago_en_linea_con_1cero1pay'");
$sql = (string) file_get_contents($raiz . '/BD/migraciones/060_pago_en_linea_con_1cero1pay.sql');
$lotes = preg_split('/^\s*GO\s*$/mi', $sql);
$errores = [];
foreach ($lotes as $lote) {
    if (trim($lote) === '') { continue; }
    try { ejec($lote); } catch (\Throwable $e) { $errores[] = mb_substr($e->getMessage(), 0, 200); }
}
$despues = todas("SELECT par_Clave, par_Valor, par_Estado FROM conf_parametros WHERE par_Clave LIKE 'UNOCEROUNO[_]%' ORDER BY par_Clave");
prueba('volver a ejecutar la 060: sin errores', !$errores, $errores);
prueba('  no duplica ni pisa nada (13 parámetros, los valores ya escritos se respetan, un registro en conf_migraciones)',
    $antes == $despues && count($despues) === 13 && (int) valor("SELECT COUNT(*) FROM conf_migraciones WHERE mig_Nombre = '060_pago_en_linea_con_1cero1pay'") === $nMig && $nMig === 1);
configurar('1');
$sens = todas("SELECT par_Clave FROM conf_parametros WHERE par_Clave LIKE 'UNOCEROUNO[_]%' AND par_Sensible = 1 ORDER BY par_Clave");
prueba('usuario, clave y el secreto de los enlaces están marcados como sensibles',
    array_column($sens, 'par_Clave') === ['UNOCEROUNO_CLAVE', 'UNOCEROUNO_SECRETO_ENLACE', 'UNOCEROUNO_USUARIO']);
$sesCfg = sesion($admin['usu_Id']);
$r = pedir('POST', 'business/controller/class.configuracion.php', ['funcion' => 1], $sesCfg, true);
$j = json_decode($r['body'], true);
$lista = [];
foreach (($j['datos'] ?? []) as $p) { if (is_array($p) && isset($p['par_Clave'])) { $lista[$p['par_Clave']] = $p; } }
if (isset($lista['UNOCEROUNO_CLAVE'])) {
    prueba('la pantalla de parámetros no recibe ni el usuario ni la clave (solo que están puestos)',
        $lista['UNOCEROUNO_CLAVE']['par_Valor'] === '' && (int) $lista['UNOCEROUNO_CLAVE']['par_Puesto'] === 1
        && $lista['UNOCEROUNO_USUARIO']['par_Valor'] === '' && strpos($r['body'], SIM_CLAVE) === false && strpos($r['body'], SIM_USUARIO) === false);
    prueba('  y el banco de 1cero1pay se elige de la lista de bancos', !empty($lista['UNOCEROUNO_BANCO']['par_Opciones']));
} else {
    prueba('la pantalla de parámetros lista los de 1cero1pay (función 1 de configuración)', false, mb_substr($r['body'], 0, 300));
}

// La herramienta de verificación (solo lectura): dice el estado sin mostrar credenciales.
stub_olvidar_pedidos();
$salida = [];
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($raiz . '/BD/verificar_municipio.php') . ' 2>&1', $salida);
$salida = implode("\n", $salida);
$lineas101 = implode("\n", array_filter(explode("\n", preg_replace('/\n\s{6,}/', ' ', $salida)), function ($l) { return strpos($l, '1cero1pay') !== false; }));
prueba('verificar_municipio.php: 1cero1pay activo, con sus IDImpuesto, y 1cero1pay acepta las credenciales',
    strpos($lineas101, 'ACTIVO y abierto a todos') !== false && strpos($lineas101, 'IDImpuesto ICA 2 / retencion 77 / autorretencion 615') !== false
    && strpos($lineas101, 'acepta el usuario y la clave') !== false && strpos($lineas101, 'usuario puesto, clave puesta') !== false, $lineas101);
prueba('  avisa que UNOCEROUNO_API_BASE no es el servicio real y que falta elegir el banco',
    strpos($lineas101, 'UNOCEROUNO_API_BASE') !== false && strpos($lineas101, 'UNOCEROUNO_BANCO sin elegir') !== false, $lineas101);
prueba('  no imprime ni el usuario ni la clave, y solo pidió el token (no creó ni consultó transacciones)',
    strpos($salida, SIM_USUARIO) === false && strpos($salida, SIM_CLAVE) === false
    && count(stub_pedidos('/api/Login/authenticate')) === 1 && count(stub_pedidos()) === 1, count(stub_pedidos()));
param('UNOCEROUNO_CLAVE', 'clave_que_no_es');
$salida = [];
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($raiz . '/BD/verificar_municipio.php') . ' 2>&1', $salida);
$salida = preg_replace('/\n\s{6,}/', ' ', implode("\n", $salida));
prueba('  con la clave equivocada: AVISO «no se pudo autenticar», sin mostrarla',
    strpos($salida, 'no se pudo autenticar con 1cero1pay') !== false && strpos($salida, 'clave_que_no_es') === false);
configurar('1');

$enLog = false;
foreach (stub_pedidos() as $p) { if (strpos(json_encode($p['query']), SIM_CLAVE) !== false) { $enLog = true; } }
prueba('la clave nunca viaja en una URL', !$enLog);

/* ======================================================================
   15. Revisión 2026-11-22, punto 1: nada frena para siempre
   ====================================================================== */
seccion('15. Estados desconocidos y PENDIENTE viejo: nada frena para siempre');
$manual = function ($bajoCandado = false) use ($rm, $con, $RET) {
    return $rm->invoke(null, $con, $RET['m'], $RET['id'], ['pse_req' => null, 'pse_est' => null], $bajoCandado);
};
configurar('1');
restaurar('reteica');
stub_reiniciar();
crear($sesU, 'reteica', $RET['id']);
$fac = ultimo('reteica')['pel_Referencia'];
stub_tx($fac, ['EstadoTransaccion' => 'NO AUTORIZADA'], 77);
$r = retorno('reteica', $RET['id'], $fac);
$i = ultimo('reteica');
prueba('estado «NO AUTORIZADA»: no es un pago ni pasa a PENDIENTE; el intento sigue COMENZADA y guarda el texto',
    (int) decl('reteica')['pagado'] === 0 && $i['pel_Estado'] === 'COMENZADA' && strpos((string) $i['pel_Mensaje'], '«NO AUTORIZADA»') !== false, $i);
prueba('  el contribuyente ve «no se pudo confirmar», no «aprobado» ni «en proceso»',
    strpos($r['body'], 'No se pudo confirmar el estado del pago') !== false && strpos($r['body'], 'Pago aprobado') === false
    && strpos($r['body'], 'quedó en proceso') === false, texto($r['body']));
$t = tarea();
prueba('  la tarea lo avisa con ATENCIÓN y todavía no lo cierra',
    strpos($t, "[1cero1pay] ATENCIÓN $fac: estado no reconocido «NO AUTORIZADA»; no se aplicó") !== false && ultimo('reteica')['pel_Estado'] === 'COMENZADA', $t);
prueba('  recién iniciado frena como cualquier otro (45 minutos)', strpos(pagar_php($sesU, 'reteica', $RET['id'], '101')['body'], 'id="formPago"') === false && is_string($manual()));
envejecer($fac, 50);
prueba('  pasados 45 minutos ya NO frena: las dos pasarelas vuelven a ofrecer el pago',
    strpos(pagar_php($sesU, 'reteica', $RET['id'], '101')['body'], 'id="formPago"') !== false
    && strpos(pagar_php($sesU, 'reteica', $RET['id'])['body'], 'id="formPago"') !== false);
prueba('  y el pago manual de la Alcaldía tampoco espera (con y sin su candado)', $manual() === null && $manual(true) === null);
envejecer($fac, 60 * 27);
$t = tarea();
$i = ultimo('reteica');
prueba('  pasadas 26 horas la tarea lo cierra SIN_PAGO, avisando y dejando escrito el estado que llegó',
    $i['pel_Estado'] === 'SIN_PAGO' && strpos((string) $i['pel_Mensaje'], '«NO AUTORIZADA»') !== false
    && strpos($t, "[1cero1pay] ATENCIÓN $fac: estado no reconocido «NO AUTORIZADA»; se cerró sin pago") !== false, [$i['pel_Estado'], $t]);
$t = tarea();
prueba('  y deja de consultarlo en cada corrida', strpos($t, $fac) === false, $t);
stub_aprobar($fac, 77);
retorno('reteica', $RET['id'], $fac);
prueba('  si al final sí era un pago (APROBADA al verificar el enlace), se registra', (int) decl('reteica')['pagado'] === 1 && ultimo('reteica')['pel_Estado'] === 'APROBADA');
restaurar('reteica'); stub_quitar($RET['numero'] . '2');

// Un PENDIENTE de verdad.
crear($sesU, 'reteica', $RET['id']);
$fac = ultimo('reteica')['pel_Referencia'];
stub_tx($fac, ['EstadoTransaccion' => 'PENDIENTE'], 77);
retorno('reteica', $RET['id'], $fac);
stub_tx($fac, ['EstadoTransaccion' => 'EN VALIDACION'], 77);
retorno('reteica', $RET['id'], $fac);
prueba('un PENDIENTE al que luego llega un estado desconocido sigue PENDIENTE (ni se cierra ni se registra)',
    ultimo('reteica')['pel_Estado'] === 'PENDIENTE' && (int) decl('reteica')['pagado'] === 0);
stub_tx($fac, ['EstadoTransaccion' => 'PENDIENTE'], 77);
envejecer($fac, 60 * 47);
prueba('PENDIENTE de 47 horas: el pago manual todavía espera (con y sin su candado)', is_string($manual()) && is_string($manual(true)), [$manual(), $manual(true)]);
envejecer($fac, 60 * 49);
prueba('PENDIENTE de más de ' . PagoUnoCeroUno::HORAS_PENDIENTE_FRENA_MANUAL . ' horas: el pago manual ya NO se frena (con y sin su candado)',
    $manual() === null && $manual(true) === null, [$manual(), $manual(true)]);
$n = count(intentos('reteica'));
prueba('  pero sigue frenando otro pago EN LÍNEA, por las dos pasarelas',
    strpos(pagar_php($sesU, 'reteica', $RET['id'], '101')['body'], 'id="formPago"') === false
    && strpos(pagar_php($sesU, 'reteica', $RET['id'])['body'], 'id="formPago"') === false
    && crear($sesU, 'reteica', $RET['id'])['code'] === 302 && crear($sesU, 'reteica', $RET['id'], [], null)['code'] === 302
    && count(intentos('reteica')) === $n && decl('reteica')['req'] === null && ultimo('reteica')['pel_Estado'] === 'PENDIENTE');
$t = tarea();
prueba('  y la tarea lo sigue avisando', strpos($t, "[1cero1pay] ATENCIÓN $fac (reteica {$RET['id']}, PENDIENTE)") !== false && ultimo('reteica')['pel_Estado'] === 'PENDIENTE', $t);
$marcada = PagoDeclaracion::registrar($con, $RET['id'], ['valor' => $RET['valor'], 'banco' => 'Transferencia - BANCOLOMBIA', 'via' => PagoDeclaracion::VIA_MANUAL], $RET['m']);
stub_aprobar($fac, 77);
$t = tarea();
$d = decl('reteica');
prueba('  si la Alcaldía registró el pago a mano y el pendiente se aprueba después: queda DOBLE (para devolver), un solo pago registrado',
    $marcada === true && ultimo('reteica')['pel_Estado'] === 'DOBLE' && $d['ruta'] === 'MANUAL' && igual_dinero($d['valor'], $RET['valor']), [$d, $t]);
restaurar('reteica'); stub_quitar($RET['numero'] . '2');

/* ======================================================================
   16. Revisión, punto 2: la tarea cierra lo que 1cero1pay contesta sin la factura
   ====================================================================== */
seccion('16. Respuestas sin la factura: la tarea no las consulta para siempre');
crear($sesU, 'reteica', $RET['id']);
$fac = ultimo('reteica')['pel_Referencia'];
stub_modo(['noExiste' => true]);      // 200 {"Mensaje": "No existe"}
envejecer($fac, 60 * 20);
$t = tarea();
prueba('200 con {"Mensaje":"No existe"}, intento de 20 horas: sigue abierto', ultimo('reteica')['pel_Estado'] === 'COMENZADA'
    && strpos($t, "[1cero1pay] $fac: la respuesta no es de esta factura; no se aplicó.") !== false, $t);
envejecer($fac, 60 * 27);
$t = tarea();
prueba('  pasadas 26 horas: se cierra SIN_PAGO', ultimo('reteica')['pel_Estado'] === 'SIN_PAGO'
    && strpos($t, "[1cero1pay] $fac: la respuesta de 1cero1pay no trae esta factura; se cerró.") !== false && (int) decl('reteica')['pagado'] === 0, $t);
$t = tarea();
prueba('  y la tarea ya no gasta una consulta en él', strpos($t, $fac) === false && preg_match('/Revisadas: 0,/', $t) === 1, $t);
stub_modo([]);
stub_aprobar($fac, 77);
retorno('reteica', $RET['id'], $fac);
prueba('  cerrado y todo, si el pago aparece al verificar el enlace se registra', (int) decl('reteica')['pagado'] === 1 && ultimo('reteica')['pel_Estado'] === 'APROBADA');
restaurar('reteica'); stub_quitar($RET['numero'] . '2');

crear($sesU, 'reteica', $RET['id']);
$fac = ultimo('reteica')['pel_Referencia'];
stub_quitar($RET['numero'] . '2');    // 1cero1pay contesta vacío (no tiene la transacción)
envejecer($fac, 60 * 20);
$t = tarea();
prueba('respuesta vacía, intento COMENZADA de 20 horas: sigue abierto', ultimo('reteica')['pel_Estado'] === 'COMENZADA', $t);
envejecer($fac, 60 * 27);
$t = tarea();
prueba('  pasadas 26 horas: se cierra SIN_PAGO', ultimo('reteica')['pel_Estado'] === 'SIN_PAGO'
    && strpos($t, "[1cero1pay] $fac: sin transacción en 1cero1pay; se cerró.") !== false, $t);
restaurar('reteica');

crear($sesU, 'reteica', $RET['id']);
$fac = ultimo('reteica')['pel_Referencia'];
stub_aprobar($fac, 77, ['Factura' => null]);
envejecer($fac, 60 * 27);
$t = tarea();
prueba('sin factura pero APROBADA (aunque sea vieja): no se cierra como sin pago, queda REVISAR',
    ultimo('reteica')['pel_Estado'] === 'REVISAR' && (int) decl('reteica')['pagado'] === 0, $t);
restaurar('reteica'); stub_quitar($RET['numero'] . '2');

/* ======================================================================
   17. Revisión, punto 3: el enlace se firma con un secreto propio, no con la clave
   ====================================================================== */
seccion('17. El enlace de retorno se firma con un secreto propio');
$leerSecreto = function () { return (string) valor("SELECT par_Valor FROM conf_parametros WHERE par_Clave = 'UNOCEROUNO_SECRETO_ENLACE'"); };
$sec = $leerSecreto();
prueba('existe UNOCEROUNO_SECRETO_ENLACE: 64 cifras hexadecimales, sensible, y no es la clave',
    preg_match('/^[0-9A-Fa-f]{64}$/', $sec) === 1 && $sec !== SIM_CLAVE
    && (int) valor("SELECT CAST(par_Sensible AS INT) FROM conf_parametros WHERE par_Clave = 'UNOCEROUNO_SECRETO_ENLACE'") === 1);
stub_reiniciar();
crear($sesU, 'reteica', $RET['id']);
$fac = ultimo('reteica')['pel_Referencia'];
$dato = '1cero1pay|reteica|' . $RET['id'] . '|' . $fac;
$firma = PagoUnoCeroUno::firmaRetorno('reteica', $RET['id'], $fac);
prueba('la firma es el HMAC con ese secreto, no con la clave de 1cero1pay',
    $firma === hash_hmac('sha256', $dato, $sec) && $firma !== hash_hmac('sha256', $dato, SIM_CLAVE));
$r = pagar_php($sesU, 'reteica', $RET['id'], '101');
prueba('el enlace «Verificar estado del pago» del resumen lleva esa firma', strpos($r['body'], 't=' . $firma) !== false
    && strpos($r['body'], hash_hmac('sha256', $dato, SIM_CLAVE)) === false);
stub_olvidar_pedidos();
$r = pedir('GET', 'extensiones/pse/retorno.php', ['pasarela' => '101', 'modulo' => 'reteica', 'decl' => $RET['id'], 'ref' => $fac,
                                                    't' => hash_hmac('sha256', $dato, SIM_CLAVE)], null);
prueba('un enlace firmado con la clave (como antes) ya no vale', strpos($r['body'], 'No se pudo verificar este enlace') !== false && count(stub_pedidos()) === 0);
param('UNOCEROUNO_CLAVE', 'otra_clave_distinta');
$r = retorno('reteica', $RET['id'], $fac);
prueba('cambiar la clave de 1cero1pay no invalida los enlaces ya entregados (el enlace se acepta; lo que falla es la consulta, por la clave)',
    strpos($r['body'], 'No se pudo verificar este enlace') === false && strpos($r['body'], 'No se pudo confirmar el estado del pago') !== false, texto($r['body']));
configurar('1');
$r = pedir('POST', 'business/controller/class.configuracion.php', ['funcion' => 1], sesion($admin['usu_Id']), true);
$j = json_decode($r['body'], true);
$claves = [];
foreach (($j['datos'] ?? []) as $x) { if (is_array($x) && isset($x['par_Clave'])) { $claves[] = $x['par_Clave']; } }
prueba('la pantalla de configuración no lista el secreto (ni su valor viaja); los demás UNOCEROUNO_* sí',
    in_array('UNOCEROUNO_ACTIVO', $claves, true) && in_array('UNOCEROUNO_CLAVE', $claves, true)
    && !in_array('UNOCEROUNO_SECRETO_ENLACE', $claves, true) && stripos($r['body'], $sec) === false, $claves);

// Se genera solo si falta, también con varias peticiones a la vez.
ejec("UPDATE conf_parametros SET par_Valor = NULL WHERE par_Clave = 'UNOCEROUNO_SECRETO_ENLACE'");
$g = ['GET', 'extensiones/pse/pagar.php', ['modulo' => 'reteica', 'id' => $RET['id'], 'pasarela' => '101'], $sesU];
$rs = juntas([$g, $g, $g, $g, $g]);
$nuevo = $leerSecreto();
$firmas = [];
foreach ($rs as $x) { $firmas[] = preg_match('/t=([0-9a-f]{64})/', $x['body'], $mm) ? $mm[1] : ''; }
prueba('vaciado, se genera solo: cinco peticiones a la vez terminan con UN mismo secreto nuevo, y todas firman con él',
    preg_match('/^[0-9A-Fa-f]{64}$/', $nuevo) === 1 && $nuevo !== $sec && count(array_unique($firmas)) === 1
    && $firmas[0] === hash_hmac('sha256', $dato, $nuevo), [$nuevo !== $sec, array_unique($firmas)]);
UnoCeroUno::olvidarSecretoEnlace();
$r1 = pedir('GET', 'extensiones/pse/retorno.php', ['pasarela' => '101', 'modulo' => 'reteica', 'decl' => $RET['id'], 'ref' => $fac, 't' => $firma], null);
$r2 = retorno('reteica', $RET['id'], $fac);
prueba('  con el secreto nuevo, el enlace viejo deja de valer y el nuevo sí',
    strpos($r1['body'], 'No se pudo verificar este enlace') !== false && strpos($r2['body'], 'No se pudo verificar este enlace') === false);
ejec("DELETE FROM conf_parametros WHERE par_Clave = 'UNOCEROUNO_SECRETO_ENLACE'");
UnoCeroUno::olvidarSecretoEnlace();
$creado = UnoCeroUno::secretoEnlace();
prueba('sin la fila (una base con la 060 de antes): se crea, sensible, una sola',
    preg_match('/^[0-9a-f]{64}$/', (string) $creado) === 1 && $leerSecreto() === $creado
    && (int) valor("SELECT COUNT(*) FROM conf_parametros WHERE par_Clave = 'UNOCEROUNO_SECRETO_ENLACE' AND par_Sensible = 1") === 1);
// Se deja el de antes de la prueba.
ejec("UPDATE conf_parametros SET par_Valor = ? WHERE par_Clave = 'UNOCEROUNO_SECRETO_ENLACE'", [$sec]);
UnoCeroUno::olvidarSecretoEnlace();
restaurar('reteica'); stub_quitar($RET['numero'] . '2');

/* ======================================================================
   18. Revisión, punto 4: REVISAR resuelto con pago manual no es DOBLE
   ====================================================================== */
seccion('18. Un REVISAR que la Alcaldía resolvió con pago manual no se vuelve DOBLE');
crear($sesU, 'reteica', $RET['id']);
$fac = ultimo('reteica')['pel_Referencia'];
stub_aprobar($fac, 77, ['Total' => $RET['valor'] - 1000]);
retorno('reteica', $RET['id'], $fac);
prueba('aprobado por otro valor: REVISAR (punto de partida)', ultimo('reteica')['pel_Estado'] === 'REVISAR' && (int) decl('reteica')['pagado'] === 0);
$marcada = PagoDeclaracion::registrar($con, $RET['id'], ['valor' => $RET['valor'], 'banco' => 'Transferencia - BANCOLOMBIA', 'via' => PagoDeclaracion::VIA_MANUAL], $RET['m']);
$antes = decl('reteica');
stub_aprobar($fac, 77, ['Total' => $RET['valor']]);     // ahora 1cero1pay lo da por el valor exacto
$r = retorno('reteica', $RET['id'], $fac);
$i = ultimo('reteica');
$d = decl('reteica');
prueba('la Alcaldía lo resuelve con pago manual y se vuelve a verificar: sigue REVISAR con una nota clara, NO DOBLE',
    $marcada === true && $i['pel_Estado'] === 'REVISAR' && strpos((string) $i['pel_Mensaje'], 'otra vía (MANUAL)') !== false
    && strpos((string) $i['pel_Mensaje'], 'no hay nada que devolver') !== false, $i);
prueba('  el pago manual no se toca', $d['ruta'] === 'MANUAL' && $d['registrado'] === $antes['registrado'] && igual_dinero($d['valor'], $RET['valor']));
prueba('  al contribuyente se le dice que está en revisión y ya figura pagada (no «pago repetido»)',
    strpos($r['body'], 'ya figura como pagada') !== false && strpos($r['body'], 'Pago repetido') === false && strpos($r['body'], 'En revisión') !== false, texto($r['body']));
$t = tarea();
prueba('  la tarea no anuncia ninguna devolución (no hay DOBLE)', strpos($t, 'DOBLE') === false, $t);
retorno('reteica', $RET['id'], $fac);
prueba('  repetirlo no lo cambia', ultimo('reteica')['pel_Estado'] === 'REVISAR');
restaurar('reteica'); stub_quitar($RET['numero'] . '2');

// Con OTRO intento de 1cero1pay aprobado y registrado sí es un segundo pago.
crear($sesU, 'reteica', $RET['id']);
$facA = ultimo('reteica')['pel_Referencia'];
stub_aprobar($facA, 77, ['Total' => $RET['valor'] - 1000]);
retorno('reteica', $RET['id'], $facA);
// (Se anota a mano: con uno en revisión la pantalla no deja iniciar otro.)
ejec("INSERT INTO ind_pagos_en_linea (pel_Pasarela, pel_Modulo, pel_IdDeclaracion, pel_Referencia, pel_Centavos, pel_Estado, pel_Ambiente, pel_IdImpuesto)
      VALUES ('1CERO1PAY', 'reteica', ?, ?, CAST(? AS BIGINT), 'APROBADA', 'prod', 77)",
     [$RET['id'], $RET['numero'] . '2777', (string) (int) round($RET['valor'] * 100)]);
PagoDeclaracion::registrar($con, $RET['id'], ['valor' => $RET['valor'], 'banco' => '1cero1pay - PSE', 'via' => PagoDeclaracion::VIA_UNOCEROUNO], $RET['m']);
stub_aprobar($facA, 77, ['Total' => $RET['valor']]);
retorno('reteica', $RET['id'], $facA);
prueba('con otro intento de 1cero1pay aprobado y registrado, el que estaba en revisión SÍ queda DOBLE',
    intentos('reteica')[0]['pel_Estado'] === 'DOBLE' && decl('reteica')['ruta'] === '1CERO1PAY', array_column(intentos('reteica'), 'pel_Estado'));
restaurar('reteica'); stub_quitar($RET['numero'] . '2');

/* ======================================================================
   19. Revisión, punto 5: el botón de siempre no termina en un error 500
   ====================================================================== */
seccion('19. Si no se puede comprobar el candado, el botón de siempre no se rompe');
$r = pedir('GET', 'extensiones/pse/pagar.php', ['modulo' => 'reteica', 'id' => $RET['id'], 'aviso' => 'reintente'], $sesU);
prueba('el resumen con aviso=reintente muestra el aviso y sigue ofreciendo el pago',
    $r['code'] === 200 && strpos($r['body'], 'No se pudo iniciar el pago en este momento') !== false && strpos($r['body'], 'id="formPago"') !== false);
$r = pedir('GET', 'extensiones/pse/pagar.php', ['modulo' => 'reteica', 'id' => $RET['id'], 'aviso' => '<script>alert(1)</script>'], $sesU);
prueba('  cualquier otro valor de aviso no pinta nada (ni se refleja en la página)',
    strpos($r['body'], 'No se pudo iniciar el pago en este momento') === false && strpos($r['body'], 'alert(1)') === false && $r['body'] === $resumenAntes['body']);

// Otro proceso retiene el candado de la declaración más de lo que siLibre espera (20 s).
$pr = proc_open([PHP_BINARY, __FILE__, '--candado', 'autorreteica', (string) $AUT['id'], '27'],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']], $pp, $raiz);
$listo = trim((string) fgets($pp[1]));
$ini = microtime(true);
$llamada = false;
$lanzo = false;
try {
    $libre = PagoUnoCeroUno::intentarSiLibre($con, $AUT['m'], $AUT['id'], function () use (&$llamada, $con, $AUT) {
        $llamada = true;
        return PlacetoPay::anotarSesion($con, $AUT['m'], $AUT['id'], 990201);
    });
} catch (\Throwable $e) { $lanzo = true; $libre = ['ok' => null]; }
$seg = microtime(true) - $ini;
prueba('con el candado ocupado por otro proceso: intentarSiLibre no lanza; dice que no pudo comprobar (ok = false) tras la espera',
    $listo === 'listo' && !$lanzo && $libre['ok'] === false && $seg >= 15 && $seg < 60, ['listo' => $listo, 'lanzo' => $lanzo, 'libre' => $libre, 'seg' => round($seg, 1)]);
prueba('  y la sesión de PlacetoPay NO se anotó a ciegas', $llamada === false && decl('autorreteica')['req'] === null);
fclose($pp[0]); fclose($pp[1]);
proc_close($pr);                      // el otro proceso suelta el candado
$libre = PagoUnoCeroUno::intentarSiLibre($con, $AUT['m'], $AUT['id'], function () use ($con, $AUT) {
    return PlacetoPay::anotarSesion($con, $AUT['m'], $AUT['id'], 990202);
});
prueba('  suelto el candado, el mismo camino anota la sesión como siempre', $libre['ok'] === true && $libre['valor'] === true
    && (string) decl('autorreteica')['req'] === '990202' && decl('autorreteica')['est'] === 'PENDING', $libre);
restaurar('autorreteica');
$fuente = (string) file_get_contents($raiz . '/extensiones/pse/crearSesion.php');
prueba('  crearSesion.php usa ese camino y, si no pudo comprobar, vuelve al resumen con aviso=reintente (lectura del código: esa línea no se puede ejecutar sin salir al banco)',
    strpos($fuente, 'PagoUnoCeroUno::intentarSiLibre($con, $m, $id, $anotar)') !== false
    && strpos($fuente, "header('Location: ' . \$resumen . '&aviso=reintente');") !== false
    && strpos($fuente, 'PagoUnoCeroUno::siLibre($con, $m, $id, $anotar)') === false);

/* ======================================================================
   20. Todo queda como estaba
   ====================================================================== */
seccion('20. La prueba deja todo como estaba');
limpiar_todo();
Parametros::olvidar();
$igual = true;
foreach ($D as $clave => $d) {
    $ahora = uno('SELECT ' . implode(', ', columnas($d['m'])) . " FROM {$d['m']['tabla']} WHERE {$d['m']['pk']} = ?", [$d['id']]);
    if ($ahora != $d['foto']) { $igual = false; echo "    distinta: $clave " . json_encode([$ahora, $d['foto']]) . "\n"; }
}
prueba('las tres declaraciones quedaron exactamente como estaban', $igual);
prueba('no queda ningún intento de la prueba', (int) valor('SELECT COUNT(*) FROM ind_pagos_en_linea') === $pelAntes
    && (int) valor('SELECT COUNT(*) FROM ind_pagos_en_linea WHERE pel_Id > ?', [$pelMax]) === 0);
prueba('los parámetros UNOCEROUNO_* volvieron a su valor',
    todas("SELECT par_Clave, par_Valor, par_Estado FROM conf_parametros WHERE par_Clave LIKE 'UNOCEROUNO[_]%' ORDER BY par_Clave")
    == (function ($a) { usort($a, function ($x, $y) { return strcmp($x['par_Clave'], $y['par_Clave']); }); return $a; })($paramsOriginales));
prueba('el config del contenedor quedó como estaba', file_get_contents($CONFIG) === $configOriginal && !is_file($RESPALDO));

echo "\n" . str_repeat('=', 60) . "\n";
echo "Pruebas correctas: $ok\n";
if ($fallas) {
    echo 'FALLAS: ' . count($fallas) . "\n";
    foreach ($fallas as $f) { echo "  - $f\n"; }
    exit(1);
}
echo "Todas pasaron.\n";
