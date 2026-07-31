<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_FacturaDetalleDocumento extends \predial\DAOGeneral {
    
    protected $_detDoc_Id ;
    protected $_detDoc_IdDocumento ;
    protected $_detDoc_IdProducto ;
    protected $_detDoc_Cantidad ;
    protected $_detDoc_ValorUnitario ;
    protected $_detDoc_ValorTotal ;
    protected $_detDoc_ValorImpuesto ;

    //protected $_strNombreProducto;
    
    protected $_tabla = 'fac_detalle_documento';
    protected $_primario = 'detDoc_Id';
    
    protected $_mapa = array(
        'detDoc_Id' => array('tipodato' => 'integer'),
        'detDoc_IdDocumento' => array('tipodato' => 'integer'),
        'detDoc_IdProducto' => array('tipodato' => 'integer'),
        'detDoc_Cantidad' => array('tipodato' => 'varchar'),
        'detDoc_ValorUnitario' => array('tipodato' => 'varchar'),
        'detDoc_ValorTotal' => array('tipodato' => 'varchar'),
        'detDoc_ValorImpuesto' => array('tipodato' => 'varchar'),
    );   
    
    public function __construct() {
        parent::__construct();
    }
    function get_detDoc_Id() {
        return $this->_detDoc_Id;
    }

    function get_detDoc_IdDocumento() {
        return $this->_detDoc_IdDocumento;
    }

    function get_detDoc_IdProducto() {
        return $this->_detDoc_IdProducto;
    }

    function get_detDoc_Cantidad() {
        return $this->_detDoc_Cantidad;
    }

    function get_detDoc_ValorUnitario() {
        return $this->_detDoc_ValorUnitario;
    }

    function get_detDoc_ValorTotal() {
        return $this->_detDoc_ValorTotal;
    }

    function get_detDoc_ValorImpuesto() {
        return $this->_detDoc_ValorImpuesto;
    }

    function set_detDoc_Id($_detDoc_Id) {
        $this->_detDoc_Id = $_detDoc_Id;
    }

    function set_detDoc_IdDocumento($_detDoc_IdDocumento) {
        $this->_detDoc_IdDocumento = $_detDoc_IdDocumento;
    }

    function set_detDoc_IdProducto($_detDoc_IdProducto) {
        $this->_detDoc_IdProducto = $_detDoc_IdProducto;
    }
    
    function set_detDoc_Cantidad($_detDoc_Cantidad) {
        $this->_detDoc_Cantidad = $_detDoc_Cantidad;
    }

    function set_detDoc_ValorUnitario($_detDoc_ValorUnitario) {
        $this->_detDoc_ValorUnitario = $_detDoc_ValorUnitario;
    }

    function set_detDoc_ValorTotal($_detDoc_ValorTotal) {
        $this->_detDoc_ValorTotal = $_detDoc_ValorTotal;
    }

    function set_detDoc_ValorImpuesto($_detDoc_ValorImpuesto) {
        $this->_detDoc_ValorImpuesto = $_detDoc_ValorImpuesto;
    }
    
}
