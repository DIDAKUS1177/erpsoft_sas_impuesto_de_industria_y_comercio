<?php
namespace predial;

use Exception;

include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_FacturaDocumento.php';
include_once SERVER . '/business/DAO/DAO_FacturaDetalleDocumento.php';
include_once SERVER . '/business/DAO/DAO_Tesoreria.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER . '/business/controller/class.logs.php';

class ControladorInformesCuentasPorPagar extends \predial\Cabecera {

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
                    $respuesta = $_obj->_cuentasPorPagarGeneral();
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
                    header('Content-Disposition:; filename="cuentasPorPagar.xls"');
                    header("Content-Transfer-Encoding: binary");
                    echo utf8_decode("<table border='0'> 
                    <tr> 
                        <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> REPORTE </td> 	
					    <td style='font-weight:bold; border:1px solid #eee;'> DE </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Cuentas por </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Pagar </td> 		
                        <td style='font-weight:bold; border:1px solid #eee;'>  </td> 		
                        <td style='font-weight:bold; border:1px solid #eee;'>  </td> 
					</tr>
                    <tr> 
                        <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Fecha Inicial: </td> 	     
                        <td style='font-weight:bold; border:1px solid #eee;'> ".$_POST['txtFechaInicio']."</td>
                        <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Fecha Final: </td>
                        <td style='font-weight:bold; border:1px solid #eee;'> ".$_POST['txtFechaFinal']." </td>
                        <td style='font-weight:bold; border:1px solid #eee;'> </td>
                        <td style='font-weight:bold; border:1px solid #eee;'> </td>
                    </tr>");

                        echo "<tr> 
                        <td style='font-weight:bold; border:1px solid #eee;'> Fecha </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Numero </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Proveedor </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Observaciónes </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Forma de Pago </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Valor Total </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Abonos/Pagos </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> </td>
					    </tr>";

                        foreach ($respuesta as $row => $item){

                            if( 0 == $item["estad"] ){
                                
                                echo utf8_decode("<tr><td style='border:1px solid #eee;'>".$item["fecha"]."</td>");
                                echo utf8_decode("<td style='border:1px solid #eee;'>".$item["numero"]."</td>");
                                echo utf8_decode("<td style='border:1px solid #eee;'>".$item["nombreProveedor"]."</td>");
                                echo utf8_decode("<td style='border:1px solid #eee;'>".$item["observaciones"]."</td>");
                                echo utf8_decode("<td style='border:1px solid #eee;'>".$item["tipoPago"]."</td>");
                                echo utf8_decode("<td style='border:1px solid #eee;'>".$item["totalEntrada"]."</td>");
                                echo utf8_decode("<td style='border:1px solid #eee;'>".$item["totalAbonos"]."</td>");
                                echo utf8_decode("<td style='border:1px solid #eee;'> </td>");
                                echo utf8_decode("<td style='border:1px solid #eee;'> </td>");
                            }
                            
                        }
                        echo "</table>";
                        
                    break;
                
            }
            // echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
        } catch (\predial\NotaException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    /**
    *** Realiza el proceso de Generar Informes
    **/
    protected function _cuentasPorPagarGeneral(){
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();

        $cupa_EstadoCuenta =  $_POST['cupa_EstadoCuenta']; 
        $fechaInicial =  $_POST['txtFechaInicio']; 
        $fechaFinal =  $_POST['txtFechaFinal'];   
        
        if($fechaInicial == $fechaFinal){
            $query = "SELECT kar.kar_EstadoPago as estad, kar.kar_Fecha as fecha, kar.kar_Id as numero, kar.kar_Observaciones as observaciones,
            case kar.kar_TipoPago  when 1 then 'Contado'  when 2 then 'Credito' end as tipoPago,
            (SELECT SUM(Round(deta.detkar_ValorEntrada, 0)) from inv_detalle_kardex as deta WHERE deta.detkar_IdKardex=kar.kar_Id) as totalEntrada, 
            (SELECT pro.prov_Nombre from inv_proveedores as pro WHERE pro.prov_Id = kar.kar_IdProveedor) as nombreProveedor, 
            (SELECT SUM(Round(cupa.cupa_Valor, 0)) from fac_cuentas_por_pagar as cupa WHERE cupa.cupa_IdNota=kar.kar_Id) as totalAbonos
             FROM inv_kardex as kar
            where kar.kar_EstadoPago = 0 and kar.kar_Tipo = 1 and kar_IdProveedor IS NOT NULL and
            kar.kar_Fecha like '%$fechaInicial%'";
		}else{
            $query = "SELECT kar.kar_EstadoPago as estad, kar.kar_Fecha as fecha, kar.kar_Id as numero, kar.kar_Observaciones as observaciones,
            case kar.kar_TipoPago  when 1 then 'Contado'  when 2 then 'Credito' end as tipoPago,
            (SELECT SUM(Round(deta.detkar_ValorEntrada, 0)) from inv_detalle_kardex as deta WHERE deta.detkar_IdKardex=kar.kar_Id) as totalEntrada, 
            (SELECT pro.prov_Nombre from inv_proveedores as pro WHERE pro.prov_Id = kar.kar_IdProveedor) as nombreProveedor, 
            (SELECT SUM(Round(cupa.cupa_Valor, 0)) from fac_cuentas_por_pagar as cupa WHERE cupa.cupa_IdNota=kar.kar_Id) as totalAbonos
             FROM inv_kardex as kar
             where kar.kar_EstadoPago = 0 and kar.kar_Tipo=1 and kar_IdProveedor IS NOT NULL and
            ((kar.kar_Fecha BETWEEN '$fechaInicial' AND '$fechaFinal') or kar.kar_Fecha like '%$fechaFinal%')";
        }
        
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
        }else{
            $row[] = NULL;
        }
        return $row;  
    }

}

class InformesCuentasPorPagarException extends \Exception{}

    \predial\ControladorInformesCuentasPorPagar::run();

