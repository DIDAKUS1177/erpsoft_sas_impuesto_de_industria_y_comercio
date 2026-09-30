<?php
namespace erpsoftsas;

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
        $this->_usuario = $_POST['u_correo_inst'] ?? '';
        $this->_claveTexto = (string) ($_POST['u_id_genesis'] ?? '');
        // La misma huella con que el DAO guarda la clave (DAOGeneral::hashClave).
        $this->_clave = DAOGeneral::hashClave($this->_claveTexto);
    }

    /** La clave tal como la escribio el usuario (solo para la huella anterior). */
    private $_claveTexto = '';

    /**
     * Busca la cuenta con esa huella de clave. Devuelve el DAO consultado.
     */
    private function _buscarCuenta($huella)
    {
        $obj = new DAO_Usuario();
        if (filter_var($this->_usuario, FILTER_VALIDATE_EMAIL)) {
            $obj->set_usu_Correo($this->_usuario);
        } else {
            $obj->set_usu_Usuario($this->_usuario);
        }
        $obj->set_usu_Password($huella);
        $obj->set_usu_Estado(1);
        $obj->consultar();
        return $obj;
    }
    
    /**
     * Verificacion y logueo de usuario
     */
    private function _verificarDatosUsuario(){
        $this->_objUsuario = $this->_buscarCuenta($this->_clave);
        $id = $this->_objUsuario->get_usu_Id();

        /*
         * Cuentas creadas antes del 2026-09-29 con una clave con ñ o tildes: su
         * huella se calculo sobre Windows-1252 (DAOGeneral::hashClaveAnterior) y
         * no coincidia con la del login -se creaban y despues no podian entrar-.
         * Se prueba con esa huella y, si entra, se le guarda la nueva, para que
         * de ahi en adelante sea una sola cuenta.
         */
        $anterior = DAOGeneral::hashClaveAnterior($this->_claveTexto);
        if (empty($id) && $anterior !== $this->_clave) {
            $this->_objUsuario = $this->_buscarCuenta($anterior);
            $id = $this->_objUsuario->get_usu_Id();
            if (!empty($id)) {
                // Si no se puede actualizar la huella, entra igual (la clave es
                // correcta) y se intenta la proxima vez.
                try {
                    $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
                    $con->consultar("UPDATE conf_usuarios SET usu_Password = ? WHERE usu_Id = ? AND usu_Password = ?",
                                    [$this->_clave, (int) $id, $anterior]);
                } catch (\Throwable $e) {
                    error_log('[login] no se actualizo la huella de la cuenta ' . (int) $id . ': ' . $e->getMessage());
                }
            }
        }
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
              /*$this->_objlogs = new logs();
                $this->_objlogs->_insertLogs($id,1,$id);   */          
                \erpsoftsas\SesionUsuario::initSession($this->_objUsuario);
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
    /** La huella de la clave no sale nunca hacia el navegador. */
    private static function _sinClave($datos)
    {
        if (is_array($datos)) { unset($datos['usu_Password']); }
        return $datos;
    }

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
                "datos_usuario" => $this->_actualizado == 0 ? self::_sinClave($this->_objUsuario->getArray()) : ""
            );
        }else{
            switch ($error) {
            case 0:
                 $arrRespu = array("ok" => $error, "url" => $id, "mensaje" => "Usuario Inactivo", "tipo_usuario" => "","token" => 0);
                break;
            case 2:
                 $arrRespu = array("ok" => $error, "url" => $id, "mensaje" => "Error en las credenciales", "tipo_usuario" => "", "token" => 0);
                break;
            }
        }
        header('Content-type: application/json');   
        echo json_encode($arrRespu);
    }
}

if(isset($_POST['u_correo_inst']) && isset($_POST['u_id_genesis'])){
    \erpsoftsas\Login::run();
}
