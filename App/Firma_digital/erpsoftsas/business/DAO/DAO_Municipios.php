<?php
namespace erpsoftsas;
include_once 'class.DAO.php';

class DAO_Municipios extends \erpsoftsas\DAOGeneral {
    
    protected $_mun_Id ;
    protected $_mun_IdDepartamento ;
    protected $_mun_Codigo ;
    protected $_mun_Nombre ;    
    
    protected $_tabla = 'conf_municipios';
    protected $_primario = 'mun_Id';
    
    protected $_mapa = array(
        'mun_Id' => array('tipodato' => 'integer'),
        'mun_IdDepartamento' => array('tipodato' => 'integer'),
        'mun_Codigo' => array('tipodato' => 'integer'),
        'mun_Nombre' => array('tipodato' => 'varchar'),
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_mun_Id() {
        return $this->_mun_Id;
    }

    function get_mun_IdDepartamento() {
        return $this->_mun_IdDepartamento;
    }

    function get_mun_Codigo() {
        return $this->_mun_Codigo;
    }    

    function get_mun_Nombre() {
        return $this->_mun_Nombre;
    }

    function set_mun_Id($_mun_Id) {
        $this->_mun_Id = $_mun_Id;
    }

    function set_mun_IdDepartamento($_mun_IdDepartamento) {
        $this->_mun_IdDepartamento = $_mun_IdDepartamento;
    }

    function set_mun_Codigo($_mun_Codigo) {
        $this->_mun_Codigo = $_mun_Codigo;
    }

    function set_mun_Nombre($_mun_Nombre) {
        $this->_mun_Nombre = $_mun_Nombre;
    }

}
