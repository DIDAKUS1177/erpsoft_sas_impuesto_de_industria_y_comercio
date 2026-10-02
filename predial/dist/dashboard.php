<?php
/*
 * PORTAL TRIBUTARIO — la página pública que la Alcaldía embebe en su sitio
 * (en producción: https://sistema.erpsoftsas.com/predial/dist/dashboard.php).
 * No pide sesión ni toca la base: solo lleva a cada trámite.
 *
 * MARCA BLANCA
 *
 * Todo lo que cambia de un municipio a otro vive en $MUNICIPIOS (abajo):
 * nombre, logo, escudo, foto, colores, contacto, normatividad, destacados y la
 * dirección de cada trámite. El municipio es el de PORTAL_MUNICIPIO
 * (config.municipio.php de este servidor, si lo hay) o Paipa; con
 * ?municipio=guateque se ve el de otro, solo de los de la lista. Un trámite sin
 * dirección no se muestra; con 'pronto', sale como "Próximamente".
 *
 * Logos, escudos y fotos se toman del sitio de Industria y Comercio de Paipa:
 * el repositorio de ICA trae los de los cuatro municipios y ese dominio es el
 * único con certificado válido (los otros aún no tienen SSL). Si un municipio
 * no tiene logo completo, el encabezado arma uno con su escudo y su nombre.
 *
 * Los trámites se abren en pestaña nueva: dentro del marco de la página de la
 * Alcaldía el navegador bloquea la cookie de sesión del módulo y no dejaría
 * iniciar sesión.
 */
@ini_set('display_errors', '0');

$configPath = __DIR__ . '/../../config.municipio.php';
if (is_file($configPath)) {
    require_once $configPath;
}

$R = 'https://industria-comercio-paipa.erpsoftsas.com/erpsoftsas/vendors/images/';

$MUNICIPIOS = [
    'paipa' => [
        'ciudad'       => 'Paipa',
        'departamento' => 'Boyacá',
        'logo'         => $R . 'logo-paipa-full.png',
        'escudo'       => $R . 'escudo-paipa.png',
        'foto'         => $R . 'fondo-paipa.jpg',
        'fotoPos'      => 'center 58%',
        'secretaria'   => 'Secretaría de Hacienda',
        'dependencia'  => 'Dirección de Impuestos, Rentas y Jurisdicción Coactiva',
        'telefono'     => '318 530 9285',
        'correo'       => 'impuestos@paipa-boyaca.gov.co',
        'direccion'    => 'Carrera 22 # 25-14, primer piso',
        'acceso'       => 'https://sistema.erpsoftsas.com/predial',
        'tramites'     => [
            'predial'     => 'https://psepaipa.erpsoftsas.com/',
            'ica'         => 'https://industria-comercio-paipa.erpsoftsas.com/erpsoftsas/',
            'exogena'     => 'https://sistema.erpsoftsas.com/predial',
            'estampillas' => 'pronto',
        ],
        'nuevo'        => ['ica'],
        'destacados'   => ['Trámites en línea', 'Firma electrónica', 'Recibo para bancos autorizados'],
        'normatividad' => [
            ['Estatuto de Rentas Municipal', 'Acuerdo 019 de 2022', 'https://www.paipa-boyaca.gov.co/Transparencia/Normatividad/Acuerdo%20019%20de%202022.pdf'],
            ['Régimen Sancionatorio', 'Acuerdo 018 de 2024', 'https://www.paipa-boyaca.gov.co/Transparencia/Normatividad/Acuerdo%20018%20de%202024.pdf'],
            ['Calendario Tributario 2025', 'Resolución 122-2143 de 2024', 'https://www.paipa-boyaca.gov.co/Transparencia/Normatividad/Resolucion%20122-2143%20de%202024.pdf'],
            ['Información Exógena', 'Resolución 122-0155 de 2024', 'https://www.paipa-boyaca.gov.co/Transparencia/Normatividad/Resolucion%20122-155%20de%202024.pdf'],
            ['Información Exógena', 'Resolución 122-0090 de 2025', 'https://www.paipa-boyaca.gov.co/Transparencia/Normatividad/RESOLUCI%C3%93N%20122-0090%20DE%202025.pdf'],
        ],
    ],
    // Los demás: lo que ya existe (escudo, foto y su módulo de ICA). Contacto,
    // normatividad y los otros trámites se agregan cuando la Alcaldía los pase.
    'guateque' => [
        'ciudad' => 'Guateque', 'departamento' => 'Boyacá',
        'escudo' => $R . 'escudo-guateque.png', 'foto' => $R . 'fondo-guateque.jpg', 'fotoPos' => 'center 45%',
        'secretaria' => 'Secretaría de Hacienda',
        'tramites' => ['ica' => 'https://industria-comercio-guateque.erpsoftsas.com/erpsoftsas/'],
        'destacados' => ['Trámites en línea', 'Firma electrónica'],
    ],
    'macanal' => [
        'ciudad' => 'Macanal', 'departamento' => 'Boyacá',
        'escudo' => $R . 'escudo-macanal.png', 'foto' => $R . 'fondo-macanal.jpg', 'fotoPos' => 'center 50%',
        'secretaria' => 'Secretaría de Hacienda',
        'tramites' => ['ica' => 'https://industria-comercio-macanal.erpsoftsas.com/erpsoftsas/'],
        'destacados' => ['Trámites en línea', 'Firma electrónica'],
    ],
    'sutatenza' => [
        'ciudad' => 'Sutatenza', 'departamento' => 'Boyacá',
        'escudo' => $R . 'escudo-sutatenza.png', 'foto' => $R . 'fondo-sutatenza.jpg', 'fotoPos' => 'center 50%',
        'secretaria' => 'Secretaría de Hacienda',
        'tramites' => ['ica' => 'https://industria-comercio-sutatenza.erpsoftsas.com/erpsoftsas/'],
        'destacados' => ['Trámites en línea', 'Firma electrónica'],
    ],
];

$slug = defined('PORTAL_MUNICIPIO') ? strtolower((string) PORTAL_MUNICIPIO) : 'paipa';
$pedido = isset($_GET['municipio']) && is_string($_GET['municipio']) ? strtolower($_GET['municipio']) : '';
if ($pedido !== '' && isset($MUNICIPIOS[$pedido])) {
    $slug = $pedido;
}
if (!isset($MUNICIPIOS[$slug])) {
    $slug = 'paipa';
}
$M = $MUNICIPIOS[$slug];

