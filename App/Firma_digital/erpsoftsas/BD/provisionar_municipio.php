<?php
/**
 * ============================================================================
 * APROVISIONA LA BASE DE UN MUNICIPIO NUEVO
 * ============================================================================
 *
 * PARA QUE EXISTE
 *
 * Un municipio nuevo (Santa Rosa, por ejemplo) arranca con una base de SQL
 * Server VACIA. Las migraciones de BD/migraciones/ no sirven para crearla desde
 * cero: la 000 solo crea la tabla de registro y la 001 ya supone que existen
 * decenas de tablas. Este script carga la base completa a partir de los
 * archivos de BD/esquema/, que son la estructura y los catalogos de Paipa al
 * nivel de la migracion 053 (ver BD/README.md):
 *
 *   1. 01_tablas.sql             tablas, llaves primarias, unicas, checks y defaults
 *   2. 02_indices_y_llaves.sql   indices y llaves foraneas
 *   3. 03_programacion.sql       procedimientos y funciones (CREATE OR ALTER)
 *   4. catalogos.sql             ciudades, actividades, conceptos, bancos, roles,
 *                                permisos y parametros, SIN datos de Paipa
 *   5. registra en conf_migraciones las migraciones que el esquema ya incluye
 *      (000 a 053), para que aplicar_migraciones.php no las repita.
 *
 * Quedan SIN registrar, a proposito, la 042 (Wompi: solo Macanal) y la 043
 * (numeracion por impuesto: solo Paipa). Seguiran apareciendo como pendientes;
 * no se aplican (nunca "--aplicar" a secas: siempre por numero). Las posteriores
 * a la 053 tambien quedan pendientes y se aplican con aplicar_migraciones.php.
 *
 * QUE NO HACE: no crea la base ni el usuario (eso es de Plesk), no crea
 * administradores (BD/crear_administrador.php) y no siembra datos de Paipa: el
 * EAN de recaudo y los bancos del recibo quedan VACIOS y las cuentas de los
 * bancos en blanco.
 *
 * SOLO POR LINEA DE COMANDOS. NUNCA POR URL (por web contesta 403, y ademas la
 * carpeta BD/ esta oculta por el web.config).
 *
 * LA CONEXION SALE DE LA CONFIGURACION DE LA APP
 *
 * Usa DB_PROD_SERVER / DB_PROD_USER / DB_PROD_PASS / DB_PROD_NAME de
 * config.municipio.php del dominio donde se ejecuta, igual que
 * aplicar_migraciones.php: no hay argumentos con claves. Si alguna de las
 * cuatro falta, el script se detiene: sin ellas la aplicacion cae a unas
 * credenciales de produccion escritas en el codigo, y aqui eso seria cargar el
 * esquema en la base equivocada.
 *
 * IDEMPOTENTE Y A PRUEBA DE ERRORES
 *
 * - Todo lo que crea lleva su guarda ("solo si no existe"), asi que si la
 *   carga se corta a la mitad se vuelve a correr sin dano.
 * - Las migraciones se registran AL FINAL. Mientras conf_migraciones este vacia
 *   la base se considera sin aprovisionar y se puede repetir. En cuanto tiene
 *   filas, el script se niega a hacer nada: asi no puede pisar por error los
 *   procedimientos de un municipio que ya esta en marcha.
 * - Se niega a correr contra la base de Paipa o contra "erpsoftweb".
 *
 * COMO SE USA
 *
 *     php BD/provisionar_municipio.php               revisa y muestra el plan, NO escribe
 *     php BD/provisionar_municipio.php --aplicar     carga el esquema y los catalogos
 *     php BD/provisionar_municipio.php --reporte=x.txt   ademas deja la salida en un archivo
 *
 * En Plesk: Herramientas de desarrollo > Tareas programadas > Ejecutar un
 * script PHP, PHP 8.3, boton "Ejecutar ahora" (NUNCA "Aceptar": guarda la
 * tarea). Primero sin argumentos; despues con --aplicar.
 * ============================================================================
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo se ejecuta por linea de comandos.');
}

/* Hasta que numero de migracion llega el esquema de BD/esquema/. Si algun dia se
   regenera mas arriba, se sube este numero junto con los archivos. */
