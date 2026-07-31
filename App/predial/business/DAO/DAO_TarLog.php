<?php

include_once 'class.DAO.php';

class DAO_TarLog extends \predial\DAOGeneral {
    
    protected $_id_log;
    protected $_id_usu;
    protected $_log_accion;
    protected $_log_id_usu_destino;
    protected $_log_useragent;
    protected $_log_fecha;
    protected $_log_hora;
    protected $_log_ip_client;
    protected $_browser_bits;
    protected $_browser;
    protected $_majorver;
    protected $_browser_type;
    protected $_browser_maker;
    protected $_platform;
    protected $_platform_version;
    protected $_platform_bits;
    protected $_platform_maker;
    protected $_device_type;
    protected $_device_pointing_method;
    protected $_device_brand_name;
    protected $_cssversion;
    protected $_javascript;
    protected $_cookies;

    protected $_tabla = 'doc_log';
    protected $_primario = 'id_log';
    
    protected $_mapa = array(
        'id_log' => array('tipodato' => 'integer'),
        'id_usu' => array('tipodato' => 'integer'),
        'log_accion' => array('tipodato' => 'varchar'),
        'browser_bits' => array('tipodato' => 'integer'),
        'browser' => array('tipodato' => 'varchar'),
        'majorver' => array('tipodato' => 'varchar'),
        'browser_type' => array('tipodato' => 'varchar'),
        'browser_maker' => array('tipodato' => 'varchar'),
        'platform' => array('tipodato' => 'varchar'),
        'platform_version' => array('tipodato' => 'varchar'),
        'platform_bits' => array('tipodato' => 'integer'),
        'platform_maker' => array('tipodato' => 'varchar'),
        'device_type' => array('tipodato' => 'varchar'),
        'device_pointing_method' => array('tipodato' => 'varchar'),
        'device_brand_name' => array('tipodato' => 'varchar'),
        'cssversion' => array('tipodato' => 'varchar'),
        'javascript' => array('tipodato' => 'integer'),
        'cookies' => array('tipodato' => 'integer'),
        'log_useragent' => array('tipodato' => 'varchar'),
        'log_ip_client' => array('tipodato' => 'varchar'),
        'log_fecha' => array('tipodato' => 'date'),
        'log_hora' => array('tipodato' => 'time'),
    );

    public function __construct() {
        parent::__construct();
    }
    function get_log_ip_client() {
        return $this->_log_ip_client;
    }

    function set_log_ip_client($_log_ip_client) {
        $this->_log_ip_client = $_log_ip_client;
    }
    function get_device_brand_name() {
        return $this->_device_brand_name;
    }

    function set_device_brand_name($_device_brand_name) {
        $this->_device_brand_name = $_device_brand_name;
    }

            
    function get_id_log() {
        return $this->_id_log;
    }

    function get_id_usu() {
        return $this->_id_usu;
    }

    function get_log_accion() {
        return $this->_log_accion;
    }
    function get_log_useragent() {
        return $this->_log_useragent;
    }
    function get_log_fecha() {
        return $this->_log_fecha;
    }
    function get_log_hora() {
        return $this->_log_hora;
    }

    function set_id_log($_id_log) {
        $this->_id_log = $_id_log;
    }

    function set_id_usu($_id_usu) {
        $this->_id_usu = $_id_usu;
    }

    function set_log_accion($_log_accion) {
        $this->_log_accion = $_log_accion;
    }
    function set_log_useragent($_log_useragent) {
        $this->_log_useragent = $_log_useragent;
    }
    function set_log_fecha($_log_fecha) {
        $this->_log_fecha = $_log_fecha;
    }
    function set_log_hora($_log_hora) {
        $this->_log_hora = $_log_hora;
    }
    function get_browser_bits() {
        return $this->_browser_bits;
    }
    function get_browser() {
        return $this->_browser;
    }
    function get_majorver() {
        return $this->_majorver;
    }

    function get_browser_type() {
        return $this->_browser_type;
    }

    function get_browser_maker() {
        return $this->_browser_maker;
    }

    function get_platform() {
        return $this->_platform;
    }

    function get_platform_version() {
        return $this->_platform_version;
    }

    function get_platform_bits() {
        return $this->_platform_bits;
    }

    function get_platform_maker() {
        return $this->_platform_maker;
    }

    function get_device_type() {
        return $this->_device_type;
    }

    function get_device_pointing_method() {
        return $this->_device_pointing_method;
    }

    function get_cssversion() {
        return $this->_cssversion;
    }

    function get_javascript() {
        return $this->_javascript;
    }

    function get_cookies() {
        return $this->_cookies;
    }

    function set_browser_bits($_browser_bits) {
        $this->_browser_bits = $_browser_bits;
    }

    function set_browser($_browser) {
        $this->_browser = $_browser;
    }

    function set_majorver($_majorver) {
        $this->_majorver = $_majorver;
    }

    function set_browser_type($_browser_type) {
        $this->_browser_type = $_browser_type;
    }

    function set_browser_maker($_browser_maker) {
        $this->_browser_maker = $_browser_maker;
    }

    function set_platform($_platform) {
        $this->_platform = $_platform;
    }

    function set_platform_version($_platform_version) {
        $this->_platform_version = $_platform_version;
    }

    function set_platform_bits($_platform_bits) {
        $this->_platform_bits = $_platform_bits;
    }

    function set_platform_maker($_platform_maker) {
        $this->_platform_maker = $_platform_maker;
    }

    function set_device_type($_device_type) {
        $this->_device_type = $_device_type;
    }

    function set_device_pointing_method($_device_pointing_method) {
        $this->_device_pointing_method = $_device_pointing_method;
    }

    function set_cssversion($_cssversion) {
        $this->_cssversion = $_cssversion;
    }

    function set_javascript($_javascript) {
        $this->_javascript = $_javascript;
    }

    function set_cookies($_cookies) {
        $this->_cookies = $_cookies;
    }



    

}
