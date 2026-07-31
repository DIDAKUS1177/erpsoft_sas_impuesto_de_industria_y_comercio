<?php
namespace predial;
include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_Departamentos.php';
include_once SERVER . '/business/DAO/DAO_Municipios.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER .'/business/controller/class.logs.php';

class ControladorDivipola extends \predial\Cabecera {

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
                    //$respuesta = $_obj->_agregarBodega();
                    break;
                case 2:
                    //$respuesta = $_obj->_editarBodega();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarDepartamentos();
                    break; 
                case 4:
                    $respuesta = $_obj->_consultarMunicipios();
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
    *** Realiza el proceso de Consultar Departamentos.
    **/ 
    private function _consultarDepartamentos() {
       
        $_objRol = new \predial\DAO_Departamentos();
        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objRol->set_dep_Id($_POST['id']);
            }    
        }

        if(isset($_POST['codigo'])){
            if (!empty($_POST['codigo']) || $_POST['codigo'] != NULL ) {
                $_objRol->set_dep_IdTipo($_POST['codigo']);
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
            $this->_mensaje = "Departamentos listadas con exito";
        }else{
            $R=$_objRol;
            $this->_ok = 0;
            $this->_mensaje = "No existen Departamentos";            
        }
        return $R;
    }  

    /**
    *** Realiza el proceso de Consultar Municipios.
    **/ 
    private function _consultarMunicipios() {
       
        $_objRol = new \predial\DAO_Municipios();
        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objRol->set_mun_Id($_POST['id']);
            }    
        }

        if(isset($_POST['codigo'])){
            if (!empty($_POST['codigo']) || $_POST['codigo'] != NULL ) {
                $_objRol->set_mun_Codigo($_POST['codigo']);
            }    
        }
        
        if(isset($_POST['departamento_id'])){
            if (!empty($_POST['departamento_id']) || $_POST['departamento_id'] != NULL ) {
                $_objRol->set_mun_IdDepartamento($_POST['departamento_id']);
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
            $this->_mensaje = "Municipios listadas con exito";
        }else{
            $R=$_objRol;
            $this->_ok = 0;
            $this->_mensaje = "No existen Municipios";            
        }
        return $R;
    }  
}

class DivipolaException extends \Exception{}

    \predial\ControladorDivipola::run();

