<?php
namespace erpsoftsas;

/**
 * Lector del archivo de recaudo Asobancaria.
 *
 * Es un plano de ANCHO FIJO, no un CSV. Cada linea empieza con dos digitos que
 * dicen que tipo de registro es, y de ahi en adelante los campos van por
 * posicion. El formato se confirmo decodificando archivos reales de recaudo del
 * sistema de predial de la empresa: uno de Banco de Bogota con 1.654 registros
 * cuadro al centavo contra su propio registro de control (219.262.936,00).
 *
 *   01  encabezado del archivo
 *       nit(10) fecha(8) banco(3) cuenta(17) fechaArchivo(8) hora(4) mod(1) tipoCta(2)
 *   05  encabezado de lote
 *       codigoServicio EAN-13(13) lote(4)
 *   06  detalle del pago
 *       referencia(48) valor(12+2) procedencia(2) medio(2) operacion(6)
 *       autorizacion(6) bancoDebitado(3) sucursal(4) secuencia(7)
 *   08  control de lote      registros(9) valor(16+2) lote(4)
 *   09  control del archivo  registros(9) valor(16+2)
 *
 * Esta clase SOLO lee y valida. No toca la base de datos: quien concilia es
 * ControladorRecaudo. Separarlo es lo que permite probar el formato contra
 * archivos reales sin montar una base.
 */
class RecaudoAsobancaria
{
    /** El valor viene en centavos partidos: 12 digitos enteros + 2 decimales. */
    const LARGO_VALOR_ENTERO = 12;
    const LARGO_VALOR_DECIMAL = 2;

