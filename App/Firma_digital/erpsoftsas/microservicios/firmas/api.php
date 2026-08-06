<?php
namespace erpsoftsas\microservicios\firmas;

header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : $_SERVER['HTTP_HOST']));
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.conexionSqlServer.php';

class FirmasAPI
{

    public static function run()
    {
        $obj = new self();
        $funcion = isset($_POST['funcion']) ? intval($_POST['funcion']) : 0;
        switch ($funcion) {
            case 1:
                $obj->_generarCodigo();
                break;
            case 2:
                $obj->_verificarCodigo();
                break;
            case 3:
                $obj->_guardarFirma();
                break;
            case 4:
                $obj->_consultarFirma();
                break;
            case 5:
                $obj->_guardarFirmaUsuario();
                break;
            case 6:
                $obj->_consultarFirmaUsuario();
                break;
            case 7:
                $obj->_firmarDeclaracion();
                break;
            case 8:
                $obj->_consultarDeclaracionFirmada();
                break;
        }
    }

    /**
     * funcion 1 — Genera OTP de 6 dígitos y lo envía al correo del usuario.
     * Sirve tanto para firma de establecimiento (id_establecimiento > 0)
     * como para firma de declaración (id_establecimiento = 0).
     */
    private function _generarCodigo()
    {
        $idUsuario = intval($_POST['id_usuario']);
        $idEstablecimiento = intval($_POST['id_establecimiento'] ?? 0);
        $rol = $this->_rolFirmante();

        $conSql = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        if ($rol === 'declarante') {
            // El declarante es el usuario del sistema: el codigo va a su correo.
            $stmt = $conSql->consultar(
                "SELECT usu_Nombres AS usu_Nombre, usu_Correo
                 FROM conf_usuarios WHERE usu_Id = ? AND usu_Estado = 1",
                [$idUsuario]
            );
            $usuario = $conSql->obnerFila($stmt);

            if (!$usuario) {
                header('Content-type: application/json');
                echo json_encode(['ok' => 0, 'mensaje' => 'Usuario no encontrado (ID: ' . $idUsuario . ')']);
                return;
            }

            $email  = $usuario['usu_Correo'];
            $nombre = $usuario['usu_Nombre'];
        } else {
            // Contador o revisor fiscal: NO es usuario del sistema. Sus datos
            // viven en el contribuyente dueño de la declaración y el codigo
            // viaja a SU correo, porque es esa persona la que firma.
            $destino = $this->_destinatarioContador($conSql);

            if (!$destino['ok']) {
                header('Content-type: application/json');
                echo json_encode(['ok' => 0, 'mensaje' => $destino['mensaje']]);
                return;
            }

            $email  = $destino['email'];
            $nombre = $destino['nombre'];
        }

        $codigo = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiracion = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        // Se anulan los codigos previos del MISMO rol: el del declarante y el
        // del contador conviven sin pisarse.
        $conSql->consultar(
            "UPDATE codigos_verificacion SET codigo_Usado = 1
             WHERE codigo_IdUsuario = ?
               AND codigo_IdEstablecimiento = ?
               AND codigo_Rol = ?
               AND codigo_Usado = 0",
            [$idUsuario, $idEstablecimiento, $rol]
        );

        $conSql->consultar(
            "INSERT INTO codigos_verificacion
                (codigo_Valor, codigo_IdUsuario, codigo_Email, codigo_IdEstablecimiento,
                 codigo_FechaExpiracion, codigo_Rol)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$codigo, $idUsuario, $email, $idEstablecimiento, $expiracion, $rol]
        );

        $enviado = $this->_enviarCodigo($email, $nombre, $codigo);

