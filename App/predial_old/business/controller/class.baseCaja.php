<?php
namespace predial;
include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_BaseCaja.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER .'/business/controller/class.logs.php';


class ControladorBaseCaja extends \predial\Cabecera {

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
                    $respuesta = $_obj->_agregarBaseCaja();
                    break;
                case 2:
                    $respuesta = $_obj->_editarBaseCaja();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarBaseCaja();
                    break; 
                case 4:
                    $respuesta = $_obj->_cerrarBaseCaja();
                    break; 
            }
            $con->commit();
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
            
        } catch (\predial\BaseCajaException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    /**
    * _agregarBaseCaja: Método que realiza el proceso de Crear BaseCaja.
    */ 
    protected function _agregarBaseCaja() {
        
        $_objBaseCaja = new \predial\DAO_BaseCaja();
        $_objBaseCaja->set_bace_IdCaja($_POST['bace_IdCaja']);
        $_objBaseCaja->set_bace_IdVendedor($_POST['bace_IdVendedor']);
        $_objBaseCaja->set_bace_Base($_POST['bace_Base']);
        $_objBaseCaja->set_bace_Cierre(0);
        //$_objBaseCaja->set_bace_IdCierre($_POST['bace_IdCierre']);
        
        //Valida si es igual el nombre a alguno de la BD.
        $nomBaseCaja= $this->_listarBaseCaja(0);
        $longitud = count($nomBaseCaja);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomBaseCaja[$i]['bace_IdCaja'] == $_objBaseCaja->get_bace_IdCaja()){
                if($nomBaseCaja[$i]['bace_Cierre'] == 0){
                    $nomduplicado=1;
                    break;   
                }
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Existe una base sin cierre en la caja.';
            $return= false; 
        }else {
            if(!$_objBaseCaja->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objBaseCaja->getMysqlError();
            }else{
                $id = $_objBaseCaja->get_bace_Id();
                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,2);
                $this->_ok = 1;
                $this->_mensaje = "BaseCaja ingresados correctamente";
            }
            $return= $_objBaseCaja->guardar();
        }
        return $return;
    }
       
    /**
    * _editarBaseCaja: Método que realiza el proceso de Editar BaseCaja.
    */ 
    protected function _editarBaseCaja() {

        $_objBaseCaja = new \predial\DAO_BaseCaja();
        $_objBaseCaja->set_bace_Id($_POST['id']);
        $_objBaseCaja->set_bace_IdCaja($_POST['bace_IdCaja']);
        $_objBaseCaja->set_bace_IdVendedor($_POST['bace_IdVendedor']);
        $_objBaseCaja->set_bace_Base($_POST['bace_Base']);
        $_objBaseCaja->set_bace_Cierre(0);    

        //Valida si es igual el nombre a alguno de la BD.
        $nomBaseCaja= $this->_listarBaseCaja($_objBaseCaja->get_bace_Id());
        $longitud = count($nomBaseCaja);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomBaseCaja[$i]['bace_IdCaja'] == $_objBaseCaja->get_bace_IdCaja()){
                if($nomBaseCaja[$i]['bace_Cierre'] == 0){
                    $nomduplicado=1;
                    break;   
                }
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Existe una base sin cierre en la caja.';
            $return= false; 
        }else {
            if(!$_objBaseCaja->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objBaseCaja->getMysqlError();
            }else{
                $id = $_objBaseCaja->get_bace_Id();
                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,3);
                $this->_ok = 1;
                $this->_mensaje = "BaseCaja editado correctamente";
            }
            $return= $_objBaseCaja->guardar();
        }
        return $return;
    }
    
    /**
    * _listarBaseCajaes: Método que realiza el proceso 
    * de Listar BaseCaja, exeptuando el BaseCaja enviado por parametro.
    * @param type $id_BaseCaja: llave primaria de la tabla BaseCaja
    */  
    private function _listarBaseCaja($id_BaseCaja) {
       
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT * FROM fac_base_caja WHERE bace_Id <> $id_BaseCaja ";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "BaseCaja listados";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existen BaseCaja";
            $row=[];
        }
        return $row;     
    }  
    
    /**
    * _cerrarBaseCaja: Método que realiza el proceso de 
    * cerrar BaseCaja.
    */ 
    protected function _cerrarBaseCaja() {

        $_objBaseCaja = new \predial\DAO_BaseCaja();
        $_objBaseCaja->set_bace_Id($_POST['id']);
        $_objBaseCaja->set_bace_Cierre(1);
        $_objBaseCaja->set_bace_IdCierre($_POST['bace_IdCierre']);

        if(!$_objBaseCaja->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objBaseCaja->getMysqlError();
        }else{
            $id = $_objBaseCaja->get_bace_Id();
            //$_objlogs = new logs();
            //$_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,4);
            $this->_ok = 1;
            $this->_mensaje = "BaseCaja Cerrada correctamente";
        }
        return $_objBaseCaja->getArray();
    }
    
    /**
    * _consultarBaseCaja: Método que ealiza el proceso de Consultar BaseCaja.
    */ 
    private function _consultarBaseCaja() {
       
        $_objBaseCaja = new \predial\DAO_BaseCaja();
        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objBaseCaja->set_bace_Id($_POST['id']);
            }    
        }

        if(isset($_POST['bace_IdCaja'])){
            if (!empty($_POST['bace_IdCaja']) || $_POST['bace_IdCaja'] != NULL ) {
                $_objBaseCaja->set_bace_IdCaja($_POST['bace_IdCaja']);
            }    
        }
        
        if(isset($_POST['bace_Cierre'])){
            if (!empty($_POST['bace_Cierre']) || $_POST['bace_Cierre'] != NULL ) {
                $_objBaseCaja->set_bace_Cierre($_POST['bace_Cierre']);
            }    
        }

        if(isset($_POST['idRol']) and ($_POST['idRol']!=1) ){
            if(isset($_POST['bace_IdVendedor'])){
                if (!empty($_POST['bace_IdVendedor']) || $_POST['bace_IdVendedor'] != NULL ) {
                    $_objBaseCaja->set_bace_IdVendedor($_POST['bace_IdVendedor']);
                }    
            }
        }

        $_objBaseCaja->habilita1ResultadoEnArray();
        $arrBaseCaja = $_objBaseCaja->consultar();
       
        if(is_array($arrBaseCaja) && count($arrBaseCaja)){
            $R = [];
            foreach($arrBaseCaja as $obj){
                $R[] = $obj->getArray();
            }
            $this->_ok = 1;
            $this->_mensaje = "BaseCaja listados con exito";
        }else{
            $R=$_objBaseCaja;
            $this->_ok = 0;
            $this->_mensaje = "No existen BaseCaja";            
        }
        return $R;
    }  
}

class BaseCajaException extends \Exception{}

    \predial\ControladorBaseCaja::run();

