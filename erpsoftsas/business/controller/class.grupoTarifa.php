<?php
namespace erpsoftsas;

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/DAO/DAO_GrupoTarifa.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER . '/business/controller/class.logs.php';

class ControladorGrupoTarifa extends \erpsoftsas\Cabecera 
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
                case 1: // Agregar Grupo Tarifa
                    $respuesta = $_obj->_agregarGrupoTarifa();
                    break;
                case 2: // Editar Grupo Tarifa
                    $respuesta = $_obj->_editarGrupoTarifa();
                    break;
                case 3: // Consultar Grupo Tarifa(s)
                    $respuesta = $_obj->_consultarGrupoTarifa();
                    break;
                case 4: // Inactivar Grupo Tarifa
                    $respuesta = $_obj->_inactivarGrupoTarifa();
                    break;
                default:
                    throw new \erpsoftsas\GrupoTarifaException("Función no válida", 0);
            }

            // Si todo va bien, se hace commit
            //$con->commit();

            header('Content-type: application/json');
            echo json_encode(array(
                "ok" => $_obj->_ok, 
                "mensaje" => $_obj->_mensaje, 
                "datos" => $respuesta
            ));

        } catch (\erpsoftsas\GrupoTarifaException $e) {
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
     * Agrega un nuevo Grupo Tarifa
     */
    protected function _agregarGrupoTarifa() 
    {
        $_obj = new \erpsoftsas\DAO_GrupoTarifa();

        if(isset($_POST['gru_Codigo'])){
            if (!empty($_POST['gru_Codigo']) || $_POST['gru_Codigo'] != NULL ) {
                $_obj->set_gru_Codigo($_POST['gru_Codigo']);
            }    
        }
        if(isset($_POST['gru_Nombre'])){
            if (!empty($_POST['gru_Nombre']) || $_POST['gru_Nombre'] != NULL ) {
                $_obj->set_gru_Nombre($_POST['gru_Nombre'] ?? null);
            }    
        }


        $_obj->set_gru_Estado(1); 

        // Llamamos al método "guardar()" (de DAOGeneral o tu capa DAO)
        if (!$_obj->guardar()) {
            $this->_ok = 0;
            $this->_mensaje = $_obj->getMysqlError(); // método de DAOGeneral para error
        } else {
            $id = $_obj->get_gru_Id(); 
            $this->_ok = 1;
            $this->_mensaje = "Grupo Tarifa agregado correctamente. ID = $id";
        }
        return $_obj->guardar();
    }

    /**
     * Edita un Grupo Tarifa existente
     */
    protected function _editarGrupoTarifa()
    {
        $_obj = new \erpsoftsas\DAO_GrupoTarifa();

        // Cargamos el ID del contribuyente que se desea editar
        $_obj->set_gru_Id($_POST['gru_Id'] ?? null);
        if(isset($_POST['gru_Codigo'])){
            if (!empty($_POST['gru_Codigo']) || $_POST['gru_Codigo'] != NULL ) {
                $_obj->set_gru_Codigo($_POST['gru_Codigo']);
            }    
        }
        if(isset($_POST['gru_Nombre'])){
            if (!empty($_POST['gru_Nombre']) || $_POST['gru_Nombre'] != NULL ) {
                $_obj->set_gru_Nombre($_POST['gru_Nombre'] ?? null);
            }    
        }
        if(isset($_POST['gru_Estado'])){
            if (!empty($_POST['gru_Estado']) || $_POST['gru_Estado'] != NULL ) {
                $_obj->set_gru_Estado($_POST['gru_Estado'] ?? null);
            }    
        }

        // Guardar cambios
        if (!$_obj->guardar()) {
            $this->_ok = 0;
            $this->_mensaje = $_obj->getMysqlError();
        } else {
            $id = $_obj->get_gru_Id();
            $this->_ok = 1;
            $this->_mensaje = "Grupo Tarifa ID $id editado correctamente";
        }
        return $_obj->getArray();
    }

    /**
     * Consulta uno o varios ActividadesComercio
     */
    private function _consultarGrupoTarifa()
    {
        $_obj = new \erpsoftsas\DAO_GrupoTarifa();

        if(isset($_POST['gru_Id'])){
            if (!empty($_POST['gru_Id']) || $_POST['gru_Id'] != NULL ) {
                $_obj->set_gru_Id($_POST['gru_Id']);
            }    
        }
        if(isset($_POST['gru_Codigo'])){
            if (!empty($_POST['gru_Codigo']) || $_POST['gru_Codigo'] != NULL ) {
                $_obj->set_gru_Codigo($_POST['gru_Codigo']);
            }    
        }
        if(isset($_POST['gru_Nombre'])){
            if (!empty($_POST['gru_Nombre']) || $_POST['gru_Nombre'] != NULL ) {
                $_obj->set_gru_Nombre($_POST['gru_Nombre'] ?? null);
            }    
        }
        if(isset($_POST['gru_Estado'])){
            if (!empty($_POST['gru_Estado']) || $_POST['gru_Estado'] != NULL ) {
                $_obj->set_gru_Estado($_POST['gru_Estado'] ?? null);
            }    
        }
        if(isset($_POST['gru_FechaCreacion'])){
            if (!empty($_POST['gru_FechaCreacion']) || $_POST['gru_FechaCreacion'] != NULL ) {
                $_obj->set_gru_FechaCreacion($_POST['gru_FechaCreacion']);
            }    
        }
        if(isset($_POST['gru_FechaActualizacion'])){
            if (!empty($_POST['gru_FechaActualizacion']) || $_POST['gru_FechaActualizacion'] != NULL ) {
                $_obj->set_gru_FechaActualizacion($_POST['gru_FechaActualizacion']);
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
            $this->_mensaje = "Grupo Tarifa consultados con éxito";
            return $R;
        } else {
            $this->_ok = 0;
            $this->_mensaje = "No existen Grupo Tarifa con los filtros seleccionados";
            return [];
        }
    }

    /**
     * Inactiva (cambia estado) de un contribuyente
     */
    protected function _inactivarGrupoTarifa()
    {
        $_obj = new \erpsoftsas\DAO_GrupoTarifa();
        // Se asume que recibes un ID y un nuevo estado
        $_obj->set_gru_Id($_POST['gru_Id']);
        $_obj->set_gru_Estado($_POST['gru_Estado']);

        if (!$_obj->guardar()) {
            $this->_ok = 0;
            $this->_mensaje = $_obj->getMysqlError();
        } else {
            $id = $_obj->get_gru_Id();
            $this->_ok = 1;
            $this->_mensaje = "Grupo Tarifa ID $id inactivado correctamente";
        }
        return $_obj->getArray();
    }
}

// Clase de excepción específica para Contribuyentes
class GrupoTarifaException extends \Exception { }

// Ejecutamos la función principal
\erpsoftsas\ControladorGrupoTarifa::run();
