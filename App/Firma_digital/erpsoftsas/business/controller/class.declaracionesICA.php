<?php
namespace erpsoftsas;

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/DAO/DAO_DeclaracionesICA.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER . '/business/controller/class.logs.php';
include_once SERVER . '/business/config.tributario.php';

class ControladorDeclaracionesICA extends \erpsoftsas\Cabecera 
{
    private $_funcion;
    private $_ok;
    private $_mensaje;

    /**
     * Contribuyente al que esta atado el usuario de la sesion, o null.
     * Mismo vinculo que usan los demas controladores:
     * conf_usuarios.usu_NumeroDocumento = ind_contribuyentes.ind_NumeroIdentificacion.
     */

    /**
     * Siguiente numero de formulario para el año dado.
     *
     * Formato AAAA + consecutivo de 6 digitos -2026000001-, que es el que usa
     * la Alcaldia en su formulario impreso. Antes NO habia generador: se
     * copiaba el IDENTITY de la fila, asi que el "numero de declaracion" era
     * el id interno de la tabla y iba por 218.
     *
     * El reparto lo hace sp_siguiente_numero_declaracion (migracion 012), que
     * incrementa y captura en una sola sentencia bajo el bloqueo de la fila:
     * dos contribuyentes declarando a la vez no pueden llevarse el mismo
     * numero. Un SELECT MAX + 1 si lo permitiria.
     *
     * Si el procedimiento no existiera -base sin la migracion 012- se cae al
     * comportamiento anterior devolviendo null, y quien llama usa el id. Asi
     * una instalacion sin migrar sigue funcionando en vez de romperse.
     */
    private function _siguienteNumeroDeclaracion($con, $anio)
    {
        try {
            $fila = $con->obnerFila($con->consultar(
                "DECLARE @n BIGINT;
                 EXEC dbo.sp_siguiente_numero_declaracion @ANIO = ?, @NUMERO = @n OUTPUT;
                 SELECT @n AS numero;",
                [(int) $anio]
            ));

            $n = isset($fila['numero']) ? (float) $fila['numero'] : 0;
            return ($n > 0) ? $n : null;

        } catch (\Exception $e) {
            error_log('[declaraciones] no se pudo generar el consecutivo: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Trae a la declaracion nueva el anticipo que se liquido el año pasado.
     *
     * Pedido el 2026-08-25: "que se cruce con la informacion del anticipo de
     * la declaracion del año anterior".
     *
     * COMO SE CRUZAN LAS DOS CASILLAS
     *
     * En el formulario, el anticipo aparece dos veces con sentidos opuestos:
     *
     *     casilla 30 (concepto 9)  (+) ANTICIPO DEL AÑO SIGUIENTE
     *     casilla 29 (concepto 8)  (-) MENOS ANTICIPO LIQUIDADO EN EL AÑO ANTERIOR
     *
     * Lo que un contribuyente liquido como anticipo del año siguiente en su
     * declaracion de 2025 es exactamente lo que debe descontarse en la de
     * 2026. Hasta ahora habia que copiarlo a mano, mirando la declaracion
     * anterior; ese es justamente el paso donde se cometen errores, y
     * equivocarse aqui cambia el impuesto a pagar.
     *
     * DE DONDE SE TOMA
     *
     * De la declaracion PRESENTADA (dec_Estado = 2) de ese contribuyente para
     * el año anterior. Un borrador no sirve: su anticipo todavia puede cambiar.
     * Si hay correccion se toma la mas reciente, que es la que vale.
     *
     * NO PISA LO QUE YA HAYA. El concepto 8 es uno de los nueve renglones que
     * escribe el usuario (migracion 010), asi que solo se rellena si esta
     * vacio: sugerir un valor es ayudar, sobreescribir el que alguien puso a
     * proposito es otra cosa.
     *
     * Devuelve el valor traido, o null si no habia de donde.
     */
    private function _cruzarAnticipoDelAnioAnterior($con, $idDeclaracion, $idContribuyente, $anio)
    {
        try {
            $anterior = $con->obnerFila($con->consultar(
                "SELECT TOP 1 dec_ValorConcepto9
                   FROM ind_declaraciones_ica
                  WHERE dec_IdContribuyente = ?
                    AND dec_AnioDeclaracion = ?
                    AND dec_Estado = 2
                  ORDER BY dec_Id DESC",
                [(int) $idContribuyente, (int) $anio - 1]
            ));

            $anticipo = isset($anterior['dec_ValorConcepto9'])
                ? (float) $anterior['dec_ValorConcepto9'] : 0;

            if ($anticipo <= 0) { return null; }

            $con->consultar(
                "UPDATE ind_declaraciones_ica
                    SET dec_ValorConcepto8 = ?
                  WHERE dec_Id = ?
                    AND ISNULL(dec_ValorConcepto8, 0) = 0",
                [$anticipo, (int) $idDeclaracion]
            );

            return $anticipo;

        } catch (\Throwable $e) {
            // Que falle el cruce no puede impedir crear la declaracion: es una
            // ayuda, y el contribuyente siempre puede escribir el valor.
            error_log('[declaraciones] no se pudo cruzar el anticipo del año anterior: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * El dec_Id de una declaracion a partir de su NUMERO.
     *
     * EL ERROR QUE ARREGLA (encontrado el 2026-08-28, reportado por el cliente)
     *
     * La tabla de actividades se engancha por dia_IdDeclaracion, y ese campo es
     * el dec_Id: asi lo lee sp_calculo_comercio -"da.dia_iddeclaracion =
     * di.dec_id"- y asi lo leen los dos PDF.
     *
     * Pero la pantalla manda el NUMERO de declaracion, no el id. Durante mucho
     * tiempo dio igual, porque el numero ERA el identity de la fila: los dos
     * valores coincidian y nadie lo noto.
     *
     * La migracion 012 rompio esa coincidencia. Desde entonces el numero es un
     * consecutivo por año (2026000001) y el id sigue siendo 232, de modo que
     * las actividades se guardaban colgadas de un id que no existe. El
     * procedimiento no las encontraba, sumaba cero, y el impuesto salia en
     * CERO; el PDF tampoco las imprimia. Medido: la misma actividad da 700.000
     * cuando numero e id coinciden y 0 cuando no.
     *
     * Se traduce aqui, en un solo sitio, en vez de cambiar el procedimiento:
     * dia_IdDeclaracion se llama "Id" y todo lo que lo LEE espera el id. Lo que
     * estaba mal era lo que lo escribia.
     *
     * Acepta que le pasen ya un dec_Id -devuelve ese mismo-, porque hay
     * caminos internos que lo hacen y no deben romperse.
     */
    /**
     * Los numeros de renglon que existen de verdad como columna.
     *
     * Se leen del esquema en vez de escribirlos a mano: si mañana se anade un
     * concepto nuevo, la lista se entera sola, y si alguien inventa un numero,
     * no esta. Se cachea por peticion.
     */
    private static $renglones = null;

    private static function _renglonesValidos($con)
    {
        if (self::$renglones !== null) { return self::$renglones; }

        self::$renglones = [];
        try {
            $st = $con->consultar(
                "SELECT name FROM sys.columns
                  WHERE object_id = OBJECT_ID('dbo.ind_declaraciones_ica')
                    AND name LIKE 'dec_ValorConcepto%'", []
            );
            while ($f = $con->obnerFila($st)) {
                $n = substr($f['name'], strlen('dec_ValorConcepto'));
                if (ctype_digit($n)) { self::$renglones[] = (int) $n; }
            }
        } catch (\Throwable $e) {
            error_log('[declaraciones] no se pudieron leer los renglones: ' . $e->getMessage());
        }

        return self::$renglones;
    }

    private static function _filaDeLaDeclaracion($con, $numeroOId)
    {
        $v = trim((string) $numeroOId);
        if ($v === '' || !ctype_digit($v)) { return null; }

        $fila = $con->obnerFila($con->consultar(
            "SELECT TOP 1 dec_Id, dec_NumeroDeclaracion, dec_AnioDeclaracion, dec_MesDeclaracion
               FROM ind_declaraciones_ica
              WHERE dec_NumeroDeclaracion = ? OR dec_Id = ?
              ORDER BY CASE WHEN dec_NumeroDeclaracion = ? THEN 0 ELSE 1 END",
            [$v, $v, $v]
        ));

        if (!isset($fila['dec_Id'])) { return null; }

        return [
            'id'     => (int) $fila['dec_Id'],
            'numero' => $fila['dec_NumeroDeclaracion'] ?: $fila['dec_Id'],
            'anio'   => $fila['dec_AnioDeclaracion'],
            'mes'    => $fila['dec_MesDeclaracion'],
        ];
    }

    private static function _idDeLaDeclaracion($con, $numeroOId)
    {
        $f = self::_filaDeLaDeclaracion($con, $numeroOId);
        return $f === null ? null : $f['id'];
    }

    private static function _contribuyenteDeLaSesion($con)
    {
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }
        if (empty($_SESSION['id_usuario'])) { return null; }

        $fila = $con->obnerFila($con->consultar(
            "SELECT c.ind_Id
               FROM ind_contribuyentes c
               INNER JOIN conf_usuarios u ON u.usu_NumeroDocumento = c.ind_NumeroIdentificacion
              WHERE u.usu_Id = ?",
            [(int) $_SESSION['id_usuario']]
        ));

        return isset($fila['ind_Id']) ? (int) $fila['ind_Id'] : null;
    }

    /**
     * Punto UNICO de control de acceso del modulo de declaraciones.
     *
     * Antes ninguna de las funciones de este controlador miraba de quien era
     * la declaracion: bastaba tener sesion y cambiar dec_IdContribuyente o
     * dec_Id en la peticion para leer -y modificar- las declaraciones de otro
     * contribuyente. Comprobado con el usuario externo de prueba: pidiendo el
     * contribuyente 31 devolvia sus 13 declaraciones con ingresos e impuesto.
     * Sobre datos con reserva tributaria eso no es un detalle.
     *
     * Se resuelve aqui, en el despacho, y no funcion por funcion, porque son
     * mas de quince y cualquiera nueva heredaria el agujero. Para los roles de
     * Alcaldia (1 y 2) no cambia nada.
     *
     * Devuelve null si todo bien, o el mensaje de rechazo.
     */
    private static function _verificarAcceso()
    {
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }

        if (empty($_SESSION['id_usuario'])) {
            return 'Debe iniciar sesión.';
        }

        $rol = isset($_SESSION['id_Rol']) ? (int) $_SESSION['id_Rol'] : 0;
        if (in_array($rol, [1, 2], true)) { return null; }

        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
        $propio = self::_contribuyenteDeLaSesion($con);
        if (!$propio) {
            return 'No se pudo establecer a qué contribuyente corresponde la sesión.';
        }

        // El filtro por contribuyente se fija: se ignora el que venga.
        if (array_key_exists('dec_IdContribuyente', $_POST)) {
            $_POST['dec_IdContribuyente'] = $propio;
        }

        // Una declaracion concreta tiene que ser suya.
        if (!empty($_POST['dec_Id'])) {
            $fila = $con->obnerFila($con->consultar(
                "SELECT dec_Id FROM ind_declaraciones_ica
                  WHERE dec_Id = ? AND dec_IdContribuyente = ?",
                [(int) $_POST['dec_Id'], $propio]
            ));
            if (!$fila) { return 'No tiene permiso sobre esta declaración.'; }
        }

        /*
         * Las funciones 6 y 14 no identifican la declaracion por dec_Id sino
         * por dec_NumeroDeclaracion, que viaja como 'idDeclaracion'. Sin esta
         * comprobacion el filtro de arriba no las cubre: bastaba mandar el
         * numero de otro contribuyente para liquidar -o, con la 14, para LEER-
         * una declaracion ajena, que son datos con reserva tributaria.
         */
        if (!empty($_POST['idDeclaracion'])) {
            /*
             * Se acepta que venga el numero O el dec_Id.
             *
             * La pantalla manda el dec_Id, asi que comparar solo contra
             * dec_NumeroDeclaracion negaba el acceso al propio contribuyente en
             * cuanto los dos valores dejaron de coincidir (migracion 012). Se
             * comprueba contra los dos, y la propiedad se sigue exigiendo igual.
             */
            $fila = $con->obnerFila($con->consultar(
                "SELECT TOP 1 dec_Id FROM ind_declaraciones_ica
                  WHERE (dec_NumeroDeclaracion = ? OR dec_Id = ?)
                    AND dec_IdContribuyente = ?",
                [$_POST['idDeclaracion'], $_POST['idDeclaracion'], $propio]
            ));
            if (!$fila) { return 'No tiene permiso sobre esta declaración.'; }
        }

        // Un establecimiento concreto tambien.
        if (!empty($_POST['dec_IdEstablecimiento'])) {
            $fila = $con->obnerFila($con->consultar(
                "SELECT est_Id FROM ind_establecimientos
                  WHERE est_Id = ? AND est_IdContribuyente = ?",
                [(int) $_POST['dec_IdEstablecimiento'], $propio]
            ));
            if (!$fila) { return 'No tiene permiso sobre este establecimiento.'; }
        }

        return null;
    }

    public static function run()
    {
        $_obj = new self();
        $_obj->_funcion = isset($_POST['funcion']) ? $_POST['funcion'] : null;

        $negado = self::_verificarAcceso();
        if ($negado !== null) {
            header('Content-type: application/json');
            echo json_encode(["ok" => 0, "mensaje" => $negado, "datos" => []]);
            return;
        }

        try {

            $respuesta = null;

            switch ($_obj->_funcion) {

                case 1:
                    $respuesta = $_obj->_agregarDeclaracion();
                break;

                case 2:
                    $respuesta = $_obj->_editarDeclaracion();
                break;

                case 3:
                    $respuesta = $_obj->_consultarDeclaraciones();
                break;

                case 4:
                    $respuesta = $_obj->_inactivarDeclaracion();
                break;

                case 5:
                    $respuesta = $_obj->_consultarActividadesEstablecimiento();
                break;

                case 6:
                    $respuesta = $_obj->_insertarActividadesDeclaracionIca();
                break;

                case 7:
                    $respuesta = $_obj->_actualizarDeclaracionIca();
                break;

                case 8:
                    $respuesta = $_obj->_consultarDeclaracionesListado();
                break;

                case 9:
                    $respuesta = $_obj->_presentarDeclaracion();
                break;

                case 10:
                    $respuesta = $_obj->_revertirABorrador();
                break;

                case 11:
                    $respuesta = $_obj->_crearCorreccion();
                break;

                case 12:
                    $respuesta = $_obj->_consultarActividadesContribuyente();
                break;

                case 13:
                    $respuesta = $_obj->_consultarDeclaracionParaEditar();
                break;

                case 14:
                    $respuesta = $_obj->_liquidarSinGuardar();
                break;

                default:
                    throw new \erpsoftsas\DeclaracionesICAException("Función no válida",0);
            }

            header('Content-type: application/json');

            echo json_encode(array(
                "ok" => $_obj->_ok,
                "mensaje" => $_obj->_mensaje,
                "datos" => $respuesta
            ));

        } catch (\erpsoftsas\DeclaracionesICAException $e) {

            $arrRespu = array(
                "ok"=>$e->getCode(),
                "mensaje"=>"Error: ".$e->getMessage(),
                "datos"=>""
            );

            header('Content-type: application/json');
            echo json_encode($arrRespu);
        }
    }

    /**
     * Crea la declaracion del CONTRIBUYENTE para el periodo actual.
     *
     * La declaracion es una sola por contribuyente (no por establecimiento):
     * un contribuyente con 3 locales solo declara una vez. Como la pantalla
     * de "Presentar Declaración" sigue mostrando un boton "Crear Declaración"
     * en la fila de CADA establecimiento (son la misma persona vista desde
     * distintos locales), este metodo es idempotente: si ya existe una
     * declaracion borrador para este contribuyente y periodo, la devuelve en
     * vez de intentar crear otra -asi no importa desde cual establecimiento
     * se pulse el boton, todas abren la misma declaracion compartida-.
     *
     * dec_IdEstablecimiento ya no es obligatorio (ver migracion 2026-08); se
     * sigue registrando el que disparo la creacion solo como referencia de
     * auditoria, nunca como filtro de a quien pertenece la declaracion.
     */
    protected function _agregarDeclaracion()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $idContribuyente = $_POST['dec_IdContribuyente'] ?? null;

        if (!$idContribuyente) {
            $this->_ok = 0;
            $this->_mensaje = "Contribuyente requerido";
            return [];
        }

        $anio = (int) date('Y');
        $mes  = 12;

        // ¿Ya existe una declaracion (borrador o firmada, no presentada) de
        // este contribuyente para el periodo actual? De ser asi se reabre en
        // vez de duplicar -el indice unico de la BD lo impediria de todos
        // modos, pero preguntar antes evita depender de que falle el INSERT-.
        /*
         * Si ya hay una en curso se reabre ESA, y se reabre siempre LA MISMA.
         *
         * Faltaba el ORDER BY, y no es un detalle: sin el, SQL Server puede
         * devolver cualquiera de las filas que cumplen. En esta base hay
         * contribuyentes con 94 y 87 borradores del mismo periodo -restos de
         * pruebas, anteriores al indice de la migracion 020-, asi que "cual se
         * abre" era literalmente impredecible y podia cambiar entre dos clics.
         *
         * Se toma el mas reciente por dec_Id, que es lo unico que siempre crece.
         * Es el que tiene mas probabilidad de ser en el que se estaba trabajando.
         */
        $existente = $con->obnerFila($con->consultar(
            "SELECT TOP 1 * FROM ind_declaraciones_ica
             WHERE dec_IdContribuyente = ?
               AND dec_AnioDeclaracion = ?
               AND dec_MesDeclaracion = ?
               AND dec_DeclaracionCorrige IS NULL
               AND (dec_Estado IS NULL OR dec_Estado <> 2)
             ORDER BY dec_Id DESC",
            [$idContribuyente, $anio, $mes]
        ));

        if ($existente) {
            /*
             * Tambien aqui se cruza el anticipo del año anterior.
             *
             * El cruce estaba solo despues del INSERT, asi que solo corria la
             * PRIMERA vez. Si el contribuyente abrio el borrador de este año
             * antes de que se presentara el del año pasado -una declaracion
             * extemporanea, por ejemplo-, el renglon 29 se quedaba en cero
             * para siempre y habia que teclearlo a mano.
             *
             * Es seguro repetirlo: el UPDATE lleva "AND ISNULL(...) = 0", asi
             * que nunca pisa un valor ya diligenciado.
             */
            $this->_cruzarAnticipoDelAnioAnterior(
                $con,
                $existente['dec_Id'] ?? $existente['dec_NumeroDeclaracion'] ?? 0,
                $idContribuyente,
                $anio
            );

            /*
             * Y se DICE que se reabrio, con numero y fecha.
             *
             * El cliente reporto el 2026-08-29: "¿por que cuando creo una nueva
             * declaracion se coloca 183 en el numero?". Porque no era nueva: era
             * un borrador suyo del 17 de abril, con 10.000.000 de ingresos y
             * 500.000 de sancion ya dentro -la sancion que venia reportando como
             * si el sistema se la inventara-.
             *
             * Reabrir es lo correcto: el boton "Crear" sale en la fila de cada
             * establecimiento y son la misma persona, asi que crear otra seria
             * duplicar. Lo que faltaba era decirlo. Un formulario que se abre
             * con cifras que uno no escribio, y sin explicacion, se lee como
             * que el sistema calcula mal.
             */
            $fecha = $existente['dec_FechaDeclaracion'] ?? null;
            if ($fecha instanceof \DateTime) { $fecha = $fecha->format('d/m/Y'); }

            $this->_ok = 1;
            $this->_mensaje = 'Ya tenía una declaración en curso para este período: la N° '
                . ($existente['dec_NumeroDeclaracion'] ?: $existente['dec_Id'])
                . ($fecha ? ', creada el ' . $fecha : '')
                . '. Se abre esa, con lo que hubiera guardado. No se creó una nueva '
                . 'para no duplicar la declaración del período.';

            $existente['_reabierta'] = 1;

            return $existente;
        }

        // El indice unico de la BD (UQ_declaracion_contribuyente_periodo)
        // es por (contribuyente, año, periodo) y NO distingue el estado:
        // si ya hay una PRESENTADA para este periodo, el INSERT de abajo
        // choca con ella igual. Antes esto no se comprobaba aparte, asi que
        // el error de SQL (duplicate key) quedaba sin capturar, tronaba
        // como fatal de PHP, y el usuario recibia una respuesta vacia (500)
        // sin ningun mensaje -"aprieto el boton y no pasa nada"-.
        $yaPresentada = $con->obnerFila($con->consultar(
            "SELECT dec_Id, dec_NumeroDeclaracion FROM ind_declaraciones_ica
             WHERE dec_IdContribuyente = ?
               AND dec_AnioDeclaracion = ?
               AND dec_MesDeclaracion = ?
               AND dec_DeclaracionCorrige IS NULL
               AND dec_Estado = 2",
            [$idContribuyente, $anio, $mes]
        ));

        if ($yaPresentada) {
            $this->_ok = 0;
            $this->_mensaje = "La declaración de este período ya fue presentada (N° "
                . ($yaPresentada['dec_NumeroDeclaracion'] ?: $yaPresentada['dec_Id'])
                . "). Para modificarla, genere una declaración de corrección "
                . "desde Consultar Declaraciones.";
            return [];
        }

        $_obj = new \erpsoftsas\DAO_DeclaracionesICA();

        $_obj->set_dec_AnioDeclaracion($anio);
        $_obj->set_dec_MesDeclaracion($mes);

        // Referencia de auditoria de cual establecimiento disparo la
        // creacion; la declaracion en si pertenece al contribuyente.
        if (!empty($_POST['dec_IdEstablecimiento'])) {
            $_obj->set_dec_IdEstablecimiento($_POST['dec_IdEstablecimiento']);
        }
        $_obj->set_dec_IdContribuyente($idContribuyente);

        date_default_timezone_set('America/Bogota');
        $_obj->set_dec_FechaDeclaracion(date('Y-m-d'));
        $_obj->set_dec_HoraDeclaracion(date('H:i:s'));
        $_obj->set_dec_OpcionUso(1);

        $_obj->set_dec_Estado(1); // borrador

        /*
         * guardar() no siempre devuelve false cuando el INSERT falla: la capa
         * de conexion LANZA la excepcion, asi que el "if (!...)" de abajo ni
         * llega a evaluarse. Sin este try, cualquier rechazo de SQL -una
         * columna NOT NULL sin valor, una clave duplicada- sale como fatal de
         * PHP y el usuario recibe una respuesta vacia (500) sin ningun texto:
         * el sintoma de "aprieto el boton y no pasa nada" que ya reporto el
         * cliente en otras pantallas. Se atrapa Throwable, y no Exception,
         * para cubrir tambien los Error de PHP.
         */
        try {
            $guardo = $_obj->guardar();
        } catch (\Throwable $e) {
            error_log('[declaraciones] fallo al crear la declaracion: ' . $e->getMessage());
            $this->_ok = 0;
            $this->_mensaje = 'No se pudo crear la declaración. Intente de nuevo; '
                            . 'si persiste, avise a la Alcaldía indicando la hora.';
            return [];
        }

        if (!$guardo) {

            $this->_ok = 0;
            $this->_mensaje = $_obj->getMysqlError();

        } else {

            $id = $_obj->get_dec_Id();

            /*
             * Numero de formulario.
             *
             * Aqui decia "ACTUALIZAR NUMERO DECLARACION = ID" y hacia
             * literalmente eso: copiaba el IDENTITY de la fila. Por eso el
             * numero iba por 218 en vez del 2026000001 que usa el formulario
             * de la Alcaldia. Ahora lo reparte el generador atomico de la
             * migracion 012.
             *
             * Se sigue asignando AQUI, al crear el borrador, y no al
             * presentar: hay cuatro consultas del flujo de liquidacion que
             * localizan la fila por dec_NumeroDeclaracion, y con el numero en
             * NULL durante el borrador dejarian de encontrarla. Mover la
             * asignacion exige antes cambiarlas para que busquen por dec_Id.
             *
             * Si el generador no esta disponible se cae al id, que es el
             * comportamiento anterior: una base sin la migracion 012 sigue
             * creando declaraciones en vez de fallar.
             */
            $numero = $this->_siguienteNumeroDeclaracion($con, $anio);
            if ($numero === null) {
                $numero = $id;
                error_log('[declaraciones] sin consecutivo disponible; se usa el id ' . $id);
            }

            $con->consultar(
                "UPDATE ind_declaraciones_ica SET dec_NumeroDeclaracion = ? WHERE dec_Id = ?",
                [$numero, $id]
            );

            // El anticipo que liquido el año pasado entra ya diligenciado, en
            // vez de tener que copiarlo a mano de la declaracion anterior.
            $this->_cruzarAnticipoDelAnioAnterior($con, $id, $idContribuyente, $anio);

            $this->_ok = 1;
            $this->_mensaje = "Declaración creada correctamente, N° $numero";

            /*
             * Se devuelve la FILA, no el objeto.
             *
             * getArray() arma la respuesta con los atributos del objeto DAO, y
             * ahi el numero sigue en null: se escribe con el UPDATE de arriba y
             * nadie lo pone de vuelta en el objeto. Asi que al CREAR viajaba
             * dec_NumeroDeclaracion = null y la pantalla caia al dec_Id — que es
             * exactamente el "¿por que sale 183?" que reporto el cliente, y por
             * lo que el arreglo de la pantalla no bastaba por si solo.
             *
             * Releyendo la fila los dos caminos -crear y reabrir- devuelven la
             * misma forma de dato, con el numero de verdad y todos los renglones.
             */
            $fila = $con->obnerFila($con->consultar(
                "SELECT * FROM ind_declaraciones_ica WHERE dec_Id = ?", [$id]
            ));
            if ($fila) { return $fila; }
        }

        return $_obj->getArray();
    }


    protected function _editarDeclaracion()
    {

        $_obj = new \erpsoftsas\DAO_DeclaracionesICA();

        $_obj->set_dec_Id($_POST['dec_Id'] ?? null);

        foreach ($_POST as $campo => $valor) {

            $metodo = 'set_' . $campo;

            if(method_exists($_obj,$metodo)){
                $_obj->$metodo($valor);
            }

        }

        if (!$_obj->guardar()) {

            $this->_ok = 0;
            $this->_mensaje = $_obj->getMysqlError();

        } else {

            $id = $_obj->get_dec_Id();

            $this->_ok = 1;
            $this->_mensaje = "Declaración actualizada correctamente ID = $id";

        }

        return $_obj->getArray();

    }


    private function _consultarDeclaraciones()
    {

        $_obj = new \erpsoftsas\DAO_DeclaracionesICA();

        foreach ($_POST as $campo => $valor) {

            $metodo = 'set_' . $campo;

            if(method_exists($_obj,$metodo)){
                $_obj->$metodo($valor);
            }

        }

        $_obj->habilita1ResultadoEnArray();

        $arr = $_obj->consultar();

        if (is_array($arr) && count($arr)) {

            $R = [];

            foreach ($arr as $obj) {

                $R[] = $obj->getArray();

            }

            $this->_ok = 1;
            $this->_mensaje = "Declaraciones consultadas correctamente";

            return $R;

        } else {

            $this->_ok = 0;
            $this->_mensaje = "No existen declaraciones con los filtros seleccionados";

            return [];

        }

    }


    /**
     * funcion 4 - Descarta un BORRADOR.
     *
     * ESTE BOTON NUNCA HABIA FUNCIONADO
     *
     * Hacia set_dec_Activo(0), y la columna dec_Activo NO EXISTE: ni en el
     * esquema, ni en ninguna migracion, ni en el mapa del DAO. Aparecia una
     * sola vez en todo el proyecto, justo en esa linea.
     *
     * El __call del DAO descarta en silencio los setters que no reconoce, asi
     * que guardar() armaba la sentencia sin nada que asignar:
     *
     *     UPDATE ind_declaraciones_ica SET  WHERE dec_Id = 183
     *
     * que es un error de sintaxis. La excepcion no la atrapaba nadie -run()
     * solo captura DeclaracionesICAException, que es una SUBCLASE- asi que
     * salia un fatal de PHP, una respuesta sin JSON, y la pantalla decia
     * "Error de conexion". Siempre. Comprobado ejecutandolo.
     *
     * Importa mas de lo que parece: era la unica forma de deshacerse de un
     * borrador. Sin ella, quien tuviera uno viejo con datos quedaba atrapado
     * -el sistema se lo vuelve a abrir cada vez- y la unica salida era pedirle
     * a la Alcaldia que borrara la fila a mano.
     *
     * SE BORRA DE VERDAD, Y SOLO SI ES UN BORRADOR
     *
     * No hay columna de baja logica y no se inventa una: un borrador no es un
     * acto juridico -no existe hasta que se presenta-, asi que descartarlo es
     * borrarlo. Lo que si se hace es no dejar borrar nada que ya tenga valor
     * legal: presentada no, firmada no. Y se borran antes sus actividades,
     * que de otro modo quedarian sueltas.
     */
    protected function _inactivarDeclaracion()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $fila = self::_filaDeLaDeclaracion($con, $_POST['dec_Id'] ?? '');
        if ($fila === null) {
            $this->_ok = 0;
            $this->_mensaje = 'La declaración no existe.';
            return [];
        }
        $id = $fila['id'];

        /*
         * Las firmas se guardan por NUMERO (fd_NumeroDeclaracion), no por id.
         * Se comprueban contra los dos valores: durante un tiempo la pantalla
         * mando el id creyendo que era el numero, asi que puede haber firmas
         * anotadas de las dos formas y ninguna debe pasarse por alto.
         */
        $d = $con->obnerFila($con->consultar(
            "SELECT d.dec_Estado, d.dec_NumeroDeclaracion,
                    (SELECT COUNT(*) FROM firmas_declaraciones f
                      WHERE f.fd_NumeroDeclaracion IN (d.dec_NumeroDeclaracion, d.dec_Id)) firmas
               FROM ind_declaraciones_ica d WHERE d.dec_Id = ?",
            [$id]
        ));

        if ((int) ($d['dec_Estado'] ?? 0) === 2) {
            $this->_ok = 0;
            $this->_mensaje = 'Una declaración presentada no se puede borrar. '
                            . 'Si hay que corregirla, use "Corregir".';
            return [];
        }

        if ((int) ($d['firmas'] ?? 0) > 0) {
            $this->_ok = 0;
            $this->_mensaje = 'Esta declaración ya tiene firmas registradas y no se puede borrar. '
                            . 'Devuélvala a borrador si necesita cambiarla.';
            return [];
        }

        try {
            $con->consultar("DELETE FROM ind_declaraciones_ica_actividades WHERE dia_IdDeclaracion = ?", [$id]);
            $con->consultar("DELETE FROM ind_declaraciones_ica WHERE dec_Id = ?", [$id]);
        } catch (\Throwable $e) {
            error_log('[declaraciones] no se pudo borrar el borrador ' . $id . ': ' . $e->getMessage());
            $this->_ok = 0;
            $this->_mensaje = 'No se pudo borrar la declaración. Intente de nuevo; si persiste, avise a soporte.';
            return [];
        }

        /*
         * Si era el ULTIMO numero repartido, se devuelve al contador.
         *
         * El consecutivo se entrega al crear, asi que crear y descartar deja un
         * hueco en la serie. Con un borrador eso no aporta nada -no llego a ser
         * un documento, no se imprimio ni se pago- y ensucia la numeracion:
         * probar tres veces dejaba la serie en 4.
         *
         * Solo se devuelve cuando el numero descartado es el ultimo entregado.
         * Un hueco intermedio NO se reutiliza: si alguien creo una despues, su
         * numero ya esta en circulacion y bajar el contador lo haria chocar.
         *
         * Los numeros de declaraciones PRESENTADAS no pasan por aqui: mas
         * arriba se rechaza borrarlas.
         */
        $numero = $d['dec_NumeroDeclaracion'] ?? null;
        if ($numero !== null && strlen((string) $numero) >= 10) {
            try {
                $anio      = (int) substr((string) $numero, 0, 4);
                $secuencia = (int) substr((string) $numero, 4);

                $con->consultar(
                    "UPDATE ind_consecutivos
                        SET cse_Valor = cse_Valor - 1, cse_FechaActualizacion = GETDATE()
                      WHERE cse_Tipo = 'DECLARACION_ICA'
                        AND cse_Anio = ?
                        AND cse_Valor = ?",
                    [$anio, $secuencia]
                );
            } catch (\Throwable $e) {
                // Un hueco en la serie no es motivo para fallar el borrado.
                error_log('[declaraciones] no se pudo devolver el consecutivo: ' . $e->getMessage());
            }
        }

        // Queda constancia: es un borrado real.
        error_log(sprintf('[declaraciones] usuario %s borro el borrador %s (N° %s)',
            $_SESSION['id_usuario'] ?? '?', $id, $d['dec_NumeroDeclaracion'] ?: $id));

        $this->_ok = 1;
        $this->_mensaje = 'Borrador descartado. Puede crear la declaración de nuevo.';

        return ['dec_Id' => $id];
    }


    /**
     * Actividades economicas del CONTRIBUYENTE para armar la declaracion.
     *
     * Regla confirmada con la Secretaria de Hacienda: la base gravable se
     * agrupa por actividad (CIIU), sumando todos los establecimientos. Un
     * contribuyente con 3 restaurantes del mismo CIIU declara una sola vez
     * esa actividad, no tres. Por eso esta consulta trae las actividades
     * DISTINTAS de TODOS los establecimientos del contribuyente (DISTINCT
     * por acc_Id), no las de uno solo.
     *
     * La base gravable y la tarifa las sigue escribiendo la persona en la
     * pantalla (no se sabe cuanto factura cada establecimiento por
     * separado); esta consulta resuelve el "que actividades declarar",
     * no el "cuanto". n_establecimientos es informativo, para que se vea
     * en cuantos locales aplica cada actividad.
     */
    private function _consultarActividadesContribuyente(){

        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $idContribuyente = $_POST['dec_IdContribuyente'] ?? null;

        if (!$idContribuyente) {
            $this->_ok = 0;
            $this->_mensaje = "Contribuyente requerido";
            return [];
        }

        /*
 * Las actividades salen de ind_actividad_contribuyente, la tabla NUEVA.
 * Las migraciones 005 y 007 las subieron del establecimiento al
 * contribuyente y les quitaron el año.
 *
 * Esta consulta se habia quedado en la vieja
 * (ind_actividad_establecimiento), a la que ya nadie escribe: la pantalla
 * del RIT guarda en la nueva. Mientras nadie editara actividades las dos
 * coincidian -la migracion copio el contenido-, pero a la primera edicion
 * la declaracion habria seguido viendo la lista vieja. Se conservan los
 * nombres de columna con alias para no tocar el resto del flujo.
 */
$sql = "
            SELECT
                atc.atc_IdCodigoActividad AS ace_IdCodigoActividad,
                acc.acc_Codigo,
                acc.acc_Nombre,
                FORMAT(acc.acc_Tarifa,'0.000') AS acc_Tarifa,
                (SELECT COUNT(*) FROM ind_establecimientos e
                  WHERE e.est_IdContribuyente = atc.atc_IdContribuyente
                    AND e.est_Activo = 1) AS n_establecimientos
            FROM ind_actividad_contribuyente atc
            INNER JOIN ind_actividadescomercio acc
                ON acc.acc_Id = atc.atc_IdCodigoActividad
            WHERE atc.atc_IdContribuyente = ?
            ORDER BY acc.acc_Codigo
        ";

        $res = $con->consultar($sql, [$idContribuyente]);

        $actividades = [];
        while ($row = $con->obnerFila($res)) {
            $actividades[] = $row;
        }

        $this->_ok = count($actividades) ? 1 : 0;
        $this->_mensaje = $actividades
            ? "Actividades cargadas"
            : "El contribuyente no tiene actividades económicas registradas en ningún establecimiento";

        return $actividades;
    }


    /**
     * Datos de una declaracion YA CREADA, para abrir el formulario de
     * liquidacion en modo edicion: la declaracion misma (totales) y las
     * actividades tal como quedaron guardadas la ultima vez (con su base,
     * tarifa e impuesto reales) -a diferencia de
     * _consultarActividadesContribuyente(), que trae la lista agregada
     * DESDE CERO (bases en 0) para cuando se está creando una declaracion
     * nueva.
     *
     * "Editar" (el lapiz sobre una declaracion en borrador) estaba sin
     * implementar: mostraba un aviso de "disponible próximamente" tanto en
     * Presentar Declaración como en Consultar Declaraciones. No habia
     * ninguna forma de modificar una declaracion ya creada.
     */
    private function _consultarDeclaracionParaEditar(){

        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $idDeclaracion = $_POST['dec_Id'] ?? null;

        if (!$idDeclaracion) {
            $this->_ok = 0;
            $this->_mensaje = "Id de declaración requerido";
            return [];
        }

        $declaracion = $con->obnerFila($con->consultar(
            "SELECT * FROM ind_declaraciones_ica WHERE dec_Id = ?",
            [$idDeclaracion]
        ));

        if (!$declaracion) {
            $this->_ok = 0;
            $this->_mensaje = "La declaración no existe";
            return [];
        }

        // Solo tiene sentido editar un borrador: una declaracion firmada o
        // presentada sigue otro camino (editarFirmada la devuelve a
        // borrador primero; presentada solo se corrige, no se edita).
        if ((int) $declaracion['dec_Estado'] === 2) {
            $this->_ok = 0;
            $this->_mensaje = "Una declaración presentada no se puede editar. Genere una corrección.";
            return [];
        }

        $sqlAct = "
            SELECT
                da.dia_IdActividad,
                da.dia_BaseGravable,
                da.dia_Tarifa,
                da.dia_ValorImpuesto,
                ca.acc_Codigo,
                ca.acc_Nombre
            FROM ind_declaraciones_ica_actividades da
            INNER JOIN ind_actividadescomercio ca
                ON ca.acc_Id = da.dia_IdActividad
            WHERE da.dia_IdDeclaracion = ?
            ORDER BY ca.acc_Codigo
        ";

        $res = $con->consultar($sqlAct, [$idDeclaracion]);

        $actividades = [];
        while ($row = $con->obnerFila($res)) {
            $actividades[] = $row;
        }

        $this->_ok = 1;
        $this->_mensaje = "Declaración cargada para edición";

        return [
            'declaracion' => $declaracion,
            'actividades' => $actividades
        ];
    }


    private function _consultarActividadesEstablecimiento(){

    $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

    /*
 * Las actividades salen de ind_actividad_contribuyente, la tabla NUEVA.
 * Las migraciones 005 y 007 las subieron del establecimiento al
 * contribuyente y les quitaron el año.
 *
 * Esta consulta se habia quedado en la vieja
 * (ind_actividad_establecimiento), a la que ya nadie escribe: la pantalla
 * del RIT guarda en la nueva. Mientras nadie editara actividades las dos
 * coincidian -la migracion copio el contenido-, pero a la primera edicion
 * la declaracion habria seguido viendo la lista vieja. Se conservan los
 * nombres de columna con alias para no tocar el resto del flujo.
 */
$sql = "
        SELECT
            atc.atc_IdCodigoActividad AS ace_IdCodigoActividad,
            atc.atc_Anio              AS ace_Anio,
            acc.acc_Codigo,
            acc.acc_Nombre,
            FORMAT(acc.acc_Tarifa,'0.000') AS acc_Tarifa
        FROM ind_actividad_contribuyente atc
        INNER JOIN ind_establecimientos e
            ON e.est_IdContribuyente = atc.atc_IdContribuyente
        INNER JOIN ind_actividadescomercio acc
            ON acc.acc_Id = atc.atc_IdCodigoActividad
        WHERE e.est_Id = ?
    ";

    $res = $con->consultar($sql,[ $_POST['est_Id'] ]);

    $actividades = [];

    while($row = $con->obnerFila($res)){
        $actividades[] = $row;
    }

    $this->_ok = count($actividades) ? 1 : 0;
    $this->_mensaje = "Actividades cargadas";

    return $actividades;
}



private function _insertarActividadesDeclaracionIca(){

    $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

    $actividades = json_decode($_POST['actividades'], true);
    $idDeclaracion = $_POST['idDeclaracion'];
    $totales = json_decode($_POST['totales'], true);
    
    if(!$idDeclaracion){
        $this->_ok = 0;
        $this->_mensaje = "Id de declaración requerido";
        return [];
    }

    try{

    // ==========================
        // 1. ACTUALIZAR DECLARACIÓN
        // ==========================
        $sqlUpdate = "
        UPDATE ind_declaraciones_ica SET
            dec_TotalIngresos = ?,
            dec_IngresosFueraMunicipio = ?,
            dec_IngresosDevoluciones = ?,
            dec_IngresosExportaciones = ?,
            dec_IngresosVentas = ?,
            dec_IngresosActividades = ?,
            dec_IngresosOtrasActividades = ?,
            dec_BaseGravable = ?,

            -- El JS de Liquidar no manda estos dos campos. Antes se leian
            -- igual y eso hacia DOS cosas malas: (1) en PHP 8 lanzaba un
            -- Warning de Undefined array key que en produccion
            -- (display_errors on) se imprimia ANTES del JSON y rompia el
            -- parseo, dejando el boton Liquidar sin hacer nada; (2) grababa
            -- NULL encima del valor que ya tuviera la declaracion.
            -- COALESCE conserva el valor actual si no viene.
            dec_CapacidadInstalada = COALESCE(?, dec_CapacidadInstalada),
            dec_ValorImpuesto      = COALESCE(?, dec_ValorImpuesto)
        WHERE dec_Id = ?
        ";

        $con->consultar($sqlUpdate, [
            $totales['dec_TotalIngresos']            ?? 0,
            $totales['dec_IngresosFueraMunicipio']   ?? 0,
            $totales['dec_IngresosDevoluciones']     ?? 0,
            $totales['dec_IngresosExportaciones']    ?? 0,
            $totales['dec_IngresosVentas']           ?? 0,
            $totales['dec_IngresosActividades']      ?? 0,
            $totales['dec_IngresosOtrasActividades'] ?? 0,
            $totales['dec_BaseGravable']             ?? 0,
            $totales['dec_CapacidadInstalada']       ?? null,
            $totales['dec_ValorImpuesto']            ?? null,
            $idFila
        ]);


        /*
         * Las actividades se enganchan por dec_Id, no por el numero.
         * Ver _idDeLaDeclaracion(): el numero dejo de coincidir con el id en la
         * migracion 012, y guardarlas por el numero las dejaba invisibles para
         * el procedimiento de liquidacion y para los PDF.
         */
        /*
         * TODO se resuelve desde la FILA, no desde lo que manda la pantalla.
         *
         * La pantalla pone $("#numDeclaracion").val(d.dec_Id) y desde ahi manda
         * ese valor como "idDeclaracion" Y como "numero". Mientras el numero fue
         * el identity de la fila daba igual. Desde la migracion 012 no lo es, y
         * entonces los UPDATE con "WHERE dec_NumeroDeclaracion = <id>" no tocaban
         * ninguna fila -sin error: un UPDATE de cero filas no falla- y
         * sp_calculo_comercio, que filtra por numero, no encontraba la
         * declaracion y no calculaba nada.
         *
         * Comprobado: mandando el dec_Id no se guardaba ni un renglon manual.
         */
        $fila = self::_filaDeLaDeclaracion($con, $idDeclaracion);
        if ($fila === null) {
            $this->_ok = 0;
            $this->_mensaje = "No se encontró la declaración " . $idDeclaracion;
            return [];
        }
        $idFila = $fila['id'];

        // ELIMINAR ACTIVIDADES EXISTENTES
        $sqlDelete = "DELETE FROM ind_declaraciones_ica_actividades 
                      WHERE dia_IdDeclaracion = ?";
        $con->consultar($sqlDelete, [$idFila]);

        // INSERTAR NUEVAS
        foreach($actividades as $a){

            $sqlInsert = "
                INSERT INTO ind_declaraciones_ica_actividades
                (
                    dia_IdDeclaracion,
                    dia_IdActividad,
                    dia_BaseGravable,
                    dia_Tarifa,
                    dia_ValorImpuesto,
                    dia_Activo,
                    dia_FechaCreador
                )
                VALUES (?,?,?,?,?,1,GETDATE())
            ";

            // Se ignora el dia_IdDeclaracion que manda el navegador: lleva el
            // numero, y aqui tiene que ir el id de la fila.
            $con->consultar($sqlInsert, [
                $idFila,
                $a['dia_IdActividad'],
                $a['dia_BaseGravable'],
                $a['dia_Tarifa'],
                $a['dia_ValorImpuesto']
            ]);
        }

        
        // El procedimiento filtra por numero, año y mes: salen de la FILA, no
        // del POST, que trae el id disfrazado de numero.
        $this->_ejecutarSpLiquidacion($fila['anio'], $fila['mes'], $fila['numero'], 0);

        // ==========================
        // 5. CONSULTAR RESULTADO FINAL
        // ==========================
        $res = $con->consultar("
            SELECT *
            FROM ind_declaraciones_ica
            WHERE dec_Id = ?
        ", [$idFila]);

        $data = $con->obnerFila($res);

        $this->_ok = 1;
        $this->_mensaje = "Liquidación completa";

        return $data;

    }catch(\Exception $e){

        $this->_ok = 0;
        $this->_mensaje = $e->getMessage();

        return [];
    }

}


/**
 * funcion 14 - Liquida SIN guardar. Solo para ver las cifras en pantalla.
 *
 * El cliente pidio el 2026-08-26 que volviera el boton "Liquidar" de antes:
 * "el boton de liquidar como el pasado, no guardarlo sino como estaba previo".
 *
 * POR QUE NO SE CALCULA EN EL NAVEGADOR
 *
 * Seria lo obvio y seria un error. Las formulas de los renglones bloqueados no
 * estan en el codigo: viven en la columna con_Observaciones de ind_Conceptos y
 * sp_calculo_comercio las inyecta como texto en un UPDATE. Reescribirlas en
 * JavaScript significaria tener DOS liquidadores -uno en el navegador y otro en
 * la base- que hay que acordarse de cambiar a la vez. El dia que alguien ajuste
 * una tarifa en la tabla, la pantalla mostraria una cifra y el PDF otra, y el
 * contribuyente firmaria la que no vio.
 *
 * COMO SE HACE ENTONCES
 *
 * Se ejecuta la liquidacion DE VERDAD, con el procedimiento de siempre, dentro
 * de una transaccion que SIEMPRE se deshace. Se leen los resultados antes del
 * rollback y se devuelven. La base queda exactamente como estaba -incluidas las
 * actividades, que la funcion 6 borra y reinserta- y las cifras que ve el
 * contribuyente son las mismas que va a guardar si pulsa "Guardar y liquidar".
 *
 * Una sola fuente de calculo, y nada escrito. Que es lo que se pidio.
 *
 * El rollback va en finally: si el SP revienta a mitad, la transaccion tiene
 * que cerrarse igual o la conexion queda con una transaccion abierta y la
 * siguiente peticion que la reuse hereda el desastre.
 */
private function _liquidarSinGuardar()
{
    $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

    $actividades   = json_decode($_POST['actividades'] ?? '[]', true) ?: [];
    $totales       = json_decode($_POST['totales'] ?? '[]', true) ?: [];
    $idDeclaracion = $_POST['idDeclaracion'] ?? null;

    if (!$idDeclaracion) {
        $this->_ok = 0;
        $this->_mensaje = "Id de declaración requerido";
        return [];
    }

    $abierta = false;

    try {
        $con->begin();
        $abierta = true;

        $con->consultar(
            "UPDATE ind_declaraciones_ica SET
                dec_TotalIngresos            = ?,
                dec_IngresosFueraMunicipio   = ?,
                dec_IngresosDevoluciones     = ?,
                dec_IngresosExportaciones    = ?,
                dec_IngresosVentas           = ?,
                dec_IngresosActividades      = ?,
                dec_IngresosOtrasActividades = ?,
                dec_BaseGravable             = ?,
                dec_CapacidadInstalada       = COALESCE(?, dec_CapacidadInstalada),
                dec_ValorImpuesto            = COALESCE(?, dec_ValorImpuesto)
              WHERE dec_Id = ?",
            [
                $totales['dec_TotalIngresos']            ?? 0,
                $totales['dec_IngresosFueraMunicipio']   ?? 0,
                $totales['dec_IngresosDevoluciones']     ?? 0,
                $totales['dec_IngresosExportaciones']    ?? 0,
                $totales['dec_IngresosVentas']           ?? 0,
                $totales['dec_IngresosActividades']      ?? 0,
                $totales['dec_IngresosOtrasActividades'] ?? 0,
                $totales['dec_BaseGravable']             ?? 0,
                $totales['dec_CapacidadInstalada']       ?? null,
                $totales['dec_ValorImpuesto']            ?? null,
                $idFila,
            ]
        );

        // Mismo criterio que la funcion 6: manda la fila, no lo que llega.
        $fila = self::_filaDeLaDeclaracion($con, $idDeclaracion);
        $idFila = $fila === null ? null : $fila['id'];
        if ($idFila === null) {
            throw new \Exception('No se encontró la declaración ' . $idDeclaracion);
        }

        $con->consultar("DELETE FROM ind_declaraciones_ica_actividades
                          WHERE dia_IdDeclaracion = ?", [$idFila]);

        foreach ($actividades as $a) {
            $con->consultar(
                "INSERT INTO ind_declaraciones_ica_actividades
                     (dia_IdDeclaracion, dia_IdActividad, dia_BaseGravable,
                      dia_Tarifa, dia_ValorImpuesto, dia_Activo, dia_FechaCreador)
                 VALUES (?,?,?,?,?,1,GETDATE())",
                [
                    $idFila,
                    $a['dia_IdActividad'],
                    $a['dia_BaseGravable'],
                    $a['dia_Tarifa'],
                    $a['dia_ValorImpuesto'],
                ]
            );
        }

        $this->_ejecutarSpLiquidacion($fila['anio'], $fila['mes'], $fila['numero'], 0);

        // Se lee ANTES de deshacer: despues del rollback la fila vuelve a
        // tener los valores viejos.
        $datos = $con->obnerFila($con->consultar(
            "SELECT * FROM ind_declaraciones_ica WHERE dec_Id = ?",
            [$idFila]
        ));

        $this->_ok = 1;
        $this->_mensaje = "Liquidación calculada (no se guardó nada)";

        return $datos ?: [];

    } catch (\Exception $e) {

        $this->_ok = 0;
        $this->_mensaje = $e->getMessage();
        return [];

    } finally {
        if ($abierta) {
            // Nunca hay commit: el proposito del metodo es no dejar rastro.
            try { $con->rollback(); } catch (\Exception $e) { /* ya cerrada */ }
        }
    }
}


private function _ejecutarSpLiquidacion($anio,$mes,$numero, $campoSeleccionado){

    $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

    try{

        $sql = "EXEC sp_calculo_comercio ?, ?, ?, ?";
        $con->consultar($sql, [$anio, $mes, $numero,$campoSeleccionado]);

        $this->_ok = 1;
        $this->_mensaje = "SP ejecutado correctamente";

        return [];

    }catch(\Exception $e){

        $this->_ok = 0;
        $this->_mensaje = $e->getMessage();

        return [];
    }

}


private function _actualizarDeclaracionIca(){

    $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

    $idDeclaracion = $_POST['idDeclaracion'];
    $valorLimpio = $_POST['valorLimpio'];

    /*
     * EL NUMERO DE RENGLON SE VALIDA. AQUI HABIA UNA INYECCION SQL.
     *
     * El nombre de la columna se armaba concatenando lo que mandara el
     * navegador -'dec_ValorConcepto' . $_POST['campoSeleccionado']- y se metia
     * tal cual en el UPDATE. Un nombre de columna no se puede parametrizar, asi
     * que lo que llegaba ahi era SQL.
     *
     * Comprobado en vivo el 2026-08-29: mandando
     *
     *     campoSeleccionado = 10 = 0, dec_Estado = 2, dec_ValorConcepto11
     *
     * la sentencia quedaba con "dec_Estado = 2" dentro y la declaracion pasaba
     * a PRESENTADA sin firma, sin OTP y sin pasar por _presentarDeclaracion.
     * Cualquiera con una sesion podia hacerlo sobre su propia declaracion; y
     * por la misma via se podia escribir en cualquier columna de la tabla.
     *
     * La defensa no es escapar: es no dejar que el nombre venga de fuera. Se
     * castea a entero y se comprueba contra las columnas que existen de verdad,
     * de modo que lo que se concatena es SIEMPRE un numero que salio de una
     * lista blanca.
     */
    $crudo = trim((string) ($_POST['campoSeleccionado'] ?? ''));
    $n     = ctype_digit($crudo) ? (int) $crudo : 0;

    // Se exige que sean SOLO digitos, no que empiecen por un digito: "1;DROP
    // TABLE x" se castearia a 1 y pasaria. El casteo ya lo dejaba inofensivo
    // -lo que se concatena es el entero-, pero aceptar basura y trabajar con
    // ella oculta al que la manda. Se rechaza y queda en el log.
    if ($n <= 0 || !in_array($n, self::_renglonesValidos($con), true)) {
        $this->_ok = 0;
        $this->_mensaje = 'Renglón no válido.';
        error_log(sprintf('[declaraciones] renglon rechazado: %s (usuario %s)',
            var_export($_POST['campoSeleccionado'] ?? null, true), $_SESSION['id_usuario'] ?? '?'));
        return [];
    }

    $campoSeleccionado = $n;
    $NombreCampo = 'dec_ValorConcepto' . $n;

    if(!$idDeclaracion){
        $this->_ok = 0;
        $this->_mensaje = "Id de declaración requerido";
        return [];
    }

    try{
 
     // ==========================
        // 1. ACTUALIZAR DECLARACIÓN
        // ==========================
        // Misma correccion que en la funcion 6: manda la fila.
        $fila = self::_filaDeLaDeclaracion($con, $idDeclaracion);
        if ($fila === null) {
            $this->_ok = 0;
            $this->_mensaje = "No se encontró la declaración " . $idDeclaracion;
            return [];
        }
        $idFila = $fila['id'];

        $sqlUpdate = "
        UPDATE ind_declaraciones_ica SET
            ".$NombreCampo." = ?
        WHERE dec_Id = ?
        ";

        $con->consultar($sqlUpdate, [
            $valorLimpio,
            $idFila
        ]);


        $this->_ejecutarSpLiquidacion($fila['anio'], $fila['mes'], $fila['numero'], $campoSeleccionado);

        // ==========================
        // 5. CONSULTAR RESULTADO FINAL
        // ==========================
        $res = $con->consultar("
            SELECT *
            FROM ind_declaraciones_ica
            WHERE dec_Id = ?
        ", [$idFila]);

        $data = $con->obnerFila($res);

        $this->_ok = 1;
        $this->_mensaje = "Liquidación completa";

        return $data;

    }catch(\Exception $e){

        $this->_ok = 0;
        $this->_mensaje = $e->getMessage();

        return [];
    }

}

/**
 * ¿Este contribuyente necesita contador/revisor fiscal para presentar?
 *
 * Regla vigente desde 2026-08-11 (reemplaza la anterior, basada en tipo de
 * persona + umbral de 3.500 UVT -esa murió por instrucción explícita del
 * cliente-): si el contribuyente tiene registrado un correo de contador O
 * de revisor fiscal, la firma de esa persona es OBLIGATORIA para presentar,
 * sin importar tipo de persona ni ingresos. Registrar los datos de un
 * contador/revisor es, en la práctica, decir "esta declaración la tiene que
 * firmar también mi contador".
 *
 * Contador y revisor comparten una sola casilla de firma (ver
 * ind_EmailContador/ind_EmailRevisor en ind_contribuyentes), así que basta
 * con que UNA de las dos personas firme -no hace falta distinguir aquí cuál
 * de las dos hace falta, con is_signed_contador alcanza-.
 */
private function _requiereContador($idContribuyente)
{
    $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

    $contrib = $con->obnerFila($con->consultar(
        "SELECT ind_EmailContador, ind_EmailRevisor FROM ind_contribuyentes WHERE ind_Id = ?",
        [$idContribuyente]
    ));

    if (!$contrib) {
        return false;
    }

    return trim((string) ($contrib['ind_EmailContador'] ?? '')) !== ''
        || trim((string) ($contrib['ind_EmailRevisor'] ?? '')) !== '';
}


/**
 * Marca una declaracion como presentada. Antes esto lo simulaba
 * enteramente el frontend (un swal de "exito" sin llamar a nada); el
 * usuario podia creer que presento su declaracion sin que quedara
 * ningun registro real. Solo se permite presentar una declaracion que
 * ya este firmada (existe registro en firmas_declaraciones).
 */
private function _presentarDeclaracion(){

    $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

    $idDeclaracion = $_POST['dec_Id'] ?? null;

    if (!$idDeclaracion) {
        $this->_ok = 0;
        $this->_mensaje = "Id de declaración requerido";
        return [];
    }

    $decl = $con->obnerFila($con->consultar(
        "SELECT dec_IdContribuyente FROM ind_declaraciones_ica WHERE dec_Id = ?",
        [$idDeclaracion]
    ));

    if (!$decl) {
        $this->_ok = 0;
        $this->_mensaje = "La declaración no existe";
        return [];
    }

    $firmas = $con->consultar(
        "SELECT fd_Rol FROM firmas_declaraciones WHERE fd_NumeroDeclaracion = ?",
        [$idDeclaracion]
    );

    $roles = [];
    while ($f = $con->obnerFila($firmas)) {
        $roles[] = $f['fd_Rol'];
    }

    if (!in_array('declarante', $roles, true)) {
        $this->_ok = 0;
        $this->_mensaje = "La declaración debe estar firmada antes de presentarla";
        return [];
    }

    // La firma del contador/revisor es obligatoria solo si el contribuyente
    // tiene uno registrado (ver _requiereContador). Quien no registro
    // contador ni revisor presenta con su sola firma.
    $requiereContador = $this->_requiereContador($decl['dec_IdContribuyente']);

    if ($requiereContador && !in_array('contador', $roles, true)) {
        $this->_ok = 0;
        $this->_mensaje = "Falta la firma del contador o revisor fiscal. "
                        . "Es obligatoria para este contribuyente.";
        // Codigo estable para que el frontend distinga ESTE motivo de
        // rechazo de cualquier otro error, y en vez de mostrarlo como un
        // error suelto, encadene el flujo de OTP del contador y reintente
        // presentar solo -asi "Presentar" es un unico click de principio a
        // fin, sin que la persona tenga que notar y pulsar un boton
        // intermedio aparte-.
        return ['codigo' => 'FALTA_CONTADOR'];
    }

    $con->consultar(
        "UPDATE ind_declaraciones_ica
         SET dec_Estado = 2, dec_FechaPresentacion = GETDATE()
         WHERE dec_Id = ?",
        [$idDeclaracion]
    );

    $res = $con->consultar(
        "SELECT dec_Id, dec_Estado, dec_FechaPresentacion FROM ind_declaraciones_ica WHERE dec_Id = ?",
        [$idDeclaracion]
    );

    $this->_ok = 1;
    $this->_mensaje = "Declaración presentada correctamente";

    return $con->obnerFila($res);
}


/**
 * Devuelve una declaracion FIRMADA al estado borrador.
 *
 * Regla del cliente: si una declaracion ya firmada se edita, la firma deja
 * de ser valida -acredita un contenido que va a cambiar-, asi que se borra
 * y la declaracion vuelve a borrador. Es lo que permite el boton "Editar
 * borrador" sobre una firmada.
 *
 * Una declaracion ya PRESENTADA no se puede devolver por aqui: eso ya es un
 * acto ante el municipio y se corrige con _crearCorreccion(), que deja
 * rastro de la original.
 */
private function _revertirABorrador(){

    $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

    $idDeclaracion = $_POST['dec_Id'] ?? null;

    if (!$idDeclaracion) {
        $this->_ok = 0;
        $this->_mensaje = "Id de declaración requerido";
        return [];
    }

    $stmt = $con->consultar(
        "SELECT dec_Id, dec_Estado FROM ind_declaraciones_ica WHERE dec_Id = ?",
        [$idDeclaracion]
    );
    $decl = $con->obnerFila($stmt);

    if (!$decl) {
        $this->_ok = 0;
        $this->_mensaje = "La declaración no existe";
        return [];
    }

    if ((int)$decl['dec_Estado'] === 2) {
        $this->_ok = 0;
        $this->_mensaje = "Una declaración ya presentada no se puede editar. "
                        . "Debe generar una declaración de corrección.";
        return [];
    }

    // Se borran TODAS las firmas de la declaracion (declarante y, cuando
    // exista, contador/revisor): si el contenido cambia, ninguna sigue
    // acreditando lo que se firmo.
    $con->consultar(
        "DELETE FROM firmas_declaraciones WHERE fd_NumeroDeclaracion = ?",
        [$idDeclaracion]
    );

    $con->consultar(
        "UPDATE ind_declaraciones_ica
         SET dec_Estado = NULL, dec_FechaPresentacion = NULL
         WHERE dec_Id = ?",
        [$idDeclaracion]
    );

    $this->_ok = 1;
    $this->_mensaje = "La declaración volvió a borrador y se eliminó la firma";

    return ['dec_Id' => $idDeclaracion];
}


/**
 * Crea una DECLARACION DE CORRECCION a partir de una ya presentada.
 *
 * Copia todos los datos de la original (renglones, ingresos, actividades) a
 * una fila nueva y la enlaza con dec_DeclaracionCorrige, que es el campo que
 * el Formulario Unico Nacional imprime en "No. DE DECLARACION A CORREGIR".
 * La original NO se toca: queda como el acto que efectivamente se presento.
 *
 * La nueva nace sin firma y sin presentar, o sea en borrador, para que el
 * contribuyente ajuste lo que deba y la vuelva a firmar y presentar.
 */
private function _crearCorreccion(){

    $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

    $idDeclaracion = $_POST['dec_Id'] ?? null;

    if (!$idDeclaracion) {
        $this->_ok = 0;
        $this->_mensaje = "Id de declaración requerido";
        return [];
    }

    $stmt = $con->consultar(
        "SELECT * FROM ind_declaraciones_ica WHERE dec_Id = ?",
        [$idDeclaracion]
    );
    $orig = $con->obnerFila($stmt);

    if (!$orig) {
        $this->_ok = 0;
        $this->_mensaje = "La declaración no existe";
        return [];
    }

    if ((int)$orig['dec_Estado'] !== 2) {
        $this->_ok = 0;
        $this->_mensaje = "Solo se puede corregir una declaración ya presentada";
        return [];
    }

    // Se copian todas las columnas menos las que deben nacer de cero:
    // el id (identity), el numero de formulario, el estado/fechas de
    // presentacion y pago, y el enlace de correccion (que se fija abajo).
    $excluidas = [
        'dec_Id', 'dec_NumeroDeclaracion', 'dec_Estado', 'dec_FechaPresentacion',
        'dec_DeclaracionCorrige', 'dec_Pagado', 'dec_FechaPago', 'dec_ValorPago',
        'dec_BancoPago', 'dec_FechaRealPago', 'dec_RutaDeclaracion', 'dec_RutaPago',
        // dec_AnioPago faltaba en esta lista: una correccion heredaba el año de
        // pago de la declaracion corregida, quedando con año de pago sin estar
        // pagada. Hasta ahora no se notaba porque nadie llenaba esa columna.
        'dec_AnioPago',
        'dec_FechaCreador', 'dec_FechaModificador', 'dec_Modificador'
    ];

    $columnas = [];
    $valores  = [];

    foreach ($orig as $col => $val) {
        if (in_array($col, $excluidas, true)) { continue; }
        if ($val instanceof \DateTime) { $val = $val->format('Y-m-d H:i:s'); }
        $columnas[] = $col;
        $valores[]  = $val;
    }

    // dec_DeclaracionCorrige guarda el NUMERO de la declaracion corregida,
    // que es lo que pide el formulario (no el id interno).
    $columnas[] = 'dec_DeclaracionCorrige';
    $valores[]  = $orig['dec_NumeroDeclaracion'] ?: $orig['dec_Id'];

    $columnas[] = 'dec_FechaCreador';
    $valores[]  = date('Y-m-d H:i:s');

    $listaCols = implode(', ', $columnas);
    $marcas    = implode(', ', array_fill(0, count($columnas), '?'));

    $con->consultar(
        "INSERT INTO ind_declaraciones_ica ($listaCols) VALUES ($marcas)",
        $valores
    );

    $nuevo = $con->obnerFila($con->consultar(
        "SELECT TOP 1 dec_Id FROM ind_declaraciones_ica
         WHERE dec_DeclaracionCorrige = ? ORDER BY dec_Id DESC",
        [$orig['dec_NumeroDeclaracion'] ?: $orig['dec_Id']]
    ));

    $idNuevo = $nuevo['dec_Id'] ?? null;

    /*
     * El numero de formulario de la correccion.
     *
     * Una correccion es una declaracion NUEVA -ligada a la original por
     * dec_DeclaracionCorrige-, asi que le toca su propio consecutivo, no una
     * copia del id. Mismo generador atomico que el flujo de creacion
     * (migracion 012), con la misma caida al id si no esta disponible.
     */
    if ($idNuevo) {
        $anioCorreccion = (int) ($orig['dec_AnioDeclaracion'] ?? date('Y'));
        $numeroNuevo = $this->_siguienteNumeroDeclaracion($con, $anioCorreccion);
        if ($numeroNuevo === null) {
            $numeroNuevo = $idNuevo;
            error_log('[declaraciones] correccion sin consecutivo; se usa el id ' . $idNuevo);
        }

        $con->consultar(
            "UPDATE ind_declaraciones_ica SET dec_NumeroDeclaracion = ? WHERE dec_Id = ?",
            [$numeroNuevo, $idNuevo]
        );

        // Se replican las actividades gravadas de la original.
        $con->consultar(
            "INSERT INTO ind_declaraciones_ica_actividades
                (dia_IdDeclaracion, dia_IdActividad, dia_BaseGravable,
                 dia_Tarifa, dia_ValorImpuesto, dia_Activo, dia_FechaCreador)
             SELECT ?, dia_IdActividad, dia_BaseGravable,
                    dia_Tarifa, dia_ValorImpuesto, dia_Activo, GETDATE()
             FROM ind_declaraciones_ica_actividades
             WHERE dia_IdDeclaracion = ?",
            [$idNuevo, $idDeclaracion]
        );
    }

    $this->_ok = 1;
    $this->_mensaje = "Declaración de corrección creada. "
                    . "Corrige la N° " . ($orig['dec_NumeroDeclaracion'] ?: $orig['dec_Id']);

    return [
        'dec_Id'                 => $idNuevo,
        'dec_DeclaracionCorrige' => $orig['dec_NumeroDeclaracion'] ?: $orig['dec_Id']
    ];
}


private function _consultarDeclaracionesListado(){

    $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

    // Se devuelven las dos firmas por separado: presentar exige la del
    // declarante Y, cuando aplica, la del contador/revisor fiscal (ver
    // _requiereContador: no es obligatoria para todo el mundo). El
    // frontend necesita distinguirlas para saber que boton ofrecer.
    // Tambien viaja si el contribuyente tiene a quien enviarle el codigo.
    $sql = "
        SELECT d.*,
               CASE WHEN fd.fd_Id IS NOT NULL THEN 1 ELSE 0 END AS is_signed,
               CASE WHEN fc.fd_Id IS NOT NULL THEN 1 ELSE 0 END AS is_signed_contador,
               CASE WHEN LTRIM(RTRIM(ISNULL(c.ind_EmailContador,''))) <> ''
                      OR LTRIM(RTRIM(ISNULL(c.ind_EmailRevisor,'')))  <> ''
                    THEN 1 ELSE 0 END AS tiene_correo_contador,
               c.ind_Persona
        FROM ind_declaraciones_ica d
        LEFT JOIN firmas_declaraciones fd
               ON fd.fd_NumeroDeclaracion = CAST(d.dec_Id AS VARCHAR)
              AND fd.fd_Rol = 'declarante'
        LEFT JOIN firmas_declaraciones fc
               ON fc.fd_NumeroDeclaracion = CAST(d.dec_Id AS VARCHAR)
              AND fc.fd_Rol = 'contador'
        LEFT JOIN ind_contribuyentes c
               ON c.ind_Id = d.dec_IdContribuyente
        WHERE " . (!empty($_POST['dec_IdContribuyente'])
                    ? "d.dec_IdContribuyente = ?"
                    : "d.dec_IdEstablecimiento = ?") . "
        ORDER BY d.dec_Id DESC
    ";

    // La declaracion es del contribuyente: se prefiere filtrar por ahi
    // cuando venga ese dato. dec_IdEstablecimiento se conserva como filtro
    // de respaldo para pantallas que aun no migraron a pedir por
    // contribuyente.
    $filtro = !empty($_POST['dec_IdContribuyente'])
        ? $_POST['dec_IdContribuyente']
        : $_POST['dec_IdEstablecimiento'];

    $res = $con->consultar($sql, [$filtro]);

    /*
     * ¿Se puede pagar en linea?
     *
     * Depende del convenio de recaudo de la entidad, que desde la migracion
     * 023 es configuracion y puede estar vacio -un municipio recien montado no
     * lo tiene todavia-. Sin el, ofrecer el boton de PSE es ofrecer algo que
     * solo puede fallar.
     *
     * Se resuelve UNA vez para todo el listado, no por fila: es el mismo dato
     * para todas y la clase ya cachea, pero preguntarlo dentro del bucle
     * sugeriria que puede variar entre declaraciones.
     */
    include_once SERVER . '/business/class.placetopay.php';
    $pagoEnLinea = (int) \PlacetoPay::configurado();

    $data = [];

    while($row = $con->obnerFila($res)){
        // El frontend necesita saber, SIN otra llamada, si a esta
        // declaracion le hace falta la firma del contador para poder
        // presentarse. Misma regla que _requiereContador(): tiene correo de
        // contador o de revisor registrado -tiene_correo_contador ya trae
        // ese calculo hecho desde el SQL de arriba-.
        $row['requiere_contador'] = (int) ($row['tiene_correo_contador'] ?? 0);

        $row['pago_en_linea'] = $pagoEnLinea;

        $data[] = $row;
    }

    $this->_ok = count($data) ? 1 : 0;
    $this->_mensaje = "Filtrado correctamente";

    return $data;
}


}


class DeclaracionesICAException extends \Exception {}


\erpsoftsas\ControladorDeclaracionesICA::run();