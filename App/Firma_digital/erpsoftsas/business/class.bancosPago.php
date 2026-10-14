<?php
namespace erpsoftsas;

require_once __DIR__ . '/class.parametros.php';

/**
 * El banco por el que entró cada pago (migración 045).
 *
 * Pedido de la Alcaldía de Paipa (2026-10-13): saber por qué banco entró cada
 * pago, venga por donde venga, con el ID de ind_bancos y no solo un texto.
 * El texto (*_BancoPago) se sigue guardando como siempre; el ID va en
 * *_IdBancoPago:
 *
 *   recaudo por archivo  el banco del archivo (su código Asobancaria)
 *   pago manual          el que se elige en la lista (bancos activos)
 *   pago en línea        PASARELA_BANCO: el banco al que la pasarela le
 *                        consigna a la entidad (Municipio y bancos)
 *
 * Todo funciona sin la 045: si la columna no existe no se escribe.
 */
class BancosPago
{
    /** ¿La tabla de este módulo ya tiene *_IdBancoPago? Se pregunta una vez por petición. */
    public static function hayColumna($con, array $m)
    {
        static $hay = [];
        $t = $m['tabla'];
        if (!isset($hay[$t])) {
            $f = $con->obnerFila($con->consultar(
                "SELECT COL_LENGTH(?, ?) AS n", ['dbo.' . $t, $m['prefijo'] . '_IdBancoPago']
            ));
            $hay[$t] = !empty($f['n']);
        }
        return $hay[$t];
    }

    /** ¿ind_pagos_manuales tiene pma_IdBanco? */
    public static function hayColumnaManual($con)
    {
        static $hay = null;
        if ($hay === null) {
            $f = $con->obnerFila($con->consultar("SELECT COL_LENGTH('dbo.ind_pagos_manuales', 'pma_IdBanco') AS n", []));
            $hay = !empty($f['n']);
        }
        return $hay;
    }

    /*
     * "CAJA" (00) y "PSE" (999) están en el catálogo para el recaudo (migración
     * 006), pero no son bancos: no se ofrecen ni se aceptan como banco del pago.
     */
    const NO_SON_BANCOS = "ban_Codigo NOT IN ('00', '999')";

    /**
     * Los bancos activos para elegir: primero los que tienen cuenta
     * recaudadora (donde la entidad recibe la plata), después los demás.
     *
     * @return array [['id' => int, 'nombre' => string, 'cuenta' => string], ...]
     */
    public static function lista($con)
    {
        $st = $con->consultar(
            "SELECT ban_Id, ban_Nombre, LTRIM(RTRIM(ISNULL(ban_CuentaRecaudadora, ''))) AS cuenta
               FROM ind_bancos
              WHERE ISNULL(ban_Activo, 1) = 1 AND " . self::NO_SON_BANCOS . "
              ORDER BY CASE WHEN LTRIM(RTRIM(ISNULL(ban_CuentaRecaudadora, ''))) = '' THEN 1 ELSE 0 END, ban_Nombre",
            []
        );
        $r = [];
        while ($f = $con->obnerFila($st)) {
            $r[] = ['id' => (int) $f['ban_Id'], 'nombre' => trim((string) $f['ban_Nombre']), 'cuenta' => (string) $f['cuenta']];
        }
        return $r;
    }

    /** Un banco ACTIVO por su id, o null. */
    public static function activo($con, $id)
    {
        $id = (int) $id;
        if ($id <= 0) { return null; }
        $f = $con->obnerFila($con->consultar(
            "SELECT ban_Id, ban_Nombre FROM ind_bancos WHERE ban_Id = ? AND ISNULL(ban_Activo, 1) = 1 AND " . self::NO_SON_BANCOS, [$id]
        ));
        return $f ? ['id' => (int) $f['ban_Id'], 'nombre' => trim((string) $f['ban_Nombre'])] : null;
    }

    /**
     * El banco de los pagos en línea (PASARELA_BANCO), si está elegido y
     * sigue activo; si no, null y el pago queda sin banco, como antes.
     */
    public static function dePasarela($con)
    {
        $v = Parametros::valor('PASARELA_BANCO');
        if ($v === null || !preg_match('/^[0-9]{1,9}$/', $v)) { return null; }
        $b = self::activo($con, (int) $v);
        return $b ? $b['id'] : null;
    }

    /**
     * Al elegir el banco de la pasarela, los pagos en línea que no tienen banco
     * quedan con él: la entidad tiene UNA pasarela y siempre le consignó al
     * mismo banco. Si se CAMBIA (revisión 2026-10-13: elegir mal no tenía
     * arreglo desde la pantalla), los que tenían el anterior pasan al nuevo.
     * Los demás no se tocan.
     *
     * @param int|null $anterior el banco que estaba elegido
     * @return int cuántos cambiaron
     */
    public static function completarPagosEnLinea($con, $idBanco, $anterior = null)
    {
        $anterior = (int) $anterior;
        require_once __DIR__ . '/class.pseModulo.php';
        $total = 0;
        foreach (PseModulo::claves() as $clave) {
            $m = PseModulo::get($clave);
            if (!self::hayColumna($con, $m)) { continue; }
            $p = $m['prefijo'];
            // Tabla y columnas salen del mapa fijo de PseModulo, nunca del usuario.
            $f = $con->obnerFila($con->consultar(
                "SET NOCOUNT ON;
                 UPDATE {$m['tabla']} SET {$p}_IdBancoPago = ?
                  WHERE ({$p}_IdBancoPago IS NULL OR {$p}_IdBancoPago = ?) AND ISNULL({$m['pagado']}, 0) = 1
                    AND {$p}_RutaPago IN ('PSE', 'WOMPI');
                 SELECT @@ROWCOUNT AS n;",
                [(int) $idBanco, $anterior > 0 && $anterior !== (int) $idBanco ? $anterior : -1]
            ));
            $total += (int) ($f['n'] ?? 0);
        }
        return $total;
    }
}
