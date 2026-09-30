<?php
namespace erpsoftsas;
include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/DAO/DAO_Usuario.php';
include_once SERVER . '/business/DAO/DAO_Contribuyentes.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER . '/business/class.permisosRol.php';
include_once SERVER.'/business/controller/class.logs.php';

class ControladorUsuarios extends \erpsoftsas\Cabecera {

    private $_funcion;
    private $_ok;
    private $_mensaje;   
        
    public static function run() {
        //\erpsoftsas\SesionUsuario::verificarSesion();
        
        $_obj = new self();
        $_obj->_funcion = $_POST['funcion'] ?? null;

        /*
         * QUIEN PUEDE LLAMAR CADA FUNCION (revision de seguridad 2026-09-28).
         *
         * La verificacion de sesion estaba comentada: sin iniciar sesion se
         * podia listar todas las cuentas (con la clave cifrada), editar la de
         * cualquiera, inactivarlas, e inscribirse como administrador mandando
         * id_rol=1. Ahora:
         *   1 crear       publica (inscripcion), pero sin "Crear y editar
         *                 usuarios" el rol se fuerza a contribuyente (4).
         *   2 editar      "Crear y editar usuarios".
         *   3 consultar   un rol de la Alcaldia o "Ver usuarios": Dependencias
         *                 lista los responsables.
         *   4 inactivar   "Activar e inactivar usuarios".
         * (Interruptores del panel de Roles desde el 2026-09-29; antes, el
         * boton 26 del menu y los roles 1 y 2 por numero.)
         *   5 recuperar   publica (olvide mi contrasena).
         *   6 cambiar     la propia clave, con sesion.
         * Solo el administrador (rol 1) da o toca cuentas de administrador.
         */
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }
        $funcion = (int) $_obj->_funcion;
        $negado  = null;
        if ($funcion === 2 && !\erpsoftsas\PermisosRol::tiene('usuarios.editar')) {
            $negado = self::_idSesion() > 0 ? \erpsoftsas\PermisosRol::mensaje('usuarios.editar') : 'No tiene permiso para administrar usuarios.';
        } elseif ($funcion === 4 && !\erpsoftsas\PermisosRol::tiene('usuarios.estado')) {
            $negado = self::_idSesion() > 0 ? \erpsoftsas\PermisosRol::mensaje('usuarios.estado') : 'No tiene permiso para administrar usuarios.';
        } elseif ($funcion === 3 && !\erpsoftsas\PermisosRol::tieneAlguno(['usuarios.ver', 'usuarios.editar'])) {
            // Antes bastaba ser de la Alcaldia (la pantalla vieja de Dependencias,
            // que el menu ya no ofrece): una cuenta sin interruptores, o de un rol
            // inactivo, bajaba todas las cuentas con documento, correo y telefono.
            $negado = self::_idSesion() > 0 ? \erpsoftsas\PermisosRol::mensaje('usuarios.ver') : 'No tiene permiso para consultar usuarios.';
        } elseif ($funcion === 6 && self::_idSesion() <= 0) {
            $negado = 'Debe iniciar sesión.';
        }
        if ($negado !== null) {
            header('Content-type: application/json');
            echo json_encode([
                'ok' => 0,
                'mensaje' => $negado,
                'datos' => '',
                // Sesion vencida: dist/menu.php lleva al login con un aviso.
                'sinSesion' => self::_idSesion() > 0 ? 0 : 1,
            ]);
            return;
        }

