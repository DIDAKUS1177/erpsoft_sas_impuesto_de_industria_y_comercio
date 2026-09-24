<?php
/*
 * ============================================================================
 * UTILIDADES COMPARTIDAS DE LOS PDF DE RETEICA Y AUTORRETEICA
 * ============================================================================
 *
 * Los dos formularios comparten encabezado, bloque de firmas, marca de agua,
 * rotulos verticales y bloque de codigo de barras. Lo que cambia entre ellos
 * son las secciones del medio.
 *
 * Vive aparte porque declaracion.php y liquidacion.php -los dos generadores del
 * ICA- son casi iguales y esa duplicacion ya costo varios arreglos hechos en un
 * archivo y olvidados en el otro. Esta escrito en CLAUDE.md; no se repite aqui.
 *
 * LAS CINCO TRAMPAS DE TCPDF QUE ESTE ARCHIVO YA TIENE RESUELTAS
 *
 *   1. SetAutoPageBreak(false): TCPDF NO avisa cuando el contenido se sale del
 *      papel, simplemente lo dibuja fuera. Hay que medir.
 *   2. GetY() tras writeHTML() NO es el borde de la tabla: queda ~3mm mas
 *      abajo. Ver DESFASE_GETY_RET.
 *   3. La marca de agua se lleva la fuente por delante si no se guarda y
 *      restaura a mano, y colgaba el worker si se dibujaba justo tras AddPage.
 *   4. Un PNG con canal alfa hace que TCPDF escriba archivos temporales, y el
 *      PHP-FPM de Plesk no puede: "Unable to write file". Todo PNG que se
 *      imprima tiene que ser color type 2, no 6.
 *   5. El codigo de barras va con write1DBarcode() -vectorial-, nunca como
 *      <img src="@base64">, por lo mismo del punto 4.
 * ============================================================================
 */

require_once __DIR__ . '/tcpdf/tcpdf.php';
require_once __DIR__ . '/tcpdf/tcpdf_barcodes_1d.php';

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.conexionSqlServer.php';
include_once SERVER . '/business/class.codigoBarrasRecaudo.php';

/*
 * La configuracion del municipio. Ubicacion real (Plesk/produccion): un nivel
 * arriba de /erpsoftsas. El fallback de adentro es solo para el contenedor
 * local, que monta unicamente esa carpeta. Mismo mecanismo que el resto del
 * sistema; ver el "gotcha critico" de CLAUDE.md.
 */
$configPath = dirname(dirname(__DIR__)) . '/config.municipio.php';
if (!file_exists($configPath)) {
    $configPath = dirname(__DIR__) . '/config.municipio.php';
}
if (file_exists($configPath)) {
    require_once $configPath;
}

if (!defined('MUNICIPIO_NOMBRE'))       define('MUNICIPIO_NOMBRE', 'Alcaldía');
if (!defined('MUNICIPIO_DEPARTAMENTO')) define('MUNICIPIO_DEPARTAMENTO', 'Boyacá');
if (!defined('MUNICIPIO_SELLO_FIRMA'))  define('MUNICIPIO_SELLO_FIRMA', 'Sello_Firma.png');
if (!defined('MUNICIPIO_LOGO'))         define('MUNICIPIO_LOGO', '/erpsoftsas/vendors/images/escudo-paipa.png');

/*
 * El NIT de la entidad, que va bajo el escudo.
 *
 * Estaba escrito a mano -copiado de declaracion.php, donde sigue asi-. En un
 * sistema pensado para varios municipios eso significa que el formulario de
 * OTRA alcaldia saldria con el NIT de Paipa impreso: un documento tributario
 * con el identificador equivocado de la entidad que lo emite.
 *
 * El valor por defecto es el de Paipa porque es el despliegue original; cada
 * municipio lo define en su config.municipio.php, como el nombre y el escudo.
 */
if (!defined('MUNICIPIO_NIT'))          define('MUNICIPIO_NIT', '891.801.240-1');
/* Para la línea "MUNICIPIO DE X - DEPTO" del encabezado. El config de cada
   municipio ya la define (la usan declaracion.php y el RIT); esto solo evita un
   fatal si alguna instalación no la trae. */
if (!defined('MUNICIPIO_CIUDAD'))       define('MUNICIPIO_CIUDAD', 'Paipa');

/* Ver la trampa 2 de la cabecera. Es el mismo valor que usa declaracion.php. */
const DESFASE_GETY_RET = 3;

/* Carta. El formulario de ICA usa oficio porque tiene 38 casillas; estos dos
   son mucho mas cortos y caben de sobra -medido: cierran en ~200mm de 279.4-.
   Imprimir un formulario de media hoja sobre oficio desperdicia papel. */
const RET_ANCHO_PAGINA = 215.9;
const RET_ALTO_PAGINA  = 279.4;


class RetencionPdf extends TCPDF
{
    public function Header() {}
    public function Footer() {}
}


