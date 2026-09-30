<?php
namespace erpsoftsas;
include_once $_SERVER['DOCUMENT_ROOT'].'/erpsoftsas/business/class.conexionUsuarios.php';
include_once $_SERVER['DOCUMENT_ROOT'].'/erpsoftsas/business/class.conexionSqlServer.php';

class DAOGeneral {
    
    /**
     * si la consulta arroja un resultado devuelve resultado, true: en array (como si hubiera mas de un resultado), false[default]: misma clase que consulta
     * @var type 
     */
    private $_1resultadoEnArray = false;
    /**
     *
     * @var type 
     */
    protected $_operador = array('=','<>','<','>');
	
    protected $_indexOperador;
    protected $_usar_tipodato ;
    protected $_unico;
    protected $_query;
    protected $_namespace;

    private $_having;
    /**
     * Limit de la consulta (int 1, int 2)
     * @var array
     */
    private $_limit = false; 
	
    private $_numFilasConsultadas;

    private $_mysqlError = false;
    
    protected $_ordenar = array();

    public function __construct() {
       
    }
    /**
     * Establecer limiites para la consulta
     * @param type $val1
     * @param type $val2
     */
    public function setLimit($val1, $val2 = null){
        $this->_limit[0] = $val1;
        if(!empty($val2)){
            $this->_limit[1] = $val2;
        }
    }
	
    public function getNumFilasConsultadas(){
            return $this->_numFilasConsultadas;	
    }

    public function getMysqlError(){
            return $this->_mysqlError;
    }
    function set_namespace($_namespace) {
        $this->_namespace = $_namespace;
    }
    /**
     * Obtener la conexion
     * @return ConexionSQL
     */
    private function _obtenerConexion() {
        $con = NULL;
        
        $this->set_namespace('sqlserver');
        switch ($this->_namespace){
            case 'sqlserver':
                 // 🔹 Conexión a SQL Server
                $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
            break;

            default :
                // 🔹 Conexión a MYSQL
                $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        }
        return $con;
    }

        /**
     * 
     * @return type
     */
    public function getQuery() {
        return $this->_query;
    }
    /**
     * 
     * @param array $operador 0 -> '=','<>','<','>'
     */
    public function setIndexOperador(array $operador){
        $this->_indexOperador = $operador;
    }
    
    /**
     * 
     */
    public function habilita1ResultadoEnArray(){
        $this->_1resultadoEnArray = true;
    }
    /**
     * 
     */
    public function deshabilita1ResultadoEnArray(){
        $this->_1resultadoEnArray = false;
    }
	
    /**
    * Implementar clausula having array("campo = valor", "campo = valor")
    */
    public function setHaving(array $having){
            $this->_having = $having;
    }
    /**
     * 
     * @return type
     */
    public function get_usar_tipodato() {
        return $this->_usar_tipodato;
    }
    
    
    /**
     * Obtner el mapa de las clases DAO
     * @return array
     */
    public function getMapa(){
        return $this->_mapa;
    }
    /**
     * Nombre de la tabla en base de datos
     * @return string
     */
    public function getTabla(){
        return $this->_tabla;
    }
    /**
     * Obtener primario
     * @return string
     */
    public function getPrimario(){
        return $this->_primario;
    }
    /**
     * Establecer el dato primario
     * @param type $dato
     */
    public function setPrimario($dato){
        $this->{'_'.$this->_primario} = $dato;
    }
    
    public function setOrdenar(array $array) {
        $this->_ordenar = $array;
    }
    
    public function getUnico($index = NULL) {
        return $index == NULL ? $this->_unico : $this->_unico[$index];
    }

