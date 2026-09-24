<?php
/*
 * ============================================================================
 * FORMULARIO IMPRESO DE AUTORRETENCIÓN DE INDUSTRIA Y COMERCIO
 * ============================================================================
 *
 * Bimestral. Aquí el contribuyente se retiene a SÍ MISMO sobre sus propios
 * ingresos, y por eso el formulario trae una sección de ingresos completa
 * (casillas 9 a 13) que el de retención no tiene.
 *
 * Las utilidades comunes están en pdfRetenciones.php.
 *
 * OJO CON LAS CASILLAS PENDIENTES. Cinco de la liquidación (15, 16, 19, 20 y
 * 23) todavía no tienen fórmula acordada: los tres documentos del cliente se
 * contradicen y una de las versiones suma el impuesto de energía dos veces.
 * Salen en cero y el papel lo dice. Imprimir un cero mudo sería peor: se lee
 * como "no debe nada".
 * ============================================================================
 */

require_once __DIR__ . '/pdfRetenciones.php';

$con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

$id  = $_GET['id'] ?? $_GET['aut_Id'] ?? 0;
$row = pdfret_filaAutorizada($con, 'ind_autorreteica', 'aut_', $id);

$contribuyente = $con->obnerFila($con->consultar(
    "SELECT * FROM ind_contribuyentes WHERE ind_Id = ?",
    [(int) $row['aut_IdContribuyente']]
));
if (!$contribuyente) { $contribuyente = []; }

/* Ciudad y departamento para el declarante (formato completo del cliente). */
$ubic = pdfret_ciudadDepto($con, $contribuyente['ind_IdCiudad'] ?? 0);

$actividades = [];
$stmt = $con->consultar(
    "SELECT a.*, ac.acc_Codigo, ac.acc_Nombre
       FROM ind_autorreteica_actividades a
       LEFT JOIN ind_actividadescomercio ac ON ac.acc_Id = a.aua_IdActividad
      WHERE a.aua_IdAutorreteica = ? AND a.aua_Activo = 1
      ORDER BY ac.acc_Codigo",
    [(int) $row['aut_Id']]
);
while ($f = $con->obnerFila($stmt)) { $actividades[] = $f; }

$numero         = $row['aut_NumeroDeclaracion'] ?: $row['aut_Id'];
$estaPresentada = ((int) $row['aut_Estado'] === 2);

$firmas     = pdfret_firmasDe($con, $numero, 'AUTORRETEICA');
$fechaSello = pdfret_fechaSello($row['aut_FechaPresentacion'], $firmas['declarante']);

$BIMESTRES = [1 => 'ENERO - FEBRERO', 'MARZO - ABRIL', 'MAYO - JUNIO',
              'JULIO - AGOSTO', 'SEPTIEMBRE - OCTUBRE', 'NOVIEMBRE - DICIEMBRE'];
$bimestre = $BIMESTRES[(int) $row['aut_Periodo']] ?? (string) $row['aut_Periodo'];

$esCorreccion = !empty($row['aut_Corrige']);

if ($estaPresentada) {
    $marcaAgua = !empty($row['aut_Pagado']) ? 'PAGADA' : 'PRESENTADA';
} else {
    $marcaAgua = 'BORRADOR';
}

$pdf = pdfret_nuevoPdf();

/* ===========================================================================
   ENCABEZADO
   =========================================================================== */

$html = pdfret_encabezado(
    'DECLARACIÓN DE AUTORRETENCIÓN DEL IMPUESTO DE INDUSTRIA Y COMERCIO',
    'Y SU COMPLEMENTARIO DE AVISOS Y TABLEROS — Declaración bimestral'
);

$marca      = function ($activo) { return $activo ? 'X' : '&#160;'; };
$fechaDoc   = $fechaSello ? substr($fechaSello, 0, 10) : date('d/m/Y');
$esJuridica = (int) ($contribuyente['ind_Persona'] ?? 0) === 2;
$dv = (isset($contribuyente['ind_DV']) && $contribuyente['ind_DV'] !== null)
      ? (string) (int) $contribuyente['ind_DV'] : '';

/* El encabezado (escudo + títulos) se pinta solo y $ySecA se toma ANTES de
   "INFORMACIÓN BÁSICA DEL DECLARANTE": esa franja y los datos son una misma
   sección, van bajo la banda "A. DECLARANTE" y en la MISMA llamada a writeHTML,
   para que no quede aire entre ellas. Igual que en retención (retro cliente
   2026-09-23: "lo de arriba y lo de A es parte de una misma"). */
$pdf->writeHTML($html, true, false, true, false, '');
$ySecA = $pdf->GetY() - DESFASE_GETY_RET;

