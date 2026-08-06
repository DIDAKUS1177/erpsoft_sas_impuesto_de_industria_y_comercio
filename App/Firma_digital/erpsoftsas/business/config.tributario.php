<?php
// ==========================================
// PARAMETROS TRIBUTARIOS (valores que cambian cada año)
// ==========================================
//
// Estos valores los fija la DIAN o la ley cada año gravable. Alguien tiene
// que revisarlos y actualizarlos en enero -no son un dato que el codigo
// pueda calcular solo-. Viven en un archivo aparte de config.municipio.php
// a proposito: ese es de branding del municipio, este es de ley nacional
// (aplica igual sin importar el municipio), y ademas config.municipio.php
// vive un nivel arriba de esta carpeta -fuera del mount del contenedor del
// 8081- mientras que este archivo esta DENTRO de erpsoftsas/, alcanzable
// desde cualquier despliegue.

if (!defined('UVT_ANIO')) define('UVT_ANIO', 2026);

// Valor de la UVT para el año de arriba. Resolución DIAN 000238 de 2025:
// UVT 2026 = $52.374. Fuente: https://incp.org.co/publicaciones/infoincp-publicaciones/impuestos/2025/12/dian-fijo-en-52-374-en-valor-de-la-uvt-para-el-ano-gravable-2026/
if (!defined('UVT_VALOR')) define('UVT_VALOR', 52374);

/*
 * Obligatoriedad de contador / revisor fiscal para presentar la
 * declaración de ICA (confirmado con la Secretaría de Hacienda,
 * 2026-08-05):
 *
 *   - Persona NATURAL: contador obligatorio solo si sus ingresos superan
 *     3.500 UVT.
 *   - Persona JURÍDICA: contador SIEMPRE obligatorio.
 *   - Persona JURÍDICA que además supere ~3.000 millones de ingresos:
 *     también requiere revisor fiscal (Art. 13 Ley 43 de 1990/Código de
 *     Comercio). En este sistema contador y revisor comparten una sola
 *     casilla de firma, así que basta con que UNA de las dos personas
 *     firme para presentar.
 */
if (!defined('UMBRAL_INGRESOS_CONTADOR_NATURAL_UVT')) {
    define('UMBRAL_INGRESOS_CONTADOR_NATURAL_UVT', 3500);
}

// Aproximado dado por el cliente ("como 3000 millones"). No hay una cifra
// exacta en UVT para este umbral en la conversación; si la Secretaría de
// Hacienda da un valor más preciso, se ajusta aquí.
if (!defined('UMBRAL_INGRESOS_REVISOR_FISCAL')) {
    define('UMBRAL_INGRESOS_REVISOR_FISCAL', 3000000000);
}
