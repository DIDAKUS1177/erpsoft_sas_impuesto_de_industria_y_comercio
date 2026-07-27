<?php
namespace erpsoftsas;

include_once 'class.DAO.php';

class DAO_ActividadEstablecimiento extends \erpsoftsas\DAOGeneral {

    protected $_ace_Id;
    protected $_ace_IdCodigoActividad;
    protected $_ace_IdEstablecimiento;
    protected $_ace_Anio;
    protected $_ace_FechaCreacion;
    protected $_ace_FechaActualizacion;

    protected $_tabla = 'ind_actividad_establecimiento';
    protected $_primario = 'ace_Id';

    protected $_mapa = array(

        'ace_Id' => array(
            'tipodato' => 'integer'
        ),

        'ace_IdCodigoActividad' => array(
            'tipodato' => 'integer'
        ),

        'ace_IdEstablecimiento' => array(
            'tipodato' => 'integer'
        ),

        'ace_Anio' => array(
            'tipodato' => 'integer'
        ),

        'ace_FechaCreacion' => array(
            'tipodato' => 'varchar'
        ),

        'ace_FechaActualizacion' => array(
            'tipodato' => 'varchar'
        )

    );

    public function __construct() {
        parent::__construct();
    }

    public function __call($name, $arguments) {

        $property = '_' . lcfirst(substr($name, 4));

        if (strpos($name, 'get_') === 0 && property_exists($this, $property)) {
            return $this->$property;
        }

        if (strpos($name, 'set_') === 0 && property_exists($this, $property)) {
            $this->$property = $arguments[0];
        }
    }
    

}