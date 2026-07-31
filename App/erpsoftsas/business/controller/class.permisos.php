<?php
namespace erpsoftsas;
include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/DAO/DAO_Permisos.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER.'/business/controller/class.logs.php';

class ControladorPermisos extends \erpsoftsas\Cabecera {

    private $_funcion;
    private $_ok;
    private $_mensaje;   
        
    public static function run() {
        //\erpsoftsas\SesionUsuario::verificarSesion();
        
        $_obj = new self();
        $_obj->_funcion = $_POST['funcion'];
        
        try {
            //$con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
            //$con->begin();
            $respuesta = null;
            switch ($_obj->_funcion) {
                case 1:
                    $respuesta = $_obj->_ingresarPermisos();
                    break;
                case 2:
                    //$respuesta = $_obj->_editarPermisos();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarPermisos();
                    break; 
                case 4:
                    $respuesta = $_obj->_inactivarPermisos();
                    break; 
                case 5:
                    $respuesta = $_obj->_eliminarPermisos();
                    break; 
            }
           // $con->commit();
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
            
        } catch (\erpsoftsas\PermisosException $e) {
            //$con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }
    
    /**
    *** Realiza el proceso de Crear Permisos.
    **/
    private function _ingresarPermisos() {
        
        //Elimina permisos primeramente
        if($this->_eliminarPermisos($_POST['id_rol']) == 1){

            $array = json_decode($_POST['data']);    
            $cantidad= count($array);
            $return=FALSE;
            for($i=0 ; $i < $cantidad; $i++){
                foreach ( $array[$i] as $nom => $he) {
                    if($nom == 'id_modulo'){
                        $id_modulo= $he;   //id_equipo   
                    }else if($nom == 'id_sub_modulo'){
                        $id_sub_modulo= $he;   //Solicitados   
                    }
                }
    
                if($this->_agregarPermisos($_POST['id_rol'], $id_modulo, $id_sub_modulo) == 1){
                    $this->_ok = 1;
                    $this->_mensaje = "creados Exitosamente";        
                    $return=true;
                }else{
                    $return=false;   
                    $this->_ok = 0;
                    $this->_mensaje = "Error";        
                }
            }
            //$_objlogs = new logs();
            //$_objlogs->_insertLogs($_POST['id_rol'],1,1,5);
        }       
        return $return;
    }         

    /**
    *** Realiza el proceso de Crear Permisos.
    **/ 
    protected function _agregarPermisos($id_rol,$id_modulo,$id_sub_modulo) {

        $_objPermiso = new \erpsoftsas\DAO_Permisos();
        $_objPermiso->set_per_IdRol($id_rol);
        $_objPermiso->set_per_IdModulo($id_modulo);
        $_objPermiso->set_per_IdSubModulo($id_sub_modulo);
        $_objPermiso->set_per_Estado(1);
        $id_boton= $id_modulo.$id_sub_modulo;
        $_objPermiso->set_per_IdBoton($id_boton);

        if(!$_objPermiso->guardar()){
            //$this->_ok = 0;
            //$this->_mensaje = $_objPermiso->getMysqlError();
            $return= 0;
        }else{
            $id = $_objPermiso->get_per_Id();
            //$_objlogs = new logs();
            //$_objlogs->_insertLogs($id_rol,1,1,5);
            $return= 1;
            //$this->_ok = 1;
            //$this->_mensaje = "Datos ingresados correctamente";
        }
        return $return;
    }
    
    /**
    *** Realiza el proceso de Consultar Permisos.
    **/
    private function _consultarPermisos() {
       
        $_objPermiso = new \erpsoftsas\DAO_Permisos();
        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objPermiso->set_per_Id($_POST['id']);
            }
        }
        if(isset($_POST['id_rol'])){
            if (!empty($_POST['id_rol']) || $_POST['id_rol'] != NULL ) {
                $_objPermiso->set_per_IdRol($_POST['id_rol']);
            }    
        }
        if(isset($_POST['id_modulo'])){
           if (!empty($_POST['id_modulo']) || $_POST['id_modulo'] != NULL ) {
                $_objPermiso->set_per_IdModulo($_POST['id_modulo']);
            }  
        }
        if(isset($_POST['id_sub_modulo'])){
           if (!empty($_POST['id_sub_modulo']) || $_POST['id_sub_modulo'] != NULL ) {
                $_objPermiso->set_per_IdSubModulo($_POST['id_sub_modulo']);
            }  
        }
        
        if(isset($_POST['id_boton'])){
           if (!empty($_POST['id_boton']) || $_POST['id_boton'] != NULL ) {
                $_objPermiso->set_per_IdBoton($_POST['id_boton']);
            }  
        }
        
        $_objPermiso->habilita1ResultadoEnArray();
        $arrPermisos = $_objPermiso->consultar();
       
        if(is_array($arrPermisos) && count($arrPermisos)){
            $R = [];
            foreach($arrPermisos as $obj){
                $R[] = $obj->getArray();
            }    
            $this->_ok = 1;
            $this->_mensaje = "Permisos listados con exito"; 
        }else{
            if($_POST['id_rol'] == 1){
                $R=$_objPermiso;
                $this->_ok = 1;
                $this->_mensaje = "Rol Super Administrador";    
            }else{
                $R=$_objPermiso;
                $this->_ok = 0;
                $this->_mensaje = "No existen Permisos"; 
            }              
        }
        return $R;
    }  
    
    /**
    *** Realiza el proceso de Activar o Inactivar Permisos.
    **/
    protected function _inactivarPermisos() {
        
        $_objPermiso = new \erpsoftsas\DAO_Permisos();
        $_objPermiso->set_per_Id($_POST['id']);
        $_objPermiso->set_per_Estado($_POST['estado']);

        if(!$_objPermiso->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objPermiso->getMysqlError();
        }else{
            $id = $_objPermiso->get_per_Id();
            //$_objlogs = new logs();
            //$_objlogs->_insertLogs(1,$id,1,5);
            $this->_ok = 1;
            $this->_mensaje = "Permiso Activado/inactivado correctamente";
        }
        return $_objPermiso->getArray();
    }
    
    /**
    *** Realiza el proceso de Eliminar un permiso.
    **/   
    protected function _eliminarPermisos($id_rol) {
        
        $_objPermiso = new \erpsoftsas\DAO_Permisos();
        $_objPermiso->set_per_IdRol($id_rol);

        if(!$_objPermiso->eliminar()){
            $return=0;
            //$this->_ok = 0;
            //$this->_mensaje = $_objPermiso->getMysqlError();
        }else{
            $id = $_objPermiso->get_per_Id();
            //ogs();
            //$_objlogs->_insertLogs(1,$id,1,5);
            $return= 1;
            //$this->_ok = 1;
            //$this->_mensaje = "Permiso eliminado correctamente";
        }  
        return $return;
    }  
}

class PermisosException extends \Exception{}

    \erpsoftsas\ControladorPermisos::run();

