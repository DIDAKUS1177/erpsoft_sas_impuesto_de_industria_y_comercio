<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_EgresosEventos extends \predial\DAOGeneral {
    
    protected $_egre_Id ;
    protected $_egre_IdEvento ;
    protected $_egre_IdActividad ;
    protected $_egre_IdCuentaContable ;
    protected $_egre_Descripcion ;
    protected $_egre_Valor ;
    protected $_egre_Fecha ;
    protected $_egre_Estado ;
    protected $_egre_FechaCreacion ;

    protected $_strNombreEvento ;
    protected $_strNombreActividad ;  
    protected $_strNombreCuenta ;  
    
    protected $_tabla = 'eve_egresoseventos';
    protected $_primario = 'egre_Id';
    
    protected $_mapa = array(
        'egre_Id' => array('tipodato' => 'integer'),
        'egre_IdEvento' => array('tipodato' => 'integer'),
        'egre_IdActividad' => array('tipodato' => 'integer'),
        'egre_IdCuentaContable' => array('tipodato' => 'integer'),
        'egre_Descripcion' => array('tipodato' => 'varchar'),
        'egre_Valor' => array('tipodato' => 'integer'),
        'egre_Fecha' => array('tipodato' => 'varchar'),
        'egre_Estado' => array('tipodato' => 'integer'),
        'egre_FechaCreacion' => array('tipodato' => 'varchar'),
        'strNombreEvento' => array('tipodato' => 'integer','sql' => '(select cse.eve_Nombre from eve_eventos as cse where cse.eve_Id = eve_egresoseventos.egre_IdEvento)'),
        'strNombreActividad' => array('tipodato' => 'integer','sql' => '(select act.eva_Descripcion from eve_actividadeseventos as act where act.eva_Id = eve_egresoseventos.egre_IdActividad)'),
        'strNombreCuenta' => array('tipodato' => 'varchar','sql' => '(select forr.forpa_Descripcion from fac_cuentascontables as cu INNER JOIN fac_formas_pago as forr on cu.cuco_IdCuentaContable= forr.forpa_Id WHERE cu.cuco_Id = eve_egresoseventos.egre_IdCuentaContable)')
    );   
    
    public function __construct() {
        parent::__construct();
    }

    function get_egre_Id(){
        return $this->_egre_Id;
    }

    function set_egre_Id($_egre_Id) {
        $this->_egre_Id = $_egre_Id;
    }
    
    function get_egre_IdEvento(){
        return $this->_egre_IdEvento;
    }

    function set_egre_IdEvento($_egre_IdEvento) {
        $this->_egre_IdEvento = $_egre_IdEvento;
    }

    function get_egre_IdActividad(){
        return $this->_egre_IdActividad;
    }

    function set_egre_IdActividad($_egre_IdActividad) {
        $this->_egre_IdActividad = $_egre_IdActividad;
    }

    function get_egre_IdCuentaContable(){
        return $this->_egre_IdCuentaContable;
    }

    function set_egre_IdCuentaContable($_egre_IdCuentaContable) {
        $this->_egre_IdCuentaContable = $_egre_IdCuentaContable;
    }

    function get_egre_Descripcion(){
        return $this->_egre_Descripcion;
    }

    function set_egre_Descripcion($_egre_Descripcion) {
        $this->_egre_Descripcion = $_egre_Descripcion;
    }

    function get_egre_Valor(){
        return $this->_egre_Valor;
    }

    function set_egre_Valor($_egre_Valor) {
        $this->_egre_Valor = $_egre_Valor;
    }

    function get_egre_Fecha(){
        return $this->_egre_Fecha;
    }

    function set_egre_Fecha($_egre_Fecha) {
        $this->_egre_Fecha = $_egre_Fecha;
    }

    function get_egre_Estado(){
        return $this->_egre_Estado;
    }

    function set_egre_Estado($_egre_Estado) {
        $this->_egre_Estado = $_egre_Estado;
    }

    function get_egre_FechaCreacion(){
        return $this->_egre_FechaCreacion;
    }

    function set_egre_FechaCreacion($_egre_FechaCreacion) {
        $this->_egre_FechaCreacion = $_egre_FechaCreacion;
    }   
    
    function get_strNombreEvento(){
        return $this->_strNombreEvento;
    }

    function set_strNombreEvento($_strNombreEvento) {
        $this->_strNombreEvento = $_strNombreEvento;
    }  

    function get_strNombreActividad(){
        return $this->_strNombreActividad;
    }

    function set_strNombreActividad($_strNombreActividad) {
        $this->_strNombreActividad = $_strNombreActividad;
    }  

    function get_strNombreCuenta(){
        return $this->_strNombreCuenta;
    }

    function set_strNombreCuenta($_strNombreCuenta) {
        $this->_strNombreCuenta = $_strNombreCuenta;
    }

}
