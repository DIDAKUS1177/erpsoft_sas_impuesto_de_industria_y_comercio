<?php
namespace predial;
include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_Configuracion.php';
include_once SERVER . '/business/class.sessions.php';

class ControladorConfiguracion extends \predial\Cabecera {

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
                    $respuesta = $_obj->_agregarConfiguracion();
                    break;
                case 2:
                    $respuesta = $_obj->_editarConfiguracion();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarConfiguracion();
                    break; 
                case 4:
                    $respuesta = $_obj->_inactivarConfiguracion();
                    break; 
            }
            $con->commit();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
            
        } catch (\predial\ConfiguracionException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    /**
    *** Realiza el proceso de Crear Configuracion.
    **/ 
    protected function _agregarConfiguracion() {
        
        $_objRol = new \predial\DAO_Configuracion();
        $_objRol->set_con_NombreDirector($_POST['nombreDirector']);
        $_objRol->set_con_Resolucion($_POST['resolucion']);
        $_objRol->set_con_Estado(1);
        
        
        //Valida si es igual el nombre a alguno de la BD.
        $nomRol= $this->_listarConfiguracion(0);
        $longitud = count($nomRol);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomRol[$i]['con_NombreDirector'] == $_objRol->get_con_NombreDirector()){
               $nomduplicado=1;
                break;
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un nombre de Director con el mismo nombre';
            $return= false; 
        }else {
            if(!$_objRol->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objRol->getMysqlError();
            }else{
                $id = $_objRol->get_con_Id();
                $this->_ok = 1;
                $this->_mensaje = "Configuracion ingresados correctamente";
            }
            $return= $_objRol->guardar();
        }
        return $return;
    }
       
    /**
    *** Realiza el proceso de Editar Configuracion
    **/ 
    protected function _editarConfiguracion() {

        $_objRol = new \predial\DAO_Configuracion();
        $_objRol->set_con_Id($_POST['id']);

        if(isset($_POST['nombreDirector'])){
            if (!empty($_POST['nombreDirector']) || $_POST['nombreDirector'] != NULL ) {
                $_objRol->set_con_NombreDirector($_POST['nombreDirector']);
            }    
        }

        if(isset($_POST['resolucion'])){
            if (!empty($_POST['resolucion']) || $_POST['resolucion'] != NULL ) {
                $_objRol->set_con_Resolucion($_POST['resolucion']);
            }    
        }        

        //Valida si es igual el nombre a alguno de la BD.
        $nomRol= $this->_listarConfiguracion($_objRol->get_con_Id());
        $longitud = count($nomRol);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomRol[$i]['con_NombreDirector'] == $_objRol->get_con_NombreDirector()){
               $nomduplicado=1;
                break;
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un nombre de Director con el mismo nombre';
            $return= false; 
        }else {
            if(!$_objRol->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objRol->getMysqlError();
            }else{
                $id = $_objRol->get_con_Id();
                $this->_ok = 1;
                $this->_mensaje = "Configuracion editado correctamente";
            }
            $return= $_objRol->guardar();
        }
        return $return;
    }
    
        
    /**
    *** Realiza el proceso de Listar roles, exeptuando el rol enviado por parametro.
    *** @param type $id_rol
    **/  
    private function _listarConfiguracion($id_con) {
       
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT * FROM pre_configuracion WHERE con_Id <> $id_con ";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "configuracion listadas";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existen configuracion";
            $row=[];
        }
        return $row;     
    }  
    
    /**
    *** Realiza el proceso de Activar o Inactivar Configuracion.
    **/ 
    protected function _inactivarConfiguracion() {

        $_objRol = new \predial\DAO_Configuracion();
        $_objRol->set_con_Id($_POST['id']);
        $_objRol->set_con_Estado($_POST['estado']);

        if(!$_objRol->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objRol->getMysqlError();
        }else{
            $id = $_objRol->get_con_Id();
            $this->_ok = 1;
            $this->_mensaje = "Configuracion Activado/Inactivado correctamente";
        }
        return $_objRol->getArray();
    }
    
    /**
    *** Realiza el proceso de Consultar Bodegas.
    **/ 
    private function _consultarConfiguracion() {
       
        $_objRol = new \predial\DAO_Configuracion();
        
        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objRol->set_con_Id($_POST['id']);
            }    
        }
        
        if(isset($_POST['nombreDirector'])){
            if (!empty($_POST['nombreDirector']) || $_POST['nombreDirector'] != NULL ) {
                $_objRol->set_con_NombreDirector($_POST['nombreDirector']);
            }    
        }

        if(isset($_POST['resolucion'])){
            if (!empty($_POST['resolucion']) || $_POST['resolucion'] != NULL ) {
                $_objRol->set_con_Resolucion($_POST['resolucion']);
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
            $this->_mensaje = "Configuracion listadas con exito";
        }else{
            $R=$_objRol;
            $this->_ok = 0;
            $this->_mensaje = "No existen Configuracion";            
        }
        return $R;
    }  
}

class ConfiguracionException extends \Exception{}

    \predial\ControladorConfiguracion::run();

