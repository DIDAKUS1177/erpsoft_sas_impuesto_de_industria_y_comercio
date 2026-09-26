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
                case 22: // Buscar entre TODOS los establecimientos (solo Alcaldía)
                    $respuesta = $_obj->_buscarEstablecimientos();
                    break;
                case 23: // Cerrar (solo Alcaldía, con soporte y fecha de cese)
                    $respuesta = $_obj->_cerrarEstablecimiento();
                    break;
                case 24: // Reabrir uno cerrado por error (solo el administrador)
                    $respuesta = $_obj->_reabrirEstablecimiento();
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
        // Crear nunca toca uno existente: con est_Id el DAO hace UPDATE de ese
        // local, fuera de quién sea el dueño y aunque esté cerrado.
        unset($_POST['est_Id']);

        // Un establecimiento nace abierto: se cierra después, con soporte, por
        // la función 23. Antes de repartir código, para no gastar un consecutivo.
        $errorOpcion = self::_errorOpcionDeUso('Un establecimiento nuevo no puede registrarse como cerrado.');
        if ($errorOpcion !== null) {
            $this->_ok = 0;
            $this->_mensaje = $errorOpcion;
            return [];
        }

        $errorCodigo = self::_validarCodigo();
        if ($errorCodigo !== null) {
            $this->_ok = 0;
            $this->_mensaje = $errorCodigo;
            return [];
        }

        // Si no viene codigo, se reparte uno. _validarCodigo ya dejo el campo
        // en null cuando llega vacio, asi que aqui basta comprobar eso.
        if (empty($_POST['est_Codigo'])) {
            $conCodigo = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
            $_POST['est_Codigo'] = self::_siguienteCodigo($conCodigo);
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

        // Lo fija el servidor, no lo que mande el navegador. Tampoco puede quedar
        // sin valor: el DAO omite lo nulo y la columna trae DEFAULT -1, que todo
        // el sistema lee como NO activo (declaración, liquidación y retenciones
        // filtran est_Activo = 1).
        $_POST['est_Activo'] = 1;

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

    /**
     * Una fecha vacia se guarda como NULL, no como 01-01-1900.
     *
     * SQL Server convierte la cadena vacia en 1900-01-01 al meterla en una
     * columna de fecha. No es un valor: es el hueco disfrazado de dato. Y de
     * ahi salio que el formulario impreso del RIT afirmara que el negocio ceso
     * el 01-01-1900 -medido en el establecimiento 43, cuya est_Fecha_cierre
     * valia 1900-01-01 sin que nadie hubiera cesado nada-.
     *
     * Se pasa a NULL antes de guardar. El DAO salta los nulos, asi que ademas
     * de no inventar una fecha tampoco pisa la que hubiera.
     *
     * Las columnas son varchar en el mapa del DAO, pero la tabla las tiene como
     * fecha; por eso la conversion ocurre igual y por eso hay que atajarla aqui.
     */
    private static function _fechasVaciasANulo()
    {
        $fechas = [
            'est_Fecha_matricula',
            'est_Fecha_inscripcion',
            'est_Fecha_inicio',
            'est_Fecha_cierre',
            'est_Fecha_actividad',
        ];

        foreach ($fechas as $campo) {
            if (array_key_exists($campo, $_POST) && trim((string) $_POST[$campo]) === '') {
                $_POST[$campo] = null;
            }
        }
    }


    protected function _editarEstablecimientos()
    {
        // Entero desde aquí: los permisos lo miran como número, pero el DAO arma
        // el WHERE con el texto tal cual llegó.
        $_POST['est_Id'] = (int) ($_POST['est_Id'] ?? 0);
        if ($_POST['est_Id'] <= 0) {
            $this->_ok = 0;
            $this->_mensaje = 'No se indicó el establecimiento.';
            return [];
        }

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

        /*
         * Cerrar y reabrir tienen su propia puerta (funciones 23 y 24), con sus
         * reglas: soporte y fecha para cerrar, administrador y justificación
         * para reabrir (cliente, 2026-09-25). Por aquí ni se cierra, ni se
         * reactiva uno cerrado, ni se toca uno cerrado.
         */
        $estado = $con->obnerFila($con->consultar(
            "SELECT est_Activo FROM ind_establecimientos WHERE est_Id = ?",
            [(int) ($_POST['est_Id'] ?? 0)]
        ));
        if ($estado && self::_estaCerrado($estado)) {
            $this->_ok = 0;
            $this->_mensaje = 'El establecimiento está cerrado y no se puede modificar. '
                            . 'Si se cerró por error, el administrador puede reabrirlo.';
            return [];
        }
        $errorOpcion = self::_errorOpcionDeUso('Para cerrar el establecimiento use el botón "Cerrar establecimiento".');
        if ($errorOpcion !== null) {
            $this->_ok = 0;
            $this->_mensaje = $errorOpcion;
            return [];
        }
        unset($_POST['est_Activo']);

        self::_filtrarCese();
        self::_fechasVaciasANulo();

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
     * Búsqueda entre TODOS los establecimientos del municipio, para el ítem
     * "Establecimientos" del administrador (dist/establecimientosTodos.php).
     * Solo Alcaldía (roles 1 y 2): un contribuyente no tiene por qué ver los
     * locales de los demás.
     *
     * Mismo criterio que la búsqueda de contribuyentes (class.contribuyentes.php,
     * función 5): como máximo 20 filas y, sin texto, los registrados más
     * recientemente. Con texto, cada palabra debe aparecer en el nombre, la
     * dirección o el código del establecimiento, o en el documento o nombre de
     * su dueño, sin distinguir tildes ni mayúsculas. Devuelve { filas, hayMas }.
     */
    protected function _buscarEstablecimientos()
    {
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }
        $rol = isset($_SESSION['id_Rol']) ? (int) $_SESSION['id_Rol'] : 0;
        if (empty($_SESSION['id_usuario']) || !in_array($rol, [1, 2], true)) {
            $this->_ok = 0;
            $this->_mensaje = 'No tiene permiso para ver todos los establecimientos.';
            return [];
        }

        $limite   = 20;
        $palabras = preg_split('/\s+/', trim((string) ($_POST['buscar'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
        $palabras = array_slice($palabras, 0, 5);

        $condiciones = [];
        $parametros  = [];

        foreach ($palabras as $palabra) {
            // Documento con puntos o NIT con dígito de verificación: el número se
            // guarda sin puntos ni DV.
            if (preg_match('/^\d[\d.,]*(-\d)?$/', $palabra)) {
                $palabra = str_replace(['.', ','], '', preg_replace('/-\d$/', '', $palabra));
            }

            // %, _ y [ que escriba la persona se buscan como texto, no como comodines.
            $comodin = '%' . strtr($palabra, ['[' => '[[]', '%' => '[%]', '_' => '[_]']) . '%';

            // Latin1_General_CI_AI por lo mismo que en la de contribuyentes: la
            // intercalación de la base distingue tildes y trata la ñ como otra letra.
            $condiciones[] = "(e.est_Nombre           COLLATE Latin1_General_CI_AI LIKE ?
                               OR e.est_Direccion     COLLATE Latin1_General_CI_AI LIKE ?
                               OR CAST(e.est_Codigo AS varchar(20)) LIKE ?
                               OR CAST(c.ind_NumeroIdentificacion AS varchar(20)) LIKE ?
                               OR c.ind_PrimerNombre    COLLATE Latin1_General_CI_AI LIKE ?
                               OR c.ind_SegundoNombre   COLLATE Latin1_General_CI_AI LIKE ?
                               OR c.ind_PrimerApellido  COLLATE Latin1_General_CI_AI LIKE ?
                               OR c.ind_SegundoApellido COLLATE Latin1_General_CI_AI LIKE ?)";
            array_push($parametros, ...array_fill(0, 8, $comodin));
        }

        $filtro = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';
        $orden  = $condiciones ? 'e.est_Nombre' : 'e.est_Id DESC';

        $con  = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
        // Se pide una fila de más solo para saber si quedaron resultados afuera.
        $stmt = $con->consultar(
            "SELECT TOP " . ($limite + 1) . "
                    e.est_Id, e.est_Codigo, e.est_Nombre, e.est_Direccion, e.est_Activo,
                    e.est_IdContribuyente, c.ind_NumeroIdentificacion,
                    c.ind_PrimerNombre, c.ind_PrimerApellido
               FROM ind_establecimientos e
               LEFT JOIN ind_contribuyentes c ON c.ind_Id = e.est_IdContribuyente
               $filtro
              ORDER BY $orden",
            $parametros
        );

        $filas = [];
        while ($f = $con->obnerFila($stmt)) {
            $filas[] = $f;
        }

        $this->_ok = 1;
        $this->_mensaje = $filas ? 'Establecimientos encontrados' : 'Sin resultados';

        return [
            'filas'  => array_slice($filas, 0, $limite),
            'hayMas' => count($filas) > $limite,
        ];
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
            // TOP 1 + ORDER BY: con documentos repetidos en el padrón debe salir
            // el mismo contribuyente que toman el login (DAO_Usuario) y el resto.
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
     * funcion 21 - Cese de actividades del CONTRIBUYENTE.
     *
     * Subio del establecimiento a la persona con la migracion 019. Lo que se
     * firma y se imprime en el RIT es que el CONTRIBUYENTE dejo de ejercer
     * actividades en el municipio; cerrar un local suelto y seguir con los
     * otros es otro hecho, y vive en el estado del registro del
     * establecimiento, con su constancia de cierre adjunta.
     *
     * Antes esto pedia un establecimiento concreto, y el formulario impreso ya
     * no recoge cual: la casilla 29 -"Numero de Establecimiento que clausura"-
     * se retiro a peticion del cliente. Pedir en pantalla un dato que el papel
     * no imprime era la incoherencia que quedaba.
     *
     * Sigue siendo endpoint propio y no la funcion 2 (editar) porque esa
     * arrastra la validacion de codigo duplicado, que con los campos del cese
     * a solas puede rechazar un cese perfectamente valido.
     */
    protected function _guardarCese()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $idContribuyente = (int) ($_POST['ind_Id'] ?? 0);
        if ($idContribuyente <= 0) {
            $this->_ok = 0;
            $this->_mensaje = 'No se indicó el contribuyente';
            return [];
        }

        $existe = $con->obnerFila($con->consultar(
            "SELECT ind_Id FROM ind_contribuyentes WHERE ind_Id = ?", [$idContribuyente]
        ));
        if (!$existe) {
            $this->_ok = 0;
            $this->_mensaje = 'El contribuyente no existe';
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
        $causal = trim((string) ($_POST['est_Causal'] ?? $_POST['ind_CausalCese'] ?? ''));
        if (!in_array($causal, ['1', '2', '3', '4'], true)) { $causal = ''; }

        $fecha = trim((string) ($_POST['est_Fecha_cierre'] ?? $_POST['ind_FechaCese'] ?? ''));
        $obs   = trim((string) ($_POST['est_Observacion_cierre'] ?? $_POST['ind_ObservacionCese'] ?? ''));

        // Sin fecha no hay cese: se limpian los tres campos juntos, para que no
        // quede una causal huerfana que haga ver al contribuyente como cesado.
        if ($fecha === '') {
            $causal = '';
            $obs    = '';
        }

        $ok = $con->consultar(
            "UPDATE ind_contribuyentes
                SET ind_FechaCese          = NULLIF(?, ''),
                    ind_CausalCese         = NULLIF(?, ''),
                    ind_ObservacionCese    = NULLIF(?, ''),
                    ind_FechaActualizacion = GETDATE()
              WHERE ind_Id = ?",
            [$fecha, $causal, $obs, $idContribuyente]
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

        return ['ind_Id' => $idContribuyente];
    }



    /**
     * Codigo del establecimiento, cuando el usuario no escribe ninguno.
     *
     * Reportado el 2026-08-25: "el codigo no lo crea por defecto". Era cierto
     * -la columna quedaba en NULL- y por eso los doce que existen tienen
     * codigos puestos a mano y sin criterio: unos son el NIT del contribuyente
     * (1192762963), otros son 12, 121 o 999.
     *
     * Lo reparte el mismo contador atomico del numero de declaracion
     * (ind_consecutivos, migracion 012), sembrado en 1000 por la 014. Se
     * SALTAN los codigos ya ocupados en vez de arrancar por encima del maximo:
     * arrancar sobre el NIT daria codigos de diez digitos para siempre.
     *
     * Se limita a 50 intentos por si alguien llenara el rango a mano; si no
     * encuentra hueco devuelve null y el establecimiento se crea sin codigo,
     * que es el comportamiento anterior -mejor un local sin codigo que un
     * local que no se puede crear-.
     */
    private static function _siguienteCodigo($con)
    {
        for ($i = 0; $i < 50; $i++) {
            try {
                $fila = $con->obnerFila($con->consultar(
                    // SET NOCOUNT ON o el primer resultado que vuelve es el
                    // contador de filas del UPDATE, no el SELECT: obnerFila()
                    // hace una sola lectura y se traeria eso, devolviendo vacio.
                    "SET NOCOUNT ON;
                     DECLARE @n INT;
                     UPDATE dbo.ind_consecutivos
                        SET @n = cse_Valor = cse_Valor + 1,
                            cse_FechaActualizacion = GETDATE()
                      WHERE cse_Tipo = 'ESTABLECIMIENTO' AND cse_Anio = 0;
                     SELECT @n AS codigo;",
                    []
                ));

                $codigo = isset($fila['codigo']) ? (int) $fila['codigo'] : 0;
                if ($codigo <= 0) { return null; }

                $ocupado = $con->obnerFila($con->consultar(
                    "SELECT est_Id FROM ind_establecimientos WHERE est_Codigo = ?",
                    [$codigo]
                ));

                if (!$ocupado) { return $codigo; }

            } catch (\Throwable $e) {
                error_log('[establecimientos] no se pudo generar el codigo: ' . $e->getMessage());
                return null;
            }
        }

        error_log('[establecimientos] 50 intentos sin encontrar un codigo libre');
        return null;
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

    /**
     * est_Opcion_uso: 1 inscripción, 2 actualización, 3 cierre. El cierre tiene
     * su propia puerta (función 23): por crear y editar solo pasan 1 y 2. Se
     * recorta porque SQL Server guarda "3 " como '3' en el varchar(1) sin avisar.
     * Devuelve el mensaje de error, o null.
     */
    private static function _errorOpcionDeUso($mensajeCierre)
    {
        if (!array_key_exists('est_Opcion_uso', $_POST)) { return null; }

        $opcion = trim((string) $_POST['est_Opcion_uso']);
        $_POST['est_Opcion_uso'] = $opcion;
        if ($opcion === '3') { return $mensajeCierre; }
        if (!in_array($opcion, ['', '1', '2'], true)) { return 'Opción de uso no válida.'; }
        return null;
    }

    /**
     * Cerrado es todo lo que no está activo (est_Activo <> 1): el 0 del cierre
     * y el -1 que la columna pone por defecto. Es como lo leen la pantalla, la
     * declaración, la liquidación y las retenciones, que filtran est_Activo = 1.
     */
    private static function _estaCerrado(array $fila)
    {
        return (int) ($fila['est_Activo'] ?? 1) !== 1;
    }

    /**
     * Los datos del cierre ya no entran por crear ni por editar, ni siquiera del
     * administrador: los escriben solo _cerrarEstablecimiento y
     * _reabrirEstablecimiento, que exigen soporte, fecha y justificación. Se
     * descartan en silencio para que el resto del formulario se guarde.
     */
    private static function _filtrarCese()
    {
        foreach (self::_camposCese() as $campo) {
            unset($_POST[$campo]);
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

        // El administrador filtra por el contribuyente que gestiona. El DAO
        // concatena en el WHERE: el id del cliente va como entero (CLAUDE.md).
        if (isset($_POST['est_IdContribuyente'])) {
            $_POST['est_IdContribuyente'] = (int) $_POST['est_IdContribuyente'];
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

                /*
 * Las actividades salen de ind_actividad_contribuyente, la tabla NUEVA.
 * Las migraciones 005 y 007 las subieron del establecimiento al
 * contribuyente y les quitaron el año.
 *
 * Esta consulta se habia quedado en la vieja
 * (ind_actividad_establecimiento), a la que ya nadie escribe: la pantalla
 * del RIT guarda en la nueva. Mientras nadie editara actividades las dos
 * coincidian -la migracion copio el contenido-, pero a la primera edicion
 * la declaracion habria seguido viendo la lista vieja. Se conservan los
 * nombres de columna con alias para no tocar el resto del flujo.
 */
$sql = "
                    SELECT
                        atc.atc_IdCodigoActividad AS ace_IdCodigoActividad,
                        atc.atc_Anio              AS ace_Anio,
                        acc.acc_Codigo,
                        acc.acc_Nombre
                    FROM ind_actividad_contribuyente atc
                    INNER JOIN ind_establecimientos e
                        ON e.est_IdContribuyente = atc.atc_IdContribuyente
                    INNER JOIN ind_actividadescomercio acc
                        ON acc.acc_Id = atc.atc_IdCodigoActividad
                    WHERE e.est_Id = ?
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

    /**
     * funcion 4 - "Retirar", que ya no existe.
     *
     * Ponía est_Activo = 0 sin soporte ni fecha y cualquiera lo deshacía con
     * "Reactivar". El cliente pidió quitarlo (2026-09-25): un establecimiento
     * solo se cierra por "Cierre de establecimiento" (función 23). Se contesta
     * con el camino correcto por si queda alguna pantalla vieja en caché.
     */
    protected function _inactivarEstablecimientos()
    {
        $this->_ok = 0;
        $this->_mensaje = 'Para cerrar un establecimiento use "Estado del registro: Cierre de establecimiento", '
                        . 'con el soporte y la fecha de cese.';
        return [];
    }

    private static function _rolDeLaSesion()
    {
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }
        return isset($_SESSION['id_Rol']) ? (int) $_SESSION['id_Rol'] : 0;
    }

    /**
     * Deja constancia en ind_establecimiento_novedades (migración 035).
     * consultar() lanza excepción si falla, y run() solo atrapa las propias:
     * sin el try, una tabla que falta tumbaría la respuesta con un 500 mudo.
     */
    private static function _registrarNovedad($con, $idEstablecimiento, $tipo, $fechaCese, $observacion)
    {
        try {
            return (bool) $con->consultar(
                "INSERT INTO ind_establecimiento_novedades
                     (nov_IdEstablecimiento, nov_Tipo, nov_FechaCese, nov_Observacion, nov_IdUsuario)
                 VALUES (?, ?, ?, NULLIF(?, ''), ?)",
                [(int) $idEstablecimiento, $tipo, $fechaCese, (string) $observacion, (int) ($_SESSION['id_usuario'] ?? 0)]
            );
        } catch (\Throwable $e) {
            error_log('[establecimientos] no se pudo registrar la novedad: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * funcion 23 - Cierre de establecimiento (revisión del cliente 2026-09-25).
     *
     * Solo la Alcaldía (roles 1 y 2). Exige la fecha de cese -hoy o anterior,
     * nunca futura- y al menos un soporte cargado como constancia de cierre
     * (cámara de comercio o acta de liquidación: "con uno de los dos basta").
     * Cerrado queda inactivo, con est_Opcion_uso = 3, y ya no se edita ni se
     * reactiva: solo el administrador lo reabre (función 24).
     *
     * Cerrar un local no toca la declaración: las actividades son del
     * contribuyente (migraciones 005 y 007), así que el año del cierre se
     * declara igual, por el tiempo que funcionó.
     */
    protected function _cerrarEstablecimiento()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $rol = self::_rolDeLaSesion();   // abre la sesión antes de leerla
        if (empty($_SESSION['id_usuario']) || !in_array($rol, [1, 2], true)) {
            $this->_ok = 0;
            $this->_mensaje = 'Solo la Alcaldía puede cerrar un establecimiento.';
            return [];
        }

        $id = (int) ($_POST['est_Id'] ?? 0);
        $fila = $con->obnerFila($con->consultar(
            "SELECT est_Id, est_Activo, est_Fecha_inicio FROM ind_establecimientos WHERE est_Id = ?", [$id]
        ));
        if (!$fila) {
            $this->_ok = 0;
            $this->_mensaje = 'El establecimiento no existe.';
            return [];
        }
        if (self::_estaCerrado($fila)) {
            $this->_ok = 0;
            $this->_mensaje = 'El establecimiento ya está cerrado.';
            return [];
        }

        $fecha = trim((string) ($_POST['est_Fecha_cierre'] ?? ''));
        $f = \DateTime::createFromFormat('!Y-m-d', $fecha);
        if ($fecha === '' || !$f || $f->format('Y-m-d') !== $fecha) {
            $this->_ok = 0;
            $this->_mensaje = 'Indique la fecha de cese de actividades.';
            return [];
        }
        date_default_timezone_set('America/Bogota');   // "hoy" es el de Colombia, no el del servidor
        if ($fecha > date('Y-m-d')) {
            $this->_ok = 0;
            $this->_mensaje = 'La fecha de cese de actividades no puede ser posterior a hoy.';
            return [];
        }
        // Hacia atrás también hay tope: la columna es DATETIME (desde 1753) y
        // 1900-01-01 es el "vacío" de esta base. Un año de dos cifras llegaba
        // como 0025-05-01 y el UPDATE reventaba sin mensaje.
        $inicio = self::_fechaIso($fila['est_Fecha_inicio'] ?? null);
        if ($fecha < '1900-01-02' || ($inicio !== '' && $fecha < $inicio)) {
            $this->_ok = 0;
            $this->_mensaje = $inicio !== ''
                ? 'La fecha de cese no puede ser anterior al inicio de actividades del establecimiento ('
                  . date('d/m/Y', strtotime($inicio)) . ').'
                : 'Revise la fecha de cese de actividades.';
            return [];
        }

        $soporte = $con->obnerFila($con->consultar(
            "SELECT COUNT(*) AS n FROM ind_establecimiento_anexos
              WHERE anx_IdEstablecimiento = ? AND anx_Tipo = 'cese' AND anx_Activo = 1",
            [$id]
        ));
        if ((int) ($soporte['n'] ?? 0) === 0) {
            $this->_ok = 0;
            $this->_mensaje = 'Cargue el soporte del cierre: cámara de comercio o acta de liquidación.';
            return [];
        }

        // La columna es varchar(255): lo que sobre se corta en vez de tumbar el cierre.
        $observacion = mb_substr(trim((string) ($_POST['est_Observacion_cierre'] ?? '')), 0, 255);

        // Solo cuenta quien de verdad lo pasó de abierto a cerrado: si dos
        // personas cierran a la vez (o se reintenta), la segunda afecta 0 filas
        // y no deja una novedad de un cierre que no hizo.
        try {
            $cambio = $con->obnerFila($con->consultar(
                "SET NOCOUNT ON;
                 UPDATE ind_establecimientos
                    SET est_Activo = 0, est_Opcion_uso = 3, est_Fecha_cierre = ?,
                        est_Observacion_cierre = NULLIF(?, '')
                  WHERE est_Id = ? AND est_Activo = 1;
                 SELECT @@ROWCOUNT AS n;",
                [$fecha, $observacion, $id]
            ));
        } catch (\Throwable $e) {
            // Sin esto un error de la base era un 500 vacío ("Error de conexión").
            error_log("[establecimientos] no se pudo cerrar est_Id=$id: " . $e->getMessage());
            $this->_ok = 0;
            $this->_mensaje = 'No se pudo cerrar el establecimiento. Intente de nuevo.';
            return [];
        }
        if ((int) ($cambio['n'] ?? 0) !== 1) {
            $this->_ok = 0;
            $this->_mensaje = 'El establecimiento ya estaba cerrado: no se registró otro cierre.';
            return [];
        }

        // La historia no debe impedir el cierre: sin la migración 035 queda en el log.
        if (!self::_registrarNovedad($con, $id, 'CIERRE', $fecha, $observacion)) {
            error_log("[establecimientos] cierre de est_Id=$id sin novedad registrada (¿falta la migración 035?)");
        }

        $this->_ok = 1;
        $this->_mensaje = 'Establecimiento cerrado.';
        return ['est_Id' => $id];
    }

    /**
     * funcion 24 - Reabrir un establecimiento cerrado por error.
     *
     * Solo el administrador (rol 1: "el director de impuestos, que va a tener
     * todos los permisos") y con la justificación escrita, que queda en
     * ind_establecimiento_novedades. Sin esa tabla no se reabre: la condición
     * era dejar constancia. Reabrir y anotar van en una transacción: o las dos
     * cosas, o ninguna.
     */
    protected function _reabrirEstablecimiento()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $rol = self::_rolDeLaSesion();   // abre la sesión antes de leerla
        if (empty($_SESSION['id_usuario']) || $rol !== 1) {
            $this->_ok = 0;
            $this->_mensaje = 'Solo el administrador puede reabrir un establecimiento cerrado.';
            return [];
        }

        $justificacion = trim((string) ($_POST['justificacion'] ?? ''));
        if (mb_strlen($justificacion) < 10) {
            $this->_ok = 0;
            $this->_mensaje = 'Escriba por qué se reabre el establecimiento (mínimo 10 caracteres).';
            return [];
        }
        if (mb_strlen($justificacion) > 1000) {   // nov_Observacion es NVARCHAR(1000)
            $this->_ok = 0;
            $this->_mensaje = 'La justificación no puede pasar de 1.000 caracteres.';
            return [];
        }

        $id = (int) ($_POST['est_Id'] ?? 0);
        $fila = $con->obnerFila($con->consultar(
            "SELECT est_Id, est_Activo, est_Fecha_cierre FROM ind_establecimientos WHERE est_Id = ?", [$id]
        ));
        if (!$fila) {
            $this->_ok = 0;
            $this->_mensaje = 'El establecimiento no existe.';
            return [];
        }
        if (!self::_estaCerrado($fila)) {
            $this->_ok = 0;
            $this->_mensaje = 'El establecimiento no está cerrado.';
            return [];
        }

        if (!$con->obnerFila($con->consultar(
            "SELECT 1 AS x WHERE OBJECT_ID('dbo.ind_establecimiento_novedades', 'U') IS NOT NULL", []
        ))) {
            $this->_ok = 0;
            $this->_mensaje = 'No se puede reabrir: falta aplicar la migración 035 (novedades de establecimiento).';
            return [];
        }

        $fechaCese = self::_fechaIso($fila['est_Fecha_cierre'] ?? null) ?: null;

        try {
            $con->begin();

            // est_Activo <> 1 en el WHERE: dos reaperturas a la vez dejan una sola.
            $cambio = $con->obnerFila($con->consultar(
                "SET NOCOUNT ON;
                 UPDATE ind_establecimientos
                    SET est_Activo = 1, est_Opcion_uso = 2, est_Fecha_cierre = NULL, est_Observacion_cierre = NULL
                  WHERE est_Id = ? AND est_Activo <> 1;
                 SELECT @@ROWCOUNT AS n;",
                [$id]
            ));
            if ((int) ($cambio['n'] ?? 0) !== 1) {
                $con->rollback();
                $this->_ok = 0;
                $this->_mensaje = 'El establecimiento ya estaba abierto: no se registró otra reapertura.';
                return [];
            }
            if (!self::_registrarNovedad($con, $id, 'REAPERTURA', $fechaCese, $justificacion)) {
                $con->rollback();
                $this->_ok = 0;
                $this->_mensaje = 'No se pudo registrar la justificación. No se reabrió.';
                return [];
            }

            $con->commit();
        } catch (\Throwable $e) {
            try { $con->rollback(); } catch (\Throwable $e2) { /* ya revertida */ }
            error_log("[establecimientos] no se pudo reabrir est_Id=$id: " . $e->getMessage());
            $this->_ok = 0;
            $this->_mensaje = 'No se pudo reabrir el establecimiento. Intente de nuevo.';
            return [];
        }

        $this->_ok = 1;
        $this->_mensaje = 'Establecimiento reabierto.';
        return ['est_Id' => $id];
    }

    /** Una fecha de la base como AAAA-MM-DD; '' si no hay (o es el 1900-01-01 de "vacío"). */
    private static function _fechaIso($valor)
    {
        if ($valor instanceof \DateTimeInterface) {
            $texto = $valor->format('Y-m-d');
        } else {
            $texto = substr(trim((string) $valor), 0, 10);
        }
        return ($texto === '' || $texto === '1900-01-01') ? '' : $texto;
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