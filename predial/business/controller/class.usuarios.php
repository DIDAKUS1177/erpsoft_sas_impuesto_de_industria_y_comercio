<?php
namespace predial;
include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_Usuario.php';
include_once SERVER . '/business/class.sessions.php';

class ControladorUsuarios extends \predial\Cabecera {

    private $_funcion;
    private $_ok;
    private $_mensaje;   
        
    public static function run() {
       // \predial\SesionUsuario::verificarSesion();
        
        $_obj = new self();
        $_obj->_funcion = $_POST['funcion'];
        
        try {
            $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
            $con->begin();
            $respuesta = null;
            switch ($_obj->_funcion) {
                case 1:
                    $respuesta = $_obj->_agregarUsuario();
                    break;
                case 2:
                    $respuesta = $_obj->_editarUsuario();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarUsuarios();
                    break; 
                case 4:
                    $respuesta = $_obj->_inactivarUsuarios();
                    break; 
            }
            $con->commit();
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
            
        } catch (\predial\UsuariosException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    /**
    *** Realiza el proceso de Crear Usuarios.
    **/  
    protected function _agregarUsuario() {
        
        $_objUsuario = new \predial\DAO_Usuario();
        $_objUsuario->set_usu_Nombre($_POST['nombre']);
        $_objUsuario->set_usu_NumeroDocumento($_POST['numeroDocumento']);
        $_objUsuario->set_usu_Correo($_POST['email']);
        $_objUsuario->set_usu_Password($_POST['clave']);
        $_objUsuario->set_usu_Rol($_POST['id_rol']);
        $_objUsuario->set_usu_Usuario($_POST['usuario']);
        
        $_objUsuario->set_usu_Estado(1);
      
        //Valida los campos que no pueden Duplicarsen en la BD.
        $nomUsurio= $this->_listarUsuarios(0);
        $longitud = count($nomUsurio);
        $nomduplicado=0;

        for($i=0; $i<$longitud; $i++){  
            if($nomUsurio[$i]['usu_Correo'] == $_objUsuario->get_usu_Correo()){
               $nomduplicado=1;
                break;
            }
            if($nomUsurio[$i]['usu_NumeroDocumento'] == $_objUsuario->get_usu_NumeroDocumento()){
               $nomduplicado=2;
                break;
            }
            if($nomUsurio[$i]['usu_Usuario'] == $_objUsuario->get_usu_Usuario()){
                $nomduplicado=3;
                 break;
             }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un usuario con el mismo email';
            $return= false; 
        }else if($nomduplicado == 2){
            $this->_ok = 3;
            $this->_mensaje = 'Ya existe un usuario con la misma identificación';
            $return= false;   
        }else if($nomduplicado == 3){
            $this->_ok = 4;
            $this->_mensaje = 'Ya existe un usuario con el mismo Usuario';
            $return= false;   
        }else{
            if(!$_objUsuario->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objUsuario->getMysqlError();
            }else{
                $id = $_objUsuario->get_usu_Id();

                $this->_ok = 1;
                $this->_mensaje = "Datos ingresados correctamente";
            }
            $return= $_objUsuario->guardar();
        }
        return $return;
    }
    
    /**
    *** Realiza el proceso de Editar usuarios.
    **/  
    protected function _editarUsuario() {
        
        $_objUsuario = new \predial\DAO_Usuario();
        $_objUsuario->set_usu_Id($_POST['id']);
        $_objUsuario->set_usu_Nombre($_POST['nombre']);
        $_objUsuario->set_usu_Usuario($_POST['usuario']);
        $_objUsuario->set_usu_NumeroDocumento($_POST['numeroDocumento']);
        $_objUsuario->set_usu_Correo($_POST['email']);
        $_objUsuario->set_usu_Password($_POST['clave']);
        $_objUsuario->set_usu_Rol($_POST['id_rol']);

        //Valida los campos que no pueden Duplicarsen en la BD.
        $nomUsurio= $this->_listarUsuarios($_objUsuario->get_usu_Id());
        $longitud = count($nomUsurio);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomUsurio[$i]['usu_Correo'] == $_objUsuario->get_usu_Correo()){
               $nomduplicado=1;
                break;
            }
            if($nomUsurio[$i]['usu_NumeroDocumento'] == $_objUsuario->get_usu_NumeroDocumento()){
               $nomduplicado=2;
                break;
            }
            if($nomUsurio[$i]['usu_Usuario'] == $_objUsuario->get_usu_Usuario()){
                $nomduplicado=3;
                 break;
             }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un usuario con el mismo email';
            $return= false; 
        }else if($nomduplicado == 2){
            $this->_ok = 3;
            $this->_mensaje = 'Ya existe un usuario con la misma identificación';
            $return= false;   
        }else if($nomduplicado == 3){
            $this->_ok = 4;
            $this->_mensaje = 'Ya existe un usuario con el mismo Usuario';
            $return= false;   
        }else{
            if(!$_objUsuario->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objUsuario->getMysqlError();
            }else{
                $id = $_objUsuario->get_usu_Id();

                $this->_ok = 1;
                $this->_mensaje = "Datos ingresados correctamente";
            }
            $return= $_objUsuario->guardar();
        }
        return $return;
    }
    
    /**
    *** Realiza el proceso de Listar usuarios, exeptuando el usuario enviado por parametro.
    *** @param type $id_usuario
    **/  
    private function _listarUsuarios($id_usuario) {
       
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT * FROM conf_usuario WHERE usu_id <> $id_usuario";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "Usuarios listados";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existen Usuarios";
            $row=[];
        }
        return $row;     
    }  
    
    /**
    *** Realiza el proceso de Consultar Usuarios.
    **/  
    private function _consultarUsuarios() {
       
        $_objUsu = new \predial\DAO_Usuario();

        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objUsu->set_usu_Id($_POST['id']);
            }    
        }

        if(isset($_POST['usu_Rol'])){
            if (!empty($_POST['usu_Rol']) || $_POST['usu_Rol'] != NULL ) {
                $_objUsu->set_usu_Rol($_POST['usu_Rol']);
            }    
        }
        
        $_objUsu->habilita1ResultadoEnArray();
        $arrUsuarios = $_objUsu->consultar();
       
        if(is_array($arrUsuarios) && count($arrUsuarios)){
            $R = [];
            foreach($arrUsuarios as $obj){
                $R[] = $obj->getArray();
            }    
            $this->_ok = 1;
            $this->_mensaje = "Usuarios listados con exito"; 
        }else{
            $R=$_objUsu;
            $this->_ok = 0;
            $this->_mensaje = "No existen Usuarios";            
        }       
        return $R;
    }
    
    /**
    *** Realiza el proceso de Activar o Inactivar Usuarios.
    **/  
    protected function _inactivarUsuarios() {

        $_objUsuario = new \predial\DAO_Usuario();
        $_objUsuario->set_usu_Id($_POST['id']);
        $_objUsuario->set_usu_Estado($_POST['estado']);
        
        if(!$_objUsuario->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objUsuario->getMysqlError();
        }else{
            $id = $_objUsuario->get_usu_Id();
            $this->_ok = 1;
            $this->_mensaje = "Usuario Activado/inactivado correctamente";
        }
        return $_objUsuario->getArray();
    }



}

class UsuariosException extends \Exception{}

    \predial\ControladorUsuarios::run();

