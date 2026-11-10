<?php
/*
 * Suite del contacto del municipio (migración 054, business/class.contacto.php).
 *
 *   docker exec -w /var/www/html/erpsoftsas erpsoftsas_web_completo php pruebas/probar_contacto.php
 *
 * SOLO contra la base LOCAL. Escribe en las tres filas CONTACTO_* de
 * conf_parametros (hace falta la migración 054) y al terminar las deja como
 * estaban, pase lo que pase. Se niega a correr contra una base que se llame
 * como una de producción (erpsofts_ind_comercio_*).
 *
 * Qué comprueba:
 *   A. los patrones: qué se acepta y qué no, con los bordes (150, 80 y 120
 *      caracteres; HTML; control; UTF-8 inválido), y que son LOS MISMOS que
 *      siembra la migración 054 en par_Patron;
 *   B. la lectura: la tabla manda, un valor raro escrito por SQL no se imprime,
 *      sin dato es null (nunca un valor de Paipa), el respaldo de la dependencia
 *      sale de las constantes de config.municipio.php;
 *   C. el JSON que va en un <script> (window.ERP_CONTACTO) no puede cerrarlo ni
 *      salirse de él.
 *
 * Las pruebas con constantes distintas corren en procesos hijos (una constante
 * de PHP no se puede volver a definir): este mismo archivo con --caso=<nombre>.
 */
if (PHP_SAPI !== 'cli') { exit("Solo por línea de comandos.\n"); }

$raiz = dirname(__DIR__);
if (empty($_SERVER['DOCUMENT_ROOT'])) { $_SERVER['DOCUMENT_ROOT'] = dirname($raiz); }

/* ---------- Procesos hijo: definen sus constantes y cuentan lo que ven ---------- */
$caso = null;
foreach ($argv as $a) { if (strpos($a, '--caso=') === 0) { $caso = substr($a, 7); } }

/*
 * Un hijo define SUS constantes ANTES de cargar globals.php: el config del
 * municipio las define con if(!defined()), así que la primera que llega gana y
 * el config de esta máquina (el del contenedor trae MUNICIPIO_CIUDAD = Paipa)
 * no se mete en la prueba. Las que el caso no pide quedan vacías.
 */
if ($caso !== null) {
    $c = ['MUNICIPIO_CIUDAD' => '', 'MUNICIPIO_SECRETARIA' => '', 'MUNICIPIO_DEPENDENCIA_TRIBUTARIA' => ''];
    $bdCaida = false;
    switch ($caso) {
        case 'sin_constantes':   break;
        case 'solo_secretaria':  $c['MUNICIPIO_SECRETARIA'] = 'SECRETARÍA DE HACIENDA'; break;
        case 'ambas':            $c['MUNICIPIO_SECRETARIA'] = 'Secretaría de Hacienda';
                                 $c['MUNICIPIO_DEPENDENCIA_TRIBUTARIA'] = 'Dirección de Impuestos'; break;
        case 'constante_hostil': $c['MUNICIPIO_SECRETARIA'] = '<script>alert(1)</script>'; break;
        case 'con_ciudad':       $c['MUNICIPIO_CIUDAD'] = 'Guateque'; break;
        case 'ciudad_vacia':     $c['MUNICIPIO_CIUDAD'] = '   '; break;
        // La pantalla de ingreso (paraIngreso) con y sin base:
        case 'ingreso':          $c['MUNICIPIO_SECRETARIA'] = 'SECRETARÍA DE HACIENDA'; break;
        case 'ingreso_bd_caida': $bdCaida = true; break;
        case 'ingreso_bd_caida_con_secretaria': $bdCaida = true; $c['MUNICIPIO_SECRETARIA'] = 'SECRETARÍA DE HACIENDA'; break;
        default: fwrite(STDERR, "caso desconocido\n"); exit(2);
    }
    foreach ($c as $nombre => $valor) { define($nombre, $valor); }
    if ($bdCaida) {
        // Una base que rechaza. TODAS las DB_PROD_* se fijan aquí: sin ellas la
        // conexión caería al respaldo de producción que trae class.conexionSqlServer.php.
        define('DB_PROD_SERVER', '127.0.0.1,9');
        define('DB_PROD_USER', 'sa');
        define('DB_PROD_PASS', 'no-importa');
        define('DB_PROD_NAME', 'no_existe');
    }
}

