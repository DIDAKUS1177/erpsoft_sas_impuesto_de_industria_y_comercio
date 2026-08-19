<?php
namespace erpsoftsas;

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/DAO/DAO_Establecimientos.php';
include_once SERVER . '/business/DAO/DAO_ActividadEstablecimiento.php';

include_once SERVER . '/business/class.sessions.php';
include_once SERVER . '/business/controller/class.logs.php';

class ControladorEstablecimientos extends \erpsoftsas\Cabecera 
{
    private $_funcion;
    private $_ok;
    private $_mensaje;

    public static function run() 
    {
        $_obj = new self();
        $_obj->_funcion = isset($_POST['funcion']) ? $_POST['funcion'] : null;

        try {
            //$con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
            //$con->begin();
            $respuesta = null;
            switch ($_obj->_funcion) {
                case 1:
                    $respuesta = $_obj->_agregarEstablecimientos();
                    break;
                case 2:
                    $respuesta = $_obj->_editarEstablecimientos();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarEstablecimientos();
                    break;
                case 4:
                    $respuesta = $_obj->_inactivarEstablecimientos();
                    break;
                case 20:
                    $respuesta = $_obj->_guardarCorreosContadorRevisor();
                    break;
                case 21:
                    $respuesta = $_obj->_guardarCese();
                    break;
                default:
                    throw new \erpsoftsas\EstablecimientosException("Función no válida", 0);
            }

            //$con->commit();

            header('Content-type: application/json');
            echo json_encode(array(
                "ok" => $_obj->_ok, 
                "mensaje" => $_obj->_mensaje, 
                "datos" => $respuesta
            ));

        } catch (\erpsoftsas\EstablecimientosException $e) {
            //$con->rollback();
            $arrRespu = array(
                "ok"      => $e->getCode(), 
                "mensaje" => "Error: " . $e->getMessage(), 
                "datos"   => ""
            );
            header('Content-type: application/json');
            echo json_encode($arrRespu);
        }
    }

    protected function _agregarEstablecimientos()
    {
        $errorCodigo = self::_validarCodigo();
        if ($errorCodigo !== null) {
            $this->_ok = 0;
            $this->_mensaje = $errorCodigo;
            return [];
        }

        self::_descartarUbicacion();

        // est_IdContribuyente llegaba tal cual del navegador: un contribuyente
        // podia crear locales a nombre de otro con solo cambiar ese campo.
        // Para todo rol que no sea Alcaldia se fija al de la propia sesion.
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }
        $rol = isset($_SESSION['id_Rol']) ? (int) $_SESSION['id_Rol'] : 0;

        if (!in_array($rol, [1, 2], true)) {
            $propio = self::_contribuyenteDeLaSesion($con);
            if (!$propio) {
                $this->_ok = 0;
                $this->_mensaje = 'No se pudo establecer a qué contribuyente pertenece el establecimiento';
                return [];
            }
            $_POST['est_IdContribuyente'] = $propio;
        }

        self::_filtrarCese();

        $_obj = new \erpsoftsas\DAO_Establecimientos();

        foreach ($_POST as $campo => $valor) {
            $metodo = 'set_' . $campo;
            $_obj->$metodo($valor);
        }

        $nomUsurio = $_obj->listarRegistros($_obj->get_est_Id());
        $longitud = count($nomUsurio);
        $nomduplicado=0;

        for($i=0; $i<$longitud; $i++){
            if($nomUsurio[$i]['est_Codigo'] == $_obj->get_est_Codigo()){
               $nomduplicado=1;
                break;
            }
        }


        $_obj->set_est_Estado(1);