const COBERTURA_ESQUEMA = 53;
/* Migraciones por debajo de la cobertura que NO se dan por aplicadas: 042 solo
   existe en Macanal y 043 es solo de Paipa (decision de Diego). */
const SIN_REGISTRAR = ['042', '043'];
/* Bases con las que este script jamas debe trabajar. */
const BASES_PROHIBIDAS = ['erpsofts_ind_comercio_paip', 'erpsoftweb'];

$raiz = dirname(__DIR__);

/* Por linea de comandos DOCUMENT_ROOT viene vacia y globals.php arma sus rutas
   con ella; se rellena a partir de la ubicacion de este archivo (igual que en
   aplicar_migraciones.php). Asi config.municipio.php se busca donde lo busca la
   aplicacion. */
if (empty($_SERVER['DOCUMENT_ROOT'])) {
    $_SERVER['DOCUMENT_ROOT'] = dirname($raiz);
}

require_once $raiz . '/business/globals.php';

/* globals.php apaga display_errors para que ningun aviso corrompa un JSON. Aqui
   no hay JSON: un fallo invisible en un script que toca el esquema es lo peor. */
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

if (function_exists('sqlsrv_configure')) {
    sqlsrv_configure('WarningsReturnAsErrors', 0);
}

/* --reporte=<ruta>: la salida tambien a un archivo (la consola de Plesk muestra
   la salida de forma poco fiable). Conviene borrarlo despues. */
$reporte = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--reporte=') === 0) {
        $nombre = basename(substr($arg, 10));
        $reporte = $raiz . '/' . $nombre;
        break;
    }
}
if ($reporte) {
    @file_put_contents($reporte, '');
    ob_start();
    register_shutdown_function(function () use ($reporte) {
        $salida = ob_get_contents();
        ob_end_flush();
        @file_put_contents($reporte, $salida);
    });
}

$aplicar = in_array('--aplicar', $argv, true);

function salir($mensaje, $codigo = 1)
{
    echo $mensaje . "\n";
    exit($codigo);
}

/* ---------------------------------------------------------------------------
   1. La conexion: solo la de config.municipio.php
   --------------------------------------------------------------------------- */
foreach (['DB_PROD_SERVER', 'DB_PROD_USER', 'DB_PROD_PASS', 'DB_PROD_NAME'] as $c) {
    if (!defined($c) || constant($c) === '') {
        salir("DETENIDO: config.municipio.php no define $c.\n"
            . "Sin las cuatro constantes DB_PROD_* la aplicacion usaria credenciales de produccion escritas en el codigo.\n"
            . "Cree el config.municipio.php del dominio (ver config.municipio.example.php) y vuelva a ejecutar.");
    }
}
if (in_array(strtolower(DB_PROD_NAME), array_map('strtolower', BASES_PROHIBIDAS), true)) {
    salir('DETENIDO: la base configurada (' . DB_PROD_NAME . ") no es la de un municipio nuevo. Este script no corre contra ella.");
}
if (!function_exists('sqlsrv_connect')) {
    salir('DETENIDO: PHP no tiene la extension sqlsrv. Use la version de PHP del dominio con sqlsrv (8.3 en Plesk).');
}

/* Conexion propia (mismas opciones que la de la aplicacion): el aplicador de
   migraciones pasa por la clase de la app, que trata cualquier "?" del texto como
   parametro y rompe los catalogos (hay descripciones con "?" y expresiones
   regulares). Aqui se manda el texto tal cual. */
$link = sqlsrv_connect(DB_PROD_SERVER, [
    'Database'               => DB_PROD_NAME,
    'UID'                    => DB_PROD_USER,
    'PWD'                    => DB_PROD_PASS,
    'CharacterSet'           => 'UTF-8',
    'TrustServerCertificate' => true,
]);
if (!$link) {
    $e = sqlsrv_errors();
    salir('DETENIDO: no se pudo conectar a la base ' . DB_PROD_NAME . ': ' . ($e ? $e[0]['message'] : 'sin detalle'));
}

