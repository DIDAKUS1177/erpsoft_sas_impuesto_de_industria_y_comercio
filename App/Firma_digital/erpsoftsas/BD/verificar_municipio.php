<?php
/**
 * ============================================================================
 * VERIFICACION DE UN MUNICIPIO  (SOLO LECTURA)
 * ============================================================================
 *
 * PARA QUE EXISTE
 *
 * Un municipio nuevo (Guateque, Santa Rosa...) es un dominio de Plesk, una base
 * de datos y un config.municipio.php que NO estan en el repositorio, mas unas
 * imagenes que si. Si algo queda a medias, la aplicacion no se cae: cae a los
 * valores de Paipa (su NIT, su sello, su convenio de recaudo). Es el peor error
 * posible, porque nadie lo ve hasta que sale un documento con datos ajenos o un
 * pago va al convenio de otra Alcaldia.
 *
 * Este script junta en UN informe corto todo lo que hay que mirar antes de
 * darle el municipio a su Alcaldia, para pegarlo en el chat y revisarlo:
 *
 *   1. La base a la que realmente se conecta (la del config de ESE dominio).
 *   2. Las migraciones aplicadas y las pendientes.
 *   3. Las constantes MUNICIPIO_* del config.municipio.php: definidas o ausentes.
 *   4. Las imagenes (escudo, escudo para PDF, fondo, sello): que existan y que
 *      las de los PDF no tengan canal alfa.
 *   5. Los parametros que un municipio hereda de Paipa (RECAUDO_EAN, contacto
 *      del municipio, bancos, funcionario del RIT, fecha limite, pago en linea).
 *   6. Los administradores (usuario y estado; NUNCA claves ni huellas).
 *   7. Las tablas de firma, que deben estar en el esquema dbo.
 *
 * SOLO LECTURA. Solo ejecuta consultas SELECT (vm_consulta se niega a mandar
 * otra cosa; lo unico que no es SELECT es el SET LOCK_TIMEOUT de su propia
 * sesion, que no escribe nada), no crea archivos y no cambia nada. Se puede
 * correr las veces que haga falta, en produccion tambien. No imprime claves,
 * huellas, llaves de pasarela ni la clave de la base.
 *
 * SOLO POR LINEA DE COMANDOS. NUNCA POR URL (por web contesta 403, y ademas la
 * carpeta BD/ esta oculta por el web.config).
 *
 * USO
 *
 *     php BD/verificar_municipio.php          (no recibe argumentos)
 *
 * EN PLESK  (en el dominio del municipio)
 *
 *   1. Tareas programadas (Herramientas de desarrollo) del dominio > Agregar tarea.
 *   2. Tipo de tarea: "Ejecutar un script PHP".
 *   3. Ruta del script: la de BD/aplicar_migraciones.php con el nombre cambiado
 *      (termina en App/Firma_digital/erpsoftsas/BD/verificar_municipio.php).
 *   4. Version de PHP: 8.3, como en las migraciones.
 *   5. Argumentos: ninguno.
 *   6. "Ejecutar ahora" y copiar la salida completa. NUNCA "Aceptar": guardaria
 *      una tarea que no hace falta (este script no tiene nada que repetir).
 *   7. Salir de la pantalla con "Cancelar".
 *
 * COMO LEER EL INFORME
 *
 *   [OK]     esta bien.
 *   [INFO]   dato para tener a la vista; no es un problema.
 *   [AVISO]  conviene revisarlo; puede ser correcto en ese municipio.
 *   [FALLA]  hay que corregirlo antes de usar el municipio.
 *
 * Al final repite, en una frase cada una, las fallas y los avisos. Sale con
 * codigo 1 si hay alguna falla, y con 0 si no (los avisos no cuentan).
 *
 * COMO SABE DE QUE MUNICIPIO ES
 *
 * Por el nombre de la base, igual que las migraciones 039, 042, 043, 049, 050,
 * 052 y 053 (erpsofts_ind_comercio_paip / _guat / _maca / _suta). Con otro
 * nombre se trata como un municipio nuevo: todo dato de Paipa que aparezca ahi
 * es herencia y se marca. Lo que se compara con "lo de Paipa" son datos que ya
 * son publicos (impresos en cada documento).
 *
 * ESTE ARCHIVO NO SE BORRA DEL SERVIDOR. Esta versionado en Git: borrarlo
 * choca con el siguiente "Pull". Solo corre por linea de comandos.
 * ============================================================================
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo se ejecuta por linea de comandos.');
}

foreach (array_slice($argv, 1) as $a) {
    if (trim($a) === '') { continue; }   // un campo de argumentos en blanco puede llegar como un argumento vacio
    if ($a === '--ayuda') {
        echo "verificar_municipio.php: informe de solo lectura del municipio (base, migraciones, config,\n"
           . "imagenes, parametros heredados de Paipa, administradores y tablas de firma).\n"
           . "No recibe argumentos. En Plesk: Tareas programadas > Ejecutar un script PHP > \"Ejecutar ahora\".\n"
           . "NUNCA \"Aceptar\".\n";
        exit(0);
    }
    echo "Este script no recibe argumentos (solo --ayuda). No se ejecuto.\n";
    exit(1);
}

/* ---------------------------------------------------------------------------
   Utilidades
   --------------------------------------------------------------------------- */

/* Un fatal de PHP nunca debe salir mudo (globals.php apaga display_errors y,
   por linea de comandos, Plesk solo diria "se completo con errores"). */
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        echo "\n[FALLA] ERROR FATAL de PHP: " . vm_limpiar($e['message']) . "\n";
    }
});

/* Texto sin tildes ni enie: la salida de Plesk no siempre respeta UTF-8. */
function vm_ascii($s)
{
    $s = strtr((string) $s, [
        "\xC3\xA1" => 'a', "\xC3\xA9" => 'e', "\xC3\xAD" => 'i', "\xC3\xB3" => 'o', "\xC3\xBA" => 'u',
        "\xC3\xBC" => 'u', "\xC3\xB1" => 'n', "\xC3\x81" => 'A', "\xC3\x89" => 'E', "\xC3\x8D" => 'I',
        "\xC3\x93" => 'O', "\xC3\x9A" => 'U', "\xC3\x9C" => 'U', "\xC3\x91" => 'N',
    ]);
    return preg_replace('/[^\x20-\x7E]/', '?', $s);
}

/* Minusculas, sin tildes y solo letras y numeros: para comparar sin que importe como se escribio. */
function vm_norm($s)
{
    return preg_replace('/[^a-z0-9]/', '', strtolower(vm_ascii($s)));
}

/* Nada de lo que se imprime puede llevar la clave de la base ni el nombre de su usuario. */
function vm_limpiar($texto)
{
    $texto = (string) $texto;
    if (defined('DB_PROD_PASS') && (string) DB_PROD_PASS !== '') {
        $texto = str_replace((string) DB_PROD_PASS, '***', $texto);
    }
    if (defined('DB_PROD_USER') && (string) DB_PROD_USER !== '') {
        // SQL Server lo cita entre comillas ("Login failed for user 'xxx'"): solo asi se
        // cambia, para no estropear palabras normales que contengan esas letras.
        $texto = str_replace("'" . DB_PROD_USER . "'", "'***'", $texto);
    }
    return $texto;
}

