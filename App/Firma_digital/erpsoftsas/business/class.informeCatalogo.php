<?php
namespace erpsoftsas;

require_once __DIR__ . '/class.pseModulo.php';
require_once __DIR__ . '/class.informePagos.php';
require_once __DIR__ . '/class.permisosRol.php';

/**
 * Un error del Informe general cuyo mensaje SÍ se le puede mostrar a la persona
 * (una configuración que no vale, un permiso que falta, un tope superado).
 * Cualquier otra excepción es un fallo del servidor: se registra y a la
 * pantalla solo llega un mensaje genérico.
 */
class InformeException extends \Exception
{
    /** true cuando el motivo es un permiso que falta (la respuesta lleva sinPermiso). */
    public $permiso = false;

    public static function permiso($mensaje)
    {
        $e = new self($mensaje);
        $e->permiso = true;
        return $e;
    }
}

/**
 * EL CATÁLOGO DEL INFORME GENERAL (informes dinámicos, migración 056).
 *
 * Es la LISTA BLANCA de todo lo que el navegador puede pedir: fuentes, columnas,
 * filtros y órdenes. Cada columna y cada filtro lleva aquí, como constante, la
 * expresión SQL que lo calcula. El navegador solo manda CLAVES de este catálogo
 * y valores; nunca un nombre de tabla, de columna ni un trozo de SQL. Por eso
 * armar la consulta con estas expresiones no abre una inyección (el repo ya
 * sufrió una real, con el DAO que concatena) y por eso tampoco se descubre el
 * esquema con INFORMATION_SCHEMA: eso expondría tablas de respaldo (bkp053_*),
 * conf_parametros (llaves de la pasarela) y conf_usuarios (clave cifrada).
 *
 * LAS FUENTES
 *   ica | reteica | autorreteica   una fila por declaración
 *   contribuyentes                 una fila por contribuyente (el RIT)
 *   ica:agrup | reteica:agrup | autorreteica:agrup
 *                                  una fila por contribuyente con las declaraciones vigentes de ese
 *                                  impuesto sumadas (usa el "Ver" del impuesto; ver _agrupado)
 *
 * TRES REGLAS QUE NO SE PUEDEN ROMPER
 *
 * 1. Las reglas de cartera son las del Informe rápido (InformePagos::filas), que
 *    Paipa ya usa y reconcilia: "pagada" no cuenta un pago con llaves de PRUEBAS
 *    de Wompi; "sin pagar" es lo que tiene valor, no está pagada y no tiene una
 *    corrección presentada encima; la fecha de presentación es la del pago
 *    manual (049) si existe y si no la de la plataforma; los intereses son lo
 *    pagado menos lo que había que pagar y nunca negativos. Esa clase no se
 *    toca (el Informe rápido sigue intacto), así que las expresiones se repiten
 *    aquí y la prueba de oráculo (pruebas/probar_informes_general.php) compara
 *    fila por fila las plantillas "Todas", "Sin pagar" y "Pagadas" contra
 *    InformePagos::filas: si alguien cambia una regla en un sitio y no en el
 *    otro, esa prueba lo dice.
 * 2. Lo que depende de una migración (044 pagos manuales, 045 banco, 049 fecha
 *    de presentación del pago manual, 051 cuenta contable, 038 sanción) o de
 *    una columna que ninguna migración crea (dec_FechaPresentacion,
 *    dec_CapacidadInstalada, dec_ValorImpuesto) se OCULTA si en esa base no
 *    está: el despliegue del código y el de la migración no son simultáneos y
 *    cada municipio lleva las suyas. Una columna que falta no puede dar un 500.
 * 3. Los datos de contacto y de terceros (correo, teléfono, dirección,
 *    representante, contador, revisor) son datos personales (Ley 1581 de 2012)
 *    y un informe masivo es una superficie nueva: solo con el permiso
 *    alcaldia.informes.contacto. Hoy el listado de la Alcaldía no los muestra.
 *
 * Cada columna del catálogo:
 *   etiqueta, grupo, tipo (texto | codigo | entero | anio | dinero | fecha |
 *   lista | multilista | booleano), sql (expresión SQL; en las fechas, de tipo
 *   DATE), orden (expresión para ORDER BY, o false si no se ordena), joins
 *   (alias que necesita), sens (1 = dato personal), total (se suma en el Excel
 *   y en la vista previa), opciones (código => rótulo, en las listas), ancho y
 *   alinea (para el Excel).
 *
 *   "anio" es un entero que NO lleva separador de miles (2026, no 2.026): se
 *   muestra y se escribe en el Excel tal cual. Los demás enteros sí lo llevan.
 */
class InformeCatalogo
{
    const VERSION = 1;

    /** Fuentes, en el orden en que se ofrecen. */
    const CLAVES = ['ica', 'reteica', 'autorreteica', 'contribuyentes'];

    /** Lo que hay que poder ver para usar cada fuente (además del permiso del informe). */
    const PERMISO_FUENTE = [
        'ica'            => 'ica.ver',
        'reteica'        => 'reteica.ver',
        'autorreteica'   => 'autorreteica.ver',
        'contribuyentes' => 'alcaldia.contribuyentes.ver',
    ];

    const ETIQUETA_FUENTE = [
        'ica'            => 'Industria y Comercio',
        'reteica'        => 'Retención de Industria y Comercio',
        'autorreteica'   => 'Autorretención de Industria y Comercio',
        'contribuyentes' => 'Contribuyentes (RIT)',
    ];

    const MODOS = [
        'declaracion' => [
            'etiqueta'    => 'Una fila por declaración',
            'descripcion' => 'Cada declaración (o período, en retención y autorretención) en una fila. Para seguimiento de presentación, pago y cartera.',
        ],
        'contribuyente' => [
            'etiqueta'    => 'Solo contribuyentes (RIT)',
            'descripcion' => 'Un contribuyente por fila, con los datos de su RIT y sin declaraciones.',
        ],
        'agrupado' => [
            'etiqueta'    => 'Una fila por contribuyente (declaraciones agrupadas)',
            'descripcion' => 'Un contribuyente por fila con la suma de sus declaraciones vigentes del impuesto elegido (las originales ya corregidas no se suman), cuántas son, los años presentados y el último año pagado.',
        ],
    ];

    /**
     * Sufijo de la fuente "agrupada" de un impuesto: ica:agrup es "una fila por contribuyente" con las
     * declaraciones de ICA sumadas. No es una fuente más con su permiso: usa el "Ver" del impuesto.
     */
    const SUFIJO_AGRUPADO = ':agrup';

    /* Topes: la configuración llega del navegador, así que se acota. */
    const MAX_COLUMNAS = 60;
    const MAX_FILTROS  = 20;
    const MAX_ORDEN    = 3;
    const MAX_VALORES  = 50;      // elementos de un "in"
    const MAX_TITULO   = 60;
    const MAX_TEXTO    = 100;     // texto de un filtro "contiene"
    const MAX_JSON     = 20000;   // bytes de la configuración
    const MAX_BUSQUEDA = 60;      // texto del buscador de la vista previa
    const MAX_PALABRAS = 5;       // palabras del buscador (cada una se busca por separado)

    /** Tope de filas y de celdas del Excel: se arma en memoria (128 MB de PHP). */
    const MAX_FILAS_EXCEL  = 50000;
    const MAX_CELDAS_EXCEL = 200000;

    /** Estados de una declaración, en el orden en que se ofrecen. */
    const ESTADOS = [
        'pagada'    => 'Pagada',
        'sinpagar'  => 'Sin pagar',
        'corregida' => 'Corregida',
        'sinvalor'  => 'Sin valor',
        'borrador'  => 'Borrador',
    ];

    /** Los estados que cuentan como "presentada" (lo que se trae si no se filtra por estado). */
    const ESTADOS_PRESENTADA = ['pagada', 'sinpagar', 'corregida', 'sinvalor'];

    const TIPOS_DOCUMENTO = ['1' => 'C.C.', '3' => 'C.E.', '4' => 'Pasaporte', '5' => 'NIT'];
    const TIPOS_PERSONA   = ['1' => 'Natural', '2' => 'Jurídica'];
    const REGIMENES = [
        'ORDINARIO'   => 'Ordinario',
        'SIMPLE'      => 'Simple',
        'ESPECIAL'    => 'Especial',
        'RESP_IVA'    => 'Responsable de IVA',
        'NO_RESP_IVA' => 'No responsable de IVA',
    ];
    const RESPONSABILIDADES = [
        'AGENTE_RETENCION'  => 'Agente de retención',
        'AUTORRETENEDOR'    => 'Autorretenedor',
        'INFORMANTE_EXOGENA' => 'Informante de exógena',
    ];
    const CAUSALES_CESE = ['1' => 'Fusión', '2' => 'Escisión', '3' => 'Liquidación', '4' => 'Otro'];

    private static $hay = [];
    private static $fuentes = [];

