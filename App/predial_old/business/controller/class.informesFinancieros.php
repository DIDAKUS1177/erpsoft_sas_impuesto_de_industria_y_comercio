<?php
namespace predial;

use Exception;

include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_FacturaDocumento.php';
include_once SERVER . '/business/DAO/DAO_FacturaDetalleDocumento.php';
include_once SERVER . '/business/DAO/DAO_Tesoreria.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER .'/business/controller/class.logs.php';

class ControladorInformesFinancieros extends \predial\Cabecera {

    private $_funcion;
    private $_ok;
    private $_mensaje;
    private $_utilidad;
        
    public static function run() {
        \predial\SesionUsuario::verificarSesion();
        
        $_obj = new self();
        $_obj->_funcion = $_POST['mod_IdModuloFinanciero'];
        
        try {
            $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
            $con->begin();
            $respuesta = null;
            switch ($_obj->_funcion) {
                case 1:
                    $respuesta = $_obj->_InformeUtilidades();
                    break;
                case 2:
                    $respuesta = $_obj->_InformeCostos();
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
                    header('Content-Disposition:; filename="utilidades.xls"');
                    header("Content-Transfer-Encoding: binary");
                    echo utf8_decode("<table border='0'> 
                    <tr> 
                        <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> REPORTE </td> 	
					    <td style='font-weight:bold; border:1px solid #eee;'> DE </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Utilidades </td> 	
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
                    </tr>
					<tr> 
                        <td style='font-weight:bold; border:1px solid #eee;'> Producto </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Cantidad </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Precio Costo Unidad </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Precio Costo Total </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Precio Venta Unidad </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Precio Venta Total </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Utilidad Total </td> 	

					</tr>");
                    foreach ($respuesta as $row => $item){
                        echo utf8_decode("<tr><td style='border:1px solid #eee;'>".$item["nombre"]."</td>");
                        echo utf8_decode("<td style='border:1px solid #eee;'>".$item["cantidad"]."</td>");
                        echo utf8_decode("<td style='border:1px solid #eee;'>".$item["precioCosto"]."</td>");
                        echo utf8_decode("<td style='border:1px solid #eee;'>".$item["precioCosto"]*$item["cantidad"]."</td>");
                        echo utf8_decode("<td style='border:1px solid #eee;'>".$item["precioUnitario"]."</td>");
                        echo utf8_decode("<td style='border:1px solid #eee;'>".$item["precioTotal"]."</td>");
                        $_utilidad= $item["precioTotal"]-($item["precioCosto"]*$item["cantidad"]);
                        echo utf8_decode("<td style='border:1px solid #eee;'>".$_utilidad."</td>");
                    }
                    echo "</table>";
                    break;
                case 2:
                            
                    setlocale(LC_ALL, 'es_CO.UTF-8');
                    $fecha = strftime(" %d / %m / %Y");
                    header('Expires: 0');
                    header('Cache-control: private');
                    header("Content-type: application/vnd.ms-excel"); // Archivo de Excel
                    header("Cache-Control: cache, must-revalidate"); 
                    header('Content-Description: File Transfer');
                    header('Last-Modified: '.date('D, d M Y H:i:s'));
                    header("Pragma: public"); 
                    header('Content-Disposition:; filename="costos.xls"');
                    header("Content-Transfer-Encoding: binary");
                    echo utf8_decode("<table border='0'> 
                    <tr> 
                        <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> REPORTE </td> 	
					    <td style='font-weight:bold; border:1px solid #eee;'> DE </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Costos </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'>  </td> 
					</tr>
                    <tr> 
                        <td style='font-weight:bold; border:1px solid #eee;'> </td>
                        <td style='font-weight:bold; border:1px solid #eee;'> Fecha: </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> ".$fecha."</td>
                        <td style='font-weight:bold; border:1px solid #eee;'> Coste:</td>
                        <td style='font-weight:bold; border:1px solid #eee;'> Ultimo </td>
                        <td style='font-weight:bold; border:1px solid #eee;'> </td>
					</tr>
                    <tr> 
                        <td style='font-weight:bold; border:1px solid #eee;'> </td>
                        <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> </td>
                        <td style='font-weight:bold; border:1px solid #eee;'> </td>
                        <td style='font-weight:bold; border:1px solid #eee;'> </td>
                        <td style='font-weight:bold; border:1px solid #eee;'> </td>
                    </tr>
					<tr> 
                        <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Producto </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Cantidad </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Costo Unitario </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Costo Total </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
					</tr>");
                    foreach ($respuesta as $row => $item){
                        if(!is_null($item["cantidad"]) and !is_null($item["precioUnitario"])){
                            echo utf8_decode("<tr><td style='border:1px solid #eee;'></td>");
                            echo utf8_decode("<td style='border:1px solid #eee;'>".$item["nombre"]."</td>");
                            echo utf8_decode("<td style='border:1px solid #eee;'>".$item["cantidad"]."</td>");
                            echo utf8_decode("<td style='border:1px solid #eee;'>".$item["precioUnitario"]."</td>");
                            echo utf8_decode("<td style='border:1px solid #eee;'>".$item["cantidad"]*$item["precioUnitario"]."</td>");
                            echo utf8_decode("<td style='border:1px solid #eee;'></td></tr>");
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
    protected function _InformeUtilidades(){
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();

        $fechaInicial =  $_POST['txtFechaInicio']; 
        $fechaFinal =  $_POST['txtFechaFinal'];       
        
        if($fechaInicial == $fechaFinal){
            $query = "SELECT ip.pro_Nombre as nombre, fdd.detDoc_Cantidad as cantidad, 
                    Round(fdd.detDoc_ValorUnitario, 0) as precioUnitario, Round(fdd.detDoc_ValorTotal, 0) as precioTotal,
                    (select Round(idk.detkar_ValorUnitario, 0) as valor from inv_detalle_kardex as idk 
                        where (idk.detkar_IdProducto = fdd.detDoc_IdProducto) and idk.detkar_CantidadEntrada IS NOT NULL ORDER BY idk.detkar_Id DESC LIMIT 1) as precioCosto
                    FROM fac_documento as fd INNER JOIN fac_detalle_documento as fdd on fd.doc_Id = fdd.detDoc_IdDocumento
                    INNER JOIN inv_producto as ip on fdd.detDoc_IdProducto = ip.pro_Id
                    where fd.doc_Estado = 1 and fd.doc_Fecha like '%$fechaInicial%'";
		}else{
            $query = "SELECT ip.pro_Nombre as nombre, fdd.detDoc_Cantidad as cantidad, 
                    Round(fdd.detDoc_ValorUnitario, 0) as precioUnitario, Round(fdd.detDoc_ValorTotal, 0) as precioTotal,
                    (select Round(idk.detkar_ValorUnitario, 0) as valor from inv_detalle_kardex as idk 
                        where (idk.detkar_IdProducto = fdd.detDoc_IdProducto) and idk.detkar_CantidadEntrada IS NOT NULL ORDER BY idk.detkar_Id DESC LIMIT 1) as precioCosto
                    FROM fac_documento as fd INNER JOIN fac_detalle_documento as fdd on fd.doc_Id = fdd.detDoc_IdDocumento
                    INNER JOIN inv_producto as ip on fdd.detDoc_IdProducto = ip.pro_Id
                    where fd.doc_Estado = 1 and (fd.doc_Fecha BETWEEN '$fechaInicial' AND '$fechaFinal') or fd.doc_Fecha like '%$fechaFinal%'";
        }
       
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            //$this->_ok = 1;
            //$this->_mensaje = "Informe FACTURAS";
        }else{
            //$this->_ok = 0;
            //$this->_mensaje = "No existen Formas de Pago";
            $row[] = NULL;
        }
        return $row;  
    }

    /**
    *** Realiza el proceso de Generar por Cantidad de Produtos.
    **/
    protected function _InformeCostos(){
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();

        $fechaInicial =  $_POST['txtFechaInicio']; 
        $fechaFinal =  $_POST['txtFechaFinal']; 
 
        $query = "SELECT fdd.pro_Nombre as nombre,  (select SUM(ie.exi_Cantidad) from inv_existencias as ie 
                    where ie.exi_IdProducto = fdd.pro_Id GROUP BY ie.exi_IdProducto) as cantidad,
                    (select Round(idk.detkar_ValorUnitario, 0) from inv_detalle_kardex as idk 
                        WHERE idk.detkar_CantidadSalida IS NULL and idk.detkar_IdProducto = fdd.pro_Id
                        ORDER BY idk.detkar_Id DESC LIMIT 1) as precioUnitario
                    FROM inv_producto as fdd";

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

class InformesException extends \Exception{}

    \predial\ControladorInformesFinancieros::run();

