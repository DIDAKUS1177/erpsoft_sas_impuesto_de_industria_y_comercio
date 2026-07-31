<?php
namespace erpsoftsas;
include_once 'class.DAO.php';

class DAO_CategoriasDocumental extends \erpsoftsas\DAOGeneral {
    
    protected $_cat_Id;
    protected $_cat_IdDependencia ;
    protected $_cat_Nombre ;
    protected $_cat_Descripcion ;
    protected $_cat_Sigla ;
    protected $_cat_Codigo ;
    protected $_cat_Estado ;

    protected $_strNombreSerieDocumental ;
    
    protected $_tabla = 'categorias_documental';
    protected $_primario = 'cat_Id';
    
    protected $_mapa = array(
        'cat_Id' => array('tipodato' => 'integer'),
        'cat_IdDependencia' => array('tipodato' => 'integer'),
        'cat_Nombre' => array('tipodato' => 'varchar'),
        'cat_Descripcion' => array('tipodato' => 'varchar'),
        'cat_Sigla' => array('tipodato' => 'varchar'),
        'cat_Codigo' => array('tipodato' => 'varchar'),
        'cat_Estado' => array('tipodato' => 'integer'),
        'strNombreSerieDocumental' => array('tipodato' => 'varchar','sql' => '(select dep.dep_Nombre from dependencia as dep where dep.dep_Id = categorias_documental.cat_IdDependencia)')
        
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    
    function get_cat_Id() {
        return $this->_cat_Id;
    }

    function get_cat_IdDependencia() {
        return $this->_cat_IdDependencia;
    }


    function get_cat_Nombre() {
        return $this->_cat_Nombre;
    }


    function get_cat_Descripcion() {
        return $this->_cat_Descripcion;
    }

    function get_cat_Sigla() {
        return $this->_cat_Sigla;
    }

    function get_cat_Codigo() {
        return $this->_cat_Codigo;
    }

    function get_cat_Estado() {
        return $this->_cat_Estado;
    }
    
    function get_strNombreSerieDocumental() {
        return $this->_strNombreSerieDocumental;
    }

    function set_cat_Id($_cat_Id) {
        $this->_cat_Id = $_cat_Id;
    }

    function set_cat_IdDependencia($_cat_IdDependencia) {
        $this->_cat_IdDependencia = $_cat_IdDependencia;
    }

    
    function set_cat_Nombre($_cat_Nombre) {
        $this->_cat_Nombre = $_cat_Nombre;
    }


    function set_cat_Descripcion($_cat_Descripcion) {
        $this->_cat_Descripcion = $_cat_Descripcion;
    }

    function set_cat_Sigla($_cat_Sigla) {
        $this->_cat_Sigla = $_cat_Sigla;
    }

    function set_cat_Codigo($_cat_Codigo) {
        $this->_cat_Codigo = $_cat_Codigo;
    }
    
    function set_cat_Estado($_cat_Estado) {
        $this->_cat_Estado = $_cat_Estado;
    }

        
    function set_strNombreSerieDocumental($_strNombreSerieDocumental) {
        $this->_strNombreSerieDocumental = $_strNombreSerieDocumental;
    }

}
