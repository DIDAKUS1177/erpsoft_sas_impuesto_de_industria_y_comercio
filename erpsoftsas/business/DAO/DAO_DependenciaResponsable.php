<?php
namespace erpsoftsas;
include_once 'class.DAO.php';

class DAO_DependenciaResponsable extends \erpsoftsas\DAOGeneral {
    
    protected $_deres_Id;
    protected $_deres_IdResponsable ;
    protected $_deres_IdDependencia ;

    protected $_tabla = 'dependencia_responsable';
    protected $_primario = 'deres_Id';
    
    protected $_mapa = array(
        'deres_Id' => array('tipodato' => 'integer'),
        'deres_IdResponsable' => array('tipodato' => 'varchar'),
        'deres_IdDependencia' => array('tipodato' => 'varchar')
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    
    function get_deres_Id() {
        return $this->_deres_Id;
    }


    function get_deres_IdResponsable() {
        return $this->_deres_IdResponsable;
    }


    function get_deres_IdDependencia() {
        return $this->_deres_IdDependencia;
    }


    
    function set_deres_Id($_deres_Id) {
        $this->_deres_Id = $_deres_Id;
    }


    function set_deres_IdResponsable($_deres_IdResponsable) {
        $this->_deres_IdResponsable = $_deres_IdResponsable;
    }


    function set_deres_IdDependencia($_deres_IdDependencia) {
        $this->_deres_IdDependencia = $_deres_IdDependencia;
    }

    
}