/**
 * El escudo que se imprime, garantizando que NO lleve canal alfa.
 *
 * escudo-paipa.png es color type 6 (RGB+alfa) porque la web lo usa sobre fondos
 * de color; imprimirlo revienta en Plesk con "Unable to write file". Para eso
 * existe escudo-paipa-pdf.png, aplanado contra blanco.
 *
 * Si el municipio define MUNICIPIO_LOGO_PDF, manda esa. Si no, se busca la
 * variante "-pdf" del logo normal y solo se usa si existe de verdad; en ultimo
 * caso se cae al logo normal, que es lo que habia antes.
 */
function pdfret_rutaEscudo()
{
    $base = dirname(dirname(__DIR__));

    if (defined('MUNICIPIO_LOGO_PDF')) {
        return $base . MUNICIPIO_LOGO_PDF;
    }

    $variante = preg_replace('/\.png$/i', '-pdf.png', MUNICIPIO_LOGO);
    if ($variante !== MUNICIPIO_LOGO && file_exists($base . $variante)) {
        return $base . $variante;
    }

    return $base . MUNICIPIO_LOGO;
}


/**
 * Marca de agua diagonal ("BORRADOR" / "PRESENTADA" / "PAGADA").
 *
 * SE LLAMA AL FINAL del documento, no despues del AddPage. Llamarla al
 * principio cuelga el worker de PHP-FPM al 99% de CPU indefinidamente
 * -reproducido en el ICA-, aparentemente por como interactua Rotate() con el
 * manejo interno de saltos de pagina. Queda dibujada encima del contenido en
 * vez de debajo; con alpha 0.15 se ve igual.
 */
function pdfret_marcaDeAgua($pdf, $texto)
{
    /* StartTransform()/StopTransform() restauran la rotacion y el alpha, pero
       NO la fuente. Sin guardarla a mano, este SetFont de 70pt se queda pegado
       en todo el resto del documento. */
    $familia = $pdf->getFontFamily();
    $estilo  = $pdf->getFontStyle();
    $tamano  = $pdf->getFontSizePt();

    $cx = RET_ANCHO_PAGINA / 2;
    $cy = RET_ALTO_PAGINA / 2;

    $pdf->SetFont('helvetica', 'B', 60);
    $ancho = $pdf->GetStringWidth($texto);

    /* En TODAS las hojas: desde que firmas y código de barras pueden pasar a una
       segunda hoja (ver pdfret_saltoSiNoCabe), pintarla solo en la actual dejaba
       la primera sin "BORRADOR" -justo la que tiene las cifras-. */
    $ultima = $pdf->getPage();
    for ($p = 1; $p <= $pdf->getNumPages(); $p++) {
        $pdf->setPage($p);
        /* SetFont OTRA VEZ por hoja: TCPDF escribe el tamaño ("Tf") solo en el
           flujo de la hoja donde está el cursor. Sin esto, en la hoja 1 quedaba
           la letra de 6.5px de las tablas y la marca salía diminuta. */
        $pdf->SetFont('helvetica', 'B', 60);
        $pdf->StartTransform();
        $pdf->SetAlpha(0.15);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->Rotate(45, $cx, $cy);
        /* Text() con un solo punto de anclaje. Con Cell() el rectangulo queda mal
           ubicado tras la rotacion: solo se pinta una esquina. */
        $pdf->Text($cx - ($ancho / 2), $cy, $texto);
        $pdf->StopTransform();
        $pdf->SetAlpha(1);
        $pdf->SetTextColor(0, 0, 0);
    }
    $pdf->setPage($ultima);
    $pdf->SetFont($familia, $estilo, $tamano);
}


/**
 * El rotulo lateral de cada seccion (A, B, C...), rotado 90 grados y centrado
 * dentro de su banda.
 *
 * DOS COSAS QUE NO SON OBVIAS Y QUE COSTARON UNA VUELTA
 *
 * 1. MultiCell centra el texto dentro de una caja de ancho fijo que arranca en
 *    el pivote y crece hacia arriba, asi que el centro visual queda en
 *    (pivote - ancho/2): el pivote tiene que ser el centro de la banda MAS la
 *    mitad de la caja.
 *
 * 2. Los renglones de esa caja se apilan HACIA LA DERECHA de la pagina, no
 *    hacia abajo. La columna gris mide 5% del ancho util -unos 9.8mm-, asi que
 *    un rotulo que se parta en tres renglones se sale de la columna y tapa la
 *    primera casilla de la tabla. Paso con "A. AGENTE RETENEDOR": el numero 2
 *    de la seccion quedaba ilegible debajo del texto rotado.
 *
 * Por eso el tamaño de letra BAJA hasta que el rotulo quepa en dos renglones
 * como maximo. Es preferible una etiqueta pequeña a una que invade la tabla.
 */
