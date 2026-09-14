<?php
/*
 * ============================================================================
 * FORMULARIO IMPRESO DE RETENCIÓN DE INDUSTRIA Y COMERCIO (RETEICA)
 * ============================================================================
 *
 * Mensual. Lo presenta el AGENTE RETENEDOR: quien al pagarle a terceros les
 * descontó el ICA y ahora se lo entrega al municipio.
 *
 * Las utilidades -encabezado, firmas, marca de agua, codigo de barras y la
 * guarda de acceso- estan en pdfRetenciones.php. Aqui solo va la maqueta de
 * este formulario.
 *
 * LAS CASILLAS SON LAS DEL PAPEL. La columna ret_ValorConcepto17 es la casilla
 * 17 del formulario; no hay traduccion de por medio. Ver la migracion 030.
 * ============================================================================
 */

require_once __DIR__ . '/pdfRetenciones.php';

$con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

/* Acepta ?id= y tambien ?ret_Id=, por si algun enlace viejo usa la forma
   larga; el ICA usa dec_Id y conviene no obligar a recordar cual es cual. */
$id  = $_GET['id'] ?? $_GET['ret_Id'] ?? 0;
$row = pdfret_filaAutorizada($con, 'ind_reteica', 'ret_', $id);

$contribuyente = $con->obnerFila($con->consultar(
    "SELECT * FROM ind_contribuyentes WHERE ind_Id = ?",
    [(int) $row['ret_IdContribuyente']]
));
if (!$contribuyente) { $contribuyente = []; }

/* Datos del RIT que pide el FORMATO COMPLETO del cliente (nombre del
   establecimiento, actividad economica principal y secundaria con su codigo,
   numero de establecimientos y regimen). No se capturan en la retencion: salen
   del RIT, igual que en el PDF del ICA. Compartido con autorreteica; la logica
   vive en pdfRetenciones.php. */
$perfil = pdfret_perfilContribuyente($con, $row['ret_IdContribuyente'], $row['ret_Anio']);

/* Las filas de actividad, con el nombre del catalogo. LEFT JOIN: si alguna vez
   se desactivara una actividad del catalogo, la declaracion ya presentada tiene
   que seguir imprimiendose -con la casilla en blanco, pero imprimiendose-. */
$actividades = [];
$stmt = $con->consultar(
    "SELECT a.*, ac.acc_Codigo, ac.acc_Nombre
       FROM ind_reteica_actividades a
       LEFT JOIN ind_actividadescomercio ac ON ac.acc_Id = a.rea_IdActividad
      WHERE a.rea_IdReteica = ? AND a.rea_Activo = 1
      ORDER BY ac.acc_Codigo",
    [(int) $row['ret_Id']]
);
while ($f = $con->obnerFila($stmt)) { $actividades[] = $f; }

$numero        = $row['ret_NumeroDeclaracion'] ?: $row['ret_Id'];
$estaPresentada = ((int) $row['ret_Estado'] === 2);

$firmas     = pdfret_firmasDe($con, $numero, 'RETEICA');
$fechaSello = pdfret_fechaSello($row['ret_FechaPresentacion'], $firmas['declarante']);

