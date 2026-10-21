<?php
namespace erpsoftsas;

require_once __DIR__ . '/class.pseModulo.php';
require_once __DIR__ . '/class.bancosPago.php';
require_once __DIR__ . '/class.registroManual.php';

/**
 * Informe de declaraciones presentadas, pagadas o las dos (pedido de la
 * Alcaldía de Paipa, 2026-10-13 y 14): NIT, contribuyente, año, período,
 * número, fecha de presentación, estado, fecha de pago, banco, cuenta contable
 * del pago manual (2026-10-19), valores e intereses (2026-10-20), para
 * descargar en PDF o Excel desde el módulo Informes (extensiones/informePagos.php).
 * Quién puede (alcaldia.informes.pagos) lo decide quien llama.
 *
 * Los pagos con llaves de PRUEBAS de Wompi (WOMPI_PRUEBA) no salen: no entró
 * plata y descuadrarían el total.
 */
class InformePagos
{
    const MESES = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio',
                   'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    const BIMESTRES = ['', 'Enero - Febrero', 'Marzo - Abril', 'Mayo - Junio',
                       'Julio - Agosto', 'Septiembre - Octubre', 'Noviembre - Diciembre'];
    const CANALES = [
        'PSE'              => 'PSE',
        'WOMPI'            => 'Wompi',
        'RECAUDO_BANCARIO' => 'Recaudo',
        'MANUAL'           => 'Manual',
    ];
    /** El medio de un pago manual, corto para la columna "Medio" del PDF. */
    const MEDIOS_CORTOS = [
        'TRANSFERENCIA' => 'Transferencia',
        'CONSIGNACION'  => 'Consignación',
        'VENTANILLA'    => 'Ventanilla',
        'OTRO'          => 'Otro medio',
        'NO_INDICADO'   => 'Manual',
    ];

    /** Columnas de año y período de cada módulo (del mapa fijo, nunca del usuario). */
    private static function columnas(array $m)
    {
        $p = $m['prefijo'];
        return $m['clave'] === 'ica'
            ? ['anio' => 'dec_AnioDeclaracion', 'periodo' => 'dec_MesDeclaracion', 'contribuyente' => 'dec_IdContribuyente']
            : ['anio' => "{$p}_Anio", 'periodo' => "{$p}_Periodo", 'contribuyente' => "{$p}_IdContribuyente"];
    }

    /** "Diciembre", "Enero - Febrero"... */
    public static function nombrePeriodo($clave, $n)
    {
        $n = (int) $n;
        $tabla = $clave === 'autorreteica' ? self::BIMESTRES : self::MESES;
        return $tabla[$n] ?? ($n > 0 ? 'Período ' . $n : '');
    }

    /** Lo que se imprime en la casilla del documento: con DV solo un NIT (tipo 5). */
    public static function documento($numero, $dv, $tipo)
    {
        $numero = trim((string) $numero);
        $dv = trim((string) $dv);
        return ((int) $tipo === 5 && $dv !== '') ? $numero . '-' . $dv : $numero;
    }

    const ESTADOS = ['pagadas', 'sinpagar', 'todas'];

    /**
     * Las presentadas del módulo: pagadas, sin pagar o todas.
     *
     * @param array $f estado ('pagadas' | 'sinpagar' | 'todas'), anio (int|null),
     *                 periodo (int|null), documento (int: NIT o cédula sin DV; null = todos),
     *                 desde / hasta (AAAA-MM-DD o null; 2026-10-20): un rango de fechas,
     *                 de PAGO en las pagadas y de PRESENTACIÓN en las demás
     * @return array filas listas para pintar: las pagadas por fecha de pago, las
     *               demás por fecha de presentación
     */
    public static function filas($con, $clave, array $f)
    {
        if (!PseModulo::existe($clave)) { return []; }
        $m   = PseModulo::get($clave);
        $p   = $m['prefijo'];
        $c   = self::columnas($m);
        $pk  = $m['pk'];

        $conBanco  = BancosPago::hayColumna($con, $m);
        $conManual = RegistroManual::hayTablas($con);

        $estado = in_array($f['estado'] ?? '', self::ESTADOS, true) ? $f['estado'] : 'pagadas';

        // Pagada de verdad: un pago con llaves de PRUEBAS de Wompi no metió plata,
        // así que esa declaración cuenta como sin pagar (antes se excluía entera).
        $pagadaSql = "CASE WHEN ISNULL(d.{$m['pagado']}, 0) = 1 AND ISNULL(d.{$p}_RutaPago, '') <> 'WOMPI_PRUEBA' THEN 1 ELSE 0 END";
        // Corregida: una corrección suya ya se presentó, así que esta ya no se cobra.
        $colCorrige  = $m['clave'] === 'ica' ? 'dec_DeclaracionCorrige' : "{$p}_Corrige";
        $corregidaSql = "CASE WHEN EXISTS (SELECT 1 FROM {$m['tabla']} x
                                            WHERE x.{$colCorrige} = CAST(d.{$m['numero']} AS VARCHAR(30))
                                              AND x.{$m['estado']} = 2) THEN 1 ELSE 0 END";

        $where = ["d.{$m['estado']} = 2"];
        if ($estado === 'pagadas')  { $where[] = "$pagadaSql = 1"; }
        // Sin pagar = lo que falta cobrar: con valor y sin una corrección presentada encima.
        if ($estado === 'sinpagar') { $where[] = "$pagadaSql = 0 AND ISNULL(d.{$m['valor']}, 0) > 0 AND $corregidaSql = 0"; }
        $params = [];
        if (!empty($f['anio']))      { $where[] = "d.{$c['anio']} = ?";    $params[] = (int) $f['anio']; }
        if (!empty($f['periodo']))   { $where[] = "d.{$c['periodo']} = ?"; $params[] = (int) $f['periodo']; }
        // ind_NumeroIdentificacion es INT: el documento va como entero (sin DV).
        if (!empty($f['documento'])) { $where[] = "c.ind_NumeroIdentificacion = ?"; $params[] = (int) $f['documento']; }
        $joinManual = $conManual
            ? "LEFT JOIN ind_pagos_manuales pm ON pm.pma_Modulo = '{$m['clave']}' AND pm.pma_IdDeclaracion = d.$pk"
            : '';
        $colMedio = $conManual ? 'pm.pma_Medio' : 'CAST(NULL AS VARCHAR(20))';
        // La fecha de presentación escrita en el pago manual (049) manda sobre
        // la de la plataforma: es la que la Alcaldía registró como real. Se
        // ordena por la misma que se muestra.
        $colPresentacion = ($conManual && RegistroManual::hayFechaPresentacion($con))
            ? "COALESCE(pm.pma_FechaPresentacion, CAST(d.{$p}_FechaPresentacion AS DATE))"
            : "d.{$p}_FechaPresentacion";
        $orden = $estado === 'pagadas'
            ? "d.{$p}_FechaPago, d.{$m['numero']}"
            : "$colPresentacion, d.{$m['numero']}";
        // La cuenta contable con la que se hizo el ingreso del pago manual (051).
        $colCuenta = ($conManual && BancosPago::hayCuentasContables($con)) ? 'pm.pma_CuentaContable' : 'CAST(NULL AS VARCHAR(70))';
        $joinBanco = $conBanco ? "LEFT JOIN ind_bancos b ON b.ban_Id = d.{$p}_IdBancoPago" : '';
        $colBanco  = $conBanco ? "COALESCE(b.ban_Nombre, d.{$p}_BancoPago)" : "d.{$p}_BancoPago";

        // Rango de fechas (Paipa, 2026-10-20: "entre fechas, por ejemplo del 1 de
        // octubre de 2025 al 7 de noviembre de 2026"): de pago en las pagadas, de
        // presentación (la que se muestra) en las demás. Ambos extremos incluidos.
        $colRango = $estado === 'pagadas' ? "CAST(d.{$p}_FechaPago AS DATE)" : "CAST($colPresentacion AS DATE)";
        if (!empty($f['desde'])) { $where[] = "$colRango >= ?"; $params[] = (string) $f['desde']; }
        if (!empty($f['hasta'])) { $where[] = "$colRango <= ?"; $params[] = (string) $f['hasta']; }

        // Tabla y columnas salen del mapa fijo de PseModulo; los filtros van como parámetros.
        $st = $con->consultar(
            "SELECT d.$pk AS id, d.{$m['numero']} AS numero, d.{$c['anio']} AS anio, d.{$c['periodo']} AS periodo,
                    CONVERT(VARCHAR(10), d.{$p}_FechaPago, 120) AS fechaPago,
                    CONVERT(VARCHAR(10), $colPresentacion, 120) AS fechaPresentacion,
                    $pagadaSql AS pagado, $corregidaSql AS corregida,
                    ISNULL(d.{$m['valor']}, 0) AS valorDeclarado, ISNULL(d.{$p}_ValorPago, 0) AS valorPagado,
                    $colBanco AS banco, d.{$p}_RutaPago AS ruta, $colMedio AS medio, $colCuenta AS cuentaContable,
                    c.ind_NumeroIdentificacion AS doc, c.ind_DV AS dv, c.ind_IdTipoDocumento AS tipoDoc,
                    c.ind_PrimerNombre, c.ind_SegundoNombre, c.ind_PrimerApellido, c.ind_SegundoApellido
               FROM {$m['tabla']} d
               LEFT JOIN ind_contribuyentes c ON c.ind_Id = d.{$c['contribuyente']}
               $joinBanco
               $joinManual
              WHERE " . implode(' AND ', $where) . "
              ORDER BY $orden",
            $params
        );

        $filas = [];
        while ($r = $con->obnerFila($st)) {
            $nombre = trim(implode(' ', array_filter(array_map('trim', [
                (string) $r['ind_PrimerNombre'], (string) $r['ind_SegundoNombre'],
                (string) $r['ind_PrimerApellido'], (string) $r['ind_SegundoApellido'],
            ]))));
            $pagada = (int) $r['pagado'] === 1;
            $estadoTxt = $pagada ? 'Pagada'
                       : ((int) $r['corregida'] === 1 ? 'Corregida'
                       : ((float) $r['valorDeclarado'] <= 0 ? 'Sin valor' : 'Sin pagar'));
            $canal = $pagada ? (self::CANALES[(string) $r['ruta']] ?? 'Sin dato') : '';
            if ($pagada && (string) $r['ruta'] === 'MANUAL' && !empty($r['medio'])) {
                $canal = self::MEDIOS_CORTOS[$r['medio']] ?? $canal;
            }
            // Intereses = lo pagado menos lo que había que pagar (Paipa,
            // 2026-10-20). Nunca negativo: si se pagó menos (una "ya pagada" con el
            // valor escrito a mano), la casilla va vacía (null).
            $diferencia = $pagada ? round((float) $r['valorPagado']) - round((float) $r['valorDeclarado']) : null;
            $filas[] = [
                'documento'      => self::documento($r['doc'], $r['dv'], $r['tipoDoc']),
                'contribuyente'  => $nombre,
                'anio'           => (int) $r['anio'],
                'periodo'        => self::nombrePeriodo($m['clave'], $r['periodo']),
                'numero'         => (string) $r['numero'],
                'fechaPresentacion' => (string) $r['fechaPresentacion'],
                'estado'         => $estadoTxt,
                'fechaPago'      => $pagada ? (string) $r['fechaPago'] : '',
                'banco'          => $pagada ? trim((string) $r['banco']) : '',
                'cuentaContable' => $pagada ? trim((string) $r['cuentaContable']) : '',
                'canal'          => $canal,
                'valorDeclarado' => round((float) $r['valorDeclarado']),
                'valorPagado'    => $pagada ? round((float) $r['valorPagado']) : 0,
                'intereses'      => ($diferencia !== null && $diferencia >= 0) ? $diferencia : null,
            ];
        }
        return $filas;
    }

    /** Nombre del contribuyente (por su documento) para el encabezado del informe. */
    public static function nombrePorDocumento($con, $documento)
    {
        $r = $con->obnerFila($con->consultar(
            "SELECT TOP 1 ind_NumeroIdentificacion AS doc, ind_DV AS dv, ind_IdTipoDocumento AS tipoDoc,
                    ind_PrimerNombre, ind_SegundoNombre, ind_PrimerApellido, ind_SegundoApellido
               FROM ind_contribuyentes WHERE ind_NumeroIdentificacion = ? ORDER BY ind_Id", [(int) $documento]
        ));
        if (!$r) { return 'documento ' . $documento . ' (no registrado)'; }
        $nombre = trim(implode(' ', array_filter(array_map('trim', [
            (string) $r['ind_PrimerNombre'], (string) $r['ind_SegundoNombre'],
            (string) $r['ind_PrimerApellido'], (string) $r['ind_SegundoApellido'],
        ]))));
        return $nombre . ' · ' . self::documento($r['doc'], $r['dv'], $r['tipoDoc']);
    }
}
