<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_CuentasContables extends \predial\DAOGeneral {
    
    protected $_cuco_Id ;
    protected $_cuco_IdCaja ;
    protected $_cuco_IdTipoMovimiento ;
    protected $_cuco_IdCuentaContable ;
    protected $_cuco_IdDocumento ;
    protected $_cuco_IdTipoSalida ;
    protected $_cuco_IdSubTipoSalida ;
    protected $_cuco_Valor ;
    protected $_cuco_Observacion ;
    protected $_cuco_Cierre ;
    protected $_cuco_IdCierre ;
    protected $_cuco_Estado ;
    protected $_cuco_FechaCreacion ;

    protected $_strNombreCuentaContable ;
    protected $_strNombreTipoSalida ;
    
    protected $_tabla = 'fac_cuentascontables';
    protected $_primario = 'cuco_Id';
    
    protected $_mapa = array(
        'cuco_Id' => array('tipodato' => 'integer'),
        'cuco_IdCaja' => array('tipodato' => 'integer'),
        'cuco_IdTipoMovimiento' => array('tipodato' => 'integer'),
        'cuco_IdCuentaContable' => array('tipodato' => 'integer'),
        'cuco_IdDocumento' => array('tipodato' => 'integer'),
        'cuco_IdTipoSalida' => array('tipodato' => 'integer'),
        'cuco_IdSubTipoSalida' => array('tipodato' => 'integer'),
        'cuco_Valor' => array('tipodato' => 'integer'),
        'cuco_Observacion' => array('tipodato' => 'varchar'),
        'cuco_Cierre' => array('tipodato' => 'integer'),
        'cuco_IdCierre' => array('tipodato' => 'integer'),
        'cuco_Estado' => array('tipodato' => 'integer'),
        'cuco_FechaCreacion' => array('tipodato' => 'varchar'),
        'strNombreCuentaContable' => array('tipodato' => 'varchar','sql' => '(select fopa.forpa_Descripcion from fac_formas_pago as fopa where fopa.forpa_Id  = fac_cuentascontables.cuco_IdCuentaContable)'),
        'strNombreTipoSalida' => array('tipodato' => 'varchar','sql' => '(select tipa.tipa_Nombre from fac_tipos_pagos as tipa where tipa.tipa_Id = fac_cuentascontables.cuco_IdTipoSalida)')
        
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_cuco_Id() {
        return $this->_cuco_Id;
    }

    function get_cuco_IdCaja() {
        return $this->_cuco_IdCaja;
    }
    
    function get_cuco_IdTipoMovimiento() {
        return $this->_cuco_IdTipoMovimiento;
    }

    function get_cuco_IdCuentaContable() {
        return $this->_cuco_IdCuentaContable;
    }

    function get_cuco_IdDocumento() {
        return $this->_cuco_IdDocumento;
    }

    function get_cuco_IdTipoSalida() {
        return $this->_cuco_IdTipoSalida;
    }
    
    function get_cuco_IdSubTipoSalida() {
        return $this->_cuco_IdSubTipoSalida;
    }
    
    function get_cuco_Valor() {
        return $this->_cuco_Valor;
    }
    
    function get_cuco_Observacion() {
        return $this->_cuco_Observacion;
    }

    function get_cuco_Cierre() {
        return $this->_cuco_Cierre;
    }

    function get_cuco_IdCierre() {
        return $this->_cuco_IdCierre;
    }

    function get_cuco_Estado() {
        return $this->_cuco_Estado;
    }

    function get_cuco_FechaCreacion() {
        return $this->_cuco_FechaCreacion;
    }
    
    function get_strNombreCuentaContable() {
        return $this->_strNombreCuentaContable;
    }

    function get_strNombreTipoSalida() {
        return $this->_strNombreTipoSalida;
    }
    
    
    function set_cuco_Id($_cuco_Id) {
        $this->_cuco_Id = $_cuco_Id;
    }

    function set_cuco_IdCaja($_cuco_IdCaja) {
        $this->_cuco_IdCaja = $_cuco_IdCaja;
    }

    function set_cuco_IdTipoMovimiento($_cuco_IdTipoMovimiento) {
        $this->_cuco_IdTipoMovimiento = $_cuco_IdTipoMovimiento;
    }

    function set_cuco_IdCuentaContable($_cuco_IdCuentaContable) {
        $this->_cuco_IdCuentaContable = $_cuco_IdCuentaContable;
    }

    function set_cuco_IdDocumento($_cuco_IdDocumento) {
        $this->_cuco_IdDocumento = $_cuco_IdDocumento;
    }

    function set_cuco_IdTipoSalida($_cuco_IdTipoSalida) {
        $this->_cuco_IdTipoSalida = $_cuco_IdTipoSalida;
    }
    
    function set_cuco_IdSubTipoSalida($_cuco_IdSubTipoSalida) {
        $this->_cuco_IdSubTipoSalida = $_cuco_IdSubTipoSalida;
    }
    
    function set_cuco_Valor($_cuco_Valor) {
        $this->_cuco_Valor = $_cuco_Valor;
    }

    function set_cuco_Observacion($_cuco_Observacion) {
        $this->_cuco_Observacion = $_cuco_Observacion;
    }

    function set_cuco_Cierre($_cuco_Cierre) {
        $this->_cuco_Cierre = $_cuco_Cierre;
    }

    function set_cuco_IdCierre($_cuco_IdCierre) {
        $this->_cuco_IdCierre = $_cuco_IdCierre;
    }

    function set_cuco_Estado($_cuco_Estado) {
        $this->_cuco_Estado = $_cuco_Estado;
    }

    function set_cuco_FechaCreacion($_cuco_FechaCreacion) {
        $this->_cuco_FechaCreacion = $_cuco_FechaCreacion;
    }

    function set_strNombreCuentaContable($_strNombreCuentaContable) {
        $this->_strNombreCuentaContable = $_strNombreCuentaContable;
    }
 
    function set_strNombreTipoSalida($_strNombreTipoSalida) {
        $this->_strNombreTipoSalida = $_strNombreTipoSalida;
    }
    
}