/* ===========================================================================
   A. DECLARANTE (del RIT, no se edita aquí)

   Igual al formato del Excel del cliente (hoja de AUTORRETEICA): tipo de
   documento (NIT/C.C./OTRO), nombre o razón social, dirección, ciudad y
   departamento, correo, teléfono, vigencia fiscal, corrección y fecha, y la
   rejilla de los seis bimestres con el declarado resaltado.
   =========================================================================== */

// Rejilla de PERÍODOS: los 6 bimestres, resaltado el que se declara.
$celdasPeriodo = '';
foreach ($BIMESTRES as $nb => $nombreBim) {
    $sel = ((int) $row['aut_Periodo'] === $nb);
    $celdasPeriodo .= '<td width="14%" align="center"' . ($sel ? ' bgcolor="#cfe8cf"' : '') . '>'
                    . ($sel ? '<b>' : '') . htmlspecialchars($nombreBim) . ($sel ? '</b>' : '')
                    . '</td>';
}

/* UNA sola tabla con la banda gris en UNA celda (rowspan), igual que la sección A
   del ICA y de retención: con una tabla por fila, las líneas horizontales
   cruzaban el rótulo "A. DECLARANTE" (retro cliente 2026-09-24). Cada fila suma
   95% + la banda; si se agrega o quita una fila, el rowspan cambia con ella. */
$html = '
<style> td { font-size: 6.5px; } </style>
<table border="1" cellpadding="2" width="100%">
<tr bgcolor="#cae6e7">
    <td width="5%" rowspan="7" bgcolor="#e1dada"></td>
    <td width="95%" align="center"><b>INFORMACIÓN BÁSICA DEL DECLARANTE</b></td>
