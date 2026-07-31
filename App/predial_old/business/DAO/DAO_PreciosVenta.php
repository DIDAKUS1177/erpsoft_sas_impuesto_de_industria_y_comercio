<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_PreciosVenta extends \predial\DAOGeneral {
    
    protected $_preVen_Id ;
    protected $_preVen_IdTarifa ;
    protected $_preVen_IdProducto ;
    protected $_preVen_PrecioNeto ;
    protected $_preVen_FechaCreacion ;
    protected $_preVen_FechaModificado ;
    protected $_preVen_Estado ;

    protected $_strIdImpuesto;
    
    protected $_tabla = 'fac_precios_venta';
    protected $_primario = 'preVen_Id';
    
    protected $_mapa = array(
        'preVen_Id' => array('tipodato' => 'integer'),
        'preVen_IdTarifa' => array('tipodato' => 'integer'),
        'preVen_IdProducto' => array('tipodato' => 'integer'),
        'preVen_PrecioNeto' => array('tipodato' => 'varchar'),
        'preVen_FechaModificado' => array('tipodato' => 'varchar'),
        'preVen_FechaCreacion' => array('tipodato' => 'varchar'),
        'preVen_Estado' => array('tipodato' => 'integer'),
        'strIdImpuesto' => array('tipodato' => 'integer','sql' => '(select fi.imp_Porcentaje from inv_producto as rol INNER JOIN fac_impuestos as fi ON rol.pro_IdImpuesto = fi.imp_Id where rol.pro_Id = fac_precios_venta.preVen_IdProducto)'),
    );   
    
    public function __construct() {
        parent::__construct();
    }

    function get_preVen_Id() {
        return $this->_preVen_Id;
    }

    function get_preVen_IdTarifa() {
        return $this->_preVen_IdTarifa;
    }

    function get_preVen_IdProducto() {
        return $this->_preVen_IdProducto;
    }

    function get_preVen_PrecioNeto() {
        return $this->_preVen_PrecioNeto;
    }

    function get_preVen_FechaModificado() {
        return $this->_preVen_FechaModificado;
    }

    function get_preVen_FechaCreacion() {
        return $this->_preVen_FechaCreacion;
    }

    function get_preVen_Estado() {
        return $this->_preVen_Estado;
    }

    function get_strIdImpuesto() {
        return $this->_strIdImpuesto;
    }
    
    function set_preVen_Id($_preVen_Id) {
        $this->_preVen_Id = $_preVen_Id;
    }

    function set_preVen_IdTarifa($_preVen_IdTarifa) {
        $this->_preVen_IdTarifa = $_preVen_IdTarifa;
    }

    function set_preVen_IdProducto($_preVen_IdProducto) {
        $this->_preVen_IdProducto = $_preVen_IdProducto;
    }
    
    function set_preVen_PrecioNeto($_preVen_PrecioNeto) {
        $this->_preVen_PrecioNeto = $_preVen_PrecioNeto;
    }

    function set_preVen_FechaModificado($_preVen_FechaModificado) {
        $this->_preVen_FechaModificado = $_preVen_FechaModificado;
    }

    function set_preVen_FechaCreacion($_preVen_FechaCreacion) {
        $this->_preVen_FechaCreacion = $_preVen_FechaCreacion;
    }

    function set_preVen_Estado($_preVen_Estado) {
        $this->_preVen_Estado = $_preVen_Estado;
    }

    function set_strIdImpuesto($_strIdImpuesto) {
        $this->_strIdImpuesto = $_strIdImpuesto;
    }    
}
