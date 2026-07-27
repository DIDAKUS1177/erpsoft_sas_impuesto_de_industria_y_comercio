<?php
namespace erpsoftsas;
include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/DAO/DAO_Estados.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER.'/business/controller/class.logs.php';

class ControladorEstados extends \erpsoftsas\Cabecera {
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
                    $respuesta = $_obj->_agregarEstados();
                    break;
                case 2:
                    $respuesta = $_obj->_editarEstados();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarEstados();
                    break; 
                case 4:
                    $respuesta = $_obj->_inactivarEstados();
                    break; 
            }
            $con->commit();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
        } catch (\erpsoftsas\EstadosException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "Error: " . $e->getMessage(), "datos" => "");
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    protected function _agregarEstados() {
        $_obj = new \erpsoftsas\DAO_Estados();
        $_obj->set_est_Nombre($_POST['est_Nombre']);
        $_obj->set_est_Descripcion($_POST['est_Descripcion']);
        $_obj->set_est_Color($_POST['est_Color']);
        $_obj->set_est_Estado(1);

        
        //Valida los campos que no pueden Duplicarsen en la BD.
        $nomUsurio= $this->_listarEstados(0);
        $longitud = count($nomUsurio);
        $nomduplicado=0;

        for($i=0; $i<$longitud; $i++){  
            if($nomUsurio[$i]['est_Nombre'] == $_obj->get_est_Nombre()){
               $nomduplicado=1;
                break;
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un Estado con el mismo nombre';
            $return= false; 
        }else{
            if(!$_obj->guardar()) {
                $this->_ok = 0;
                $this->_mensaje = $_obj->getMysqlError();
            } else {
                $id = $_obj->get_est_Id();
                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($id,1,2,7);
                $this->_ok = 1;
                $this->_mensaje = "Datos ingresados correctamente";
            }
            $return= $_obj->guardar();
        }
        return $return;
    }

    protected function _editarEstados() {
        $_obj = new \erpsoftsas\DAO_Estados();
        $_obj->set_est_Id($_POST['id']);
        $_obj->set_est_Nombre($_POST['est_Nombre']);
        $_obj->set_est_Descripcion($_POST['est_Descripcion']);
        $_obj->set_est_Color($_POST['est_Color']);

        //Valida los campos que no pueden Duplicarsen en la BD.
        $nomUsurio= $this->_listarEstados($_obj->get_est_Id());
        $longitud = count($nomUsurio);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomUsurio[$i]['est_Nombre'] == $_obj->get_est_Nombre()){
               $nomduplicado=1;
                break;
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un estado con el mismo Nombre';
            $return= false; 
        }else{
            if(!$_obj->guardar()) {
                $this->_ok = 0;
                $this->_mensaje = $_obj->getMysqlError();
            } else {
                $id = $_obj->get_est_Id();
                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($id,1,2,8);
                $this->_ok = 1;
                $this->_mensaje = "Datos actualizados correctamente";
            }
            $return= $_obj->guardar();
        }
        return $return;
    }

    private function _consultarEstados() {
        $_obj = new \erpsoftsas\DAO_Estados();

        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_obj->set_est_Id($_POST['id']);
            }    
        }

        if(isset($_POST['nombre'])){
            if (!empty($_POST['nombre']) || $_POST['nombre'] != NULL ) {
                $_obj->set_est_Nombre($_POST['nombre']);
            }    
        }

        if(isset($_POST['estado'])){
            if (!empty($_POST['estado']) || $_POST['estado'] != NULL ) {
                $_obj->set_est_Estado($_POST['estado']);
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
            $this->_mensaje = "EstadosTiposPeticioness listados con éxito"; 
        } else {
            $R = $_obj;
            $this->_ok = 0;
            $this->_mensaje = "No existen EstadosTiposPeticioness";            
        }
        return $R;
    }

    protected function _inactivarEstados() {
        $_obj = new \erpsoftsas\DAO_Estados();
        $_obj->set_est_Id($_POST['id']);
        $_obj->set_est_estado($_POST['estado']);
        if(!$_obj->guardar()) {
            $this->_ok = 0;
            $this->_mensaje = $_obj->getMysqlError();
        } else {
            $id = $_obj->get_est_Id();
            //$_objlogs = new logs();
            //$_objlogs->_insertLogs($id,1,2,9);
            $this->_ok = 1;
            $this->_mensaje = "EstadosTiposPeticiones inactivado correctamente";
        }
        return $_obj->getArray();
    }

    private function _listarEstados($id_estado) {
       
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT * FROM estados WHERE est_Id <> $id_estado";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "Estados listados";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existen Estados";
            $row=[];
        }
        return $row;     
    }  

}

class EstadosException extends \Exception { }
\erpsoftsas\ControladorEstados::run();
