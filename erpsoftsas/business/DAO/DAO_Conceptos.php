<?php
namespace erpsoftsas;

include_once 'class.DAO.php';

class DAO_Conceptos extends \erpsoftsas\DAOGeneral 
{
    // Propiedades mapeadas
    protected $_con_Id;
    protected $_con_Anio;
    protected $_con_Codigo;
    protected $_con_Nombre;
    protected $_con_Observaciones;
    protected $_con_Estado;
    protected $_con_FechaCreacion;
    protected $_con_FechaActualizacion;

    // Información de tabla y campo primario
    protected $_tabla = 'ind_conceptos';
    protected $_primario = 'con_Id';

    // Mapa de columnas y tipos
    protected $_mapa = array(
        'con_Id'                => array('tipodato' => 'integer'),
        'con_Anio'              => array('tipodato' => 'integer'),
        'con_Codigo'            => array('tipodato' => 'varchar'),
        'con_Nombre'            => array('tipodato' => 'varchar'),
        'con_Observaciones'     => array('tipodato' => 'varchar'),
        'con_Estado'            => array('tipodato' => 'integer'),
        'con_FechaCreacion'     => array('tipodato' => 'varchar'),
        'con_FechaActualizacion'=> array('tipodato' => 'varchar')
    );
    
    public function __construct() {
        parent::__construct();
    }

    // Getters y Setters    
    public function get_con_Id() {
        return $this->_con_Id;
    }
    public function set_con_Id($_con_Id) {
        $this->_con_Id = $_con_Id;
    }

    public function get_con_Anio() {
        return $this->_con_Anio;
    }

    public function set_con_Anio($_con_Anio) {
        $this->_con_Anio = $_con_Anio;
    }

    public function get_con_Codigo() {
        return $this->_con_Codigo;
    }
    public function set_con_Codigo($_con_Codigo) {
        $this->_con_Codigo = $_con_Codigo;
    }

    public function get_con_Nombre() {
        return $this->_con_Nombre;
    }
    public function set_con_Nombre($_con_Nombre) {
        $this->_con_Nombre = $_con_Nombre;
    }

    public function get_con_Observaciones() {
        return $this->_con_Observaciones;
    }
    public function set_con_Observaciones($_con_Observaciones) {
        $this->_con_Observaciones = $_con_Observaciones;
    }

    public function get_con_Estado() {
        return $this->_con_Estado;
    }
    public function set_con_Estado($_con_Estado) {
        $this->_con_Estado = $_con_Estado;
    }

    public function get_con_FechaCreacion() {
        return $this->_con_FechaCreacion;
    }
    public function set_con_FechaCreacion($_con_FechaCreacion) {
        $this->_con_FechaCreacion = $_con_FechaCreacion;
    }

    public function get_con_FechaActualizacion() {
        return $this->_con_FechaActualizacion;
    }
    public function set_con_FechaActualizacion($_con_FechaActualizacion) {
        $this->_con_FechaActualizacion = $_con_FechaActualizacion;
    }

}