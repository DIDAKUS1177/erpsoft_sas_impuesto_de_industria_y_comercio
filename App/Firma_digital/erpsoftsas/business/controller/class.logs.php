<?php
namespace erpsoftsas;
    header('Access-Control-Allow-Origin: '.(isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : $_SERVER['HTTP_HOST']));
    header('Access-Control-Allow-Methods: POST');
    header('Access-Control-Max-Age: 1000');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');   
        
include_once '../globals.php';
include_once SERVER.'/business/DAO/DAO_logs.php';
require_once SERVER.'/business/class.sessions.php';

class Logs {
       
    public static function _insertLogs($id_registro, $id_usuario, $id_modulo=null, $id_sub_modulo=null){
        
        $_objLogsUsuario = new DAO_Logs();
        $_objLogsUsuario->set_log_IdRegistro($id_registro);
        $_objLogsUsuario->set_log_IdUsuario($id_usuario);
        $_objLogsUsuario->set_log_IdModulo($id_modulo);
        $_objLogsUsuario->set_log_IdSubModulo($id_sub_modulo);
        
        $_objLogsUsuario->guardar();      
    }
}
