<?php
/**
 * Prueba de la casilla 16 de AUTORRETENCION (avisos y tableros), migracion 057.
 *
 * Uso, DENTRO del contenedor web (necesita el driver de SQL Server):
 *
 *     docker exec -w /var/www/html/erpsoftsas erpsoftsas_web_completo php pruebas/probar_autorretencion_avisos.php
 *
 * Usa el usuario de prueba `pruebaica` y su contribuyente (CLAUDE.md). Crea UN borrador
 * de autorretencion para un bimestre que no tenga declaraciones, lo borra al terminar y deja
 * ind_SinAvisosTableros del contribuyente como estaba. Cada peticion corre en un proceso PHP
 * aparte, con la sesion y el POST que se le indique, como en la web. Se NIEGA a correr si la
 * base se llama como las de los municipios.
 *
 * Que comprueba:
 *   1. Sin exencion y sin escribirla, la casilla 16 es el 15% de la 15, a miles.
 *   2. Con ind_SinAvisosTableros = 1 en el RIT sale en cero, y el total baja.
 *   3. Escrita a mano se respeta (aunque el RIT este exento) y el total la suma.
 *   4. Un valor negativo se rechaza y no cambia nada.
 *   5. Un cliente viejo, que no manda la marca, no la cambia.
 *   6. Vaciarla (marca en 0) vuelve al calculo.
 *   7. Cambiar una actividad con la casilla a mano no la pisa.
 *   8. Retencion (RETEICA) no se toca: no tiene casilla de avisos.
 */

if (PHP_SAPI !== 'cli') { exit('Solo por línea de comandos.'); }

$raiz = dirname(__DIR__);
$_SERVER['DOCUMENT_ROOT'] = dirname($raiz);

if (($argv[1] ?? '') === '--peticion') {
    if (!function_exists('fsockopen'))            { function fsockopen()            { return false; } }
    if (!function_exists('pfsockopen'))           { function pfsockopen()           { return false; } }
    if (!function_exists('stream_socket_client')) { function stream_socket_client() { return false; } }

    $cfg = json_decode(stream_get_contents(STDIN), true);
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_SERVER['SCRIPT_FILENAME'] = (string) $cfg['destino'];
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    session_save_path($cfg['sesiones']);
    @session_start();
    $_SESSION = $cfg['sesion'];
    $_POST = $cfg['post'];
    $_REQUEST = $_POST;
    include_once $raiz . '/business/globals.php';
    ob_start();
    register_shutdown_function(function () { echo ob_get_clean(); });
    include $cfg['destino'];
    exit(0);
}

error_reporting(E_ALL);
include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.conexionSqlServer.php';
ini_set('display_errors', '1');

$ok = 0;
$fallas = [];
function prueba($nombre, $condicion, $detalle = '')
{
    global $ok, $fallas;
    if ($condicion) { $ok++; echo "  ok   $nombre\n"; return true; }
    $fallas[] = $nombre;
    $d = is_string($detalle) ? $detalle : json_encode($detalle, JSON_UNESCAPED_UNICODE);
    echo "  FALLA $nombre" . ($d !== '' ? '  -> ' . mb_substr($d, 0, 500) : '') . "\n";
    return false;
}
function seccion($t) { echo "\n== $t\n"; }

$con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
function uno($sql, array $p = []) { global $con; return $con->obnerFila($con->consultar($sql, $p)); }
function ejec($sql, array $p = []) { global $con; $con->consultar($sql, $p); }

$base = uno('SELECT DB_NAME() AS b')['b'];
if (preg_match('/^erpsofts_ind_comercio_/i', (string) $base)) {
    exit("Se niega a correr: la base '$base' se llama como las de los municipios. Use una copia local.\n");
}
echo "Base: $base\n";

if (!uno("SELECT 1 AS x FROM conf_migraciones WHERE mig_Nombre = '057_autorretencion_avisos_exento_y_editable'")) {
    exit("Falta la migración 057: php BD/aplicar_migraciones.php --aplicar 057\n");
}

