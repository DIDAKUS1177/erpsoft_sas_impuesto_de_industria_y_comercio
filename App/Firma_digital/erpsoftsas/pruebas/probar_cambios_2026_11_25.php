<?php
/**
 * Prueba de los pedidos de la Alcaldía de Paipa del 2026-11-25 (reunión con Juan):
 *
 *   A. Autorretención: la Alcaldía puede agregar una actividad que el RIT del contribuyente no
 *      tiene; el contribuyente no (pero conserva la que le puso la Alcaldía).
 *   B. Quién presentó la declaración (migración 061) y quién registró el pago: salen en el
 *      Informe rápido y en el Informe general.
 *   C. Contribuyente activo o inactivo en los informes.
 *
 * La casilla 36 (descuento por pronto pago) se prueba en probar_cambios_ica_web.php.
 *
 * Uso (dentro del contenedor web):
 *     docker exec -w /var/www/html/erpsoftsas erpsoftsas_web_completo php pruebas/probar_cambios_2026_11_25.php
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
include_once SERVER . '/business/class.permisosRol.php';
include_once SERVER . '/business/class.pseModulo.php';
include_once SERVER . '/business/class.informePagos.php';
include_once SERVER . '/business/class.informeCatalogo.php';
include_once SERVER . '/business/class.informeConstructor.php';
ini_set('display_errors', '1');

use erpsoftsas\PseModulo;
use erpsoftsas\InformePagos;
use erpsoftsas\InformeCatalogo;
use erpsoftsas\InformeConstructor;

$ok = 0;
$fallas = [];
function prueba($nombre, $condicion, $detalle = '')
{
    global $ok, $fallas;
    if ($condicion) { $ok++; echo "  ok   $nombre\n"; return true; }
    $fallas[] = $nombre;
    $d = is_string($detalle) ? $detalle : json_encode($detalle, JSON_UNESCAPED_UNICODE);
    echo "  FALLA $nombre" . ($d !== '' ? '  -> ' . mb_substr($d, 0, 600) : '') . "\n";
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

$dirSesiones = sys_get_temp_dir() . '/sesiones_prueba_cambios_1125';
@mkdir($dirSesiones, 0777, true);

function aut($funcion, array $post, array $ses)
{
    global $dirSesiones, $raiz;
    $cfg = ['destino' => $raiz . '/business/controller/class.autorreteica.php', 'sesion' => $ses,
            'post' => ['funcion' => $funcion] + $post, 'sesiones' => $dirSesiones];
    $cmd = [PHP_BINARY, '-d', 'disable_functions=stream_socket_client,fsockopen,pfsockopen', __FILE__, '--peticion'];
    $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $raiz);
    fwrite($pipes[0], json_encode($cfg));
    fclose($pipes[0]);
    $salida = stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    proc_close($proc);
    $j = json_decode($salida, true);
    return $j === null ? ['ok' => null, '__crudo__' => mb_substr($salida, 0, 400)] : $j;
}

/* ---------- datos de la prueba ---------- */
$u = uno("SELECT usu_Id, usu_NumeroDocumento FROM conf_usuarios WHERE usu_Usuario = 'pruebaica'");
if (!$u) { exit("No existe el usuario de prueba pruebaica.\n"); }
$ses = ['id_usuario' => (int) $u['usu_Id']];
$admin = uno("SELECT usu_Id, usu_Nombres, usu_Apellidos, usu_Usuario FROM conf_usuarios WHERE usu_Usuario = 'administrador'");
if (!$admin) { exit("No existe el usuario administrador.\n"); }
$idAdmin = (int) $admin['usu_Id'];
$sesAdmin = ['id_usuario' => $idAdmin];
$nombreAdmin = trim(trim((string) $admin['usu_Nombres']) . ' ' . trim((string) $admin['usu_Apellidos'])) ?: (string) $admin['usu_Usuario'];