/** Ejecuta un lote y consume todos sus resultados. Lanza excepcion si algo falla. */
function ejecutar($link, $sql, $params = [])
{
    $st = sqlsrv_query($link, $sql, $params);
    if ($st === false) {
        $e = sqlsrv_errors();
        throw new RuntimeException($e ? $e[0]['message'] : 'error desconocido');
    }
    do {
        $r = sqlsrv_next_result($st);
    } while ($r === true);
    if ($r === false) {
        $e = sqlsrv_errors();
        throw new RuntimeException($e ? $e[0]['message'] : 'error desconocido');
    }
    sqlsrv_free_stmt($st);
}

/** Primera fila de un SELECT, o null. */
function una_fila($link, $sql, $params = [])
{
    $st = sqlsrv_query($link, $sql, $params);
    if ($st === false) {
        $e = sqlsrv_errors();
        throw new RuntimeException($e ? $e[0]['message'] : 'error desconocido');
    }
    $f = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($st);
    return $f ?: null;
}

$d = una_fila($link, "SELECT DB_NAME() AS base, @@SERVERNAME AS servidor, SCHEMA_NAME() AS esquema,
                             CONVERT(sysname, DATABASEPROPERTYEX(DB_NAME(), 'Collation')) AS colacion,
                             (SELECT COUNT(*) FROM sys.tables) AS tablas");
echo "Base de datos: " . $d['base'] . "   (servidor " . $d['servidor'] . ")\n";
echo "Esquema por defecto del usuario: " . $d['esquema'] . "\n";
echo "Colacion de la base: " . $d['colacion'] . "\n";
echo "Tablas que ya tiene: " . $d['tablas'] . "\n\n";

if (strcasecmp($d['esquema'], 'dbo') !== 0) {
    salir("DETENIDO: el esquema por defecto del usuario es '" . $d['esquema'] . "', no dbo.\n"
        . "Con otro esquema por defecto las migraciones de las tablas de firma fallan (paso en Macanal).\n"
        . "En Plesk el usuario de la base debe tener dbo como esquema por defecto; corrijalo y vuelva a ejecutar.");
}
if (strcasecmp($d['colacion'], 'Modern_Spanish_CI_AS') !== 0) {
    echo "AVISO: Paipa usa la colacion Modern_Spanish_CI_AS y esta base usa " . $d['colacion'] . ".\n"
       . "       Funciona, pero el orden alfabetico y algunas comparaciones pueden diferir. Lo ideal es crearla con Modern_Spanish_CI_AS.\n\n";
}

/* Ya aprovisionada: conf_migraciones con filas. No se toca nada. */
$hay = una_fila($link, "SELECT OBJECT_ID(N'dbo.conf_migraciones', N'U') AS id");
if (!empty($hay['id'])) {
    $n = una_fila($link, "SELECT COUNT(*) AS n FROM dbo.conf_migraciones");
    if ((int) $n['n'] > 0) {
        salir("Esta base ya esta aprovisionada (conf_migraciones tiene " . $n['n'] . " migraciones registradas).\n"
            . "No se hace nada. Para lo que falte use BD/aplicar_migraciones.php (sin --aplicar primero).", 0);
    }
}

/* ---------------------------------------------------------------------------
   2. Los archivos y las migraciones que se daran por aplicadas
   --------------------------------------------------------------------------- */
$carpeta = __DIR__ . '/esquema';
$archivos = ['01_tablas.sql', '02_indices_y_llaves.sql', '03_programacion.sql', 'catalogos.sql'];
$lotesPorArchivo = [];
foreach ($archivos as $a) {
    $ruta = $carpeta . '/' . $a;
    if (!is_file($ruta)) {
        salir("DETENIDO: falta BD/esquema/$a. Haga el Pull del repositorio antes de aprovisionar.");
    }
    $sql = file_get_contents($ruta);
    if (substr($sql, 0, 3) === "\xEF\xBB\xBF") { $sql = substr($sql, 3); }
    /* Partir por las lineas que contienen solo GO (separador de lotes de sqlcmd). */
    $lotes = array_values(array_filter(
        preg_split('/^\s*GO\s*;?\s*$/mi', $sql),
        function ($l) { return trim($l) !== ''; }
    ));
    $lotesPorArchivo[$a] = $lotes;
}

$registrar = [];
foreach (glob($raiz . '/BD/migraciones/*.sql') as $ruta) {
    $nombre = basename($ruta, '.sql');
    if (!preg_match('/^(\d{3})_/', $nombre, $m)) { continue; }
    if ((int) $m[1] > COBERTURA_ESQUEMA || in_array($m[1], SIN_REGISTRAR, true)) { continue; }
    $registrar[] = $nombre;
}
sort($registrar);

echo "PLAN\n";
foreach ($lotesPorArchivo as $a => $lotes) { echo "  - $a: " . count($lotes) . " lotes\n"; }
echo "  - registrar " . count($registrar) . " migraciones (000 a " . sprintf('%03d', COBERTURA_ESQUEMA) . ", salvo " . implode(' y ', SIN_REGISTRAR) . ")\n\n";

if (!$aplicar) {
    echo "Nada se aplico. Para cargar el esquema y los catalogos, repetir con --aplicar\n";
    exit(0);
}

/* ---------------------------------------------------------------------------
   3. Cargar
   --------------------------------------------------------------------------- */
foreach ($lotesPorArchivo as $a => $lotes) {
    echo "==> $a\n";
    $n = 0;
    foreach ($lotes as $lote) {
        $n++;
        try {
            ejecutar($link, $lote);
        } catch (Throwable $e) {
            echo "    ERROR en el lote $n: " . $e->getMessage() . "\n";
            echo "    Se detiene aqui. Como todo lleva guarda, se puede corregir y volver a ejecutar.\n";
            exit(1);
        }
    }
    echo "    OK ($n lotes)\n";
}

/* Las migraciones se registran AL FINAL, una por una y solo si faltan. */
echo "==> conf_migraciones\n";
foreach ($registrar as $nombre) {
    try {
        ejecutar($link,
            "IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = ?)
                 INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota) VALUES (?, ?)",
            [$nombre, $nombre, 'Cubierta por el esquema base (provisionar_municipio.php).']);
    } catch (Throwable $e) {
        echo "    ERROR registrando $nombre: " . $e->getMessage() . "\n";
        exit(1);
    }
}
echo "    OK (" . count($registrar) . " registradas)\n\n";

