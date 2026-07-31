<?php
namespace predial;
include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_Empresa.php';
include_once SERVER . '/business/DAO/DAO_SedesEmpresa.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER.'/business/controller/class.logs.php';

class ControladorEmpresas extends \predial\Cabecera {

    private $_funcion;
    private $_ok;
    private $_mensaje;   
    private $_id;   
        
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
                    $respuesta = $_obj->_agregarEmpresa();
                    break;
                case 2:
                    $respuesta = $_obj->_editarEmpresa();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarEmpresas();
                    break; 
                case 4:
                    $respuesta = $_obj->_inactivarEmpresas();
                    break; 
                case 5:
                    $respuesta = $_obj->_ConsultarSedesEmpresas();
                    break;     
                case 6:
                    $respuesta = $_obj->_editarSedeEmpresa();     
                    break;          
            }
            $con->commit();
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta, "id" => $_obj->_id));
            
        } catch (\predial\EmpresasException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "", "id" => $_obj->_id);
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    /**
    *** Realiza el proceso de Crear Empresas.
    **/  
    protected function _agregarEmpresa() {
        
        $_objEmpresa = new \predial\DAO_Empresa();
        $_objEmpresa->set_emp_Nombre($_POST['Nombre']);
        $_objEmpresa->set_emp_NombreComercial($_POST['NombreComercial']);
        $_objEmpresa->set_emp_Nit($_POST['Nit']);
        $_objEmpresa->set_emp_IdDepartamento($_POST['IdDepartamento']);
        $_objEmpresa->set_emp_IdMunicipio($_POST['IdMunicipio']);
        $_objEmpresa->set_emp_Email($_POST['Email']);
        $_objEmpresa->set_emp_SitioWeb($_POST['SitioWeb']);
        $_objEmpresa->set_emp_TipoImpresora($_POST['TipoImpresora']);
        $_objEmpresa->set_emp_TipoPantalla($_POST['TipoPantalla']);
        $_objEmpresa->set_emp_TextoFactura($_POST['TextoFactura']);
        $_objEmpresa->set_emp_Estado(1);

        //Valida si es igual el codigo alguno de la BD.
        $nomUsurio= $this->_listarEmpresas(0);
        $longitud = count($nomUsurio);
        $nomduplicado=0;

        for($i=0; $i<$longitud; $i++){  
            if($nomUsurio[$i]['emp_Nit'] == $_objEmpresa->get_emp_Nit()){
               $nomduplicado=1;
                break;
            }            
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un empresa con el mismo NIT';
            $return= false; 
        }else{
            if(!$_objEmpresa->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objEmpresa->getMysqlError();
            }else{
                $id = $_objEmpresa->get_emp_Id();

                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($id,3,$_SESSION['id_Empresa'],3);

                $this->_ok = 1;
                $this->_id = $id ;
                $this->_mensaje = "Datos ingresados correctamente";
            }
            $return= $_objEmpresa->guardar();
        }
        return $return;
    }
    
    /**
    *** Realiza el proceso de Editar Empresas.
    **/  
    protected function _editarEmpresa() {
        
        $_objEmpresa = new \predial\DAO_Empresa();

        $_objEmpresa->set_emp_Id($_POST['id']);
        $_objEmpresa->set_emp_Nombre($_POST['Nombre']);
        $_objEmpresa->set_emp_NombreComercial($_POST['NombreComercial']);
        $_objEmpresa->set_emp_Nit($_POST['Nit']);
        $_objEmpresa->set_emp_IdDepartamento($_POST['IdDepartamento']);
        $_objEmpresa->set_emp_IdMunicipio($_POST['IdMunicipio']);
        $_objEmpresa->set_emp_Email($_POST['Email']);
        $_objEmpresa->set_emp_SitioWeb($_POST['SitioWeb']);
        $_objEmpresa->set_emp_TipoImpresora($_POST['TipoImpresora']);
        $_objEmpresa->set_emp_TipoPantalla($_POST['TipoPantalla']);
        $_objEmpresa->set_emp_TextoFactura($_POST['TextoFactura']);

        if(isset($_POST['urlSoporteLogo'])){
            if (!empty($_POST['urlSoporteLogo']) || $_POST['urlSoporteLogo'] != NULL ) {
                $_objEmpresa->set_emp_UrlSoporteLogo('images/'.$_POST['urlSoporteLogo']);
            }    
        }
        
        //Valida si es igual el email y/o identificación a alguno de la BD.
        $nomUsurio= $this->_listarEmpresas($_objEmpresa->get_emp_Id());
        $longitud = count($nomUsurio);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomUsurio[$i]['emp_Nit'] == $_objEmpresa->get_emp_Nit()){
               $nomduplicado=1;
                break;
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un empresa con el mismo NIT';
            $return= false; 
        }else{
            if(!$_objEmpresa->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objEmpresa->getMysqlError();
            }else{
                $id = $_objEmpresa->get_emp_Id();
                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($id,4,$_SESSION['id_Usuario'],3);
                $this->_ok = 1;
                $this->_id = $_POST['id'] ;
                $this->_mensaje = "Datos ingresados correctamente";
            }
            $return= $_objEmpresa->guardar();
        }
        return $return;
    }

    /**
    *** Realiza el proceso de Listar Empresas, exeptuando el Empresa enviado por parametro.
    *** @param type $id_Empresa
    **/  
    private function _listarEmpresas($id_Empresa) {
       
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT * FROM conf_empresa WHERE emp_Id <> $id_Empresa ";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_id = 0;
            $this->_mensaje = "Empresas listados";
        }else{
            $this->_ok = 0;
            $this->_id = 0;
            $this->_mensaje = "No existen Empresas";
            $row=[];
        }
        return $row;     
    }  

    /**
    *** Realiza el proceso de Listar Sedes, exeptuando la Sede enviada por parametro.
    *** @param type $id_SedeEmpresa
    **/  
    private function _listarSedesEmpresas($id_SedeEmpresa) {
       
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT * FROM conf_sedes_empresa WHERE seem_Id <> $id_SedeEmpresa";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_id = 0;
            $this->_mensaje = "Sede listadas";
        }else{
            $this->_ok = 0;
            $this->_id = 0;
            $this->_mensaje = "No existen Sedes";
            $row=[];
        }
        return $row;     
    }  
    
    
    /**
    *** Realiza el proceso de Consultar Empresas.
    **/  
    private function _consultarEmpresas() {
       
        $_objUsu = new \predial\DAO_Empresa();
        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objUsu->set_emp_Id($_POST['id']);
            }    
        }

        if(isset($_POST['estado'])){
            if (!empty($_POST['estado']) || $_POST['estado'] != NULL ) {
                $_objUsu->set_emp_Estado($_POST['estado']);
            }    
        }
        
        $_objUsu->habilita1ResultadoEnArray();
        $arrEmpresas = $_objUsu->consultar();
       
        if(is_array($arrEmpresas) && count($arrEmpresas)){
            $R = [];
            foreach($arrEmpresas as $obj){
                $R[] = $obj->getArray();
            }    
            $this->_ok = 1;
            $this->_id = 0;
            $this->_mensaje = "Empresas listados con exito"; 
        }else{
            $R=$_objUsu;
            $this->_ok = 0;
            $this->_id = 0;
            $this->_mensaje = "No existen Empresas";            
        }       
        return $R;
    }
    
    /**
    *** Realiza el proceso de Activar o Inactivar Empresas.
    **/  
    protected function _inactivarEmpresas() {

        $_objEmpresa = new \predial\DAO_Empresa();
        $_objEmpresa->set_emp_Id($_POST['id']);
        $_objEmpresa->set_emp_Estado($_POST['estado']);
        
        if(!$_objEmpresa->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objEmpresa->getMysqlError();
        }else{
            $id = $_objEmpresa->get_emp_Id();
//          $_objlogs = new logs();
//          $_objlogs->_insertLogs($id,5,$_SESSION['id_Empresa'],2);
            $this->_ok = 1;
            $this->_mensaje = "Empresa Activado/inactivado correctamente";
        }
        return $_objEmpresa->getArray();
    }

    /**
    *** Realiza el proceso de consultar las Sedes
    **/
    private function _ConsultarSedesEmpresas(){
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        
        if(isset($_POST['id'])){

            $id_sede = $_POST['id'];
            $query = "SELECT * FROM conf_sedes_empresa where seem_Id = $id_sede  ";
        }else{
            $query = "SELECT * FROM conf_sedes_empresa";
        }
        
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "Sedes listadas";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existen Sedes";
            $row = NULL;
        }
        return $row;  
    }

    /**
    *** Realiza el proceso de Editar Sede Empresas.
    **/  
    protected function _editarSedeEmpresa() {
        
        $_objEmpresa = new \predial\DAO_SedesEmpresa();

        $_objEmpresa->set_seem_Id($_POST['id']);
        $_objEmpresa->set_seem_IdEmpresa($_POST['IdEmpresa']);
        $_objEmpresa->set_seem_IdBodega($_POST['IdBodega']);
        $_objEmpresa->set_seem_Nombre($_POST['Nombre']);
        $_objEmpresa->set_seem_Telefono($_POST['Telefono']);
        $_objEmpresa->set_seem_Direccion($_POST['Direccion']);
        $_objEmpresa->set_seem_IdDepartamento($_POST['IdDepartamento']);
        $_objEmpresa->set_seem_IdMunicipio($_POST['IdMunicipio']);
        $_objEmpresa->set_seem_Email($_POST['Email']);
        
        //Valida si es igual el email y/o identificación a alguno de la BD.
        $nomUsurio= $this->_listarSedesEmpresas($_objEmpresa->get_seem_Id());
        $longitud = count($nomUsurio);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomUsurio[$i]['seem_Nombre'] == $_objEmpresa->get_seem_Nombre()){
               $nomduplicado=1;
                break;
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe una Sede con el mismo Nombre';
            $return= false; 
        }else{
            if(!$_objEmpresa->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objEmpresa->getMysqlError();
            }else{
                $id = $_objEmpresa->get_seem_Id();
                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($id,4,$_SESSION['id_Usuario'],3);
                $this->_ok = 1;
                $this->_id = $_POST['id'] ;
                $this->_mensaje = "Datos ingresados correctamente";
            }
            $return= $_objEmpresa->guardar();
        }
        return $return;
    }

}

class EmpresasException extends \Exception{}

    \predial\ControladorEmpresas::run();
