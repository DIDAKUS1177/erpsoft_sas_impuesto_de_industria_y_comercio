<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_FormasPago extends \predial\DAOGeneral {
    
    protected $_forpa_Id ;
    protected $_forpa_Descripcion ;
    protected $_forpa_Estado ;
    protected $_forpa_Saldada ;
    protected $_forpa_FechaCreacion ;
    
    protected $_tabla = 'fac_formas_pago';
    protected $_primario = 'forpa_Id';
    
    protected $_mapa = array(
        'forpa_Id' => array('tipodato' => 'integer'),
        'forpa_Descripcion' => array('tipodato' => 'varchar'),
        'forpa_Estado' => array('tipodato' => 'integer'),
        'forpa_Saldada' => array('tipodato' => 'integer'),
        'forpa_FechaCreacion' => array('tipodato' => 'varchar'),
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_forpa_Id() {
        return $this->_forpa_Id;
    }

    function get_forpa_Descripcion() {
        return $this->_forpa_Descripcion;
    }

    function get_forpa_Estado() {
        return $this->_forpa_Estado;
    }

    function get_forpa_Saldada() {
        return $this->_forpa_Saldada;
    }

    function get_forpa_FechaCreacion() {
        return $this->_forpa_FechaCreacion;
    }

    function set_forpa_Id($_forpa_Id) {
        $this->_forpa_Id = $_forpa_Id;
    }

    function set_forpa_Descripcion($_forpa_Descripcion) {
        $this->_forpa_Descripcion = $_forpa_Descripcion;
    }

    function set_forpa_Estado($_forpa_Estado) {
        $this->_forpa_Estado = $_forpa_Estado;
    }

    function set_forpa_Saldada($_forpa_Saldada) {
        $this->_forpa_Saldada = $_forpa_Saldada;
    }

    function set_forpa_FechaCreacion($_forpa_FechaCreacion) {
        $this->_forpa_FechaCreacion = $_forpa_FechaCreacion;
    }

}
