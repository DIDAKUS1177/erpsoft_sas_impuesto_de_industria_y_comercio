<?php
namespace predial;
include_once 'class.DAO.php';

class DAO_Usuario extends \predial\DAOGeneral {
    
    protected $_usu_Id ;
    protected $_usu_NumeroDocumento ;
    protected $_usu_Nombre ;
    protected $_usu_Usuario ;
    protected $_usu_Correo ;
    protected $_usu_Password ;
    protected $_usu_Estado ;
    protected $_usu_Rol ;
    
    protected $_usu_NombreRol ;
    protected $_strIdBodega ;
    protected $_strNombreBodega ;
    protected $_strNombreSede ;
    protected $_strNombreCaja ;
    protected $_strCodigoCaja ;
    protected $_strIdCaja ;
    protected $_strIdSede ;
    protected $_strIdUsuarioCaja ;
    protected $_strNombreEmpresa ;
    protected $_strTipoImpresora ;
    protected $_strTipoFactura ;
    
    protected $_tabla = 'conf_usuario';
    protected $_primario = 'usu_Id';
    
    protected $_mapa = array(
        'usu_Id' => array('tipodato' => 'integer'),
        'usu_NumeroDocumento' => array('tipodato' => 'varchar'),
        'usu_Nombre' => array('tipodato' => 'varchar'),
        'usu_Usuario' => array('tipodato' => 'varchar'),
        'usu_Correo' => array('tipodato' => 'varchar'),
        'usu_Estado' => array('tipodato' => 'integer'),
        'usu_Password' => array('tipodato' => 'clave'),
        'usu_Rol' => array('tipodato' => 'integer'),
        'usu_NombreRol' => array('tipodato' => 'integer','sql' => '(select rol.rol_Nombre from  conf_rol as rol where rol.rol_Id = conf_usuario.usu_Rol)'),
        'strIdBodega' => array('tipodato' => 'integer','sql' => '(select ise.seem_IdBodega from conf_usuario_caja as cuc INNER JOIN conf_sedes_empresa as ise on cuc.usuca_IdSede = ise.seem_Id INNER JOIN inv_bodega as ib on ise.seem_IdBodega = ib.bod_Id where cuc.usuca_IdVendedor = conf_usuario.usu_Id)'),
        'strNombreBodega' => array('tipodato' => 'varchar','sql' => '(select ib.bod_Nombre from conf_usuario_caja as cuc INNER JOIN conf_sedes_empresa as ise on cuc.usuca_IdSede = ise.seem_Id INNER JOIN inv_bodega as ib on ise.seem_IdBodega = ib.bod_Id where cuc.usuca_IdVendedor = conf_usuario.usu_Id)'),
        'strNombreSede' => array('tipodato' => 'varchar','sql' => '(select ise.seem_Nombre from conf_usuario_caja as cuc INNER JOIN conf_sedes_empresa as ise on cuc.usuca_IdSede = ise.seem_Id where cuc.usuca_IdVendedor = conf_usuario.usu_Id)'),
//        'strNombreCaja' => array('tipodato' => 'varchar','sql' => '(select csee.seemca_Nombre from conf_usuario_caja as cuc INNER JOIN conf_sedes_empresa as ise on cuc.usuca_IdSede = ise.seem_Id INNER JOIN conf_sedes_empresa_cajas as csee on ise.seem_Id = csee.seemca_IdSedeEmpresa where cuc.usuca_IdVendedor = conf_usuario.usu_Id)'),
        'strNombreCaja' => array('tipodato' => 'varchar','sql' => '(select csee.seemca_Nombre from conf_usuario_caja as cuc INNER JOIN conf_sedes_empresa_cajas as csee on cuc.usuca_IdCaja = csee.seemca_Id where cuc.usuca_IdVendedor = conf_usuario.usu_Id)'),
        'strCodigoCaja' => array('tipodato' => 'varchar','sql' => '(select csee.seemca_CodigoCaja from conf_usuario_caja as cuc INNER JOIN conf_sedes_empresa_cajas as csee on cuc.usuca_IdCaja = csee.seemca_Id where cuc.usuca_IdVendedor = conf_usuario.usu_Id)'),

        'strIdSede' => array('tipodato' => 'varchar','sql' => '(select ise.seem_Id from conf_usuario_caja as cuc INNER JOIN conf_sedes_empresa as ise on cuc.usuca_IdSede = ise.seem_Id where cuc.usuca_IdVendedor = conf_usuario.usu_Id)'),
//        'strIdCaja' => array('tipodato' => 'varchar','sql' => '(select csee.seemca_Id from conf_usuario_caja as cuc INNER JOIN conf_sedes_empresa as ise on cuc.usuca_IdSede = ise.seem_Id INNER JOIN conf_sedes_empresa_cajas as csee on ise.seem_Id = csee.seemca_IdSedeEmpresa where cuc.usuca_IdVendedor = conf_usuario.usu_Id)'),
        'strIdCaja' => array('tipodato' => 'varchar','sql' => '(select cuc.usuca_IdCaja  from conf_usuario_caja as cuc where cuc.usuca_IdVendedor = conf_usuario.usu_Id)'),
        'strIdUsuarioCaja' => array('tipodato' => 'varchar','sql' => '(select usuca.usuca_Id from conf_usuario_caja as usuca where usuca.usuca_IdVendedor = conf_usuario.usu_Id)'),
        'strNombreEmpresa' => array('tipodato' => 'varchar','sql' => '(select coem.emp_Nombre from conf_empresa as coem)'),
        'strTipoImpresora' => array('tipodato' => 'varchar','sql' => '(select coem.emp_TipoImpresora from conf_empresa as coem)'),
        'strTipoFactura' => array('tipodato' => 'varchar','sql' => '(select coem.emp_TipoPantalla from conf_empresa as coem)'),
    );   
    