function pdfret_textoVertical($pdf, $texto, $x, $yTop, $yBottom, $anchoColumna = 9.8)
{
    $altoBanda = $yBottom - $yTop;
    $anchoCaja = max(10, $altoBanda - 2);
    $altoLinea = 3.2;

    /*
     * Se mide con getNumLines(), NO con GetStringWidth() dividido entre el
     * ancho. GetStringWidth ignora que MultiCell parte por PALABRAS: con el,
     * "A. CONTRIBUYENTE" daba 2 renglones calculados y salian 3 reales
     * -"A." / "CONTRIBUYENT" / "E"-, que es justo lo que se queria evitar.
     */
    $palabras = preg_split('/\s+/', $texto);

    $tam    = 7.0;
    $lineas = 3;
    while ($tam >= 4.5) {
        $pdf->SetFont('helvetica', 'B', $tam);

        $lineas = max(1, (int) $pdf->getNumLines($texto, $anchoCaja));

        /*
         * No basta con contar renglones: hay que exigir que la palabra mas
         * larga quepa ENTERA. Con solo el conteo, "A. CONTRIBUYENTE" salia en
         * dos renglones -que es lo pedido- pero partido como
         * "A. CONTRIBUYEN" / "TE", porque MultiCell corta a mitad de palabra
         * cuando ninguna otra cosa le cabe.
         */
        $cabenLasPalabras = true;
        foreach ($palabras as $palabra) {
            if ($pdf->GetStringWidth($palabra) > $anchoCaja) {
                $cabenLasPalabras = false;
                break;
            }
        }

        if ($lineas <= 2 && $cabenLasPalabras && ($lineas * $altoLinea) <= $anchoColumna) {
            break;
        }
        $tam -= 0.5;
    }

    // El bloque de renglones se centra dentro de la columna gris.
    $xCentrado = $x + max(0, ($anchoColumna - ($lineas * $altoLinea)) / 2);
    $pivote    = $yTop + ($altoBanda / 2) + ($anchoCaja / 2);

    $pdf->StartTransform();
    $pdf->Rotate(90, $xCentrado, $pivote);
    $pdf->SetXY($xCentrado, $pivote);
    $pdf->MultiCell($anchoCaja, $altoLinea, $texto, 0, 'C');
    $pdf->StopTransform();

    $pdf->SetFont('helvetica', '', 7);
}


/**
 * El PDF ya configurado. Todo lo de aqui es deliberado; ver la cabecera.
 */
function pdfret_nuevoPdf()
{
    $pdf = new RetencionPdf('P', 'mm', array(RET_ANCHO_PAGINA, RET_ALTO_PAGINA), true, 'UTF-8', false);
    $pdf->SetMargins(10, 8, 10);
    $pdf->SetAutoPageBreak(false, 0);
    $pdf->SetPrintHeader(false);
    $pdf->SetPrintFooter(false);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 7);
    return $pdf;
}


/** Los estilos y el encabezado con escudo, comunes a los dos formularios. */
function pdfret_encabezado($titulo, $subtitulo)
{
    /* La dependencia y "MUNICIPIO DE X - DEPTO" vienen del formulario oficial en
       papel y faltaban aquí (retro cliente 2026-09-23: "le falta texto de lo que
       decía el formato original"). Mismas constantes y mismos valores por
       defecto que ya usa el RIT (ritActualizado.php), para que otro municipio
       ponga los suyos en su config sin tocar este archivo. */
    $secretaria  = defined('MUNICIPIO_SECRETARIA') ? MUNICIPIO_SECRETARIA : 'SECRETARÍA DE HACIENDA';
    $dependencia = defined('MUNICIPIO_DEPENDENCIA_TRIBUTARIA')
        ? MUNICIPIO_DEPENDENCIA_TRIBUTARIA
        : 'DIRECCIÓN DE IMPUESTOS, RENTAS Y JURISDICCIÓN COACTIVA';
    $municipio   = 'MUNICIPIO DE ' . mb_strtoupper(MUNICIPIO_CIUDAD, 'UTF-8')
                 . ' - ' . mb_strtoupper(MUNICIPIO_DEPARTAMENTO, 'UTF-8');

    /* TODAS las líneas del mismo tamaño (11, negrita), como el encabezado del ICA
       (declaracion.php). Antes el título iba a 11 y las demás a 8, y el cliente
       lo notó al compararlos (retro 2026-09-24: "el título debería ser igual, el
       tamaño de letra"). A 11 "SECRETARÍA - DIRECCIÓN…" ya no cabe en un renglón
       y TCPDF dejaba "COACTIVA" sola abajo: por eso van en dos líneas. */
    $lineas = [
        mb_strtoupper(MUNICIPIO_NOMBRE, 'UTF-8'),
        $secretaria,
        $dependencia,
        $titulo,
        $subtitulo,
        $municipio,
    ];
    $lineas = array_map('htmlspecialchars', array_filter($lineas, 'strlen'));

    return '
<style>
    td { vertical-align: top; font-size: 6.5px; }
    .tituloPrincipal { font-size: 11px; font-weight: bold; }
</style>

<table border="0" cellpadding="2" width="100%">
<tr>
    <td width="12%" rowspan="2" align="center">
        <img src="' . pdfret_rutaEscudo() . '" width="52">
        <div style="font-size:5px; text-align:center;">NIT ' . htmlspecialchars(MUNICIPIO_NIT) . '</div>
    </td>
    <td class="tituloPrincipal" width="88%" align="center">' . implode('<br>', $lineas) . '</td>
</tr>
<tr><td height="6"></td></tr>
</table>
';
}


