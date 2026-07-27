<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_Modulos extends \predial\DAOGeneral {
    
    protected $_mod_Id ;
    protected $_mod_Nombre ;
    protected $_mod_Descripcion;
    protected $_mod_Url ;
    protected $_mod_Icono ;
    protected $_mod_Estado ;
    
    protected $_tabla = 'conf_modulo';
    protected $_primario = '_mod_Id';
    
    protected $_mapa = array(
        'mod_Id' => array('tipodato' => 'integer'),
        'mod_Nombre' => array('tipodato' => 'varchar'),
        'mod_Url' => array('tipodato' => 'varchar'),
        'mod_Descripcion' => array('tipodato' => 'varchar'),
        'mod_Icono' => array('tipodato' => 'varchar'),
        'mod_Estado' => array('tipodato' => 'bit')
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_mod_Id() {
        return $this->_mod_Id;
    }

    function get_mod_Nombre() {
        return $this->_mod_Nombre;
    }

    function get_mod_Url() {
        return $this->_mod_Url;
    }
    
    function get_mod_Descripcion() {
        return $this->_mod_Descripcion;
    }
    
    function get_mod_Icono() {
        return $this->_mod_Icono;
    }
    
    function get_mod_Estado() {
        return $this->_mod_Estado;
    }
    
    function set_mod_Id($_mod_Id) {
        $this->_mod_Id = $_mod_Id;
    }

    function set_mod_Nombre($_mod_Nombre) {
        $this->_mod_Nombre = $_mod_Nombre;
    }   
    
    function set_mod_Url($_mod_Url) {
        $this->_mod_Url = $_mod_Url;
    }
    
    function set_mod_Descripcion($_mod_Descripcion) {
        $this->_mod_Descripcion = $_mod_Descripcion;
    }
    
    function set_mod_Icono($_mod_Icono) {
        $this->_mod_Icono = $_mod_Icono;
    }
    
    function set_mod_Estado($_mod_Estado) {
        $this->_mod_Estado = $_mod_Estado;
    }    
    
}