$dirSesiones = sys_get_temp_dir() . '/sesiones_prueba_avisos';
@mkdir($dirSesiones, 0777, true);

function pedir_a($destino, array $ses, array $post)
{
    global $dirSesiones, $raiz;
    $cfg = ['destino' => $raiz . '/business/controller/' . $destino, 'sesion' => $ses,
            'post' => $post, 'sesiones' => $dirSesiones];
    $cmd = [PHP_BINARY, '-d', 'disable_functions=stream_socket_client,fsockopen,pfsockopen', __FILE__, '--peticion'];
    $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $raiz);
    fwrite($pipes[0], json_encode($cfg));
    fclose($pipes[0]);
    $salida = stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    proc_close($proc);
    $j = json_decode($salida, true);
    return $j === null ? ['__crudo__' => $salida] : $j;
}

/* ---------- datos de la prueba ---------- */
$u = uno("SELECT usu_Id, usu_NumeroDocumento FROM conf_usuarios WHERE usu_Usuario = 'pruebaica'");
if (!$u) { exit("No existe el usuario de prueba pruebaica.\n"); }
$ses = ['id_usuario' => (int) $u['usu_Id']];

$c = uno('SELECT TOP 1 ind_Id, ISNULL(ind_SinAvisosTableros,0) AS exento
            FROM ind_contribuyentes WHERE ind_NumeroIdentificacion = ? ORDER BY ind_Id',
         [$u['usu_NumeroDocumento']]);
if (!$c) { exit("pruebaica no tiene contribuyente.\n"); }
$idC = (int) $c['ind_Id'];
$exentoOriginal = (int) $c['exento'];

function exento($v) { global $idC; ejec('UPDATE ind_contribuyentes SET ind_SinAvisosTableros = ? WHERE ind_Id = ?', [$v, $idC]); }
function aut($funcion, array $post = []) { global $ses; return pedir_a('class.autorreteica.php', $ses, ['funcion' => $funcion] + $post); }
function ret($funcion, array $post = []) { global $ses; return pedir_a('class.reteica.php', $ses, ['funcion' => $funcion] + $post); }

// Un bimestre del año que no tenga NADA de este contribuyente.
$anio = (int) date('Y');
$periodo = 0;
for ($p = 6; $p >= 1; $p--) {
    if (!uno('SELECT 1 AS x FROM ind_autorreteica WHERE aut_IdContribuyente = ? AND aut_Anio = ? AND aut_Periodo = ?', [$idC, $anio, $p])) {
        $periodo = $p; break;
    }
}
if (!$periodo) { exit("El contribuyente tiene los seis bimestres de $anio ocupados: la prueba no los toca.\n"); }

$idDecl = 0;
function limpiar_todo()
{
    global $idDecl, $idC, $exentoOriginal;
    if ($idDecl) {
        ejec('DELETE FROM ind_autorreteica_actividades WHERE aua_IdAutorreteica = ?', [$idDecl]);
        ejec('DELETE FROM firmas_declaraciones WHERE fd_Modulo = ? AND fd_NumeroDeclaracion IN (SELECT CAST(aut_NumeroDeclaracion AS VARCHAR(50)) FROM ind_autorreteica WHERE aut_Id = ?)', ['AUTORRETEICA', $idDecl]);
        ejec('DELETE FROM ind_autorreteica WHERE aut_Id = ?', [$idDecl]);
    }
    ejec('UPDATE ind_contribuyentes SET ind_SinAvisosTableros = ? WHERE ind_Id = ?', [$exentoOriginal, $idC]);
}
register_shutdown_function('limpiar_todo');

function casilla($datos, $codigo)
{
    foreach ($datos['renglones'] as $r) { if ((int) $r['codigo'] === $codigo) { return (float) $r['valor']; } }
    return null;
}