    /** 'ica:agrup' -> 'ica' (el impuesto cuyo permiso manda); lo demás queda igual. */
    public static function claveBase($clave)
    {
        $clave = (string) $clave;
        return self::esAgrupada($clave) ? substr($clave, 0, -strlen(self::SUFIJO_AGRUPADO)) : $clave;
    }

    public static function esAgrupada($clave)
    {
        $s = self::SUFIJO_AGRUPADO;
        $clave = (string) $clave;
        return strlen($clave) > strlen($s) && substr($clave, -strlen($s)) === $s
            && in_array(substr($clave, 0, -strlen($s)), ['ica', 'reteica', 'autorreteica'], true);
    }

    /** Olvida lo detectado y lo armado (pruebas, o tras cambiar el esquema en la misma petición). */
    public static function olvidar()
    {
        self::$hay = [];
        self::$fuentes = [];
    }

    /* =====================================================================
     * QUÉ HAY EN ESTA BASE
     * ===================================================================== */

    /**
     * ¿Existe esto en la base? 'T:tabla' o 'C:tabla.columna'. Se pregunta una
     * vez por petición y un fallo cuenta como "no hay" (se oculta, no se cae).
     */
    public static function hay($con, $req)
    {
        if (!array_key_exists($req, self::$hay)) {
            self::_precargar($con, [$req]);
        }
        return self::$hay[$req];
    }

    /** Resuelve varias de una sola consulta. */
    private static function _precargar($con, array $reqs)
    {
        $reqs = array_values(array_filter(array_unique($reqs), function ($r) {
            return !array_key_exists($r, self::$hay);
        }));
        if (!$reqs) { return; }
        $exprs = [];
        $params = [];
        $validos = [];
        foreach ($reqs as $i => $r) {
            $tipo = substr($r, 0, 2);
            $nombre = substr($r, 2);
            if ($tipo === 'T:' && preg_match('/^[A-Za-z0-9_]+$/', $nombre)) {
                $exprs[] = "OBJECT_ID(?, 'U') AS r$i";
                $params[] = 'dbo.' . $nombre;
                $validos[$i] = $r;
            } elseif ($tipo === 'C:' && preg_match('/^([A-Za-z0-9_]+)\.([A-Za-z0-9_]+)$/', $nombre, $x)) {
                $exprs[] = "COL_LENGTH(?, ?) AS r$i";
                $params[] = 'dbo.' . $x[1];
                $params[] = $x[2];
                $validos[$i] = $r;
            } else {
                self::$hay[$r] = false;
            }
        }
        if (!$validos) { return; }
        try {
            $f = $con->obnerFila($con->consultar('SELECT ' . implode(', ', $exprs), $params));
            foreach ($validos as $i => $r) { self::$hay[$r] = !empty($f["r$i"]); }
        } catch (\Throwable $e) {
            error_log('[informes] no se pudo revisar el esquema: ' . $e->getMessage());
            foreach ($validos as $r) { self::$hay[$r] = false; }
        }
    }

    /* =====================================================================
     * QUIÉN PUEDE QUÉ
     * ===================================================================== */

    /**
     * Lo que la sesión puede hacer con el informe, leído UNA vez. Todo el motor
     * trabaja con este arreglo y no con PermisosRol: así se puede soltar la
     * sesión (session_write_close) antes de armar un informe grande, y el mismo
     * motor se prueba con cualquier combinación de permisos.
     *
     * Se autoriza en CADA ejecución con los permisos de quien ejecuta, nunca con
     * los de quien guardó una plantilla: una plantilla compartida es el camino
     * clásico para escalar privilegios.
     */
    public static function contexto()
    {
        $ctx = ['usuario' => 0, 'admin' => false, 'personalizados' => false, 'contacto' => false,
                'plantillas' => false, 'fuentes' => []];
        foreach (self::CLAVES as $c) { $ctx['fuentes'][$c] = false; }
        $id = PermisosRol::idUsuario();
        if ($id <= 0) { return $ctx; }
        $ctx['usuario'] = $id;
        $ctx['admin'] = PermisosRol::esAdministrador();
        $ctx['personalizados'] = PermisosRol::tiene('alcaldia.informes.personalizados');
        $ctx['contacto'] = $ctx['personalizados'] && PermisosRol::tiene('alcaldia.informes.contacto');
        $ctx['plantillas'] = $ctx['personalizados'] && PermisosRol::tiene('alcaldia.informes.plantillas');
        foreach (self::PERMISO_FUENTE as $fuente => $permiso) {
            $ctx['fuentes'][$fuente] = $ctx['personalizados'] && PermisosRol::tiene($permiso);
        }
        return $ctx;
    }

    /** El mensaje de "no puede" de cada cosa, con el nombre del catálogo de Roles. */
    public static function mensajePermiso($clave)
    {
        return PermisosRol::mensaje($clave);
    }

    /** ¿La columna es de las que la persona puede incluir? */
    public static function columnaPermitida(array $def, array $ctx)
    {
        return empty($def['sens']) || !empty($ctx['contacto']);
    }

    /* =====================================================================
     * LAS FUENTES
     * ===================================================================== */

    /**
     * El descriptor completo de una fuente EN ESTA BASE (con lo que no existe ya
     * quitado), o null si la fuente no existe o su tabla no está.
     */
    public static function fuente($con, $clave)
    {
        $clave = (string) $clave;
        if (!in_array($clave, self::CLAVES, true) && !self::esAgrupada($clave)) { return null; }
        if (!array_key_exists($clave, self::$fuentes)) {
            // Primera pasada: se arma suponiendo que todo existe, solo para saber
            // QUÉ hay que preguntarle a la base; se pregunta de una vez; segunda
            // pasada: el descriptor real. Así la lista de requisitos no se escribe
            // dos veces.
            $pedidos = [];
            self::_construir($clave, function ($r) use (&$pedidos) { $pedidos[$r] = true; return true; });
            self::_precargar($con, array_keys($pedidos));
            self::$fuentes[$clave] = self::_construir($clave, function ($r) use ($con) { return self::hay($con, $r); });
        }
        return self::$fuentes[$clave];
    }

    private static function _construir($clave, callable $hay)
    {
        if (self::esAgrupada($clave)) { return self::_agrupado(self::claveBase($clave), $hay); }
        return $clave === 'contribuyentes' ? self::_contribuyentes($hay) : self::_declaracion($clave, $hay);
    }

    /** Código => rótulo de los meses o bimestres. */
    private static function _periodos($clave)
    {
        $tabla = $clave === 'autorreteica' ? InformePagos::BIMESTRES : InformePagos::MESES;
        $r = [];
        for ($i = 1; $i < count($tabla); $i++) { $r[$i] = $tabla[$i]; }
        return $r;
    }

    /** El rótulo de un canal de pago, con el medio corto del pago manual (como el Informe rápido). */
    private static function _opcionesCanal()
    {
        $o = [];
        foreach (InformePagos::CANALES as $k => $v) { $o[$k] = $v; }
        $o['OTRO'] = 'Sin dato';
        foreach (InformePagos::MEDIOS_CORTOS as $k => $v) { $o['MANUAL:' . $k] = $v; }
        return $o;
    }

    /** El nombre completo: los cuatro campos de persona natural (en una jurídica, la razón social va en el primero). */
    private static function _sqlNombre()
    {
        $parte = function ($c) { return "ISNULL(NULLIF(LTRIM(RTRIM(c.$c)), '') + ' ', '')"; };
        return 'LTRIM(RTRIM(' . $parte('ind_PrimerNombre') . ' + ' . $parte('ind_SegundoNombre') . ' + '
             . $parte('ind_PrimerApellido') . ' + ' . $parte('ind_SegundoApellido') . '))';
    }

    /** El documento: con dígito de verificación solo un NIT (tipo 5), como InformePagos::documento. */
    private static function _sqlDocumento()
    {
        return "CASE WHEN c.ind_Id IS NULL THEN NULL ELSE CAST(c.ind_NumeroIdentificacion AS VARCHAR(20))"
             . " + CASE WHEN c.ind_IdTipoDocumento = 5 AND c.ind_DV IS NOT NULL THEN '-' + CAST(c.ind_DV AS VARCHAR(5)) ELSE '' END END";
    }

