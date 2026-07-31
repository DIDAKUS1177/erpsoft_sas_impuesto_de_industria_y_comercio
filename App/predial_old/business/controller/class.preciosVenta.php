<?php
namespace predial;
include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_PreciosVenta.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER.'/business/controller/class.logs.php';

class ControladorPreciosVenta extends \predial\Cabecera {

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
                    $respuesta = $_obj->_agregarPreciosVenta();
                    break;
                case 2:
                    $respuesta = $_obj->_editarProducto();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarPreciosVenta();
                    break; 
                case 4:
                    //$respuesta = $_obj->_inactivarProductos();
                    break; 
                case 5:
                    //$respuesta = $_obj->_consultarUnidadMedida();
                    break; 
            }
            $con->commit();
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
            
        } catch (\predial\ProductosException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    /**
    *** Realiza el proceso de Crear Precios de Venta.
    **/  
    protected function _agregarPreciosVenta() {
        
        $_objProducto = new \predial\DAO_PreciosVenta();
        $_objProducto->set_preVen_IdTarifa($_POST['idTarifa']);
        $_objProducto->set_preVen_IdProducto($_POST['idProducto']);
        $_objProducto->set_preVen_PrecioNeto($_POST['precioNeto']);
        $_objProducto->set_preVen_Estado(1);

        //Valida si es igual el codigo alguno de la BD.
        //$nomUsurio= $this->_listarProductos(0);
        //$longitud = count($nomUsurio);
        //$nomduplicado=0;

        //for($i=0; $i<$longitud; $i++){  
        //    if($nomUsurio[$i]['pro_Codigo'] == $_objProducto->get_pro_Codigo()){
        //       $nomduplicado=1;
        //       break;
        //    }
        //}

        //if($nomduplicado == 1){
        //    $this->_ok = 2;
        //    $this->_mensaje = 'Ya existe un producto con el mismo código';
        //    $return= false;   
            if(!$_objProducto->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objProducto->getMysqlError();
            }else{
                $id = $_objProducto->get_preVen_Id();
                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($id,3,$_SESSION['id_Producto'],3);
                $this->_ok = 1;
                $this->_mensaje = "Datos ingresados correctamente";
            }
            $return= $_objProducto->guardar();
        //}
        return $return;
    }
    
    /**
    *** Realiza el proceso de Editar Productos.
    **/  
    protected function _editarProducto() {
        
        $_objProducto = new \predial\DAO_PreciosVenta();

        $_objProducto->set_preVen_Id($_POST['id']);
        $_objProducto->set_preVen_IdTarifa($_POST['idTarifa']);
        $_objProducto->set_preVen_IdProducto($_POST['idProducto']);
        $_objProducto->set_preVen_PrecioNeto($_POST['precioNeto']);
        
        //Valida si es igual el email y/o identificación a alguno de la BD.
        /*
        $nomUsurio= $this->_listarProductos($_objProducto->get_pro_Id());
        $longitud = count($nomUsurio);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomUsurio[$i]['pro_Codigo'] == $_objProducto->get_pro_Codigo()){
               $nomduplicado=1;
                break;
            }

            if($nomUsurio[$i]['pro_CodBarras'] == $_objProducto->get_pro_CodBarras()){
                $nomduplicado=2;
                 break;
             }
        }
        */
        
        //if($nomduplicado == 1){
        //    $this->_ok = 2;
        //    $this->_mensaje = 'Ya existe un producto con el mismo código';
        //    $return= false; 
        //}else if($nomduplicado == 2){
        //    $this->_ok = 3;
        //    $this->_mensaje = 'Ya existe un producto con el mismo código de Barras';
       //     $return= false;   
        //}else{
            if(!$_objProducto->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objProducto->getMysqlError();
            }else{
                $id = $_objProducto->get_preVen_Id();
                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($id,4,$_SESSION['id_Usuario'],3);
                $this->_ok = 1;
                $this->_mensaje = "Datos ingresados correctamente";
            }
            $return= $_objProducto->guardar();
       // }
        return $return;
    }
    
    /**
    *** Realiza el proceso de Consultar Precios Productos.
    **/  
    private function _consultarPreciosVenta() {
       
        $_objUsu = new \predial\DAO_PreciosVenta();
        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objUsu->set_preVen_Id($_POST['id']);
            }    
        }
        
        if(isset($_POST['preVen_IdProducto'])){
            if (!empty($_POST['preVen_IdProducto']) || $_POST['preVen_IdProducto'] != NULL ) {
                $_objUsu->set_preVen_IdProducto($_POST['preVen_IdProducto']);
            }    
        }

        if(isset($_POST['estado'])){
            if (!empty($_POST['estado']) || $_POST['estado'] != NULL ) {
                $_objUsu->set_preVen_Estado($_POST['estado']);
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
            $this->_mensaje = "Precios Productos listados con exito"; 
        }else{
            $R=$_objUsu;
            $this->_ok = 0;
            $this->_mensaje = "No existen Productos";            
        }       
        return $R;
    }
    
}

class PreciosVentaException extends \Exception{}

    \predial\ControladorPreciosVenta::run();

