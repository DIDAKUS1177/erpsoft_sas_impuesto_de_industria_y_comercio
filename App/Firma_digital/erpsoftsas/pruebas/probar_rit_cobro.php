<?php
/**
 * Prueba del cobro de la inscripción del RIT (migración 055).
 *
 * Uso, DENTRO del contenedor web (necesita el driver de SQL Server):
 *
 *     docker exec -w /var/www/html/erpsoftsas erpsoftsas_web_completo php pruebas/probar_rit_cobro.php
 *
 * Cómo funciona: cada "petición" (la API de firmas, el controlador del cobro) corre en un
 * proceso PHP aparte con la sesión y el POST que se le indique, como en la web; así las clases
 * no chocan y el doble registro simultáneo es simultáneo de verdad. No sale ningún correo: a
 * los procesos hijos se les deshabilitan las funciones de red, y un código que pasa todas las
 * barreras termina en "Error al enviar el correo".
 *
 * Crea sus contribuyentes (documentos 990001 a 990099), sus cuentas (prueba_cobro_*) y un rol
 * temporal, y los borra al terminar; los parámetros RIT_* vuelven a como estaban. Nunca toca
 * el rol 2. Se NIEGA a correr si la base se llama como las de los municipios
 * (erpsofts_ind_comercio_*).
 *
 * Qué comprueba (las ocho pruebas del plan y sus bordes):
 *   1. Con RIT_VALOR_INSCRIPCION vacío (o con un valor que no se entiende) todo queda igual:
 *      la función 10 de la API de firmas no trae la clave "cobro", nada se bloquea, no se
 *      puede registrar nada, y el PDF y la huella del RIT ni siquiera conocen el cobro.
 *   2. Con valor y un contribuyente nuevo hay un cobro PENDIENTE; con el pago previo en 1, las
 *      funciones 1 (pedir código) y 9 (firmar) lo rechazan sin gastar el código.
 *   3. La Alcaldía registra el pago y entonces sí se firma; el valor queda como foto, en hora
 *      de Colombia, y no se crea ningún anexo del RIT.
 *   4. Con firma previa, declaración presentada o cese, o un id que no existe, no se cobra.
 *   5. Exonerar pide un motivo de 10 a 500 caracteres y queda escrito.
 *   6. Dos o más registros a la vez dejan uno solo (candado + índice único).
 *   7. El contribuyente no registra su propio pago; hace falta el permiso, "Gestionar a un
 *      contribuyente", la cabecera X-Requested-With y nombrar al contribuyente.
 *   8. El hash de un RIT firmado no cambia.
 * Además: fechas, referencia, banco, datos que no son texto, la tabla o los parámetros
 * ausentes, y los CHECK de la tabla.
 */

if (PHP_SAPI !== 'cli') { exit('Solo por línea de comandos.'); }

$raiz = dirname(__DIR__);                          // .../erpsoftsas
$_SERVER['DOCUMENT_ROOT'] = dirname($raiz);        // SERVER = DOCUMENT_ROOT/erpsoftsas

/* ======================================================================
   EL PROCESO HIJO: una petición
   ====================================================================== */
if (($argv[1] ?? '') === '--peticion') {
    // Sin red (a la par de disable_functions): que fallen limpio en vez de morir con un fatal.
    if (!function_exists('fsockopen'))            { function fsockopen()            { return false; } }
    if (!function_exists('pfsockopen'))           { function pfsockopen()           { return false; } }
    if (!function_exists('stream_socket_client')) { function stream_socket_client() { return false; } }

    $cfg = json_decode(stream_get_contents(STDIN), true);
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_HOST'] = 'localhost';
    // La guarda de auto-ejecución del controlador compara SCRIPT_FILENAME con su propio archivo.
    $_SERVER['SCRIPT_FILENAME'] = (string) ($cfg['destino'] ?? '');
    if (!empty($cfg['ajax'])) { $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest'; }
    session_save_path($cfg['sesiones']);
    @session_start();                              // ANTES de fijar $_SESSION
    $_SESSION = $cfg['sesion'] ?? [];
    $_POST = $cfg['post'] ?? [];
    $_REQUEST = $_POST;
    include_once $raiz . '/business/globals.php';
    ob_start();
    register_shutdown_function(function () { echo ob_get_clean(); });
    if (isset($cfg['codigo'])) {
        // Un trozo de código suelto: las clases que usa la prueba (los destinos reales cargan las suyas).
        include_once SERVER . '/business/class.conexionSqlServer.php';
        include_once SERVER . '/business/class.permisosRol.php';
        include_once SERVER . '/business/class.ritCobro.php';
        eval($cfg['codigo']);
    } else {
        include $cfg['destino'];
    }
    exit(0);
}

error_reporting(E_ALL);
include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.conexionSqlServer.php';
include_once SERVER . '/business/class.permisosRol.php';
include_once SERVER . '/business/class.ritFirma.php';
include_once SERVER . '/business/class.ritCobro.php';
ini_set('display_errors', '1');                    // globals.php lo apaga; en una prueba se quiere ver

use erpsoftsas\RitCobro;
use erpsoftsas\RitFirma;

/* ======================================================================
   Utilidades de la prueba
   ====================================================================== */
$ok = 0;
$fallas = [];
function prueba($nombre, $condicion, $detalle = '')
{
    global $ok, $fallas;
    if ($condicion) { $ok++; echo "  ok   $nombre\n"; return true; }
    $fallas[] = $nombre;
    $d = is_string($detalle) ? $detalle : json_encode($detalle, JSON_UNESCAPED_UNICODE);
    echo "  FALLA $nombre" . ($d !== '' ? '  -> ' . mb_substr($d, 0, 400) : '') . "\n";
    return false;
}
function seccion($titulo) { echo "\n== $titulo\n"; }

$con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
// Los avisos informativos de SQL Server (el de sp_rename, por ejemplo) no son errores.
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

$dirSesiones = sys_get_temp_dir() . '/sesiones_prueba_rit_cobro';
@mkdir($dirSesiones, 0777, true);

/** Hoy en Colombia (UTC-5 todo el año), independiente de la zona del proceso. */
function hoy($dias = 0) { return gmdate('Y-m-d', time() - 5 * 3600 + $dias * 86400); }

/* ---------- peticiones en procesos aparte ---------- */
function abrir_proceso(array $cfg)
{
    global $dirSesiones, $raiz;
    $cfg['sesiones'] = $dirSesiones;
    $destinos = [
        'api'   => $raiz . '/microservicios/firmas/api.php',
        'cobro' => $raiz . '/business/controller/class.ritCobro.php',
    ];
    if (isset($cfg['destino'])) { $cfg['destino'] = $destinos[$cfg['destino']] ?? $cfg['destino']; }
    $cmd = [PHP_BINARY, '-d', 'disable_functions=stream_socket_client,fsockopen,pfsockopen', __FILE__, '--peticion'];
    $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $raiz);
    fwrite($pipes[0], json_encode($cfg));
    fclose($pipes[0]);
    return [$proc, $pipes];
}
function cerrar_proceso(array $h)
{
    [$proc, $pipes] = $h;
    $salida = stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    proc_close($proc);
    $j = json_decode($salida, true);
    return $j === null ? ['__crudo__' => $salida] : $j;
}
function pedir(array $cfg) { return cerrar_proceso(abrir_proceso($cfg)); }
function juntas(array $cfgs)
{
    $hs = [];
    foreach ($cfgs as $cfg) { $hs[] = abrir_proceso($cfg); }      // todas arrancan antes de leer ninguna
    return array_map('cerrar_proceso', $hs);
}
function api($ses, array $post) { return pedir(['destino' => 'api', 'sesion' => $ses, 'post' => $post, 'ajax' => true]); }
function fn10($ses, $cid = null) { return api($ses, ['funcion' => 10] + ($cid === null ? [] : ['id_contribuyente' => $cid])); }
function fn1($ses, $cid = null) { return api($ses, ['funcion' => 1, 'rol' => 'rit', 'id_establecimiento' => 0] + ($cid === null ? [] : ['id_contribuyente' => $cid])); }
function fn9($ses, $codigo, $cid = null) { return api($ses, ['funcion' => 9, 'codigo' => $codigo] + ($cid === null ? [] : ['id_contribuyente' => $cid])); }
function cobro($ses, $funcion, array $post = [], $ajax = true)
{
    return pedir(['destino' => 'cobro', 'sesion' => $ses, 'post' => ['funcion' => $funcion] + $post, 'ajax' => $ajax]);
}
function pagar($ses, $cid, $ref = 'RC-2026-001', $fecha = null, $banco = '')
{
    return cobro($ses, 2, ['id_contribuyente' => $cid, 'fechaPago' => $fecha ?? hoy(), 'referencia' => $ref, 'idBanco' => $banco]);
}
function ejecutar_php($codigo, $ses = []) { return pedir(['destino' => 'eval', 'sesion' => $ses, 'post' => [], 'codigo' => $codigo]); }