/* ---------------------------------------------------------------------------
   4. Resumen
   --------------------------------------------------------------------------- */
$r = una_fila($link, "SELECT (SELECT COUNT(*) FROM sys.tables) AS tablas,
                             (SELECT COUNT(*) FROM dbo.conf_ciudades) AS ciudades,
                             (SELECT COUNT(*) FROM dbo.ind_actividadescomercio) AS actividades,
                             (SELECT COUNT(*) FROM dbo.ind_conceptos) AS conceptos,
                             (SELECT COUNT(*) FROM dbo.ind_bancos) AS bancos,
                             (SELECT COUNT(*) FROM dbo.conf_parametros) AS parametros,
                             (SELECT COUNT(*) FROM dbo.conf_usuarios) AS usuarios");
echo "RESULTADO\n";
foreach ($r as $k => $v) { echo "  $k: $v\n"; }
echo "\nSIGUIENTE PASO: BD/aplicar_migraciones.php SIN --aplicar. Debe listar la 042 y la 043 (no se aplican) y las\n"
   . "posteriores a la 053. Despues aplicar esas por numero (--aplicar 054, etc.), nunca \"--aplicar\" a secas.\n"
   . "Luego BD/crear_administrador.php para la primera cuenta.\n";
echo "\nTerminado sin errores.\n";
exit(0);
