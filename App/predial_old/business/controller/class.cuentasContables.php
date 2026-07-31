<?php
namespace predial;
include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_CuentasContables.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER .'/business/controller/class.logs.php';

class ControladorCuentasContables extends \predial\Cabecera {

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
                    $respuesta = $_obj->_agregarCuentasContables();
                    break;
//                case 2:
//                    $respuesta = $_obj->_editarCuentasContables();
//                    break;
                case 3:
                    $respuesta = $_obj->_consultarCuentasContables();
                    break; 
                case 4:
                    $respuesta = $_obj->_inactivarCuentasContables();
                    break;
                case 5:
                    $respuesta = $_obj->_consultarConsolidado();
                    break;
            }
            $con->commit();
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
            
        } catch (\predial\FormasPagoException $e) {
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
    protected function _agregarCuentasContables() {
        
        $_objCuentasContables = new \predial\DAO_CuentasContables();
        $_objCuentasContables->set_cuco_IdCaja($_POST['doc_IdSerieCaja']);
        $_objCuentasContables->set_cuco_IdTipoMovimiento($_POST['idTipoMovimiento']);
        $_objCuentasContables->set_cuco_IdCuentaContable($_POST['idCuentaContable']);
        $_objCuentasContables->set_cuco_IdTipoSalida($_POST['IdTipoSalida']);
        $_objCuentasContables->set_cuco_IdSubTipoSalida($_POST['subtipoSalida']);
        $_objCuentasContables->set_cuco_IdDocumento($_POST['idDocumento']);
        $_objCuentasContables->set_cuco_Valor($_POST['valor']);
        $_objCuentasContables->set_cuco_Observacion($_POST['observacion']);
        $_objCuentasContables->set_cuco_Estado(1);

        if(!$_objCuentasContables->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objCuentasContables->getMysqlError();
        }else{
            $id = $_objCuentasContables->get_cuco_Id();
            //$_objlogs = new logs();
            //$_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,2);
            $this->_ok = 1;
            $this->_mensaje = "Movimiento Contable ingresados correctamente";
        }
        return $_objCuentasContables->guardar();
       
    }
    
    /**
    * _inactivarCuentaContable: Método que realiza el proceso de 
    * Activar o Inactivar FormasPagoes.
    */ 
    protected function _inactivarCuentasContables() {

        $_objCuentaContable = new \predial\DAO_CuentasContables();
        $_objCuentaContable->set_cuco_Id($_POST['id']);
        $_objCuentaContable->set_cuco_Estado($_POST['estado']);

        if(!$_objCuentaContable->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objCuentaContable->getMysqlError();
        }else{
            $id = $_objCuentaContable->get_cuco_Id();
            // $_objlogs = new logs();
            // $_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,4);
            $this->_ok = 1;
            $this->_mensaje = "Movimiento Contavle Activado/Inactivado correctamente";
        }
        return $_objCuentaContable->getArray();
    }
    
    /**
    * _consultarFormasPago: Método que ealiza el proceso de Consultar FormasPagoes.
    */ 
    private function _consultarCuentasContables() {
       
        $_objCuentaContable = new \predial\DAO_CuentasContables();
        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objCuentaContable->set_cuco_Id($_POST['id']);
            }    
        }

        if(isset($_POST['estado'])){
            if (!empty($_POST['estado']) || $_POST['estado'] != NULL ) {
                $_objCuentaContable->set_cuco_Estado($_POST['estado']);
            }    
        }
        
        if(isset($_POST['idCuentaContable'])){
            if (!empty($_POST['idCuentaContable']) || $_POST['idCuentaContable'] != NULL ) {
                $_objCuentaContable->set_cuco_IdCuentaContable($_POST['idCuentaContable']);
            }    
        }

        if(isset($_POST['doc_IdSerieCaja'])){
            if (!empty($_POST['doc_IdSerieCaja']) || $_POST['doc_IdSerieCaja'] != NULL ) {
                $_objCuentaContable->set_cuco_IdCaja($_POST['doc_IdSerieCaja']);
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
            $this->_mensaje = "Cuentas Contables listados con exito";
        }else{
            $R=$_objCuentaContable;
            $this->_ok = 0;
            $this->_mensaje = "No existen Cuentas Contables";            
        }
        return $R;
    }

    /**
    *** Realiza el proceso de consultar consolidado de Cuentas Contables
    **/
    private function _consultarConsolidado(){
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();

        $query = "SELECT *, (SELECT IFNULL( SUM(cu.cuco_Valor),0) FROM fac_cuentascontables as cu 
        where cu.cuco_IdTipoMovimiento = 1 and fopa.forpa_Id = cu.cuco_IdCuentaContable ) as entradas,
        (SELECT IFNULL(SUM(cu.cuco_Valor),0) FROM fac_cuentascontables as cu
        where cu.cuco_IdTipoMovimiento = 2 and fopa.forpa_Id = cu.cuco_IdCuentaContable ) as salidas
        FROM fac_formas_pago as fopa";

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

class CuentasContablesException extends \Exception{}

    \predial\ControladorCuentasContables::run();

