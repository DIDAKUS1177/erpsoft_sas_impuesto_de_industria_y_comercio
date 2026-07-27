<?php
namespace erpsoftsas;

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/DAO/DAO_ActividadesComercio.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER . '/business/controller/class.logs.php';

class ControladorActividadesComercio extends \erpsoftsas\Cabecera 
{
    private $_funcion;
    private $_ok;
    private $_mensaje;

    public static function run() 
    {
        // Instanciamos el controlador
        $_obj = new self();
        // Obtenemos el número de función que indica la operación a ejecutar
        $_obj->_funcion = isset($_POST['funcion']) ? $_POST['funcion'] : null;

        try {
            // Iniciamos la transacción (adaptar a tu clase de conexión)
            //$con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
            //$con->begin();
            $respuesta = null;
            switch ($_obj->_funcion) {
                case 1: // Agregar Contribuyente
                    $respuesta = $_obj->_agregarActividadesComercio();
                    break;
                case 2: // Editar Contribuyente
                    $respuesta = $_obj->_editarActividadesComercio();
                    break;
                case 3: // Consultar Contribuyente(s)
                    $respuesta = $_obj->_consultarActividadesComercio();
                    break;
                case 4: // Inactivar Contribuyente
                    $respuesta = $_obj->_inactivarActividadesComercio();
                    break;
                default:
                    throw new \erpsoftsas\ActividadesComercioException("Función no válida", 0);
            }

            // Si todo va bien, se hace commit
            //$con->commit();

            header('Content-type: application/json');
            echo json_encode(array(
                "ok" => $_obj->_ok, 
                "mensaje" => $_obj->_mensaje, 
                "datos" => $respuesta
            ));

        } catch (\erpsoftsas\ActividadesComercioException $e) {
            // En caso de error, se realiza rollback
            //$con->rollback();
            $arrRespu = array(
                "ok"      => $e->getCode(), 
                "mensaje" => "Error: " . $e->getMessage(), 
                "datos"   => ""
            );
            header('Content-type: application/json');
            echo json_encode($arrRespu);
        }
    }

    /**
     * Agrega un nuevo ActividadesComercio
     */
    protected function _agregarActividadesComercio() 
    {
        $_obj = new \erpsoftsas\DAO_ActividadesComercio();

        if(isset($_POST['acc_Codigo'])){
            if (!empty($_POST['acc_Codigo']) || $_POST['acc_Codigo'] != NULL ) {
                $_obj->set_acc_Codigo($_POST['acc_Codigo']);
            }    
        }

        if(isset($_POST['acc_Anio'])){
            if (!empty($_POST['acc_Anio']) || $_POST['acc_Anio'] != NULL ) {
                $_obj->set_acc_Anio($_POST['acc_Anio']);
            }    
        }

        if(isset($_POST['acc_Nombre'])){
            if (!empty($_POST['acc_Nombre']) || $_POST['acc_Nombre'] != NULL ) {
                $_obj->set_acc_Nombre($_POST['acc_Nombre'] ?? null);
            }    
        }
        if(isset($_POST['acc_Tarifa'])){
            if (!empty($_POST['acc_Tarifa']) || $_POST['acc_Tarifa'] != NULL ) {
                $_obj->set_acc_Tarifa($_POST['acc_Tarifa']);
            }    
        } 
        if(isset($_POST['acc_GrupoTarifa'])){
            if (!empty($_POST['acc_GrupoTarifa']) || $_POST['acc_GrupoTarifa'] != NULL ) {
                $_obj->set_acc_GrupoTarifa($_POST['acc_GrupoTarifa'] ?? null);
            }    
        }
        if(isset($_POST['acc_Exento'])){
            if (!empty($_POST['acc_Exento']) || $_POST['acc_Exento'] != NULL ) {
                $_obj->set_acc_Exento($_POST['acc_Exento']);
            }    
        }

        $_obj->set_acc_Estado(1); 

        // Llamamos al método "guardar()" (de DAOGeneral o tu capa DAO)
        if (!$_obj->guardar()) {
            $this->_ok = 0;
            $this->_mensaje = $_obj->getMysqlError(); // método de DAOGeneral para error
        } else {
            $id = $_obj->get_acc_Id(); 
            $this->_ok = 1;
            $this->_mensaje = "Actividad Comercio agregado correctamente. ID = $id";
        }
        return $_obj->guardar();
    }

