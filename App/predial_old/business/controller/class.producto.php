<?php
namespace predial;
include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_Producto.php';
include_once SERVER . '/business/DAO/DAO_PreciosVenta.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER.'/business/controller/class.logs.php';

class ControladorProductos extends \predial\Cabecera {

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
                    $respuesta = $_obj->_agregarProducto();
                    break;
                case 2:
                    $respuesta = $_obj->_editarProducto();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarProductos();
                    break; 
                case 4:
                    $respuesta = $_obj->_inactivarProductos();
                    break; 
                case 5:
                    $respuesta = $_obj->_consultarUnidadMedida();
                    break; 
                case 6:
                    $respuesta = $_obj->_consultarMxId();
                    break; 
                case 7:
                    $respuesta = $_obj->_consultarStockGlobales();
                    break; 
                case 8:
                    $respuesta = $_obj->_consultarStockProducto();
                    break; 
            }
            $con->commit();
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta, "id" => $_obj->_id));
            
        } catch (\predial\ProductosException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "", "id" => $_obj->_id);
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    /**
    *** Realiza el proceso de Crear Productos.
    **/  
    protected function _agregarProducto() {
        
        $_objProducto = new \predial\DAO_Producto();
        $_objProducto->set_pro_Codigo($_POST['codigo']);
        $_objProducto->set_pro_Nombre($_POST['nombre']);
        $_objProducto->set_pro_CodBarras($_POST['codBarras']);
        $_objProducto->set_pro_Tipo($_POST['tipo']);
        $_objProducto->set_pro_UnidadMed($_POST['unidad']);
        $_objProducto->set_pro_CantidadMed($_POST['cantidadMed']);
        $_objProducto->set_pro_UsaStoks($_POST['UsaStoks']);
        $_objProducto->set_pro_IdImpuesto($_POST['IdImpuesto']);
        $_objProducto->set_pro_Categoria($_POST['Categoria']);
        $_objProducto->set_pro_SubCategoria($_POST['SubCategoria']);
        $_objProducto->set_pro_IdMarca($_POST['IdMarca']);
        $_objProducto->set_pro_IdProveedor($_POST['IdProveedor']);
        $_objProducto->set_pro_Estado(1);

        //Valida si es igual el codigo alguno de la BD.
        $nomUsurio= $this->_listarProductos(0);
        $longitud = count($nomUsurio);
        $nomduplicado=0;

        for($i=0; $i<$longitud; $i++){  
            if($nomUsurio[$i]['pro_Codigo'] == $_objProducto->get_pro_Codigo()){
               $nomduplicado=1;
                break;
            }

            if(($nomUsurio[$i]['pro_CodBarras'] == $_objProducto->get_pro_CodBarras())and($nomUsurio[$i]['pro_CodBarras'] != NULL)){
                $nomduplicado=2;
                 break;
             }
            
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un producto con el mismo código';
            $return= false; 
        }else if($nomduplicado == 2){
            $this->_ok = 3;
            $this->_mensaje = 'Ya existe un producto con el mismo codigo de Barras';
            $return= false;   
        }else{
            if(!$_objProducto->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objProducto->getMysqlError();
            }else{
                $id = $_objProducto->get_pro_Id();

                // Crear precio Venta Inicial
                $precioVenta = $this->_agregarPreciosVenta(1, $id, $_POST['pro_PrecioVenta'] ); 

                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($id,3,$_SESSION['id_Producto'],3);

                $this->_ok = 1;
                $this->_id = $id ;
                $this->_mensaje = "Datos ingresados correctamente";
            }
            $return= $_objProducto->guardar();
        }
        return $return;
    }

    /**
    *** Realiza el proceso de Crear Precios de Venta.
    **/  
    protected function _agregarPreciosVenta($tarifa, $idProducto, $precioVenta) {
        
        $_objProducto = new \predial\DAO_PreciosVenta();
        $_objProducto->set_preVen_IdTarifa($tarifa);
        $_objProducto->set_preVen_IdProducto($idProducto);
        $_objProducto->set_preVen_PrecioNeto($precioVenta);
        $_objProducto->set_preVen_Estado(1);
  
            if(!$_objProducto->guardar()){
                $return = 0;
            }else{
                $return = 1;
            }
        return $return;

    }

    
    /**
    *** Realiza el proceso de Editar Productos.
    **/  
    protected function _editarProducto() {
        
        $_objProducto = new \predial\DAO_Producto();

        $_objProducto->set_pro_Id($_POST['id']);
        $_objProducto->set_pro_Codigo($_POST['codigo']);
        $_objProducto->set_pro_Nombre($_POST['nombre']);
        $_objProducto->set_pro_CodBarras($_POST['codBarras']);
        $_objProducto->set_pro_Tipo($_POST['tipo']);
        $_objProducto->set_pro_UnidadMed($_POST['unidad']);
        $_objProducto->set_pro_CantidadMed($_POST['cantidadMed']);
        $_objProducto->set_pro_UsaStoks($_POST['UsaStoks']);
        $_objProducto->set_pro_IdImpuesto($_POST['IdImpuesto']);
        $_objProducto->set_pro_Categoria($_POST['Categoria']);
        $_objProducto->set_pro_SubCategoria($_POST['SubCategoria']);
        $_objProducto->set_pro_IdMarca($_POST['IdMarca']);
        $_objProducto->set_pro_IdProveedor($_POST['IdProveedor']);
        
        //Valida si es igual el email y/o identificación a alguno de la BD.
        $nomUsurio= $this->_listarProductos($_objProducto->get_pro_Id());
        $longitud = count($nomUsurio);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomUsurio[$i]['pro_Codigo'] == $_objProducto->get_pro_Codigo()){
               $nomduplicado=1;
                break;
            }

            //if($nomUsurio[$i]['pro_CodBarras'] == $_objProducto->get_pro_CodBarras()){
            if(($nomUsurio[$i]['pro_CodBarras'] == $_objProducto->get_pro_CodBarras())and($nomUsurio[$i]['pro_CodBarras'] != NULL)){
                $nomduplicado=2;
                 break;
             }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un producto con el mismo código';
            $return= false; 
        }else if($nomduplicado == 2){
            $this->_ok = 3;
            $this->_mensaje = 'Ya existe un producto con el mismo código de Barras';
            $return= false;   
        }else{
            if(!$_objProducto->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objProducto->getMysqlError();
            }else{

                // Crear precio Venta Inicial
                $costoVenta = $this->_actualizarCosto_PrecioVenta($_POST['pro_costo'], $_POST['pro_costo_Id'],
                                    $_POST['pro_PrecioVenta'], $_POST['pro_PrecioVenta_Id'] ); 

                $id = $_objProducto->get_pro_Id();
                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($id,4,$_SESSION['id_Usuario'],3);
                $this->_ok = 1;
                $this->_id = $costoVenta ;
                $this->_mensaje = "Datos ingresados correctamente";
            }
            $return= $_objProducto->guardar();
        }
        return $return;
    }
    
    
    /**
    *** Realiza el proceso de sumar o restar las nuevas existencias del producto.
    **/ 
    private function _actualizarCosto_PrecioVenta($pro_costo, $pro_costo_Id, $pro_PrecioVenta, $pro_PrecioVenta_Id) {

        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "UPDATE fac_precios_venta SET preVen_PrecioNeto = $pro_PrecioVenta WHERE preVen_Id = $pro_PrecioVenta_Id";
        $data1 = $con->consultar($query);

        $query1 = "UPDATE inv_detalle_kardex SET detkar_ValorUnitario = $pro_costo WHERE detkar_Id = $pro_costo_Id";
        $data = $con->consultar($query1);
        return $data;
    } 


    /**
    *** Realiza el proceso de Listar Productos, exeptuando el Producto enviado por parametro.
    *** @param type $id_Producto
    **/  
    private function _listarProductos($id_Producto) {
       
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT * FROM inv_producto WHERE pro_Id <> $id_Producto ";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_id = 0;
            $this->_mensaje = "Productos listados";
        }else{
            $this->_ok = 0;
            $this->_id = 0;
            $this->_mensaje = "No existen Productos";
            $row=[];
        }
        return $row;     
    }  
    
    /**
    *** Realiza el proceso de Consultar Productos.
    **/  
    private function _consultarProductos() {
       
        $_objUsu = new \predial\DAO_Producto();
        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objUsu->set_pro_Id($_POST['id']);
            }    
        }

        if(isset($_POST['estado'])){
            if (!empty($_POST['estado']) || $_POST['estado'] != NULL ) {
                $_objUsu->set_pro_Estado($_POST['estado']);
            }    
        }
        
        
        $_objUsu->habilita1ResultadoEnArray();
        $arrProductos = $_objUsu->consultar();
       
        if(is_array($arrProductos) && count($arrProductos)){
            $R = [];
            foreach($arrProductos as $obj){
                $R[] = $obj->getArray();
            }    
            $this->_ok = 1;
            $this->_id = 0;
            $this->_mensaje = "Productos listados con exito"; 
        }else{
            $R=$_objUsu;
            $this->_ok = 0;
            $this->_id = 0;
            $this->_mensaje = "No existen Productos";            
        }       
        return $R;
    }
    
    /**
    *** Realiza el proceso de Activar o Inactivar Productos.
    **/  
    protected function _inactivarProductos() {

        $_objProducto = new \predial\DAO_Producto();
        $_objProducto->set_pro_Id($_POST['id']);
        $_objProducto->set_pro_Estado($_POST['estado']);
        
        if(!$_objProducto->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objProducto->getMysqlError();
        }else{
            $id = $_objProducto->get_pro_Id();
//            $_objlogs = new logs();
//            $_objlogs->_insertLogs($id,5,$_SESSION['id_Producto'],2);
            $this->_ok = 1;
            $this->_mensaje = "Producto Activado/inactivado correctamente";
        }
        return $_objProducto->getArray();
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
            $this->_id = 0;
            $this->_mensaje = "unidad de medida listadas";
        }else{
            $this->_ok = 0;
            $this->_id = 0;
            $this->_mensaje = "No existen unidades de medidas";
            $row = NULL;
        }
        return $row;  
    }

    /**
    *** Realiza el proceso de consultar stock globales
    **/  
    protected function _consultarStockGlobales() {

        $idd = $_POST['id_Producto'];

        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT inb.bod_Nombre as nombre, ine.exi_Cantidad as cantidad FROM inv_existencias as ine 
                    INNER JOIN inv_bodega as inb ON ine.exi_IdBodega=inb.bod_Id 
                    where ine.exi_IdProducto = $idd";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_id = 0;
            $this->_mensaje = "Stock";
        }else{
            $this->_ok = 0;
            $this->_id = 0;
            $this->_mensaje = "Stock no hay ";
            $row = NULL;
        }
        return $row;  
    }

    /**
    *** Realiza el proceso de consultar max ID
    **/  
    protected function _consultarStockProducto() {

        $producto = $_POST['id'];
        $bodega = $_POST['IdBodega'];

        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        //$query = "SELECT * from inv_existencias where exi_IdProducto = $producto and exi_IdBodega = $bodega";
        $query = "SELECT exi.*, (SELECT pro.pro_UsaStoks FROM inv_producto AS pro WHERE pro.pro_Id= exi.exi_IdProducto) as tipo from inv_existencias as exi where exi.exi_IdProducto = $producto and exi.exi_IdBodega = $bodega";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_id = 0;
            $this->_mensaje = "STOCK ";
        }else{
            $this->_ok = 0;
            $this->_id = 0;
            $this->_mensaje = "No existen ";
            $row = NULL;
        }
        return $row;  
    }

    /**
    *** Realiza el proceso de consultar max ID
    **/  
    protected function _consultarMxId() {

        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT MAX(pro_Codigo)+1 as id FROM inv_producto";
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

class ProductosException extends \Exception{}

    \predial\ControladorProductos::run();

