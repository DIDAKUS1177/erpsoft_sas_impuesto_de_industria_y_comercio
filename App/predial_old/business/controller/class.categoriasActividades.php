<?php
namespace predial;
include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_CategoriasActividades.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER .'/business/controller/class.logs.php';

class ControladorCategoriasActividades extends \predial\Cabecera {

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
                    $respuesta = $_obj->_agregarCategoriasActividades();
                    break;
                case 2:
                    $respuesta = $_obj->_editarCategoriasActividades();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarCategoriasActividades();
                    break; 
                case 4:
                    $respuesta = $_obj->_inactivarCategoriasActividades();
                    break; 
            }
            $con->commit();
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
            
        } catch (\predial\CategoriasActividadesException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    /**
    * _agregarCategoriasActividades: Método que realiza el proceso de Crear CategoriasActividadeses.
    */ 
    protected function _agregarCategoriasActividades() {
        
        $_objCategoriasActividades = new \predial\DAO_CategoriasActividades();
        $_objCategoriasActividades->set_caa_Nombre($_POST['nombre']);
        $_objCategoriasActividades->set_caa_Estado(1);
        
        //Valida si es igual el nombre a alguno de la BD.
        $nomCategoriasActividades= $this->_listarCategoriasActividades(0);
        $longitud = count($nomCategoriasActividades);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomCategoriasActividades[$i]['caa_Nombre'] == $_objCategoriasActividades->get_caa_Nombre()){
               $nomduplicado=1;
                break;
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe una categorias Actividades con el mismo nombre';
            $return= false; 
        }else {
            if(!$_objCategoriasActividades->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objCategoriasActividades->getMysqlError();
            }else{
                $id = $_objCategoriasActividades->get_caa_Id();
                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,2);
                $this->_ok = 1;
                $this->_mensaje = "CategoriasActividades ingresada correctamente";
            }
            $return= $_objCategoriasActividades->guardar();
        }
        return $return;
    }
       
    /**
    * _editarCategoriasActividades: Método que realiza el proceso de Editar CategoriasActividadeses.
    */ 
    protected function _editarCategoriasActividades() {

        $_objCategoriasActividades = new \predial\DAO_CategoriasActividades();
        $_objCategoriasActividades->set_caa_Id($_POST['id']);
        $_objCategoriasActividades->set_caa_Nombre($_POST['nombre']);

        //Valida si es igual el nombre a alguno de la BD.
        $nomCategoriasActividades= $this->_listarCategoriasActividades($_objCategoriasActividades->get_caa_Id());
        $longitud = count($nomCategoriasActividades);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomCategoriasActividades[$i]['caa_Nombre'] == $_objCategoriasActividades->get_caa_Nombre()){
               $nomduplicado=1;
                break;
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un nombre de la categoriasActividades con el mismo nombre';
            $return= false; 
        }else {
            if(!$_objCategoriasActividades->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objCategoriasActividades->getMysqlError();
            }else{
                $id = $_objCategoriasActividades->get_caa_Id();
                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,3);
                $this->_ok = 1;
                $this->_mensaje = "CategoriasActividades editada correctamente";
            }
            $return= $_objCategoriasActividades->guardar();
        }
        return $return;
    }
    
    /**
    * _listarCategoriasActividadeses: Método que realiza el proceso 
    * de Listar roles, exeptuando el rol enviado por parametro.
    * @param type $id_rol: llave primaria de la tabla rol
    */  
    private function _listarCategoriasActividades($id_categoriasActividades) {
       
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT * FROM eve_categoriasactividades WHERE caa_Id <> $id_categoriasActividades";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "CategoriasActividadess listados";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existen CategoriasActividadess";
            $row=[];
        }
        return $row;     
    }  
    
    /**
    * _inactivarCategoriasActividades: Método que ealiza el proceso de 
    * Activar o Inactivar CategoriasActividadeses.
    */ 
    protected function _inactivarCategoriasActividades() {

        $_objCategoriasActividades = new \predial\DAO_CategoriasActividades();
        $_objCategoriasActividades->set_caa_Id($_POST['id']);
        $_objCategoriasActividades->set_caa_Estado($_POST['estado']);

        if(!$_objCategoriasActividades->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objCategoriasActividades->getMysqlError();
        }else{
            $id = $_objCategoriasActividades->get_caa_Id();
            //$_objlogs = new logs();
            //$_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,4);
            $this->_ok = 1;
            $this->_mensaje = "CategoriasActividades Activada/Inactivada correctamente";
        }
        return $_objCategoriasActividades->getArray();
    }
    
    /**
    * _consultarCategoriasActividades: Método que ealiza el proceso de Consultar CategoriasActividadeses.
    */ 
    private function _consultarCategoriasActividades() {
       
        $_objCategoriasActividades = new \predial\DAO_CategoriasActividades();
        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objCategoriasActividades->set_caa_Id($_POST['id']);
            }    
        }

        if(isset($_POST['estado'])){
            if (!empty($_POST['estado']) || $_POST['estado'] != NULL ) {
                $_objCategoriasActividades->set_caa_Estado($_POST['estado']);
            }    
        }
        
        $_objCategoriasActividades->habilita1ResultadoEnArray();
        $arrCategoriasActividades = $_objCategoriasActividades->consultar();
       
        if(is_array($arrCategoriasActividades) && count($arrCategoriasActividades)){
            $R = [];
            foreach($arrCategoriasActividades as $obj){
                $R[] = $obj->getArray();
            }
            $this->_ok = 1;
            $this->_mensaje = "CategoriasActividades listados con exito";
        }else{
            $R=$_objCategoriasActividades;
            $this->_ok = 0;
            $this->_mensaje = "No existen CategoriasActividades";            
        }
        return $R;
    }  
}

class CategoriasActividadesException extends \Exception{}

    \predial\ControladorCategoriasActividades::run();

