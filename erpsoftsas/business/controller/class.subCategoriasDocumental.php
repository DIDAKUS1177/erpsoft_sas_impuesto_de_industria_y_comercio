<?php
namespace erpsoftsas;
include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/DAO/DAO_SubCategoriasDocumental.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER.'/business/controller/class.logs.php';

class ControladorSubCategoriasDocumental extends \erpsoftsas\Cabecera {
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
                    $respuesta = $_obj->_agregarSubCategoriasDocumental();
                    break;
                case 2:
                    $respuesta = $_obj->_editarSubCategoriasDocumental();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarSubCategoriasDocumental();
                    break; 
                case 4:
                    $respuesta = $_obj->_inactivarSubCategoriasDocumental();
                    break; 
            }
            $con->commit();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
        } catch (\erpsoftsas\SubCategoriasDocumentalException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "Error: " . $e->getMessage(), "datos" => "");
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    protected function _agregarSubCategoriasDocumental() {
        $_obj = new \erpsoftsas\DAO_SubCategoriasDocumental();
        $_obj->set_subc_IdCategoria($_POST['subc_IdCategoria']);
        $_obj->set_subc_Nombre($_POST['subc_Nombre']);
        $_obj->set_subc_Descripcion($_POST['subc_Descripcion']);

        if(isset($_POST['subc_Codigo'])){
            if (!empty($_POST['subc_Codigo']) || $_POST['subc_Codigo'] != NULL ) {
                $_obj->set_subc_Codigo($_POST['subc_Codigo']);
            }    
        }

        if(isset($_POST['subc_Sigla'])){
            if (!empty($_POST['subc_Sigla']) || $_POST['subc_Sigla'] != NULL ) {
                $_obj->set_subc_Sigla($_POST['subc_Sigla']);
            }    
        }

        $_obj->set_subc_Estado(1);
        if(!$_obj->guardar()) {
            $this->_ok = 0;
            $this->_mensaje = $_obj->getMysqlError();
        } else {
            $id = $_obj->get_subc_Id();
            //$_objlogs = new logs();
            //$_objlogs->_insertLogs($id,1,2,7);
            $this->_ok = 1;
            $this->_mensaje = "Datos ingresados correctamente";
        }
        return $_obj->guardar();
    }

    protected function _editarSubCategoriasDocumental() {
        $_obj = new \erpsoftsas\DAO_SubCategoriasDocumental();
        $_obj->set_subc_Id($_POST['id']);
        $_obj->set_subc_IdCategoria($_POST['subc_IdCategoria']);
        $_obj->set_subc_Nombre($_POST['subc_Nombre']);
        $_obj->set_subc_Descripcion($_POST['subc_Descripcion']);

        if(isset($_POST['codigo'])){
            if (!empty($_POST['codigo']) || $_POST['codigo'] != NULL ) {
                $_obj->set_subc_Codigo($_POST['codigo']);
            }    
        }

        if(isset($_POST['sigla'])){
            if (!empty($_POST['sigla']) || $_POST['sigla'] != NULL ) {
                $_obj->set_subc_Sigla($_POST['sigla']);
            }    
        }

        if(!$_obj->guardar()) {
            $this->_ok = 0;
            $this->_mensaje = $_obj->getMysqlError();
        } else {
            $id = $_obj->get_subc_Id();
            //$_objlogs = new logs();
            //$_objlogs->_insertLogs($id,1,2,8);
            $this->_ok = 1;
            $this->_mensaje = "Datos actualizados correctamente";
        }
        return $_obj->guardar();
    }

    private function _consultarSubCategoriasDocumental() {
        $_obj = new \erpsoftsas\DAO_SubCategoriasDocumental();

        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_obj->set_subc_Id($_POST['id']);
            }    
        }

        if(isset($_POST['idCategoria'])){
            if (!empty($_POST['idCategoria']) || $_POST['idCategoria'] != NULL ) {
                $_obj->set_subc_IdCategoria($_POST['idCategoria']);
            }    
        }
        

        if(isset($_POST['nombre'])){
            if (!empty($_POST['nombre']) || $_POST['nombre'] != NULL ) {
                $_obj->set_subc_Nombre($_POST['nombre']);
            }    
        }

        if(isset($_POST['estado'])){
            if (!empty($_POST['estado']) || $_POST['estado'] != NULL ) {
                $_obj->set_subc_Estado($_POST['estado']);
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
            $this->_mensaje = "SubCategoriasDocumentals listados con éxito"; 
        } else {
            $R = $_obj;
            $this->_ok = 0;
            $this->_mensaje = "No existen SubCategoriasDocumentals";            
        }
        return $R;
    }

    protected function _inactivarSubCategoriasDocumental() {
        $_obj = new \erpsoftsas\DAO_SubCategoriasDocumental();
        $_obj->set_subc_Id($_POST['id']);
        $_obj->set_subc_Estado($_POST['estado']);
        if(!$_obj->guardar()) {
            $this->_ok = 0;
            $this->_mensaje = $_obj->getMysqlError();
        } else {
            $id = $_obj->get_subc_Id();
            //$_objlogs = new logs();
            //$_objlogs->_insertLogs($id,1,2,9);
            $this->_ok = 1;
            $this->_mensaje = "SubCategoriasDocumental inactivado correctamente";
        }
        return $_obj->getArray();
    }
}

class SubCategoriasDocumentalException extends \Exception { }
\erpsoftsas\ControladorSubCategoriasDocumental::run();
