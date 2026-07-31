<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_Producto extends \predial\DAOGeneral {
    
    protected $_pro_Id ;
    protected $_pro_Codigo ;
    protected $_pro_Nombre ;
    protected $_pro_Tipo ;
    protected $_pro_UnidadMed ;
    protected $_pro_CantidadMed ;
    protected $_pro_UsaStoks ;
    protected $_pro_CodBarras ;
    protected $_pro_IdImpuesto ;
    protected $_pro_Categoria ;
    protected $_pro_SubCategoria ;
    protected $_pro_IdMarca ;
    protected $_pro_IdProveedor ;
    protected $_imagen ;
    protected $_pro_Estado ;
    protected $_pro_FechaModificacion ;
    protected $_pro_FechaCreacion ;

    protected $_strNombreUnidad;
    protected $_strNombreCategoria;
    protected $_strTotalStock;
    protected $_strPrecioVenta;
    protected $_strPrecioCompra;

    protected $_strPrecioVentaId;
    protected $_strPrecioCompraId;
    
    
    protected $_tabla = 'inv_producto';
    protected $_primario = 'pro_Id';
    
    protected $_mapa = array(
        'pro_Id' => array('tipodato' => 'integer'),
        'pro_Codigo' => array('tipodato' => 'varchar'),
        'pro_Nombre' => array('tipodato' => 'varchar'),
        'pro_Tipo' => array('tipodato' => 'integer'),
        'pro_Estado' => array('tipodato' => 'integer'),
        'pro_UnidadMed' => array('tipodato' => 'integer'),
        'pro_CantidadMed' => array('tipodato' => 'integer'),
        'pro_UsaStoks' => array('tipodato' => 'integer'),
        'pro_CodBarras' => array('tipodato' => 'integer'),
        'pro_IdImpuesto' => array('tipodato' => 'integer'),
        'pro_Categoria' => array('tipodato' => 'integer'),
        'pro_SubCategoria' => array('tipodato' => 'integer'),
        'pro_IdMarca' => array('tipodato' => 'integer'),
        'pro_IdProveedor' => array('tipodato' => 'integer'),
        'imagen' => array('tipodato' => 'varchar'),
        'pro_FechaModificacion' => array('tipodato' => 'varchar'),
        'pro_FechaCreacion' => array('tipodato' => 'varchar'),
        'strNombreUnidad' => array('tipodato' => 'integer','sql' => '(select uni.uniM_Nombre from  inv_unidad_medida as uni where uni.uniM_Id = inv_producto.pro_UnidadMed)'),
        'strNombreCategoria' => array('tipodato' => 'varchar','sql' => '(select cate.cate_Descripcion from inv_categoria as cate where cate.cate_Id = inv_producto.pro_Categoria)'),
        'strTotalStock' => array('tipodato' => 'integer','sql' => '(select SUM(exi.exi_Cantidad) from inv_existencias as exi where exi.exi_IdProducto = inv_producto.pro_Id)'),
        'strPrecioVenta' => array('tipodato' => 'integer','sql' => '(select fpv.preVen_PrecioNeto from fac_precios_venta as fpv where fpv.preVen_IdProducto = inv_producto.pro_Id and fpv.preVen_IdTarifa = 1 )'),
        'strPrecioVentaId' => array('tipodato' => 'integer','sql' => '(select fpv.preVen_Id from fac_precios_venta as fpv where fpv.preVen_IdProducto = inv_producto.pro_Id and fpv.preVen_IdTarifa = 1 )'),
        'strPrecioCompra' => array('tipodato' => 'integer','sql' => '(select TRUNCATE(idk.detkar_ValorUnitario,0) from inv_detalle_kardex as idk where idk.detkar_IdProducto = inv_producto.pro_Id and detkar_CantidadEntrada IS NOT NULL ORDER by detkar_Id DESC LIMIT 1)'),
        'strPrecioCompraId' => array('tipodato' => 'integer','sql' => '(select idk.detkar_Id from inv_detalle_kardex as idk where idk.detkar_IdProducto = inv_producto.pro_Id and detkar_CantidadEntrada IS NOT NULL ORDER by detkar_Id DESC LIMIT 1)'),
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_pro_Id() {
        return $this->_pro_Id;
    }

    function get_pro_Codigo() {
        return $this->_pro_Codigo;
    }

    function get_pro_Nombre() {
        return $this->_pro_Nombre;
    }

    function get_pro_Tipo() {
        return $this->_pro_Tipo;
    }

    function get_pro_UnidadMed() {
        return $this->_pro_UnidadMed;
    }

    function get_pro_CantidadMed() {
        return $this->_pro_CantidadMed;
    }

    function get_pro_UsaStoks() {
        return $this->_pro_UsaStoks;
    }

    function get_pro_CodBarras() {
        return $this->_pro_CodBarras;
    }

    function get_pro_IdImpuesto() {
        return $this->_pro_IdImpuesto;
    }

    function get_pro_Categoria() {
        return $this->_pro_Categoria;
    }

    function get_pro_SubCategoria() {
        return $this->_pro_SubCategoria;
    }

    function get_pro_IdMarca() {
        return $this->_pro_IdMarca;
    }

    function get_pro_IdProveedor() {
        return $this->_pro_IdProveedor;
    }

    function get_imagen() {
        return $this->_imagen;
    }

    function get_pro_Estado() {
        return $this->_pro_Estado;
    }

    function get_pro_FechaModificacion() {
        return $this->_pro_FechaModificacion;
    }
    
    function get_pro_FechaCreacion() {
        return $this->_pro_FechaCreacion;
    }

    function get_strNombreUnidad() {
        return $this->_strNombreUnidad;
    }

    function get_strNombreCategoria() {
        return $this->_strNombreCategoria;
    }
    
    function get_strTotalStock() {
        return $this->_strTotalStock;
    }  

    function get_strPrecioVenta() {
        return $this->_strPrecioVenta;
    }      

    function get_strPrecioCompra() {
        return $this->_strPrecioCompra;
    }      

    function get_strPrecioVentaId() {
        return $this->_strPrecioVentaId;
    }      

    function get_strPrecioCompraId() {
        return $this->_strPrecioCompraId;
    } 

    function set_pro_Id($_pro_Id) {
        $this->_pro_Id = $_pro_Id;
    }

    function set_pro_Codigo($_pro_Codigo) {
        $this->_pro_Codigo = $_pro_Codigo;
    }

    function set_pro_Nombre($_pro_Nombre) {
        $this->_pro_Nombre = $_pro_Nombre;
    }

    function set_pro_Tipo($_pro_Tipo) {
        $this->_pro_Tipo = $_pro_Tipo;
    }

    function set_pro_UnidadMed($_pro_UnidadMed) {
        $this->_pro_UnidadMed = $_pro_UnidadMed;
    }

    function set_pro_CantidadMed($_pro_CantidadMed) {
        $this->_pro_CantidadMed = $_pro_CantidadMed;
    }
    
    function set_pro_UsaStoks($_pro_UsaStoks) {
        $this->_pro_UsaStoks = $_pro_UsaStoks;
    }

    function set_pro_CodBarras($_pro_CodBarras) {
        $this->_pro_CodBarras = $_pro_CodBarras;
    }

    function set_pro_IdImpuesto($_pro_IdImpuesto) {
        $this->_pro_IdImpuesto = $_pro_IdImpuesto;
    }

    function set_pro_Categoria($_pro_Categoria) {
        $this->_pro_Categoria = $_pro_Categoria;
    }
    
    function set_pro_SubCategoria($_pro_SubCategoria) {
        $this->_pro_SubCategoria = $_pro_SubCategoria;
    }

    function set_pro_IdMarca($_pro_IdMarca) {
        $this->_pro_IdMarca = $_pro_IdMarca;
    }

    function set_pro_IdProveedor($_pro_IdProveedor) {
        $this->_pro_IdProveedor = $_pro_IdProveedor;
    }

    function set_imagen($_imagen) {
        $this->_imagen = $_imagen;
    }

    function set_pro_Estado($_pro_Estado) {
        $this->_pro_Estado = $_pro_Estado;
    }

    function set_pro_FechaModificacion($_pro_FechaModificacion) {
        $this->_pro_FechaModificacion = $_pro_FechaModificacion;
    }

    function set_pro_FechaCreacion($_pro_FechaCreacion) {
        $this->_pro_FechaCreacion = $_pro_FechaCreacion;
    }

    function set_strNombreUnidad($_strNombreUnidad) {
        $this->_strNombreUnidad = $_strNombreUnidad;
    }

    function set_strNombreCategoria($_strNombreCategoria) {
        $this->_strNombreCategoria = $_strNombreCategoria;
    }

    function set_strTotalStock($_strTotalStock) {
        $this->_strTotalStock = $_strTotalStock;
    }

    function set_strPrecioVenta($_strPrecioVenta) {
        $this->_strPrecioVenta = $_strPrecioVenta;
    }

    function set_strPrecioCompra($_strPrecioCompra) {
        $this->_strPrecioCompra = $_strPrecioCompra;
    }

    function set_strPrecioVentaId($_strPrecioVentaId) {
        $this->_strPrecioVentaId = $_strPrecioVentaId;
    }

    function set_strPrecioCompraId($_strPrecioCompraId) {
        $this->_strPrecioCompraId = $_strPrecioCompraId;
    }
    
    
}
