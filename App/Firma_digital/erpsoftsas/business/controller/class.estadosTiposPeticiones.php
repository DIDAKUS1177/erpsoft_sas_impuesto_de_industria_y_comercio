<?php
namespace erpsoftsas;
include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/DAO/DAO_EstadosTiposPeticiones.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER.'/business/controller/class.logs.php';

class ControladorEstadosTiposPeticiones extends \erpsoftsas\Cabecera {
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
                    $respuesta = $_obj->_agregarEstadosTiposPeticiones();
                    break;
                case 2:
                    $respuesta = $_obj->_editarEstadosTiposPeticiones();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarEstadosTiposPeticiones();
                    break; 
                case 4:
                    $respuesta = $_obj->_eliminarEstadosTiposPeticiones();
                    break; 
            }
            $con->commit();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
        } catch (\erpsoftsas\EstadosTiposPeticionesException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "Error: " . $e->getMessage(), "datos" => "");
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    protected function _agregarEstadosTiposPeticiones() {
        $_obj = new \erpsoftsas\DAO_EstadosTiposPeticiones();
        $_obj->set_estipe_IdTipoPeticion($_POST['estipe_IdTipoPeticion']);
        $_obj->set_estipe_IdEstado($_POST['estipe_IdEstado']);
        $_obj->set_estipe_OrdenProceso($_POST['estipe_OrdenProceso']);

        //Valida los campos que no pueden Duplicarsen en la BD.
        $nomUsurio= $this->_listarTiposPeticiones($_obj->get_estipe_IdTipoPeticion());
        $longitud = count($nomUsurio);
        $nomduplicado=0;

        for($i=0; $i<$longitud; $i++){  
            if($nomUsurio[$i]['estipe_IdEstado'] == $_obj->get_estipe_IdEstado()){
                $nomduplicado=1;
                break;
            }
            if($nomUsurio[$i]['estipe_OrdenProceso'] == $_obj->get_estipe_OrdenProceso()){
                $nomduplicado=2;
                break;
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un Estado igual asociado al Tipo de Peticion';
            $return= false; 
        }else if($nomduplicado == 2){
            $this->_ok = 3;
            $this->_mensaje = 'Ya existe un Orden igual asociado al Tipo de Peticion';
            $return= false; 
        }else{

            if(!$_obj->guardar()) {
                $this->_ok = 0;
                $this->_mensaje = $_obj->getMysqlError();
            } else {
                $id = $_obj->get_estipe_Id();
                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($id,1,2,7);
                $this->_ok = 1;
                $this->_mensaje = "Datos ingresados correctamente";
            }
            $return= $_obj->guardar();
        }
        return $return;
    }

    private function _listarTiposPeticiones($id_tipoPeticion) {
       
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT * FROM estados_tipos_peticiones WHERE estipe_IdTipoPeticion = $id_tipoPeticion";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "TiposPeticiones listados";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existen TiposPeticiones";
            $row=[];
        }
        return $row;     
    }  
    

    protected function _editarEstadosTiposPeticiones() {
        $_obj = new \erpsoftsas\DAO_EstadosTiposPeticiones();
        $_obj->set_estipe_Id($_POST['id']);
        $_obj->set_estipe_IdTipoPeticion($_POST['estipe_IdTipoPeticion']);
        $_obj->set_estipe_IdEstado($_POST['estipe_IdEstado']);
        $_obj->set_estipe_OrdenProceso($_POST['estipe_OrdenProceso']);
        if(!$_obj->guardar()) {
            $this->_ok = 0;
            $this->_mensaje = $_obj->getMysqlError();
        } else {
            $id = $_obj->get_estipe_Id();
            //$_objlogs = new logs();
            //$_objlogs->_insertLogs($id,1,2,8);
            $this->_ok = 1;
            $this->_mensaje = "Datos actualizados correctamente";
        }
        return $_obj->guardar();
    }

    private function _consultarEstadosTiposPeticiones() {

        $_obj = new \erpsoftsas\DAO_EstadosTiposPeticiones();

        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_obj->set_estipe_Id($_POST['id']);
            }    
        }

        if(isset($_POST['idTipoPeticion'])){
            if (!empty($_POST['idTipoPeticion']) || $_POST['idTipoPeticion'] != NULL ) {
                $_obj->set_estipe_IdTipoPeticion($_POST['idTipoPeticion']);
            }    
        }

        if(isset($_POST['idEstado'])){
            if (!empty($_POST['idEstado']) || $_POST['idEstado'] != NULL ) {
                $_obj->set_estipe_IdEstado($_POST['idEstado']);
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
            $this->_ok = 0;
            $this->_mensaje = "No existen EstadosTiposPeticioness";            
            $R = [];
        }
        return $R;
    }

        /**
    *** Realiza el proceso de Eliminar un Estado tipo peticion.
    **/   
    protected function _eliminarEstadosTiposPeticiones() {
        
        $_obj = new \erpsoftsas\DAO_EstadosTiposPeticiones();
        $_obj->set_estipe_Id($_POST['id']);

        if(!$_obj->eliminar()){
            $this->_ok = 0;
            $this->_mensaje = $_obj->getMysqlError();
        }else{
            $id = $_obj->get_estipe_Id();
            //$_objlogs = new logs();
            //$_objlogs->_insertLogs(1,$id,1,5);
            $this->_ok = 1;
            $this->_mensaje = "EstadosTiposPeticiones eliminado correctamente";
        }  
        return $_obj->getArray();
    } 

}

class EstadosTiposPeticionesException extends \Exception { }
\erpsoftsas\ControladorEstadosTiposPeticiones::run();
