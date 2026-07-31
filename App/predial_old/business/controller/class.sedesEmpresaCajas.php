<?php
namespace predial;
include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_SedesEmpresaCajas.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER .'/business/controller/class.logs.php';

class ControladorSedesEmpresaCajas extends \predial\Cabecera {

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
                    $respuesta = $_obj->_agregarSedesEmpresaCajas();
                    break;
                case 2:
                    $respuesta = $_obj->_editarSedesEmpresaCajas();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarSedesEmpresaCajas();
                    break; 
                case 4:
                    $respuesta = $_obj->_inactivarSedesEmpresaCajas();
                    break; 
            }
            $con->commit();
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
            
        } catch (\predial\SedesEmpresaCajasException $e) {
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
    protected function _agregarSedesEmpresaCajas() {
        
        $_objCaja = new \predial\DAO_SedesEmpresaCajas();
        
        $_objCaja->set_seemca_Nombre($_POST['nombre']);
        $_objCaja->set_seemca_Serial($_POST['Serial']);
        $_objCaja->set_seemca_CodigoCaja($_POST['CodigoCaja']);
        $_objCaja->set_seemca_IdSedeEmpresa($_POST['IdSedeEmpresa']);
        $_objCaja->set_seemca_IdResolucion($_POST['IdResolucion']);
        $_objCaja->set_seemca_IdResolucionRemi($_POST['IdResolucionRemi']);
        
        $_objCaja->set_seemca_Estado($_POST['estado']);
        
        //Valida si es igual el nombre a alguno de la BD.
        $nomRol= $this->_listarSedesEmpresaCajas(0);
        $longitud = count($nomRol);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomRol[$i]['seemca_Nombre'] == $_objCaja->get_seemca_Nombre()){
               $nomduplicado=1;
                break;
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un nombre de caja con el mismo nombre';
            $return= false; 
        }else {
            if(!$_objCaja->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objCaja->getMysqlError();
            }else{
                $id = $_objCaja->get_seemca_Id();
                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,2);
                $this->_ok = 1;
                $this->_mensaje = "Caja ingresada correctamente";
            }
            $return= $_objCaja->guardar();
        }
        return $return;
    }
       
    /**
    *** Realiza el proceso de Editar Roles.
    **/ 
    protected function _editarSedesEmpresaCajas() {

        $_objCaja = new \predial\DAO_SedesEmpresaCajas();

        $_objCaja->set_seemca_Id($_POST['id']);
        $_objCaja->set_seemca_Nombre($_POST['Nombre']);
        $_objCaja->set_seemca_Serial($_POST['Serial']);
        $_objCaja->set_seemca_CodigoCaja($_POST['CodigoCaja']);
        $_objCaja->set_seemca_IdSedeEmpresa($_POST['IdSedeEmpresa']);
        $_objCaja->set_seemca_IdResolucion($_POST['IdResolucion']);
        $_objCaja->set_seemca_IdResolucionRemi($_POST['IdResolucionRemi']);
        
        //$_objCaja->set_seemca_Estado($_POST['estado']);

        //Valida si es igual el nombre a alguno de la BD.
        $nomRol= $this->_listarSedesEmpresaCajas($_objCaja->get_seemca_Id());
        $longitud = count($nomRol);
        $nomduplicado=0;

        for($i=0; $i<$longitud; $i++){  
            if($nomRol[$i]['seemca_Nombre'] == $_objCaja->get_seemca_Nombre()){
               $nomduplicado=1;
                break;
            }

            if(($nomRol[$i]['seemca_IdResolucion'] != 0)){
                if($nomRol[$i]['seemca_IdResolucion'] == $_objCaja->get_seemca_IdResolucion()){
                    $nomduplicado=2;
                    break;
                }
            }
            
            if(($nomRol[$i]['seemca_IdResolucionRemi'] != 0)){
                if($nomRol[$i]['seemca_IdResolucionRemi'] == $_objCaja->get_seemca_IdResolucionRemi()){
                    $nomduplicado=3;
                    break;
                }
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un nombre de caja con el mismo nombre';
            $return= false; 
        }else if($nomduplicado == 2){
            $this->_ok = 3;
            $this->_mensaje = 'La Resolución POS ya esta asociada a otra Caja';
            $return= false; 
        }else if($nomduplicado == 3){
            $this->_ok = 4;
            $this->_mensaje = 'La Resolución Remisión ya esta asociada a otra Caja';
            $return= false; 
        }else {
            if(!$_objCaja->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objCaja->getMysqlError();
            }else{
                $id = $_objCaja->get_seemca_Id();
                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,3);
                $this->_ok = 1;
                $this->_mensaje = "Caja editado correctamenteeee";
            }
            $return= $_objCaja->guardar();
        }
        return $return;
    }
    
        
    /**
    *** Realiza el proceso de Listar roles, exeptuando el rol enviado por parametro.
    *** @param type $id_rol
    **/  
    private function _listarSedesEmpresaCajas($id_bod) {
       
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT * FROM conf_sedes_empresa_cajas WHERE seemca_Id <> $id_bod";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) > 0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "Cajas listadas";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existen Cajas";
            $row=[];
        }
        return $row;     
    }  
    
    /**
    *** Realiza el proceso de Activar o Inactivar Roles.
    **/ 
    protected function _inactivarSedesEmpresaCajas() {

        $_objCaja = new \predial\DAO_SedesEmpresaCajas();
        $_objCaja->set_seemca_Id($_POST['id']);
        $_objCaja->set_seemca_Estado($_POST['estado']);

        if(!$_objCaja->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objCaja->getMysqlError();
        }else{
            $id = $_objCaja->get_seemca_Id();
            // $_objlogs = new logs();
            // $_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,4);
            $this->_ok = 1;
            $this->_mensaje = "Caja Activado/Inactivado correctamente";
        }
        return $_objCaja->getArray();
    }
    
    /**
    *** Realiza el proceso de Consultar SedesEmpresaCajas.
    **/ 
    private function _consultarSedesEmpresaCajas() {
       
        $_objCaja = new \predial\DAO_SedesEmpresaCajas();
        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objCaja->set_seemca_Id($_POST['id']);
            }    
        }

        if(isset($_POST['IdSedeEmpresa'])){
            if (!empty($_POST['IdSedeEmpresa']) || $_POST['IdSedeEmpresa'] != NULL ) {
                $_objCaja->set_seemca_IdSedeEmpresa($_POST['IdSedeEmpresa']);
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
            $this->_mensaje = "Cajas listadas con exito";
        }else{
            $R=$_objCaja;
            $this->_ok = 0;
            $this->_mensaje = "No existen Cajas";            
        }
        return $R;
    }  
}

class SedesEmpresaCajasException extends \Exception{}

    \predial\ControladorSedesEmpresaCajas::run();

