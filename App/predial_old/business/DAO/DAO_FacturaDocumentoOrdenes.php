<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_FacturaDocumentoOrdenes extends \predial\DAOGeneral {
    
    protected $_doc_Id ;
    protected $_doc_Numero ;    
    protected $_doc_IdSede ;
    protected $_doc_IdMesa ;
    protected $_doc_IdVendedor ;
    protected $_doc_Observaciones ;
    protected $_doc_ValorNeto ;  
    protected $_doc_Fecha ;
    protected $_doc_MotivoAnulacion ;
    protected $_doc_IdFactura ;
    
    protected $_strNombreMesaCuenta ;
    
    protected $_doc_Estado ;

    protected $_tabla = 'fac_documento_ordenes';
    protected $_primario = 'doc_Id';
    
    protected $_mapa = array(
        'doc_Id' => array('tipodato' => 'integer'),
        'doc_Numero' => array('tipodato' => 'integer'),
        'doc_IdSede' => array('tipodato' => 'integer'),
        'doc_IdMesa' => array('tipodato' => 'integer'),
        'doc_IdVendedor' => array('tipodato' => 'integer'),
        'doc_Observaciones' => array('tipodato' => 'varchar'),
        'doc_ValorNeto' => array('tipodato' => 'varchar'),
        'doc_Fecha' => array('tipodato' => 'varchar-like'),
        'doc_MotivoAnulacion' => array('tipodato' => 'varchar'),
        'doc_IdFactura' => array('tipodato' => 'varchar'),
        'doc_Estado' => array('tipodato' => 'integer'),
        'strNombreMesaCuenta' => array('tipodato' => 'varchar','sql' => '(select cu.seemma_Nombre from conf_sedes_empresa_mesas as cu WHERE cu.seemma_Id = fac_documento_ordenes.doc_IdMesa)'),
    );   
    
    public function __construct() {
        parent::__construct();
    }
    function get_doc_Id() {
        return $this->_doc_Id;
    }

    function get_doc_Numero() {
        return $this->_doc_Numero;
    }

    function get_doc_IdSede() {
        return $this->_doc_IdSede;
    }

    function get_doc_IdMesa() {
        return $this->_doc_IdMesa;
    }

    function get_doc_IdVendedor() {
        return $this->_doc_IdVendedor;
    }

    function get_doc_Observaciones() {
        return $this->_doc_Observaciones;
    }

    function get_doc_ValorNeto() {
        return $this->_doc_ValorNeto;
    }

    function get_doc_Fecha() {
        return $this->_doc_Fecha;
    }

    function get_doc_MotivoAnulacion() {
        return $this->_doc_MotivoAnulacion;
    }

    function get_doc_IdFactura() {
        return $this->_doc_IdFactura;
    }
        
    function get_doc_Estado() {
        return $this->_doc_Estado;
    }

    function get_strNombreMesaCuenta() {
        return $this->_strNombreMesaCuenta;
    }
        
    function set_doc_Id($_doc_Id) {
        $this->_doc_Id = $_doc_Id;
    }

    function set_doc_Numero($_doc_Numero) {
        $this->_doc_Numero = $_doc_Numero;
    }
    
    function set_doc_IdSede($_doc_IdSede) {
        $this->_doc_IdSede = $_doc_IdSede;
    }
    
    function set_doc_IdMesa($_doc_IdMesa) {
        $this->_doc_IdMesa = $_doc_IdMesa;
    }

    function set_doc_IdVendedor($_doc_IdVendedor) {
        $this->_doc_IdVendedor = $_doc_IdVendedor;
    }

    function set_doc_Observaciones($_doc_Observaciones) {
        $this->_doc_Observaciones = $_doc_Observaciones;
    }

    function set_doc_ValorNeto($_doc_ValorNeto) {
        $this->_doc_ValorNeto = $_doc_ValorNeto;
    }

    function set_doc_Fecha($_doc_Fecha) {
        $this->_doc_Fecha = $_doc_Fecha;
    }

    function set_doc_MotivoAnulacion($_doc_MotivoAnulacion) {
        $this->_doc_MotivoAnulacion = $_doc_MotivoAnulacion;
    }

    function set_doc_IdFactura($_doc_IdFactura) {
        $this->_doc_IdFactura = $_doc_IdFactura;
    }
    
    function set_doc_Estado($_doc_Estado) {
        $this->_doc_Estado = $_doc_Estado;
    }

    function set_strNombreMesaCuenta($_strNombreMesaCuenta) {
        $this->_strNombreMesaCuenta = $_strNombreMesaCuenta;
    }
    
}
