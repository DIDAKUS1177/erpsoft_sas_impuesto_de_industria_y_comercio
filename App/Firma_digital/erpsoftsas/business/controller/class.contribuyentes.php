<?php
namespace erpsoftsas;

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/DAO/DAO_Contribuyentes.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER . '/business/controller/class.logs.php';

class ControladorContribuyentes extends \erpsoftsas\Cabecera 
{
    private $_funcion;
    private $_ok;
    private $_mensaje;

    public static function run() 
    {
        // Instanciamos el controlador
        $_obj = new self();
        // Obtenemos el número de función que indica la operación a ejecutar
        $_obj->_funcion = isset($_POST['funcion']) ? $_POST['funcion'] : null;

        try {
            // Iniciamos la transacción (adaptar a tu clase de conexión)
            //$con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
            //$con->begin();
            $respuesta = null;
            switch ($_obj->_funcion) {
                case 1: // Agregar Contribuyente
                    $respuesta = $_obj->_agregarContribuyente();
                    break;
                case 2: // Editar Contribuyente
                    $respuesta = $_obj->_editarContribuyente();
                    break;
                case 3: // Consultar Contribuyente(s)
                    $respuesta = $_obj->_consultarContribuyente();
                    break;
                case 4: // Inactivar Contribuyente
                    $respuesta = $_obj->_inactivarContribuyente();
                    break;
                case 5:
                    $respuesta = $_obj->_buscarContribuyentes();
                     break;
                case 6: // Consultar el RIT del contribuyente
                    $respuesta = $_obj->_consultarRIT();
                    break;
                case 7: // Guardar el RIT del contribuyente
                    $respuesta = $_obj->_guardarRIT();
                    break;
                default:
                    throw new \erpsoftsas\ContribuyentesException("Función no válida", 0);
            }

            // Si todo va bien, se hace commit
            //$con->commit();

            header('Content-type: application/json');
            echo json_encode(array(
                "ok" => $_obj->_ok, 
                "mensaje" => $_obj->_mensaje, 
                "datos" => $respuesta
            ));

        } catch (\erpsoftsas\ContribuyentesException $e) {
            // En caso de error, se realiza rollback
            //$con->rollback();
            $arrRespu = array(
                "ok"      => $e->getCode(),
                "mensaje" => "Error: " . $e->getMessage(),
                "datos"   => ""
            );
            header('Content-type: application/json');
            echo json_encode($arrRespu);
        } catch (\Exception $e) {
            // Antes solo se atrapaba ContribuyentesException: un error real de
            // SQL Server (por ejemplo, una fecha con formato invalido o un
            // texto mas largo que la columna) se propagaba sin capturar y
            // terminaba en un 500 con cuerpo vacio -sin JSON, sin mensaje-.
            // Con esto, cualquier fallo del driver responde igual que un
            // error de negocio normal.
            header('Content-type: application/json');
            echo json_encode(array(
                "ok"      => 0,
                "mensaje" => "No se pudo procesar la solicitud. Verifique los datos e intente de nuevo.",
                "datos"   => "",
            ));
        }
    }

