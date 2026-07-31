<?php
namespace predial;
include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_EgresosEventos.php';
include_once SERVER . '/business/DAO/DAO_CuentasContables.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER .'/business/controller/class.logs.php';

class ControladorEgresosEventos extends \predial\Cabecera {

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
                    $respuesta = $_obj->_agregarEgresosGastos();
                    break;
                case 2:
                    $respuesta = $_obj->_editarEgresosGastos();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarEgresosGastos();
                    break;
            }
            $con->commit();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
            
        } catch (\predial\EgresosEventosException $e) {
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
    protected function _agregarEgresosGastos() {
        
        $_objRol = new \predial\DAO_EgresosEventos();

        $_objRol->set_egre_IdEvento($_POST['selec_IdEventos']);
        $_objRol->set_egre_IdActividad($_POST['selec_IdActividad']);
        $_objRol->set_egre_Descripcion($_POST['txtDescripcionEgreso']);  
        $_objRol->set_egre_Valor($_POST['txtValorPagado']);
        $_objRol->set_egre_Fecha($_POST['txtFecha']);      
        $_objRol->set_egre_Estado(1);

        // Crear movimiento en Tesoreria.
        if($_POST['check'] == 1){
            $observacionesTotal = 'EGRESO EVENTO '.$_POST['txtDescripcionEgreso'];
            $idMoviCuenta = $this->_agregarCuentasContables(2, $_POST['select_Cuentas'],2 , $_POST['txtValorPagado'], $observacionesTotal);
        }
       
        $_objRol->set_egre_IdCuentaContable($idMoviCuenta);

        if(!$_objRol->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objRol->getMysqlError();
        }else{
            $id = $_objRol->get_egre_Id();
            $this->_ok = 1;
            $this->_mensaje = "Egreso ingresado correctamente";
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
    protected function _editarEgresosGastos() {

        $_objRol = new \predial\DAO_EgresosEventos();

        $_objRol->set_egre_Id($_POST['id']);
        $_objRol->set_egre_IdEvento($_POST['selec_IdEventos']);
        $_objRol->set_egre_IdActividad($_POST['selec_IdActividad']);
        $_objRol->set_egre_Descripcion($_POST['txtDescripcionEgreso']);  
        $_objRol->set_egre_Valor($_POST['txtValorPagado']);
        $_objRol->set_egre_Fecha($_POST['txtFecha']);      
    
        if(!$_objRol->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objRol->getMysqlError();
        }else{
            $id = $_objRol->get_egre_Id();
            $this->_ok = 1;
            $this->_mensaje = "Egreso Actualizado correctamente";
        }

        return $_objRol->guardar();
    }
    
    /**
    *** Realiza el proceso de Consultar Egresos.
    **/ 
    private function _consultarEgresosGastos() {
       
        $_objRol = new \predial\DAO_EgresosEventos();

        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objRol->set_egre_Id($_POST['id']);
            }    
        }

        if(isset($_POST['estado'])){
            if (!empty($_POST['estado']) || $_POST['estado'] != NULL ) {
                $_objRol->set_egre_Estado($_POST['estado']);
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

class EgresosEventosException extends \Exception{}

    \predial\ControladorEgresosEventos::run();

