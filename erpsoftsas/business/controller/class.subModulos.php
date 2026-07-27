<?php
namespace erpsoftsas;
include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/DAO/DAO_SubModulos.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER.'/business/controller/class.logs.php';

class ControladorSubModulos extends \erpsoftsas\Cabecera {

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
                    $respuesta = $_obj->_agregarSubModulos();
                    break;
                case 2:
                    $respuesta = $_obj->_editarSubModulos();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarSubModulos();
                    break; 
                case 4:
                    $respuesta = $_obj->_inactivarSubModulos();
                    break;         
            }
            // $con->commit();
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
            
        } catch (\erpsoftsas\SubModulosException $e) {
            // $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    /**
    *** Realiza el proceso de Creación de SubModulos.
    **/
    protected function _agregarSubModulos() {
        
        $_objSubModulos = new \erpsoftsas\DAO_SubModulos();
        $_objSubModulos->set_nombre($_POST['nombre']);
        $_objSubModulos->set_nit($_POST['nit']);
        $_objSubModulos->set_telefono($_POST['telefono']);
        if(isset($_POST['telefono_fijo'])){
            if (!empty($_POST['telefono_fijo']) || $_POST['telefono_fijo'] != NULL ) {
                $_objSubModulos->set_telefono_fijo($_POST['telefono_fijo']);
            }    
        }
        $_objSubModulos->set_email($_POST['email']);
        $_objSubModulos->set_nombre_representante($_POST['nombre_representante']);
        $_objSubModulos->set_direccion($_POST['direccion']);
        $_objSubModulos->set_estado($_POST['estado']);

        //Validar si es igual el email a alguno en la BD.
        $nomSubModulos= $this->_listarSubModulos(0);
        $longitud = count($nomSubModulos);
        $nomduplicado=0;

        for($i=0; $i<$longitud; $i++){  
            if($nomSubModulos[$i]['email'] == $_objSubModulos->get_email()){
                $nomduplicado=1;
                break;
            }
            if($nomSubModulos[$i]['nit'] == $_objSubModulos->get_nit()){
                $nomduplicado=2;
                break;
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un Cliente con el mismo email';
            $return= false; 
        }else if($nomduplicado == 2){
            $this->_ok = 3;
            $this->_mensaje = 'Ya existe un Cliente con el mismo nit';
            $return= false;   
        }else{
            if(!$_objSubModulos->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objSubModulos->getMysqlError();
            }else{
                $id = $_objSubModulos->get_id();
                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($id,1,1,5);
                $this->_ok = 1;
                $this->_mensaje = "Datos ingresados correctamente";
            }
            $return= $_objSubModulos->guardar();
        }
        return $return;   
    }
       
    /**
    *** Realiza el proceso de Editar SubModulos.
    **/
    protected function _editarSubModulos() {

        $_objSubModulos = new \dextera\DAO_SubModulos();
        $_objSubModulos->set_id($_POST['id']);
        $_objSubModulos->set_nombre($_POST['nombre']);
        $_objSubModulos->set_nit($_POST['nit']);
        $_objSubModulos->set_telefono($_POST['telefono']);
        if(isset($_POST['telefono_fijo'])){
            if (!empty($_POST['telefono_fijo']) || $_POST['telefono_fijo'] != NULL ) {
                $_objSubModulos->set_telefono_fijo($_POST['telefono_fijo']);
            }    
        }
        $_objSubModulos->set_email($_POST['email']);
        $_objSubModulos->set_nombre_representante($_POST['nombre_representante']);
        $_objSubModulos->set_direccion($_POST['direccion']);

        //Validar si es igual el email a alguno en la BD.
        $nomSubModulos= $this->_listarSubModulos($_objSubModulos->get_id());
        $longitud = count($nomSubModulos);
        $nomduplicado=0;
        
        for($i=0; $i<$longitud; $i++){  
            if($nomSubModulos[$i]['email'] == $_objSubModulos->get_email()){
                $nomduplicado=1;
                break;
            }
            if($nomSubModulos[$i]['nit'] == $_objSubModulos->get_nit()){
                $nomduplicado=2;
                break;
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un Cliente con el mismo email';
            $return= false; 
        }else if($nomduplicado == 2){
            $this->_ok = 3;
            $this->_mensaje = 'Ya existe un Cliente con el mismo nit';
            $return= false;   
        }else{
            if(!$_objSubModulos->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objSubModulos->getMysqlError();
            }else{
                $id = $_objSubModulos->get_id();
                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($id,4,1,4);
                $this->_ok = 1;
                $this->_mensaje = "Datos ingresados correctamente";
            }
            $return= $_objSubModulos->guardar();
        }
        return $return;  
    }
    
    /**
    *** Realiza el proceso de Listar SubModulos exceptuando el cliente enviado por parametro.
    *** @param type $id_cliente
    **/ 
    private function _listarSubModulos ($id_cliente){
        
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT * FROM cli_cliente WHERE id <> $id_cliente ";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){                 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "SubModulos listado con Éxito";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existen SubModulos";
            $row=[];
        }
        return $row;       
    }    
    
    /**
    *** Realiza de proceso de Consultar SubModulos.
    **/ 
    private function _consultarSubModulos() {
       
        $_objSubModulos = new \erpsoftsas\DAO_SubModulos();
        if(isset($_POST['id_modulo'])){
            if (!empty($_POST['id_modulo']) || $_POST['id_modulo'] != NULL ) {
                $_objSubModulos->set_subMod_IdModulo($_POST['id_modulo']);
            }    
        }
        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objSubModulos->set_subMod_Id($_POST['id']);
            }    
        }
        
        $_objSubModulos->habilita1ResultadoEnArray();
        $arrSubModulos = $_objSubModulos->consultar();
       
        if(is_array($arrSubModulos) && count($arrSubModulos)){
            $R = [];
            foreach($arrSubModulos as $obj){
                $R[] = $obj->getArray();
            }
            $this->_ok = 1;
            $this->_mensaje = "SubModulos listados con Éxito";
        }else{
            $R=$arrSubModulos;
            $this->_ok = 0;
            $this->_mensaje = "No existen SubModulos";            
        }
        return $R;
    }
     
    /**
    *** Realiza de proceso de Activer o Inactivar SubModulos.
    **/ 
    protected function _inactivarSubModulos() {

        $_objSubModulos = new \dextera\DAO_SubModulos();
        $_objSubModulos->set_id($_POST['id']);
        $_objSubModulos->set_estado($_POST['estado']);

        if(!$_objSubModulos->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objSubModulos->getMysqlError();
        }else{
            $id = $_objSubModulos->get_id();
            //$_objlogs = new logs();
            //$_objlogs->_insertLogs($id,5,1,4);
            $this->_ok = 1;
            $this->_mensaje = "Cliente Activado/inactivado correctamente";
        }
        return $_objSubModulos->getArray();
    }       
}

class SubModulosException extends \Exception{}

    \erpsoftsas\ControladorSubModulos::run();

