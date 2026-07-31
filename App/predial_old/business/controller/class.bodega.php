<?php
namespace predial;
include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_Bodega.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER .'/business/controller/class.logs.php';

class ControladorBodega extends \predial\Cabecera {

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
                    $respuesta = $_obj->_agregarBodega();
                    break;
                case 2:
                    $respuesta = $_obj->_editarBodega();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarBodega();
                    break; 
                case 4:
                    $respuesta = $_obj->_inactivarBodega();
                    break; 
            }
            $con->commit();
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
            
        } catch (\predial\BodegaException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    /**
    *** Realiza el proceso de Crear Roles.
    **/ 
    protected function _agregarBodega() {
        
        $_objRol = new \predial\DAO_Bodega();
        $_objRol->set_bod_Nombre($_POST['nombre']);
        $_objRol->set_bod_IdTipo($_POST['tipo']);
        $_objRol->set_bod_Estado(1);
        
        
        //Valida si es igual el nombre a alguno de la BD.
        $nomRol= $this->_listarBodegas(0);
        $longitud = count($nomRol);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomRol[$i]['bod_Nombre'] == $_objRol->get_bod_Nombre()){
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
                $id = $_objRol->get_bod_Id();
                $_objlogs = new logs();
                $_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,2);
                $this->_ok = 1;
                $this->_mensaje = "Rol ingresados correctamente";
            }
            $return= $_objRol->guardar();
        }
        return $return;
    }
       
    /**
    *** Realiza el proceso de Editar Roles.
    **/ 
    protected function _editarBodega() {

        $_objRol = new \predial\DAO_Bodega();
        $_objRol->set_bod_Id($_POST['id']);
        $_objRol->set_bod_Nombre($_POST['nombre']);
        $_objRol->set_bod_IdTipo($_POST['tipo']);

        //Valida si es igual el nombre a alguno de la BD.
        $nomRol= $this->_listarBodegas($_objRol->get_bod_Id());
        $longitud = count($nomRol);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomRol[$i]['bod_Nombre'] == $_objRol->get_bod_Nombre()){
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
                $id = $_objRol->get_bod_Id();
                $_objlogs = new logs();
                $_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,3);
                $this->_ok = 1;
                $this->_mensaje = "Rol editado correctamente";
            }
            $return= $_objRol->guardar();
        }
        return $return;
    }
    
        
    /**
    *** Realiza el proceso de Listar roles, exeptuando el rol enviado por parametro.
    *** @param type $id_rol
    **/  
    private function _listarBodegas($id_bod) {
       
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT * FROM inv_bodega WHERE bod_Id <> $id_bod ";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "Bodegas listadas";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existen Bodegas";
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
    private function _consultarBodega() {
       
        $_objRol = new \predial\DAO_Bodega();
        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objRol->set_bod_Id($_POST['id']);
            }    
        }

        if(isset($_POST['tipo'])){
            if (!empty($_POST['tipo']) || $_POST['tipo'] != NULL ) {
                $_objRol->set_bod_IdTipo($_POST['tipo']);
            }    
        }

        if(isset($_POST['estado'])){
            if (!empty($_POST['estado']) || $_POST['estado'] != NULL ) {
                $_objRol->set_bod_Estado($_POST['estado']);
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
            $this->_mensaje = "Bodegas listadas con exito";
        }else{
            $R=$_objRol;
            $this->_ok = 0;
            $this->_mensaje = "No existen Bodegas";            
        }
        return $R;
    }  
}

class BodegaException extends \Exception{}

    \predial\ControladorBodega::run();

