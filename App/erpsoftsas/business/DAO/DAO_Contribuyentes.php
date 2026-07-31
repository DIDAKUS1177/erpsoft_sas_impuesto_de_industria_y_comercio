<?php
namespace erpsoftsas;

include_once 'class.DAO.php';

class DAO_Contribuyentes extends \erpsoftsas\DAOGeneral 
{
    // Propiedades mapeadas
    protected $_ind_Id;
    protected $_ind_NumeroIdentificacion;
    protected $_ind_DV;
    protected $_ind_IdTipoDocumento;
    protected $_ind_PrimerNombre;
    protected $_ind_SegundoNombre;
    protected $_ind_PrimerApellido;
    protected $_ind_SegundoApellido;
    protected $_ind_Direccion;
    protected $_ind_IdCiudad;
    protected $_ind_Persona;
    protected $_ind_IdRegimen;
    protected $_ind_Telefono;
    protected $_ind_Email;
    protected $_ind_Estado;
    protected $_ind_FechaCreacion;
    protected $_ind_FechaActualizacion;

    // Información de tabla y campo primario
    protected $_tabla = 'ind_contribuyentes';
    protected $_primario = 'ind_Id';

    // Mapa de columnas y tipos
    protected $_mapa = array(
        'ind_Id'                  => array('tipodato' => 'integer'),
        'ind_NumeroIdentificacion'=> array('tipodato' => 'integer'),
        'ind_DV'                  => array('tipodato' => 'integer'),
        'ind_IdTipoDocumento'     => array('tipodato' => 'integer'),
        'ind_PrimerNombre'        => array('tipodato' => 'varchar'),
        'ind_SegundoNombre'       => array('tipodato' => 'varchar'),
        'ind_PrimerApellido'      => array('tipodato' => 'varchar'),
        'ind_SegundoApellido'     => array('tipodato' => 'varchar'),
        'ind_Direccion'           => array('tipodato' => 'varchar'),
        'ind_IdCiudad'            => array('tipodato' => 'integer'),
        'ind_Persona'             => array('tipodato' => 'integer'),
        'ind_IdRegimen'           => array('tipodato' => 'integer'),
        'ind_Telefono'            => array('tipodato' => 'integer'),
        'ind_Email'               => array('tipodato' => 'varchar'),
        'ind_Estado'              => array('tipodato' => 'integer'),
        'ind_FechaCreacion'       => array('tipodato' => 'varchar'),
        'ind_FechaActualizacion'  => array('tipodato' => 'varchar')
    );

    public function __construct() {
        parent::__construct();
    }

    // Getters y Setters    

    public function get_ind_Id() {
        return $this->_ind_Id;
    }
    public function set_ind_Id($_ind_Id) {
        $this->_ind_Id = $_ind_Id;
    }

    public function get_ind_NumeroIdentificacion() {
        return $this->_ind_NumeroIdentificacion;
    }
    public function set_ind_NumeroIdentificacion($_ind_NumeroIdentificacion) {
        $this->_ind_NumeroIdentificacion = $_ind_NumeroIdentificacion;
    }

    public function get_ind_DV() {
        return $this->_ind_DV;
    }
    public function set_ind_DV($_ind_DV) {
        $this->_ind_DV = $_ind_DV;
    }

    public function get_ind_IdTipoDocumento() {
        return $this->_ind_IdTipoDocumento;
    }
    public function set_ind_IdTipoDocumento($_ind_IdTipoDocumento) {
        $this->_ind_IdTipoDocumento = $_ind_IdTipoDocumento;
    }

    public function get_ind_PrimerNombre() {
        return $this->_ind_PrimerNombre;
    }
    public function set_ind_PrimerNombre($_ind_PrimerNombre) {
        $this->_ind_PrimerNombre = $_ind_PrimerNombre;
    }

    public function get_ind_SegundoNombre() {
        return $this->_ind_SegundoNombre;
    }
    public function set_ind_SegundoNombre($_ind_SegundoNombre) {
        $this->_ind_SegundoNombre = $_ind_SegundoNombre;
    }

    public function get_ind_PrimerApellido() {
        return $this->_ind_PrimerApellido;
    }
    public function set_ind_PrimerApellido($_ind_PrimerApellido) {
        $this->_ind_PrimerApellido = $_ind_PrimerApellido;
    }

    public function get_ind_SegundoApellido() {
        return $this->_ind_SegundoApellido;
    }
    public function set_ind_SegundoApellido($_ind_SegundoApellido) {
        $this->_ind_SegundoApellido = $_ind_SegundoApellido;
    }

    public function get_ind_Direccion() {
        return $this->_ind_Direccion;
    }
    public function set_ind_Direccion($_ind_Direccion) {
        $this->_ind_Direccion = $_ind_Direccion;
    }

    public function get_ind_IdCiudad() {
        return $this->_ind_IdCiudad;
    }
    public function set_ind_IdCiudad($_ind_IdCiudad) {
        $this->_ind_IdCiudad = $_ind_IdCiudad;
    }

    public function get_ind_Persona() {
        return $this->_ind_Persona;
    }
    public function set_ind_Persona($_ind_Persona) {
        $this->_ind_Persona = $_ind_Persona;
    }

    public function get_ind_IdRegimen() {
        return $this->_ind_IdRegimen;
    }
    public function set_ind_IdRegimen($_ind_IdRegimen) {
        $this->_ind_IdRegimen = $_ind_IdRegimen;
    }

    public function get_ind_Telefono() {
        return $this->_ind_Telefono;
    }
    public function set_ind_Telefono($_ind_Telefono) {
        $this->_ind_Telefono = $_ind_Telefono;
    }

    public function get_ind_Email() {
        return $this->_ind_Email;
    }
    public function set_ind_Email($_ind_Email) {
        $this->_ind_Email = $_ind_Email;
    }

    public function get_ind_Estado() {
        return $this->_ind_Estado;
    }
    public function set_ind_Estado($_ind_Estado) {
        $this->_ind_Estado = $_ind_Estado;
    }

    public function get_ind_FechaCreacion() {
        return $this->_ind_FechaCreacion;
    }
    public function set_ind_FechaCreacion($_ind_FechaCreacion) {
        $this->_ind_FechaCreacion = $_ind_FechaCreacion;
    }

    public function get_ind_FechaActualizacion() {
        return $this->_ind_FechaActualizacion;
    }
    public function set_ind_FechaActualizacion($_ind_FechaActualizacion) {
        $this->_ind_FechaActualizacion = $_ind_FechaActualizacion;
    }
}