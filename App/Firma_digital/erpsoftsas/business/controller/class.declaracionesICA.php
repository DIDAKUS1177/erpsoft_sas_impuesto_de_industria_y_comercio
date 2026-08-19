<?php
namespace erpsoftsas;

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/DAO/DAO_DeclaracionesICA.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER . '/business/controller/class.logs.php';
include_once SERVER . '/business/config.tributario.php';

class ControladorDeclaracionesICA extends \erpsoftsas\Cabecera 
{
    private $_funcion;
    private $_ok;
    private $_mensaje;

    /**
     * Contribuyente al que esta atado el usuario de la sesion, o null.
     * Mismo vinculo que usan los demas controladores:
     * conf_usuarios.usu_NumeroDocumento = ind_contribuyentes.ind_NumeroIdentificacion.
     */
    private static function _contribuyenteDeLaSesion($con)
    {
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }
        if (empty($_SESSION['id_usuario'])) { return null; }

        $fila = $con->obnerFila($con->consultar(
            "SELECT c.ind_Id
               FROM ind_contribuyentes c
               INNER JOIN conf_usuarios u ON u.usu_NumeroDocumento = c.ind_NumeroIdentificacion
              WHERE u.usu_Id = ?",
            [(int) $_SESSION['id_usuario']]
        ));

