<?php
namespace predial;
include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_Rol.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER .'/business/controller/class.logs.php';

class ControladorRol extends \predial\Cabecera {

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
                    $respuesta = $_obj->_agregarRol();
                    break;
                case 2:
                    $respuesta = $_obj->_editarRol();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarRol();
                    break; 
                case 4:
                    $respuesta = $_obj->_inactivarRol();
                    break; 
            }
            $con->commit();
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
            
        } catch (\predial\RolException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    /**
    * _agregarRol: Método que realiza el proceso de Crear Roles.
    */ 
    protected function _agregarRol() {
        
        $_objRol = new \predial\DAO_Rol();
        $_objRol->set_rol_Nombre($_POST['nombre']);
        $_objRol->set_rol_Estado(1);
        
        //Valida los campos que no pueden Duplicarsen en la BD.
        $nomRol= $this->_listarRoles(0);
        $longitud = count($nomRol);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomRol[$i]['rol_Nombre'] == $_objRol->get_rol_Nombre()){
               $nomduplicado=1;
                break;
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un nombre de rol con el mismo nombre';
            $return= false; 
        }else {
            if(!$_objRol->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objRol->getMysqlError();
            }else{
                $id = $_objRol->get_rol_Id();
                $_objlogs = new logs();
                $_objlogs->_insertLogs($id,$_SESSION['id_usuario'],1,2);
                $this->_ok = 1;
                $this->_mensaje = "Rol ingresados correctamente";
            }
            $return= $_objRol->guardar();
        }
        return $return;
    }
       
    /**
    * _editarRol: Método que realiza el proceso de Editar Roles.
    */ 
    protected function _editarRol() {

        $_objRol = new \predial\DAO_Rol();
        $_objRol->set_rol_Id($_POST['id']);
        $_objRol->set_rol_Nombre($_POST['nombre']);
        
        //Valida los campos que no pueden Duplicarsen en la BD.
        $nomRol= $this->_listarRoles($_objRol->get_rol_Id());
        $longitud = count($nomRol);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomRol[$i]['rol_Nombre'] == $_objRol->get_rol_Nombre()){
               $nomduplicado=1;
                break;
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un nombre de rol con el mismo nombre';
            $return= false; 
        }else {
            if(!$_objRol->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objRol->getMysqlError();
            }else{
                $id = $_objRol->get_rol_Id();
                $_objlogs = new logs();
                $_objlogs->_insertLogs($id,$_SESSION['id_usuario'],1,3);
                $this->_ok = 1;
                $this->_mensaje = "Rol editado correctamente";
            }
            $return= $_objRol->guardar();
        }
        return $return;
    }
    
    /**
    * _listarRoles: Método que realiza el proceso 
    * de Listar roles, exeptuando el rol enviado por parametro.
    * @param type $id_rol: llave primaria de la tabla rol
    */  
    private function _listarRoles($id_rol) {
       
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT * FROM conf_rol WHERE rol_Id <> $id_rol";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "Roles listados";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existen Usuarios";
            $row=[];
        }
        return $row;     
    }  
    
    /**
    * _inactivarRol: Método que ealiza el proceso de 
    * Activar o Inactivar Roles.
    */ 
    protected function _inactivarRol() {

        $_objRol = new \predial\DAO_Rol();
        $_objRol->set_rol_Id($_POST['id']);
        $_objRol->set_rol_Estado($_POST['estado']);

        if(!$_objRol->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objRol->getMysqlError();
        }else{
            $id = $_objRol->get_rol_Id();
            $_objlogs = new logs();
            $_objlogs->_insertLogs($id,$_SESSION['id_usuario'],1,4);
            $this->_ok = 1;
            $this->_mensaje = "Rol Activado/Inactivado correctamente";
        }
        return $_objRol->getArray();
    }
    
    /**
    * _consultarRol: Método que Realiza el proceso de Consultar Roles.
    */ 
    private function _consultarRol() {
       
        $_objRol = new \predial\DAO_Rol();
        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objRol->set_rol_Id($_POST['id']);
            }    
        }

        if(isset($_POST['estado'])){
            if (!empty($_POST['estado']) || $_POST['estado'] != NULL ) {
                $_objRol->set_rol_Estado($_POST['estado']);
            }    
        }
        
        $_objRol->habilita1ResultadoEnArray();
        $arrRol = $_objRol->consultar();
       
        if(is_array($arrRol) && count($arrRol)){
            $R = [];
            foreach($arrRol as $obj){
                $R[] = $obj->getArray();
            }
            $this->_ok = 1;
            $this->_mensaje = "Roles listados con exito";
        }else{
            $R=$_objRol;
            $this->_ok = 0;
            $this->_mensaje = "No existen Roles";            
        }
        return $R;
    }  
}

class RolException extends \Exception{}

    \predial\ControladorRol::run();

