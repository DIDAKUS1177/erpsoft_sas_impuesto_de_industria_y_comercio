<?php
namespace erpsoftsas;
include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/DAO/DAO_CategoriasDocumental.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER.'/business/controller/class.logs.php';

class ControladorCategoriasDocumental extends \erpsoftsas\Cabecera {
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
                    $respuesta = $_obj->_agregarCategoriasDocumental();
                    break;
                case 2:
                    $respuesta = $_obj->_editarCategoriasDocumental();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarCategoriasDocumental();
                    break; 
                case 4:
                    $respuesta = $_obj->_inactivarCategoriasDocumental();
                    break; 
            }
            $con->commit();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
        } catch (\erpsoftsas\CategoriasDocumentalException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "Error: " . $e->getMessage(), "datos" => "");
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    protected function _agregarCategoriasDocumental() {
        $_obj = new \erpsoftsas\DAO_CategoriasDocumental();
        $_obj->set_cat_IdDependencia($_POST['cat_IdDependencia']);
        $_obj->set_cat_Nombre($_POST['cat_Nombre']);
        $_obj->set_cat_Descripcion($_POST['cat_Descripcion']);
        $_obj->set_cat_Sigla($_POST['cat_Sigla']);
        $_obj->set_cat_Codigo($_POST['cat_Codigo']);
        $_obj->set_cat_Estado(1);
        if(!$_obj->guardar()) {
            $this->_ok = 0;
            $this->_mensaje = $_obj->getMysqlError();
        } else {
            $id = $_obj->get_cat_Id();
            //$_objlogs = new logs();
            //$_objlogs->_insertLogs($id,1,2,7);
            $this->_ok = 1;
            $this->_mensaje = "Datos ingresados correctamente";
        }
        return $_obj->guardar();
    }

    protected function _editarCategoriasDocumental() {
        $_obj = new \erpsoftsas\DAO_CategoriasDocumental();
        $_obj->set_cat_Id($_POST['cat_Id']);
        $_obj->set_cat_IdDependencia($_POST['cat_IdDependencia']);
        $_obj->set_cat_Nombre($_POST['cat_Nombre']);
        $_obj->set_cat_Descripcion($_POST['cat_Descripcion']);
        $_obj->set_cat_Sigla($_POST['cat_Sigla']);
        $_obj->set_cat_Codigo($_POST['cat_Codigo']);
        if(!$_obj->guardar()) {
            $this->_ok = 0;
            $this->_mensaje = $_obj->getMysqlError();
        } else {
            $id = $_obj->get_cat_Id();
            //$_objlogs = new logs();
            //$_objlogs->_insertLogs($id,1,2,8);
            $this->_ok = 1;
            $this->_mensaje = "Datos actualizados correctamente";
        }
        return $_obj->guardar();
    }

    private function _consultarCategoriasDocumental() {
        $_obj = new \erpsoftsas\DAO_CategoriasDocumental();

        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_obj->set_cat_Id($_POST['id']);
            }    
        }

        if(isset($_POST['idDependencia'])){
            if (!empty($_POST['idDependencia']) || $_POST['idDependencia'] != NULL ) {
                $_obj->set_cat_IdDependencia($_POST['cat_IdDependencia']);
            }    
        }
        
        if(isset($_POST['nombre'])){
            if (!empty($_POST['nombre']) || $_POST['nombre'] != NULL ) {
                $_obj->set_cat_Nombre($_POST['nombre']);
            }    
        }

        if(isset($_POST['estado'])){
            if (!empty($_POST['estado']) || $_POST['estado'] != NULL ) {
                $_obj->set_cat_Estado($_POST['estado']);
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
            $this->_mensaje = "CategoriasDocumentals listados con éxito"; 
        } else { 
            $R = $_obj;
            $this->_ok = 0;
            $this->_mensaje = "No existen CategoriasDocumentals";            
        }
        return $R;
    }

    protected function _inactivarCategoriasDocumental() {
        $_obj = new \erpsoftsas\DAO_CategoriasDocumental();
        $_obj->set_cat_Id($_POST['id']);
        $_obj->set_cat_Estado($_POST['estado']);
        if(!$_obj->guardar()) {
            $this->_ok = 0;
            $this->_mensaje = $_obj->getMysqlError();
        } else {
            $id = $_obj->get_cat_Id();
            //$_objlogs = new logs();
            //$_objlogs->_insertLogs($id,1,2,9);
            $this->_ok = 1;
            $this->_mensaje = "Categorias Documental inactivado correctamente";
        }
        return $_obj->getArray();
    }
}

class CategoriasDocumentalException extends \Exception { }
\erpsoftsas\ControladorCategoriasDocumental::run();
