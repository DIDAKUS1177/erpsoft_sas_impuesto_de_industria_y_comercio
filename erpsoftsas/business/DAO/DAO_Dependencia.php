<?php
namespace erpsoftsas;
include_once 'class.DAO.php';

class DAO_Dependencia extends \erpsoftsas\DAOGeneral {
    
    protected $_dep_Id;
    protected $_dep_Nombre ;
    protected $_dep_Descripcion ;
    protected $_dep_Sigla ;
    protected $_dep_Codigo ;
    protected $_dep_IdResponsable ;
    protected $_dep_Estado ;

    protected $_strResponsable ;

    protected $_tabla = 'dependencia';
    protected $_primario = 'dep_Id';
    
    protected $_mapa = array(
        'dep_Id' => array('tipodato' => 'integer'),
        'dep_Nombre' => array('tipodato' => 'varchar'),
        'dep_Descripcion' => array('tipodato' => 'varchar'),
        'dep_Sigla' => array('tipodato' => 'varchar'),
        'dep_Codigo' => array('tipodato' => 'varchar'),
        'dep_IdResponsable' => array('tipodato' => 'varchar'),
        'dep_Estado' => array('tipodato' => 'integer'),
        'strResponsable' => array('tipodato' => 'varchar','sql' => '(select usu.usu_Nombre from conf_usuario as usu where usu.usu_Id = dependencia.dep_IdResponsable)')
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    
    function get_dep_Id() {
        return $this->_dep_Id;
    }


    function get_dep_Nombre() {
        return $this->_dep_Nombre;
    }


    function get_dep_Descripcion() {
        return $this->_dep_Descripcion;
    }

    function get_dep_Sigla() {
        return $this->_dep_Sigla;
    }

    function get_dep_Codigo() {
        return $this->_dep_Codigo;
    }

    function get_dep_IdResponsable() {
        return $this->_dep_IdResponsable;
    }
    
    function get_dep_Estado() {
        return $this->_dep_Estado;
    }

    function get_strResponsable() {
        return $this->_strResponsable;
    }
    
    function set_dep_Id($_dep_Id) {
        $this->_dep_Id = $_dep_Id;
    }

    function set_dep_Nombre($_dep_Nombre) {
        $this->_dep_Nombre = $_dep_Nombre;
    }

    function set_dep_Descripcion($_dep_Descripcion) {
        $this->_dep_Descripcion = $_dep_Descripcion;
    }

    function set_dep_Sigla($_dep_Sigla) {
        $this->_dep_Sigla = $_dep_Sigla;
    }

    function set_dep_Codigo($_dep_Codigo) {
        $this->_dep_Codigo = $_dep_Codigo;
    }

    function set_dep_IdResponsable($_dep_IdResponsable) {
        $this->_dep_IdResponsable = $_dep_IdResponsable;
    }

    function set_dep_Estado($_dep_Estado) {
        $this->_dep_Estado = $_dep_Estado;
    }

    function set_strResponsable($_strResponsable) {
        $this->_strResponsable = $_strResponsable;
    }

    

}
