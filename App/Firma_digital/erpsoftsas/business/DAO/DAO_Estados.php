<?php
namespace erpsoftsas;
include_once 'class.DAO.php';

class DAO_Estados extends \erpsoftsas\DAOGeneral {
    
    protected $_est_Id;
    protected $_est_Nombre ;
    protected $_est_Descripcion ;
    protected $_est_Color ;

    protected $_est_Estado ;

    protected $_tabla = 'estados';
    protected $_primario = 'est_Id';
    
    protected $_mapa = array(
        'est_Id' => array('tipodato' => 'integer'),
        'est_Nombre' => array('tipodato' => 'varchar'),
        'est_Descripcion' => array('tipodato' => 'varchar'),
        'est_Color' => array('tipodato' => 'varchar'),
        'est_Estado' => array('tipodato' => 'integer'),
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    
    function get_est_Id() {
        return $this->_est_Id;
    }


    function get_est_Nombre() {
        return $this->_est_Nombre;
    }


    function get_est_Descripcion() {
        return $this->_est_Descripcion;
    }

    function get_est_Color() {
        return $this->_est_Color;
    }

    function get_est_Estado() {
        return $this->_est_Estado;
    }
    
    function set_est_Id($_est_Id) {
        $this->_est_Id = $_est_Id;
    }


    function set_est_Nombre($_est_Nombre) {
        $this->_est_Nombre = $_est_Nombre;
    }


    function set_est_Descripcion($_est_Descripcion) {
        $this->_est_Descripcion = $_est_Descripcion;
    }

    function set_est_Color($_est_Color) {
        $this->_est_Color = $_est_Color;
    }
    
    function set_est_Estado($_est_Estado) {
        $this->_est_Estado = $_est_Estado;
    }
}
