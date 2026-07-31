<?php
namespace erpsoftsas;
include_once 'class.DAO.php';

class DAO_Peticiones extends \erpsoftsas\DAOGeneral {
    
    protected $_pe_Id;
    protected $_pe_IdTipoPeticion ;

    protected $_pe_NombreCompleto ;
    protected $_pe_NumeroIdentificacion ;
    protected $_pe_Direccion ;
    protected $_pe_Telefono ;
    protected $_pe_CorreoElectronico ;
    protected $_pe_NumeroFolios ;
    protected $_pe_IdDependencia ;
    protected $_pe_IdCategoria ;
    protected $_pe_IdSubCategoria ;
    protected $_pe_Prioridad ;
    protected $_pe_FormaRecepcion ;
    protected $_pe_IdDependenciaResponsable ;
    protected $_pe_IdEstadoTiposPeticion ;
    protected $_pe_Descripcion ;
    protected $_pe_Observaciones ;
    protected $_pe_Estado ;
    protected $_created_at ;
    
    protected $_strTipoDocumento ;
    protected $_strIdDependencia ;   
    protected $_strEstadoActual ;   
    protected $_strEstadoActualColor ;   

    protected $_tabla = 'peticiones';
    protected $_primario = 'pe_Id';
    
    protected $_mapa = array(
        'pe_Id' => array('tipodato' => 'integer'),
        'pe_IdTipoPeticion' => array('tipodato' => 'varchar'),
        'pe_NombreCompleto' => array('tipodato' => 'varchar'),
        'pe_NumeroIdentificacion' => array('tipodato' => 'integer'),
        'pe_Direccion' => array('tipodato' => 'varchar'),
        'pe_Telefono' => array('tipodato' => 'integer'),
        'pe_CorreoElectronico' => array('tipodato' => 'varchar'),
        'pe_NumeroFolios' => array('tipodato' => 'integer'),
        'pe_IdDependencia' => array('tipodato' => 'integer'),
        'pe_IdCategoria' => array('tipodato' => 'varchar'),
        'pe_IdSubCategoria' => array('tipodato' => 'varchar'),
        'pe_Prioridad' => array('tipodato' => 'varchar'),
        'pe_FormaRecepcion' => array('tipodato' => 'varchar'),
        'pe_IdDependenciaResponsable' => array('tipodato' => 'varchar'),
        'pe_IdEstadoTiposPeticion' => array('tipodato' => 'varchar'),
        'pe_Descripcion' => array('tipodato' => 'varchar'),
        'pe_Observaciones' => array('tipodato' => 'varchar'),
        'pe_Estado' => array('tipodato' => 'varchar'),
        'created_at' => array('tipodato' => 'varchar'),
        'strTipoDocumento' => array('tipodato' => 'integer','sql' => '(select tipe.tipe_Nombre from  tipos_peticiones as tipe where tipe.tipe_Id = peticiones.pe_IdTipoPeticion)'),
        'strIdDependencia' => array('tipodato' => 'integer','sql' => '(select dep.dep_Nombre from  dependencia as dep where dep.dep_Id = peticiones.pe_IdDependencia)'),
        'strEstadoActual' => array('tipodato' => 'integer','sql' => '(select esta.est_Nombre from estados as esta INNER JOIN estados_tipos_peticiones as estati on estati.estipe_IdEstado = esta.est_Id where estati.estipe_Id  = peticiones.pe_IdEstadoTiposPeticion)'),
        'strEstadoActualColor' => array('tipodato' => 'integer','sql' => '(select esta.est_Color from estados as esta INNER JOIN estados_tipos_peticiones as estati on estati.estipe_IdEstado = esta.est_Id where estati.estipe_Id  = peticiones.pe_IdEstadoTiposPeticion)'),
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    
    function get_pe_Id() {
        return $this->_pe_Id;
    }


    function get_pe_IdTipoPeticion() {
        return $this->_pe_IdTipoPeticion;
    }


    function get_pe_NombreCompleto() {
        return $this->_pe_NombreCompleto;
    }

    function get_pe_NumeroIdentificacion() {
        return $this->_pe_NumeroIdentificacion;
    }

    function get_pe_Direccion() {
        return $this->_pe_Direccion;
    }

    function get_pe_Telefono() {
        return $this->_pe_Telefono;
    }

    function get_pe_CorreoElectronico() {
        return $this->_pe_CorreoElectronico;
    }

    function get_pe_NumeroFolios() {
        return $this->_pe_NumeroFolios;
    }

    function get_pe_IdDependencia() {
        return $this->_pe_IdDependencia;
    }


    function get_pe_IdCategoria() {
        return $this->_pe_IdCategoria;
    }


    function get_pe_IdSubCategoria() {
        return $this->_pe_IdSubCategoria;
    }
    
    function get_pe_Prioridad() {
        return $this->_pe_Prioridad;
    }

    function get_pe_FormaRecepcion() {
        return $this->_pe_FormaRecepcion;
    }

    function get_pe_IdDependenciaResponsable() {
        return $this->_pe_IdDependenciaResponsable;
    }

    function get_pe_IdEstadoTiposPeticion() {
        return $this->_pe_IdEstadoTiposPeticion;
    }

    function get_pe_Descripcion() {
        return $this->_pe_Descripcion;
    }

    function get_pe_Observaciones() {
        return $this->_pe_Observaciones;
    }    

    function get_pe_Estado() {
        return $this->_pe_Estado;
    }


    function get_strTipoDocumento() {
        return $this->_strTipoDocumento;
    }

    function get_strIdDependencia() {
        return $this->_strIdDependencia;
    }

    function get_strEstadoActual() {
        return $this->_strEstadoActual;
    }

    function get_strEstadoActualColor() {
        return $this->_strEstadoActualColor;
    }
    
    function get_created_at() {
        return $this->_created_at;
    }  

    function set_pe_Id($_pe_Id) {
        $this->_pe_Id = $_pe_Id;
    }

    function set_pe_IdTipoPeticion($_pe_IdTipoPeticion) {
        $this->_pe_IdTipoPeticion = $_pe_IdTipoPeticion;
    }


    function set_pe_NombreCompleto($pe_NombreCompleto) {
        $this->_pe_NombreCompleto = $pe_NombreCompleto;
    }

    function set_pe_NumeroIdentificacion($pe_NumeroIdentificacion) {
        $this->_pe_NumeroIdentificacion = $pe_NumeroIdentificacion;
    }

    function set_pe_Direccion($pe_Direccion) {
        $this->_pe_Direccion = $pe_Direccion;
    }

    function set_pe_Telefono($pe_Telefono) {
        $this->_pe_Telefono = $pe_Telefono;
    }

    function set_pe_CorreoElectronico($pe_CorreoElectronico) {
        $this->_pe_CorreoElectronico = $pe_CorreoElectronico;
    }

    function set_pe_NumeroFolios($pe_NumeroFolios) {
        $this->_pe_NumeroFolios = $pe_NumeroFolios;
    }

    function set_pe_IdDependencia($pe_IdDependencia) {
        $this->_pe_IdDependencia = $pe_IdDependencia;
    }

    function set_pe_Prioridad($pe_Prioridad) {
        $this->_pe_Prioridad = $pe_Prioridad;
    }

    function set_pe_FormaRecepcion($pe_FormaRecepcion) {
        $this->_pe_FormaRecepcion = $pe_FormaRecepcion;
    }


    function set_pe_IdCategoria($_pe_IdCategoria) {
        $this->_pe_IdCategoria = $_pe_IdCategoria;
    }


    function set_pe_IdSubCategoria($_pe_IdSubCategoria) {
        $this->_pe_IdSubCategoria = $_pe_IdSubCategoria;
    }


    function set_pe_IdDependenciaResponsable($_pe_IdDependenciaResponsable) {
        $this->_pe_IdDependenciaResponsable = $_pe_IdDependenciaResponsable;
    }


    function set_pe_IdEstadoTiposPeticion($_pe_IdEstadoTiposPeticion) {
        $this->_pe_IdEstadoTiposPeticion = $_pe_IdEstadoTiposPeticion;
    }


    function set_pe_Descripcion($_pe_Descripcion) {
        $this->_pe_Descripcion = $_pe_Descripcion;
    }

    function set_pe_Observaciones($_pe_Observaciones) {
        $this->_pe_Observaciones = $_pe_Observaciones;
    }


    function set_pe_Estado($_pe_Estado) {
        $this->_pe_Estado = $_pe_Estado;
    }
    
    function set_strTipoDocumento($_strTipoDocumento) {
        $this->_strTipoDocumento = $_strTipoDocumento;
    }

    
    function set_strIdDependencia($_strIdDependencia) {
        $this->_strIdDependencia = $_strIdDependencia;
    }

    function set_strEstadoActual($_strEstadoActual) {
        $this->_strEstadoActual = $_strEstadoActual;
    }
    
    function set_strEstadoActualColor($_strEstadoActualColor) {
        $this->_strEstadoActualColor = $_strEstadoActualColor;
    }

    function set_created_at($_created_at) {
        $this->_created_at = $_created_at;
    }
    
}





