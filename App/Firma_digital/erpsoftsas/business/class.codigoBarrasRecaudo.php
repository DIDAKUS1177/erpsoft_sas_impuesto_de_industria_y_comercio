<?php
namespace erpsoftsas;

/**
 * Referencia de recaudo bancario en formato GS1-128.
 *
 * Replica la estructura que ya usa el sistema de PREDIAL de la misma
 * alcaldía (Laravel, en paipa.erpsoftsas.com), leída de
 * app/Http/Controllers/PrediosController.php y de la vista
 * resources/views/predios/facturaPDF_pai.blade.php. Es el formato que el
 * banco recaudador ya acepta en ventanilla para el recibo de predial, así
 * que ICA lo reutiliza en vez de inventar uno nuevo.
 *
 * Estructura:
 *
 *   FNC1 + "415" + EAN(13)
 *        + "8020" + numeroFactura(24, ceros a la izquierda)
 *   FNC1 + "3900" + valor(14, ceros a la izquierda, SIN decimales)
 *   FNC1 + "96"   + fechaVencimiento(AAAAMMDD)        [opcional]
 *
 * Los identificadores son los de GS1:
 *   415  GLN de la entidad que factura (el convenio de recaudo)
 *   8020 referencia del comprobante de pago
 *   3900 importe a pagar, moneda local, sin punto decimal
 *   96   uso interno; aquí la fecha límite de pago
 *
 * FNC1 se representa con chr(241): así lo entiende el generador Code128 de
 * TCPDF (ver $fnc_a/$fnc_b en extensiones/tcpdf/tcpdf_barcodes_1d.php, donde
 * 241 se mapea al carácter 102 = FNC1) y también la librería que usa
 * predial. El primer FNC1 marca el código como GS1-128; los siguientes
 * cierran los identificadores de longitud variable (8020 y 3900). El 415 no
 * lleva separador porque su longitud es fija.
 *
 * PENDIENTE DE VERIFICAR ANTES DE ANUNCIARLO COMO FUNCIONAL:
 *
 *   1. El EAN de ICA es DISTINTO al de predial (confirmado con el cliente el
 *      2026-08-12). Hay que pedirlo al banco y ponerlo en
 *      MUNICIPIO_EAN_RECAUDO dentro de config.municipio.php. Mientras esa
 *      constante no exista, construir() devuelve null y los PDF siguen
 *      imprimiendo el código simple de siempre.
 *   2. El formato exacto de la fecha del identificador 96 no está
 *      confirmado; 96 no es un AI estándar de GS1 sino de uso interno. Se
 *      asume AAAAMMDD. Como la fecha límite de ICA todavía no se captura
 *      (queda pendiente con el cliente), hoy el segmento se omite.
 *   3. Nada de esto sustituye la certificación del banco: hay que imprimir
 *      un PDF de prueba y confirmar que el escáner de ventanilla lo lee.
 */
class CodigoBarrasRecaudo
{
    /** Longitudes fijadas por la estructura que usa predial. */
    const LARGO_EAN     = 13;
    const LARGO_FACTURA = 24;
    const LARGO_VALOR   = 14;

    const FNC1 = "\xF1"; // chr(241)

    /** Claves de los parametros en conf_parametros. */
    const CLAVE_EAN            = 'RECAUDO_EAN';
    const CLAVE_DIAS_VIGENCIA  = 'RECAUDO_DIAS_VIGENCIA';

    /** Se resuelven una vez por peticion; no hace falta ir a la base en cada PDF. */
    private static $eanCache = false;
    private static $parametros = null;

    /**
     * Lee un parametro de conf_parametros. Devuelve null si no existe, esta
     * inactivo, la tabla no esta (instalacion sin la migracion 009) o la base
     * no responde: en todos esos casos el llamador debe poder seguir.
     */
    private static function parametro($clave)
    {
        if (self::$parametros === null) {
            self::$parametros = [];
            try {
                if (class_exists('\\ConexionMysqlUsuariosSqlServer\\ConexionSQLServer')) {
                    $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
                    $st = $con->consultar(
                        "SELECT par_Clave, par_Valor FROM conf_parametros
                          WHERE ISNULL(par_Estado, 1) = 1", []
                    );
                    while ($f = $con->obnerFila($st)) {
                        self::$parametros[$f['par_Clave']] = $f['par_Valor'];
                    }
                }
            } catch (\Exception $e) {
                error_log('[codigoBarras] no se pudieron leer los parametros: ' . $e->getMessage());
            }
        }

        $v = self::$parametros[$clave] ?? null;
        $v = ($v === null) ? null : trim((string) $v);

        return ($v === null || $v === '') ? null : $v;
    }

    /**
     * Fecha que va en el segmento (96), o null para omitirlo.
     *
     * Sale del parametro RECAUDO_DIAS_VIGENCIA: vacio = no se imprime el
     * segmento, 0 = la fecha de hoy, N = hoy mas N dias. Ver la nota de la
     * migracion 009: el 96 no es un AI estandar y su significado lo tiene que
     * confirmar el banco, asi que por defecto no se imprime.
     */
    public static function fechaVigencia()
    {
        $dias = self::parametro(self::CLAVE_DIAS_VIGENCIA);
        if ($dias === null || !ctype_digit($dias)) {
            return null;
        }

        return date('Y-m-d', strtotime('+' . (int) $dias . ' days'));
    }

