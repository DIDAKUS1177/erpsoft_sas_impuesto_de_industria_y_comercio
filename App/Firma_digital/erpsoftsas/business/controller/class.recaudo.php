<?php
namespace erpsoftsas;

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER . '/business/class.recaudoAsobancaria.php';
include_once SERVER . '/business/class.pseModulo.php';
include_once SERVER . '/business/class.pagoDeclaracion.php';

/**
 * Conciliacion del recaudo pagado en ventanilla con codigo de barras.
 *
 * El banco entrega un archivo con lo que la gente pago; la Alcaldia lo sube y
 * el sistema marca esas declaraciones como pagadas. Es el equivalente por
 * ventanilla de lo que el webhook de PlacetoPay hace para PSE.
 *
 * Dos pasos a proposito, y no uno solo como hace predial:
 *
 *   funcion 1  PREVISUALIZAR  lee el archivo y dice que pasaria, sin tocar
 *                             una sola declaracion.
 *   funcion 2  APLICAR        vuelve a leer ese mismo archivo y aplica.
 *   funcion 4  ASIGNAR A MANO un pago que quedo "para revisar" (el numero es
 *                             de dos declaraciones pendientes), a la que
 *                             elija la Alcaldia. Solo despues de aplicar.
 *
 * LOS TRES MODULOS. El recibo de pago y el formulario de retencion y de
 * autorretencion llevan el mismo EAN que el ICA y su numero como referencia,
 * asi que el archivo del banco trae pagos de los tres. Antes solo se buscaban
 * en el ICA: un pago de retencion nunca quedaba registrado.
 *
 * Aplicar a ciegas un archivo de recaudo es irreversible en la practica: deja
 * declaraciones marcadas como pagadas que despues hay que desmarcar a mano.
 * Ver antes lo que va a pasar cuesta un clic.
 */
class ControladorRecaudo extends \erpsoftsas\Cabecera
{
    private $_funcion;
    private $_ok;
    private $_mensaje;

    /**
     * Donde quedan los archivos subidos.
     *
     * En Plesk esta ruta cae fuera de la raiz web. En el contenedor Docker NO:
     * el docroot es /var/www/html y la carpeta queda colgando de el, alcanzable
     * por URL. Comprobado -no supuesto-, que es el mismo error que ya se
     * cometio con los anexos. Por eso al crearla se le escribe un .htaccess que
     * niega todo, y se verifico que responde 403 tanto para el listado como
     * para un archivo concreto.
     *
     * OJO: .htaccess solo lo respeta Apache. En nginx hay que bloquearla en la
     * configuracion del sitio.
     */
    const CARPETA = '/archivos_recaudo';

    const TIPOS_PERMITIDOS = ['txt', 'asc', 'rec', 'dat'];
    const TAMANO_MAXIMO    = 20971520; // 20 MB

    public static function run()
    {
        $_obj = new self();
        $_obj->_funcion = isset($_POST['funcion']) ? $_POST['funcion'] : null;

        // Esto es potestad exclusiva de la Alcaldia: marca declaraciones como
        // pagadas. Un contribuyente no puede acercarse a este endpoint.
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }
        $rol = isset($_SESSION['id_Rol']) ? (int) $_SESSION['id_Rol'] : 0;

        if (empty($_SESSION['id_usuario']) || !in_array($rol, [1, 2], true)) {
            header('Content-type: application/json');
            echo json_encode([
                'ok' => 0,
                'mensaje' => 'Solo la Alcaldía puede cargar archivos de recaudo.',
                'datos' => [],
                // Sesión vencida: dist/menu.php lleva al login con un aviso.
                'sinSesion' => empty($_SESSION['id_usuario']) ? 1 : 0,
            ]);
            return;
        }