        try {
            //$con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
            //$con->begin();
            $respuesta = null;
            switch ($_obj->_funcion) {
                case 1:
                    $respuesta = $_obj->_agregarUsuario();
                    break;
                case 2:
                    $respuesta = $_obj->_editarUsuario();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarUsuarios();
                    break; 
                case 4:
                    $respuesta = $_obj->_inactivarUsuarios();
                    break; 
                case 5:
                    $respuesta = $_obj->_recuperarUsuario();
                    break;
                case 6:
                    $respuesta = $_obj->_cambiarClave();
                    break;
            }
            //$con->commit();
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
            
        } catch (\erpsoftsas\UsuariosException $e) {
            //$con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            //$_obj->cabeceras();
            header('Content-type: application/json');
            echo json_encode($arrRespu);
        } catch (\Throwable $e) {
            // Sin esto, cualquier error de la base (un dato que no cabe en su
            // columna, por ejemplo) salia como un 500 con el cuerpo vacio: la
            // inscripcion publica no decia NADA y la pantalla de Usuarios solo
            // "Error de conexion". El detalle va al log; al usuario, un aviso.
            error_log('[usuarios] funcion ' . ($_POST['funcion'] ?? '?') . ': ' . $e->getMessage());
            header('Content-type: application/json');
            echo json_encode(array(
                "ok"      => 0,
                "mensaje" => "No se pudo completar la operación. Revise los datos e intente de nuevo; "
                           . "si el problema sigue, comuníquese con la Secretaría de Hacienda.",
                "datos"   => ""
            ));
        }
    }

    /** Usuario de la sesion, o 0 sin sesion. */
    private static function _idSesion()
    {
        return (int) ($_SESSION['id_usuario'] ?? 0);
    }

    private static function _rolSesion()
    {
        return self::_idSesion() > 0 ? (int) ($_SESSION['id_Rol'] ?? 0) : 0;
    }

    /** Rol de la Alcaldia (tipo de rol, migracion 040; sin ella, 1 y 2). */
    private static function _esAlcaldia()
    {
        return \erpsoftsas\PermisosRol::esAlcaldia();
    }

    /**
     * Puede administrar usuarios: el administrador, o un rol con el permiso del
     * boton "Usuarios" del menu (26). Es la misma regla con la que el menu deja
     * entrar a usuario.php (core/menu.js -> class.permisos.php funcion 3).
     */
    private static function _puedeGestionar()
    {
        // "Crear y editar usuarios" del panel de Roles (antes, el boton 26).
        return \erpsoftsas\PermisosRol::tiene('usuarios.editar');
    }

    /**
     * Aviso si el rol no tiene NINGUN permiso cargado (conf_permisos): el login
     * no deja entrar a esas cuentas ("El sistema no pudo cargar los
     * privilegios"). Pasaba con "Internos Alcaldia" (rol 2), que el cliente
     * todavia no ha configurado: la cuenta se creaba bien y despues no podia
     * ingresar. No se bloquea -la Alcaldia puede dejar las cuentas listas-, se
     * dice. El administrador (rol 1) entra siempre.
     */
    private static function _avisoRolSinPermisos($rol)
    {
        $rol = (int) $rol;
        if (\erpsoftsas\PermisosRol::esAdministrador($rol)) { return ''; }
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
        if (\erpsoftsas\PermisosRol::hayCatalogo()) {
            // Lo mismo que mira el login: que el rol tenga algun interruptor
            // prendido (y que este activo).
            if (\erpsoftsas\PermisosRol::claves($rol)) { return ''; }
        } else {
            $hay = $con->obnerFila($con->consultar(
                "SELECT TOP 1 1 AS x FROM conf_permisos WHERE per_IdRol = ?", [$rol]
            ));
            if ($hay) { return ''; }
        }
        $nombre = $con->obnerFila($con->consultar("SELECT rol_Nombre AS n FROM conf_rol WHERE rol_Id = ?", [$rol]));
        return 'El rol «' . trim((string) ($nombre['n'] ?? $rol)) . '» todavía no tiene permisos asignados: '
             . 'esta cuenta no podrá ingresar hasta que se le asignen en Usuarios y roles > Roles.';
    }

    /** Respuesta de "ya existe" para _cuentaRepetida (1 correo, 2 documento, 3 usuario). */
    private function _avisarRepetida($cual)
    {
        $mensajes = [
            1 => [2, 'Ya existe un usuario con el mismo email'],
            2 => [3, 'Ya existe un usuario con la misma identificación'],
            3 => [4, 'Ya existe un usuario con el mismo Usuario'],
        ];
        [$this->_ok, $this->_mensaje] = $mensajes[$cual] ?? [0, 'Ya existe una cuenta con esos datos'];
        return false;
    }

    /**
     * El candado de los documentos (el mismo de la creacion en Contribuyentes),
     * hasta el fin de la transaccion de quien llama. Si no se obtiene en 15 s,
     * excepcion: seguir sin el permitiria repetidos.
     */
    private static function _candadoDocumento($con)
    {
        $f = $con->obnerFila($con->consultar(
            "SET NOCOUNT ON; DECLARE @r INT;
             EXEC @r = sp_getapplock @Resource = 'erp_contribuyente_documento', @LockMode = 'Exclusive',
                                     @LockOwner = 'Transaction', @LockTimeout = 15000;
             SELECT @r AS r;"
        ));
        if (!$f || (int) $f['r'] < 0) {
            throw new \RuntimeException('No se obtuvo el candado de documentos (' . ($f['r'] ?? 'sin respuesta') . ')');
        }
    }

    /** Mensaje si el rol no existe o esta inactivo; null si sirve. */
    private static function _rolNoValido($rol)
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
        $f = $con->obnerFila($con->consultar(
            "SELECT rol_Estado AS e FROM conf_rol WHERE rol_Id = ?", [(int) $rol]
        ));
        if (!$f) { return 'Escoja un rol válido.'; }
        if ((int) $f['e'] !== 1) { return 'Ese rol está inactivo: actívelo en Roles o escoja otro.'; }
        return null;
    }

    /** Rol actual de una cuenta, o 0 si no existe. */
    private static function _rolDeCuenta($id)
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
        $f = $con->obnerFila($con->consultar("SELECT usu_Rol AS r FROM conf_usuarios WHERE usu_Id = ?", [(int) $id]));
        return $f ? (int) $f['r'] : 0;
    }

    /**
     * Dos correos son el mismo si solo se diferencian en la caja o en espacios.
     *
     * Es el criterio de SQL Server, que es quien acaba guardandolos, y por eso
     * es el que hay que usar para decidir si algo esta repetido. Vacio nunca
     * cuenta como repetido: hay cuentas sin correo y no son duplicadas entre si.
     */
    private static function _mismoCorreo($a, $b)
    {
        $a = mb_strtolower(trim((string) $a));
        $b = mb_strtolower(trim((string) $b));

        return $a !== '' && $a === $b;
    }

    /** Tipos de documento que existen en el sistema: 1 C.C., 3 C.E., 4 pasaporte, 5 NIT. */
    const TIPOS_DOCUMENTO = [1, 3, 4, 5];

    /**
     * Lee y valida los datos de una cuenta. Crear y editar usan la MISMA regla
     * (revision 2026-09-28): antes cada uno validaba distinto, y por Editar se
     * colaba lo que Crear rechazaba.
     *
     * $esAlcaldia: las cuentas de la Alcaldia (roles 1 y 2) no llevan
     * contribuyente, asi que el documento es opcional y solo cuentan los largos
     * de la cuenta; las demas crean o usan un contribuyente y tienen que caber
     * tambien en sus columnas.
     *
     * @return array los datos normalizados, o ['error' => mensaje]
     */
    private static function _leerCuenta($esAlcaldia)
    {
        $texto = function ($campo) { return trim((string) ($_POST[$campo] ?? '')); };

        $c = [
            'nombres'       => $texto('nombres'),
            'apellidos'     => $texto('apellidos'),
            'direccion'     => $texto('direccion'),
            'email'         => $texto('email'),
            'usuario'       => $texto('usuario'),
            'documento'     => $texto('numeroDocumento'),
            'tipoDocumento' => (int) $texto('idTipoDocumento'),
            'tipoPersona'   => (int) $texto('tipoPersona'),
            // Municipio de residencia: lo pide la inscripcion publica (index.php).
            // Sin el, 0 = "sin escoger": el RIT no deja guardarse hasta que se
            // elija. Antes caia en 1 (Tunja), que es justo el "municipio de
            // registro carga mal" del punto 5.
            'idCiudad'      => ctype_digit($texto('idCiudad')) ? (int) $texto('idCiudad') : 0,
            // El telefono se guarda solo con sus digitos: "310 123 4567",
            // "310-123-4567" y "+57 3101234567" son el mismo numero, y la columna
            // del contribuyente es bigint. Vacio se admite (en Usuarios no es
            // obligatorio).
            'telefono'      => preg_replace('/\D/', '', (string) ($_POST['telefono'] ?? '')),
        ];

        if ($c['telefono'] !== '' && (strlen($c['telefono']) < 7 || strlen($c['telefono']) > 15)) {
            return ['error' => 'El teléfono debe tener entre 7 y 15 dígitos.'];
        }

        /*
         * El documento, si viene, SOLO con digitos y para CUALQUIER rol. La
         * columna de la cuenta es texto, pero DAO_Usuario la cruza con
         * ind_NumeroIdentificacion (INT) en la subconsulta usu_idContibuyente, y
         * SQL Server convierte el texto a INT: un "1.5" o un "1e5" (un input
         * type=number los acepta) hacia fallar esa consulta, y con ella el
         * listado ENTERO de Usuarios. El tope es el del INT (2.147.483.647). Sin
         * documento solo pueden quedar las cuentas de la Alcaldia.
         */
        $documentoValido = ctype_digit($c['documento']) && strlen($c['documento']) <= 10
            && (float) $c['documento'] <= 2147483647 && (int) $c['documento'] > 0;
        if (($c['documento'] !== '' || !$esAlcaldia) && !$documentoValido) {
            return ['error' => 'Escriba el número de documento solo con números: sin puntos, sin guion y sin el dígito de verificación.'];
        }

        // El tipo, obligatorio con documento o con contribuyente, y solo los que
        // existen en el sistema: el 2 ("Tarjeta de identidad") lo ofrecia esta
        // pantalla, pero el RIT, los PDF y Contribuyentes no lo conocen.
        if (($c['documento'] !== '' || !$esAlcaldia || $c['tipoDocumento'] !== 0)
            && !in_array($c['tipoDocumento'], self::TIPOS_DOCUMENTO, true)) {
            return ['error' => 'Elija el tipo de documento (C.C., C.E., pasaporte o NIT).'];
        }

        // Tipo de persona: el que se escogio; si no, sale del documento (NIT es
        // juridica), como hasta ahora.
        if (!in_array($c['tipoPersona'], [1, 2], true)) {
            $c['tipoPersona'] = ($c['tipoDocumento'] === 5) ? 2 : 1;
        }
        // La razon social de una juridica va ENTERA en el primer nombre del
        // contribuyente. Si en la pantalla quedo partida entre "Nombres" y
        // "Apellidos", se une: antes los apellidos se descartaban sin decir nada.
        $c['razon'] = trim($c['nombres'] . ' ' . $c['apellidos']);

        /*
         * Largos. La cuenta admite 500 caracteres (el usuario, 100), pero el
         * contribuyente guarda los nombres en varchar(100) y la direccion en
         * varchar(200): pasarse era un error de la base y un "No se pudo
         * completar el registro" que no decia que campo. Se avisa por campo, como
         * en la ficha de Contribuyentes (_leerFicha).
         */
        $largos = [
            ['nombres', 'los nombres', 500], ['apellidos', 'los apellidos', 500],
            ['direccion', 'la dirección', 500], ['email', 'el correo', 500],
            ['usuario', 'el usuario', 100],
        ];
        if (!$esAlcaldia) {
            if ($c['tipoPersona'] === 2) {
                $largos[] = ['razon', 'la razón social (nombres y apellidos juntos)', 100];
            } else {
                $largos[] = ['nombres', 'los nombres', 100];
                $largos[] = ['apellidos', 'los apellidos', 100];
            }
            $largos[] = ['direccion', 'la dirección', 200];
        }
        foreach ($largos as list($campo, $rotulo, $maximo)) {
            if (mb_strlen($c[$campo]) > $maximo) {
                return ['error' => 'El texto de ' . $rotulo . ' no puede pasar de ' . $maximo . ' caracteres.'];
            }
        }

        return $c;
    }

    /**
     * El contribuyente de una cuenta de contribuyente: si no hay ninguno con su
     * documento, se crea. Corre DENTRO de la transaccion de quien llama (crear o
     * editar la cuenta): quedan los dos o ninguno.
     *
     * Lo que la pantalla de Usuarios no pide se deduce: el DV sale del NIT, el
     * tipo de persona del documento si no se escogio (_leerCuenta), y el
     * municipio queda en 0, que el RIT trata como "sin escoger" y obliga a
     * llenar antes de guardar. Es mejor que el 1 (Tunja) que se ponia a ciegas.
     *
     * @return bool true si lo creo
     */
    private static function _asegurarContribuyente($con, array $c)
    {
        // El mismo candado que la creacion en Contribuyentes (dura hasta el fin
        // de la transaccion de quien llama): dos pedidos a la vez no crean dos
        // contribuyentes con el mismo documento.
        self::_candadoDocumento($con);

        // Mismo criterio que el enlace cuenta-contribuyente del resto del
        // sistema: por numero de documento. Si ya existe, no se duplica.
        $existe= $con->obnerFila($con->consultar(
            "SELECT TOP 1 ind_Id FROM ind_contribuyentes
              WHERE ind_NumeroIdentificacion = ? ORDER BY ind_Id",
            [(int) $c['documento']]
        ));
        if ($existe) { return false; }

        $juridica = ($c['tipoPersona'] === 2);
        // Con NIT el DV se calcula aqui (el que llegue del navegador no decide);
        // sin NIT el sistema no usa DV y la columna guarda 0.
        $dv = ($c['tipoDocumento'] === 5) ? \erpsoftsas\DAO_Contribuyentes::digitoVerificacion($c['documento']) : 0;

        $con->consultar(
            "INSERT INTO ind_contribuyentes
                 (ind_NumeroIdentificacion, ind_IdTipoDocumento, ind_DV, ind_PrimerNombre,
                  ind_PrimerApellido, ind_Direccion, ind_Telefono, ind_Email,
                  ind_Persona, ind_IdCiudad, ind_Estado)
             VALUES (?, ?, ?, ?, NULLIF(?, ''), ?, NULLIF(?, ''), NULLIF(?, ''), ?, ?, 1)",
            [
                (int) $c['documento'], $c['tipoDocumento'], $dv,
                // Persona juridica: la razon social entera (ver _leerCuenta) y
                // sin apellidos.
                $juridica ? $c['razon'] : $c['nombres'],
                $juridica ? '' : $c['apellidos'],
                $c['direccion'], $c['telefono'], $c['email'],
                $c['tipoPersona'], $c['idCiudad'],
            ]
        );
        return true;
    }

    /**
    *** Realiza el proceso de Crear Usuarios.
    **/
    protected function _agregarUsuario() {

        /*
         * CUENTA Y CONTRIBUYENTE: LAS DOS O NINGUNA (revision 2026-09-28).
         *
         * Antes se guardaba la cuenta y DESPUES se creaba el contribuyente, sin
         * transaccion y sin validar. Si el contribuyente fallaba -un telefono
         * "310 123 4567" en una columna bigint, o el DV y el tipo de persona que
         * la pantalla de Usuarios nunca manda-, la cuenta ya existia, la
         * respuesta era un 500 vacio y el RIT de esa persona decia "Su usuario
         * no esta asociado a un contribuyente": sin salida. Al reintentar,
         * "Email duplicado". Ahora se valida antes (_leerCuenta) y lo demas va en
         * una sola transaccion (_asegurarContribuyente).
         *
         * Los roles de la Alcaldia (1 administrador, 2 funcionario) NO reciben
         * contribuyente: no son contribuyentes, y el que se les creaba quedaba en
         * el padron como uno mas (asi aparecio el contribuyente con el documento
         * del administrador).
         */
        $rolNuevo   = (int) ($_POST['id_rol'] ?? 0);
        // La inscripcion publica (o quien no administra usuarios) solo crea
        // contribuyentes, llegue el rol que llegue en la peticion.
        if (!self::_puedeGestionar()) {
            $rolNuevo = 4;
        } elseif (\erpsoftsas\PermisosRol::esAdministrador($rolNuevo) && !\erpsoftsas\PermisosRol::esAdministrador()) {
            $this->_ok = 0;
            $this->_mensaje = 'Solo un administrador puede crear cuentas de administrador.';
            return false;
        } elseif (!\erpsoftsas\PermisosRol::puedeEntregarRol($rolNuevo)) {
            // Sin esto, "Crear y editar usuarios" bastaba para hacerse una
            // cuenta con un rol de la Alcaldia mas poderoso que el propio.
            $this->_ok = 0;
            $this->_mensaje = 'Ese rol tiene permisos que el suyo no tiene: solo el administrador puede asignarlo.';
            return false;
        } elseif (($errorRol = self::_rolNoValido($rolNuevo)) !== null) {
            $this->_ok = 0;
            $this->_mensaje = $errorRol;
            return false;
        }
        // Las cuentas de la Alcaldia no reciben contribuyente (tipo de rol).
        $esAlcaldia = \erpsoftsas\PermisosRol::esAlcaldia($rolNuevo);

        $c = self::_leerCuenta($esAlcaldia);
        if (isset($c['error'])) {
            $this->_ok = 0;
            $this->_mensaje = $c['error'];
            return false;
        }
        $documento = $c['documento'];

        $_objUsuario = new \erpsoftsas\DAO_Usuario();
        $_objUsuario->set_usu_Nombres($c['nombres']);
        $_objUsuario->set_usu_Apellidos($c['apellidos']);
        $_objUsuario->set_usu_Telefono($c['telefono']);
        $_objUsuario->set_usu_Direccion($c['direccion']);
        // Sin tipo (una cuenta de la Alcaldia sin documento) la columna queda NULL.
        $_objUsuario->set_usu_IdTipoDocumento($c['tipoDocumento'] ?: null);
        $_objUsuario->set_usu_NumeroDocumento($documento);
        $_objUsuario->set_usu_Correo($c['email']);
        $_objUsuario->set_usu_Password($_POST['clave'] ?? '');
        $_objUsuario->set_usu_Rol($rolNuevo);
        $_objUsuario->set_usu_Usuario($c['usuario']);

        $_objUsuario->set_usu_Estado(1);

        // Correo, documento y usuario no se repiten (ver _cuentaRepetida).
        $nomduplicado = self::_cuentaRepetida($_objUsuario->get_usu_Correo(), $documento,
                                              $_objUsuario->get_usu_Usuario(), 0);

        if ($nomduplicado !== 0) { return $this->_avisarRepetida($nomduplicado); }

        // La cuenta (por el DAO: la clave se guarda con HASHBYTES, como la
        // compara el login) y el contribuyente (parametrizado), en la misma
        // transaccion de la conexion compartida.
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
        try {
            $con->begin();

            // Otra vez, ya con el candado: dos inscripciones a la vez con el
            // mismo documento o correo pasaban las dos la revision de arriba.
            self::_candadoDocumento($con);
            $nomduplicado = self::_cuentaRepetida($_objUsuario->get_usu_Correo(), $documento,
                                                  $_objUsuario->get_usu_Usuario(), 0);
            if ($nomduplicado !== 0) {
                $con->rollback();
                return $this->_avisarRepetida($nomduplicado);
            }

            if (!$_objUsuario->guardar()) {
                throw new \Exception('No se pudo guardar la cuenta: ' . $_objUsuario->getMysqlError());
            }

            if (!$esAlcaldia) {
                self::_asegurarContribuyente($con, $c);
            }

            $con->commit();

        } catch (\Throwable $e) {
            try { $con->rollback(); } catch (\Throwable $e2) { /* ya no habia transaccion */ }
            error_log('[usuarios] no se creo la cuenta ' . $documento . ': ' . $e->getMessage());
            $this->_ok = 0;
            $this->_mensaje = 'No se pudo completar el registro y no quedó nada guardado. Revise los datos '
                            . 'e intente de nuevo; si el problema sigue, comuníquese con la Secretaría de Hacienda.';
            return false;
        }

        //$_objlogs = new logs();
        //$_objlogs->_insertLogs($id,1,2,7);
        $this->_ok = 1;
        $this->_mensaje = "Datos ingresados correctamente";

        $aviso = self::_avisoRolSinPermisos($rolNuevo);
        if ($aviso !== '') {
            $this->_mensaje .= '. ' . $aviso;
            return ['aviso' => $aviso];
        }

        // Antes aqui habia un segundo $_objUsuario->guardar(): un UPDATE repetido
        // de la cuenta recien creada, que ademas corria fuera de todo control.
        return true;
    }

    /**
     * Dos documentos son el mismo si los dos traen algo y valen lo mismo.
     *
     * Vacio nunca es repetido: las cuentas de la Alcaldia pueden no tener
     * documento, y dos de ellas no son la misma persona. Con == a secas, una
     * vieja con NULL "chocaba" con cualquier otra vacia (null == '' es true en
     * PHP 8) y editarla decia "Ya existe un usuario con la misma
     * identificacion". Se compara como numero, igual que el enlace con el
     * contribuyente (INT): "0123" y "123" son el mismo.
     */
    /**
     * ¿Otra cuenta ya usa este correo (1), documento (2) o usuario (3)? 0 si no.
     *
     * Antes se traian TODAS las cuentas (listarRegistros: SELECT * sin filtro)
     * y se comparaban aqui. La lista crece con cada inscripcion, y el driver de
     * SQL Server corta un resultado de mas de 10 MB ("Memory limit of 10240 KB
     * exceeded for buffered query"): asi fallaba editar establecimientos en
     * produccion (2026-09-29). Mismas reglas que _mismoCorreo (sin mayusculas
     * ni espacios) y _mismoDocumento (vacio no choca; los digitos se comparan
     * como numero, igual que el == de PHP).
     */
    private static function _cuentaRepetida($correo, $documento, $usuario, $excluirId)
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
        $excluirId = (int) $excluirId;

        $correo = mb_strtolower(trim((string) $correo));
        if ($correo !== '' && $con->obnerFila($con->consultar(
                "SELECT TOP 1 1 AS x FROM conf_usuarios WHERE LOWER(LTRIM(RTRIM(usu_Correo))) = ? AND usu_Id <> ?",
                [$correo, $excluirId]))) {
            return 1;
        }

        $documento = trim((string) $documento);
        if ($documento !== '') {
            $sql = (ctype_digit($documento) && strlen($documento) <= 30)
                ? "SELECT TOP 1 1 AS x FROM conf_usuarios
                    WHERE TRY_CONVERT(DECIMAL(38,0), LTRIM(RTRIM(usu_NumeroDocumento))) = CONVERT(DECIMAL(38,0), ?)
                      AND usu_Id <> ?"
                : "SELECT TOP 1 1 AS x FROM conf_usuarios WHERE LTRIM(RTRIM(usu_NumeroDocumento)) = ? AND usu_Id <> ?";
            if ($con->obnerFila($con->consultar($sql, [$documento, $excluirId]))) { return 2; }
        }

        $usuario = trim((string) $usuario);
        if ($usuario !== '' && $con->obnerFila($con->consultar(
                "SELECT TOP 1 1 AS x FROM conf_usuarios WHERE usu_Usuario = ? AND usu_Id <> ?",
                [$usuario, $excluirId]))) {
            return 3;
        }
        return 0;
    }

    private static function _mismoDocumento($a, $b)
    {
        $a = trim((string) $a);
        $b = trim((string) $b);

        return $a !== '' && $b !== '' && $a == $b;
    }

    /**
    *** Realiza el proceso de Editar usuarios.
    **/
    protected function _editarUsuario() {

        /*
         * EDITAR CON LA MISMA REGLA DE CREAR (revision 2026-09-28).
         *
         * - El documento se compara con los demas solo si viene (_mismoDocumento):
         *   dos cuentas de la Alcaldia sin documento chocaban entre si.
         * - Documento, tipo, telefono y largos, con la regla de crear (_leerCuenta).
         * - Pasar una cuenta de la Alcaldia a un rol de contribuyente le crea su
         *   contribuyente en la misma transaccion (_asegurarContribuyente). Antes
         *   quedaba sin el: entraba al RIT y leia "Su usuario no esta asociado a
         *   un contribuyente". Se crea en vez de rechazar el cambio porque una
         *   cuenta hecha con el rol equivocado no tiene otra salida: las cuentas
         *   no se borran, solo se inactivan.
         * - Se guardaba DOS veces (un segundo guardar() al final, fuera de todo
         *   control), igual que pasaba al crear.
         */

        // Va al WHERE del DAO sin comillas: siempre entero.
        $id = (int) ($_POST['id'] ?? 0);
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
        if ($id <= 0 || !$con->obnerFila($con->consultar("SELECT usu_Id FROM conf_usuarios WHERE usu_Id = ?", [$id]))) {
            $this->_ok = 0;
            $this->_mensaje = 'La cuenta que se quiere editar no existe.';
            return false;
        }

        $rolNuevo   = (int) ($_POST['id_rol'] ?? 0);
        if (!\erpsoftsas\PermisosRol::esAdministrador()
            && (\erpsoftsas\PermisosRol::esAdministrador($rolNuevo) || \erpsoftsas\PermisosRol::esAdministrador(self::_rolDeCuenta($id)))) {
            $this->_ok = 0;
            $this->_mensaje = 'Solo un administrador puede modificar cuentas de administrador.';
            return false;
        }
        // Sin escalar (panel de Roles, 2026-09-29): nadie se cambia su propio
        // rol (tampoco el administrador: si es el unico, se quedaria por
        // fuera), y quien no es administrador no toca cuentas ni entrega roles
        // de la Alcaldia con permisos que el suyo no tiene.
        if ($id === self::_idSesion() && $rolNuevo !== self::_rolDeCuenta($id)) {
            $this->_ok = 0;
            $this->_mensaje = 'No puede cambiar el rol de su propia cuenta. Pídaselo a otro administrador.';
            return false;
        }
        if (!\erpsoftsas\PermisosRol::esAdministrador()) {
            $rolActual = self::_rolDeCuenta($id);
            if (!\erpsoftsas\PermisosRol::puedeEntregarRol($rolActual)) {
                $this->_ok = 0;
                $this->_mensaje = 'Esa cuenta tiene un rol con permisos que el suyo no tiene: solo el administrador puede modificarla.';
                return false;
            }
            if (!\erpsoftsas\PermisosRol::puedeEntregarRol($rolNuevo)) {
                $this->_ok = 0;
                $this->_mensaje = 'Ese rol tiene permisos que el suyo no tiene: solo el administrador puede asignarlo.';
                return false;
            }
        }
        if (($errorRol = self::_rolNoValido($rolNuevo)) !== null) {
            $this->_ok = 0;
            $this->_mensaje = $errorRol;
            return false;
        }
        $esAlcaldia = \erpsoftsas\PermisosRol::esAlcaldia($rolNuevo);

        $c = self::_leerCuenta($esAlcaldia);
        if (isset($c['error'])) {
            $this->_ok = 0;
            $this->_mensaje = $c['error'];
            return false;
        }

        $_objUsuario = new \erpsoftsas\DAO_Usuario();
        $_objUsuario->set_usu_Id($id);
        $_objUsuario->set_usu_Nombres($c['nombres']);
        $_objUsuario->set_usu_Apellidos($c['apellidos']);
        $_objUsuario->set_usu_Telefono($c['telefono']);
        $_objUsuario->set_usu_Direccion($c['direccion']);
        $_objUsuario->set_usu_Usuario($c['usuario']);
        $_objUsuario->set_usu_NumeroDocumento($c['documento']);
        $_objUsuario->set_usu_IdTipoDocumento($c['tipoDocumento'] ?: null);
        $_objUsuario->set_usu_Correo($c['email']);
        // Vacia no se toca: el DAO solo guarda la clave si trae algo.
        $_objUsuario->set_usu_Password($_POST['clave'] ?? '');
        $_objUsuario->set_usu_Rol($rolNuevo);

        // Correo, documento y usuario no se repiten (ver _cuentaRepetida).
        $nomduplicado = self::_cuentaRepetida($_objUsuario->get_usu_Correo(), $c['documento'],
                                              $_objUsuario->get_usu_Usuario(), $id);

        if ($nomduplicado !== 0) { return $this->_avisarRepetida($nomduplicado); }

        $creado = false;
        try {
            $con->begin();

            self::_candadoDocumento($con);
            $nomduplicado = self::_cuentaRepetida($_objUsuario->get_usu_Correo(), $c['documento'],
                                                  $_objUsuario->get_usu_Usuario(), $id);
            if ($nomduplicado !== 0) {
                $con->rollback();
                return $this->_avisarRepetida($nomduplicado);
            }

            if (!$_objUsuario->guardar()) {
                throw new \Exception('No se pudo guardar la cuenta: ' . $_objUsuario->getMysqlError());
            }

            if (!$esAlcaldia) {
                $creado = self::_asegurarContribuyente($con, $c);
            }

            $con->commit();

        } catch (\Throwable $e) {
            try { $con->rollback(); } catch (\Throwable $e2) { /* ya no habia transaccion */ }
            error_log('[usuarios] no se edito la cuenta ' . $id . ': ' . $e->getMessage());
            $this->_ok = 0;
            $this->_mensaje = 'No se pudo actualizar la cuenta y no quedó nada a medias. Revise los datos '
                            . 'e intente de nuevo; si el problema sigue, comuníquese con la Secretaría de Hacienda.';
            return false;
        }

        //$_objlogs = new logs();
        //$_objlogs->_insertLogs($id,1,2,8);
        $this->_ok = 1;
        $this->_mensaje = $creado
            ? 'Datos ingresados correctamente. Se creó también su registro de contribuyente: el municipio y lo demás se completan en su RIT.'
            : 'Datos ingresados correctamente';

        $aviso = self::_avisoRolSinPermisos($rolNuevo);
        if ($aviso !== '') { $this->_mensaje .= ' ' . $aviso; }

        return ['contribuyenteCreado' => $creado ? 1 : 0, 'aviso' => $aviso];
    }
    
    /**
    *** Realiza el proceso de Listar usuarios, exeptuando el usuario enviado por parametro.
    *** @param type $id_usuario
    **/  
    private function _listarUsuarios($id_usuario) {
       
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT * FROM conf_usuario WHERE usu_Id <> $id_usuario";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "Usuarios listados";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existen Usuarios";
            $row=[];
        }
        return $row;     
    }  
    
    /**
    *** Realiza el proceso de Consultar Usuarios.
    **/  
    private function _consultarUsuarios() {
       
        $_objUsu = new \erpsoftsas\DAO_Usuario();

        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objUsu->set_usu_Id((int) $_POST['id']);
            }    
        }

        if(isset($_POST['usu_Rol'])){
            if (!empty($_POST['usu_Rol']) || $_POST['usu_Rol'] != NULL ) {
                $_objUsu->set_usu_Rol((int) $_POST['usu_Rol']);
            }    
        }
        
        $_objUsu->habilita1ResultadoEnArray();
        $arrUsuarios = $_objUsu->consultar();
       
        if(is_array($arrUsuarios) && count($arrUsuarios)){
            $R = [];
            foreach($arrUsuarios as $obj){
                $fila = $obj->getArray();
                unset($fila['usu_Password']);   // la clave (cifrada) no sale nunca
                $R[] = $fila;
            }
            $this->_ok = 1;
            $this->_mensaje = "Usuarios listados con exito";
        }else{
            $R=[];
            $this->_ok = 0;
            $this->_mensaje = "No existen Usuarios";            
        }       
        return $R;
    }
    
    /**
    *** Realiza el proceso de Activar o Inactivar Usuarios.
    **/  
    protected function _inactivarUsuarios() {

        // Van al WHERE / SET del DAO sin parametros: siempre enteros.
        // Solo digitos: (int) de "0; UPDATE ..." daria 0 y se aceptaria.
        $crudoId     = trim((string) ($_POST['id'] ?? ''));
        $crudoEstado = trim((string) ($_POST['estado'] ?? ''));
        $id     = ctype_digit($crudoId) ? (int) $crudoId : 0;
        $estado = in_array($crudoEstado, ['0', '1'], true) ? (int) $crudoEstado : -1;
        $rolCuenta = self::_rolDeCuenta($id);
        if ($id <= 0 || $rolCuenta === 0 || !in_array($estado, [0, 1], true)) {
            $this->_ok = 0;
            $this->_mensaje = 'La cuenta no existe o el estado no es válido.';
            return false;
        }
        if ($id === self::_idSesion() && $estado === 0) {
            $this->_ok = 0;
            $this->_mensaje = 'No puede inactivar su propia cuenta.';
            return false;
        }
        if (\erpsoftsas\PermisosRol::esAdministrador($rolCuenta) && !\erpsoftsas\PermisosRol::esAdministrador()) {
            $this->_ok = 0;
            $this->_mensaje = 'Solo un administrador puede modificar cuentas de administrador.';
            return false;
        }
        if (!\erpsoftsas\PermisosRol::puedeEntregarRol($rolCuenta)) {
            $this->_ok = 0;
            $this->_mensaje = 'Esa cuenta tiene un rol con permisos que el suyo no tiene: solo el administrador puede modificarla.';
            return false;
        }

        $_objUsuario = new \erpsoftsas\DAO_Usuario();
        $_objUsuario->set_usu_Id($id);
        $_objUsuario->set_usu_Estado($estado);
        
        if(!$_objUsuario->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objUsuario->getMysqlError();
        }else{
            $id = $_objUsuario->get_usu_Id();
            //$_objlogs = new logs();
            //$_objlogs->_insertLogs($id,1,2,9);
            $this->_ok = 1;
            $this->_mensaje = "Usuario Activado/inactivado correctamente";
        }
        $fila = $_objUsuario->getArray();
        unset($fila['usu_Password']);
        return $fila;
    }


    /**
    *** Cambio de contraseña por el propio usuario (punto 1 solicitado por el
    *** cliente): antes solo existia el reseteo por correo con una clave
    *** temporal generada por el sistema, y no habia forma de volver a
    *** asignar una propia despues. Requiere la clave ACTUAL para autorizar
    *** el cambio -es la unica verificacion de identidad real en este flujo,
    *** ya que el resto del sistema confia en localStorage para saber quien
    *** esta logueado, igual que el resto de pantallas de esta app-.
    ***
    *** Nota sobre el hash: las contraseñas se guardan con
    *** HASHBYTES('SHA1', texto) vía DAO->guardar() (ver class.DAO.php,
    *** tipodato 'clave'), pero DAO->consultar() NO aplica ese mismo hash del
    *** lado del WHERE -hace una comparación literal-. Por eso, para
    *** verificar la clave actual, hay que replicar exactamente lo que hace
    *** el login real (business/controller/class.login.php): comparar contra
    *** sha1() calculado en PHP, no contra el texto plano. SQL Server hace el
    *** match sin importar mayusculas/minusculas porque la collation por
    *** defecto es case-insensitive.
    ***
    *** OJO con la inyeccion SQL: class.DAO.php arma las consultas
    *** concatenando strings, NO con parametros. Por eso aqui:
    ***   - usu_Id se castea a int antes de tocar el DAO (en guardar() va al
    ***     WHERE del UPDATE sin comillas siquiera: " WHERE usu_Id = $valor").
    ***   - la comilla simple de la clave nueva la escapa el DAO
    ***     (DAOGeneral::_literalSql, desde 2026-09-28). Antes se escapaba
    ***     AQUI; con el escape en el DAO eso la duplicaba dos veces, HASHBYTES
    ***     recibia dos comillas y el login (sha1() sobre el texto crudo) ya no
    ***     coincidia. No volver a escaparla en este metodo.
    **/
    protected function _cambiarClave() {

        // La cuenta es la de la sesion: con el usu_Id de la peticion se podia
        // probar claves de otra cuenta.
        $idUsuario = self::_idSesion();
        $claveActual = $_POST['claveActual'] ?? '';
        $claveNueva = $_POST['claveNueva'] ?? '';

        if ($idUsuario <= 0 || $claveActual === '' || $claveNueva === '') {
            $this->_ok = 0;
            $this->_mensaje = 'Datos incompletos';
            return false;
        }

        // Misma regla que se valida en el navegador (login.js
        // validarPassword): min 8, mayuscula, minuscula, numero. Se repite
        // aqui porque el navegador se puede saltar llamando el endpoint
        // directamente.
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $claveNueva)) {
            $this->_ok = 0;
            $this->_mensaje = 'La nueva contraseña debe tener mínimo 8 caracteres, incluir mayúscula, minúscula y número.';
            return false;
        }

        // Verificar la clave actual con la misma huella del login; si la cuenta
        // es de antes del 2026-09-29 y la clave tiene ñ o tildes, con la
        // anterior (ver DAOGeneral::hashClave).
        $encontrado = false;
        foreach (array_unique([\erpsoftsas\DAOGeneral::hashClave($claveActual),
                               \erpsoftsas\DAOGeneral::hashClaveAnterior($claveActual)]) as $huella) {
            $_objVerif = new \erpsoftsas\DAO_Usuario();
            $_objVerif->set_usu_Id($idUsuario);
            $_objVerif->set_usu_Password($huella);
            if ($_objVerif->consultar()) { $encontrado = true; break; }
        }

        if (!$encontrado) {
            $this->_ok = 0;
            $this->_mensaje = 'La contraseña actual no es correcta';
            return false;
        }

        $_objUsuario = new \erpsoftsas\DAO_Usuario();
        $_objUsuario->set_usu_Id($idUsuario);
        // Sin escapar: lo hace el DAO (ver la nota de arriba).
        $_objUsuario->set_usu_Password($claveNueva);

        if (!$_objUsuario->guardar()) {
            $this->_ok = 0;
            $this->_mensaje = 'No se pudo actualizar la contraseña';
            return false;
        }

        $this->_ok = 1;
        $this->_mensaje = 'Contraseña actualizada correctamente';
        return true;
    }

    /**
    *** Proceso de recuperación de contraseña
    **/
    protected function _recuperarUsuario() {

        // La persona se identifica con su NIT o cedula (no con el correo):
        // es el dato que si recuerda. El correo destino se toma del que ya
        // tiene registrado en su cuenta.
        if (empty($_POST['documento'])) {
            $this->_ok = 0;
            $this->_mensaje = 'Debe ingresar su NIT o cédula';
            return false;
        }

        $documento = trim($_POST['documento']);

        $_objUsuario = new \erpsoftsas\DAO_Usuario();
        $_objUsuario->set_usu_NumeroDocumento($documento);
        $_objUsuario->habilita1ResultadoEnArray();

        $usuario = $_objUsuario->consultar();

        if (!is_array($usuario) || !count($usuario)) {
            $this->_ok = 0;
            $this->_mensaje = 'El documento no se encuentra registrado';
            return false;
        }

        // Usuario encontrado
        $usuario = $usuario[0];

        // Sin correo registrado no hay a donde enviar la clave temporal.
        $email = trim((string) $usuario->get_usu_Correo());

        if ($email === '') {
            $this->_ok = 0;
            $this->_mensaje = 'La cuenta no tiene un correo registrado. '
                            . 'Comuníquese con la Secretaría de Hacienda.';
            return false;
        }

        // Generar clave temporal
        $claveTemporal = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 8);

        $_objUsuario->set_usu_Id($usuario->get_usu_Id());
        $_objUsuario->set_usu_Password($claveTemporal);

        if (!$_objUsuario->guardar()) {
            $this->_ok = 0;
            $this->_mensaje = 'No se pudo actualizar la contraseña';
            return false;
        }

        // Enviar correo SOLO si se actualizó la contraseña
        $this->_enviarCorreoRecuperacion(
            $email,
            $usuario->get_usu_Nombres(),
            $claveTemporal
        );

        $this->_ok = 1;
        $this->_mensaje = 'Correo de recuperación enviado';

        // Se devuelve el correo enmascarado para que la persona confirme a
        // donde llego, sin revelar la direccion completa a quien solo tecleo
        // un numero de documento.
        return array('correo' => $this->_enmascararCorreo($email));
    }

    /**
     * Convierte "contribuyente@dominio.com" en "co***@dominio.com".
     */
    protected function _enmascararCorreo($email)
    {
        $partes = explode('@', $email);

        if (count($partes) !== 2) {
            return '';
        }

        $usuario = $partes[0];
        $visible = mb_substr($usuario, 0, 2);

        return $visible . '***@' . $partes[1];
    }

     /** Función para enviar el correo de recuperación de contraseña
     * @param string $email Correo del usuario
     * @param string $nombre Nombre del usuario
     * @param string $claveTemporal Clave temporal generada
     */
        protected function _enviarCorreoRecuperacion($email, $nombre, $claveTemporal)
        {
            require_once __DIR__ . '/../php_mailer/Exception.php';
            require_once __DIR__ . '/../php_mailer/PHPMailer.php';
            require_once __DIR__ . '/../php_mailer/SMTP.php';

            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'gestor.documental.alcaldia@gmail.com';
                $mail->Password   = 'igzq hteh qrru rmbu'; // contraseña de aplicación
                $mail->SMTPSecure = 'tls';
                $mail->Port       = 587;
                $mail->CharSet = 'UTF-8';

                $mail->setFrom('gestor.documental.alcaldia@gmail.com', 'Alcaldia de Paipa');
                $mail->addAddress($email, $nombre);

                $mail->isHTML(true);
                $mail->Subject = 'Recuperación de contraseña Industria y Comercio';
                $mail->Body = "
                    <p>Hola <strong>{$nombre}</strong>,</p>
                    <p>Tu contraseña es:</p>
                    <h2>{$claveTemporal}</h2>
                    <p>Alcaldia de Paipa</p>
                ";

                $mail->send();
                return true;

            } catch (\Exception $e) {
                return false;
            }
        }

}

class UsuariosException extends \Exception{}

    \erpsoftsas\ControladorUsuarios::run();

