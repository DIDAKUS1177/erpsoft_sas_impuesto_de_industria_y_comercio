<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_PrediosFacMorososGenerados extends \predial\DAOGeneral {
    
    protected $_pre_Id ;
    protected $_pre_IdUsuario ;
    protected $_pre_IdDirector ;
    protected $_pre_IdPredio ;
    protected $_pre_CodigoPredio ;
    protected $_pre_Anio ;
    protected $_pre_DiaCreacion ;  
    protected $_pre_MesCreacion ;  
    protected $_pre_AnioCreacion ;  
    protected $_pre_FechaCreacion ;    
    
    protected $_tabla = 'pre_prediosfacmorososgenerados';
    protected $_primario = 'pre_Id';
    
    protected $_mapa = array(
        'pre_Id' => array('tipodato' => 'integer'),
        'pre_IdUsuario' => array('tipodato' => 'integer'),
        'pre_IdDirector' => array('tipodato' => 'integer'),
        'pre_IdPredio' => array('tipodato' => 'integer'),
        'pre_CodigoPredio' => array('tipodato' => 'varchar'),
        'pre_Anio' => array('tipodato' => 'integer'),
        'pre_DiaCreacion' => array('tipodato' => 'varchar'),
        'pre_MesCreacion' => array('tipodato' => 'varchar'),
        'pre_AnioCreacion' => array('tipodato' => 'varchar'),
        'pre_FechaCreacion' => array('tipodato' => 'varchar'),
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_pre_Id() {
        return $this->_pre_Id;
    }

    function get_pre_IdUsuario() {
        return $this->_pre_IdUsuario;
    }

    function get_pre_IdDirector() {
        return $this->_pre_IdDirector;
    }

    function get_pre_IdPredio() {
        return $this->_pre_IdPredio;
    }    
    
    function get_pre_CodigoPredio() {
        return $this->_pre_CodigoPredio;
    }    
    
    function get_pre_Anio() {
        return $this->_pre_Anio;
    }    

    function get_pre_DiaCreacion() {
        return $this->_pre_DiaCreacion;
    }

    function get_pre_MesCreacion() {
        return $this->_pre_MesCreacion;
    }

    function get_pre_AnioCreacion() {
        return $this->_pre_AnioCreacion;
    }

    function get_pre_FechaCreacion() {
        return $this->_pre_FechaCreacion;
    }

    function set_pre_Id($_pre_Id) {
        $this->_pre_Id = $_pre_Id;
    }

    function set_pre_IdUsuario($_pre_IdUsuario) {
        $this->_pre_IdUsuario = $_pre_IdUsuario;
    }

    function set_pre_IdDirector($_pre_IdDirector) {
        $this->_pre_IdDirector = $_pre_IdDirector;
    }

    function set_pre_IdPredio($_pre_IdPredio) {
        $this->_pre_IdPredio = $_pre_IdPredio;
    }

    function set_pre_CodigoPredio($_pre_CodigoPredio) {
        $this->_pre_CodigoPredio = $_pre_CodigoPredio;
    }

    function set_pre_Anio($_pre_Anio) {
        $this->_pre_Anio = $_pre_Anio;
    }
    
    function set_pre_DiaCreacion($_pre_DiaCreacion) {
        $this->_pre_DiaCreacion = $_pre_DiaCreacion;
    }
    
    function set_pre_MesCreacion($_pre_MesCreacion) {
        $this->_pre_MesCreacion = $_pre_MesCreacion;
    }
    
    function set_pre_AnioCreacion($_pre_AnioCreacion) {
        $this->_pre_AnioCreacion = $_pre_AnioCreacion;
    }

    function set_pre_FechaCreacion($_pre_FechaCreacion) {
        $this->_pre_FechaCreacion = $_pre_FechaCreacion;
    }

}