/** Guarda con una base fija en la primera actividad y devuelve la respuesta. */
function guardar(array $extra = [], $base = 70000000)
{
    global $idDecl, $abierta;
    $acts = [];
    foreach ($abierta['actividades'] as $i => $a) {
        $acts[] = ['idActividad' => $a['idActividad'], 'base' => $i === 0 ? $base : 0, 'manual' => 0, 'valor' => 0];
    }
    return aut(4, ['id' => $idDecl, 'renglones' => [9 => $base, 10 => 0, 11 => 0, 12 => 0],
                   'actividades' => $acts, 'impuestoEnergia' => 0] + $extra);
}
function alMil($x) { return round($x / 1000) * 1000; }

/* ======================================================================
   Preparación
   ====================================================================== */
seccion("Preparación: contribuyente $idC, bimestre $periodo de $anio");
exento(0);
$r = aut(1, ['anio' => $anio, 'periodo' => $periodo]);
$idDecl = (int) ($r['datos']['id'] ?? 0);
prueba('se crea el borrador', $r['ok'] == 1 && $idDecl > 0, $r);
if (!$idDecl) { exit(1); }

$r = aut(3, ['id' => $idDecl]);
$abierta = $r['datos'];
prueba('abre y trae lo nuevo en extra', $r['ok'] == 1 && isset($abierta['extra']['avisosEditable'])
       && isset($abierta['extra']['avisosManual']) && isset($abierta['extra']['sinAvisos']), $abierta['extra'] ?? $r);
prueba('con la 057 la casilla se ofrece editable', ($abierta['extra']['avisosEditable'] ?? 0) == 1);
prueba('trae actividades del RIT (hace falta para liquidar)', count($abierta['actividades']) > 0,
       'El contribuyente de prueba no tiene actividades en el RIT.');
if (!count($abierta['actividades'])) { exit(1); }

/* ======================================================================
   1. Sin exención y sin escribirla
   ====================================================================== */
seccion('1. Sin exención: 15% de la casilla 15');
$r = guardar(['avisosManual' => 0, 'avisos' => 0]);
prueba('guarda', $r['ok'] == 1, $r);
$d = $r['datos'];
$c15 = casilla($d, 15); $c16 = casilla($d, 16); $c17 = casilla($d, 17);
prueba('la 15 tiene impuesto (hay algo que probar)', $c15 > 0, $c15);
prueba('16 = 15% de la 15, a miles', $c16 == alMil($c15 * 0.15), [$c15, $c16]);
prueba('17 = 15 + 16', $c17 == $c15 + $c16, [$c15, $c16, $c17]);
prueba('extra: ni exento ni a mano', $d['extra']['sinAvisos'] == 0 && $d['extra']['avisosManual'] == 0, $d['extra']);
$abierta = $d;
$esperado15 = $c15; $esperado16 = $c16;

/* ======================================================================
   2. Exento en el RIT
   ====================================================================== */
seccion('2. Exento en el RIT: sale en cero');
exento(1);
$r = guardar(['avisosManual' => 0, 'avisos' => 0]);
$d = $r['datos'];
prueba('guarda', $r['ok'] == 1, $r);
prueba('16 = 0', casilla($d, 16) == 0, casilla($d, 16));
prueba('17 = 15 (sin avisos)', casilla($d, 17) == casilla($d, 15), [casilla($d, 15), casilla($d, 17)]);
prueba('la 15 no cambia', casilla($d, 15) == $esperado15);
prueba('extra dice que esta exento', $d['extra']['sinAvisos'] == 1, $d['extra']);
$abierta = $d;

/* ======================================================================
   3. A mano, aunque esté exento
   ====================================================================== */
