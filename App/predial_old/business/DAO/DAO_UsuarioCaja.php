<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_UsuarioCaja extends \predial\DAOGeneral {
    
    protected $_usuca_Id ;
    protected $_usuca_IdSede ;
    protected $_usuca_IdCaja ;
    protected $_usuca_IdVendedor ;
    protected $_usuca_FechaCreacion ;
    
    protected $_tabla = 'conf_usuario_caja';
    protected $_primario = 'usuca_Id';
    
    protected $_mapa = array(
        'usuca_Id' => array('tipodato' => 'integer'),
        'usuca_IdSede' => array('tipodato' => 'integer'),
        'usuca_IdCaja' => array('tipodato' => 'varchar'),
        'usuca_IdVendedor' => array('tipodato' => 'varchar'),
        'usuca_FechaCreacion' => array('tipodato' => 'varchar'),
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_usuca_Id() {
        return $this->_usuca_Id;
    }

    function get_usuca_IdSede() {
        return $this->_usuca_IdSede;
    }

    function get_usuca_IdCaja() {
        return $this->_usuca_IdCaja;
    }

    function get_usuca_IdVendedor() {
        return $this->_usuca_IdVendedor;
    }

    function get_usuca_FechaCreacion() {
        return $this->_usuca_FechaCreacion;
    }



    function set_usuca_Id($_usuca_Id) {
        $this->_usuca_Id = $_usuca_Id;
    }

    function set_usuca_IdSede($_usuca_IdSede) {
        $this->_usuca_IdSede = $_usuca_IdSede;
    }

    function set_usuca_IdCaja($_usuca_IdCaja) {
        $this->_usuca_IdCaja = $_usuca_IdCaja;
    }

    function set_usuca_IdVendedor($_usuca_IdVendedor) {
        $this->_usuca_IdVendedor = $_usuca_IdVendedor;
    }

    function set_usuca_FechaCreacion($_usuca_FechaCreacion){
        $this->_usuca_FechaCreacion = $_usuca_FechaCreacion;
    }

}
