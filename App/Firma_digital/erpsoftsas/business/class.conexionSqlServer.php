<?php
namespace ConexionMysqlUsuariosSqlServer;

class ConexionSQLServer {
    private static $_conData;
    private static $_obj = null;
    private $_link;
    private $_logQuery;

    /**
     * Antes de generar una conexión ejecutar este método para establecer
     * a qué base de datos se conectará
     */
    public static function setConData(Conexiones_sqlserver $_obj) {
        self::$_conData = $_obj;
    }

    /**
     * Singleton: obtener instancia de conexión
     */
    public static function getInstance($BD = null) {
        if (self::$_obj === null) {
            self::$_obj = new self($BD);
        }
        return self::$_obj;
    }

    /*
     * TRANSACCIONES: van por la API del driver, no por consulta
     *
     * Los tres metodos mandaban "BEGIN TRANSACTION" / "COMMIT" / "ROLLBACK"
     * como si fueran consultas normales, y con esta conexion eso NO funciona:
     * la cadena abre MARS (varios conjuntos de resultados activos a la vez), y
     * en MARS cada sqlsrv_query es su propio lote. SQL Server deshace toda
     * transaccion que siga abierta al terminar el lote donde nacio, asi que el
     * BEGIN moria en el acto y devolvia el error 3997:
     *
     *   "A transaction that was started in a MARS batch is still active at the
     *    end of the batch. The transaction is rolled back."
     *
     * O sea que el begin() no abria nada: lo que viniera despues corria suelto
     * y el rollback() no tenia nada que deshacer. Una red de seguridad que no
     * estaba puesta.
     *
     * sqlsrv_begin_transaction actua sobre la CONEXION, no sobre un lote, asi
     * que la transaccion sobrevive a las consultas siguientes, que es lo unico
     * que se le pide.
     *
     * Descubierto el 2026-08-26 al escribir _liquidarSinGuardar(), que necesita
     * un rollback que de verdad deshaga. Los demas controladores que usan
     * transacciones van por ConexionMysqlUsuariosCentral\ConexionSQL, otra
     * clase: este arreglo no los toca.
     */

    /**
     * Inicia una transacción
     */
    public function begin() {
        if (!sqlsrv_begin_transaction($this->_link)) {
            throw new \Exception("No se pudo iniciar la transacción: " . print_r(sqlsrv_errors(), true));
        }
    }

    /**
     * Confirma una transacción
     */
    public function commit() {
        if (!sqlsrv_commit($this->_link)) {
            throw new \Exception("No se pudo confirmar la transacción: " . print_r(sqlsrv_errors(), true));
        }
    }

    /**
     * Revierte una transacción
     */
    public function rollback() {
        if (!sqlsrv_rollback($this->_link)) {
            throw new \Exception("No se pudo revertir la transacción: " . print_r(sqlsrv_errors(), true));
        }
    }

    /**
     * Constructor: abre la conexión automáticamente
     */
    public function __construct() {
        if (!(self::$_conData instanceof Conexiones_sqlserver)) {
            self::setConData(Conexiones_sqlserver::getConProduccion());
        }
        $this->_conectar();
    }

    /**
     * Conectar a SQL Server usando sqlsrv_connect()
     */
    private function _conectar() {
        $connectionInfo = array(
            "Database" => self::$_conData->getDatabase(),
            "UID" => self::$_conData->getUsername(),
            "PWD" => self::$_conData->getPassword(),
            "CharacterSet" => "UTF-8",
            "TrustServerCertificate" => true
        );

        $this->_link = \sqlsrv_connect(self::$_conData->getServer(), $connectionInfo);

        if (!$this->_link) {
            $error = print_r(sqlsrv_errors(), true);
            throw new \Exception("Error de conexión a SQL Server: " . $error);
        }
    }

    /**
     * Ejecutar una consulta (SELECT, INSERT, UPDATE, DELETE)
     */
    public function consultar($query, $params = array()) {
        $this->_logQuery = $query;
        // SQLSRV_CURSOR_KEYSET es un cursor DEL LADO DEL SERVIDOR: cada fila que
        // se lee (sqlsrv_fetch_array) implica un viaje de red al servidor SQL.
        // Contra la BD de produccion remota, con muchas filas eso se nota
        // muchisimo (~450ms por fila medido con 131 filas = 59s en total).
        // SQLSRV_CURSOR_CLIENT_BUFFERED trae todo el resultado en un solo viaje
        // y lo guarda en el cliente; sqlsrv_num_rows() sigue funcionando igual
        // (getNumeroFilasConsultadas() no se ve afectado), pero iterar las filas
        // ya no cuesta un round-trip cada vez.
        $options = array("Scrollable" => SQLSRV_CURSOR_CLIENT_BUFFERED);

        // Detecta si hay parámetros o placeholders
        if (!empty($params) || strpos($query, '?') !== false) {
            return $this->consultarConParametros($query, $params);
        }

        $stmt = sqlsrv_query($this->_link, $query, [], $options);
        if ($stmt === false) {
            $error = print_r(sqlsrv_errors(), true);
            throw new \Exception("Error al ejecutar consulta: " . $error . "\nQuery: " . $query);
        }
        return $stmt;
    }
    
