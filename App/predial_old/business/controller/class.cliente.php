<?php
namespace predial;
include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_Cliente.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER .'/business/controller/class.logs.php';

class ControladorCliente extends \predial\Cabecera {

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
                    $respuesta = $_obj->_agregarCliente();
                    break;
                case 2:
                    $respuesta = $_obj->_editarCliente();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarCliente();
                    break; 
                 case 4:
                    $respuesta = $_obj->_inactivarCliente();
                    break;  
            }
            $con->commit();
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
            
        } catch (\predial\ClienteException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    /**
    * _agregarCliente: Método que realiza el proceso de Crear Clientes.
    */ 
    protected function _agregarCliente() {
        
        $_objCliente = new \predial\DAO_Cliente();
       
        $_objCliente->set_cli_IdTipoPersona($_POST['idTipoPersona']);
        $_objCliente->set_cli_Nombre($_POST['nombre']);
        $_objCliente->set_cli_RazonSocial($_POST['razon_social']);
        $_objCliente->set_cli_IdDepartamento($_POST['idDepartamento']);
        $_objCliente->set_cli_IdCiudad($_POST['idCiudad']);

        if(isset($_POST['identificacion'])){
            if (!empty($_POST['identificacion']) || $_POST['identificacion'] != NULL ) {
                $_objCliente->set_cli_Identificacion($_POST['identificacion']);
            }    
        }

        if(isset($_POST['direccion'])){
            if (!empty($_POST['direccion']) || $_POST['direccion'] != NULL ) {
                $_objCliente->set_cli_Direccion($_POST['direccion']);
            }    
        }
                
        if(isset($_POST['telefono'])){
            if (!empty($_POST['telefono']) || $_POST['telefono'] != NULL ) {
                $_objCliente->set_cli_Telefono($_POST['telefono']);
            }    
        }

        if(isset($_POST['correo'])){
            if (!empty($_POST['correo']) || $_POST['correo'] != NULL ) {
                $_objCliente->set_cli_Correo($_POST['correo']);
            }    
        }
        
        $_objCliente->set_cli_Estado(1);        
        
        //Valida si es igual el nombre a alguno de la BD.
        $nomCliente= $this->_listarClientes(0);
        $longitud = count($nomCliente);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if(($nomCliente[$i]['cli_Identificacion'] == $_objCliente->get_cli_Identificacion())and($nomCliente[$i]['cli_Identificacion'] != NULL)){
               $nomduplicado=1;
                break;
            }

            if(($nomCliente[$i]['cli_Nombre'] == $_objCliente->get_cli_Nombre())and($nomCliente[$i]['cli_Nombre'] != NULL)){
                $nomduplicado=2;
                 break;
             }

            if(($nomCliente[$i]['cli_RazonSocial'] == $_objCliente->get_cli_RazonSocial())and($nomCliente[$i]['cli_RazonSocial'] != NULL)){
                $nomduplicado=3;
                 break;
             }

             if(($nomCliente[$i]['cli_Correo'] == $_objCliente->get_cli_Correo())and($nomCliente[$i]['cli_Correo'] != NULL)){
                $nomduplicado=4;
                 break;
             }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un cliente con la misma identificación.';
            $return= false; 
        }else if($nomduplicado == 2){
            $this->_ok = 3;
            $this->_mensaje = 'Ya existe un cliente con el mismo Nombre.';
            $return= false; 
        }else if($nomduplicado == 3){
            $this->_ok = 4;
            $this->_mensaje = 'Ya existe un cliente con la misma Razon Social.';
            $return= false; 
        }else if($nomduplicado == 4){
            $this->_ok = 5;
            $this->_mensaje = 'Ya existe un cliente con un mismo Correo.';
            $return= false; 
        }else {
            if(!$_objCliente->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objCliente->getMysqlError();
            }else{
                $id = $_objCliente->get_cli_Id();
                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,2);
                $this->_ok = 1;
                $this->_mensaje = "Cliente ingresado correctamente";
            }
            $return= $_objCliente->guardar();
        }
        return $return;
    }
       
    /**
    * _editarCliente: Método que realiza el proceso de Editar Roles.
    */ 
    protected function _editarCliente() {

        $_objCliente = new \predial\DAO_Cliente();
        
        $_objCliente->set_cli_Id($_POST['idcli']);
        $_objCliente->set_cli_IdTipoPersona($_POST['idTipoPersona']);
        $_objCliente->set_cli_Nombre($_POST['nombre']);
        $_objCliente->set_cli_RazonSocial($_POST['razon_social']);
        $_objCliente->set_cli_IdDepartamento($_POST['idDepartamento']);
        $_objCliente->set_cli_IdCiudad($_POST['idCiudad']);

        if(isset($_POST['identificacion'])){
            if (!empty($_POST['identificacion']) || $_POST['identificacion'] != NULL ) {
                $_objCliente->set_cli_Identificacion($_POST['identificacion']);
            }    
        }

        if(isset($_POST['direccion'])){
            if (!empty($_POST['direccion']) || $_POST['direccion'] != NULL ) {
                $_objCliente->set_cli_Direccion($_POST['direccion']);
            }    
        }
                
        if(isset($_POST['telefono'])){
            if (!empty($_POST['telefono']) || $_POST['telefono'] != NULL ) {
                $_objCliente->set_cli_Telefono($_POST['telefono']);
            }    
        }

        if(isset($_POST['correo'])){
            if (!empty($_POST['correo']) || $_POST['correo'] != NULL ) {
                $_objCliente->set_cli_Correo($_POST['correo']);
            }    
        }
        
        //$_objCliente->set_cli_Estado($_POST['estado']);  

        //Valida si es igual el nombre a alguno de la BD.
        $nomCliente= $this->_listarClientes($_objCliente->get_cli_Id());
        $longitud = count($nomCliente);
        $nomduplicado=0;

        for($i=0; $i<$longitud; $i++){  
            if(($nomCliente[$i]['cli_Identificacion'] == $_objCliente->get_cli_Identificacion())and($nomCliente[$i]['cli_Identificacion'] != NULL)){
                $nomduplicado=1;
                 break;
             }
 
             if(($nomCliente[$i]['cli_Nombre'] == $_objCliente->get_cli_Nombre())and($nomCliente[$i]['cli_Nombre'] != NULL)){
                 $nomduplicado=2;
                  break;
              }
 
              if(($nomCliente[$i]['cli_RazonSocial'] == $_objCliente->get_cli_RazonSocial())and($nomCliente[$i]['cli_RazonSocial'] != NULL)){
                 $nomduplicado=3;
                  break;
              }
 
              if(($nomCliente[$i]['cli_Correo'] == $_objCliente->get_cli_Correo())and($nomCliente[$i]['cli_Correo'] != NULL)){
                 $nomduplicado=4;
                  break;
              }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un cliente con la misma identificación.';
            $return= false; 
        }else if($nomduplicado == 2){
            $this->_ok = 3;
            $this->_mensaje = 'Ya existe un cliente con el mismo Nombre.';
            $return= false; 
        }else if($nomduplicado == 3){
            $this->_ok = 4;
            $this->_mensaje = 'Ya existe un cliente con la misma Razon Social.';
            $return= false; 
        }else if($nomduplicado == 4){
            $this->_ok = 5;
            $this->_mensaje = 'Ya existe un cliente con un mismo Correo.';
            $return= false; 
        }else {
            if(!$_objCliente->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objCliente->getMysqlError();
            }else{
                $id = $_objCliente->get_cli_Id();
                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,3);
                $this->_ok = 1;
                $this->_mensaje = "Cliente editado correctamente";
            }
            $return= $_objCliente->guardar();
        }
        return $return;
    }
    
    /**
    * _listarClientes: Método que realiza el proceso 
    * de Listar clientes, exeptuando el cliente enviado por parametro.
    * @param type $id_cli: llave primaria de la tabla cliente
    */  
    private function _listarClientes($id_cli) {
       
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT * FROM fac_cliente WHERE cli_Id <> $id_cli ";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) > 0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "Clientes listados";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existen Clientes";
            $row=[];
        }
        return $row;     
    }  
    
    
    /**
    * _consultarCliente: Método que ealiza el proceso de Consultar Clientes.
    */ 
    private function _consultarCliente() {
       
        $_objCliente = new \predial\DAO_Cliente();
        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objCliente->set_cli_Id($_POST['id']);
            }    
        }

        if(isset($_POST['identificacion'])){
            if (!empty($_POST['identificacion']) || $_POST['identificacion'] != NULL ) {
                $_objCliente->set_cli_Identificacion($_POST['identificacion']);
            }    
        }
        
        $_objCliente->habilita1ResultadoEnArray();
        $arrRol = $_objCliente->consultar();
       
        if(is_array($arrRol) && count($arrRol)){
            $R = [];
            foreach($arrRol as $obj){
                $R[] = $obj->getArray();
            }
            $this->_ok = 1;
            $this->_mensaje = "Clientes listados con exito";
        }else{
            $R=$_objCliente;
            $this->_ok = 0;
            $this->_mensaje = "No existen Clintes";            
        }
        return $R;
    }  

    /**
    *** Realiza el proceso de Activar o Inactivar Cliente.
    **/     
    protected function _inactivarCliente() {

        $_objCliente = new \predial\DAO_Cliente();
        $_objCliente->set_cli_Id($_POST['id']);
        $_objCliente->set_cli_Estado($_POST['estado']);
        
        if(!$_objCliente->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objCliente->getMysqlError();
        }else{
            $id = $_objCliente->get_cli_Id();
//            $_objlogs = new logs();
//            $_objlogs->_insertLogs($id,5,$_SESSION['id_Producto'],2);
            $this->_ok = 1;
            $this->_mensaje = "Cliente Activado/inactivado correctamente";
        }
        return $_objCliente->getArray();
    }
}

class ClienteException extends \Exception{}

    \predial\ControladorCliente::run();

