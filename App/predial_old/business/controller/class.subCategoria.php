<?php
namespace predial;
include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_SubCategoria.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER .'/business/controller/class.logs.php';

class ControladorSubCategoria extends \predial\Cabecera {

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
                    $respuesta = $_obj->_agregarSubCategoria();
                    break;
                case 2:
                    $respuesta = $_obj->_editarSubCategoria();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarSubCategoria();
                    break; 
                case 4:
                    $respuesta = $_obj->_inactivarSubCategoria();
                    break; 
            }
            $con->commit();
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
            
        } catch (\predial\SubCategoriaException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    /**
    * _agregarSubCategoria: Método que realiza el proceso de Crear SubCategoriaes.
    */ 
    protected function _agregarSubCategoria() {
        
        $_objSubCategoria = new \predial\DAO_SubCategoria();
        $_objSubCategoria->set_subCate_Descripcion($_POST['nombre']);
        $_objSubCategoria->set_subCate_IdCategoria($_POST['idCategoria']);
        $_objSubCategoria->set_subCate_Estado(1);
        
        //Valida si es igual el nombre a alguno de la BD.
        $nomSubCategoria= $this->_listarSubCategoria(0);
        $longitud = count($nomSubCategoria);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomSubCategoria[$i]['subCate_Descripcion'] == $_objSubCategoria->get_subCate_Descripcion()){
               $nomduplicado=1;
                break;
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe una SubCategoria con el mismo nombre';
            $return= false; 
        }else {
            if(!$_objSubCategoria->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objSubCategoria->getMysqlError();
            }else{
                $id = $_objSubCategoria->get_subCate_Id();
                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,2);
                $this->_ok = 1;
                $this->_mensaje = "SubCategoria ingresados correctamente";
            }
            $return= $_objSubCategoria->guardar();
        }
        return $return;
    }
       
    /**
    * _editarSubCategoria: Método que realiza el proceso de Editar SubCategoriaes.
    */ 
    protected function _editarSubCategoria() {

        $_objSubCategoria = new \predial\DAO_SubCategoria();
        $_objSubCategoria->set_subCate_Id($_POST['id']);
        $_objSubCategoria->set_subCate_Descripcion($_POST['nombre']);
        $_objSubCategoria->set_subCate_IdCategoria($_POST['idCategoria']);
        

        //Valida si es igual el nombre a alguno de la BD.
        $nomSubCategoria= $this->_listarSubCategoria($_objSubCategoria->get_subCate_Id());
        $longitud = count($nomSubCategoria);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomSubCategoria[$i]['subCate_Descripcion'] == $_objSubCategoria->get_subCate_Descripcion()){
               $nomduplicado=1;
                break;
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un nombre de la SubCategoria con el mismo nombre';
            $return= false; 
        }else {
            if(!$_objSubCategoria->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objSubCategoria->getMysqlError();
            }else{
                $id = $_objSubCategoria->get_subCate_Id();
                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,3);
                $this->_ok = 1;
                $this->_mensaje = "SubCategoria editada correctamente";
            }
            $return= $_objSubCategoria->guardar();
        }
        return $return;
    }
    
    /**
    * _listarSubCategoriaes: Método que realiza el proceso 
    * de Listar roles, exeptuando el rol enviado por parametro.
    * @param type $id_rol: llave primaria de la tabla rol
    */  
    private function _listarSubCategoria($id_SubCategoria) {
       
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT * FROM inv_sub_categoria WHERE subCate_Id <> $id_SubCategoria";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "SubCategorias listados";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existen Usuarios";
            $row=[];
        }
        return $row;     
    }  
    
    /**
    * _inactivarSubCategoria: Método que ealiza el proceso de 
    * Activar o Inactivar SubCategoriaes.
    */ 
    protected function _inactivarSubCategoria() {

        $_objSubCategoria = new \predial\DAO_SubCategoria();
        $_objSubCategoria->set_subCate_Id($_POST['id']);
        $_objSubCategoria->set_subCate_Estado($_POST['estado']);

        if(!$_objSubCategoria->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objSubCategoria->getMysqlError();
        }else{
            $id = $_objSubCategoria->get_subCate_Id();
            $_objlogs = new logs();
                $_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,4);
            $this->_ok = 1;
            $this->_mensaje = "SubCategoria Activado/Inactivado correctamente";
        }
        return $_objSubCategoria->getArray();
    }
    
    /**
    * _consultarSubCategoria: Método que ealiza el proceso de Consultar SubCategoriaes.
    */ 
    private function _consultarSubCategoria() {
       
        $_objSubCategoria = new \predial\DAO_SubCategoria();
        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objSubCategoria->set_subCate_Id($_POST['id']);
            }    
        }

        if(isset($_POST['estado'])){
            if (!empty($_POST['estado']) || $_POST['estado'] != NULL ) {
                $_objSubCategoria->set_subCate_Estado($_POST['estado']);
            }    
        }

        if(isset($_POST['subCate_IdCategoria'])){
            if (!empty($_POST['subCate_IdCategoria']) || $_POST['subCate_IdCategoria'] != NULL ) {
                $_objSubCategoria->set_subCate_IdCategoria($_POST['subCate_IdCategoria']);
            }    
        }
        
        $_objSubCategoria->habilita1ResultadoEnArray();
        $arrSubCategoria = $_objSubCategoria->consultar();
       
        if(is_array($arrSubCategoria) && count($arrSubCategoria)){
            $R = [];
            foreach($arrSubCategoria as $obj){
                $R[] = $obj->getArray();
            }
            $this->_ok = 1;
            $this->_mensaje = "SubCategoria listados con exito";
        }else{
            $R=$_objSubCategoria;
            $this->_ok = 0;
            $this->_mensaje = "No existen SubCategoria";            
        }
        return $R;
    }  
}

class SubCategoriaException extends \Exception{}

    \predial\ControladorSubCategoria::run();

