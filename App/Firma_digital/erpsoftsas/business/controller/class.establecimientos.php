<?php
namespace erpsoftsas;

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/DAO/DAO_Establecimientos.php';
include_once SERVER . '/business/DAO/DAO_ActividadEstablecimiento.php';

include_once SERVER . '/business/class.sessions.php';
include_once SERVER . '/business/controller/class.logs.php';

class ControladorEstablecimientos extends \erpsoftsas\Cabecera 
{
    private $_funcion;
    private $_ok;
    private $_mensaje;

    public static function run() 
    {
        $_obj = new self();
        $_obj->_funcion = isset($_POST['funcion']) ? $_POST['funcion'] : null;

        try {
            //$con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
            //$con->begin();
            $respuesta = null;
            switch ($_obj->_funcion) {
                case 1:
                    $respuesta = $_obj->_agregarEstablecimientos();
                    break;
                case 2:
                    $respuesta = $_obj->_editarEstablecimientos();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarEstablecimientos();
                    break;
                case 4:
                    $respuesta = $_obj->_inactivarEstablecimientos();
                    break;
                case 20:
                    $respuesta = $_obj->_guardarCorreosContadorRevisor();
                    break;
                default:
                    throw new \erpsoftsas\EstablecimientosException("Función no válida", 0);
            }

            //$con->commit();

            header('Content-type: application/json');
            echo json_encode(array(
                "ok" => $_obj->_ok, 
                "mensaje" => $_obj->_mensaje, 
                "datos" => $respuesta
            ));

        } catch (\erpsoftsas\EstablecimientosException $e) {
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

    protected function _agregarEstablecimientos() 
    {
        $_obj = new \erpsoftsas\DAO_Establecimientos();

        foreach ($_POST as $campo => $valor) {
            $metodo = 'set_' . $campo;
            $_obj->$metodo($valor);
        }
        
        $nomUsurio = $_obj->listarRegistros($_obj->get_est_Id());
        $longitud = count($nomUsurio);
        $nomduplicado=0;

        for($i=0; $i<$longitud; $i++){
            if($nomUsurio[$i]['est_Codigo'] == $_obj->get_est_Codigo()){
               $nomduplicado=1;
                break;
            }
        }


        $_obj->set_est_Estado(1);

         if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe ese Codigo en un establecimiento';
            $return= false; 
        }else{
                
            if (!$_obj->guardar()) {
                $this->_ok = 0;
                $this->_mensaje = $_obj->getMysqlError();
            } else {
                $id = $_obj->get_est_Id(); 

                // GUARDAR ACTIVIDADES
                if(isset($_POST['actividades'])){
                    $actividades = json_decode($_POST['actividades'], true);
                    foreach($actividades as $a){

                        $_objAct = new \erpsoftsas\DAO_ActividadEstablecimiento();

                        $_objAct->set_ace_IdCodigoActividad($a['ace_IdCodigoActividad']);
                        $_objAct->set_ace_IdEstablecimiento($id);
                        $_objAct->set_ace_Anio($a['ace_Anio']);
                        $_objAct->guardar();
                    }
                }

                $this->_ok = 1;
                $this->_mensaje = "Establecimiento agregado correctamente. ID = $id";
            }
            $return= $_obj->guardar();
        }
        return $return;
    }

    protected function _editarEstablecimientos()
    {
        $_obj = new \erpsoftsas\DAO_Establecimientos();
        $_obj->set_est_Id($_POST['est_Id'] ?? null);

        foreach ($_POST as $campo => $valor) {
            $metodo = 'set_' . $campo;
            $_obj->$metodo($valor);
        }

        $nomUsurio = $_obj->listarRegistros($_obj->get_est_Id());
        $longitud = count($nomUsurio);
        $nomduplicado=0;

        for($i=0; $i<$longitud; $i++){
            if($nomUsurio[$i]['est_Codigo'] == $_obj->get_est_Codigo()){
               $nomduplicado=1;
                break;
            }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe ese Codigo en un establecimiento';
            $return= false; 
        }else{

            if (!$_obj->guardar()) {
                $this->_ok = 0;
                $this->_mensaje = $_obj->getMysqlError();
            } else {
                $id = $_obj->get_est_Id();

                /*
                 * ACTIVIDADES ECONOMICAS
                 *
                 * Antes esto era un DELETE incondicional seguido de un
                 * "if (isset($_POST['actividades']))" para reinsertar. El
                 * problema: las 4 pantallas que editan mandan SIEMPRE la
                 * clave (formData.actividades = JSON.stringify(actividades)),
                 * asi que isset() es true incluso cuando el arreglo llega
                 * VACIO -que es lo que pasa si el modal se guarda antes de
                 * que cargarActividades() termine de pintar la tabla-. El
                 * DELETE corria, el foreach no insertaba nada, y las
                 * actividades se perdian sin aviso. Reproducido: un
                 * establecimiento con 2 actividades quedo en 0 enviando
                 * actividades=[].
                 *
                 * Ahora solo se reemplazan cuando llega al menos una. Un
                 * arreglo vacio se trata como "esta pantalla no trae
                 * actividades", no como "borralas todas": quitar todas las
                 * actividades dejaria al establecimiento sin poder declarar,
                 * y no es algo que deba pasar por accidente. Si mas adelante
                 * hace falta esa accion, debe ser explicita (un flag propio),
                 * nunca el efecto colateral de un arreglo vacio.
                 */
                $actividades = [];
                if (isset($_POST['actividades'])) {
                    $decodificadas = json_decode($_POST['actividades'], true);
                    if (is_array($decodificadas)) {
                        $actividades = $decodificadas;
                    }
                }

                if (count($actividades) > 0) {
                    $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

                    $con->consultar(
                        "DELETE FROM ind_actividad_establecimiento WHERE ace_IdEstablecimiento = ?",
                        [$id]
                    );

                    foreach ($actividades as $a) {
                        $_objAct = new \erpsoftsas\DAO_ActividadEstablecimiento();
                        $_objAct->set_ace_IdCodigoActividad($a['ace_IdCodigoActividad']);
                        $_objAct->set_ace_IdEstablecimiento($id);
                        $_objAct->set_ace_Anio($a['ace_Anio']);
                        $_objAct->guardar();
                    }
                } elseif (isset($_POST['actividades'])) {
                    error_log(
                        "establecimientos: se recibio actividades vacio para est_Id=$id; "
                        . "no se tocaron las actividades existentes (ver nota en _editarEstablecimientos)."
                    );
                }


                $this->_ok = 1;
                $this->_mensaje = "Establecimiento ID $id editado correctamente";
                $return = true;
            }
            // Antes aqui habia un segundo $_obj->guardar(): el UPDATE se
            // ejecutaba DOS veces por cada edicion, y ademas corria tambien
            // cuando el primero habia fallado, pisando el mensaje de error.
        }
        return $return;
    }

    
    private function _consultarEstablecimientos()
    {
        $_obj = new \erpsoftsas\DAO_Establecimientos();

        foreach ($_POST as $campo => $valor) {
            $metodo = 'set_' . $campo;
            $_obj->$metodo($valor);
        }

        $_obj->habilita1ResultadoEnArray();
        $arr = $_obj->consultar();

        if (is_array($arr) && count($arr)) {
            $R = [];
            

            $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

            foreach ($arr as $obj) {

                $est = $obj->getArray();

                // ============================
                // CORREOS DE CONTADOR / REVISOR
                // Viven en el contribuyente (la declaracion es una sola por
                // contribuyente), pero la pantalla del RIT los edita junto
                // con los demas datos del establecimiento.
                // ============================

                // Punto 16: contador y revisor tienen que verse tanto en el
                // RIT como en el establecimiento. Desde la migracion 003 el
                // dato vive en el contribuyente -antes estaba repetido en cada
                // local, sin nada que garantizara que las copias coincidieran-,
                // asi que aqui se lee de alli y viaja con el establecimiento.
                $datosContador = $con->obnerFila($con->consultar(
                    "SELECT ind_EmailContador, ind_EmailRevisor,
                            ind_Cedula_contador, ind_Nombre_contador, ind_Tarjeta_profesional,
                            ind_Cedula_revisor, ind_Nombre_revisor, ind_Tarjeta_profesional_revisor
                       FROM ind_contribuyentes WHERE ind_Id = ?",
                    [$est['est_IdContribuyente']]
                ));

                foreach ([
                    'ind_EmailContador', 'ind_EmailRevisor',
                    'ind_Cedula_contador', 'ind_Nombre_contador', 'ind_Tarjeta_profesional',
                    'ind_Cedula_revisor', 'ind_Nombre_revisor', 'ind_Tarjeta_profesional_revisor',
                ] as $campoContador) {
                    $est[$campoContador] = $datosContador[$campoContador] ?? '';
                }

                // ============================
                // CONSULTAR ACTIVIDADES
                // ============================

                $sql = "
                    SELECT 
                        ace_IdCodigoActividad,
                        ace_Anio,
                        acc_Codigo,
                        acc_Nombre
                    FROM ind_actividad_establecimiento
                    INNER JOIN ind_actividadescomercio
                        ON acc_Id = ace_IdCodigoActividad
                    WHERE ace_IdEstablecimiento = ?
                ";

                $res = $con->consultar($sql, [$est['est_Id']]);

                $actividades = [];

                while($row = $con->obnerFila($res)){
                    $actividades[] = $row;
                }

                $est['actividades'] = $actividades;

                $R[] = $est;

            }


            $this->_ok = 1;
            $this->_mensaje = "Establecimiento consultados con éxito";
            return $R;
        } else {
            $this->_ok = 0;
            $this->_mensaje = "No existen Establecimientos con los filtros seleccionados";
            return [];
        }
    }

    protected function _inactivarEstablecimientos()
    {
        $_obj = new \erpsoftsas\DAO_Establecimientos();
        $_obj->set_est_Id($_POST['est_Id'] ?? null);
        $_obj->set_est_Activo(0);

        if (!$_obj->guardar()) {
            $this->_ok = 0;
            $this->_mensaje = $_obj->getMysqlError();
        } else {
            $id = $_obj->get_est_Id();
            $this->_ok = 1;
            $this->_mensaje = "Establecimiento ID $id inactivado correctamente";
        }
        return $_obj->getArray();
    }


    /**
     * Guarda el correo del contador y del revisor fiscal.
     *
     * Van en el CONTRIBUYENTE, no en el establecimiento: la declaración es
     * una sola por contribuyente aunque tenga varios establecimientos, así
     * que quien la firma es uno solo. Estos correos son el destino del
     * código OTP de firma (ver microservicios/firmas/api.php).
     *
     * Se recibe el id del establecimiento porque es lo que la pantalla del
     * RIT tiene a mano; de ahí se resuelve su contribuyente.
     */
    private function _guardarCorreosContadorRevisor()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $idEstablecimiento = $_POST['est_Id'] ?? null;
        $idContribuyente   = $_POST['est_IdContribuyente'] ?? null;

        if (!$idContribuyente && $idEstablecimiento) {
            $fila = $con->obnerFila($con->consultar(
                "SELECT est_IdContribuyente FROM ind_establecimientos WHERE est_Id = ?",
                [$idEstablecimiento]
            ));
            $idContribuyente = $fila['est_IdContribuyente'] ?? null;
        }

        if (!$idContribuyente) {
            $this->_ok = 0;
            $this->_mensaje = 'No se pudo determinar el contribuyente';
            return [];
        }

        $correoContador = trim($_POST['ind_EmailContador'] ?? '');
        $correoRevisor  = trim($_POST['ind_EmailRevisor'] ?? '');

        foreach ([$correoContador, $correoRevisor] as $correo) {
            if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                $this->_ok = 0;
                $this->_mensaje = 'El correo "' . $correo . '" no es válido';
                return [];
            }
        }

        $con->consultar(
            "UPDATE ind_contribuyentes
                SET ind_EmailContador = ?, ind_EmailRevisor = ?
              WHERE ind_Id = ?",
            [$correoContador, $correoRevisor, $idContribuyente]
        );

        $this->_ok = 1;
        $this->_mensaje = 'Correos de contador/revisor actualizados';

        return ['ind_Id' => $idContribuyente];
    }
}

// Clase de excepción específica para Contribuyentes
class EstablecimientosException extends \Exception { }

// Ejecutamos la función principal
\erpsoftsas\ControladorEstablecimientos::run();