<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_Configuracion extends \predial\DAOGeneral {
    
    protected $_con_Id ;
    protected $_con_NombreDirector ;
    protected $_con_Resolucion ;
    protected $_con_Estado ;
    protected $_con_FechaCreacion ;
    
    protected $_tabla = 'pre_configuracion';
    protected $_primario = 'con_Id';
    
    protected $_mapa = array(
        'con_Id' => array('tipodato' => 'integer'),
        'con_NombreDirector' => array('tipodato' => 'varchar'),
        'con_Resolucion' => array('tipodato' => 'varchar'),
        'con_Estado' => array('tipodato' => 'integer'),
        'con_FechaCreacion' => array('tipodato' => 'varchar'),
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_con_Id() {
        return $this->_con_Id;
    }

    function get_con_NombreDirector() {
        return $this->_con_NombreDirector;
    }

    function get_con_Resolucion() {
        return $this->_con_Resolucion;
    }

    function get_con_Estado(){
        return $this->_con_Estado;
    }

    function get_con_FechaCreacion(){
        return $this->_con_FechaCreacion;
    }

    function set_con_Id($_con_Id) {
        $this->_con_Id = $_con_Id;
    }

    function set_con_NombreDirector($_con_NombreDirector) {
        $this->_con_NombreDirector = $_con_NombreDirector;
    }

    function set_con_Resolucion($_con_Resolucion) {
        $this->_con_Resolucion = $_con_Resolucion;
    }

    function set_con_Estado($_con_Estado){
        $this->_con_Estado = $_con_Estado;
    }

    function set_con_FechaCreacion($_con_FechaCreacion){
        $this->_con_FechaCreacion = $_con_FechaCreacion;
    }
}
