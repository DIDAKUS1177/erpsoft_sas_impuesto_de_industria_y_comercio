<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_BaseCaja extends \predial\DAOGeneral {
    
    protected $_bace_Id ;
    protected $_bace_IdCaja ;
    protected $_bace_IdVendedor ;
    protected $_bace_Base ;
    protected $_bace_Cierre ;
    protected $_bace_IdCierre ;
    protected $_bace_FechaCreacion ;

    protected $_strNombreCaja ;
    protected $_strNombreVendedor ;
    
    protected $_tabla = 'fac_base_caja';
    protected $_primario = 'bace_Id';
    
    protected $_mapa = array(
        'bace_Id' => array('tipodato' => 'integer'),
        'bace_IdCaja' => array('tipodato' => 'integer'),
        'bace_IdVendedor' => array('tipodato' => 'integer'),
        'bace_Base' => array('tipodato' => 'varchar'),
        'bace_Cierre' => array('tipodato' => 'integer'),
        'bace_IdCierre' => array('tipodato' => 'integer'),
        'bace_FechaCreacion' => array('tipodato' => 'varchar'),
        'strNombreCaja' => array('tipodato' => 'integer','sql' => '(select cse.seemca_Nombre from conf_sedes_empresa_cajas as cse where cse.seemca_Id = fac_base_caja.bace_IdCaja)'),
        'strNombreVendedor' => array('tipodato' => 'integer','sql' => '(select cu.usu_Nombre from conf_usuario as cu where cu.usu_Id = fac_base_caja.bace_IdVendedor)')
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_bace_Id() {
        return $this->_bace_Id;
    }

    function get_bace_IdCaja() {
        return $this->_bace_IdCaja;
    }

    function get_bace_IdVendedor() {
        return $this->_bace_IdVendedor;
    }

    function get_bace_Base() {
        return $this->_bace_Base;
    }

    function get_bace_Cierre() {
        return $this->_bace_Cierre;
    }
    
    function get_bace_IdCierre() {
        return $this->_bace_IdCierre;
    }

    function get_bace_FechaCreacion() {
        return $this->_bace_FechaCreacion;
    }


    function get_strNombreCaja() {
        return $this->_strNombreCaja;
    }

    function get_strNombreVendedor() {
        return $this->_strNombreVendedor;
    }


    function set_bace_Id($_bace_Id) {
        $this->_bace_Id = $_bace_Id;
    }

    function set_bace_IdCaja($_bace_IdCaja) {
        $this->_bace_IdCaja = $_bace_IdCaja;
    }

    function set_bace_IdVendedor($_bace_IdVendedor) {
        $this->_bace_IdVendedor = $_bace_IdVendedor;
    }

    function set_bace_Base($_bace_Base) {
        $this->_bace_Base = $_bace_Base;
    }

    function set_bace_Cierre($_bace_Cierre) {
        $this->_bace_Cierre = $_bace_Cierre;
    }

    function set_bace_IdCierre($_bace_IdCierre) {
        $this->_bace_IdCierre = $_bace_IdCierre;
    }

    function set_bace_FechaCreacion($_bace_FechaCreacion) {
        $this->_bace_FechaCreacion = $_bace_FechaCreacion;
    }
    
    function set_strNombreCaja($_strNombreCaja) {
        $this->_strNombreCaja = $_strNombreCaja;
    }
    
    function set_strNombreVendedor($_strNombreVendedor) {
        $this->_strNombreVendedor = $_strNombreVendedor;
    }    

}
