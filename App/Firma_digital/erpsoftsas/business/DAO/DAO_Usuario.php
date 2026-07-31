<?php
namespace erpsoftsas;
include_once 'class.DAO.php';

class DAO_Usuario extends \erpsoftsas\DAOGeneral {
    
    protected $_usu_Id ;
    protected $_usu_NumeroDocumento ;
    protected $_usu_IdTipoDocumento ;
    protected $_usu_Nombres ;
    protected $_usu_Apellidos ;
    protected $_usu_Telefono ;
    protected $_usu_Direccion ;

    protected $_usu_Usuario ;
    protected $_usu_Correo ;
    protected $_usu_Password ;
    protected $_usu_Estado ;
    protected $_usu_Rol ;    
    protected $_usu_FechaCreacion;
    protected $_usu_FechaActualizacion;
    
    protected $_usu_NombreRol ;
    protected $_usu_idContibuyente;

    protected $_tabla = 'conf_usuarios';
    protected $_primario = 'usu_Id';
    
    protected $_mapa = array(
        'usu_Id' => array('tipodato' => 'integer'),
        'usu_NumeroDocumento' => array('tipodato' => 'varchar'),
        'usu_IdTipoDocumento' => array('tipodato' => 'integer'),
        'usu_Nombres' => array('tipodato' => 'varchar'),
        'usu_Apellidos' => array('tipodato' => 'varchar'),
        'usu_Telefono' => array('tipodato' => 'varchar'),
        'usu_Direccion' =>  array('tipodato' => 'varchar'),
        'usu_Usuario' => array('tipodato' => 'varchar'),
        'usu_Correo' => array('tipodato' => 'varchar'),
        'usu_Estado' => array('tipodato' => 'integer'),
        'usu_Password' => array('tipodato' => 'clave'),
        'usu_Rol' => array('tipodato' => 'integer'),
        'usu_FechaCreacion' => array('tipodato' => 'varchar'),
        'usu_FechaActualizacion' => array('tipodato' => 'varchar'),
        'usu_NombreRol' => array('tipodato' => 'integer','sql' => '(select rol.rol_Nombre from conf_rol as rol where rol.rol_Id = conf_usuarios.usu_Rol)'),
        'usu_idContibuyente' => array('tipodato' => 'integer','sql' => '(select conn.ind_Id from ind_contribuyentes as conn where conn.ind_NumeroIdentificacion = conf_usuarios.usu_NumeroDocumento)')
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_usu_Id() {
        return $this->_usu_Id;
    }

    function get_usu_NumeroDocumento() {
        return $this->_usu_NumeroDocumento;
    }

    function get_usu_IdTipoDocumento() {
        return $this->_usu_IdTipoDocumento;
    }

    function get_usu_Nombres() {
        return $this->_usu_Nombres;
    }
    
    function get_usu_Apellidos() {
        return $this->_usu_Apellidos;
    }
    
    function get_usu_Telefono() {
        return $this->_usu_Telefono;
    }

    function get_usu_Direccion() {
        return $this->_usu_Direccion;
    }

    function get_usu_Usuario() {
        return $this->_usu_Usuario;
    }

    function get_usu_Correo() {
        return $this->_usu_Correo;
    }

    function get_usu_Password() {
        return $this->_usu_Password;
    }

    function get_usu_Estado() {
        return $this->_usu_Estado;
    }

    function get_usu_Rol() {
        return $this->_usu_Rol;
    }

    function get_usu_FechaCreacion() {
        return $this->_usu_FechaCreacion;
    }

    function get_usu_FechaActualizacion() {
        return $this->_usu_FechaActualizacion;
    }

    function get_usu_NombreRol() {
        return $this->_usu_NombreRol;
    }

    function get_usu_idContibuyente() {
        return $this->_usu_idContibuyente;
    }

    function set_usu_Id($_usu_Id) {
        $this->_usu_Id = $_usu_Id;
    }

    function set_usu_NumeroDocumento($_usu_NumeroDocumento) {
        $this->_usu_NumeroDocumento = $_usu_NumeroDocumento;
    }

    function set_usu_IdTipoDocumento($_usu_IdTipoDocumento) {
        $this->_usu_IdTipoDocumento = $_usu_IdTipoDocumento;
    }

    function set_usu_Nombres($_usu_Nombres) {
        $this->_usu_Nombres = $_usu_Nombres;
    }

    function set_usu_Apellidos($_usu_Apellidos) {
        $this->_usu_Apellidos = $_usu_Apellidos;
    }

    function set_usu_Telefono($_usu_Telefono) {
        $this->_usu_Telefono = $_usu_Telefono;
    }
    function set_usu_Direccion($_usu_Direccion) {
        $this->_usu_Direccion = $_usu_Direccion;
    }
    function set_usu_Usuario($_usu_Usuario) {
        $this->_usu_Usuario = $_usu_Usuario;
    }

    function set_usu_Correo($_usu_Correo) {
        $this->_usu_Correo = $_usu_Correo;
    }

    function set_usu_Password($_usu_Password) {
        $this->_usu_Password = $_usu_Password;
    }

    function set_usu_Estado($_usu_Estado) {
        $this->_usu_Estado = $_usu_Estado;
    }

    function set_usu_Rol($_usu_Rol) {
        $this->_usu_Rol = $_usu_Rol;
    }

    function set_usu_FechaCreacion($_usu_FechaCreacion) {
        $this->_usu_FechaCreacion = $_usu_FechaCreacion;
    }

    function set_usu_FechaActualizacion($_usu_FechaActualizacion) {
        $this->_usu_FechaActualizacion = $_usu_FechaActualizacion;
    }

    function set_usu_NombreRol($_usu_NombreRol) {
        $this->_usu_NombreRol = $_usu_NombreRol;
    }

    function set_usu_idContibuyente($_usu_idContibuyente) {
        $this->_usu_idContibuyente = $_usu_idContibuyente;
    } 
    
}
