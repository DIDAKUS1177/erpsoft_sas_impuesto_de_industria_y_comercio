<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of class
 *
 * @author luis.caceres
 */
include_once '../DAO/DAO_Usuarios.php';
include_once $_SERVER['DOCUMENT_ROOT'].'/erpsoftsas/business/class.sessions.php';

abstract class AuthStatus {
    const FAIL = "Error en la autenticación<br>";
    const OK = "Autenticación OK<br>";
    const CONNECT = "Conexión OK<br>";
    const SERVER_FAIL = "No se puede realizar la conexión con el servisdor LDAP<br>";
    const ANONYMOUS = "login anonimo<br>";
    const PROTOCOL = "Imposible asignar el Protocolo LDAP<br>";
    const PROTOCOL_OK = "Protocolo LDAP OK<br>";
    const BLIND_FAIL = "BLIND LDAP FAIL<br>";
    const BLIND_OK = "BLIND LDAP OK<br>";
    const USER_FAIL = "DATOS INCORRECTOS<br>";
}

class LDAP extends Cabecera {

    private $_ldap_host = "uauth.uniminuto.edu";
    private $_ldap_port = "389";
    private $_ldap_user = "uid=uvd,dc=umd,dc=local";
    private $_ldap_pswd = "BGu5mayP!QO!Z!6x";
    private $_user_mail;
    private $_user_pass;
    
    private $_dn;
    private $_ad;

    public static function run() {
        $_obj = new self();
        try{
            $_obj->_establecerDatos();
            $_obj->_autenticarLDAP();
            
        }catch(ConexionLDAPException $e){
            echo $e->getMessage();
        }
        //$_obj->_establecerDatos();     
    }

    private function _establecerDatos() {
        //print_r($_POST);
        $this->_user_mail = $_POST['u_correo_inst'];
        $this->_user_pass = $_POST['u_id_genesis'];  
        $user = explode("@", $this->_user_mail);
        if ( $user[1] == 'uniminuto.edu' ) {
            $this->_dn = 'ou=administrativos,dc=umd,dc=local';
        }else if($user[1] == 'uniminuto.edu.co') {
            $this->_dn = 'ou=academicos,dc=umd,dc=local';
        }      
    }

    private function _autenticarLDAP() {
        $this->_ad = @ldap_connect($this->_ldap_host, $this->_ldap_port); 
        if (!$this->_ad) {
            throw new ConexionLDAPException(AuthStatus::SERVER_FAIL . ldap_errno($this->_ad));      
        } else {
            //echo AuthStatus::CONNECT;
        }

        if (@!ldap_set_option($this->_ad, LDAP_OPT_PROTOCOL_VERSION, 3)) {
            throw new ConexionLDAPException(AuthStatus::PROTOCOL . ldap_errno($this->_ad));
        } else {
            //echo AuthStatus::PROTOCOL_OK;
        }
        $bd = @ldap_bind($this->_ad, $this->_ldap_user, $this->_ldap_pswd);
        if (!$bd) {
            throw new ConexionLDAPException(AuthStatus::BLIND_FAIL . ldap_errno($this->_ad));
        } else {
            //echo AuthStatus::BLIND_OK;
        }
        $this->_verificarDatosUsuario();
    }

    private function _verificarDatosUsuario() {
        //$attrs = array("cn", "mail", "pager", "sn", "givenname");
        $attrs = array("cn", "mail","pager","homephone","l","o", "description", "ou", "givenname", "sn");
        // Creo el filtro para la busqueda
        $filter = "(mail=$this->_user_mail)";
        //$filter="(|(mail=$this->_user_mail*)(pager=$this->_user_idge*))";        
        $search = @ldap_search($this->_ad, $this->_dn, $filter, $attrs)
                or die("");
        $entries = ldap_get_entries($this->_ad, $search);
        $arrRespu = array();
        if ($entries["count"] > 0) {       
               //print_r($entries);
            if ( !@ldap_bind($this->_ad, $entries[0]['dn'], $this->_user_pass) ) {  
                $arrRespu = array("ok" => "0", "mensaje" => "Error en las credenciales");
                //throw new ConexionLDAPException(AuthStatus::USER_FAIL . ldap_errno($this->_ad));
            }else{
                //print_r($entries[0]);
                // hacer consulta en dextera para comprobar si datos del usuario están actualizados
                $_objUsuario = new DAO_Usuarios();
                $_objUsuario->set_u_correo_inst($entries[0]["mail"][0]);
                $_objUsuario->consultar();
                $estado = $_objUsuario->get_u_estado();
                // respuesta
                $arrRespu = array(
                    "ok" => 1, 
                    "mensaje" => "Bienvenido {$entries[0]["givenname"][0]} {$entries[0]["sn"][0]}", 
                    "mail" => "{$entries[0]["mail"][0]}", 
                    "data" => $entries,
                    'actualizado' => $estado ); 
                if($estado == 1){
                    \erpsoftsas\SesionUsuario::initSession($_objUsuario);
                }
            }       
        } else {
            $arrRespu = array("ok" => "0", "mensaje" => "Error en las credenciales");
            //echo AuthStatus::USER_FAIL;
        }
        echo json_encode($arrRespu);
        ldap_unbind($this->_ad);
        // 
        
    }

}

class ConexionLDAPException extends \Exception {
    
}

LDAP::run();
