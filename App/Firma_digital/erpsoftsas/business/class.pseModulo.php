<?php

namespace erpsoftsas;

/**
 * Descriptor de cada modulo pagable por PSE: ICA, RETEICA y AUTORRETEICA.
 *
 * POR QUE EXISTE
 *
 * El pago en linea se construyo para el ICA (una sola tabla, columnas dec_*).
 * Al habilitarlo tambien en retencion y autorretencion habia dos caminos:
 * copiar los archivos de pse/ por modulo -y este repo ya sabe lo que cuesta
 * duplicar (las cifras estuvieron copiadas en tres JS y costo dos bugs)- o
 * tener UN flujo parametrizado por modulo. Se eligio lo segundo: aqui viven,
 * en un solo sitio, los nombres de tabla y columnas de cada modulo, y
 * pagar/crearSesion/retorno/webhook/cron los piden a esta clase.
 *
 * Los nombres NO vienen nunca del usuario: se resuelven contra este mapa fijo,
 * asi que interpolarlos en el SQL es seguro (no hay inyeccion posible por aqui).
 */
class PseModulo
{
    private static function mapa()
    {
        return [
            'ica' => [
                'clave'    => 'ica',
                'tabla'    => 'ind_declaraciones_ica',
                'prefijo'  => 'dec',
                'pk'       => 'dec_Id',
                'numero'   => 'dec_NumeroDeclaracion',
                'valor'    => 'dec_ValorConcepto20',   // total a pagar del ICA
                'estado'   => 'dec_Estado',
                'pagado'   => 'dec_Pagado',
                'etiqueta' => 'Impuesto de Industria y Comercio',
                'fd'       => 'ICA',
            ],
            'reteica' => [
                'clave'    => 'reteica',
                'tabla'    => 'ind_reteica',
                'prefijo'  => 'ret',
                'pk'       => 'ret_Id',
                'numero'   => 'ret_NumeroDeclaracion',
                'valor'    => 'ret_ValorConcepto17',   // casilla 17: total a pagar
                'estado'   => 'ret_Estado',
                'pagado'   => 'ret_Pagado',
                'etiqueta' => 'Retención de Industria y Comercio',
                'fd'       => 'RETEICA',
            ],
            'autorreteica' => [
                'clave'    => 'autorreteica',
                'tabla'    => 'ind_autorreteica',
                'prefijo'  => 'aut',
                'pk'       => 'aut_Id',
                'numero'   => 'aut_NumeroDeclaracion',
                'valor'    => 'aut_ValorConcepto23',   // casilla 23: total a pagar del bimestre
                'estado'   => 'aut_Estado',
                'pagado'   => 'aut_Pagado',
                'etiqueta' => 'Autorretención de Industria y Comercio',
                'fd'       => 'AUTORRETEICA',
            ],
        ];
    }

    /** Descriptor del modulo. Si la clave no es valida cae a ICA (compat). */
    public static function get($clave)
    {
        $m = self::mapa();
        $clave = strtolower(trim((string) $clave));
        return $m[$clave] ?? $m['ica'];
    }

    /** ¿La clave corresponde a un modulo conocido? */
    public static function existe($clave)
    {
        return isset(self::mapa()[strtolower(trim((string) $clave))]);
    }

    /** Las tres claves, para el webhook y el cron que recorren los modulos. */
    public static function claves()
    {
        return array_keys(self::mapa());
    }

    /**
     * ¿Puede la sesión pagar por PSE esta declaración? Devuelve el motivo si
     * no, o null.
     *
     * El botón solo se pinta a quien puede, pero pagar.php y crearSesion.php se
     * abren con la URL: sin esto, en certificación cualquiera con el enlace
     * creaba sesiones del ambiente de PRUEBAS sobre declaraciones reales, y el
     * banco de pruebas las aprobaba y las dejaba "pagadas" sin plata. Mismo
     * criterio que los PDF (pdfret_filaAutorizada): la Alcaldía, cualquiera; el
     * contribuyente, solo las suyas. Y el mismo del botón (botonVisible).
     */
    public static function motivoParaNoPagar($con, array $m, $id)
    {
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }

        $usuario = (int) ($_SESSION['id_usuario'] ?? 0);
        if ($usuario <= 0) {
            return 'Inicie sesión para pagar en línea.';
        }
        require_once __DIR__ . '/class.placetopay.php';
        if (!\PlacetoPay::botonVisible($usuario)) {
            return 'El pago en línea todavía no está disponible. Puede pagar en el banco con el recibo de pago.';
        }
        if (in_array((int) ($_SESSION['id_Rol'] ?? 0), [1, 2], true)) {
            return null;
        }

        // Tabla y columnas salen del mapa fijo de arriba, nunca del usuario.
        $fila = $con->obnerFila($con->consultar(
            "SELECT 1 AS x FROM {$m['tabla']}
              WHERE {$m['pk']} = ?
                AND {$m['prefijo']}_IdContribuyente IN (
                    SELECT c.ind_Id FROM ind_contribuyentes c
                    INNER JOIN conf_usuarios u ON u.usu_NumeroDocumento = c.ind_NumeroIdentificacion
                     WHERE u.usu_Id = ?)",
            [(int) $id, $usuario]
        ));
        // "No encontrada" y no "no es suya": no se confirma que exista.
        return $fila ? null : 'Declaración no encontrada.';
    }

    /* --- Nombres de las columnas PSE, derivados del prefijo en un solo sitio --- */
    public static function colRequestId($m)   { return $m['prefijo'] . '_PSE_RequestId'; }
    public static function colEstado($m)      { return $m['prefijo'] . '_PSE_Estado'; }
    public static function colFechaEstado($m) { return $m['prefijo'] . '_PSE_FechaEstado'; }
    public static function colMensaje($m)     { return $m['prefijo'] . '_PSE_Mensaje'; }
}
