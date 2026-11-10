<?php
namespace erpsoftsas;

require_once __DIR__ . '/class.informeCatalogo.php';
require_once __DIR__ . '/class.informeConstructor.php';

/**
 * LAS PLANTILLAS DEL INFORME GENERAL (informes dinámicos, migración 056).
 *
 * Dos clases de plantilla:
 *
 * DEL SISTEMA, en código (aquí abajo). Son inmutables: no están en la base, así
 * que nadie las pisa ni las borra, y una mejora llega con el despliegue en los
 * cuatro municipios sin migración. "Guardar" sobre una de ellas siempre es
 * "guardar con otro nombre". Una plantilla del sistema no es de un impuesto: la
 * misma sirve para los tres y se materializa para el que se pida (las columnas
 * que ese impuesto no tiene, como el mes en el ICA, se quitan sin ruido).
 *
 * GUARDADAS, en ind_informe_plantillas (migración 056), una tabla por base: cada
 * municipio tiene la suya, así que "compartida" es compartida con quienes usan
 * el Informe general en ese municipio. Son PRIVADAS (solo su dueño) o del
 * MUNICIPIO (las ven todos).
 *
 * QUIÉN PUEDE QUÉ
 *   ver         las del sistema, las compartidas y las propias.
 *   guardar     cualquiera con el Informe general; para compartir con todos hace
 *               falta alcaldia.informes.plantillas.
 *   modificar y borrar   su dueño, o quien tenga alcaldia.informes.plantillas
 *               sobre una compartida. Una plantilla PRIVADA solo la toca su dueño
 *               (ni el administrador: ni siquiera se ve).
 *
 * Una plantilla nunca da permisos: se vuelve a autorizar con los de quien la
 * ejecuta. Si la ejecuta alguien sin acceso a una columna (datos de contacto),
 * esa columna se quita y se avisa.
 *
 * Borrar es lógico (ipl_Activo = 0): el índice único solo cuenta las activas, así
 * que el nombre queda libre.
 */
class InformePlantillas
{
    const MAX_NOMBRE      = 100;
    const MAX_DESCRIPCION = 300;
    const VISIBILIDADES   = ['PRIVADA', 'MUNICIPIO'];

    private static $hayTabla = null;

    public static function olvidar()
    {
        self::$hayTabla = null;
    }

    /** ¿Ya corrió la 056 en esta base? */
    public static function hayTabla($con)
    {
        if (self::$hayTabla === null) {
            try {
                $f = $con->obnerFila($con->consultar("SELECT OBJECT_ID('dbo.ind_informe_plantillas', 'U') AS o", []));
                self::$hayTabla = !empty($f['o']);
            } catch (\Throwable $e) {
                self::$hayTabla = false;
            }
        }
        return self::$hayTabla;
    }

    /* =====================================================================
     * LAS DEL SISTEMA
     * ===================================================================== */

    /**
     * Las columnas del Informe rápido (extensiones/informePagos.php) con sus
     * mismos encabezados: quien ya lo usa las reconoce, y la prueba de oráculo
     * compara esta plantilla contra él.
     */
    private static function _columnasRapido()
    {
        return [['documento', 'NIT / Documento'], 'contribuyente', 'anio', 'periodo', ['numero', 'N° declaración'],
                ['fechaPresentacion', 'Presentada'], 'estado', 'fechaPago', 'banco', 'cuentaContable',
                ['canal', 'Medio'], ['valorDeclarado', 'Valor a pagar'], 'valorPagado', ['intereses', 'Intereses']];
    }

