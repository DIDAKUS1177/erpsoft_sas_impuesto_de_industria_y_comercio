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

        $anio = (int) $anio ?: (int) substr(self::hoy(), 0, 4);

        if (preg_match('#^(\d{1,2})/(\d{1,2})$#', trim((string) Parametros::valor(self::CLAVE)), $m)
            && checkdate((int) $m[2], (int) $m[1], $anio)) {
            return sprintf('%04d-%02d-%02d', $anio, $m[2], $m[1]);
        }

        return $anio . '-04-30';
    }

    /**
     * ¿Ya pasó la fecha límite? El mismo día límite todavía se paga normal.
     * "Hoy" es el de Colombia: con el servidor en UTC, el día límite quedaba
     * vencido desde las 7 p. m.
     */
    public static function vencida($anio)
    {
        return self::hoy() > self::fechaLimite($anio);
    }

    /** Hoy en Colombia (AAAA-MM-DD), sin depender de la zona del servidor. */
    public static function hoy()
    {
        return (new \DateTime('now', new \DateTimeZone('America/Bogota')))->format('Y-m-d');
    }

    /* =======================================================================
       INTERESES DE MORA A MANO (Javier, 2026-09-25: "que se deje de manera
       manual de momento"). No hay tasas ni fórmula: los escribe quien genera
       el recibo o paga por PSE. Una declaración VENCIDA se paga CON intereses,
       también cuando el recibo lo saca el propio contribuyente (cliente,
       2026-09-25). leerIntereses, exigeIntereses y diasDeMora las comparten
       reciboPago.php y extensiones/pse/, para que el banco y PSE cobren con la
       misma regla.
       ======================================================================= */

    /**
     * Los intereses escritos: pesos enteros, con puntos de miles o signo si
     * vienen copiados; diez cifras como mucho. Los centavos (",50") se
     * descartan: la casilla deja escribir la coma, y sin esto "150000,50" se
     * volvía 15.000.050. null si no es un valor válido.
     */
    public static function leerIntereses($texto)
    {
        $limpio = preg_replace('/[\s.$]/', '', trim((string) $texto));
        $limpio = preg_replace('/,\d{0,2}$/', '', $limpio);
        if (!ctype_digit($limpio) || strlen($limpio) > 10) { return null; }
        return (int) $limpio;
    }

    /**
     * ¿Hay que escribirlos sí o sí? Vencida y sin intereses en el formulario:
     * si la declaración ya trae los suyos (renglón 37, liquidados al presentar),
     * con esos basta.
     */
    public static function exigeIntereses($anio, $declarados)
    {
        return self::vencida($anio) && (float) $declarados <= 0;
    }

    /** Días de mora hasta una fecha (AAAA-MM-DD): del día siguiente a la fecha límite en adelante. */
    public static function diasDeMora($anio, $hasta)
    {
        $limite = new \DateTime(self::fechaLimite($anio));
        $fin    = new \DateTime(substr((string) $hasta, 0, 10));
        return $fin > $limite ? (int) $limite->diff($fin)->days : 0;
    }
}
