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

// OJO (2026-08-11): hoy NINGUNA regla del sistema consume estas dos
// constantes. La única que las usaba era el umbral de 3.500 UVT para exigir
// contador, y esa regla murió (ver nota abajo). Se conservan porque la UVT
// es un parámetro tributario de uso general y es probable que vuelva a
// hacer falta, pero mientras tanto actualizarlas en enero NO cambia el
// comportamiento de nada. Antes de confiar en ellas, verificar quién las
// lee realmente.

if (!defined('UVT_ANIO')) define('UVT_ANIO', 2026);

// Valor de la UVT para el año de arriba. Resolución DIAN 000238 de 2025:
// UVT 2026 = $52.374. Fuente: https://incp.org.co/publicaciones/infoincp-publicaciones/impuestos/2025/12/dian-fijo-en-52-374-en-valor-de-la-uvt-para-el-ano-gravable-2026/
if (!defined('UVT_VALOR')) define('UVT_VALOR', 52374);

/*
 * Obligatoriedad de contador / revisor fiscal para presentar la
 * declaración de ICA.
 *
 * 2026-08-11: el cliente reemplazó la regla anterior (persona jurídica
 * siempre, persona natural solo por encima de 3.500 UVT) por una más
 * simple: si el contribuyente tiene registrado un correo de contador O de
 * revisor fiscal (ind_EmailContador / ind_EmailRevisor en
 * ind_contribuyentes), la firma de esa persona pasa a ser obligatoria para
 * presentar -sin importar el tipo de persona ni el monto de ingresos-. Ver
 * _requiereContador() en business/controller/class.declaracionesICA.php,
 * que es donde vive la regla real. Los umbrales en UVT que existían aquí
 * (UMBRAL_INGRESOS_CONTADOR_NATURAL_UVT / UMBRAL_INGRESOS_REVISOR_FISCAL) se
 * quitaron por quedar sin uso.
 */
