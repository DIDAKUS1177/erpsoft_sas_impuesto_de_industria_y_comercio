<?php
/**
 * ============================================================================
 * CREA LA CUENTA DE ADMINISTRADOR DE UN MUNICIPIO NUEVO
 * ============================================================================
 *
 * Un municipio que arranca sin usuarios (Macanal, 2026-10-28) no tiene con
 * quien entrar a crear a los demas: el registro publico (Inscribirse) siempre
 * deja rol contribuyente. Este script crea UNA cuenta de rol 1 en la base a
 * la que apunta la propia aplicacion, igual que BD/aplicar_migraciones.php:
 * no hace falta saber ni escribir ninguna credencial de la base.
 *
 * SOLO POR LINEA DE COMANDOS. NUNCA POR URL. En Plesk: Herramientas de
 * desarrollo > Tareas programadas > "Ejecutar un script PHP" > "Ejecutar
 * ahora" (sin guardar la tarea, para que la clave no quede escrita).
 *
 *     php BD/crear_administrador.php <usuario> <correo> <clave>            solo mira
 *     php BD/crear_administrador.php <usuario> <correo> <clave> --crear    crea
 *
 * La clave se guarda con la misma huella que usa el login (sha1 en mayusculas)
 * y no se imprime. Conviene cambiarla en la primera entrada. Despues de usarlo,
 * borrar este archivo del servidor.
 * ============================================================================
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo se ejecuta por linea de comandos.');
}

$raiz = dirname(__DIR__);
if (empty($_SERVER['DOCUMENT_ROOT'])) {
    $_SERVER['DOCUMENT_ROOT'] = dirname($raiz);
}

require_once $raiz . '/business/globals.php';
require_once $raiz . '/business/class.conexionSqlServer.php';

ini_set('display_errors', '1');
error_reporting(E_ALL);
if (function_exists('sqlsrv_configure')) {
    sqlsrv_configure('WarningsReturnAsErrors', 0);
}

$crear = in_array('--crear', $argv, true);
$args = array_values(array_filter(array_slice($argv, 1), function ($a) {
    return strpos($a, '--') !== 0;
}));
if (count($args) !== 3) {
    exit("Uso: php BD/crear_administrador.php <usuario> <correo> <clave> [--crear]\n");
}
list($usuario, $correo, $clave) = $args;

if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    exit("El correo no es valido.\n");
}
if (strlen($clave) < 8 || !preg_match('/[A-Z]/', $clave) || !preg_match('/[a-z]/', $clave) || !preg_match('/\d/', $clave)) {
    exit("La clave debe tener minimo 8 caracteres, una mayuscula, una minuscula y un numero.\n");
}

$con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

$donde = $con->obnerFila($con->consultar("SELECT DB_NAME() AS base, @@SERVERNAME AS servidor"));
echo "Base de datos: " . $donde['base'] . "   (servidor " . $donde['servidor'] . ")\n";

$fila = $con->obnerFila($con->consultar(
    "SELECT COUNT(*) AS n,
            SUM(CASE WHEN usu_Rol = 1 AND usu_Estado = 1 THEN 1 ELSE 0 END) AS admins
       FROM conf_usuarios"
));
echo "Cuentas existentes: " . (int) $fila['n'] . "   (administradores activos: " . (int) $fila['admins'] . ")\n";

$repetida = $con->obnerFila($con->consultar(
    "SELECT usu_Id FROM conf_usuarios WHERE usu_Usuario = ? OR usu_Correo = ?",
    [$usuario, $correo]
));
if (!empty($repetida['usu_Id'])) {
    exit("Ya existe una cuenta con ese usuario o ese correo (id " . (int) $repetida['usu_Id'] . "). No se hizo nada.\n");
}

if (!$crear) {
    exit("Todo en orden. Para crearla, repetir el comando con --crear.\n");
}

// La misma huella de DAOGeneral::hashClave (business/DAO/class.DAO.php).
$huella = strtoupper(sha1($clave));
$con->consultar(
    "INSERT INTO conf_usuarios (usu_Nombres, usu_Apellidos, usu_Usuario, usu_Correo, usu_Password, usu_Rol, usu_Estado, usu_FechaCreacion, usu_FechaActualizacion)
     VALUES (?, N'', ?, ?, ?, 1, 1, GETDATE(), GETDATE())",
    [$usuario, $usuario, $correo, $huella]
);

$creada = $con->obnerFila($con->consultar(
    "SELECT usu_Id FROM conf_usuarios WHERE usu_Usuario = ? AND usu_Rol = 1 AND usu_Estado = 1",
    [$usuario]
));
echo !empty($creada['usu_Id'])
    ? "Administrador creado (id " . (int) $creada['usu_Id'] . "). Borre este archivo del servidor.\n"
    : "No se pudo confirmar la creacion. Revise la base.\n";
