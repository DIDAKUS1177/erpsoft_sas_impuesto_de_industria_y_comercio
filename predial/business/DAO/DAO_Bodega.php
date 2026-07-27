<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_Bodega extends \predial\DAOGeneral {
    
    protected $_bod_Id ;
    protected $_bod_Nombre ;
    protected $_bod_IdTipo ;
    protected $_bod_Estado ;    
    
    protected $_tabla = 'inv_bodega';
    protected $_primario = 'bod_Id';
    
    protected $_mapa = array(
        'bod_Id' => array('tipodato' => 'integer'),
        'bod_Nombre' => array('tipodato' => 'varchar'),
        'bod_IdTipo' => array('tipodato' => 'integer'),
        'bod_Estado' => array('tipodato' => 'integer'),
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_bod_Id() {
        return $this->_bod_Id;
    }

    function get_bod_Nombre() {
        return $this->_bod_Nombre;
    }

    function get_bod_IdTipo() {
        return $this->_bod_IdTipo;
    }    

    function get_bod_Estado() {
        return $this->_bod_Estado;
    }

    function set_bod_Id($_bod_Id) {
        $this->_bod_Id = $_bod_Id;
    }

    function set_bod_Nombre($_bod_Nombre) {
        $this->_bod_Nombre = $_bod_Nombre;
    }

    function set_bod_IdTipo($_bod_IdTipo) {
        $this->_bod_IdTipo = $_bod_IdTipo;
    }

    function set_bod_Estado($_bod_Estado) {
        $this->_bod_Estado = $_bod_Estado;
    }

}
