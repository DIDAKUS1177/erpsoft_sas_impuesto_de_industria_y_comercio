<?php
namespace erpsoftsas;

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.conexionSqlServer.php';
include_once SERVER . '/business/controller/class.cabecera.php';
include_once SERVER . '/business/class.permisosRol.php';
include_once SERVER . '/business/class.informeCatalogo.php';
include_once SERVER . '/business/class.informeConstructor.php';
include_once SERVER . '/business/class.informePlantillas.php';

/**
 * El Informe general (informes dinámicos, migración 056): el catálogo de lo que
 * se puede pedir, la vista previa y las plantillas. El Excel va aparte, en
 * extensiones/informeGeneral.php, porque es una descarga y no JSON.
 *
 * POST application/x-www-form-urlencoded (o multipart) con X-Requested-With
 * (jQuery lo manda solo). Responde siempre { ok, mensaje, datos } y, cuando
 * toca, sinSesion = 1 (la sesión terminó) o sinPermiso = 1.
 *
 *   funcion = catalogo     lo que esta sesión puede pedir: fuentes, columnas y filtros
 *   funcion = plantillas   las plantillas que puede ver (sistema, compartidas, propias)
 *   funcion = plantilla    una plantilla lista para cargar: id [, fuente]
 *   funcion = vista        la vista previa: config (JSON) [, pagina, porPagina, buscar, ordenC, ordenD]
 *                          buscar: texto libre (documento, nombre o número de declaración);
 *                          ordenC/ordenD: la columna del informe y el sentido (asc|desc) que eligió
 *                          la persona en el encabezado; manda sobre el orden de la configuración.
 *   funcion = guardar      crea (sin id) o modifica (con id): nombre, descripcion,
 *                          visibilidad (PRIVADA | MUNICIPIO), config (JSON)
 *   funcion = borrar       id
 *
 * Quién: sesión y alcaldia.informes.personalizados (que solo cuenta en un rol de la
 * Alcaldía); además el "Ver" de cada fuente y, para las columnas de contacto,
 * alcaldia.informes.contacto. Se autoriza en CADA petición con el rol de quien
 * la hace, no con el de quien guardó una plantilla.
 *
 * La pantalla manda solo CLAVES del catálogo y valores: ni nombres de tabla o de
 * columna ni SQL (business/class.informeConstructor.php lo valida todo).
 */
class ControladorInformes extends \erpsoftsas\Cabecera
{
    const FUNCIONES = ['catalogo', 'plantillas', 'plantilla', 'vista', 'guardar', 'borrar'];

    private static function _responder($ok, $mensaje, $datos = [], array $extra = [])
    {
        // JSON_HEX_*: aunque algún día esta respuesta se pegue en una página, un dato
        // con < o ' no puede abrir una etiqueta ni cerrar una cadena.
        $json = json_encode(
            array_merge(['ok' => $ok ? 1 : 0, 'mensaje' => $mensaje, 'datos' => $datos], $extra),
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE
        );
        echo $json !== false ? $json : '{"ok":0,"mensaje":"No se pudo armar la respuesta.","datos":[]}';
    }

    /** Un campo del POST como texto (un arreglo o un objeto cuenta como vacío). */
    private static function _texto($clave)
    {
        $v = $_POST[$clave] ?? '';
        return is_scalar($v) ? (string) $v : '';
    }

