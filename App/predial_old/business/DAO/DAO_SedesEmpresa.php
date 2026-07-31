<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_SedesEmpresa extends \predial\DAOGeneral {
    
    protected $_seem_Id ;
    protected $_seem_IdEmpresa ;
    protected $_seem_IdBodega ;
    protected $_seem_Nombre ;
    protected $_seem_Direccion ;
    protected $_seem_IdMunicipio ;
    protected $_seem_IdDepartamento ;
    protected $_seem_Telefono ;
    protected $_seem_Estado ;
    protected $_seem_Email ;    
    protected $_seem_FechaCreacion ;    
    
    protected $_tabla = 'conf_sedes_empresa';
    protected $_primario = 'seem_Id';
    
    protected $_mapa = array(
        'seem_Id' => array('tipodato' => 'integer'),
        'seem_IdEmpresa' => array('tipodato' => 'integer'),
        'seem_IdBodega' => array('tipodato' => 'integer'),
        'seem_Nombre' => array('tipodato' => 'varchar'),
        'seem_Direccion' => array('tipodato' => 'varchar'),
        'seem_IdMunicipio' => array('tipodato' => 'integer'),
        'seem_IdDepartamento' => array('tipodato' => 'integer'),
        'seem_Telefono' => array('tipodato' => 'integer'),
        'seem_Estado' => array('tipodato' => 'integer'),
        'seem_Email' => array('tipodato' => 'varchar'),
        'seem_FechaCreacion' => array('tipodato' => 'varchar'),
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_seem_Id() {
        return $this->_seem_Id;
    }

    function get_seem_IdEmpresa() {
        return $this->_seem_IdEmpresa;
    }

    function get_seem_IdBodega() {
        return $this->_seem_IdBodega;
    }
    
    function get_seem_Nombre() {
        return $this->_seem_Nombre;
    }

    function get_seem_Direccion() {
        return $this->_seem_Direccion;
    }

    function get_seem_IdMunicipio() {
        return $this->_seem_IdMunicipio;
    }

    function get_seem_IdDepartamento() {
        return $this->_seem_IdDepartamento;
    }

    function get_seem_Telefono() {
        return $this->_seem_Telefono;
    }

    function get_seem_Estado() {
        return $this->_seem_Estado;
    }

    function get_seem_Email() {
        return $this->_seem_Email;
    }

    function get_seem_FechaCreacion() {
        return $this->_seem_FechaCreacion;
    }
    
    function set_seem_Id($_seem_Id) {
        $this->_seem_Id = $_seem_Id;
    }

    function set_seem_IdEmpresa($_seem_IdEmpresa) {
        $this->_seem_IdEmpresa = $_seem_IdEmpresa;
    }

    function set_seem_IdBodega($_seem_IdBodega) {
        $this->_seem_IdBodega = $_seem_IdBodega;
    }

    function set_seem_Nombre($_seem_Nombre) {
        $this->_seem_Nombre = $_seem_Nombre;
    }

    function set_seem_Direccion($_seem_Direccion) {
        $this->_seem_Direccion = $_seem_Direccion;
    }

    function set_seem_IdMunicipio($_seem_IdMunicipio) {
        $this->_seem_IdMunicipio = $_seem_IdMunicipio;
    }

    function set_seem_IdDepartamento($_seem_IdDepartamento) {
        $this->_seem_IdDepartamento = $_seem_IdDepartamento;
    }
    
    function set_seem_Telefono($_seem_Telefono) {
        $this->_seem_Telefono = $_seem_Telefono;
    }

    function set_seem_Estado($_seem_Estado) {
        $this->_seem_Estado = $_seem_Estado;
    }

    function set_seem_Email($_seem_Email) {
        $this->_seem_Email = $_seem_Email;
    }

    function set_seem_FechaCreacion($_seem_FechaCreacion) {
        $this->_seem_FechaCreacion = $_seem_FechaCreacion;
    }
    
}
