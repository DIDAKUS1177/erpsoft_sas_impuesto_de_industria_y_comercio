<?php
namespace erpsoftsas;

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/DAO/DAO_Conceptos.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER . '/business/controller/class.logs.php';

class ControladorConceptos extends \erpsoftsas\Cabecera 
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
                    $respuesta = $_obj->_agregarConceptos();
                    break;
                case 2: // Editar Contribuyente
                    $respuesta = $_obj->_editarConceptos();
                    break;
                case 3: // Consultar Contribuyente(s)
                    $respuesta = $_obj->_consultarConceptos();
                    break;
                case 4: // Inactivar Contribuyente
                    $respuesta = $_obj->_inactivarConceptos();
                    break;
                default:
                    throw new \erpsoftsas\ConceptosException("Función no válida", 0);
            }

            // Si todo va bien, se hace commit
            //$con->commit();

            header('Content-type: application/json');
            echo json_encode(array(
                "ok" => $_obj->_ok, 
                "mensaje" => $_obj->_mensaje, 
                "datos" => $respuesta
            ));

        } catch (\erpsoftsas\ConceptosException $e) {
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
     * Agrega un nuevo Conceptos
     */
    protected function _agregarConceptos() 
    {
        $_obj = new \erpsoftsas\DAO_Conceptos();

        if(isset($_POST['con_Codigo'])){
            if (!empty($_POST['con_Codigo']) || $_POST['con_Codigo'] != NULL ) {
                $_obj->set_con_Codigo($_POST['con_Codigo']);
            }    
        }
        if(isset($_POST['con_Anio'])){
            if (!empty($_POST['con_Anio']) || $_POST['con_Anio'] != NULL ) {
                $_obj->set_con_Anio($_POST['con_Anio']);
            }    
        }
        if(isset($_POST['con_Nombre'])){
            if (!empty($_POST['con_Nombre']) || $_POST['con_Nombre'] != NULL ) {
                $_obj->set_con_Nombre($_POST['con_Nombre'] ?? null);
            }    
        }
        if(isset($_POST['con_Observaciones'])){
            if (!empty($_POST['con_Observaciones']) || $_POST['con_Observaciones'] != NULL ) {
                $_obj->set_con_Observaciones($_POST['con_Observaciones']);
            }    
        }

        $_obj->set_con_Estado(1); 

        // Llamamos al método "guardar()" (de DAOGeneral o tu capa DAO)
        if (!$_obj->guardar()) {
            $this->_ok = 0;
            $this->_mensaje = $_obj->getMysqlError(); // método de DAOGeneral para error
        } else {
            $id = $_obj->get_con_Id(); 
            $this->_ok = 1;
            $this->_mensaje = "Conceptos agregado correctamente. ID = $id";
        }
        return $_obj->guardar();
    }

    /**
     * Edita un ActividadesComercio existente
     */
    protected function _editarConceptos()
    {
        $_obj = new \erpsoftsas\DAO_Conceptos();

        // Cargamos el ID del contribuyente que se desea editar
        $_obj->set_con_Id($_POST['con_Id'] ?? null);
        if(isset($_POST['con_Codigo'])){
            if (!empty($_POST['con_Codigo']) || $_POST['con_Codigo'] != NULL ) {
                $_obj->set_con_Codigo($_POST['con_Codigo']);
            }    
        }
        if(isset($_POST['con_Anio'])){
            if (!empty($_POST['con_Anio']) || $_POST['con_Anio'] != NULL ) {
                $_obj->set_con_Anio($_POST['con_Anio']);
            }    
        }
        if(isset($_POST['con_Nombre'])){
            if (!empty($_POST['con_Nombre']) || $_POST['con_Nombre'] != NULL ) {
                $_obj->set_con_Nombre($_POST['con_Nombre'] ?? null);
            }    
        }
        if(isset($_POST['con_Observaciones'])){
            if (!empty($_POST['con_Observaciones']) || $_POST['con_Observaciones'] != NULL ) {
                $_obj->set_con_Observaciones($_POST['con_Observaciones']);
            }    
        } 
        if(isset($_POST['con_Estado'])){
            if (!empty($_POST['con_Estado']) || $_POST['con_Estado'] != NULL ) {
                $_obj->set_con_Estado($_POST['con_Estado'] ?? null);
            }    
        }

        // Guardar cambios
        if (!$_obj->guardar()) {
            $this->_ok = 0;
            $this->_mensaje = $_obj->getMysqlError();
        } else {
            $id = $_obj->get_con_Id();
            $this->_ok = 1;
            $this->_mensaje = "Conceptos ID $id editado correctamente";
        }
        return $_obj->getArray();
    }

    /**
     * Consulta uno o varios ActividadesComercio
     */
    private function _consultarConceptos()
    {
        $_obj = new \erpsoftsas\DAO_Conceptos();

        if(isset($_POST['con_Id'])){
            if (!empty($_POST['con_Id']) || $_POST['con_Id'] != NULL ) {
                $_obj->set_con_Id($_POST['con_Id']);
            }    
        }
        if(isset($_POST['con_Codigo'])){
            if (!empty($_POST['con_Codigo']) || $_POST['con_Codigo'] != NULL ) {
                $_obj->set_con_Codigo($_POST['con_Codigo']);
            }    
        }
        if(isset($_POST['con_Anio'])){
            if (!empty($_POST['con_Anio']) || $_POST['con_Anio'] != NULL ) {
                $_obj->set_con_Anio($_POST['con_Anio']);
            }    
        }
        if(isset($_POST['con_Nombre'])){
            if (!empty($_POST['con_Nombre']) || $_POST['con_Nombre'] != NULL ) {
                $_obj->set_con_Nombre($_POST['con_Nombre'] ?? null);
            }    
        }
        if(isset($_POST['con_Observaciones'])){
            if (!empty($_POST['con_Observaciones']) || $_POST['con_Observaciones'] != NULL ) {
                $_obj->set_con_Observaciones($_POST['con_Observaciones']);
            }    
        } 
        if(isset($_POST['con_Estado'])){
            if (!empty($_POST['con_Estado']) || $_POST['con_Estado'] != NULL ) {
                $_obj->set_con_Estado($_POST['con_Estado'] ?? null);
            }    
        }
        if(isset($_POST['con_FechaCreacion'])){
            if (!empty($_POST['con_FechaCreacion']) || $_POST['con_FechaCreacion'] != NULL ) {
                $_obj->set_con_FechaCreacion($_POST['con_FechaCreacion']);
            }    
        }
        if(isset($_POST['con_FechaActualizacion'])){
            if (!empty($_POST['con_FechaActualizacion']) || $_POST['con_FechaActualizacion'] != NULL ) {
                $_obj->set_con_FechaActualizacion($_POST['con_FechaActualizacion']);
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
            $this->_mensaje = "Actividad Comercio consultados con éxito";
            return $R;
        } else {
            $this->_ok = 0;
            $this->_mensaje = "No existen Conceptos con los filtros seleccionados";
            return [];
        }
    }

    /**
     * Inactiva (cambia estado) de un contribuyente
     */
    protected function _inactivarConceptos()
    {
        $_obj = new \erpsoftsas\DAO_Conceptos();
        // Se asume que recibes un ID y un nuevo estado
        $_obj->set_con_Id($_POST['con_Id']);
        $_obj->set_con_Estado($_POST['con_Estado']);

        if (!$_obj->guardar()) {
            $this->_ok = 0;
            $this->_mensaje = $_obj->getMysqlError();
        } else {
            $id = $_obj->get_con_Id();
            $this->_ok = 1;
            $this->_mensaje = "Conceptos ID $id inactivado correctamente";
        }
        return $_obj->getArray();
    }
}

// Clase de excepción específica para Contribuyentes
class ConceptosException extends \Exception { }

// Ejecutamos la función principal
\erpsoftsas\ControladorConceptos::run();