    private static function _definiciones()
    {
        $todos = ['ica', 'reteica', 'autorreteica'];
        return [
            'todas' => [
                'nombre' => 'Todas las declaraciones presentadas',
                'descripcion' => 'Las declaraciones presentadas, pagadas o no, con su estado, su pago y los intereses. Es el «Todas» del Informe rápido.',
                'modo' => 'declaracion', 'fuentes' => $todos,
                'filtros' => [['c' => 'estado', 'op' => 'in', 'v' => InformeCatalogo::ESTADOS_PRESENTADA]],
                'columnas' => self::_columnasRapido(),
                'orden' => [['c' => 'fechaPresentacion', 'd' => 'asc'], ['c' => 'numero', 'd' => 'asc']],
            ],
            'sinpagar' => [
                'nombre' => 'Presentadas sin pagar',
                'descripcion' => 'Lo que falta cobrar: presentadas con valor, sin pagar y sin una corrección presentada encima. Es el «Sin pagar» del Informe rápido.',
                'modo' => 'declaracion', 'fuentes' => $todos,
                'filtros' => [['c' => 'estado', 'op' => 'in', 'v' => ['sinpagar']]],
                'columnas' => self::_columnasRapido(),
                'orden' => [['c' => 'fechaPresentacion', 'd' => 'asc'], ['c' => 'numero', 'd' => 'asc']],
            ],
            'pagadas' => [
                'nombre' => 'Declaraciones pagadas',
                'descripcion' => 'Las declaraciones pagadas, con la fecha, el banco, la cuenta contable y los intereses. Es el «Pagadas» del Informe rápido.',
                'modo' => 'declaracion', 'fuentes' => $todos,
                'filtros' => [['c' => 'estado', 'op' => 'in', 'v' => ['pagada']]],
                'columnas' => self::_columnasRapido(),
                'orden' => [['c' => 'fechaPago', 'd' => 'asc'], ['c' => 'numero', 'd' => 'asc']],
            ],
            'bancos' => [
                'nombre' => 'Pagos por banco del mes',
                'descripcion' => 'Lo que entró en el mes en curso, por banco: fecha, medio, contribuyente y valor.',
                'modo' => 'declaracion', 'fuentes' => $todos,
                'filtros' => [['c' => 'estado', 'op' => 'in', 'v' => ['pagada']],
                              ['c' => 'fechaPago', 'op' => 'between', 'v' => ['{inicioMes}', '{hoy}']]],
                'columnas' => ['banco', 'fechaPago', 'canal', 'documento', 'contribuyente', 'anio', 'periodo', 'numero', 'valorPagado', 'cuentaContable'],
                'orden' => [['c' => 'banco', 'd' => 'asc'], ['c' => 'fechaPago', 'd' => 'asc']],
            ],
            'intereses' => [
                'nombre' => 'Intereses de mora pagados',
                'descripcion' => 'Las declaraciones pagadas por más de su total a pagar: cuánto se recaudó por mora.',
                'modo' => 'declaracion', 'fuentes' => $todos,
                'filtros' => [['c' => 'estado', 'op' => 'in', 'v' => ['pagada']], ['c' => 'conIntereses', 'op' => '=', 'v' => true]],
                'columnas' => ['documento', 'contribuyente', 'anio', 'periodo', 'numero', 'fechaPago', 'valorDeclarado', 'valorPagado', 'intereses'],
                'orden' => [['c' => 'fechaPago', 'd' => 'asc'], ['c' => 'numero', 'd' => 'asc']],
            ],
            'corregidas' => [
                'nombre' => 'Declaraciones corregidas',
                'descripcion' => 'Las declaraciones que ya tienen una corrección presentada (aunque estén pagadas).',
                'modo' => 'declaracion', 'fuentes' => $todos,
                'filtros' => [['c' => 'corregida', 'op' => '=', 'v' => true]],
                'columnas' => ['documento', 'contribuyente', 'anio', 'periodo', 'numero', 'fechaPresentacion', 'estado', 'valorDeclarado'],
                'orden' => [['c' => 'contribuyente', 'd' => 'asc'], ['c' => 'numero', 'd' => 'asc']],
            ],
            'retenciones' => [
                'nombre' => 'Retenciones por período',
                'descripcion' => 'Lo que retuvo cada agente en cada mes del año en curso, con sanciones, intereses y total a pagar.',
                'modo' => 'declaracion', 'fuentes' => ['reteica'],
                'filtros' => [['c' => 'anio', 'op' => 'in', 'v' => ['{anioActual}']],
                              ['c' => 'estado', 'op' => 'in', 'v' => InformeCatalogo::ESTADOS_PRESENTADA]],
                'columnas' => ['documento', 'contribuyente', 'anio', 'periodo', 'numero', 'fechaPresentacion', 'totalRetenciones', 'sanciones',
                               'interesesDeclarados', 'valorDeclarado', 'pagada', 'fechaPago', 'banco'],
                'orden' => [['c' => 'periodo', 'd' => 'asc'], ['c' => 'contribuyente', 'd' => 'asc']],
            ],
            'autorretenciones' => [
                'nombre' => 'Autorretenciones por bimestre',
                'descripcion' => 'Lo que se autorretuvo en cada bimestre del año en curso, con los ingresos y el total a pagar.',
                'modo' => 'declaracion', 'fuentes' => ['autorreteica'],
                'filtros' => [['c' => 'anio', 'op' => 'in', 'v' => ['{anioActual}']],
                              ['c' => 'estado', 'op' => 'in', 'v' => InformeCatalogo::ESTADOS_PRESENTADA]],
                'columnas' => ['documento', 'contribuyente', 'anio', 'periodo', 'numero', 'fechaPresentacion', 'ingresosBrutos', 'ingresosNetosGravados',
                               'autorretencionIca', 'autorretencionAvisos', 'valorDeclarado', 'pagada', 'fechaPago'],
                'orden' => [['c' => 'periodo', 'd' => 'asc'], ['c' => 'contribuyente', 'd' => 'asc']],
            ],
            'padron' => [
                'nombre' => 'Padrón de contribuyentes (RIT)',
                'descripcion' => 'Los contribuyentes inscritos, con su tipo de persona, municipio, firma del RIT y establecimientos activos.',
                'modo' => 'contribuyente', 'fuentes' => ['contribuyentes'],
                'filtros' => [],
                'columnas' => ['documento', 'contribuyente', 'tipoPersona', 'municipio', 'fechaInscripcion', 'ritFirmado', 'fechaFirmaRit', 'nEstablecimientos', 'conCese'],
                'orden' => [['c' => 'contribuyente', 'd' => 'asc']],
            ],
        ];
    }

