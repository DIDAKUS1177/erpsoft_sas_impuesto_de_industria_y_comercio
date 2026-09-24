<?php
namespace erpsoftsas;

/**
 * Fecha límite del ICA, y si una declaración ya está vencida.
 *
 * Regla del cliente (audio y respuestas del 2026-09-24): hasta la fecha límite
 * -casi siempre el 30 de abril- el ICA se paga "normal", por PSE o con la
 * declaración y su código de barras. Desde el día siguiente la declaración
 * está VENCIDA: ya no se imprime con código de barras y se paga con el recibo
 * de pago, que es donde van los intereses de mora.
 *
 * La fecha la cambia la Alcaldía en Parámetros ICA > Municipio y bancos
 * (ICA_FECHA_LIMITE, DD/MM, detrás de la contraseña de esa pantalla; migración
 * 034). Vacía o imposible (31/02), vale el 30/04.
 *
 * EL AÑO ES EL DE LA DECLARACIÓN, NO EL SIGUIENTE. dec_AnioDeclaracion lo pone
 * el sistema con el año en que se crea (date('Y'), ver _agregarDeclaracion: el
 * cliente pidió quitar el selector, "nada de años"), o sea el año en que se
 * presenta y se paga. Si algún día vuelve el selector de año GRAVABLE, la
 * fecha límite pasa a caer en el año siguiente al de la declaración.
 */
class VencimientoICA
{
    const CLAVE = 'ICA_FECHA_LIMITE';

    /** Fecha límite (AAAA-MM-DD) de una declaración del año dado. */
    public static function fechaLimite($anio)
    {
        include_once __DIR__ . '/class.parametros.php';

        $anio = (int) $anio ?: (int) date('Y');

        if (preg_match('#^(\d{1,2})/(\d{1,2})$#', trim((string) Parametros::valor(self::CLAVE)), $m)
            && checkdate((int) $m[2], (int) $m[1], $anio)) {
            return sprintf('%04d-%02d-%02d', $anio, $m[2], $m[1]);
        }

        return $anio . '-04-30';
    }

    /** ¿Ya pasó la fecha límite? El mismo día límite todavía se paga normal. */
    public static function vencida($anio)
    {
        return date('Y-m-d') > self::fechaLimite($anio);
    }
}
