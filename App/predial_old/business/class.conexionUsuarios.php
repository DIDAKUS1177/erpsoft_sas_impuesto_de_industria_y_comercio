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
        $this->consultar("begin");
    }
    public function commit() {
        $this->consultar("commit");
    }
    public function rollback() {
        $this->consultar("rollback");
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
        //print_r(self::$_conData);
        if(!$this->_link = mysqli_connect(self::$_conData->getServer(), self::$_conData->getUsername(), self::$_conData->getPassword())){
            throw new ConexionMysqlSQLException("No se pudo conectar. ".  mysqli_error());
        }
        if(!mysqli_select_db($this->_link,self::$_conData->getDatabase())){
            throw new ConexionMysqlSQLException("No se pudo seleccionar base de datos '". self::$_conData->getDatabase()."' ERR: ". mysqli_error());
        }
        mysqli_set_charset($this->_link,'utf8');
    }
    /**
     * 
     * @param type $query
     * @return type
     */
    public function consultar($query){
        $this->_logQuery = $query;
        
        $result = mysqli_query($this->_link,$query);
        return $result;
    }
    /**
     * Obtener numero de filas de una consulta
     * @param type $id
     * @return type
     */
    public function getNumeroFilasConsultadas($id){
        return mysqli_num_rows($id);
    }
    /**
     * 
     * @param type $id
     * @return type
     */
    public function obnerFila($id) {
        
        if(!empty($id)){
            return mysqli_fetch_array($id, MYSQLI_ASSOC);
        }
        return false;
    }
    /**
     * Obtener ultimo elemento autoincrementable en campo id_primario
     * @return type
     */
    public function obtenerIdInsertado(){
        return mysqli_insert_id($this->_link);
    }
    /**
     * Obtener error 
     * @return type
     */
    public function obtenerError(){
        return mysqli_errno($this->_link);
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
            'server' => 'localhost',
            'username' => 'erpsofts_predialdocumentos',
            'password' => '17s51$nAa',
            'database' => 'erpsofts_predialdocumentos'       
        ),
       'local' => array(
		   'server' => 'localhost',            
            'username' => '56Svt15l_2025.',
            'password' => '56Svt~15l_2025.',
            'database' => 'sistema'    
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

