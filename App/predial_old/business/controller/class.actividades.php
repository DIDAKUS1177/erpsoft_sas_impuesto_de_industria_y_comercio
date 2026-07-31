<?php
namespace predial;
include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_Actividades.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER .'/business/controller/class.logs.php';

class ControladorEventosAct extends \predial\Cabecera {

    private $_funcion;
    private $_ok;
    private $_mensaje;   
        
    public static function run() {
        \predial\SesionUsuario::verificarSesion();
        
        $_obj = new self();
        $_obj->_funcion = $_POST['funcion'];
        
        try {
            $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
            $con->begin();
            $respuesta = null;
            switch ($_obj->_funcion) {
                case 1:
                    $respuesta = $_obj->_agregarEventosAct();
                    break;
                case 2:
                    $respuesta = $_obj->_editarEventosAct();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarEventosAct();
                    break; 
                case 4:
                    $respuesta = $_obj->_inactivarEventosAct();
                    break; 
            }
            $con->commit();
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
            
        } catch (\predial\EventosActException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    /**
    * _agregarMarca: Método que realiza el proceso de Crear Marcaes.
    */ 
    protected function _agregarEventosAct() {
        
        $_objMarca = new \predial\DAO_Actividades();
        
        $_objMarca->set_eva_IdProyecto($_POST['idProyecto']);
        $_objMarca->set_eva_IdProveedor($_POST['idProveedor']);
        $_objMarca->set_eva_IdCategoria($_POST['idCategoria']);
        $_objMarca->set_eva_Descripcion($_POST['descripcion']);
        $_objMarca->set_eva_Valor($_POST['valorEvento']);
        $_objMarca->set_eva_Estado(1);

        if(!$_objMarca->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objMarca->getMysqlError();
        }else{
            $id = $_objMarca->get_eva_Id();
            $this->_ok = 1;
            $this->_mensaje = "Actividad ingresada correctamente";
        }
        $return= $_objMarca->guardar();
        return $return;
    }
       
    /**
    * _editarMarca: Método que realiza el proceso de Editar Marcaes.
    */ 
    protected function _editarEventosAct() {
        
        $_objMarca = new \predial\DAO_Actividades();

        $_objMarca->set_eva_Id($_POST['id']);
        $_objMarca->set_eva_IdProyecto($_POST['idProyecto']);
        $_objMarca->set_eva_IdProveedor($_POST['idProveedor']);
        $_objMarca->set_eva_IdCategoria($_POST['idCategoria']);
        $_objMarca->set_eva_Descripcion($_POST['descripcion']);
        $_objMarca->set_eva_Valor($_POST['valorEvento']);        
           
        if(!$_objMarca->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objMarca->getMysqlError();
        }else{
            $id = $_objMarca->get_eva_Id();
            $this->_ok = 1;
            $this->_mensaje = "Actividad editada correctamente";
        }
        $return= $_objMarca->guardar();
    
        return $return;
    }
    
    /**
    * _inactivarMarca: Método que ealiza el proceso de 
    * Activar o Inactivar Marcaes.
    */ 
    protected function _inactivarEventosAct() {

        $_objMarca = new \predial\DAO_Actividades();
        $_objMarca->set_eva_Id($_POST['id']);
        $_objMarca->set_eva_Estado($_POST['estado']);

        if(!$_objMarca->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objMarca->getMysqlError();
        }else{
            $id = $_objMarca->get_eva_Id();
            $this->_ok = 1;
            $this->_mensaje = "Evento Activada/Inactivada correctamente";
        }
        return $_objMarca->getArray();
    }
    
    /**
    * _consultarMarca: Método que ealiza el proceso de Consultar Marcaes.
    */ 
    private function _consultarEventosAct() {
       
        $_objMarca = new \predial\DAO_Actividades();

        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objMarca->set_eva_Id($_POST['id']);
            }    
        }

        if(isset($_POST['IdEvento'])){
            if (!empty($_POST['id']) || $_POST['IdEvento'] != NULL ) {
                $_objMarca->set_eva_IdProyecto($_POST['IdEvento']);
            }    
        }

        if(isset($_POST['estado'])){
            if (!empty($_POST['estado']) || $_POST['estado'] != NULL ) {
                $_objMarca->set_eva_Estado($_POST['estado']);
            }    
        }
        
        $_objMarca->habilita1ResultadoEnArray();
        $arrMarca = $_objMarca->consultar();
       
        if(is_array($arrMarca) && count($arrMarca)){
            $R = [];
            foreach($arrMarca as $obj){
                $R[] = $obj->getArray();
            }
            $this->_ok = 1;
            $this->_mensaje = "Eventos listados con exito";
        }else{
            $R=$_objMarca;
            $this->_ok = 0;
            $this->_mensaje = "No existen Eventos";            
        }
        return $R;
    }  
}

class EventosActException extends \Exception{}

    \predial\ControladorEventosAct::run();

