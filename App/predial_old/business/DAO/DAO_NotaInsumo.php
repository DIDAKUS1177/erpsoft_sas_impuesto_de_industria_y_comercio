<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_NotaInsumo extends \predial\DAOGeneral {
    
    protected $_kar_Id ;
    protected $_kar_Tipo ;
    protected $_kar_Observaciones ;
    protected $_Kar_Estado ;
    protected $_kar_Fecha ;

    protected $_strNombreProducto;
    
    
    protected $_tabla = 'prod_kardex';
    protected $_primario = 'kar_Id';
    
    protected $_mapa = array(
        'kar_Id' => array('tipodato' => 'integer'),
        'kar_Tipo' => array('tipodato' => 'integer'),
        'kar_Observaciones' => array('tipodato' => 'varchar'),
        'Kar_Estado' => array('tipodato' => 'integer'),
        'kar_Fecha' => array('tipodato' => 'varchar'),
        
    );   
    
    public function __construct() {
        parent::__construct();
    }
    function get_kar_Id() {
        return $this->_kar_Id;
    }

    function get_kar_Tipo() {
        return $this->_kar_Tipo;
    }

    function get_kar_Observaciones() {
        return $this->_kar_Observaciones;
    }

    function get_Kar_Estado() {
        return $this->_Kar_Estado;
    }

    function get_kar_Fecha() {
        return $this->_kar_Fecha;
    }

    

    function set_kar_Id($_kar_Id) {
        $this->_kar_Id = $_kar_Id;
    }

    function set_kar_Tipo($_kar_Tipo) {
        $this->_kar_Tipo = $_kar_Tipo;
    }

    function set_kar_Observaciones($_kar_Observaciones) {
        $this->_kar_Observaciones = $_kar_Observaciones;
    }
    
    function set_Kar_Estado($_Kar_Estado) {
        $this->_Kar_Estado = $_Kar_Estado;
    }

    function set_kar_Fecha($_kar_Fecha) {
        $this->_kar_Fecha = $_kar_Fecha;
    }

}
