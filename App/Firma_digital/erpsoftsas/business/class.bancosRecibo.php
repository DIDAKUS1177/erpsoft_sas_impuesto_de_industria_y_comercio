<?php
namespace erpsoftsas;

include_once __DIR__ . '/class.parametros.php';

/**
 * Los bancos del pie del recibo de pago ("Páguese en: BANCOS: ...").
 *
 * DE DÓNDE SALEN
 *
 * De "Cuentas de los bancos" (Parámetros ICA > Municipio y bancos, tabla
 * ind_bancos): cada banco activo con cuenta recaudadora, en orden alfabético,
 * como "NOMBRE (cuenta)". El cliente pidió que las cuentas sean dinámicas
 * (2026-09-30): si una cambia, la Alcaldía la cambia ahí misma sin pedírsela a
 * nadie, y para sacar un banco del recibo deja su cuenta vacía.
 *
 * Antes (039) era un texto libre en el parámetro RECIBO_BANCOS, y la tabla de
 * la misma pantalla no hacía nada en el recibo. La migración 041 pasó esas
 * cuentas a la tabla y apagó el parámetro. Si ningún banco tiene cuenta y el
 * parámetro sigue activo (una base donde la 041 aún no corrió), se usa el
 * parámetro, para que el recibo no pierda los bancos mientras tanto.
 *
 * Si la tabla no se puede leer, lo mismo: se registra y se sigue con el
 * parámetro (como en class.parametros.php, un recibo sin esa línea es mejor que
 * un recibo que no sale).
 */
class BancosRecibo
{
    /** @return string|null "BANCO (cuenta), BANCO (cuenta)" o null si no hay ninguno. */
    public static function texto()
    {
        $partes = [];

        try {
            $con  = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
            $stmt = $con->consultar(
                "SELECT ban_Nombre, ban_CuentaRecaudadora
                   FROM ind_bancos
                  WHERE ban_Activo = 1
                    AND NULLIF(LTRIM(RTRIM(ban_CuentaRecaudadora)), '') IS NOT NULL
                  ORDER BY ban_Nombre",
                []
            );
            while ($f = $con->obnerFila($stmt)) {
                $partes[] = trim($f['ban_Nombre']) . ' (' . trim($f['ban_CuentaRecaudadora']) . ')';
            }
        } catch (\Throwable $e) {
            error_log('[bancosRecibo] no se pudieron leer los bancos: ' . $e->getMessage());
        }

        if ($partes) {
            return implode(', ', $partes);
        }

        return Parametros::valorOConstante('RECIBO_BANCOS', 'MUNICIPIO_BANCOS_RECIBO');
    }
}
