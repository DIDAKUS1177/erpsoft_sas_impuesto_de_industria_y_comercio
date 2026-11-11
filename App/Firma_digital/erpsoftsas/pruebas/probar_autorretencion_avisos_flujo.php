<?php
/**
 * Prueba de la casilla 16 de AUTORRETENCION (avisos y tableros) con FIRMAS y CORRECCION.
 *
 * Complementa probar_autorretencion_avisos.php (que prueba el calculo). Aqui:
 *   1. Guardar una firmada SIN cambiar nada conserva las firmas.
 *   2. Escribir la casilla 16 a mano en una firmada QUITA las firmas.
 *   3. Cambiar el valor escrito, o vaciarlo, tambien las quita.
 *   4. Corregir una presentada con la casilla a mano: la correccion parte del MISMO valor.
 *   5. En la correccion se puede cambiar o vaciar la casilla; la original no se toca.
 *
 * Uso (dentro del contenedor web):
 *     docker exec -w /var/www/html/erpsoftsas erpsoftsas_web_completo php pruebas/probar_autorretencion_avisos_flujo.php
 *
 * Crea borradores del usuario pruebaica, los borra al terminar y no consume numeros de la serie
 * (la presentada se simula con UPDATE). Se NIEGA a correr en las bases de los municipios.
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

$dirSesiones = sys_get_temp_dir() . '/sesiones_prueba_avisos_flujo';
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
$ids = [];
function limpiar_todo()
{
    global $ids, $idC, $exentoOriginal;
    rsort($ids);   // primero la correccion (id mayor), despues la original
    foreach ($ids as $id) {
        $n = uno('SELECT aut_NumeroDeclaracion AS n FROM ind_autorreteica WHERE aut_Id = ?', [$id]);
        if ($n) {
            ejec('DELETE FROM firmas_declaraciones WHERE fd_Modulo = ? AND fd_NumeroDeclaracion = ?', ['AUTORRETEICA', (string) $n['n']]);
        }
        ejec('DELETE FROM ind_autorreteica_actividades WHERE aua_IdAutorreteica = ?', [$id]);
        ejec('DELETE FROM ind_autorreteica WHERE aut_Id = ?', [$id]);
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
$ids[] = $idDecl;
$r = aut(3, ['id' => $idDecl]);
$abierta = $r['datos'];
if (!count($abierta['actividades'])) { exit("El contribuyente de prueba no tiene actividades en el RIT.\n"); }

function numeroDe($id) { $n = uno('SELECT aut_NumeroDeclaracion AS n FROM ind_autorreteica WHERE aut_Id = ?', [$id]); return (string) $n['n']; }
function firmar($id)
{
    global $ses;
    ejec("INSERT INTO firmas_declaraciones (fd_NumeroDeclaracion, fd_IdUsuario, fd_NombreUsuario, fd_EmailUsuario, fd_Rol, fd_Modulo, fd_FechaHora)
          VALUES (?, ?, 'Prueba', 'prueba@pruebalocal.invalid', 'declarante', 'AUTORRETEICA', GETDATE())",
         [numeroDe($id), $ses['id_usuario']]);
}
function nFirmas($id)
{
    $f = uno("SELECT COUNT(*) AS n FROM firmas_declaraciones WHERE fd_Modulo = 'AUTORRETEICA' AND fd_NumeroDeclaracion = ?", [numeroDe($id)]);
    return (int) $f['n'];
}

seccion('1. Guardar una firmada sin cambios conserva las firmas');
$r = guardar(['avisosManual' => 0, 'avisos' => 0]);
prueba('guarda el borrador calculado', $r['ok'] == 1, $r);
$abierta = $r['datos'];
firmar($idDecl);
prueba('queda firmada', nFirmas($idDecl) === 1);
$r = guardar(['avisosManual' => 0, 'avisos' => 0]);
prueba('guarda otra vez igual', $r['ok'] == 1, $r);
prueba('NO se quitaron las firmas', nFirmas($idDecl) === 1 && empty($r['datos']['firmasQuitadas']), [nFirmas($idDecl), $r['datos']['firmasQuitadas'] ?? null]);
$abierta = $r['datos'];

seccion('2. Escribir la casilla 16 a mano quita las firmas');
$r = guardar(['avisosManual' => 1, 'avisos' => 123000]);
prueba('guarda', $r['ok'] == 1, $r);
prueba('se quitaron las firmas', nFirmas($idDecl) === 0 && ($r['datos']['firmasQuitadas'] ?? 0) == 1, [nFirmas($idDecl), $r['datos']['firmasQuitadas'] ?? null]);
prueba('el mensaje avisa que hay que firmar de nuevo', strpos((string) ($r['mensaje'] ?? ''), 'firmas') !== false, $r['mensaje'] ?? '');
$abierta = $r['datos'];

seccion('2b. Firmada con la casilla a mano: guardar igual conserva las firmas');
firmar($idDecl);
$r = guardar(['avisosManual' => 1, 'avisos' => 123000]);
prueba('guarda igual', $r['ok'] == 1 && casilla($r['datos'], 16) == 123000, $r);
prueba('las firmas siguen', nFirmas($idDecl) === 1);
$abierta = $r['datos'];

seccion('3. Cambiar el valor escrito quita las firmas');
$r = guardar(['avisosManual' => 1, 'avisos' => 150000]);
prueba('guarda con otro valor', $r['ok'] == 1 && casilla($r['datos'], 16) == 150000, $r);
prueba('se quitaron las firmas', nFirmas($idDecl) === 0);
$abierta = $r['datos'];
firmar($idDecl);
seccion('3b. Vaciarla (volver al calculo) quita las firmas');
$r = guardar(['avisosManual' => 0, 'avisos' => 0]);
prueba('guarda', $r['ok'] == 1, $r);
prueba('vuelve al 15%', casilla($r['datos'], 16) == alMil(casilla($r['datos'], 15) * 0.15), [casilla($r['datos'], 15), casilla($r['datos'], 16)]);
prueba('se quitaron las firmas', nFirmas($idDecl) === 0);
$abierta = $r['datos'];

seccion('4. Corregir: la correccion parte del mismo valor escrito a mano');
$r = guardar(['avisosManual' => 1, 'avisos' => 123000]);
prueba('la original queda con 123.000 a mano', $r['ok'] == 1 && casilla($r['datos'], 16) == 123000, $r);
$tot_orig = casilla($r['datos'], 23);
// Se simula la presentacion (sin consumir un numero de la serie).
ejec('UPDATE ind_autorreteica SET aut_Estado = 2, aut_FechaPresentacion = GETDATE() WHERE aut_Id = ?', [$idDecl]);
$r = aut(7, ['id' => $idDecl]);
$idCorr = (int) ($r['datos']['id'] ?? 0);
prueba('Corregir crea el borrador de la correccion', $r['ok'] == 1 && $idCorr > 0 && $idCorr !== $idDecl, $r);
if (!$idCorr) { exit(1); }
$ids[] = $idCorr;
$r = aut(3, ['id' => $idCorr]);
$d = $r['datos'];
prueba('abre la correccion', $r['ok'] == 1, $r);
prueba('la casilla 16 conserva los 123.000', casilla($d, 16) == 123000, casilla($d, 16));
prueba('sigue marcada a mano', ($d['extra']['avisosManual'] ?? 0) == 1, $d['extra'] ?? '');
prueba('la 23 coincide con la original', casilla($d, 23) == $tot_orig, [casilla($d, 23), $tot_orig]);
prueba('es un borrador sin firmas', nFirmas($idCorr) === 0);

seccion('5. En la correccion se puede cambiar o vaciar la casilla');
$idOrig = $idDecl;
$idDecl = $idCorr; $abierta = $d;
firmar($idCorr);
$r = guardar(['avisosManual' => 1, 'avisos' => 200000]);
prueba('cambia a 200.000', $r['ok'] == 1 && casilla($r['datos'], 16) == 200000, $r);
prueba('quita las firmas de la correccion', nFirmas($idCorr) === 0);
$abierta = $r['datos'];
$r = guardar(['avisosManual' => 0, 'avisos' => 0]);
prueba('vaciarla vuelve al 15%', $r['ok'] == 1 && casilla($r['datos'], 16) == alMil(casilla($r['datos'], 15) * 0.15), $r);
$o = uno('SELECT aut_ValorConcepto16 AS v, aut_AvisosManual AS m, aut_Estado AS e FROM ind_autorreteica WHERE aut_Id = ?', [$idOrig]);
prueba('la original NO cambio (sigue a mano, 123.000, presentada)', (float) $o['v'] == 123000 && (int) $o['m'] === 1 && (int) $o['e'] === 2, $o);

echo "\n" . str_repeat('=', 60) . "\n";
echo "Pruebas ok: $ok   Fallas: " . count($fallas) . "\n";
if ($fallas) { echo "Fallaron:\n  - " . implode("\n  - ", $fallas) . "\n"; exit(1); }