    /**
     * Ejecutar una consulta SQL Server con parámetros (para procedimientos almacenados)
     * @param string $query  Consulta o SP, ejemplo: "EXEC sp_detalle_pazysalvo @codigo_predio = ?"
     * @param array  $params Arreglo con los valores a pasar
     * @return mixed         Resultado de sqlsrv_query() o sqlsrv_prepare()
     */
    public function consultarConParametros($query, $params = array()) {
        try {
            $this->_logQuery = $query;

            // Si la consulta contiene EXEC, lo convertimos en SELECT para habilitar el cursor
            if (stripos($query, 'EXEC') === 0) {
                // Envolvemos el EXEC en un SELECT con OPENQUERY (cursor-friendly)
                $query = "SET NOCOUNT ON; " . $query;
            }

            // 🔹 Activa el cursor scrollable
            // SQLSRV_CURSOR_KEYSET es un cursor DEL LADO DEL SERVIDOR: cada fila que
        // se lee (sqlsrv_fetch_array) implica un viaje de red al servidor SQL.
        // Contra la BD de produccion remota, con muchas filas eso se nota
        // muchisimo (~450ms por fila medido con 131 filas = 59s en total).
        // SQLSRV_CURSOR_CLIENT_BUFFERED trae todo el resultado en un solo viaje
        // y lo guarda en el cliente; sqlsrv_num_rows() sigue funcionando igual
        // (getNumeroFilasConsultadas() no se ve afectado), pero iterar las filas
        // ya no cuesta un round-trip cada vez.
        $options = array("Scrollable" => SQLSRV_CURSOR_CLIENT_BUFFERED);

            $stmt = sqlsrv_query($this->_link, $query, $params, $options);

            if ($stmt === false) {
                $errors = sqlsrv_errors();
                throw new \Exception("Error al ejecutar la consulta: " . print_r($errors, true));
            }

            return $stmt;
        } catch (\Exception $e) {
            throw new \Exception("Error en consultarConParametros_sqlServer(): " . $e->getMessage());
        }
    }



    /**
     * Obtener una fila de resultados
     */
    public function obnerFila($stmt) {
        if ($stmt) {
            return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        }
        return false;
    }

    /**
     * Obtener el número de filas de una consulta
     */
    public function getNumeroFilasConsultadas($stmt) {
        if ($stmt) {
            return sqlsrv_num_rows($stmt);
        }
        return 0;
    }

    /**
     * Obtener el último ID insertado (para campos IDENTITY)
     */
    public function obtenerIdInsertado() {
        $stmt = sqlsrv_query($this->_link, "SELECT SCOPE_IDENTITY() AS last_id");
        if ($stmt && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
            return $row['last_id'];
        }
        return null;
    }

    /**
     * Obtener el error más reciente
     */
    public function obtenerError() {
        $err = sqlsrv_errors(SQLSRV_ERR_ERRORS);
        if ($err) {
            return json_encode($err);
        }
        return '';
    }

    /**
     * Avanzar al siguiente resultado (si aplica)
     */
    public function obtenerNextResult($stmt) {
        if ($stmt) {
            return sqlsrv_next_result($stmt);
        }
        return false;
    }
    
}

/**
 * Clase de configuración de conexiones SQL Server
 */
class Conexiones_sqlserver {
    private $_server;
    private $_username;
    private $_password;
    private $_database;

    private static $_conexiones = array(
        'local' => array(
            'server' => 'DESARROLLO\SQLEXPRESS2019',
            'username' => 'sa',
            'password' => 'Server2019',
            'database' => 'erpsoftweb'
        ),
        'produccion' => array(
            'server' => '178.156.143.97',
            'username' => 'erpsoftweb',
            'password' => 'E26baAp6bW~xblk!',
            'database' => 'erpsoftweb'
        )
    );

    public function getServer() { return $this->_server; }
    public function getUsername() { return $this->_username; }
    public function getPassword() { return $this->_password; }
    public function getDatabase() { return $this->_database; }
    public function setDatabase($db) { $this->_database = $db; }
/*
    public static function getConLocal() {
        return self::_getConexion('local');
    }
*/
    public static function getConProduccion() {
        return self::_getConexion('produccion');
    }

    private static function _getConexion($nomConexion) {
        $obj = new self();
        $obj->_server = self::$_conexiones[$nomConexion]['server'];
        $obj->_username = self::$_conexiones[$nomConexion]['username'];
        $obj->_password = self::$_conexiones[$nomConexion]['password'];
        $obj->_database = self::$_conexiones[$nomConexion]['database'];

        // --- MARCA BLANCA: Sobrescribir con config.municipio.php si existen ---
        if (defined('DB_PROD_SERVER')) $obj->_server = DB_PROD_SERVER;
        if (defined('DB_PROD_USER'))   $obj->_username = DB_PROD_USER;
        if (defined('DB_PROD_PASS'))   $obj->_password = DB_PROD_PASS;
        if (defined('DB_PROD_NAME'))   $obj->_database = DB_PROD_NAME;

        return $obj;
    }
}
?>
