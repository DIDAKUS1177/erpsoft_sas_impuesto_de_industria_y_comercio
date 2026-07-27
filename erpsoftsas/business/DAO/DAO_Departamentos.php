<?php
namespace erpsoftsas;
include_once 'class.DAO.php';

class DAO_Departamentos extends \erpsoftsas\DAOGeneral {
    
    protected $_dep_Id ;
    protected $_dep_Nombre ;
    protected $_dep_Codigo ;
    
    protected $_tabla = 'conf_departamentos';
    protected $_primario = 'dep_Id';
    
    protected $_mapa = array(
        'dep_Id' => array('tipodato' => 'integer'),
        'dep_Nombre' => array('tipodato' => 'varchar'),
        'dep_Codigo' => array('tipodato' => 'integer'),
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

    function get_dep_Codigo() {
        return $this->_dep_Codigo;
    }    

    function set_dep_Id($_dep_Id) {
        $this->_dep_Id = $_dep_Id;
    }

    function set_dep_Nombre($_dep_Nombre) {
        $this->_dep_Nombre = $_dep_Nombre;
    }

    function set_dep_Codigo($_dep_Codigo) {
        $this->_dep_Codigo = $_dep_Codigo;
    }

}
