<?php
namespace erpsoftsas;

include_once 'class.DAO.php';

class DAO_DeclaracionesICA extends \erpsoftsas\DAOGeneral {

    protected $_dec_Id;
    protected $_dec_AnioDeclaracion;
    protected $_dec_MesDeclaracion;
    protected $_dec_NumeroDeclaracion;

    protected $_dec_IdContribuyente;
    protected $_dec_IdEstablecimiento;

    protected $_dec_FechaDeclaracion;
    protected $_dec_HoraDeclaracion;

    protected $_dec_ValorPago;
    protected $_dec_FechaPago;
    protected $_dec_Pagado;
    protected $_dec_BancoPago;

    protected $_dec_ValorConcepto1;
    protected $_dec_ValorConcepto2;
    protected $_dec_ValorConcepto3;
    protected $_dec_ValorConcepto4;
    protected $_dec_ValorConcepto5;
    protected $_dec_ValorConcepto6;
    protected $_dec_ValorConcepto7;
    protected $_dec_ValorConcepto8;
    protected $_dec_ValorConcepto9;
    protected $_dec_ValorConcepto10;
    protected $_dec_ValorConcepto11;
    protected $_dec_ValorConcepto12;
    protected $_dec_ValorConcepto13;
    protected $_dec_ValorConcepto14;
    protected $_dec_ValorConcepto15;
    protected $_dec_ValorConcepto16;
    protected $_dec_ValorConcepto17;
    protected $_dec_ValorConcepto18;
    protected $_dec_ValorConcepto19;
    protected $_dec_ValorConcepto20;
    protected $_dec_ValorConcepto21;
    protected $_dec_ValorConcepto22;
    protected $_dec_ValorConcepto23;
    protected $_dec_ValorConcepto24;
    protected $_dec_ValorConcepto25;
    protected $_dec_ValorConcepto26;
    protected $_dec_ValorConcepto27;
    protected $_dec_ValorConcepto28;
    protected $_dec_ValorConcepto29;
    protected $_dec_ValorConcepto30;

    protected $_dec_TotalCalculo;

    protected $_dec_Creador;
    protected $_dec_FechaCreador;
    protected $_dec_Modificador;
    protected $_dec_FechaModificador;

    protected $_dec_TotalIngresos;
    protected $_dec_IngresosFueraMunicipio;
    protected $_dec_IngresosDevoluciones;
    protected $_dec_IngresosExportaciones;
    protected $_dec_IngresosVentas;
    protected $_dec_IngresosActividades;
    protected $_dec_IngresosOtrasActividades;

    protected $_dec_DeclaracionCorrige;
    protected $_dec_VigenciaActual;
    protected $_dec_AnioPago;

    protected $_dec_FechaIntereses;
    protected $_dec_OpcionUso;

    protected $_dec_BaseGravable;

    protected $_dec_CuentaDebito;
    protected $_dec_FechaRealPago;

    protected $_dec_RutaDeclaracion;
    protected $_dec_RutaPago;


    protected $_tabla = 'ind_declaraciones_ica';
    protected $_primario = 'dec_Id';