    /**
     * Agrega las columnas de contacto y de terceros (datos personales, sens = 1).
     * Las comparten las declaraciones y los contribuyentes: los datos son los del
     * contribuyente (alias c).
     */
    private static function _contacto(array &$columnas, callable $hay, $grupo)
    {
        $a = function ($clave, $etiqueta, $campo, $sql = null, $ancho = 28) use (&$columnas, $hay, $grupo) {
            if (!$hay('C:ind_contribuyentes.' . $campo)) { return; }
            $columnas[$clave] = [
                'etiqueta' => $etiqueta, 'grupo' => $grupo, 'tipo' => 'texto',
                'sql' => $sql ?: ('c.' . $campo), 'orden' => false, 'joins' => ['c'], 'sens' => 1,
                'total' => false, 'ancho' => $ancho, 'alinea' => 'L',
            ];
        };
        $a('direccion', 'Dirección', 'ind_Direccion', null, 34);
        $a('telefono', 'Teléfono', 'ind_Telefono', 'CAST(c.ind_Telefono AS VARCHAR(30))', 16);
        $a('correo', 'Correo electrónico', 'ind_Email', null, 32);
        $a('representante', 'Representante legal', 'ind_Nombre_representante', null, 30);
        $a('representanteDocumento', 'Documento del representante legal', 'ind_Cedula_representante', null, 18);
        $a('representanteCorreo', 'Correo del representante legal', 'ind_Email_representante', null, 32);
        $a('representanteTelefono', 'Teléfono del representante legal', 'ind_Telefono_representante', null, 16);
        $a('contador', 'Contador', 'ind_NombreContador', null, 30);
        $a('contadorDocumento', 'Documento del contador', 'ind_CedulaContador', null, 18);
        $a('contadorTarjeta', 'Tarjeta profesional del contador', 'ind_TarjetaProfContador', null, 18);
        $a('contadorCorreo', 'Correo del contador', 'ind_EmailContador', null, 32);
        $a('revisor', 'Revisor fiscal', 'ind_NombreRevisor', null, 30);
        $a('revisorDocumento', 'Documento del revisor fiscal', 'ind_CedulaRevisor', null, 18);
        $a('revisorTarjeta', 'Tarjeta profesional del revisor fiscal', 'ind_TarjetaProfRevisor', null, 18);
        $a('revisorCorreo', 'Correo del revisor fiscal', 'ind_EmailRevisor', null, 32);
    }