    /**
     * Agrega un nuevo contribuyente
     */
    protected function _agregarContribuyente() 
    {
        $_obj = new \erpsoftsas\DAO_Contribuyentes();

        if(isset($_POST['ind_NumeroIdentificacion'])){
            if (!empty($_POST['ind_NumeroIdentificacion']) || $_POST['ind_NumeroIdentificacion'] != NULL ) {
                $_obj->set_ind_NumeroIdentificacion($_POST['ind_NumeroIdentificacion'] ?? null);
            }    
        }
        if(isset($_POST['ind_DV'])){
            if (!empty($_POST['ind_DV']) || $_POST['ind_DV'] != NULL ) {
                $_obj->set_ind_DV($_POST['ind_DV']);
            }    
        }
        if(isset($_POST['ind_IdTipoDocumento'])){
            if (!empty($_POST['ind_IdTipoDocumento']) || $_POST['ind_IdTipoDocumento'] != NULL ) {
                $_obj->set_ind_IdTipoDocumento($_POST['ind_IdTipoDocumento']);
            }    
        } 
        if(isset($_POST['ind_PrimerNombre'])){
            if (!empty($_POST['ind_PrimerNombre']) || $_POST['ind_PrimerNombre'] != NULL ) {
                $_obj->set_ind_PrimerNombre($_POST['ind_PrimerNombre'] ?? null);
            }    
        }
        if(isset($_POST['ind_SegundoNombre'])){
            if (!empty($_POST['ind_SegundoNombre']) || $_POST['ind_SegundoNombre'] != NULL ) {
                $_obj->set_ind_SegundoNombre($_POST['ind_SegundoNombre']);
            }    
        }
        if(isset($_POST['ind_PrimerApellido'])){
            if (!empty($_POST['ind_PrimerApellido']) || $_POST['ind_PrimerApellido'] != NULL ) {
                $_obj->set_ind_PrimerApellido($_POST['ind_PrimerApellido']);
            }    
        }
        if(isset($_POST['ind_SegundoApellido'])){
            if (!empty($_POST['ind_SegundoApellido']) || $_POST['ind_SegundoApellido'] != NULL ) {
                $_obj->set_ind_SegundoApellido($_POST['ind_SegundoApellido']);
            }    
        }  
        if(isset($_POST['ind_Direccion'])){
            if (!empty($_POST['ind_Direccion']) || $_POST['ind_Direccion'] != NULL ) {
                $_obj->set_ind_Direccion($_POST['ind_Direccion']);
            }    
        }
        if(isset($_POST['ind_IdCiudad'])){
            if (!empty($_POST['ind_IdCiudad']) || $_POST['ind_IdCiudad'] != NULL ) {
                $_obj->set_ind_IdCiudad($_POST['ind_IdCiudad']);
            }    
        }
        if(isset($_POST['ind_Persona'])){
            if (!empty($_POST['ind_Persona']) || $_POST['ind_Persona'] != NULL ) {
                $_obj->set_ind_Persona($_POST['ind_Persona']);
            }    
        }
        if(isset($_POST['ind_IdRegimen'])){
            if (!empty($_POST['ind_IdRegimen']) || $_POST['ind_IdRegimen'] != NULL ) {
                $_obj->set_ind_IdRegimen($_POST['ind_IdRegimen']);
            }    
        }
        if(isset($_POST['ind_Telefono'])){
            if (!empty($_POST['ind_Telefono']) || $_POST['ind_Telefono'] != NULL ) {
                $_obj->set_ind_Telefono($_POST['ind_Telefono']);
            }    
        }
        if(isset($_POST['ind_Email'])){
            if (!empty($_POST['ind_Email']) || $_POST['ind_Email'] != NULL ) {
                $_obj->set_ind_Email($_POST['ind_Email']);
            }    
        }

        $_obj->set_ind_Estado(1); 


        // Llamamos al método "guardar()" (de DAOGeneral o tu capa DAO)
        if (!$_obj->guardar()) {
            $this->_ok = 0;
            $this->_mensaje = $_obj->getMysqlError(); // método de DAOGeneral para error
        } else {
            $id = $_obj->get_ind_Id(); 
            $this->_ok = 1;
            $this->_mensaje = "Contribuyente agregado correctamente. ID = $id";
        }
        return $_obj->guardar();
    }