    /**
     * Edita un ActividadesComercio existente
     */
    protected function _editarActividadesComercio()
    {
        $_obj = new \erpsoftsas\DAO_ActividadesComercio();

        // Cargamos el ID del contribuyente que se desea editar
        $_obj->set_acc_Id($_POST['acc_Id'] ?? null);
        if(isset($_POST['acc_Codigo'])){
            if (!empty($_POST['acc_Codigo']) || $_POST['acc_Codigo'] != NULL ) {
                $_obj->set_acc_Codigo($_POST['acc_Codigo']);
            }    
        }
        if(isset($_POST['acc_Anio'])){
            if (!empty($_POST['acc_Anio']) || $_POST['acc_Anio'] != NULL ) {
                $_obj->set_acc_Anio($_POST['acc_Anio']);
            }    
        }
        if(isset($_POST['acc_Nombre'])){
            if (!empty($_POST['acc_Nombre']) || $_POST['acc_Nombre'] != NULL ) {
                $_obj->set_acc_Nombre($_POST['acc_Nombre'] ?? null);
            }    
        }
        if(isset($_POST['acc_Tarifa'])){
            if (!empty($_POST['acc_Tarifa']) || $_POST['acc_Tarifa'] != NULL ) {
                $_obj->set_acc_Tarifa($_POST['acc_Tarifa']);
            }    
        } 
        if(isset($_POST['acc_GrupoTarifa'])){
            if (!empty($_POST['acc_GrupoTarifa']) || $_POST['acc_GrupoTarifa'] != NULL ) {
                $_obj->set_acc_GrupoTarifa($_POST['acc_GrupoTarifa'] ?? null);
            }    
        }
        if(isset($_POST['acc_Exento'])){
            if (!empty($_POST['acc_Exento']) || $_POST['acc_Exento'] != NULL ) {
                $_obj->set_acc_Exento($_POST['acc_Exento']);
            }    
        }

        // Guardar cambios
        if (!$_obj->guardar()) {
            $this->_ok = 0;
            $this->_mensaje = $_obj->getMysqlError();
        } else {
            $id = $_obj->get_acc_Id();
            $this->_ok = 1;
            $this->_mensaje = "Actividad Comercio ID $id editado correctamente";
        }
        return $_obj->getArray();
    }

    /**
     * Consulta uno o varios ActividadesComercio
    */
    private function _consultarActividadesComercio()
    {
        $_obj = new \erpsoftsas\DAO_ActividadesComercio();

        if(isset($_POST['acc_Id'])){
            if (!empty($_POST['acc_Id']) || $_POST['acc_Id'] != NULL ) {
                $_obj->set_acc_Id($_POST['acc_Id']);
            }    
        }
        if(isset($_POST['acc_Codigo'])){
            if (!empty($_POST['acc_Codigo']) || $_POST['acc_Codigo'] != NULL ) {
                $_obj->set_acc_Codigo($_POST['acc_Codigo']);
            }    
        }
        if(isset($_POST['acc_Anio'])){
            if (!empty($_POST['acc_Anio']) || $_POST['acc_Anio'] != NULL ) {
                $_obj->set_acc_Anio($_POST['acc_Anio']);
            }    
        }
        if(isset($_POST['acc_Nombre'])){
            if (!empty($_POST['acc_Nombre']) || $_POST['acc_Nombre'] != NULL ) {
                $_obj->set_acc_Nombre($_POST['acc_Nombre'] ?? null);
            }    
        }
        if(isset($_POST['acc_Tarifa'])){
            if (!empty($_POST['acc_Tarifa']) || $_POST['acc_Tarifa'] != NULL ) {
                $_obj->set_acc_Tarifa($_POST['acc_Tarifa']);
            }    
        } 
        if(isset($_POST['acc_GrupoTarifa'])){
            if (!empty($_POST['acc_GrupoTarifa']) || $_POST['acc_GrupoTarifa'] != NULL ) {
                $_obj->set_acc_GrupoTarifa($_POST['acc_GrupoTarifa'] ?? null);
            }    
        }
        if(isset($_POST['acc_Exento'])){
            if (!empty($_POST['acc_Exento']) || $_POST['acc_Exento'] != NULL ) {
                $_obj->set_acc_Exento($_POST['acc_Exento']);
            }    
        }

        // Habilitamos que retorne un array de resultados
        $_obj->habilita1ResultadoEnArray();
        $arr = $_obj->consultar(); // Método heredado de DAOGeneral

        if (is_array($arr) && count($arr)) {
            // Transformamos los objetos en array (según sea tu implementación)
            $R = [];
            foreach ($arr as $obj) {
                $R[] = $obj->getArray(); 
            }
            $this->_ok = 1;
            $this->_mensaje = "Actividad Comercio consultados con éxito";
            return $R;
        } else {
            $this->_ok = 0;
            $this->_mensaje = "No existen Actividad Comercio con los filtros seleccionados";
            return [];
        }
    }

    /**
     * Inactiva (cambia estado) de un contribuyente
     */
    protected function _inactivarActividadesComercio()
    {
        $_obj = new \erpsoftsas\DAO_ActividadesComercio();
        // Se asume que recibes un ID y un nuevo estado
        $_obj->set_acc_Id($_POST['acc_Id']);
        $_obj->set_acc_Estado($_POST['acc_Estado']);

        if (!$_obj->guardar()) {
            $this->_ok = 0;
            $this->_mensaje = $_obj->getMysqlError();
        } else {
            $id = $_obj->get_acc_Id();
            $this->_ok = 1;
            $this->_mensaje = "Actividades Comercio ID $id inactivado correctamente";
        }
        return $_obj->getArray();
    }
}

// Clase de excepción específica para Contribuyentes
class ActividadesComercioException extends \Exception { }

// Ejecutamos la función principal
\erpsoftsas\ControladorActividadesComercio::run();
