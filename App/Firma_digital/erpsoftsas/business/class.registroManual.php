<?php

namespace erpsoftsas;

require_once __DIR__ . '/class.pseModulo.php';
require_once __DIR__ . '/class.pagoDeclaracion.php';
require_once __DIR__ . '/class.bancosPago.php';
require_once __DIR__ . '/class.vencimientoICA.php';

/**
 * Lo que registra la Alcaldía a mano sobre una declaración (migración 044).
 *
 *   - registrarHistorica: un borrador ya llenado con el formulario de siempre
 *     queda PRESENTADO y PAGADO, con la fecha y el número del papel, los PDF de
 *     la declaración original y del soporte de pago. Es la declaración que se
 *     presentó y pagó fuera de la plataforma (Juan, Paipa, 2026-10-09).
 *   - registrarPago: una declaración presentada y sin pagar queda pagada con
 *     un pago hecho por transferencia, consignación..., con su soporte.
 *
 * Los dos saltan la firma por código: el documento ya existe en papel. Por eso
 * solo la Alcaldía con su permiso (alcaldia.declaraciones.historicas /
 * alcaldia.pagos.manual) y queda quién y cuándo en ind_declaraciones_historicas
 * e ind_pagos_manuales. El pago entra por PagoDeclaracion::registrar con la vía
 * MANUAL, como cualquier otro: las mismas columnas en los tres módulos.
 *
 * Los PDF se guardan bajo la carpeta de los anexos (anexos_establecimientos/
 * declaraciones/<modulo>_<id>), que ya está cerrada al acceso directo por el
 * .htaccess (Apache) y el web.config de App/Firma_digital (IIS). Se entregan
 * solo por extensiones/soporte.php.
 *
 * Nada va en las tablas de las declaraciones: una corrección del ICA copia
 * todas sus columnas y heredaría el "ya pagada" o los soportes.
 */
class RegistroManual
{
    const MEDIOS = [
        'TRANSFERENCIA' => 'Transferencia',
        'CONSIGNACION'  => 'Consignación',
        'VENTANILLA'    => 'Pago en ventanilla',
        'OTRO'          => 'Otro medio',
    ];

    /**
     * El pago manual (no el de una "ya pagada") pide solo fecha, banco y
     * soporte desde el 2026-10-14 (Paipa): su medio se guarda así (047).
     */
    const SIN_MEDIO = 'NO_INDICADO';

    const TIPO_DECLARACION = 'declaracion_original';
    const TIPO_SOPORTE     = 'soporte_pago';

    const TAMANO_MAXIMO = 10485760; // 10 MB, como los anexos

    /** Hoy en Colombia (AAAA-MM-DD). */
    public static function hoy()
    {
        return (new \DateTime('now', new \DateTimeZone('America/Bogota')))->format('Y-m-d');
    }

    /* =====================================================================
     * LOS DOS REGISTROS
     * ===================================================================== */

