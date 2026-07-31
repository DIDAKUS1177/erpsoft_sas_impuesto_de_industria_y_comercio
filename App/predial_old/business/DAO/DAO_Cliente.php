<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_Cliente extends \predial\DAOGeneral {
    
    protected $_cli_Id ;
    protected $_cli_IdTipoPersona ;
    protected $_cli_Nombre ;
    protected $_cli_RazonSocial ;
    protected $_cli_Identificacion ;
    protected $_cli_Direccion ;
    protected $_cli_IdDepartamento ;
    protected $_cli_IdCiudad ;
    protected $_cli_Telefono ;
    protected $_cli_Correo ;
    protected $_cli_Estado ;
    protected $_cli_FechaCreacion ;
    
    protected $_tabla = 'fac_cliente';
    protected $_primario = 'cli_Id';
    
    protected $_mapa = array(
        'cli_Id' => array('tipodato' => 'integer'),
        'cli_IdTipoPersona' => array('tipodato' => 'integer'),
        'cli_Nombre' => array('tipodato' => 'varchar'),
        'cli_RazonSocial' => array('tipodato' => 'varchar'),
        'cli_Identificacion' => array('tipodato' => 'varchar'),
        'cli_Direccion' => array('tipodato' => 'varchar'),
        'cli_IdDepartamento' => array('tipodato' => 'integer'),
        'cli_IdCiudad' => array('tipodato' => 'integer'),
        'cli_Telefono' => array('tipodato' => 'varchar'),
        'cli_Correo' => array('tipodato' => 'varchar'),
        'cli_Estado' => array('tipodato' => 'integer'),
        'cli_FechaCreacion' => array('tipodato' => 'varchar'),
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_cli_Id() {
        return $this->_cli_Id;
    }

    function get_cli_IdTipoPersona() {
        return $this->_cli_IdTipoPersona;
    }

    function get_cli_Nombre() {
        return $this->_cli_Nombre;
    }

    function get_cli_RazonSocial() {
        return $this->_cli_RazonSocial;
    }

    function get_cli_Identificacion() {
        return $this->_cli_Identificacion;
    }

    function get_cli_Direccion(){
        return $this->_cli_Direccion;
    }

    function get_cli_IdDepartamento(){
        return $this->_cli_IdDepartamento;
    }

    function get_cli_IdCiudad(){
        return $this->_cli_IdCiudad;
    }
    
    function get_cli_Telefono(){
        return $this->_cli_Telefono;
    }

    function get_cli_Correo(){
        return $this->_cli_Correo;
    }

    function get_cli_Estado(){
        return $this->_cli_Estado;
    }

    function get_cli_FechaCreacion(){
        return $this->_cli_FechaCreacion;
    }

    function set_cli_Id($_cli_Id) {
        $this->_cli_Id = $_cli_Id;
    }

    function set_cli_IdTipoPersona($_cli_IdTipoPersona) {
        $this->_cli_IdTipoPersona = $_cli_IdTipoPersona;
    }

    function set_cli_Nombre($_cli_Nombre) {
        $this->_cli_Nombre = $_cli_Nombre;
    }

    function set_cli_RazonSocial($_cli_RazonSocial) {
        $this->_cli_RazonSocial = $_cli_RazonSocial;
    }

    function set_cli_Identificacion($_cli_Identificacion){
        $this->_cli_Identificacion = $_cli_Identificacion;
    }

    function set_cli_Direccion($_cli_Direccion){
        $this->_cli_Direccion = $_cli_Direccion;
    }

    function set_cli_IdDepartamento($_cli_IdDepartamento){
        $this->_cli_IdDepartamento = $_cli_IdDepartamento;
    }

    function set_cli_IdCiudad($_cli_IdCiudad){
        $this->_cli_IdCiudad = $_cli_IdCiudad;
    }

    function set_cli_Telefono($_cli_Telefono){
        $this->_cli_Telefono = $_cli_Telefono;
    }

    function set_cli_Correo($_cli_Correo){
        $this->_cli_Correo = $_cli_Correo;
    }

    function set_cli_Estado($_cli_Estado){
        $this->_cli_Estado = $_cli_Estado;
    }

    function set_cli_FechaCreacion($_cli_FechaCreacion){
        $this->_cli_FechaCreacion = $_cli_FechaCreacion;
    }
}
