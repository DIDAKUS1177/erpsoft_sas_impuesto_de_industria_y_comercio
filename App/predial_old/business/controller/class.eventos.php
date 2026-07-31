<?php
namespace predial;
include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_Eventos.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER .'/business/controller/class.logs.php';

class ControladorEventos extends \predial\Cabecera {

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
                    $respuesta = $_obj->_agregarEventos();
                    break;
                case 2:
                    $respuesta = $_obj->_editarEventos();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarEventos();
                    break; 
                case 4:
                    $respuesta = $_obj->_inactivarEventos();
                    break; 
            }
            $con->commit();
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
            
        } catch (\predial\EventosException $e) {
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
    protected function _agregarEventos() {
        
        $_objMarca = new \predial\DAO_Eventos();
        // $_objMarca->set_eve_Id($_POST['id']);
        $_objMarca->set_eve_Nombre($_POST['nombre']);

        if(isset($_POST['descripcion'])){
            if (!empty($_POST['descripcion']) || $_POST['descripcion'] != NULL ) {
                $_objMarca->set_eve_Descripcion($_POST['descripcion']);
            }    
        }

        $_objMarca->set_eve_FechaEvento($_POST['fechaEventos']);
        $_objMarca->set_eve_NombreCliente($_POST['nombreCliente']);
        $_objMarca->set_eve_TelefonoCliente($_POST['telefonoCliente']);

        if(isset($_POST['email'])){
            if (!empty($_POST['email']) || $_POST['email'] != NULL ) {
                $_objMarca->set_eve_Email($_POST['email']);
            }    
        }
        
        $_objMarca->set_eve_LugarEvento($_POST['lugarEvento']);
        $_objMarca->set_eve_ValorEvento($_POST['valorEvento']);

        if(isset($_POST['notas'])){
            if (!empty($_POST['notas']) || $_POST['notas'] != NULL ) {
                $_objMarca->set_eve_Notas($_POST['notas']);
            }    
        }
        
        // $_objMarca->set_eve_FechaCreacion($_POST['fechaCreacion']);
        $_objMarca->set_eve_Estado(1);

        if(!$_objMarca->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objMarca->getMysqlError();
        }else{
            $id = $_objMarca->get_eve_Id();
            $this->_ok = 1;
            $this->_mensaje = "Evento ingresado correctamente";
        }
        $return= $_objMarca->guardar();
        return $return;
    }
       
    /**
    * _editarMarca: Método que realiza el proceso de Editar Marcaes.
    */ 
    protected function _editarEventos() {
        $_objMarca = new \predial\DAO_Eventos();

        $_objMarca->set_eve_Id($_POST['id']);
        $_objMarca->set_eve_Nombre($_POST['nombre']);

        if(isset($_POST['descripcion'])){
            if (!empty($_POST['descripcion']) || $_POST['descripcion'] != NULL ) {
                $_objMarca->set_eve_Descripcion($_POST['descripcion']);
            }    
        }

        $_objMarca->set_eve_FechaEvento($_POST['fechaEventos']);
        $_objMarca->set_eve_NombreCliente($_POST['nombreCliente']);
        $_objMarca->set_eve_TelefonoCliente($_POST['telefonoCliente']);

        if(isset($_POST['email'])){
            if (!empty($_POST['email']) || $_POST['email'] != NULL ) {
                $_objMarca->set_eve_Email($_POST['email']);
            }    
        }
        
        $_objMarca->set_eve_LugarEvento($_POST['lugarEvento']);
        $_objMarca->set_eve_ValorEvento($_POST['valorEvento']);

        if(isset($_POST['notas'])){
            if (!empty($_POST['notas']) || $_POST['notas'] != NULL ) {
                $_objMarca->set_eve_Notas($_POST['notas']);
            }    
        }
           
        if(!$_objMarca->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objMarca->getMysqlError();
        }else{
            $id = $_objMarca->get_eve_Id();
            $this->_ok = 1;
            $this->_mensaje = "Evento editado correctamente";
        }
        $return= $_objMarca->guardar();
    
        return $return;
    }
    
    /**
    * _inactivarMarca: Método que ealiza el proceso de 
    * Activar o Inactivar Marcaes.
    */ 
    protected function _inactivarEventos() {

        $_objMarca = new \predial\DAO_Eventos();
        $_objMarca->set_eve_Id($_POST['id']);
        $_objMarca->set_eve_Estado($_POST['estado']);

        if(!$_objMarca->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objMarca->getMysqlError();
        }else{
            $id = $_objMarca->get_eve_Id();
            $this->_ok = 1;
            $this->_mensaje = "Evento Activada/Inactivada correctamente";
        }
        return $_objMarca->getArray();
    }
    
    /**
    * _consultarMarca: Método que ealiza el proceso de Consultar Marcaes.
    */ 
    private function _consultarEventos() {
       
        $_objMarca = new \predial\DAO_Eventos();
        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objMarca->set_eve_Id($_POST['id']);
            }    
        }

        if(isset($_POST['estado'])){
            if (!empty($_POST['estado']) || $_POST['estado'] != NULL ) {
                $_objMarca->set_eve_Estado($_POST['estado']);
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

class EventosException extends \Exception{}

    \predial\ControladorEventos::run();

