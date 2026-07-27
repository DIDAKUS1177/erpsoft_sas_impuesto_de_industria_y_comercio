<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_Usuario extends \predial\DAOGeneral {
    
    protected $_usu_Id ;
    protected $_usu_NumeroDocumento ;
    protected $_usu_Nombre ;
    protected $_usu_Usuario ;
    protected $_usu_Correo ;
    protected $_usu_Password ;
    protected $_usu_Estado ;
    protected $_usu_Rol ;
    
    protected $_usu_NombreRol ;

    protected $_tabla = 'conf_usuario';
    protected $_primario = 'usu_Id';
    
    protected $_mapa = array(
        'usu_Id' => array('tipodato' => 'integer'),
        'usu_NumeroDocumento' => array('tipodato' => 'varchar'),
        'usu_Nombre' => array('tipodato' => 'varchar'),
        'usu_Usuario' => array('tipodato' => 'varchar'),
        'usu_Correo' => array('tipodato' => 'varchar'),
        'usu_Estado' => array('tipodato' => 'integer'),
        'usu_Password' => array('tipodato' => 'clave'),
        'usu_Rol' => array('tipodato' => 'integer'),
        'usu_NombreRol' => array('tipodato' => 'integer','sql' => '(select rol.rol_Nombre from  conf_rol as rol where rol.rol_Id = conf_usuario.usu_Rol)'),
        
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

    function get_usu_Nombre() {
        return $this->_usu_Nombre;
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

    function get_usu_NombreRol() {
        return $this->_usu_NombreRol;
    }
    
    function set_usu_Id($_usu_Id) {
        $this->_usu_Id = $_usu_Id;
    }

    function set_usu_NumeroDocumento($_usu_NumeroDocumento) {
        $this->_usu_NumeroDocumento = $_usu_NumeroDocumento;
    }

    function set_usu_Nombre($_usu_Nombre) {
        $this->_usu_Nombre = $_usu_Nombre;
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

    function set_usu_NombreRol($_usu_NombreRol) {
        $this->_usu_NombreRol = $_usu_NombreRol;
    }

   
}