/* ---------- parámetros y datos de prueba ---------- */
const ROL_PRUEBA = 'PRUEBA COBRO ROL';
const CLAVES_RIT = "('RIT_VALOR_INSCRIPCION', 'RIT_PAGO_INSTRUCCIONES', 'RIT_PAGO_PREVIO_A_FIRMAR')";
$originales = todas('SELECT par_Clave, par_Valor, par_Estado FROM conf_parametros WHERE par_Clave IN ' . CLAVES_RIT);
if (count($originales) !== 3) { exit("Faltan los parámetros de la migración 055: aplíquela (php BD/aplicar_migraciones.php --aplicar 055).\n"); }

function config($valor, $previo = '0', $instrucciones = null)
{
    foreach (['RIT_VALOR_INSCRIPCION' => $valor, 'RIT_PAGO_PREVIO_A_FIRMAR' => $previo, 'RIT_PAGO_INSTRUCCIONES' => $instrucciones] as $k => $v) {
        ejec('UPDATE conf_parametros SET par_Valor = ?, par_Estado = 1 WHERE par_Clave = ?', [$v, $k]);
    }
}

function limpiar()
{
    $ids = array_column(todas('SELECT ind_Id FROM ind_contribuyentes WHERE ind_NumeroIdentificacion BETWEEN 990001 AND 990099'), 'ind_Id');
    $uids = array_column(todas("SELECT usu_Id FROM conf_usuarios WHERE usu_Usuario LIKE 'prueba_cobro_%'"), 'usu_Id');
    if ($ids) {
        $l = implode(',', array_map('intval', $ids));
        ejec("DELETE FROM ind_rit_cobros WHERE rit_IdContribuyente IN ($l)");
        ejec("DELETE FROM ind_rit_firmas WHERE rif_IdContribuyente IN ($l)");
        ejec("DELETE FROM ind_establecimiento_anexos WHERE anx_IdContribuyente IN ($l)");
        ejec("DELETE FROM ind_autorreteica WHERE aut_IdContribuyente IN ($l)");
        ejec("DELETE FROM ind_contribuyentes WHERE ind_Id IN ($l)");
    }
    // Los códigos que el flujo de firma deja para quien opera (el administrador gestionando a un
    // contribuyente de prueba) llevan el correo del representante de prueba.
    ejec("DELETE FROM codigos_verificacion WHERE codigo_Email LIKE 'rep.cobro.%@pruebalocal.invalid'");
    if ($uids) {
        $l = implode(',', array_map('intval', $uids));
        ejec("DELETE FROM codigos_verificacion WHERE codigo_IdUsuario IN ($l)");
        ejec("DELETE FROM conf_usuarios WHERE usu_Id IN ($l)");
    }
    foreach (todas('SELECT rol_Id FROM conf_rol WHERE rol_Nombre = ?', [ROL_PRUEBA]) as $r) {
        ejec('DELETE FROM conf_permisos WHERE per_IdRol = ?', [$r['rol_Id']]);
        ejec('DELETE FROM conf_rol WHERE rol_Id = ?', [$r['rol_Id']]);
    }
}

/** Contribuyente n (documento 990000+n) con su RIT completo y su cuenta. Devuelve [id, idUsuario, sesión]. */
function nuevo($n, $completo = true, $rol = 4)
{
    $doc = 990000 + $n;
    ejec("INSERT INTO ind_contribuyentes (ind_NumeroIdentificacion, ind_DV, ind_IdTipoDocumento, ind_PrimerNombre, ind_PrimerApellido,
            ind_Direccion, ind_IdCiudad, ind_Persona, ind_Telefono, ind_Email, ind_Estado, ind_Fecha_inicio, ind_Cedula_representante,
            ind_Nombre_representante, ind_Email_representante, ind_Telefono_representante, ind_Rut, ind_Autorizacion, ind_RegimenTributario)
          VALUES (?, 0, 1, ?, 'RIT', 'Calle 1 N 2-3', 66, 1, 3001112233, ?, 1, '2026-08-24', '123456', 'REPRESENTANTE PRUEBA', ?,
                  '3001112233', '1254', 1, 'SIMPLE,NO_RESP_IVA')",
        [$doc, "PRUEBA COBRO $n", "prueba.cobro.$n@pruebalocal.invalid", "rep.cobro.$n@pruebalocal.invalid"]);
    $cid = (int) valor('SELECT ind_Id FROM ind_contribuyentes WHERE ind_NumeroIdentificacion = ?', [$doc]);
    if ($completo) {
        foreach (['rut', 'camara', 'cedula'] as $tipo) {
            ejec("INSERT INTO ind_establecimiento_anexos (anx_IdEstablecimiento, anx_Tipo, anx_NombreOriginal, anx_Ruta, anx_Extension, anx_Tamano,
                    anx_FechaCarga, anx_IdUsuario, anx_Activo, anx_IdContribuyente) VALUES (NULL, ?, 'prueba.pdf', 'prueba/prueba.pdf', 'pdf', 10, GETDATE(), 1, 1, ?)",
                [$tipo, $cid]);
        }
    }
    ejec("INSERT INTO conf_usuarios (usu_Nombres, usu_Apellidos, usu_Usuario, usu_IdTipoDocumento, usu_NumeroDocumento, usu_Correo, usu_Password, usu_Rol, usu_Estado)
          VALUES ('PRUEBA', ?, ?, 1, ?, ?, 'x', ?, 1)",
        ["COBRO $n", "prueba_cobro_$n", (string) $doc, "usuario.cobro.$n@pruebalocal.invalid", $rol]);
    $uid = (int) valor('SELECT usu_Id FROM conf_usuarios WHERE usu_Usuario = ?', ["prueba_cobro_$n"]);
    return [$cid, $uid, ['id_usuario' => $uid, 'id_Rol' => $rol]];
}

