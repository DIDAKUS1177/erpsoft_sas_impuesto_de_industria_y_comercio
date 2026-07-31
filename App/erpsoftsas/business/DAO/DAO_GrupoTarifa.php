<?php
namespace erpsoftsas;

include_once 'class.DAO.php';

class DAO_GrupoTarifa extends \erpsoftsas\DAOGeneral 
{
    // Propiedades mapeadas
    protected $_gru_Id;
    protected $_gru_Codigo;
    protected $_gru_Nombre;
    protected $_gru_Estado;
    protected $_gru_FechaCreacion;
    protected $_gru_FechaActualizacion;

    // Información de tabla y campo primario
    protected $_tabla = 'ind_grupotarifa';
    protected $_primario = 'gru_Id';

    // Mapa de columnas y tipos
    protected $_mapa = array(
        'gru_Id'                => array('tipodato' => 'integer'),
        'gru_Codigo'            => array('tipodato' => 'varchar'),
        'gru_Nombre'            => array('tipodato' => 'varchar'),
        'gru_Estado'            => array('tipodato' => 'integer'),
        'gru_FechaCreacion'     => array('tipodato' => 'varchar'),
        'gru_FechaActualizacion' => array('tipodato' => 'varchar')
    );
    
    public function __construct() {
        parent::__construct();
    }

    // Getters y Setters    
    public function get_gru_Id() {
        return $this->_gru_Id;
    }
    public function set_gru_Id($_gru_Id) {
        $this->_gru_Id = $_gru_Id;
    }

    public function get_gru_Codigo() {
        return $this->_gru_Codigo;
    }

    public function set_gru_Codigo($_gru_Codigo) {
        $this->_gru_Codigo = $_gru_Codigo;
    }

    public function get_gru_Nombre() {
        return $this->_gru_Nombre;
    }
    public function set_gru_Nombre($_gru_Nombre) {
        $this->_gru_Nombre = $_gru_Nombre;
    }

    public function get_gru_Estado() {
        return $this->_gru_Estado;
    }
    public function set_gru_Estado($_gru_Estado) {
        $this->_gru_Estado = $_gru_Estado;
    }

    public function get_gru_FechaCreacion() {
        return $this->_gru_FechaCreacion;
    }
    public function set_gru_FechaCreacion($_gru_FechaCreacion) {
        $this->_gru_FechaCreacion = $_gru_FechaCreacion;
    }

    public function get_gru_FechaActualizacion() {
        return $this->_gru_FechaActualizacion;
    }
    public function set_gru_FechaActualizacion($_gru_FechaActualizacion) {
        $this->_gru_FechaActualizacion = $_gru_FechaActualizacion;
    }

}