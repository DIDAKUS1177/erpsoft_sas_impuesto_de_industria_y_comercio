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

        $buscar = $_POST['buscar'];

        $_obj = new \erpsoftsas\DAO_Contribuyentes();

        $sql = "
            SELECT TOP 20
                ind_Id,
                ind_NumeroIdentificacion,
                ind_PrimerNombre,
                ind_PrimerApellido
            FROM ind_contribuyentes
            WHERE
                ind_NumeroIdentificacion LIKE '%$buscar%'
                OR ind_PrimerNombre LIKE '%$buscar%'
                OR ind_PrimerApellido LIKE '%$buscar%'
            ORDER BY ind_PrimerNombre
        ";

        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $id = $con->consultar($sql, []);

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

    /** Columnas del RIT que el formulario puede grabar. */
    private static function _camposRIT()
    {
        return [
            'ind_Matricula', 'ind_Fecha_matricula', 'ind_Fecha_inicio',
            'ind_Ind_camara_comercio',
            'ind_Cedula_representante', 'ind_Nombre_representante', 'ind_Email_representante',
            'ind_Cedula_contador', 'ind_Nombre_contador', 'ind_Tarjeta_profesional',
            'ind_Cedula_revisor', 'ind_Nombre_revisor', 'ind_Tarjeta_profesional_revisor',
            'ind_EmailContador', 'ind_EmailRevisor',
        ];
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

        $sets    = [];
        $valores = [];

        foreach (self::_camposRIT() as $campo) {
            if (!array_key_exists($campo, $_POST)) { continue; }

            $valor = trim((string) $_POST[$campo]);

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

            $sets[]    = $campo . ' = ?';
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
        $this->_mensaje = 'RIT actualizado';

        return ['ind_Id' => $idContribuyente];
    }

}

// Clase de excepción específica para Contribuyentes
class ContribuyentesException extends \Exception { }

// Ejecutamos la función principal
\erpsoftsas\ControladorContribuyentes::run();
