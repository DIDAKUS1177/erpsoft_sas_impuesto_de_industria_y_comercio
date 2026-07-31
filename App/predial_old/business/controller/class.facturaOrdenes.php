<?php
namespace predial;

use Exception;

include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_FacturaDocumentoOrdenes.php';
include_once SERVER . '/business/DAO/DAO_FacturaDetalleDocumentoOrdenes.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER .'/business/controller/class.logs.php';

class ControladorFacturaOrdenes extends \predial\Cabecera {

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
                    $respuesta = $_obj->_agregarFacturaDocumento();
                    break;
                case 2:
                    $respuesta = $_obj->_editarFacturaDocumento();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarFacturasOrdenes();
                    break; 
                case 4:
                    $respuesta = $_obj->_AnularFacturaOrdenes();
                    break; 
                case 5:
                    //$respuesta = $_obj->_consultarDetalle();
                    break; 
                case 6:
                    $respuesta = $_obj->_consultarResoluciones();
                    break; 
                case 7:
                    $respuesta = $_obj->_consultarFormasPago();
                    break; 
                case 8:
                    $respuesta = $_obj->_InformeExcel();
                    break; 
                case 9:
                    $respuesta = $_obj->_consultarResolucionesRemision();
                    break; 
                case 10:
                    $respuesta = $_obj->_consultarFacturasSinCierre();
                    break; 
                case 11:
                    $respuesta = $_obj->_consultarDetalleOrden();
                    break; 
                case 12:
                    $respuesta = $_obj->_consultarProductosOrden();
                    break;
                    
            }
            $con->commit();
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
            
        } catch (\predial\FacturaOrdenesException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    /**
    *** Realiza el proceso de Crear Factura Documento Ordenes.
    **/ 
    
    protected function _agregarFacturaDocumento() {
        
        try{

            $_objFacturaDocumento = new \predial\DAO_FacturaDocumentoOrdenes();

            $_objFacturaDocumento->set_doc_Numero($_POST['doc_Numero']);
            $_objFacturaDocumento->set_doc_IdSede($_POST['doc_IdSede']);
            $_objFacturaDocumento->set_doc_IdMesa($_POST['doc_IdMesa']);            
            $_objFacturaDocumento->set_doc_IdVendedor($_POST['doc_IdVendedor']);
            
            if(isset($_POST['doc_Observaciones'])){
                if (!empty($_POST['doc_Observaciones']) || $_POST['doc_Observaciones'] != NULL ) {
                    $_objFacturaDocumento->set_doc_Observaciones($_POST['doc_Observaciones']);
                }    
            }
            
            $_objFacturaDocumento->set_doc_ValorNeto($_POST['doc_ValorNeto']);
            $_objFacturaDocumento->set_doc_Estado(1);            

            //Detalle de los productos de la Factura
            $detalle = $_POST['detallesNota'];

                if(!$_objFacturaDocumento->guardar()){
                    $this->_ok = 0;
                    $this->_mensaje = $_objFacturaDocumento->getMysqlError();
                }else{
                    // Maximo ID FACTURA DOCUMENTO ORDENES
                    $idFacturaDoc = $this->_validarMaximoFacturaDoc(); 
     
                    // INSERTAR el detalle de los productos de la Factura                    
                    $detalles = json_decode($detalle);

                    foreach($detalles as $d){
                        $this->_insertarDetalleFactura($idFacturaDoc, 
                            $d->detkar_IdProducto, $d->detkar_Cantidad, 
                            $d->detkar_CostoUnitario, $d->detkar_CostoText);
                    }
                    

                    $this->_ok = 1;
                    $this->_mensaje = "Orden Creada Exitosamente";
                    $return= $idFacturaDoc;

                }
            return $return;
        } catch(Exception $e){
            $this->_ok = 2;
            $this->_mensaje = $_objFacturaDocumento->getMysqlError();
            return $return;
        } 
       
    } 


       /**
    *** Realiza el proceso de Editar Factura DocumentoOrden.
    **/ 
    
    protected function _editarFacturaDocumento() {
        
        try{

            $return=0;
            $_objFacturaDocumento = new \predial\DAO_FacturaDocumentoOrdenes();
            
            $_objFacturaDocumento->set_doc_Id($_POST['doc_Id']);

            
            $_objFacturaDocumento->set_doc_Numero($_POST['doc_Numero']);
            $_objFacturaDocumento->set_doc_IdSede($_POST['doc_IdSede']);
            $_objFacturaDocumento->set_doc_IdMesa($_POST['doc_IdMesa']);            
            $_objFacturaDocumento->set_doc_IdVendedor($_POST['doc_IdVendedor']);
            
            if(isset($_POST['doc_Observaciones'])){
                if (!empty($_POST['doc_Observaciones']) || $_POST['doc_Observaciones'] != NULL ) {
                    $_objFacturaDocumento->set_doc_Observaciones($_POST['doc_Observaciones']);
                }    
            }
            
            $_objFacturaDocumento->set_doc_ValorNeto($_POST['doc_ValorNeto']);

            //Detalle de los productos de la Factura
            $detalle = $_POST['detallesNota'];

                if(!$_objFacturaDocumento->guardar()){
                    $this->_ok = 0;
                    $this->_mensaje = $_objFacturaDocumento->getMysqlError();
                }else{
                    // Maximo ID FACTURA DOCUMENTO
                    //$idFacturaDoc = $this->_validarMaximoFacturaDoc(); 
                    // Forma de pago UNICA
                    //$this->_insertarFormasPagoEditar($_POST['idFormaPago'], $_POST['doc_Id'], $_POST['doc_IdSerieCaja'],1, $_POST['doc_ValorNeto'], $_POST['doc_IdFormaPago']);
/*
                    // Guardar Formas de Pago multiples
                        //Detalle de las formas de pago de la Factura
                        $formasPago = $_POST['detallesPagos'];

                    $forPago = json_decode($formasPago);
                    foreach($forPago as $p){
                        $this->_insertarFormasPago($idFacturaDoc, $p->teso_IdCaja, 
                            $p->teso_Pocision, $p->teso_Importe, $p->teso_IdFormaPago);
                    }
*/
//_____________________________________________________________________________________________

                    // INSERTAR el detalle de los productos de la Factura                    
                    $detalles = json_decode($detalle);

                    // Eliminar Delattes anteriores de la actualización 
                    $this->_eliminarDetalleFactura($_POST['doc_Id']);

                    foreach($detalles as $d){
                        $this->_insertarDetalleFactura($_POST['doc_Id'], 
                            $d->detkar_IdProducto, $d->detkar_Cantidad, 
                            $d->detkar_CostoUnitario, $d->detkar_CostoText);
                    }

                    //Consultar datos crear Preforma
                    //$resulDatos=$this->_plantillaConfig($_POST['doc_Id']);

                    $this->_ok = 1;
                    $this->_mensaje = "Orden Editada Exitosamente";
                    $return= $_POST['doc_Id'];

                }
            return $return;
        } catch(Exception $e){
            $this->_ok = 2;
            $this->_mensaje = $_objFacturaDocumento->getMysqlError();
            return $return;
        } 
       
    }


    /**
    *** Realiza el proceso de sumar o restar las nuevas existencias del producto.
    **/ 
    protected function _eliminarDetalleFactura($idPreforma) {

        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "DELETE FROM `fac_detalle_documento_ordenes` WHERE detDoc_IdDocumento = $idPreforma";
        $data = $con->consultar($query);
    } 
    

    /**
    *** Realiza el proceso de Insertar el detalle de la factura Ordenes.
    **/ 
    
    protected function _insertarDetalleFactura($idFacturaDoc, $detDoc_IdProducto, $detDoc_Cantidad, 
                                    $detDoc_ValorUnitario, $detDoc_ValorTotal) {

        $_objTDetalleFac = new \predial\DAO_FacturaDetalleDocumentoOrdenes();
        $_objTDetalleFac->set_detDoc_IdDocumento($idFacturaDoc);
        $_objTDetalleFac->set_detDoc_IdProducto($detDoc_IdProducto);
        $_objTDetalleFac->set_detDoc_Cantidad($detDoc_Cantidad);
        $_objTDetalleFac->set_detDoc_ValorUnitario($detDoc_ValorUnitario);
        $_objTDetalleFac->set_detDoc_ValorTotal($detDoc_ValorTotal);
        
        if(!$_objTDetalleFac->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objRol->getMysqlError();
        }else{
            $id = $_objTDetalleFac->get_detDoc_Id();
            $this->_ok = 1;
            $this->_mensaje = "Detalle facrura creada";
        }
        return $_objTDetalleFac->getArray();
    }
  


    /**
    *** Realiza el proceso de consultar el Documento de Factura Ordenes.
    **/ 
    
    protected function _validarMaximoFacturaDoc() {

        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        
        $query = "SELECT MAX(doc_Id) FROM fac_documento_Ordenes";
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
    *** Realiza el proceso de Anular Factura.
    **/ 
    protected function _AnularFacturaOrdenes() {

        $_objRol = new \predial\DAO_FacturaDocumentoOrdenes();
        $_objRol->set_doc_Id($_POST['id']);
        $_objRol->set_doc_MotivoAnulacion($_POST['motivo']);
        $_objRol->set_doc_Estado(0);

        if(!$_objRol->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objRol->getMysqlError();
        }else{    
            $this->_ok = 1;
            $this->_mensaje = "Orden Anulada correctamente";
                     
        }

        return $_objRol->guardar();
    }

    
    /**
    *** Realiza el proceso de Consultar FActuras.
    **/ 
    
    private function _consultarFacturasOrdenes() {
       
        $_objFacturaDocumento = new \predial\DAO_FacturaDocumentoOrdenes();
        
        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objFacturaDocumento->set_doc_Id($_POST['id']);
            }    
        }
        
        if(isset($_POST['doc_IdSede'])){
            if (!empty($_POST['_doc_IdSede']) || $_POST['doc_IdSede'] != NULL ) {
                $_objFacturaDocumento->set_doc_IdSede($_POST['doc_IdSede']);
            }    
        }

        if(isset($_POST['doc_IdVendedor'])){
            if (!empty($_POST['doc_IdVendedor']) || $_POST['doc_IdVendedor'] != NULL ) {
                $_objFacturaDocumento->set_doc_IdVendedor($_POST['doc_IdVendedor']);
            }    
        }

        if(isset($_POST['doc_Fecha'])){
            if (!empty($_POST['doc_Fecha']) || $_POST['doc_Fecha'] != NULL ) {
                $_objFacturaDocumento->set_doc_Fecha($_POST['doc_Fecha']);
            }    
        }

        if(isset($_POST['doc_Estado'])){
            if (!empty($_POST['doc_Estado']) || $_POST['doc_Estado'] != NULL ) {
                $_objFacturaDocumento->set_doc_Estado($_POST['doc_Estado']);
            }    
        }
        
        $_objFacturaDocumento->habilita1ResultadoEnArray();
        $arrRol = $_objFacturaDocumento->consultar();
       
        if(is_array($arrRol) && count($arrRol)){
            $R = [];
            foreach($arrRol as $obj){
                $R[] = $obj->getArray();
            }
            $this->_ok = 1;
            $this->_mensaje = "Facturas Ordenes listadas con exito";
        }else{
            $R=$_objFacturaDocumento;
            $this->_ok = 0;
            $this->_mensaje = "No existen Facturas";            
        }
        return $R;
    } 
    
     /**
    *** Realiza el proceso de consultar los detalles 
    *** de la Resolucion
    **/
    private function _consultarDetalleOrden(){
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $idMesa = $_POST['idMesa'];

        $query = "SELECT fd.doc_Id as idServicioMesa, fdd.detDoc_IdProducto, FORMAT(fdd.detDoc_Cantidad,0) as detDoc_Cantidad, ROUND(fdd.detDoc_ValorTotal,0) as valor
                    FROM fac_documento_ordenes as fd INNER JOIN fac_detalle_documento_ordenes AS fdd 
                        on fd.doc_Id=fdd.detDoc_IdDocumento 
                    WHERE fd.doc_Estado = 1 and fd.doc_IdMesa = $idMesa";

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
    *** Realiza el proceso de consultar los detalles 
    *** de la Resolucion
    **/
    private function _consultarResoluciones(){
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $seem_Id = $_POST['seem_Id'];

        $query = "SELECT *, (SELECT (max(fac.doc_Numero)+1)  from fac_documento_ordenes as fac 
                            WHERE fac.doc_IdSede = csec.seem_Id) as strMaxId  
                    FROM conf_sedes_empresa as csec WHERE csec.seem_Id = $seem_Id ";

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
    *** Realiza el proceso de consultar los detalles 
    *** de la premorma PRODUCTOS
    **/
    private function _consultarProductosOrden(){
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $idOrden = $_POST['idOrdenEditar'];

        $query = "SELECT * , (SELECT inp.pro_Nombre from inv_producto as inp 
                    WHERE inp.pro_Id= fac_detalle_documento_ordenes.detDoc_IdProducto) AS strNombreProducto 
                    FROM fac_detalle_documento_ordenes WHERE detDoc_IdDocumento  = $idOrden";

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

class FacturaOrdenesException extends \Exception{}

    \predial\ControladorFacturaOrdenes::run();

