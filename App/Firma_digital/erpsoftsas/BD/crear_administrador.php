<?php
/**
 * ============================================================================
 * CREA LA CUENTA DE ADMINISTRADOR DE UN MUNICIPIO NUEVO
 * ============================================================================
 *
 * PARA QUE EXISTE
 *
 * Un municipio que arranca sin usuarios (Macanal, 2026-10-28; despues Guateque
 * y Santa Rosa) no tiene con quien entrar a crear a los demas: el registro
 * publico (Inscribirse) siempre deja rol contribuyente. Este script crea UNA
 * cuenta de rol 1 (Administrador) en la base a la que apunta la propia
 * aplicacion, igual que BD/aplicar_migraciones.php: la conexion sale del
 * config.municipio.php del dominio donde se ejecuta, asi que no hace falta
 * saber ni escribir ninguna credencial de la base. Por lo mismo hay que
 * correrlo en el dominio de CADA municipio: la base es siempre la de ese
 * dominio, nunca otra.
 *
 * SOLO POR LINEA DE COMANDOS. NUNCA POR URL (por web contesta 403, y ademas la
 * carpeta BD/ esta oculta por el web.config).
 *
 * LA CLAVE: QUE NADIE LA CONOZCA  (--sin-clave, lo recomendado)
 *
 * La clave de un administrador no debe pasar por manos de quien instala el
 * municipio, ni por el chat, ni por un argumento que Plesk pueda guardar. Con
 * --sin-clave la cuenta nace con una clave ALEATORIA que este script genera,
 * convierte en huella y descarta en el mismo instante: nadie la ve y nadie la
 * puede recuperar. La persona entra por el camino normal del login:
 *
 *   1. Pulsa "Has olvidado tu Contrasena?" en la pagina de ingreso.
 *   2. Escribe su documento (el mismo de --documento, solo numeros).
 *   3. El sistema le envia una clave temporal al correo de la cuenta
 *      (el de --correo).
 *   4. Entra con su usuario o su correo y esa clave, y la cambia enseguida
 *      (menu de usuario > Cambiar contrasena).
 *
 * Por eso, con --sin-clave, el documento y el correo son OBLIGATORIOS y deben
 * estar bien escritos: son la unica puerta de la cuenta. El documento se busca
 * tal cual (el login de recuperacion lo compara como texto), asi que se pide
 * solo con numeros, sin puntos, sin guion, sin digito de verificacion y sin
 * ceros a la izquierda.
 *
 * Y como el envio del correo no se puede dar por hecho: si el servidor no logra
 * enviarlo, la pantalla de recuperacion igual dice "Correo enviado" (la
 * aplicacion no revisa si salio). Al crear la cuenta hay que confirmar con la
 * persona que el correo le llego y que pudo entrar.
 *
 * --clave=<clave> existe por si hiciera falta una cuenta con clave conocida
 * (una instalacion local, por ejemplo), pero NO se recomienda: la clave viaja
 * en los argumentos y, si la tarea de Plesk se guarda, queda a la vista.
 *
 * USO  (todas las opciones van como --nombre=valor, sin espacios alrededor del =)
 *
 *   --usuario=<usuario>       con el que entra. Sin espacios; hasta 100 caracteres.
 *   --correo=<correo>         el institucional de la persona (ahi llega la clave temporal).
 *   --documento=<numero>      cedula o NIT: solo numeros (ver arriba).
 *   --tipo-documento=<tipo>   CC (por defecto), CE, PASAPORTE o NIT.
 *   --nombres=<texto>         opcional; por defecto se usa el usuario.
 *   --apellidos=<texto>       opcional.
 *   --sin-clave               la clave queda al azar y nadie la conoce (recomendado).
 *   --clave=<clave>           alternativa a --sin-clave (no recomendada).
 *   --crear                   SIN esto solo se revisa y NO se escribe nada.
 *   --ayuda                   muestra este resumen.
 *
 *   php BD/crear_administrador.php --usuario=jperez --correo=persona@correo-institucional.example --documento=12345678 --sin-clave
 *   php BD/crear_administrador.php --usuario=jperez --correo=persona@correo-institucional.example --documento=12345678 --sin-clave --crear
 *
 * EN PLESK  (en el dominio del municipio, una vez por cada administrador)
 *
 *   1. Tareas programadas (Herramientas de desarrollo) del dominio del
 *      municipio > Agregar tarea.
 *   2. Tipo de tarea: "Ejecutar un script PHP".
 *   3. Ruta del script: la misma de BD/aplicar_migraciones.php con el nombre
 *      cambiado (termina en App/Firma_digital/erpsoftsas/BD/crear_administrador.php).
 *   4. Version de PHP: 8.3, como en las migraciones.
 *   5. Argumentos: lo que va DESPUES de "php BD/crear_administrador.php" en los
 *      ejemplos de arriba (empieza en --usuario=...), en una sola linea. Un valor
 *      con espacios va entre comillas dobles: --nombres="Maria Perez".
 *   6. Pulsar "Ejecutar ahora" y leer la salida. NUNCA "Aceptar": Aceptar GUARDA
 *      la tarea con sus argumentos (con --clave, la clave quedaria escrita en
 *      Plesk; con --sin-clave, el documento y el correo de la persona).
 *   7. Primera pasada SIN --crear. Confirmar que "Base de datos" es la del
 *      municipio y que el correo y el documento estan bien escritos.
 *   8. Segunda pasada: agregar --crear al final de los argumentos y "Ejecutar
 *      ahora" otra vez. La salida confirma que se creo.
 *   9. Salir de la pantalla con "Cancelar": la tarea no debe quedar guardada.
 *
 * QUE COMPRUEBA ANTES DE CREAR  (si algo falla, no escribe nada y sale con error)
 *
 *   - Que la configuracion del dominio trae las cuatro DB_PROD_*: sin ellas la
 *     aplicacion cae a una base de respaldo embebida en el codigo, y escribir
 *     ahi seria crear la cuenta en la base equivocada.
 *   - Que existe el rol 1, esta activo y (con la migracion 040) es de tipo
 *     ADMINISTRADOR.
 *   - Que la migracion 040 (roles y permisos por accion) esta aplicada.
 *   - Que el usuario, el correo y el documento no los usa otra cuenta (las
 *     mismas reglas de la pantalla Usuarios) y que el usuario, si parece un
 *     correo, es ese mismo correo (el login trata como correo todo lo que lo
 *     parezca, y la cuenta no podria entrar con su usuario).
 *
 * QUE NUNCA HACE
 *
 *   - Imprimir la clave ni su huella.
 *   - Tocar cuentas existentes: solo inserta una.
 *   - Crear un contribuyente: las cuentas de la Alcaldia no lo llevan.
 *
 * ESTE ARCHIVO NO SE BORRA DEL SERVIDOR. Esta versionado en Git: borrarlo
 * choca con el siguiente "Pull". No es un riesgo dejarlo: solo corre por linea
 * de comandos y BD/ no se sirve por web.
 * ============================================================================
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo se ejecuta por linea de comandos.');
}

/* ---------------------------------------------------------------------------
   Utilidades
   --------------------------------------------------------------------------- */

