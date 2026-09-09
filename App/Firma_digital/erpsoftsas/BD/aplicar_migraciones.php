<?php
/**
 * ============================================================================
 * APLICADOR DE MIGRACIONES
 * ============================================================================
 *
 * Corre los .sql de BD/migraciones/ contra la base a la que apunta la
 * aplicacion, y los registra en conf_migraciones.
 *
 * POR QUE EXISTE
 *
 * Hasta hoy no habia forma comoda de correr una migracion en produccion:
 * Plesk administra MySQL, pero esta aplicacion usa SQL Server y su panel solo
 * ofrece volcados -exportar, importar, copiar-, no una consola. Correr una
 * migracion exigia un cliente de SQL Server contra el servidor de produccion.
 *
 * El resultado previsible: al 2026-09-09 produccion iba SEIS migraciones atras
 * (025, 027, 028, 029, 030 y 031). No es descuido de nadie, es que no habia
 * camino. Este script es ese camino.
 *
 * SOLO POR LINEA DE COMANDOS. NUNCA POR URL.
 *
 * Un script que aplica DDL no puede quedar expuesto en un dominio publico: es
 * un archivo dentro del checkout que Plesk publica, asi que alcanzarlo por
 * navegador seria trivial. La guarda de abajo lo impide de raiz -no con una
 * contraseña, que se puede filtrar, sino porque por HTTP simplemente no
 * arranca-.
 *
 * En Plesk se ejecuta desde Herramientas de desarrollo > Tareas programadas,
 * como "Ejecutar un script PHP", con el boton "Ejecutar ahora".
 *
 * COMO SE USA
 *
 *     php BD/aplicar_migraciones.php                 lista lo que falta, NO aplica
 *     php BD/aplicar_migraciones.php --aplicar       aplica TODAS las que falten
 *     php BD/aplicar_migraciones.php --aplicar 030   aplica solo las que empiecen por 030
 *
 * Sin --aplicar no toca nada: primero se mira, despues se decide. Y se puede
 * dar la lista de las que se quieren, que es lo prudente en produccion: si el
 * nombre con que quedo registrada una migracion vieja no coincide con el de su
 * archivo -paso con la 027-, "todas las que falten" la volveria a correr.
 * Estan escritas para aguantarlo, pero no hay motivo para averiguarlo.
 *
 * SOBRE 'GO'
 *
 * No es SQL: es el separador de lotes de sqlcmd, y el driver lo rechaza. Aqui
 * se parte el archivo por las lineas que solo contienen GO y se manda cada
 * lote por separado, que es exactamente lo que hace sqlcmd.
 * ============================================================================
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo se ejecuta por linea de comandos.');
}

$raiz = dirname(__DIR__);

/*
 * globals.php arma la constante SERVER como DOCUMENT_ROOT . '/erpsoftsas', y
 * por linea de comandos DOCUMENT_ROOT viene VACIA: SERVER quedaba en
 * '/erpsoftsas' y no encontraba nada. Se rellena a partir de la propia
 * ubicacion de este archivo, que es la unica referencia fiable en CLI.
 *
 * Tiene un efecto util de paso: config.municipio.php se busca un nivel arriba
 * de /erpsoftsas -la ubicacion real en Plesk-, asi que el script se conecta a
 * la MISMA base que usa la aplicacion. No hay forma de equivocarse de base.
 */
if (empty($_SERVER['DOCUMENT_ROOT'])) {
    $_SERVER['DOCUMENT_ROOT'] = dirname($raiz);
}

require_once $raiz . '/business/globals.php';
require_once $raiz . '/business/class.conexionSqlServer.php';

/*
 * globals.php fuerza display_errors=0 -a proposito: un aviso de PHP impreso
 * antes de un json_encode corrompe la respuesta y deja las pantallas mudas-.
 * Pero AQUI no hay JSON ni navegador: solo se llega por linea de comandos, y
 * un fatal invisible convierte este script en lo peor posible, algo que toca
 * el esquema y no dice que paso.
 *
 * Costo una vuelta en el despliegue del 2026-09-09: el script imprimio la
 * linea de la base y murio en silencio. En Plesk solo se veia "se completo con
 * errores", sin una sola pista.
 */
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/* Los mensajes informativos de SQL Server (PRINT, "N filas afectadas") llegan
   como advertencias; sin esto el driver los trata como error y aborta una
   migracion que en realidad fue bien. Es la misma razon por la que hizo falta
   al restaurar copias. */
if (function_exists('sqlsrv_configure')) {
    sqlsrv_configure('WarningsReturnAsErrors', 0);
}

