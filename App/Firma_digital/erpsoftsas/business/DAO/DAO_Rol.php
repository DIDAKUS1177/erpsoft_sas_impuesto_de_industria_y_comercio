<?php
namespace erpsoftsas;
include_once 'class.DAO.php';

class DAO_Rol extends \erpsoftsas\DAOGeneral {
    
    protected $_rol_Id ;
    protected $_rol_Nombre ;
    protected $_rol_Descripcion ;
    protected $_rol_Estado ;
    protected $_rol_Fecha_Creacion ;
    
    protected $_tabla = 'conf_rol';
    protected $_primario = 'rol_Id';
    
    protected $_mapa = array(
        'rol_Id' => array('tipodato' => 'integer'),
        'rol_Nombre' => array('tipodato' => 'varchar'),
        'rol_Descripcion' => array('tipodato' => 'varchar'),
        'rol_Estado' => array('tipodato' => 'integer'),
        'rol_Fecha_Creacion' => array('tipodato' => 'varchar'),
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_rol_Id() {
        return $this->_rol_Id;
    }

    function get_rol_Nombre() {
        return $this->_rol_Nombre;
    }

    function get_rol_Descripcion() {
        return $this->_rol_Descripcion;
    }

    function get_rol_Estado() {
        return $this->_rol_Estado;
    }

    function get_rol_Fecha_Creacion() {
        return $this->_rol_Fecha_Creacion;
    }

    function set_rol_Id($_rol_Id) {
        $this->_rol_Id = $_rol_Id;
    }

    function set_rol_Nombre($_rol_Nombre) {
        $this->_rol_Nombre = $_rol_Nombre;
    }

    function set_rol_Descripcion($_rol_Descripcion) {
        $this->_rol_Descripcion = $_rol_Descripcion;
    }

    function set_rol_Estado($_rol_Estado) {
        $this->_rol_Estado = $_rol_Estado;
    }

    function set_rol_Fecha_Creacion($_rol_Fecha_Creacion) {
        $this->_rol_Fecha_Creacion = $_rol_Fecha_Creacion;
    }

}
