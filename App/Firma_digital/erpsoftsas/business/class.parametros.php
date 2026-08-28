<?php
namespace erpsoftsas;

/**
 * Los parámetros de configuración de la entidad (tabla conf_parametros).
 *
 * POR QUÉ EXISTE ESTA CLASE
 *
 * El mecanismo -leer la tabla una vez, cachear, caer a una constante del
 * config si no hay valor- lo inventó class.codigoBarrasRecaudo para el EAN de
 * recaudo. Al llegar el segundo caso (las credenciales de la pasarela de pago,
 * 2026-08-26) tocaba copiarlo, y este proyecto ya sabe cómo termina eso: las
 * funciones de cifras estuvieron copiadas en tres archivos y costó dos errores
 * en producción, porque se arreglaba una copia y las otras seguían rotas.
 *
 * Así que el mecanismo vive aquí una sola vez y quien lo necesite lo llama.
 *
 * CÓMO SE RESUELVE UN VALOR
 *
 *   1. La tabla conf_parametros. Es la que manda: la Alcaldía la edita desde
 *      la pantalla de Configuración, sin desplegar nada.
 *   2. Si no hay valor -o está vacío-, la constante de config.municipio.php.
 *      Es el respaldo para instalaciones donde todavía no se aplicó la
 *      migración correspondiente.
 *   3. Si tampoco, null. Nunca un valor inventado: quien pregunta decide qué
 *      hacer sin configuración, y para eso tiene que poder distinguir
 *      "no configurado" de "configurado en blanco".
 *
 * LA CACHÉ ES POR PETICIÓN
 *
 * Se lee la tabla entera una vez y se guarda en memoria mientras dura la
 * petición de PHP. No hay caché entre peticiones, así que un cambio hecho en
 * la pantalla de Configuración se ve en la siguiente pantalla que se abra, sin
 * reiniciar nada. Si dentro de una misma petición se escribe un parámetro y
 * hay que releerlo, está olvidar().
 *
 * SI LA BASE FALLA, NO SE CAE NADA
 *
 * Un fallo leyendo la tabla se registra y se sigue con las constantes. Estos
 * valores gobiernan cosas como imprimir un código de barras o mostrar el botón
 * de pago: es preferible funcionar con la configuración del archivo que dejar
 * la pantalla en blanco.
 */
class Parametros
{
    /** @var array<string,string>|null Todos los parámetros activos, o null si no se han leído. */
    private static $cache = null;

    /**
     * El valor de un parámetro, o null si no está puesto.
     *
     * Un valor que solo tiene espacios cuenta como no puesto: en la pantalla
     * de Configuración es indistinguible de vacío y sería una trampa que se
     * comportaran distinto.
     *
     * @param  string      $clave  par_Clave, p. ej. 'RECAUDO_EAN'
     * @return string|null
     */
    public static function valor($clave)
    {
        if (self::$cache === null) {
            self::_cargar();
        }

        $v = self::$cache[$clave] ?? null;
        $v = ($v === null) ? null : trim((string) $v);

        return ($v === null || $v === '') ? null : $v;
    }

    /**
     * El valor de la tabla, y si no, el de una constante del config.
     *
     * Es el patrón que usan todos los parámetros migrados: la tabla manda y la
     * constante queda de respaldo. Se pasa el NOMBRE de la constante, no su
     * valor, porque referenciar una constante que no existe es un error fatal
     * en PHP 8 y aquí hace falta poder preguntar si está definida.
     *
     * @param  string      $clave      par_Clave en conf_parametros
     * @param  string|null $constante  nombre de la constante de respaldo
     * @param  string|null $patron     expresión regular COMPLETA (con delimitadores)
     *                                 que debe cumplir el valor para aceptarse
     * @return string|null
     */
    public static function valorOConstante($clave, $constante = null, $patron = null)
    {
        $valido = function ($v) use ($patron) {
            if ($v === null || $v === '') { return false; }
            return $patron === null || preg_match($patron, $v) === 1;
        };

        $v = self::valor($clave);
        if ($valido($v)) { return $v; }

        if ($constante !== null && defined($constante)) {
            $v = trim((string) constant($constante));
            if ($valido($v)) { return $v; }
        }

        return null;
    }

    /**
     * Olvida lo leído. Solo hace falta si en la MISMA petición se escribe un
     * parámetro y después se vuelve a preguntar por él.
     */
    public static function olvidar()
    {
        self::$cache = null;
    }

    private static function _cargar()
    {
        self::$cache = [];

        try {
            if (!class_exists('\\ConexionMysqlUsuariosSqlServer\\ConexionSQLServer')) {
                return;
            }

            $con  = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
            $stmt = $con->consultar(
                "SELECT par_Clave, par_Valor FROM conf_parametros
                  WHERE ISNULL(par_Estado, 1) = 1",
                []
            );

            while ($f = $con->obnerFila($stmt)) {
                self::$cache[$f['par_Clave']] = $f['par_Valor'];
            }

        } catch (\Exception $e) {
            // Se sigue con las constantes: ver la nota de arriba.
            error_log('[parametros] no se pudieron leer: ' . $e->getMessage());
        }
    }
}