    /**
     * Edita un contribuyente existente
     */
    protected function _editarContribuyente()
    {
        $_obj = new \erpsoftsas\DAO_Contribuyentes();

        // Cargamos el ID del contribuyente que se desea editar
        $_obj->set_ind_Id($_POST['ind_Id'] ?? null);

        if(isset($_POST['ind_NumeroIdentificacion'])){
            if (!empty($_POST['ind_NumeroIdentificacion']) || $_POST['ind_NumeroIdentificacion'] != NULL ) {
                $_obj->set_ind_NumeroIdentificacion($_POST['ind_NumeroIdentificacion'] ?? null);
            }    
        }
        if(isset($_POST['ind_DV'])){
            if (!empty($_POST['ind_DV']) || $_POST['ind_DV'] != NULL ) {
                $_obj->set_ind_DV($_POST['ind_DV']);
            }    
        }
        if(isset($_POST['ind_IdTipoDocumento'])){
            if (!empty($_POST['ind_IdTipoDocumento']) || $_POST['ind_IdTipoDocumento'] != NULL ) {
                $_obj->set_ind_IdTipoDocumento($_POST['ind_IdTipoDocumento']);
            }    
        } 
        if(isset($_POST['ind_PrimerNombre'])){
            if (!empty($_POST['ind_PrimerNombre']) || $_POST['ind_PrimerNombre'] != NULL ) {
                $_obj->set_ind_PrimerNombre($_POST['ind_PrimerNombre'] ?? null);
            }    
        }
        if(isset($_POST['ind_SegundoNombre'])){
            if (!empty($_POST['ind_SegundoNombre']) || $_POST['ind_SegundoNombre'] != NULL ) {
                $_obj->set_ind_SegundoNombre($_POST['ind_SegundoNombre']);
            }    
        }
        if(isset($_POST['ind_PrimerApellido'])){
            if (!empty($_POST['ind_PrimerApellido']) || $_POST['ind_PrimerApellido'] != NULL ) {
                $_obj->set_ind_PrimerApellido($_POST['ind_PrimerApellido']);
            }    
        }
        if(isset($_POST['ind_SegundoApellido'])){
            if (!empty($_POST['ind_SegundoApellido']) || $_POST['ind_SegundoApellido'] != NULL ) {
                $_obj->set_ind_SegundoApellido($_POST['ind_SegundoApellido']);
            }    
        }  
        if(isset($_POST['ind_Direccion'])){
            if (!empty($_POST['ind_Direccion']) || $_POST['ind_Direccion'] != NULL ) {
                $_obj->set_ind_Direccion($_POST['ind_Direccion']);
            }    
        }
        if(isset($_POST['ind_IdCiudad'])){
            if (!empty($_POST['ind_IdCiudad']) || $_POST['ind_IdCiudad'] != NULL ) {
                $_obj->set_ind_IdCiudad($_POST['ind_IdCiudad']);
            }    
        }
        if(isset($_POST['ind_Persona'])){
            if (!empty($_POST['ind_Persona']) || $_POST['ind_Persona'] != NULL ) {
                $_obj->set_ind_Persona($_POST['ind_Persona']);
            }    
        }
        if(isset($_POST['ind_IdRegimen'])){
            if (!empty($_POST['ind_IdRegimen']) || $_POST['ind_IdRegimen'] != NULL ) {
                $_obj->set_ind_IdRegimen($_POST['ind_IdRegimen']);
            }    
        }
        if(isset($_POST['ind_Telefono'])){
            if (!empty($_POST['ind_Telefono']) || $_POST['ind_Telefono'] != NULL ) {
                $_obj->set_ind_Telefono($_POST['ind_Telefono']);
            }    
        }
        if(isset($_POST['ind_Email'])){
            if (!empty($_POST['ind_Email']) || $_POST['ind_Email'] != NULL ) {
                $_obj->set_ind_Email($_POST['ind_Email']);
            }    
        }

        // Guardar cambios
        if (!$_obj->guardar()) {
            $this->_ok = 0;
            $this->_mensaje = $_obj->getMysqlError();
        } else {
            $id = $_obj->get_ind_Id();
            $this->_ok = 1;
            $this->_mensaje = "Contribuyente ID $id editado correctamente";
        }
        return $_obj->getArray();
    }

