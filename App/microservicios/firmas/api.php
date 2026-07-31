<?php
namespace erpsoftsas\microservicios\firmas;

header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : $_SERVER['HTTP_HOST']));
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.conexionSqlServer.php';
include_once SERVER . '/business/class.conexionUsuarios.php';

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

        $conMysql = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $stmt = $conMysql->consultar(
            "SELECT usu_Nombre, usu_Correo FROM conf_usuario WHERE usu_Id = $idUsuario AND usu_Estado = 1"
        );
        $usuario = $conMysql->obnerFila($stmt);

        if (!$usuario) {
            echo json_encode(['ok' => 0, 'mensaje' => 'Usuario no encontrado']);
            return;
        }

        $email = $usuario['usu_Correo'];
        $nombre = $usuario['usu_Nombre'];
        $codigo = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiracion = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        $conSql = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
        $conSql->consultar(
            "UPDATE codigos_verificacion SET codigo_Usado = 1
             WHERE codigo_IdUsuario = $idUsuario
               AND codigo_IdEstablecimiento = $idEstablecimiento
               AND codigo_Usado = 0"
        );
        $conSql->consultar(
            "INSERT INTO codigos_verificacion
                (codigo_Valor, codigo_IdUsuario, codigo_Email, codigo_IdEstablecimiento, codigo_FechaExpiracion)
             VALUES ('$codigo', $idUsuario, '$email', $idEstablecimiento, '$expiracion')"
        );

        $enviado = $this->_enviarCodigo($email, $nombre, $codigo);

        header('Content-type: application/json');
        echo json_encode([
            'ok' => $enviado ? 1 : 0,
            'mensaje' => $enviado
                ? 'Código enviado a ' . $email
                : 'Error al enviar el correo. Intente nuevamente.'
        ]);
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
        $stmt = $conSql->consultar(
            "SELECT codigo_Id FROM codigos_verificacion
             WHERE codigo_Valor = '$codigo'
               AND codigo_IdUsuario = $idUsuario
               AND codigo_IdEstablecimiento = $idEstablecimiento
               AND codigo_Usado = 0
               AND codigo_FechaExpiracion > GETDATE()"
        );
        $row = $conSql->obnerFila($stmt);

        if (!$row) {
            header('Content-type: application/json');
            echo json_encode(['ok' => 0, 'mensaje' => 'Código inválido o expirado']);
            return;
        }

        $id = intval($row['codigo_Id']);
        $conSql->consultar("UPDATE codigos_verificacion SET codigo_Usado = 1 WHERE codigo_Id = $id");

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

        $conMysql = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $stmt = $conMysql->consultar(
            "SELECT usu_Nombre, usu_Correo FROM conf_usuario WHERE usu_Id = $idUsuario"
        );
        $usuario = $conMysql->obnerFila($stmt);
        $nombre = addslashes($usuario['usu_Nombre'] ?? '');
        $email = addslashes($usuario['usu_Correo'] ?? '');

        $conSql = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
        $conSql->consultar(
            "UPDATE firmas SET firma_Estado = 0 WHERE firma_IdEstablecimiento = $idEstablecimiento"
        );

        $base64Safe = str_replace("'", "''", $base64);
        $conSql->consultar(
            "INSERT INTO firmas
                (firma_IdEstablecimiento, firma_IdUsuario, firma_NombreUsuario, firma_EmailUsuario, firma_Base64)
             VALUES ($idEstablecimiento, $idUsuario, '$nombre', '$email', '$base64Safe')"
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
             WHERE firma_IdEstablecimiento = $idEstablecimiento AND firma_Estado = 1"
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

        $base64Safe = str_replace("'", "''", $base64);
        $conSql = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        // UPSERT: actualiza si existe, inserta si no
        $stmt = $conSql->consultar(
            "SELECT fu_Id FROM firmas_usuario WHERE fu_IdUsuario = $idUsuario"
        );
        $existe = $conSql->obnerFila($stmt);

        if ($existe) {
            $conSql->consultar(
                "UPDATE firmas_usuario
                 SET fu_Base64 = '$base64Safe', fu_FechaHora = GETDATE()
                 WHERE fu_IdUsuario = $idUsuario"
            );
        } else {
            $conSql->consultar(
                "INSERT INTO firmas_usuario (fu_IdUsuario, fu_Base64)
                 VALUES ($idUsuario, '$base64Safe')"
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
             FROM firmas_usuario WHERE fu_IdUsuario = $idUsuario"
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
        $numeroDeclaracion = preg_replace('/[^A-Za-z0-9\-]/', '', $_POST['numero_declaracion'] ?? '');
        $esRefirma = intval($_POST['refirma'] ?? 0);

        if (!$numeroDeclaracion) {
            header('Content-type: application/json');
            echo json_encode(['ok' => 0, 'mensaje' => 'Número de declaración inválido']);
            return;
        }

        $conMysql = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $stmt = $conMysql->consultar(
            "SELECT usu_Nombre, usu_Correo FROM conf_usuario WHERE usu_Id = $idUsuario"
        );
        $usuario = $conMysql->obnerFila($stmt);
        $nombre = addslashes($usuario['usu_Nombre'] ?? '');
        $email = addslashes($usuario['usu_Correo'] ?? '');

        $conSql = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        // Verificar si ya está firmada
        $stmtCheck = $conSql->consultar(
            "SELECT fd_Id FROM firmas_declaraciones
             WHERE fd_NumeroDeclaracion = '$numeroDeclaracion'"
        );
        $existe = $conSql->obnerFila($stmtCheck);

        if ($existe && !$esRefirma) {
            header('Content-type: application/json');
            echo json_encode(['ok' => 0, 'mensaje' => 'Esta declaración ya fue firmada anteriormente']);
            return;
        }

        if ($existe && $esRefirma) {
            // Actualizar registro existente
            $idFirma = intval($existe['fd_Id']);
            $conSql->consultar(
                "UPDATE firmas_declaraciones
                 SET fd_IdUsuario = $idUsuario,
                     fd_NombreUsuario = '$nombre',
                     fd_EmailUsuario = '$email',
                     fd_FechaHora = GETDATE()
                 WHERE fd_Id = $idFirma"
            );
            $msg = 'Declaración refirmada correctamente';
        } else {
            // Insertar nuevo registro
            $conSql->consultar(
                "INSERT INTO firmas_declaraciones
                    (fd_NumeroDeclaracion, fd_IdUsuario, fd_NombreUsuario, fd_EmailUsuario)
                 VALUES ('$numeroDeclaracion', $idUsuario, '$nombre', '$email')"
            );
            $msg = 'Declaración firmada correctamente';
        }

        header('Content-type: application/json');
        echo json_encode(['ok' => 1, 'mensaje' => $msg]);
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
            return false;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \erpsoftsas\microservicios\firmas\FirmasAPI::run();
}
