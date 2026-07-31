<?php
namespace predial;
include_once $_SERVER['DOCUMENT_ROOT'].'/predial/business/class.conexionUsuarios.php';

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
        switch ($this->_namespace){
            default :
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
                case 'clave':
                        if(!empty($this->{'_' . $nom_campo}))
                        $set[] = $nom_campo . " = SHA1('" . $this->{'_' . $nom_campo} . "')";
                break;
                case 'bit':
                      $set[] = $nom_campo . " = " . $this->{'_' . $nom_campo} . "";
                break;
                default : // tratamiento a cuaquier otro elemento
                    
                    $set[] = $nom_campo . " = '" . $this->{'_' . $nom_campo} . "'";
                }
            }
        }
        
        $where = "";
        if(!empty($this->{'_'.$this->_primario})){
            $where = " WHERE $this->_primario = ". $this->{'_'.$this->_primario} ;
            $query = "update ".$this->_tabla." set ".implode(",", $set) . $where;
        }else{
            $query = "insert into ".$this->_tabla." set ".  implode(",", $set) ;
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
                    case 'varchar-like':
                        $where[] = $nom_campo . " LIKE '%" . $this->{'_' . $nom_campo} . "%' ";
                    break;
                    default :
                        if(is_array($this->{'_' . $nom_campo} )){
                            $aux = array();
                            for ($i = 0; $i < count($this->{'_' . $nom_campo}); $i++ ) {
                                $aux[] = "'".$this->{'_' . $nom_campo}[$i]."'";
                            }
                            $where[] = $nom_campo . " in (" . implode(",", $aux ) . ")";
                        } else {
                            $where[] = ($nom_campo . " ".$this->_operador[isset($this->_indexOperador[$nom_campo]) ? $this->_indexOperador[$nom_campo] : 0 ]." '" . $this->{'_' . $nom_campo} . "'");
                        }
                }
            }
            if(isset($arrAtributos['sql']) && !empty($arrAtributos['sql'])){
                $select[] = $arrAtributos['sql'] . " as " . $nom_campo;
            }else{
                $select[] = $nom_campo;
            }
        }
        
        if (count($where) == 0) {
            $query = "select ".implode(",",$select)." from " . $this->_tabla . " where 1 ";
        } else {
            $query = "select ".implode(",",$select)." from " . $this->_tabla . " where " . implode(" AND ", $where)." ";
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
        $id = $con->consultar($query);
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
                $where[] = $nom_campo . " = '" . $this->{'_' . $nom_campo} . "'";
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

}