    /**
     * Consulta uno o varios contribuyentes
     */
    private function _consultarContribuyente()
    {
        $_obj = new \erpsoftsas\DAO_Contribuyentes();

        if(isset($_POST['ind_Id'])){
            if (!empty($_POST['ind_Id']) || $_POST['ind_Id'] != NULL ) {
                $_obj->set_ind_Id($_POST['ind_Id']);
            }    
        }
        if(isset($_POST['ind_NumeroIdentificacion'])){
            if (!empty($_POST['ind_NumeroIdentificacion']) || $_POST['ind_NumeroIdentificacion'] != NULL ) {
                $_obj->set_ind_NumeroIdentificacion($_POST['ind_NumeroIdentificacion'] ?? null);
            }    
        }
        if(isset($_POST['ind_DV'])){
            if (!empty($_POST['ind_DV']) || $_POST['ind_DV'] != NULL ) {
                $_obj->set_ind_DV($_POST['ind_DV']);
            }    
        }
        if(isset($_POST['ind_IdTipoDocumento'])){
            if (!empty($_POST['ind_IdTipoDocumento']) || $_POST['ind_IdTipoDocumento'] != NULL ) {
                $_obj->set_ind_IdTipoDocumento($_POST['ind_IdTipoDocumento']);
            }    
        } 
        if(isset($_POST['ind_PrimerNombre'])){
            if (!empty($_POST['ind_PrimerNombre']) || $_POST['ind_PrimerNombre'] != NULL ) {
                $_obj->set_ind_PrimerNombre($_POST['ind_PrimerNombre'] ?? null);
            }    
        }
        if(isset($_POST['ind_SegundoNombre'])){
            if (!empty($_POST['ind_SegundoNombre']) || $_POST['ind_SegundoNombre'] != NULL ) {
                $_obj->set_ind_SegundoNombre($_POST['ind_SegundoNombre']);
            }    
        }
        if(isset($_POST['ind_PrimerApellido'])){
            if (!empty($_POST['ind_PrimerApellido']) || $_POST['ind_PrimerApellido'] != NULL ) {
                $_obj->set_ind_PrimerApellido($_POST['ind_PrimerApellido']);
            }    
        }
        if(isset($_POST['ind_SegundoApellido'])){
            if (!empty($_POST['ind_SegundoApellido']) || $_POST['ind_SegundoApellido'] != NULL ) {
                $_obj->set_ind_SegundoApellido($_POST['ind_SegundoApellido']);
            }    
        }
        if(isset($_POST['ind_Persona'])){
            if (!empty($_POST['ind_Persona']) || $_POST['ind_Persona'] != NULL ) {
                $_obj->set_ind_Persona($_POST['ind_Persona']);
            }    
        }
        if(isset($_POST['ind_IdRegimen'])){
            if (!empty($_POST['ind_IdRegimen']) || $_POST['ind_IdRegimen'] != NULL ) {
                $_obj->set_ind_IdRegimen($_POST['ind_IdRegimen']);
            }    
        }

        // Habilitamos que retorne un array de resultados
        $_obj->habilita1ResultadoEnArray();
        $arr = $_obj->consultar(); // Método heredado de DAOGeneral

        if (is_array($arr) && count($arr)) {
            // Transformamos los objetos en array (según sea tu implementación)
            $R = [];
            foreach ($arr as $obj) {
                $R[] = $obj->getArray(); 
            }
            $this->_ok = 1;
            $this->_mensaje = "Contribuyentes consultados con éxito";
            return $R;
        } else {
            $this->_ok = 0;
            $this->_mensaje = "No existen contribuyentes con los filtros seleccionados";
            return [];
        }
    }

    /**
     * Inactiva (cambia estado) de un contribuyente
     */
    protected function _inactivarContribuyente()
    {
        $_obj = new \erpsoftsas\DAO_Contribuyentes();
        // Se asume que recibes un ID y un nuevo estado
        $_obj->set_ind_Id($_POST['ind_Id']);
        $_obj->set_ind_Estado($_POST['ind_Estado']);

        if (!$_obj->guardar()) {
            $this->_ok = 0;
            $this->_mensaje = $_obj->getMysqlError();
        } else {
            $id = $_obj->get_ind_Id();
            $this->_ok = 1;
            $this->_mensaje = "Contribuyente ID $id inactivado correctamente";
        }
        return $_obj->getArray();
    }


    protected function _buscarContribuyentes(){

        // $_POST['buscar'] iba interpolado directo en el LIKE, sin parametrizar
        // (a diferencia de _consultarRIT/_guardarRIT, que si usan '?'). Un
        // valor como "x%' OR ind_Id=30 --" devolvia filas de OTRO contribuyente:
        // inyeccion SQL clasica, confirmada con extraccion real de datos.
        $buscar = (string) ($_POST['buscar'] ?? '');

        $sql = "
            SELECT TOP 20
                ind_Id,
                ind_NumeroIdentificacion,
                ind_PrimerNombre,
                ind_PrimerApellido
            FROM ind_contribuyentes
            WHERE
                ind_NumeroIdentificacion LIKE ?
                OR ind_PrimerNombre LIKE ?
                OR ind_PrimerApellido LIKE ?
            ORDER BY ind_PrimerNombre
        ";
        $comodin = '%' . $buscar . '%';

        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $id = $con->consultar($sql, [$comodin, $comodin, $comodin]);

        $datos = [];

        while($fila = $con->obnerFila($id)){
            $datos[] = $fila;
        }

        if(count($datos) > 0){

            $this->_ok = 1;
            $this->_mensaje = "Contribuyentes encontrados";
            return $datos;

        }else{

            $this->_ok = 0;
            $this->_mensaje = "Sin resultados";
            return [];
        }

    }

