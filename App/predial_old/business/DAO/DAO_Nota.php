<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_Nota extends \predial\DAOGeneral {
    
    protected $_kar_Id ;
    protected $_kar_Tipo ;
    protected $_kar_NumOrden ;
    protected $_kar_IdProveedor ;
    protected $_kar_TipoPago ;
    protected $_kar_Observaciones ;
    protected $_kar_EstadoPago ;
    protected $_Kar_Estado ;
    protected $_kar_Fecha ;

    protected $_strNombreProveedor;
    protected $_strValorTotalCuenta;
    protected $_strValorTotalAbonos;
        
    protected $_tabla = 'inv_kardex';
    protected $_primario = 'kar_Id';
    
    protected $_mapa = array(
        'kar_Id' => array('tipodato' => 'integer'),
        'kar_Tipo' => array('tipodato' => 'integer'),
        'kar_NumOrden' => array('tipodato' => 'varchar'),
        'kar_IdProveedor' => array('tipodato' => 'varchar'),
        'kar_TipoPago' => array('tipodato' => 'varchar'),
        'kar_Observaciones' => array('tipodato' => 'varchar'),
        'kar_EstadoPago' => array('tipodato' => 'integer'),
        'Kar_Estado' => array('tipodato' => 'integer'),
        'kar_Fecha' => array('tipodato' => 'varchar-like'),
        'strNombreProveedor' => array('tipodato' => 'varchar','sql' => '(select cse.prov_Nombre from inv_proveedores as cse where cse.prov_Id = inv_kardex.kar_IdProveedor)'),
        'strValorTotalCuenta' => array('tipodato' => 'varchar','sql' => '(select  SUM(cse.detkar_ValorEntrada) from inv_detalle_kardex as cse where cse.detkar_IdKardex  = inv_kardex.kar_Id)'),
        'strValorTotalAbonos' => array('tipodato' => 'varchar','sql' => '(select  SUM(cse.cupa_Valor) from fac_cuentas_por_pagar as cse where cse.cupa_IdNota  = inv_kardex.kar_Id)'),
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

    function get_kar_NumOrden() {
        return $this->_kar_NumOrden;
    }
    
    function get_kar_IdProveedor() {
        return $this->_kar_IdProveedor;
    }

    function get_kar_TipoPago() {
        return $this->_kar_TipoPago;
    }

    function get_kar_Observaciones() {
        return $this->_kar_Observaciones;
    }

    function get_kar_EstadoPago() {
        return $this->_kar_EstadoPago;
    }

    function get_Kar_Estado() {
        return $this->_Kar_Estado;
    }

    function get_kar_Fecha() {
        return $this->_kar_Fecha;
    }
    
    function get_strNombreProveedor() {
        return $this->_strNombreProveedor;
    }    

    function get_strValorTotalCuenta() {
        return $this->_strValorTotalCuenta;
    }    

    function get_strValorTotalAbonos() {
        return $this->_strValorTotalAbonos;
    }    
    
    function set_kar_Id($_kar_Id) {
        $this->_kar_Id = $_kar_Id;
    }

    function set_kar_Tipo($_kar_Tipo) {
        $this->_kar_Tipo = $_kar_Tipo;
    }
    
    function set_kar_NumOrden($_kar_NumOrden) {
        $this->_kar_NumOrden = $_kar_NumOrden;
    }  

    function set_kar_IdProveedor($_kar_IdProveedor) {
        $this->_kar_IdProveedor = $_kar_IdProveedor;
    }
    
    function set_kar_TipoPago($_kar_TipoPago) {
        $this->_kar_TipoPago = $_kar_TipoPago;
    }

    function set_kar_Observaciones($_kar_Observaciones) {
        $this->_kar_Observaciones = $_kar_Observaciones;
    }
    
    function set_kar_EstadoPago($_kar_EstadoPago) {
        $this->_kar_EstadoPago = $_kar_EstadoPago;
    }

    function set_Kar_Estado($_Kar_Estado) {
        $this->_Kar_Estado = $_Kar_Estado;
    }

    function set_kar_Fecha($_kar_Fecha) {
        $this->_kar_Fecha = $_kar_Fecha;
    }

    function set_strNombreProveedor($_strNombreProveedor) {
        $this->_strNombreProveedor = $_strNombreProveedor;
    }

    function set_strValorTotalCuenta($_strValorTotalCuenta) {
        $this->_strValorTotalCuenta = $_strValorTotalCuenta;
    }
    
    function set_strValorTotalAbonos($_strValorTotalAbonos) {
        $this->_strValorTotalAbonos = $_strValorTotalAbonos;
    }
    
}
