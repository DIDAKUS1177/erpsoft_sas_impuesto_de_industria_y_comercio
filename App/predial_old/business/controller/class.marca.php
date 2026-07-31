<?php
namespace predial;
include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_Marca.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER .'/business/controller/class.logs.php';

class ControladorMarca extends \predial\Cabecera {

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
                    $respuesta = $_obj->_agregarMarca();
                    break;
                case 2:
                    $respuesta = $_obj->_editarMarca();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarMarca();
                    break; 
                case 4:
                    $respuesta = $_obj->_inactivarMarca();
                    break; 
            }
            $con->commit();
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
            
        } catch (\predial\MarcaException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    /**
    * _agregarMarca: Método que realiza el proceso de Crear Marcaes.
    */ 
    protected function _agregarMarca() {
        
        $_objMarca = new \predial\DAO_Marca();
        $_objMarca->set_mar_Descripcion($_POST['nombre']);
        $_objMarca->set_mar_Estado(1);
        
        //Valida si es igual el nombre a alguno de la BD.
        $nomMarca= $this->_listarMarca(0);
        $longitud = count($nomMarca);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomMarca[$i]['mar_Descripcion'] == $_objMarca->get_mar_Descripcion()){
               $nomduplicado=1;
                break;
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe una marca con el mismo nombre';
            $return= false; 
        }else {
            if(!$_objMarca->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objMarca->getMysqlError();
            }else{
                $id = $_objMarca->get_mar_Id();
                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,2);
                $this->_ok = 1;
                $this->_mensaje = "Marca ingresada correctamente";
            }
            $return= $_objMarca->guardar();
        }
        return $return;
    }
       
    /**
    * _editarMarca: Método que realiza el proceso de Editar Marcaes.
    */ 
    protected function _editarMarca() {

        $_objMarca = new \predial\DAO_Marca();
        $_objMarca->set_mar_Id($_POST['id']);
        $_objMarca->set_mar_Descripcion($_POST['nombre']);

        //Valida si es igual el nombre a alguno de la BD.
        $nomMarca= $this->_listarMarca($_objMarca->get_mar_Id());
        $longitud = count($nomMarca);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomMarca[$i]['mar_Descripcion'] == $_objMarca->get_mar_Descripcion()){
               $nomduplicado=1;
                break;
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un nombre de la marca con el mismo nombre';
            $return= false; 
        }else {
            if(!$_objMarca->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objMarca->getMysqlError();
            }else{
                $id = $_objMarca->get_mar_Id();
                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,3);
                $this->_ok = 1;
                $this->_mensaje = "Marca editada correctamente";
            }
            $return= $_objMarca->guardar();
        }
        return $return;
    }
    
    /**
    * _listarMarcaes: Método que realiza el proceso 
    * de Listar roles, exeptuando el rol enviado por parametro.
    * @param type $id_rol: llave primaria de la tabla rol
    */  
    private function _listarMarca($id_marca) {
       
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT * FROM inv_marca WHERE mar_Id <> $id_marca";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "Marcas listados";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existen Marcas";
            $row=[];
        }
        return $row;     
    }  
    
    /**
    * _inactivarMarca: Método que ealiza el proceso de 
    * Activar o Inactivar Marcaes.
    */ 
    protected function _inactivarMarca() {

        $_objMarca = new \predial\DAO_Marca();
        $_objMarca->set_mar_Id($_POST['id']);
        $_objMarca->set_mar_Estado($_POST['estado']);

        if(!$_objMarca->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objMarca->getMysqlError();
        }else{
            $id = $_objMarca->get_mar_Id();
            //$_objlogs = new logs();
            //$_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,4);
            $this->_ok = 1;
            $this->_mensaje = "Marca Activada/Inactivada correctamente";
        }
        return $_objMarca->getArray();
    }
    
    /**
    * _consultarMarca: Método que ealiza el proceso de Consultar Marcaes.
    */ 
    private function _consultarMarca() {
       
        $_objMarca = new \predial\DAO_Marca();
        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objMarca->set_mar_Id($_POST['id']);
            }    
        }

        if(isset($_POST['estado'])){
            if (!empty($_POST['estado']) || $_POST['estado'] != NULL ) {
                $_objMarca->set_mar_Estado($_POST['estado']);
            }    
        }
        
        $_objMarca->habilita1ResultadoEnArray();
        $arrMarca = $_objMarca->consultar();
       
        if(is_array($arrMarca) && count($arrMarca)){
            $R = [];
            foreach($arrMarca as $obj){
                $R[] = $obj->getArray();
            }
            $this->_ok = 1;
            $this->_mensaje = "Marca listados con exito";
        }else{
            $R=$_objMarca;
            $this->_ok = 0;
            $this->_mensaje = "No existen Marca";            
        }
        return $R;
    }  
}

class MarcaException extends \Exception{}

    \predial\ControladorMarca::run();

