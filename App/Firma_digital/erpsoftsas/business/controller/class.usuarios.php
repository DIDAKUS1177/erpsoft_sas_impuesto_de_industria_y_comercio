<?php
namespace erpsoftsas;
include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/DAO/DAO_Usuario.php';
include_once SERVER . '/business/DAO/DAO_Contribuyentes.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER.'/business/controller/class.logs.php';

class ControladorUsuarios extends \erpsoftsas\Cabecera {

    private $_funcion;
    private $_ok;
    private $_mensaje;   
        
    public static function run() {
        //\erpsoftsas\SesionUsuario::verificarSesion();
        
        $_obj = new self();
        $_obj->_funcion = $_POST['funcion'];
        
        try {
            //$con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
            //$con->begin();
            $respuesta = null;
            switch ($_obj->_funcion) {
                case 1:
                    $respuesta = $_obj->_agregarUsuario();
                    break;
                case 2:
                    $respuesta = $_obj->_editarUsuario();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarUsuarios();
                    break; 
                case 4:
                    $respuesta = $_obj->_inactivarUsuarios();
                    break; 
                case 5:
                    $respuesta = $_obj->_recuperarUsuario();
                    break;
            }
            //$con->commit();
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
            
        } catch (\erpsoftsas\UsuariosException $e) {
            //$con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    /**
    *** Realiza el proceso de Crear Usuarios.
    **/  
    protected function _agregarUsuario() {
        
        $_objUsuario = new \erpsoftsas\DAO_Usuario();
        $_objUsuario->set_usu_Nombres($_POST['nombres']);
        $_objUsuario->set_usu_Apellidos($_POST['apellidos']);
        $_objUsuario->set_usu_Telefono($_POST['telefono']);
        $_objUsuario->set_usu_Direccion($_POST['direccion']);
        $_objUsuario->set_usu_IdTipoDocumento($_POST['idTipoDocumento']);
        $_objUsuario->set_usu_NumeroDocumento($_POST['numeroDocumento']);
        $_objUsuario->set_usu_Correo($_POST['email']);
        $_objUsuario->set_usu_Password($_POST['clave']);
        $_objUsuario->set_usu_Rol($_POST['id_rol']);
        $_objUsuario->set_usu_Usuario($_POST['usuario']);
        
        $_objUsuario->set_usu_Estado(1);
      
        //Valida los campos que no pueden Duplicarsen en la BD.
        //$nomUsurio= $this->_listarUsuarios(0);
        // 🔹 Usa el nuevo método genérico del DAO
        $nomUsurio = $_objUsuario->listarRegistros(0);
        $longitud = count($nomUsurio);
        $nomduplicado=0;

        for($i=0; $i<$longitud; $i++){  
            if($nomUsurio[$i]['usu_Correo'] == $_objUsuario->get_usu_Correo()){
               $nomduplicado=1;
                break;
            }
            if($nomUsurio[$i]['usu_NumeroDocumento'] == $_objUsuario->get_usu_NumeroDocumento()){
               $nomduplicado=2;
                break;
            }
            if($nomUsurio[$i]['usu_Usuario'] == $_objUsuario->get_usu_Usuario()){
                $nomduplicado=3;
                 break;
             }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un usuario con el mismo email';
            $return= false; 
        }else if($nomduplicado == 2){
            $this->_ok = 3;
            $this->_mensaje = 'Ya existe un usuario con la misma identificación';
            $return= false;   
        }else if($nomduplicado == 3){
            $this->_ok = 4;
            $this->_mensaje = 'Ya existe un usuario con el mismo Usuario';
            $return= false;   
        }else{
            if(!$_objUsuario->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objUsuario->getMysqlError();
            }else{
                $id = $_objUsuario->get_usu_Id();
                
                // Si el usuario se creó correctamente, verificamos si el contribuyente existe
                $_objContribuyente = new \erpsoftsas\DAO_Contribuyentes();
                $_objContribuyente->set_ind_NumeroIdentificacion($_POST['numeroDocumento']);
                $_objContribuyente->habilita1ResultadoEnArray();

                $existeContribuyente = $_objContribuyente->consultar();

                if (!is_array($existeContribuyente) || count($existeContribuyente) == 0) {

                    // No existe → Crear contribuyente
                    $_objContribuyenteNuevo = new \erpsoftsas\DAO_Contribuyentes();

                    $_objContribuyenteNuevo->set_ind_NumeroIdentificacion($_POST['numeroDocumento']);
                    $_objContribuyenteNuevo->set_ind_IdTipoDocumento($_POST['idTipoDocumento']);
                    $_objContribuyenteNuevo->set_ind_DV($_POST['DV']);
                    $_objContribuyenteNuevo->set_ind_PrimerNombre($_POST['nombres']);
                    $_objContribuyenteNuevo->set_ind_PrimerApellido($_POST['apellidos']);
                    $_objContribuyenteNuevo->set_ind_Direccion($_POST['direccion']);
                    $_objContribuyenteNuevo->set_ind_Telefono($_POST['telefono']);
                    $_objContribuyenteNuevo->set_ind_Email($_POST['email']);
                    $_objContribuyenteNuevo->set_ind_Persona($_POST['tipoPersona']);
                    $_objContribuyenteNuevo->set_ind_IdCiudad(1);
                    $_objContribuyenteNuevo->set_ind_Estado(1);

                    if (!$_objContribuyenteNuevo->guardar()) {
                        $this->_ok = 5;
                        $this->_mensaje = $_objContribuyenteNuevo->getMysqlError();
                        return false;
                    }
                }
                
                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($id,1,2,7);
                $this->_ok = 1;
                $this->_mensaje = "Datos ingresados correctamente";
            }
            $return= $_objUsuario->guardar();
        }
        return $return;
    }
    
    /**
    *** Realiza el proceso de Editar usuarios.
    **/  
    protected function _editarUsuario() {
        
        $_objUsuario = new \erpsoftsas\DAO_Usuario();
        $_objUsuario->set_usu_Id($_POST['id']);
        $_objUsuario->set_usu_Nombres($_POST['nombres']);
        $_objUsuario->set_usu_Apellidos($_POST['apellidos']);
        $_objUsuario->set_usu_Telefono($_POST['telefono']);
        $_objUsuario->set_usu_Direccion($_POST['direccion']);
        $_objUsuario->set_usu_Usuario($_POST['usuario']);
        $_objUsuario->set_usu_NumeroDocumento($_POST['numeroDocumento']);
        $_objUsuario->set_usu_IdTipoDocumento($_POST['idTipoDocumento']);
        $_objUsuario->set_usu_Correo($_POST['email']);
        $_objUsuario->set_usu_Password($_POST['clave']);
        $_objUsuario->set_usu_Rol($_POST['id_rol']);

        //Valida los campos que no pueden Duplicarsen en la BD.
        //$nomUsurio= $this->_listarUsuarios($_objUsuario->get_usu_Id());
        $nomUsurio = $_objUsuario->listarRegistros($_objUsuario->get_usu_Id());
        
        $longitud = count($nomUsurio);
        $nomduplicado=0;
        for($i=0; $i<$longitud; $i++){  
            if($nomUsurio[$i]['usu_Correo'] == $_objUsuario->get_usu_Correo()){
               $nomduplicado=1;
                break;
            }
            if($nomUsurio[$i]['usu_NumeroDocumento'] == $_objUsuario->get_usu_NumeroDocumento()){
               $nomduplicado=2;
                break;
            }
            if($nomUsurio[$i]['usu_Usuario'] == $_objUsuario->get_usu_Usuario()){
                $nomduplicado=3;
                 break;
             }
        }

        if($nomduplicado == 1){
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un usuario con el mismo email';
            $return= false; 
        }else if($nomduplicado == 2){
            $this->_ok = 3;
            $this->_mensaje = 'Ya existe un usuario con la misma identificación';
            $return= false;   
        }else if($nomduplicado == 3){
            $this->_ok = 4;
            $this->_mensaje = 'Ya existe un usuario con el mismo Usuario';
            $return= false;   
        }else{
            if(!$_objUsuario->guardar()){
                $this->_ok = 0;
                $this->_mensaje = $_objUsuario->getMysqlError();
            }else{
                $id = $_objUsuario->get_usu_Id();
                //$_objlogs = new logs();
                //$_objlogs->_insertLogs($id,1,2,8);
                $this->_ok = 1;
                $this->_mensaje = "Datos ingresados correctamente";
            }
            $return= $_objUsuario->guardar();
        }
        return $return;
    }
    
    /**
    *** Realiza el proceso de Listar usuarios, exeptuando el usuario enviado por parametro.
    *** @param type $id_usuario
    **/  
    private function _listarUsuarios($id_usuario) {
       
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
        $query = "SELECT * FROM conf_usuario WHERE usu_Id <> $id_usuario";
        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "Usuarios listados";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existen Usuarios";
            $row=[];
        }
        return $row;     
    }  
    
    /**
    *** Realiza el proceso de Consultar Usuarios.
    **/  
    private function _consultarUsuarios() {
       
        $_objUsu = new \erpsoftsas\DAO_Usuario();

        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objUsu->set_usu_Id($_POST['id']);
            }    
        }

