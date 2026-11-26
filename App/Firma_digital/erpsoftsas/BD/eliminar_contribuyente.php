<?php
/**
 * ============================================================================
 * ELIMINA UN CONTRIBUYENTE QUE SE INSCRIBIO POR ERROR
 * ============================================================================
 *
 * PARA QUE EXISTE
 *
 * Alcaldia de Paipa, 2026-11-25: un contador se inscribio con SUS datos en vez
 * de los de la empresa y pidieron quitarlo. La pantalla no borra contribuyentes
 * (y no debe: un contribuyente con declaraciones presentadas es historia
 * tributaria). Este script lo hace solo en el caso seguro: alguien que se
 * inscribio mal y no alcanzo a presentar ni a pagar nada.
 *
 * La conexion sale del config.municipio.php del dominio donde se ejecuta, como
 * BD/aplicar_migraciones.php: no hace falta saber ninguna credencial, y la base
 * es siempre la de ESE municipio.
 *
 * SOLO POR LINEA DE COMANDOS. NUNCA POR URL.
 *
 * QUE HACE
 *
 *   Sin --eliminar (lo normal primero): SOLO LEE. Muestra el contribuyente, su
 *   cuenta y cuantas filas tiene en cada tabla. No escribe nada.
 *
 *   Con --eliminar: guarda una copia de cada fila en ind_eliminados_respaldo
 *   (la crea si no existe) y borra, todo en una transaccion: si algo falla no
 *   se quita nada.
 *
 * SE NIEGA A ELIMINAR SI
 *
 *   - tiene alguna declaracion PRESENTADA o PAGADA (ICA, retencion o autorretencion);
 *   - tiene pagos en linea, pagos manuales o registros de "Ya pagada";
 *   - pago la inscripcion del RIT;
 *   - hay mas de un contribuyente con ese documento (se pide --id);
 *   - con --con-cuenta, la cuenta es de la Alcaldia.
 *
 * QUE SE BORRA
 *
 *   Sus borradores con sus actividades y firmas, establecimientos (con sus
 *   actividades, anexos y novedades), actividades del RIT, firmas del RIT,
 *   cobros del RIT sin pagar y el contribuyente. Con --con-cuenta, tambien la
 *   cuenta con la que entra (mismo documento), para que la persona pueda
 *   inscribirse de nuevo con el mismo correo.
 *
 *   Los PDF de los anexos quedan en el disco del servidor: solo se borra su
 *   registro.
 *
 * USO
 *
 *   --documento=<numero>   cedula o NIT, solo numeros, sin digito de verificacion.
 *   --id=<ind_Id>          solo si hay varios contribuyentes con ese documento.
 *   --con-cuenta           borra tambien la cuenta de usuario de ese documento.
 *   --eliminar             SIN esto solo se revisa y NO se escribe nada.
 *
 *   php BD/eliminar_contribuyente.php --documento=12345678 --con-cuenta
 *   php BD/eliminar_contribuyente.php --documento=12345678 --con-cuenta --eliminar
 *
 * EN PLESK: Tareas programadas > Agregar tarea > "Ejecutar un script PHP", la
 * ruta de BD/aplicar_migraciones.php con este nombre, los argumentos de arriba
 * y "Ejecutar ahora" (nunca "Aceptar": no es una tarea para repetir).
 *
 * VUELTA ATRAS: las filas quedan en ind_eliminados_respaldo (elr_Lote es el
 * mismo para todo lo borrado en una corrida), en JSON, tabla por tabla.
 * ============================================================================
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo se ejecuta por linea de comandos.');
}

function ec_salir($mensaje, $codigo = 1) { echo rtrim($mensaje) . "\n"; exit($codigo); }
function ec_fila($con, $sql, array $p = []) { return $con->obnerFila($con->consultar($sql, $p)); }
function ec_filas($con, $sql, array $p = [])
{
    $st = $con->consultar($sql, $p);
    $r = [];
    while ($f = $con->obnerFila($st)) { $r[] = $f; }
    return $r;
}
function ec_hay($con, $tabla) { $f = ec_fila($con, "SELECT OBJECT_ID(?, 'U') AS o", ['dbo.' . $tabla]); return $f && $f['o'] !== null; }
function ec_col($con, $tabla, $col) { $f = ec_fila($con, 'SELECT COL_LENGTH(?, ?) AS c', ['dbo.' . $tabla, $col]); return $f && $f['c'] !== null; }

register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        echo "\nERROR FATAL: {$e['message']} ({$e['file']}:{$e['line']})\n";
    }
});

/* ---------- argumentos ---------- */
$documento = null; $idPedido = null; $eliminar = false; $conCuenta = false;
foreach (array_slice($argv, 1) as $a) {
    if ($a === '--eliminar') { $eliminar = true; }
    elseif ($a === '--con-cuenta') { $conCuenta = true; }
    elseif (preg_match('/^--documento=(\d{4,15})$/', $a, $m)) { $documento = $m[1]; }
    elseif (preg_match('/^--id=(\d{1,9})$/', $a, $m)) { $idPedido = (int) $m[1]; }
    else { ec_salir("Argumento no reconocido: $a\nUso: --documento=<numero> [--id=<ind_Id>] [--con-cuenta] [--eliminar]"); }
}
if ($documento === null) { ec_salir("Falta --documento=<numero> (solo numeros, sin digito de verificacion).\nSin --eliminar solo se revisa."); }

