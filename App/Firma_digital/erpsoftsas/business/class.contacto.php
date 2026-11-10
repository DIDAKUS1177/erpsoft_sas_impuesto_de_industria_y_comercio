<?php
namespace erpsoftsas;

include_once __DIR__ . '/class.parametros.php';
// class.parametros.php lee la tabla por esta conexión y, si la clase no está
// cargada, sigue en silencio sin leer nada: la pantalla de ingreso no incluye
// globals.php ni ningún controlador, así que se asegura aquí.
include_once __DIR__ . '/class.conexionSqlServer.php';

/**
 * El contacto del municipio: la dependencia que atiende, su teléfono y su correo.
 *
 * POR QUÉ EXISTE
 *
 * Esos tres datos salían ESCRITOS en el código, y eran los de Paipa: el pie de
 * la pantalla de ingreso decía "Secretaría de Hacienda - Dirección de
 * Impuestos, Rentas y Jurisdicción Coactiva" y "Soporte: 318 530 9285" en todos
 * los municipios, y seis avisos de las pantallas mandaban al contribuyente al
 * correo de Paipa. Diego (2026-11-09): hay que poder cambiar los números de
 * contacto desde la configuración del municipio, porque en los demás salían los
 * de Paipa.
 *
 * Ahora los tres viven en conf_parametros (migración 054), los escribe cada
 * Alcaldía en Municipio y bancos > Contacto del municipio, y todo el sistema los
 * lee por AQUÍ: un solo lugar, para que no vuelva a pasar que uno se corrija en
 * un archivo y los demás sigan con el dato viejo.
 *
 * REGLAS
 *
 *  - Sin dato, null. NUNCA un valor de Paipa (ni de nadie) por defecto: es
 *    justo el defecto que esto corrige. Quien pregunta decide qué hacer sin él;
 *    el pie del ingreso, por ejemplo, simplemente no pinta esa línea.
 *  - La tabla manda. Como respaldo, solo para la dependencia, valen las
 *    constantes MUNICIPIO_SECRETARIA y MUNICIPIO_DEPENDENCIA_TRIBUTARIA de
 *    config.municipio.php, que ya existen y que los PDF del RIT y de retención
 *    usan para esos mismos dos nombres. Teléfono y correo no tienen constante.
 *  - Lo que está en la tabla se vuelve a comprobar al LEER contra los mismos
 *    patrones con que se valida al guardar. La pantalla ya los exige, pero la
 *    tabla también se toca con SQL, y un valor raro no debe llegar a una página.
 *  - Los valores salen SIN escapar: quien los imprime los escapa para donde los
 *    pone (htmlspecialchars en HTML; jsonParaPantalla() para un <script>).
 *  - Si la base no responde, no se cae nada: class.parametros.php registra el
 *    fallo y todo vuelve null. La pantalla de ingreso depende de eso, y además
 *    lee con paraIngreso(), que espera a la base solo unos segundos.
 *
 * Los patrones de aquí son los mismos de par_Patron en la migración 054.
 */
class Contacto
{
    const CLAVE_DEPENDENCIA = 'CONTACTO_DEPENDENCIA';
    const CLAVE_TELEFONO    = 'CONTACTO_TELEFONO';
    const CLAVE_CORREO      = 'CONTACTO_CORREO';

    /** Las tres claves de conf_parametros que forman el contacto, en el orden de la pantalla. */
    const CLAVES = [self::CLAVE_DEPENDENCIA, self::CLAVE_TELEFONO, self::CLAVE_CORREO];

    /**
     * Qué se acepta en cada una, con anclas y SIN delimitadores (así los guarda
     * par_Patron). (*UTF8) para que el largo cuente letras y no bytes.
     *  - dependencia: hasta 150, sin < ni > ni caracteres de control.
     *  - teléfono: dígitos, espacios y + ( ) . , / -, con al menos un dígito.
     *  - correo: una dirección con arroba y dominio, hasta 120.
     */
    const PATRONES = [
        self::CLAVE_DEPENDENCIA => '(*UTF8)^[^<>\x00-\x1F\x7F]{1,150}$',
        self::CLAVE_TELEFONO    => '^(?=.*[0-9])[0-9 +().,/-]{1,80}$',
        self::CLAVE_CORREO      => '^(?=.{1,120}$)[A-Za-z0-9_%+-]+(\.[A-Za-z0-9_%+-]+)*@[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,}$',
    ];

