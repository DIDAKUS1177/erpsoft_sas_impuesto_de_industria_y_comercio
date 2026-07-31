<?php
namespace predial;

use Exception;

include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_FacturaDocumento.php';
include_once SERVER . '/business/DAO/DAO_FacturaDetalleDocumento.php';
include_once SERVER . '/business/DAO/DAO_Tesoreria.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER .'/business/controller/class.logs.php';

class ControladorInformesModulos extends \predial\Cabecera {

    private $_funcion;
    private $_canbo;
    private $_ok;
    private $_mensaje;
    private $_utilidad;
        
    public static function run() {
        \predial\SesionUsuario::verificarSesion();
        
        $_obj = new self();
        $_obj->_funcion = $_POST['mod_IdModulos'];
        $_obj->_canbo = $_POST['cantidBodegas'];
        
        try {
            $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
            $con->begin();
            $respuesta = null;
            switch ($_obj->_funcion) {
                case 1:
                    $respuesta = $_obj->_Inventarios();
                    break;
                case 2:
                    $respuesta = $_obj->_Clientes();
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
                    header('Content-Disposition:; filename="inventario.xls"');
                    header("Content-Transfer-Encoding: binary");
                    echo utf8_decode("<table border='0'> 
                    <tr> 
                        <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> REPORTE </td> 	
					    <td style='font-weight:bold; border:1px solid #eee;'> DE </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Inventario </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'>  </td> 		
                        <td style='font-weight:bold; border:1px solid #eee;'>  </td> 		
					</tr>
                    <tr> 
                        <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> </td>
                        <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> </td>
                        <td style='font-weight:bold; border:1px solid #eee;'> </td>
                        <td style='font-weight:bold; border:1px solid #eee;'> </td>
                    </tr>");

                    if($_obj->_canbo == 1){
                        echo "<tr> 
                        <td style='font-weight:bold; border:1px solid #eee;'> Codigo Barras </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Nombre Producto</td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Nombre Bodega # 1</td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Cantidades </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> </td>
					    </tr>";

                        foreach ($respuesta as $row => $item){
                            echo utf8_decode("<tr><td style='border:1px solid #eee;'>".$item["pro_CodBarras"]."</td>");
                            echo utf8_decode("<td style='border:1px solid #eee;'>".$item["pro_Nombre"]."</td>");
                            echo utf8_decode("<td style='border:1px solid #eee;'>".$item["NombreBodega_1"]."</td>");
                            echo utf8_decode("<td style='border:1px solid #eee;'>".$item["cantidad1"]."</td>");
                            echo utf8_decode("<td style='border:1px solid #eee;'> </td>");
                            echo utf8_decode("<td style='border:1px solid #eee;'> </td>");
                            echo utf8_decode("<td style='border:1px solid #eee;'> </td>");
                            echo utf8_decode("<td style='border:1px solid #eee;'> </td>");
                        }
                        echo "</table>";
                        
                    }else if($_obj->_canbo == 2){
                        echo "<tr> 
                        <td style='font-weight:bold; border:1px solid #eee;'> Codigo Barras </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Nombre Producto</td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Nombre Bodega # 1</td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Cantidades </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Nombre Bodega # 2</td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Cantidades</td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> </td>
					    </tr>";
                        foreach ($respuesta as $row => $item){
                            echo utf8_decode("<tr><td style='border:1px solid #eee;'>".$item["pro_CodBarras"]."</td>");
                            echo utf8_decode("<td style='border:1px solid #eee;'>".$item["pro_Nombre"]."</td>");
                            echo utf8_decode("<td style='border:1px solid #eee;'>".$item["NombreBodega_1"]."</td>");
                            echo utf8_decode("<td style='border:1px solid #eee;'>".$item["cantidad1"]."</td>");
                            echo utf8_decode("<td style='border:1px solid #eee;'>".$item["NombreBodega_2"]."</td>");
                            echo utf8_decode("<td style='border:1px solid #eee;'>".$item["cantidad2"]."</td>");
                            echo utf8_decode("<td style='border:1px solid #eee;'> </td>");
                            echo utf8_decode("<td style='border:1px solid #eee;'> </td>");
                        }
                        echo "</table>";
                    }else if($_obj->_canbo == 3){
                        echo "<tr> 
                        <td style='font-weight:bold; border:1px solid #eee;'> Codigo Barras </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Nombre Producto</td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Nombre Bodega # 1</td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Cantidades </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Nombre Bodega # 2</td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Cantidades </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Nombre Bodega # 3</td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Cantidades </td>
                        </tr>";
                        foreach ($respuesta as $row => $item){
                            echo utf8_decode("<tr><td style='border:1px solid #eee;'>".$item["pro_CodBarras"]."</td>");
                            echo utf8_decode("<td style='border:1px solid #eee;'>".$item["pro_Nombre"]."</td>");
                            echo utf8_decode("<td style='border:1px solid #eee;'>".$item["NombreBodega_1"]."</td>");
                            echo utf8_decode("<td style='border:1px solid #eee;'>".$item["cantidad1"]."</td>");
                            echo utf8_decode("<td style='border:1px solid #eee;'>".$item["NombreBodega_2"]."</td>");
                            echo utf8_decode("<td style='border:1px solid #eee;'>".$item["cantidad2"]."</td>");
                            echo utf8_decode("<td style='border:1px solid #eee;'>".$item["NombreBodega_3"]."</td>");
                            echo utf8_decode("<td style='border:1px solid #eee;'>".$item["cantidad3"]."</td>");
                        }
                        echo "</table>";
                    }else{}

                   
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
                    header('Content-Disposition:; filename="clientes.xls"');
                    header("Content-Transfer-Encoding: binary");
                    echo utf8_decode("<table border='0'> 
                    <tr> 
                        <td style='font-weight:bold; border:1px solid #eee;'>  </td> 
                        <td style='font-weight:bold; border:1px solid #eee;'>  </td> 
                        <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> REPORTE </td> 	
					    <td style='font-weight:bold; border:1px solid #eee;'> DE </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> CLIENTES </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'>  </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'>  </td> 
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
                        <td style='font-weight:bold; border:1px solid #eee;'> Tipo Persona </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Numero Documento </td> 
                        <td style='font-weight:bold; border:1px solid #eee;'> Nombre </td> 		
                        <td style='font-weight:bold; border:1px solid #eee;'> Razón Social </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Dirección </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Telefono </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Correo </td> 	
                        <td style='font-weight:bold; border:1px solid #eee;'> Departamento </td>
                        <td style='font-weight:bold; border:1px solid #eee;'> Ciudad </td>
					</tr>");
                    foreach ($respuesta as $row => $item){
                        echo utf8_decode("<tr>");
                        echo utf8_decode("<td style='border:1px solid #eee;'>".$item["tipoPersona"]."</td>");
                        echo utf8_decode("<td style='border:1px solid #eee;'>".$item["numeroDocumento"]."</td>");
                        echo utf8_decode("<td style='border:1px solid #eee;'>".$item["nombre"]."</td>");
                        echo utf8_decode("<td style='border:1px solid #eee;'>".$item["razonSocial"]."</td>");
                        echo utf8_decode("<td style='border:1px solid #eee;'>".$item["direccion"]."</td>");
                        echo utf8_decode("<td style='border:1px solid #eee;'>".$item["telefono"]."</td>");
                        echo utf8_decode("<td style='border:1px solid #eee;'>".$item["correo"]."</td>");
                        echo utf8_decode("<td style='border:1px solid #eee;'>".$item["departamento"]."</td>");
                        echo utf8_decode("<td style='border:1px solid #eee;'>".$item["ciudad"]."</td>");
                        echo utf8_decode("</tr>");
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
    protected function _Inventarios(){
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();

        $cantidBodegas =  $_POST['cantidBodegas']; 
        
        if($cantidBodegas == 1){
            $query = "SELECT ip.pro_CodBarras, ip.pro_Nombre,
            (SELECT bo.bod_Nombre from inv_bodega as bo
                where bo.bod_Id= 1)as  NombreBodega_1, 
            IFNULL((SELECT ie.exi_Cantidad from inv_existencias as ie 
                where ip.pro_Id=ie.exi_IdProducto and ie.exi_IdBodega=1),0)as  cantidad1
            FROM inv_producto as ip;";

		}else if($cantidBodegas == 2){
            $query = "SELECT ip.pro_CodBarras, ip.pro_Nombre,
            (SELECT bo.bod_Nombre from inv_bodega as bo
                where bo.bod_Id= 1)as  NombreBodega_1, 
            IFNULL((SELECT ie.exi_Cantidad from inv_existencias as ie 
                where ip.pro_Id=ie.exi_IdProducto and ie.exi_IdBodega=1),0)as  cantidad1, 
            (SELECT bo.bod_Nombre from inv_bodega as bo
                where bo.bod_Id= 2)as  NombreBodega_2, 
            IFNULL((SELECT ie.exi_Cantidad from inv_existencias as ie 
                where ip.pro_Id=ie.exi_IdProducto and ie.exi_IdBodega=2),0)as cantidad2
            FROM inv_producto as ip;";

		}else if($cantidBodegas == 3){
            $query = "SELECT ip.pro_CodBarras, ip.pro_Nombre,
            (SELECT bo.bod_Nombre from inv_bodega as bo
                where bo.bod_Id= 1)as  NombreBodega_1, 
            IFNULL((SELECT ie.exi_Cantidad from inv_existencias as ie 
                where ip.pro_Id=ie.exi_IdProducto and ie.exi_IdBodega=1),0)as  cantidad1, 
             (SELECT bo.bod_Nombre from inv_bodega as bo
                where bo.bod_Id= 2)as  NombreBodega_2, 
            IFNULL((SELECT ie.exi_Cantidad from inv_existencias as ie 
                where ip.pro_Id=ie.exi_IdProducto and ie.exi_IdBodega=2),0)as cantidad2, 
             (SELECT bo.bod_Nombre from inv_bodega as bo
                where bo.bod_Id= 3)as  NombreBodega_3, 
            IFNULL((SELECT ie.exi_Cantidad from inv_existencias as ie
                where ip.pro_Id=ie.exi_IdProducto and ie.exi_IdBodega=3),0)as cantidad3
            FROM inv_producto as ip;";
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
    *** Realiza el proceso de Generar por Listado de Clientes.
    **/
    protected function _Clientes(){
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
 
        $query = "SELECT case cli.cli_IdTipoPersona when 1 then 'Natural'  when 2 then 'Natural' 
                        when 3 then 'Juridica' end as tipoPersona, cli.cli_Identificacion as numeroDocumento,
                cli.cli_Nombre as nombre, cli.cli_RazonSocial as razonSocial, cli.cli_Direccion as direccion,
                cli.cli_Telefono as telefono, cli.cli_Correo as correo,
                (select de.dep_Nombre from conf_departamentos as de where de.dep_Id=cli.cli_IdDepartamento) as departamento,
                (select muni.mun_Nombre from conf_municipios as muni where muni.mun_Id=cli.cli_IdCiudad) as ciudad
                FROM fac_cliente as cli";

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
}

class InformesException extends \Exception{}

    \predial\ControladorInformesModulos::run();