    public function __construct() {
        parent::__construct();
    }
    
    function get_usu_Id() {
        return $this->_usu_Id;
    }

    function get_usu_NumeroDocumento() {
        return $this->_usu_NumeroDocumento;
    }

    function get_usu_Nombre() {
        return $this->_usu_Nombre;
    }
    
    function get_usu_Usuario() {
        return $this->_usu_Usuario;
    }

    function get_usu_Correo() {
        return $this->_usu_Correo;
    }

    function get_usu_Password() {
        return $this->_usu_Password;
    }

    function get_usu_Estado() {
        return $this->_usu_Estado;
    }

    function get_usu_Rol() {
        return $this->_usu_Rol;
    }

    function get_usu_NombreRol() {
        return $this->_usu_NombreRol;
    }

    function get_strIdBodega() {
        return $this->_strIdBodega;
    }

    function get_strNombreBodega() {
        return $this->_strNombreBodega;
    }

    function get_strNombreSede() {
        return $this->_strNombreSede;
    }

    function get_strNombreCaja() {
        return $this->_strNombreCaja;
    }

    function get_strCodigoCaja() {
        return $this->_strCodigoCaja;
    }

    function get_strIdSede() {
        return $this->_strIdSede;
    }

    function get_strIdCaja() {
        return $this->_strIdCaja;
    }

    function get_strIdUsuarioCaja() {
        return $this->_strIdUsuarioCaja;
    }
    
    function get_strNombreEmpresa() {
        return $this->_strNombreEmpresa;
    }
    
    function get_strTipoImpresora() {
        return $this->_strTipoImpresora;
    }
    
    function get_strTipoFactura() {
        return $this->_strTipoFactura;
    }
    
    function set_usu_Id($_usu_Id) {
        $this->_usu_Id = $_usu_Id;
    }

    function set_usu_NumeroDocumento($_usu_NumeroDocumento) {
        $this->_usu_NumeroDocumento = $_usu_NumeroDocumento;
    }

    function set_usu_Nombre($_usu_Nombre) {
        $this->_usu_Nombre = $_usu_Nombre;
    }

    function set_usu_Usuario($_usu_Usuario) {
        $this->_usu_Usuario = $_usu_Usuario;
    }

    function set_usu_Correo($_usu_Correo) {
        $this->_usu_Correo = $_usu_Correo;
    }

    function set_usu_Password($_usu_Password) {
        $this->_usu_Password = $_usu_Password;
    }

    function set_usu_Estado($_usu_Estado) {
        $this->_usu_Estado = $_usu_Estado;
    }

    function set_usu_Rol($_usu_Rol) {
        $this->_usu_Rol = $_usu_Rol;
    }

    function set_usu_NombreRol($_usu_NombreRol) {
        $this->_usu_NombreRol = $_usu_NombreRol;
    }

    function set_strIdBodega($_strIdBodega) {
        $this->_strIdBodega = $_strIdBodega;
    }

    function set_strNombreBodega($_strNombreBodega) {
        $this->_strNombreBodega = $_strNombreBodega;
    }

    function set_strNombreSede($_strNombreSede) {
        $this->_strNombreSede = $_strNombreSede;
    }
    
    function set_strNombreCaja($_strNombreCaja) {
        $this->_strNombreCaja = $_strNombreCaja;
    }

    function set_strCodigoCaja($_strCodigoCaja) {
        $this->_strCodigoCaja = $_strCodigoCaja;
    }

    function set_strIdSede($_strIdSede) {
        $this->_strIdSede = $_strIdSede;
    }

    function set_strIdCaja($_strIdCaja) {
        $this->_strIdCaja = $_strIdCaja;
    }

    function set_strIdUsuarioCaja($_strIdUsuarioCaja) {
        $this->_strIdUsuarioCaja = $_strIdUsuarioCaja;
    }

    function set_strNombreEmpresa($_strNombreEmpresa) {
        $this->_strNombreEmpresa = $_strNombreEmpresa;
    }
    
    function set_strTipoImpresora($_strTipoImpresora) {
        $this->_strTipoImpresora = $_strTipoImpresora;
    }

    function set_strTipoFactura($_strTipoFactura) {
        $this->_strTipoFactura = $_strTipoFactura;
    }
    
}
