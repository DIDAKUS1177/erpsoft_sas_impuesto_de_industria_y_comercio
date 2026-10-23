<?php

namespace erpsoftsas;

require_once __DIR__ . '/class.pseModulo.php';

/**
 * El número de formulario de las declaraciones (ICA, retención y
 * autorretención) se asigna al PRESENTAR, no al crear el borrador.
 *
 * Pedido de Paipa en la reunión del 2026-10-20: crear un borrador consumía un
 * consecutivo, y cada borrador que no se presentaba (pruebas, uno repetido, uno
 * descartado que no era el último) dejaba un hueco en la serie. Ahora la serie
 * solo avanza con lo que se presenta.
 *
 * MIENTRAS ES BORRADOR LLEVA UN NÚMERO PROVISIONAL: MENOS SU ID (-1081).
 *
 * No se deja en NULL porque el número es el identificador de medio sistema:
 * sp_calculo_comercio localiza la fila por él, la ventana de firma de
 * retención y autorretención manda el número, firmas_declaraciones guarda la
 * firma por número y módulo, y la pantalla del ICA lo usa para guardar. Con
 * -id todo eso sigue funcionando sin tocarlo, y no choca con nada: ningún
 * número real es negativo, los índices únicos (filtrados por IS NOT NULL) lo
 * admiten y las tres tablas tienen su propia serie de ids. Lo que cambia es lo
 * que se MUESTRA: un provisional nunca se imprime ni se ve ("Sin número").
 *
 * Al presentar, asignar() pide el consecutivo y pasa las firmas del número
 * provisional al real. Un borrador viejo que ya tenía número real lo conserva.
 */
class NumeroDeclaracion
{
    /** Números ocupados seguidos que se saltan antes de rendirse (ver asignar). */
    const INTENTOS = 50;

    /** ¿Es un número provisional (borrador sin consecutivo) o vacío? */
    public static function esProvisional($numero)
    {
        if ($numero === null) { return true; }
        $t = trim((string) $numero);
        return $t === '' || (float) $t <= 0;
    }

    /** El número provisional de un borrador: menos su id. */
    public static function provisional($id)
    {
        return -abs((int) $id);
    }

    /** Para mostrar: el número, o el texto que se pida si es provisional. */
    public static function texto($numero, $sinNumero = 'sin número (borrador)')
    {
        return self::esProvisional($numero) ? $sinNumero : (string) $numero;
    }

    /**
     * Pone el número provisional a un borrador RECIÉN CREADO (todavía sin
     * actividades ni firmas). Un borrador nunca se queda sin número: si no se
     * puede marcar, se borra la fila y se lanza el error, para que quien crea
     * avise y no quede uno con NULL (la ventana de firma de retención no
     * podría firmarlo: firma por número).
     */
    public static function marcarProvisional($con, $clave, $id)
    {
        $m = PseModulo::get($clave);
        try {
            $f = $con->obnerFila($con->consultar(
                "SET NOCOUNT ON;
                 UPDATE {$m['tabla']} SET {$m['numero']} = ? WHERE {$m['pk']} = ?;
                 SELECT @@ROWCOUNT AS n;",
                [self::provisional($id), (int) $id]
            ));
            if ((int) ($f['n'] ?? 0) !== 1) { throw new \RuntimeException('no se encontró la fila'); }
        } catch (\Throwable $e) {
            try {
                $con->consultar("DELETE FROM {$m['tabla']} WHERE {$m['pk']} = ? AND {$m['numero']} IS NULL", [(int) $id]);
            } catch (\Throwable $e2) { /* queda en el log de abajo */ }
            error_log("[numero] no se pudo marcar el provisional de la $clave $id: " . $e->getMessage());
            throw new \RuntimeException('no se pudo marcar el número provisional', 0, $e);
        }
        return self::provisional($id);
    }

