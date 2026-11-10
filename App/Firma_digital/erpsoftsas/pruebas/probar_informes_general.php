<?php
/**
 * Prueba del Informe general (informes dinámicos, migración 056).
 *
 * Uso, DENTRO del contenedor web (necesita el driver de SQL Server):
 *
 *     php pruebas/probar_informes_general.php            las pruebas que no tocan el esquema
 *     php pruebas/probar_informes_general.php --ddl      además las de "faltan migraciones"
 *                                                        (renombran tablas y columnas en una
 *                                                        transacción que se deshace: usarla SOLO
 *                                                        en una copia desechable de la base)
 *     PRUEBA_BD=nombre_de_la_copia php pruebas/probar_informes_general.php --ddl
 *
 * Todo lo que escribe (contribuyentes, declaraciones, pagos manuales, roles, cuentas y
 * plantillas de prueba) va dentro de UNA transacción que se deshace al terminar: la base
 * queda como estaba. Corre como root en el contenedor; no usa el navegador ni manda correos.
 *
 * Qué comprueba:
 *   - ORÁCULO: las plantillas "Todas", "Sin pagar" y "Pagadas" dan las mismas filas, en el
 *     mismo orden, y los mismos totales que el Informe rápido (InformePagos::filas), en los
 *     tres impuestos y con sus filtros.
 *   - Que cada columna, cada filtro y cada orden del catálogo se ejecuta sin error.
 *   - INYECCIÓN: claves de columna, de filtro, de orden y de título, operadores y valores.
 *   - PERMISOS en el servidor: sin el permiso del informe, sin el "Ver" del impuesto, sin
 *     el permiso de contacto, rol de contribuyente, cuenta inactiva, plantillas ajenas.
 *   - Reglas de datos: número provisional, WOMPI_PRUEBA, intereses, topes.
 *   - Excel válido (se parte el ZIP y se lee cada parte), tope de filas, plantillas.
 *   - Con --ddl: el motor sigue funcionando cuando faltan las migraciones 044, 045, 049,
 *     051, columnas que ninguna migración crea, un impuesto entero o la propia 056.
 */

if (PHP_SAPI !== 'cli') { exit('Solo por línea de comandos.'); }

$raiz = dirname(__DIR__);
$_SERVER['DOCUMENT_ROOT'] = dirname($raiz);
$_SERVER['SCRIPT_FILENAME'] = __FILE__;      // para que los archivos que se auto-ejecutan no corran al incluirlos
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
if (getenv('PRUEBA_BD')) { define('DB_PROD_NAME', getenv('PRUEBA_BD')); }
$conDdl = in_array('--ddl', $argv ?? [], true);

error_reporting(E_ALL);
$avisosPhp = [];
set_error_handler(function ($no, $texto, $archivo, $linea) use (&$avisosPhp) {
    // Solo importan los avisos del código del informe.
    if (!(error_reporting() & $no)) { return true; }   // lo silenciado con @
    // Avisos de la propia prueba por correr en la consola (en la web no pasan): cabeceras y sesión.
    if (preg_match('/headers already sent|Session ID cannot be changed|session_start\(\)|session_write_close|Session cannot be started/i', (string) $texto)) { return true; }
    if (preg_match('/informe|xlsxSimple|paginacion|permisosRol/i', (string) $archivo)) {
        $avisosPhp[] = "$texto ($archivo:$linea)";
    }
    return true;
});

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.conexionSqlServer.php';
include_once SERVER . '/business/class.permisosRol.php';
include_once SERVER . '/business/class.informePagos.php';
include_once SERVER . '/business/class.informeCatalogo.php';
include_once SERVER . '/business/class.informeConstructor.php';
include_once SERVER . '/business/class.informePlantillas.php';
include_once SERVER . '/business/controller/class.informes.php';
include_once SERVER . '/extensiones/informeGeneral.php';

use erpsoftsas\InformeCatalogo;
use erpsoftsas\InformeConstructor;
use erpsoftsas\InformeException;
use erpsoftsas\InformePagos;
use erpsoftsas\InformePlantillas;
use erpsoftsas\PermisosRol;
use erpsoftsas\PseModulo;

ini_set('display_errors', '1');   // globals.php lo apaga; en una prueba se quiere ver
$dirSesiones = sys_get_temp_dir() . '/sesiones_prueba_informes';
@mkdir($dirSesiones, 0777, true);
session_save_path($dirSesiones);

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
    echo "  FALLA $nombre" . ($detalle !== '' ? "  -> $detalle" : '') . "\n";
    return false;
}
function seccion($titulo) { echo "\n== $titulo\n"; }
function uno($con, $sql, array $p = []) { return $con->obnerFila($con->consultar($sql, $p)); }
function todas($con, $sql, array $p = [])
{
    $st = $con->consultar($sql, $p);
    $r = [];
    while ($f = $con->obnerFila($st)) { $r[] = $f; }
    return $r;
}
/** Ejecuta algo que debe lanzar InformeException y devuelve la excepción (o null). */
function lanza(callable $f)
{
    try { $f(); } catch (InformeException $e) { return $e; } catch (\Throwable $e) { return new \RuntimeException('OTRA: ' . $e->getMessage()); }
    return null;
}
function ctxTotal()
{
    return ['usuario' => 1, 'admin' => true, 'personalizados' => true, 'contacto' => true, 'plantillas' => true,
            'fuentes' => ['ica' => true, 'reteica' => true, 'autorreteica' => true, 'contribuyentes' => true]];
}

$con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
$base = uno($con, 'SELECT DB_NAME() AS b')['b'];
echo "Base: $base" . ($conDdl ? '  (con pruebas de esquema)' : '') . "\n";

/* ======================================================================
   Datos de prueba (dentro de una transacción que se deshace)
   ====================================================================== */
$con->begin();
register_shutdown_function(function () use ($con) {
    try { $con->rollback(); } catch (\Throwable $e) { /* ya estaba deshecha */ }
});

$ids = [];   // lo que se sembró

function nuevoContribuyente($con, $doc, $dv, $tipoDoc, $persona, $nombre, $apellido, array $extra = [])
{
    $cols = ['ind_NumeroIdentificacion' => $doc, 'ind_DV' => $dv, 'ind_IdTipoDocumento' => $tipoDoc, 'ind_PrimerNombre' => $nombre,
             'ind_PrimerApellido' => $apellido, 'ind_Direccion' => 'Calle 1 # 2-3', 'ind_IdCiudad' => 1, 'ind_Persona' => $persona,
             'ind_Estado' => 1] + $extra;
    $f = uno($con, 'INSERT INTO ind_contribuyentes (' . implode(', ', array_keys($cols)) . ') OUTPUT INSERTED.ind_Id AS id VALUES ('
                   . implode(', ', array_fill(0, count($cols), '?')) . ')', array_values($cols));
    return (int) $f['id'];
}

/** Una declaración de cualquiera de los tres módulos con nombres neutros. */
function nuevaDeclaracion($con, $clave, array $d)
{
    $m = PseModulo::get($clave);
    $p = $m['prefijo'];
    $ica = $clave === 'ica';
    $mapa = [
        'contrib' => "{$p}_IdContribuyente", 'anio' => $ica ? 'dec_AnioDeclaracion' : "{$p}_Anio",
        'periodo' => $ica ? 'dec_MesDeclaracion' : "{$p}_Periodo", 'numero' => $m['numero'], 'estado' => $m['estado'],
        'pres' => "{$p}_FechaPresentacion", 'valor' => $m['valor'], 'pagado' => $m['pagado'], 'fpago' => "{$p}_FechaPago",
        'vpago' => "{$p}_ValorPago", 'banco' => "{$p}_BancoPago", 'idbanco' => "{$p}_IdBancoPago", 'ruta' => "{$p}_RutaPago",
        'corrige' => $ica ? 'dec_DeclaracionCorrige' : "{$p}_Corrige", 'elab' => $ica ? 'dec_FechaDeclaracion' : "{$p}_FechaDeclaracion",
    ];
    $cols = [];
    foreach ($d as $k => $v) { $cols[$mapa[$k] ?? $k] = $v; }
    $f = uno($con, "INSERT INTO {$m['tabla']} (" . implode(', ', array_keys($cols)) . ") OUTPUT INSERTED.{$m['pk']} AS id VALUES ("
                   . implode(', ', array_fill(0, count($cols), '?')) . ')', array_values($cols));
    return (int) $f['id'];
}

function nuevoPagoManual($con, $clave, $idDecl, $fecha, $valor, $medio, array $extra = [])
{
    $cols = ['pma_Modulo' => $clave, 'pma_IdDeclaracion' => $idDecl, 'pma_FechaPago' => $fecha, 'pma_Valor' => $valor,
             'pma_Medio' => $medio, 'pma_IdUsuario' => 1, 'pma_FechaRegistro' => '2026-03-01T10:00:00'] + $extra;
    $con->consultar('INSERT INTO ind_pagos_manuales (' . implode(', ', array_keys($cols)) . ') VALUES ('
                    . implode(', ', array_fill(0, count($cols), '?')) . ')', array_values($cols));
}

$hayPm = InformeCatalogo::hay($con, 'T:ind_pagos_manuales');
$hayPmFecha = InformeCatalogo::hay($con, 'C:ind_pagos_manuales.pma_FechaPresentacion');
$hayPmCuenta = InformeCatalogo::hay($con, 'C:ind_pagos_manuales.pma_CuentaContable');
$hayPlantillas = InformePlantillas::hayTabla($con);
$bancos = todas($con, "SELECT TOP 2 ban_Id, ban_Nombre FROM ind_bancos WHERE ban_Codigo NOT IN ('00','999') AND ISNULL(ban_Activo,1)=1 ORDER BY ban_Id");

$cA = nuevoContribuyente($con, 880000001, 0, 1, 1, 'María', 'Peñaranda', ['ind_Email' => 'maria@ejemplo.invalid', 'ind_Telefono' => 3105550101,
    'ind_Nombre_representante' => 'Rep Uno', 'ind_NombreContador' => 'Con Tador', 'ind_EmailContador' => 'contador@ejemplo.invalid',
    'ind_NombreRevisor' => 'Revi Sor', 'ind_EmailRevisor' => 'revisor@ejemplo.invalid']);
$cB = nuevoContribuyente($con, 880000002, 7, 5, 2, "Inversiones D'Luca S.A.S.", '', ['ind_RegimenTributario' => 'SIMPLE,NO_RESP_IVA',
    'ind_Responsabilidades' => 'AGENTE_RETENCION,AUTORRETENEDOR']);
$cC = nuevoContribuyente($con, 880000003, 0, 1, 1, 'José', 'Avendaño');
$cD = nuevoContribuyente($con, 880000004, 0, 1, 1, '100% Pérez [x]_y', 'Gómez', ['ind_FechaCese' => '2026-01-31', 'ind_CausalCese' => '3']);
// El padrón real trae contribuyentes con el mismo documento dos veces: no debe romper nada.
$cA2 = nuevoContribuyente($con, 880000001, 0, 1, 1, 'María', 'Peñaranda (repetida)');

$sembrado = [];
foreach (['ica', 'reteica', 'autorreteica'] as $clave) {
    $esIca = $clave === 'ica';
    $p = PseModulo::get($clave)['prefijo'];
    $n = ['ica' => 9100000000, 'reteica' => 9200000000, 'autorreteica' => 9300000000][$clave];
    $per = function ($k) use ($esIca) { return $esIca ? 12 : $k; };
    $s = [];
    // 1. Borrador: el número provisional es NEGATIVO y nunca se muestra.
    $s['borrador'] = nuevaDeclaracion($con, $clave, ['contrib' => $cA, 'anio' => 2026, 'periodo' => $per(1), 'numero' => -$n - 1, 'valor' => 111000]);
    // 2. Presentada sin pagar.
    $s['sinpagar'] = nuevaDeclaracion($con, $clave, ['contrib' => $cA, 'anio' => 2026, 'periodo' => $per(2), 'numero' => $n + 2, 'estado' => 2,
        'pres' => '2026-02-10T09:30:00', 'valor' => 500000, 'elab' => '2026-02-09']);
    // 3. Presentada con valor cero.
    $s['sinvalor'] = nuevaDeclaracion($con, $clave, ['contrib' => $cB, 'anio' => 2026, 'periodo' => $per(3), 'numero' => $n + 3, 'estado' => 2,
        'pres' => '2026-03-02T09:30:00', 'valor' => 0]);
    // 4. Pagada por PSE, exacto, con banco por id.
    $s['pse'] = nuevaDeclaracion($con, $clave, ['contrib' => $cB, 'anio' => 2026, 'periodo' => $per(4), 'numero' => $n + 4, 'estado' => 2,
        'pres' => '2026-04-01T09:30:00', 'valor' => 700000, 'pagado' => 1, 'fpago' => '2026-04-05T00:00:00', 'vpago' => 700000,
        'banco' => 'BANCO DEL QUE PAGO', 'ruta' => 'PSE'] + (InformeCatalogo::hay($con, 'C:' . PseModulo::get($clave)['tabla'] . ".{$p}_IdBancoPago") && $bancos ? ['idbanco' => $bancos[0]['ban_Id']] : []));
    // 5. Pagada por Wompi con intereses.
    $s['wompi'] = nuevaDeclaracion($con, $clave, ['contrib' => $cC, 'anio' => 2026, 'periodo' => $per(5), 'numero' => $n + 5, 'estado' => 2,
        'pres' => '2026-05-03T09:30:00', 'valor' => 800000, 'pagado' => 1, 'fpago' => '2026-05-20T00:00:00', 'vpago' => 825000,
        'banco' => 'Wompi - CARD', 'ruta' => 'WOMPI']);
    // 6. "Pagada" con llaves de PRUEBAS de Wompi: no entró plata, cuenta como sin pagar.
    $s['wompiPrueba'] = nuevaDeclaracion($con, $clave, ['contrib' => $cC, 'anio' => 2026, 'periodo' => $per(6), 'numero' => $n + 6, 'estado' => 2,
        'pres' => '2026-06-03T09:30:00', 'valor' => 90000, 'pagado' => 1, 'fpago' => '2026-06-04T00:00:00', 'vpago' => 90000,
        'banco' => 'Wompi PRUEBAS - CARD', 'ruta' => 'WOMPI_PRUEBA']);
    // 7. Pagada por recaudo bancario, con menos de lo declarado: los intereses quedan vacíos, nunca negativos.
    $s['recaudo'] = nuevaDeclaracion($con, $clave, ['contrib' => $cD, 'anio' => 2025, 'periodo' => $per(6), 'numero' => $n + 7, 'estado' => 2,
        'pres' => '2026-01-15T09:30:00', 'valor' => 400000, 'pagado' => 1, 'fpago' => '2026-01-20T00:00:00', 'vpago' => 390000,
        'banco' => 'BANCO POPULAR', 'ruta' => 'RECAUDO_BANCARIO']);
    // 8. Pagada a mano (transferencia), con otra fecha de presentación y cuenta contable.
    $s['manual'] = nuevaDeclaracion($con, $clave, ['contrib' => $cA, 'anio' => 2025, 'periodo' => $per(1), 'numero' => $n + 8, 'estado' => 2,
        'pres' => '2026-01-05T09:30:00', 'valor' => 250000, 'pagado' => 1, 'fpago' => '2026-01-30T00:00:00', 'vpago' => 250000,
        'banco' => 'Transferencia - BANCOLOMBIA', 'ruta' => 'MANUAL'] + (InformeCatalogo::hay($con, 'C:' . PseModulo::get($clave)['tabla'] . ".{$p}_IdBancoPago") && $bancos ? ['idbanco' => $bancos[1]['ban_Id']] : []));
    if ($hayPm) {
        nuevoPagoManual($con, $clave, $s['manual'], '2026-01-30', 250000, 'TRANSFERENCIA',
            ($hayPmFecha ? ['pma_FechaPresentacion' => '2025-12-28'] : []) + ($hayPmCuenta ? ['pma_CuentaContable' => '1110-05-001'] : []));
    }
    // 9. Original sin pagar con una corrección ya presentada encima: no se cobra ("Corregida").
    $s['corregida'] = nuevaDeclaracion($con, $clave, ['contrib' => $cB, 'anio' => 2025, 'periodo' => $per(2), 'numero' => $n + 9, 'estado' => 2,
        'pres' => '2026-02-01T09:30:00', 'valor' => 300000]);
    $s['correccion'] = nuevaDeclaracion($con, $clave, ['contrib' => $cB, 'anio' => 2025, 'periodo' => $per(2), 'numero' => $n + 10, 'estado' => 2,
        'pres' => '2026-02-20T09:30:00', 'valor' => 320000, 'corrige' => $n + 9]);
    // 10. Original PAGADA que también tiene una corrección presentada: sigue siendo "Pagada".
    $s['pagadaCorregida'] = nuevaDeclaracion($con, $clave, ['contrib' => $cC, 'anio' => 2025, 'periodo' => $per(3), 'numero' => $n + 11, 'estado' => 2,
        'pres' => '2026-03-05T09:30:00', 'valor' => 150000, 'pagado' => 1, 'fpago' => '2026-03-06T00:00:00', 'vpago' => 150000,
        'banco' => 'BANCO POPULAR', 'ruta' => 'RECAUDO_BANCARIO']);
    nuevaDeclaracion($con, $clave, ['contrib' => $cC, 'anio' => 2025, 'periodo' => $per(3), 'numero' => $n + 12, 'estado' => 2,
        'pres' => '2026-03-25T09:30:00', 'valor' => 160000, 'corrige' => $n + 11]);
    // 11. Una más sin pagar de otro año, para los filtros por año y por rango.
    $s['otroAnio'] = nuevaDeclaracion($con, $clave, ['contrib' => $cA2, 'anio' => 2024, 'periodo' => $per(4), 'numero' => $n + 13, 'estado' => 2,
        'pres' => '2026-07-01T09:30:00', 'valor' => 60000]);
    $sembrado[$clave] = $s;
}
echo "Sembrado: 5 contribuyentes y " . array_sum(array_map('count', $sembrado)) . " declaraciones (en una transacción que se deshace).\n";

/* ======================================================================
   Cuentas y roles de prueba
   ====================================================================== */
$subs = [];
foreach (todas($con, 'SELECT subMod_Id, subMod_IdModulo, subMod_Clave FROM conf_submodulo WHERE subMod_Clave IS NOT NULL') as $f) { $subs[$f['subMod_Clave']] = $f; }
function nuevoRol($con, $subs, $nombre, $tipo, array $claves, $estado = 1)
{
    $r = uno($con, 'INSERT INTO conf_rol (rol_Nombre, rol_Descripcion, rol_Estado, rol_Tipo) OUTPUT INSERTED.rol_Id AS id VALUES (?, ?, ?, ?)',
             [$nombre, 'prueba', $estado, $tipo]);
    $id = (int) $r['id'];
    foreach ($claves as $c) {
        if (!isset($subs[$c])) { continue; }
        $con->consultar('INSERT INTO conf_permisos (per_IdSubmodulo, per_IdRol, per_IdModulo, per_IdBoton, per_Estado) VALUES (?, ?, ?, ?, 1)',
            [(int) $subs[$c]['subMod_Id'], $id, (int) $subs[$c]['subMod_IdModulo'], (int) ((string) $subs[$c]['subMod_IdModulo'] . (string) $subs[$c]['subMod_Id'])]);
    }
    return $id;
}
function nuevaCuenta($con, $rol, $usuario, $estado = 1)
{
    $r = uno($con, 'INSERT INTO conf_usuarios (usu_Nombres, usu_Apellidos, usu_Usuario, usu_Password, usu_Rol, usu_Estado) OUTPUT INSERTED.usu_Id AS id VALUES (?, ?, ?, ?, ?, ?)',
             ['Prueba', $usuario, $usuario, 'x', $rol, $estado]);
    return (int) $r['id'];
}
$base1 = ['alcaldia.informes.pagos', 'alcaldia.informes.personalizados'];
$roles = [
    'solo_ica'   => nuevoRol($con, $subs, 'PI solo ICA', 'ALCALDIA', array_merge($base1, ['ica.ver'])),
    'contacto'   => nuevoRol($con, $subs, 'PI contacto', 'ALCALDIA', array_merge($base1, ['ica.ver', 'reteica.ver', 'alcaldia.informes.contacto'])),
    'plantillas' => nuevoRol($con, $subs, 'PI plantillas', 'ALCALDIA', array_merge($base1, ['ica.ver', 'alcaldia.informes.plantillas'])),
    'sin_perm'   => nuevoRol($con, $subs, 'PI sin permiso', 'ALCALDIA', ['alcaldia.informes.pagos', 'ica.ver']),
    'contrib'    => nuevoRol($con, $subs, 'PI contribuyente', 'CONTRIBUYENTE', array_merge($base1, ['ica.ver', 'alcaldia.informes.contacto'])),
    'inactivo'   => nuevoRol($con, $subs, 'PI inactivo', 'ALCALDIA', array_merge($base1, ['ica.ver']), 0),
    'todo'       => nuevoRol($con, $subs, 'PI todo', 'ALCALDIA', array_merge($base1, ['ica.ver', 'reteica.ver', 'autorreteica.ver', 'alcaldia.informes.plantillas', 'alcaldia.informes.contacto'])),
];
$cuentas = [
    'admin'      => 1,
    'solo_ica'   => nuevaCuenta($con, $roles['solo_ica'], 'pi_solo_ica'),
    'contacto'   => nuevaCuenta($con, $roles['contacto'], 'pi_contacto'),
    'plantillas' => nuevaCuenta($con, $roles['plantillas'], 'pi_plantillas'),
    'sin_perm'   => nuevaCuenta($con, $roles['sin_perm'], 'pi_sin_perm'),
    'contrib'    => nuevaCuenta($con, $roles['contrib'], 'pi_contrib'),
    'rolInactivo' => nuevaCuenta($con, $roles['inactivo'], 'pi_rol_inactivo'),
    'cuentaInactiva' => nuevaCuenta($con, $roles['solo_ica'], 'pi_cuenta_inactiva', 0),
    'todo'       => nuevaCuenta($con, $roles['todo'], 'pi_todo'),
];

