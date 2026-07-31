<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_CuentasPorCobrar extends \predial\DAOGeneral {
    
    protected $_cuco_Id ;
    protected $_cuco_IdDocumento ;
    protected $_cuco_IdCuentaContable ;
    protected $_cuco_Valor ;
    protected $_cuco_FechaCreacion ;

    protected $_tabla = 'fac_cuentas_por_cobrar';
    protected $_primario = 'cuco_Id';
    
    protected $_mapa = array(
        'cuco_Id' => array('tipodato' => 'integer'),
        'cuco_IdDocumento' => array('tipodato' => 'integer'),
        'cuco_IdCuentaContable' => array('tipodato' => 'integer'),
        'cuco_Valor' => array('tipodato' => 'varchar'),
        'cuco_FechaCreacion' => array('tipodato' => 'varchar')
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_cuco_Id() {
        return $this->_cuco_Id;
    }

    function get_cuco_IdDocumento() {
        return $this->_cuco_IdDocumento;
    }

    function get_cuco_IdCuentaContable() {
        return $this->_cuco_IdCuentaContable;
    }

    function get_cuco_Valor() {
        return $this->_cuco_Valor;
    }

    function get_cuco_FechaCreacion() {
        return $this->_cuco_FechaCreacion;
    }
    
    function set_cuco_Id($_cuco_Id) {
        $this->_cuco_Id = $_cuco_Id;
    }

    function set_cuco_IdDocumento($_cuco_IdDocumento) {
        $this->_cuco_IdDocumento = $_cuco_IdDocumento;
    }

    function set_cuco_IdCuentaContable($_cuco_IdCuentaContable) {
        $this->_cuco_IdCuentaContable = $_cuco_IdCuentaContable;
    }

    function set_cuco_Valor($_cuco_Valor) {
        $this->_cuco_Valor = $_cuco_Valor;
    }

    function set_cuco_FechaCreacion($_cuco_FechaCreacion) {
        $this->_cuco_FechaCreacion = $_cuco_FechaCreacion;
    }
    
}
