<?php
namespace predial;
include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_Resolucion.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER .'/business/controller/class.logs.php';


class ControladorResoluciones extends \predial\Cabecera {

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
                    $respuesta = $_obj->_agregarResoluciones();
                    break;
                case 2:
                    $respuesta = $_obj->_editarResoluciones();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarResoluciones();
                    break; 
                case 4:
                    $respuesta = $_obj->_inactivarResoluciones();
                    break; 
                case 5:
                    $respuesta = $_obj->_consultarTipoDocumentos();
                    break; 
            }
            $con->commit();
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
            
        } catch (\predial\ResolucionesException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    /**
    * _agregarResoluciones: Método que realiza el proceso de Crear Resoluciones.
    */ 
    protected function _agregarResoluciones() {
        
        $_objResoluciones = new \predial\DAO_Resolucion();
        $_objResoluciones->set_reso_IdTipoDocumento($_POST['IdTipoDocumento']);
        $_objResoluciones->set_reso_Numero($_POST['Numero']);
        $_objResoluciones->set_reso_Prefijo($_POST['Prefijo']);
        $_objResoluciones->set_reso_NumeroInicial($_POST['NumeroInicial']);
        $_objResoluciones->set_reso_NumeroFinal($_POST['NumeroFinal']);
        $_objResoluciones->set_reso_FechaAutorizacion($_POST['FechaAutorizacion']);
        $_objResoluciones->set_reso_FechaVencimiento($_POST['FechaVencimiento']);
        $_objResoluciones->set_reso_Estado(1);
        
        //Valida si es igual el nombre a alguno de la BD.
        $nomResoluciones= $this->_listarResoluciones(0);
        $longitud = count($nomResoluciones);
        $nomduplicado=0;
        
            for($i=0; $i<$longitud; $i++){ 
                if($_POST['IdTipoDocumento'] != 1){ 
                    if($nomResoluciones[$i]['reso_Numero'] == $_objResoluciones->get_reso_Numero()){
                        $nomduplicado=1;
                        break;
                    }
                }
                if($nomResoluciones[$i]['reso_Prefijo'] == $_objResoluciones->get_reso_Prefijo()){
                    $nomduplicado=2;
                     break;
                 }
            }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe una Resolucion con el mismo Numero';
            $return= false; 
        }else if($nomduplicado == 2){
            $this->_ok = 3;
            $this->_mensaje = 'Ya existe una Resolucion con el mismo Prefijo';
            $return= false; 
        }else {
            if(!$_objResoluciones->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objResoluciones->getMysqlError();
            }else{
                $id = $_objResoluciones->get_reso_Id();
                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,2);
                $this->_ok = 1;
                $this->_mensaje = "Resoluciones ingresados correctamente";
            }
            $return= $_objResoluciones->guardar();
        }
        return $return;
    }
       
    /**
    * _editarResoluciones: Método que realiza el proceso de Editar Resoluciones.
    */ 
    protected function _editarResoluciones() {

        $_objResoluciones = new \predial\DAO_Resolucion();
        $_objResoluciones->set_reso_Id($_POST['id']);
        $_objResoluciones->set_reso_IdTipoDocumento($_POST['IdTipoDocumento']);
        $_objResoluciones->set_reso_Numero($_POST['Numero']);
        $_objResoluciones->set_reso_Prefijo($_POST['Prefijo']);
        $_objResoluciones->set_reso_NumeroInicial($_POST['NumeroInicial']);
        $_objResoluciones->set_reso_NumeroFinal($_POST['NumeroFinal']);
        $_objResoluciones->set_reso_FechaAutorizacion($_POST['FechaAutorizacion']);
        $_objResoluciones->set_reso_FechaVencimiento($_POST['FechaVencimiento']);

        //Valida si es igual el nombre a alguno de la BD.
        $nomResoluciones= $this->_listarResoluciones($_objResoluciones->get_reso_Id());
        $longitud = count($nomResoluciones);
        $nomduplicado=0;
        
            for($i=0; $i<$longitud; $i++){  
                if($_POST['IdTipoDocumento'] != 1){
                    if($nomResoluciones[$i]['reso_Numero'] == $_objResoluciones->get_reso_Numero()){
                        $nomduplicado=1;
                        break;
                    }
                }
                
                if($nomResoluciones[$i]['reso_Prefijo'] == $_objResoluciones->get_reso_Prefijo()){
                    $nomduplicado=2;
                     break;
                 }            
            }   

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe una Resolucion con el mismo nombre';
            $return= false; 
        }else if($nomduplicado == 2){
            $this->_ok = 3;
            $this->_mensaje = 'Ya existe una Resolucion con el mismo Prefijo';
            $return= false; 
        }else {
            if(!$_objResoluciones->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objResoluciones->getMysqlError();
            }else{
                $id = $_objResoluciones->get_reso_Id();
                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,3);
                $this->_ok = 1;
                $this->_mensaje = "Resoluciones editado correctamente";
            }
            $return= $_objResoluciones->guardar();
        }
        return $return;
    }
    
    /**
    * _listarResolucioneses: Método que realiza el proceso 
    * de Listar Resoluciones, exeptuando la Resolucion enviado por parametro.
    * @param type $id_Resoluciones: llave primaria de la tabla Resoluciones
    */  
    private function _listarResoluciones($id_Resolucion) {
       
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT * FROM conf_resoluciones WHERE reso_Id <> $id_Resolucion ";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "Resoluciones listados";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existen Resoluciones";
            $row=[];
        }
        return $row;     
    }  
    
    /**
    * _inactivarResoluciones: Método que realiza el proceso de 
    * Activar o Inactivar Resoluciones.
    */ 
    protected function _inactivarResoluciones() {

        $_objResoluciones = new \predial\DAO_Resolucion();
        $_objResoluciones->set_reso_Id($_POST['id']);
        $_objResoluciones->set_reso_Estado($_POST['estado']);

        if(!$_objResoluciones->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objResoluciones->getMysqlError();
        }else{
            $id = $_objResoluciones->get_reso_Id();
            //$_objlogs = new logs();
            //$_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,4);
            $this->_ok = 1;
            $this->_mensaje = "Resoluciones Activado/Inactivado correctamente";
        }
        return $_objResoluciones->getArray();
    }
    
    /**
    * _consultarResoluciones: Método que ealiza el proceso de Consultar Resoluciones.
    */ 
    private function _consultarResoluciones() {
       
        $_objResoluciones = new \predial\DAO_Resolucion();
        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objResoluciones->set_reso_Id($_POST['id']);
            }    
        }

        if(isset($_POST['estado'])){
            if (!empty($_POST['estado']) || $_POST['estado'] != NULL ) {
                $_objResoluciones->set_reso_Estado($_POST['estado']);
            }    
        }

        
        if(isset($_POST['tipo_Documento'])){
            if (!empty($_POST['tipo_Documento']) || $_POST['tipo_Documento'] != NULL ) {
                $_objResoluciones->set_reso_IdTipoDocumento($_POST['tipo_Documento']);
            }    
        }
        
        $_objResoluciones->habilita1ResultadoEnArray();
        $arrResoluciones = $_objResoluciones->consultar();
       
        if(is_array($arrResoluciones) && count($arrResoluciones)){
            $R = [];
            foreach($arrResoluciones as $obj){
                $R[] = $obj->getArray();
            }
            $this->_ok = 1;
            $this->_mensaje = "Resoluciones listados con exito";
        }else{
            $R=$_objResoluciones;
            $this->_ok = 0;
            $this->_mensaje = "No existen Resoluciones";            
        }
        return $R;
    }  

    /**
    * _consultarTipoDocumentos: Método que realiza el proceso de Consultar Tipos de Documento.
    */ 
    private function _consultarTipoDocumentos() {
       
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT * FROM conf_tipo_documento";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) > 0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "Tipos Documentos listados";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existen Tipos Documentos";
            $row=[];
        }
        return $row;     
    } 
}

class ResolucionesException extends \Exception{}

    \predial\ControladorResoluciones::run();

