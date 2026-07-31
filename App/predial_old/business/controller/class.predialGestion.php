<?php
namespace predial;

use Exception;

include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_FacturaDocumento.php';
include_once SERVER . '/business/DAO/DAO_FacturaDetalleDocumento.php';
include_once SERVER . '/business/DAO/DAO_Tesoreria.php';
include_once SERVER . '/business/DAO/DAO_FacturaDocumentoOrdenes.php';
include_once SERVER . '/business/DAO/DAO_CuentasContables.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER .'/business/controller/class.logs.php';

class ControladorPredialGestion extends \predial\Cabecera {

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
                    $respuesta = $_obj->_consultarPredialGestionSql();
                    break;
                case 2:
                    $respuesta = $_obj->_consultarPredialDocumentos();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarMorosos();
                    break;
                case 4:
                    $respuesta = $_obj->_transladarPredioFizcalización();
                    break;
                case 5:
                    $respuesta = $_obj->_consultarMorososEstados();
                    break;
				case 6:
                    $respuesta = $_obj->_transladarPredioFizcalizaciónInves();
                    break;	
                case 7:
                    $respuesta = $_obj->_agregarInvestigacion();
                    break;	
                case 8:
                    $respuesta = $_obj->_consultarHojaVida();
                    break;						
            }
            $con->commit();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
            
        } catch (\predial\NotaException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    /**
    *** Realiza el proceso de Consultar Facturas en ICG - Caja ganadora.
    **/ 
    public function _consultarPredialGestionSql() {

        $fecha = $_POST['FechaGestion'];
        
        // LOCAL
        $serverName = "DESARROLLO\SQLEXPRESS2019";
        $connectionInfo = array( "Database"=>"erpsofts_paipa_test", "UID"=>"sa", "PWD"=>"Server2019");
        
        // PRODUCCIÓN  
		//$serverName = "167.114.216.134\\MSSQLSERVER2019";
        //$connectionInfo = array( "Database"=>"erpsofts_paipa_test", "UID"=>"erpsofts_pse", "PWD"=>"predial123.");
        $conn = sqlsrv_connect( $serverName, $connectionInfo);

        if( $conn === false ) {
            die( print_r(sqlsrv_errors(), true));
        }
/*
		// AMBIENTE DE PRUEBAS
        $sql = "SELECT TOP (10) id, codigo_predio ,codigo_predio_anterior ,
                direccion ,ultimo_anio_pago
                FROM predios 
                    where ultimo_anio_pago = $fecha and codigo_predio_anterior != 1
                ORDER BY codigo_predio ASC";
*/
		// AMBIENTE DE PRODUCCION
        $sql = "SELECT TOP (10) id, codigo_predio ,codigo_predio_anterior ,
                direccion ,ultimo_anio_pago, (SELECT TOP(1)pr.nombre FROM predios_propietarios as pp inner join propietarios as pr 
					on pp.id_propietario=pr.id where pp.id_predio = p.id) as nombre
                FROM predios as p
                    where p.ultimo_anio_pago = $fecha and p.estado_documentacion = 0 and p.ind_eliminado = 0 and p.ind_excento = 0
                ORDER BY p.ultimo_anio_pago DESC";


        $stmt = sqlsrv_query( $conn, $sql );
        
        if( $stmt === false) {
            $row = FALSE;
            die( print_r( sqlsrv_errors(), true) );
            $this->_ok = 0;
            $this->_mensaje = "No existen Predios";  
        }else{
            while( $res = sqlsrv_fetch_array( $stmt, SQLSRV_FETCH_ASSOC) ) {
                    $row[] = $res;
             }
             $this->_ok = 1;
             $this->_mensaje = "Predios listadas con exito";
        }
        //print_r($row);
        return $row;  
    }

    /**
    *** Realiza el proceso de Consultar Predios con Documentación.
    **/ 
    public function _consultarPredialDocumentos() {

        $fechaAnio = $_POST['fechaAnio'];
        $fechaMes = $_POST['fechaMes'];
		$fechaDia = $_POST['fechaDia'];

        // LOCAL
        $serverName = "DESARROLLO\SQLEXPRESS2019";
        $connectionInfo = array( "Database"=>"erpsofts_paipa_test", "UID"=>"sa", "PWD"=>"Server2019");
            
        // PRODUCCIÓN  
        //$serverName = "167.114.216.134\\MSSQLSERVER2019";
        //$connectionInfo = array( "Database"=>"erpsofts_paipa_test", "UID"=>"erpsofts_pse", "PWD"=>"predial123.");
        
        $conn = sqlsrv_connect( $serverName, $connectionInfo);

        if( $conn === false ) {
            die( print_r(sqlsrv_errors(), true));
        }
/*		
		// AMBIENTE DE PRUEBAS
           $sql = "SELECT id, codigo_predio ,codigo_predio_anterior ,
                direccion ,ultimo_anio_pago
                FROM predios 
                    where codigo_predio_anterior = '1' 
                ORDER BY codigo_predio ASC";
*/
		// AMBIENTE DE PRODUCCION
        $sql = "SELECT id, codigo_predio ,codigo_predio_anterior ,nom_usu_documentacion,
                direccion ,ultimo_anio_pago, (SELECT TOP(1)pr.nombre FROM predios_propietarios as pp inner join propietarios as pr 
					on pp.id_propietario=pr.id where pp.id_predio = p.id) as nombre
                FROM predios as p
                    where p.estado_documentacion = 1 and p.fecha_documentacion = $fechaAnio 
                            and p.mes_documentacion = $fechaMes and p.dia_documentacion = $fechaDia
                ORDER BY p.codigo_predio DESC";


        $stmt = sqlsrv_query( $conn, $sql );
        
        if( $stmt === false) {
            $row = FALSE;
            die( print_r( sqlsrv_errors(), true) );
            $this->_ok = 0;
            $this->_mensaje = "No existen Predios";  
        }else{
            while( $res = sqlsrv_fetch_array( $stmt, SQLSRV_FETCH_ASSOC) ) {
                    $row[] = $res;
             }
             $this->_ok = 1;
             $this->_mensaje = "Predios listadas con exito";
        }
        //print_r($row);
        return $row;  
    }
	
 	/**
    *** Realiza el proceso de Consultar Predios con Documentación.
    **/ 
    public function _consultarMorosos() {

        $fecha = $_POST['fecha'];
        $fechafinal = $_POST['fechaFinal'];

        // LOCAL
        $serverName = "DESARROLLO\SQLEXPRESS2019";
        $connectionInfo = array( "Database"=>"erpsofts_paipa_test", "UID"=>"sa", "PWD"=>"Server2019");
        
        // PRODUCCIÓN  
        //$serverName = "167.114.216.134\\MSSQLSERVER2019";
        //$connectionInfo = array( "Database"=>"erpsofts_paipa_test", "UID"=>"erpsofts_pse", "PWD"=>"predial123.");
        
        $conn = sqlsrv_connect( $serverName, $connectionInfo);

        if( $conn === false ) {
            die( print_r(sqlsrv_errors(), true));
        }
        ini_set('memory_limit', '1024M'); // Aumenta el límite de memoria
        ini_set('max_execution_time', '600'); // Aumenta el tiempo de ejecución

		// AMBIENTE DE PRODUCCION   TOP (10)
        $sql = "SELECT  TOP (500) pp.id_predio, pp.id as id, (SELECT p.codigo_predio from predios p where p.id=pp.id_predio) as codigo,
				pp.avaluo as avaluo,
                (SELECT pro.identificacion from propietarios pro where pro.id = (SELECT ppr.id_propietario from predios_propietarios ppr where ppr.id_predio=pp.id_predio and ppr.jerarquia = 001)) as identificacionPropietario,
                (SELECT pro.nombre from propietarios pro where pro.id = (SELECT ppr.id_propietario from predios_propietarios ppr where ppr.id_predio=pp.id_predio and ppr.jerarquia = 001)) as nombrePropietario,
                (SELECT pro.direccion from propietarios pro where pro.id = (SELECT ppr.id_propietario from predios_propietarios ppr where ppr.id_predio=pp.id_predio and ppr.jerarquia = 001)) as direccionPropietarioAnterior,
				(SELECT pred.direccion from predios pred where pred.id = pp.id_predio) as direccionPropietario,
				(select pr.matricula_inmobiliaria from predios_datos pr where id_predio = pp.id_predio) as matricula,
                pp.ultimo_anio as anio,
                pp.total_calculo as valorAproximado,
				pp.estado_moroso as estadoMoroso
		from predios_pagos pp where pp.pagado = 0 and (pp.ultimo_anio >= $fecha and pp.ultimo_anio <= $fechafinal)
        ORDER BY codigo ASC, anio ASC";
		
		/* Se agrega el campo ( estado_fiscalizacion )  
		0: Libre
		1: Investigación
		2: Factura
		3: Publicacion
		4: Constancia.
		5: Mandamiento
		*/

        // and pp.estado_moroso = 0
        $stmt = sqlsrv_query( $conn, $sql );
        
        if( $stmt === false) {
            $row = FALSE;
            die( print_r( sqlsrv_errors(), true) );
            $this->_ok = 0;
            $this->_mensaje = "No existen Predios";  
        }else{
            while( $res = sqlsrv_fetch_array( $stmt, SQLSRV_FETCH_ASSOC) ) {
                    $row[] = $res;
             }
             $this->_ok = 1;
             $this->_mensaje = "Predios listadas con exito";
        }

        return $row;  
    }
	
	
	/**
    *** Realiza el proceso de translado de estado en fiscalización.
    **/ 
    public function _transladarPredioFizcalización() {

        $idPredio = $_POST['idPredio'];
        $anio = $_POST['anio'];
		$estado = $_POST['estado'];

        // LOCAL
        $serverName = "DESARROLLO\SQLEXPRESS2019";
        $connectionInfo = array( "Database"=>"erpsofts_paipa_test", "UID"=>"sa", "PWD"=>"Server2019");
        
        // PRODUCCIÓN  
        //$serverName = "167.114.216.134\\MSSQLSERVER2019";
        //$connectionInfo = array( "Database"=>"erpsofts_paipa_test", "UID"=>"erpsofts_pse", "PWD"=>"predial123.");
        
        $conn = sqlsrv_connect( $serverName, $connectionInfo);
		
        if( $conn === false ) {
            die( print_r(sqlsrv_errors(), true));
        }
		
		$queryye = "EXEC [API].[SP_UPDATE_ESTADO_PREDIOSPAGOS_MOROSOS] @Annio = $anio, @IdPredio = $idPredio, @Estado = $estado";
		$stmt = sqlsrv_query( $conn, $queryye);

        if( $stmt === false) {
            $row = FALSE;
            die( print_r( sqlsrv_errors(), true) );
            $this->_ok = 0;
            $this->_mensaje = "No Translado";  
        }else{
            while( $res = sqlsrv_fetch_array( $stmt, SQLSRV_FETCH_ASSOC) ) {
                    $row[] = $res;
             }
             $row=true;
             $this->_ok = 1;
             $this->_mensaje = "Translado con exito".$anio.'---'.$idPredio.'---'.$estado;
        }

        return $row;  
    }

	
	/**
    *** Realiza el proceso de Consultar Predios con Documentación.
    **/ 
    public function _consultarMorososEstados() {

        $estado = $_POST['estado'];
		$idUsuario = $_POST['idUsuario'];
        $idRol = $_POST['idRol'];

        // LOCAL
        $serverName = "DESARROLLO\SQLEXPRESS2019";
        $connectionInfo = array( "Database"=>"erpsofts_paipa_test", "UID"=>"sa", "PWD"=>"Server2019");
        
        // PRODUCCIÓN  
        //$serverName = "167.114.216.134\\MSSQLSERVER2019";
        //$connectionInfo = array( "Database"=>"erpsofts_paipa_test", "UID"=>"erpsofts_pse", "PWD"=>"predial123.");
        
        $conn = sqlsrv_connect( $serverName, $connectionInfo);

        if( $conn === false ) {
            die( print_r(sqlsrv_errors(), true));
        }

        if($idRol == 1){
            // AMBIENTE DE PRODUCCION 
            $sql = "SELECT  pp.id_predio, pp.id as id, (SELECT p.codigo_predio from predios p where p.id=pp.id_predio) as codigo,
                    pp.avaluo as avaluo,
                    (SELECT pro.identificacion from propietarios pro where pro.id = (SELECT ppr.id_propietario from predios_propietarios ppr where ppr.id_predio=pp.id_predio and ppr.jerarquia = 001)) as identificacionPropietario,
                    (SELECT pro.nombre from propietarios pro where pro.id = (SELECT ppr.id_propietario from predios_propietarios ppr where ppr.id_predio=pp.id_predio and ppr.jerarquia = 001)) as nombrePropietario,
                    (SELECT pro.direccion from propietarios pro where pro.id = (SELECT ppr.id_propietario from predios_propietarios ppr where ppr.id_predio=pp.id_predio and ppr.jerarquia = 001)) as direccionPropietarioAnterior,
                    (SELECT pred.direccion from predios pred where pred.id = pp.id_predio) as direccionPropietario,
                    (select pr.matricula_inmobiliaria from predios_datos pr where id_predio = pp.id_predio) as matricula,
                    pp.ultimo_anio as anio,
                    pp.total_calculo as valorAproximado,
                    pp.estado_moroso as estadoMoroso,
                    CAST(pp.fecha_asignacion_moroso AS DATE) AS fechaInvestigacion,
                    CAST(pp.fecha_asignacion_factura_moroso AS DATE) AS fechaFactura,
                    CAST(pp.fecha_asignacion_publicacion_moroso AS DATE) AS fechaPublicacion,
                    CAST(pp.fecha_asignacion_constancia_moroso AS DATE) AS fechaConstancia,
                    CAST(pp.fecha_asignacion_mandamiento_moroso AS DATE) AS fechaMandamiento,
                    CAST(pp.fecha_autoArchivo_moroso AS DATE) AS fechaAutoArchivo,
                    pp.folio_estado_investigacion AS folioInvestigacion,
                    CAST(pp.fecha_pago AS DATE) AS fechaPago, pp.pagado AS pagado
                    

                from predios_pagos pp where pp.estado_moroso = $estado ";
        }else{
            $sql = "SELECT  pp.id_predio, pp.id as id, (SELECT p.codigo_predio from predios p where p.id=pp.id_predio) as codigo,
                    pp.avaluo as avaluo,
                    (SELECT pro.identificacion from propietarios pro where pro.id = (SELECT ppr.id_propietario from predios_propietarios ppr where ppr.id_predio=pp.id_predio and ppr.jerarquia = 001)) as identificacionPropietario,
                    (SELECT pro.nombre from propietarios pro where pro.id = (SELECT ppr.id_propietario from predios_propietarios ppr where ppr.id_predio=pp.id_predio and ppr.jerarquia = 001)) as nombrePropietario,
                    (SELECT pro.direccion from propietarios pro where pro.id = (SELECT ppr.id_propietario from predios_propietarios ppr where ppr.id_predio=pp.id_predio and ppr.jerarquia = 001)) as direccionPropietarioAnterior,
                    (SELECT pred.direccion from predios pred where pred.id = pp.id_predio) as direccionPropietario,
                    (select pr.matricula_inmobiliaria from predios_datos pr where id_predio = pp.id_predio) as matricula,
                    pp.ultimo_anio as anio,
                    pp.total_calculo as valorAproximado,
                    pp.estado_moroso as estadoMoroso,
                    CAST(pp.fecha_asignacion_moroso AS DATE) AS fechaInvestigacion,
                    CAST(pp.fecha_asignacion_factura_moroso AS DATE) AS fechaFactura,
                    CAST(pp.fecha_asignacion_publicacion_moroso AS DATE) AS fechaPublicacion,
                    CAST(pp.fecha_asignacion_constancia_moroso AS DATE) AS fechaConstancia,
                    CAST(pp.fecha_asignacion_mandamiento_moroso AS DATE) AS fechaMandamiento,
                    CAST(pp.fecha_autoArchivo_moroso AS DATE) AS fechaAutoArchivo,
                    pp.folio_estado_investigacion AS folioInvestigacion,
                    CAST(pp.fecha_pago AS DATE) AS fechaPago, pp.pagado AS pagado

                from predios_pagos pp where pp.estado_moroso = $estado and pp.idUsuario= $idUsuario";
		}
		/* Se agrega el campo ( estado_fiscalizacion )  
		0: Libre
		1: Investigación
		2: Factura
		3: Publicacion
		4: Constancia.
		5: Mandamiento
		*/

        // and pp.estado_moroso = 0
        $stmt = sqlsrv_query( $conn, $sql );
        $row = FALSE;
        if( $stmt === false) {
            $row = FALSE;
            die( print_r( sqlsrv_errors(), true) );
            $this->_ok = 0;
            $this->_mensaje = "No existen Predios";  
        }else{
            while( $res = sqlsrv_fetch_array( $stmt, SQLSRV_FETCH_ASSOC) ) {
                    $row[] = $res;
             }
			if($row != FALSE){
                $this->_ok = 1;
                $this->_mensaje = "Predios listadas con exito";
			}else{
			
                $this->_ok = 0;
                $this->_mensaje = "Predios listadas con exito";}
			
        }

        return $row;  
    }
	
	
		/**
    *** Realiza el proceso de translado de estado en fiscalización.
    **/ 
    public function _transladarPredioFizcalizaciónInves() {

        $idPredio = $_POST['idPredio'];
        $anio = $_POST['anio'];
		$estado = $_POST['estado'];
		$idUsuario = $_POST['idUsuario'];
		
        // LOCAL
        $serverName = "DESARROLLO\SQLEXPRESS2019";
        $connectionInfo = array( "Database"=>"erpsofts_paipa_test", "UID"=>"sa", "PWD"=>"Server2019");
        
        // PRODUCCIÓN  
        //$serverName = "167.114.216.134\\MSSQLSERVER2019";
        //$connectionInfo = array( "Database"=>"erpsofts_paipa_test", "UID"=>"erpsofts_pse", "PWD"=>"predial123.");
        
        $conn = sqlsrv_connect( $serverName, $connectionInfo);
		
        if( $conn === false ) {
            die( print_r(sqlsrv_errors(), true));
        }
		
		$queryye = "EXEC [API].[SP_UPDATE_ESTADO_PREDIOSPAGOS_MOROSOS_INVES] @Annio = $anio, @IdPredio = $idPredio, @Estado = $estado, @idUsuario = $idUsuario";
		$stmt = sqlsrv_query( $conn, $queryye);

        if( $stmt === false) {
            $row = FALSE;
            die( print_r( sqlsrv_errors(), true) );
            $this->_ok = 0;
            $this->_mensaje = "No Translado";  
        }else{
            while( $res = sqlsrv_fetch_array( $stmt, SQLSRV_FETCH_ASSOC) ) {
                    $row[] = $res;
             }
             $row= true;
             $this->_ok = 1;
             $this->_mensaje = "Translado con exito".$anio.'---'.$idPredio.'---'.$estado;
        }

        return $row;  
    }


    /**
    *** Realiza el proceso de translado _agregarInvestigacion.
    **/ 
    public function _agregarInvestigacion() {

        $idPredio = $_POST['idPredio'];
		$anio = $_POST['anio'];
        $codigoPredio = $_POST['codigoPredio'];    
		$pre_Folio = $_POST['pre_Folio'];
        $pre_Observaciones = $_POST['pre_Observaciones'];

        $micarpetaUno = '../../PROCESO_FISCALIZACION/'.$codigoPredio;
        if (!file_exists($micarpetaUno)) {
            mkdir("../../PROCESO_FISCALIZACION/".$codigoPredio, 0700);
        }
        
        $micarpeta = '../../PROCESO_FISCALIZACION/'.$codigoPredio.'/INVESTIGACION';
        if (!file_exists($micarpeta)) {
            mkdir("../../PROCESO_FISCALIZACION/".$codigoPredio."/INVESTIGACION", 0700);
        }

        if ($_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['file']['tmp_name'];
            //$fileName = $_FILES['file']['name'];
            $fileName = $pre_Folio.'.pdf';
            $uploadFileDir = '../../PROCESO_FISCALIZACION/'.$codigoPredio.'/INVESTIGACION'.'/';
            $destPath = $uploadFileDir . $fileName;
        
            move_uploaded_file($fileTmpPath, $destPath);
        }

		
        // LOCAL
        $serverName = "DESARROLLO\SQLEXPRESS2019";
        $connectionInfo = array( "Database"=>"erpsofts_paipa_test", "UID"=>"sa", "PWD"=>"Server2019");
        
        // PRODUCCIÓN  
        //$serverName = "167.114.216.134\\MSSQLSERVER2019";
        //$connectionInfo = array( "Database"=>"erpsofts_paipa_test", "UID"=>"erpsofts_pse", "PWD"=>"predial123.");
        
        $conn = sqlsrv_connect( $serverName, $connectionInfo);
		
        if( $conn === false ) {
            die( print_r(sqlsrv_errors(), true));
        }
		
		$queryye = "EXEC [API].[SP_UPDATE_ESTADO_PREDIOSPAGOS_MOROSOS_INVES_VUR] @IdPredio = $idPredio, @Anio = $anio, @Folio = '$pre_Folio', @Observaciones = '$pre_Observaciones'";

		$stmt = sqlsrv_query( $conn, $queryye);

        if( $stmt === false) {
            $row = FALSE;
            die( print_r( sqlsrv_errors(), true) );
            $this->_ok = 0;
            $this->_mensaje = "No Insertado";  
        }else{
            while( $res = sqlsrv_fetch_array( $stmt, SQLSRV_FETCH_ASSOC) ) {
                    $row[] = $res;
             }
             $row= true;
             $this->_ok = 1;
             $this->_mensaje = "Datos Cargados con exito".$anio.'---'.$idPredio.'---';
        }

        return $row;  
    }


     	/**
    *** Realiza el proceso de Consultar Predios con Documentación.
    **/ 
    public function _consultarHojaVida() {

        $cod_predio = $_POST['cod_predio'];
        $fecha = $_POST['fecha'];

        // LOCAL
        $serverName = "DESARROLLO\SQLEXPRESS2019";
        $connectionInfo = array( "Database"=>"erpsofts_paipa_test", "UID"=>"sa", "PWD"=>"Server2019");
        
        // PRODUCCIÓN  
        //$serverName = "167.114.216.134\\MSSQLSERVER2019";
        //$connectionInfo = array( "Database"=>"erpsofts_paipa_test", "UID"=>"erpsofts_pse", "PWD"=>"predial123.");
        
        $conn = sqlsrv_connect( $serverName, $connectionInfo);

        if( $conn === false ) {
            die( print_r(sqlsrv_errors(), true));
        }

        $sql = "SELECT prepa.*, pre.codigo_predio as codigo,
        (SELECT pro.nombre from propietarios pro where pro.id = (SELECT ppr.id_propietario from predios_propietarios ppr where ppr.id_predio=prepa.id_predio and ppr.jerarquia = 001)) as nombrePropietario,
        (SELECT pro.identificacion from propietarios pro where pro.id = (SELECT ppr.id_propietario from predios_propietarios ppr where ppr.id_predio=prepa.id_predio and ppr.jerarquia = 001)) as identificacionPropietario
		from predios pre INNER JOIN predios_pagos prepa on pre.id=prepa.id_predio
		where pre.codigo_predio = '$cod_predio' and prepa.ultimo_anio = $fecha 
        and prepa.estado_moroso >= 1";
            

        // and pp.estado_moroso = 0
        $stmt = sqlsrv_query( $conn, $sql );
        $row = FALSE;
        if( $stmt === false) {
            $row = false;
            die( print_r( sqlsrv_errors(), true) );
            $this->_ok = 0;
            $this->_mensaje = "No existen Predios";  
        }else{
            while( $res = sqlsrv_fetch_array( $stmt, SQLSRV_FETCH_ASSOC) ) {
                    $row[] = $res;
             }
             if($row != false){
                $this->_ok = 1;
                $this->_mensaje = "Predios listadas con exito";
			}else{
			
                $this->_ok = 0;
                $this->_mensaje = "Predios listadas con exito";
            }
        }

        return $row;  
    }


}

class PredialGestionException extends \Exception{}

    \predial\ControladorPredialGestion::run();

