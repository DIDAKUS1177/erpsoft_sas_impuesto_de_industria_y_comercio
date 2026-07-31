<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_Resolucion extends \predial\DAOGeneral {
    
    protected $_reso_Id ;
    protected $_reso_IdTipoDocumento ;
    protected $_reso_Numero ;
    protected $_reso_Prefijo ;
    protected $_reso_NumeroInicial ;
    protected $_reso_NumeroFinal ;
    protected $_reso_Contador ;
    protected $_reso_FechaAutorizacion ;
    protected $_reso_FechaIngreso ;
    protected $_reso_FechaVencimiento ;
    protected $_reso_Estado ;
    protected $_reso_FechaCreacion ;
    
    protected $_tabla = 'conf_resoluciones';
    protected $_primario = 'reso_Id';
    
    protected $_mapa = array(
        'reso_Id' => array('tipodato' => 'integer'),
        'reso_IdTipoDocumento' => array('tipodato' => 'integer'),
        'reso_Numero' => array('tipodato' => 'integer'),
        'reso_Prefijo' => array('tipodato' => 'varchar'),
        'reso_NumeroInicial' => array('tipodato' => 'integer'),
        'reso_NumeroFinal' => array('tipodato' => 'integer'),
        'reso_Contador' => array('tipodato' => 'integer'),
        'reso_FechaAutorizacion' => array('tipodato' => 'varchar'),
        'reso_FechaIngreso' => array('tipodato' => 'varchar'),
        'reso_FechaVencimiento' => array('tipodato' => 'varchar'),
        'reso_Estado' => array('tipodato' => 'integer'),
        'reso_FechaCreacion' => array('tipodato' => 'varchar'),
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_reso_Id() {
        return $this->_reso_Id;
    }

    function get_reso_IdTipoDocumento() {
        return $this->_reso_IdTipoDocumento;
    }

    function get_reso_Numero() {
        return $this->_reso_Numero;
    }

    function get_reso_Prefijo() {
        return $this->_reso_Prefijo;
    }

    function get_reso_NumeroInicial() {
        return $this->_reso_NumeroInicial;
    }

    function get_reso_NumeroFinal() {
        return $this->_reso_NumeroFinal;
    }
    
    function get_reso_Contador() {
        return $this->_reso_Contador;
    }
    
    function get_reso_FechaAutorizacion() {
        return $this->_reso_FechaAutorizacion;
    }
    
    function get_reso_FechaIngreso() {
        return $this->_reso_FechaIngreso;
    }
    
    function get_reso_FechaVencimiento() {
        return $this->_reso_FechaVencimiento;
    }
    
    function get_reso_Estado() {
        return $this->_reso_Estado;
    }

    function get_reso_FechaCreacion() {
        return $this->_reso_FechaCreacion;
    }
    



    function set_reso_Id($_reso_Id) {
        $this->_reso_Id = $_reso_Id;
    }

    function set_reso_IdTipoDocumento($_reso_IdTipoDocumento) {
        $this->_reso_IdTipoDocumento = $_reso_IdTipoDocumento;
    }
    
    function set_reso_Numero($_reso_Numero) {
        $this->_reso_Numero = $_reso_Numero;
    }

    function set_reso_Prefijo($_reso_Prefijo) {
        $this->_reso_Prefijo = $_reso_Prefijo;
    }

    function set_reso_NumeroInicial($_reso_NumeroInicial) {
        $this->_reso_NumeroInicial = $_reso_NumeroInicial;
    }
    
    function set_reso_NumeroFinal($_reso_NumeroFinal) {
        $this->_reso_NumeroFinal = $_reso_NumeroFinal;
    }

    function set_reso_Contador($_reso_Contador) {
        $this->_reso_Contador = $_reso_Contador;
    }

    function set_reso_FechaAutorizacion($_reso_FechaAutorizacion) {
        $this->_reso_FechaAutorizacion = $_reso_FechaAutorizacion;
    }
    

    function set_reso_FechaIngreso($_reso_FechaIngreso) {
        $this->_reso_FechaIngreso = $_reso_FechaIngreso;
    }

    function set_reso_FechaVencimiento($_reso_FechaVencimiento) {
        $this->_reso_FechaVencimiento = $_reso_FechaVencimiento;
    }
    
    function set_reso_Estado($_reso_Estado) {
        $this->_reso_Estado = $_reso_Estado;
    }

    function set_reso_FechaCreacion($_reso_FechaCreacion) {
        $this->_reso_FechaCreacion = $_reso_FechaCreacion;
    }

}
