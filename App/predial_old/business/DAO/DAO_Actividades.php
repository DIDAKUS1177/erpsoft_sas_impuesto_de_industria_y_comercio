<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_Actividades extends \predial\DAOGeneral {
    
    protected $_eva_Id ;
    protected $_eva_Descripcion ;
    protected $_eva_IdProyecto	;
    protected $_eva_IdProveedor ;
    protected $_eva_IdCategoria	;
    protected $_eva_Valor	;
    protected $_eva_FechaCreacion ;
    protected $_eva_Estado ;

    protected $_strNombreProyecto ;
    protected $_strNombreProveedor ;   
    protected $_strRazonSocialProveedor ;       
    
    protected $_tabla = 'eve_actividadeseventos';
    protected $_primario = 'eva_Id';
    
    protected $_mapa = array(
        'eva_Id' => array('tipodato' => 'integer'),
        'eva_Descripcion' => array('tipodato' => 'varchar'),
        'eva_IdProyecto' => array('tipodato' => 'integer'),
        'eva_IdProveedor' => array('tipodato' => 'integer'),
        'eva_IdCategoria' => array('tipodato' => 'integer'),
        'eva_Valor' => array('tipodato' => 'integer'),
        'eva_FechaCreacion' => array('tipodato' => 'varchar'),
        'eva_Estado' => array('tipodato' => 'integer'),
        'strNombreProyecto' => array('tipodato' => 'integer','sql' => '(select cse.eve_Nombre from eve_eventos as cse where cse.eve_Id = eve_actividadeseventos.eva_IdProyecto)'),
        'strNombreProveedor' => array('tipodato' => 'integer','sql' => '(select pro.prov_Nombre from eve_proveedoreseventos as pro where pro.prov_Id = eve_actividadeseventos.eva_IdProveedor)'),
        'strRazonSocialProveedor' => array('tipodato' => 'integer','sql' => '(select pro.prov_RazonSocial from eve_proveedoreseventos as pro where pro.prov_Id = eve_actividadeseventos.eva_IdProveedor)'),
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_eva_Id() {
        return $this->_eva_Id;
    }

    function set_eva_Id($_eva_Id) {
        $this->_eva_Id = $_eva_Id;
    }

    function get_eva_Descripcion() {
        return $this->_eva_Descripcion;
    }

    function set_eva_Descripcion($_eva_Descripcion) {
        $this->_eva_Descripcion = $_eva_Descripcion;
    }

    function get_eva_IdProyecto() {
        return $this->_eva_IdProyecto;
    }

    function set_eva_IdProyecto($_eva_IdProyecto) {
        $this->_eva_IdProyecto = $_eva_IdProyecto;
    }

    function get_eva_IdProveedor() {
        return $this->_eva_IdProveedor;
    }

    function set_eva_IdProveedor($_eva_IdProveedor) {
        $this->_eva_IdProveedor = $_eva_IdProveedor;
    }

    function get_eva_IdCategoria() {
        return $this->_eva_IdCategoria;
    }

    function set_eva_IdCategoria($_eva_IdCategoria) {
        $this->_eva_IdCategoria = $_eva_IdCategoria;
    }

    function get_eva_Valor() {
        return $this->_eva_Valor;
    }

    function set_eva_Valor($_eva_Valor) {
        $this->_eva_Valor = $_eva_Valor;
    }

    function get_eva_FechaCreacion() {
        return $this->_eva_FechaCreacion;
    }

    function set_eva_FechaCreacion($_eva_FechaCreacion) {
        $this->_eva_FechaCreacion = $_eva_FechaCreacion;
    }
    
    function get_eva_Estado() {
        return $this->_eva_Estado;
    }

    function set_eva_Estado($_eva_Estado) {
        $this->_eva_Estado = $_eva_Estado;
    }

    function get_strNombreProyecto() {
        return $this->_strNombreProyecto;
    }

    function set_strNombreProyecto($_strNombreProyecto) {
        $this->_strNombreProyecto = $_strNombreProyecto;
    }
    
    function get_strNombreProveedor() {
        return $this->_strNombreProveedor;
    }

    function set_strNombreProveedor($_strNombreProveedor) {
        $this->_strNombreProveedor = $_strNombreProveedor;
    }

    function get_strRazonSocialProveedor() {
        return $this->_strRazonSocialProveedor;
    }

    function set_strRazonSocialProveedor($_strRazonSocialProveedor) {
        $this->_strRazonSocialProveedor = $_strRazonSocialProveedor;
    }
    
}
