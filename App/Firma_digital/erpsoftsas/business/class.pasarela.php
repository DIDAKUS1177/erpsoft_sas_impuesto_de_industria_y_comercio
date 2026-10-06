<?php

namespace erpsoftsas;

include_once __DIR__ . '/class.parametros.php';

/**
 * Qué pasarela de pago en línea usa esta entidad, y lo que es igual en todas.
 *
 * Cada municipio cobra con la suya (migración 042, PASARELA_PROVEEDOR): Paipa
 * con AvalPay (PlacetoPay), certificada con Evertec; Macanal con Wompi
 * (Bancolombia). El recorrido es el mismo -resumen (pagar.php), ir a pagar
 * (crearSesion.php), volver (retorno.php), aviso de la pasarela y cron de
 * respaldo- y solo cambia la pieza que habla con la pasarela. Sin el parámetro
 * es PlacetoPay, que es lo que había antes de la 042.
 */
class Pasarela
{
    const PLACETOPAY = 'PLACETOPAY';
    const WOMPI      = 'WOMPI';

    public static function proveedor()
    {
        return strtoupper((string) Parametros::valor('PASARELA_PROVEEDOR')) === self::WOMPI
            ? self::WOMPI : self::PLACETOPAY;
    }

    public static function esWompi()
    {
        return self::proveedor() === self::WOMPI;
    }

    /** ¿Tiene la entidad completas las credenciales de SU pasarela? */
    public static function configurado()
    {
        if (self::esWompi()) {
            require_once __DIR__ . '/class.wompi.php';
            return Wompi::configurado();
        }
        require_once __DIR__ . '/class.placetopay.php';
        return \PlacetoPay::configurado();
    }

    /** Nombre para el contribuyente ("Pago seguro procesado por ..."). */
    public static function nombre()
    {
        return self::esWompi() ? 'Wompi (Bancolombia)' : 'AvalPay (PSE)';
    }

    /** Texto del botón: Wompi no es solo PSE (tarjetas, Nequi...). */
    public static function textoBoton()
    {
        return self::esWompi() ? 'Pagar en línea' : 'Pagar PSE';
    }

    /**
     * ¿Se le ofrece el pago en línea a este usuario?
     *
     * MODO CERTIFICACIÓN: mientras se prueba con las credenciales de PRUEBAS,
     * el parámetro PASARELA_USUARIOS_PRUEBA lista los ids de usuario que SÍ ven
     * el botón; vacío = producción, lo ven todos. Sirve igual para las dos
     * pasarelas (antes vivía en PlacetoPay::botonVisible).
     */
    public static function botonVisible($idUsuario = null)
    {
        if (!self::configurado()) {
            return false;
        }

        $lista = Parametros::valor('PASARELA_USUARIOS_PRUEBA');
        if ($lista === null) {
            // Con las llaves de PRUEBAS de Wompi y sin lista, nadie: un botón de
            // pruebas abierto a todos dejaría "pagar" declaraciones reales con
            // la tarjeta de mentira del sandbox (revisión 2026-10-02).
            if (self::esWompi()) {
                require_once __DIR__ . '/class.wompi.php';
                return Wompi::ambiente() === 'prod';
            }
            return true;
        }

        $ids = array_filter(array_map('trim', explode(',', $lista)), 'strlen');
        return in_array((string) (int) $idUsuario, $ids, true);
    }

    /**
     * Deja en la declaración el resultado que dio la pasarela: SIEMPRE el
     * estado (*_PSE_Estado, migración 014), y el pago solo si se aprobó.
     *
     * Es lo que hacía PlacetoPay::aplicarADeclaracion, ahora para las dos: el
     * pago lo registra PagoDeclaracion, el único sitio que toca esas columnas,
     * con la vía de cada pasarela en *_RutaPago.
     *
     * @return bool true si la declaración quedó marcada como pagada en esta llamada
     */
    public static function aplicarADeclaracion($con, $idDeclaracion, array $info, $valor, $m = null, $via = null)
    {
        require_once __DIR__ . '/class.pseModulo.php';
        require_once __DIR__ . '/class.pagoDeclaracion.php';
        if ($m === null) { $m = PseModulo::get('ica'); }

        // Sesion de PlacetoPay abierta y sin ningun intento de pago: no hay nada
        // nuevo; se deja "Pago iniciado" con su hora (PlacetoPay::interpretarRespuesta).
        if (!empty($info['sinIntento'])) {
            return false;
        }

        // Tabla y columnas salen del mapa fijo de PseModulo, nunca del usuario.
        // Con PlacetoPay, solo sobre la declaracion que sigue teniendo la sesion
        // consultada: el aviso tardio de una ya reemplazada no pisa el estado de
        // la nueva (revision 2026-10-06). Wompi no trae requestId: como siempre.
        $sesion = '';
        $params = [$info['estado'], $info['mensaje'] ?? '', (int) $idDeclaracion];
        if (!empty($info['requestId'])) {
            $sesion   = ' AND ' . PseModulo::colRequestId($m) . ' = ?';
            $params[] = (string) $info['requestId'];
        }
        $con->consultar(
            "UPDATE {$m['tabla']}
                SET " . PseModulo::colEstado($m) . " = ?,
                    " . PseModulo::colFechaEstado($m) . " = GETDATE(),
                    " . PseModulo::colMensaje($m) . " = ?
              WHERE {$m['pk']} = ?" . $sesion,
            $params
        );

        if (empty($info['aprobado'])) {
            return false;
        }

        // Lo que entró de verdad (con los intereses de mora, si los hubo); el
        // total de la declaración solo si la pasarela no lo informa.
        if (isset($info['valor']) && (float) $info['valor'] > 0) {
            $valor = (float) $info['valor'];
        }

        // fechaPago: cuándo pagó según la pasarela (Wompi la informa). Sin ella
        // se usa la de ahora, como siempre se hizo con PSE.
        return PagoDeclaracion::registrar($con, $idDeclaracion, [
            'valor'     => $valor,
            'banco'     => $info['banco'] ?? '',
            'via'       => $via ?? PagoDeclaracion::VIA_PSE,
            'fechaPago' => $info['fechaPago'] ?? null,
        ], $m);
    }
}