    /** Qué decirle a quien escribió un valor que no cumple (lo que ve en la pantalla). */
    const MENSAJES_FORMATO = [
        self::CLAVE_DEPENDENCIA => 'La dependencia admite hasta 150 caracteres y no puede llevar los signos < ni >.',
        self::CLAVE_TELEFONO    => 'El teléfono solo admite números, espacios y los signos + ( ) . , / - (hasta 80 caracteres), '
                                 . 'y debe tener al menos un número.',
        self::CLAVE_CORREO      => 'El correo no es válido: escríbalo completo, por ejemplo impuestos@municipio.gov.co '
                                 . '(hasta 120 caracteres).',
    ];

    /* ==================== LO QUE SE LEE ==================== */

    /** @return string|null La dependencia que atiende, o null si no hay. */
    public static function dependencia()
    {
        $v = self::_validar(self::CLAVE_DEPENDENCIA, Parametros::valor(self::CLAVE_DEPENDENCIA));
        return $v !== null ? $v : self::_dependenciaDeLasConstantes();
    }

    /** @return string|null El teléfono (uno o varios números), o null si no hay. */
    public static function telefono()
    {
        return self::_validar(self::CLAVE_TELEFONO, Parametros::valor(self::CLAVE_TELEFONO));
    }

    /** @return string|null El correo de la dependencia, o null si no hay. */
    public static function correo()
    {
        return self::_validar(self::CLAVE_CORREO, Parametros::valor(self::CLAVE_CORREO));
    }

    /** Segundos que la pantalla de ingreso espera a la base para leer el contacto. */
    const ESPERA_INGRESO = 3;

    /**
     * La dependencia y el teléfono del pie de la pantalla de INGRESO.
     *
     * POR QUÉ NO USA Parametros
     *
     * El ingreso no tocaba la base hasta que el pie empezó a leerla de aquí.
     * Con la base caída o ahogada, la conexión normal espera lo que el driver
     * quiera (15 segundos, medido, aunque la base rechace al instante), y todos
     * los que abran el ingreso en esa caída se quedarían esperando, cada uno con
     * un proceso de PHP ocupado: un ingreso lento haría caer también lo demás.
     *
     * Así que el ingreso lee con una conexión propia que espera ESPERA_INGRESO
     * segundos como mucho. Si no contesta, el pie sale sin esas líneas (o con la
     * dependencia de las constantes del config) y se sigue: es mejor un pie
     * incompleto que un ingreso que no abre. Lo demás es lo mismo que dependencia()
     * y telefono(): la tabla manda, el valor se vuelve a comprobar contra su
     * patrón y, solo para la dependencia, valen las constantes del config.
     *
     * @return array{dependencia: string|null, telefono: string|null}
     */
    public static function paraIngreso()
    {
        $deTabla = [];

        try {
            $origen = \ConexionMysqlUsuariosSqlServer\Conexiones_sqlserver::getConProduccion();
            $c = @\sqlsrv_connect($origen->getServer(), [
                'Database'               => $origen->getDatabase(),
                'UID'                    => $origen->getUsername(),
                'PWD'                    => $origen->getPassword(),
                'CharacterSet'           => 'UTF-8',
                'TrustServerCertificate' => true,
                'LoginTimeout'           => self::ESPERA_INGRESO,
            ]);

            if ($c) {
                $q = \sqlsrv_query(
                    $c,
                    "SELECT par_Clave, par_Valor FROM conf_parametros
                      WHERE ISNULL(par_Estado, 1) = 1 AND par_Clave IN (?, ?)",
                    [self::CLAVE_DEPENDENCIA, self::CLAVE_TELEFONO]
                );
                while ($q && ($f = \sqlsrv_fetch_array($q, \SQLSRV_FETCH_ASSOC))) {
                    $deTabla[$f['par_Clave']] = $f['par_Valor'];
                }
                \sqlsrv_close($c);
            } else {
                error_log('[contacto] ingreso: la base no contestó en ' . self::ESPERA_INGRESO
                        . ' s; el pie sale sin contacto.');
            }
        } catch (\Throwable $e) {
            error_log('[contacto] ingreso: ' . $e->getMessage());
        }

        $dependencia = self::_validar(self::CLAVE_DEPENDENCIA, $deTabla[self::CLAVE_DEPENDENCIA] ?? null);

        return [
            'dependencia' => $dependencia !== null ? $dependencia : self::_dependenciaDeLasConstantes(),
            'telefono'    => self::_validar(self::CLAVE_TELEFONO, $deTabla[self::CLAVE_TELEFONO] ?? null),
        ];
    }

