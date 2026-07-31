<?php
namespace predial;
include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_Proveedores.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER .'/business/controller/class.logs.php';


class ControladorProveedores extends \predial\Cabecera {

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
                    $respuesta = $_obj->_agregarProveedores();
                    break;
                case 2:
                    $respuesta = $_obj->_editarProveedores();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarProveedores();
                    break; 
                case 4:
                    $respuesta = $_obj->_inactivarProveedores();
                    break; 
            }
            $con->commit();
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
            
        } catch (\predial\ProveedoresException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    /**
    * _agregarProveedores: Método que realiza el proceso de Crear Proveedores.
    */ 
    protected function _agregarProveedores() {
        
        $_objProveedores = new \predial\DAO_Proveedores();
        $_objProveedores->set_prov_Nombre($_POST['nombre']);
        $_objProveedores->set_prov_RazonSocial($_POST['razonSocial']);
        $_objProveedores->set_prov_Nit($_POST['nit']);
        $_objProveedores->set_prov_Direccion($_POST['direccion']); 
        $_objProveedores->set_prov_IdDepartamento($_POST['idDepartamento']); 
        $_objProveedores->set_prov_IdCiudad($_POST['idCiudad']); 
        $_objProveedores->set_prov_Telefono($_POST['telefono']); 
        $_objProveedores->set_prov_Email($_POST['email']); 
        $_objProveedores->set_prov_IdTipoPersona($_POST['idTipoPersona']); 
        $_objProveedores->set_prov_Estado(1);
        
        //Valida si es igual el nombre a alguno de la BD.
        $nomProveedores= $this->_listarProveedores(0);
        $longitud = count($nomProveedores);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomProveedores[$i]['prov_RazonSocial'] == $_objProveedores->get_prov_RazonSocial()){
               $nomduplicado=1;
                break;
            }

            if($nomProveedores[$i]['prov_Nit'] == $_objProveedores->get_prov_Nit()){
                $nomduplicado=2;
                 break;
             }

             if($nomProveedores[$i]['prov_Nombre'] == $_objProveedores->get_prov_Nombre()){
                $nomduplicado=3;
                 break;
             }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un Proveedor con la misma Razon Social';
            $return= false; 
        }else if($nomduplicado == 2){
            $this->_ok = 3;
            $this->_mensaje = 'Ya existe un Proveedor con el mismo NIT';
            $return= false;   
        }else if($nomduplicado == 3){
            $this->_ok = 4;
            $this->_mensaje = 'Ya existe un Proveedor con el mismo Nombre';
            $return= false;   
        }else {
            if(!$_objProveedores->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objProveedores->getMysqlError();
            }else{
                $id = $_objProveedores->get_prov_Id();
                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,2);
                $this->_ok = 1;
                $this->_mensaje = "Proveedores ingresados correctamente";
            }
            $return= $_objProveedores->guardar();
        }
        return $return;
    }
       
    /**
    * _editarProveedores: Método que realiza el proceso de Editar Proveedores.
    */ 
    protected function _editarProveedores() {

        $_objProveedores = new \predial\DAO_Proveedores();
        $_objProveedores->set_prov_Id($_POST['idpro']);
        $_objProveedores->set_prov_Nombre($_POST['nombre']);
        $_objProveedores->set_prov_RazonSocial($_POST['razonSocial']);
        $_objProveedores->set_prov_Nit($_POST['nit']);
        $_objProveedores->set_prov_Direccion($_POST['direccion']); 
        $_objProveedores->set_prov_IdDepartamento($_POST['idDepartamento']); 
        $_objProveedores->set_prov_IdCiudad($_POST['idCiudad']); 
        $_objProveedores->set_prov_Telefono($_POST['telefono']); 
        $_objProveedores->set_prov_Email($_POST['email']); 
        $_objProveedores->set_prov_IdTipoPersona($_POST['idTipoPersona']);  

        //Valida si es igual el nombre a alguno de la BD.
        $nomProveedores= $this->_listarProveedores($_objProveedores->get_prov_Id());
        $longitud = count($nomProveedores);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomProveedores[$i]['prov_RazonSocial'] == $_objProveedores->get_prov_RazonSocial()){
                $nomduplicado=1;
                 break;
            }
 
             if($nomProveedores[$i]['prov_Nit'] == $_objProveedores->get_prov_Nit()){
                 $nomduplicado=2;
                  break;
            }
 
              if($nomProveedores[$i]['prov_Nombre'] == $_objProveedores->get_prov_Nombre()){
                 $nomduplicado=3;
                  break;
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un Proveedor con la misma Razon Social';
            $return= false; 
        }else if($nomduplicado == 2){
            $this->_ok = 3;
            $this->_mensaje = 'Ya existe un Proveedor con el mismo NIT';
            $return= false;   
        }else if($nomduplicado == 3){
            $this->_ok = 4;
            $this->_mensaje = 'Ya existe un Proveedor con el mismo Nombre';
            $return= false;   
        }else {
            if(!$_objProveedores->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objProveedores->getMysqlError();
            }else{
                $id = $_objProveedores->get_prov_Id();
                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,3);
                $this->_ok = 1;
                $this->_mensaje = "Proveedores editado correctamente";
            }
            $return= $_objProveedores->guardar();
        }
        return $return;
    }
    
    /**
    * _listarProveedoreses: Método que realiza el proceso 
    * de Listar Proveedores, exeptuando el Proveedores enviado por parametro.
    * @param type $id_Proveedores: llave primaria de la tabla Proveedores
    */  
    private function _listarProveedores($id_Proveedores) {
       
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT * FROM inv_proveedores WHERE prov_Id <> $id_Proveedores ";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "Proveedores listados";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existen Proveedores";
            $row=[];
        }
        return $row;     
    }  
    
    /**
    * _inactivarProveedores: Método que realiza el proceso de 
    * Activar o Inactivar Proveedores.
    */ 
    protected function _inactivarProveedores() {

        $_objProveedores = new \predial\DAO_Proveedores();
        $_objProveedores->set_prov_Id($_POST['id']);
        $_objProveedores->set_prov_Estado($_POST['estado']);

        if(!$_objProveedores->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objProveedores->getMysqlError();
        }else{
            $id = $_objProveedores->get_prov_Id();
            //$_objlogs = new logs();
            //$_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,4);
            $this->_ok = 1;
            $this->_mensaje = "Proveedores Activado/Inactivado correctamente";
        }
        return $_objProveedores->getArray();
    }
    
    /**
    * _consultarProveedores: Método que ealiza el proceso de Consultar Proveedores.
    */ 
    private function _consultarProveedores() {
       
        $_objProveedores = new \predial\DAO_Proveedores();
        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objProveedores->set_prov_Id($_POST['id']);
            }    
        }

        if(isset($_POST['estado'])){
            if (!empty($_POST['estado']) || $_POST['estado'] != NULL ) {
                $_objProveedores->set_prov_Estado($_POST['estado']);
            }    
        }
        
        $_objProveedores->habilita1ResultadoEnArray();
        $arrProveedores = $_objProveedores->consultar();
       
        if(is_array($arrProveedores) && count($arrProveedores)){
            $R = [];
            foreach($arrProveedores as $obj){
                $R[] = $obj->getArray();
            }
            $this->_ok = 1;
            $this->_mensaje = "Proveedores listados con exito";
        }else{
            $R=$_objProveedores;
            $this->_ok = 0;
            $this->_mensaje = "No existen Proveedores";            
        }
        return $R;
    }  
}

class ProveedoresException extends \Exception{}

    \predial\ControladorProveedores::run();