require_once $raiz . '/business/globals.php';
require_once $raiz . '/business/class.contacto.php';

if ($caso !== null) {
    $t0 = microtime(true);
    $datos = strpos($caso, 'ingreso') === 0 ? \erpsoftsas\Contacto::paraIngreso() : \erpsoftsas\Contacto::paraPantalla();
    if (strpos($caso, 'ingreso') === 0) { $datos['segundos'] = round(microtime(true) - $t0, 1); }
    echo json_encode($datos);
    exit(0);
}

/* ---------- Padre: la suite ---------- */
use erpsoftsas\Contacto;
use erpsoftsas\Parametros;

ini_set('display_errors', '1');
error_reporting(E_ALL);

$con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
$base = $con->obnerFila($con->consultar("SELECT DB_NAME() AS b"))['b'];
if (strpos($base, 'erpsofts_ind_comercio_') === 0) {
    exit("Me niego a correr contra '$base': parece una base de producción.\n");
}
echo "Base: $base\n";

$pasadas = 0; $fallidas = [];
function comprobar($descripcion, $obtenido, $esperado)
{
    global $pasadas, $fallidas;
    if ($obtenido === $esperado) { $pasadas++; return; }
    $fallidas[] = '  X ' . $descripcion . "\n      esperado: " . var_export($esperado, true)
                . "\n      obtenido: " . var_export($obtenido, true);
}

