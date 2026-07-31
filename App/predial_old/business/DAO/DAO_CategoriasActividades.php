<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_CategoriasActividades extends \predial\DAOGeneral {
    
    protected $_caa_Id ;
    protected $_caa_Nombre ;
    protected $_caa_Estado ;
    protected $_caa_FechaCreacion ;
    
    protected $_tabla = 'eve_categoriasactividades';
    protected $_primario = 'caa_Id';
    
    protected $_mapa = array(
        'caa_Id' => array('tipodato' => 'integer'),
        'caa_Nombre' => array('tipodato' => 'varchar'),
        'caa_Estado' => array('tipodato' => 'integer'),
        'caa_FechaCreacion' => array('tipodato' => 'varchar')
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_caa_Id() {
        return $this->_caa_Id;
    }

    function get_caa_Nombre() {
        return $this->_caa_Nombre;
    }

    function get_caa_Estado() {
        return $this->_caa_Estado;
    }

    function get_caa_FechaCreacion() {
        return $this->_caa_FechaCreacion;
    }

    function set_caa_Id($_caa_Id) {
        $this->_caa_Id = $_caa_Id;
    }

    function set_caa_Nombre($_caa_Nombre) {
        $this->_caa_Nombre = $_caa_Nombre;
    }

    function set_caa_Estado($_caa_Estado) {
        $this->_caa_Estado = $_caa_Estado;
    }

    function set_caa_FechaCreacion($_caa_FechaCreacion) {
        $this->_caa_FechaCreacion = $_caa_FechaCreacion;
    }
}