</tr>
<tr>
    <td width="19%"><b>1. TIPO DE DOCUMENTO</b></td>
    <td width="10%">NIT [<b>' . $marca($esJuridica) . '</b>]</td>
    <td width="10%">C.C. [<b>' . $marca(!$esJuridica) . '</b>]</td>
    <td width="11%">OTRO [<b>&#160;</b>]</td>
    <td width="6%"><b>D.V.</b></td>
    <td width="6%" align="center">' . htmlspecialchars($dv) . '</td>
    <td width="6%"><b>No.</b></td>
    <td width="27%">' . htmlspecialchars((string) ($contribuyente['ind_NumeroIdentificacion'] ?? '')) . '</td>
</tr>
<tr>
    <td width="35%"><b>2. NOMBRE O RAZÓN SOCIAL</b></td>
    <td width="60%">' . htmlspecialchars(pdfret_nombreContribuyente($contribuyente)) . '</td>
</tr>
<tr>
    <td width="13%"><b>3. DIRECCIÓN</b></td>
    <td width="37%">' . htmlspecialchars((string) ($contribuyente['ind_Direccion'] ?? '')) . '</td>
    <td width="10%"><b>4. CIUDAD</b></td>
    <td width="17%">' . htmlspecialchars($ubic['ciudad']) . '</td>
    <td width="10%"><b>5. DPTO.</b></td>
    <td width="8%">' . htmlspecialchars($ubic['departamento']) . '</td>
</tr>
<tr>
    <td width="18%"><b>6. CORREO ELECTRÓNICO</b></td>
    <td width="44%">' . htmlspecialchars((string) ($contribuyente['ind_Email'] ?? '')) . '</td>
    <td width="13%"><b>7. TELÉFONO</b></td>
    <td width="20%">' . htmlspecialchars((string) ($contribuyente['ind_Telefono'] ?? '')) . '</td>
</tr>
<tr>
    <td width="20%"><b>8. VIGENCIA FISCAL</b></td>
    <td width="10%" align="center">' . (int) $row['aut_Anio'] . '</td>
    <td width="18%"><b>10. CORRECCIÓN</b> [<b>' . $marca($esCorreccion) . '</b>]</td>
    <td width="6%"><b>N°</b></td>
    <td width="11%">' . htmlspecialchars((string) ($row['aut_Corrige'] ?? '')) . '</td>
    <td width="10%"><b>9. FECHA</b></td>
    <td width="20%" align="center">' . htmlspecialchars($fechaDoc) . '</td>
</tr>
<tr>
    <td width="11%" bgcolor="#e1dada"><b>PERÍODO</b></td>
    ' . $celdasPeriodo . '
</tr>
</table>

<br>
';

$pdf->writeHTML($html, true, false, true, false, '');
$ySecB = $pdf->GetY() - DESFASE_GETY_RET;
pdfret_textoVertical($pdf, 'A. DECLARANTE', 10, $ySecA, $ySecB);

/* ===========================================================================
   Los renglones del catálogo, indexados por casilla.

   Se leen una sola vez y se reparten entre las dos secciones que los usan
   -ingresos y liquidación-, porque en el papel están separados pero en la base
   son la misma tabla.
   =========================================================================== */

$renglones = [];
$stmt = $con->consultar(
    "SELECT ren_Codigo, ren_Nombre, ren_Formula
       FROM ind_renglones_retencion
      WHERE ren_Modulo = 'AUTORRETEICA' AND ren_Anio = ? AND ren_Estado = 1
      ORDER BY ren_Orden",
    [(int) $row['aut_Anio']]
);
while ($r = $con->obnerFila($stmt)) { $renglones[(int) $r['ren_Codigo']] = $r; }

/**
 * Una fila de renglón. $destacar pone la fila en negrita y con fondo: se usa en
 * los subtotales y en el total a pagar.
 */
function autret_fila($renglones, $row, $codigo, $destacar = false)
{
    if (!isset($renglones[$codigo])) { return ''; }

    $r         = $renglones[$codigo];
    $columna   = 'aut_ValorConcepto' . $codigo;
    $pendiente = ($r['ren_Formula'] === null);
    $b         = $destacar ? 'b' : 'span';

    return '
<tr' . ($destacar ? ' bgcolor="#f2f2f2"' : '') . '>
    <td align="center">' . $codigo . '</td>
    <td><' . $b . '>' . htmlspecialchars($r['ren_Nombre']) . '</' . $b . '>'
       . ($pendiente ? ' <i>(cálculo pendiente de confirmación)</i>' : '') . '</td>
    <td align="right"><' . $b . '>'
       . pdfret_pesos(isset($row[$columna]) ? $row[$columna] : 0)
       . '</' . $b . '></td>
</tr>';
}

/* ===========================================================================
   B. INGRESOS DEL BIMESTRE (casillas 9 a 13)
   =========================================================================== */

$html = '
<table border="1" cellpadding="2" width="100%">
<tr bgcolor="#e1dada">
    <td width="5%" rowspan="6" bgcolor="#e1dada"></td>
    <td width="7%" align="center"><b>No.</b></td>
    <td width="68%"><b>INGRESOS</b></td>
    <td width="20%" align="right"><b>VALOR</b></td>
</tr>'
. autret_fila($renglones, $row, 9)
. autret_fila($renglones, $row, 10)
. autret_fila($renglones, $row, 11)
. autret_fila($renglones, $row, 12)
. autret_fila($renglones, $row, 13, true)
. '
</table>

<br>
';

$pdf->writeHTML($html, true, false, true, false, '');
$ySecC = $pdf->GetY() - DESFASE_GETY_RET;
pdfret_textoVertical($pdf, 'B. INGRESOS', 10, $ySecB, $ySecC);

/* ===========================================================================
   C. ACTIVIDADES

   Precargadas del RIT: son las propias del contribuyente. Él solo escribe los
   ingresos gravados de cada una.
   =========================================================================== */

/* Filas bajo la banda: encabezado + actividades + energía + TOTAL. Sin
   actividades hay UNA fila de mensaje que también cuenta (con count() a secas la
   banda quedaba corta y "C. ACTIVIDADES" se salía de su franja). */
$html = '
<table border="1" cellpadding="2" width="100%">
<tr bgcolor="#e1dada">
    <td width="5%" rowspan="' . (max(1, count($actividades)) + 3) . '" bgcolor="#e1dada"></td>
    <td width="10%" align="center"><b>CÓDIGO</b></td>
    <td width="45%"><b>ACTIVIDAD</b></td>
    <td width="10%" align="center"><b>TARIFA<br>(x mil)</b></td>
    <td width="15%" align="right"><b>INGRESOS GRAVADOS</b></td>
    <td width="15%" align="right"><b>IMPUESTO</b></td>
</tr>
';

if (!$actividades) {
    $html .= '<tr><td colspan="5" align="center"><i>El contribuyente no tiene actividades '
           . 'registradas en el RIT.</i></td></tr>';
} else {
    foreach ($actividades as $a) {
        $html .= '
<tr>
    <td align="center">' . htmlspecialchars((string) $a['acc_Codigo']) . '</td>
    <td>' . htmlspecialchars((string) $a['acc_Nombre']) . '</td>
    <td align="center">' . pdfret_porMil($a['aua_Tarifa']) . '</td>
    <td align="right">' . pdfret_pesos($a['aua_IngresosGravados']) . '</td>
    <td align="right">' . pdfret_pesos($a['aua_ValorImpuesto']) . '</td>
</tr>';
    }
}

/*
 * El impuesto de generación de energía (Ley 56 de 1981) va en esta tabla, en la
 * fila del total y SIN número de casilla: así está en el formulario del
 * cliente. Solo aplica a generadoras.
 *
 * Es el dato de la discrepancia más grave del proyecto: el Excel lo suma dentro
 * de este total y otra vez en la casilla 15, con lo que se cobraría dos veces.
 * Por eso la 15 sigue sin fórmula.
 */
$html .= '
<tr>
    <td colspan="3" align="right">IMPUESTO GENERACIÓN DE ENERGÍA (Ley 56 de 1981)</td>
    <td align="right"></td>
    <td align="right">' . pdfret_pesos($row['aut_ImpuestoEnergia']) . '</td>
</tr>
<tr bgcolor="#f2f2f2">
    <td colspan="3" align="right"><b>TOTAL</b></td>
    <td align="right"><b>' . pdfret_pesos(array_sum(array_map(function ($a) {
            return $a['aua_IngresosGravados'];
        }, $actividades))) . '</b></td>
    <td align="right"><b>' . pdfret_pesos(
            array_sum(array_map(function ($a) { return $a['aua_ValorImpuesto']; }, $actividades))
            + (float) $row['aut_ImpuestoEnergia']
        ) . '</b></td>