        header('Content-type: application/json');
        echo json_encode([
            'ok' => $enviado ? 1 : 0,
            'mensaje' => $enviado
                ? 'Código enviado a ' . $this->_enmascarar($email)
                : 'Error al enviar el correo. Intente nuevamente.'
        ]);
    }

    /**
     * Rol del firmante para esta operación: 'declarante' (por defecto) o
     * 'contador'. Contador y revisor fiscal comparten una sola casilla en el
     * formulario, por eso comparten también un solo rol.
     */
    private function _rolFirmante()
    {
        $rol = strtolower(trim($_POST['rol'] ?? 'declarante'));
        return $rol === 'contador' ? 'contador' : 'declarante';
    }

    /**
     * Nombre y correo del contador (o, si no hay, del revisor fiscal) del
     * contribuyente dueño de la declaración que se está firmando.
     */
    private function _destinatarioContador($conSql)
    {
        $numeroDeclaracion = preg_replace(
            '/[^A-Za-z0-9\-]/', '',
            $_POST['numero_declaracion'] ?? $_POST['id_declaracion'] ?? ''
        );

        if ($numeroDeclaracion === '') {
            return ['ok' => false, 'mensaje' => 'Número de declaración inválido'];
        }

        $stmt = $conSql->consultar(
            "SELECT c.ind_NombreContador, c.ind_EmailContador,
                    c.ind_NombreRevisor,  c.ind_EmailRevisor
             FROM ind_declaraciones_ica d
             INNER JOIN ind_contribuyentes c ON c.ind_Id = d.dec_IdContribuyente
             WHERE d.dec_Id = ?",
            [$numeroDeclaracion]
        );
        $fila = $conSql->obnerFila($stmt);

        if (!$fila) {
            return ['ok' => false, 'mensaje' => 'No se encontró la declaración'];
        }

        $nombre = trim((string)$fila['ind_NombreContador']);
        $email  = trim((string)$fila['ind_EmailContador']);

        if ($email === '') {
            $nombre = trim((string)$fila['ind_NombreRevisor']);
            $email  = trim((string)$fila['ind_EmailRevisor']);
        }

        if ($email === '') {
            return [
                'ok' => false,
                'mensaje' => 'El contribuyente no tiene registrado el correo del contador '
                           . 'ni del revisor fiscal. Regístrelo en el RIT para poder firmar.'
            ];
        }

        return [
            'ok'     => true,
            'nombre' => $nombre !== '' ? $nombre : 'Contador / Revisor Fiscal',
            'email'  => $email
        ];
    }

    /** "contador@dominio.com" -> "co***@dominio.com" */
    private function _enmascarar($email)
    {
        $p = explode('@', (string)$email);
        return count($p) === 2 ? mb_substr($p[0], 0, 2) . '***@' . $p[1] : '';
    }

    /**
     * funcion 2 — Verifica el OTP ingresado por el usuario.
     */
    private function _verificarCodigo()
    {
        $codigo = preg_replace('/[^0-9]/', '', $_POST['codigo'] ?? '');
        $idUsuario = intval($_POST['id_usuario']);
        $idEstablecimiento = intval($_POST['id_establecimiento'] ?? 0);

        if (strlen($codigo) !== 6) {
            echo json_encode(['ok' => 0, 'mensaje' => 'El código debe tener 6 dígitos']);
            return;
        }

        $conSql = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
        $rol = $this->_rolFirmante();

        $stmt = $conSql->consultar(
            "SELECT codigo_Id FROM codigos_verificacion
             WHERE codigo_Valor = ?
               AND codigo_IdUsuario = ?
               AND codigo_IdEstablecimiento = ?
               AND codigo_Rol = ?
               AND codigo_Usado = 0
               AND codigo_FechaExpiracion > GETDATE()",
            [$codigo, $idUsuario, $idEstablecimiento, $rol]
        );
        $row = $conSql->obnerFila($stmt);

        if (!$row) {
            header('Content-type: application/json');
            echo json_encode(['ok' => 0, 'mensaje' => 'Código inválido o expirado']);
            return;
        }

        $conSql->consultar(
            "UPDATE codigos_verificacion SET codigo_Usado = 1 WHERE codigo_Id = ?",
            [intval($row['codigo_Id'])]
        );

        header('Content-type: application/json');
        echo json_encode(['ok' => 1, 'mensaje' => 'Código verificado correctamente']);
    }

    /**
     * funcion 3 — Guarda la firma de un establecimiento (base64 sello en PNG).
     */
    private function _guardarFirma()
    {
        $idUsuario = intval($_POST['id_usuario']);
        $idEstablecimiento = intval($_POST['id_establecimiento']);
        $base64 = $_POST['firma_base64'] ?? '';

        if (strpos($base64, 'data:image/') !== 0) {
            header('Content-type: application/json');
            echo json_encode(['ok' => 0, 'mensaje' => 'Formato de imagen inválido']);
            return;
        }

        $conSql = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
        $stmt = $conSql->consultar(
            "SELECT usu_Nombres AS usu_Nombre, usu_Correo FROM conf_usuarios WHERE usu_Id = ?",
            [$idUsuario]
        );
        $usuario = $conSql->obnerFila($stmt);
        $nombre = $usuario['usu_Nombre'] ?? '';
        $email = $usuario['usu_Correo'] ?? '';

        $conSql->consultar(
            "UPDATE firmas SET firma_Estado = 0 WHERE firma_IdEstablecimiento = ?",
            [$idEstablecimiento]
        );

        $conSql->consultar(
            "INSERT INTO firmas
                (firma_IdEstablecimiento, firma_IdUsuario, firma_NombreUsuario, firma_EmailUsuario, firma_Base64)
             VALUES (?, ?, ?, ?, ?)",
            [$idEstablecimiento, $idUsuario, $nombre, $email, $base64]
        );

        header('Content-type: application/json');
        echo json_encode(['ok' => 1, 'mensaje' => 'Firma guardada correctamente']);
    }

    /**
     * funcion 4 — Consulta si existe firma activa para un establecimiento.
     */
    private function _consultarFirma()
    {
        $idEstablecimiento = intval($_POST['id_establecimiento']);

        $conSql = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
        $stmt = $conSql->consultar(
            "SELECT firma_Id, firma_NombreUsuario, firma_EmailUsuario,
                    CONVERT(VARCHAR(19), firma_FechaHora, 120) AS firma_FechaHora
             FROM firmas
             WHERE firma_IdEstablecimiento = ? AND firma_Estado = 1",
            [$idEstablecimiento]
        );
        $row = $conSql->obnerFila($stmt);

        header('Content-type: application/json');
        if ($row) {
            echo json_encode(['ok' => 1, 'datos' => $row]);
        } else {
            echo json_encode(['ok' => 0, 'mensaje' => 'Sin firma registrada']);
        }
    }

    private function _guardarFirmaUsuario()
    {
        $idUsuario = intval($_POST['id_usuario']);
        $base64 = $_POST['firma_base64'] ?? '';

        if (strpos($base64, 'data:image/') !== 0) {
            header('Content-type: application/json');
            echo json_encode(['ok' => 0, 'mensaje' => 'Formato de firma inválido']);
            return;
        }

        $conSql = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        // UPSERT: actualiza si existe, inserta si no
        $stmt = $conSql->consultar(
            "SELECT fu_Id FROM firmas_usuario WHERE fu_IdUsuario = ?",
            [$idUsuario]
        );
        $existe = $conSql->obnerFila($stmt);

        if ($existe) {
            $conSql->consultar(
                "UPDATE firmas_usuario
                 SET fu_Base64 = ?, fu_FechaHora = GETDATE()
                 WHERE fu_IdUsuario = ?",
                [$base64, $idUsuario]
            );
        } else {
            $conSql->consultar(
                "INSERT INTO firmas_usuario (fu_IdUsuario, fu_Base64)
                 VALUES (?, ?)",
                [$idUsuario, $base64]
            );
        }

        header('Content-type: application/json');
        echo json_encode(['ok' => 1, 'mensaje' => 'Firma personal guardada correctamente']);
    }

    // ════════════════════════════════════════════════════════════════════
    // FUNCIÓN 6  — Consulta la firma personal del usuario
    // ════════════════════════════════════════════════════════════════════

    private function _consultarFirmaUsuario()
    {
        $idUsuario = intval($_POST['id_usuario']);

        $conSql = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
        $stmt = $conSql->consultar(
            "SELECT fu_Base64, CONVERT(VARCHAR(19), fu_FechaHora, 120) AS fu_FechaHora
             FROM firmas_usuario WHERE fu_IdUsuario = ?",
            [$idUsuario]
        );
        $row = $conSql->obnerFila($stmt);

        header('Content-type: application/json');
        if ($row) {
            echo json_encode(['ok' => 1, 'datos' => $row]);
        } else {
            echo json_encode(['ok' => 0, 'mensaje' => 'Sin firma personal registrada']);
        }
    }

    // ════════════════════════════════════════════════════════════════════
    // FUNCIÓN 7  — Firma una declaración (requiere OTP previo)
    // ════════════════════════════════════════════════════════════════════

    private function _firmarDeclaracion()
    {
        $idUsuario = intval($_POST['id_usuario']);
        $numeroDeclaracion = preg_replace('/[^A-Za-z0-9\-]/', '', $_POST['numero_declaracion'] ?? $_POST['id_declaracion'] ?? '');
        $esRefirma = intval($_POST['refirma'] ?? 0);

        if (!$numeroDeclaracion) {
            header('Content-type: application/json');
            echo json_encode(['ok' => 0, 'mensaje' => 'Número de declaración inválido']);
            return;
        }

        $conSql = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
        $rol = $this->_rolFirmante();

        // De quién queda el sello: el declarante es el usuario del sistema;
        // el contador/revisor no lo es, sus datos vienen del contribuyente.
        if ($rol === 'declarante') {
            $stmt = $conSql->consultar(
                "SELECT usu_Nombres AS usu_Nombre, usu_Correo FROM conf_usuarios WHERE usu_Id = ?",
                [$idUsuario]
            );
            $usuario = $conSql->obnerFila($stmt);
            $nombre  = $usuario['usu_Nombre'] ?? '';
            $email   = $usuario['usu_Correo'] ?? '';
        } else {
            $destino = $this->_destinatarioContador($conSql);

            if (!$destino['ok']) {
                header('Content-type: application/json');
                echo json_encode(['ok' => 0, 'mensaje' => $destino['mensaje']]);
                return;
            }

            $nombre = $destino['nombre'];
            $email  = $destino['email'];
        }

        // La unicidad ahora es por (declaración, rol): el declarante y el
        // contador firman la misma declaración sin pisarse.
        $stmtCheck = $conSql->consultar(
            "SELECT fd_Id FROM firmas_declaraciones
             WHERE fd_NumeroDeclaracion = ? AND fd_Rol = ?",
            [$numeroDeclaracion, $rol]
        );
        $existe = $conSql->obnerFila($stmtCheck);

        if ($existe && !$esRefirma) {
            header('Content-type: application/json');
            echo json_encode(['ok' => 0, 'mensaje' => 'Esta declaración ya fue firmada anteriormente']);
            return;
        }

        if ($existe && $esRefirma) {
            $conSql->consultar(
                "UPDATE firmas_declaraciones
                 SET fd_IdUsuario = ?, fd_NombreUsuario = ?, fd_EmailUsuario = ?,
                     fd_FechaHora = GETDATE()
                 WHERE fd_Id = ?",
                [$idUsuario, $nombre, $email, intval($existe['fd_Id'])]
            );
            $msg = 'Declaración refirmada correctamente';
        } else {
            $conSql->consultar(
                "INSERT INTO firmas_declaraciones
                    (fd_NumeroDeclaracion, fd_IdUsuario, fd_NombreUsuario, fd_EmailUsuario, fd_Rol)
                 VALUES (?, ?, ?, ?, ?)",
                [$numeroDeclaracion, $idUsuario, $nombre, $email, $rol]
            );
            $msg = $rol === 'contador'
                 ? 'Declaración firmada por el contador / revisor fiscal'
                 : 'Declaración firmada correctamente';
        }

        header('Content-type: application/json');
        echo json_encode(['ok' => 1, 'mensaje' => $msg, 'rol' => $rol]);
    }

    // ════════════════════════════════════════════════════════════════════
    // FUNCIÓN 8  — Consulta estado de declaraciones firmadas
    // Recibe: numeros_declaracion (array JSON)
    // Devuelve: array de números de declaración que ya están firmados
    // ════════════════════════════════════════════════════════════════════

    private function _consultarDeclaracionFirmada()
    {
        $numerosRaw = $_POST['numeros_declaracion'] ?? '[]';
        $numeros = json_decode($numerosRaw, true);

        if (!is_array($numeros) || empty($numeros)) {
            header('Content-type: application/json');
            echo json_encode(['ok' => 1, 'firmados' => []]);
            return;
        }

        // Sanitizar cada número
        $numerosLimpios = array_map(function ($n) {
            return "'" . preg_replace('/[^A-Za-z0-9\-]/', '', $n) . "'";
        }, $numeros);
        $inClause = implode(',', $numerosLimpios);

        $conSql = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
        $stmt = $conSql->consultar(
            "SELECT fd_NumeroDeclaracion, fd_NombreUsuario,
                    CONVERT(VARCHAR(19), fd_FechaHora, 120) AS fd_FechaHora
             FROM firmas_declaraciones
             WHERE fd_NumeroDeclaracion IN ($inClause)"
        );

        $firmados = [];
        while ($row = $conSql->obnerFila($stmt)) {
            $firmados[$row['fd_NumeroDeclaracion']] = [
                'nombre' => $row['fd_NombreUsuario'],
                'fecha' => $row['fd_FechaHora']
            ];
        }

        header('Content-type: application/json');
        echo json_encode(['ok' => 1, 'firmados' => $firmados]);
    }

    // ════════════════════════════════════════════════════════════════════
    // HELPERS
    // ════════════════════════════════════════════════════════════════════

    private function _enviarCodigo($email, $nombre, $codigo)
    {
        require_once SERVER . '/business/php_mailer/Exception.php';
        require_once SERVER . '/business/php_mailer/PHPMailer.php';
        require_once SERVER . '/business/php_mailer/SMTP.php';

        $cfg = require __DIR__ . '/config.php';

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host      = $cfg['smtp_host'];
            $mail->SMTPAuth  = true;
            $mail->Username  = $cfg['smtp_user'];
            $mail->Password  = $cfg['smtp_password'];
            $mail->SMTPSecure = 'tls';
            $mail->Port      = $cfg['smtp_port'];
            $mail->CharSet   = 'UTF-8';

            $mail->setFrom($cfg['smtp_user'], $cfg['from_name']);
            $mail->addAddress($email, $nombre);
            $mail->isHTML(true);
            $mail->Subject = $cfg['otp_subject'] . ' - ICA';

            $selloPath = __DIR__ . '/../../src/images/user/svg/Sello_Firma.png';
            if (file_exists($selloPath)) {
                $mail->addEmbeddedImage($selloPath, 'sello_firma', 'Sello_Firma.png');
                $selloImg = "<img src='cid:sello_firma' alt='Sello' style='max-width:180px;display:block;margin:10px auto 0;'>";
            } else {
                $selloImg = '';
            }

            $mail->Body = "
                <div style='font-family:Arial,sans-serif;max-width:480px;margin:auto;'>
                    <h3 style='color:#1a73e8;'>Firma Digital - Industria y Comercio</h3>
                    <p>Hola <strong>{$nombre}</strong>,</p>
                    <p>Tu código de verificación es:</p>
                    <div style='font-size:36px;font-weight:bold;letter-spacing:10px;
                                text-align:center;padding:20px;background:#f1f3f4;
                                border-radius:8px;margin:20px 0;'>{$codigo}</div>
                    <p style='color:#666;'>Este código expira en <strong>10 minutos</strong>.</p>
                    <p style='color:#999;font-size:12px;'>Si no solicitaste este código, ignora este mensaje.</p>
                    <hr>
                    {$selloImg}
                    <p style='color:#999;font-size:11px;'>{$cfg['from_name']} · Industria y Comercio</p>
                </div>
            ";
            $mail->send();
            return true;
        } catch (\Exception $e) {
            // Antes la excepcion se descartaba en silencio: si el SMTP fallaba,
            // el usuario solo veia "Error al enviar el correo" y no quedaba
            // ningun rastro para saber por que. Ahora queda en el log de PHP.
            error_log('[firmas] Fallo al enviar OTP a ' . $email . ': ' . $e->getMessage());
            return false;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \erpsoftsas\microservicios\firmas\FirmasAPI::run();
}
