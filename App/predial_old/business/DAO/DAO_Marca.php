<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_Marca extends \predial\DAOGeneral {
    
    protected $_mar_Id ;
    protected $_mar_Descripcion ;
    protected $_mar_Estado ;
    protected $_mar_FechaCreacion ;
    
    protected $_tabla = 'inv_marca';
    protected $_primario = 'mar_Id';
    
    protected $_mapa = array(
        'mar_Id' => array('tipodato' => 'integer'),
        'mar_Descripcion' => array('tipodato' => 'varchar'),
        'mar_Estado' => array('tipodato' => 'integer'),
        'mar_FechaCreacion' => array('tipodato' => 'varchar'),
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_mar_Id() {
        return $this->_mar_Id;
    }

    function get_mar_Descripcion() {
        return $this->_mar_Descripcion;
    }

    function get_mar_Estado() {
        return $this->_mar_Estado;
    }

    function get_mar_FechaCreacion() {
        return $this->_mar_FechaCreacion;
    }

    function set_mar_Id($_mar_Id) {
        $this->_mar_Id = $_mar_Id;
    }

    function set_mar_Descripcion($_mar_Descripcion) {
        $this->_mar_Descripcion = $_mar_Descripcion;
    }

    function set_mar_Estado($_mar_Estado) {
        $this->_mar_Estado = $_mar_Estado;
    }

    function set_mar_FechaCreacion($_mar_FechaCreacion) {
        $this->_mar_FechaCreacion = $_mar_FechaCreacion;
    }
}
