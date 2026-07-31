<?php
namespace erpsoftsas;

include_once 'class.DAO.php';

class DAO_Ciudades extends \erpsoftsas\DAOGeneral
{
    protected $_ciu_Id;
    protected $_ciu_Nombre;
    protected $_ciu_CodigoDane;
    protected $_ciu_Departamento;
    protected $_ciu_Estado;
    protected $_ciu_FechaCreacion;
    protected $_ciu_FechaActualizacion;

    protected $_tabla = 'conf_ciudades';
    protected $_primario = 'ciu_Id';

    protected $_mapa = array(
        'ciu_Id' => array('tipodato' => 'integer'),
        'ciu_CodigoDane' => array('tipodato' => 'varchar'),
        'ciu_Nombre' => array('tipodato' => 'varchar'),
        'ciu_Departamento' => array('tipodato' => 'varchar'),
        'ciu_Estado' => array('tipodato' => 'integer'),
        'ciu_FechaCreacion' => array('tipodato' => 'varchar'),
        'ciu_FechaActualizacion' => array('tipodato' => 'varchar')
    );

    public function get_ciu_Id(){ return $this->_ciu_Id; }
    public function set_ciu_Id($v){ $this->_ciu_Id = $v; }

    public function get_ciu_CodigoDane(){ return $this->_ciu_CodigoDane; }
    public function set_ciu_CodigoDane($v){ $this->_ciu_CodigoDane = $v; }

    public function get_ciu_Nombre(){ return $this->_ciu_Nombre; }
    public function set_ciu_Nombre($v){ $this->_ciu_Nombre = $v; }

    public function get_ciu_Departamento(){ return $this->_ciu_Departamento; }
    public function set_ciu_Departamento($v){ $this->_ciu_Departamento = $v; }

    public function get_ciu_Estado(){ return $this->_ciu_Estado; }
    public function set_ciu_Estado($v){ $this->_ciu_Estado = $v; }

    public function get_ciu_FechaCreacion(){ return $this->_ciu_FechaCreacion; }
    public function set_ciu_FechaCreacion($v){ $this->_ciu_FechaCreacion = $v; }

    public function get_ciu_FechaActualizacion(){ return $this->_ciu_FechaActualizacion; }
    public function set_ciu_FechaActualizacion($v){ $this->_ciu_FechaActualizacion = $v; }
}