<?php
namespace erpsoftsas;
include_once 'class.DAO.php';

class DAO_EstadosTiposPeticiones extends \erpsoftsas\DAOGeneral {
    
    protected $_estipe_Id ;
    protected $_estipe_IdTipoPeticion ;
    protected $_estipe_IdEstado ;
    protected $_estipe_OrdenProceso ;

    protected $_strNombreEstado ;

    protected $_tabla = 'estados_tipos_peticiones';
    protected $_primario = 'estipe_Id';
    
    protected $_mapa = array(
        'estipe_Id' => array('tipodato' => 'integer'),
        'estipe_IdTipoPeticion' => array('tipodato' => 'integer'),
        'estipe_IdEstado' => array('tipodato' => 'integer'),
        'estipe_OrdenProceso' => array('tipodato' => 'integer'),
        'strNombreEstado' => array('tipodato' => 'integer','sql' => '(select esta.est_Nombre from estados as esta where esta.est_Id = estados_tipos_peticiones.estipe_IdEstado)'),
    );   
    
    public function __construct() {
        parent::__construct();
    }

    function get_estipe_Id() {
        return $this->_estipe_Id;
    }


    function get_estipe_IdTipoPeticion() {
        return $this->_estipe_IdTipoPeticion;
    }


    function get_estipe_IdEstado() {
        return $this->_estipe_IdEstado;
    }


    function get_estipe_OrdenProceso() {
        return $this->_estipe_OrdenProceso;
    }

    function get_strNombreEstado() {
        return $this->_strNombreEstado;
    }  
    
    function set_estipe_Id($_estipe_Id) {
        $this->_estipe_Id = $_estipe_Id;
    }


    function set_estipe_IdTipoPeticion($_estipe_IdTipoPeticion) {
        $this->_estipe_IdTipoPeticion = $_estipe_IdTipoPeticion;
    }


    function set_estipe_IdEstado($_estipe_IdEstado) {
        $this->_estipe_IdEstado = $_estipe_IdEstado;
    }

    function set_estipe_OrdenProceso($_estipe_OrdenProceso) {
        $this->_estipe_OrdenProceso = $_estipe_OrdenProceso;
    }

    function set_strNombreEstado($_strNombreEstado) {
        $this->_strNombreEstado = $_strNombreEstado;
    }
    
}