$MESES = [1 => 'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO',
          'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];
$mes = $MESES[(int) $row['ret_Periodo']] ?? (string) $row['ret_Periodo'];

$esCorreccion = !empty($row['ret_Corrige']);

if ($estaPresentada) {
    $marcaAgua = !empty($row['ret_Pagado']) ? 'PAGADA' : 'PRESENTADA';
} else {
    $marcaAgua = 'BORRADOR';
}

$pdf = pdfret_nuevoPdf();

/* ===========================================================================
   ENCABEZADO Y DATOS DEL PERIODO
   =========================================================================== */

$html = pdfret_encabezado(
    'DECLARACIÓN DE RETENCIÓN DEL IMPUESTO DE INDUSTRIA Y COMERCIO',
    'Y SU COMPLEMENTARIO DE AVISOS Y TABLEROS — Declaración mensual'
);

/* Marca de casilla tipo [X] / [ ]. &#160; (espacio duro) para que la celda no
   se colapse cuando va vacia. */
$marca    = function ($activo) { return $activo ? 'X' : '&#160;'; };
$fechaDoc = $fechaSello ? substr($fechaSello, 0, 10) : date('d/m/Y');
$regimen  = $perfil['regimen'];

/* Vigencia fiscal / fecha / No. de formulario, y el RÉGIMEN. El régimen sale del
   RIT (ver pdfret_regimenContribuyente): hoy casi siempre COMÚN, porque el RIT no
   lo captura estructurado; cuando se capture, se marcará solo. */
$html .= '
<table border="1" cellpadding="2" width="100%">
<tr bgcolor="#e1dada">
    <td width="14%"><b>FORMULARIO ÚNICO</b></td>
    <td width="18%"><b>VIGENCIA FISCAL</b></td>
    <td width="12%" align="center">' . (int) $row['ret_Anio'] . '</td>
    <td width="9%"><b>FECHA</b></td>
    <td width="20%" align="center">' . htmlspecialchars($fechaDoc) . '</td>
    <td width="12%"><b>No. FORM.</b></td>
    <td width="15%" align="center">' . htmlspecialchars((string) $numero) . '</td>
</tr>
</table>

<table border="1" cellpadding="2" width="100%">
<tr>
    <td width="16%" bgcolor="#e1dada"><b>RÉGIMEN</b></td>
    <td width="26%">RÉGIMEN COMÚN &nbsp;[<b>' . $marca($regimen === 'comun') . '</b>]</td>
    <td width="28%">RÉGIMEN ESPECIAL &nbsp;[<b>' . $marca($regimen === 'especial') . '</b>]</td>
    <td width="30%">GRAN CONTRIBUYENTE &nbsp;[<b>' . $marca($regimen === 'gran') . '</b>]</td>
</tr>
</table>

<br>
';

$pdf->writeHTML($html, true, false, true, false, '');
$ySecA = $pdf->GetY() - DESFASE_GETY_RET;

/* ===========================================================================
   A. AGENTE RETENEDOR

   Formato completo del cliente (Hoja1 (2) del Excel): año, NIT/DV, correo,
   razón social, nombre del establecimiento, actividad económica principal y
   secundaria con código, número de establecimientos, dirección y teléfono, y
   el tipo de declaración (con/sin pago o corrección).

   Todo sale del RIT y no se edita aquí: si algo está mal se corrige en el RIT y
   se reimprime. Tener dos sitios donde editar el mismo dato es como se acaba con
   dos versiones distintas del mismo contribuyente.
   =========================================================================== */

$dv = (isset($contribuyente['ind_DV']) && $contribuyente['ind_DV'] !== null)
      ? (string) (int) $contribuyente['ind_DV'] : '';

$html = '
<table border="1" cellpadding="2" width="100%">
<tr>
    <td width="5%" bgcolor="#e1dada"></td>
    <td width="10%"><b>1. AÑO</b></td>
    <td width="13%" align="center">' . (int) $row['ret_Anio'] . '</td>
    <td width="8%"><b>2. NIT</b></td>
    <td width="20%">' . htmlspecialchars((string) ($contribuyente['ind_NumeroIdentificacion'] ?? '')) . '</td>
    <td width="7%"><b>D.V.</b></td>
    <td width="6%" align="center">' . htmlspecialchars($dv) . '</td>
    <td width="11%"><b>3. CORREO</b></td>
    <td width="20%">' . htmlspecialchars((string) ($contribuyente['ind_Email'] ?? '')) . '</td>
</tr>
</table>
<table border="1" cellpadding="2" width="100%">
<tr>
    <td width="5%" bgcolor="#e1dada"></td>
    <td width="35%"><b>4. APELLIDOS Y NOMBRES O RAZÓN SOCIAL</b></td>
    <td width="60%">' . htmlspecialchars(pdfret_nombreContribuyente($contribuyente)) . '</td>
</tr>
<tr>
    <td width="5%" bgcolor="#e1dada"></td>
    <td width="35%"><b>5. RAZÓN COMERCIAL / NOMBRE DEL ESTABLECIMIENTO</b></td>
    <td width="60%">' . htmlspecialchars($perfil['establecimiento']) . '</td>
</tr>
<tr>
    <td width="5%" bgcolor="#e1dada"></td>
    <td width="35%"><b>6. ACTIVIDAD ECONÓMICA PRINCIPAL</b></td>
    <td width="45%">' . htmlspecialchars($perfil['act_principal']['nombre']) . '</td>
    <td width="8%"><b>CÓDIGO</b></td>
    <td width="7%" align="center">' . htmlspecialchars($perfil['act_principal']['codigo']) . '</td>
</tr>
<tr>
    <td width="5%" bgcolor="#e1dada"></td>
    <td width="26%"><b>ACTIVIDAD SECUNDARIA</b></td>
    <td width="34%">' . htmlspecialchars($perfil['act_secundaria']['nombre']) . '</td>
    <td width="7%"><b>CÓD.</b></td>
    <td width="8%" align="center">' . htmlspecialchars($perfil['act_secundaria']['codigo']) . '</td>
    <td width="13%"><b>No. ESTABLEC.</b></td>
    <td width="7%" align="center">' . (int) $perfil['num_establec'] . '</td>
</tr>
<tr>
    <td width="5%" bgcolor="#e1dada"></td>
    <td width="14%"><b>7. DIRECCIÓN</b></td>
    <td width="42%">' . htmlspecialchars((string) ($contribuyente['ind_Direccion'] ?? '')) . '</td>
    <td width="15%"><b>8. TELÉFONO</b></td>
    <td width="24%">' . htmlspecialchars((string) ($contribuyente['ind_Telefono'] ?? '')) . '</td>
</tr>
</table>
<table border="1" cellpadding="2" width="100%">
<tr>
    <td width="5%" bgcolor="#e1dada"></td>
    <td width="9%"><b>PERÍODO</b></td>
    <td width="16%" align="center"><b>' . htmlspecialchars($mes) . '</b></td>
    <td width="22%">CON PAGO &nbsp;[<b>' . $marca(!empty($row['ret_Pagado'])) . '</b>]</td>
    <td width="17%">SIN PAGO &nbsp;[<b>' . $marca(empty($row['ret_Pagado'])) . '</b>]</td>
    <td width="17%">CORRECCIÓN &nbsp;[<b>' . $marca($esCorreccion) . '</b>]</td>
    <td width="14%">No. ' . htmlspecialchars((string) ($row['ret_Corrige'] ?? '')) . '</td>
</tr>
</table>

<br>
';

$pdf->writeHTML($html, true, false, true, false, '');
$ySecB = $pdf->GetY() - DESFASE_GETY_RET;
pdfret_textoVertical($pdf, 'A. RETENEDOR', 10, $ySecA, $ySecB);

/* ===========================================================================
   B. RETENCIONES PRACTICADAS
   =========================================================================== */

$html = '
<table border="1" cellpadding="2" width="100%">
<tr bgcolor="#e1dada">
    <td width="5%" rowspan="' . (count($actividades) + 2) . '" bgcolor="#e1dada"></td>
    <td width="10%" align="center"><b>CÓDIGO</b></td>
    <td width="45%"><b>ACTIVIDAD</b></td>
    <td width="10%" align="center"><b>11. TARIFA<br>(x mil)</b></td>
    <td width="15%" align="right"><b>12. BASE GRAVABLE</b></td>
    <td width="15%" align="right"><b>13. RETENCIÓN</b></td>
</tr>
';

if (!$actividades) {
    /* Una declaracion sin actividades es rara pero posible -un mes sin
       retenciones-. Se dice, en vez de dejar la tabla muda. */
    $html .= '<tr><td colspan="5" align="center"><i>Sin retenciones practicadas en el período.</i></td></tr>';
} else {
    foreach ($actividades as $a) {
        $html .= '
<tr>
    <td align="center">' . htmlspecialchars((string) $a['acc_Codigo']) . '</td>
    <td>' . htmlspecialchars((string) $a['acc_Nombre']) . '</td>
    <td align="center">' . pdfret_porMil($a['rea_Tarifa']) . '</td>
    <td align="right">' . pdfret_pesos($a['rea_BaseGravable']) . '</td>
    <td align="right">' . pdfret_pesos($a['rea_ValorRetencion']) . '</td>
</tr>';
    }
}

/* El total de la tabla es la casilla 14, y se repite abajo en la liquidacion.
   Se imprime en los dos sitios porque asi esta el formulario del cliente. */
$html .= '
<tr bgcolor="#f2f2f2">
    <td colspan="3" align="right"><b>TOTAL</b></td>
    <td align="right"><b>' . pdfret_pesos(array_sum(array_map(function ($a) {
            return $a['rea_BaseGravable'];
        }, $actividades))) . '</b></td>
    <td align="right"><b>' . pdfret_pesos($row['ret_ValorConcepto14']) . '</b></td>
