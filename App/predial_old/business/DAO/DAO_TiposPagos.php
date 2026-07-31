<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_TiposPagos extends \predial\DAOGeneral {
    
    protected $_tipa_Id ;
    protected $_tipa_IdTipo ;
    protected $_tipa_Nombre ;
    protected $_tipa_Estado ;
    protected $_tipa_FechaCreacion ;
    
    protected $_tabla = 'fac_tipos_pagos';
    protected $_primario = 'tipa_Id';
    
    protected $_mapa = array(
        'tipa_Id' => array('tipodato' => 'integer'),
        'tipa_IdTipo' => array('tipodato' => 'integer'),
        'tipa_Nombre' => array('tipodato' => 'varchar'),
        'tipa_Estado' => array('tipodato' => 'integer'),
        'tipa_FechaCreacion' => array('tipodato' => 'integer')
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_tipa_Id() {
        return $this->_tipa_Id;
    }

    function get_tipa_IdTipo() {
        return $this->_tipa_IdTipo;
    }

    function get_tipa_Nombre() {
        return $this->_tipa_Nombre;
    }

    function get_tipa_Estado() {
        return $this->_tipa_Estado;
    }
    function get_tipa_FechaCreacion() {
        return $this->_tipa_FechaCreacion;
    }
    

    function set_tipa_Id($_tipa_Id) {
        $this->_tipa_Id = $_tipa_Id;
    }

    function set_tipa_IdTipo($_tipa_IdTipo) {
        $this->_tipa_IdTipo = $_tipa_IdTipo;
    }

    function set_tipa_Nombre($_tipa_Nombre) {
        $this->_tipa_Nombre = $_tipa_Nombre;
    }

    function set_tipa_Estado($_tipa_Estado) {
        $this->_tipa_Estado = $_tipa_Estado;
    }

    function set_tipa_FechaCreacion($_tipa_FechaCreacion) {
        $this->_tipa_FechaCreacion = $_tipa_FechaCreacion;
    }
    
}
