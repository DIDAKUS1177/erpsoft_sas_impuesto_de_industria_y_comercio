<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_CuentasPorPagar extends \predial\DAOGeneral {
    
    protected $_cupa_Id ;
    protected $_cupa_IdNota ;
    protected $_cupa_IdCuentaContable ;
    protected $_cupa_Valor ;
    protected $_cupa_FechaCreacion ;

    protected $_tabla = 'fac_cuentas_por_pagar';
    protected $_primario = 'cupa_Id';
    
    protected $_mapa = array(
        'cupa_Id' => array('tipodato' => 'integer'),
        'cupa_IdNota' => array('tipodato' => 'integer'),
        'cupa_IdCuentaContable' => array('tipodato' => 'integer'),
        'cupa_Valor' => array('tipodato' => 'varchar'),
        'cupa_FechaCreacion' => array('tipodato' => 'varchar')
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_cupa_Id() {
        return $this->_cupa_Id;
    }

    function get_cupa_IdNota() {
        return $this->_cupa_IdNota;
    }

    function get_cupa_IdCuentaContable() {
        return $this->_cupa_IdCuentaContable;
    }

    function get_cupa_Valor() {
        return $this->_cupa_Valor;
    }

    function get_cupa_FechaCreacion() {
        return $this->_cupa_FechaCreacion;
    }
    
    function set_cupa_Id($_cupa_Id) {
        $this->_cupa_Id = $_cupa_Id;
    }

    function set_cupa_IdNota($_cupa_IdNota) {
        $this->_cupa_IdNota = $_cupa_IdNota;
    }

    function set_cupa_IdCuentaContable($_cupa_IdCuentaContable) {
        $this->_cupa_IdCuentaContable = $_cupa_IdCuentaContable;
    }

    function set_cupa_Valor($_cupa_Valor) {
        $this->_cupa_Valor = $_cupa_Valor;
    }

    function set_cupa_FechaCreacion($_cupa_FechaCreacion) {
        $this->_cupa_FechaCreacion = $_cupa_FechaCreacion;
    }
    
}
