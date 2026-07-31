<?php
namespace predial;
include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_Insumo.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER.'/business/controller/class.logs.php';

class ControladorInsumo extends \predial\Cabecera {

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
                    $respuesta = $_obj->_agregarInsumos();
                    break;
                case 2:
                    $respuesta = $_obj->_editarInsumos();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarInsumos();
                    break; 
                case 4:
                    $respuesta = $_obj->_inactivarInsumos();
                    break; 
                case 5:
                    $respuesta = $_obj->_consultarUnidadMedida();
                    break; 
                case 6:
                    $respuesta = $_obj->_consultarTipoCantidad();
                    break; 
                case 7:
                    $respuesta = $_obj->_consultarTipoUnidad();
                    break; 
                case 8:
                    $respuesta = $_obj->_consultarMxId();
                    break; 
                    
            }
            $con->commit();
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
            
        } catch (\predial\InsumosException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    /**
    *** Realiza el proceso de Crear Insumos.
    **/  
    protected function _agregarInsumos() {
        
        $_objInsumo = new \predial\DAO_Insumo();
        $_objInsumo->set_ins_IdCategoria($_POST['idCategoria']);
        $_objInsumo->set_ins_IdSubCategoria($_POST['idSubCategoria']);
        $_objInsumo->set_ins_IdProveedor($_POST['idProveedor']);
        $_objInsumo->set_ins_Nombre($_POST['nombre']);
        $_objInsumo->set_ins_Codigo($_POST['codigo']);

        if($_POST['codBarras'] == ''){}else{
            $_objInsumo->set_ins_CodBarras($_POST['codBarras']);
        }
        if($_POST['referenciaNombre1'] == ''){}else{
            $_objInsumo->set_ins_ReferenciaNombre1($_POST['referenciaNombre1']);
        }
        if($_POST['referenciaValor1'] == ''){}else{
            $_objInsumo->set_ins_ReferenciaValor1($_POST['referenciaValor1']);
        }
        if($_POST['referenciaNombre2'] == ''){}else{
            $_objInsumo->set_ins_ReferenciaNombre2($_POST['referenciaNombre2']);
        }
        if($_POST['referenciaValor2'] == ''){}else{
            $_objInsumo->set_ins_ReferenciaValor2($_POST['referenciaValor2']);
        }

        $_objInsumo->set_ins_IdTipoCantidad($_POST['idTipoCantidad']);
        $_objInsumo->set_ins_IdTipoUnidad($_POST['idTipoUnidad']);        
        $_objInsumo->set_ins_Estado(1);

        //Valida si es igual el codigo alguno de la BD.
        $nomUsurio= $this->_listarInsumos(0);
        $longitud = count($nomUsurio);
        $nomduplicado=0;

        for($i=0; $i<$longitud; $i++){  
            if($nomUsurio[$i]['ins_Codigo'] == $_objInsumo->get_ins_Codigo()){
               $nomduplicado=1;
                break;
            }

            if( ($_POST['codBarras'] != '') and ($nomUsurio[$i]['ins_CodBarras'] == $_objInsumo->get_ins_CodBarras())){
                $nomduplicado=2;
                 break;
             }
            
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un Insumo con el mismo código';
            $return= false; 
        }else if($nomduplicado == 2){
            $this->_ok = 3;
            $this->_mensaje = 'Ya existe un Insumo con el mismo codigo de Barras';
            $return= false;   
        }else{
            if(!$_objInsumo->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objInsumo->getMysqlError();
            }else{
                $id = $_objInsumo->get_ins_Id();
                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($id,3,$_SESSION['id_Insumo'],3);
                $this->_ok = 1;
                $this->_mensaje = "Datos ingresados correctamente";
            }
            $return= $_objInsumo->guardar();
        }
        return $return;
    }
    
    /**
    *** Realiza el proceso de Editar Insumos.
    **/  
    protected function _editarInsumos() {
        
        $_objInsumo = new \predial\DAO_Insumo();

        $_objInsumo->set_ins_Id($_POST['id']);
        $_objInsumo->set_ins_IdCategoria($_POST['idCategoria']);
        $_objInsumo->set_ins_IdSubCategoria($_POST['idSubCategoria']);
        $_objInsumo->set_ins_IdProveedor($_POST['idProveedor']);
        $_objInsumo->set_ins_Nombre($_POST['nombre']);
        $_objInsumo->set_ins_Codigo($_POST['codigo']);

        if($_POST['codBarras'] == ''){
            $_objInsumo->set_ins_CodBarras('');
        }else{
            $_objInsumo->set_ins_CodBarras($_POST['codBarras']);
        }
        if($_POST['referenciaNombre1'] == ''){
            $_objInsumo->set_ins_ReferenciaNombre1('');
        }else{
            $_objInsumo->set_ins_ReferenciaNombre1($_POST['referenciaNombre1']);
        }
        if($_POST['referenciaValor1'] == ''){
            $_objInsumo->set_ins_ReferenciaValor1('');
        }else{
            $_objInsumo->set_ins_ReferenciaValor1($_POST['referenciaValor1']);
        }
        if($_POST['referenciaNombre2'] == ''){
            $_objInsumo->set_ins_ReferenciaNombre2('');
        }else{
            $_objInsumo->set_ins_ReferenciaNombre2($_POST['referenciaNombre2']);
        }
        if($_POST['referenciaValor2'] == ''){
            $_objInsumo->set_ins_ReferenciaValor2('');
        }else{
            $_objInsumo->set_ins_ReferenciaValor2($_POST['referenciaValor2']);
        }

        $_objInsumo->set_ins_IdTipoCantidad($_POST['idTipoCantidad']);
        $_objInsumo->set_ins_IdTipoUnidad($_POST['idTipoUnidad']);   

        //Valida si es igual el email y/o identificación a alguno de la BD.
        $nomUsurio= $this->_listarInsumos($_objInsumo->get_ins_Id());
        $longitud = count($nomUsurio);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomUsurio[$i]['ins_Codigo'] == $_objInsumo->get_ins_Codigo()){
               $nomduplicado=1;
                break;
            }

            if( ($_POST['codBarras'] != '') and ($nomUsurio[$i]['ins_CodBarras'] == $_objInsumo->get_ins_CodBarras())){
                $nomduplicado=2;
                 break;
             }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un Insumo con el mismo código';
            $return= false; 
        }else if($nomduplicado == 2){
            $this->_ok = 3;
            $this->_mensaje = 'Ya existe un Insumo con el mismo código de Barras';
            $return= false;   
        }else{
            if(!$_objInsumo->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objInsumo->getMysqlError();
            }else{
                $id = $_objInsumo->get_ins_Id();
                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($id,4,$_SESSION['id_Usuario'],3);
                $this->_ok = 1;
                $this->_mensaje = "Datos ingresados correctamente";
            }
            $return= $_objInsumo->guardar();
        }
        return $return;
    }
    
    /**
    *** Realiza el proceso de Listar Insumos, exeptuando el Insumo enviado por parametro.
    *** @param type $id_Insumo
    **/  
    private function _listarInsumos($id_Insumo) {
       
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT * FROM prod_insumos WHERE ins_Id <> $id_Insumo ";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "Insumos listados";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existen Insumos";
            $row=[];
        }
        return $row;     
    }  
    
    /**
    *** Realiza el proceso de Consultar Insumos.
    **/  
    private function _consultarInsumos() {
       
        $_objUsu = new \predial\DAO_Insumo();
        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objUsu->set_ins_Id($_POST['id']);
            }    
        }

        if(isset($_POST['estado'])){
            if (!empty($_POST['estado']) || $_POST['estado'] != NULL ) {
                $_objUsu->set_ins_Estado($_POST['estado']);
            }    
        }
        
        
        $_objUsu->habilita1ResultadoEnArray();
        $arrInsumos = $_objUsu->consultar();
       
        if(is_array($arrInsumos) && count($arrInsumos)){
            $R = [];
            foreach($arrInsumos as $obj){
                $R[] = $obj->getArray();
            }    
            $this->_ok = 1;
            $this->_mensaje = "Insumos listados con exito"; 
        }else{
            $R=$_objUsu;
            $this->_ok = 0;
            $this->_mensaje = "No existen Insumos";            
        }       
        return $R;
    }
    
    /**
    *** Realiza el proceso de Activar o Inactivar Insumos.
    **/  
    protected function _inactivarInsumos() {

        $_objInsumo = new \predial\DAO_Insumo();
        $_objInsumo->set_ins_Id($_POST['id']);
        $_objInsumo->set_ins_Estado($_POST['estado']);
        
        if(!$_objInsumo->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objInsumo->getMysqlError();
        }else{
            $id = $_objInsumo->get_ins_Id();
//            $_objlogs = new logs();
//            $_objlogs->_insertLogs($id,5,$_SESSION['id_Insumo'],2);
            $this->_ok = 1;
            $this->_mensaje = "Insumo Activado/inactivado correctamente";
        }
        return $_objInsumo->getArray();
    }

    /**
    *** Realiza el proceso de consultar unidad de medida.
    **/  
    protected function _consultarUnidadMedida() {

        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT * FROM inv_unidad_medida WHERE uniM_Estado <> 0 ";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "unidad de medida listadas";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existen unidades de medidas";
            $row=[];
        }
        return $row;  
    }

    
    /**
    *** Realiza el proceso de consultar los TipoCantidad 
    *** de Insumos
    **/
    private function _consultarTipoCantidad(){

        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $estado = $_POST['estado'];
        $query = "SELECT * FROM prod_tipo_cantidad where tica_Estado = $estado";

        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "Tipo Cantidad listados";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existen Tipo Cantidad";
            $row = NULL;
        }
        return $row;  
    }

    /**
    *** Realiza el proceso de consultar los Tipo Unidad 
    *** de Insumos
    **/
    private function _consultarTipoUnidad(){

        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $estado = $_POST['estado'];
        $query = "SELECT * FROM prod_tipo_unidad where tiuni_Estado = $estado";

        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "Tipo Unidad listados";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existen Tipo Unidad";
            $row = NULL;
        }
        return $row;  
    }

          /**
    *** Realiza el proceso de consultar max ID
    **/  
    protected function _consultarMxId() {

        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT MAX(ins_Codigo)+1 as id FROM prod_insumos";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "Max ID Productos";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existen ";
            $row = NULL;
        }
        return $row;  
    }
}

class InsumosException extends \Exception{}

    \predial\ControladorInsumo::run();

