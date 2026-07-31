<?php
namespace predial;

use Exception;

include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_FacturaDocumento.php';
include_once SERVER . '/business/DAO/DAO_FacturaDetalleDocumento.php';
include_once SERVER . '/business/DAO/DAO_Tesoreria.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER . '/business/controller/class.logs.php';

class ControladorInformes extends \predial\Cabecera {

    private $_funcion;
    private $_ok;
    private $_mensaje;
        
    public static function run() {
        \predial\SesionUsuario::verificarSesion();
        
        $_obj = new self();

        if(isset($_POST['mod_IdModulo'])){
            $_obj->_funcion = $_POST['mod_IdModulo'];
        }
		if(isset($_POST['mod_IdModuloMorosos'])){
            $_obj->_funcion = $_POST['mod_IdModuloMorosos'];
        }

        try {
            $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
            $con->begin();
            $respuesta = null;
            switch ($_obj->_funcion) {
                case 1:
                    $respuesta = $_obj->_InformeConDocumentacionExcel();
                    break;
                case 2:
                    $respuesta = $_obj->_InformeSinDocumentacionExcel();
                    break;
                case 3:
                    $respuesta = $_obj->_InformeMorososPorAnio();
                    break;
            }
            $con->commit();
   
            switch ($_obj->_funcion) {
                case 1:
                    header('Expires: 0');
                    header('Cache-control: private');
                    header("Content-type: application/vnd.ms-excel"); // Archivo de Excel
                    header("Cache-Control: cache, must-revalidate"); 
                    header('Content-Description: File Transfer');
                    header('Last-Modified: '.date('D, d M Y H:i:s'));
                    header("Pragma: public"); 
                    header('Content-Disposition:; filename="informeConDocumentacion.xls"');
                    header("Content-Transfer-Encoding: binary");
                    echo utf8_decode("<table border='0'> 
                    <tr> 	
                        <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> REPORTE </td> 	
					    <td style='font-weight:bold; border:1px solid #eee;'> DE </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> PREDIOS </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
					</tr>
                    <tr> 
                        <td style='font-weight:bold; border:1px solid #eee;'> </td>
                        <td style='font-weight:bold; border:1px solid #eee;'> </td>
                        <td style='font-weight:bold; border:1px solid #eee;'> Fecha Año: </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> ".$_POST['txtFechaInicio']."</td>
                        <td style='font-weight:bold; border:1px solid #eee;'> Fecha Mes: </td>
                        <td style='font-weight:bold; border:1px solid #eee;'> ".$_POST['txtFechaFinal']."</td>
                        <td style='font-weight:bold; border:1px solid #eee;'> </td>
                    </tr>

					<tr> 
                        <td style='font-weight:bold; border:1px solid #eee;'> </td>    
                        <td style='font-weight:bold; border:1px solid #eee;'> Codigo Predio </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Nombre Propietario </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Dirección </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Ultimo Año Pago </td> 
                        <td style='font-weight:bold; border:1px solid #eee;'> Usuario Generado </td> 
						<td style='font-weight:bold; border:1px solid #eee;'> Fecha Generado </td> 
                        <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
	
					</tr>");
                    foreach ($respuesta as $row => $item){
                        echo utf8_decode("<tr><td style='font-weight:bold; border:1px solid #eee;'></td>");
                        echo utf8_decode("<td style='border:1px solid #eee;'>'".$item["codigo_predio"]."'</td>");
                        echo utf8_decode("<td style='border:1px solid #eee;'>".$item["nombre"]."</td>");
                        echo utf8_decode("<td style='border:1px solid #eee;'>".$item["direccion"]."</td>");
                        echo utf8_decode("<td style='border:1px solid #eee;'>".$item["ultimo_anio_pago"]."</td>");
                        echo utf8_decode("<td style='border:1px solid #eee;'>".$item["nom_usu_documentacion"]."</td>");        
						echo utf8_decode("<td style='border:1px solid #eee;'>".$item["fecha"]."/".$item["mes"]."/".$item["dia"]."</td>");        
                        echo utf8_decode("<td style='border:1px solid #eee;'> </td></tr>");
                    }
                    echo "</table>";
                    break;

                    case 2:
                        header('Expires: 0');
                        header('Cache-control: private');
                        header("Content-type: application/vnd.ms-excel"); // Archivo de Excel
                        header("Cache-Control: cache, must-revalidate"); 
                        header('Content-Description: File Transfer');
                        header('Last-Modified: '.date('D, d M Y H:i:s'));
                        header("Pragma: public"); 
                        header('Content-Disposition:; filename="informeSinDocumentacion.xls"');
                        header("Content-Transfer-Encoding: binary");
                        echo utf8_decode("<table border='0'> 
                        <tr> 	
                            <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> REPORTE </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> DE </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> PREDIOS </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                        </tr>
                        <tr> 
                            <td style='font-weight:bold; border:1px solid #eee;'> </td>
                            <td style='font-weight:bold; border:1px solid #eee;'> </td>
                            <td style='font-weight:bold; border:1px solid #eee;'> Fecha Año Ultimo Pago: </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> ".$_POST['id_anioPago']."</td>
                            <td style='font-weight:bold; border:1px solid #eee;'> </td>
                            <td style='font-weight:bold; border:1px solid #eee;'> </td>
                            <td style='font-weight:bold; border:1px solid #eee;'> </td>
                        </tr>
    
                        <tr> 
                            <td style='font-weight:bold; border:1px solid #eee;'> </td>    
                            <td style='font-weight:bold; border:1px solid #eee;'> Codigo Predio </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> Nombre Propietario </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> Dirección </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> Ultimo Año Pago </td> 
                            <td style='font-weight:bold; border:1px solid #eee;'> Usuario Generado </td> 
                            <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
        
                        </tr>");
                        foreach ($respuesta as $row => $item){
                            echo utf8_decode("<tr><td style='font-weight:bold; border:1px solid #eee;'></td>");
                            echo utf8_decode("<td style='border:1px solid #eee;'>'".$item["codigo_predio"]."'</td>");
                            echo utf8_decode("<td style='border:1px solid #eee;'>".$item["nombre"]."</td>");
                            echo utf8_decode("<td style='border:1px solid #eee;'>".$item["direccion"]."</td>");
                            echo utf8_decode("<td style='border:1px solid #eee;'>".$item["ultimo_anio_pago"]."</td>");
                            echo utf8_decode("<td style='border:1px solid #eee;'>".$item["nom_usu_documentacion"]."</td>");        
                            echo utf8_decode("<td style='border:1px solid #eee;'> </td></tr>");
                        }
                        echo "</table>";
                        break;
 						
						case 3:
                            header('Expires: 0');
                            header('Cache-control: private');
                            header("Content-type: application/vnd.ms-excel"); // Archivo de Excel
                            header("Cache-Control: cache, must-revalidate"); 
                            header('Content-Description: File Transfer');
                            header('Last-Modified: '.date('D, d M Y H:i:s'));
                            header("Pragma: public"); 
                            header('Content-Disposition:; filename="informeMorosos.xls"');
                            header("Content-Transfer-Encoding: binary");
                            echo utf8_decode("<table border='0'> 
                            <tr> 	
                                <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                                <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                                <td style='font-weight:bold; border:1px solid #eee;'> </td>
                                <td style='font-weight:bold; border:1px solid #eee;'> REPORTE </td> 	
                                <td style='font-weight:bold; border:1px solid #eee;'> DE </td> 	
                                <td style='font-weight:bold; border:1px solid #eee;'> MOROSOS </td> 	
                                <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
								<td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                                <td style='font-weight:bold; border:1px solid #eee;'>  </td> 
                                <td style='font-weight:bold; border:1px solid #eee;'> </td>	
                            </tr>
                            <tr> 
                                <td style='font-weight:bold; border:1px solid #eee;'> </td>
                                <td style='font-weight:bold; border:1px solid #eee;'> </td>
                                <td style='font-weight:bold; border:1px solid #eee;'> </td>
                                <td style='font-weight:bold; border:1px solid #eee;'> Año Inicial: </td> 	
                                <td style='font-weight:bold; border:1px solid #eee;'> ".$_POST['id_anioPago']."</td>
                                <td style='font-weight:bold; border:1px solid #eee;'> Año Final: </td>
                                <td style='font-weight:bold; border:1px solid #eee;'> ".$_POST['id_anioPagoFinal']."</td> 
                                <td style='font-weight:bold; border:1px solid #eee;'> </td>
								<td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                                <td style='font-weight:bold; border:1px solid #eee;'> </td>
                            </tr>
        
                            <tr> 
                                <td style='font-weight:bold; border:1px solid #eee;'> </td>    
                                <td style='font-weight:bold; border:1px solid #eee;'> No </td> 
                                <td style='font-weight:bold; border:1px solid #eee;'> Codigo Predio </td> 	
								<td style='font-weight:bold; border:1px solid #eee;'> Matricula </td> 	
								<td style='font-weight:bold; border:1px solid #eee;'> Avaluo </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> NIT/C.C Propietario </td> 	
                                <td style='font-weight:bold; border:1px solid #eee;'> Propietario Principal</td> 	
                                <td style='font-weight:bold; border:1px solid #eee;'> Dirección </td> 	
                                <td style='font-weight:bold; border:1px solid #eee;'> Periodo </td> 
                            <td style='font-weight:bold; border:1px solid #eee;'> Valor Aproximado </td> 
                                <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
            
                            </tr>");
                            foreach ($respuesta as $row => $item){
                                echo utf8_decode("<tr><td style='font-weight:bold; border:1px solid #eee;'></td>");
                                echo utf8_decode("<td style='border:1px solid #eee;'>".$item["id"]."</td>");
                                echo utf8_decode("<td style='border:1px solid #eee;'>'".$item["codigo"]."'</td>");
								if($item["matricula"] == NULL){
									echo utf8_decode("<td style='border:1px solid #eee;'>'0'</td>");
								}else{
									echo utf8_decode("<td style='border:1px solid #eee;'>'".$item["matricula"]."'</td>");
								}
									
								
								
								echo utf8_decode("<td style='border:1px solid #eee;'>".$item["avaluo"]."</td>");
                                echo utf8_decode("<td style='border:1px solid #eee;'>".$item["identificacionPropietario"]."</td>");
                                echo utf8_decode("<td style='border:1px solid #eee;'>".$item["nombrePropietario"]."</td>");
                                echo utf8_decode("<td style='border:1px solid #eee;'>".$item["direccionPropietario"]."</td>");        
                                echo utf8_decode("<td style='border:1px solid #eee;'>".$item["anio"]."</td>");        
                                echo utf8_decode("<td style='border:1px solid #eee;'>".$item["valorAproximado"]."</td>");        
                                echo utf8_decode("<td style='border:1px solid #eee;'> </td></tr>");
                            }
                            echo "</table>";
                            break;
            }
            
        } catch (\predial\NotaException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    /**
    *** Realiza el proceso de Generar Informe Con Documentación.
    **/
    protected function _InformeConDocumentacionExcel(){
        
        // $fechaDia =  $_POST['txtFechaDia']; 
        $fechaMes =  $_POST['txtFechaMes']; 
        $fechaAnio =  $_POST['txtFechaAnio'];        

        // $serverName = "DESARROLLO\SQLEXPRESS01";
        // $connectionInfo = array( "Database"=>"erpsofts_paipa", "UID"=>"cristian", "PWD"=>"wah7d6cybp");
        $serverName = "167.114.216.134\\MSSQLSERVER2019";
        $connectionInfo = array( "Database"=>"erpsofts_paipa", "UID"=>"erpsofts_pse", "PWD"=>"predial123.");
        $conn = sqlsrv_connect( $serverName, $connectionInfo);

        if( $conn === false ) {
            die( print_r(sqlsrv_errors(), true));
        }
        
        $sql = "SELECT id, codigo_predio ,codigo_predio_anterior ,nom_usu_documentacion,
            direccion ,ultimo_anio_pago, (SELECT TOP(1)pr.nombre FROM predios_propietarios as pp inner join propietarios as pr 
                on pp.id_propietario=pr.id where pp.id_predio = p.id) as nombre, fecha_documentacion as fecha, 
					mes_documentacion as mes, dia_documentacion as dia
                FROM predios as p
                    where p.estado_documentacion = 1 and p.fecha_documentacion = $fechaAnio 
                        and p.mes_documentacion = $fechaMes
            ORDER BY p.codigo_predio DESC";

            // and p.dia_documentacion = $fechaDia
		
        $stmt = sqlsrv_query( $conn, $sql );


        if( $stmt === false) {
            $row[] = NULL;
            die( print_r( sqlsrv_errors(), true) ); 
        }else{
            while( $res = sqlsrv_fetch_array( $stmt, SQLSRV_FETCH_ASSOC) ) {
                    $row[] = $res;
             }
        }

        return $row;  
    }

    /**
    *** Realiza el proceso de Generar Informe Sin Documentación.
    **/
    protected function _InformeSinDocumentacionExcel(){

        $fecha = $_POST['id_anioPago'];         

        // $serverName = "DESARROLLO\SQLEXPRESS01";
        // $connectionInfo = array( "Database"=>"erpsofts_paipa", "UID"=>"cristian", "PWD"=>"wah7d6cybp");
        $serverName = "167.114.216.134\\MSSQLSERVER2019";
        $connectionInfo = array( "Database"=>"erpsofts_paipa", "UID"=>"erpsofts_pse", "PWD"=>"predial123.");
        $conn = sqlsrv_connect( $serverName, $connectionInfo);

        if( $conn === false ) {
            die( print_r(sqlsrv_errors(), true));
        }
        
        $sql = "SELECT id, codigo_predio ,codigo_predio_anterior ,
                direccion ,ultimo_anio_pago, (SELECT TOP(1)pr.nombre FROM predios_propietarios as pp inner join propietarios as pr 
					on pp.id_propietario=pr.id where pp.id_predio = p.id) as nombre
                FROM predios as p
                    where p.ultimo_anio_pago = $fecha and p.estado_documentacion = 0 and p.ind_eliminado = 0 and p.ind_excento = 0
                ORDER BY p.ultimo_anio_pago DESC";
		
        $stmt = sqlsrv_query( $conn, $sql );


        if( $stmt === false) {
            $row[] = NULL;
            die( print_r( sqlsrv_errors(), true) ); 
        }else{
            while( $res = sqlsrv_fetch_array( $stmt, SQLSRV_FETCH_ASSOC) ) {
                    $row[] = $res;
             }
        }

        return $row;  
    }
	
	    /**
    *** Realiza el proceso de Generar Informe morosos por rango de año.
    **/
    protected function _InformeMorososPorAnio(){

        $fecha = $_POST['id_anioPago'];         
		$fechafinal = $_POST['id_anioPagoFinal'];    

        // $serverName = "DESARROLLO\SQLEXPRESS01";
        // $connectionInfo = array( "Database"=>"erpsofts_paipa", "UID"=>"cristian", "PWD"=>"wah7d6cybp");
        $serverName = "167.114.216.134\\MSSQLSERVER2019";
        $connectionInfo = array( "Database"=>"erpsofts_paipa", "UID"=>"erpsofts_pse", "PWD"=>"predial123.");
        $conn = sqlsrv_connect( $serverName, $connectionInfo);

        if( $conn === false ) {
            die( print_r(sqlsrv_errors(), true));
        }
        
        $sql = "SELECT pp.id as id, (SELECT p.codigo_predio from predios p where p.id=pp.id_predio) as codigo,
				pp.avaluo as avaluo,
                (SELECT pro.identificacion from propietarios pro where pro.id = (SELECT ppr.id_propietario from predios_propietarios ppr where ppr.id_predio=pp.id_predio and ppr.jerarquia = 001)) as identificacionPropietario,
                (SELECT pro.nombre from propietarios pro where pro.id = (SELECT ppr.id_propietario from predios_propietarios ppr where ppr.id_predio=pp.id_predio and ppr.jerarquia = 001)) as nombrePropietario,
                (SELECT pro.direccion from propietarios pro where pro.id = (SELECT ppr.id_propietario from predios_propietarios ppr where ppr.id_predio=pp.id_predio and ppr.jerarquia = 001)) as direccionPropietarioAnterior,
				(SELECT pred.direccion from predios pred where pred.id = pp.id_predio) as direccionPropietario,
				(select pr.matricula_inmobiliaria from predios_datos pr where id_predio = pp.id_predio) as matricula,
                pp.ultimo_anio as anio,
                pp.total_calculo as valorAproximado
		from predios_pagos pp where pp.pagado = 0 and (pp.ultimo_anio >= $fecha and pp.ultimo_anio <= $fechafinal)";
		
        $stmt = sqlsrv_query( $conn, $sql );


        if( $stmt === false) {
            $row[] = NULL;
            die( print_r( sqlsrv_errors(), true) ); 
        }else{
            while( $res = sqlsrv_fetch_array( $stmt, SQLSRV_FETCH_ASSOC) ) {
                    $row[] = $res;
             }
        }

        return $row;  
    }

}

class InformesException extends \Exception{}

    \predial\ControladorInformes::run();