/* Un fatal de PHP nunca debe salir mudo (globals.php apaga display_errors y,
   por linea de comandos, Plesk solo diria "se completo con errores"). */
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        echo "\nERROR FATAL de PHP: " . ca_limpiar($e['message']) . "\n";
    }
});

/* Texto sin tildes ni enie: la salida de Plesk no siempre respeta UTF-8. */
function ca_ascii($s)
{
    $s = strtr((string) $s, [
        "\xC3\xA1" => 'a', "\xC3\xA9" => 'e', "\xC3\xAD" => 'i', "\xC3\xB3" => 'o', "\xC3\xBA" => 'u',
        "\xC3\xBC" => 'u', "\xC3\xB1" => 'n', "\xC3\x81" => 'A', "\xC3\x89" => 'E', "\xC3\x8D" => 'I',
        "\xC3\x93" => 'O', "\xC3\x9A" => 'U', "\xC3\x9C" => 'U', "\xC3\x91" => 'N',
    ]);
    return preg_replace('/[^\x20-\x7E]/', '?', $s);
}

/* Nada de lo que se imprime puede llevar la clave de la base ni el nombre de su usuario. */
function ca_limpiar($texto)
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
function ca_mensaje_error($e)
{
    $m = ca_limpiar($e->getMessage());
    if (preg_match_all('/\[message\]\s*=>\s*(.+)/', $m, $mm) && !empty($mm[1])) {
        // El driver repite cada error con clave numerica y con clave "message": se juntan
        // los distintos, sin los prefijos [Microsoft][ODBC Driver ...][SQL Server].
        $mensajes = [];
        foreach ($mm[1] as $linea) {
            $linea = trim(preg_replace('/^(\[[^\]]*\])+/', '', trim($linea)));
            if ($linea !== '') { $mensajes[$linea] = true; }
        }
        $m = implode(' | ', array_keys($mensajes));
    }
    return substr(ca_ascii(preg_replace('/\s+/', ' ', $m)), 0, 400);
}

