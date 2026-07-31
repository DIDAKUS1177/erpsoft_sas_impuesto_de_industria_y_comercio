<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_Impuestos extends \predial\DAOGeneral {
    
    protected $_imp_Id ;
    protected $_imp_Descripcion ;
    protected $_imp_Porcentaje ;
    protected $_imp_Estado ;
    protected $_imp_FechaCreacion ;
    
    protected $_tabla = 'fac_impuestos';
    protected $_primario = 'imp_Id';
    
    protected $_mapa = array(
        'imp_Id' => array('tipodato' => 'integer'),
        'imp_Descripcion' => array('tipodato' => 'varchar'),
        'imp_Porcentaje' => array('tipodato' => 'integer'),
        'imp_Estado' => array('tipodato' => 'integer'),
        'imp_FechaCreacion' => array('tipodato' => 'varchar')
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_imp_Id() {
        return $this->_imp_Id;
    }

    function get_imp_Descripcion() {
        return $this->_imp_Descripcion;
    }

    function get_imp_Porcentaje() {
        return $this->_imp_Porcentaje;
    }

    function get_imp_Estado() {
        return $this->_imp_Estado;
    }

    function get_imp_FechaCreacion() {
        return $this->_imp_FechaCreacion;
    }
    

    function set_imp_Id($_imp_Id) {
        $this->_imp_Id = $_imp_Id;
    }

    function set_imp_Descripcion($_imp_Descripcion) {
        $this->_imp_Descripcion = $_imp_Descripcion;
    }

    function set_imp_Porcentaje($_imp_Porcentaje) {
        $this->_imp_Porcentaje = $_imp_Porcentaje;
    }

    function set_imp_Estado($_imp_Estado) {
        $this->_imp_Estado = $_imp_Estado;
    }

    function set_imp_FechaCreacion($_imp_FechaCreacion) {
        $this->_imp_FechaCreacion = $_imp_FechaCreacion;
    }

}