    /* ---------------------------------------------------------------------
     * Una fila por declaración (ica | reteica | autorreteica)
     * --------------------------------------------------------------------- */
    private static function _declaracion($clave, callable $hay)
    {
        $m = PseModulo::get($clave);
        $p = $m['prefijo'];
        $t = $m['tabla'];
        $esIca = $clave === 'ica';
        if (!$hay('T:' . $t)) { return null; }

        // Lo que puede faltar en una base.
        $conRuta    = $hay("C:$t.{$p}_RutaPago");          // 014 (ICA) / 032 (retenciones)
        $conBanco   = $hay("C:$t.{$p}_IdBancoPago") && $hay('T:ind_bancos');       // 045
        $conPm      = $hay('T:ind_pagos_manuales');                                 // 044
        $conPmFecha = $conPm && $hay('C:ind_pagos_manuales.pma_FechaPresentacion'); // 049
        $conPmCuenta = $conPm && $hay('C:ind_pagos_manuales.pma_CuentaContable');   // 051
        $colPres    = "{$p}_FechaPresentacion";
        $conPres    = $hay("C:$t.$colPres");               // en el ICA no la crea ninguna migración
        $colElab    = $esIca ? 'dec_FechaDeclaracion' : "{$p}_FechaDeclaracion";
        $colAnio    = $esIca ? 'dec_AnioDeclaracion' : "{$p}_Anio";
        $colCorrige = $esIca ? 'dec_DeclaracionCorrige' : "{$p}_Corrige";

        /* ---- Las expresiones de cartera (las mismas de InformePagos::filas) ---- */
        $valor = "ISNULL(d.{$m['valor']}, 0)";
        $pagada = $conRuta
            ? "CASE WHEN ISNULL(d.{$m['pagado']}, 0) = 1 AND ISNULL(d.{$p}_RutaPago, '') <> 'WOMPI_PRUEBA' THEN 1 ELSE 0 END"
            : "CASE WHEN ISNULL(d.{$m['pagado']}, 0) = 1 THEN 1 ELSE 0 END";
        $corregida = "CASE WHEN EXISTS (SELECT 1 FROM $t x WHERE x.$colCorrige = CAST(d.{$m['numero']} AS VARCHAR(30)) AND x.{$m['estado']} = 2) THEN 1 ELSE 0 END";
        $estado = "CASE WHEN ISNULL(d.{$m['estado']}, 0) <> 2 THEN 'borrador'"
                . " WHEN $pagada = 1 THEN 'pagada'"
                . " WHEN $corregida = 1 THEN 'corregida'"
                . " WHEN $valor <= 0 THEN 'sinvalor'"
                . " ELSE 'sinpagar' END";
        $presentacion = $conPres
            ? ($conPmFecha ? "COALESCE(pm.pma_FechaPresentacion, CAST(d.$colPres AS DATE))" : "CAST(d.$colPres AS DATE)")
            : null;
        $valorPagadoNum = "ROUND(ISNULL(d.{$p}_ValorPago, 0), 0)";
        $diferencia = "$valorPagadoNum - ROUND($valor, 0)";
        $joinPm = $conPm ? ['pm'] : [];

        /* ---- Los joins: solo se emiten los que algo use; todos son 1:1 (o un
           agregado de una fila) para que el conteo y la paginación no se multipliquen ---- */
        $joins = [
            'c'  => ['sql' => "LEFT JOIN ind_contribuyentes c ON c.ind_Id = d.{$p}_IdContribuyente", 'dep' => []],
            'ci' => ['sql' => 'LEFT JOIN conf_ciudades ci ON ci.ciu_Id = c.ind_IdCiudad', 'dep' => ['c']],
        ];
        if ($conBanco) {
            $joins['b'] = ['sql' => "LEFT JOIN ind_bancos b ON b.ban_Id = d.{$p}_IdBancoPago", 'dep' => []];
        }
        if ($conPm) {
            // Un pago manual por declaración (índice único UX_pma_declaracion).
            $joins['pm'] = ['sql' => "LEFT JOIN ind_pagos_manuales pm ON pm.pma_Modulo = '$clave' AND pm.pma_IdDeclaracion = d.{$m['pk']}", 'dep' => []];
        }
        $conAct = false;
        if ($esIca && $hay('T:ind_declaraciones_ica_actividades')) {
            $conAct = true;
            $joins['act'] = ['sql' => 'OUTER APPLY (SELECT COUNT(*) AS n FROM ind_declaraciones_ica_actividades a WHERE a.dia_IdDeclaracion = d.dec_Id AND a.dia_Activo = 1) act', 'dep' => []];
        } elseif ($clave === 'reteica' && $hay('T:ind_reteica_actividades')) {
            $conAct = true;
            $joins['act'] = ['sql' => 'OUTER APPLY (SELECT COUNT(*) AS n, SUM(a.rea_BaseGravable) AS base FROM ind_reteica_actividades a WHERE a.rea_IdReteica = d.ret_Id AND a.rea_Activo = 1) act', 'dep' => []];
        } elseif ($clave === 'autorreteica' && $hay('T:ind_autorreteica_actividades')) {
            $conAct = true;
            $joins['act'] = ['sql' => 'OUTER APPLY (SELECT COUNT(*) AS n FROM ind_autorreteica_actividades a WHERE a.aua_IdAutorreteica = d.aut_Id AND a.aua_Activo = 1) act', 'dep' => []];
        }

        $grupos = ['contribuyente' => 'Contribuyente', 'declaracion' => 'Declaración', 'valores' => 'Valores de la declaración', 'pago' => 'Pago'];
        $columnas = [];
        // El SQL de una fecha va como DATE; el resto, tal cual. Lo que llega en $o
        // manda sobre los valores por defecto.
        $col = function ($c, $etiqueta, $grupo, $tipo, $sql, array $o = []) use (&$columnas) {
            $columnas[$c] = $o + [
                'orden' => $sql, 'joins' => [], 'sens' => 0, 'total' => $tipo === 'dinero', 'opciones' => null,
                'ancho' => null, 'alinea' => null, 'ayuda' => '',
            ];
            $columnas[$c]['etiqueta'] = $etiqueta;
            $columnas[$c]['grupo'] = $grupo;
            $columnas[$c]['tipo'] = $tipo;
            $columnas[$c]['sql'] = $sql;
        };

        /* Contribuyente */
        $col('documento', 'NIT / Documento', 'contribuyente', 'codigo', self::_sqlDocumento(),
             ['orden' => 'c.ind_NumeroIdentificacion', 'joins' => ['c'], 'ancho' => 16, 'alinea' => 'L']);
        $col('tipoDocumento', 'Tipo de documento', 'contribuyente', 'lista', 'c.ind_IdTipoDocumento',
             ['joins' => ['c'], 'opciones' => self::TIPOS_DOCUMENTO, 'ancho' => 14, 'alinea' => 'C']);
        $col('contribuyente', 'Contribuyente', 'contribuyente', 'texto', self::_sqlNombre(),
             ['joins' => ['c'], 'ancho' => 38, 'alinea' => 'L']);
        $col('tipoPersona', 'Tipo de persona', 'contribuyente', 'lista', 'c.ind_Persona',
             ['joins' => ['c'], 'opciones' => self::TIPOS_PERSONA, 'ancho' => 14, 'alinea' => 'C']);
        $col('municipio', 'Municipio del contribuyente', 'contribuyente', 'texto', 'ci.ciu_Nombre',
             ['joins' => ['c', 'ci'], 'ancho' => 22, 'alinea' => 'L']);
        $col('departamento', 'Departamento del contribuyente', 'contribuyente', 'texto', 'ci.ciu_Departamento',
             ['joins' => ['c', 'ci'], 'ancho' => 22, 'alinea' => 'L']);

        /* Declaración */
        $col('numero', 'N° declaración', 'declaracion', 'codigo',
             "CASE WHEN d.{$m['numero']} > 0 THEN CAST(d.{$m['numero']} AS VARCHAR(20)) END",
             // El número de un borrador es provisional y NEGATIVO (-id): nunca se muestra.
             ['orden' => "CASE WHEN d.{$m['numero']} > 0 THEN d.{$m['numero']} END", 'ancho' => 16, 'alinea' => 'C']);
        $col('anio', 'Año', 'declaracion', 'anio', "d.$colAnio", ['ancho' => 8, 'alinea' => 'C']);
        if (!$esIca) {
            $col('periodo', $clave === 'autorreteica' ? 'Bimestre' : 'Mes', 'declaracion', 'lista', "d.{$p}_Periodo",
                 ['opciones' => self::_periodos($clave), 'ancho' => 18, 'alinea' => 'L']);
        }
        $col('estado', 'Estado', 'declaracion', 'lista', $estado,
             ['opciones' => self::ESTADOS, 'ancho' => 13, 'alinea' => 'C']);
        $col('tipoDeclaracion', 'Tipo de declaración', 'declaracion', 'lista',
             "CASE WHEN d.$colCorrige IS NOT NULL THEN 'correccion' ELSE 'inicial' END",
             ['opciones' => ['inicial' => 'Inicial', 'correccion' => 'Corrección'], 'ancho' => 14, 'alinea' => 'C']);
        $col('corrige', 'Corrige la declaración N°', 'declaracion', 'codigo',
             "CASE WHEN d.$colCorrige IS NOT NULL THEN CAST(d.$colCorrige AS VARCHAR(20)) END",
             ['orden' => "d.$colCorrige", 'ancho' => 16, 'alinea' => 'C']);
        if ($presentacion) {
            $col('fechaPresentacion', 'Fecha de presentación', 'declaracion', 'fecha', $presentacion,
                 ['joins' => $joinPm, 'ancho' => 14, 'alinea' => 'C']);
        }
        $col('fechaElaboracion', 'Fecha de elaboración', 'declaracion', 'fecha', "CAST(d.$colElab AS DATE)",
             ['ancho' => 14, 'alinea' => 'C']);
        if ($conAct) {
            $col('nActividades', 'Actividades declaradas', 'declaracion', 'entero', 'ISNULL(act.n, 0)',
                 ['joins' => ['act'], 'ancho' => 12, 'alinea' => 'C']);
        }
        if ($esIca && $hay('C:ind_declaraciones_ica.dec_TipoSancion')) {
            $col('tipoSancion', 'Tipo de sanción', 'declaracion', 'lista', 'd.dec_TipoSancion',
                 ['opciones' => ['extemporaneidad' => 'Extemporaneidad', 'correccion' => 'Corrección',
                                 'inexactitud' => 'Inexactitud', 'otra' => 'Otra'], 'ancho' => 18, 'alinea' => 'L']);
        }

        /* Valores de la declaración (los rótulos son los del formulario impreso) */
        $dinero = function ($c, $etiqueta, $campo) use ($col, $hay, $t) {
            if (!$hay("C:$t.$campo")) { return; }
            $col($c, $etiqueta, 'valores', 'dinero', "ROUND(d.$campo, 0)", ['ancho' => 16, 'alinea' => 'R']);
        };
        if ($esIca) {
            $col('valorDeclarado', 'Total a pagar', 'valores', 'dinero', "ROUND($valor, 0)", ['ancho' => 16, 'alinea' => 'R']);
            foreach ([
                ['totalIngresos', 'Total ingresos del período', 'dec_TotalIngresos'],
                ['ingresosFueraMunicipio', 'Menos ingresos fuera del municipio', 'dec_IngresosFueraMunicipio'],
                ['ingresosDevoluciones', 'Menos devoluciones, rebajas y descuentos', 'dec_IngresosDevoluciones'],
                ['ingresosExportaciones', 'Menos exportaciones', 'dec_IngresosExportaciones'],
                ['ingresosVentas', 'Menos venta de activos fijos', 'dec_IngresosVentas'],
                ['ingresosActividades', 'Menos actividades excluidas o no sujetas', 'dec_IngresosActividades'],
                ['ingresosOtrasActividades', 'Menos otras actividades exentas', 'dec_IngresosOtrasActividades'],
                ['baseGravable', 'Total ingresos gravados (base gravable)', 'dec_BaseGravable'],
                ['impuestoIca', 'Impuesto de industria y comercio', 'dec_ValorConcepto1'],
                ['avisosTableros', 'Avisos y tableros', 'dec_ValorConcepto2'],
                ['sobretasaBomberil', 'Sobretasa bomberil', 'dec_ValorConcepto3'],
                ['totalImpuestoCargo', 'Total impuesto a cargo', 'dec_ValorConcepto4'],
                ['exencion', 'Menos exención o exoneración', 'dec_ValorConcepto5'],
                ['retenciones', 'Menos retenciones', 'dec_ValorConcepto6'],
                ['autorretenciones', 'Menos autorretenciones', 'dec_ValorConcepto7'],
                ['anticipoAnterior', 'Menos anticipo del año anterior', 'dec_ValorConcepto8'],
                ['anticipoSiguiente', 'Más anticipo del año siguiente', 'dec_ValorConcepto9'],
                ['sanciones', 'Sanciones', 'dec_ValorConcepto10'],
                ['saldoFavorAnterior', 'Menos saldo a favor de vigencias anteriores', 'dec_ValorConcepto11'],
                ['saldoCargo', 'Total saldo a cargo', 'dec_ValorConcepto12'],
                ['saldoFavor', 'Total saldo a favor', 'dec_ValorConcepto13'],
                ['valorPagar', 'Valor a pagar antes del pronto pago (casilla 14)', 'dec_ValorConcepto14'],
                ['descuentoProntoPago', 'Descuento por pronto pago', 'dec_ValorConcepto15'],
                ['interesesDeclarados', 'Intereses de mora liquidados en la declaración', 'dec_ValorConcepto16'],
                ['impuestoEnergia', 'Impuesto de energía (Ley 56 de 1981)', 'dec_ValorImpuesto'],
            ] as $d) { $dinero($d[0], $d[1], $d[2]); }
            if ($hay('C:ind_declaraciones_ica.dec_CapacidadInstalada')) {
                $col('capacidadInstalada', 'Capacidad instalada (kW)', 'valores', 'entero', 'd.dec_CapacidadInstalada', ['ancho' => 14, 'alinea' => 'R']);
            }
        } elseif ($clave === 'reteica') {
            $col('valorDeclarado', 'Total a pagar (casilla 17)', 'valores', 'dinero', "ROUND($valor, 0)", ['ancho' => 16, 'alinea' => 'R']);
            $dinero('totalRetenciones', 'Total valor retenciones (casilla 14)', 'ret_ValorConcepto14');
            $dinero('sanciones', 'Sanciones (casilla 15)', 'ret_ValorConcepto15');
            $dinero('interesesDeclarados', 'Intereses moratorios (casilla 16)', 'ret_ValorConcepto16');
            if ($conAct) {
                $col('baseRetenida', 'Base de las retenciones', 'valores', 'dinero', 'ROUND(act.base, 0)',
                     ['joins' => ['act'], 'ancho' => 16, 'alinea' => 'R']);
            }
        } else {
            $col('valorDeclarado', 'Total a pagar en el bimestre (casilla 23)', 'valores', 'dinero', "ROUND($valor, 0)", ['ancho' => 16, 'alinea' => 'R']);
            foreach ([
                ['ingresosBrutos', 'Ingresos brutos recibidos en el bimestre (casilla 9)', 'aut_ValorConcepto9'],
                ['ingresosDevoluciones', 'Menos devoluciones, rebajas y descuentos (casilla 10)', 'aut_ValorConcepto10'],
                ['ingresosDeducciones', 'Menos deducciones, exenciones y no sujeciones (casilla 11)', 'aut_ValorConcepto11'],
                ['ingresosFueraMunicipio', 'Menos ingresos fuera del municipio (casilla 12)', 'aut_ValorConcepto12'],
                ['ingresosNetosGravados', 'Ingresos netos gravados (casilla 13)', 'aut_ValorConcepto13'],
                ['impuestoEnergia', 'Impuesto de energía (Ley 56 de 1981)', 'aut_ImpuestoEnergia'],
                ['autorretencionIca', 'Autorretenciones de industria y comercio (casilla 15)', 'aut_ValorConcepto15'],
                ['autorretencionAvisos', 'Autorretenciones de avisos y tableros (casilla 16)', 'aut_ValorConcepto16'],
                ['totalAutorretenciones', 'Total autorretenciones a cargo (casilla 17)', 'aut_ValorConcepto17'],
                ['anticipos', 'Menos anticipos u otros (casilla 18)', 'aut_ValorConcepto18'],
                ['autorretencionLiquidada', 'Total autorretención liquidada (casilla 19)', 'aut_ValorConcepto19'],
                ['saldoFavor', 'Total saldo a favor (casilla 20)', 'aut_ValorConcepto20'],
                ['interesesDeclarados', 'Intereses (casilla 21)', 'aut_ValorConcepto21'],
                ['sanciones', 'Más sanciones (casilla 22)', 'aut_ValorConcepto22'],
            ] as $d) { $dinero($d[0], $d[1], $d[2]); }
        }

        /* Pago. Todo lo del pago va vacío si la declaración no está pagada de verdad. */
        $col('pagada', 'Pagada', 'pago', 'booleano', $pagada, ['ancho' => 9, 'alinea' => 'C']);
        $col('fechaPago', 'Fecha de pago', 'pago', 'fecha', "CASE WHEN $pagada = 1 THEN CAST(d.{$p}_FechaPago AS DATE) END",
             ['ancho' => 14, 'alinea' => 'C']);
        $col('valorPagado', 'Valor pagado', 'pago', 'dinero', "CASE WHEN $pagada = 1 THEN $valorPagadoNum END",
             ['ancho' => 16, 'alinea' => 'R']);
        $col('intereses', 'Intereses de mora pagados', 'pago', 'dinero',
             "CASE WHEN $pagada = 1 AND $diferencia >= 0 THEN $diferencia END",
             ['ancho' => 16, 'alinea' => 'R',
              'ayuda' => 'Lo pagado menos el total a pagar de la declaración. Va vacío si se pagó menos.']);
        $bancoSql = $conBanco ? "COALESCE(b.ban_Nombre, d.{$p}_BancoPago)" : "d.{$p}_BancoPago";
        $col('banco', 'Banco', 'pago', 'texto', "CASE WHEN $pagada = 1 THEN LTRIM(RTRIM($bancoSql)) END",
             ['joins' => $conBanco ? ['b'] : [], 'ancho' => 30, 'alinea' => 'L']);
        if ($conRuta) {
            $canal = "CASE WHEN $pagada = 1 THEN "
                   . ($conPm
                        ? "CASE WHEN d.{$p}_RutaPago = 'MANUAL' AND ISNULL(pm.pma_Medio, '') <> '' THEN 'MANUAL:' + pm.pma_Medio "
                        : 'CASE ')
                   . "WHEN d.{$p}_RutaPago IN ('" . implode("', '", array_keys(InformePagos::CANALES)) . "') THEN d.{$p}_RutaPago ELSE 'OTRO' END END";
            $col('canal', 'Medio de pago', 'pago', 'lista', $canal,
                 ['joins' => $joinPm, 'opciones' => self::_opcionesCanal(), 'ancho' => 16, 'alinea' => 'L']);
        }
        if ($conPmCuenta) {
            $col('cuentaContable', 'Cuenta contable', 'pago', 'codigo',
                 "CASE WHEN $pagada = 1 THEN LTRIM(RTRIM(pm.pma_CuentaContable)) END",
                 ['joins' => ['pm'], 'ancho' => 16, 'alinea' => 'C']);
        }

        /* Contacto y terceros (datos personales) */
        $grupos['contacto'] = 'Contacto y terceros (datos personales)';
        self::_contacto($columnas, $hay, 'contacto');

        /* ---- Los filtros ---- */
        $filtros = [];
        $fil = function ($c, $etiqueta, $grupo, $tipo, array $ops, $sql, array $o = []) use (&$filtros) {
            $filtros[$c] = $o + ['joins' => [], 'opciones' => null, 'valor' => 'texto', 'ayuda' => '', 'min' => null, 'max' => null];
            $filtros[$c] += ['etiqueta' => $etiqueta, 'grupo' => $grupo, 'tipo' => $tipo, 'ops' => $ops, 'sql' => $sql];
        };
        $gruposFiltro = ['declaraciones' => 'Declaraciones', 'situacion' => 'Situación y pago', 'contribuyente' => 'Contribuyente'];

        $fil('anio', 'Año', 'declaraciones', 'entero', ['in', 'between'], "d.$colAnio",
             ['min' => 2000, 'max' => 2100, 'ayuda' => 'Año de la declaración.']);
        if (!$esIca) {
            $fil('periodo', $clave === 'autorreteica' ? 'Bimestre' : 'Mes', 'declaraciones', 'lista', ['in'], "d.{$p}_Periodo",
                 ['opciones' => self::_periodos($clave), 'valor' => 'entero']);
        }
        $fil('estado', 'Estado', 'situacion', 'lista', ['in'], $estado,
             ['opciones' => self::ESTADOS,
              'ayuda' => 'Sin elegir ningún estado se traen las declaraciones presentadas. Los borradores solo salen si se piden.']);
        $fil('corregida', 'Tiene una corrección presentada', 'situacion', 'booleano', ['='], $corregida,
             ['ayuda' => 'Declaraciones cuya corrección ya se presentó (aunque estén pagadas).']);
        $fil('tipoDeclaracion', 'Tipo de declaración', 'declaraciones', 'lista', ['in'],
             "CASE WHEN d.$colCorrige IS NOT NULL THEN 'correccion' ELSE 'inicial' END",
             ['opciones' => ['inicial' => 'Inicial', 'correccion' => 'Corrección']]);
        if ($presentacion) {
            $fil('fechaPresentacion', 'Fecha de presentación', 'situacion', 'fecha', ['between', '>=', '<='], $presentacion,
                 ['joins' => $joinPm]);
        }
        $fil('fechaPago', 'Fecha de pago', 'situacion', 'fecha', ['between', '>=', '<='],
             "CASE WHEN $pagada = 1 THEN CAST(d.{$p}_FechaPago AS DATE) END");
        $fil('valorDeclarado', 'Total a pagar', 'situacion', 'dinero', ['between', '>=', '<='], "ROUND($valor, 0)");
        $fil('conIntereses', 'Con intereses de mora pagados', 'situacion', 'booleano', ['='],
             "CASE WHEN $pagada = 1 AND $diferencia > 0 THEN 1 ELSE 0 END");
        if ($conRuta) {
            $fil('canal', 'Canal de pago', 'situacion', 'lista', ['in'],
                 "CASE WHEN $pagada = 1 THEN d.{$p}_RutaPago END",
                 ['opciones' => ['PSE' => 'PSE', 'WOMPI' => 'Wompi', 'RECAUDO_BANCARIO' => 'Recaudo bancario', 'MANUAL' => 'Manual']]);
        }
        if ($conBanco) {
            // El banco POR EL QUE ENTRÓ la plata (su id), no el texto del PSE (que es el banco del que pagó).
            $fil('banco', 'Banco (solo pagos con banco registrado)', 'situacion', 'lista', ['in'], "CASE WHEN $pagada = 1 THEN d.{$p}_IdBancoPago END",
                 ['valor' => 'entero', 'opcionesFn' => 'bancos']);
        }
        $fil('documento', 'NIT o cédula (sin dígito de verificación)', 'contribuyente', 'entero', ['in'], 'c.ind_NumeroIdentificacion',
             ['joins' => ['c'], 'min' => 1, 'max' => 2147483647]);
        $fil('contribuyente', 'Nombre del contribuyente', 'contribuyente', 'texto', ['contiene'], self::_sqlNombre(), ['joins' => ['c']]);
        $fil('tipoPersona', 'Tipo de persona', 'contribuyente', 'lista', ['in'], 'c.ind_Persona',
             ['joins' => ['c'], 'opciones' => self::TIPOS_PERSONA, 'valor' => 'entero']);
        $fil('municipio', 'Municipio del contribuyente', 'contribuyente', 'texto', ['contiene'], 'ci.ciu_Nombre', ['joins' => ['c', 'ci']]);

        return [
            'clave' => $clave, 'etiqueta' => self::ETIQUETA_FUENTE[$clave], 'modo' => 'declaracion', 'impuesto' => $clave,
            'permiso' => self::PERMISO_FUENTE[$clave],
            'tabla' => $t, 'alias' => 'd', 'pk' => 'd.' . $m['pk'],
            'desde' => "FROM $t d",
            'joins' => $joins,
            'grupos' => $grupos, 'gruposFiltro' => $gruposFiltro,
            'columnas' => $columnas, 'filtros' => $filtros,
            // Si el informe no filtra por estado se traen las presentadas, como el Informe rápido.
            'base' => "d.{$m['estado']} = 2", 'baseSalvoFiltro' => 'estado',
            'unidad' => ['singular' => 'declaración', 'plural' => 'declaraciones'],
            // El buscador de la vista previa: el documento (sin DV), el nombre y el número de la declaración
            // (el provisional de un borrador es negativo y no se busca).
            'buscar' => [
                ['sql' => 'CAST(c.ind_NumeroIdentificacion AS VARCHAR(20))', 'num' => true, 'joins' => ['c']],
                ['sql' => self::_sqlNombre(), 'num' => false, 'joins' => ['c']],
                ['sql' => "CASE WHEN d.{$m['numero']} > 0 THEN CAST(d.{$m['numero']} AS VARCHAR(20)) END", 'num' => true, 'joins' => []],
            ],
            // Piezas que reutiliza la fuente agrupada (_agrupado): se arma sobre las mismas reglas de cartera.
            '_decl' => ['idc' => "d.{$p}_IdContribuyente", 'anio' => "d.$colAnio", 'pagada' => $pagada,
                        'corregida' => $corregida, 'estadoCol' => "d.{$m['estado']}"],
        ];
    }