    /**
     * Lee el archivo completo.
     *
     * Devuelve un arreglo con:
     *   ok            bool
     *   error         string   motivo cuando ok es false
     *   encabezado    nit, fecha, banco, cuenta
     *   ean           codigo del servicio (convenio de recaudo)
     *   detalles[]    referencia, valor, bancoDebitado, operacion, autorizacion, linea
     *   control       registros, valor        (lo que dice el propio archivo)
     *   sumas         registros, valor        (lo que contamos nosotros)
     *   cuadra        bool
     */
    public static function leer($ruta)
    {
        $vacio = [
            'ok' => false, 'error' => '', 'encabezado' => [], 'ean' => '',
            'detalles' => [], 'control' => null,
            'sumas' => ['registros' => 0, 'valor' => 0.0], 'cuadra' => false,
        ];

        if (!is_file($ruta) || !is_readable($ruta)) {
            $vacio['error'] = 'No se pudo abrir el archivo.';
            return $vacio;
        }

        $fh = fopen($ruta, 'r');
        if ($fh === false) {
            $vacio['error'] = 'No se pudo abrir el archivo.';
            return $vacio;
        }

        $r = $vacio;
        $numeroLinea = 0;
        $vioEncabezado = false;

        while (($linea = fgets($fh)) !== false) {

            // Los archivos vienen rellenados con espacios a la derecha y con
            // fin de linea de Windows; ninguna de las dos cosas es un campo.
            $linea = rtrim($linea, "\r\n");
            if (trim($linea) === '') { continue; }

            $numeroLinea++;
            $tipo = substr($linea, 0, 2);

            // La primera linea util TIENE que ser el encabezado. Si no lo es,
            // no es un archivo de recaudo y no vale la pena seguir leyendo.
            //
            // (En predial esta comprobacion usa && donde queria decir ||, asi
            // que un archivo con el tipo correcto pero longitud equivocada
            // pasaba igual. Aqui se valida solo el tipo, que es el criterio
            // que de verdad importa: el relleno de espacios hace que la
            // longitud varie entre bancos -los archivos reales miden 162
            // caracteres, no los 55 del formato base-.)
            if ($numeroLinea === 1 && $tipo !== '01') {
                fclose($fh);
                $r['error'] = 'El archivo no tiene el formato de recaudo Asobancaria: '
                            . 'la primera línea debería ser un encabezado (tipo 01) y es «' . $tipo . '».';
                return $r;
            }

            switch ($tipo) {

                case '01':
                    $vioEncabezado = true;
                    $r['encabezado'] = [
                        'nit'          => trim(substr($linea, 2, 10)),
                        'fecha'        => trim(substr($linea, 12, 8)),   // AAAAMMDD
                        'banco'        => trim(substr($linea, 20, 3)),
                        'cuenta'       => trim(substr($linea, 23, 17)),
                        'fechaArchivo' => trim(substr($linea, 40, 8)),
                        'hora'         => trim(substr($linea, 48, 4)),
                    ];
                    break;

                case '05':
                    // El codigo del servicio es el EAN-13 del convenio: el mismo
                    // numero que se imprime dentro del codigo de barras (AI 415).
                    $r['ean'] = trim(substr($linea, 2, 13));
                    break;

                case '06':
                    $bancoDebitado = trim(substr($linea, 80, 3));

                    // '000' y '999' significan "no aplica / fue el mismo banco
                    // del archivo". No son entidades.
                    if ($bancoDebitado === '000' || $bancoDebitado === '999') {
                        $bancoDebitado = $r['encabezado']['banco'] ?? '';
                    }

                    $valor = self::valorDe($linea, 50);

                    $r['detalles'][] = [
                        'linea'         => $numeroLinea,
                        // La referencia son 48 digitos con ceros a la izquierda;
                        // el numero util es el entero.
                        'referencia'    => ltrim(substr($linea, 2, 48), '0'),
                        'valor'         => $valor,
                        'procedencia'   => trim(substr($linea, 64, 2)),
                        'medio'         => trim(substr($linea, 66, 2)),
                        'operacion'     => trim(substr($linea, 68, 6)),
                        'autorizacion'  => trim(substr($linea, 74, 6)),
                        'bancoDebitado' => $bancoDebitado,
                    ];

                    $r['sumas']['registros']++;
                    $r['sumas']['valor'] += $valor;
                    break;

                case '09':
                    $r['control'] = [
                        'registros' => (int) substr($linea, 2, 9),
                        'valor'     => self::valorDe($linea, 11, 16),
                    ];
                    break;

                // 08 es el control de cada lote. Con archivos de un solo lote
                // es redundante con el 09, y con varios lotes el 09 ya suma
                // todo, asi que no se usa para validar.
                case '08':
                default:
                    break;
            }
        }
        fclose($fh);

        if (!$vioEncabezado) {
            $r['error'] = 'El archivo no trae registro de encabezado (tipo 01).';
            return $r;
        }

        if (!$r['detalles']) {
            $r['error'] = 'El archivo no trae ningún registro de pago (tipo 06).';
            return $r;
        }

        // Comparar contra el registro de control es lo que detecta un archivo
        // truncado o alterado. Predial lo lee pero nunca lo compara; por eso
        // aqui es una validacion y no un dato mas.
        if ($r['control'] === null) {
            $r['error'] = 'El archivo no trae registro de control (tipo 09), '
                        . 'así que no hay forma de comprobar que llegó completo.';
            return $r;
        }

        $difValor = abs($r['control']['valor'] - $r['sumas']['valor']);

        // Un centavo de tolerancia por el redondeo de coma flotante al sumar
        // miles de registros; no es holgura de negocio.
        $r['cuadra'] = ($r['control']['registros'] === $r['sumas']['registros'])
                    && ($difValor < 0.01);

        if (!$r['cuadra']) {
            $r['error'] = sprintf(
                'El archivo no cuadra con su propio registro de control. '
                . 'Dice %d registros por $%s y se leyeron %d por $%s. '
                . 'Probablemente llegó incompleto: pida uno nuevo a la entidad financiera.',
                $r['control']['registros'], number_format($r['control']['valor'], 2, ',', '.'),
                $r['sumas']['registros'],   number_format($r['sumas']['valor'], 2, ',', '.')
            );
            return $r;
        }

        $r['ok'] = true;
        return $r;
    }

    /**
     * Lee un valor partido en enteros + decimales desde una posicion.
     *
     * No se usa floatval() sobre la cadena con coma como hace predial:
     * floatval("300000009631,90") devuelve 300000009631 -se corta en la coma-,
     * o sea que los centavos se perdian.
     */
    private static function valorDe($linea, $desde, $largoEntero = self::LARGO_VALOR_ENTERO)
    {
        $entero  = substr($linea, $desde, $largoEntero);
        $decimal = substr($linea, $desde + $largoEntero, self::LARGO_VALOR_DECIMAL);

        return (float) $entero + ((float) $decimal) / 100;
    }

    /** AAAAMMDD del archivo -> AAAA-MM-DD para la base. */
    public static function fechaAIso($aaaammdd)
    {
        if (!preg_match('/^\d{8}$/', (string) $aaaammdd)) { return null; }

        $a = substr($aaaammdd, 0, 4);
        $m = substr($aaaammdd, 4, 2);
        $d = substr($aaaammdd, 6, 2);

        return checkdate((int) $m, (int) $d, (int) $a) ? "$a-$m-$d" : null;
    }
}