function ca_largo($s)
{
    return function_exists('mb_strlen') ? mb_strlen((string) $s, 'UTF-8') : strlen((string) $s);
}

/* "co***@dominio.com", como lo muestra la propia aplicacion al recuperar la clave. */
function ca_correo_oculto($correo)
{
    $p = explode('@', (string) $correo);
    if (count($p) !== 2) { return '(correo sin formato valido)'; }
    return ca_ascii(substr($p[0], 0, 2)) . '***@' . ca_ascii($p[1]);
}

/* Solo los ultimos tres digitos. */
function ca_documento_oculto($doc)
{
    $doc = (string) $doc;
    return str_repeat('*', max(0, strlen($doc) - 3)) . substr($doc, -3);
}

function ca_linea($marca, $texto)
{
    echo '  ' . str_pad('[' . $marca . ']', 8) . $texto . "\n";
}

function ca_salir($texto, $codigo = 1)
{
    echo $texto . "\n";
    exit($codigo);
}

/* La primera fila de una consulta con parametros, o null. */
function ca_fila($con, $sql, array $params = [])
{
    $f = $con->obnerFila($con->consultar($sql, $params));
    return $f ? $f : null;
}

/* Tipo de documento (valores de conf_usuarios.usu_IdTipoDocumento). */
$CA_TIPOS = [1 => 'C.C.', 3 => 'C.E.', 4 => 'Pasaporte', 5 => 'NIT'];

function ca_tipo_documento($texto)
{
    $t = strtoupper(str_replace('.', '', trim((string) $texto)));
    $mapa = ['CC' => 1, '1' => 1, 'CE' => 3, '3' => 3, 'PA' => 4, 'PASAPORTE' => 4, '4' => 4, 'NIT' => 5, '5' => 5];
    return isset($mapa[$t]) ? $mapa[$t] : null;
}

/*
 * Que otras cuentas ya usan este correo, documento o usuario. Mismas reglas que
 * ControladorUsuarios::_cuentaRepetida (la pantalla Usuarios): el correo sin
 * importar mayusculas ni espacios, el documento como numero, el usuario tal cual.
 * No se incluye ese controlador porque se ejecuta al cargarlo.
 *
 * @return array ['correo' => id, 'documento' => id, 'usuario' => id] solo con lo repetido
 */
function ca_repetidos($con, $correo, $documento, $usuario)
{
    $r = [];

    $f = ca_fila($con,
        "SELECT TOP 1 usu_Id FROM conf_usuarios WHERE LOWER(LTRIM(RTRIM(usu_Correo))) = ?",
        [function_exists('mb_strtolower') ? mb_strtolower(trim($correo), 'UTF-8') : strtolower(trim($correo))]
    );
    if ($f) { $r['correo'] = (int) $f['usu_Id']; }

    if ($documento !== null && $documento !== '') {
        $f = ca_fila($con,
            "SELECT TOP 1 usu_Id FROM conf_usuarios
              WHERE TRY_CONVERT(DECIMAL(38,0), LTRIM(RTRIM(usu_NumeroDocumento))) = CONVERT(DECIMAL(38,0), ?)",
            [$documento]
        );
        if ($f) { $r['documento'] = (int) $f['usu_Id']; }
    }

    $f = ca_fila($con, "SELECT TOP 1 usu_Id FROM conf_usuarios WHERE usu_Usuario = ?", [trim($usuario)]);
    if ($f) { $r['usuario'] = (int) $f['usu_Id']; }

    return $r;
}

