<?php
namespace predial;
include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_TipoPersona.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER .'/business/controller/class.logs.php';

class ControladorTipoPersona extends \predial\Cabecera {

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
                    $respuesta = $_obj->_agregarTipoPersona();
                    break;
                case 2:
                    $respuesta = $_obj->_editarTipoPersona();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarTipoPersona();
                    break; 
                /* case 4:
                    $respuesta = $_obj->_inactivarBodega();
                    break;  */
            }
            $con->commit();
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
            
        } catch (\predial\TipoPersonaException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    /**
    *** Realiza el proceso de Crear Tipo de persona.
    **/ 
    protected function _agregarTipoPersona() {
        
        $_objRol = new \predial\DAO_TipoPersona();
        $_objRol->set_tip_Descripcion($_POST['tip_Descripcion']);
        $_objRol->set_tip_DIAN($_POST['tip_Descripcion']);
        
        
        //Valida si es igual el nombre a alguno de la BD.
        $nomRol= $this->_listarTipoPersona(0);
        $longitud = count($nomRol);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomRol[$i]['tip_Descripcion'] == $_objRol->get_tip_Descripcion()){
               $nomduplicado=1;
                break;
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un nombre de roltipo persona con la misma descripción';
            $return= false; 
        }else {
            if(!$_objRol->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objRol->getMysqlError();
            }else{
                $id = $_objRol->get_tip_Id();
                $_objlogs = new logs();
                $_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,2);
                $this->_ok = 1;
                $this->_mensaje = "Tipo persona ingresado correctamente";
            }
            $return= $_objRol->guardar();
        }
        return $return;
    }
       
    /**
    *** Realiza el proceso de Editar Tipo de persona.
    **/ 
    protected function _editarTipoPersona() {

        $_objRol = new \predial\DAO_TipoPersona();
        $_objRol->set_tip_Id($_POST['id']);
        $_objRol->set_tip_Descripcion($_POST['tip_Descripcion']);
        $_objRol->set_tip_DIAN($_POST['tip_DIAN']);
        

        //Valida si es igual el nombre a alguno de la BD.
        $nomRol= $this->_listarTipoPersona($_objRol->get_tip_Descripcion());
        $longitud = count($nomRol);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomRol[$i]['tip_Descripcion'] == $_objRol->get_tip_Descripcion()){
               $nomduplicado=1;
                break;
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un tipo de persona con la misma descripción';
            $return= false; 
        }else {
            if(!$_objRol->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objRol->getMysqlError();
            }else{
                $id = $_objRol->get_tip_Id();
                $_objlogs = new logs();
                $_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,3);
                $this->_ok = 1;
                $this->_mensaje = "Tipo de persona editado correctamente";
            }
            $return= $_objRol->guardar();
        }
        return $return;
    }
    
        
    /**
    *** Realiza el proceso de Listar los tipos de persona, 
    *** exeptuando el tipo de persona enviado por parametro.
    *** @param type $id_tipoPer
    **/  
    private function _listarTipoPersona($id_tipoPer) {
       
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT * FROM conf_tipo_persona WHERE tip_Id <> $id_tipoPer ";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "Tipos persona listados";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existen tipos personas";
            $row=[];
        }
        return $row;     
    }  
    
    /**
    *** Realiza el proceso de Activar o Inactivar Roles.
    **/ 
    protected function _inactivarBodega() {

        $_objRol = new \predial\DAO_Bodega();
        $_objRol->set_bod_Id($_POST['id']);
        $_objRol->set_bod_Estado($_POST['estado']);

        if(!$_objRol->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objRol->getMysqlError();
        }else{
            $id = $_objRol->get_bod_Id();
            $_objlogs = new logs();
                $_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,4);
            $this->_ok = 1;
            $this->_mensaje = "Bodega Activado/Inactivado correctamente";
        }
        return $_objRol->getArray();
    }
    
    /**
    *** Realiza el proceso de Consultar Bodegas.
    **/ 
    private function _consultarTipoPersona() {
       
        $_objRol = new \predial\DAO_TipoPersona();
        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objRol->set_tip_Id($_POST['id']);
            }    
        }

        if(isset($_POST['tip_DIAN'])){
            if (!empty($_POST['tip_DIAN']) || $_POST['tip_DIAN'] != NULL ) {
                $_objRol->set_tip_DIAN($_POST['tip_DIAN']);
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
            $this->_mensaje = "Tipos de persona listados con exito";
        }else{
            $R=$_objRol;
            $this->_ok = 0;
            $this->_mensaje = "No existen Tipos de persona";            
        }
        return $R;
    }  
}

class TipoPersonaException extends \Exception{}

    \predial\ControladorTipoPersona::run();