/**
 * El bloque de firmas.
 *
 * Lo que se estampa NO es una firma manuscrita: es el sello del municipio con
 * el nombre de quien firmo y la fecha. La firma es electronica y su prueba esta
 * en firmas_declaraciones, con el codigo OTP que se consumio al registrarla.
 *
 * El sello va a 90px (~23.8mm) y el nombre/fecha a 7px. El cliente pidio agrandarlo
 * varias veces (16→24 el 2026-09-14; 24→30 el 2026-09-15; 30→52 el 2026-09-17; 52→90
 * el 2026-09-21, que seguia "muy chiquito") y que lo compensara la letra. Estos
 * formularios son CARTA y cierran holgados -medido con ?medir=1 sobre una PRESENTADA:
 * ~197mm (reteica) y ~231mm (autorreteica) de 279.4-, asi que a 90px (la fila de firma
 * crece ~10mm) sigue cabiendo de sobra. El ICA NO puede: es oficio casi lleno y su
 * sello se queda en 20 (ver declaracion.php). No subir mucho mas sin volver a medir
 * (el sello solo ocupa alto cuando hay firma).
 */
function pdfret_firmas($firmaDeclarante, $firmaContador, $fechaSello, $nombreDeclarante, $datosContador)
{
    $html = '
<table border="1" cellpadding="2" width="100%">
<tr>
    <td width="5%" rowspan="3" bgcolor="#e1dada"></td>
    <td width="47%"><b>FIRMA DEL DECLARANTE</b><br>';

    if ($firmaDeclarante) {
        /* Sobre el sello va el REPRESENTANTE LEGAL (para jurídica) / el declarante
           mismo (natural), no el nombre de la cuenta que firmó por OTP -razón social-
           (retro cliente 2026-09-17). Es el mismo nombre que la casilla NOMBRE. */
        $html .= '<div align="center"><img src="' . MUNICIPIO_SELLO_FIRMA . '" width="90" height="90"><br>'
               . '<span style="font-size:7px;">'
               . htmlspecialchars($nombreDeclarante)
               . '<br>' . $fechaSello . '</span></div>';
    } else {
        $html .= '<br><br>';
    }

    $html .= '
    </td>
    <td width="48%"><b>FIRMA DEL CONTADOR O REVISOR FISCAL</b><br>';

    /* Contador y revisor comparten una sola casilla: el contribuyente tiene uno
       O el otro, no los dos firmando a la vez. Mismo criterio que el ICA. */
    if ($firmaContador) {
        $html .= '<div align="center"><img src="' . MUNICIPIO_SELLO_FIRMA . '" width="90" height="90"><br>'
               . '<span style="font-size:7px;">'
               . htmlspecialchars($firmaContador['fd_NombreUsuario'])
               . '<br>' . $fechaSello . '</span></div>';
    } else {
        $html .= '<br><br>';
    }

    $html .= '
    </td>
</tr>
<tr>
    <td><b>NOMBRE:</b> ' . htmlspecialchars($nombreDeclarante) . '</td>
    <td><b>NOMBRE:</b> ' . htmlspecialchars($datosContador['nombre']) . '</td>
</tr>
<tr>
    <td><b>C.C. / NIT:</b> ' . htmlspecialchars($datosContador['doc_declarante']) . '</td>
    <td><b>C.C.:</b> ' . htmlspecialchars($datosContador['cedula'])
      . ' &nbsp;&nbsp; <b>T.P.:</b> ' . htmlspecialchars($datosContador['tarjeta']) . '</td>
</tr>
</table>
';

    return $html;
}


/* Lo que el bloque de código de barras baja por debajo del GetY() con que
   arranca: rótulo (4.5) + código (21.0) - el desfase con que empieza (ver
   pdfret_bloqueBarras). */
const RET_ALTO_BARRAS = 22.5;

/**
 * Pasa firmas + código de barras a una hoja nueva si no caben en la actual.
 * Devuelve true si saltó de hoja.
 *
 * SetAutoPageBreak está en false (trampa 1), así que sin esto lo que no cabe se
 * dibuja por debajo del borde y NO se ve: un contribuyente con varias
 * actividades se quedaba sin firmas ni código de barras en el papel. Medido el
 * 2026-09-23: una autorretención con las 6 actividades del RIT más cargado de
 * la base cerraba en 300.7mm de 279.4. Firmas y código van siempre JUNTOS:
 * separarlos dejaría un código de barras huérfano en otra hoja.
 *
 * El alto de las firmas se MIDE -se pintan dentro de una transacción de TCPDF y
 * se deshacen- en vez de suponerlo: el sello ya cambió de tamaño cinco veces.
 */
function pdfret_saltoSiNoCabe($pdf, $htmlFirmas)
{
    $y0 = $pdf->GetY();

    $pdf->startTransaction();
    $pdf->writeHTML($htmlFirmas, true, false, true, false, '');
    $altoFirmas = $pdf->GetY() - $y0;
    $pdf->rollbackTransaction(true);

    // 5mm de margen inferior: la impresora no llega al borde exacto del papel.
    if ($y0 + $altoFirmas + RET_ALTO_BARRAS <= RET_ALTO_PAGINA - 5) {
        return false;
    }

    $pdf->AddPage();
    return true;
}


