<?php
namespace erpsoftsas;

/*
 * ============================================================================
 * EL MOTOR COMPARTIDO DE RETEICA Y AUTORRETEICA
 * ============================================================================
 *
 * Son dos modulos distintos -retencion mensual y autorretencion bimestral- con
 * formularios distintos, pero el ciclo de vida es EL MISMO: crear borrador,
 * capturar actividades, liquidar, presentar, corregir. Aqui vive ese ciclo una
 * sola vez y cada modulo solo declara en que se diferencia.
 *
 * POR QUE NO DOS CONTROLADORES COPIADOS
 *
 * Porque el proyecto ya tiene esa herida: declaracion.php y liquidacion.php son
 * dos generadores de PDF casi iguales, y varias veces se arreglo un bug en uno
 * y el otro siguio roto -esta escrito en CLAUDE.md-. Lo mismo paso con las
 * funciones de cifras copiadas en tres JS, que costaron dos bugs de produccion
 * hasta que se centralizaron en core/numeros.js.
 *
 * NO SE USA EL DAO. A PROPOSITO.
 *
 * business/DAO/class.DAO.php arma sus consultas concatenando strings, y mete la
 * clave primaria en el WHERE sin comillas siquiera. Todo lo de aqui usa
 * consultar() con parametros (?), que si parametriza de verdad. Esa es la razon
 * de que estos dos modulos no tengan clase DAO propia: no hace falta, y tenerla
 * seria heredar el problema.
 *
 * LAS FORMULAS NO ESTAN EN ESTE ARCHIVO
 *
 * Viven en ind_renglones_retencion (migracion 030), una fila por casilla. El
 * motor recorre el catalogo e inyecta la expresion de cada renglon que TENGA
 * formula. Un renglon con formula NULL no se toca.
 *
 * Eso no es una carencia, es el diseño: al 2026-09-08 hay cuatro casillas de
 * autorretencion cuyo calculo los tres documentos del cliente describen de tres
 * maneras distintas -y una de ellas cobra casi el doble-. Quedan en NULL hasta
 * que el cliente confirme, y el dia que se confirmen se llenan CUATRO FILAS de
 * la tabla; este archivo no se toca. Es el mismo mecanismo que ya usa el ICA
 * desde la migracion 010.
 *
 * Lo que no lleva formula sale en 0, que es visible y revisable. Lo contrario
 * -inventar una formula plausible- no daria ningun error: daria una liquidacion
 * equivocada, firmada por el contribuyente y con un codigo de barras pagable.
 * ============================================================================
 */

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER . '/business/controller/class.cabecera.php';

abstract class ControladorRetencion extends \erpsoftsas\Cabecera
{
    /* ---------------------------------------------------------------------
     * Lo que cada modulo declara sobre si mismo.
     * Se llena en el constructor de la clase hija; nada mas los distingue.
     * ------------------------------------------------------------------- */

    /** 'RETEICA' o 'AUTORRETEICA'. Es la llave del catalogo de renglones. */
    protected $modulo;

    /** Nombre de la tabla principal y prefijo de TODAS sus columnas. */
    protected $tabla;
    protected $p;

    /** Tabla de filas de actividad, su prefijo y la columna que apunta arriba. */
    protected $tablaAct;
    protected $pa;
    protected $fkAct;

    /** Las dos columnas de la fila de actividad que cambian de nombre entre
     *  modulos: lo que escribe el contribuyente y lo que calcula el sistema. */
    protected $colBase;
    protected $colValor;

    /** 12 en retencion (mensual), 6 en autorretencion (bimestral). */
    protected $periodoMax;

    /** Como se le llama al periodo cuando hay que escribirle al usuario. */
    protected $nombrePeriodo = 'periodo';

    /* Respuesta estandar de los controladores del proyecto. */
    protected $_ok = 0;
    protected $_mensaje = '';

    /* ---------------------------------------------------------------------
     * Helpers de nombres de columna. Existen para que el resto del archivo se
     * lea como SQL y no como concatenacion.
     * ------------------------------------------------------------------- */

    /** Columna de la tabla principal: c('Estado') -> 'ret_Estado'. */
    protected function c($sufijo) { return $this->p . $sufijo; }

    /** Columna de la tabla de actividades. */
    protected function ca($sufijo) { return $this->pa . $sufijo; }

    /** La clave primaria de la tabla principal. */
    protected function pk() { return $this->p . 'Id'; }


    /* =====================================================================
     * CONTROL DE ACCESO
     *
     * Copiado en intencion -no en letra- de class.declaracionesICA.php, donde
     * se descubrio el 2026-08-31 que sin filtro de contribuyente una sola
     * peticion devolvia 200 filas de seis contribuyentes distintos con sus
     * ingresos y su impuesto. Sobre datos con reserva tributaria eso no es un
     * detalle.
     *
     * La leccion que se aplica aqui desde el primer dia: el filtro por
     * contribuyente NO se toma de la peticion. Se FIJA. Si se leyera de
     * $_POST, omitir la clave seria la forma de saltarse la guarda.
     * ===================================================================== */

