<?php
namespace erpsoftsas;

require_once __DIR__ . '/class.pseModulo.php';
require_once __DIR__ . '/class.bancosPago.php';
require_once __DIR__ . '/class.registroManual.php';

/**
 * Informe de declaraciones PAGADAS (pedido de la Alcaldía de Paipa,
 * 2026-10-13): NIT, contribuyente, año, período, número, fecha de pago, banco
 * y valor, para descargar en PDF o Excel (extensiones/informePagos.php).
 *
 * La Alcaldía con el permiso alcaldia.informes.pagos lo saca de todos los
 * contribuyentes; los demás, solo del contribuyente que pueden operar. Esa
 * regla la aplica quien llama; aquí solo se arma la consulta.
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
        'RECAUDO_BANCARIO' => 'Banco (recaudo)',
        'MANUAL'           => 'Manual',
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

    /**
     * Las pagadas del módulo.
     *
     * @param array $f anio (int|null), periodo (int|null), contribuyente (int|null = todos)
     * @return array filas listas para pintar, en orden de fecha de pago
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

        $where  = ["ISNULL(d.{$m['pagado']}, 0) = 1", "d.{$m['estado']} = 2", "ISNULL(d.{$p}_RutaPago, '') <> 'WOMPI_PRUEBA'"];
        $params = [];
        if (!empty($f['anio']))          { $where[] = "d.{$c['anio']} = ?";          $params[] = (int) $f['anio']; }
        if (!empty($f['periodo']))       { $where[] = "d.{$c['periodo']} = ?";       $params[] = (int) $f['periodo']; }
        if (!empty($f['contribuyente'])) { $where[] = "d.{$c['contribuyente']} = ?"; $params[] = (int) $f['contribuyente']; }

        $joinManual = $conManual
            ? "LEFT JOIN ind_pagos_manuales pm ON pm.pma_Modulo = '{$m['clave']}' AND pm.pma_IdDeclaracion = d.$pk"
            : '';
        $colMedio = $conManual ? 'pm.pma_Medio' : 'CAST(NULL AS VARCHAR(20))';
        $joinBanco = $conBanco ? "LEFT JOIN ind_bancos b ON b.ban_Id = d.{$p}_IdBancoPago" : '';
        $colBanco  = $conBanco ? "COALESCE(b.ban_Nombre, d.{$p}_BancoPago)" : "d.{$p}_BancoPago";

        // Tabla y columnas salen del mapa fijo de PseModulo; los filtros van como parámetros.
        $st = $con->consultar(
            "SELECT d.$pk AS id, d.{$m['numero']} AS numero, d.{$c['anio']} AS anio, d.{$c['periodo']} AS periodo,
                    CONVERT(VARCHAR(10), d.{$p}_FechaPago, 120) AS fechaPago,
                    ISNULL(d.{$m['valor']}, 0) AS valorDeclarado, ISNULL(d.{$p}_ValorPago, 0) AS valorPagado,
                    $colBanco AS banco, d.{$p}_RutaPago AS ruta, $colMedio AS medio,
                    c.ind_NumeroIdentificacion AS doc, c.ind_DV AS dv, c.ind_IdTipoDocumento AS tipoDoc,
                    c.ind_PrimerNombre, c.ind_SegundoNombre, c.ind_PrimerApellido, c.ind_SegundoApellido
               FROM {$m['tabla']} d
               LEFT JOIN ind_contribuyentes c ON c.ind_Id = d.{$c['contribuyente']}
               $joinBanco
               $joinManual
              WHERE " . implode(' AND ', $where) . "
              ORDER BY d.{$p}_FechaPago, d.{$m['numero']}",
            $params
        );

        $filas = [];
        while ($r = $con->obnerFila($st)) {
            $nombre = trim(implode(' ', array_filter(array_map('trim', [
                (string) $r['ind_PrimerNombre'], (string) $r['ind_SegundoNombre'],
                (string) $r['ind_PrimerApellido'], (string) $r['ind_SegundoApellido'],
            ]))));
            $canal = self::CANALES[(string) $r['ruta']] ?? 'Sin dato';
            if ((string) $r['ruta'] === 'MANUAL' && !empty($r['medio'])) {
                $canal = RegistroManual::MEDIOS[$r['medio']] ?? $canal;
            }
            $filas[] = [
                'documento'      => self::documento($r['doc'], $r['dv'], $r['tipoDoc']),
                'contribuyente'  => $nombre,
                'anio'           => (int) $r['anio'],
                'periodo'        => self::nombrePeriodo($m['clave'], $r['periodo']),
                'numero'         => (string) $r['numero'],
                'fechaPago'      => (string) $r['fechaPago'],
                'banco'          => trim((string) $r['banco']),
                'canal'          => $canal,
                'valorDeclarado' => round((float) $r['valorDeclarado']),
                'valorPagado'    => round((float) $r['valorPagado']),
            ];
        }
        return $filas;
    }

    /** Nombre del contribuyente para el encabezado del informe. */
    public static function nombreContribuyente($con, $id)
    {
        $r = $con->obnerFila($con->consultar(
            "SELECT ind_NumeroIdentificacion AS doc, ind_DV AS dv, ind_IdTipoDocumento AS tipoDoc,
                    ind_PrimerNombre, ind_SegundoNombre, ind_PrimerApellido, ind_SegundoApellido
               FROM ind_contribuyentes WHERE ind_Id = ?", [(int) $id]
        ));
        if (!$r) { return ''; }
        $nombre = trim(implode(' ', array_filter(array_map('trim', [
            (string) $r['ind_PrimerNombre'], (string) $r['ind_SegundoNombre'],
            (string) $r['ind_PrimerApellido'], (string) $r['ind_SegundoApellido'],
        ]))));
        return $nombre . ' · ' . self::documento($r['doc'], $r['dv'], $r['tipoDoc']);
    }
}
