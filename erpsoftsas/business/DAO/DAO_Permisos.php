<?php
namespace erpsoftsas;
include_once 'class.DAO.php';

class DAO_Permisos extends \erpsoftsas\DAOGeneral {
    
    protected $_per_Id ;
    protected $_per_IdRol ;
    protected $_per_IdModulo ;
    protected $_per_IdSubModulo ;
    protected $_per_Estado ;
    protected $_per_IdBoton ;
    
    protected $_tabla = 'conf_permisos';
    protected $_primario = 'per_Id';
    
    protected $_mapa = array(
        'per_Id' => array('tipodato' => 'integer'),
        'per_IdRol' => array('tipodato' => 'integer'),
        'per_IdModulo' => array('tipodato' => 'integer'),
        'per_IdSubModulo' => array('tipodato' => 'integer'),
        'per_Estado' => array('tipodato' => 'bit'),
        'per_IdBoton' => array('tipodato' => 'integer')
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_per_Id() {
        return $this->_per_Id;
    }

    function get_per_IdRol() {
        return $this->_per_IdRol;
    }

    function get_per_IdModulo() {
        return $this->_per_IdModulo;
    }
    
    function get_per_IdSubModulo() {
        return $this->_per_IdSubModulo;
    }
    
    function get_per_Estado() {
        return $this->_per_Estado;
    }
    
    function get_per_IdBoton() {
        return $this->_per_IdBoton;
    }
    
    function set_per_Id($_per_Id) {
        $this->_per_Id = $_per_Id;
    }

    function set_per_IdRol($_per_IdRol) {
        $this->_per_IdRol = $_per_IdRol;
    }   
    
    function set_per_IdModulo($_per_IdModulo) {
        $this->_per_IdModulo = $_per_IdModulo;
    }
    
    function set_per_IdSubModulo($_per_IdSubModulo) {
        $this->_per_IdSubModulo = $_per_IdSubModulo;
    }
    
    function set_per_Estado($_per_Estado) {
        $this->_per_Estado = $_per_Estado;
    }    
    
    function set_per_IdBoton($_per_IdBoton) {
        $this->_per_IdBoton = $_per_IdBoton;
    }    
}
