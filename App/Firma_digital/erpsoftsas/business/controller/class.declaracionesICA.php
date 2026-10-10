<?php
namespace erpsoftsas;

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/DAO/DAO_DeclaracionesICA.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER . '/business/controller/class.logs.php';
include_once SERVER . '/business/config.tributario.php';

class ControladorDeclaracionesICA extends \erpsoftsas\Cabecera 
{
    /**
     * Cuantos años hacia atras se puede declarar (ver _agregarDeclaracion). El
     * mismo numero esta en core/declaraciones.ui.js (ANIOS_ANTERIORES_ICA),
     * que arma la lista de la pantalla.
     */
    const ANIOS_ANTERIORES = 10;

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
     *
     * EL AÑO DEL NUMERO ES EL DE HOY, NO EL DE LA DECLARACION. Juan (Paipa,
     * 2026-10-05), al habilitar las declaraciones de años anteriores: «Aunque
     * sea de otro año sigue el consecutivo de este año». Una declaracion de
     * 2023 creada hoy sale 2026100024, y la correccion de una vieja tambien
     * toma la serie de hoy. Descartar un borrador devuelve el numero a la fila
     * del año que lleva el NUMERO (sus cuatro primeras cifras), asi que la
     * regla cuadra. Año de Colombia: el servidor esta en UTC.
     */
    private function _siguienteNumeroDeclaracion($con)
    {
        $anio = (int) (new \DateTime('now', new \DateTimeZone('America/Bogota')))->format('Y');

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

    /**
     * Los roles que ya firmaron ESTA declaracion de ICA ('declarante',
     * 'contador'); vacio si no tiene firmas.
     *
     * SOLO LAS DEL MODULO ICA. firmas_declaraciones guarda las firmas de los
     * tres formularios, y retencion y autorretencion reparten numeros de su
     * propia serie: la ICA 2026000001 y la retencion 2026000001 existen a la
     * vez. Sin el filtro de fd_Modulo, una retencion firmada le "ponia firmas"
     * a la ICA homonima -medido el 2026-09-28: los borradores 232, 350 y 352
     * no se podian borrar ("ya tiene firmas registradas") y no habia como
     * quitarselas, porque ya eran borradores-.
     *
     * Las del ICA se anotan con el dec_Id, que es lo que manda la ventana de
     * firma; se mira tambien el numero por las que quedaron anotadas asi en la
     * epoca en que numero e identity coincidian. Se compara como TEXTO:
     * fd_NumeroDeclaracion es VARCHAR, y contra un BIGINT SQL Server convierte
     * la columna entera, con lo que un valor no numerico tumbaria la consulta.
     *
     * Un solo sitio para la regla: la usan borrar, abrir para editar, guardar,
     * recalcular un renglon, presentar y la correccion en curso.
     */
    private static function _firmasIca($con, $idFila)
    {
        $roles = [];
        $st = $con->consultar(
            "SELECT DISTINCT f.fd_Rol
               FROM firmas_declaraciones f, ind_declaraciones_ica d
              WHERE d.dec_Id = ?
                AND f.fd_Modulo = 'ICA'
                AND f.fd_NumeroDeclaracion IN (CAST(d.dec_Id AS VARCHAR(30)),
                                               CAST(d.dec_NumeroDeclaracion AS VARCHAR(30)))",
            [(int) $idFila]
        );
        while ($f = $con->obnerFila($st)) {
            $roles[] = (string) $f['fd_Rol'];
        }
        return $roles;
    }

    /**
     * Por que esta declaracion ya no se puede cambiar, o null si se puede.
     *
     * Guardar (funcion 6) y recalcular un renglon (funcion 7) escriben en la
     * declaracion, y ninguna de las dos miraba su estado: la integridad
     * dependia de que la pantalla no ofreciera el formulario. Y hubo un camino
     * que si lo ofrecia: "Corregir" reabria una correccion en curso YA FIRMADA
     * directo al formulario, y se guardaba contenido nuevo debajo de las firmas
     * viejas (revision 2026-09-28). La regla del cliente es que editar una
     * firmada le quita las firmas; eso lo hace "Editar" (funcion 10), que
     * pregunta antes. Aqui solo se impide que alguna puerta se la salte, venga
     * de otra pestaña o de un clic.
     */
    private static function _motivoParaNoEditar($con, $idFila)
    {
        $d = $con->obnerFila($con->consultar(
            "SELECT dec_Estado FROM ind_declaraciones_ica WHERE dec_Id = ?",
            [(int) $idFila]
        ));

        if ((int) ($d['dec_Estado'] ?? 0) === 2) {
            return 'Una declaración presentada no se puede editar. Genere una corrección.';
        }

        if (self::_firmasIca($con, $idFila)) {
            return 'La declaración está firmada. Para cambiarla use "Editar" en el listado: '
                 . 'se quitarán las firmas y volverá a borrador.';
        }

        return null;
    }

    private static function _contribuyenteDeLaSesion($con)
    {
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }
        if (empty($_SESSION['id_usuario'])) { return null; }

        $fila = $con->obnerFila($con->consultar(
            // TOP 1 + ORDER BY: con documentos repetidos en el padrón debe salir
            // el mismo contribuyente que toman el login (DAO_Usuario) y el resto.
            "SELECT TOP 1 c.ind_Id
               FROM ind_contribuyentes c
               INNER JOIN conf_usuarios u ON u.usu_NumeroDocumento = c.ind_NumeroIdentificacion
              WHERE u.usu_Id = ?
              ORDER BY c.ind_Id",
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

        /*
         * Permisos por accion (panel de Roles, 2026-09-29): cada funcion pide
         * su interruptor de "Declaración de ICA". Trabajar sobre OTRO
         * contribuyente pide ademas "Gestionar a un contribuyente" (antes:
         * roles 1 y 2 podian todo, sin filtro).
         */
        include_once SERVER . '/business/class.permisosRol.php';
        $necesita = [
            1 => 'ica.editar', 2 => 'ica.editar', 3 => 'ica.ver', 4 => 'ica.editar',
            5 => 'ica.ver', 6 => 'ica.editar', 7 => 'ica.editar', 8 => 'ica.ver',
            9 => 'ica.presentar', 10 => 'ica.editar', 11 => 'ica.corregir',
            12 => 'ica.ver', 13 => 'ica.ver', 14 => 'ica.editar',
        ][(int) ($_POST['funcion'] ?? 0)] ?? null;
        if ($necesita !== null && !\erpsoftsas\PermisosRol::tiene($necesita)) {
            return \erpsoftsas\PermisosRol::mensaje($necesita);
        }
        if (\erpsoftsas\PermisosRol::gestionaOtros()) { return null; }
        if (\erpsoftsas\PermisosRol::esAlcaldia()) {
            return \erpsoftsas\PermisosRol::mensaje('alcaldia.contribuyentes.gestionar');
        }

        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
        $propio = self::_contribuyenteDeLaSesion($con);
        if (!$propio) {
            return 'No se pudo establecer a qué contribuyente corresponde la sesión.';
        }

        /*
         * El filtro por contribuyente se fija SIEMPRE, venga o no venga.
         *
         * Antes solo se pisaba si la clave ya estaba en el POST. Omitirla era
         * entonces la forma de saltarse la guarda: la peticion salia sin filtro
         * de contribuyente y la funcion 3 devolvia la tabla ENTERA -medido el
         * 2026-08-31 con el usuario externo de prueba: 200 filas de seis
         * contribuyentes distintos, con sus renglones de ingresos e impuesto-.
         *
         * Una guarda que solo corrige lo que le mandan, y no lo que le callan,
         * no es una guarda. Ahora el contribuyente de la sesion se impone como
         * filtro exista o no la clave, asi que ninguna funcion de este
         * controlador puede consultar a ciegas.
         *
         * Para los roles de Alcaldia (1 y 2) esto no corre: ya salieron arriba.
         */
        $_POST['dec_IdContribuyente'] = $propio;

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
            /*
             * SE AUTORIZA LA MISMA FILA QUE SE VA A TOCAR, NO OTRA QUE ENCAJE.
             *
             * Antes esta comprobacion resolvia el valor por su cuenta -"que
             * exista ALGUNA fila mia cuyo numero O cuyo id sea este"- mientras
             * que la operacion lo resuelve con _filaDeLaDeclaracion(), que
             * desempata prefiriendo la coincidencia por NUMERO. Dos criterios
             * distintos para el mismo dato ambiguo.
             *
             * Si un valor fuera a la vez el id de una declaracion mia y el
             * numero de la de otro, el permiso se concedia mirando la mia y la
             * operacion se ejecutaba sobre la ajena. Afecta a las funciones 6,
             * 7 y 14: la 7 ESCRIBE.
             *
             * Hoy no es explotable -medido: cero colisiones cruzadas, y los
             * numeros nuevos llevan prefijo de año, asi que no pueden chocar
             * con un id-. Pero las 99 filas heredadas tienen numero igual al
             * id y comparten ese espacio, asi que la propiedad depende de un
             * accidente de los datos y no de una regla.
             *
             * Se resuelve la fila canonica primero, con el mismo metodo que
             * usara la operacion, y se autoriza ESE id.
             */
            $filaCanonica = self::_filaDeLaDeclaracion($con, $_POST['idDeclaracion']);

            if ($filaCanonica === null) {
                return 'No tiene permiso sobre esta declaración.';
            }

            $fila = $con->obnerFila($con->consultar(
                "SELECT dec_Id FROM ind_declaraciones_ica
                  WHERE dec_Id = ? AND dec_IdContribuyente = ?",
                [$filaCanonica['id'], $propio]
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
            echo json_encode(["ok" => 0, "mensaje" => $negado, "datos" => [],
                              "sinSesion" => empty($_SESSION['id_usuario']) ? 1 : 0]);
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

        /*
         * El año lo elige quien crea; el periodo lo pone el sistema.
         *
         * El 2026-08-31 se llego a poner un selector de año en pantalla, porque
         * el ICA se presenta el año siguiente al gravable y con el año fijo al
         * del reloj nadie podria declarar en enero el año que acaba de cerrar.
         * El cliente pidio retirarlo: «nada de años». Se retiro entero.
         *
         * VUELVE EL 2026-10-05, a pedido de Juan (Paipa): «Las declaraciones de
         * años anteriores se presentan en todo momento, ya que hay
         * contribuyentes que las deben y tienen que presentarlas». Escribir
         * 2023 en la casilla no servia: el servidor ponia el año en curso. La
         * pantalla pregunta el año al pulsar "Crear" (pedirAnioDeclaracion en
         * core/declaraciones.ui.js) con el actual marcado; sin año, el actual,
         * como antes. Se acepta del año en curso hacia atras ANIOS_ANTERIORES,
         * nunca uno futuro.
         *
         * Lo que arrastra el año, y por que esta bien asi:
         *   - el NUMERO no: sigue la serie de hoy (_siguienteNumeroDeclaracion);
         *   - las formulas: las del año declarado o, si no hay, las mas
         *     antiguas cargadas (sp_calculo_comercio, migracion 036);
         *   - el vencimiento: el de ese año (VencimientoICA), asi que una de
         *     2023 ya nace vencida y se paga con recibo e intereses;
         *   - el anticipo del renglon 29: el de la presentada del año anterior
         *     al declarado.
         *
         * LA ZONA HORARIA VA ANTES DEL date('Y'), NO DESPUES (revision
         * 2026-09-28). Se fijaba mas abajo, justo antes de la fecha y la hora,
         * asi que el AÑO salia con el reloj del servidor, que esta en UTC: el
         * 31 de diciembre desde las 7 p. m. de Colombia la declaracion nacia
         * con el año SIGUIENTE -y con el numero de la serie siguiente-,
         * mientras su fecha decia 31/12. Año, fecha y hora, los tres de Colombia.
         */
        date_default_timezone_set('America/Bogota');
        $anioActual = (int) date('Y');
        $anio = $anioActual;
        $mes  = 12;

        $pedido = trim((string) ($_POST['dec_AnioDeclaracion'] ?? ''));
        if ($pedido !== '') {
            $desde = $anioActual - self::ANIOS_ANTERIORES;
            if (!ctype_digit($pedido) || (int) $pedido < $desde || (int) $pedido > $anioActual) {
                $this->_ok = 0;
                $this->_mensaje = "El año de la declaración debe estar entre $desde y $anioActual.";
                return [];
            }
            $anio = (int) $pedido;
        }

        /*
         * CREAR CREA. SIEMPRE.
         *
         * Aqui vivian dos frenos, y el cliente pidio el 2026-08-31 quitar los
         * dos: «cuando cree una nueva salga 200 y 201 y asi sucesivamente, nada
         * mas; nada de años y presentadas, nada de eso».
         *
         *   1. Si el contribuyente ya tenia un borrador del periodo, se reabria
         *      ESE en vez de crear otro. De ahi venia el «se queda en la 199»:
         *      no era que el numero no avanzara, es que no se estaba creando
         *      ninguna declaracion.
         *
         *   2. Si ya habia una PRESENTADA del periodo, se rechazaba y se
         *      mandaba a Corregir.
         *
         * SE ADVIRTIO Y SE DECIDIO. Se le expuso que sin el primer freno un
         * contribuyente acumula borradores del mismo periodo -que es como
         * empezo todo este hilo- y que sin el segundo pueden convivir dos
         * declaraciones ORIGINALES del mismo periodo, cosa que el propio
         * cliente habia confirmado antes como requisito legal. Lo confirmo
         * igualmente. Queda como decision suya.
         *
         * El indice que lo impedia a nivel de base (UQ_declaracion_periodo_nuevas)
         * se retira en la migracion 027; sin retirarlo, el INSERT de abajo
         * chocaria contra el y esto no serviria de nada.
         *
         * Lo que NO se toca: un numero sigue siendo unico (UQ_declaracion_numero).
         * El numero viaja dentro del codigo de barras de recaudo, asi que dos
         * declaraciones con el mismo serian dos recibos indistinguibles para el
         * banco. Eso no es politica, es condicion para cobrar.
         */

        $_obj = new \erpsoftsas\DAO_DeclaracionesICA();

        $_obj->set_dec_AnioDeclaracion($anio);
        $_obj->set_dec_MesDeclaracion($mes);

        // Referencia de auditoria de cual establecimiento disparo la
        // creacion; la declaracion en si pertenece al contribuyente.
        if (!empty($_POST['dec_IdEstablecimiento'])) {
            $_obj->set_dec_IdEstablecimiento($_POST['dec_IdEstablecimiento']);
        }
        $_obj->set_dec_IdContribuyente($idContribuyente);

        // La zona de Colombia ya quedo fijada arriba, antes del año.
        $_obj->set_dec_FechaDeclaracion(date('Y-m-d'));
        $_obj->set_dec_HoraDeclaracion(date('H:i:s'));
        $_obj->set_dec_OpcionUso(1);

        /*
         * EL BORRADOR ES dec_Estado NULL, Y AQUI NO SE ESCRIBE NINGUN ESTADO.
         *
         * Habia un set_dec_Estado(1) comentado como "borrador". Era doblemente
         * equivocado y no hacia nada:
         *
         *   1. DAO_DeclaracionesICA no mapea dec_Estado -no esta como propiedad
         *      ni en $_mapa-, y su __call solo asigna lo que existe, asi que la
         *      llamada se descartaba en silencio. Comprobado: toda declaracion
         *      nueva nace con dec_Estado NULL.
         *   2. El valor 1 tampoco es "borrador". El diccionario de la columna
         *      (migracion 001) dice: 0 borrador, 1 firmada, 2 presentada. Si
         *      algun dia alguien "arreglara" el DAO añadiendo la columna, esa
         *      linea empezaria a marcar como FIRMADA cada declaracion recien
         *      creada, sin firma ninguna detras.
         *
         * Se retira en vez de corregirla porque NULL es de facto el borrador en
         * todo el sistema: claveEstado() en el frontend lo trata asi por
         * descarte, _revertirABorrador() vuelve a dejar NULL, y ninguna consulta
         * filtra por dec_Estado = 0. Escribir un 0 aqui no arreglaria nada y
         * dejaria dos representaciones del mismo estado conviviendo.
         *
         * Lo que no podia quedarse es la apariencia de que se escribe un estado
         * que no se escribe.
         */

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
            $numero = $this->_siguienteNumeroDeclaracion($con);
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


    /**
     * funcion 3 - Consulta declaraciones por los filtros que vengan.
     *
     * EL FILTRO ERA CODIGO MUERTO: DEVOLVIA LA TABLA ENTERA
     *
     * DAO_DeclaracionesICA no declara ni un solo metodo set_: los resuelve
     * todos por __call. Y method_exists() devuelve FALSE para los metodos
     * magicos -comprobado en este mismo entorno-, asi que la condicion de
     * abajo no se cumplia NUNCA, no se asignaba NINGUN filtro, y consultar()
     * salia con "where 1=1".
     *
     * Medido el 2026-08-31 con la sesion del contribuyente de prueba: la
     * peticion devolvia 200 de las 201 declaraciones de la base, de seis
     * contribuyentes distintos, con todos sus renglones. Sobre datos con
     * reserva tributaria eso es una fuga, no una molestia. Pasar el filtro
     * correcto tampoco servia de nada: se descartaba igual.
     *
     * Se cambia a property_exists sobre el atributo, que es exactamente lo que
     * comprueba el propio __call antes de asignar. Asi el filtro que se pide
     * es el filtro que se aplica.
     *
     * Y SE EXIGE FILTRO DE CONTRIBUYENTE
     *
     * _verificarAcceso() ya impone el contribuyente de la sesion a todo lo que
     * no sea rol de Alcaldia, asi que aqui siempre llega. La comprobacion se
     * deja igual porque esta funcion no tiene ningun llamador en la interfaz
     * -se revisaron las tres pantallas: sus "funcion: 3" van a otros
     * controladores- y un endpoint sin dueño es justo el que nadie vuelve a
     * mirar. Si algun dia alguien lo llama sin filtro, contesta que no en vez
     * de volcar la tabla.
     */
    private function _consultarDeclaraciones()
    {

        $_obj = new \erpsoftsas\DAO_DeclaracionesICA();

        foreach ($_POST as $campo => $valor) {

            $metodo = 'set_' . $campo;

            // property_exists, no method_exists: los setters son magicos.
            if (property_exists($_obj, '_' . $campo)) {
                $_obj->$metodo($valor);
            }

        }

        if (empty($_POST['dec_IdContribuyente'])) {
            $this->_ok = 0;
            $this->_mensaje = 'Consulta sin contribuyente: no se devuelven declaraciones.';
            return [];
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

        $d = $con->obnerFila($con->consultar(
            "SELECT d.dec_Estado, d.dec_NumeroDeclaracion
               FROM ind_declaraciones_ica d WHERE d.dec_Id = ?",
            [$id]
        ));

        if ((int) ($d['dec_Estado'] ?? 0) === 2) {
            $this->_ok = 0;
            $this->_mensaje = 'Una declaración presentada no se puede borrar. '
                            . 'Si hay que corregirla, use "Corregir".';
            return [];
        }

        /*
         * Solo cuentan las firmas de ESTA declaracion de ICA (_firmasIca).
         * Antes se contaban las de cualquier modulo con el mismo numero, y como
         * las tres series empiezan en 2026000001, un borrador de ICA no se podia
         * borrar en cuanto una retencion homonima estaba firmada.
         */
        if (self::_firmasIca($con, $id)) {
            $this->_ok = 0;
            // "Devuélvala a borrador" no decía cómo: el botón que lo hace es
            // "Editar" de una firmada (función 10).
            $this->_mensaje = 'Esta declaración ya tiene firmas registradas y no se puede borrar. '
                            . 'Para cambiarla use "Editar": se quitan las firmas y vuelve a borrador.';
            return [];
        }

        try {
            // Solo si sigue siendo borrador: la Alcaldía pudo registrarla
            // presentada y pagada en el intervalo (registro manual, 044).
            $sigueBorrador = "EXISTS (SELECT 1 FROM ind_declaraciones_ica b
                                       WHERE b.dec_Id = ? AND ISNULL(b.dec_Estado, 0) <> 2 AND ISNULL(b.dec_Pagado, 0) = 0)";
            $con->consultar("DELETE FROM ind_declaraciones_ica_actividades WHERE dia_IdDeclaracion = ? AND $sigueBorrador", [$id, $id]);
            $con->consultar("DELETE FROM ind_declaraciones_ica WHERE dec_Id = ? AND ISNULL(dec_Estado, 0) <> 2 AND ISNULL(dec_Pagado, 0) = 0", [$id]);
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
        /*
         * El numero vuelve a llevar el año (migracion 029), asi que se parte en
         * año + secuencia y se busca la fila de ESE año.
         *
         * Ojo con el historial: esto ya estuvo escrito asi, se cambio el
         * 2026-08-31 a la serie corrida de la migracion 027, y vuelve ahora
         * porque el cliente pidio de nuevo el formato por año. La condicion de
         * los diez caracteres es la que distingue un numero nuevo
         * (2026000001) de los historicos de dos y tres digitos, que no pasan
         * por ningun contador y no hay nada que devolverles.
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

        /*
         * Una FIRMADA tampoco se abre directo para editar: primero se le quitan
         * las firmas (funcion 10, que pide confirmacion en pantalla).
         *
         * "Corregir" con una correccion en curso ya firmada la abria aqui sin
         * mas, y se guardaba contenido nuevo debajo de las firmas viejas. El
         * codigo FIRMADA le dice a la pantalla que tome el camino de "Editar"
         * de una firmada en vez de mostrar un error suelto; sirve para
         * cualquier otra puerta que llegue aqui con una firmada.
         */
        if (self::_firmasIca($con, $declaracion['dec_Id'])) {
            $this->_ok = 0;
            $this->_mensaje = 'La declaración está firmada: para editarla hay que quitarle las firmas.';
            return ['codigo' => 'FIRMADA', 'dec_Id' => (int) $declaracion['dec_Id']];
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
    $params = [(int) ($_POST['est_Id'] ?? 0)];
    // Solo de un establecimiento suyo, salvo quien gestiona contribuyentes
    // (_verificarAcceso ya fijo dec_IdContribuyente al de la sesion).
    if (!\erpsoftsas\PermisosRol::gestionaOtros()) {
        $sql .= " AND e.est_IdContribuyente = ?";
        $params[] = (int) ($_POST['dec_IdContribuyente'] ?? 0);
    }

    $res = $con->consultar($sql, $params);

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
         *
         * VA LO PRIMERO. En una version anterior de este arreglo quedo DESPUES
         * del UPDATE de totales, asi que $idFila llegaba nulo justo ahi y los
         * ingresos no se guardaban. Resolver primero y usar despues.
         */
        $fila = self::_filaDeLaDeclaracion($con, $idDeclaracion);
        if ($fila === null) {
            $this->_ok = 0;
            $this->_mensaje = "No se encontró la declaración " . $idDeclaracion;
            return [];
        }
        $idFila = $fila['id'];

        // Ni presentada ni firmada: ver _motivoParaNoEditar.
        $motivo = self::_motivoParaNoEditar($con, $idFila);
        if ($motivo !== null) {
            $this->_ok = 0;
            $this->_mensaje = $motivo;
            return [];
        }

        /*
         * Guardado de totales y actividades: lo hace _guardarActividadesYTotales.
         *
         * Estaba escrito aqui dentro, y desde el 2026-09-01 lo necesita tambien
         * la funcion 7 -escribir un renglon manual tiene que liquidar con las
         * actividades del formulario, no con las de la base-. Copiarlo habria
         * repetido el error que ya costo dos veces en este proyecto: se arregla
         * una copia y la otra sigue rota.
         */
        $this->_guardarActividadesYTotales($con, $idFila, $actividades, is_array($totales) ? $totales : []);

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

        // Aquí solo llegan errores de la base (p. ej. una cifra que no cabe en
        // la columna): su texto es un volcado de sqlsrv_errors() que no le sirve
        // al usuario. Va al log; la pantalla recibe un aviso que se entiende.
        error_log('[declaracionesICA] no se pudo guardar la declaración: ' . $e->getMessage());
        $this->_ok = 0;
        $this->_mensaje = 'No se pudo guardar la declaración. Revise que las cifras estén bien escritas e intente de nuevo; si persiste, avise a soporte.';

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

        // Mismo criterio que la funcion 6, y tambien ANTES del UPDATE.
        $fila = self::_filaDeLaDeclaracion($con, $idDeclaracion);
        $idFila = $fila === null ? null : $fila['id'];
        if ($idFila === null) {
            throw new \erpsoftsas\DeclaracionesICAException('No se encontró la declaración ' . $idDeclaracion);
        }

        /*
         * Mismo guardado que "Guardar", con la diferencia de que esto vive
         * dentro de una transaccion que SIEMPRE se deshace: se escribe para que
         * el procedimiento tenga sobre que liquidar, se lee el resultado, y el
         * rollback lo borra. De ahi que Liquidar no deje rastro.
         */
        $this->_guardarActividadesYTotales($con, $idFila, $actividades, is_array($totales) ? $totales : []);

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

    } catch (\erpsoftsas\DeclaracionesICAException $e) {

        $this->_ok = 0;
        $this->_mensaje = $e->getMessage();
        return [];

    } catch (\Exception $e) {

        // Error de la base: al log; la pantalla recibe un aviso que se entiende
        // (como en las funciones 6 y 7).
        error_log('[declaracionesICA] no se pudo liquidar: ' . $e->getMessage());
        $this->_ok = 0;
        $this->_mensaje = 'No se pudo calcular la liquidación. Revise que las cifras estén bien escritas e intente de nuevo; si persiste, avise a soporte.';
        return [];

    } finally {
        if ($abierta) {
            // Nunca hay commit: el proposito del metodo es no dejar rastro.
            try { $con->rollback(); } catch (\Exception $e) { /* ya cerrada */ }
        }
    }
}


/*
 * Sin try/catch A PROPÓSITO. Atrapaba el error del procedimiento, ponía _ok = 0
 * y volvía; quien la llama (funciones 6, 7 y 14) seguía y lo pisaba con ok = 1:
 * una fórmula mala en Conceptos o un desbordamiento dentro del SP terminaban en
 * "Declaración guardada" con los renglones sin recalcular, y sin rastro en el
 * log. Los tres la llaman dentro de su propio try, que registra y avisa.
 */
private function _ejecutarSpLiquidacion($anio,$mes,$numero, $campoSeleccionado){

    $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

    $sql = "EXEC sp_calculo_comercio ?, ?, ?, ?";
    $con->consultar($sql, [$anio, $mes, $numero,$campoSeleccionado]);

    return [];
}


/**
 * Guarda los totales de ingresos y las actividades de una declaracion.
 *
 * Vive aparte porque lo usan DOS caminos: "Guardar" (funcion 6) y, desde el
 * 2026-09-01, tambien la escritura de un renglon manual (funcion 7). Antes solo
 * lo hacia la 6, y esa asimetria era el defecto: la 7 liquidaba con lo que
 * hubiera en la base aunque la pantalla tuviera otra cosa.
 *
 * Recibe el dec_Id YA RESUELTO. No acepta el "idDeclaracion" del navegador, que
 * lleva el numero: engancharlas por el numero las deja invisibles para el
 * procedimiento de liquidacion, que suma por dia_IdDeclaracion = dec_Id.
 *
 * @param object $con     conexion
 * @param int    $idFila  dec_Id resuelto
 * @param array  $actividades  filas de la tabla de actividades del formulario
 * @param array  $totales      renglones de ingresos; si viene vacio no se tocan
 */
/** ¿La base tiene las columnas del tipo de sancion (migracion 038)? */
private static function _hayColumnasSancion($con)
{
    static $hay = null;
    if ($hay === null) {
        $f = $con->obnerFila($con->consultar(
            "SELECT COL_LENGTH('dbo.ind_declaraciones_ica', 'dec_TipoSancion') AS t,
                    COL_LENGTH('dbo.ind_declaraciones_ica', 'dec_OtraSancion') AS o"
        ));
        $hay = !empty($f['t']) && !empty($f['o']);
    }
    return $hay;
}

private function _guardarActividadesYTotales($con, $idFila, array $actividades, array $totales = [])
{
    if (count($totales)) {
        /*
         * UNA CLAVE QUE NO VIENE CONSERVA SU VALOR. NO ESCRIBE CERO.
         *
         * Estos ocho renglones se guardaban con "?? 0", asi que bastaba que la
         * pantalla no mandara uno para que se escribiera un cero encima del
         * dato bueno. Y paso: el 2026-09-01 la funcion que arma los totales
         * leia data-campo="total_ingresos" cuando el campo se llama
         * "ingresos_total_pais"; el selector devolvia undefined, la clave no
         * viajaba, y el renglon 8 del formulario impreso se iba a CERO -con el
         * renglon 10, que resta, saliendo negativo-.
         *
         * COALESCE distingue las dos cosas que "?? 0" confundia: mandar un cero
         * a proposito (llega 0 y se guarda 0) y no mandar nada (llega null y se
         * conserva lo que hubiera). Es el mismo trato que ya tenian capacidad
         * instalada y valor del impuesto, por esta misma razon.
         */
        $con->consultar("
            UPDATE ind_declaraciones_ica SET
                dec_TotalIngresos            = COALESCE(?, dec_TotalIngresos),
                dec_IngresosFueraMunicipio   = COALESCE(?, dec_IngresosFueraMunicipio),
                dec_IngresosDevoluciones     = COALESCE(?, dec_IngresosDevoluciones),
                dec_IngresosExportaciones    = COALESCE(?, dec_IngresosExportaciones),
                dec_IngresosVentas           = COALESCE(?, dec_IngresosVentas),
                dec_IngresosActividades      = COALESCE(?, dec_IngresosActividades),
                dec_IngresosOtrasActividades = COALESCE(?, dec_IngresosOtrasActividades),
                dec_BaseGravable             = COALESCE(?, dec_BaseGravable),
                dec_CapacidadInstalada       = COALESCE(?, dec_CapacidadInstalada),
                dec_ValorImpuesto            = COALESCE(?, dec_ValorImpuesto)
            WHERE dec_Id = ?
        ", [
            $totales['dec_TotalIngresos']            ?? null,
            $totales['dec_IngresosFueraMunicipio']   ?? null,
            $totales['dec_IngresosDevoluciones']     ?? null,
            $totales['dec_IngresosExportaciones']    ?? null,
            $totales['dec_IngresosVentas']           ?? null,
            $totales['dec_IngresosActividades']      ?? null,
            $totales['dec_IngresosOtrasActividades'] ?? null,
            $totales['dec_BaseGravable']             ?? null,
            $totales['dec_CapacidadInstalada']       ?? null,
            $totales['dec_ValorImpuesto']            ?? null,
            $idFila
        ]);

        /*
         * Tipo de sancion del renglon 31 (migracion 038). Solo si la pantalla
         * lo manda y la base tiene las columnas; un valor fuera de la lista no
         * entra. "Ninguna" guarda NULL, y el "¿Cuál?" solo vale con "otra".
         */
        if (array_key_exists('dec_TipoSancion', $totales) && self::_hayColumnasSancion($con)) {
            $tipo = strtolower(trim((string) $totales['dec_TipoSancion']));
            if (!in_array($tipo, ['extemporaneidad', 'correccion', 'inexactitud', 'otra'], true)) { $tipo = ''; }
            // 40: mas largo parte en dos el renglon 31 del PDF y saca el codigo
            // de barras del papel (revision 2026-09-29).
            $otra = $tipo === 'otra' ? mb_substr(trim((string) ($totales['dec_OtraSancion'] ?? '')), 0, 40) : '';
            $con->consultar(
                "UPDATE ind_declaraciones_ica
                    SET dec_TipoSancion = NULLIF(?, ''), dec_OtraSancion = NULLIF(?, '')
                  WHERE dec_Id = ?",
                [$tipo, $otra, $idFila]
            );
        }
    }

    // Se reemplazan enteras: la pantalla manda siempre la tabla completa, asi
    // que borrar y volver a insertar es lo unico que refleja una fila quitada.
    $con->consultar(
        "DELETE FROM ind_declaraciones_ica_actividades WHERE dia_IdDeclaracion = ?",
        [$idFila]
    );

    foreach ($actividades as $a) {
        $con->consultar("
            INSERT INTO ind_declaraciones_ica_actividades
                (dia_IdDeclaracion, dia_IdActividad, dia_BaseGravable,
                 dia_Tarifa, dia_ValorImpuesto, dia_Activo, dia_FechaCreador)
            VALUES (?,?,?,?,?,1,GETDATE())
        ", [
            $idFila,
            $a['dia_IdActividad']   ?? 0,
            $a['dia_BaseGravable']  ?? 0,
            $a['dia_Tarifa']        ?? 0,
            $a['dia_ValorImpuesto'] ?? 0
        ]);
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

        // Ni presentada ni firmada, como Guardar: ver _motivoParaNoEditar.
        $motivo = self::_motivoParaNoEditar($con, $idFila);
        if ($motivo !== null) {
            $this->_ok = 0;
            $this->_mensaje = $motivo;
            return [];
        }

        $sqlUpdate = "
        UPDATE ind_declaraciones_ica SET
            ".$NombreCampo." = ?
        WHERE dec_Id = ?
        ";

        $con->consultar($sqlUpdate, [
            $valorLimpio,
            $idFila
        ]);

        /*
         * SE RECALCULA CON LO QUE HAY EN PANTALLA, NO CON LO QUE HAY EN LA BASE.
         *
         * Aqui estaba el "pongo un dato y se pasa a 0" que reporto el cliente el
         * 2026-09-01, y es el mismo sintoma que Juan describio como "me cambia
         * toda la declaracion".
         *
         * La pantalla tenia DOS fuentes de verdad. "Liquidar" (funcion 14)
         * calcula con las actividades del FORMULARIO y no guarda nada -asi lo
         * pidio el cliente-. Pero escribir una retencion disparaba esta funcion,
         * que recalculaba con las actividades de la BASE. Si el contribuyente
         * todavia no habia pulsado "Guardar", en la base no habia ninguna, el
         * procedimiento liquidaba sobre cero y la pantalla repintaba ceros
         * encima de las cifras que Liquidar acababa de mostrar.
         *
         * Reproducido de punta a punta: crear la N° 224 sin guardar, Liquidar
         * -mostraba renglon 20 = 40.000, 21 = 6.000, 25 = 48.000- y escribir
         * 1.000.000 en el renglon 28 devolvia 20 = 0, 21 = 0, 25 = 0. Y la
         * declaracion 222 del cliente estaba justo asi: cero actividades
         * guardadas y los dos renglones que alcanzo a teclear.
         *
         * Ahora, si la peticion trae las actividades del formulario, se guardan
         * ANTES de liquidar. Con eso la base y la pantalla dicen lo mismo y el
         * resultado ya no depende de si se pulso "Guardar" antes o despues.
         *
         * Si no vienen -una llamada vieja, o una pantalla que no las mande- se
         * comporta como siempre y liquida con lo que haya guardado.
         */
        if (isset($_POST['actividades'])) {
            $actividades = json_decode($_POST['actividades'], true);
            $totales     = isset($_POST['totales']) ? json_decode($_POST['totales'], true) : [];

            if (is_array($actividades) && count($actividades)) {
                $this->_guardarActividadesYTotales($con, $idFila, $actividades, is_array($totales) ? $totales : []);
            }
        }

        /*
         * SE RECALCULA DESDE EL PRINCIPIO (0), NO DESDE EL RENGLON EDITADO.
         *
         * Aqui se pasaba $campoSeleccionado. El procedimiento solo recorre los
         * conceptos con con_Codigo > @POSICION_CONCEPTO, asi que editar el
         * renglon 28 dejaba SIN recalcular los renglones 20, 21 y 25. Medido:
         * tras escribir en el 28, el 21 y el 25 se quedaban en cero.
         *
         * Ese parametro existia para que el recalculo no pisara el valor recien
         * escrito. Ya no hace falta: la migracion 010 cambio la formula de los
         * nueve renglones manuales (5,6,7,8,9,10,11,16,17) por una referencia a
         * si mismos, de modo que recalcularlos los conserva. Protegerlos ademas
         * saltandose los anteriores es lo que rompia el resto.
         *
         * "Guardar" (funcion 6) y "Liquidar" (funcion 14) ya pasaban 0. Esta era
         * la unica que no, y por eso era la unica que dejaba la pantalla a medias.
         */
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

        // Aquí solo llegan errores de la base (p. ej. una cifra que no cabe en
        // la columna): su texto es un volcado de sqlsrv_errors() que no le sirve
        // al usuario. Va al log; la pantalla recibe un aviso que se entiende.
        error_log('[declaracionesICA] no se pudo recalcular un renglón: ' . $e->getMessage());
        $this->_ok = 0;
        $this->_mensaje = 'No se pudo recalcular. Revise que la cifra esté bien escrita e intente de nuevo; si persiste, avise a soporte.';

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

    $idDeclaracion = (int) ($_POST['dec_Id'] ?? 0);

    if ($idDeclaracion <= 0) {
        $this->_ok = 0;
        $this->_mensaje = "Id de declaración requerido";
        return [];
    }

    $decl = $con->obnerFila($con->consultar(
        "SELECT dec_IdContribuyente, dec_Estado,
                (SELECT COUNT(*) FROM ind_declaraciones_ica_actividades a
                  WHERE a.dia_IdDeclaracion = d.dec_Id) AS n_actividades
           FROM ind_declaraciones_ica d WHERE d.dec_Id = ?",
        [$idDeclaracion]
    ));

    if (!$decl) {
        $this->_ok = 0;
        $this->_mensaje = "La declaración no existe";
        return [];
    }

    /*
     * Presentar dos veces movia la fecha de presentacion -la que imprime el
     * sello- a la del segundo intento. Una presentada ya no se toca.
     */
    if ((int) ($decl['dec_Estado'] ?? 0) === 2) {
        $this->_ok = 0;
        $this->_mensaje = "La declaración ya está presentada.";
        return [];
    }

    /*
     * SIN ACTIVIDADES GUARDADAS NO SE PRESENTA (revision 2026-09-28).
     *
     * Una declaracion recien creada ya ofrece "Firmar", y nada impedia firmarla
     * y presentarla sin haberla guardado nunca: quedaba presentada en $0, sin
     * actividades, y la unica salida era una correccion. La firma ya lo exige
     * (microservicios/firmas/api.php, _icaSinContenido, con el mismo conteo);
     * aqui se exige tambien, porque hay declaraciones que se firmaron antes de
     * esa regla y porque presentar es el acto que cuenta.
     *
     * El mensaje dice el camino que de verdad funciona: "Editar" abre la
     * declaracion con las actividades del contribuyente cuando no tiene
     * ninguna guardada (EditarDeclaracion.abrir, core/declaraciones.ui.js);
     * antes la abria con la tabla vacia y "ábrala, liquídela y guárdela" no
     * llevaba a ningun lado (segunda revision 2026-09-28).
     */
    if ((int) ($decl['n_actividades'] ?? 0) === 0) {
        $this->_ok = 0;
        $this->_mensaje = 'La declaración no tiene actividades guardadas. Ábrala con "Editar" '
                        . '(si está firmada, se le quitan las firmas), escriba la base de cada actividad, '
                        . 'pulse "Guardar" y fírmela antes de presentarla.';
        return ['codigo' => 'SIN_ACTIVIDADES'];
    }

    // Solo las firmas de ESTA declaracion de ICA: ver _firmasIca.
    $roles = self::_firmasIca($con, $idDeclaracion);

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

    /*
     * LA FECHA DE PRESENTACION ES LA DE COLOMBIA, CALCULADA AQUI.
     *
     * Era GETDATE(): el reloj del servidor SQL, que en local -y segun el propio
     * codigo, en produccion- va en UTC. El sello del PDF imprime esta fecha tal
     * cual, asi que una declaracion presentada a las 8 p. m. del 30 de abril
     * salia sellada "01/05/2026 01:00", como si se hubiera presentado tarde.
     * La fecha y la hora de la declaracion ya se ponian en hora de Colombia.
     */
    $ahora = (new \DateTime('now', new \DateTimeZone('America/Bogota')))->format('Y-m-d H:i:s');

    $con->consultar(
        "UPDATE ind_declaraciones_ica
         SET dec_Estado = 2, dec_FechaPresentacion = ?
         WHERE dec_Id = ? AND ISNULL(dec_Estado, 0) <> 2",
        [$ahora, $idDeclaracion]
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

    /*
     * PRIMERO EL ESTADO, Y SOLO SI SIGUE SIN PRESENTAR (segunda revision
     * 2026-09-28).
     *
     * La consulta de arriba y lo que sigue no son un solo paso: si alguien
     * presentaba la declaracion entre los dos (otra pestaña, el contador), se
     * le borraban las firmas y el UPDATE sin condicion le quitaba el estado y
     * la fecha de presentacion. Una declaracion ya presentada ante el
     * municipio volvia a borrador. Ahora el UPDATE solo toca la fila si sigue
     * sin presentar, y las firmas solo se borran si lo hizo.
     */
    $cambio = $con->obnerFila($con->consultar(
        "SET NOCOUNT ON;
         UPDATE ind_declaraciones_ica
            SET dec_Estado = NULL, dec_FechaPresentacion = NULL
          WHERE dec_Id = ? AND ISNULL(dec_Estado, 0) <> 2;
         SELECT @@ROWCOUNT AS n;",
        [(int) $idDeclaracion]
    ));

    if ((int) ($cambio['n'] ?? 0) === 0) {
        $sigue = $con->obnerFila($con->consultar(
            "SELECT dec_Id FROM ind_declaraciones_ica WHERE dec_Id = ?",
            [(int) $idDeclaracion]
        ));
        $this->_ok = 0;
        $this->_mensaje = $sigue
            ? "La declaración se acaba de presentar: ya no se puede editar. "
              . "Debe generar una declaración de corrección."
            : "La declaración no existe";
        return [];
    }

    // Se borran TODAS las firmas de la declaracion (declarante y, cuando
    // exista, contador/revisor): si el contenido cambia, ninguna sigue
    // acreditando lo que se firmo.
    //
    // Las mismas que ve _firmasIca -por id o por numero, y SOLO del modulo
    // ICA-: si se borraran menos de las que se cuentan, "Editar" de una
    // firmada volveria a encontrarla firmada al abrirla. Y sin el filtro de
    // modulo se podian llevar por delante las de una retencion homonima.
    $con->consultar(
        "DELETE FROM firmas_declaraciones
          WHERE fd_Modulo = 'ICA'
            AND fd_NumeroDeclaracion IN (
                  SELECT CAST(dec_Id AS VARCHAR(30)) FROM ind_declaraciones_ica WHERE dec_Id = ?
                  UNION
                  SELECT CAST(dec_NumeroDeclaracion AS VARCHAR(30)) FROM ind_declaraciones_ica
                   WHERE dec_Id = ? AND dec_NumeroDeclaracion IS NOT NULL)",
        [(int) $idDeclaracion, (int) $idDeclaracion]
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

    /*
     * SE CORRIGE EL ACTO VIGENTE, NO EL QUE SE PULSO.
     *
     * El boton "Corregir" se pinta sobre TODA fila presentada, incluidas las
     * originales que una correccion posterior ya dejo sin efecto. Pulsando la
     * de arriba se creaba una correccion HERMANA de la que ya existia -las dos
     * colgando de la misma original- en vez de un eslabon de la cadena. Y el
     * "N° DE DECLARACION A CORREGIR" que se imprime sale del enlace, asi que el
     * formulario declaraba corregir un documento que ya no era el vigente.
     *
     * Corregir es sustituir lo ultimo que se presento del periodo. Si lo que se
     * pulso no es eso, se sigue la cadena hasta el ultimo acto en firme y se
     * dice, en vez de obedecer un clic que produce un documento incorrecto.
     *
     * LA CADENA ES LA DE dec_DeclaracionCorrige, NO "LA ULTIMA DEL AÑO"
     * (revision 2026-09-28). La vigente se buscaba como la ultima presentada
     * del contribuyente en ese año y mes. Eso valia mientras hubo una sola
     * original por periodo; desde que "crear crea siempre" (2026-08-31) puede
     * haber varias -en la base local, el contribuyente 30 tiene cinco
     * originales presentadas en 2026-, y todas nacen con mes 12 y el año en
     * que se crean. Corregir cualquiera de ellas corregia la ULTIMA, con el
     * aviso falso de que "la que seleccionó ya había sido corregida"; las
     * anteriores no se podian corregir nunca.
     *
     * Ahora se sigue el enlace real: si la pulsada tiene una correccion
     * presentada, se pasa a esa, y asi hasta la que nadie ha corregido. El
     * tope de vueltas y el registro de las vistas son solo por si un enlace
     * mal grabado formara un ciclo.
     */
    $seleccionada = $orig['dec_NumeroDeclaracion'] ?: $orig['dec_Id'];
    $seRedirigio  = false;
    $vistas       = [(int) $orig['dec_Id'] => true];

    for ($vuelta = 0; $vuelta < 50; $vuelta++) {
        $siguiente = $con->obnerFila($con->consultar(
            "SELECT TOP 1 *
               FROM ind_declaraciones_ica
              WHERE dec_DeclaracionCorrige = ?
                AND dec_IdContribuyente = ?
                AND dec_Estado = 2
              ORDER BY dec_FechaPresentacion DESC, dec_Id DESC",
            [$orig['dec_NumeroDeclaracion'] ?: $orig['dec_Id'], $orig['dec_IdContribuyente']]
        ));

        if (!$siguiente || isset($vistas[(int) $siguiente['dec_Id']])) { break; }

        $vistas[(int) $siguiente['dec_Id']] = true;
        $orig          = $siguiente;
        $idDeclaracion = $siguiente['dec_Id'];
        $seRedirigio   = true;
    }

    /*
     * UNA CORRECCION EN CURSO, NO UNA PILA DE ELLAS.
     *
     * Nada impedia pulsar "Corregir" dos veces: cada clic insertaba otra
     * correccion de la misma declaracion, cada una con su consecutivo gastado.
     * Medido el 2026-08-31 sobre el contribuyente de prueba: cuatro
     * correcciones en borrador de las mismas presentadas, tres de ellas de la
     * misma N° 189, y los numeros 2026000002 a 2026000005 consumidos sin que
     * exista ningun documento detras.
     *
     * Es la misma regla que ya gobierna la creacion: mientras haya una en
     * curso se reabre ESA en vez de duplicar. Una correccion es una
     * declaracion, y el periodo sigue admitiendo un solo documento vivo.
     *
     * Solo se reabre lo que todavia es borrador: si la correccion anterior ya
     * se presento, corregirla otra vez es legitimo -es la segunda correccion-
     * y ahi si nace una nueva.
     */
    $enCurso = $con->obnerFila($con->consultar(
        "SELECT TOP 1 dec_Id, dec_NumeroDeclaracion
           FROM ind_declaraciones_ica
          WHERE dec_DeclaracionCorrige = ?
            AND dec_IdContribuyente = ?
            AND (dec_Estado IS NULL OR dec_Estado <> 2)
          ORDER BY dec_Id DESC",
        [$orig['dec_NumeroDeclaracion'] ?: $orig['dec_Id'], $orig['dec_IdContribuyente']]
    ));

    if ($enCurso) {
        /*
         * Si la que esta en curso ya se FIRMO, no se abre tal cual: la pantalla
         * la pide para editar (funcion 13), que ahora contesta FIRMADA, y toma
         * el camino de "Editar" de una firmada -pregunta y quita las firmas-.
         * Antes se abria directo y se guardaba contenido nuevo debajo de las
         * firmas viejas. Aqui solo se avisa en el mensaje.
         */
        $firmada = (bool) self::_firmasIca($con, $enCurso['dec_Id']);

        $this->_ok = 1;
        $this->_mensaje = 'Ya tenía una corrección en curso de la N° '
            . ($orig['dec_NumeroDeclaracion'] ?: $orig['dec_Id'])
            . ': la N° ' . ($enCurso['dec_NumeroDeclaracion'] ?: $enCurso['dec_Id'])
            . '. Se abre esa, con lo que hubiera guardado. No se creó otra.'
            . ($firmada ? ' Está firmada: para editarla se le quitarán las firmas.' : '');

        return [
            'dec_Id'                 => $enCurso['dec_Id'],
            'dec_DeclaracionCorrige' => $orig['dec_NumeroDeclaracion'] ?: $orig['dec_Id'],
            '_reabierta'             => 1,
            'firmada'                => $firmada ? 1 : 0
        ];
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
        /*
         * La fecha y la hora tambien nacen de cero.
         *
         * Se copiaban de la original, asi que una correccion hecha hoy salia
         * fechada el dia en que se creo la declaracion corregida -medido:
         * correcciones creadas el 31/08 con fecha 17/04/2026-. Esa fecha se
         * imprime en el formulario, de modo que el papel afirmaba que el
         * documento se hizo meses antes de existir. Se fijan abajo con la de
         * hoy, igual que hace _agregarDeclaracion().
         */
        'dec_FechaDeclaracion', 'dec_HoraDeclaracion',
        'dec_FechaCreador', 'dec_FechaModificador', 'dec_Modificador',
        // La opcion de uso de una correccion es "Corrección" (3); se fija abajo.
        // Se copiaba el 1 de la original, y la pantalla la mostraba como
        // "Declaración Inicial".
        'dec_OpcionUso'
    ];

    $columnas = [];
    $valores  = [];

    foreach ($orig as $col => $val) {
        if (in_array($col, $excluidas, true)) { continue; }
        /*
         * NI LA SESION DE PAGO EN LINEA (dec_PSE_*). Revision 2026-09-28.
         *
         * Se copiaba el requestId de PlacetoPay de la original, y con el su
         * estado. El cron de respaldo revisa toda declaracion SIN PAGAR que
         * tenga requestId: consultaba la sesion de la ORIGINAL -aprobada- y
         * marcaba pagada la correccion, en borrador y sin un peso de por medio,
         * con el valor de la original. Y si la original tenia un pago PENDIENTE,
         * la correccion nacia bloqueada en "Pago en proceso". Una correccion no
         * ha pasado por la pasarela: nace sin sesion. Se excluye por prefijo
         * para que una columna PSE nueva tampoco se herede.
         */
        if (strpos($col, 'dec_PSE_') === 0) { continue; }
        if ($val instanceof \DateTime) { $val = $val->format('Y-m-d H:i:s'); }
        $columnas[] = $col;
        $valores[]  = $val;
    }

    // dec_DeclaracionCorrige guarda el NUMERO de la declaracion corregida,
    // que es lo que pide el formulario (no el id interno).
    $columnas[] = 'dec_DeclaracionCorrige';
    $valores[]  = $orig['dec_NumeroDeclaracion'] ?: $orig['dec_Id'];

    $columnas[] = 'dec_OpcionUso';
    $valores[]  = '3';

    date_default_timezone_set('America/Bogota');

    $columnas[] = 'dec_FechaDeclaracion';
    $valores[]  = date('Y-m-d');

    $columnas[] = 'dec_HoraDeclaracion';
    $valores[]  = date('H:i:s');

    $columnas[] = 'dec_FechaCreador';
    $valores[]  = date('Y-m-d H:i:s');

    $listaCols = implode(', ', $columnas);
    $marcas    = implode(', ', array_fill(0, count($columnas), '?'));

    /*
     * El id se pide en el MISMO lote del INSERT, con SCOPE_IDENTITY().
     *
     * Antes se releia con "SELECT TOP 1 ... WHERE dec_DeclaracionCorrige = ?
     * ORDER BY dec_Id DESC", y esa clave no es unica: si dos sesiones corrigen
     * a la vez, las dos leen el dec_Id mas alto y las dos escriben su
     * consecutivo sobre esa misma fila. Una correccion se queda sin numero de
     * formulario -y una declaracion sin numero no se puede pagar en banco,
     * porque el numero es lo que viaja dentro del codigo de barras de recaudo-
     * mientras la otra recibe dos numeros seguidos.
     *
     * SCOPE_IDENTITY() devuelve la identidad generada por ESTA sesion y en este
     * ambito, asi que no puede confundirse con la fila de otra. No sirve
     * @@IDENTITY, que cruzaria a un trigger.
     *
     * SET NOCOUNT ON NO ES OPCIONAL AQUI.
     *
     * Sin el, el INSERT emite su recuento de filas como PRIMER resultado del
     * lote, y obnerFila() lee ese, no el SELECT. Medido: devolvia dec_Id nulo,
     * y con el id nulo se saltaban las dos cosas que dependen de el -asignar el
     * consecutivo y copiar las actividades-, asi que la correccion nacia SIN
     * NUMERO y SIN actividades. Silenciar el recuento deja al SELECT como
     * primer resultado.
     */
    $nuevo = $con->obnerFila($con->consultar(
        "SET NOCOUNT ON;
         INSERT INTO ind_declaraciones_ica ($listaCols) VALUES ($marcas);
         SELECT CAST(SCOPE_IDENTITY() AS BIGINT) AS dec_Id;",
        $valores
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
        $numeroNuevo = $this->_siguienteNumeroDeclaracion($con);
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
    // Ahora el aviso dice la verdad: solo se redirige cuando la pulsada SI
    // tiene una correccion presentada, y se nombra cual.
    $this->_mensaje = "Declaración de corrección creada. "
                    . "Corrige la N° " . ($orig['dec_NumeroDeclaracion'] ?: $orig['dec_Id'])
                    . ($seRedirigio
                        ? ', que es la vigente: la N° ' . $seleccionada
                          . ' que seleccionó ya había sido corregida.'
                        : '');

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
    /*
     * Las firmas, solo del modulo ICA (ver _firmasIca): la serie de retencion
     * repite numeros y no debe teñir el estado de una ICA.
     *
     * n_actividades: cuantas actividades tiene GUARDADAS. Sin ninguna, la
     * declaracion no se firma ni se presenta (revision 2026-09-28), y la
     * pantalla deja de ofrecer esos botones en vez de dejar que reboten.
     */
    $sql = "
        SELECT d.*,
               CASE WHEN fd.fd_Id IS NOT NULL THEN 1 ELSE 0 END AS is_signed,
               CASE WHEN fc.fd_Id IS NOT NULL THEN 1 ELSE 0 END AS is_signed_contador,
               CASE WHEN LTRIM(RTRIM(ISNULL(c.ind_EmailContador,''))) <> ''
                      OR LTRIM(RTRIM(ISNULL(c.ind_EmailRevisor,'')))  <> ''
                    THEN 1 ELSE 0 END AS tiene_correo_contador,
               (SELECT COUNT(*) FROM ind_declaraciones_ica_actividades a
                 WHERE a.dia_IdDeclaracion = d.dec_Id) AS n_actividades,
               c.ind_Persona
        FROM ind_declaraciones_ica d
        LEFT JOIN firmas_declaraciones fd
               ON fd.fd_NumeroDeclaracion = CAST(d.dec_Id AS VARCHAR)
              AND fd.fd_Rol = 'declarante'
              AND fd.fd_Modulo = 'ICA'
        LEFT JOIN firmas_declaraciones fc
               ON fc.fd_NumeroDeclaracion = CAST(d.dec_Id AS VARCHAR)
              AND fc.fd_Rol = 'contador'
              AND fc.fd_Modulo = 'ICA'
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
    include_once SERVER . '/business/class.pasarela.php';
    // botonVisible (no solo configurado): durante la certificacion el boton solo
    // lo ven los usuarios de prueba (parametro PASARELA_USUARIOS_PRUEBA). La
    // pasarela es la de la entidad (042): AvalPay en Paipa, Wompi en Macanal.
    $pagoEnLinea      = (int) \erpsoftsas\Pasarela::botonVisible($_SESSION['id_usuario'] ?? null);
    $pagoEnLineaTexto = \erpsoftsas\Pasarela::textoBoton();

    $data = [];

    while($row = $con->obnerFila($res)){
        // El frontend necesita saber, SIN otra llamada, si a esta
        // declaracion le hace falta la firma del contador para poder
        // presentarse. Misma regla que _requiereContador(): tiene correo de
        // contador o de revisor registrado -tiene_correo_contador ya trae
        // ese calculo hecho desde el SQL de arriba-.
        $row['requiere_contador'] = (int) ($row['tiene_correo_contador'] ?? 0);

        $row['pago_en_linea'] = $pagoEnLinea;
        $row['pago_en_linea_texto'] = $pagoEnLineaTexto;

        $data[] = $row;
    }

    // Lo registrado a mano por la Alcaldía (migración 044): declaración en papel
    // ya pagada, pago manual y sus PDF. Una consulta por tabla para todo el listado.
    include_once SERVER . '/business/class.registroManual.php';
    $manual = \erpsoftsas\RegistroManual::resumenes($con, 'ica', array_column($data, 'dec_Id'));
    foreach ($data as &$fila) {
        $fila['registro_manual'] = $manual[(int) $fila['dec_Id']] ?? null;
    }
    unset($fila);

    $this->_ok = count($data) ? 1 : 0;
    $this->_mensaje = "Filtrado correctamente";

    return $data;
}


}


class DeclaracionesICAException extends \Exception {}


\erpsoftsas\ControladorDeclaracionesICA::run();