    /**
     * La ciudad del municipio (MUNICIPIO_CIUDAD), para los avisos que dicen "la
     * secretaría de Hacienda del municipio de X". Sin la constante, null: nunca
     * una ciudad por defecto.
     *
     * @return string|null
     */
    public static function ciudad()
    {
        if (!defined('MUNICIPIO_CIUDAD')) { return null; }
        $v = trim((string) constant('MUNICIPIO_CIUDAD'));
        return $v !== '' ? $v : null;
    }

    /** Lo que necesitan las pantallas, de una vez. Cada dato falla por separado. */
    public static function paraPantalla()
    {
        $datos = [];
        foreach (['dependencia', 'telefono', 'correo', 'ciudad'] as $campo) {
            try {
                $datos[$campo] = self::$campo();
            } catch (\Throwable $e) {
                $datos[$campo] = null;
                error_log('[contacto] no se pudo leer ' . $campo . ': ' . $e->getMessage());
            }
        }
        return $datos;
    }

    /**
     * paraPantalla() como JSON listo para meter en un <script> (dist/menu.php
     * lo deja en window.ERP_CONTACTO). JSON_HEX_* escapa < > & ' y ", así que
     * un valor con </script> o comillas no puede salirse del script.
     *
     * @return string
     */
    public static function jsonParaPantalla()
    {
        $json = json_encode(
            self::paraPantalla(),
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE
        );
        return $json === false ? '{}' : $json;
    }

    /* ==================== LO QUE SE VALIDA ==================== */

    /** Espacios de los lados fuera y los de en medio, de uno solo: es lo que se guarda. */
    public static function normalizar($valor)
    {
        $v = preg_replace('/\s+/u', ' ', trim((string) $valor));
        return $v === null ? trim((string) $valor) : $v;
    }

    /**
     * La expresión COMPLETA (con delimitadores) de una de las tres claves.
     *
     * Lleva el modificador u a propósito: el verbo (*UTF8) del patrón hace que
     * se cuenten letras y no bytes, pero PHP NO comprueba entonces que el texto
     * sea UTF-8 válido, y uno con bytes sueltos (lo que manda un cliente hecho
     * a mano) pasaba la validación y reventaba después en el driver de SQL
     * Server. Con la u, PHP lo rechaza antes. Medido en PHP 8.1 / PCRE 10.39.
     *
     * Y la D: sin ella el ancla $ también acepta un salto de línea al final,
     * y "a@dominio.co\n" pasaba por correo válido.
     */
    public static function expresion($clave)
    {
        return '#' . str_replace('#', '\#', self::PATRONES[$clave]) . '#uD';
    }

    /** ¿Este valor (no vacío) cumple lo que se acepta en esa clave? */
    public static function esValido($clave, $valor)
    {
        return isset(self::PATRONES[$clave])
            && @preg_match(self::expresion($clave), (string) $valor) === 1;
    }

    /** El aviso para quien escribió un valor que no cumple. */
    public static function mensajeFormato($clave)
    {
        return self::MENSAJES_FORMATO[$clave] ?? 'El valor no tiene el formato esperado.';
    }

    /* ==================== PRIVADO ==================== */

    /**
     * El valor, recortado, si no está vacío y cumple el patrón de su clave; si
     * no, null. Es lo que todas las lecturas hacen con lo que sale de la tabla.
     */
    private static function _validar($clave, $valor)
    {
        $v = $valor === null ? '' : trim((string) $valor);
        return ($v !== '' && self::esValido($clave, $v)) ? $v : null;
    }

    /**
     * El respaldo de la dependencia: la secretaría y la dependencia tributaria
     * que ya trae config.municipio.php (las mismas que imprimen el RIT y las
     * retenciones), unidas como "Secretaría - Dependencia". Sin ninguna, null.
     */
    private static function _dependenciaDeLasConstantes()
    {
        $partes = [];
        foreach (['MUNICIPIO_SECRETARIA', 'MUNICIPIO_DEPENDENCIA_TRIBUTARIA'] as $constante) {
            if (!defined($constante)) { continue; }
            $v = self::normalizar(constant($constante));
            if ($v !== '') { $partes[] = $v; }
        }
        $v = implode(' - ', $partes);
        return ($v !== '' && self::esValido(self::CLAVE_DEPENDENCIA, $v)) ? $v : null;
    }
}
