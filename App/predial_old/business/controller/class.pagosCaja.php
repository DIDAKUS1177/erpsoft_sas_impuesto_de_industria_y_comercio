<?php
namespace predial;
include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_PagosCaja.php';
include_once SERVER . '/business/DAO/DAO_CuentasContables.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER .'/business/controller/class.logs.php';



class ControladorPagosCaja extends \predial\Cabecera {

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
                    $respuesta = $_obj->_agregarPagosCaja();
                    break;
                case 2:
                    $respuesta = $_obj->_editarPagosCaja();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarPagosCaja();
                    break; 
                case 4:
                    $respuesta = $_obj->_cerrarPagosCaja();
                    break; 
            }
            $con->commit();
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
            
        } catch (\predial\PagosCajaException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    /**
    * _agregarPagosCaja: Método que realiza el proceso de Crear PagosCaja.
    */ 
    protected function _agregarPagosCaja() {
        
        $_objPagosCaja = new \predial\DAO_PagosCaja();
        $_objPagosCaja->set_paca_IdCaja($_POST['paca_IdCaja']);
        $_objPagosCaja->set_paca_IdVendedor($_POST['paca_IdVendedor']);
        $_objPagosCaja->set_paca_IdTipoPago($_POST['paca_IdTipoPago']);
        $_objPagosCaja->set_paca_Valor($_POST['paca_Valor']);      

        if(isset($_POST['paca_IdSubTipoPago'])){
            if (!empty($_POST['paca_IdSubTipoPago']) || $_POST['paca_IdSubTipoPago'] != NULL ) {
                $_objPagosCaja->set_paca_IdSubTipoPago($_POST['paca_IdSubTipoPago']);
            }   
        }

        if(isset($_POST['paca_Observaciones'])){
            if (!empty($_POST['paca_Observaciones']) || $_POST['paca_Observaciones'] != NULL ) {
                $_objPagosCaja->set_paca_Observaciones($_POST['paca_Observaciones']);
            }   
        }

        $_objPagosCaja->set_paca_Cierre(0);
        //$_objPagosCaja->set_paca_IdCierre($_POST['bace_IdCierre']);

        if($_POST['paca_IdTipoPago'] == 4){
            if(isset($_POST['cuentaTransferir'])){
                if (!empty($_POST['cuentaTransferir']) || $_POST['cuentaTransferir'] != NULL ) {

                    $observacion = 'Translado Dinero: '.$_POST['paca_Observaciones'] ;
                    $idMoviCuenta = $this->_agregarCuentasContables($_POST['paca_IdCaja'], $_POST['cuentaTransferir'], $_POST['paca_Valor'], $observacion);
                }      
            }
        }
        
        //Valida si es igual el nombre a alguno de la BD.
        $nomPagosCaja= $this->_listarPagosCaja(0);
        $longitud = count($nomPagosCaja);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomPagosCaja[$i]['paca_IdCaja'] == $_objPagosCaja->get_paca_IdCaja()){
                if($nomPagosCaja[$i]['paca_Cierre'] == 0){
                   // $nomduplicado=1;
                    break;   
                }
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Existe una pago sin cierre en la caja.';
            $return= false; 
        }else {
            if(!$_objPagosCaja->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objPagosCaja->getMysqlError();
            }else{
                $id = $_objPagosCaja->get_paca_Id();
                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,2);
                $this->_ok = 1;
                $this->_mensaje = "PagosCaja ingresados correctamente";
            }
            $return= $_objPagosCaja->guardar();
        }
        return $return;
    }

    /**
    * _agregarFormasPago: Método que realiza el proceso de Crear FormasPagoes.
    */ 
    protected function _agregarCuentasContables($idCaja, $idCuentaContable, $valor, $observacion) {
        
        $_objCuentasContables = new \predial\DAO_CuentasContables();
        $_objCuentasContables->set_cuco_IdCaja($idCaja);
        $_objCuentasContables->set_cuco_IdTipoMovimiento(1);
        $_objCuentasContables->set_cuco_IdCuentaContable($idCuentaContable);
        $_objCuentasContables->set_cuco_IdTipoSalida(0);
        $_objCuentasContables->set_cuco_IdDocumento(0);
        $_objCuentasContables->set_cuco_Valor($valor);
        $_objCuentasContables->set_cuco_Observacion($observacion);
        $_objCuentasContables->set_cuco_Estado(1);

        if(!$_objCuentasContables->guardar()){
            $retur= 0;
        }else{
            $retur= $_objCuentasContables->get_cuco_Id();
        }
        return $retur;
       
    }
       
    /**
    * _editarPagosCaja: Método que realiza el proceso de Editar PagosCaja.
    */ 
    protected function _editarPagosCaja() {

        $_objPagosCaja = new \predial\DAO_PagosCaja();
        $_objPagosCaja->set_paca_Id($_POST['id']);
        $_objPagosCaja->set_paca_IdCaja($_POST['paca_IdCaja']);
        $_objPagosCaja->set_paca_IdVendedor($_POST['paca_IdVendedor']);
        $_objPagosCaja->set_paca_IdTipoPago($_POST['paca_IdTipoPago']);
        $_objPagosCaja->set_paca_Valor($_POST['paca_Valor']);      

        if(isset($_POST['paca_Observaciones'])){
            if (!empty($_POST['paca_Observaciones']) || $_POST['paca_Observaciones'] != NULL ) {
                $_objPagosCaja->set_paca_Observaciones($_POST['paca_Observaciones']);
            }    
        }

        if(isset($_POST['paca_IdSubTipoPago'])){
            if (!empty($_POST['paca_IdSubTipoPago']) || $_POST['paca_IdSubTipoPago'] != NULL ) {
                $_objPagosCaja->set_paca_IdSubTipoPago($_POST['paca_IdSubTipoPago']);
            }   
        }

        if($_POST['paca_IdTipoPago'] == 4){
            if(isset($_POST['cuentaTransferir'])){
                if (!empty($_POST['cuentaTransferir']) || $_POST['cuentaTransferir'] != NULL ) {

                    $observacion = 'Translado Dinero: '.$_POST['paca_Observaciones'] ;
                    $idMoviCuenta = $this->_agregarCuentasContables($_POST['paca_IdCaja'], $_POST['cuentaTransferir'], $_POST['paca_Valor'], $observacion);
                }      
            }
        }
        
        $_objPagosCaja->set_paca_Cierre(0); 

        //Valida si es igual el nombre a alguno de la BD.
        $nomPagosCaja= $this->_listarPagosCaja($_objPagosCaja->get_paca_Id());
        $longitud = count($nomPagosCaja);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomPagosCaja[$i]['paca_IdCaja'] == $_objPagosCaja->get_paca_IdCaja()){
                if($nomPagosCaja[$i]['paca_Cierre'] == 0){
                   // $nomduplicado=1;
                    break;   
                }
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Existe una pago sin cierre en la caja.';
            $return= false; 
        }else {
            if(!$_objPagosCaja->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objPagosCaja->getMysqlError();
            }else{
                $id = $_objPagosCaja->get_paca_Id();
                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,3);
                $this->_ok = 1;
                $this->_mensaje = "PagosCaja editado correctamente";
            }
            $return= $_objPagosCaja->guardar();
        }
        return $return;
    }
    
    /**
    * _listarPagosCajaes: Método que realiza el proceso 
    * de Listar PagosCaja, exeptuando el PagosCaja enviado por parametro.
    * @param type $id_PagosCaja: llave primaria de la tabla PagosCaja
    */  
    private function _listarPagosCaja($id_PagosCaja) {
       
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT * FROM fac_pagos_caja WHERE paca_Id <> $id_PagosCaja ";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "Pagos Caja listados";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existen Pagos Caja";
            $row=[];
        }
        return $row;     
    }  
    
    /**
    * _cerrarPagosCaja: Método que realiza el proceso de 
    * cerrar PagosCaja.
    */ 
    protected function _cerrarPagosCaja() {

        $_objPagosCaja = new \predial\DAO_PagosCaja();
        $_objPagosCaja->set_paca_Id($_POST['id']);
        $_objPagosCaja->set_paca_Cierre(1);
        $_objPagosCaja->set_paca_IdCierre($_POST['paca_IdCierre']);

        if(!$_objPagosCaja->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objPagosCaja->getMysqlError();
        }else{
            $id = $_objPagosCaja->get_paca_Id();
            //$_objlogs = new logs();
            //$_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,4);
            $this->_ok = 1;
            $this->_mensaje = "PagosCaja Cerrada correctamente";
        }
        return $_objPagosCaja->getArray();
    }
    
    /**
    * _consultarPagosCaja: Método que realiza el proceso de Consultar PagosCaja.
    */ 
    private function _consultarPagosCaja() {
       
        $_objPagosCaja = new \predial\DAO_PagosCaja();
        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objPagosCaja->set_paca_Id($_POST['id']);
            }    
        }

        if(isset($_POST['paca_IdCaja'])){
            if (!empty($_POST['paca_IdCaja']) || $_POST['paca_IdCaja'] != NULL ) {
                $_objPagosCaja->set_paca_IdCaja($_POST['paca_IdCaja']);
            }
        }
        
        if(isset($_POST['paca_Cierre'])){
            if (!empty($_POST['paca_Cierre']) || $_POST['paca_Cierre'] != NULL ) {
                $_objPagosCaja->set_paca_Cierre($_POST['paca_Cierre']);
            }    
        }

        if(isset($_POST['paca_IdTipoPago'])){
            if (!empty($_POST['paca_IdTipoPago']) || $_POST['paca_IdTipoPago'] != NULL ) {
                $_objPagosCaja->set_paca_IdTipoPago($_POST['paca_IdTipoPago']);
            }    
        }
        
        if(isset($_POST['paca_IdSubTipoPago'])){
            if (!empty($_POST['paca_IdSubTipoPago']) || $_POST['paca_IdSubTipoPago'] != NULL ) {
                $_objPagosCaja->set_paca_IdSubTipoPago($_POST['paca_IdSubTipoPago']);
            }   
        }

        if(isset($_POST['idRol']) and ($_POST['idRol']!=1) ){
            if(isset($_POST['paca_IdVendedor'])){
                if (!empty($_POST['paca_IdVendedor']) || $_POST['paca_IdVendedor'] != NULL ) {
                    $_objPagosCaja->set_paca_IdVendedor($_POST['paca_IdVendedor']);
                }    
            }
        }

        $_objPagosCaja->habilita1ResultadoEnArray();
        $arrPagosCaja = $_objPagosCaja->consultar();
       
        if(is_array($arrPagosCaja) && count($arrPagosCaja)){
            $R = [];
            foreach($arrPagosCaja as $obj){
                $R[] = $obj->getArray();
            }
            $this->_ok = 1;
            $this->_mensaje = "PagosCaja listados con exito";
        }else{
            $R=$_objPagosCaja;
            $this->_ok = 0;
            $this->_mensaje = "No existen PagosCaja";            
        }
        return $R;
    }  
}

class PagosCajaException extends \Exception{}

    \predial\ControladorPagosCaja::run();

