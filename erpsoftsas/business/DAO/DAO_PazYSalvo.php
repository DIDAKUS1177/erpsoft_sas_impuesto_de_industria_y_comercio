<?php
namespace erpsoftsas;
include_once 'class.DAO.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/class.conexionSqlServerPaz.php';
use ConexionMysqlUsuariosSqlServerPaz\ConexionSQLServerPaz;

class DAO_PazYSalvo extends \erpsoftsas\DAOGeneral {

    private $_codigo_predio;
    private $_nombre;
    private $_direccion;
    private $_avaluo;
    private $_anio_pago;
    private $_tiene_deuda;

    public function __construct() {
        parent::__construct("conf_pazysalvo");
    }

    /** ================================ GETTERS/SETTERS ================================ */
    public function set_codigo_predio($val) { $this->_codigo_predio = $val; }
    public function get_codigo_predio() { return $this->_codigo_predio; }

    public function set_nombre($val) { $this->_nombre = $val; }
    public function get_nombre() { return $this->_nombre; }

    public function set_direccion($val) { $this->_direccion = $val; }
    public function get_direccion() { return $this->_direccion; }

    public function set_avaluo($val) { $this->_avaluo = $val; }
    public function get_avaluo() { return $this->_avaluo; }

    public function set_anio_pago($val) { $this->_anio_pago = $val; }
    public function get_anio_pago() { return $this->_anio_pago; }

    public function set_tiene_deuda($val) { $this->_tiene_deuda = $val; }
    public function get_tiene_deuda() { return $this->_tiene_deuda; }

    /** ========================= MÉTODO: buscarPredios ========================= */
    public function buscarPredios($dato) {
        try {
            $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

            $query = "EXEC sp_buscar_predios @dato = ?";
            $params = array($dato);

            $result = $con->consultarConParametros($query, $params);
            $data = [];

            while ($row = $con->obnerFila($result)) {
                $data[] = $row;
            }

            return $data;
        } catch (\Exception $e) {
            throw new \erpsoftsas\PazYSalvoException("Error al buscar predios: " . $e->getMessage());
        }
    }

    /** ========================= NUEVO: obtenerCabecera =========================
     * Ejecuta el SP de la otra base de datos que trae la información principal
     * del certificado Paz y Salvo (Certificado, Propietario, Dirección, etc.)
     */
    public function obtenerCabecera($codigo_predio) {
        try {
            $con = ConexionSQLServerPaz::getInstance();
            $query = "EXEC [API].[SP_GET_DATOS_PAZ_Y_SALVO] NULL,NULL, @CodigoPredio = ?";
            $params = array($codigo_predio);
            $stmt = $con->consultarConParametros($query, $params);

            $data = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $data[] = $row;
            }

            return $data;
        } catch (\Exception $e) {
            throw new \erpsoftsas\PazYSalvoException("Error al obtener cabecera de Paz y Salvo: " . $e->getMessage());
        }
    }

    /** ========================= NUEVO: obtenerPropietarios =========================
     * Ejecuta el SP que devuelve la lista de propietarios del predio
     */
    public function obtenerPropietarios($codigo_predio) {
        try {
            $con = ConexionSQLServerPaz::getInstance();
            $query = "EXEC [API].[SP_GET_PROPIETARIOS] NULL, @CodigoPredio = ?";
            $params = array($codigo_predio);
            $stmt = $con->consultarConParametros($query, $params);

            $data = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $data[] = $row;
            }

            return $data;
        } catch (\Exception $e) {
            throw new \erpsoftsas\PazYSalvoException("Error al obtener propietarios de Paz y Salvo: " . $e->getMessage());
        }
    }

    /** ========================= CONVERTIR OBJETO A ARRAY ========================= */
    public function getArray() {
        return [
            "codigo_predio" => $this->_codigo_predio,
            "nombre" => $this->_nombre,
            "direccion" => $this->_direccion,
            "avaluo" => $this->_avaluo,
            "anio_pago" => $this->_anio_pago,
            "tiene_deuda" => $this->_tiene_deuda
        ];
    }
}
?>
