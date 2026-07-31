<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_TipoPersona extends \predial\DAOGeneral {
    
    protected $_tip_Id ;
    protected $_tip_Descripcion ;
    protected $_tip_DIAN ;
    
    protected $_tabla = 'conf_tipo_persona';
    protected $_primario = 'tip_Id';
    
    protected $_mapa = array(
        'tip_Id' => array('tipodato' => 'integer'),
        'tip_Descripcion' => array('tipodato' => 'varchar'),
        'tip_DIAN' => array('tipodato' => 'varchar'),
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_tip_Id() {
        return $this->_tip_Id;
    }

    function get_tip_Descripcion() {
        return $this->_tip_Descripcion;
    }

    function get_tip_DIAN() {
        return $this->_tip_DIAN;
    }

    function set_tip_Id($_tip_Id) {
        $this->_tip_Id = $_tip_Id;
    }

    function set_tip_Descripcion($_tip_Descripcion) {
        $this->_tip_Descripcion = $_tip_Descripcion;
    }

    function set_tip_DIAN($_tip_DIAN) {
        $this->_tip_DIAN = $_tip_DIAN;
    }

}