    /* ======================================================================
       RIT (Registro de Identificacion Tributaria) del contribuyente
       ----------------------------------------------------------------------
       El RIT no es un registro aparte: es el propio contribuyente. Hasta la
       migracion 003 sus datos -matricula, representante legal, contador y
       revisor- vivian repetidos en cada establecimiento; ahora estan donde
       corresponde y estas dos funciones son las que los leen y los graban.

       Van con SQL parametrizado a proposito, sin pasar por el DAO: ese
       concatena cadenas y obligaria a escapar a mano cada campo (ver la nota
       de inyeccion en CLAUDE.md). Es el mismo camino que ya usaba
       _guardarCorreosContadorRevisor.
       ====================================================================== */

    /**
     * Columnas que el formulario del RIT puede grabar.
     *
     * NO estan aqui, a proposito: ind_NumeroIdentificacion, ind_DV,
     * ind_IdTipoDocumento y ind_Estado. Son la identidad tributaria del
     * contribuyente; dejar que se cambien desde el formulario que el propio
     * contribuyente llena permitiria que una declaracion firmada quedara
     * atada a un documento distinto del que la firmo. Se cambian por el
     * camino de siempre (funcion 2, solo administrador).
     */
    private static function _camposRIT()
    {
        return [
            // Datos generales
            'ind_PrimerNombre', 'ind_SegundoNombre',
            'ind_PrimerApellido', 'ind_SegundoApellido',
            'ind_Direccion', 'ind_IdCiudad', 'ind_Telefono', 'ind_Email',
            'ind_Persona',
            // Propios del RIT
            'ind_Matricula', 'ind_Fecha_matricula', 'ind_Fecha_inicio',
            'ind_Ind_camara_comercio',
            'ind_Cedula_representante', 'ind_Nombre_representante', 'ind_Email_representante',
            'ind_Cedula_contador', 'ind_Nombre_contador', 'ind_Tarjeta_profesional',
            'ind_Cedula_revisor', 'ind_Nombre_revisor', 'ind_Tarjeta_profesional_revisor',
            'ind_EmailContador', 'ind_EmailRevisor',
        ];
    }

    /**
     * Contador y revisor fiscal solo los registra el administrador (punto 14);
     * el contribuyente los ve en modo lectura (punto 15). El formulario ya los
     * pinta deshabilitados, pero eso es solo la pantalla: sin esta comprobacion
     * en el servidor bastaria un POST a mano para saltarsela.
     */
    private static function _camposSoloAdministrador()
    {
        return [
            'ind_Cedula_contador', 'ind_Nombre_contador', 'ind_Tarjeta_profesional',
            'ind_Cedula_revisor', 'ind_Nombre_revisor', 'ind_Tarjeta_profesional_revisor',
            'ind_EmailContador', 'ind_EmailRevisor',
        ];
    }

    /**
     * Mapa nombre-del-formulario -> columna real de ind_contribuyentes, SOLO
     * para los 6 campos donde difieren.
     *
     * La migracion 003 habia creado ind_Cedula_contador, ind_Nombre_contador,
     * ind_Tarjeta_profesional, ind_Cedula_revisor, ind_Nombre_revisor,
     * ind_Tarjeta_profesional_revisor como columnas NUEVAS -pero produccion
     * ya tenia, desde el 2026-08-04 (migracion_2026-08_contribuyente.sql
     * BLOQUE 4), un juego con el MISMO significado y otro nombre:
     * ind_NombreContador, ind_CedulaContador, ind_TarjetaProfContador,
     * ind_NombreRevisor, ind_CedulaRevisor, ind_TarjetaProfRevisor -que es lo
     * que declaracion.php y microservicios/firmas/api.php ya leen-. La
     * migracion 003 se corrigio para usar esos nombres (ver el archivo), pero
     * el formulario del RIT (dist/icaWebRit.php, core/icaWebRit.js) sigue
     * mandando/esperando los nombres viejos -no hacia falta tocar la vista
     * para esto-, asi que aqui se traduce en el unico punto que habla con la
     * base de datos.
     */
    private static function _mapaColumnaReal()
    {
        return [
            'ind_Cedula_contador'             => 'ind_CedulaContador',
            'ind_Nombre_contador'             => 'ind_NombreContador',
            'ind_Tarjeta_profesional'         => 'ind_TarjetaProfContador',
            'ind_Cedula_revisor'              => 'ind_CedulaRevisor',
            'ind_Nombre_revisor'              => 'ind_NombreRevisor',
            'ind_Tarjeta_profesional_revisor' => 'ind_TarjetaProfRevisor',
        ];
    }

