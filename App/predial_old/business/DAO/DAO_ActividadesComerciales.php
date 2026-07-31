<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_ActividadesComerciales extends \predial\DAOGeneral {

    protected $_aco_Id;
    protected $_aco_Nombre;
    protected $_aco_Codigo;
    protected $_aco_Tarifa;
    protected $_aco_Estado;
    protected $_aco_FechaCreacion;

    protected $_tabla = 'actividades_comerciales';
    protected $_primario = 'aco_Id';

    protected $_mapa = array(
        'aco_Id' => array('tipodato' => 'integer'),
        'aco_Nombre' => array('tipodato' => 'varchar'),
        'aco_Codigo' => array('tipodato' => 'integer'),
        'aco_Tarifa' => array('tipodato' => 'integer'),
        'aco_Estado' => array('tipodato' => 'integer'),
        'aco_FechaCreacion' => array('tipodato' => 'datetime'),
    );

    public function __construct() {
        parent::__construct();
    }

    // Getters
    function get_aco_Id() { return $this->_aco_Id; }
    function get_aco_Nombre() { return $this->_aco_Nombre; }
    function get_aco_Codigo() { return $this->_aco_Codigo; }
    function get_aco_Tarifa() { return $this->_aco_Tarifa; }
    function get_aco_Estado() { return $this->_aco_Estado; }
    function get_aco_FechaCreacion() { return $this->_aco_FechaCreacion; }

    // Setters
    function set_aco_Id($value) { $this->_aco_Id = $value; }
    function set_aco_Nombre($value) { $this->_aco_Nombre = $value; }
    function set_aco_Codigo($value) { $this->_aco_Codigo = $value; }
    function set_aco_Tarifa($value) { $this->_aco_Tarifa = $value; }
    function set_aco_Estado($value) { $this->_aco_Estado = $value; }
    function set_aco_FechaCreacion($value) { $this->_aco_FechaCreacion = $value; }
}
