<?php

/**
 * Preguntas frecuentes + Términos y Condiciones + Política de tratamiento de
 * datos de los pagos en línea (AvalPay/PSE).
 *
 * La Guía de certificación WC (item 12.5) exige que el sitio incluya un
 * apartado de FAQ que mencione los pagos por AvalPay, y unos T&C y una política
 * de datos accesibles al usuario ANTES de pagar. Esta página es pública (no
 * requiere sesión) y se enlaza desde el resumen de pago (pagar.php) y desde el
 * pie de las pantallas.
 *
 * El texto del FAQ es el que entregó AvalPay; el nombre del comercio se toma
 * del config del municipio. Los T&C legales los define la Alcaldía: aquí va una
 * base razonable que la entidad puede ampliar.
 */
$configPath = dirname(dirname(dirname(__DIR__))) . '/config.municipio.php';
if (!file_exists($configPath)) {
    $configPath = dirname(dirname(__DIR__)) . '/config.municipio.php';
}
if (file_exists($configPath)) {
    require_once $configPath;
}

$muni    = defined('MUNICIPIO_NOMBRE') ? MUNICIPIO_NOMBRE : 'la Alcaldía';
$color   = defined('MUNICIPIO_COLOR') ? MUNICIPIO_COLOR : '#1fa49d';
$colorOs = defined('MUNICIPIO_COLOR_OSCURO') ? MUNICIPIO_COLOR_OSCURO : '#17756f';
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pagos en línea — Preguntas frecuentes y Términos · <?= $e($muni) ?></title>
<style>
  :root { --c: <?= $e($color) ?>; --c2: <?= $e($colorOs) ?>; }
  * { box-sizing: border-box; }
  body { font-family: 'Segoe UI', Arial, sans-serif; color: #2b2b2b; background: #f4f6f8; margin: 0; }
  header { background: var(--c); color: #fff; padding: 22px 16px; }
  header .in { max-width: 760px; margin: auto; }
  header h1 { margin: 0; font-size: 20px; }
  header p { margin: 4px 0 0; opacity: .9; font-size: 14px; }
  main { max-width: 760px; margin: 0 auto; padding: 24px 16px 60px; }
  section { background: #fff; border-radius: 10px; box-shadow: 0 2px 12px rgba(0,0,0,.06); padding: 22px 24px; margin-bottom: 20px; }
  h2 { color: var(--c2); font-size: 17px; border-bottom: 2px solid #eef2f3; padding-bottom: 8px; margin-top: 0; }
  h3 { font-size: 15px; margin: 18px 0 4px; }
  p, li { font-size: 14px; line-height: 1.6; color: #444; }
  .nota { font-size: 12px; color: #888; }
  a.volver { display: inline-block; margin-top: 8px; color: #fff; text-decoration: none; background: rgba(255,255,255,.2); padding: 6px 12px; border-radius: 6px; font-size: 13px; }
  code { background: #f1f3f4; padding: 1px 5px; border-radius: 4px; }
</style>
</head>
<body>
<header>
  <div class="in">
    <h1>Pagos en línea</h1>
    <p><?= $e($muni) ?> · Impuesto de Industria y Comercio</p>
    <a class="volver" href="javascript:history.length>1?history.back():window.close();">← Volver</a>
  </div>
</header>
<main>

  <section>
    <h2>Preguntas frecuentes</h2>

    <h3>¿Qué es AvalPay?</h3>
    <p>AvalPay es la plataforma de pagos electrónicos que usa <?= $e($muni) ?> para procesar
    en línea las transacciones generadas en el sistema, con las formas de pago habilitadas para tal fin.</p>

    <h3>¿Cómo puedo pagar?</h3>
    <p>Podrá realizar su pago con los medios habilitados: <strong>cuentas de ahorro y corriente por PSE</strong>
    (débito bancario), según las opciones dispuestas por la entidad.</p>

    <h3>¿Es seguro ingresar mis datos bancarios en este sitio?</h3>
    <p>Sí. Para proteger sus datos, <?= $e($muni) ?> delega en <strong>AvalPay</strong> la captura de la
    información sensible. La plataforma cumple los estándares de la norma internacional <strong>PCI DSS</strong>
    de seguridad en transacciones y usa cifrado de la información hacia y desde el sitio.
    <?= $e($muni) ?> <strong>no almacena</strong> los datos de su cuenta.</p>

    <h3>¿Puedo realizar el pago cualquier día y a cualquier hora?</h3>
    <p>Sí, los pagos en línea a través de AvalPay están disponibles los 7 días de la semana, las 24 horas.</p>

    <h3>¿Puedo cambiar la forma de pago?</h3>
    <p>Si aún no ha finalizado el pago, puede volver al paso inicial y elegir otra forma de pago. Una vez
    finalizada la operación no es posible cambiarla.</p>

    <h3>¿Pagar electrónicamente tiene algún costo para mí?</h3>
    <p>No. Los pagos electrónicos realizados a través de AvalPay no generan costos adicionales para el pagador.</p>

    <h3>¿Qué debo hacer si mi transacción no concluyó?</h3>
    <p>Primero, revise si llegó un correo de confirmación a la cuenta que registró al pagar (incluida la carpeta
    de spam). Si no lo recibió, comuníquese con <?= $e($muni) ?> para confirmar el estado de la transacción,
    indicando su número de referencia.</p>

    <h3>¿Qué debo hacer si no recibí el comprobante de pago?</h3>
    <p>Por cada transacción aprobada recibirá un comprobante con la referencia en el correo que indicó al pagar.
    Si no lo recibe, contacte a <?= $e($muni) ?> para solicitar el reenvío a la misma dirección de correo.</p>
  </section>

  <section>
    <h2>Política de tratamiento de datos personales</h2>
    <p>Los datos que usted suministra en el proceso de pago se utilizan <strong>únicamente</strong> para procesar
    el pago del Impuesto de Industria y Comercio y llevar su registro y trazabilidad ante <?= $e($muni) ?>.</p>
    <p>La captura de la información financiera (cuenta, entidad bancaria) la realiza directamente <strong>AvalPay</strong>,
    que cumple el estándar PCI DSS; <?= $e($muni) ?> no tiene acceso ni almacena esos datos.</p>
    <p>Usted puede conocer, actualizar o rectificar sus datos y ejercer los derechos previstos en la Ley 1581 de 2012
    y demás normas aplicables, comunicándose con <?= $e($muni) ?>.</p>
  </section>

  <section>
    <h2>Términos y condiciones</h2>
    <ul>
      <li>El pago en línea aplica sobre declaraciones <strong>presentadas</strong> y con un valor a pagar mayor a cero.</li>
      <li>El valor a pagar corresponde al liquidado en la declaración; antes de ser redirigido a AvalPay usted verá
      el monto total a pagar y deberá aceptar esta política.</li>
      <li>La transacción puede quedar en estado <strong>aprobada</strong>, <strong>rechazada</strong> o
      <strong>pendiente</strong>. Si queda pendiente, el sistema la confirmará automáticamente cuando la entidad
      financiera responda; no vuelva a pagar sin verificar el estado.</li>
      <li>El comprobante de la operación queda a cargo de AvalPay y del sistema de <?= $e($muni) ?>.</li>
      <li>Al continuar con el pago, usted acepta estos términos y la política de tratamiento de datos.</li>
    </ul>
    <p class="nota">Estos términos son una base general; <?= $e($muni) ?> es responsable de definir y ampliar las
    condiciones que regulen sus operaciones de recaudo.</p>
  </section>

</main>
</body>
</html>
