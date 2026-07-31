<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_CierreCaja extends \predial\DAOGeneral {

    protected $_cica_Id ;
    protected $_cica_IdCaja ;
    protected $_cica_IdVendedor ;
    protected $_cica_Fecha ;
    protected $_cica_Hora ;
    protected $_cica_Total ;
    protected $_cica_Descuadre ;
    protected $_cica_Observaciones ;
    
    protected $_strNombreCaja ;
    protected $_strNombreVendedor ;
    
    protected $_tabla = 'fac_cierre_caja';
    protected $_primario = 'cica_Id';
    
    protected $_mapa = array(
        'cica_Id' => array('tipodato' => 'integer'),
        'cica_IdCaja' => array('tipodato' => 'integer'),
        'cica_IdVendedor' => array('tipodato' => 'integer'),
        'cica_Fecha' => array('tipodato' => 'varchar'),
        'cica_Hora' => array('tipodato' => 'varchar'),
        'cica_Total' => array('tipodato' => 'varchar'),
        'cica_Descuadre' => array('tipodato' => 'varchar'),
        'cica_Observaciones' => array('tipodato' => 'varchar'),
        'strNombreCaja' => array('tipodato' => 'integer','sql' => '(select cse.seemca_Nombre from conf_sedes_empresa_cajas as cse where cse.seemca_Id = fac_cierre_caja.cica_IdCaja)'),
        'strNombreVendedor' => array('tipodato' => 'integer','sql' => '(select cu.usu_Nombre from conf_usuario as cu where cu.usu_Id = fac_cierre_caja.cica_IdVendedor)')
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_cica_Id() {
        return $this->_cica_Id;
    }

    function get_cica_IdCaja() {
        return $this->_cica_IdCaja;
    }

    function get_cica_IdVendedor() {
        return $this->_cica_IdVendedor;
    }

    function get_cica_Fecha() {
        return $this->_cica_Fecha;
    }

    function get_cica_Hora() {
        return $this->_cica_Hora;
    }
    
    function get_cica_Total() {
        return $this->_cica_Total;
    }

    function get_cica_Descuadre() {
        return $this->_cica_Descuadre;
    }

    function get_cica_Observaciones() {
        return $this->_cica_Observaciones;
    }

    function get_strNombreCaja() {
        return $this->_strNombreCaja;
    }

    function get_strNombreVendedor() {
        return $this->_strNombreVendedor;
    }


    function set_cica_Id($_cica_Id) {
        $this->_cica_Id = $_cica_Id;
    }

    function set_cica_IdCaja($_cica_IdCaja) {
        $this->_cica_IdCaja = $_cica_IdCaja;
    }

    function set_cica_IdVendedor($_cica_IdVendedor) {
        $this->_cica_IdVendedor = $_cica_IdVendedor;
    }

    function set_cica_Fecha($_cica_Fecha) {
        $this->_cica_Fecha = $_cica_Fecha;
    }

    function set_cica_Hora($_cica_Hora) {
        $this->_cica_Hora = $_cica_Hora;
    }

    function set_cica_Total($_cica_Total) {
        $this->_cica_Total = $_cica_Total;
    }

    function set_cica_Descuadre($_cica_Descuadre) {
        $this->_cica_Descuadre = $_cica_Descuadre;
    }
    
    function set_cica_Observaciones($_cica_Observaciones) {
        $this->_cica_Observaciones = $_cica_Observaciones;
    }
    
    function set_strNombreCaja($_strNombreCaja) {
        $this->_strNombreCaja = $_strNombreCaja;
    }
    
    function set_strNombreVendedor($_strNombreVendedor) {
        $this->_strNombreVendedor = $_strNombreVendedor;
    }    

}