/**
 * El bloque de codigo de barras, dibujado a mano.
 *
 * No va como tabla HTML por dos motivos, los dos aprendidos a golpes en el ICA:
 * una imagen embebida obliga a TCPDF a escribir un temporal que Plesk no puede
 * escribir, y anclar el bloque a GetY() lo deja colgando fuera del recuadro
 * porque GetY() no es el borde de la tabla anterior.
 *
 * El codigo escaneable SOLO se dibuja si la declaracion esta presentada: un
 * codigo con apariencia de pagable sobre un borrador cuyo valor todavia puede
 * cambiar es peor que no tener codigo.
 *
 * Devuelve la Y del borde inferior del bloque.
 */
function pdfret_bloqueBarras($pdf, $referencia, $valor, $estaPresentada)
{
    $margenes  = $pdf->getMargins();
    $anchoUtil = $pdf->getPageWidth() - $margenes['left'] - $margenes['right'];
    $mitad     = $anchoUtil / 2;
    $x         = $margenes['left'];
    $y         = $pdf->GetY() - DESFASE_GETY_RET;

    $altoRotulo = 4.5;
    $altoCodigo = 21.0;

    $contenido = \erpsoftsas\CodigoBarrasRecaudo::construir(
        $referencia,
        $valor,
        \erpsoftsas\CodigoBarrasRecaudo::fechaVigencia()
    );
    if ($contenido === null) { $contenido = $referencia; }

    $pdf->SetXY($x, $y);
    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->Cell($mitad, $altoRotulo, 'CÓDIGO DE BARRAS', 1, 0, 'L');
    $pdf->Cell($mitad, $altoRotulo, 'REFERENCIA DE RECAUDO FORMULARIO No.', 1, 1, 'L');

    $pdf->SetXY($x, $y + $altoRotulo);
    $pdf->Cell($mitad, $altoCodigo, '', 1, 0, 'C');

    /*
     * La referencia va CENTRADA y a 11pt, no pegada a la izquierda en 7pt.
     *
     * Dos motivos. Estetico: la celda mide media hoja de ancho y el numero
     * arrinconado contra el borde deja un hueco raro frente al codigo de
     * barras, que si esta centrado en la suya. Y practico: este es el numero
     * que el cajero DIGITA A MANO cuando el escaner falla, asi que cuanto mas
     * grande y mas facil de encontrar en la hoja, mejor.
     *
     * Cell() ya centra verticalmente por si solo, asi que basta con la 'C'.
     */
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell($mitad, $altoCodigo, $referencia, 1, 1, 'C');
    $pdf->SetFont('helvetica', '', 7);

    $anchoBarcode = $mitad - 14;
    $altoBarcode  = 16;
    $yBarcode     = $y + $altoRotulo + 1.2;

    /*
     * El codigo escaneable exige DOS condiciones, no una.
     *
     * Que este presentada -un codigo con pinta de pagable sobre un borrador
     * cuyo valor todavia puede cambiar es peor que no tener codigo- y que haya
     * algo que pagar. Un GS1-128 con valor 0 el cajero no lo puede cobrar, y
     * en autorretencion es hoy el caso normal: mientras las formulas de la
     * liquidacion sigan sin confirmar, la casilla 23 sale en cero.
     */
    if ($estaPresentada && $valor > 0) {
        $pdf->write1DBarcode(
            $contenido, 'C128',
            $x + ($mitad - $anchoBarcode) / 2, $yBarcode,
            $anchoBarcode, $altoBarcode, '',
            array('position' => '', 'border' => false, 'padding' => 0,
                  'fgcolor' => array(0, 0, 0), 'bgcolor' => false,
                  'text' => false, 'stretch' => true),
            'N'
        );

        /* La linea legible NO es decorativa: si el escaner del banco falla, el
           cajero digita ese numero a mano. En GS1-128 los FNC1 no son
           imprimibles, asi que no sirve el 'text' => true de TCPDF. */
        $pdf->SetFont('helvetica', '', 5);
        $pdf->SetXY($x, $yBarcode + $altoBarcode + 0.3);
        $pdf->Cell($mitad, 2.6,
            \erpsoftsas\CodigoBarrasRecaudo::textoLegible(
                $referencia, $valor, \erpsoftsas\CodigoBarrasRecaudo::fechaVigencia()
            ),
            0, 0, 'C');
    } else {
        $pdf->SetFont('helvetica', 'I', 6);
        $pdf->SetXY($x, $yBarcode + ($altoBarcode / 2) - 2);
        $pdf->MultiCell(
            $mitad, 4,
            $estaPresentada
                ? "Sin valor a pagar en este período"
                : "Disponible al presentar\nla declaración",
            0, 'C'
        );
    }

    return $y + $altoRotulo + $altoCodigo;
}


/* ===========================================================================
   DATOS
   =========================================================================== */

