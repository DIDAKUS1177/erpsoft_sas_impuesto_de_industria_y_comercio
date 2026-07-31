<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_TiposDocumentos extends \predial\DAOGeneral {

    protected $_tip_Id;
    protected $_tip_Nombre;
    protected $_tip_Estado;
    protected $_tip_FechaCreacion;

    protected $_tabla = 'tipos_documentos';
    protected $_primario = 'tip_Id';

    protected $_mapa = array(
        'tip_Id' => array('tipodato' => 'integer'),
        'tip_Nombre' => array('tipodato' => 'varchar'),
        'tip_Estado' => array('tipodato' => 'integer'),
        'tip_FechaCreacion' => array('tipodato' => 'datetime'),
    );

    public function __construct() {
        parent::__construct();
    }

    function get_tip_Id() { return $this->_tip_Id; }
    function get_tip_Nombre() { return $this->_tip_Nombre; }
    function get_tip_Estado() { return $this->_tip_Estado; }
    function get_tip_FechaCreacion() { return $this->_tip_FechaCreacion; }

    function set_tip_Id($value) { $this->_tip_Id = $value; }
    function set_tip_Nombre($value) { $this->_tip_Nombre = $value; }
    function set_tip_Estado($value) { $this->_tip_Estado = $value; }
    function set_tip_FechaCreacion($value) { $this->_tip_FechaCreacion = $value; }
}
