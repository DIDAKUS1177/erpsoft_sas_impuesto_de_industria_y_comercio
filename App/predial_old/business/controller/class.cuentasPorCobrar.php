<?php
namespace predial;
include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_CuentasPorCobrar.php';
include_once SERVER . '/business/DAO/DAO_CuentasContables.php';
include_once SERVER . '/business/DAO/DAO_Tesoreria.php';
include_once SERVER . '/business/DAO/DAO_Nota.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER .'/business/controller/class.logs.php';

class ControladorCuentasPorCobrar extends \predial\Cabecera {

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
                    $respuesta = $_obj->_agregarCuentasPorCobrar();
                    break;
//                case 2:
//                    $respuesta = $_obj->_editarCuentasContables();
//                    break;
                case 3:
                    $respuesta = $_obj->_consultarCuentasPorCobrar();
                    break; 
//                case 4:
//                    $respuesta = $_obj->_inactivarCuentasPorPagar();
//                    break;
            }
            $con->commit();
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
            
        } catch (\predial\CuentasPorCobrarException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    /**
    * _agregarFormasPago: Método que realiza el proceso de Crear FormasPagoes.
    */ 
    protected function _agregarCuentasPorCobrar() {
        
        $_objCuentasContables = new \predial\DAO_CuentasPorCobrar();
        $_objCuentasContables->set_cuco_IdDocumento($_POST['id']);
        $_objCuentasContables->set_cuco_IdCuentaContable($_POST['kar_Cuentas']);
        $_objCuentasContables->set_cuco_Valor($_POST['Kar_Valor']);

        if(!$_objCuentasContables->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objCuentasContables->getMysqlError();
        }else{
            $id = $_objCuentasContables->get_cuco_Id();
                   
            // INSERTAR movimiento en cuenta contable
            $observacion= "Abono/Pago Factura a Credito #  ".$_POST['strNumeroPrefijo'];
            $this->_agregarCuentasContables($_POST['doc_IdSerieCaja'],1,$_POST['kar_Cuentas'], 
                    $_POST['id'], $_POST['Kar_Valor'], $observacion);

            // Validar abonos Totales
            $totalAbonos = $this->_consultarConsolidado($_POST['id']);
            
            if($totalAbonos >= $_POST['totalNota']){
                $this->_saldarCuenta($_POST['idTesoreria']);
            }
            
            $this->_ok = 1;
            $this->_mensaje = "Abono cuentas por cobrar ingresados correctamente";
        }
        return $_objCuentasContables->guardar();
       
    }

    /**
    * _inactivarMarca: Método que ealiza el proceso de 
    * Activar o Inactivar Marcaes.
    */ 
    protected function _saldarCuenta($id) {

        $_objMarca = new \predial\DAO_Tesoreria();
        $_objMarca->set_teso_Id($id);
        $_objMarca->set_teso_EstadoPago(1);

        if(!$_objMarca->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objMarca->getMysqlError();
        }else{
            $id = $_objMarca->get_teso_Id();
            $this->_ok = 1;
            $this->_mensaje = "Saldada cuenta correctamente";
        }
        return $_objMarca->getArray();
    }
    

    /**
    * _agregarFormasPago: Método que realiza el proceso de Crear FormasPagoes.
    */ 
    protected function _agregarCuentasContables($idCaja, $tipo, $idCuentaContable, $idDocumento, $valor, $observacion) {
        
        $_objCuentasContables = new \predial\DAO_CuentasContables();
        $_objCuentasContables->set_cuco_IdCaja($idCaja);
        $_objCuentasContables->set_cuco_IdTipoMovimiento($tipo);
        $_objCuentasContables->set_cuco_IdCuentaContable($idCuentaContable);
        $_objCuentasContables->set_cuco_IdDocumento($idDocumento);
        $_objCuentasContables->set_cuco_IdTipoSalida(0);
        $_objCuentasContables->set_cuco_Valor($valor);
        $_objCuentasContables->set_cuco_Observacion($observacion);
        $_objCuentasContables->set_cuco_Estado(1);

        if(!$_objCuentasContables->guardar()){
            $return= 0;
        }else{
            $return= 1;
        }
        return $return;
    }
    
    
    /**
    * _consultarFormasPago: Método que ealiza el proceso de Consultar FormasPagoes.
    */ 
    private function _consultarCuentasPorCobrar() {
       
        $_objCuentaContable = new \predial\DAO_CuentasPorPagar();
        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objCuentaContable->set_cupa_Id($_POST['id']);
            }    
        }

        if(isset($_POST['kar_Cuentas'])){
            if (!empty($_POST['kar_Cuentas']) || $_POST['kar_Cuentas'] != NULL ) {
                $_objCuentaContable->set_cupa_IdCuentaContable($_POST['kar_Cuentas']);
            }    
        }

        $_objCuentaContable->habilita1ResultadoEnArray();
        $arrCuentaContable = $_objCuentaContable->consultar();
       
        if(is_array($arrCuentaContable) && count($arrCuentaContable)){
            $R = [];
            foreach($arrCuentaContable as $obj){
                $R[] = $obj->getArray();
            }
            $this->_ok = 1;
            $this->_mensaje = "Abonos Cuentas por pagar listados con exito";
        }else{
            $R=$_objCuentaContable;
            $this->_ok = 0;
            $this->_mensaje = "No existen Abonos de Cuentas por pagar";            
        }
        return $R;
    }

    protected function _consultarConsolidado($id) {

        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        
        $query = "SELECT SUM(cu.cuco_Valor) as tot 
            FROM fac_cuentas_por_cobrar as cu where cu.cuco_IdDocumento = $id";

        $data = $con->consultar($query);
        $cont = mysqli_num_rows($data);
        
        if($cont > 0){
            $id = mysqli_fetch_row($data);
        }else{
            $id[0] = 0;
        }
        
        return $id[0];
    } 
}

class CuentasPorCobrarException extends \Exception{}

    \predial\ControladorCuentasPorCobrar::run();

