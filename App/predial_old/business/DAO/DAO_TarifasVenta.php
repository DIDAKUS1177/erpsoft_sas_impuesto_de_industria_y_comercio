<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_TarifasVenta extends \predial\DAOGeneral {
    
    protected $_tar_Id ;
    protected $_tar_Descripcion ;
    protected $_tar_Estado ;
    protected $_tar_FechaCreacion ;

    //protected $_strNombreProducto;
    
    protected $_tabla = 'fac_tarifas_venta';
    protected $_primario = 'tar_Id';
    
    protected $_mapa = array(
        'tar_Id' => array('tipodato' => 'integer'),
        'tar_Descripcion' => array('tipodato' => 'varchar'),
        'tar_Estado' => array('tipodato' => 'integer'),
        'tar_FechaCreacion' => array('tipodato' => 'varchar'),
    );   
    
    public function __construct() {
        parent::__construct();
    }

    function get_tar_Id() {
        return $this->_tar_Id;
    }

    function get_tar_Descripcion() {
        return $this->_tar_Descripcion;
    }

    function get_tar_Estado() {
        return $this->_tar_Estado;
    }

    function get_tar_FechaCreacion() {
        return $this->_tar_FechaCreacion;
    }

    function set_tar_Id($_tar_Id) {
        $this->_tar_Id = $_tar_Id;
    }

    function set_tar_Descripcion($_tar_Descripcion) {
        $this->_tar_Descripcion = $_tar_Descripcion;
    }

    function set_tar_Estado($_tar_Estado) {
        $this->_tar_Estado = $_tar_Estado;
    }
    
    function set_tar_FechaCreacion($_tar_FechaCreacion) {
        $this->_tar_FechaCreacion = $_tar_FechaCreacion;
    }

}