    /* ---------------------------------------------------------------------
     * Una fila por contribuyente (el RIT)
     * --------------------------------------------------------------------- */
    private static function _contribuyentes(callable $hay)
    {
        if (!$hay('T:ind_contribuyentes')) { return null; }
        $existe = function ($campo) use ($hay) { return $hay('C:ind_contribuyentes.' . $campo); };

        $joins = [
            'ci' => ['sql' => 'LEFT JOIN conf_ciudades ci ON ci.ciu_Id = c.ind_IdCiudad', 'dep' => []],
        ];
        $conFirmas = $hay('T:ind_rit_firmas');
        if ($conFirmas) {
            // La última firma del RIT (TOP 1): una fila por contribuyente, sin multiplicar.
            $joins['rf'] = ['sql' => 'OUTER APPLY (SELECT TOP 1 f.rif_FechaHora AS fecha FROM ind_rit_firmas f WHERE f.rif_IdContribuyente = c.ind_Id ORDER BY f.rif_FechaHora DESC, f.rif_Id DESC) rf', 'dep' => []];
        }

        $grupos = ['contribuyente' => 'Contribuyente', 'rit' => 'RIT', 'cese' => 'Cese de actividades', 'establecimientos' => 'Establecimientos'];
        $columnas = [];
        $col = function ($c, $etiqueta, $grupo, $tipo, $sql, array $o = []) use (&$columnas) {
            $columnas[$c] = $o + ['orden' => $sql, 'joins' => [], 'sens' => 0, 'total' => false, 'opciones' => null, 'ancho' => null, 'alinea' => null];
            $columnas[$c]['etiqueta'] = $etiqueta;
            $columnas[$c]['grupo'] = $grupo;
            $columnas[$c]['tipo'] = $tipo;
            $columnas[$c]['sql'] = $sql;
        };
        $bit = function ($campo) { return "CASE WHEN c.$campo = 1 THEN 1 ELSE 0 END"; };
        $csv = function ($campo, $codigo) { return "CASE WHEN ',' + ISNULL(c.$campo, '') + ',' LIKE '%,$codigo,%' THEN 1 ELSE 0 END"; };

        $col('documento', 'NIT / Documento', 'contribuyente', 'codigo', self::_sqlDocumento(),
             ['orden' => 'c.ind_NumeroIdentificacion', 'ancho' => 16, 'alinea' => 'L']);
        $col('tipoDocumento', 'Tipo de documento', 'contribuyente', 'lista', 'c.ind_IdTipoDocumento',
             ['opciones' => self::TIPOS_DOCUMENTO, 'ancho' => 14, 'alinea' => 'C']);
        $col('contribuyente', 'Contribuyente', 'contribuyente', 'texto', self::_sqlNombre(), ['ancho' => 38, 'alinea' => 'L']);
        $col('tipoPersona', 'Tipo de persona', 'contribuyente', 'lista', 'c.ind_Persona',
             ['opciones' => self::TIPOS_PERSONA, 'ancho' => 14, 'alinea' => 'C']);
        $col('municipio', 'Municipio del contribuyente', 'contribuyente', 'texto', 'ci.ciu_Nombre', ['joins' => ['ci'], 'ancho' => 22, 'alinea' => 'L']);
        $col('departamento', 'Departamento del contribuyente', 'contribuyente', 'texto', 'ci.ciu_Departamento', ['joins' => ['ci'], 'ancho' => 22, 'alinea' => 'L']);
        $col('fechaInscripcion', 'Fecha de inscripción', 'contribuyente', 'fecha', 'CAST(c.ind_FechaCreacion AS DATE)', ['ancho' => 14, 'alinea' => 'C']);

        if ($existe('ind_Matricula')) {
            $col('matricula', 'Matrícula mercantil', 'rit', 'codigo', 'c.ind_Matricula', ['ancho' => 18, 'alinea' => 'L']);
        }
        if ($existe('ind_Fecha_matricula')) {
            $col('fechaMatricula', 'Fecha de matrícula', 'rit', 'fecha', 'CAST(c.ind_Fecha_matricula AS DATE)', ['ancho' => 14, 'alinea' => 'C']);
        }
        if ($existe('ind_Fecha_inicio')) {
            $col('fechaInicioActividades', 'Fecha de inicio de actividades', 'rit', 'fecha', 'CAST(c.ind_Fecha_inicio AS DATE)', ['ancho' => 14, 'alinea' => 'C']);
        }
        if ($existe('ind_RegimenTributario')) {
            $col('regimen', 'Régimen tributario', 'rit', 'multilista', 'c.ind_RegimenTributario',
                 ['opciones' => self::REGIMENES, 'ancho' => 30, 'alinea' => 'L']);
        }
        if ($existe('ind_Responsabilidades')) {
            $col('responsabilidades', 'Responsabilidades', 'rit', 'multilista', 'c.ind_Responsabilidades',
                 ['opciones' => self::RESPONSABILIDADES, 'ancho' => 34, 'alinea' => 'L']);
        }
        foreach ([['sinAvisosTableros', 'Exento de avisos y tableros', 'ind_SinAvisosTableros'],
                  ['noSujetas', 'Actividades no sujetas', 'ind_NoSujetas'],
                  ['esConsorcio', 'Es consorcio o unión temporal', 'ind_EsConsorcio'],
                  ['patrimonioAutonomo', 'Es patrimonio autónomo', 'ind_PatrimonioAutonomo']] as $b) {
            if ($existe($b[2])) { $col($b[0], $b[1], 'rit', 'booleano', $bit($b[2]), ['ancho' => 14, 'alinea' => 'C']); }
        }
        foreach ([['ciiuPrincipal', 'CIIU principal', 'ind_Rut'], ['ciiuSegundo', 'CIIU secundario', 'ind_Rut_segundo'],
                  ['ciiuTercero', 'CIIU terciario', 'ind_Rut_tercero']] as $c) {
            if ($existe($c[2])) { $col($c[0], $c[1], 'rit', 'codigo', 'c.' . $c[2], ['ancho' => 12, 'alinea' => 'C']); }
        }
        if ($conFirmas) {
            $col('ritFirmado', 'RIT firmado alguna vez', 'rit', 'booleano', 'CASE WHEN rf.fecha IS NOT NULL THEN 1 ELSE 0 END',
                 ['joins' => ['rf'], 'ancho' => 14, 'alinea' => 'C',
                  'ayuda' => 'Hay una firma del RIT registrada. No dice si esa firma sigue vigente (un cambio posterior la invalida).']);
            $col('fechaFirmaRit', 'Fecha de la última firma del RIT', 'rit', 'fecha', 'CAST(rf.fecha AS DATE)',
                 ['joins' => ['rf'], 'ancho' => 16, 'alinea' => 'C']);
        }
        if ($existe('ind_FechaCese')) {
            $col('conCese', 'Con cese de actividades', 'cese', 'booleano', 'CASE WHEN c.ind_FechaCese IS NOT NULL THEN 1 ELSE 0 END', ['ancho' => 14, 'alinea' => 'C']);
            $col('fechaCese', 'Fecha de cese', 'cese', 'fecha', 'CAST(c.ind_FechaCese AS DATE)', ['ancho' => 14, 'alinea' => 'C']);
        }
        if ($existe('ind_CausalCese')) {
            $col('causalCese', 'Causal de cese', 'cese', 'lista', 'c.ind_CausalCese', ['opciones' => self::CAUSALES_CESE, 'ancho' => 16, 'alinea' => 'L']);
        }
        if ($hay('T:ind_establecimientos')) {
            $col('nEstablecimientos', 'Establecimientos activos', 'establecimientos', 'entero',
                 '(SELECT COUNT(*) FROM ind_establecimientos e WHERE e.est_IdContribuyente = c.ind_Id AND e.est_Activo = 1)',
                 ['ancho' => 14, 'alinea' => 'C']);
        }

        // Datos personales: el cese lleva un texto libre que puede nombrar a alguien.
        $grupos['contacto'] = 'Contacto y terceros (datos personales)';
        self::_contacto($columnas, $hay, 'contacto');
        if ($existe('ind_ObservacionCese')) {
            $columnas['observacionCese'] = ['etiqueta' => 'Observación del cese', 'grupo' => 'contacto', 'tipo' => 'texto',
                'sql' => 'c.ind_ObservacionCese', 'orden' => false, 'joins' => [], 'sens' => 1, 'total' => false,
                'opciones' => null, 'ancho' => 34, 'alinea' => 'L', 'ajuste' => true];
        }

        $filtros = [];
        $fil = function ($c, $etiqueta, $grupo, $tipo, array $ops, $sql, array $o = []) use (&$filtros) {
            $filtros[$c] = $o + ['joins' => [], 'opciones' => null, 'valor' => 'texto', 'ayuda' => '', 'min' => null, 'max' => null];
            $filtros[$c] += ['etiqueta' => $etiqueta, 'grupo' => $grupo, 'tipo' => $tipo, 'ops' => $ops, 'sql' => $sql];
        };
        $gruposFiltro = ['contribuyente' => 'Contribuyente', 'rit' => 'RIT'];
        $fil('documento', 'NIT o cédula (sin dígito de verificación)', 'contribuyente', 'entero', ['in'], 'c.ind_NumeroIdentificacion',
             ['min' => 1, 'max' => 2147483647]);
        $fil('contribuyente', 'Nombre del contribuyente', 'contribuyente', 'texto', ['contiene'], self::_sqlNombre());
        $fil('tipoPersona', 'Tipo de persona', 'contribuyente', 'lista', ['in'], 'c.ind_Persona',
             ['opciones' => self::TIPOS_PERSONA, 'valor' => 'entero']);
        $fil('tipoDocumento', 'Tipo de documento', 'contribuyente', 'lista', ['in'], 'c.ind_IdTipoDocumento',
             ['opciones' => self::TIPOS_DOCUMENTO, 'valor' => 'entero']);
        $fil('municipio', 'Municipio del contribuyente', 'contribuyente', 'texto', ['contiene'], 'ci.ciu_Nombre', ['joins' => ['ci']]);
        $fil('fechaInscripcion', 'Fecha de inscripción', 'contribuyente', 'fecha', ['between', '>=', '<='], 'CAST(c.ind_FechaCreacion AS DATE)');
        if ($existe('ind_RegimenTributario')) {
            // Un contribuyente puede tener varios regímenes marcados: basta con que tenga alguno de los pedidos.
            $fil('regimen', 'Régimen tributario', 'rit', 'lista', ['in'], 'c.ind_RegimenTributario',
                 ['opciones' => self::REGIMENES, 'csv' => 'ind_RegimenTributario']);
        }
        if ($existe('ind_Responsabilidades')) {
            $fil('agenteRetencion', 'Es agente de retención', 'rit', 'booleano', ['='], $csv('ind_Responsabilidades', 'AGENTE_RETENCION'));
            $fil('autorretenedor', 'Es autorretenedor', 'rit', 'booleano', ['='], $csv('ind_Responsabilidades', 'AUTORRETENEDOR'));
        }
        if ($existe('ind_FechaCese')) {
            $fil('conCese', 'Con cese de actividades', 'rit', 'booleano', ['='], 'CASE WHEN c.ind_FechaCese IS NOT NULL THEN 1 ELSE 0 END');
        }
        if ($conFirmas) {
            $fil('ritFirmado', 'RIT firmado alguna vez', 'rit', 'booleano', ['='], 'CASE WHEN rf.fecha IS NOT NULL THEN 1 ELSE 0 END', ['joins' => ['rf']]);
        }
        foreach ([['sinAvisosTableros', 'Exento de avisos y tableros', 'ind_SinAvisosTableros'],
                  ['noSujetas', 'Actividades no sujetas', 'ind_NoSujetas'],
                  ['esConsorcio', 'Es consorcio o unión temporal', 'ind_EsConsorcio'],
                  ['patrimonioAutonomo', 'Es patrimonio autónomo', 'ind_PatrimonioAutonomo']] as $b) {
            if ($existe($b[2])) { $fil($b[0], $b[1], 'rit', 'booleano', ['='], $bit($b[2])); }
        }
        if ($existe('ind_Rut')) {
            // El código CIIU puede estar en cualquiera de las tres casillas del RUT.
            $fil('ciiu', 'Código CIIU (cualquiera de los tres)', 'rit', 'texto', ['igual'], 'c.ind_Rut',
                 ['ciiu' => array_values(array_filter(['ind_Rut', $existe('ind_Rut_segundo') ? 'ind_Rut_segundo' : null,
                                                       $existe('ind_Rut_tercero') ? 'ind_Rut_tercero' : null]))]);
        }

        return [
            'clave' => 'contribuyentes', 'etiqueta' => self::ETIQUETA_FUENTE['contribuyentes'], 'modo' => 'contribuyente', 'impuesto' => null,
            'permiso' => self::PERMISO_FUENTE['contribuyentes'],
            'tabla' => 'ind_contribuyentes', 'alias' => 'c', 'pk' => 'c.ind_Id',
            'desde' => 'FROM ind_contribuyentes c',
            'joins' => $joins,
            'grupos' => $grupos, 'gruposFiltro' => $gruposFiltro,
            'columnas' => $columnas, 'filtros' => $filtros,
            'base' => null, 'baseSalvoFiltro' => null,
            'unidad' => ['singular' => 'contribuyente', 'plural' => 'contribuyentes'],
            'buscar' => [
                ['sql' => 'CAST(c.ind_NumeroIdentificacion AS VARCHAR(20))', 'num' => true, 'joins' => []],
                ['sql' => self::_sqlNombre(), 'num' => false, 'joins' => []],
            ],
        ];
    }