</tr>
</table>

<br>
';

$pdf->writeHTML($html, true, false, true, false, '');
$ySecD = $pdf->GetY() - DESFASE_GETY_RET;
pdfret_textoVertical($pdf, 'C. ACTIVIDADES', 10, $ySecC, $ySecD);

/* ===========================================================================
   D. LIQUIDACIÓN PRIVADA (casillas 15 a 23)

   No hay casilla 14 en este formulario: salta de la sección de actividades a
   la 15. El hueco es del formulario, no un olvido.
   =========================================================================== */

$html = '
<table border="1" cellpadding="2" width="100%">
<tr bgcolor="#e1dada">
    <td width="5%" rowspan="10" bgcolor="#e1dada"></td>
    <td width="7%" align="center"><b>No.</b></td>
    <td width="68%"><b>LIQUIDACIÓN PRIVADA</b></td>
    <td width="20%" align="right"><b>VALOR</b></td>
</tr>'
. autret_fila($renglones, $row, 15)
. autret_fila($renglones, $row, 16)
. autret_fila($renglones, $row, 17, true)
. autret_fila($renglones, $row, 18)
. autret_fila($renglones, $row, 19)
. autret_fila($renglones, $row, 20)
. autret_fila($renglones, $row, 21)
. autret_fila($renglones, $row, 22)
. autret_fila($renglones, $row, 23, true)
. '
</table>

<br>
';

$pdf->writeHTML($html, true, false, true, false, '');
$ySecE = $pdf->GetY() - DESFASE_GETY_RET;
pdfret_textoVertical($pdf, 'D. LIQUIDACIÓN', 10, $ySecD, $ySecE);

/* ===========================================================================
   E. FIRMAS
   =========================================================================== */

/* Persona juridica: firma el REPRESENTANTE LEGAL, no la razon social (pedido del
   cliente 2026-09-14). Igual que en reteica.php. */
$firmante = pdfret_firmanteDeclarante($contribuyente);

$htmlFirmas = pdfret_firmas(
        $firmas['declarante'],
        $firmas['contador'],
        $fechaSello,
        $firmante['nombre'],
        [
            'doc_declarante' => $firmante['documento'],
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
    );

/* Firmas y código de barras van juntos: si no caben en lo que queda de esta
   hoja pasan a la siguiente, y la banda "E. FIRMAS" arranca arriba de ella.
   Aquí es donde más se nota: las actividades salen del RIT y un contribuyente
   con varias llenaba la hoja (ver pdfret_saltoSiNoCabe). */
if (pdfret_saltoSiNoCabe($pdf, $htmlFirmas)) {
    $ySecE = $pdf->GetY();
}
$pdf->writeHTML($htmlFirmas, true, false, true, false, '');

/* Ver la nota equivalente en reteica.php: el rotulo cubre solo la tabla de
   firmas, no el bloque de codigo de barras. */
$yFinFirmas = $pdf->GetY() - DESFASE_GETY_RET;
pdfret_textoVertical($pdf, 'E. FIRMAS', 10, $ySecE, $yFinFirmas);

$yFin = pdfret_bloqueBarras($pdf, $numero, $row['aut_ValorConcepto23'], $estaPresentada);

pdfret_marcaDeAgua($pdf, $marcaAgua);

/* Sonda de medición: SetAutoPageBreak está en false y TCPDF no avisa si el
   contenido se sale del papel. Ver reteica.php. */
if (!empty($_GET['medir'])) {
    header('Content-type: text/plain');
    echo 'cierra en ' . round($yFin, 2) . 'mm de ' . RET_ALTO_PAGINA . 'mm'
       . ' (holgura ' . round(RET_ALTO_PAGINA - $yFin, 2) . 'mm) - hojas: '
       . $pdf->getNumPages();
    exit;
}

$pdf->Output('AUTORRETEICA_' . $numero . '.pdf', 'I');
