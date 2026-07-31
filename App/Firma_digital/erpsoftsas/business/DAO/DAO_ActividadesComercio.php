<?php
namespace erpsoftsas;

include_once 'class.DAO.php';

class DAO_ActividadesComercio extends \erpsoftsas\DAOGeneral 
{
    // Propiedades mapeadas
    protected $_acc_Id;
    protected $_acc_Anio;
    protected $_acc_Codigo;
    protected $_acc_Nombre;
    protected $_acc_Tarifa;
    protected $_acc_GrupoTarifa;
    protected $_acc_Exento;
    protected $_acc_Estado;
    protected $_acc_FechaCreacion;
    protected $_acc_FechaActualizacion;

    protected $_acc_TarifaConsulta; // Propiedad adicional para formato de consulta

    // Información de tabla y campo primario
    protected $_tabla = 'ind_actividadescomercio';
    protected $_primario = 'acc_Id';

    // Mapa de columnas y tipos
    protected $_mapa = array(
        'acc_Id'                => array('tipodato' => 'integer'),
        'acc_Codigo'            => array('tipodato' => 'varchar'),
        'acc_Anio'              => array('tipodato' => 'integer'),
        'acc_Nombre'            => array('tipodato' => 'varchar'),
        'acc_Tarifa'            => array('tipodato' => 'varchar'),
        'acc_GrupoTarifa'       => array('tipodato' => 'integer'),
        'acc_Exento'            => array('tipodato' => 'integer'),
        'acc_Estado'            => array('tipodato' => 'integer'),
        'acc_FechaCreacion'     => array('tipodato' => 'varchar'),
        'acc_FechaActualizacion'=> array('tipodato' => 'varchar'),
        'acc_TarifaConsulta'    => array('tipodato' => 'varchar','sql' => "CONVERT(VARCHAR(20), acc_Tarifa)")
    );
    
    public function __construct() {
        parent::__construct();
    }

    // Getters y Setters    
    public function get_acc_Id() {
        return $this->_acc_Id;
    }
    public function set_acc_Id($_acc_Id) {
        $this->_acc_Id = $_acc_Id;
    }

    public function get_acc_Nombre() {
        return $this->_acc_Nombre;
    }
    public function set_acc_Nombre($_acc_Nombre) {
        $this->_acc_Nombre = $_acc_Nombre;
    }

    public function get_acc_Codigo() {
        return $this->_acc_Codigo;
    }

    public function set_acc_Codigo($_acc_Codigo) {
        $this->_acc_Codigo = $_acc_Codigo;
    }

    public function get_acc_Anio() {
        return $this->_acc_Anio;
    }
    
    public function set_acc_Anio($_acc_Anio) {
        $this->_acc_Anio = $_acc_Anio;
    }

    public function get_acc_Tarifa() {
        return $this->_acc_Tarifa;
    }
    public function set_acc_Tarifa($_acc_Tarifa) {
        $this->_acc_Tarifa = $_acc_Tarifa;
    }

    public function get_acc_GrupoTarifa() {
        return $this->_acc_GrupoTarifa;
    }
    public function set_acc_GrupoTarifa($_acc_GrupoTarifa) {
        $this->_acc_GrupoTarifa = $_acc_GrupoTarifa;
    }

    public function get_acc_Exento() {
        return $this->_acc_Exento;
    }
    public function set_acc_Exento($_acc_Exento) {
        $this->_acc_Exento = $_acc_Exento;
    }

    public function get_acc_Estado() {
        return $this->_acc_Estado;
    }
    public function set_acc_Estado($_acc_Estado) {
        $this->_acc_Estado = $_acc_Estado;
    }

    public function get_acc_FechaCreacion() {
        return $this->_acc_FechaCreacion;
    }
    public function set_acc_FechaCreacion($_acc_FechaCreacion) {
        $this->_acc_FechaCreacion = $_acc_FechaCreacion;
    }

    public function get_acc_FechaActualizacion() {
        return $this->_acc_FechaActualizacion;
    }
    public function set_acc_FechaActualizacion($_acc_FechaActualizacion) {
        $this->_acc_FechaActualizacion = $_acc_FechaActualizacion;
    }

    public function get_acc_TarifaConsulta() {
        return $this->_acc_TarifaConsulta;
    }
    public function set_acc_TarifaConsulta($_acc_TarifaConsulta) {
        $this->_acc_TarifaConsulta = $_acc_TarifaConsulta;
    }
}