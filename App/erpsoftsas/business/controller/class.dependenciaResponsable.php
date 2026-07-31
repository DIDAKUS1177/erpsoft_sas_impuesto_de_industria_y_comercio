<?php
namespace erpsoftsas;
include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/DAO/DAO_DependenciaResponsable.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER.'/business/controller/class.logs.php';

class ControladorDependenciaResponsable extends \erpsoftsas\Cabecera {
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
                    $respuesta = $_obj->_agregarDependenciaResponsable();
                    break;
                case 2:
                    $respuesta = $_obj->_editarDependenciaResponsable();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarDependenciaResponsable();
                    break; 
                case 4:
                    $respuesta = $_obj->_eliminarDependenciaResponsable();
                    break; 
            }
            $con->commit();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
        } catch (\erpsoftsas\DependenciaResponsableException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "Error: " . $e->getMessage(), "datos" => "");
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    protected function _agregarDependenciaResponsable() {
        $_obj = new \erpsoftsas\DAO_DependenciaResponsable();
        $_obj->set_deres_IdResponsable($_POST['deres_IdResponsable']);
        $_obj->set_deres_IdDependencia($_POST['deres_IdDependencia']);
        if(!$_obj->guardar()) {
            $this->_ok = 0;
            $this->_mensaje = $_obj->getMysqlError();
        } else {
            $id = $_obj->get_deres_Id();
            //$_objlogs = new logs();
            //$_objlogs->_insertLogs($id,1,2,7);
            $this->_ok = 1;
            $this->_mensaje = "Datos ingresados correctamente";
        }
        return $_obj->guardar();
    }

    protected function _editarDependenciaResponsable() {
        $_obj = new \erpsoftsas\DAO_DependenciaResponsable();
        $_obj->set_deres_Id($_POST['id']);
        $_obj->set_deres_IdResponsable($_POST['deres_IdResponsable']);
        $_obj->set_deres_IdDependencia($_POST['deres_IdDependencia']);
        if(!$_obj->guardar()) {
            $this->_ok = 0;
            $this->_mensaje = $_obj->getMysqlError();
        } else {
            $id = $_obj->get_deres_Id();
            //$_objlogs = new logs();
            //$_objlogs->_insertLogs($id,1,2,8);
            $this->_ok = 1;
            $this->_mensaje = "Datos actualizados correctamente";
        }
        return $_obj->guardar();
    }

    private function _consultarDependenciaResponsable() {
        $_obj = new \erpsoftsas\DAO_DependenciaResponsable();

        if(isset($_POST['deres_IdResponsable'])){
            if (!empty($_POST['deres_IdResponsable']) || $_POST['deres_IdResponsable'] != NULL ) {
                $_obj->set_deres_IdResponsable($_POST['deres_IdResponsable']);
            }    
        }

        if(isset($_POST['deres_IdDependencia'])){
            if (!empty($_POST['deres_IdDependencia']) || $_POST['deres_IdDependencia'] != NULL ) {
                $_obj->set_deres_IdDependencia($_POST['deres_IdDependencia']);
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
            $this->_mensaje = "DependenciaResponsables listados con éxito"; 
        } else {
            $this->_ok = 0;
            $this->_mensaje = "No existen DependenciaResponsables";            
        }
        return $R;
    }

    /**
    *** Realiza el proceso de Eliminar un Estado tipo peticion.
    **/   
    protected function _eliminarDependenciaResponsable() {
        
        $_obj = new \erpsoftsas\DAO_DependenciaResponsable();
        $_obj->set_deres_Id($_POST['id']);

        if(!$_obj->eliminar()){
            $this->_ok = 0;
            $this->_mensaje = $_obj->getMysqlError();
        }else{
            $id = $_obj->get_deres_Id();
            //$_objlogs = new logs();
            //$_objlogs->_insertLogs(1,$id,1,5);
            $this->_ok = 1;
            $this->_mensaje = "Dependencia Responsable eliminado correctamente";
        }  
        return $_obj->getArray();
    } 

}

class DependenciaResponsableException extends \Exception { }
\erpsoftsas\ControladorDependenciaResponsable::run();
