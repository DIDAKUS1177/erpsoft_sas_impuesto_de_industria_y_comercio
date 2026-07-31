<?php
namespace predial;
include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_TiposPagos.php';
include_once SERVER . '/business/DAO/DAO_SubTiposPagos.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER .'/business/controller/class.logs.php';

class ControladorTiposPagos extends \predial\Cabecera {

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
                    $respuesta = $_obj->_agregarTiposPagos();
                    break;
                case 2:
                    $respuesta = $_obj->_editarTiposPagos();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarTiposPagos();
                    break; 
                case 4:
                    $respuesta = $_obj->_inactivarTiposPagos();
                    break; 

                case 5:
                    $respuesta = $_obj->_consultarSubTiposPagos();
                    break; 
                case 6:
                    $respuesta = $_obj->_agregarSubTiposPagos();
                    break; 
                case 7:
                    $respuesta = $_obj->_editarSubTiposPagos();
                    break; 
                case 8:
                    $respuesta = $_obj->_inactivarSubTiposPagos();
                    break; 

            }
            $con->commit();
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
            
        } catch (\predial\RolException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    /**
    * _agregarRol: Método que realiza el proceso de Crear Roles.
    */ 
    protected function _agregarTiposPagos() {
        
        $_objTipo = new \predial\DAO_TiposPagos();
        $_objTipo->set_tipa_Nombre($_POST['nombre']);
        $_objTipo->set_tipa_IdTipo($_POST['idTipo']);
        $_objTipo->set_tipa_Estado(1);
        
        //Valida los campos que no pueden Duplicarsen en la BD.
        $nomTipo= $this->_listarTiposPagos(0);
        $longitud = count($nomTipo);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomTipo[$i]['tipa_Nombre'] == $_objTipo->get_tipa_Nombre()){
               $nomduplicado=1;
                break;
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un nombre de Tipo de Pago con el mismo nombre';
            $return= false; 
        }else {
            if(!$_objTipo->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objTipo->getMysqlError();
            }else{
                $id = $_objTipo->get_tipa_Id();
                $this->_ok = 1;
                $this->_mensaje = "Tipo de Pago ingresado correctamente";
            }
            $return= $_objTipo->guardar();
        }
        return $return;
    }
       
    /**
    * _editarTiposPagos: Método que realiza el proceso de Editar _editarTiposPagos.
    */ 
    protected function _editarTiposPagos() {

        $_objTipo = new \predial\DAO_TiposPagos();
        $_objTipo->set_tipa_Id($_POST['id']);
        $_objTipo->set_tipa_IdTipo($_POST['idTipo']);
        $_objTipo->set_tipa_Nombre($_POST['nombre']);
        
        //Valida los campos que no pueden Duplicarsen en la BD.
        $nomTipo= $this->_listarTiposPagos($_objTipo->get_tipa_Id());
        $longitud = count($nomTipo);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomTipo[$i]['tipa_Nombre'] == $_objTipo->get_tipa_Nombre()){
               $nomduplicado=1;
                break;
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un nombre de tipo de pago con el mismo nombre';
            $return= false; 
        }else {
            if(!$_objTipo->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objTipo->getMysqlError();
            }else{
                $id = $_objTipo->get_tipa_Id();
                $this->_ok = 1;
                $this->_mensaje = "Tipo de Pago editado correctamente";
            }
            $return= $_objTipo->guardar();
        }
        return $return;
    }
    
    /**
    * _listarTiposPagos: Método que realiza el proceso 
    * de Listar tipos de pagos, exeptuando el tipo de pago enviado por parametro.
    * @param type $id_tipoPago: llave primaria de la tabla rol
    */  
    private function _listarTiposPagos($id_tipoPago) {
       
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT * FROM fac_tipos_pagos WHERE tipa_Id <> $id_tipoPago";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "Tipo Pago listados";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existen Tipos Pagos";
            $row=[];
        }
        return $row;     
    }  
    
    /**
    * _inactivarRol: Método que ealiza el proceso de 
    * Activar o Inactivar Roles.
    */ 
    protected function _inactivarTiposPagos() {

        $_objTipo = new \predial\DAO_TiposPagos();
        $_objTipo->set_tipa_Id($_POST['id']);
        $_objTipo->set_tipa_Estado($_POST['estado']);

        if(!$_objTipo->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objTipo->getMysqlError();
        }else{
            $id = $_objTipo->get_tipa_Id();
            $this->_ok = 1;
            $this->_mensaje = "Tipo de Pago Activado/Inactivado correctamente";
        }
        return $_objTipo->getArray();
    }
    
    /**
    * _consultarRol: Método que Realiza el proceso de Consultar Roles.
    */ 
    private function _consultarTiposPagos() {
       
        $_objTipo = new \predial\DAO_TiposPagos();
        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objTipo->set_tipa_Id($_POST['id']);
            }    
        }

        if(isset($_POST['idTipo'])){
            if (!empty($_POST['idTipo']) || $_POST['idTipo'] != NULL ) {
                $_objTipo->set_tipa_IdTipo($_POST['idTipo']);
            }    
        }

        if(isset($_POST['estado'])){
            if (!empty($_POST['estado']) || $_POST['estado'] != NULL ) {
                $_objTipo->set_tipa_Estado($_POST['estado']);
            }    
        }
        
        $_objTipo->habilita1ResultadoEnArray();
        $arrRol = $_objTipo->consultar();
       
        if(is_array($arrRol) && count($arrRol)){
            $R = [];
            foreach($arrRol as $obj){
                $R[] = $obj->getArray();
            }
            $this->_ok = 1;
            $this->_mensaje = "Tipos de Pago listados con exito";
        }else{
            $R=$_objTipo;
            $this->_ok = 0;
            $this->_mensaje = "No existen Tipos de Pagos";            
        }
        return $R;
    }  

