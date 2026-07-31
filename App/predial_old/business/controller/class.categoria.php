<?php
namespace predial;
include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_Categoria.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER .'/business/controller/class.logs.php';

class ControladorCategoria extends \predial\Cabecera {

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
                    $respuesta = $_obj->_agregarCategoria();
                    break;
                case 2:
                    $respuesta = $_obj->_editarCategoria();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarCategoria();
                    break; 
                case 4:
                    $respuesta = $_obj->_inactivarCategoria();
                    break; 
            }
            $con->commit();
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
            
        } catch (\predial\CategoriaException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    /**
    * _agregarCategoria: Método que realiza el proceso de Crear Categoriaes.
    */ 
    protected function _agregarCategoria() {
        
        $_objCategoria = new \predial\DAO_Categoria();
        $_objCategoria->set_cate_Descripcion($_POST['nombre']);
        $_objCategoria->set_cate_IdTipo($_POST['idTipo']);
        $_objCategoria->set_cate_Estado(1);
        
        //Valida si es igual el nombre a alguno de la BD.
        $nomCategoria= $this->_listarCategoria(0);
        $longitud = count($nomCategoria);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomCategoria[$i]['cate_Descripcion'] == $_objCategoria->get_cate_Descripcion()){
               $nomduplicado=1;
                break;
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe una categoria con el mismo nombre';
            $return= false; 
        }else {
            if(!$_objCategoria->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objCategoria->getMysqlError();
            }else{
                $id = $_objCategoria->get_cate_Id();
                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,2);
                $this->_ok = 1;
                $this->_mensaje = "Categoria ingresados correctamente";
            }
            $return= $_objCategoria->guardar();
        }
        return $return;
    }
       
    /**
    * _editarCategoria: Método que realiza el proceso de Editar Categoriaes.
    */ 
    protected function _editarCategoria() {

        $_objCategoria = new \predial\DAO_Categoria();
        $_objCategoria->set_cate_Id($_POST['id']);
        $_objCategoria->set_cate_Descripcion($_POST['nombre']);
        $_objCategoria->set_cate_IdTipo($_POST['idTipo']);
        

        //Valida si es igual el nombre a alguno de la BD.
        $nomCategoria= $this->_listarCategoria($_objCategoria->get_cate_Id());
        $longitud = count($nomCategoria);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomCategoria[$i]['cate_Descripcion'] == $_objCategoria->get_cate_Descripcion()){
               $nomduplicado=1;
                break;
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un nombre de la categoria con el mismo nombre';
            $return= false; 
        }else {
            if(!$_objCategoria->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objCategoria->getMysqlError();
            }else{
                $id = $_objCategoria->get_cate_Id();
                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,3);
                $this->_ok = 1;
                $this->_mensaje = "Categoria editada correctamente";
            }
            $return= $_objCategoria->guardar();
        }
        return $return;
    }
    
    /**
    * _listarCategoriaes: Método que realiza el proceso 
    * de Listar roles, exeptuando el rol enviado por parametro.
    * @param type $id_rol: llave primaria de la tabla rol
    */  
    private function _listarCategoria($id_categoria) {
       
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT * FROM inv_categoria WHERE cate_Id <> $id_categoria";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "Categorias listados";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existen Usuarios";
            $row=[];
        }
        return $row;     
    }  
    
    /**
    * _inactivarCategoria: Método que ealiza el proceso de 
    * Activar o Inactivar Categoriaes.
    */ 
    protected function _inactivarCategoria() {

        $_objCategoria = new \predial\DAO_Categoria();
        $_objCategoria->set_cate_Id($_POST['id']);
        $_objCategoria->set_cate_Estado($_POST['estado']);

        if(!$_objCategoria->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objCategoria->getMysqlError();
        }else{
            $id = $_objCategoria->get_cate_Id();
            $_objlogs = new logs();
                $_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,4);
            $this->_ok = 1;
            $this->_mensaje = "Categoria Activado/Inactivado correctamente";
        }
        return $_objCategoria->getArray();
    }
    
    /**
    * _consultarCategoria: Método que ealiza el proceso de Consultar Categoriaes.
    */ 
    private function _consultarCategoria() {
       
        $_objCategoria = new \predial\DAO_Categoria();
        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objCategoria->set_cate_Id($_POST['id']);
            }    
        }

        if(isset($_POST['estado'])){
            if (!empty($_POST['estado']) || $_POST['estado'] != NULL ) {
                $_objCategoria->set_cate_Estado($_POST['estado']);
            }    
        }
        
        $_objCategoria->habilita1ResultadoEnArray();
        $arrCategoria = $_objCategoria->consultar();
       
        if(is_array($arrCategoria) && count($arrCategoria)){
            $R = [];
            foreach($arrCategoria as $obj){
                $R[] = $obj->getArray();
            }
            $this->_ok = 1;
            $this->_mensaje = "Categoria listados con exito";
        }else{
            $R=$_objCategoria;
            $this->_ok = 0;
            $this->_mensaje = "No existen Categoria";            
        }
        return $R;
    }  
}

class CategoriaException extends \Exception{}

    \predial\ControladorCategoria::run();

