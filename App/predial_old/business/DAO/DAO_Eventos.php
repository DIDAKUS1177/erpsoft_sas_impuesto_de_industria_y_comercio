<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_Eventos extends \predial\DAOGeneral {
    
    protected $_eve_Id ;
    protected $_eve_Nombre ;
    protected $_eve_Descripcion ;
    protected $_eve_FechaEvento	;
    protected $_eve_NombreCliente ;
    protected $_eve_TelefonoCliente	;
    protected $_eve_Email ;
    protected $_eve_LugarEvento ;
    protected $_eve_ValorEvento	;
    protected $_eve_Notas ;
    protected $_eve_FechaCreacion ;
    protected $_eve_Estado ;
    
    protected $_tabla = 'eve_eventos';
    protected $_primario = 'eve_Id';
    
    protected $_mapa = array(
        'eve_Id' => array('tipodato' => 'integer'),
        'eve_Nombre' => array('tipodato' => 'varchar'),
        'eve_Descripcion' => array('tipodato' => 'varchar'),
        'eve_FechaEvento' => array('tipodato' => 'varchar'),
        'eve_NombreCliente' => array('tipodato' => 'varchar'),
        'eve_TelefonoCliente' => array('tipodato' => 'varchar'),
        'eve_Email' => array('tipodato' => 'varchar'),
        'eve_LugarEvento' => array('tipodato' => 'varchar'),
        'eve_ValorEvento' => array('tipodato' => 'integer'),
        'eve_Notas' => array('tipodato' => 'varchar'),
        'eve_FechaCreacion' => array('tipodato' => 'varchar'),
        'eve_Estado' => array('tipodato' => 'integer'),
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_eve_Id() {
        return $this->_eve_Id;
    }

    function set_eve_Id($_eve_Id) {
        $this->_eve_Id = $_eve_Id;
    }

    function get_eve_Nombre() {
        return $this->_eve_Nombre;
    }

    function set_eve_Nombre($_eve_Nombre) {
        $this->_eve_Nombre = $_eve_Nombre;
    }

    function get_eve_Descripcion() {
        return $this->_eve_Descripcion;
    }

    function set_eve_Descripcion($_eve_Descripcion) {
        $this->_eve_Descripcion = $_eve_Descripcion;
    }

    function get_eve_FechaEvento() {
        return $this->_eve_FechaEvento;
    }

    function set_eve_FechaEvento($_eve_FechaEvento) {
        $this->_eve_FechaEvento = $_eve_FechaEvento;
    }

    function get_eve_NombreCliente() {
        return $this->_eve_NombreCliente;
    }

    function set_eve_NombreCliente($_eve_NombreCliente) {
        $this->_eve_NombreCliente = $_eve_NombreCliente;
    }

    function get_eve_TelefonoCliente() {
        return $this->_eve_TelefonoCliente;
    }

    function set_eve_TelefonoCliente($_eve_TelefonoCliente) {
        $this->_eve_TelefonoCliente = $_eve_TelefonoCliente;
    }

    function get_eve_Email() {
        return $this->_eve_Email;
    }

    function set_eve_Email($_eve_Email) {
        $this->_eve_Email = $_eve_Email;
    }

    function get_eve_LugarEvento() {
        return $this->_eve_LugarEvento;
    }

    function set_eve_LugarEvento($_eve_LugarEvento) {
        $this->_eve_LugarEvento = $_eve_LugarEvento;
    }

    function get_eve_ValorEvento() {
        return $this->_eve_ValorEvento;
    }

    function set_eve_ValorEvento($_eve_ValorEvento) {
        $this->_eve_ValorEvento = $_eve_ValorEvento;
    }

    function get_eve_Notas() {
        return $this->_eve_Notas;
    }

    function set_eve_Notas($_eve_Notas) {
        $this->_eve_Notas = $_eve_Notas;
    }

    function get_eve_FechaCreacion() {
        return $this->_eve_FechaCreacion;
    }

    function set_eve_FechaCreacion($_eve_FechaCreacion) {
        $this->_eve_FechaCreacion = $_eve_FechaCreacion;
    }
    
    function get_eve_Estado() {
        return $this->_eve_Estado;
    }

    function set_eve_Estado($_eve_Estado) {
        $this->_eve_Estado = $_eve_Estado;
    }
    
}
