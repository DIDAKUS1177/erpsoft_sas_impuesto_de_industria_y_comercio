<?php
namespace predial;
include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_SedesEmpresaMesas.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER .'/business/controller/class.logs.php';

class ControladorSedesEmpresaMesas extends \predial\Cabecera {

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
                    $respuesta = $_obj->_agregarSedesEmpresaMesas();
                    break;
                case 2:
                    $respuesta = $_obj->_editarSedesEmpresaMesas();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarSedesEmpresaMesas();
                    break; 
                case 4:
                    $respuesta = $_obj->_inactivarSedesEmpresaMesas();
                    break; 
                case 5:
                    $respuesta = $_obj->_consultarMesasDisponibles();
                    break; 
            }
            $con->commit();
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
            
        } catch (\predial\SedesEmpresaMesasException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    /**
    *** Realiza el proceso de Crear Roles.
    **/ 
    protected function _agregarSedesEmpresaMesas() {
        
        $_objCaja = new \predial\DAO_SedesEmpresaMesas();
        
        $_objCaja->set_seemma_Nombre($_POST['nombre']);
        $_objCaja->set_seemma_IdSedeEmpresa($_POST['IdSedeEmpresa']);        
        $_objCaja->set_seemma_Estado(1);
        
        //Valida si es igual el nombre a alguno de la BD.
        $nomRol= $this->_listarSedesEmpresaMesas(0);
        $longitud = count($nomRol);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomRol[$i]['seemma_Nombre'] == $_objCaja->get_seemma_Nombre()){
               $nomduplicado=1;
                break;
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un nombre de Mesa con el mismo nombre';
            $return= false; 
        }else {
            if(!$_objCaja->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objCaja->getMysqlError();
            }else{
                $id = $_objCaja->get_seemma_Id();                
                $this->_ok = 1;
                $this->_mensaje = "Mesa ingresada correctamente";
            }
            $return= $_objCaja->guardar();
        }
        return $return;
    }
       
    /**
    *** Realiza el proceso de Editar Roles.
    **/ 
    protected function _editarSedesEmpresaMesas() {

        $_objCaja = new \predial\DAO_SedesEmpresaMesas();

        $_objCaja->set_seemma_Id($_POST['id']);
        $_objCaja->set_seemma_Nombre($_POST['nombre']);        
        $_objCaja->set_seemma_IdSedeEmpresa($_POST['IdSedeEmpresa']);

        //Valida si es igual el nombre a alguno de la BD.
        $nomRol= $this->_listarSedesEmpresaMesas($_objCaja->get_seemma_Id());
        $longitud = count($nomRol);
        $nomduplicado=0;

        for($i=0; $i<$longitud; $i++){  
            if($nomRol[$i]['seemma_Nombre'] == $_objCaja->get_seemma_Nombre()){
               $nomduplicado=1;
                break;
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un nombre de mesa con el mismo nombre';
            $return= false; 
        }else {
            if(!$_objCaja->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objCaja->getMysqlError();
            }else{
                $id = $_objCaja->get_seemma_Id();
                $this->_ok = 1;
                $this->_mensaje = "Mesa editada correctamenteeee";
            }
            $return= $_objCaja->guardar();
        }
        return $return;
    }
    
        
    /**
    *** Realiza el proceso de Listar roles, exeptuando el rol enviado por parametro.
    *** @param type $id_rol
    **/  
    private function _listarSedesEmpresaMesas($id_bod) {
       
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT * FROM conf_sedes_empresa_mesas WHERE seemma_Id <> $id_bod";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) > 0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "Mesas listadas";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existen Mesas";
            $row=[];
        }
        return $row;     
    }  
    
    /**
    *** Realiza el proceso de Activar o Inactivar Roles.
    **/ 
    protected function _inactivarSedesEmpresaMesas() {

        $_objCaja = new \predial\DAO_SedesEmpresaMesas();
        $_objCaja->set_seemma_Id($_POST['id']);
        $_objCaja->set_seemma_Estado($_POST['estado']);

        if(!$_objCaja->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objCaja->getMysqlError();
        }else{
            $id = $_objCaja->get_seemma_Id();
            $this->_ok = 1;
            $this->_mensaje = "Mesa Activado/Inactivado correctamente";
        }
        return $_objCaja->getArray();
    }
    
    /**
    *** Realiza el proceso de Consultar SedesEmpresaCajas.
    **/ 
    private function _consultarSedesEmpresaMesas() {
       
        $_objCaja = new \predial\DAO_SedesEmpresaMesas();
        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objCaja->set_seemma_Id($_POST['id']);
            }    
        }

        if(isset($_POST['IdSedeEmpresa'])){
            if (!empty($_POST['IdSedeEmpresa']) || $_POST['IdSedeEmpresa'] != NULL ) {
                $_objCaja->set_seemma_IdSedeEmpresa($_POST['IdSedeEmpresa']);
            }    
        }
        
        $_objCaja->habilita1ResultadoEnArray();
        $arrRol = $_objCaja->consultar();
       
        if(is_array($arrRol) && count($arrRol)){
            $R = [];
            foreach($arrRol as $obj){
                $R[] = $obj->getArray();
            }
            $this->_ok = 1;
            $this->_mensaje = "Mesas listadas con exito";
        }else{
            $R=$_objCaja;
            $this->_ok = 0;
            $this->_mensaje = "No existen Mesas";            
        }
        return $R;
    }  


     /**
    *** Realiza el proceso de _consultarMesasDisponibles
    **/  
    protected function _consultarMesasDisponibles() {

        $idSede = $_POST['idSede'];
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT  sm.seemma_Id, sm.seemma_Nombre, IFNULL((SELECT om.doc_Estado from fac_documento_ordenes
                                            as om WHERE om.doc_IdMesa=sm.seemma_Id 
                                            ORDER BY OM.doc_Id DESC LIMIT 1),0) estado 
                FROM conf_sedes_empresa_mesas as sm 
                    WHERE sm.seemma_IdSedeEmpresa = $idSede and sm.seemma_Estado = 1;";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_id = 0;
            $this->_mensaje = "Max ID Productos";
        }else{
            $this->_ok = 0;
            $this->_id = 0;
            $this->_mensaje = "No existen ";
            $row = NULL;
        }
        return $row;  
    }
}

class SedesEmpresaMesasException extends \Exception{}

    \predial\ControladorSedesEmpresaMesas::run();