/** Pasa a ser esa persona: sesión nueva y permisos releídos. */
function comoUsuario($id)
{
    if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
    session_id('prueba' . $id . 'x' . mt_rand(1000, 9999));
    @session_start();
    $_SESSION = $id ? ['id_usuario' => $id, 'IdUsuario' => $id, 'usu_Id' => $id, 'id_Rol' => 0] : [];
    PermisosRol::olvidar();
    InformeCatalogo::olvidar();
    InformePlantillas::olvidar();
}
/** Llama al controlador JSON como lo hace jQuery y devuelve la respuesta decodificada. */
function llamar($funcion, array $post = [], $xhr = true, $metodo = 'POST')
{
    $_POST = ['funcion' => $funcion] + $post;
    $_SERVER['REQUEST_METHOD'] = $metodo;
    if ($xhr) { $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest'; } else { unset($_SERVER['HTTP_X_REQUESTED_WITH']); }
    ob_start();
    \erpsoftsas\ControladorInformes::run();
    $s = ob_get_clean();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $j = json_decode($s, true);
    return is_array($j) ? $j : ['_crudo' => $s];
}

/* ======================================================================
   Plantillas y filtros auxiliares
   ====================================================================== */
function configDe($con, $ctx, $slug, $fuente, array $filtrosExtra = [])
{
    $p = InformePlantillas::obtener($con, $ctx, 'sistema:' . $slug, $fuente);
    $cfg = $p['config'];
    foreach ($filtrosExtra as $f) { $cfg['filtros'][] = $f; }
    return $cfg;
}
function ejecutar($con, $ctx, array $cfg)
{
    $v = InformeConstructor::validar($con, $cfg, $ctx, 'estricto');
    $q = InformeConstructor::armar($v);
    $filas = [];
    InformeConstructor::recorrer($con, $q, function ($f) use (&$filas) { $filas[] = $f; });
    return ['q' => $q, 'filas' => $filas, 'totales' => InformeConstructor::totales($con, $q), 'total' => InformeConstructor::contar($con, $q), 'v' => $v];
}
function aTexto($v) { return $v === null ? '' : (string) $v; }

/* ======================================================================
   1. ORÁCULO: el Informe rápido
   ====================================================================== */
seccion('1. Oráculo: las plantillas Todas, Sin pagar y Pagadas contra el Informe rápido');
$ctx = ctxTotal();
$estados = ['todas' => 'todas', 'sinpagar' => 'sinpagar', 'pagadas' => 'pagadas'];
$variantes = [
    'sin filtros' => [],
    'año 2026' => ['anio' => 2026],
    'año 2025' => ['anio' => 2025],
    'año y período' => ['anio' => 2026, 'periodo' => 3],
    'documento' => ['documento' => 880000003],
    'rango' => ['desde' => '2026-01-10', 'hasta' => '2026-04-30'],
    'solo desde' => ['desde' => '2026-03-01'],
    'solo hasta' => ['hasta' => '2026-03-31'],
];
$comparados = 0;
foreach (['ica', 'reteica', 'autorreteica'] as $clave) {
    $tienePeriodo = $clave !== 'ica';
    foreach ($estados as $slugEstado => $estadoRapido) {
        $slug = $slugEstado;
        foreach ($variantes as $nombreVariante => $f) {
            if (isset($f['periodo']) && !$tienePeriodo) { continue; }
            $esperado = InformePagos::filas($con, $clave, ['estado' => $estadoRapido] + [
                'anio' => $f['anio'] ?? null, 'periodo' => $f['periodo'] ?? null, 'documento' => $f['documento'] ?? null,
                'desde' => $f['desde'] ?? null, 'hasta' => $f['hasta'] ?? null]);
            $extra = [];
            if (isset($f['anio']))      { $extra[] = ['c' => 'anio', 'op' => 'in', 'v' => [$f['anio']]]; }
            if (isset($f['periodo']))   { $extra[] = ['c' => 'periodo', 'op' => 'in', 'v' => [$f['periodo']]]; }
            if (isset($f['documento'])) { $extra[] = ['c' => 'documento', 'op' => 'in', 'v' => [$f['documento']]]; }
            // El rango del Informe rápido es de PAGO en las pagadas y de PRESENTACIÓN en las demás; aquí se elige.
            $campoFecha = $estadoRapido === 'pagadas' ? 'fechaPago' : 'fechaPresentacion';
            if (isset($f['desde']) && isset($f['hasta'])) { $extra[] = ['c' => $campoFecha, 'op' => 'between', 'v' => [$f['desde'], $f['hasta']]]; }
            elseif (isset($f['desde'])) { $extra[] = ['c' => $campoFecha, 'op' => '>=', 'v' => $f['desde']]; }
            elseif (isset($f['hasta'])) { $extra[] = ['c' => $campoFecha, 'op' => '<=', 'v' => $f['hasta']]; }

            $r = ejecutar($con, $ctx, configDe($con, $ctx, $slug, $clave, $extra));
            $claves = array_column($r['q']['columnas'], 'c');
            $col = array_flip($claves);
            $etiqueta = "$clave / $slugEstado / $nombreVariante";
            if (count($r['filas']) !== count($esperado)) {
                prueba("$etiqueta: misma cantidad de filas", false, count($r['filas']) . ' contra ' . count($esperado));
                continue;
            }
            $dif = null;
            foreach ($esperado as $i => $e) {
                $g = $r['filas'][$i];
                $pares = [
                    'documento' => [$e['documento'], $g[$col['documento']]],
                    'contribuyente' => [$e['contribuyente'], $g[$col['contribuyente']]],
                    'anio' => [$e['anio'], $g[$col['anio']]],
                    'numero' => [$e['numero'], $g[$col['numero']]],
                    'fechaPresentacion' => [$e['fechaPresentacion'], $g[$col['fechaPresentacion']]],
                    'estado' => [$e['estado'], $g[$col['estado']]],
                    'fechaPago' => [$e['fechaPago'], $g[$col['fechaPago']]],
                    'banco' => [$e['banco'], $g[$col['banco']]],
                    'cuentaContable' => [$e['cuentaContable'], isset($col['cuentaContable']) ? $g[$col['cuentaContable']] : $e['cuentaContable']],
                    'canal' => [$e['canal'], $g[$col['canal']]],
                    'valorDeclarado' => [$e['valorDeclarado'], $g[$col['valorDeclarado']]],
                    'valorPagado' => [$e['valorPagado'], $g[$col['valorPagado']] ?? 0],
                    'intereses' => [$e['intereses'], $g[$col['intereses']]],
                ];
                if ($tienePeriodo) { $pares['periodo'] = [$e['periodo'], $g[$col['periodo']]]; }
                foreach ($pares as $campo => [$a, $b]) {
                    if (aTexto($a) !== aTexto($b)) { $dif = "fila $i, $campo: rápido «" . aTexto($a) . "» contra general «" . aTexto($b) . '»'; break 2; }
                }
            }
            // Los totales del rápido se suman en PHP sobre cifras redondeadas; los del general, en SQL.
            $tV = array_sum(array_column($esperado, 'valorDeclarado'));
            $tP = array_sum(array_column($esperado, 'valorPagado'));
            $tI = array_sum(array_map('floatval', array_filter(array_column($esperado, 'intereses'), 'is_numeric')));
            if ($dif === null && ((int) $r['totales']['valorDeclarado'] !== (int) $tV || (int) ($r['totales']['valorPagado'] ?? 0) !== (int) $tP
                                  || (int) ($r['totales']['intereses'] ?? 0) !== (int) $tI)) {
                $dif = 'totales: general ' . json_encode($r['totales']) . " contra rápido [$tV, $tP, $tI]";
            }
            $comparados++;
            if ($dif !== null) { prueba("$etiqueta: filas y totales iguales", false, $dif); }
        }
    }
}
prueba("oráculo: $comparados comparaciones (3 impuestos x 3 plantillas x filtros) sin diferencias", $comparados > 50 && count($fallas) === 0, 'fallas: ' . count($fallas));

// El oráculo tiene que haber visto de todo: si la siembra no cubre un estado, la comparación no prueba nada.
$vistos = [];
foreach (['ica', 'reteica', 'autorreteica'] as $clave) {
    foreach (InformePagos::filas($con, $clave, ['estado' => 'todas']) as $e) { $vistos[$e['estado']] = true; }
}
prueba('el oráculo cubrió Pagada, Corregida, Sin valor y Sin pagar', count(array_intersect(['Pagada', 'Corregida', 'Sin valor', 'Sin pagar'], array_keys($vistos))) === 4);
$canales = [];
foreach (InformePagos::filas($con, 'ica', ['estado' => 'pagadas']) as $e) { $canales[$e['canal']] = true; }
prueba('el oráculo cubrió PSE, Wompi, Recaudo y el medio manual (Transferencia)', !array_diff(['PSE', 'Wompi', 'Recaudo', $hayPm ? 'Transferencia' : 'Manual'], array_keys($canales)), implode(',', array_keys($canales)));

/* ======================================================================
   2. Reglas de datos
   ====================================================================== */
seccion('2. Reglas de datos');
$r = ejecutar($con, $ctx, ['v' => 1, 'modo' => 'declaracion', 'impuesto' => 'ica',
    'filtros' => [['c' => 'estado', 'op' => 'in', 'v' => ['borrador']], ['c' => 'documento', 'op' => 'in', 'v' => [880000001]]],
    'columnas' => [['c' => 'numero'], ['c' => 'estado'], ['c' => 'valorDeclarado'], ['c' => 'fechaPago']], 'orden' => []]);
prueba('un borrador sale con el número vacío (el provisional negativo nunca se muestra)', count($r['filas']) >= 1 && $r['filas'][0][0] === null && $r['filas'][0][1] === 'Borrador', json_encode($r['filas']));
$r = ejecutar($con, $ctx, ['v' => 1, 'modo' => 'declaracion', 'impuesto' => 'ica', 'filtros' => [['c' => 'documento', 'op' => 'in', 'v' => [880000001]]],
    'columnas' => [['c' => 'estado']], 'orden' => []]);
prueba('sin filtro de estado no salen los borradores', !in_array('Borrador', array_column($r['filas'], 0), true));
$r = ejecutar($con, $ctx, ['v' => 1, 'modo' => 'declaracion', 'impuesto' => 'ica', 'filtros' => [['c' => 'documento', 'op' => 'in', 'v' => [880000003]]],
    'columnas' => [['c' => 'estado'], ['c' => 'pagada'], ['c' => 'fechaPago'], ['c' => 'valorPagado'], ['c' => 'banco'], ['c' => 'canal'], ['c' => 'intereses']],
    'orden' => [['c' => 'numero', 'd' => 'asc']]]);
$wp = null;
foreach ($r['filas'] as $f) { if ($f[1] === 'No' && $f[0] === 'Sin pagar') { $wp = $f; } }
prueba('un pago con llaves de PRUEBAS de Wompi cuenta como sin pagar y no muestra fecha, valor, banco ni medio',
       $wp && $wp[2] === null && $wp[3] === null && $wp[4] === null && $wp[5] === null && $wp[6] === null, json_encode($r['filas']));
$r = ejecutar($con, $ctx, ['v' => 1, 'modo' => 'declaracion', 'impuesto' => 'ica', 'filtros' => [['c' => 'documento', 'op' => 'in', 'v' => [880000004]]],
    'columnas' => [['c' => 'estado'], ['c' => 'valorDeclarado'], ['c' => 'valorPagado'], ['c' => 'intereses']], 'orden' => []]);
prueba('si se pagó menos de lo declarado los intereses van vacíos, nunca negativos', $r['filas'] && $r['filas'][0][0] === 'Pagada' && $r['filas'][0][3] === null && $r['filas'][0][2] === 390000, json_encode($r['filas']));
$r = ejecutar($con, $ctx, ['v' => 1, 'modo' => 'declaracion', 'impuesto' => 'ica', 'filtros' => [['c' => 'conIntereses', 'op' => '=', 'v' => true], ['c' => 'documento', 'op' => 'in', 'v' => [880000003]]],
    'columnas' => [['c' => 'valorDeclarado'], ['c' => 'valorPagado'], ['c' => 'intereses']], 'orden' => []]);
prueba('el filtro "con intereses" trae solo lo pagado por encima del total (825.000 contra 800.000)', count($r['filas']) === 1 && $r['filas'][0][2] === 25000, json_encode($r['filas']));
$r = ejecutar($con, $ctx, ['v' => 1, 'modo' => 'declaracion', 'impuesto' => 'ica', 'filtros' => [['c' => 'corregida', 'op' => '=', 'v' => true], ['c' => 'documento', 'op' => 'in', 'v' => [880000002, 880000003]]],
    'columnas' => [['c' => 'documento'], ['c' => 'estado']], 'orden' => [['c' => 'documento', 'd' => 'asc']]]);
prueba('el filtro "tiene una corrección" incluye la original corregida y la pagada que también se corrigió', count($r['filas']) === 2 && $r['filas'][0][1] === 'Corregida' && $r['filas'][1][1] === 'Pagada', json_encode($r['filas']));
$r = ejecutar($con, $ctx, ['v' => 1, 'modo' => 'declaracion', 'impuesto' => 'ica', 'filtros' => [['c' => 'documento', 'op' => 'in', 'v' => [880000002]]],
    'columnas' => [['c' => 'documento'], ['c' => 'contribuyente'], ['c' => 'tipoDocumento'], ['c' => 'tipoPersona']], 'orden' => []]);
prueba('el documento lleva el dígito de verificación solo en un NIT, y el nombre de una jurídica sale de la razón social',
       $r['filas'] && $r['filas'][0][0] === '880000002-7' && $r['filas'][0][1] === "Inversiones D'Luca S.A.S." && $r['filas'][0][2] === 'NIT' && $r['filas'][0][3] === 'Jurídica', json_encode($r['filas'][0] ?? null));
$r = ejecutar($con, $ctx, ['v' => 1, 'modo' => 'declaracion', 'impuesto' => 'ica', 'filtros' => [['c' => 'documento', 'op' => 'in', 'v' => [880000001]]],
    'columnas' => [['c' => 'documento']], 'orden' => []]);
prueba('un contribuyente repetido en el padrón no rompe el informe', count($r['filas']) >= 2);
$r = ejecutar($con, $ctx, ['v' => 1, 'modo' => 'declaracion', 'impuesto' => 'ica', 'filtros' => [['c' => 'anio', 'op' => 'in', 'v' => [2099]]],
    'columnas' => [['c' => 'documento'], ['c' => 'valorDeclarado']], 'orden' => []]);
prueba('un informe sin filas funciona (cero filas, totales en cero)', $r['total'] === 0 && $r['filas'] === [] && $r['totales']['valorDeclarado'] === 0);

// Tabla de rótulos y tipos de cada fuente: todo lo que sale a la pantalla es texto, números o null.
foreach (['ica', 'reteica', 'autorreteica'] as $clave) {
    $cfg = configDe($con, $ctx, 'todas', $clave);
    $r = ejecutar($con, $ctx, $cfg);
    $json = json_encode($r['filas'], JSON_INVALID_UTF8_SUBSTITUTE);
    prueba("$clave: las filas se pueden convertir a JSON (sin objetos DateTime ni UTF-8 roto)", $json !== false && strpos($json, '"date"') === false);
}

/* ======================================================================
   2b. Cada filtro contra una consulta escrita a mano
   El oráculo solo cubre las tres plantillas del Informe rápido; aquí cada filtro del catálogo
   se compara con un SQL escrito aparte (sin pasar por el catálogo ni el constructor) sobre
   los mismos datos sembrados. Se compara el CONJUNTO de números de declaración.
   ====================================================================== */
seccion('2b. Cada filtro del catálogo, contra una consulta escrita aparte');
$docsSembrados = [880000001, 880000002, 880000003, 880000004];
$listaDocs = implode(', ', $docsSembrados);
$numerosInforme = function ($clave, array $filtro) use ($con, $ctx, $docsSembrados) {
    $r = ejecutar($con, $ctx, ['v' => 1, 'modo' => 'declaracion', 'impuesto' => $clave,
        'filtros' => $filtro['c'] === 'documento' ? [$filtro] : [['c' => 'documento', 'op' => 'in', 'v' => $docsSembrados], $filtro],
        'columnas' => [['c' => 'numero']], 'orden' => [['c' => 'numero', 'd' => 'asc']]]);
    $n = array_map(function ($f) { return (string) $f[0]; }, $r['filas']);
    sort($n, SORT_NUMERIC);
    return $n;
};
$numerosAMano = function ($clave, $predicado) use ($con, $listaDocs, $hayPm) {
    $m = PseModulo::get($clave);
    $p = $m['prefijo'];
    $joinPm = $hayPm ? "LEFT JOIN ind_pagos_manuales pm ON pm.pma_Modulo = '$clave' AND pm.pma_IdDeclaracion = d.{$m['pk']}" : '';
    $filas = todas($con, "SELECT CAST(d.{$m['numero']} AS VARCHAR(30)) AS n
                            FROM {$m['tabla']} d JOIN ind_contribuyentes c ON c.ind_Id = d.{$p}_IdContribuyente $joinPm
                           WHERE d.{$m['estado']} = 2 AND c.ind_NumeroIdentificacion IN ($listaDocs) AND ($predicado)
                           ORDER BY d.{$m['numero']}");
    $n = array_map(function ($f) { return (string) $f['n']; }, $filas);
    sort($n, SORT_NUMERIC);
    return $n;
};
$ciudad = uno($con, 'SELECT ciu_Nombre FROM conf_ciudades WHERE ciu_Id = 1');
$fragCiudad = $ciudad ? mb_strtolower(mb_substr($ciudad['ciu_Nombre'], 1, 4, 'UTF-8'), 'UTF-8') : 'xxxx';
$cubiertos = 0;
$conFilas = 0;
$malos = [];
foreach (['ica', 'reteica', 'autorreteica'] as $clave) {
    $m = PseModulo::get($clave);
    $p = $m['prefijo'];
    $corr = $clave === 'ica' ? 'dec_DeclaracionCorrige' : "{$p}_Corrige";
    $anioCol = $clave === 'ica' ? 'dec_AnioDeclaracion' : "{$p}_Anio";
    $pag = "d.{$m['pagado']} = 1 AND ISNULL(d.{$p}_RutaPago, '') <> 'WOMPI_PRUEBA'";
    $valor = "ROUND(ISNULL(d.{$m['valor']}, 0), 0)";
    $corregida = "EXISTS (SELECT 1 FROM {$m['tabla']} x WHERE x.$corr = d.{$m['numero']} AND x.{$m['estado']} = 2)";
    $pres = ($hayPm && $hayPmFecha) ? "COALESCE(pm.pma_FechaPresentacion, CAST(d.{$p}_FechaPresentacion AS DATE))" : "CAST(d.{$p}_FechaPresentacion AS DATE)";
    $casos = [
        'fecha de pago entre' => [['c' => 'fechaPago', 'op' => 'between', 'v' => ['2026-01-25', '2026-04-10']], "$pag AND CAST(d.{$p}_FechaPago AS DATE) BETWEEN '2026-01-25' AND '2026-04-10'"],
        'fecha de pago desde' => [['c' => 'fechaPago', 'op' => '>=', 'v' => '2026-04-01'], "$pag AND CAST(d.{$p}_FechaPago AS DATE) >= '2026-04-01'"],
        'fecha de pago hasta' => [['c' => 'fechaPago', 'op' => '<=', 'v' => '2026-01-31'], "$pag AND CAST(d.{$p}_FechaPago AS DATE) <= '2026-01-31'"],
        'fecha de presentación entre' => [['c' => 'fechaPresentacion', 'op' => 'between', 'v' => ['2026-02-01', '2026-03-31']], "$pres BETWEEN '2026-02-01' AND '2026-03-31'"],
        'fecha de presentación hasta (la del pago manual manda)' => [['c' => 'fechaPresentacion', 'op' => '<=', 'v' => '2025-12-31'], "$pres <= '2025-12-31'"],
        'total a pagar entre' => [['c' => 'valorDeclarado', 'op' => 'between', 'v' => [200000, 500000]], "$valor BETWEEN 200000 AND 500000"],
        'total a pagar desde' => [['c' => 'valorDeclarado', 'op' => '>=', 'v' => 700000], "$valor >= 700000"],
        'total a pagar hasta' => [['c' => 'valorDeclarado', 'op' => '<=', 'v' => 100000], "$valor <= 100000"],
        'medio PSE' => [['c' => 'canal', 'op' => 'in', 'v' => ['PSE']], "$pag AND d.{$p}_RutaPago = 'PSE'"],
        'medio manual o Wompi' => [['c' => 'canal', 'op' => 'in', 'v' => ['MANUAL', 'WOMPI']], "$pag AND d.{$p}_RutaPago IN ('MANUAL', 'WOMPI')"],
        'medio recaudo' => [['c' => 'canal', 'op' => 'in', 'v' => ['RECAUDO_BANCARIO']], "$pag AND d.{$p}_RutaPago = 'RECAUDO_BANCARIO'"],
        'tipo corrección' => [['c' => 'tipoDeclaracion', 'op' => 'in', 'v' => ['correccion']], "d.$corr IS NOT NULL"],
        'tipo inicial' => [['c' => 'tipoDeclaracion', 'op' => 'in', 'v' => ['inicial']], "d.$corr IS NULL"],
        'sin corrección presentada' => [['c' => 'corregida', 'op' => '=', 'v' => false], "NOT $corregida"],
        'con corrección presentada' => [['c' => 'corregida', 'op' => '=', 'v' => true], $corregida],
        'sin intereses pagados' => [['c' => 'conIntereses', 'op' => '=', 'v' => false], "NOT ($pag AND ROUND(ISNULL(d.{$p}_ValorPago, 0), 0) > $valor)"],
        'con intereses pagados' => [['c' => 'conIntereses', 'op' => '=', 'v' => true], "$pag AND ROUND(ISNULL(d.{$p}_ValorPago, 0), 0) > $valor"],
        'estado sin valor' => [['c' => 'estado', 'op' => 'in', 'v' => ['sinvalor']], "NOT ($pag) AND NOT $corregida AND $valor <= 0"],
        'estado pagada o corregida' => [['c' => 'estado', 'op' => 'in', 'v' => ['pagada', 'corregida']], "($pag) OR (NOT ($pag) AND $corregida)"],
        'año 2025' => [['c' => 'anio', 'op' => 'in', 'v' => [2025]], "d.$anioCol = 2025"],
        'años entre 2025 y 2026' => [['c' => 'anio', 'op' => 'between', 'v' => [2025, 2026]], "d.$anioCol BETWEEN 2025 AND 2026"],
        'documentos' => [['c' => 'documento', 'op' => 'in', 'v' => [880000002, 880000003]], "c.ind_NumeroIdentificacion IN (880000002, 880000003)"],
        'persona jurídica' => [['c' => 'tipoPersona', 'op' => 'in', 'v' => [2]], "c.ind_Persona = 2"],
        'nombre: una palabra, sin tildes ni mayúsculas' => [['c' => 'contribuyente', 'op' => 'contiene', 'v' => 'LUCA'], "c.ind_PrimerNombre COLLATE Latin1_General_CI_AI LIKE '%luca%'"],
        'nombre: dos palabras' => [['c' => 'contribuyente', 'op' => 'contiene', 'v' => 'maria penaranda'], "c.ind_PrimerNombre COLLATE Latin1_General_CI_AI LIKE '%maria%' AND c.ind_PrimerApellido COLLATE Latin1_General_CI_AI LIKE '%penaranda%'"],
        'nombre con % y [ ] _ literales' => [['c' => 'contribuyente', 'op' => 'contiene', 'v' => '100% pérez [x]_y'], "c.ind_NumeroIdentificacion = 880000004"],
        'municipio' => [['c' => 'municipio', 'op' => 'contiene', 'v' => $fragCiudad], "EXISTS (SELECT 1 FROM conf_ciudades ci WHERE ci.ciu_Id = c.ind_IdCiudad AND ci.ciu_Nombre COLLATE Latin1_General_CI_AI LIKE '%" . str_replace("'", "''", $fragCiudad) . "%')"],
    ];
    if ($clave !== 'ica') {
        $casos['períodos 2 y 3'] = [['c' => 'periodo', 'op' => 'in', 'v' => [2, 3]], "d.{$p}_Periodo IN (2, 3)"];
    }
    if (InformeCatalogo::hay($con, "C:{$m['tabla']}.{$p}_IdBancoPago") && $bancos) {
        $casos['banco (por id)'] = [['c' => 'banco', 'op' => 'in', 'v' => [(int) $bancos[0]['ban_Id']]], "$pag AND d.{$p}_IdBancoPago = " . (int) $bancos[0]['ban_Id']];
    }
    foreach ($casos as $nombre => [$filtro, $predicado]) {
        $obtenido = $numerosInforme($clave, $filtro);
        $esperado = $numerosAMano($clave, $predicado);
        $cubiertos++;
        if ($esperado) { $conFilas++; }
        if ($obtenido !== $esperado) { $malos[] = "$clave / $nombre: informe [" . implode(',', array_map(function ($x) { return substr($x, -3); }, $obtenido)) . '] contra a mano [' . implode(',', array_map(function ($x) { return substr($x, -3); }, $esperado)) . ']'; }
    }
}
prueba("cada filtro de las declaraciones da lo mismo que una consulta escrita a mano ($cubiertos comparaciones en los tres impuestos)", !$malos, implode(' | ', array_slice($malos, 0, 4)));
prueba('...y la mayoría de esas comparaciones traía filas (no son pruebas vacías)', $conFilas >= $cubiertos * 0.6, "$conFilas de $cubiertos con filas");

// El padrón (modo "solo contribuyentes").
$con->consultar("UPDATE ind_contribuyentes SET ind_Rut = '4711', ind_Rut_segundo = '5611' WHERE ind_Id = ?", [$cB]);
$con->consultar("UPDATE ind_contribuyentes SET ind_Rut_tercero = '4711' WHERE ind_Id = ?", [$cC]);
$con->consultar("INSERT INTO ind_rit_firmas (rif_IdContribuyente, rif_IdUsuario, rif_Hash) VALUES (?, 1, 'hash-de-prueba')", [$cC]);
$docsPadron = function (array $filtro) use ($con, $ctx, $docsSembrados) {
    $r = ejecutar($con, $ctx, ['v' => 1, 'modo' => 'contribuyente', 'filtros' => $filtro['c'] === 'documento' ? [$filtro] : [['c' => 'documento', 'op' => 'in', 'v' => $docsSembrados], $filtro],
        'columnas' => [['c' => 'documento']], 'orden' => [['c' => 'documento', 'd' => 'asc']]]);
    $d = array_map(function ($f) { return preg_replace('/-\d+$/', '', (string) $f[0]); }, $r['filas']);
    sort($d, SORT_NUMERIC);
    return $d;
};
$docsAMano = function ($predicado) use ($con, $listaDocs) {
    $d = array_map(function ($f) { return (string) $f['d']; },
        todas($con, "SELECT c.ind_NumeroIdentificacion AS d FROM ind_contribuyentes c WHERE c.ind_NumeroIdentificacion IN ($listaDocs) AND ($predicado)"));
    sort($d, SORT_NUMERIC);
    return $d;
};
$casosPadron = [
    'persona jurídica' => [['c' => 'tipoPersona', 'op' => 'in', 'v' => [2]], 'c.ind_Persona = 2'],
    'tipo de documento NIT' => [['c' => 'tipoDocumento', 'op' => 'in', 'v' => [5]], 'c.ind_IdTipoDocumento = 5'],
    'con cese' => [['c' => 'conCese', 'op' => '=', 'v' => true], 'c.ind_FechaCese IS NOT NULL'],
    'sin cese' => [['c' => 'conCese', 'op' => '=', 'v' => false], 'c.ind_FechaCese IS NULL'],
    'régimen simple' => [['c' => 'regimen', 'op' => 'in', 'v' => ['SIMPLE']], "c.ind_RegimenTributario LIKE '%SIMPLE%'"],
    'régimen ordinario (nadie)' => [['c' => 'regimen', 'op' => 'in', 'v' => ['ORDINARIO']], "c.ind_RegimenTributario LIKE '%ORDINARIO%'"],
    'régimen: alguno de dos' => [['c' => 'regimen', 'op' => 'in', 'v' => ['ORDINARIO', 'NO_RESP_IVA']], "c.ind_RegimenTributario LIKE '%ORDINARIO%' OR c.ind_RegimenTributario LIKE '%NO_RESP_IVA%'"],
    'agente de retención' => [['c' => 'agenteRetencion', 'op' => '=', 'v' => true], "c.ind_Responsabilidades LIKE '%AGENTE_RETENCION%'"],
    'no es agente de retención' => [['c' => 'agenteRetencion', 'op' => '=', 'v' => false], "ISNULL(c.ind_Responsabilidades, '') NOT LIKE '%AGENTE_RETENCION%'"],
    'autorretenedor' => [['c' => 'autorretenedor', 'op' => '=', 'v' => true], "c.ind_Responsabilidades LIKE '%AUTORRETENEDOR%'"],
    'nombre con % [ ] _ literales' => [['c' => 'contribuyente', 'op' => 'contiene', 'v' => '100% pérez'], 'c.ind_NumeroIdentificacion = 880000004'],
    'CIIU en cualquiera de las tres casillas (4711)' => [['c' => 'ciiu', 'op' => 'igual', 'v' => '4711'], "c.ind_Rut = '4711' OR c.ind_Rut_segundo = '4711' OR c.ind_Rut_tercero = '4711'"],
    'CIIU solo en la segunda (5611)' => [['c' => 'ciiu', 'op' => 'igual', 'v' => '5611'], "c.ind_Rut_segundo = '5611'"],
    'CIIU que nadie tiene' => [['c' => 'ciiu', 'op' => 'igual', 'v' => '9999'], "c.ind_Rut = '9999'"],
    'fecha de inscripción (todos)' => [['c' => 'fechaInscripcion', 'op' => 'between', 'v' => ['2020-01-01', '2099-12-31']], "CAST(c.ind_FechaCreacion AS DATE) BETWEEN '2020-01-01' AND '2099-12-31'"],
    'fecha de inscripción (nadie)' => [['c' => 'fechaInscripcion', 'op' => 'between', 'v' => ['2000-01-01', '2001-01-01']], "CAST(c.ind_FechaCreacion AS DATE) BETWEEN '2000-01-01' AND '2001-01-01'"],
    'RIT firmado' => [['c' => 'ritFirmado', 'op' => '=', 'v' => true], 'EXISTS (SELECT 1 FROM ind_rit_firmas f WHERE f.rif_IdContribuyente = c.ind_Id)'],
    'RIT sin firmar' => [['c' => 'ritFirmado', 'op' => '=', 'v' => false], 'NOT EXISTS (SELECT 1 FROM ind_rit_firmas f WHERE f.rif_IdContribuyente = c.ind_Id)'],
    'municipio' => [['c' => 'municipio', 'op' => 'contiene', 'v' => $fragCiudad], "EXISTS (SELECT 1 FROM conf_ciudades ci WHERE ci.ciu_Id = c.ind_IdCiudad AND ci.ciu_Nombre COLLATE Latin1_General_CI_AI LIKE '%" . str_replace("'", "''", $fragCiudad) . "%')"],
];
$malosP = [];
$conFilasP = 0;
foreach ($casosPadron as $nombre => [$filtro, $predicado]) {
    $obtenido = $docsPadron($filtro);
    $esperado = $docsAMano($predicado);
    if ($esperado) { $conFilasP++; }
    if ($obtenido !== $esperado) { $malosP[] = "$nombre: informe [" . implode(',', $obtenido) . '] contra a mano [' . implode(',', $esperado) . ']'; }
}
prueba('cada filtro del padrón da lo mismo que una consulta escrita a mano (' . count($casosPadron) . ' comparaciones)', !$malosP, implode(' | ', array_slice($malosP, 0, 4)));
prueba('...y la mayoría traía filas', $conFilasP >= count($casosPadron) * 0.6, "$conFilasP de " . count($casosPadron));

/* ======================================================================
   3. El catálogo: cada columna, cada filtro y cada orden se ejecuta
   ====================================================================== */
seccion('3. Cada columna, filtro y orden del catálogo se ejecuta sin error');
function pruebaCatalogo($con, $ctx, $clave, $etiquetaPrueba)
{
    $f = InformeCatalogo::fuente($con, $clave);
    if (!$f) { prueba("$etiquetaPrueba: la fuente existe", false); return; }
    $modo = $f['modo'];
    $impuesto = $f['impuesto'];
    $mal = [];
    foreach ($f['columnas'] as $c => $def) {
        $cfg = ['v' => 1, 'modo' => $modo, 'impuesto' => $impuesto, 'filtros' => [], 'columnas' => [['c' => $c]],
                'orden' => $def['orden'] === false ? [] : [['c' => $c, 'd' => 'desc']]];
        try {
            $v = InformeConstructor::validar($con, $cfg, $ctx, 'estricto');
            $q = InformeConstructor::armar($v);
            InformeConstructor::contar($con, $q);
            InformeConstructor::pagina($con, $q, 1, 5);
        } catch (\Throwable $e) { $mal[] = "$c: " . substr($e->getMessage(), 0, 160); }
    }
    prueba("$etiquetaPrueba: las " . count($f['columnas']) . ' columnas (cada una sola, con su orden) corren', !$mal, implode(' | ', array_slice($mal, 0, 3)));

    // Todas juntas, en bloques de 30 (el máximo por informe es 60).
    $mal = [];
    foreach (array_chunk(array_keys($f['columnas']), 30) as $i => $bloque) {
        $cfg = ['v' => 1, 'modo' => $modo, 'impuesto' => $impuesto, 'filtros' => [], 'columnas' => array_map(function ($c) { return ['c' => $c]; }, $bloque), 'orden' => []];
        try {
            $q = InformeConstructor::armar(InformeConstructor::validar($con, $cfg, $ctx, 'estricto'));
            InformeConstructor::pagina($con, $q, 1, 20);
        } catch (\Throwable $e) { $mal[] = "bloque $i: " . substr($e->getMessage(), 0, 160); }
    }
    prueba("$etiquetaPrueba: todas las columnas juntas (en bloques) corren", !$mal, implode(' | ', $mal));

    $muestras = function ($def) use ($con) {
        $r = [];
        foreach ($def['ops'] as $op) {
            switch ($def['tipo']) {
                case 'entero':
                    $r[] = [$op, $op === 'between' ? [2020, 2030] : [2026]];
                    break;
                case 'dinero':
                    $r[] = [$op, $op === 'between' ? [0, 1000000000] : 0];
                    break;
                case 'fecha':
                    $r[] = [$op, $op === 'between' ? ['2026-01-01', '2026-12-31'] : '2026-06-30'];
                    break;
                case 'lista':
                    $opc = InformeCatalogo::opcionesFiltro($con, $def);
                    $k = array_keys($opc);
                    if ($k) { $r[] = [$op, [$k[0]]]; if (count($k) > 1) { $r[] = [$op, [$k[0], $k[1]]]; } }
                    break;
                case 'texto':
                    $r[] = [$op, !empty($def['ciiu']) ? '1234' : 'Pe ña'];
                    break;
                case 'booleano':
                    $r[] = [$op, true];
                    $r[] = [$op, false];
                    break;
            }
        }
        return $r;
    };
    $mal = [];
    $n = 0;
    foreach ($f['filtros'] as $c => $def) {
        foreach ($muestras($def) as [$op, $v]) {
            $cfg = ['v' => 1, 'modo' => $modo, 'impuesto' => $impuesto, 'filtros' => [['c' => $c, 'op' => $op, 'v' => $v]],
                    'columnas' => [['c' => array_keys($f['columnas'])[0]]], 'orden' => []];
            try {
                $q = InformeConstructor::armar(InformeConstructor::validar($con, $cfg, $ctx, 'estricto'));
                InformeConstructor::contar($con, $q);
                InformeConstructor::pagina($con, $q, 1, 5);
                $n++;
            } catch (\Throwable $e) { $mal[] = "$c $op: " . substr($e->getMessage(), 0, 160); }
        }
    }
    prueba("$etiquetaPrueba: los " . count($f['filtros']) . " filtros ($n combinaciones de operador y valor) corren", !$mal, implode(' | ', array_slice($mal, 0, 3)));
}
foreach (['ica', 'reteica', 'autorreteica', 'contribuyentes'] as $clave) { pruebaCatalogo($con, $ctx, $clave, $clave); }

// Todas las plantillas del sistema, en todas las fuentes que declaran, corren.
$mal = [];
$n = 0;
foreach (InformePlantillas::listar($con, $ctx) as $pl) {
    if (!$pl['sistema']) { continue; }
    foreach ($pl['fuentes'] as $fu) {
        try {
            $p = InformePlantillas::obtener($con, $ctx, $pl['id'], $fu);
            if ($p['avisos']) { $mal[] = $pl['id'] . "/$fu: avisos " . json_encode($p['avisos']); }
            $r = ejecutar($con, $ctx, $p['config']);
            $n++;
        } catch (\Throwable $e) { $mal[] = $pl['id'] . "/$fu: " . substr($e->getMessage(), 0, 160); }
    }
}
prueba("las plantillas del sistema corren en cada impuesto que declaran ($n combinaciones) y sin avisos", !$mal && $n >= 20, implode(' | ', $mal));

/* ======================================================================
   4. Inyección y validación de la configuración
   ====================================================================== */
seccion('4. Inyección y validación de la configuración');
$estr = function (array $cfg) use ($con, $ctx) { return InformeConstructor::validar($con, $cfg, $ctx, 'estricto'); };
$basecfg = ['v' => 1, 'modo' => 'declaracion', 'impuesto' => 'ica', 'filtros' => [], 'columnas' => [['c' => 'documento']], 'orden' => []];
$antesUsuarios = (int) uno($con, 'SELECT COUNT(*) AS n FROM conf_usuarios')['n'];
$malas = ["documento'; DROP TABLE ind_informe_plantillas;--", 'c.ind_Id', '1=1', 'documento) UNION SELECT usu_Password FROM conf_usuarios--',
          "documento\0", 'DOCUMENTO', 'documento ', '', 'ind_contribuyentes.ind_Email', '../../etc/passwd', 'document%', '*'];
$todasRechazadas = true;
foreach ($malas as $m) {
    $e = lanza(function () use ($estr, $basecfg, $m) { $c = $basecfg; $c['columnas'] = [['c' => $m]]; $estr($c); });
    if (!$e instanceof InformeException) { $todasRechazadas = false; echo "    sin rechazo (columna): " . json_encode($m) . ($e ? ' -> ' . $e->getMessage() : '') . "\n"; }
}
prueba('una clave de columna que no está en el catálogo se rechaza (12 intentos de inyección)', $todasRechazadas);
$todasRechazadas = true;
foreach ($malas as $m) {
    $e = lanza(function () use ($estr, $basecfg, $m) { $c = $basecfg; $c['filtros'] = [['c' => $m, 'op' => 'in', 'v' => [1]]]; $estr($c); });
    if (!$e instanceof InformeException) { $todasRechazadas = false; echo "    sin rechazo (filtro): " . json_encode($m) . "\n"; }
    $e = lanza(function () use ($estr, $basecfg, $m) { $c = $basecfg; $c['orden'] = [['c' => $m, 'd' => 'asc']]; $estr($c); });
    if (!$e instanceof InformeException) { $todasRechazadas = false; echo "    sin rechazo (orden): " . json_encode($m) . "\n"; }
}
prueba('una clave de filtro o de orden que no está en el catálogo se rechaza', $todasRechazadas);
$todasRechazadas = true;
foreach (['=; DROP TABLE x', 'like', 'LIKE', '<>', 'OR 1=1', '', "in'", 'contiene', 'between '] as $op) {
    $e = lanza(function () use ($estr, $basecfg, $op) { $c = $basecfg; $c['filtros'] = [['c' => 'anio', 'op' => $op, 'v' => [2026]]]; $estr($c); });
    if (!$e instanceof InformeException) { $todasRechazadas = false; echo "    sin rechazo (operador): " . json_encode($op) . "\n"; }
}
prueba('un operador fuera de la lista del filtro se rechaza', $todasRechazadas);
$todasRechazadas = true;
foreach (['desc; DROP TABLE x', 'DESC ', 'sideways', 'asc,documento', 1, ['asc']] as $d) {
    $e = lanza(function () use ($estr, $basecfg, $d) { $c = $basecfg; $c['orden'] = [['c' => 'documento', 'd' => $d]]; $estr($c); });
    if (!$e instanceof InformeException && !in_array($d, ['DESC '], true)) { $todasRechazadas = false; echo "    sin rechazo (sentido): " . json_encode($d) . "\n"; }
}
prueba('un sentido de orden que no es asc ni desc se rechaza', $todasRechazadas);
// Valores del tipo equivocado.
$valoresMalos = [
    ['anio', 'in', ['2026 OR 1=1']], ['anio', 'in', "2026'; DROP TABLE x;--"], ['anio', 'in', [[2026]]], ['anio', 'in', [2026.5]], ['anio', 'in', [1500]],
    ['anio', 'between', [2026, 2025]], ['anio', 'between', [2026]], ['documento', 'in', ['1 OR 1=1']], ['documento', 'in', [0]], ['documento', 'in', [99999999999]],
    ['fechaPago', 'between', ['2026-02-30', '2026-03-01']], ['fechaPago', '>=', "2026-01-01' OR '1'='1"], ['fechaPago', '>=', '01/02/2026'], ['fechaPago', 'between', ['2026-01-01']],
    ['valorDeclarado', '>=', "0 OR 1=1"], ['valorDeclarado', 'between', [10, 'x']], ['estado', 'in', ['pagada OR 1=1']], ['estado', 'in', ['cualquiera']], ['estado', 'in', []],
    ['contribuyente', 'contiene', ''], ['contribuyente', 'contiene', str_repeat('a', 101)], ['contribuyente', 'contiene', ['x']], ['corregida', '=', 'quizas'],
    ['tipoPersona', 'in', ['1; DROP']], ['canal', 'in', ['PSE', 'inexistente']],
];
$todasRechazadas = true;
foreach ($valoresMalos as [$c, $op, $v]) {
    $e = lanza(function () use ($estr, $basecfg, $c, $op, $v) { $cfg = $basecfg; $cfg['filtros'] = [['c' => $c, 'op' => $op, 'v' => $v]]; $estr($cfg); });
    if (!$e instanceof InformeException) { $todasRechazadas = false; echo "    sin rechazo (valor): $c $op " . json_encode($v) . ($e ? ' -> ' . $e->getMessage() : '') . "\n"; }
}
prueba('los valores del tipo equivocado o con SQL se rechazan (' . count($valoresMalos) . ' casos)', $todasRechazadas);
// Un texto con comillas, % y [ NO es SQL: se busca como texto y no devuelve todo.
$totalSinFiltro = ejecutar($con, $ctx, $basecfg)['total'];
foreach (["' OR '1'='1", "x' OR 1=1 --", '%', '_', '[', '[a-z]', "Robert'); DROP TABLE ind_contribuyentes;--"] as $texto) {
    $cfg = $basecfg;
    $cfg['filtros'] = [['c' => 'contribuyente', 'op' => 'contiene', 'v' => $texto]];
    $r = ejecutar($con, $ctx, $cfg);
    prueba('el texto ' . json_encode($texto) . ' se busca como texto (no trae todo ni rompe)', $r['total'] < $totalSinFiltro || $totalSinFiltro === 0, "total {$r['total']} de $totalSinFiltro");
    prueba('   y va como parámetro: el SQL no lo contiene', strpos($r['q']['desde'], 'DROP') === false && strpos($r['q']['desde'], "1'='1") === false && in_array('%' . strtr(preg_split('/\s+/', $texto)[0], ['[' => '[[]', '%' => '[%]', '_' => '[_]']) . '%', $r['q']['params'], true));
}
$cfg = $basecfg;
$cfg['filtros'] = [['c' => 'contribuyente', 'op' => 'contiene', 'v' => 'peñaranda']];
prueba('la búsqueda de texto no distingue mayúsculas ni tildes ("penaranda" encuentra "Peñaranda")',
    ejecutar($con, $ctx, array_replace($cfg, ['filtros' => [['c' => 'contribuyente', 'op' => 'contiene', 'v' => 'PENARANDA']]]))['total'] > 0);
prueba('y cada palabra debe aparecer ("maria penaranda")', ejecutar($con, $ctx, array_replace($cfg, ['filtros' => [['c' => 'contribuyente', 'op' => 'contiene', 'v' => 'maria penaranda']]]))['total'] > 0
    && ejecutar($con, $ctx, array_replace($cfg, ['filtros' => [['c' => 'contribuyente', 'op' => 'contiene', 'v' => 'maria zzz']]]))['total'] === 0);
// Títulos.
$e = lanza(function () use ($estr, $basecfg) { $c = $basecfg; $c['columnas'] = [['c' => 'documento', 't' => '<script>alert(1)</script>']]; $estr($c); });
prueba('un título con < o > se rechaza', $e instanceof InformeException);
$e = lanza(function () use ($estr, $basecfg) { $c = $basecfg; $c['columnas'] = [['c' => 'documento', 't' => "Doc\x01umento"]]; $estr($c); });
prueba('un título con caracteres de control se rechaza', $e instanceof InformeException);
$e = lanza(function () use ($estr, $basecfg) { $c = $basecfg; $c['columnas'] = [['c' => 'documento', 't' => str_repeat('ñ', 61)]]; $estr($c); });
prueba('un título de más de 60 caracteres se rechaza', $e instanceof InformeException);
$e = lanza(function () use ($estr, $basecfg) { $c = $basecfg; $c['columnas'] = [['c' => 'documento', 't' => ['x']]]; $estr($c); });
prueba('un título que no es texto se rechaza', $e instanceof InformeException);
$titulo = "NIT \"del\" contribuyente' ; DROP TABLE x; -- =HYPERLINK(1)";
$c = $basecfg;
$c['columnas'] = [['c' => 'documento', 't' => $titulo]];
$v = $estr($c);
$q = InformeConstructor::armar($v);
prueba('un título con comillas y SQL es texto plano: se acepta, sale tal cual y NO llega al SQL',
    $v['config']['columnas'][0]['t'] === $titulo && strpos($q['select'] . $q['desde'] . $q['orden'], 'DROP') === false && $q['columnas'][0]['titulo'] === $titulo);
$v = $estr(array_replace($basecfg, ['columnas' => [['c' => 'documento', 't' => "  NIT   \n  del   contribuyente  "]]]));
prueba('los espacios y saltos de línea del título se normalizan', $v['config']['columnas'][0]['t'] === 'NIT del contribuyente');
$v = $estr(array_replace($basecfg, ['columnas' => [['c' => 'documento', 't' => 'NIT / Documento']]]));
prueba('un título igual al del catálogo no se guarda (sigue al catálogo)', !isset($v['config']['columnas'][0]['t']));
// Topes.
$e = lanza(function () use ($estr, $basecfg) { $c = $basecfg; $c['filtros'] = [['c' => 'anio', 'op' => 'in', 'v' => range(2000, 2050)]]; $estr($c); });
prueba('un "in" con 51 valores se rechaza', $e instanceof InformeException);
$v = $estr(array_replace($basecfg, ['filtros' => [['c' => 'anio', 'op' => 'in', 'v' => range(2001, 2050)]]]));
prueba('y con 50 valores se acepta', count($v['config']['filtros'][0]['v']) === 50);
$todas = array_keys(InformeCatalogo::fuente($con, 'ica')['columnas']);
$e = lanza(function () use ($estr, $basecfg, $todas) { $c = $basecfg; $c['columnas'] = array_map(function ($x) { return ['c' => $x]; }, array_slice($todas, 0, 61)); $estr($c); });
prueba('más de 60 columnas se rechaza', $e instanceof InformeException);
$e = lanza(function () use ($estr, $basecfg) { $c = $basecfg; $c['columnas'] = [['c' => 'documento'], ['c' => 'documento']]; $estr($c); });
prueba('una columna repetida se rechaza', $e instanceof InformeException);
$e = lanza(function () use ($estr, $basecfg) { $c = $basecfg; $c['filtros'] = [['c' => 'anio', 'op' => 'in', 'v' => [2026]], ['c' => 'anio', 'op' => 'in', 'v' => [2025]]]; $estr($c); });
prueba('un filtro repetido se rechaza', $e instanceof InformeException);
$e = lanza(function () use ($estr, $basecfg) { $c = $basecfg; $c['orden'] = [['c' => 'documento'], ['c' => 'anio'], ['c' => 'numero'], ['c' => 'estado']]; $estr($c); });
prueba('más de 3 criterios de orden se rechaza', $e instanceof InformeException);
$e = lanza(function () use ($estr, $basecfg) { $c = $basecfg; $c['filtros'] = array_fill(0, 21, ['c' => 'anio', 'op' => 'in', 'v' => [2026]]); $estr($c); });
prueba('más de 20 filtros se rechaza', $e instanceof InformeException);
$e = lanza(function () { InformeConstructor::leerJson(str_repeat('a', 1024 * 1024)); });
prueba('una configuración de 1 MB se rechaza antes de leerla', $e instanceof InformeException);
$e = lanza(function () { InformeConstructor::leerJson(str_repeat('[', 50) . str_repeat(']', 50)); });
prueba('un JSON demasiado profundo se rechaza', $e instanceof InformeException);
$e = lanza(function () { InformeConstructor::leerJson('{"modo": '); });
prueba('un JSON roto se rechaza', $e instanceof InformeException);
foreach ([['modo' => 'xx'], ['modo' => 'declaracion', 'impuesto' => 'predial'], ['modo' => 'declaracion'], ['modo' => 'declaracion', 'impuesto' => ['ica']], []] as $malo) {
    $e = lanza(function () use ($estr, $malo) { $estr($malo + ['columnas' => [['c' => 'documento']]]); });
    prueba('un modo o impuesto inválido se rechaza: ' . json_encode($malo), $e instanceof InformeException);
}
$e = lanza(function () use ($con, $ctx, $basecfg) { $c = $basecfg; $c['columnas'] = []; InformeConstructor::armar(InformeConstructor::validar($con, $c, $ctx, 'estricto')); });
prueba('un informe sin columnas no se ejecuta', $e instanceof InformeException);
prueba('después de todos los intentos, la base está intacta (usuarios, plantillas y contribuyentes)',
    (int) uno($con, 'SELECT COUNT(*) AS n FROM conf_usuarios')['n'] === $antesUsuarios
    && ($hayPlantillas ? (int) uno($con, 'SELECT COUNT(*) AS n FROM ind_informe_plantillas')['n'] >= 0 : true)
    && (int) uno($con, 'SELECT COUNT(*) AS n FROM ind_contribuyentes WHERE ind_NumeroIdentificacion = 880000001')['n'] === 2);

/* ======================================================================
   5. Permisos en el servidor
   ====================================================================== */
seccion('5. Permisos en el servidor');
$cfgIca = ['v' => 1, 'modo' => 'declaracion', 'impuesto' => 'ica', 'filtros' => [], 'columnas' => [['c' => 'documento'], ['c' => 'estado']], 'orden' => []];
$cfgConCorreo = $cfgIca;
$cfgConCorreo['columnas'][] = ['c' => 'correo'];
$cfgRet = array_replace($cfgIca, ['impuesto' => 'reteica']);
$cfgPadron = ['v' => 1, 'modo' => 'contribuyente', 'impuesto' => null, 'filtros' => [], 'columnas' => [['c' => 'documento']], 'orden' => []];

comoUsuario(0);
$j = llamar('catalogo');
prueba('sin sesión: sinSesion y nada de datos', ($j['ok'] ?? 1) === 0 && ($j['sinSesion'] ?? 0) === 1 && empty($j['datos']));

comoUsuario($cuentas['sin_perm']);
$j = llamar('catalogo');
prueba('un rol de la Alcaldía sin "Usar el Informe general" (aunque tenga el Informe rápido): sinPermiso', ($j['ok'] ?? 1) === 0 && ($j['sinPermiso'] ?? 0) === 1 && empty($j['datos']), json_encode($j));
foreach (['plantillas', 'vista', 'guardar', 'borrar', 'plantilla'] as $fn) {
    comoUsuario($cuentas['sin_perm']);
    $j = llamar($fn, ['config' => json_encode($cfgIca), 'nombre' => 'x', 'id' => 'sistema:todas']);
    prueba("   y tampoco puede «{$fn}»", ($j['ok'] ?? 1) === 0 && ($j['sinPermiso'] ?? 0) === 1);
}

comoUsuario($cuentas['contrib']);
$j = llamar('catalogo');
prueba('un rol de contribuyente con las claves prendidas por error no gana poderes de funcionario (alcaldia.* no cuenta)', ($j['ok'] ?? 1) === 0 && ($j['sinPermiso'] ?? 0) === 1, json_encode($j));

comoUsuario($cuentas['rolInactivo']);
$j = llamar('catalogo');
prueba('un rol inactivo no puede nada', ($j['ok'] ?? 1) === 0);

comoUsuario($cuentas['cuentaInactiva']);
$j = llamar('catalogo');
prueba('una cuenta inactiva sale de la sesión (sinSesion)', ($j['ok'] ?? 1) === 0 && ($j['sinSesion'] ?? 0) === 1, json_encode($j));

comoUsuario($cuentas['solo_ica']);
$j = llamar('catalogo');
$fu = $j['datos']['fuentes'] ?? [];
// Cada impuesto que se puede ver trae además su versión «una fila por contribuyente» (clave ica:agrup, mismo permiso).
$sinAgrup = function (array $fuentes) { return array_values(array_filter(array_keys($fuentes), function ($k) { return strpos($k, ':agrup') === false; })); };
prueba('solo el ICA: el catálogo ofrece únicamente el ICA (y su versión agrupada por contribuyente)', ($j['ok'] ?? 0) === 1 && array_keys($fu) === ['ica', 'ica:agrup'] && $sinAgrup($fu) === ['ica'], json_encode(array_keys($fu)));
$colsIca = array_column($fu['ica']['columnas'] ?? [], 'c');
prueba('sin el permiso de contacto el catálogo ni menciona las columnas de datos personales',
    !array_intersect(['correo', 'telefono', 'direccion', 'representante', 'contador', 'revisor', 'contadorCorreo', 'revisorCorreo', 'representanteCorreo'], $colsIca) && in_array('documento', $colsIca, true));
prueba('el catálogo dice qué puede esta persona', ($j['datos']['puede']['contacto'] ?? 1) === false && ($j['datos']['puede']['plantillas'] ?? 1) === false);
comoUsuario($cuentas['solo_ica']);
$j = llamar('vista', ['config' => json_encode($cfgConCorreo)]);
prueba('pedir la columna de correo en línea sin el permiso de contacto: sinPermiso', ($j['ok'] ?? 1) === 0 && ($j['sinPermiso'] ?? 0) === 1, json_encode($j));
foreach (['telefono', 'direccion', 'representante', 'representanteCorreo', 'contador', 'contadorCorreo', 'revisor', 'revisorCorreo', 'contadorTarjeta'] as $colSens) {
    comoUsuario($cuentas['solo_ica']);
    $cfg = $cfgIca;
    $cfg['columnas'][] = ['c' => $colSens];
    $j = llamar('vista', ['config' => json_encode($cfg)]);
    if (($j['sinPermiso'] ?? 0) !== 1) { prueba("sin contacto no se puede pedir «{$colSens}»", false, json_encode($j)); }
}
prueba('ninguna columna de datos personales se puede pedir sin el permiso de contacto', true);
comoUsuario($cuentas['solo_ica']);
$j = llamar('vista', ['config' => json_encode($cfgRet)]);
prueba('pedir retención sin ver retención: sinPermiso', ($j['ok'] ?? 1) === 0 && ($j['sinPermiso'] ?? 0) === 1, json_encode($j));
comoUsuario($cuentas['solo_ica']);
$j = llamar('vista', ['config' => json_encode($cfgPadron)]);
prueba('pedir contribuyentes sin ver contribuyentes: sinPermiso', ($j['ok'] ?? 1) === 0 && ($j['sinPermiso'] ?? 0) === 1);
comoUsuario($cuentas['solo_ica']);
$j = llamar('vista', ['config' => json_encode($cfgIca)]);
prueba('lo que SÍ puede, funciona (ICA sin contacto)', ($j['ok'] ?? 0) === 1 && isset($j['datos']['filas']), json_encode($j));
// Contacto por filtro u orden: no hay filtros ni órdenes sobre datos personales.
comoUsuario($cuentas['solo_ica']);
$cfg = $cfgIca;
$cfg['orden'] = [['c' => 'correo', 'd' => 'asc']];
$j = llamar('vista', ['config' => json_encode($cfg)]);
prueba('ordenar por una columna de contacto sin el permiso: rechazado', ($j['ok'] ?? 1) === 0);

comoUsuario($cuentas['contacto']);
$j = llamar('vista', ['config' => json_encode($cfgConCorreo), 'porPagina' => 100]);
$filaCorreo = null;
if (($j['ok'] ?? 0) === 1) {
    $iC = array_search('correo', array_column($j['datos']['columnas'], 'c'), true);
    foreach ($j['datos']['filas'] as $fl) { if ($fl[$iC] !== null) { $filaCorreo = $fl[$iC]; } }
}
prueba('con el permiso de contacto sí puede incluir el correo', ($j['ok'] ?? 0) === 1 && $filaCorreo !== null, json_encode($j['mensaje'] ?? ''));
comoUsuario($cuentas['contacto']);
$j = llamar('vista', ['config' => json_encode($cfgPadron)]);
prueba('con retención pero sin contribuyentes: el padrón sigue prohibido', ($j['sinPermiso'] ?? 0) === 1);
comoUsuario($cuentas['contacto']);
$j = llamar('catalogo');
prueba('con el permiso de contacto el catálogo ofrece ICA y retención (no autorretención)', $sinAgrup($j['datos']['fuentes'] ?? []) === ['ica', 'reteica']);

comoUsuario($cuentas['admin']);
$j = llamar('catalogo');
prueba('el administrador lo tiene todo: las cuatro fuentes', $sinAgrup($j['datos']['fuentes'] ?? []) === ['ica', 'reteica', 'autorreteica', 'contribuyentes']
    && array_keys($j['datos']['fuentes']) === ['ica', 'reteica', 'autorreteica', 'contribuyentes', 'ica:agrup', 'reteica:agrup', 'autorreteica:agrup']);
$j = llamar('catalogo');
$todasCols = [];
foreach ($j['datos']['fuentes'] as $fte) { foreach ($fte['columnas'] as $c) { $todasCols[$c['c']] = $c; } }
prueba('...y las columnas de contacto vienen marcadas como tales', ($todasCols['correo']['contacto'] ?? false) === true && ($todasCols['documento']['contacto'] ?? true) === false);

// El método y la cabecera.
comoUsuario($cuentas['admin']);
$j = llamar('catalogo', [], true, 'GET');
prueba('GET no sirve (405 y sin datos)', ($j['ok'] ?? 1) === 0 && http_response_code() === 405 && empty($j['datos']));
http_response_code(200);
comoUsuario($cuentas['admin']);
$j = llamar('catalogo', [], false);
prueba('sin X-Requested-With se rechaza', ($j['ok'] ?? 1) === 0 && empty($j['datos']));
comoUsuario($cuentas['admin']);
$j = llamar('noExiste');
prueba('una función desconocida se rechaza', ($j['ok'] ?? 1) === 0);
comoUsuario($cuentas['admin']);
$j = llamar('vista', ['config' => ['x' => 1]]);
prueba('un campo que llega como arreglo no rompe nada (mensaje claro)', ($j['ok'] ?? 1) === 0 && isset($j['mensaje']) && strpos($j['mensaje'], 'Falta') === 0, json_encode($j));
comoUsuario($cuentas['admin']);
$j = llamar('vista', ['config' => '{"modo":"declaracion","impuesto":"ica","columnas":[{"c":"x\u0000"}]}']);
prueba('un error de configuración se explica (sin SQL ni traza)', ($j['ok'] ?? 1) === 0 && strpos($j['mensaje'], 'SELECT') === false && strpos($j['mensaje'], 'Stack') === false);

// La sesión se suelta apenas se sabe quién es: una vista grande no deja esperando las demás pestañas.
foreach (['catalogo' => [], 'plantillas' => [], 'vista' => ['config' => json_encode($cfgIca)]] as $fn => $post) {
    comoUsuario($cuentas['admin']);
    $_SESSION['marca_prueba'] = 'x';
    $j = llamar($fn, $post);
    prueba('«' . $fn . '» suelta la sesión antes de armar la respuesta (session_write_close)', ($j['ok'] ?? 0) === 1 && session_status() === PHP_SESSION_NONE, 'estado de la sesión: ' . session_status());
}

/* ======================================================================
   6. Plantillas
   ====================================================================== */
seccion('6. Plantillas: sistema, privadas y compartidas');
comoUsuario($cuentas['admin']);
$j = llamar('plantillas');
$ids = array_column($j['datos']['plantillas'] ?? [], 'id');
prueba('el listado trae las plantillas del sistema', in_array('sistema:todas', $ids, true) && in_array('sistema:sinpagar', $ids, true) && in_array('sistema:pagadas', $ids, true) && in_array('sistema:padron', $ids, true), implode(',', $ids));
$sis = array_values(array_filter($j['datos']['plantillas'], function ($p) { return $p['sistema']; }));
prueba('las del sistema no se pueden editar ni borrar', !array_filter($sis, function ($p) { return $p['puedeEditar'] || $p['puedeBorrar']; }));

$cfgPlantilla = ['v' => 1, 'modo' => 'declaracion', 'impuesto' => 'ica', 'filtros' => [['c' => 'estado', 'op' => 'in', 'v' => ['sinpagar']]],
                 'columnas' => [['c' => 'documento'], ['c' => 'contribuyente', 't' => 'Deudor'], ['c' => 'valorDeclarado']], 'orden' => [['c' => 'valorDeclarado', 'd' => 'desc']]];
if (!$hayPlantillas) {
    comoUsuario($cuentas['admin']);
    $j = llamar('guardar', ['nombre' => 'X', 'config' => json_encode($cfgPlantilla)]);
    prueba('sin la tabla de la 056: guardar avisa con claridad que falta la migración', ($j['ok'] ?? 1) === 0 && strpos($j['mensaje'], '056') !== false);
    echo "  (la base no tiene la migración 056: se omiten las pruebas de plantillas guardadas)\n";
} else {
    // Un usuario sin el permiso de plantillas.
    comoUsuario($cuentas['solo_ica']);
    $j = llamar('guardar', ['nombre' => 'Mi cartera', 'descripcion' => 'Lo que debo cobrar', 'visibilidad' => 'PRIVADA', 'config' => json_encode($cfgPlantilla)]);
    $idPrivada = $j['datos']['id'] ?? null;
    prueba('cualquiera con el Informe general guarda una plantilla PRIVADA', ($j['ok'] ?? 0) === 1 && $idPrivada && ($j['datos']['visibilidad'] ?? '') === 'PRIVADA' && ($j['datos']['propia'] ?? false) === true, json_encode($j));
    comoUsuario($cuentas['solo_ica']);
    $j = llamar('guardar', ['nombre' => 'Para todos', 'visibilidad' => 'MUNICIPIO', 'config' => json_encode($cfgPlantilla)]);
    prueba('sin el permiso de plantillas no puede compartir', ($j['ok'] ?? 1) === 0 && ($j['sinPermiso'] ?? 0) === 1, json_encode($j));
    comoUsuario($cuentas['solo_ica']);
    $j = llamar('guardar', ['nombre' => 'Mi cartera', 'visibilidad' => 'PRIVADA', 'config' => json_encode($cfgPlantilla)]);
    prueba('el nombre no se repite entre las propias', ($j['ok'] ?? 1) === 0 && strpos($j['mensaje'], 'Ya existe') === 0, json_encode($j));
    foreach (['Todas las declaraciones presentadas', 'todas las declaraciones presentadas'] as $reservado) {
        comoUsuario($cuentas['solo_ica']);
        $j = llamar('guardar', ['nombre' => $reservado, 'config' => json_encode($cfgPlantilla)]);
        prueba("el nombre de una plantilla del sistema está reservado ($reservado)", ($j['ok'] ?? 1) === 0);
    }
    foreach (['<b>x</b>' => 'con HTML', '' => 'vacío', str_repeat('n', 101) => 'de 101 caracteres'] as $nombreMalo => $queEs) {
        comoUsuario($cuentas['solo_ica']);
        $j = llamar('guardar', ['nombre' => $nombreMalo, 'config' => json_encode($cfgPlantilla)]);
        prueba("un nombre $queEs se rechaza", ($j['ok'] ?? 1) === 0);
    }
    comoUsuario($cuentas['solo_ica']);
    $j = llamar('guardar', ['nombre' => 'Con descripción larga', 'descripcion' => str_repeat('d', 301), 'config' => json_encode($cfgPlantilla)]);
    prueba('una descripción de más de 300 caracteres se rechaza', ($j['ok'] ?? 1) === 0);
    comoUsuario($cuentas['solo_ica']);
    $j = llamar('guardar', ['nombre' => 'Sin columnas', 'config' => json_encode(array_replace($cfgPlantilla, ['columnas' => []]))]);
    prueba('no se guarda una plantilla sin columnas', ($j['ok'] ?? 1) === 0);
    // NVARCHAR(100) cuenta unidades UTF-16: 50 emojis caben exactos (100) y 51 no (se explica, no es un fallo del servidor).
    comoUsuario($cuentas['solo_ica']);
    $j = llamar('guardar', ['nombre' => str_repeat('😀', 51), 'config' => json_encode($cfgPlantilla)]);
    prueba('un nombre de 51 emojis (102 unidades UTF-16) se rechaza con un mensaje claro', ($j['ok'] ?? 1) === 0 && strpos($j['mensaje'], 'hasta 100') !== false, json_encode($j));
    comoUsuario($cuentas['solo_ica']);
    $j = llamar('guardar', ['nombre' => str_repeat('😀', 50), 'config' => json_encode($cfgPlantilla)]);
    prueba('un nombre de 50 emojis (100 unidades) cabe y se guarda', ($j['ok'] ?? 0) === 1 && $j['datos']['nombre'] === str_repeat('😀', 50), json_encode($j));
    // Una plantilla grande (más de 4.000 caracteres de JSON) se guarda y se abre completa, con tildes y emojis en los títulos.
    $colsGrandes = [];
    foreach (array_slice(array_keys(InformeCatalogo::fuente($con, 'ica')['columnas']), 0, 60) as $i => $clave) {
        if (!empty(InformeCatalogo::fuente($con, 'ica')['columnas'][$clave]['sens'])) { continue; }
        $colsGrandes[] = ['c' => $clave, 't' => 'Título ñandú ✓ ' . $i . ' ' . str_repeat('x', 40)];
    }
    $cfgGrande = array_replace($cfgPlantilla, ['columnas' => $colsGrandes]);
    comoUsuario($cuentas['solo_ica']);
    $j = llamar('guardar', ['nombre' => 'Plantilla grande ñ', 'descripcion' => 'Descripción con tildes y 😀', 'config' => json_encode($cfgGrande)]);
    $idGrande = $j['datos']['id'] ?? null;
    prueba('una plantilla grande (' . strlen(json_encode($cfgGrande)) . ' caracteres de JSON) se guarda', ($j['ok'] ?? 0) === 1 && $idGrande, json_encode($j));
    comoUsuario($cuentas['solo_ica']);
    $j = llamar('plantilla', ['id' => $idGrande]);
    $abiertas = $j['datos']['config']['columnas'] ?? [];
    prueba('...y se abre completa, con los títulos intactos', count($abiertas) === count($colsGrandes) && $abiertas === array_map(function ($c) { return ['c' => $c['c'], 't' => $c['t']]; }, $colsGrandes) && $j['datos']['plantilla']['descripcion'] === 'Descripción con tildes y 😀', 'columnas: ' . count($abiertas) . ' de ' . count($colsGrandes));
    comoUsuario($cuentas['solo_ica']);
    $j = llamar('vista', ['config' => json_encode($j['datos']['config'])]);
    prueba('...y esa configuración corre', ($j['ok'] ?? 0) === 1, json_encode($j['mensaje'] ?? ''));
    // El error de índice único del driver es el que reconoce el guardado cuando dos personas guardan el mismo nombre a la vez.
    $duplicado = null;
    try {
        $con->consultar("INSERT INTO ind_informe_plantillas (ipl_Nombre, ipl_Fuente, ipl_Config, ipl_IdUsuario) VALUES (N'PI carrera', 'ica', N'{}', ?)", [$cuentas['solo_ica']]);
        $con->consultar("INSERT INTO ind_informe_plantillas (ipl_Nombre, ipl_Fuente, ipl_Config, ipl_IdUsuario) VALUES (N'PI carrera', 'ica', N'{}', ?)", [$cuentas['solo_ica']]);
    } catch (\Throwable $e) { $duplicado = $e; }
    $rep = new ReflectionMethod(InformePlantillas::class, '_esRepetido');
    $rep->setAccessible(true);
    prueba('el índice único frena un nombre repetido y el guardado reconoce ese error del driver', $duplicado !== null && $rep->invoke(null, $duplicado) === true, $duplicado ? substr($duplicado->getMessage(), 0, 200) : 'no hubo error');
    prueba('...y no confunde con otros errores', $rep->invoke(null, new \Exception('Error al ejecutar la consulta: Invalid column name')) === false);
    $con->consultar("DELETE FROM ind_informe_plantillas WHERE ipl_Nombre = N'PI carrera'");
    comoUsuario($cuentas['solo_ica']);
    $j = llamar('guardar', ['nombre' => 'Con contacto', 'config' => json_encode($cfgConCorreo)]);
    prueba('no se guarda una plantilla con columnas de contacto sin el permiso', ($j['ok'] ?? 1) === 0 && ($j['sinPermiso'] ?? 0) === 1);

    // Compartida por alguien con el permiso (y con contacto, para probar la reautorización).
    comoUsuario($cuentas['todo']);
    $cfgContacto = $cfgIca;
    $cfgContacto['columnas'] = [['c' => 'documento'], ['c' => 'contribuyente'], ['c' => 'correo'], ['c' => 'valorDeclarado']];
    $j = llamar('guardar', ['nombre' => 'Cartera con correos', 'descripcion' => 'Compartida', 'visibilidad' => 'MUNICIPIO', 'config' => json_encode($cfgContacto)]);
    $idCompartida = $j['datos']['id'] ?? null;
    prueba('con el permiso de plantillas se comparte con el municipio', ($j['ok'] ?? 0) === 1 && ($j['datos']['visibilidad'] ?? '') === 'MUNICIPIO', json_encode($j));
    comoUsuario($cuentas['todo']);
    $j = llamar('guardar', ['nombre' => 'cartera con CORREOS', 'visibilidad' => 'PRIVADA', 'config' => json_encode($cfgIca)]);
    prueba('el nombre de una compartida tampoco se repite (sin distinguir mayúsculas)', ($j['ok'] ?? 1) === 0);

    // Quién ve qué.
    comoUsuario($cuentas['solo_ica']);
    $j = llamar('plantillas');
    $vistas = array_column($j['datos']['plantillas'], 'nombre');
    prueba('otra persona ve las compartidas y las suyas', in_array('Cartera con correos', $vistas, true) && in_array('Mi cartera', $vistas, true));
    comoUsuario($cuentas['contacto']);
    $j = llamar('plantillas');
    $vistas = array_column($j['datos']['plantillas'], 'nombre');
    prueba('...pero no las privadas de otros', in_array('Cartera con correos', $vistas, true) && !in_array('Mi cartera', $vistas, true));
    comoUsuario($cuentas['admin']);
    $j = llamar('plantillas');
    prueba('...ni siquiera el administrador ve las privadas de otros', !in_array('Mi cartera', array_column($j['datos']['plantillas'], 'nombre'), true));
    comoUsuario($cuentas['contacto']);
    $j = llamar('plantilla', ['id' => (string) $idPrivada]);
    prueba('abrir por id una privada ajena responde "no existe" (no confirma que exista)', ($j['ok'] ?? 1) === 0 && strpos($j['mensaje'], 'no existe') !== false);
    comoUsuario($cuentas['contacto']);
    $j = llamar('borrar', ['id' => (string) $idPrivada]);
    prueba('...y no se puede borrar', ($j['ok'] ?? 1) === 0);
    comoUsuario($cuentas['plantillas']);
    $j = llamar('borrar', ['id' => (string) $idPrivada]);
    prueba('ni siquiera con el permiso de plantillas (solo toca las compartidas)', ($j['ok'] ?? 1) === 0);
    comoUsuario($cuentas['plantillas']);
    $j = llamar('guardar', ['id' => (string) $idPrivada, 'nombre' => 'Robada', 'config' => json_encode($cfgPlantilla)]);
    prueba('...ni modificar una privada ajena', ($j['ok'] ?? 1) === 0);

    // Reautorización: el que ejecuta la compartida no recibe el contacto.
    comoUsuario($cuentas['solo_ica']);
    $j = llamar('plantilla', ['id' => (string) $idCompartida]);
    $colsCargadas = array_column($j['datos']['config']['columnas'] ?? [], 'c');
    prueba('una compartida con correo, abierta por quien no tiene contacto: se quita la columna y se avisa',
        ($j['ok'] ?? 0) === 1 && !in_array('correo', $colsCargadas, true) && in_array('documento', $colsCargadas, true) && count($j['datos']['avisos'] ?? []) === 1, json_encode($j['datos']['avisos'] ?? $j));
    comoUsuario($cuentas['contacto']);
    $j = llamar('plantilla', ['id' => (string) $idCompartida]);
    prueba('...y quien sí tiene contacto la recibe completa', in_array('correo', array_column($j['datos']['config']['columnas'] ?? [], 'c'), true) && empty($j['datos']['avisos']));
    // Ejecutar la plantilla como otro: la vista previa con la configuración cargada.
    comoUsuario($cuentas['solo_ica']);
    $j = llamar('plantilla', ['id' => (string) $idCompartida]);
    $cfgCargada = $j['datos']['config'] ?? [];
    comoUsuario($cuentas['solo_ica']);
    $j2 = llamar('vista', ['config' => json_encode($cfgCargada)]);
    prueba('y la configuración cargada se ejecuta con los permisos de quien la abre', ($j2['ok'] ?? 0) === 1);

    // Editar y borrar compartidas.
    comoUsuario($cuentas['plantillas']);
    $j = llamar('guardar', ['id' => (string) $idCompartida, 'nombre' => 'Cartera con correos (revisada)', 'visibilidad' => 'MUNICIPIO', 'config' => json_encode($cfgContacto)]);
    prueba('quien tiene el permiso de plantillas edita una compartida ajena (si puede usar sus columnas)', ($j['ok'] ?? 1) === 0 && ($j['sinPermiso'] ?? 0) === 1, json_encode($j));
    comoUsuario($cuentas['plantillas']);
    $j = llamar('guardar', ['id' => (string) $idCompartida, 'nombre' => 'Cartera revisada', 'visibilidad' => 'MUNICIPIO', 'config' => json_encode($cfgIca)]);
    prueba('...y con una configuración que sí puede usar, la edita', ($j['ok'] ?? 0) === 1 && ($j['datos']['nombre'] ?? '') === 'Cartera revisada', json_encode($j));
    comoUsuario($cuentas['solo_ica']);
    $j = llamar('borrar', ['id' => (string) $idCompartida]);
    prueba('quien NO es el dueño ni tiene el permiso no borra una compartida', ($j['ok'] ?? 1) === 0 && ($j['sinPermiso'] ?? 0) === 1);
    comoUsuario($cuentas['solo_ica']);
    $j = llamar('guardar', ['id' => (string) $idCompartida, 'nombre' => 'Mía ahora', 'config' => json_encode($cfgIca)]);
    prueba('...ni la modifica', ($j['ok'] ?? 1) === 0 && ($j['sinPermiso'] ?? 0) === 1);
    // Quien administra plantillas edita una compartida ajena sin decir la visibilidad: sigue compartida; pasarla a privada se rechaza.
    comoUsuario($cuentas['plantillas']);
    $j = llamar('guardar', ['id' => (string) $idCompartida, 'nombre' => 'Cartera revisada', 'visibilidad' => 'PRIVADA', 'config' => json_encode($cfgIca)]);
    prueba('una compartida ajena no se vuelve privada', ($j['ok'] ?? 1) === 0);
    comoUsuario($cuentas['plantillas']);
    $j = llamar('guardar', ['id' => (string) $idCompartida, 'nombre' => 'Cartera revisada', 'config' => json_encode($cfgIca)]);
    prueba('...y sin decir la visibilidad conserva la que tenía', ($j['ok'] ?? 0) === 1 && ($j['datos']['visibilidad'] ?? '') === 'MUNICIPIO', json_encode($j));
    comoUsuario($cuentas['plantillas']);
    $j = llamar('borrar', ['id' => (string) $idCompartida]);
    prueba('quien tiene el permiso de plantillas sí borra una compartida ajena', ($j['ok'] ?? 0) === 1);
    comoUsuario($cuentas['todo']);
    $j = llamar('guardar', ['nombre' => 'Cartera con correos', 'visibilidad' => 'MUNICIPIO', 'config' => json_encode($cfgContacto)]);
    prueba('borrar es lógico: el nombre queda libre para otra', ($j['ok'] ?? 0) === 1, json_encode($j));
    // El dueño modifica y borra la suya.
    comoUsuario($cuentas['solo_ica']);
    $j = llamar('guardar', ['id' => (string) $idPrivada, 'nombre' => 'Mi cartera 2', 'descripcion' => 'Nueva', 'visibilidad' => 'PRIVADA', 'config' => json_encode($cfgPlantilla)]);
    prueba('el dueño modifica su plantilla', ($j['ok'] ?? 0) === 1 && $j['datos']['nombre'] === 'Mi cartera 2' && $j['datos']['id'] == $idPrivada);
    comoUsuario($cuentas['solo_ica']);
    $j = llamar('guardar', ['id' => (string) $idPrivada, 'nombre' => 'Mi cartera 2', 'visibilidad' => 'MUNICIPIO', 'config' => json_encode($cfgPlantilla)]);
    prueba('...pero no puede pasarla a compartida sin el permiso', ($j['ok'] ?? 1) === 0 && ($j['sinPermiso'] ?? 0) === 1);
    comoUsuario($cuentas['solo_ica']);
    $j = llamar('borrar', ['id' => (string) $idPrivada]);
    prueba('el dueño borra la suya', ($j['ok'] ?? 0) === 1);
    comoUsuario($cuentas['solo_ica']);
    $j = llamar('plantilla', ['id' => (string) $idPrivada]);
    prueba('una borrada ya no se abre', ($j['ok'] ?? 1) === 0);
    comoUsuario($cuentas['solo_ica']);
    $j = llamar('borrar', ['id' => (string) $idPrivada]);
    prueba('borrarla otra vez avisa que ya no existe', ($j['ok'] ?? 1) === 0);
    // Las del sistema son inmutables.
    comoUsuario($cuentas['admin']);
    $j = llamar('borrar', ['id' => 'sistema:todas']);
    prueba('una plantilla del sistema no se borra (ni el administrador)', ($j['ok'] ?? 1) === 0);
    comoUsuario($cuentas['admin']);
    $j = llamar('guardar', ['id' => 'sistema:todas', 'nombre' => 'Otra', 'config' => json_encode($cfgIca)]);
    prueba('una plantilla del sistema no se modifica (ni el administrador): se guarda con otro nombre', ($j['ok'] ?? 1) === 0);
    comoUsuario($cuentas['admin']);
    $j = llamar('guardar', ['nombre' => 'Todas con mi cambio', 'config' => json_encode(configDe($con, ctxTotal(), 'todas', 'ica'))]);
    prueba('"Guardar" sobre una del sistema equivale a guardar una copia con otro nombre', ($j['ok'] ?? 0) === 1, json_encode($j));
    // Una plantilla con una clave que ya no existe: se abre sin ella y avisa.
    $con->consultar("UPDATE ind_informe_plantillas SET ipl_Config = ? WHERE ipl_Nombre = 'Todas con mi cambio'",
        [str_replace('"documento"', '"columnaQueYaNoExiste"', uno($con, "SELECT ipl_Config FROM ind_informe_plantillas WHERE ipl_Nombre = 'Todas con mi cambio'")['ipl_Config'])]);
    comoUsuario($cuentas['admin']);
    $idVieja = uno($con, "SELECT ipl_Id FROM ind_informe_plantillas WHERE ipl_Nombre = 'Todas con mi cambio'")['ipl_Id'];
    $j = llamar('plantilla', ['id' => (string) $idVieja]);
    prueba('una plantilla con una columna que ya no existe se abre sin ella y avisa', ($j['ok'] ?? 0) === 1 && count($j['datos']['avisos'] ?? []) >= 1
        && !in_array('columnaQueYaNoExiste', array_column($j['datos']['config']['columnas'], 'c'), true), json_encode($j['datos']['avisos'] ?? $j));
    $con->consultar("UPDATE ind_informe_plantillas SET ipl_Config = '{roto' WHERE ipl_Nombre = 'Todas con mi cambio'");
    comoUsuario($cuentas['admin']);
    $j = llamar('plantilla', ['id' => (string) $idVieja]);
    prueba('una plantilla con el JSON dañado avisa con claridad', ($j['ok'] ?? 1) === 0 && strpos($j['mensaje'], 'dañada') !== false);
    // Las del sistema por fuente.
    comoUsuario($cuentas['solo_ica']);
    $j = llamar('plantilla', ['id' => 'sistema:retenciones']);
    prueba('una del sistema de un impuesto que no puede ver avisa del permiso', ($j['ok'] ?? 1) === 0 && ($j['sinPermiso'] ?? 0) === 1);
    comoUsuario($cuentas['solo_ica']);
    $j = llamar('plantillas');
    $vis = array_column($j['datos']['plantillas'], 'id');
    prueba('y el listado la oculta (también el padrón de contribuyentes)', !in_array('sistema:retenciones', $vis, true) && !in_array('sistema:padron', $vis, true) && in_array('sistema:todas', $vis, true));
    comoUsuario($cuentas['admin']);
    $j = llamar('plantilla', ['id' => 'sistema:todas', 'fuente' => 'reteica']);
    prueba('una del sistema se materializa para el impuesto pedido (retención: lleva el mes)', ($j['datos']['config']['impuesto'] ?? '') === 'reteica' && in_array('periodo', array_column($j['datos']['config']['columnas'], 'c'), true));
    comoUsuario($cuentas['admin']);
    $j = llamar('plantilla', ['id' => 'sistema:todas', 'fuente' => 'ica']);
    prueba('...y para el ICA se le quita (no tiene mes), sin avisos', !in_array('periodo', array_column($j['datos']['config']['columnas'], 'c'), true) && empty($j['datos']['avisos']));
    comoUsuario($cuentas['admin']);
    $j = llamar('plantilla', ['id' => 'sistema:bancos']);
    $fp = null;
    foreach ($j['datos']['config']['filtros'] as $f) { if ($f['c'] === 'fechaPago') { $fp = $f; } }
    $hoyBogota = (new DateTime('now', new DateTimeZone('America/Bogota')))->format('Y-m-d');
    prueba('las fechas relativas ("este mes") llegan ya resueltas a fechas', $fp && $fp['v'] === [substr($hoyBogota, 0, 8) . '01', $hoyBogota], json_encode($fp));
}

/* ======================================================================
   6b. Plantillas: el nombre es único en el municipio y los del sistema están reservados en el servidor
   ====================================================================== */
seccion('6b. Plantillas: nombre único por municipio y nombres del sistema reservados');
prueba('nombreComparable junta tildes, mayúsculas, signos y espacios',
    InformePlantillas::nombreComparable('  Todás   LAS declaraciones-presentadas. ') === 'todas las declaraciones presentadas'
    && InformePlantillas::nombreComparable('Ñandú') === 'nandu');
if ($hayPlantillas) {
    $idxs = array_column(todas($con, "SELECT name FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.ind_informe_plantillas') AND name IS NOT NULL"), 'name');
    prueba('la 056 deja un solo índice único de nombre (UX_ipl_nombre) y quita los dos de la primera versión', in_array('UX_ipl_nombre', $idxs, true)
        && !in_array('UX_ipl_nombre_dueno', $idxs, true) && !in_array('UX_ipl_nombre_compartida', $idxs, true), implode(',', $idxs));
    $cfgU = ['v' => 1, 'modo' => 'declaracion', 'impuesto' => 'ica', 'filtros' => [], 'columnas' => [['c' => 'documento'], ['c' => 'estado']], 'orden' => []];
    comoUsuario($cuentas['solo_ica']);
    $j = llamar('guardar', ['nombre' => 'PI Única', 'visibilidad' => 'PRIVADA', 'config' => json_encode($cfgU)]);
    $idU = $j['datos']['id'] ?? null;
    prueba('una persona guarda una plantilla privada', ($j['ok'] ?? 0) === 1 && $idU, json_encode($j));
    foreach ([['contacto', 'PRIVADA', 'pi única'], ['todo', 'MUNICIPIO', 'PI ÚNICA'], ['contacto', 'PRIVADA', '  PI   Única ']] as [$quien, $vis, $nombre]) {
        comoUsuario($cuentas[$quien]);
        $j = llamar('guardar', ['nombre' => $nombre, 'visibilidad' => $vis, 'config' => json_encode($cfgU)]);
        prueba("otra persona no puede usar el mismo nombre en el municipio, ni privada ni compartida («{$nombre}»)", ($j['ok'] ?? 1) === 0 && strpos($j['mensaje'], 'Ya existe') === 0, json_encode($j));
    }
    // Cambiar el nombre de otra plantilla propia al de una existente también se rechaza.
    comoUsuario($cuentas['contacto']);
    $j = llamar('guardar', ['nombre' => 'PI Otra', 'visibilidad' => 'PRIVADA', 'config' => json_encode($cfgU)]);
    $idO = $j['datos']['id'] ?? null;
    comoUsuario($cuentas['contacto']);
    $j = llamar('guardar', ['id' => (string) $idO, 'nombre' => 'pi única', 'visibilidad' => 'PRIVADA', 'config' => json_encode($cfgU)]);
    prueba('renombrar una plantilla con el nombre de otra se rechaza', ($j['ok'] ?? 1) === 0 && strpos($j['mensaje'], 'Ya existe') === 0, json_encode($j));
    comoUsuario($cuentas['contacto']);
    $j = llamar('guardar', ['id' => (string) $idO, 'nombre' => 'PI Otra', 'descripcion' => 'solo la descripción', 'visibilidad' => 'PRIVADA', 'config' => json_encode($cfgU)]);
    prueba('...pero guardar una plantilla con su propio nombre (sin renombrar) sigue funcionando', ($j['ok'] ?? 0) === 1);
    // La base lo impone aunque dos guardados lleguen a la vez: un INSERT directo con el nombre de otra persona falla.
    $dupl = null;
    try {
        $con->consultar("INSERT INTO ind_informe_plantillas (ipl_Nombre, ipl_Fuente, ipl_Config, ipl_IdUsuario, ipl_Visibilidad) VALUES (N'pi única', 'ica', N'{}', ?, 'PRIVADA')", [$cuentas['plantillas']]);
    } catch (\Throwable $e) { $dupl = $e; }
    $rep = new ReflectionMethod(InformePlantillas::class, '_esRepetido');
    $rep->setAccessible(true);
    prueba('el índice único de la base frena el mismo nombre de dos dueños distintos (privadas) y el guardado lo reconoce', $dupl !== null && $rep->invoke(null, $dupl) === true, $dupl ? substr($dupl->getMessage(), 0, 160) : 'no hubo error');
    // Borrar es lógico y libera el nombre.
    comoUsuario($cuentas['solo_ica']);
    $j = llamar('borrar', ['id' => (string) $idU]);
    prueba('el dueño borra su plantilla', ($j['ok'] ?? 0) === 1);
    comoUsuario($cuentas['contacto']);
    $j = llamar('guardar', ['nombre' => 'pi única', 'visibilidad' => 'PRIVADA', 'config' => json_encode($cfgU)]);
    prueba('...y el nombre queda libre para otra persona', ($j['ok'] ?? 0) === 1, json_encode($j));
    // Los nombres del sistema, con variaciones: en el servidor.
    $reservados = ['TODAS LAS DECLARACIONES  PRESENTADAS', 'Todas las declaraciones presentadas.', 'Todás las declaraciones presentadas', 'todas-las declaraciones, presentadas',
                   'Presentadas sin pagar', 'presentadas sin pagar!', 'DECLARACIONES PAGADAS', 'Pagos por banco del mes', 'Intereses de mora pagados', 'Padrón de contribuyentes (RIT)', 'padron de contribuyentes rit'];
    foreach ($reservados as $nombre) {
        comoUsuario($cuentas['solo_ica']);
        $j = llamar('guardar', ['nombre' => $nombre, 'config' => json_encode($cfgU)]);
        prueba("el nombre de una plantilla del sistema no se puede usar («{$nombre}»)", ($j['ok'] ?? 1) === 0 && strpos($j['mensaje'], 'plantilla del sistema') !== false, json_encode($j['mensaje'] ?? ''));
    }
    comoUsuario($cuentas['contacto']);
    $j = llamar('guardar', ['id' => (string) $idO, 'nombre' => 'Declaraciones PAGADAS', 'visibilidad' => 'PRIVADA', 'config' => json_encode($cfgU)]);
    prueba('...tampoco renombrando una plantilla propia', ($j['ok'] ?? 1) === 0 && strpos($j['mensaje'], 'plantilla del sistema') !== false);
    comoUsuario($cuentas['admin']);
    $j = llamar('guardar', ['nombre' => 'Declaraciones pagadas', 'visibilidad' => 'MUNICIPIO', 'config' => json_encode($cfgU)]);
    prueba('...ni el administrador, ni compartiéndola', ($j['ok'] ?? 1) === 0);
    $nombresSis = array_column(array_filter(InformePlantillas::listar($con, ctxTotal()), function ($p) { return $p['sistema']; }), 'nombre');
    $suplantadas = 0;
    foreach ($nombresSis as $nombre) {
        comoUsuario($cuentas['admin']);
        $j = llamar('guardar', ['nombre' => $nombre, 'config' => json_encode($cfgU)]);
        if (($j['ok'] ?? 0) === 1) { $suplantadas++; }
    }
    prueba('ninguno de los ' . count($nombresSis) . ' nombres del sistema se puede guardar', count($nombresSis) >= 9 && $suplantadas === 0, "suplantadas: $suplantadas");
} else {
    echo "  (la base no tiene la migración 056: se omiten las pruebas de nombre único)\n";
}

/* ======================================================================
   7. Excel
   ====================================================================== */
seccion('7. Excel');
/** Parte el ZIP y devuelve [nombre => contenido], comprobando CRC y XML bien formado. */
function leerXlsx($bytes)
{
    $r = [];
    $fin = strrpos($bytes, "PK\x05\x06");
    if ($fin === false) { return null; }
    $e = unpack('vdisco/vdiscoCentral/vnEste/vnTotal/VtamCentral/VdesdeCentral/vlongComentario', substr($bytes, $fin + 4, 18));
    $p = $e['desdeCentral'];
    for ($i = 0; $i < $e['nTotal']; $i++) {
        $c = unpack('Vfirma/vverHecha/vverNecesita/vflags/vmetodo/vhora/vfecha/Vcrc/VtamComp/VtamReal/vlongNombre/vlongExtra/vlongComent/vdiscoIni/vattrInt/VattrExt/VdesdeLocal', substr($bytes, $p, 46));
        if ($c['firma'] !== 0x02014b50) { return null; }
        $nombre = substr($bytes, $p + 46, $c['longNombre']);
        $l = unpack('Vfirma/vver/vflags/vmetodo/vhora/vfecha/Vcrc/VtamComp/VtamReal/vlongNombre/vlongExtra', substr($bytes, $c['desdeLocal'], 30));
        $datos = substr($bytes, $c['desdeLocal'] + 30 + $l['longNombre'] + $l['longExtra'], $c['tamComp']);
        $txt = $c['metodo'] === 8 ? gzinflate($datos) : $datos;
        if ($txt === false || (crc32($txt) & 0xFFFFFFFF) !== $c['crc']) { return null; }
        $r[$nombre] = $txt;
        $p += 46 + $c['longNombre'] + $c['longExtra'] + $c['longComent'];
    }
    return $r;
}
$dirSalida = sys_get_temp_dir() . '/informes_prueba';
@mkdir($dirSalida, 0777, true);
$ctx = ctxTotal();
$enc = ['titulo' => 'Declaraciones pagadas', 'municipio' => 'Paipa', 'generado' => 'Generado el 09/11/2026 a las 10:00 am por Prueba', 'color' => '1fa49d', 'avisos' => []];
foreach (['ica', 'reteica', 'autorreteica'] as $clave) {
    foreach (['todas', 'sinpagar', 'pagadas'] as $slug) {
        $v = InformeConstructor::validar($con, configDe($con, $ctx, $slug, $clave), $ctx, 'estricto');
        $bytes = InformeGeneralExcel::construir($con, $v, $enc);
        $partes = leerXlsx($bytes);
        $esperadas = count(InformePagos::filas($con, $clave, ['estado' => $slug]));
        $okXml = $partes !== null;
        if ($okXml) {
            foreach ($partes as $nombre => $xml) {
                $d = new DOMDocument();
                if (!@$d->loadXML($xml)) { $okXml = false; echo "    XML mal formado: $nombre\n"; }
            }
        }
        $filasHoja = $partes ? substr_count($partes['xl/worksheets/sheet1.xml'], '<row ') : -1;
        // Filas: 4 de encabezado + 1 de columnas + los datos + 1 de total (o 1 de "no hay filas").
        $esperadoFilas = 4 + 1 + $esperadas + 1;
        prueba("Excel $clave/$slug: ZIP válido, XML bien formado y $esperadas filas de datos más el total", $okXml && $filasHoja === $esperadoFilas, "filas de la hoja: $filasHoja, esperadas $esperadoFilas");
        file_put_contents("$dirSalida/{$clave}_{$slug}.xlsx", $bytes);
    }
}
$vTodo = InformeConstructor::validar($con, configDe($con, $ctx, 'pagadas', 'ica'), $ctx, 'estricto');
$partes = leerXlsx(InformeGeneralExcel::construir($con, $vTodo, $enc));
$hoja = $partes['xl/worksheets/sheet1.xml'];
prueba('el Excel trae el autofiltro, el encabezado congelado y SUBTOTAL(109) en los valores', strpos($hoja, '<autoFilter') !== false && strpos($hoja, 'state="frozen"') !== false && strpos($hoja, 'SUBTOTAL(109,') !== false);
prueba('el libro conserva los 10 estilos de siempre y agrega 2 al final (12)', strpos($partes['xl/styles.xml'], '<cellXfs count="12">') !== false);
// Los estilos 0 a 9 los usa el Informe rápido: su contenido no puede cambiar. Las huellas son las de la clase original
// (antes de agregar los 2 del final) y de todo lo que va antes de cellXfs (fuentes, rellenos, bordes y formatos).
preg_match('#<cellXfs count="12">(.*)</cellXfs>#s', $partes['xl/styles.xml'], $mx);
$cuerpo10 = implode('', array_slice(preg_split('#(?=<xf )#', $mx[1] ?? '', -1, PREG_SPLIT_NO_EMPTY), 0, 10));
prueba('los estilos 0 a 9 y los fondos, bordes y formatos que los acompañan siguen idénticos a los del Informe rápido (huella)',
    md5($cuerpo10) === '4ea78007892a53561d7b1daa1750e686' && md5(substr($partes['xl/styles.xml'], 0, strpos($partes['xl/styles.xml'], '<cellXfs'))) === 'caa3f807701a28c0aae5c82cfef3a7ee',
    md5($cuerpo10) . ' / ' . md5(substr($partes['xl/styles.xml'], 0, strpos($partes['xl/styles.xml'], '<cellXfs'))));
prueba('la hoja se llama como el informe y el título va en A1', strpos($partes['xl/workbook.xml'], 'name="Declaraciones pagadas"') !== false && strpos($hoja, 'Declaraciones pagadas') !== false);
// Texto con = + - @ y con caracteres especiales de XML: siempre texto, nunca fórmula.
$cfgX = ['v' => 1, 'modo' => 'declaracion', 'impuesto' => 'ica', 'filtros' => [['c' => 'documento', 'op' => 'in', 'v' => [880000002]]],
         'columnas' => [['c' => 'documento', 't' => '=SUMA(1+1) & <b> "x"'], ['c' => 'contribuyente', 't' => '+cmd|calc'], ['c' => 'estado']], 'orden' => []];
$cfgX['columnas'] = [['c' => 'documento', 't' => '=SUMA(1+1) & "x"'], ['c' => 'contribuyente', 't' => '@titulo'], ['c' => 'estado']];
$partesX = leerXlsx(InformeGeneralExcel::construir($con, InformeConstructor::validar($con, $cfgX, $ctx, 'estricto'), $enc));
$hX = $partesX['xl/worksheets/sheet1.xml'];
prueba('un título que empieza por = o @ se escribe como texto (inlineStr), nunca como fórmula',
    strpos($hX, 't="inlineStr"><is><t xml:space="preserve">=SUMA(1+1) &amp; &quot;x&quot;</t>') !== false && strpos($hX, '<f>=SUMA') === false);
prueba('el nombre con apóstrofo y comillas va escapado y bien formado', strpos($hX, 'Inversiones D&apos;Luca S.A.S.') !== false);
// El nombre del archivo.
prueba('el nombre del archivo es ASCII seguro', preg_match('/^Informe_[A-Za-z0-9_]+_20261109\.xlsx$/', InformeGeneralExcel::nombreArchivo('Declaraciones pagadas — niño "x"/y; ..\z', '20261109')) === 1);
// El año es un número SIN separador de miles; con una sola columna no hay celdas "combinadas" de una sola celda.
$cfgAnio = ['v' => 1, 'modo' => 'declaracion', 'impuesto' => 'ica', 'filtros' => [], 'columnas' => [['c' => 'anio']], 'orden' => []];
$partesA = leerXlsx(InformeGeneralExcel::construir($con, InformeConstructor::validar($con, $cfgAnio, $ctx, 'estricto'), $enc));
$hA = $partesA['xl/worksheets/sheet1.xml'];
prueba('el año se escribe como número centrado sin separador de miles (estilo 9, no el 10 de "#,##0")',
    preg_match('/<c r="A7" s="9"><v>20\d\d<\/v><\/c>/', $hA) === 1 && strpos($hA, 's="10"') === false, substr($hA, strpos($hA, '<row r="7"'), 120));
prueba('con una sola columna no hay celdas combinadas de una sola celda (Excel pide reparar A1:A1)',
    strpos($hA, 'A1:A1') === false && strpos($hA, '<mergeCells') === false);
$colAnio = InformeCatalogo::fuente($con, 'ica')['columnas']['anio'];
prueba('la columna "Año" es de tipo anio y el filtro "Año" sigue siendo entero', $colAnio['tipo'] === 'anio' && InformeCatalogo::fuente($con, 'ica')['filtros']['anio']['tipo'] === 'entero');
// Una hoja sin filas, con una sola columna, también sin celdas combinadas inválidas.
$cfgVacio = ['v' => 1, 'modo' => 'declaracion', 'impuesto' => 'ica', 'filtros' => [['c' => 'documento', 'op' => 'in', 'v' => [999999999]]], 'columnas' => [['c' => 'anio']], 'orden' => []];
$hV = leerXlsx(InformeGeneralExcel::construir($con, InformeConstructor::validar($con, $cfgVacio, $ctx, 'estricto'), $enc))['xl/worksheets/sheet1.xml'];
prueba('sin filas y con una columna: dice que no hay declaraciones y no combina una sola celda', strpos($hV, 'No hay declaraciones con estos filtros.') !== false && strpos($hV, 'A7:A7') === false);
// El nombre de la hoja que Excel acepta.
prueba('el nombre de la hoja es válido para Excel (sin apóstrofos en los extremos ni \\ / ? * [ ] :, máx. 31, nunca vacío ni History)',
    InformeGeneralExcel::nombreHoja("'Cartera'") === 'Cartera'
    && InformeGeneralExcel::nombreHoja('A/B:C?*[x]') === 'A B C x'
    && InformeGeneralExcel::nombreHoja('History') === 'Informe'
    && InformeGeneralExcel::nombreHoja('   ') === 'Informe'
    && InformeGeneralExcel::nombreHoja("''") === 'Informe'
    && InformeGeneralExcel::nombreHoja("Inversiones D'Luca") === 'Inversiones D’Luca'
    && mb_strlen(InformeGeneralExcel::nombreHoja(str_repeat('ñ', 80)), 'UTF-8') === 31);
$encC = $enc;
$encC['titulo'] = "'Cartera D'Luca'";
$pC = leerXlsx(InformeGeneralExcel::construir($con, $vTodo, $encC));
prueba('un título entre apóstrofos da una hoja con nombre válido y un rango de autofiltro bien formado',
    strpos($pC['xl/workbook.xml'], '<sheet name="Cartera D’Luca"') !== false && strpos($pC['xl/workbook.xml'], '\'Cartera D’Luca\'!$A$6:') !== false,
    substr($pC['xl/workbook.xml'], 0, 400));
// Tope de filas y de celdas: se rechaza, no se recorta.
$topeAntes = InformeConstructor::$maxFilasExcel;
InformeConstructor::$maxFilasExcel = 3;
$e = lanza(function () use ($con, $vTodo, $enc) { InformeGeneralExcel::construir($con, InformeConstructor::validar($con, configDe($con, ctxTotal(), 'todas', 'ica'), ctxTotal(), 'estricto'), $enc); });
prueba('pasado el tope de filas el Excel se rechaza con un mensaje que dice qué hacer', $e instanceof InformeException && strpos($e->getMessage(), 'filtros') !== false, $e ? $e->getMessage() : 'sin error');
InformeConstructor::$maxFilasExcel = $topeAntes;
$celdasAntes = InformeConstructor::$maxCeldasExcel;
InformeConstructor::$maxCeldasExcel = 20;
$e = lanza(function () use ($con, $enc) { InformeGeneralExcel::construir($con, InformeConstructor::validar($con, configDe($con, ctxTotal(), 'todas', 'ica'), ctxTotal(), 'estricto'), $enc); });
prueba('pasado el tope de celdas el Excel se rechaza y pide quitar columnas o filtrar', $e instanceof InformeException && strpos($e->getMessage(), 'celdas') !== false, $e ? $e->getMessage() : 'sin error');
InformeConstructor::$maxCeldasExcel = $celdasAntes;
// El recorrido por lotes (OFFSET/FETCH) devuelve lo mismo que de una sola vez, aunque el total sea múltiplo del lote.
$q = InformeConstructor::armar(InformeConstructor::validar($con, configDe($con, $ctx, 'todas', 'ica'), $ctx, 'estricto'));
prueba('el orden termina en la llave de la tabla (orden total, sin filas repetidas ni perdidas entre lotes)', preg_match('/dec_Id ASC$/', $q['orden']) === 1, $q['orden']);
$total = InformeConstructor::contar($con, $q);
$unaVez = [];
InformeConstructor::recorrer($con, $q, function ($f) use (&$unaVez) { $unaVez[] = $f; });
$loteAntes = InformeConstructor::$filasPorLote;
foreach ([1, 7, $total, $total + 1] as $lote) {
    InformeConstructor::$filasPorLote = $lote;
    $porLotes = [];
    InformeConstructor::recorrer($con, $q, function ($f) use (&$porLotes) { $porLotes[] = $f; });
    prueba("recorrer por lotes de $lote da las mismas $total filas en el mismo orden", $total > 10 && $porLotes === $unaVez, count($porLotes) . ' filas');
}
InformeConstructor::$filasPorLote = $loteAntes;

/* ======================================================================
   7b. Ajustes del 2026-11-10: buscador, orden y paginación en el servidor, rangos,
       sin mensajes de la base, totales (vista previa y Excel) y encabezado del Excel
   ====================================================================== */
seccion('7b. Buscador, orden y paginación de la vista previa (el mismo constructor que el Excel)');

/** La vista previa por el controlador, como administrador. */
function vistaGeneral(array $cfg, array $extra = [])
{
    comoUsuario(1);
    return llamar('vista', ['config' => json_encode($cfg)] + $extra);
}
/** Cantidad de declaraciones presentadas de un contribuyente (consulta escrita aparte, sin pasar por el catálogo). */
function cuantasPresentadas($con, $clave, array $idsContribuyente)
{
    $m = PseModulo::get($clave);
    $p = $m['prefijo'];
    $ph = implode(', ', array_fill(0, count($idsContribuyente), '?'));
    return (int) uno($con, "SELECT COUNT(*) AS n FROM {$m['tabla']} WHERE {$p}_IdContribuyente IN ($ph) AND {$m['estado']} = 2", $idsContribuyente)['n'];
}
/** Declaraciones presentadas cuyo contribuyente tiene la palabra en alguno de los cuatro campos de nombre (consulta escrita aparte). */
function presentadasPorNombre($con, $clave, $palabra)
{
    $m = PseModulo::get($clave);
    $p = $m['prefijo'];
    $or = implode(' OR ', array_map(function ($c) { return "c.$c COLLATE Latin1_General_CI_AI LIKE ?"; },
        ['ind_PrimerNombre', 'ind_SegundoNombre', 'ind_PrimerApellido', 'ind_SegundoApellido']));
    return (int) uno($con, "SELECT COUNT(*) AS n FROM {$m['tabla']} d JOIN ind_contribuyentes c ON c.ind_Id = d.{$p}_IdContribuyente WHERE d.{$m['estado']} = 2 AND ($or)",
                     array_fill(0, 4, '%' . $palabra . '%'))['n'];
}
$cfgB = ['v' => 1, 'modo' => 'declaracion', 'impuesto' => 'ica', 'filtros' => [],
         'columnas' => [['c' => 'documento'], ['c' => 'contribuyente'], ['c' => 'numero'], ['c' => 'valorDeclarado'], ['c' => 'valorPagado']],
         'orden' => [['c' => 'numero', 'd' => 'asc']]];

// ---- Buscador
$j = vistaGeneral($cfgB, ['buscar' => '880000003', 'porPagina' => 100]);
prueba('buscar por documento trae solo las declaraciones de ese contribuyente (y el conteo es el de la búsqueda)',
    ($j['ok'] ?? 0) === 1 && $j['datos']['total'] === cuantasPresentadas($con, 'ica', [$cC]) && $j['datos']['total'] > 0
    && count(array_unique(array_column($j['datos']['filas'], 0))) === 1, json_encode($j['mensaje'] ?? $j['datos']['total'] ?? ''));
foreach (['avendano', 'AVENDAÑO', 'avendaño'] as $texto) {
    $j = vistaGeneral($cfgB, ['buscar' => $texto, 'porPagina' => 100]);
    prueba("buscar por nombre sin distinguir tildes ni mayúsculas («{$texto}»)", ($j['ok'] ?? 0) === 1 && $j['datos']['total'] === presentadasPorNombre($con, 'ica', 'avendano')
        && $j['datos']['total'] >= cuantasPresentadas($con, 'ica', [$cC]), json_encode($j['mensaje'] ?? $j['datos']['total'] ?? ''));
}
$j = vistaGeneral($cfgB, ['buscar' => 'peñaranda', 'porPagina' => 100]);
prueba('...y trae a los dos contribuyentes con ese apellido (el repetido del padrón también)', ($j['ok'] ?? 0) === 1 && $j['datos']['total'] === presentadasPorNombre($con, 'ica', 'penaranda')
    && $j['datos']['total'] >= cuantasPresentadas($con, 'ica', [$cA, $cA2]), json_encode($j['datos']['total'] ?? ''));
$j = vistaGeneral($cfgB, ['buscar' => '9100000005']);
prueba('buscar por número de declaración trae esa declaración', ($j['ok'] ?? 0) === 1 && $j['datos']['total'] === 1 && $j['datos']['filas'][0][2] === '9100000005', json_encode($j['datos']['filas'] ?? $j));
$j = vistaGeneral($cfgB, ['buscar' => '880000002-7']);
prueba('un NIT con su dígito de verificación se busca por el número', ($j['ok'] ?? 0) === 1 && $j['datos']['total'] === cuantasPresentadas($con, 'ica', [$cB]), json_encode($j['datos']['total'] ?? ''));
$j = vistaGeneral($cfgB, ['buscar' => '880.000.002']);
prueba('...y con puntos de miles', ($j['ok'] ?? 0) === 1 && $j['datos']['total'] === cuantasPresentadas($con, 'ica', [$cB]));
foreach (['100%' => '100%', '%' => '%', '[x]_y' => '[x]_y', '_' => '_'] as $texto => $etiqueta) {
    $j = vistaGeneral($cfgB, ['buscar' => $texto, 'porPagina' => 100]);
    prueba("%, _ y [ del buscador son texto y no comodines («{$etiqueta}»)", ($j['ok'] ?? 0) === 1 && $j['datos']['total'] === cuantasPresentadas($con, 'ica', [$cD])
        && count(array_unique(array_column($j['datos']['filas'], 0))) === 1, json_encode($j['datos']['total'] ?? $j['mensaje'] ?? ''));
}
$j = vistaGeneral($cfgB, ['buscar' => 'maria 880000001', 'porPagina' => 100]);
prueba('varias palabras: todas deben aparecer (nombre y documento)', ($j['ok'] ?? 0) === 1 && $j['datos']['total'] === cuantasPresentadas($con, 'ica', [$cA, $cA2]));
$j = vistaGeneral($cfgB, ['buscar' => 'maria 880000002']);
prueba('...y si una no aparece no hay filas', ($j['ok'] ?? 0) === 1 && $j['datos']['total'] === 0 && $j['datos']['filas'] === []);
$j = vistaGeneral($cfgB, ['buscar' => "x'; DROP TABLE ind_contribuyentes;--"]);
prueba('el buscador no inyecta SQL (es un parámetro) y la tabla sigue', ($j['ok'] ?? 0) === 1 && $j['datos']['total'] === 0 && (int) uno($con, 'SELECT COUNT(*) AS n FROM ind_contribuyentes')['n'] > 0);
foreach ([str_repeat('a', 61) => 'más de 60 caracteres', 'a b c d e f' => 'más de 5 palabras', "a\x01b" => 'con un carácter de control'] as $texto => $queEs) {
    $j = vistaGeneral($cfgB, ['buscar' => $texto]);
    prueba("un buscador $queEs se rechaza con un mensaje claro", ($j['ok'] ?? 1) === 0 && strpos($j['mensaje'], 'búsqueda') !== false, json_encode($j['mensaje'] ?? $j));
}
$j = vistaGeneral($cfgB, ['buscar' => '880000002', 'porPagina' => 100]);
$sumaB = (int) uno($con, 'SELECT SUM(dec_ValorConcepto20) AS s FROM ind_declaraciones_ica WHERE dec_IdContribuyente = ? AND dec_Estado = 2', [$cB])['s'];
prueba('los totales de la vista previa son los de la búsqueda (todas sus filas), no los de todo el informe', ($j['datos']['totales']['valorDeclarado'] ?? -1) === $sumaB && $sumaB > 0, json_encode($j['datos']['totales'] ?? '') . " contra $sumaB");
prueba('la respuesta repite la búsqueda y el orden para que la pantalla los muestre', ($j['datos']['buscar'] ?? '') === '880000002' && array_key_exists('orden', $j['datos']));

// ---- Orden por columna y paginación
$todo = ejecutar($con, ctxTotal(), $cfgB);
$valores = array_column($todo['filas'], 3);
$j = vistaGeneral($cfgB, ['ordenC' => 'valorDeclarado', 'ordenD' => 'desc', 'porPagina' => 100]);
$ordenados = array_column($j['datos']['filas'] ?? [], 3);
$esperado = $valores;
rsort($esperado);
prueba('ordenar por una columna (descendente) lo hace el servidor sobre todas las filas', ($j['ok'] ?? 0) === 1 && $ordenados === $esperado && ($j['datos']['orden'] ?? null) === ['c' => 'valorDeclarado', 'd' => 'desc'], json_encode($j['mensaje'] ?? ''));
$j = vistaGeneral($cfgB, ['ordenC' => 'contribuyente', 'ordenD' => 'asc', 'porPagina' => 100]);
$nombres = array_map(function ($f) { return mb_strtolower((string) $f[1], 'UTF-8'); }, $j['datos']['filas'] ?? []);
prueba('...también por una columna de texto', ($j['ok'] ?? 0) === 1 && count($nombres) === $todo['total']);
// Páginas de 5 con el orden de la vista: juntas dan la lista completa, sin repetir ni perder filas (hay empates de valor).
$juntas = [];
$paginas = 0;
for ($pg = 1; $pg <= 20; $pg++) {
    $j = vistaGeneral($cfgB, ['ordenC' => 'valorDeclarado', 'ordenD' => 'desc', 'porPagina' => 5, 'pagina' => $pg]);
    if (($j['ok'] ?? 0) !== 1) { break; }
    $paginas = $j['datos']['paginas'];
    if ($j['datos']['pagina'] !== $pg) { break; }
    foreach ($j['datos']['filas'] as $f) { $juntas[] = $f[2] . '|' . $f[3]; }
    if ($pg >= $paginas) { break; }
}
$completa = vistaGeneral($cfgB, ['ordenC' => 'valorDeclarado', 'ordenD' => 'desc', 'porPagina' => 100])['datos']['filas'];
$completaTxt = array_map(function ($f) { return $f[2] . '|' . $f[3]; }, $completa);
prueba("paginar de a 5 con orden da las mismas {$todo['total']} filas, en el mismo orden y sin repetir", $juntas === $completaTxt && count(array_unique($juntas)) === count($juntas) && $paginas === (int) ceil($todo['total'] / 5), count($juntas) . ' de ' . count($completaTxt));
$j = vistaGeneral($cfgB, ['ordenC' => 'valorDeclarado', 'ordenD' => 'desc', 'porPagina' => 5, 'pagina' => 999]);
prueba('una página que no existe lleva a la última con filas', ($j['ok'] ?? 0) === 1 && $j['datos']['pagina'] === $j['datos']['paginas'] && count($j['datos']['filas']) > 0);
// El orden de la vista manda sobre el de la configuración.
$j = vistaGeneral($cfgB, ['porPagina' => 100]);
prueba('sin orden de la vista rige el de la configuración (número ascendente)', ($j['datos']['filas'][0][2] ?? null) === min(array_filter(array_column($todo['filas'], 2), 'strlen')) || ($j['datos']['filas'][0][2] ?? null) === null);
$j = vistaGeneral($cfgB, ['porPagina' => 100, 'ordenC' => 'valorDeclarado', 'ordenD' => 'desc']);
prueba('...y con orden de la vista, este manda', ($j['datos']['filas'][0][3] ?? -1) === max($valores));
// Lo que NO se acepta como orden.
foreach ([['correo', 'asc', 'una columna que no está en el informe'], ['numero; DROP TABLE x', 'asc', 'una clave inventada'],
          ['valorDeclarado', 'sideways', 'un sentido que no existe'], ['valorDeclarado', "asc; --", 'un sentido con SQL'], ['noExiste', 'asc', 'una columna que no existe']] as [$c, $d, $queEs]) {
    $j = vistaGeneral($cfgB, ['ordenC' => $c, 'ordenD' => $d]);
    prueba("ordenar por $queEs se rechaza", ($j['ok'] ?? 1) === 0 && strpos($j['mensaje'], 'orden') !== false, json_encode($j['mensaje'] ?? $j));
}
$cfgCorreo = $cfgB;
$cfgCorreo['columnas'][] = ['c' => 'correo'];
$j = vistaGeneral($cfgCorreo, ['ordenC' => 'correo']);
prueba('una columna que no se puede ordenar (datos de contacto) se rechaza aunque esté en el informe', ($j['ok'] ?? 1) === 0 && strpos($j['mensaje'], 'No se puede ordenar') === 0, json_encode($j['mensaje'] ?? $j));
$e = lanza(function () use ($con) { $v = InformeConstructor::validar($con, ['v' => 1, 'modo' => 'declaracion', 'impuesto' => 'ica', 'filtros' => [], 'columnas' => [['c' => 'documento']], 'orden' => []], ctxTotal(), 'estricto'); InformeConstructor::ordenDeVista($v, ['x'], 'asc'); });
prueba('la columna del orden debe ser un texto (no un arreglo)', $e instanceof InformeException);
$sinOrden = InformeConstructor::ordenDeVista(InformeConstructor::validar($con, $cfgB, ctxTotal(), 'estricto'), '', 'asc');
prueba('sin columna pedida no hay orden de la vista', $sinOrden === null);

// ---- Rangos validados en el servidor, sin texto de la base
$malos = [
    'año inicial mayor que el final' => [['c' => 'anio', 'op' => 'between', 'v' => [2027, 2026]], 'mayor'],
    'año fuera de 2000 a 2100 (1999)' => [['c' => 'anio', 'op' => 'between', 'v' => [1999, 2026]], 'entre 2000 y 2100'],
    'año fuera de 2000 a 2100 (2101)' => [['c' => 'anio', 'op' => 'in', 'v' => [2101]], 'entre 2000 y 2100'],
    'fechas al revés' => [['c' => 'fechaPago', 'op' => 'between', 'v' => ['2026-05-01', '2026-04-01']], 'mayor'],
    'una fecha que no existe (30 de febrero)' => [['c' => 'fechaPago', 'op' => 'between', 'v' => ['2026-02-30', '2026-03-01']], 'fechas'],
    'una fecha fuera de 1900 a 2100' => [['c' => 'fechaPago', 'op' => '>=', 'v' => '2200-01-01'], 'fechas'],
    'valores al revés' => [['c' => 'valorDeclarado', 'op' => 'between', 'v' => [500000, 100000]], 'mayor'],
    'un valor con letras' => [['c' => 'valorDeclarado', 'op' => '>=', 'v' => '12abc'], 'pesos'],
    'un documento en cero' => [['c' => 'documento', 'op' => 'in', 'v' => [0]], 'entre'],
    'un solo extremo en un rango' => [['c' => 'fechaPago', 'op' => 'between', 'v' => ['2026-01-01']], 'inicial y el final'],
];
foreach ($malos as $queEs => [$fil, $palabra]) {
    $c = $cfgB;
    $c['filtros'] = [$fil];
    $j = vistaGeneral($c);
    $m = (string) ($j['mensaje'] ?? '');
    prueba("rango inválido en el servidor: $queEs", ($j['ok'] ?? 1) === 0 && stripos($m, $palabra) !== false && !preg_match('/sqlsrv|SQL Server|Msg \d|Incorrect|Invalid|Conversion|converting|\[code\]/i', $m), $m);
}
// Un fallo de la base NO llega a la persona: genérico, y el detalle va al registro (acá se silencia).
$rf = new ReflectionProperty(InformeCatalogo::class, 'fuentes');
$rf->setAccessible(true);
InformeCatalogo::fuente($con, 'ica');
$ff = $rf->getValue();
$ff['ica']['columnas']['valorDeclarado']['sql'] = 'COLUMNA_QUE_NO_EXISTE_EN_LA_BASE';
$rf->setValue(null, $ff);
$logAntes = ini_get('error_log');
ini_set('error_log', sys_get_temp_dir() . '/informes_prueba_errores.log');
comoUsuario(1);
$rf->setValue(null, $ff);   // comoUsuario olvida el catálogo: se vuelve a envenenar
$j = llamar('vista', ['config' => json_encode($cfgB)]);
prueba('un fallo de la base en la vista previa responde un mensaje genérico, sin texto del servidor SQL',
    ($j['ok'] ?? 1) === 0 && $j['mensaje'] === 'No se pudo procesar la solicitud. Intente de nuevo.' && !preg_match('/COLUMNA_QUE_NO_EXISTE|sqlsrv|Invalid column/i', json_encode($j)), json_encode($j));
InformeCatalogo::olvidar();
InformeCatalogo::fuente($con, 'ica');
$ff = $rf->getValue();
$ff['ica']['columnas']['valorDeclarado']['sql'] = 'COLUMNA_QUE_NO_EXISTE_EN_LA_BASE';
$rf->setValue(null, $ff);
$msgExcel = null;
try { InformeGeneralExcel::construir($con, InformeConstructor::validar($con, $cfgB, ctxTotal(), 'estricto'), $enc); }
catch (InformeException $e) { $msgExcel = 'InformeException: ' . $e->getMessage(); }
catch (\Throwable $e) { $msgExcel = 'Throwable'; }
prueba('...y en el Excel el fallo sube como error del servidor (la descarga responde 500 genérico, no el texto de la base)', $msgExcel === 'Throwable', (string) $msgExcel);
ini_set('error_log', $logAntes);
@unlink(sys_get_temp_dir() . '/informes_prueba_errores.log');
InformeCatalogo::olvidar();

// ---- El Excel con la búsqueda y el orden de la vista previa, y su encabezado
seccion('7c. Excel: encabezado con filtros y búsqueda, totales (SUBTOTAL) sin que el rótulo tape una suma');
$ctx = ctxTotal();
$encB = $enc;
$vB = InformeConstructor::validar($con, $cfgB, $ctx, 'estricto');
$opcB = InformeConstructor::opcionesDeVista($vB, '880000003', 'valorDeclarado', 'desc');
$encB['opc'] = $opcB;
$partesB = leerXlsx(InformeGeneralExcel::construir($con, $vB, $encB));
$hB = $partesB['xl/worksheets/sheet1.xml'];
$nCue = cuantasPresentadas($con, 'ica', [$cC]);
prueba("el Excel con la búsqueda trae solo sus {$nCue} filas (más 4 de encabezado, el de columnas y el total)", substr_count($hB, '<row ') === 4 + 1 + $nCue + 1, 'filas: ' . substr_count($hB, '<row '));
prueba('el encabezado dice la búsqueda y el orden usados', strpos($hB, 'Búsqueda: «880000003»') !== false && strpos($hB, 'Orden: Total a pagar (descendente)') !== false);
$hSin = leerXlsx(InformeGeneralExcel::construir($con, $vB, $enc))['xl/worksheets/sheet1.xml'];
prueba('sin búsqueda ni orden el encabezado no cambia: filtros en A3 y «Generado el … por …» en A4', strpos($hSin, 'Búsqueda') === false && strpos($hSin, 'Orden:') === false && preg_match('/<c r="A3"[^>]*><is><t[^>]*>Estado: presentadas<\/t>/', $hSin) === 1 && strpos($hSin, 'Generado el 09/11/2026') !== false);
$cfgConFiltros = $cfgB;
$cfgConFiltros['filtros'] = [['c' => 'anio', 'op' => 'in', 'v' => [2026]], ['c' => 'estado', 'op' => 'in', 'v' => ['pagada', 'sinpagar']]];
$hF = leerXlsx(InformeGeneralExcel::construir($con, InformeConstructor::validar($con, $cfgConFiltros, $ctx, 'estricto'), $enc))['xl/worksheets/sheet1.xml'];
prueba('los filtros usados salen escritos en A3', strpos($hF, 'Año: 2026') !== false && strpos($hF, 'Estado: Pagada, Sin pagar') !== false);
// Misma lista que la vista previa: los mismos documentos/números y el mismo orden.
$qB = InformeConstructor::armar($vB, $opcB);
$enVista = [];
InformeConstructor::recorrer($con, $qB, function ($f) use (&$enVista) { $enVista[] = $f[2]; });
$jV = vistaGeneral($cfgB, ['buscar' => '880000003', 'ordenC' => 'valorDeclarado', 'ordenD' => 'desc', 'porPagina' => 100]);
prueba('el Excel y la vista previa traen las mismas filas, en el mismo orden (un solo constructor)', $enVista === array_column($jV['datos']['filas'], 2) && count($enVista) === $nCue);
prueba('opcionesDeVista rechaza lo mismo que la vista previa (búsqueda larga, orden fuera del informe)',
    lanza(function () use ($vB) { InformeConstructor::opcionesDeVista($vB, str_repeat('x', 61), '', ''); }) instanceof InformeException
    && lanza(function () use ($vB) { InformeConstructor::opcionesDeVista($vB, '', 'correo', 'asc'); }) instanceof InformeException);

// Totales con la primera columna de dinero: la suma no se pisa con el rótulo.
$cfgTot = ['v' => 1, 'modo' => 'declaracion', 'impuesto' => 'ica', 'filtros' => [['c' => 'documento', 'op' => 'in', 'v' => [880000002]]],
           'columnas' => [['c' => 'valorDeclarado'], ['c' => 'documento'], ['c' => 'valorPagado']], 'orden' => []];
$hT = leerXlsx(InformeGeneralExcel::construir($con, InformeConstructor::validar($con, $cfgTot, $ctx, 'estricto'), $enc))['xl/worksheets/sheet1.xml'];
$nB = cuantasPresentadas($con, 'ica', [$cB]);
$filaTot = 6 + $nB + 1;
prueba('si la primera columna es de dinero, su suma queda en A y el rótulo "Total: N declaraciones" pasa a la primera columna sin suma (B)',
    preg_match('/<c r="A' . $filaTot . '"[^>]*><f>SUBTOTAL\(109,A7:A' . (6 + $nB) . '\)<\/f><v>' . $sumaB . '<\/v><\/c>/', $hT) === 1
    && preg_match('/<c r="B' . $filaTot . '"[^>]*><is><t[^>]*>Total: ' . $nB . ' declaraciones<\/t>/', $hT) === 1
    && preg_match('/<c r="C' . $filaTot . '"[^>]*><f>SUBTOTAL\(109,C7:C' . (6 + $nB) . '\)<\/f>/', $hT) === 1, substr($hT, strpos($hT, '<row r="' . $filaTot . '"'), 600));
$cfgSoloDinero = $cfgTot;
$cfgSoloDinero['columnas'] = [['c' => 'valorDeclarado'], ['c' => 'valorPagado']];
$hD = leerXlsx(InformeGeneralExcel::construir($con, InformeConstructor::validar($con, $cfgSoloDinero, $ctx, 'estricto'), $enc))['xl/worksheets/sheet1.xml'];
prueba('si TODAS las columnas suman, el rótulo va en la fila de abajo y ninguna suma se pierde',
    preg_match('/<c r="A' . $filaTot . '"[^>]*><f>SUBTOTAL/', $hD) === 1 && preg_match('/<c r="B' . $filaTot . '"[^>]*><f>SUBTOTAL/', $hD) === 1
    && preg_match('/<c r="A' . ($filaTot + 1) . '"[^>]*><is><t[^>]*>Total: ' . $nB . ' declaraciones<\/t>/', $hD) === 1);
$hN = leerXlsx(InformeGeneralExcel::construir($con, InformeConstructor::validar($con, array_replace($cfgTot, ['columnas' => [['c' => 'documento'], ['c' => 'valorDeclarado']]]), $ctx, 'estricto'), $enc))['xl/worksheets/sheet1.xml'];
prueba('con el documento primero, el rótulo sigue en A y la suma en B (como siempre)', preg_match('/<c r="A' . $filaTot . '"[^>]*><is><t[^>]*>Total: ' . $nB . ' declaraciones<\/t>/', $hN) === 1 && preg_match('/<c r="B' . $filaTot . '"[^>]*><f>SUBTOTAL/', $hN) === 1);
// El valor en caché de la suma es el de la columna (sin él, un visor que no recalcula la muestra vacía).
preg_match('/<c r="C' . $filaTot . '"[^>]*><f>[^<]*<\/f><v>([^<]*)<\/v>/', $hT, $mC);
$pagadoB = (int) uno($con, 'SELECT SUM(ROUND(dec_ValorPago, 0)) AS s FROM ind_declaraciones_ica WHERE dec_IdContribuyente = ? AND dec_Estado = 2 AND dec_Pagado = 1 AND ISNULL(dec_RutaPago, \'\') <> \'WOMPI_PRUEBA\'', [$cB])['s'];
prueba('la suma de valorPagado trae su valor en caché y coincide con la base', isset($mC[1]) && (int) $mC[1] === $pagadoB, ($mC[1] ?? '?') . " contra $pagadoB");
// Vista previa: el servidor manda las sumas de todas las filas (la pantalla pone el rótulo donde no tape una suma).
$jT = vistaGeneral($cfgTot, ['porPagina' => 5]);
prueba('la vista previa trae las sumas de TODAS las filas aunque se vea una página', ($jT['datos']['totales']['valorDeclarado'] ?? -1) === $sumaB && $jT['datos']['total'] === $nB);

// ---- Una fila por contribuyente (declaraciones agrupadas)
seccion('7d. Una fila por contribuyente (declaraciones agrupadas) y su oráculo contra el modo por declaración');
$colsAg = ['documento', 'contribuyente', 'nDeclaraciones', 'aniosPresentados', 'ultimoAnioPagado', 'valorDeclarado', 'valorPagado', 'intereses'];
$cfgAg = function ($impuesto, array $filtros = [], array $columnas = null, array $orden = []) use ($colsAg) {
    return ['v' => 1, 'modo' => 'agrupado', 'impuesto' => $impuesto, 'filtros' => $filtros,
            'columnas' => array_map(function ($c) { return ['c' => $c]; }, $columnas ?? $colsAg), 'orden' => $orden];
};
// Por documento y nombre. El padrón real repite contribuyentes (mismo documento y nombre): sus filas se juntan.
$porClave = function (array $r) {
    $cols = array_column($r['q']['columnas'], 'c');
    $o = [];
    foreach ($r['filas'] as $f) {
        $fila = array_combine($cols, $f);
        $k = $fila['documento'] . '|' . $fila['contribuyente'];
        if (!isset($o[$k])) { $o[$k] = $fila; continue; }
        $a = &$o[$k];
        foreach (['nDeclaraciones', 'valorDeclarado', 'valorPagado', 'intereses'] as $c) {
            if (!array_key_exists($c, $fila)) { continue; }
            $a[$c] = ($a[$c] === null && $fila[$c] === null) ? null : (int) $a[$c] + (int) $fila[$c];
        }
        if (isset($fila['aniosPresentados'])) {
            $an = array_unique(array_merge(explode(', ', (string) $a['aniosPresentados']), explode(', ', (string) $fila['aniosPresentados'])));
            sort($an);
            $a['aniosPresentados'] = implode(', ', $an);
            $a['ultimoAnioPagado'] = max((int) $a['ultimoAnioPagado'], (int) $fila['ultimoAnioPagado']) ?: null;
        }
        unset($a);
    }
    return $o;
};
foreach (['ica', 'reteica', 'autorreteica'] as $clave) {
    $ag = ejecutar($con, $ctx, $cfgAg($clave));
    // El mismo informe, una fila por declaración, solo con las vigentes: presentadas y sin una corrección presentada.
    $det = ejecutar($con, $ctx, ['v' => 1, 'modo' => 'declaracion', 'impuesto' => $clave,
        'filtros' => [['c' => 'estado', 'op' => 'in', 'v' => InformeCatalogo::ESTADOS_PRESENTADA], ['c' => 'corregida', 'op' => '=', 'v' => false]],
        'columnas' => array_map(function ($c) { return ['c' => $c]; }, ['documento', 'contribuyente', 'anio', 'valorDeclarado', 'valorPagado', 'intereses', 'pagada']), 'orden' => []]);
    $cd = array_column($det['q']['columnas'], 'c');
    $suma = ['n' => 0, 'valor' => 0, 'pagado' => 0, 'intereses' => 0];
    $porC = [];
    foreach ($det['filas'] as $f) {
        $fila = array_combine($cd, $f);
        $k = $fila['documento'] . '|' . $fila['contribuyente'];
        $porC[$k] = $porC[$k] ?? ['n' => 0, 'valor' => 0, 'pagado' => 0, 'intereses' => 0, 'anios' => [], 'pagados' => []];
        $porC[$k]['n']++;
        $porC[$k]['valor'] += (int) $fila['valorDeclarado'];
        $porC[$k]['pagado'] += (int) $fila['valorPagado'];
        $porC[$k]['intereses'] += (int) $fila['intereses'];
        $porC[$k]['anios'][$fila['anio']] = true;
        if ($fila['pagada'] === 'Sí') { $porC[$k]['pagados'][$fila['anio']] = true; }
    }
    $filasAg = $porClave($ag);
    $dif = null;
    if (count($filasAg) !== count($porC)) { $dif = 'contribuyentes: ' . count($filasAg) . ' contra ' . count($porC); }
    foreach ($porC as $k => $e) {
        if ($dif) { break; }
        $g = $filasAg[$k] ?? null;
        if (!$g) { $dif = "falta $k"; break; }
        ksort($e['anios']);
        $anios = implode(', ', array_keys($e['anios']));
        $ultimo = $e['pagados'] ? max(array_keys($e['pagados'])) : null;
        if ($g['nDeclaraciones'] !== $e['n'] || $g['valorDeclarado'] !== $e['valor'] || (int) $g['valorPagado'] !== $e['pagado']
            || (int) $g['intereses'] !== $e['intereses'] || $g['aniosPresentados'] !== $anios || $g['ultimoAnioPagado'] !== $ultimo) {
            $dif = "$k: agrupado " . json_encode($g) . ' contra detalle ' . json_encode($e + ['aniosTxt' => $anios, 'ultimo' => $ultimo]);
        }
    }
    prueba("ORÁCULO $clave: cada contribuyente agrupado = la suma de sus declaraciones vigentes del modo por declaración (valores, cantidad, años y último año pagado)", $dif === null && count($filasAg) > 3, $dif ?? '');
    $totalDet = ['valorDeclarado' => array_sum(array_column($porC, 'valor')), 'valorPagado' => array_sum(array_column($porC, 'pagado')), 'intereses' => array_sum(array_column($porC, 'intereses'))];
    $qTot = InformeConstructor::totales($con, $ag['q']);
    prueba("ORÁCULO $clave: los totales del modo agrupado coinciden con los del modo por declaración", $qTot['valorDeclarado'] === $totalDet['valorDeclarado'] && $qTot['valorPagado'] === $totalDet['valorPagado'] && $qTot['intereses'] === $totalDet['intereses'] && $ag['totales'] === $qTot, json_encode($qTot) . ' contra ' . json_encode($totalDet));
    $totDet = InformeConstructor::totales($con, $det['q']);
    prueba("ORÁCULO $clave: ...y con los totales que calcula el propio modo por declaración (SUM en SQL)", $qTot['valorDeclarado'] === $totDet['valorDeclarado'] && $qTot['valorPagado'] === $totDet['valorPagado'] && $qTot['intereses'] === $totDet['intereses']);
    prueba("ORÁCULO $clave: la suma de las cantidades = las filas del modo por declaración", array_sum(array_column($porC, 'n')) === $det['total']);
}
// Los casos sembrados, a mano (ICA): una original corregida no se suma; la corrección sí.
$ag = ejecutar($con, $ctx, $cfgAg('ica', [['c' => 'documento', 'op' => 'in', 'v' => [880000001, 880000002, 880000003, 880000004]]]));
$m = $porClave($ag);
$kB = '880000002-7|' . "Inversiones D'Luca S.A.S.";
$kC = '880000003|José Avendaño';
$kD = '880000004|100% Pérez [x]_y Gómez';
$kA = '880000001|María Peñaranda';
prueba('ICA, el contribuyente con una corrección: suma pagada + sin valor + la CORRECCIÓN (no la original corregida) = 1.020.000 en 3 declaraciones, años 2025 y 2026',
    isset($m[$kB]) && $m[$kB]['valorDeclarado'] === 1020000 && $m[$kB]['nDeclaraciones'] === 3 && $m[$kB]['aniosPresentados'] === '2025, 2026' && $m[$kB]['ultimoAnioPagado'] === 2026, json_encode($m[$kB] ?? null));
prueba('ICA: la original PAGADA que luego se corrigió tampoco se suma; Wompi de pruebas cuenta como sin pagar; los intereses son lo pagado de más',
    isset($m[$kC]) && $m[$kC]['nDeclaraciones'] === 3 && $m[$kC]['valorDeclarado'] === 800000 + 90000 + 160000 && $m[$kC]['valorPagado'] === 825000 && $m[$kC]['intereses'] === 25000, json_encode($m[$kC] ?? null));
prueba('ICA: lo pagado de menos no da intereses negativos y el último año pagado es el de esa declaración (2025)',
    isset($m[$kD]) && $m[$kD]['valorPagado'] === 390000 && $m[$kD]['intereses'] === null && $m[$kD]['ultimoAnioPagado'] === 2025 && $m[$kD]['nDeclaraciones'] === 1, json_encode($m[$kD] ?? null));
prueba('ICA: el borrador no cuenta, y quien pagó en 2025 y debe 2026 tiene último año pagado 2025',
    isset($m[$kA]) && $m[$kA]['nDeclaraciones'] === 2 && $m[$kA]['valorDeclarado'] === 750000 && $m[$kA]['aniosPresentados'] === '2025, 2026' && $m[$kA]['ultimoAnioPagado'] === 2025, json_encode($m[$kA] ?? null));
// Filtros de la declaración se aplican ANTES de agrupar.
$agAnio = $porClave(ejecutar($con, $ctx, $cfgAg('ica', [['c' => 'anio', 'op' => 'in', 'v' => [2026]], ['c' => 'documento', 'op' => 'in', 'v' => [880000002, 880000003]]])));
prueba('el filtro de año agrupa solo lo de ese año (la corrección de 2025 sale de la suma)',
    isset($agAnio[$kB]) && $agAnio[$kB]['valorDeclarado'] === 700000 && $agAnio[$kB]['nDeclaraciones'] === 2 && $agAnio[$kB]['aniosPresentados'] === '2026', json_encode($agAnio[$kB] ?? null));
$agPag = $porClave(ejecutar($con, $ctx, $cfgAg('ica', [['c' => 'estado', 'op' => 'in', 'v' => ['pagada']], ['c' => 'documento', 'op' => 'in', 'v' => [880000002, 880000003]]])));
prueba('el filtro de estado "pagada" suma solo las pagadas vigentes', isset($agPag[$kB]) && $agPag[$kB]['valorDeclarado'] === 700000 && $agPag[$kB]['nDeclaraciones'] === 1 && isset($agPag[$kC]) && $agPag[$kC]['nDeclaraciones'] === 1, json_encode($agPag));
$e = lanza(function () use ($con, $ctx, $cfgAg) { InformeConstructor::validar($con, $cfgAg('ica', [['c' => 'estado', 'op' => 'in', 'v' => ['corregida']]]), $ctx, 'estricto'); });
prueba('"Corregida" no es un estado de las declaraciones vigentes y se rechaza', $e instanceof InformeException);
$e = lanza(function () use ($con, $ctx, $cfgAg) { InformeConstructor::validar($con, $cfgAg('ica', [['c' => 'corregida', 'op' => '=', 'v' => true]]), $ctx, 'estricto'); });
prueba('...y el filtro "tiene una corrección" tampoco existe en este modo', $e instanceof InformeException);
$agSin = ejecutar($con, $ctx, $cfgAg('ica', [['c' => 'documento', 'op' => 'in', 'v' => [880000002]], ['c' => 'anio', 'op' => 'in', 'v' => [2099]]]));
prueba('un contribuyente sin declaraciones vigentes que cumplan los filtros no aparece', $agSin['total'] === 0 && $agSin['filas'] === [] && $agSin['totales']['valorDeclarado'] === 0);
// Filtros del contribuyente (fuera de la suma) y buscador.
$agNom = ejecutar($con, $ctx, $cfgAg('ica', [['c' => 'contribuyente', 'op' => 'contiene', 'v' => 'avendano'], ['c' => 'documento', 'op' => 'in', 'v' => [880000003]]]));
prueba('el filtro por nombre del contribuyente funciona en el modo agrupado', $agNom['total'] === 1 && $agNom['filas'][0][1] === 'José Avendaño');
$agTp = ejecutar($con, $ctx, $cfgAg('ica', [['c' => 'tipoPersona', 'op' => 'in', 'v' => [2]], ['c' => 'documento', 'op' => 'in', 'v' => [880000001, 880000002, 880000003, 880000004]]]));
prueba('...y el de tipo de persona', $agTp['total'] === 1 && $agTp['filas'][0][0] === '880000002-7');
$vAg = InformeConstructor::validar($con, $cfgAg('ica'), $ctx, 'estricto');
$qAg = InformeConstructor::armar($vAg, ['buscar' => '880000003 avendano']);
prueba('el buscador del modo agrupado busca el documento y el nombre (no el número de declaración)', InformeConstructor::contar($con, $qAg) === 1 && strpos($qAg['desde'], 'dec_NumeroDeclaracion') === false);
$qAg2 = InformeConstructor::armar($vAg, ['buscar' => '9100000005']);
prueba('...un número de declaración no encuentra contribuyentes', InformeConstructor::contar($con, $qAg2) === 0);
// Orden y paginación en el modo agrupado.
$jAg = vistaGeneral($cfgAg('ica'), ['ordenC' => 'valorDeclarado', 'ordenD' => 'desc', 'porPagina' => 100]);
$vals = array_column($jAg['datos']['filas'] ?? [], 5);
$sorted = $vals;
rsort($sorted);
prueba('el modo agrupado se ordena por una suma (descendente) en el servidor', ($jAg['ok'] ?? 0) === 1 && $vals === $sorted && count($vals) > 3, json_encode($jAg['mensaje'] ?? ''));
$juntasAg = [];
for ($pg = 1; $pg <= 30; $pg++) {
    $j = vistaGeneral($cfgAg('ica'), ['ordenC' => 'valorDeclarado', 'ordenD' => 'desc', 'porPagina' => 5, 'pagina' => $pg]);
    if (($j['ok'] ?? 0) !== 1) { break; }
    foreach ($j['datos']['filas'] as $f) { $juntasAg[] = $f[0] . '|' . $f[1]; }
    if ($pg >= $j['datos']['paginas']) { break; }
}
prueba('paginar el modo agrupado de a 5 da los mismos contribuyentes, sin repetir', count($juntasAg) === count($vals) && count(array_unique($juntasAg)) === count($juntasAg));
$jAg2 = vistaGeneral($cfgAg('ica', [], null, [['c' => 'ultimoAnioPagado', 'd' => 'desc']]), ['porPagina' => 100]);
prueba('...y por el último año pagado o por los años presentados (texto), sin error', ($jAg2['ok'] ?? 0) === 1 && ($jAg2['datos']['total'] ?? 0) === count($vals), json_encode($jAg2['mensaje'] ?? ''));
$jAg3 = vistaGeneral($cfgAg('ica'), ['ordenC' => 'aniosPresentados', 'ordenD' => 'asc', 'porPagina' => 100]);
prueba('ordenar por los años presentados funciona', ($jAg3['ok'] ?? 0) === 1);
// Cada columna del modo agrupado corre, sola y en el informe, con sus filtros.
$fAg = InformeCatalogo::fuente($con, 'ica:agrup');
$malAg = [];
foreach (array_chunk(array_keys($fAg['columnas']), 20) as $bloque) {
    try {
        $q = InformeConstructor::armar(InformeConstructor::validar($con, ['v' => 1, 'modo' => 'agrupado', 'impuesto' => 'ica', 'filtros' => [],
            'columnas' => array_map(function ($c) { return ['c' => $c]; }, $bloque), 'orden' => []], $ctx, 'estricto'));
        InformeConstructor::contar($con, $q);
        InformeConstructor::pagina($con, $q, 1, 5);
    } catch (\Throwable $e) { $malAg[] = substr($e->getMessage(), 0, 120); }
}
foreach ($fAg['columnas'] as $c => $def) {
    if ($def['orden'] === false) { continue; }
    try {
        $q = InformeConstructor::armar(InformeConstructor::validar($con, ['v' => 1, 'modo' => 'agrupado', 'impuesto' => 'ica', 'filtros' => [],
            'columnas' => $c === 'documento' ? [['c' => 'documento']] : [['c' => 'documento'], ['c' => $c]], 'orden' => [['c' => $c, 'd' => 'desc']]], $ctx, 'estricto'));
        InformeConstructor::pagina($con, $q, 1, 5);
    } catch (\Throwable $e) { $malAg[] = "orden $c: " . substr($e->getMessage(), 0, 120); }
}
foreach ($fAg['filtros'] as $c => $def) {
    $op = $def['ops'][0];
    $v = ['entero' => $op === 'between' ? [2020, 2030] : [2026], 'dinero' => $op === 'between' ? [0, 9] : 0, 'fecha' => $op === 'between' ? ['2026-01-01', '2026-12-31'] : '2026-01-01',
          'texto' => 'a', 'booleano' => true][$def['tipo']] ?? null;
    if ($def['tipo'] === 'lista') { $k = array_keys(InformeCatalogo::opcionesFiltro($con, $def)); $v = $k ? [$k[0]] : null; }
    if ($v === null) { continue; }
    try {
        $q = InformeConstructor::armar(InformeConstructor::validar($con, ['v' => 1, 'modo' => 'agrupado', 'impuesto' => 'ica', 'filtros' => [['c' => $c, 'op' => $op, 'v' => $v]],
            'columnas' => [['c' => 'documento'], ['c' => 'valorDeclarado'], ['c' => 'aniosPresentados']], 'orden' => []], $ctx, 'estricto'));
        InformeConstructor::contar($con, $q);
        InformeConstructor::totales($con, $q);
    } catch (\Throwable $e) { $malAg[] = "filtro $c: " . substr($e->getMessage(), 0, 120); }
}
prueba('el modo agrupado: cada columna, cada orden y cada filtro del catálogo corre sin error en la base', !$malAg, implode(' | ', array_slice($malAg, 0, 3)));
// Permisos.
comoUsuario($cuentas['solo_ica']);
$j = llamar('vista', ['config' => json_encode($cfgAg('ica'))]);
prueba('quien puede ver el ICA usa el modo agrupado del ICA', ($j['ok'] ?? 0) === 1, json_encode($j['mensaje'] ?? ''));
comoUsuario($cuentas['solo_ica']);
$j = llamar('vista', ['config' => json_encode($cfgAg('reteica'))]);
prueba('...pero no el de retención (el permiso es el del impuesto)', ($j['ok'] ?? 1) === 0 && ($j['sinPermiso'] ?? 0) === 1, json_encode($j['mensaje'] ?? ''));
comoUsuario($cuentas['solo_ica']);
$j = llamar('vista', ['config' => json_encode($cfgAg('ica', [], ['documento', 'correo']))]);
prueba('...ni los datos de contacto sin su permiso, también agrupado', ($j['ok'] ?? 1) === 0 && ($j['sinPermiso'] ?? 0) === 1);
comoUsuario($cuentas['sin_perm']);
$j = llamar('vista', ['config' => json_encode($cfgAg('ica'))]);
prueba('...ni quien no tiene el Informe general', ($j['ok'] ?? 1) === 0 && ($j['sinPermiso'] ?? 0) === 1);
comoUsuario($cuentas['contacto']);
$j = llamar('vista', ['config' => json_encode($cfgAg('ica', [], ['documento', 'correo', 'valorDeclarado']))]);
prueba('con el permiso de contacto se pueden agregar los datos del contribuyente a la fila agrupada', ($j['ok'] ?? 0) === 1, json_encode($j['mensaje'] ?? ''));
comoUsuario($cuentas['solo_ica']);
$jc = llamar('catalogo');
$fCat = $jc['datos']['fuentes'] ?? [];
prueba('el catálogo ofrece el modo agrupado del ICA con sus columnas de resumen y sin contacto (y no ofrece el de retención)',
    isset($fCat['ica:agrup']) && !isset($fCat['reteica:agrup']) && $fCat['ica:agrup']['modo'] === 'agrupado' && $fCat['ica:agrup']['impuesto'] === 'ica'
    && !array_diff(['nDeclaraciones', 'aniosPresentados', 'ultimoAnioPagado'], array_column($fCat['ica:agrup']['columnas'], 'c'))
    && !in_array('correo', array_column($fCat['ica:agrup']['columnas'], 'c'), true)
    && in_array('agrupado', array_column($jc['datos']['modos'], 'clave'), true), json_encode(array_keys($fCat)));
// El Excel del modo agrupado: ZIP válido, sumas y total.
$vAgX = InformeConstructor::validar($con, $cfgAg('ica'), $ctx, 'estricto');
$pAg = leerXlsx(InformeGeneralExcel::construir($con, $vAgX, $enc));
$okXmlAg = $pAg !== null;
foreach ($pAg ?? [] as $nombre => $xml) { $d = new DOMDocument(); if (!@$d->loadXML($xml)) { $okXmlAg = false; } }
$hAg = $pAg['xl/worksheets/sheet1.xml'] ?? '';
$nAg = ejecutar($con, $ctx, $cfgAg('ica'))['total'];
prueba("el Excel agrupado es un libro válido con una fila por contribuyente ($nAg), sumas con SUBTOTAL y el rótulo en 'contribuyentes'",
    $okXmlAg && substr_count($hAg, '<row ') === 4 + 1 + $nAg + 1 && strpos($hAg, 'SUBTOTAL(109,') !== false && strpos($hAg, 'Total: ' . $nAg . ' contribuyentes') !== false
    && strpos($hAg, 'Declaraciones vigentes (presentadas y sin una corrección presentada)') !== false && strpos($hAg, 'Industria y Comercio · por contribuyente') !== false, 'filas ' . substr_count($hAg, '<row '));
// Plantillas guardadas del modo agrupado.
if ($hayPlantillas) {
    comoUsuario($cuentas['solo_ica']);
    $j = llamar('guardar', ['nombre' => 'PI cartera por contribuyente', 'visibilidad' => 'PRIVADA', 'config' => json_encode($cfgAg('ica', [['c' => 'anio', 'op' => 'between', 'v' => [2025, 2026]]]))]);
    $idAg = $j['datos']['id'] ?? null;
    prueba('se guarda una plantilla del modo agrupado (la fuente queda como ica:agrup)', ($j['ok'] ?? 0) === 1 && ($j['datos']['modo'] ?? '') === 'agrupado' && ($j['datos']['fuente'] ?? '') === 'ica:agrup'
        && uno($con, 'SELECT ipl_Fuente AS f FROM ind_informe_plantillas WHERE ipl_Id = ?', [$idAg])['f'] === 'ica:agrup', json_encode($j));
    comoUsuario($cuentas['solo_ica']);
    $j = llamar('plantilla', ['id' => (string) $idAg]);
    prueba('...y se abre con su modo, su impuesto y sus filtros', ($j['ok'] ?? 0) === 1 && $j['datos']['config']['modo'] === 'agrupado' && $j['datos']['config']['impuesto'] === 'ica' && count($j['datos']['config']['filtros']) === 1, json_encode($j['mensaje'] ?? ''));
    comoUsuario($cuentas['solo_ica']);
    $j = llamar('plantillas');
    $lista = array_values(array_filter($j['datos']['plantillas'], function ($p) use ($idAg) { return (string) $p['id'] === (string) $idAg; }));
    prueba('...y el listado la muestra como del modo agrupado', count($lista) === 1 && $lista[0]['modo'] === 'agrupado');
    comoUsuario($cuentas['contacto']);
    $j = llamar('guardar', ['nombre' => 'PI agrupado de retención', 'config' => json_encode($cfgAg('autorreteica'))]);
    prueba('quien no ve la autorretención no guarda una plantilla agrupada de ella', ($j['ok'] ?? 1) === 0 && ($j['sinPermiso'] ?? 0) === 1);
    comoUsuario($cuentas['solo_ica']);
    llamar('borrar', ['id' => (string) $idAg]);
}

/* ======================================================================
   8. Esquema: lo que pasa cuando faltan migraciones (con --ddl)
   ====================================================================== */
if ($conDdl) {
    seccion('8. Tolerancia a migraciones ausentes (esquema modificado dentro de la transacción)');
    $ctx = ctxTotal();
    // sp_rename avisa ("Caution: Changing any part of an object name...") y el driver lo trata como error.
    if (function_exists('sqlsrv_configure')) { sqlsrv_configure('WarningsReturnAsErrors', 0); }
    $recorrerTodo = function ($etiqueta, array $fuentesEsperadas = ['ica', 'reteica', 'autorreteica', 'contribuyentes']) use ($con, $ctx) {
        InformeCatalogo::olvidar();
        InformePlantillas::olvidar();
        $mal = [];
        $presentes = [];
        foreach (['ica', 'reteica', 'autorreteica', 'contribuyentes'] as $clave) {
            $f = InformeCatalogo::fuente($con, $clave);
            if (!$f) { continue; }
            $presentes[] = $clave;
            foreach (array_chunk(array_keys($f['columnas']), 25) as $bloque) {
                try {
                    $q = InformeConstructor::armar(InformeConstructor::validar($con, ['v' => 1, 'modo' => $f['modo'], 'impuesto' => $f['impuesto'], 'filtros' => [],
                        'columnas' => array_map(function ($c) { return ['c' => $c]; }, $bloque), 'orden' => []], $ctx, 'estricto'));
                    InformeConstructor::contar($con, $q);
                    InformeConstructor::pagina($con, $q, 1, 5);
                } catch (\Throwable $e) { $mal[] = "$clave: " . substr($e->getMessage(), 0, 140); }
            }
            // El modo agrupado de cada impuesto se arma sobre el mismo esquema: también debe correr.
            if ($f['modo'] === 'declaracion') {
                $fa = InformeCatalogo::fuente($con, $clave . ':agrup');
                if ($fa) {
                    foreach (array_chunk(array_keys($fa['columnas']), 25) as $bloque) {
                        try {
                            $q = InformeConstructor::armar(InformeConstructor::validar($con, ['v' => 1, 'modo' => 'agrupado', 'impuesto' => $clave, 'filtros' => [],
                                'columnas' => array_map(function ($c) { return ['c' => $c]; }, $bloque), 'orden' => []], $ctx, 'estricto'));
                            InformeConstructor::contar($con, $q);
                            InformeConstructor::pagina($con, $q, 1, 5);
                        } catch (\Throwable $e) { $mal[] = "$clave:agrup: " . substr($e->getMessage(), 0, 140); }
                    }
                } else { $mal[] = "$clave:agrup no se ofrece"; }
            }
            foreach ($f['filtros'] as $c => $def) {
                $op = $def['ops'][0];
                $v = ['entero' => $op === 'between' ? [2020, 2030] : [2026], 'dinero' => $op === 'between' ? [0, 9] : 0, 'fecha' => $op === 'between' ? ['2026-01-01', '2026-12-31'] : '2026-01-01',
                      'texto' => !empty($def['ciiu']) ? '1234' : 'a', 'booleano' => true][$def['tipo']] ?? null;
                if ($def['tipo'] === 'lista') { $k = array_keys(InformeCatalogo::opcionesFiltro($con, $def)); $v = $k ? [$k[0]] : null; }
                if ($v === null) { continue; }
                try {
                    $q = InformeConstructor::armar(InformeConstructor::validar($con, ['v' => 1, 'modo' => $f['modo'], 'impuesto' => $f['impuesto'],
                        'filtros' => [['c' => $c, 'op' => $op, 'v' => $v]], 'columnas' => [['c' => array_keys($f['columnas'])[0]]], 'orden' => []], $ctx, 'estricto'));
                    InformeConstructor::contar($con, $q);
                } catch (\Throwable $e) { $mal[] = "$clave filtro $c: " . substr($e->getMessage(), 0, 140); }
            }
        }
        // Las plantillas del sistema que quedan siguen corriendo.
        foreach (InformePlantillas::listar($con, $ctx) as $pl) {
            if (!$pl['sistema']) { continue; }
            foreach ($pl['fuentes'] as $fu) {
                try { ejecutar($con, $ctx, InformePlantillas::obtener($con, $ctx, $pl['id'], $fu)['config']); }
                catch (\Throwable $e) { $mal[] = $pl['id'] . "/$fu: " . substr($e->getMessage(), 0, 140); }
            }
        }
        $presenteOk = $presentes === $fuentesEsperadas;
        prueba($etiqueta . ': todo lo que el catálogo ofrece corre' . ($presenteOk ? '' : ' (fuentes: ' . implode(',', $presentes) . ')'), !$mal && $presenteOk, implode(' | ', array_slice($mal, 0, 3)));
        return $presentes;
    };
    $ddl = function ($sql) use ($con) { $con->consultar($sql); };
    $columnasCatalogo = function ($clave) use ($con) { InformeCatalogo::olvidar(); $f = InformeCatalogo::fuente($con, $clave); return $f ? array_keys($f['columnas']) : []; };

    $base0 = $columnasCatalogo('ica');
    // 044: sin pagos manuales ni declaraciones históricas.
    $ddl("EXEC sp_rename 'dbo.ind_pagos_manuales', 'x_pm_prueba'");
    $recorrerTodo('sin la 044 (pagos manuales)');
    $cols = $columnasCatalogo('ica');
    prueba('   y se ocultan la cuenta contable (depende del pago manual)', !in_array('cuentaContable', $cols, true) && in_array('banco', $cols, true) && in_array('fechaPresentacion', $cols, true));
    $ddl("EXEC sp_rename 'dbo.x_pm_prueba', 'ind_pagos_manuales'");
    // 049: sin la fecha de presentación del pago manual.
    $ddl('ALTER TABLE dbo.ind_pagos_manuales DROP COLUMN pma_FechaPresentacion');
    $recorrerTodo('sin la 049 (fecha de presentación del pago manual)');
    $ddl("ALTER TABLE dbo.ind_pagos_manuales ADD pma_FechaPresentacion DATE NULL");
    // 051: sin cuentas contables.
    $ddl('ALTER TABLE dbo.ind_pagos_manuales DROP COLUMN pma_CuentaContable');
    $recorrerTodo('sin la 051 (cuenta contable)');
    $cols = $columnasCatalogo('ica');
    prueba('   y se oculta la cuenta contable', !in_array('cuentaContable', $cols, true));
    $ddl('ALTER TABLE dbo.ind_pagos_manuales ADD pma_CuentaContable VARCHAR(70) NULL');
    // 045: sin el banco por id.
    foreach (['dec' => 'ind_declaraciones_ica', 'ret' => 'ind_reteica', 'aut' => 'ind_autorreteica'] as $p => $t) {
        $ddl("ALTER TABLE dbo.$t DROP CONSTRAINT FK_{$p}_IdBancoPago");
        $ddl("ALTER TABLE dbo.$t DROP COLUMN {$p}_IdBancoPago");
    }
    $recorrerTodo('sin la 045 (banco por id)');
    $f = InformeCatalogo::fuente($con, 'ica');
    prueba('   y el filtro de banco no se ofrece, pero la columna "Banco" (texto) sí', !isset($f['filtros']['banco']) && isset($f['columnas']['banco']));
    foreach (['dec' => 'ind_declaraciones_ica', 'ret' => 'ind_reteica', 'aut' => 'ind_autorreteica'] as $p => $t) {
        $ddl("ALTER TABLE dbo.$t ADD {$p}_IdBancoPago INT NULL");
    }
    // Columnas del ICA que ninguna migración crea.
    $ddl("EXEC sp_rename 'dbo.ind_declaraciones_ica.dec_FechaPresentacion', 'dec_FechaPresentacionX', 'COLUMN'");
    $recorrerTodo('sin dec_FechaPresentacion');
    $cols = $columnasCatalogo('ica');
    prueba('   y el ICA no ofrece la fecha de presentación (ni falla)', !in_array('fechaPresentacion', $cols, true));
    $ddl("EXEC sp_rename 'dbo.ind_declaraciones_ica.dec_FechaPresentacionX', 'dec_FechaPresentacion', 'COLUMN'");
    $ddl("EXEC sp_rename 'dbo.ind_declaraciones_ica.dec_CapacidadInstalada', 'dec_CapacidadInstaladaX', 'COLUMN'");
    $ddl("EXEC sp_rename 'dbo.ind_declaraciones_ica.dec_ValorImpuesto', 'dec_ValorImpuestoX', 'COLUMN'");
    $recorrerTodo('sin dec_CapacidadInstalada ni dec_ValorImpuesto');
    $cols = $columnasCatalogo('ica');
    prueba('   y se ocultan la capacidad instalada y el impuesto de energía', !in_array('capacidadInstalada', $cols, true) && !in_array('impuestoEnergia', $cols, true));
    $ddl("EXEC sp_rename 'dbo.ind_declaraciones_ica.dec_CapacidadInstaladaX', 'dec_CapacidadInstalada', 'COLUMN'");
    $ddl("EXEC sp_rename 'dbo.ind_declaraciones_ica.dec_ValorImpuestoX', 'dec_ValorImpuesto', 'COLUMN'");
    // Sin la ruta de pago (014/032).
    $ddl("EXEC sp_rename 'dbo.ind_declaraciones_ica.dec_RutaPago', 'dec_RutaPagoX', 'COLUMN'");
    $recorrerTodo('sin dec_RutaPago');
    $cols = $columnasCatalogo('ica');
    prueba('   y el ICA no ofrece el medio de pago', !in_array('canal', $cols, true) && in_array('pagada', $cols, true));
    $ddl("EXEC sp_rename 'dbo.ind_declaraciones_ica.dec_RutaPagoX', 'dec_RutaPago', 'COLUMN'");
    // Sin la 038 (tipo de sanción).
    $ddl("EXEC sp_rename 'dbo.ind_declaraciones_ica.dec_TipoSancion', 'dec_TipoSancionX', 'COLUMN'");
    $recorrerTodo('sin la 038 (tipo de sanción)');
    $ddl("EXEC sp_rename 'dbo.ind_declaraciones_ica.dec_TipoSancionX', 'dec_TipoSancion', 'COLUMN'");
    // Sin la firma del RIT (008).
    $ddl("EXEC sp_rename 'dbo.ind_rit_firmas', 'x_rit_prueba'");
    $recorrerTodo('sin la 008 (firmas del RIT)');
    $cols = $columnasCatalogo('contribuyentes');
    prueba('   y el padrón no ofrece la firma del RIT', !in_array('ritFirmado', $cols, true) && in_array('documento', $cols, true));
    $ddl("EXEC sp_rename 'dbo.x_rit_prueba', 'ind_rit_firmas'");
    // Sin el módulo de retención (030).
    $ddl("EXEC sp_rename 'dbo.ind_reteica', 'x_ret_prueba'");
    $recorrerTodo('sin la tabla de retención (030)', ['ica', 'autorreteica', 'contribuyentes']);
    $e = lanza(function () use ($con, $ctx, $cfgRet) { InformeConstructor::validar($con, $cfgRet, $ctx, 'estricto'); });
    prueba('   y pedir retención da un mensaje claro', $e instanceof InformeException && strpos($e->getMessage(), 'no está disponible') !== false, $e ? $e->getMessage() : 'sin error');
    comoUsuario($cuentas['admin']);
    $j = llamar('catalogo');
    prueba('   y el catálogo no la ofrece', !isset($j['datos']['fuentes']['reteica']) && isset($j['datos']['fuentes']['ica']));
    $ddl("EXEC sp_rename 'dbo.x_ret_prueba', 'ind_reteica'");
    // Sin la 056.
    if ($hayPlantillas) {
        $ddl("EXEC sp_rename 'dbo.ind_informe_plantillas', 'x_plantillas_prueba'");
        InformePlantillas::olvidar();
        comoUsuario($cuentas['admin']);
        $j = llamar('plantillas');
        prueba('sin la 056: se listan las plantillas del sistema y se avisa que no se puede guardar', ($j['ok'] ?? 0) === 1 && ($j['datos']['guardar'] ?? 1) === false && count($j['datos']['plantillas']) >= 9);
        comoUsuario($cuentas['admin']);
        $j = llamar('guardar', ['nombre' => 'X', 'config' => json_encode($cfgIca)]);
        prueba('sin la 056: guardar dice que falta la migración', ($j['ok'] ?? 1) === 0 && strpos($j['mensaje'], '056') !== false);
        comoUsuario($cuentas['admin']);
        $j = llamar('vista', ['config' => json_encode($cfgIca)]);
        prueba('sin la 056: la vista previa sigue funcionando', ($j['ok'] ?? 0) === 1);
        comoUsuario($cuentas['admin']);
        $j = llamar('catalogo');
        prueba('sin la 056: el catálogo marca que no se puede guardar', ($j['datos']['puede']['guardar'] ?? 1) === false);
        $ddl("EXEC sp_rename 'dbo.x_plantillas_prueba', 'ind_informe_plantillas'");
    }
    InformeCatalogo::olvidar();
    InformePlantillas::olvidar();
    $despues = $columnasCatalogo('ica');
    prueba('al restaurar el esquema el catálogo vuelve a ser el mismo', $despues === $base0);
}

/* ======================================================================
   Fin
   ====================================================================== */
seccion('Resumen');
prueba('ningún aviso ni notice de PHP en el código del informe', !$avisosPhp, implode(' | ', array_slice($avisosPhp, 0, 3)));
$con->rollback();
$despuesDoc = (int) uno($con, 'SELECT COUNT(*) AS n FROM ind_contribuyentes WHERE ind_NumeroIdentificacion BETWEEN 880000001 AND 880000004')['n'];
prueba('la transacción se deshizo: no queda nada de la siembra', $despuesDoc === 0);
$total = $ok + count($fallas);
echo "\n" . ($fallas ? "FALLARON " . count($fallas) . " de $total:\n  - " . implode("\n  - ", $fallas) . "\n" : "TODO BIEN: $ok de $total pruebas.\n");
exit($fallas ? 1 : 0);
