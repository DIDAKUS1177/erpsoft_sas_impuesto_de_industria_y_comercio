<?php
namespace erpsoftsas;

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/DAO/DAO_Contribuyentes.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER . '/business/controller/class.logs.php';

class ControladorContribuyentes extends \erpsoftsas\Cabecera 
{
    private $_funcion;
    private $_ok;
    private $_mensaje;

    /**
     * Nadie entra aqui sin sesion, y no todo el mundo puede con todo.
     *
     * EL AGUJERO QUE CIERRA (encontrado el 2026-08-26, comprobado en vivo)
     *
     * run() despachaba directo al switch. Las funciones 6, 7 y 8 comprueban
     * permiso por su cuenta, pero las demas no comprobaban NADA, y este
     * controlador es el que guarda el padron de contribuyentes: cedulas, NIT,
     * direcciones, telefonos, correos y representantes legales. Datos con
     * reserva tributaria.
     *
     * Un POST con funcion=3 y SIN NINGUNA COOKIE devolvia el padron entero.
     * Uno con funcion=2 reescribia la ficha de cualquier contribuyente, y con
     * funcion=1 metia uno nuevo. Sin identificarse.
     *
     * QUE PUEDE CADA QUIEN
     *
     *   roles 1 y 2 (Alcaldia)  todo, que es su trabajo: inscriben en ventanilla
     *   los demas               solo LO SUYO, y solo leerlo
     *
     * Escribir en el padron -crear, editar, inactivar- y buscar en el padron
     * completo quedan reservados a la Alcaldia. Un contribuyente no tiene por
     * que poder listar a los demas ni corregirse a si mismo el numero de
     * documento; para lo suyo esta el RIT, que es la funcion 7 y ya valida.
     *
     * A la consulta se le FIJA el filtro en vez de rechazarla: las pantallas
     * del contribuyente (icaWebRit, establecimientos) piden su propio ind_Id,
     * asi que siguen funcionando igual, pero el que venga en el POST deja de
     * decidir nada. Mismo criterio que class.declaracionesICA.php.
     */
    private static function _verificarAcceso()
    {
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }

        if (empty($_SESSION['id_usuario'])) {
            return 'Debe iniciar sesión.';
        }

        $rol     = isset($_SESSION['id_Rol']) ? (int) $_SESSION['id_Rol'] : 0;
        $funcion = (int) ($_POST['funcion'] ?? 0);

        if (in_array($rol, [1, 2], true)) { return null; }

        // 1 agregar · 2 editar · 4 inactivar · 5 buscar en todo el padron
        if (in_array($funcion, [1, 2, 4, 5], true)) {
            return 'No tiene permiso sobre el registro de contribuyentes.';
        }

        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $propio = $con->obnerFila($con->consultar(
            "SELECT TOP 1 c.ind_Id
               FROM ind_contribuyentes c
               INNER JOIN conf_usuarios u
                       ON u.usu_NumeroDocumento = c.ind_NumeroIdentificacion
              WHERE u.usu_Id = ?
              ORDER BY c.ind_Id",
            [(int) $_SESSION['id_usuario']]
        ));

        if (!$propio) {
            return 'Su usuario no está asociado a un contribuyente.';
        }

        // La consulta solo devuelve lo suyo, venga lo que venga en el POST.
        if ($funcion === 3) {
            $_POST['ind_Id'] = (int) $propio['ind_Id'];
        }

