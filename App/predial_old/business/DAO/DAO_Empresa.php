<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_Empresa extends \predial\DAOGeneral {
    
    protected $_emp_Id ;
    protected $_emp_Nombre ;
    protected $_emp_NombreComercial ;
    protected $_emp_Nit ;
    protected $_emp_IdMunicipio ;
    protected $_emp_IdDepartamento ;
    protected $_emp_Email ;
    protected $_emp_SitioWeb ;
    protected $_emp_TipoImpresora ;
    protected $_emp_TipoPantalla ;
    protected $_emp_TextoFactura ;
    protected $_emp_UrlSoporteLogo ;
    
    protected $_emp_Estado ;
    protected $_emp_FechaCreacion ;    
    
    protected $_tabla = 'conf_empresa';
    protected $_primario = 'emp_Id';
    
    protected $_mapa = array(
        'emp_Id' => array('tipodato' => 'integer'),
        'emp_Nombre' => array('tipodato' => 'varchar'),
        'emp_NombreComercial' => array('tipodato' => 'varchar'),
        'emp_Nit' => array('tipodato' => 'varchar'),
        'emp_IdMunicipio' => array('tipodato' => 'integer'),
        'emp_IdDepartamento' => array('tipodato' => 'integer'),
        'emp_Email' => array('tipodato' => 'varchar'),
        'emp_SitioWeb' => array('tipodato' => 'varchar'),
        'emp_TipoImpresora' => array('tipodato' => 'integer'),
        'emp_TipoPantalla' => array('tipodato' => 'integer'),
        'emp_TextoFactura' => array('tipodato' => 'varchar'),
        'emp_UrlSoporteLogo' => array('tipodato' => 'varchar'),
        'emp_Estado' => array('tipodato' => 'integer'),
        'emp_FechaCreacion' => array('tipodato' => 'varchar'),
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_emp_Id() {
        return $this->_emp_Id;
    }

    function get_emp_Nombre() {
        return $this->_emp_Nombre;
    }

    function get_emp_NombreComercial() {
        return $this->_emp_NombreComercial;
    }
    
    function get_emp_Nit() {
        return $this->_emp_Nit;
    }

    function get_emp_IdMunicipio() {
        return $this->_emp_IdMunicipio;
    }

    function get_emp_IdDepartamento() {
        return $this->_emp_IdDepartamento;
    }

    function get_emp_Email() {
        return $this->_emp_Email;
    }

    function get_emp_SitioWeb() {
        return $this->_emp_SitioWeb;
    }

    function get_emp_TipoImpresora() {
        return $this->_emp_TipoImpresora;
    }

    function get_emp_TipoPantalla() {
        return $this->_emp_TipoPantalla;
    }
    
    function get_emp_TextoFactura() {
        return $this->_emp_TextoFactura;
    }

    function get_emp_UrlSoporteLogo() {
        return $this->_emp_UrlSoporteLogo;
    }

    function get_emp_Estado() {
        return $this->_emp_Estado;
    }

    function get_emp_FechaCreacion() {
        return $this->_emp_FechaCreacion;
    }

    function set_emp_Id($_emp_Id) {
        $this->_emp_Id = $_emp_Id;
    }

    function set_emp_Nombre($_emp_Nombre) {
        $this->_emp_Nombre = $_emp_Nombre;
    }

    function set_emp_NombreComercial($_emp_NombreComercial) {
        $this->_emp_NombreComercial = $_emp_NombreComercial;
    }

    function set_emp_Nit($_emp_Nit) {
        $this->_emp_Nit = $_emp_Nit;
    }

    function set_emp_IdMunicipio($_emp_IdMunicipio) {
        $this->_emp_IdMunicipio = $_emp_IdMunicipio;
    }

    function set_emp_IdDepartamento($_emp_IdDepartamento) {
        $this->_emp_IdDepartamento = $_emp_IdDepartamento;
    }

    function set_emp_Email($_emp_Email) {
        $this->_emp_Email = $_emp_Email;
    }
    
    function set_emp_SitioWeb($_emp_SitioWeb) {
        $this->_emp_SitioWeb = $_emp_SitioWeb;
    }

    function set_emp_TipoImpresora($_emp_TipoImpresora) {
        $this->_emp_TipoImpresora = $_emp_TipoImpresora;
    }

    function set_emp_TipoPantalla($_emp_TipoPantalla) {
        $this->_emp_TipoPantalla = $_emp_TipoPantalla;
    }
    
    function set_emp_TextoFactura($_emp_TextoFactura) {
        $this->_emp_TextoFactura = $_emp_TextoFactura;
    }

    function set_emp_UrlSoporteLogo($_emp_UrlSoporteLogo) {
        $this->_emp_UrlSoporteLogo = $_emp_UrlSoporteLogo;
    }    

    function set_emp_Estado($_emp_Estado) {
        $this->_emp_Estado = $_emp_Estado;
    }

    function set_emp_FechaCreacion($_emp_FechaCreacion) {
        $this->_emp_FechaCreacion = $_emp_FechaCreacion;
    }
    
}