/**
 * Sesion y permiso.
 *
 * El generador del ICA NO comprobaba nada hasta el 2026-09-01: un curl sin
 * ninguna cookie descargaba la declaracion completa de cualquier contribuyente
 * -NIT, ingresos, impuesto- cambiando un entero en la URL. Son datos con
 * reserva tributaria. Estos dos nacen con la guarda puesta.
 *
 * Devuelve la fila, o corta la ejecucion con el codigo HTTP que corresponda.
 */
function pdfret_filaAutorizada($con, $tabla, $prefijo, $id)
{
    if (session_status() === PHP_SESSION_NONE) { @session_start(); }

    if (empty($_SESSION['id_usuario'])) {
        http_response_code(401);
        exit('Debe iniciar sesión para descargar este documento.');
    }

    $id = (int) $id;
    if ($id <= 0) {
        http_response_code(400);
        exit('Declaración no válida.');
    }

    $sql    = "SELECT * FROM {$tabla} WHERE {$prefijo}Id = ?";
    $params = [$id];

    $rol = isset($_SESSION['id_Rol']) ? (int) $_SESSION['id_Rol'] : 0;

    /* Los roles de Alcaldia (1 y 2) ven cualquiera; el resto solo lo suyo.
       No hay columna que ate usuario y contribuyente: se cruzan por numero de
       documento, igual que en todo el sistema. */
    if (!in_array($rol, [1, 2], true)) {
        $sql .= " AND {$prefijo}IdContribuyente IN (
                      SELECT c.ind_Id FROM ind_contribuyentes c
                      INNER JOIN conf_usuarios u
                              ON u.usu_NumeroDocumento = c.ind_NumeroIdentificacion
                       WHERE u.usu_Id = ?)";
        $params[] = (int) $_SESSION['id_usuario'];
    }

    $fila = $con->obnerFila($con->consultar($sql, $params));

    if (!$fila) {
        /* 404 y no 403 a proposito: un 403 confirmaria que la declaracion
           existe y es de otro, que ya es informacion. */
        http_response_code(404);
        exit('Declaración no encontrada.');
    }

    return $fila;
}


/**
 * El nombre del contribuyente.
 *
 * ind_contribuyentes NO tiene columna de razon social: el nombre vive en los
 * cuatro campos de persona natural y, en una persona juridica, la razon social
 * esta en ind_PrimerNombre. Es la misma trampa que dejaba el nombre en blanco
 * en el RIT de las juridicas.
 */
function pdfret_nombreContribuyente($c)
{
    return trim(implode(' ', array_filter([
        isset($c['ind_PrimerNombre'])    ? trim((string) $c['ind_PrimerNombre'])    : '',
        isset($c['ind_SegundoNombre'])   ? trim((string) $c['ind_SegundoNombre'])   : '',
        isset($c['ind_PrimerApellido'])  ? trim((string) $c['ind_PrimerApellido'])  : '',
        isset($c['ind_SegundoApellido']) ? trim((string) $c['ind_SegundoApellido']) : '',
    ])));
}


/**
 * Quien firma como DECLARANTE.
 *
 * Una persona JURIDICA no firma: firma su REPRESENTANTE LEGAL. El cliente pidio
 * (2026-09-14) que en el bloque de firmas de una juridica aparezca el nombre y
 * la cedula del representante legal (ind_Nombre_representante /
 * ind_Cedula_representante), no la razon social con el NIT. En persona natural
 * el declarante es el propio contribuyente. Si es juridica pero no hay
 * representante registrado, se cae a la razon social para no dejar el nombre en
 * blanco -mejor el dato que hay que ninguno-.
 *
 * Devuelve ['nombre' => ..., 'documento' => ...].
 */
function pdfret_firmanteDeclarante($c)
{
    if ((int) ($c['ind_Persona'] ?? 0) === 2) {
        $nombre = trim((string) ($c['ind_Nombre_representante'] ?? ''));
        $doc    = trim((string) ($c['ind_Cedula_representante'] ?? ''));
        if ($nombre !== '') {
            return ['nombre' => $nombre, 'documento' => $doc];
        }
    }
    return [
        'nombre'    => pdfret_nombreContribuyente($c),
        'documento' => (string) ($c['ind_NumeroIdentificacion'] ?? ''),
    ];
}


/** Las firmas de ESTE formulario. El modulo no es opcional: los tres reparten
 *  numeros de series distintas y 2026000001 existe en los tres a la vez. */
function pdfret_firmasDe($con, $numero, $modulo)
{
    $sql = "SELECT fd_NombreUsuario, fd_EmailUsuario, fd_FechaHora
              FROM firmas_declaraciones
             WHERE fd_NumeroDeclaracion = ? AND fd_Rol = ? AND fd_Modulo = ?";

    return [
        'declarante' => $con->obnerFila($con->consultar($sql, [(string) $numero, 'declarante', $modulo])),
        'contador'   => $con->obnerFila($con->consultar($sql, [(string) $numero, 'contador',   $modulo])),
    ];
}


/**
 * Fecha impresa dentro del sello.
 *
 * El sello acredita la PRESENTACION ante el municipio, no el instante de la
 * firma; por eso manda la fecha de presentacion. Mientras la declaracion este
 * firmada pero sin presentar todavia no existe esa fecha, y se usa la de la
 * firma para no dejar el sello mudo.
 */