$c = uno('SELECT TOP 1 ind_Id, CONVERT(varchar(10), ind_FechaCese, 23) AS cese
            FROM ind_contribuyentes WHERE ind_NumeroIdentificacion = ? ORDER BY ind_Id', [$u['usu_NumeroDocumento']]);
if (!$c) { exit("pruebaica no tiene contribuyente.\n"); }
$idC = (int) $c['ind_Id'];
$ceseOriginal = $c['cese'];
$documento = (int) $u['usu_NumeroDocumento'];

$ids = [];
function limpiar_todo()
{
    global $ids, $idC, $ceseOriginal;
    rsort($ids);
    foreach ($ids as $id) {
        ejec('DELETE FROM ind_autorreteica_actividades WHERE aua_IdAutorreteica = ?', [$id]);
        ejec('DELETE FROM ind_autorreteica WHERE aut_Id = ?', [$id]);
    }
    ejec('UPDATE ind_contribuyentes SET ind_FechaCese = ? WHERE ind_Id = ?', [$ceseOriginal ?: null, $idC]);
}
register_shutdown_function('limpiar_todo');
ejec('UPDATE ind_contribuyentes SET ind_FechaCese = NULL WHERE ind_Id = ?', [$idC]);

/* ======================================================================
   A. Autorretención: actividades que el RIT no tiene
   ====================================================================== */
$anio = (int) date('Y');
$periodo = 0;
for ($p = 6; $p >= 1; $p--) {
    if (!uno('SELECT 1 AS x FROM ind_autorreteica WHERE aut_IdContribuyente = ? AND aut_Anio = ? AND aut_Periodo = ?', [$idC, $anio, $p])) {
        $periodo = $p; break;
    }
}
if (!$periodo) { exit("El contribuyente tiene los seis bimestres de $anio ocupados: la prueba no los toca.\n"); }

seccion("A. Autorretención, bimestre $periodo de $anio: actividades que el RIT no tiene");
$r = aut(1, ['anio' => $anio, 'periodo' => $periodo, 'idContribuyente' => $idC], $sesAdmin);
$idDecl = (int) ($r['datos']['id'] ?? 0);
prueba('la Alcaldía crea el borrador del contribuyente', $r['ok'] == 1 && $idDecl > 0, $r);
if (!$idDecl) { exit(1); }
$ids[] = $idDecl;

$abAdmin = aut(3, ['id' => $idDecl], $sesAdmin);
$abContr = aut(3, ['id' => $idDecl], $ses);
prueba('a la Alcaldía la pantalla le ofrece agregar actividades', (int) ($abAdmin['datos']['extra']['agregaActividades'] ?? -1) === 1, $abAdmin['datos']['extra'] ?? $abAdmin);
prueba('al contribuyente no', (int) ($abContr['datos']['extra']['agregaActividades'] ?? -1) === 0, $abContr['datos']['extra'] ?? $abContr);

$delRit = array_map(function ($a) { return (int) $a['idActividad']; }, $abAdmin['datos']['actividades'] ?? []);
// Una actividad del catálogo del año cuyo CÓDIGO no esté en el RIT del contribuyente.
$cat = aut(8, ['anio' => $anio], $sesAdmin)['datos'] ?? [];
$codigosRit = [];
$st = $con->consultar('SELECT ac.acc_Codigo AS cod FROM ind_actividad_contribuyente atc
                         INNER JOIN ind_actividadescomercio ac ON ac.acc_Id = atc.atc_IdCodigoActividad
                        WHERE atc.atc_IdContribuyente = ?', [$idC]);
while ($f = $con->obnerFila($st)) { $codigosRit[(string) $f['cod']] = true; }
$ajenas = array_values(array_filter($cat, function ($a) use ($codigosRit, $delRit) {
    return !isset($codigosRit[(string) $a['codigo']]) && !in_array((int) $a['id'], $delRit, true);
}));
if (count($ajenas) < 2) { exit("El catálogo de $anio no tiene dos actividades fuera del RIT del contribuyente de prueba.\n"); }
$ajena = (int) $ajenas[0]['id'];
$otraAjena = (int) $ajenas[1]['id'];

function filas(array $ids, $base)
{
    $acts = [];
    foreach ($ids as $i => $id) { $acts[] = ['idActividad' => $id, 'base' => $i === 0 ? $base : 0, 'manual' => 0, 'valor' => 0]; }
    return $acts;
}
function guardar(array $idsAct, array $sesion)
{
    global $idDecl;
    return aut(4, ['id' => $idDecl, 'renglones' => [9 => 4000000, 10 => 0, 11 => 0, 12 => 0],
                   'actividades' => filas($idsAct, 4000000), 'impuestoEnergia' => 0, 'avisosManual' => 0, 'avisos' => 0], $sesion);
}
function guardadas()
{
    global $con, $idDecl;
    $r = [];
    $st = $con->consultar('SELECT aua_IdActividad AS id FROM ind_autorreteica_actividades WHERE aua_IdAutorreteica = ? AND aua_Activo = 1', [$idDecl]);
    while ($f = $con->obnerFila($st)) { $r[] = (int) $f['id']; }
    sort($r);
    return $r;
}

$antes = guardadas();
$r = guardar(array_merge([$ajena], $delRit), $ses);
prueba('el contribuyente NO puede declarar una actividad que no está en su RIT',
       $r['ok'] == 0 && strpos((string) ($r['mensaje'] ?? ''), 'no está en su RIT') !== false, $r);
prueba('... y no quedó ningún cambio', guardadas() === $antes, guardadas());

$r = guardar(array_merge([$ajena], $delRit), $sesAdmin);
prueba('la Alcaldía SÍ puede agregarla', $r['ok'] == 1, $r);
prueba('... y queda guardada en la declaración', in_array($ajena, guardadas(), true), guardadas());
$f = uno('SELECT aua_Tarifa AS t, aua_ValorImpuesto AS v, (SELECT acc_Tarifa FROM ind_actividadescomercio WHERE acc_Id = aua_IdActividad) AS tc
            FROM ind_autorreteica_actividades WHERE aua_IdAutorreteica = ? AND aua_IdActividad = ?', [$idDecl, $ajena]);
prueba('... con la tarifa del catálogo y el impuesto liquidado',
       abs((float) $f['t'] - (float) $f['tc']) < 0.0000001 && round((float) $f['v']) == round(round(4000000 * (float) $f['tc'] / 1000) * 1000), $f);

$r = aut(3, ['id' => $idDecl], $sesAdmin);
$abiertas = array_map(function ($a) { return (int) $a['idActividad']; }, $r['datos']['actividades'] ?? []);
prueba('al volver a abrirla sigue ahí (el RIT no la borra ni la repite)',
       count(array_keys($abiertas, $ajena, true)) === 1 && count(array_diff($delRit, $abiertas)) === 0, $abiertas);

$r = guardar(array_merge([$ajena], $delRit), $ses);
prueba('el contribuyente puede guardar conservando la que le puso la Alcaldía', $r['ok'] == 1, $r);
$r = guardar(array_merge([$ajena, $otraAjena], $delRit), $ses);
prueba('... pero no agregar otra por su cuenta', $r['ok'] == 0 && strpos((string) ($r['mensaje'] ?? ''), 'no está en su RIT') !== false, $r);
prueba('... y la declaración queda como estaba', in_array($ajena, guardadas(), true) && !in_array($otraAjena, guardadas(), true), guardadas());

// Al guardar, el borrador se vuelve a abrir y recibe las actividades del RIT que le falten
// (con base 0): la agregada sigue ahí con sus ingresos.
$r = guardar([$ajena], $sesAdmin);
$f = uno('SELECT aua_IngresosGravados AS b FROM ind_autorreteica_actividades WHERE aua_IdAutorreteica = ? AND aua_IdActividad = ? AND aua_Activo = 1', [$idDecl, $ajena]);
prueba('la Alcaldía puede declarar solo por la actividad agregada', $r['ok'] == 1 && round((float) ($f['b'] ?? 0)) == 4000000, [$r['mensaje'] ?? $r, $f, guardadas()]);

/* ======================================================================
   B. Quién presentó y quién registró
   ====================================================================== */
seccion('B. Quién presentó la declaración (migración 061) y quién registró el pago');
$m = PseModulo::get('autorreteica');
$col = PseModulo::columnaQuienPresenta($con, $m);
if ($col === null) {
    echo "  (la base no tiene la migración 061: se comprueba solo que nada se rompe)\n";
    PseModulo::anotarQuienPresenta($con, 'autorreteica', $idDecl, $idAdmin);
    prueba('sin la 061, anotar no hace nada ni falla', true);
    ejec('UPDATE ind_autorreteica SET aut_Estado = 2, aut_FechaPresentacion = GETDATE() WHERE aut_Id = ?', [$idDecl]);
    $filas = InformePagos::filas($con, 'autorreteica', ['estado' => 'todas', 'documento' => $documento, 'anio' => $anio, 'periodo' => $periodo]);
    prueba('sin la 061, el Informe rápido trae la fila con «Presentada por» vacío', count($filas) === 1 && $filas[0]['presento'] === '', $filas);
} else {
    prueba('la columna es aut_IdUsuarioPresenta', $col === 'aut_IdUsuarioPresenta', $col);
    $f = uno("SELECT $col AS u FROM ind_autorreteica WHERE aut_Id = ?", [$idDecl]);
    prueba('un borrador no tiene quién lo presentó', $f['u'] === null, $f);

    ejec('UPDATE ind_autorreteica SET aut_Estado = 2, aut_FechaPresentacion = GETDATE() WHERE aut_Id = ?', [$idDecl]);
    $filas = InformePagos::filas($con, 'autorreteica', ['estado' => 'todas', 'documento' => $documento, 'anio' => $anio, 'periodo' => $periodo]);
    prueba('presentada sin anotar (como las de antes): «Presentada por» va vacío', count($filas) === 1 && $filas[0]['presento'] === '', $filas);

    PseModulo::anotarQuienPresenta($con, 'autorreteica', $idDecl, 0);
    $f = uno("SELECT $col AS u FROM ind_autorreteica WHERE aut_Id = ?", [$idDecl]);
    prueba('sin sesión no se anota nada', $f['u'] === null, $f);

    PseModulo::anotarQuienPresenta($con, 'autorreteica', $idDecl, $idAdmin);
    $f = uno("SELECT $col AS u FROM ind_autorreteica WHERE aut_Id = ?", [$idDecl]);
    prueba('queda anotada la cuenta que la presentó', (int) $f['u'] === $idAdmin, $f);

    $filas = InformePagos::filas($con, 'autorreteica', ['estado' => 'todas', 'documento' => $documento, 'anio' => $anio, 'periodo' => $periodo]);
    prueba('Informe rápido: «Presentada por» trae su nombre', count($filas) === 1 && $filas[0]['presento'] === $nombreAdmin, [$nombreAdmin, $filas]);
    prueba('Informe rápido: sin pago, «Pago registrado por» va vacío', ($filas[0]['registro'] ?? null) === '', $filas);

    InformeCatalogo::olvidar();
    $ctx = ['usuario' => 1, 'admin' => true, 'personalizados' => true, 'contacto' => true, 'plantillas' => true,
            'fuentes' => ['ica' => true, 'reteica' => true, 'autorreteica' => true, 'contribuyentes' => true]];
    $cfg = ['v' => 1, 'modo' => 'declaracion', 'impuesto' => 'autorreteica',
            'filtros' => [['c' => 'documento', 'op' => 'in', 'v' => [$documento]], ['c' => 'anio', 'op' => 'in', 'v' => [$anio]]],
            'columnas' => [['c' => 'documento'], ['c' => 'periodo'], ['c' => 'presentadaPor'], ['c' => 'estadoContribuyente']], 'orden' => []];
    $q = InformeConstructor::armar(InformeConstructor::validar($con, $cfg, $ctx, 'estricto'));
    $gen = [];
    InformeConstructor::recorrer($con, $q, function ($f) use (&$gen) { $gen[] = $f; });
    $mia = array_values(array_filter($gen, function ($f) use ($nombreAdmin) { return in_array($nombreAdmin, array_map('strval', array_values($f)), true); }));
    prueba('Informe general: la columna «Presentada por» trae su nombre', count($mia) === 1, $gen);

    foreach (['ica', 'reteica'] as $k) {
        $mm = PseModulo::get($k);
        prueba("la columna existe también en $k", PseModulo::columnaQuienPresenta($con, $mm) === $mm['prefijo'] . '_IdUsuarioPresenta');
    }
}

/* ======================================================================
   C. Contribuyente activo o inactivo en los informes
   ====================================================================== */
seccion('C. Contribuyente activo o inactivo en los informes');
$filtro = ['estado' => 'todas', 'documento' => $documento, 'anio' => $anio, 'periodo' => $periodo];
$filas = InformePagos::filas($con, 'autorreteica', $filtro);
prueba('Informe rápido: sin cese sale Activo', ($filas[0]['estadoContribuyente'] ?? '') === 'Activo', $filas);
ejec("UPDATE ind_contribuyentes SET ind_FechaCese = '2026-01-15' WHERE ind_Id = ?", [$idC]);
$filas = InformePagos::filas($con, 'autorreteica', $filtro);
prueba('Informe rápido: con cese sale Inactivo', ($filas[0]['estadoContribuyente'] ?? '') === 'Inactivo', $filas);

InformeCatalogo::olvidar();
$ctx = ['usuario' => 1, 'admin' => true, 'personalizados' => true, 'contacto' => true, 'plantillas' => true,
        'fuentes' => ['ica' => true, 'reteica' => true, 'autorreteica' => true, 'contribuyentes' => true]];
$padron = function (array $estados) use ($con, $ctx, $documento) {
    $filtros = [['c' => 'documento', 'op' => 'in', 'v' => [$documento]]];
    if ($estados) { $filtros[] = ['c' => 'estadoContribuyente', 'op' => 'in', 'v' => $estados]; }
    $cfg = ['v' => 1, 'modo' => 'contribuyente', 'impuesto' => null, 'filtros' => $filtros,
            'columnas' => [['c' => 'documento'], ['c' => 'contribuyente'], ['c' => 'estadoContribuyente']], 'orden' => []];
    $q = InformeConstructor::armar(InformeConstructor::validar($con, $cfg, $ctx, 'estricto'));
    $r = [];
    InformeConstructor::recorrer($con, $q, function ($f) use (&$r) { $r[] = array_map('strval', array_values($f)); });
    return $r;
};
$total = count($padron([]));
$inact = $padron(['inactivo']);
$act = $padron(['activo']);
prueba('Informe general de contribuyentes: el filtro Inactivo lo trae', $total >= 1 && count($inact) >= 1, [$total, $inact]);
prueba('... y la columna dice Inactivo', $inact && in_array('Inactivo', $inact[0], true), $inact);
prueba('... y el filtro Activo ya no lo trae (activos + inactivos = todos)', count($act) + count($inact) === $total, [count($act), count($inact), $total]);
ejec('UPDATE ind_contribuyentes SET ind_FechaCese = NULL WHERE ind_Id = ?', [$idC]);
$act = $padron(['activo']);
prueba('sin el cese vuelve a salir como Activo', $act && in_array('Activo', $act[0], true), $act);

echo "\n" . $ok . ' de ' . ($ok + count($fallas)) . "\n";
if ($fallas) { echo "FALLAS:\n  - " . implode("\n  - ", $fallas) . "\n"; exit(1); }
