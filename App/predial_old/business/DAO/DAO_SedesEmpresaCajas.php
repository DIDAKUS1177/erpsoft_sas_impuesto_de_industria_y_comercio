<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_SedesEmpresaCajas extends \predial\DAOGeneral {
    
    protected $_seemca_Id ;
    protected $_seemca_Nombre ;
    protected $_seemca_Serial ;
    protected $_seemca_CodigoCaja ;
    protected $_seemca_IdSedeEmpresa ;
    protected $_seemca_IdResolucion ;    
    protected $_seemca_IdResolucionRemi ;    
    protected $_seemca_Estado ;
    protected $_seemca_FechaCreacion ;

    protected $_strNombreSede ;
    protected $_strNumeroResolucion ;
    protected $_strNumeroResolucionRemi ;
    
    protected $_tabla = 'conf_sedes_empresa_cajas';
    protected $_primario = 'seemca_Id';
    
    protected $_mapa = array(
        'seemca_Id' => array('tipodato' => 'integer'),
        'seemca_Nombre' => array('tipodato' => 'varchar'),
        'seemca_Serial' => array('tipodato' => 'varchar'),
        'seemca_CodigoCaja' => array('tipodato' => 'varchar'),
        'seemca_IdSedeEmpresa' => array('tipodato' => 'integer'),
        'seemca_IdResolucion' => array('tipodato' => 'integer'),
        'seemca_IdResolucionRemi' => array('tipodato' => 'integer'),
        'seemca_Estado' => array('tipodato' => 'integer'),
        'seemca_FechaCreacion' => array('tipodato' => 'varchar'),
        'strNombreSede' => array('tipodato' => 'varchar','sql' => '(select cse.seem_Nombre from conf_sedes_empresa as cse where cse.seem_Id = conf_sedes_empresa_cajas.seemca_IdSedeEmpresa)'),
        'strNumeroResolucion' => array('tipodato' => 'varchar','sql' => '(select cre.reso_Prefijo from conf_resoluciones as cre where cre.reso_Id = conf_sedes_empresa_cajas.seemca_IdResolucion )'),
        'strNumeroResolucionRemi' => array('tipodato' => 'varchar','sql' => '(select cre.reso_Prefijo from conf_resoluciones as cre where cre.reso_Id = conf_sedes_empresa_cajas.seemca_IdResolucionRemi )'),
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_seemca_Id() {
        return $this->_seemca_Id;
    }

    function get_seemca_Nombre() {
        return $this->_seemca_Nombre;
    }

    function get_seemca_Serial() {
        return $this->_seemca_Serial;
    }

    function get_seemca_CodigoCaja() {
        return $this->_seemca_CodigoCaja;
    }

    function get_seemca_IdSedeEmpresa() {
        return $this->_seemca_IdSedeEmpresa;
    }    

    function get_seemca_IdResolucion() {
        return $this->_seemca_IdResolucion;
    }

    function get_seemca_IdResolucionRemi() {
        return $this->_seemca_IdResolucionRemi;
    }

    function get_seemca_Estado() {
        return $this->_seemca_Estado;
    }

    function get_seemca_FechaCreacion() {
        return $this->_seemca_FechaCreacion;
    }

    function get_strNombreSede() {
        return $this->_strNombreSede;
    }

    function get_strNumeroResolucion() {
        return $this->_strNumeroResolucion;
    }

    function get_strNumeroResolucionRemi() {
        return $this->_strNumeroResolucionRemi;
    }


    function set_seemca_Id($_seemca_Id) {
        $this->_seemca_Id = $_seemca_Id;
    }

    function set_seemca_Nombre($_seemca_Nombre) {
        $this->_seemca_Nombre = $_seemca_Nombre;
    }

    function set_seemca_Serial($_seemca_Serial) {
        $this->_seemca_Serial = $_seemca_Serial;
    }

    function set_seemca_CodigoCaja($_seemca_CodigoCaja) {
        $this->_seemca_CodigoCaja = $_seemca_CodigoCaja;
    }

    function set_seemca_IdSedeEmpresa($_seemca_IdSedeEmpresa) {
        $this->_seemca_IdSedeEmpresa = $_seemca_IdSedeEmpresa;
    }

    function set_seemca_IdResolucion($_seemca_IdResolucion) {
        $this->_seemca_IdResolucion = $_seemca_IdResolucion;
    }

    function set_seemca_IdResolucionRemi($_seemca_IdResolucionRemi) {
        $this->_seemca_IdResolucionRemi = $_seemca_IdResolucionRemi;
    }

    function set_seemca_Estado($_seemca_Estado) {
        $this->_seemca_Estado = $_seemca_Estado;
    }

    function set_seemca_FechaCreacion($_seemca_FechaCreacion) {
        $this->_seemca_FechaCreacion = $_seemca_FechaCreacion;
    }
    
    function set_strNombreSede($_strNombreSede) {
        $this->_strNombreSede = $_strNombreSede;
    }

    function set_strNumeroResolucion($_strNumeroResolucion) {
        $this->_strNumeroResolucion = $_strNumeroResolucion;
    }
    
    function set_strNumeroResolucionRemi($_strNumeroResolucionRemi) {
        $this->_strNumeroResolucionRemi = $_strNumeroResolucionRemi;
    }

}