    /**
     * Al PRESENTAR: si la declaración todavía tiene número provisional, le da
     * el consecutivo y pasa sus firmas al número nuevo. Devuelve el número que
     * queda (el de siempre si ya tenía uno real).
     *
     * QUIEN LLAMA ABRE LA TRANSACCIÓN y la confirma junto con el cambio de
     * estado: o queda presentada con su número, o ninguna de las dos cosas (un
     * consecutivo pedido dentro de una transacción que se revierte vuelve al
     * contador, así que tampoco deja hueco). El candado por declaración evita
     * que dos "Presentar" a la vez le pidan dos números.
     *
     * El año del consecutivo: el de HOY en el ICA (Juan, 2026-10-05: «aunque
     * sea de otro año sigue el consecutivo de este año»); el de la declaración
     * en retención y autorretención, como numeraban al crear.
     *
     * Si el generador no existe (base sin las migraciones 012 / 030) se usa el
     * id, como hacía la creación antes.
     */
    public static function asignar($con, $clave, $id)
    {
        $m  = PseModulo::get($clave);
        $id = (int) $id;

        $con->consultar(
            "DECLARE @r INT;
             EXEC @r = sp_getapplock @Resource = ?, @LockMode = 'Exclusive',
                                     @LockOwner = 'Transaction', @LockTimeout = 20000;
             IF @r < 0 THROW 50054, 'No se pudo tomar el candado del número de la declaración.', 1;",
            ['erp_numero_' . $m['clave'] . '_' . $id]
        );

        $anioCol = $m['clave'] === 'ica' ? 'dec_AnioDeclaracion' : $m['prefijo'] . '_Anio';
        $fila = $con->obnerFila($con->consultar(
            "SELECT CAST({$m['numero']} AS VARCHAR(30)) AS numero, $anioCol AS anio
               FROM {$m['tabla']} WITH (UPDLOCK) WHERE {$m['pk']} = ?",
            [$id]
        ));
        if (!$fila) { throw new \RuntimeException("declaración $clave $id no encontrada"); }

        $actual = $fila['numero'];
        if (!self::esProvisional($actual)) { return $actual; }

        if ($m['clave'] === 'ica') {
            $anio = (int) (new \DateTime('now', new \DateTimeZone('America/Bogota')))->format('Y');
            $sql  = "DECLARE @n BIGINT;
                     EXEC dbo.sp_siguiente_numero_declaracion @ANIO = ?, @NUMERO = @n OUTPUT;
                     SELECT CAST(@n AS VARCHAR(30)) AS numero;";
            $par  = [$anio];
        } else {
            $sql  = "DECLARE @n BIGINT;
                     EXEC dbo.sp_siguiente_numero_retencion @TIPO = ?, @ANIO = ?, @NUMERO = @n OUTPUT;
                     SELECT CAST(@n AS VARCHAR(30)) AS numero;";
            $par  = [$m['fd'], (int) $fila['anio']];
        }

        $nuevo = null;
        if (self::hayGenerador($con, $m['clave'])) {
            /*
             * Si el número que entrega el procedimiento ya lo tiene otra
             * declaración (un contador que se devolvió de más, un número
             * puesto a mano), se pide el siguiente en vez de chocar con el
             * índice único y dejar sin presentar. Con tope: más de 50
             * ocupados seguidos es un contador dañado, no un hueco.
             */
            for ($intento = 0; $intento < self::INTENTOS && $nuevo === null; $intento++) {
                $n = $con->obnerFila($con->consultar($sql, $par));
                $candidato = isset($n['numero']) && (float) $n['numero'] > 0 ? (string) $n['numero'] : null;
                if ($candidato === null) { throw new \RuntimeException('el generador no devolvió un número'); }
                $ocupado = $con->obnerFila($con->consultar(
                    "SELECT COUNT(*) AS n FROM {$m['tabla']} WITH (UPDLOCK, HOLDLOCK) WHERE {$m['numero']} = ?", [$candidato]
                ));
                if ((int) ($ocupado['n'] ?? 0) === 0) {
                    $nuevo = $candidato;
                } else {
                    error_log("[numero] el $candidato ya lo tiene otra declaración ($clave): se pide el siguiente");
                }
            }
            if ($nuevo === null) { throw new \RuntimeException('el contador de ' . $clave . ' entrega números ya usados'); }
        } else {
            $nuevo = (string) $id;
            error_log("[numero] sin generador de consecutivos; la $clave $id se numera con su id");
        }

        $con->consultar(
            "UPDATE {$m['tabla']} SET {$m['numero']} = ? WHERE {$m['pk']} = ?",
            [$nuevo, $id]
        );

        // Las firmas se guardan por número y módulo: pasan al número real. Las
        // del ICA van por dec_Id (lo que manda su ventana de firma) y no
        // cambian; si alguna quedó con el provisional, también pasa.
        if ($actual !== null && trim((string) $actual) !== '') {
            $con->consultar(
                "UPDATE firmas_declaraciones SET fd_NumeroDeclaracion = ?
                  WHERE fd_NumeroDeclaracion = ? AND fd_Modulo = ?",
                [$nuevo, (string) $actual, $m['fd']]
            );
        }

        return $nuevo;
    }

    /** ¿Existe el procedimiento que reparte los consecutivos de este módulo? */
    private static function hayGenerador($con, $clave)
    {
        $sp = $clave === 'ica' ? 'dbo.sp_siguiente_numero_declaracion' : 'dbo.sp_siguiente_numero_retencion';
        $f  = $con->obnerFila($con->consultar("SELECT OBJECT_ID(?, 'P') AS o", [$sp]));
        return !empty($f['o']);
    }
}
