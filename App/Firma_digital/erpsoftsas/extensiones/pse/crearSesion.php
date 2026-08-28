<?php

/**
 * Punto de entrada del botón "Pagar PSE": crea la sesión en PlacetoPay
 * para una declaración y redirige al usuario a pagarla.
 *
 * GET dec_Id
 */
include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.conexionSqlServer.php';
require_once SERVER . '/business/class.placetopay.php';

$configPath = dirname(dirname(dirname(__DIR__))) . '/config.municipio.php';
if (!file_exists($configPath)) {
    $configPath = dirname(dirname(__DIR__)) . '/config.municipio.php';
}
if (file_exists($configPath)) {
    require_once $configPath;
}

$con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

/*
 * Sin convenio de recaudo no hay nada que cobrar.
 *
 * Se comprueba lo PRIMERO, antes de tocar la base. Hasta ahora nadie lo
 * miraba: la clase leia las constantes directamente, asi que en una
 * instalacion sin convenio -un municipio recien montado- PHP fallaba al leer
 * una constante inexistente y el contribuyente veia una pagina en blanco,
 * sin mensaje ni forma de saber que pasaba.
 *
 * Desde la migracion 023 los datos salen de conf_parametros y pueden estar
 * legitimamente vacios, asi que la comprobacion deja de ser una precaucion
 * teorica: es el estado normal de una instalacion nueva.
 */
if (!PlacetoPay::configurado()) {
    http_response_code(503);
    die('El pago en línea no está disponible: la Alcaldía todavía no ha configurado '
      . 'el convenio de recaudo. Puede pagar en el banco con el código de barras '
      . 'impreso en su declaración.');
}

$idDeclaracion = $_GET['dec_Id'] ?? 0;

$row = $con->obnerFila($con->consultar(
    "SELECT dec_NumeroDeclaracion, dec_ValorConcepto20, dec_Pagado, dec_Estado, dec_PSE_RequestId
     FROM ind_declaraciones_ica WHERE dec_Id = ?",
    [$idDeclaracion]
));

if (!$row) {
    http_response_code(404);
    die('Declaración no encontrada.');
}

if ((int) $row['dec_Pagado'] === 1) {
    die('Esta declaración ya está pagada.');
}

/*
 * Solo se paga lo que ya esta PRESENTADO.
 *
 * Sin esta guarda se podia pagar un BORRADOR: el boton de PSE se mostraba en
 * cualquier declaracion no pagada, y al volver el pago quedaba dec_Pagado = 1
 * con dec_Estado en 0/NULL. Ese estado es imposible y rompe el resto del
 * sistema -"Corregir" exige dec_Estado = 2 y lo rechaza, de modo que el
 * contribuyente queda con una declaracion que dice estar pagada y que no
 * puede tocar-. Se encontro con la declaracion 48, pagada contra el sandbox.
 *
 * Ademas el monto de un borrador todavia puede cambiar: cobrarlo antes de
 * presentar seria cobrar una cifra que no es definitiva.
 *
 * La misma regla se aplica al recaudo bancario (ver class.recaudo.php). No se
 * confia en que el boton este oculto: esta URL se puede llamar a mano.
 */
if ((int) ($row['dec_Estado'] ?? 0) !== 2) {
    die('Esta declaración todavía no está presentada. Debe presentarla antes de pagar.');
}

$referencia = (string) $row['dec_NumeroDeclaracion'];
$valor = (float) $row['dec_ValorConcepto20'];

if ($valor <= 0) {
    die('El valor a pagar de esta declaración es $0, no aplica pago PSE.');
}

// URL a la que PlacetoPay redirige al usuario al terminar (o cancelar) el
// pago. Se arma dinamicamente (esquema+host actuales) para que funcione
// igual en Docker local, pruebas y produccion sin tocar codigo.
$esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$returnUrl = $esquema . '://' . $_SERVER['HTTP_HOST'] . '/erpsoftsas/extensiones/pse/retorno.php?dec_Id=' . urlencode($idDeclaracion);

try {
    $sesion = PlacetoPay::crearSesion(
        $referencia,
        $valor,
        'Pago Impuesto de Industria y Comercio - Formulario No. ' . $referencia,
        $returnUrl
    );
} catch (Exception $e) {
    http_response_code(502);
    die('No se pudo iniciar el pago con PlacetoPay: ' . htmlspecialchars($e->getMessage()));
}

$con->consultar(
    "UPDATE ind_declaraciones_ica SET dec_PSE_RequestId = ? WHERE dec_Id = ?",
    [$sesion['requestId'], $idDeclaracion]
);

header('Location: ' . $sesion['processUrl']);
exit;
