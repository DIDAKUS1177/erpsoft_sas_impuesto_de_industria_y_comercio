<?php
/**
 * Prueba de los cambios del documento "CAMBIOS ICA WEB" de la Alcaldía de Paipa (2026-11-19/20):
 *
 *   A. Autorretención: con impuesto por generación de energía (Ley 56) no se exige que la
 *      casilla 13 sea igual a la suma de las bases; sin energía, la regla sigue.
 *   B. ICA, casilla 37 (interés de mora): el contribuyente no la escribe; la Alcaldía sí.
 *   C. Contribuyente INACTIVO (cese registrado en el RIT): consulta, pero no declara ni
 *      modifica; la pantalla recibe el aviso; al quitar el cese vuelve a poder.
 *
 * Uso (dentro del contenedor web):
 *     docker exec -w /var/www/html/erpsoftsas erpsoftsas_web_completo php pruebas/probar_cambios_ica_web.php
 *
 * Usa el usuario pruebaica, borra lo que crea y deja el cese como estaba. Se NIEGA a correr en
 * las bases de los municipios.
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

$dirSesiones = sys_get_temp_dir() . '/sesiones_prueba_cambios_ica_web';
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
$admin = uno("SELECT usu_Id FROM conf_usuarios WHERE usu_Usuario = 'administrador'");
$sesAdmin = ['id_usuario' => (int) ($admin['usu_Id'] ?? 1)];

$c = uno('SELECT TOP 1 ind_Id, CONVERT(varchar(10), ind_FechaCese, 23) AS cese, ind_CausalCese AS causal, ind_ObservacionCese AS obs
            FROM ind_contribuyentes WHERE ind_NumeroIdentificacion = ? ORDER BY ind_Id', [$u['usu_NumeroDocumento']]);
if (!$c) { exit("pruebaica no tiene contribuyente.\n"); }
$idC = (int) $c['ind_Id'];
$ceseOriginal = $c;

function aut($funcion, array $post = [], $sesion = null) { global $ses; return pedir_a('class.autorreteica.php', $sesion ?: $ses, ['funcion' => $funcion] + $post); }
function ica($funcion, array $post = [], $sesion = null) { global $ses; return pedir_a('class.declaracionesICA.php', $sesion ?: $ses, ['funcion' => $funcion] + $post); }
function permisos($sesion = null) { global $ses; return pedir_a('class.permisos.php', $sesion ?: $ses, ['funcion' => 6]); }
function cese($fecha) { global $idC; ejec('UPDATE ind_contribuyentes SET ind_FechaCese = ? WHERE ind_Id = ?', [$fecha, $idC]); }

$ids = [];
function limpiar_todo()
{
    global $ids, $idC, $ceseOriginal;
    rsort($ids);
    foreach ($ids as $id) {
        ejec('DELETE FROM ind_autorreteica_actividades WHERE aua_IdAutorreteica = ?', [$id]);
        ejec('DELETE FROM ind_autorreteica WHERE aut_Id = ?', [$id]);
    }
    ejec('UPDATE ind_contribuyentes SET ind_FechaCese = ?, ind_CausalCese = ?, ind_ObservacionCese = ? WHERE ind_Id = ?',
         [$ceseOriginal['cese'] ?: null, $ceseOriginal['causal'], $ceseOriginal['obs'], $idC]);
}
register_shutdown_function('limpiar_todo');

// Se parte de un contribuyente ACTIVO.
cese(null);

/* ======================================================================
   A. Autorretención: casilla 13 y energía
   ====================================================================== */
$anio = (int) date('Y');
$periodo = 0;
for ($p = 6; $p >= 1; $p--) {
    if (!uno('SELECT 1 AS x FROM ind_autorreteica WHERE aut_IdContribuyente = ? AND aut_Anio = ? AND aut_Periodo = ?', [$idC, $anio, $p])) {
        $periodo = $p; break;
    }
}
if (!$periodo) { exit("El contribuyente tiene los seis bimestres de $anio ocupados: la prueba no los toca.\n"); }