    /**
     * Borrador → presentada y pagada.
     *
     * @param array $datos   numeroPapel, fechaPresentacion, fechaPago, valor,
     *                       medio, idBanco, referencia, observacion
     * @param array $archivos 'declaracion' y 'soporte' de $_FILES
     * @return array ['ok' => bool, 'mensaje' => string]
     */
    public static function registrarHistorica($con, $clave, $id, array $datos, array $archivos, $idUsuario)
    {
        if (!PseModulo::existe($clave)) { return self::_no('Módulo no válido.'); }
        $m  = PseModulo::get($clave);
        $id = (int) $id;

        $pago = self::_leerPago($con, $datos);
        if (is_string($pago)) { return self::_no($pago); }

        $papel = trim((string) ($datos['numeroPapel'] ?? ''));
        if (!preg_match('#^[A-Za-z0-9][A-Za-z0-9 ./-]{0,29}$#', $papel)) {
            return self::_no('Escriba el número que tiene la declaración en papel (letras, números, puntos, guiones o barras; hasta 30).');
        }
        $presentacion = self::_fecha($datos['fechaPresentacion'] ?? '', 'de presentación');
        if (is_array($presentacion)) { return $presentacion; }
        if ($pago['fecha'] < $presentacion) {
            // No se bloquea por regla de negocio dudosa: un anticipo se paga
            // antes de presentar. Pero casi siempre es un error de digitación.
            // Se acepta solo si viene confirmado desde la pantalla.
            if (empty($datos['confirmaFechas'])) {
                return self::_no('La fecha de pago es anterior a la de presentación. Revísela; si es correcta, confírmelo.', 'FECHAS');
            }
        }

        $decl = self::_archivo($archivos['declaracion'] ?? null, 'la declaración original');
        if (is_string($decl)) { return self::_no($decl); }
        $sop  = self::_archivo($archivos['soporte'] ?? null, 'el soporte de pago');
        if (is_string($sop)) { return self::_no($sop); }

        $fila = self::_fila($con, $m, $id);
        if (!$fila) { return self::_no('Declaración no encontrada.'); }
        if ((int) $fila['estado'] === 2) { return self::_no('Esa declaración ya está presentada. Para pagarla use "Registrar pago manual".'); }
        if ((int) $fila['pagado'] === 1) { return self::_no('Esa declaración ya figura como pagada.'); }
        if ($m['clave'] === 'ica' && self::_actividadesIca($con, $id) === 0) {
            return self::_no('Agregue las actividades y guarde la declaración antes de registrarla.');
        }
        // Un borrador recién creado, sin valores, quedaría "presentado y pagado"
        // en $0 con el valor que se escriba: se llena y se guarda antes.
        if ((float) $fila['total'] <= 0) {
            return self::_no('El total a pagar está en $0: llene la declaración con los valores del papel y guárdela antes de registrarla.');
        }

        $guardados = [];
        try {
            $guardados[] = self::_guardarPdf($m, $id, $decl);
            $guardados[] = self::_guardarPdf($m, $id, $sop);
        } catch (\Throwable $e) {
            self::_borrar($guardados);
            error_log('[registro manual] no se pudo guardar el PDF: ' . $e->getMessage());
            return self::_no('No se pudieron guardar los PDF. Intente de nuevo; si persiste, avise a soporte.');
        }

        try {
            $con->begin();
            if (!self::_candado($con, $m, $id)) {
                $con->rollback();
                self::_borrar($guardados);
                return self::_no('Otra persona está registrando un pago de esta declaración. Intente de nuevo en un minuto.');
            }

            // Bajo el candado: que siga siendo un borrador sin pagar.
            $fila = self::_fila($con, $m, $id);
            if (!$fila || (int) $fila['estado'] === 2 || (int) $fila['pagado'] === 1) {
                $con->rollback();
                self::_borrar($guardados);
                return self::_no('La declaración cambió mientras se registraba. Recargue la lista y revise su estado.');
            }

            // Presentada con la fecha del papel. Sin firma: el original es el PDF.
            // Si no tocó la fila (la presentaron o la borraron en el intervalo),
            // no se sigue: registrar() vería "presentada" y la pagaría igual.
            $p = $m['prefijo'];
            $hecho = $con->obnerFila($con->consultar(
                "SET NOCOUNT ON;
                 UPDATE {$m['tabla']} SET {$p}_Estado = 2, {$p}_FechaPresentacion = ?
                  WHERE {$m['pk']} = ? AND ISNULL({$p}_Estado, 0) <> 2 AND ISNULL({$m['pagado']}, 0) = 0;
                 SELECT @@ROWCOUNT AS n;",
                [$presentacion, $id]
            ));
            if ((int) ($hecho['n'] ?? 0) !== 1) {
                $con->rollback();
                self::_borrar($guardados);
                return self::_no('La declaración cambió mientras se registraba. Recargue la lista y revise su estado.');
            }

            $idSop = self::_insertarSoportes($con, $m, $id, $guardados, $idUsuario);

            if (!self::_registrarPago($con, $m, $id, $pago, $idSop[self::TIPO_SOPORTE] ?? null, $idUsuario)) {
                $con->rollback();
                self::_borrar($guardados);
                return self::_no('No se pudo marcar como pagada; no quedó ningún cambio. Recargue la lista y revise su estado.');
            }

            $con->consultar(
                "INSERT INTO ind_declaraciones_historicas
                    (his_Modulo, his_IdDeclaracion, his_NumeroPapel, his_FechaPresentacion, his_Observacion, his_IdUsuario)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$m['clave'], $id, $papel, $presentacion, $pago['observacion'], (int) $idUsuario]
            );

            $con->commit();
        } catch (\Throwable $e) {
            try { $con->rollback(); } catch (\Throwable $e2) { /* ya cerrada */ }
            self::_borrar($guardados);
            error_log('[registro manual] histórica ' . $m['clave'] . ' ' . $id . ': ' . $e->getMessage());
            return self::_no('No se pudo registrar: no quedó ningún cambio. Intente de nuevo.');
        }

        error_log(sprintf('[registro manual] usuario %d registró la %s %d como presentada y pagada (papel N° %s)',
            (int) $idUsuario, $m['clave'], $id, $papel));