$aplicar = in_array('--aplicar', $argv, true);
$filtros = array_values(array_filter(array_slice($argv, 1), function ($a) {
    return strpos($a, '--') !== 0;
}));

$con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

/* Se le pregunta a la propia conexion en vez de leer la configuracion: asi lo
   que se imprime es la base donde REALMENTE se va a escribir. Antes de aplicar
   DDL conviene ver eso, no lo que uno cree que dice el archivo de config. */
$donde = $con->obnerFila($con->consultar(
    "SELECT DB_NAME() AS base, @@SERVERNAME AS servidor"
));
echo "Base de datos: " . $donde['base'] . "   (servidor " . $donde['servidor'] . ")\n\n";

/*
 * Lo que ya esta aplicado.
 *
 * Si conf_migraciones NO existe, esta base nunca vio el sistema de migraciones
 * y hay que empezar por la 000, que es justamente la que crea esa tabla. Se
 * dice con todas las letras en vez de morir con "Invalid object name": es la
 * diferencia entre saber que hacer y no saber que paso.
 */
$existe = $con->obnerFila($con->consultar(
    "SELECT OBJECT_ID('dbo.conf_migraciones') AS id"
));

if (empty($existe['id'])) {
    echo "La tabla conf_migraciones NO existe en esta base.\n";
    echo "Esta base nunca ha pasado por el sistema de migraciones.\n";
    echo "Hay que empezar por 000_registro_de_migraciones, que es la que la crea.\n\n";
    $aplicadas = [];
} else {
    $aplicadas = [];
    $stmt = $con->consultar("SELECT mig_Nombre FROM conf_migraciones");
    while ($f = $con->obnerFila($stmt)) { $aplicadas[trim($f['mig_Nombre'])] = true; }
    echo "Migraciones ya registradas: " . count($aplicadas) . "\n\n";
}

$archivos = glob($raiz . '/BD/migraciones/*.sql');
sort($archivos);

$pendientes = [];
foreach ($archivos as $ruta) {
    $nombre = basename($ruta, '.sql');

    if (isset($aplicadas[$nombre])) { continue; }

    if ($filtros) {
        $coincide = false;
        foreach ($filtros as $f) {
            if (strpos($nombre, $f) === 0) { $coincide = true; break; }
        }
        if (!$coincide) { continue; }
    }

    $pendientes[$nombre] = $ruta;
}

if (!$pendientes) {
    echo "No hay migraciones pendientes.\n";
    exit(0);
}

echo "PENDIENTES (" . count($pendientes) . "):\n";
foreach ($pendientes as $nombre => $ruta) { echo "  - $nombre\n"; }
echo "\n";

if (!$aplicar) {
    echo "Nada se aplico. Para aplicarlas, repetir con --aplicar\n";
    exit(0);
}

$fallos = 0;

foreach ($pendientes as $nombre => $ruta) {

    echo "==> $nombre\n";

    $sql = file_get_contents($ruta);

    /* Partir por las lineas que contienen solo GO. */
    $lotes = preg_split('/^\s*GO\s*;?\s*$/mi', $sql);

    $n = 0;
    foreach ($lotes as $lote) {
        if (trim($lote) === '') { continue; }
        $n++;
        try {
            $stmt = $con->consultar($lote);
            /* Consumir todos los resultados: un lote puede devolver varios y
               dejarlos sin leer deja la conexion a medias para el siguiente. */
            if ($stmt) { while (@$con->obtenerNextResult($stmt)) { /* seguir */ } }
        } catch (\Throwable $e) {
            echo "    ERROR en el lote $n: " . $e->getMessage() . "\n";
            $fallos++;
            /* No se sigue con esta migracion: los lotes siguientes suelen
               depender del anterior y encadenar errores solo confunde. */
            continue 2;
        }
    }

    /* Comprobar que quedo registrada. Cada .sql se registra a si mismo al
       final; si no aparece, algo se salto y hay que mirarlo. */
    $ok = $con->obnerFila($con->consultar(
        "SELECT mig_Nombre FROM conf_migraciones WHERE mig_Nombre = ?", [$nombre]
    ));

    echo $ok
        ? "    OK ($n lotes) y registrada\n"
        : "    ATENCION: corrio ($n lotes) pero NO quedo registrada en conf_migraciones\n";
}

echo "\n";
echo $fallos === 0
    ? "Terminado sin errores.\n"
    : "Terminado con $fallos error(es). Revisar arriba.\n";

exit($fallos === 0 ? 0 : 1);