    /**
     * EAN del convenio de recaudo de este municipio.
     *
     * Se busca en DOS sitios, en este orden:
     *
     *   1. conf_parametros (clave RECAUDO_EAN). Es el sitio bueno: la Alcaldia
     *      lo cambia desde la pantalla de configuracion, sin pedir un
     *      despliegue. Lo pidio asi Javier el 2026-08-20 -"se debe configurar
     *      en una tabla para poderlo cambiar en caso que sea necesario, cada
     *      entidad tiene su propio EAN"- y tiene razon: si el banco cambia el
     *      convenio, no puede depender de que alguien edite un archivo del
     *      servidor.
     *
     *   2. La constante MUNICIPIO_EAN_RECAUDO, como respaldo. Sirve para las
     *      instalaciones que todavia no han corrido la migracion 009, y para
     *      poder forzar un valor desde el config si hiciera falta.
     *
     * Devuelve null si no hay ninguno valido, y entonces el codigo de barras
     * se imprime sin referencia de recaudo -que es preferible a inventarse
     * trece digitos que el banco va a rechazar en la fila-.
     */
    public static function ean()
    {
        if (self::$eanCache !== false) {
            return self::$eanCache;
        }

        self::$eanCache = null;
        $patron = '/^\d{' . self::LARGO_EAN . '}$/';

        // 1. la tabla
        $valor = self::parametro(self::CLAVE_EAN);
        if ($valor !== null && preg_match($patron, $valor) === 1) {
            self::$eanCache = $valor;
            return self::$eanCache;
        }

        // 2. el config
        if (defined('MUNICIPIO_EAN_RECAUDO')
            && preg_match($patron, (string) MUNICIPIO_EAN_RECAUDO) === 1) {
            self::$eanCache = (string) MUNICIPIO_EAN_RECAUDO;
        }

        return self::$eanCache;
    }

    /**
     * ¿Está configurado el convenio de recaudo de este municipio?
     */
    public static function configurado()
    {
        return self::ean() !== null;
    }

    /**
     * Construye la cadena a codificar en Code128.
     *
     * @param string|int      $numeroFactura   Referencia del recibo (número de declaración).
     * @param float|int       $valor           Importe a pagar, en pesos, sin decimales.
     * @param string|null     $fechaVencimiento Fecha límite (cualquier formato que entienda strtotime).
     * @return string|null    null si falta el convenio o los datos no son válidos.
     */
    public static function construir($numeroFactura, $valor, $fechaVencimiento = null)
    {
        if (!self::configurado()) {
            return null;
        }

        // Solo dígitos: el banco no acepta otra cosa en estos campos.
        $factura = preg_replace('/\D/', '', (string) $numeroFactura);
        if ($factura === '') {
            return null;
        }
        if (strlen($factura) > self::LARGO_FACTURA) {
            return null; // no se puede truncar una referencia de pago
        }

        // El importe va sin decimales; se redondea al peso.
        $importe = (string) (int) round((float) $valor);
        if (strlen($importe) > self::LARGO_VALOR) {
            return null;
        }

        $cadena = self::FNC1
                . '415'  . self::ean()
                . '8020' . str_pad($factura, self::LARGO_FACTURA, '0', STR_PAD_LEFT)
                . self::FNC1
                . '3900' . str_pad($importe, self::LARGO_VALOR, '0', STR_PAD_LEFT);

        // El segmento de fecha solo se agrega si de verdad hay una. Ver nota 2
        // de la cabecera: hoy ICA no captura la fecha límite.
        $fecha = self::formatearFecha($fechaVencimiento);
        if ($fecha !== null) {
            $cadena .= self::FNC1 . '96' . $fecha;
        }

        return $cadena;
    }

    /**
     * Normaliza la fecha a AAAAMMDD. Devuelve null si no hay fecha usable,
     * para que el segmento se omita en vez de codificar una fecha inventada.
     */
    private static function formatearFecha($fecha)
    {
        if ($fecha instanceof \DateTime) {
            return $fecha->format('Ymd');
        }
        if (!is_string($fecha) || trim($fecha) === '') {
            return null;
        }
        $ts = strtotime($fecha);
        return $ts ? date('Ymd', $ts) : null;
    }

    /**
     * Versión legible de la referencia, para imprimir bajo las barras.
     * Los FNC1 no son imprimibles, así que se muestran los identificadores
     * entre paréntesis, como es costumbre en GS1-128.
     */
    public static function textoLegible($numeroFactura, $valor, $fechaVencimiento = null)
    {
        if (!self::configurado()) {
            return (string) $numeroFactura;
        }

        $factura = preg_replace('/\D/', '', (string) $numeroFactura);
        $importe = (string) (int) round((float) $valor);

        // self::ean(), no la constante: si el EAN vive solo en la tabla
        // -que es el caso desde la migracion 009- referirse a la constante
        // aqui reventaba con "Undefined constant" al generar el PDF.
        $texto = '(415)' . self::ean()
               . '(8020)' . str_pad($factura, self::LARGO_FACTURA, '0', STR_PAD_LEFT)
               . '(3900)' . str_pad($importe, self::LARGO_VALOR, '0', STR_PAD_LEFT);

        $fecha = self::formatearFecha($fechaVencimiento);
        if ($fecha !== null) {
            $texto .= '(96)' . $fecha;
        }

        return $texto;
    }
}