        return null;
    }

    public static function run() 
    {
        // Instanciamos el controlador
        $_obj = new self();
        // Obtenemos el número de función que indica la operación a ejecutar
        $_obj->_funcion = isset($_POST['funcion']) ? $_POST['funcion'] : null;

        $negado = self::_verificarAcceso();
        if ($negado !== null) {
            header('Content-type: application/json');
            // sinSesion: la sesion PHP vencio aunque la pantalla siga abierta.
            // dist/menu.php lo detecta y lleva al login con un aviso, en vez de
            // dejar un RIT vacio con "Debe iniciar sesion." en letra gris.
            echo json_encode(["ok" => 0, "mensaje" => $negado, "datos" => [],
                              "sinSesion" => empty($_SESSION['id_usuario']) ? 1 : 0]);
            return;
        }

        try {
            // Iniciamos la transacción (adaptar a tu clase de conexión)
            //$con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
            //$con->begin();
            $respuesta = null;
            switch ($_obj->_funcion) {
                case 1: // Agregar Contribuyente
                    $respuesta = $_obj->_agregarContribuyente();
                    break;
                case 2: // Editar Contribuyente
                    $respuesta = $_obj->_editarContribuyente();
                    break;
                case 3: // Consultar Contribuyente(s)
                    $respuesta = $_obj->_consultarContribuyente();
                    break;
                case 4: // Inactivar Contribuyente
                    $respuesta = $_obj->_inactivarContribuyente();
                    break;
                case 5:
                    $respuesta = $_obj->_buscarContribuyentes();
                     break;
                case 6: // Consultar el RIT del contribuyente
                    $respuesta = $_obj->_consultarRIT();
                    break;
                case 7: // Guardar el RIT del contribuyente
                    $respuesta = $_obj->_guardarRIT();
                    break;
                case 8: // Guardar las actividades economicas del contribuyente
                    $respuesta = $_obj->_guardarActividadesRIT();
                    break;
                default:
                    throw new \erpsoftsas\ContribuyentesException("Función no válida", 0);
            }

            // Si todo va bien, se hace commit
            //$con->commit();

            header('Content-type: application/json');
            echo json_encode(array(
                "ok" => $_obj->_ok, 
                "mensaje" => $_obj->_mensaje, 
                "datos" => $respuesta
            ));

        } catch (\erpsoftsas\ContribuyentesException $e) {
            // En caso de error, se realiza rollback
            //$con->rollback();
            $arrRespu = array(
                "ok"      => $e->getCode(),
                "mensaje" => "Error: " . $e->getMessage(),
                "datos"   => ""
            );
            header('Content-type: application/json');
            echo json_encode($arrRespu);
        } catch (\Throwable $e) {
            // Antes solo se atrapaba ContribuyentesException: un error real de
            // SQL Server (por ejemplo, una fecha con formato invalido o un
            // texto mas largo que la columna) se propagaba sin capturar y
            // terminaba en un 500 con cuerpo vacio -sin JSON, sin mensaje-.
            // Con esto, cualquier fallo del driver responde igual que un
            // error de negocio normal. \Throwable y no \Exception: un Error de
            // PHP (un tipo inesperado) tambien dejaba el 500 mudo.
            error_log('[contribuyentes] funcion ' . ($_POST['funcion'] ?? '?') . ': ' . $e->getMessage());
            header('Content-type: application/json');
            echo json_encode(array(
                "ok"      => 0,
                "mensaje" => "No se pudo procesar la solicitud. Verifique los datos e intente de nuevo.",
                "datos"   => "",
            ));
        }
    }

    /*
     * FICHA DEL CONTRIBUYENTE (funciones 1 y 2, solo Alcaldia) -- revision 2026-09-28.
     *
     * Iban por el DAO, que tiene tres problemas para este caso:
     *   - Salta los valores vacios: borrar el segundo nombre, el correo o el
     *     telefono decia "actualizado exitosamente" y el dato viejo seguia ahi. Y
     *     al pasar a persona juridica, el apellido de antes se quedaba (asi quedo
     *     "SISTEMAS ERPSOFT S.A.S pp" en los listados).
     *   - No revisaba si el documento ya existia: por esta puerta entro el
     *     contribuyente repetido (24 y 29, C.C. 1052400234). Con dos registros, la
     *     cuenta del contribuyente queda atada al de menor ind_Id y lo que la
     *     Alcaldia gestione en el otro no le aparece.
     *   - Pegaba los valores en el SQL (ver la nota del DAO).
     * Ahora las dos funciones validan lo mismo (_leerFicha) y escriben con
     * parametros.
     */

    /**
     * Reglas de cada columna de la ficha. 'obligatorio' => la columna es NOT NULL
     * y no puede quedar vacia; las demas, vacias, se guardan NULL.
     */
    private static function _camposFicha()
    {
        return [
            'ind_NumeroIdentificacion' => ['el número de documento', true],
            'ind_IdTipoDocumento'      => ['el tipo de documento', true],
            'ind_PrimerNombre'         => ['el primer nombre o la razón social', true],
            'ind_SegundoNombre'        => ['el segundo nombre', false],
            'ind_PrimerApellido'       => ['el primer apellido', false],
            'ind_SegundoApellido'      => ['el segundo apellido', false],
            'ind_Direccion'            => ['la dirección', true],
            'ind_IdCiudad'             => ['el municipio', true],
            'ind_Persona'              => ['el tipo de persona', true],
            'ind_IdRegimen'            => ['el régimen', false],
            'ind_Telefono'             => ['el teléfono', false],
            'ind_Email'                => ['el correo electrónico', false],
        ];
    }

    /**
     * Lee y valida los campos de la ficha que vengan en el POST.
     *
     * Devuelve ['valores' => [columna => valor normalizado o null]] con SOLO las
     * columnas que llegaron, o ['error' => mensaje]. Con $exigirTodas (crear)
     * falta una obligatoria es error; al editar, lo que no llega no se toca.
     */
    private static function _leerFicha($exigirTodas)
    {
        $valores = [];

        foreach (self::_camposFicha() as $campo => list($rotulo, $obligatorio)) {
            if (!array_key_exists($campo, $_POST)) {
                if ($exigirTodas && $obligatorio) {
                    return ['error' => 'Falta ' . $rotulo . '.'];
                }
                continue;
            }

            $v = trim((string) $_POST[$campo]);

            if ($v === '') {
                if ($obligatorio) {
                    return ['error' => 'Complete ' . $rotulo . '.'];
                }
                $valores[$campo] = null;
                continue;
            }

            switch ($campo) {
                case 'ind_NumeroIdentificacion':
                    // La columna es INT: se aceptan puntos y espacios de quien lo
                    // copia de un documento, pero no letras ni el DV pegado.
                    $v = str_replace(['.', ' '], '', $v);
                    if (!ctype_digit($v) || strlen($v) > 10 || (float) $v > 2147483647 || (int) $v <= 0) {
                        return ['error' => 'Escriba el número de documento solo con números: sin guion ni dígito de verificación.'];
                    }
                    $v = (int) $v;
                    break;

                case 'ind_IdTipoDocumento':
                    // El catalogo del sistema (icaWebRit.js, PDF): 1 C.C., 3 C.E.,
                    // 4 pasaporte, 5 NIT. El 2 no existe.
                    if (!in_array($v, ['1', '3', '4', '5'], true)) {
                        return ['error' => 'Elija el tipo de documento (C.C., C.E., pasaporte o NIT).'];
                    }
                    $v = (int) $v;
                    break;

                case 'ind_Persona':
                    if (!in_array($v, ['1', '2'], true)) {
                        return ['error' => 'Elija si es persona natural o jurídica.'];
                    }
                    $v = (int) $v;
                    break;

                case 'ind_IdCiudad':
                    if (!ctype_digit($v) || (int) $v <= 0) {
                        return ['error' => 'Elija el municipio.'];
                    }
                    $v = (int) $v;
                    break;

                case 'ind_IdRegimen':
                    if (!ctype_digit($v)) {
                        return ['error' => 'El régimen no es válido.'];
                    }
                    $v = (int) $v;
                    break;

                case 'ind_Telefono':
                    // bigint: solo los digitos, como el RIT. "310 123 4567" es el
                    // mismo numero; mas de 15 digitos no es un telefono.
                    $v = preg_replace('/\D/', '', $v);
                    if ($v === '') {
                        $v = null;
                    } elseif (strlen($v) > 15) {
                        return ['error' => 'El teléfono no puede tener más de 15 dígitos.'];
                    }
                    break;

                case 'ind_Email':
                    if (!filter_var($v, FILTER_VALIDATE_EMAIL)) {
                        return ['error' => 'El correo "' . $v . '" no es válido.'];
                    }
                    break;

                default:
                    // Textos: el largo de la columna (varchar 100 los nombres, 200
                    // la direccion). Pasarse era un 500 mudo.
                    $maximo = ($campo === 'ind_Direccion') ? 200 : 100;
                    if (mb_strlen($v) > $maximo) {
                        return ['error' => 'El texto de ' . $rotulo . ' no puede pasar de ' . $maximo . ' caracteres.'];
                    }
            }

            $valores[$campo] = $v;
        }

        return ['valores' => $valores];
    }

    /**
     * Otro contribuyente (distinto de $excepto) con ese NUMERO de documento, sea
     * del tipo que sea, o null.
     *
     * Por numero y no por tipo y numero: todo el sistema ata la cuenta de acceso
     * a su contribuyente solo por el numero (DAO_Usuario, el login,
     * _contribuyenteDeLaSesion, la API de firmas, la creacion de cuentas). Una
     * C.C. 1052400237 y un NIT 1052400237 serian dos contribuyentes para una
     * misma cuenta, que quedaria atada al de menor ind_Id: lo que la Alcaldia
     * gestionara en el otro no le apareceria nunca.
     */
    private static function _documentoRepetido($con, $numero, $excepto = 0)
    {
        $fila = $con->obnerFila($con->consultar(
            "SELECT TOP 1 ind_Id FROM ind_contribuyentes
              WHERE ind_NumeroIdentificacion = ? AND ind_Id <> ?
              ORDER BY ind_Id",
            [(int) $numero, (int) $excepto]
        ));
        return $fila ? (int) $fila['ind_Id'] : null;
    }

    /**
     * Agrega un nuevo contribuyente
     */
    protected function _agregarContribuyente()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $leido = self::_leerFicha(true);
        if (isset($leido['error'])) {
            $this->_ok = 0;
            $this->_mensaje = $leido['error'];
            return [];
        }
        $v = $leido['valores'];

        $repetido = self::_documentoRepetido($con, $v['ind_NumeroIdentificacion']);
        if ($repetido !== null) {
            $this->_ok = 0;
            $this->_mensaje = 'Ya existe un contribuyente con ese número de documento. '
                            . 'Búsquelo en esta pantalla y use "Gestionar" en vez de crear otro.';
            return ['repetido' => $repetido];
        }

        // Persona juridica: sin segundo nombre ni apellidos (la razon social va
        // entera en el primer nombre), igual que en el RIT.
        if ($v['ind_Persona'] === 2) {
            $v['ind_SegundoNombre'] = $v['ind_PrimerApellido'] = $v['ind_SegundoApellido'] = null;
        }

        // Con NIT el DV lo calcula el servidor; sin NIT el sistema no lo usa (0).
        $dv = ($v['ind_IdTipoDocumento'] === 5)
            ? \erpsoftsas\DAO_Contribuyentes::digitoVerificacion($v['ind_NumeroIdentificacion']) : 0;

        $fila = $con->obnerFila($con->consultar(
            "SET NOCOUNT ON;
             INSERT INTO ind_contribuyentes
                 (ind_NumeroIdentificacion, ind_DV, ind_IdTipoDocumento, ind_PrimerNombre, ind_SegundoNombre,
                  ind_PrimerApellido, ind_SegundoApellido, ind_Direccion, ind_IdCiudad, ind_Persona,
                  ind_IdRegimen, ind_Telefono, ind_Email, ind_Estado)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1);
             SELECT CAST(SCOPE_IDENTITY() AS INT) AS id;",
            [
                $v['ind_NumeroIdentificacion'], $dv, $v['ind_IdTipoDocumento'], $v['ind_PrimerNombre'],
                $v['ind_SegundoNombre'] ?? null, $v['ind_PrimerApellido'] ?? null, $v['ind_SegundoApellido'] ?? null,
                $v['ind_Direccion'], $v['ind_IdCiudad'], $v['ind_Persona'],
                $v['ind_IdRegimen'] ?? null, $v['ind_Telefono'] ?? null, $v['ind_Email'] ?? null,
            ]
        ));

        $id = (int) ($fila['id'] ?? 0);
        $this->_ok = 1;
        $this->_mensaje = "Contribuyente agregado correctamente. ID = $id";
        return ['ind_Id' => $id];
    }

    /**
     * Edita un contribuyente existente
     */
    protected function _editarContribuyente()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $idContribuyente = (int) ($_POST['ind_Id'] ?? 0);
        $actual = $idContribuyente > 0 ? $con->obnerFila($con->consultar(
            "SELECT ind_IdTipoDocumento, ind_NumeroIdentificacion, ind_Persona, ind_Email
               FROM ind_contribuyentes WHERE ind_Id = ?",
            [$idContribuyente]
        )) : null;
        if (!$actual) {
            $this->_ok = 0;
            $this->_mensaje = 'El contribuyente no existe.';
            return [];
        }

        $leido = self::_leerFicha(false);
        if (isset($leido['error'])) {
            $this->_ok = 0;
            $this->_mensaje = $leido['error'];
            return [];
        }
        $v = $leido['valores'];

        // Identidad como quedaria despues de guardar.
        $tipo    = $v['ind_IdTipoDocumento'] ?? (int) $actual['ind_IdTipoDocumento'];
        $numero  = $v['ind_NumeroIdentificacion'] ?? (int) $actual['ind_NumeroIdentificacion'];
        $persona = $v['ind_Persona'] ?? (int) $actual['ind_Persona'];

        $cambiaDocumento = $tipo !== (int) $actual['ind_IdTipoDocumento']
                        || $numero !== (int) $actual['ind_NumeroIdentificacion'];

        // Solo si el documento CAMBIA (numero o tipo): los que ya estaban
        // repetidos (24 y 29) se pueden seguir editando sin tocar su documento.
        // Cambiar solo el tipo tambien se revisa: con otro registro del mismo
        // numero, el cambio no deshace la ambiguedad, la disfraza.
        if ($cambiaDocumento && self::_documentoRepetido($con, $numero, $idContribuyente) !== null) {
            $this->_ok = 0;
            $this->_mensaje = 'Ya existe otro contribuyente con ese número de documento.';
            return [];
        }

        /*
         * El correo, con la MISMA regla del RIT (cliente, 2026-08-26): el de la
         * cuenta y el del contribuyente son uno, y no se repite entre
         * contribuyentes. Por aqui (Editar y la ventana "Informacion del
         * Contribuyente") se podia poner el correo de otro y la cuenta se quedaba
         * con el viejo, asi que el codigo de firma seguia yendo alla. Solo si
         * CAMBIA: un repetido de antes no le bloquea a nadie el resto de la ficha.
         */
        $correoNuevo  = array_key_exists('ind_Email', $v) ? trim((string) ($v['ind_Email'] ?? '')) : '';
        $cambiaCorreo = $correoNuevo !== ''
            && mb_strtolower($correoNuevo) !== mb_strtolower(trim((string) $actual['ind_Email']));
        if ($cambiaCorreo && self::_correoDeOtro($con, $idContribuyente, $numero, $correoNuevo)) {
            $this->_ok = 0;
            $this->_mensaje = 'El correo "' . $correoNuevo . '" ya está registrado por otro '
                            . 'contribuyente. Cada correo puede pertenecer a uno solo.';
            return [];
        }

        if ($persona === 2) {
            $v['ind_SegundoNombre'] = $v['ind_PrimerApellido'] = $v['ind_SegundoApellido'] = null;
        }

        // El DV sale del NIT (algoritmo de la DIAN), como en el RIT; el que llegue
        // del navegador no decide. Sin NIT el sistema no usa DV.
        if (array_key_exists('ind_IdTipoDocumento', $v) || array_key_exists('ind_NumeroIdentificacion', $v)
            || array_key_exists('ind_DV', $_POST)) {
            $v['ind_DV'] = ($tipo === 5) ? \erpsoftsas\DAO_Contribuyentes::digitoVerificacion($numero) : 0;
        }

        if (!$v) {
            $this->_ok = 0;
            $this->_mensaje = 'No llegó ningún dato para guardar.';
            return [];
        }

        $sets = [];
        $params = [];
        foreach ($v as $columna => $valor) {
            $sets[]   = $columna . ' = ?';
            $params[] = $valor;
        }
        $params[] = $idContribuyente;

        $con->consultar(
            "UPDATE ind_contribuyentes SET " . implode(', ', $sets) . ", ind_FechaActualizacion = GETDATE()
              WHERE ind_Id = ?",
            $params
        );

        $this->_ok = 1;
        $this->_mensaje = "Contribuyente ID $idContribuyente editado correctamente";

        // El correo nuevo pasa tambien a su cuenta de acceso (una sola; si no se
        // puede, se avisa). El aviso va aparte en los datos para las pantallas
        // que muestran su propio texto de exito.
        $aviso = $cambiaCorreo ? trim(self::_sincronizarCorreoCuenta($con, $idContribuyente, $correoNuevo)) : '';

        /*
         * La cuenta de acceso se enlaza al contribuyente por el NUMERO de
         * documento (conf_usuarios.usu_NumeroDocumento). Si se corrige el numero
         * aqui y no alli, esa persona entra y su RIT dice "Su usuario no esta
         * asociado a un contribuyente". No se cambia la cuenta sola -puede no ser
         * de esta persona-, pero se avisa.
         */
        if ($numero !== (int) $actual['ind_NumeroIdentificacion']) {
            $cuentas = $con->obnerFila($con->consultar(
                "SELECT COUNT(*) AS n FROM conf_usuarios WHERE usu_NumeroDocumento = ?",
                [(string) $actual['ind_NumeroIdentificacion']]
            ));
            if ((int) ($cuentas['n'] ?? 0) > 0) {
                $aviso = trim($aviso . ' Ojo: hay una cuenta de acceso con el documento anterior ('
                       . $actual['ind_NumeroIdentificacion'] . '); corrija también su documento en '
                       . 'Usuarios, o esa persona no verá su RIT.');
            }
        }

        if ($aviso !== '') {
            $this->_mensaje .= '. ' . $aviso;
        }

        return ['ind_Id' => $idContribuyente, 'aviso' => $aviso];
    }

    /**
     * Consulta uno o varios contribuyentes
     */
    private function _consultarContribuyente()
    {
        $_obj = new \erpsoftsas\DAO_Contribuyentes();

        if(isset($_POST['ind_Id'])){
            if (!empty($_POST['ind_Id']) || $_POST['ind_Id'] != NULL ) {
                $_obj->set_ind_Id($_POST['ind_Id']);
            }    
        }
        if(isset($_POST['ind_NumeroIdentificacion'])){
            if (!empty($_POST['ind_NumeroIdentificacion']) || $_POST['ind_NumeroIdentificacion'] != NULL ) {
                $_obj->set_ind_NumeroIdentificacion($_POST['ind_NumeroIdentificacion'] ?? null);
            }    
        }
        if(isset($_POST['ind_DV'])){
            if (!empty($_POST['ind_DV']) || $_POST['ind_DV'] != NULL ) {
                $_obj->set_ind_DV($_POST['ind_DV']);
            }    
        }
        if(isset($_POST['ind_IdTipoDocumento'])){
            if (!empty($_POST['ind_IdTipoDocumento']) || $_POST['ind_IdTipoDocumento'] != NULL ) {
                $_obj->set_ind_IdTipoDocumento($_POST['ind_IdTipoDocumento']);
            }    
        } 
        if(isset($_POST['ind_PrimerNombre'])){
            if (!empty($_POST['ind_PrimerNombre']) || $_POST['ind_PrimerNombre'] != NULL ) {
                $_obj->set_ind_PrimerNombre($_POST['ind_PrimerNombre'] ?? null);
            }    
        }
        if(isset($_POST['ind_SegundoNombre'])){
            if (!empty($_POST['ind_SegundoNombre']) || $_POST['ind_SegundoNombre'] != NULL ) {
                $_obj->set_ind_SegundoNombre($_POST['ind_SegundoNombre']);
            }    
        }
        if(isset($_POST['ind_PrimerApellido'])){
            if (!empty($_POST['ind_PrimerApellido']) || $_POST['ind_PrimerApellido'] != NULL ) {
                $_obj->set_ind_PrimerApellido($_POST['ind_PrimerApellido']);
            }    
        }
        if(isset($_POST['ind_SegundoApellido'])){
            if (!empty($_POST['ind_SegundoApellido']) || $_POST['ind_SegundoApellido'] != NULL ) {
                $_obj->set_ind_SegundoApellido($_POST['ind_SegundoApellido']);
            }    
        }
        if(isset($_POST['ind_Persona'])){
            if (!empty($_POST['ind_Persona']) || $_POST['ind_Persona'] != NULL ) {
                $_obj->set_ind_Persona($_POST['ind_Persona']);
            }    
        }
        if(isset($_POST['ind_IdRegimen'])){
            if (!empty($_POST['ind_IdRegimen']) || $_POST['ind_IdRegimen'] != NULL ) {
                $_obj->set_ind_IdRegimen($_POST['ind_IdRegimen']);
            }    
        }

        // Habilitamos que retorne un array de resultados
        $_obj->habilita1ResultadoEnArray();
        $arr = $_obj->consultar(); // Método heredado de DAOGeneral

        if (is_array($arr) && count($arr)) {
            // Transformamos los objetos en array (según sea tu implementación)
            $R = [];
            foreach ($arr as $obj) {
                $R[] = $obj->getArray(); 
            }
            $this->_ok = 1;
            $this->_mensaje = "Contribuyentes consultados con éxito";
            return $R;
        } else {
            $this->_ok = 0;
            $this->_mensaje = "No existen contribuyentes con los filtros seleccionados";
            return [];
        }
    }

    /**
     * Inactiva (cambia estado) de un contribuyente
     */
    protected function _inactivarContribuyente()
    {
        $_obj = new \erpsoftsas\DAO_Contribuyentes();
        // Se asume que recibes un ID y un nuevo estado
        $_obj->set_ind_Id($_POST['ind_Id']);
        $_obj->set_ind_Estado($_POST['ind_Estado']);

        if (!$_obj->guardar()) {
            $this->_ok = 0;
            $this->_mensaje = $_obj->getMysqlError();
        } else {
            $id = $_obj->get_ind_Id();
            $this->_ok = 1;
            $this->_mensaje = "Contribuyente ID $id inactivado correctamente";
        }
        return $_obj->getArray();
    }


    /**
     * Búsqueda en el padrón para la pantalla de Contribuyentes (solo Alcaldía:
     * _verificarAcceso le niega la función 5 a los demás roles).
     *
     * La pantalla antes descargaba el padrón COMPLETO (función 3) y lo filtraba
     * en el navegador; ahora pide como máximo 20 filas. Sin texto devuelve los
     * registrados más recientemente. Con texto, cada palabra tiene que aparecer
     * en el documento o en alguno de los nombres, sin distinguir tildes ni
     * mayúsculas: "juan perez" encuentra a "Juan Carlos Pérez".
     *
     * Devuelve { filas, hayMas }; "hayMas" avisa que quedaron resultados por
     * fuera de las 20 y que conviene afinar la búsqueda. Cero resultados es una
     * búsqueda exitosa (ok = 1): ok = 0 queda solo para errores de verdad.
     */
    protected function _buscarContribuyentes(){

        // $_POST['buscar'] iba interpolado directo en el LIKE, sin parametrizar
        // (a diferencia de _consultarRIT/_guardarRIT, que si usan '?'). Un
        // valor como "x%' OR ind_Id=30 --" devolvia filas de OTRO contribuyente:
        // inyeccion SQL clasica, confirmada con extraccion real de datos.
        $limite   = 20;
        $palabras = preg_split('/\s+/', trim((string) ($_POST['buscar'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
        $palabras = array_slice($palabras, 0, 5);

        $condiciones = [];
        $parametros  = [];

        foreach ($palabras as $palabra) {
            // El documento se guarda como número: "1.052.400.237" o un NIT con
            // su dígito de verificación ("900123456-7") se buscan sin puntos ni DV.
            if (preg_match('/^\d[\d.,]*(-\d)?$/', $palabra)) {
                $palabra = str_replace(['.', ','], '', preg_replace('/-\d$/', '', $palabra));
            }

            // %, _ y [ que escriba la persona se buscan como texto, no como comodines.
            $comodin = '%' . strtr($palabra, ['[' => '[[]', '%' => '[%]', '_' => '[_]']) . '%';

            // Latin1_General_CI_AI y no la de la base (Modern_Spanish_CI_AS): esa
            // distingue tildes, y aun en su versión AI trata la ñ como otra letra,
            // así que "avendano" no encontraba a "Avendaño".

            $condiciones[] = "(CAST(ind_NumeroIdentificacion AS varchar(20)) LIKE ?
                               OR ind_PrimerNombre    COLLATE Latin1_General_CI_AI LIKE ?
                               OR ind_SegundoNombre   COLLATE Latin1_General_CI_AI LIKE ?
                               OR ind_PrimerApellido  COLLATE Latin1_General_CI_AI LIKE ?
                               OR ind_SegundoApellido COLLATE Latin1_General_CI_AI LIKE ?)";
            array_push($parametros, $comodin, $comodin, $comodin, $comodin, $comodin);
        }

        $filtro = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';
        $orden  = $condiciones ? 'ind_PrimerNombre, ind_PrimerApellido' : 'ind_Id DESC';

        // Se pide una fila de más solo para saber si quedaron resultados afuera.
        $sql = "SELECT TOP " . ($limite + 1) . "
                       ind_Id, ind_NumeroIdentificacion, ind_PrimerNombre,
                       ind_PrimerApellido, ind_Direccion, ind_Estado
                  FROM ind_contribuyentes
                  $filtro
                 ORDER BY $orden";

        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
        $id  = $con->consultar($sql, $parametros);

        $filas = [];
        while ($fila = $con->obnerFila($id)) {
            $filas[] = $fila;
        }

        $this->_ok = 1;
        $this->_mensaje = $filas ? 'Contribuyentes encontrados' : 'Sin resultados';

        return [
            'filas'  => array_slice($filas, 0, $limite),
            'hayMas' => count($filas) > $limite,
        ];
    }

    /* ======================================================================
       RIT (Registro de Informacion Tributaria) del contribuyente
       ----------------------------------------------------------------------
       El RIT no es un registro aparte: es el propio contribuyente. Hasta la
       migracion 003 sus datos -matricula, representante legal, contador y
       revisor- vivian repetidos en cada establecimiento; ahora estan donde
       corresponde y estas dos funciones son las que los leen y los graban.

       Van con SQL parametrizado a proposito, sin pasar por el DAO: ese
       concatena cadenas y obligaria a escapar a mano cada campo (ver la nota
       de inyeccion en CLAUDE.md). Es el mismo camino que ya usaba
       _guardarCorreosContadorRevisor.
       ====================================================================== */

    /**
     * Columnas que el formulario del RIT puede grabar.
     *
     * NO estan aqui, a proposito: ind_NumeroIdentificacion, ind_DV,
     * ind_IdTipoDocumento y ind_Estado. Son la identidad tributaria del
     * contribuyente; dejar que se cambien desde el formulario que el propio
     * contribuyente llena permitiria que una declaracion firmada quedara
     * atada a un documento distinto del que la firmo. Se cambian por el
     * camino de siempre (funcion 2, solo administrador).
     */
    /**
     * Valida los tres codigos CIIU del RUT.
     *
     * Devuelve null si estan bien, o el mensaje de rechazo. El mensaje dice
     * SIEMPRE de que numeracion se trata: rechazar un "103" con un escueto
     * "debe tener 4 digitos" hace que la persona cuente los digitos y vuelva a
     * escribir otro codigo municipal.
     */
    private static function _validarCodigosCiiu()
    {
        $campos = [
            'ind_Rut'          => ['Código de actividad principal', true],
            'ind_Rut_segundo'  => ['Código de actividad secundaria', false],
            'ind_Rut_tercero'  => ['Código de otra actividad',       false],
        ];

        foreach ($campos as $campo => list($rotulo, $obligatorio)) {
            // No enviado: se deja como esta (no se toca lo ya guardado).
            if (!isset($_POST[$campo])) {
                if ($obligatorio) {
                    return $rotulo . ' es obligatorio: son los cuatro dígitos del código CIIU de la DIAN.';
                }
                continue;
            }

            $v = trim((string) $_POST[$campo]);

            if ($v === '') {
                if ($obligatorio) {
                    return $rotulo . ' es obligatorio: son los cuatro dígitos del código CIIU de la DIAN.';
                }
                $_POST[$campo] = '';
                continue;
            }

            if (!ctype_digit($v)) {
                return $rotulo . ' solo admite números. Es el código CIIU de la DIAN, de cuatro dígitos.';
            }

            if (strlen($v) !== 4) {
                return $rotulo . ' debe tener exactamente 4 dígitos. Recuerde que el CIIU de la DIAN '
                     . 'es de 4; los códigos del acuerdo municipal, que son de 3, se eligen en la '
                     . 'tabla de actividades económicas.';
            }

            $_POST[$campo] = $v;
        }

        return null;
    }

    private static function _camposRIT()
    {
        return [
            // Datos generales
            'ind_PrimerNombre', 'ind_SegundoNombre',
            'ind_PrimerApellido', 'ind_SegundoApellido',
            'ind_Direccion', 'ind_IdCiudad', 'ind_Telefono', 'ind_Email',
            'ind_Persona',
            // Propios del RIT
            'ind_Matricula', 'ind_Fecha_matricula', 'ind_Fecha_inicio',
            'ind_Ind_camara_comercio',
            'ind_Cedula_representante', 'ind_Nombre_representante', 'ind_Email_representante',
            'ind_Telefono_representante',
            'ind_Cedula_contador', 'ind_Nombre_contador', 'ind_Tarjeta_profesional',
            'ind_Cedula_revisor', 'ind_Nombre_revisor', 'ind_Tarjeta_profesional_revisor',
            // Codigos de actividad economica del RUT. Subieron del
            // establecimiento al contribuyente en la migracion 005: son de
            // la persona y estaban copiados en cada local.
            'ind_Rut', 'ind_Rut_segundo', 'ind_Rut_tercero',
            // Autorizacion de notificacion electronica. Vivia en cada
            // establecimiento y se pedia por local; es una manifestacion de
            // la PERSONA, asi que subio al contribuyente en la migracion 007.
            'ind_Autorizacion',
            'ind_EmailContador', 'ind_EmailRevisor',
            // Regimen tributario y responsabilidades (migracion 014). Son
            // listas de seleccion multiple y viajan como codigos separados por
            // coma; _normalizarSeleccionMultiple() se encarga de que no entre
            // cualquier cosa.
            'ind_RegimenTributario', 'ind_Responsabilidades',
            // Las dos exenciones, subidas del establecimiento por la 016.
            'ind_NoSujetas', 'ind_SinAvisosTableros',
            // Consorcio/union temporal y patrimonio autonomo (migracion 033).
            // Banderas de si/no que el formulario impreso ya pedia.
            'ind_EsConsorcio', 'ind_PatrimonioAutonomo',
        ];
    }

    /**
     * Catalogos cerrados de las dos casillas de seleccion multiple del RIT.
     *
     * Se guardan como codigos separados por coma en una sola columna. Estan
     * aqui, en el servidor, y no solo en el formulario: la lista de opciones
     * de un <input type=checkbox> se manipula desde la consola del navegador
     * en dos segundos, y esto acaba impreso en un certificado tributario.
     */
    private static function _opcionesSeleccionMultiple()
    {
        return [
            'ind_RegimenTributario' => ['ORDINARIO', 'SIMPLE', 'ESPECIAL', 'RESP_IVA', 'NO_RESP_IVA'],
            'ind_Responsabilidades' => ['AGENTE_RETENCION', 'AUTORRETENEDOR', 'INFORMANTE_EXOGENA'],
        ];
    }

    /**
     * Deja el valor de una casilla multiple en codigos validos, sin repetir y
     * en el orden del catalogo, o cadena vacia si no queda ninguno.
     */
    private static function _normalizarSeleccionMultiple($campo, $valor)
    {
        $validas = self::_opcionesSeleccionMultiple()[$campo] ?? [];
        if (!$validas) { return ''; }

        $pedidas = array_filter(array_map('trim', explode(',', (string) $valor)));
        $limpias = array_values(array_intersect($validas, $pedidas));

        return implode(',', $limpias);
    }

    /**
     * Contador y revisor fiscal solo los registra el administrador (punto 14);
     * el contribuyente los ve en modo lectura (punto 15). El formulario ya los
     * pinta deshabilitados, pero eso es solo la pantalla: sin esta comprobacion
     * en el servidor bastaria un POST a mano para saltarsela.
     */
    private static function _camposSoloAdministrador()
    {
        // Vacio a proposito.
        //
        // Hasta 2026-08-18 aqui estaban los seis campos de contador y revisor
        // fiscal mas sus dos correos: la lista anterior del cliente (puntos 14
        // y 15) decia que solo el administrador podia registrarlos. En la
        // reunion del 18 el cliente cambio la regla: los registra el propio
        // contribuyente. Se deja la funcion en su sitio -y no se borra el
        // mecanismo- porque el filtro sigue siendo util si vuelve a haber
        // campos reservados a la Alcaldia.
        return [];
    }

    /**
     * Mapa nombre-del-formulario -> columna real de ind_contribuyentes, SOLO
     * para los 6 campos donde difieren.
     *
     * La migracion 003 habia creado ind_Cedula_contador, ind_Nombre_contador,
     * ind_Tarjeta_profesional, ind_Cedula_revisor, ind_Nombre_revisor,
     * ind_Tarjeta_profesional_revisor como columnas NUEVAS -pero produccion
     * ya tenia, desde el 2026-08-04 (migracion_2026-08_contribuyente.sql
     * BLOQUE 4), un juego con el MISMO significado y otro nombre:
     * ind_NombreContador, ind_CedulaContador, ind_TarjetaProfContador,
     * ind_NombreRevisor, ind_CedulaRevisor, ind_TarjetaProfRevisor -que es lo
     * que declaracion.php y microservicios/firmas/api.php ya leen-. La
     * migracion 003 se corrigio para usar esos nombres (ver el archivo), pero
     * el formulario del RIT (dist/icaWebRit.php, core/icaWebRit.js) sigue
     * mandando/esperando los nombres viejos -no hacia falta tocar la vista
     * para esto-, asi que aqui se traduce en el unico punto que habla con la
     * base de datos.
     */
    private static function _mapaColumnaReal()
    {
        return [
            'ind_Cedula_contador'             => 'ind_CedulaContador',
            'ind_Nombre_contador'             => 'ind_NombreContador',
            'ind_Tarjeta_profesional'         => 'ind_TarjetaProfContador',
            'ind_Cedula_revisor'              => 'ind_CedulaRevisor',
            'ind_Nombre_revisor'              => 'ind_NombreRevisor',
            'ind_Tarjeta_profesional_revisor' => 'ind_TarjetaProfRevisor',
        ];
    }

    private static function _esAdministrador()
    {
        // La sesion se abre en class.sessions.php, pero este controlador puede
        // entrar sin que se haya llamado todavia. Sin este arranque, $_SESSION
        // llega vacio y TODO el mundo pareceria no-administrador.
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }

        // Rol 1 = Administrador en conf_rol.
        return isset($_SESSION['id_Rol']) && (int) $_SESSION['id_Rol'] === 1;
    }

    /**
     * ¿Puede la sesión actual leer/escribir el RIT de este contribuyente?
     *
     * Hasta encontrarse esto, _consultarRIT() y _guardarRIT() no comprobaban
     * NADA: ni que hubiera sesión, ni que el ind_Id pedido fuera el del
     * usuario logueado. Confirmado en vivo: un POST sin ninguna cookie leía
     * el RIT completo (cédula, dirección, teléfono, representante legal) de
     * cualquier contribuyente, y otro POST sin cookie lo modificaba. Con
     * sesión de un contribuyente ajeno el resultado era el mismo. Rol 1
     * (Administrador) y 2 (Internos Alcaldía) operan sobre cualquiera; el
     * resto, solo sobre el contribuyente que les corresponde por número de
     * documento -mismo cruce que ya usa ControladorAnexos::
     * puedeOperarSobreEstablecimiento(), que es el patrón que se replica
     * aquí para no reinventarlo distinto en cada controlador.
     */
    public static function puedeOperarSobreContribuyente($idContribuyente, $con)
    {
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }

        if (empty($_SESSION['id_usuario'])) { return false; }

        $rol = isset($_SESSION['id_Rol']) ? (int) $_SESSION['id_Rol'] : 0;
        if (in_array($rol, [1, 2], true)) { return true; }

        $propio = $con->obnerFila($con->consultar(
            "SELECT c.ind_Id
               FROM ind_contribuyentes c
               INNER JOIN conf_usuarios u ON u.usu_NumeroDocumento = c.ind_NumeroIdentificacion
              WHERE u.usu_Id = ? AND c.ind_Id = ?",
            [(int) $_SESSION['id_usuario'], (int) $idContribuyente]
        ));

        return (bool) $propio;
    }

    protected function _consultarRIT()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $idContribuyente = (int) ($_POST['ind_Id'] ?? 0);
        if ($idContribuyente <= 0) {
            $this->_ok = 0;
            $this->_mensaje = 'No se indico el contribuyente';
            return [];
        }

        if (!self::puedeOperarSobreContribuyente($idContribuyente, $con)) {
            $this->_ok = 0;
            $this->_mensaje = 'No tiene permiso para ver este RIT';
            return [];
        }

        // consultar() devuelve un statement de sqlsrv, no un arreglo: hay que
        // pasarlo por obnerFila(), igual que hace el resto del sistema.
        $fila = $con->obnerFila($con->consultar(
            "SELECT c.*, ciu.ciu_Nombre, ciu.ciu_Departamento
               FROM ind_contribuyentes c
               LEFT JOIN conf_ciudades ciu ON ciu.ciu_Id = c.ind_IdCiudad
              WHERE c.ind_Id = ?",
            [$idContribuyente]
        ));

        if (!$fila) {
            $this->_ok = 0;
            $this->_mensaje = 'El contribuyente no existe';
            return [];
        }

        // El SELECT trae las columnas reales (ind_NombreContador, etc); se
        // alias hacia los nombres que el formulario del RIT espera, sin tener
        // que tocar la vista. Ver _mapaColumnaReal().
        foreach (self::_mapaColumnaReal() as $nombreFormulario => $columnaReal) {
            $fila[$nombreFormulario] = $fila[$columnaReal] ?? null;
        }

        // Punto 10: el RIT se da por inicializado en el primer ingreso. No se
        // crea nada nuevo -el contribuyente ya existe desde la inscripcion-,
        // solo se deja constancia de cuando el sistema lo abrio por primera
        // vez, para poder auditarlo y para no repetirlo.
        if (empty($fila['ind_RIT_FechaCreacion'])) {
            $con->consultar(
                "UPDATE ind_contribuyentes
                    SET ind_RIT_FechaCreacion = GETDATE()
                  WHERE ind_Id = ? AND ind_RIT_FechaCreacion IS NULL",
                [$idContribuyente]
            );
            $fila['ind_RIT_FechaCreacion']  = date('Y-m-d H:i:s');
            $fila['rit_recien_inicializado'] = 1;
        }

        // Las fechas salen como DateTime del driver y asi no le sirven a un
        // <input type="date">.
        foreach (['ind_Fecha_matricula', 'ind_Fecha_inicio', 'ind_RIT_FechaCreacion',
                  'ind_FechaCreacion', 'ind_FechaActualizacion'] as $campoFecha) {
            if (isset($fila[$campoFecha]) && $fila[$campoFecha] instanceof \DateTime) {
                $fila[$campoFecha] = $fila[$campoFecha]->format('Y-m-d');
            }
        }

        // Punto 9: el RIT debe mostrar las actividades economicas. Se toman
        // las del año mas reciente que el contribuyente tenga registrado en
        // cualquiera de sus establecimientos; un año fijo dejaba la lista
        // vacia, que es el mismo fallo que traia el certificado.
        // Desde la migracion 005 las actividades son del CONTRIBUYENTE, no de
        // cada local: se leen de ind_actividad_contribuyente. Antes habia que
        // unir todos sus establecimientos y deducirlas, y el mismo codigo podia
        // salir repetido -el contribuyente 30 tenia el 103 en sus dos locales-.
        $stmtActividades = $con->consultar(
            // Sin filtro por año: desde la migracion 007 las actividades del
            // RIT son las VIGENTES, no un registro por periodo. El historico
            // por año vive en cada declaracion
            // (ind_declaraciones_ica_actividades), que guarda su propia copia
            // al liquidar.
            "SELECT ca.acc_Codigo, ca.acc_Nombre, ca.acc_Tarifa,
                    a.atc_IdCodigoActividad
               FROM ind_actividad_contribuyente a
               LEFT JOIN ind_actividadescomercio ca ON ca.acc_Id = a.atc_IdCodigoActividad
              WHERE a.atc_IdContribuyente = ?
              ORDER BY ca.acc_Codigo",
            [$idContribuyente]
        );

        $fila['actividades'] = [];
        while ($act = $con->obnerFila($stmtActividades)) {
            $fila['actividades'][] = $act;
        }

        // Punto 16: el cese de actividades tiene que verse tanto en el
        // establecimiento como en el RIT. El cese es por local -las columnas
        // viven en ind_establecimientos-, asi que aqui se listan todos los
        // establecimientos del contribuyente con su estado. De solo lectura:
        // se registra desde el modulo de establecimientos y unicamente la
        // Alcaldia (punto 14).
        $stmtEstablecimientos = $con->consultar(
            // est_Activo (int) es el indicador activo/inactivo. NO confundir con
            // est_Activos (float), que es el monto de activos del formulario:
            // son dos columnas distintas con nombres casi iguales.
            "SELECT est_Id, est_Nombre, est_Direccion, est_Activo,
                    est_Fecha_cierre, est_Causal, est_Resolucion_cierre
               FROM ind_establecimientos
              WHERE est_IdContribuyente = ?
              ORDER BY est_Id",
            [$idContribuyente]
        );

        $fila['establecimientos'] = [];
        while ($est = $con->obnerFila($stmtEstablecimientos)) {
            // 1900-01-01 es el centinela de "nunca se lleno" de esta base.
            if ($est['est_Fecha_cierre'] instanceof \DateTime) {
                $f = $est['est_Fecha_cierre']->format('Y-m-d');
                $est['est_Fecha_cierre'] = ($f === '1900-01-01') ? '' : $f;
            }
            $fila['establecimientos'][] = $est;
        }

        $this->_ok = 1;
        $this->_mensaje = 'RIT consultado';

        return $fila;
    }

    /*
     * Lo que le falta al RIT (campos y documentos) vive en RitFirma::faltantes(),
     * porque la firma aplica la misma regla. Dos respuestas del cliente se
     * ajustaron al sentido común: el primer apellido se exige solo a la persona
     * natural (una jurídica no tiene), y el DV solo existe con NIT; si falta, se
     * calcula (_completarDV). Contador y revisor fiscal quedan opcionales: son
     * "para quien la ley le obliga", y eso el sistema no lo sabe.
     */

    /**
     * Dígito de verificación de un NIT: el algoritmo de la DIAN, el mismo de
     * core/contribuyentes.js. Vive en DAO_Contribuyentes desde 2026-09-28 porque
     * la creación de cuentas (class.usuarios.php) también lo necesita.
     */
    private static function _digitoVerificacion($nit)
    {
        return \erpsoftsas\DAO_Contribuyentes::digitoVerificacion($nit);
    }

    /**
     * El DV es obligatorio (cliente, 2026-09-25), pero en el RIT no se edita y
     * muchos NIT viejos lo tienen mal. Como sale del propio NIT, se calcula en
     * vez de trabar el guardado: si falta o no coincide, se corrige. "Falta" no
     * se puede mirar solo como vacío: la columna no admite NULL y los que nunca
     * lo tuvieron guardan 0, que a su vez es un DV válido para algunos NIT.
     * Solo con NIT (tipo 5): con cédula el sistema no usa DV.
     */
    private static function _completarDV($con, $idContribuyente)
    {
        try {
            $f = $con->obnerFila($con->consultar(
                "SELECT ind_IdTipoDocumento, ind_NumeroIdentificacion, ind_DV
                   FROM ind_contribuyentes WHERE ind_Id = ?",
                [(int) $idContribuyente]
            ));
            if (!$f || (int) $f['ind_IdTipoDocumento'] !== 5) { return; }

            $nit = preg_replace('/\D/', '', (string) $f['ind_NumeroIdentificacion']);
            if ($nit === '') { return; }

            $dv = self::_digitoVerificacion($nit);
            $guardado = trim((string) $f['ind_DV']);
            if ($guardado !== '' && (int) $guardado === $dv) { return; }

            $con->consultar(
                "UPDATE ind_contribuyentes SET ind_DV = ? WHERE ind_Id = ?",
                [$dv, (int) $idContribuyente]
            );
        } catch (\Throwable $e) {
            error_log('[contribuyentes] no se pudo completar el DV: ' . $e->getMessage());
        }
    }

    protected function _guardarRIT()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $idContribuyente = (int) ($_POST['ind_Id'] ?? 0);
        if ($idContribuyente <= 0) {
            $this->_ok = 0;
            $this->_mensaje = 'No se indico el contribuyente';
            return [];
        }

        if (!self::puedeOperarSobreContribuyente($idContribuyente, $con)) {
            $this->_ok = 0;
            $this->_mensaje = 'No tiene permiso para editar este RIT';
            return [];
        }


        // El cliente pidio que la autorizacion de notificacion electronica sea
        // requisito para actualizar el RIT: sin marcarla, no se guarda nada.
        // Es la manifestacion con la que la Alcaldia queda habilitada para
        // notificar actos administrativos al correo registrado, asi que tiene
        // que ser un acto consciente y no un campo mas del formulario.
        //
        // Se comprueba en el SERVIDOR y no solo con el 'required' de la
        // pantalla: un required se quita desde la consola del navegador.
        if (empty($_POST['ind_Autorizacion'])) {
            $this->_ok = 0;
            $this->_mensaje = 'Debe autorizar la notificación electrónica para actualizar el RIT.';
            return [];
        }

        /*
         * Codigos CIIU del RUT: cuatro digitos, y el principal obligatorio.
         *
         * Lo pidio Jennifer (Alcaldia) el 2026-08-20 y la base le da la razon:
         * estas casillas tenian guardado un "wer" y varios "1", "2", "3".
         *
         * El motivo de fondo no es la higiene del dato, es una confusion real
         * entre dos numeraciones parecidas: las actividades del acuerdo
         * municipal -las que liquidan el impuesto- son de TRES digitos, y los
         * CIIU de la DIAN, que son los que van aqui, de CUATRO. Quien escribe
         * el de tres no se equivoca de tecla: se equivoca de tabla.
         *
         * Se valida aqui y no solo en el formulario porque un pattern de HTML
         * se salta desde la consola del navegador.
         */
        $errorCiiu = self::_validarCodigosCiiu();
        if ($errorCiiu !== null) {
            $this->_ok = 0;
            $this->_mensaje = $errorCiiu;
            return [];
        }
        // puedeOperarSobreContribuyente() ya verifica existencia para
        // cualquier rol que NO sea administrador (el cruce por documento no
        // encuentra nada si el ind_Id no existe). Pero para rol 1/2 devuelve
        // true de inmediato sin comprobar nada mas: un administrador podia
        // "guardar" un ind_Id inexistente, el UPDATE de mas abajo afectaba 0
        // filas sin que sqlsrv marcara error, y la respuesta seguia siendo
        // ok:1 "RIT actualizado" -exito falso sobre un contribuyente que no
        // existe-.
        $existe = $con->obnerFila($con->consultar(
            "SELECT ind_Id FROM ind_contribuyentes WHERE ind_Id = ?",
            [$idContribuyente]
        ));
        if (!$existe) {
            $this->_ok = 0;
            $this->_mensaje = 'El contribuyente no existe';
            return [];
        }

        /*
         * Campos y documentos obligatorios (revisión del cliente 2026-09-25).
         *
         * El RIT ya no se guarda incompleto: ni el contribuyente ni la Alcaldía
         * en ventanilla, en inscripción igual que en actualización. Antes se
         * guardaba a medias y los documentos solo se exigían al firmar; el
         * cliente confirmó que quien tenga el RIT sin documentos los suba antes
         * de guardar cualquier cambio.
         */
        include_once SERVER . '/business/class.ritFirma.php';
        $faltan = \erpsoftsas\RitFirma::faltantes($con, $idContribuyente, $_POST);
        if ($faltan) {
            $this->_ok = 0;
            $this->_mensaje = 'Para guardar el RIT falta: ' . implode('; ', $faltan) . '.';
            return ['faltan' => $faltan];
        }

        $sets    = [];
        $valores = [];

        $soloAdmin  = self::_camposSoloAdministrador();
        $esAdmin    = self::_esAdministrador();
        $ignorados  = [];

        /* Columnas de la migración 033. El formulario las manda SIEMPRE; si el
           código llega a una base donde la migración aún no corrió, grabarlas
           tumbaría el guardado ENTERO del RIT para todos. Se omiten hasta que
           existan, y el resto del RIT se guarda normal. */
        $faltaMig033 = [];
        foreach (['ind_EsConsorcio', 'ind_PatrimonioAutonomo'] as $col) {
            $hay = $con->obnerFila($con->consultar(
                "SELECT COL_LENGTH('dbo.ind_contribuyentes', ?) AS l", [$col]
            ));
            if (empty($hay['l'])) { $faltaMig033[] = $col; }
        }

        foreach (self::_camposRIT() as $campo) {
            if (!array_key_exists($campo, $_POST)) { continue; }
            if (in_array($campo, $faltaMig033, true)) { continue; }

            // Puntos 14 y 15: los datos de contador y revisor solo los graba
            // el administrador. Si llegan de otro rol se descartan en silencio
            // en vez de fallar, para que el contribuyente si pueda guardar el
            // resto de su RIT.
            if (in_array($campo, $soloAdmin, true) && !$esAdmin) {
                $ignorados[] = $campo;
                continue;
            }

            $valor = trim((string) $_POST[$campo]);

            // ind_Telefono es bigint, pero un (int) crudo corta el string en
            // el primer caracter no numerico tras un signo -"+57 312 566 6656"
            // quedaba guardado como "57"-. Se extraen solo los digitos en vez
            // de castear a ciegas, para no destruir un numero con indicativo,
            // espacios o guiones (formato normal en Colombia).
            if ($campo === 'ind_Telefono') {
                $soloDigitos = preg_replace('/\D/', '', $valor);
                $valor = ($soloDigitos === '') ? null : $soloDigitos;
            } elseif (in_array($campo, ['ind_IdCiudad', 'ind_Persona'], true)) {
                $valor = ($valor === '') ? null : (int) $valor;
            }

            // Un correo mal escrito deja al contribuyente sin poder firmar,
            // porque el OTP no llega a ningun lado.
            if (in_array($campo, ['ind_Email', 'ind_EmailContador', 'ind_EmailRevisor', 'ind_Email_representante'], true)) {
                if ($valor !== '' && !filter_var($valor, FILTER_VALIDATE_EMAIL)) {
                    $this->_ok = 0;
                    $this->_mensaje = 'El correo "' . $valor . '" no es válido';
                    return [];
                }
            }

            // Las dos casillas de seleccion multiple llegan como codigos
            // separados por coma. Se filtran contra su catalogo aqui, no solo
            // en el formulario: lo que quede acaba impreso en un certificado
            // tributario, y una lista de <input type=checkbox> se manipula
            // desde la consola del navegador.
            // Las dos exenciones son BIT: llega '0' o '1' desde el campo oculto.
            if (in_array($campo, ['ind_NoSujetas', 'ind_SinAvisosTableros', 'ind_EsConsorcio', 'ind_PatrimonioAutonomo'], true)) {
                $valor = ($valor === '1') ? 1 : 0;
            }

            if (in_array($campo, ['ind_RegimenTributario', 'ind_Responsabilidades'], true)) {
                $valor = self::_normalizarSeleccionMultiple($campo, $valor);
                if ($valor === '') { $valor = null; }
            }

            // Una fecha vacia tiene que quedar NULL, no cadena vacia: SQL
            // Server convierte '' en 1900-01-01, que es justo la basura que la
            // migracion 003 se ocupo de no arrastrar.
            if (in_array($campo, ['ind_Fecha_matricula', 'ind_Fecha_inicio'], true)) {
                $valor = ($valor === '') ? null : $valor;
            }

            if ($campo === 'ind_Ind_camara_comercio') {
                $valor = ($valor === '') ? null : (int) $valor;
            }

            // Ver _mapaColumnaReal(): 6 de estos campos usan un nombre en el
            // formulario distinto al de la columna real en la base.
            $columnaReal = self::_mapaColumnaReal()[$campo] ?? $campo;

            $sets[]    = $columnaReal . ' = ?';
            $valores[] = $valor;
        }

        if (!$sets) {
            $this->_ok = 0;
            $this->_mensaje = 'No se recibió ningún dato del RIT';
            return [];
        }

        /*
         * El correo de notificacion del RIT y el de la cuenta son EL MISMO.
         *
         * Instruccion del cliente el 2026-08-26: "si deben ser iguales, el de
         * usuario y el del RIT es el mismo... no permitir repeticion de
         * correos electronicos de base de datos".
         *
         * Eran dos campos sueltos que nadie sincronizaba, y por eso el codigo
         * de firma llegaba a un correo distinto del que el contribuyente
         * acababa de escribir -fue justo el sintoma que reportaron-.
         *
         * Se comprueba ANTES de escribir: si el correo ya es de otro, no se
         * guarda nada. Guardar el RIT y fallar despues al sincronizar dejaria
         * los dos campos distintos otra vez, que es lo que se quiere evitar.
         */
        if (array_key_exists('ind_Email', $_POST)) {
            $correo = trim((string) $_POST['ind_Email']);

            /*
             * Si el correo NO cambio, no se comprueba nada.
             *
             * El formulario manda siempre el campo entero (serialize()), asi
             * que hasta guardar un telefono reenvia el correo tal cual. Sin
             * esta salida, un contribuyente que YA tuviera el correo repetido
             * -de antes, cuando nada lo impedia- se quedaba con el RIT
             * congelado: no podia corregir su direccion, ni su telefono, ni
             * sus codigos CIIU, porque el guardado entero aborta aqui. Y el
             * mensaje le echaba la culpa a un correo que no habia tocado.
             *
             * La regla es para el correo que se ESTA poniendo. Lo que ya
             * estaba se limpia por su lado, no negandole el resto del
             * formulario a quien lo padece.
             */
            $actual = $con->obnerFila($con->consultar(
                "SELECT ind_Email, ind_NumeroIdentificacion FROM ind_contribuyentes WHERE ind_Id = ?",
                [$idContribuyente]
            ));
            $sinCambio = $actual
                && mb_strtolower(trim((string) $actual['ind_Email'])) === mb_strtolower($correo);

            if ($correo !== '' && !$sinCambio
                && self::_correoDeOtro($con, $idContribuyente, $actual['ind_NumeroIdentificacion'] ?? '', $correo)) {
                $this->_ok = 0;
                $this->_mensaje = 'El correo "' . $correo . '" ya está registrado por otro '
                                . 'contribuyente. Cada correo puede pertenecer a uno solo.';
                return [];
            }
        }

        $valores[] = $idContribuyente;

        /*
         * LAS ACTIVIDADES VIAJAN CON EL RIT, COMO CAMBIOS (revision 2026-09-28).
         *
         * Iban solo por "Guardar actividades" (funcion 8). Quien agregaba
         * actividades y pulsaba Guardar veia "RIT actualizado" y las perdia: no se
         * mandaban, y la pantalla se repintaba con las de la base. Ahora viajan
         * con Guardar, con la MISMA regla de obligatorios de arriba.
         *
         * Pero viajan como CAMBIOS: las que el usuario agrego y las que quito
         * desde que cargo la tabla (_cambiosActividades), no la lista entera. La
         * primera version de este arreglo mandaba la lista y el servidor borraba
         * todas y las volvia a crear, y eso tenia costos que no se veian:
         *   - El PDF de retencion toma como actividad PRINCIPAL la de menor atc_Id
         *     (pdfRetenciones.php). Recrearlas en el orden de la pantalla (por
         *     codigo) cambiaba la principal y la secundaria con CUALQUIER Guardar
         *     del RIT, hasta al reimprimir retenciones ya presentadas. En local,
         *     el contribuyente 32 pasaba de 301 a 201.
         *   - Se perdian atc_Anio y la fecha de creacion de cada fila.
         *   - Una pestaña abierta desde antes borraba la actividad que la Alcaldia
         *     hubiera agregado en otra, con solo guardar un telefono.
         * Con cambios, lo que no se toco conserva su fila (id, año y fecha), lo
         * nuevo queda al final y lo que otra pestaña agrego sigue ahi.
         */
        $cambiosActividades = self::_cambiosActividades($con);

        // Datos y actividades juntos: o queda el RIT entero, o nada.
        try {
            $con->begin();

            $con->consultar(
                "UPDATE ind_contribuyentes SET " . implode(', ', $sets) . ",
                        ind_FechaActualizacion = GETDATE()
                  WHERE ind_Id = ?",
                $valores
            );

            self::_aplicarCambiosActividades($con, $idContribuyente, $cambiosActividades);

            $con->commit();
        } catch (\Throwable $e) {
            try { $con->rollback(); } catch (\Throwable $e2) { /* ya no habia transaccion */ }
            error_log('[contribuyentes] no se guardo el RIT de ' . $idContribuyente . ': ' . $e->getMessage());
            $this->_ok = 0;
            $this->_mensaje = 'No se pudo guardar el RIT y no quedó nada a medias. Intente de nuevo; '
                            . 'si el problema sigue, avise a soporte.';
            return [];
        }

        self::_completarDV($con, $idContribuyente);

        // Y se sincroniza con la cuenta de acceso, para que sean el mismo (ver
        // _sincronizarCorreoCuenta: una sola cuenta, y si no se puede, se avisa).
        $avisoSincronizacion = '';

        if (array_key_exists('ind_Email', $_POST) && trim((string) $_POST['ind_Email']) !== '') {
            $avisoSincronizacion = self::_sincronizarCorreoCuenta(
                $con, $idContribuyente, trim((string) $_POST['ind_Email'])
            );
        }

        $this->_ok = 1;
        $this->_mensaje = ($ignorados
            ? 'RIT actualizado. Los datos de contador y revisor solo los puede cambiar el administrador.'
            : 'RIT actualizado') . $avisoSincronizacion;

        return ['ind_Id' => $idContribuyente, 'ignorados' => $ignorados];
    }

    /**
     * ¿El correo ya es de OTRO contribuyente (o de la cuenta de otro)? Lo usan el
     * RIT y la ficha de Contribuyentes (Editar y la ventana "Informacion del
     * Contribuyente"), porque la regla del cliente es una sola (2026-08-26):
     * el correo de la cuenta y el del contribuyente son el mismo, y no se repite.
     *
     * "De otro" se mide por DOCUMENTO, no por fila. El documento es quien es el
     * contribuyente; la fila es solo donde quedo escrito. En esta base hay un
     * documento con DOS registros de contribuyente -la misma persona, inscrita
     * dos veces-, y comparando por ind_Id el segundo registro le daria "ese
     * correo ya es de otro" a la persona sobre si misma. Justo lo contrario de lo
     * que pidio el cliente. Del lado de conf_usuarios ya se comparaba por
     * documento; faltaba igualar el otro lado.
     *
     * El documento vacio no agrupa: sin el, dos registros sueltos pasarian por la
     * misma persona sin serlo. $documento es el que tendra el contribuyente al
     * guardar (en Editar puede cambiar en el mismo guardado).
     */
    private static function _correoDeOtro($con, $idContribuyente, $documento, $correo)
    {
        $fila = $con->obnerFila($con->consultar(
            "DECLARE @doc VARCHAR(50) = LTRIM(RTRIM(ISNULL(?, '')));

             SELECT TOP 1 1 AS x FROM conf_usuarios u
              WHERE u.usu_Correo = ?
                AND (@doc = '' OR LTRIM(RTRIM(ISNULL(u.usu_NumeroDocumento, ''))) <> @doc)
             UNION ALL
             SELECT TOP 1 1 FROM ind_contribuyentes c
              WHERE c.ind_Email = ?
                AND c.ind_Id <> ?
                AND (@doc = '' OR LTRIM(RTRIM(ISNULL(c.ind_NumeroIdentificacion, ''))) <> @doc)",
            [(string) $documento, $correo, $correo, (int) $idContribuyente]
        ));
        return (bool) $fila;
    }

    /**
     * Copia el correo del contribuyente a su cuenta de acceso. Devuelve el aviso
     * que hay que añadir al mensaje ('' si no hay nada que decir).
     *
     * SE SINCRONIZA UNA CUENTA, NO TODAS LAS DEL DOCUMENTO
     *
     * El cruce natural es por numero de documento, pero un documento puede tener
     * mas de una cuenta -en esta base, el 1052400237 tiene dos-. El UPDATE por
     * documento les ponia el MISMO correo a todas, y eso ahora es imposible:
     * desde la migracion 022 hay un indice unico sobre usu_Correo. El motor
     * rechazaba el UPDATE y, de paso, se llevaba por delante el guardado entero.
     *
     * Con dos cuentas no hay forma honesta de adivinar cual es "la" cuenta del
     * contribuyente, y elegir mal seria mandarle el codigo de firma al buzon
     * equivocado. Asi que se sincroniza solo cuando hay UNA, y cuando no, se
     * dice. La anomalia de fondo -dos cuentas para un mismo documento- la
     * resuelve la Alcaldia, no este UPDATE. Sin cuentas no hay nada que
     * sincronizar y no es un problema: la Alcaldia inscribe contribuyentes que
     * aun no tienen usuario.
     *
     * Que la sincronizacion falle nunca tumba el guardado: lo escrito es valido y
     * ya esta grabado. Pero se AVISA, porque callarlo dejaria el codigo de firma
     * yendo al correo viejo sin que se sepa.
     */
    private static function _sincronizarCorreoCuenta($con, $idContribuyente, $correo)
    {
        try {
            $cuentas = [];
            $stmt = $con->consultar(
                "SELECT u.usu_Id
                   FROM conf_usuarios u
                   INNER JOIN ind_contribuyentes c
                           ON c.ind_NumeroIdentificacion = u.usu_NumeroDocumento
                  WHERE c.ind_Id = ?",
                [(int) $idContribuyente]
            );
            while ($f = $con->obnerFila($stmt)) { $cuentas[] = (int) $f['usu_Id']; }

            if (count($cuentas) === 1) {
                $con->consultar(
                    "UPDATE conf_usuarios
                        SET usu_Correo = ?, usu_FechaActualizacion = GETDATE()
                      WHERE usu_Id = ?",
                    [$correo, $cuentas[0]]
                );
            } elseif (count($cuentas) > 1) {
                return ' Ojo: este documento tiene más de una cuenta de '
                     . 'acceso, así que el correo no se copió a ninguna para no elegir mal. '
                     . 'La Alcaldía debe dejar una sola cuenta por contribuyente.';
            }
            return '';

        } catch (\Throwable $e) {
            error_log('[contribuyentes] no se pudo sincronizar usu_Correo del contribuyente '
                      . $idContribuyente . ': ' . $e->getMessage());

            return ' El correo se guardó, pero no se pudo poner también en la cuenta de '
                 . 'acceso porque ya pertenece a otra. Avise a la Alcaldía: hasta que se '
                 . 'resuelva, el código de firma seguirá llegando al correo anterior de la cuenta.';
        }
    }

    /**
     * Punto 11 (reunion 2026-08-18): las actividades economicas las registra el
     * contribuyente desde su RIT.
     *
     * Antes se guardaban por establecimiento (ind_actividad_establecimiento) y
     * el RIT solo las mostraba de lectura; la migracion 005 las subio a
     * ind_actividad_contribuyente, que es donde el negocio ya las usaba: la
     * declaracion es una por contribuyente y agrega por codigo CIIU.
     *
     * Desde el 2026-09-28 lo normal es que viajen con Guardar (funcion 7); esta
     * funcion queda por compatibilidad, y aplica CAMBIOS igual que Guardar (ver
     * _aplicarCambiosActividades): nunca borra y recrea el juego completo.
     */
    protected function _guardarActividadesRIT()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $idContribuyente = (int) ($_POST['ind_Id'] ?? 0);

        if ($idContribuyente <= 0) {
            $this->_ok = 0;
            $this->_mensaje = 'No se indicó el contribuyente';
            return [];
        }

        if (!self::puedeOperarSobreContribuyente($idContribuyente, $con)) {
            $this->_ok = 0;
            $this->_mensaje = 'No tiene permiso para editar este RIT';
            return [];
        }

        /*
         * Las actividades son parte del RIT (se imprimen y entran en la firma), asi
         * que tambien aqui rige "incompleto no se guarda" (RitFirma::faltantes,
         * sobre lo guardado). La pantalla ya no llama a esta funcion: las
         * actividades viajan con Guardar (funcion 7). Queda por compatibilidad,
         * sin ser una puerta para cambiar el RIT saltandose la regla.
         */
        include_once SERVER . '/business/class.ritFirma.php';
        $faltan = \erpsoftsas\RitFirma::faltantes($con, $idContribuyente);
        if ($faltan) {
            $this->_ok = 0;
            $this->_mensaje = 'Para guardar el RIT falta: ' . implode('; ', $faltan) . '.';
            return ['faltan' => $faltan];
        }

        // Cambios (como Guardar) o, a la manera vieja, la lista completa: esa se
        // convierte en cambios contra lo guardado, para no recrear las filas.
        $cambios = (array_key_exists('actividadesAgregadas', $_POST) || array_key_exists('actividadesQuitadas', $_POST))
            ? self::_cambiosActividades($con)
            : self::_cambiosDesdeLista($con, $idContribuyente, (array) ($_POST['actividades'] ?? []));

        try {
            $con->begin();
            self::_aplicarCambiosActividades($con, $idContribuyente, $cambios);
            $con->commit();
        } catch (\Throwable $e) {
            try { $con->rollback(); } catch (\Throwable $e2) { /* ya no habia transaccion */ }
            throw $e;
        }

        $this->_ok = 1;
        $this->_mensaje = ($cambios['agregar'] || $cambios['quitar'])
            ? 'Actividades económicas actualizadas'
            : 'No hubo cambios en las actividades';

        return ['agregadas' => count($cambios['agregar']), 'quitadas' => count($cambios['quitar'])];
    }

    /**
     * Ids de actividad que existen en el catalogo, sin repetir y en el orden en
     * que llegaron: la tabla tiene indice UNICO por (contribuyente, actividad) y
     * un duplicado abortaria el guardado entero. Lo que no existe se descarta.
     */
    private static function _actividadesValidas($con, array $enviadas)
    {
        $limpias = [];
        foreach ($enviadas as $id) {
            $id = (int) $id;
            if ($id <= 0 || in_array($id, $limpias, true)) { continue; }
            $existe = $con->obnerFila($con->consultar(
                "SELECT acc_Id FROM ind_actividadescomercio WHERE acc_Id = ?", [$id]
            ));
            if ($existe) { $limpias[] = $id; }
        }
        return $limpias;
    }

    /**
     * Los cambios de actividades que manda la pantalla del RIT: las que el
     * usuario AGREGO y las que QUITO desde que cargo la tabla
     * (actividadesAgregadas / actividadesQuitadas). Sin ninguna de las dos no hay
     * cambios, y eso es lo que manda una pantalla en la que no se tocaron.
     *
     * Las agregadas pasan por el catalogo (_actividadesValidas). Un id que llegue
     * en las dos listas se ignora: no se sabe cual de las dos quiso el usuario.
     *
     * @return array{agregar:int[], quitar:int[]}
     */
    private static function _cambiosActividades($con)
    {
        $agregar = self::_actividadesValidas($con, (array) ($_POST['actividadesAgregadas'] ?? []));

        $quitar = [];
        foreach ((array) ($_POST['actividadesQuitadas'] ?? []) as $id) {
            $id = (int) $id;
            if ($id > 0 && !in_array($id, $quitar, true)) { $quitar[] = $id; }
        }

        $ambas = array_intersect($agregar, $quitar);
        return [
            'agregar' => array_values(array_diff($agregar, $ambas)),
            'quitar'  => array_values(array_diff($quitar, $ambas)),
        ];
    }

    /**
     * Una lista COMPLETA (la forma vieja de la funcion 8) convertida en cambios
     * contra lo que ya esta guardado, para aplicarla sin recrear las filas.
     *
     * @return array{agregar:int[], quitar:int[]}
     */
    private static function _cambiosDesdeLista($con, $idContribuyente, array $lista)
    {
        $deseadas = self::_actividadesValidas($con, $lista);

        $guardadas = [];
        $stmt = $con->consultar(
            "SELECT atc_IdCodigoActividad FROM ind_actividad_contribuyente
              WHERE atc_IdContribuyente = ? ORDER BY atc_Id",
            [(int) $idContribuyente]
        );
        while ($f = $con->obnerFila($stmt)) { $guardadas[] = (int) $f['atc_IdCodigoActividad']; }

        return [
            'agregar' => array_values(array_diff($deseadas, $guardadas)),
            'quitar'  => array_values(array_diff($guardadas, $deseadas)),
        ];
    }

    /**
     * Aplica los cambios SIN tocar lo demas: borra solo las quitadas y agrega al
     * FINAL las nuevas que no esten ya. Las filas que siguen conservan su atc_Id,
     * su atc_Anio y su fecha de creacion, y con el id el orden: la retencion toma
     * como principal la de menor atc_Id (ver _guardarRIT).
     *
     * Quien llama abre la transaccion. El NOT EXISTS con UPDLOCK/HOLDLOCK cubre
     * que otra pestaña la agregue en el mismo instante: el indice unico por
     * (contribuyente, actividad) haria fallar el guardado entero.
     */
    private static function _aplicarCambiosActividades($con, $idContribuyente, array $cambios)
    {
        $idContribuyente = (int) $idContribuyente;

        foreach ($cambios['quitar'] as $id) {
            $con->consultar(
                "DELETE FROM ind_actividad_contribuyente
                  WHERE atc_IdContribuyente = ? AND atc_IdCodigoActividad = ?",
                [$idContribuyente, (int) $id]
            );
        }

        foreach ($cambios['agregar'] as $id) {
            $con->consultar(
                "INSERT INTO ind_actividad_contribuyente (atc_IdContribuyente, atc_IdCodigoActividad)
                 SELECT ?, ?
                  WHERE NOT EXISTS (SELECT 1 FROM ind_actividad_contribuyente WITH (UPDLOCK, HOLDLOCK)
                                     WHERE atc_IdContribuyente = ? AND atc_IdCodigoActividad = ?)",
                [$idContribuyente, (int) $id, $idContribuyente, (int) $id]
            );
        }
    }

}

// Clase de excepción específica para Contribuyentes
class ContribuyentesException extends \Exception { }

// Ejecutamos la función principal
\erpsoftsas\ControladorContribuyentes::run();