         if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe ese Codigo en un establecimiento';
            $return= false; 
        }else{
                
            if (!$_obj->guardar()) {
                $this->_ok = 0;
                $this->_mensaje = $_obj->getMysqlError();
            } else {
                $id = $_obj->get_est_Id(); 

                // GUARDAR ACTIVIDADES
                if(isset($_POST['actividades'])){
                    $actividades = json_decode($_POST['actividades'], true);
                    foreach($actividades as $a){

                        $_objAct = new \erpsoftsas\DAO_ActividadEstablecimiento();

                        $_objAct->set_ace_IdCodigoActividad($a['ace_IdCodigoActividad']);
                        $_objAct->set_ace_IdEstablecimiento($id);
                        $_objAct->set_ace_Anio($a['ace_Anio']);
                        $_objAct->guardar();
                    }
                }

                $this->_ok = 1;
                $this->_mensaje = "Establecimiento agregado correctamente. ID = $id";
            }
            $return= $_obj->guardar();
        }
        return $return;
    }

    protected function _editarEstablecimientos()
    {
        $errorCodigo = self::_validarCodigo();
        if ($errorCodigo !== null) {
            $this->_ok = 0;
            $this->_mensaje = $errorCodigo;
            return [];
        }

        self::_descartarUbicacion();

        // Se editaba por est_Id sin mirar de quien era: cualquiera con sesion
        // podia modificar el local de otro contribuyente cambiando ese id.
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
        if (!self::_puedeSobreEstablecimiento($_POST['est_Id'] ?? 0, $con)) {
            $this->_ok = 0;
            $this->_mensaje = 'No tiene permiso para modificar este establecimiento';
            return [];
        }

        // Tampoco puede reasignarse a otro dueño por la puerta de atras.
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }
        $rol = isset($_SESSION['id_Rol']) ? (int) $_SESSION['id_Rol'] : 0;
        if (!in_array($rol, [1, 2], true)) {
            unset($_POST['est_IdContribuyente']);
        }

        self::_filtrarCese();

        $_obj = new \erpsoftsas\DAO_Establecimientos();
        $_obj->set_est_Id($_POST['est_Id'] ?? null);

        foreach ($_POST as $campo => $valor) {
            $metodo = 'set_' . $campo;
            $_obj->$metodo($valor);
        }

        $nomUsurio = $_obj->listarRegistros($_obj->get_est_Id());
        $longitud = count($nomUsurio);
        $nomduplicado=0;

        for($i=0; $i<$longitud; $i++){
            if($nomUsurio[$i]['est_Codigo'] == $_obj->get_est_Codigo()){
               $nomduplicado=1;
                break;
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe ese Codigo en un establecimiento';
            $return= false; 
        }else{

            if (!$_obj->guardar()) {
                $this->_ok = 0;
                $this->_mensaje = $_obj->getMysqlError();
            } else {
                $id = $_obj->get_est_Id();

                /*
                 * ACTIVIDADES ECONOMICAS
                 *
                 * Antes esto era un DELETE incondicional seguido de un
                 * "if (isset($_POST['actividades']))" para reinsertar. El
                 * problema: las 4 pantallas que editan mandan SIEMPRE la
                 * clave (formData.actividades = JSON.stringify(actividades)),
                 * asi que isset() es true incluso cuando el arreglo llega
                 * VACIO -que es lo que pasa si el modal se guarda antes de
                 * que cargarActividades() termine de pintar la tabla-. El
                 * DELETE corria, el foreach no insertaba nada, y las
                 * actividades se perdian sin aviso. Reproducido: un
                 * establecimiento con 2 actividades quedo en 0 enviando
                 * actividades=[].
                 *
                 * Ahora solo se reemplazan cuando llega al menos una. Un
                 * arreglo vacio se trata como "esta pantalla no trae
                 * actividades", no como "borralas todas": quitar todas las
                 * actividades dejaria al establecimiento sin poder declarar,
                 * y no es algo que deba pasar por accidente. Si mas adelante
                 * hace falta esa accion, debe ser explicita (un flag propio),
                 * nunca el efecto colateral de un arreglo vacio.
                 */
                $actividades = [];
                if (isset($_POST['actividades'])) {
                    $decodificadas = json_decode($_POST['actividades'], true);
                    if (is_array($decodificadas)) {
                        $actividades = $decodificadas;
                    }
                }

                if (count($actividades) > 0) {
                    $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

                    $con->consultar(
                        "DELETE FROM ind_actividad_establecimiento WHERE ace_IdEstablecimiento = ?",
                        [$id]
                    );

                    foreach ($actividades as $a) {
                        $_objAct = new \erpsoftsas\DAO_ActividadEstablecimiento();
                        $_objAct->set_ace_IdCodigoActividad($a['ace_IdCodigoActividad']);
                        $_objAct->set_ace_IdEstablecimiento($id);
                        $_objAct->set_ace_Anio($a['ace_Anio']);
                        $_objAct->guardar();
                    }
                } elseif (isset($_POST['actividades'])) {
                    error_log(
                        "establecimientos: se recibio actividades vacio para est_Id=$id; "
                        . "no se tocaron las actividades existentes (ver nota en _editarEstablecimientos)."
                    );
                }


                $this->_ok = 1;
                $this->_mensaje = "Establecimiento ID $id editado correctamente";
                $return = true;
            }
            // Antes aqui habia un segundo $_obj->guardar(): el UPDATE se
            // ejecutaba DOS veces por cada edicion, y ademas corria tambien
            // cuando el primero habia fallado, pisando el mensaje de error.
        }
        return $return;
    }

    
    /**
     * Contribuyente al que esta atado el usuario de la sesion, o null si no
     * hay sesion / no se puede resolver.
     *
     * Mismo criterio que ControladorAnexos::puedeOperarSobreEstablecimiento()
     * y ControladorContribuyentes::puedeOperarSobreContribuyente(): el vinculo
     * es conf_usuarios.usu_NumeroDocumento = ind_contribuyentes.ind_NumeroIdentificacion.
     * Se repite aqui en vez de reutilizarse porque class.contribuyentes.php
     * llama a run() al final del archivo: incluirlo desde otro controlador
     * dispararia su respuesta JSON.
     */
    private static function _contribuyenteDeLaSesion($con)
    {
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }
        if (empty($_SESSION['id_usuario'])) { return null; }

        $fila = $con->obnerFila($con->consultar(
            "SELECT c.ind_Id
               FROM ind_contribuyentes c
               INNER JOIN conf_usuarios u ON u.usu_NumeroDocumento = c.ind_NumeroIdentificacion
              WHERE u.usu_Id = ?",
            [(int) $_SESSION['id_usuario']]
        ));

        return isset($fila['ind_Id']) ? (int) $fila['ind_Id'] : null;
    }

    /**
     * true si la sesion actual puede tocar ESE establecimiento.
     * Alcaldia (rol 1 y 2) puede con todos; los demas solo con los suyos.
     */
    /**
     * Cese de actividades (puntos 14, 15 y 16 de la lista del cliente).
     *
     * Las columnas ya existian en ind_establecimientos desde antes; lo que
     * faltaba era conectarlas: en el formulario los campos estaban comentados
     * y el select se llamaba con_Causal, que no corresponde a ninguna columna,
     * asi que el cese nunca llegaba a guardarse.
     */
    private static function _camposCese()
    {
        return [
            'est_Fecha_cierre',
            'est_Causal',
            'est_Resolucion_cierre',
            'est_Observacion_cierre',
        ];
    }

    /** Rol 1 = Administrador en conf_rol, igual que en class.contribuyentes.php. */
    private static function _esAdministrador()
    {
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }
        return isset($_SESSION['id_Rol']) && (int) $_SESSION['id_Rol'] === 1;
    }

    /**
     * Punto 14: el cese lo registra UNICAMENTE el administrador. Si los campos
     * llegan desde otro rol se descartan en silencio -no se falla- para que el
     * contribuyente pueda seguir guardando el resto de su establecimiento.
     * Comprobado que hacia falta: antes un contribuyente podia fijarse su
     * propia fecha de cierre mandando el POST a mano.
     *
     * De paso se valida est_Causal, que en la base es varchar(1): cualquier
     * texto mas largo hacia fallar el guardado con un 500 de cuerpo vacio, que
     * en pantalla se ve como el generico "error de conexion".
     */

    /**
     * funcion 21 - Cese de actividades de UN establecimiento.
     *
     * Existe como endpoint propio, y no reusando la funcion 2 (editar), por
     * dos razones:
     *
     *  - La funcion 2 arrastra la validacion de codigo duplicado, que compara
     *    $_POST['est_Codigo'] contra los demas establecimientos. Al mandar
     *    solo los campos del cese ese codigo llega vacio y la comparacion
     *    puede dar positivo contra cualquier fila que tambien lo tenga vacio,
     *    rechazando un cese perfectamente valido.
     *
     *  - El cese lo captura ahora la pantalla del RIT (reunion 2026-08-19), y
     *    esa pantalla no tiene cargado el establecimiento completo. Mandar el
     *    formulario entero solo para cerrar un local seria pedirle datos que
     *    no tiene a la vista.
     *
     * El dato sigue viviendo en ind_establecimientos: lo que cesa es el LOCAL,
     * no la persona. Un contribuyente puede cerrar uno y seguir con los otros.
     */
    protected function _guardarCese()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $idEstablecimiento = (int) ($_POST['est_Id'] ?? 0);

        if (!self::_puedeSobreEstablecimiento($idEstablecimiento, $con)) {
            $this->_ok = 0;
            $this->_mensaje = 'No tiene permiso sobre este establecimiento';
            return [];
        }

        // El cese lo registra la Alcaldia. El readonly de la pantalla no basta:
        // se quita desde la consola del navegador.
        if (!self::_esAdministrador()) {
            $this->_ok = 0;
            $this->_mensaje = 'Solo la Alcaldía puede registrar el cese de actividades';
            return [];
        }

        // 1 Fusion, 2 Escision, 3 Liquidacion, 4 Otro. Cualquier otra cosa se
        // guarda como "sin causal" en vez de colarse a la base.
        $causal = trim((string) ($_POST['est_Causal'] ?? ''));
        if (!in_array($causal, ['1', '2', '3', '4'], true)) { $causal = ''; }

        $fecha = trim((string) ($_POST['est_Fecha_cierre'] ?? ''));
        $obs   = trim((string) ($_POST['est_Observacion_cierre'] ?? ''));

        // Sin fecha no hay cese: se limpian los tres campos juntos, para que no
        // quede una causal huerfana que haga ver el local como cerrado.
        if ($fecha === '') {
            $causal = '';
            $obs    = '';
        }

        $ok = $con->consultar(
            "UPDATE ind_establecimientos
                SET est_Fecha_cierre        = NULLIF(?, ''),
                    est_Causal              = NULLIF(?, ''),
                    est_Observacion_cierre  = NULLIF(?, '')
              WHERE est_Id = ?",
            [$fecha, $causal, $obs, $idEstablecimiento]
        );

        if ($ok === false) {
            $this->_ok = 0;
            $this->_mensaje = 'No se pudo guardar el cese';
            return [];
        }

        // Este controlador no da por buena una respuesta sola: hay que poner
        // _ok en 1 a mano (por defecto queda en null y el JS lo lee como fallo).
        $this->_ok = 1;
        $this->_mensaje = ($fecha === '')
            ? 'Se retiró el cese de actividades'
            : 'Cese de actividades guardado';

        return [];
    }


    /**
     * est_Codigo es una columna INT.
     *
     * El campo del formulario es de texto libre, asi que basta que alguien
     * escriba una letra para que SQL Server conteste "Conversion failed when
     * converting the varchar value 'ABC' to data type int". Esa excepcion no
     * se captura, el endpoint responde 500 con el cuerpo VACIO y la pantalla
     * solo alcanza a decir "error de conexion" -sin decir que campo, ni por
     * que-. Es exactamente el sintoma que reporto el cliente.
     *
     * Se valida aqui, en el servidor, y no solo en el input: un pattern de
     * HTML se salta desde la consola del navegador.
     *
     * Devuelve null si esta bien, o el mensaje de rechazo.
     */
    private static function _validarCodigo()
    {
        if (!isset($_POST['est_Codigo'])) { return null; }

        $codigo = trim((string) $_POST['est_Codigo']);

        // Vacio es valido: la columna admite NULL.
        if ($codigo === '') {
            $_POST['est_Codigo'] = null;
            return null;
        }

        if (!ctype_digit($codigo)) {
            return 'El código del establecimiento debe ser numérico (solo dígitos).';
        }

        // Fuera del rango de un INT de SQL Server tambien revienta la consulta.
        if (strlen($codigo) > 10 || (float) $codigo > 2147483647) {
            return 'El código del establecimiento es demasiado largo (máximo 2.147.483.647).';
        }

        return null;
    }


    /**
     * Ubicacion del establecimiento: est_Pais / est_Departamento / est_Ciudad.
     *
     * Estas tres columnas son VARCHAR(5). No caben "Colombia" (8) ni "Boyaca"
     * (6); solo "Paipa" entra, y por los pelos. Cualquier pantalla que mande el
     * NOMBRE hace que SQL Server conteste "String or binary data would be
     * truncated", la excepcion no se captura y el endpoint responde 500 con el
     * cuerpo vacio -de nuevo el "error de conexion" sin explicacion-.
     *
     * Por eso las 12 filas de la tabla tienen '1' en las tres: la ubicacion del
     * establecimiento NUNCA se ha llegado a guardar. Se comprobo que ningun
     * sitio las lee -ni los PDF ni las pantallas-, solo el DAO las declara.
     *
     * Se descartan aqui en vez de intentar guardarlas porque:
     *  - el sistema liquida el ICA de UN municipio, asi que la ubicacion de un
     *    establecimiento es siempre la misma y el cliente pidio justamente
     *    dejarla fija ("dejar bloqueado Paipa - Boyaca");
     *  - guardarlas de verdad exigiria ensanchar las columnas, y ensanchar algo
     *    que nadie lee no arregla nada.
     *
     * Si algun dia hace falta almacenarlas -por ejemplo para un municipio que
     * admita establecimientos de fuera-, hay que ensanchar las tres columnas en
     * una migracion ANTES de volver a enviarlas. No se borra ninguna: quedan
     * como estan.
     */
    private static function _descartarUbicacion()
    {
        foreach (['est_Pais', 'est_Departamento', 'est_Ciudad'] as $campo) {
            unset($_POST[$campo]);
        }
    }

    private static function _filtrarCese()
    {
        if (!self::_esAdministrador()) {
            foreach (self::_camposCese() as $campo) {
                unset($_POST[$campo]);
            }
            return;
        }

        if (isset($_POST['est_Causal'])) {
            $causal = trim((string) $_POST['est_Causal']);
            // 1 Fusion, 2 Escision, 3 Liquidacion, 4 Otro.
            $_POST['est_Causal'] = in_array($causal, ['1', '2', '3', '4'], true) ? $causal : '';
        }
    }

    private static function _puedeSobreEstablecimiento($idEstablecimiento, $con)
    {
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }
        if (empty($_SESSION['id_usuario'])) { return false; }

        $rol = isset($_SESSION['id_Rol']) ? (int) $_SESSION['id_Rol'] : 0;
        if (in_array($rol, [1, 2], true)) { return true; }

        $propio = self::_contribuyenteDeLaSesion($con);
        if (!$propio) { return false; }

        $fila = $con->obnerFila($con->consultar(
            "SELECT est_Id FROM ind_establecimientos
              WHERE est_Id = ? AND est_IdContribuyente = ?",
            [(int) $idEstablecimiento, $propio]
        ));

        return (bool) $fila;
    }

    private function _consultarEstablecimientos()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        // El filtro venia entero del cliente: quien no mandara
        // est_IdContribuyente recibia TODOS los establecimientos del
        // municipio, y quien mandara el de otro recibia los de ese otro
        // (comprobado: el usuario externo de prueba listaba 12
        // establecimientos de 6 contribuyentes distintos). Para todo rol que
        // no sea Alcaldia el filtro se fija aqui, en el servidor, pisando lo
        // que haya mandado el navegador.
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }
        $rol = isset($_SESSION['id_Rol']) ? (int) $_SESSION['id_Rol'] : 0;

        if (!in_array($rol, [1, 2], true)) {
            $propio = self::_contribuyenteDeLaSesion($con);
            if (!$propio) {
                $this->_ok = 0;
                $this->_mensaje = 'No se pudo establecer de qué contribuyente son los establecimientos';
                return [];
            }
            $_POST['est_IdContribuyente'] = $propio;
        }

        $_obj = new \erpsoftsas\DAO_Establecimientos();

        foreach ($_POST as $campo => $valor) {
            $metodo = 'set_' . $campo;
            $_obj->$metodo($valor);
        }

        $_obj->habilita1ResultadoEnArray();
        $arr = $_obj->consultar();

        if (is_array($arr) && count($arr)) {
            $R = [];
            

            $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

            foreach ($arr as $obj) {

                $est = $obj->getArray();

                // ============================
                // CORREOS DE CONTADOR / REVISOR
                // Viven en el contribuyente (la declaracion es una sola por
                // contribuyente), pero la pantalla del RIT los edita junto
                // con los demas datos del establecimiento.
                // ============================

                // Punto 16: contador y revisor tienen que verse tanto en el
                // RIT como en el establecimiento. Desde la migracion 003 el
                // dato vive en el contribuyente -antes estaba repetido en cada
                // local, sin nada que garantizara que las copias coincidieran-,
                // asi que aqui se lee de alli y viaja con el establecimiento.
                // Los nombres REALES de las columnas son ind_CedulaContador,
                // ind_NombreContador, etc. -asi los trae produccion desde antes
                // de este lote-. Las pantallas siguen hablando en los nombres
                // largos (ind_Cedula_contador...), que es como se llaman los
                // campos del formulario, asi que se traducen aqui con un alias
                // igual que hace _consultarRIT() en class.contribuyentes.php.
                // Sin este alias la consulta pedia columnas inexistentes y el
                // endpoint devolvia 500 con cuerpo vacio: la pantalla de
                // establecimientos solo mostraba "error de conexion".
                $datosContador = $con->obnerFila($con->consultar(
                    "SELECT ind_EmailContador, ind_EmailRevisor,
                            ind_CedulaContador      AS ind_Cedula_contador,
                            ind_NombreContador      AS ind_Nombre_contador,
                            ind_TarjetaProfContador AS ind_Tarjeta_profesional,
                            ind_CedulaRevisor       AS ind_Cedula_revisor,
                            ind_NombreRevisor       AS ind_Nombre_revisor,
                            ind_TarjetaProfRevisor  AS ind_Tarjeta_profesional_revisor
                       FROM ind_contribuyentes WHERE ind_Id = ?",
                    [$est['est_IdContribuyente']]
                ));

                foreach ([
                    'ind_EmailContador', 'ind_EmailRevisor',
                    'ind_Cedula_contador', 'ind_Nombre_contador', 'ind_Tarjeta_profesional',
                    'ind_Cedula_revisor', 'ind_Nombre_revisor', 'ind_Tarjeta_profesional_revisor',
                ] as $campoContador) {
                    $est[$campoContador] = $datosContador[$campoContador] ?? '';
                }

                // ============================
                // CONSULTAR ACTIVIDADES
                // ============================

                $sql = "
                    SELECT 
                        ace_IdCodigoActividad,
                        ace_Anio,
                        acc_Codigo,
                        acc_Nombre
                    FROM ind_actividad_establecimiento
                    INNER JOIN ind_actividadescomercio
                        ON acc_Id = ace_IdCodigoActividad
                    WHERE ace_IdEstablecimiento = ?
                ";

                $res = $con->consultar($sql, [$est['est_Id']]);

                $actividades = [];

                while($row = $con->obnerFila($res)){
                    $actividades[] = $row;
                }

                $est['actividades'] = $actividades;

                $R[] = $est;

            }


            $this->_ok = 1;
            $this->_mensaje = "Establecimiento consultados con éxito";
            return $R;
        } else {
            $this->_ok = 0;
            $this->_mensaje = "No existen Establecimientos con los filtros seleccionados";
            return [];
        }
    }

    protected function _inactivarEstablecimientos()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
        if (!self::_puedeSobreEstablecimiento($_POST['est_Id'] ?? 0, $con)) {
            $this->_ok = 0;
            $this->_mensaje = 'No tiene permiso para retirar este establecimiento';
            return [];
        }

        $_obj = new \erpsoftsas\DAO_Establecimientos();
        $_obj->set_est_Id($_POST['est_Id'] ?? null);
        $_obj->set_est_Activo(0);

        if (!$_obj->guardar()) {
            $this->_ok = 0;
            $this->_mensaje = $_obj->getMysqlError();
        } else {
            $id = $_obj->get_est_Id();
            $this->_ok = 1;
            $this->_mensaje = "Establecimiento ID $id inactivado correctamente";
        }
        return $_obj->getArray();
    }


    /**
     * Guarda el correo del contador y del revisor fiscal.
     *
     * Van en el CONTRIBUYENTE, no en el establecimiento: la declaración es
     * una sola por contribuyente aunque tenga varios establecimientos, así
     * que quien la firma es uno solo. Estos correos son el destino del
     * código OTP de firma (ver microservicios/firmas/api.php).
     *
     * Se recibe el id del establecimiento porque es lo que la pantalla del
     * RIT tiene a mano; de ahí se resuelve su contribuyente.
     */
    private function _guardarCorreosContadorRevisor()
    {
        // Estas dos columnas son el correo al que se manda el OTP de firma
        // (microservicios/firmas/api.php) -las mismas que _camposSoloAdministrador()
        // protege en el RIT (class.contribuyentes.php)-. Esta funcion no
        // comprobaba sesion NI rol en ningun punto: confirmado en vivo, un
        // POST sin ninguna cookie a funcion=20 secuestraba el correo del
        // contador/revisor de cualquier contribuyente. Mismo umbral que usa
        // _esAdministrador() en class.contribuyentes.php (rol 1 exacto, no
        // basta con estar logueado).
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }
        $esAdmin = isset($_SESSION['id_Rol']) && (int) $_SESSION['id_Rol'] === 1;
        if (!$esAdmin) {
            $this->_ok = 0;
            $this->_mensaje = 'No tiene permiso para cambiar el correo de contador/revisor';
            return [];
        }

        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $idEstablecimiento = $_POST['est_Id'] ?? null;
        $idContribuyente   = $_POST['est_IdContribuyente'] ?? null;

        if (!$idContribuyente && $idEstablecimiento) {
            $fila = $con->obnerFila($con->consultar(
                "SELECT est_IdContribuyente FROM ind_establecimientos WHERE est_Id = ?",
                [$idEstablecimiento]
            ));
            $idContribuyente = $fila['est_IdContribuyente'] ?? null;
        }

        if (!$idContribuyente) {
            $this->_ok = 0;
            $this->_mensaje = 'No se pudo determinar el contribuyente';
            return [];
        }

        $correoContador = trim($_POST['ind_EmailContador'] ?? '');
        $correoRevisor  = trim($_POST['ind_EmailRevisor'] ?? '');

        foreach ([$correoContador, $correoRevisor] as $correo) {
            if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                $this->_ok = 0;
                $this->_mensaje = 'El correo "' . $correo . '" no es válido';
                return [];
            }
        }

        $con->consultar(
            "UPDATE ind_contribuyentes
                SET ind_EmailContador = ?, ind_EmailRevisor = ?
              WHERE ind_Id = ?",
            [$correoContador, $correoRevisor, $idContribuyente]
        );

        $this->_ok = 1;
        $this->_mensaje = 'Correos de contador/revisor actualizados';

        return ['ind_Id' => $idContribuyente];
    }
}

// Clase de excepción específica para Contribuyentes
class EstablecimientosException extends \Exception { }

// Ejecutamos la función principal
\erpsoftsas\ControladorEstablecimientos::run();