function ca_uso()
{
    echo "Uso (todas las opciones como --nombre=valor):\n"
       . "  php BD/crear_administrador.php --usuario=<usuario> --correo=<correo> --documento=<numero> --sin-clave [--crear]\n\n"
       . "  --usuario=<usuario>       con el que entra; sin espacios; hasta 100 caracteres\n"
       . "  --correo=<correo>         el institucional de la persona (ahi llega su clave temporal)\n"
       . "  --documento=<numero>      cedula o NIT: solo numeros, sin puntos, sin guion, sin digito\n"
       . "                            de verificacion y sin ceros a la izquierda\n"
       . "  --tipo-documento=<tipo>   CC (por defecto), CE, PASAPORTE o NIT\n"
       . "  --nombres=<texto>         opcional (por defecto, el usuario)\n"
       . "  --apellidos=<texto>       opcional\n"
       . "  --sin-clave               la clave queda al azar y nadie la conoce (recomendado):\n"
       . "                            la persona entra con \"Has olvidado tu Contrasena?\"\n"
       . "  --clave=<clave>           alternativa a --sin-clave (NO recomendada: viaja en los argumentos)\n"
       . "  --crear                   sin esto solo se revisa y NO se escribe nada\n"
       . "  --ayuda                   este resumen\n\n"
       . "En Plesk: Tareas programadas > Ejecutar un script PHP > \"Ejecutar ahora\".\n"
       . "NUNCA \"Aceptar\": guardaria la tarea con sus argumentos.\n";
}

/* ---------------------------------------------------------------------------
   Argumentos
   --------------------------------------------------------------------------- */

$permitidas = [
    'usuario' => 'valor', 'correo' => 'valor', 'documento' => 'valor', 'tipo-documento' => 'valor',
    'nombres' => 'valor', 'apellidos' => 'valor', 'clave' => 'valor',
    'sin-clave' => 'marca', 'crear' => 'marca', 'ayuda' => 'marca',
];

// Un campo de argumentos en blanco puede llegar como un argumento vacio: no cuenta.
$argumentos = array_values(array_filter(array_slice($argv, 1), function ($a) { return trim($a) !== ''; }));

$opc = [];
$sueltos = [];
$errores = [];
$malEscritas = [];   // opciones que ya se reportaron: no se repite que "falta"
foreach ($argumentos as $a) {
    if (strpos($a, '--') !== 0) { $sueltos[] = $a; continue; }
    $partes = explode('=', substr($a, 2), 2);
    $nombre = $partes[0];
    // Solo se nombra la opcion, nunca su valor: puede ser la clave. Y como un
    // "--clave:Secreta1" (dos puntos en vez de =) lleva la clave PEGADA al nombre,
    // de lo que se escribio solo se repiten las letras y guiones iniciales.
    if (!isset($permitidas[$nombre])) {
        preg_match('/^[A-Za-z-]{0,30}/', $nombre, $ini);
        $errores[] = 'Opcion desconocida: --' . $ini[0] . ($ini[0] === $nombre ? '' : '... (el resto no se muestra)');
        continue;
    }
    if (isset($opc[$nombre])) { $errores[] = 'La opcion --' . $nombre . ' esta repetida.'; continue; }
    if ($permitidas[$nombre] === 'marca') {
        if (isset($partes[1])) { $errores[] = 'La opcion --' . $nombre . ' no lleva valor.'; $malEscritas[$nombre] = true; continue; }
        $opc[$nombre] = true;
    } else {
        if (!isset($partes[1]) || trim($partes[1]) === '') {
            $errores[] = 'La opcion --' . $nombre . ' necesita un valor: --' . $nombre . '=<valor> (sin espacios alrededor del =).';
            $malEscritas[$nombre] = true;
            continue;
        }
        $opc[$nombre] = $partes[1];
    }
}

if (!empty($opc['ayuda'])) {
    ca_uso();
    exit(0);
}
if (!$argumentos) {
    ca_uso();
    exit(1);
}
if (count($sueltos) === 3 && !$errores && array_diff(array_keys($opc), ['crear']) === []) {
    // El formato viejo era: <usuario> <correo> <clave> [--crear]. Ya no existe.
    echo "Este formato (usuario correo clave, sin opciones) ya no existe: ahora cada dato lleva su nombre.\n\n";
    ca_uso();
    exit(1);
}
if ($sueltos) {
    $errores[] = 'Hay argumentos sin nombre; cada dato va como --opcion=valor.';
}

