<?php
namespace erpsoftsas;

require_once __DIR__ . '/class.informeCatalogo.php';
require_once __DIR__ . '/class.paginacion.php';

/**
 * EL CONSTRUCTOR DEL INFORME GENERAL (informes dinámicos, migración 056).
 *
 * Recibe la configuración que arma la pantalla (o que trae una plantilla) y
 * hace dos cosas, siempre en este orden:
 *
 *   1. VALIDAR contra el catálogo (InformeCatalogo) y contra los permisos de
 *      quien ejecuta. La configuración solo transporta CLAVES del catálogo y
 *      valores: fuente, claves de columna, claves de filtro con su operador y
 *      su valor, claves de orden con su dirección. Una clave que no está en el
 *      catálogo, un operador fuera de la lista del filtro o un valor del tipo
 *      equivocado se rechazan; nada de eso llega nunca al SQL.
 *   2. ARMAR el SQL con las expresiones del catálogo (constantes del servidor).
 *      Todo valor del usuario va como parámetro "?"; lo único que se pega en el
 *      texto SQL son esas constantes y los marcadores. El DAO genérico no se usa
 *      (concatena): va ConexionSQLServer::consultar($sql, $params).
 *
 * La configuración (JSON v1):
 *
 *   { "v": 1,
 *     "modo": "declaracion" | "agrupado" | "contribuyente",
 *     "impuesto": "ica" | "reteica" | "autorreteica",     (en "declaracion" y "agrupado")
 *     "filtros":  [ { "c": "<clave>", "op": "<operador>", "v": <valor> }, ... ],
 *     "columnas": [ { "c": "<clave>", "t": "<título, opcional>" }, ... ],
 *     "orden":    [ { "c": "<clave>", "d": "asc" | "desc" }, ... ] }
 *
 * Además de la configuración, la vista previa (y, si la persona lo pide, el Excel) pasan un buscador de texto
 * libre y el orden de un encabezado: se validan con leerBuscar / ordenDeVista y armar() los aplica en el MISMO
 * SQL que cuenta, suma, pagina y recorre. Toda consulta sobre lo armado empieza por $q['prefijo'] (el WITH del
 * modo agrupado, '' en los demás).
 *
 * Operadores y forma del valor, por tipo de filtro:
 *   entero    in [n, ...]  |  between [desde, hasta]
 *   dinero    between [desde, hasta]  |  >= n  |  <= n
 *   fecha     between ["AAAA-MM-DD", "AAAA-MM-DD"]  |  >= f  |  <= f
 *   lista     in ["codigo", ...]
 *   texto     contiene "texto"  (cada palabra, sin distinguir tildes ni mayúsculas)
 *   booleano  = true | false
 */
class InformeConstructor
{
    /** Filas por lote al recorrer todo el resultado (el Excel); las pruebas lo bajan. */
    public static $filasPorLote = 1000;

    /** Operador => el que se pega al SQL. Una lista cerrada: el operador del usuario nunca se interpola. */
    const COMPARADORES = ['=' => '=', '>=' => '>=', '<=' => '<='];

    /** Tope de filas del Excel; las pruebas lo bajan. */
    public static $maxFilasExcel = InformeCatalogo::MAX_FILAS_EXCEL;
    public static $maxCeldasExcel = InformeCatalogo::MAX_CELDAS_EXCEL;

    /* =====================================================================
     * LEER Y VALIDAR
     * ===================================================================== */

    /** El JSON de la configuración, con tope de tamaño y de profundidad. */
    public static function leerJson($texto)
    {
        $texto = (string) $texto;
        if ($texto === '') { throw new InformeException('Falta la configuración del informe.'); }
        if (strlen($texto) > InformeCatalogo::MAX_JSON) {
            throw new InformeException('La configuración del informe es demasiado grande.');
        }
        $c = json_decode($texto, true, 8);
        if (!is_array($c)) { throw new InformeException('La configuración del informe no es válida.'); }
        return $c;
    }

    /** Una clave recibida, lista para citarla en un mensaje (solo letras, números y _). */
    private static function _ver($c)
    {
        return is_string($c) ? mb_substr(preg_replace('/[^A-Za-z0-9_.-]/', '', $c), 0, 40, 'UTF-8') : '?';
    }

    /**
     * Las fechas y los años relativos de las plantillas del sistema: así
     * "Pagos por banco del mes en curso" sigue sirviendo el mes siguiente.
     */
    private static function _ficha($v)
    {
        if (is_array($v)) {
            return array_map(function ($x) { return self::_ficha($x); }, $v);
        }
        if (!is_string($v) || $v === '' || $v[0] !== '{') { return $v; }
        $hoy = new \DateTime('now', new \DateTimeZone('America/Bogota'));
        switch ($v) {
            case '{hoy}':          return $hoy->format('Y-m-d');
            case '{inicioMes}':    return $hoy->format('Y-m-01');
            case '{inicioAnio}':   return $hoy->format('Y-01-01');
            case '{anioActual}':   return (int) $hoy->format('Y');
            case '{anioAnterior}': return (int) $hoy->format('Y') - 1;
        }
        return $v;
    }

    private static function _titulo($t, $posicion, $estricto)
    {
        if ($t === null || $t === '') { return ''; }
        if (!is_string($t)) {
            if ($estricto) { throw new InformeException('El título de la columna número ' . ($posicion + 1) . ' no es válido.'); }
            return '';
        }
        $limpio = preg_replace('/\s+/u', ' ', $t);
        if ($limpio === null) {   // UTF-8 inválido
            if ($estricto) { throw new InformeException('El título de la columna número ' . ($posicion + 1) . ' no es válido.'); }
            return '';
        }
        $limpio = trim($limpio);
        // Texto plano: sin < ni > (los nombres de rol ya causaron un XSS) ni caracteres de control.
        if (preg_match('/[<>\x00-\x1F\x7F]/', $limpio)) {
            if ($estricto) { throw new InformeException('Los títulos de las columnas no pueden llevar los signos < ni >.'); }
            $limpio = trim(preg_replace('/[<>\x00-\x1F\x7F]/', '', $limpio));
        }
        if (mb_strlen($limpio, 'UTF-8') > InformeCatalogo::MAX_TITULO) {
            if ($estricto) { throw new InformeException('Cada título puede tener hasta ' . InformeCatalogo::MAX_TITULO . ' caracteres.'); }
            $limpio = mb_substr($limpio, 0, InformeCatalogo::MAX_TITULO, 'UTF-8');
        }
        return $limpio;
    }