    /* ---------------------------------------------------------------------
     * Una fila por contribuyente con sus declaraciones de UN impuesto sumadas
     * --------------------------------------------------------------------- */

    /**
     * Las declaraciones "vigentes" de cada contribuyente (presentadas y sin una
     * corrección presentada encima: una original ya corregida no se suma, la
     * reemplaza la corrección) se agrupan por contribuyente.
     *
     * Se arma sobre la fuente de declaraciones del mismo impuesto, así que las
     * reglas de cartera (pagada, intereses, valor) son EXACTAMENTE las del modo
     * "una fila por declaración": la prueba de oráculo compara los totales de un
     * modo contra los del otro. Las columnas de dinero son la SUMA de la misma
     * expresión; el resto lo arma InformeConstructor::_armarAgrupado:
     *
     *   y  = una fila por (contribuyente, año) con las sumas de ese año
     *   g  = una fila por contribuyente: suma de y, años presentados y último año pagado
     *   luego ind_contribuyentes c JOIN g, de donde salen los datos del contribuyente.
     *
     * Los filtros son de dos niveles: los de la declaración (año, período, fechas, canal...)
     * se aplican ANTES de agrupar, dentro de y ("la suma de las declaraciones de 2026");
     * los del contribuyente (documento, nombre...) después. Un contribuyente sin ninguna
     * declaración vigente que cumpla los filtros no sale (no hay nada que sumar).
     */
    private static function _agrupado($base, callable $hay)
    {
        $d = self::_declaracion($base, $hay);
        if (!$d) { return null; }
        $x = $d['_decl'];

        $grupos = ['contribuyente' => 'Contribuyente', 'resumen' => 'Resumen de las declaraciones vigentes',
                   'valores' => 'Sumas de los valores de las declaraciones vigentes', 'pago' => 'Sumas de los pagos',
                   'contacto' => 'Contacto y terceros (datos personales)'];
        $columnas = [];
        // Los datos del contribuyente salen de la tabla de contribuyentes (alias c, ahora la principal).
        foreach ($d['columnas'] as $k => $def) {
            if (!in_array($def['grupo'], ['contribuyente', 'contacto'], true)) { continue; }
            $def['joins'] = array_values(array_diff($def['joins'], ['c']));
            $columnas[$k] = $def;
        }

        $agg = function ($k, $etiqueta, $grupo, $tipo, $sqlSalida, array $o) use (&$columnas) {
            $columnas[$k] = $o + ['orden' => $sqlSalida, 'joins' => [], 'sens' => 0, 'total' => false, 'opciones' => null,
                                  'ancho' => null, 'alinea' => null, 'ayuda' => ''];
            $columnas[$k]['etiqueta'] = $etiqueta;
            $columnas[$k]['grupo'] = $grupo;
            $columnas[$k]['tipo'] = $tipo;
            $columnas[$k]['sql'] = $sqlSalida;
        };
        $agg('nDeclaraciones', 'Declaraciones vigentes', 'resumen', 'entero', 'g.a_nDeclaraciones',
             ['agg' => 'n', 'ancho' => 14, 'alinea' => 'C',
              'ayuda' => 'Cuántas declaraciones presentadas, sin una corrección presentada encima, cumplen los filtros.']);
        $agg('aniosPresentados', 'Años presentados', 'resumen', 'texto', 'g.a_aniosPresentados',
             ['agg' => 'anios', 'ancho' => 24, 'alinea' => 'L', 'ayuda' => 'Los años de esas declaraciones, de menor a mayor.']);
        $agg('ultimoAnioPagado', 'Último año pagado', 'resumen', 'anio', 'g.a_ultimoAnioPagado',
             ['agg' => 'ultimoPagado', 'ancho' => 12, 'alinea' => 'C', 'ayuda' => 'El año más reciente con una declaración pagada.']);
        // Cada columna de dinero de la declaración, sumada: el mismo SQL dentro de SUM().
        foreach ($d['columnas'] as $k => $def) {
            if ($def['tipo'] !== 'dinero') { continue; }
            $agg($k, $def['etiqueta'] . ' (suma)', $def['grupo'] === 'pago' ? 'pago' : 'valores', 'dinero', 'g.a_' . $k,
                 ['agg' => 'suma', 'dsql' => $def['sql'], 'dj' => $def['joins'], 'total' => true, 'ancho' => $def['ancho'], 'alinea' => 'R',
                  'ayuda' => (string) ($def['ayuda'] ?? '')]);
        }

        /* Filtros: nivel 'd' = de la declaración (dentro de y); nivel 'c' = del contribuyente (fuera) */
        $filtros = [];
        foreach (['anio', 'periodo', 'estado', 'tipoDeclaracion', 'fechaPresentacion', 'fechaPago', 'valorDeclarado', 'conIntereses', 'canal', 'banco'] as $k) {
            if (!isset($d['filtros'][$k])) { continue; }
            $filtros[$k] = $d['filtros'][$k] + ['nivel' => 'd'];
        }
        if (isset($filtros['estado'])) {
            // "Corregida" no es un estado de las vigentes (una corregida no se suma).
            $filtros['estado']['opciones'] = array_intersect_key(self::ESTADOS, array_flip(['pagada', 'sinpagar', 'sinvalor']));
            $filtros['estado']['ayuda'] = 'Sin elegir ningún estado se traen todas las vigentes: pagadas, sin pagar y sin valor.';
        }
        foreach (['documento', 'contribuyente', 'tipoPersona', 'municipio'] as $k) {
            if (!isset($d['filtros'][$k])) { continue; }
            $f = $d['filtros'][$k] + ['nivel' => 'c'];
            $f['joins'] = array_values(array_diff($f['joins'], ['c']));
            $filtros[$k] = $f;
        }

        return [
            'clave' => $base . self::SUFIJO_AGRUPADO, 'etiqueta' => self::ETIQUETA_FUENTE[$base] . ' · por contribuyente',
            'modo' => 'agrupado', 'impuesto' => $base, 'permiso' => self::PERMISO_FUENTE[$base],
            'tabla' => 'ind_contribuyentes', 'alias' => 'c', 'pk' => 'c.ind_Id',
            'desde' => 'FROM ind_contribuyentes c',
            'joins' => ['ci' => ['sql' => 'LEFT JOIN conf_ciudades ci ON ci.ciu_Id = c.ind_IdCiudad', 'dep' => []]],
            'grupos' => $grupos,
            'gruposFiltro' => ['declaraciones' => 'Declaraciones', 'situacion' => 'Situación y pago', 'contribuyente' => 'Contribuyente'],
            'columnas' => $columnas, 'filtros' => $filtros,
            'base' => null, 'baseSalvoFiltro' => null,
            'baseTexto' => 'Declaraciones vigentes (presentadas y sin una corrección presentada)',
            'unidad' => ['singular' => 'contribuyente', 'plural' => 'contribuyentes'],
            'buscar' => [
                ['sql' => 'CAST(c.ind_NumeroIdentificacion AS VARCHAR(20))', 'num' => true, 'joins' => []],
                ['sql' => self::_sqlNombre(), 'num' => false, 'joins' => []],
            ],
            // Lo que armar() necesita de la fuente de declaraciones para escribir el CTE.
            'agrupa' => ['fuente' => $d, 'idc' => $x['idc'], 'anio' => $x['anio'], 'pagada' => $x['pagada'],
                         'corregida' => $x['corregida'], 'estadoCol' => $x['estadoCol']],
        ];
    }