function e($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function dato($m, $clave) { return isset($m[$clave]) ? (string) $m[$clave] : ''; }
function colorValido($c, $porDefecto) {
    return (is_string($c) && preg_match('/^#[0-9a-fA-F]{6}$/', $c)) ? strtolower($c) : $porDefecto;
}
/** Mezcla dos colores #rrggbb; $t es cuánto pesa el segundo (0 a 1). */
function mezclar($a, $b, $t) {
    $x = sscanf($a, '#%02x%02x%02x');
    $y = sscanf($b, '#%02x%02x%02x');
    $c = [];
    for ($i = 0; $i < 3; $i++) { $c[] = (int) round($x[$i] * (1 - $t) + $y[$i] * $t); }
    return sprintf('#%02x%02x%02x', $c[0], $c[1], $c[2]);
}
function rgbDe($hex) { return implode(', ', sscanf($hex, '#%02x%02x%02x')); }

$primario = colorValido(isset($M['color']) ? $M['color'] : (defined('MUNICIPIO_COLOR') ? MUNICIPIO_COLOR : null), '#1fa49d');
$oscuro   = colorValido(isset($M['colorOscuro']) ? $M['colorOscuro'] : (defined('MUNICIPIO_COLOR_OSCURO') ? MUNICIPIO_COLOR_OSCURO : null), '#17756f');
$profundo = mezclar($oscuro, '#000000', 0.52);
$suave    = mezclar($primario, '#ffffff', 0.88);

// La foto y su encuadre van dentro de CSS (url() en <style>): se validan
// aparte, porque e() escapa HTML, no CSS.
$foto    = preg_match('~^https://[^\s\'"()<>\\\\]+$~', dato($M, 'foto')) ? dato($M, 'foto') : '';
$fotoPos = preg_match('/^[a-z0-9 .%-]{1,40}$/', dato($M, 'fotoPos')) ? dato($M, 'fotoPos') : 'center';

$ciudad   = dato($M, 'ciudad');
$alcaldia = 'Alcaldía de ' . $ciudad;
$lugar    = $ciudad . (dato($M, 'departamento') !== '' ? ', ' . dato($M, 'departamento') : '');

// Íconos de línea (24x24, trazo 2).
$ICONOS = [
    'predial'     => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V9.5"/>',
    'ica'         => '<path d="M3 21h18"/><path d="M5 21V8l7-5 7 5v13"/><path d="M9 21v-4h6v4"/><path d="M9 10h.01M12 10h.01M15 10h.01M9 13.5h.01M12 13.5h.01M15 13.5h.01"/>',
    'exogena'     => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M12 17v-6"/><path d="m9 14 3 3 3-3"/>',
    'estampillas' => '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M4 9h16"/><path d="M9 4v5"/><circle cx="12" cy="14.5" r="2.5"/>',
    'telefono'    => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/>',
    'correo'      => '<rect x="2.5" y="4.5" width="19" height="15" rx="2"/><path d="m3 6 9 7 9-7"/>',
    'direccion'   => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/>',
    'documento'   => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h4"/>',
    'acceso'      => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="m10 17 5-5-5-5"/><path d="M15 12H3"/>',
    'ir'          => '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
    'externo'     => '<path d="M14 4h6v6"/><path d="M20 4 10 14"/><path d="M19 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h5"/>',
    'chequeo'     => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
    'chispa'      => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M6 18l2.5-2.5M15.5 8.5 18 6"/>',
];
function icono($nombre, $clase = '') {
    global $ICONOS;
    return '<svg' . ($clase !== '' ? ' class="' . $clase . '"' : '') . ' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . $ICONOS[$nombre] . '</svg>';
}

// Cada trámite con su tono (predial lleva el del municipio).
// [título, texto, tono de la franja, tono del texto y del ícono (4,5:1 sobre blanco)]
$TRAMITES = [
    'predial'     => ['Impuesto Predial', 'Consulta tu estado de cuenta y paga el impuesto predial.', $primario, $oscuro],
    'ica'         => ['Industria y Comercio', 'Inscríbete en el RIT y presenta tus declaraciones de ICA, retención y autorretención.', '#c38d1c', '#8f6510'],
    'exogena'     => ['Información Exógena', 'Reporta la información exógena que solicita el municipio.', '#2f78b8', '#23649c'],
    'estampillas' => ['Estampillas', 'Liquida y paga las estampillas municipales.', '#8b9a96', '#5f6e6a'],
];
$tarjetas = [];
$urls = [];
foreach ($TRAMITES as $clave => $t) {
    $url = isset($M['tramites'][$clave]) ? (string) $M['tramites'][$clave] : '';
    if ($url === '') { continue; }
    $pronto = $url === 'pronto';
    if (!$pronto) { $urls[$clave] = $url; }
    $tarjetas[] = ['clave' => $clave, 'titulo' => $t[0], 'texto' => $t[1], 'url' => $url, 'tono' => $t[2], 'tonoTexto' => $t[3],
                   'tonoSuave' => mezclar($t[2], '#ffffff', 0.86), 'tonoRgb' => rgbDe($t[2]),
                   'pronto' => $pronto, 'nuevo' => in_array($clave, isset($M['nuevo']) ? $M['nuevo'] : [], true)];
}
$normas = isset($M['normatividad']) ? $M['normatividad'] : [];
$destacados = isset($M['destacados']) ? $M['destacados'] : [];
$hayContacto = dato($M, 'telefono') !== '' || dato($M, 'correo') !== '' || dato($M, 'direccion') !== '';
$telefonoEnlace = '+57' . preg_replace('/\D/', '', dato($M, 'telefono'));

// Logo de ERPSoft, embebido: el servidor del portal no tiene el archivo
// (la copia de origen es predial/vendors/images/erpsoftsas-logo.svg).
$logoErpsoft = 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPHN2ZyB2ZXJzaW9uPSIxLjEiIHZpZXdCb3g9IjAgMCAyMDQ4IDU0OCIgd2lkdGg9IjY2OCIgaGVpZ2h0PSIxNzkiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxwYXRoIHRyYW5zZm9ybT0idHJhbnNsYXRlKDM0NiwxNDApIiBkPSJtMCAwIDQgMiA4IDE0IDggMTNoMjRsNi05IDktMTUgMi00IDkgMSAxOCA2IDEgMXYxMmwtMSAxMnYxMGwxNyAxMyAzIDEgMjUtMTEgNS0yIDkgMTEgOCAxMS0yIDQtMTMgMTUtNiA3IDEgNiA1IDE3djJsMTIgMiAyMSA1IDEgMXYyOGwtMzMgOC03IDIwIDEgNiAxMSAxMiA3IDggMiA0LTEzIDE4LTMgNC01LTEtMjYtMTEtNSAyLTE1IDExdjEwbDEgMTN2MTJsLTI0IDgtNC0xLTE2LTI3aC0yNWwtMTcgMjgtNy0xLTE4LTYtMy0yIDItMjd2LTdsLTE0LTEwLTQtMy01IDEtMjYgMTEtNC0yLTEzLTE4LTEtNCAxMi0xMyA5LTExLTEtNS02LTE4LTktMy0yMi01LTItMXYtMjhsOS0zIDIyLTUgMi0xIDctMjAtMS01LTEyLTEzLTgtMTAgMi01IDktMTIgNC02IDYgMSAyNCAxMGg1bDEwLTggNy01LTItMjh2LTZsMTAtNHptMjQgNjYtMTcgNC0xNCA3LTEwIDgtOCA4LTggMTMtNSAxMi0yIDl2MjRsNSAxNyA4IDE0IDEwIDExIDExIDggMTIgNiA5IDMgNiAxaDIzbDE1LTQgMTUtOCAxMi0xMSA3LTggOC0xNiA0LTE2di0xOGwtNC0xNy03LTE0LTktMTEtOS04LTE0LTgtMTUtNS03LTF6IiBmaWxsPSIjMDQzMzc1Ii8+CjxwYXRoIHRyYW5zZm9ybT0idHJhbnNsYXRlKDU3NywyNzEpIiBkPSJtMCAwaDZsOCA3IDI3IDI3LTYgNy00IDQtNC0yLTE1LTE1LTItMXY4bC00IDIyLTcgMjQtNyAxNi0xMSAyMS0xMSAxNi05IDExLTkgMTAtOSA5LTExIDktMTQgMTAtMTUgOS0xNiA4LTI0IDktMjUgNi0zLTEtMy0xMnYtM2wyNS02IDIyLTggMjAtMTAgMTgtMTIgMTEtOSAxMC05IDktOSAxMS0xNCAxMC0xNSA5LTE3IDgtMjEgNi0yMyAzLTE2LTcgNi05IDktNC0xLTctOHYtNGwxMS0xMCA3LTggMTAtMTB6IiBmaWxsPSIjMkQ2MEIwIi8+CjxwYXRoIHRyYW5zZm9ybT0idHJhbnNsYXRlKDE4MywzMTApIiBkPSJtMCAwaDRsNiAyNSA1IDE1IDkgMjAgOSAxNSAxMCAxNCAxMyAxNSA4IDggMTEgOSAxNCAxMCAxOCAxMCAxNSA3IDI0IDggMjYgNS02LTctNS02LTMtMiAxLTQgOS05IDcgNiAyNyAyNyAyIDUtNCA3LTMwIDMwLTQtMS03LTh2LTRsMTYtMTZ2LTFsLTIyLTMtMjMtNi0yMS04LTIwLTEwLTE1LTEwLTEzLTEwLTEyLTExLTE0LTE0LTEzLTE3LTExLTE4LTExLTIzLTgtMjQtNC0xOSAxLTN6IiBmaWxsPSIjMkQ2MEIwIi8+CjxwYXRoIHRyYW5zZm9ybT0idHJhbnNsYXRlKDQwNCw0MCkiIGQ9Im0wIDAgNCAyIDggOC02IDctMTIgMTIgMzIgNiAyMCA2IDIzIDEwIDE4IDEwIDE3IDEyIDE0IDEyIDE1IDE1IDExIDE0IDEwIDE1IDE0IDI3IDkgMjcgNSAyMHYybC0xMCAyaC02bC00LTE1LTctMjMtOC0xOC05LTE2LTEyLTE3LTEwLTExLTctOC0yMi0xOC0xOC0xMS0xNi04LTIxLTgtMjEtNS0xNS0zIDcgOCA5IDktNiA3LTMgNC01LTEtMTMtMTMtNy04LTExLTExLTEtNiA5LTExIDUtNCAxLTJoMmwyLTQgNy02IDQtNWgydi0yeiIgZmlsbD0iIzJENjFCMCIvPgo8cGF0aCB0cmFuc2Zvcm09InRyYW5zbGF0ZSgzNDAsNzIpIiBkPSJtMCAwaDVsMiAxMS0xIDUtMjIgNS0xOCA2LTE4IDgtMjAgMTItMTMgMTAtMTIgMTEtMTEgMTEtMTEgMTQtOSAxNC0xMSAyMS02IDE1LTYgMjItMyAxN3YzbDE2LTE2IDQgMiA1IDYgMyAyLTIgNC0zMCAzMC0zIDItNy0xLTI5LTI5LTQtMyAxLTQgOC04IDQgMSAxNiAxNiA1LTI4IDYtMjEgMTAtMjQgMTAtMTggMTItMTcgOC0xMCAxNS0xNiA4LTcgMTMtMTAgMTQtOSAxNi05IDE5LTggMjMtN3oiIGZpbGw9IiMyQzYwQjAiLz4KPHBhdGggdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMTA0NSwyNDApIiBkPSJtMCAwaDcwbDggNSA1IDh2NmgtMTJsLTMtNWgtNjZsLTIgNXYxMGwxIDMgMiAxIDY1IDEgNyAzIDYgNyAyIDQgMSAxMi0yIDEwLTcgOC05IDNoLTY1bC04LTQtNi05LTEtNmgxM2wzIDVoNjVsMi0yIDEtMTEtMS01LTItMS02NS0xLTctMy03LTgtMi02di0xM2wzLTggNC01eiIgZmlsbD0iIzI5MjQ3QSIvPgo8cGF0aCB0cmFuc2Zvcm09InRyYW5zbGF0ZSgxNTAzLDI0MCkiIGQ9Im0wIDBoNjlsNyA0IDYgOCAxIDdoLTEzbC0yLTVoLTY3bC0yIDggMSA5IDMgMiA2NCAxIDggMyA2IDcgMiA2djE1bC0zIDgtNyA2LTcgMmgtNjVsLTctMy01LTYtMy02di00aDEybDMgNHYybDY2LTEgMi00di0xNWwtNjctMS04LTMtNS02LTMtNnYtMTdsNC04IDctNnoiIGZpbGw9IiMyOTI0N0IiLz4KPHBhdGggdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMTc3MCwyNDApIiBkPSJtMCAwaDY5bDcgNCA1IDYgMiA0djVoLTEzbC0yLTUtMTQtMS01MyAxLTEgM3YxM2wyIDMgNjUgMSA2IDIgNiA1IDMgNSAxIDR2MTZsLTQgOC02IDUtOCAyaC02NGwtOC00LTQtNS0zLTZ2LTRoMTNsMiA2IDY4LTF2LTE3bC0xLTItNjYtMS03LTMtNS01LTQtOHYtMTVsMy04IDctNnoiIGZpbGw9IiMyQTI0N0EiLz4KPHBhdGggdHJhbnNmb3JtPSJ0cmFuc2xhdGUoODgwLDc4KSIgZD0ibTAgMGg2Mmw3IDIgNiA1IDQgOC0xIDUtMTItMS0zLTVoLTY3djE2bDEgMyAxMCAxaDU0bDggMiA1IDQgNCA2IDEgNHYxNmwtMyA3LTQgNS03IDNoLTcwbC02LTQtNi04LTEtN2gxMmwzIDUgMiAxaDYybDMtMSAxLTN2LTEzbC0yLTMtNjctMS02LTMtNi03LTItNXYtMTZsNC05IDgtNnoiIGZpbGw9IiMyOTIzN0EiLz4KPHBhdGggdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMTU5OCw3OCkiIGQ9Im0wIDBoNjNsOSAzIDYgNyAzIDktMiAxLTEyLTEtMS01aC02N2wtMiA0djExbDIgNCA5IDFoNTNsOSAyIDcgNiAzIDYgMSAxNC0zIDEwLTUgNi03IDNoLTcwbC04LTYtNS0xMCAxLTNoMTJsMiAzdjNoNjRsNC0yIDEtMTItMS02LTY5LTEtOC01LTUtOC0xLTV2LTEwbDMtMTAgOC03eiIgZmlsbD0iIzI5MjI3QSIvPgo8cGF0aCB0cmFuc2Zvcm09InRyYW5zbGF0ZSgxMDI5LDc4KSIgZD0ibTAgMGg2Mmw4IDIgNyA3IDMgNnY0aC0xM2wtMy01aC02N2wtMSAxMyAyIDYgNjMgMSA3IDEgOSA4IDMgOHYxNGwtMyA3LTYgNy01IDJoLTcwbC01LTMtNi03LTItNHYtNWgxMmwzIDUgMiAxaDY1bDEtNnYtMTBsLTEtMy0yLTEtNjYtMS02LTMtNi03LTItNHYtMThsNC04IDgtNnoiIGZpbGw9IiMyOTIyN0EiLz4KPHBhdGggdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMTMzMCw3OCkiIGQ9Im0wIDAgOSAxIDYgNyAyNiAzNiAxMiAxNyAzIDMgMi00IDktMTMgMTQtMTkgMTMtMTggNi05IDQtMWg3djgxaC0xM2wtMS01Mi04IDEwLTIwIDI4LTkgMTItMyAzLTUtMS03LTgtMTAtMTQtMTMtMTgtOS0xMnYtMmgtMmwxIDJ2NTJoLTEzdi04MHoiIGZpbGw9IiMyOTI0N0IiLz4KPHBhdGggdHJhbnNmb3JtPSJ0cmFuc2xhdGUoNjkwLDI0MCkiIGQ9Im0wIDBoOTBsMSAzdjhsLTMgMmgtNzRsLTEgMjFoNjhsMSA0LTEgOWgtNjh2MjFoNzdsMSAzLTEgMTBoLTg5bC0xLTF6IiBmaWxsPSIjMkEyNTdBIi8+CjxwYXRoIHRyYW5zZm9ybT0idHJhbnNsYXRlKDEyMTksNzgpIiBkPSJtMCAwaDg5bDEgMXYxMmgtNzhsMSAyMWg2N2wxIDF2MTJoLTY5djIyaDc4djEyaC05MHoiIGZpbGw9IiMyODIxNzkiLz4KPHBhdGggdHJhbnNmb3JtPSJ0cmFuc2xhdGUoODAxLDI0MCkiIGQ9Im0wIDBoODJsOCA0IDYgOSAxIDd2MTBsLTEgOS0zIDUgMSA0IDIgNCAxIDI4LTEgMWgtMTJsLTEtMjQtMS01LTcwLTF2MzBoLTEyem03MCAxMi01OCAxdjI1bDEgMWg2NmwzLTEgMS0ydi0yMmwtMS0xeiIgZmlsbD0iIzI5MjE3OCIvPgo8cGF0aCB0cmFuc2Zvcm09InRyYW5zbGF0ZSgxMTY1LDI0MCkiIGQ9Im0wIDBoNTdsOCAzIDYgNSA1IDggMSAzdjQzbC01IDEwLTcgNi01IDItOCAxaC01MGwtOS0zLTctNi01LTEwdi00M2w1LTEwIDctNnptMjEgMTItMjAgMS01IDQtMyA3djMzbDMgNyA2IDRoNTRsNi00IDItNHYtNDBsLTUtNi0yLTF6IiBmaWxsPSIjMkEyNDdBIi8+CjxwYXRoIHRyYW5zZm9ybT0idHJhbnNsYXRlKDkxOCwyNDApIiBkPSJtMCAwaDgybDggNCA2IDkgMSA3djE0bC0yIDctNyA4LTUgMmgtNzF2MzBoLTEybC0xLTV2LTc1em00NyAxMi0zNSAxdjI2aDY3bDMtMSAyLTd2LTEzbC0zLTUtNC0xeiIgZmlsbD0iIzJBMjI3OCIvPgo8cGF0aCB0cmFuc2Zvcm09InRyYW5zbGF0ZSgxNTEyLDc3KSIgZD0ibTAgMCA0IDIgOCAxMCAxOCAyNyAyMyAzNCA2IDloLTE2bC03LTEwLTQtNmgtNjNsLTEwIDE1LTEgMWgtMTVsMi00IDEwLTE1IDE1LTIyIDE4LTI3IDgtMTF6bS0xIDIwLTE2IDI0LTUgOGg0NGwtMi01LTE1LTIyLTQtNXoiIGZpbGw9IiMyQTI0NzkiLz4KPHBhdGggdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMTY2OSwyMzkpIiBkPSJtMCAwIDUgMSA4IDEwIDE4IDI3IDIzIDM0IDYgOXYxaC0xNWwtOC0xMS00LTZoLTYybC0xMSAxNi0xIDFoLTE1bDQtNyAyMS0zMSAyNy00MHptMSAxOS04IDEyLTEzIDE5IDEgMmg0MmwtMS00LTE5LTI5eiIgZmlsbD0iIzI5MjI3OSIvPgo8cGF0aCB0cmFuc2Zvcm09InRyYW5zbGF0ZSgxMjYzLDI0MCkiIGQ9Im0wIDBoOTB2MTJsLTc3IDEtMSAyMWg2OGwxIDF2MTFsLTEgMWgtNjh2MzRoLTEybC0xLTF2LTc5eiIgZmlsbD0iIzI4MjI3QSIvPgo8cGF0aCB0cmFuc2Zvcm09InRyYW5zbGF0ZSg1OTIsMzUyKSIgZD0ibTAgMCA3IDEgOCAzLTIgOS05IDIxLTkgMTctMTAgMTUtMTAgMTMtMTEgMTMtMTYgMTYtMTQgMTEtMTQgMTAtMTggMTEtNC0xLTUtMTAgMS00IDE2LTkgMTQtMTAgMTMtMTEgMTQtMTMgNy04IDEyLTE1IDEyLTE5IDEwLTE5IDctMTd6IiBmaWxsPSIjNjc2NjY2Ii8+CjxwYXRoIHRyYW5zZm9ybT0idHJhbnNsYXRlKDI2NCw2NSkiIGQ9Im0wIDBoMmw2IDEyLTEgMy0xMSA2LTE2IDExLTE2IDEzLTMgM2gtMnYybC04IDctOSAxMC0xMCAxMy0xMCAxNS0xMCAxOC0xMCAyMi0yIDYtMTEtMy00LTIgMy05IDctMTcgMTEtMjEgMTMtMTkgMTEtMTQgMTItMTMgOC04IDExLTkgMTMtMTAgMTktMTJ6IiBmaWxsPSIjNjY2Ii8+CjxwYXRoIHRyYW5zZm9ybT0idHJhbnNsYXRlKDEzNjQsMjQwKSIgZD0ibTAgMGg4NnYxMmwtMzcgMXY2OGgtMTNsLTEtNjhoLTM1bC0xLTF2LTExeiIgZmlsbD0iIzJBMjU3QSIvPgo8cGF0aCB0cmFuc2Zvcm09InRyYW5zbGF0ZSgxMTE5LDc4KSIgZD0ibTAgMGg4NmwxIDItMSAxMWgtMzZ2NjhoLTEzdi02OGgtMzdsLTEtMTB6IiBmaWxsPSIjMjkyMjc5Ii8+CjxwYXRoIHRyYW5zZm9ybT0idHJhbnNsYXRlKDk3OSw3OCkiIGQ9Im0wIDBoMTJsMSAxdjgwaC0xM3oiIGZpbGw9IiMyQzI1NzciLz4KPHBhdGggdHJhbnNmb3JtPSJ0cmFuc2xhdGUoODk0LDQ2NSkiIGQ9Im0wIDBoN2w0IDE1IDQtMTVoN2w0IDE2IDQtMTZoN2wtNCAyMC0zIDUtNSAxLTMtMS0yLTQtMi05LTMgMTEtNSAzLTUtMi00LTE2eiIgZmlsbD0iIzBDMEUxMCIvPgo8cGF0aCB0cmFuc2Zvcm09InRyYW5zbGF0ZSgxNTAxLDQ2NCkiIGQ9Im0wIDAgNyAxIDQgNSAxIDJ2OWwtMyA2LTMgMy04IDEtMy0xdjlsLTEgMWgtNWwtMS0xLTEtMjAgMS0xNGg5em0tNCA3LTEgMnYxMGwyIDIgNS0xIDItMnYtMTBsLTEtMXoiIGZpbGw9IiMwQTBDMEUiLz4KPHBhdGggdHJhbnNmb3JtPSJ0cmFuc2xhdGUoNzk0LDQ1NikiIGQ9Im0wIDAgMTAgMSAzIDItMSA1LTEzLTEgMSA0IDEzIDcgMiAydjhsLTUgNS00IDJoLTlsLTYtNCAxLTUgMy0xIDYgMyA1LTEtMS00LTEwLTUtNC01IDEtOCA1LTR6IiBmaWxsPSIjMEIwQzBGIi8+CjxwYXRoIHRyYW5zZm9ybT0idHJhbnNsYXRlKDE0MzUsNDU2KSIgZD0ibTAgMCAxMCAxIDMgMi0xIDVoLTEybC0zIDMtMSA4IDIgNyA0IDIgNi0xIDUtMXY1bC02IDRoLTlsLTctNS0zLTYtMS03IDItOCA0LTZ6IiBmaWxsPSIjMEIwRDBGIi8+CjxwYXRoIHRyYW5zZm9ybT0idHJhbnNsYXRlKDEyMjgsNDY0KSIgZD0ibTAgMCA5IDEgNCA0IDEgNHYxNmwtMSAxaC03bC05IDEtNS00LTEtNSAzLTUgOC0zaDN2LTRsLTExIDItMS0zIDItNHptMSAxNS0yIDIgMSA0IDYtMSAxLTQtMS0xeiIgZmlsbD0iIzBEMEYxMSIvPgo8cGF0aCB0cmFuc2Zvcm09InRyYW5zbGF0ZSgxNzA4LDQ2NCkiIGQ9Im0wIDAgOCAxIDYgNSAxIDN2OWwtMyA1LTYgNGgtOGwtNS00LTMtNnYtN2wzLTYgNC0zem0xIDYtMyAzLTEgNCAyIDcgNSAxIDMtM3YtOWwtMy0zeiIgZmlsbD0iIzBFMEYxMiIvPgo8cGF0aCB0cmFuc2Zvcm09InRyYW5zbGF0ZSgxNjMwLDQ2NCkiIGQ9Im0wIDAgOSAxIDQgNSAxIDZ2MTFsLTIgNC03LTEtOSAxLTQtNHYtOGw2LTQgNy0xLTEtMy0xMSAxIDEtNnptMSAxNS0yIDIgMSA0IDUtMSAxLTV6IiBmaWxsPSIjMEQwRjEyIi8+CjxwYXRoIHRyYW5zZm9ybT0idHJhbnNsYXRlKDE1MzAsNDY0KSIgZD0ibTAgMCA5IDEgMyA0IDEgNHYxNmwtNCAyLTQtMS05IDEtNS01di02bDYtNSA4LTEtMS0zLTExIDEtMS00IDMtM3ptMiAxNS00IDIgMSA0aDVsMi0ydi00eiIgZmlsbD0iIzBDMEQwRiIvPgo8cGF0aCB0cmFuc2Zvcm09InRyYW5zbGF0ZSgxMzQzLDQ2NCkiIGQ9Im0wIDAgNyAxIDMgM3YyMmgtN2wtMS0xOGgtNmwtMSAxOGgtN2wtMS0xdi0yNGgxMHoiIGZpbGw9IiMwQTBEMTEiLz4KPHBhdGggdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMTMwNiw0NjQpIiBkPSJtMCAwaDZsNiA0IDMgNHYxMWwtNSA2LTQgMmgtN2wtNi00LTMtNyAxLTggNC02em0xIDYtMyAzLTEgNCAzIDcgNSAxIDMtNS0xLTctMy0zeiIgZmlsbD0iIzBDMEQxMCIvPgo8cGF0aCB0cmFuc2Zvcm09InRyYW5zbGF0ZSgxMTY3LDQ2NCkiIGQ9Im0wIDAgNyAxIDUgNCAyIDV2N2wtMyA2LTQgMy0zIDFoLTdsLTctNi0xLTN2LTlsMy01IDMtM3ptLTEgNi0zIDUgMSA4IDIgMiA1LTEgMi0ydi05bC0zLTN6IiBmaWxsPSIjMEMwRTEwIi8+CjxwYXRoIHRyYW5zZm9ybT0idHJhbnNsYXRlKDgyNiw0NjQpIiBkPSJtMCAwIDggMSA2IDUgMSAzdjEwbC02IDctMyAxaC03bC02LTQtMy02di03bDMtNiA0LTN6bTEgNi0zIDN2OWwzIDMgNS0xIDItMy0xLTktMi0yeiIgZmlsbD0iIzBBMEMwRiIvPgo8cGF0aCB0cmFuc2Zvcm09InRyYW5zbGF0ZSgxNDY1LDQ2NCkiIGQ9Im0wIDAgOSAxIDMgMyAxIDN2MTlsLTMgMS01LTEtOSAxLTUtNXYtNmw0LTQgNi0yaDR2LTNsLTExIDEtMS01em0xIDE1LTMgMyAxIDNoNWwyLTJ2LTR6IiBmaWxsPSIjMEIwRDExIi8+CjxwYXRoIHRyYW5zZm9ybT0idHJhbnNsYXRlKDE3MzMsNDY0KSIgZD0ibTAgMCA3IDIgNS0yIDcgMSAzIDV2MjBoLTdsLTEtMTktNiAxLTEgMTUtMSAzaC02bC0xLTF2LTI0eiIgZmlsbD0iIzBBMEIwQyIvPgo8cGF0aCB0cmFuc2Zvcm09InRyYW5zbGF0ZSg5NDcsNDY0KSIgZD0ibTAgMCA5IDEgMyAzIDEgM3YxOWgtOGwtOSAxLTUtNXYtNmw0LTQgNi0yaDRsLTEtMy0xMSAxdi01bDItMnptMSAxNS0zIDIgMSA0IDctMXYtNXoiIGZpbGw9IiMwQTBDMEQiLz4KPHBhdGggdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMWUzIDQ2NCkiIGQ9Im0wIDBoN2w2IDUgMiAxMC0xIDFoLTE1bDEgNGgxM2wtMSA1LTQgMmgtOGwtNi00LTMtNiAxLTkgNC02em0xIDYtMiA0IDEgMWg3bC0xLTV6IiBmaWxsPSIjMEUwRjExIi8+CjxwYXRoIHRyYW5zZm9ybT0idHJhbnNsYXRlKDExMzcsNDY0KSIgZD0ibTAgMCA3IDEgMyA1djIwaC03bC0xLTE5aC03bC0xIDE5aC03di0yNWgxMHoiIGZpbGw9IiMwODA5MEQiLz4KPHBhdGggdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMTEwMyw0NjQpIiBkPSJtMCAwIDcgMSAzIDMgMSA4djEzbC0xIDFoLTd2LTE5bC03IDEtMSAxOGgtN3YtMjVoMTB6IiBmaWxsPSIjMDkwQTBEIi8+CjxwYXRoIHRyYW5zZm9ybT0idHJhbnNsYXRlKDg1Niw0NTQpIiBkPSJtMCAwaDEwbDEgMi0yIDMtNSAydjNsNCAxLTEgNS0zIDItMSAxOC0zIDEtNC0xLTEtMTUtMi01LTEtMyA2LTExeiIgZmlsbD0iIzA4MEEwQiIvPgo8cGF0aCB0cmFuc2Zvcm09InRyYW5zbGF0ZSgxMTg4LDQ2NCkiIGQ9Im0wIDAgNyAyIDMgOSAxIDhoMmwzLTE0IDItNCA1LTEgMSA1LTYgMTgtMiAzLTYgMS00LTQtNi0xOHoiIGZpbGw9IiMwQzBEMEYiLz4KPHBhdGggdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMTYwMyw0NTcpIiBkPSJtMCAwIDQgMSAyIDYgNSAyLTEgNC00IDJ2MTNsNSAxLTEgNWgtOGwtNC00LTEtMy0xLTEyLTItMiAxLTUgMy01eiIgZmlsbD0iIzBCMEMwRiIvPgo8cGF0aCB0cmFuc2Zvcm09InRyYW5zbGF0ZSg4NzcsNDU3KSIgZD0ibTAgMCAzIDEgMSA2IDYgMnY0bC01IDEtMSA0djdsMSAyIDUgMnY0bC0yIDFoLTdsLTQtNC0yLTEwLTItNy0xLTMgNC01IDItNHoiIGZpbGw9IiMwQzBEMTAiLz4KPHBhdGggdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMTI1Nyw0NjUpIiBkPSJtMCAwaDEzdjVsLTEgMS05IDEtMSAydjdsMiAzaDlsMSAyLTIgNC0zIDFoLTdsLTUtMy0zLTQtMS04IDMtOHoiIGZpbGw9IiMwRTEwMTQiLz4KPHBhdGggdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMTY1OSw0NjUpIiBkPSJtMCAwaDEybDEgNC0zIDItNyAxLTEgMXY4bDIgM2g5bC0xIDUtNCAyaC03bC02LTUtMi01IDEtMTAgNC01eiIgZmlsbD0iIzBEMEUxMSIvPgo8cGF0aCB0cmFuc2Zvcm09InRyYW5zbGF0ZSgxNTU4LDQ2NSkiIGQ9Im0wIDBoMTNsMSA0LTQgMi03IDEtMSA3IDEgNCA3IDFoNGwtMSA1LTQgMmgtN2wtNS0zLTMtNXYtMTF6IiBmaWxsPSIjMEIwRDBGIi8+CjxwYXRoIHRyYW5zZm9ybT0idHJhbnNsYXRlKDEwNDMsNDUxKSIgZD0ibTAgMCAzIDF2NTBsLTUgMS0xLTF2LTUweiIgZmlsbD0iIzE2MUIyNyIvPgo8cGF0aCB0cmFuc2Zvcm09InRyYW5zbGF0ZSgxMzc5LDQ1MSkiIGQ9Im0wIDAgNSAxdjUxaC01bC0xLTUxeiIgZmlsbD0iIzE4MTkxQSIvPgo8cGF0aCB0cmFuc2Zvcm09InRyYW5zbGF0ZSg0MDMsMjcxKSIgZD0ibTAgMGgxNWwxIDF2MTRsLTEgMWgtMTVsLTEtNnoiIGZpbGw9IiMyQzI3NzkiLz4KPHBhdGggdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMzcwLDI3MSkiIGQ9Im0wIDBoMTZ2MTZoLTE1bC0xLTF6IiBmaWxsPSIjMkIyNjdBIi8+CjxwYXRoIHRyYW5zZm9ybT0idHJhbnNsYXRlKDMzOCwyNzEpIiBkPSJtMCAwaDE2djE1bC0xIDFoLTE1eiIgZmlsbD0iIzI4MjM3QSIvPgo8cGF0aCB0cmFuc2Zvcm09InRyYW5zbGF0ZSg5ODQsNDY0KSIgZD0ibTAgMCAzIDF2NWwtNyAzLTEgMS0xIDE2aC03bC0xLTF2LTI0bDkgMXoiIGZpbGw9IiMwRjEwMTMiLz4KPHBhdGggdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMjk1LDUxKSIgZD0ibTAgMGgybDQgMTJ2M2wtMTEgNS00LTEtNS0xMXYtMnoiIGZpbGw9IiM2MzY1NkEiLz4KPHBhdGggdHJhbnNmb3JtPSJ0cmFuc2xhdGUoNDY4LDQ4NikiIGQ9Im0wIDAgMyAyIDQgOXY0bC0xMyA1aC0ybC01LTEyIDEtM3oiIGZpbGw9IiM2NjY1NjYiLz4KPHBhdGggdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMTA3Miw0NTcpIiBkPSJtMCAwaDZsMiAzLTEgMzBoLTd6IiBmaWxsPSIjMDMwNDA0Ii8+CjxwYXRoIHRyYW5zZm9ybT0idHJhbnNsYXRlKDE1ODEsNDY1KSIgZD0ibTAgMGg3bDEgNnYxOGwtMSAxaC03eiIgZmlsbD0iIzBEMEUwRSIvPgo8cGF0aCB0cmFuc2Zvcm09InRyYW5zbGF0ZSgxMjgxLDQ2NCkiIGQ9Im0wIDAgNiAxIDEgNS0xIDIwaC03bC0xLTQgMS0yMXoiIGZpbGw9IiMwODA5MDkiLz4KPHBhdGggdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMTY4Myw0NjQpIiBkPSJtMCAwIDYgMXYyNWgtN3YtMjV6IiBmaWxsPSIjMDMwNDA1Ii8+CjxwYXRoIHRyYW5zZm9ybT0idHJhbnNsYXRlKDE1OTYsMzExKSIgZD0ibTAgMGgxM3YxMGgtMTJsLTEtMXoiIGZpbGw9IiMyOTI5N0YiLz4KPHBhdGggdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMTczMywzMTEpIiBkPSJtMCAwaDEybDEgMXY4bC0xIDFoLTExbC0xLTF6IiBmaWxsPSIjMjkyODdGIi8+CjxwYXRoIHRyYW5zZm9ybT0idHJhbnNsYXRlKDI5NSw1MSkiIGQ9Im0wIDBoMmw0IDEydjNsLTExIDUtNC0xLTEtNmgzdjJsOC0yLTItNy0yLTR6IiBmaWxsPSIjMzE1Q0ExIi8+CjxwYXRoIHRyYW5zZm9ybT0idHJhbnNsYXRlKDE3MTQsNDQ5KSIgZD0ibTAgMCA1IDF2NGwtNiA1aC01bDEtNHoiIGZpbGw9IiMwQzBGMTIiLz4KPHBhdGggdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMTMxMiw0NDkpIiBkPSJtMCAwIDUgMXY0bC01IDVoLTVsLTEtMnoiIGZpbGw9IiMwRDBEMTEiLz4KPHBhdGggdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMTI4MSw0NTMpIiBkPSJtMCAwIDYgMSAxIDUtMSAxaC03bC0xLTV6IiBmaWxsPSIjMTUxNTE3Ii8+CjxwYXRoIHRyYW5zZm9ybT0idHJhbnNsYXRlKDE2ODQsNDUzKSIgZD0ibTAgMCA1IDEgMSA1LTEgMWgtN2wtMS01eiIgZmlsbD0iIzEzMTQxNiIvPgo8cGF0aCB0cmFuc2Zvcm09InRyYW5zbGF0ZSgxNTgyLDQ1MykiIGQ9Im0wIDAgNiAxdjZsLTQgMS0zLTF2LTZ6IiBmaWxsPSIjMTQxNDE2Ii8+CjxwYXRoIHRyYW5zZm9ybT0idHJhbnNsYXRlKDI2NCw2NSkiIGQ9Im0wIDBoMnY2aC04bDEtM3oiIGZpbGw9IiMzODVFOUQiLz4KPC9zdmc+Cg==';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Portal Tributario | <?= e($alcaldia) ?></title>
    <meta name="description" content="Portal Tributario de <?= e($ciudad) ?>: <?= e(implode(', ', array_map(function ($t) { return $t['titulo']; }, array_filter($tarjetas, function ($t) { return !$t['pronto']; })))) ?> en línea.">
    <meta name="theme-color" content="<?= $profundo ?>">
    <link rel="icon" type="image/png" href="<?= e(dato($M, 'escudo')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Source+Serif+4:ital,opsz,wght@0,8..60,600;0,8..60,700;1,8..60,600&family=Inter:wght@400;500;600;700&display=swap">
    <?php if ($foto !== ''): ?><link rel="preload" as="image" href="<?= e($foto) ?>"><?php endif; ?>
    <style>
        /* Colores del municipio (de $MUNICIPIOS); los demás se derivan de ellos. */
        :root {
            --primario: <?= $primario ?>;
            --primario-rgb: <?= rgbDe($primario) ?>;
            --primario-oscuro: <?= $oscuro ?>;
            --profundo: <?= $profundo ?>;
            --profundo-rgb: <?= rgbDe($profundo) ?>;
            --suave: <?= $suave ?>;
            --oro: #c99a2e;
            --oro-claro: #e8c66a;

            --papel: #f3f6f5;
            --tarjeta: #ffffff;
            --linea: #dce6e3;
            --tinta: #12201d;
            --tenue: #4f625d;

            --radio: 18px;
            --serif: "Source Serif 4", Georgia, "Times New Roman", serif;
            --sans: "Inter", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            --salida: cubic-bezier(.2, .75, .2, 1);
        }

        *, *::before, *::after { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body {
            margin: 0;
            background: var(--papel);
            color: var(--tinta);
            font: 400 15px/1.55 var(--sans);
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }
        img { max-width: 100%; }
        a { color: inherit; }
        h1, h2, h3 { text-wrap: balance; }
        .contenedor {
            width: 100%;
            max-width: 1220px;
            margin-inline: auto;
            padding-inline: clamp(16px, 4vw, 40px);
        }
        .oculto {
            position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
            overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0;
        }

        /* Entrada: sube y aparece, escalonado. Nada queda oculto si no hay animación. */
        @keyframes subir { from { opacity: 0; transform: translateY(22px); } to { opacity: 1; transform: none; } }
        .entra { animation: subir .8s var(--salida) both; }
        .d1 { animation-delay: .05s; } .d2 { animation-delay: .15s; } .d3 { animation-delay: .25s; }
        .d4 { animation-delay: .35s; } .d5 { animation-delay: .45s; } .d6 { animation-delay: .55s; }

        /* ===================== ENCABEZADO: el logo, en grande ===================== */
        .cabecera {
            position: relative;
            z-index: 5;
            background: #fff;
            box-shadow: 0 1px 0 var(--linea), 0 8px 24px -18px rgba(var(--profundo-rgb), .35);
        }
        .cabecera::before {
            content: "";
            display: block;
            height: 5px;
            background: linear-gradient(90deg, var(--oro), var(--oro-claro) 18%, var(--primario) 52%, var(--primario-oscuro));
        }
        .cabecera-fila {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding-block: clamp(10px, 1.4vw, 18px);
        }
        .marca { display: flex; align-items: center; min-width: 0; }
        .marca-logo { display: block; height: 80px; height: clamp(64px, 5vw + 44px, 108px); width: auto; max-width: 100%; object-fit: contain; object-position: left center; }
        .lockup { display: flex; align-items: center; gap: clamp(10px, 1.2vw, 16px); min-width: 0; }
        .lockup img { height: 80px; height: clamp(64px, 5vw + 44px, 108px); width: auto; flex: none; }
        .lockup-linea { width: 2px; align-self: stretch; margin-block: 8px; background: #1d1d1b; flex: none; }
        .lockup-texto { display: flex; flex-direction: column; color: #1d1d1b; line-height: 1; min-width: 0; }
        .lockup-texto span { font-size: clamp(15px, 1.1vw + 10px, 26px); font-weight: 400; }
        .lockup-texto strong { font-size: clamp(26px, 2.4vw + 16px, 52px); font-weight: 700; letter-spacing: -.015em; margin-top: 2px; }

        .cabecera-derecha { display: flex; align-items: center; gap: 24px; flex: none; }
        .dependencia { display: none; margin: 0; max-width: 30ch; text-align: right; font-size: 13px; line-height: 1.4; color: var(--tenue); }
        .dependencia span {
            display: block; margin-bottom: 2px;
            font-size: 11px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase;
            color: var(--primario-oscuro);
        }
        @media (min-width: 960px) { .dependencia { display: block; } }

        .boton-acceso {
            display: inline-flex; align-items: center; gap: 8px;
            min-height: 46px; padding: 0 18px;
            border-radius: 12px;
            background: var(--profundo);
            color: #fff;
            font-size: 14px; font-weight: 600; text-decoration: none; white-space: nowrap;
            box-shadow: 0 6px 16px -8px rgba(var(--profundo-rgb), .8);
            transition: transform .2s var(--salida), box-shadow .2s ease, background-color .2s ease;
        }
        .boton-acceso svg { width: 18px; height: 18px; flex: none; }
        .boton-acceso:hover { background: var(--primario-oscuro); transform: translateY(-1px); box-shadow: 0 10px 22px -10px rgba(var(--profundo-rgb), .9); }
        .boton-acceso:focus-visible { outline: 3px solid var(--primario-oscuro); outline-offset: 3px; }
        .boton-acceso .corto { display: none; }
        @media (max-width: 479px) {
            .boton-acceso { padding: 0 13px; }
            .boton-acceso .largo { display: none; }
            .boton-acceso .corto { display: inline; }
        }

        /* ===================== PORTADA ===================== */
        .portada {
            position: relative;
            isolation: isolate;
            overflow: hidden;
            color: #fff;
            background: var(--profundo);
        }
        .portada-foto {
            position: absolute;
            inset: -2%;
            z-index: -2;
            background: <?= $foto !== '' ? "url('" . $foto . "') " . $fotoPos . ' / cover no-repeat' : 'none' ?>;
            animation: acercar 28s ease-in-out infinite alternate;
            transform-origin: 70% 55%;
        }
        @keyframes acercar { from { transform: scale(1.02); } to { transform: scale(1.12); } }
        .portada::before {
            /* Velo: pleno donde va el texto, abierto a la derecha para la foto,
               con un brillo del color del municipio detrás del título. */
            content: "";
            position: absolute;
            inset: 0;
            z-index: -1;
            background:
                radial-gradient(55% 75% at 12% 35%, rgba(var(--primario-rgb), .38) 0%, rgba(var(--primario-rgb), 0) 70%),
                linear-gradient(180deg, rgba(var(--profundo-rgb), .05) 0%, rgba(var(--profundo-rgb), .55) 100%),
                linear-gradient(98deg, rgba(var(--profundo-rgb), .95) 0%, rgba(var(--profundo-rgb), .82) 36%,
                                       rgba(var(--profundo-rgb), .30) 70%, rgba(var(--profundo-rgb), .10) 100%);
        }
        .portada-texto {
            position: relative;
            padding-top: clamp(44px, 6vw, 96px);
            padding-bottom: clamp(150px, 12vw + 70px, 230px);
        }
        .ceja {
            display: inline-flex; align-items: center; gap: 10px;
            margin: 0 0 18px;
            font-size: 12px; font-weight: 700; letter-spacing: .16em; text-transform: uppercase;
            color: rgba(255, 255, 255, .88);
        }
        .ceja::before { content: ""; width: 28px; height: 2px; background: var(--oro-claro); border-radius: 2px; }
        .portada h1 {
            margin: 0 0 18px;
            max-width: 14ch;
            font-family: var(--serif);
            font-size: clamp(40px, 4.4vw + 18px, 80px);
            font-weight: 700;
            line-height: 1.02;
            letter-spacing: -.02em;
        }
        .portada h1 em {
            display: block;
            font-style: italic;
            font-weight: 600;
            color: var(--oro-claro);
        }
        .bajada {
            margin: 0;
            max-width: 46ch;
            font-size: clamp(16px, .6vw + 13px, 20px);
            line-height: 1.55;
            color: rgba(255, 255, 255, .9);
        }
        .acciones { display: flex; flex-wrap: wrap; gap: 12px; margin-top: clamp(24px, 2.4vw, 34px); }
        .boton {
            display: inline-flex; align-items: center; justify-content: center; gap: 10px;
            min-height: 52px; padding: 0 22px;
            border-radius: 14px;
            font-size: 15px; font-weight: 700; text-decoration: none;
            transition: transform .2s var(--salida), box-shadow .2s ease, background-color .2s ease, border-color .2s ease;
        }
        .boton svg { width: 18px; height: 18px; transition: transform .2s var(--salida); }
        .boton:hover svg { transform: translateX(3px); }
        .boton:focus-visible { outline: 3px solid #fff; outline-offset: 3px; }
        .boton-oro {
            background: linear-gradient(135deg, var(--oro-claro), var(--oro));
            color: #2b1f04;
            box-shadow: 0 12px 28px -12px rgba(201, 154, 46, .9);
        }
        .boton-oro:hover { transform: translateY(-2px); box-shadow: 0 18px 34px -14px rgba(201, 154, 46, 1); }
        .boton-vidrio {
            color: #fff;
            background: rgba(255, 255, 255, .10);
            border: 1.5px solid rgba(255, 255, 255, .55);
            -webkit-backdrop-filter: blur(6px);
            backdrop-filter: blur(6px);
        }
        .boton-vidrio:hover { background: rgba(255, 255, 255, .2); border-color: #fff; transform: translateY(-2px); }
        .destacados { list-style: none; display: flex; flex-wrap: wrap; gap: 10px; margin: clamp(20px, 2vw, 28px) 0 0; padding: 0; }
        .destacados li {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 7px 14px 7px 10px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .10);
            border: 1px solid rgba(255, 255, 255, .22);
            -webkit-backdrop-filter: blur(6px);
            backdrop-filter: blur(6px);
            font-size: 13px; font-weight: 500;
        }
        .destacados svg { width: 16px; height: 16px; color: var(--oro-claro); }
        .ola {
            position: absolute;
            left: 0; right: 0; bottom: -1px;
            width: 100%;
            height: clamp(56px, 7vw, 130px);
            display: block;
        }
        .ola .atras { fill: rgba(var(--primario-rgb), .45); }
        .ola .medio { fill: rgba(255, 255, 255, .18); }
        .ola .frente { fill: var(--papel); }
        @media (max-width: 599px) {
            .portada::before {
                background:
                    radial-gradient(90% 60% at 20% 20%, rgba(var(--primario-rgb), .35) 0%, rgba(var(--primario-rgb), 0) 70%),
                    linear-gradient(180deg, rgba(var(--profundo-rgb), .72) 0%, rgba(var(--profundo-rgb), .86) 100%);
            }
            .acciones .boton { width: 100%; }
        }

        /* ===================== TRÁMITES ===================== */
        .tramites { position: relative; z-index: 2; margin-top: calc(-1 * clamp(110px, 8vw + 48px, 170px)); }
        .tarjetas {
            list-style: none; margin: 0; padding: 0;
            display: grid; gap: clamp(14px, 1.4vw, 22px);
            grid-template-columns: 1fr;
        }
        @media (min-width: 600px) {
            .tarjetas.n2, .tarjetas.n4 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .tarjetas.n1 { grid-template-columns: minmax(0, 460px); }
        }
        @media (min-width: 900px) { .tarjetas.n3 { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        @media (min-width: 1100px) { .tarjetas.n4 { grid-template-columns: repeat(4, minmax(0, 1fr)); } }

        .tarjeta {
            position: relative;
            display: flex; flex-direction: column; gap: 8px;
            height: 100%;
            padding: clamp(22px, 1.4vw + 14px, 30px);
            overflow: hidden;
            background: var(--tarjeta);
            border: 1px solid var(--linea);
            border-radius: var(--radio);
            box-shadow: 0 1px 2px rgba(var(--profundo-rgb), .06), 0 16px 36px -18px rgba(var(--profundo-rgb), .38);
            color: var(--tinta);
            text-decoration: none;
            transition: transform .25s var(--salida), box-shadow .25s ease, border-color .25s ease;
        }
        .tarjeta::before {
            /* Franja superior del tono del trámite. */
            content: "";
            position: absolute; inset: 0 0 auto 0;
            height: 5px;
            background: linear-gradient(90deg, var(--tono), rgba(var(--tono-rgb), .45));
        }
        .tarjeta::after {
            /* Brillo del tono, que aparece al pasar el mouse. */
            content: "";
            position: absolute;
            right: -60px; top: -60px;
            width: 180px; height: 180px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(var(--tono-rgb), .16), rgba(var(--tono-rgb), 0) 70%);
            opacity: 0;
            transition: opacity .3s ease;
            pointer-events: none;
        }
        a.tarjeta:hover {
            transform: translateY(-6px);
            border-color: rgba(var(--tono-rgb), .45);
            box-shadow: 0 2px 4px rgba(var(--profundo-rgb), .06), 0 26px 50px -22px rgba(var(--tono-rgb), .65);
        }
        a.tarjeta:hover::after { opacity: 1; }
        a.tarjeta:focus-visible { outline: 3px solid var(--tono-texto); outline-offset: 3px; }
        .tarjeta-icono {
            position: relative;
            display: grid; place-items: center;
            width: 58px; height: 58px;
            margin: 4px 0 8px;
            border-radius: 16px;
            background: var(--tono-suave);
            color: var(--tono-texto);
            transition: transform .3s var(--salida);
        }
        a.tarjeta:hover .tarjeta-icono { transform: rotate(-6deg) scale(1.06); }
        .tarjeta-icono svg { width: 28px; height: 28px; }
        .tarjeta-titulo { font-size: 19px; font-weight: 700; line-height: 1.25; letter-spacing: -.005em; }
        .tarjeta-texto { font-size: 14.5px; line-height: 1.55; color: var(--tenue); }
        .tarjeta-ir {
            display: inline-flex; align-items: center; gap: 8px;
            margin-top: auto; padding-top: 12px;
            font-size: 14.5px; font-weight: 700;
            color: var(--tono-texto);
        }
        .tarjeta-ir svg { width: 18px; height: 18px; transition: transform .25s var(--salida); }
        a.tarjeta:hover .tarjeta-ir svg { transform: translateX(5px); }
        .insignia {
            position: absolute;
            top: clamp(18px, 1.2vw + 12px, 26px);
            right: clamp(18px, 1.2vw + 12px, 26px);
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 11px 5px 8px;
            border-radius: 999px;
            background: linear-gradient(135deg, var(--oro-claro), var(--oro));
            color: #2b1f04;
            font-size: 11px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase;
            box-shadow: 0 6px 14px -6px rgba(201, 154, 46, .9);
        }
        .insignia svg { width: 13px; height: 13px; }
        .tarjeta.pronto { box-shadow: none; background: #f8faf9; border-style: dashed; border-color: #c9d6d2; }
        .tarjeta.pronto::before { display: none; }
        .tarjeta.pronto .tarjeta-ir { color: #5f6e6a; }

        /* ===================== PASOS (Industria y Comercio) ===================== */
        .pasos {
            position: relative;
            isolation: isolate;
            overflow: hidden;
            margin-top: clamp(40px, 5vw, 72px);
            padding: clamp(28px, 3.4vw, 52px);
            border-radius: calc(var(--radio) + 6px);
            color: #fff;
            background:
                radial-gradient(70% 90% at 100% 0%, rgba(var(--primario-rgb), .45) 0%, rgba(var(--primario-rgb), 0) 60%),
                radial-gradient(60% 80% at 0% 100%, rgba(201, 154, 46, .22) 0%, rgba(201, 154, 46, 0) 60%),
                var(--profundo);
        }
        .pasos::after {
            /* Líneas de agua: el lago, como textura. */
            content: "";
            position: absolute; inset: 0; z-index: -1;
            opacity: .12;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='40' viewBox='0 0 160 40'%3E%3Cpath d='M0 20 C 20 8, 40 8, 60 20 S 100 32, 120 20 S 150 8, 160 14' fill='none' stroke='%23ffffff' stroke-width='1.2'/%3E%3C/svg%3E");
            background-size: 160px 40px;
        }
        .pasos-cabeza { display: grid; gap: 18px; align-items: end; margin-bottom: clamp(26px, 3vw, 40px); }
        @media (min-width: 900px) { .pasos-cabeza { grid-template-columns: 1fr auto; } }
        .pasos .ceja { margin-bottom: 10px; }
        .pasos h2 {
            margin: 0 0 10px;
            font-family: var(--serif);
            font-size: clamp(28px, 2vw + 18px, 44px);
            font-weight: 700;
            line-height: 1.08;
            letter-spacing: -.015em;
        }
        .pasos h2 em { font-style: italic; font-weight: 600; color: var(--oro-claro); }
        .pasos-cabeza p { margin: 0; max-width: 52ch; color: rgba(255, 255, 255, .82); }
        .pasos-cabeza .boton { justify-self: start; }
        @media (max-width: 599px) { .pasos-cabeza .boton { justify-self: stretch; } }
        .pasos-lista {
            list-style: none; margin: 0; padding: 0;
            display: grid; gap: 14px;
            grid-template-columns: 1fr;
            counter-reset: paso;
        }
        @media (min-width: 640px) { .pasos-lista { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (min-width: 1040px) { .pasos-lista { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        .paso {
            position: relative;
            padding: 22px 20px 22px;
            border-radius: 16px;
            background: rgba(255, 255, 255, .07);
            border: 1px solid rgba(255, 255, 255, .14);
            -webkit-backdrop-filter: blur(4px);
            backdrop-filter: blur(4px);
            transition: background-color .25s ease, transform .25s var(--salida);
        }
        .paso:hover { background: rgba(255, 255, 255, .11); transform: translateY(-3px); }
        .pasos:last-child, .tramites:last-child { margin-bottom: clamp(40px, 5vw, 72px); }
        .paso-num {
            display: grid; place-items: center;
            width: 42px; height: 42px;
            margin-bottom: 14px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--oro-claro), var(--oro));
            color: #2b1f04;
            font-family: var(--serif);
            font-size: 20px; font-weight: 700;
            font-variant-numeric: tabular-nums;
        }
        .paso h3 { margin: 0 0 6px; font-size: 17px; font-weight: 700; }
        .paso p { margin: 0; font-size: 14px; line-height: 1.55; color: rgba(255, 255, 255, .8); }

        /* ===================== ATENCIÓN Y NORMATIVIDAD ===================== */
        .info {
            display: grid; grid-template-columns: 1fr;
            gap: clamp(32px, 3vw, 56px);
            margin-block: clamp(44px, 5vw, 80px) clamp(44px, 5vw, 72px);
        }
        @media (min-width: 900px) { .info.dos { grid-template-columns: minmax(0, 1fr) minmax(0, 1.25fr); } }
        .seccion-titulo {
            display: flex; align-items: center; gap: 12px;
            margin: 0 0 18px;
            font-family: var(--serif);
            font-size: clamp(24px, 1vw + 18px, 32px);
            font-weight: 700;
            line-height: 1.15;
            letter-spacing: -.01em;
        }
        .seccion-titulo::after { content: ""; flex: 1; height: 1px; background: var(--linea); }
        .contactos { list-style: none; margin: 0; padding: 0; display: grid; gap: 12px; }
        .contacto {
            display: flex; align-items: center; gap: 16px;
            padding: 16px 18px;
            border-radius: 16px;
            background: var(--tarjeta);
            border: 1px solid var(--linea);
            text-decoration: none;
            transition: transform .2s var(--salida), box-shadow .2s ease, border-color .2s ease;
        }
        a.contacto:hover { transform: translateX(4px); border-color: rgba(var(--primario-rgb), .5); box-shadow: 0 12px 26px -18px rgba(var(--profundo-rgb), .6); }
        a.contacto:focus-visible { outline: 3px solid var(--primario-oscuro); outline-offset: 3px; }
        .contacto-icono {
            display: grid; place-items: center; flex: none;
            width: 46px; height: 46px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--primario), var(--primario-oscuro));
            color: #fff;
            box-shadow: 0 8px 18px -10px rgba(var(--primario-rgb), .9);
        }
        .contacto-icono svg { width: 21px; height: 21px; }
        .etiqueta {
            display: block;
            font-size: 11.5px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase;
            color: var(--tenue);
        }
        .valor { display: block; font-size: 15.5px; font-weight: 600; overflow-wrap: anywhere; }
        .valor.cifras { font-variant-numeric: tabular-nums; font-size: 17px; }

        .documentos { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
        .documento {
            display: flex; align-items: center; gap: 14px;
            padding: 14px 16px;
            border-radius: 14px;
            background: var(--tarjeta);
            border: 1px solid var(--linea);
            text-decoration: none;
            transition: transform .2s var(--salida), border-color .2s ease, box-shadow .2s ease;
        }
        .documento:hover { transform: translateY(-2px); border-color: rgba(var(--primario-rgb), .5); box-shadow: 0 12px 26px -18px rgba(var(--profundo-rgb), .6); }
        .documento:focus-visible { outline: 3px solid var(--primario-oscuro); outline-offset: 3px; }
        .documento-icono {
            display: grid; place-items: center; flex: none;
            width: 40px; height: 46px;
            border-radius: 8px 14px 8px 8px;
            background: var(--suave);
            color: var(--primario-oscuro);
        }
        .documento-icono svg { width: 20px; height: 20px; }
        .documento-texto { min-width: 0; }
        .documento .valor { font-size: 15px; }
        .documento-tipo {
            margin-left: auto; flex: none;
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 11.5px; font-weight: 800; letter-spacing: .06em;
            color: #b4432f;
        }
        .documento-tipo svg { width: 15px; height: 15px; color: var(--tenue); }

        /* ===================== PIE ===================== */
        .pie {
            position: relative;
            isolation: isolate;
            overflow: hidden;
            color: rgba(255, 255, 255, .8);
            background:
                radial-gradient(60% 120% at 0% 0%, rgba(var(--primario-rgb), .28) 0%, rgba(var(--primario-rgb), 0) 60%),
                var(--profundo);
        }
        .pie-escudo {
            position: absolute;
            z-index: -1;
            right: -40px; bottom: -60px;
            width: clamp(220px, 26vw, 380px);
            opacity: .07;
            filter: grayscale(1) brightness(2);
            pointer-events: none;
        }
        .pie-columnas {
            display: grid; gap: 28px;
            grid-template-columns: 1fr;
            padding-block: clamp(36px, 4vw, 56px) 26px;
        }
        @media (min-width: 720px) { .pie-columnas { grid-template-columns: 1.3fr 1fr 1fr; } }
        .pie-marca { display: flex; align-items: flex-start; gap: 14px; }
        .pie-marca img { height: 64px; width: auto; flex: none; }
        .pie-marca strong { display: block; margin-bottom: 4px; color: #fff; font-family: var(--serif); font-size: 21px; font-weight: 700; }
        .pie-marca p { margin: 0; font-size: 13.5px; line-height: 1.5; color: rgba(255, 255, 255, .72); }
        .pie h2 {
            margin: 4px 0 14px;
            font-size: 12px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase;
            color: var(--oro-claro);
        }
        .pie ul { list-style: none; margin: 0; padding: 0; display: grid; gap: 9px; }
        .pie-enlace { font-size: 14px; text-decoration: none; color: rgba(255, 255, 255, .86); }
        .pie-enlace:hover { color: #fff; text-decoration: underline; text-underline-offset: 3px; }
        .pie-enlace:focus-visible { outline: 2px solid var(--oro-claro); outline-offset: 3px; border-radius: 4px; }
        .pie-base {
            display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
            gap: 14px 24px;
            padding-block: 18px 22px;
            border-top: 1px solid rgba(255, 255, 255, .12);
            font-size: 12.5px;
            color: rgba(255, 255, 255, .62);
        }
        .pie-plataforma { display: inline-flex; align-items: center; gap: 12px; text-decoration: none; }
        .pie-plataforma img { height: 34px; width: auto; padding: 5px 9px; border-radius: 9px; background: #fff; transition: box-shadow .2s ease; }
        .pie-plataforma:hover img { box-shadow: 0 0 0 2px var(--oro-claro); }
        .pie-plataforma:focus-visible { outline: 2px solid var(--oro-claro); outline-offset: 3px; border-radius: 9px; }

        @media (prefers-reduced-motion: reduce) {
            .entra, .portada-foto { animation: none !important; }
            *, *::before, *::after { transition: none !important; }
        }
    </style>
</head>
<body>

<header class="cabecera">
    <div class="contenedor cabecera-fila">
        <div class="marca">
            <?php if (dato($M, 'logo') !== ''): ?>
                <img class="marca-logo" src="<?= e(dato($M, 'logo')) ?>" alt="<?= e($alcaldia) ?>" width="900" height="463">
            <?php else: ?>
                <div class="lockup" role="img" aria-label="<?= e($alcaldia) ?>">
                    <img src="<?= e(dato($M, 'escudo')) ?>" alt="">
                    <span class="lockup-linea"></span>
                    <span class="lockup-texto"><span>Alcaldía de</span><strong><?= e($ciudad) ?></strong></span>
                </div>
            <?php endif; ?>
        </div>
        <div class="cabecera-derecha">
            <?php if (dato($M, 'dependencia') !== ''): ?>
                <p class="dependencia"><span><?= e(dato($M, 'secretaria')) ?></span><?= e(dato($M, 'dependencia')) ?></p>
            <?php endif; ?>
            <?php if (dato($M, 'acceso') !== ''): ?>
                <a class="boton-acceso" href="<?= e(dato($M, 'acceso')) ?>" target="_blank" rel="noopener">
                    <?= icono('acceso') ?><span class="largo">Acceso funcionarios</span><span class="corto">Funcionarios</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>

<section class="portada">
    <div class="portada-foto" aria-hidden="true"></div>
    <div class="contenedor portada-texto">
        <p class="ceja entra d1"><?= e(dato($M, 'secretaria') !== '' ? dato($M, 'secretaria') . ' · ' . $lugar : $lugar) ?></p>
        <h1 class="entra d2">Portal Tributario <em>de <?= e($ciudad) ?></em></h1>
        <p class="bajada entra d3">Consulta, liquida y presenta tus obligaciones tributarias con el municipio desde un solo lugar.</p>
        <?php if (isset($urls['ica']) || isset($urls['predial'])): ?>
        <div class="acciones entra d4">
            <?php if (isset($urls['ica'])): ?>
                <a class="boton boton-oro" href="<?= e($urls['ica']) ?>" target="_blank" rel="noopener">Declarar Industria y Comercio <?= icono('ir') ?></a>
            <?php endif; ?>
            <?php if (isset($urls['predial'])): ?>
                <a class="boton boton-vidrio" href="<?= e($urls['predial']) ?>" target="_blank" rel="noopener">Pagar el impuesto predial <?= icono('ir') ?></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if ($destacados): ?>
        <ul class="destacados entra d5" aria-label="Lo que puedes hacer aquí">
            <?php foreach ($destacados as $d): ?><li><?= icono('chequeo') ?><?= e($d) ?></li><?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
    <svg class="ola" viewBox="0 0 1440 140" preserveAspectRatio="none" aria-hidden="true">
        <path class="atras" d="M0 62 C 160 104, 330 112, 520 82 S 860 22, 1060 46 S 1330 104, 1440 76 V140 H0 Z"/>
        <path class="medio" d="M0 84 C 190 122, 400 122, 600 96 S 960 50, 1180 74 S 1380 112, 1440 102 V140 H0 Z"/>
        <path class="frente" d="M0 104 C 220 134, 450 132, 680 112 S 1060 82, 1260 98 S 1400 124, 1440 120 V140 H0 Z"/>
    </svg>
</section>

<main class="contenedor">
    <section class="tramites" aria-labelledby="titulo-tramites">
        <h2 id="titulo-tramites" class="oculto">Trámites en línea</h2>
        <ul class="tarjetas n<?= count($tarjetas) ?>">
            <?php $i = 3; foreach ($tarjetas as $t): $i++; ?>
                <li class="entra d<?= min($i, 6) ?>" style="--tono: <?= $t['tono'] ?>; --tono-rgb: <?= $t['tonoRgb'] ?>; --tono-suave: <?= $t['tonoSuave'] ?>; --tono-texto: <?= $t['tonoTexto'] ?>;">
                    <?php if ($t['pronto']): ?>
                        <div class="tarjeta pronto">
                            <span class="tarjeta-icono"><?= icono($t['clave']) ?></span>
                            <span class="tarjeta-titulo"><?= e($t['titulo']) ?></span>
                            <span class="tarjeta-texto">Próximamente podrás hacer este trámite en línea.</span>
                            <span class="tarjeta-ir">Próximamente</span>
                        </div>
                    <?php else: ?>
                        <a class="tarjeta" href="<?= e($t['url']) ?>" target="_blank" rel="noopener">
                            <?php if ($t['nuevo']): ?><span class="insignia"><?= icono('chispa') ?>Nuevo</span><?php endif; ?>
                            <span class="tarjeta-icono"><?= icono($t['clave']) ?></span>
                            <span class="tarjeta-titulo"><?= e($t['titulo']) ?></span>
                            <span class="tarjeta-texto"><?= e($t['texto']) ?></span>
                            <span class="tarjeta-ir">Ir al trámite <?= icono('ir') ?></span>
                        </a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <?php if (isset($urls['ica'])): ?>
    <section class="pasos" aria-labelledby="titulo-pasos">
        <div class="pasos-cabeza">
            <div>
                <p class="ceja">Industria y Comercio</p>
                <h2 id="titulo-pasos">Declara en <em>cuatro pasos</em></h2>
                <p>El trámite de ICA, retención y autorretención se hace en línea, desde la inscripción hasta la presentación.</p>
            </div>
            <a class="boton boton-oro" href="<?= e($urls['ica']) ?>" target="_blank" rel="noopener">Ir a Industria y Comercio <?= icono('ir') ?></a>
        </div>
        <ol class="pasos-lista">
            <li class="paso"><span class="paso-num">1</span><h3>Inscríbete</h3><p>Crea tu cuenta con tu NIT o cédula y completa el RIT del municipio.</p></li>
            <li class="paso"><span class="paso-num">2</span><h3>Declara</h3><p>Registra tus actividades e ingresos; el sistema liquida el impuesto.</p></li>
            <li class="paso"><span class="paso-num">3</span><h3>Firma</h3><p>Firma con el código que llega a tu correo. Si tienes contador o revisor fiscal, él también firma con su propio código.</p></li>
            <li class="paso"><span class="paso-num">4</span><h3>Presenta y paga</h3><p>Presenta la declaración y págala en los bancos autorizados, con su código de barras o con el recibo de pago.</p></li>
        </ol>
    </section>
    <?php endif; ?>

    <?php if ($hayContacto || $normas): ?>
    <div class="info<?= ($hayContacto && $normas) ? ' dos' : '' ?>">
        <?php if ($hayContacto): ?>
        <section aria-labelledby="titulo-atencion">
            <h2 id="titulo-atencion" class="seccion-titulo">Atención al contribuyente</h2>
            <ul class="contactos">
                <?php if (dato($M, 'telefono') !== ''): ?>
                <li><a class="contacto" href="tel:<?= e($telefonoEnlace) ?>">
                    <span class="contacto-icono"><?= icono('telefono') ?></span>
                    <span><span class="etiqueta">Teléfono</span><span class="valor cifras"><?= e(dato($M, 'telefono')) ?></span></span>
                </a></li>
                <?php endif; ?>
                <?php if (dato($M, 'correo') !== ''): ?>
                <li><a class="contacto" href="mailto:<?= e(dato($M, 'correo')) ?>">
                    <span class="contacto-icono"><?= icono('correo') ?></span>
                    <span><span class="etiqueta">Correo</span><span class="valor"><?= e(dato($M, 'correo')) ?></span></span>
                </a></li>
                <?php endif; ?>
                <?php if (dato($M, 'direccion') !== ''): ?>
                <li><div class="contacto">
                    <span class="contacto-icono"><?= icono('direccion') ?></span>
                    <span><span class="etiqueta">Oficina</span><span class="valor"><?= e(dato($M, 'direccion')) ?> · <?= e($lugar) ?></span></span>
                </div></li>
                <?php endif; ?>
            </ul>
        </section>
        <?php endif; ?>

        <?php if ($normas): ?>
        <section aria-labelledby="titulo-normatividad">
            <h2 id="titulo-normatividad" class="seccion-titulo">Normatividad</h2>
            <ul class="documentos">
                <?php foreach ($normas as $n): ?>
                <li><a class="documento" href="<?= e($n[2]) ?>" target="_blank" rel="noopener">
                    <span class="documento-icono"><?= icono('documento') ?></span>
                    <span class="documento-texto"><span class="valor"><?= e($n[0]) ?></span><span class="etiqueta"><?= e($n[1]) ?></span></span>
                    <span class="documento-tipo">PDF <?= icono('externo') ?></span>
                </a></li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</main>

<footer class="pie">
    <img class="pie-escudo" src="<?= e(dato($M, 'escudo')) ?>" alt="">
    <div class="contenedor">
        <div class="pie-columnas">
            <div class="pie-marca">
                <img src="<?= e(dato($M, 'escudo')) ?>" alt="">
                <div>
                    <strong><?= e($alcaldia) ?></strong>
                    <p><?= e(dato($M, 'secretaria')) ?><?= dato($M, 'dependencia') !== '' ? '<br>' . e(dato($M, 'dependencia')) : '' ?></p>
                </div>
            </div>
            <?php if ($urls): ?>
            <div>
                <h2>Trámites</h2>
                <ul>
                    <?php foreach ($tarjetas as $t): if ($t['pronto']) { continue; } ?>
                        <li><a class="pie-enlace" href="<?= e($t['url']) ?>" target="_blank" rel="noopener"><?= e($t['titulo']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            <?php if ($hayContacto): ?>
            <div>
                <h2>Contacto</h2>
                <ul>
                    <?php if (dato($M, 'telefono') !== ''): ?><li><a class="pie-enlace" href="tel:<?= e($telefonoEnlace) ?>"><?= e(dato($M, 'telefono')) ?></a></li><?php endif; ?>
                    <?php if (dato($M, 'correo') !== ''): ?><li><a class="pie-enlace" href="mailto:<?= e(dato($M, 'correo')) ?>"><?= e(dato($M, 'correo')) ?></a></li><?php endif; ?>
                    <?php if (dato($M, 'direccion') !== ''): ?><li><?= e(dato($M, 'direccion')) ?></li><?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
        <div class="pie-base">
            <span>© <?= date('Y') ?> <?= e($alcaldia) ?> · Portal Tributario</span>
            <a class="pie-plataforma" href="https://erpsoftsas.com" target="_blank" rel="noopener">
                <span>Plataforma desarrollada por</span>
                <img src="<?= e($logoErpsoft) ?>" alt="Sistemas ERPSoft S.A.S.">
            </a>
        </div>
    </div>
</footer>

</body>
</html>