    private static function _entero($v)
    {
        if (is_int($v)) { $n = $v; }
        elseif (is_string($v) && preg_match('/^-?\d{1,10}$/', $v)) { $n = (int) $v; }
        elseif (is_float($v) && is_finite($v) && floor($v) == $v && abs($v) < 2147483648) { $n = (int) $v; }
        else { return null; }
        return abs($n) <= 2147483647 ? $n : null;
    }

    private static function _numero($v)
    {
        if (is_int($v) || is_float($v)) { $n = (float) $v; }
        elseif (is_string($v) && preg_match('/^-?\d{1,15}(\.\d{1,2})?$/', $v)) { $n = (float) $v; }
        else { return null; }
        return (is_finite($n) && abs($n) < 1e15) ? $n : null;
    }

    private static function _fecha($v)
    {
        if (!is_string($v) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $x)) { return null; }
        if ($x[1] < 1900 || $x[1] > 2100 || !checkdate((int) $x[2], (int) $x[3], (int) $x[1])) { return null; }
        return $v;
    }

    /**
     * El valor de un filtro, validado y normalizado. Lanza InformeException con
     * un mensaje que se le puede mostrar a la persona.
     */
    private static function _valorFiltro($con, array $def, $op, $v)
    {
        $et = $def['etiqueta'];
        $malo = function ($que) use ($et) { return new InformeException('El filtro «' . $et . '»: ' . $que); };
        $v = self::_ficha($v);

        switch ($def['tipo']) {
            case 'booleano':
                if ($v === true || $v === 1 || $v === '1' || $v === 'true') { return true; }
                if ($v === false || $v === 0 || $v === '0' || $v === 'false') { return false; }
                throw $malo('elija Sí o No.');

            case 'texto':
                if (!is_string($v)) { throw $malo('escriba un texto.'); }
                $v = preg_replace('/\s+/u', ' ', $v);
                if ($v === null) { throw $malo('el texto no es válido.'); }
                $v = trim($v);
                if ($v === '' || mb_strlen($v, 'UTF-8') > InformeCatalogo::MAX_TEXTO || preg_match('/[\x00-\x08\x0B-\x1F\x7F]/', $v)) {
                    throw $malo('escriba un texto de hasta ' . InformeCatalogo::MAX_TEXTO . ' caracteres.');
                }
                if (!empty($def['ciiu']) && !preg_match('/^\d{1,6}$/', $v)) { throw $malo('el código CIIU lleva solo números.'); }
                return $v;

            case 'entero':
            case 'dinero':
            case 'fecha':
                $uno = function ($x) use ($def, $malo) {
                    if ($def['tipo'] === 'entero') {
                        $n = self::_entero($x);
                        if ($n === null) { throw $malo('escriba números enteros.'); }
                        if (($def['min'] !== null && $n < $def['min']) || ($def['max'] !== null && $n > $def['max'])) {
                            throw $malo('el valor debe estar entre ' . $def['min'] . ' y ' . $def['max'] . '.');
                        }
                        return $n;
                    }
                    if ($def['tipo'] === 'dinero') {
                        $n = self::_numero($x);
                        if ($n === null) { throw $malo('escriba valores en pesos.'); }
                        return $n;
                    }
                    $f = self::_fecha($x);
                    if ($f === null) { throw $malo('escriba las fechas como día/mes/año.'); }
                    return $f;
                };
                if ($op === 'in') {
                    $lista = is_array($v) ? array_values($v) : [$v];
                    if (!$lista) { throw $malo('elija al menos un valor.'); }
                    if (count($lista) > InformeCatalogo::MAX_VALORES) { throw $malo('admite hasta ' . InformeCatalogo::MAX_VALORES . ' valores.'); }
                    $r = [];
                    foreach ($lista as $x) { $r[] = $uno($x); }
                    return array_values(array_unique($r));
                }
                if ($op === 'between') {
                    if (!is_array($v) || count($v) !== 2) { throw $malo('escriba el valor inicial y el final.'); }
                    $v = array_values($v);
                    $a = $uno($v[0]);
                    $b = $uno($v[1]);
                    if ($a > $b) { throw $malo('el valor inicial es mayor que el final.'); }
                    return [$a, $b];
                }
                if (is_array($v)) { throw $malo('escriba un solo valor.'); }
                return $uno($v);

            case 'lista':
                $lista = is_array($v) ? array_values($v) : [$v];
                if (!$lista) { throw $malo('elija al menos una opción.'); }
                if (count($lista) > InformeCatalogo::MAX_VALORES) { throw $malo('admite hasta ' . InformeCatalogo::MAX_VALORES . ' opciones.'); }
                $r = [];
                foreach ($lista as $x) {
                    if (!is_string($x) && !is_int($x)) { throw $malo('una de las opciones no es válida.'); }
                    if (($def['valor'] ?? 'texto') === 'entero') {
                        $n = self::_entero($x);
                        if ($n === null) { throw $malo('una de las opciones no es válida.'); }
                        $x = $n;
                    } else {
                        $x = (string) $x;
                    }
                    // Las opciones son un catálogo cerrado, salvo las de la base (bancos): ahí basta con un id.
                    if (($def['opcionesFn'] ?? '') === '') {
                        if (!array_key_exists($x, $def['opciones'] ?? [])) { throw $malo('una de las opciones no es válida.'); }
                    } elseif (!is_int($x) || $x <= 0) {
                        throw $malo('una de las opciones no es válida.');
                    }
                    $r[] = $x;
                }
                return array_values(array_unique($r, SORT_REGULAR));
        }
        throw $malo('no se puede usar.');
    }

    /**
     * Valida la configuración contra el catálogo y los permisos de $ctx.
     *
     * $modo:
     *   'estricto'  la configuración llega en línea (la pantalla): lo que no vale se rechaza.
     *   'tolerante' viene de una plantilla guardada: el catálogo cambia con el tiempo, así
     *               que lo que ya no existe (o que esta sesión no puede usar) se quita y
     *               se avisa.
     *   'sistema'   como tolerante, sin avisos: una plantilla del sistema sirve para los
     *               tres impuestos y las columnas que no aplican a uno se quitan sin ruido.
     *
     * @return array ['config' => normalizada, 'fuente' => descriptor, 'clave' => string, 'avisos' => []]
     */
    public static function validar($con, $config, array $ctx, $modo = 'estricto')
    {
        $estricto = $modo === 'estricto';
        $avisos = [];
        $aviso = function ($m) use (&$avisos, $modo) { if ($modo !== 'sistema') { $avisos[] = $m; } };

        if (!is_array($config)) { throw new InformeException('La configuración del informe no es válida.'); }

        $modoInforme = isset($config['modo']) && is_string($config['modo']) ? $config['modo'] : '';
        $impuesto = null;
        if ($modoInforme === 'declaracion' || $modoInforme === 'agrupado') {
            $impuesto = isset($config['impuesto']) && is_string($config['impuesto']) ? $config['impuesto'] : '';
            if (!in_array($impuesto, ['ica', 'reteica', 'autorreteica'], true)) {
                throw new InformeException('Elija el impuesto del informe.');
            }
            // La fuente agrupada es la del mismo impuesto, sumada por contribuyente.
            $clave = $modoInforme === 'agrupado' ? $impuesto . InformeCatalogo::SUFIJO_AGRUPADO : $impuesto;
        } elseif ($modoInforme === 'contribuyente') {
            $clave = 'contribuyentes';
        } else {
            throw new InformeException('Elija qué filas trae el informe.');
        }
        $permisoDe = $impuesto ?: $clave;   // el permiso de la fuente agrupada es el "Ver" de su impuesto

        if (empty($ctx['personalizados'])) {
            throw InformeException::permiso('Su rol no tiene permiso para usar el Informe general. Pídaselo al administrador.');
        }
        if (empty($ctx['fuentes'][$permisoDe])) {
            throw InformeException::permiso('Su rol no tiene permiso para ver «' . InformeCatalogo::ETIQUETA_FUENTE[$permisoDe] . '» en el Informe general. Pídaselo al administrador.');
        }
        $f = InformeCatalogo::fuente($con, $clave);
        if (!$f) { throw new InformeException('Este informe no está disponible en este municipio.'); }

        /* ---- Columnas ---- */
        $cols = isset($config['columnas']) && is_array($config['columnas']) ? array_values($config['columnas']) : [];
        if (count($cols) > InformeCatalogo::MAX_COLUMNAS) {
            if ($estricto) { throw new InformeException('El informe admite hasta ' . InformeCatalogo::MAX_COLUMNAS . ' columnas.'); }
            $cols = array_slice($cols, 0, InformeCatalogo::MAX_COLUMNAS);
            $aviso('Se quitaron las columnas que pasaban de ' . InformeCatalogo::MAX_COLUMNAS . '.');
        }
        $columnas = [];
        $vistas = [];
        foreach ($cols as $i => $item) {
            $c = is_array($item) ? ($item['c'] ?? null) : null;
            if (!is_string($c) || !isset($f['columnas'][$c])) {
                if ($estricto) { throw new InformeException('La columna número ' . ($i + 1) . ' no existe en este informe.'); }
                $aviso('Se quitó una columna que ya no existe en este informe (' . self::_ver($c) . ').');
                continue;
            }
            $def = $f['columnas'][$c];
            if (!InformeCatalogo::columnaPermitida($def, $ctx)) {
                if ($estricto) {
                    throw InformeException::permiso('Su rol no tiene permiso para incluir datos de contacto y de terceros (correo, teléfono, dirección, representante, contador y revisor fiscal). Pídaselo al administrador.');
                }
                $aviso('Se quitó la columna «' . $def['etiqueta'] . '»: su rol no puede incluir datos de contacto.');
                continue;
            }
            if (isset($vistas[$c])) {
                if ($estricto) { throw new InformeException('La columna «' . $def['etiqueta'] . '» está repetida.'); }
                $aviso('Se quitó la columna repetida «' . $def['etiqueta'] . '».');
                continue;
            }
            $vistas[$c] = true;
            $t = self::_titulo(is_array($item) ? ($item['t'] ?? '') : '', $i, $estricto);
            // Un título igual al de siempre no se guarda: así sigue al catálogo si cambia.
            $columnas[] = ($t === '' || $t === $def['etiqueta']) ? ['c' => $c] : ['c' => $c, 't' => $t];
        }

        /* ---- Filtros ---- */
        $fils = isset($config['filtros']) && is_array($config['filtros']) ? array_values($config['filtros']) : [];
        if (count($fils) > InformeCatalogo::MAX_FILTROS) {
            if ($estricto) { throw new InformeException('El informe admite hasta ' . InformeCatalogo::MAX_FILTROS . ' filtros.'); }
            $fils = array_slice($fils, 0, InformeCatalogo::MAX_FILTROS);
            $aviso('Se quitaron los filtros que pasaban de ' . InformeCatalogo::MAX_FILTROS . '.');
        }
        $filtros = [];
        $vistos = [];
        foreach ($fils as $i => $item) {
            $c = is_array($item) ? ($item['c'] ?? null) : null;
            if (!is_string($c) || !isset($f['filtros'][$c])) {
                if ($estricto) { throw new InformeException('El filtro número ' . ($i + 1) . ' no existe en este informe.'); }
                $aviso('Se quitó un filtro que ya no existe en este informe (' . self::_ver($c) . ').');
                continue;
            }
            $def = $f['filtros'][$c];
            if (isset($vistos[$c])) {
                if ($estricto) { throw new InformeException('El filtro «' . $def['etiqueta'] . '» está repetido.'); }
                $aviso('Se quitó el filtro repetido «' . $def['etiqueta'] . '».');
                continue;
            }
            $op = is_array($item) && isset($item['op']) && is_string($item['op']) ? $item['op'] : '';
            try {
                if (!in_array($op, $def['ops'], true)) {
                    throw new InformeException('El filtro «' . $def['etiqueta'] . '» no admite esa condición.');
                }
                $valor = self::_valorFiltro($con, $def, $op, is_array($item) ? ($item['v'] ?? null) : null);
            } catch (InformeException $e) {
                if ($estricto) { throw $e; }
                $aviso('Se quitó un filtro: ' . $e->getMessage());
                continue;
            }
            $vistos[$c] = true;
            $filtros[] = ['c' => $c, 'op' => $op, 'v' => $valor];
        }

        /* ---- Orden ---- */
        $ords = isset($config['orden']) && is_array($config['orden']) ? array_values($config['orden']) : [];
        if (count($ords) > InformeCatalogo::MAX_ORDEN) {
            if ($estricto) { throw new InformeException('El orden admite hasta ' . InformeCatalogo::MAX_ORDEN . ' columnas.'); }
            $ords = array_slice($ords, 0, InformeCatalogo::MAX_ORDEN);
            $aviso('Se quitaron los órdenes que pasaban de ' . InformeCatalogo::MAX_ORDEN . '.');
        }
        $orden = [];
        $ordenados = [];
        foreach ($ords as $i => $item) {
            $c = is_array($item) ? ($item['c'] ?? null) : null;
            // Sin sentido es ascendente; si viene, tiene que ser exactamente asc o desc.
            $sentido = is_array($item) && array_key_exists('d', $item) ? $item['d'] : 'asc';
            $d = is_string($sentido) ? strtolower($sentido) : '?';
            $def = (is_string($c) && isset($f['columnas'][$c])) ? $f['columnas'][$c] : null;
            $problema = null;
            if (!$def || $def['orden'] === false) { $problema = 'no se puede ordenar por la columna número ' . ($i + 1) . '.'; }
            elseif (!InformeCatalogo::columnaPermitida($def, $ctx)) { $problema = 'no puede ordenar por «' . $def['etiqueta'] . '».'; }
            elseif (!in_array($d, ['asc', 'desc'], true)) { $problema = 'el sentido del orden debe ser ascendente o descendente.'; }
            elseif (isset($ordenados[$c])) { $problema = 'la columna «' . $def['etiqueta'] . '» está repetida en el orden.'; }
            if ($problema) {
                if ($estricto) { throw new InformeException(ucfirst($problema)); }
                $aviso('Se quitó un criterio de orden: ' . $problema);
                continue;
            }
            $ordenados[$c] = true;
            $orden[] = ['c' => $c, 'd' => $d];
        }

        return [
            'config' => [
                'v' => InformeCatalogo::VERSION, 'modo' => $modoInforme,
                'impuesto' => $impuesto,
                'filtros' => $filtros, 'columnas' => $columnas, 'orden' => $orden,
            ],
            'fuente' => $f, 'clave' => $clave, 'avisos' => $avisos,
        ];
    }

    /* =====================================================================
     * ARMAR EL SQL
     * ===================================================================== */

    /** Los alias de join que hacen falta, con sus dependencias, en el orden del descriptor. */
    private static function _joins(array $f, array $alias)
    {
        $necesarios = [];
        $pedir = function ($a) use (&$pedir, &$necesarios, $f) {
            // La tabla principal (d o c) no es un join: ya está en el FROM.
            if ($a === $f['alias'] || isset($necesarios[$a])) { return; }
            if (!isset($f['joins'][$a])) { throw new \LogicException("El join «{$a}» no existe en la fuente {$f['clave']}."); }
            $necesarios[$a] = true;
            foreach ($f['joins'][$a]['dep'] as $d) { $pedir($d); }
        };
        foreach ($alias as $a) { $pedir($a); }
        $sql = [];
        foreach ($f['joins'] as $a => $j) { if (isset($necesarios[$a])) { $sql[] = $j['sql']; } }
        return $sql;
    }

    /** La condición SQL de un filtro ya validado: [texto con "?", parámetros]. */
    private static function _condicion(array $def, $op, $valor)
    {
        $s = $def['sql'];
        $ph = function ($n) { return implode(', ', array_fill(0, $n, '?')); };

        // Régimen: viene como una lista de códigos separados por coma en una sola columna.
        if (!empty($def['csv'])) {
            $partes = [];
            $ps = [];
            foreach ($valor as $cod) {
                $partes[] = "(',' + ISNULL($s, '') + ',') LIKE ?";
                $ps[] = '%,' . $cod . ',%';
            }
            return ['(' . implode(' OR ', $partes) . ')', $ps];
        }
        // El código CIIU puede estar en cualquiera de las tres casillas del RUT.
        if (!empty($def['ciiu'])) {
            $partes = [];
            $ps = [];
            foreach ($def['ciiu'] as $campo) { $partes[] = "c.$campo = ?"; $ps[] = $valor; }
            return ['(' . implode(' OR ', $partes) . ')', $ps];
        }

        switch ($def['tipo']) {
            case 'booleano':
                return ["($s) = ?", [$valor ? 1 : 0]];

            case 'texto':
                // Cada palabra debe aparecer, sin distinguir tildes ni mayúsculas. %, _ y [ del usuario
                // se buscan como texto, no como comodines. Latin1_General_CI_AI y no la de la base:
                // esa distingue tildes y trata la ñ como otra letra.
                $partes = [];
                $ps = [];
                foreach (preg_split('/\s+/u', $valor, -1, PREG_SPLIT_NO_EMPTY) as $palabra) {
                    $partes[] = "($s) COLLATE Latin1_General_CI_AI LIKE ?";
                    $ps[] = '%' . strtr($palabra, ['[' => '[[]', '%' => '[%]', '_' => '[_]']) . '%';
                }
                return ['(' . implode(' AND ', $partes) . ')', $ps];

            case 'lista':
                return ["($s) IN (" . $ph(count($valor)) . ')', $valor];

            default: // entero, dinero, fecha
                if ($op === 'in') { return ["($s) IN (" . $ph(count($valor)) . ')', $valor]; }
                if ($op === 'between') { return ["($s) BETWEEN ? AND ?", [$valor[0], $valor[1]]]; }
                if (!isset(self::COMPARADORES[$op])) {
                    throw new \LogicException('Operador sin comparador en el constructor: ' . $op);
                }
                return ["($s) " . self::COMPARADORES[$op] . ' ?', [$valor]];
        }
    }

    private static function _select(array $def)
    {
        // El driver entrega las fechas como objetos DateTime, que json_encode rompe: salen como texto ISO.
        return $def['tipo'] === 'fecha' ? 'CONVERT(VARCHAR(10), ' . $def['sql'] . ', 120)' : $def['sql'];
    }

    /* ---------------------------------------------------------------------
     * El buscador y el orden de la vista previa
     * --------------------------------------------------------------------- */

    /**
     * El texto del buscador, limpio. '' si no hay. Cada palabra se busca por
     * separado (y todas deben aparecer); un texto demasiado largo se rechaza en
     * vez de cortarlo, para que la persona sepa qué se está buscando.
     */
    public static function leerBuscar($texto)
    {
        if ($texto === null || $texto === '') { return ''; }
        if (!is_string($texto)) { throw new InformeException('El texto de búsqueda no es válido.'); }
        $t = preg_replace('/\s+/u', ' ', $texto);
        if ($t === null || preg_match('/[\x00-\x08\x0B-\x1F\x7F]/', $t)) { throw new InformeException('El texto de búsqueda no es válido.'); }
        $t = trim($t);
        if (mb_strlen($t, 'UTF-8') > InformeCatalogo::MAX_BUSQUEDA) {
            throw new InformeException('La búsqueda admite hasta ' . InformeCatalogo::MAX_BUSQUEDA . ' caracteres.');
        }
        if (count(preg_split('/\s+/u', $t, -1, PREG_SPLIT_NO_EMPTY)) > InformeCatalogo::MAX_PALABRAS) {
            throw new InformeException('La búsqueda admite hasta ' . InformeCatalogo::MAX_PALABRAS . ' palabras.');
        }
        return $t;
    }

    /**
     * El orden pedido desde los encabezados de la vista previa: una columna DEL INFORME
     * (no cualquiera del catálogo) que se pueda ordenar, y su sentido. Reemplaza al
     * orden de la configuración mientras la persona lo tenga elegido. null si no pidió.
     */
    public static function ordenDeVista(array $validada, $c, $d = 'asc')
    {
        if ($c === null || $c === '') { return null; }
        $sentido = is_string($d) ? strtolower($d) : '?';
        if ($sentido === '') { $sentido = 'asc'; }
        if (!is_string($c) || !in_array($sentido, ['asc', 'desc'], true)) {
            throw new InformeException('El orden pedido no es válido.');
        }
        $enInforme = false;
        foreach ($validada['config']['columnas'] as $col) { if ($col['c'] === $c) { $enInforme = true; } }
        if (!$enInforme || !isset($validada['fuente']['columnas'][$c])) {
            throw new InformeException('Solo se puede ordenar por una de las columnas del informe.');
        }
        $def = $validada['fuente']['columnas'][$c];
        if ($def['orden'] === false) {
            throw new InformeException('No se puede ordenar por «' . $def['etiqueta'] . '».');
        }
        return ['c' => $c, 'd' => $sentido];
    }

    /**
     * Búsqueda y orden de la vista previa tal como llegan (textos del navegador), validados:
     * ['buscar' => '...', 'orden' => ['c', 'd']], sin las claves que no se pidieron.
     */
    public static function opcionesDeVista(array $validada, $buscar, $ordenC, $ordenD)
    {
        $opc = [];
        $b = self::leerBuscar($buscar);
        if ($b !== '') { $opc['buscar'] = $b; }
        $o = self::ordenDeVista($validada, $ordenC, $ordenD === '' || $ordenD === null ? 'asc' : $ordenD);
        if ($o) { $opc['orden'] = $o; }
        return $opc;
    }

    /** La condición del buscador: [texto SQL con "?", parámetros, alias de join] o null si no hay búsqueda. */
    private static function _busqueda(array $f, $texto)
    {
        if ($texto === '' || empty($f['buscar'])) { return null; }
        $partes = [];
        $params = [];
        $alias = [];
        foreach (preg_split('/\s+/u', $texto, -1, PREG_SPLIT_NO_EMPTY) as $palabra) {
            // Un documento o un número: "1.052.400.237" y "900123456-7" se buscan por sus dígitos (sin el DV).
            $digitos = null;
            if (preg_match('/^[\d.\-]+$/', $palabra)) {
                $digitos = preg_replace('/\D/', '', explode('-', $palabra)[0]);
                if ($digitos === '') { $digitos = null; }
            }
            $o = [];
            foreach ($f['buscar'] as $b) {
                if ($b['num']) {
                    if ($digitos === null) { continue; }
                    $o[] = '(' . $b['sql'] . ') LIKE ?';
                    $params[] = '%' . $digitos . '%';
                } else {
                    // %, _ y [ del usuario se buscan como texto, sin tildes ni mayúsculas (como el filtro "contiene").
                    $o[] = '(' . $b['sql'] . ') COLLATE Latin1_General_CI_AI LIKE ?';
                    $params[] = '%' . strtr($palabra, ['[' => '[[]', '%' => '[%]', '_' => '[_]']) . '%';
                }
                foreach ($b['joins'] as $a) { $alias[] = $a; }
            }
            $partes[] = '(' . implode(' OR ', $o) . ')';
        }
        return $partes ? [implode(' AND ', $partes), $params, $alias] : null;
    }

    /**
     * Arma la consulta de una configuración YA validada (la salida de validar).
     *
     * $opc (opcional, ya validado con leerBuscar y ordenDeVista; el Excel puede pasarlo igual que la vista previa):
     *   buscar  texto libre: documento, nombre y número de declaración
     *   orden   ['c' => clave, 'd' => 'asc'|'desc']: manda sobre el orden de la configuración
     *
     * @return array select, desde (FROM + joins + WHERE), params, orden, columnas (meta), fuente, config, prefijo.
     *               TODA consulta sobre esto se escribe como prefijo + "SELECT ..." (el prefijo es un WITH en el
     *               modo agrupado y '' en los demás).
     */
    public static function armar(array $validada, array $opc = [])
    {
        $f = $validada['fuente'];
        $cfg = $validada['config'];
        if (!$cfg['columnas']) { throw new InformeException('Elija al menos una columna para el informe.'); }
        $ordenCfg = !empty($opc['orden']) ? [$opc['orden']] : $cfg['orden'];
        $buscar = (string) ($opc['buscar'] ?? '');
        if (!empty($f['agrupa'])) { return self::_armarAgrupado($validada, $ordenCfg, $buscar); }

        $select = [];
        $meta = [];
        $alias = [];
        foreach ($cfg['columnas'] as $i => $col) {
            $def = $f['columnas'][$col['c']];
            $select[] = self::_select($def) . ' AS c' . $i;
            $meta[] = ['c' => $col['c'], 'titulo' => $col['t'] ?? $def['etiqueta'], 'tipo' => $def['tipo'], 'def' => $def];
            foreach ($def['joins'] as $a) { $alias[] = $a; }
        }

        $where = [];
        $params = [];
        $porEstado = false;
        foreach ($cfg['filtros'] as $fil) {
            $def = $f['filtros'][$fil['c']];
            foreach ($def['joins'] as $a) { $alias[] = $a; }
            [$sql, $ps] = self::_condicion($def, $fil['op'], $fil['v']);
            $where[] = $sql;
            foreach ($ps as $p) { $params[] = $p; }
            if ($f['baseSalvoFiltro'] === $fil['c']) { $porEstado = true; }
        }
        if ($f['base'] && !$porEstado) { array_unshift($where, $f['base']); }
        $b = self::_busqueda($f, $buscar);
        if ($b) {
            $where[] = $b[0];
            foreach ($b[1] as $p) { $params[] = $p; }
            foreach ($b[2] as $a) { $alias[] = $a; }
        }

        $orden = [];
        foreach ($ordenCfg as $o) {
            $def = $f['columnas'][$o['c']];
            foreach ($def['joins'] as $a) { $alias[] = $a; }
            $orden[] = '(' . $def['orden'] . ') ' . ($o['d'] === 'desc' ? 'DESC' : 'ASC');
        }
        // La llave al final: con ella el orden es total y una fila no sale en dos páginas ni en ninguna.
        $orden[] = $f['pk'] . ' ASC';

        $desde = $f['desde'] . ' ' . implode(' ', self::_joins($f, $alias));
        if ($where) { $desde .= ' WHERE ' . implode(' AND ', $where); }

        return [
            'prefijo' => '', 'select' => implode(', ', $select), 'desde' => $desde, 'params' => $params,
            'orden' => implode(', ', $orden), 'columnas' => $meta, 'fuente' => $f, 'config' => $cfg,
        ];
    }

    /**
     * La consulta del modo "una fila por contribuyente (declaraciones agrupadas)".
     *
     *   WITH y AS (SELECT idc, anio, <sumas de ese año>
     *                FROM <tabla> d <joins> WHERE <vigentes y filtros de la declaración> GROUP BY idc, anio)
     *   SELECT <columnas> FROM ind_contribuyentes c
     *     JOIN (SELECT y.idc, <sumas, años, último año pagado> FROM y GROUP BY y.idc) g ON g.idc = c.ind_Id
     *    WHERE <filtros del contribuyente y buscador>
     *
     * Lo único que se pega al texto son expresiones del catálogo y alias fijos (y, g, a_<clave>); todo
     * valor del usuario va en parámetros. Los parámetros del WITH son los primeros porque el texto empieza ahí.
     */
    private static function _armarAgrupado(array $validada, array $ordenCfg, $buscar)
    {
        $f = $validada['fuente'];
        $cfg = $validada['config'];
        $a = $f['agrupa'];
        $d = $a['fuente'];

        $select = [];
        $meta = [];
        $aliasC = [];       // joins del contribuyente (fuera)
        $aggs = [];         // columnas calculadas que hay que llevar hasta g
        foreach ($cfg['columnas'] as $i => $col) {
            $def = $f['columnas'][$col['c']];
            $select[] = self::_select($def) . ' AS c' . $i;
            $meta[] = ['c' => $col['c'], 'titulo' => $col['t'] ?? $def['etiqueta'], 'tipo' => $def['tipo'], 'def' => $def];
            foreach ($def['joins'] as $j) { $aliasC[] = $j; }
            if (!empty($def['agg'])) { $aggs[$col['c']] = $def; }
        }
        foreach ($ordenCfg as $o) {
            $def = $f['columnas'][$o['c']];
            foreach ($def['joins'] as $j) { $aliasC[] = $j; }
            if (!empty($def['agg'])) { $aggs[$o['c']] = $def; }
        }

        /* Filtros: los de la declaración dentro de y; los del contribuyente fuera */
        $whereD = [$a['estadoCol'] . ' = 2', '(' . $a['corregida'] . ') = 0'];   // vigentes
        $paramsD = [];
        $aliasD = [];
        $whereC = [];
        $paramsC = [];
        foreach ($cfg['filtros'] as $fil) {
            $def = $f['filtros'][$fil['c']];
            [$sql, $ps] = self::_condicion($def, $fil['op'], $fil['v']);
            if (($def['nivel'] ?? 'c') === 'd') {
                $whereD[] = $sql;
                foreach ($ps as $p) { $paramsD[] = $p; }
                foreach ($def['joins'] as $j) { $aliasD[] = $j; }
            } else {
                $whereC[] = $sql;
                foreach ($ps as $p) { $paramsC[] = $p; }
                foreach ($def['joins'] as $j) { $aliasC[] = $j; }
            }
        }
        $b = self::_busqueda($f, $buscar);
        if ($b) {
            $whereC[] = $b[0];
            foreach ($b[1] as $p) { $paramsC[] = $p; }
            foreach ($b[2] as $j) { $aliasC[] = $j; }
        }

        /* y: una fila por contribuyente y año. g: una fila por contribuyente. */
        $dentro = [$a['idc'] . ' AS idc', $a['anio'] . ' AS anio'];
        $fuera = [];
        foreach ($aggs as $k => $def) {
            switch ($def['agg']) {
                case 'n':
                    $dentro[] = 'COUNT(*) AS v_n';
                    $fuera[] = 'SUM(y.v_n) AS a_' . $k;
                    break;
                case 'suma':
                    $dentro[] = 'SUM(' . $def['dsql'] . ') AS v_' . $k;
                    $fuera[] = 'SUM(y.v_' . $k . ') AS a_' . $k;
                    foreach ($def['dj'] as $j) { $aliasD[] = $j; }
                    break;
                case 'anios':
                    $fuera[] = "STUFF((SELECT ', ' + CAST(y2.anio AS VARCHAR(4)) FROM y y2 WHERE y2.idc = y.idc ORDER BY y2.anio FOR XML PATH('')), 1, 2, '') AS a_" . $k;
                    break;
                case 'ultimoPagado':
                    $dentro[] = 'SUM(CASE WHEN ' . $a['pagada'] . ' = 1 THEN 1 ELSE 0 END) AS v_pagadas';
                    $fuera[] = 'MAX(CASE WHEN y.v_pagadas > 0 THEN y.anio END) AS a_' . $k;
                    break;
                default:
                    throw new \LogicException('Agregado desconocido en el catálogo: ' . $def['agg']);
            }
        }
        $cte = 'WITH y AS (SELECT ' . implode(', ', $dentro) . ' ' . $d['desde'] . ' ' . implode(' ', self::_joins($d, $aliasD))
             . ' WHERE ' . implode(' AND ', $whereD) . ' GROUP BY ' . $a['idc'] . ', ' . $a['anio'] . ')';
        $g = '(SELECT y.idc AS idc' . ($fuera ? ', ' . implode(', ', $fuera) : '') . ' FROM y GROUP BY y.idc) g';

        $orden = [];
        foreach ($ordenCfg as $o) {
            $orden[] = '(' . $f['columnas'][$o['c']]['orden'] . ') ' . ($o['d'] === 'desc' ? 'DESC' : 'ASC');
        }
        $orden[] = $f['pk'] . ' ASC';

        $desde = $f['desde'] . ' INNER JOIN ' . $g . ' ON g.idc = c.ind_Id ' . implode(' ', self::_joins($f, $aliasC));
        if ($whereC) { $desde .= ' WHERE ' . implode(' AND ', $whereC); }

        return [
            'prefijo' => $cte, 'select' => implode(', ', $select), 'desde' => $desde,
            'params' => array_merge($paramsD, $paramsC),
            'orden' => implode(', ', $orden), 'columnas' => $meta, 'fuente' => $f, 'config' => $cfg,
        ];
    }

    /* =====================================================================
     * EJECUTAR
     * ===================================================================== */

    /** Un valor de la base, en la forma que sale a la pantalla y al Excel. */
    private static function _valor(array $def, $v)
    {
        if ($v === null) { return null; }
        switch ($def['tipo']) {
            case 'dinero':  return (int) round((float) $v);
            case 'entero':
            case 'anio':    return (int) $v;
            case 'fecha':   return (is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) ? $v : null;
            case 'booleano': return ((int) $v) === 1 ? 'Sí' : 'No';
            case 'lista':
                $o = $def['opciones'] ?: [];
                $k = (string) $v;
                if (array_key_exists($k, $o)) { return (string) $o[$k]; }
                // Un medio manual que el catálogo no conoce se muestra como lo que es: un pago manual.
                if (strpos($k, 'MANUAL:') === 0) { return 'Manual'; }
                return $k === '' ? null : $k;
            case 'multilista':
                $o = $def['opciones'] ?: [];
                $r = [];
                foreach (explode(',', (string) $v) as $cod) {
                    $cod = trim($cod);
                    if ($cod !== '') { $r[] = isset($o[$cod]) ? $o[$cod] : $cod; }
                }
                return $r ? implode(', ', $r) : null;
            default:
                $t = trim((string) $v);
                return $t === '' ? null : $t;
        }
    }

    /** Una fila de la base, en el orden de las columnas elegidas. */
    public static function formatear(array $q, array $fila)
    {
        $r = [];
        foreach ($q['columnas'] as $i => $m) { $r[] = self::_valor($m['def'], $fila['c' . $i] ?? null); }
        return $r;
    }

    /** Una consulta sobre lo armado: el prefijo (WITH del modo agrupado) y luego el SELECT. */
    private static function _sql(array $q, $select)
    {
        return ($q['prefijo'] !== '' ? $q['prefijo'] . ' ' : '') . $select;
    }

    public static function contar($con, array $q)
    {
        $f = $con->obnerFila($con->consultar(self::_sql($q, 'SELECT COUNT(*) AS n ' . $q['desde']), $q['params']));
        return (int) ($f['n'] ?? 0);
    }

    /** La suma de cada columna en pesos, sobre TODAS las filas del filtro (no solo la página). */
    public static function totales($con, array $q)
    {
        $sumas = [];
        foreach ($q['columnas'] as $i => $m) {
            if (!empty($m['def']['total'])) { $sumas[] = 'SUM(' . $m['def']['sql'] . ') AS t' . $i; }
        }
        if (!$sumas) { return []; }
        $f = $con->obnerFila($con->consultar(self::_sql($q, 'SELECT ' . implode(', ', $sumas) . ' ' . $q['desde']), $q['params']));
        $r = [];
        foreach ($q['columnas'] as $i => $m) {
            if (!empty($m['def']['total'])) { $r[$m['c']] = (int) round((float) ($f['t' . $i] ?? 0)); }
        }
        return $r;
    }

    /**
     * Una página de la vista previa: las filas ya formateadas, el total de filas
     * del filtro y la suma de las columnas en pesos.
     */
    public static function pagina($con, array $q, $pagina, $porPagina)
    {
        // Como Paginacion::consultar, pero con el prefijo (el WITH del modo agrupado) delante de cada SELECT.
        $total = self::contar($con, $q);
        $porPagina = max(1, (int) $porPagina);
        $paginas = max(1, (int) ceil($total / $porPagina));
        // Una página que ya no existe (cambió el filtro o la búsqueda) lleva a la última que tiene filas.
        $pagina = min(max(1, (int) $pagina), $paginas);
        $st = $con->consultar(
            self::_sql($q, 'SELECT ' . $q['select'] . ' ' . $q['desde'] . ' ORDER BY ' . $q['orden'] . ' OFFSET ? ROWS FETCH NEXT ? ROWS ONLY'),
            array_merge($q['params'], [($pagina - 1) * $porPagina, $porPagina])
        );
        $filas = [];
        while ($fila = $con->obnerFila($st)) { $filas[] = self::formatear($q, $fila); }
        return [
            'filas' => $filas, 'total' => $total, 'pagina' => $pagina,
            'paginas' => $paginas, 'porPagina' => $porPagina,
            'totales' => self::totales($con, $q),
        ];
    }

    /**
     * Recorre TODAS las filas por lotes (para el Excel). Cada lote es su propia
     * consulta con OFFSET/FETCH: el resultado completo no cabría en el búfer del
     * driver (32 MB) ni en la memoria de PHP, y así nunca pasa del tamaño del lote.
     */
    public static function recorrer($con, array $q, callable $alFila)
    {
        $tamano = max(1, (int) self::$filasPorLote);
        $desplazamiento = 0;
        do {
            $st = $con->consultar(
                self::_sql($q, 'SELECT ' . $q['select'] . ' ' . $q['desde'] . ' ORDER BY ' . $q['orden'] . ' OFFSET ? ROWS FETCH NEXT ? ROWS ONLY'),
                array_merge($q['params'], [$desplazamiento, $tamano])
            );
            $n = 0;
            while ($fila = $con->obnerFila($st)) {
                $n++;
                $alFila(self::formatear($q, $fila));
            }
            $st = null;
            $desplazamiento += $tamano;
        } while ($n === $tamano);
    }

    /* =====================================================================
     * DESCRIBIR (el encabezado del Excel y de la vista previa)
     * ===================================================================== */

    private static function _dmy($f) { return substr($f, 8, 2) . '/' . substr($f, 5, 2) . '/' . substr($f, 0, 4); }

    private static function _pesos($n) { return '$ ' . number_format($n, 0, ',', '.'); }

    /** "Estado: Pagada · Año: 2026 · Fecha de pago: del 01/01/2026 al 31/03/2026" */
    public static function describirFiltros($con, array $validada)
    {
        $f = $validada['fuente'];
        $partes = [];
        $porEstado = false;
        foreach ($validada['config']['filtros'] as $fil) {
            $def = $f['filtros'][$fil['c']];
            $v = $fil['v'];
            $op = $fil['op'];
            if ($fil['c'] === $f['baseSalvoFiltro']) { $porEstado = true; }
            $t = '';
            switch ($def['tipo']) {
                case 'booleano': $t = $v ? 'Sí' : 'No'; break;
                case 'texto':    $t = 'contiene ' . $v; break;
                case 'lista':
                    $o = InformeCatalogo::opcionesFiltro($con, $def);
                    $t = implode(', ', array_map(function ($x) use ($o) { return isset($o[$x]) ? $o[$x] : (string) $x; }, $v));
                    break;
                case 'entero':
                    $t = $op === 'between' ? 'de ' . $v[0] . ' a ' . $v[1] : implode(', ', $v);
                    break;
                case 'dinero':
                    $t = $op === 'between' ? 'de ' . self::_pesos($v[0]) . ' a ' . self::_pesos($v[1])
                       : ($op === '>=' ? 'desde ' . self::_pesos($v) : 'hasta ' . self::_pesos($v));
                    break;
                case 'fecha':
                    $t = $op === 'between' ? 'del ' . self::_dmy($v[0]) . ' al ' . self::_dmy($v[1])
                       : ($op === '>=' ? 'desde el ' . self::_dmy($v) : 'hasta el ' . self::_dmy($v));
                    break;
            }
            $partes[] = $def['etiqueta'] . ': ' . $t;
        }
        if ($f['base'] && !$porEstado) { array_unshift($partes, 'Estado: presentadas'); }
        if (!empty($f['baseTexto'])) { array_unshift($partes, $f['baseTexto']); }
        return $partes ? implode(' · ', $partes) : 'Sin filtros';
    }

    /**
     * Lo que se le dice a la persona sobre qué trae el informe: los filtros y, si los usó, el buscador y el
     * orden de la vista previa ("Filtros · Búsqueda: «pérez» · Orden: Total a pagar, de mayor a menor").
     * Va en el encabezado del Excel: quien lo lea debe saber por qué faltan filas.
     */
    public static function describirConsulta($con, array $validada, array $opc = [])
    {
        $t = self::describirFiltros($con, $validada);
        if (!empty($opc['buscar'])) { $t .= ' · Búsqueda: «' . $opc['buscar'] . '»'; }
        if (!empty($opc['orden'])) {
            $def = $validada['fuente']['columnas'][$opc['orden']['c']];
            $t .= ' · Orden: ' . $def['etiqueta'] . ($opc['orden']['d'] === 'desc' ? ' (descendente)' : ' (ascendente)');
        }
        return $t;
    }
}
