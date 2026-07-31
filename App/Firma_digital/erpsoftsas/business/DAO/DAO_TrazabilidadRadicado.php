<?php
namespace erpsoftsas;
include_once 'class.DAO.php';

class DAO_TrazabilidadRadicado extends \erpsoftsas\DAOGeneral {
    
    protected $_tra_Id;
    protected $_tra_IdPeticion ;
    protected $_tra_IdTipoPeticion ;
    protected $_tra_IdEstadoTipoPeticion ;
    protected $_tra_IdDependencia ;
    protected $_tra_IdCategoria ;
    protected $_tra_IdSubCategoria ;
    protected $_tra_Cambios ;
    protected $_tra_Observaciones ;
    protected $_tra_IdDependenciaResponsable ;
    protected $_tra_IdUsuario ;
    protected $_created_at ;
/*
    protected $_strTipoPeticion ;
    protected $_strNombreEstado ;   
    protected $_strEstadoActual ;   
    protected $_strNombreDependencia ;   
    protected $_strNombreCategoria ;   
    protected $_strNombreSubCategoria ;   
*/
    protected $_strNombreResponsable ;   
    protected $_strNombreUsuario ;   

    protected $_tabla = 'trazabilidad_peticiones';
    protected $_primario = 'tra_Id';
    
    protected $_mapa = array(
        'tra_Id' => array('tipodato' => 'integer'),
        'tra_IdPeticion' => array('tipodato' => 'integer'),
        'tra_IdTipoPeticion' => array('tipodato' => 'integer'),
        'tra_IdEstadoTipoPeticion' => array('tipodato' => 'integer'),
        'tra_IdDependencia' => array('tipodato' => 'integer'),
        'tra_IdCategoria' => array('tipodato' => 'integer'),
        'tra_IdSubCategoria' => array('tipodato' => 'integer'),
        'tra_Cambios' => array('tipodato' => 'varchar'),
        'tra_Observaciones' => array('tipodato' => 'varchar'),
        'tra_IdDependenciaResponsable' => array('tipodato' => 'integer'),
        'tra_IdUsuario' => array('tipodato' => 'integer'),
        'created_at' => array('tipodato' => 'varchar'),
/*
        'strTipoPeticion' => array('tipodato' => 'integer','sql' => '(select tipe.tipe_Nombre from  tipos_peticiones as tipe where tipe.tipe_Id = peticiones.pe_IdTipoPeticion)'),
        'strNombreEstado' => array('tipodato' => 'integer','sql' => '(select dep.dep_Nombre from  dependencia as dep where dep.dep_Id = peticiones.pe_IdDependencia)'),
        'strEstadoActual' => array('tipodato' => 'integer','sql' => '(select esta.est_Nombre from estados as esta INNER JOIN estados_tipos_peticiones as estati on estati.estipe_IdEstado = esta.est_Id where estati.estipe_Id  = peticiones.pe_IdEstadoTiposPeticion)'),
        
        'strNombreDependencia' => array('tipodato' => 'integer','sql' => '(select esta.est_Color from estados as esta INNER JOIN estados_tipos_peticiones as estati on estati.estipe_IdEstado = esta.est_Id where estati.estipe_Id  = peticiones.pe_IdEstadoTiposPeticion)'),
        'strNombreCategoria' => array('tipodato' => 'integer','sql' => '(select esta.est_Color from estados as esta INNER JOIN estados_tipos_peticiones as estati on estati.estipe_IdEstado = esta.est_Id where estati.estipe_Id  = peticiones.pe_IdEstadoTiposPeticion)'),
        'strNombreSubCategoria' => array('tipodato' => 'integer','sql' => '(select esta.est_Color from estados as esta INNER JOIN estados_tipos_peticiones as estati on estati.estipe_IdEstado = esta.est_Id where estati.estipe_Id  = peticiones.pe_IdEstadoTiposPeticion)'),
*/
        'strNombreResponsable' => array('tipodato' => 'varchar','sql' => '(select usus.usu_Nombre from conf_usuario as usus where usus.usu_Id   = trazabilidad_peticiones.tra_IdDependenciaResponsable)'),
        'strNombreUsuario' => array('tipodato' => 'varchar','sql' => '(select usus.usu_Nombre from conf_usuario as usus where usus.usu_Id   = trazabilidad_peticiones.tra_IdUsuario)'),


    );   
    