function pdfret_fechaSello($fechaPresentacion, $firma)
{
    foreach ([$fechaPresentacion, isset($firma['fd_FechaHora']) ? $firma['fd_FechaHora'] : null] as $v) {
        if ($v instanceof DateTime) { return $v->format('d/m/Y H:i:s'); }
        if (is_string($v) && trim($v) !== '') {
            $ts = strtotime($v);
            if ($ts) { return date('d/m/Y H:i:s', $ts); }
        }
    }
    return '';
}


/** Cifra en formato colombiano, sin decimales: es como se declara. */
function pdfret_pesos($v)
{
    return number_format((float) $v, 0, ',', '.');
}


/** La tarifa se guarda en FRACCION (0.004) y el formulario la imprime POR MIL
 *  (4.0). La conversion ocurre aqui y en ningun otro sitio: confundir las dos
 *  unidades no da error, da una cifra mil veces mayor o menor. */
function pdfret_porMil($tarifa)
{
    return number_format(((float) $tarifa) * 1000, 1, ',', '.');
}


/* ===========================================================================
   TOTAL EN LETRAS

   El formulario completo que pidio el cliente (2026-09-14) trae el total a
   pagar escrito con palabras. Se calcula aqui, en el generador, y no se guarda
   en la base: es una representacion del numero, no un dato aparte que pueda
   quedar desincronizado.
   =========================================================================== */

/** 0..999 en palabras (MAYUSCULAS). "CIEN" exacto; 100+ es "CIENTO ...". */
function pdfret_letrasCentena($n)
{
    $unidades = [0 => '', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE',
        'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE',
        'VEINTE', 'VEINTIUNO', 'VEINTIDÓS', 'VEINTITRÉS', 'VEINTICUATRO', 'VEINTICINCO', 'VEINTISÉIS',
        'VEINTISIETE', 'VEINTIOCHO', 'VEINTINUEVE'];
    $decenas  = [3 => 'TREINTA', 4 => 'CUARENTA', 5 => 'CINCUENTA', 6 => 'SESENTA',
                 7 => 'SETENTA', 8 => 'OCHENTA', 9 => 'NOVENTA'];
    $centenas = [1 => 'CIENTO', 2 => 'DOSCIENTOS', 3 => 'TRESCIENTOS', 4 => 'CUATROCIENTOS',
                 5 => 'QUINIENTOS', 6 => 'SEISCIENTOS', 7 => 'SETECIENTOS', 8 => 'OCHOCIENTOS', 9 => 'NOVECIENTOS'];

    $n = (int) $n;
    if ($n === 0)   { return ''; }
    if ($n === 100) { return 'CIEN'; }

    $c     = intdiv($n, 100);
    $resto = $n % 100;
    $out   = $c ? $centenas[$c] : '';

    if ($resto) {
        if ($out !== '') { $out .= ' '; }
        if ($resto <= 29) {
            $out .= $unidades[$resto];
        } else {
            $d = intdiv($resto, 10);
            $u = $resto % 10;
            $out .= $decenas[$d] . ($u ? ' Y ' . $unidades[$u] : '');
        }
    }
    return $out;
}

/** "UNO" -> "UN" delante de MIL/MILLON(ES): "VEINTIÚN MIL", "TREINTA Y UN MIL". */
function pdfret_apocope($s)
{
    if ($s === 'UNO') { return 'UN'; }
    $s = preg_replace('/VEINTIUNO$/u', 'VEINTIÚN', $s);
    $s = preg_replace('/ UNO$/u', ' UN', $s);
    return $s;
}

/** Entero >= 0 en palabras, agrupando por millones y miles. */
function pdfret_letrasEntero($n)
{
    $n = (int) $n;
    if ($n === 0) { return 'CERO'; }

    $millones = intdiv($n, 1000000);
    $miles    = intdiv($n % 1000000, 1000);
    $resto    = $n % 1000;

    $partes = [];
    if ($millones) {
        $partes[] = ($millones === 1)
            ? 'UN MILLÓN'
            : pdfret_apocope(pdfret_letrasEntero($millones)) . ' MILLONES';
    }
    if ($miles) {
        $partes[] = ($miles === 1)
            ? 'MIL'
            : pdfret_apocope(pdfret_letrasCentena($miles)) . ' MIL';
    }
    if ($resto) {
        $partes[] = pdfret_letrasCentena($resto);
    }
    return trim(implode(' ', $partes));
}

/** El total a pagar en palabras, como lo pide el formulario del cliente. */
function pdfret_numeroALetras($numero)
{
    $n = (int) round((float) $numero);
    if ($n <= 0) { return 'CERO PESOS M/CTE.'; }
    return pdfret_letrasEntero($n) . ' PESOS M/CTE.';
}


