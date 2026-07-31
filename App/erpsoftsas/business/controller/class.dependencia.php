<?php
namespace erpsoftsas;

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/DAO/DAO_Dependencia.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER. '/business/controller/class.logs.php';

class ControladorDependencia extends \erpsoftsas\Cabecera {
    private $_funcion;
    private $_ok;
    private $_mensaje;

    public static function run() {
        $_obj = new self();
        $_obj->_funcion = $_POST['funcion'];

        try {
            $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
            $con->begin();
            $respuesta = null;
            switch ($_obj->_funcion) {
                case 1:
                    $respuesta = $_obj->_agregarDependencia();
                    break;
                case 2:
                    $respuesta = $_obj->_editarDependencia();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarDependencia();
                    break; 
                case 4:
                    $respuesta = $_obj->_inactivarDependencia();
                    break; 
            }
            $con->commit();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
        } catch (\erpsoftsas\DependenciaException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "Error: " . $e->getMessage(), "datos" => "");
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    protected function _agregarDependencia() {
        $_obj = new \erpsoftsas\DAO_Dependencia();
        
        $_obj->set_dep_Nombre($_POST['dep_Nombre']);
        $_obj->set_dep_Descripcion($_POST['dep_Descripcion']);
        $_obj->set_dep_Sigla($_POST['dep_Sigla']);
        $_obj->set_dep_Codigo($_POST['dep_Codigo']);
        $_obj->set_dep_IdResponsable($_POST['dep_IdResponsable']);

        $_obj->set_dep_Estado(1);
        if(!$_obj->guardar()) {
            $this->_ok = 0;
            $this->_mensaje = $_obj->getMysqlError();
        } else {
            $id = $_obj->get_dep_Id();
            //$_objlogs = new logs();
            //$_objlogs->_insertLogs($id,1,2,7);
            $this->_ok = 1;
            $this->_mensaje = "Datos ingresados correctamente";
        }
        return $_obj->guardar();
    }

    protected function _editarDependencia() {
        $_obj = new \erpsoftsas\DAO_Dependencia();
        $_obj->set_dep_Id($_POST['dep_Id']);
        $_obj->set_dep_Nombre($_POST['dep_Nombre']);
        $_obj->set_dep_Descripcion($_POST['dep_Descripcion']);
        $_obj->set_dep_Sigla($_POST['dep_Sigla']);
        $_obj->set_dep_Codigo($_POST['dep_Codigo']);
        $_obj->set_dep_IdResponsable($_POST['dep_IdResponsable']);

        if(!$_obj->guardar()) {
            $this->_ok = 0;
            $this->_mensaje = $_obj->getMysqlError();
        } else {
            $id = $_obj->get_dep_Id();
            //$_objlogs = new logs();
            //$_objlogs->_insertLogs($id,1,2,8);
            $this->_ok = 1;
            $this->_mensaje = "Datos actualizados correctamente";
        }
        return $_obj->guardar();
    }

    private function _consultarDependencia() {
        $_obj = new \erpsoftsas\DAO_Dependencia();

        if(isset($_POST['dep_Id'])){
            if (!empty($_POST['dep_Id']) || $_POST['dep_Id'] != NULL ) {
                $_obj->set_dep_Id($_POST['dep_Id']);
            }    
        }

        if(isset($_POST['dep_Nombre'])){
            if (!empty($_POST['dep_Nombre']) || $_POST['dep_Nombre'] != NULL ) {
                $_obj->set_dep_Nombre($_POST['dep_Nombre']);
            }    
        }

        if(isset($_POST['dep_IdResponsable'])){
            if (!empty($_POST['dep_IdResponsable']) || $_POST['dep_IdResponsable'] != NULL ) {
                $_obj->set_dep_IdResponsable($_POST['dep_IdResponsable']);
            }    
        }
        

        $_obj->habilita1ResultadoEnArray();
        $arr = $_obj->consultar();
        if(is_array($arr) && count($arr)) {
            $R = [];
            foreach($arr as $obj) {
                $R[] = $obj->getArray();
            }
            $this->_ok = 1;
            $this->_mensaje = "Dependencia listados con éxito"; 
        } else {
            $R=$_obj;
            $this->_ok = 0;
            $this->_mensaje = "No existen Dependencia";            
        }
        return $R;
    }

    protected function _inactivarDependencia() {
        $_obj = new \erpsoftsas\DAO_Dependencia();
        $_obj->set_dep_Id($_POST['id']);
        $_obj->set_dep_estado($_POST['estado']);
        if(!$_obj->guardar()) {
            $this->_ok = 0;
            $this->_mensaje = $_obj->getMysqlError();
        } else {
            $id = $_obj->get_dep_Id();
            //$_objlogs = new logs();
            //$_objlogs->_insertLogs($id,1,2,9);
            $this->_ok = 1;
            $this->_mensaje = "Dependencia inactivado correctamente";
        }
        return $_obj->getArray();
    }

}

class DependenciaException extends \Exception { }
\erpsoftsas\ControladorDependencia::run();
