<?php
namespace predial;

use Exception;

include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_FacturaDocumento.php';
include_once SERVER . '/business/DAO/DAO_FacturaDetalleDocumento.php';
include_once SERVER . '/business/DAO/DAO_Tesoreria.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER . '/business/controller/class.logs.php';

class ControladorInformesEventos extends \predial\Cabecera {

    private $_funcion;
    private $_ok;
    private $_actividades;
    private $_ingresos;
    private $_egresos;
    private $_mensaje;
    private $_utilidad;
        
    public static function run() {
        \predial\SesionUsuario::verificarSesion();
        
        $_obj = new self();
        
        if(isset($_POST['mod_IdModulo'])){
            $_obj->_funcion = $_POST['mod_IdModulo'];
        }
        if(isset($_POST['mod_IdModuloFinanciero'])){
            $_obj->_funcion = $_POST['mod_IdModuloFinanciero'];
        }
        if(isset($_POST['mod_IdActividad'])){
            $_obj->_funcion = $_POST['mod_IdActividad'];
        }
        if(isset($_POST['mod_IdModuloEventoPyG'])){
            $_obj->_funcion = $_POST['mod_IdModuloEventoPyG'];
        }
        try {
            $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
            $con->begin();
            $respuesta = null;
            switch ($_obj->_funcion) {
                case 1:
                    $respuesta = $_obj->_InformeEvento();
                    break;
                case 2:
                    $respuesta = $_obj->_InformeEventoActividades();
                    break;
                case 3:
                    $respuesta = $_obj->_InformeActividades();
                    break;
                case 4:
                    $respuesta = $_obj->_InformePyG();
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
                    header('Content-Disposition:; filename="informeUtilidad.xls"');
                    header("Content-Transfer-Encoding: binary");
                    echo utf8_decode("<table border='0'> 
                    <tr> 
                        <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> REPORTE </td> 	
					    <td style='font-weight:bold; border:1px solid #eee;'> DE </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> UTILIDAD EVENTO </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
					</tr>
                    <tr> 
                        <td style='font-weight:bold; border:1px solid #eee;'> </td>
                        <td style='font-weight:bold; border:1px solid #eee;'> </td>
                        <td style='font-weight:bold; border:1px solid #eee;'> Fecha Inicial: </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> ".$_POST['txtFechaInicio']."</td>
                        <td style='font-weight:bold; border:1px solid #eee;'> Fecha Final: </td>
                        <td style='font-weight:bold; border:1px solid #eee;'> ".$_POST['txtFechaFinal']."</td>
                        <td style='font-weight:bold; border:1px solid #eee;'> </td>                        
                        <td style='font-weight:bold; border:1px solid #eee;'> </td>
                    </tr>
					<tr> 
                        <td style='font-weight:bold; border:1px solid #eee;'> </td>
                        <td style='font-weight:bold; border:1px solid #eee;'> Fecha </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Evento </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Cliente </td> 
                        <td style='font-weight:bold; border:1px solid #eee;'> Valor Total Evento </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Valor Egresos </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Utilidad </td>
                        <td style='font-weight:bold; border:1px solid #eee;'> </td>
					</tr>");
                    foreach ($respuesta as $row => $item){
                        echo utf8_decode("<tr><td style='border:1px solid #eee;'></td>");
                        echo utf8_decode("<td style='border:1px solid #eee;'>".$item["fecha"]."</td>");
                        echo utf8_decode("<td style='border:1px solid #eee;'>".$item["nombre"]."</td>");
                        echo utf8_decode("<td style='border:1px solid #eee;'>".$item["cliente"]."</td>");
                        echo utf8_decode("<td style='border:1px solid #eee;'>".$item["valor"]."</td>");
                        echo utf8_decode("<td style='border:1px solid #eee;'>".$item["egresos"]."</td>");
                        $_utilidad = $item["valor"] - $item["egresos"];
                        echo utf8_decode("<td style='border:1px solid #eee;'>".$_utilidad."</td>");
                        echo utf8_decode("<td style='border:1px solid #eee;'> </td>");
                        echo utf8_decode("<td style='border:1px solid #eee;'></td></tr>");
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
                    header('Content-Disposition:; filename="informeEvento.xls"');
                    header("Content-Transfer-Encoding: binary");
                    echo utf8_decode("<table border='0'> 
                    <tr> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> </td>
                        <td style='font-weight:bold; border:1px solid #eee;'> REPORTE </td> 	
					    <td style='font-weight:bold; border:1px solid #eee;'> DE </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> EVENTO / ACTIVIDADES </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> </td> 
                        <td style='font-weight:bold; border:1px solid #eee;'> </td>	
					</tr>
                    <tr> 
                        <td style='font-weight:bold; border:1px solid #eee;'> </td>    
                        <td style='font-weight:bold; border:1px solid #eee;'> Nombre Evento: </td>");

                        foreach ($respuesta as $row => $item){
                            echo utf8_decode("<td style='font-weight:bold; border:1px solid #eee;'> ".$item["nomEvento"]."</td>");
                            break;
                        }
                        
                        echo utf8_decode("<td style='font-weight:bold; border:1px solid #eee;'> </td>
                        <td style='font-weight:bold; border:1px solid #eee;'> </td>
                        <td style='font-weight:bold; border:1px solid #eee;'> </td>
					</tr>
					<tr> 
                        <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Actividad </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Valor Total </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Total Abonos </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Pendiente por Pago </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
					</tr>");
                    $totalValor=0;
                    $totalAbonos=0;
                    $totalUtilidad=0;
                    
                    foreach ($respuesta as $row => $item){

                        echo utf8_decode("<tr><td style='border:1px solid #eee;'></td>");
                        echo utf8_decode("<td style='border:1px solid #eee;'>".$item["nombre"]."</td>");
                        echo utf8_decode("<td style='border:1px solid #eee;'>".$item["valor"]."</td>");
                        echo utf8_decode("<td style='border:1px solid #eee;'>".$item["abonos"]."</td>");
                        $_utilidad = $item["valor"] - $item["abonos"];
                        echo utf8_decode("<td style='border:1px solid #eee;'>".$_utilidad."</td>");
                        echo utf8_decode("<td style='border:1px solid #eee;'></td></tr>");
                        $totalValor= $totalValor + $item["valor"];
                        $totalAbonos= $totalAbonos + $item["abonos"];
                        $totalUtilidad= $totalUtilidad + $_utilidad;
                    }

                    echo utf8_decode("
					<tr> 
                        <td style='font-weight:bold; border:1px solid #eee;'> </td>                        
                        <td style='font-weight:bold; border:1px solid #eee;'> TOTAL </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> $totalValor </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> $totalAbonos </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> $totalUtilidad </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
					</tr>");
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
                    header('Content-Disposition:; filename="informeActividad.xls"');
                    header("Content-Transfer-Encoding: binary");
                    echo utf8_decode("<table border='0'> 
                    <tr> 
                        <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	                        
                        <td style='font-weight:bold; border:1px solid #eee;'>  </td>
                        <td style='font-weight:bold; border:1px solid #eee;'> REPORTE </td> 	
					    <td style='font-weight:bold; border:1px solid #eee;'> DE </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> ACTIVIDAD </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> </td>
					</tr>
                    <tr> 
                        <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Actividad: </td>");
                        foreach ($respuesta as $row => $item){
                            echo utf8_decode("<td style='border:1px solid #eee;'>".$item["actividad"]."</td>");
                            break;
                        }
                    echo utf8_decode("
                        <td style='font-weight:bold; border:1px solid #eee;'> </td>
                        <td style='font-weight:bold; border:1px solid #eee;'> </td>
                        <td style='font-weight:bold; border:1px solid #eee;'> </td>
                        <td style='font-weight:bold; border:1px solid #eee;'> </td>
					</tr>
					<tr> 
                        <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Fecha </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Descripcion </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Valor </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Desconto de Cuentas </td> 
                        <td style='font-weight:bold; border:1px solid #eee;'> Nombre Cuenta </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
					</tr>");
                    foreach ($respuesta as $row => $item){
                        echo utf8_decode("<tr><td style='border:1px solid #eee;'></td>");
                        echo utf8_decode("<td style='border:1px solid #eee;'>".$item["egre_FechaCreacion"]."</td>");
                        echo utf8_decode("<td style='border:1px solid #eee;'>".$item["egre_Descripcion"]."</td>");
                        echo utf8_decode("<td style='border:1px solid #eee;'>".$item["egre_Valor"]."</td>");
                        if($item["egre_IdCuentaContable"] >= 1){
                            echo utf8_decode("<td style='border:1px solid #eee;'>Si</td>");
                            echo utf8_decode("<td style='border:1px solid #eee;'>".$item["cuentaContable"]."</td>");
                        }else{
                            echo utf8_decode("<td style='border:1px solid #eee;'>No</td>");
                            echo utf8_decode("<td style='border:1px solid #eee;'>No Ingreso a Cuenta Contable</td>");
                        }
                        echo utf8_decode("<td style='border:1px solid #eee;'></td></tr>");
                    }
                    echo "</table>";
                    break;

                    case 4:
                        header('Expires: 0');
                        header('Cache-control: private');
                        header("Content-type: application/vnd.ms-excel"); // Archivo de Excel
                        header("Cache-Control: cache, must-revalidate"); 
                        header('Content-Description: File Transfer');
                        header('Last-Modified: '.date('D, d M Y H:i:s'));
                        header("Pragma: public"); 
                        header('Content-Disposition:; filename="informePYG.xls"');
                        header("Content-Transfer-Encoding: binary");
                        echo utf8_decode("<table border='0'> 
                        <tr> 
                            <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'>  </td>                        
                            <td style='font-weight:bold; border:1px solid #eee;'> INFORME </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> GENERAL </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> DE </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> EVENTO </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'>  </td> 		
                        </tr>
                        <tr> 
                            <td> </td>
                        </tr>
                        <tr> 
                            <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> EVENTO </td>");
    
                        if($respuesta != 'error'){
                            foreach ($respuesta[0] as $row => $item){
                                echo utf8_decode("<td style='font-weight:bold; border:1px solid #eee;'>".$item["eve_Nombre"]."</td>");
                                echo utf8_decode("<td style='border:1px solid #eee;'></td>");
                                echo utf8_decode("<td style='border:1px solid #eee;'></td>");
                                echo utf8_decode("<td style='border:1px solid #eee;'></td></tr>");
                            }
                        }

                     
                        echo utf8_decode("<tr>
                        <tr>                         
                            <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> FECHA EVENTO </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> CLIENTE </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> TELEFONO </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> VALOR </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> LUGAR EVENTO </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> OBSERVACIONES </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                        </tr>");

//                        if($_obj->_ok != 'error'){
//                            foreach ($_obj->_ok as $row => $item){
                        if($respuesta != 'error'){
                            foreach ($respuesta[0] as $row => $item){
                                echo utf8_decode("<tr>");
                                echo utf8_decode("<td style='border:1px solid #eee;'></td>");
                                echo utf8_decode("<td style='border:1px solid #eee;'>".$item["eve_FechaEvento"]."</td>");
                                echo utf8_decode("<td style='border:1px solid #eee;'>".$item["eve_NombreCliente"]."</td>");
                                echo utf8_decode("<td style='border:1px solid #eee;'>".$item["eve_TelefonoCliente"]."</td>");
                                echo utf8_decode("<td style='border:1px solid #eee;'>".number_format($item["eve_ValorEvento"], 0, '', '.')."</td>");
                                echo utf8_decode("<td style='border:1px solid #eee;'>".$item["eve_LugarEvento"]."</td>");
                                echo utf8_decode("<td style='border:1px solid #eee;'>".$item["eve_Notas"]."</td>");
                                echo utf8_decode("<td style='border:1px solid #eee;'></td></tr>");
                            }
                        }

                        echo utf8_decode("<tr>
                        <tr>                         
                            <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> ACTIVIDADES </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> DEL </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> EVENTO </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                        </tr>
                        <tr><td></td></tr>
                        <tr>                         
                            <td style='font-weight:bold; border:1px solid #eee;'> FECHA </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> DESCRIPCIÓN </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> CATEGORIA </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> NOMBRE PROVEEDOR </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> RAZON SOCIAL PROVEEDOR </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> VALOR TOTAL </td>
                            <td style='font-weight:bold; border:1px solid #eee;'> VALOR PAGADO </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> VALOR SALDO </td> 	
                        </tr>");
                        
                        $totaltotalActividades=0;
                        

                        if($_obj->_actividades != 'error'){
                            foreach ($_obj->_actividades as $row => $item){
                                    $totaltotalActividadesEgre=0;
                                    echo utf8_decode("<tr>");
                                    echo utf8_decode("<td style='border:1px solid #eee;'>".$item["eva_FechaCreacion"]."</td>");
                                    echo utf8_decode("<td style='border:1px solid #eee;'>".$item["eva_Descripcion"]."</td>");
                                    echo utf8_decode("<td style='border:1px solid #eee;'>".$item["categoriaActividad"]."</td>");
                                    echo utf8_decode("<td style='border:1px solid #eee;'>".$item["nombreProveedor"]."</td>");
                                    echo utf8_decode("<td style='border:1px solid #eee;'>".$item["razonProveedor"]."</td>");
                                    echo utf8_decode("<td style='border:1px solid #eee;'>".number_format($item["eva_Valor"], 0, '', '.')."</td>");
                                    echo utf8_decode("<td style='border:1px solid #eee;'>".number_format($item["totalEgresos"], 0, '', '.')."</td>");
                                    $totaltotalActividadesEgre= $item["eva_Valor"] - $item["totalEgresos"];
                                    echo utf8_decode("<td style='border:1px solid #eee;'>".number_format($totaltotalActividadesEgre, 0, '', '.')."</td></tr>");
                                    $totaltotalActividades= $totaltotalActividades + $item["eva_Valor"];
                                }
                                
                            }
                            $totaltotalActividadess = number_format( $totaltotalActividades, 0, '', '.');

                        echo utf8_decode("
                        <tr> 
                            <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> TOTAL </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> $totaltotalActividadess </td> 	  
                            <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> </td> 	                            
                        </tr>");



                        echo utf8_decode("<tr>
                        <tr>                         
                            <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> INGRESOS </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> DEL </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> EVENTO </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                        </tr>
                        <tr><td></td></tr>
                        <tr>                         
                            <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> FECHA </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> DESCRIPCIÓN </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> CUENTA INGRESO</td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> VALOR </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> </td>
                            
                        </tr>");
                        
                        $totaltotalIngresos=0;

                        if($_obj->_ingresos != 'error'){
                            foreach ($_obj->_ingresos as $row => $item){
                                    echo utf8_decode("<tr>");
                                    echo utf8_decode("<td style='border:1px solid #eee;'> </td>");
                                    echo utf8_decode("<td style='border:1px solid #eee;'> </td>");
                                    echo utf8_decode("<td style='border:1px solid #eee;'>".$item["pago_FechaCreacion"]."</td>");
                                    echo utf8_decode("<td style='border:1px solid #eee;'>".$item["pago_Descripcion"]."</td>");
                                    if($item["cuenta"] != null){
                                        echo utf8_decode("<td style='border:1px solid #eee;'>".$item["cuenta"]."</td>");
                                    }else{
                                        echo utf8_decode("<td style='border:1px solid #eee;'>No se realizo Ingreso a cuenta en tesoreria</td>");
                                    }
                                    
                                    echo utf8_decode("<td style='border:1px solid #eee;'>".number_format( $item["pago_Valor"], 0, '', '.')."</td>");
                                    echo utf8_decode("<td style='border:1px solid #eee;'> </td>");
                                    echo utf8_decode("<td style='border:1px solid #eee;'></td></tr>");
                                    $totaltotalIngresos= $totaltotalIngresos + $item["pago_Valor"];
                                }
                            }
                            $totaltotalIngresoss = number_format( $totaltotalIngresos, 0, '', '.');

                        echo utf8_decode("
                        <tr> 
                            <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> TOTAL </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> $totaltotalIngresoss </td> 	  
                            <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> </td> 	                            
                        </tr>");



                        echo utf8_decode("<tr>
                        <tr>                         
                            <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> EGRESOS </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> DEL </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> EVENTO </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                        </tr>
                        <tr><td></td></tr>
                        <tr>                         
                            <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> FECHA </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> ACTIVIDAD </td> 
                            <td style='font-weight:bold; border:1px solid #eee;'> PROVEEDOR </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> DESCRIPCIÓN </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> CUENTA INGRESO</td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> VALOR </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> </td>
                            
                        </tr>");
                        
                        $totaltotalEgresos=0;
                        $pendiente = 0;
                        $utilidad = 0;
                        $porcen = 0;

                        if($_obj->_egresos != 'error'){
                            foreach ($_obj->_egresos as $row => $item){
                                    echo utf8_decode("<tr>");
                                    echo utf8_decode("<td style='border:1px solid #eee;'> </td>");
                                    echo utf8_decode("<td style='border:1px solid #eee;'>".$item["egre_Fecha"]."</td>");
                                    echo utf8_decode("<td style='border:1px solid #eee;'>".$item["actividad"]."</td>");
                                    echo utf8_decode("<td style='border:1px solid #eee;'>".$item["proveedor"]."</td>");
                                    echo utf8_decode("<td style='border:1px solid #eee;'>".$item["egre_Descripcion"]."</td>");
                                    if($item["cuenta"] != null){
                                        echo utf8_decode("<td style='border:1px solid #eee;'>".$item["cuenta"]."</td>");
                                    }else{
                                        echo utf8_decode("<td style='border:1px solid #eee;'>No se realizo Egreso a cuenta en tesoreria</td>");
                                    }
                                    
                                    echo utf8_decode("<td style='border:1px solid #eee;'>". number_format( $item["egre_Valor"], 0, '', '.')."</td>");
                                    echo utf8_decode("<td style='border:1px solid #eee;'></td></tr>");
                                    $totaltotalEgresos= $totaltotalEgresos + $item["egre_Valor"];
                                }

                            }
                            $totaltotalEgresoss = number_format( $totaltotalEgresos, 0, '', '.');

                            $pendiente = $totaltotalActividades - $totaltotalEgresos;
                            $pendientee = number_format( $pendiente, 0, '', '.');

                            $utilidad =  $totaltotalIngresos - $totaltotalActividades;
                            $utilidadd = number_format( $utilidad, 0, '', '.');
                            if($totaltotalIngresos > 0){
                                $porcen = ($utilidad * 100) / $totaltotalIngresos;
                                $porcenn = number_format( $porcen, 2, ',', '');
                            }
                            

                        echo utf8_decode("
                        <tr> 
                            <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> TOTAL </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> $totaltotalEgresoss </td>
                            <td style='font-weight:bold; border:1px solid #eee;'> </td> 	                            
                        </tr>");


                        echo utf8_decode("<tr>
                        <tr>                         
                            <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> TOTAL INGRESOS </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> TOTAL EGRESOS </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> PRENDIENTE POR PAGAR </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> UTILIDAD </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> % UTILIDAD </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                            <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                        </tr>
                        <tr><td></td></tr>
                        <tr>                         
                            <td style='border:1px solid #eee;'>  </td> 	
                            <td style='border:1px solid #eee;'> $totaltotalIngresoss </td> 	
                            <td style='border:1px solid #eee;'> $totaltotalEgresoss </td> 
                            <td style='border:1px solid #eee;'> $pendientee </td> 	
                            <td style='border:1px solid #eee;'> $utilidadd </td> 	
                            <td style='border:1px solid #eee;'> $porcenn </td> 	
                            <td style='border:1px solid #eee;'> </td> 	
                            <td style='border:1px solid #eee;'> </td>
                            
                        </tr>");

                        echo "</table>";
                        break;
            }
            
        } catch (\predial\InformesEventosException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    /**
    *** Realiza el proceso de Generar Informes
    **/
    protected function _InformeEvento(){
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();

        $fechaInicial =  $_POST['txtFechaInicio']; 
        $fechaFinal =  $_POST['txtFechaFinal'];        
   
        if($fechaInicial == $fechaFinal){
            $query = "SELECT eve.eve_FechaCreacion as fecha, eve.eve_Nombre as nombre, 
                    eve.eve_ValorEvento as valor,  eve.eve_NombreCliente as cliente , 
                    (SELECT SUM(egre.egre_Valor) from eve_egresoseventos as egre 
                        where egre.egre_IdEvento = eve.eve_Id ) as egresos 
                FROM eve_eventos as eve 
                WHERE  eve.eve_Estado = 1 
                    and eve.eve_FechaCreacion like '%$fechaInicial%'";
		}else{
            $query = "SELECT eve.eve_FechaCreacion as fecha, eve.eve_Nombre as nombre, 
            eve.eve_ValorEvento as valor,  eve.eve_NombreCliente as cliente , 
            (SELECT SUM(egre.egre_Valor) from eve_egresoseventos as egre 
                where egre.egre_IdEvento = eve.eve_Id ) as egresos 
                FROM eve_eventos as eve 
            WHERE  eve.eve_Estado = 1 and (eve.eve_FechaCreacion BETWEEN '$fechaInicial' AND '$fechaFinal') 
                or eve.eve_FechaCreacion like '%$fechaFinal%'";
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

    /**
    *** Realiza el proceso de Generar por Cantidad de Produtos.
    **/
    protected function _InformeEventoActividades(){
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();

        $idEvento = $_POST['id_Eventos'];

        $query = "SELECT act.eva_Descripcion as nombre, act.eva_Valor as valor, 
                (SELECT SUM(egre.egre_Valor) FROM eve_egresoseventos as egre 
                    WHERE egre.egre_IdActividad= act.eva_Id) as abonos,
                    (SELECT eve.eve_Nombre FROM eve_eventos as eve 
                    WHERE eve.eve_Id = act.eva_IdProyecto) as nomEvento 
                    FROM eve_actividadeseventos as act 
                    WHERE act.eva_IdProyecto = $idEvento";

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
    *** Realiza el proceso de Generar Informes por Vendedores.
    **/
    protected function _InformeActividades(){
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        
        $idEvento =  $_POST['id_EventosAct'];        
        $idActividadEvento =  $_POST['id_ActEvento']; 
            
        $query = "SELECT * , (SELECT forr.forpa_Descripcion FROM fac_formas_pago as forr 
                    INNER JOIN fac_cuentascontables as cue ON forr.forpa_Id = cue.cuco_IdCuentaContable
                WHERE cue.cuco_Id = egre.egre_IdCuentaContable ) as cuentaContable ,
                (SELECT evv.eva_Descripcion FROM eve_actividadeseventos as evv 
                    WHERE evv.eva_Id = egre.egre_IdActividad) as actividad 
                FROM eve_egresoseventos as egre
                    WHERE egre.egre_IdEvento = $idEvento and egre.egre_IdActividad = $idActividadEvento";
		
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


    /**
    *** Realiza el proceso de Generar Informe P Y G del evento enviado.
    **/
    protected function _InformePyG(){
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();

        $idEvento = $_POST['id_EventosPyg'];

// Obtiene los datos basicos del Evento
        $query = "SELECT * FROM eve_eventos as ev
                    WHERE ev.eve_Id =  $idEvento";

        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $total[0]= $row;        
        }else{
            $row = 0;
            $total = 'error';
        }

// Obtiene las Actividades del Evento

        $query_1 = "SELECT *, (SELECT pro.prov_Nombre FROM eve_proveedoreseventos as pro 
                            WHERE pro.prov_Id = act.eva_IdProveedor) as nombreProveedor ,
                    (SELECT pro.prov_RazonSocial FROM eve_proveedoreseventos as pro 
                        WHERE pro.prov_Id = act.eva_IdProveedor) as razonProveedor,
                    (SELECT acc.caa_Nombre FROM eve_categoriasactividades as acc 
                    WHERE acc.caa_Id = act.eva_IdCategoria) as categoriaActividad ,
                    (SELECT if(SUM(er.egre_Valor) > 0 , SUM(er.egre_Valor), '0')  
                    FROM eve_egresoseventos as er WHERE er.egre_IdActividad = act.eva_Id)  as totalEgresos 
                FROM eve_actividadeseventos as act WHERE act.eva_IdProyecto =  $idEvento";

        $data_1 = $con->consultar($query_1);

        if( $con->getNumeroFilasConsultadas($data_1) >0 ){ 
            while($res = $con->obnerFila($data_1)){
                $row_1[] = $res;
            }
            $this->_actividades = $row_1;               
        }else{
            $row_1 = 0;
            $this->_actividades = 'error';            
        }

// Obtiene los Ingresos del Evento

        $query_2 = "SELECT *, (SELECT pag.forpa_Descripcion FROM fac_formas_pago  as pag 
                        INNER JOIN fac_cuentascontables as cu ON pag.forpa_Id = cu.cuco_IdCuentaContable 
                        WHERE cu.cuco_Id = ing.pago_IdCuentaContable ) as cuenta 
                FROM eve_ingresoseventos as ing WHERE ing.pago_IdProyecto = $idEvento";

        $data_2 = $con->consultar($query_2);

        if( $con->getNumeroFilasConsultadas($data_2) >0 ){
            while($res = $con->obnerFila($data_2)){
                $row_2[] = $res;
            }
            $this->_ingresos = $row_2;               
        }else{
            $row_2 = 0;
            $this->_ingresos = 'error';            
        }

// Obtiene los Egresos del Evento

        $query_3 = "SELECT *, (SELECT pag.forpa_Descripcion FROM fac_formas_pago  as pag 
                        INNER JOIN fac_cuentascontables as cu ON pag.forpa_Id = cu.cuco_IdCuentaContable 
                        WHERE cu.cuco_Id = eg.egre_IdCuentaContable ) as cuenta, 
                        (SELECT ac.eva_Descripcion FROM eve_actividadeseventos  as ac 
                            WHERE ac.eva_Id = eg.egre_IdActividad) as actividad ,
                        (SELECT proo.prov_RazonSocial FROM eve_proveedoreseventos  as proo
                        INNER JOIN eve_actividadeseventos as acc ON proo.prov_Id = acc.eva_IdProveedor 
                        WHERE acc.eva_Id = eg.egre_IdActividad ) as proveedor
                    FROM eve_egresoseventos as eg WHERE eg.egre_IdEvento = $idEvento";

        $data_3 = $con->consultar($query_3);

        if( $con->getNumeroFilasConsultadas($data_3) >0 ){
            while($res = $con->obnerFila($data_3)){
                $row_3[] = $res;
            }
            $this->_egresos = $row_3;               
        }else{
            $row_3 = 0;
            $this->_egresos = 'error';            
        }

        return $total;  
    }
    
    
}

class InformesEventosException extends \Exception{}

    \predial\ControladorInformesEventos::run();