/* ---------- conexion: la de la aplicacion ---------- */
$raiz = dirname(__DIR__);
if (empty($_SERVER['DOCUMENT_ROOT'])) { $_SERVER['DOCUMENT_ROOT'] = dirname($raiz); }
require_once $raiz . '/business/globals.php';
require_once $raiz . '/business/class.conexionSqlServer.php';
ini_set('display_errors', '1');
error_reporting(E_ALL);
if (function_exists('sqlsrv_configure')) { sqlsrv_configure('WarningsReturnAsErrors', 0); }

$faltan = [];
foreach (['DB_PROD_SERVER', 'DB_PROD_USER', 'DB_PROD_PASS', 'DB_PROD_NAME'] as $c) {
    if (!defined($c) || trim((string) constant($c)) === '') { $faltan[] = $c; }
}
if ($faltan) { ec_salir('No se hizo nada: config.municipio.php no define ' . implode(', ', $faltan) . '.'); }

try {
    $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
    $con->consultar('SET LOCK_TIMEOUT 15000');
    $donde = ec_fila($con, 'SELECT DB_NAME() AS base');
} catch (\Throwable $e) {
    ec_salir("No se pudo conectar a la base de datos del municipio:\n  " . $e->getMessage());
}

echo 'ELIMINAR CONTRIBUYENTE   modo: ' . ($eliminar ? 'ELIMINAR' : 'REVISION, no escribe nada') . "\n";
echo 'Base de datos: ' . $donde['base'] . "\n\n";

