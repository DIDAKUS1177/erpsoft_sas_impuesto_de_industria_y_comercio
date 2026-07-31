<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_ProductoComposicion extends \predial\DAOGeneral {
    
    protected $_proco_Id ;
    protected $_proco_IdProducto ;
    protected $_proco_IdInsumo ;
    protected $_proco_Cantidad ;
    protected $_proco_Estado ;
    protected $_proco_FechaCreacion ;

    protected $_tabla = 'prod_producto_composicion';
    protected $_primario = 'proco_Id';
    
    protected $_mapa = array(
        'proco_Id' => array('tipodato' => 'integer'),
        'proco_IdProducto' => array('tipodato' => 'integer'),
        'proco_IdInsumo' => array('tipodato' => 'integer'),
        'proco_Cantidad' => array('tipodato' => 'integer'),
        'proco_Estado' => array('tipodato' => 'integer'),
        'proco_FechaCreacion' => array('tipodato' => 'varchar'),
    );
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_proco_Id() {
        return $this->_proco_Id;
    }

    function get_proco_IdProducto() {
        return $this->_proco_IdProducto;
    }

    function get_proco_IdInsumo() {
        return $this->_proco_IdInsumo;
    }    

    function get_proco_Cantidad() {
        return $this->_proco_Cantidad;
    }

    function get_proco_Estado() {
        return $this->_proco_Estado;
    }

    function get_proco_FechaCreacion() {
        return $this->_proco_FechaCreacion;
    }

    function set_proco_Id($_proco_Id) {
        $this->_proco_Id = $_proco_Id;
    }

    function set_proco_IdProducto($_proco_IdProducto) {
        $this->_proco_IdProducto = $_proco_IdProducto;
    }

    function set_proco_IdInsumo($_proco_IdInsumo) {
        $this->_proco_IdInsumo = $_proco_IdInsumo;
    }

    function set_proco_Cantidad($_proco_Cantidad) {
        $this->_proco_Cantidad = $_proco_Cantidad;
    }

    function set_proco_Estado($_proco_Estado) {
        $this->_proco_Estado = $_proco_Estado;
    }

    function set_proco_FechaCreacion($_proco_FechaCreacion) {
        $this->_proco_FechaCreacion = $_proco_FechaCreacion;
    }

}