/* Las tres filas, como están ahora (se restauran al final). */
$claves = Contacto::CLAVES;
$antes = [];
$st = $con->consultar("SELECT par_Clave, par_Valor, par_Estado, par_FechaActualizacion FROM conf_parametros
                        WHERE par_Clave IN (?, ?, ?)", $claves);
while ($f = $con->obnerFila($st)) { $antes[$f['par_Clave']] = $f; }
if (count($antes) !== 3) { exit("Faltan las filas CONTACTO_* : aplique la migración 054 (--aplicar 054).\n"); }

register_shutdown_function(function () use ($con, $antes) {
    foreach ($antes as $clave => $f) {
        $fecha = $f['par_FechaActualizacion'] instanceof \DateTime ? $f['par_FechaActualizacion']->format('Y-m-d\TH:i:s') : null;
        $con->consultar("UPDATE conf_parametros SET par_Valor = ?, par_Estado = ?, par_FechaActualizacion = ? WHERE par_Clave = ?",
            [$f['par_Valor'], (int) $f['par_Estado'], $fecha, $clave]);
    }
    echo "(las tres filas CONTACTO_* quedaron como estaban)\n";
});

function fijar($dep, $tel, $cor, $estado = 1)
{
    global $con;
    foreach ([Contacto::CLAVE_DEPENDENCIA => $dep, Contacto::CLAVE_TELEFONO => $tel, Contacto::CLAVE_CORREO => $cor] as $clave => $valor) {
        $con->consultar("UPDATE conf_parametros SET par_Valor = ?, par_Estado = ? WHERE par_Clave = ?", [$valor, $estado, $clave]);
    }
    Parametros::olvidar();
}

/* ====================== A. LOS PATRONES ====================== */
$valido = [
    Contacto::CLAVE_DEPENDENCIA => [
        'Secretaría de Hacienda',
        'Secretaría de Hacienda - Dirección de Impuestos, Rentas y Jurisdicción Coactiva',
        "Hacienda & \"Rentas\" O'Neil / Tesorería (Ñandú)",
        str_repeat('B', 150),
        str_repeat('í', 150),            // 150 letras son 300 bytes: se cuentan letras
        'a',
    ],
    Contacto::CLAVE_TELEFONO => [
        '318 530 9285', '8 785 0000, 310 123 4567', '(8) 785 0000 / +57 310 123 4567', '310.123.4567', '3',
        str_repeat('3', 80),
    ],
    Contacto::CLAVE_CORREO => [
        'impuestos@paipa-boyaca.gov.co', 'a.b+c@dominio.gov.co', 'hacienda_2@guateque.gov.co', 'a@b.co',
        str_repeat('a', 100) . '@dominio.gov.co',
    ],
];
$invalido = [
    Contacto::CLAVE_DEPENDENCIA => [
        '<script>alert(1)</script>', 'Hacienda <b>', 'a > b', "Linea1\nLinea2", "con\ttab", "con\x7Fdel", "con\x00nulo",
        str_repeat('A', 151), str_repeat('í', 151),
        "Tesorer\xEDa de Guateque",       // Latin-1: UTF-8 inválido (reventaba en el driver)
        '',
    ],
    Contacto::CLAVE_TELEFONO => [
        'abc', '+()', '...', '310 123 4567 ext 2', '<b>3</b>', "310\n123", str_repeat('3', 81), '3;4', '',
    ],
    Contacto::CLAVE_CORREO => [
        'sin-arroba', 'a@b', 'a@b.c', 'a b@c.co', 'a..b@c.co', '.a@c.co', 'a.@c.co', 'a@c..co', '<a>@c.co',
        'a@c.co"onerror="', "a@c.co\n", 'a@c.co ', str_repeat('a', 110) . '@dominio.gov.co', '',
    ],
];
foreach ($valido as $clave => $lista) {
    foreach ($lista as $v) {
        comprobar("$clave acepta " . json_encode(mb_strimwidth($v, 0, 40, '…'), JSON_UNESCAPED_UNICODE), Contacto::esValido($clave, $v), true);
    }
}
foreach ($invalido as $clave => $lista) {
    foreach ($lista as $v) {
        comprobar("$clave rechaza " . json_encode(mb_strimwidth($v, 0, 40, '…'), JSON_UNESCAPED_UNICODE), Contacto::esValido($clave, $v), false);
    }
}
comprobar('una clave que no es de contacto no es válida', Contacto::esValido('RECAUDO_EAN', '7709998161047'), false);

comprobar('normalizar: quita los lados y deja un solo espacio en medio',
    Contacto::normalizar("  Tesorería \t  Municipal\n de   Guateque  "), 'Tesorería Municipal de Guateque');
comprobar('normalizar: solo espacios es vacío', Contacto::normalizar(" \n\t "), '');
comprobar('normalizar: UTF-8 inválido no revienta (se queda como llegó, recortado)', Contacto::normalizar("  a\xEDb "), "a\xEDb");
foreach ($claves as $clave) {
    comprobar("$clave tiene mensaje de formato propio", Contacto::mensajeFormato($clave) !== 'El valor no tiene el formato esperado.', true);
}

/* Los patrones de la clase son LOS MISMOS que la migración siembra en par_Patron. */
$sql = file_get_contents($raiz . '/BD/migraciones/054_contacto_del_municipio.sql');
foreach ($claves as $clave) {
    $ok = preg_match("/VALUES \('" . preg_quote($clave, '/') . "'.*?\n\s*'([^\n]+)'\);/s", $sql, $m) === 1;
    comprobar("la migración 054 siembra el patrón de $clave", $ok ? $m[1] : null, Contacto::PATRONES[$clave]);
}
foreach ($claves as $clave) {
    $f = $con->obnerFila($con->consultar("SELECT par_Patron FROM conf_parametros WHERE par_Clave = ?", [$clave]));
    comprobar("la base local tiene el patrón de $clave igual al de la clase", $f['par_Patron'], Contacto::PATRONES[$clave]);
    comprobar("el patrón de $clave cabe en par_Patron (VARCHAR 200)", strlen(Contacto::PATRONES[$clave]) <= 200, true);
}
comprobar('la migración registra su nota con 500 caracteres o menos',
    preg_match("/N'(Parámetros CONTACTO[^']*)'/u", $sql, $m) === 1 && mb_strlen($m[1]) <= 500, true);
comprobar('la migración usa la misma guarda de base que la 049',
    substr_count($sql, "DB_NAME() = 'erpsofts_ind_comercio_paip'"), 1);

/* ====================== B. LA LECTURA ====================== */
$hijo = function ($caso) {
    $salida = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --caso=' . $caso . ' 2>/dev/null');
    return json_decode((string) $salida, true);
};

fijar(null, null, null);
comprobar('sin dato y sin constantes: todo null (nunca un valor de Paipa)',
    $hijo('sin_constantes'), ['dependencia' => null, 'telefono' => null, 'correo' => null, 'ciudad' => null]);
comprobar('sin dato, con MUNICIPIO_SECRETARIA: la dependencia sale de la constante',
    $hijo('solo_secretaria')['dependencia'], 'SECRETARÍA DE HACIENDA');
comprobar('sin dato, con las dos constantes: se unen',
    $hijo('ambas')['dependencia'], 'Secretaría de Hacienda - Dirección de Impuestos');
comprobar('una constante con HTML no se acepta como respaldo',
    $hijo('constante_hostil')['dependencia'], null);
comprobar('el teléfono y el correo no tienen constante de respaldo',
    [$hijo('ambas')['telefono'], $hijo('ambas')['correo']], [null, null]);
comprobar('la ciudad es MUNICIPIO_CIUDAD', $hijo('con_ciudad')['ciudad'], 'Guateque');
comprobar('una ciudad en blanco es null', $hijo('ciudad_vacia')['ciudad'], null);

fijar('Tesorería Municipal', '311 000 1111', 'hacienda@guateque.gov.co');
comprobar('la tabla manda sobre las constantes',
    $hijo('ambas'), ['dependencia' => 'Tesorería Municipal', 'telefono' => '311 000 1111', 'correo' => 'hacienda@guateque.gov.co', 'ciudad' => null]);
comprobar('Contacto::dependencia() lee la tabla', Contacto::dependencia(), 'Tesorería Municipal');
comprobar('Contacto::telefono() lee la tabla', Contacto::telefono(), '311 000 1111');
comprobar('Contacto::correo() lee la tabla', Contacto::correo(), 'hacienda@guateque.gov.co');

fijar('  Con espacios  ', '   ', '');
comprobar('un valor con espacios se recorta', Contacto::dependencia(), 'Con espacios');
comprobar('un valor de solo espacios es null', Contacto::telefono(), null);
comprobar('un valor vacío es null', Contacto::correo(), null);

fijar('<script>alert(1)</script>', '<img src=x onerror=alert(2)>', 'a<b>@x.co');
comprobar('escritos por SQL: la dependencia con HTML no se lee', Contacto::dependencia(), null);
comprobar('escritos por SQL: el teléfono con HTML no se lee', Contacto::telefono(), null);
comprobar('escritos por SQL: el correo con HTML no se lee', Contacto::correo(), null);
comprobar('con valores peligrosos en la tabla el respaldo de constantes sí se usa',
    $hijo('solo_secretaria')['dependencia'], 'SECRETARÍA DE HACIENDA');

fijar('Tesorería', '311', 'a@x.co', 0);   // par_Estado = 0: apagados
comprobar('un parámetro apagado (par_Estado 0) no se lee', [Contacto::dependencia(), Contacto::telefono(), Contacto::correo()], [null, null, null]);

/* ====================== B2. LA LECTURA DE LA PANTALLA DE INGRESO ====================== */
// paraIngreso() lee con su propia conexión de espera corta: tiene que dar LO MISMO que
// dependencia() y telefono() cuando la base contesta.
$escenarios = [
    'con datos'                   => ['Tesorería Municipal', '311 000 1111', 'a@x.co', 1],
    'sin datos'                   => [null, null, null, 1],
    'solo la dependencia'         => ['Tesorería Municipal', null, null, 1],
    'solo el teléfono'            => [null, '311 000 1111', null, 1],
    'valores de solo espacios'    => ['   ', '  ', '', 1],
    'HTML escrito por SQL'        => ['<script>alert(1)</script>', '<b>3</b>', 'a<b>@x.co', 1],
    'parámetros apagados'         => ['Tesorería', '311', 'a@x.co', 0],
];
foreach ($escenarios as $nombre => [$dep, $tel, $cor, $estado]) {
    fijar($dep, $tel, $cor, $estado);
    comprobar("paraIngreso() == dependencia() y telefono(): $nombre",
        Contacto::paraIngreso(), ['dependencia' => Contacto::dependencia(), 'telefono' => Contacto::telefono()]);
}
fijar('Tesorería Municipal', '311 000 1111', 'a@x.co');
comprobar('paraIngreso() trae los datos de la tabla', Contacto::paraIngreso(), ['dependencia' => 'Tesorería Municipal', 'telefono' => '311 000 1111']);

fijar(null, '311 000 1111', null);
$h = $hijo('ingreso');
comprobar('paraIngreso() sin dependencia en la tabla usa la constante del config', [$h['dependencia'], $h['telefono']], ['SECRETARÍA DE HACIENDA', '311 000 1111']);

// Con la base caída: no se cae, no espera más de unos segundos y no inventa nada.
// (?? no sirve aquí: trata un null como si faltara la llave, y null es justo lo que se espera)
$dato = function ($h, $llave) { return is_array($h) && array_key_exists($llave, $h) ? $h[$llave] : 'ROTO'; };
$h = $hijo('ingreso_bd_caida');
comprobar('base caída: no se cae y no trae contacto', [$dato($h, 'dependencia'), $dato($h, 'telefono')], [null, null]);
comprobar('base caída: espera menos de 8 s (el driver, solo, esperaba 15)', isset($h['segundos']) && $h['segundos'] < 8, true);
$h = $hijo('ingreso_bd_caida_con_secretaria');
comprobar('base caída: la dependencia sale de la constante del config', [$dato($h, 'dependencia'), $dato($h, 'telefono')], ['SECRETARÍA DE HACIENDA', null]);

/* ====================== C. EL JSON PARA UN <script> ====================== */
fijar("Hacienda & \"Rentas\" O'Neil \\ barra / Ñandú", '(8) 785 0000', 'hacienda@guateque.gov.co');
$json = Contacto::jsonParaPantalla();
comprobar('el JSON no lleva < > & ni comillas simples sin escapar', preg_match('/[<>&\']/', $json), 0);
$ida = json_decode($json, true);
comprobar('el JSON vuelve a ser exactamente lo guardado',
    $ida['dependencia'], "Hacienda & \"Rentas\" O'Neil \\ barra / Ñandú");
comprobar('el JSON trae las cuatro llaves', array_keys($ida), ['dependencia', 'telefono', 'correo', 'ciudad']);

fijar(null, null, null);
// (la ciudad sale de la constante del config de esta máquina, no de la tabla)
comprobar('sin datos el JSON trae nulos y la ciudad del config',
    json_decode(Contacto::jsonParaPantalla(), true), ['dependencia' => null, 'telefono' => null, 'correo' => null, 'ciudad' => Contacto::ciudad()]);

/* ====================== Resultado ====================== */
$total = $pasadas + count($fallidas);
if ($fallidas) {
    echo 'FALLARON ' . count($fallidas) . " de $total:\n" . implode("\n", $fallidas) . "\n";
    exit(1);
}
echo "probar_contacto.php: $pasadas de $total casos pasan.\n";
