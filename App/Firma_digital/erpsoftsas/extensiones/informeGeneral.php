<?php
/**
 * Excel del Informe general (informes dinámicos, migración 056).
 *
 * La pantalla (dist/informes.php, pestaña "Informe general") lo pide cuando la
 * persona pulsa "Excel". Es una descarga y no JSON, por eso vive aquí y no en
 * business/controller/class.informes.php (que hace el catálogo, la vista previa y
 * las plantillas).
 *
 * POST (el formulario o un XHR con responseType blob; nunca GET: la
 * configuración puede llevar el NIT de una persona y no debe quedar en la
 * dirección, en el historial ni en el registro del servidor)
 *   config     la configuración del informe en JSON (lo que arma la pantalla), o
 *   plantilla  el id de una plantilla ('sistema:pagadas', '12') [+ fuente para las del sistema]
 *   titulo     (opcional) el título que lleva el archivo; por defecto el nombre de la plantilla
 *   buscar, ordenC, ordenD   (opcionales) el buscador y el orden de la vista previa, cuando la persona
 *              quiere el Excel con lo mismo que ve; el MISMO constructor los aplica y el encabezado del
 *              Excel los dice ("Búsqueda: «...»", "Orden: ...")
 *
 * Responde el .xlsx, o un texto plano con el motivo y el código HTTP: 400 la
 * configuración no vale o no cabe, 401 sin sesión, 403 sin permiso, 405 no es POST,
 * 500 fallo del servidor.
 *
 * Quién: sesión y alcaldia.informes.personalizados, el "Ver" de la fuente y, para las
 * columnas de contacto, alcaldia.informes.contacto. Se autoriza con el rol de quien
 * DESCARGA, aunque la plantilla sea de otra persona.
 *
 * Límites: el Excel se arma en memoria (sin librerías ni la extensión zip), así que
 * tiene un tope de filas y de celdas (InformeConstructor::$maxFilasExcel y
 * $maxCeldasExcel). Pasado el tope NO se recorta en silencio —un informe a medias
 * es peor que ninguno—: se rechaza y se pide acotarlo con filtros. Las filas se leen
 * por lotes, así que el resultado completo nunca está en memoria a la vez.
 */

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.conexionSqlServer.php';
include_once SERVER . '/business/class.permisosRol.php';
include_once SERVER . '/business/class.informeCatalogo.php';
include_once SERVER . '/business/class.informeConstructor.php';
include_once SERVER . '/business/class.informePlantillas.php';
include_once SERVER . '/business/class.xlsxSimple.php';

use erpsoftsas\InformeCatalogo;
use erpsoftsas\InformeConstructor;
use erpsoftsas\InformeException;
use erpsoftsas\InformePlantillas;
use erpsoftsas\PermisosRol;
use erpsoftsas\XlsxSimple;

/**
 * Arma el libro. Separado del endpoint para poder probarlo sin pasar por
 * exit() ni cabeceras.
 */
class InformeGeneralExcel
{
    /** Ancho de columna (caracteres) cuando el catálogo no trae uno. */
    const ANCHOS = ['texto' => 24, 'codigo' => 16, 'entero' => 10, 'anio' => 8, 'dinero' => 16, 'fecha' => 13,
                    'lista' => 18, 'multilista' => 28, 'booleano' => 10];

    /** Una celda de Excel admite hasta 32.767 caracteres; más y el archivo se marca como dañado. */
    const MAX_TEXTO_CELDA = 32000;

    /**
     * El nombre de la hoja a partir del título. Excel no admite \ / ? * [ ] :
     * ni un apóstrofo al principio o al final, ni el nombre "History", ni una
     * hoja sin nombre, y corta a 31 caracteres: un título como 'Cartera' (con
     * apóstrofos) dejaba un archivo que Excel pide "reparar". Un apóstrofo en
     * el medio ("D'Luca") se cambia por el tipográfico (’): XlsxSimple cita el
     * nombre entre apóstrofos al definir el rango del autofiltro y uno recto
     * dentro de esa cita rompería la fórmula.
     */
    public static function nombreHoja($titulo)
    {
        $n = preg_replace('/[\\\\\/\?\*\[\]:\x00-\x1F\x7F]/u', ' ', (string) $titulo);
        $n = trim((string) preg_replace('/\s+/u', ' ', (string) $n), " '");
        $n = trim(mb_substr(str_replace("'", '’', $n), 0, 31, 'UTF-8'));
        return ($n === '' || strcasecmp($n, 'history') === 0) ? 'Informe' : $n;
    }

