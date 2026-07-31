<?php
namespace predial;

    header('Access-Control-Allow-Origin: '.(isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : $_SERVER['HTTP_HOST']));
    header('Access-Control-Allow-Methods: POST');
    header('Access-Control-Max-Age: 1000');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        
        
include_once '../globals.php';
include_once SERVER.'/business/DAO/DAO_Usuario.php';
//include_once SERVER_CENTRAL.'/business/DAO/DAO_logs.php';
require_once SERVER.'/business/class.sessions.php';
include_once SERVER.'/business/controller/class.logs.php';

#esto es una prueba por nestor
class Login{
    
    private $_url = "";
    private $_usuario;
    private $_clave;
    private $_tipo_usuario;
    private $_email;
    private $_estado=1;
    private $_url_programa_redireccion;
    private $_actualizado;
    /**
     * @var DAO_Usuarios 
     */
    private $_objUsuario;
    private $_objLogs;

    public static function run(){
        $_obj = new self();
        $_obj->_establecerDatos();
        $_obj->_verificarDatosUsuario();
    }
    
    private function _establecerDatos(){
        //print_r($_POST);
        $this->_usuario = $_POST['u_correo_inst'];
        $this->_clave = sha1($_POST['u_id_genesis']);
    }
    
    /**
     * Verificacion y logueo de usuario
     */
    private function _verificarDatosUsuario(){
        $this->_objUsuario = new DAO_Usuario();

        if (filter_var($this->_usuario, FILTER_VALIDATE_EMAIL)) {
            $this->_objUsuario->set_usu_Correo($this->_usuario);
        }else{
            $this->_objUsuario->set_usu_Usuario($this->_usuario);
        }
        
        $this->_objUsuario->set_usu_Password($this->_clave);
        $this->_objUsuario->set_usu_Estado(1);
        $this->_objUsuario->consultar();
        $id = $this->_objUsuario->get_usu_Id();
        $estado = $this->_objUsuario->get_usu_Estado();
        
        if (filter_var($this->_usuario, FILTER_VALIDATE_EMAIL)) {
            $this->_email = $this->_objUsuario->get_usu_Correo();
        }else{
            $this->_email = $this->_objUsuario->get_usu_Usuario();
        }

        $this->_clave = $this->_objUsuario->get_usu_Password();
        
        if(empty($id)){
            $this->_respuesta(false,0,2);
        }else{
            if($estado==0){
                $this->_respuesta(false,0,0);
            }else{
                //Inserta Log de Ingreso
              /*   $this->_objlogs = new logs();
                $this->_objlogs->_insertLogs($id,1,$id);   */          
                \predial\SesionUsuario::initSession($this->_objUsuario);
                $this->_respuesta(true,$id,0);
            }
        }
    }

    /**
     * 
     * @param \dextera\DAO_Usuarios $_objU
     * @return boolean
     */
    private function _actualizarUsuario() {
        // sede
        $sede = $this->_objUsuario->get_u_sede();
        // facultad
        //$facultadd = $this->_objUsuario->get_u_facultad();
        // programa  
        $programa = $this->_objUsuario->get_u_programa();
        // rectoria
        $rectoria = $this->_objUsuario->get_u_rectoria();
        // cargo
        $cargo = $this->_tipo_usuario == 1 ? 1 : $this->_objUsuario->get_u_cargo();
        // ciudad
        $ciudad = $this->_objUsuario->get_u_ciudad();
        return false;
        if(empty($sede) || empty($facultadd) || empty($programa) || empty($rectoria) || empty($cargo) || empty($ciudad)){
            return true;
        }
        return false;
    }
    
    /**
     * Realiza el proceso de Respuesta de los Metodos de la Clase.
     * @param type $respuesta
     * @param type $id
     */
    private function _respuesta($respuesta,$id,$error){
        $arrRespu = array();
        if($respuesta){
            $arrRespu = array(
                "ok" => 1, 
                "id_usuario" => $id, 
                "mensaje" => "Bienvenido {$this->_email} ", 
                //"tipo_usuario" => $this->_objUsuario->get_tipo_usuario(),
                "token" => 1,
                "mail" => $this->_objUsuario->get_usu_Correo(),
                "datos_usuario" => $this->_actualizado == 0 ? $this->_objUsuario->getArray() : ""
            );
        }else{
            switch ($error) {
            case 0:
                 $arrRespu = array("ok" => $error, "url" => $id, "mensaje" => "Usuario Inactivo", "tipo_usuario" => "","token" => 0);
                break;
            case 2:
                 $arrRespu = array("ok" => $error, "url" => $id, "mensaje" => "Error en las credenciales", "tipo_usuario" =>  $this->_objUsuario,"token" => 0);
                break;
            }
        }
        header('Content-type: application/json');   
        echo json_encode($arrRespu);
    }
}

if(isset($_POST['u_correo_inst']) && isset($_POST['u_id_genesis'])){
    \predial\Login::run();
}
