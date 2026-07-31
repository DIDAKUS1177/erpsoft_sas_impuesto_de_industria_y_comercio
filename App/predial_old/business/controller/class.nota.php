<?php
namespace predial;

use Exception;

include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_Nota.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER .'/business/controller/class.logs.php';

class ControladorNota extends \predial\Cabecera {

    private $_funcion;
    private $_ok;
    private $_id;
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
                    $respuesta = $_obj->_agregarNota();
                    break;
                case 2:
                    /* $respuesta = $_obj->_editarNota(); */
                    break;
                case 3:
                    $respuesta = $_obj->_consultarNota();
                    break; 
                case 4:
                    $respuesta = $_obj->_inactivarNota();
                    break; 
                case 5:
                    $respuesta = $_obj->_consultarDetalle();
                    break; 
                case 6:
                    $respuesta = $_obj->_consultarDetalleFactura();
                    break; 
                case 7:
                    $respuesta = $_obj->_regularizacionProductos();
                    break;
                case 8:
                    $respuesta = $_obj->_cuentasPorPagasrProveedor();
                    break;
            }
            $con->commit();
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "id" => $_obj->_id, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
            
        } catch (\predial\NotaException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "id" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    /**
    *** Realiza el proceso de Crear Notas.
    **/ 
    protected function _agregarNota() {
        
        try{
            $_objNota = new \predial\DAO_Nota();
            $_objNota->set_kar_Tipo($_POST['kar_Tipo']);
            
            $_objNota->set_Kar_Estado(1);
            $activa=0;
            
            if($_POST['kar_Tipo'] == 1){

                if(isset($_POST['numOrden'])){
                    if (!empty($_POST['numOrden']) || $_POST['numOrden'] != NULL ) {
                        $_objNota->set_kar_NumOrden($_POST['numOrden']);
                    }    
                }
                
                if(isset($_POST['idProveedor'])){
                    if (!empty($_POST['idProveedor']) || $_POST['idProveedor'] != NULL ) {
                        $_objNota->set_kar_IdProveedor($_POST['idProveedor']);
                    }    
                }
                
                if(isset($_POST['tipoPago'])){
                    if (!empty($_POST['tipoPago']) || $_POST['tipoPago'] != NULL ) {
                        $_objNota->set_kar_TipoPago($_POST['tipoPago']);
                        $activa=1;
                    }    
                }

             }

            if(isset($_POST['Observaciones'])){
                if (!empty($_POST['Observaciones']) || $_POST['Observaciones'] != NULL ) {
                    $_objNota->set_kar_Observaciones($_POST['Observaciones']);
                }    
            }

            $detalle = $_POST['detallesNota'];
            if($_POST['kar_Tipo'] == 2){
                $validaCreacion = $this->_validarSalidaDetalle($detalle); 
            }else{
                $validaCreacion = true;
            }

            if($validaCreacion){

                if(!$_objNota->guardar()){
                    $this->_ok = 0;
                    $this->_id = 0;
                    $this->_mensaje = $_objNota->getMysqlError();
                    
                }else{
                    
                    $detalles = json_decode($detalle);

                    foreach($detalles as $d){
                        //Valida los saldos anteriores del producto en la bodega
                        $Sald= 0;
                        $valorUnitario = 0;
                        $saldo = $this->_validarSaldos($d->detkar_IdProducto, $d->detkar_IdBodega);
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
                                    $valorUnitario = $d->detkar_CostoUnitario;

                                    // Se calcula valor total, con base al ultimo valor de entrada unitario.
                                    $valorSaldo = $valorUnitario * $saldoNew;

                                    $cantidadEntrada = $d->detkar_Cantidad;
                                    $valorEntrada = $d->detkar_CostoUnitario * $d->detkar_Cantidad;
                                    $cantidadSaldo = $saldo[$i]['detkar_CantidadSaldo'] + $d->detkar_Cantidad;
                    
                                }else{
                                    $saldoNew = $saldo[$i]['detkar_CantidadSaldo'] - $d->detkar_Cantidad;
                                    
                                    // --- ORIGINAL
                                   //$valorUnitario = $saldo[$i]['detkar_ValorUnitario'];
                                    $valorUnitario = $d->detkar_CostoUnitario;

                                    $cantidadSalida = $d->detkar_Cantidad;
                                    $valorSalida = $valorUnitario * $d->detkar_Cantidad;
                                    $cantidadSaldo = $saldo[$i]['detkar_CantidadSaldo'] - $d->detkar_Cantidad;
                                    $valorSaldo = $saldo[$i]['detkar_ValorSaldo'] - $valorSalida;
                                } 
                            }
                        }else{
                            if($_POST['kar_Tipo'] == 1){
                             
                                $cantidadEntrada = $d->detkar_Cantidad;
                                $valorUnitario = $d->detkar_CostoUnitario;
                                $valorEntrada = floatval($valorUnitario) * floatval($cantidadEntrada);
                                $cantidadSaldo = $d->detkar_Cantidad;
                                $valorSaldo = $valorEntrada;
                                $saldoNew = $d->detkar_Cantidad;

                            }else{
                                $this->_ok = 2;
                                $this->_id = 0;
                                $this->_mensaje = "Sin existencias 1"; 
                                return;
                            }
                        }
                        $idNota = $this->_validarMaximo($_POST['kar_Tipo']); 
                        
                        
                        //Valida si es igual el nombre a alguno de la BD.
                        if($_POST['kar_Tipo'] == 2){
                            $nomRol= $this->_validarExistencias($d->detkar_IdProducto,$d->detkar_IdBodega);
                            $longitud = 0;
                            if($nomRol != NULL && $nomRol != ""){
                                $longitud = count($nomRol);
                            }
                            
                            // 17 - 02 -2021
                            // Se cambia a true para que facture predialmpre sin importar el stock.
                            // $crear=false;
                            $crear=true;
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

                            if($activa == 1){
                                $nombreFacturarr= '';
                                $nombreFacturarr= "'".$d->nombreFacturar."'";
                            }else{
                                $nombreFacturarr= $d->detkar_IdProducto;
                            }                            

                            $this->_insertarDetalles($d->detkar_IdProducto,$nombreFacturarr,$valorUnitario,$cantidadEntrada,$valorEntrada,
                            $cantidadSalida,$valorSalida,$cantidadSaldo,$valorSaldo,$d->detkar_IdBodega,$idNota);
                            $this->_insertarExistencias($d->detkar_IdProducto,$d->detkar_IdBodega,$saldoNew);
                            $this->_ok = 1;
                            $this->_id = $idNota;
                            $this->_mensaje = "Detalle agregado";
                        
                        }else{
                            $this->_ok = 2;
                            $this->_id = 0;
                            $this->_mensaje = "Sin existencias 2";
                        }
                    }
                }
            }else{
                $this->_ok = 2;
                $this->_id = 0;
                $this->_mensaje = "No hay existencias";
            }
            return;
        } catch(Exception $e){
            $this->_ok = 4;
            $this->_id = 0;
            $this->_mensaje = $_objNota->getMysqlError();
            return;
        }    
       
    }


    
    /**
    *** Realiza el proceso de validacion de existencias de todos
    *** los detalles para sabe si al menos un detalle cumple con
    *** las existencias
    **/ 
    protected function _validarSalidaDetalle($detalle) {
        
        try{
            $detalles = json_decode($detalle);
            $crear=false;
            foreach($detalles as $d){

                $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
                $query = "SELECT dt.* FROM inv_detalle_kardex dt 
                        JOIN inv_kardex k ON dt.detkar_IdKardex = kar_Id
                        WHERE dt.detkar_IdBodega = $d->detkar_IdBodega AND dt.detkar_IdProducto = $d->detkar_IdProducto
                        ORDER BY detkar_Id DESC LIMIT 1";
                
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
            }
            
            return $row;

        } catch(Exception $e){
            return false;
        }    
       
    }

    /**
    *** Realiza el proceso de validacion de existencias de todos
    *** los detalles para sabe si al menos un detalle cumple con
    *** las existencias
    **/ 
    protected function _validarSalida($detalle) {
        
        try{
 
            $detalles = json_decode($detalle);
            $crear=false;
            foreach($detalles as $d){
                
                $nomRol= $this->_validarExistencias($d->detkar_IdProducto,$d->detkar_IdBodega);
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
    protected function _validarExistencias($idProducto, $idBodega) {

        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT * FROM inv_existencias WHERE exi_IdBodega = $idBodega AND exi_IdProducto = $idProducto";
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
    protected function _validarSaldos($idProducto, $idBodega) {

        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT dt.* FROM inv_detalle_kardex dt 
                JOIN inv_kardex k ON dt.detkar_IdKardex = kar_Id
                WHERE dt.detkar_IdBodega = $idBodega AND dt.detkar_IdProducto = $idProducto 
                ORDER BY detkar_Id DESC LIMIT 1";
        
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
    protected function _insertarExistencias($idProducto, $idBodega,$cantidad) {

        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT * FROM inv_existencias  WHERE exi_IdBodega = $idBodega AND exi_IdProducto = $idProducto ORDER BY exi_Id DESC LIMIT 1";
        $data = $con->consultar($query);
        if($data != NULL && $data != ""){
            if( $con->getNumeroFilasConsultadas($data) >0 ){ 
                $res = mysqli_fetch_row($data);
                $query = "UPDATE inv_existencias SET exi_Cantidad = $cantidad WHERE exi_IdBodega = $idBodega AND exi_IdProducto = $idProducto";
                $data = $con->consultar($query);
        
            }else{
                $query = "INSERT INTO inv_existencias(exi_IdBodega,exi_IdProducto,exi_Cantidad)VALUES($idBodega,$idProducto,$cantidad)";
                $data = $con->consultar($query);
            }
        }else{
            $query = "INSERT INTO inv_existencias(exi_IdBodega,exi_IdProducto,exi_Cantidad)VALUES($idBodega,$idProducto,$cantidad)";
            $data = $con->consultar($query);
        }    
       
    } 
    
    /**
    *** Realiza el proceso de consultar la nota con el maximo id
    **/ 
    protected function _validarMaximo($tipo) {

        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        
        $query = "SELECT MAX(kar_Id) FROM inv_kardex WHERE kar_Tipo = '$tipo'";
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
    *** Realiza el insertar el detalle de la nota.
    **/ 
    protected function _insertarDetalles($detkar_IdProducto, $detkar_NombreFacturar, $detkar_ValorUnitario,$detkar_CantidadEntrada,
    $detkar_ValorEntrada,$detkar_CantidadSalida,$detkar_ValorSalida,$detkar_CantidadSaldo, $detkar_ValorSaldo,
    $detkar_IdBodega,$detkar_IdKardex) {

        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        
        if($detkar_CantidadSalida == "" && $detkar_ValorSalida == ""){
            $query = "INSERT INTO inv_detalle_kardex(detkar_IdProducto,detkar_NombreFacturar,
                detkar_ValorUnitario,detkar_CantidadEntrada, detkar_ValorEntrada,detkar_CantidadSaldo,
                detkar_ValorSaldo,detkar_IdBodega,detkar_IdKardex)
                VALUES($detkar_IdProducto,$detkar_NombreFacturar,$detkar_ValorUnitario,
                $detkar_CantidadEntrada,$detkar_ValorEntrada,$detkar_CantidadSaldo,$detkar_ValorSaldo,
                $detkar_IdBodega,$detkar_IdKardex)";
        }else{
            $query = "INSERT INTO inv_detalle_kardex(detkar_IdProducto,detkar_NombreFacturar,
                detkar_ValorUnitario,detkar_CantidadSalida,detkar_ValorSalida,detkar_CantidadSaldo,
                detkar_ValorSaldo,detkar_IdBodega,detkar_IdKardex)
                VALUES($detkar_IdProducto,$detkar_NombreFacturar,$detkar_ValorUnitario,
                $detkar_CantidadSalida,$detkar_ValorSalida,$detkar_CantidadSaldo,$detkar_ValorSaldo,
                $detkar_IdBodega,$detkar_IdKardex)";
        }
       
        $data = $con->consultar($query);
       
    }  
   
    
    /**
    *** Realiza el proceso de Activar o Inactivar Roles.
    **/ 
    protected function _inactivarNota() {

        $_objRol = new \predial\DAO_Nota();
        $_objRol->set_kar_Id($_POST['id']);
        $_objRol->set_kar_Estado($_POST['estado']);

        if(!$_objRol->guardar()){
            $this->_ok = 0;
            $this->_id = 0;
            $this->_mensaje = $_objRol->getMysqlError();
        }else{
            $this->_reversarNota($_POST['id']);
            $this->_ok = 1;
            $this->_id = 0;
            $this->_mensaje = "Rol Activado/Inactivado correctamente";
        }
        return $_objRol->getArray();
    }
    
    /**
    *** Realiza el proceso de reversar las acciones de la nota que se esta anulando.
    **/ 
    protected function _reversarNota($idNota) {

        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT * FROM inv_kardex  WHERE kar_Id = $idNota";
        $data = $con->consultar($query);
        if($data != NULL && $data != ""){
            if( $con->getNumeroFilasConsultadas($data) >0 ){ 
                $res = mysqli_fetch_row($data);
                $query2 = "SELECT * FROM inv_existencias  WHERE exi_IdBodega = $res[8] AND exi_IdProducto = $res[1]";
                $data2 = $con->consultar($query2);
                $res2 = mysqli_fetch_row($data2);
                if($res[2] == 1){
                   $cantidad = $res2[3] - $res[3];
                }else{
                     $cantidad = $res2[3] + $res[3];
                }
                
                $query3 = "UPDATE inv_existencias SET exi_Cantidad = $cantidad WHERE exi_IdBodega = $res[8] AND exi_IdProducto = $res[1]";
                $data3 = $con->consultar($query3);
        
            }
        }
    }   
    
    /**
    *** Realiza el proceso de Consultar Nota.
    **/ 
    private function _consultarNota() {
       
        $_objRol = new \predial\DAO_Nota();
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

        if(isset($_POST['tipo'])){
            if (!empty($_POST['tipo']) || $_POST['tipo'] != NULL ) {
                $_objRol->set_Kar_Tipo($_POST['tipo']);
            }    
        }

        if(isset($_POST['fecha'])){
            if (!empty($_POST['fecha']) || $_POST['fecha'] != NULL ) {
                $_objRol->set_kar_Fecha($_POST['fecha']);
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
            $this->_id = 0;
            $this->_mensaje = "Notas listadas con exito";
        }else{
            $R=$_objRol;
            $this->_ok = 0;
            $this->_id = 0;
            $this->_mensaje = "No existen Notas";            
        }
        return $R;
    } 
    
    /**
    *** Realiza el proceso de consultar los detalles 
    *** de las notas
    **/
    private function _consultarDetalle(){
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $idKardex = $_POST['idNota'];
        $query = "SELECT dt.*, p.pro_Nombre, bod_Nombre FROM inv_detalle_kardex dt  
                JOIN inv_producto p ON dt.detkar_IdProducto = p.pro_Id
                JOIN inv_bodega b ON dt.detkar_IdBodega = b.bod_Id  
                WHERE detkar_IdKardex = $idKardex";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_id = 0;
            $this->_mensaje = "detalles listados";
        }else{
            $this->_ok = 0;
            $this->_id = 0;
            $this->_mensaje = "No existen detalles";
            $row = NULL;
        }
        return $row;  
    }



    /**
    *** Realiza el proceso de Regularizar el inventario
    **/
    private function _regularizacionProductos(){

/*
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        
        for ($i = 1; $i <= 10; $i++) {

            $query = "SELECT * FROM inv_detalle_kardex 
                        WHERE detkar_IdProducto = $i
                    ORDER BY detkar_Id DESC limit 1";

            $data = $con->consultar($query);

            if( $con->getNumeroFilasConsultadas($data) >0 ){ 

                while($res = $con->obnerFila($data)){
                    $row[] = $res;
                }

                $this->_insertarDetalles($row[0]['detkar_IdProducto'], $row[0]['detkar_ValorUnitario'],'null', 'null', $row[0]['detkar_CantidadSaldo'], $row[0]['detkar_ValorUnitario'],0,0,1,917); 

            }else{
               
            }

        }

        

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_id = 0;
            $this->_mensaje = "detalles listados";
        }else{
            $this->_ok = 0;
            $this->_id = 0;
            $this->_mensaje = "No existen detalles";
            $row = NULL;
        }
        return $row;  
*/
    }




    /**
    *** Realiza el proceso de consultar los detalles 
    *** de las notas de la factura enviada
    **/
    private function _consultarDetalleFactura(){
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $idDoc = $_POST['idDoc'];
        $query = "SELECT * ,  (select idk.detkar_IdBodega from fac_documento as fd 
                                INNER JOIN inv_detalle_kardex as idk on fd.doc_IdKardex = idk.detkar_IdKardex
                                WHERE fd.doc_Id= fac_detalle_documento.detDoc_IdDocumento LIMIT 1) AS idBodega,
                            (select TRUNCATE(idk.detkar_ValorUnitario,0) 
                                from inv_detalle_kardex as idk where idk.detkar_IdProducto = fac_detalle_documento.detDoc_IdProducto 
                                    and detkar_CantidadEntrada IS NOT NULL ORDER by detkar_Id DESC LIMIT 1) as costoActivo

                            FROM fac_detalle_documento WHERE detDoc_IdDocumento = $idDoc";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_id = 0;
            $this->_mensaje = "detalles listados";
        }else{
            $this->_ok = 0;
            $this->_id = 0;
            $this->_mensaje = "No existen detalles";
            $row = NULL;
        }
        return $row;  
    }

    
    private function _cuentasPorPagasrProveedor(){
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        
        $query = "SELECT (select cse.prov_Nombre from inv_proveedores as cse where cse.prov_Id = ind.kar_IdProveedor) as proveedor,
                SUM(indd.detkar_ValorEntrada) totalFacturas
                from inv_kardex as ind INNER JOIN inv_detalle_kardex AS indd on indd.detkar_IdKardex=ind.kar_Id
                    WHERE ind.kar_Tipo=1 and ind.kar_IdProveedor >= 1 and ind.kar_EstadoPago=0
                    GROUP BY ind.kar_IdProveedor";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_id = 0;
            $this->_mensaje = "detalles listados";
        }else{
            $this->_ok = 0;
            $this->_id = 0;
            $this->_mensaje = "No existen detalles";
            $row = NULL;
        }
        return $row;  
    }
}

class NotaException extends \Exception{}

    \predial\ControladorNota::run();