function filas_cobro($cid)
{
    return todas("SELECT rit_Id, rit_Estado, CAST(rit_Valor AS INT) AS valor, rit_Pagado, rit_Exonerado, rit_Referencia, rit_BancoPago, rit_IdBancoPago,
                         CONVERT(VARCHAR(10), rit_FechaPago, 120) AS fechaPago, CONVERT(VARCHAR(19), rit_FechaRealPago, 120) AS pagoReal,
                         CONVERT(VARCHAR(19), rit_FechaCreacion, 120) AS creada, rit_RutaPago, rit_AnioPago, CAST(rit_ValorPago AS INT) AS valorPago,
                         rit_IdUsuario, rit_MotivoExoneracion
                    FROM ind_rit_cobros WHERE rit_IdContribuyente = ? ORDER BY rit_Id", [$cid]);
}
function n_firmas($cid) { return (int) valor('SELECT COUNT(*) FROM ind_rit_firmas WHERE rif_IdContribuyente = ?', [$cid]); }
function n_anexos($cid) { return (int) valor('SELECT COUNT(*) FROM ind_establecimiento_anexos WHERE anx_IdContribuyente = ?', [$cid]); }
function n_codigos($uid) { return (int) valor('SELECT COUNT(*) FROM codigos_verificacion WHERE codigo_IdUsuario = ?', [$uid]); }
function poner_codigo($uid, $valor = '123456')
{
    ejec("INSERT INTO codigos_verificacion (codigo_Valor, codigo_IdUsuario, codigo_Email, codigo_IdEstablecimiento, codigo_FechaExpiracion, codigo_Rol)
          VALUES (?, ?, 'x@pruebalocal.invalid', 0, DATEADD(MINUTE, 10, GETDATE()), 'rit')", [$valor, $uid]);
    return $valor;
}
function huella($cid) { global $con; return [RitFirma::hashActual($con, $cid), RitFirma::firmaVigente($con, $cid)]; }

const SMTP_FALLA = 'Error al enviar el correo';    // lo que contesta la función 1 cuando PASA todas las barreras
const INSTR = 'Consigne en Bancolombia, cuenta de ahorros 123456789, y lleve el recibo a Tesorería.';
$ADMIN = ['id_usuario' => 1, 'id_Rol' => 1];

register_shutdown_function(function () use ($originales) {
    limpiar();
    foreach ($originales as $o) {
        ejec('UPDATE conf_parametros SET par_Valor = ?, par_Estado = ? WHERE par_Clave = ?', [$o['par_Valor'], $o['par_Estado'], $o['par_Clave']]);
    }
});
limpiar();
config(null, '0', null);

/* ======================================================================
   Los contribuyentes
   ====================================================================== */
[$A, $uA, $sA] = nuevo(1);     // pendiente: bloqueos
[$B, $uB, $sB] = nuevo(2);     // modo "solo informa": firma sin pagar
[$C, $uC, $sC] = nuevo(3);     // la Alcaldía registra y firma; huella
[$D, $uD, $sD] = nuevo(4);     // firma previa
[$E, $uE, $sE] = nuevo(5);     // declaración presentada
[$F, $uF, $sF] = nuevo(6);     // cese
[$G, $uG, $sG] = nuevo(7);     // exoneración
[$H, $uH, $sH] = nuevo(8);     // doble registro simultáneo
[$I, $uI, $sI] = nuevo(9);     // permisos
$todos = array_column(todas('SELECT ind_Id FROM ind_contribuyentes WHERE ind_NumeroIdentificacion NOT BETWEEN 990001 AND 990099 ORDER BY ind_Id'), 'ind_Id');

/* ======================================================================
   1. Valor vacío: todo igual que hoy
   ====================================================================== */
seccion('1. Con RIT_VALOR_INSCRIPCION vacío todo queda igual (Paipa)');
$ordenBase = ['ok', 'firmado', 'firma', 'desactualizada', 'opcionUso'];
$igual = true;
foreach (array_merge($todos, [$A]) as $cid) {
    $r = fn10($ADMIN, $cid);
    $igual = $igual && !array_key_exists('cobro', $r) && array_keys($r) === $ordenBase;
}
prueba('1a fn10 de todos los contribuyentes: sin clave "cobro" y con las mismas claves, en el mismo orden', $igual);
foreach (['0', '00', 'abc', '50.000', '-5', '1234567890', ' '] as $malo) {
    config($malo, '1', 'Consigne en el banco');
    prueba("1b valor '$malo' => gratis (sin clave cobro)", !array_key_exists('cobro', fn10($ADMIN, $A)));
}
config(null, '1', null);
prueba('1c PREVIO=1 con valor vacío: pedir el código NO se bloquea (llega al envío del correo)', strpos(fn1($sA, $A)['mensaje'] ?? '', SMTP_FALLA) !== false);
ejec('DELETE FROM codigos_verificacion WHERE codigo_IdUsuario = ?', [$uA]);
prueba('1d PREVIO=1 con valor vacío: firmar tampoco se bloquea por el cobro (rechaza por el código)', strpos(fn9($sA, '000000')['mensaje'] ?? '', 'Código inválido') !== false);
$r = pagar($ADMIN, $A);
prueba('1e con valor vacío la Alcaldía no puede registrar pagos', $r['ok'] == 0 && strpos($r['mensaje'], 'no cobra') !== false, $r);
$r = cobro($ADMIN, 3, ['id_contribuyente' => $A, 'motivo' => 'un motivo suficientemente largo']);
prueba('1f ni exonerar', $r['ok'] == 0 && strpos($r['mensaje'], 'no cobra') !== false, $r);
$r = cobro($ADMIN, 1, ['id_contribuyente' => $A]);
prueba('1g el controlador (consultar) contesta ok y cobro null', $r['ok'] == 1 && $r['datos']['cobro'] === null, $r);
prueba('1h no se creó ninguna fila en ind_rit_cobros', count(filas_cobro($A)) === 0);
$ritPdf = file_get_contents($raiz . '/extensiones/ritActualizado.php');
$ritHuella = file_get_contents($raiz . '/business/class.ritFirma.php');
prueba('1i el PDF del RIT y la huella (RitFirma) no mencionan el cobro: no pueden cambiar', stripos($ritPdf, 'ritcobro') === false && stripos($ritHuella, 'ritcobro') === false);
// Con un valor puesto, a quien NO le corresponde cobro (firmó antes) la respuesta es idéntica byte a byte.
$firmado = null;
foreach ($todos as $cid) { if (n_firmas($cid) > 0) { $firmado = $cid; break; } }
if ($firmado !== null) {
    config(null, '0', null);
    $sinCobro = pedir(['destino' => 'api', 'sesion' => $ADMIN, 'post' => ['funcion' => 10, 'id_contribuyente' => $firmado], 'ajax' => true]);
    config('25000', '1', INSTR);
    $conCobro = pedir(['destino' => 'api', 'sesion' => $ADMIN, 'post' => ['funcion' => 10, 'id_contribuyente' => $firmado], 'ajax' => true]);
    prueba('1j con el cobro activo, un contribuyente que ya firmó recibe la MISMA respuesta que sin cobro', $sinCobro === $conCobro && !array_key_exists('cobro', $conCobro), [$sinCobro, $conCobro]);
}

/* ======================================================================
   2. Pendiente y bloqueo
   ====================================================================== */
seccion('2. Con valor y contribuyente nuevo: pendiente; con PREVIO=1 pedir código y firmar se rechazan');
config('25000', '1', INSTR);
$r = fn10($ADMIN, $A);
$c = $r['cobro'] ?? [];
prueba('2a fn10 (Alcaldía): PENDIENTE, $25000, bloquea y puede registrar',
    ($c['estado'] ?? '') === 'PENDIENTE' && ($c['valor'] ?? 0) === 25000 && ($c['bloquea'] ?? 0) === 1 && ($c['puedeRegistrar'] ?? 0) === 1, $r);
prueba('2b la clave cobro va AL FINAL y las demás no cambian', array_slice(array_keys($r), 0, 5) === $ordenBase && array_key_last($r) === 'cobro', array_keys($r));
prueba('2c las instrucciones viajan', ($c['instrucciones'] ?? '') === INSTR, $c);
$c = fn10($sA)['cobro'] ?? [];
prueba('2d fn10 (el propio contribuyente): PENDIENTE y NO puede registrar', ($c['estado'] ?? '') === 'PENDIENTE' && ($c['puedeRegistrar'] ?? 1) === 0, $c);
$antes = n_codigos($uA);
$r = fn1($sA);
prueba('2e pedir el código se RECHAZA con el aviso del cobro y cómo pagar',
    $r['ok'] == 0 && strpos($r['mensaje'], 'derechos de inscripción') !== false && strpos($r['mensaje'], '$25.000') !== false && strpos($r['mensaje'], 'Bancolombia') !== false, $r);
prueba('2f ...y no guardó ningún código (no sale correo)', n_codigos($uA) === $antes);
poner_codigo($uA, '123456');
$r = fn9($sA, '123456');
prueba('2g firmar se RECHAZA con el aviso aunque el código sea bueno', $r['ok'] == 0 && strpos($r['mensaje'], 'derechos de inscripción') !== false, $r);
prueba('2h el código NO se gastó y no hay firma', valor("SELECT codigo_Usado FROM codigos_verificacion WHERE codigo_IdUsuario = ? AND codigo_Valor = '123456'", [$uA]) == 0 && n_firmas($A) === 0);
prueba('2i la Alcaldía tampoco firma por él', strpos(fn1($ADMIN, $A)['mensaje'] ?? '', 'derechos de inscripción') !== false);
[$J, $uJ, $sJ] = nuevo(10, false);
$r = fn1($sJ);
prueba('2j un RIT incompleto dice primero lo que falta', $r['ok'] == 0 && strpos($r['mensaje'], 'Falta') !== false, $r);
foreach (['declarante', 'contador'] as $rol) {
    $r = api($sA, ['funcion' => 1, 'rol' => $rol, 'id_establecimiento' => 0, 'numero_declaracion' => 987654321]);
    prueba("2k pedir código con rol $rol no lo frena el cobro del RIT", strpos($r['mensaje'] ?? '', 'derechos de inscripción') === false, $r);
}
// Modo "solo informa": se puede firmar sin pagar
config('25000', '0', INSTR);
$c = fn10($sB)['cobro'] ?? [];
prueba('2l PREVIO=0: PENDIENTE y bloquea=0', ($c['estado'] ?? '') === 'PENDIENTE' && ($c['bloquea'] ?? 1) === 0, $c);
prueba('2m PREVIO=0: pedir el código pasa todas las barreras', strpos(fn1($sB)['mensaje'] ?? '', SMTP_FALLA) !== false);
$cod = valor("SELECT TOP 1 codigo_Valor FROM codigos_verificacion WHERE codigo_IdUsuario = ? AND codigo_Rol = 'rit' AND codigo_Usado = 0 ORDER BY codigo_Id DESC", [$uB]);
$r = $cod ? fn9($sB, $cod) : [];
prueba('2n PREVIO=0: se firma sin pagar', ($r['ok'] ?? 0) == 1, $r);
prueba('2o ...y firmado deja de figurar con cobro (el pendiente se deduce, no se guarda)', !array_key_exists('cobro', fn10($ADMIN, $B)));

/* ======================================================================
   3. La Alcaldía registra el pago y se puede firmar
   ====================================================================== */
seccion('3. La Alcaldía registra el pago y entonces se firma; el valor queda como foto');
config('25000', '1', INSTR);
prueba('3a antes de registrar, pedir el código está bloqueado', strpos(fn1($sC)['mensaje'] ?? '', 'derechos de inscripción') !== false);
$anexos0 = n_anexos($C);
$r = pagar($ADMIN, $C, 'RC-2026-001');
prueba('3b registrar el pago: ok', $r['ok'] == 1, $r);
$f = filas_cobro($C);
prueba('3c una sola fila', count($f) === 1, $f);
$f = $f[0] ?? [];
prueba('3d vigente (2), $25000, pagado, no exonerado, con su referencia y vía MANUAL',
    ($f['rit_Estado'] ?? 0) == 2 && ($f['valor'] ?? 0) == 25000 && ($f['rit_Pagado'] ?? 0) == 1 && ($f['rit_Exonerado'] ?? 1) == 0
    && ($f['rit_Referencia'] ?? '') === 'RC-2026-001' && ($f['rit_RutaPago'] ?? '') === 'MANUAL', $f);
prueba('3e fecha de pago = hoy de Colombia y año del pago', ($f['fechaPago'] ?? '') === hoy() && ($f['rit_AnioPago'] ?? 0) == (int) hoy(), $f);
$colombia = time() - 5 * 3600;
prueba('3f FechaRealPago y FechaCreacion en HORA DE COLOMBIA (no la UTC del servidor SQL)',
    abs(strtotime(($f['pagoReal'] ?? '') . ' UTC') - $colombia) < 180 && abs(strtotime(($f['creada'] ?? '') . ' UTC') - $colombia) < 180, $f);
prueba('3g ValorPago = valor y quedó quien lo registró (administrador)', ($f['valorPago'] ?? 0) == 25000 && ($f['rit_IdUsuario'] ?? 0) == 1, $f);
$c = fn10($ADMIN, $C)['cobro'] ?? [];
prueba('3h fn10: PAGADO con los datos del pago y quién lo registró',
    ($c['estado'] ?? '') === 'PAGADO' && ($c['valor'] ?? 0) === 25000 && ($c['bloquea'] ?? 1) === 0 && ($c['pago']['referencia'] ?? '') === 'RC-2026-001'
    && ($c['pago']['registradoPor'] ?? '') !== '' && !isset($c['puedeRegistrar']) && !isset($c['instrucciones']), $c);
prueba('3i ahora pedir el código pasa', strpos(fn1($sC)['mensaje'] ?? '', SMTP_FALLA) !== false);
$cod = valor("SELECT TOP 1 codigo_Valor FROM codigos_verificacion WHERE codigo_IdUsuario = ? AND codigo_Rol = 'rit' AND codigo_Usado = 0 ORDER BY codigo_Id DESC", [$uC]);
$r = $cod ? fn9($sC, $cod) : [];
prueba('3j firmar el RIT: ok, y queda UNA firma vigente', ($r['ok'] ?? 0) == 1 && n_firmas($C) === 1 && huella($C)[1]['firmado'] === true, $r);
[$hash1] = huella($C);
prueba('3k pagar NO creó anexos del RIT (el soporte nunca va como anexo: cambia la huella)', n_anexos($C) === $anexos0, [$anexos0, n_anexos($C)]);
config('99999', '1', 'Otro texto');
$c = fn10($ADMIN, $C)['cobro'] ?? [];
prueba('3l foto: cambiar el parámetro NO cambia lo ya registrado ($25000)', ($c['estado'] ?? '') === 'PAGADO' && ($c['valor'] ?? 0) === 25000, $c);
prueba('3m ...y los pendientes sí siguen el valor de hoy', (fn10($ADMIN, $A)['cobro']['valor'] ?? 0) === 99999);
config('25000', '1', INSTR);

/* ======================================================================
   4. Firma previa, declaración o cese: no se cobra
   ====================================================================== */
seccion('4. Con firma previa, declaración presentada o cese no se cobra');
ejec("INSERT INTO ind_rit_firmas (rif_IdContribuyente, rif_IdUsuario, rif_NombreUsuario, rif_EmailUsuario, rif_Hash) VALUES (?, 1, 'prueba', 'x@pruebalocal.invalid', ?)", [$D, str_repeat('a', 64)]);
prueba('4a con firma previa (aunque esté vencida) no hay cobro', !array_key_exists('cobro', fn10($ADMIN, $D)));
$r = pagar($ADMIN, $D, 'Z1');
prueba('4b ...y la Alcaldía no puede registrarle un pago', $r['ok'] == 0, $r);
ejec('INSERT INTO ind_autorreteica (aut_IdContribuyente, aut_Anio, aut_Periodo, aut_Estado) VALUES (?, 2026, 1, 2)', [$E]);
$r = fn10($ADMIN, $E);
prueba('4c con declaración presentada no hay cobro (y la opción de uso es Actualización)', !array_key_exists('cobro', $r) && $r['opcionUso'] === 'Actualización', $r);
$r = cobro($ADMIN, 3, ['id_contribuyente' => $E, 'motivo' => 'motivo suficientemente largo']);
prueba('4d ...ni exonerarlo', $r['ok'] == 0 && strpos($r['mensaje'], 'no tiene una inscripción pendiente') !== false, $r);
ejec("UPDATE ind_contribuyentes SET ind_FechaCese = '2026-01-15' WHERE ind_Id = ?", [$F]);
$r = fn10($ADMIN, $F);
prueba('4e en cese no hay cobro', !array_key_exists('cobro', $r) && $r['opcionUso'] === 'Cese', $r);
prueba('4f un id que no existe no figura como inscripción pendiente', !array_key_exists('cobro', fn10($ADMIN, 987654)));
prueba('4g ...ni se le registra un pago', pagar($ADMIN, 987654, 'Z2')['ok'] == 0);

/* ======================================================================
   5. Exoneración con motivo
   ====================================================================== */
seccion('5. Exoneración con motivo');
foreach ([['corto', 'motivo corto'], [str_repeat('x', 501), 'más de 500'], ['', 'sin motivo'], [['un motivo suficientemente largo'], 'como arreglo']] as [$motivo, $que]) {
    $r = cobro($ADMIN, 3, ['id_contribuyente' => $G, 'motivo' => $motivo]);
    prueba("5a $que: rechazado", $r['ok'] == 0 && strpos($r['mensaje'], 'entre 10 y 500') !== false, $r);
}
prueba('5b nada se guardó', count(filas_cobro($G)) === 0);
$r = cobro($ADMIN, 3, ['id_contribuyente' => $G, 'motivo' => "Entidad sin ánimo de lucro, según acuerdo municipal\n<b>x</b>"]);
prueba('5c exonerar con motivo: ok', $r['ok'] == 1, $r);
$f = filas_cobro($G)[0] ?? [];
prueba('5d fila exonerada: vigente, no pagada, $25000, motivo guardado sin saltos de línea',
    ($f['rit_Estado'] ?? 0) == 2 && ($f['rit_Pagado'] ?? 1) == 0 && ($f['rit_Exonerado'] ?? 0) == 1 && ($f['valor'] ?? 0) == 25000
    && strpos($f['rit_MotivoExoneracion'] ?? '', 'ánimo de lucro') !== false && strpos($f['rit_MotivoExoneracion'] ?? '', "\n") === false, $f);
$c = fn10($ADMIN, $G)['cobro'] ?? [];
prueba('5e fn10: EXONERADO con motivo, quién y cuándo',
    ($c['estado'] ?? '') === 'EXONERADO' && ($c['bloquea'] ?? 1) === 0 && strpos($c['exoneracion']['motivo'] ?? '', 'ánimo de lucro') !== false
    && ($c['exoneracion']['registradoPor'] ?? '') !== '' && ($c['exoneracion']['registradoEl'] ?? '') !== '', $c);
prueba('5f exonerado: con PREVIO=1 ya no lo bloquea', strpos(fn1($sG)['mensaje'] ?? '', SMTP_FALLA) !== false);
$r = cobro($ADMIN, 3, ['id_contribuyente' => $G, 'motivo' => 'otra exoneración distinta aquí']);
prueba('5g exonerar dos veces: rechazado y una sola fila', $r['ok'] == 0 && count(filas_cobro($G)) === 1, $r);
prueba('5h registrar el pago de un exonerado: rechazado', pagar($ADMIN, $G)['ok'] == 0);
prueba('5i registrar el pago de uno ya pagado: rechazado', pagar($ADMIN, $C, 'RC-OTRA')['ok'] == 0);

/* ======================================================================
   6. Registros simultáneos
   ====================================================================== */
seccion('6. Doble registro simultáneo');
$rs = juntas(array_map(function ($i) use ($ADMIN, $H) {
    return ['destino' => 'cobro', 'sesion' => $ADMIN, 'ajax' => true,
            'post' => ['funcion' => 2, 'id_contribuyente' => $H, 'fechaPago' => hoy(), 'referencia' => "RC-PAR-$i", 'idBanco' => '']];
}, range(1, 4)));
$oks = array_filter($rs, function ($x) { return ($x['ok'] ?? 0) == 1; });
prueba('6a cuatro registros a la vez: exactamente UNO ok', count($oks) === 1, $rs);
prueba('6b ...los otros dicen que ya figura registrado',
    count(array_filter($rs, function ($x) { return ($x['ok'] ?? 0) != 1 && preg_match('/ya figura|ya fue registrada/', $x['mensaje'] ?? ''); })) === 3, $rs);
prueba('6c una sola fila vigente en la base', count(filas_cobro($H)) === 1);
ejec('DELETE FROM ind_rit_cobros WHERE rit_IdContribuyente = ?', [$H]);
$rs = juntas([
    ['destino' => 'cobro', 'sesion' => $ADMIN, 'ajax' => true, 'post' => ['funcion' => 2, 'id_contribuyente' => $H, 'fechaPago' => hoy(), 'referencia' => 'RC-MIX', 'idBanco' => '']],
    ['destino' => 'cobro', 'sesion' => $ADMIN, 'ajax' => true, 'post' => ['funcion' => 3, 'id_contribuyente' => $H, 'motivo' => 'exoneración en carrera con el pago']],
    ['destino' => 'cobro', 'sesion' => $ADMIN, 'ajax' => true, 'post' => ['funcion' => 2, 'id_contribuyente' => $H, 'fechaPago' => hoy(), 'referencia' => 'RC-MIX2', 'idBanco' => '']],
    ['destino' => 'cobro', 'sesion' => $ADMIN, 'ajax' => true, 'post' => ['funcion' => 3, 'id_contribuyente' => $H, 'motivo' => 'otra exoneración en carrera ya']],
]);
prueba('6d pagos y exoneraciones a la vez: exactamente UNO ok y una fila',
    count(array_filter($rs, function ($x) { return ($x['ok'] ?? 0) == 1; })) === 1 && count(filas_cobro($H)) === 1, [$rs, filas_cobro($H)]);
$msg = '';
try {
    ejec("INSERT INTO ind_rit_cobros (rit_IdContribuyente, rit_Valor, rit_Pagado, rit_FechaPago, rit_ValorPago, rit_IdUsuario, rit_FechaCreacion)
          VALUES (?, 25000, 1, '2026-11-09', 25000, 1, '2026-11-09T10:00:00')", [$H]);
} catch (\Throwable $e) { $msg = $e->getMessage(); }
prueba('6e el índice único impide un segundo registro vigente aunque se salte la aplicación', strpos($msg, 'UX_rit_cobros_vigente') !== false, $msg);
ejec('UPDATE ind_rit_cobros SET rit_Estado = 9 WHERE rit_IdContribuyente = ?', [$H]);
prueba('6f con el anterior anulado (estado 9) se puede registrar de nuevo', pagar($ADMIN, $H, 'RC-TRAS-ANULAR')['ok'] == 1);
// El candado de verdad (el índice único deja un solo registro aunque no hubiera candado; este es el que
// serializa): la prueba retiene el candado de un contribuyente y el registro tiene que ESPERAR.
[$HH] = nuevo(19);
$con->begin();
$ret = uno("SET NOCOUNT ON; DECLARE @r INT; EXEC @r = sp_getapplock @Resource = ?, @LockMode = 'Exclusive', @LockOwner = 'Transaction', @LockTimeout = 0; SELECT @r AS r;",
    ['erp_ritcobro_' . $HH]);
prueba('6g (preparación) el candado del contribuyente quedó en poder de la prueba', (int) ($ret['r'] ?? -9) >= 0, $ret);
$espera = abrir_proceso(['destino' => 'cobro', 'sesion' => $ADMIN, 'ajax' => true,
                         'post' => ['funcion' => 2, 'id_contribuyente' => $HH, 'fechaPago' => hoy(), 'referencia' => 'RC-ESPERA', 'idBanco' => '']]);
usleep(2500000);
$vivo = proc_get_status($espera[0])['running'];
prueba('6h con el candado ocupado el registro ESPERA: sigue en curso y no escribió nada', $vivo === true && count(filas_cobro($HH)) === 0);
$con->rollback();     // suelta el candado
$r = cerrar_proceso($espera);
prueba('6i al soltarse el candado el registro termina bien y deja una fila', ($r['ok'] ?? 0) == 1 && count(filas_cobro($HH)) === 1, $r);

/* ======================================================================
   7. Quién puede registrar
   ====================================================================== */
seccion('7. El contribuyente no registra su propio pago; permisos y cabecera');
$r = cobro($sA, 2, ['id_contribuyente' => $A, 'fechaPago' => hoy(), 'referencia' => 'MIO-1']);
prueba('7a el contribuyente NO registra su propio pago', $r['ok'] == 0 && strpos($r['mensaje'], 'permiso') !== false, $r);
$r = cobro($sA, 3, ['id_contribuyente' => $A, 'motivo' => 'quiero quedar exonerado yo mismo']);
prueba('7b ...ni se exonera', $r['ok'] == 0 && strpos($r['mensaje'], 'permiso') !== false, $r);
$r = cobro($sA, 2, ['id_contribuyente' => $I, 'fechaPago' => hoy(), 'referencia' => 'AJENO-1']);
prueba('7c ...ni el de otro', $r['ok'] == 0 && strpos($r['mensaje'], 'permiso') !== false, $r);
prueba('7d no se creó ninguna fila', count(filas_cobro($A)) === 0 && count(filas_cobro($I)) === 0);
$r = cobro($sA, 1, ['id_contribuyente' => $I]);
prueba('7e consultar: el contribuyente ve SOLO el suyo (ignora el id ajeno), sin bancos ni botones',
    $r['ok'] == 1 && ($r['datos']['cobro']['estado'] ?? '') === 'PENDIENTE' && ($r['datos']['cobro']['puedeRegistrar'] ?? 1) === 0 && $r['datos']['bancos'] === [], $r);
prueba('7f la función 10 de la API de firmas también ignora el id ajeno', ((fn10($sA, $I)['cobro']['estado'] ?? '') === 'PENDIENTE'));
$r = cobro($ADMIN, 2, ['id_contribuyente' => $A, 'fechaPago' => hoy(), 'referencia' => 'SIN-AJAX'], false);
prueba('7g sin X-Requested-With: rechazado aun siendo administrador', $r['ok'] == 0 && strpos($r['mensaje'], 'Solicitud no válida') !== false, $r);
$r = cobro([], 2, ['id_contribuyente' => $A, 'fechaPago' => hoy(), 'referencia' => 'SIN-SESION']);
prueba('7h sin sesión: sinSesion', $r['ok'] == 0 && ($r['sinSesion'] ?? 0) == 1, $r);
prueba('7i función no válida (y como arreglo)', cobro($ADMIN, 9)['ok'] == 0 && cobro($ADMIN, ['1'])['ok'] == 0);
// Un rol de la Alcaldía (temporal) con permisos a medida
ejec("INSERT INTO conf_rol (rol_Nombre, rol_Descripcion, rol_Estado, rol_Fecha_Creacion, rol_Tipo) VALUES (?, 'solo para la prueba del cobro del RIT', 1, GETDATE(), 'ALCALDIA')", [ROL_PRUEBA]);
$ROL = (int) valor('SELECT rol_Id FROM conf_rol WHERE rol_Nombre = ?', [ROL_PRUEBA]);
function dar($rol, array $claves)
{
    $marcas = implode(',', array_fill(0, count($claves), '?'));
    ejec("INSERT INTO conf_permisos (per_IdSubmodulo, per_IdRol, per_IdModulo, per_IdBoton, per_Estado)
          SELECT subMod_Id, ?, subMod_IdModulo, 0, 1 FROM conf_submodulo WHERE subMod_Clave IN ($marcas)", array_merge([$rol], $claves));
}
[$K, $uK, $sK] = nuevo(11, false, $ROL);
prueba('7j rol de la Alcaldía SIN el permiso: rechazado', cobro($sK, 2, ['id_contribuyente' => $A, 'fechaPago' => hoy(), 'referencia' => 'R-SIN'])['ok'] == 0);
dar($ROL, ['alcaldia.rit.cobro']);
$r = cobro($sK, 2, ['id_contribuyente' => $A, 'fechaPago' => hoy(), 'referencia' => 'R-SOLO']);
prueba('7k con el permiso pero SIN "Gestionar a un contribuyente": rechazado', $r['ok'] == 0 && strpos($r['mensaje'], 'permiso') !== false, $r);
dar($ROL, ['alcaldia.contribuyentes.ver', 'alcaldia.contribuyentes.gestionar', 'rit.ver']);
prueba('7l con permiso + gestionar: fn10 le muestra el botón (puedeRegistrar=1)', ((fn10($sK, $I)['cobro']['puedeRegistrar'] ?? 0) === 1));
$r = cobro($sK, 2, ['id_contribuyente' => $I, 'fechaPago' => hoy(), 'referencia' => 'R-OK', 'idBanco' => '']);
prueba('7m con ambos permisos: registra, y queda su usuario como quien registró', $r['ok'] == 1 && (filas_cobro($I)[0]['rit_IdUsuario'] ?? 0) == $uK, $r);
foreach ([['sin id_contribuyente', []], ['id vacío', ['id_contribuyente' => '']], ['id 0', ['id_contribuyente' => 0]], ['id basura', ['id_contribuyente' => 'abc']],
          ['id negativo', ['id_contribuyente' => -3]], ['id como arreglo', ['id_contribuyente' => ['5']]]] as [$que, $extra]) {
    $r = cobro($sK, 2, $extra + ['fechaPago' => hoy(), 'referencia' => 'SIN-ID']);
    prueba("7n registrar $que: rechazado (no hay \"el propio\" de respaldo)", $r['ok'] == 0 && strpos($r['mensaje'], 'No se indicó el contribuyente') !== false, $r);
    $r = cobro($sK, 3, $extra + ['motivo' => 'un motivo suficientemente largo']);
    prueba("7o exonerar $que: rechazado", $r['ok'] == 0 && strpos($r['mensaje'], 'No se indicó el contribuyente') !== false, $r);
}
prueba('7p ...y el contribuyente propio del funcionario no recibió nada', count(filas_cobro($K)) === 0);
ejec("UPDATE conf_rol SET rol_Tipo = 'CONTRIBUYENTE' WHERE rol_Id = ?", [$ROL]);
$r = cobro($sK, 2, ['id_contribuyente' => $A, 'fechaPago' => hoy(), 'referencia' => 'R4']);
prueba('7q un rol de CONTRIBUYENTE con el permiso marcado tampoco registra', $r['ok'] == 0 && strpos($r['mensaje'], 'permiso') !== false, $r);
ejec("UPDATE conf_rol SET rol_Tipo = 'ALCALDIA' WHERE rol_Id = ?", [$ROL]);
ejec('UPDATE conf_usuarios SET usu_Estado = 0 WHERE usu_Id = ?', [$uK]);
$r = cobro($sK, 2, ['id_contribuyente' => $A, 'fechaPago' => hoy(), 'referencia' => 'R5']);
prueba('7r una cuenta inactivada no registra y se le avisa que su sesión terminó', $r['ok'] == 0 && ($r['sinSesion'] ?? 0) == 1, $r);
ejec('UPDATE conf_usuarios SET usu_Estado = 1 WHERE usu_Id = ?', [$uK]);
$req = ejecutar_php('echo json_encode(\erpsoftsas\PermisosRol::completarRequisitos(["alcaldia.rit.cobro"]));');
prueba('7s el permiso arrastra sus requisitos (gestionar, ver contribuyentes y ver el RIT)',
    is_array($req) && !array_diff(['alcaldia.rit.cobro', 'alcaldia.contribuyentes.gestionar', 'alcaldia.contribuyentes.ver', 'rit.ver'], $req), $req);
$r = ejecutar_php('echo json_encode(in_array("alcaldia.rit.cobro", \erpsoftsas\PermisosRol::paraPantalla()["claves"], true));', $ADMIN);
prueba('7t el administrador recibe el permiso en la pantalla', $r === true, $r);
ejec('DELETE FROM conf_permisos WHERE per_IdRol = ?', [$ROL]);

/* ======================================================================
   8. La huella del RIT firmado no cambia
   ====================================================================== */
seccion('8. El hash de un RIT firmado no cambia');
config('77777', '0', 'Instrucciones nuevas');
fn10($ADMIN, $C); cobro($ADMIN, 1, ['id_contribuyente' => $C]); fn10($sC);
config(null, '0', null);
fn10($ADMIN, $C);
[$hash2, $estado2] = huella($C);
prueba('8a el hash recalculado es el mismo que el firmado, antes y después de tocar parámetros y consultar', $hash2 === $hash1 && $estado2['firmado'] === true, [$hash1, $hash2]);
prueba('8b la firma sigue vigente y es una sola', n_firmas($C) === 1);
config('25000', '1', INSTR);

/* ======================================================================
   9. Bordes
   ====================================================================== */
seccion('9. Fecha, referencia, banco y datos que no son texto');
[$L] = nuevo(12);
$r = pagar($ADMIN, $L, 'RC-F', hoy(1));
prueba('9a fecha futura (mañana en Colombia): rechazada', $r['ok'] == 0 && strpos($r['mensaje'], 'futura') !== false, $r);
prueba('9b fecha anterior al año 2000: rechazada', pagar($ADMIN, $L, 'RC-F', '1999-12-31')['ok'] == 0);
foreach (['2026-02-30', 'hoy', '', '09/11/2026', '2026-13-01'] as $mala) {
    $r = pagar($ADMIN, $L, 'RC-F', $mala);
    prueba("9c fecha inválida '$mala': rechazada", $r['ok'] == 0 && stripos($r['mensaje'], 'fecha') !== false, $r);
}
foreach (['', '   ', '<script>alert(1)</script>', '=cmd|calc', '+573001112233', '@cmd', '-x', 'a;b', "a'b", 'a"b', "a\nb", "a\tb", '日本語', '𝐀BC',
          str_repeat('a', 61), "x'; DROP TABLE ind_rit_cobros;--", '.punto', '(paréntesis'] as $mala) {
    $r = pagar($ADMIN, $L, $mala);
    prueba('9d referencia ' . json_encode(mb_substr($mala, 0, 22), JSON_UNESCAPED_UNICODE) . ': rechazada', $r['ok'] == 0 && strpos($r['mensaje'], 'recibo') !== false, $r);
}
foreach (['referencia' => ['x'], 'fechaPago' => [hoy()], 'idBanco' => ['5']] as $campo => $arreglo) {
    $post = ['id_contribuyente' => $L, 'fechaPago' => hoy(), 'referencia' => 'RC-A', 'idBanco' => ''];
    $post[$campo] = $arreglo;
    prueba("9e $campo como arreglo: rechazado", cobro($ADMIN, 2, $post)['ok'] == 0);
}
foreach (['abc', '99999999', '-1', '1.5', '0'] as $malo) {
    $r = pagar($ADMIN, $L, 'RC-B', null, $malo);
    prueba("9f banco '$malo' inexistente o inválido: rechazado", $r['ok'] == 0 && stripos($r['mensaje'], 'banco') !== false, $r);
}
prueba('9g ninguna de esas intentonas dejó fila', count(filas_cobro($L)) === 0);
$banco = uno("SELECT TOP 1 ban_Id, ban_Nombre FROM ind_bancos WHERE ISNULL(ban_Activo, 1) = 1 AND ban_Codigo NOT IN ('00', '999') ORDER BY ban_Id");
$bancos = cobro($ADMIN, 1, ['id_contribuyente' => $L])['datos']['bancos'] ?? [];
prueba('9h consultar le trae a la Alcaldía la lista de bancos para el formulario', count($bancos) > 5 && !array_diff(['id', 'nombre', 'cuenta'], array_keys($bancos[0])), count($bancos));
$r = pagar($ADMIN, $L, '  Consignación 889 - Nº 12/3 (dup)  ', null, (string) $banco['ban_Id']);
prueba('9i pago con banco activo y una referencia normal (tildes, Nº, barra, paréntesis): ok', $r['ok'] == 1, $r);
$f = filas_cobro($L)[0] ?? [];
prueba('9j guarda el banco (id y nombre) y la referencia recortada y con tildes',
    ($f['rit_IdBancoPago'] ?? 0) == $banco['ban_Id'] && ($f['rit_BancoPago'] ?? '') === mb_substr(trim($banco['ban_Nombre']), 0, 60) && ($f['rit_Referencia'] ?? '') === 'Consignación 889 - Nº 12/3 (dup)', $f);
[$M] = nuevo(13);
$activo0 = valor('SELECT ban_Activo FROM ind_bancos WHERE ban_Id = ?', [$banco['ban_Id']]);
ejec('UPDATE ind_bancos SET ban_Activo = 0 WHERE ban_Id = ?', [$banco['ban_Id']]);
try { $r = pagar($ADMIN, $M, 'RC-INACT', null, (string) $banco['ban_Id']); }
finally { ejec('UPDATE ind_bancos SET ban_Activo = ? WHERE ban_Id = ?', [$activo0, $banco['ban_Id']]); }
prueba('9k un banco INACTIVO se rechaza', $r['ok'] == 0 && stripos($r['mensaje'], 'banco') !== false, $r);
prueba('9l ...y el banco quedó como estaba', valor('SELECT ban_Activo FROM ind_bancos WHERE ban_Id = ?', [$banco['ban_Id']]) == $activo0);

seccion('10. Textos con signos, instrucciones y motivo');
config('25000', '0', "Pague en <b>caja</b> & lleve <script>alert(1)</script>");
$ins = fn10($ADMIN, $M)['cobro']['instrucciones'] ?? '';
prueba('10a instrucciones con < > llegan sin esos signos', strpos($ins, '<') === false && strpos($ins, '>') === false && strpos($ins, 'caja') !== false, $ins);
config('25000', '0', str_repeat('a', 500));
prueba('10b instrucciones de 500 caracteres (el tope) llegan completas', strlen(fn10($ADMIN, $M)['cobro']['instrucciones'] ?? '') === 500);
config('25000', '1', INSTR);
$r = cobro($ADMIN, 3, ['id_contribuyente' => $M, 'motivo' => 'Motivo con "comillas" y \'apóstrofes\' <i>y etiquetas</i> & ampersand']);
prueba('10c el motivo con comillas, apóstrofes y etiquetas se guarda tal cual (se escapa al IMPRIMIR)', $r['ok'] == 1, $r);
$mt = fn10($ADMIN, $M)['cobro']['exoneracion']['motivo'] ?? '';
prueba('10d ...y vuelve en JSON sin tocar', strpos($mt, '<i>y etiquetas</i>') !== false && strpos($mt, '"comillas"') !== false, $mt);

/* ======================================================================
   11. La tabla o los parámetros ausentes (se despliega el código antes que la migración)
   ====================================================================== */
seccion('11. Tabla o parámetros ausentes: el código aguanta');
[$N, $uN, $sN] = nuevo(14);
config('25000', '1', INSTR);
ejec("EXEC sp_rename 'dbo.ind_rit_cobros', 'ind_rit_cobros_oculta'");
try {
    $r = fn10($ADMIN, $N);
    prueba('11a sin la tabla: fn10 ok y sin cobro', ($r['ok'] ?? 0) == 1 && !array_key_exists('cobro', $r), $r);
    prueba('11b sin la tabla: pedir el código no se bloquea', strpos(fn1($sN)['mensaje'] ?? '', SMTP_FALLA) !== false);
    ejec('DELETE FROM codigos_verificacion WHERE codigo_IdUsuario = ?', [$uN]);
    prueba('11c sin la tabla: firmar no se bloquea (rechaza por el código)', strpos(fn9($sN, '000000')['mensaje'] ?? '', 'Código inválido') !== false);
    $r = pagar($ADMIN, $N, 'SIN-TABLA');
    prueba('11d sin la tabla: el controlador dice que falta la migración 055', $r['ok'] == 0 && strpos($r['mensaje'], '055') !== false, $r);
    $r = cobro($ADMIN, 1, ['id_contribuyente' => $N]);
    prueba('11e sin la tabla: consultar contesta ok y cobro null', ($r['ok'] ?? 0) == 1 && $r['datos']['cobro'] === null, $r);
} finally {
    ejec("EXEC sp_rename 'dbo.ind_rit_cobros_oculta', 'ind_rit_cobros'");
}
prueba('11f la tabla volvió a su nombre', valor("SELECT OBJECT_ID('dbo.ind_rit_cobros', 'U')") !== null);
ejec('UPDATE conf_parametros SET par_Estado = 0 WHERE par_Clave IN ' . CLAVES_RIT);
try {
    $r = fn10($ADMIN, $N);
    prueba('11g con los parámetros inactivos (equivale a ausentes): sin cobro y sin bloqueo', !array_key_exists('cobro', $r) && strpos(fn1($sN)['mensaje'] ?? '', SMTP_FALLA) !== false, $r);
} finally {
    ejec('UPDATE conf_parametros SET par_Estado = 1 WHERE par_Clave IN ' . CLAVES_RIT);
}

seccion('12. Restricciones de la tabla (CHECK y llave foránea)');
$pares = [
    ['pagado Y exonerado a la vez', 'rit_Pagado, rit_Exonerado, rit_FechaPago, rit_ValorPago, rit_MotivoExoneracion', "1, 1, '2026-11-09', 25000, 'motivo largo suficiente'"],
    ['ni pagado ni exonerado', 'rit_Pagado, rit_Exonerado', '0, 0'],
    ['pagado sin fecha de pago', 'rit_Pagado, rit_ValorPago', '1, 25000'],
    ['exonerado sin motivo', 'rit_Pagado, rit_Exonerado', '0, 1'],
    ['exonerado con motivo corto', 'rit_Pagado, rit_Exonerado, rit_MotivoExoneracion', "0, 1, 'corto'"],
    ['estado distinto de 2 o 9', 'rit_Pagado, rit_Exonerado, rit_MotivoExoneracion, rit_Estado', "0, 1, 'motivo largo suficiente', 3"],
];
foreach ($pares as [$nombre, $cols, $vals]) {
    $msg = '';
    try { ejec("INSERT INTO ind_rit_cobros (rit_IdContribuyente, rit_Valor, rit_IdUsuario, rit_FechaCreacion, $cols) VALUES (?, 25000, 1, '2026-11-09T10:00:00', $vals)", [$N]); }
    catch (\Throwable $e) { $msg = $e->getMessage(); }
    prueba("12a la tabla rechaza: $nombre", strpos($msg, 'CK_rit_cobros') !== false, $msg);
    ejec('DELETE FROM ind_rit_cobros WHERE rit_IdContribuyente = ?', [$N]);
}
$msg = '';
try { ejec("INSERT INTO ind_rit_cobros (rit_IdContribuyente, rit_Valor, rit_Pagado, rit_FechaPago, rit_ValorPago, rit_IdUsuario, rit_FechaCreacion) VALUES (987654, 25000, 1, '2026-11-09', 25000, 1, '2026-11-09T10:00:00')"); }
catch (\Throwable $e) { $msg = $e->getMessage(); }
prueba('12b un contribuyente que no existe: rechazado por la llave foránea', strpos($msg, 'FK_rit_cobros_contribuyente') !== false, $msg);

seccion('13. La hora de Colombia no depende de la zona del servidor');
$r = ejecutar_php('$o = [];
    foreach (["UTC", "Asia/Tokyo", "Pacific/Auckland", "America/Los_Angeles", "America/Bogota", "Europe/Madrid"] as $z) {
        date_default_timezone_set($z);
        $esperado = time() - 5 * 3600;
        $ahora = \erpsoftsas\RitCobro::ahora();
        $o[$z] = [\erpsoftsas\RitCobro::hoy() === gmdate("Y-m-d", $esperado), preg_match("/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d$/", $ahora) === 1, abs(strtotime($ahora . " UTC") - $esperado) <= 5];
    }
    echo json_encode($o);');
prueba('13a hoy() y ahora() son de Colombia (UTC-5) con cualquier zona del servidor, y ahora() va en ISO con T',
    is_array($r) && count($r) === 6 && !in_array(false, array_merge(...array_values($r)), true), $r);

/* ======================================================================
   Resumen
   ====================================================================== */
$total = $ok + count($fallas);
echo "\n" . str_repeat('-', 60) . "\n";
echo "$total pruebas: $ok ok, " . count($fallas) . " con falla\n";
foreach ($fallas as $f) { echo "  FALLA: $f\n"; }
exit($fallas ? 1 : 0);