/* Un error de la base en una sola linea, sin la clave y sin el ruido del driver. */
function vm_mensaje_error($e)
{
    $m = vm_limpiar($e->getMessage());
    if (preg_match_all('/\[message\]\s*=>\s*(.+)/', $m, $mm) && !empty($mm[1])) {
        $mensajes = [];
        foreach ($mm[1] as $linea) {
            $linea = trim(preg_replace('/^(\[[^\]]*\])+/', '', trim($linea)));
            if ($linea !== '') { $mensajes[$linea] = true; }
        }
        $m = implode(' | ', array_keys($mensajes));
    }
    return substr(vm_ascii(preg_replace('/\s+/', ' ', $m)), 0, 300);
}

/* Cuenta y recuerda lo que se imprime, para el resumen del final. */
$VM = ['n' => ['OK' => 0, 'INFO' => 0, 'AVISO' => 0, 'FALLA' => 0], 'AVISO' => [], 'FALLA' => []];

function vm_linea($nivel, $texto)
{
    global $VM;
    $VM['n'][$nivel]++;
    if ($nivel === 'AVISO' || $nivel === 'FALLA') { $VM[$nivel][] = $texto; }
    $prefijo = '  ' . str_pad('[' . $nivel . ']', 8);
    echo $prefijo . wordwrap($texto, 100 - strlen($prefijo), "\n" . str_repeat(' ', strlen($prefijo)), true) . "\n";
}

/* Un dato sin juicio, alineado. */
function vm_dato($rotulo, $valor)
{
    $prefijo = '  ' . str_pad($rotulo . ' ', 21, '.') . ' ';
    echo $prefijo . wordwrap($valor, 100 - strlen($prefijo), "\n" . str_repeat(' ', strlen($prefijo)), true) . "\n";
}

function vm_seccion($titulo)
{
    echo "\n== " . $titulo . " ==\n";
}

/* Cada seccion se protege sola: un fallo en una no impide ver las demas. */
function vm_seguro($titulo, $f)
{
    try {
        $f();
    } catch (\Throwable $e) {
        vm_linea('FALLA', 'No se pudo revisar ' . $titulo . ': ' . vm_mensaje_error($e));
    }
}

/*
 * SOLO LECTURA, garantizada en el codigo: cualquier consulta que no sea un
 * SELECT solo (sin ';' y sin INTO, que con un SELECT tambien crea una tabla)
 * se rechaza antes de llegar a la base.
 */
function vm_consulta($sql, array $params = [])
{
    global $ctx;
    if (!preg_match('/^\s*SELECT\b/i', $sql) || strpos($sql, ';') !== false || preg_match('/\bINTO\b/i', $sql)) {
        throw new \LogicException('verificar_municipio.php solo hace consultas SELECT.');
    }
    $con = $ctx['con'];
    $stmt = $con->consultar($sql, $params);
    $filas = [];
    while ($f = $con->obnerFila($stmt)) { $filas[] = $f; }
    return $filas;
}

function vm_fila($sql, array $params = [])
{
    $r = vm_consulta($sql, $params);
    return $r ? $r[0] : null;
}

/* Con la base vacia, las secciones que dependen de sus tablas se omiten con un solo aviso. */
function vm_base_vacia()
{
    global $ctx;
    if (!empty($ctx['vacia'])) {
        vm_linea('INFO', 'Omitido: la base esta vacia (ver la seccion 1).');
        return true;
    }
    return false;
}

/* El valor de una constante del config (recortado), o null si no esta definida. */
function vm_const($nombre)
{
    return defined($nombre) ? trim((string) constant($nombre)) : null;
}

/* El tipo de color de un PNG (byte 25: 0 gris, 2 RGB, 3 paleta, 4 gris+alfa, 6 RGB+alfa), false si no es PNG, null si no se lee. */
function vm_png_tipo($ruta)
{
    $f = @fopen($ruta, 'rb');
    if (!$f) { return null; }
    $cab = fread($f, 26);
    fclose($f);
    if (strlen($cab) < 26 || substr($cab, 0, 8) !== "\x89PNG\r\n\x1a\n") { return false; }
    return ord($cab[25]);
}

/* ---------------------------------------------------------------------------
   Lo que se sabe de Paipa (datos publicos: salen impresos en sus documentos)
   --------------------------------------------------------------------------- */

$PAIPA = [
    'base'        => 'erpsofts_ind_comercio_paip',
    'ean'         => '7709998161047',      // convenio de recaudo de Paipa (migracion 009)
    'nit'         => '891801240',          // NIT sin puntos ni digito de verificacion
    'direccion'   => 'carrera22no2514',    // "Carrera 22 No 25-14", normalizada con vm_norm
    // Lo que el pie del ingreso de Paipa mostraba en TODOS los municipios (migracion 054).
    'telefono'    => '3185309285',         // "Soporte: 318 530 9285", solo digitos
    'dependencia' => 'secretariadehaciendadirecciondeimpuestosrentasyjurisdiccioncoactiva',
];

/* Nombre de la base -> municipio (convencion erpsofts_ind_comercio_<4 letras>). */
$BASES_CONOCIDAS = [
    'erpsofts_ind_comercio_paip' => 'Paipa',
    'erpsofts_ind_comercio_guat' => 'Guateque',
    'erpsofts_ind_comercio_maca' => 'Macanal',
    'erpsofts_ind_comercio_suta' => 'Sutatenza',
];

/*
 * Migraciones que se aplican solo en algunos municipios. Aplicar TODAS a secas en
 * una base nueva las correria, y por eso las migraciones se aplican por numero.
 *   'no_en'  => bases donde queda pendiente a proposito
 *   'solo_en'=> unica base donde se aplica
 */
$MIGRACIONES_SELECTIVAS = [
    '042_pago_en_linea_con_wompi' => [
        'no_en' => ['erpsofts_ind_comercio_paip'],
        'nota'  => 'pendiente a proposito en Paipa; hace falta solo donde se cobre con Wompi',
    ],
    '043_consecutivos_por_impuesto_paipa' => [
        'solo_en' => ['erpsofts_ind_comercio_paip'],
        'nota'    => 'solo Paipa (decision de Diego); en los demas municipios no se aplica',
    ],
];

/* Las cuatro tablas de firma que usa el codigo (microservicios/firmas y RIT). */
$TABLAS_FIRMA = ['codigos_verificacion', 'firmas_declaraciones', 'firmas_usuario', 'ind_rit_firmas'];

/* ---------------------------------------------------------------------------
   Arranque: la conexion es la de la propia aplicacion
   --------------------------------------------------------------------------- */

$raiz = dirname(__DIR__);
if (empty($_SERVER['DOCUMENT_ROOT'])) {
    $_SERVER['DOCUMENT_ROOT'] = dirname($raiz);
}

require_once $raiz . '/business/globals.php';
require_once $raiz . '/business/class.conexionSqlServer.php';