// ********************** CRUD SUB TIPO DE PAGOS ************************************************


  /**
    * _agregarRol: Método que realiza el proceso de Crear Roles.
    */ 
    protected function _agregarSubTiposPagos() {
        
        $_objTipo = new \predial\DAO_SubTiposPagos();
        $_objTipo->set_subtipa_Nombre($_POST['nombre']);
        $_objTipo->set_subtipa_IdTipo($_POST['idTipo']);
        $_objTipo->set_subtipa_Estado(1);
        
        //Valida los campos que no pueden Duplicarsen en la BD.
        $nomTipo= $this->_listarSubTiposPagos(0);
        $longitud = count($nomTipo);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomTipo[$i]['subtipa_Nombre'] == $_objTipo->get_subtipa_Nombre()){
               $nomduplicado=1;
                break;
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un nombre de Sub Tipo de Pago con el mismo nombre';
            $return= false; 
        }else {
            if(!$_objTipo->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objTipo->getMysqlError();
            }else{
                $id = $_objTipo->get_subtipa_Id();
                $this->_ok = 1;
                $this->_mensaje = "SubTipo de Pago ingresado correctamente";
            }
            $return= $_objTipo->guardar();
        }
        return $return;
    }
       
    /**
    * _editarTiposPagos: Método que realiza el proceso de Editar _editarTiposPagos.
    */ 
    protected function _editarSubTiposPagos() {

        $_objTipo = new \predial\DAO_SubTiposPagos();
        $_objTipo->set_subtipa_Id($_POST['id']);
        $_objTipo->set_subtipa_IdTipo($_POST['idTipo']);
        $_objTipo->set_subtipa_Nombre($_POST['nombre']);
        
        //Valida los campos que no pueden Duplicarsen en la BD.
        $nomTipo= $this->_listarSubTiposPagos($_objTipo->get_subtipa_Id());
        $longitud = count($nomTipo);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomTipo[$i]['subtipa_Nombre'] == $_objTipo->get_subtipa_Nombre()){
               $nomduplicado=1;
                break;
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un nombre de Sub tipo de pago con el mismo nombre';
            $return= false; 
        }else {
            if(!$_objTipo->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objTipo->getMysqlError();
            }else{
                $id = $_objTipo->get_subtipa_Id();
                $this->_ok = 1;
                $this->_mensaje = "SubTipo de Pago editado correctamente";
            }
            $return= $_objTipo->guardar();
        }
        return $return;
    }
    
    /**
    * _listarTiposPagos: Método que realiza el proceso 
    * de Listar tipos de pagos, exeptuando el tipo de pago enviado por parametro.
    * @param type $id_tipoPago: llave primaria de la tabla rol
    */  
    private function _listarSubTiposPagos($id_subtipoPago) {
       
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT * FROM fac_sub_tipos_pagos WHERE subtipa_Id <> $id_subtipoPago";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "SubTipo Pago listados";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existen SubTipos Pagos";
            $row=[];
        }
        return $row;     
    }  
    
    /**
    * _inactivarSubTiposPagos: Método que ealiza el proceso de 
    * Activar o Inactivar Roles.
    */ 
    protected function _inactivarSubTiposPagos() {

        $_objTipo = new \predial\DAO_SubTiposPagos();
        $_objTipo->set_subtipa_Id($_POST['id']);
        $_objTipo->set_subtipa_Estado($_POST['estado']);

        if(!$_objTipo->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objTipo->getMysqlError();
        }else{
            $id = $_objTipo->get_subtipa_Id();
            $this->_ok = 1;
            $this->_mensaje = "SubTipo de Pago Activado/Inactivado correctamente";
        }
        return $_objTipo->getArray();
    }

    
    /**
    * _consultarSubTiposPagos: Método que Realiza el proceso de Consultar Roles.
    */ 
    private function _consultarSubTiposPagos() {
       
        $_objTipo = new \predial\DAO_SubTiposPagos();
        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objTipo->set_subtipa_Id($_POST['id']);
            }    
        }

        if(isset($_POST['idTipo'])){
            if (!empty($_POST['idTipo']) || $_POST['idTipo'] != NULL ) {
                $_objTipo->set_subtipa_IdTipo($_POST['idTipo']);
            }    
        }

        if(isset($_POST['estado'])){
            if (!empty($_POST['estado']) || $_POST['estado'] != NULL ) {
                $_objTipo->set_subtipa_Estado($_POST['estado']);
            }    
        }
        
        $_objTipo->habilita1ResultadoEnArray();
        $arrRol = $_objTipo->consultar();
       
        if(is_array($arrRol) && count($arrRol)){
            $R = [];
            foreach($arrRol as $obj){
                $R[] = $obj->getArray();
            }
            $this->_ok = 1;
            $this->_mensaje = "Tipos de Pago listados con exito";
        }else{
            $R=$_objTipo;
            $this->_ok = 0;
            $this->_mensaje = "No existen Tipos de Pagos";            
        }
        return $R;
    }  
}

class TiposPagosException extends \Exception{}

    \predial\ControladorTiposPagos::run();

