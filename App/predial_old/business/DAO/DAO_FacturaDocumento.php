<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_FacturaDocumento extends \predial\DAOGeneral {
    
    protected $_doc_Id ;
    protected $_doc_Prefijo ;
    protected $_doc_Numero ;
    protected $_doc_IdCliente ;
    protected $_doc_IdSerieCaja ;
    protected $_doc_IdVendedor ;
    protected $_doc_IdTipoDocumento ;
    protected $_doc_IdKardex ;
    protected $_doc_Observaciones ;
    protected $_doc_ValorBruto ;
    protected $_doc_ValorImpuestos ;
    protected $_doc_ValorNeto ;  
    protected $_doc_Subtotal ;     
    protected $_doc_Redondeo ;    
    protected $_doc_Descuento ;    
    protected $_doc_campoPersonalizado ;  
    protected $_doc_Fecha ;
    protected $_doc_MotivoAnulacion ;
    protected $_doc_Estado ;

    protected $_strCierreFactura ;
    protected $_strNombreCliente ;

    protected $_tabla = 'fac_documento';
    protected $_primario = 'doc_Id';
    
    protected $_mapa = array(
        'doc_Id' => array('tipodato' => 'integer'),
        'doc_Prefijo' => array('tipodato' => 'varchar'),
        'doc_Numero' => array('tipodato' => 'integer'),
        'doc_IdSerieCaja' => array('tipodato' => 'integer'),
        'doc_IdCliente' => array('tipodato' => 'integer'),
        'doc_IdVendedor' => array('tipodato' => 'integer'),
        'doc_IdTipoDocumento' => array('tipodato' => 'integer'),
        'doc_IdKardex' => array('tipodato' => 'integer'),
        'doc_Observaciones' => array('tipodato' => 'varchar'),
        'doc_ValorBruto' => array('tipodato' => 'varchar'),
        'doc_ValorImpuestos' => array('tipodato' => 'varchar'),
        'doc_ValorNeto' => array('tipodato' => 'varchar'),
        'doc_Subtotal' => array('tipodato' => 'varchar'),
        'doc_Redondeo' => array('tipodato' => 'varchar'),
        'doc_Descuento' => array('tipodato' => 'varchar'),
        'doc_campoPersonalizado' => array('tipodato' => 'varchar'),
        'doc_Fecha' => array('tipodato' => 'varchar-like'),
        'doc_MotivoAnulacion' => array('tipodato' => 'varchar'),
        'doc_Estado' => array('tipodato' => 'integer'),
        'strCierreFactura' => array('tipodato' => 'integer','sql' => '(select ft.teso_Cierre from fac_tesoreria as ft where ft.teso_IdDocumento = fac_documento.doc_Id)'),
        'strNombreCliente' => array('tipodato' => 'varchar','sql' => '(select cl.cli_RazonSocial from fac_cliente as cl where cl.cli_Id = fac_documento.doc_IdCliente)'),
    );   
    
    public function __construct() {
        parent::__construct();
    }
    function get_doc_Id() {
        return $this->_doc_Id;
    }

    function get_doc_Prefijo() {
        return $this->_doc_Prefijo;
    }

    function get_doc_Numero() {
        return $this->_doc_Numero;
    }

    function get_doc_IdSerieCaja() {
        return $this->_doc_IdSerieCaja;
    }
    
    function get_doc_IdCliente() {
        return $this->_doc_IdCliente;
    }

    function get_doc_IdVendedor() {
        return $this->_doc_IdVendedor;
    }

    function get_doc_IdTipoDocumento() {
        return $this->_doc_IdTipoDocumento;
    }

    function get_doc_IdKardex() {
        return $this->_doc_IdKardex;
    }

    function get_doc_Observaciones() {
        return $this->_doc_Observaciones;
    }

    function get_doc_ValorBruto() {
        return $this->_doc_ValorBruto;
    }

    function get_doc_ValorImpuestos() {
        return $this->_doc_ValorImpuestos;
    }

    function get_doc_ValorNeto() {
        return $this->_doc_ValorNeto;
    }

    function get_doc_Subtotal() {
        return $this->_doc_Subtotal;
    }
    
    function get_doc_Redondeo() {
        return $this->_doc_Redondeo;
    }

    function get_doc_Descuento() {
        return $this->_doc_Descuento;
    }

    function get_doc_Fecha() {
        return $this->_doc_Fecha;
    }

    function get_doc_campoPersonalizado() {
        return $this->_doc_campoPersonalizado;
    }

    function get_doc_MotivoAnulacion() {
        return $this->_doc_MotivoAnulacion;
    }
    
    function get_doc_Estado() {
        return $this->_doc_Estado;
    }
    
    function get_strCierreFactura() {
        return $this->_strCierreFactura;
    }

    function get_strNombreCliente() {
        return $this->_strNombreCliente;
    }
    
    
    function set_doc_Id($_doc_Id) {
        $this->_doc_Id = $_doc_Id;
    }

    function set_doc_Prefijo($_doc_Prefijo) {
        $this->_doc_Prefijo = $_doc_Prefijo;
    }

    function set_doc_Numero($_doc_Numero) {
        $this->_doc_Numero = $_doc_Numero;
    }
    
    function set_doc_IdSerieCaja($_doc_IdSerieCaja) {
        $this->_doc_IdSerieCaja = $_doc_IdSerieCaja;
    }
    
    function set_doc_IdCliente($_doc_IdCliente) {
        $this->_doc_IdCliente = $_doc_IdCliente;
    }

    function set_doc_IdVendedor($_doc_IdVendedor) {
        $this->_doc_IdVendedor = $_doc_IdVendedor;
    }

    function set_doc_IdTipoDocumento($_doc_IdTipoDocumento) {
        $this->_doc_IdTipoDocumento = $_doc_IdTipoDocumento;
    }

    function set_doc_IdKardex($_doc_IdKardex) {
        $this->_doc_IdKardex = $_doc_IdKardex;
    }

    function set_doc_Observaciones($_doc_Observaciones) {
        $this->_doc_Observaciones = $_doc_Observaciones;
    }

    function set_doc_ValorBruto($_doc_ValorBruto) {
        $this->_doc_ValorBruto = $_doc_ValorBruto;
    }
    
    function set_doc_ValorImpuestos($_doc_ValorImpuestos) {
        $this->_doc_ValorImpuestos = $_doc_ValorImpuestos;
    }

    function set_doc_ValorNeto($_doc_ValorNeto) {
        $this->_doc_ValorNeto = $_doc_ValorNeto;
    }

    function set_doc_Subtotal($_doc_Subtotal) {
        $this->_doc_Subtotal = $_doc_Subtotal;
    }

    function set_doc_Redondeo($_doc_Redondeo) {
        $this->_doc_Redondeo = $_doc_Redondeo;
    }

    function set_doc_Descuento($_doc_Descuento) {
        $this->_doc_Descuento = $_doc_Descuento;
    }

    function set_doc_campoPersonalizado($_doc_campoPersonalizado) {
        $this->_doc_campoPersonalizado = $_doc_campoPersonalizado;
    }

    function set_doc_Fecha($_doc_Fecha) {
        $this->_doc_Fecha = $_doc_Fecha;
    }

    function set_doc_MotivoAnulacion($_doc_MotivoAnulacion) {
        $this->_doc_MotivoAnulacion = $_doc_MotivoAnulacion;
    }
    
    function set_doc_Estado($_doc_Estado) {
        $this->_doc_Estado = $_doc_Estado;
    }

    function set_strCierreFactura($_strCierreFactura) {
        $this->_strCierreFactura = $_strCierreFactura;
    }
    
    function set_strNombreCliente($_strNombreCliente) {
        $this->_strNombreCliente = $_strNombreCliente;
    }
    
}