/* ===========================================================================
   PERFIL DEL CONTRIBUYENTE (para el formato completo)

   El formulario completo (Hoja1 (2) del Excel del cliente) pide, ademas del
   nombre y el NIT, el nombre del establecimiento, la actividad economica
   principal y la secundaria con su codigo, el numero de establecimientos y el
   regimen. Todo sale del RIT; no se captura en la retencion. Se resuelve aqui,
   compartido por RETEICA y AUTORRETEICA, con el mismo criterio que el PDF del
   ICA (declaracion.php): la actividad "principal" es la primera del RIT.

   Ojo: la clasificacion COMERCIAL / SERVICIOS que muestra el Excel NO se puede
   reconstruir -el sistema no guarda ese tipo por actividad-, asi que las
   retenciones se listan en una sola tabla, como en el PDF del ICA.
   =========================================================================== */
function pdfret_perfilContribuyente($con, $idContribuyente, $anio)
{
    $idContribuyente = (int) $idContribuyente;
    $anio            = (int) $anio;

    // Nombre del establecimiento y cuantos hay (activos).
    $est = $con->obnerFila($con->consultar(
        "SELECT TOP 1 est_Nombre FROM ind_establecimientos
          WHERE est_IdContribuyente = ? AND est_Activo = 1
          ORDER BY est_Id",
        [$idContribuyente]
    ));
    $conteo = $con->obnerFila($con->consultar(
        "SELECT COUNT(*) AS n FROM ind_establecimientos
          WHERE est_IdContribuyente = ? AND est_Activo = 1",
        [$idContribuyente]
    ));

    /* Actividades del RIT, la mas reciente que no pase del año declarado (mismo
       criterio que el catalogo: pedir literalmente el año podria devolver vacio).
       La primera es la principal; la segunda, la secundaria. */
    $acts = [];
    $stmt = $con->consultar(
        "SELECT ac.acc_Codigo, ac.acc_Nombre
           FROM ind_actividad_contribuyente atc
           INNER JOIN ind_actividadescomercio ac ON ac.acc_Id = atc.atc_IdCodigoActividad
          WHERE atc.atc_IdContribuyente = ?
            AND atc.atc_Anio = (
                SELECT MAX(a2.atc_Anio) FROM ind_actividad_contribuyente a2
                 WHERE a2.atc_IdContribuyente = atc.atc_IdContribuyente
                   AND a2.atc_Anio <= ?)
          ORDER BY atc.atc_Id",
        [$idContribuyente, $anio]
    );
    while ($a = $con->obnerFila($stmt)) { $acts[] = $a; }

    $fmtAct = function ($a) {
        if (!$a) { return ['codigo' => '', 'nombre' => '']; }
        return ['codigo' => (string) $a['acc_Codigo'], 'nombre' => (string) $a['acc_Nombre']];
    };

    return [
        'establecimiento'   => $est ? (string) $est['est_Nombre'] : '',
        'num_establec'      => $conteo ? (int) $conteo['n'] : 0,
        'act_principal'     => $fmtAct($acts[0] ?? null),
        'act_secundaria'    => $fmtAct($acts[1] ?? null),
        'regimen'           => pdfret_regimenContribuyente($con, $idContribuyente),
    ];
}

/**
 * El regimen (COMUN / ESPECIAL / GRAN CONTRIBUYENTE) marcado.
 *
 * Se toma de ind_RegimenTributario / ind_IdRegimen, pero HOY esos campos vienen
 * casi siempre vacios o con texto libre ("ORDINARIO,RESP_IVA"): el RIT no captura
 * el regimen de ICA de forma estructurada. Por eso se marca COMUN por defecto
 * -que es el caso de la inmensa mayoria- y solo se cambia si el texto dice
 * explicitamente ESPECIAL o GRAN CONTRIBUYENTE. Devuelve 'comun'|'especial'|'gran'.
 * Cuando el municipio empiece a capturarlo, este es el unico sitio a tocar.
 */
function pdfret_regimenContribuyente($con, $idContribuyente)
{
    $c = $con->obnerFila($con->consultar(
        "SELECT ind_RegimenTributario, ind_IdRegimen
           FROM ind_contribuyentes WHERE ind_Id = ?",
        [(int) $idContribuyente]
    ));
    $texto = mb_strtoupper((string) ($c['ind_RegimenTributario'] ?? ''), 'UTF-8');

    if (strpos($texto, 'GRAN') !== false)     { return 'gran'; }
    if (strpos($texto, 'ESPECIAL') !== false) { return 'especial'; }
    return 'comun';
}


/** Ciudad y departamento del contribuyente desde ind_IdCiudad (catalogo DIVIPOLA
 *  conf_ciudades). El formato completo de autorretencion los pide por separado. */
function pdfret_ciudadDepto($con, $idCiudad)
{
    $idCiudad = (int) $idCiudad;
    if ($idCiudad <= 0) { return ['ciudad' => '', 'departamento' => '']; }

    $c = $con->obnerFila($con->consultar(
        "SELECT ciu_Nombre, ciu_Departamento FROM conf_ciudades WHERE ciu_Id = ?",
        [$idCiudad]
    ));
    return [
        'ciudad'       => $c ? (string) $c['ciu_Nombre'] : '',
        'departamento' => $c ? (string) $c['ciu_Departamento'] : '',
    ];
}