    /**
     * Texto listo para ir DENTRO de un literal '...' de SQL Server.
     *
     * Este DAO arma las consultas pegando los valores entre comillas (no
     * parametriza, ver CLAUDE.md). Sin duplicar la comilla simple, un nombre
     * como "Donde Pepe's" o una razon social "INVERSIONES D'LUCA" cerraba el
     * literal antes de tiempo: la consulta no compilaba, el controlador
     * respondia 500 con el cuerpo vacio y la pantalla solo decia "error de
     * conexion". En SQL Server la comilla se escapa duplicandola, y el motor
     * guarda UNA sola: en la base queda el texto tal como se escribio.
     *
     * Por lo mismo, quien llame al DAO NO debe escapar por su cuenta: la
     * comilla quedaria doble en la base, y en una clave el hash dejaria de
     * coincidir con el del login. _cambiarClave (class.usuarios.php) lo hacia
     * a mano y se le quito al traer el escape aqui (2026-09-28).
     *
     * El driver devuelve las fechas como DateTime, que no se puede pegar a un
     * texto (PHP lanza Error): se escribe como AAAA-MM-DD hh:mm:ss.
     */
    /**
     * Huella de una clave: SHA-1 del texto en UTF-8, en hexadecimal mayusculas.
     *
     * Es la MISMA cuenta que hace el login (sha1() en PHP). Hasta el 2026-09-29
     * la calculaba SQL Server con HASHBYTES('SHA1', '...') sobre un literal
     * VARCHAR, que la base convierte a Windows-1252 (Modern_Spanish_CI_AS): para
     * claves con ñ o tildes ("Contraseña2026") el hash guardado no era el del
     * login, la cuenta se creaba ("Datos ingresados correctamente") y despues
     * no podia entrar. Para ASCII las dos cuentas dan lo mismo.
     */
    public static function hashClave($clave)
    {
        return strtoupper(sha1((string) $clave));
    }

    /**
     * La huella con que se guardaron las claves ANTES del 2026-09-29 (la de
     * HASHBYTES sobre Windows-1252). Solo difiere de hashClave() si la clave
     * tiene caracteres fuera de ASCII. La usa el login para dejar entrar a esas
     * cuentas y pasarlas a la huella nueva.
     */
    public static function hashClaveAnterior($clave)
    {
        $clave = (string) $clave;
        if (!preg_match('/[^\x00-\x7F]/', $clave)) { return self::hashClave($clave); }
        return strtoupper(sha1(mb_convert_encoding($clave, 'Windows-1252', 'UTF-8')));
    }

    protected static function _literalSql($valor){
        if ($valor instanceof \DateTimeInterface) {
            $valor = $valor->format('Y-m-d H:i:s');
        }
        return str_replace("'", "''", (string) $valor);
    }