$usuario   = trim(isset($opc['usuario']) ? $opc['usuario'] : '');
$correo    = trim(isset($opc['correo']) ? $opc['correo'] : '');
$documento = trim(isset($opc['documento']) ? $opc['documento'] : '');
$nombres   = trim(isset($opc['nombres']) ? $opc['nombres'] : '');
$apellidos = trim(isset($opc['apellidos']) ? $opc['apellidos'] : '');
$sinClave  = !empty($opc['sin-clave']);
$clave     = isset($opc['clave']) ? $opc['clave'] : null;   // tal cual: no se recorta
$crear     = !empty($opc['crear']);
$tipo      = null;

if ($usuario === '') {
    if (!isset($malEscritas['usuario'])) { $errores[] = 'Falta --usuario=<usuario>.'; }
} elseif (ca_largo($usuario) > 100 || preg_match('/[\s<>\x00-\x1F\x7F]/', $usuario)) {
    $errores[] = 'El usuario no puede llevar espacios ni < >, y tiene hasta 100 caracteres.';
}

if ($correo === '') {
    if (!isset($malEscritas['correo'])) { $errores[] = 'Falta --correo=<correo>.'; }
} elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($correo) > 254) {
    $errores[] = 'El correo no es valido.';
} elseif ($usuario !== '' && filter_var($usuario, FILTER_VALIDATE_EMAIL) && strtolower($usuario) !== strtolower($correo)) {
    // El login trata como correo todo lo que lo parezca: un usuario con forma de
    // correo que no sea el de la cuenta nunca podria entrar escribiendolo.
    $errores[] = 'El usuario parece un correo pero no es el de la cuenta: use un usuario sin @ o el mismo correo.';
}

if ($sinClave && $clave !== null) {
    $errores[] = 'Use --sin-clave o --clave=<clave>, no las dos.';
} elseif (!$sinClave && $clave === null && !isset($malEscritas['clave']) && !isset($malEscritas['sin-clave'])) {
    $errores[] = 'Falta decidir la clave: --sin-clave (recomendado, la persona la crea con "Has olvidado tu Contrasena?") o --clave=<clave>.';
} elseif ($clave !== null && (strlen($clave) < 8 || !preg_match('/[A-Z]/', $clave) || !preg_match('/[a-z]/', $clave) || !preg_match('/\d/', $clave))) {
    $errores[] = 'La clave debe tener minimo 8 caracteres, una mayuscula, una minuscula y un numero.';
}

if ($documento === '') {
    if ($sinClave && !isset($malEscritas['documento'])) {
        $errores[] = 'Con --sin-clave falta --documento=<numero>: la persona entra con su documento.';
    }
    if (isset($opc['tipo-documento'])) {
        $errores[] = '--tipo-documento solo se usa junto con --documento.';
    }
} else {
    // La misma regla de la pantalla Usuarios (_leerCuenta) y la columna INT del contribuyente.
    if (!ctype_digit($documento) || strlen($documento) > 10 || (float) $documento > 2147483647 || (int) $documento <= 0) {
        $errores[] = 'El documento va solo con numeros: sin puntos, sin guion y sin el digito de verificacion (maximo 2147483647).';
    } elseif ($documento[0] === '0') {
        $errores[] = 'El documento va sin ceros a la izquierda (la recuperacion de clave lo compara tal cual).';
    }
    $tipo = isset($opc['tipo-documento']) ? ca_tipo_documento($opc['tipo-documento']) : 1;
    if ($tipo === null) {
        $errores[] = 'Tipo de documento no valido: use CC, CE, PASAPORTE o NIT.';
    }
}

foreach ([['nombres', $nombres], ['apellidos', $apellidos]] as $par) {
    if (ca_largo($par[1]) > 500 || preg_match('/[<>\x00-\x1F\x7F]/', $par[1])) {
        $errores[] = 'Los ' . $par[0] . ' no pueden llevar < > y tienen hasta 500 caracteres.';
    }
}
if ($nombres === '') { $nombres = $usuario; }

