<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_Categoria extends \predial\DAOGeneral {
    
    protected $_cate_Id ;
    protected $_cate_Descripcion ;
    protected $_cate_IdTipo ;
    protected $_cate_Estado ;
    protected $_cate_FechaCreacion ;
    
    protected $_tabla = 'inv_categoria';
    protected $_primario = 'cate_Id';
    
    protected $_mapa = array(
        'cate_Id' => array('tipodato' => 'integer'),
        'cate_Descripcion' => array('tipodato' => 'varchar'),
        'cate_IdTipo' => array('tipodato' => 'integer'),
        'cate_Estado' => array('tipodato' => 'integer'),
        'cate_FechaCreacion' => array('tipodato' => 'varchar'),
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_cate_Id() {
        return $this->_cate_Id;
    }

    function get_cate_Descripcion() {
        return $this->_cate_Descripcion;
    }

    function get_cate_IdTipo() {
        return $this->_cate_IdTipo;
    }

    function get_cate_Estado() {
        return $this->_cate_Estado;
    }

    function get_cate_FechaCreacion() {
        return $this->_cate_FechaCreacion;
    }

    function set_cate_Id($_cate_Id) {
        $this->_cate_Id = $_cate_Id;
    }

    function set_cate_Descripcion($_cate_Descripcion) {
        $this->_cate_Descripcion = $_cate_Descripcion;
    }

    function set_cate_IdTipo($_cate_IdTipo) {
        $this->_cate_IdTipo = $_cate_IdTipo;
    }

    function set_cate_Estado($_cate_Estado) {
        $this->_cate_Estado = $_cate_Estado;
    }

    function set_cate_FechaCreacion($_cate_FechaCreacion) {
        $this->_cate_FechaCreacion = $_cate_FechaCreacion;
    }

}
