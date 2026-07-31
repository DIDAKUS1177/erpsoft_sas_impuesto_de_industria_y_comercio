<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_SubCategoria extends \predial\DAOGeneral {
    
    protected $_subCate_Id ;
    protected $_subCate_Descripcion ;
    protected $_subCate_IdCategoria ;
    protected $_subCate_Estado ;
    protected $_subCate_FechaCreacion ;

    protected $_strNombreCategoria ;
    
    protected $_tabla = 'inv_sub_categoria';
    protected $_primario = 'subCate_Id';
    
    protected $_mapa = array(
        'subCate_Id' => array('tipodato' => 'integer'),
        'subCate_Descripcion' => array('tipodato' => 'varchar'),
        'subCate_IdCategoria' => array('tipodato' => 'integer'),
        'subCate_Estado' => array('tipodato' => 'integer'),
        'subCate_FechaCreacion' => array('tipodato' => 'varchar'),
        'strNombreCategoria' => array('tipodato' => 'integer','sql' => '(select inc.cate_Descripcion from inv_categoria as inc where inc.cate_Id = inv_sub_categoria.subCate_IdCategoria)')
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_subCate_Id() {
        return $this->_subCate_Id;
    }

    function get_subCate_Descripcion() {
        return $this->_subCate_Descripcion;
    }

    function get_subCate_IdCategoria() {
        return $this->_subCate_IdCategoria;
    }

    function get_subCate_Estado() {
        return $this->_subCate_Estado;
    }

    function get_subCate_FechaCreacion() {
        return $this->_subCate_FechaCreacion;
    }

    function get_strNombreCategoria() {
        return $this->_strNombreCategoria;
    }

    function set_subCate_Id($_subCate_Id) {
        $this->_subCate_Id = $_subCate_Id;
    }

    function set_subCate_Descripcion($_subCate_Descripcion) {
        $this->_subCate_Descripcion = $_subCate_Descripcion;
    }

    function set_subCate_IdCategoria($_subCate_IdCategoria) {
        $this->_subCate_IdCategoria = $_subCate_IdCategoria;
    }

    function set_subCate_Estado($_subCate_Estado) {
        $this->_subCate_Estado = $_subCate_Estado;
    }

    function set_subCate_FechaCreacion($_subCate_FechaCreacion) {
        $this->_subCate_FechaCreacion = $_subCate_FechaCreacion;
    }

    function set_strNombreCategoria($_strNombreCategoria) {
        $this->_strNombreCategoria = $_strNombreCategoria;
    }
    
}