if ($errores) {
    echo "No se puede continuar:\n";
    foreach ($errores as $e) { echo '  - ' . $e . "\n"; }
    echo "\n";
    ca_uso();
    exit(1);
}

/* ---------------------------------------------------------------------------
   Conexion: la de la propia aplicacion
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

/*
 * Sin las cuatro DB_PROD_* la clase de conexion cae a una base de respaldo que
 * trae embebida el codigo. Para consultar daria igual de confuso; para ESCRIBIR
 * una cuenta de administrador seria crearla en una base que no es del municipio.
 */
$faltan = [];
foreach (['DB_PROD_SERVER', 'DB_PROD_USER', 'DB_PROD_PASS', 'DB_PROD_NAME'] as $c) {
    if (!defined($c) || trim((string) constant($c)) === '') { $faltan[] = $c; }
}
if ($faltan) {
    ca_salir("No se hizo nada: config.municipio.php no define " . implode(', ', $faltan) . ".\n"
           . "Sin eso la aplicacion se conecta a una base de respaldo embebida en el codigo.\n"
           . "Revise que el config del municipio este un nivel arriba de erpsoftsas/ (o dentro, solo en desarrollo).");
}

try {
    $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
    $con->consultar("SET LOCK_TIMEOUT 15000");
    $donde = ca_fila($con, "SELECT DB_NAME() AS base, @@SERVERNAME AS servidor");
} catch (\Throwable $e) {
    ca_salir("No se pudo conectar a la base de datos del municipio:\n  " . ca_mensaje_error($e));
}

echo 'CREAR ADMINISTRADOR (rol 1)   modo: ' . ($crear ? 'CREAR' : 'REVISION, no escribe nada') . "\n";
echo 'Base de datos: ' . ca_ascii($donde['base']) . '   (servidor ' . ca_ascii($donde['servidor']) . ")\n";
echo ">>> Confirme que esta es la base de ESTE municipio antes de crear nada. <<<\n\n";

/* ---------------------------------------------------------------------------
   Comprobaciones
   --------------------------------------------------------------------------- */

$bloqueos = [];
echo "Comprobaciones\n";