</tr>
</table>

<br>
';

$pdf->writeHTML($html, true, false, true, false, '');
$ySecC = $pdf->GetY() - DESFASE_GETY_RET;
pdfret_textoVertical($pdf, 'B. RETENCIONES', 10, $ySecB, $ySecC);

/* ===========================================================================
   C. LIQUIDACIÓN PRIVADA

   Las cuatro casillas salen del catalogo ind_renglones_retencion, no de una
   lista escrita aqui: si mañana se agrega o se renombra un renglon, el papel lo
   refleja sin tocar este archivo. Es el mismo motivo por el que las formulas
   viven en la base.
   =========================================================================== */

$renglones = [];
$stmt = $con->consultar(
    "SELECT ren_Codigo, ren_Nombre, ren_Formula
       FROM ind_renglones_retencion
      WHERE ren_Modulo = 'RETEICA' AND ren_Anio = ? AND ren_Estado = 1
      ORDER BY ren_Orden",
    [(int) $row['ret_Anio']]
);
while ($r = $con->obnerFila($stmt)) { $renglones[] = $r; }

$html = '
<table border="1" cellpadding="2" width="100%">
<tr bgcolor="#e1dada">
    <td width="5%" rowspan="' . (count($renglones) + 1) . '" bgcolor="#e1dada"></td>
    <td width="7%" align="center"><b>No.</b></td>
    <td width="68%"><b>CONCEPTO</b></td>
    <td width="20%" align="right"><b>VALOR</b></td>