        try {
            $respuesta = null;
            switch ($_obj->_funcion) {
                case 1:
                    $respuesta = $_obj->_previsualizar();
                    break;
                case 2:
                    $respuesta = $_obj->_aplicar();
                    break;
                case 3:
                    $respuesta = $_obj->_historial();
                    break;
                case 4:
                    $respuesta = $_obj->_asignarAMano();
                    break;
                default:
                    throw new \Exception('Función no válida');
            }

            header('Content-type: application/json');
            echo json_encode([
                'ok' => $_obj->_ok, 'mensaje' => $_obj->_mensaje, 'datos' => $respuesta,
            ]);

        } catch (\Exception $e) {
            header('Content-type: application/json');
            echo json_encode([
                'ok' => 0,
                'mensaje' => 'No se pudo procesar la solicitud. Verifique el archivo e intente de nuevo.',
                'datos' => [],
            ]);
        }
    }

    /* ====================================================================
       PASO 1 — leer el archivo y decir que pasaria
       ==================================================================== */
    private function _previsualizar()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $guardado = $this->_recibirArchivo();
        if (!$guardado) { return []; }   // _recibirArchivo ya puso el mensaje

        $lectura = \erpsoftsas\RecaudoAsobancaria::leer($guardado['ruta']);

        if (!$lectura['ok']) {
            @unlink($guardado['ruta']);
            $this->_ok = 0;
            $this->_mensaje = $lectura['error'];
            return [];
        }

        // Mismo archivo subido dos veces: se ataja aqui, antes de tocar nada.
        // Predial lo atajaba pago por pago, que ya es tarde.
        $repetido = $con->obnerFila($con->consultar(
            "SELECT arc_Id, arc_Nombre, arc_FechaCarga
               FROM ind_archivos_asobancaria WHERE arc_Hash = ?",
            [$guardado['hash']]
        ));

        $analisis = $this->_analizar($con, $lectura);
        $analisis['archivo'] = [
            'nombre' => $guardado['nombre'],
            'hash'   => $guardado['hash'],
            'ruta'   => basename($guardado['ruta']),
        ];
        $analisis['yaSubido'] = $repetido ? [
            'nombre' => $repetido['arc_Nombre'],
            'fecha'  => $repetido['arc_FechaCarga'] instanceof \DateTime
                        ? $repetido['arc_FechaCarga']->format('Y-m-d H:i')
                        : (string) $repetido['arc_FechaCarga'],
        ] : null;

        $this->_ok = 1;
        $this->_mensaje = $repetido
            ? 'Este archivo ya se había cargado antes. Revise el detalle antes de continuar.'
            : 'Archivo leído. Revise el resumen antes de aplicar.';

        return $analisis;
    }

    /* ====================================================================
       PASO 2 — aplicar
       ==================================================================== */
    private function _aplicar()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $nombreEnDisco = basename((string) ($_POST['archivo'] ?? ''));
        $ruta = $this->_carpeta() . DIRECTORY_SEPARATOR . $nombreEnDisco;

        if ($nombreEnDisco === '' || !is_file($ruta)) {
            $this->_ok = 0;
            $this->_mensaje = 'El archivo ya no está disponible. Vuelva a cargarlo.';
            return [];
        }

        $lectura = \erpsoftsas\RecaudoAsobancaria::leer($ruta);
        if (!$lectura['ok']) {
            $this->_ok = 0;
            $this->_mensaje = $lectura['error'];
            return [];
        }

        // Sin fecha de pago no hay con qué reconocer un pago ya aplicado (ver
        // _contarAplicados): se pide otro archivo en vez de aplicar a ciegas.
        $fechaPago = \erpsoftsas\RecaudoAsobancaria::fechaAIso($lectura['encabezado']['fecha']);
        if ($fechaPago === null) {
            $this->_ok = 0;
            $this->_mensaje = 'El archivo no trae una fecha de pago válida en el encabezado. Pida uno nuevo al banco.';
            return [];
        }

        $hash = hash_file('sha256', $ruta);

        /*
         * TODO O NADA, Y DE A UNO. Aplicar un archivo y asignar a mano (función
         * 4) comparten un candado, así que dos funcionarios no aplican el mismo
         * pago a la vez; y todo va en una transacción: si algo falla a mitad,
         * no queda la mitad de los pagos aplicada ni el archivo sin registrar
         * (reintentarlo lo habría vuelto a leer como nuevo).
         */
        try {
            $con->begin();

            if (!$this->_candado($con)) {
                $con->rollback();
                $this->_ok = 0;
                $this->_mensaje = 'Otra persona está aplicando pagos en este momento. Intente de nuevo en un minuto.';
                return [];
            }

            if ($con->obnerFila($con->consultar(
                    "SELECT arc_Id FROM ind_archivos_asobancaria WHERE arc_Hash = ?", [$hash]))) {
                $con->rollback();
                $this->_ok = 0;
                $this->_mensaje = 'Este archivo ya fue aplicado antes. No se hizo nada. '
                                . 'Lo que quedó pendiente se asigna desde la tabla.';
                return ['yaAplicado' => 1];
            }

            // Dentro del candado: lo que se aplica es lo que hay en la base AHORA.
            $analisis    = $this->_analizar($con, $lectura);
            $idBanco     = $analisis['banco']['id'] ?? null;
            $nombreBanco = $analisis['banco']['nombre'] ?? '';

            /*
             * El pago lo registra PagoDeclaracion, que es el unico sitio que toca
             * esas columnas. Antes este UPDATE llenaba cuatro y el de PSE cinco, y
             * dos no las llenaba nadie: la misma declaracion quedaba con datos
             * distintos segun por donde entrara la plata.
             *
             * Aqui SI se manda la fecha de pago: la del archivo del banco es la de
             * ventanilla, que puede ser de dias atras. dec_FechaRealPago guarda
             * aparte cuando se cargo el archivo.
             */
            $aplicados = 0;
            foreach ($analisis['aplicables'] as $k => $item) {
                $marcada = \erpsoftsas\PagoDeclaracion::registrar($con, $item['id'], [
                    'valor'     => $item['valor'],
                    'banco'     => $nombreBanco,
                    'via'       => \erpsoftsas\PagoDeclaracion::VIA_RECAUDO,
                    'fechaPago' => $fechaPago,
                ], \erpsoftsas\PseModulo::get($item['modulo']));
                // La pantalla marca "Aplicado" solo lo que de verdad quedo; lo
                // demas conserva su boton para asignarlo a mano.
                $analisis['aplicables'][$k]['aplicado'] = $marcada ? 1 : 0;
                if ($marcada) { $aplicados++; }
            }

            $con->consultar(
                "INSERT INTO ind_archivos_asobancaria
                    (arc_IdUsuario, arc_Nombre, arc_Ruta, arc_Hash, arc_IdBanco, arc_FechaPago,
                     arc_TotalRegistros, arc_TotalAplicados, arc_TotalYaPagados, arc_TotalFallidos,
                     arc_ValorControl, arc_ValorSumado, arc_RegistrosControl, arc_Descripcion)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [
                    (int) $_SESSION['id_usuario'],
                    $_POST['nombre'] ?? $nombreEnDisco,
                    $nombreEnDisco,
                    $hash,
                    $idBanco,
                    $fechaPago,
                    $lectura['sumas']['registros'],
                    $aplicados,
                    count($analisis['yaPagadas']),
                    // No aplicados: sin declaración, sin presentar y para revisar a mano.
                    count($analisis['sinDeclaracion']) + count($analisis['valorNoCuadra'])
                        + count($analisis['sinPresentar']) + count($analisis['revisar']),
                    $lectura['control']['valor'],
                    $lectura['sumas']['valor'],
                    $lectura['control']['registros'],
                    $this->_resumenTexto($analisis, $aplicados),
                ]
            );

            $con->commit();

        } catch (\Throwable $e) {
            try { $con->rollback(); } catch (\Throwable $e2) { /* ya cerrada */ }
            error_log('[recaudo] no se pudo aplicar ' . $nombreEnDisco . ': ' . $e->getMessage());
            $this->_ok = 0;
            $this->_mensaje = 'No se pudo aplicar el archivo: no quedó ningún pago registrado. Intente de nuevo.';
            return [];
        }

        $this->_ok = 1;
        $this->_mensaje = "Se aplicaron $aplicados pagos.";

        $analisis['aplicados'] = $aplicados;
        return $analisis;
    }

    /* ====================================================================
       El corazon: cruzar lo que trae el archivo con las declaraciones
       ==================================================================== */
    private function _analizar($con, $lectura)
    {
        $codigoBanco = $lectura['encabezado']['banco'] ?? '';
        $banco = $con->obnerFila($con->consultar(
            "SELECT ban_Id, ban_Nombre FROM ind_bancos WHERE ban_Asobancaria = ?", [$codigoBanco]
        ));

        $aplicables = [];
        $yaPagadas = [];
        $sinDeclaracion = [];
        $valorNoCuadra = [];
        $sinPresentar = [];
        $revisar = [];

        /*
         * LOS TRES MÓDULOS USAN LOS MISMOS NÚMEROS.
         *
         * Cada módulo lleva su propio consecutivo con el mismo formato
         * (2026000001 existe en los tres), y sus recibos y formularios llevan el
         * mismo EAN con el número como referencia. El banco no distingue. Por
         * eso se busca el número en los tres y se aplica solo cuando hay UNA
         * declaración presentada y sin pagar que pueda haber originado el pago:
         * las pagadas o sin presentar no pudieron. Con dos o más, no se adivina:
         * va a "Revisar a mano", con las candidatas, y la Alcaldía lo asigna
         * (función 4) después de aplicar el archivo.
         *
         * Antes solo se buscaba en el ICA: un pago de retención se tomaba por el
         * de la ICA ajena con ese número, o no se registraba nunca.
         */
        $fechaArchivo = \erpsoftsas\RecaudoAsobancaria::fechaAIso($lectura['encabezado']['fecha'] ?? '');
        $vistas   = [];   // "referencia|valor" => líneas de este archivo con ese pago
        $destinos = [];   // "modulo:id" => ya la paga una línea anterior de este archivo

        foreach ($lectura['detalles'] as $d) {

            $ref = $d['referencia'];
            if ($ref === '') { $sinDeclaracion[] = ['referencia' => '(vacía)', 'valor' => $d['valor']]; continue; }

            $candidatas = $this->_candidatas($con, $ref);

            /*
             * EL MISMO PAGO NO SE APLICA DOS VECES. Si el banco reenvía el
             * archivo, o se descarga otra vez del portal (otro hash), sus pagos
             * ya están aplicados: mismo número, mismo valor, misma fecha, por
             * recaudo. Se cuentan las líneas de este archivo con ese pago y las
             * declaraciones que ya lo tienen; las que sobran, ya están. Sin
             * esto, con la ICA de ese número ya pagada, el pago repetido caía en
             * la retención homónima de otro contribuyente.
             */
            $clavePago = $ref . '|' . number_format((float) $d['valor'], 2, '.', '');
            $vistas[$clavePago] = ($vistas[$clavePago] ?? 0) + 1;
            if ($fechaArchivo !== null
                && $vistas[$clavePago] <= $this->_contarAplicados($candidatas, $d['valor'], $fechaArchivo)) {
                $yaPagadas[] = ['referencia' => $ref, 'valor' => $d['valor'], 'etiqueta' => 'este mismo pago ya estaba aplicado'];
                continue;
            }

            $pendientes = array_values(array_filter($candidatas, function ($c) {
                return $c['presentada'] && !$c['pagada'];
            }));
            $pagadas = array_values(array_filter($candidatas, function ($c) { return $c['pagada']; }));

            /*
             * Se aplica solo lo inequívoco: UNA pendiente, ninguna homónima ya
             * pagada y ninguna otra línea de este archivo pagándole a la misma.
             * Con una homónima pagada puede ser el pago repetido de esa, y con
             * dos líneas para la misma declaración, un pago doble: en los dos
             * casos decide la Alcaldía con el comprobante (función 4).
             */
            if (count($pendientes) === 1 && !$pagadas) {
                $c = $pendientes[0];
                $destino = $c['modulo'] . ':' . $c['id'];
                if (!isset($destinos[$destino])) {
                    $destinos[$destino] = true;
                    $aplicables[] = [
                        'modulo'        => $c['modulo'],
                        'etiqueta'      => $c['etiqueta'],
                        'id'            => $c['id'],
                        'referencia'    => $ref,
                        'valor'         => $d['valor'],
                        'presentada'    => true,
                        'contribuyente' => $c['contribuyente'],
                        'documento'     => $c['documento'],
                        'total'         => $c['total'],
                    ];
                    continue;
                }
                $revisar[] = [
                    'referencia' => $ref,
                    'valor'      => $d['valor'],
                    'motivo'     => 'Otra línea de este archivo ya paga esa declaración: puede ser un pago repetido. '
                                  . 'Revíselo con el comprobante del banco.',
                    'candidatas' => $pendientes,
                ];
                continue;
            }

            if ($pendientes) {
                $revisar[] = [
                    'referencia' => $ref,
                    'valor'      => $d['valor'],
                    'motivo'     => count($pendientes) > 1
                        ? 'El número es de ' . count($pendientes) . ' declaraciones presentadas sin pagar ('
                          . implode(', ', array_map(function ($c) { return $c['etiqueta']; }, $pendientes))
                          . '): no se sabe a cuál corresponde el pago.'
                        : 'El número también es de una declaración que ya estaba pagada ('
                          . implode(', ', array_map(function ($c) { return $c['etiqueta']; }, $pagadas))
                          . '): puede ser su pago repetido. Revíselo con el comprobante del banco.',
                    'candidatas' => $pendientes,
                ];
                continue;
            }

            if (!$candidatas) {
                $sinDeclaracion[] = ['referencia' => $ref, 'valor' => $d['valor']];
                continue;
            }

            // Ninguna pendiente: o ya estaban pagadas, o no se han presentado.
            if ($pagadas) {
                $yaPagadas[] = ['referencia' => $ref, 'valor' => $d['valor'],
                                'etiqueta' => implode(', ', array_map(function ($c) { return $c['etiqueta']; }, $pagadas))];
                continue;
            }

            /*
             * Una declaracion SIN PRESENTAR no puede tener un pago legitimo
             * por este canal. El codigo de barras de recaudo solo se imprime
             * en declaraciones presentadas (ver extensiones/declaracion.php),
             * asi que el banco no tuvo de donde leer esta referencia.
             *
             * Aplicarla igual dejaba la declaracion en un estado imposible
             * -pagada pero no presentada- y ese es exactamente el que rompe
             * "Corregir": la pantalla la pinta "Pagada" porque mira
             * dec_Pagado, pero corregir exige dec_Estado = 2 y la rechaza.
             * El usuario queda con una declaracion que dice estar pagada y no
             * se puede tocar.
             *
             * Las causas plausibles (referencia mal digitada en ventanilla,
             * numero reutilizado, declaracion revertida despues de imprimir)
             * necesitan criterio humano, asi que esto se REPORTA y no se
             * aplica. La plata no se pierde: queda listada para que la
             * Alcaldia la concilie a mano.
             */
            $sinPresentar[] = [
                'referencia' => $ref,
                'valor'      => $d['valor'],
                'etiqueta'   => implode(', ', array_map(function ($c) { return $c['etiqueta']; }, $candidatas)),
            ];
        }

        return [
            'encabezado' => $lectura['encabezado'],
            'ean'        => $lectura['ean'],
            'banco'      => $banco
                ? ['id' => (int) $banco['ban_Id'], 'nombre' => $banco['ban_Nombre'], 'codigo' => $codigoBanco]
                // Un codigo que no esta en el catalogo no se adivina: se
                // reporta. En los archivos reales de predial aparecieron dos
                // (030 y 740) que no estaban en la tabla.
                : ['id' => null, 'nombre' => '', 'codigo' => $codigoBanco, 'desconocido' => true],
            'control'         => $lectura['control'],
            'sumas'           => $lectura['sumas'],
            'aplicables'      => $aplicables,
            'yaPagadas'       => $yaPagadas,
            'sinDeclaracion'  => $sinDeclaracion,
            'sinPresentar'    => $sinPresentar,
            'revisar'         => $revisar,
            'valorNoCuadra'   => $valorNoCuadra,
        ];
    }

    /** Nombre del modulo como se le dice a la Alcaldia. */
    private static function _etiqueta($clave)
    {
        return ['ica' => 'ICA', 'reteica' => 'retención', 'autorreteica' => 'autorretención'][$clave] ?? $clave;
    }

    private $_tablas = [];

    /** ¿Existe la tabla? Se pregunta una vez por petición: un archivo trae
     *  cientos de líneas y cada una busca en los tres módulos. */
    private function _existeTabla($con, $tabla)
    {
        if (!array_key_exists($tabla, $this->_tablas)) {
            $hay = $con->obnerFila($con->consultar("SELECT OBJECT_ID(?, 'U') AS o", ['dbo.' . $tabla]));
            $this->_tablas[$tabla] = !empty($hay['o']);
        }
        return $this->_tablas[$tabla];
    }

    /**
     * Un solo recaudo a la vez: aplicar un archivo y asignar a mano toman este
     * candado dentro de su transacción (se suelta con el commit o el rollback).
     * Sin él, dos funcionarios asignando la misma línea a la vez pasaban los
     * dos la cuenta de "ya aplicado". false si no se consiguió en 20 segundos.
     */
    private function _candado($con)
    {
        $f = $con->obnerFila($con->consultar(
            "SET NOCOUNT ON;
             DECLARE @r INT;
             EXEC @r = sp_getapplock @Resource = 'erp_recaudo_bancario', @LockMode = 'Exclusive',
                                     @LockOwner = 'Transaction', @LockTimeout = 20000;
             SELECT @r AS r;"
        ));
        return isset($f['r']) && (int) $f['r'] >= 0;
    }

    /**
     * Cuántas declaraciones con ese número ya tienen ESE pago aplicado por
     * recaudo: mismo valor y misma fecha de pago. Es lo que dice que una línea
     * del archivo ya se aplicó (reenvío del banco, archivo descargado otra vez,
     * o asignada a mano).
     */
    private function _contarAplicados(array $candidatas, $valor, $fecha)
    {
        $n = 0;
        foreach ($candidatas as $c) {
            if ($c['pagada'] && $c['ruta'] === \erpsoftsas\PagoDeclaracion::VIA_RECAUDO
                && abs($c['valorPago'] - (float) $valor) < 0.005 && $c['fechaPago'] === $fecha) {
                $n++;
            }
        }
        return $n;
    }

    /**
     * Las declaraciones de los tres modulos que llevan ese numero, con lo que
     * hace falta para decidir (presentada, pagada) y para que la Alcaldia elija
     * a mano (contribuyente y total). Tablas y columnas salen de PseModulo.
     */
    private function _candidatas($con, $ref)
    {
        $lista = [];
        foreach (\erpsoftsas\PseModulo::claves() as $clave) {
            $m = \erpsoftsas\PseModulo::get($clave);
            if (!$this->_existeTabla($con, $m['tabla'])) { continue; }
            $p = $m['prefijo'];
            $stmt = $con->consultar(
                "SELECT d.{$m['pk']} AS id, d.{$m['estado']} AS estado,
                        ISNULL(d.{$m['pagado']}, 0) AS pagado, d.{$m['valor']} AS total,
                        d.{$p}_RutaPago AS ruta, ISNULL(d.{$p}_ValorPago, 0) AS valorPago,
                        CONVERT(VARCHAR(10), d.{$p}_FechaPago, 23) AS fechaPago,
                        LTRIM(RTRIM(CONCAT(c.ind_PrimerNombre, ' ', c.ind_SegundoNombre, ' ',
                                           c.ind_PrimerApellido, ' ', c.ind_SegundoApellido))) AS contribuyente,
                        c.ind_NumeroIdentificacion AS documento
                   FROM {$m['tabla']} d
                   LEFT JOIN ind_contribuyentes c ON c.ind_Id = d.{$p}_IdContribuyente
                  WHERE d.{$m['numero']} = ?",
                [$ref]
            );
            while ($f = $con->obnerFila($stmt)) {
                $lista[] = [
                    'modulo'        => $clave,
                    'etiqueta'      => self::_etiqueta($clave),
                    'id'            => (int) $f['id'],
                    'presentada'    => (int) $f['estado'] === 2,
                    'pagada'        => (int) $f['pagado'] === 1,
                    'total'         => (float) $f['total'],
                    'contribuyente' => preg_replace('/\s+/', ' ', (string) $f['contribuyente']),
                    'documento'     => (string) $f['documento'],
                    // Para reconocer un pago ya aplicado (_contarAplicados).
                    'ruta'          => (string) $f['ruta'],
                    'valorPago'     => (float) $f['valorPago'],
                    'fechaPago'     => (string) $f['fechaPago'],
                ];
            }
        }
        return $lista;
    }

    /* ====================================================================
       FUNCION 4 — asignar a mano un pago que quedó pendiente

       Los de "Revisar a mano", y los que al volver a cargar un archivo ya
       aplicado salen como aplicables (se presentó la declaración después, o el
       archivo se aplicó con la versión que solo miraba el ICA).

       Solo sobre un archivo YA APLICADO: si se asignara antes, al aplicar el
       archivo la otra candidata quedaria sola y se le aplicaria el mismo pago.
       Y cada pago una sola vez: se cuentan las lineas del archivo con esa
       referencia y ese valor, y las declaraciones que ya lo tienen aplicado
       (mismo numero, valor, fecha y via); no se aplica mas de lo que el banco
       reporto.
       ==================================================================== */
    private function _asignarAMano()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $nombreEnDisco = basename((string) ($_POST['archivo'] ?? ''));
        $ruta  = $this->_carpeta() . DIRECTORY_SEPARATOR . $nombreEnDisco;
        $ref   = trim((string) ($_POST['referencia'] ?? ''));
        $clave = strtolower(trim((string) ($_POST['modulo'] ?? '')));
        $id    = (int) ($_POST['id'] ?? 0);
        $valor = round((float) ($_POST['valor'] ?? 0), 2);

        if ($nombreEnDisco === '' || !is_file($ruta)) {
            $this->_ok = 0;
            $this->_mensaje = 'El archivo ya no está disponible. Vuelva a cargarlo.';
            return [];
        }
        if ($ref === '' || $id <= 0 || !\erpsoftsas\PseModulo::existe($clave)) {
            $this->_ok = 0;
            $this->_mensaje = 'Faltan datos para asignar el pago.';
            return [];
        }

        $lectura = \erpsoftsas\RecaudoAsobancaria::leer($ruta);
        if (!$lectura['ok']) {
            $this->_ok = 0;
            $this->_mensaje = $lectura['error'];
            return [];
        }
        $fechaPago = \erpsoftsas\RecaudoAsobancaria::fechaAIso($lectura['encabezado']['fecha']);
        if ($fechaPago === null) {
            $this->_ok = 0;
            $this->_mensaje = 'El archivo no trae una fecha de pago válida en el encabezado. Pida uno nuevo al banco.';
            return [];
        }

        $lineas = 0;
        foreach ($lectura['detalles'] as $d) {
            if ($d['referencia'] === $ref && abs((float) $d['valor'] - $valor) < 0.005) { $lineas++; }
        }
        if ($lineas === 0) {
            $this->_ok = 0;
            $this->_mensaje = 'Ese pago no está en el archivo.';
            return [];
        }

        $m = \erpsoftsas\PseModulo::get($clave);

        // Contar, comprobar y registrar bajo el MISMO candado que "Aplicar":
        // dos funcionarios a la vez no pasan los dos la cuenta de "ya aplicado".
        try {
            $con->begin();

            if (!$this->_candado($con)) {
                $con->rollback();
                $this->_ok = 0;
                $this->_mensaje = 'Otra persona está aplicando pagos en este momento. Intente de nuevo en un minuto.';
                return [];
            }

            $archivo = $con->obnerFila($con->consultar(
                "SELECT a.arc_Id, b.ban_Nombre
                   FROM ind_archivos_asobancaria a
                   LEFT JOIN ind_bancos b ON b.ban_Id = a.arc_IdBanco
                  WHERE a.arc_Hash = ?",
                [hash_file('sha256', $ruta)]
            ));
            if (!$archivo) {
                $con->rollback();
                $this->_ok = 0;
                $this->_mensaje = 'Primero aplique el archivo; después se asignan a mano los pagos que quedaron pendientes.';
                return [];
            }

            if ($this->_contarAplicados($this->_candidatas($con, $ref), $valor, $fechaPago) >= $lineas) {
                $con->rollback();
                $this->_ok = 0;
                $this->_mensaje = 'Ese pago ya se aplicó. No se hizo nada.';
                return [];
            }

            $dec = $con->obnerFila($con->consultar(
                "SELECT {$m['pk']} AS id FROM {$m['tabla']}
                  WHERE {$m['pk']} = ? AND {$m['numero']} = ?
                    AND {$m['estado']} = 2 AND ISNULL({$m['pagado']}, 0) = 0",
                [$id, $ref]
            ));
            if (!$dec) {
                $con->rollback();
                $this->_ok = 0;
                $this->_mensaje = 'Esa declaración ya no está presentada y sin pagar. Vuelva a cargar el archivo para ver el estado actual.';
                return [];
            }

            $marcada = \erpsoftsas\PagoDeclaracion::registrar($con, $id, [
                'valor'     => $valor,
                'banco'     => (string) ($archivo['ban_Nombre'] ?? ''),
                'via'       => \erpsoftsas\PagoDeclaracion::VIA_RECAUDO,
                'fechaPago' => $fechaPago,
            ], $m);
            if (!$marcada) {
                $con->rollback();
                $this->_ok = 0;
                $this->_mensaje = 'No se pudo marcar como pagada. Vuelva a cargar el archivo para ver el estado actual.';
                return [];
            }

            // Queda en el historial del archivo: quién lo asignó y a qué.
            $con->consultar(
                "UPDATE ind_archivos_asobancaria
                    SET arc_TotalAplicados = arc_TotalAplicados + 1,
                        arc_TotalFallidos  = CASE WHEN arc_TotalFallidos > 0 THEN arc_TotalFallidos - 1 ELSE 0 END,
                        arc_Descripcion    = CONCAT(arc_Descripcion, N' | A mano: ', ?, N' a ', ?, N' (usuario ', ?, N')')
                  WHERE arc_Id = ?",
                [$ref, self::_etiqueta($clave), (int) $_SESSION['id_usuario'], (int) $archivo['arc_Id']]
            );

            $con->commit();

        } catch (\Throwable $e) {
            try { $con->rollback(); } catch (\Throwable $e2) { /* ya cerrada */ }
            error_log('[recaudo] no se pudo asignar ' . $ref . ': ' . $e->getMessage());
            $this->_ok = 0;
            $this->_mensaje = 'No se pudo aplicar el pago: no quedó ningún cambio. Intente de nuevo.';
            return [];
        }

        $this->_ok = 1;
        $this->_mensaje = 'Pago aplicado a la ' . self::_etiqueta($clave) . ' N° ' . $ref . '.';
        return ['modulo' => $clave, 'id' => $id];
    }

    private function _resumenTexto($a, $aplicados)
    {
        return sprintf(
            'Banco %s (%s). Registros: %d por $%s. Aplicados: %d. Ya pagadas: %d. Sin declaración: %d. Sin presentar: %d. Revisar a mano: %d.',
            $a['banco']['nombre'] ?: '(desconocido)', $a['banco']['codigo'],
            $a['sumas']['registros'], number_format($a['sumas']['valor'], 2, ',', '.'),
            $aplicados, count($a['yaPagadas']), count($a['sinDeclaracion']),
            count($a['sinPresentar'] ?? []), count($a['revisar'] ?? [])
        );
    }

    /* ==================================================================== */

    private function _carpeta()
    {
        $base = dirname(dirname(dirname(__DIR__))) . self::CARPETA;
        if (!is_dir($base)) { @mkdir($base, 0755, true); }

        // Mismo blindaje que la carpeta de anexos: si el despliegue deja esto
        // dentro de la raiz web, .htaccess lo tapa en Apache.
        $htaccess = $base . DIRECTORY_SEPARATOR . '.htaccess';
        if (is_dir($base) && !is_file($htaccess)) {
            @file_put_contents($htaccess, "Require all denied\n");
        }
        return $base;
    }

    private function _recibirArchivo()
    {
        if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            $this->_ok = 0;
            $this->_mensaje = 'No llegó ningún archivo.';
            return null;
        }

        $tmp = $_FILES['archivo']['tmp_name'];
        if (!is_uploaded_file($tmp)) {
            $this->_ok = 0;
            $this->_mensaje = 'El archivo no es una subida válida.';
            return null;
        }

        if ($_FILES['archivo']['size'] > self::TAMANO_MAXIMO) {
            $this->_ok = 0;
            $this->_mensaje = 'El archivo supera los 20 MB.';
            return null;
        }

        $nombre = (string) $_FILES['archivo']['name'];
        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        if (!in_array($ext, self::TIPOS_PERMITIDOS, true)) {
            $this->_ok = 0;
            $this->_mensaje = 'El archivo de recaudo debe ser de texto plano ('
                            . implode(', ', self::TIPOS_PERMITIDOS) . ').';
            return null;
        }

        // El nombre en disco lo genera el servidor; el del usuario solo se
        // guarda para mostrarlo. Mismo criterio que en los anexos.
        $destino = $this->_carpeta() . DIRECTORY_SEPARATOR
                 . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

        if (!move_uploaded_file($tmp, $destino)) {
            $this->_ok = 0;
            $this->_mensaje = 'No se pudo guardar el archivo en el servidor.';
            return null;
        }

        return ['ruta' => $destino, 'nombre' => $nombre, 'hash' => hash_file('sha256', $destino)];
    }

    private function _historial()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
        $stmt = $con->consultar(
            "SELECT TOP 50 a.arc_Id, a.arc_Nombre, a.arc_FechaCarga, a.arc_FechaPago,
                    a.arc_TotalRegistros, a.arc_TotalAplicados, a.arc_TotalYaPagados,
                    a.arc_TotalFallidos, a.arc_Descripcion, b.ban_Nombre
               FROM ind_archivos_asobancaria a
               LEFT JOIN ind_bancos b ON b.ban_Id = a.arc_IdBanco
              ORDER BY a.arc_Id DESC"
        );

        $filas = [];
        while ($f = $con->obnerFila($stmt)) {
            foreach (['arc_FechaCarga', 'arc_FechaPago'] as $k) {
                if (isset($f[$k]) && $f[$k] instanceof \DateTime) {
                    $f[$k] = $f[$k]->format('Y-m-d H:i');
                }
            }
            $filas[] = $f;
        }

        $this->_ok = 1;
        $this->_mensaje = 'Historial consultado';
        return $filas;
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    \erpsoftsas\ControladorRecaudo::run();
}