    protected $_mapa = array(

        'dec_Id' => array('tipodato' => 'integer'),

        'dec_AnioDeclaracion' => array('tipodato' => 'integer'),
        'dec_MesDeclaracion' => array('tipodato' => 'integer'),
        'dec_NumeroDeclaracion' => array('tipodato' => 'integer'),

        'dec_IdContribuyente' => array('tipodato' => 'integer'),
        'dec_IdEstablecimiento' => array('tipodato' => 'integer'),

        'dec_FechaDeclaracion' => array('tipodato' => 'varchar'),
        'dec_HoraDeclaracion' => array('tipodato' => 'varchar'),

        'dec_ValorPago' => array('tipodato' => 'double'),
        'dec_FechaPago' => array('tipodato' => 'varchar'),
        'dec_Pagado' => array('tipodato' => 'integer'),
        'dec_BancoPago' => array('tipodato' => 'varchar'),

        'dec_ValorConcepto1' => array('tipodato' => 'double'),
        'dec_ValorConcepto2' => array('tipodato' => 'double'),
        'dec_ValorConcepto3' => array('tipodato' => 'double'),
        'dec_ValorConcepto4' => array('tipodato' => 'double'),
        'dec_ValorConcepto5' => array('tipodato' => 'double'),
        'dec_ValorConcepto6' => array('tipodato' => 'double'),
        'dec_ValorConcepto7' => array('tipodato' => 'double'),
        'dec_ValorConcepto8' => array('tipodato' => 'double'),
        'dec_ValorConcepto9' => array('tipodato' => 'double'),
        'dec_ValorConcepto10' => array('tipodato' => 'double'),
        'dec_ValorConcepto11' => array('tipodato' => 'double'),
        'dec_ValorConcepto12' => array('tipodato' => 'double'),
        'dec_ValorConcepto13' => array('tipodato' => 'double'),
        'dec_ValorConcepto14' => array('tipodato' => 'double'),
        'dec_ValorConcepto15' => array('tipodato' => 'double'),
        'dec_ValorConcepto16' => array('tipodato' => 'double'),
        'dec_ValorConcepto17' => array('tipodato' => 'double'),
        'dec_ValorConcepto18' => array('tipodato' => 'double'),
        'dec_ValorConcepto19' => array('tipodato' => 'double'),
        'dec_ValorConcepto20' => array('tipodato' => 'double'),
        'dec_ValorConcepto21' => array('tipodato' => 'double'),
        'dec_ValorConcepto22' => array('tipodato' => 'double'),
        'dec_ValorConcepto23' => array('tipodato' => 'double'),
        'dec_ValorConcepto24' => array('tipodato' => 'double'),
        'dec_ValorConcepto25' => array('tipodato' => 'double'),
        'dec_ValorConcepto26' => array('tipodato' => 'double'),
        'dec_ValorConcepto27' => array('tipodato' => 'double'),
        'dec_ValorConcepto28' => array('tipodato' => 'double'),
        'dec_ValorConcepto29' => array('tipodato' => 'double'),
        'dec_ValorConcepto30' => array('tipodato' => 'double'),

        'dec_TotalCalculo' => array('tipodato' => 'double'),

        'dec_Creador' => array('tipodato' => 'varchar'),
        'dec_FechaCreador' => array('tipodato' => 'varchar'),
        'dec_Modificador' => array('tipodato' => 'varchar'),
        'dec_FechaModificador' => array('tipodato' => 'varchar'),

        'dec_TotalIngresos' => array('tipodato' => 'double'),
        'dec_IngresosFueraMunicipio' => array('tipodato' => 'double'),
        'dec_IngresosDevoluciones' => array('tipodato' => 'double'),
        'dec_IngresosExportaciones' => array('tipodato' => 'double'),
        'dec_IngresosVentas' => array('tipodato' => 'double'),
        'dec_IngresosActividades' => array('tipodato' => 'double'),
        'dec_IngresosOtrasActividades' => array('tipodato' => 'double'),

        'dec_DeclaracionCorrige' => array('tipodato' => 'integer'),
        'dec_VigenciaActual' => array('tipodato' => 'integer'),
        'dec_AnioPago' => array('tipodato' => 'integer'),

        'dec_FechaIntereses' => array('tipodato' => 'varchar'),
        'dec_OpcionUso' => array('tipodato' => 'varchar'),

        'dec_BaseGravable' => array('tipodato' => 'double'),

        'dec_CuentaDebito' => array('tipodato' => 'varchar'),
        'dec_FechaRealPago' => array('tipodato' => 'varchar'),

        'dec_RutaDeclaracion' => array('tipodato' => 'varchar'),
        'dec_RutaPago' => array('tipodato' => 'varchar')
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