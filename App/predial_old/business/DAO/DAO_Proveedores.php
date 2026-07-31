<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_Proveedores extends \predial\DAOGeneral {
    
    protected $_prov_Id ;
    protected $_prov_Nombre ;
    protected $_prov_RazonSocial ;
    protected $_prov_Nit ;
    protected $_prov_Direccion ;
    protected $_prov_IdDepartamento ;
    protected $_prov_IdCiudad ;
    protected $_prov_Telefono ;
    protected $_prov_Email ;
    protected $_prov_IdTipoPersona ;
    protected $_prov_Estado ;
    protected $_prov_FechaCreacion ;
    
    protected $_tabla = 'inv_proveedores';
    protected $_primario = 'prov_Id';
    
    protected $_mapa = array(
        'prov_Id' => array('tipodato' => 'integer'),
        'prov_Nombre' => array('tipodato' => 'varchar'),
        'prov_RazonSocial' => array('tipodato' => 'varchar'),
        'prov_Nit' => array('tipodato' => 'varchar'),
        'prov_Direccion' => array('tipodato' => 'varchar'),
        'prov_IdDepartamento' => array('tipodato' => 'integer'),
        'prov_IdCiudad' => array('tipodato' => 'integer'),
        'prov_Telefono' => array('tipodato' => 'varchar'),
        'prov_Email' => array('tipodato' => 'varchar'),
        'prov_IdTipoPersona' => array('tipodato' => 'integer'),
        'prov_Estado' => array('tipodato' => 'integer'),
        'prov_FechaCreacion' => array('tipodato' => 'varchar')
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_prov_Id() {
        return $this->_prov_Id;
    }

    function get_prov_Nombre() {
        return $this->_prov_Nombre;
    }

    function get_prov_RazonSocial() {
        return $this->_prov_RazonSocial;
    }

    function get_prov_Nit() {
        return $this->_prov_Nit;
    }

    function get_prov_Direccion() {
        return $this->_prov_Direccion;
    }
    
    function get_prov_IdDepartamento() {
        return $this->_prov_IdDepartamento;
    }
    
    function get_prov_IdCiudad() {
        return $this->_prov_IdCiudad;
    }
    
    function get_prov_Telefono() {
        return $this->_prov_Telefono;
    }
    
    function get_prov_Email() {
        return $this->_prov_Email;
    }
    
    function get_prov_IdTipoPersona() {
        return $this->_prov_IdTipoPersona;
    }

    function get_prov_Estado() {
        return $this->_prov_Estado;
    }

    function get_prov_FechaCreacion() {
        return $this->_prov_FechaCreacion;
    }

    function set_prov_Id($_prov_Id) {
        $this->_prov_Id = $_prov_Id;
    }

    function set_prov_Nombre($_prov_Nombre) {
        $this->_prov_Nombre = $_prov_Nombre;
    }

    function set_prov_RazonSocial($_prov_RazonSocial) {
        $this->_prov_RazonSocial = $_prov_RazonSocial;
    }

    function set_prov_Nit($_prov_Nit) {
        $this->_prov_Nit = $_prov_Nit;
    }

    function set_prov_Direccion($_prov_Direccion) {
        $this->_prov_Direccion = $_prov_Direccion;
    }

    function set_prov_IdDepartamento($_prov_IdDepartamento) {
        $this->_prov_IdDepartamento = $_prov_IdDepartamento;
    }

    function set_prov_IdCiudad($_prov_IdCiudad) {
        $this->_prov_IdCiudad = $_prov_IdCiudad;
    }

    function set_prov_Telefono($_prov_Telefono) {
        $this->_prov_Telefono = $_prov_Telefono;
    }

    function set_prov_Email($_prov_Email) {
        $this->_prov_Email = $_prov_Email;
    }

    function set_prov_IdTipoPersona($_prov_IdTipoPersona) {
        $this->_prov_IdTipoPersona = $_prov_IdTipoPersona;
    }

    function set_prov_Estado($_prov_Estado) {
        $this->_prov_Estado = $_prov_Estado;
    }

    function set_prov_FechaCreacion($_prov_FechaCreacion) {
        $this->_prov_FechaCreacion = $_prov_FechaCreacion;
    }

}