    public static function run()
    {
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }
        header('Content-type: application/json');

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            self::_responder(false, 'Solicitud no válida.');
            return;
        }
        $funcion = self::_texto('funcion');
        if (!in_array($funcion, self::FUNCIONES, true)) {
            self::_responder(false, 'Función no válida.');
            return;
        }
        if (empty($_SESSION['id_usuario'])) {
            self::_responder(false, 'Su sesión terminó. Inicie sesión de nuevo.', [], ['sinSesion' => 1]);
            return;
        }

        // Lo que puede esta sesión, leído una vez; de aquí en adelante el motor solo usa este arreglo.
        $ctx = InformeCatalogo::contexto();
        if ($ctx['usuario'] <= 0) {
            // La cuenta se inactivó o ya no existe: PermisosRol borró la identidad de la sesión.
            self::_responder(false, 'Su sesión terminó. Inicie sesión de nuevo.', [], ['sinSesion' => 1]);
            return;
        }
        if (!$ctx['personalizados']) {
            PermisosRol::negar(PermisosRol::mensaje('alcaldia.informes.personalizados'));
            return;
        }
        // Solo desde la pantalla (jQuery manda esta cabecera): un formulario de otro sitio no
        // puede usar la sesión abierta para guardar o borrar plantillas.
        if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'xmlhttprequest') {
            self::_responder(false, 'Solicitud no válida.');
            return;
        }

        // Ya se sabe quién es: soltar la sesión para que sus otras pestañas no esperen mientras se arma una vista.
        session_write_close();

        try {
            $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
            switch ($funcion) {
                case 'catalogo':
                    $datos = InformeCatalogo::paraPantalla($con, $ctx);
                    $datos['version'] = InformeCatalogo::VERSION;
                    $datos['ciudad'] = defined('MUNICIPIO_CIUDAD') ? (string) MUNICIPIO_CIUDAD : '';
                    $datos['puede'] = [
                        'contacto'   => (bool) $ctx['contacto'],
                        'plantillas' => (bool) $ctx['plantillas'],
                        'guardar'    => InformePlantillas::hayTabla($con),
                    ];
                    $datos['limites'] = [
                        'columnas' => InformeCatalogo::MAX_COLUMNAS, 'filtros' => InformeCatalogo::MAX_FILTROS,
                        'orden' => InformeCatalogo::MAX_ORDEN, 'valores' => InformeCatalogo::MAX_VALORES,
                        'titulo' => InformeCatalogo::MAX_TITULO, 'texto' => InformeCatalogo::MAX_TEXTO,
                        'nombrePlantilla' => InformePlantillas::MAX_NOMBRE, 'descripcionPlantilla' => InformePlantillas::MAX_DESCRIPCION,
                        'filasPorPagina' => Paginacion::TAMANOS, 'filasExcel' => InformeConstructor::$maxFilasExcel,
                        'celdasExcel' => InformeConstructor::$maxCeldasExcel,
                    ];
                    self::_responder(true, '', $datos);
                    return;

                case 'plantillas':
                    self::_responder(true, '', [
                        'guardar' => InformePlantillas::hayTabla($con),
                        'plantillas' => InformePlantillas::listar($con, $ctx),
                    ]);
                    return;

                case 'plantilla':
                    $fuente = self::_texto('fuente');
                    self::_responder(true, '', InformePlantillas::obtener($con, $ctx, self::_texto('id'), $fuente === '' ? null : $fuente));
                    return;

                case 'vista':
                    $v = InformeConstructor::validar($con, InformeConstructor::leerJson(self::_texto('config')), $ctx, 'estricto');
                    // Búsqueda y orden de la vista previa: se validan aquí y las usa el MISMO constructor que el Excel.
                    $opc = InformeConstructor::opcionesDeVista($v, self::_texto('buscar'), self::_texto('ordenC'), self::_texto('ordenD'));
                    $q = InformeConstructor::armar($v, $opc);
                    [$pagina, $porPagina] = Paginacion::leerPedido($_POST);
                    $r = InformeConstructor::pagina($con, $q, $pagina, $porPagina);
                    $columnas = [];
                    foreach ($q['columnas'] as $m) {
                        $columnas[] = ['c' => $m['c'], 't' => $m['titulo'], 'tipo' => $m['tipo'],
                                       'total' => !empty($m['def']['total']), 'alinea' => $m['def']['alinea'] ?: 'L',
                                       'ordenable' => $m['def']['orden'] !== false];
                    }
                    self::_responder(true, '', [
                        'columnas' => $columnas, 'filas' => $r['filas'], 'total' => $r['total'], 'pagina' => $r['pagina'],
                        // Un objeto aun sin columnas que sumar ({}), no una lista vacía ([]).
                        'paginas' => $r['paginas'], 'porPagina' => $r['porPagina'], 'totales' => (object) $r['totales'],
                        'unidad' => $q['fuente']['unidad'],
                        'filtros' => InformeConstructor::describirFiltros($con, $v),
                        'buscar' => $opc['buscar'] ?? '', 'orden' => $opc['orden'] ?? null,
                        'avisos' => $v['avisos'],
                        // Para que la pantalla ofrezca el Excel o avise que no cabe.
                        'excel' => ['permitido' => $r['total'] <= InformeConstructor::$maxFilasExcel
                                                   && $r['total'] * count($columnas) <= InformeConstructor::$maxCeldasExcel,
                                    'maxFilas' => InformeConstructor::$maxFilasExcel, 'maxCeldas' => InformeConstructor::$maxCeldasExcel],
                    ]);
                    return;

                case 'guardar':
                    $p = InformePlantillas::guardar($con, $ctx, [
                        'id' => self::_texto('id'), 'nombre' => self::_texto('nombre'),
                        'descripcion' => self::_texto('descripcion'), 'visibilidad' => self::_texto('visibilidad'),
                        'config' => InformeConstructor::leerJson(self::_texto('config')),
                    ]);
                    self::_responder(true, self::_texto('id') === '' ? 'Plantilla guardada.' : 'Plantilla actualizada.', $p);
                    return;

                case 'borrar':
                    InformePlantillas::borrar($con, $ctx, self::_texto('id'));
                    self::_responder(true, 'Plantilla borrada.');
                    return;
            }
        } catch (InformeException $e) {
            // Un mensaje pensado para la persona: una configuración que no vale, un permiso, un tope.
            self::_responder(false, $e->getMessage(), [], $e->permiso ? ['sinPermiso' => 1] : []);
        } catch (\Throwable $e) {
            // Lo demás es un fallo del servidor: al log, y a la pantalla un mensaje que no revela SQL.
            error_log('[informe general] ' . $funcion . ': ' . $e->getMessage());
            self::_responder(false, 'No se pudo procesar la solicitud. Intente de nuevo.');
        }
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    \erpsoftsas\ControladorInformes::run();
}