try {
    $t = ca_fila($con, "SELECT OBJECT_ID('dbo.conf_usuarios') AS u, OBJECT_ID('dbo.conf_rol') AS r, OBJECT_ID('dbo.conf_migraciones') AS m");
    if (empty($t['u']) || empty($t['r'])) {
        ca_linea('FALLA', 'Esta base no tiene las tablas conf_usuarios y conf_rol: no es la base de la aplicacion.');
        ca_salir("\nNo se creo nada.");
    }

    // Lo que trae la migracion 040: el tipo de cada rol y el catalogo de permisos.
    $cols040 = ca_fila($con, "SELECT COL_LENGTH('dbo.conf_rol', 'rol_Tipo') AS t, COL_LENGTH('dbo.conf_submodulo', 'subMod_Clave') AS c");

    // Rol 1: existe, activo y (con la 040) de tipo ADMINISTRADOR.
    $rol = ca_fila($con, "SELECT rol_Nombre, rol_Estado FROM conf_rol WHERE rol_Id = 1");
    if (!$rol) {
        ca_linea('FALLA', 'No existe el rol 1 (Administrador) en conf_rol.');
        $bloqueos[] = 'rol 1';
    } elseif ((int) $rol['rol_Estado'] !== 1) {
        ca_linea('FALLA', 'El rol 1 (' . ca_ascii($rol['rol_Nombre']) . ') esta inactivo: una cuenta suya no podria entrar.');
        $bloqueos[] = 'rol 1';
    } else {
        $tipoRol = null;
        if (!empty($cols040['t'])) {
            $f = ca_fila($con, "SELECT rol_Tipo FROM conf_rol WHERE rol_Id = 1");
            $tipoRol = strtoupper(trim((string) ($f['rol_Tipo'] ?? '')));
        }
        if ($tipoRol !== null && $tipoRol !== 'ADMINISTRADOR') {
            ca_linea('FALLA', 'El rol 1 (' . ca_ascii($rol['rol_Nombre']) . ') esta como tipo "' . ca_ascii($tipoRol) . '", no ADMINISTRADOR.');
            $bloqueos[] = 'tipo del rol 1';
        } else {
            ca_linea('OK', 'Rol 1 "' . ca_ascii($rol['rol_Nombre']) . '" existe y esta activo' . ($tipoRol !== null ? ' (tipo ADMINISTRADOR).' : '.'));
        }
    }

    // Migracion 040: la misma condicion con que la aplicacion decide si hay catalogo de permisos.
    $registrada040 = false;
    if (!empty($t['m'])) {
        $f = ca_fila($con, "SELECT COUNT(*) AS n FROM dbo.conf_migraciones WHERE mig_Nombre = '040_roles_y_permisos_por_accion'");
        $registrada040 = $f && (int) $f['n'] > 0;
    }
    if ($registrada040 && !empty($cols040['t']) && !empty($cols040['c'])) {
        ca_linea('OK', 'Migracion 040 (roles y permisos por accion) aplicada.');
    } else {
        ca_linea('FALLA', 'La migracion 040 (roles y permisos por accion) no esta aplicada en esta base.');
        echo "          Aplique primero las migraciones (BD/aplicar_migraciones.php) y vuelva a correr este script.\n";
        $bloqueos[] = 'migracion 040';
    }

    // Usuario, correo y documento libres.
    $rep = ca_repetidos($con, $correo, $documento, $usuario);
    foreach (['usuario' => 'El usuario', 'correo' => 'El correo', 'documento' => 'El documento'] as $campo => $rotulo) {
        if (isset($rep[$campo])) {
            ca_linea('FALLA', $rotulo . ' ya lo usa otra cuenta (id ' . $rep[$campo] . ').');
            $bloqueos[] = $campo . ' repetido';
        } elseif ($campo !== 'documento' || $documento !== '') {
            ca_linea('OK', $rotulo . ' esta libre.');
        }
    }

    $n = ca_fila($con,
        "SELECT COUNT(*) AS n, SUM(CASE WHEN usu_Rol = 1 AND usu_Estado = 1 THEN 1 ELSE 0 END) AS admins
           FROM conf_usuarios"
    );
    ca_linea('INFO', 'Cuentas existentes: ' . (int) $n['n'] . '   (administradores activos: ' . (int) $n['admins'] . ')');

} catch (\Throwable $e) {
    ca_salir("\nNo se pudo revisar la base: " . ca_mensaje_error($e) . "\nNo se creo nada.", 2);
}

if ($bloqueos) {
    ca_salir("\nNo se puede crear la cuenta hasta corregir lo anterior. No se creo nada.");
}

/* ---------------------------------------------------------------------------
   Lo que se va a crear
   --------------------------------------------------------------------------- */

echo "\nLa cuenta (rol 1, Administrador):\n";
echo '  Usuario ....... ' . ca_ascii($usuario) . "\n";
echo '  Nombre ........ ' . ca_ascii(trim($nombres . ' ' . $apellidos)) . "\n";
// En la revision se muestra completo para que quien corre el script lo lea con cuidado;
// al crear, enmascarado.
echo '  Correo ........ ' . ($crear ? ca_correo_oculto($correo) : ca_ascii($correo)) . "\n";
if ($documento !== '') {
    echo '  Documento ..... ' . $CA_TIPOS[$tipo] . ' ' . ($crear ? ca_documento_oculto($documento) : $documento) . "\n";
} else {
    echo "  Documento ..... (ninguno: esta cuenta NO podra usar \"Has olvidado tu Contrasena?\")\n";
}
echo '  Clave ......... ' . ($sinClave
    ? "la define el sistema AL AZAR y nadie la conoce\n"
    : "la indicada en --clave (no se muestra)\n");

if (!$crear) {
    echo "\nRevision terminada: no se escribio nada.\n";
    echo "Si la base de datos y los datos de arriba son los correctos, repita el comando agregando --crear\n";
    echo "al final de los argumentos.\n";
    exit(0);
}

/* ---------------------------------------------------------------------------
   Crear
   --------------------------------------------------------------------------- */

try {
    $claveReal = $sinClave ? bin2hex(random_bytes(32)) : $clave;
} catch (\Throwable $e) {
    ca_salir("\nNo se pudo generar una clave aleatoria segura en este servidor. No se creo nada.", 2);
}
// La misma huella de DAOGeneral::hashClave (business/DAO/class.DAO.php): sha1 en
// mayusculas. La clave en claro no se guarda ni se imprime en ninguna parte.
$huella = strtoupper(sha1($claveReal));
unset($claveReal, $clave, $opc);

