<?php
include_once 'class.DAO.php'; 

class DAO_MTablas extends \erpsoftsas\DAOGeneral {
    
    protected $_id_tablas;
    protected $_id_maestro;
    protected $_valor_valor;
    protected $_valor_nombre;
    protected $_valor_estado;
    protected $_nomTabla;

    protected $_ordenar = array();
    
    protected $_tabla = 'sis_maestro_contenido';
    protected $_primario = 'id_tablas';
    
    protected $_mapa = array(
        'id_tablas' => array('tipodato' => 'integer'),
        'id_maestro' => array('tipodato' => 'integer'),
        'valor_valor' => array('tipodato' => 'varchar'),
        'valor_nombre' => array('tipodato' => 'varchar'),
        'valor_estado' => array('tipodato' => 'integer'),
        'nomTabla' => array('tipodato' => 'varchar', 'sql' => '(SELECT a.nom_tabla FROM sis_maestro_tablas a WHERE a.id_maestro = sis_maestro_contenido.id_maestro)')
    );   

    public function __construct() {
        parent::__construct();
    }
   
    function get_id_tablas() {
        return $this->_id_tablas;
    }

    function get_id_maestro() {
        return $this->_id_maestro;
    }

    function get_valor_valor() {
        return $this->_valor_valor;
    }

    function get_valor_nombre() {
        return $this->_valor_nombre;
    }

    function get_valor_estado() {
        return $this->_valor_estado;
    }

    function set_id_tablas($_id_tablas) {
        $this->_id_tablas = $_id_tablas;
    }

    function set_id_maestro($_id_maestro) {
        $this->_id_maestro = $_id_maestro;
    }

    function set_valor_valor($_valor_valor) {
        $this->_valor_valor = $_valor_valor;
    }

    function set_valor_nombre($_valor_nombre) {
        $this->_valor_nombre = $_valor_nombre;
    }

    function set_valor_estado($_valor_estado) {
        $this->_valor_estado = $_valor_estado;
    }
    function get_nomTabla() {
        return $this->_nomTabla;
    }

    function set_nomTabla($_nomTabla) {
        $this->_nomTabla = $_nomTabla;
    }
    /**
     * Establece valor_valor, debe establecer id_maestro
     */
    public function getSiguienteValorValor() {
        if(empty($this->_id_maestro)){
            return false;
        }
        $query = "SELECT IFNULL((SELECT valor_valor + 1 FROM $this->_tabla WHERE id_maestro = $this->_id_maestro ORDER BY CAST(valor_valor AS SIGNED) DESC LIMIT 1),1) sig";
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $id = $con->consultar($query);
        if(!$res = $con->obenerFila($id)){
            return false;
        }
        $this->set_valor_valor( $res['sig'] );
        return true;
    }
}