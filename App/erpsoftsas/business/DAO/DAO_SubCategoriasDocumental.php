<?php
namespace erpsoftsas;
include_once 'class.DAO.php';

class DAO_SubCategoriasDocumental extends \erpsoftsas\DAOGeneral {
    
    protected $_subc_Id;
    protected $_subc_IdCategoria ;
    protected $_subc_Nombre ;
    protected $_subc_Descripcion ;
    protected $_subc_Sigla ;
    protected $_subc_Codigo ;
    protected $_subc_Estado ;

    protected $_tabla = 'sub_categorias_documental';
    protected $_primario = 'subc_Id';
    
    protected $_mapa = array(
        'subc_Id' => array('tipodato' => 'integer'),
        'subc_IdCategoria' => array('tipodato' => 'varchar'),
        'subc_Nombre' => array('tipodato' => 'varchar'),
        'subc_Descripcion' => array('tipodato' => 'varchar'),
        'subc_Sigla' => array('tipodato' => 'varchar'),
        'subc_Codigo' => array('tipodato' => 'varchar'),
        'subc_Estado' => array('tipodato' => 'integer')
    );   
    
    public function __construct() {
        parent::__construct();
    }
    

    function get_subc_Id() {
        return $this->_subc_Id;
    }


    function get_subc_IdCategoria() {
        return $this->_subc_IdCategoria;
    }


    function get_subc_Nombre() {
        return $this->_subc_Nombre;
    }


    function get_subc_Descripcion() {
        return $this->_subc_Descripcion;
    }

    function get_subc_Codigo() {
        return $this->_subc_Codigo;
    }

    function get_subc_Sigla() {
        return $this->_subc_Sigla;
    }


    function get_subc_Estado() {
        return $this->_subc_Estado;
    }
    
    function set_subc_Id($_subc_Id) {
        $this->_subc_Id = $_subc_Id;
    }


    function set_subc_IdCategoria($_subc_IdCategoria) {
        $this->_subc_IdCategoria = $_subc_IdCategoria;
    }


    function set_subc_Nombre($_subc_Nombre) {
        $this->_subc_Nombre = $_subc_Nombre;
    }

    function set_subc_Descripcion($_subc_Descripcion) {
        $this->_subc_Descripcion = $_subc_Descripcion;
    }

    function set_subc_Sigla($_subc_Sigla) {
        $this->_subc_Sigla = $_subc_Sigla;
    }

    function set_subc_Codigo($_subc_Codigo) {
        $this->_subc_Codigo = $_subc_Codigo;
    }
    
    function set_subc_Estado($_subc_Estado) {
        $this->_subc_Estado = $_subc_Estado;
    }
    
}
