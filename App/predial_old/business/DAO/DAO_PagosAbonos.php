<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_PagosAbonos extends \predial\DAOGeneral {
    
    protected $_pago_Id ;
    protected $_pago_IdProyecto ;
    protected $_pago_IdCuentaContable ;
    protected $_pago_Fecha ;
    protected $_pago_Descripcion ;
    protected $_pago_Valor ;
    protected $_pago_FechaCreacion ;
    protected $_pago_Estado ;

    protected $_strNombreCuenta ;
    protected $_strNombreProyecto ;
    
    
    protected $_tabla = 'eve_ingresoseventos';
    protected $_primario = 'pago_Id';
    
    protected $_mapa = array(
        'pago_Id' => array('tipodato' => 'integer'),
        'pago_IdProyecto' => array('tipodato' => 'integer'),
        'pago_IdCuentaContable' => array('tipodato' => 'integer'),
        'pago_Fecha' => array('tipodato' => 'integer'),
        'pago_Descripcion' => array('tipodato' => 'varchar'),
        'pago_Valor' => array('tipodato' => 'varchar'),
        'pago_FechaCreacion' => array('tipodato' => 'varchar'),
        'pago_Estado' => array('tipodato' => 'integer'),
        'strNombreCuenta' => array('tipodato' => 'varchar','sql' => '(select forr.forpa_Descripcion from fac_cuentascontables as cu INNER JOIN fac_formas_pago as forr on cu.cuco_IdCuentaContable= forr.forpa_Id WHERE cu.cuco_Id = eve_ingresoseventos.pago_IdCuentaContable)'),
        'strNombreProyecto' => array('tipodato' => 'varchar','sql' => '(select ev.eve_Descripcion from eve_eventos as ev WHERE ev.eve_Id = eve_ingresoseventos.pago_IdProyecto)')
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_pago_Id() {
        return $this->_pago_Id;
    }

    function get_pago_IdProyecto() {
        return $this->_pago_IdProyecto;
    }

    function get_pago_IdCuentaContable() {
        return $this->_pago_IdCuentaContable;
    }

    function get_pago_Fecha() {
        return $this->_pago_Fecha;
    }

    function get_pago_Descripcion() {
        return $this->_pago_Descripcion;
    }

    function get_pago_Valor() {
        return $this->_pago_Valor;
    }

    function get_pago_FechaCreacion() {
        return $this->_pago_FechaCreacion;
    }

    function get_pago_Estado() {
        return $this->_pago_Estado;
    }

    function get_strNombreCuenta() {
        return $this->_strNombreCuenta;
    }

    function get_strNombreProyecto() {
        return $this->_strNombreProyecto;
    }
    
    function set_pago_Id($_pago_Id) {
        $this->_pago_Id = $_pago_Id;
    }

    function set_pago_IdProyecto($_pago_IdProyecto) {
        $this->_pago_IdProyecto = $_pago_IdProyecto;
    }

    function set_pago_IdCuentaContable($_pago_IdCuentaContable) {
        $this->_pago_IdCuentaContable = $_pago_IdCuentaContable;
    }

    function set_pago_Fecha($_pago_Fecha) {
        $this->_pago_Fecha = $_pago_Fecha;
    }

    function set_pago_Descripcion($_pago_Descripcion) {
        $this->_pago_Descripcion = $_pago_Descripcion;
    }

    function set_pago_Valor($_pago_Valor) {
        $this->_pago_Valor = $_pago_Valor;
    }

    function set_pago_FechaCreacion($_pago_FechaCreacion) {
        $this->_pago_FechaCreacion = $_pago_FechaCreacion;
    }

    function set_pago_Estado($_pago_Estado) {
        $this->_pago_Estado = $_pago_Estado;
    }

    function set_strNombreCuenta($_strNombreCuenta) {
        $this->_strNombreCuenta = $_strNombreCuenta;
    }

    function set_strNombreProyecto($_strNombreProyecto) {
        $this->_strNombreProyecto = $_strNombreProyecto;
    }
    
}