/* ---------- el contribuyente ---------- */
$lista = ec_filas($con,
    "SELECT ind_Id, ind_NumeroIdentificacion AS doc,
            LTRIM(RTRIM(ISNULL(ind_PrimerNombre,'') + ' ' + ISNULL(ind_SegundoNombre,'') + ' ' + ISNULL(ind_PrimerApellido,'') + ' ' + ISNULL(ind_SegundoApellido,''))) AS nombre,
            CONVERT(VARCHAR(10), ind_FechaCreacion, 120) AS creado
       FROM ind_contribuyentes WHERE ind_NumeroIdentificacion = ? ORDER BY ind_Id", [$documento]);
if (!$lista) {
    $n = (int) ec_fila($con, 'SELECT COUNT(*) AS n FROM conf_usuarios WHERE usu_NumeroDocumento = ?', [$documento])['n'];
    $resp = ec_hay($con, 'ind_eliminados_respaldo')
        ? ec_fila($con, 'SELECT COUNT(DISTINCT elr_Lote) AS lotes, ISNULL(SUM(elr_Filas), 0) AS filas, CONVERT(VARCHAR(16), MAX(elr_Fecha), 120) AS fecha FROM dbo.ind_eliminados_respaldo WHERE elr_Documento = ?', [$documento])
        : ['lotes' => 0, 'filas' => 0, 'fecha' => null];
    // En revision no es un error: Plesk no muestra la salida de una tarea que termina con error.
    ec_salir("No hay ningun contribuyente con el documento $documento ($n cuenta(s) de usuario con ese documento).
"
           . ((int) $resp['lotes'] > 0 ? "Ya se elimino antes: {$resp['filas']} filas en el respaldo, ultima vez {$resp['fecha']}.
" : '')
           . 'No se hizo nada.', $eliminar ? 1 : 0);
}
if ($idPedido !== null) {
    $lista = array_values(array_filter($lista, function ($f) use ($idPedido) { return (int) $f['ind_Id'] === $idPedido; }));
    if (!$lista) { ec_salir("El --id=$idPedido no corresponde a un contribuyente con el documento $documento. No se hizo nada."); }
}
if (count($lista) > 1) {
    echo "Hay " . count($lista) . " contribuyentes con ese documento. Repita con --id=<ind_Id>:\n";
    foreach ($lista as $f) { echo "  ind_Id {$f['ind_Id']}  {$f['nombre']}  (inscrito {$f['creado']})\n"; }
    ec_salir('No se hizo nada.');
}
$C = (int) $lista[0]['ind_Id'];
echo "Contribuyente: ind_Id $C · {$lista[0]['nombre']} · documento {$lista[0]['doc']} · inscrito {$lista[0]['creado']}\n";

/* ---------- la cuenta ---------- */
$cuentas = ec_filas($con,
    "SELECT u.usu_Id, u.usu_Usuario, u.usu_Correo, u.usu_Rol, r.rol_Nombre" . (ec_col($con, 'conf_rol', 'rol_Tipo') ? ', r.rol_Tipo' : ", CAST(NULL AS VARCHAR(20)) AS rol_Tipo") . "
       FROM conf_usuarios u LEFT JOIN conf_rol r ON r.rol_Id = u.usu_Rol
      WHERE u.usu_NumeroDocumento = ? ORDER BY u.usu_Id", [$documento]);
$U = [];
$cuentaAlcaldia = false;
if (!$cuentas) { echo "Cuenta de usuario: ninguna con ese documento.\n"; }
foreach ($cuentas as $u) {
    $esAlcaldia = (int) $u['usu_Rol'] === 1 || stripos((string) $u['rol_Tipo'], 'ALCALD') !== false;
    if ($esAlcaldia) { $cuentaAlcaldia = true; }
    $U[] = (int) $u['usu_Id'];
    echo "Cuenta de usuario: usu_Id {$u['usu_Id']} · {$u['usu_Usuario']} · {$u['usu_Correo']} · rol {$u['usu_Rol']} ({$u['rol_Nombre']})"
       . ($esAlcaldia ? '  << ES DE LA ALCALDIA' : '') . ($conCuenta ? '  -> se borra' : '  -> se conserva (sin --con-cuenta)') . "\n";
}
$inU = $U ? implode(',', $U) : '0';

/* ---------- lo que impide eliminar ---------- */
$modulos = [
    ['ica',          'ICA',          'ind_declaraciones_ica', 'dec', 'dec_Id', 'ind_declaraciones_ica_actividades', 'dia_IdDeclaracion'],
    ['reteica',      'RETEICA',      'ind_reteica',           'ret', 'ret_Id', 'ind_reteica_actividades',           'rea_IdReteica'],
    ['autorreteica', 'AUTORRETEICA', 'ind_autorreteica',      'aut', 'aut_Id', 'ind_autorreteica_actividades',      'aua_IdAutorreteica'],
];
$impide = [];
echo "\nDeclaraciones:\n";
foreach ($modulos as [$clave, $fd, $t, $p, $pk, $tAct, $fkAct]) {
    if (!ec_hay($con, $t)) { continue; }
    $pagado = ec_col($con, $t, "{$p}_Pagado") ? "SUM(CASE WHEN ISNULL({$p}_Pagado, 0) = 1 THEN 1 ELSE 0 END)" : '0';
    $r = ec_fila($con, "SELECT COUNT(*) AS n, ISNULL(SUM(CASE WHEN ISNULL({$p}_Estado, 0) = 2 THEN 1 ELSE 0 END), 0) AS pres, ISNULL($pagado, 0) AS pag
                          FROM $t WHERE {$p}_IdContribuyente = ?", [$C]);
    printf("  %-13s %d en total · %d presentadas · %d pagadas\n", $clave, $r['n'], $r['pres'], $r['pag']);
    if ((int) $r['pres'] > 0) { $impide[] = "tiene {$r['pres']} declaracion(es) de $clave PRESENTADAS"; }
    if ((int) $r['pag'] > 0)  { $impide[] = "tiene {$r['pag']} declaracion(es) de $clave PAGADAS"; }
    foreach ([['ind_pagos_en_linea', 'pel_Modulo', 'pel_IdDeclaracion', 'pagos en linea'],
              ['ind_pagos_manuales', 'pma_Modulo', 'pma_IdDeclaracion', 'pagos manuales'],
              ['ind_declaraciones_historicas', 'his_Modulo', 'his_IdDeclaracion', 'registros de Ya pagada'],
              ['ind_recaudo_aplicaciones', 'rap_Modulo', 'rap_IdDeclaracion', 'aplicaciones del recaudo bancario']] as [$tp, $cm, $ci, $que]) {
        if (!ec_hay($con, $tp) || !ec_col($con, $tp, $cm)) { continue; }
        $n = (int) ec_fila($con, "SELECT COUNT(*) AS n FROM $tp WHERE $cm = ? AND $ci IN (SELECT $pk FROM $t WHERE {$p}_IdContribuyente = ?)", [$clave, $C])['n'];
        if ($n > 0) { $impide[] = "tiene $n $que en $clave"; }
    }
}
if (ec_hay($con, 'ind_rit_cobros')) {
    $n = (int) ec_fila($con, 'SELECT COUNT(*) AS n FROM ind_rit_cobros WHERE rit_IdContribuyente = ? AND ISNULL(rit_Pagado, 0) = 1', [$C])['n'];
    if ($n > 0) { $impide[] = 'pago la inscripcion del RIT'; }
}
if ($conCuenta && $cuentaAlcaldia) { $impide[] = 'la cuenta con ese documento es de la Alcaldia (quite --con-cuenta o revise el documento)'; }

/* ---------- lo que se borra, en orden ---------- */
$decl = function ($t, $p, $pk) use ($C) { return "(SELECT $pk FROM $t WHERE {$p}_IdContribuyente = $C)"; };
$est = "(SELECT est_Id FROM ind_establecimientos WHERE est_IdContribuyente = $C)";
$pasos = [];
foreach ($modulos as [$clave, $fd, $t, $p, $pk, $tAct, $fkAct]) {
    if (!ec_hay($con, $t)) { continue; }
    $pasos[] = [$tAct, "$fkAct IN " . $decl($t, $p, $pk)];
    $pasos[] = ['firmas_declaraciones',
                "CAST(fd_NumeroDeclaracion AS VARCHAR(30)) IN (SELECT CAST({$p}_NumeroDeclaracion AS VARCHAR(30)) FROM $t WHERE {$p}_IdContribuyente = $C)"
                . (ec_col($con, 'firmas_declaraciones', 'fd_Modulo') ? " AND ISNULL(fd_Modulo, 'ICA') = '$fd'" : ($clave === 'ica' ? '' : ' AND 1 = 0'))];
    $pasos[] = ['ind_declaracion_soportes', "sop_Modulo = '$clave' AND sop_IdDeclaracion IN " . $decl($t, $p, $pk)];
    $pasos[] = [$t, "{$p}_IdContribuyente = $C"];
}
$pasos[] = ['ind_actividad_establecimiento', "ace_IdEstablecimiento IN $est"];
$pasos[] = ['ind_establecimiento_anexos', ec_col($con, 'ind_establecimiento_anexos', 'anx_IdContribuyente')
                ? "(anx_IdContribuyente = $C OR anx_IdEstablecimiento IN $est)" : "anx_IdEstablecimiento IN $est"];
$pasos[] = ['ind_establecimiento_novedades', "nov_IdEstablecimiento IN $est"];
$pasos[] = ['ind_establecimientos', "est_IdContribuyente = $C"];
$pasos[] = ['ind_actividad_contribuyente', "atc_IdContribuyente = $C"];
$pasos[] = ['ind_rit_firmas', "rif_IdContribuyente = $C"];
$pasos[] = ['ind_rit_cobros', "rit_IdContribuyente = $C"];
if ($conCuenta && $U) {
    $pasos[] = ['codigos_verificacion', "codigo_IdUsuario IN ($inU)"];
    $pasos[] = ['firmas_usuario', "fu_IdUsuario IN ($inU)"];
    $pasos[] = ['firmas', "firma_IdUsuario IN ($inU)"];
    $pasos[] = ['conf_usuarios', "usu_Id IN ($inU)"];
}
$pasos[] = ['ind_contribuyentes', "ind_Id = $C"];
$pasos = array_values(array_filter($pasos, function ($p) use ($con) { return ec_hay($con, $p[0]); }));

echo "\nFilas que se borrarian:\n";
$total = 0;
foreach ($pasos as $i => [$t, $w]) {
    $n = (int) ec_fila($con, "SELECT COUNT(*) AS n FROM $t WHERE $w")['n'];
    $pasos[$i][2] = $n;
    $total += $n;
    if ($n > 0) { printf("  %-36s %d\n", $t, $n); }
}
echo "  TOTAL: $total filas\n";

/* La cuenta, si se conserva o se borra, no debe quedar referida en registros de otros. */
if ($conCuenta && $U) {
    foreach ([['ind_pagos_manuales', 'pma_IdUsuario'], ['ind_declaraciones_historicas', 'his_IdUsuario'], ['ind_recaudo_aplicaciones', 'rap_IdUsuario'],
              ['ind_archivos_asobancaria', 'arc_IdUsuario'], ['ind_registros_manuales_anulados', 'anu_IdUsuario']] as [$t, $c]) {
        if (!ec_hay($con, $t) || !ec_col($con, $t, $c)) { continue; }
        $n = (int) ec_fila($con, "SELECT COUNT(*) AS n FROM $t WHERE $c IN ($inU)")['n'];
        if ($n > 0) { $impide[] = "la cuenta registro $n fila(s) en $t (actuo como funcionario)"; }
    }
}

if ($impide) {
    echo "\nNO SE PUEDE ELIMINAR:\n  - " . implode("\n  - ", $impide) . "\n";
    ec_salir('No se hizo nada.', $eliminar ? 1 : 0);
}
if (!$eliminar) {
    ec_salir("\nRevision terminada: no se escribio nada. Nada lo impide; para borrarlo repita con --eliminar.", 0);
}

/* ---------- eliminar ---------- */
try {
    $con->consultar('SET QUOTED_IDENTIFIER ON');
    $con->consultar('SET ANSI_NULLS ON');
    $con->consultar('SET XACT_ABORT ON');
    if (!ec_hay($con, 'ind_eliminados_respaldo')) {
        $con->consultar("CREATE TABLE dbo.ind_eliminados_respaldo (
            elr_Id        INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_ind_eliminados_respaldo PRIMARY KEY,
            elr_Lote      VARCHAR(40)   NOT NULL,
            elr_Fecha     DATETIME2(0)  NOT NULL CONSTRAINT DF_elr_Fecha DEFAULT (GETDATE()),
            elr_Documento VARCHAR(20)   NOT NULL,
            elr_Tabla     VARCHAR(80)   NOT NULL,
            elr_Filas     INT           NOT NULL,
            elr_Datos     NVARCHAR(MAX) NULL)");
    }
    $lote = 'contribuyente-' . $documento . '-' . date('Ymd-His');

    $con->begin();
    $borradas = 0;
    foreach ($pasos as [$t, $w, $n]) {
        if ($n === 0) { continue; }
        $con->consultar(
            "INSERT INTO dbo.ind_eliminados_respaldo (elr_Lote, elr_Documento, elr_Tabla, elr_Filas, elr_Datos)
             SELECT ?, ?, ?, ?, (SELECT * FROM $t WHERE $w FOR JSON PATH, INCLUDE_NULL_VALUES)",
            [$lote, $documento, $t, $n]);
        $r = ec_fila($con, "SET NOCOUNT ON; DELETE FROM $t WHERE $w; SELECT @@ROWCOUNT AS n;");
        if ((int) $r['n'] !== $n) { throw new \RuntimeException("$t: se esperaban $n filas y se borraron {$r['n']} (alguien la modifico mientras tanto)"); }
        $borradas += $n;
        printf("  borrado %-36s %d\n", $t, $n);
    }
    $queda = (int) ec_fila($con, 'SELECT COUNT(*) AS n FROM ind_contribuyentes WHERE ind_Id = ?', [$C])['n'];
    $resp = (int) ec_fila($con, 'SELECT ISNULL(SUM(elr_Filas), 0) AS n FROM dbo.ind_eliminados_respaldo WHERE elr_Lote = ?', [$lote])['n'];
    if ($queda !== 0 || $resp !== $borradas) { throw new \RuntimeException("la comprobacion final no cuadra (queda $queda, respaldo $resp de $borradas)"); }
    $con->commit();
} catch (\Throwable $e) {
    try { $con->rollback(); } catch (\Throwable $e2) { /* ya no habia transaccion */ }
    ec_salir("\nERROR: " . $e->getMessage() . "\nNo se elimino nada (la transaccion se deshizo).");
}

echo "\nELIMINADO: $borradas filas. Respaldo en ind_eliminados_respaldo, lote $lote.\n";
echo "Los PDF de los anexos, si habia, siguen en el disco del servidor.\n";
exit(0);
