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
            case 9:
                $obj->_firmarRit();
                break;
            case 10:
                $obj->_consultarFirmaRit();
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
        /*
         * El usuario sale de la SESION, no del POST.
         *
         * Antes era intval($_POST['id_usuario']) a secas, y eso causo el cuelgue
         * que reporto el cliente: la pantalla del RIT no emite la constante
         * ID_USUARIO -solo la tienen icaWebPresentar e icaWebConsultar-, asi que
         * el navegador lanzaba ReferenceError al ARMAR la peticion, antes de
         * enviarla. Sin peticion no hay respuesta ni error que capturar, y la
         * ventana "Generando codigo" se quedaba girando para siempre.
         *
         * Tomarlo de la sesion mata la clase entera de bug: ya no importa que
         * pantalla llame, ni que emita o deje de emitir una constante. Y de paso
         * es lo correcto, que es lo que ya hacen las funciones 7, 9 y 10: un id
         * que manda el navegador no prueba quien es quien.
         *
         * Se conserva el POST como respaldo unicamente para los flujos del
         * perfil de firma (microservicios/firmas/firmas.js y perfilFirma.js),
         * que todavia lo mandan. Cuando esos migren, el fallback sobra.
         */
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }
        $idUsuario = isset($_SESSION['id_usuario']) ? (int) $_SESSION['id_usuario'] : 0;
        if ($idUsuario <= 0) {
            $idUsuario = intval($_POST['id_usuario'] ?? 0);
        }

        if ($idUsuario <= 0) {
            header('Content-type: application/json');
            echo json_encode(['ok' => 0, 'mensaje' => 'Sesión no válida. Vuelva a ingresar.']);
            return;
        }

        $idEstablecimiento = intval($_POST['id_establecimiento'] ?? 0);
        $rol = $this->_rolFirmante();

        $conSql = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        // Solo un usuario ACTIVO pide códigos (la versión anterior lo exigía con
        // usu_Estado = 1; se conserva al sacar el correo de la declaración).
        $activo = $conSql->obnerFila($conSql->consultar(
            "SELECT usu_Id FROM conf_usuarios WHERE usu_Id = ? AND usu_Estado = 1",
            [$idUsuario]
        ));
        if (!$activo) {
            header('Content-type: application/json');
            echo json_encode(['ok' => 0, 'mensaje' => 'Usuario no encontrado o inactivo.']);
            return;
        }

        if ($rol === 'rit') {
            // Firma del RIT. El contribuyente lo decide _ritPermitido: los roles
            // de Alcaldía (1 y 2) pueden firmar el de cualquiera indicando
            // id_contribuyente; el resto, solo el suyo. El código va al correo
            // del REPRESENTANTE de ESE contribuyente, nunca al del funcionario
            // que lo gestiona.
            $permiso = $this->_ritPermitido($conSql);
            if (!$permiso['ok']) {
                header('Content-type: application/json');
                echo json_encode(['ok' => 0, 'mensaje' => $permiso['mensaje']]);
                return;
            }
            // Un RIT que no se puede firmar no recibe código: se dice qué falta.
            $faltan = $this->_faltantesRit($conSql, $permiso['id']);
            if ($faltan !== null) {
                header('Content-type: application/json');
                echo json_encode(['ok' => 0, 'mensaje' => $faltan]);
                return;
            }
            $dest   = $this->_correoRepresentante($conSql, $permiso['id'], $idUsuario);
            $email  = $dest['email'];
            $nombre = $dest['nombre'];

            if (trim((string) $email) === '') {
                header('Content-type: application/json');
                echo json_encode(['ok' => 0, 'mensaje' => 'No hay a dónde enviar el código: registre el '
                    . 'correo del representante legal en el RIT.']);
                return;
            }
        } elseif ($rol === 'declarante') {
            // El código va al REPRESENTANTE del contribuyente DUEÑO de la
            // declaración que se firma (regla del cliente 2026-08-26), NO al del
            // usuario logueado. Así llega a quien registró el contribuyente lo
            // mismo cuando el propio contribuyente firma su declaración que
            // cuando la Alcaldía la presenta por él en ventanilla; nunca al
            // correo del funcionario. Antes salía del usuario de la sesión, y un
            // administrador gestionando a otro recibía el código en SU correo.
            $d = $this->_datosDeclarante($conSql);
            if (!$d['ok']) {
                header('Content-type: application/json');
                echo json_encode(['ok' => 0, 'mensaje' => $d['mensaje']]);
                return;
            }
            if (!$this->_puedeFirmar($conSql, $d['id'])) {
                header('Content-type: application/json');
                echo json_encode(['ok' => 0, 'mensaje' => 'No puede firmar la declaración de otro contribuyente.']);
                return;
            }
            $dest   = $this->_correoRepresentante($conSql, $d['id'], $idUsuario);
            $email  = $dest['email'];
            $nombre = $dest['nombre'];

            if (trim((string) $email) === '') {
                header('Content-type: application/json');
                echo json_encode(['ok' => 0, 'mensaje' => 'No hay a dónde enviar el código: no está '
                    . 'registrado el correo del representante legal del contribuyente. Regístrelo en el RIT.']);
                return;
            }
        } else {
            // Contador o revisor fiscal: NO es usuario del sistema. Sus datos
            // viven en el contribuyente dueño de la declaración y el codigo
            // viaja a SU correo, porque es esa persona la que firma.
            // Misma regla de propiedad que el declarante: solo el dueño de la
            // declaración o la Alcaldía pueden pedir este código.
            $d = $this->_datosDeclarante($conSql);
            if (!$d['ok'] || !$this->_puedeFirmar($conSql, $d['id'])) {
                header('Content-type: application/json');
                echo json_encode(['ok' => 0, 'mensaje' => $d['ok']
                    ? 'No puede firmar la declaración de otro contribuyente.'
                    : $d['mensaje']]);
                return;
            }
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

        // El rol con que viaja el codigo lleva el modulo dentro (ver
        // _rolCodigo): un codigo pedido para el ICA no debe firmar una
        // retencion.
        $rolCodigo = $this->_rolCodigo($rol);

        // Se anulan los codigos previos del MISMO rol: el del declarante y el
        // del contador conviven sin pisarse.
        $conSql->consultar(
            "UPDATE codigos_verificacion SET codigo_Usado = 1
             WHERE codigo_IdUsuario = ?
               AND codigo_IdEstablecimiento = ?
               AND codigo_Rol = ?
               AND codigo_Usado = 0",
            [$idUsuario, $idEstablecimiento, $rolCodigo]
        );

        $conSql->consultar(
            "INSERT INTO codigos_verificacion
                (codigo_Valor, codigo_IdUsuario, codigo_Email, codigo_IdEstablecimiento,
                 codigo_FechaExpiracion, codigo_Rol)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$codigo, $idUsuario, $email, $idEstablecimiento, $expiracion, $rolCodigo]
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
    /**
     * De que formulario es esta firma: 'ICA' (por defecto), 'RETEICA' o
     * 'AUTORRETEICA'.
     *
     * Hace falta porque firmas_declaraciones identifica la firma por el NUMERO
     * de declaracion, y los tres modulos reparten numeros de series distintas:
     * la retencion 2026000001 y la declaracion de ICA 2026000001 son documentos
     * diferentes. Sin esta columna la firma de una valdria para la otra.
     */
    private function _moduloFirma()
    {
        $m = strtoupper(trim($_POST['modulo'] ?? 'ICA'));
        return in_array($m, ['RETEICA', 'AUTORRETEICA'], true) ? $m : 'ICA';
    }

    /**
     * El rol con que se guarda el CODIGO OTP, que no es el mismo con que se
     * guarda la firma.
     *
     * codigos_verificacion identifica cada codigo por (usuario, establecimiento,
     * rol). Si los tres modulos usaran 'declarante' a secas, un codigo pedido
     * para firmar la declaracion anual de ICA serviria para firmar una
     * retencion, y al reves. Son tres autorizaciones distintas.
     *
     * Es exactamente el mismo razonamiento por el que el RIT tiene su propio
     * rol desde el 2026-08-19.
     *
     * El prefijo va abreviado porque codigo_Rol es varchar(20):
     * 'aut:declarante' son 14 caracteres, 'AUTORRETEICA:declarante' serian 23.
     */
    private function _rolCodigo($rol)
    {
        $modulo = $this->_moduloFirma();
        if ($modulo === 'RETEICA')      { return 'ret:' . $rol; }
        if ($modulo === 'AUTORRETEICA') { return 'aut:' . $rol; }
        return $rol;
    }

    private function _rolFirmante()
    {
        $rol = strtolower(trim($_POST['rol'] ?? 'declarante'));
        if ($rol === 'contador') { return 'contador'; }
        // El RIT tiene rol propio a proposito. codigos_verificacion identifica
        // cada codigo por (usuario, establecimiento, rol); si el RIT usara
        // 'declarante' como las declaraciones, un codigo pedido para firmar una
        // declaracion serviria para firmar el RIT y al reves. Son dos
        // autorizaciones distintas y no deben ser intercambiables.
        if ($rol === 'rit') { return 'rit'; }
        return 'declarante';
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

        /*
         * De que tabla se busca el contribuyente depende del modulo. Antes
         * estaba fijo en ind_declaraciones_ica, que para una retencion habria
         * buscado un id de OTRA tabla y devuelto o nada, o -peor- el
         * contribuyente equivocado si los ids coincidian.
         *
         * En ICA se busca por dec_Id porque es lo que manda esa pantalla; en
         * los modulos nuevos por el NUMERO, que es lo que viaja en el POST y lo
         * que se imprime en el papel.
         */
        $modulo = $this->_moduloFirma();

        if ($modulo === 'RETEICA') {
            $stmt = $conSql->consultar(
                "SELECT c.ind_NombreContador, c.ind_EmailContador,
                        c.ind_NombreRevisor,  c.ind_EmailRevisor
                 FROM ind_reteica d
                 INNER JOIN ind_contribuyentes c ON c.ind_Id = d.ret_IdContribuyente
                 WHERE d.ret_NumeroDeclaracion = ?",
                [$numeroDeclaracion]
            );
        } elseif ($modulo === 'AUTORRETEICA') {
            $stmt = $conSql->consultar(
                "SELECT c.ind_NombreContador, c.ind_EmailContador,
                        c.ind_NombreRevisor,  c.ind_EmailRevisor
                 FROM ind_autorreteica d
                 INNER JOIN ind_contribuyentes c ON c.ind_Id = d.aut_IdContribuyente
                 WHERE d.aut_NumeroDeclaracion = ?",
                [$numeroDeclaracion]
            );
        } else {
            $stmt = $conSql->consultar(
                "SELECT c.ind_NombreContador, c.ind_EmailContador,
                        c.ind_NombreRevisor,  c.ind_EmailRevisor
                 FROM ind_declaraciones_ica d
                 INNER JOIN ind_contribuyentes c ON c.ind_Id = d.dec_IdContribuyente
                 WHERE d.dec_Id = ?",
                [$numeroDeclaracion]
            );
        }
        $fila = $conSql->obnerFila($stmt);

        if (!$fila) {
            return ['ok' => false, 'mensaje' => 'No se encontró la declaración'];
        }

        /*
         * Manda el REVISOR FISCAL, y solo si no hay se usa el contador.
         *
         * Era al reves hasta el 2026-08-26. Lo corrigio el cliente en la
         * reunion: "si tienen al revisor y al contador, se le da prioridad al
         * revisor; y si solo tiene contador, solo al contador". Tiene sentido
         * jerarquico -el revisor fiscal dictamina sobre la contabilidad que
         * lleva el contador-, asi que cuando estan los dos firma el de mayor
         * responsabilidad.
         */
        $nombre = trim((string)$fila['ind_NombreRevisor']);
        $email  = trim((string)$fila['ind_EmailRevisor']);

        if ($email === '') {
            $nombre = trim((string)$fila['ind_NombreContador']);
            $email  = trim((string)$fila['ind_EmailContador']);
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

    /**
     * El contribuyente DUEÑO de la declaración que se firma, con sus datos, a
     * partir del número/id que manda la pantalla. Mismo criterio por módulo que
     * _destinatarioContador: el dueño de la declaración manda, no quién esté
     * logueado. Devuelve ['ok', 'id', 'fila'] o ['ok'=>false, 'mensaje'].
     */
    private function _datosDeclarante($conSql)
    {
        $ident = preg_replace(
            '/[^A-Za-z0-9\-]/', '',
            $_POST['numero_declaracion'] ?? $_POST['id_declaracion'] ?? ''
        );
        if ($ident === '') {
            return ['ok' => false, 'mensaje' => 'Número de declaración inválido'];
        }

        $modulo = $this->_moduloFirma();
        if ($modulo === 'RETEICA') {
            $sql = "SELECT c.ind_Id FROM ind_reteica d
                     INNER JOIN ind_contribuyentes c ON c.ind_Id = d.ret_IdContribuyente
                     WHERE d.ret_NumeroDeclaracion = ?";
        } elseif ($modulo === 'AUTORRETEICA') {
            $sql = "SELECT c.ind_Id FROM ind_autorreteica d
                     INNER JOIN ind_contribuyentes c ON c.ind_Id = d.aut_IdContribuyente
                     WHERE d.aut_NumeroDeclaracion = ?";
        } else {
            $sql = "SELECT c.ind_Id FROM ind_declaraciones_ica d
                     INNER JOIN ind_contribuyentes c ON c.ind_Id = d.dec_IdContribuyente
                     WHERE d.dec_Id = ?";
        }

        $fila = $conSql->obnerFila($conSql->consultar($sql, [$ident]));
        if (!$fila) {
            return ['ok' => false, 'mensaje' => 'No se encontró la declaración'];
        }
        return ['ok' => true, 'id' => (int) $fila['ind_Id']];
    }

    /**
     * ¿Puede esta sesión firmar por este contribuyente? Los roles de Alcaldía
     * (1 y 2) sí -inscriben y presentan en ventanilla-; el resto solo por el
     * suyo. Así un contribuyente no puede firmar la declaración de otro aunque
     * arme el POST a mano.
     */
    private function _puedeFirmar($conSql, $idContribuyente)
    {
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }
        $rol = isset($_SESSION['id_Rol']) ? (int) $_SESSION['id_Rol'] : 0;
        if (in_array($rol, [1, 2], true)) { return true; }

        $propio = $this->_contribuyenteDeLaSesion($conSql);
        return $propio && (int) $propio === (int) $idContribuyente;
    }

    /**
     * Correo y nombre a donde va el código del DECLARANTE / RIT: el del
     * REPRESENTANTE LEGAL del contribuyente (regla del cliente 2026-08-26).
     *
     * Si el representante no tiene correo se cae, EN ESTE ORDEN:
     *   1. al de la CUENTA, pero solo si quien firma es el propio contribuyente
     *      (persona natural que se representa a sí misma). Nunca el de un
     *      funcionario que gestiona a otro: ese es justo el error a evitar.
     *   2. al correo general del contribuyente (ind_Email).
     */
    private function _correoRepresentante($conSql, $idContribuyente, $idUsuario)
    {
        $c = $conSql->obnerFila($conSql->consultar(
            "SELECT ind_Email_representante, ind_Nombre_representante, ind_Email,
                    ind_PrimerNombre, ind_SegundoNombre, ind_PrimerApellido, ind_SegundoApellido
               FROM ind_contribuyentes WHERE ind_Id = ?",
            [(int) $idContribuyente]
        ));
        if (!$c) { return ['email' => '', 'nombre' => '']; }

        $nombreContrib = trim(preg_replace('/\s+/', ' ',
            ($c['ind_PrimerNombre'] ?? '') . ' ' . ($c['ind_SegundoNombre'] ?? '') . ' ' .
            ($c['ind_PrimerApellido'] ?? '') . ' ' . ($c['ind_SegundoApellido'] ?? '')));

        $emailRep  = trim((string) ($c['ind_Email_representante'] ?? ''));
        $nombreRep = trim((string) ($c['ind_Nombre_representante'] ?? ''));
        if ($emailRep !== '') {
            return ['email' => $emailRep, 'nombre' => $nombreRep !== '' ? $nombreRep : $nombreContrib];
        }

        $propio = $this->_contribuyenteDeLaSesion($conSql);
        if ($propio && (int) $propio === (int) $idContribuyente) {
            $u = $conSql->obnerFila($conSql->consultar(
                "SELECT usu_Nombres, usu_Correo FROM conf_usuarios WHERE usu_Id = ?",
                [(int) $idUsuario]
            ));
            $emailCuenta = trim((string) ($u['usu_Correo'] ?? ''));
            if ($emailCuenta !== '') {
                return ['email' => $emailCuenta, 'nombre' => $nombreContrib !== '' ? $nombreContrib : trim((string) ($u['usu_Nombres'] ?? ''))];
            }
        }

        return ['email' => trim((string) ($c['ind_Email'] ?? '')), 'nombre' => $nombreContrib];
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
        /*
         * Desde el 2026-08-19 esta funcion EXIGE el codigo OTP, igual que la
         * del RIT. Antes no lo miraba -daba por hecho que la pantalla habia
         * llamado a la funcion 2 antes- y por esa puerta se podia registrar
         * una firma sin haber recibido ningun correo.
         *
         * Tambien deja de creerle al id_usuario que manda el navegador: el
         * firmante sale de la sesion. Un id en el POST no prueba quien es
         * quien.
         */
        $numeroDeclaracion = preg_replace('/[^A-Za-z0-9\-]/', '', $_POST['numero_declaracion'] ?? $_POST['id_declaracion'] ?? '');
        $esRefirma = intval($_POST['refirma'] ?? 0);

        header('Content-type: application/json');

        if (!$numeroDeclaracion) {
            echo json_encode(['ok' => 0, 'mensaje' => 'Número de declaración inválido']);
            return;
        }

        $idUsuario = $this->_usuarioDeLaSesion();
        if (!$idUsuario) {
            echo json_encode(['ok' => 0, 'mensaje' => 'Sesión no válida. Vuelva a ingresar.']);
            return;
        }

        $conSql = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
        $rol = $this->_rolFirmante();

        $modulo = $this->_moduloFirma();

        // El codigo se valida y se consume aqui, no en una llamada aparte.
        // Vale tambien para refirmar: refirmar es volver a firmar.
        // El rol lleva el modulo dentro: ver _rolCodigo().
        $errorCodigo = $this->_consumirCodigo($conSql, $idUsuario, $this->_rolCodigo($rol));
        if ($errorCodigo !== null) {
            echo json_encode(['ok' => 0, 'mensaje' => $errorCodigo]);
            return;
        }

        // De quién queda el sello: el declarante es el usuario del sistema;
        // el contador/revisor no lo es, sus datos vienen del contribuyente.
        if ($rol === 'declarante') {
            // El sello del declarante muestra al REPRESENTANTE del contribuyente
            // dueño de la declaración, no al usuario que operó (que puede ser un
            // funcionario firmando en ventanilla). fd_IdUsuario abajo sí queda
            // como el usuario real de la sesión: traza de quién firmó.
            $d = $this->_datosDeclarante($conSql);
            if (!$d['ok']) {
                echo json_encode(['ok' => 0, 'mensaje' => $d['mensaje']]);
                return;
            }
            if (!$this->_puedeFirmar($conSql, $d['id'])) {
                echo json_encode(['ok' => 0, 'mensaje' => 'No puede firmar la declaración de otro contribuyente.']);
                return;
            }
            $dest   = $this->_correoRepresentante($conSql, $d['id'], $idUsuario);
            $nombre = $dest['nombre'];
            $email  = $dest['email'];
        } else {
            // Misma regla de propiedad que el declarante (ver funcion 1).
            $d = $this->_datosDeclarante($conSql);
            if (!$d['ok'] || !$this->_puedeFirmar($conSql, $d['id'])) {
                echo json_encode(['ok' => 0, 'mensaje' => $d['ok']
                    ? 'No puede firmar la declaración de otro contribuyente.'
                    : $d['mensaje']]);
                return;
            }
            $destino = $this->_destinatarioContador($conSql);

            if (!$destino['ok']) {
                header('Content-type: application/json');
                echo json_encode(['ok' => 0, 'mensaje' => $destino['mensaje']]);
                return;
            }

            $nombre = $destino['nombre'];
            $email  = $destino['email'];
        }

        // La unicidad es por (declaración, rol, MÓDULO): el declarante y el
        // contador firman la misma declaración sin pisarse, y la retención
        // 2026000001 no es la declaración de ICA 2026000001.
        $stmtCheck = $conSql->consultar(
            "SELECT fd_Id FROM firmas_declaraciones
             WHERE fd_NumeroDeclaracion = ? AND fd_Rol = ? AND fd_Modulo = ?",
            [$numeroDeclaracion, $rol, $modulo]
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
                    (fd_NumeroDeclaracion, fd_IdUsuario, fd_NombreUsuario, fd_EmailUsuario,
                     fd_Rol, fd_Modulo)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$numeroDeclaracion, $idUsuario, $nombre, $email, $rol, $modulo]
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
        // Filtrado por modulo: sin esto, una retencion firmada apareceria como
        // firmada tambien en la pantalla del ICA, si comparten el numero.
        $stmt = $conSql->consultar(
            "SELECT fd_NumeroDeclaracion, fd_NombreUsuario,
                    CONVERT(VARCHAR(19), fd_FechaHora, 120) AS fd_FechaHora
             FROM firmas_declaraciones
             WHERE fd_NumeroDeclaracion IN ($inClause) AND fd_Modulo = ?",
            [$this->_moduloFirma()]
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

            // Correo LEAN para mejorar la ENTREGA, sobre todo a Hotmail/Outlook,
            // que filtra con dureza el correo automatico: SIN imagen embebida
            // (era un adjunto que sube el puntaje de spam en un OTP) y CON
            // version de texto plano (AltBody), que los filtros esperan en un
            // correo legitimo. El sello no aporta nada a un codigo de un solo uso.
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
                    <p style='color:#999;font-size:11px;'>{$cfg['from_name']} · Industria y Comercio</p>
                </div>
            ";
            $mail->AltBody = "Firma Digital - Industria y Comercio\n\n"
                . "Hola {$nombre},\n\n"
                . "Tu codigo de verificacion es: {$codigo}\n"
                . "Este codigo expira en 10 minutos.\n\n"
                . "Si no solicitaste este codigo, ignora este mensaje.\n"
                . "{$cfg['from_name']} - Industria y Comercio";
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

    /* ====================================================================
       FIRMA DEL RIT  (funciones 9 y 10)
       ====================================================================

       Pedido del cliente el 2026-08-19: el RIT se firma al inscribirse y en
       cada novedad, y la casilla 30 del formulario impreso -"Contribuyente o
       Representante Legal", hasta ahora en blanco- sale estampada, tal como
       ya sale la firma en las declaraciones.

       DOS DIFERENCIAS DELIBERADAS CON _firmarDeclaracion()

       1. El codigo OTP se valida AQUI DENTRO, en la misma llamada que registra
          la firma, y se consume en el acto. La firma de declaracion quedo
          partida en dos pasos (funcion 2 verifica, funcion 7 registra) y la
          funcion 7 no vuelve a mirar el codigo: quien llame ese endpoint
          directamente registra una firma sin haber recibido ningun correo.
          Aqui no se repite ese diseño.

       2. Quien firma sale de la SESION, no de $_POST. El id_usuario que manda
          el navegador no prueba nada; el de la sesion si.
    ==================================================================== */


    /**
     * Valida el codigo OTP y lo consume, todo en la misma llamada.
     *
     * Lo usan por igual la firma del RIT y la de declaraciones. Antes solo lo
     * hacia el RIT: la de declaraciones venia partida en dos pasos -la funcion
     * 2 verificaba, la 7 registraba- y la 7 no volvia a mirar el codigo, asi
     * que un POST directo a la 7 dejaba una firma registrada sin haber recibido
     * ningun correo. Se comprobo que ya habia pasado: la tabla de codigos
     * estaba vacia y aun asi habia 5 firmas de declaracion guardadas.
     *
     * Validar y consumir juntos es lo que cierra el hueco: entre el momento de
     * comprobar el codigo y el de marcarlo usado no queda ninguna puerta.
     *
     * Devuelve null si el codigo es bueno, o el mensaje de rechazo.
     */
    private function _consumirCodigo($conSql, $idUsuario, $rol, $idEstablecimiento = 0)
    {
        $codigo = preg_replace('/[^0-9]/', '', $_POST['codigo'] ?? '');

        if (strlen($codigo) !== 6) {
            return 'El código debe tener 6 dígitos';
        }

        $vale = $conSql->obnerFila($conSql->consultar(
            "SELECT codigo_Id FROM codigos_verificacion
              WHERE codigo_Valor = ?
                AND codigo_IdUsuario = ?
                AND codigo_IdEstablecimiento = ?
                AND codigo_Rol = ?
                AND codigo_Usado = 0
                AND codigo_FechaExpiracion > GETDATE()",
            [$codigo, (int) $idUsuario, (int) $idEstablecimiento, $rol]
        ));

        if (!$vale) {
            return 'Código inválido o expirado';
        }

        $conSql->consultar(
            "UPDATE codigos_verificacion SET codigo_Usado = 1 WHERE codigo_Id = ?",
            [(int) $vale['codigo_Id']]
        );

        return null;
    }

    /** Usuario de la sesion. El id que manda el navegador no prueba nada. */
    private function _usuarioDeLaSesion()
    {
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }
        return empty($_SESSION['id_usuario']) ? null : (int) $_SESSION['id_usuario'];
    }

    /** Contribuyente al que pertenece el usuario de la sesion. */
    private function _contribuyenteDeLaSesion($conSql)
    {
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }
        if (empty($_SESSION['id_usuario'])) { return null; }

        $fila = $conSql->obnerFila($conSql->consultar(
            // TOP 1 + ORDER BY: con documentos repetidos en el padrón el
            // resultado debe ser siempre el mismo (igual criterio que
            // class.contribuyentes.php::_verificarAcceso).
            "SELECT TOP 1 c.ind_Id
               FROM ind_contribuyentes c
               INNER JOIN conf_usuarios u ON u.usu_NumeroDocumento = c.ind_NumeroIdentificacion
              WHERE u.usu_Id = ?
              ORDER BY c.ind_Id",
            [(int) $_SESSION['id_usuario']]
        ));

        return isset($fila['ind_Id']) ? (int) $fila['ind_Id'] : null;
    }

    /**
     * A que RIT puede firmar esta sesion. Los roles de Alcaldia (1 y 2) pueden
     * firmar el de cualquiera -hacen inscripciones en ventanilla-; el resto,
     * solo el propio.
     */
    private function _ritPermitido($conSql)
    {
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }
        if (empty($_SESSION['id_usuario'])) {
            return ['ok' => false, 'mensaje' => 'Sesión no válida. Vuelva a ingresar.'];
        }

        $rolSesion = isset($_SESSION['id_Rol']) ? (int) $_SESSION['id_Rol'] : 0;
        $pedido    = (int) ($_POST['id_contribuyente'] ?? 0);
        $propio    = $this->_contribuyenteDeLaSesion($conSql);

        if (in_array($rolSesion, [1, 2], true)) {
            $id = $pedido ?: $propio;
            if (!$id) { return ['ok' => false, 'mensaje' => 'No se indicó el contribuyente.']; }
            return ['ok' => true, 'id' => $id, 'usuario' => (int) $_SESSION['id_usuario']];
        }

        if (!$propio) {
            return ['ok' => false, 'mensaje' => 'Su usuario no está asociado a un contribuyente.'];
        }
        // Fuera de la Alcaldía el id que llega del navegador no decide nada: se
        // firma SIEMPRE el propio. (Antes se rechazaba si no coincidía, y como
        // la pantalla ahora manda el id de localStorage, un padrón con
        // documentos repetidos podía dejar al dueño sin poder firmar.)
        return ['ok' => true, 'id' => $propio, 'usuario' => (int) $_SESSION['id_usuario']];
    }

    /**
     * Mensaje de por qué el RIT guardado no se puede firmar, o null si está
     * completo. La regla es RitFirma::faltantes(), la misma del guardado.
     */
    private function _faltantesRit($conSql, $idContribuyente)
    {
        include_once SERVER . '/business/class.ritFirma.php';
        $faltan = \erpsoftsas\RitFirma::faltantes($conSql, $idContribuyente);
        if (!$faltan) { return null; }

        return 'No se puede firmar el RIT. Falta: ' . implode('; ', $faltan)
             . '. Complételo (los documentos van en la sección "Documentos"), '
             . 'guárdelo y vuelva a firmar.';
    }

    /**
     * funcion 9 - Firma el RIT.
     * Requiere el codigo OTP; lo valida y lo consume en esta misma llamada.
     */
    private function _firmarRit()
    {
        header('Content-type: application/json');

        $conSql = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $permiso = $this->_ritPermitido($conSql);
        if (!$permiso['ok']) {
            echo json_encode(['ok' => 0, 'mensaje' => $permiso['mensaje']]);
            return;
        }
        $idContribuyente = $permiso['id'];
        $idUsuario       = $permiso['usuario'];

        /*
         * Incompleto no se firma: ni sin los soportes obligatorios (cliente,
         * 2026-08-26) ni sin los campos obligatorios (2026-09-25). Es la misma
         * regla que impide guardarlo, aplicada a lo que está en la base, que es
         * lo que se firma: un RIT guardado a medias antes de esa regla tampoco
         * pasa. Se comprueba ANTES de consumir el OTP, para no gastar el código.
         */
        $faltan = $this->_faltantesRit($conSql, $idContribuyente);
        if ($faltan !== null) {
            echo json_encode(['ok' => 0, 'mensaje' => $faltan]);
            return;
        }

        // --- OTP: se valida y se consume aqui mismo ---
        $errorCodigo = $this->_consumirCodigo($conSql, $idUsuario, 'rit');
        if ($errorCodigo !== null) {
            echo json_encode(['ok' => 0, 'mensaje' => $errorCodigo]);
            return;
        }

        // --- Huella de lo que se esta firmando ---
        $hash = \erpsoftsas\RitFirma::hashActual($conSql, $idContribuyente);

        if ($hash === '') {
            echo json_encode(['ok' => 0, 'mensaje' => 'No se encontró el RIT del contribuyente.']);
            return;
        }

        $usuario = $conSql->obnerFila($conSql->consultar(
            "SELECT usu_Nombres AS usu_Nombre, usu_Correo FROM conf_usuarios WHERE usu_Id = ?",
            [$idUsuario]
        ));

        $conSql->consultar(
            "INSERT INTO ind_rit_firmas
                 (rif_IdContribuyente, rif_IdUsuario, rif_NombreUsuario, rif_EmailUsuario,
                  rif_Hash, rif_Opcion)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $idContribuyente,
                $idUsuario,
                $usuario['usu_Nombre'] ?? '',
                $usuario['usu_Correo'] ?? '',
                $hash,
                ((int) ($_POST['opcion'] ?? 0)) ?: null,
            ]
        );

        echo json_encode([
            'ok'      => 1,
            'mensaje' => 'RIT firmado correctamente',
            'nombre'  => $usuario['usu_Nombre'] ?? '',
        ]);
    }

    /**
     * funcion 10 - Estado de firma del RIT.
     * Contesta si esta firmado AHORA: una firma anterior a la ultima novedad
     * no cuenta (ver class.ritFirma.php).
     */
    private function _consultarFirmaRit()
    {
        header('Content-type: application/json');

        $conSql = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $permiso = $this->_ritPermitido($conSql);
        if (!$permiso['ok']) {
            echo json_encode(['ok' => 0, 'mensaje' => $permiso['mensaje']]);
            return;
        }

        include_once SERVER . '/business/class.ritFirma.php';
        $estado = \erpsoftsas\RitFirma::firmaVigente($conSql, $permiso['id']);

        $fmt = function ($f) {
            if (!$f) { return null; }
            return [
                'nombre' => $f['rif_NombreUsuario'],
                'fecha'  => ($f['rif_FechaHora'] instanceof \DateTime)
                                ? $f['rif_FechaHora']->format('Y-m-d H:i:s')
                                : (string) $f['rif_FechaHora'],
            ];
        };

        echo json_encode([
            'ok'             => 1,
            'firmado'        => $estado['firmado'] ? 1 : 0,
            'firma'          => $fmt($estado['firma']),
            // Hubo firma, pero el RIT cambio despues: la pantalla lo dice en
            // vez de mostrar un "sin firmar" que pareceria que nunca se firmo.
            'desactualizada' => $fmt($estado['desactualizada']),
        ]);
    }

}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \erpsoftsas\microservicios\firmas\FirmasAPI::run();
}
