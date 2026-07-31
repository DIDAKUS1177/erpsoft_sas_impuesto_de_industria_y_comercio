<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_Insumo extends \predial\DAOGeneral {
    
    protected $_ins_Id ;
    protected $_ins_IdCategoria ;
    protected $_ins_IdSubCategoria ;
    protected $_ins_IdProveedor ;
    protected $_ins_Nombre ;
    protected $_ins_Codigo ;
    protected $_ins_CodBarras ;
    protected $_ins_ReferenciaNombre1 ;
    protected $_ins_ReferenciaValor1 ;
    protected $_ins_ReferenciaNombre2 ;
    protected $_ins_ReferenciaValor2 ;
    protected $_ins_IdTipoCantidad ;
    protected $_ins_IdTipoUnidad ;
    protected $_ins_Imagen ;
    protected $_ins_Estado ;
    protected $_ins_FechaCreacion ;

    protected $_strNombreCategoria;
    protected $_strTipoUnidad;
    protected $_strNombreSubCategoria;
    protected $_strTotalStock;
    
    protected $_tabla = 'prod_insumos';
    protected $_primario = 'ins_Id';
    
    protected $_mapa = array(
        'ins_Id' => array('tipodato' => 'integer'),
        'ins_IdCategoria' => array('tipodato' => 'integer'),
        'ins_IdSubCategoria' => array('tipodato' => 'integer'),
        'ins_IdProveedor' => array('tipodato' => 'integer'),
        'ins_Nombre' => array('tipodato' => 'varchar'),
        'ins_Codigo' => array('tipodato' => 'varchar'),
        'ins_CodBarras' => array('tipodato' => 'varchar'),
        'ins_ReferenciaNombre1' => array('tipodato' => 'varchar'),
        'ins_ReferenciaValor1' => array('tipodato' => 'varchar'),
        'ins_ReferenciaNombre2' => array('tipodato' => 'varchar'),
        'ins_ReferenciaValor2' => array('tipodato' => 'varchar'),
        'ins_IdTipoCantidad' => array('tipodato' => 'integer'),
        'ins_IdTipoUnidad' => array('tipodato' => 'integer'),
        'ins_Imagen' => array('tipodato' => 'varchar'),
        'ins_Estado' => array('tipodato' => 'integer'),
        'ins_FechaCreacion' => array('tipodato' => 'varchar'),
        'strNombreCategoria' => array('tipodato' => 'integer','sql' => '(select ic.cate_Descripcion from inv_categoria as ic where ic.cate_Id = prod_insumos.ins_IdCategoria)'),
        'strNombreSubCategoria' => array('tipodato' => 'varchar','sql' => '(select ic.subCate_Descripcion from inv_sub_categoria as ic where ic.subCate_Id = prod_insumos.ins_IdSubCategoria)'),
        'strTipoUnidad' => array('tipodato' => 'varchar','sql' => '(select cate.tiuni_Abreviatura from prod_tipo_unidad as cate where cate.tiuni_Id = prod_insumos.ins_IdTipoUnidad)'),
        'strTotalStock' => array('tipodato' => 'integer','sql' => '(select FORMAT(SUM(exi.exi_Cantidad), 2) from prod_existencias as exi where exi.exi_IdProducto = prod_insumos.ins_Id)')
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_ins_Id() {
        return $this->_ins_Id;
    }

    function get_ins_IdCategoria() {
        return $this->_ins_IdCategoria;
    }

    function get_ins_IdSubCategoria() {
        return $this->_ins_IdSubCategoria;
    }

    function get_ins_IdProveedor() {
        return $this->_ins_IdProveedor;
    }

    function get_ins_Nombre() {
        return $this->_ins_Nombre;
    }
    
    function get_ins_Codigo() {
        return $this->_ins_Codigo;
    }

    function get_ins_CodBarras() {
        return $this->_ins_CodBarras;
    }

    function get_ins_ReferenciaNombre1() {
        return $this->_ins_ReferenciaNombre1;
    }

    function get_ins_ReferenciaValor1() {
        return $this->_ins_ReferenciaValor1;
    }

    function get_ins_ReferenciaNombre2() {
        return $this->_ins_ReferenciaNombre2;
    }

    function get_ins_ReferenciaValor2() {
        return $this->_ins_ReferenciaValor2;
    }

    function get_ins_IdTipoCantidad() {
        return $this->_ins_IdTipoCantidad;
    }

    function get_ins_IdTipoUnidad() {
        return $this->_ins_IdTipoUnidad;
    }

    function get_ins_Imagen() {
        return $this->_ins_Imagen;
    }

    function get_ins_Estado() {
        return $this->_ins_Estado;
    }

    function get_ins_FechaCreacion() {
        return $this->_ins_FechaCreacion;
    }  

    function get_strNombreCategoria() {
        return $this->_strNombreCategoria;
    }  

    function get_strNombreSubCategoria() {
        return $this->_strNombreSubCategoria;
    } 

    function get_strTipoUnidad() {
        return $this->_strTipoUnidad;
    }

    function get_strTotalStock() {
        return $this->_strTotalStock;
    } 

    function set_ins_Id($_ins_Id) {
        $this->_ins_Id = $_ins_Id;
    }

    function set_ins_IdCategoria($_ins_IdCategoria) {
        $this->_ins_IdCategoria = $_ins_IdCategoria;
    }

    function set_ins_IdSubCategoria($_ins_IdSubCategoria) {
        $this->_ins_IdSubCategoria = $_ins_IdSubCategoria;
    }

    function set_ins_IdProveedor($_ins_IdProveedor) {
        $this->_ins_IdProveedor = $_ins_IdProveedor;
    }

    function set_ins_Nombre($_ins_Nombre) {
        $this->_ins_Nombre = $_ins_Nombre;
    }
    
    function set_ins_Codigo($_ins_Codigo) {
        $this->_ins_Codigo = $_ins_Codigo;
    }

    function set_ins_CodBarras($_ins_CodBarras) {
        $this->_ins_CodBarras = $_ins_CodBarras;
    }
    
    function set_ins_ReferenciaNombre1($_ins_ReferenciaNombre1) {
        $this->_ins_ReferenciaNombre1 = $_ins_ReferenciaNombre1;
    }

    function set_ins_ReferenciaValor1($_ins_ReferenciaValor1) {
        $this->_ins_ReferenciaValor1 = $_ins_ReferenciaValor1;
    }

    function set_ins_ReferenciaNombre2($_ins_ReferenciaNombre2) {
        $this->_ins_ReferenciaNombre2 = $_ins_ReferenciaNombre2;
    }

    function set_ins_ReferenciaValor2($_ins_ReferenciaValor2) {
        $this->_ins_ReferenciaValor2 = $_ins_ReferenciaValor2;
    }

    function set_ins_IdTipoCantidad($_ins_IdTipoCantidad) {
        $this->_ins_IdTipoCantidad = $_ins_IdTipoCantidad;
    }

    function set_ins_IdTipoUnidad($_ins_IdTipoUnidad) {
        $this->_ins_IdTipoUnidad = $_ins_IdTipoUnidad;
    }

    function set_ins_Imagen($_ins_Imagen) {
        $this->_ins_Imagen = $_ins_Imagen;
    }

    function set_ins_Estado($_ins_Estado) {
        $this->_ins_Estado = $_ins_Estado;
    }

    function set_ins_FechaCreacion($_ins_FechaCreacion) {
        $this->_ins_FechaCreacion = $_ins_FechaCreacion;
    }

    function set_strNombreCategoria($_strNombreCategoria) {
        $this->_strNombreCategoria = $_strNombreCategoria;
    }

    function set_strNombreSubCategoria($_strNombreSubCategoria) {
        $this->_strNombreSubCategoria = $_strNombreSubCategoria;
    }

    function set_strTipoUnidad($_strTipoUnidad) {
        $this->_strTipoUnidad = $_strTipoUnidad;
    }

    function set_strTotalStock($_strTotalStock) {
        $this->_strTotalStock = $_strTotalStock;
    }

}
