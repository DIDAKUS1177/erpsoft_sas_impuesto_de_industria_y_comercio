<?php
namespace predial;
/*
 * Clase que coloca las cabeceras para transacciones json.
 */
include_once $_SERVER['DOCUMENT_ROOT'].'/predial/business/DAO/DAO_TarLog.php';
class Cabecera {

    protected $_http_estado = 200;

    protected function cabeceras() {
        http_response_code($this->_http_estado);
        header('Content-type: application/json');
    }
    
     /**
     * Guarda datos en log
     * @param array $arrDatosLog
     */
    protected function _guardarLog($id_usuario, array $accion) {
        //return;
        /*if(empty($usu_central)){
            return false;
        }*/
        //echo $_SERVER['HTTP_USER_AGENT'];
        //print_r($datos);
        $arrDatosLog = array(
                'log_accion' => json_encode($accion) ,
                'id_usu' => $id_usuario,
                'log_fecha' => date("Y-m-d"),
                'log_hora' => date("H:i:s"),
                'log_ip_client' => (isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? ($_SERVER['HTTP_X_FORWARDED_FOR'].":".$_SERVER['REMOTE_ADDR']) : $_SERVER['REMOTE_ADDR']),
                'log_useragent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ""
            );
        $_objLog = new \DAO_TarLog();
        $_objLog->set_log_accion(isset($arrDatosLog['log_accion']) ? $arrDatosLog['log_accion'] : "");
        $_objLog->set_id_usu(isset($arrDatosLog['id_usu']) ? $arrDatosLog['id_usu'] : "");
        $_objLog->set_log_fecha(isset($arrDatosLog['log_fecha']) ? $arrDatosLog['log_fecha'] : "");
        $_objLog->set_log_hora(isset($arrDatosLog['log_hora']) ? $arrDatosLog['log_hora'] : "");
        $_objLog->set_log_ip_client(isset($arrDatosLog['log_ip_client']) ? $arrDatosLog['log_ip_client'] : "");
        
        if(isset($_SERVER['HTTP_USER_AGENT'])){
            $datos = get_browser($_SERVER['HTTP_USER_AGENT'],true);
            $_objLog->set_browser_bits(isset($datos['browser_bits']) ? $datos['browser_bits'] : "");
            $_objLog->set_browser(isset($datos['browser']) ? $datos['browser'] : "");
            $_objLog->set_majorver(isset($datos['majorver']) ? $datos['majorver'] : "");
            $_objLog->set_browser_type(isset($datos['browser_type']) ? $datos['browser_type'] : "" );
            $_objLog->set_browser_maker(isset($datos['browser_maker']) ? $datos['browser_maker'] : "");

            $_objLog->set_platform(isset($datos['platform']) ? $datos['platform'] : "");
            $_objLog->set_platform_version(isset($datos['platform_version']) ? $datos['platform_version'] : "");
            $_objLog->set_platform_bits(isset($datos['platform_bits']) ? $datos['platform_bits'] : "");
            $_objLog->set_platform_maker(isset($datos['platform_maker']) ? $datos['platform_maker'] : "");

            $_objLog->set_device_type(isset($datos['device_type']) ? $datos['device_type'] : "");
            $_objLog->set_device_pointing_method(isset($datos['device_pointing_method']) ? $datos['device_pointing_method'] : "");

            $_objLog->set_cssversion(isset($datos['cssversion']) ? $datos['cssversion'] : "");
            $_objLog->set_javascript (isset($datos['javascript']) ? $datos['javascript'] : "");
            $_objLog->set_cookies (isset($datos['cookies']) ? $datos['cookies'] : "");
            $_objLog->set_device_brand_name(isset($datos['device_brand_name']) ? $datos['device_brand_name'] : "");
        }
        
        $_objLog->set_log_useragent(isset($arrDatosLog['log_useragent']) ? $arrDatosLog['log_useragent'] : "");  //      print_r($_objLog);return ;
        $_objLog->guardar();
        //echo $_objLog->getQuery();die();
    }
    
}


if (!function_exists('http_response_code')) {
    function http_response_code($newcode = NULL) {
        static $code = 200;
        if ($newcode !== NULL) {
            header('X-PHP-Response-Code: ' . $newcode, true, $newcode);
            if (!headers_sent())
                $code = $newcode;
        }
        return $code;
    }
}