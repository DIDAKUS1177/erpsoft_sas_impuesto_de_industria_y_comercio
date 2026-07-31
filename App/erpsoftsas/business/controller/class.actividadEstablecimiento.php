<?php
namespace erpsoftsas;

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/DAO/DAO_ActividadEstablecimiento.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER . '/business/controller/class.logs.php';

class ControladorActividadEstablecimiento extends \erpsoftsas\Cabecera
{
    private $_funcion;
    private $_ok;
    private $_mensaje;

    public static function run()
    {
        $_obj = new self();
        $_obj->_funcion = isset($_POST['funcion']) ? $_POST['funcion'] : null;

        try {

            $respuesta = null;

            switch ($_obj->_funcion) {

                case 1:
                    $respuesta = $_obj->_agregarActividadEstablecimiento();
                    break;

                case 2:
                    $respuesta = $_obj->_editarActividadEstablecimiento();
                    break;

                case 3:
                    $respuesta = $_obj->_consultarActividadEstablecimiento();
                    break;

                case 4:
                    $respuesta = $_obj->_inactivarActividadEstablecimiento();
                    break;

                default:
                    throw new \erpsoftsas\ActividadEstablecimientoException("Función no válida", 0);
            }

            header('Content-type: application/json');

            echo json_encode(array(
                "ok" => $_obj->_ok,
                "mensaje" => $_obj->_mensaje,
                "datos" => $respuesta
            ));

        } catch (\erpsoftsas\ActividadEstablecimientoException $e) {

            $arrRespu = array(
                "ok"      => $e->getCode(),
                "mensaje" => "Error: " . $e->getMessage(),
                "datos"   => ""
            );

            header('Content-type: application/json');
            echo json_encode($arrRespu);
        }
    }

    protected function _agregarActividadEstablecimiento()
    {

        $_obj = new \erpsoftsas\DAO_ActividadEstablecimiento();

        foreach ($_POST as $campo => $valor) {
            $metodo = 'set_' . $campo;
            if (method_exists($_obj, $metodo)) {
                $_obj->$metodo($valor);
            }
        }

        if (!$_obj->guardar()) {

            $this->_ok = 0;
            $this->_mensaje = $_obj->getMysqlError();

        } else {

            $id = $_obj->get_ace_Id();

            $this->_ok = 1;
            $this->_mensaje = "Actividad del establecimiento agregada correctamente. ID = $id";
        }

        return $_obj->guardar();
    }

    protected function _editarActividadEstablecimiento()
    {

        $_obj = new \erpsoftsas\DAO_ActividadEstablecimiento();

        $_obj->set_ace_Id($_POST['ace_Id'] ?? null);

        foreach ($_POST as $campo => $valor) {

            $metodo = 'set_' . $campo;

            if (method_exists($_obj, $metodo)) {
                $_obj->$metodo($valor);
            }
        }

        if (!$_obj->guardar()) {

            $this->_ok = 0;
            $this->_mensaje = $_obj->getMysqlError();

        } else {

            $id = $_obj->get_ace_Id();

            $this->_ok = 1;
            $this->_mensaje = "Actividad del establecimiento ID $id editada correctamente";
        }

        return $_obj->guardar();
    }

    private function _consultarActividadEstablecimiento()
    {

        $_obj = new \erpsoftsas\DAO_ActividadEstablecimiento();

        foreach ($_POST as $campo => $valor) {

            $metodo = 'set_' . $campo;

            if (method_exists($_obj, $metodo)) {
                $_obj->$metodo($valor);
            }
        }

        $_obj->habilita1ResultadoEnArray();

        $arr = $_obj->consultar();

        if (is_array($arr) && count($arr)) {

            $R = [];

            foreach ($arr as $obj) {

                $R[] = $obj->getArray();
            }

            $this->_ok = 1;
            $this->_mensaje = "Actividades del establecimiento consultadas con éxito";

            return $R;

        } else {

            $this->_ok = 0;
            $this->_mensaje = "No existen actividades para el establecimiento";

            return [];
        }
    }

    protected function _inactivarActividadEstablecimiento()
    {

        $_obj = new \erpsoftsas\DAO_ActividadEstablecimiento();

        $_obj->set_ace_Id($_POST['ace_Id'] ?? null);
        $_obj->set_ace_Estado(0);

        if (!$_obj->guardar()) {

            $this->_ok = 0;
            $this->_mensaje = $_obj->getMysqlError();

        } else {

            $id = $_obj->get_ace_Id();

            $this->_ok = 1;
            $this->_mensaje = "Actividad del establecimiento ID $id inactivada correctamente";
        }

        return $_obj->getArray();
    }
}

class ActividadEstablecimientoException extends \Exception { }

\erpsoftsas\ControladorActividadEstablecimiento::run();