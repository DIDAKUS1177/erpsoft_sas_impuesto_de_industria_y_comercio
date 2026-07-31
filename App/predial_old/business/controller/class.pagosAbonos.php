<?php
namespace predial;
include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_PagosAbonos.php';
include_once SERVER . '/business/DAO/DAO_CuentasContables.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER .'/business/controller/class.logs.php';

class ControladorPagosAbonos extends \predial\Cabecera {

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
                    $respuesta = $_obj->_agregarPagosAbonos();
                    break;
                case 2:
                    $respuesta = $_obj->_editarPagosAbonos();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarPagosAbonos();
                    break;
            }
            $con->commit();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
            
        } catch (\predial\ProyectosException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    /**
    *** Realiza el proceso de Crear Roles.
    **/ 
    protected function _agregarPagosAbonos() {
        
        $_objRol = new \predial\DAO_PagosAbonos();

        $_objRol->set_pago_IdProyecto($_POST['pago_IdProyecto']);        
        $_objRol->set_pago_Fecha($_POST['pago_Fecha']);
        $_objRol->set_pago_Descripcion($_POST['pago_Descripcion']);
        $_objRol->set_pago_Valor($_POST['pago_Valor']);
        $_objRol->set_pago_Estado(1);

        // Crear movimiento en Tesoreria.
        if($_POST['check'] == 1){
            $observacionesTotal = 'INGRESO EVENTO '.$_POST['pago_Descripcion'];
            $idMoviCuenta = $this->_agregarCuentasContables(1, $_POST['select_Cuentas'], 0, $_POST['pago_Valor'], $observacionesTotal);
        }
        
        $_objRol->set_pago_IdCuentaContable($idMoviCuenta);

        if(!$_objRol->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objRol->getMysqlError();
        }else{
            $id = $_objRol->get_pago_Id();
            $this->_ok = 1;
            $this->_mensaje = "Abono/Pago ingresado correctamente";
        }
        
        return $_objRol->guardar();
    }

    /**
    * _agregarFormasPago: Método que realiza el proceso de Crear FormasPagoes.
    */ 
    protected function _agregarCuentasContables($idTipoMovimiento, $idCuentaContable ,$IdTipoSalida ,$valor, $observacion) {
        
        $_objCuentasContables = new \predial\DAO_CuentasContables();
        $_objCuentasContables->set_cuco_IdTipoMovimiento($idTipoMovimiento);
        $_objCuentasContables->set_cuco_IdCuentaContable($idCuentaContable);
        $_objCuentasContables->set_cuco_IdTipoSalida($IdTipoSalida);
        $_objCuentasContables->set_cuco_IdDocumento(0);
        $_objCuentasContables->set_cuco_Valor($valor);
        $_objCuentasContables->set_cuco_Observacion($observacion);
        $_objCuentasContables->set_cuco_Estado(1);

        if(!$_objCuentasContables->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objCuentasContables->getMysqlError();
            $id = 0;
        }else{
            $id = $_objCuentasContables->get_cuco_Id();
            //$_objlogs = new logs();
            //$_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,2);
            $this->_ok = 1;
            $this->_mensaje = "Movimiento Contable ingresados correctamente";
        }
        return $id;
       
    }


       
    /**
    *** Realiza el proceso de Editar Egresos.
    **/ 
    protected function _editarPagosAbonos() {

        $_objRol = new \predial\DAO_PagosAbonos();

        $_objRol->set_pago_Id($_POST['id']);
        $_objRol->set_pago_IdProyecto($_POST['pago_IdProyecto']);
        $_objRol->set_pago_Fecha($_POST['pago_Fecha']);
        $_objRol->set_pago_Descripcion($_POST['pago_Descripcion']);
        $_objRol->set_pago_Valor($_POST['pago_Valor']);
        $_objRol->set_pago_Estado(1);
        
        if(!$_objRol->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objRol->getMysqlError();
        }else{
            $id = $_objRol->get_pago_Id();
            $this->_ok = 1;
            $this->_mensaje = "Abono/Pago Actualizado correctamente";
        }

        return $_objRol->guardar();
    }
    
    /**
    *** Realiza el proceso de Consultar Egresos.
    **/ 
    private function _consultarPagosAbonos() {
       
        $_objRol = new \predial\DAO_PagosAbonos();

        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objRol->set_pago_Id($_POST['id']);
            }    
        }

        if(isset($_POST['estado'])){
            if (!empty($_POST['estado']) || $_POST['estado'] != NULL ) {
                $_objRol->set_pago_IdProyecto($_POST['estado']);
            }    
        }

        if(isset($_POST['pago_IdProyecto'])){
            if (!empty($_POST['pago_IdProyecto']) || $_POST['pago_IdProyecto'] != NULL ) {
                $_objRol->set_pago_IdProyecto($_POST['pago_IdProyecto']);
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
            $this->_mensaje = "Egresos listadas con exito";
        }else{
            $R=$_objRol;
            $this->_ok = 0;
            $this->_mensaje = "No existen Egresos";            
        }
        return $R;
    }

}

class PagosAbonosException extends \Exception{}

    \predial\ControladorPagosAbonos::run();

