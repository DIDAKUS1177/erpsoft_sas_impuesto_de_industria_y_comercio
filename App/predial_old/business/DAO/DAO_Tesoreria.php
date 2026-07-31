<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_Tesoreria extends \predial\DAOGeneral {
    
    protected $_teso_Id ;
    protected $_teso_IdDocumento ;
    protected $_teso_IdCaja ;
    protected $_teso_Pocision ;
    protected $_teso_Importe ;
    protected $_teso_IdFormaPago ;
    protected $_teso_Cierre ;
    protected $_teso_IdCierre ;
    protected $_teso_EstadoPago ;
    protected $_teso_FechaCreacion ;

    protected $_strNombreCliente;
    protected $_strNumeroPrefijo;
    protected $_strValorTotalAbonos;   
    protected $_strVendedor;
    protected $_strEstadoFactura;
            
    protected $_tabla = 'fac_tesoreria';
    protected $_primario = 'teso_Id';
    
    protected $_mapa = array(
        'teso_Id' => array('tipodato' => 'integer'),
        'teso_IdDocumento' => array('tipodato' => 'integer'),
        'teso_IdCaja' => array('tipodato' => 'integer'),
        'teso_Pocision' => array('tipodato' => 'integer'),
        'teso_Importe' => array('tipodato' => 'varchar'),
        'teso_IdFormaPago' => array('tipodato' => 'integer'),
        'teso_Cierre' => array('tipodato' => 'integer'),
        'teso_IdCierre' => array('tipodato' => 'varchar'),
        'teso_EstadoPago' => array('tipodato' => 'integer'),
        'teso_FechaCreacion' => array('tipodato' => 'varchar'),
        'strNombreCliente' => array('tipodato' => 'varchar','sql' => '(select cse.cli_RazonSocial from fac_cliente as cse INNER JOIN fac_documento as doc ON doc.doc_IdCliente=cse.cli_Id where doc.doc_Id  = fac_tesoreria.teso_IdDocumento)'),
        'strNumeroPrefijo' => array('tipodato' => 'varchar','sql' => '(select CONCAT(doc.doc_Prefijo, "-", doc.doc_Numero) from fac_documento as doc where doc.doc_Id  = fac_tesoreria.teso_IdDocumento)'),
        'strValorTotalAbonos' => array('tipodato' => 'varchar','sql' => '(select  SUM(cse.cuco_Valor) from fac_cuentas_por_cobrar as cse where cse.cuco_IdDocumento  = fac_tesoreria.teso_IdDocumento)'),
        'strVendedor' => array('tipodato' => 'varchar','sql' => '(select usu.usu_Id from conf_usuario as usu INNER JOIN fac_documento as doc ON doc.doc_IdVendedor = usu.usu_Id where doc.doc_Id = fac_tesoreria.teso_IdDocumento)'),
        'strEstadoFactura' => array('tipodato' => 'varchar','sql' => '(select doc.doc_Estado from fac_documento as doc where doc.doc_Id  = fac_tesoreria.teso_IdDocumento)')
    );   
    
    public function __construct() {
        parent::__construct();
    }
    function get_teso_Id() {
        return $this->_teso_Id;
    }

    function get_teso_IdDocumento() {
        return $this->_teso_IdDocumento;
    }

    function get_teso_IdCaja() {
        return $this->_teso_IdCaja;
    }

    function get_teso_Pocision() {
        return $this->_teso_Pocision;
    }

    function get_teso_Importe() {
        return $this->_teso_Importe;
    }

    function get_teso_IdFormaPago() {
        return $this->_teso_IdFormaPago;
    }

    function get_teso_Cierre() {
        return $this->_teso_Cierre;
    }

    function get_teso_IdCierre() {
        return $this->_teso_IdCierre;
    }

    function get_teso_EstadoPago() {
        return $this->_teso_EstadoPago;
    }
    
    function get_teso_FechaCreacion() {
        return $this->_teso_FechaCreacion;
    }

    function get_strNombreCliente() {
        return $this->_strNombreCliente;
    }   

    function get_strNumeroPrefijo() {
        return $this->_strNumeroPrefijo;
    }   

    function get_strValorTotalAbonos() {
        return $this->_strValorTotalAbonos;
    }   

    function get_strVendedor() {
        return $this->_strVendedor;
    }   

    function get_strEstadoFactura() {
        return $this->_strEstadoFactura;
    }   
                
    function set_teso_Id($_teso_Id) {
        $this->_teso_Id = $_teso_Id;
    }

    function set_teso_IdDocumento($_teso_IdDocumento) {
        $this->_teso_IdDocumento = $_teso_IdDocumento;
    }

    function set_teso_IdCaja($_teso_IdCaja) {
        $this->_teso_IdCaja = $_teso_IdCaja;
    }
    
    function set_doc_IdCliente($_doc_IdCliente) {
        $this->_doc_IdCliente = $_doc_IdCliente;
    }

    function set_teso_Pocision($_teso_Pocision) {
        $this->_teso_Pocision = $_teso_Pocision;
    }

    function set_teso_Importe($_teso_Importe) {
        $this->_teso_Importe = $_teso_Importe;
    }

    function set_teso_IdFormaPago($_teso_IdFormaPago) {
        $this->_teso_IdFormaPago = $_teso_IdFormaPago;
    }

    function set_teso_Cierre($_teso_Cierre) {
        $this->_teso_Cierre = $_teso_Cierre;
    }

    function set_teso_IdCierre($_teso_IdCierre) {
        $this->_teso_IdCierre = $_teso_IdCierre;
    }

    function set_teso_EstadoPago($_teso_EstadoPago) {
        $this->_teso_EstadoPago = $_teso_EstadoPago;
    }
    
    function set_teso_FechaCreacion($_teso_FechaCreacion) {
        $this->_teso_FechaCreacion = $_teso_FechaCreacion;
    }

    function set_strNombreCliente($_strNombreCliente) {
        $this->_strNombreCliente = $_strNombreCliente;
    }

    function set_strNumeroPrefijo($_strNumeroPrefijo) {
        $this->_strNumeroPrefijo = $_strNumeroPrefijo;
    }

    function set_strValorTotalAbonos($_strValorTotalAbonos) {
        $this->_strValorTotalAbonos = $_strValorTotalAbonos;
    }

    function set_strVendedor($_strVendedor) {
        $this->_strVendedor = $_strVendedor;
    }

    function set_strEstadoFactura($_strEstadoFactura) {
        $this->_strEstadoFactura = $_strEstadoFactura;
    }   
    
}
