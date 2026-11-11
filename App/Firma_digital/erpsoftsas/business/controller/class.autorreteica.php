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
include_once SERVER . '/business/class.catalogoAnio.php';

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
     * registradas en el RIT, con ingresos en cero: el contribuyente solo
     * escribe las cifras del bimestre.
     *
     * Estan en ind_actividad_contribuyente, UNA fila por actividad (las
     * migraciones 005 y 007 las subieron de los establecimientos al
     * contribuyente y colapsaron las repetidas entre años; todas las filas son
     * las vigentes, tengan o no año). Leer de ind_actividad_establecimiento, a
     * la que ya nadie escribe, dejaba la autorretencion sin nada que precargar.
     *
     * SE CASAN POR CODIGO con el catalogo del año que rige (CatalogoAnio), no
     * por acc_Id: el RIT guarda el acc_Id del catalogo con que se inscribio, y
     * el dia que se cargue el catalogo de otro año sus filas tendran ids nuevos.
     * Uniendo por id y exigiendo el año vigente, la precarga quedaba vacia.
     *
     * NO REPITE: solo agrega las que el borrador no tenga ya. Por eso sirve
     * tambien para ponerlo al dia (_completarBorrador): un borrador que nacio
     * sin actividades -el RIT estaba vacio- las recibe al abrirlo despues de
     * actualizar el RIT, en vez de quedarse vacio para siempre.
     *
     * LA TARIFA SE COPIA DEL CATALOGO Y SE GUARDA. Una declaracion presentada
     * tiene que seguir diciendo lo mismo dentro de cinco años, aunque el
     * acuerdo municipal cambie la tarifa despues.
     */
    protected function _sembrarActividades($con, $id, $idContribuyente, $anio)
    {
        $anioCatalogo = CatalogoAnio::actividades($con, $anio);

        $stmt = $con->consultar(
            "SELECT cur.acc_Id, cur.acc_Tarifa
               FROM ind_actividad_contribuyente atc
               INNER JOIN ind_actividadescomercio rit ON rit.acc_Id = atc.atc_IdCodigoActividad
               INNER JOIN ind_actividadescomercio cur ON cur.acc_Codigo = rit.acc_Codigo
                                                     AND cur.acc_Anio = ?
              WHERE atc.atc_IdContribuyente = ?
                AND NOT EXISTS (
                    SELECT 1 FROM ind_autorreteica_actividades ya
                     INNER JOIN ind_actividadescomercio yc ON yc.acc_Id = ya.aua_IdActividad
                     WHERE ya.aua_IdAutorreteica = ? AND ya.aua_Activo = 1
                       AND yc.acc_Codigo = cur.acc_Codigo)
              GROUP BY cur.acc_Id, cur.acc_Tarifa
              ORDER BY MIN(atc.atc_Id)",
            [$anioCatalogo, (int) $idContribuyente, (int) $id]
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

    /** Un borrador sin firmas recibe las actividades que el RIT gano despues. */
    protected function _completarBorrador($con, array $fila)
    {
        $this->_sembrarActividades(
            $con, (int) $fila['aut_Id'], (int) $fila['aut_IdContribuyente'], (int) $fila['aut_Anio']
        );
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
     * Va ANTES de liquidar porque la casilla 15 lo usa; si se guardara
     * despues, la liquidacion correria con el valor viejo. Lo llama _guardar
     * dentro de su transaccion, ya comprobado que la fila es de quien pide y
     * que sigue siendo borrador.
     *
     * Y es justo el dato de la discrepancia mas grave del proyecto: el Excel
     * del cliente lo suma dentro del TOTAL y otra vez en la casilla 15, lo que
     * cobra el impuesto de energia dos veces. Por eso la formula de la 15 esta
     * en NULL hasta que el cliente confirme.
     */
    protected function _guardarExtra($con, array $fila)
    {
        if (array_key_exists('impuestoEnergia', $_POST)) {
            $con->consultar(
                "UPDATE ind_autorreteica SET aut_ImpuestoEnergia = ? WHERE aut_Id = ?",
                [$this->_cifra($_POST['impuestoEnergia']), (int) $fila['aut_Id']]
            );
        }

        $this->_guardarAvisos($con, $fila);
    }

    /**
     * Casilla 16, AVISOS Y TABLEROS: se calcula, pero el contribuyente puede
     * escribirla (057, decision de Jennifer y Diego 2026-11-10). Es la UNICA
     * casilla calculada que se puede editar: si se aceptara cualquiera, se
     * podria presentar una declaracion cuyas casillas no cuadran entre si.
     *
     *   avisosManual = 1  -> se guarda el valor escrito y la liquidacion no lo pisa
     *   avisosManual = 0  -> vuelve a calcularse (15%, o 0 si el RIT esta exento)
     *
     * Va ANTES de liquidar, como la energia: la formula de la 16 lee la marca.
     * Sin la 057 no hay columna: se ignora y la casilla se calcula como siempre.
     */
    protected function _guardarAvisos($con, array $fila)
    {
        if (!array_key_exists('avisosManual', $_POST)) { return; }
        if (!$this->_existeColumna($con, $this->tabla, 'aut_AvisosManual')) { return; }

        $manual = !empty($_POST['avisosManual']) && (string) $_POST['avisosManual'] !== '0';

        if (!$manual) {
            $con->consultar(
                "UPDATE ind_autorreteica SET aut_AvisosManual = 0 WHERE aut_Id = ?",
                [(int) $fila['aut_Id']]
            );
            return;
        }

        $valor = $this->_cifra(isset($_POST['avisos']) ? $_POST['avisos'] : 0);
        if ($valor < 0) { throw new \Exception('AVISOS_VALOR'); }

        $con->consultar(
            "UPDATE ind_autorreteica SET aut_AvisosManual = 1, aut_ValorConcepto16 = ? WHERE aut_Id = ?",
            [$valor, (int) $fila['aut_Id']]
        );
    }

    /**
     * La energia y la marca de la casilla 16 tambien se firman: cambiarlas quita
     * las firmas, como una casilla. Sin la 057 la columna no existe y no se lee.
     */
    protected function _columnasFirmadas() { return ['aut_ImpuestoEnergia', 'aut_AvisosManual']; }

    /**
     * "La suma de ingresos gravados debe ser igual a la casilla 13 INGRESOS
     * NETOS GRAVADOS" (formato del cliente, DECLARACION DE AUTORRETENCION DE
     * ICA.docx). No se comprobaba: se podia presentar descuadrada. Se compara
     * en pesos (diferencias de centavos no cuentan).
     */
    protected function _descuadre($con, $id)
    {
        $f = $con->obnerFila($con->consultar(
            "SELECT ISNULL(a.aut_ValorConcepto13, 0) AS casilla13,
                    (SELECT ISNULL(SUM(x.aua_IngresosGravados), 0)
                       FROM ind_autorreteica_actividades x
                      WHERE x.aua_IdAutorreteica = a.aut_Id AND x.aua_Activo = 1) AS suma
               FROM ind_autorreteica a
              WHERE a.aut_Id = ?",
            [(int) $id]
        ));
        if (!$f) { return null; }

        $casilla13 = round((float) $f['casilla13']);
        $suma      = round((float) $f['suma']);
        if ($casilla13 === $suma) { return null; }

        $pesos = function ($v) { return '$' . number_format($v, 0, ',', '.'); };
        return 'La suma de los ingresos gravados de las actividades (' . $pesos($suma) . ') '
             . 'debe ser igual a la casilla 13, ingresos netos gravados (' . $pesos($casilla13) . '). '
             . 'Revise las casillas 9 a 12 o los ingresos de cada actividad.';
    }

    /**
     * La correccion arranca con lo que decia la original, y la energia no es
     * un renglon: el motor copia los renglones manuales y las actividades, asi
     * que sin esto la correccion nacia con energia en 0 y la casilla 15 se
     * recalculaba sin ella. Una generadora que corrigiera presentaba de menos.
     */
    protected function _copiarContenido($con, $origen, $destino)
    {
        parent::_copiarContenido($con, $origen, $destino);

        $con->consultar(
            "UPDATE d SET d.aut_ImpuestoEnergia = o.aut_ImpuestoEnergia
               FROM ind_autorreteica d, ind_autorreteica o
              WHERE d.aut_Id = ? AND o.aut_Id = ?",
            [(int) $destino, (int) $origen]
        );

        // La casilla 16 escrita a mano en la original: la correccion parte de lo
        // mismo (si no, la liquidacion la recalcularia y cambiaria lo declarado).
        if ($this->_existeColumna($con, $this->tabla, 'aut_AvisosManual')) {
            $con->consultar(
                "UPDATE d SET d.aut_AvisosManual = o.aut_AvisosManual,
                              d.aut_ValorConcepto16 = CASE WHEN o.aut_AvisosManual = 1
                                                           THEN o.aut_ValorConcepto16
                                                           ELSE d.aut_ValorConcepto16 END
                   FROM ind_autorreteica d, ind_autorreteica o
                  WHERE d.aut_Id = ? AND o.aut_Id = ?",
                [(int) $destino, (int) $origen]
            );
        }
    }

    /** Lo que la pantalla necesita y no cabe en el catalogo de renglones. */
    protected function _datosExtra($con, $fila)
    {
        $hay = $this->_existeColumna($con, $this->tabla, 'aut_AvisosManual');

        // La exencion sale del RIT. La pantalla la usa para la vista previa y
        // para avisar; la que manda al liquidar es la formula de la casilla 16.
        $c = $con->obnerFila($con->consultar(
            "SELECT ISNULL(ind_SinAvisosTableros, 0) AS exento
               FROM ind_contribuyentes WHERE ind_Id = ?",
            [(int) $fila['aut_IdContribuyente']]
        ));

        return [
            'impuestoEnergia' => (float) $fila['aut_ImpuestoEnergia'],
            // Sin la 057 la casilla 16 no se ofrece editable.
            'avisosEditable'  => $hay ? 1 : 0,
            'avisosManual'    => ($hay && !empty($fila['aut_AvisosManual'])) ? 1 : 0,
            'sinAvisos'       => ($c && !empty($c['exento'])) ? 1 : 0,
        ];
    }

    public static function run()
    {
        $c = new self();
        $c->_correr();
    }
}

\erpsoftsas\ControladorAutorreteica::run();
