<?php

namespace dextera;

class ConexionSQLServer {
    private static $_conData;
    private static $_obj = null;
    private $_logQuery;
    private $_link;

    
    /**
     * Antes de generar una conexion ejecutar este metodo para establecer a que base de datos se conectara
     * @param Conexiones_sqlServer $_obj
     */
    public static function setConData_sqlServer(Conexiones_sqlserver $_obj){
        self::$_conData = $_obj;
    }
    /**
     * Obtener instancia de conexion (previamente debio ejecutarse el metodo setConData en caso que la conexion haya fallado)
     * @return ConexionSQL
     */
    public static function getInstance_sqlServer($BD = NULL){
        if(self::$_obj === null){
            self::$_obj = new self($BD);
        }
        return self::$_obj;
    }
        
    public function begin_sqlServer() {
        $this->consultar_sqlServer("begin");
    }
    public function commit_sqlServer() {
        $this->consultar_sqlServer("commit");
    }
    public function rollback_sqlServer() {
        $this->consultar_sqlServer("rollback");
    }

    public function __construct() {
        if(!(self::$_conData instanceof Conexiones_sqlserver)){
            self::setConData_sqlServer(Conexiones_sqlserver::getConLocal_sqlServer());
        }
        $this->_conectar();
    }
    
    /**
     * Genera la conexion con la base de datos
     * @throws ConexionMysqlSQLException
     */
    private function _conectar(){
        $connectionInfo = array( "Database"=>self::$_conData->getDatabase_sqlServer(), "UID"=>self::$_conData->getUsername_sqlServer(), "PWD"=>self::$_conData->getPassword_sqlServer(), "CharacterSet" => "UTF-8");
        
        if(!$this->_link = sqlsrv_connect(self::$_conData->getServer_sqlServer(),$connectionInfo)){
            throw new ConexionsqlserverSQLException("No se pudo conectar. ".  sqlsrv_errors());
        }
    }
    
    /**
     * Ejetuta un query
     * @param type $query
     * @return type
     */
    public function consultar_sqlServer($query){
        $this->_logQuery = $query;
        $result = sqlsrv_query($this->_link,$query);
        return $result;
    }
    
    /**
     * Obtener numero de filas de una consulta
     * @param type $id
     * @return type
     */
    public function getNumeroFilasConsultadas_sqlServer($id){
        return sqlsrv_num_rows($id);
    }
    
    /**
     * @param type $id
     * @return type
     */
    public function obnerFila_sqlServer($id) {
        if($id){
            return sqlsrv_fetch_array($id, SQLSRV_FETCH_ASSOC);
        }
        return false;
    }
    
    /**
     * @param type $id
     * @return type
     */
    public function obtenerNextResult_sqlServer($id) {
        if($id){
            return sqlsrv_next_result($id);
        }
        return false;
    }

    /**
     * Obtener Error 
     * @return type
     */
    public function obtenerError_sqlServer(){
        return sqlsrv_errors(true);
    }
}

class ConexionsqlserverSQLException extends \Exception{}

class Conexiones_sqlserver{
    
    private $_server;
    private $_username;
    private $_password;
    private $_database;
    
    private static $_conexiones = array(
        'local' => array(
            'server' => '10.112.32.130',
            'username' => 'ImpWeb',
            'password' => 'Impl3m3nt4c10nW3b',
            'database' => 'IMPLEMENTACION_DEMO'       
        ),
        'produccion' => array(
            'server' => '10.112.32.130',
            'username' => 'AplicacionWeb',
            'password' => 'Aplicacion$Web%',
            'database' => 'IMPLEMENTACION'      
        ),
    );
    public function getServer_sqlServer(){
        return $this->_server;
    }
    public function getUsername_sqlServer(){
        return $this->_username;
    }
    public function getPassword_sqlServer(){
        return $this->_password;
    }
    public function getDatabase_sqlServer(){
        return $this->_database;
    }
    public function setDataBase_sqlServer($_DB) {
        $this->_database = $_DB;
    }
    
    /**
     * @return Conexiones_sqlServer
     */
    public static function getConLocal_sqlServer(){
         return self::_getConexion('produccion');
    }
    
    /**
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