        return ['ok' => true, 'mensaje' => 'Declaración registrada como presentada y pagada.'];
    }

    /**
     * Presentada sin pagar → pagada, con su soporte.
     *
     * Pide solo la fecha de pago, el banco y el soporte (Paipa, 2026-10-14):
     * el valor es el total a pagar de la declaración y el medio queda
     * SIN_MEDIO. Lo demás que llegue se ignora.
     *
     * @param array $datos fechaPago, idBanco
     */
    public static function registrarPago($con, $clave, $id, array $datos, array $archivos, $idUsuario)
    {
        if (!PseModulo::existe($clave)) { return self::_no('Módulo no válido.'); }
        $m  = PseModulo::get($clave);
        $id = (int) $id;

        $pago = self::_leerPago($con, $datos, true);
        if (is_string($pago)) { return self::_no($pago); }

        $sop = self::_archivo($archivos['soporte'] ?? null, 'el soporte de pago');
        if (is_string($sop)) { return self::_no($sop); }

        $fila = self::_fila($con, $m, $id);
        if (!$fila) { return self::_no('Declaración no encontrada.'); }
        if ((int) $fila['estado'] !== 2) { return self::_no('Solo se registra el pago de una declaración presentada.'); }
        if ((int) $fila['pagado'] === 1) { return self::_no('Esa declaración ya figura como pagada.'); }
        if ((float) $fila['total'] <= 0) { return self::_no('Esta declaración no tiene valor a pagar.'); }
        $intereses = self::_intereses($m, $fila, $pago['fecha'], $datos['intereses'] ?? '');
        if (is_string($intereses)) { return self::_no($intereses); }
        // La fecha de pago es lo único que se escribe: antes de la presentación
        // casi siempre es un error de digitación (un año equivocado). Se acepta
        // solo confirmado, como en "Ya pagada".
        if (!empty($fila['presentada']) && $pago['fecha'] < $fila['presentada'] && empty($datos['confirmaFechas'])) {
            return self::_no('La fecha de pago es anterior a la presentación de la declaración (' . $fila['presentada']
                           . '). Revísela; si es correcta, confírmelo.', 'FECHAS');
        }
        $tramite = self::_pagoEnLinea($con, $m, $id, $fila);
        if ($tramite !== null) { return self::_no($tramite); }

        $guardados = [];
        try {
            $guardados[] = self::_guardarPdf($m, $id, $sop);
        } catch (\Throwable $e) {
            error_log('[registro manual] no se pudo guardar el PDF: ' . $e->getMessage());
            return self::_no('No se pudo guardar el PDF. Intente de nuevo; si persiste, avise a soporte.');
        }

        try {
            $con->begin();
            if (!self::_candado($con, $m, $id)) {
                $con->rollback();
                self::_borrar($guardados);
                return self::_no('Otra persona está registrando un pago de esta declaración. Intente de nuevo en un minuto.');
            }

            // Bajo el candado, otra vez: otro funcionario pudo pagarla, o el
            // contribuyente abrir un pago en línea, mientras se subía el PDF.
            $fila = self::_fila($con, $m, $id);
            if (!$fila || (int) $fila['estado'] !== 2 || (int) $fila['pagado'] === 1) {
                $con->rollback();
                self::_borrar($guardados);
                return self::_no(($fila && (int) $fila['pagado'] === 1)
                    ? 'Esa declaración ya figura como pagada.'
                    : 'La declaración ya no está presentada y sin pagar. Recargue la lista y revise su estado.');
            }
            $tramite = self::_pagoEnLinea($con, $m, $id, $fila);
            if ($tramite !== null) {
                $con->rollback();
                self::_borrar($guardados);
                return self::_no($tramite);
            }
            // El valor es el total de la declaración, leído bajo el candado, más
            // los intereses de mora si los hubo (ICA vencida).
            $pago['valor'] = round((float) $fila['total'], 2) + $intereses;
            if ($intereses > 0) {
                $pago['observacion'] = 'Intereses de mora: $ ' . number_format($intereses, 0, ',', '.');
            }
            if ($pago['valor'] <= 0) {
                $con->rollback();
                self::_borrar($guardados);
                return self::_no('Esta declaración no tiene valor a pagar.');
            }

            $idSop = self::_insertarSoportes($con, $m, $id, $guardados, $idUsuario);

            // registrar() exige presentada y sin pagar en el mismo UPDATE.
            if (!self::_registrarPago($con, $m, $id, $pago, $idSop[self::TIPO_SOPORTE] ?? null, $idUsuario)) {
                $con->rollback();
                self::_borrar($guardados);
                return self::_no('La declaración ya no está presentada y sin pagar. Recargue la lista y revise su estado.');
            }

            $con->commit();
        } catch (\Throwable $e) {
            try { $con->rollback(); } catch (\Throwable $e2) { /* ya cerrada */ }
            self::_borrar($guardados);
            error_log('[registro manual] pago ' . $m['clave'] . ' ' . $id . ': ' . $e->getMessage());
            return self::_no('No se pudo registrar el pago: no quedó ningún cambio. Intente de nuevo.');
        }

        error_log(sprintf('[registro manual] usuario %d registró un pago manual de %s a la %s %d',
            (int) $idUsuario, $pago['valor'], $m['clave'], $id));

        return ['ok' => true, 'mensaje' => 'Pago registrado. La declaración quedó pagada.'];
    }

    /**
     * "Eliminar pago" en pantalla (se llamó "Anular" hasta el 2026-10-13, a
     * pedido de la Alcaldía): deshace un registro manual hecho por error
     * (permiso alcaldia.registro.anular, Diego, 2026-10-09):
     *   - ya pagada (histórica): vuelve a BORRADOR, sin presentación ni pago;
     *   - pago manual: se quita el pago y queda presentada sin pagar.
     * Solo pagos con vía MANUAL: uno de PSE, Wompi o del banco no se toca
     * aquí. Con motivo obligatorio; lo que había queda en
     * ind_registros_manuales_anulados y los PDF se desactivan, no se borran.
     */
    public static function anular($con, $clave, $id, $motivo, $idUsuario)
    {
        if (!PseModulo::existe($clave)) { return self::_no('Módulo no válido.'); }
        $m  = PseModulo::get($clave);
        $id = (int) $id;
        $p  = $m['prefijo'];

        $motivo = trim((string) $motivo);
        if (mb_strlen($motivo) < 10 || mb_strlen($motivo) > 500) {
            return self::_no('Escriba por qué se elimina el pago (entre 10 y 500 caracteres).');
        }

        try {
            $con->begin();
            if (!self::_candado($con, $m, $id)) {
                $con->rollback();
                return self::_no('Otra persona está registrando un pago de esta declaración. Intente de nuevo en un minuto.');
            }

            $fila = $con->obnerFila($con->consultar(
                "SELECT {$m['numero']} AS numero, {$p}_RutaPago AS ruta, ISNULL({$m['pagado']}, 0) AS pagado,
                        CONVERT(VARCHAR(19), {$p}_FechaPresentacion, 120) AS presentada,
                        CONVERT(VARCHAR(10), {$p}_FechaPago, 120) AS fechaPago, {$p}_ValorPago AS valorPago,
                        {$p}_BancoPago AS bancoPago"
                      . (BancosPago::hayColumna($con, $m) ? ", {$p}_IdBancoPago AS idBancoPago" : '') . "
                   FROM {$m['tabla']} WHERE {$m['pk']} = ?", [$id]
            ));
            $pm  = $con->obnerFila($con->consultar(
                "SELECT pma_Id, CONVERT(VARCHAR(10), pma_FechaPago, 120) AS fecha, pma_Valor AS valor, pma_Medio AS medio,
                        pma_Banco AS banco, pma_Referencia AS referencia, pma_Observacion AS observacion, pma_IdUsuario AS usuario"
                      . (BancosPago::hayColumnaManual($con) ? ', pma_IdBanco AS idBanco' : '') . "
                   FROM ind_pagos_manuales WHERE pma_Modulo = ? AND pma_IdDeclaracion = ?", [$m['clave'], $id]
            ));
            $his = $con->obnerFila($con->consultar(
                "SELECT his_Id, his_NumeroPapel AS numeroPapel, CONVERT(VARCHAR(10), his_FechaPresentacion, 120) AS fechaPresentacion,
                        his_IdUsuario AS usuario
                   FROM ind_declaraciones_historicas WHERE his_Modulo = ? AND his_IdDeclaracion = ?", [$m['clave'], $id]
            ));

            if (!$fila || !$pm) {
                $con->rollback();
                return self::_no('Esta declaración no tiene un pago manual que eliminar.');
            }
            if ((string) $fila['ruta'] !== PagoDeclaracion::VIA_MANUAL) {
                $con->rollback();
                return self::_no('El pago de esta declaración no es manual (entró por ' . ($fila['ruta'] ?: 'otra vía') . '): no se elimina aquí.');
            }

            // Una ya pagada con corrección encima no vuelve a borrador: la
            // corrección quedaría corrigiendo un borrador.
            if ($his) {
                $colCorrige = $m['clave'] === 'ica' ? 'dec_DeclaracionCorrige' : "{$p}_Corrige";
                $corr = $con->obnerFila($con->consultar(
                    "SELECT COUNT(*) AS n FROM {$m['tabla']} WHERE {$colCorrige} = ?", [(string) $fila['numero']]
                ));
                if ((int) ($corr['n'] ?? 0) > 0) {
                    $con->rollback();
                    return self::_no('Esta declaración tiene una corrección. Bórrela, o elimine su pago, primero.');
                }
            }

            // Sin pago; la histórica además vuelve a borrador.
            $hecho = $con->obnerFila($con->consultar(
                "SET NOCOUNT ON;
                 UPDATE {$m['tabla']}
                    SET {$m['pagado']} = 0, {$p}_FechaPago = NULL, {$p}_FechaRealPago = NULL, {$p}_ValorPago = NULL,
                        {$p}_BancoPago = NULL, {$p}_AnioPago = NULL, {$p}_RutaPago = NULL"
                      . (BancosPago::hayColumna($con, $m) ? ", {$p}_IdBancoPago = NULL" : '')
                      . ($his ? ", {$p}_Estado = NULL, {$p}_FechaPresentacion = NULL" : '') . "
                  WHERE {$m['pk']} = ? AND {$p}_RutaPago = ?;
                 SELECT @@ROWCOUNT AS n;",
                [$id, PagoDeclaracion::VIA_MANUAL]
            ));
            if ((int) ($hecho['n'] ?? 0) !== 1) {
                $con->rollback();
                return self::_no('La declaración cambió mientras se eliminaba el pago. Recargue la lista y revise su estado.');
            }

            $datos = ['declaracion' => $fila, 'pagoManual' => $pm, 'historica' => $his ?: null];
            $con->consultar(
                "INSERT INTO ind_registros_manuales_anulados
                    (anu_Modulo, anu_IdDeclaracion, anu_Tipo, anu_Motivo, anu_Datos, anu_IdUsuario)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$m['clave'], $id, $his ? 'HISTORICA' : 'PAGO', $motivo,
                 json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (int) $idUsuario]
            );
            $con->consultar(
                "UPDATE ind_declaracion_soportes SET sop_Activo = 0 WHERE sop_Modulo = ? AND sop_IdDeclaracion = ?",
                [$m['clave'], $id]
            );
            $con->consultar("DELETE FROM ind_pagos_manuales WHERE pma_Id = ?", [(int) $pm['pma_Id']]);
            if ($his) {
                $con->consultar("DELETE FROM ind_declaraciones_historicas WHERE his_Id = ?", [(int) $his['his_Id']]);
            }

            $con->commit();
        } catch (\Throwable $e) {
            try { $con->rollback(); } catch (\Throwable $e2) { /* ya cerrada */ }
            error_log('[registro manual] anular ' . $m['clave'] . ' ' . $id . ': ' . $e->getMessage());
            return self::_no('No se pudo eliminar el pago: no quedó ningún cambio. Intente de nuevo.');
        }

        error_log(sprintf('[registro manual] usuario %d eliminó el pago manual de la %s %d: %s',
            (int) $idUsuario, $m['clave'], $id, $motivo));

        return ['ok' => true, 'mensaje' => $his
            ? 'Pago eliminado: la declaración, que se había registrado ya pagada, volvió a borrador.'
            : 'Pago eliminado: la declaración quedó presentada sin pagar.'];
    }

    /* =====================================================================
     * LO QUE VEN LAS PANTALLAS
     * ===================================================================== */

    /**
     * Por cada id: si es histórica (y su número en papel), si tiene pago
     * manual (y su medio) y sus soportes. Una consulta por tabla, para el
     * listado entero. Sin la migración 044 devuelve vacío.
     *
     * @return array id => ['historica' => ?array, 'pagoManual' => ?array, 'soportes' => array]
     */
    public static function resumenes($con, $clave, array $ids)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids || !PseModulo::existe($clave) || !self::hayTablas($con)) { return []; }
        $clave = PseModulo::get($clave)['clave'];
        $lista = implode(',', $ids);   // enteros: seguro interpolarlos

        $r = [];
        $st = $con->consultar(
            "SELECT his_IdDeclaracion AS id, his_NumeroPapel AS papel,
                    CONVERT(VARCHAR(10), his_FechaPresentacion, 120) AS fecha
               FROM ind_declaraciones_historicas
              WHERE his_Modulo = ? AND his_IdDeclaracion IN ($lista)", [$clave]
        );
        while ($f = $con->obnerFila($st)) {
            $r[(int) $f['id']]['historica'] = ['numeroPapel' => $f['papel'], 'fechaPresentacion' => $f['fecha']];
        }
        $st = $con->consultar(
            "SELECT pma_IdDeclaracion AS id, pma_Medio AS medio, pma_Referencia AS ref, pma_Banco AS banco
               FROM ind_pagos_manuales
              WHERE pma_Modulo = ? AND pma_IdDeclaracion IN ($lista)", [$clave]
        );
        while ($f = $con->obnerFila($st)) {
            // Sin medio (pago manual desde el 2026-10-14) la nota dice el banco.
            $r[(int) $f['id']]['pagoManual'] = [
                'medio' => self::MEDIOS[$f['medio']] ?? (string) $f['banco'], 'referencia' => (string) $f['ref'],
            ];
        }
        $st = $con->consultar(
            "SELECT sop_IdDeclaracion AS id, sop_Id AS sop, sop_Tipo AS tipo, sop_NombreOriginal AS nombre
               FROM ind_declaracion_soportes
              WHERE sop_Modulo = ? AND sop_IdDeclaracion IN ($lista) AND sop_Activo = 1
              ORDER BY sop_Id", [$clave]
        );
        while ($f = $con->obnerFila($st)) {
            $r[(int) $f['id']]['soportes'][] = ['id' => (int) $f['sop'], 'tipo' => $f['tipo'], 'nombre' => $f['nombre']];
        }
        return $r;
    }

    /**
     * Los intereses de mora del pago manual (Diego, 2026-10-15). Una ICA
     * VENCIDA en la fecha de pago se paga con intereses (recibo y PSE ya lo
     * hacen): la casilla aparece y lo escrito se suma al total. Sin vencer, o
     * en retención y autorretención, no lleva (vacío o 0). Pesos enteros, como
     * en el recibo (VencimientoICA::leerIntereses). Devuelve el valor o el
     * mensaje de error.
     */
    private static function _intereses(array $m, array $fila, $fechaPago, $texto)
    {
        $texto = trim((string) $texto);
        $valor = $texto === '' ? 0 : VencimientoICA::leerIntereses($texto);
        if ($valor === null) { return 'Escriba los intereses de mora en pesos, sin centavos (0 si no se cobraron).'; }
        if ($valor > 0 && !self::vencidaAlPagar($m, $fila['anio'] ?? null, $fechaPago)) {
            return 'La declaración no estaba vencida en esa fecha de pago: no lleva intereses de mora.';
        }
        return (float) $valor;
    }

    /** ¿La ICA estaba vencida en la fecha de pago? Retención y autorretención aún no tienen fecha límite. */
    public static function vencidaAlPagar(array $m, $anio, $fechaPago)
    {
        return $m['clave'] === 'ica' && VencimientoICA::diasDeMora((int) $anio, $fechaPago) > 0;
    }

    /** La fecha límite de la declaración (AAAA-MM-DD), para que la pantalla muestre la casilla; null si no tiene. */
    public static function fechaLimite($con, $clave, $id)
    {
        if (!PseModulo::existe($clave) || PseModulo::get($clave)['clave'] !== 'ica') { return null; }
        $fila = self::_fila($con, PseModulo::get($clave), (int) $id);
        return $fila ? VencimientoICA::fechaLimite((int) $fila['anio']) : null;
    }

    /**
     * El medio de un pago manual: NO_INDICADO si ya corrió la 047 (que lo
     * admite en CK_pma_Medio); si el código llegó antes que la migración, OTRO,
     * que siempre vale. Se mira conf_migraciones y no sys.check_constraints: la
     * definición de una restricción puede llegar vacía sin VIEW DEFINITION.
     */
    private static function _sinMedio($con)
    {
        static $medio = null;
        if ($medio === null) {
            $f = $con->obnerFila($con->consultar(
                "SELECT COUNT(*) AS n FROM conf_migraciones WHERE mig_Nombre = '047_informes_valor_manual_y_pago_simple'", []
            ));
            $medio = ((int) ($f['n'] ?? 0)) > 0 ? self::SIN_MEDIO : 'OTRO';
        }
        return $medio;
    }

    /** ¿Ya corrió la 044 en esta base? Se cachea por petición. */
    public static function hayTablas($con)
    {
        static $hay = null;
        if ($hay === null) {
            $f = $con->obnerFila($con->consultar("SELECT OBJECT_ID('dbo.ind_declaracion_soportes', 'U') AS o", []));
            $hay = !empty($f['o']);
        }
        return $hay;
    }

    /** Carpeta de los PDF: dentro de la de anexos, que ya está cerrada al acceso directo. */
    public static function carpetaBase()
    {
        require_once __DIR__ . '/controller/class.anexos.php';
        return ControladorAnexos::carpetaBase() . '/declaraciones';
    }

    /* =====================================================================
     * PIEZAS
     * ===================================================================== */

    private static function _no($mensaje, $codigo = null)
    {
        return ['ok' => false, 'mensaje' => $mensaje, 'codigo' => $codigo];
    }

    /** Fecha AAAA-MM-DD válida, desde el 2000 y no futura. Devuelve la fecha o el error. */
    private static function _fecha($texto, $que)
    {
        $t = trim((string) $texto);
        if (!preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $t, $x) || !checkdate((int) $x[2], (int) $x[3], (int) $x[1])) {
            return self::_no("Escriba la fecha $que.");
        }
        if ($t < '2000-01-01' || $t > self::hoy()) {
            return self::_no("La fecha $que no puede ser futura ni anterior al año 2000.");
        }
        return $t;
    }

    /**
     * Los datos del pago, validados. Devuelve el arreglo o el mensaje de error.
     * El banco se elige de la lista (ind_bancos, migración 045): llega su id y
     * se guarda su nombre; antes era texto libre.
     */
    private static function _leerPago($con, array $d, $simple = false)
    {
        $fecha = self::_fecha($d['fechaPago'] ?? '', 'de pago');
        if (is_array($fecha)) { return $fecha['mensaje']; }

        // Pago manual: fecha y banco; valor (el total) y medio los pone quien llama.
        if ($simple) {
            $idBanco = trim((string) ($d['idBanco'] ?? ''));
            $banco   = preg_match('/^[0-9]{1,9}$/', $idBanco) ? BancosPago::activo($con, (int) $idBanco) : null;
            if (!$banco) { return 'Elija el banco por el que entró el pago.'; }
            return [
                'fecha' => $fecha, 'valor' => 0, 'medio' => self::_sinMedio($con), 'simple' => true,
                'banco' => $banco['nombre'], 'idBanco' => $banco['id'], 'referencia' => '', 'observacion' => '',
            ];
        }

        // Pesos con puntos de miles o coma decimal, como se escriben a mano.
        $txt = preg_replace('/[\s$]/', '', (string) ($d['valor'] ?? ''));
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/', $txt)) { $txt = str_replace(['.', ','], ['', '.'], $txt); }
        elseif (preg_match('/^\d+,\d{1,2}$/', $txt))             { $txt = str_replace(',', '.', $txt); }
        if (!preg_match('/^\d{1,13}(\.\d{1,2})?$/', $txt) || (float) $txt <= 0) {
            return 'Escriba el valor pagado, en pesos.';
        }

        $medio = strtoupper(trim((string) ($d['medio'] ?? '')));
        if (!isset(self::MEDIOS[$medio])) { return 'Elija el medio de pago.'; }

        $idBanco = trim((string) ($d['idBanco'] ?? ''));
        $banco   = preg_match('/^[0-9]{1,9}$/', $idBanco) ? BancosPago::activo($con, (int) $idBanco) : null;
        if (!$banco) { return 'Elija el banco por el que entró el pago.'; }

        $ref   = trim((string) ($d['referencia'] ?? ''));
        $obs   = trim((string) ($d['observacion'] ?? ''));
        if (mb_strlen($ref) > 60) { return 'La referencia va hasta 60 caracteres.'; }
        if (mb_strlen($obs) > 500) { return 'La observación va hasta 500 caracteres.'; }

        return [
            'fecha' => $fecha, 'valor' => round((float) $txt, 2), 'medio' => $medio,
            'banco' => $banco['nombre'], 'idBanco' => $banco['id'], 'referencia' => $ref, 'observacion' => $obs,
        ];
    }

    /**
     * Un PDF subido, validado como los anexos: subida real, tamaño, y que el
     * CONTENIDO sea PDF (la extensión la escribe quien sube). Devuelve el
     * arreglo de $_FILES o el mensaje de error.
     */
    private static function _archivo($f, $que)
    {
        if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return "Adjunte $que (PDF).";
        }
        $error = (int) ($f['error'] ?? 0);
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            return "El PDF de $que supera el tamaño permitido (10 MB).";
        }
        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($f['tmp_name'] ?? ''))) {
            return "No se pudo recibir $que. Intente de nuevo.";
        }
        if ((int) ($f['size'] ?? 0) > self::TAMANO_MAXIMO) {
            return "El PDF de $que supera los 10 MB.";
        }
        if (strtolower(pathinfo((string) ($f['name'] ?? ''), PATHINFO_EXTENSION)) !== 'pdf') {
            return "$que debe ser un PDF.";
        }
        $tipo = (new \finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        if ($tipo !== 'application/pdf') {
            return "El archivo de $que no es un PDF válido.";
        }
        return $f;
    }

    /** Mueve el PDF a su carpeta. Devuelve ruta relativa y absoluta, nombre original y tamaño. */
    private static function _guardarPdf(array $m, $id, array $f)
    {
        require_once __DIR__ . '/controller/class.anexos.php';
        $base = ControladorAnexos::carpetaBase();
        if (!is_dir($base) && !@mkdir($base, 0750, true)) { throw new \RuntimeException("no se pudo crear $base"); }
        // Como los anexos: sin la carpeta cerrada al acceso directo, no se guarda.
        if (!ControladorAnexos::blindarCarpeta()) { throw new \RuntimeException("no se pudo proteger $base"); }

        $sub     = 'declaraciones/' . $m['clave'] . '_' . (int) $id;
        $carpeta = $base . '/' . $sub;
        if (!is_dir($carpeta) && !@mkdir($carpeta, 0750, true)) { throw new \RuntimeException("no se pudo crear $carpeta"); }

        $nombre = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.pdf';
        if (!move_uploaded_file($f['tmp_name'], $carpeta . '/' . $nombre)) {
            throw new \RuntimeException('move_uploaded_file falló');
        }
        @chmod($carpeta . '/' . $nombre, 0640);

        return [
            'relativa' => $sub . '/' . $nombre,
            'absoluta' => $carpeta . '/' . $nombre,
            // El nombre lo escribe quien sube y se muestra en pantalla: sin
            // comillas, signos de etiqueta ni caracteres de control.
            'nombre'   => mb_substr(preg_replace('/["\'<>\\\\\x00-\x1F\x7F]/u', '', basename((string) $f['name'])) ?: 'archivo.pdf', 0, 255),
            'tamano'   => (int) $f['size'],
        ];
    }

    private static function _borrar(array $guardados)
    {
        foreach ($guardados as $g) { if (!empty($g['absoluta'])) { @unlink($g['absoluta']); } }
    }

    /**
     * Inserta los soportes. El primero de dos es la declaración original y el
     * último el soporte de pago (así se guardan en registrarHistorica); uno
     * solo es el soporte de pago. Devuelve tipo => sop_Id.
     */
    private static function _insertarSoportes($con, array $m, $id, array $guardados, $idUsuario)
    {
        $tipos = count($guardados) === 2 ? [self::TIPO_DECLARACION, self::TIPO_SOPORTE] : [self::TIPO_SOPORTE];
        $ids = [];
        foreach ($guardados as $i => $g) {
            $con->consultar(
                "INSERT INTO ind_declaracion_soportes
                    (sop_Modulo, sop_IdDeclaracion, sop_Tipo, sop_NombreOriginal, sop_Ruta, sop_Tamano, sop_IdUsuario)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$m['clave'], (int) $id, $tipos[$i], $g['nombre'], $g['relativa'], $g['tamano'], (int) $idUsuario]
            );
            $f = $con->obnerFila($con->consultar(
                "SELECT MAX(sop_Id) AS id FROM ind_declaracion_soportes
                  WHERE sop_Modulo = ? AND sop_IdDeclaracion = ? AND sop_Ruta = ?",
                [$m['clave'], (int) $id, $g['relativa']]
            ));
            $ids[$tipos[$i]] = (int) ($f['id'] ?? 0);
        }
        return $ids;
    }

    /** El pago: en la declaración (vía MANUAL) y su detalle. false si no se marcó. */
    private static function _registrarPago($con, array $m, $id, array $pago, $idSoporte, $idUsuario)
    {
        // El pago simple (sin medio) lleva solo el nombre del banco.
        $banco = empty($pago['simple'])
            ? self::MEDIOS[$pago['medio']] . ($pago['banco'] !== '' ? ' - ' . $pago['banco'] : '')
            : $pago['banco'];

        $marcada = PagoDeclaracion::registrar($con, $id, [
            'valor'     => $pago['valor'],
            'banco'     => $banco,
            'idBanco'   => $pago['idBanco'],
            'via'       => PagoDeclaracion::VIA_MANUAL,
            'fechaPago' => $pago['fecha'],
        ], $m);
        if (!$marcada) { return false; }

        $conBanco = BancosPago::hayColumnaManual($con);
        $params = [$m['clave'], (int) $id, $pago['fecha'], $pago['valor'], $pago['medio'],
                   // pma_Banco es VARCHAR(60); el nombre del catálogo admite más.
                   $pago['banco'] !== '' ? mb_substr($pago['banco'], 0, 60) : null,
                   $pago['referencia'] !== '' ? $pago['referencia'] : null,
                   $pago['observacion'] !== '' ? $pago['observacion'] : null,
                   $idSoporte ?: null, (int) $idUsuario];
        if ($conBanco) { $params[] = (int) $pago['idBanco']; }
        $con->consultar(
            "INSERT INTO ind_pagos_manuales
                (pma_Modulo, pma_IdDeclaracion, pma_FechaPago, pma_Valor, pma_Medio, pma_Banco,
                 pma_Referencia, pma_Observacion, pma_IdSoporte, pma_IdUsuario" . ($conBanco ? ', pma_IdBanco' : '') . ")
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?" . ($conBanco ? ', ?' : '') . ")",
            $params
        );
        return true;
    }

    private static function _fila($con, array $m, $id)
    {
        $p = $m['prefijo'];
        return $con->obnerFila($con->consultar(
            "SELECT {$m['pk']} AS id, ISNULL({$p}_Estado, 0) AS estado, ISNULL({$m['pagado']}, 0) AS pagado,
                    ISNULL({$m['valor']}, 0) AS total,
                    CONVERT(VARCHAR(10), {$p}_FechaPresentacion, 120) AS presentada,
                    " . ($m['clave'] === 'ica' ? 'dec_AnioDeclaracion' : "{$p}_Anio") . " AS anio,
                    {$p}_PSE_RequestId AS pse_req, {$p}_PSE_Estado AS pse_est
               FROM {$m['tabla']} WHERE {$m['pk']} = ?",
            [(int) $id]
        ));
    }

    private static function _actividadesIca($con, $id)
    {
        $f = $con->obnerFila($con->consultar(
            "SELECT COUNT(*) AS n FROM ind_declaraciones_ica_actividades
              WHERE dia_IdDeclaracion = ? AND ISNULL(dia_Activo, 1) = 1",
            [(int) $id]
        ));
        return (int) ($f['n'] ?? 0);
    }

    /**
     * ¿Hay un pago en línea en trámite? Registrar uno manual encima dejaría la
     * declaración con dos pagos cuando el banco confirme el suyo.
     */
    private static function _pagoEnLinea($con, array $m, $id, array $fila)
    {
        require_once __DIR__ . '/class.pasarela.php';
        if (Pasarela::esWompi()) {
            require_once __DIR__ . '/class.pagoWompi.php';
            if (PagoWompi::enTramite($con, $m['clave'], $id) !== null) {
                return 'Hay un pago en línea de esta declaración que todavía no tiene respuesta de Wompi. Verifique su estado antes de registrar un pago manual.';
            }
            return null;
        }
        require_once __DIR__ . '/class.placetopay.php';
        if (!empty($fila['pse_req']) && \PlacetoPay::enTramite($fila['pse_est'])) {
            return 'Hay un pago por PSE de esta declaración en trámite. Use "Verificar estado del pago" antes de registrar un pago manual.';
        }
        return null;
    }

    /** El mismo candado que los pagos en línea (PagoWompi): un pago a la vez por declaración. */
    private static function _candado($con, array $m, $id)
    {
        $f = $con->obnerFila($con->consultar(
            "SET NOCOUNT ON;
             DECLARE @r INT;
             EXEC @r = sp_getapplock @Resource = ?, @LockMode = 'Exclusive',
                                     @LockOwner = 'Transaction', @LockTimeout = 20000;
             SELECT @r AS r;",
            ['erp_pago_' . $m['clave'] . '_' . (int) $id]
        ));
        return isset($f['r']) && (int) $f['r'] >= 0;
    }
}
