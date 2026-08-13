<?php
/**
 * Pruebas de la referencia de recaudo GS1-128.
 *
 * Ejecutar:  php pruebas/codigoBarrasRecaudo.test.php
 *
 * Es lógica de cobro: un error aquí no rompe una pantalla, hace que un
 * contribuyente no pueda pagar en el banco o pague un valor equivocado.
 */

// El EAN se define ANTES de cargar la clase, simulando config.municipio.php.
define('MUNICIPIO_EAN_RECAUDO', '7709998765432');

require_once __DIR__ . '/../business/class.codigoBarrasRecaudo.php';

use erpsoftsas\CodigoBarrasRecaudo;

$pasaron = 0;
$fallaron = 0;

function verificar($descripcion, $esperado, $obtenido) {
    global $pasaron, $fallaron;
    // Los FNC1 no son imprimibles: se muestran como {FNC1} en los mensajes.
    $leg = function ($v) { return str_replace("\xF1", '{FNC1}', var_export($v, true)); };
    if ($esperado === $obtenido) {
        $pasaron++;
    } else {
        $fallaron++;
        echo "  FALLO: $descripcion\n";
        echo "    esperado: " . $leg($esperado) . "\n";
        echo "    obtenido: " . $leg($obtenido) . "\n";
    }
}

$F = "\xF1";

// ---------------------------------------------------------------- estructura
verificar(
    'estructura completa sin fecha',
    $F . '415' . '7709998765432'
       . '8020' . '000000000000000000000218'
       . $F . '3900' . '00000000019000',
    CodigoBarrasRecaudo::construir(218, 19000)
);

verificar(
    'con fecha de vencimiento agrega el segmento 96',
    $F . '415' . '7709998765432'
       . '8020' . '000000000000000000000218'
       . $F . '3900' . '00000000019000'
       . $F . '96' . '20261231',
    CodigoBarrasRecaudo::construir(218, 19000, '2026-12-31')
);

verificar(
    'sin fecha NO agrega el segmento 96',
    false,
    strpos(CodigoBarrasRecaudo::construir(218, 19000), $F . '96') !== false
);

// ------------------------------------------------------------------ rellenos
// Desplazamiento 21 = FNC1(1) + '415'(3) + EAN(13) + '8020'(4).
verificar(
    'la factura se rellena a 24 digitos',
    '000000000000000000000005',
    substr(CodigoBarrasRecaudo::construir(5, 1000), 21, 24)
);

verificar(
    'el valor se rellena a 14 digitos',
    '00000000001000',
    substr(CodigoBarrasRecaudo::construir(5, 1000), -14)
);

// -------------------------------------------------------------------- valores
verificar('el valor se redondea al peso (hacia arriba)',
    '00000000019001', substr(CodigoBarrasRecaudo::construir(1, 19000.6), -14));

verificar('el valor se redondea al peso (hacia abajo)',
    '00000000019000', substr(CodigoBarrasRecaudo::construir(1, 19000.4), -14));

verificar('valor cero es valido (declaracion sin saldo)',
    '00000000000000', substr(CodigoBarrasRecaudo::construir(1, 0), -14));

verificar('acepta el valor como cadena',
    '00000000019000', substr(CodigoBarrasRecaudo::construir(1, '19000'), -14));

// ------------------------------------------------------------------ limpieza
verificar(
    'la referencia se queda solo con digitos',
    '000000000000000000000218',
    substr(CodigoBarrasRecaudo::construir('DEC-218', 1000), 21, 24)
);

// --------------------------------------------------------------- rechazos
verificar('referencia sin digitos devuelve null',
    null, CodigoBarrasRecaudo::construir('ABC', 1000));

verificar('referencia mas larga que 24 digitos devuelve null (no se trunca)',
    null, CodigoBarrasRecaudo::construir(str_repeat('9', 25), 1000));

verificar('valor mas largo que 14 digitos devuelve null',
    null, CodigoBarrasRecaudo::construir(1, '999999999999999'));

// ---------------------------------------------------------------- legible
verificar(
    'el texto legible usa parentesis en vez de FNC1',
    '(415)7709998765432(8020)000000000000000000000218(3900)00000000019000',
    CodigoBarrasRecaudo::textoLegible(218, 19000)
);

verificar('configurado() reconoce un EAN valido',
    true, CodigoBarrasRecaudo::configurado());

// -------------------------------------------------------------- resultado
echo "\n";
if ($fallaron === 0) {
    echo "  $pasaron pruebas pasaron\n  Todo correcto.\n";
    exit(0);
}
echo "  $pasaron pasaron, $fallaron fallaron\n";
exit(1);
