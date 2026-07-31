<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_Exogenas extends \predial\DAOGeneral {

    protected $_exo_Id;
    protected $_exo_IdUsuario;
    protected $_exo_IdTipoDocumento;
    protected $_exo_Anio;
    protected $_exo_Observaciones;
    protected $_exo_estado;
    protected $_exo_FechaCreacion;

    protected $_strNombre;
    protected $_strCedula;

    protected $_tabla = 'exogenas';
    protected $_primario = 'exo_Id';

    protected $_mapa = array(
        'exo_Id' => array('tipodato' => 'integer'),
        'exo_IdUsuario' => array('tipodato' => 'integer'),
        'exo_IdTipoDocumento' => array('tipodato' => 'integer'),
        'exo_Anio' => array('tipodato' => 'integer'),
        'exo_Observaciones' => array('tipodato' => 'varchar'),
        'exo_estado' => array('tipodato' => 'integer'),
        'exo_FechaCreacion' => array('tipodato' => 'datetime'),

        'strNombre' => array('tipodato' => 'integer','sql' => '(select usu.usu_Nombre from conf_usuario as usu where usu.usu_Id = exogenas.exo_IdUsuario)'),
        'strCedula' => array('tipodato' => 'integer','sql' => '(select usu.usu_NumeroDocumento from conf_usuario as usu where usu.usu_Id = exogenas.exo_IdUsuario)'),
    );

    public function __construct() {
        parent::__construct();
    }

    function get_exo_Id() { return $this->_exo_Id; }
    function get_exo_IdUsuario() { return $this->_exo_IdUsuario; }
    function get_exo_IdTipoDocumento() { return $this->_exo_IdTipoDocumento; }
    function get_exo_Anio() { return $this->_exo_Anio; }
    function get_exo_Observaciones() { return $this->_exo_Observaciones; }

    function get_exo_estado() { return $this->_exo_estado; }
    function get_exo_FechaCreacion() { return $this->_exo_FechaCreacion; }

    function get_strNombre() { return $this->_strNombre; }
    function get_strCedula() { return $this->_strCedula; }

    function set_exo_Id($value) { $this->_exo_Id = $value; }
    function set_exo_IdUsuario($value) { $this->_exo_IdUsuario = $value; }
    function set_exo_IdTipoDocumento($value) { $this->_exo_IdTipoDocumento = $value; }
    function set_exo_Anio($value) { $this->_exo_Anio = $value; }

    function set_exo_Observaciones($value) { $this->_exo_Observaciones = $value; }

    function set_exo_estado($value) { $this->_exo_estado = $value; }
    function set_exo_FechaCreacion($value) { $this->_exo_FechaCreacion = $value; }
    function set_strNombre($value) { $this->_strNombre = $value; }
    function set_strCedula($value) { $this->_strCedula = $value; }  
}
