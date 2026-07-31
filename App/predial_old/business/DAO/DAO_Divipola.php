<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_Divipola extends \predial\DAOGeneral {

    protected $_divi_Id;
    protected $_divi_NombreMunicipio;
    protected $_divi_NombreDepartamento;
    protected $_divi_Codigo;
    protected $_divi_Estado;
    protected $_divi_FechaCreacion;

    protected $_tabla = 'divipola';
    protected $_primario = 'divi_Id';

    protected $_mapa = array(
        'divi_Id' => array('tipodato' => 'integer'),
        'divi_NombreMunicipio' => array('tipodato' => 'varchar'),
        'divi_NombreDepartamento' => array('tipodato' => 'varchar'),
        'divi_Codigo' => array('tipodato' => 'integer'),
        'divi_Estado' => array('tipodato' => 'integer'),
        'divi_FechaCreacion' => array('tipodato' => 'datetime'),
    );

    public function __construct() {
        parent::__construct();
    }

    function get_divi_Id() { return $this->_divi_Id; }
    function get_divi_NombreMunicipio() { return $this->_divi_NombreMunicipio; }
    function get_divi_NombreDepartamento() { return $this->_divi_NombreDepartamento; }
    function get_divi_Codigo() { return $this->_divi_Codigo; }
    function get_divi_Estado() { return $this->_divi_Estado; }
    function get_divi_FechaCreacion() { return $this->_divi_FechaCreacion; }

    function set_divi_Id($value) { $this->_divi_Id = $value; }
    function set_divi_NombreMunicipio($value) { $this->_divi_NombreMunicipio = $value; }
    function set_divi_NombreDepartamento($value) { $this->_divi_NombreDepartamento = $value; }
    function set_divi_Codigo($value) { $this->_divi_Codigo = $value; }
    function set_divi_Estado($value) { $this->_divi_Estado = $value; }
    function set_divi_FechaCreacion($value) { $this->_divi_FechaCreacion = $value; }
}