    /**
     *
     * @return boolean
     */
    public function guardar(){
        $con = $this->_obtenerConexion();
        $set = array();
       
        foreach($this->_mapa as $nom_campo => $arrAtributos){  
            //echo "antes ".$nom_campo."  pri ".$this->_primario." "; 
            if ($this->{'_'. $nom_campo} !== null && $nom_campo != $this->_primario && !isset($arrAtributos['sql'])) {
                //echo "entro "; 
                switch($arrAtributos['tipodato']){
                case 'blob': // convierte a binario los elementos (archivos) contenidos en una variable blob
                    $binario_contenido = addslashes(fread(fopen($this->{'_' . $nom_campo},"rb"),filesize($this->{'_' . $nom_campo}))) ;
                    $set[] = $nom_campo . " = '" . $binario_contenido . "'";
                break;
/*
                case 'clave':
                        if(!empty($this->{'_' . $nom_campo}))
                        $set[] = $nom_campo . " = SHA1('" . $this->{'_' . $nom_campo} . "')";
                break;
*/
                case 'clave':
                    if(!empty($this->{'_' . $nom_campo})) {
                        // La comilla duplicada no cambia el hash: SQL Server la lee
                        // como UNA, y HASHBYTES recibe la clave tal como se escribio
                        // (la misma que el login compara con sha1() en PHP).
                        if ($this->_namespace === 'sqlserver') {
                            // La huella se calcula en PHP, igual que el login (ver
                            // hashClave): con HASHBYTES una clave con ñ o tilde
                            // quedaba con otra huella y la cuenta no podia entrar.
                            $set[] = $nom_campo . " = '" . self::hashClave($this->{'_' . $nom_campo}) . "'";
                        } else {
                            // MySQL
                            $set[] = $nom_campo . " = SHA1('" . self::_literalSql($this->{'_' . $nom_campo}) . "')";
                        }
                    }
                break;
                case 'bit':
                      $set[] = $nom_campo . " = " . $this->{'_' . $nom_campo} . "";
                break;
                default : // tratamiento a cuaquier otro elemento

                    $set[] = $nom_campo . " = '" . self::_literalSql($this->{'_' . $nom_campo}) . "'";
                }
            }
        }
        
        $where = "";
/*
        if(!empty($this->{'_'.$this->_primario})){
            $where = " WHERE $this->_primario = ". $this->{'_'.$this->_primario} ;
            $query = "update ".$this->_tabla." set ".implode(",", $set) . $where;
        }else{
            $query = "insert into ".$this->_tabla." set ".  implode(",", $set) ;
        }
*/
        if(!empty($this->{'_'.$this->_primario})){
            $where = " WHERE $this->_primario = ". $this->{'_'.$this->_primario};
            $query = "UPDATE ".$this->_tabla." SET ".implode(",", $set).$where;
        }else{
            // Para SQL Server y MySQL
            $campos = [];
            $valores = [];

            foreach($set as $s){
                list($campo, $valor) = explode('=', $s, 2);
                $campos[] = trim($campo);
                $valores[] = trim($valor);
            }

            $query = "INSERT INTO ".$this->_tabla." (".implode(',', $campos).") VALUES (".implode(',', $valores).")";
        }


        //"**$query";
        //print_r($query);
        if($id = $con->consultar($query)){
            if(empty($this->{'_'.$this->_primario})){
                $this->{'_'.$this->_primario} = $con->obtenerIdInsertado();
            }
            return true;
        }else{
			$this->_mysqlError = $con->obtenerError();
		}
        return false;
    }
    /**
     * 
     * @return boolean|\clases_llamada
     */
    public function consultar() {
        $where = array();
        $select = array();
        
        foreach($this->_mapa as $nom_campo => $arrAtributos){
            if ($this->{'_' . $nom_campo} !== null) {
                switch($arrAtributos['tipodato']){
                    // Mismo escape que guardar() (ver _literalSql): el login busca
                    // aqui el usuario que se escribe, y un filtro con comilla
                    // tampoco debe partir la consulta.
                    case 'varchar-like':
                        $where[] = $nom_campo . " LIKE '%" . self::_literalSql($this->{'_' . $nom_campo}) . "%' ";
                    break;
                    default :
                        if(is_array($this->{'_' . $nom_campo} )){
                            $aux = array();
                            for ($i = 0; $i < count($this->{'_' . $nom_campo}); $i++ ) {
                                $aux[] = "'".self::_literalSql($this->{'_' . $nom_campo}[$i])."'";
                            }
                            $where[] = $nom_campo . " in (" . implode(",", $aux ) . ")";
                        } else {
                            $where[] = ($nom_campo . " ".$this->_operador[isset($this->_indexOperador[$nom_campo]) ? $this->_indexOperador[$nom_campo] : 0 ]." '" . self::_literalSql($this->{'_' . $nom_campo}) . "'");
                        }
                }
            }
            if(isset($arrAtributos['sql']) && !empty($arrAtributos['sql'])){
                $select[] = $arrAtributos['sql'] . " as " . $nom_campo;
            }else{
                $select[] = $nom_campo;
            }
        }
/*
        if (count($where) == 0) {
            $query = "select ".implode(",",$select)." from " . $this->_tabla . " where 1 ";
        } else {
            $query = "select ".implode(",",$select)." from " . $this->_tabla . " where " . implode(" AND ", $where)." ";
        }
*/
        if (count($where) == 0) {
            $query = "select ".implode(",", $select)." from " . $this->_tabla . " where 1=1 ";
        } else {
            $query = "select ".implode(",", $select)." from " . $this->_tabla . " where " . implode(" AND ", $where)." ";
        }



		// having
		if(is_array($this->_having) && count($this->_having) > 0){
			$query .= (" HAVING (" . implode(" AND ", $this->_having). ")");
		}
        // orden 
        if(isset($this->_ordenar) && is_array($this->_ordenar) && count($this->_ordenar) > 0){
            $query .= ( " ORDER BY ".implode(",",  $this->_ordenar));
        }
        // limites
        if(!empty($this->_limit)){
            $query .= (" LIMIT " . implode(",", $this->_limit));
        }
        //echo "|$query"; die();
        $con = $this->_obtenerConexion();
        $this->_query = $query;
        $id = $con->consultar($query,[]);
        $this->_numFilasConsultadas = $con->getNumeroFilasConsultadas( $id );
        //echo "consultado: $nummm -- ";
        //echo $con->getNumeroFilasConsultadas($id);
        if($res = $con->obnerFila($id)) {
            if ($con->getNumeroFilasConsultadas($id) == 1 && !$this->_1resultadoEnArray) {// si viene mas de un resultado debe clonarse la clase y retornar en un arreglo de clases
                //$res = $con->obnerFila($id);
                foreach($this->_mapa as $nom_campo => $arrAtributos){
                    $this->{'_' . $nom_campo} = $res[$nom_campo];
                }
                return true;
            } else {
                $R = array();
                do{
                    $clases_llamada = get_called_class();
                    $obj = new $clases_llamada()  ;
                    //print_r($this->_mapa);
                    foreach($this->_mapa as $nom_campo => $arrAtributos){
                        //print_r($arrAtributos);
                        //echo "$nom_campo : $res[$nom_campo] ";
                        //$obj->{'_' . $this->_mapa[$i]} = $res[$this->_mapa[$i]];
                        $obj->{'set_'.$nom_campo}($res[$nom_campo]);
                    }
                    //print_r($obj);
                    $R[] = $obj;
                }while($res = $con->obnerFila($id));
                //$con->liberarResultado($id);
                return $R;
            }
        }
        return false;
    }
    /**
     * eliminar
     * @return boolean
     */
    public function eliminar(){
        $where = array();
        foreach($this->_mapa as $nom_campo => $arrAtributos){
            if ($this->{'_' . $nom_campo} !== null) {
                $where[] = $nom_campo . " = '" . self::_literalSql($this->{'_' . $nom_campo}) . "'";
            }
        }
        if (count($where) != 0) {
            $query = "DELETE FROM " . $this->_tabla ." WHERE " . implode(" AND ", $where);
        }
        $con = $this->_obtenerConexion();
        //echo $query;
        if(!$id = $con->consultar($query)){
            return false;
        }
        //$this->_numFilasConsultadas = $con->getNumeroFilasConsultadas($id);
        return true;
    }
    /**
     * Obtener los valores de la clase en formato array
     * @return array
     */
    public function getArray() {
        $arrDatos = array();
        foreach ($this->_mapa as $nom_campo => $arrAtributos) {
            $arrDatos[$nom_campo] = $this->{'get_' . $nom_campo}();
        }
        return $arrDatos;
    }

    public function get_obj_seccion(){
       return NULL; 
    }

    /**
     * Lista todos los registros de la tabla actual, excluyendo un ID si se desea.
     * Es útil, por ejemplo, para validar duplicados antes de guardar.
     *
     * @param int $idExcluir  ID que se desea excluir de la consulta (opcional)
     * @return array|false
     */
    public function listarRegistros($idExcluir = 0) {
        $con = $this->_obtenerConexion();
        $tabla = $this->_tabla;
        $primario = $this->_primario;

        $query = "SELECT * FROM $tabla";
        if (!empty($idExcluir)) {
            $query .= " WHERE $primario <> $idExcluir";
        }

        $data = $con->consultar($query, []);
        $registros = [];

        while ($fila = $con->obnerFila($data)) {
            $registros[] = $fila;
        }

        return $registros;
    }


}