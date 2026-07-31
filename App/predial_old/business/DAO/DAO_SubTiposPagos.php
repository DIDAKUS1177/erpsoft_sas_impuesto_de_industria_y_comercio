<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_SubTiposPagos extends \predial\DAOGeneral {
    
    protected $_subtipa_Id ;
    protected $_subtipa_IdTipo ;
    protected $_subtipa_Nombre ;
    protected $_subtipa_Estado ;
    protected $_subtipa_FechaCreacion ;
    
    protected $_tabla = 'fac_sub_tipos_pagos';
    protected $_primario = 'subtipa_Id';
    
    protected $_mapa = array(
        'subtipa_Id' => array('tipodato' => 'integer'),
        'subtipa_IdTipo' => array('tipodato' => 'integer'),
        'subtipa_Nombre' => array('tipodato' => 'varchar'),
        'subtipa_Estado' => array('tipodato' => 'integer'),
        'subtipa_FechaCreacion' => array('tipodato' => 'integer')
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_subtipa_Id() {
        return $this->_subtipa_Id;
    }

    function get_subtipa_IdTipo() {
        return $this->_subtipa_IdTipo;
    }

    function get_subtipa_Nombre() {
        return $this->_subtipa_Nombre;
    }

    function get_subtipa_Estado() {
        return $this->_subtipa_Estado;
    }
    function get_subtipa_FechaCreacion() {
        return $this->_subtipa_FechaCreacion;
    }

    function set_subtipa_Id($_subtipa_Id) {
        $this->_subtipa_Id = $_subtipa_Id;
    }

    function set_subtipa_IdTipo($_subtipa_IdTipo) {
        $this->_subtipa_IdTipo = $_subtipa_IdTipo;
    }

    function set_subtipa_Nombre($_subtipa_Nombre) {
        $this->_subtipa_Nombre = $_subtipa_Nombre;
    }

    function set_subtipa_Estado($_subtipa_Estado) {
        $this->_subtipa_Estado = $_subtipa_Estado;
    }

    function set_subtipa_FechaCreacion($_subtipa_FechaCreacion) {
        $this->_subtipa_FechaCreacion = $_subtipa_FechaCreacion;
    }
    
}