        return isset($fila['ind_Id']) ? (int) $fila['ind_Id'] : null;
    }

    /**
     * Punto UNICO de control de acceso del modulo de declaraciones.
     *
     * Antes ninguna de las funciones de este controlador miraba de quien era
     * la declaracion: bastaba tener sesion y cambiar dec_IdContribuyente o
     * dec_Id en la peticion para leer -y modificar- las declaraciones de otro
     * contribuyente. Comprobado con el usuario externo de prueba: pidiendo el
     * contribuyente 31 devolvia sus 13 declaraciones con ingresos e impuesto.
     * Sobre datos con reserva tributaria eso no es un detalle.
     *
     * Se resuelve aqui, en el despacho, y no funcion por funcion, porque son
     * mas de quince y cualquiera nueva heredaria el agujero. Para los roles de
     * Alcaldia (1 y 2) no cambia nada.
     *
     * Devuelve null si todo bien, o el mensaje de rechazo.
     */
    private static function _verificarAcceso()
    {
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }

        if (empty($_SESSION['id_usuario'])) {
            return 'Debe iniciar sesión.';
        }

        $rol = isset($_SESSION['id_Rol']) ? (int) $_SESSION['id_Rol'] : 0;
        if (in_array($rol, [1, 2], true)) { return null; }

        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
        $propio = self::_contribuyenteDeLaSesion($con);
        if (!$propio) {
            return 'No se pudo establecer a qué contribuyente corresponde la sesión.';
        }

        // El filtro por contribuyente se fija: se ignora el que venga.
        if (array_key_exists('dec_IdContribuyente', $_POST)) {
            $_POST['dec_IdContribuyente'] = $propio;
        }

        // Una declaracion concreta tiene que ser suya.
        if (!empty($_POST['dec_Id'])) {
            $fila = $con->obnerFila($con->consultar(
                "SELECT dec_Id FROM ind_declaraciones_ica
                  WHERE dec_Id = ? AND dec_IdContribuyente = ?",
                [(int) $_POST['dec_Id'], $propio]
            ));
            if (!$fila) { return 'No tiene permiso sobre esta declaración.'; }
        }

        // Un establecimiento concreto tambien.
        if (!empty($_POST['dec_IdEstablecimiento'])) {
            $fila = $con->obnerFila($con->consultar(
                "SELECT est_Id FROM ind_establecimientos
                  WHERE est_Id = ? AND est_IdContribuyente = ?",
                [(int) $_POST['dec_IdEstablecimiento'], $propio]
            ));
            if (!$fila) { return 'No tiene permiso sobre este establecimiento.'; }
        }

        return null;
    }

    public static function run()
    {
        $_obj = new self();
        $_obj->_funcion = isset($_POST['funcion']) ? $_POST['funcion'] : null;

        $negado = self::_verificarAcceso();
        if ($negado !== null) {
            header('Content-type: application/json');
            echo json_encode(["ok" => 0, "mensaje" => $negado, "datos" => []]);
            return;
        }

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

                case 9:
                    $respuesta = $_obj->_presentarDeclaracion();
                break;

                case 10:
                    $respuesta = $_obj->_revertirABorrador();
                break;

                case 11:
                    $respuesta = $_obj->_crearCorreccion();
                break;

                case 12:
                    $respuesta = $_obj->_consultarActividadesContribuyente();
                break;

                case 13:
                    $respuesta = $_obj->_consultarDeclaracionParaEditar();
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

    /**
     * Crea la declaracion del CONTRIBUYENTE para el periodo actual.
     *
     * La declaracion es una sola por contribuyente (no por establecimiento):
     * un contribuyente con 3 locales solo declara una vez. Como la pantalla
     * de "Presentar Declaración" sigue mostrando un boton "Crear Declaración"
     * en la fila de CADA establecimiento (son la misma persona vista desde
     * distintos locales), este metodo es idempotente: si ya existe una
     * declaracion borrador para este contribuyente y periodo, la devuelve en
     * vez de intentar crear otra -asi no importa desde cual establecimiento
     * se pulse el boton, todas abren la misma declaracion compartida-.
     *
     * dec_IdEstablecimiento ya no es obligatorio (ver migracion 2026-08); se
     * sigue registrando el que disparo la creacion solo como referencia de
     * auditoria, nunca como filtro de a quien pertenece la declaracion.
     */
    protected function _agregarDeclaracion()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $idContribuyente = $_POST['dec_IdContribuyente'] ?? null;

        if (!$idContribuyente) {
            $this->_ok = 0;
            $this->_mensaje = "Contribuyente requerido";
            return [];
        }

        $anio = (int) date('Y');
        $mes  = 12;

        // ¿Ya existe una declaracion (borrador o firmada, no presentada) de
        // este contribuyente para el periodo actual? De ser asi se reabre en
        // vez de duplicar -el indice unico de la BD lo impediria de todos
        // modos, pero preguntar antes evita depender de que falle el INSERT-.
        $existente = $con->obnerFila($con->consultar(
            "SELECT * FROM ind_declaraciones_ica
             WHERE dec_IdContribuyente = ?
               AND dec_AnioDeclaracion = ?
               AND dec_MesDeclaracion = ?
               AND dec_DeclaracionCorrige IS NULL
               AND (dec_Estado IS NULL OR dec_Estado <> 2)",
            [$idContribuyente, $anio, $mes]
        ));

        if ($existente) {
            $this->_ok = 1;
            $this->_mensaje = "Ya existe una declaración en curso para este contribuyente; se abre esa";
            return $existente;
        }

        // El indice unico de la BD (UQ_declaracion_contribuyente_periodo)
        // es por (contribuyente, año, periodo) y NO distingue el estado:
        // si ya hay una PRESENTADA para este periodo, el INSERT de abajo
        // choca con ella igual. Antes esto no se comprobaba aparte, asi que
        // el error de SQL (duplicate key) quedaba sin capturar, tronaba
        // como fatal de PHP, y el usuario recibia una respuesta vacia (500)
        // sin ningun mensaje -"aprieto el boton y no pasa nada"-.
        $yaPresentada = $con->obnerFila($con->consultar(
            "SELECT dec_Id, dec_NumeroDeclaracion FROM ind_declaraciones_ica
             WHERE dec_IdContribuyente = ?
               AND dec_AnioDeclaracion = ?
               AND dec_MesDeclaracion = ?
               AND dec_DeclaracionCorrige IS NULL
               AND dec_Estado = 2",
            [$idContribuyente, $anio, $mes]
        ));

        if ($yaPresentada) {
            $this->_ok = 0;
            $this->_mensaje = "La declaración de este período ya fue presentada (N° "
                . ($yaPresentada['dec_NumeroDeclaracion'] ?: $yaPresentada['dec_Id'])
                . "). Para modificarla, genere una declaración de corrección "
                . "desde Consultar Declaraciones.";
            return [];
        }

        $_obj = new \erpsoftsas\DAO_DeclaracionesICA();

        $_obj->set_dec_AnioDeclaracion($anio);
        $_obj->set_dec_MesDeclaracion($mes);

        // Referencia de auditoria de cual establecimiento disparo la
        // creacion; la declaracion en si pertenece al contribuyente.
        if (!empty($_POST['dec_IdEstablecimiento'])) {
            $_obj->set_dec_IdEstablecimiento($_POST['dec_IdEstablecimiento']);
        }
        $_obj->set_dec_IdContribuyente($idContribuyente);

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
            $con->consultar(
                "UPDATE ind_declaraciones_ica SET dec_NumeroDeclaracion = ? WHERE dec_Id = ?",
                [$id, $id]
            );

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


    /**
     * Actividades economicas del CONTRIBUYENTE para armar la declaracion.
     *
     * Regla confirmada con la Secretaria de Hacienda: la base gravable se
     * agrupa por actividad (CIIU), sumando todos los establecimientos. Un
     * contribuyente con 3 restaurantes del mismo CIIU declara una sola vez
     * esa actividad, no tres. Por eso esta consulta trae las actividades
     * DISTINTAS de TODOS los establecimientos del contribuyente (DISTINCT
     * por acc_Id), no las de uno solo.
     *
     * La base gravable y la tarifa las sigue escribiendo la persona en la
     * pantalla (no se sabe cuanto factura cada establecimiento por
     * separado); esta consulta resuelve el "que actividades declarar",
     * no el "cuanto". n_establecimientos es informativo, para que se vea
     * en cuantos locales aplica cada actividad.
     */
    private function _consultarActividadesContribuyente(){

        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $idContribuyente = $_POST['dec_IdContribuyente'] ?? null;

        if (!$idContribuyente) {
            $this->_ok = 0;
            $this->_mensaje = "Contribuyente requerido";
            return [];
        }

        /*
 * Las actividades salen de ind_actividad_contribuyente, la tabla NUEVA.
 * Las migraciones 005 y 007 las subieron del establecimiento al
 * contribuyente y les quitaron el año.
 *
 * Esta consulta se habia quedado en la vieja
 * (ind_actividad_establecimiento), a la que ya nadie escribe: la pantalla
 * del RIT guarda en la nueva. Mientras nadie editara actividades las dos
 * coincidian -la migracion copio el contenido-, pero a la primera edicion
 * la declaracion habria seguido viendo la lista vieja. Se conservan los
 * nombres de columna con alias para no tocar el resto del flujo.
 */
$sql = "
            SELECT
                atc.atc_IdCodigoActividad AS ace_IdCodigoActividad,
                acc.acc_Codigo,
                acc.acc_Nombre,
                FORMAT(acc.acc_Tarifa,'0.000') AS acc_Tarifa,
                (SELECT COUNT(*) FROM ind_establecimientos e
                  WHERE e.est_IdContribuyente = atc.atc_IdContribuyente
                    AND e.est_Activo = 1) AS n_establecimientos
            FROM ind_actividad_contribuyente atc
            INNER JOIN ind_actividadescomercio acc
                ON acc.acc_Id = atc.atc_IdCodigoActividad
            WHERE atc.atc_IdContribuyente = ?
            ORDER BY acc.acc_Codigo
        ";

        $res = $con->consultar($sql, [$idContribuyente]);

        $actividades = [];
        while ($row = $con->obnerFila($res)) {
            $actividades[] = $row;
        }

        $this->_ok = count($actividades) ? 1 : 0;
        $this->_mensaje = $actividades
            ? "Actividades cargadas"
            : "El contribuyente no tiene actividades económicas registradas en ningún establecimiento";

        return $actividades;
    }


    /**
     * Datos de una declaracion YA CREADA, para abrir el formulario de
     * liquidacion en modo edicion: la declaracion misma (totales) y las
     * actividades tal como quedaron guardadas la ultima vez (con su base,
     * tarifa e impuesto reales) -a diferencia de
     * _consultarActividadesContribuyente(), que trae la lista agregada
     * DESDE CERO (bases en 0) para cuando se está creando una declaracion
     * nueva.
     *
     * "Editar" (el lapiz sobre una declaracion en borrador) estaba sin
     * implementar: mostraba un aviso de "disponible próximamente" tanto en
     * Presentar Declaración como en Consultar Declaraciones. No habia
     * ninguna forma de modificar una declaracion ya creada.
     */
    private function _consultarDeclaracionParaEditar(){

        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $idDeclaracion = $_POST['dec_Id'] ?? null;

        if (!$idDeclaracion) {
            $this->_ok = 0;
            $this->_mensaje = "Id de declaración requerido";
            return [];
        }

        $declaracion = $con->obnerFila($con->consultar(
            "SELECT * FROM ind_declaraciones_ica WHERE dec_Id = ?",
            [$idDeclaracion]
        ));

        if (!$declaracion) {
            $this->_ok = 0;
            $this->_mensaje = "La declaración no existe";
            return [];
        }

        // Solo tiene sentido editar un borrador: una declaracion firmada o
        // presentada sigue otro camino (editarFirmada la devuelve a
        // borrador primero; presentada solo se corrige, no se edita).
        if ((int) $declaracion['dec_Estado'] === 2) {
            $this->_ok = 0;
            $this->_mensaje = "Una declaración presentada no se puede editar. Genere una corrección.";
            return [];
        }

        $sqlAct = "
            SELECT
                da.dia_IdActividad,
                da.dia_BaseGravable,
                da.dia_Tarifa,
                da.dia_ValorImpuesto,
                ca.acc_Codigo,
                ca.acc_Nombre
            FROM ind_declaraciones_ica_actividades da
            INNER JOIN ind_actividadescomercio ca
                ON ca.acc_Id = da.dia_IdActividad
            WHERE da.dia_IdDeclaracion = ?
            ORDER BY ca.acc_Codigo
        ";

        $res = $con->consultar($sqlAct, [$idDeclaracion]);

        $actividades = [];
        while ($row = $con->obnerFila($res)) {
            $actividades[] = $row;
        }

        $this->_ok = 1;
        $this->_mensaje = "Declaración cargada para edición";

        return [
            'declaracion' => $declaracion,
            'actividades' => $actividades
        ];
    }


    private function _consultarActividadesEstablecimiento(){

    $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

    /*
 * Las actividades salen de ind_actividad_contribuyente, la tabla NUEVA.
 * Las migraciones 005 y 007 las subieron del establecimiento al
 * contribuyente y les quitaron el año.
 *
 * Esta consulta se habia quedado en la vieja
 * (ind_actividad_establecimiento), a la que ya nadie escribe: la pantalla
 * del RIT guarda en la nueva. Mientras nadie editara actividades las dos
 * coincidian -la migracion copio el contenido-, pero a la primera edicion
 * la declaracion habria seguido viendo la lista vieja. Se conservan los
 * nombres de columna con alias para no tocar el resto del flujo.
 */
$sql = "
        SELECT
            atc.atc_IdCodigoActividad AS ace_IdCodigoActividad,
            atc.atc_Anio              AS ace_Anio,
            acc.acc_Codigo,
            acc.acc_Nombre,
            FORMAT(acc.acc_Tarifa,'0.000') AS acc_Tarifa
        FROM ind_actividad_contribuyente atc
        INNER JOIN ind_establecimientos e
            ON e.est_IdContribuyente = atc.atc_IdContribuyente
        INNER JOIN ind_actividadescomercio acc
            ON acc.acc_Id = atc.atc_IdCodigoActividad
        WHERE e.est_Id = ?
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

            -- El JS de Liquidar no manda estos dos campos. Antes se leian
            -- igual y eso hacia DOS cosas malas: (1) en PHP 8 lanzaba un
            -- Warning de Undefined array key que en produccion
            -- (display_errors on) se imprimia ANTES del JSON y rompia el
            -- parseo, dejando el boton Liquidar sin hacer nada; (2) grababa
            -- NULL encima del valor que ya tuviera la declaracion.
            -- COALESCE conserva el valor actual si no viene.
            dec_CapacidadInstalada = COALESCE(?, dec_CapacidadInstalada),
            dec_ValorImpuesto      = COALESCE(?, dec_ValorImpuesto)
        WHERE dec_NumeroDeclaracion = ?
        ";

        $con->consultar($sqlUpdate, [
            $totales['dec_TotalIngresos']            ?? 0,
            $totales['dec_IngresosFueraMunicipio']   ?? 0,
            $totales['dec_IngresosDevoluciones']     ?? 0,
            $totales['dec_IngresosExportaciones']    ?? 0,
            $totales['dec_IngresosVentas']           ?? 0,
            $totales['dec_IngresosActividades']      ?? 0,
            $totales['dec_IngresosOtrasActividades'] ?? 0,
            $totales['dec_BaseGravable']             ?? 0,
            $totales['dec_CapacidadInstalada']       ?? null,
            $totales['dec_ValorImpuesto']            ?? null,
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

/**
 * ¿Este contribuyente necesita contador/revisor fiscal para presentar?
 *
 * Regla vigente desde 2026-08-11 (reemplaza la anterior, basada en tipo de
 * persona + umbral de 3.500 UVT -esa murió por instrucción explícita del
 * cliente-): si el contribuyente tiene registrado un correo de contador O
 * de revisor fiscal, la firma de esa persona es OBLIGATORIA para presentar,
 * sin importar tipo de persona ni ingresos. Registrar los datos de un
 * contador/revisor es, en la práctica, decir "esta declaración la tiene que
 * firmar también mi contador".
 *
 * Contador y revisor comparten una sola casilla de firma (ver
 * ind_EmailContador/ind_EmailRevisor en ind_contribuyentes), así que basta
 * con que UNA de las dos personas firme -no hace falta distinguir aquí cuál
 * de las dos hace falta, con is_signed_contador alcanza-.
 */
private function _requiereContador($idContribuyente)
{
    $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

    $contrib = $con->obnerFila($con->consultar(
        "SELECT ind_EmailContador, ind_EmailRevisor FROM ind_contribuyentes WHERE ind_Id = ?",
        [$idContribuyente]
    ));

    if (!$contrib) {
        return false;
    }

    return trim((string) ($contrib['ind_EmailContador'] ?? '')) !== ''
        || trim((string) ($contrib['ind_EmailRevisor'] ?? '')) !== '';
}


/**
 * Marca una declaracion como presentada. Antes esto lo simulaba
 * enteramente el frontend (un swal de "exito" sin llamar a nada); el
 * usuario podia creer que presento su declaracion sin que quedara
 * ningun registro real. Solo se permite presentar una declaracion que
 * ya este firmada (existe registro en firmas_declaraciones).
 */
private function _presentarDeclaracion(){

    $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

    $idDeclaracion = $_POST['dec_Id'] ?? null;

    if (!$idDeclaracion) {
        $this->_ok = 0;
        $this->_mensaje = "Id de declaración requerido";
        return [];
    }

    $decl = $con->obnerFila($con->consultar(
        "SELECT dec_IdContribuyente FROM ind_declaraciones_ica WHERE dec_Id = ?",
        [$idDeclaracion]
    ));

    if (!$decl) {
        $this->_ok = 0;
        $this->_mensaje = "La declaración no existe";
        return [];
    }

    $firmas = $con->consultar(
        "SELECT fd_Rol FROM firmas_declaraciones WHERE fd_NumeroDeclaracion = ?",
        [$idDeclaracion]
    );

    $roles = [];
    while ($f = $con->obnerFila($firmas)) {
        $roles[] = $f['fd_Rol'];
    }

    if (!in_array('declarante', $roles, true)) {
        $this->_ok = 0;
        $this->_mensaje = "La declaración debe estar firmada antes de presentarla";
        return [];
    }

    // La firma del contador/revisor es obligatoria solo si el contribuyente
    // tiene uno registrado (ver _requiereContador). Quien no registro
    // contador ni revisor presenta con su sola firma.
    $requiereContador = $this->_requiereContador($decl['dec_IdContribuyente']);

    if ($requiereContador && !in_array('contador', $roles, true)) {
        $this->_ok = 0;
        $this->_mensaje = "Falta la firma del contador o revisor fiscal. "
                        . "Es obligatoria para este contribuyente.";
        // Codigo estable para que el frontend distinga ESTE motivo de
        // rechazo de cualquier otro error, y en vez de mostrarlo como un
        // error suelto, encadene el flujo de OTP del contador y reintente
        // presentar solo -asi "Presentar" es un unico click de principio a
        // fin, sin que la persona tenga que notar y pulsar un boton
        // intermedio aparte-.
        return ['codigo' => 'FALTA_CONTADOR'];
    }

    $con->consultar(
        "UPDATE ind_declaraciones_ica
         SET dec_Estado = 2, dec_FechaPresentacion = GETDATE()
         WHERE dec_Id = ?",
        [$idDeclaracion]
    );

    $res = $con->consultar(
        "SELECT dec_Id, dec_Estado, dec_FechaPresentacion FROM ind_declaraciones_ica WHERE dec_Id = ?",
        [$idDeclaracion]
    );

    $this->_ok = 1;
    $this->_mensaje = "Declaración presentada correctamente";

    return $con->obnerFila($res);
}


/**
 * Devuelve una declaracion FIRMADA al estado borrador.
 *
 * Regla del cliente: si una declaracion ya firmada se edita, la firma deja
 * de ser valida -acredita un contenido que va a cambiar-, asi que se borra
 * y la declaracion vuelve a borrador. Es lo que permite el boton "Editar
 * borrador" sobre una firmada.
 *
 * Una declaracion ya PRESENTADA no se puede devolver por aqui: eso ya es un
 * acto ante el municipio y se corrige con _crearCorreccion(), que deja
 * rastro de la original.
 */
private function _revertirABorrador(){

    $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

    $idDeclaracion = $_POST['dec_Id'] ?? null;

    if (!$idDeclaracion) {
        $this->_ok = 0;
        $this->_mensaje = "Id de declaración requerido";
        return [];
    }

    $stmt = $con->consultar(
        "SELECT dec_Id, dec_Estado FROM ind_declaraciones_ica WHERE dec_Id = ?",
        [$idDeclaracion]
    );
    $decl = $con->obnerFila($stmt);

    if (!$decl) {
        $this->_ok = 0;
        $this->_mensaje = "La declaración no existe";
        return [];
    }

    if ((int)$decl['dec_Estado'] === 2) {
        $this->_ok = 0;
        $this->_mensaje = "Una declaración ya presentada no se puede editar. "
                        . "Debe generar una declaración de corrección.";
        return [];
    }

    // Se borran TODAS las firmas de la declaracion (declarante y, cuando
    // exista, contador/revisor): si el contenido cambia, ninguna sigue
    // acreditando lo que se firmo.
    $con->consultar(
        "DELETE FROM firmas_declaraciones WHERE fd_NumeroDeclaracion = ?",
        [$idDeclaracion]
    );

    $con->consultar(
        "UPDATE ind_declaraciones_ica
         SET dec_Estado = NULL, dec_FechaPresentacion = NULL
         WHERE dec_Id = ?",
        [$idDeclaracion]
    );

    $this->_ok = 1;
    $this->_mensaje = "La declaración volvió a borrador y se eliminó la firma";

    return ['dec_Id' => $idDeclaracion];
}


/**
 * Crea una DECLARACION DE CORRECCION a partir de una ya presentada.
 *
 * Copia todos los datos de la original (renglones, ingresos, actividades) a
 * una fila nueva y la enlaza con dec_DeclaracionCorrige, que es el campo que
 * el Formulario Unico Nacional imprime en "No. DE DECLARACION A CORREGIR".
 * La original NO se toca: queda como el acto que efectivamente se presento.
 *
 * La nueva nace sin firma y sin presentar, o sea en borrador, para que el
 * contribuyente ajuste lo que deba y la vuelva a firmar y presentar.
 */
private function _crearCorreccion(){

    $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

    $idDeclaracion = $_POST['dec_Id'] ?? null;

    if (!$idDeclaracion) {
        $this->_ok = 0;
        $this->_mensaje = "Id de declaración requerido";
        return [];
    }

    $stmt = $con->consultar(
        "SELECT * FROM ind_declaraciones_ica WHERE dec_Id = ?",
        [$idDeclaracion]
    );
    $orig = $con->obnerFila($stmt);

    if (!$orig) {
        $this->_ok = 0;
        $this->_mensaje = "La declaración no existe";
        return [];
    }

    if ((int)$orig['dec_Estado'] !== 2) {
        $this->_ok = 0;
        $this->_mensaje = "Solo se puede corregir una declaración ya presentada";
        return [];
    }

    // Se copian todas las columnas menos las que deben nacer de cero:
    // el id (identity), el numero de formulario, el estado/fechas de
    // presentacion y pago, y el enlace de correccion (que se fija abajo).
    $excluidas = [
        'dec_Id', 'dec_NumeroDeclaracion', 'dec_Estado', 'dec_FechaPresentacion',
        'dec_DeclaracionCorrige', 'dec_Pagado', 'dec_FechaPago', 'dec_ValorPago',
        'dec_BancoPago', 'dec_FechaRealPago', 'dec_RutaDeclaracion', 'dec_RutaPago',
        'dec_FechaCreador', 'dec_FechaModificador', 'dec_Modificador'
    ];

    $columnas = [];
    $valores  = [];

    foreach ($orig as $col => $val) {
        if (in_array($col, $excluidas, true)) { continue; }
        if ($val instanceof \DateTime) { $val = $val->format('Y-m-d H:i:s'); }
        $columnas[] = $col;
        $valores[]  = $val;
    }

    // dec_DeclaracionCorrige guarda el NUMERO de la declaracion corregida,
    // que es lo que pide el formulario (no el id interno).
    $columnas[] = 'dec_DeclaracionCorrige';
    $valores[]  = $orig['dec_NumeroDeclaracion'] ?: $orig['dec_Id'];

    $columnas[] = 'dec_FechaCreador';
    $valores[]  = date('Y-m-d H:i:s');

    $listaCols = implode(', ', $columnas);
    $marcas    = implode(', ', array_fill(0, count($columnas), '?'));

    $con->consultar(
        "INSERT INTO ind_declaraciones_ica ($listaCols) VALUES ($marcas)",
        $valores
    );

    $nuevo = $con->obnerFila($con->consultar(
        "SELECT TOP 1 dec_Id FROM ind_declaraciones_ica
         WHERE dec_DeclaracionCorrige = ? ORDER BY dec_Id DESC",
        [$orig['dec_NumeroDeclaracion'] ?: $orig['dec_Id']]
    ));

    $idNuevo = $nuevo['dec_Id'] ?? null;

    // El numero de formulario de la correccion: se usa el propio id, igual
    // que hace el flujo normal de creacion.
    if ($idNuevo) {
        $con->consultar(
            "UPDATE ind_declaraciones_ica SET dec_NumeroDeclaracion = ? WHERE dec_Id = ?",
            [$idNuevo, $idNuevo]
        );

        // Se replican las actividades gravadas de la original.
        $con->consultar(
            "INSERT INTO ind_declaraciones_ica_actividades
                (dia_IdDeclaracion, dia_IdActividad, dia_BaseGravable,
                 dia_Tarifa, dia_ValorImpuesto, dia_Activo, dia_FechaCreador)
             SELECT ?, dia_IdActividad, dia_BaseGravable,
                    dia_Tarifa, dia_ValorImpuesto, dia_Activo, GETDATE()
             FROM ind_declaraciones_ica_actividades
             WHERE dia_IdDeclaracion = ?",
            [$idNuevo, $idDeclaracion]
        );
    }

    $this->_ok = 1;
    $this->_mensaje = "Declaración de corrección creada. "
                    . "Corrige la N° " . ($orig['dec_NumeroDeclaracion'] ?: $orig['dec_Id']);

    return [
        'dec_Id'                 => $idNuevo,
        'dec_DeclaracionCorrige' => $orig['dec_NumeroDeclaracion'] ?: $orig['dec_Id']
    ];
}


private function _consultarDeclaracionesListado(){

    $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

    // Se devuelven las dos firmas por separado: presentar exige la del
    // declarante Y, cuando aplica, la del contador/revisor fiscal (ver
    // _requiereContador: no es obligatoria para todo el mundo). El
    // frontend necesita distinguirlas para saber que boton ofrecer.
    // Tambien viaja si el contribuyente tiene a quien enviarle el codigo.
    $sql = "
        SELECT d.*,
               CASE WHEN fd.fd_Id IS NOT NULL THEN 1 ELSE 0 END AS is_signed,
               CASE WHEN fc.fd_Id IS NOT NULL THEN 1 ELSE 0 END AS is_signed_contador,
               CASE WHEN LTRIM(RTRIM(ISNULL(c.ind_EmailContador,''))) <> ''
                      OR LTRIM(RTRIM(ISNULL(c.ind_EmailRevisor,'')))  <> ''
                    THEN 1 ELSE 0 END AS tiene_correo_contador,
               c.ind_Persona
        FROM ind_declaraciones_ica d
        LEFT JOIN firmas_declaraciones fd
               ON fd.fd_NumeroDeclaracion = CAST(d.dec_Id AS VARCHAR)
              AND fd.fd_Rol = 'declarante'
        LEFT JOIN firmas_declaraciones fc
               ON fc.fd_NumeroDeclaracion = CAST(d.dec_Id AS VARCHAR)
              AND fc.fd_Rol = 'contador'
        LEFT JOIN ind_contribuyentes c
               ON c.ind_Id = d.dec_IdContribuyente
        WHERE " . (!empty($_POST['dec_IdContribuyente'])
                    ? "d.dec_IdContribuyente = ?"
                    : "d.dec_IdEstablecimiento = ?") . "
        ORDER BY d.dec_Id DESC
    ";

    // La declaracion es del contribuyente: se prefiere filtrar por ahi
    // cuando venga ese dato. dec_IdEstablecimiento se conserva como filtro
    // de respaldo para pantallas que aun no migraron a pedir por
    // contribuyente.
    $filtro = !empty($_POST['dec_IdContribuyente'])
        ? $_POST['dec_IdContribuyente']
        : $_POST['dec_IdEstablecimiento'];

    $res = $con->consultar($sql, [$filtro]);

    $data = [];

    while($row = $con->obnerFila($res)){
        // El frontend necesita saber, SIN otra llamada, si a esta
        // declaracion le hace falta la firma del contador para poder
        // presentarse. Misma regla que _requiereContador(): tiene correo de
        // contador o de revisor registrado -tiene_correo_contador ya trae
        // ese calculo hecho desde el SQL de arriba-.
        $row['requiere_contador'] = (int) ($row['tiene_correo_contador'] ?? 0);

        $data[] = $row;
    }

    $this->_ok = count($data) ? 1 : 0;
    $this->_mensaje = "Filtrado correctamente";

    return $data;
}


}


class DeclaracionesICAException extends \Exception {}


\erpsoftsas\ControladorDeclaracionesICA::run();