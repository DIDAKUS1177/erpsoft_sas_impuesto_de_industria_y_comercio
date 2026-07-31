<?php
namespace predial;
include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_PrediosGenerados.php';
include_once SERVER . '/business/DAO/DAO_PrediosFacMorososGenerados.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER .'/business/controller/class.logs.php';

class ControladorPrediosGenerados extends \predial\Cabecera {

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
                    $respuesta = $_obj->_agregarPrediosGenerados();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarPrediosGenerados();
                    break; 
                case 4:
                    $respuesta = $_obj->_agregarPredioFacturaMorosos();
                    break;
            }
            $con->commit();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
            
        } catch (\predial\PrediosGeneradosException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    /**
    *** Realiza el proceso de Crear PrediosGenerados.
    **/ 
    protected function _agregarPrediosGenerados() {
        
        $_objRol = new \predial\DAO_PrediosGenerados();
        $_objRol->set_pre_IdUsuario($_POST['idUsuario']);
        $_objRol->set_pre_CodigoPredio($_POST['codigoPredio']);        
        $_objRol->set_pre_Fecha($_POST['fecha']);   
        $_objRol->set_pre_FechaFinal($_POST['fechaFinal']);   

        $diaDoc = date("d");
        $mesDoc = date("m");
        $anioDoc = date("Y");

        $_objRol->set_pre_DiaCreacion($diaDoc);        
        $_objRol->set_pre_MesCreacion($mesDoc);   
        $_objRol->set_pre_AnioCreacion($anioDoc);   

        $nomUsurio= $this->_consultarDirector();
        $_objRol->set_pre_IdDirector($nomUsurio[0]['con_Id']);
   
        if(!$_objRol->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objRol->getMysqlError();
        }else{
            $id = $_objRol->get_pre_Id();
            $this->_ok = 1;
            $this->_mensaje = $id;
        }
        
        return $_objRol->guardar();
    }


    /**
    *** Realiza el proceso de Listar DIRECTOR
    **/  
    private function _consultarDirector() {
       
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT * from pre_configuracion con where con_Estado = 1 ORDER BY con_Id DESC limit 1";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
        }else{
            $row=[];
        }
        return $row;     
    }  
    
    
    /**
    *** Realiza el proceso de Consultar Bodegas.
    **/ 
    private function _consultarPrediosGenerados() {
       
        $_objRol = new \predial\DAO_PrediosGenerados();
        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objRol->set_pre_Id($_POST['id']);
            }    
        }

        if(isset($_POST['idUsuario'])){
            if (!empty($_POST['idUsuario']) || $_POST['idUsuario'] != NULL ) {
                $_objRol->set_pre_IdUsuario($_POST['idUsuario']);
            }    
        }

        if(isset($_POST['idDirector'])){
            if (!empty($_POST['idDirector']) || $_POST['idDirector'] != NULL ) {
                $_objRol->set_pre_IdDirector($_POST['idDirector']);
            }    
        }

        if(isset($_POST['codigoPredio'])){
            if (!empty($_POST['codigoPredio']) || $_POST['codigoPredio'] != NULL ) {
                $_objRol->set_pre_CodigoPredio($_POST['codigoPredio']);
            }    
        }

        if(isset($_POST['fecha'])){
            if (!empty($_POST['fecha']) || $_POST['fecha'] != NULL ) {
                $_objRol->set_pre_Fecha($_POST['fecha']);   
            }    
        }

        if(isset($_POST['fechaFinal'])){
            if (!empty($_POST['fechaFinal']) || $_POST['fechaFinal'] != NULL ) {
                $_objRol->set_pre_FechaFinal($_POST['fechaFinal']);  
            }    
        }

        if(isset($_POST['fechaCreacion'])){
            if (!empty($_POST['fechaCreacion']) || $_POST['fechaCreacion'] != NULL ) {
                $_objRol->set_pre_FechaCreacion($_POST['fechaCreacion']);
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
            $this->_mensaje = "PrediosGenerados listadas con exito";
        }else{
            $R=$_objRol;
            $this->_ok = 0;
            $this->_mensaje = "No existen PrediosGenerados";            
        }
        return $R;
    }  
	
	
	 /**
    *** Realiza el proceso de Crear registro de predio factura de morosos.
    **/ 
    protected function _agregarPredioFacturaMorosos() {
        
        $_objRol = new \predial\DAO_PrediosFacMorososGenerados();
        $_objRol->set_pre_IdUsuario($_POST['idUsuario']);
        $_objRol->set_pre_IdPredio($_POST['idPredio']);        
        $_objRol->set_pre_CodigoPredio($_POST['codigoPredio']);     
        $_objRol->set_pre_Anio($_POST['anio']);   

        //barcode( 'imaBarras/prueba.png', '123456789','70','horizontal','code128');

        $diaDoc = date("d");
        $mesDoc = date("m");
        $anioDoc = date("Y");

        $_objRol->set_pre_DiaCreacion($diaDoc);        
        $_objRol->set_pre_MesCreacion($mesDoc);   
        $_objRol->set_pre_AnioCreacion($anioDoc);   

        $nomUsurio= $this->_consultarDirector();
        $_objRol->set_pre_IdDirector($nomUsurio[0]['con_Id']);
   
        if(!$_objRol->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objRol->getMysqlError();
        }else{
            $id = $_objRol->get_pre_Id();
            $this->_ok = 1;
            $this->_mensaje = $id;
        }
        
        return $_objRol->guardar();
    }
}

class PrediosGeneradosException extends \Exception{}

    \predial\ControladorPrediosGenerados::run();

