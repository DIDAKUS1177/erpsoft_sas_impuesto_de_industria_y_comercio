<?php
namespace erpsoftsas;

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/DAO/DAO_DeclaracionesICA.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER . '/business/controller/class.logs.php';

class ControladorDeclaracionesICA extends \erpsoftsas\Cabecera 
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
                    $respuesta = $_obj->_agregarDeclaracion();
                break;

                case 2:
                    $respuesta = $_obj->_editarDeclaracion();
                break;

                case 3:
                    $respuesta = $_obj->_consultarDeclaraciones();
                break;

                case 4:
                    $respuesta = $_obj->_inactivarDeclaracion();
                break;

                case 5:
                    $respuesta = $_obj->_consultarActividadesEstablecimiento();
                break;

                case 6:
                    $respuesta = $_obj->_insertarActividadesDeclaracionIca();
                break;

                case 7:
                    $respuesta = $_obj->_actualizarDeclaracionIca();
                break;

                case 8:
                    $respuesta = $_obj->_consultarDeclaracionesListado();
                break;

                default:
                    throw new \erpsoftsas\DeclaracionesICAException("Función no válida",0);
            }

            header('Content-type: application/json');

            echo json_encode(array(
                "ok" => $_obj->_ok,
                "mensaje" => $_obj->_mensaje,
                "datos" => $respuesta
            ));

        } catch (\erpsoftsas\DeclaracionesICAException $e) {

            $arrRespu = array(
                "ok"=>$e->getCode(),
                "mensaje"=>"Error: ".$e->getMessage(),
                "datos"=>""
            );

            header('Content-type: application/json');
            echo json_encode($arrRespu);
        }
    }

    protected function _agregarDeclaracion()
    {
            $_obj = new \erpsoftsas\DAO_DeclaracionesICA();

            $_obj->set_dec_AnioDeclaracion(date('Y'));
            $_obj->set_dec_MesDeclaracion(12);

            $_obj->set_dec_IdEstablecimiento($_POST['dec_IdEstablecimiento']);
            $_obj->set_dec_IdContribuyente($_POST['dec_IdContribuyente']);
            

            date_default_timezone_set('America/Bogota');
            $_obj->set_dec_FechaDeclaracion(date('Y-m-d'));
            $_obj->set_dec_HoraDeclaracion(date('H:i:s'));
            $_obj->set_dec_OpcionUso(1);

            $_obj->set_dec_Estado(1); // borrador

        if (!$_obj->guardar()) {

            $this->_ok = 0;
            $this->_mensaje = $_obj->getMysqlError();

        } else {

            $id = $_obj->get_dec_Id();

            // 🔥 ACTUALIZAR NUMERO DECLARACION = ID
            $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

            $sql = "UPDATE ind_declaraciones_ica 
                    SET dec_NumeroDeclaracion = ?
                    WHERE dec_Id = ?";

            $con->consultar($sql, [$id, $id]);

            $this->_ok = 1;
            $this->_mensaje = "Declaración creada correctamente ID = $id";

        }

        return $_obj->getArray();
    }


    protected function _editarDeclaracion()
    {

        $_obj = new \erpsoftsas\DAO_DeclaracionesICA();

        $_obj->set_dec_Id($_POST['dec_Id'] ?? null);

        foreach ($_POST as $campo => $valor) {

            $metodo = 'set_' . $campo;

            if(method_exists($_obj,$metodo)){
                $_obj->$metodo($valor);
            }

        }

        if (!$_obj->guardar()) {

            $this->_ok = 0;
            $this->_mensaje = $_obj->getMysqlError();

        } else {

            $id = $_obj->get_dec_Id();

            $this->_ok = 1;
            $this->_mensaje = "Declaración actualizada correctamente ID = $id";

        }

        return $_obj->getArray();

    }


    private function _consultarDeclaraciones()
    {

        $_obj = new \erpsoftsas\DAO_DeclaracionesICA();

        foreach ($_POST as $campo => $valor) {

            $metodo = 'set_' . $campo;

            if(method_exists($_obj,$metodo)){
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
            $this->_mensaje = "Declaraciones consultadas correctamente";

            return $R;

        } else {

            $this->_ok = 0;
            $this->_mensaje = "No existen declaraciones con los filtros seleccionados";

            return [];

        }

    }


    protected function _inactivarDeclaracion()
    {

        $_obj = new \erpsoftsas\DAO_DeclaracionesICA();

        $_obj->set_dec_Id($_POST['dec_Id'] ?? null);
        $_obj->set_dec_Activo(0);

        if (!$_obj->guardar()) {

            $this->_ok = 0;
            $this->_mensaje = $_obj->getMysqlError();

        } else {

            $id = $_obj->get_dec_Id();

            $this->_ok = 1;
            $this->_mensaje = "Declaración inactivada correctamente ID = $id";

        }

        return $_obj->getArray();
    }


    private function _consultarActividadesEstablecimiento(){

    $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

    $sql = "
        SELECT 
            ace_IdCodigoActividad,
            ace_Anio,
            acc_Codigo,
            acc_Nombre,
            FORMAT(acc_Tarifa,'0.000') AS acc_Tarifa
        FROM ind_actividad_establecimiento
        INNER JOIN ind_actividadescomercio
            ON acc_Id = ace_IdCodigoActividad
        WHERE ace_IdEstablecimiento = ?
    ";

    $res = $con->consultar($sql,[ $_POST['est_Id'] ]);

    $actividades = [];

    while($row = $con->obnerFila($res)){
        $actividades[] = $row;
    }

    $this->_ok = count($actividades) ? 1 : 0;
    $this->_mensaje = "Actividades cargadas";

    return $actividades;
}



private function _insertarActividadesDeclaracionIca(){

    $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

    $actividades = json_decode($_POST['actividades'], true);
    $idDeclaracion = $_POST['idDeclaracion'];
    $totales = json_decode($_POST['totales'], true);
    
    if(!$idDeclaracion){
        $this->_ok = 0;
        $this->_mensaje = "Id de declaración requerido";
        return [];
    }

    try{

    // ==========================
        // 1. ACTUALIZAR DECLARACIÓN
        // ==========================
        $sqlUpdate = "
        UPDATE ind_declaraciones_ica SET
            dec_TotalIngresos = ?,
            dec_IngresosFueraMunicipio = ?,
            dec_IngresosDevoluciones = ?,
            dec_IngresosExportaciones = ?,
            dec_IngresosVentas = ?,
            dec_IngresosActividades = ?,
            dec_IngresosOtrasActividades = ?,
            dec_BaseGravable = ?,

            dec_CapacidadInstalada = ?,
            dec_ValorImpuesto = ?
        WHERE dec_NumeroDeclaracion = ?
        ";

        $con->consultar($sqlUpdate, [
            $totales['dec_TotalIngresos'],
            $totales['dec_IngresosFueraMunicipio'],
            $totales['dec_IngresosDevoluciones'],
            $totales['dec_IngresosExportaciones'],
            $totales['dec_IngresosVentas'],
            $totales['dec_IngresosActividades'],
            $totales['dec_IngresosOtrasActividades'],
            $totales['dec_BaseGravable'],
            $totales['dec_CapacidadInstalada'],
            $totales['dec_ValorImpuesto'],
            $idDeclaracion
        ]);


        // ELIMINAR ACTIVIDADES EXISTENTES
        $sqlDelete = "DELETE FROM ind_declaraciones_ica_actividades 
                      WHERE dia_IdDeclaracion = ?";
        $con->consultar($sqlDelete, [$idDeclaracion]);

        // INSERTAR NUEVAS
        foreach($actividades as $a){

            $sqlInsert = "
                INSERT INTO ind_declaraciones_ica_actividades
                (
                    dia_IdDeclaracion,
                    dia_IdActividad,
                    dia_BaseGravable,
                    dia_Tarifa,
                    dia_ValorImpuesto,
                    dia_Activo,
                    dia_FechaCreador
                )
                VALUES (?,?,?,?,?,1,GETDATE())
            ";

            $con->consultar($sqlInsert, [
                $a['dia_IdDeclaracion'],
                $a['dia_IdActividad'],
                $a['dia_BaseGravable'],
                $a['dia_Tarifa'],
                $a['dia_ValorImpuesto']
            ]);
        }

        
        $anio   = $_POST['anio'];
        $mes    = $_POST['mes'];
        $numero = $_POST['numero'];
        
        $this->_ejecutarSpLiquidacion($anio,$mes,$numero,0);

        // ==========================
        // 5. CONSULTAR RESULTADO FINAL
        // ==========================
        $res = $con->consultar("
            SELECT *
            FROM ind_declaraciones_ica
            WHERE dec_NumeroDeclaracion = ?
        ", [$idDeclaracion]);

        $data = $con->obnerFila($res);

        $this->_ok = 1;
        $this->_mensaje = "Liquidación completa";

        return $data;

    }catch(\Exception $e){

        $this->_ok = 0;
        $this->_mensaje = $e->getMessage();

        return [];
    }

}


private function _ejecutarSpLiquidacion($anio,$mes,$numero, $campoSeleccionado){

    $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

    try{

        $sql = "EXEC sp_calculo_comercio ?, ?, ?, ?";
        $con->consultar($sql, [$anio, $mes, $numero,$campoSeleccionado]);

        $this->_ok = 1;
        $this->_mensaje = "SP ejecutado correctamente";

        return [];

    }catch(\Exception $e){

        $this->_ok = 0;
        $this->_mensaje = $e->getMessage();

        return [];
    }

}


private function _actualizarDeclaracionIca(){

    $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

    $idDeclaracion = $_POST['idDeclaracion'];
    $campoSeleccionado = $_POST['campoSeleccionado'];

    $valorLimpio = $_POST['valorLimpio'];
    
    $NombreCampo = 'dec_ValorConcepto'.$campoSeleccionado;

    if(!$idDeclaracion){
        $this->_ok = 0;
        $this->_mensaje = "Id de declaración requerido";
        return [];
    }

    try{
 
     // ==========================
        // 1. ACTUALIZAR DECLARACIÓN
        // ==========================
        $sqlUpdate = "
        UPDATE ind_declaraciones_ica SET
            ".$NombreCampo." = ?
        WHERE dec_NumeroDeclaracion = ?
        ";

        $con->consultar($sqlUpdate, [
            $valorLimpio,
            $idDeclaracion
        ]);


        $anio   = $_POST['anio'];
        $mes    = $_POST['mes'];
        $numero = $_POST['numero'];
        
        $this->_ejecutarSpLiquidacion($anio,$mes,$numero,$campoSeleccionado);

        // ==========================
        // 5. CONSULTAR RESULTADO FINAL
        // ==========================
        $res = $con->consultar("
            SELECT *
            FROM ind_declaraciones_ica
            WHERE dec_NumeroDeclaracion = ?
        ", [$idDeclaracion]);

        $data = $con->obnerFila($res);

        $this->_ok = 1;
        $this->_mensaje = "Liquidación completa";

        return $data;

    }catch(\Exception $e){

        $this->_ok = 0;
        $this->_mensaje = $e->getMessage();

        return [];
    }

}

private function _consultarDeclaracionesListado(){

    $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

    $sql = "
        SELECT d.*, 
               CASE WHEN f.fd_Id IS NOT NULL THEN 1 ELSE 0 END AS is_signed
        FROM ind_declaraciones_ica d
        LEFT JOIN firmas_declaraciones f ON f.fd_NumeroDeclaracion = CAST(d.dec_Id AS VARCHAR)
        WHERE d.dec_IdEstablecimiento = ?
        ORDER BY d.dec_Id DESC
    ";

    $res = $con->consultar($sql, [$_POST['dec_IdEstablecimiento']]);

    $data = [];

    while($row = $con->obnerFila($res)){
        $data[] = $row;
    }

    $this->_ok = count($data) ? 1 : 0;
    $this->_mensaje = "Filtrado correctamente";

    return $data;
}


}


class DeclaracionesICAException extends \Exception {}


\erpsoftsas\ControladorDeclaracionesICA::run();