</tr>
';

foreach ($renglones as $r) {
    $codigo  = (int) $r['ren_Codigo'];
    $columna = 'ret_ValorConcepto' . $codigo;
    $esTotal = ($codigo === 17);

    /* Un renglon calculado sin formula todavia no se puede liquidar. Se marca
       en el papel en vez de imprimir un cero limpio: un cero sin explicacion se
       lee como "no debe nada", que es justo la conclusion equivocada. */
    $pendiente = ($r['ren_Formula'] === null);

    $html .= '
<tr' . ($esTotal ? ' bgcolor="#f2f2f2"' : '') . '>
    <td align="center">' . $codigo . '</td>
    <td>' . ($esTotal ? '<b>' : '') . htmlspecialchars($r['ren_Nombre']) . ($esTotal ? '</b>' : '')
          . ($pendiente ? ' <i>(cálculo pendiente de confirmación)</i>' : '') . '</td>
    <td align="right">' . ($esTotal ? '<b>' : '')
          . pdfret_pesos(isset($row[$columna]) ? $row[$columna] : 0)
          . ($esTotal ? '</b>' : '') . '</td>
</tr>';
}

$html .= '
</table>

<table border="1" cellpadding="3" width="100%">
<tr>
    <td width="26%" bgcolor="#e1dada"><b>TOTAL A PAGAR (EN LETRAS)</b></td>
    <td width="74%"><b>' . htmlspecialchars(pdfret_numeroALetras($row['ret_ValorConcepto17'])) . '</b></td>
