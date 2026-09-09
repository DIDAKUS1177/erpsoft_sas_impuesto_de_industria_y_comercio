<?php
/**
 * ============================================================================
 * DIAGNOSTICO DE ESQUEMA — TEMPORAL, BORRAR DESPUES DE USAR
 * ============================================================================
 *
 * Dice en que estado esta la base: que migraciones tiene registradas, cuales
 * faltan y que tablas existen. NO MODIFICA NADA: solo hace SELECT.
 *
 * POR QUE EXISTE
 *
 * Al desplegar el 2026-09-09 hizo falta saber que le faltaba a la base de
 * produccion, y no habia como averiguarlo: Plesk no trae consola SQL para SQL
 * Server, el motor solo escucha dentro del servidor (host '.\MSSQLSERVER2022'),
 * el panel muestra la salida de una tarea de forma poco fiable, y el script no
 * puede escribir un informe en disco.
 *
 * Queda la via HTTP. Se protege con un token que se pasa por la URL, y como
 * solo lee, lo peor que puede pasar si alguien acierta el token es que vea
 * nombres de tablas.
 *
 * AUN ASI: ES TEMPORAL. En cuanto se sepa el estado de la base, se borra.
 * Un archivo que enumera el esquema no tiene por que vivir en un sitio publico.
 * ============================================================================
 */

const TOKEN = 'k7Qm2xR9vT4wZ1nB';

if (!isset($_GET['t']) || !hash_equals(TOKEN, (string) $_GET['t'])) {
    http_response_code(404);
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/business/globals.php';
require_once __DIR__ . '/business/class.conexionSqlServer.php';

/* globals.php apaga display_errors a proposito; aqui hace falta verlo. */
ini_set('display_errors', '1');
error_reporting(E_ALL);

try {
    $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

    $d = $con->obnerFila($con->consultar("SELECT DB_NAME() AS b, @@SERVERNAME AS s"));
    echo "BASE: {$d['b']}   SERVIDOR: {$d['s']}\n";
    echo str_repeat('=', 60) . "\n\n";

    /* Tablas que existen hoy. */
    $tablas = [];
    $st = $con->consultar(
        "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
          WHERE TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME"
    );
    while ($f = $con->obnerFila($st)) { $tablas[] = $f['TABLE_NAME']; }

    echo "TABLAS (" . count($tablas) . "):\n";
    foreach ($tablas as $t) { echo "  $t\n"; }
    echo "\n";

    /* Migraciones registradas. */
    $hay = $con->obnerFila($con->consultar(
        "SELECT OBJECT_ID('dbo.conf_migraciones') AS id"
    ));

    if (empty($hay['id'])) {
        echo "conf_migraciones: NO EXISTE\n";
        echo "  -> esta base nunca paso por el sistema de migraciones.\n\n";
    } else {
        $ap = [];
        $st = $con->consultar("SELECT mig_Nombre FROM conf_migraciones ORDER BY mig_Nombre");
        while ($f = $con->obnerFila($st)) { $ap[] = trim($f['mig_Nombre']); }

        echo "MIGRACIONES REGISTRADAS (" . count($ap) . "):\n";
        foreach ($ap as $m) { echo "  $m\n"; }
        echo "\n";

        $archivos = glob(__DIR__ . '/BD/migraciones/*.sql');
        sort($archivos);
        $faltan = [];
        foreach ($archivos as $r) {
            $n = basename($r, '.sql');
            if (!in_array($n, $ap, true)) { $faltan[] = $n; }
        }

        echo "PENDIENTES (" . count($faltan) . "):\n";
        foreach ($faltan as $m) { echo "  $m\n"; }
        echo "\n";
    }

    /* Las columnas que el codigo nuevo necesita y que, si faltan, rompen algo
       que hoy funciona. Se comprueban una a una para poder decir cual. */
    echo "COLUMNAS CRITICAS DEL CODIGO YA DESPLEGADO:\n";
    $criticas = [
        'firmas_declaraciones'    => ['fd_Rol', 'fd_Modulo'],
        'codigos_verificacion'    => ['codigo_Rol'],
        'ind_declaraciones_ica'   => ['dec_Estado', 'dec_NumeroDeclaracion'],
        'ind_contribuyentes'      => ['ind_EmailContador', 'ind_Telefono_representante', 'ind_SinAvisosTableros'],
        'ind_establecimientos'    => ['est_Telefono'],
    ];
    foreach ($criticas as $tabla => $cols) {
        foreach ($cols as $c) {
            $r = $con->obnerFila($con->consultar("SELECT COL_LENGTH(?, ?) AS x", [$tabla, $c]));
            $estado = (isset($r['x']) && $r['x'] !== null) ? 'ok' : 'FALTA';
            printf("  %-24s %-28s %s\n", $tabla, $c, $estado);
        }
    }

    /* Parametros de configuracion. Sin RECAUDO_EAN el codigo de barras se
       imprime pero NO es pagable en banco: interesa saberlo. */
    echo "
PARAMETROS (conf_parametros):
";
    $st = $con->consultar(
        "SELECT par_Clave, par_Valor, par_Sensible FROM conf_parametros ORDER BY par_Clave"
    );
    $hayPar = false;
    while ($f = $con->obnerFila($st)) {
        $hayPar = true;
        $v = $f['par_Sensible'] ? '(sensible)'
           : (trim((string) $f['par_Valor']) === '' ? '(VACIO)' : $f['par_Valor']);
        printf("  %-26s %s
", $f['par_Clave'], $v);
    }
    if (!$hayPar) { echo "  (sin filas)
"; }

    /* Catalogos que los modulos necesitan para liquidar. */
    echo "
CATALOGOS:
";
    $r = $con->obnerFila($con->consultar(
        "SELECT COUNT(*) AS n FROM ind_actividadescomercio"
    ));
    echo "  actividades economicas        " . $r['n'] . "
";

    $st = $con->consultar(
        "SELECT ren_Modulo, COUNT(*) AS n,
                SUM(CASE WHEN ren_Formula IS NULL THEN 1 ELSE 0 END) AS sinFormula
           FROM ind_renglones_retencion GROUP BY ren_Modulo"
    );
    $hayRen = false;
    while ($f = $con->obnerFila($st)) {
        $hayRen = true;
        printf("  renglones %-18s %s  (sin formula: %s)
",
               $f['ren_Modulo'], $f['n'], $f['sinFormula']);
    }
    if (!$hayRen) { echo "  renglones de retencion        NINGUNO
"; }

} catch (\Throwable $e) {
    echo "\nERROR: " . $e->getMessage() . "\n";
}
