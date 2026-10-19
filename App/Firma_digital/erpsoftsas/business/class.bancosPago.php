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

    /* =====================================================================
     * CUENTAS CONTABLES (migración 051)
     *
     * Pedido de Paipa (2026-10-19): el pago manual lleva, además del banco, la
     * cuenta contable con la que se hace el ingreso, como en el sistema
     * anterior. El catálogo es ind_bancos_cuenta (Javier la creó en Paipa): un
     * banco puede tener varias y van por año. Se usan las del año en que se
     * REGISTRA el pago ("Año registro de pago" del sistema anterior).
     * ===================================================================== */

    /** ¿Ya corrió la 051 (catálogo con descripción y columnas del pago manual)? */
    public static function hayCuentasContables($con)
    {
        static $hay = null;
        if ($hay === null) {
            $f = $con->obnerFila($con->consultar(
                "SELECT CASE WHEN COL_LENGTH('dbo.ind_bancos_cuenta', 'descripcion') IS NOT NULL
                              AND COL_LENGTH('dbo.ind_pagos_manuales', 'pma_IdCuentaContable') IS NOT NULL
                             THEN 1 ELSE 0 END AS n", []
            ));
            $hay = (int) ($f['n'] ?? 0) === 1;
        }
        return $hay;
    }

    /** El año de las cuentas contables: el de hoy en Colombia (año de registro del pago). */
    public static function anioCuentas()
    {
        return (int) (new \DateTime('now', new \DateTimeZone('America/Bogota')))->format('Y');
    }

    /**
     * Las cuentas contables de un año, agrupadas por banco.
     *
     * @return array idBanco => [['id' => int, 'cuenta' => string, 'descripcion' => string, 'anio' => int], ...]
     */
    public static function cuentasContables($con, $anio)
    {
        if (!self::hayCuentasContables($con)) { return []; }
        $st = $con->consultar(
            "SELECT Id, Id_banco, ano, LTRIM(RTRIM(cuenta)) AS cuenta, LTRIM(RTRIM(ISNULL(descripcion, ''))) AS descripcion
               FROM ind_bancos_cuenta WHERE ano = ? ORDER BY Id_banco, cuenta", [(int) $anio]
        );
        $r = [];
        while ($f = $con->obnerFila($st)) {
            $r[(int) $f['Id_banco']][] = ['id' => (int) $f['Id'], 'cuenta' => (string) $f['cuenta'],
                                          'descripcion' => (string) $f['descripcion'], 'anio' => (int) $f['ano']];
        }
        return $r;
    }

    /**
     * La cuenta contable de un pago: null si el banco no tiene cuentas ese año
     * (no se pide); la cuenta si la elegida es de ese banco y ese año; si no,
     * el mensaje de error.
     *
     * @return array|string|null
     */
    public static function cuentaDelPago($con, $idBanco, $idCuenta)
    {
        $anio     = self::anioCuentas();
        $cuentas  = self::cuentasContables($con, $anio)[(int) $idBanco] ?? [];
        $idCuenta = trim((string) $idCuenta);
        if (!$cuentas) {
            // Llegó una cuenta y el banco ya no tiene cuentas este año (la
            // página se abrió el 31 de diciembre, o se quitaron): no se guarda
            // el pago sin decirlo.
            return $idCuenta === '' ? null
                : 'La cuenta contable elegida ya no está vigente para ' . $anio . '. Recargue la página (F5) y vuelva a registrar.';
        }
        if (!preg_match('/^[0-9]{1,9}$/', $idCuenta)) {
            return 'Elija la cuenta contable del banco. Si no ve la casilla, recargue la página (F5).';
        }
        foreach ($cuentas as $c) {
            if ($c['id'] === (int) $idCuenta) { return $c; }
        }
        return 'La cuenta contable elegida no es de ese banco para ' . $anio . '. Elíjala de nuevo.';
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
