<?php
namespace erpsoftsas;

/*
 * ============================================================================
 * RETEICA — declaracion de RETENCION de industria y comercio
 * ============================================================================
 *
 * Quien declara aqui es el AGENTE RETENEDOR: alguien que le pago a terceros y,
 * al pagarles, les descontó el ICA. Declara y entrega a la Alcaldia lo que
 * retuvo, mes a mes.
 *
 * Esa es la diferencia de fondo con AUTORRETEICA -donde el contribuyente
 * retiene sobre sus PROPIOS ingresos- y es la razon de que aqui la declaracion
 * nazca vacia: el sistema no puede saber a quien le retuvo este mes ni bajo que
 * actividad.
 *
 * Todo el ciclo de vida vive en business/class.retenciones.php. Este archivo
 * solo declara en que se diferencia este modulo.
 * ============================================================================
 */

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.retenciones.php';

class ControladorReteica extends \erpsoftsas\ControladorRetencion
{
    public function __construct()
    {
        $this->modulo   = 'RETEICA';

        $this->tabla    = 'ind_reteica';
        $this->p        = 'ret_';

        $this->tablaAct = 'ind_reteica_actividades';
        $this->pa       = 'rea_';
        $this->fkAct    = 'rea_IdReteica';

        $this->colBase  = 'BaseGravable';    // casilla 12, la escribe el usuario
        $this->colValor = 'ValorRetencion';  // casilla 13, la calcula el sistema

        // Mensual: doce declaraciones al año.
        $this->periodoMax    = 12;
        $this->nombrePeriodo = 'mes';
    }

    /** Casilla 17 del formulario de retencion. */
    protected function renglonTotal() { return 17; }

    /**
     * La declaracion de retencion nace SIN filas.
     *
     * No hay de donde sacarlas: las actividades que van aqui son las de los
     * terceros a quienes se les retuvo durante el mes, y eso no esta en ninguna
     * parte del sistema hasta que el agente retenedor lo escribe. Precargar las
     * actividades propias del RIT -que es lo que hace autorretencion- seria
     * proponerle datos que no tienen nada que ver con lo que debe declarar.
     */
    protected function _sembrarActividades($con, $id, $idContribuyente, $anio)
    {
        return 0;
    }

    public static function run()
    {
        $c = new self();
        $c->_correr();
    }
}

\erpsoftsas\ControladorReteica::run();
