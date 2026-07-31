<?php
namespace predial;
include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_FormasPago.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER .'/business/controller/class.logs.php';

class ControladorFormasPago extends \predial\Cabecera {

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
                    $respuesta = $_obj->_agregarFormasPago();
                    break;
                case 2:
                    $respuesta = $_obj->_editarFormasPago();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarFormasPago();
                    break; 
                case 4:
                    $respuesta = $_obj->_inactivarFormasPago();
                    break; 
            }
            $con->commit();
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
            
        } catch (\predial\FormasPagoException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    /**
    * _agregarFormasPago: Método que realiza el proceso de Crear FormasPagoes.
    */ 
    protected function _agregarFormasPago() {
        
        $_objFormasPago = new \predial\DAO_FormasPago();
        $_objFormasPago->set_forpa_Descripcion($_POST['nombre']);
        $_objFormasPago->set_forpa_Saldada($_POST['forpa_Saldada']);
        $_objFormasPago->set_forpa_Estado(1);
        
        //Valida si es igual el nombre a alguno de la BD.
        $nomFormasPago= $this->_listarFormasPago(0);
        $longitud = count($nomFormasPago);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomFormasPago[$i]['forpa_Descripcion'] == $_objFormasPago->get_forpa_Descripcion()){
               $nomduplicado=1;
                break;
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe una Forma de pago con el mismo nombre';
            $return= false; 
        }else {
            if(!$_objFormasPago->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objFormasPago->getMysqlError();
            }else{
                $id = $_objFormasPago->get_forpa_Id();
                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,2);
                $this->_ok = 1;
                $this->_mensaje = "FormasPago ingresados correctamente";
            }
            $return= $_objFormasPago->guardar();
        }
        return $return;
    }
       
    /**
    * _editarFormasPago: Método que realiza el proceso de Editar FormasPagoes.
    */ 
    protected function _editarFormasPago() {

        $_objFormasPago = new \predial\DAO_FormasPago();
        $_objFormasPago->set_forpa_Id($_POST['id']);
        $_objFormasPago->set_forpa_Descripcion($_POST['nombre']);
        $_objFormasPago->set_forpa_Saldada($_POST['forpa_Saldada']);
        

        //Valida si es igual el nombre a alguno de la BD.
        $nomFormasPago= $this->_listarFormasPago($_objFormasPago->get_forpa_Id());
        $longitud = count($nomFormasPago);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomFormasPago[$i]['forpa_Descripcion'] == $_objFormasPago->get_forpa_Descripcion()){
               $nomduplicado=1;
                break;
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un nombre de la forma de pago con el mismo nombre';
            $return= false; 
        }else {
            if(!$_objFormasPago->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objFormasPago->getMysqlError();
            }else{
                $id = $_objFormasPago->get_forpa_Id();
                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,3);
                $this->_ok = 1;
                $this->_mensaje = "Forma de Pago editada correctamente";
            }
            $return= $_objFormasPago->guardar();
        }
        return $return;
    }
    
    /**
    * _listarFormasPagoes: Método que realiza el proceso 
    * de Listar roles, exeptuando el rol enviado por parametro.
    * @param type $id_rol: llave primaria de la tabla rol
    */  
    private function _listarFormasPago($id_formasPago) {
       
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT * FROM fac_formas_pago WHERE forpa_Id <> $id_formasPago";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "Forma de Pago listados";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existen Forma de Pago";
            $row=[];
        }
        return $row;     
    }  
    
    /**
    * _inactivarFormasPago: Método que realiza el proceso de 
    * Activar o Inactivar FormasPagoes.
    */ 
    protected function _inactivarFormasPago() {

        $_objFormasPago = new \predial\DAO_FormasPago();
        $_objFormasPago->set_forpa_Id($_POST['id']);
        $_objFormasPago->set_forpa_Estado($_POST['estado']);

        if(!$_objFormasPago->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objFormasPago->getMysqlError();
        }else{
            $id = $_objFormasPago->get_forpa_Id();
            // $_objlogs = new logs();
            // $_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,4);
            $this->_ok = 1;
            $this->_mensaje = "Forma de Pago Activado/Inactivado correctamente";
        }
        return $_objFormasPago->getArray();
    }
    
    /**
    * _consultarFormasPago: Método que ealiza el proceso de Consultar FormasPagoes.
    */ 
    private function _consultarFormasPago() {
       
        $_objFormasPago = new \predial\DAO_FormasPago();
        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objFormasPago->set_forpa_Id($_POST['id']);
            }    
        }

        if(isset($_POST['estado'])){
            if (!empty($_POST['estado']) || $_POST['estado'] != NULL ) {
                $_objFormasPago->set_forpa_Estado($_POST['estado']);
            }    
        }
        
        if(isset($_POST['forpa_Saldada'])){
            if (!empty($_POST['forpa_Saldada']) || $_POST['forpa_Saldada'] != NULL ) {
                $_objFormasPago->set_forpa_Saldada($_POST['forpa_Saldada']);
            }    
        }

        $_objFormasPago->habilita1ResultadoEnArray();
        $arrFormasPago = $_objFormasPago->consultar();
       
        if(is_array($arrFormasPago) && count($arrFormasPago)){
            $R = [];
            foreach($arrFormasPago as $obj){
                $R[] = $obj->getArray();
            }
            $this->_ok = 1;
            $this->_mensaje = "Formas de Pago listados con exito";
        }else{
            $R=$_objFormasPago;
            $this->_ok = 0;
            $this->_mensaje = "No existen Formas de Pago";            
        }
        return $R;
    }  
}

class FormasPagoException extends \Exception{}

    \predial\ControladorFormasPago::run();

