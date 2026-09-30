<?php
namespace erpsoftsas;
include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/DAO/DAO_Permisos.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER . '/business/class.permisosRol.php';

/*
 * PERMISOS DE UN ROL (consulta)
 *
 * Desde el 2026-09-29 los permisos se arman en el panel de Roles, con un
 * interruptor por accion (class.rol.php, funciones 5 y 6). Aqui queda:
 *
 *   3  la consulta de siempre (filas de conf_permisos) para las pantallas
 *      viejas que aun preguntan por un "boton". Pide sesion; la de OTRO rol
 *      pide "Ver roles".
 *   6  mis permisos: lo que la pantalla necesita para mostrar u ocultar
 *      opciones (PermisosRol::paraPantalla). La usan el login y el menu.
 *
 * Las funciones 1, 4 y 5 (borrar y reescribir los permisos de un rol) no
 * pedian ni sesion: cualquiera podia dejar sin permisos a los contribuyentes
 * o darse todos. Las usaba solo el panel viejo; contestan que se recargue.
 */
class ControladorPermisos extends \erpsoftsas\Cabecera {

    private $_ok = 0;
    private $_mensaje = '';

    public static function run() {
        $_obj = new self();
        $funcion = (int) ($_POST['funcion'] ?? 0);

        if (PermisosRol::idUsuario() <= 0) {
            PermisosRol::negar('Debe iniciar sesión.');
            return;
        }

        switch ($funcion) {
            case 3:
                $rol = (int) ($_POST['id_rol'] ?? 0);
                if ($rol !== PermisosRol::rol() && !PermisosRol::tiene('roles.ver')) {
                    PermisosRol::negar(PermisosRol::mensaje('roles.ver'));
                    return;
                }
                $respuesta = $_obj->_consultarPermisos($rol);
                break;
            case 6:
                $_obj->_ok = 1;
                $_obj->_mensaje = 'Permisos de la sesión';
                $respuesta = PermisosRol::paraPantalla();
                break;
            case 1:
            case 4:
            case 5:
                PermisosRol::negar('Los permisos ahora se asignan con los interruptores del panel de Roles. Recargue la página (Ctrl + F5).');
                return;
            default:
                PermisosRol::negar('Función no válida.');
                return;
        }
        header('Content-type: application/json');
        echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
    }

    /**
     * Filas de conf_permisos del rol (y del boton, si llega). El administrador
     * no necesita filas: tiene todo.
     */
    private function _consultarPermisos($rol) {
        $_objPermiso = new \erpsoftsas\DAO_Permisos();
        // Enteros: el DAO pega los valores en el WHERE.
        $_objPermiso->set_per_IdRol($rol);
        if (!empty($_POST['id_boton'])) {
            $_objPermiso->set_per_IdBoton((int) $_POST['id_boton']);
        }
        $_objPermiso->habilita1ResultadoEnArray();
        $arrPermisos = $_objPermiso->consultar();

        if (is_array($arrPermisos) && count($arrPermisos)) {
            $R = [];
            foreach ($arrPermisos as $obj) {
                $R[] = $obj->getArray();
            }
            $this->_ok = 1;
            $this->_mensaje = "Permisos listados con exito";
            return $R;
        }
        if (PermisosRol::esAdministrador($rol)) {
            $this->_ok = 1;
            $this->_mensaje = "Rol Super Administrador";
            return [];
        }
        $this->_ok = 0;
        $this->_mensaje = "No existen Permisos";
        return [];
    }
}

class PermisosException extends \Exception{}

\erpsoftsas\ControladorPermisos::run();
