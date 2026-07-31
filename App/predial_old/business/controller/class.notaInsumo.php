<?php
namespace predial;

use Exception;

include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_NotaInsumo.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER .'/business/controller/class.logs.php';

class ControladorNotaInsumo extends \predial\Cabecera {

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
                    $respuesta = $_obj->_agregarNotaInsumo();
                    break;
                case 2:
                    /* $respuesta = $_obj->_editarNotaInsumo(); */
                    break;
                case 3:
                    $respuesta = $_obj->_consultarNotaInsumo();
                    break; 
                case 4:
                    $respuesta = $_obj->_inactivarNotaInsumo();
                    break; 
                case 5:
                    $respuesta = $_obj->_consultarDetalle();
                    break; 
                case 6:
                    $respuesta = $_obj->_consultarExistencias();
                    break; 
            }
            $con->commit();
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
            
        } catch (\predial\NotaInsumoException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    /**
    *** Realiza el proceso de Crear Notas Insumo.
    **/ 
    protected function _agregarNotaInsumo() {
        
        try{
            $_objNotaInsumo = new \predial\DAO_NotaInsumo();
            $_objNotaInsumo->set_kar_Tipo($_POST['kar_Tipo']);
            
            $_objNotaInsumo->set_Kar_Estado(1);

            if(isset($_POST['Observaciones'])){
                if (!empty($_POST['Observaciones']) || $_POST['Observaciones'] != NULL ) {
                    $_objNotaInsumo->set_kar_Observaciones($_POST['Observaciones']);
                }    
            }

            $detalle = $_POST['detallesNotaInsumo'];
            if($_POST['kar_Tipo'] == 2){
                $validaCreacion = $this->_validarSalida($detalle,$_POST['kar_Tipo']); 
            }else{
                $validaCreacion = true;
            }

            if($validaCreacion){

                if(!$_objNotaInsumo->guardar()){
                    $this->_ok = 0;
                    $this->_mensaje = $_objNotaInsumo->getMysqlError();
                    
                }else{
                    
                    $detalles = json_decode($detalle);

                    foreach($detalles as $d){
                        //Valida los saldos anteriores del producto en la bodega
                        $Sald= 0;
                        $valorUnitario = 0;
                        $saldo = $this->_validarSaldos($d->detkar_IdInsumo, $d->detkar_IdBodega, $_POST['kar_Tipo']);
                        //var_dump($saldo);
                        if($saldo != NULL && $saldo != ""){
                            $Sald = count($saldo);
                        }
                        $cantidadEntrada = NULL;
                        $valorEntrada = NULL;
                        $cantidadSalida = NULL;
                        $valorSalida = NULL;
                        $valorUnitario = NULL;
                        $cantidadSaldo = NULL;
                        $valorSaldo = NULL;

                        //Valida si es inventario inicial
                        if($Sald > 0){
                            for($i=0; $i<$Sald; $i++){ 
                                if($_POST['kar_Tipo'] == 1){
                                    $saldoNew = $saldo[$i]['detkar_CantidadSaldo'] + $d->detkar_Cantidad;

                                    // --- ORIGINAL
                                    //$valorUnitario = (($saldo[$i]['detkar_ValorSaldo']) + ($d->detkar_Costo * $d->detkar_Cantidad)) / ($saldo[$i]['detkar_CantidadSaldo'] + $d->detkar_Cantidad); 
                                    $valorUnitario = $d->detkar_Costo;

                                    // Se calcula valor total, con base al ultimo valor de entrada unitario.
                                    //$valorSaldo = $valorUnitario * $saldoNew;
                                    $valorSaldo = $d->detkar_Costo;

                                    $cantidadEntrada = $d->detkar_Cantidad;
                                    
                                    //$valorEntrada = $d->detkar_Costo * $d->detkar_Cantidad;
                                    $valorEntrada = $d->detkar_Costo;

                                    //$cantidadSaldo = $saldo[$i]['detkar_CantidadSaldo'] + $d->detkar_Cantidad;
                                    $cantidadSaldo = $d->detkar_Cantidad;
                                }else{
                                    $saldoNew = $saldo[$i]['exi_Cantidad'] - $d->detkar_Cantidad;
                                    
                                    // --- ORIGINAL
                                   //$valorUnitario = $saldo[$i]['detkar_ValorUnitario'];
                                    $valorUnitario = $d->detkar_Costo;

                                    $cantidadSalida = $d->detkar_Cantidad;
                                    
                                    //$valorSalida = $valorUnitario * $d->detkar_Cantidad;
                                    $valorSalida = $d->detkar_Costo;
                                                                        
                                    $cantidadSaldo = $saldo[$i]['exi_Cantidad'] - $d->detkar_Cantidad;
                                    
                                    //$valorSaldo = $saldo[$i]['detkar_ValorSaldo'] - $valorSalida;
                                    $valorSaldo = 0;
                                } 
                            }
                        }else{
                            if($_POST['kar_Tipo'] == 1){
                                $cantidadEntrada = $d->detkar_Cantidad;
                                $valorUnitario = $d->detkar_Costo;

                                //$valorEntrada = floatval($valorUnitario) * floatval($cantidadEntrada);
                                $valorEntrada = $d->detkar_Costo;

                                $cantidadSaldo = $d->detkar_Cantidad;
                                $valorSaldo = $valorEntrada;
                                $saldoNew = $d->detkar_Cantidad;
                            }else{
                                $this->_ok = 2;
                                $this->_mensaje = "Sin existencias 1"; 
                            }
                        }
                        $idNotaInsumo = $this->_validarMaximo($_POST['kar_Tipo']); 
                        
                        
                        //Valida si es igual el nombre a alguno de la BD.
                        if($_POST['kar_Tipo'] == 2){
                            $nomRol= $this->_validarExistencias($d->detkar_IdInsumo,$d->detkar_IdBodega,2);
                            $longitud = 0;
                            if($nomRol != NULL && $nomRol != ""){
                                $longitud = count($nomRol);
                            }
                            
                            $crear=false;
                            for($i=0; $i<$longitud; $i++){  
                                if($nomRol[$i]['exi_Cantidad'] >= $d->detkar_Cantidad){
                                    $crear=true;
                                    break;
                                }
                            }
                        }else{
                            $crear=true;
                        }

                        if($crear){
                            $this->_insertarDetalles($d->detkar_IdInsumo,$valorUnitario,$cantidadEntrada,$valorEntrada,
                            $cantidadSalida,$valorSalida,$cantidadSaldo,$valorSaldo,$d->detkar_IdBodega,$idNotaInsumo, $_POST['kar_Tipo']);

                            if($_POST['kar_Tipo'] == 1){
                                $canti = $cantidadEntrada;
                            }else{
                                $canti = $cantidadSaldo;
                            }                          

                            $this->_insertarExistencias($d->detkar_IdInsumo,$d->detkar_IdBodega,$canti,$_POST['kar_Tipo']);
                            $this->_ok = 1;
                            $this->_mensaje = "Detalle agergado";
                        
                        }else{
                            $this->_ok = 2;
                            $this->_mensaje = "Sin existencias 2";
                        }
                    }
                }
            }else{
                $this->_ok = 3;
                $this->_mensaje = "Ningún detalle tiene existencias para realizar una salida";
            }
            return;
        } catch(Exception $e){
            $this->_ok = 4;
            $this->_mensaje = $_objNotaInsumo->getMysqlError();
            return;
        }    
       
    }

    /**
    *** Realiza el proceso de validacion de existencias de todos
    *** los detalles para sabe si al menos un detalle cumple con
    *** las existencias
    **/ 
    protected function _validarSalida($detalle, $IdTipoKardex) {
        
        try{
 
            $detalles = json_decode($detalle);
            $crear=false;
            foreach($detalles as $d){
                
                $nomRol= $this->_validarExistencias($d->detkar_IdInsumo,$d->detkar_IdBodega, $IdTipoKardex);
                $longitud = 0;
                if($nomRol != NULL && $nomRol != ""){
                    $longitud = count($nomRol);
                }
                
                for($i=0; $i<$longitud; $i++){
                    if($nomRol[$i]['exi_Cantidad'] >= $d->detkar_Cantidad){
                        $crear=true;
                        break;
                    }
                }
                
            }
            
            return $crear;

        } catch(Exception $e){
            return false;
        }    
       
    }

    /**
    *** Realiza el proceso de validar las existencias del producto.
    **/ 
    protected function _validarExistencias($idProducto, $idBodega, $IdTipoKardex) {

        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();

        if($IdTipoKardex == 1){
            $query = "SELECT * FROM prod_existencias WHERE exi_IdBodega = $idBodega AND exi_IdProducto = $idProducto";
        }else{
            $query = "SELECT * FROM prod_existencias WHERE exi_Id = $idProducto";
        }
               
        $data = $con->consultar($query);
        if($data != NULL && $data != ""){
            if( $con->getNumeroFilasConsultadas($data) >0 ){ 
                while($res = $con->obnerFila($data)){
                    $row[] = $res;
                }
         
            }else{
    
                $row = NULL;
            }
        }else{
            $row = NULL;
        }

        
        return $row;     
    }
    
    /**
    *** Realiza el proceso de validar las existencias del producto.
    **/ 
    protected function _validarSaldos($idProducto, $idBodega, $IdTipoKardex) {

        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        
        if($IdTipoKardex == 1){
            $query = "SELECT dt.* FROM prod_detalle_kardex dt 
            JOIN prod_kardex k ON dt.detkar_IdKardex = kar_Id
            WHERE dt.detkar_IdBodega = $idBodega AND dt.detkar_IdProducto = $idProducto 
            ORDER BY detkar_Id DESC LIMIT 1";
        }else{
            $query = "SELECT dt.* FROM prod_existencias dt 
            WHERE dt.exi_Id = $idProducto ";
        }
  
        
        $data = $con->consultar($query);
        if($data != NULL && $data != ""){
            if( $con->getNumeroFilasConsultadas($data) >0 ){ 
                while($res = $con->obnerFila($data)){
                    $row[] = $res;
                }
            }else{
                $row = NULL;
            }
        }else{
            $row = NULL;
        }    
        return $row;     
    }   

    /**
    *** Realiza el proceso de sumar o restar las nuevas existencias del producto.
    **/ 
    protected function _insertarExistencias($idProducto, $idBodega,$cantidad, $IdTipoKardex) {

        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();

        if($IdTipoKardex == 1){
            $query = "SELECT * FROM prod_existencias  WHERE exi_IdBodega = $idBodega AND exi_IdProducto = $idProducto ORDER BY exi_Id DESC LIMIT 1";
            $data = $con->consultar($query);
            if($data != NULL && $data != ""){
                if( $con->getNumeroFilasConsultadas($data) >0 ){ 
                    $res = mysqli_fetch_row($data);
                    $query = "INSERT INTO prod_existencias(exi_IdBodega,exi_IdProducto,exi_Cantidad)VALUES($idBodega,$idProducto,$cantidad)";
                    $data = $con->consultar($query);
                    //$query = "UPDATE prod_existencias SET exi_Cantidad = $cantidad WHERE exi_IdBodega = $idBodega AND exi_IdProducto = $idProducto";
                    //$data = $con->consultar($query);
            
                }else{
                    $query = "INSERT INTO prod_existencias(exi_IdBodega,exi_IdProducto,exi_Cantidad)VALUES($idBodega,$idProducto,$cantidad)";
                    $data = $con->consultar($query);
                }
            }else{
                $query = "INSERT INTO prod_existencias(exi_IdBodega,exi_IdProducto,exi_Cantidad)VALUES($idBodega,$idProducto,$cantidad)";
                $data = $con->consultar($query);
            }   
        }else{
            $query = "SELECT * FROM prod_existencias  WHERE exi_Id = $idProducto";

            $data = $con->consultar($query);
            if($data != NULL && $data != ""){
                if( $con->getNumeroFilasConsultadas($data) >0 ){ 
                    $res = mysqli_fetch_row($data);
                    $query = "UPDATE prod_existencias SET exi_Cantidad = $cantidad WHERE  exi_Id = $idProducto";
                    $data = $con->consultar($query);
            
                }
                //else{
                //    $query = "INSERT INTO prod_existencias(exi_IdBodega,exi_IdProducto,exi_Cantidad)VALUES($idBodega,$idProducto,$cantidad)";
                //    $data = $con->consultar($query);
                //}
            }
            //else{
            //    $query = "INSERT INTO prod_existencias(exi_IdBodega,exi_IdProducto,exi_Cantidad)VALUES($idBodega,$idProducto,$cantidad)";
            //    $data = $con->consultar($query);
            //}  
        }
        
          
       
    } 
    
    /**
    *** Realiza el proceso de consultar la nota Insumo con el maximo id
    **/ 
    protected function _validarMaximo($tipo) {

        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        
        $query = "SELECT MAX(kar_Id) FROM prod_kardex WHERE kar_Tipo = '$tipo'";
        $data = $con->consultar($query);
        $cont = mysqli_num_rows($data);
        
        if($cont > 0){
            $id = mysqli_fetch_row($data);
        }else{
            $id[0] = 0;
        }
        
        return $id[0];
    } 
    
    /**
    *** Realiza el insertar el detalle de la nota Insumo.
    **/ 
    protected function _insertarDetalles($detkar_IdProducto, $detkar_ValorUnitario,$detkar_CantidadEntrada,
    $detkar_ValorEntrada,$detkar_CantidadSalida,$detkar_ValorSalida,$detkar_CantidadSaldo, $detkar_ValorSaldo,
    $detkar_IdBodega,$detkar_IdKardex, $tipoKar) {

        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        
        if( $tipoKar == 1){
            if($detkar_CantidadSalida == "" && $detkar_ValorSalida == ""){
                $query = "INSERT INTO prod_detalle_kardex(detkar_IdProducto,detkar_ValorUnitario,detkar_CantidadEntrada,
                    detkar_ValorEntrada,detkar_CantidadSaldo,detkar_ValorSaldo,
                    detkar_IdBodega,detkar_IdKardex)
                    VALUES($detkar_IdProducto,$detkar_ValorUnitario,$detkar_CantidadEntrada,$detkar_ValorEntrada,
                    $detkar_CantidadSaldo,$detkar_ValorSaldo,$detkar_IdBodega,$detkar_IdKardex)";
            }else{
                $query = "INSERT INTO prod_detalle_kardex(detkar_IdProducto,detkar_ValorUnitario,detkar_CantidadSalida,
                    detkar_ValorSalida,detkar_CantidadSaldo,detkar_ValorSaldo,
                    detkar_IdBodega,detkar_IdKardex)
                    VALUES($detkar_IdProducto,$detkar_ValorUnitario,$detkar_CantidadSalida,$detkar_ValorSalida,
                    $detkar_CantidadSaldo,$detkar_ValorSaldo,$detkar_IdBodega,$detkar_IdKardex)";
            }
        }else{
        
            $queryy = "SELECT exi_IdProducto FROM prod_existencias WHERE exi_Id=$detkar_IdProducto";
            $dataa = $con->consultar($queryy);
            $cont = mysqli_num_rows($dataa);
        
            if($cont > 0){
                $idCar = floatval(mysqli_fetch_row($dataa));
            }else{
                $idCar[0] = 0;
            }

            if($detkar_CantidadSalida == "" && $detkar_ValorSalida == ""){
                $query = "INSERT INTO prod_detalle_kardex(detkar_IdProducto,detkar_ValorUnitario,detkar_CantidadEntrada,
                    detkar_ValorEntrada,detkar_CantidadSaldo,detkar_ValorSaldo,
                    detkar_IdBodega,detkar_IdKardex)
                    VALUES($idCar,$detkar_ValorUnitario,$detkar_CantidadEntrada,$detkar_ValorEntrada,
                    $detkar_CantidadSaldo,$detkar_ValorSaldo,$detkar_IdBodega,$detkar_IdKardex)";
            }else{
                $query = "INSERT INTO prod_detalle_kardex(detkar_IdProducto,detkar_ValorUnitario,detkar_CantidadSalida,
                    detkar_ValorSalida,detkar_CantidadSaldo,detkar_ValorSaldo,
                    detkar_IdBodega,detkar_IdKardex)
                    VALUES($idCar,$detkar_ValorUnitario,$detkar_CantidadSalida,$detkar_ValorSalida,
                    $detkar_CantidadSaldo,$detkar_ValorSaldo,$detkar_IdBodega,$detkar_IdKardex)";
            }

        }
       
        $data = $con->consultar($query);
       
    }  
   
    
    /**
    *** Realiza el proceso de Activar o Inactivar Roles.
    **/ 
    protected function _inactivarNotaInsumo() {

        $_objRol = new \predial\DAO_NotaInsumo();
        $_objRol->set_kar_Id($_POST['id']);
        $_objRol->set_kar_Estado($_POST['estado']);

        if(!$_objRol->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objRol->getMysqlError();
        }else{
            $this->_reversarNotaInsumo($_POST['id']);
            $this->_ok = 1;
            $this->_mensaje = "Rol Activado/Inactivado correctamente";
        }
        return $_objRol->getArray();
    }
    
    /**
    *** Realiza el proceso de reversar las acciones de la nota Insumo que se esta anulando.
    **/ 
    protected function _reversarNotaInsumo($idNotaInsumo) {

        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT * FROM prod_kardex  WHERE kar_Id = $idNotaInsumo";
        $data = $con->consultar($query);
        if($data != NULL && $data != ""){
            if( $con->getNumeroFilasConsultadas($data) >0 ){ 
                $res = mysqli_fetch_row($data);
                $query2 = "SELECT * FROM prod_existencias  WHERE exi_IdBodega = $res[8] AND exi_IdProducto = $res[1]";
                $data2 = $con->consultar($query2);
                $res2 = mysqli_fetch_row($data2);
                if($res[2] == 1){
                   $cantidad = $res2[3] - $res[3];
                }else{
                     $cantidad = $res2[3] + $res[3];
                }
                
                $query3 = "UPDATE prod_existencias SET exi_Cantidad = $cantidad WHERE exi_IdBodega = $res[8] AND exi_IdProducto = $res[1]";
                $data3 = $con->consultar($query3);
        
            }
        }
    }   
    
    /**
    *** Realiza el proceso de Consultar Nota Insumo.
    **/ 
    private function _consultarNotaInsumo() {
       
        $_objRol = new \predial\DAO_NotaInsumo();
        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objRol->set_kar_Id($_POST['id']);
            }    
        }
        
        if(isset($_POST['estado'])){
            if (!empty($_POST['estado']) || $_POST['estado'] != NULL ) {
                $_objRol->set_Kar_Estado($_POST['estado']);
            }    
        }
        
        $_objRol->habilita1ResultadoEnArray();
        $arrRol = $_objRol->consultar();
       
        if(is_array($arrRol) && count($arrRol)){
            $R = [];
            foreach($arrRol as $obj){
                $R[] = $obj->getArray();
            }
            $this->_ok = 1;
            $this->_mensaje = "Notas Insumo listadas con exito";
        }else{
            $R=$_objRol;
            $this->_ok = 0;
            $this->_mensaje = "No existen Notas Insumo";            
        }
        return $R;
    } 
    
    /**
    *** Realiza el proceso de consultar los detalles 
    *** de las notas Insumo
    **/
    private function _consultarDetalle(){
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $idKardex = $_POST['idNotaInsumo'];
        $idtipoKa = $_POST['idTipoKardex'];

        if($idtipoKa == 1){
            $query = "SELECT dt.*, p.ins_Nombre, bod_Nombre FROM prod_detalle_kardex dt  
            JOIN prod_insumos p ON dt.detkar_IdProducto = p.ins_Id
            JOIN inv_bodega b ON dt.detkar_IdBodega = b.bod_Id  
            WHERE detkar_IdKardex = $idKardex";
        }else{
            $query = "SELECT DISTINCT(dt.detkar_IdProducto), dt.*, pp.ins_Nombre, bod_Nombre FROM prod_detalle_kardex dt  
            JOIN prod_existencias p ON dt.detkar_IdProducto = p.exi_IdProducto
            JOIN prod_insumos pp ON p.exi_IdProducto = pp.ins_Id
            JOIN inv_bodega b ON dt.detkar_IdBodega = b.bod_Id  
            WHERE detkar_IdKardex = $idKardex";
        }
                
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "detalles listados";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existen detalles";
            $row = NULL;
        }
        return $row;  
    }

    /**
    *** Realiza el proceso de consultar los Existencias 
    *** de Insumos
    **/
    private function _consultarExistencias(){
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        //$idKardex = $_POST['idNotaInsumo'];
        $query = "SELECT dt.*, p.ins_Nombre as 'strNombreInsumo', 
                tu.tiuni_Abreviatura as 'strNombreTipoUnidad', p.ins_Id FROM prod_existencias dt  
                JOIN prod_insumos p ON dt.exi_IdProducto = p.ins_Id
                JOIN prod_tipo_unidad tu ON p.ins_IdTipoUnidad = tu.tiuni_Id
                where dt.exi_Cantidad > 0";

        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "detalles listados";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existen detalles";
            $row = NULL;
        }
        return $row;  
    }

}

class NotaInsumoException extends \Exception{}

    \predial\ControladorNotaInsumo::run();