    /* =====================================================================
     * LO QUE VE LA PANTALLA
     * ===================================================================== */

    /** Los bancos para el filtro "Banco" (los que no son CAJA ni PSE, que no son bancos). */
    public static function bancos($con)
    {
        $r = [];
        try {
            $st = $con->consultar("SELECT ban_Id, ban_Nombre FROM ind_bancos WHERE ban_Codigo NOT IN ('00', '999') ORDER BY ban_Nombre", []);
            while ($f = $con->obnerFila($st)) { $r[(int) $f['ban_Id']] = trim((string) $f['ban_Nombre']); }
        } catch (\Throwable $e) {
            error_log('[informes] no se pudieron leer los bancos: ' . $e->getMessage());
        }
        return $r;
    }

    /** Las opciones de un filtro de lista (las de la base se leen aquí). */
    public static function opcionesFiltro($con, array $filtro)
    {
        if (($filtro['opcionesFn'] ?? '') === 'bancos') { return self::bancos($con); }
        return $filtro['opciones'] ?? [];
    }

    /** [{v, t}] en el orden del arreglo (un objeto JSON no garantiza orden). */
    private static function _lista(array $opciones)
    {
        $r = [];
        foreach ($opciones as $k => $t) { $r[] = ['v' => (string) $k, 't' => (string) $t]; }
        return $r;
    }