    private static function _esAdministrador()
    {
        // La sesion se abre en class.sessions.php, pero este controlador puede
        // entrar sin que se haya llamado todavia. Sin este arranque, $_SESSION
        // llega vacio y TODO el mundo pareceria no-administrador.
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }

        // Rol 1 = Administrador en conf_rol.
        return isset($_SESSION['id_Rol']) && (int) $_SESSION['id_Rol'] === 1;
    }

    /**
     * ¿Puede la sesión actual leer/escribir el RIT de este contribuyente?
     *
     * Hasta encontrarse esto, _consultarRIT() y _guardarRIT() no comprobaban
     * NADA: ni que hubiera sesión, ni que el ind_Id pedido fuera el del
     * usuario logueado. Confirmado en vivo: un POST sin ninguna cookie leía
     * el RIT completo (cédula, dirección, teléfono, representante legal) de
     * cualquier contribuyente, y otro POST sin cookie lo modificaba. Con
     * sesión de un contribuyente ajeno el resultado era el mismo. Rol 1
     * (Administrador) y 2 (Internos Alcaldía) operan sobre cualquiera; el
     * resto, solo sobre el contribuyente que les corresponde por número de
     * documento -mismo cruce que ya usa ControladorAnexos::
     * puedeOperarSobreEstablecimiento(), que es el patrón que se replica
     * aquí para no reinventarlo distinto en cada controlador.
     */
    public static function puedeOperarSobreContribuyente($idContribuyente, $con)
    {
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }

        if (empty($_SESSION['id_usuario'])) { return false; }

        $rol = isset($_SESSION['id_Rol']) ? (int) $_SESSION['id_Rol'] : 0;
        if (in_array($rol, [1, 2], true)) { return true; }

        $propio = $con->obnerFila($con->consultar(
            "SELECT c.ind_Id
               FROM ind_contribuyentes c
               INNER JOIN conf_usuarios u ON u.usu_NumeroDocumento = c.ind_NumeroIdentificacion
              WHERE u.usu_Id = ? AND c.ind_Id = ?",
            [(int) $_SESSION['id_usuario'], (int) $idContribuyente]
        ));

        return (bool) $propio;
    }

    protected function _consultarRIT()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $idContribuyente = (int) ($_POST['ind_Id'] ?? 0);
        if ($idContribuyente <= 0) {
            $this->_ok = 0;
            $this->_mensaje = 'No se indico el contribuyente';
            return [];
        }

        if (!self::puedeOperarSobreContribuyente($idContribuyente, $con)) {
            $this->_ok = 0;
            $this->_mensaje = 'No tiene permiso para ver este RIT';
            return [];
        }

        // consultar() devuelve un statement de sqlsrv, no un arreglo: hay que
        // pasarlo por obnerFila(), igual que hace el resto del sistema.
        $fila = $con->obnerFila($con->consultar(
            "SELECT c.*, ciu.ciu_Nombre, ciu.ciu_Departamento
               FROM ind_contribuyentes c
               LEFT JOIN conf_ciudades ciu ON ciu.ciu_Id = c.ind_IdCiudad
              WHERE c.ind_Id = ?",
            [$idContribuyente]
        ));

        if (!$fila) {
            $this->_ok = 0;
            $this->_mensaje = 'El contribuyente no existe';
            return [];
        }

        // El SELECT trae las columnas reales (ind_NombreContador, etc); se
        // alias hacia los nombres que el formulario del RIT espera, sin tener
        // que tocar la vista. Ver _mapaColumnaReal().
        foreach (self::_mapaColumnaReal() as $nombreFormulario => $columnaReal) {
            $fila[$nombreFormulario] = $fila[$columnaReal] ?? null;
        }

        // Punto 10: el RIT se da por inicializado en el primer ingreso. No se
        // crea nada nuevo -el contribuyente ya existe desde la inscripcion-,
        // solo se deja constancia de cuando el sistema lo abrio por primera
        // vez, para poder auditarlo y para no repetirlo.
        if (empty($fila['ind_RIT_FechaCreacion'])) {
            $con->consultar(
                "UPDATE ind_contribuyentes
                    SET ind_RIT_FechaCreacion = GETDATE()
                  WHERE ind_Id = ? AND ind_RIT_FechaCreacion IS NULL",
                [$idContribuyente]
            );
            $fila['ind_RIT_FechaCreacion']  = date('Y-m-d H:i:s');
            $fila['rit_recien_inicializado'] = 1;
        }

        // Las fechas salen como DateTime del driver y asi no le sirven a un
        // <input type="date">.
        foreach (['ind_Fecha_matricula', 'ind_Fecha_inicio', 'ind_RIT_FechaCreacion',
                  'ind_FechaCreacion', 'ind_FechaActualizacion'] as $campoFecha) {
            if (isset($fila[$campoFecha]) && $fila[$campoFecha] instanceof \DateTime) {
                $fila[$campoFecha] = $fila[$campoFecha]->format('Y-m-d');
            }
        }

        // Punto 9: el RIT debe mostrar las actividades economicas. Se toman
        // las del año mas reciente que el contribuyente tenga registrado en
        // cualquiera de sus establecimientos; un año fijo dejaba la lista
        // vacia, que es el mismo fallo que traia el certificado.
        $stmtActividades = $con->consultar(
            "SELECT ca.acc_Codigo, ca.acc_Nombre, a.ace_Anio,
                    e.est_Id, e.est_Nombre
               FROM ind_actividad_establecimiento a
               INNER JOIN ind_establecimientos e ON e.est_Id = a.ace_IdEstablecimiento
               LEFT JOIN ind_actividadescomercio ca ON ca.acc_Id = a.ace_IdCodigoActividad
              WHERE e.est_IdContribuyente = ?
                AND a.ace_Anio = (
                        SELECT MAX(a2.ace_Anio)
                          FROM ind_actividad_establecimiento a2
                          INNER JOIN ind_establecimientos e2 ON e2.est_Id = a2.ace_IdEstablecimiento
                         WHERE e2.est_IdContribuyente = ?
                    )
              ORDER BY ca.acc_Codigo",
            [$idContribuyente, $idContribuyente]
        );

        $fila['actividades'] = [];
        while ($act = $con->obnerFila($stmtActividades)) {
            $fila['actividades'][] = $act;
        }

        // Punto 16: el cese de actividades tiene que verse tanto en el
        // establecimiento como en el RIT. El cese es por local -las columnas
        // viven en ind_establecimientos-, asi que aqui se listan todos los
        // establecimientos del contribuyente con su estado. De solo lectura:
        // se registra desde el modulo de establecimientos y unicamente la
        // Alcaldia (punto 14).
        $stmtEstablecimientos = $con->consultar(
            "SELECT est_Id, est_Nombre, est_Direccion, est_Activos,
                    est_Fecha_cierre, est_Causal, est_Resolucion_cierre
               FROM ind_establecimientos
              WHERE est_IdContribuyente = ?
              ORDER BY est_Id",
            [$idContribuyente]
        );

        $fila['establecimientos'] = [];
        while ($est = $con->obnerFila($stmtEstablecimientos)) {
            // 1900-01-01 es el centinela de "nunca se lleno" de esta base.
            if ($est['est_Fecha_cierre'] instanceof \DateTime) {
                $f = $est['est_Fecha_cierre']->format('Y-m-d');
                $est['est_Fecha_cierre'] = ($f === '1900-01-01') ? '' : $f;
            }
            $fila['establecimientos'][] = $est;
        }

        $this->_ok = 1;
        $this->_mensaje = 'RIT consultado';

        return $fila;
    }

    protected function _guardarRIT()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $idContribuyente = (int) ($_POST['ind_Id'] ?? 0);
        if ($idContribuyente <= 0) {
            $this->_ok = 0;
            $this->_mensaje = 'No se indico el contribuyente';
            return [];
        }

        if (!self::puedeOperarSobreContribuyente($idContribuyente, $con)) {
            $this->_ok = 0;
            $this->_mensaje = 'No tiene permiso para editar este RIT';
            return [];
        }

        // puedeOperarSobreContribuyente() ya verifica existencia para
        // cualquier rol que NO sea administrador (el cruce por documento no
        // encuentra nada si el ind_Id no existe). Pero para rol 1/2 devuelve
        // true de inmediato sin comprobar nada mas: un administrador podia
        // "guardar" un ind_Id inexistente, el UPDATE de mas abajo afectaba 0
        // filas sin que sqlsrv marcara error, y la respuesta seguia siendo
        // ok:1 "RIT actualizado" -exito falso sobre un contribuyente que no
        // existe-.
        $existe = $con->obnerFila($con->consultar(
            "SELECT ind_Id FROM ind_contribuyentes WHERE ind_Id = ?",
            [$idContribuyente]
        ));
        if (!$existe) {
            $this->_ok = 0;
            $this->_mensaje = 'El contribuyente no existe';
            return [];
        }

        $sets    = [];
        $valores = [];

        $soloAdmin  = self::_camposSoloAdministrador();
        $esAdmin    = self::_esAdministrador();
        $ignorados  = [];

        foreach (self::_camposRIT() as $campo) {
            if (!array_key_exists($campo, $_POST)) { continue; }

            // Puntos 14 y 15: los datos de contador y revisor solo los graba
            // el administrador. Si llegan de otro rol se descartan en silencio
            // en vez de fallar, para que el contribuyente si pueda guardar el
            // resto de su RIT.
            if (in_array($campo, $soloAdmin, true) && !$esAdmin) {
                $ignorados[] = $campo;
                continue;
            }

            $valor = trim((string) $_POST[$campo]);

            // ind_Telefono es bigint, pero un (int) crudo corta el string en
            // el primer caracter no numerico tras un signo -"+57 312 566 6656"
            // quedaba guardado como "57"-. Se extraen solo los digitos en vez
            // de castear a ciegas, para no destruir un numero con indicativo,
            // espacios o guiones (formato normal en Colombia).
            if ($campo === 'ind_Telefono') {
                $soloDigitos = preg_replace('/\D/', '', $valor);
                $valor = ($soloDigitos === '') ? null : $soloDigitos;
            } elseif (in_array($campo, ['ind_IdCiudad', 'ind_Persona'], true)) {
                $valor = ($valor === '') ? null : (int) $valor;
            }

            // Un correo mal escrito deja al contribuyente sin poder firmar,
            // porque el OTP no llega a ningun lado.
            if (in_array($campo, ['ind_EmailContador', 'ind_EmailRevisor', 'ind_Email_representante'], true)) {
                if ($valor !== '' && !filter_var($valor, FILTER_VALIDATE_EMAIL)) {
                    $this->_ok = 0;
                    $this->_mensaje = 'El correo "' . $valor . '" no es válido';
                    return [];
                }
            }

            // Una fecha vacia tiene que quedar NULL, no cadena vacia: SQL
            // Server convierte '' en 1900-01-01, que es justo la basura que la
            // migracion 003 se ocupo de no arrastrar.
            if (in_array($campo, ['ind_Fecha_matricula', 'ind_Fecha_inicio'], true)) {
                $valor = ($valor === '') ? null : $valor;
            }

            if ($campo === 'ind_Ind_camara_comercio') {
                $valor = ($valor === '') ? null : (int) $valor;
            }

            // Ver _mapaColumnaReal(): 6 de estos campos usan un nombre en el
            // formulario distinto al de la columna real en la base.
            $columnaReal = self::_mapaColumnaReal()[$campo] ?? $campo;

            $sets[]    = $columnaReal . ' = ?';
            $valores[] = $valor;
        }

        if (!$sets) {
            $this->_ok = 0;
            $this->_mensaje = 'No se recibió ningún dato del RIT';
            return [];
        }

        $valores[] = $idContribuyente;

        $con->consultar(
            "UPDATE ind_contribuyentes SET " . implode(', ', $sets) . ",
                    ind_FechaActualizacion = GETDATE()
              WHERE ind_Id = ?",
            $valores
        );

        $this->_ok = 1;
        $this->_mensaje = $ignorados
            ? 'RIT actualizado. Los datos de contador y revisor solo los puede cambiar el administrador.'
            : 'RIT actualizado';

        return ['ind_Id' => $idContribuyente, 'ignorados' => $ignorados];
    }

}

// Clase de excepción específica para Contribuyentes
class ContribuyentesException extends \Exception { }

// Ejecutamos la función principal
\erpsoftsas\ControladorContribuyentes::run();
