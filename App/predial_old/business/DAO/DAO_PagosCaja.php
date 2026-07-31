<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_PagosCaja extends \predial\DAOGeneral {
    
    protected $_paca_Id ;
    protected $_paca_IdCaja ;
    protected $_paca_IdVendedor ;
    protected $_paca_IdTipoPago ;
    protected $_paca_IdSubTipoPago ;
    protected $_paca_Valor ;
    protected $_paca_Observaciones ;
    protected $_paca_Cierre ;
    protected $_paca_IdCierre ;
    protected $_paca_FechaCreacion ;

    protected $_strNombreCaja ;
    protected $_strNombreVendedor ;
    protected $_strNombreTipoPago ;
    
    protected $_tabla = 'fac_pagos_caja';
    protected $_primario = 'paca_Id';
    
    protected $_mapa = array(
        'paca_Id' => array('tipodato' => 'integer'),
        'paca_IdCaja' => array('tipodato' => 'integer'),
        'paca_IdVendedor' => array('tipodato' => 'integer'),
        'paca_IdTipoPago' => array('tipodato' => 'integer'),
        'paca_IdSubTipoPago' => array('tipodato' => 'integer'),
        'paca_Valor' => array('tipodato' => 'varchar'),
        'paca_Observaciones' => array('tipodato' => 'varchar'),
        'paca_Cierre' => array('tipodato' => 'integer'),
        'paca_IdCierre' => array('tipodato' => 'integer'),
        'paca_FechaCreacion' => array('tipodato' => 'varchar'),
        'strNombreCaja' => array('tipodato' => 'integer','sql' => '(select cse.seemca_Nombre from conf_sedes_empresa_cajas as cse where cse.seemca_Id = fac_pagos_caja.paca_IdCaja)'),
        'strNombreVendedor' => array('tipodato' => 'integer','sql' => '(select cu.usu_Nombre from conf_usuario as cu where cu.usu_Id = fac_pagos_caja.paca_IdVendedor)'),
        'strNombreTipoPago' => array('tipodato' => 'integer','sql' => '(select tipa.tipa_Nombre from fac_tipos_pagos as tipa where tipa.tipa_Id = fac_pagos_caja.paca_IdTipoPago)')
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_paca_Id() {
        return $this->_paca_Id;
    }

    function get_paca_IdCaja() {
        return $this->_paca_IdCaja;
    }

    function get_paca_IdVendedor() {
        return $this->_paca_IdVendedor;
    }
    function get_paca_IdTipoPago() {
        return $this->_paca_IdTipoPago;
    }

    function get_paca_IdSubTipoPago() {
        return $this->_paca_IdSubTipoPago;
    }
        
    function get_paca_Valor() {
        return $this->_paca_Valor;
    }

    function get_paca_Observaciones() {
        return $this->_paca_Observaciones;
    }
    
    function get_paca_Cierre() {
        return $this->_paca_Cierre;
    }

    function get_paca_IdCierre() {
        return $this->_paca_IdCierre;
    }

    function get_paca_FechaCreacion() {
        return $this->_paca_FechaCreacion;
    }

    function get_strNombreCaja() {
        return $this->_strNombreCaja;
    }

    function get_strNombreVendedor() {
        return $this->_strNombreVendedor;
    }

    function get_strNombreTipoPago() {
        return $this->_strNombreTipoPago;
    }

    function set_paca_Id($_paca_Id) {
        $this->_paca_Id = $_paca_Id;
    }

    function set_paca_IdCaja($_paca_IdCaja) {
        $this->_paca_IdCaja = $_paca_IdCaja;
    }

    function set_paca_IdVendedor($_paca_IdVendedor) {
        $this->_paca_IdVendedor = $_paca_IdVendedor;
    }

    function set_paca_IdTipoPago($_paca_IdTipoPago) {
        $this->_paca_IdTipoPago = $_paca_IdTipoPago;
    }

    function set_paca_IdSubTipoPago($_paca_IdSubTipoPago) {
        $this->_paca_IdSubTipoPago = $_paca_IdSubTipoPago;
    }
    
    function set_paca_Valor($_paca_Valor) {
        $this->_paca_Valor = $_paca_Valor;
    }

    function set_paca_Observaciones($_paca_Observaciones) {
        $this->_paca_Observaciones = $_paca_Observaciones;
    }

    function set_paca_Cierre($_paca_Cierre) {
        $this->_paca_Cierre = $_paca_Cierre;
    }

    function set_paca_IdCierre($_paca_IdCierre) {
        $this->_paca_IdCierre = $_paca_IdCierre;
    }

    function set_paca_FechaCreacion($_paca_FechaCreacion) {
        $this->_paca_FechaCreacion = $_paca_FechaCreacion;
    }
    
    function set_strNombreCaja($_strNombreCaja) {
        $this->_strNombreCaja = $_strNombreCaja;
    }
    
    function set_strNombreVendedor($_strNombreVendedor) {
        $this->_strNombreVendedor = $_strNombreVendedor;
    }    

    function set_strNombreTipoPago($_strNombreTipoPago) {
        $this->_strNombreTipoPago = $_strNombreTipoPago;
    }

}
