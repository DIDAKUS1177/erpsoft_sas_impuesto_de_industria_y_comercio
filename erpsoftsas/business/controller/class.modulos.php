<?php
namespace erpsoftsas;
include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/DAO/DAO_Modulos.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER.'/business/controller/class.logs.php';

class Modulos extends \erpsoftsas\Cabecera {

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
                    //$respuesta = $_obj->_agregarModulos();
                    break;
                case 2:
                    //$respuesta = $_obj->_editarModulos();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarModulos();
                    break; 
                case 4:
                    //$respuesta = $_obj->_inactivarModulos();
                    break; 
            }
            //$con->commit();
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
        } catch (\erpsoftsas\ModulosException $e) {
            //$con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    /**
    *** Realiza el proceso de Consultar Modulos.
    **/ 
    private function _consultarModulos() {
       
        $_objModulos = new \erpsoftsas\DAO_Modulos();
        if(isset($_POST['tipo_configuracion'])){
            if (!empty($_POST['tipo_configuracion']) || $_POST['tipo_configuracion'] != NULL ) {
                $_objModulos->set_tipo_configuracion($_POST['tipo_configuracion']);
            }
        }
        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objModulos->set_tipo_configuracion($_POST['id']);
            }
        }
        
        $_objModulos->habilita1ResultadoEnArray();
        $arrModulos = $_objModulos->consultar();
       
        if(is_array($arrModulos) && count($arrModulos)){
            $R = [];
            foreach($arrModulos as $obj){
                $R[] = $obj->getArray();
            }
            $this->_ok = 1;
            $this->_mensaje = "Modulos listados con exito";
        }else{
            $R=$arrModulos;
            $this->_ok = 0;
            $this->_mensaje = "No existen Modulos";
        }
        return $R;
    }
}

class ModulosException extends \Exception{}

    \erpsoftsas\Modulos::run();

