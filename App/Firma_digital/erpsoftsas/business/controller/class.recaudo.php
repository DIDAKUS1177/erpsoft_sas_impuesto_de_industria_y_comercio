<?php
namespace erpsoftsas;

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER . '/business/class.recaudoAsobancaria.php';

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

        $hash = hash_file('sha256', $ruta);
        if ($con->obnerFila($con->consultar(
                "SELECT arc_Id FROM ind_archivos_asobancaria WHERE arc_Hash = ?", [$hash]))) {
            $this->_ok = 0;
            $this->_mensaje = 'Este archivo ya fue aplicado antes. No se hizo nada.';
            return [];
        }

        $analisis    = $this->_analizar($con, $lectura);
        $fechaPago   = \erpsoftsas\RecaudoAsobancaria::fechaAIso($lectura['encabezado']['fecha']);
        $idBanco     = $analisis['banco']['id'] ?? null;
        $nombreBanco = $analisis['banco']['nombre'] ?? '';

        $aplicados = 0;
        foreach ($analisis['aplicables'] as $item) {
            $r = $con->consultar(
                "UPDATE ind_declaraciones_ica
                    SET dec_Pagado    = 1,
                        dec_FechaPago = ?,
                        dec_ValorPago = ?,
                        dec_BancoPago = ?
                  WHERE dec_Id = ? AND ISNULL(dec_Pagado, 0) = 0",
                [$fechaPago, $item['valor'], $nombreBanco, $item['dec_Id']]
            );
            if ($r !== false) { $aplicados++; }
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
                count($analisis['sinDeclaracion']) + count($analisis['valorNoCuadra']),
                $lectura['control']['valor'],
                $lectura['sumas']['valor'],
                $lectura['control']['registros'],
                $this->_resumenTexto($analisis, $aplicados),
            ]
        );

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

        foreach ($lectura['detalles'] as $d) {

            $ref = $d['referencia'];
            if ($ref === '') { $sinDeclaracion[] = ['referencia' => '(vacía)', 'valor' => $d['valor']]; continue; }

            // La referencia del codigo de barras es dec_NumeroDeclaracion.
            $dec = $con->obnerFila($con->consultar(
                "SELECT dec_Id, dec_NumeroDeclaracion, dec_Pagado, dec_Estado,
                        dec_TotalCalculo, dec_ValorImpuesto, dec_IdContribuyente
                   FROM ind_declaraciones_ica
                  WHERE dec_NumeroDeclaracion = ?",
                [$ref]
            ));

            if (!$dec) {
                $sinDeclaracion[] = ['referencia' => $ref, 'valor' => $d['valor']];
                continue;
            }

            if ((int) ($dec['dec_Pagado'] ?? 0) === 1) {
                $yaPagadas[] = ['referencia' => $ref, 'valor' => $d['valor'], 'dec_Id' => $dec['dec_Id']];
                continue;
            }

            $aplicables[] = [
                'dec_Id'     => (int) $dec['dec_Id'],
                'referencia' => $ref,
                'valor'      => $d['valor'],
                'presentada' => ((int) ($dec['dec_Estado'] ?? 0) === 2),
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
            'valorNoCuadra'   => $valorNoCuadra,
        ];
    }

    private function _resumenTexto($a, $aplicados)
    {
        return sprintf(
            'Banco %s (%s). Registros: %d por $%s. Aplicados: %d. Ya pagadas: %d. Sin declaración: %d.',
            $a['banco']['nombre'] ?: '(desconocido)', $a['banco']['codigo'],
            $a['sumas']['registros'], number_format($a['sumas']['valor'], 2, ',', '.'),
            $aplicados, count($a['yaPagadas']), count($a['sinDeclaracion'])
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
