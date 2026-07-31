<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_Logs extends \predial\DAOGeneral {
    
    protected $_log_Id ;
    protected $_log_IdUsuario ;
    protected $_log_IdModulo ;
    protected $_log_IdSubModulo ;
    protected $_log_IdRegistro;
    protected $_log_Fecha ;

    protected $_strNombreModulo;
    protected $_strNombreSubModulo;
    protected $_strNombreUsuario;

    protected $_tabla = 'conf_log';
    protected $_primario = 'log_Id';
    
    protected $_mapa = array(
        'log_Id' => array('tipodato' => 'integer'),
        'log_IdUsuario' => array('tipodato' => 'integer'),
        'log_IdModulo' => array('tipodato' => 'integer'),
        'log_IdSubModulo' => array('tipodato' => 'integer'),
        'log_IdRegistro' => array('tipodato' => 'integer'),
        'log_Fecha' => array('tipodato' => 'varchar'),
        'strNombreModulo' => array('tipodato' => 'integer','sql' => '(select mod.mod_Nombre from  conf_modulo as mod where mod.mod_Id = conf_log.log_IdModulo)'),
        'strNombreSubModulo' => array('tipodato' => 'integer','sql' => '(select submod.subMod_Nombre from  conf_submodulo as submod where submod.subMod_Id = conf_log.log_IdSubModulo)'),
        'strNombreUsuario' => array('tipodato' => 'integer','sql' => '(select usu.usu_Nombre from  conf_usuario as usu where usu.usu_Id = conf_log.log_IdUsuario)')
    );
   
    public function __construct() {
        parent::__construct();
    }
    
    function get_log_Id() {
        return $this->_log_Id;
    }

    function get_log_IdModulo() {
        return $this->_log_IdModulo;
    }
    
    function get_log_IdSubModulo() {
        return $this->_log_IdSubModulo;
    }

    function get_log_IdUsuario() {
        return $this->_log_IdUsuario;
    }
    
    function get_log_IdRegistro() {
        return $this->_log_IdRegistro;
    }

    function get_log_Fecha() {
        return $this->_log_Fecha;
    }
    
    function get_strNombreModulo() {
        return $this->_strNombreModulo;
    }

    function get_strNombreSubModulo() {
        return $this->_strNombreSubModulo;
    }

    function get_strNombreUsuario() {
        return $this->_strNombreUsuario;
    }
 
    function set_log_Id($_log_Id) {
        $this->_id = $_log_Id;
    }

    function set_log_IdModulo($_log_IdModulo) {
        $this->_log_IdModulo = $_log_IdModulo;
    }
    
    function set_log_IdSubModulo($_log_IdSubModulo) {
        $this->_log_IdSubModulo = $_log_IdSubModulo;
    }   
    
    function set_log_IdUsuario($_log_IdUsuario) {
        $this->_log_IdUsuario = $_log_IdUsuario;
    }
    
    function set_log_Fecha($_log_Fecha) {
        $this->_log_Fecha = $_log_Fecha;
    }

    function set_log_IdRegistro($_log_IdRegistro) {
        $this->_log_IdRegistro = $_log_IdRegistro;
    }
    
    function set_strNombreModulo($_strNombreModulo) {
        $this->_strNombreModulo = $_strNombreModulo;
    }

    function set_strNombreSubModulo($_strNombreSubModulo) {
        $this->_strNombreSubModulo = $_strNombreSubModulo;
    }

    function set_strNombreUsuario($_strNombreUsuario) {
        $this->_strNombreUsuario = $_strNombreUsuario;
    }
}
