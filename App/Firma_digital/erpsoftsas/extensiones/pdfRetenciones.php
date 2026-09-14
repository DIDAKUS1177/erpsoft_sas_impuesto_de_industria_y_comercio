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
    return '
<style>
    td { vertical-align: top; font-size: 6.5px; }
    .tituloPrincipal { font-size: 11px; font-weight: bold; }
    .sub { font-size: 5.5px; }
</style>

<table border="0" cellpadding="2" width="100%">
<tr>
    <td width="12%" rowspan="2" align="center">
        <img src="' . pdfret_rutaEscudo() . '" width="52">
        <div style="font-size:5px; text-align:center;">NIT ' . htmlspecialchars(MUNICIPIO_NIT) . '</div>
    </td>
    <td class="tituloPrincipal" width="88%" align="center">
        <b>' . htmlspecialchars(mb_strtoupper(MUNICIPIO_NOMBRE, 'UTF-8')) . '</b><br>
        SECRETARÍA DE HACIENDA<br>
        ' . htmlspecialchars($titulo) . '<br>
        <span class="sub">' . htmlspecialchars($subtitulo) . '</span>
    </td>
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
 * El sello va a 24x24mm. Antes estaba a 16; el cliente pidio (2026-09-14) que
 * fuera "un poco mas grande". En el ICA se probo a 65 y a 30 y la fila crecia
 * tanto que empujaba el codigo de barras fuera del papel, PERO el ICA es oficio
 * casi lleno; estos formularios son carta y cierran en 176mm (reteica) y 240mm
 * (autorreteica) sobre 279.4mm, asi que +8mm de fila caben con holgura. No subir
 * mucho mas sin medir con ?medir=1 sobre una declaracion PRESENTADA (el sello
 * solo ocupa alto cuando hay firma).
 */
function pdfret_firmas($firmaDeclarante, $firmaContador, $fechaSello, $nombreDeclarante, $datosContador)
{
    $html = '
<table border="1" cellpadding="2" width="100%">
<tr>
    <td width="5%" rowspan="3" bgcolor="#e1dada"></td>
    <td width="47%"><b>FIRMA DEL DECLARANTE</b><br>';

    if ($firmaDeclarante) {
        $html .= '<div align="center"><img src="' . MUNICIPIO_SELLO_FIRMA . '" width="24" height="24"><br>'
               . '<span style="font-size:8px;">'
               . htmlspecialchars($firmaDeclarante['fd_NombreUsuario'])
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
        $html .= '<div align="center"><img src="' . MUNICIPIO_SELLO_FIRMA . '" width="24" height="24"><br>'
               . '<span style="font-size:8px;">'
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