    /** El contribuyente atado al usuario de la sesion, o null. */
    protected function _contribuyenteDeLaSesion($con)
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
     * Es funcionario de la Alcaldia (roles 1 y 2): ve todo, como en el ICA.
     */
    protected function _esFuncionario()
    {
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }
        $rol = isset($_SESSION['id_Rol']) ? (int) $_SESSION['id_Rol'] : 0;
        return in_array($rol, [1, 2], true);
    }

    /**
     * Devuelve el contribuyente sobre el que puede operar esta peticion:
     * el propio para un externo, o el que pida el funcionario.
     *
     * Devuelve null y deja el mensaje puesto si no se puede establecer.
     */
    protected function _contribuyenteAutorizado($con)
    {
        if ($this->_esFuncionario()) {
            $pedido = isset($_POST['idContribuyente']) ? (int) $_POST['idContribuyente'] : 0;
            return $pedido > 0 ? $pedido : null;
        }

        $propio = $this->_contribuyenteDeLaSesion($con);
        if (!$propio) {
            $this->_ok = 0;
            $this->_mensaje = 'No se pudo establecer a qué contribuyente corresponde la sesión.';
            return null;
        }
        return $propio;
    }

    /**
     * Trae la fila SOLO si el usuario tiene derecho a verla.
     *
     * Es el unico camino por el que el resto del archivo llega a una
     * declaracion: ninguna funcion recibe un id y opera sobre el a ciegas.
     */
    protected function _filaAutorizada($con, $id)
    {
        $id = (int) $id;
        if ($id <= 0) { return null; }

        $sql = "SELECT * FROM {$this->tabla} WHERE {$this->pk()} = ?";
        $params = [$id];

        if (!$this->_esFuncionario()) {
            $propio = $this->_contribuyenteDeLaSesion($con);
            if (!$propio) { return null; }
            $sql .= " AND {$this->c('IdContribuyente')} = ?";
            $params[] = $propio;
        }

        $fila = $con->obnerFila($con->consultar($sql, $params));
        return $fila ? $fila : null;
    }

    /** Borrador es estado NULL, 0 o 1; presentada es 2. Mismo vocabulario que el ICA. */
    protected function _esBorrador($fila)
    {
        $e = $fila[$this->c('Estado')];
        return ($e === null || (int) $e !== 2);
    }


    /* =====================================================================
     * EL NUMERO DE FORMULARIO
     * ===================================================================== */

    /**
     * Pide el siguiente consecutivo del modulo para el año dado.
     *
     * Lo reparte sp_siguiente_numero_retencion (migracion 030), que incrementa
     * y captura en la MISMA sentencia: dos contribuyentes declarando a la vez
     * no pueden llevarse el mismo numero. Un SELECT MAX + 1 si lo permitiria.
     *
     * Si el procedimiento no existe -base sin migrar- devuelve null y quien
     * llama cae al id de la fila, igual que hacia el ICA antes de la 012. Una
     * instalacion sin migrar sigue funcionando en vez de romperse.
     */
    protected function _siguienteNumero($con, $anio)
    {
        try {
            $fila = $con->obnerFila($con->consultar(
                "DECLARE @n BIGINT;
                 EXEC dbo.sp_siguiente_numero_retencion @TIPO = ?, @ANIO = ?, @NUMERO = @n OUTPUT;
                 SELECT @n AS numero;",
                [$this->modulo, (int) $anio]
            ));

            $n = isset($fila['numero']) ? (float) $fila['numero'] : 0;
            return ($n > 0) ? $n : null;

        } catch (\Throwable $e) {
            error_log('[' . $this->modulo . '] sin consecutivo: ' . $e->getMessage());
            return null;
        }
    }


    /* =====================================================================
     * LIQUIDACION
     * ===================================================================== */

    /**
     * Recalcula la declaracion entera: primero las filas de actividad, despues
     * los renglones que tengan formula en el catalogo.
     *
     * SOBRE INYECTAR ren_Formula EN EL SQL
     *
     * Va concatenada, no parametrizada, porque es una EXPRESION y no un valor
     * -no existe forma de parametrizar "a + b"-. Es aceptable porque el
     * catalogo no lo escribe el contribuyente: son filas que carga una
     * migracion o un funcionario de la Alcaldia. Es exactamente lo que ya hace
     * el ICA con con_Observaciones. Lo que NO se debe hacer nunca es dejar que
     * una formula llegue desde una pantalla publica.
     *
     * EL ORDEN IMPORTA. Los renglones se aplican por ren_Orden y cada UPDATE ve
     * el resultado del anterior, que es como el total a pagar puede sumar
     * casillas calculadas antes que el.
     *
     * QUE NO HACE: redondear. Los dos formularios nuevos redondean a miles fila
     * por fila y el ICA redondea una sola vez sobre el total; son resultados
     * distintos sobre los mismos datos y esa decision esta pendiente. Se guarda
     * el valor exacto, que es el unico del que se puede derivar cualquiera de
     * los dos despues.
     */
    protected function _liquidar($con, $id)
    {
        $id = (int) $id;

        /*
         * 1. Las filas de actividad: base x tarifa, REDONDEADO A MILES.
         *
         * La tarifa esta guardada en FRACCION (0.004). El formulario la imprime
         * por mil (4). Aqui se multiplica directo, sin dividir por 1000:
         * confundir las dos unidades no da error, da una liquidacion mil veces
         * mayor o menor.
         *
         * EL REDONDEO ES FILA POR FILA, y lo piden los dos formularios en sus
         * propias formulas -leidas de los archivos del cliente-:
         *
         *     RETEICA        O16 = MROUND((I16*K16/1000),1000)
         *     AUTORRETEICA   P19 = MROUND((M19*J19/1000),1000)
         *
         * No es lo mismo que hace el ICA, que redondea UNA vez sobre el total.
         * Son formularios distintos y cada uno manda en el suyo; el ICA no se
         * toca. ROUND() de SQL Server redondea la mitad alejandose del cero,
         * igual que MROUND de Excel, asi que 823.500 da 824.000 en los dos.
         */
        $con->consultar(
            "UPDATE {$this->tablaAct}
                SET {$this->ca($this->colValor)} =
                    ROUND(ISNULL({$this->ca($this->colBase)},0) * ISNULL({$this->ca('Tarifa')},0) / 1000.0, 0) * 1000
              WHERE {$this->fkAct} = ?",
            [$id]
        );

        /*
         * 2. Los renglones con formula, en orden.
         */
        $anio = $this->_anioDe($con, $id);

        $stmt = $con->consultar(
            "SELECT ren_Codigo, ren_Formula
               FROM ind_renglones_retencion
              WHERE ren_Modulo = ? AND ren_Anio = ? AND ren_Estado = 1
                AND ren_Formula IS NOT NULL
              ORDER BY ren_Orden",
            [$this->modulo, $anio]
        );

        $pendientes = [];
        while ($r = $con->obnerFila($stmt)) { $pendientes[] = $r; }

        $aplicados = 0;
        foreach ($pendientes as $r) {

            $columna = $this->c('ValorConcepto' . (int) $r['ren_Codigo']);

            /*
             * Guarda de cordura: la formula referencia columnas por nombre, y
             * una columna inexistente tumbaria el UPDATE entero -y con el, el
             * guardado-. Si el catalogo trae un codigo sin columna, se salta y
             * se anota, en vez de dejar al usuario con una pantalla muda.
             */
            if (!$this->_existeColumna($con, $this->tabla, $columna)) {
                error_log('[' . $this->modulo . '] renglon ' . $r['ren_Codigo'] . ' sin columna ' . $columna);
                continue;
            }

            try {
                $con->consultar(
                    "UPDATE ep SET ep.{$columna} = {$r['ren_Formula']}
                       FROM {$this->tabla} ep
                      WHERE ep.{$this->pk()} = ?",
                    [$id]
                );
                $aplicados++;
            } catch (\Throwable $e) {
                error_log('[' . $this->modulo . '] formula del renglon '
                        . $r['ren_Codigo'] . ' fallo: ' . $e->getMessage());
            }
        }

        return $aplicados;
    }

    protected function _anioDe($con, $id)
    {
        $f = $con->obnerFila($con->consultar(
            "SELECT {$this->c('Anio')} AS anio FROM {$this->tabla} WHERE {$this->pk()} = ?",
            [(int) $id]
        ));
        return isset($f['anio']) ? (int) $f['anio'] : (int) date('Y');
    }

    protected function _existeColumna($con, $tabla, $columna)
    {
        $f = $con->obnerFila($con->consultar(
            "SELECT COL_LENGTH(?, ?) AS existe", [$tabla, $columna]
        ));
        return isset($f['existe']) && $f['existe'] !== null;
    }


    /* =====================================================================
     * FUNCION 1 - Crear
     * ===================================================================== */

    /**
     * Crea el borrador del periodo que se pida.
     *
     * A DIFERENCIA DEL ICA, AQUI EL PERIODO SI SE ELIGE, y no puede ser de otro
     * modo: el ICA es uno al año, pero de retencion hay doce al año y de
     * autorretencion seis. Sin poder decir cual, un contribuyente no podria
     * declarar el mes pasado, que es justo lo que se hace siempre. El año se
     * ofrece por defecto pero tambien se puede cambiar, porque estas
     * declaraciones se presentan vencido el periodo y en enero toca declarar
     * diciembre del año anterior.
     */
    protected function _crear()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $idContribuyente = $this->_contribuyenteAutorizado($con);
        if (!$idContribuyente) {
            if ($this->_mensaje === '') { $this->_mensaje = 'Contribuyente requerido'; }
            return [];
        }

        $anio    = isset($_POST['anio'])    ? (int) $_POST['anio']    : (int) date('Y');
        $periodo = isset($_POST['periodo']) ? (int) $_POST['periodo'] : 0;

        if ($periodo < 1 || $periodo > $this->periodoMax) {
            $this->_ok = 0;
            $this->_mensaje = 'Debe indicar el ' . $this->nombrePeriodo
                            . ' a declarar (1 a ' . $this->periodoMax . ').';
            return [];
        }

        if ($anio < 2000 || $anio > ((int) date('Y') + 1)) {
            $this->_ok = 0;
            $this->_mensaje = 'El año de la declaración no es válido.';
            return [];
        }

        /*
         * SI YA HAY UN BORRADOR DE ESE PERIODO, SE ABRE ESE.
         *
         * En el ICA el cliente pidio quitar este freno -«nada de presentadas,
         * que salga 200 y 201»- y el efecto conocido es que se acumulan
         * borradores del mismo periodo. Aqui se conserva porque el riesgo no es
         * el mismo: con doce periodos al año, un contribuyente que pulse
         * "Crear" dos veces sobre el mismo mes se queda con dos borradores que
         * no sabe distinguir. Reabrir el suyo es lo que espera.
         *
         * No frena las PRESENTADAS: sobre una presentada la via es Corregir, y
         * eso lo decide el usuario desde la pantalla, no este metodo.
         */
        $existente = $con->obnerFila($con->consultar(
            "SELECT TOP 1 * FROM {$this->tabla}
              WHERE {$this->c('IdContribuyente')} = ?
                AND {$this->c('Anio')} = ?
                AND {$this->c('Periodo')} = ?
                AND ({$this->c('Estado')} IS NULL OR {$this->c('Estado')} <> 2)
                AND {$this->c('Corrige')} IS NULL
              ORDER BY {$this->pk()} DESC",
            [$idContribuyente, $anio, $periodo]
        ));

        if ($existente) {
            $this->_ok = 1;
            $this->_mensaje = 'Se abrió el borrador que ya existía para este '
                            . $this->nombrePeriodo . '.';
            return [
                'id'     => (int) $existente[$this->pk()],
                'numero' => $existente[$this->c('NumeroDeclaracion')],
                'nueva'  => 0,
            ];
        }

        date_default_timezone_set('America/Bogota');

        try {
            /*
             * SET NOCOUNT ON es OBLIGATORIO antes de un INSERT seguido de
             * SCOPE_IDENTITY en el mismo lote: sin el, el primer resultado que
             * devuelve sqlsrv es el conteo de filas y el id vuelve nulo. Ya
             * costo un rato el 2026-09-01, creando correcciones sin numero y
             * sin actividades.
             */
            $nuevo = $con->obnerFila($con->consultar(
                "SET NOCOUNT ON;
                 INSERT INTO {$this->tabla}
                        ({$this->c('IdContribuyente')}, {$this->c('Anio')}, {$this->c('Periodo')},
                         {$this->c('FechaDeclaracion')}, {$this->c('HoraDeclaracion')},
                         {$this->c('FechaCreador')}, {$this->c('Creador')})
                 VALUES (?, ?, ?, ?, ?, GETDATE(), ?);
                 SELECT CAST(SCOPE_IDENTITY() AS BIGINT) AS id;",
                [
                    $idContribuyente, $anio, $periodo,
                    date('Y-m-d'), date('H:i:s'),
                    isset($_SESSION['id_usuario']) ? (int) $_SESSION['id_usuario'] : null,
                ]
            ));
        } catch (\Throwable $e) {
            error_log('[' . $this->modulo . '] no se pudo crear: ' . $e->getMessage());
            $this->_ok = 0;
            $this->_mensaje = 'No se pudo crear la declaración. Intente de nuevo; '
                            . 'si persiste, avise a la Alcaldía indicando la hora.';
            return [];
        }

        $id = isset($nuevo['id']) ? (int) $nuevo['id'] : 0;
        if ($id <= 0) {
            $this->_ok = 0;
            $this->_mensaje = 'No se pudo crear la declaración.';
            return [];
        }

        $numero = $this->_siguienteNumero($con, $anio);
        if ($numero === null) {
            $numero = $id;
            error_log('[' . $this->modulo . '] sin consecutivo; se usa el id ' . $id);
        }

        $con->consultar(
            "UPDATE {$this->tabla} SET {$this->c('NumeroDeclaracion')} = ? WHERE {$this->pk()} = ?",
            [$numero, $id]
        );

        // Cada modulo decide con que filas de actividad nace la declaracion.
        $this->_sembrarActividades($con, $id, $idContribuyente, $anio);

        $this->_ok = 1;
        $this->_mensaje = "Declaración creada correctamente, N° $numero";

        return ['id' => $id, 'numero' => $numero, 'nueva' => 1];
    }

    /**
     * Con que actividades nace una declaracion nueva.
     *
     * Es la diferencia real entre los dos modulos y por eso es abstracta:
     * en retencion el contribuyente elige las actividades de quienes le
     * retuvieron, asi que nace VACIA; en autorretencion se declara sobre las
     * actividades propias, que ya estan en el RIT, asi que nacen precargadas.
     */
    abstract protected function _sembrarActividades($con, $id, $idContribuyente, $anio);


    /* =====================================================================
     * FUNCION 2 - Listado
     * ===================================================================== */

    protected function _listar()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        /*
         * NO HAY COLUMNA DE RAZON SOCIAL. ind_contribuyentes guarda el nombre
         * en los cuatro campos de persona natural, y para una persona juridica
         * la razon social va en ind_PrimerNombre. Es la misma trampa que hacia
         * salir el nombre en blanco en el RIT de las juridicas.
         */
        $sql = "SELECT d.*,
                       c.ind_NumeroIdentificacion AS documento,
                       c.ind_PrimerNombre, c.ind_SegundoNombre,
                       c.ind_PrimerApellido, c.ind_SegundoApellido
                  FROM {$this->tabla} d
                  LEFT JOIN ind_contribuyentes c ON c.ind_Id = d.{$this->c('IdContribuyente')}
                 WHERE 1 = 1";
        $params = [];

        /*
         * El filtro por contribuyente se fija SIEMPRE para un usuario externo,
         * venga o no venga en la peticion. Omitirlo era, en el modulo de ICA,
         * la forma de que una sola llamada devolviera la tabla entera.
         */
        if (!$this->_esFuncionario()) {
            $propio = $this->_contribuyenteDeLaSesion($con);
            if (!$propio) {
                $this->_ok = 0;
                $this->_mensaje = 'No se pudo establecer a qué contribuyente corresponde la sesión.';
                return [];
            }
            $sql .= " AND d.{$this->c('IdContribuyente')} = ?";
            $params[] = $propio;

        } elseif (!empty($_POST['idContribuyente'])) {
            $sql .= " AND d.{$this->c('IdContribuyente')} = ?";
            $params[] = (int) $_POST['idContribuyente'];
        }

        if (!empty($_POST['anio'])) {
            $sql .= " AND d.{$this->c('Anio')} = ?";
            $params[] = (int) $_POST['anio'];
        }

        if (!empty($_POST['periodo'])) {
            $sql .= " AND d.{$this->c('Periodo')} = ?";
            $params[] = (int) $_POST['periodo'];
        }

        $sql .= " ORDER BY d.{$this->c('Anio')} DESC, d.{$this->c('Periodo')} DESC, d.{$this->pk()} DESC";

        $stmt = $con->consultar($sql, $params);

        $datos = [];
        while ($f = $con->obnerFila($stmt)) {
            $datos[] = $this->_filaParaPantalla($f);
        }

        $this->_ok = 1;
        $this->_mensaje = count($datos) ? 'Consulta correcta' : 'No hay declaraciones';
        return $datos;
    }

    /**
     * Normaliza una fila para la pantalla: nombres cortos y estado ya resuelto,
     * para que el JS no tenga que conocer los prefijos de columna de cada
     * modulo ni repetir la logica de estados.
     */
    protected function _filaParaPantalla($f)
    {
        $estado = $f[$this->c('Estado')];
        $estado = ($estado === null) ? null : (int) $estado;

        $pagado = !empty($f[$this->c('Pagado')]);

        // "Pagada" exige las DOS condiciones. Un pago sobre algo no presentado
        // es un estado imposible que ya rompio "Corregir" en el ICA.
        if ($pagado && $estado === 2)      { $clave = 'pagada'; }
        elseif ($estado === 2)             { $clave = 'presentada'; }
        elseif ($estado === 1)             { $clave = 'firmada'; }
        else                               { $clave = 'borrador'; }

        $razon = trim((string) (isset($f['razon']) ? $f['razon'] : ''));
        if ($razon === '') {
            $razon = trim(implode(' ', array_filter([
                isset($f['ind_PrimerNombre'])    ? $f['ind_PrimerNombre']    : '',
                isset($f['ind_SegundoNombre'])   ? $f['ind_SegundoNombre']   : '',
                isset($f['ind_PrimerApellido'])  ? $f['ind_PrimerApellido']  : '',
                isset($f['ind_SegundoApellido']) ? $f['ind_SegundoApellido'] : '',
            ])));
        }

        $colTotal = $this->c('ValorConcepto' . $this->renglonTotal());

        return [
            'id'          => (int) $f[$this->pk()],
            'numero'      => $f[$this->c('NumeroDeclaracion')],
            'anio'        => (int) $f[$this->c('Anio')],
            'periodo'     => (int) $f[$this->c('Periodo')],
            'fecha'       => $this->_fecha($f[$this->c('FechaDeclaracion')]),
            'presentada'  => $this->_fecha($f[$this->c('FechaPresentacion')]),
            'corrige'     => $f[$this->c('Corrige')],
            'estado'      => $estado,
            'estadoClave' => $clave,
            'pagado'      => $pagado ? 1 : 0,
            'pago_en_linea' => $this->_pagoEnLinea(),
            'total'       => isset($f[$colTotal]) ? (float) $f[$colTotal] : 0,
            'documento'   => isset($f['documento']) ? $f['documento'] : '',
            'razon'       => $razon,
        ];
    }

    /**
     * ¿Se ofrece el boton "Pagar PSE" a este usuario? Igual que en el ICA:
     * depende de que el convenio de recaudo este configurado y, en modo
     * certificacion, de que el usuario este en PASARELA_USUARIOS_PRUEBA. Se
     * resuelve una sola vez por peticion (mismo dato para todas las filas).
     */
    private $_pagoEnLineaCache = null;
    protected function _pagoEnLinea()
    {
        if ($this->_pagoEnLineaCache === null) {
            require_once __DIR__ . '/class.placetopay.php';
            $this->_pagoEnLineaCache = (int) \PlacetoPay::botonVisible($_SESSION['id_usuario'] ?? null);
        }
        return $this->_pagoEnLineaCache;
    }

    /** La casilla "TOTAL A PAGAR" de cada formulario: 17 en retencion, 23 en autorretencion. */
    abstract protected function renglonTotal();

    protected function _fecha($v)
    {
        if (empty($v)) { return ''; }
        if ($v instanceof \DateTime) { return $v->format('Y-m-d'); }
        return substr((string) $v, 0, 10);
    }


    /* =====================================================================
     * FUNCION 3 - Abrir una declaracion
     * ===================================================================== */

    protected function _abrir()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $fila = $this->_filaAutorizada($con, isset($_POST['id']) ? $_POST['id'] : 0);
        if ($fila === null) {
            $this->_ok = 0;
            $this->_mensaje = 'No tiene permiso sobre esta declaración.';
            return [];
        }

        $id   = (int) $fila[$this->pk()];
        $anio = (int) $fila[$this->c('Anio')];

        /* Los renglones: valor guardado + si lo escribe el usuario o lo calcula
           el sistema. La pantalla no tiene que saberse las casillas de memoria. */
        $renglones = [];
        $stmt = $con->consultar(
            "SELECT ren_Codigo, ren_Nombre, ren_Manual, ren_Formula, ren_Orden
               FROM ind_renglones_retencion
              WHERE ren_Modulo = ? AND ren_Anio = ? AND ren_Estado = 1
              ORDER BY ren_Orden",
            [$this->modulo, $anio]
        );
        while ($r = $con->obnerFila($stmt)) {
            $codigo  = (int) $r['ren_Codigo'];
            $columna = $this->c('ValorConcepto' . $codigo);
            $renglones[] = [
                'codigo'    => $codigo,
                'nombre'    => $r['ren_Nombre'],
                'manual'    => (int) $r['ren_Manual'],
                // Un renglon calculado SIN formula todavia no se puede liquidar:
                // la pantalla lo muestra en 0 y advierte, en vez de fingir.
                'pendiente' => ($r['ren_Manual'] ? 0 : ($r['ren_Formula'] === null ? 1 : 0)),
                'valor'     => isset($fila[$columna]) ? (float) $fila[$columna] : 0,
            ];
        }

        // El encabezado del formulario sale del RIT y no se edita.
        $contribuyente = $con->obnerFila($con->consultar(
            "SELECT ind_Id, ind_NumeroIdentificacion, ind_Persona,
                    ind_PrimerNombre, ind_SegundoNombre, ind_PrimerApellido, ind_SegundoApellido,
                    ind_Direccion, ind_Telefono, ind_Email
               FROM ind_contribuyentes WHERE ind_Id = ?",
            [(int) $fila[$this->c('IdContribuyente')]]
        ));

        $paraPantalla = $this->_filaParaPantalla(array_merge(
            $fila,
            $contribuyente
                ? array_merge($contribuyente, ['documento' => $contribuyente['ind_NumeroIdentificacion']])
                : ['documento' => '']
        ));

        $this->_ok = 1;
        $this->_mensaje = 'Consulta correcta';

        return [
            'declaracion'   => $paraPantalla,
            'contribuyente' => $contribuyente ? $contribuyente : [],
            'editable'      => $this->_esBorrador($fila) ? 1 : 0,
            'renglones'     => $renglones,
            'actividades'   => $this->_actividadesDe($con, $id),
            'extra'         => $this->_datosExtra($con, $fila),
        ];
    }

    /** Cada modulo añade lo suyo. */
    protected function _datosExtra($con, $fila) { return []; }

    protected function _actividadesDe($con, $id)
    {
        $stmt = $con->consultar(
            "SELECT a.*, ac.acc_Codigo, ac.acc_Nombre
               FROM {$this->tablaAct} a
               LEFT JOIN ind_actividadescomercio ac ON ac.acc_Id = a.{$this->ca('IdActividad')}
              WHERE a.{$this->fkAct} = ? AND a.{$this->ca('Activo')} = 1
              ORDER BY a.{$this->pa}Id",
            [(int) $id]
        );

        $datos = [];
        while ($f = $con->obnerFila($stmt)) {
            $datos[] = [
                'id'          => (int) $f[$this->pa . 'Id'],
                'idActividad' => (int) $f[$this->ca('IdActividad')],
                'codigo'      => $f['acc_Codigo'],
                'descripcion' => $f['acc_Nombre'],
                'base'        => (float) $f[$this->ca($this->colBase)],
                'tarifa'      => (float) $f[$this->ca('Tarifa')],
                'valor'       => (float) $f[$this->ca($this->colValor)],
            ];
        }
        return $datos;
    }


    /* =====================================================================
     * FUNCION 4 - Guardar y liquidar
     * ===================================================================== */

    /**
     * Guarda lo que escribio el usuario y recalcula.
     *
     * "Liquidar" y "Guardar" son el MISMO camino a proposito. En el ICA son
     * dos -uno calcula sin guardar y otro guarda-, y esa duplicidad ya produjo
     * el caso de dos fuentes de verdad discrepando en pantalla. Como esto solo
     * corre sobre BORRADORES, guardar antes de mostrar no tiene coste: nada de
     * lo que se pisa estaba cerrado.
     */
    protected function _guardar()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $fila = $this->_filaAutorizada($con, isset($_POST['id']) ? $_POST['id'] : 0);
        if ($fila === null) {
            $this->_ok = 0;
            $this->_mensaje = 'No tiene permiso sobre esta declaración.';
            return [];
        }

        if (!$this->_esBorrador($fila)) {
            $this->_ok = 0;
            $this->_mensaje = 'Una declaración presentada no se edita. Use "Corregir".';
            return [];
        }

        $id = (int) $fila[$this->pk()];

        /* 1. Los renglones que escribe el usuario.
              SOLO los marcados manuales en el catalogo: si se aceptara
              cualquier casilla que llegue en el POST, un cliente podria
              escribir directamente el total a pagar. */
        $manuales = [];
        $stmt = $con->consultar(
            "SELECT ren_Codigo FROM ind_renglones_retencion
              WHERE ren_Modulo = ? AND ren_Anio = ? AND ren_Estado = 1 AND ren_Manual = 1",
            [$this->modulo, (int) $fila[$this->c('Anio')]]
        );
        while ($r = $con->obnerFila($stmt)) { $manuales[] = (int) $r['ren_Codigo']; }

        $entrantes = (isset($_POST['renglones']) && is_array($_POST['renglones']))
                   ? $_POST['renglones'] : [];

        foreach ($manuales as $codigo) {
            if (!array_key_exists($codigo, $entrantes)) { continue; }
            $columna = $this->c('ValorConcepto' . $codigo);
            if (!$this->_existeColumna($con, $this->tabla, $columna)) { continue; }
            $con->consultar(
                "UPDATE {$this->tabla} SET {$columna} = ? WHERE {$this->pk()} = ?",
                [$this->_cifra($entrantes[$codigo]), $id]
            );
        }

        /* 2. Las filas de actividad. */
        $this->_guardarActividades(
            $con, $id,
            (isset($_POST['actividades']) && is_array($_POST['actividades'])) ? $_POST['actividades'] : []
        );

        /* 3. Recalcular. */
        $this->_liquidar($con, $id);

        $_POST['id'] = $id;
        $respuesta = $this->_abrir();

        $this->_ok = 1;
        $this->_mensaje = 'Declaración guardada y liquidada';
        return $respuesta;
    }

    /**
     * Reemplaza las filas de actividad por las que manda la pantalla.
     *
     * BORRAR E INSERTAR, no actualizar: la pantalla permite añadir y quitar
     * filas, y casar cual es cual con la que ya estaba es donde se cometen los
     * errores. Con la declaracion cerrada al presentarse, perder los ids de las
     * filas de un borrador no le importa a nadie.
     *
     * La TARIFA no se toma de la pantalla: se lee del catalogo aqui. Si viniera
     * del navegador, cualquiera podria declarar con la tarifa que quisiera.
     */
    protected function _guardarActividades($con, $id, array $actividades)
    {
        $con->consultar("DELETE FROM {$this->tablaAct} WHERE {$this->fkAct} = ?", [(int) $id]);

        foreach ($actividades as $a) {

            $idActividad = isset($a['idActividad']) ? (int) $a['idActividad'] : 0;
            if ($idActividad <= 0) { continue; }

            $t = $con->obnerFila($con->consultar(
                "SELECT acc_Tarifa FROM ind_actividadescomercio WHERE acc_Id = ?", [$idActividad]
            ));
            $tarifa = isset($t['acc_Tarifa']) ? (float) $t['acc_Tarifa'] : 0;

            $base = $this->_cifra(isset($a['base']) ? $a['base'] : 0);

            $con->consultar(
                "INSERT INTO {$this->tablaAct}
                        ({$this->fkAct}, {$this->ca('IdActividad')}, {$this->ca($this->colBase)},
                         {$this->ca('Tarifa')}, {$this->ca($this->colValor)}, {$this->ca('FechaCreador')})
                 VALUES (?, ?, ?, ?, ?, GETDATE())",
                [(int) $id, $idActividad, $base, $tarifa, $base * $tarifa]
            );
        }
    }

    /**
     * Convierte a numero lo que venga de la pantalla.
     *
     * El frontend manda cifras ya limpias, pero si alguna llegara con formato
     * colombiano ("2.500.000,50") un cast directo la truncaria a 2. Se
     * normaliza aqui: el punto es separador de miles y la coma el decimal, que
     * es la regla que ya costo dos bugs de produccion en el ICA -documentada en
     * core/numeros.js-.
     */
    protected function _cifra($v)
    {
        if (is_numeric($v)) { return (float) $v; }
        $v = (string) $v;
        $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
        $v = preg_replace('/[^0-9.\-]/', '', $v);
        return $v === '' ? 0.0 : (float) $v;
    }


    /* =====================================================================
     * FUNCION 5 - Descartar borrador
     * ===================================================================== */

    protected function _descartar()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $fila = $this->_filaAutorizada($con, isset($_POST['id']) ? $_POST['id'] : 0);
        if ($fila === null) {
            $this->_ok = 0;
            $this->_mensaje = 'No tiene permiso sobre esta declaración.';
            return [];
        }

        if (!$this->_esBorrador($fila)) {
            $this->_ok = 0;
            $this->_mensaje = 'Una declaración presentada no se puede eliminar.';
            return [];
        }

        $id = (int) $fila[$this->pk()];

        // Las filas hijas primero: hay clave foranea.
        $con->consultar("DELETE FROM {$this->tablaAct} WHERE {$this->fkAct} = ?", [$id]);
        $con->consultar("DELETE FROM {$this->tabla} WHERE {$this->pk()} = ?", [$id]);

        $this->_ok = 1;
        $this->_mensaje = 'Borrador eliminado';
        return ['id' => $id];
    }


    /* =====================================================================
     * FUNCION 6 - Presentar
     * ===================================================================== */

    /**
     * Si el contribuyente tiene contador o revisor registrado, su firma es
     * obligatoria. Quien no registro ninguno presenta con la suya sola.
     *
     * Es la regla que el cliente fijo el 2026-08-11 para el ICA, y reemplazo a
     * la anterior -juridica siempre, natural sobre 3.500 UVT-. No hay motivo
     * para que estos formularios se rijan por otra.
     */
    protected function _requiereContador($con, $idContribuyente)
    {
        $c = $con->obnerFila($con->consultar(
            "SELECT ind_EmailContador, ind_EmailRevisor FROM ind_contribuyentes WHERE ind_Id = ?",
            [(int) $idContribuyente]
        ));

        if (!$c) { return false; }

        return trim((string) $c['ind_EmailContador']) !== ''
            || trim((string) $c['ind_EmailRevisor'])  !== '';
    }

    protected function _presentar()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $fila = $this->_filaAutorizada($con, isset($_POST['id']) ? $_POST['id'] : 0);
        if ($fila === null) {
            $this->_ok = 0;
            $this->_mensaje = 'No tiene permiso sobre esta declaración.';
            return [];
        }

        if (!$this->_esBorrador($fila)) {
            $this->_ok = 0;
            $this->_mensaje = 'Esta declaración ya fue presentada.';
            return [];
        }

        $id = (int) $fila[$this->pk()];

        /*
         * SIN FIRMA NO SE PRESENTA.
         *
         * Mismo criterio que el ICA: hace falta la del declarante siempre, y
         * la del contador o revisor fiscal cuando el contribuyente tiene uno
         * registrado -regla que el cliente fijo el 2026-08-11 y que no depende
         * del tipo de persona ni de los ingresos-.
         *
         * Las firmas se consultan por (numero, rol, MODULO). El modulo no es
         * decoracion: los tres formularios reparten numeros de series distintas,
         * asi que la retencion 2026000001 y la declaracion de ICA 2026000001
         * existen a la vez y son documentos diferentes.
         */
        $numero = $fila[$this->c('NumeroDeclaracion')];

        $firmas = [];
        $stmtF = $con->consultar(
            "SELECT fd_Rol FROM firmas_declaraciones
              WHERE fd_NumeroDeclaracion = ? AND fd_Modulo = ?",
            [(string) $numero, $this->modulo]
        );
        while ($f = $con->obnerFila($stmtF)) { $firmas[] = $f['fd_Rol']; }

        if (!in_array('declarante', $firmas, true)) {
            $this->_ok = 0;
            $this->_mensaje = 'Debe firmar la declaración antes de presentarla.';
            return ['falta' => 'declarante'];
        }

        if ($this->_requiereContador($con, (int) $fila[$this->c('IdContribuyente')])
            && !in_array('contador', $firmas, true)) {
            $this->_ok = 0;
            $this->_mensaje = 'Falta la firma del contador o revisor fiscal. '
                            . 'Es obligatoria para este contribuyente.';
            // Motivo estable, para que la pantalla pueda encadenar el OTP del
            // contador en vez de mostrar un error suelto, igual que hace el ICA.
            return ['falta' => 'contador'];
        }

        // Se liquida otra vez ANTES de cerrar: lo que se presenta tiene que
        // ser lo que sale de los datos guardados, no lo que quedo en pantalla.
        $this->_liquidar($con, $id);

        date_default_timezone_set('America/Bogota');

        $con->consultar(
            "UPDATE {$this->tabla}
                SET {$this->c('Estado')} = 2,
                    {$this->c('FechaPresentacion')} = GETDATE()
              WHERE {$this->pk()} = ?",
            [$id]
        );

        $this->_ok = 1;
        $this->_mensaje = 'Declaración presentada correctamente';
        return ['id' => $id];
    }


    /* =====================================================================
     * FUNCION 7 - Corregir
     * ===================================================================== */

    /**
     * Crea una nueva declaracion que corrige a la que este vigente.
     *
     * Se ENCADENA sobre la ultima de la cadena, no sobre la que se pulso: si ya
     * hubo una correccion, corregir otra vez tiene que partir de esa. Corregir
     * el original de nuevo produciria dos correcciones hermanas y ninguna forma
     * de saber cual manda. Es la misma correccion que hubo que hacerle al ICA.
     */
    protected function _corregir()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $fila = $this->_filaAutorizada($con, isset($_POST['id']) ? $_POST['id'] : 0);
        if ($fila === null) {
            $this->_ok = 0;
            $this->_mensaje = 'No tiene permiso sobre esta declaración.';
            return [];
        }

        $idContribuyente = (int) $fila[$this->c('IdContribuyente')];
        $anio            = (int) $fila[$this->c('Anio')];
        $periodo         = (int) $fila[$this->c('Periodo')];

        // La que manda hoy en este periodo: la presentada mas reciente.
        $vigente = $con->obnerFila($con->consultar(
            "SELECT TOP 1 * FROM {$this->tabla}
              WHERE {$this->c('IdContribuyente')} = ?
                AND {$this->c('Anio')} = ? AND {$this->c('Periodo')} = ?
                AND {$this->c('Estado')} = 2
              ORDER BY {$this->c('FechaPresentacion')} DESC, {$this->pk()} DESC",
            [$idContribuyente, $anio, $periodo]
        ));

        if (!$vigente) {
            $this->_ok = 0;
            $this->_mensaje = 'Solo se puede corregir una declaración ya presentada.';
            return [];
        }

        // Si ya hay una correccion EN CURSO de este periodo, se abre esa en vez
        // de crear otra. Sin esto, pulsar "Corregir" dos veces deja dos
        // borradores gemelos.
        $enCurso = $con->obnerFila($con->consultar(
            "SELECT TOP 1 * FROM {$this->tabla}
              WHERE {$this->c('IdContribuyente')} = ?
                AND {$this->c('Anio')} = ? AND {$this->c('Periodo')} = ?
                AND {$this->c('Corrige')} IS NOT NULL
                AND ({$this->c('Estado')} IS NULL OR {$this->c('Estado')} <> 2)
              ORDER BY {$this->pk()} DESC",
            [$idContribuyente, $anio, $periodo]
        ));

        if ($enCurso) {
            $this->_ok = 1;
            $this->_mensaje = 'Se abrió la corrección que ya estaba en curso.';
            return [
                'id'     => (int) $enCurso[$this->pk()],
                'numero' => $enCurso[$this->c('NumeroDeclaracion')],
            ];
        }

        date_default_timezone_set('America/Bogota');

        try {
            $nuevo = $con->obnerFila($con->consultar(
                "SET NOCOUNT ON;
                 INSERT INTO {$this->tabla}
                        ({$this->c('IdContribuyente')}, {$this->c('Anio')}, {$this->c('Periodo')},
                         {$this->c('FechaDeclaracion')}, {$this->c('HoraDeclaracion')},
                         {$this->c('Corrige')}, {$this->c('FechaCreador')}, {$this->c('Creador')})
                 VALUES (?, ?, ?, ?, ?, ?, GETDATE(), ?);
                 SELECT CAST(SCOPE_IDENTITY() AS BIGINT) AS id;",
                [
                    $idContribuyente, $anio, $periodo,
                    // La correccion se declara HOY, no el dia del original.
                    date('Y-m-d'), date('H:i:s'),
                    $vigente[$this->c('NumeroDeclaracion')],
                    isset($_SESSION['id_usuario']) ? (int) $_SESSION['id_usuario'] : null,
                ]
            ));
        } catch (\Throwable $e) {
            error_log('[' . $this->modulo . '] no se pudo corregir: ' . $e->getMessage());
            $this->_ok = 0;
            $this->_mensaje = 'No se pudo crear la corrección.';
            return [];
        }

        $id = isset($nuevo['id']) ? (int) $nuevo['id'] : 0;
        if ($id <= 0) {
            $this->_ok = 0;
            $this->_mensaje = 'No se pudo crear la corrección.';
            return [];
        }

        $numero = $this->_siguienteNumero($con, $anio);
        if ($numero === null) { $numero = $id; }

        $con->consultar(
            "UPDATE {$this->tabla} SET {$this->c('NumeroDeclaracion')} = ? WHERE {$this->pk()} = ?",
            [$numero, $id]
        );

        // La correccion arranca con lo mismo que decia la vigente: se corrige
        // partiendo de lo declarado, no de una hoja en blanco.
        $this->_copiarContenido($con, (int) $vigente[$this->pk()], $id);
        $this->_liquidar($con, $id);

        $this->_ok = 1;
        $this->_mensaje = "Corrección creada, N° $numero";
        return ['id' => $id, 'numero' => $numero];
    }

    /** Copia renglones manuales y actividades del original a la correccion. */
    protected function _copiarContenido($con, $origen, $destino)
    {
        $anio = $this->_anioDe($con, $destino);

        $stmt = $con->consultar(
            "SELECT ren_Codigo FROM ind_renglones_retencion
              WHERE ren_Modulo = ? AND ren_Anio = ? AND ren_Estado = 1 AND ren_Manual = 1",
            [$this->modulo, $anio]
        );

        $columnas = [];
        while ($r = $con->obnerFila($stmt)) {
            $col = $this->c('ValorConcepto' . (int) $r['ren_Codigo']);
            if ($this->_existeColumna($con, $this->tabla, $col)) { $columnas[] = $col; }
        }

        if ($columnas) {
            $sets = [];
            foreach ($columnas as $col) { $sets[] = "d.$col = o.$col"; }
            $con->consultar(
                "UPDATE d SET " . implode(', ', $sets) . "
                   FROM {$this->tabla} d, {$this->tabla} o
                  WHERE d.{$this->pk()} = ? AND o.{$this->pk()} = ?",
                [(int) $destino, (int) $origen]
            );
        }

        $con->consultar(
            "INSERT INTO {$this->tablaAct}
                    ({$this->fkAct}, {$this->ca('IdActividad')}, {$this->ca($this->colBase)},
                     {$this->ca('Tarifa')}, {$this->ca($this->colValor)}, {$this->ca('FechaCreador')})
             SELECT ?, {$this->ca('IdActividad')}, {$this->ca($this->colBase)},
                    {$this->ca('Tarifa')}, {$this->ca($this->colValor)}, GETDATE()
               FROM {$this->tablaAct}
              WHERE {$this->fkAct} = ? AND {$this->ca('Activo')} = 1",
            [(int) $destino, (int) $origen]
        );
    }


    /* =====================================================================
     * FUNCION 8 - Catalogo de actividades para el desplegable
     * ===================================================================== */

    protected function _actividadesDisponibles()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $anio = isset($_POST['anio']) ? (int) $_POST['anio'] : (int) date('Y');

        /*
         * EL CATALOGO ESTA POR AÑO, Y HOY SOLO TIENE 2025.
         *
         * ind_actividadescomercio lleva acc_Anio, y al 2026-09-08 sus 69 filas
         * son todas de 2025. Pedir literalmente el año de la declaracion
         * devolveria CERO actividades y el desplegable saldria vacio, sin que
         * nada indique por que.
         *
         * Por eso se toma el año vigente MAS RECIENTE que no pase del año
         * declarado. Con el catalogo de 2025, una declaracion de 2026 usa las
         * tarifas de 2025 -que es lo que rige mientras la Alcaldia no expida el
         * acuerdo del año siguiente-. El dia que se cargue 2026, esta misma
         * consulta empieza a usarlo sola.
         */
        $vigente = $con->obnerFila($con->consultar(
            "SELECT MAX(acc_Anio) AS anio FROM ind_actividadescomercio WHERE acc_Anio <= ?",
            [$anio]
        ));
        $anioCatalogo = (isset($vigente['anio']) && $vigente['anio'] !== null)
                      ? (int) $vigente['anio'] : $anio;

        $stmt = $con->consultar(
            "SELECT acc_Id, acc_Codigo, acc_Nombre, acc_Tarifa, acc_Exento
               FROM ind_actividadescomercio
              WHERE acc_Estado = 1 AND acc_Anio = ?
              ORDER BY acc_Codigo",
            [$anioCatalogo]
        );

        $datos = [];
        while ($f = $con->obnerFila($stmt)) {
            $datos[] = [
                'id'          => (int) $f['acc_Id'],
                'codigo'      => $f['acc_Codigo'],
                'descripcion' => $f['acc_Nombre'],
                'tarifa'      => (float) $f['acc_Tarifa'],
                'exento'      => (int) $f['acc_Exento'],
            ];
        }

        $this->_ok = 1;
        $this->_mensaje = 'Consulta correcta';
        return $datos;
    }


    /* =====================================================================
     * DESPACHO
     * ===================================================================== */

    protected function _despachar($funcion)
    {
        switch ((int) $funcion) {
            case 1: return $this->_crear();
            case 2: return $this->_listar();
            case 3: return $this->_abrir();
            case 4: return $this->_guardar();
            case 5: return $this->_descartar();
            case 6: return $this->_presentar();
            case 7: return $this->_corregir();
            case 8: return $this->_actividadesDisponibles();
        }
        return $this->_despacharPropia($funcion);
    }

    /** Funciones que solo tiene uno de los dos modulos. */
    protected function _despacharPropia($funcion)
    {
        throw new \Exception('Función no válida');
    }

    /**
     * Punto de entrada. Toda peticion pasa por aqui, y la sesion se exige
     * ANTES de mirar que funcion es: un controlador que contestaba sin sesion
     * ya aparecio una vez en este proyecto (class.actividadEstablecimiento.php)
     * y era un agujero de confidencialidad.
     */
    protected function _correr()
    {
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }

        header('Content-type: application/json');

        if (empty($_SESSION['id_usuario'])) {
            echo json_encode(['ok' => 0, 'mensaje' => 'Debe iniciar sesión.', 'datos' => []]);
            return;
        }

        try {
            $datos = $this->_despachar(isset($_POST['funcion']) ? $_POST['funcion'] : 0);
            echo json_encode([
                'ok'      => $this->_ok,
                'mensaje' => $this->_mensaje,
                'datos'   => $datos,
            ]);

        } catch (\Throwable $e) {
            error_log('[' . $this->modulo . '] ' . $e->getMessage());
            echo json_encode([
                'ok'      => 0,
                'mensaje' => 'Error: ' . $e->getMessage(),
                'datos'   => [],
            ]);
        }
    }
}