    public function __construct() {
        parent::__construct();
    }
        
    function get_tra_Id() {
        return $this->_tra_Id;
    }

    function get_tra_IdPeticion() {
        return $this->_tra_IdPeticion;
    }

    function get_tra_IdTipoPeticion() {
        return $this->_tra_IdTipoPeticion;
    }

    function get_tra_IdEstadoTipoPeticion() {
        return $this->_tra_IdEstadoTipoPeticion;
    }

    function get_tra_IdDependencia() {
        return $this->_tra_IdDependencia;
    }

    function get_tra_IdCategoria() {
        return $this->_tra_IdCategoria;
    }

    function get_tra_IdSubCategoria() {
        return $this->_tra_IdSubCategoria;
    }

    function get_tra_Cambios() {
        return $this->_tra_Cambios;
    }

    function get_tra_Observaciones() {
        return $this->_tra_Observaciones;
    }
    
    function get_tra_IdDependenciaResponsable() {
        return $this->_tra_IdDependenciaResponsable;
    }


    function get_tra_IdUsuario() {
        return $this->_tra_IdUsuario;
    }

    function get_created_at() {
        return $this->_created_at;
    }
    

    function get_strNombreResponsable() {
        return $this->_strNombreResponsable;
    }
    function get_strNombreUsuario() {
        return $this->_strNombreUsuario;
    }
    


    function set_tra_Id($_tra_Id) {
        $this->_tra_Id = $_tra_Id;
    }

    function set_tra_IdPeticion($_tra_IdPeticion) {
        $this->_tra_IdPeticion = $_tra_IdPeticion;
    }

    function set_tra_IdTipoPeticion($tra_IdTipoPeticion) {
        $this->_tra_IdTipoPeticion = $tra_IdTipoPeticion;
    }

    function set_tra_IdEstadoTipoPeticion($tra_IdEstadoTipoPeticion) {
        $this->_tra_IdEstadoTipoPeticion = $tra_IdEstadoTipoPeticion;
    }

    function set_tra_IdDependencia($tra_IdDependencia) {
        $this->_tra_IdDependencia = $tra_IdDependencia;
    }

    function set_tra_IdCategoria($tra_IdCategoria) {
        $this->_tra_IdCategoria = $tra_IdCategoria;
    }

    function set_tra_IdSubCategoria($tra_IdSubCategoria) {
        $this->_tra_IdSubCategoria = $tra_IdSubCategoria;
    }

    function set_tra_Cambios($tra_Cambios) {
        $this->_tra_Cambios = $tra_Cambios;
    }

    function set_tra_Observaciones($tra_Observaciones) {
        $this->_tra_Observaciones = $tra_Observaciones;
    }

    function set_tra_IdDependenciaResponsable($tra_IdDependenciaResponsable) {
        $this->_tra_IdDependenciaResponsable = $tra_IdDependenciaResponsable;
    }

    function set_tra_IdUsuario($tra_IdUsuario) {
        $this->_tra_IdUsuario = $tra_IdUsuario;
    }

    function set_created_at($_created_at) {
        $this->_created_at = $_created_at;
    }

    function set_strNombreResponsable($_strNombreResponsable) {
        $this->_strNombreResponsable = $_strNombreResponsable;
    }
 
    function set_strNombreUsuario($_strNombreUsuario) {
        $this->_strNombreUsuario = $_strNombreUsuario;
    }
     
}





