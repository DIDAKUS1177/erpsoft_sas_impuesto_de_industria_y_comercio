<?php

namespace ConexionMysqlUsuariosCentral;

class ConexionSQL {
    private static $_conData;
    private static $_obj = null;
    private $_logQuery;

    private $_link;
    /**
     *
     * @var Conexiones_mysql
     */
    private $_conexiones ;
    /**
     * Antes de generar una conexion ejecutar este metodo para establecer a que base de datos se conectara
     * @param Conexiones_mysql $_obj
     */
    public static function setConData(Conexiones_mysql $_obj){
        self::$_conData = $_obj;
    }
    /**
     * Obtener instancia de conexion (previamente debio ejecutarse el metodo setConData en caso que la conexion haya fallado)
     * @return ConexionSQL
     */
    public static function getInstance($BD = NULL){
        if(self::$_obj === null){
            self::$_obj = new self($BD);
        }
        return self::$_obj;
    }
        
    public function begin() {
        if ($this->_link) {
            sqlsrv_begin_transaction($this->_link);
        }
    }
    public function commit() {
        if ($this->_link) {
            sqlsrv_commit($this->_link);
        }
    }
    public function rollback() {
        if ($this->_link) {
            sqlsrv_rollback($this->_link);
        }
    }
    public function __construct($_BD) {
        // generar conexion
        if(!(self::$_conData instanceof Conexiones_mysql)){
            self::setConData(Conexiones_mysql::getConLocal());
        }
        if(!empty($_BD))
            self::$_conData->setDataBase($_BD); // establecer la base de datos
        $this->_conectar();
    }
    /**
     * genera la conexion con la base de datos
     * @throws ConexionMysqlSQLException
     */
    private function _conectar(){
        $connectionInfo = array(
            "Database" => self::$_conData->getDatabase(),
            "UID" => self::$_conData->getUsername(),
            "PWD" => self::$_conData->getPassword(),
            "CharacterSet" => "UTF-8",
            "TrustServerCertificate" => true
        );
        $this->_link = sqlsrv_connect(self::$_conData->getServer(), $connectionInfo);
        
        if(!$this->_link){
            $errors = sqlsrv_errors();
            throw new ConexionMysqlSQLException("No se pudo conectar a SQL Server. ".  print_r($errors, true));
        }
    }
    /**
     * 
     * @param type $query
     * @return type
     */
    public function consultar($query){
        $this->_logQuery = $query;
        // Se añade Scrollable para permitir sqlsrv_num_rows()
        $result = sqlsrv_query($this->_link, $query, array(), array("Scrollable" => SQLSRV_CURSOR_KEYSET));
        return $result;
    }
    /**
     * Obtener numero de filas de una consulta
     * @param type $id
     * @return type
     */
    public function getNumeroFilasConsultadas($id){
        if ($id === false || $id === null) return 0;
        return sqlsrv_num_rows($id);
    }
    /**
     * 
     * @param type $id
     * @return type
     */
    public function obnerFila($id) {
        if(!empty($id) && $id !== false){
            return sqlsrv_fetch_array($id, SQLSRV_FETCH_ASSOC);
        }
        return false;
    }
    /**
     * Obtener ultimo elemento autoincrementable en campo id_primario
     * @return type
     */
    public function obtenerIdInsertado(){
        $stmt = sqlsrv_query($this->_link, "SELECT SCOPE_IDENTITY() AS id");
        if ($stmt) {
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            return $row['id'];
        }
        return false;
    }
    /**
     * Obtener error 
     * @return type
     */
    public function obtenerError(){
        $errors = sqlsrv_errors();
        return print_r($errors, true);
    }
    /**
     * Obtener la ultima sentencia ejecutada en metodo consultar()
     * @param type $codificado
     */
    public function obtenerQuery($codificado = true){
        return $codificado ? base64_encode($this->_logQuery) : $this->_logQuery;
    }
    
}
class ConexionMysqlSQLException extends \Exception{}

// Incluir configuración global del municipio si existe
if (file_exists(__DIR__ . '/config.municipio.php')) {
    require_once __DIR__ . '/config.municipio.php';
} else {
    // Valores por defecto si no existe la config
    define('DB_PROD_SERVER', 'localhost');
    define('DB_PROD_USER', 'erpsofts_predialdocumentos');
    define('DB_PROD_PASS', '17s51$nAa');
    define('DB_PROD_NAME', 'erpsofts_predialdocumentos');
    
    define('DB_DEV_SERVER', 'db');
    define('DB_DEV_USER', 'sa');
    define('DB_DEV_PASS', 'ErpsoftPassword123!');
    define('DB_DEV_NAME', 'erpsoftweb_2026-07-26_18-29-36');
}

class Conexiones_mysql{
    
    private $_server;
    private $_username;
    private $_password;
    private $_database;
    
    private static $_conexiones = array(
        'desarrollo' => array(
            'server' => '10.0.30.28',
            'username' => 'uvd_usr',
            'password' => '1.5Y7u.p3Bv2',
            'database' => 'uvd_usuarios'        
        ),
        'produccion' => array(
            'server' => DB_PROD_SERVER,
            'username' => DB_PROD_USER,
            'password' => DB_PROD_PASS,
            'database' => DB_PROD_NAME       
        ),
       'local' => array(
            'server' => DB_DEV_SERVER,            
            'username' => DB_DEV_USER,
            'password' => DB_DEV_PASS,
            'database' => DB_DEV_NAME      
        ),
    );
    public function getServer(){
        return $this->_server;
    }
    public function getUsername(){
        return $this->_username;
    }
    public function getPassword(){
        return $this->_password;
    }
    public function getDatabase(){
        return $this->_database;
    }
    public function setDataBase($_DB) {
        $this->_database = $_DB;
    }
    /**
     * 
     * @return Conexiones_mysql
     */
    public static function getConLocal(){
         return self::_getConexion('local');
    }
    /**
     * 
     * @param type $nomConexion
     * @return \self
     */
    private static function _getConexion($nomConexion){
        $_obj = new self();
        $_obj->_server = self::$_conexiones[$nomConexion]['server'];
        $_obj->_username = self::$_conexiones[$nomConexion]['username'];
        $_obj->_password = self::$_conexiones[$nomConexion]['password'];
        $_obj->_database = self::$_conexiones[$nomConexion]['database'];
        return $_obj;
    }
}
