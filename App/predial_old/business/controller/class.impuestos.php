<?php
namespace predial;
include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_Impuestos.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER .'/business/controller/class.logs.php';



class ControladorImpuestos extends \predial\Cabecera {

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
                    $respuesta = $_obj->_agregarImpuestos();
                    break;
                case 2:
                    $respuesta = $_obj->_editarImpuestos();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarImpuestos();
                    break; 
                case 4:
                    $respuesta = $_obj->_inactivarImpuestos();
                    break; 
            }
            $con->commit();
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
            
        } catch (\predial\ImpuestosException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    /**
    * _agregarImpuestos: Método que realiza el proceso de Crear Impuestos.
    */ 
    protected function _agregarImpuestos() {
        
        $_objImpuestos = new \predial\DAO_Impuestos();
        $_objImpuestos->set_imp_Descripcion($_POST['descripcion']);
        $_objImpuestos->set_imp_Porcentaje($_POST['porcentaje']);
        $_objImpuestos->set_imp_Estado(1);
        
        
        //Valida si es igual el nombre a alguno de la BD.
        $nomImpuestos= $this->_listarImpuestos(0);
        $longitud = count($nomImpuestos);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomImpuestos[$i]['imp_Porcentaje'] == $_objImpuestos->get_imp_Porcentaje()){
               $nomduplicado=1;
                break;
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un impuesto con el mismo porcentaje';
            $return= false; 
        }else {
            if(!$_objImpuestos->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objImpuestos->getMysqlError();
            }else{
                $id = $_objImpuestos->get_imp_Id();
                $_objlogs = new logs();
                $_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,2);
                $this->_ok = 1;
                $this->_mensaje = "Impuestos ingresados correctamente";
            }
            $return= $_objImpuestos->guardar();
        }
        return $return;
    }
       
    /**
    * _editarImpuestos: Método que realiza el proceso de Editar Impuestos.
    */ 
    protected function _editarImpuestos() {

        $_objImpuestos = new \predial\DAO_Impuestos();
        $_objImpuestos->set_imp_Id($_POST['id']);
        $_objImpuestos->set_imp_Descripcion($_POST['descripcion']);
        $_objImpuestos->set_imp_Porcentaje($_POST['porcentaje']);        

        //Valida si es igual el nombre a alguno de la BD.
        $nomImpuestos= $this->_listarImpuestoses($_objImpuestos->get_imp_Id());
        $longitud = count($nomImpuestos);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomImpuestos[$i]['imp_Porcentaje'] == $_objImpuestos->get_imp_Porcentaje()){
               $nomduplicado=1;
                break;
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un impuesto con el mismo porcentaje';
            $return= false; 
        }else {
            if(!$_objImpuestos->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objImpuestos->getMysqlError();
            }else{
                $id = $_objImpuestos->get_imp_Id();
                $_objlogs = new logs();
                $_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,3);
                $this->_ok = 1;
                $this->_mensaje = "Impuestos editado correctamente";
            }
            $return= $_objImpuestos->guardar();
        }
        return $return;
    }
    
    /**
    * _listarImpuestoses: Método que realiza el proceso 
    * de Listar Impuestos, exeptuando el Impuestos enviado por parametro.
    * @param type $id_Impuestos: llave primaria de la tabla Impuestos
    */  
    private function _listarImpuestos($id_Impuestos) {
       
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT * FROM fac_impuestos WHERE imp_Id <> $id_Impuestos ";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "Impuestos listados";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existen Impuestos";
            $row=[];
        }
        return $row;     
    }  
    
    /**
    * _inactivarImpuestos: Método que realiza el proceso de 
    * Activar o Inactivar Impuestos.
    */ 
    protected function _inactivarImpuestos() {

        $_objImpuestos = new \predial\DAO_Impuestos();
        $_objImpuestos->set_imp_Id($_POST['id']);
        $_objImpuestos->set_imp_Estado($_POST['estado']);

        if(!$_objImpuestos->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objImpuestos->getMysqlError();
        }else{
            $id = $_objImpuestos->get_imp_Id();
            $_objlogs = new logs();
                $_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,4);
            $this->_ok = 1;
            $this->_mensaje = "Impuestos Activado/Inactivado correctamente";
        }
        return $_objImpuestos->getArray();
    }
    
    /**
    * _consultarImpuestos: Método que ealiza el proceso de Consultar Impuestos.
    */ 
    private function _consultarImpuestos() {
       
        $_objImpuestos = new \predial\DAO_Impuestos();
        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objImpuestos->set_imp_Id($_POST['id']);
            }    
        }

        if(isset($_POST['estado'])){
            if (!empty($_POST['estado']) || $_POST['estado'] != NULL ) {
                $_objImpuestos->set_imp_Estado($_POST['estado']);
            }    
        }
        
        $_objImpuestos->habilita1ResultadoEnArray();
        $arrImpuestos = $_objImpuestos->consultar();
       
        if(is_array($arrImpuestos) && count($arrImpuestos)){
            $R = [];
            foreach($arrImpuestos as $obj){
                $R[] = $obj->getArray();
            }
            $this->_ok = 1;
            $this->_mensaje = "Impuestos listados con exito";
        }else{
            $R=$_objImpuestos;
            $this->_ok = 0;
            $this->_mensaje = "No existen Impuestos";            
        }
        return $R;
    }  
}

class ImpuestosException extends \Exception{}

    \predial\ControladorImpuestos::run();