</tr>
</table>

<br>
';

$pdf->writeHTML($html, true, false, true, false, '');
$ySecD = $pdf->GetY() - DESFASE_GETY_RET;
pdfret_textoVertical($pdf, 'C. LIQUIDACIÓN', 10, $ySecC, $ySecD);

/* ===========================================================================
   D. FIRMAS
   =========================================================================== */

/* Persona juridica: firma el REPRESENTANTE LEGAL, no la razon social (pedido del
   cliente 2026-09-14). pdfret_firmanteDeclarante decide segun ind_Persona. */
$firmante = pdfret_firmanteDeclarante($contribuyente);

$pdf->writeHTML(
    pdfret_firmas(
        $firmas['declarante'],
        $firmas['contador'],
        $fechaSello,
        $firmante['nombre'],
        [
            'doc_declarante' => $firmante['documento'],
            /* Manda el revisor fiscal y, si no hay, el contador. Lo fijo el
               cliente el 2026-08-26: cuando estan los dos firma el de mayor
               responsabilidad. */
            'nombre'  => trim((string) ($contribuyente['ind_NombreRevisor'] ?? '')) !== ''
                       ? (string) $contribuyente['ind_NombreRevisor']
                       : (string) ($contribuyente['ind_NombreContador'] ?? ''),
            'cedula'  => trim((string) ($contribuyente['ind_NombreRevisor'] ?? '')) !== ''
                       ? (string) ($contribuyente['ind_CedulaRevisor'] ?? '')
                       : (string) ($contribuyente['ind_CedulaContador'] ?? ''),
            'tarjeta' => trim((string) ($contribuyente['ind_NombreRevisor'] ?? '')) !== ''
                       ? (string) ($contribuyente['ind_TarjetaProfRevisor'] ?? '')
                       : (string) ($contribuyente['ind_TarjetaProfContador'] ?? ''),
        ]
    ),
    true, false, true, false, ''
);

/* El rotulo cubre solo la tabla de firmas. El bloque de codigo de barras que
   va debajo no tiene columna gris donde apoyarlo, y el texto rotado terminaba
   encima del rotulo "CÓDIGO DE BARRAS". */
$yFinFirmas = $pdf->GetY() - DESFASE_GETY_RET;
pdfret_textoVertical($pdf, 'D. FIRMAS', 10, $ySecD, $yFinFirmas);

$yFin = pdfret_bloqueBarras($pdf, $numero, $row['ret_ValorConcepto17'], $estaPresentada);

/* La marca de agua va AL FINAL. Dibujarla justo despues del AddPage cuelga el
   worker de PHP-FPM indefinidamente; ver pdfRetenciones.php. */
pdfret_marcaDeAgua($pdf, $marcaAgua);

/* Sonda de medicion. SetAutoPageBreak esta en false, asi que TCPDF no avisa si
   el contenido se sale del papel: se dibuja fuera y no se ve. Con ?medir=1 se
   imprime donde cerro el formulario en vez de generar el PDF. */
if (!empty($_GET['medir'])) {
    header('Content-type: text/plain');
    echo 'cierra en ' . round($yFin, 2) . 'mm de ' . RET_ALTO_PAGINA . 'mm'
       . ' (holgura ' . round(RET_ALTO_PAGINA - $yFin, 2) . 'mm)';
    exit;
}

$pdf->Output('RETEICA_' . $numero . '.pdf', 'I');
