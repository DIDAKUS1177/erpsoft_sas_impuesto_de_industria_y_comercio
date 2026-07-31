<?php
namespace erpsoftsas;
include_once $_SERVER['DOCUMENT_ROOT'].'/erpsoftsas/business/controller/class.cabecera.php';

class SesionUsuario extends \erpsoftsas\Cabecera{
    
    public static function initSession(DAO_Usuario $_objUsuario, $periodoForzado = NULL) {//$idUsuario, $usuNombre, $usuApellidos,$estado, $cmIdUsuario, $tipUsuario, $idSede
        
        session_start();
        $_SESSION['id_usuario']     = $_objUsuario->get_usu_Id();
        $_SESSION['IdUsuario']      = $_objUsuario->get_usu_Id(); // Para icaWebPresentar.php
        $_SESSION['usu_Id']         = $_objUsuario->get_usu_Id(); // Para icaWebPresentar.php
        $_SESSION['nombres']        = $_objUsuario->get_usu_Nombres(); 
        $_SESSION['email']          = $_objUsuario->get_usu_Correo();
        $_SESSION['id_Rol']         = $_objUsuario->get_usu_Rol();
      
    }
    
    public static function establecerRolUsuario($id_usu) {
        
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT ru.id_rol, pru.id_proyecto, sp.id_modulo, sp.id_sub_modulo 
                  FROM us_rol_usuario AS ru  
                  INNER JOIN us_proyecto_rol_usuario AS pru ON pru.id_rol_usuario = ru.id 
                  INNER JOIN sis_permisos AS sp ON sp.id_rol = ru.id_rol
                  WHERE ru.id_usuario=$id_usu";
                  
        $id = $con->consultar($query);

            while($res = $con->obnerFila($id)){
                $row[] = $res;
            }
            return $row;
        }
    
    /**
     * 
     * @param type $codPrograma
     * @param type $antiguo
     */
    public static function setProgramaUsuario($codPrograma,$antiguo) {
        
        $_SESSION['cm_programa'] = $codPrograma;
        $_SESSION['cm_antiguo'] = $antiguo ;
    }

    public static function verificarSesion() {
        
        if(!isset($_SESSION)){ 
            session_start();
            if (!isset($_SESSION['id_usuario']) || empty($_SESSION['id_usuario'])) {
                session_destroy();
                header("Location: ../index.php");
            }
        }else{
            if (!isset($_SESSION['id_usuario']) || empty($_SESSION['id_usuario'])) {
                session_destroy();
                header("Location: ../index.php");
            }
        }
    }

    public static function destroySession() {
        session_start();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]
            );
        }

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]
            );
        }
        $_obj = new self();
        $_obj->_guardarLog($_SESSION['cm_id_usuario'], array('accion' => 'logout'));
        // Finalmente, destruir la sesión.
        $_SESSION = array();
        session_destroy();
        header("Location: ../index.php");
        echo "No se pudo cerrar La sesion " . print_r($_SESSION);
    }

}
if (isset($_GET['kill'])) {
    \erpsoftsas\SesionUsuario::destroySession();
}