/* globals.php apaga display_errors a proposito (un aviso impreso rompe el JSON de
   las pantallas). Aqui no hay JSON ni navegador: un fallo invisible seria lo peor. */
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
if (function_exists('sqlsrv_configure')) {
    sqlsrv_configure('WarningsReturnAsErrors', 0);
}
/* Las clases de la aplicacion cuentan sus problemas con error_log() y, por linea de comandos, eso
   sale tal cual en medio del informe (un volcado de varias lineas si, por ejemplo, falta una tabla).
   Aqui cada problema ya tiene su propia linea, asi que ese ruido se descarta (al dispositivo nulo:
   no se crea ningun archivo). Si el servidor no lo permite, solo queda el ruido. */
@ini_set('error_log', DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null');

$ahora = new \DateTime('now', new \DateTimeZone('America/Bogota'));
echo "VERIFICACION DEL MUNICIPIO   (solo lectura: no cambia nada)\n";
echo 'Fecha (hora de Colombia): ' . $ahora->format('Y-m-d H:i') . '   PHP ' . PHP_VERSION . "\n";

$ctx = ['con' => null, 'base' => '', 'esPaipa' => false, 'municipio' => null, 'vacia' => false, 'raiz' => $raiz, 'web' => dirname($raiz)];

/* ===========================================================================
   1. BASE DE DATOS Y CONFIGURACION
   =========================================================================== */

vm_seccion('1. BASE DE DATOS Y CONFIGURACION');

// Donde esta el config: el mismo orden con que lo busca business/globals.php.
$cfgArriba = $ctx['web'] . '/config.municipio.php';
$cfgDentro = $raiz . '/config.municipio.php';
$lineaConfig = is_file($cfgArriba)
    ? ['OK', 'config.municipio.php: un nivel arriba de erpsoftsas/ (la ubicacion prevista para produccion).']
    : (is_file($cfgDentro)
        ? ['INFO', 'config.municipio.php: dentro de erpsoftsas/ (funciona, pero la ubicacion prevista para produccion es un nivel arriba).']
        : ['FALLA', 'No se encontro config.municipio.php ni un nivel arriba de erpsoftsas/ ni dentro de el.']);

// Sin las cuatro DB_PROD_* la clase de conexion cae a una base de respaldo que trae
// embebida el codigo: ni se consulta, porque no seria la base del municipio.
$faltan = [];
foreach (['DB_PROD_SERVER', 'DB_PROD_USER', 'DB_PROD_PASS', 'DB_PROD_NAME'] as $c) {
    if (vm_const($c) === null || vm_const($c) === '') { $faltan[] = $c; }
}
if ($faltan) {
    vm_linea($lineaConfig[0], $lineaConfig[1]);
    vm_linea('FALLA', 'El config no define ' . implode(', ', $faltan) . '. Sin eso la aplicacion se conecta a una base de respaldo '
        . 'embebida en el codigo; por eso este informe NO se conecta a ninguna base.');
} else {
    vm_seguro('la conexion', function () use ($lineaConfig) {
        global $ctx, $PAIPA, $BASES_CONOCIDAS;
        $ctx['con'] = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
        // Que un bloqueo ajeno no deje esperando a Plesk: se corta con un error claro.
        $ctx['con']->consultar('SET LOCK_TIMEOUT 8000');

        $d = vm_fila("SELECT DB_NAME() AS base, ISNULL(@@SERVERNAME, '-') AS servidor, ISNULL(SCHEMA_NAME(), '-') AS esquema");
        $ctx['base'] = (string) $d['base'];
        $ctx['esPaipa'] = strcasecmp($ctx['base'], $PAIPA['base']) === 0;
        foreach ($BASES_CONOCIDAS as $nombreBase => $muni) {
            if (strcasecmp($ctx['base'], $nombreBase) === 0) { $ctx['municipio'] = $muni; }
        }

        vm_dato('Base de datos', vm_ascii($ctx['base']));
        vm_dato('Servidor', vm_ascii($d['servidor']));
        if ($ctx['municipio'] !== null) {
            vm_dato('Municipio', $ctx['municipio'] . ' (por el nombre de la base)');
        } else {
            vm_dato('Municipio', '(nombre de base no reconocido: se trata como un municipio nuevo)');
        }

        vm_linea($lineaConfig[0], $lineaConfig[1]);
        vm_linea('OK', 'DB_PROD_SERVER, DB_PROD_USER, DB_PROD_PASS y DB_PROD_NAME definidas.');
        if ($ctx['municipio'] === null) {
            vm_linea('INFO', 'Las migraciones 039, 042, 043, 049, 050, 052 y 053 se ramifican por el nombre de la base: con este nombre '
                . 'actuan como municipio generico (no siembran los datos propios de Paipa ni de Macanal).');
        }
        if (strcasecmp($d['esquema'], 'dbo') === 0) {
            vm_linea('OK', 'Esquema por defecto del usuario de la base: dbo.');
        } else {
            vm_linea('AVISO', 'El esquema por defecto del usuario de la base es "' . vm_ascii($d['esquema'])
                . '", no dbo: una migracion que cree tablas sin el prefijo dbo. las dejaria en ese esquema (paso en Macanal con las de firma).');
        }

        // Una base vacia (Sutatenza, o una recien creada) o que no es la de la aplicacion: lo demas del
        // informe seria una lista de faltantes que son consecuencia de esto, asi que se omite.
        $t = vm_fila("SELECT OBJECT_ID('dbo.conf_usuarios') AS u, OBJECT_ID('dbo.conf_rol') AS r, OBJECT_ID('dbo.conf_migraciones') AS m");
        $ctx['vacia'] = empty($t['u']) && empty($t['r']) && empty($t['m']);
        if ($ctx['vacia']) {
            vm_linea('FALLA', 'Esta base no tiene ninguna tabla de la aplicacion (conf_usuarios, conf_rol, conf_migraciones): esta vacia o no es la '
                . 'del municipio. Revise DB_PROD_NAME en el config y, si es la correcta, cargue el esquema y las migraciones antes de seguir.');
        }
    });
}

if ($ctx['con'] === null) {
    // Sin conexion no hay nada mas que revisar.
    vm_resumen_y_salir();
}

/* ===========================================================================
   2. MIGRACIONES
   =========================================================================== */

vm_seccion('2. MIGRACIONES');
vm_seguro('las migraciones', function () {
    global $ctx, $MIGRACIONES_SELECTIVAS;
    if (vm_base_vacia()) { return; }

    $archivos = [];
    foreach (glob($ctx['raiz'] . '/BD/migraciones/*.sql') ?: [] as $ruta) { $archivos[] = basename($ruta, '.sql'); }
    sort($archivos);

    $aplicadas = [];
    $hay = vm_fila("SELECT OBJECT_ID('dbo.conf_migraciones') AS id");
    if (empty($hay['id'])) {
        vm_linea('FALLA', 'La tabla conf_migraciones no existe: esta base nunca paso por el sistema de migraciones (hay que empezar por la 000).');
    } else {
        foreach (vm_consulta("SELECT mig_Nombre, mig_FechaAplicada FROM conf_migraciones") as $f) {
            $fecha = $f['mig_FechaAplicada'];
            $aplicadas[trim($f['mig_Nombre'])] = ($fecha instanceof \DateTimeInterface) ? $fecha->format('Y-m-d') : (string) $fecha;
        }
    }
    if (!$archivos) {
        vm_linea('AVISO', 'No se encontro ningun .sql en BD/migraciones (se esperaba la carpeta junto a este script).');
    }

    // Una migracion registrada con otro nombre (paso con la 027) cuenta como aplicada si su
    // numero tiene un solo archivo y una sola fila registrada.
    $archivoSet = array_flip($archivos);
    $porNum = [];
    foreach ($archivos as $n) { $porNum[substr($n, 0, 3)][] = $n; }
    $regPorNum = [];
    foreach (array_keys($aplicadas) as $n) { $regPorNum[substr($n, 0, 3)][] = $n; }

    $pendientes = [];
    $esperadas = [];
    $alias = [];
    foreach ($archivos as $n) {
        if (isset($aplicadas[$n])) { continue; }
        $num = substr($n, 0, 3);
        if (count($porNum[$num]) === 1 && isset($regPorNum[$num]) && count($regPorNum[$num]) === 1
            && !isset($archivoSet[$regPorNum[$num][0]])) {
            $alias[$n] = $regPorNum[$num][0];
            continue;
        }
        $sel = isset($MIGRACIONES_SELECTIVAS[$n]) ? $MIGRACIONES_SELECTIVAS[$n] : null;
        $espera = false;
        if ($sel) {
            if (isset($sel['no_en']) && in_array(strtolower($ctx['base']), $sel['no_en'], true)) { $espera = true; }
            if (isset($sel['solo_en']) && !in_array(strtolower($ctx['base']), $sel['solo_en'], true)) { $espera = true; }
        }
        if ($espera) { $esperadas[$n] = $sel['nota']; } else { $pendientes[] = $n; }
    }

    vm_dato('Archivos', count($archivos) . ' en BD/migraciones');
    vm_dato('Registradas', count($aplicadas) . ' en la base');
    if ($aplicadas) {
        $ultima = array_keys($aplicadas);
        sort($ultima);
        $u = end($ultima);
        vm_dato('Ultima aplicada', vm_ascii($u) . ' (' . $aplicadas[$u] . ')');
    }

    if ($pendientes) {
        $muestra = array_slice($pendientes, 0, 12);
        $resto = count($pendientes) - count($muestra);
        vm_linea('AVISO', 'Migraciones pendientes (' . count($pendientes) . '): ' . implode(', ', $muestra)
            . ($resto > 0 ? ' y ' . $resto . ' mas' : '')
            . '. Aplicar SIEMPRE por numero (aplicar_migraciones.php --aplicar NNN), nunca --aplicar a secas.');
    } elseif ($archivos && $aplicadas) {
        vm_linea('OK', 'Todas las migraciones del repositorio estan aplicadas.');
    }
    foreach ($esperadas as $n => $nota) {
        vm_linea('INFO', $n . ': ' . $nota . '.');
    }
    foreach ($alias as $archivo => $registrada) {
        vm_linea('INFO', $archivo . ' esta registrada con otro nombre (' . vm_ascii($registrada) . '): se cuenta como aplicada.');
    }
});

/* ===========================================================================
   3. CONSTANTES DEL config.municipio.php
   =========================================================================== */

vm_seccion('3. CONSTANTES DE config.municipio.php');
vm_seguro('las constantes', function () {
    global $ctx, $PAIPA;
    $esPaipa = $ctx['esPaipa'];

    /*
     * clase:
     *   propia     - cada municipio la define; si falta, el codigo usa la de Paipa
     *   sindefecto - cada municipio la define; si falta, el codigo NO imprime nada (ya no trae
     *                el dato de Paipa por defecto): falla en TODOS los municipios, Paipa incluida
     *   texto      - titulo que sale en los formularios; si falta, el del formulario de Paipa
     *   defecto    - tiene un valor por defecto razonable
     *   derivada   - se deduce de otra
     */
    $constantes = [
        ['MUNICIPIO_NOMBRE',  'propia',   'el login, el menu y los PDF dicen Paipa'],
        ['MUNICIPIO_CIUDAD',  'propia',   'los PDF dicen Paipa'],
        ['MUNICIPIO_NIT',     'sindefecto', 'los documentos (declaracion, liquidacion, recibo, retenciones, RIT) salen SIN el NIT de la Alcaldia'],
        ['MUNICIPIO_DIRECCION', 'sindefecto', 'la direccion de la Alcaldia sale en blanco en la liquidacion, el recibo y el RIT'],
        ['MUNICIPIO_LOGO',    'propia',   'se usa el escudo de Paipa'],
        ['MUNICIPIO_FONDO_LOGIN', 'propia', 'el login usa el fondo de Paipa'],
        ['MUNICIPIO_SELLO_FIRMA', 'propia', 'los documentos se firman con el sello de Paipa (Sello_Firma.png)'],
        ['MUNICIPIO_SECRETARIA', 'texto', 'los formularios dicen SECRETARIA DE HACIENDA (el titulo de Paipa)'],
        ['MUNICIPIO_DEPENDENCIA_TRIBUTARIA', 'texto', 'los formularios dicen DIRECCION DE IMPUESTOS, RENTAS Y JURISDICCION COACTIVA (la de Paipa)'],
        ['MUNICIPIO_DEPARTAMENTO', 'defecto', 'se usa Boyaca'],
        ['MUNICIPIO_COLOR',   'defecto',  'se usa el color verde azulado por defecto (#1fa49d)'],
        ['MUNICIPIO_COLOR_OSCURO', 'defecto', 'se usa el tono oscuro por defecto (#17756f)'],
        ['MUNICIPIO_LOGO_PDF', 'derivada', 'se busca el escudo con sufijo -pdf junto al escudo normal'],
    ];

    // Lo correcto va junto en una sola linea; solo lo que falla o avisa va aparte.
    $definidas = [];
    $porDefecto = [];
    foreach ($constantes as $c) {
        list($nombre, $clase, $siFalta) = $c;
        $v = vm_const($nombre);
        if ($v === null || $v === '') {
            $estado = ($v === null) ? 'no esta definida' : 'esta vacia';
            if ($clase === 'sindefecto' || ($clase === 'propia' && !$esPaipa)) {
                vm_linea('FALLA', $nombre . ' ' . $estado . ': ' . $siFalta . '.');
            } elseif ($clase === 'texto' && !$esPaipa) {
                vm_linea('AVISO', $nombre . ' ' . $estado . ': ' . $siFalta . '. Defina el del municipio si es otro.');
            } else {
                $porDefecto[] = $nombre;
            }
            continue;
        }

        // globals.php da 'Boyaca' al departamento cuando el config no lo define: no se puede saber si lo definio.
        if ($nombre === 'MUNICIPIO_DEPARTAMENTO' && vm_norm($v) === 'boyaca') {
            $definidas[] = $nombre . ' = ' . vm_ascii($v) . ($esPaipa ? '' : ' (es el valor por defecto: cambielo en el config si el municipio no es de Boyaca)');
            continue;
        }

        // Definida: si no es Paipa, que no sea un dato de Paipa.
        $norm = vm_norm($v);
        $dePaipa = false;
        if (!$esPaipa) {
            if ($nombre === 'MUNICIPIO_NIT') {
                $dePaipa = strpos(preg_replace('/\D/', '', $v), $PAIPA['nit']) === 0;
            } elseif ($nombre === 'MUNICIPIO_DIRECCION') {
                $dePaipa = strpos($norm, $PAIPA['direccion']) !== false;
            } elseif ($nombre === 'MUNICIPIO_SELLO_FIRMA') {
                $dePaipa = strcasecmp($v, 'Sello_Firma.png') === 0 || strpos($norm, 'paipa') !== false;
            } else {
                $dePaipa = strpos($norm, 'paipa') !== false;
            }
        }
        if ($dePaipa) {
            vm_linea('FALLA', $nombre . ' = ' . vm_ascii($v) . ': es un dato de PAIPA y esta base no es la de Paipa.');
        } else {
            $definidas[] = $nombre . ' = ' . vm_ascii($v);
        }
    }
    if ($definidas) {
        vm_linea('OK', 'Definidas: ' . implode('; ', $definidas) . '.');
    }
    if ($porDefecto) {
        vm_linea('INFO', 'Sin definir (el codigo usa su valor por defecto' . ($esPaipa ? ', que es el de Paipa: correcto aqui' : '') . '): '
            . implode(', ', $porDefecto) . '.');
    }

    // Respaldos y secretos: solo se dice si estan, nunca su valor.
    if (vm_const('MUNICIPIO_CLAVE_PARAMETROS_HASH') !== null && vm_const('MUNICIPIO_CLAVE_PARAMETROS_HASH') !== '') {
        vm_linea('OK', 'MUNICIPIO_CLAVE_PARAMETROS_HASH definida: el municipio tiene su propia contrasena para editar los parametros.');
    } else {
        vm_linea('INFO', 'MUNICIPIO_CLAVE_PARAMETROS_HASH no esta definida: editar los parametros (EAN, pasarela, fecha limite) pide la contrasena del dueno del sistema.');
    }
    $lg = vm_const('PLACETOPAY_LOGIN');
    $sk = vm_const('PLACETOPAY_SECRETKEY');
    if ($lg === 'login_placetopay' || $sk === 'secretkey_placetopay') {
        vm_linea('AVISO', 'PLACETOPAY_LOGIN / PLACETOPAY_SECRETKEY traen los valores de relleno de la plantilla: la aplicacion los toma como credenciales reales '
            . '(no copie ese bloque de config.municipio.example.php).');
    }
});

/* ===========================================================================
   4. IMAGENES
   =========================================================================== */

vm_seccion('4. IMAGENES');
vm_seguro('las imagenes', function () {
    global $ctx;
    $esPaipa = $ctx['esPaipa'];
    $web = $ctx['web'];       // desde aqui cuelgan las rutas web (/erpsoftsas/...), como en los PDF
    $raiz = $ctx['raiz'];

    // Ruta web (/erpsoftsas/...) -> disco, como lo hacen los PDF; sin barra inicial, relativa a erpsoftsas/.
    $aDisco = function ($ruta) use ($web, $raiz) {
        return $ruta[0] === '/' ? $web . $ruta : $raiz . '/' . $ruta;
    };

    // Los mismos valores por defecto que usan index.php y los PDF cuando el config no define la constante.
    $logo  = vm_const('MUNICIPIO_LOGO');
    $logo  = ($logo !== null && $logo !== '') ? $logo : '/erpsoftsas/vendors/images/escudo-paipa.png';
    $logoPdf = vm_const('MUNICIPIO_LOGO_PDF');
    if ($logoPdf === null || $logoPdf === '') {
        $variante = preg_replace('/\.png$/i', '-pdf.png', $logo);
        $logoPdf = ($variante !== $logo && is_file($aDisco($variante))) ? $variante : $logo;
    }
    $fondo = vm_const('MUNICIPIO_FONDO_LOGIN');
    $fondo = ($fondo !== null && $fondo !== '') ? $fondo : 'vendors/images/fondo-paipa.jpg';
    $sello = vm_const('MUNICIPIO_SELLO_FIRMA');
    $sello = ($sello !== null && $sello !== '') ? $sello : 'Sello_Firma.png';

    $imagenes = [
        // rotulo, ruta tal como esta en el config, ruta en disco, va dentro de un PDF (si/no)
        ['Escudo (web)',     $logo,    $aDisco($logo), false],
        ['Escudo (PDF)',     $logoPdf, $aDisco($logoPdf), true],
        ['Fondo del login',  $fondo,   $aDisco($fondo), false],
        // El sello es un nombre de archivo dentro de extensiones/ (los PDF hacen chdir ahi); si el
        // config trae una ruta absoluta del disco, se usa tal cual, como hace declaracion.php.
        ['Sello de firma',   $sello,   (preg_match('#^([A-Za-z]:[\\\\/]|/)#', $sello) ? $sello : $raiz . '/extensiones/' . $sello), true],
    ];

    // Cada imagen da un [nivel, texto]; si todas estan bien se resumen en una sola linea.
    $resultados = [];
    foreach ($imagenes as $img) {
        list($rotulo, $ruta, $disco, $enPdf) = $img;
        $etiqueta = $rotulo . ' ' . vm_ascii($ruta);

        if (preg_match('#^https?://#i', $ruta)) {
            $resultados[] = ['AVISO', $etiqueta . ': es una direccion web, no se puede revisar desde aqui.'];
            continue;
        }
        if (!is_file($disco)) {
            // El escudo de los PDF cae al escudo web cuando no hay variante -pdf: no se repite la falta.
            if ($rotulo === 'Escudo (PDF)' && $ruta === $logo) { continue; }
            $resultados[] = ['FALLA', $etiqueta . ': el archivo NO existe en el servidor.'];
            continue;
        }
        // Una imagen de Paipa en otro municipio. La falla ya se conto en la seccion 3 (la
        // constante falta o apunta a Paipa); aqui solo se deja a la vista, sin contarla dos veces.
        if (!$esPaipa && (strpos(vm_norm($ruta), 'paipa') !== false || strcasecmp(basename($ruta), 'Sello_Firma.png') === 0)) {
            $resultados[] = ['PAIPA', $rotulo . ' ' . vm_ascii($ruta)];
            continue;
        }
        if ($enPdf) {
            $t = vm_png_tipo($disco);
            if ($t === 4 || $t === 6) {
                $resultados[] = ['FALLA', $etiqueta . ': PNG con canal alfa (tipo ' . $t . '). TCPDF necesita escribir archivos temporales con el y '
                    . 'en Plesk el PDF muere con "Unable to write file". Hay que aplanarlo a RGB sin alfa (tipo 2).'];
            } elseif ($t === 3) {
                $resultados[] = ['AVISO', $etiqueta . ': PNG de paleta (tipo 3). El estandar del proyecto es RGB sin alfa (tipo 2); si el PDF falla, aplanarlo.'];
            } elseif ($t === 0 || $t === 2) {
                $resultados[] = ['OK', $etiqueta . ': existe, PNG sin alfa (tipo ' . $t . ').'];
            } elseif ($t === false) {
                $resultados[] = preg_match('/\.png$/i', $disco)
                    ? ['AVISO', $etiqueta . ': se llama .png pero no es un PNG valido (archivo danado o subido a medias): el PDF no podra usarlo.']
                    : ['INFO', $etiqueta . ': existe (no es PNG, el canal alfa no aplica).'];
            } else {
                $resultados[] = ['AVISO', $etiqueta . ': existe pero no se pudo leer.'];
            }
        } else {
            $resultados[] = ['OK', $etiqueta . ': existe.'];
        }
    }
    // Las imagenes de Paipa en otro municipio van juntas en una sola linea.
    $dePaipa = [];
    $demas = [];
    foreach ($resultados as $r) {
        if ($r[0] === 'PAIPA') { $dePaipa[] = $r[1]; } else { $demas[] = $r; }
    }
    $todasOk = true;
    foreach ($demas as $r) { if ($r[0] !== 'OK') { $todasOk = false; } }
    if ($dePaipa) {
        vm_linea('INFO', 'Imagenes de PAIPA en uso: ' . implode('; ', $dePaipa) . ' (ver la seccion 3).');
    }
    if ($todasOk && $demas && !$dePaipa) {
        vm_linea('OK', 'Escudo (web), escudo (PDF), fondo del login y sello de firma existen; los dos que van dentro de los PDF son PNG sin canal alfa.');
    } else {
        foreach ($demas as $r) { vm_linea($r[0], $r[1]); }
    }
});

/* ===========================================================================
   5. PARAMETROS QUE UN MUNICIPIO HEREDA DE PAIPA
   =========================================================================== */

vm_seccion('5. PARAMETROS (conf_parametros)');
vm_seguro('los parametros', function () {
    global $ctx, $PAIPA;
    if (vm_base_vacia()) { return; }
    $esPaipa = $ctx['esPaipa'];

    $hay = vm_fila("SELECT OBJECT_ID('dbo.conf_parametros') AS id");
    if (empty($hay['id'])) {
        vm_linea('AVISO', 'No existe la tabla conf_parametros (migracion 009): el EAN, la pasarela y demas salen del config.');
        $P = [];
    } else {
        $P = [];
        // Activo es lo mismo que para la aplicacion (Parametros::_cargar): ISNULL(par_Estado, 1) = 1.
        foreach (vm_consulta("SELECT par_Clave, par_Valor, ISNULL(par_Estado, 1) AS par_Estado FROM conf_parametros") as $f) {
            $P[trim($f['par_Clave'])] = [
                'valor'  => trim((string) $f['par_Valor']),
                'activo' => (int) $f['par_Estado'] === 1,
            ];
        }
    }
    // El valor de un parametro activo y con algo escrito, o null (como Parametros::valor).
    $valor = function ($clave) use ($P) {
        return (isset($P[$clave]) && $P[$clave]['activo'] && $P[$clave]['valor'] !== '') ? $P[$clave]['valor'] : null;
    };

    // --- EAN del convenio de recaudo (la forma de la aplicacion: la tabla manda, el config es respaldo)
    $ean = null;
    $fuente = '';
    $t = $valor('RECAUDO_EAN');
    if ($t !== null && preg_match('/^\d{13}$/', $t) === 1) {
        $ean = $t;
        $fuente = 'tabla';
    } elseif (preg_match('/^\d{13}$/', (string) vm_const('MUNICIPIO_EAN_RECAUDO')) === 1) {
        $ean = vm_const('MUNICIPIO_EAN_RECAUDO');
        $fuente = 'config';
    }
    if ($ean !== null && $ean === $PAIPA['ean'] && !$esPaipa) {
        $arreglo = ($fuente === 'tabla')
            ? 'Cambielo por el del municipio, o vacielo, en Municipio y bancos.'
            : 'Viene de MUNICIPIO_EAN_RECAUDO en config.municipio.php: quite esa constante, o escriba el del municipio en Municipio y bancos (la tabla manda sobre el config).';
        vm_linea('FALLA', 'RECAUDO_EAN (' . $fuente . ') es el convenio de recaudo de PAIPA (' . $ean . '): el codigo de barras de los recibos de este '
            . 'municipio mandaria el pago al convenio de Paipa. ' . $arreglo);
    } elseif ($ean !== null) {
        vm_linea('OK', 'RECAUDO_EAN (' . $fuente . ') = ' . $ean . ($esPaipa ? ' (convenio de Paipa).' : ' (distinto del de Paipa).'));
    } elseif ($t !== null) {
        vm_linea('AVISO', 'RECAUDO_EAN tiene un valor que no son 13 digitos: la aplicacion lo ignora.');
    } elseif (isset($P['RECAUDO_EAN']) && !$P['RECAUDO_EAN']['activo'] && $P['RECAUDO_EAN']['valor'] !== '') {
        vm_linea('INFO', 'RECAUDO_EAN esta apagado (par_Estado = 0) y la aplicacion lo ignora: el codigo de barras lleva solo el numero de la declaracion.');
    } else {
        vm_linea('INFO', 'RECAUDO_EAN vacio: el codigo de barras lleva solo el numero de la declaracion y NO es pagable en banco (estado seguro hasta tener el convenio).');
    }

    // --- Fecha limite del ICA
    $fl = $valor('ICA_FECHA_LIMITE');
    if ($fl === null) {
        vm_linea('INFO', 'ICA_FECHA_LIMITE sin valor: el codigo usa 30/04.');
    } elseif ($fl === '30/04' && !$esPaipa) {
        vm_linea('INFO', 'ICA_FECHA_LIMITE = 30/04, el valor con que nace toda base: confirme con la Alcaldia que es la suya.');
    } else {
        vm_linea('OK', 'ICA_FECHA_LIMITE = ' . vm_ascii($fl));
    }

    // --- Funcionario que firma la casilla 31 del RIT. Si no es Paipa, a la vista para que
    // se lea: un nombre heredado de Paipa no se puede reconocer por si solo.
    $fn = $valor('RIT_FUNCIONARIO_NOMBRE');
    $fc = $valor('RIT_FUNCIONARIO_CARGO');
    if ($fn === null && $fc === null) {
        vm_linea('AVISO', 'RIT_FUNCIONARIO_NOMBRE y RIT_FUNCIONARIO_CARGO vacios: la casilla 31 del RIT sale en blanco para firmar a mano.');
    } else {
        vm_linea($esPaipa ? 'OK' : 'INFO', 'Funcionario del RIT (casilla 31): ' . vm_ascii($fn === null ? '(sin nombre)' : $fn) . ' / '
            . vm_ascii($fc === null ? '(sin cargo)' : $fc) . ($esPaipa ? '' : '. Confirme que es el de este municipio y no uno heredado de Paipa.'));
    }

    // --- Contacto del municipio (migracion 054): el pie del ingreso y los avisos de las pantallas.
    // Antes salian los datos de Paipa en TODOS los municipios; ahora cada Alcaldia escribe los
    // suyos en Municipio y bancos y, vacios, no sale nada. Aqui solo se mira que no sean los de Paipa.
    $cd = $valor('CONTACTO_DEPENDENCIA');
    $ct = $valor('CONTACTO_TELEFONO');
    $cc = $valor('CONTACTO_CORREO');
    if (!isset($P['CONTACTO_DEPENDENCIA']) && !isset($P['CONTACTO_TELEFONO']) && !isset($P['CONTACTO_CORREO'])) {
        vm_linea('INFO', 'Contacto del municipio: los parametros CONTACTO_* no existen todavia (migracion 054).');
    } else {
        $deP = [];
        if (!$esPaipa) {
            if ($ct !== null && strpos(preg_replace('/\D/', '', $ct), $PAIPA['telefono']) !== false) { $deP[] = 'CONTACTO_TELEFONO'; }
            if ($cd !== null && (strpos(vm_norm($cd), $PAIPA['dependencia']) !== false || strpos(vm_norm($cd), 'paipa') !== false)) { $deP[] = 'CONTACTO_DEPENDENCIA'; }
            if ($cc !== null && strpos(vm_norm($cc), 'paipa') !== false) { $deP[] = 'CONTACTO_CORREO'; }
        }
        if ($deP) {
            vm_linea('FALLA', implode(', ', $deP) . (count($deP) > 1 ? ' traen' : ' trae') . ' datos de PAIPA y esta base no es la de Paipa: el pie '
                . 'del ingreso o los avisos de las pantallas mandarian a los contribuyentes a Paipa. Cambielos en Municipio y bancos > Contacto del municipio.');
        } elseif ($cd === null && $ct === null && $cc === null) {
            vm_linea('AVISO', 'Contacto del municipio vacio: el pie del ingreso no muestra dependencia ni telefono de soporte y los avisos no mencionan '
                . 'ningun correo. Escribalos en Municipio y bancos > Contacto del municipio.');
        } else {
            vm_linea('OK', 'Contacto del municipio: ' . ($cd !== null ? 'dependencia "' . vm_ascii($cd) . '"' : 'sin dependencia') . '; '
                . ($ct !== null ? 'telefono ' . vm_ascii($ct) : 'sin telefono') . '; '
                . ($cc !== null ? 'correo ' . vm_ascii($cc) : 'sin correo') . '.');
        }
    }

    // --- Bancos que salen en el recibo de pago (cuentas recaudadoras). Solo los nombres: nunca las cuentas.
    $hayBancos = vm_fila("SELECT OBJECT_ID('dbo.ind_bancos') AS id");
    if (!empty($hayBancos['id'])) {
        $bancos = vm_consulta("SELECT ban_Nombre FROM ind_bancos
                                WHERE ban_Activo = 1 AND NULLIF(LTRIM(RTRIM(ban_CuentaRecaudadora)), '') IS NOT NULL
                                ORDER BY ban_Nombre");
        if (!$bancos) {
            vm_linea('INFO', 'Bancos del recibo: ninguno tiene cuenta recaudadora (el recibo sale sin la linea "Paguese en").');
        } else {
            $nombres = [];
            foreach ($bancos as $b) { $nombres[] = vm_ascii(trim($b['ban_Nombre'])); }
            vm_linea($esPaipa ? 'OK' : 'INFO', 'Bancos del recibo: ' . count($bancos) . ' con cuenta recaudadora (' . implode(', ', array_slice($nombres, 0, 8))
                . (count($nombres) > 8 ? ', ...' : '') . ')' . ($esPaipa ? '.' : '. Confirme que las cuentas son de este municipio y no de Paipa.'));
        }
    }
    if (!$esPaipa && $valor('RECIBO_BANCOS') !== null) {
        vm_linea('AVISO', 'RECIBO_BANCOS (texto de bancos escrito a mano, de antes de la migracion 041) esta activo: confirme que es de este municipio.');
    }

    // --- Pago en linea: lo que dice la propia aplicacion (la misma logica que decide si se ve el boton)
    try {
        require_once $ctx['raiz'] . '/business/class.pasarela.php';
        $prov  = \erpsoftsas\Pasarela::proveedor();
        $conf  = \erpsoftsas\Pasarela::configurado();
        $todos = $conf ? \erpsoftsas\Pasarela::botonVisible(null) : false;
        if (!$conf) {
            vm_linea('INFO', 'Pago en linea (' . $prov . '): credenciales incompletas, el boton de pago no se ofrece.');
        } elseif (!$todos) {
            vm_linea('INFO', 'Pago en linea (' . $prov . '): configurado en modo certificacion (el boton solo lo ven los usuarios de PASARELA_USUARIOS_PRUEBA).');
        } else {
            // Abierto a todos: lo grave es que apunte a pruebas o traiga los valores de relleno de la plantilla.
            $host = '';
            $relleno = false;
            if ($prov === 'PLACETOPAY' && class_exists('\PlacetoPay')) {
                $host = (string) parse_url((string) \PlacetoPay::baseUrl(), PHP_URL_HOST);
                $relleno = \PlacetoPay::login() === 'login_placetopay' || \PlacetoPay::secretKey() === 'secretkey_placetopay';
            }
            $pruebas = $host !== '' && preg_match('/(test|sandbox|pruebas|\bdev\b|\buat\b|staging)/i', $host) === 1;
            if ($pruebas || $relleno) {
                vm_linea('AVISO', 'Pago en linea (' . $prov . '): el boton lo ven TODOS los contribuyentes pero '
                    . ($relleno ? 'las credenciales son los valores de relleno de la plantilla' : 'apunta a un ambiente de PRUEBAS (' . vm_ascii($host) . ')')
                    . '. Hasta tener las de produccion de ESTE municipio, llene PASARELA_USUARIOS_PRUEBA con los usuarios que pueden probar.');
            } else {
                vm_linea($esPaipa ? 'OK' : 'INFO', 'Pago en linea (' . $prov . '): configurado y abierto a todos los contribuyentes'
                    . ($host !== '' ? ' (' . vm_ascii($host) . ')' : '') . ($esPaipa ? '.' : '. Confirme que las credenciales son las de este municipio y no las de Paipa.'));
            }
        }
    } catch (\Throwable $e) {
        vm_linea('INFO', 'Pago en linea: no se pudo evaluar (' . vm_mensaje_error($e) . ').');
    }

    // --- Cualquier otro parametro que mencione a Paipa (los secretos no se miran)
    if (!$esPaipa) {
        $mencionan = [];
        foreach ($P as $clave => $p) {
            if (!$p['activo'] || $p['valor'] === '') { continue; }
            if (preg_match('/(CLAVE|SECRET|LLAVE|PASS|TOKEN|LOGIN|KEY)/i', $clave)) { continue; }
            if (strpos($clave, 'CONTACTO_') === 0) { continue; }   // ya se reviso arriba, con su propio mensaje
            if (strpos(vm_norm($p['valor']), 'paipa') !== false) { $mencionan[] = $clave; }
        }
        if ($mencionan) {
            vm_linea('AVISO', 'Parametros que mencionan a Paipa: ' . implode(', ', $mencionan) . '. Revisarlos en Municipio y bancos.');
        }
    }
});

/* ===========================================================================
   6. ADMINISTRADORES
   =========================================================================== */

vm_seccion('6. ADMINISTRADORES');
vm_seguro('los administradores', function () {
    global $ctx;
    if (vm_base_vacia()) { return; }

    $rol = vm_fila("SELECT rol_Nombre, rol_Estado FROM conf_rol WHERE rol_Id = 1");
    if (!$rol) {
        vm_linea('FALLA', 'No existe el rol 1 (Administrador) en conf_rol.');
    } elseif ((int) $rol['rol_Estado'] !== 1) {
        vm_linea('FALLA', 'El rol 1 (' . vm_ascii($rol['rol_Nombre']) . ') esta inactivo: sus cuentas no pueden entrar.');
    } else {
        $c = vm_fila("SELECT COL_LENGTH('dbo.conf_rol', 'rol_Tipo') AS t");
        $tipo = null;
        if (!empty($c['t'])) {
            $f = vm_fila("SELECT rol_Tipo FROM conf_rol WHERE rol_Id = 1");
            $tipo = strtoupper(trim((string) ($f['rol_Tipo'] ?? '')));
        }
        if ($tipo !== null && $tipo !== 'ADMINISTRADOR') {
            vm_linea('FALLA', 'El rol 1 esta como tipo "' . vm_ascii($tipo) . '", no ADMINISTRADOR.');
        } else {
            vm_linea('OK', 'Rol 1 "' . vm_ascii($rol['rol_Nombre']) . '" existe y esta activo' . ($tipo !== null ? ' (tipo ADMINISTRADOR).' : '.'));
        }
    }

    $porRol = [];
    foreach (vm_consulta("SELECT r.rol_Id, r.rol_Nombre, SUM(CASE WHEN u.usu_Estado = 1 THEN 1 ELSE 0 END) AS activas
                            FROM conf_rol r LEFT JOIN conf_usuarios u ON u.usu_Rol = r.rol_Id
                           GROUP BY r.rol_Id, r.rol_Nombre ORDER BY r.rol_Id") as $f) {
        $porRol[] = (int) $f['rol_Id'] . ' ' . vm_ascii($f['rol_Nombre']) . '=' . (int) $f['activas'];
    }
    vm_dato('Cuentas activas', implode(', ', $porRol));

    $admins = vm_consulta("SELECT usu_Id, usu_Usuario, usu_Estado,
                                  CASE WHEN LTRIM(RTRIM(ISNULL(usu_NumeroDocumento, N''))) <> N'' THEN 1 ELSE 0 END AS con_doc,
                                  CASE WHEN LTRIM(RTRIM(ISNULL(usu_Correo, N''))) <> N'' THEN 1 ELSE 0 END AS con_correo
                             FROM conf_usuarios WHERE usu_Rol = 1 ORDER BY usu_Id");
    $activos = 0;
    foreach ($admins as $a) {
        $activo = (int) $a['usu_Estado'] === 1;
        if ($activo) { $activos++; }
        // Un usuario con forma de correo se enmascara (como lo hace la aplicacion al recuperar la clave).
        $u = trim((string) $a['usu_Usuario']);
        if (strpos($u, '@') !== false) {
            $p = explode('@', $u, 2);
            $u = substr($p[0], 0, 2) . '***@' . $p[1];
        }
        $texto = 'Administrador id ' . (int) $a['usu_Id'] . ' "' . vm_ascii($u) . '": ' . ($activo ? 'activo' : 'INACTIVO')
               . ', documento ' . ((int) $a['con_doc'] ? 'si' : 'NO') . ', correo ' . ((int) $a['con_correo'] ? 'si' : 'NO');
        if ($activo && (!(int) $a['con_doc'] || !(int) $a['con_correo'])) {
            vm_linea('AVISO', $texto . ': le falta el documento o el correo, asi que no puede recuperar su clave con "Has olvidado tu Contrasena?".');
        } else {
            vm_linea('INFO', $texto . '.');
        }
    }
    if ($activos === 0) {
        vm_linea('FALLA', 'No hay ningun administrador activo: nadie puede entrar a administrar el municipio. Use BD/crear_administrador.php.');
    }
});

/* ===========================================================================
   7. TABLAS DE FIRMA
   =========================================================================== */

vm_seccion('7. TABLAS DE FIRMA');
vm_seguro('las tablas de firma', function () {
    global $TABLAS_FIRMA;
    if (vm_base_vacia()) { return; }
    $marcas = implode(',', array_fill(0, count($TABLAS_FIRMA), '?'));
    $donde = [];
    foreach (vm_consulta("SELECT s.name AS esquema, t.name AS tabla
                            FROM sys.tables t INNER JOIN sys.schemas s ON s.schema_id = t.schema_id
                           WHERE t.name IN (" . $marcas . ") ORDER BY t.name, s.name", $TABLAS_FIRMA) as $f) {
        $donde[$f['tabla']][] = $f['esquema'];
    }
    $mal = false;
    foreach ($TABLAS_FIRMA as $t) {
        if (empty($donde[$t])) {
            vm_linea('FALLA', 'Falta la tabla ' . $t . ' (firma de declaraciones y del RIT).');
            $mal = true;
        } elseif (!in_array('dbo', $donde[$t], true)) {
            vm_linea('FALLA', 'La tabla ' . $t . ' esta en el esquema "' . vm_ascii(implode(', ', $donde[$t])) . '", no en dbo: las migraciones y el codigo la buscan en dbo.');
            $mal = true;
        } elseif (count($donde[$t]) > 1) {
            vm_linea('AVISO', 'La tabla ' . $t . ' existe en varios esquemas (' . vm_ascii(implode(', ', $donde[$t])) . '): puede estar duplicada.');
            $mal = true;
        }
    }
    if (!$mal) {
        vm_linea('OK', implode(', ', $TABLAS_FIRMA) . ': las cuatro en dbo.');
    }
});

vm_resumen_y_salir();

/* ===========================================================================
   Resumen final
   =========================================================================== */

function vm_resumen_y_salir()
{
    global $VM;
    $f = $VM['n']['FALLA'];
    $a = $VM['n']['AVISO'];

    echo "\n" . str_repeat('=', 70) . "\n";
    if ($f > 0) {
        echo 'RESULTADO: HAY FALLAS (' . $f . ' falla' . ($f > 1 ? 's' : '') . ', ' . $a . ' aviso' . ($a === 1 ? '' : 's') . ")\n";
    } elseif ($a > 0) {
        echo 'RESULTADO: SIN FALLAS, CON ' . $a . ' AVISO' . ($a > 1 ? 'S' : '') . " A REVISAR\n";
    } else {
        echo "RESULTADO: SIN OBSERVACIONES\n";
    }
    echo str_repeat('=', 70) . "\n";

    // Solo la primera frase de cada una: el detalle y que hacer estan arriba, en su seccion.
    foreach (['FALLA' => 'FALLAS (corregir antes de usar el municipio)', 'AVISO' => 'AVISOS (revisar)'] as $nivel => $titulo) {
        if (!$VM[$nivel]) { continue; }
        echo '  ' . $titulo . ":\n";
        foreach ($VM[$nivel] as $i => $texto) {
            $corto = preg_split('/\.\s/', $texto, 2)[0];
            $pref = '   ' . ($i + 1) . '. ';
            echo $pref . wordwrap(rtrim($corto, '.') . '.', 100 - strlen($pref), "\n" . str_repeat(' ', strlen($pref)), true) . "\n";
        }
    }
    echo "\nSolo lectura: no se cambio nada.\n";
    exit($f > 0 ? 1 : 0);
}