seccion('3. Escrita a mano se respeta');
$r = guardar(['avisosManual' => 1, 'avisos' => 123000]);
$d = $r['datos'];
prueba('guarda', $r['ok'] == 1, $r);
prueba('16 = lo escrito', casilla($d, 16) == 123000, casilla($d, 16));
prueba('17 = 15 + lo escrito', casilla($d, 17) == casilla($d, 15) + 123000, casilla($d, 17));
prueba('extra marca que va a mano', $d['extra']['avisosManual'] == 1, $d['extra']);
$tot = casilla($d, 23);
prueba('el total a pagar la incluye', $tot >= casilla($d, 17) - 0.01 * 0 && $tot > 0, $tot);
$r2 = aut(3, ['id' => $idDecl]);
prueba('al reabrir sigue a mano', casilla($r2['datos'], 16) == 123000 && $r2['datos']['extra']['avisosManual'] == 1);
$abierta = $d;

/* ======================================================================
   4. Negativo
   ====================================================================== */
seccion('4. Valor negativo');
$r = guardar(['avisosManual' => 1, 'avisos' => -5000]);
prueba('se rechaza', $r['ok'] == 0, $r);
prueba('con mensaje claro', strpos((string) $r['mensaje'], 'avisos y tableros') !== false, $r['mensaje'] ?? '');
$r2 = aut(3, ['id' => $idDecl]);
prueba('no cambió nada', casilla($r2['datos'], 16) == 123000 && $r2['datos']['extra']['avisosManual'] == 1);

/* ======================================================================
   5. Cliente viejo (no manda la marca)
   ====================================================================== */
seccion('5. Cliente que no manda la marca');
$r = guardar([]);
$d = $r['datos'];
prueba('guarda', $r['ok'] == 1, $r);
prueba('la casilla a mano sigue igual', casilla($d, 16) == 123000 && $d['extra']['avisosManual'] == 1, $d['extra'] ?? '');
$abierta = $d;

/* ======================================================================
   7. Cambiar la actividad con la casilla a mano
   ====================================================================== */
seccion('6. Cambiar la base con la casilla a mano');
$r = guardar(['avisosManual' => 1, 'avisos' => 123000], 40000000);
$d = $r['datos'];
prueba('guarda', $r['ok'] == 1, $r);
prueba('la 15 cambia', casilla($d, 15) != $esperado15, casilla($d, 15));
prueba('la 16 escrita NO se pisa', casilla($d, 16) == 123000, casilla($d, 16));
$abierta = $d;

/* ======================================================================
   6. Vaciarla
   ====================================================================== */
seccion('7. Vaciarla vuelve al cálculo');
$r = guardar(['avisosManual' => 0, 'avisos' => 0]);
$d = $r['datos'];
prueba('guarda', $r['ok'] == 1, $r);
prueba('exento: vuelve a 0', casilla($d, 16) == 0 && $d['extra']['avisosManual'] == 0, [casilla($d, 16), $d['extra']]);
exento(0);
$abierta = $d;
$r = guardar(['avisosManual' => 0, 'avisos' => 0]);
$d = $r['datos'];
prueba('no exento: vuelve al 15%', casilla($d, 16) == alMil(casilla($d, 15) * 0.15), [casilla($d, 15), casilla($d, 16)]);

/* ======================================================================
   8. Retención no cambia
   ====================================================================== */
seccion('8. Retención (RETEICA) no tiene casilla de avisos');
$f = uno("SELECT COUNT(*) AS n FROM ind_renglones_retencion WHERE ren_Modulo = 'RETEICA' AND ren_Nombre LIKE '%AVISOS%'");
prueba('el catálogo de retención no tiene avisos y tableros', (int) $f['n'] === 0, $f);
$f = uno("SELECT COUNT(*) AS n FROM sys.columns WHERE object_id = OBJECT_ID('dbo.ind_reteica') AND name = 'ret_AvisosManual'");
prueba('ind_reteica no recibió la columna', (int) $f['n'] === 0);

/* ======================================================================
   Resultado
   ====================================================================== */
echo "\n" . str_repeat('=', 60) . "\n";
echo "Pruebas ok: $ok   Fallas: " . count($fallas) . "\n";
if ($fallas) { echo "Fallaron:\n  - " . implode("\n  - ", $fallas) . "\n"; exit(1); }
