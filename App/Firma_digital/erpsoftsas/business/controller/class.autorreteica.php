<?php
namespace erpsoftsas;

/*
 * ============================================================================
 * AUTORRETEICA — declaracion de AUTORRETENCION de industria y comercio
 * ============================================================================
 *
 * Aqui el contribuyente se retiene a SI MISMO sobre sus propios ingresos del
 * bimestre. Por eso el formulario, a diferencia del de retencion, trae una
 * seccion de ingresos completa (casillas 9 a 13) antes de la liquidacion, y por
 * eso las actividades se precargan del RIT en vez de elegirse: son las suyas,
 * las que ya declaro al inscribirse.
 *
 * Todo el ciclo de vida vive en business/class.retenciones.php.
 * ============================================================================
 */

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.retenciones.php';

class ControladorAutorreteica extends \erpsoftsas\ControladorRetencion
{
    public function __construct()
    {
        $this->modulo   = 'AUTORRETEICA';

        $this->tabla    = 'ind_autorreteica';
        $this->p        = 'aut_';

        $this->tablaAct = 'ind_autorreteica_actividades';
        $this->pa       = 'aua_';
        $this->fkAct    = 'aua_IdAutorreteica';

        $this->colBase  = 'IngresosGravados';
        $this->colValor = 'ValorImpuesto';

        // Bimestral: seis declaraciones al año.
        $this->periodoMax    = 6;
        $this->nombrePeriodo = 'bimestre';
    }

    /** Casilla 23 del formulario de autorretencion. */
    protected function renglonTotal() { return 23; }

    /**
     * La declaracion nace con las actividades que el contribuyente tiene
     * registradas en el RIT.
     *
     * Se declara sobre lo propio, asi que el sistema ya sabe cuales son: estan
     * en ind_actividad_establecimiento, colgando de los establecimientos del
     * contribuyente. Se traen con ingresos en cero, para que el contribuyente
     * solo escriba las cifras del bimestre.
     *
     * SE AGRUPAN POR ACTIVIDAD, no por establecimiento. Un contribuyente con
     * tres locales que ejercen la misma actividad tiene esa actividad tres
     * veces en la tabla; en el formulario es UNA linea. Es el mismo criterio
     * que ya usa la declaracion anual de ICA: se declara por contribuyente y
     * las actividades se agregan por codigo.
     *
     * LA TARIFA SE COPIA DEL CATALOGO Y SE GUARDA. Una declaracion presentada
     * tiene que seguir diciendo lo mismo dentro de cinco años, aunque el
     * acuerdo municipal cambie la tarifa despues.
     *
     * El catalogo esta por año y hoy solo tiene 2025, asi que se toma el año
     * vigente mas reciente que no pase del declarado -misma regla que el
     * desplegable de actividades del motor, y por el mismo motivo-.
     */
    protected function _sembrarActividades($con, $id, $idContribuyente, $anio)
    {
        $vigente = $con->obnerFila($con->consultar(
            "SELECT MAX(acc_Anio) AS anio FROM ind_actividadescomercio WHERE acc_Anio <= ?",
            [(int) $anio]
        ));
        $anioCatalogo = (isset($vigente['anio']) && $vigente['anio'] !== null)
                      ? (int) $vigente['anio'] : (int) $anio;

        /*
         * Las actividades del contribuyente viven en ind_actividad_contribuyente
         * -la tabla NUEVA-. Las migraciones 005 y 007 las subieron del
         * establecimiento al contribuyente y les quitaron el año; desde
         * entonces la pantalla del RIT guarda ahi y a ind_actividad_establecimiento
         * ya nadie escribe. Leer de la vieja devolvia CERO actividades para todo
         * contribuyente cuyo RIT se tocara despues de esa migracion, y la
         * autorretencion salia sin nada que precargar. Es la misma correccion que
         * ya llevan el RIT (class.establecimientos.php) y el ICA.
         *
         * La tarifa se toma del catalogo del año vigente uniendo por acc_Id, igual
         * que antes: el RIT guarda el acc_Id del catalogo, que es por año.
         */
        $stmt = $con->consultar(
            "SELECT DISTINCT ac.acc_Id, ac.acc_Tarifa
               FROM ind_actividad_contribuyente atc
               INNER JOIN ind_actividadescomercio ac ON ac.acc_Id = atc.atc_IdCodigoActividad
              WHERE atc.atc_IdContribuyente = ?
                AND ac.acc_Anio = ?",
            [(int) $idContribuyente, $anioCatalogo]
        );

        $filas = [];
        while ($f = $con->obnerFila($stmt)) { $filas[] = $f; }

        foreach ($filas as $f) {
            $con->consultar(
                "INSERT INTO {$this->tablaAct}
                        ({$this->fkAct}, aua_IdActividad, aua_IngresosGravados,
                         aua_Tarifa, aua_ValorImpuesto, aua_FechaCreador)
                 VALUES (?, ?, 0, ?, 0, GETDATE())",
                [(int) $id, (int) $f['acc_Id'], (float) $f['acc_Tarifa']]
            );
        }

        return count($filas);
    }

    /**
     * El impuesto por generacion de energia se guarda antes de liquidar.
     *
     * NO ES UNA CASILLA NUMERADA. En el formulario aparece en la fila del
     * TOTAL de la seccion de actividades, sin rotulo, asi que no cabe en el
     * catalogo de renglones -que va por numero de casilla- y hay que tratarlo
     * aparte. Lo escribe el contribuyente: es el impuesto de la Ley 56 de 1981
     * para generadoras, y solo aplica a unas pocas.
     *
     * Va ANTES de llamar al motor porque la casilla 15 lo usa; si se guardara
     * despues, la liquidacion correria con el valor viejo.
     *
     * Y es justo el dato de la discrepancia mas grave del proyecto: el Excel
     * del cliente lo suma dentro del TOTAL y otra vez en la casilla 15, lo que
     * cobra el impuesto de energia dos veces. Por eso la formula de la 15 esta
     * en NULL hasta que el cliente confirme.
     */
    protected function _guardar()
    {
        if (array_key_exists('impuestoEnergia', $_POST)) {

            $con  = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
            $fila = $this->_filaAutorizada($con, isset($_POST['id']) ? $_POST['id'] : 0);

            // Solo si la fila es suya Y sigue abierta. Sin las dos condiciones
            // esto seria una puerta lateral para escribir en una declaracion
            // ajena o ya presentada, saltandose las guardas del motor.
            if ($fila !== null && $this->_esBorrador($fila)) {
                $con->consultar(
                    "UPDATE ind_autorreteica SET aut_ImpuestoEnergia = ? WHERE aut_Id = ?",
                    [$this->_cifra($_POST['impuestoEnergia']), (int) $fila['aut_Id']]
                );
            }
        }

        return parent::_guardar();
    }

    /** Lo que la pantalla necesita y no cabe en el catalogo de renglones. */
    protected function _datosExtra($con, $fila)
    {
        return [
            'impuestoEnergia' => (float) $fila['aut_ImpuestoEnergia'],
        ];
    }

    public static function run()
    {
        $c = new self();
        $c->_correr();
    }
}

\erpsoftsas\ControladorAutorreteica::run();
