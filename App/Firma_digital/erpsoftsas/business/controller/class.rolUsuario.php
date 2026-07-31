<?php
namespace erpsoftsas;
    header('Access-Control-Allow-Origin: '.(isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : $_SERVER['HTTP_HOST']));
    header('Access-Control-Allow-Methods: POST');
    header('Access-Control-Max-Age: 1000');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            
include_once '../globals.php';
include_once SERVER.'/business/DAO/DAO_Usuarios.php';
include_once SERVER.'/business/DAO/DAO_logs.php';
include_once SERVER.'/business/controller/class.cabecera.php';
require_once SERVER.'/business/class.sessions.php';


class rolUsuario {
    
    private $_id_proyecto;
    private $_funcion;
    
    public static function run() {
        //\erpsoftsas\SesionUsuario::verificarSesion();
        
        $_obj = new self();
        $_obj->_funcion = $_POST['funcion'];
        try {
            $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
            $con->begin();
            $respuesta = null;
            switch ($_obj->_funcion) {
                case 1:
                    $respuesta = $_obj->_asignarRolUsuario($_POST['id_usuario'], $_POST['id_rol']);
                    break;
                case 2:
                    $respuesta = $_obj->_editarRolUsuario();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarProyectoRolUsuario($_POST['id_usuario']);
                    break;
                case 4:
                    $respuesta = $_obj->_consultarRolUsuario($_POST['id_usuario'], $_POST['id_rol']);
                    break;
                case 5:
                    $respuesta = $_obj->_consultarProyectoAsignadoUsuario($_POST['id_usuario']);
                    break;
                case 6:
                    $respuesta = $_obj->_consultarProyectoAsignado($_POST['id']);
                    break;
            }
            $con->commit();
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
        } catch (\erpsoftsas\RolUsuarioProyectosException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }     
    
    /**
    *** Realiza el proceso de Asignar un Rol a un Usuario.
    *** @param type $id_usuario
    *** @param type $id_rol
    **/ 
    protected function _asignarRolUsuario($id_usuario, $id_rol) {
            
        $_objRolUsu = new \erpsoftsas\DAO_rol_usuarios();
        $_objRolUsu->set_id_usuario($id_usuario);
        $_objRolUsu->set_id_rol($id_rol);

        if($_objRolUsu->guardar()){
            $id_rol_usuario = $_objRolUsu->get_id();
            $_objLogs = new logs();
            $_objLogs->_insertLogs($id_rol_usuario,3,1,11);
            $this->_ok = 1;
            $this->_mensaje = "Datos ingresados correctamente";    
        }else{
            $this->_ok = 0;
            $this->_mensaje = $_objRolUsu->getMysqlError();
        }
        return $_objRolUsu->guardar();    
    }
    
    /**
    *** Realiza el proceso de Editar un Rol a un Usuario.
    **/ 
    protected function _editarRolUsuario() {
            
        $_objRolUsu = new \erpsoftsas\DAO_rol_usuarios();
        $_objRolUsu->set_id($_POST['id']);
        $_objRolUsu->set_id_usuario($_POST['id_usuario']);
        $_objRolUsu->set_id_rol($_POST['id_rol']);

        if($_objRolUsu->guardar()){
            $id_rol_usuario = $_objRolUsu->get_id();
            //$_objlogs = new logs();
            //$_objlogs->_insertLogs($id_rol_usuario,4,1,11);
            $this->_ok = 1;
            $this->_mensaje = "Datos ingresados correctamente";    
        }else{
            $this->_ok = 0;
            $this->_mensaje = $_objRolUsu->getMysqlError();
        }
        return  $_objRolUsu->guardar();
    }
    
    /**
    *** Realiza el proceso de Consultar roles a un usuario y viceversa.
    *** @param type $id_usuario
    *** @param type $id_rol
    **/ 
    private function _consultarRolUsuario($id_usuario, $id_rol) {
       
        $_objRolUsu = new \erpsoftsas\DAO_rol_usuarios();
        if (!empty($id_usuario)) {
            $_objRolUsu->set_id_usuario($id_usuario);
        }
        if (!empty($id_rol)) {
            $_objRolUsu->set_id_rol($id_rol);
        }
        
        $_objRolUsu->habilita1ResultadoEnArray();
        $arrRolUsu = $_objRolUsu->consultar();
                
        if(is_array($arrRolUsu) && count($arrRolUsu)){
            $R = [];
            foreach($arrRolUsu as $obj){
                $R[] = $obj->getArray();
            }    
            $this->_ok = 1;
            $this->_mensaje = "Roles Usuarios listados con Éxito"; 
        }else{
            $R=$_objRolUsu;
            $this->_ok = 0;
            $this->_mensaje = "No existen Roles Usuarios";            
        }
        return $R;
    }  
    
    /**
    *** Realiza el proceso de Consultar Proyectos y sedes asociadas a usuario enviado por parametro.
    *** @param type $id_usuario
    **/  
    private function _consultarProyectoRolUsuario($id_usuario) {
        
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT cpr.id AS 'id_proyecto', cpr.nombre AS 'nombre_proyecto', cpr.descripcion AS 'descripcion_proyecto', cpr.id_cliente AS 'id_cliente', 
                    cpr.fecha_inicio AS 'fecha_inicio_proyecto', cpr.fecha_final AS 'fecha_final_proyecto', cpr.estado AS 'estado_proyecto', 
                    ss.id AS 'id_sede', ss.nombre AS 'nombre_sede', ss.direccion AS 'direccion_sede', ss.descripcion AS 'descripcion_sede', ss.estado AS 'estado_sede',
                    upr.id AS 'Id_Proyecto_Rol_Usuario', upr.estado AS 'Estado_Proyecto_Rol_Usuario', ur.nombre AS 'nombre_rol', ur.id AS 'id_rol'
                    from us_rol_usuario uru 
                    LEFT JOIN us_rol ur ON uru.id_rol = ur.id
                    LEFT JOIN us_proyecto_rol_usuario upr ON uru.id = upr.id_rol_usuario
                    LEFT JOIN cli_proyecto cpr ON upr.id_proyecto = cpr.id
                    LEFT JOIN se_sede_proyecto_rol_usuario spru ON upr.id = spru.id_proyecto_rol_usuario
                    LEFT JOIN se_sede ss ON spru.id_sede = ss.id
                    where uru.id_usuario = $id_usuario";
        $proyectos = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas( $proyectos ) >0 ){
            while($res = $con->obnerFila($proyectos)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "Proyectos Listados con Éxito";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existe Proyectos";
            $row=[];
        }
        return $row;
    }
    
    /**
    *** Realiza el proceso de Consultar Proyectos asociadas a usuario enviado por parametro.
    *** @param type $id_usuario
    **/  
    private function _consultarProyectoAsignadoUsuario($id_usuario) {
        
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT cpr.id AS 'id_proyecto', cpr.nombre AS 'nombre_proyecto', cpr.descripcion AS 'descripcion_proyecto', cpr.id_cliente AS 'id_cliente', 
                    cpr.fecha_inicio AS 'fecha_inicio_proyecto', cpr.fecha_final AS 'fecha_final_proyecto', cpr.estado AS 'estado_proyecto',
                    upr.id AS 'Id_Proyecto_Rol_Usuario', upr.estado AS 'Estado_Proyecto_Rol_Usuario', ur.nombre AS 'nombre_rol', ur.id AS 'id_rol'
                    from us_rol_usuario uru 
                    LEFT JOIN us_rol ur ON uru.id_rol = ur.id
                    LEFT JOIN us_proyecto_rol_usuario upr ON uru.id = upr.id_rol_usuario
                    LEFT JOIN cli_proyecto cpr ON upr.id_proyecto = cpr.id
                    where uru.id_usuario = $id_usuario";
        $proyectos = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas( $proyectos ) >0 ){      
            while($res = $con->obnerFila($proyectos)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "Proyectos Listados con Éxito";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existe Proyectos";
            $row = NULL;
        }
        return $row;
    }
    
    /**
    *** Realiza el proceso de Consultar los datos de un Proyecto asociada a usuario enviado por parametro.
    *** @param type $id
    *** Nestor Bautista 05/07/2019
    **/  
    private function _consultarProyectoAsignado($id_usuario) {
        
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT cpr.id AS 'id_proyecto', cpr.nombre AS 'nombre_proyecto', cpr.descripcion AS 'descripcion_proyecto', cpr.id_cliente AS 'id_cliente', 
                    cpr.fecha_inicio AS 'fecha_inicio_proyecto', cpr.fecha_final AS 'fecha_final_proyecto', cpr.estado AS 'estado_proyecto',
                    upr.id AS 'Id_Proyecto_Rol_Usuario', upr.estado AS 'Estado_Proyecto_Rol_Usuario', ur.nombre AS 'nombre_rol', ur.id AS 'id_rol'
                    from us_rol_usuario uru 
                    LEFT JOIN us_rol ur ON uru.id_rol = ur.id
                    LEFT JOIN us_proyecto_rol_usuario upr ON uru.id = upr.id_rol_usuario
                    LEFT JOIN cli_proyecto cpr ON upr.id_proyecto = cpr.id
                    where uru.id_usuario = $id_usuario";
        $proyectos = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas( $proyectos ) >0 ){      
            while($res = $con->obnerFila($proyectos)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "Proyectos Listados con Éxito";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existe Proyectos";
            $row = NULL;
        }
        return $row;
    }
}

class RolUsuarioProyectosException extends \Exception {}

    \erpsoftsas\rolUsuario::run();