        if(isset($_POST['usu_Rol'])){
            if (!empty($_POST['usu_Rol']) || $_POST['usu_Rol'] != NULL ) {
                $_objUsu->set_usu_Rol($_POST['usu_Rol']);
            }    
        }
        
        $_objUsu->habilita1ResultadoEnArray();
        $arrUsuarios = $_objUsu->consultar();
       
        if(is_array($arrUsuarios) && count($arrUsuarios)){
            $R = [];
            foreach($arrUsuarios as $obj){
                $R[] = $obj->getArray();
            }    
            $this->_ok = 1;
            $this->_mensaje = "Usuarios listados con exito"; 
        }else{
            $R=$_objUsu;
            $this->_ok = 0;
            $this->_mensaje = "No existen Usuarios";            
        }       
        return $R;
    }
    
    /**
    *** Realiza el proceso de Activar o Inactivar Usuarios.
    **/  
    protected function _inactivarUsuarios() {

        $_objUsuario = new \erpsoftsas\DAO_Usuario();
        $_objUsuario->set_usu_Id($_POST['id']);
        $_objUsuario->set_usu_Estado($_POST['estado']);
        
        if(!$_objUsuario->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objUsuario->getMysqlError();
        }else{
            $id = $_objUsuario->get_usu_Id();
            //$_objlogs = new logs();
            //$_objlogs->_insertLogs($id,1,2,9);
            $this->_ok = 1;
            $this->_mensaje = "Usuario Activado/inactivado correctamente";
        }
        return $_objUsuario->getArray();
    }


    /**
    *** Proceso de recuperación de contraseña
    **/
    protected function _recuperarUsuario() {

        if (empty($_POST['email'])) {
            $this->_ok = 0;
            $this->_mensaje = 'Correo no enviado';
            return false;
        }

        $email = trim($_POST['email']);

        $_objUsuario = new \erpsoftsas\DAO_Usuario();
        $_objUsuario->set_usu_Correo($email);
        $_objUsuario->habilita1ResultadoEnArray();

        $usuario = $_objUsuario->consultar();

        if (!is_array($usuario) || !count($usuario)) {
            $this->_ok = 0;
            $this->_mensaje = 'El correo no existe';
            return false;
        }

        // Usuario encontrado
        $usuario = $usuario[0];

        // Generar clave temporal
        $claveTemporal = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 8);

        $_objUsuario->set_usu_Id($usuario->get_usu_Id());
        $_objUsuario->set_usu_Password($claveTemporal);

        if (!$_objUsuario->guardar()) {
            $this->_ok = 0;
            $this->_mensaje = 'No se pudo actualizar la contraseña';
            return false;
        }

        // Enviar correo SOLO si se actualizó la contraseña
        $this->_enviarCorreoRecuperacion(
            $email,
            $usuario->get_usu_Nombres(),
            $claveTemporal
        );

        $this->_ok = 1;
        $this->_mensaje = 'Correo de recuperación enviado';
        return true;
    }

     /** Función para enviar el correo de recuperación de contraseña
     * @param string $email Correo del usuario
     * @param string $nombre Nombre del usuario
     * @param string $claveTemporal Clave temporal generada
     */
        protected function _enviarCorreoRecuperacion($email, $nombre, $claveTemporal)
        {
            require_once __DIR__ . '/../php_mailer/Exception.php';
            require_once __DIR__ . '/../php_mailer/PHPMailer.php';
            require_once __DIR__ . '/../php_mailer/SMTP.php';

            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'gestor.documental.alcaldia@gmail.com';
                $mail->Password   = 'igzq hteh qrru rmbu'; // contraseña de aplicación
                $mail->SMTPSecure = 'tls';
                $mail->Port       = 587;
                $mail->CharSet = 'UTF-8';

                $mail->setFrom('gestor.documental.alcaldia@gmail.com', 'Alcaldia de Paipa');
                $mail->addAddress($email, $nombre);

                $mail->isHTML(true);
                $mail->Subject = 'Recuperación de contraseña Industria y Comercio';
                $mail->Body = "
                    <p>Hola <strong>{$nombre}</strong>,</p>
                    <p>Tu contraseña es:</p>
                    <h2>{$claveTemporal}</h2>
                    <p>Alcaldia de Paipa</p>
                ";

                $mail->send();
                return true;

            } catch (\Exception $e) {
                return false;
            }
        }

}

class UsuariosException extends \Exception{}

    \erpsoftsas\ControladorUsuarios::run();

