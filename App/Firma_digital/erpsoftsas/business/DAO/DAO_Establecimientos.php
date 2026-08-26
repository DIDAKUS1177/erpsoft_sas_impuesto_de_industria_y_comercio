<?php
namespace erpsoftsas;
include_once 'class.DAO.php';

class DAO_Establecimientos extends \erpsoftsas\DAOGeneral {
    
    protected $_est_Id;
    protected $_est_Codigo;
    protected $_est_IdContribuyente;
    protected $_est_Nombre;
    protected $_est_Direccion;
    protected $_est_Pais;
    protected $_est_Departamento;
    protected $_est_Ciudad;
    protected $_est_Barrio;
    protected $_est_Correo;
    protected $_est_Activos;
    protected $_est_Area;
    protected $_est_Opcion_uso;
    protected $_est_Causal;
    protected $_est_Cedula_representante;
    protected $_est_Nombre_representante;
    protected $_est_Email_representante;
    protected $_est_Matricula;
    protected $_est_Fecha_matricula;
    protected $_est_Fecha_inscripcion;
    protected $_est_Fecha_inicio;
    protected $_est_Excento_avisos;
    protected $_est_Exento;
    protected $_est_Local_municipio;

    protected $_est_Rut;
    protected $_est_Fecha_actividad;
    protected $_est_Rut_segundo;
    protected $_est_Rut_tercero;

    protected $_est_Cedula_contador;
    protected $_est_Nombre_contador;
    protected $_est_Tarjeta_profesional;
    protected $_est_Cedula_revisor;
    protected $_est_Nombre_revisor;
    protected $_est_Tarjeta_profesional_revisor;
    
    protected $_est_Observacion_cierre;
    /** Nota del estado del registro (migracion 018). Distinta de la del cese. */
    protected $_est_Observacion;

    protected $_est_Autorizacion;
    
    protected $_est_Activo;
    protected $_est_Ind_camara_comercio;
    

    // No se han activado
    protected $_est_Codigo_catastral;
    
    protected $_est_Fecha_cierre;
    protected $_est_Resolucion_cierre;

    
 
    protected $_est_Ultimo_ano_pago;
    protected $_est_Estado_registro;
    protected $_est_Ruta_anexos;

    protected $_est_Creador;
    protected $_est_Fecha_creador;

    protected $_strNombreContribuyente;
    protected $_strDocumentoContribuyente;

    protected $_tabla = 'ind_establecimientos';
    protected $_primario = 'est_Id';

    protected $_mapa = array(
        'est_Id' => array('tipodato' => 'integer'),
        'est_Codigo' => array('tipodato' => 'integer'),
        'est_IdContribuyente' => array('tipodato' => 'integer'),
        'est_Nombre' => array('tipodato' => 'varchar'),
        'est_Direccion' => array('tipodato' => 'varchar'),
        'est_Pais' => array('tipodato' => 'varchar'),
        'est_Departamento' => array('tipodato' => 'varchar'),
        'est_Ciudad' => array('tipodato' => 'varchar'),
        'est_Barrio' => array('tipodato' => 'varchar'),
        'est_Correo' => array('tipodato' => 'varchar'),
        'est_Activos' => array('tipodato' => 'integer'),
        'est_Local_municipio' => array('tipodato' => 'integer'),
        'est_Matricula' => array('tipodato' => 'varchar'),
        'est_Fecha_matricula' => array('tipodato' => 'varchar'),
        'est_Fecha_inscripcion' => array('tipodato' => 'varchar'),
        'est_Fecha_inicio' => array('tipodato' => 'varchar'),
        'est_Activo' => array('tipodato' => 'integer'),
        'est_Autorizacion'=> array('tipodato' => 'integer'),
        'est_Observacion_cierre' => array('tipodato' => 'varchar'),
        'est_Observacion' => array('tipodato' => 'varchar'),
        'est_Fecha_cierre' => array('tipodato' => 'varchar'),
        'est_Resolucion_cierre' => array('tipodato' => 'varchar'),
        'est_Area' => array('tipodato' => 'varchar'),
        'est_Creador' => array('tipodato' => 'varchar'),
        'est_Fecha_creador' => array('tipodato' => 'varchar'),
        'est_Excento_avisos' => array('tipodato' => 'integer'),
        'est_Ultimo_ano_pago' => array('tipodato' => 'integer'),
        'est_Estado_registro' => array('tipodato' => 'varchar'),
        'est_Local_municipio' => array('tipodato' => 'integer'),
        'est_Codigo_catastral' => array('tipodato' => 'varchar'),
        'est_Opcion_uso' => array('tipodato' => 'varchar'),
        'est_Causal' => array('tipodato' => 'varchar'),
        'est_Cedula_representante' => array('tipodato' => 'varchar'),
        'est_Nombre_representante' => array('tipodato' => 'varchar'),
        'est_Email_representante' => array('tipodato' => 'varchar'),
        'est_Cedula_contador' => array('tipodato' => 'varchar'),
        'est_Nombre_contador' => array('tipodato' => 'varchar'),
        'est_Tarjeta_profesional' => array('tipodato' => 'varchar'),
        'est_Cedula_revisor' => array('tipodato' => 'varchar'),
        'est_Nombre_revisor' => array('tipodato' => 'varchar'),
        'est_Ruta_anexos' => array('tipodato' => 'varchar'),
        'est_Tarjeta_profesional_revisor' => array('tipodato' => 'varchar'),
        'est_Exento' => array('tipodato' => 'integer'),
        'est_Rut' => array('tipodato' => 'varchar'),
        'est_Fecha_actividad' => array('tipodato' => 'varchar'),
        'est_Rut_segundo' => array('tipodato' => 'varchar'),
        'est_Rut_tercero' => array('tipodato' => 'varchar'),
        'est_Ind_camara_comercio' => array('tipodato' => 'integer'),
        'strNombreContribuyente' => array('tipodato' => 'varchar','sql' => '(select CONCAT(con.ind_PrimerNombre,\' \',con.ind_PrimerApellido) from ind_contribuyentes as con where con.ind_Id = ind_establecimientos.est_IdContribuyente)'),
        'strDocumentoContribuyente' => array('tipodato' => 'varchar','sql' => '(select con.ind_NumeroIdentificacion from ind_contribuyentes as con where con.ind_Id = ind_establecimientos.est_IdContribuyente)')
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