seccion("A. Autorretención, bimestre $periodo de $anio: casilla 13 contra la suma de las bases");
$r = aut(1, ['anio' => $anio, 'periodo' => $periodo]);
$idDecl = (int) ($r['datos']['id'] ?? 0);
prueba('se crea el borrador', $r['ok'] == 1 && $idDecl > 0, $r);
if (!$idDecl) { exit(1); }
$ids[] = $idDecl;
$abierta = aut(3, ['id' => $idDecl])['datos'];
if (!count($abierta['actividades'])) { exit("El contribuyente de prueba no tiene actividades en el RIT.\n"); }

function guardar($casilla9, $base, $energia)
{
    global $idDecl, $abierta;
    $acts = [];
    foreach ($abierta['actividades'] as $i => $a) {
        $acts[] = ['idActividad' => $a['idActividad'], 'base' => $i === 0 ? $base : 0, 'manual' => 0, 'valor' => 0];
    }
    return aut(4, ['id' => $idDecl, 'renglones' => [9 => $casilla9, 10 => 0, 11 => 0, 12 => 0],
                   'actividades' => $acts, 'impuestoEnergia' => $energia, 'avisosManual' => 0, 'avisos' => 0]);
}

$r = guardar(10000000, 10000000, 0);
prueba('cuadrada y sin energía: guarda', $r['ok'] == 1, $r);
$r = guardar(10000000, 7000000, 0);
prueba('descuadrada y SIN energía: no guarda', $r['ok'] == 0 && strpos((string) ($r['mensaje'] ?? ''), 'casilla 13') !== false, $r);
$r = guardar(10000000, 7000000, 5000000);
prueba('descuadrada y CON energía: guarda', $r['ok'] == 1, $r);
$f = uno('SELECT aut_ValorConcepto13 AS c13, aut_ImpuestoEnergia AS e,
                 (SELECT SUM(aua_IngresosGravados) FROM ind_autorreteica_actividades WHERE aua_IdAutorreteica = aut_Id AND aua_Activo = 1) AS suma
            FROM ind_autorreteica WHERE aut_Id = ?', [$idDecl]);
prueba('quedó guardada con 13 = 10.000.000, bases = 7.000.000 y energía = 5.000.000',
       round((float) $f['c13']) == 10000000 && round((float) $f['suma']) == 7000000 && round((float) $f['e']) == 5000000, $f);
$r = guardar(10000000, 7000000, 0);
prueba('al quitar la energía vuelve a exigir el cuadre', $r['ok'] == 0 && strpos((string) ($r['mensaje'] ?? ''), 'casilla 13') !== false, $r);

/* ======================================================================
   B. ICA: casilla 37 (interés de mora)
   ====================================================================== */
seccion('B. ICA, casilla 37 (interés de mora, renglón 16 de la tabla)');
$aviso = 'recibo de pago';
$r = ica(7, ['idDeclaracion' => 0, 'campoSeleccionado' => 16, 'valorLimpio' => '5000']);
prueba('el contribuyente no puede escribir intereses', $r['ok'] == 0 && strpos((string) ($r['mensaje'] ?? ''), $aviso) !== false, $r);
$r = ica(7, ['idDeclaracion' => 0, 'campoSeleccionado' => 16, 'valorLimpio' => '0']);
prueba('un cero no se rechaza por esta regla', strpos((string) ($r['mensaje'] ?? ''), $aviso) === false, $r);
$r = ica(7, ['idDeclaracion' => 0, 'campoSeleccionado' => 14, 'valorLimpio' => '5000']);
prueba('otra casilla (35) no se rechaza por esta regla', strpos((string) ($r['mensaje'] ?? ''), $aviso) === false, $r);
// Casilla 36 (descuento por pronto pago, renglón 15): la misma regla desde el 2026-11-25.
$r = ica(7, ['idDeclaracion' => 0, 'campoSeleccionado' => 15, 'valorLimpio' => '5000']);
prueba('el contribuyente no puede escribir el descuento por pronto pago', $r['ok'] == 0 && strpos((string) ($r['mensaje'] ?? ''), 'pronto pago') !== false, $r);
$r = ica(7, ['idDeclaracion' => 0, 'campoSeleccionado' => 15, 'valorLimpio' => '0']);
prueba('un cero en la 36 no se rechaza por esta regla', strpos((string) ($r['mensaje'] ?? ''), 'pronto pago') === false, $r);
$r = ica(7, ['idDeclaracion' => 0, 'campoSeleccionado' => 15, 'valorLimpio' => '5000'], $sesAdmin);
prueba('la Alcaldía tampoco puede escribir la 36 (2026-11-27)', $r['ok'] == 0 && strpos((string) ($r['mensaje'] ?? ''), 'pronto pago') !== false, $r);
$r = ica(7, ['idDeclaracion' => 0, 'campoSeleccionado' => 16, 'valorLimpio' => '5000'], $sesAdmin);
prueba('la Alcaldía tampoco puede escribir la 37 (2026-11-27)', $r['ok'] == 0 && strpos((string) ($r['mensaje'] ?? ''), $aviso) !== false, $r);

/* ======================================================================
   C. Contribuyente inactivo
   ====================================================================== */
seccion('C. Contribuyente inactivo (cese de actividades registrado)');
$p = permisos();
$clavesActivo = $p['datos']['claves'] ?? [];
prueba('activo: la pantalla no lo marca inactivo', $p['ok'] == 1 && empty($p['datos']['inactivo']), $p['datos'] ?? $p);
$modifican = array_values(array_filter($clavesActivo, function ($k) {
    return in_array(substr($k, strrpos($k, '.') + 1), ['editar', 'firmar', 'presentar', 'corregir', 'documentos'], true);
}));
prueba('activo: su rol tiene permisos que modifican', count($modifican) > 0, $clavesActivo);

cese('2026-01-15');
$p = permisos();
$clavesInactivo = $p['datos']['claves'] ?? [];
prueba('inactivo: la pantalla lo recibe marcado, con el aviso', !empty($p['datos']['inactivo']) && strpos((string) ($p['datos']['avisoInactivo'] ?? ''), 'INACTIVO') !== false, $p['datos'] ?? $p);
prueba('inactivo: no le queda ningún permiso que modifique', count(array_intersect($clavesInactivo, $modifican)) === 0, array_intersect($clavesInactivo, $modifican));
$ver = array_values(array_filter($clavesActivo, function ($k) { return in_array(substr($k, strrpos($k, '.') + 1), ['ver', 'pagar', 'consultar'], true); }));
prueba('inactivo: conserva ver, pagar y consultar', count(array_diff($ver, $clavesInactivo)) === 0, array_diff($ver, $clavesInactivo));

$r = aut(2, []);
prueba('inactivo: puede consultar sus autorretenciones', $r['ok'] == 1, $r);
$r = aut(3, ['id' => $idDecl]);
prueba('inactivo: puede abrir una para verla', $r['ok'] == 1, $r);
$r = guardar(10000000, 10000000, 0);
prueba('inactivo: NO puede guardar una autorretención', $r['ok'] == 0 && strpos((string) ($r['mensaje'] ?? ''), 'INACTIVO') !== false, $r);
$r = ica(1, []);
prueba('inactivo: NO puede crear una declaración de ICA', ($r['ok'] ?? 0) == 0 && strpos((string) ($r['mensaje'] ?? ''), 'INACTIVO') !== false, $r);
$r = ica(8, []);
prueba('inactivo: puede consultar sus declaraciones de ICA', strpos((string) ($r['mensaje'] ?? ''), 'INACTIVO') === false, $r);

$p = permisos($sesAdmin);
prueba('la Alcaldía no se ve afectada por el cese de ese contribuyente', empty($p['datos']['inactivo']) && !empty($p['datos']['admin']), $p['datos'] ?? $p);

cese(null);
$p = permisos();
prueba('sin el cese vuelve a tener sus permisos', empty($p['datos']['inactivo']) && count(array_diff($clavesActivo, $p['datos']['claves'] ?? [])) === 0, $p['datos'] ?? $p);
$r = guardar(10000000, 10000000, 0);
prueba('y vuelve a poder guardar', $r['ok'] == 1, $r);

echo "\n" . $ok . ' de ' . ($ok + count($fallas)) . "\n";
if ($fallas) { echo "FALLAS:\n  - " . implode("\n  - ", $fallas) . "\n"; exit(1); }
