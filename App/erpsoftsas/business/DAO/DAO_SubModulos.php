<?php
namespace erpsoftsas;
include_once 'class.DAO.php';

class DAO_SubModulos extends \erpsoftsas\DAOGeneral {
    
    protected $_subMod_Id ;
    protected $_subMod_Nombre ;
    protected $_subMod_Descripcion ;
    protected $_subMod_IdModulo ;
    
    protected $_tabla = 'conf_submodulo';
    protected $_primario = 'subMod_Id';
    
    protected $_mapa = array(
        'subMod_Id' => array('tipodato' => 'integer'),
        'subMod_Nombre' => array('tipodato' => 'varchar'),
        'subMod_Descripcion' => array('tipodato' => 'varchar'),
        'subMod_IdModulo' => array('tipodato' => 'integer')
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_subMod_Id() {
        return $this->_subMod_Id;
    }

    function get_subMod_Nombre() {
        return $this->_subMod_Nombre;
    }

    function get_subMod_Descripcion() {
        return $this->_subMod_Descripcion;
    }
    
    function get_subMod_IdModulo() {
        return $this->_subMod_IdModulo;
    }
    
    function set_subMod_Id($_subMod_Id) {
        $this->_subMod_Id = $_subMod_Id;
    }

    function set_subMod_Nombre($_subMod_Nombre) {
        $this->_subMod_Nombre = $_subMod_Nombre;
    }   
    
    function set_subMod_Descripcion($_subMod_Descripcion) {
        $this->_subMod_Descripcion = $_subMod_Descripcion;
    }
    
    function set_subMod_IdModulo($_subMod_IdModulo) {
        $this->_subMod_IdModulo = $_subMod_IdModulo;
    }    
}
