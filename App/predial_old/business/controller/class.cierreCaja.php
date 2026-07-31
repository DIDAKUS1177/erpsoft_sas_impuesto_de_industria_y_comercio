<?php
namespace predial;
include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_CierreCaja.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER .'/business/controller/class.logs.php';



class ControladorCierreCaja extends \predial\Cabecera {

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
                    $respuesta = $_obj->_agregarCierreCaja();
                    break;
                case 2:
                    $respuesta = $_obj->_editarCierreCaja();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarCierreCaja();
                    break; 
                case 4:
                    $respuesta = $_obj->_inactivarCierreCaja();
                    break; 
                case 5:
                    $respuesta = $_obj->_consultarVendedorCaja();
                    break; 
            }
            $con->commit();
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
            
        } catch (\predial\CierreCajaException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    /**
    * _agregarCierreCaja: Método que realiza el proceso de Crear CierreCaja.
    */ 
    protected function _agregarCierreCaja() {
        
        $_objCierreCaja = new \predial\DAO_CierreCaja();
        $_objCierreCaja->set_cica_IdCaja($_POST['cica_IdCaja']);
        $_objCierreCaja->set_cica_IdVendedor($_POST['cica_IdVendedor']);
        $_objCierreCaja->set_cica_Total($_POST['cica_Total']);
        if(isset($_POST['cica_Descuadre'])){
            if (!empty($_POST['cica_Descuadre']) || $_POST['cica_Descuadre'] != NULL ) {
                $_objCierreCaja->set_cica_Descuadre($_POST['cica_Descuadre']);
            }    
        }
        if(isset($_POST['paca_ObservacionesCierre'])){
            if (!empty($_POST['paca_ObservacionesCierre']) || $_POST['paca_ObservacionesCierre'] != NULL ) {
                $_objCierreCaja->set_cica_Observaciones($_POST['paca_ObservacionesCierre']);
            }    
        }
        

        if(empty($_POST['cica_Pagos'])){
            $pagos=0;
        }else{
            $pagos=$_POST['cica_Pagos'] ;
        }

        if(empty($_POST['cica_Base'])){
            $base=0;
        }else{
            $base=$_POST['cica_Base'] ;
        }

        if(empty($_POST['cica_TotalEfectivo'])){
            $efectivo=0;
        }else{
            $efectivo=$_POST['cica_TotalEfectivo'] ;
        }

        $newBase = ($efectivo + $base) - $pagos;
        
        //Valida si es igual el nombre a alguno de la BD.
/*        $nomCierreCaja= $this->_listarCierreCaja(0);
        $longitud = count($nomCierreCaja);
        */
        $nomduplicado=0;
        /*
        for($i=0; $i<$longitud; $i++){  
            if($nomCierreCaja[$i]['imp_Porcentaje'] == $_objCierreCaja->get_imp_Porcentaje()){
               $nomduplicado=1;
                break;
            }
        }
*/
        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un impuesto con el mismo porcentaje';
            $return= false; 
        }else {
            if(!$_objCierreCaja->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objCierreCaja->getMysqlError();
            }else{
                
                // Cerrar: Facturas, Base, Pagos.
                $id = $_objCierreCaja->get_cica_Id();
                $nomCierreCaja= $this->_cerrarFacturas($id, $_POST['cica_IdCaja'],$newBase, $_POST['cica_IdVendedor'], $_POST['cica_CrearBase']);

                $id = $_objCierreCaja->get_cica_Id();
                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,2);
                $this->_ok = 1;
                $this->_mensaje = "CierreCaja ingresados correctamente";
            }
            $return= $_objCierreCaja->guardar();
        }
        return $return;
    }
       
    /*
    * _editarCierreCaja: Método que realiza el proceso de Editar CierreCaja.
    
    protected function _editarCierreCaja() {

        $_objCierreCaja = new \predial\DAO_CierreCaja();
        $_objCierreCaja->set_imp_Id($_POST['id']);
        $_objCierreCaja->set_imp_Descripcion($_POST['descripcion']);
        $_objCierreCaja->set_imp_Porcentaje($_POST['porcentaje']);        

        //Valida si es igual el nombre a alguno de la BD.
        $nomCierreCaja= $this->_listarCierreCajaes($_objCierreCaja->get_imp_Id());
        $longitud = count($nomCierreCaja);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomCierreCaja[$i]['imp_Porcentaje'] == $_objCierreCaja->get_imp_Porcentaje()){
               $nomduplicado=1;
                break;
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un impuesto con el mismo porcentaje';
            $return= false; 
        }else {
            if(!$_objCierreCaja->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objCierreCaja->getMysqlError();
            }else{
                $id = $_objCierreCaja->get_imp_Id();
                $_objlogs = new logs();
                $_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,3);
                $this->_ok = 1;
                $this->_mensaje = "CierreCaja editado correctamente";
            }
            $return= $_objCierreCaja->guardar();
        }
        return $return;
    }
    */ 

    /**
    * _listarCierreCajaes: Método que realiza el proceso 
    * de Listar CierreCaja, exeptuando el CierreCaja enviado por parametro.
    * @param type $id_CierreCaja: llave primaria de la tabla CierreCaja
    */  
    private function _listarCierreCaja($id_CierreCaja) {
       
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT * FROM fac_cierre_caja WHERE imp_Id <> $id_CierreCaja ";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "CierreCaja listados";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existen CierreCaja";
            $row=[];
        }
        return $row;     
    }  

    /**
    * _consultarcierresinfacturas: Método consultar cierre sin facturas
    */  
    protected function _consultarcierresinfacturas(){

        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $id = $_POST['cica_IdCaja'];

        $query = "SELECT cus.usu_Nombre as 'nomVendedor', cus.usu_Id as 'idVendedor' ,fbc.bace_Base as baseCaja,  
            (SELECT SUM(fpc.paca_Valor) FROM fac_pagos_caja as fpc 
            WHERE fbc.bace_IdCaja = fpc.paca_IdCaja and fpc.paca_Cierre=0) as pagosCaja 
        FROM fac_base_caja as fbc
        INNER JOIN conf_usuario as cus ON fbc.bace_IdVendedor = cus.usu_Id
            where fbc.bace_Cierre = 0 and  fbc.bace_IdCaja = $id";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) > 0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
        }else{
            $row=[];
        }
        return $row;
    }

    /**
    * _listarCierreCajaes: Método que realiza el proceso 
    */
    private function _consultarVendedorCaja() {
       
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $id = $_POST['cica_IdCaja'];
/*
        $query = "SELECT MAX(cus.usu_Nombre) as 'nomVendedor', MAX(cus.usu_Id) as 'idVendedor',
                    SUM(fte.teso_Importe) as 'total' ,(SELECT fbc.bace_Base FROM fac_base_caja as fbc 
                                WHERE fbc.bace_IdCaja= $id and fbc.bace_Cierre=0 and cus.usu_Id= fbc.bace_IdVendedor) as baseCaja , 
                                (SELECT SUM(fpc.paca_Valor) from fac_pagos_caja as fpc where fpc.paca_IdCaja= $id
                                    and fpc.paca_Cierre = 0 and cus.usu_Id= fpc.paca_IdVendedor) as pagosCaja,
                            (SELECT SUM(fp.teso_Importe) from fac_tesoreria as fp where fp.teso_IdFormaPago=1 and  fp.teso_Cierre = 0 and fp.teso_IdCaja = $id) as totalEfectivo
                    FROM fac_tesoreria as fte 
                    INNER JOIN fac_documento as fdoc on fte.teso_IdDocumento = fdoc.doc_Id
                    INNER JOIN conf_usuario as cus ON fdoc.doc_IdVendedor = cus.usu_Id
                        where fte.teso_Cierre = 0 and fte.teso_IdCaja = $id";
*/
        $query = "SELECT MAX(cus.usu_Nombre) as 'nomVendedor', MAX(cus.usu_Id) as 'idVendedor',
        SUM(fte.teso_Importe) as 'total' ,(SELECT fbc.bace_Base FROM fac_base_caja as fbc 
                    WHERE fbc.bace_IdCaja= $id and fbc.bace_Cierre=0 and cus.usu_Id= fbc.bace_IdVendedor) as baseCaja , 
                    (SELECT SUM(fpc.paca_Valor) from fac_pagos_caja as fpc where fpc.paca_IdCaja= $id
                        and fpc.paca_Cierre = 0) as pagosCaja,
                (SELECT SUM(fp.teso_Importe) from fac_tesoreria as fp where fp.teso_IdFormaPago=1 and  fp.teso_Cierre = 0 and fp.teso_IdCaja = $id) as totalEfectivo,
                (SELECT SUM(round(cu.cuco_Valor)) as valor
		FROM fac_cuentascontables as cu WHERE cu.cuco_IdCuentaContable = 1 and cu.cuco_Cierre = 0 
			and cu.cuco_IdCierre IS NULL and cu.cuco_IdCaja = $id and cu.cuco_Observacion like '%Abono/Pago Factura a Credito%')as  totalAbonos 
        FROM fac_tesoreria as fte 
        INNER JOIN fac_documento as fdoc on fte.teso_IdDocumento = fdoc.doc_Id
        INNER JOIN conf_usuario as cus ON fdoc.doc_IdVendedor = cus.usu_Id
            where fte.teso_Cierre = 0 and fte.teso_IdCaja = $id";
                        
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) > 0 ){ 
            while($res = $con->obnerFila($data)){
                if (is_null($res["nomVendedor"])){
                   
                    $row = $this->_consultarcierresinfacturas();
                    $this->_ok = 3;
                    $this->_mensaje = "Cierre sin facturas";

                }else{
                    $row[] = $res;
                    $this->_ok = 1;
                    $this->_mensaje = "CierreCaja listados";
                }
            }

        }else{
            
            $this->_ok = 0;
            $this->_mensaje = "No hay facruras por cerrar";
            $row=[];

        }
        return $row;     
    }  

     /**
    * _listarCierreCajaes: Método que realiza el proceso 
    */  
    private function _cerrarFacturas($id, $idCaja, $newBase, $idVendedor, $crearBase) {
       
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
    
        // Actualiza el ID cierre en cada factura
        $query = "UPDATE fac_tesoreria SET teso_Cierre = 1 , teso_IdCierre = $id
                    WHERE teso_IdCaja = $idCaja AND teso_Cierre = 0";
        $data = $con->consultar($query);

        // Actualiza el ID cierre en la base 
        $query = "UPDATE fac_base_caja SET bace_Cierre = 1 , bace_IdCierre = $id
        WHERE bace_IdCaja = $idCaja AND bace_Cierre = 0";
        $data1 = $con->consultar($query);

        
        if($crearBase == 1){
            // Crear Base Caja
                $query = "INSERT INTO fac_base_caja( bace_IdCaja, bace_IdVendedor, bace_Base, bace_Cierre, bace_IdCierre) 
                VALUES ($idCaja, $idVendedor, $newBase, 0, NULL)";
                $data3 = $con->consultar($query);
        }else{

        }


        // Actualiza el ID cierre en cada pago
        $query = "UPDATE fac_pagos_caja SET paca_Cierre = 1 , paca_IdCierre = $id
        WHERE paca_IdCaja = $idCaja AND paca_Cierre = 0";
        $data2 = $con->consultar($query);

        // Actualiza el ID cierre en cada Movimiento de Cuenta
        $query = "UPDATE fac_cuentascontables SET cuco_Cierre = 1 , cuco_IdCierre = $id
        WHERE cuco_Cierre = 0 and cuco_IdCierre IS NULL";
        $data4 = $con->consultar($query);
 
    }  
    
    /*
    * _inactivarCierreCaja: Método que realiza el proceso de 
    * Activar o Inactivar CierreCaja.

    protected function _inactivarCierreCaja() {

        $_objCierreCaja = new \predial\DAO_CierreCaja();
        $_objCierreCaja->set_cica_Id($_POST['id']);
        $_objCierreCaja->set_imp_Estado($_POST['estado']);

        if(!$_objCierreCaja->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objCierreCaja->getMysqlError();
        }else{
            $id = $_objCierreCaja->get_imp_Id();
            $_objlogs = new logs();
                $_objlogs->_insertLogs($_SESSION['id_usuario'],$id,1,4);
            $this->_ok = 1;
            $this->_mensaje = "CierreCaja Activado/Inactivado correctamente";
        }
        return $_objCierreCaja->getArray();
    }
    */ 

    /**
    * _consultarCierreCaja: Método que ealiza el proceso de Consultar CierreCaja.
    */ 
    private function _consultarCierreCaja() {
       
        $_objCierreCaja = new \predial\DAO_CierreCaja();

        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objCierreCaja->set_cica_Id($_POST['id']);
            }    
        }

        if(isset($_POST['cica_IdCaja'])){
            if (!empty($_POST['cica_IdCaja']) || $_POST['cica_IdCaja'] != NULL ) {
                $_objCierreCaja->set_cica_IdCaja($_POST['cica_IdCaja']);
            }    
        }

        if(isset($_POST['idRol']) and ($_POST['idRol']!=1) ){
            if(isset($_POST['cica_IdVendedor'])){
                if (!empty($_POST['cica_IdVendedor']) || $_POST['cica_IdVendedor'] != NULL ) {
                    $_objCierreCaja->set_cica_IdVendedor($_POST['cica_IdVendedor']);
                }    
            }  
        }

      

        $_objCierreCaja->habilita1ResultadoEnArray();
        $arrCierreCaja = $_objCierreCaja->consultar();
       
        if(is_array($arrCierreCaja) && count($arrCierreCaja)){
            $R = [];
            foreach($arrCierreCaja as $obj){
                $R[] = $obj->getArray();
            }
            $this->_ok = 1;
            $this->_mensaje = "Cierre Caja listados con exito";
        }else{
            $R=$_objCierreCaja;
            $this->_ok = 0;
            $this->_mensaje = "No existen Cierre Caja";            
        }
        return $R;
    }  
}

class CierreCajaException extends \Exception{}

    \predial\ControladorCierreCaja::run();

