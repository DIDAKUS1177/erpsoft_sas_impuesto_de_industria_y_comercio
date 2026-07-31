<?php
namespace predial;
include_once $_SERVER['DOCUMENT_ROOT'].'/predial/business/controller/class.cabecera.php';


class SesionUsuario extends \predial\Cabecera{
    
    public static function initSession(DAO_Usuario $_objUsuario, $periodoForzado = NULL) {//$idUsuario, $usuNombre, $usuApellidos,$estado, $cmIdUsuario, $tipUsuario, $idSede
        
        session_start();
        $_SESSION['id_usuario']     = $_objUsuario->get_usu_Id();
        $_SESSION['nombres']        = $_objUsuario->get_usu_Nombre(); 
        $_SESSION['email']          = $_objUsuario->get_usu_Correo();
        $_SESSION['id_Rol']         = $_objUsuario->get_usu_Rol();
      
    }
    
    public static function establecerRolUsuario($id_usu) {
//        if(isset($_SESSION['rol_bit_a_bit'])){
//            return true;
//        }
        
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        //$query = "SELECT RU.`id_rol`, pru.`id_proyecto`  FROM us_rol_usuario AS ru  INNER JOIN us_proyecto_rol_usuario AS pru ON ru.`id` = pru.`id_proyecto` WHERE ru.`id_usuario`= {$_SESSION['id_usuario']} ";
        $query = "SELECT ru.id_rol, pru.id_proyecto, sp.id_modulo, sp.id_sub_modulo 
                  FROM us_rol_usuario AS ru  
                  INNER JOIN us_proyecto_rol_usuario AS pru ON pru.id_rol_usuario = ru.id 
                  INNER JOIN sis_permisos AS sp ON sp.id_rol = ru.id_rol
                  WHERE ru.id_usuario=$id_usu";
                  
        $id = $con->consultar($query);

            while($res = $con->obnerFila($id)){
                $row[] = $res;
            }

            //$_SESSION['rol_proyecto']=$row ;
            
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

    /*
     * 
     */

    public static function verificarSesion() {
        //ini_set('session.save_path', realpath(dirname('') . '../../../sesiones'));
        
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


    /*
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
    */
    

    public static function destroySession() {
        // 1) Arranca la sesión (si no está) y la destruye:
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        // Log de cierre
        if (isset($_SESSION['cm_id_usuario'])) {
            $_obj = new self();
            $_obj->_guardarLog($_SESSION['cm_id_usuario'], ['accion' => 'logout']);
        }
        // Limpia todo
        $_SESSION = [];
        // Borra cookie de sesión si la hay
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();

        // 2) Respuesta AJAX vs normal
        $isAjax = (
            !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        );
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        } else {
            header("Location: ../index.php");
            exit;
        }
    }

}
if (isset($_GET['kill'])) {
    \predial\SesionUsuario::destroySession();
}