    /**
     * Un nombre para comparar: sin tildes, sin mayúsculas, sin signos y con los espacios juntos. Así
     * «Todas las declaraciones presentadas», «todas las declaraciones presentadas.» y «TODAS LAS
     * DECLARACIONES  PRESENTADAS» son el mismo y no se puede suplantar una plantilla del sistema.
     */
    public static function nombreComparable($nombre)
    {
        $n = mb_strtolower((string) $nombre, 'UTF-8');
        $n = strtr($n, ['á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
                        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i', 'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
                        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u', 'ñ' => 'n']);
        $n = preg_replace('/[^a-z0-9]+/', ' ', $n);
        return trim((string) $n);
    }

    /** Los nombres de las del sistema ya normalizados (nombreComparable), para que nadie guarde otra con el mismo. */
    private static function _nombresReservados()
    {
        $r = [];
        foreach (self::_definiciones() as $d) { $r[self::nombreComparable($d['nombre'])] = true; }
        return $r;
    }

    /** La configuración de una del sistema para una fuente (impuesto), con las fichas de fecha todavía sin resolver. */
    private static function _configSistema(array $d, $fuente)
    {
        $columnas = [];
        foreach ($d['columnas'] as $c) {
            $columnas[] = is_array($c) ? ['c' => $c[0], 't' => $c[1]] : ['c' => $c];
        }
        return [
            'v' => InformeCatalogo::VERSION, 'modo' => $d['modo'],
            'impuesto' => $d['modo'] === 'declaracion' ? $fuente : null,
            'filtros' => $d['filtros'], 'columnas' => $columnas, 'orden' => $d['orden'],
        ];
    }

    /* =====================================================================
     * LISTAR Y ABRIR
     * ===================================================================== */

    private static function _meta($id, $nombre, $descripcion, $modo, $fuentes, $fuente, $sistema, $visibilidad, $propia, $dueno, $puedeEditar, $actualizada)
    {
        return [
            'id' => (string) $id, 'nombre' => $nombre, 'descripcion' => $descripcion, 'modo' => $modo,
            'fuente' => $fuente, 'fuentes' => $fuentes, 'sistema' => $sistema, 'visibilidad' => $visibilidad,
            'propia' => $propia, 'dueno' => $dueno, 'puedeEditar' => $puedeEditar, 'puedeBorrar' => $puedeEditar,
            'actualizada' => $actualizada,
        ];
    }

    private static function _modoDe($fuente)
    {
        if (InformeCatalogo::esAgrupada($fuente)) { return 'agrupado'; }
        return $fuente === 'contribuyentes' ? 'contribuyente' : 'declaracion';
    }

    /** ¿Esta sesión puede usar la fuente (permiso) y existe en esta base? La agrupada usa el permiso de su impuesto. */
    private static function _usable($con, array $ctx, $fuente)
    {
        return !empty($ctx['fuentes'][InformeCatalogo::claveBase($fuente)]) && InformeCatalogo::fuente($con, $fuente) !== null;
    }

    /**
     * Las que esta sesión puede ver: primero las del sistema, luego las
     * compartidas y las propias. Se omiten las de una fuente que no puede usar.
     */
    public static function listar($con, array $ctx)
    {
        $lista = [];
        foreach (self::_definiciones() as $slug => $d) {
            $fuentes = array_values(array_filter($d['fuentes'], function ($f) use ($con, $ctx) { return self::_usable($con, $ctx, $f); }));
            if (!$fuentes) { continue; }
            $lista[] = self::_meta('sistema:' . $slug, $d['nombre'], $d['descripcion'], $d['modo'], $fuentes, $fuentes[0],
                                   true, 'SISTEMA', false, null, false, null);
        }
        if (!self::hayTabla($con)) { return $lista; }

        $st = $con->consultar(
            "SELECT p.ipl_Id, p.ipl_Nombre, p.ipl_Descripcion, p.ipl_Fuente, p.ipl_Visibilidad, p.ipl_IdUsuario,
                    CONVERT(VARCHAR(19), COALESCE(p.ipl_FechaActualizacion, p.ipl_FechaCreacion), 126) AS actualizada,
                    LTRIM(RTRIM(ISNULL(u.usu_Nombres, '') + ' ' + ISNULL(u.usu_Apellidos, ''))) AS dueno
               FROM ind_informe_plantillas p
               LEFT JOIN conf_usuarios u ON u.usu_Id = p.ipl_IdUsuario
              WHERE p.ipl_Activo = 1 AND (p.ipl_Visibilidad = 'MUNICIPIO' OR p.ipl_IdUsuario = ?)
              ORDER BY CASE WHEN p.ipl_Visibilidad = 'MUNICIPIO' THEN 0 ELSE 1 END, p.ipl_Nombre, p.ipl_Id",
            [(int) $ctx['usuario']]
        );
        while ($r = $con->obnerFila($st)) {
            $fuente = (string) $r['ipl_Fuente'];
            if (!self::_usable($con, $ctx, $fuente)) { continue; }
            $propia = (int) $r['ipl_IdUsuario'] === (int) $ctx['usuario'];
            $puede = $propia || ($r['ipl_Visibilidad'] === 'MUNICIPIO' && !empty($ctx['plantillas']));
            $lista[] = self::_meta((int) $r['ipl_Id'], (string) $r['ipl_Nombre'], (string) $r['ipl_Descripcion'], self::_modoDe($fuente),
                                   [$fuente], $fuente, false, (string) $r['ipl_Visibilidad'], $propia, trim((string) $r['dueno']),
                                   $puede, (string) $r['actualizada']);
        }
        return $lista;
    }

    /** Una fila guardada que esta sesión puede ver (propia o compartida), o null. */
    private static function _fila($con, array $ctx, $id)
    {
        if (!self::hayTabla($con) || !preg_match('/^\d{1,9}$/', (string) $id)) { return null; }
        $r = $con->obnerFila($con->consultar(
            "SELECT p.ipl_Id, p.ipl_Nombre, p.ipl_Descripcion, p.ipl_Fuente, p.ipl_Config, p.ipl_Visibilidad, p.ipl_IdUsuario,
                    CONVERT(VARCHAR(19), COALESCE(p.ipl_FechaActualizacion, p.ipl_FechaCreacion), 126) AS actualizada,
                    LTRIM(RTRIM(ISNULL(u.usu_Nombres, '') + ' ' + ISNULL(u.usu_Apellidos, ''))) AS dueno
               FROM ind_informe_plantillas p
               LEFT JOIN conf_usuarios u ON u.usu_Id = p.ipl_IdUsuario
              WHERE p.ipl_Id = ? AND p.ipl_Activo = 1 AND (p.ipl_Visibilidad = 'MUNICIPIO' OR p.ipl_IdUsuario = ?)",
            [(int) $id, (int) $ctx['usuario']]
        ));
        return $r ?: null;
    }

    private static function _metaDeFila(array $r, array $ctx)
    {
        $fuente = (string) $r['ipl_Fuente'];
        $propia = (int) $r['ipl_IdUsuario'] === (int) $ctx['usuario'];
        $puede = $propia || ($r['ipl_Visibilidad'] === 'MUNICIPIO' && !empty($ctx['plantillas']));
        return self::_meta((int) $r['ipl_Id'], (string) $r['ipl_Nombre'], (string) $r['ipl_Descripcion'], self::_modoDe($fuente),
                           [$fuente], $fuente, false, (string) $r['ipl_Visibilidad'], $propia, trim((string) $r['dueno']),
                           $puede, (string) $r['actualizada']);
    }

    /**
     * Una plantilla lista para cargar en la pantalla: su configuración ya validada
     * con los permisos de quien la abre, y los avisos de lo que se quitó.
     *
     * @param string|null $fuente  solo para las del sistema: el impuesto para el que se pide
     * @return array ['plantilla' => meta, 'config' => ..., 'avisos' => [...]]
     */
    public static function obtener($con, array $ctx, $id, $fuente = null)
    {
        $id = (string) $id;
        if (strpos($id, 'sistema:') === 0) {
            $defs = self::_definiciones();
            $slug = substr($id, 8);
            if (!isset($defs[$slug])) { throw new InformeException('La plantilla no existe.'); }
            $d = $defs[$slug];
            $usables = array_values(array_filter($d['fuentes'], function ($f) use ($con, $ctx) { return self::_usable($con, $ctx, $f); }));
            if (!$usables) {
                throw InformeException::permiso('Su rol no tiene permiso para ver ninguno de los impuestos de esta plantilla. Pídaselo al administrador.');
            }
            $fuente = ($fuente !== null && in_array($fuente, $usables, true)) ? $fuente : $usables[0];
            $v = InformeConstructor::validar($con, self::_configSistema($d, $fuente), $ctx, 'sistema');
            return [
                'plantilla' => self::_meta($id, $d['nombre'], $d['descripcion'], $d['modo'], $usables, $fuente, true, 'SISTEMA', false, null, false, null),
                'config' => $v['config'], 'avisos' => [],
            ];
        }

        $r = self::_fila($con, $ctx, $id);
        if (!$r) { throw new InformeException('La plantilla no existe o ya no está disponible.'); }
        $cfg = json_decode((string) $r['ipl_Config'], true, 8);
        if (!is_array($cfg)) { throw new InformeException('La plantilla está dañada y no se puede abrir. Bórrela y vuelva a guardarla.'); }
        // Tolerante: el catálogo cambia con el tiempo; lo que ya no existe se quita y se avisa.
        $v = InformeConstructor::validar($con, $cfg, $ctx, 'tolerante');
        return ['plantilla' => self::_metaDeFila($r, $ctx), 'config' => $v['config'], 'avisos' => $v['avisos']];
    }

    /* =====================================================================
     * GUARDAR Y BORRAR
     * ===================================================================== */

    private static function _texto($t, $max, $que, $articulo, $obligatorio)
    {
        $limpio = is_string($t) ? preg_replace('/\s+/u', ' ', $t) : '';
        if ($limpio === null) { throw new InformeException(ucfirst($articulo) . ' ' . $que . ' no es válido.'); }
        $limpio = trim($limpio);
        if ($limpio === '') {
            if ($obligatorio) { throw new InformeException('Escriba ' . $articulo . ' ' . $que . ' de la plantilla.'); }
            return '';
        }
        // Texto plano: sin < ni > (un nombre con HTML ya ejecutó código en el panel de Roles).
        if (preg_match('/[<>\x00-\x1F\x7F]/', $limpio)) {
            throw new InformeException(ucfirst($articulo) . ' ' . $que . ' de la plantilla no puede llevar los signos < ni >.');
        }
        // NVARCHAR(n) cuenta unidades UTF-16: un carácter fuera del plano básico (un emoji) ocupa dos. Medir
        // en caracteres dejaría pasar un texto que la columna no admite, y se vería como un fallo del servidor.
        if (strlen((string) mb_convert_encoding($limpio, 'UTF-16LE', 'UTF-8')) / 2 > $max) {
            throw new InformeException(ucfirst($articulo) . ' ' . $que . ' de la plantilla puede tener hasta ' . $max . ' caracteres.');
        }
        return $limpio;
    }

    private static function _ahora()
    {
        // Hora de Colombia, con la T de ISO: el servidor SQL está en UTC.
        return (new \DateTime('now', new \DateTimeZone('America/Bogota')))->format('Y-m-d\TH:i:s');
    }

    private static function _esRepetido(\Throwable $e)
    {
        return (bool) preg_match('/\[code\]\s*=>\s*(2601|2627)\b|duplicate key/i', $e->getMessage());
    }

    /**
     * Crea una plantilla (sin id) o modifica una (con id).
     *
     * @param array $d id (opcional), nombre, descripcion (opcional), visibilidad ('PRIVADA' | 'MUNICIPIO'), config (arreglo)
     * @return array la plantilla guardada (meta)
     */
    public static function guardar($con, array $ctx, array $d)
    {
        if (!self::hayTabla($con)) {
            throw new InformeException('Guardar plantillas aún no está disponible en este municipio (falta aplicar la migración 056).');
        }
        $nombre = self::_texto($d['nombre'] ?? '', self::MAX_NOMBRE, 'nombre', 'el', true);
        $descripcion = self::_texto($d['descripcion'] ?? '', self::MAX_DESCRIPCION, 'descripción', 'la', false);
        // Sin decir nada, la plantilla es privada: compartir es una decisión expresa.
        $visibilidadPedida = strtoupper(trim((string) ($d['visibilidad'] ?? '')));
        $visibilidad = $visibilidadPedida === '' ? 'PRIVADA' : $visibilidadPedida;
        if (!in_array($visibilidad, self::VISIBILIDADES, true)) {
            throw new InformeException('Elija si la plantilla es solo suya o de todo el municipio.');
        }
        // En el servidor, no solo en la pantalla: se compara sin tildes, mayúsculas ni signos.
        if (isset(self::_nombresReservados()[self::nombreComparable($nombre)])) {
            throw new InformeException('Ese nombre lo usa una plantilla del sistema. Elija otro.');
        }

        // La configuración se valida como cuando se ejecuta: con los permisos de quien guarda.
        $v = InformeConstructor::validar($con, $d['config'] ?? null, $ctx, 'estricto');
        if (!$v['config']['columnas']) { throw new InformeException('Elija al menos una columna antes de guardar la plantilla.'); }
        $fuente = $v['clave'];
        $json = json_encode($v['config'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $id = $d['id'] ?? null;
        $usuario = (int) $ctx['usuario'];
        $antes = null;
        if ($id !== null && $id !== '') {
            if (strpos((string) $id, 'sistema:') === 0) {
                throw new InformeException('Las plantillas del sistema no se pueden modificar. Guárdela con otro nombre.');
            }
            $antes = self::_fila($con, $ctx, $id);
            if (!$antes) { throw new InformeException('La plantilla no existe o ya no está disponible.'); }
            $propia = (int) $antes['ipl_IdUsuario'] === $usuario;
            if (!$propia && !($antes['ipl_Visibilidad'] === 'MUNICIPIO' && !empty($ctx['plantillas']))) {
                throw InformeException::permiso('Solo quien creó la plantilla, o quien administra las plantillas compartidas, puede modificarla.');
            }
            // Al modificar sin decir la visibilidad se conserva la que tenía; quien no es el dueño
            // no puede volver privada una compartida (el dueño la perdería de vista).
            if ($visibilidadPedida === '') { $visibilidad = $antes['ipl_Visibilidad']; }
            if (!$propia && $visibilidad !== 'MUNICIPIO') {
                throw new InformeException('Una plantilla compartida de otra persona debe seguir siendo del municipio.');
            }
        }
        // Compartir con todos pide el permiso; una que ya era compartida sigue siéndolo sin él.
        $yaEraCompartida = $antes && $antes['ipl_Visibilidad'] === 'MUNICIPIO';
        if ($visibilidad === 'MUNICIPIO' && !$yaEraCompartida && empty($ctx['plantillas'])) {
            throw InformeException::permiso('Su rol no tiene permiso para compartir plantillas con todo el municipio («Compartir plantillas del Informe general»). Pídaselo al administrador.');
        }

        $idActual = $antes ? (int) $antes['ipl_Id'] : 0;

        // El nombre es ÚNICO en el municipio (esta base es de un solo municipio), también frente a las privadas
        // de otras personas: así dos «Cartera» nunca se confunden al compartir una y el nombre dice siempre
        // lo mismo para todos. Lo comparan igual el índice único de la 056 (UX_ipl_nombre) y esta consulta,
        // que da el mensaje claro; el índice cubre dos guardados a la vez.
        $rep = $con->obnerFila($con->consultar(
            "SELECT TOP 1 ipl_Id FROM ind_informe_plantillas WHERE ipl_Activo = 1 AND ipl_Id <> ? AND ipl_Nombre = ?",
            [$idActual, $nombre]
        ));
        if ($rep) { throw new InformeException('Ya existe una plantilla con ese nombre. Elija otro.'); }

        try {
            if ($antes) {
                $con->consultar(
                    "UPDATE ind_informe_plantillas
                        SET ipl_Nombre = ?, ipl_Descripcion = ?, ipl_Fuente = ?, ipl_Config = ?, ipl_VersionConfig = ?,
                            ipl_Visibilidad = ?, ipl_FechaActualizacion = ?, ipl_IdUsuarioActualizo = ?
                      WHERE ipl_Id = ? AND ipl_Activo = 1",
                    [$nombre, $descripcion === '' ? null : $descripcion, $fuente, $json, InformeCatalogo::VERSION, $visibilidad,
                     self::_ahora(), $usuario, $idActual]
                );
                $nuevoId = $idActual;
            } else {
                $f = $con->obnerFila($con->consultar(
                    "INSERT INTO ind_informe_plantillas
                            (ipl_Nombre, ipl_Descripcion, ipl_Fuente, ipl_Config, ipl_VersionConfig, ipl_Visibilidad, ipl_Activo, ipl_IdUsuario, ipl_FechaCreacion)
                     OUTPUT INSERTED.ipl_Id AS id
                     VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)",
                    [$nombre, $descripcion === '' ? null : $descripcion, $fuente, $json, InformeCatalogo::VERSION, $visibilidad,
                     $usuario, self::_ahora()]
                ));
                $nuevoId = (int) ($f['id'] ?? 0);
            }
        } catch (\Throwable $e) {
            // Dos guardados a la vez con el mismo nombre: lo frena el índice único.
            if (self::_esRepetido($e)) { throw new InformeException('Ya existe una plantilla con ese nombre. Elija otro.'); }
            throw $e;
        }
        $r = self::_fila($con, $ctx, $nuevoId);
        if (!$r) { throw new InformeException('No se pudo guardar la plantilla. Intente de nuevo.'); }
        return self::_metaDeFila($r, $ctx);
    }

    /** Borra (lógicamente) una plantilla. */
    public static function borrar($con, array $ctx, $id)
    {
        $id = (string) $id;
        if (strpos($id, 'sistema:') === 0) {
            throw new InformeException('Las plantillas del sistema no se pueden borrar.');
        }
        if (!self::hayTabla($con)) {
            throw new InformeException('Las plantillas guardadas aún no están disponibles en este municipio (falta aplicar la migración 056).');
        }
        $r = self::_fila($con, $ctx, $id);
        if (!$r) { throw new InformeException('La plantilla no existe o ya fue borrada.'); }
        $propia = (int) $r['ipl_IdUsuario'] === (int) $ctx['usuario'];
        if (!$propia && !($r['ipl_Visibilidad'] === 'MUNICIPIO' && !empty($ctx['plantillas']))) {
            throw InformeException::permiso('Solo quien creó la plantilla, o quien administra las plantillas compartidas, puede borrarla.');
        }
        $con->consultar(
            "UPDATE ind_informe_plantillas SET ipl_Activo = 0, ipl_FechaActualizacion = ?, ipl_IdUsuarioActualizo = ?
              WHERE ipl_Id = ? AND ipl_Activo = 1",
            [self::_ahora(), (int) $ctx['usuario'], (int) $r['ipl_Id']]
        );
    }
}
