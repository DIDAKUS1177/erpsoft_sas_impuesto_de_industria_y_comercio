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
                case 6:
                    $respuesta = $_obj->_cambiarClave();
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
        } catch (\Throwable $e) {
            // Sin esto, cualquier error de la base (un dato que no cabe en su
            // columna, por ejemplo) salia como un 500 con el cuerpo vacio: la
            // inscripcion publica no decia NADA y la pantalla de Usuarios solo
            // "Error de conexion". El detalle va al log; al usuario, un aviso.
            error_log('[usuarios] funcion ' . ($_POST['funcion'] ?? '?') . ': ' . $e->getMessage());
            header('Content-type: application/json');
            echo json_encode(array(
                "ok"      => 0,
                "mensaje" => "No se pudo completar la operación. Revise los datos e intente de nuevo; "
                           . "si el problema sigue, comuníquese con la Secretaría de Hacienda.",
                "datos"   => ""
            ));
        }
    }

    /**
     * Dos correos son el mismo si solo se diferencian en la caja o en espacios.
     *
     * Es el criterio de SQL Server, que es quien acaba guardandolos, y por eso
     * es el que hay que usar para decidir si algo esta repetido. Vacio nunca
     * cuenta como repetido: hay cuentas sin correo y no son duplicadas entre si.
     */
    private static function _mismoCorreo($a, $b)
    {
        $a = mb_strtolower(trim((string) $a));
        $b = mb_strtolower(trim((string) $b));

        return $a !== '' && $a === $b;
    }

    /**
    *** Realiza el proceso de Crear Usuarios.
    **/  
    protected function _agregarUsuario() {

        /*
         * CUENTA Y CONTRIBUYENTE: LAS DOS O NINGUNA (revision 2026-09-28).
         *
         * Antes se guardaba la cuenta y DESPUES se creaba el contribuyente, sin
         * transaccion y sin validar. Si el contribuyente fallaba -un telefono
         * "310 123 4567" en una columna bigint, o el DV y el tipo de persona que
         * la pantalla de Usuarios nunca manda-, la cuenta ya existia, la
         * respuesta era un 500 vacio y el RIT de esa persona decia "Su usuario
         * no esta asociado a un contribuyente": sin salida. Al reintentar,
         * "Email duplicado". Ahora se valida antes y lo demas va en una sola
         * transaccion.
         *
         * Los roles de la Alcaldia (1 administrador, 2 funcionario) NO reciben
         * contribuyente: no son contribuyentes, y el que se les creaba quedaba en
         * el padron como uno mas (asi aparecio el contribuyente con el documento
         * del administrador). Para los demas, lo que la pantalla de Usuarios no
         * pide se deduce: el DV sale del NIT, el tipo de persona del tipo de
         * documento, y el municipio queda en 0, que el RIT trata como "sin
         * escoger" y obliga a llenar antes de guardar. Es mejor que el 1 (Tunja)
         * que se ponia a ciegas.
         */
        $rolNuevo     = (int) ($_POST['id_rol'] ?? 0);
        $esAlcaldia   = in_array($rolNuevo, [1, 2], true);
        $documento    = trim((string) ($_POST['numeroDocumento'] ?? ''));
        $tipoDocumento = (int) ($_POST['idTipoDocumento'] ?? 0);

        // El telefono se guarda solo con sus digitos: "310 123 4567", "310-123-4567"
        // y "+57 3101234567" son el mismo numero, y la columna del contribuyente
        // es bigint. Vacio se admite (en Usuarios no es obligatorio).
        $telefono = preg_replace('/\D/', '', (string) ($_POST['telefono'] ?? ''));
        if ($telefono !== '' && (strlen($telefono) < 7 || strlen($telefono) > 15)) {
            $this->_ok = 0;
            $this->_mensaje = 'El teléfono debe tener entre 7 y 15 dígitos.';
            return false;
        }

        if (!$esAlcaldia) {
            // La columna del documento del contribuyente es INT: sin puntos, sin
            // guion ni DV, y hasta 2.147.483.647. Un documento que no cabe hacia
            // fallar el INSERT del contribuyente con la cuenta ya creada.
            if ($documento === '' || !ctype_digit($documento) || strlen($documento) > 10
                || (float) $documento > 2147483647 || (int) $documento <= 0) {
                $this->_ok = 0;
                $this->_mensaje = 'Escriba el número de documento solo con números: sin puntos, sin guion y sin el dígito de verificación.';
                return false;
            }
            if ($tipoDocumento < 1 || $tipoDocumento > 5) {
                $this->_ok = 0;
                $this->_mensaje = 'Elija el tipo de documento.';
                return false;
            }
        }

        $_objUsuario = new \erpsoftsas\DAO_Usuario();
        $_objUsuario->set_usu_Nombres($_POST['nombres']);
        $_objUsuario->set_usu_Apellidos($_POST['apellidos']);
        $_objUsuario->set_usu_Telefono($telefono);
        $_objUsuario->set_usu_Direccion($_POST['direccion']);
        $_objUsuario->set_usu_IdTipoDocumento($_POST['idTipoDocumento']);
        $_objUsuario->set_usu_NumeroDocumento($documento);
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
            // El correo se compara SIN distinguir mayusculas ni espacios.
            //
            // Con == a secas, "Cristian@x.com" y "cristian@x.com" son distintos
            // para PHP pero el MISMO para SQL Server, que compara sin distinguir
            // mayusculas por su collation. O sea que esta comprobacion daba el
            // visto bueno a un correo que la base considera repetido: bastaba
            // cambiar una letra de caja para colar un duplicado.
            if(self::_mismoCorreo($nomUsurio[$i]['usu_Correo'], $_objUsuario->get_usu_Correo())){
               $nomduplicado=1;
                break;
            }
            // Un documento vacio (se admite para cuentas de la Alcaldia) no
            // choca con otro vacio: no identifica a nadie.
            if($documento !== '' && $nomUsurio[$i]['usu_NumeroDocumento'] == $_objUsuario->get_usu_NumeroDocumento()){
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
            return false;
        }else if($nomduplicado == 2){
            $this->_ok = 3;
            $this->_mensaje = 'Ya existe un usuario con la misma identificación';
            return false;
        }else if($nomduplicado == 3){
            $this->_ok = 4;
            $this->_mensaje = 'Ya existe un usuario con el mismo Usuario';
            return false;
        }

        // La cuenta (por el DAO: la clave se guarda con HASHBYTES, como la
        // compara el login) y el contribuyente (parametrizado), en la misma
        // transaccion de la conexion compartida.
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
        try {
            $con->begin();

            if (!$_objUsuario->guardar()) {
                throw new \Exception('No se pudo guardar la cuenta: ' . $_objUsuario->getMysqlError());
            }

            if (!$esAlcaldia) {
                // Mismo criterio que el enlace cuenta-contribuyente del resto del
                // sistema: por numero de documento. Si ya existe, no se duplica.
                $existe = $con->obnerFila($con->consultar(
                    "SELECT TOP 1 ind_Id FROM ind_contribuyentes
                      WHERE ind_NumeroIdentificacion = ? ORDER BY ind_Id",
                    [(int) $documento]
                ));

                if (!$existe) {
                    $tipoPersona = (int) ($_POST['tipoPersona'] ?? 0);
                    if (!in_array($tipoPersona, [1, 2], true)) {
                        $tipoPersona = ($tipoDocumento === 5) ? 2 : 1;
                    }
                    // Con NIT el DV se calcula aqui (el que llegue del navegador no
                    // decide); sin NIT el sistema no usa DV y la columna guarda 0.
                    $dv = ($tipoDocumento === 5) ? \erpsoftsas\DAO_Contribuyentes::digitoVerificacion($documento) : 0;

                    // Municipio de residencia: lo pide la inscripcion publica
                    // (index.php). Sin el, 0 = "sin escoger": el RIT no deja
                    // guardarse hasta que se elija. Antes caia en 1 (Tunja), que
                    // es justo el "municipio de registro carga mal" del punto 5.
                    $idCiudad = (isset($_POST['idCiudad']) && ctype_digit((string) $_POST['idCiudad']))
                        ? (int) $_POST['idCiudad'] : 0;

                    $con->consultar(
                        "INSERT INTO ind_contribuyentes
                             (ind_NumeroIdentificacion, ind_IdTipoDocumento, ind_DV, ind_PrimerNombre,
                              ind_PrimerApellido, ind_Direccion, ind_Telefono, ind_Email,
                              ind_Persona, ind_IdCiudad, ind_Estado)
                         VALUES (?, ?, ?, ?, NULLIF(?, ''), ?, NULLIF(?, ''), NULLIF(?, ''), ?, ?, 1)",
                        [
                            (int) $documento, $tipoDocumento, $dv,
                            trim((string) ($_POST['nombres'] ?? '')),
                            // Una persona juridica no tiene apellidos: su razon
                            // social va entera en el primer nombre.
                            $tipoPersona === 2 ? '' : trim((string) ($_POST['apellidos'] ?? '')),
                            trim((string) ($_POST['direccion'] ?? '')),
                            $telefono,
                            trim((string) ($_POST['email'] ?? '')),
                            $tipoPersona, $idCiudad,
                        ]
                    );
                }
            }

            $con->commit();

        } catch (\Throwable $e) {
            try { $con->rollback(); } catch (\Throwable $e2) { /* ya no habia transaccion */ }
            error_log('[usuarios] no se creo la cuenta ' . $documento . ': ' . $e->getMessage());
            $this->_ok = 0;
            $this->_mensaje = 'No se pudo completar el registro y no quedó nada guardado. Revise los datos '
                            . 'e intente de nuevo; si el problema sigue, comuníquese con la Secretaría de Hacienda.';
            return false;
        }

        //$_objlogs = new logs();
        //$_objlogs->_insertLogs($id,1,2,7);
        $this->_ok = 1;
        $this->_mensaje = "Datos ingresados correctamente";

        // Antes aqui habia un segundo $_objUsuario->guardar(): un UPDATE repetido
        // de la cuenta recien creada, que ademas corria fuera de todo control.
        return true;
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
            // El correo se compara SIN distinguir mayusculas ni espacios.
            //
            // Con == a secas, "Cristian@x.com" y "cristian@x.com" son distintos
            // para PHP pero el MISMO para SQL Server, que compara sin distinguir
            // mayusculas por su collation. O sea que esta comprobacion daba el
            // visto bueno a un correo que la base considera repetido: bastaba
            // cambiar una letra de caja para colar un duplicado.
            if(self::_mismoCorreo($nomUsurio[$i]['usu_Correo'], $_objUsuario->get_usu_Correo())){
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
    *** Cambio de contraseña por el propio usuario (punto 1 solicitado por el
    *** cliente): antes solo existia el reseteo por correo con una clave
    *** temporal generada por el sistema, y no habia forma de volver a
    *** asignar una propia despues. Requiere la clave ACTUAL para autorizar
    *** el cambio -es la unica verificacion de identidad real en este flujo,
    *** ya que el resto del sistema confia en localStorage para saber quien
    *** esta logueado, igual que el resto de pantallas de esta app-.
    ***
    *** Nota sobre el hash: las contraseñas se guardan con
    *** HASHBYTES('SHA1', texto) vía DAO->guardar() (ver class.DAO.php,
    *** tipodato 'clave'), pero DAO->consultar() NO aplica ese mismo hash del
    *** lado del WHERE -hace una comparación literal-. Por eso, para
    *** verificar la clave actual, hay que replicar exactamente lo que hace
    *** el login real (business/controller/class.login.php): comparar contra
    *** sha1() calculado en PHP, no contra el texto plano. SQL Server hace el
    *** match sin importar mayusculas/minusculas porque la collation por
    *** defecto es case-insensitive.
    ***
    *** OJO con la inyeccion SQL: class.DAO.php arma las consultas
    *** concatenando strings, NO con parametros. Por eso aqui:
    ***   - usu_Id se castea a int antes de tocar el DAO (en guardar() va al
    ***     WHERE del UPDATE sin comillas siquiera: " WHERE usu_Id = $valor").
    ***   - la comilla simple de la clave nueva la escapa el DAO
    ***     (DAOGeneral::_literalSql, desde 2026-09-28). Antes se escapaba
    ***     AQUI; con el escape en el DAO eso la duplicaba dos veces, HASHBYTES
    ***     recibia dos comillas y el login (sha1() sobre el texto crudo) ya no
    ***     coincidia. No volver a escaparla en este metodo.
    **/
    protected function _cambiarClave() {

        $idUsuario = isset($_POST['usu_Id']) ? (int) $_POST['usu_Id'] : 0;
        $claveActual = $_POST['claveActual'] ?? '';
        $claveNueva = $_POST['claveNueva'] ?? '';

        if ($idUsuario <= 0 || $claveActual === '' || $claveNueva === '') {
            $this->_ok = 0;
            $this->_mensaje = 'Datos incompletos';
            return false;
        }

        // Misma regla que se valida en el navegador (login.js
        // validarPassword): min 8, mayuscula, minuscula, numero. Se repite
        // aqui porque el navegador se puede saltar llamando el endpoint
        // directamente.
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $claveNueva)) {
            $this->_ok = 0;
            $this->_mensaje = 'La nueva contraseña debe tener mínimo 8 caracteres, incluir mayúscula, minúscula y número.';
            return false;
        }

        // Verificar la clave actual (ver nota de clase sobre el hash).
        $_objVerif = new \erpsoftsas\DAO_Usuario();
        $_objVerif->set_usu_Id($idUsuario);
        $_objVerif->set_usu_Password(sha1($claveActual));
        $encontrado = $_objVerif->consultar();

        if (!$encontrado) {
            $this->_ok = 0;
            $this->_mensaje = 'La contraseña actual no es correcta';
            return false;
        }

        $_objUsuario = new \erpsoftsas\DAO_Usuario();
        $_objUsuario->set_usu_Id($idUsuario);
        // Sin escapar: lo hace el DAO (ver la nota de arriba).
        $_objUsuario->set_usu_Password($claveNueva);

        if (!$_objUsuario->guardar()) {
            $this->_ok = 0;
            $this->_mensaje = 'No se pudo actualizar la contraseña';
            return false;
        }

        $this->_ok = 1;
        $this->_mensaje = 'Contraseña actualizada correctamente';
        return true;
    }

    /**
    *** Proceso de recuperación de contraseña
    **/
    protected function _recuperarUsuario() {

        // La persona se identifica con su NIT o cedula (no con el correo):
        // es el dato que si recuerda. El correo destino se toma del que ya
        // tiene registrado en su cuenta.
        if (empty($_POST['documento'])) {
            $this->_ok = 0;
            $this->_mensaje = 'Debe ingresar su NIT o cédula';
            return false;
        }

        $documento = trim($_POST['documento']);

        $_objUsuario = new \erpsoftsas\DAO_Usuario();
        $_objUsuario->set_usu_NumeroDocumento($documento);
        $_objUsuario->habilita1ResultadoEnArray();

        $usuario = $_objUsuario->consultar();

        if (!is_array($usuario) || !count($usuario)) {
            $this->_ok = 0;
            $this->_mensaje = 'El documento no se encuentra registrado';
            return false;
        }

        // Usuario encontrado
        $usuario = $usuario[0];

        // Sin correo registrado no hay a donde enviar la clave temporal.
        $email = trim((string) $usuario->get_usu_Correo());

        if ($email === '') {
            $this->_ok = 0;
            $this->_mensaje = 'La cuenta no tiene un correo registrado. '
                            . 'Comuníquese con la Secretaría de Hacienda.';
            return false;
        }

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

        // Se devuelve el correo enmascarado para que la persona confirme a
        // donde llego, sin revelar la direccion completa a quien solo tecleo
        // un numero de documento.
        return array('correo' => $this->_enmascararCorreo($email));
    }

    /**
     * Convierte "contribuyente@dominio.com" en "co***@dominio.com".
     */
    protected function _enmascararCorreo($email)
    {
        $partes = explode('@', $email);

        if (count($partes) !== 2) {
            return '';
        }

        $usuario = $partes[0];
        $visible = mb_substr($usuario, 0, 2);

        return $visible . '***@' . $partes[1];
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

