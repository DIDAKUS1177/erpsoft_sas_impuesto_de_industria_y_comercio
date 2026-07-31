<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_SedesEmpresaMesas extends \predial\DAOGeneral {
    
    protected $_seemma_Id ;
    protected $_seemma_IdSedeEmpresa ;
    protected $_seemma_Nombre ;
    protected $_seemma_Estado ;
    protected $_seemma_FechaCreacion ;

    protected $_strNombreSede ;
    
    protected $_tabla = 'conf_sedes_empresa_mesas';
    protected $_primario = 'seemma_Id';
    
    protected $_mapa = array(
        'seemma_Id' => array('tipodato' => 'integer'),
        'seemma_IdSedeEmpresa' => array('tipodato' => 'integer'),
        'seemma_Nombre' => array('tipodato' => 'varchar'),
        'seemma_Estado' => array('tipodato' => 'integer'),
        'seemma_FechaCreacion' => array('tipodato' => 'varchar'),
        'strNombreSede' => array('tipodato' => 'varchar','sql' => '(select cse.seem_Nombre from conf_sedes_empresa as cse where cse.seem_Id = conf_sedes_empresa_mesas.seemma_IdSedeEmpresa)'),
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_seemma_Id() {
        return $this->_seemma_Id;
    }

    function get_seemma_IdSedeEmpresa() {
        return $this->_seemma_IdSedeEmpresa;
    }    

    function get_seemma_Nombre() {
        return $this->_seemma_Nombre;
    }    

    function get_seemma_Estado() {
        return $this->_seemma_Estado;
    }

    function get_seemma_FechaCreacion() {
        return $this->_seemma_FechaCreacion;
    }

    function get_strNombreSede() {
        return $this->_strNombreSede;
    }


    function set_seemma_Id($_seemma_Id) {
        $this->_seemma_Id = $_seemma_Id;
    }

    function set_seemma_Nombre($_seemma_Nombre) {
        $this->_seemma_Nombre = $_seemma_Nombre;
    }

    function set_seemma_IdSedeEmpresa($_seemma_IdSedeEmpresa) {
        $this->_seemma_IdSedeEmpresa = $_seemma_IdSedeEmpresa;
    }

    function set_seemma_Estado($_seemma_Estado) {
        $this->_seemma_Estado = $_seemma_Estado;
    }

    function set_seemma_FechaCreacion($_seemma_FechaCreacion) {
        $this->_seemma_FechaCreacion = $_seemma_FechaCreacion;
    }

    function set_strNombreSede($_strNombreSede) {
        $this->_strNombreSede = $_strNombreSede;
    }

}