    /**
     * @param array $validada  la salida de InformeConstructor::validar
     * @param array $enc       titulo, municipio, generado, color (hex) y avisos
     * @return string los bytes del .xlsx
     */
    public static function construir($con, array $validada, array $enc)
    {
        $opc = $enc['opc'] ?? [];
        $q = InformeConstructor::armar($validada, $opc);
        $columnas = $q['columnas'];
        $nCol = count($columnas);

        // Primero el tamaño: si no cabe, ni se empieza.
        $total = InformeConstructor::contar($con, $q);
        if ($total > InformeConstructor::$maxFilasExcel) {
            throw new InformeException('El informe tiene ' . number_format($total, 0, ',', '.') . ' filas y el Excel admite hasta '
                . number_format(InformeConstructor::$maxFilasExcel, 0, ',', '.') . '. Agregue filtros (por ejemplo un año o un rango de fechas) para acotarlo.');
        }
        if ($total * $nCol > InformeConstructor::$maxCeldasExcel) {
            throw new InformeException('El informe tiene ' . number_format($total, 0, ',', '.') . ' filas por ' . $nCol
                . ' columnas y el Excel admite hasta ' . number_format(InformeConstructor::$maxCeldasExcel, 0, ',', '.')
                . ' celdas. Quite columnas o agregue filtros para acotarlo.');
        }

        $x = new XlsxSimple(self::nombreHoja($enc['titulo']), (string) ($enc['color'] ?? '1FA49D'));
        $anchos = [];
        foreach ($columnas as $m) { $anchos[] = $m['def']['ancho'] ?: (self::ANCHOS[$m['tipo']] ?? 20); }
        $x->anchos($anchos);
        $ultima = XlsxSimple::columna($nCol);
        // Combinar una sola celda (A1:A1) es un rango que Excel pide reparar: con una columna no se une.
        $unir = function ($fila) use ($x, $ultima, $nCol) {
            if ($nCol > 1) { $x->unir('A' . $fila, $ultima . $fila); }
        };

        $x->texto(1, 1, $enc['titulo'], XlsxSimple::TITULO);
        $unir(1);
        $x->texto(2, 1, $validada['fuente']['etiqueta'] . ' · Municipio de ' . $enc['municipio'], XlsxSimple::SUBTITULO);
        $unir(2);
        $x->texto(3, 1, InformeConstructor::describirConsulta($con, $validada, $opc), XlsxSimple::SUBTITULO);
        $unir(3);
        $x->texto(4, 1, $enc['generado'], XlsxSimple::SUBTITULO);
        $unir(4);
        if (!empty($enc['avisos'])) {
            $x->texto(5, 1, 'Nota: ' . implode(' ', $enc['avisos']), XlsxSimple::SUBTITULO);
            $unir(5);
        }

        $cab = 6;
        foreach ($columnas as $i => $m) { $x->texto($cab, $i + 1, $m['titulo'], XlsxSimple::ENCABEZADO); }

        $fila = $cab;
        $sumas = [];
        InformeConstructor::recorrer($con, $q, function (array $valores) use ($x, &$fila, $columnas, &$sumas) {
            $fila++;
            foreach ($columnas as $i => $m) {
                $v = $valores[$i];
                $col = $i + 1;
                switch ($m['tipo']) {
                    case 'fecha':
                        $x->fecha($fila, $col, $v === null ? '' : $v);
                        break;
                    case 'dinero':
                        if ($v === null) { $x->texto($fila, $col, '', XlsxSimple::PESOS); }
                        else {
                            $x->numero($fila, $col, $v, XlsxSimple::PESOS);
                            $sumas[$i] = ($sumas[$i] ?? 0) + $v;
                        }
                        break;
                    case 'entero':
                        if ($v === null) { $x->texto($fila, $col, '', XlsxSimple::ENTERO); }
                        else { $x->numero($fila, $col, $v, XlsxSimple::ENTERO); }
                        break;
                    case 'anio':
                        // Número sin separador de miles (2026, no 2.026), centrado.
                        if ($v === null) { $x->texto($fila, $col, '', XlsxSimple::CENTRADO); }
                        else { $x->numero($fila, $col, $v, XlsxSimple::CENTRADO); }
                        break;
                    default:
                        // Texto, códigos, listas y Sí/No: siempre como texto (inlineStr), así un
                        // documento no pierde ceros ni un texto que empiece por = se evalúa como fórmula.
                        $estilo = !empty($m['def']['ajuste']) ? XlsxSimple::TEXTO_AJUSTADO
                                : (($m['def']['alinea'] ?? '') === 'C' ? XlsxSimple::CENTRADO : XlsxSimple::TEXTO);
                        $texto = $v === null ? '' : (string) $v;
                        // Una celda admite 32.767 caracteres: un texto libre más largo dejaría el archivo dañado.
                        if (mb_strlen($texto, 'UTF-8') > self::MAX_TEXTO_CELDA) {
                            $texto = mb_substr($texto, 0, self::MAX_TEXTO_CELDA, 'UTF-8') . '…';
                        }
                        $x->texto($fila, $col, $texto, $estilo);
                }
            }
        });

        $x->congelarDebajoDe($cab);
        if ($fila > $cab) {
            $x->autofiltro('A' . $cab . ':' . $ultima . $fila);
            // SUBTOTAL(109, …): el total sigue a los filtros que se apliquen en Excel.
            $t = $fila + 1;
            $unidad = $validada['fuente']['unidad'];
            $n = $fila - $cab;
            $rotulo = 'Total: ' . number_format($n, 0, ',', '.') . ' ' . ($n === 1 ? $unidad['singular'] : $unidad['plural']);
            // El rótulo va en la primera columna que NO suma: si la primera columna es de dinero, su
            // suma no puede quedar tapada por el texto. Si todas suman, el rótulo va en la fila de abajo.
            $colRotulo = null;
            foreach ($columnas as $i => $m) { if (empty($m['def']['total'])) { $colRotulo = $i; break; } }
            foreach ($columnas as $i => $m) {
                if (!empty($m['def']['total'])) {
                    $letra = XlsxSimple::columna($i + 1);
                    $x->formula($t, $i + 1, 'SUBTOTAL(109,' . $letra . ($cab + 1) . ':' . $letra . $fila . ')', XlsxSimple::TOTAL_PESOS, $sumas[$i] ?? 0);
                } elseif ($i === $colRotulo) {
                    $x->texto($t, $i + 1, $rotulo, XlsxSimple::TOTAL);
                } else {
                    $x->texto($t, $i + 1, '', XlsxSimple::TOTAL);
                }
            }
            if ($colRotulo === null) {
                $x->texto($t + 1, 1, $rotulo, XlsxSimple::SUBTITULO);
            }
        } else {
            $x->texto($cab + 1, 1, 'No hay ' . $validada['fuente']['unidad']['plural'] . ' con estos filtros.', XlsxSimple::SUBTITULO);
            $unir($cab + 1);
        }
        return $x->contenido();
    }