    /**
     * El catálogo para la pantalla, YA recortado a lo que esta sesión puede usar:
     * solo las fuentes que puede ver y, sin el permiso de contacto, sin las
     * columnas de datos personales (ni se enteran de que existen).
     */
    public static function paraPantalla($con, array $ctx)
    {
        $fuentes = [];
        $impuestos = [];
        $claves = self::CLAVES;
        foreach (['ica', 'reteica', 'autorreteica'] as $imp) { $claves[] = $imp . self::SUFIJO_AGRUPADO; }
        foreach ($claves as $clave) {
            // La fuente agrupada de un impuesto usa el permiso de ese impuesto.
            if (empty($ctx['fuentes'][self::claveBase($clave)])) { continue; }
            $f = self::fuente($con, $clave);
            if (!$f) { continue; }
            $columnas = [];
            foreach ($f['columnas'] as $c => $def) {
                if (!self::columnaPermitida($def, $ctx)) { continue; }
                $columnas[] = [
                    'c' => $c, 'etiqueta' => $def['etiqueta'], 'grupo' => $def['grupo'], 'tipo' => $def['tipo'],
                    'ordenable' => $def['orden'] !== false, 'total' => (bool) $def['total'],
                    'contacto' => !empty($def['sens']), 'ayuda' => (string) ($def['ayuda'] ?? ''),
                ];
            }
            $filtros = [];
            foreach ($f['filtros'] as $c => $def) {
                $filtros[] = [
                    'c' => $c, 'etiqueta' => $def['etiqueta'], 'grupo' => $def['grupo'], 'tipo' => $def['tipo'],
                    'ops' => $def['ops'], 'opciones' => $def['tipo'] === 'lista' ? self::_lista(self::opcionesFiltro($con, $def)) : null,
                    'min' => $def['min'], 'max' => $def['max'], 'ayuda' => (string) $def['ayuda'],
                ];
            }
            $usados = [];
            foreach ($columnas as $c) { $usados[$c['grupo']] = true; }
            $grupos = [];
            foreach ($f['grupos'] as $g => $et) { if (isset($usados[$g])) { $grupos[] = ['clave' => $g, 'etiqueta' => $et]; } }
            $usadosF = [];
            foreach ($filtros as $x) { $usadosF[$x['grupo']] = true; }
            $gruposF = [];
            foreach ($f['gruposFiltro'] as $g => $et) { if (isset($usadosF[$g])) { $gruposF[] = ['clave' => $g, 'etiqueta' => $et]; } }
            $fuentes[$clave] = [
                'clave' => $clave, 'etiqueta' => $f['etiqueta'], 'modo' => $f['modo'], 'impuesto' => $f['impuesto'],
                'unidad' => $f['unidad'], 'grupos' => $grupos, 'columnas' => $columnas,
                'gruposFiltros' => $gruposF, 'filtros' => $filtros,
                'buscable' => !empty($f['buscar']),
            ];
            if ($f['modo'] === 'declaracion') { $impuestos[] = ['clave' => $clave, 'etiqueta' => $f['etiqueta']]; }
        }
        $modos = [];
        foreach (self::MODOS as $clave => $m) {
            $hay = false;
            foreach ($fuentes as $f) { if ($f['modo'] === $clave) { $hay = true; } }
            if ($hay) { $modos[] = ['clave' => $clave, 'etiqueta' => $m['etiqueta'], 'descripcion' => $m['descripcion']]; }
        }
        return ['modos' => $modos, 'impuestos' => $impuestos, 'fuentes' => $fuentes];
    }
}