$idNueva = 0;
try {
    $con->begin();

    // El mismo candado con que la aplicacion crea cuentas y contribuyentes: si la
    // pantalla Usuarios crea a la vez una cuenta con el mismo documento, una espera.
    $b = ca_fila($con,
        "SET NOCOUNT ON; DECLARE @r INT;
         EXEC @r = sp_getapplock @Resource = 'erp_contribuyente_documento', @LockMode = 'Exclusive',
                                 @LockOwner = 'Transaction', @LockTimeout = 15000;
         SELECT @r AS r;"
    );
    if (!$b || (int) $b['r'] < 0) {
        throw new \RuntimeException('No se obtuvo el candado de documentos.');
    }

    // Otra vez, ya con el candado.
    $rep = ca_repetidos($con, $correo, $documento, $usuario);
    if ($rep) {
        $con->rollback();
        ca_salir("\nMientras tanto otra cuenta tomo el " . implode(', el ', array_keys($rep)) . ". No se creo nada.");
    }

    $con->consultar(
        "INSERT INTO conf_usuarios
             (usu_Nombres, usu_Apellidos, usu_Usuario, usu_IdTipoDocumento, usu_NumeroDocumento,
              usu_Correo, usu_Password, usu_Rol, usu_Estado, usu_FechaCreacion, usu_FechaActualizacion)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1, GETDATE(), GETDATE())",
        [$nombres, $apellidos, $usuario, $tipo, ($documento === '' ? null : $documento), $correo, $huella]
    );

    $nueva = ca_fila($con,
        "SELECT usu_Id, usu_Rol, usu_Estado, LEN(usu_Password) AS largo FROM conf_usuarios WHERE usu_Usuario = ?",
        [$usuario]
    );
    if (!$nueva || (int) $nueva['usu_Rol'] !== 1 || (int) $nueva['usu_Estado'] !== 1 || (int) $nueva['largo'] !== 40) {
        throw new \RuntimeException('La cuenta no quedo como se esperaba.');
    }
    $idNueva = (int) $nueva['usu_Id'];

    $con->commit();
} catch (\Throwable $e) {
    try { $con->rollback(); } catch (\Throwable $e2) { /* ya no habia transaccion */ }
    ca_salir("\nNo se pudo crear la cuenta y no quedo nada guardado:\n  " . ca_mensaje_error($e), 2);
}

echo "\nADMINISTRADOR CREADO\n";
echo '  Id ............ ' . $idNueva . "\n";
echo '  Base .......... ' . ca_ascii($donde['base']) . "\n";

if ($sinClave) {
    echo "\nComo entra la persona:\n";
    echo "  1. Abre la pagina de ingreso del municipio y pulsa \"Has olvidado tu Contrasena?\".\n";
    echo "  2. Escribe su documento, solo numeros (el mismo de --documento).\n";
    echo "  3. Le llega una clave temporal al correo " . ca_correo_oculto($correo) . "\n";
    echo "     (si no aparece en unos minutos, que revise la carpeta de spam).\n";
    echo "  4. Entra con su usuario o su correo y esa clave, y la cambia enseguida:\n";
    echo "     menu de usuario > Cambiar contrasena.\n";
    echo "Confirme con la persona que el correo llego y que pudo entrar. Si no llega (el correo se\n";
    echo "escribio mal, o el servidor no logra enviarlo: la pantalla igual dice \"Correo enviado\"), la\n";
    echo "cuenta no tiene como entrar hasta que otro administrador corrija el correo o le cambie la\n";
    echo "clave en Usuarios.\n";
} else {
    echo "\nATENCION: la clave viajo en los argumentos de este comando. Si la tarea de Plesk se guardo\n";
    echo "(boton Aceptar), borrela ahora. Conviene que la persona cambie la clave en su primer ingreso\n";
    echo "(menu de usuario > Cambiar contrasena).\n";
}
echo "\nSalga de la pantalla de la tarea con \"Cancelar\": no debe quedar guardada.\n";
exit(0);