    /** El nombre del archivo: solo letras, números y guiones bajos. */
    public static function nombreArchivo($titulo, $fecha)
    {
        $sinTildes = strtr((string) $titulo, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
                                              'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N']);
        $slug = trim(preg_replace('/[^A-Za-z0-9]+/', '_', $sinTildes), '_');
        return 'Informe_' . ($slug !== '' ? substr($slug, 0, 60) : 'general') . '_' . $fecha . '.xlsx';
    }
}

function informe_general_fin($codigo, $texto)
{
    http_response_code($codigo);
    header('Content-Type: text/plain; charset=utf-8');
    exit($texto);
}

function informe_general_run()
{
    if (session_status() === PHP_SESSION_NONE) { @session_start(); }

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        informe_general_fin(405, 'Solicitud no válida.');
    }
    if (empty($_SESSION['id_usuario'])) {
        informe_general_fin(401, 'Debe iniciar sesión para descargar el informe.');
    }
    $ctx = InformeCatalogo::contexto();
    if ($ctx['usuario'] <= 0) {
        informe_general_fin(401, 'Debe iniciar sesión para descargar el informe.');
    }
    if (!$ctx['personalizados']) {
        informe_general_fin(403, PermisosRol::mensaje('alcaldia.informes.personalizados'));
    }

    $campo = function ($c) { $v = $_POST[$c] ?? ''; return is_scalar($v) ? (string) $v : ''; };
    $texto = $campo('config');
    $idPlantilla = $campo('plantilla');
    $buscar = $campo('buscar');
    $ordenC = $campo('ordenC');
    $ordenD = $campo('ordenD');
    $titulo = trim(preg_replace('/\s+/u', ' ', $campo('titulo')) ?? '');
    if ($titulo !== '' && (preg_match('/[<>\x00-\x1F\x7F]/', $titulo) || mb_strlen($titulo, 'UTF-8') > InformePlantillas::MAX_NOMBRE)) {
        informe_general_fin(400, 'El título del informe no puede llevar los signos < ni > y tiene hasta ' . InformePlantillas::MAX_NOMBRE . ' caracteres.');
    }
    if ($texto === '' && $idPlantilla === '') {
        informe_general_fin(400, 'Falta la configuración del informe.');
    }

    $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
    $opc = [];
    try {
        $avisos = [];
        if ($texto !== '') {
            $validada = InformeConstructor::validar($con, InformeConstructor::leerJson($texto), $ctx, 'estricto');
        } else {
            // Una plantilla (la que sea, aunque no sea suya): sus claves se validan con los permisos de quien descarga.
            $fuente = $campo('fuente');
            $p = InformePlantillas::obtener($con, $ctx, $idPlantilla, $fuente === '' ? null : $fuente);
            $avisos = $p['avisos'];
            $validada = InformeConstructor::validar($con, $p['config'], $ctx, 'estricto');
            if ($titulo === '') { $titulo = $p['plantilla']['nombre']; }
        }
        // El buscador y el orden de la vista previa (opcionales), validados como en la vista previa.
        $opc = InformeConstructor::opcionesDeVista($validada, $buscar, $ordenC, $ordenD);
    } catch (InformeException $e) {
        informe_general_fin($e->permiso ? 403 : 400, $e->getMessage());
    } catch (\Throwable $e) {
        error_log('[informe general excel] ' . $e->getMessage());
        informe_general_fin(500, 'No se pudo generar el informe. Intente de nuevo.');
    }
    if ($titulo === '') { $titulo = 'Informe general'; }

    $tz = new \DateTimeZone('America/Bogota');
    $ahora = new \DateTime('now', $tz);
    $quien = '';
    try {
        $u = $con->obnerFila($con->consultar('SELECT usu_Nombres, usu_Apellidos FROM conf_usuarios WHERE usu_Id = ?', [(int) $ctx['usuario']]));
        $quien = trim(preg_replace('/\s+/', ' ', ($u['usu_Nombres'] ?? '') . ' ' . ($u['usu_Apellidos'] ?? '')));
    } catch (\Throwable $e) { /* el pie sale sin el nombre */ }
    $color = ltrim(defined('MUNICIPIO_COLOR') ? (string) MUNICIPIO_COLOR : '#1fa49d', '#');
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $color)) { $color = '1fa49d'; }

    // Ya se sabe quién es: soltar la sesión para que sus otras pestañas no esperen mientras se arma un
    // informe grande, y darle tiempo y memoria (el Excel se arma en memoria).
    session_write_close();
    @set_time_limit(180);
    $ml = trim((string) ini_get('memory_limit'));
    $mlBytes = (int) $ml;
    switch (strtoupper(substr($ml, -1))) {
        case 'G': $mlBytes *= 1024;
        // no break
        case 'M': $mlBytes *= 1024;
        // no break
        case 'K': $mlBytes *= 1024;
    }
    if ($mlBytes > 0 && $mlBytes < 256 * 1048576) { @ini_set('memory_limit', '256M'); }

    try {
        $bytes = InformeGeneralExcel::construir($con, $validada, [
            'titulo' => $titulo,
            'municipio' => defined('MUNICIPIO_CIUDAD') ? (string) MUNICIPIO_CIUDAD : '',
            'generado' => 'Generado el ' . $ahora->format('d/m/Y \a \l\a\s h:i a') . ($quien !== '' ? ' por ' . $quien : ''),
            'color' => $color, 'avisos' => $avisos, 'opc' => $opc,
        ]);
    } catch (InformeException $e) {
        informe_general_fin($e->permiso ? 403 : 400, $e->getMessage());
    } catch (\Throwable $e) {
        error_log('[informe general excel] ' . $e->getMessage());
        informe_general_fin(500, 'No se pudo generar el informe. Intente de nuevo.');
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . InformeGeneralExcel::nombreArchivo($titulo, $ahora->format('Ymd')) . '"');
    header('Content-Length: ' . strlen($bytes));
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    echo $bytes;
    exit;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    informe_general_run();
}
